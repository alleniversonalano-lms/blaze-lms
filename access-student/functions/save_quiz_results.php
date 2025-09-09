<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Get the JSON data from the POST request
$json_data = file_get_contents('php://input');
$quiz_data = json_decode($json_data, true);

if (!$quiz_data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data format']);
    exit;
}

// Create results directory if it doesn't exist
$results_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/quiz_results';
if (!file_exists($results_dir)) {
    mkdir($results_dir, 0777, true);
}

// Create a filename using quiz ID, student ID, and timestamp
$filename = sprintf(
    'quiz_%s_student_%s_attempt_%d_%s.json',
    $quiz_data['quizId'],
    $_SESSION['user_id'],
    $quiz_data['attemptNumber'],
    date('Y-m-d_H-i-s')
);

$filepath = $results_dir . '/' . $filename;

// Add student info to the results
$quiz_data['studentInfo'] = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'firstName' => $_SESSION['first_name'],
    'lastName' => $_SESSION['last_name'],
    'email' => $_SESSION['email_address']
];

// Save the results to a JSON file
if (file_put_contents($filepath, json_encode($quiz_data, JSON_PRETTY_PRINT))) {
    echo json_encode([
        'success' => true,
        'message' => 'Quiz results saved successfully',
        'filename' => $filename
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save quiz results'
    ]);
}
