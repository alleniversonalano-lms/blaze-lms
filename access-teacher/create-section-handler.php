<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php'); // adjust path if needed

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section_name = trim($_POST['section_name'] ?? '');
    $course_id = intval($_POST['course_id'] ?? 0);

    if ($section_name !== '' && $course_id > 0) {
        $stmt = $conn->prepare("INSERT INTO sections (name, course_id) VALUES (?, ?)");
        $stmt->bind_param("si", $section_name, $course_id);

        if ($stmt->execute()) {
            // Success: redirect back with a success flag
            header("Location: groups.php?course_id=$course_id&error=Section Created");
            exit;
        } else {
            // Query error
            header("Location: groups.php?course_id=$course_id&error=Failed to Create Section");
            exit;
        }
    } else {
        // Invalid input
        header("Location: groups.php?course_id=$course_id&error=Invalid Input");
        exit;
    }
} else {
    // Invalid method
    header("Location: groups.php?error=Invalid Request");
    exit;
}
