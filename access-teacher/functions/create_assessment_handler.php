<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$title = $_POST['title'] ?? '';
$desc = $_POST['description'] ?? '';
$type = $_POST['type'] ?? 'Practice Quiz';
$is_timed = isset($_POST['is_timed']) ? 1 : 0;
$time_limit = $is_timed ? intval($_POST['time_limit']) : null;

$start_time_raw = $_POST['start_time'] ?? '';
$end_time_raw = $_POST['end_time'] ?? '';
$start_time = !empty($start_time_raw) ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $start_time_raw))) : null;
$end_time = !empty($end_time_raw) ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $end_time_raw))) : null;

$max_attempts = isset($_POST['max_attempts']) ? intval($_POST['max_attempts']) : 1;
$highest_score_only = isset($_POST['highest_score_only']) ? 1 : 0;

$is_published = isset($_POST['is_published']) ? 1 : 0;
$created_by = $_SESSION['user_id'] ?? 0;
$course_id = $_POST['course_id'] ?? 0;

$stmt = $conn->prepare("INSERT INTO assessments (
    course_id, title, description, type, is_timed, time_limit,
    start_time, end_time, max_attempts, highest_score_only,
    is_published, created_by
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param(
    "issssissiiii",
    $course_id,
    $title,
    $desc,
    $type,
    $is_timed,
    $time_limit,
    $start_time,
    $end_time,
    $max_attempts,
    $highest_score_only,
    $is_published,
    $created_by
);

$stmt->execute();
$assessment_id = $stmt->insert_id;

$_SESSION['current_assessment_id'] = $assessment_id;

header("Location: ../add_questions");
exit;
