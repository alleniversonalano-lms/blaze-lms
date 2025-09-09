<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

header('Content-Type: application/json');

// Check authentication
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Read and decode JSON input
$data = json_decode(file_get_contents('php://input'), true);
$group_name = trim($data['name'] ?? '');
$members = $data['members'] ?? [];

if (!$group_name || !is_array($members) || count($members) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Insert group
$stmt = $conn->prepare("INSERT INTO chat_groups (name, created_by) VALUES (?, ?)");
$stmt->bind_param("si", $group_name, $user_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to create group']);
    exit;
}
$group_id = $stmt->insert_id;

// Insert group members (including creator)
$members[] = $user_id;
$members = array_unique(array_map('intval', $members));

$stmt = $conn->prepare("INSERT INTO chat_group_members (group_id, user_id) VALUES (?, ?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare member insertion']);
    exit;
}

foreach ($members as $member_id) {
    $stmt->bind_param("ii", $group_id, $member_id);
    $stmt->execute(); // Optionally check result here
}

echo json_encode(['success' => true, 'group_id' => $group_id]);
