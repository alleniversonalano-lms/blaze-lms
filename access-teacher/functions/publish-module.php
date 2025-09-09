<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

if (!isset($_GET['id'])) {
    header("Location: ../modules?error=Invalid+module+ID");
    exit;
}

$module_id = $_GET['id'];

// Get module details including course information
$stmt = $conn->prepare("
    SELECT m.id, m.module_number, m.title, m.description, m.course_id,
           c.course_code, c.course_title,
           u.first_name, u.last_name 
    FROM modules m
    JOIN courses c ON m.course_id = c.id
    JOIN users u ON m.created_by = u.id
    WHERE m.id = ?
");
$stmt->bind_param("i", $module_id);
$stmt->execute();
$result = $stmt->get_result();
$module = $result->fetch_assoc();

if (!$module) {
    header("Location: ../modules?error=Module+not+found");
    exit;
}

// Update module to published status
$stmt = $conn->prepare("UPDATE modules SET is_published = 1 WHERE id = ?");
$stmt->bind_param("i", $module_id);

if ($stmt->execute()) {
    // Send email notifications
    require_once $_SERVER['DOCUMENT_ROOT'] . '/email/notification_sender.php';
    
    $emailSubject = "New Module Published in {$module['course_code']}";
    $emailContent = "
        <p>{$module['first_name']} {$module['last_name']} has published a new module:</p>
        <div style='margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px;'>
            <h3 style='margin-top: 0;'>Module {$module['module_number']}: {$module['title']}</h3>
            <p>" . nl2br(htmlspecialchars($module['description'])) . "</p>
        </div>
        <p>Log in to BLAZE to view the complete module content.</p>
    ";

    notifyEnrolledUsers($module['course_id'], $emailSubject, $emailContent, $module['course_code'], $module['course_title']);
    
    header("Location: ../modules?error=Module+published");
} else {
    header("Location: ../modules?error=Failed+to+publish");
}
exit;
