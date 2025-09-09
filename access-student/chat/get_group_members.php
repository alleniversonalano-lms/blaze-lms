<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$userId = $_SESSION['user_id'] ?? 0;
$groupId = $_POST['group_id'] ?? 0;

if (!$groupId || !$userId) {
    echo json_encode(['members' => []]);
    exit;
}

// Get group creator
$creatorQuery = $conn->prepare("SELECT created_by FROM chat_groups WHERE id = ?");
$creatorQuery->bind_param("i", $groupId);
$creatorQuery->execute();
$creatorResult = $creatorQuery->get_result();
$creatorId = ($row = $creatorResult->fetch_assoc()) ? $row['created_by'] : 0;

// Fetch members
$stmt = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.username
    FROM chat_group_members cgm
    JOIN users u ON cgm.user_id = u.id
    WHERE cgm.group_id = ?
    ORDER BY u.first_name ASC
");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$result = $stmt->get_result();

$members = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = "{$row['first_name']} {$row['last_name']}";
    $row['can_kick'] = $creatorId == $userId && $row['id'] != $userId;
    $members[] = $row;
}

echo json_encode(['members' => $members]);
