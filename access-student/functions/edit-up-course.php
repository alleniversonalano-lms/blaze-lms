<?php
session_start();

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'teacher') {
    header("Location: /login?error=Access+denied");
    exit;
}

require $_SERVER["DOCUMENT_ROOT"] . '/connect/db.php';

// Ensure form submission
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['course_id'])) {
    header("Location: ../unpublished?error=Invalid+request");
    exit;
}

$course_id = intval($_POST['course_id']);
$course_code = trim($_POST['course_code']);
$course_title = trim($_POST['course_title']);
$description = trim($_POST['description']);
$meeting_link = trim($_POST['meeting_link'] ?? '');

// Validate required fields
if ($course_code === '' || $course_title === '') {
    header("Location: ../edit-course-up-page.php?error=Missing+required+fields");
    exit;
}

// Update course table
$update_stmt = $conn->prepare("UPDATE courses SET course_code = ?, course_title = ?, description = ?, meeting_link = ? WHERE id = ?");
$update_stmt->bind_param("ssssi", $course_code, $course_title, $description, $meeting_link, $course_id);
$update_stmt->execute();
$update_stmt->close();

// Clear existing ILOs
$delete_stmt = $conn->prepare("DELETE FROM course_ilos WHERE course_id = ?");
$delete_stmt->bind_param("i", $course_id);
$delete_stmt->execute();
$delete_stmt->close();


// Re-insert ILOs
$ilo_numbers = $_POST['ilo_number'] ?? [];
$ilo_descriptions = $_POST['ilo_description'] ?? [];

$ilo_stmt = $conn->prepare("INSERT INTO course_ilos (course_id, ilo_number, ilo_description) VALUES (?, ?, ?)");

foreach ($ilo_numbers as $index => $ilo_number) {
    $desc = trim($ilo_descriptions[$index]);
    $num = trim($ilo_number);

    if ($num !== '' && $desc !== '') {
        $ilo_stmt->bind_param("iss", $course_id, $num, $desc);
        $ilo_stmt->execute();
    }
}

$ilo_stmt->close();

// Redirect back with success message
header("Location: ../unpublished?error=Course+updated");
exit;
