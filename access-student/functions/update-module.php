<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../modules?error=Invalid+request");
    exit;
}

if (!isset($_POST['module_id'], $_POST['course_id'], $_SESSION['user_id'])) {
    header("Location: ../modules?error=Missing+data");
    exit;
}

$module_id = (int)$_POST['module_id'];
$course_id = (int)$_POST['course_id'];
$module_number = trim($_POST['module_number']);
$title = trim($_POST['title']);
$description = trim($_POST['description']);
$scheduled_at_raw = trim($_POST['scheduled_at']);
$scheduled_at = $scheduled_at_raw === '' ? null : date('Y-m-d H:i:s', strtotime($scheduled_at_raw));

if ($scheduled_at === false && $scheduled_at_raw !== '') {
    header("Location: ../edit-module-page?error=Invalid+date+format");
    exit;
}

// Check permission: only course creator or module creator can update
$stmt = $conn->prepare("SELECT m.created_by, c.user_id AS course_creator FROM modules m JOIN courses c ON m.course_id = c.id WHERE m.id = ?");
$stmt->bind_param("i", $module_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../modules?error=Module+not+found");
    exit;
}

$row = $result->fetch_assoc();
$course_creator = $row['course_creator'];
$module_creator = $row['created_by'];
$current_user = $_SESSION['user_id'];

if ($current_user != $course_creator && $current_user != $module_creator) {
    header("Location: ../modules?error=Not+authorized+to+edit+this+module");
    exit;
}

$update_stmt = $conn->prepare("UPDATE modules SET module_number = ?, title = ?, description = ?, scheduled_at = ? WHERE id = ?");
$update_stmt->bind_param("ssssi", $module_number, $title, $description, $scheduled_at, $module_id);

if ($update_stmt->execute()) {
    header("Location: ../modules?error=Module+updated+successfully");
    exit;
} else {
    header("Location: ../modules?error=Failed+to+update+module");
    exit;
}
