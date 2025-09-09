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
    <title>Assessments</title>
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

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
        }

        .badge.status-available {
            background-color: #4CAF50;
            color: white;
        }

        .badge.status-pending {
            background-color: #FFC107;
            color: black;
        }

        .badge.status-expired {
            background-color: #9E9E9E;
            color: white;
        }

        .badge.status-overdue {
            background-color: #F44336;
            color: white;
        }

        .badge.status-completed {
            background-color: #2196F3;
            color: white;
        }

        .quiz-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 120px;
            text-decoration: none;
            display: inline-block;
        }

        .quiz-btn.take {
            background-color: #B71C1C;
            color: white;
        }

        .quiz-btn.review {
            background-color: #2196F3;
            color: white;
        }

        .quiz-btn.retake {
            background-color: #4CAF50;
            color: white;
        }

        .quiz-btn.disabled {
            background-color: #9E9E9E;
            color: white;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .quiz-btn:not(.disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .assessment-details {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin: 16px 0;
        }

        .detail-group {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .detail-group span {
            font-size: 0.9rem;
            color: #555;
        }

        .detail-group span strong {
            color: #333;
        }

        .due-date {
            font-size: 0.9rem;
            color: #B71C1C;
        }

        .assessment-actions {
            border-top: 1px solid #eee;
            padding-top: 16px;
            display: flex;
            justify-content: flex-end;
        }

        .assessment-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 4px;
            font-size: 0.9rem;
            color: #666;
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

        .nav-btn {
            display: inline-block;
            background: rgba(0, 0, 0, 0.4);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.95rem;
            backdrop-filter: blur(2px);
            transition: all 0.3s ease;
        }

        .nav-btn:hover {
            transform: scale(1.05);
            background: rgba(255, 255, 255, 0.2);
            color: black;
            transform: scale(1.05);
            text-decoration: none;
        }

        .nav-button {
            text-align: right;
            margin-bottom: 20px;
        }

        .assessment-cards {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 10px;
            z-index: 100;
        }

        .assessment-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
            padding: 20px;
            position: relative;
            z-index: 100;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .assessment-title {
            margin: 0;
            font-size: 1.25rem;
            color: #333;
        }

        .assessment-meta {
            font-size: 0.9rem;
            color: #777;
            margin-top: 4px;
        }

        .menu-container {
            position: relative;
        }

        .menu-button {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 24px;
            right: 0;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            z-index: 10;
        }

        .menu-container.show .dropdown-menu {
            display: block;
        }

        .dropdown-menu a {
            display: block;
            padding: 10px 16px;
            color: #333;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .dropdown-menu a:hover {
            background: #f0f0f0;
        }

        .card-body {
            margin-top: 12px;
        }

        .assessment-desc {
            margin: 0 0 12px;
            font-size: 0.95rem;
            color: #444;
        }

        .assessment-details {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.88rem;
            color: #555;
        }

        .menu-container .dropdown-menu {
            display: none;
            position: absolute;
            background: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
            padding: 8px 0;
            z-index: 10;
        }

        .menu-container.show .dropdown-menu {
            display: block;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .quiz-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quiz-btn.take {
            background-color: #B71C1C;
            color: white;
        }

        .quiz-btn.review {
            background-color: #2196F3;
            color: white;
        }

        .quiz-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .attempt-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 12px;
        }

        .attempt-badge.attempted {
            background-color: #4CAF50;
            color: white;
        }

        .attempt-badge.not-attempted {
            background-color: #FFC107;
            color: #000;
        }

        .due-date {
            font-size: 13px;
            color: #666;
        }

        .assessment-actions {
            border-top: 1px solid #eee;
            padding-top: 16px;
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
            <a href="assessments" class="topbar-link active">Assessments</a>     
            <a href="people" class="topbar-link">People</a>
            <a href="grades" class="topbar-link">Grades</a>
        </div>

        <!-- Header -->
        <div class="header">
            <p><strong><?= $course_code ?>:</strong> <?= $course_title ?></p>
        </div>

        <h2>Assessments</h2>

        <br>

        <div class="assessment-cards" id="assessmentContainer"></div>
        <script>
            // Helper function to escape HTML and prevent XSS
            function escapeHtml(unsafe) {
                if (typeof unsafe !== 'string') return '';
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            // Function to format dates in PH timezone
            function formatDate(dateStr) {
                if (!dateStr) return 'N/A';
                
                try {
                    const date = new Date(dateStr);
                    
                    if (isNaN(date.getTime())) {
                        return 'Invalid Date';
                    }
                    
                    return date.toLocaleString('en-US', {
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true,
                        timeZone: 'Asia/Manila'
                    }) + ' (PHT)';
                } catch (e) {
                    console.error('Date formatting error:', e);
                    return 'Date Error';
                }
            }

            // Update the loadAssessments function
            async function loadAssessments() {
                const container = document.getElementById('assessmentContainer');
                container.innerHTML = '<div class="loading">Loading assessments...</div>';

                try {
                    const res = await fetch('functions/load_assessments', {
                        credentials: 'same-origin'
                    });

                    if (!res.ok) {
                        throw new Error(`HTTP error! status: ${res.status}`);
                    }

                    const response = await res.json();

                    if (!response.success) {
                        throw new Error(response.error || 'Failed to load assessments');
                    }

                    const data = response.data;
                    container.innerHTML = '';

                    if (!Array.isArray(data)) {
                        throw new Error('Invalid data format received');
                    }

                    if (data.length === 0) {
                        container.innerHTML = '<div class="no-data">No assessments found</div>';
                        return;
                    }

                    data.forEach(assess => {
                        // Sanitize data
                        const safeAssess = {
                            ...assess,
                            title: escapeHtml(assess.title || ''),
                            type: escapeHtml(assess.type || 'practice')
                        };

                        const card = document.createElement('div');
                        card.className = 'assessment-card';

                        const postedAt = formatDate(safeAssess.created_at);
                        const timeLimit = safeAssess.time_limit ? `${safeAssess.time_limit} minutes` : 'None';
                        
                        // Get attempts information
                        const attempts = safeAssess.student_attempts || { 
                            count: 0, 
                            max_attempts: 1, 
                            has_attempts: false, 
                            can_retake: true 
                        };
                        
                        const attemptsText = `${attempts.count}/${attempts.max_attempts}`;
                        const { statusBadgeClass, statusText, buttonClass, buttonText } = getAssessmentStatus(safeAssess);
                        const { scoreText, attemptBadgeClass, attemptText, lastAttempt, timeUsed } = getScoreDisplay(attempts);

                        // Build score display section
                        let scoreSection = '';
                        if (attempts.has_attempts && attempts.display_attempt) {
                            scoreSection = `
                                <div class="detail-group">
                                    <span><strong>Score:</strong> ${scoreText}</span>
                                    <span><strong>Last Attempt:</strong> ${lastAttempt}</span>
                                    <span><strong>Time Used:</strong> ${timeUsed}</span>
                                </div>`;
                        }

                        card.innerHTML = `
                            <div class="card-header">
                                <div>
                                    <h3 class="assessment-title">${safeAssess.title}</h3>
                                    <p class="assessment-meta">
                                        <span class="badge ${statusBadgeClass}">${statusText}</span>
                                        <span class="attempt-badge ${attemptBadgeClass}">${attemptText}</span>
                                        Posted on: ${postedAt}
                                    </p>
                                </div>
                            </div>
                            <div class="card-body">
                                ${scoreSection}
                                <div class="assessment-details">
                                    <div class="detail-group">
                                        <span><strong>Type:</strong> ${safeAssess.quiz_type || 'Quiz'}</span>
                                        <span><strong>Time Limit:</strong> ${timeLimit}</span>
                                        <span><strong>Questions:</strong> ${
                                            (() => {
                                                try {
                                                    const questions = typeof safeAssess.questions === 'string' 
                                                        ? JSON.parse(safeAssess.questions) 
                                                        : safeAssess.questions;
                                                    return Array.isArray(questions) ? questions.length : 0;
                                                } catch (e) {
                                                    return 0;
                                                }
                                            })()
                                        }</span>
                                    </div>
                                    <div class="detail-group">
                                        <span><strong>Attempts:</strong> ${attemptsText}</span>
                                        <span><strong>Score to Keep:</strong> ${safeAssess.score_to_keep || 'Highest'}</span>
                                    </div>
                                    ${safeAssess.availability.dueDate ? `
                                    <div class="detail-group">
                                        <span class="due-date"><strong>Due:</strong> ${formatDate(safeAssess.availability.dueDate)}</span>
                                    </div>` : ''}
                                </div>
                                <div class="assessment-actions">
                                    ${buttonClass === 'disabled' ? 
                                        `<button class="quiz-btn disabled" disabled>${buttonText}</button>` :
                                        `<a href="take-quiz.php?id=${encodeURIComponent(safeAssess.id)}" 
                                        class="quiz-btn ${buttonClass}">
                                            ${buttonText}
                                        </a>`
                                    }
                                </div>
                            </div>
                        `;

                        container.appendChild(card);
                    });

                } catch (error) {
                    console.error('Error loading assessments:', error);
                    container.innerHTML = `
                        <div class="error">
                            Failed to load assessments: ${error.message}
                            <button onclick="loadAssessments()" class="retry-btn">Retry</button>
                        </div>`;
                }
            }


            // Helper function to escape HTML and prevent XSS
            function escapeHtml(unsafe) {
                if (typeof unsafe !== 'string') return '';
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            // Function to get status badge and button info
            function getAssessmentStatus(assess) {
                const attempts = assess.student_attempts;
                let statusBadgeClass = 'status-available';
                let statusText = 'Available';
                let buttonClass = 'take';
                let buttonText = 'Take Quiz';

                // Check if student has completed all attempts
                if (attempts.has_attempts && !attempts.can_retake) {
                    statusBadgeClass = 'status-completed';
                    statusText = 'Completed';
                    buttonClass = 'review';
                    buttonText = 'Review';
                } else {
                    // Check availability status
                    switch(assess.availability.status) {
                        case 'pending':
                            statusBadgeClass = 'status-pending';
                            statusText = 'Not Yet Available';
                            buttonClass = 'disabled';
                            buttonText = 'Not Available';
                            break;
                        case 'expired':
                            statusBadgeClass = 'status-expired';
                            statusText = 'Expired';
                            buttonClass = 'disabled';
                            buttonText = 'Expired';
                            break;
                        case 'overdue':
                            statusBadgeClass = 'status-overdue';
                            statusText = 'Overdue';
                            buttonClass = attempts.has_attempts ? 'retake' : 'take';
                            buttonText = attempts.has_attempts ? 'Retake Quiz' : 'Take Quiz';
                            break;
                        case 'completed':
                            statusBadgeClass = 'status-completed';
                            statusText = 'Completed';
                            buttonClass = 'review';
                            buttonText = 'Review';
                            break;
                        default:
                            statusBadgeClass = 'status-available';
                            statusText = 'Available';
                            buttonClass = attempts.has_attempts ? 'retake' : 'take';
                            buttonText = attempts.has_attempts ? 'Retake Quiz' : 'Take Quiz';
                    }
                }

                return { statusBadgeClass, statusText, buttonClass, buttonText };
            }

            // Function to get score display
            function getScoreDisplay(attempts) {
                if (!attempts.has_attempts || !attempts.display_attempt) {
                    return { scoreText: '', attemptBadgeClass: 'not-attempted', attemptText: 'Not Attempted' };
                }

                const attempt = attempts.display_attempt;
                const percentage = attempt.percentage || 0;
                const scoreText = `${attempt.score}/${attempt.totalPoints} (${percentage}%)`;
                
                return {
                    scoreText: scoreText,
                    attemptBadgeClass: 'attempted',
                    attemptText: 'Attempted',
                    lastAttempt: formatDate(attempt.submittedAt),
                    timeUsed: formatTimeUsed(attempt.timeUsed)
                };
            }

            // Function to format time used
            function formatTimeUsed(seconds) {
                if (!seconds) return 'N/A';
                
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = seconds % 60;
                
                if (hours > 0) {
                    return `${hours}h ${minutes}m ${secs}s`;
                } else {
                    return `${minutes}m ${secs}s`;
                }
            }
            

            // Optional: Add a notification system
            function showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.textContent = message;
                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }

                    // Initialize on page load
            window.addEventListener('DOMContentLoaded', loadAssessments);
        </script>

        <style>
            .loading, .error, .no-data {
                text-align: center;
                padding: 20px;
                background: white;
                border-radius: 8px;
                margin: 20px 0;
            }
            
            .error {
                color: #721c24;
                background-color: #f8d7da;
                border: 1px solid #f5c6cb;
            }
            
            .retry-btn {
                margin-left: 10px;
                padding: 5px 10px;
                border: none;
                border-radius: 4px;
                background: #dc3545;
                color: white;
                cursor: pointer;
            }
        </style>

    </div>



</body>

</html>