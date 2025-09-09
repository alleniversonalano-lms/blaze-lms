<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$code = $_GET['code'] ?? '';
if (!$code) {
    header('Location: /login?error=Missing class code');
    exit;
}

// Not signed in? Redirect to login with redirect param
if (!isset($_SESSION['user_id'])) {
    $redirectUrl = '/join-class.php?code=' . urlencode($code);
    header('Location: /login.php?error=Please sign in to continue');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'student'; // fallback to student if role not set

// Get course by class_code
$stmt = $conn->prepare("SELECT id FROM courses WHERE class_code = ?");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $role = $_SESSION['role'] ?? 'student';

    if ($role === 'teacher') {
        header('Location: /access-teacher/dashboard.php?error=Invalid+invitation+link');
    } else {
        header('Location: /access-student/dashboard.php?error=Invalid+invitation+link');
    }
    exit;
}

$course = $result->fetch_assoc();
$course_id = $course['id'];

// Save in session for future access
$_SESSION['active_course_id'] = $course_id;

if ($role === 'teacher') {
    // Check if already added
    $check = $conn->prepare("SELECT id FROM course_collaborators WHERE course_id = ? AND teacher_id = ?");
    $check->bind_param("ii", $course_id, $user_id);
    $check->execute();
    $checkRes = $check->get_result();

    if ($checkRes->num_rows === 0) {
        $insert = $conn->prepare("INSERT INTO course_collaborators (course_id, teacher_id) VALUES (?, ?)");
        $insert->bind_param("ii", $course_id, $user_id);
        $insert->execute();
    }

    // Redirect to teacher announcements
    header("Location: /access-teacher/collaboration?error=Joined successfully");
    exit;
} else {
    // Check if already enrolled
    $check = $conn->prepare("SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ?");
    $check->bind_param("ii", $course_id, $user_id);
    $check->execute();
    $checkRes = $check->get_result();

    if ($checkRes->num_rows === 0) {
        $insert = $conn->prepare("INSERT INTO course_enrollments (course_id, student_id) VALUES (?, ?)");
        $insert->bind_param("ii", $course_id, $user_id);
        $insert->execute();
    }

    // Redirect to student announcements
    header("Location: /access-student/dashboard?error=Joined successfully");
    exit;
}
