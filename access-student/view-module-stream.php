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
            z-index: 100;
            /* Higher than default */
        }

        .current-module-header {
            background: linear-gradient(135deg, #B71C1C 0%, #d32f2f 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(183, 28, 28, 0.2);
            position: relative;
            overflow: hidden;
        }

        .current-module-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            pointer-events: none;
        }

        .current-module-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .current-module-description {
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.9;
            max-width: 800px;
            font-weight: 300;
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
            <a href="modules" class="topbar-link active">Modules</a>
            <a href="assessments" class="topbar-link">Assessments</a>
            <a href="people" class="topbar-link">People</a>
            <a href="grades" class="topbar-link">Grades</a>
        </div>

        <!-- Header -->
        <div class="header">
            <p><strong><?= $course_code ?>:</strong> <?= $course_title ?></p>
        </div>

        <?php
        // Get the current module's title and description
        if (isset($_POST['module_id'])) {
            $current_module_id = (int)$_POST['module_id'];
            $stmt = $conn->prepare("SELECT title, description FROM modules WHERE id = ? AND course_id = ? LIMIT 1");
            $stmt->bind_param("ii", $current_module_id, $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($module = $result->fetch_assoc()) {
                echo '<div class="current-module-header">';
                echo '<div class="current-module-title">' . htmlspecialchars($module['title']) . '</div>';
                echo '<div class="current-module-description">' . htmlspecialchars($module['description']) . '</div>';
                echo '</div>';
            }
        }
        ?>
        
        <?php

        // Validate POST module_id
        if (!isset($_POST['module_id'])) {
            echo "<p style='padding: 20px; color: #e74c3c;'>Module ID is required.</p>";
            exit;
        }

        $module_id = (int) $_POST['module_id'];

        // Store in session if needed later
        $_SESSION['module_id'] = $module_id;
        ?>


        <br>

        <!-- Snackbar Container -->
        <div id="snackbar">This is a sample snackbar message.</div>

        <?php


        if (isset($_POST['id'])) {
            $_SESSION['module_id'] = (int) $_POST['id'];
        }

        if (isset($_SESSION['module_id'])) {
            $module_id = $_SESSION['module_id'];
        } else {
            echo "<p style='padding:20px; color:#e74c3c;'>Invalid module ID.</p>";
            exit;
        }


        // Fetch module info
        $mod_stmt = $conn->prepare("SELECT m.*, c.course_title AS course_title, c.course_code FROM modules m JOIN courses c ON m.course_id = c.id WHERE m.id = ?");
        $mod_stmt->bind_param("i", $module_id);
        $mod_stmt->execute();
        $mod_res = $mod_stmt->get_result();

        if ($mod_res->num_rows === 0) {
            echo "<p style='padding:20px; color:#e74c3c;'>Module not found.</p>";
            exit;
        }

        $module = $mod_res->fetch_assoc();
        $module_title = htmlspecialchars($module['title']);
        $module_number = htmlspecialchars($module['module_number']);
        $module_desc = htmlspecialchars($module['description']);

        ?>

        <?php
        $stmt = $conn->prepare("SELECT ms.*, u.first_name, u.last_name, u.profile_pic
                        FROM module_streams ms 
                        JOIN users u ON ms.user_id = u.id 
                        WHERE ms.module_id = ? AND ms.is_published = 1
                        ORDER BY ms.created_at DESC");


        $stmt->bind_param("i", $module_id);
        $stmt->execute();
        $stream_result = $stmt->get_result();

        if ($stream_result->num_rows === 0): ?>
            <p style="padding: 10px; color: #666;">No posts in this module yet.</p>
            <?php else:
            while ($row = $stream_result->fetch_assoc()):
                $stream_id = $row['id'];
                $author = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                $content = nl2br(htmlspecialchars($row['content']));
                $created_at = date("F j, Y - g:i A", strtotime($row['created_at']));
                $is_published = (int)$row['is_published'];
                $profile_picture = !empty($row['profile_pic']) ? htmlspecialchars($row['profile_pic']) : 'default.png';


                // Fetch attachments
                $att_stmt = $conn->prepare("SELECT * FROM module_attachments WHERE stream_id = ?");
                $att_stmt->bind_param("i", $stream_id);
                $att_stmt->execute();
                $att_res = $att_stmt->get_result();

                $files = [];
                $links = [];
                while ($att = $att_res->fetch_assoc()) {
                    if (filter_var($att['file_path'], FILTER_VALIDATE_URL)) {
                        $links[] = $att;
                    } else {
                        $files[] = $att;
                    }
                }
            ?>
                <div class="stream-card">
                    <div class="stream-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <img src="/uploads/profile_pics/<?= $profile_picture ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                            <div class="stream-author"><?= $author ?></div>
                        </div>
                        <div class="stream-timestamp"><?= $created_at ?></div>



                    </div>
                    <div class="menu-container">
                        <div class="stream-content">
                            <?= $content ?>
                        </div>
                    </div>

                    <br>

                    <?php if (!empty($files)): ?>
                        <div class="stream-attachments">
                            <strong>Attachments:</strong>
                            <div class="attachment-list">
                                <?php foreach ($files as $file):
                                    $path = $file['file_path'];
                                    $filename = $file['file_name'];
                                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                    $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $ext);
                                    $extLabel = strtoupper($ext);

                                    // Viewer URLs
                                    $publicUrl = urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$path");
                                    $viewerUrl = '';
                                    if ($ext === 'pdf') {
                                        $viewerUrl = $path; // Browser can open PDF directly
                                    } elseif (in_array($ext, ['docx', 'xlsx', 'pptx'])) {
                                        $viewerUrl = "https://view.officeapps.live.com/op/embed.aspx?src=$publicUrl";
                                    } elseif (in_array($ext, ['doc', 'xls', 'ppt'])) {
                                        $viewerUrl = "https://docs.google.com/gview?url=$publicUrl&embedded=true";
                                    }
                                ?>
                                    <a href="<?= htmlspecialchars($viewerUrl ?: $path) ?>" target="_blank" class="attachment-card">
                                        <?php if ($isImage): ?>
                                            <img src="<?= htmlspecialchars($path) ?>" alt="Image">
                                        <?php else: ?>
                                            <div class="attachment-icon">
                                                <i class="fas fa-file-alt" style="font-size: 36px; color: #4285f4;"></i>
                                                <div class="attachment-ext"><?= $extLabel ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="attachment-name"><?= htmlspecialchars($filename) ?></div>
                                        <?php if ($viewerUrl): ?>
                                            <div style="text-align:center; margin-top:4px;">
                                                <span style="font-size:12px; color:#1976d2;">👁 View</span>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($links)): ?>
                        <div class="stream-attachments" style="margin-top: 15px;">
                            <strong>Links:</strong>
                            <ul style="padding-left: 20px; margin-top: 5px;">
                                <?php foreach ($links as $link): ?>
                                    <li>
                                        <?php
                                        $rawName = htmlspecialchars($link['file_name']);
                                        $shortName = (mb_strlen($rawName) > 50) ? mb_substr($rawName, 0, 47) . '...' : $rawName;
                                        ?>
                                        <a href="<?= htmlspecialchars($link['file_path']) ?>" target="_blank" title="<?= $rawName ?>">
                                            🔗 <?= $shortName ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
        <?php
            endwhile;
        endif;
        ?>
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