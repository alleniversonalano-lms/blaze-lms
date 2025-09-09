<?php
session_start();
require $_SERVER["DOCUMENT_ROOT"] . '/connect/db.php';

// Ensure user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: /login?error=Access+denied");
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate course ID from POST
if (!isset($_POST['course_id']) || !is_numeric($_POST['course_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid course ID.']);
    exit;
}

$course_id = (int) $_POST['course_id'];

// Only allow the teacher who owns the course to unpublish it
$stmt = $conn->prepare("UPDATE courses SET published = 1 WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $course_id, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    header("Location: ../unpublished?error=Course+restored+successfully");
    exit;
} else {
    header("Location: ../unpublished?error=Unable+to+restore+course+or+unauthorized+access");
    exit;
}


$conn->close();
