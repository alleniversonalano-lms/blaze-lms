<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

$quizId = $input['quizId'] ?? null;
$sessionId = $input['sessionId'] ?? null;
$sessionToken = $input['sessionToken'] ?? null;
$answers = $input['answers'] ?? [];
$timeUsed = $input['timeUsed'] ?? 0;
$attemptNumber = $input['attempt'] ?? 1;
$meta = $input['meta'] ?? [];

if (!$quizId || !$sessionId || !$sessionToken) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Get user information from session
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';
$userEmail = $_SESSION['email_address'] ?? '';

// Validate session token
$expectedToken = hash('sha256', $sessionId . $userId . $quizId);
if ($sessionToken !== $expectedToken) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session token - possible security breach']);
    exit;
}

// Verify session exists and is valid
$sessionDir = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quiz-sessions/';
$sessionFile = $sessionDir . $quizId . '_' . $userId . '.json';

if (!file_exists($sessionFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Quiz session not found']);
    exit;
}

$sessionData = json_decode(file_get_contents($sessionFile), true);
if (!$sessionData || $sessionData['sessionId'] !== $sessionId || $sessionData['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid or expired quiz session']);
    exit;
}

// Load quiz data
$quizJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/' . $quizId . '.json';
if (!file_exists($quizJsonPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Quiz not found']);
    exit;
}

$quizData = json_decode(file_get_contents($quizJsonPath), true);
if (!$quizData) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid quiz data']);
    exit;
}

date_default_timezone_set('Asia/Manila');

// Then modify the DateTime checks section:
$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$options = $quizData['options'] ?? [];

// Check availability dates
if (!empty($options['availableFromDate']) && !empty($options['availableFromTime'])) {
    $availableFrom = DateTime::createFromFormat('Y-m-d H:i', 
        $options['availableFromDate'] . ' ' . $options['availableFromTime'], 
        new DateTimeZone('Asia/Manila'));
    if ($now < $availableFrom) {
        http_response_code(403);
        echo json_encode(['error' => 'Quiz is not yet available']);
        exit;
    }
}

if (!empty($options['availableUntilDate']) && !empty($options['availableUntilTime'])) {
    $availableUntil = DateTime::createFromFormat('Y-m-d H:i', 
        $options['availableUntilDate'] . ' ' . $options['availableUntilTime'], 
        new DateTimeZone('Asia/Manila'));
    if ($now > $availableUntil) {
        http_response_code(403);
        echo json_encode(['error' => 'Quiz is no longer available']);
        exit;
    }
}

// Check due date
if (!empty($options['dueDate']) && !empty($options['dueTime'])) {
    $dueDate = DateTime::createFromFormat('Y-m-d H:i', 
        $options['dueDate'] . ' ' . $options['dueTime'], 
        new DateTimeZone('Asia/Manila'));
    if ($now > $dueDate) {
        http_response_code(403);
        echo json_encode(['error' => 'Quiz submission deadline has passed']);
        exit;
    }
}

// Security validation - check for suspicious activity
$suspiciousActivity = $meta['suspiciousActivity'] ?? [];
$isSecurityBreach = $meta['isSecurityBreach'] ?? false;
$securityFlags = [];

// Analyze suspicious activities
$criticalActivities = ['developer_tools_attempt', 'developer_tools_detected', 'prolonged_tab_switch', 'excessive_reloads'];
$criticalCount = 0;

foreach ($suspiciousActivity as $activity) {
    if (in_array($activity['type'], $criticalActivities)) {
        $criticalCount++;
        $securityFlags[] = $activity['type'];
    }
}

// Check for excessive tab switching or focus changes
$visibilityChanges = $meta['visibilityChanges'] ?? 0;
$focusChanges = $meta['focusChanges'] ?? 0;

if ($visibilityChanges > 5) {
    $securityFlags[] = 'excessive_tab_switching';
}

if ($focusChanges > 10) {
    $securityFlags[] = 'excessive_focus_changes';
}

// Validate browser fingerprint consistency
$storedFingerprint = $sessionData['browserFingerprint'] ?? null;
$currentFingerprint = $meta['browserFingerprint'] ?? null;

