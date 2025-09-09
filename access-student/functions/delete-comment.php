<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$comment_id = $_POST['comment_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;
$ref = $_SERVER['HTTP_REFERER'] ?? '/';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if (!$user_id || !$comment_id) {
    if ($isAjax) {
        json_response(['success' => false, 'error' => 'Invalid access']);
    } else {
        header("Location: $ref?error=Invalid access");
        exit;
    }
}

// Get comment info
$stmt = $conn->prepare("SELECT ac.user_id, a.course_id FROM announcement_comments ac JOIN announcements a ON ac.announcement_id = a.id WHERE ac.id = ?");
$stmt->bind_param("i", $comment_id);
$stmt->execute();
$res = $stmt->get_result();
$comment = $res->fetch_assoc();

if (!$comment) {
    if ($isAjax) {
        json_response(['success' => false, 'error' => 'Comment not found']);
    } else {
        header("Location: $ref?error=Comment not found");
        exit;
    }
}

// Get course creator (column should be user_id not created_by)
$course_stmt = $conn->prepare("SELECT user_id FROM courses WHERE id = ?");
$course_stmt->bind_param("i", $comment['course_id']);
$course_stmt->execute();
$course_res = $course_stmt->get_result();
$course = $course_res->fetch_assoc();

$is_creator = ($course && $course['user_id'] == $user_id);
$is_owner = ($comment['user_id'] == $user_id);

if ($is_creator || $is_owner) {
    $del_stmt = $conn->prepare("DELETE FROM announcement_comments WHERE id = ?");
    $del_stmt->bind_param("i", $comment_id);
    $del_stmt->execute();

    if ($isAjax) {
        json_response(['success' => true]);
    } else {
        header("Location: $ref?error=Comment deleted");
        exit;
    }
} else {
    if ($isAjax) {
        json_response(['success' => false, 'error' => 'Permission denied']);
    } else {
        header("Location: $ref?error=Permission denied");
        exit;
    }
}
