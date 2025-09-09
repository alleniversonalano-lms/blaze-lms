<?php
session_start();
require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'teacher') {
    header("Location: /login?error=Access+denied");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../announcements?error=Invalid+announcement+ID");
    exit;
}

$announcement_id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];

// Get the announcement and its poster
$stmt = $conn->prepare("
    SELECT a.user_id AS poster_id, c.user_id AS creator_id
    FROM announcements a
    JOIN courses c ON a.course_id = c.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $announcement_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    header("Location: ../announcements?error=Announcement+not+found");
    exit;
}

// Allow if user is the course creator or the one who posted the announcement
if ($user_id !== (int)$data['poster_id'] && $user_id !== (int)$data['creator_id']) {
    header("Location: ../announcements?error=You+are+not+authorized+to+delete+this+announcement");
    exit;
}

// Delete related comments first (to avoid FK constraint errors)
$comment_stmt = $conn->prepare("DELETE FROM announcement_comments WHERE announcement_id = ?");
$comment_stmt->bind_param("i", $announcement_id);
$comment_stmt->execute();

// Delete attachments and files from server
$attachment_stmt = $conn->prepare("SELECT file_path FROM announcement_attachments WHERE announcement_id = ?");
$attachment_stmt->bind_param("i", $announcement_id);
$attachment_stmt->execute();
$attachments_result = $attachment_stmt->get_result();

while ($file = $attachments_result->fetch_assoc()) {
    $full_path = $_SERVER['DOCUMENT_ROOT'] . $file['file_path'];
    if (file_exists($full_path)) {
        unlink($full_path);
    }
}

// Remove attachment records
$delete_attachments_stmt = $conn->prepare("DELETE FROM announcement_attachments WHERE announcement_id = ?");
$delete_attachments_stmt->bind_param("i", $announcement_id);
$delete_attachments_stmt->execute();

// Delete the announcement
$stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
$stmt->bind_param("i", $announcement_id);

if ($stmt->execute()) {
    header("Location: ../announcements?error=Thread+deleted+successfully");
} else {
    header("Location: ../announcements?error=Failed+to+delete+thread");
}
exit;
