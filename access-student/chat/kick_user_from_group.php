<?php
session_start();
header('Content-Type: application/json');

require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

// Sanitize input
$data = json_decode(file_get_contents('php://input'), true);
$groupId = intval($data['group_id'] ?? 0);
$userToKick = intval($data['user_id'] ?? 0);
$currentUser = $_SESSION['user_id'] ?? 0;

// Validate input
if ($groupId <= 0 || $userToKick <= 0 || $currentUser <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Check if current user is the group creator
$creatorStmt = $conn->prepare("SELECT created_by FROM chat_groups WHERE id = ?");
$creatorStmt->bind_param("i", $groupId);
$creatorStmt->execute();
$creatorResult = $creatorStmt->get_result();
$creatorRow = $creatorResult->fetch_assoc();
$creatorStmt->close();

if (!$creatorRow || intval($creatorRow['created_by']) !== $currentUser) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Kick the user from the group
$delStmt = $conn->prepare("DELETE FROM chat_group_members WHERE group_id = ? AND user_id = ?");
$delStmt->bind_param("ii", $groupId, $userToKick);

if ($delStmt->execute()) {
    $delStmt->close();
    echo json_encode(['success' => true]);
} else {
    $delStmt->close();
    echo json_encode(['success' => false, 'message' => 'Failed to remove user']);
}
