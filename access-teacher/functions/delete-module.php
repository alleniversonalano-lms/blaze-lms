<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

if (!isset($_GET['id']) || !isset($_SESSION['user_id'])) {
    header("Location: ../modules?error=Unauthorized");
    exit;
}

$module_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get course creator + module creator
$stmt = $conn->prepare("
    SELECT m.created_by, c.user_id AS course_creator
    FROM modules m
    JOIN courses c ON m.course_id = c.id
    WHERE m.id = ?
");
$stmt->bind_param("i", $module_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../modules?error=Module+not+found");
    exit;
}

$row = $result->fetch_assoc();
$module_creator = $row['created_by'];
$course_creator = $row['course_creator'];

// Allow if current user is course creator OR module creator
if ($user_id != $course_creator && $user_id != $module_creator) {
    header("Location: ../modules?error=You+are+not+authorized+to+delete+this+module");
    exit;
}

// Proceed with deletion
$delete_stmt = $conn->prepare("DELETE FROM modules WHERE id = ?");
$delete_stmt->bind_param("i", $module_id);

if ($delete_stmt->execute()) {
    header("Location: ../modules?error=Module+deleted+successfully");
} else {
    header("Location: ../modules?error=Failed+to+delete+module");
}
exit;
