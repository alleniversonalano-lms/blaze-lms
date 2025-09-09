<?php
// functions/edit_folder_name.php
header('Content-Type: application/json');
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['id'], $data['name']) || empty(trim($data['name']))) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$folderId = (int)$data['id'];
$newName = trim($data['name']);

// Update DB
$stmt = $conn->prepare("UPDATE question_folders SET name = ? WHERE id = ?");
$stmt->bind_param("si", $newName, $folderId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database update failed']);
}

$stmt->close();
$conn->close();
