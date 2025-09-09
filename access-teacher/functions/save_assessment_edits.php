<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Get JSON data from request body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}

$assessment_id = (int)$data['assessment_id'];
$is_saved_quiz = (bool)$data['is_saved_quiz'];
$user_id = $_SESSION['user_id'];

// Verify ownership/collaboration rights
$stmt = $conn->prepare("
    SELECT c.user_id as owner_id
    FROM assessments a
    JOIN courses c ON a.course_id = c.id
    WHERE a.id = ? AND (c.user_id = ? OR EXISTS(
        SELECT 1 FROM course_collaborators 
        WHERE course_id = a.course_id AND teacher_id = ?
    ))
");
$stmt->bind_param("iii", $assessment_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Assessment not found or access denied']);
    exit;
}

try {
    $conn->begin_transaction();

    // Prepare the base assessment update
    $updateStmt = $conn->prepare("
        UPDATE assessments 
        SET title = ?,
            instructions = ?,
            time_limit = ?,
            multiple_attempts = ?,
            allowed_attempts = ?
        WHERE id = ?
    ");

    $title = $data['title'];
    $instructions = $data['instructions'] ?? '';
    $time_limit = $data['time_limit'] ? (int)$data['time_limit'] : null;
    $multiple_attempts = isset($data['multiple_attempts']) ? 1 : 0;
    $allowed_attempts = $multiple_attempts ? ((int)$data['allowed_attempts'] ?: 1) : 1;

    $updateStmt->bind_param("ssiiii", 
        $title,
        $instructions,
        $time_limit,
        $multiple_attempts,
        $allowed_attempts,
        $assessment_id
    );
    $updateStmt->execute();

    // If it's a saved quiz, update the quiz_data
    if ($is_saved_quiz && isset($data['questions'])) {
        // Reorganize questions data
        $quiz_data = [
            'questions' => array_values(array_map(function($q) {
                return [
                    'text' => $q['text'],
                    'options' => array_values($q['options']),
                    'correct' => (int)$q['correct']
                ];
            }, $data['questions']))
        ];

        $quiz_json = json_encode($quiz_data);
        
        $quizUpdateStmt = $conn->prepare("
            UPDATE assessments 
            SET quiz_data = ?
            WHERE id = ?
        ");
        $quizUpdateStmt->bind_param("si", $quiz_json, $assessment_id);
        $quizUpdateStmt->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update assessment: ' . $e->getMessage()]);
}
?>
