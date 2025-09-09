<?php
// functions/delete_folder.php
header('Content-Type: application/json');
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['id']) || !is_numeric($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid folder ID']);
    exit;
}

$folderId = (int)$data['id'];

// Optional: Check if folder is empty before deleting (recommended for safety)
// $check = $conn->prepare("SELECT COUNT(*) FROM questions WHERE folder_id = ?");
// $check->bind_param("i", $folderId);
// $check->execute();
// $check->bind_result($count);
// $check->fetch();
// $check->close();

// if ($count > 0) {
//     echo json_encode(['success' => false, 'error' => 'Folder is not empty']);
//     exit;
// }

$stmt = $conn->prepare("DELETE FROM question_folders WHERE id = ?");
$stmt->bind_param("i", $folderId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database delete failed']);
}

$stmt->close();
$conn->close();
