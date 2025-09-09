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


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Unpublished</title>
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
            padding: 40px;
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

        .course-card {
            width: 300px;
            /* Fixed card width */
            flex: 0 0 auto;
            /* Don't grow/shrink */
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
            height: 250px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            z-index: 100;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .card-header>div {
            max-width: 80%;
            /* Ensure text containers can overflow */
        }

        .card-header h3,
        .card-header h4,
        .meta {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .card-header h3 {
            margin-bottom: 6px;
            font-size: 1.25rem;
            color: #B71C1C;
        }

        .card-header h4 {
            margin-bottom: 6px;
            font-size: 1.10rem;
            color: #B71C1C;
        }

        .description {
            font-size: 0.95rem;
            color: #444;
            margin-bottom: 10px;
            display: -webkit-box;

            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .meta {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 4px;
        }

        .menu-container {
            position: relative;
        }

        .menu-button {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #555;
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

        .menu-container.show .dropdown-menu {
            display: flex;
        }

        .card-footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .view-btn {
            background: #B71C1C;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s ease;
            text-decoration: none;
        }

        .view-btn:hover {
            background: #a01515;
        }

        .view-button {
            margin-bottom: 10px;
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
            text-decoration: none;
        }

        .nav-button {
            text-align: right;
            margin-bottom: 20px;
        }

        .course-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
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

        <h2>Unpublished Courses</h2>

        <br>

        <!-- Snackbar Container -->
        <div id="snackbar">This is a sample snackbar message.</div>

        <div class="course-grid">
            <?php
            require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

            $stmt = $conn->prepare("SELECT * FROM courses WHERE user_id = ? AND published = 0 ORDER BY created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0):
                while ($course = $result->fetch_assoc()):
            ?>
                    <div class="course-card">
                        <div class="card-header">
                            <div>
                                <h3><?= htmlspecialchars($course['course_code']) ?></h3>
                                <h4><?= htmlspecialchars($course['course_title']) ?></h4>
                                <p class="description"><?= htmlspecialchars($course['description']) ?></p>
                                <p class="meta">Class Code: <strong><?= htmlspecialchars($course['class_code']) ?></strong></p>
                                <p class="meta">Instructor: <strong><?= htmlspecialchars($first_name . ' ' . $last_name) ?></strong></p>
                            </div>
                            <div class="menu-container">
                                <button class="menu-button">⋮</button>
                                <div class="dropdown-menu">
                                    <form action="edit-course-up-page" method="POST" style="margin: 0;">
                                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                        <button type="submit" style="background: none; border: none; padding: 10px 14px; width: 100%; text-align: left; cursor: pointer;">
                                            Edit
                                        </button>
                                    </form>

                                    <form action="functions/restore-course" method="POST" style="margin: 0;">
                                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                        <button type="submit" style="background: none; border: none; padding: 10px 14px; width: 100%; text-align: left; cursor: pointer;">
                                            Restore
                                        </button>
                                    </form>

                                    <form action="functions/delete-up-course" method="POST" onsubmit="return confirmDeleteCourse();" style="margin: 0;">
                                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                        <button type="submit" style="background: none; border: none; padding: 10px 14px; width: 100%; text-align: left; cursor: pointer;">
                                            Delete
                                        </button>
                                    </form>

                                    <script>
                                        function confirmDeleteCourse() {
                                            return confirm("Are you sure you want to delete this course? This action cannot be undone.");
                                        }
                                    </script>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <form action="announcements" method="POST" style="display:inline;">
                                <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                <button type="submit" class="view-btn">
                                    View
                                </button>
                            </form>
                        </div>
                    </div>
            <?php
                endwhile;
            else:
                echo '<p>No courses found.</p>';
            endif;
            ?>
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