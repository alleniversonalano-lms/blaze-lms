<?php
session_start();
header('Content-Type: application/json');

require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

try {
    $course_id = $_SESSION['ann_course_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;

    if (!$course_id || !$user_id) {
        echo json_encode(['error' => 'Invalid course or user ID']);
        exit;
    }

    // Get the course ID from session
    $course_id = $_SESSION['ann_course_id'] ?? 0;
    
    // Get course details
    $stmt = $conn->prepare("SELECT course_code, course_title FROM courses WHERE id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $course_result = $stmt->get_result();
    $course_data = $course_result->fetch_assoc();
    
    // Initialize assessments array
    $assessments = [];
    
    // Read quizzes from shared assessments directory
    $quizzes_dir = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/';
    error_log("Loading quizzes from directory: " . $quizzes_dir);
    
    if (is_dir($quizzes_dir)) {
        $files = glob($quizzes_dir . '*.json');
        error_log("Found " . count($files) . " quiz files");
        
        foreach ($files as $file) {
            error_log("Processing file: " . basename($file));
            $content = file_get_contents($file);
            $quiz_data = json_decode($content, true);
            
            // Log quiz data for debugging
            error_log("Quiz data: " . print_r($quiz_data, true));
            
            // Skip if quiz data is invalid
            if (!$quiz_data) {
                error_log("Invalid quiz data in " . basename($file));
                continue;
            }

            // Debug course ID comparison - check both metadata and direct courseId
            $quiz_course_id = isset($quiz_data['courseId']) ? (int)$quiz_data['courseId'] : 0;
            error_log("Comparing course IDs - File: {$quiz_course_id}, Current: {$course_id}");
            
            if ($quiz_course_id !== (int)$course_id) {
                error_log("Course ID mismatch - skipping file");
                continue;
            }
            
            error_log("Quiz matches course - adding to list");
            
            $file_name = basename($file);
            $assessment = [
                'id' => pathinfo($file_name, PATHINFO_FILENAME),
                'uuid' => $quiz_data['id'], // Add UUID from filename
                'token' => null,
                'title' => $quiz_data['title'] ?? 'Untitled Quiz',
                'instructions' => $quiz_data['description'] ?? '',
                'quiz_type' => $quiz_data['type'] ?? '',
                'time_limit' => isset($quiz_data['options']['timeLimit']) ? (int)$quiz_data['options']['timeLimit'] : null,
                'multiple_attempts' => isset($quiz_data['options']['attempts']) && (int)$quiz_data['options']['attempts'] > 1,
                'allowed_attempts' => isset($quiz_data['options']['attempts']) ? (int)$quiz_data['options']['attempts'] : 1,
                'score_to_keep' => 'highest',
                'is_published' => $quiz_data['is_published'] ?? 0,  // Default to unpublished if not set

                // Add availability and due dates
                'due' => [
                    'date' => $quiz_data['dueDate'] ?? null,
                    'time' => $quiz_data['dueTime'] ?? null,
                    'datetime' => isset($quiz_data['dueDate'], $quiz_data['dueTime']) ? 
                        date('Y-m-d H:i:s', strtotime($quiz_data['dueDate'] . ' ' . $quiz_data['dueTime'])) : null
                ],
                'available' => [
                    'from_date' => $quiz_data['availableFromDate'] ?? null,
                    'from_time' => $quiz_data['availableFromTime'] ?? null,
                    'from_datetime' => isset($quiz_data['availableFromDate'], $quiz_data['availableFromTime']) ? 
                        date('Y-m-d H:i:s', strtotime($quiz_data['availableFromDate'] . ' ' . $quiz_data['availableFromTime'])) : null,
                    'until_date' => $quiz_data['availableUntilDate'] ?? null,
                    'until_time' => $quiz_data['availableUntilTime'] ?? null,
                    'until_datetime' => isset($quiz_data['availableUntilDate'], $quiz_data['availableUntilTime']) ? 
                        date('Y-m-d H:i:s', strtotime($quiz_data['availableUntilDate'] . ' ' . $quiz_data['availableUntilTime'])) : null
                ],

                'questions' => json_encode($quiz_data['questions'] ?? []),
                'quiz_data' => json_encode($quiz_data),
                'created_at' => isset($quiz_data['createdAt']) ? date('Y-m-d H:i:s', strtotime($quiz_data['createdAt'])) : date('Y-m-d H:i:s', filemtime($file)),
                'course_code' => $course_data['course_code'] ?? '',
                'course_title' => $course_data['course_title'] ?? '',
                'assessment_type' => 'saved'
            ];
            
            $assessments[] = $assessment;
        }
    }
    
    // Sort assessments by created_at
    usort($assessments, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    echo json_encode([
        'success' => true,
        'data' => $assessments,
        'course' => [
            'id' => $course_id,
            'code' => $course_data['course_code'] ?? '',
            'title' => $course_data['course_title'] ?? ''
        ]
    ]);

} catch (Exception $e) {
    error_log('Load Assessments Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}