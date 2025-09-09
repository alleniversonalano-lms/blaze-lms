<?php
session_start();
header('Content-Type: application/json');
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id <= 0 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data) || empty($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    $conn->begin_transaction();

    foreach ($data as $question) {
        $assessment_id = intval($question['assessment_id']);
        $type = $question['type'];
        $text = trim($question['text']);
        $now = date('Y-m-d H:i:s');

        // Determine overall case sensitivity flag (for identification type only)
        $is_question_case_sensitive = 0;
        if ($type === 'identification' && !empty($question['case_sensitive'])) {
            $is_question_case_sensitive = in_array(true, $question['case_sensitive'], true) ? 1 : 0;
        }

        // Insert question with new fields
        $stmt = $conn->prepare("
            INSERT INTO questions (
                assessment_id, question_text, question_type, is_case_sensitive, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssis", $assessment_id, $text, $type, $is_question_case_sensitive, $user_id, $now);
        $stmt->execute();
        $question_id = $stmt->insert_id;
        $stmt->close();

        if ($type === 'multiple_choice') {
            $options = $question['options'] ?? [];
            $correct = $question['correct_mcq'] ?? [];

            foreach ($options as $i => $option_text) {
                $is_correct = in_array($i, $correct) ? 1 : 0;
                $opt_stmt = $conn->prepare("
                    INSERT INTO question_options (question_id, option_text, is_correct)
                    VALUES (?, ?, ?)
                ");
                $opt_stmt->bind_param("isi", $question_id, $option_text, $is_correct);
                $opt_stmt->execute();
                $opt_stmt->close();
            }
        } elseif ($type === 'identification') {
            $answers = $question['correct_answers'] ?? [];
            $flags = $question['case_sensitive'] ?? [];

            foreach ($answers as $i => $answer_text) {
                $is_case = !empty($flags[$i]) ? 1 : 0;
                $id_stmt = $conn->prepare("
                    INSERT INTO question_identification_answers (question_id, blank_index, answer_text, is_case_sensitive)
                    VALUES (?, ?, ?, ?)
                ");
                $id_stmt->bind_param("iisi", $question_id, $i, $answer_text, $is_case);
                $id_stmt->execute();
                $id_stmt->close();
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error saving questions',
        'error' => $e->getMessage()
    ]);
}
?>
