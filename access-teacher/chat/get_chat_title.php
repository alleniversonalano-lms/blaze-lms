<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) exit;

// Get POST values
$group_id = $_POST['groupId'] ?? null;
$partner_id = $_POST['userId'] ?? null;

// Convert string "null" to actual null
if ($group_id === 'null') $group_id = null;
if ($partner_id === 'null') $partner_id = null;

if ($group_id !== null) {
    $stmt = $conn->prepare("SELECT name FROM chat_groups WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $stmt->bind_result($name);
    if ($stmt->fetch()) {
        echo htmlspecialchars($name);
    } else {
        echo "Unknown Group";
    }
    $stmt->close();
} elseif ($partner_id !== null) {
    $stmt = $conn->prepare("SELECT first_name, last_name, username FROM users WHERE id = ?");
    $stmt->bind_param("i", $partner_id);
    $stmt->execute();
    $stmt->bind_result($fname, $lname, $uname);
    if ($stmt->fetch()) {
        echo htmlspecialchars("$fname $lname @$uname");
    } else {
        echo "Unknown User";
    }
    $stmt->close();
} else {
    echo "No chat selected";
}
