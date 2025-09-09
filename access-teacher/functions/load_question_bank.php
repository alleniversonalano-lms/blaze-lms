<?php
session_start();
header('Content-Type: application/json');

require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id <= 0) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id, question_type, question_text, options, correct_mcq, correct_answers, case_sensitive, created_at
        FROM question_bank
        WHERE user_id = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$questions = [];
while ($row = $result->fetch_assoc()) {
    $questions[] = [
        'id' => (int) $row['id'],
        'question_type' => $row['question_type'],
        'question_text' => htmlspecialchars_decode($row['question_text']), // to allow HTML display
        'options' => $row['options'], // still encoded JSON string
        'correct_mcq' => $row['correct_mcq'],
        'correct_answers' => $row['correct_answers'],
        'case_sensitive' => $row['case_sensitive'],
        'created_at' => $row['created_at']
    ];
}

echo json_encode($questions);
