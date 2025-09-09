<?php
// Disable error reporting to prevent HTML errors in JSON response
error_reporting(0);
ini_set('display_errors', 0);

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
    
    // Get course details
    $stmt = $conn->prepare("SELECT course_code, course_title FROM courses WHERE id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $course_result = $stmt->get_result();
    $course_data = $course_result->fetch_assoc();
    
    // Initialize assessments array
    $assessments = [];

    // Read quizzes from JSON files - from assessment-list directory
    $quizzes_dir = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes';
    $attempts_dir = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/attempts';
    
    error_log("Loading quizzes from directory: " . $quizzes_dir);
    
    if (!is_dir($quizzes_dir)) {
        throw new Exception("Quizzes directory not found: " . $quizzes_dir);
    }
    
    $files = glob($quizzes_dir . '/*.json');
    if ($files === false) {
        throw new Exception("Failed to read quiz files from directory");
    }
    
    error_log("Found " . count($files) . " quiz files");
    
    foreach ($files as $file) {
        if (!is_readable($file)) {
            error_log("Cannot read file: " . $file);
            continue;
        }
        
        $content = file_get_contents($file);
        if ($content === false) {
            error_log("Failed to read content from: " . $file);
            continue;
        }
        
        $quiz_data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error in " . basename($file) . ": " . json_last_error_msg());
            continue;
        }
            
        if (!$quiz_data) {
            error_log("Invalid quiz data in " . basename($file));
            continue;
        }

        // Check course ID
        $quiz_course_id = isset($quiz_data['courseId']) ? (int)$quiz_data['courseId'] : 0;
        if ($quiz_course_id !== (int)$course_id) {
            continue;
        }
        
        // Check if quiz is published and available for students
        if (!isset($quiz_data['is_published']) || !$quiz_data['is_published']) {
            continue;
        }

        $file_name = basename($file);
        $quiz_id = pathinfo($file_name, PATHINFO_FILENAME);
        
        // Load student attempts for this quiz
        $attempts_file = $attempts_dir . '/' . $quiz_id . '_' . $user_id . '.json';
        $student_attempts = [];
        $latest_attempt = null;
        $best_attempt = null;
        $attempt_count = 0;
        
        if (file_exists($attempts_file)) {
            $attempts_content = file_get_contents($attempts_file);
            if ($attempts_content) {
                $student_attempts = json_decode($attempts_content, true) ?: [];
                $attempt_count = count($student_attempts);
                
                if ($attempt_count > 0) {
                    // Get the latest attempt
                    $latest_attempt = end($student_attempts);
                    
                    // Find the best attempt (highest score)
                    $best_attempt = $student_attempts[0];
                    foreach ($student_attempts as $attempt) {
                        if ($attempt['score'] > $best_attempt['score']) {
                            $best_attempt = $attempt;
                        }
                    }
                }
            }
        }
        
        // Determine which score to display based on quiz settings
        $score_to_keep = $quiz_data['options']['scoreToKeep'] ?? 'highest';
        $display_attempt = null;
        
        if ($latest_attempt) {
            switch ($score_to_keep) {
                case 'latest':
                    $display_attempt = $latest_attempt;
                    break;
                case 'highest':
                default:
                    $display_attempt = $best_attempt;
                    break;
            }
        }

        $assessment = [
            'id' => $quiz_id,
            'token' => null,
            'title' => $quiz_data['title'] ?? 'Untitled Quiz',
            'instructions' => $quiz_data['description'] ?? '',
            'quiz_type' => $quiz_data['type'] ?? '',
            'time_limit' => isset($quiz_data['options']['timeLimit']) ? (int)$quiz_data['options']['timeLimit'] : null,
            'multiple_attempts' => isset($quiz_data['options']['attempts']) && (int)$quiz_data['options']['attempts'] > 1,
            'allowed_attempts' => isset($quiz_data['options']['attempts']) ? (int)$quiz_data['options']['attempts'] : 1,
            'score_to_keep' => $score_to_keep,
            'is_published' => $quiz_data['is_published'] ?? 0,
            'questions' => json_encode($quiz_data['questions'] ?? []),
            'quiz_data' => json_encode($quiz_data),
            'created_at' => isset($quiz_data['created_at']) ? date('Y-m-d H:i:s', strtotime($quiz_data['created_at'])) : date('Y-m-d H:i:s', filemtime($file)),
            'course_code' => $course_data['course_code'] ?? '',
            'course_title' => $course_data['course_title'] ?? '',
            'assessment_type' => 'saved',
            'availability' => [
                'status' => 'available',
                'dueDate' => isset($quiz_data['options']['dueDate']) && isset($quiz_data['options']['dueTime']) ?
                    date('Y-m-d H:i:s', strtotime($quiz_data['options']['dueDate'] . ' ' . $quiz_data['options']['dueTime'])) : null,
                'availableFrom' => isset($quiz_data['options']['availableFromDate']) && isset($quiz_data['options']['availableFromTime']) ?
                    date('Y-m-d H:i:s', strtotime($quiz_data['options']['availableFromDate'] . ' ' . $quiz_data['options']['availableFromTime'])) : null,
                'availableUntil' => isset($quiz_data['options']['availableUntilDate']) && isset($quiz_data['options']['availableUntilTime']) ?
                    date('Y-m-d H:i:s', strtotime($quiz_data['options']['availableUntilDate'] . ' ' . $quiz_data['options']['availableUntilTime'])) : null
            ],
            'settings' => [
                'timeLimit' => isset($quiz_data['options']['timeLimit']) ? (int)$quiz_data['options']['timeLimit'] : null,
                'allowedAttempts' => isset($quiz_data['options']['attempts']) ? (int)$quiz_data['options']['attempts'] : 1,
                'scoreToKeep' => $score_to_keep,
                'shuffleQuestions' => isset($quiz_data['options']['shuffleQuestions']) ? (bool)$quiz_data['options']['shuffleQuestions'] : false,
                'shuffleAnswers' => isset($quiz_data['options']['shuffleAnswers']) ? (bool)$quiz_data['options']['shuffleAnswers'] : false
            ],
            
            // Add student attempt information
            'student_attempts' => [
                'count' => $attempt_count,
                'max_attempts' => isset($quiz_data['options']['attempts']) ? (int)$quiz_data['options']['attempts'] : 1,
                'has_attempts' => $attempt_count > 0,
                'can_retake' => $attempt_count < (isset($quiz_data['options']['attempts']) ? (int)$quiz_data['options']['attempts'] : 1),
                'latest_attempt' => $latest_attempt,
                'best_attempt' => $best_attempt,
                'display_attempt' => $display_attempt
            ]
        ];

        // Set timezone to Philippines/Manila
        date_default_timezone_set('Asia/Manila');
        
        // Calculate quiz availability status using Philippine time
        $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $now_timestamp = $now->getTimestamp();

        if ($assessment['availability']['availableFrom']) {
            $availableFrom = new DateTime($assessment['availability']['availableFrom'], new DateTimeZone('Asia/Manila'));
            if ($availableFrom->getTimestamp() > $now_timestamp) {
                $assessment['availability']['status'] = 'pending';
                goto availability_end;
            }
        }

        if ($assessment['availability']['availableUntil']) {
            $availableUntil = new DateTime($assessment['availability']['availableUntil'], new DateTimeZone('Asia/Manila'));
            if ($availableUntil->getTimestamp() < $now_timestamp) {
                $assessment['availability']['status'] = 'expired';
                goto availability_end;
            }
        }

        if ($assessment['availability']['dueDate']) {
            $dueDate = new DateTime($assessment['availability']['dueDate'], new DateTimeZone('Asia/Manila'));
            if ($dueDate->getTimestamp() < $now_timestamp) {
                $assessment['availability']['status'] = 'overdue';
                goto availability_end;
            }
        }

        // Check if student has completed all attempts
        if ($attempt_count >= $assessment['student_attempts']['max_attempts'] && $assessment['student_attempts']['max_attempts'] != -1) {
            $assessment['availability']['status'] = 'completed';
        } else {
            $assessment['availability']['status'] = 'available';
        }
        
        availability_end:

        $assessments[] = $assessment;
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

} catch (Throwable $e) {
    // Log the full error details for debugging
    error_log('Load Assessments Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Set proper headers
    http_response_code(500);
    header('Content-Type: application/json');
    
    // Return a safe error message to the client
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load assessments. Please try again later.'
    ]);
}