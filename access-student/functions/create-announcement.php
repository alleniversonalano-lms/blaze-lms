<?php
session_start();
require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /404");
    exit;
}

$course_id = $_POST['course_id'] ?? null;
$user_id = $_POST['user_id'] ?? $_SESSION['user_id'] ?? null;
$description = trim($_POST['description'] ?? '');

if (!$course_id || !$user_id || empty($description)) {
    header("Location: ../create-announcement-page?error=Missing+required+fields");
    exit;
}

// Insert the announcement
$stmt = $conn->prepare("INSERT INTO announcements (course_id, user_id, description) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $course_id, $user_id, $description);

if (!$stmt->execute()) {
    error_log("Failed to insert announcement: " . $stmt->error);
    header("Location: ../create-announcement-page?error=Unable+to+post+announcement");
    exit;
}

$announcement_id = $stmt->insert_id;

// Ensure upload directory exists
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/announcement_files/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle uploaded files
if (!empty($_FILES['attachments']['name'][0])) {
    $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'rar'];
    $max_file_size = 25 * 1024 * 1024; // 25MB

    foreach ($_FILES['attachments']['name'] as $key => $name) {
        $tmp_name = $_FILES['attachments']['tmp_name'][$key];
        $error = $_FILES['attachments']['error'][$key];
        $size = $_FILES['attachments']['size'][$key];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($error === UPLOAD_ERR_OK && in_array($ext, $allowed_exts) && $size <= $max_file_size) {
            $safe_name = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', pathinfo($name, PATHINFO_FILENAME));
            $new_filename = 'ann_' . $announcement_id . '_' . time() . '_' . uniqid() . '.' . $ext;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($tmp_name, $destination)) {
                $relative_path = '/uploads/announcement_files/' . $new_filename;

                $file_stmt = $conn->prepare("INSERT INTO announcement_attachments (announcement_id, file_path, file_name) VALUES (?, ?, ?)");
                $file_stmt->bind_param("iss", $announcement_id, $relative_path, $name);
                $file_stmt->execute();
            }
        }
    }
}

header("Location: ../announcements?course_id=$course_id&error=Announcement+posted+successfully");
exit;
