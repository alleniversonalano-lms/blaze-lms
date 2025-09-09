<?php
session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/access-teacher/functions/history_logger.php');

logUserHistory("visited", "Edit Bulletin Feed"); // You can customize this per page

// Store session details into variables
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$email_address = $_SESSION['email_address'];
$role = $_SESSION['role'];

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

// Get announcements for a specific course
$stmt = $conn->prepare("
    SELECT a.id AS announcement_id, a.description, a.created_at, u.first_name, u.last_name, u.id AS user_id,
           aa.file_path, aa.file_name
    FROM announcements a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN announcement_attachments aa ON a.id = aa.announcement_id
    WHERE a.course_id = ?
    ORDER BY a.created_at DESC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

// Group results by announcement_id
$announcements = [];
while ($row = $result->fetch_assoc()) {
    $id = $row['announcement_id'];
    if (!isset($announcements[$id])) {
        $announcements[$id] = [
            'description' => $row['description'],
            'created_at' => $row['created_at'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'user_id' => $row['user_id'],
            'attachments' => []
        ];
    }
    if ($row['file_path']) {
        $announcements[$id]['attachments'][] = [
            'path' => $row['file_path'],
            'name' => $row['file_name']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Bulletin</title>
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


        .announcement-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 16px;
            margin-top: 30px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 2;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }

        .name {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 2px;
            color: #333;
        }

        .timestamp {
            font-size: 0.8rem;
            color: #888;
        }

        .card-body {
            margin-top: 12px;
        }

        .announcement-text {
            font-size: 0.95rem;
            color: #444;
            margin-bottom: 12px;
        }

        .attachment-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .attachment-tile {
            width: 120px;
            border: 1px solid #ccc;
            border-radius: 6px;
            overflow: hidden;
            background: #f9f9f9;
            text-align: center;
            font-size: 0.8rem;
            color: #555;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
            text-decoration: none;
            color: inherit;
            transition: box-shadow 0.2s;
        }

        .attachment-tile:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }


        .attachment-tile img {
            width: 100%;
            height: 80px;
            object-fit: cover;
            display: block;
        }

        .attachment-tile p {
            margin: 6px 8px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .menu-container {
            position: relative;
        }

        .menu-button {
            background: none;
            border: none;
            font-size: 1.5rem;
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
            min-width: 120px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10;
        }

        .dropdown-menu a {
            padding: 10px 14px;
            text-decoration: none;
            font-size: 0.9rem;
            color: #333;
            transition: background 0.2s;
        }

        .dropdown-menu a:hover {
            background-color: #f5f5f5;
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

        .announcement-form {
            max-width: 700px;
            margin: 40px auto;
            background: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .announcement-form h2 {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
        }

        textarea {
            width: 100%;
            height: 120px;
            resize: vertical;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        input[type="file"] {
            display: block;
            margin-top: 8px;
        }

        .submit-btn {
            background-color: #0666eb;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }

        .submit-btn:hover {
            background-color: #004ec2;
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
            <a href="announcements" class="topbar-link active">Bulletin</a>
            <a href="modules" class="topbar-link">Modules</a>
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

        <h2>Edit Bulletin</h2>

        <br>

        <!-- Page content goes here -->
        <?php

        $announcement_id = (int) $_POST['announcement_id'];

        $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
        $stmt->bind_param("i", $announcement_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $announcement = $result->fetch_assoc();

        if (!$announcement) {
            header("Location: /404");
            exit;
        }

        // Fetch attachments if needed
        $attachments_stmt = $conn->prepare("SELECT * FROM announcement_attachments WHERE announcement_id = ?");
        $attachments_stmt->bind_param("i", $announcement_id);
        $attachments_stmt->execute();
        $attachments_result = $attachments_stmt->get_result();
        $attachments = $attachments_result->fetch_all(MYSQLI_ASSOC);
        ?>

        <!-- Page content -->
        <div class="announcement-form">
            <form id="announcementForm" action="functions/update-announcement" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="announcement_id" value="<?= $announcement_id ?>">
                <input type="hidden" name="course_id" value="<?= htmlspecialchars($announcement['course_id']) ?>">
                <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>">

                <div class="form-group">
                    <label for="description">Announcement Description</label>
                    <textarea name="description" id="description" required placeholder="Type your announcement here..."><?= htmlspecialchars($announcement['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Existing Attachments</label>
                    <ul>
                        <?php foreach ($attachments as $file): ?>
                            <li>
                                <a href="<?= htmlspecialchars($file['file_path']) ?>" target="_blank"><?= htmlspecialchars($file['file_name']) ?></a>
                                <label style="color:red; margin-left: 10px;">
                                    <input type="checkbox" name="delete_attachments[]" value="<?= htmlspecialchars($file['id']) ?>">
                                    Delete
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>


                <div class="form-group">
                    <label for="attachments">Add More Attachments (optional)</label>
                    <input type="file" id="fileInput" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.rar">
                    <div id="filePreview" style="margin-top: 10px;"></div>
                </div>

                <button type="submit" class="submit-btn">Update Announcement</button>
            </form>
        </div>

        <script>
            const fileInput = document.getElementById('fileInput');
            const filePreview = document.getElementById('filePreview');
            const form = document.getElementById('announcementForm');
            let fileList = [];

            fileInput.addEventListener('change', function(e) {
                const newFiles = Array.from(e.target.files);
                fileList = [...fileList, ...newFiles];
                renderPreview();
                fileInput.value = '';
            });

            function renderPreview() {
                filePreview.innerHTML = '';
                fileList.forEach((file, index) => {
                    const fileBlock = document.createElement('div');
                    fileBlock.style.marginBottom = '6px';

                    const nameSpan = document.createElement('span');
                    nameSpan.textContent = file.name;
                    nameSpan.style.marginRight = '10px';

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.textContent = '✕';
                    removeBtn.style.border = 'none';
                    removeBtn.style.background = '#e74c3c';
                    removeBtn.style.color = '#fff';
                    removeBtn.style.borderRadius = '50%';
                    removeBtn.style.width = '24px';
                    removeBtn.style.height = '24px';
                    removeBtn.style.cursor = 'pointer';
                    removeBtn.style.fontWeight = 'bold';

                    removeBtn.addEventListener('click', () => {
                        fileList.splice(index, 1);
                        renderPreview();
                    });

                    fileBlock.appendChild(nameSpan);
                    fileBlock.appendChild(removeBtn);
                    filePreview.appendChild(fileBlock);
                });
            }

            form.addEventListener('submit', function(e) {
                if (fileList.length > 0) {
                    const dataTransfer = new DataTransfer();
                    fileList.forEach(file => dataTransfer.items.add(file));
                    fileInput.files = dataTransfer.files;
                }
            });
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
        document.addEventListener('DOMContentLoaded', () => {
            const menuContainers = document.querySelectorAll('.menu-container');

            menuContainers.forEach(container => {
                const button = container.querySelector('.menu-button');

                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        if (!container.contains(menu)) menu.style.display = 'none';
                    });

                    const dropdown = container.querySelector('.dropdown-menu');
                    dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex';
                });
            });

            document.addEventListener('click', () => {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            });
        });
    </script>



</body>

</html>