if ($storedFingerprint && $currentFingerprint && $storedFingerprint !== $currentFingerprint) {
    $securityFlags[] = 'browser_fingerprint_mismatch';
}

// Check session timing for anomalies
$sessionStartTime = new DateTime($sessionData['startTime']);
$submissionTime = new DateTime($meta['endTime'] ?? 'now');
$sessionDuration = $submissionTime->getTimestamp() - $sessionStartTime->getTimestamp();
$timeLimit = ($options['timeLimit'] ?? 60) * 60;

// Unusually fast completion (less than 30% of time limit)
if ($sessionDuration < ($timeLimit * 0.3)) {
    $securityFlags[] = 'suspiciously_fast_completion';
}

// Load existing attempts
$attemptsDir = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/attempts/';
if (!is_dir($attemptsDir)) {
    mkdir($attemptsDir, 0755, true);
}

$attemptsFile = $attemptsDir . $quizId . '_' . $userId . '.json';
$existingAttempts = [];
if (file_exists($attemptsFile)) {
    $existingAttempts = json_decode(file_get_contents($attemptsFile), true) ?: [];
}

// Check attempt limits
$maxAttempts = intval($options['attempts'] ?? 3);
if ($maxAttempts != -1 && count($existingAttempts) >= $maxAttempts) {
    http_response_code(403);
    echo json_encode(['error' => 'Maximum number of attempts exceeded']);
    exit;
}

// Validate that this is the expected attempt number
$expectedAttemptNumber = count($existingAttempts) + 1;
if ($attemptNumber !== $expectedAttemptNumber) {
    error_log("Attempt number mismatch for user $userId, quiz $quizId. Expected: $expectedAttemptNumber, Received: $attemptNumber");
    $attemptNumber = $expectedAttemptNumber;
}

// Grade the quiz
$gradingResult = gradeQuiz($quizData, $answers);

// Create comprehensive attempt record with enhanced security data
$attempt = [
    // Attempt identification
    'attemptNumber' => $attemptNumber,
    'attemptId' => uniqid('attempt_', true),
    
    // User identification
    'userId' => $userId,
    'username' => $username,
    'firstName' => $firstName,
    'lastName' => $lastName,
    'userEmail' => $userEmail,
    
    // Quiz identification
    'quizId' => $quizId,
    'quizTitle' => $quizData['title'] ?? 'Untitled Quiz',
    'sessionId' => $sessionId,
    
    // Timing information
    'submittedAt' => date('c'),
    'submittedTimestamp' => time(),
    'timeUsed' => $timeUsed,
    'timeLimit' => $options['timeLimit'] ?? 60,
    'isTimeout' => $meta['isTimeout'] ?? false,
    'startTime' => $meta['startTime'] ?? null,
    'endTime' => $meta['endTime'] ?? null,
    'sessionDuration' => $sessionDuration,
    
    // Security information
    'securityFlags' => array_unique($securityFlags),
    'isSecurityBreach' => $isSecurityBreach || count($securityFlags) > 0,
    'suspiciousActivity' => $suspiciousActivity,
    'visibilityChanges' => $visibilityChanges,
    'focusChanges' => $focusChanges,
    'securityWarnings' => $meta['securityWarnings'] ?? 0,
    'browserFingerprint' => $currentFingerprint,
    'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
    'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'sessionData' => [
        'pageLoads' => $sessionData['pageLoads'] ?? 1,
        'lastActivity' => $sessionData['lastActivity'] ?? null
    ],
    
    // Quiz configuration at time of submission
    'quizOptions' => $options,
    
    // Student responses
    'answers' => $answers,
    
    // Scoring results
    'score' => $gradingResult['score'],
    'totalPoints' => $gradingResult['totalPoints'],
    'correctQuestions' => $gradingResult['correctQuestions'],
    'totalQuestions' => $gradingResult['totalQuestions'],
    'percentage' => round(($gradingResult['score'] / max(1, $gradingResult['totalPoints'])) * 100, 2),
    'feedback' => $gradingResult['feedback'],
    'questionResults' => $gradingResult['questionResults'],
    
    // Data version for future migrations
    'dataVersion' => '1.1'
];

