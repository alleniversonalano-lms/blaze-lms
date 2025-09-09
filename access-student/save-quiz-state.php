<?php
// ==========================================
// File: save-quiz-state.php
// ==========================================
?>
<?php
session_start();
header('Content-Type: application/json');

// Verify user is logged in and is a student
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['quizId'])) {
        throw new Exception('Missing quiz ID');
    }
    
    $quizId = $input['quizId'];
    $userId = $_SESSION['user_id'];
    $answers = $input['answers'] ?? [];
    $currentQuestion = intval($input['currentQuestion'] ?? 0);
    $suspiciousActivity = $input['suspiciousActivity'] ?? [];
    $focusLossCount = intval($input['focusLossCount'] ?? 0);
    $tabSwitchCount = intval($input['tabSwitchCount'] ?? 0);
    $lastActivity = $input['lastActivity'] ?? date('Y-m-d H:i:s');
    
    // Load existing session data
    $sessionJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/sessions/' . $quizId . '_' . $userId . '_session.json';
    
    if (!file_exists($sessionJsonPath)) {
        throw new Exception('Session not found');
    }
    
    $sessionData = json_decode(file_get_contents($sessionJsonPath), true);
    if (!$sessionData) {
        throw new Exception('Invalid session data');
    }
    
    // Check if session is still valid
    if ($sessionData['status'] === 'submitted') {
        throw new Exception('Quiz already submitted');
    }
    
    // Update session data
    $sessionData['answers'] = $answers;
    $sessionData['currentQuestion'] = $currentQuestion;
    $sessionData['lastActivity'] = $lastActivity;
    $sessionData['suspiciousActivity'] = array_merge($sessionData['suspiciousActivity'] ?? [], $suspiciousActivity);
    $sessionData['focusLossCount'] = $focusLossCount;
    $sessionData['tabSwitchCount'] = $tabSwitchCount;
    
    // Flag excessive suspicious activity
    if (count($sessionData['suspiciousActivity']) > 10) {
        $sessionData['cheatFlags'][] = [
            'type' => 'excessive_suspicious_activity',
            'timestamp' => date('Y-m-d H:i:s'),
            'count' => count($sessionData['suspiciousActivity'])
        ];
    }
    
    // Save updated session data
    file_put_contents($sessionJsonPath, json_encode($sessionData, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'saved_at' => date('Y-m-d H:i:s')]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

<?php
// ==========================================
// File: quiz-heartbeat.php
// ==========================================
?>
<?php
session_start();
header('Content-Type: application/json');

// Verify user is logged in and is a student
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['quizId'])) {
        throw new Exception('Missing quiz ID');
    }
    
    $quizId = $input['quizId'];
    $userId = $_SESSION['user_id'];
    $timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');
    
    // Update session heartbeat
    $sessionJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/sessions/' . $quizId . '_' . $userId . '_session.json';
    
    if (file_exists($sessionJsonPath)) {
        $sessionData = json_decode(file_get_contents($sessionJsonPath), true);
        if ($sessionData) {
            $sessionData['lastHeartbeat'] = $timestamp;
            file_put_contents($sessionJsonPath, json_encode($sessionData, JSON_PRETTY_PRINT));
        }
    }
    
    echo json_encode(['success' => true, 'timestamp' => $timestamp]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

<?php
// ==========================================
// File: log-suspicious-activity.php
// ==========================================
?>
<?php
session_start();
header('Content-Type: application/json');

// Verify user is logged in and is a student
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['quizId']) || !isset($input['activity'])) {
        throw new Exception('Missing required data');
    }
    
    $quizId = $input['quizId'];
    $userId = $_SESSION['user_id'];
    $activity = $input['activity'];
    
    // Log suspicious activity to separate file
    $logDir = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . $quizId . '_' . $userId . '_suspicious.json';
    $logData = [];
    
    if (file_exists($logFile)) {
        $logData = json_decode(file_get_contents($logFile), true) ?: [];
    }
    
    $logData[] = array_merge($activity, [
        'userId' => $userId,
        'quizId' => $quizId,
        'sessionId' => session_id(),
        'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'logged_at' => date('Y-m-d H:i:s')
    ]);
    
    file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT));
    
    // Also update session data
    $sessionJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/sessions/' . $quizId . '_' . $userId . '_session.json';
    if (file_exists($sessionJsonPath)) {
        $sessionData = json_decode(file_get_contents($sessionJsonPath), true);
        if ($sessionData) {
            $sessionData['suspiciousActivity'][] = $activity;
            file_put_contents($sessionJsonPath, json_encode($sessionData, JSON_PRETTY_PRINT));
        }
    }
    
    echo json_encode(['success' => true, 'logged_at' => date('Y-m-d H:i:s')]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

<?php
// ==========================================
// File: submit-answers.php (Enhanced Version)
// ==========================================
?>
<?php
session_start();
header('Content-Type: application/json');

// Verify user is logged in and is a student
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['quizId'])) {
        throw new Exception('Missing quiz ID');
    }
    
    $quizId = $input['quizId'];
    $userId = $_SESSION['user_id'];
    $answers = $input['answers'] ?? [];
    $timeUsed = intval($input['timeUsed'] ?? 0);
    $attempt = intval($input['attempt'] ?? 1);
    $suspiciousActivity = $input['suspiciousActivity'] ?? [];
    $focusLossCount = intval($input['focusLossCount'] ?? 0);
    $tabSwitchCount = intval($input['tabSwitchCount'] ?? 0);
    $isAutomatic = $input['isAutomatic'] ?? false;
    $meta = $input['meta'] ?? [];
    
    // Load quiz data
    $quizJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/' . $quizId . '.json';
    if (!file_exists($quizJsonPath)) {
        throw new Exception('Quiz not found');
    }
    
    $quizData = json_decode(file_get_contents($quizJsonPath), true);
    if (!$quizData) {
        throw new Exception('Invalid quiz data');
    }
    
    // Load and update session data
    $sessionJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/sessions/' . $quizId . '_' . $userId . '_session.json';
    $sessionData = [];
    
    if (file_exists($sessionJsonPath)) {
        $sessionData = json_decode(file_get_contents($sessionJsonPath), true) ?: [];
        
        // Check if already submitted
        if ($sessionData['status'] === 'submitted') {
            throw new Exception('Quiz already submitted');
        }
        
        // Mark as submitted
        $sessionData['status'] = 'submitted';
        $sessionData['submittedAt'] = date('Y-m-d H:i:s');
        $sessionData['finalAnswers'] = $answers;
        $sessionData['suspiciousActivity'] = array_merge($sessionData['suspiciousActivity'] ?? [], $suspiciousActivity);
        $sessionData['focusLossCount'] = $focusLossCount;
        $sessionData['tabSwitchCount'] = $tabSwitchCount;
        $sessionData['isAutomatic'] = $isAutomatic;
        
        file_put_contents($sessionJsonPath, json_encode($sessionData, JSON_PRETTY_PRINT));
    }
    
    // Grade the quiz
    $gradingResult = gradeQuiz($quizData, $answers);
    
    // Prepare attempt data
    $attemptData = [
        'attempt' => $attempt,
        'userId' => $userId,
        'quizId' => $quizId,
        'answers' => $answers,
        'score' => $gradingResult['score'],
        'totalPoints' => $gradingResult['totalPoints'],
        'correctQuestions' => $gradingResult['correctQuestions'],
        'totalQuestions' => count($quizData['questions']),
        'timeUsed' => $timeUsed,
        'submittedAt' => date('Y-m-d H:i:s'),
        'feedback' => $gradingResult['feedback'],
        'suspiciousActivity' => $suspiciousActivity,
        'focusLossCount' => $focusLossCount,
        'tabSwitchCount' => $tabSwitchCount,
        'isAutomatic' => $isAutomatic,
        'academicIntegrityFlags' => generateIntegrityFlags($sessionData),
        'meta' => $meta
    ];
    
    // Save attempt
    $attemptsJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/attempts/' . $quizId . '_' . $userId . '.json';
    $attemptsDir = dirname($attemptsJsonPath);
    if (!is_dir($attemptsDir)) {
        mkdir($attemptsDir, 0755, true);
    }
    
    $attempts = [];
    if (file_exists($attemptsJsonPath)) {
        $attempts = json_decode(file_get_contents($attemptsJsonPath), true) ?: [];
    }
    
    $attempts[] = $attemptData;
    file_put_contents($attemptsJsonPath, json_encode($attempts, JSON_PRETTY_PRINT));
    
    // Log to database if needed
    try {
        $stmt = $pdo->prepare("
            INSERT INTO quiz_attempts (
                user_id, quiz_id, attempt_number, score, total_points, 
                time_used, suspicious_activity_count, submitted_at, is_automatic
            ) VALUES (?, ?,?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $stmt->execute([
            $userId,
            $quizId,
            $attempt,
            $gradingResult['score'],
            $gradingResult['totalPoints'],
            $timeUsed,
            count($suspiciousActivity),
            $isAutomatic ? 1 : 0
        ]);
    } catch (PDOException $e) {
        // Continue even if database logging fails
        error_log("Quiz database logging failed: " . $e->getMessage());
    }
    
    // Return results
    echo json_encode([
        'success' => true,
        'score' => $gradingResult['score'],
        'totalPoints' => $gradingResult['totalPoints'],
        'correctQuestions' => $gradingResult['correctQuestions'],
        'totalQuestions' => count($quizData['questions']),
        'timeUsed' => $timeUsed,
        'attemptNumber' => $attempt,
        'feedback' => $gradingResult['feedback'],
        'suspiciousActivity' => $suspiciousActivity,
        'academicIntegrityNotice' => !empty($attemptData['academicIntegrityFlags'])
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function gradeQuiz($quizData, $answers) {
    $totalScore = 0;
    $totalPoints = 0;
    $correctQuestions = 0;
    $feedback = [];
    
    foreach ($quizData['questions'] as $index => $question) {
        $questionNumber = $index + 1;
        $questionPoints = intval($question['points'] ?? 1);
        $totalPoints += $questionPoints;
        
        $questionKey = "q{$questionNumber}";
        $userAnswer = $answers[$questionKey] ?? null;
        
        $isCorrect = false;
        $feedbackText = '';
        $earnedPoints = 0;
        
        switch ($question['type']) {
            case 'multiple_choice':
                $correctAnswers = [];
                foreach ($question['choices'] as $choiceIndex => $choice) {
                    if (isset($choice['isCorrect']) && $choice['isCorrect']) {
                        $correctAnswers[] = $choiceIndex;
                    }
                }
                
                if ($question['isMultipleAnswer']) {
                    // Multiple answer question
                    $userAnswerArray = is_array($userAnswer) ? $userAnswer : [];
                    sort($userAnswerArray);
                    sort($correctAnswers);
                    $isCorrect = $userAnswerArray === $correctAnswers;
                } else {
                    // Single answer question
                    $isCorrect = in_array($userAnswer, $correctAnswers);
                }
                
                if ($isCorrect) {
                    $earnedPoints = $questionPoints;
                    $correctQuestions++;
                    $feedbackText = $question['feedback']['correct'] ?? 'Correct!';
                } else {
                    $feedbackText = $question['feedback']['incorrect'] ?? 'Incorrect.';
                }
                break;
                
            case 'fill_blank':
                $correctAnswers = $question['correctAnswers'] ?? [];
                $userAnswerArray = is_array($userAnswer) ? $userAnswer : [];
                
                $correctBlanks = 0;
                foreach ($userAnswerArray as $blank) {
                    $blankIndex = $blank['blankIndex'] - 1;
                    if (isset($correctAnswers[$blankIndex])) {
                        $correctAnswer = strtolower(trim($correctAnswers[$blankIndex]));
                        $userBlankAnswer = strtolower(trim($blank['answer']));
                        
                        if ($correctAnswer === $userBlankAnswer) {
                            $correctBlanks++;
                        }
                    }
                }
                
                if ($correctBlanks === count($correctAnswers)) {
                    $isCorrect = true;
                    $earnedPoints = $questionPoints;
                    $correctQuestions++;
                    $feedbackText = $question['feedback']['correct'] ?? 'Correct!';
                } else {
                    // Partial credit
                    $earnedPoints = round(($correctBlanks / count($correctAnswers)) * $questionPoints, 2);
                    $feedbackText = $question['feedback']['incorrect'] ?? 'Some answers are incorrect.';
                }
                break;
                
            case 'formula':
                if (isset($question['formula']) && isset($userAnswer['answer']) && isset($userAnswer['variables'])) {
                    $formula = $question['formula'];
                    $variables = $userAnswer['variables'];
                    $userNumericAnswer = floatval($userAnswer['answer']);
                    
                    // Calculate expected answer
                    $expectedAnswer = calculateFormulaAnswer($formula, $variables);
                    $tolerance = floatval($question['tolerance'] ?? 0.01);
                    
                    if (abs($userNumericAnswer - $expectedAnswer) <= $tolerance) {
                        $isCorrect = true;
                        $earnedPoints = $questionPoints;
                        $correctQuestions++;
                        $feedbackText = $question['feedback']['correct'] ?? 'Correct!';
                    } else {
                        $feedbackText = $question['feedback']['incorrect'] ?? 
                            "Incorrect. Expected: " . number_format($expectedAnswer, 3);
                    }
                }
                break;
        }
        
        $totalScore += $earnedPoints;
        
        $feedback[] = [
            'question' => $questionNumber,
            'correct' => $isCorrect,
            'points' => $earnedPoints,
            'totalPoints' => $questionPoints,
            'feedback' => $feedbackText
        ];
    }
    
    return [
        'score' => $totalScore,
        'totalPoints' => $totalPoints,
        'correctQuestions' => $correctQuestions,
        'feedback' => $feedback
    ];
}

function calculateFormulaAnswer($formula, $variables) {
    // Replace variables in formula with their values
    foreach ($variables as $varName => $varValue) {
        $formula = str_replace($varName, $varValue, $formula);
    }
    
    // Use math.js style evaluation or simple eval (be careful with eval!)
    // For safety, we'll use a limited math parser
    return evaluateSimpleMath($formula);
}

function evaluateSimpleMath($expression) {
    // Remove whitespace
    $expression = preg_replace('/\s+/', '', $expression);
    
    // Only allow numbers, basic operators, and parentheses
    if (!preg_match('/^[0-9+\-*\/\.\(\)]+$/', $expression)) {
        throw new Exception('Invalid formula expression');
    }
    
    // Use eval carefully (in production, consider using a proper math parser)
    try {
        $result = eval("return $expression;");
        return floatval($result);
    } catch (ParseError $e) {
        throw new Exception('Formula calculation error');
    }
}

function generateIntegrityFlags($sessionData) {
    $flags = [];
    
    if (($sessionData['focusLossCount'] ?? 0) > 3) {
        $flags[] = 'excessive_focus_loss';
    }
    
    if (($sessionData['tabSwitchCount'] ?? 0) > 2) {
        $flags[] = 'excessive_tab_switching';
    }
    
    if (($sessionData['visitCount'] ?? 0) > 5) {
        $flags[] = 'excessive_page_refresh';
    }
    
    $suspiciousTypes = array_column($sessionData['suspiciousActivity'] ?? [], 'type');
    if (in_array('dev_tools_opened', $suspiciousTypes)) {
        $flags[] = 'developer_tools_detected';
    }
    
    if (count($suspiciousTypes) > 10) {
        $flags[] = 'multiple_suspicious_activities';
    }
    
    return $flags;
}
?>

<?php
// ==========================================
// File: clean-expired-sessions.php (Cron job script)
// ==========================================
?>
<?php
// This script should be run as a cron job to clean up expired sessions

$sessionDir = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/sessions/';

if (!is_dir($sessionDir)) {
    echo "Sessions directory not found\n";
    exit;
}

$files = glob($sessionDir . '*_session.json');
$now = time();
$cleanedCount = 0;

foreach ($files as $file) {
    $sessionData = json_decode(file_get_contents($file), true);
    
    if ($sessionData && isset($sessionData['startTime'])) {
        $startTime = strtotime($sessionData['startTime']);
        $maxSessionTime = 24 * 60 * 60; // 24 hours
        
        // If session is older than 24 hours and not submitted, mark as expired
        if (($now - $startTime) > $maxSessionTime && $sessionData['status'] !== 'submitted') {
            $sessionData['status'] = 'expired';
            $sessionData['expiredAt'] = date('Y-m-d H:i:s');
            file_put_contents($file, json_encode($sessionData, JSON_PRETTY_PRINT));
            $cleanedCount++;
        }
    }
}

echo "Cleaned $cleanedCount expired sessions\n";
?>