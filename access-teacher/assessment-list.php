<?php
session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/access-teacher/functions/history_logger.php');

logUserHistory("visited", "Assessments"); // You can customize this per page

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
    <title>Assessments</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 24px;
            margin-top: 20px;
            z-index: 100;
        }

        .assessment-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            padding: 24px;
            position: relative;
            z-index: 100;
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .assessment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.1);
            border-color: #e0e0e0;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 16px;
            border-bottom: 1px solid #f5f5f5;
            margin-bottom: 16px;
        }

        .assessment-title {
            margin: 0;
            font-size: 1.35rem;
            color: #2c3e50;
            font-weight: 600;
            line-height: 1.3;
        }

        .assessment-meta {
            font-size: 0.9rem;
            color: #94a3b8;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .assessment-meta i {
            font-size: 0.85rem;
        }

        .menu-container {
            position: relative;
        }

        .menu-button {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 1.25rem;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            transition: all 0.2s ease;
        }

        .menu-button:hover {
            background: #f1f5f9;
            color: #334155;
            border-color: #cbd5e1;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            z-index: 10;
            min-width: 180px;
            padding: 8px;
        }

        .menu-container.show .dropdown-menu {
            display: block;
            animation: menuFadeIn 0.2s ease;
        }

        @keyframes menuFadeIn {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-menu .menu-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            color: #475569;
            text-decoration: none;
            font-size: 0.875rem;
            border-radius: 6px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .dropdown-menu .menu-item:hover {
            background: #f8fafc;
            color: #1e293b;
        }

        .dropdown-menu .menu-item i {
            font-size: 1rem;
            width: 16px;
            text-align: center;
        }

        .dropdown-menu .publish-btn {
            color: #0284c7;
        }

        .dropdown-menu .publish-btn:hover {
            background: #f0f9ff;
        }

        .dropdown-menu .delete-btn {
            color: #dc2626;
        }

        .dropdown-menu .delete-btn:hover {
            background: #fef2f2;
        }

        .card-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
        }

        .view-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: #0ea5e9;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .view-btn:hover {
            background: #0284c7;
            transform: translateY(-1px);
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
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
        }

        .detail-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .detail-value {
            font-size: 0.95rem;
            color: #1e293b;
            font-weight: 500;
        }

        .assessment-desc {
            color: #4b5563;
            line-height: 1.6;
            font-size: 0.95rem;
            background: #ffffff;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
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
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }

        .badge i {
            font-size: 0.8rem;
        }

        .badge-published {
            background-color: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }
        
        .badge-published:hover {
            background-color: #bbf7d0;
        }

        .badge-draft {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .badge-draft:hover {
            background-color: #fecaca;
        }
        
        .badge-saved {
            background-color: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
        }

        .badge-saved:hover {
            background-color: #bfdbfe;
        }

        .assessment-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f5f5f5;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 1rem;
            color: #1e293b;
            font-weight: 600;
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
            <a href="assessments" class="topbar-link active">Assessments</a>
            <a href="question-bank" class="topbar-link">Question Bank</a>
            <a href="history" class="topbar-link">History</a>
            <a href="people" class="topbar-link">People</a>
            <a href="grades" class="topbar-link">Grades</a>
            <a href="ilo" class="topbar-link">ILO</a>
        </div>

        <!-- Header -->
        <div class="header">
            <p><strong><?= $course_code ?>:</strong> <?= $course_title ?></p>
        </div>

        <h2>Assessments</h2>

        <div class="nav-button">
            <a href="create_assessment" class="nav-btn">Create Assessment</a>
        </div>

        <br>

        <div class="assessment-cards" id="assessmentContainer"></div>
    
        <script>
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
                            title: escapeHtml(assess.title),
                            instructions: assess.instructions || '',
                            quiz_type: escapeHtml(assess.quiz_type || 'practice')
                        };

                        const postedAt = new Date(safeAssess.created_at).toLocaleString('en-US', {
                            month: 'long',
                            day: 'numeric',
                            year: 'numeric',
                            hour: 'numeric',
                            minute: '2-digit'
                        });

                        const card = document.createElement('div');
                        card.className = 'assessment-card';
                        card.innerHTML = `
                            <div class="card-header">
                                <div>
                                    <h3 class="assessment-title">${safeAssess.title}</h3>
                                    <p class="assessment-meta">Posted on: ${postedAt}</p>
                                    <div style="display: flex; gap: 8px; margin-top: 4px;">
                                        <span class="badge ${safeAssess.is_published ? 'badge-published' : 'badge-draft'}" title="${safeAssess.is_published ? 'Quiz is visible to students' : 'Quiz is hidden from students'}">
                                            <i class="fas fa-${safeAssess.is_published ? 'eye' : 'eye-slash'}"></i>
                                            ${safeAssess.is_published ? 'Published' : 'Unpublished'}
                                        </span>
                                        ${safeAssess.assessment_type === 'saved' ? 
                                            '<span class="badge badge-saved"><i class="fas fa-save"></i> Saved Quiz</span>' : 
                                            ''}
                                    </div>
                                </div>
                                <div class="menu-container">
                                    <button class="menu-button" aria-label="Assessment options">⋮</button>
                                    <div class="dropdown-menu">
                                        ${safeAssess.assessment_type === 'saved' ? 
                                            `<a href="edit-assessment?id=${safeAssess.id}&type=saved" class="menu-item">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>` : 
                                            `<a href="edit-assessment?id=${safeAssess.id}" class="menu-item">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>`
                                        }
                                        <a href="#" class="menu-item publish-btn" data-id="${safeAssess.id}" data-status="${safeAssess.is_published ? '1' : '0'}">
                                            <i class="fas fa-${safeAssess.is_published ? 'eye-slash' : 'eye'}"></i>
                                            ${safeAssess.is_published ? 'Unpublish' : 'Publish'}
                                        </a>
                                        <a href="#" class="menu-item delete-btn" data-id="${safeAssess.id}">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="assessment-desc">
                                    ${safeAssess.instructions ? `<p style="margin-bottom: 16px; line-height: 1.5;">${safeAssess.instructions}</p>` : ''}
                                </div>
                                <div class="assessment-details">
                                    <div class="detail-group">
                                        <span class="detail-label">Type:</span>
                                        <span class="detail-value">${safeAssess.quiz_type.charAt(0).toUpperCase() + safeAssess.quiz_type.slice(1)}</span>
                                    </div>
                                    <div class="detail-group">
                                        <span class="detail-label">Time Limit:</span>
                                        <span class="detail-value">${safeAssess.time_limit ? safeAssess.time_limit + ' minutes' : 'None'}</span>
                                    </div>
                                    <div class="detail-group">
                                        <span class="detail-label">Multiple Attempts:</span>
                                        <span class="detail-value">${safeAssess.multiple_attempts ? 'Yes' : 'No'}</span>
                                    </div>
                                    <div class="detail-group">
                                        <span class="detail-label">Attempts Allowed:</span>
                                        <span class="detail-value">${safeAssess.allowed_attempts || 1}</span>
                                    </div>
                                    <div class="detail-group">
                                        <span class="detail-label">Score to Keep:</span>
                                        <span class="detail-value">${safeAssess.score_to_keep || 'Highest'}</span>
                                    </div>
                                    <div class="detail-group">
                                        <span class="detail-label">Questions:</span>
                                        <span class="detail-value">${safeAssess.questions ? safeAssess.questions.length : 0}</span>
                                    </div>
                                </div>
                                <form action="answer-student" method="GET" style="margin-top: 12px;">
                                    <input type="hidden" name="id" value="${safeAssess.id}">
                                    <button type="submit" class="view-btn">View</button>
                                </form>
                            </div>
                        `;
                        // Add delete event listener
                        const deleteBtn = card.querySelector('.delete-btn');
                        deleteBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            deleteAssessment(safeAssess.id, card);
                        });

                        // Add publish button event listener only if it exists
                        const publishBtn = card.querySelector('.publish-btn');
                        if (publishBtn) {
                            publishBtn.addEventListener('click', async (e) => {
                                e.preventDefault();
                                const assessmentId = publishBtn.getAttribute('data-id');
                                const currentStatus = publishBtn.getAttribute('data-status');
                                const newStatus = currentStatus === '1' ? 0 : 1;
                                const actionText = currentStatus === '1' ? 'unpublish' : 'publish';
                                
                                // Show confirmation dialog with clear message
                                if (!confirm(`Are you sure you want to ${actionText} this assessment?\n\n` + 
                                    (newStatus ? 
                                        '✓ Students will be able to see and take this quiz\n' + 
                                        '✓ The quiz will appear in students\' assessment list' :
                                        '⚠ Students will no longer have access to this quiz\n' + 
                                        '⚠ The quiz will be hidden from students\' assessment list'
                                    )
                                )) {
                                    return;
                                }
    
                                try {
                                    const res = await fetch('functions/publish_assessment', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json'
                                        },
                                        body: JSON.stringify({
                                            id: assessmentId,
                                            is_published: newStatus
                                        }),
                                        cache: 'no-cache' // Prevent caching
                                    });

                                    if (!res.ok) {
                                        throw new Error(`HTTP error! status: ${res.status}`);
                                    }

                                    const result = await res.json();
                                    
                                    if (result.success) {
                                        // Update button status immediately
                                        publishBtn.setAttribute('data-status', newStatus.toString());
                                        publishBtn.innerHTML = `
                                            <i class="fas fa-${newStatus ? 'eye-slash' : 'eye'}"></i>
                                            ${newStatus ? 'Unpublish' : 'Publish'}
                                        `;
                                        
                                        // Update badge
                                        const badge = card.querySelector('.badge');
                                        if (badge) {
                                            badge.className = `badge ${newStatus ? 'badge-published' : 'badge-draft'}`;
                                            badge.innerHTML = `
                                                <i class="fas fa-${newStatus ? 'eye' : 'eye-slash'}"></i>
                                                ${newStatus ? 'Published' : 'Unpublished'}
                                            `;
                                            badge.title = newStatus ? 'Quiz is visible to students' : 'Quiz is hidden from students';
                                        }

                                        showNotification(
                                            `Assessment ${newStatus ? 'published successfully. Students can now access this quiz.' : 'unpublished successfully. Students can no longer access this quiz.'}`,
                                            'success'
                                        );
                                    } else {
                                        throw new Error(result.error || 'Failed to update publish status');
                                    }
                                } catch (error) {
                                    console.error('Publish error:', error);
                                    showNotification('Failed to update publish status: ' + error.message, 'error');
                                    // Reload the assessment list to ensure consistent state
                                    loadAssessments();
                                }
                            });
                        }

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

            async function deleteAssessment(id, card) {
                

                if (!confirm("Are you sure you want to delete this assessment?")) return;

                try {
                    const res = await fetch(`functions/delete_assessment?id=${id}`, {
                        method: 'GET'
                    });

                    if (!res.ok) {
                        throw new Error(`HTTP error! status: ${res.status}`);
                    }

                    const data = await res.json();

                    if (data.success) {
                        card.remove();
                        showNotification('Assessment deleted successfully', 'success');
                    } else {
                        throw new Error(data.error || 'Failed to delete assessment');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('Failed to delete assessment', 'error');
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

            // Add necessary styles
            const styles = `
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
    
    .notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 10px 20px;
        border-radius: 4px;
        color: white;
        z-index: 1000;
    }
    
    .notification.success {
        background: #28a745;
    }
    
    .notification.error {
        background: #dc3545;
    }
`;

            // Add styles to document
            const styleSheet = document.createElement('style');
            styleSheet.textContent = styles;
            document.head.appendChild(styleSheet);

            // Initialize on page load
            window.addEventListener('DOMContentLoaded', loadAssessments);
        </script>



    </div>

    <script>
        // Event delegation for menu buttons
        document.addEventListener('click', (e) => {
            const isMenuBtn = e.target.closest('.menu-button');
            const isMenuItem = e.target.closest('.dropdown-menu');

            // If a menu button was clicked
            if (isMenuBtn) {
                e.preventDefault();
                e.stopPropagation();

                // Close all open menus first
                document.querySelectorAll('.menu-container.show').forEach(c => {
                    if (!c.contains(isMenuBtn)) c.classList.remove('show');
                });

                // Toggle this menu
                const container = isMenuBtn.closest('.menu-container');
                container.classList.toggle('show');
                return;
            }

            // If clicked outside any menu
            if (!isMenuItem) {
                document.querySelectorAll('.menu-container').forEach(c => c.classList.remove('show'));
            }
        });
    </script>



</body>

</html>