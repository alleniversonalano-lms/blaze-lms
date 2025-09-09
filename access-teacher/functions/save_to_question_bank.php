<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id <= 0 || !$data) {
    echo json_encode(['success' => false]);
    exit;
}

$type = $data['type'];
$text = $data['text'];
$options = json_encode($data['options'] ?? []);
$correct_mcq = json_encode($data['correct_mcq'] ?? []);
$correct_answers = json_encode($data['correct_answers'] ?? []);
$case_sensitive = json_encode($data['case_sensitive'] ?? []);

$stmt = $conn->prepare("INSERT INTO question_bank (user_id, question_type, question_text, options, correct_mcq, correct_answers, case_sensitive) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssss", $user_id, $type, $text, $options, $correct_mcq, $correct_answers, $case_sensitive);
$success = $stmt->execute();

echo json_encode(['success' => $success]);
