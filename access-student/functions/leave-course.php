<?php
session_start();

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'teacher') {
    header("Location: /login?error=Access+denied");
    exit;
}

$user_id = $_SESSION['user_id'];
require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $course_id = intval($_POST['course_id']);

    // Remove from course_collaborators
    $stmt = $conn->prepare("DELETE FROM course_collaborators WHERE course_id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $course_id, $user_id);

    if ($stmt->execute()) {
        header("Location: ../collaboration?error=Left+the+course");
    } else {
        header("Location: ../collaboration?error=Failed+to+leave+the+course");
    }
    exit;
} else {
    header("Location: ../collaboration?error=Invalid+request");
    exit;
}
