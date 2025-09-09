<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$section_id = intval($_POST['section_id'] ?? 0);
$course_id = intval($_POST['course_id'] ?? 0);
$user_ids = $_POST['user_ids'] ?? [];

if ($section_id <= 0 || $course_id <= 0 || empty($user_ids)) {
    header("Location: groups.php?course_id=$course_id&error=No+users+selected");
    exit;
}

// Start DB transaction
$conn->begin_transaction();

try {
    // Remove previous section assignments
    $remove_stmt = $conn->prepare("DELETE FROM section_members WHERE user_id = ? AND section_id IN (SELECT id FROM sections WHERE course_id = ?)");
    $insert_stmt = $conn->prepare("INSERT INTO section_members (user_id, section_id) VALUES (?, ?)");

    foreach ($user_ids as $uid) {
        $uid = intval($uid);

        // Remove from any existing section in this course
        $remove_stmt->bind_param("ii", $uid, $course_id);
        $remove_stmt->execute();

        // Assign to the new section
        $insert_stmt->bind_param("ii", $uid, $section_id);
        $insert_stmt->execute();
    }

    $conn->commit();
    header("Location: groups.php?course_id=$course_id&error=Section+assignment+updated");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Section assign error: " . $e->getMessage());
    header("Location: groups.php?course_id=$course_id&error=Assignment+failed");
    exit;
}
