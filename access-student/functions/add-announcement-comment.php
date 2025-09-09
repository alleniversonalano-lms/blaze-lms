<?php
session_start();
require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';
$conn->query("SET time_zone = '+08:00'"); // Set MySQL session timezone

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /404");
    exit;
}

$user_id = $_POST['user_id'];
$announcement_id = $_POST['announcement_id'];
$comment_text = trim($_POST['comment_text']);

if ($comment_text !== '') {
    $stmt = $conn->prepare("
        INSERT INTO announcement_comments 
        (announcement_id, user_id, comment, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iis", $announcement_id, $user_id, $comment_text);

    if ($stmt->execute()) {
        $comment_id = $stmt->insert_id;

        // Fetch comment with created_at and user details
        $user_stmt = $conn->prepare("
            SELECT u.first_name, u.last_name, ac.created_at
            FROM users u 
            JOIN announcement_comments ac ON ac.user_id = u.id
            WHERE u.id = ? AND ac.id = ?
        ");
        $user_stmt->bind_param("ii", $user_id, $comment_id);
        $user_stmt->execute();
        $user = $user_stmt->get_result()->fetch_assoc();

        echo json_encode([
            'success' => true,
            'comment_id' => $comment_id,
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'comment_text' => htmlspecialchars($comment_text),
            'created_at' => date("F j, Y g:i A", strtotime($user['created_at'])),
            'can_delete' => true,
            'user_reaction_type' => null,
            'reaction_totals' => []
        ]);
        exit;
    }
}

echo json_encode(['success' => false]);
