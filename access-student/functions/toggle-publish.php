<?php
// functions/toggle-publish.php

session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $stream_id = (int) $_POST['id'];

    // Fetch current publish status
    $stmt = $conn->prepare("SELECT is_published FROM module_streams WHERE id = ?");
    $stmt->bind_param("i", $stream_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $row = $res->fetch_assoc();
        $current = (int) $row['is_published'];
        $new_status = $current === 1 ? 0 : 1;

        // Update publish status
        $update = $conn->prepare("UPDATE module_streams SET is_published = ? WHERE id = ?");
        $update->bind_param("ii", $new_status, $stream_id);
        $update->execute();

        // Optional: redirect back with a status
        header("Location: ../view-module-stream?id=" . $_SESSION['module_id'] . "&error=Topic visibility updated.");
        exit;
    } else {
        // Invalid ID
        header("Location: ../view-module-stream?id=" . $_SESSION['module_id'] . "&error=Topic not found.");
        exit;
    }
} else {
    // Invalid access
    header("Location: ../view-module-stream?id=" . $_SESSION['module_id'] . "&error=Invalid action.");
    exit;
}
