<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$groupId = intval($data['group_id'] ?? 0);
$userIds = $data['user_ids'] ?? [];
$adderId = $_SESSION['user_id'] ?? null;

if ($groupId <= 0 || !$adderId || !is_array($userIds) || empty($userIds)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

$addedNames = [];
$existingNames = [];

foreach ($userIds as $uid) {
    $uid = intval($uid);
    if ($uid <= 0) continue;

    // Check if user already in group
    $checkStmt = $conn->prepare("SELECT 1 FROM chat_group_members WHERE group_id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $groupId, $uid);
    $checkStmt->execute();
    $checkStmt->store_result();
    $alreadyInGroup = $checkStmt->num_rows > 0;
    $checkStmt->close();

    // Get user's name once
    $userStmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $userStmt->bind_param("i", $uid);
    $userStmt->execute();
    $result = $userStmt->get_result();
    $fullName = 'Unknown User';
    if ($row = $result->fetch_assoc()) {
        $fullName = "{$row['first_name']} {$row['last_name']}";
    }
    $userStmt->close();

    if ($alreadyInGroup) {
        $existingNames[] = $fullName;
        continue;
    }

    // Add to group
    $insertStmt = $conn->prepare("INSERT INTO chat_group_members (group_id, user_id) VALUES (?, ?)");
    $insertStmt->bind_param("ii", $groupId, $uid);
    $insertStmt->execute();
    $insertStmt->close();

    $addedNames[] = $fullName;
}

// Post group message from adder
if (!empty($addedNames)) {
    $msg = implode(', ', $addedNames) . ' joined the group.';
    $msgStmt = $conn->prepare("INSERT INTO messages (group_id, sender_id, message) VALUES (?, ?, ?)");
    $msgStmt->bind_param("iis", $groupId, $adderId, $msg);
    $msgStmt->execute();
    $msgStmt->close();
}

// Final response
echo json_encode([
    'success' => true,
    'message' => 'Users processed.',
    'added' => $addedNames,
    'already_in_group' => $existingNames
]);
