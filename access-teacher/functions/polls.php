<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$course_id = $_POST['course_id'];
$question = trim($_POST['question']);
$options = array_filter($_POST['options'], fn($opt) => trim($opt) !== '');

if (!$question || count($options) < 2) {
    header("Location: ../create-poll.php?course_id=$course_id&error=Invalid+input");
    exit;
}

// Insert poll
$stmt = $conn->prepare("INSERT INTO polls (course_id, user_id, question, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $course_id, $_SESSION['user_id'], $question);
$stmt->execute();
$poll_id = $stmt->insert_id;
$stmt->close();

// Get course details for email
$course_stmt = $conn->prepare("SELECT course_code, course_title FROM courses WHERE id = ?");
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course_result = $course_stmt->get_result();
$course = $course_result->fetch_assoc();

// Get teacher name for email
$teacher_stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$teacher_stmt->bind_param("i", $_SESSION['user_id']);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher = $teacher_result->fetch_assoc();

// Format options for email
$options_html = '<ul style="margin-top: 10px;">';
foreach ($options as $opt) {
    $options_html .= '<li style="margin-bottom: 5px;">' . htmlspecialchars($opt) . '</li>';
}
$options_html .= '</ul>';

// Send email notifications
require_once $_SERVER['DOCUMENT_ROOT'] . '/email/notification_sender.php';
$emailSubject = "New Poll in {$course['course_code']}";
$emailContent = "
    <p>{$teacher['first_name']} {$teacher['last_name']} has created a new poll:</p>
    <div style='margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px;'>
        <p><strong>" . htmlspecialchars($question) . "</strong></p>
        <p>Options:</p>
        $options_html
    </div>
    <p>Log in to BLAZE to vote in this poll.</p>
";

notifyEnrolledUsers($course_id, $emailSubject, $emailContent, $course['course_code'], $course['course_title']);

// Insert options
$opt_stmt = $conn->prepare("INSERT INTO poll_options (poll_id, option_text) VALUES (?, ?)");
foreach ($options as $opt) {
    $opt_stmt->bind_param("is", $poll_id, $opt);
    $opt_stmt->execute();
}
$opt_stmt->close();

header("Location: ../announcements.php?error=Poll posted successfully");
exit;
