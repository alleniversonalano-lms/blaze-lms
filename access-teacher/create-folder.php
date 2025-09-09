<?php
header('Content-Type: application/json');
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

// Use try-catch style error reporting
try {
    $course_id = intval($_POST['course_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $parent_id = $_POST['parent_id'] ?? null;

    if ($course_id <= 0 || $name === '') {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO question_folders (course_id, name, parent_id) VALUES (?, ?, ?)");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param("isi", $course_id, $name, $parent_id);
    $stmt->execute();

    echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
