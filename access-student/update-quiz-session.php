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

$sessionToken = $input['sessionToken'] ?? null;
$sessionData = $input['sessionData'] ?? null;
$isSecurityEvent = $input['isSecurityEvent'] ?? false;

if (!$sessionToken || !$sessionData) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$userId = $_SESSION['user_id'];
$sessionId = $sessionData['sessionId'];

// Find the quiz ID and validate session token
$sessionDir = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quiz-sessions/';
$pattern = $sessionDir . '*_' . $userId . '.json';
$sessionFiles = glob($pattern);

$validSession = false;
$sessionFile = null;
$quizId = null;

// Add this after the initial POST data processing
error_log('=== Session Update Debug ===');
error_log('Received Token: ' . $sessionToken);
error_log('User ID: ' . $userId);
error_log('Session ID: ' . $sessionId);

// Modify the validation loop with logging
foreach ($sessionFiles as $file) {
    error_log('Checking file: ' . basename($file));
    $existingData = json_decode(file_get_contents($file), true);
    
    if ($existingData && isset($existingData['sessionId']) && $existingData['sessionId'] === $sessionId) {
        $filename = basename($file, '.json');
        $parts = explode('_', $filename);
        
        if (count($parts) >= 2) {
            $quizId = $parts[0];
            // Match the exact same token generation as in take-quiz.php
            $expectedToken = hash('sha256', $sessionId . $userId . $quizId);
            
            error_log('Token comparison:');
            error_log('Session ID: ' . $sessionId);
            error_log('User ID: ' . $userId);
            error_log('Quiz ID: ' . $quizId);
            error_log('Expected Token: ' . $expectedToken);
            error_log('Received Token: ' . $sessionToken);
            
            if (hash_equals($expectedToken, $sessionToken)) {
                $validSession = true;
                $sessionFile = $file;
                error_log('Token validation successful');
                break;
            } else {
                error_log('Token validation failed for file: ' . $file);
            }
        } else {
            error_log('Invalid filename format: ' . $filename);
        }
    } else {
        error_log('No matching session data in file: ' . $file);
    }
}

if (!$validSession) {
    error_log('Final validation failed. Files checked: ' . count($sessionFiles));
    error_log('Session directory: ' . $sessionDir);
    error_log('Pattern searched: ' . $pattern);
    
    http_response_code(403);
    echo json_encode([
        'error' => 'Invalid session token',
        'debug' => [
            'sessionId' => $sessionId,
            'userId' => $userId,
            'filesChecked' => count($sessionFiles),
            'pattern' => $pattern
        ]
    ]);
    exit;
}

