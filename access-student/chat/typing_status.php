<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$sender_id = $_SESSION['user_id'] ?? null;
if (!$sender_id) exit("Not logged in");

$receiver_id = $_POST['userId'] ?? null;
$group_id = $_POST['groupId'] ?? null;
$now = date('Y-m-d H:i:s');

// Only allow either receiver or group typing target
if (!$receiver_id && !$group_id) {
    exit("No recipient provided");
}

// Check if an entry exists
if ($receiver_id) {
    $stmt = $conn->prepare("SELECT id FROM chat_typing WHERE sender_id = ? AND receiver_id = ?");
    $stmt->bind_param("ii", $sender_id, $receiver_id);
} else {
    $stmt = $conn->prepare("SELECT id FROM chat_typing WHERE sender_id = ? AND group_id = ?");
    $stmt->bind_param("ii", $sender_id, $group_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing
    $row = $result->fetch_assoc();
    $id = $row['id'];
    $stmt = $conn->prepare("UPDATE chat_typing SET last_typed = ? WHERE id = ?");
    $stmt->bind_param("si", $now, $id);
    $stmt->execute();
} else {
    // Insert new
    if ($receiver_id) {
        $stmt = $conn->prepare("INSERT INTO chat_typing (sender_id, receiver_id, last_typed) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $sender_id, $receiver_id, $now);
    } else {
        $stmt = $conn->prepare("INSERT INTO chat_typing (sender_id, group_id, last_typed) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $sender_id, $group_id, $now);
    }
    $stmt->execute();
}

echo "ok";
