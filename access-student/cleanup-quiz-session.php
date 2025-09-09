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

if (!$sessionToken) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing session token']);
    exit;
}

$userId = $_SESSION['user_id'];
$sessionDir = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quiz-sessions/';

// Find and validate session file
$pattern = $sessionDir . '*_' . $userId . '.json';
$sessionFiles = glob($pattern);

$validSession = false;
$sessionFile = null;
$quizId = null;
$sessionId = null;

foreach ($sessionFiles as $file) {
    $sessionData = json_decode(file_get_contents($file), true);
    if ($sessionData && isset($sessionData['sessionId'])) {
        // Extract quiz ID from filename
        $filename = basename($file, '.json');
        $parts = explode('_', $filename);
        if (count($parts) >= 2) {
            $testQuizId = $parts[0];
            $expectedToken = hash('sha256', $sessionData['sessionId'] . $userId . $testQuizId);
            
            if ($sessionToken === $expectedToken) {
                $validSession = true;
                $sessionFile = $file;
                $quizId = $testQuizId;
                $sessionId = $sessionData['sessionId'];
                break;
            }
        }
    }
}

if (!$validSession) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session token']);
    exit;
}

// Load session data
$sessionData = json_decode(file_get_contents($sessionFile), true);
if (!$sessionData) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load session data']);
    exit;
}

// Archive session data before cleanup
$archiveDir = $sessionDir . 'archived/';
if (!is_dir($archiveDir)) {
    mkdir($archiveDir, 0755, true);
}

$archiveFile = $archiveDir . $quizId . '_' . $userId . '_' . date('Y-m-d_H-i-s') . '.json';

// Add cleanup metadata
$sessionData['cleanedUp'] = true;
$sessionData['cleanupTime'] = date('c');
$sessionData['cleanupUserId'] = $userId;

// Archive the session
if (file_put_contents($archiveFile, json_encode($sessionData, JSON_PRETTY_PRINT)) === false) {
    error_log("Failed to archive session data for user $userId, quiz $quizId");
}

// Create cleanup log entry
$cleanupLogFile = $sessionDir . 'cleanup_log.jsonl';
$cleanupEntry = [
    'timestamp' => date('c'),
    'userId' => $userId,
    'quizId' => $quizId,
    'sessionId' => $sessionId,
    'originalFile' => basename($sessionFile),
    'archiveFile' => basename($archiveFile),
    'sessionStatus' => $sessionData['status'] ?? 'unknown',
    'sessionDuration' => calculateSessionDuration($sessionData),
    'totalSuspiciousActivities' => count($sessionData['suspiciousActivity'] ?? []),
    'visibilityChanges' => $sessionData['visibilityChanges'] ?? 0,
    'focusChanges' => $sessionData['focusChanges'] ?? 0,
    'autoSubmitted' => $sessionData['autoSubmitted'] ?? false,
    'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
    'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
];

file_put_contents($cleanupLogFile, json_encode($cleanupEntry) . "\n", FILE_APPEND | LOCK_EX);

// Remove the active session file
if (unlink($sessionFile)) {
    $success = true;
    $message = 'Session cleaned up successfully';
} else {
    $success = false;
    $message = 'Failed to remove session file';
    error_log("Failed to remove session file: $sessionFile");
}

// Clean up old archived sessions (keep only last 30 days)
cleanupOldArchivedSessions($archiveDir);

// Clean up old log entries (keep only last 90 days)
cleanupOldLogEntries($sessionDir);

echo json_encode([
    'success' => $success,
    'message' => $message,
    'archived' => file_exists($archiveFile),
    'archiveFile' => basename($archiveFile)
]);

function calculateSessionDuration($sessionData) {
    if (!isset($sessionData['startTime'])) {
        return null;
    }
    
    $startTime = new DateTime($sessionData['startTime']);
    $endTime = isset($sessionData['endTime']) ? 
        new DateTime($sessionData['endTime']) : 
        new DateTime($sessionData['cleanupTime'] ?? 'now');
    
    return $endTime->getTimestamp() - $startTime->getTimestamp();
}

function cleanupOldArchivedSessions($archiveDir) {
    $cutoffTime = time() - (30 * 24 * 60 * 60); // 30 days ago
    $files = glob($archiveDir . '*.json');
    $cleaned = 0;
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            if (unlink($file)) {
                $cleaned++;
            }
        }
    }
    
    if ($cleaned > 0) {
        error_log("Cleaned up $cleaned old archived quiz sessions");
    }
}

function cleanupOldLogEntries($sessionDir) {
    $logFiles = [
        'cleanup_log.jsonl',
        'security_events.jsonl'
    ];
    
    $cutoffTime = time() - (90 * 24 * 60 * 60); // 90 days ago
    
    foreach ($logFiles as $logFile) {
        $filePath = $sessionDir . $logFile;
        if (!file_exists($filePath)) continue;
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) continue;
        
        $filteredLines = [];
        $removed = 0;
        
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data && isset($data['timestamp'])) {
                $entryTime = strtotime($data['timestamp']);
                if ($entryTime >= $cutoffTime) {
                    $filteredLines[] = $line;
                } else {
                    $removed++;
                }
            } else {
                // Keep malformed entries for debugging
                $filteredLines[] = $line;
            }
        }
        
        if ($removed > 0) {
            // Create backup before cleaning
            copy($filePath, $filePath . '.backup.' . date('Y-m-d'));
            
            // Write cleaned log
            file_put_contents($filePath, implode("\n", $filteredLines) . "\n");
            error_log("Cleaned up $removed old entries from $logFile");
        }
    }
}
?>