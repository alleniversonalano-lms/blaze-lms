<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$notifications = [];

// --- Unread private messages ---
$stmt = $conn->prepare("
    SELECT sender_id, COUNT(*) AS unread_count
    FROM messages
    WHERE receiver_id = ? AND is_read = 0 AND group_id IS NULL
    GROUP BY sender_id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $notifications[(string)$row['sender_id']] = (int)$row['unread_count'];
}
$stmt->close();

// --- Unread group messages ---
// Assumes messages have group_id set for group chats
// and current user is not the sender
$stmt = $conn->prepare("
    SELECT m.group_id, COUNT(*) AS unread_count
    FROM messages m
    INNER JOIN chat_group_members gm ON m.group_id = gm.group_id
    WHERE gm.user_id = ? AND m.sender_id != ? 
      AND m.group_id IS NOT NULL AND m.is_read = 0
    GROUP BY m.group_id
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $groupKey = "g-" . $row['group_id'];
    $notifications[$groupKey] = (int)$row['unread_count'];
}
$stmt->close();

echo json_encode($notifications);
