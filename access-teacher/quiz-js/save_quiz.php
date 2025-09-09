<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Get POST data
    $input = file_get_contents('php://input');
    if (!$input) {
        throw new Exception('No input received');
    }

    $quizData = json_decode($input, true);
    if (!$quizData) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    // Get course_id from session
    $course_id = $_SESSION['ann_course_id'] ?? 0;
    if (!$course_id) {
        throw new Exception('No course selected');
    }

    // Generate unique quiz ID for metadata
    $quizId = uniqid('quiz_', true);
    
    // Add required fields to quiz data
    $quizData['courseId'] = (int)$course_id;  // Ensure it's stored as integer
    $quizData['createdAt'] = date('c');  // ISO 8601 format
    $quizData['lastModified'] = date('c');
    $quizData['is_published'] = 0;  // Default to unpublished

    // Create quizzes directory if it doesn't exist
    $quizzes_dir = $_SERVER['DOCUMENT_ROOT'] . '/access-teacher/quizzes/';
    if (!is_dir($quizzes_dir)) {
        mkdir($quizzes_dir, 0777, true);
    }

    // Save the quiz as a JSON file
    $filename = $quizId . '.json';
    $filepath = $quizzes_dir . $filename;
    
    if (file_put_contents($filepath, json_encode($quizData, JSON_PRETTY_PRINT))) {
        echo json_encode([
            'success' => true,
            'quiz_id' => $quizId,
            'message' => 'Quiz saved successfully'
        ]);
    } else {
        throw new Exception('Failed to save quiz file');
    }

} catch (Exception $e) {
    error_log('Quiz Save Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

} catch (Exception $e) {
    error_log('Quiz Save Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}