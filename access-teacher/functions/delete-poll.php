<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$poll_id = (int)($_GET['id'] ?? 0);

if ($poll_id > 0) {
    // First, delete related poll_votes
    $stmt = $conn->prepare("DELETE FROM poll_votes WHERE poll_id = ?");
    $stmt->bind_param("i", $poll_id);
    $stmt->execute();
    $stmt->close();

    // Then delete related poll_options
    $stmt = $conn->prepare("DELETE FROM poll_options WHERE poll_id = ?");
    $stmt->bind_param("i", $poll_id);
    $stmt->execute();
    $stmt->close();

    // Finally, delete the poll itself
    $stmt = $conn->prepare("DELETE FROM polls WHERE id = ?");
    $stmt->bind_param("i", $poll_id);
    if ($stmt->execute()) {
        header("Location: ../announcements?error=Poll+deleted+successfully");
        exit;
    } else {
        header("Location: ../announcements?error=Failed+to+delete+poll");
        exit;
    }
}

header("Location: ../announcements?error=Invalid+poll+ID");
exit;
