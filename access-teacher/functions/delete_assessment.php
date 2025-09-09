<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$quiz_id = $_GET['id'] ?? '';
if (!$quiz_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No quiz ID provided']);
    exit;
}

try {
    // Look for the quiz file in the shared assessments directory
    $quizzes_dir = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/';
    $quiz_file = $quizzes_dir . $quiz_id . '.json';
    
    if (file_exists($quiz_file)) {
        if (unlink($quiz_file)) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Failed to delete quiz file');
        }
    } else {
        throw new Exception('Quiz file not found');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}