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
$newName = isset($data['new_name']) ? trim($data['new_name']) : '';

if ($groupId <= 0 || $newName === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Optional: Verify if user is a member of the group
$user_id = $_SESSION['user_id'];
$verifyStmt = $conn->prepare("SELECT 1 FROM chat_group_members WHERE user_id = ? AND group_id = ?");
$verifyStmt->bind_param("ii", $user_id, $groupId);
$verifyStmt->execute();
$verifyStmt->store_result();

if ($verifyStmt->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Update the group name
$stmt = $conn->prepare("UPDATE chat_groups SET name = ? WHERE id = ?");
$stmt->bind_param("si", $newName, $groupId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