// Apply security penalties if necessary
if (count($securityFlags) > 0) {
    $securityPenalty = min(20, count($securityFlags) * 5); // Max 20% penalty
    $originalScore = $attempt['score'];
    $penaltyPoints = round(($securityPenalty / 100) * $gradingResult['totalPoints']);
    
    $attempt['score'] = max(0, $originalScore - $penaltyPoints);
    $attempt['percentage'] = round(($attempt['score'] / max(1, $gradingResult['totalPoints'])) * 100, 2);
    $attempt['securityPenalty'] = $securityPenalty;
    $attempt['originalScore'] = $originalScore;
    $attempt['penaltyPoints'] = $penaltyPoints;
    
    // Log security incident
    error_log("Security flags detected for user $userId, quiz $quizId, attempt $attemptNumber: " . implode(', ', $securityFlags));
}

// Add attempt to existing attempts
$existingAttempts[] = $attempt;

// Create backup before saving
$backupFile = $attemptsFile . '.backup.' . date('Y-m-d-H-i-s');
if (file_exists($attemptsFile)) {
    copy($attemptsFile, $backupFile);
    
    // Clean up old backups (keep only last 5)
    $backupPattern = $attemptsFile . '.backup.*';
    $backups = glob($backupPattern);
    if (count($backups) > 5) {
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        foreach (array_slice($backups, 5) as $oldBackup) {
            unlink($oldBackup);
        }
    }
}

