<?php

session_start();

// Check login and role
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

$user_id = $_SESSION['user_id'];
$course_id = $_SESSION['ann_course_id'] ?? 0;

// Fetch course details
$course_code = '';
$course_title = '';

if ($course_id) {
    $course_stmt = $conn->prepare("
        SELECT c.course_code, c.course_title, c.user_id AS owner_id,
               EXISTS(SELECT 1 FROM course_collaborators WHERE course_id = ? AND teacher_id = ?) AS is_collaborator
        FROM courses c 
        WHERE c.id = ?
    ");
    $course_stmt->bind_param("iii", $course_id, $user_id, $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    
    if ($course_row = $course_result->fetch_assoc()) {
        $course_code = htmlspecialchars($course_row['course_code']);
        $course_title = htmlspecialchars($course_row['course_title']);
        $is_owner = $course_row['owner_id'] == $user_id;
        $is_collaborator = $course_row['is_collaborator'];
        
        // Fetch assessments
        $stmt = $conn->prepare("
            SELECT id, title, quiz_type, due_date, available_from, available_until
            FROM assessments 
            WHERE course_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $assessments = $stmt->get_result();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessments - <?= $course_code ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Add your existing CSS here -->
    <style>
        .assessment-list {
            margin-top: 20px;
        }
        .assessment-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .assessment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .assessment-title {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .assessment-type {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .type-practice {
            background: #e3f2fd;
            color: #1565c0;
        }
        .type-graded {
            background: #fbe9e7;
            color: #d84315;
        }
        .assessment-dates {
            font-size: 0.9rem;
            color: #666;
        }
        .assessment-actions {
            margin-top: 10px;
        }
        .btn {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
            margin-right: 10px;
        }
        .btn-primary {
            background: #b71c1c;
            color: white;
        }
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }
    </style>
</head>
<body>
    <!-- Include your sidebar and topbar here -->
    
    <div class="main-content">
        <div class="header">
            <p><strong><?= $course_code ?>:</strong> <?= $course_title ?></p>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Assessments</h2>
            <?php if ($is_owner || $is_collaborator): ?>
            <a href="create_assessment" class="btn btn-primary">Create New Assessment</a>
            <?php endif; ?>
        </div>

        <div class="assessment-list">
            <?php if (isset($assessments) && $assessments->num_rows > 0): ?>
                <?php while($assessment = $assessments->fetch_assoc()): ?>
                    <div class="assessment-card">
                        <div class="assessment-header">
                            <div class="assessment-title"><?= htmlspecialchars($assessment['title']) ?></div>
                            <span class="assessment-type type-<?= $assessment['quiz_type'] ?>">
                                <?= ucfirst($assessment['quiz_type']) ?>
                            </span>
                        </div>
                        <div class="assessment-dates">
                            <?php if ($assessment['due_date']): ?>
                                <div>Due: <?= date('M j, Y g:i A', strtotime($assessment['due_date'])) ?></div>
                            <?php endif; ?>
                            <?php if ($assessment['available_from']): ?>
                                <div>Available from: <?= date('M j, Y g:i A', strtotime($assessment['available_from'])) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="assessment-actions">
                            <a href="view_assessment?id=<?= $assessment['id'] ?>" class="btn btn-secondary">View</a>
                            <?php if ($is_owner || $is_collaborator): ?>
                            <a href="edit_assessment?id=<?= $assessment['id'] ?>" class="btn btn-secondary">Edit</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No assessments found for this course.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>