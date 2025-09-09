<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: people?error=Invalid request method.");
    exit;
}

require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$course_id = (int)($data['course_id'] ?? 0);
$lecturers = $data['lecturers'] ?? [];
$students = $data['students'] ?? [];

if (!$course_id || (!is_array($lecturers) && !is_array($students))) {
    header("Location: people?error=Invalid input data.");
    exit;
}

// Get creator ID to protect owner
$creator_stmt = $conn->prepare("SELECT user_id FROM courses WHERE id = ?");
$creator_stmt->bind_param("i", $course_id);
$creator_stmt->execute();
$creator_result = $creator_stmt->get_result();
$creator_id = $creator_result->fetch_assoc()['user_id'] ?? 0;

// Remove lecturers (exclude creator)
if (!empty($lecturers)) {
    $lecturers = array_filter($lecturers, fn($uid) => $uid != $creator_id);
    if (!empty($lecturers)) {
        $placeholders = implode(',', array_fill(0, count($lecturers), '?'));
        $types = str_repeat('i', count($lecturers) + 1);
        $params = array_merge([$course_id], $lecturers);

        $stmt = $conn->prepare("DELETE FROM course_collaborators WHERE course_id = ? AND teacher_id IN ($placeholders)");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }
}

// Remove students
if (!empty($students)) {
    $placeholders = implode(',', array_fill(0, count($students), '?'));
    $types = str_repeat('i', count($students) + 1);
    $params = array_merge([$course_id], $students);

    $stmt = $conn->prepare("DELETE FROM course_enrollments WHERE course_id = ? AND student_id IN ($placeholders)");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
}

header("Location: people?error=Selected users have been unenrolled.");
exit;