// Save attempts to file with error handling
$jsonData = json_encode($existingAttempts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($jsonData === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to encode attempt data']);
    exit;
}

// Atomic write
$tempFile = $attemptsFile . '.tmp';
if (file_put_contents($tempFile, $jsonData, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save attempt']);
    exit;
}

if (!rename($tempFile, $attemptsFile)) {
    unlink($tempFile);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to finalize attempt save']);
    exit;
}

// Save to global attempts log for administrative purposes
$globalLogFile = $attemptsDir . 'all_attempts.jsonl';
$logEntry = json_encode($attempt) . "\n";
file_put_contents($globalLogFile, $logEntry, FILE_APPEND | LOCK_EX);

// Security incidents log
if (count($securityFlags) > 0) {
    $securityLogFile = $attemptsDir . 'security_incidents.jsonl';
    $securityIncident = [
        'timestamp' => date('c'),
        'userId' => $userId,
        'quizId' => $quizId,
        'attemptId' => $attempt['attemptId'],
        'sessionId' => $sessionId,
        'securityFlags' => $securityFlags,
        'suspiciousActivity' => $suspiciousActivity,
        'penalty' => $attempt['securityPenalty'] ?? 0,
        'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
    file_put_contents($securityLogFile, json_encode($securityIncident) . "\n", FILE_APPEND | LOCK_EX);
}

// Mark session as completed
$sessionData['status'] = 'completed';
$sessionData['endTime'] = date('c');
$sessionData['submitted'] = true;
file_put_contents($sessionFile, json_encode($sessionData, JSON_PRETTY_PRINT));

// Prepare response based on quiz settings
$response = [
    'success' => true,
    'score' => $attempt['score'],
    'totalPoints' => $gradingResult['totalPoints'],
    'correctQuestions' => $gradingResult['correctQuestions'],
    'totalQuestions' => $gradingResult['totalQuestions'],
    'percentage' => $attempt['percentage'],
    'timeUsed' => $timeUsed,
    'attemptNumber' => $attemptNumber,
    'attemptId' => $attempt['attemptId'],
    'securityFlags' => $securityFlags
];

// Add security penalty information if applicable
if (isset($attempt['securityPenalty'])) {
    $response['securityPenalty'] = $attempt['securityPenalty'];
    $response['originalScore'] = $attempt['originalScore'];
    $response['penaltyPoints'] = $attempt['penaltyPoints'];
    $response['securityMessage'] = "Your score has been reduced by {$attempt['securityPenalty']}% due to detected suspicious activity.";
}

// Add feedback if allowed
if ($options['seeResponses'] ?? false) {
    $showResponses = false;
    $responsesTiming = $options['seeResponsesTiming'] ?? 'once';
    
    switch ($responsesTiming) {
        case 'immediately':
            $showResponses = true;
            break;
        case 'once':
            $showResponses = (count($existingAttempts) >= $maxAttempts || $maxAttempts == -1);
            break;
        case 'never':
            $showResponses = false;
            break;
    }
    
    if ($showResponses) {
        $response['feedback'] = $gradingResult['feedback'];
    }
}

// Add correct answers if allowed and conditions are met
if ($options['seeCorrectAnswers'] ?? false) {
    $showCorrect = false;
    
    if (!empty($options['showCorrectAnswersDate']) && !empty($options['showCorrectAnswersTime'])) {
        $showDate = DateTime::createFromFormat('Y-m-d H:i', $options['showCorrectAnswersDate'] . ' ' . $options['showCorrectAnswersTime']);
        if ($now >= $showDate) {
            $showCorrect = true;
        }
    } else {
        $showCorrect = (count($existingAttempts) >= $maxAttempts || $maxAttempts == -1);
    }
    
    // Check hide date
    if ($showCorrect && !empty($options['hideCorrectAnswersDate']) && !empty($options['hideCorrectAnswersTime'])) {
        $hideDate = DateTime::createFromFormat('Y-m-d H:i', $options['hideCorrectAnswersDate'] . ' ' . $options['hideCorrectAnswersTime']);
        if ($now >= $hideDate) {
            $showCorrect = false;
        }
    }
    
    if ($showCorrect) {
        $response['correctAnswers'] = getCorrectAnswers($quizData);
    }
}

echo json_encode($response);

function gradeQuiz($quizData, $studentAnswers) {
    $questions = $quizData['questions'] ?? [];
    $totalScore = 0;
    $totalPoints = 0;
    $correctQuestions = 0;
    $feedback = [];
    $questionResults = [];
    
    foreach ($questions as $index => $question) {
        $questionNum = $index + 1;
        $questionKey = "q{$questionNum}";
        $studentAnswer = $studentAnswers[$questionKey] ?? null;
        $points = intval($question['points'] ?? 1);
        $totalPoints += $points;
        
        $questionResult = [
            'questionNumber' => $questionNum,
            'points' => $points,
            'studentAnswer' => $studentAnswer,
            'correct' => false,
            'score' => 0
        ];
        
        switch ($question['type']) {
    case 'multiple_choice':
    case 'single_choice':  // Added to match assessment creation
        $correct = gradeMultipleChoice($question, $studentAnswer);
        if ($correct) {
            $totalScore += $points;
            $correctQuestions++;
            $questionResult['correct'] = true;
            $questionResult['score'] = $points;
        }
        
        $feedback[] = [
            'question' => $questionNum,
            'correct' => $correct,
            'points' => $correct ? $points : 0,
            'totalPoints' => $points,
            'feedback' => $correct ? 'Correct!' : 'Incorrect answer.',
            'correctAnswer' => $question['correctAnswers'][0] ?? null,
            'explanation' => $question['explanation'] ?? ''
        ];
        break;
        
    case 'multiple_answer':  // Separated from multiple_choice
        $score = gradeMultipleAnswer($question, $studentAnswer);
        $totalScore += $score;
        $isFullyCorrect = ($score == $points);
        if ($isFullyCorrect) $correctQuestions++;
        
        $questionResult['correct'] = $isFullyCorrect;
        $questionResult['score'] = $score;
        
        $feedback[] = [
            'question' => $questionNum,
            'correct' => $isFullyCorrect,
            'points' => $score,
            'totalPoints' => $points,
            'feedback' => $isFullyCorrect ? 'Correct!' : 
                         ($score > 0 ? 'Partially correct.' : 'Incorrect answer.'),
            'correctAnswers' => $question['correctAnswers'] ?? [],
            'explanation' => $question['explanation'] ?? ''
        ];
        break;
        
    case 'fill_blank':
    case 'fill_in_blanks':  // Added alternative name
        $score = gradeFillBlank($question, $studentAnswer);
        $totalScore += $score;
        $isCorrect = ($score == $points);
        if ($isCorrect) $correctQuestions++;
        
        $questionResult['correct'] = $isCorrect;
        $questionResult['score'] = $score;
        
        $feedback[] = [
            'question' => $questionNum,
            'correct' => $isCorrect,
            'points' => $score,
            'totalPoints' => $points,
            'feedback' => $isCorrect ? 'Correct!' : 
                         "Partial or incorrect answer. Score: {$score}/{$points}",
            'correctAnswers' => $question['blanks'] ?? [],
            'explanation' => $question['explanation'] ?? ''
        ];
        break;
        
        case 'true_false':  // Added true/false type
            $correct = gradeTrueFalse($question, $studentAnswer);
            if ($correct) {
                $totalScore += $points;
                $correctQuestions++;
                $questionResult['correct'] = true;
                $questionResult['score'] = $points;
            }
            
            $feedback[] = [
                'question' => $questionNum,
                'correct' => $correct,
                'points' => $correct ? $points : 0,
                'totalPoints' => $points,
                'feedback' => $correct ? 'Correct!' : 'Incorrect answer.',
                'correctAnswer' => $question['correctAnswer'] ?? null,
                'explanation' => $question['explanation'] ?? ''
            ];
            break;
            
        case 'formula':
        case 'computation':  // Added alternative name
            $correct = gradeFormula($question, $studentAnswer);
            if ($correct) {
                $totalScore += $points;
                $correctQuestions++;
                $questionResult['correct'] = true;
                $questionResult['score'] = $points;
            }
            
            $feedback[] = [
                'question' => $questionNum,
                'correct' => $correct,
                'points' => $correct ? $points : 0,
                'totalPoints' => $points,
                'feedback' => $correct ? 'Correct!' : 'Incorrect calculation.',
                'correctFormula' => $question['formula'] ?? '',
                'explanation' => $question['explanation'] ?? ''
            ];
            break;
    }
        
        $questionResults[] = $questionResult;
    }
    
    return [
        'score' => $totalScore,
        'totalPoints' => $totalPoints,
        'correctQuestions' => $correctQuestions,
        'totalQuestions' => count($questions),
        'feedback' => $feedback,
        'questionResults' => $questionResults
    ];
}

function gradeMultipleChoice($question, $studentAnswer) {
    $correctAnswers = $question['correctAnswers'] ?? [];
    
    if ($question['isMultipleAnswer'] ?? false) {
        if (!is_array($studentAnswer)) return false;
        
        sort($correctAnswers);
        sort($studentAnswer);
        
        return $correctAnswers === array_map('intval', $studentAnswer);
    } else {
        return in_array(intval($studentAnswer), $correctAnswers);
    }
}

function gradeMultipleAnswer($question, $studentAnswer) {
    if (!is_array($studentAnswer)) return 0;
    
    $correctAnswers = $question['correctAnswers'] ?? [];
    $partialCredit = $question['allowPartialCredit'] ?? true;
    $points = intval($question['points'] ?? 1);
    
    if (!$partialCredit) {
        sort($correctAnswers);
        sort($studentAnswer);
        return (array_map('intval', $studentAnswer) === $correctAnswers) ? $points : 0;
    }
    
    // Calculate partial credit
    $correctCount = 0;
    $incorrectCount = 0;
    
    foreach ($studentAnswer as $answer) {
        if (in_array(intval($answer), $correctAnswers)) {
            $correctCount++;
        } else {
            $incorrectCount++;
        }
    }
    
    // Deduct points for incorrect selections
    $totalCorrectAnswers = count($correctAnswers);
    $score = ($correctCount / $totalCorrectAnswers) * $points;
    $penalty = ($incorrectCount / $totalCorrectAnswers) * ($points * 0.5); // 50% penalty for wrong answers
    
    return max(0, round($score - $penalty));
}

function gradeTrueFalse($question, $studentAnswer) {
    $correctAnswer = $question['correctAnswer'] ?? null;
    if ($correctAnswer === null) return false;
    
    // Convert to boolean/integer for comparison
    $studentBool = filter_var($studentAnswer, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $correctBool = filter_var($correctAnswer, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    
    return $studentBool === $correctBool;
}

function gradeFillBlank($question, $studentAnswer) {
    if (!is_array($studentAnswer) || !isset($studentAnswer['blankAnswers'])) return 0;
    
    $blanks = $question['blanks'] ?? [];
    $caseSensitive = $question['caseSensitive'] ?? [];
    $allowPartialCredit = $question['allowPartialCredit'] ?? true;
    $points = intval($question['points'] ?? 1);
    $totalBlanks = count($blanks);
    $correctBlanks = 0;
    
    foreach ($studentAnswer['blankAnswers'] as $answerData) {
        $blankIndex = $answerData['blankIndex'] - 1;
        $studentResp = trim($answerData['answer']);
        
        if (isset($blanks[$blankIndex])) {
            $correctOptions = $blanks[$blankIndex];
            $isCaseSensitive = $caseSensitive[$blankIndex] ?? false;
            
            // Check against all possible correct answers
            foreach ($correctOptions as $correctAnswer) {
                $match = $isCaseSensitive ? 
                    ($studentResp === $correctAnswer) : 
                    (strtolower($studentResp) === strtolower($correctAnswer));
                
                if ($match) {
                    $correctBlanks++;
                    break; // Found a match, move to next blank
                }
            }
        }
    }
    
    // Calculate score based on partial credit setting
    if (!$allowPartialCredit) {
        return ($correctBlanks === $totalBlanks) ? $points : 0;
    }
    
    return $totalBlanks > 0 ? round(($correctBlanks / $totalBlanks) * $points) : 0;
}

function gradeFormula($question, $studentAnswer) {
    if (!is_array($studentAnswer) || !isset($studentAnswer['answer']) || !isset($studentAnswer['variables'])) {
        return false;
    }
    
    $formula = $question['formula'] ?? '';
    $variables = $studentAnswer['variables'];
    $studentResult = floatval($studentAnswer['answer']);
    
    if (empty($formula)) return false;
    
    try {
        // Safe formula evaluation
        $expression = $formula;
        foreach ($variables as $varName => $value) {
            $expression = str_replace($varName, $value, $expression);
        }
        
        // Basic security: only allow basic math operations
        if (!preg_match('/^[0-9+\-*\/\.\(\)\s]+$/', $expression)) {
            return false;
        }
        
        $expectedResult = eval("return $expression;");
        
        $tolerance = floatval($question['tolerance'] ?? 0.01);
        return abs($studentResult - $expectedResult) < $tolerance;
    } catch (Exception $e) {
        error_log("Formula evaluation error: " . $e->getMessage());
        return false;
    }
}

function getCorrectAnswers($quizData) {
    $correctAnswers = [];
    $questions = $quizData['questions'] ?? [];
    
    foreach ($questions as $index => $question) {
        $questionNum = $index + 1;
        
        switch ($question['type']) {
            case 'multiple_choice':
            case 'single_choice':
                $correctAnswers["q{$questionNum}"] = [
                    'type' => $question['type'],
                    'correctAnswers' => $question['correctAnswers'] ?? [],
                    'choices' => $question['choices'] ?? [],
                    'explanation' => $question['explanation'] ?? ''
                ];
                break;
                
            case 'multiple_answer':
                $correctAnswers["q{$questionNum}"] = [
                    'type' => 'multiple_answer',
                    'correctAnswers' => $question['correctAnswers'] ?? [],
                    'choices' => $question['choices'] ?? [],
                    'allowPartialCredit' => $question['allowPartialCredit'] ?? true,
                    'explanation' => $question['explanation'] ?? ''
                ];
                break;
                
            case 'fill_blank':
            case 'fill_in_blanks':
                $correctAnswers["q{$questionNum}"] = [
                    'type' => 'fill_blank',
                    'blanks' => $question['blanks'] ?? [], // Array of possible correct answers
                    'caseSensitive' => $question['caseSensitive'] ?? [],
                    'explanation' => $question['explanation'] ?? ''
                ];
                break;
                
            case 'true_false':
                $correctAnswers["q{$questionNum}"] = [
                    'type' => 'true_false',
                    'correctAnswer' => $question['correctAnswer'] ?? null,
                    'explanation' => $question['explanation'] ?? ''
                ];
                break;
                
            case 'formula':
            case 'computation':
                $correctAnswers["q{$questionNum}"] = [
                    'type' => 'formula',
                    'formula' => $question['formula'] ?? '',
                    'variables' => $question['variables'] ?? [],
                    'tolerance' => $question['tolerance'] ?? 0.01,
                    'explanation' => $question['explanation'] ?? ''
                ];
                break;
        }
    }
    
    return $correctAnswers;
}
?>