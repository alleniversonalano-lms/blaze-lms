<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$results = [];

// Search Users
$user_stmt = $conn->prepare("
    SELECT id, username, first_name, last_name, profile_pic 
    FROM users 
    WHERE id != ? AND (
        username LIKE CONCAT('%', ?, '%') 
        OR first_name LIKE CONCAT('%', ?, '%') 
        OR last_name LIKE CONCAT('%', ?, '%') 
        OR CONCAT_WS(' ', first_name, last_name) LIKE CONCAT('%', ?, '%')
    )
    ORDER BY username ASC
    LIMIT 10
");
$user_stmt->bind_param("issss", $user_id, $q, $q, $q, $q);
$user_stmt->execute();
$user_res = $user_stmt->get_result();

while ($row = $user_res->fetch_assoc()) {
    $results[] = [
        'type' => 'user',
        'id' => $row['id'],
        'username' => $row['username'],
        'first_name' => $row['first_name'],
        'last_name' => $row['last_name'],
        'profile_pic' => $row['profile_pic'] ?? 'default.png'
    ];
}
$user_stmt->close();

// Search Groups (only user's groups)
$group_stmt = $conn->prepare("
    SELECT cg.id, cg.name 
    FROM chat_groups cg
    JOIN chat_group_members cgm ON cg.id = cgm.group_id
    WHERE cgm.user_id = ? AND cg.name LIKE CONCAT('%', ?, '%')
    ORDER BY cg.name ASC
    LIMIT 10
");
$group_stmt->bind_param("is", $user_id, $q);
$group_stmt->execute();
$group_res = $group_stmt->get_result();

while ($row = $group_res->fetch_assoc()) {
    $results[] = [
        'type' => 'group',
        'id' => $row['id'],
        'name' => $row['name']
    ];
}
$group_stmt->close();

echo json_encode($results);
