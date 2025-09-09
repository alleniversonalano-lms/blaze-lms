<?php
session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/access-teacher/functions/history_logger.php');

logUserHistory("visited", "People"); // You can customize this per page

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
    header("Location: people");
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



// Fetch course code, title, and creator
$course_code = '';
$course_title = '';
$creator_id = null;

if ($course_id) {
    $course_stmt = $conn->prepare("SELECT class_code, course_code, course_title, user_id FROM courses WHERE id = ?");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    if ($course_result && $course_row = $course_result->fetch_assoc()) {
        $course_code = htmlspecialchars($course_row['course_code']);
        $course_title = htmlspecialchars($course_row['course_title']);
        $creator_id = (int) $course_row['user_id'];
    }
}


// Fetch collaborators (Lecturers)
$lecturers = [];

$lecturer_stmt = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email_address, u.profile_pic
    FROM users u 
    JOIN course_collaborators cc ON u.id = cc.teacher_id 
    WHERE cc.course_id = ?
");
$lecturer_stmt->bind_param("i", $course_id);
$lecturer_stmt->execute();
$lecturer_result = $lecturer_stmt->get_result();
while ($row = $lecturer_result->fetch_assoc()) {
    $lecturers[$row['id']] = $row;
}

// Add the creator if not already in list
if ($creator_id && !isset($lecturers[$creator_id])) {
    $creator_stmt = $conn->prepare("
        SELECT id, first_name, last_name, email_address, profile_pic 
        FROM users 
        WHERE id = ?
    ");
    $creator_stmt->bind_param("i", $creator_id);
    $creator_stmt->execute();
    $creator_result = $creator_stmt->get_result();
    if ($creator_row = $creator_result->fetch_assoc()) {
        $lecturers[$creator_row['id']] = $creator_row;
    }
}

// Fetch students (Enrollments)
$students = [];
$student_stmt = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email_address, u.profile_pic
    FROM users u 
    JOIN course_enrollments ce ON u.id = ce.student_id 
    WHERE ce.course_id = ?
    ORDER BY u.first_name ASC, u.last_name ASC
");
$student_stmt->bind_param("i", $course_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
while ($row = $student_result->fetch_assoc()) {
    $students[] = $row;
}




?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>People</title>
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


        .people-section {
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
            /* ensures it's above the cutout background */
        }

        .bulk-actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .people-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            font-size: 0.95rem;
        }

        .people-table th,
        .people-table td {
            padding: 14px 18px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .people-table th {
            background-color: #f9f9f9;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .people-table tbody tr:hover {
            background-color: #f5f7fa;
            transition: background 0.2s ease;
        }

        .people-table td:first-child,
        .people-table th:first-child {
            border-left: none;
        }

        .people-table td:last-child,
        .people-table th:last-child {
            border-right: none;
        }


        .unenroll-btn {
            background-color: #b71c1c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .unenroll-btn:hover {
            background-color: #c62828;
        }

        .floating-actions {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            border: 1px solid #ccc;
            padding: 12px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 10;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .badge-owner {
            background-color: #0666eb;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
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
            bottom: 100px;
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

        .section-tab.active {
            border-bottom: 2px solid #6c1d45;
            color: #6c1d45;
        }

        .section-tab:hover {
            color: #6c1d45;
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
            <a href="people" class="topbar-link active">People</a>
            <a href="grades" class="topbar-link">Grades</a>
            <a href="ilo" class="topbar-link">ILO</a>
        </div>

        <!-- Header -->
        <div class="header">
            <p><strong><?= $course_code ?>:</strong> <?= $course_title ?></p>
        </div>

        <?php if ($user_id === $creator_id): ?>
            <!-- Section Navigation (People | Groups) -->
            <div class="section-nav" style="margin-top: 20px; border-bottom: 2px solid #ccc; padding-bottom: 10px;">
                <a href="people" class="section-tab active" style="margin-right: 20px; text-decoration: none; font-weight: bold; color: #6c1d45;">People</a>
                <a href="groups" class="section-tab" style="text-decoration: none; color: #333;">Groups</a>
            </div>
        <?php endif; ?>

        <br>

        <h2>People</h2>

        <!-- Invite Button -->
        <div class="nav-button">
            <button onclick="showInvitePopup()" class="nav-btn">Invite People</button>
        </div>

        <?php
        $host = $_SERVER['HTTP_HOST']; // Automatically gets localhost or blaze.cloudkyle.com
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $inviteLink = $protocol . $host . "/join-class.php?code=" . urlencode($course_row['class_code']);
        ?>


        <!-- Hidden Modal -->
        <div id="inviteModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9999; justify-content:center; align-items:center;">
            <div style="background:#fff; padding:20px; border-radius:10px; max-width:400px; width:90%; box-shadow:0 4px 12px rgba(0,0,0,0.2); position:relative;">
                <h3 style="margin-top:0;">Invite Students</h3>
                <br>
                <p>Share this invite link:</p>
                <input type="text" id="inviteLinkInput" value="<?= htmlspecialchars($inviteLink) ?>" readonly style="width:100%; padding:8px; font-size:14px; border:1px solid #ccc; border-radius:4px;" />
                <button onclick="copyInviteLink()" style="margin-top:10px; padding:6px 12px;">Copy Link</button>
                <button onclick="closeInvitePopup()" style="margin-top:10px; margin-left:10px; padding:6px 12px;">Close</button>
            </div>
        </div>

        <!-- JavaScript for Modal Behavior -->
        <script>
            function showInvitePopup() {
                document.getElementById('inviteModal').style.display = 'flex';
            }

            function closeInvitePopup() {
                document.getElementById('inviteModal').style.display = 'none';
            }

            function copyInviteLink() {
                const input = document.getElementById('inviteLinkInput');
                input.select();
                input.setSelectionRange(0, 99999); // Mobile support
                document.execCommand('copy');
                alert("Invite link copied to clipboard!");
            }
        </script>




        <br>

        <!-- Snackbar Container -->
        <div id="snackbar">This is a sample snackbar message.</div>

        <!-- Lecturers Section -->
        <div class="people-section">
            <h3>Lecturers</h3>

            <br>
            <table class="people-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($creator_id == $user_id): ?>
                        <tr>
                            <td></td>
                            <td>
                                <div style="display: flex; align-items: center;">
                                    <img src="/uploads/profile_pics/<?= htmlspecialchars($profile_pic ?? 'default.png') ?>"
                                        alt="Your Avatar"
                                        class="avatar-sm"
                                        style="width:32px; height:32px; border-radius:50%; object-fit:cover; margin-right:8px;">
                                    <strong>You (Owner)</strong>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($email_address) ?></td>
                            <td><span class="badge-owner">Owner</span></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td></td>
                            <td>
                                <div style="display: flex; align-items: center;">
                                    <img src="/uploads/profile_pics/<?= htmlspecialchars($profile_pic ?? 'default.png') ?>"
                                        alt="Your Avatar"
                                        class="avatar-sm"
                                        style="width:32px; height:32px; border-radius:50%; object-fit:cover; margin-right:8px;">
                                    <strong>You</strong>
                                </div>
                            </td>

                            <td><?= htmlspecialchars($email_address) ?></td>
                            <td>Lecturer</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($lecturers as $lecturer): ?>
                        <?php
                        $is_owner = $lecturer['id'] == $creator_id;
                        $is_self = $lecturer['id'] == $user_id;
                        $avatar = !empty($lecturer['profile_pic']) ? htmlspecialchars($lecturer['profile_pic']) : 'default.png';
                        ?>
                        <?php if (!$is_self): ?>
                            <tr <?= $is_owner ? 'style="background-color:#e8f4ff;"' : '' ?>>
                                <td>
                                    <?php if (!$is_owner && $creator_id == $user_id): ?>
                                        <input type="checkbox" class="lecturer-checkbox" data-user-id="<?= $lecturer['id'] ?>">
                                    <?php endif; ?>

                                </td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <img src="/uploads/profile_pics/<?= htmlspecialchars($avatar) ?>"
                                            alt="Lecturer Avatar"
                                            class="avatar-sm"
                                            style="width:32px; height:32px; border-radius:50%; object-fit:cover; margin-right:8px;">
                                        <span>
                                            <?= htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']) ?>
                                            <?= $is_owner ? '<strong style="color:#0666eb;"> (Owner)</strong>' : '' ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($lecturer['email_address']) ?></td>
                                <td><?= $is_owner ? '<span class="badge-owner">Owner</span>' : 'Lecturer' ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>

        <br>

        <!-- Students Section -->
        <div class="people-section">
            <h3>Students (<?= count($students) ?>)</h3>

            <br>
            <table class="people-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <?php
                        $avatar = !empty($student['profile_pic']) ? htmlspecialchars($student['profile_pic']) : 'default.png';
                        ?>
                        <tr>
                            <?php if ($user_id === $creator_id): ?>
                                <td><input type="checkbox" class="student-checkbox" data-user-id="<?= $student['id'] ?>"></td>
                            <?php else: ?>
                                <td></td>
                            <?php endif; ?>
                            <td>
                                <div style="display: flex; align-items: center;">
                                    <img src="/uploads/profile_pics/<?= $avatar ?>"
                                        alt="Student Avatar"
                                        class="avatar-sm"
                                        style="width:32px; height:32px; border-radius:50%; object-fit:cover; margin-right:8px;">
                                    <span><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($student['email_address']) ?></td>
                            <td>Student</td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>


            </table>
        </div>


    </div>

    <?php if ($creator_id == $user_id): ?>
        <div class="floating-actions" id="floating-bar">
            <div>
                <label><input type="checkbox" id="select-all-lecturers-floating"> Select All Lecturers</label>
            </div>
            <div>
                <label><input type="checkbox" id="select-all-students-floating"> Select All Students</label>
            </div>
            <div>
                <button class="unenroll-btn" onclick="unenrollChecked()">Unenroll</button>
            </div>
        </div>
    <?php endif; ?>





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
        function unenrollChecked() {
            const isCreator = <?= json_encode($creator_id == $user_id) ?>;
            if (!isCreator) {
                alert("Only the course owner can perform unenrollment.");
                return;
            }

            const selectedLecturers = [...document.querySelectorAll('.lecturer-checkbox:checked')]
                .map(cb => cb.getAttribute('data-user-id'));
            const selectedStudents = [...document.querySelectorAll('.student-checkbox:checked')]
                .map(cb => cb.getAttribute('data-user-id'));

            const courseId = <?= (int) $course_id ?>;

            if (selectedLecturers.length === 0 && selectedStudents.length === 0) {
                alert('No users selected for unenrollment.');
                return;
            }

            if (!confirm("Are you sure you want to unenroll the selected users?")) return;

            fetch('functions/unenroll-users', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        course_id: courseId,
                        lecturers: selectedLecturers,
                        students: selectedStudents
                    })
                })
                .then(() => {
                    window.location.href = 'people?error=Selected users have been unenrolled.';
                })
                .catch(err => {
                    console.error(err);
                    window.location.href = 'people?error=Unenrollment failed.';
                });
        }

        // Floating Select All logic
        document.getElementById('select-all-lecturers-floating').addEventListener('change', function() {
            document.querySelectorAll('.lecturer-checkbox').forEach(cb => cb.checked = this.checked);
        });

        document.getElementById('select-all-students-floating').addEventListener('change', function() {
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked);
        });

        document.querySelectorAll('.lecturer-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                const all = document.querySelectorAll('.lecturer-checkbox').length;
                const checked = document.querySelectorAll('.lecturer-checkbox:checked').length;
                document.getElementById('select-all-lecturers-floating').checked = (all === checked);
            });
        });

        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                const all = document.querySelectorAll('.student-checkbox').length;
                const checked = document.querySelectorAll('.student-checkbox:checked').length;
                document.getElementById('select-all-students-floating').checked = (all === checked);
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