<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) exit("Not logged in");

$partner_id = $_POST['userId'] ?? null;
$group_id = $_POST['groupId'] ?? null;

// Clean values
$partner_id = ($partner_id !== 'null' && $partner_id !== '') ? (int)$partner_id : null;
$group_id = ($group_id !== 'null' && $group_id !== '') ? (int)$group_id : null;

function formatDateHeader($date) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($date === $today) return 'Today';
    if ($date === $yesterday) return 'Yesterday';

    return date('D, M j', strtotime($date));
}

function formatTime($datetime) {
    return date('g:i A', strtotime($datetime));
}

$currentDate = '';

if ($partner_id) {
    // Mark private messages as read
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt->bind_param("ii", $partner_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // Fetch private messages
    $stmt = $conn->prepare("
        SELECT m.*, u.username, u.profile_pic
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY sent_at ASC
    ");
    $stmt->bind_param("iiii", $user_id, $partner_id, $partner_id, $user_id);
} elseif ($group_id) {
    // Check group membership
    $check = $conn->prepare("SELECT 1 FROM chat_group_members WHERE group_id = ? AND user_id = ?");
    $check->bind_param("ii", $group_id, $user_id);
    $check->execute();
    $is_member = $check->get_result()->fetch_row();
    $check->close();

    if (!$is_member) {
        echo "You are not a member of this group.";
        exit;
    }

    // Mark group messages as read
    $read_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE group_id = ? AND sender_id != ? AND is_read = 0");
    $read_stmt->bind_param("ii", $group_id, $user_id);
    $read_stmt->execute();
    $read_stmt->close();

    // Fetch group messages
    $stmt = $conn->prepare("
        SELECT m.*, u.username, u.profile_pic
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.group_id = ?
        ORDER BY m.sent_at ASC
    ");
    $stmt->bind_param("i", $group_id);
} else {
    echo "No chat selected.";
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

echo '<style>
.message {
    position: relative;
    display: inline-block;
    max-width: 70%;
    padding: 10px;
    border-radius: 10px;
    word-wrap: break-word;
}
.message.mine {
    background: #dcf8c6;
    border-bottom-right-radius: 0;
}
.message.mine::after {
    content: "";
    position: absolute;
    right: -10px;
    bottom: 0;
    width: 0;
    height: 0;
    border-left: 10px solid #dcf8c6;
    border-top: 10px solid transparent;
}
.message.other {
    background: #eee;
    border-bottom-left-radius: 0;
}
.message.other::after {
    content: "";
    position: absolute;
    left: -10px;
    bottom: 0;
    width: 0;
    height: 0;
    border-right: 10px solid #eee;
    border-top: 10px solid transparent;
}
.message-time {
    font-size: 10px;
    color: #555;
    text-align: right;
    margin-top: 5px;
}
</style>';

while ($row = $result->fetch_assoc()) {
    $isMine = $row['sender_id'] == $user_id;
    $msgDate = date('Y-m-d', strtotime($row['sent_at']));
    $msgTime = formatTime($row['sent_at']);

    if ($msgDate !== $currentDate) {
        $currentDate = $msgDate;
        echo '<div style="text-align:center;color:#888;font-size:13px;margin:10px 0;">' . formatDateHeader($msgDate) . '</div>';
    }

    $class = $isMine ? 'mine' : 'other';
    echo '<div style="margin:5px 0;text-align:' . ($isMine ? 'right' : 'left') . '">';
    echo '<div class="message ' . $class . '">';
    
    if (!$isMine && $group_id) {
        echo '<strong>' . htmlspecialchars($row['username']) . '</strong><br>';
    }

    echo htmlspecialchars($row['message']);
    echo '<div class="message-time">' . $msgTime . '</div>';
    echo '</div></div>';
}
?>
