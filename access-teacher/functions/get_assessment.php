<?php
session_start();
header('Content-Type: application/json');

require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    $assessment_id = isset($data['id']) ? (int)$data['id'] : 0;
    $course_id = $_SESSION['ann_course_id'] ?? 0;

    if (!$assessment_id) {
        throw new Exception('Invalid assessment ID');
    }

    // Fetch assessment details
    $stmt = $conn->prepare("
        SELECT 
            a.id,
            a.title,
            a.instructions,
            a.quiz_type,
            a.time_limit,
            a.multiple_attempts,
            a.allowed_attempts,
            a.score_to_keep,
            a.file_path,
            c.course_title
        FROM assessments a
        JOIN courses c ON a.course_id = c.id
        WHERE a.id = ? AND a.course_id = ?
    ");

    $stmt->bind_param("ii", $assessment_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Assessment not found');
    }

    $assessment = $result->fetch_assoc();
    
    // Get the quiz content from JSON file
    if (!empty($assessment['file_path'])) {
        if (!file_exists($assessment['file_path'])) {
            throw new Exception('Quiz file not found: ' . $assessment['file_path']);
        }
        
        $quizContent = file_get_contents($assessment['file_path']);
        $quizData = json_decode($quizContent, true);
        
        if ($quizData === null) {
            throw new Exception('Invalid quiz file format');
        }
        
        // Merge quiz data with assessment data
        $assessment['questions'] = $quizData['questions'] ?? [];
        $assessment['metadata'] = $quizData['metadata'] ?? [];
    }
    
    echo json_encode($assessment);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'debug' => [
            'assessment_id' => $assessment_id ?? null,
            'course_id' => $course_id ?? null,
            'file_path' => $assessment['file_path'] ?? null
        ]
    ]);
}