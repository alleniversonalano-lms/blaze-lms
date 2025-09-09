<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !isset($_SESSION['user_id'])) {
    header("Location: ../modules?error=Invalid+request");
    exit;
}

$stream_id = (int)$_POST['id'];
$user_id = $_SESSION['user_id'];
$content = trim($_POST['content'] ?? '');

// Check if the topic exists and belongs to this module
$stmt = $conn->prepare("SELECT module_id, user_id FROM module_streams WHERE id = ?");
$stmt->bind_param("i", $stream_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../modules?error=Topic+not+found");
    exit;
}

$row = $result->fetch_assoc();
$module_id = $row['module_id'];
$original_author = $row['user_id'];

// Only allow author or full owner (creator)
$is_creator = ($_SESSION['user_id'] == $original_author || $_SESSION['role'] === 'creator');

// SECURITY: if not allowed, deny
if (!$is_creator) {
    header("Location: ../view-module-stream?id=$module_id&error=Unauthorized");
    exit;
}

// Update the content
$update_stmt = $conn->prepare("UPDATE module_streams SET content = ? WHERE id = ?");
$update_stmt->bind_param("si", $content, $stream_id);
$update_stmt->execute();

// Remove selected files
if (!empty($_POST['remove_files'])) {
    $file_ids = $_POST['remove_files'];
    $in = implode(',', array_fill(0, count($file_ids), '?'));
    $types = str_repeat('i', count($file_ids));

    // Get file paths
    $fetch_stmt = $conn->prepare("SELECT id, file_path FROM module_attachments WHERE id IN ($in) AND stream_id = ?");
    $params = array_merge($file_ids, [$stream_id]);
    $fetch_stmt->bind_param($types . 'i', ...$params);
    $fetch_stmt->execute();
    $res = $fetch_stmt->get_result();

    while ($f = $res->fetch_assoc()) {
        if (!filter_var($f['file_path'], FILTER_VALIDATE_URL)) {
            $abs_path = $_SERVER['DOCUMENT_ROOT'] . $f['file_path'];
            if (file_exists($abs_path)) unlink($abs_path);
        }
    }

    // Delete records
    $del_stmt = $conn->prepare("DELETE FROM module_attachments WHERE id IN ($in) AND stream_id = ?");
    $del_stmt->bind_param($types . 'i', ...$params);
    $del_stmt->execute();
}

// Remove selected links
if (!empty($_POST['remove_links'])) {
    $link_ids = $_POST['remove_links'];
    $in = implode(',', array_fill(0, count($link_ids), '?'));
    $types = str_repeat('i', count($link_ids));

    $del_stmt = $conn->prepare("DELETE FROM module_attachments WHERE id IN ($in) AND stream_id = ?");
    $params = array_merge($link_ids, [$stream_id]);
    $del_stmt->bind_param($types . 'i', ...$params);
    $del_stmt->execute();
}

// Handle new file uploads
if (!empty($_FILES['new_files']['name'][0])) {
    $upload_dir = '/uploads/module_files/';
    $abs_dir = $_SERVER['DOCUMENT_ROOT'] . $upload_dir;

    if (!is_dir($abs_dir)) mkdir($abs_dir, 0777, true);

    foreach ($_FILES['new_files']['tmp_name'] as $i => $tmp_name) {
        if ($_FILES['new_files']['error'][$i] === 0) {
            $original_name = basename($_FILES['new_files']['name'][$i]);
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $original_name);
            $destination = $abs_dir . $filename;

            if (move_uploaded_file($tmp_name, $destination)) {
                $path = $upload_dir . $filename;
                $stmt = $conn->prepare("INSERT INTO module_attachments (stream_id, file_path, file_name) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $stream_id, $path, $original_name);
                $stmt->execute();
            }
        }
    }
}

// Handle new links
if (!empty($_POST['new_links'])) {
    foreach ($_POST['new_links'] as $link) {
        $trimmed = trim($link);
        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
            $stmt = $conn->prepare("INSERT INTO module_attachments (stream_id, file_path, file_name) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $stream_id, $trimmed, $trimmed);
            $stmt->execute();
        }
    }
}

header("Location: ../view-module-stream?id=$module_id&error=Topic+updated");
exit;
