<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$data = json_decode(file_get_contents('php://input'), true);
$groupId = isset($data['group_id']) ? intval($data['group_id']) : 0;

if ($groupId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid group ID']);
    exit;
}

$userId = $_SESSION['user_id'];

// Check if the user is a member of the group
$checkStmt = $conn->prepare("SELECT 1 FROM chat_group_members WHERE user_id = ? AND group_id = ?");
$checkStmt->bind_param("ii", $userId, $groupId);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Not a member of this group']);
    exit;
}

// Remove the user from the group
$deleteStmt = $conn->prepare("DELETE FROM chat_group_members WHERE user_id = ? AND group_id = ?");
$deleteStmt->bind_param("ii", $userId, $groupId);

if ($deleteStmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to leave group']);
}
