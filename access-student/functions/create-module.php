<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

// Ensure form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../create-module-page?error=Invalid+request");
    exit;
}

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: ../create-module-page?error=Unauthorized");
    exit;
}

$course_id = $_POST['course_id'];
$module_number = trim($_POST['module_number']);
$title = trim($_POST['title']);
$description = trim($_POST['description']);
$scheduled_at_raw = trim($_POST['scheduled_at']);
$created_by = $_SESSION['user_id'];

// Convert scheduled_at to null if empty
$scheduled_at = $scheduled_at_raw === '' ? null : date('Y-m-d H:i:s', strtotime($scheduled_at_raw));

// Validate datetime if provided
if ($scheduled_at === false && $scheduled_at_raw !== '') {
    header("Location: ../create-module-page?error=Invalid+schedule+format");
    exit;
}

try {
    $stmt = $conn->prepare("
        INSERT INTO modules (course_id, module_number, title, description, scheduled_at, is_published, created_at, created_by)
        VALUES (?, ?, ?, ?, ?, 0, NOW(), ?)
    ");
    $stmt->bind_param(
        "issssi",
        $course_id,
        $module_number,
        $title,
        $description,
        $scheduled_at,
        $created_by
    );

    if ($stmt->execute()) {
        header("Location: ../modules?error=Module+created+successfully");
        exit;
    } else {
        throw new Exception("Database error: " . $stmt->error);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    header("Location: ../create-module-page?error=Error+creating+module");
    exit;
}
