<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) exit("Not logged in");

$partner_user_id = $_POST['userId'] ?? null;
$group_id = $_POST['groupId'] ?? null;

if (!$partner_user_id && !$group_id) {
    exit("No chat target");
}

$now = time();
$cutoff = date('Y-m-d H:i:s', $now - 5); // 5 seconds ago

if ($partner_user_id) {
    // Check if the other user is typing to me
    $stmt = $conn->prepare("
        SELECT id FROM chat_typing
        WHERE sender_id = ? AND receiver_id = ? AND last_typed > ?
    ");
    $stmt->bind_param("iis", $partner_user_id, $user_id, $cutoff);
} else {
    // Check if any other group member is typing in the group
    $stmt = $conn->prepare("
        SELECT id FROM chat_typing
        WHERE group_id = ? AND sender_id != ? AND last_typed > ?
    ");
    $stmt->bind_param("iis", $group_id, $user_id, $cutoff);
}

$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    echo "typing";
} else {
    echo "";
}
