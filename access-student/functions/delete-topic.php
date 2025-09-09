<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !isset($_SESSION['user_id'])) {
    header("Location: ../modules?error=Invalid+request");
    exit;
}

$stream_id = (int) $_POST['id'];
$current_user_id = $_SESSION['user_id'];

// Fetch module_id, stream owner, and module creator
$mod_stmt = $conn->prepare("
    SELECT ms.module_id, ms.user_id AS stream_owner, c.created_by AS module_creator
    FROM module_streams ms
    JOIN modules c ON ms.module_id = c.id
    WHERE ms.id = ?
");
$mod_stmt->bind_param("i", $stream_id);
$mod_stmt->execute();
$mod_res = $mod_stmt->get_result();

if ($mod_res->num_rows === 0) {
    header("Location: ../modules?error=Topic+not+found");
    exit;
}

$row = $mod_res->fetch_assoc();
$module_id = $row['module_id'];
$stream_owner = $row['stream_owner'];
$module_creator = $row['module_creator'];

// Check authorization
if ($current_user_id !== $module_creator && $current_user_id !== $stream_owner) {
    header("Location: ../view-module-stream?id=$module_id&error=You+can+only+delete+your+own+posts");
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Delete physical files
    $att_stmt = $conn->prepare("SELECT file_path FROM module_attachments WHERE stream_id = ?");
    $att_stmt->bind_param("i", $stream_id);
    $att_stmt->execute();
    $att_result = $att_stmt->get_result();

    while ($att = $att_result->fetch_assoc()) {
        $file_path = $att['file_path'];
        if (!filter_var($file_path, FILTER_VALIDATE_URL)) {
            $abs_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
            if (file_exists($abs_path)) {
                unlink($abs_path);
            }
        }
    }

    // Delete attachments
    $del_att_stmt = $conn->prepare("DELETE FROM module_attachments WHERE stream_id = ?");
    $del_att_stmt->bind_param("i", $stream_id);
    $del_att_stmt->execute();

    // Delete stream post
    $del_stream_stmt = $conn->prepare("DELETE FROM module_streams WHERE id = ?");
    $del_stream_stmt->bind_param("i", $stream_id);
    $del_stream_stmt->execute();

    $conn->commit();
    header("Location: ../view-module-stream?id=$module_id&error=Topic+deleted+successfully");
    exit;
} catch (Exception $e) {
    $conn->rollback();
    error_log("Delete Topic Error: " . $e->getMessage());
    header("Location: ../view-module-stream?id=$module_id&error=Failed+to+delete+topic");
    exit;
}
