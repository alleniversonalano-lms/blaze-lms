<?php
session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/access-teacher/functions/history_logger.php');

logUserHistory("visited", "Questions"); // You can customize this per page

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
    <title>Question Bank</title>
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


        .question-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.07);
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
            z-index: 100;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            flex-wrap: wrap;
        }

        .question-info {
            max-width: 80%;
        }

        .question-type {
            display: inline-block;
            background-color: #f1f1f1;
            color: #b71c1c;
            font-size: 0.85rem;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .question-text {
            margin: 4px 0 6px;
            font-size: 1rem;
            color: #333;
        }

        .question-answer {
            font-size: 0.95rem;
            color: #444;
        }

        .question-footer {
            margin-top: 16px;
            text-align: right;
        }

        .edit-btn {
            background-color: #b71c1c;
            color: white;
            padding: 8px 18px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: background 0.3s;
        }

        .edit-btn:hover {
            background-color: #d32f2f;
        }

        .menu-container {
            position: relative;
        }

        .menu-button {
            background: none;
            border: none;
            font-size: 1.4rem;
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

        .menu-container.show .dropdown-menu {
            display: flex;
        }

        .choices {
            list-style: none;
            padding: 0;
            margin: 8px 0 0;
        }

        .choices li {
            padding: 6px 12px;
            border-radius: 6px;
            background-color: #f5f5f5;
            margin-bottom: 4px;
            font-size: 0.95rem;
            color: #444;
        }

        .choices .correct {
            background-color: #e3f2fd;
            font-weight: bold;
            border-left: 4px solid #2196f3;
        }

        /* Button Styling */
        .create-folder-btn {
            background-color: #800000;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .create-folder-btn:hover {
            background-color: #a30000;
        }

        /* Modal Styling */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background: #fff;
            padding: 20px;
            max-width: 400px;
            margin: 10% auto;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .modal-content h2 {
            margin-top: 0;
            font-size: 1.2rem;
        }

        .modal-content input[type="text"] {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            margin-top: 10px;
            margin-bottom: 15px;
        }

        .modal-content .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .modal-content button {
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .modal-save {
            background-color: #006400;
            color: white;
        }

        .modal-cancel {
            background-color: #ccc;
        }

        .modal-save:hover {
            background-color: #007d00;
        }

        .modal-cancel:hover {
            background-color: #aaa;
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
            <a href="question-bank" class="topbar-link active">Question Bank</a>
            <a href="history" class="topbar-link">History</a>
            <a href="people" class="topbar-link">People</a>
            <a href="grades" class="topbar-link">Grades</a>
            <a href="ilo" class="topbar-link">ILO</a>
        </div>

        <!-- Header -->
        <div class="header">
            <p><strong><?= $course_code ?>:</strong> <?= $course_title ?></p>
        </div>

        <h2>Questions</h2>
        <br>

        <div id="questionBankStreamContainer"></div>

        <?php $folder_id = isset($_GET['folder_id']); ?>

        <script>
            async function loadQuestionBankStream(folder_id) {

                async function loadQuestionBank(folder_id) {
                    const res = await fetch('functions/load_question_bank_by_folder?folder_id=' + folder_id);

                    // ✅ Log raw JSON
                    const rawText = await res.text();
                    console.log("Raw JSON:", rawText);

                    // ❗ Try to parse — if it fails, you'll know from above
                    let data;
                    try {
                        data = JSON.parse(rawText);
                    } catch (e) {
                        console.error("Failed to parse JSON:", e);
                        return;
                    }

                    const container = document.getElementById('questionBankStreamContainer');
                    container.innerHTML = '';

                    // continue with rendering cards...
                }



                const res = await fetch('functions/load_question_bank_by_folder?folder_id=' + folder_id);
                const data = await res.json();
                const container = document.getElementById('questionBankStreamContainer');
                container.innerHTML = '';

                data.forEach((q) => {
                    let extraContent = '';
                    const type = q.question_type;

                    if (type === 'multiple_choice') {
                        const options = JSON.parse(q.options || '[]');
                        const correct_mcq = JSON.parse(q.correct_mcq || '[]');
                        extraContent += '<ul class="option-list" style="margin-top: 8px; padding-left: 20px;">';
                        options.forEach((opt, i) => {
                            const isCorrect = correct_mcq.includes(i);
                            extraContent += `
                        <li style="margin-bottom: 4px; ${isCorrect ? 'font-weight: bold; color: #2e7d32;' : ''}">
                            ${opt}
                            ${isCorrect ? '<span style="margin-left: 8px; color: #2e7d32;">✔</span>' : ''}
                        </li>`;
                        });
                        extraContent += '</ul>';
                    } else if (type === 'identification') {
                        const answers = JSON.parse(q.correct_answers || '[]');
                        const caseFlags = JSON.parse(q.case_sensitive || '[]');

                        let answerList = '<ol class="answer-list" style="margin-top: 8px; padding-left: 20px;">';

                        answers.forEach((ans, idx) => {
                            const sensitive = caseFlags[idx] ?? false;
                            answerList += `
                        <li style="margin-bottom: 6px;">
                            ${ans}
                            ${sensitive ? '<span style="color: #e53935; margin-left: 8px;">(Case Sensitive)</span>' : ''}
                        </li>`;
                        });

                        answerList += '</ol>';
                        extraContent += `
                    <div class="question-answer" style="margin-top: 8px;">
                        <strong>Blank-Specific Answers:</strong>
                        ${answerList}
                    </div>`;
                        q.question_type = 'Fill in the Blanks';
                    } else if (type === 'formula') {
                        const formula = q.correct_answers ? JSON.parse(q.correct_answers)[0] : '';
                        extraContent += `
                    <div class="question-answer" style="margin-top: 8px;">
                        <strong>Formula:</strong>
                        <span style="color: #333;">${formula}</span>
                    </div>`;
                    }

                    const truncatedText = q.question_text.length > 150 ?
                        q.question_text.substring(0, 150) + '...' :
                        q.question_text;

                    const card = document.createElement('div');
                    card.className = 'question-card';
                    card.style = `
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 16px;
                margin-bottom: 16px;
                background: #fff;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            `;

                    card.innerHTML = `
                <div class="question-header">
                    <div class="question-info">
                        <span class="question-type" style="
                            background: #e0f2f1;
                            color: #00695c;
                            padding: 4px 8px;
                            border-radius: 4px;
                            font-size: 13px;
                            display: inline-block;
                            margin-bottom: 6px;
                        ">${q.question_type.replace(/_/g, ' ')}</span>
                        <div class="question-text" style="font-size: 16px; font-weight: 500; margin-bottom: 8px;" title="${q.question_text}">
                            ${truncatedText}
                        </div>
                        ${extraContent}
                    </div>
                </div>
                <div class="question-footer" style="display: flex; gap: 10px; margin-top: 16px;">
                    <button onclick="deleteBankQuestion(${q.id}, this)" class="delete-btn" style="padding: 6px 12px; background: #f44336; color: white; border: none; border-radius: 4px;">Delete</button>
                </div>
            `;

                    container.appendChild(card);
                });
            }

            function deleteBankQuestion(id, el) {
                if (!confirm("Are you sure you want to delete this question from the bank?")) return;
                fetch(`functions/delete_question_bank?id=${id}`, {
                    method: 'POST'
                }).then(res => res.json()).then(res => {
                    if (res.success) {
                        el.closest('.question-card').remove();
                    } else {
                        alert('Failed to delete.');
                    }
                });
            }

            window.addEventListener("DOMContentLoaded", () => {
                loadQuestionBankStream(folder_id); // Replace 1 with the desired folder_id
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


</body>

</html>