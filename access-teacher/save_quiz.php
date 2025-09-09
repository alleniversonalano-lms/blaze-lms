<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log file for debugging
$logFile = __DIR__ . '/debug_log.txt';
file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Save quiz request received\n", FILE_APPEND);

header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Error: Method not allowed\n", FILE_APPEND);
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get the JSON data from the request body
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData);

file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Received data: " . substr($jsonData, 0, 100) . "...\n", FILE_APPEND);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// Create quizzes directory if it doesn't exist in the shared assessments folder
$quizDir = dirname(dirname(__FILE__)) . '/assessment-list/quizzes';
if (!file_exists($quizDir)) {
    mkdir($quizDir, 0777, true);
}

// Generate unique filename using timestamp and quiz title
$filename = 'quiz_' . time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $data->title) . '.json';
$filepath = $quizDir . '/' . $filename;

// Save the JSON data to file
if (file_put_contents($filepath, $jsonData)) {
    echo json_encode([
        'success' => true,
        'message' => 'Quiz saved successfully',
        'filename' => $filename
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to save quiz'
    ]);
}
?>
