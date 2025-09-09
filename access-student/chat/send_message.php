<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$message = trim($_POST['message'] ?? '');
$receiver_id = $_POST['userId'] ?? null;
$group_id = $_POST['groupId'] ?? null;

if ($message === '') {
    echo json_encode(['success' => false, 'message' => 'Empty message']);
    exit;
}

// Normalize IDs
$receiver_id = ($receiver_id !== null && $receiver_id !== '' && $receiver_id !== 'null') ? (int)$receiver_id : null;
$group_id    = ($group_id !== null && $group_id !== '' && $group_id !== 'null') ? (int)$group_id : null;

// Enforce only one target
if ($receiver_id && $group_id) {
    echo json_encode(['success' => false, 'message' => 'Specify only one recipient']);
    exit;
}

if ($receiver_id) {
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, sent_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $user_id, $receiver_id, $message);
} elseif ($group_id) {
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, group_id, message, sent_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $user_id, $group_id, $message);
} else {
    echo json_encode(['success' => false, 'message' => 'No valid recipient specified']);
    exit;
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message sent']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send message', 'error' => $stmt->error]);
}
