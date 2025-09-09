<?php
session_start();
require_once('../connect/db.php');
include('functions/verify-session.php');

// Ensure request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid JSON data']));
}

// Validate required fields
$required = ['quizId', 'studentId', 'attemptNumber', 'answers', 'results', 'timeUsed', 'submittedAt'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        exit(json_encode(['error' => "Missing required field: $field"]));
    }
}

// Get user ID from session
$userId = $_SESSION['user_Id'];

try {
    // Create attempts directory if it doesn't exist
    $attemptsDir = "../../assessment-list/quizzes/attempts";
    if (!file_exists($attemptsDir)) {
        mkdir($attemptsDir, 0777, true);
    }

    // Load existing attempts or create new array
    $attemptsFile = "{$attemptsDir}/{$data['quizId']}_{$userId}.json";
    $attempts = [];
    if (file_exists($attemptsFile)) {
        $attempts = json_decode(file_get_contents($attemptsFile), true);
    }

    // Add new attempt
    $newAttempt = [
        'attemptNumber' => $data['attemptNumber'],
        'score' => $data['results']['score'],
        'maxScore' => $data['results']['maxScore'],
        'correctQuestions' => $data['results']['correctQuestions'],
        'totalQuestions' => $data['results']['totalQuestions'],
        'timeUsed' => $data['timeUsed'],
        'submittedAt' => $data['submittedAt'],
        'autoSubmit' => $data['autoSubmit'],
        'answers' => $data['answers']
    ];

    $attempts[] = $newAttempt;

    // Save attempts to file
    if (file_put_contents($attemptsFile, json_encode($attempts, JSON_PRETTY_PRINT)) === false) {
        throw new Exception('Failed to write attempts file');
    }

    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Quiz results saved successfully',
        'attemptNumber' => count($attempts)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to save quiz results',
        'details' => $e->getMessage()
    ]);
}
