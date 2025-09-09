<?php
session_start();

echo "<script>
    window.user_id = '{$_SESSION['user_id']}';
    window.first_name = '{$_SESSION['first_name']}';
    window.last_name = '{$_SESSION['last_name']}';
    window.ann_course_id = '{$_SESSION['ann_course_id']}';
</script>";

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

// Add this after getting course_id
$ilos = [];
if ($course_id) {
    $ilo_stmt = $conn->prepare("SELECT id, ilo_number, ilo_description FROM course_ilos WHERE course_id = ? ORDER BY ilo_number");
    $ilo_stmt->bind_param("i", $course_id);
    $ilo_stmt->execute();
    $ilo_result = $ilo_stmt->get_result();
    while ($row = $ilo_result->fetch_assoc()) {
        $ilos[] = $row;
    }
}

// Add this to pass ILOs to JavaScript
echo "<script>window.COURSE_ILOS = " . json_encode($ilos) . ";</script>";

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Assessment</title>
    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/3meu9fvsi79o1afk1s1kb1s10s81u6vau3n4l4fqwh8vkjz5/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <!-- mathjs for formula evaluation -->
    <script src="https://cdn.jsdelivr.net/npm/mathjs@12.4.1/lib/browser/math.js"></script>

    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="quiz-style/styles.css">

    <script>
        window.COURSE_ID = <?= json_encode($course_id) ?>;
    </script>
    <!-- Bootstrap -->

    <style>
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

        <h2>Edit Assessment</h2>


        <div class="wrap">

            
    <style>
        .floating-new-question-btn {
            /* ...existing styles... */
        }

        .floating-new-question-btn.hide {
            display: none !important;
        }

        /* Floating New Question Button */
        .floating-new-question-btn {
            position: fixed;
            right: 32px;
            bottom: 32px;
            z-index: 2000;
            background: #0374b5;
            color: white;
            border: none;
            border-radius: 50px;
            box-shadow: 0 4px 16px rgba(3, 116, 181, 0.15);
            padding: 12px 20px;
            font-size: 14px;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s, box-shadow 0.2s;
        }

        .floating-new-question-btn:hover {
            background: #025a8c;
            box-shadow: 0 6px 24px rgba(3, 116, 181, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Lato", "Helvetica Neue", Helvetica, Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            font-size: 14px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
        }

        .tabs {
            display: flex;
            background: #e6eaed;
            border-bottom: 1px solid #c7cdd1;
        }

        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            color: #555;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            font-family: inherit;
        }

        .tab:hover {
            background: #d1d7da;
        }

        .tab.active {
            background: white;
            color: #0374b5;
            border-bottom-color: #0374b5;
            font-weight: 500;
        }

        .tab-content {
            display: none;
            padding: 0;
        }

        .tab-content.active {
            display: block;
        }

        .content-wrapper {
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .details-title {
            font-size: 1.8rem;
            color: #2d3b47;
            font-weight: 500;
            margin-bottom: 32px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e6eaed;
        }

        /* Question Bank Styling */
        .question-bank {
            border: 1px solid #c7cdd1;
            border-radius: 4px;
            margin-bottom: 24px;
        }

        .question-bank-header {
            background: #f8f9fa;
            border-bottom: 1px solid #c7cdd1;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .question-bank-title {
            font-size: 16px;
            font-weight: 500;
            color: #2d3b47;
        }

        .add-question-dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-btn {
            background: #0374b5;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
        }

        .dropdown-btn:hover {
            background: #025a8c;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            min-width: 200px;
            max-width: 220px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid #c7cdd1;
            border-radius: 4px;
            z-index: 1000;
            margin-top: 4px;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-item {
            display: block;
            padding: 8px 12px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item-title {
            font-weight: 500;
            margin-bottom: 2px;
        }

        .dropdown-item-desc {
            font-size: 12px;
            color: #666;
        }

        /* Question Item Styling */
        .question-item {
            border: 1px solid #c7cdd1;
            border-radius: 4px;
            margin-bottom: 16px;
            background: white;
            transition: all 0.2s;
        }

        .question-item:hover {
            border-color: #0374b5;
            box-shadow: 0 2px 8px rgba(3, 116, 181, 0.1);
        }

        .question-item.editing {
            border-color: #0374b5;
            box-shadow: 0 0 0 2px rgba(3, 116, 181, 0.1);
        }

        .question-header {
            background: #f8f9fa;
            padding: 12px 16px;
            border-bottom: 1px solid #c7cdd1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .question-header:hover {
            background: #e9ecef;
        }

        .question-title-section {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .question-type-badge {
            background: #e3f2fd;
            color: #0277bd;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .question-type-badge.multiple-choice {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .question-type-badge.fill-blank {
            background: #fff3e0;
            color: #f57c00;
        }

        .question-type-badge.formula {
            background: #fce4ec;
            color: #c2185b;
        }

        .question-summary {
            color: #555;
            font-weight: 400;
        }

        .question-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .points-input {
            width: 50px;
            padding: 4px 6px;
            border: 1px solid #c7cdd1;
            border-radius: 3px;
            font-size: 12px;
            text-align: center;
        }

        .question-content {
            display: none;
            padding: 20px;
            border-top: 1px solid #e9ecef;
            background: #fafafa;
        }

        .question-content.expanded {
            display: block;
        }

        /* Form Styling */
        .form-row {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-col {
            flex: 1;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2d3b47;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #c7cdd1;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s ease;
            background-color: #ffffff;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
            max-width: 100%;
            box-sizing: border-box;
        }

        .datetime-group {
            display: flex;
            gap: 6px;
            margin-top: 4px;
            flex-wrap: wrap;
            font-size: 12px;
        }

        .datetime-group input[type="date"],
        .datetime-group input[type="time"] {
            flex: none;
            min-width: 110px;
            font-size: 12px;
            padding: 4px 8px;
        }

        .datetime-group input[type="date"] {
            width: 120px;
        }

        .datetime-group input[type="time"] {
            width: 90px;
        }

        .form-control:focus {
            outline: none;
            border-color: #0374b5;
            box-shadow: 0 0 0 3px rgba(3, 116, 181, 0.1);
        }

        .form-control.large {
            min-height: 100px;
            resize: vertical;
        }

        /* Rich Text Editor */
        .rich-editor {
            border: 1px solid #c7cdd1;
            border-radius: 6px;
            background: white;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .rich-editor-toolbar {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 8px 12px;
            display: flex;
            gap: 4px;
            border-top-left-radius: 6px;
            border-top-right-radius: 6px;
            flex-wrap: wrap;
            align-items: center;
        }

        .toolbar-btn {
            background: none;
            border: 1px solid transparent;
            padding: 6px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            color: #444;
            transition: all 0.2s ease;
            min-width: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toolbar-btn:hover {
            background: #edf2f7;
            border-color: #c7cdd1;
        }

        .toolbar-btn.active {
            background: #e2e8f0;
            border-color: #a0aec0;
            color: #2d3748;
        }

        .toolbar-separator {
            width: 1px;
            height: 24px;
            background: #e2e8f0;
            margin: 0 4px;
        }

        .toolbar-select {
            padding: 4px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 13px;
            color: #444;
            background: white;
            cursor: pointer;
        }

        .toolbar-select:hover {
            border-color: #c7cdd1;
        }

        /* Symbol Picker */
        .symbol-picker {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #c7cdd1;
            border-radius: 6px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            padding: 12px;
            margin-top: 4px;
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
        }

        .symbol-section {
            margin-bottom: 16px;
        }

        .symbol-category {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
        }

        .symbol-section button {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            margin: 2px;
            font-size: 14px;
            min-width: 32px;
        }

        .symbol-section button:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
        }

        /* Table Creation Dialog */
        .table-creator {
            position: absolute;
            background: white;
            border: 1px solid #c7cdd1;
            border-radius: 6px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 12px;
            z-index: 1000;
        }

        .table-grid {
            display: grid;
            grid-template-columns: repeat(8, 24px);
            gap: 2px;
            margin-bottom: 8px;
        }

        .table-cell {
            width: 24px;
            height: 24px;
            border: 1px solid #e2e8f0;
            background: white;
            cursor: pointer;
        }

        .table-cell:hover {
            background: #ebf8ff;
            border-color: #4299e1;
        }

        /* Math Equation Editor */
        .equation-editor {
            position: absolute;
            background: white;
            border: 1px solid #c7cdd1;
            border-radius: 6px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 16px;
            z-index: 1000;
            width: 400px;
        }

        .equation-preview {
            margin-top: 8px;
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            min-height: 40px;
            background: #f8fafc;
        }

        .toolbar-btn:hover {
            background: #e9ecef;
            border-color: #c7cdd1;
        }

        .rich-editor-content {
            padding: 12px;
            min-height: 80px;
            outline: none;
            position: relative;
            cursor: text;
        }

        .rich-editor-content.focused {
            background-color: #ffffff;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .rich-editor-content[placeholder]:empty:before {
            content: attr(placeholder);
            color: #999;
            pointer-events: none;
            position: absolute;
            left: 12px;
            top: 12px;
        }

        .rich-editor-content img {
            max-width: 100%;
            height: auto;
        }

        .rich-editor-content table {
            border-collapse: collapse;
            margin: 8px 0;
        }

        .rich-editor-content table td {
            border: 1px solid #ccc;
            padding: 8px;
            min-width: 50px;
        }

        /* Answer Choices */
        .answer-choices {
            border: 1px solid #e9ecef;
            border-radius: 4px;
            background: white;
        }

        .choice-item {
            display: flex;
            align-items: flex-start;
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            gap: 12px;
        }

        .choice-item:last-child {
            border-bottom: none;
        }

        .choice-item.correct {
            background: #f0f8f0;
            border-left: 3px solid #4caf50;
        }

        .choice-marker {
            width: 20px;
            height: 20px;
            border: 2px solid #c7cdd1;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin-top: 2px;
            flex-shrink: 0;
            position: relative;
            transition: all 0.2s ease;
        }

        .choice-marker::before {
            content: '';
            width: 12px;
            height: 12px;
            transition: all 0.2s ease;
        }

        /* Style for single answer mode */
        .question-item:not([data-multiple-answer="true"]) .choice-marker {
            border-radius: 50%;
        }

        .question-item:not([data-multiple-answer="true"]) .choice-marker::before {
            border-radius: 50%;
        }

        .question-item:not([data-multiple-answer="true"]) .choice-marker.correct::before {
            background: #4caf50;
        }

        /* Style for multiple answer mode */
        .question-item[data-multiple-answer="true"] .choice-marker {
            border-radius: 4px;
        }

        .question-item[data-multiple-answer="true"] .choice-marker::before {
            content: '✓';
            opacity: 0;
            color: white;
            font-size: 14px;
            line-height: 1;
        }

        .question-item[data-multiple-answer="true"] .choice-marker.correct {
            background: #4caf50;
            border-color: #4caf50;
        }

        .question-item[data-multiple-answer="true"] .choice-marker.correct::before {
            opacity: 1;
        }

        .choice-marker.correct {
            border-color: #4caf50;
            background: #4caf50;
            color: white;
        }

        .choice-marker::after {
            content: '';
            position: absolute;
            transition: all 0.2s ease;
        }

        .choice-marker.radio::after {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0;
        }

        .choice-marker.checkbox::after {
            width: 6px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg) scale(0);
            opacity: 0;
            top: 2px;
        }

        .choice-marker.radio.correct::after {
            opacity: 1;
            background: white;
        }

        .choice-marker.checkbox.correct::after {
            opacity: 1;
            transform: rotate(45deg) scale(1);
        }

        .choice-content {
            flex: 1;
        }

        .choice-text {
            width: 100%;
            border: none;
            background: transparent;
            font-size: 14px;
            font-family: inherit;
            resize: none;
            outline: none;
            min-height: 20px;
        }

        .choice-actions {
            display: flex;
            gap: 4px;
            margin-left: auto;
        }

        .icon-btn {
            background: none;
            border: none;
            padding: 4px;
            cursor: pointer;
            border-radius: 3px;
            color: #666;
        }

        .icon-btn:hover {
            background: #f0f0f0;
            color: #333;
        }

        .icon-btn.danger:hover {
            background: #fee;
            color: #d32f2f;
        }

        /* Formula Question Styling */
        .formula-section {
            background: #f8f9fb;
            border: 1px solid #e1e5f2;
            border-radius: 4px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .variable-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .variable-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: white;
            border: 1px solid #e1e5f2;
            border-radius: 4px;
        }

        .variable-name {
            font-weight: 500;
            min-width: 30px;
            color: #2d3b47;
        }

        .range-group {
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 120px;
        }

        .range-inputs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 8px;
            flex: 1;
        }

        .range-input {
            width: 80px;
            padding: 4px 6px;
            border: 1px solid #c7cdd1;
            border-radius: 3px;
            font-size: 13px;
            text-align: right;
        }

        /* Fill in the Blank Styling */
        .blank-template {
            background: #f8fffe;
            border: 1px solid #b2dfdb;
            border-radius: 4px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .blank-indicator {
            display: inline-block;
            background: #e0f2f1;
            border: 2px dashed #26a69a;
            border-radius: 4px;
            padding: 2px 12px;
            margin: 0 2px;
            font-weight: 500;
            color: #00695c;
            font-size: 12px;
        }

        .blank-answers {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .blank-answer-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }

        .blank-number {
            background: #26a69a;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 500;
            flex-shrink: 0;
        }

        /* Button Styling */
        .btn {
            padding: 8px 16px;
            border: 1px solid #c7cdd1;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn:hover {
            background: #f8f9fa;
        }

        .btn-primary {
            background: #0374b5;
            color: white;
            border-color: #0374b5;
        }

        .btn-primary:hover {
            background: #025a8c;
        }

        .btn-success {
            background: #00ac18;
            color: white;
            border-color: #00ac18;
        }

        .btn-outline {
            background: transparent;
            color: #0374b5;
            border-color: #0374b5;
        }

        .btn-outline:hover {
            background: #0374b5;
            color: white;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }

        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }

        .form-row {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            width: 100%;
        }

        .settings-datetime-container {
            margin-left: 24px;
            margin-top: 8px;
            background: #ffffff;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #e6eaed;
        }

        .settings-datetime-group {
            margin-bottom: 10px;
            font-size: 12px;
        }

        .settings-datetime-group:last-child {
            margin-bottom: 0;
        }

        .settings-datetime-label {
            font-size: 12px;
            color: #2d3b47;
            margin-bottom: 2px;
            display: block;
            font-weight: normal;
        }

        .settings-section {
            background: #fafafa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 28px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            height: fit-content;
            overflow: visible;
            min-width: 320px;
        }

        .settings-section h3 {
            color: #2d3b47;
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e6eaed;
            letter-spacing: 0.5px;
        }

        /* Preview Styling */
        .preview-quiz {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            margin-bottom: 24px;
        }

        .preview-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .preview-title {
            font-size: 24px;
            font-weight: 400;
            color: #2d3b47;
            margin-bottom: 8px;
        }

        .quiz-meta {
            display: flex;
            gap: 24px;
            font-size: 13px;
            color: #666;
        }

        .preview-question {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .preview-question:last-child {
            border-bottom: none;
        }

        .preview-q-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .preview-q-number {
            font-weight: 500;
            color: #2d3b47;
        }

        .preview-points {
            font-size: 12px;
            color: #666;
        }

        /* Utility Classes */
        .text-muted {
            color: #666;
        }

        .text-sm {
            font-size: 12px;
        }

        .mb-2 {
            margin-bottom: 8px;
        }

        .mb-3 {
            margin-bottom: 12px;
        }

        .mb-4 {
            margin-bottom: 16px;
        }

        .flex {
            display: flex;
        }

        .justify-between {
            justify-content: space-between;
        }

        .items-center {
            align-items: center;
        }

        .gap-2 {
            gap: 8px;
        }

        .gap-3 {
            gap: 12px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 0;
            width: 90%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 500;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 0 0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .close-button {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            color: #666;
        }

        /* Question Bank Modal Styles */
        .question-bank-item {
            padding: 16px;
            border-bottom: 1px solid #eee;
            transition: background 0.2s ease;
        }

        .question-bank-item:hover {
            background: #f8f9fa;
        }

        .question-bank-label {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            cursor: pointer;
            width: 100%;
        }

        .question-bank-content {
            flex: 1;
        }

        .question-bank-title {
            font-weight: 500;
            margin-bottom: 4px;
            color: #2d3748;
        }

        .question-bank-meta {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }

        .question-preview {
            font-size: 14px;
            color: #4a5568;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #e9ecef;
            margin-top: 8px;
        }

        .empty-state, .error-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .url-suggestions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }

        .url-type-btn {
            padding: 8px 12px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }

        .url-type-btn:hover {
            background-color: #e9ecef;
            border-color: #dde2e6;
        }

        /* Table Modal Styles */
        .table-grid-selector {
            margin-top: 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 16px;
            background: #f8f9fa;
        }

        .grid-size-display {
            text-align: center;
            margin-bottom: 12px;
            color: #495057;
            font-size: 14px;
        }

        .grid-container {
            display: inline-grid;
            grid-template-columns: repeat(10, 24px);
            gap: 2px;
            background: white;
            padding: 4px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }

        .grid-cell {
            width: 24px;
            height: 24px;
            border: 1px solid #e9ecef;
            background: white;
            transition: all 0.15s ease;
        }

        .grid-cell:hover {
            border-color: #0374b5;
            background-color: #e3f2fd;
        }

        .grid-cell.active {
            background-color: #0374b5;
            border-color: #0374b5;
        }

        /* Preview Inputs */
        .preview-question input[type="text"] {
            width: 200px;
            max-width: 100%;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .form-row {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>

    <div class="container">
        <button class="floating-new-question-btn" id="floatingNewQuestionBtn" onclick="toggleFloatingDropdown(event)">
            <span style="font-size: 22px;">＋</span> New Question
        </button>
       

        <div class="tabs">
            <button class="tab" onclick="showTab('settings')">Settings</button>
            <button class="tab active" onclick="showTab('questions')">Questions</button>
            <button class="tab" onclick="showTab('preview')">Preview</button>
        </div>

        <!-- Quiz Settings Tab -->
        <div id="settings" class="tab-content">
            <div class="content-wrapper">
                <h2 class="details-title">Quiz Settings</h2>
                <div class="settings-grid">
                    <div class="settings-section">
                        <h3>Basic Information</h3>
                        <input type="hidden" id="quizId" value="">
                        <div class="form-group">
                            <label class="form-label" for="quizTitle">Quiz Title <span style="color: #e74c3c;">*</span></label>
                            <input type="text" id="quizTitle" class="form-control" placeholder="Enter quiz title" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="quizType">Quiz Type <span style="color: #e74c3c;">*</span></label>
                            <select id="quizType" class="form-control" required>
                                <option value="practice">Practice</option>
                                <option value="graded">Graded</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="quizDescription">Description</label>
                            <div class="rich-editor">
                                <div class="rich-editor-toolbar">
                                    <!-- Text Style -->
                                    <button class="toolbar-btn" title="Bold" onclick="execCommand('bold')"><strong>B</strong></button>
                                    <button class="toolbar-btn" title="Italic" onclick="execCommand('italic')"><em>I</em></button>
                                    <button class="toolbar-btn" title="Underline" onclick="execCommand('underline')"><u>U</u></button>
                                    <button class="toolbar-btn" title="Strikethrough" onclick="execCommand('strikethrough')"><s>S</s></button>
                                    <span class="toolbar-separator"></span>

                                    <!-- Text Scripts -->
                                    <button class="toolbar-btn" title="Subscript" onclick="execCommand('subscript')">X₂</button>
                                    <button class="toolbar-btn" title="Superscript" onclick="execCommand('superscript')">X²</button>
                                    <span class="toolbar-separator"></span>

                                    <!-- Lists -->
                                    <button class="toolbar-btn" title="Bullet List" onclick="execCommand('insertunorderedlist')">•</button>
                                    <button class="toolbar-btn" title="Numbered List" onclick="execCommand('insertorderedlist')">1.</button>
                                    <button class="toolbar-btn" title="Decrease Indent" onclick="execCommand('outdent')">←</button>
                                    <button class="toolbar-btn" title="Increase Indent" onclick="execCommand('indent')">→</button>
                                    <span class="toolbar-separator"></span>

                                    <span class="toolbar-separator"></span>

                                    <!-- Insert -->
                                    <button class="toolbar-btn" title="Insert Link" onclick="insertLink()">🔗</button>
                                    <button class="toolbar-btn" title="Insert Image" onclick="insertDescriptionImage()">🖼️</button>
                                    <button class="toolbar-btn" title="Insert Table" onclick="insertTable()">📊</button>
                                    <button class="toolbar-btn" title="Insert Symbol" onclick="showSymbolPicker()">Ω</button>
                                </div>
                                <div class="rich-editor-content" contenteditable="true" id="quizDescription" placeholder="Enter quiz instructions or description..."></div>
                                
                                <!-- Description Image Modal Dialog -->
                                <div id="descriptionImageModal" class="modal" style="display: none;">
                                    <div class="modal-content" style="max-width: 500px;">
                                        <div class="modal-header" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 1rem;">
                                            <h3 style="margin: 0; font-size: 1.25rem; color: #212529;">Insert Image</h3>
                                            <button onclick="closeDescriptionImageModal()" class="close-button" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; padding: 0; color: #6c757d;">&times;</button>
                                        </div>
                                        <div class="modal-body" style="padding: 1.5rem;">
                                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Image URL</label>
                                                <input type="text" class="form-control" id="descImageUrl" 
                                                    placeholder="Enter image URL (e.g., https://example.com/image.jpg)"
                                                    style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">
                                            </div>
                                            <div class="text-center" style="margin: 1.5rem 0; position: relative;">
                                                <span style="background: #fff; padding: 0 1rem; color: #6c757d; position: relative; z-index: 1;">OR</span>
                                                <hr style="margin: -0.75rem 0 0 0; border: 0; border-top: 1px solid #dee2e6;">
                                            </div>
                                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Upload Image</label>
                                                <input type="file" class="form-control" id="descImageFile" accept="image/*"
                                                    style="width: 100%; padding: 0.375rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">
                                            </div>
                                            <div class="image-preview" style="margin-top: 1.5rem; text-align: center; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 1rem;">
                                                <img id="descImagePreview" style="max-width: 100%; max-height: 200px; display: none; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <div id="descImagePlaceholder" style="color: #6c757d; padding: 2rem;">
                                                    Image preview will appear here
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer" style="background: #f8f9fa; border-top: 1px solid #dee2e6; padding: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                                            <button onclick="closeDescriptionImageModal()" class="btn btn-outline" 
                                                style="padding: 0.5rem 1rem; border: 1px solid #6c757d; background: none; border-radius: 4px; cursor: pointer; font-size: 14px;">Cancel</button>
                                            <button onclick="confirmDescriptionImage()" class="btn btn-primary" 
                                                style="padding: 0.5rem 1rem; background: #0d6efd; border: 1px solid #0d6efd; color: white; border-radius: 4px; cursor: pointer; font-size: 14px;">Insert Image</button>
                                        </div>
                                    </div>
                                </div>
                                
                            <!-- Link Modal Dialog -->
                            <div id="linkModal" class="modal" style="display: none;">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h3>Insert Link</h3>
                                        <button onclick="closeLinkModal()" class="close-button">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="linkText" class="form-label">Link Text</label>
                                            <input type="text" id="linkText" class="form-control" placeholder="Text to display">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Link Type</label>
                                            <div class="url-suggestions">
                                                <button onclick="selectUrlType('google')" class="url-type-btn">Google Search</button>
                                                <button onclick="selectUrlType('youtube')" class="url-type-btn">YouTube</button>
                                                <button onclick="selectUrlType('wikipedia')" class="url-type-btn">Wikipedia</button>
                                                <button onclick="selectUrlType('custom')" class="url-type-btn">Custom URL</button>
                                            </div>
                                        </div>
                                        <div id="urlInputGroup" class="form-group" style="display: none;">
                                            <label for="linkUrl" class="form-label">URL</label>
                                            <input type="text" id="linkUrl" class="form-control" placeholder="https://">
                                        </div>
                                        <div class="modal-footer">
                                            <button onclick="closeLinkModal()" class="btn btn-outline">Cancel</button>
                                            <button onclick="confirmLink()" class="btn btn-primary">Insert Link</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Table Modal Dialog -->
                            <div id="tableModal" class="modal" style="display: none;">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h3>Insert Table</h3>
                                        <button onclick="closeTableModal()" class="close-button">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label class="form-label">Table Size</label>
                                            <div class="table-grid-selector" id="tableGridSelector">
                                                <div class="grid-size-display">
                                                    <span id="gridSize">0 × 0</span> table
                                                </div>
                                                <div class="grid-container">
                                                    <!-- Grid cells will be created by JavaScript -->
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Table Style</label>
                                            <div style="display: flex; gap: 12px;">
                                                <label style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="checkbox" id="tableBordered" checked>
                                                    Show borders
                                                </label>
                                                <label style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="checkbox" id="tableStriped">
                                                    Striped rows
                                                </label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button onclick="closeTableModal()" class="btn btn-outline">Cancel</button>
                                            <button onclick="confirmTable()" class="btn btn-primary">Insert Table</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                
                                <!-- Symbol Picker Dialog -->
                                <div id="symbolPicker" class="symbol-picker" style="display: none;">
                                    <div class="symbol-picker-content">
                                        <div class="symbol-section">
                                            <div class="symbol-category">Math</div>
                                            <button onclick="insertSymbol('±')">±</button>
                                            <button onclick="insertSymbol('×')">×</button>
                                            <button onclick="insertSymbol('÷')">÷</button>
                                            <button onclick="insertSymbol('≈')">≈</button>
                                            <button onclick="insertSymbol('≠')">≠</button>
                                            <button onclick="insertSymbol('≤')">≤</button>
                                            <button onclick="insertSymbol('≥')">≥</button>
                                            <button onclick="insertSymbol('∑')">∑</button>
                                            <button onclick="insertSymbol('∏')">∏</button>
                                            <button onclick="insertSymbol('√')">√</button>
                                            <button onclick="insertSymbol('∫')">∫</button>
                                            <button onclick="insertSymbol('∞')">∞</button>
                                            <button onclick="insertSymbol('∂')">∂</button>
                                            <button onclick="insertSymbol('Δ')">Δ</button>
                                        </div>
                                        <div class="symbol-section">
                                            <div class="symbol-category">Greek</div>
                                            <button onclick="insertSymbol('α')">α</button>
                                            <button onclick="insertSymbol('β')">β</button>
                                            <button onclick="insertSymbol('γ')">γ</button>
                                            <button onclick="insertSymbol('δ')">δ</button>
                                            <button onclick="insertSymbol('ε')">ε</button>
                                            <button onclick="insertSymbol('θ')">θ</button>
                                            <button onclick="insertSymbol('λ')">λ</button>
                                            <button onclick="insertSymbol('μ')">μ</button>
                                            <button onclick="insertSymbol('π')">π</button>
                                            <button onclick="insertSymbol('σ')">σ</button>
                                            <button onclick="insertSymbol('φ')">φ</button>
                                            <button onclick="insertSymbol('Ω')">Ω</button>
                                        </div>
                                        <div class="symbol-section">
                                            <div class="symbol-category">Arrows</div>
                                            <button onclick="insertSymbol('←')">←</button>
                                            <button onclick="insertSymbol('→')">→</button>
                                            <button onclick="insertSymbol('↑')">↑</button>
                                            <button onclick="insertSymbol('↓')">↓</button>
                                            <button onclick="insertSymbol('↔')">↔</button>
                                            <button onclick="insertSymbol('⇒')">⇒</button>
                                            <button onclick="insertSymbol('⇐')">⇐</button>
                                            <button onclick="insertSymbol('⇔')">⇔</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="settings-section">
                        <h3>Quiz Options</h3>
                        <div class="form-row" style="display: flex; gap: 32px; flex-wrap: wrap; align-items: flex-start;">
                            <div class="form-col" style="min-width: 220px; flex: 1;">
                                <label class="form-label" for="timeLimit">Time Limit</label>
                                <input type="number" id="timeLimit" class="form-control" min="1" value="60" placeholder="Minutes">
                            </div>
                            <div class="form-col" style="min-width: 220px; flex: 1;">
                                <label class="form-label" for="attempts">Allowed Attempts</label>
                                <select id="attempts" class="form-control">
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                    <option value="6">6</option>
                                    <option value="7">7</option>
                                    <option value="8">8</option>
                                    <option value="9">9</option>
                                    <option value="10">10</option>
                                    <option value="-1">Unlimited</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row" style="display: flex; gap: 32px; flex-wrap: wrap; align-items: flex-start; margin-top: 16px;">
                            <div class="form-col" style="min-width: 220px; flex: 1;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="showOneQuestion"> Show One Question at a Time
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 12px;">
                                    <input type="checkbox" id="shuffleQuestions"> Shuffle the order of questions
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 12px;">
                                    <input type="checkbox" id="shuffleAnswers"> Shuffle answer choices
                                </label>
                            </div>
                            <div class="form-col" style="min-width: 220px; flex: 2;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                    <input type="checkbox" id="seeResponses"> Let Students See Their Responses
                                </label>
                                <div style="margin-left: 24px; margin-bottom: 12px;">
                                    <select id="seeResponsesTiming" class="form-control" style="width: auto; display: inline-block; font-size: 12px; padding: 4px 8px;">
                                        <option value="once">Only once after each attempt</option>
                                        <option value="always">Always</option>
                                    </select>
                                </div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="seeCorrectAnswers"> Let students see correct answers
                                </label>
                                <div class="settings-datetime-container">
                                    <div class="settings-datetime-group">
                                        <label class="settings-datetime-label">Show correct answers on</label>
                                        <div class="datetime-group">
                                            <input type="date" id="showCorrectAnswersDate" class="form-control">
                                            <input type="time" id="showCorrectAnswersTime" class="form-control">
                                        </div>
                                    </div>
                                    <div class="settings-datetime-group">
                                        <label class="settings-datetime-label">Hide correct answers on</label>
                                        <div class="datetime-group">
                                            <input type="date" id="hideCorrectAnswersDate" class="form-control">
                                            <input type="time" id="hideCorrectAnswersTime" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row" style="display: flex; gap: 32px; flex-wrap: wrap; align-items: flex-start; margin-top: 16px;">
                            <div class="form-col" style="min-width: 220px; flex: 1;">
                                <label style="font-weight: 500;">Assign</label>
                                <div class="settings-datetime-container">
                                    <div class="settings-datetime-group">
                                        <label class="settings-datetime-label">Due Date</label>
                                        <div class="datetime-group">
                                            <input type="date" id="dueDateDate" class="form-control">
                                            <input type="time" id="dueDateTime" class="form-control">
                                        </div>
                                    </div>
                                    <div class="settings-datetime-group">
                                        <label class="settings-datetime-label">Available From</label>
                                        <div class="datetime-group">
                                            <input type="date" id="availableFromDate" class="form-control">
                                            <input type="time" id="availableFromTime" class="form-control">
                                        </div>
                                    </div>
                                    <div class="settings-datetime-group">
                                        <label class="settings-datetime-label">Until</label>
                                        <div class="datetime-group">
                                            <input type="date" id="availableUntilDate" class="form-control">
                                            <input type="time" id="availableUntilTime" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Questions Tab -->
        <div id="questions" class="tab-content active">
            <div class="content-wrapper">
            <!-- Modal for Image Insert -->
            <div id="imageModal" class="modal" style="display: none;">
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 1rem;">
                        <h3 style="margin: 0; font-size: 1.25rem; color: #212529;">Insert Image</h3>
                        <button onclick="closeImageModal()" class="close-button" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; padding: 0; color: #6c757d;">&times;</button>
                    </div>
                    <div class="modal-body" style="padding: 1.5rem;">
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Image URL</label>
                            <input type="text" class="form-control" id="imageUrl" 
                                placeholder="Enter image URL (e.g., https://example.com/image.jpg)"
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div class="text-center" style="margin: 1.5rem 0; position: relative;">
                            <span style="background: #fff; padding: 0 1rem; color: #6c757d; position: relative; z-index: 1;">OR</span>
                            <hr style="margin: -0.75rem 0 0 0; border: 0; border-top: 1px solid #dee2e6;">
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Upload Image</label>
                            <input type="file" class="form-control" id="imageFile" accept="image/*"
                                style="width: 100%; padding: 0.375rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div class="image-preview" style="margin-top: 1.5rem; text-align: center; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 1rem;">
                            <img id="imagePreview" style="max-width: 100%; max-height: 200px; display: none; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div id="imagePlaceholder" style="color: #6c757d; padding: 2rem;">
                                Image preview will appear here
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="background: #f8f9fa; border-top: 1px solid #dee2e6; padding: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                        <button onclick="closeImageModal()" class="btn btn-outline" 
                            style="padding: 0.5rem 1rem; border: 1px solid #6c757d; background: none; border-radius: 4px; cursor: pointer; font-size: 14px;">Cancel</button>
                        <button onclick="confirmImage()" class="btn btn-primary" 
                            style="padding: 0.5rem 1rem; background: #0d6efd; border: 1px solid #0d6efd; color: white; border-radius: 4px; cursor: pointer; font-size: 14px;">Insert Image</button>
                    </div>
                </div>
            </div>
                <div class="question-bank">
                    <div class="question-bank-header">
                        <div class="question-bank-title">Questions</div>
                        <div class="add-question-dropdown">

                            <div class="dropdown-content" id="questionTypeDropdown">

                                <div class="dropdown-item" onclick="loadFromQuestionBank()">
                                    <div class="dropdown-item-title">Load from Question Bank</div>
                                    <div class="dropdown-item-desc">Import existing questions from your bank</div>
                                </div>
                                
                                <div class="dropdown-item" onclick="addNewQuestion('multiple_choice')">
                                    <div class="dropdown-item-title">Multiple Choice</div>
                                    <div class="dropdown-item-desc">Students can select multiple correct answers</div>
                                </div>
                                <div class="dropdown-item" onclick="addNewQuestion('fill_blank')">
                                    <div class="dropdown-item-title">Fill in the Blank</div>
                                    <div class="dropdown-item-desc">Students type answers into blank spaces in text</div>
                                </div>
                                <div class="dropdown-item" onclick="addNewQuestion('formula')">
                                    <div class="dropdown-item-title">Formula Question</div>
                                    <div class="dropdown-item-desc">Mathematical problems with variable substitution</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="questionsList" style="padding: 16px;">
                        <div class="text-muted" style="text-align: center; padding: 40px 20px;" id="emptyState">
                            <div style="font-size: 48px; margin-bottom: 16px;">📝</div>
                            <div style="font-size: 16px; margin-bottom: 8px;">No questions yet</div>
                            <div style="font-size: 14px;">Click "New Question" to get started</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview Tab -->
        <div id="preview" class="tab-content">
            <div class="content-wrapper">
                <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
                    <label for="previewModeToggle" style="font-weight: 500;">Preview Mode:</label>
                    <select id="previewModeToggle" style="padding: 6px 12px; border-radius: 4px; border: 1px solid #ccc; width: 150px;">
                        <option value="student">Student View</option>
                        <option value="proctor">Proctor View</option>
                    </select>
                </div>
                <div id="previewContent"></div>
            </div>
        </div>
    </div>

    </div> <!-- /wrap -->

        <!-- Add save button container -->
        <div style="position: fixed; bottom: 32px; left: 280px; z-index: 2000;">
            <button class="btn btn-primary" onclick="saveQuizToJson()" style="padding: 10px 20px; font-size: 14px;">
                💾 Save Progress
            </button>
        </div>

        <script src="edit-assessment-script.js"></script>

</body>

</html>