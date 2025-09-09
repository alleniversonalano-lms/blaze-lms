<?php
session_start();

// Store session details into variables
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$email_address = $_SESSION['email_address'];
$role = $_SESSION['role'];
$profile_pic = $_SESSION['profile_pic'];

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'student') {
    header("Location: /login?error=Access+denied");
    exit;
}

require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

$quizId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$quizId) {
    header('Location: assessments.php');
    exit();
}

// Read quiz data from JSON file
$quizJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/' . $quizId . '.json';
if (!file_exists($quizJsonPath)) {
    // Check for backup files if original not found
    $pattern = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/quiz_' . $quizId . '*.json*';
    $matches = glob($pattern);

    if (!empty($matches)) {
        // Use the most recent backup
        usort($matches, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $quizJsonPath = $matches[0];
    } else {
        header('Location: assessments?error=quiz_not_found');
        exit();
    }
}

$quizData = json_decode(file_get_contents($quizJsonPath), true);
if (!$quizData) {
    header('Location: assessments?error=invalid_quiz');
    exit();
}

// Get quiz options
$options = $quizData['options'] ?? [];

// Check if quiz is published
if (!isset($quizData['is_published']) || $quizData['is_published'] != 1) {
    header('Location: assessments?error=quiz_not_published');
    exit();
}

// Set default timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Check availability dates
$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
if (!empty($options['availableFromDate']) && !empty($options['availableFromTime'])) {
    $availableFrom = DateTime::createFromFormat(
        'Y-m-d H:i',
        $options['availableFromDate'] . ' ' . $options['availableFromTime'],
        new DateTimeZone('Asia/Manila')
    );

    if ($now < $availableFrom) {
        error_log("Quiz not yet available. Current time: " . $now->format('Y-m-d H:i:s') .
            ", Available from: " . $availableFrom->format('Y-m-d H:i:s'));
        header('Location: assessments?error=quiz_not_available');
        exit();
    }
}

if (!empty($options['availableUntilDate']) && !empty($options['availableUntilTime'])) {
    $availableUntil = DateTime::createFromFormat(
        'Y-m-d H:i',
        $options['availableUntilDate'] . ' ' . $options['availableUntilTime'],
        new DateTimeZone('Asia/Manila')
    );

    if ($now > $availableUntil) {
        error_log("Quiz expired. Current time: " . $now->format('Y-m-d H:i:s') .
            ", Available until: " . $availableUntil->format('Y-m-d H:i:s'));
        header('Location: assessments?error=quiz_expired');
        exit();
    }
}

// Enhanced session management for quiz continuation
$sessionDir = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quiz-sessions/';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0755, true);
}

$sessionFile = $sessionDir . $quizId . '_' . $user_id . '.json';
$quizSession = null;

// Check for existing active session
if (file_exists($sessionFile)) {
    $sessionData = json_decode(file_get_contents($sessionFile), true);
    if ($sessionData && isset($sessionData['status']) && $sessionData['status'] === 'active') {
        $quizSession = $sessionData;

        // Check if session has expired
        $sessionStartTime = new DateTime($quizSession['startTime']);
        $timeLimit = intval($options['timeLimit'] ?? 60) * 60; // Convert to seconds
        $sessionExpiry = clone $sessionStartTime;
        $sessionExpiry->add(new DateInterval('PT' . $timeLimit . 'S'));

        if ($now > $sessionExpiry) {
            // Session expired - mark as auto-submitted
            $quizSession['status'] = 'expired';
            $quizSession['endTime'] = $now->format('c');
            $quizSession['autoSubmitted'] = true;
            file_put_contents($sessionFile, json_encode($quizSession, JSON_PRETTY_PRINT));

            header('Location: assessments?error=quiz_session_expired');
            exit();
        }
    }
}

// Get student's previous attempts
$attemptsJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/attempts/' . $quizId . '_' . $user_id . '.json';
$currentAttempt = 1;
$existingAttempts = [];
if (file_exists($attemptsJsonPath)) {
    $existingAttempts = json_decode(file_get_contents($attemptsJsonPath), true) ?: [];
    $currentAttempt = count($existingAttempts) + 1;
}

// Check if max attempts reached
$maxAttempts = intval($options['attempts'] ?? 3);
if ($maxAttempts != -1 && $currentAttempt > $maxAttempts) {
    header('Location: assessments?error=max_attempts');
    exit();
}

// Create or continue quiz session
if (!$quizSession) {
    // Create new session
    $quizSession = [
        'sessionId' => uniqid('session_', true),
        'userId' => $user_id,
        'quizId' => $quizId,
        'attemptNumber' => $currentAttempt,
        'startTime' => $now->format('c'), // ISO 8601 format
        'status' => 'active',
        'answers' => [],
        'currentQuestion' => 0,
        'pageLoads' => 1,
        'visibilityChanges' => 0,
        'focusChanges' => 0,
        'suspiciousActivity' => [],
        'lastActivity' => $now->format('c'),
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
        'browserFingerprint' => null // Will be set by JavaScript
    ];
} else {
    // Continue existing session
    $quizSession['pageLoads']++;
    $quizSession['lastActivity'] = $now->format('c');

    // Check for suspicious activity - too many page reloads
    if ($quizSession['pageLoads'] > 5) {
        $quizSession['suspiciousActivity'][] = [
            'type' => 'excessive_reloads',
            'count' => $quizSession['pageLoads'],
            'timestamp' => $now->format('c')
        ];
    }
}

