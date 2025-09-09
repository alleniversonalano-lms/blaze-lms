<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: /login?error=Access+denied");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = strtolower($_SESSION['role']);

require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_code = trim($_POST['class_code'] ?? '');

    if ($entered_code === '') {
        header("Location: ../dashboard?error=Class+code+is+required");
        exit;
    }

    // Find matching course (case-sensitive) and get the creator ID
    $stmt = $conn->prepare("SELECT id, user_id FROM courses WHERE class_code = BINARY ?");
    $stmt->bind_param("s", $entered_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: ../dashboard?error=Invalid+class+code");
        exit;
    }

    $course = $result->fetch_assoc();
    $course_id = $course['id'];
    $creator_id = $course['user_id'];

    // Prevent joining own course
    if ($creator_id == $user_id) {
        header("Location: ../dashboard?error=You+are+already+the+creator+of+this+course");
        exit;
    }

    if ($role === 'teacher') {
        // Check if already joined as a co-teacher
        $check = $conn->prepare("SELECT 1 FROM course_collaborators WHERE course_id = ? AND teacher_id = ?");
        $check->bind_param("ii", $course_id, $user_id);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            header("Location: ../dashboard?error=Already+joined+this+course+as+teacher");
            exit;
        }

        // Join as co-teacher
        $insert = $conn->prepare("INSERT INTO course_collaborators (course_id, teacher_id) VALUES (?, ?)");
        $insert->bind_param("ii", $course_id, $user_id);

    } elseif ($role === 'student') {
        // Check if already enrolled
        $check = $conn->prepare("SELECT 1 FROM course_enrollments WHERE course_id = ? AND student_id = ?");
        $check->bind_param("ii", $course_id, $user_id);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            header("Location: ../dashboard?error=Already+enrolled+in+this+course");
            exit;
        }

        // Enroll as student
        $insert = $conn->prepare("INSERT INTO course_enrollments (course_id, student_id) VALUES (?, ?)");
        $insert->bind_param("ii", $course_id, $user_id);
    } else {
        header("Location: ../dashboard?error=Unknown+user+role");
        exit;
    }

    // Attempt to execute the insert
    if ($insert->execute()) {
        header("Location: ../dashboard?error=Successfully+joined+the+course");
        exit;
    } else {
        header("Location: ../dashboard?error=Failed+to+join+course");
        exit;
    }
} else {
    header("Location: ../dashboard");
    exit;
}
