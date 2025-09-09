<?php
session_start();

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'teacher') {
    die(json_encode(['success' => false, 'error' => 'Access denied']));
}

require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$course_id = $_SESSION['ann_course_id'] ?? 0;

if (!$course_id) {
    die(json_encode(['success' => false, 'error' => 'No course selected']));
}

try {
    $stmt = $conn->prepare("INSERT INTO assessments (
        course_id, title, instructions, quiz_type, time_limit, 
        multiple_attempts, allowed_attempts, score_to_keep,
        shuffle_questions, shuffle_answers, one_question_at_time,
        questions
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "isssiiisiiis",
        $course_id,
        $input['title'],
        $input['instructions'],
        $input['quiz_type'],
        $input['time_limit'],
        $input['multiple_attempts'],
        $input['allowed_attempts'],
        $input['score_to_keep'],
        $input['shuffle_questions'],
        $input['shuffle_answers'],
        $input['one_question_at_time'],
        json_encode($input['questions'])
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
