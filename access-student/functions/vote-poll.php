<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$user_id  = $_SESSION['user_id'] ?? 0;
$poll_id  = (int)($_POST['poll_id'] ?? 0);
$option_id = (int)($_POST['vote_option'] ?? 0);

if ($user_id <= 0 || $poll_id <= 0 || $option_id <= 0) {
    header('Location: ../announcements.php?error=Invalid+vote+submission');
    exit;
}

// Check if user has already voted in this poll
$check_stmt = $conn->prepare("
    SELECT 1 FROM poll_votes 
    WHERE user_id = ? AND poll_id = ? 
    LIMIT 1
");
$check_stmt->bind_param("ii", $user_id, $poll_id);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows === 0) {
    // Insert vote
    $insert_stmt = $conn->prepare("INSERT INTO poll_votes (user_id, poll_id, option_id) VALUES (?, ?, ?)");
    $insert_stmt->bind_param("iii", $user_id, $poll_id, $option_id);
    $insert_stmt->execute();
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
?>

