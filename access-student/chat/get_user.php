<?php
session_start();
header('Content-Type: application/json');
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$userId = $_SESSION['user_id'] ?? 0;
$q = trim($_GET['q'] ?? '');
$groupId = intval($_GET['group_id'] ?? 0);

if ($userId <= 0 || strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$like = "%{$q}%";

// Optional: fetch user IDs already in the group
$joinedIds = [];
if ($groupId > 0) {
    $joinedStmt = $conn->prepare("SELECT user_id FROM chat_group_members WHERE group_id = ?");
    $joinedStmt->bind_param("i", $groupId);
    $joinedStmt->execute();
    $joinedResult = $joinedStmt->get_result();
    while ($row = $joinedResult->fetch_assoc()) {
        $joinedIds[] = intval($row['user_id']);
    }
    $joinedStmt->close();
}

// Search users
$stmt = $conn->prepare("
    SELECT id, first_name, last_name, username 
    FROM users 
    WHERE (
        first_name LIKE ? 
        OR last_name LIKE ? 
        OR username LIKE ? 
        OR CONCAT(first_name, ' ', last_name) LIKE ?
    ) AND id != ?
    ORDER BY first_name ASC
");
$stmt->bind_param("ssssi", $like, $like, $like, $like, $userId);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $id = intval($row['id']);
    $users[] = [
        'id' => $id,
        'first_name' => $row['first_name'],
        'last_name' => $row['last_name'],
        'username' => $row['username'],
        'full_name' => "{$row['first_name']} {$row['last_name']}",
        'joined' => in_array($id, $joinedIds)
    ];
}

echo json_encode($users);
