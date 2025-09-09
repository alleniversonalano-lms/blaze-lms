<?php
session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/access-teacher/functions/history_logger.php');

logUserHistory("visited", "Grades"); // You can customize this per page

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
    header("Location: /login?error=Access+denied");
    exit;
}


// Get unread message count
require_once $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

// Debug connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
}

try {
    // Check if messages table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'messages'");
    if ($table_check->num_rows == 0) {
        error_log("Messages table does not exist");
        $unread_count = 0;
    } else {
        $unread_query = "SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0";
        $stmt = $conn->prepare($unread_query);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            $unread_count = 0;
        } else {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $unread_count = $row['unread_count'];
            error_log("User ID: " . $user_id . " | Unread count: " . $unread_count);
        }
    }
} catch (Exception $e) {
    error_log("Error getting unread count: " . $e->getMessage());
    $unread_count = 0;
}

// Get course ID from URL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $_SESSION['ann_course_id'] = (int) $_POST['course_id'];
    header("Location: assessment");
    exit;
}

$course_id = $_SESSION['ann_course_id'] ?? 0;

if ($course_id) {
    $stmt = $conn->prepare("
        SELECT c.user_id AS owner_id,
               EXISTS(SELECT 1 FROM course_collaborators WHERE course_id = ? AND teacher_id = ?) AS is_collaborator,
               EXISTS(SELECT 1 FROM course_enrollments WHERE course_id = ? AND student_id = ?) AS is_enrolled
        FROM courses c
        WHERE c.id = ?
    ");
    $stmt->bind_param("iiiii", $course_id, $user_id, $course_id, $user_id, $course_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        // Course doesn't exist
        unset($_SESSION['ann_course_id']);
        header("Location: dashboard?error=Course+not+found");
        exit;
    }

    $row = $res->fetch_assoc();
    $is_owner = $row['owner_id'] == $user_id;
    $is_collaborator = $row['is_collaborator'];
    $is_enrolled = $row['is_enrolled'];

    if (!($is_owner || $is_collaborator || $is_enrolled)) {
        // Not part of the course
        unset($_SESSION['ann_course_id']);
        header("Location: dashboard?error=Access+denied+to+course");
        exit;
    }
}

// Fetch course code and title
$course_code = '';
$course_title = '';

if ($course_id) {
    $course_stmt = $conn->prepare("SELECT course_code, course_title FROM courses WHERE id = ?");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    if ($course_result && $course_row = $course_result->fetch_assoc()) {
        $course_code = htmlspecialchars($course_row['course_code']);
        $course_title = htmlspecialchars($course_row['course_title']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Grades</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar {
            background-color: #B71C1C;
            color: white;
            width: 240px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            padding: 120px 20px 20px;
            /* extra top padding to clear the logo */
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
        }

        .logo {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            z-index: 10;
            text-align: center;
            user-select: none;
            /* Prevent text/image selection */
            pointer-events: none;
            /* Optional: ignore pointer events like drag */
        }

        .logo img {
            width: 100%;
            max-height: 150px;
            height: auto;
            /* For Safari */
            user-select: none;
            /* For other browsers */
            -webkit-user-drag: none;
            /* For Safari */
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            pointer-events: none;
            /* Prevent dragging */
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .nav a {
            color: white;
            text-decoration: none;
            font-size: 1rem;
            padding: 10px 15px;
            border-radius: 8px;
            transition: background 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

.unread-count {
            background-color: #fff;
            color: #B71C1C;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.8rem;
            font-weight: bold;
            min-width: 20px;
            text-align: center;
        }

        .nav a:hover {
            background-color: rgba(254, 80, 80, 0.73);
        }

        .main-content {
            margin-left: 240px;
            padding: 100px 40px 40px;
            /* Increased top padding to make space for fixed topbar */
            flex-grow: 1;
            background: #f5f5f5;
            overflow-y: auto;
            position: relative;
            z-index: 1;
        }

        .main-content::before {
            content: "BLAZE";
            position: fixed;
            top: 40%;
            left: 58%;
            transform: translate(-50%, -50%);
            font-size: 12rem;
            font-weight: 900;
            color: rgb(60, 60, 60);
            opacity: 0.08;
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
        }

        .main-content::after {
            content: "BatStateU Learning and Academic Zone for Excellence";
            position: fixed;
            top: 58%;
            left: 58%;
            transform: translate(-50%, -50%);
            font-size: 1.8rem;
            font-weight: 600;
            color: rgb(60, 60, 60);
            opacity: 0.08;
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
            text-align: center;
        }

        h1 {
            font-size: 2rem;
            color: #333;
            position: relative;
            z-index: 2;
        }

        p {
            position: relative;
            z-index: 2;
            color: #555;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }

            .main-content {
                margin-left: 200px;
                padding: 20px;
            }

            .main-content::before {
                font-size: 8rem;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                position: absolute;
                z-index: 10;
                height: 100%;
            }

            .main-content {
                margin-left: 0;
            }

            .main-content::before {
                font-size: 5rem;
            }
        }

        .topbar {
            position: fixed;
            /* Fix it to the top */
            top: 0;
            left: 240px;
            /* Offset to match sidebar width */
            right: 0;
            height: 60px;
            /* Optional: consistent height */
            background-color: white;
            display: flex;
            align-items: center;
            padding: 0 30px;
            gap: 30px;
            border-bottom: 1px solid #ddd;
            z-index: 1000;
            /* Ensure it's on top of all layers */
        }


        .topbar-link {
            text-decoration: none;
            color: #444;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .topbar-link:hover {
            background-color: #f2f2f2;
        }

        .topbar-link.active {
            background-color: #b71c1c;
            color: white;
        }

        .header {
            margin-top: -45px;
            margin-bottom: 10px;
            padding: 20px 0;
            border-bottom: 1px solid #ddd;
            text-align: left;
            font-size: 0.95rem;
            color: #666;
        }

        .gradebook-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
            padding: 20px;
            margin-top: 20px;
            position: relative;
            /* Make z-index work */
            z-index: 100;
        }

        .gradebook-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            position: relative;
            /* Make z-index work */
            z-index: 100;
        }

        .gradebook-header h3 {
            margin: 0;
            font-size: 1.25rem;
            color: #2c3e50;
        }

        .export-btn {
            background-color: #1976d2;
            color: white;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .export-btn:hover {
            background-color: #1565c0;
        }

        .gradebook-table-wrapper {
            overflow-x: auto;
            max-width: 100%;
            position: relative;
            /* Make z-index work */
            z-index: 100;
        }

        .gradebook-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
            background-color: white;
            position: relative;
            /* Ensure it stacks correctly */
            z-index: 100;
        }

        .gradebook-table th,
        .gradebook-table td {
            padding: 8px 10px;
            /* Reduced padding */

            text-align: center;
            border-bottom: 1px solid #eee;
            white-space: nowrap;
            /* Prevents wrapping */
        }


        .gradebook-table th {
            background-color: #f9f9f9;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 10;
            /* Keep header above body rows */
        }

        .gradebook-table td:first-child {
            text-align: left;
        }

        /* Assessment Section Styles */
        .assessment-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .assessment-header {
            background: #f8f9fa;
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
        }

        .assessment-header:hover {
            background: #f0f0f0;
        }

        .assessment-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .assessment-header .stats {
            font-size: 0.9rem;
            color: #666;
        }

        .assessment-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .assessment-section.expanded .assessment-content {
            max-height: 2000px;
            transition: max-height 0.5s ease-in;
        }

        .attempts-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .attempts-table th,
        .attempts-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .attempts-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .attempts-table tr:last-child td {
            border-bottom: none;
        }

        .attempts-table tr:hover td {
            background: #f5f5f5;
        }

        .score {
            font-weight: 600;
        }

        .timestamp {
            color: #666;
            font-size: 0.9rem;
        }

        .security-warning {
            color: #dc3545;
            font-size: 0.85rem;
        }

        .assessment-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #e9ecef;
            border: none;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .assessment-section.expanded .assessment-toggle {
            transform: rotate(180deg);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal.show {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 2rem;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 900px;
            position: relative;
            margin-top: 2rem;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            color: #666;
        }

        .close-modal:hover {
            color: #333;
        }

        .student-attempts {
            width: 100%;
            border-collapse: collapse;
        }

        .student-attempts th,
        .student-attempts td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .student-attempts th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .view-details-btn, .view-logs-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .view-logs-btn {
            background: #6c757d;
            margin-left: 0.5rem;
        }

        .activity-logs {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 1rem;
            margin: 1rem 0;
            display: none;
        }

        .activity-logs.show {
            display: block;
        }

        .log-entry {
            padding: 0.5rem;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.9rem;
        }

        .log-entry:last-child {
            border-bottom: none;
        }

        .log-time {
            color: #666;
            font-size: 0.85rem;
            margin-right: 1rem;
        }

        .log-type {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }

        .log-type.warning {
            background: #fff3cd;
            color: #856404;
        }

        .log-type.suspicious {
            background: #f8d7da;
            color: #721c24;
        }

        .log-type.info {
            background: #cce5ff;
            color: #004085;
        }

        .log-entry small {
            display: block;
            margin-top: 0.25rem;
            margin-left: 1.5rem;
            color: #666;
            font-family: monospace;
            word-break: break-all;
        }

        .log-entry pre {
            margin: 0.5rem 0;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
            overflow-x: auto;
        }

        .view-details-btn:hover {
            background: #0056b3;
        }

        .student-summary {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .student-summary h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }

        .student-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .stat-item {
            background: white;
            padding: 0.75rem;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0.25rem;
        }

        .stat-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }

        /* Progress bar styles */
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: #28a745;
            transition: width 0.3s ease;
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="/img/left-logo.png" alt="BLAZE Logo">
        </div>

        <div class="nav">
            <a href="dashboard">Dashboard</a>
            <a href="unpublished">Unpublished</a>
            <a href="collaboration">Collaboration</a>
            <a href="msg" target="_blank">Chat <?php if ($unread_count > 0): ?><span class="unread-count"><?= $unread_count ?></span><?php endif; ?></a>
            <a href="profile">Profile</a>
            <a href="logout">Logout</a>
        </div>
    </div>




    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <a href="announcements" class="topbar-link">Bulletin</a>
            <a href="modules" class="topbar-link">Modules</a>
            <a href="assessments" class="topbar-link">Assessments</a>
            <a href="question-bank" class="topbar-link">Question Bank</a>
            <a href="history" class="topbar-link">History</a>
            <a href="people" class="topbar-link">People</a>
            <a href="grades" class="topbar-link active">Grades</a>
            <a href="ilo" class="topbar-link">ILO</a>
        </div>

        <!-- Header -->
        <div class="header">
            <p><strong><?= $course_code ?>:</strong> <?= $course_title ?></p>
        </div>

        <h2>Grades</h2>

        <br>

        <?php
        // Function to get all quizzes for a course
        function getQuizzesByCourse($courseId) {
            $quizDir = __DIR__ . "/../assessment-list/quizzes/";
            $quizzes = [];
            
            if (is_dir($quizDir)) {
                $files = glob($quizDir . "quiz_*.json");
                foreach ($files as $file) {
                    $quizData = json_decode(file_get_contents($file), true);
                    if ($quizData && isset($quizData['courseId']) && $quizData['courseId'] == $courseId) {
                        $quizData['file_id'] = basename($file, '.json');
                        $quizzes[] = $quizData;
                    }
                }
            }
            
            return $quizzes;
        }

        // Function to get all attempts for a quiz
        function getQuizAttempts($quizId) {
            $attemptsDir = __DIR__ . "/../assessment-list/quizzes/attempts/";
            $attempts = [];
            
            if (is_dir($attemptsDir)) {
                $files = glob($attemptsDir . $quizId . "_*.json");
                foreach ($files as $file) {
                    $attemptData = json_decode(file_get_contents($file), true);
                    if ($attemptData) {
                        if (is_array($attemptData) && isset($attemptData[0])) {
                            $attempts = array_merge($attempts, $attemptData);
                        } else {
                            $attempts[] = $attemptData;
                        }
                    }
                }
            }
            
            // Also check all_attempts.jsonl
            $allAttemptsFile = $attemptsDir . "all_attempts.jsonl";
            if (file_exists($allAttemptsFile)) {
                $lines = file($allAttemptsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $attempt = json_decode($line, true);
                    if ($attempt && isset($attempt['quizId']) && $attempt['quizId'] === $quizId) {
                        $attempts[] = $attempt;
                    }
                }
            }
            
            return $attempts;
        }

        // Get all quizzes for the course
        $quizzes = getQuizzesByCourse($course_id);
        ?>

        <!-- Summary Card -->
        <div class="gradebook-card">
            <div class="gradebook-header">
                <h3>Assessment Overview</h3>
                <a href="#" class="export-btn">Export All Results</a>
            </div>
            <div class="gradebook-table-wrapper">
                <table class="gradebook-table">
                    <thead>
                        <tr>
                            <th>Assessment</th>
                            <th>Total Attempts</th>
                            <th>Average Score</th>
                            <th>Highest Score</th>
                            <th>Lowest Score</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quizzes as $quiz): 
                            $attempts = getQuizAttempts($quiz['file_id']);
                            $totalAttempts = count($attempts);
                            
                            $scores = array_map(function($a) {
                                return isset($a['score']) && isset($a['totalPoints']) ? 
                                    ($a['score'] / $a['totalPoints']) * 100 : 0;
                            }, $attempts);
                            
                            $avgScore = $totalAttempts ? array_sum($scores) / $totalAttempts : 0;
                            $highScore = $totalAttempts ? max($scores) : 0;
                            $lowScore = $totalAttempts ? min($scores) : 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                            <td><?php echo $totalAttempts; ?></td>
                            <td><?php echo number_format($avgScore, 1); ?>%</td>
                            <td><?php echo number_format($highScore, 1); ?>%</td>
                            <td><?php echo number_format($lowScore, 1); ?>%</td>
                            <td>
                                <a href="#" onclick="event.preventDefault(); toggleAssessment('<?php echo $quiz['file_id']; ?>')">View Details</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Individual Assessment Sections -->
        <?php foreach ($quizzes as $quiz): 
            $attempts = getQuizAttempts($quiz['file_id']);
            // Sort attempts chronologically from earliest to latest
            usort($attempts, function($a, $b) {
                return strtotime($a['submittedAt'] ?? 0) - strtotime($b['submittedAt'] ?? 0);
            });
        ?>
        <div id="section-<?php echo $quiz['file_id']; ?>" class="assessment-section">
            <div class="assessment-header" onclick="toggleAssessment('<?php echo $quiz['file_id']; ?>')">
                <h3>
                    <button class="assessment-toggle">▼</button>
                    <?php echo htmlspecialchars($quiz['title']); ?>
                </h3>
                <span class="stats">
                    <?php echo count($attempts); ?> attempts | 
                    Last attempt: <?php 
                        echo !empty($attempts) ? 
                            date('F j, Y \a\t g:i A (T)', strtotime($attempts[0]['submittedAt'])) : 
                            'No attempts'; 
                    ?>
                </span>
            </div>
            <div class="assessment-content">
                <?php
                // Group attempts by student
                $studentAttempts = [];
                foreach ($attempts as $attempt) {
                    $studentId = $attempt['userId'];
                    if (!isset($studentAttempts[$studentId])) {
                        $studentAttempts[$studentId] = [
                            'info' => [
                                'name' => $attempt['firstName'] . ' ' . $attempt['lastName'],
                                'email' => $attempt['userEmail'],
                                'userId' => $studentId
                            ],
                            'attempts' => []
                        ];
                    }
                    $studentAttempts[$studentId]['attempts'][] = $attempt;
                }

                // Sort students by name
                uasort($studentAttempts, function($a, $b) {
                    return strcasecmp($a['info']['name'], $b['info']['name']);
                });
                ?>
                <table class="attempts-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Attempts</th>
                            <th>Best Score</th>
                            <th>Last Attempt</th>
                            <th>Average Score</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($studentAttempts as $studentId => $student): 
                            $attempts = $student['attempts'];
                            $numAttempts = count($attempts);
                            
                            // Calculate statistics
                            $scores = array_map(function($a) {
                                return isset($a['score']) && isset($a['totalPoints']) ? 
                                    ($a['score'] / $a['totalPoints']) * 100 : 0;
                            }, $attempts);
                            
                            $bestScore = max($scores);
                            $avgScore = array_sum($scores) / count($scores);
                            $lastAttempt = end($attempts);
                        ?>
                        <tr>
                            <td>
                                <?php 
                                echo htmlspecialchars($student['info']['name']); 
                                $securityIssues = array_filter($attempts, function($a) {
                                    return isset($a['isSecurityBreach']) && $a['isSecurityBreach'];
                                });
                                if (!empty($securityIssues)) {
                                    echo '<br><span class="security-warning">⚠️ Security issues detected</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo $numAttempts; ?></td>
                            <td class="score"><?php echo number_format($bestScore, 1); ?>%</td>
                            <td class="timestamp">
                                <?php echo date('F j, Y \a\t g:i A', strtotime($lastAttempt['submittedAt'])); ?>
                            </td>
                            <td><?php echo number_format($avgScore, 1); ?>%</td>
                            <td>
                                <button class="view-details-btn" 
                                        onclick="showStudentAttempts('<?php echo $quiz['title']; ?>', 
                                                                    <?php echo htmlspecialchars(json_encode($student)); ?>)">
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($studentAttempts)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No attempts recorded for this assessment</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>


    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuContainers = document.querySelectorAll('.menu-container');

            menuContainers.forEach(container => {
                const button = container.querySelector('.menu-button');

                button.addEventListener('click', (e) => {
                    e.stopPropagation(); // prevent the click from bubbling to document
                    // Close any other open menus
                    document.querySelectorAll('.menu-container').forEach(c => {
                        if (c !== container) c.classList.remove('show');
                    });
                    // Toggle current
                    container.classList.toggle('show');
                });
            });

            // Close menu when clicking outside
            document.addEventListener('click', () => {
                document.querySelectorAll('.menu-container').forEach(container => {
                    container.classList.remove('show');
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const table = document.querySelector('.gradebook-table');
            const columns = table.querySelectorAll('thead th').length;

            if (columns > 7) {
                table.style.fontSize = '0.85rem';
            }
            if (columns > 10) {
                table.style.fontSize = '0.78rem';
            }
            if (columns > 15) {
                table.style.fontSize = '0.72rem';
            }
            if (columns > 20) {
                table.style.fontSize = '0.64rem';
            }
            if (columns > 25) {
                table.style.fontSize = '0.59rem';
            }
            if (columns > 35) {
                table.style.fontSize = '0.45rem';
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const table = document.querySelector('.gradebook-table');

            if (table) {
                table.addEventListener('mouseover', (e) => {
                    const cell = e.target.closest('td, th');
                    if (!cell) return;

                    const index = cell.cellIndex;
                    table.querySelectorAll(`td:nth-child(${index + 1}), th:nth-child(${index + 1})`)
                        .forEach(c => c.classList.add('hovered'));
                });

                table.addEventListener('mouseout', (e) => {
                    table.querySelectorAll('.hovered').forEach(c => c.classList.remove('hovered'));
                });
            }
        });

        function toggleAssessment(quizId) {
            const section = document.getElementById('section-' + quizId);
            section.classList.toggle('expanded');
            
            // Save state to localStorage
            const expanded = section.classList.contains('expanded');
            localStorage.setItem('assessment_' + quizId, expanded ? 'expanded' : 'collapsed');
        }

        // Restore expanded state on page load
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.assessment-section').forEach(section => {
                const quizId = section.id.replace('section-', '');
                const isExpanded = localStorage.getItem('assessment_' + quizId) === 'expanded';
                if (isExpanded) {
                    section.classList.add('expanded');
                }
            });
        });
    </script>

    <!-- Student Attempts Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Student Attempts</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="studentSummary" class="student-summary">
                    <!-- Student summary will be inserted here -->
                </div>
                <table id="attemptsTable" class="student-attempts">
                    <!-- Attempts will be inserted here -->
                </table>
            </div>
        </div>
    </div>

    <script>
        function showStudentAttempts(quizTitle, studentData) {
            const modal = document.getElementById('studentModal');
            const modalTitle = document.getElementById('modalTitle');
            const summaryDiv = document.getElementById('studentSummary');
            const tableDiv = document.getElementById('attemptsTable');
            
            // Set title
            modalTitle.textContent = `${quizTitle} - ${studentData.info.name}`;
            
            // Calculate statistics
            const attempts = studentData.attempts;
            const scores = attempts.map(a => ({
                score: a.score,
                total: a.totalPoints,
                percentage: (a.score / a.totalPoints) * 100
            }));
            
            const bestScore = Math.max(...scores.map(s => s.percentage));
            const avgScore = scores.reduce((sum, s) => sum + s.percentage, 0) / scores.length;
            const totalAttempts = attempts.length;
            const securityIssues = attempts.filter(a => a.isSecurityBreach).length;
            
            // Create summary section
            summaryDiv.innerHTML = `
                <h4>${studentData.info.name}</h4>
                <div class="student-stats">
                    <div class="stat-item">
                        <div class="stat-label">Total Attempts</div>
                        <div class="stat-value">${totalAttempts}</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Best Score</div>
                        <div class="stat-value">${bestScore.toFixed(1)}%</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Average Score</div>
                        <div class="stat-value">${avgScore.toFixed(1)}%</div>
                    </div>
                    ${securityIssues ? `
                    <div class="stat-item" style="color: #dc3545">
                        <div class="stat-label">Security Issues</div>
                        <div class="stat-value">${securityIssues}</div>
                    </div>
                    ` : ''}
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${bestScore}%"></div>
                </div>
            `;
            
            // Create attempts table
            let tableHTML = `
                <thead>
                    <tr>
                        <th>Attempt #</th>
                        <th>Score</th>
                        <th>Time</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Activity</th>
                    </tr>
                </thead>
                <tbody>
            `;
            
            // Sort attempts chronologically from earliest to latest
            attempts.sort((a, b) => new Date(a.startTime) - new Date(b.startTime));
            
            attempts.forEach((attempt, index) => {
                const score = attempt.score;
                const total = attempt.totalPoints;
                const percentage = (score / total) * 100;
                
                let status = 'Completed';
                let statusClass = '';
                
                if (attempt.isTimeout) {
                    status = 'Time Out';
                    statusClass = 'text-warning';
                }
                if (attempt.isSecurityBreach) {
                    status = 'Security Warning';
                    statusClass = 'text-danger';
                }

                // Process activity data
                const hasActivity = attempt.suspiciousActivity || 
                                  attempt.visibilityChanges > 0 || 
                                  attempt.focusChanges > 0 || 
                                  attempt.securityWarnings > 0;
                
                tableHTML += `
                    <tr>
                        <td>Attempt ${index + 1}</td>
                        <td class="score">
                            ${attempt.originalScore !== attempt.score ? 
                                `<span style="text-decoration: line-through">${attempt.originalScore}</span> ` : 
                                ''}
                            ${score} / ${total} (${percentage.toFixed(1)}%)
                            ${attempt.securityPenalty ? 
                                `<br><span class="security-warning">-${attempt.securityPenalty} point penalty</span>` : 
                                ''}
                        </td>
                        <td>
                            ${new Date(attempt.startTime).toLocaleTimeString()} - 
                            ${new Date(attempt.endTime).toLocaleTimeString()}
                        </td>
                        <td>${Math.floor(attempt.timeUsed / 60)}m ${attempt.timeUsed % 60}s</td>
                        <td class="${statusClass}">${status}</td>
                        <td>
                            <button class="view-logs-btn" onclick="toggleActivityLogs('${attempt.attemptId}')">
                                View Logs
                            </button>
                        </td>
                    </tr>
                    <tr id="logs-${attempt.attemptId}" style="display: none;">
                        <td colspan="6">
                            <div class="activity-logs">
                                ${generateActivityLogs(attempt)}
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tableHTML += '</tbody>';
            tableDiv.innerHTML = tableHTML;
            
            // Show modal
            modal.classList.add('show');
        }

        function closeModal() {
            document.getElementById('studentModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('studentModal').addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        async function loadSecurityIncidents(attemptId) {
            try {
                const response = await fetch(`/assessment-list/quizzes/attempts/security_incidents.jsonl`);
                const text = await response.text();
                const incidents = text.split('\\n')
                    .filter(line => line.trim())
                    .map(line => JSON.parse(line))
                    .filter(incident => incident.attemptId === attemptId)
                    .sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp)); // Sort by timestamp ascending
                
                // Return all incidents instead of just the first one
                return incidents.length > 0 ? incidents : null;
            } catch (error) {
                console.error('Error loading security incidents:', error);
                return null;
            }
        }

        function generateActivityLogs(attempt) {
            let logsHTML = '';
            
            // Add attempt timestamp
            const attemptTime = new Date(attempt.submittedAt).toLocaleString();
            logsHTML += `
                <div class="log-entry">
                    <span class="log-time">${attemptTime}</span>
                    <span class="log-type info">Session Start</span>
                    📝 Assessment attempt started
                </div>`;

            // Add page load info
            if (attempt.suspiciousActivity) {
                attempt.suspiciousActivity.forEach(activity => {
                    const time = new Date(activity.timestamp).toLocaleTimeString();
                    let logType = 'info';
                    let icon = '🔍';
                    
                    if (activity.type === 'page_loaded') {
                        logsHTML += `
                            <div class="log-entry">
                                <span class="log-time">${time}</span>
                                <span class="log-type ${logType}">Page Load</span>
                                ${icon} Loaded from: ${activity.data.referrer || 'Direct access'}
                            </div>`;
                    }
                });
            }

            // Add security incident details if available
            loadSecurityIncidents(attempt.attemptId).then(incidents => {
                if (incidents && incidents.length > 0) {
                    const incidentLogs = document.createElement('div');
                    
                    // Process all incidents chronologically
                    incidents.forEach(incident => {
                        const incidentTime = new Date(incident.timestamp).toLocaleString();
                        
                        // Add incident timestamp and ID
                        incidentLogs.innerHTML += `
                            <div class="log-entry">
                                <span class="log-time">${incidentTime}</span>
                                <span class="log-type info">Incident</span>
                                🔍 Incident recorded (${incident.attemptId})
                            </div>`;
                        
                        // Add penalty information
                        if (incident.penalty) {
                            incidentLogs.innerHTML += `
                                <div class="log-entry">
                                    <span class="log-time">${incidentTime}</span>
                                    <span class="log-type suspicious">Penalty</span>
                                    ⚠️ Security penalty applied: -${incident.penalty} points
                                </div>`;
                        }
                        
                        // Add suspicious activity details if any
                        if (incident.suspiciousActivity) {
                            incident.suspiciousActivity.forEach(activity => {
                                const actTime = new Date(activity.timestamp).toLocaleString();
                                incidentLogs.innerHTML += `
                                    <div class="log-entry">
                                        <span class="log-time">${actTime}</span>
                                        <span class="log-type warning">Activity</span>
                                        🚨 ${formatActivityType(activity.type)}
                                        ${activity.data ? `<br><small>${JSON.stringify(activity.data)}</small>` : ''}
                                    </div>`;
                            });
                        }
                    });

                    // Add suspicious activity details
                    if (incident.suspiciousActivity) {
                        incident.suspiciousActivity.forEach(activity => {
                            const actTime = new Date(activity.timestamp).toLocaleString();
                            incidentLogs.innerHTML += `
                                <div class="log-entry">
                                    <span class="log-time">${actTime}</span>
                                    <span class="log-type warning">Activity</span>
                                    🚨 ${formatActivityType(activity.type)}
                                    ${activity.data ? `<br><small>${JSON.stringify(activity.data)}</small>` : ''}
                                </div>`;
                        });
                    }

                    // Add IP address information
                    if (incident.ipAddress) {
                        incidentLogs.innerHTML += `
                            <div class="log-entry">
                                <span class="log-type info">Network</span>
                                🌐 IP Address: ${incident.ipAddress}
                            </div>`;
                    }

                    // Append the incident logs to the existing logs container
                    const logsContainer = document.querySelector(`#logs-${attempt.attemptId} .activity-logs`);
                    if (logsContainer) {
                        logsContainer.appendChild(incidentLogs);
                    }
                }
            });

            // Add focus/visibility changes
            if (attempt.focusChanges > 0) {
                logsHTML += `
                    <div class="log-entry">
                        <span class="log-type warning">Focus</span>
                        🔄 Tab/Window focus changed ${attempt.focusChanges} times
                    </div>`;
            }

            if (attempt.visibilityChanges > 0) {
                logsHTML += `
                    <div class="log-entry">
                        <span class="log-type warning">Visibility</span>
                        👁️ Page visibility changed ${attempt.visibilityChanges} times
                    </div>`;
            }

            // Add security warnings
            if (attempt.securityWarnings > 0) {
                logsHTML += `
                    <div class="log-entry">
                        <span class="log-type suspicious">Security</span>
                        ⚠️ ${attempt.securityWarnings} security warnings detected
                    </div>`;
            }

            // Add specific security flags
            if (attempt.securityFlags && attempt.securityFlags.length > 0) {
                attempt.securityFlags.forEach(flag => {
                    logsHTML += `
                        <div class="log-entry">
                            <span class="log-type suspicious">Flag</span>
                            🚫 ${formatSecurityFlag(flag)}
                        </div>`;
                });
            }

            // Add browser info
            if (attempt.browserFingerprint) {
                try {
                    const fingerprint = JSON.parse(atob(attempt.browserFingerprint));
                    logsHTML += `
                        <div class="log-entry">
                            <span class="log-type info">Browser</span>
                            💻 ${fingerprint.userAgent}<br>
                            🌐 Timezone: ${fingerprint.timezone}<br>
                            🖥️ Screen: ${fingerprint.screen}
                        </div>`;
                } catch (e) {
                    // Handle parsing error
                }
            }

            return logsHTML || '<div class="log-entry">No activity logs available</div>';
        }

        function formatSecurityFlag(flag) {
            // Convert flag from snake_case to readable format
            return flag
                .split('_')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        }

        function formatActivityType(type) {
            switch (type) {
                case 'page_loaded':
                    return 'Page loaded';
                case 'tab_switch':
                    return 'Tab/Window switched';
                case 'visibility_change':
                    return 'Page visibility changed';
                case 'network_request':
                    return 'Unexpected network request';
                case 'script_injection':
                    return 'Attempted script injection';
                case 'copy_paste':
                    return 'Copy/Paste detected';
                case 'keyboard_shortcut':
                    return 'Suspicious keyboard shortcut';
                default:
                    return type.split('_').map(word => 
                        word.charAt(0).toUpperCase() + word.slice(1)
                    ).join(' ');
            }
        }

        function formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }

        function toggleActivityLogs(attemptId) {
            const logsRow = document.getElementById('logs-' + attemptId);
            if (logsRow) {
                logsRow.style.display = logsRow.style.display === 'none' ? 'table-row' : 'none';
            }
        }
    </script>

</body>

</html>