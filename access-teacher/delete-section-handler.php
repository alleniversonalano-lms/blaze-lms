<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$user_id = $_SESSION['user_id'] ?? 0;
$section_id = intval($_POST['section_id'] ?? 0);
$course_id = intval($_POST['course_id'] ?? 0);

// Optional: Validate that the user is the course creator
$stmt = $conn->prepare("SELECT user_id FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();
$course = $result->fetch_assoc();

if ($course && $course['user_id'] == $user_id) {
    // Delete user assignments to section first (if foreign key constraints exist)
    $conn->prepare("DELETE FROM section_members WHERE section_id = ?")->execute([$section_id]);

    // Then delete the section
    $stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
}

header("Location: {$_SERVER['HTTP_REFERER']}" . (str_contains($_SERVER['HTTP_REFERER'], '?') ? '&' : '?') . "error=Section deleted.");

exit;