// Load existing session data
$existingSessionData = json_decode(file_get_contents($sessionFile), true);
if (!$existingSessionData || $existingSessionData['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['error' => 'Session not active']);
    exit;
}

// Merge session data with security validations
$updatedSessionData = array_merge($existingSessionData, [
    'currentQuestion' => $sessionData['currentQuestion'],
    'answers' => $sessionData['answers'],
    'visibilityChanges' => max($existingSessionData['visibilityChanges'] ?? 0, $sessionData['visibilityChanges'] ?? 0),
    'focusChanges' => max($existingSessionData['focusChanges'] ?? 0, $sessionData['focusChanges'] ?? 0),
    'lastActivity' => $sessionData['lastActivity'],
    'securityWarnings' => max($existingSessionData['securityWarnings'] ?? 0, $sessionData['securityWarnings'] ?? 0),
    'isSecurityBreach' => ($existingSessionData['isSecurityBreach'] ?? false) || ($sessionData['isSecurityBreach'] ?? false)
]);

// Merge suspicious activities (avoid duplicates)
$existingActivities = $existingSessionData['suspiciousActivity'] ?? [];
$newActivities = $sessionData['suspiciousActivity'] ?? [];

// Only add new activities that aren't already recorded
$latestActivityTime = 0;
foreach ($existingActivities as $activity) {
    $activityTime = strtotime($activity['timestamp']);
    if ($activityTime > $latestActivityTime) {
        $latestActivityTime = $activityTime;
    }
}

foreach ($newActivities as $activity) {
    $activityTime = strtotime($activity['timestamp']);
    if ($activityTime > $latestActivityTime) {
        $existingActivities[] = $activity;
    }
}

$updatedSessionData['suspiciousActivity'] = $existingActivities;

// Update browser fingerprint if provided and valid
if (isset($sessionData['browserFingerprint']) && !empty($sessionData['browserFingerprint'])) {
    $updatedSessionData['browserFingerprint'] = $sessionData['browserFingerprint'];
}

// Security event handling
if ($isSecurityEvent) {
    $updatedSessionData['lastSecurityEvent'] = date('c');

    // Log security event
    $securityLogFile = $sessionDir . 'security_events.jsonl';
    $securityEvent = [
        'timestamp' => date('c'),
        'userId' => $userId,
        'sessionId' => $sessionId,
        'quizId' => $quizId,
        'eventType' => 'session_update',
        'securityData' => [
            'visibilityChanges' => $sessionData['visibilityChanges'] ?? 0,
            'focusChanges' => $sessionData['focusChanges'] ?? 0,
            'suspiciousActivityCount' => count($newActivities),
            'isSecurityBreach' => $sessionData['isSecurityBreach'] ?? false
        ],
        'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];

    file_put_contents($securityLogFile, json_encode($securityEvent) . "\n", FILE_APPEND | LOCK_EX);
}

// Check for automatic submission conditions
$shouldAutoSubmit = false;
$autoSubmitReason = '';

// Check for excessive security violations
$criticalActivities = ['developer_tools_attempt', 'developer_tools_detected', 'prolonged_tab_switch'];
$criticalCount = 0;

foreach ($updatedSessionData['suspiciousActivity'] as $activity) {
    if (in_array($activity['type'], $criticalActivities)) {
        $criticalCount++;
    }
}

if ($criticalCount >= 3) {
    $shouldAutoSubmit = true;
    $autoSubmitReason = 'excessive_security_violations';
}

// Check for excessive tab switching
if (($updatedSessionData['visibilityChanges'] ?? 0) > 10) {
    $shouldAutoSubmit = true;
    $autoSubmitReason = 'excessive_tab_switching';
}

// Check session timeout
$sessionStartTime = new DateTime($updatedSessionData['startTime']);
$now = new DateTime();
$sessionDuration = $now->getTimestamp() - $sessionStartTime->getTimestamp();

// Load quiz data to get time limit
$quizJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/' . $quizId . '.json';
if (file_exists($quizJsonPath)) {
    $quizData = json_decode(file_get_contents($quizJsonPath), true);
    $timeLimit = intval(($quizData['options']['timeLimit'] ?? 60)) * 60; // Convert to seconds

    if ($sessionDuration > $timeLimit) {
        $shouldAutoSubmit = true;
        $autoSubmitReason = 'time_exceeded';
        $updatedSessionData['status'] = 'expired';
    }
}

// Auto-submit if necessary
if ($shouldAutoSubmit) {
    $updatedSessionData['autoSubmitted'] = true;
    $updatedSessionData['autoSubmitReason'] = $autoSubmitReason;
    $updatedSessionData['autoSubmitTime'] = date('c');
    $updatedSessionData['status'] = 'auto_submitted';

    // Log auto-submission
    error_log("Auto-submitting quiz session for user $userId, quiz $quizId. Reason: $autoSubmitReason");
}

// Save updated session data with atomic write
$tempFile = $sessionFile . '.tmp';
$jsonData = json_encode($updatedSessionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if ($jsonData === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to encode session data']);
    exit;
}

if (file_put_contents($tempFile, $jsonData, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save session data']);
    exit;
}

if (!rename($tempFile, $sessionFile)) {
    unlink($tempFile);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to finalize session save']);
    exit;
}

// Response data
$response = [
    'success' => true,
    'sessionUpdated' => true,
    'lastActivity' => $updatedSessionData['lastActivity']
];

// Add auto-submit information if applicable
if ($shouldAutoSubmit) {
    $response['autoSubmit'] = true;
    $response['autoSubmitReason'] = $autoSubmitReason;
    $response['message'] = getAutoSubmitMessage($autoSubmitReason);
}

// Add security warnings if needed
if ($isSecurityEvent && !$shouldAutoSubmit) {
    $response['securityWarning'] = true;

    if ($criticalCount >= 2) {
        $response['message'] = 'Multiple security violations detected. Continued suspicious activity will result in automatic submission.';
    } elseif (($updatedSessionData['visibilityChanges'] ?? 0) > 5) {
        $response['message'] = 'Excessive tab switching detected. Please stay focused on the quiz.';
    }
}

echo json_encode($response);

function getAutoSubmitMessage($reason)
{
    switch ($reason) {
        case 'excessive_security_violations':
            return 'Quiz automatically submitted due to multiple security violations (developer tools, tab switching, etc.)';
        case 'excessive_tab_switching':
            return 'Quiz automatically submitted due to excessive tab switching';
        case 'time_exceeded':
            return 'Quiz automatically submitted because time limit was exceeded';
        default:
            return 'Quiz automatically submitted due to suspicious activity';
    }
}
