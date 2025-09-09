<?php
session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/access-teacher/functions/history_logger.php');

logUserHistory("visited", "Bulletin"); // You can customize this per page

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
    SELECT a.id AS announcement_id, a.description, a.created_at, u.first_name, u.last_name, u.id AS user_id, u.profile_pic,
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
            'profile_pic' => $row['profile_pic'] ?: 'default.png',
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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

        .nav a:hover {
            background-color: rgba(254, 80, 80, 0.73);
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

        .dropdown-menu a,
        .dropdown-menu .dropdown-link {
            display: block;
            padding: 10px 14px;
            text-decoration: none;
            font-size: 0.9rem;
            color: #333;
            background: none;
            border: none;
            text-align: left;
            width: 100%;
            cursor: pointer;
            transition: background 0.2s;
            font-family: inherit;
        }

        .dropdown-menu a:hover,
        .dropdown-menu .dropdown-link:hover {
            background: #f5f5f5;
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

        .react-btn.active {
            background-color: #e0f7fa;
            border-radius: 4px;
        }

        .card-body-poll {
            padding: 1rem;
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
        }

        .card-body-poll h5 {
            font-size: 1.1rem;
            font-weight: bold;
        }

        .card-body-poll .form-check-label {
            font-size: 0.95rem;
        }

        .card-body-poll .text-muted {
            font-size: 0.9rem;
        }

        .poll-question {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
            line-height: 1.4;
            white-space: pre-wrap;
            word-break: break-word;
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

        <h2>Bulletin</h2>

        <div class="nav-button">
            <a href="create-announcement-page" class="nav-btn">Create</a>
            <!-- 
            <a href="create-poll.php?course=<?= $course_id ?>" class="nav-btn" style="margin-left: 10px;">Create Poll</a> 
            -->
        </div>


        <br>

        <!-- Snackbar Container -->
        <div id="snackbar">This is a sample snackbar message.</div>

        <!-- Page content goes here -->
        <?php
        // Fetch announcement comments with user info, user reaction, and total reactions
        $comments_by_announcement = [];

        $comment_stmt = $conn->prepare("
    SELECT 
        ac.*, 
        u.first_name, 
        u.last_name, 
        u.profile_pic,
        cr.reaction_type AS user_reaction_type,
        r.reaction_type AS reaction_type_total,
        r.total AS reaction_count
    FROM announcement_comments ac
    JOIN users u ON ac.user_id = u.id
    LEFT JOIN comment_reactions cr 
        ON cr.comment_id = ac.id AND cr.user_id = ?
    LEFT JOIN (
        SELECT comment_id, reaction_type, COUNT(*) as total
        FROM comment_reactions
        GROUP BY comment_id, reaction_type
    ) AS r ON r.comment_id = ac.id
    WHERE ac.announcement_id IN (
        SELECT id FROM announcements WHERE course_id = ?
    )
    ORDER BY ac.created_at ASC
");

        $comment_stmt->bind_param("ii", $_SESSION['user_id'], $course_id);
        $comment_stmt->execute();
        $comment_result = $comment_stmt->get_result();

        while ($row = $comment_result->fetch_assoc()) {
            $aid = $row['announcement_id'];
            $cid = $row['id'];

            // Initialize if first encounter
            if (!isset($comments_by_announcement[$aid][$cid])) {
                $row['reaction_totals'] = [];
                $comments_by_announcement[$aid][$cid] = $row;
            }

            // Add or update reaction count
            if ($row['reaction_type_total']) {
                $comments_by_announcement[$aid][$cid]['reaction_totals'][$row['reaction_type_total']] = (int) $row['reaction_count'];
            }
        }



        // Get classroom creator ID
        $creator_stmt = $conn->prepare("SELECT user_id FROM courses WHERE id = ?");
        $creator_stmt->bind_param("i", $course_id);
        $creator_stmt->execute();
        $creator_res = $creator_stmt->get_result();
        $course_data = $creator_res->fetch_assoc();
        $classroom_creator_id = $course_data['user_id'] ?? null;

        $polls_by_id = [];

        $poll_stmt = $conn->prepare("
            SELECT 
            p.id AS poll_id,
            p.question,
            p.created_at,
            p.user_id,
            po.id AS option_id,
            po.option_text,
            COUNT(pv.id) AS vote_count,
            uv.user_vote_option_id,
            u.first_name,
            u.last_name,
            u.profile_pic
        FROM polls p
        LEFT JOIN poll_options po ON po.poll_id = p.id
        LEFT JOIN poll_votes pv ON pv.option_id = po.id
        LEFT JOIN (
            SELECT po.poll_id, pv.option_id AS user_vote_option_id
            FROM poll_votes pv
            INNER JOIN poll_options po ON po.id = pv.option_id
            WHERE pv.user_id = ?
        ) AS uv ON uv.poll_id = p.id
        LEFT JOIN users u ON u.id = p.user_id
        WHERE p.course_id = ?
        GROUP BY p.id, po.id, p.user_id, uv.user_vote_option_id, u.first_name, u.last_name, u.profile_pic
        ORDER BY p.created_at DESC

        ");

        $poll_stmt->bind_param("ii", $_SESSION['user_id'], $course_id);

        $poll_stmt->execute();
        $poll_result = $poll_stmt->get_result();

        while ($row = $poll_result->fetch_assoc()) {
            $poll_id = $row['poll_id'];
            $option_id = $row['option_id'];
            $created_at = $row['created_at'];

            if (!isset($polls_by_id[$poll_id])) {
                $polls_by_id[$poll_id] = [
                    'id' => $poll_id,
                    'question' => $row['question'],
                    'user_vote_option_id' => $row['user_vote_option_id'],
                    'options' => [],
                    'created_at' => $created_at,
                    'user_id' => $row['user_id'],
                    // User Info
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'profile_pic' => $row['profile_pic'],
                ];
            }

            if ($option_id) {
                $polls_by_id[$poll_id]['options'][] = [
                    'id' => $option_id,
                    'option_text' => $row['option_text'],
                    'vote_count' => (int) $row['vote_count']
                ];
            }
        }

        $bulletin_feed = [];

        // Prepare announcements
        foreach ($announcements as $id => $announcement) {
            $bulletin_feed[] = [
                'type' => 'announcement',
                'created_at' => $announcement['created_at'] ?? '1970-01-01 00:00:00',
                'data' => $announcement,
                'id' => $id,
            ];
        }

        // Prepare polls
        foreach ($polls_by_id as $poll) {
            $bulletin_feed[] = [
                'type' => 'poll',
                'created_at' => $poll['created_at'] ?? '1970-01-01 00:00:00',
                'data' => $poll,
                'id' => $poll['id'],
            ];
        }


        // Sort by date descending
        usort($bulletin_feed, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        ?>

        <?php if (empty($bulletin_feed)): ?>
            <div class="text-center text-muted" style="margin-top: 2rem; font-size: 18px;">
                <i class="fas fa-info-circle" style="margin-right: 5px;"></i> Nothing to show.
            </div>
        <?php else: ?>
            <?php foreach ($bulletin_feed as $item): ?>
                <?php if ($item['type'] === 'poll'): ?>
                    <?php
                    $poll = $item['data'];
                    $hasVoted = isset($poll['user_vote_option_id']);
                    $avatar = !empty($poll['profile_pic']) ? htmlspecialchars($poll['profile_pic']) : 'default.png';
                    $fullName = htmlspecialchars($poll['name'] ?? ($poll['first_name'] . ' ' . $poll['last_name']));
                    ?>
                    <div class="announcement-card">
                        <div class="card-header">
                            <div class="user-info">
                                <img src="/uploads/profile_pics/<?= $avatar ?>" alt="Profile" class="avatar" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px;">
                                <div>
                                    <p class="name">
                                        <a href="/profile?user_id=<?= $poll['user_id'] ?>" style="text-decoration:none; color:#000;">
                                            <?= $fullName ?>
                                        </a>
                                    </p>
                                    <p class="timestamp"><?= date("F j, Y g:i A", strtotime($poll['created_at'])) ?></p>
                                </div>
                            </div>

                            <?php
                            $pollData = json_encode([
                                'id' => $poll['id'],
                                'question' => $poll['question'],
                                'options' => $poll['options'] ?? [],
                            ]);
                            ?>

                            <script>
                                const pollData<?= $poll['id'] ?> = <?= $pollData ?>;
                            </script>

                            <div class="menu-container">
                                <button class="menu-button">⋮</button>
                                <div class="dropdown-menu">
                                    <button type="button" class="dropdown-link" onclick='openEditPollModalUnique(<?= json_encode($poll) ?>)'>
                                        Edit
                                    </button>

                                    <a href="functions/delete-poll?id=<?= $poll['id'] ?>"
                                        onclick="return confirm('Are you sure you want to delete this poll?')"
                                        class="dropdown-link">Delete</a>
                                </div>

                            </div>
                        </div>

                        <div class="card-body-poll">
                            <p class="card-title poll-question"><?= htmlspecialchars($poll['question']) ?></p>


                            <form action="functions/vote-poll" method="POST">
                                <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">

                                <?php foreach ($poll['options'] as $opt): ?>
                                    <div class="form-check mb-2">
                                        <input
                                            class="form-check-input"
                                            type="radio"
                                            name="vote_option"
                                            value="<?= $opt['id'] ?>"
                                            id="option<?= $opt['id'] ?>"
                                            <?= $hasVoted ? 'disabled' : 'required' ?>
                                            <?= ($opt['id'] == ($poll['user_vote_option_id'] ?? 0)) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="option<?= $opt['id'] ?>">
                                            <?= htmlspecialchars($opt['option_text']) ?>
                                            <?php if (isset($opt['vote_count'])): ?>
                                                – <?= $opt['vote_count'] ?> vote<?= $opt['vote_count'] == 1 ? '' : 's' ?>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (!$hasVoted): ?>
                                    <br>
                                    <div class="poll-vote-section mt-3">
                                        <button type="submit" class="btn btn-primary btn-sm">Submit Vote</button>
                                    </div>
                                <?php else: ?>
                                    <br>
                                    <div class="poll-vote-section mt-3 text-muted">
                                        <i class="fas fa-check-circle me-1 text-success"></i>
                                        You’ve already voted in this poll.
                                    </div>
                                <?php endif; ?>

                            </form>
                        </div>

                    </div>


                <?php elseif ($item['type'] === 'announcement'): ?>


                    <?php
                    $announcement = $item['data'];
                    $id = $item['id'];
                    $avatar = !empty($announcement['profile_pic']) ? htmlspecialchars($announcement['profile_pic']) : 'default.png';
                    $fullName = htmlspecialchars($announcement['name'] ?? ($announcement['first_name'] . ' ' . $announcement['last_name']));
                    $comments = $comments_by_announcement[$id] ?? [];
                    $latestComment = end($comments);
                    ?>


                    <div class="announcement-card">
                        <div class="card-header">
                            <div class="user-info">
                                <img src="/uploads/profile_pics/<?= $avatar ?>" alt="Profile" class="avatar" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px;">
                                <div>
                                    <p class="name">
                                        <a href="/profile?user_id=<?= $announcement['user_id'] ?>" style="text-decoration:none; color:#000;">
                                            <?= $fullName ?>
                                        </a>
                                    </p>
                                    <p class="timestamp"><?= date("F j, Y g:i A", strtotime($announcement['created_at'])) ?></p>
                                </div>
                            </div>
                            <div class="menu-container">
                                <button class="menu-button">⋮</button>
                                <div class="dropdown-menu">
                                    <form action="edit-announcement-page" method="POST" style="display: block;">
                                        <input type="hidden" name="announcement_id" value="<?= $id ?>">
                                        <button type="submit" class="dropdown-link">Edit</button>
                                    </form>

                                    <a href="functions/delete-announcement?id=<?= $id ?>"
                                        onclick="return confirm('Are you sure you want to delete this announcement?')"
                                        class="dropdown-link">Delete</a>
                                </div>

                            </div>
                        </div>

                        <div class="card-body">
                            <p class="announcement-text"><?= nl2br(htmlspecialchars($announcement['description'])) ?></p>

                            <?php if (!empty($announcement['attachments'])): ?>
                                <div class="attachment-list" style="display:flex; flex-wrap:wrap; gap:15px;">
                                    <?php foreach ($announcement['attachments'] as $file): ?>
                                        <?php
                                        $path = $file['path'];
                                        $filename = $file['name'];
                                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                        $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $ext);
                                        $iconColor = '#4285f4';
                                        $extLabel = strtoupper($ext);

                                        // Viewer URLs
                                        $officeExts = ['docx', 'xlsx', 'pptx'];
                                        $gviewExts = ['doc', 'xls', 'ppt'];
                                        $publicUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$path";
                                        $viewerUrl = '';
                                        if ($ext === 'pdf') {
                                            $viewerUrl = $path; // Browser can open PDF directly
                                        } elseif (in_array($ext, $officeExts)) {
                                            $viewerUrl = "https://view.officeapps.live.com/op/embed.aspx?src=" . urlencode($publicUrl);
                                        } elseif (in_array($ext, $gviewExts)) {
                                            $viewerUrl = "https://docs.google.com/gview?url=" . urlencode($publicUrl) . "&embedded=true";
                                        }
                                        ?>
                                        <a href="<?= htmlspecialchars($viewerUrl ?: $path) ?>" target="_blank" style="
                                                    width: 140px;
                                                    text-decoration: none;
                                                    color: #333;
                                                    border-radius: 8px;
                                                    overflow: hidden;
                                                    border: 1px solid #ddd;
                                                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                                                    display: flex;
                                                    flex-direction: column;
                                                ">
                                            <?php if ($isImage): ?>
                                                <img src="<?= htmlspecialchars($path) ?>" alt="Image" style="width: 100%; height: 100px; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="height: 100px; background: #f3f3f3; display: flex; align-items: center; justify-content: center; position: relative;">
                                                    <i class="fas fa-file-alt" style="font-size: 36px; color: <?= $iconColor ?>;"></i>
                                                    <div style="
                                                        position: absolute;
                                                        bottom: 6px;
                                                        right: 6px;
                                                        background: <?= $iconColor ?>;
                                                        color: #fff;
                                                        padding: 2px 6px;
                                                        border-radius: 3px;
                                                        font-size: 10px;
                                                        font-weight: bold;
                                                    "><?= $extLabel ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <div style="
                                                        padding: 8px;
                                                        font-size: 13px;
                                                        text-align: center;
                                                        white-space: nowrap;
                                                        overflow: hidden;
                                                        text-overflow: ellipsis;
                                                        width: 100%;
                                                    "><?= htmlspecialchars($filename) ?></div>
                                            <?php if ($viewerUrl): ?>
                                                <div style="text-align:center; margin-top:4px;">
                                                    <span style="font-size:12px; color:#1976d2;">👁 View</span>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <br>

                        <!-- Comment Preview -->
                        <?php if ($latestComment): ?>
                            <div class="comment-preview" data-ann-id="<?= $id ?>" style="padding: 10px 20px; border-top: 1px solid #eee; font-size: 14px; color: #555;">
                                <strong><?= htmlspecialchars($latestComment['first_name'] . ' ' . $latestComment['last_name']) ?>:</strong>
                                <?= htmlspecialchars($latestComment['comment']) ?>
                                <a href="#" class="view-more-comments" data-ann-id="<?= $id ?>" style="display:block; margin-top:5px; font-size:13px; color:#007bff; text-decoration:none;">View more comments</a>
                            </div>
                        <?php endif; ?>
                        <div class="all-comments" id="comments-<?= $id ?>" style="display:none; padding:10px 20px; border-top:1px solid #f0f0f0; max-height: 200px; overflow-y: auto;">

                            <?php
                            // You should fetch user's reaction per comment before this loop if not included in $comments
                            foreach ($comments as $comment):
                                $userReaction = $comment['user_reaction_type'] ?? null; // You need to fetch this when preparing $comments
                            ?>
                                <div class="comment-item" data-comment-id="<?= $comment['id'] ?>" style="margin-bottom:12px; position:relative;">
                                    <strong><?= htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']) ?>:</strong>
                                    <?= htmlspecialchars($comment['comment']) ?>
                                    <div style="font-size: 12px; color: #888;"><?= date("F j, Y g:i A", strtotime($comment['created_at'])) ?></div>

                                    <!-- Reactions -->
                                    <div class="comment-reactions" style="margin-top: 4px; font-size: 14px;">
                                        <?php foreach (['like' => '👍', 'heart' => '❤️', 'haha' => '😂', 'angry' => '😡'] as $type => $icon): ?>
                                            <button class="react-btn <?= $userReaction === $type ? 'active' : '' ?>" data-type="<?= $type ?>"><?= $icon ?></button>
                                        <?php endforeach; ?>
                                        <span class="reaction-totals" style="margin-left: 10px; font-size: 13px; color:#444;">
                                            <?php
                                            $reactions = $comment['reaction_totals'] ?? [];
                                            echo implode(' | ', array_map(function ($type, $count) {
                                                return ucfirst($type) . ": $count";
                                            }, array_keys($reactions), $reactions));
                                            ?>
                                        </span>
                                    </div>

                                    <!-- Delete -->
                                    <?php if ($_SESSION['user_id'] == $comment['user_id'] || $_SESSION['user_id'] == $classroom_creator_id): ?>
                                        <button class="delete-comment-btn"
                                            title="Delete comment"
                                            style="position: absolute; top: 0; right: 0; background: none; border: none; color: #c00; font-weight: bold; cursor: pointer;">×</button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                        </div>

                        <!-- Comment Form -->
                        <div class="comment-form" style="padding: 10px 20px; border-top: 1px solid #eee;">
                            <form class="ajax-comment-form" data-announcement-id="<?= $id ?>">
                                <input type="text" name="comment_text" placeholder="Write a comment..." required
                                    style="width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px;">
                                <button type="submit"
                                    style="margin-top: 6px; padding: 5px 10px; font-size: 13px; border: none; background: #007bff; color: #fff; border-radius: 4px;">
                                    Post
                                </button>
                            </form>

                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <style>
            /* Modal Base */
            .custom-modal-unique {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
            }

            .custom-modal-content-unique {
                background-color: #fff;
                margin: 80px auto;
                padding: 20px 30px;
                border-radius: 8px;
                max-width: 600px;
                position: relative;
                box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            }

            .custom-close-button-unique {
                position: absolute;
                top: 15px;
                right: 20px;
                font-size: 22px;
                color: #888;
                cursor: pointer;
            }

            .custom-close-button-unique:hover {
                color: #000;
            }

            .form-group {
                margin-bottom: 16px;
            }

            .form-group label {
                display: block;
                font-weight: 600;
                margin-bottom: 6px;
            }

            .form-group input {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #ccc;
                border-radius: 5px;
            }

            .option-container {
                margin-top: 10px;
            }

            .option-item {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 10px;
            }

            .option-item input[type="text"] {
                flex: 1;
                padding: 8px;
                border: 1px solid #ccc;
                border-radius: 5px;
            }

            .option-item .remove-btn {
                background: #e74c3c;
                color: white;
                border: none;
                padding: 6px 10px;
                border-radius: 4px;
                cursor: pointer;
            }

            .option-item .remove-btn:hover {
                background: #c0392b;
            }

            .add-option-btn {
                display: inline-block;
                background-color: #eee;
                border: 1px solid #aaa;
                padding: 6px 12px;
                border-radius: 5px;
                margin-top: 5px;
                cursor: pointer;
            }

            .add-option-btn:hover {
                background-color: #ddd;
            }

            .form-actions {
                display: flex;
                justify-content: flex-end;
                margin-top: 20px;
                gap: 10px;
            }

            .btn-primary {
                background-color: #3498db;
                color: white;
                padding: 8px 16px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
            }

            .btn-primary:hover {
                background-color: #2980b9;
            }

            .btn-secondary {
                background-color: #ccc;
                color: #333;
                padding: 8px 16px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
            }

            .btn-secondary:hover {
                background-color: #bbb;
            }
        </style>

        <!-- ✅ Edit Poll Modal -->
        <div id="editPollModalUnique" class="custom-modal-unique">
            <div class="custom-modal-content-unique">
                <span class="custom-close-button-unique" onclick="closeEditPollModalUnique()">&times;</span>
                <h3>Edit Poll</h3>
                <br>
                <form id="editPollFormUnique" method="POST" action="functions/update-poll">
                    <input type="hidden" name="poll_id" id="edit-poll-id-unique">

                    <div class="form-group">
                        <label for="edit-poll-question-unique">Question</label>
                        <input type="text" name="question" id="edit-poll-question-unique" maxlength="250" required>
                    </div>

                    <div id="edit-poll-options-container-unique" class="option-container">
                        <!-- JS will populate option inputs here -->
                    </div>

                    <button type="button" class="add-option-btn" onclick="addEditOptionInputUnique()">+ Add Option</button>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Save Changes</button>
                        <button type="button" class="btn-secondary" onclick="closeEditPollModalUnique()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            function openEditPollModalUnique(pollData) {
                const modal = document.getElementById('editPollModalUnique');
                const pollIdField = document.getElementById('edit-poll-id-unique');
                const questionField = document.getElementById('edit-poll-question-unique');
                const optionsContainer = document.getElementById('edit-poll-options-container-unique');

                pollIdField.value = pollData.id;
                questionField.value = pollData.question || '';
                optionsContainer.innerHTML = '';

                if (Array.isArray(pollData.options)) {
                    pollData.options.forEach((opt, i) => {
                        addEditOptionInputUnique(opt.option_text);
                    });
                }

                modal.style.display = 'block';
            }

            function closeEditPollModalUnique() {
                document.getElementById('editPollModalUnique').style.display = 'none';
            }

            function addEditOptionInputUnique(value = '') {
                const container = document.getElementById('edit-poll-options-container-unique');
                const div = document.createElement('div');
                div.className = 'option-item';

                const input = document.createElement('input');
                input.type = 'text';
                input.name = 'options[]';
                input.required = true;
                input.value = value;

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-btn';
                removeBtn.textContent = 'Remove';
                removeBtn.onclick = () => div.remove();

                div.appendChild(input);
                div.appendChild(removeBtn);
                container.appendChild(div);
            }

            window.onclick = function(event) {
                const modal = document.getElementById('editPollModalUnique');
                if (event.target === modal) {
                    closeEditPollModalUnique();
                }
            };
        </script>




        <script>
            document.querySelectorAll('.view-more-comments').forEach(el => {
                el.addEventListener('click', e => {
                    e.preventDefault();
                    const id = el.getAttribute('data-ann-id');
                    const container = document.getElementById('comments-' + id);
                    if (container) container.style.display = 'block';
                    el.style.display = 'none';
                });
            });
        </script>
        <script>
            document.querySelectorAll('.ajax-comment-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const annId = this.getAttribute('data-announcement-id');
                    const commentInput = this.querySelector('input[name="comment_text"]');
                    const commentText = commentInput.value.trim();
                    if (!commentText) return;

                    fetch('functions/add-announcement-comment', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                announcement_id: annId,
                                user_id: <?= json_encode($_SESSION['user_id']) ?>,
                                comment_text: commentText
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                const commentContainer = document.getElementById('comments-' + annId);
                                const preview = document.querySelector(`.comment-preview[data-ann-id="${annId}"]`);

                                if (preview) preview.style.display = 'none';
                                if (commentContainer) commentContainer.style.display = 'block';

                                // Build new comment element
                                const newComment = document.createElement('div');
                                newComment.classList.add('comment-item');
                                newComment.setAttribute('data-comment-id', data.comment_id);
                                newComment.style.marginBottom = '12px';
                                newComment.style.position = 'relative';

                                newComment.innerHTML = `
                    <strong>${data.first_name} ${data.last_name}:</strong> ${data.comment_text}
                    <div style="font-size: 12px; color: #888;">${data.created_at}</div>
                    <div class="comment-reactions" style="margin-top: 4px; font-size: 14px;">
                        ${['like', 'heart', 'haha', 'angry'].map(type => `
                            <button class="react-btn ${data.user_reaction_type === type ? 'active' : ''}" data-type="${type}">
                                ${type === 'like' ? '👍' : type === 'heart' ? '❤️' : type === 'haha' ? '😂' : '😡'}
                            </button>
                        `).join('')}
                        <span class="reaction-totals" style="margin-left: 10px; font-size: 13px; color:#444;">
                            ${Object.entries(data.reaction_totals || {}).map(([k, v]) => `
                                $ {
                                    k.charAt(0).toUpperCase() + k.slice(1)
                                }: $ {
                                    v
                                }
                                `).join(' | ')}
                        </span>
                    </div>

                    ${data.can_delete ? `
                        <button class="delete-comment-btn"
                            title="Delete comment"
                            style="position: absolute; top: 0; right: 0; background: none; border: none; color: #c00; font-weight: bold; cursor: pointer;">×</button>
                    ` : ''}
                `;

                                commentContainer.appendChild(newComment);

                                // Smooth scroll to latest
                                commentContainer.scrollTo({
                                    top: commentContainer.scrollHeight,
                                    behavior: 'smooth'
                                });

                                commentInput.value = '';
                            } else {
                                alert(data.error || 'Error posting comment');
                            }
                        })
                        .catch(err => {
                            console.error('Comment error:', err);
                            alert('Something went wrong while adding the comment.');
                        });
                });
            });
        </script>
        <script>
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('react-btn')) {
                    const commentEl = e.target.closest('.comment-item');
                    const commentId = commentEl.getAttribute('data-comment-id');
                    const type = e.target.getAttribute('data-type');

                    if (!commentId || !type) return;

                    fetch('functions/react-comment', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                comment_id: commentId,
                                type
                            })
                        })
                        .then(async res => {
                            const text = await res.text();
                            try {
                                const data = JSON.parse(text);
                                if (data.success && data.totals) {
                                    // Update UI
                                    const buttons = commentEl.querySelectorAll('.react-btn');
                                    buttons.forEach(btn => {
                                        if (btn.getAttribute('data-type') === type) {
                                            // Toggle class: highlight only if added
                                            btn.classList.toggle('active');
                                        } else {
                                            btn.classList.remove('active');
                                        }
                                    });

                                    // If toggled off, make sure none are active
                                    const activeBtn = commentEl.querySelector('.react-btn.active');
                                    if (!activeBtn) {
                                        buttons.forEach(btn => btn.classList.remove('active'));
                                    }

                                    const totalText = Object.entries(data.totals)
                                        .map(([key, val]) => `${key.charAt(0).toUpperCase() + key.slice(1)}: ${val}`)
                                        .join(' | ');
                                    commentEl.querySelector('.reaction-totals').textContent = totalText;

                                    // Push success param for snackbar
                                    const url = new URL(window.location);
                                    url.searchParams.set('error', 'Reaction updated');
                                    window.history.pushState({}, '', url);
                                } else {
                                    const url = new URL(window.location);
                                    url.searchParams.set('error', data.error || 'Reaction failed');
                                    window.history.pushState({}, '', url);
                                }
                            } catch (err) {
                                console.error('Invalid JSON:', text);
                                const url = new URL(window.location);
                                url.searchParams.set('error', 'Invalid server response');
                                window.history.pushState({}, '', url);
                            }
                        })
                        .catch(err => {
                            console.error('Request failed:', err);
                            const url = new URL(window.location);
                            url.searchParams.set('error', 'Request failed');
                            window.history.pushState({}, '', url);
                        });
                }
            });

            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('delete-comment-btn')) {
                    const commentEl = e.target.closest('.comment-item');
                    if (!commentEl) return;

                    const commentId = commentEl.getAttribute('data-comment-id');
                    if (!commentId) return;

                    if (confirm('Are you sure you want to delete this comment?')) {
                        // Redirect to delete-comment with POST via a hidden form
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'functions/delete-comment';

                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'comment_id';
                        input.value = commentId;
                        form.appendChild(input);

                        document.body.appendChild(form);
                        form.submit(); // 👈 triggers PHP header('Location: ...')
                    }
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