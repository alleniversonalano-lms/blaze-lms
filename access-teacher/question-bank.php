<?php
session_start();

echo "<script>
    window.user_id = '{$_SESSION['user_id']}';
    window.first_name = '{$_SESSION['first_name']}';
    window.last_name = '{$_SESSION['last_name']}';
    window.ann_course_id = '{$_SESSION['ann_course_id']}';
</script>";

require_once($_SERVER['DOCUMENT_ROOT'] . '/access-teacher/functions/history_logger.php');

logUserHistory("visited", "Question Bank"); // You can customize this per page

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
    header("Location: question-bank");
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

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .loading {
            background: white;
            padding: 20px 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            font-weight: 500;
            color: #333;
        }

        .search-input {
                flex: 1;
                padding: 10px 15px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 0.95rem;
                transition: border-color 0.3s;
            }

            .search-input:focus {
                outline: none;
                border-color: #b71c1c;
                box-shadow: 0 0 0 2px rgba(183, 28, 28, 0.1);
            }

            .filter-select {
                min-width: 150px;
                padding: 10px 15px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 0.95rem;
                background-color: white;
                cursor: pointer;
                transition: border-color 0.3s;
            }

            .filter-select:focus {
                outline: none;
                border-color: #b71c1c;
                box-shadow: 0 0 0 2px rgba(183, 28, 28, 0.1);
            }

            .add-question-btn {
                background-color: #b71c1c;
                color: white;
                padding: 12px 24px;
                border: none;
                border-radius: 6px;
                font-size: 1rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                transition: all 0.3s ease;
                margin-bottom: 20px;
            }

            .add-question-btn:hover {
                background-color: #d32f2f;
                transform: translateY(-1px);
            }

            .add-question-btn:active {
                transform: translateY(0);
            }

            .add-question-btn i {
                font-size: 1.1rem;
            }

            .question-bank-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .btn {
                padding: 8px 16px;
                border: none;
                border-radius: 6px;
                font-size: 0.9rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .btn-sm {
                padding: 6px 12px;
                font-size: 0.85rem;
            }

            .btn-primary {
                background-color: #b71c1c;
                color: white;
            }

            .btn-danger {
                background-color: #dc3545;
                color: white;
            }

            .btn:hover {
                opacity: 0.9;
                transform: translateY(-1px);
            }

            .btn:active {
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

        <h2>Question Bank</h2>

        <!-- Snackbar Container -->
        <div id="snackbar">This is a sample snackbar message.</div>

        <br>

        <div class="container">
            <div class="question-bank-header">
                <div class="header-actions">
                    
                    <button class="btn btn-secondary" onclick="showCategoryModal()">Create Category</button>
                </div>
            </div>

            <div class="filters">
                <input type="text" id="searchInput" class="form-control" placeholder="Search questions...">
                <select id="typeFilter" class="form-control">
                    <option value="">All Types</option>
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="fill_blank">Fill in Blank</option>
                    <option value="formula">Formula</option>
                </select>
                <select id="categoryFilter" class="form-control">
                    <option value="">All Categories</option>
                </select>
            </div>

            <div id="questionsList" class="questions-grid"></div>

            <!-- Category Modal -->
            <!-- Create Category Modal -->
            <div id="categoryModal" class="modal-overlay">
                <div class="modal-content">
                    <h2>Create New Category</h2>
                    <input type="text" id="categoryName" placeholder="Enter category name">
                    <div class="modal-buttons">
                        <button class="modal-cancel" onclick="hideCategoryModal()">Cancel</button>
                        <button class="modal-save" onclick="saveCategory()">Save</button>
                    </div>
                </div>
            </div>

            <!-- Edit Category Modal -->
            <div id="editCategoryModal" class="modal-overlay">
                <div class="modal-content">
                    <h2>Edit Category Name</h2>
                    <input type="text" id="editCategoryName" placeholder="Enter new category name">
                    <input type="hidden" id="editCategoryId">
                    <div class="modal-buttons">
                        <button class="modal-cancel" onclick="hideEditCategoryModal()">Cancel</button>
                        <button class="modal-save" onclick="updateCategory()">Update</button>
                    </div>
                </div>
            </div>

            <!-- Move Question Modal -->
            <div id="moveQuestionModal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Move Question to Category</h2>
                        <br>
                        
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="targetCategory" class="form-label">Select Category</label>
                            <select id="targetCategory" class="form-control">
                                <option value="uncategorized">Uncategorized</option>
                                <!-- Categories will be populated dynamically -->
                            </select>
                        </div>
                    </div>
                    <br>
                    <hr>
                    <br>
                    <div class="modal-buttons">
                        <button class="modal-cancel" onclick="hideMoveQuestionModal()">Cancel</button>
                        <button class="modal-save" onclick="moveQuestion()">Move</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- All JavaScript code in one place -->
    <script>
            "use strict";
            // Global variables
            const currentCourseId = '<?php echo $course_id; ?>';
            let questions = [];
            let categories = [];
            let filteredQuestions = [];

            // Initialize on page load
            document.addEventListener('DOMContentLoaded', function() {
                loadQuestions();
            });

            function editQuestion(questionId) {
                const question = questions.find(q => q.id === questionId);
                if (!question) {
                    showSnackbar('Question not found');
                    return;
                }
                
                // Store the current question ID in the session
                sessionStorage.setItem('editQuestionId', questionId);
                
                // Redirect to the question edit page
                window.location.href = 'edit-assessment-new.?id=' + questionId;

            }

                // Category Modal Functions
                function showCategoryModal() {
                    document.getElementById('categoryModal').style.display = 'block';
                }

                function hideCategoryModal() {
                    document.getElementById('categoryModal').style.display = 'none';
                }

                function showEditCategoryModal(categoryId) {
                    const category = categories.find(c => c.id === categoryId);
                    if (!category) {
                        showSnackbar('Category not found');
                        return;
                    }

                    document.getElementById('editCategoryId').value = categoryId;
                    document.getElementById('editCategoryName').value = category.name;
                    document.getElementById('editCategoryModal').style.display = 'block';
                }

                function hideEditCategoryModal() {
                    document.getElementById('editCategoryModal').style.display = 'none';
                }

                function updateCategory() {
                    const categoryId = document.getElementById('editCategoryId').value;
                    const newName = document.getElementById('editCategoryName').value.trim();

                    if (!categoryId || !newName) {
                        showSnackbar('Please enter a category name');
                        return;
                    }

                    if (!currentCourseId) {
                        showSnackbar('Error: No course selected');
                        return;
                    }

                    // Show loading state
                    document.getElementById('questionsList').innerHTML = '<div class="loading">Updating category...</div>';

                    // Load current data
                    fetch('question_bank.json?' + new Date().getTime())
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Failed to load question bank data');
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Check for duplicate names
                            const isDuplicate = data.categories.some(c => 
                                c.id !== categoryId && 
                                c.name.toLowerCase() === newName.toLowerCase() && 
                                String(c.courseId) === String(currentCourseId)
                            );

                            if (isDuplicate) {
                                throw new Error('A category with this name already exists');
                            }

                            // Filter out the old category and add the updated one
                            const updatedCategories = data.categories.filter(c => c.id !== categoryId);
                            updatedCategories.push({
                                id: categoryId,
                                name: newName,
                                courseId: currentCourseId
                            });

                            // Save updated data
                            return fetch('save_question_bank', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    questions: data.questions,
                                    categories: updatedCategories
                                })
                            });
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                throw new Error(data.error || 'Failed to update category');
                            }

                            // Update local categories array
                            categories = categories.map(c => 
                                c.id === categoryId ? { ...c, name: newName } : c
                            );

                            // Update UI
                            renderQuestions(questions);
                            populateCategoryFilter();
                            hideEditCategoryModal();
                            showSnackbar('Category updated successfully');
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showSnackbar(error.message || 'Failed to update category');
                            loadQuestions();
                        });
                }

            function saveCategory() {
                const categoryName = document.getElementById('categoryName').value.trim();
                if (!categoryName) {
                    showSnackbar('Please enter a category name');
                    return;
                }

                if (!currentCourseId) {
                    showSnackbar('Error: No course selected');
                    return;
                }

                const newCategory = {
                    id: 'cat_' + Date.now(),
                    name: categoryName,
                    courseId: currentCourseId
                };

                console.log('Creating new category:', newCategory);

                // Show loading state
                document.getElementById('questionsList').innerHTML = '<div class="loading">Saving category...</div>';

                // Load current data
                fetch('question_bank.json?' + new Date().getTime()) // Add timestamp to prevent caching
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Failed to load question bank data');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Current data:', data);
                        // Ensure data structure with existing data
                        const existingData = {
                            categories: Array.isArray(data.categories) ? data.categories : [],
                            questions: Array.isArray(data.questions) ? data.questions : []
                        };
                        
                        // Check for duplicate category names in this course
                        const isDuplicate = existingData.categories.some(c => 
                            c.name.toLowerCase() === categoryName.toLowerCase() && 
                            String(c.courseId) === String(currentCourseId)
                        );
                        
                        if (isDuplicate) {
                            throw new Error('A category with this name already exists');
                        }
                        
                        // Add new category to existing categories
                        existingData.categories.push(newCategory);
                        console.log('Updated data:', existingData);

                        // Save back to file with all existing data
                        return fetch('save_question_bank', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(existingData)
                        });
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.error || 'Failed to save category');
                        }
                        
                        // Update local data
                        categories = [...categories, newCategory];
                        
                        // Reset and close modal
                        hideCategoryModal();
                        document.getElementById('categoryName').value = '';
                        
                        // Force a fresh load of questions and categories
                        setTimeout(() => {
                            loadQuestions();
                            showSnackbar('Category created successfully');
                        }, 500); // Small delay to ensure the file is updated
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showSnackbar(error.message || 'Failed to create category');
                        // Reset loading state
                        loadQuestions();
                    });
            }



            // Function to format question type names for display
            function getTypeName(type) {
                switch(type) {
                    case 'multiple_choice':
                        return 'Multiple Choice';
                    case 'fill_blank':
                        return 'Fill in Blank';
                    case 'formula':
                        return 'Formula';
                    default:
                        return 'Unknown Type';
                }
            }

            // Function to format math content with proper display
            function formatPreviewContent(question) {
                if (!question) return '';
                
                let html = '';
                
                // Add question text for all types except fill-in-blanks
                if (question.type !== 'fill_blank' && question.content) {
                    html += formatMathContent(question.content);
                }
                
                // Type-specific previews
                switch (question.type) {
                    case 'multiple_choice':
                        html += '<div class="question-settings">';
                        html += `<div class="question-setting">
                            <i>⚙️</i> ${question.isMultipleAnswer ? 'Multiple answers allowed' : 'Single answer only'}
                        </div>`;
                        html += '</div>';
                        html += '<div class="mcq-choices">';
                        question.choices.forEach((choice, index) => {
                            html += `
                                <div class="mcq-choice ${question.correctAnswers.includes(index) ? 'correct' : ''}">
                                    <div class="mcq-marker"></div>
                                    <div class="mcq-text">${formatMathContent(choice)}</div>
                                </div>`;
                        });
                        html += '</div>';
                        break;

                    case 'fill_blank':
                        html += '<div class="fill-blanks-preview">';
                        if (question.question_text || question.blankText) {
                            const questionText = question.question_text || question.blankText;
                            
                            // First show the full text with numbered blanks
                            html += '<div class="text-with-blanks">';
                            let blankCount = 0;
                            html += formatMathContent(questionText.replace(/\[blank\]/g, () => {
                                blankCount++;
                                return `<span class="blank-slot">Blank ${blankCount}</span>`;
                            }));
                            html += '</div>';

                            // Show answers and settings for each blank
                            if ((question.blanks && question.blanks.length > 0) || 
                                (question.answers && JSON.parse(question.answers || '[]').length > 0)) {
                                
                                const answers = question.blanks || JSON.parse(question.answers || '[]');
                                html += '<div class="blanks-answers">';
                                
                                answers.forEach((blankAnswers, blankIndex) => {
                                    const answers = Array.isArray(blankAnswers) ? blankAnswers : 
                                        (blankAnswers.answers || [blankAnswers.text || blankAnswers]);
                                        
                                    if (answers && answers.length > 0) {
                                        html += `
                                            <div class="blank-answer-group">
                                                <div class="blank-header">
                                                    <div class="blank-number">Blank ${blankIndex + 1}</div>
                                                    <div class="question-settings">
                                                        ${answers.length > 1 ? 
                                                            '<div class="question-setting"><i>✓</i> Multiple answers accepted</div>' 
                                                            : ''}
                                                    </div>
                                                </div>
                                                <div class="blank-answers-list">`;
                                        
                                        answers.forEach((answer, answerIndex) => {
                                            const answerText = typeof answer === 'object' ? answer.text : answer;
                                            if (answerText && answerText.trim()) {
                                                const isCaseSensitive = 
                                                    (question.caseSensitive && 
                                                    question.caseSensitive[blankIndex] && 
                                                    question.caseSensitive[blankIndex][answerIndex]) ||
                                                    (answer.caseSensitive);
                                                
                                                const isPrimary = 
                                                    answerIndex === 0 || 
                                                    answer.isPrimary;
                                                
                                                html += `
                                                    <div class="blank-answer-item">
                                                        <div class="answer-text">${formatMathContent(answerText)}</div>
                                                        ${isCaseSensitive ? 
                                                            '<div class="case-sensitive-badge" title="Case Sensitive">Aa</div>' 
                                                            : ''}
                                                        ${isPrimary ? 
                                                            '<div class="primary-badge" title="Primary Answer">1°</div>' 
                                                            : ''}
                                                    </div>`;
                                            }
                                        });
                                        
                                        html += '</div></div>';
                                    }
                                });
                                html += '</div>';
                            }
                        }
                        html += '</div>';
                        break;

                    case 'formula':
                        html += '<div class="formula-preview">';
                        if (question.formula) {
                            html += formatMathContent(question.formula
                                .replace(/\*/g, '×')
                                .replace(/\//g, '÷')
                                .replace(/\^/g, '^'));

                            if (question.variables && Object.keys(question.variables).length > 0) {
                                html += '<div class="formula-variables">';
                                html += 'Variables: ';
                                Object.entries(question.variables).forEach(([name, range]) => {
                                    html += `<span class="variable">${name} ∈ [${range.min}, ${range.max}]</span> `;
                                });
                                html += '</div>';
                            }
                        }
                        html += '</div>';
                        break;
                }
                
                return html;
            }

            function formatMathContent(content) {
                if (!content) return '';
                
                // Basic sanitization
                const sanitized = content.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                
                // Handle math expressions
                return sanitized.replace(/\$(.*?)\$/g, (match, expr) => {
                    // For inline math expressions
                    return `<span class="math-expr">${expr}</span>`;
                }).replace(/\$\$(.*?)\$\$/g, (match, expr) => {
                    // For block math expressions
                    return `<div class="math-expr-block">${expr}</div>`;
                });
            }

            function renderChoices(choices, correctAnswers) {
                if (!Array.isArray(choices)) return '';

                var choicesHtml = choices.map(function(choice, index) {
                    return '<div class="choice-item ' + (correctAnswers.includes(index) ? 'correct' : '') + '">' +
                        (formatMathContent(choice) || 'Empty choice') +
                    '</div>';
                }).join('');

                return '<div class="choices-list">' + choicesHtml + '</div>';
            }

            function loadQuestions() {
                if (!currentCourseId) {
                    console.error('No course ID available');
                    showSnackbar('Error: No course selected');
                    return;
                }

                console.log('Loading questions...'); // Initial debug log
                fetch('question_bank.json?' + new Date().getTime()) // Add timestamp to prevent caching
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Failed to load question bank');
                        }
                        return response.json();
                    })
                    .catch(() => ({
                        questions: [],
                        categories: []
                    }))
                    .then(data => {
                        // Reset global arrays
                        categories.length = 0;
                        questions.length = 0;
                        
                        // Validate and filter categories first
                        const validCategories = (data.categories || []).filter(c => {
                            const isValid = c && c.id && c.name && String(c.courseId) === String(currentCourseId);
                            return isValid;
                        });
                        
                        // Push valid categories to global array
                        categories.push(...validCategories);
                        
                        // Then process and filter questions
                        const validQuestions = (data.questions || [])
                            .filter(q => String(q.courseId) === String(currentCourseId))
                            .map(q => {
                                // Deep clone the question to avoid reference issues
                                const questionCopy = JSON.parse(JSON.stringify(q));
                                
                                // Check if the question's category exists in this course
                                const categoryExists = categories.some(c => c.id === questionCopy.categoryId);
                                if (!categoryExists) {
                                    questionCopy.categoryId = 'uncategorized';
                                }
                                return questionCopy;
                            });
                        
                        // Push valid questions to global array
                        questions.push(...validQuestions);
                        
                        // Save any category fixes back to the file
                        const needsSave = data.questions.some(q => {
                            const matchingQ = validQuestions.find(pq => pq.id === q.id);
                            return matchingQ && matchingQ.categoryId !== q.categoryId;
                        });
                        
                        if (needsSave) {
                            const updatedData = {
                                categories: data.categories,
                                questions: data.questions.map(q => {
                                    const matchingQ = validQuestions.find(pq => pq.id === q.id);
                                    return matchingQ || q;
                                })
                            };
                            
                            fetch('save_question_bank', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(updatedData)
                            }).catch(err => console.error('Failed to save category fixes:', err));
                        }
                        
                        populateCategoryFilter();
                        renderQuestions(questions);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('questionsList').innerHTML = 
                            '<div class="error-state">Failed to load questions</div>';
                        showSnackbar('Failed to load questions: ' + error.message);
                    });
            }

            // Global variable to store the currently selected question for moving
            let selectedQuestionForMove = null;

            function showMoveQuestionModal(questionId) {
                selectedQuestionForMove = questionId;
                const modal = document.getElementById('moveQuestionModal');
                const select = document.getElementById('targetCategory');
                
                // Clear existing options except uncategorized
                select.innerHTML = '<option value="uncategorized">Uncategorized</option>';
                
                // Populate categories
                categories.forEach(category => {
                    if (category && category.id) {
                        const question = questions.find(q => q.id === questionId);
                        // Don't show current category
                        if (question && category.id !== question.categoryId) {
                            select.innerHTML += `<option value="${category.id}">${category.name}</option>`;
                        }
                    }
                });
                
                modal.style.display = 'block';
            }

            function hideMoveQuestionModal() {
                document.getElementById('moveQuestionModal').style.display = 'none';
                selectedQuestionForMove = null;
            }

            function moveQuestion() {
                if (!selectedQuestionForMove) {
                    showSnackbar('No question selected to move');
                    return;
                }

                const targetCategoryId = document.getElementById('targetCategory').value;
                const questionIndex = questions.findIndex(q => q.id === selectedQuestionForMove);
                
                if (questionIndex === -1) {
                    showSnackbar('Question not found');
                    return;
                }

                // Create a deep copy of the questions array
                const updatedQuestions = JSON.parse(JSON.stringify(questions));
                
                // Update the question's category in our copy
                updatedQuestions[questionIndex].categoryId = targetCategoryId;

                // Show loading state
                document.getElementById('questionsList').innerHTML = '<div class="loading">Moving question...</div>';

                // Save to question bank with only the updated question
                fetch('save_question_bank', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        questions: [updatedQuestions[questionIndex]], // Send only the updated question
                        categories: categories
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to move question');
                    }
                    
                    // Update local state
                    questions = updatedQuestions;
                    
                    hideMoveQuestionModal();
                    renderQuestions(questions); // Update the display with our local state
                    showSnackbar('Question moved successfully');
                })
                .catch(error => {
                    console.error('Error moving question:', error);
                    showSnackbar(error.message || 'Failed to move question');
                    loadQuestions(); // Reset to server state on error
                });
            }

            function createQuestionCard(question, categoryName) {
                // Question info section (left side)
                const info = 
                    '<div class="question-info">' +
                        '<div class="badge-container">' +
                            '<span class="badge ' + question.type + '">' + getTypeName(question.type) + '</span>' +
                            '<span class="badge category">' + categoryName + '</span>' +
                            (question.ilo_number ? '<span class="badge ilo">ILO ' + question.ilo_number + '</span>' : '') +
                        '</div>' +
                        '<div class="meta-info">' +
                            '<div class="date">Added: ' + new Date(question.savedDate).toLocaleDateString() + '</div>' +
                            '<div class="author">By: ' + (question.savedBy?.name || 'Unknown') + '</div>' +
                        '</div>' +
                    '</div>';

                // Question content section (middle)
                const content = 
                    '<div class="question-content">' +
                        '<div class="question-title">' + (question.title || 'Untitled Question') + '</div>' +
                        '<div class="question-preview">' + 
                            formatPreviewContent(question) +
                        '</div>' +
                    '</div>';

                // Actions section (right side)
                const actions = 
                    '<div class="question-actions">' +
                        '<button onclick="editQuestion(\'' + question.id + '\')" class="btn btn-sm">Edit</button>' +
                        '<button onclick="showMoveQuestionModal(\'' + question.id + '\')" class="btn btn-sm btn-outline">Move</button>' +
                        '<button onclick="deleteQuestion(\'' + question.id + '\')" class="btn btn-sm btn-danger">Delete</button>' +
                    '</div>';

                return '<div class="question-card" data-type="' + question.type + '">' +
                    info +
                    content +
                    actions +
                    '</div>';
            }

            function createCategorySection(category, questions) {
                if (!category || !Array.isArray(questions)) {
                    console.error('Invalid category or questions:', { category, questions });
                    return '';
                }

                console.log('Creating category section:', { category, questionCount: questions.length });
                
                const questionCards = questions.map(q => createQuestionCard(q, category.name || 'Uncategorized')).join('');
                let categoryActions = '';
                
                if (category.id !== 'uncategorized') {
                    categoryActions = `
                        <div class="category-actions">
                            <button onclick="showEditCategoryModal('${category.id}')" class="btn btn-sm btn-primary category-edit-btn">
                                Edit Name
                            </button>
                            <button onclick="deleteCategory('${category.id}')" class="btn btn-sm btn-danger category-delete-btn">
                                Delete Category
                            </button>
                        </div>`;
                }
                
                // Get stored collapse state
                const isCollapsed = sessionStorage.getItem('category_' + category.id + '_collapsed') === 'true';
                return '<div class="category-section' + (isCollapsed ? ' collapsed' : '') + '" data-category-id="' + category.id + '">' +
                    '<div class="category-header" onclick="toggleCategory(\'' + category.id + '\')">' +
                        '<h3 class="category-title">' +
                            '<span class="collapse-icon">▼</span>' +
                            (category.name || 'Uncategorized') +
                        '</h3>' +
                        categoryActions +
                    '</div>' +
                    '<div class="category-questions">' + questionCards + '</div>' +
                    '</div>';
            }

            function renderQuestions(questions) {
                const container = document.getElementById('questionsList');
                console.log('Rendering questions:', questions); // Debug questions being rendered
                console.log('Available categories:', categories); // Debug all available categories

                if (questions && questions.length > 0) {
                    // First, ensure every question has a valid category
                    const questionsWithCategories = questions.map(q => {
                        const category = q.categoryId ? categories.find(c => c.id === q.categoryId) : null;
                        console.log(`Question ${q.id} category lookup:`, { 
                            questionCategoryId: q.categoryId,
                            foundCategory: category ? category.name : 'not found'
                        });
                        return {
                            ...q,
                            categoryId: 'uncategorized' // Default all questions to uncategorized for now
                        };
                    });

                    // Initialize with uncategorized group
                    const groupedQuestions = {
                        uncategorized: {
                            name: 'Uncategorized',
                            questions: []
                        }
                    };

                    // Add all categories from our categories list
                    categories.forEach(cat => {
                        groupedQuestions[cat.id] = {
                            name: cat.name,
                            questions: []
                        };
                    });

                    // Group questions
                    questionsWithCategories.forEach(q => {
                        console.log('Processing question:', q); // Debug each question
                        const categoryId = q.categoryId || 'uncategorized';
                        
                        if (groupedQuestions[categoryId]) {
                            groupedQuestions[categoryId].questions.push(q);
                        } else {
                            // If somehow the category wasn't initialized, put it in uncategorized
                            groupedQuestions['uncategorized'].questions.push(q);
                        }
                    });

                    console.log('Final grouped questions:', groupedQuestions); // Debug grouped questions

                    const html = Object.entries(groupedQuestions)
                        .map(([categoryId, category]) => {
                            console.log('Creating section for category:', category.name); // Debug category section
                            return createCategorySection(
                                { id: categoryId, name: category.name },
                                category.questions
                            );
                        })
                        .join('');
                    
                    container.innerHTML = html;
                    console.log('Final HTML:', html); // Debug final HTML
                } else {
                    container.innerHTML = 
                        '<div class="empty-state">' +
                        '<div class="empty-icon">📚</div>' +
                        '<div class="empty-text">No questions in bank</div>' +
                        
                        '</div>';
                    console.log('No questions to display'); // Debug empty state
                }
            }

            function filterQuestions() {
                const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
                const typeFilter = document.getElementById('typeFilter').value;
                const categoryFilter = document.getElementById('categoryFilter').value;

                if (!questions || !Array.isArray(questions)) {
                    console.error('Invalid questions array');
                    return;
                }

                const filtered = questions.filter(q => {
                    // Basic validation
                    if (!q || typeof q !== 'object') return false;

                    // Search in all relevant fields
                    const matchesSearch = !searchTerm || [
                        q.title,
                        q.content,
                        q.ilo_description,
                        ...(q.choices || [])
                    ].some(field => 
                        field && field.toString().toLowerCase().includes(searchTerm)
                    );

                    // Type and category filtering
                    const matchesType = !typeFilter || q.type === typeFilter;
                    const matchesCategory = !categoryFilter || q.categoryId === categoryFilter;

                    return matchesSearch && matchesType && matchesCategory;
                });

                renderQuestions(filtered);
            }

            function showSnackbar(message) {
                const snackbar = document.getElementById('snackbar');
                snackbar.textContent = message;
                snackbar.classList.add('show');
                
                // Remove the show class after 3 seconds
                setTimeout(() => {
                    snackbar.classList.remove('show');
                }, 3000);
            }

            function deleteQuestion(questionId) {
                if (!confirm('Are you sure you want to delete this question?')) {
                    return;
                }

                // Find the question to delete
                const questionToDelete = questions.find(q => q.id === questionId);
                if (!questionToDelete) {
                    showSnackbar('Question not found');
                    return;
                }

                // Store current state for rollback if needed
                const previousQuestions = JSON.parse(JSON.stringify(questions));

                // Show loading state but keep the structure
                const questionsList = document.getElementById('questionsList');
                const loadingOverlay = document.createElement('div');
                loadingOverlay.className = 'loading-overlay';
                loadingOverlay.innerHTML = '<div class="loading">Deleting question...</div>';
                questionsList.appendChild(loadingOverlay);

                // Optimistically update UI
                questions = questions.filter(q => q.id !== questionId);
                renderQuestions(questions);

                // Load current data to ensure we have the latest state
                fetch('question_bank.json?' + new Date().getTime())
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Failed to load question bank data');
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Filter out the question to delete from the full dataset
                        const updatedQuestions = data.questions.filter(q => q.id !== questionId);
                        
                        // Send delete request
                        return fetch('save_question_bank', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                questions: updatedQuestions,
                                categories: data.categories
                            })
                        });
                    })
                    .then(async response => {
                        if (!response.ok) {
                            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                        }
                        
                        let result;
                        try {
                            const text = await response.text();
                            // Try to extract JSON from the response
                            const jsonMatch = text.match(/\{.*\}/s);
                            if (jsonMatch) {
                                result = JSON.parse(jsonMatch[0]);
                            } else {
                                throw new Error('Invalid JSON response');
                            }
                        } catch (e) {
                            console.error('Failed to parse response:', e);
                            throw new Error('Invalid server response');
                        }

                        if (!result.success) {
                            throw new Error(result.error || 'Failed to delete question');
                        }

                        // Remove loading overlay
                        loadingOverlay.remove();
                        showSnackbar('Question deleted successfully');

                        // Refresh category filter in case it was the last question in a category
                        populateCategoryFilter();
                    })
                    .catch(error => {
                        console.error('Error deleting question:', error);
                        // Rollback to previous state
                        questions = previousQuestions;
                        renderQuestions(questions);
                        // Remove loading overlay
                        loadingOverlay.remove();
                        showSnackbar(error.message || 'Failed to delete question');
                    });
            }

                function deleteCategory(categoryId) {
                    if (!categoryId || categoryId === 'uncategorized') {
                        showSnackbar('Cannot delete this category');
                        return;
                    }

                    if (!confirm('Are you sure you want to delete this category? Questions in this category will become uncategorized.')) {
                        return;
                    }

                    const currentCourseId = '<?php echo $course_id; ?>';
                    
                    // Show loading state
                    document.getElementById('questionsList').innerHTML = '<div class="loading">Deleting category...</div>';

                    console.log('Starting category deletion for ID:', categoryId);

                    // Load current data
                    fetch('question_bank.json')
                        .then(response => {
                            if (!response.ok) {
                                console.error('Failed to load question bank:', response.status, response.statusText);
                                throw new Error('Failed to load question bank data');
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Verify the category exists and belongs to current course
                            const categoryToDelete = (data.categories || []).find(c => 
                                c.id === categoryId && String(c.courseId) === String(currentCourseId)
                            );

                            if (!categoryToDelete) {
                                throw new Error('Category not found or unauthorized');
                            }

                            console.log('Sending delete request for category:', categoryId);
                            
                            // Send delete request
                            return fetch('save_question_bank', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    deleteCategory: categoryId,
                                    categories: data.categories.filter(c => c.id !== categoryId),
                                    questions: data.questions.map(q => 
                                        q.categoryId === categoryId ? {...q, categoryId: 'uncategorized'} : q
                                    )
                                })
                            });
                        })
                        .then(async response => {
                            if (!response.ok) {
                                throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                            }
                            
                            let result;
                            try {
                                const text = await response.text();
                                console.log('Raw server response:', text);

                                // Try to find a JSON string in the response
                                let jsonStr = text;
                                const jsonStart = text.indexOf('{');
                                const jsonEnd = text.lastIndexOf('}');
                                
                                if (jsonStart >= 0 && jsonEnd >= 0) {
                                    jsonStr = text.substring(jsonStart, jsonEnd + 1);
                                }

                                try {
                                    result = JSON.parse(jsonStr);
                                } catch (e) {
                                    // If direct parse fails, try cleaning the string
                                    const cleanText = text.replace(/<[^>]*>/g, '').trim();
                                    result = JSON.parse(cleanText);
                                }

                                if (!result || typeof result !== 'object') {
                                    throw new Error('Invalid response structure');
                                }

                                if (!result.success) {
                                    throw new Error(result.error || 'Failed to delete category');
                                }

                                console.log('Parsed result:', result);
                                
                                // Update local state
                                categories = categories.filter(c => c.id !== categoryId);
                                questions = questions.map(q => {
                                    if (q.categoryId === categoryId) {
                                        return { ...q, categoryId: 'uncategorized' };
                                    }
                                    return q;
                                });

                                // Refresh UI
                                renderQuestions(questions);
                                populateCategoryFilter();
                                showSnackbar('Category deleted successfully');
                                return result;
                            } catch (e) {
                                console.error('Response parsing error:', e);
                                console.error('Server returned invalid format. Please check PHP errors.');
                                throw new Error('Failed to process server response');
                            }
                        })
                        .catch(error => {
                            console.error('Error deleting category:', error);
                            showSnackbar(error.message || 'Failed to delete category. Please try again.');
                            loadQuestions(); // Reload the questions to get fresh data
                        });
                }            // Event listeners
            document.getElementById('searchInput').addEventListener('input', filterQuestions);
            document.getElementById('typeFilter').addEventListener('change', filterQuestions);
            document.getElementById('categoryFilter').addEventListener('change', filterQuestions);

    
    
        // Global variables
        

        // Utility Functions


        function getTypeName(type) {
            const types = {
                'multiple_choice': 'Multiple Choice',
                'fill_blank': 'Fill in Blank',
                'formula': 'Formula'
            };
            return types[type] || 'Unknown Type';
        }

        function formatMathContent(content) {
            if (!content) return '';
            const sanitized = content.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return sanitized.replace(/\$(.*?)\$/g, (_, expr) => `<span class="math-expr">${expr}</span>`)
                          .replace(/\$\$(.*?)\$\$/g, (_, expr) => `<div class="math-expr-block">${expr}</div>`);
        }

        // Category Management Functions
        function showCategoryModal() {
            document.getElementById('categoryModal').style.display = 'block';
        }

        function hideCategoryModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }





        // Question Management Functions








        // UI Functions
            function renderQuestions(questionsList) {
            const container = document.getElementById('questionsList');
            
            if (!questionsList || questionsList.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📚</div>
                        <div class="empty-text">No questions in bank</div>
                        
                    </div>`;
                return;
            }

            const groupedQuestions = {
                uncategorized: { name: 'Uncategorized', questions: [] }
            };

            // Only use categories from the current course
            const courseCats = categories.filter(cat => 
                cat && cat.id && cat.name && String(cat.courseId) === String(currentCourseId)
            );

            courseCats.forEach(cat => {
                groupedQuestions[cat.id] = {
                    name: cat.name,
                    questions: []
                };
            });            questionsList.forEach(q => {
                const categoryId = q.categoryId || 'uncategorized';
                if (groupedQuestions[categoryId]) {
                    groupedQuestions[categoryId].questions.push(q);
                } else {
                    groupedQuestions['uncategorized'].questions.push(q);
                }
            });

            container.innerHTML = Object.entries(groupedQuestions)
                .map(([categoryId, category]) => createCategorySection(
                    { id: categoryId, name: category.name },
                    category.questions
                ))
                .join('');
        }

        function toggleCategory(categoryId) {
            const section = document.querySelector(`.category-section[data-category-id="${categoryId}"]`);
            if (!section) return;
            
            section.classList.toggle('collapsed');
            
            // Store the state in sessionStorage
            sessionStorage.setItem('category_' + categoryId + '_collapsed', 
                section.classList.contains('collapsed'));
            
            // Prevent the event from reaching the edit/delete buttons
            event.stopPropagation();
        }

                function populateCategoryFilter() {
            console.log('Populating category filter with categories:', categories);
            const select = document.getElementById('categoryFilter');
            select.innerHTML = '<option value="">All Categories</option><option value="uncategorized">Uncategorized</option>';
            
            // Only show categories that belong to this course
            const courseCats = categories.filter(cat => 
                cat && cat.id && cat.name && String(cat.courseId) === String(currentCourseId)
            );
            
            console.log('Filtered course categories:', courseCats);
            
            // Add sorted categories
            courseCats
                .sort((a, b) => a.name.localeCompare(b.name)) // Sort alphabetically
                .forEach(category => {
                    console.log(`Adding category to filter: ${category.name}`);
                    select.innerHTML += `<option value="${category.id}">${category.name}</option>`;
                });
            
            // Update any select elements for moving questions too
            const moveSelect = document.getElementById('targetCategory');
            if (moveSelect) {
                moveSelect.innerHTML = '<option value="uncategorized">Uncategorized</option>';
                courseCats.forEach(category => {
                    moveSelect.innerHTML += `<option value="${category.id}">${category.name}</option>`;
                });
            }
        }        // Initialize everything when the DOM is ready
        function initializePage() {
            document.getElementById('searchInput').addEventListener('input', filterQuestions);
            document.getElementById('typeFilter').addEventListener('change', filterQuestions);
            document.getElementById('categoryFilter').addEventListener('change', filterQuestions);

            if (currentCourseId) {
                loadQuestions();
            } else {
                console.error('No course ID available');
                showSnackbar('Error: No course selected');
            }
        }

        // Run initialization when DOM is loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializePage);
        } else {
            initializePage();
        }
        </script>

         <!-- Snackbar for notifications -->
         <div id="snackbar"></div>
    <style>
            .questions-grid {
                display: flex;
                flex-direction: column;
                gap: 16px;
                padding: 20px;
            }

            .question-card {
                background: white;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                padding: 16px;
                transition: all 0.2s;
                display: flex;
                align-items: start;
                gap: 20px;
            }

            .question-card:hover {
                transform: translateX(4px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }

            .badge {
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
                white-space: nowrap;
            }

            .badge.multiple_choice {
                background: #e8f5e8;
                color: #2e7d32;
            }

            .badge.fill_blank {
                background: #fff3e0;
                color: #f57c00;
            }

            .badge.formula {
                background: #fce4ec;
                color: #c2185b;
            }

            .badge.ilo {
                background: #e3f2fd;
                color: #0277bd;
            }

            .question-meta {
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid #eee;
                font-size: 12px;
                color: #666;
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }

            .category-section {
                margin-bottom: 30px;
                background: white;
                border-radius: 12px;
                padding: 20px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .category-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #b71c1c;
                cursor: pointer;
            }

            .category-header .collapse-icon {
                margin-right: 10px;
                transition: transform 0.3s ease;
            }

            .category-section.collapsed .collapse-icon {
                transform: rotate(-90deg);
            }

            .category-section.collapsed .category-questions {
                display: none;
            }

            .category-title {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .category-title {
                font-size: 1.2rem;
                color: #333;
                margin: 0;
            }

            .category-delete-btn {
                font-size: 0.9rem;
                padding: 4px 12px;
            }

            .category-questions {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .question-content {
                flex: 1;
                min-width: 0; /* Prevents content from breaking layout */
            }

            .question-title {
                font-weight: 600;
                margin-bottom: 8px;
                color: #2d3748;
            }

            .question-preview {
                color: #4a5568;
                margin-bottom: 12px;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 6px;
                border: 1px solid #e9ecef;
            }

            .fill-blanks-preview {
                margin: 10px 0;
            }

            .fill-blanks-preview {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 16px;
            }

            .fill-blanks-preview .text-with-blanks {
                font-size: 1em;
                line-height: 1.6;
                margin-bottom: 16px;
                padding: 12px;
                background: white;
                border-radius: 6px;
                border: 1px solid #e9ecef;
            }

            .fill-blanks-preview .blank-slot {
                display: inline-block;
                min-width: 80px;
                padding: 2px 10px;
                margin: 0 4px;
                background: #e3f2fd;
                border: 1px dashed #1976d2;
                border-radius: 4px;
                color: #1976d2;
                font-size: 0.9em;
            }

            .blanks-answers {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .blank-answer-group {
                background: white;
                border: 1px solid #e9ecef;
                border-radius: 6px;
                overflow: hidden;
            }

            .blank-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 12px;
                background: #f8f9fa;
                border-bottom: 1px solid #e9ecef;
            }

            .blank-number {
                font-weight: 500;
                color: #444;
            }

            .blank-answers-list {
                display: flex;
                flex-direction: column;
                gap: 1px;
                background: #f0f0f0;
            }

            .blank-answer-item {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 8px 12px;
                background: white;
                position: relative;
            }

            .answer-text {
                flex: 1;
                color: #333;
            }

            .case-sensitive-badge {
                padding: 2px 6px;
                background: #fff3e0;
                color: #f57c00;
                border-radius: 4px;
                font-size: 0.8em;
                font-weight: 500;
            }

            .primary-badge {
                padding: 2px 6px;
                background: #e8f5e9;
                color: #2e7d32;
                border-radius: 4px;
                font-size: 0.8em;
                font-weight: 500;
            }

            .formula-preview {
                font-family: monospace;
                padding: 8px 12px;
                background: #f8f9fa;
                border-radius: 4px;
                border: 1px solid #e9ecef;
                margin: 8px 0;
            }

            .formula-variables {
                margin-top: 8px;
                font-size: 0.9em;
                color: #666;
            }

            .formula-variables .variable {
                display: inline-block;
                margin-right: 12px;
                padding: 2px 8px;
                background: #e8eaf6;
                border-radius: 4px;
                font-size: 0.85em;
            }

                .mcq-choices {
                margin-top: 10px;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .question-settings {
                margin: 4px 0 8px;
                font-size: 0.85em;
                color: #666;
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }

            .question-setting {
                display: flex;
                align-items: center;
                gap: 4px;
                padding: 2px 8px;
                background: #f0f0f0;
                border-radius: 12px;
                color: #555;
            }

            .question-setting i {
                font-size: 14px;
                color: #666;
            }

            .mcq-choice {
                display: flex;
                gap: 8px;
                align-items: center;
                padding: 8px 12px;
                background: white;
                border: 1px solid #e9ecef;
                border-radius: 4px;
            }            .mcq-choice.correct {
                background: #e8f5e9;
                border-color: #66bb6a;
            }

            .mcq-marker {
                width: 18px;
                height: 18px;
                border: 2px solid #ccc;
                border-radius: 50%;
                position: relative;
            }

            .mcq-choice.correct .mcq-marker {
                border-color: #4caf50;
                background: #4caf50;
            }

            .mcq-choice.correct .mcq-marker::after {
                content: "";
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 8px;
                height: 8px;
                background: white;
                border-radius: 50%;
            }

            [data-multiple-answer="true"] .mcq-marker {
                border-radius: 4px;
            }

            [data-multiple-answer="true"] .mcq-choice.correct .mcq-marker::after {
                content: "✓";
                background: none;
                color: white;
                width: auto;
                height: auto;
                font-size: 12px;
            }

            .question-info {
                display: flex;
                flex-direction: column;
                gap: 8px;
                min-width: 200px;
            }

            .badge-container {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin-bottom: 8px;
            }

            .question-actions {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-left: auto;
            }

            .badge.category {
                background: #e3f2fd;
                color: #1976d2;
                margin-left: 8px;
            }

            .badge-container {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }

            .empty-state {
                text-align: center;
                padding: 40px;
                grid-column: 1 / -1;
                background: white;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .empty-icon {
                font-size: 48px;
                margin-bottom: 16px;
            }

            .choices-list {
                margin-top: 12px;
                border-top: 1px solid #eee;
                padding-top: 12px;
            }

            .choice-item {
                padding: 8px;
                margin: 4px 0;
                border-radius: 4px;
                background: #f8f9fa;
            }

            .choice-item.correct {
                background: #e8f5e8;
                color: #2e7d32;
            }

            .filters {
                display: flex;
                gap: 12px;
                margin-bottom: 20px;
                padding: 16px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            /* Button styling in question cards */
            .question-actions {
                margin-left: auto;
                display: flex;
                gap: 8px;
            }
            
            .question-actions .btn {
                padding: 8px 16px;
                font-size: 13px;
                min-width: 70px;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s ease;
                font-weight: 500;
                text-align: center;
            }

            .question-actions .btn-sm {
                background-color: #006400;
                border: none;
                color: white;
            }

            .question-actions .btn-sm:hover {
                background-color: #008000;
            }

            .question-actions .btn-danger {
                background-color: #dc3545;
                border: none;
                color: white;
            }

            .question-actions .btn-danger:hover {
                background-color: #c82333;
            }

            .question-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16px;
                gap: 12px;
            }

            /* Math expression styling */
            .math-expr {
                font-family: 'Times New Roman', serif;
                font-style: italic;
                padding: 0 4px;
                background-color: #f8f9fa;
                border-radius: 4px;
                display: inline-block;
            }

            .math-expr-block {
                font-family: 'Times New Roman', serif;
                font-style: italic;
                padding: 12px;
                margin: 8px 0;
                background-color: #f8f9fa;
                border-radius: 4px;
                text-align: center;
                display: block;
            }

            .loading {
                text-align: center;
                padding: 2rem;
                font-size: 1.1rem;
                color: #666;
                background: #f8f9fa;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                margin: 1rem 0;
            }

            .category-actions {
                display: flex;
                gap: 8px;
            }

            .category-edit-btn {
                background-color: #1976d2;
                color: white;
            }

            .category-edit-btn:hover {
                background-color: #1565c0;
            }

            #editCategoryModal .modal-content {
                max-width: 400px;
            }

            #editCategoryModal input[type="text"] {
                width: 100%;
                padding: 10px;
                margin: 10px 0;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }

            #editCategoryModal input[type="text"]:focus {
                outline: none;
                border-color: #1976d2;
                box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.1);
            }
        </style>
        </div>
    </body>
</html>
<?php
// End of file
?>