<?php
session_start();
header('Content-Type: application/json');
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$user_id = $_SESSION['user_id'] ?? 0;
$id = $_GET['id'] ?? 0;

if ($user_id <= 0 || $id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Secure delete query with ownership check
$stmt = $conn->prepare("DELETE FROM question_bank WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Not found or unauthorized']);
}
?>
