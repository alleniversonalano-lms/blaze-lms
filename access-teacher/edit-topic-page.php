<?php
session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/access-teacher/functions/history_logger.php');

logUserHistory("visited", "Edit Material"); // You can customize this per page

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
    header("Location: announcements");
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
    <title>Modules</title>
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


        .module-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
            z-index: 100;
        }

        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
        }

        .module-title h3 {
            margin: 0 0 6px;
            font-size: 1.2rem;
            color: #333;
        }

        .module-description {
            font-size: 0.95rem;
            color: #555;
            max-width: 600px;
        }

        .module-meta {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .module-date {
            font-size: 0.9rem;
            color: #999;
        }

        .menu-container {
            position: relative;
            display: inline-block;
            /* ensures it wraps around the menu button */
            z-index: 2000;
            /* Higher than .stream-card */
            /* ensure it's on top of anything else */
        }

        .menu-button {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #666;
            cursor: pointer;
        }

        .dropdown-menu {
            position: absolute;
            right: 0;
            top: 24px;
            background: white;
            border: 1px solid #ccc;
            border-radius: 8px;
            display: none;
            flex-direction: column;
            min-width: 140px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
            z-index: 3000;
            /* Highest to appear on top */
        }


        .dropdown-menu a,
        .dropdown-menu .dropdown-link {
            display: block;
            padding: 10px 14px;
            font-size: 0.9rem;
            color: #333;
            text-align: left;
            width: 100%;
            cursor: pointer;
            background: none;
            border: none;
            font-family: inherit;
            transition: background 0.2s;
        }

        .dropdown-menu a:hover,
        .dropdown-menu .dropdown-link:hover {
            background: #f5f5f5;
        }

        .menu-container.show .dropdown-menu {
            display: flex;
        }


        .menu-container.show .dropdown-menu {
            display: flex;
        }

        .module-footer {
            margin-top: 20px;
            text-align: right;
        }

        .view-btn {
            background-color: #b71c1c;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.95rem;
            transition: background 0.3s;
        }

        .view-btn:hover {
            background-color: #d32f2f;
        }

        #snackbar {
            visibility: hidden;
            min-width: 280px;
            max-width: 90%;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 8px;
            padding: 14px 20px;
            position: fixed;
            z-index: 9999;
            right: 30px;
            /* Position it from the right */
            bottom: 30px;
            /* Keep it at the bottom */
            font-size: 0.95rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: all 0.5s ease;
            opacity: 0;
            transform: translateY(20px);
        }

        #snackbar.show {
            visibility: visible;
            opacity: 1;
            transform: translateY(0);
        }

        .stream-card {
            position: relative;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
            z-index: 1000;
            /* Higher than default */
        }


        .stream-card:hover {
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        }

        .stream-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .stream-author {
            font-weight: 600;
            color: #2c3e50;
        }

        .stream-timestamp {
            font-size: 0.85rem;
            color: #888;
            display: inline-block;
            margin-right: 10px;
        }


        .stream-content {
            font-size: 1rem;
            color: #333;
            margin-top: 10px;
            white-space: pre-line;
        }

        .attachment-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }

        .attachment-card {
            width: 140px;
            text-decoration: none;
            color: #333;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
        }

        .attachment-card img {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }

        .attachment-icon {
            height: 100px;
            background: #f3f3f3;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .attachment-ext {
            position: absolute;
            bottom: 6px;
            right: 6px;
            background: #4285f4;
            color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }

        .attachment-name {
            padding: 8px;
            font-size: 13px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        .profile-picture {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
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
            <a href="modules" class="topbar-link active">Modules</a>
            <a href="assessments" class="topbar-link">Assessments</a>
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

        <h2>Edit Topic</h2>
        <?php

        if (!isset($_POST['id'])) {
            echo "<p style='padding: 20px; color: #e74c3c;'>Module ID is required.</p>";
            exit;
        }

        $module_id = (int)$_POST['id']; // Make sure this line exists
        ?>


        <br>

        <!-- Snackbar Container -->
        <div id="snackbar">This is a sample snackbar message.</div>

        <?php

        $stream_id = (int) $_POST['id'];
        $user_id = $_SESSION['user_id'];

        // Fetch the stream post
        $stmt = $conn->prepare("SELECT ms.*, u.first_name, u.last_name, u.profile_pic, m.course_id, c.user_id 
                        FROM module_streams ms 
                        JOIN users u ON ms.user_id = u.id 
                        JOIN modules m ON ms.module_id = m.id 
                        JOIN courses c ON m.course_id = c.id 
                        WHERE ms.id = ?");
        $stmt->bind_param("i", $stream_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            header("Location: ../modules?error=Topic+not+found");
            exit;
        }

        $post = $res->fetch_assoc();

        if ($post['user_id'] != $user_id && $post['user_id'] != $user_id) {
            header("Location: ../modules?error=Unauthorized");
            exit;
        }

        $module_id = $post['module_id'];
        $content = htmlspecialchars($post['content']);
        $profile_picture = !empty($post['profile_pic']) ? htmlspecialchars($post['profile_pic']) : 'default.png';

        // Fetch existing attachments
        $att_stmt = $conn->prepare("SELECT * FROM module_attachments WHERE stream_id = ?");
        $att_stmt->bind_param("i", $stream_id);
        $att_stmt->execute();
        $att_result = $att_stmt->get_result();

        $files = [];
        $links = [];
        while ($att = $att_result->fetch_assoc()) {
            if (filter_var($att['file_path'], FILTER_VALIDATE_URL)) {
                $links[] = $att;
            } else {
                $files[] = $att;
            }
        }
        ?>

        <form action="functions/update-topic" method="POST" enctype="multipart/form-data"
            style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); max-width: 800px; margin-left: 0;">

            <input type="hidden" name="id" value="<?= $stream_id ?>">

            <label for="content" style="display:block; font-weight:600; margin-bottom: 8px;">Edit Content</label>
            <textarea name="content" rows="8" style="width: 100%; padding: 12px; font-size: 1rem; border-radius: 8px; border: 1px solid #ccc; margin-bottom: 20px;"><?= $content ?></textarea>

            <!-- Current Files -->
            <div style="margin-bottom: 20px;">
                <h4 style="margin-bottom: 10px;">📁 Current Files</h4>
                <p style="font-size: 0.9rem; color: #888; margin-bottom: 10px;">Tick the box to delete a file.</p>
                <ul style="list-style: none; padding-left: 0;">
                    <?php foreach ($files as $f): ?>
                        <li style="margin-bottom: 10px;">
                            <label style="display: flex; align-items: center; gap: 12px;">
                                <input type="checkbox" name="remove_files[]" value="<?= $f['id'] ?>">
                                <a href="<?= htmlspecialchars($f['file_path']) ?>" target="_blank" style="color: #007bff; text-decoration: none;">
                                    📎 <?= htmlspecialchars($f['file_name']) ?>
                                </a>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Current Links -->
            <div style="margin-bottom: 20px;">
                <h4 style="margin-bottom: 10px;">🔗 Current Links</h4>
                <p style="font-size: 0.9rem; color: #888; margin-bottom: 10px;">Tick the box to delete a link.</p>
                <ul style="list-style: none; padding-left: 0;">
                    <?php foreach ($links as $l): ?>
                        <li style="margin-bottom: 10px;">
                            <label style="display: flex; align-items: center; gap: 12px;">
                                <input type="checkbox" name="remove_links[]" value="<?= $l['id'] ?>">
                                <a href="<?= htmlspecialchars($l['file_path']) ?>" target="_blank" style="color: #007bff; text-decoration: none;">
                                    <?= htmlspecialchars($l['file_name']) ?>
                                </a>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- New Attachments -->
            <div style="margin-bottom: 20px;">
                <h4>Add New Attachments</h4>
                <input type="file" name="new_files[]" multiple onchange="previewNewFiles(this)" style="margin-top: 6px;">
                <ul id="file-preview" style="list-style: none; margin-top: 10px; padding-left: 0;"></ul>
            </div>

            <!-- New Links -->
            <div style="margin-bottom: 20px;">
                <h4>Add New Links</h4>
                <div id="link-inputs">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <input type="url" name="new_links[]" placeholder="https://example.com" style="flex: 1; padding: 10px; border-radius: 6px; border: 1px solid #ccc;">
                        <button type="button" onclick="removeLinkInput(this)" style="background: transparent; border: none; color: #b71c1c;">✖</button>
                    </div>
                </div>
                <button type="button" onclick="addLinkField()" style="background-color: #f2f2f2; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer;">+ Add Another Link</button>
            </div>

            <!-- Submit -->
            <div style="text-align: right;">
                <button type="submit" style="background-color: #b71c1c; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 1rem; cursor: pointer;">
                    Update Topic
                </button>
            </div>
        </form>

        <!-- JavaScript -->
        <script>
            function addLinkField() {
                const div = document.createElement('div');
                div.style.display = "flex";
                div.style.alignItems = "center";
                div.style.gap = "10px";
                div.style.marginBottom = "10px";
                div.innerHTML = `
            <input type="url" name="new_links[]" placeholder="https://example.com" style="flex: 1; padding: 10px; border-radius: 6px; border: 1px solid #ccc;">
            <button type="button" onclick="removeLinkInput(this)" style="background: transparent; border: none; color: #b71c1c;">✖</button>
        `;
                document.getElementById('link-inputs').appendChild(div);
            }

            function removeLinkInput(btn) {
                btn.parentElement.remove();
            }

            function previewNewFiles(input) {
                const previewList = document.getElementById('file-preview');
                previewList.innerHTML = '';

                for (let i = 0; i < input.files.length; i++) {
                    const li = document.createElement('li');
                    li.style.display = 'flex';
                    li.style.alignItems = 'center';
                    li.style.justifyContent = 'space-between';
                    li.style.marginBottom = '8px';

                    const span = document.createElement('span');
                    span.textContent = '📎 ' + input.files[i].name;

                    const removeBtn = document.createElement('button');
                    removeBtn.textContent = '✖';
                    removeBtn.type = 'button';
                    removeBtn.style.background = 'transparent';
                    removeBtn.style.border = 'none';
                    removeBtn.style.color = '#b71c1c';
                    removeBtn.style.cursor = 'pointer';
                    removeBtn.onclick = () => {
                        input.value = ''; // clear input
                        previewList.innerHTML = ''; // clear preview
                    };

                    li.appendChild(span);
                    li.appendChild(removeBtn);
                    previewList.appendChild(li);
                }
            }
        </script>



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
        function showSnackbar(message = 'This is a sample snackbar message.', duration = 3000) {
            const snackbar = document.getElementById('snackbar');
            snackbar.textContent = message;
            snackbar.classList.add('show');

            setTimeout(() => {
                snackbar.classList.remove('show');
            }, duration);
        }

        // Check for error messages in the URL
        document.addEventListener("DOMContentLoaded", function() {
            const params = new URLSearchParams(window.location.search);
            if (params.has("error")) {
                const error = decodeURIComponent(params.get("error"));
                showSnackbar(error);

                // Remove error from URL without reloading
                const url = new URL(window.location);
                url.searchParams.delete('error');
                window.history.replaceState({}, document.title, url.pathname);
            }
        });
    </script>


</body>

</html>