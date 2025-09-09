<?php
session_start();

// Store session details into variables
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$email_address = $_SESSION['email_address'];
$role = $_SESSION['role'];

// Redirect if not logged in or not a teacher
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'student') {
    header("Location: /login?error=Access+denied");
    exit;
}

require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

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
        .gradebook-table th.hovered,
        .gradebook-table td.hovered {
            background-color: #f9f9f9;
        }
        .gradebook-table tr:hover {
            background-color: #f9f9f9;
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
            <a href="msg" target="_blank">Chat</a>
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
            <a href="people" class="topbar-link">People</a>
            <a href="grades" class="topbar-link active">Grades</a>
        </div>

        <!-- Header -->
        <div class="header">
            <p><strong><?php echo $course_code; ?>:</strong> <?php echo $course_title; ?></p>
        </div>
        

        <h2>Grades</h2>

        <br>

        <?php if (!$course_id): ?>
        <div class="gradebook-card">
            <div class="gradebook-header">
                <h3>No Course Selected</h3>
            </div>
            <div style="padding: 20px; text-align: center;">
                <p>Please select a course from your dashboard to view your grades.</p>
            </div>
        </div>
        <?php return; endif; ?>

        <?php
        // Function to get quiz details from quiz file
        function getQuizDetails($quizFile) {
            if (file_exists($quizFile)) {
                $quizData = json_decode(file_get_contents($quizFile), true);
                return $quizData;
            }
            return null;
        }

        // Function to get student attempts for a quiz
        function getStudentAttempts($quizId, $studentId) {
            $attemptsDir = __DIR__ . "/../assessment-list/quizzes/attempts/";
            $attempts = [];
            
            if (is_dir($attemptsDir)) {
                // Get the quiz name from the quiz ID
                $quizFile = glob(__DIR__ . "/../assessment-list/quizzes/" . $quizId . "*.json");
                if (!empty($quizFile)) {
                    $quizBaseName = basename($quizFile[0], '.json');
                    // Look for attempts with the quiz name and student ID at the end
                    $files = glob($attemptsDir . $quizBaseName . "_" . $studentId . ".json");
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            $fileContent = file_get_contents($file);
                            $attemptData = json_decode($fileContent, true);
                            if ($attemptData) {
                                // If the data is an array (multiple attempts), add them all
                                if (is_array($attemptData) && isset($attemptData[0])) {
                                    foreach ($attemptData as $attempt) {
                                        $attempts[] = $attempt;
                                    }
                                } else {
                                    // Single attempt
                                    $attempts[] = $attemptData;
                                }
                            }
                        }
                    }
                }
            }
            
            return $attempts;
        }

                    

        // Get all quiz files for the current course
        $quizFiles = glob(__DIR__ . "/../assessment-list/quizzes/quiz_*.json");
        $quizzes = [];

        // Process each quiz file
        foreach ($quizFiles as $quizFile) {
            $quizData = getQuizDetails($quizFile);
            // Only include quizzes from the current course
            if ($quizData && isset($quizData['courseId']) && $quizData['courseId'] == $course_id) {
                $quizId = basename($quizFile, '.json');
                // Extract just the numeric ID from the quiz filename
                preg_match('/quiz_(\d+)_/', $quizId, $matches);
                $numericId = isset($matches[1]) ? $matches[1] : $quizId;
                
                $attempts = getStudentAttempts($quizId, $user_id);
                $quizzes[] = [
                    'quiz' => $quizData,
                    'attempts' => $attempts
                ];
            }
        }

        // Sort quizzes by date (most recent first)
        usort($quizzes, function($a, $b) {
            $aDate = strtotime($a['quiz']['lastModified']);
            $bDate = strtotime($b['quiz']['lastModified']);
            return $bDate - $aDate;
        });
        ?>

        <div class="gradebook-card">
            <div class="gradebook-header">
                <h3>Assessment Grades</h3>
            </div>
            <div class="gradebook-table-wrapper">
                <table class="gradebook-table">
                    <thead>
                        <tr>
                            <th>Assessment Name</th>
                            <th>Attempt</th>
                            <th>Score</th>
                            <th>Date Taken</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($quizzes)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No assessment attempts found.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($quizzes as $quiz): ?>
                                <?php 
                                $attempts = $quiz['attempts'];
                                $rowspan = count($attempts);
                                $first = true;
                                foreach ($attempts as $index => $attempt):
                                    $score = isset($attempt['score']) ? $attempt['score'] : 'N/A';
                                    $total = isset($attempt['total_points']) ? $attempt['total_points'] : 'N/A';
                                    $date = isset($attempt['timestamp']) ? date('M d, Y h:i A', strtotime($attempt['timestamp'])) : 'N/A';
                                    $status = isset($attempt['status']) ? ucfirst($attempt['status']) : 'Completed';
                                ?>
                                <tr>
                                    <?php if ($first): ?>
                                    <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($quiz['quiz']['title']); ?></td>
                                    <?php endif; ?>
                                    <td>Attempt <?php echo $index + 1; ?></td>
                                    <td>
                                        <?php
                                        $score = isset($attempt['score']) ? $attempt['score'] : 0;
                                        $totalPoints = isset($attempt['totalPoints']) ? $attempt['totalPoints'] : 0;
                                        $percentage = ($totalPoints > 0) ? (($score / $totalPoints) * 100) : 0;
                                        $originalScore = isset($attempt['originalScore']) ? $attempt['originalScore'] : $score;
                                        
                                        if ($originalScore != $score) {
                                            echo '<span style="text-decoration: line-through;">' . $originalScore . '</span> ';
                                        }
                                        echo $score . ' / ' . $totalPoints . ' (' . number_format($percentage, 2) . '%)';
                                        
                                        if (isset($attempt['securityPenalty']) && $attempt['securityPenalty'] > 0) {
                                            echo '<br><small class="text-danger">(Security penalty: -' . $attempt['securityPenalty'] . ' points)</small>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php 
                                        if (isset($attempt['submittedAt'])) {
                                            $timestamp = strtotime($attempt['submittedAt']);
                                            echo date('F j, Y \a\t g:i A (T)', $timestamp);
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?></td>
                                    <td><?php
                                        $status = 'Completed';
                                        if (isset($attempt['isTimeout']) && $attempt['isTimeout']) {
                                            $status = 'Time Out';
                                        }
                                        if (isset($attempt['isSecurityBreach']) && $attempt['isSecurityBreach']) {
                                            $status = '<span class="text-danger">Security Warning</span>';
                                        }
                                        echo $status;
                                    ?></td>
                                </tr>
                                <?php 
                                $first = false;
                                endforeach; 
                                ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <style>
        .text-danger {
            color: #dc3545;
            font-size: 0.85em;
        }
        </style>


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
        });
    </script>



</body>

</html>