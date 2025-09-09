<?php
session_start();
require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login?error=Access+denied");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $course_id = intval($_POST['course_id']);

    // Check if the user is the course creator
    $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $course_id, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        header("Location: ../unpublished?error=Unauthorized+deletion+attempt");
        exit;
    }

    // Delete course (consider adding ON DELETE CASCADE to related tables for cleanup)
    $delete = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $delete->bind_param("i", $course_id);

    if ($delete->execute()) {
        header("Location: ../unpublished?error=Course+deleted+successfully");
    } else {
        header("Location: ../unpublished?error=Failed+to+delete+course");
    }
    exit;
}

header("Location: ../unpublished?error=Invalid+request");
exit;
