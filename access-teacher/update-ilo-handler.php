<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$id = intval($_POST['id'] ?? 0);
$ilo_number = intval($_POST['ilo_number'] ?? 0);
$ilo_description = trim($_POST['ilo_description'] ?? '');

if ($id > 0 && $ilo_number && $ilo_description) {
    $stmt = $conn->prepare("UPDATE course_ilos SET ilo_number = ?, ilo_description = ? WHERE id = ?");
    $stmt->bind_param("isi", $ilo_number, $ilo_description, $id);
    $stmt->execute();
}

header("Location: {$_SERVER['HTTP_REFERER']}?error=ILO updated");
exit;
