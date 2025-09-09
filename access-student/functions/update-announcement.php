<?php
session_start();
require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

$conn->query("SET time_zone = '+08:00'");

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /404");
    exit;
}

$announcement_id = (int) $_POST['announcement_id'];
$course_id = (int) $_POST['course_id'];
$user_id = (int) $_POST['user_id'];
$description = trim($_POST['description']);

// 1. Delete selected attachments
if (!empty($_POST['delete_attachments']) && is_array($_POST['delete_attachments'])) {
    $delete_ids = array_map('intval', $_POST['delete_attachments']);
    $placeholders = implode(',', array_fill(0, count($delete_ids), '?'));
    $types = str_repeat('i', count($delete_ids));

    // Get stored file paths
    $stmt = $conn->prepare("SELECT file_path FROM announcement_attachments WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$delete_ids);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $file_path = $_SERVER['DOCUMENT_ROOT'] . $row['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path); // delete file from server
        }
    }

    // Delete DB entries
    $stmt = $conn->prepare("DELETE FROM announcement_attachments WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$delete_ids);
    $stmt->execute();
}


// 2. Update announcement text
$stmt = $conn->prepare("UPDATE announcements SET description = ? WHERE id = ?");
$stmt->bind_param("si", $description, $announcement_id);
$stmt->execute();

// 3. Handle file uploads
$upload_dir = '/uploads/announcement_files/';
$full_upload_path = $_SERVER['DOCUMENT_ROOT'] . $upload_dir;

if (!empty($_FILES['attachments']['name'][0])) {
    foreach ($_FILES['attachments']['tmp_name'] as $index => $tmp_name) {
        if ($_FILES['attachments']['error'][$index] === UPLOAD_ERR_OK) {
            $original_name = basename($_FILES['attachments']['name'][$index]);
            $unique_name = time() . '_' . preg_replace('/\s+/', '_', $original_name);
            $target_path = $full_upload_path . $unique_name;

            if (move_uploaded_file($tmp_name, $target_path)) {
                $stored_file_path = $upload_dir . $unique_name; // e.g., /uploads/announcement_files/12345_file.pdf

                $stmt = $conn->prepare("
                    INSERT INTO announcement_attachments 
                        (announcement_id, file_name, file_path) 
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iss", $announcement_id, $original_name, $stored_file_path);
                $stmt->execute();
            }
        }
    }
}



// Redirect back to announcements with snackbar
header("Location: ../announcements?error=Announcement updated successfully.");
exit;
