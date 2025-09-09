<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log file for debugging
$logFile = __DIR__ . '/debug_log.txt';
file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Save quiz edit request received\n", FILE_APPEND);

session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'teacher') {
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Error: Access denied\n", FILE_APPEND);
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

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

// Log initial data received
file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Initial data received:\n", FILE_APPEND);
file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "- Filepath ID: " . (isset($data->id) ? $data->id : 'not set') . "\n", FILE_APPEND);
file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "- Formatted UUID: " . (isset($data->uuid) ? $data->uuid : 'not set') . "\n", FILE_APPEND);

// Clean the filepath ID by removing &type=saved if present
if (isset($data->id)) {
    $originalId = $data->id;
    $data->id = preg_replace('/&type=saved$/', '', $data->id);
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Filepath ID Cleaning:\n", FILE_APPEND);
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "- Original filepath: " . $originalId . "\n", FILE_APPEND);
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "- Cleaned filepath: " . $data->id . "\n", FILE_APPEND);
}

file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Received edit data: " . substr($jsonData, 0, 100) . "...\n", FILE_APPEND);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// Define the quizzes directory path
$quizDir = dirname(dirname(__FILE__)) . '/assessment-list/quizzes';

// Look for the quiz file directly using the filepath ID
$existingQuizFile = null;
file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Searching for quiz file: " . $data->id . ".json\n", FILE_APPEND);
file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Looking in directory: " . $quizDir . "\n", FILE_APPEND);

// For new quizzes, create the file with the ID as filename
$expectedFilePath = $quizDir . '/' . $data->id . '.json';

// Check if it's a new quiz or an existing one
if (file_exists($expectedFilePath)) {
    // Existing quiz - load it and update
    $fileContent = file_get_contents($expectedFilePath);
    $quizContent = json_decode($fileContent);
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Found existing quiz file:\n", FILE_APPEND);
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "- Filepath ID: " . $data->id . "\n", FILE_APPEND);
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "- Quiz UUID: " . (isset($quizContent->uuid) ? $quizContent->uuid : 'not set') . "\n", FILE_APPEND);
    $existingQuizFile = $expectedFilePath;
} else {
    // New quiz - create the quizzes directory if it doesn't exist
    if (!file_exists($quizDir)) {
        mkdir($quizDir, 0777, true);
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Created quizzes directory\n", FILE_APPEND);
    }
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Creating new quiz file: " . $expectedFilePath . "\n", FILE_APPEND);
    $existingQuizFile = $expectedFilePath;
}

// Update the last modified timestamp
$data->lastModified = date('Y-m-d H:i:s');

// Save the JSON data to file
if (file_put_contents($existingQuizFile, json_encode($data, JSON_PRETTY_PRINT))) {
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Quiz saved successfully\n", FILE_APPEND);
    echo json_encode([
        'success' => true,
        'message' => 'Quiz saved successfully',
        'filename' => basename($existingQuizFile)
    ]);
} else {
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Error: Failed to save quiz\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to save quiz'
    ]);
}
?>
