<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$user_id = $_SESSION['user_id'] ?? null;
$course_id = intval($_POST['course_id'] ?? 0);
$ilo_number = intval($_POST['ilo_number'] ?? 0);
$ilo_description = trim($_POST['ilo_description'] ?? '');

if (!$user_id || !$course_id || !$ilo_number || empty($ilo_description)) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Optional: validate course ownership
$stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $course_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit('Unauthorized or course not found.');
}

$stmt = $conn->prepare("INSERT INTO course_ilos (course_id, ilo_number, ilo_description) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $course_id, $ilo_number, $ilo_description);
$stmt->execute();

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