// Save session
file_put_contents($sessionFile, json_encode($quizSession, JSON_PRETTY_PRINT));

// Add current attempt number to quiz data
$quizData['currentAttempt'] = $currentAttempt;

// Calculate total points
$totalPoints = 0;
foreach ($quizData['questions'] as $question) {
    $totalPoints += intval($question['points'] ?? 1);
}

// Generate unique session token for this quiz session
$sessionToken = hash('sha256', $quizSession['sessionId'] . $user_id . $quizId);

error_log('=== Token Generation Debug ===');
error_log('Generated Token: ' . $sessionToken);
error_log('Session ID: ' . $quizSession['sessionId']);
error_log('User ID: ' . $user_id);
error_log('Quiz ID: ' . $quizId);


function processQuestionContent($content)
{
    if (empty($content)) {
        return '';
    }

    // Remove extra escapes
    $content = stripslashes($content);

    // First extract the image if it exists
    $image = '';
    if (preg_match('/<div><img src=\\"(.*?)\\".*?><\/div>/', $content, $matches)) {
        $image = '<div class="question-image"><img src="' . $matches[1] . '" alt="Question Image"></div>';
        // Remove the image from the content
        $content = preg_replace('/<div><img src=\\"(.*?)\\".*?><\/div>/', '', $content);
    }

    // Clean up remaining content
    $content = str_replace(['\"', '\/', '\\\\'], ['"', '/', '\\'], $content);
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content = stripslashes($content);

    // Return both parts
    return [
        'image' => $image,
        'text' => $content
    ];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quizData['title'] ?? 'Take Quiz'); ?></title>

    <!-- Include Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Include MathJax for formula rendering -->
    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

    <!-- Include Math.js for formula evaluation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mathjs/9.4.4/math.js"></script>

    <!-- Enhanced Security: Disable right-click, F12, etc. -->
    <style>
        :root {
            --primary-color: #0374b5;
            --error-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
        }

        /* Anti-cheat styles */
        body {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
        }

        .quiz-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .quiz-header {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .quiz-title {
            font-size: 24px;
            margin-bottom: 16px;
            color: #2d3748;
        }

        .quiz-description {
            color: #666;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .quiz-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .quiz-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #4a5568;
        }

        .quiz-meta-item i {
            color: var(--primary-color);
        }

        .quiz-progress {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
        }

        .quiz-progress-bar {
            height: 100%;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .quiz-instructions {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .question {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .question-number {
            font-size: 18px;
            font-weight: 500;
            color: #2d3748;
        }

        .question-points {
            background: #ebf4ff;
            color: var(--primary-color);
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 500;
        }

        .question-content {
            font-size: 16px;
            line-height: 1.6;
            color: #2d3748;
            margin-bottom: 20px;
        }

        .question-content img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 10px 0;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .question-image {
            margin-bottom: 20px;
            text-align: center;
        }

        .question-image img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .question-text {
            margin-bottom: 15px;
            line-height: 1.6;
        }

        /* For responsive images */
        @media (max-width: 768px) {
            .question-content img {
                width: 100%;
            }
        }

        /* Multiple Choice Styling */
        .choice-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .choice-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }

        .choice-item:hover {
            background: #f8fafc;
            border-color: #cbd5e0;
        }

        .choice-item.selected {
            background: #ebf4ff;
            border-color: var(--primary-color);
        }

        .choice-item input[type="radio"],
        .choice-item input[type="checkbox"] {
            margin: 0;
            width: 20px;
            height: 20px;
            position: relative;
            cursor: pointer;
            appearance: none;
        }

        .choice-item input[type="radio"]::before,
        .choice-item input[type="checkbox"]::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 20px;
            height: 20px;
            border: 2px solid #a0aec0;
            background: white;
            transition: all 0.2s ease;
        }

        .choice-item input[type="radio"]::before {
            border-radius: 50%;
        }

        .choice-item input[type="checkbox"]::before {
            border-radius: 4px;
        }

        .choice-item input[type="radio"]:checked::before,
        .choice-item input[type="checkbox"]:checked::before {
            border-color: var(--primary-color);
            background: var(--primary-color);
        }

        .choice-item input[type="radio"]:checked::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
        }

        .choice-item input[type="checkbox"]:checked::after {
            content: '';
            position: absolute;
            top: 4px;
            left: 7px;
            width: 4px;
            height: 8px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .choice-content {
            flex: 1;
            font-size: 15px;
            color: #4a5568;
        }

        /* Fill in the Blank Styling */
        .fill-blank-content {
            line-height: 1.8;
            font-size: 16px;
        }

        .blank-input {
            display: inline-block;
            min-width: 120px;
            padding: 4px 8px;
            border: 2px solid #e2e8f0;
            border-radius: 4px;
            margin: 0 4px;
            font-family: inherit;
            font-size: inherit;
            transition: all 0.2s ease;
        }

        .blank-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(3, 116, 181, 0.1);
        }

        /* Formula Question Styling */
        .formula-input-group {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
        }

        .variable-display {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
            padding: 12px;
            background: white;
            border-radius: 4px;
        }

        .variable-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: monospace;
        }

        .variable-name {
            font-weight: 500;
            color: var(--primary-color);
        }

        .formula-input {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 4px;
            font-family: monospace;
            font-size: 16px;
        }

        .formula-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(3, 116, 181, 0.1);
        }

        /* Action Buttons */
        .quiz-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 32px;
            gap: 16px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #025a8c;
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background: #f0f9ff;
        }

        /* Timer */
        .timer {
            font-size: 16px;
            font-weight: 500;
        }

        .timer.warning {
            color: var(--warning-color);
        }

        .timer.danger {
            color: var(--error-color);
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }
        }

        /* Security Warning Banner */
        .security-banner {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .security-banner i {
            color: #856404;
        }

        .security-banner-text {
            color: #856404;
            font-size: 14px;
        }

        /* Results Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 32px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .final-score {
            font-size: 48px;
            font-weight: 700;
            text-align: center;
            color: var(--primary-color);
            margin-bottom: 24px;
        }

        .score-details {
            display: grid;
            gap: 16px;
            margin-bottom: 32px;
        }

        .score-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8fafc;
            border-radius: 6px;
        }

        .feedback-section {
            margin-top: 24px;
        }

        .feedback-item {
            margin-bottom: 16px;
            padding: 12px;
            border-radius: 6px;
            border-left: 4px solid;
        }

        .feedback-item.correct {
            background: #f0fff4;
            border-left-color: var(--success-color);
        }

        .feedback-item.incorrect {
            background: #fef2f2;
            border-left-color: var(--error-color);
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            font-weight: 500;
            margin-bottom: 8px;
        }

        /* Media Queries */
        @media (max-width: 768px) {
            .quiz-container {
                padding: 16px;
            }

            .quiz-meta {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 12px;
            }

            .question {
                padding: 16px;
            }

            .modal-content {
                padding: 24px;
            }

            .quiz-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .btn {
                justify-content: center;
            }
        }
    </style>

    <link rel="stylesheet" href="quiz-style/quiz-app.css">
</head>

<body>

    <div class="quiz-container">
        <div class="security-banner">
            <i class="fas fa-shield-alt"></i>
            <div class="security-banner-text">
                This quiz is monitored for security. Switching tabs, opening developer tools, or other suspicious activities will be logged and may result in automatic submission.
            </div>
        </div>

        <div class="quiz-header">
            <h1 class="quiz-title" id="quizTitle"><?php echo htmlspecialchars($quizData['title']); ?></h1>

            <?php if (!empty($quizData['description'])): ?>
                <div class="quiz-description"><?php echo htmlspecialchars($quizData['description']); ?></div>
            <?php endif; ?>

            <div class="quiz-meta">
                <div class="quiz-meta-item">
                    <i class="fas fa-tasks"></i>
                    <span>Questions: <span id="questionCount"><?php echo count($quizData['questions'] ?? []); ?></span></span>
                </div>
                <div class="quiz-meta-item">
                    <i class="fas fa-star"></i>
                    <span>Points: <span id="totalPoints"><?php echo $totalPoints; ?></span></span>
                </div>
                <div class="quiz-meta-item">
                    <i class="fas fa-clock"></i>
                    <span>Time: <span id="timeLimit"><?php echo $options['timeLimit'] ?? 60; ?></span> minutes</span>
                </div>
                <?php if (isset($options['attempts']) && $options['attempts'] != -1): ?>
                    <div class="quiz-meta-item">
                        <i class="fas fa-redo"></i>
                        <span>Attempt: <span id="attemptCount"><?php echo $currentAttempt; ?>/<?php echo $options['attempts']; ?></span></span>
                    </div>
                <?php endif; ?>
                <div class="quiz-meta-item">
                    <i class="fas fa-hourglass-half"></i>
                    <span class="timer" id="timer">--:--</span>
                </div>
            </div>

            <div class="quiz-progress">
                <div class="quiz-progress-bar" id="progressBar" style="width: 0%"></div>
            </div>
        </div>

        <?php if (!empty($quizData['instructions'])): ?>
            <div class="quiz-instructions">
                <?php echo $quizData['instructions']; ?>
            </div>
        <?php endif; ?>

        <form id="quizForm">
            <div id="questionsContainer">
                <?php
                $questions = $quizData['questions'] ?? [];
                if ($options['shuffleQuestions'] ?? false) {
                    shuffle($questions);
                }

                foreach ($questions as $index => $question):
                    $questionNumber = $index + 1;
                ?>
                    <div class="question" data-type="<?php echo $question['type']; ?>"
                        data-multiple="<?php echo isset($question['isMultipleAnswer']) ? ($question['isMultipleAnswer'] ? 'true' : 'false') : 'false'; ?>"
                        <?php echo ($options['showOneQuestion'] ?? false) ? 'style="display: ' . ($index === 0 ? 'block' : 'none') . ';"' : ''; ?>>
                        <div class="question-header">
                            <div class="question-number">Question <?php echo $questionNumber; ?></div>
                            <div class="question-points"><?php echo $question['points']; ?> points</div>
                        </div>

                        <div class="question-content">
                            <?php
if ($question['type'] === 'fill_blank') {
    $processed = processQuestionContent($question['blankText']);
    echo '<div class="question-content">';
    if (!empty($processed['image'])) {
        echo $processed['image']; 
    }
    // Skip the text display here since it will be handled by the blank inputs section
    echo '</div>';
} else {
    $processed = processQuestionContent($question['content']);
    echo '<div class="question-content">';
    if (!empty($processed['image'])) {
        echo $processed['image'];
    }
    if (!empty($processed['text'])) {
        echo '<div class="question-text">' . $processed['text'] . '</div>';
    }
    echo '</div>';
}
?>
                        </div>

                        <?php
                        switch ($question['type']):
                            case 'multiple_choice':
                                $choices = $question['choices'];
                                if ($options['shuffleAnswers'] ?? false) {
                                    $choiceOrder = range(0, count($choices) - 1);
                                    shuffle($choiceOrder);
                                } else {
                                    $choiceOrder = range(0, count($choices) - 1);
                                }
                        ?>
                                <div class="choice-list">
                                    <?php foreach ($choiceOrder as $choiceIndex): ?>
                                        <label class="choice-item">
                                            <input type="<?php echo $question['isMultipleAnswer'] ? 'checkbox' : 'radio'; ?>"
                                                name="q<?php echo $questionNumber; ?><?php echo $question['isMultipleAnswer'] ? '[]' : ''; ?>"
                                                value="<?php echo $choiceIndex; ?>">
                                            <div class="choice-content"><?php echo $choices[$choiceIndex]; ?></div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                            <?php break;
                            case 'fill_blank':
                                $blankText = $question['blankText'] ?? '';
                                $blankCount = substr_count($blankText, '_');
                            ?>
                                <div class="fill-blank-content">
                                    <?php
                                    $parts = explode('_', $blankText);
                                    for ($i = 0; $i < count($parts); $i++):
                                        echo htmlspecialchars($parts[$i]);
                                        if ($i < count($parts) - 1):
                                    ?>
                                            <input type="text" class="blank-input"
                                                name="q<?php echo $questionNumber; ?>_blank<?php echo $i + 1; ?>"
                                                placeholder="Enter answer">
                                    <?php
                                        endif;
                                    endfor;
                                    ?>
                                </div>

                            <?php break;
                            case 'formula':
                            ?>
                                <div class="formula-input-group">
                                    <div class="variable-display">
                                        <?php foreach ($question['variables'] as $varName => $varData): ?>
                                            <div class="variable-item">
                                                <span class="variable-name"><?php echo $varName; ?> =</span>
                                                <span id="var_<?php echo $varName; ?>_<?php echo $questionNumber; ?>">
                                                    <?php
                                                    $min = floatval($varData['min']);
                                                    $max = floatval($varData['max']);
                                                    $step = floatval($varData['step']);
                                                    $decimals = intval($varData['decimals']);
                                                    $value = $min + (floor(((mt_rand() / mt_getrandmax()) * (($max - $min) / $step)))) * $step;
                                                    echo number_format($value, $decimals);
                                                    ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="number" step="0.001" class="formula-input"
                                        name="q<?php echo $questionNumber; ?>"
                                        placeholder="Enter your answer">
                                </div>
                        <?php break;
                        endswitch; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="quiz-actions">
                <?php if ($options['showOneQuestion'] ?? false): ?>
                    <button type="button" class="btn btn-outline" id="prevBtn" style="display: none;">
                        <i class="fas fa-chevron-left"></i> Previous Question
                    </button>
                    <button type="button" class="btn btn-primary" id="nextBtn">
                        Next Question <i class="fas fa-chevron-right"></i>
                    </button>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" id="submitBtn" <?php echo ($options['showOneQuestion'] ?? false) ? 'style="display: none;"' : ''; ?>>
                    <i class="fas fa-check"></i> Submit Quiz
                </button>
            </div>
        </form>
    </div>

    <!-- Results Modal -->
    <div id="resultsModal" class="modal">
        <div class="modal-content">
            <h2>Quiz Results</h2>
            <div class="final-score" id="finalScore"></div>

            <div class="score-details">
                <div class="score-item">
                    <span>Total Score:</span>
                    <strong id="scoreValue"></strong>
                </div>
                <div class="score-item">
                    <span>Correct Questions:</span>
                    <strong id="correctCount"></strong>
                </div>
                <div class="score-item">
                    <span>Time Used:</span>
                    <strong id="timeUsed"></strong>
                </div>
                <div class="score-item">
                    <span>Attempt:</span>
                    <strong id="attemptNumber"><?php echo $currentAttempt; ?></strong>
                </div>
            </div>

            <div id="questionFeedback"></div>

            <div style="text-align: center; margin-top: 24px;">
                <button type="button" onclick="window.location.href='assessments'" class="btn btn-primary">
                    Return to Assessments
                </button>
            </div>
        </div>
    </div>

    <script>
        // Enhanced Quiz state management with persistence
        let quizState = {
            sessionId: <?php echo json_encode($quizSession['sessionId']); ?>,
            sessionToken: <?php echo json_encode($sessionToken); ?>,
            startTime: new Date(<?php echo json_encode($quizSession['startTime']); ?>),
            remainingTime: <?php echo ($options['timeLimit'] ?? 60) * 60; ?>, // in seconds
            currentQuestion: <?php echo $quizSession['currentQuestion']; ?>,
            answers: <?php echo json_encode($quizSession['answers']); ?>,
            submitted: false,
            visibilityChanges: <?php echo $quizSession['visibilityChanges']; ?>,
            focusChanges: <?php echo $quizSession['focusChanges']; ?>,
            suspiciousActivity: <?php echo json_encode($quizSession['suspiciousActivity']); ?>,
            lastSaveTime: null,
            autoSaveInterval: null,
            securityWarnings: 0,
            browserFingerprint: null,
            isSecurityBreach: false
        };

        // Quiz configuration from PHP
        const quizConfig = {
            id: <?php echo json_encode($quizId); ?>,
            showOneQuestion: <?php echo json_encode($options['showOneQuestion'] ?? false); ?>,
            shuffleQuestions: <?php echo json_encode($options['shuffleQuestions'] ?? false); ?>,
            shuffleAnswers: <?php echo json_encode($options['shuffleAnswers'] ?? false); ?>,
            seeResponses: <?php echo json_encode($options['seeResponses'] ?? false); ?>,
            seeCorrectAnswers: <?php echo json_encode($options['seeCorrectAnswers'] ?? false); ?>,
            currentAttempt: <?php echo $currentAttempt; ?>,
            questions: <?php echo json_encode($questions); ?>,
            maxSuspiciousActivity: 3,
            autoSaveInterval: 30000, // 30 seconds
            securityMode: true
        };

        // Security monitoring variables
        let lastVisibilityTime = Date.now();
        let lastFocusTime = Date.now();
        let devToolsOpen = false;
        let windowResizeCount = 0;
        let tabSwitchCount = 0;

        // Initialize quiz
        document.addEventListener('DOMContentLoaded', function() {
            generateBrowserFingerprint();
            initializeQuiz();
            setupEventHandlers();
            setupSecurityMonitoring();
            startQuizTimer();
            loadSavedAnswers();
            startAutoSave();
        });

        function processQuestionContent($content) {
            // First decode any HTML entities
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Convert escaped image tags to proper HTML
            $content = str_replace(
                ['\"', '\/', '<div><img src=', '<\/div>'],
                ['"', '/', '<img src=', '</div>'],
                $content
            );

            return $content;
        }

        function generateBrowserFingerprint() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillText('Quiz Security Check', 2, 2);

            const fingerprint = {
                screen: screen.width + 'x' + screen.height,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                language: navigator.language,
                platform: navigator.platform,
                canvas: canvas.toDataURL(),
                userAgent: navigator.userAgent.substring(0, 100) // Truncated for storage
            };

            quizState.browserFingerprint = btoa(JSON.stringify(fingerprint));
        }

        function setupSecurityMonitoring() {
            // Disable right-click
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                logSuspiciousActivity('right_click_attempt');
                return false;
            });

            // Disable F12, Ctrl+Shift+I, Ctrl+U, etc.
            document.addEventListener('keydown', function(e) {
                // F12 or Ctrl+Shift+I or Ctrl+Shift+J or Ctrl+U
                if (e.key === 'F12' ||
                    (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J')) ||
                    (e.ctrlKey && e.key === 'U') ||
                    (e.ctrlKey && e.key === 'S')) {
                    e.preventDefault();
                    logSuspiciousActivity('developer_tools_attempt');
                    showSecurityWarning('Developer tools access detected!');
                    return false;
                }
            });

            // Monitor visibility changes (tab switching)
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    lastVisibilityTime = Date.now();
                    tabSwitchCount++;
                    logSuspiciousActivity('tab_switch', {
                        switchCount: tabSwitchCount,
                        timeAway: 0
                    });
                } else {
                    const timeAway = Date.now() - lastVisibilityTime;
                    if (timeAway > 5000) { // More than 5 seconds away
                        quizState.visibilityChanges++;
                        logSuspiciousActivity('prolonged_tab_switch', {
                            timeAway: timeAway,
                            switchCount: tabSwitchCount
                        });

                        if (tabSwitchCount > 3) {
                            showSecurityWarning('Multiple tab switches detected. Continued suspicious activity may result in automatic submission.');
                        }
                    }
                }
                updateQuizSession();
            });

            // Monitor focus changes
            window.addEventListener('blur', function() {
                lastFocusTime = Date.now();
                quizState.focusChanges++;
                logSuspiciousActivity('focus_lost');
                updateQuizSession();
            });

            window.addEventListener('focus', function() {
                const timeAway = Date.now() - lastFocusTime;
                if (timeAway > 10000) { // More than 10 seconds away
                    logSuspiciousActivity('prolonged_focus_loss', {
                        timeAway: timeAway
                    });
                }
            });

            // Monitor window resizing (potential developer tools)
            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                windowResizeCount++;

                resizeTimeout = setTimeout(function() {
                    if (windowResizeCount > 5) {
                        logSuspiciousActivity('excessive_window_resize', {
                            count: windowResizeCount
                        });
                    }
                    windowResizeCount = 0;
                }, 1000);

                // Check for developer tools (simplified detection)
                if (window.outerHeight - window.innerHeight > 200 ||
                    window.outerWidth - window.innerWidth > 200) {
                    devToolsOpen = true;
                    logSuspiciousActivity('developer_tools_detected');
                    showSecurityWarning('Developer tools detected! This activity is being logged.');
                }
            });

            // Monitor for copy/paste attempts
            document.addEventListener('copy', function(e) {
                logSuspiciousActivity('copy_attempt');
                e.preventDefault();
                return false;
            });

            document.addEventListener('paste', function(e) {
                logSuspiciousActivity('paste_attempt');
                // Allow paste in input fields but log it
            });

            // Print detection
            window.addEventListener('beforeprint', function(e) {
                logSuspiciousActivity('print_attempt');
                e.preventDefault();
                return false;
            });
        }

        function logSuspiciousActivity(type, data = {}) {
            const activity = {
                type: type,
                timestamp: new Date().toISOString(),
                data: data,
                url: window.location.href,
                userAgent: navigator.userAgent.substring(0, 100)
            };

            quizState.suspiciousActivity.push(activity);

            // Check if security breach threshold reached
            const criticalActivities = ['developer_tools_attempt', 'developer_tools_detected', 'prolonged_tab_switch'];
            const criticalCount = quizState.suspiciousActivity.filter(a => criticalActivities.includes(a.type)).length;

            if (criticalCount >= quizConfig.maxSuspiciousActivity) {
                quizState.isSecurityBreach = true;
                autoSubmitForSecurity();
            }

            // Update session immediately for security events
            updateQuizSession(true);
        }

        function showSecurityWarning(message) {
            quizState.securityWarnings++;

            const warningDiv = document.createElement('div');
            warningDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #dc3545;
        color: white;
        padding: 15px 20px;
        border-radius: 6px;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        max-width: 300px;
        font-weight: 500;
    `;
            warningDiv.innerHTML = `
        <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
        ${message}
    `;

            document.body.appendChild(warningDiv);

            setTimeout(() => {
                warningDiv.remove();
            }, 5000);
        }

        function autoSubmitForSecurity() {
            if (quizState.submitted) return;

            alert('Security breach detected! Your quiz will be submitted automatically due to suspicious activity.');
            submitQuiz(false, true); // Submit with security flag
        }

        function initializeQuiz() {
            // Initialize MathJax if available
            if (typeof MathJax !== 'undefined') {
                MathJax.Hub.Queue(["Typeset", MathJax.Hub]);
            }

            // Set up choice selection handlers
            document.querySelectorAll('.choice-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    if (e.target === item || e.target.classList.contains('choice-content')) {
                        const input = this.querySelector('input');
                        if (input.type === 'radio') {
                            // Clear all selections in this group first
                            const questionDiv = this.closest('.question');
                            questionDiv.querySelectorAll('.choice-item').forEach(choice => {
                                choice.classList.remove('selected');
                            });

                            input.checked = true;
                            this.classList.add('selected');
                        } else {
                            input.checked = !input.checked;
                            this.classList.toggle('selected');
                        }

                        // Save answer immediately
                        saveCurrentAnswers();
                    }
                });
            });

            // Show appropriate question based on session state
            if (quizConfig.showOneQuestion) {
                const questions = document.querySelectorAll('.question');
                questions.forEach((q, index) => {
                    q.style.display = index === quizState.currentQuestion ? 'block' : 'none';
                });
                updateQuestionNavigation();
                updateProgressBar();
            }
        }

        function setupEventHandlers() {
            // Form submission
            document.getElementById('quizForm').addEventListener('submit', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to submit your quiz? This action cannot be undone.')) {
                    submitQuiz();
                }
            });

            // Question navigation (if enabled)
            if (quizConfig.showOneQuestion) {
                const prevBtn = document.getElementById('prevBtn');
                const nextBtn = document.getElementById('nextBtn');

                if (prevBtn) prevBtn.addEventListener('click', showPreviousQuestion);
                if (nextBtn) nextBtn.addEventListener('click', showNextQuestion);
            }

            // Handle formula inputs
            document.querySelectorAll('.formula-input').forEach(input => {
                input.addEventListener('input', function() {
                    // Allow numbers, decimal points, and negative signs
                    this.value = this.value.replace(/[^0-9.-]/g, '');
                    saveCurrentAnswers();
                });
            });

            // Handle blank inputs
            document.querySelectorAll('.blank-input').forEach(input => {
                input.addEventListener('input', function() {
                    saveCurrentAnswers();
                });

                input.addEventListener('blur', function() {
                    this.value = this.value.trim();
                    saveCurrentAnswers();
                });
            });
        }

        function loadSavedAnswers() {
            // Restore answers from session
            Object.keys(quizState.answers).forEach(questionKey => {
                const answer = quizState.answers[questionKey];

                if (Array.isArray(answer)) {
                    // Multiple choice with multiple answers
                    answer.forEach(value => {
                        const input = document.querySelector(`input[name="${questionKey}[]"][value="${value}"]`);
                        if (input) {
                            input.checked = true;
                            input.closest('.choice-item').classList.add('selected');
                        }
                    });
                } else if (typeof answer === 'object' && answer !== null) {
                    // Fill in the blank or formula
                    if (answer.blankAnswers) {
                        answer.blankAnswers.forEach(blank => {
                            const input = document.querySelector(`input[name="${blank.inputName}"]`);
                            if (input) input.value = blank.answer;
                        });
                    } else if (answer.answer !== undefined) {
                        // Formula answer
                        const input = document.querySelector(`input[name="${questionKey}"]`);
                        if (input) input.value = answer.answer;
                    }
                } else {
                    // Single choice
                    const input = document.querySelector(`input[name="${questionKey}"][value="${answer}"]`);
                    if (input) {
                        input.checked = true;
                        input.closest('.choice-item').classList.add('selected');
                    }
                }
            });
        }

        function saveCurrentAnswers() {
            const questions = document.querySelectorAll('.question');
            const answers = {};

            questions.forEach((questionEl, index) => {
                const questionType = questionEl.dataset.type;
                const questionNum = index + 1;
                const questionKey = `q${questionNum}`;

                switch (questionType) {
                    case 'multiple_choice': {
                        const isMultiple = questionEl.dataset.multiple === 'true';
                        if (isMultiple) {
                            const checkboxes = questionEl.querySelectorAll('input[type="checkbox"]:checked');
                            const values = Array.from(checkboxes).map(cb => parseInt(cb.value));
                            if (values.length > 0) answers[questionKey] = values;
                        } else {
                            const radio = questionEl.querySelector('input[type="radio"]:checked');
                            if (radio) answers[questionKey] = parseInt(radio.value);
                        }
                        break;
                    }
                    case 'fill_blank': {
                        const inputs = questionEl.querySelectorAll('.blank-input');
                        const blankAnswers = Array.from(inputs).map((input, i) => ({
                            blankIndex: i + 1,
                            answer: input.value.trim(),
                            inputName: input.name
                        })).filter(blank => blank.answer !== '');

                        if (blankAnswers.length > 0) {
                            answers[questionKey] = {
                                blankAnswers: blankAnswers
                            };
                        }
                        break;
                    }
                    case 'formula': {
                        const formulaInput = questionEl.querySelector('.formula-input');
                        if (formulaInput && formulaInput.value.trim() !== '') {
                            // Collect variable values
                            const variables = {};
                            questionEl.querySelectorAll('.variable-item').forEach(varItem => {
                                const varName = varItem.querySelector('.variable-name').textContent.replace(' =', '');
                                const varValue = varItem.querySelector('span[id^="var_"]').textContent;
                                variables[varName] = parseFloat(varValue);
                            });

                            answers[questionKey] = {
                                answer: formulaInput.value.trim(),
                                variables: variables
                            };
                        }
                        break;
                    }
                }
            });

            quizState.answers = answers;
            quizState.lastSaveTime = new Date().toISOString();
        }

        function startAutoSave() {
            quizState.autoSaveInterval = setInterval(() => {
                if (!quizState.submitted) {
                    saveCurrentAnswers();
                    updateQuizSession();
                }
            }, quizConfig.autoSaveInterval);
        }

        function updateQuizSession(isSecurityEvent = false) {

            console.log('Updating quiz session...');
            console.log('Session Token:', quizState.sessionToken);
            console.log('Session ID:', quizState.sessionId);
            // Verify we have a valid token before sending
            if (!quizState.sessionToken) {
                console.error('Missing session token');
                return;
            }

            const sessionData = {
                sessionId: quizState.sessionId,
                currentQuestion: quizState.currentQuestion,
                answers: quizState.answers,
                visibilityChanges: quizState.visibilityChanges,
                focusChanges: quizState.focusChanges,
                suspiciousActivity: quizState.suspiciousActivity,
                lastActivity: new Date().toISOString(),
                browserFingerprint: quizState.browserFingerprint,
                securityWarnings: quizState.securityWarnings,
                isSecurityBreach: quizState.isSecurityBreach
            };

            // Send update to server
            fetch('update-quiz-session', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        sessionToken: quizState.sessionToken,
                        sessionData: sessionData,
                        isSecurityEvent: isSecurityEvent
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            console.error('Session update failed:', err);
                            throw err;
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Session update successful:', data);
                })
                .catch(error => {
                    console.error('Session update error:', error);
                });
        }

        function startQuizTimer() {
            const startTime = quizState.startTime;
            const timerElement = document.getElementById('timer');

            // Calculate elapsed time since quiz started
            const elapsed = Math.floor((new Date() - startTime) / 1000);
            quizState.remainingTime = Math.max(0, quizState.remainingTime - elapsed);

            function updateTimer() {
                if (quizState.submitted) return;

                quizState.remainingTime--;

                if (quizState.remainingTime <= 0) {
                    alert('Time is up! Your quiz will be submitted automatically.');
                    submitQuiz(true);
                    return;
                }

                const minutes = Math.floor(quizState.remainingTime / 60);
                const seconds = quizState.remainingTime % 60;
                const display = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                timerElement.textContent = display;
                timerElement.className = quizState.remainingTime <= 300 ? 'timer danger' :
                    quizState.remainingTime <= 600 ? 'timer warning' : 'timer';
            }

            updateTimer();
            const timerInterval = setInterval(updateTimer, 1000);

            // Store timer interval for cleanup
            quizState.timerInterval = timerInterval;
        }

        function showPreviousQuestion() {
            if (quizState.currentQuestion > 0) {
                saveCurrentAnswers();
                const questions = document.querySelectorAll('.question');
                questions[quizState.currentQuestion].style.display = 'none';
                quizState.currentQuestion--;
                questions[quizState.currentQuestion].style.display = 'block';
                updateQuestionNavigation();
                updateProgressBar();
                updateQuizSession();
            }
        }

        function showNextQuestion() {
            const questions = document.querySelectorAll('.question');
            if (quizState.currentQuestion < questions.length - 1) {
                saveCurrentAnswers();
                questions[quizState.currentQuestion].style.display = 'none';
                quizState.currentQuestion++;
                questions[quizState.currentQuestion].style.display = 'block';
                updateQuestionNavigation();
                updateProgressBar();
                updateQuizSession();
            }
        }

        function updateQuestionNavigation() {
            const questions = document.querySelectorAll('.question');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');

            if (prevBtn) prevBtn.style.display = quizState.currentQuestion > 0 ? 'inline-flex' : 'none';

            if (quizState.currentQuestion === questions.length - 1) {
                if (nextBtn) nextBtn.style.display = 'none';
                if (submitBtn) submitBtn.style.display = 'inline-flex';
            } else {
                if (nextBtn) nextBtn.style.display = 'inline-flex';
                if (submitBtn) submitBtn.style.display = 'none';
            }
        }

        function updateProgressBar() {
            const questions = document.querySelectorAll('.question');
            const progress = ((quizState.currentQuestion + 1) / questions.length) * 100;
            document.getElementById('progressBar').style.width = `${progress}%`;
        }

        function submitQuiz(isTimeout = false, isSecurityBreach = false) {
            if (quizState.submitted) return;

            quizState.submitted = true;

            // Clear intervals
            if (quizState.autoSaveInterval) clearInterval(quizState.autoSaveInterval);
            if (quizState.timerInterval) clearInterval(quizState.timerInterval);

            // Final save of answers
            saveCurrentAnswers();

            // Calculate time used
            const endTime = new Date();
            const timeUsed = Math.floor((endTime - quizState.startTime) / 1000);

            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                submitBtn.disabled = true;
            }

            // Prepare submission data
            const submissionData = {
                quizId: quizConfig.id,
                sessionId: quizState.sessionId,
                sessionToken: quizState.sessionToken,
                answers: quizState.answers,
                timeUsed: timeUsed,
                attempt: quizConfig.currentAttempt,
                meta: {
                    timeLimit: quizState.remainingTime / 60,
                    isTimeout: isTimeout,
                    isSecurityBreach: isSecurityBreach,
                    startTime: quizState.startTime.toISOString(),
                    endTime: endTime.toISOString(),
                    visibilityChanges: quizState.visibilityChanges,
                    focusChanges: quizState.focusChanges,
                    suspiciousActivity: quizState.suspiciousActivity,
                    browserFingerprint: quizState.browserFingerprint,
                    securityWarnings: quizState.securityWarnings
                }
            };

            // Send answers to server
            fetch('submit-answers', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        // Add CSRF token if you have one
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(submissionData)
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            console.error('Submission failed:', err);
                            throw new Error(err.message || `Server error: ${response.status}`);
                        });
                    }
                    return response.json();
                })
                .then(result => {
                    if (result.error) {
                        throw new Error(result.error);
                    }
                    showResults(result);
                    cleanupSession();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('There was an error submitting your quiz: ' + error.message + '\n\nPlease try again or contact your instructor.');

                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-check"></i> Submit Quiz';
                        submitBtn.disabled = false;
                    }
                    quizState.submitted = false;
                    startAutoSave();
                });
        }

        function cleanupSession() {
            // Send cleanup request
            fetch('cleanup-quiz-session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    sessionToken: quizState.sessionToken
                })
            });
        }

        function showResults(results) {
            const modal = document.getElementById('resultsModal');
            const scoreValue = document.getElementById('scoreValue');
            const correctCount = document.getElementById('correctCount');
            const timeUsed = document.getElementById('timeUsed');
            const finalScore = document.getElementById('finalScore');
            const attemptNumber = document.getElementById('attemptNumber');

            // Calculate percentage
            const percentage = results.totalPoints > 0 ?
                ((results.score / results.totalPoints) * 100).toFixed(1) : 0;
            finalScore.textContent = `${percentage}%`;

            // Update score details
            scoreValue.textContent = `${results.score}/${results.totalPoints}`;
            correctCount.textContent = `${results.correctQuestions}/${results.totalQuestions}`;

            // Format time used
            const hours = Math.floor(results.timeUsed / 3600);
            const minutes = Math.floor((results.timeUsed % 3600) / 60);
            const seconds = results.timeUsed % 60;
            const timeString = hours > 0 ?
                `${hours}h ${minutes}m ${seconds}s` :
                `${minutes}m ${seconds}s`;
            timeUsed.textContent = timeString;

            attemptNumber.textContent = results.attemptNumber || quizConfig.currentAttempt;

            // Show security warning if applicable
            if (results.securityFlags && results.securityFlags.length > 0) {
                const securityWarning = document.createElement('div');
                securityWarning.style.cssText = `
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 20px;
            color: #856404;
        `;
                securityWarning.innerHTML = `
            <strong><i class="fas fa-exclamation-triangle"></i> Security Notice:</strong>
            Your quiz session was flagged for suspicious activity. This has been recorded for review.
        `;
                modal.querySelector('.modal-content').insertBefore(securityWarning, modal.querySelector('.score-details'));
            }

            // Show feedback if available
            const feedbackContainer = document.getElementById('questionFeedback');
            if (results.feedback && feedbackContainer) {
                let feedbackHtml = '<div class="feedback-section"><h3>Question Feedback</h3>';
                results.feedback.forEach((item) => {
                    feedbackHtml += `
                <div class="feedback-item ${item.correct ? 'correct' : 'incorrect'}">
                    <div class="feedback-header">
                        <span>Question ${item.question}</span>
                        <span>${item.points} / ${item.totalPoints} points</span>
                    </div>
                    <div>${item.feedback}</div>
                </div>
            `;
                });
                feedbackHtml += '</div>';
                feedbackContainer.innerHTML = feedbackHtml;
            }

            // Show modal
            modal.classList.add('show');

            // Disable form to prevent resubmission
            document.getElementById('quizForm').style.pointerEvents = 'none';
            document.getElementById('quizForm').style.opacity = '0.5';
        }

        // Enhanced page unload protection
        window.addEventListener('beforeunload', function(e) {
            if (!quizState.submitted && !quizState.isSecurityBreach) {
                // Save current state before leaving
                saveCurrentAnswers();
                updateQuizSession();

                e.preventDefault();
                e.returnValue = 'Are you sure you want to leave? Your progress has been saved, but leaving may be flagged as suspicious activity.';
                return e.returnValue;
            }
        });

        // Page load event to log
        window.addEventListener('load', function() {
            logSuspiciousActivity('page_loaded', {
                loadTime: Date.now(),
                referrer: document.referrer
            });
        });
    </script>

</body>

</html>