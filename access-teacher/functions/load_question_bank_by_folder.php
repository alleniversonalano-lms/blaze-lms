<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

header('Content-Type: application/json');

$folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;

$stmt = $conn->prepare("SELECT * FROM question_bank WHERE folder_id = ?");
$stmt->bind_param("i", $folder_id);
$stmt->execute();
$res = $stmt->get_result();

$questions = [];
while ($row = $res->fetch_assoc()) {
    $questions[] = $row;
}

echo json_encode($questions);
