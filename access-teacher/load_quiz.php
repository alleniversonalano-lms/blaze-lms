<?php
session_start();

// Store session details into variables
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$email_address = $_SESSION['email_address'];
$role = $_SESSION['role'];
$profile_pic = $_SESSION['profile_pic'];

// Redirect if not logged in or not a teacher
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'teacher') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Check if quiz ID is provided
if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Quiz ID not provided']);
    exit;
}

$quizId = $_GET['id'];

// Define the path where quiz files are stored
$quizDirectory = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/';

// Check if quiz file exists
$quizFile = $quizDirectory . $quizId . '.json';
if (!file_exists($quizFile)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Quiz not found']);
    exit;
}

try {
    // Read the quiz file
    $quizJson = file_get_contents($quizFile);
    $quiz = json_decode($quizJson, true);

    // Verify the quiz belongs to this course
    if ($quiz['courseId'] != $_SESSION['ann_course_id']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Quiz does not belong to this course']);
        exit;
    }

    // Verify the quiz belongs to this teacher or is a collaborator
    require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';
    $stmt = $conn->prepare("
        SELECT EXISTS(
            SELECT 1 FROM courses c
            LEFT JOIN course_collaborators cc ON c.id = cc.course_id AND cc.teacher_id = ?
            WHERE c.id = ? AND (c.user_id = ? OR cc.teacher_id IS NOT NULL)
        ) as has_access
    ");
    $stmt->bind_param("iii", $user_id, $quiz['courseId'], $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row['has_access']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied to this quiz']);
        exit;
    }

    // Return the quiz data
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'quiz' => $quiz
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load quiz: ' . $e->getMessage()
    ]);
}
