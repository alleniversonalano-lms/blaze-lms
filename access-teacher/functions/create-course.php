<?php
session_start();
require $_SERVER["DOCUMENT_ROOT"] . '/connect/db.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'teacher') {
    header("Location: /login?error=Access+denied");
    exit;
}

function generateUniqueClassCode($conn) {
    do {
        $code = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 7);
        $stmt = $conn->prepare("SELECT id FROM courses WHERE class_code = ? LIMIT 1");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $stmt->store_result();
    } while ($stmt->num_rows > 0);

    return $code;
}

// Collect and sanitize form input
$teacher_id     = $_SESSION['user_id'];
$course_code    = trim($_POST['course_code'] ?? '');
$course_title   = trim($_POST['course_title'] ?? '');
$description    = trim($_POST['description'] ?? '');
$meeting_link   = trim($_POST['meeting_link'] ?? '');
$class_code = generateUniqueClassCode($conn);

// Validation
if (empty($course_code) || empty($course_title) || empty($class_code)) {
    header("Location: ../create-course-page.php?error=Missing+required+fields");
    exit;
}

// Insert into `courses`
$stmt = $conn->prepare("INSERT INTO courses (user_id, course_code, course_title, class_code, description, meeting_link) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isssss", $teacher_id, $course_code, $course_title, $class_code, $description, $meeting_link);
if (!$stmt->execute()) {
    header("Location: ../create-course-page.php?error=Failed+to+create+course");
    exit;
}
$course_id = $stmt->insert_id;

// Insert multiple ILOs
$ilo_numbers     = $_POST['ilo_number'] ?? [];
$ilo_descriptions = $_POST['ilo_description'] ?? [];

if (count($ilo_numbers) === count($ilo_descriptions)) {
    $stmt_ilo = $conn->prepare("INSERT INTO course_ilos (course_id, ilo_number, ilo_description) VALUES (?, ?, ?)");
    foreach ($ilo_numbers as $index => $number) {
        $desc = trim($ilo_descriptions[$index]);
        $num  = trim($number);
        if ($num && $desc) {
            $stmt_ilo->bind_param("iss", $course_id, $num, $desc);
            $stmt_ilo->execute();
        }
    }
}

header("Location: ../dashboard.php?error=Course+created");
exit;
?>
