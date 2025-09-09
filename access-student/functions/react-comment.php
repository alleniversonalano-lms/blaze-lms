<?php
session_start();
require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$comment_id = $_POST['comment_id'] ?? null;
$reaction_type = $_POST['type'] ?? null;

if (!$user_id || !$comment_id || !$reaction_type) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Check if a reaction already exists for this user and comment
$stmt = $conn->prepare("SELECT reaction_type FROM comment_reactions WHERE comment_id = ? AND user_id = ?");
$stmt->bind_param("ii", $comment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result->fetch_assoc();

if ($existing) {
    if ($existing['reaction_type'] === $reaction_type) {
        // Same reaction exists, remove it (toggle off)
        $del = $conn->prepare("DELETE FROM comment_reactions WHERE comment_id = ? AND user_id = ?");
        $del->bind_param("ii", $comment_id, $user_id);
        $del->execute();
    } else {
        // Different reaction exists, update it
        $upd = $conn->prepare("UPDATE comment_reactions SET reaction_type = ? WHERE comment_id = ? AND user_id = ?");
        $upd->bind_param("sii", $reaction_type, $comment_id, $user_id);
        $upd->execute();
    }
} else {
    // No reaction exists, insert it
    $ins = $conn->prepare("INSERT INTO comment_reactions (comment_id, user_id, reaction_type) VALUES (?, ?, ?)");
    $ins->bind_param("iis", $comment_id, $user_id, $reaction_type);
    $ins->execute();
}

// Get updated totals
$totals_stmt = $conn->prepare("
    SELECT reaction_type, COUNT(*) as count 
    FROM comment_reactions 
    WHERE comment_id = ? 
    GROUP BY reaction_type
");
$totals_stmt->bind_param("i", $comment_id);
$totals_stmt->execute();
$totals_result = $totals_stmt->get_result();

$totals = [];
while ($row = $totals_result->fetch_assoc()) {
    $totals[$row['reaction_type']] = $row['count'];
}

echo json_encode([
    'success' => true,
    'totals' => $totals
]);
