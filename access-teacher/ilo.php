<?php
session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/access-teacher/functions/history_logger.php');

logUserHistory("visited", "ILO"); // You can customize this per page

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
    header("Location: ilo");
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

$ilos = [];
$ilo_stmt = $conn->prepare("SELECT id, ilo_number, ilo_description FROM course_ilos WHERE course_id = ? ORDER BY ilo_number ASC");
$ilo_stmt->bind_param("i", $course_id);
$ilo_stmt->execute();
$ilo_result = $ilo_stmt->get_result();

while ($ilo = $ilo_result->fetch_assoc()) {
    $ilos[] = $ilo;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Intended Learning Outcomes</title>
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

        .ilo-cards {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 10px;
            z-index: 100;
        }

        .ilo-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
            padding: 20px;
            position: relative;
            transition: 0.2s ease;
            z-index: 100;
        }

        .ilo-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ilo-number {
            font-size: 1.2rem;
            margin: 0;
            color: #2c3e50;
        }

        .ilo-description {
            margin-top: 12px;
            font-size: 0.95rem;
            color: #444;
            max-width: 100%;
            word-wrap: break-word;
        }

        .menu-container {
            position: relative;
        }

        .menu-button {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #444;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 24px;
            right: 0;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
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

        /* Modal Overlay */
        #editIloModal.modal,
        #createIloModal.modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        /* Modal Card */
        .modal-card {
            background: #fff;
            margin: 8% auto;
            padding: 25px 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            position: relative;
            font-family: 'Segoe UI', sans-serif;
        }

        /* Close Button */
        .modal-card .close {
            position: absolute;
            top: 12px;
            right: 16px;
            font-size: 22px;
            color: #555;
            cursor: pointer;
        }

        .modal-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 20px;
            color: #333;
        }

        .modal-card label {
            display: block;
            margin-top: 10px;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 14px;
            color: #444;
        }

        .modal-card input[type="number"],
        .modal-card textarea {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        .modal-card button {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 8px;
        }

        .modal-card button.nav-btn {
            background-color: maroon;
            color: white;
        }

        .modal-card button.cancel-btn {
            background-color: #ccc;
            color: #333;
        }

        .modal-card button.nav-btn:hover {
            background-color: #800000;
        }

        .modal-card button.cancel-btn:hover {
            background-color: #bbb;
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
            <a href="grades" class="topbar-link">Grades</a>
            <a href="ilo" class="topbar-link active">ILO</a>
        </div>

        <!-- Header -->
        <div class="header">
            <p><strong><?= $course_code ?>:</strong> <?= $course_title ?></p>
        </div>

        <h2>Intended Learning Outcomes</h2>

        <!-- Snackbar Container -->
        <div id="snackbar">This is a sample snackbar message.</div>


        <div class="nav-button">
            <button class="nav-btn" onclick="openCreateModal()">Create ILO</button>
        </div>


        <br>

        <div class="ilo-cards">
            <?php if (empty($ilos)): ?>
                <p>No ILOs found for this course.</p>
            <?php else: ?>
                <?php foreach ($ilos as $ilo): ?>
                    <div class="ilo-card">
                        <div class="ilo-header">
                            <h3 class="ilo-number">ILO #<?= htmlspecialchars($ilo['ilo_number']) ?></h3>
                            <div class="menu-container">
                                <button class="menu-button">⋮</button>
                                <div class="dropdown-menu">
                                    <a href="#" class="edit-ilo-link"
                                        data-id="<?= $ilo['id'] ?>"
                                        data-number="<?= htmlspecialchars($ilo['ilo_number']) ?>"
                                        data-description="<?= htmlspecialchars($ilo['ilo_description']) ?>">
                                        Edit
                                    </a>

                                    <a href="delete-ilo?id=<?= $ilo['id'] ?>" onclick="return confirm('Delete this ILO?')">Delete</a>
                                </div>
                            </div>
                        </div>
                        <p class="ilo-description"><?= htmlspecialchars($ilo['ilo_description']) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Modal HTML -->
        <div id="editIloModal" class="modal">
            <div class="modal-card">
                <span class="close" onclick="closeEditModal()">&times;</span>
                <h3>Edit Intended Learning Outcome</h3>
                <form id="editIloForm" method="POST" action="update-ilo-handler">
                    <input type="hidden" name="id" id="editIloId">

                    <label for="editIloNumber">ILO Number</label>
                    <input type="number" name="ilo_number" id="editIloNumber" required>

                    <label for="editIloDescription">Description</label>
                    <textarea name="ilo_description" id="editIloDescription" rows="4" required></textarea>

                    <div style="margin-top: 15px;">
                        <button type="submit" class="nav-btn">Update</button>
                        <button type="button" onclick="closeEditModal()" class="cancel-btn">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="createIloModal" class="modal" style="display:none;">
            <div class="modal-card">
                <div class="modal-header">
                    <h3>Create Intended Learning Outcome</h3>
                    <span class="close" onclick="closeCreateModal()">&times;</span>
                </div>
                <form method="POST" action="create-ilo-handler">
                    <label for="iloNumber">ILO Number</label>
                    <input type="number" name="ilo_number" id="iloNumber" required>

                    <label for="iloDescription">Description</label>
                    <textarea name="ilo_description" id="iloDescription" rows="4" required></textarea>

                    <input type="hidden" name="course_id" value="<?= $course_id ?>">

                    <div class="modal-actions">
                        <button type="submit" class="nav-btn">Create</button>
                        <button type="button" onclick="closeCreateModal()" class="nav-btn cancel">Cancel</button>
                    </div>
                </form>
            </div>
        </div>


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
        function closeEditModal() {
            document.getElementById('editIloModal').style.display = 'none';
        }

        function openEditModal(id, number, description) {
            document.getElementById('editIloId').value = id;
            document.getElementById('editIloNumber').value = number;
            document.getElementById('editIloDescription').value = description;
            document.getElementById('editIloModal').style.display = 'block';
        }

        document.querySelectorAll('.edit-ilo-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.dataset.id;
                const number = this.dataset.number;
                const description = this.dataset.description;
                openEditModal(id, number, description);
            });
        });

        // Optional: close modal if clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editIloModal');
            if (event.target === modal) {
                modal.style.display = "none";
            }
        };
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
    <script>
        function openCreateModal() {
            document.getElementById("createIloModal").style.display = "block";
        }

        function closeCreateModal() {
            document.getElementById("createIloModal").style.display = "none";
        }
    </script>




</body>

</html>