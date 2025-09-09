<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

try {
    $quiz_id = $_GET['id'] ?? '';
    $course_id = $_SESSION['ann_course_id'] ?? 0;

    if (!$quiz_id || !$course_id) {
        throw new Exception('Invalid quiz ID or course ID');
    }

    // Build the path to the quiz file
    $quiz_file = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/' . $quiz_id . '.json';

    if (!file_exists($quiz_file)) {
        throw new Exception('Quiz not found');
    }

    $quiz_json = file_get_contents($quiz_file);
    if ($quiz_json === false) {
        throw new Exception('Failed to read quiz file');
    }

    $quiz_data = json_decode($quiz_json, true);
    if (!$quiz_data) {
        throw new Exception('Invalid quiz data');
    }

    // Verify the quiz belongs to the current course
    if ((int)$quiz_data['courseId'] !== (int)$course_id) {
        throw new Exception('Quiz does not belong to current course');
    }

    // Prepare the quiz data for the UI
    $js_quiz_data = [
        'id' => $quiz_id,
        'title' => $quiz_data['title'],
        'instructions' => $quiz_data['description'] ?? '',
        'timeLimit' => isset($quiz_data['options']['timeLimit']) ? (int)$quiz_data['options']['timeLimit'] : 30,
        'allowedAttempts' => isset($quiz_data['options']['attempts']) ? (int)$quiz_data['options']['attempts'] : 1,
        'currentAttempt' => 1, // TODO: Track attempts
        'scoreToKeep' => 'highest',
        'shuffleQuestions' => isset($quiz_data['options']['shuffleQuestions']) ? (bool)$quiz_data['options']['shuffleQuestions'] : false,
        'shuffleAnswers' => isset($quiz_data['options']['shuffleAnswers']) ? (bool)$quiz_data['options']['shuffleAnswers'] : false,
        'questions' => array_map(function($q, $index) {
            return [
                'id' => $q['id'],
                'index' => $index + 1,
                'type' => str_replace('_', '', $q['type']), // Convert multiple_choice to multiplechoice
                'title' => $q['title'],
                'text' => $q['content'],
                'points' => $q['points'],
                'options' => isset($q['choices']) ? array_map(function($choice, $i) use ($q) {
                    return [
                        'text' => $choice,
                        'is_correct' => in_array($i, $q['correctAnswers'])
                    ];
                }, $q['choices'], array_keys($q['choices'])) : [],
                'allow_multiple' => $q['isMultipleAnswer'] ?? false,
                'formula' => $q['formula'] ?? '',
                'variables' => $q['variables'] ?? [],
                'blanks' => isset($q['blanks']) ? array_map(function($answers) {
                    return ['answers' => is_array($answers) ? $answers : [$answers]];
                }, $q['blanks']) : [],
                'blankText' => $q['blankText'] ?? ''
            ];
        }, $quiz_data['questions'], array_keys($quiz_data['questions']))
    ];

    echo json_encode([
        'success' => true,
        'data' => $js_quiz_data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
