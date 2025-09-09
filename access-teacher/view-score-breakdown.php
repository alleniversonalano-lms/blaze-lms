<?php
session_start();

// Get unread message count
require_once $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

// Debug connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
}

try {
    // Check if messages table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'messages'");
    if ($table_check->num_rows == 0) {
        error_log("Messages table does not exist");
        $unread_count = 0;
    } else {
        $unread_query = "SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0";
        $stmt = $conn->prepare($unread_query);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            $unread_count = 0;
        } else {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $unread_count = $row['unread_count'];
            error_log("User ID: " . $user_id . " | Unread count: " . $unread_count);
        }
    }
} catch (Exception $e) {
    error_log("Error getting unread count: " . $e->getMessage());
    $unread_count = 0;
}

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';
$attempt_id = $_GET['attempt_id'] ?? null;

if (!$user_id || strtolower($role) !== 'teacher' || !$attempt_id) {
    die('Access denied or missing attempt ID.');
}

$stmt = $conn->prepare("
    SELECT q.question_text, i.student_answer, i.is_correct
    FROM assessment_attempt_items i
    JOIN questions q ON i.question_id = q.id
    JOIN assessment_attempts a ON i.attempt_id = a.id
    WHERE i.attempt_id = ? AND a.student_id = ?
");
$stmt->bind_param("ii", $attempt_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Score Breakdown</title>
</head>
<body>
    <h2>Score Breakdown</h2>
    <table border="1" cellpadding="10">
        <tr>
            <th>Question</th>
            <th>Your Answer</th>
            <th>Correct</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['question_text']) ?></td>
                <td><?= htmlspecialchars($row['student_answer']) ?></td>
                <td><?= $row['is_correct'] ? '✅' : '❌' ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
