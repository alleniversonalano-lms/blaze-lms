<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['module_id'], $_SESSION['user_id'], $_POST['content'])) {
    header("Location: ../modules?error=Invalid+submission");
    exit;
}

$module_id = (int) $_POST['module_id'];
$user_id = (int) $_SESSION['user_id'];
$content = trim($_POST['content']);
$links_raw = $_POST['links'] ?? '';
$links = is_array($links_raw) ? $links_raw : explode(',', $links_raw);

if ($content === '') {
    header("Location: ../create-topic-page?error=Content+is+required");
    exit;
}

$conn->begin_transaction();

try {
    // Insert topic post
    $stmt = $conn->prepare("INSERT INTO module_streams (module_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $module_id, $user_id, $content);
    $stmt->execute();
    $stream_id = $stmt->insert_id;

    // Handle file uploads
    if (!empty($_FILES['attachments']['name'][0])) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/module_stream/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        foreach ($_FILES['attachments']['name'] as $i => $filename) {
            $tmp = $_FILES['attachments']['tmp_name'][$i];
            $name = basename($filename);
            $safe_name = time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $name);
            $target_path = $upload_dir . $safe_name;

            if (move_uploaded_file($tmp, $target_path)) {
                $db_path = "/uploads/module_stream/" . $safe_name;

                $file_stmt = $conn->prepare("INSERT INTO module_attachments (stream_id, file_name, file_path, uploaded_at) VALUES (?, ?, ?, NOW())");
                $file_stmt->bind_param("iss", $stream_id, $name, $db_path);
                $file_stmt->execute();
            }
        }
    }

    // Handle valid links
    foreach ($links as $link) {
        $link = trim($link);
        if (filter_var($link, FILTER_VALIDATE_URL)) {
            $label = parse_url($link, PHP_URL_HOST);
            $file_stmt = $conn->prepare("INSERT INTO module_attachments (stream_id, file_name, file_path, uploaded_at) VALUES (?, ?, ?, NOW())");
            $file_stmt->bind_param("iss", $stream_id, $label, $link);
            $file_stmt->execute();
        }
    }

    $conn->commit();
    header("Location: ../view-module-stream?id=$module_id&error=Topic+posted+successfully");
    exit;
} catch (Exception $e) {
    $conn->rollback();
    error_log("Create Topic Error: " . $e->getMessage());
    header("Location: ../create-topic-page?error=Failed+to+post+topic");
    exit;
}
