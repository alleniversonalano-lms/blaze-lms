<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

if (!isset($_GET['id'])) {
    header("Location: ../modules?error=Invalid+module+ID");
    exit;
}

$module_id = $_GET['id'];

// Optional: check ownership or permission if needed

$stmt = $conn->prepare("UPDATE modules SET is_published = 1 WHERE id = ?");
$stmt->bind_param("i", $module_id);

if ($stmt->execute()) {
    header("Location: ../modules?error=Module+published");
} else {
    header("Location: ../modules?error=Failed+to+publish");
}
exit;
