<?php
session_start();
header('Content-Type: application/json');

// Get data from request
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? '';
$is_published = isset($data['is_published']) ? (int)$data['is_published'] : 0;
$course_id = $_SESSION['ann_course_id'] ?? 0;

if (!$id || !$course_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // Find and update the JSON file
    $quizzes_dir = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/';
    $quiz_file = $quizzes_dir . $id . '.json';

    if (!file_exists($quiz_file)) {
        throw new Exception('Quiz file not found');
    }

    // Read the current quiz data
    $quiz_data = json_decode(file_get_contents($quiz_file), true);
    if (!$quiz_data) {
        throw new Exception('Invalid quiz data');
    }

    // Update the is_published status
    $quiz_data['is_published'] = $is_published;
    
    // Save the updated quiz data back to the file
    if (!file_put_contents($quiz_file, json_encode($quiz_data, JSON_PRETTY_PRINT))) {
        throw new Exception('Failed to update quiz file');
    }

    echo json_encode([
        'success' => true,
        'message' => $is_published ? 'Quiz published successfully' : 'Quiz unpublished successfully'
    ]);

} catch (Exception $e) {
    error_log('Publish Assessment Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}