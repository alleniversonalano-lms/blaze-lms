<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

// Ensure user is logged in and has access
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$ilo_id = intval($_GET['id'] ?? 0);

// Validate
if ($ilo_id <= 0) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Check ownership
$stmt = $conn->prepare("
    SELECT i.id
    FROM course_ilos i
    JOIN courses c ON i.course_id = c.id
    WHERE i.id = ? AND c.user_id = ?
");
$stmt->bind_param('ii', $ilo_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Not found or not owned
    header("Location: {$_SERVER['HTTP_REFERER']}?error=Error deleting ILO");
    exit;
}

// Delete ILO
$delete = $conn->prepare("DELETE FROM course_ilos WHERE id = ?");
$delete->bind_param('i', $ilo_id);
$delete->execute();

// Redirect back (optionally with a snackbar/message param)
header("Location: {$_SERVER['HTTP_REFERER']}?error=ILO deleted");
exit;
