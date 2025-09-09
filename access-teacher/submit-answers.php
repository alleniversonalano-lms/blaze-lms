<?php
session_start();
require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';
if (!$user_id || strtolower($role) !== 'teacher') {
    header("Location: /login?error=Access+denied");
    exit;
}

$assessment_id = $_POST['assessment_id'] ?? null;
$answers = $_POST['answers'] ?? [];

if (!$assessment_id || empty($answers)) {
    die("Invalid submission.");
}

// Fetch all questions
$stmt = $conn->prepare("SELECT * FROM questions WHERE assessment_id = ?");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Grading
$score = 0;
$total = 0;
$gradingItems = [];

foreach ($questions as $q) {
    $qid = $q['id'];
    $type = $q['question_type'];

    if (!isset($answers[$qid])) continue;

    if ($type === 'multiple_choice') {
        $chk = $conn->prepare("SELECT COUNT(*) as c FROM question_options WHERE question_id = ? AND is_correct = 1");
        $chk->bind_param("i", $qid);
        $chk->execute();
        $c = $chk->get_result()->fetch_assoc()['c'];
        $isMultiple = $c > 1;

        $correct_stmt = $conn->prepare("SELECT option_text FROM question_options WHERE question_id = ? AND is_correct = 1");
        $correct_stmt->bind_param("i", $qid);
        $correct_stmt->execute();
        $correctOptions = array_column($correct_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'option_text');

        $studentAns = $answers[$qid];
        $studentAnsArr = is_array($studentAns) ? $studentAns : [$studentAns];

        sort($correctOptions);
        sort($studentAnsArr);

        $isCorrect = ($correctOptions == $studentAnsArr);
        $answerText = implode(', ', $studentAnsArr);

        $gradingItems[] = [
            'question_id' => $qid,
            'student_answer' => $answerText,
            'is_correct' => $isCorrect ? 1 : 0
        ];

        if ($isCorrect) $score++;
        $total++;
    } elseif ($type === 'identification') {
        $studentAnswers = $answers[$qid];

        $stmt = $conn->prepare("SELECT blank_index, answer_text, is_case_sensitive FROM question_identification_answers WHERE question_id = ?");
        $stmt->bind_param("i", $qid);
        $stmt->execute();
        $result = $stmt->get_result();

        $correctAnswers = [];
        while ($row = $result->fetch_assoc()) {
            $idx = (int)$row['blank_index'];
            $correctAnswers[$idx][] = [
                'text' => $row['answer_text'],
                'is_case_sensitive' => (bool)$row['is_case_sensitive']
            ];
        }

        foreach ($correctAnswers as $blankIndex => $validAnswers) {
            $studentInput = trim($studentAnswers[$blankIndex] ?? '');
            $blankCorrect = false;

            foreach ($validAnswers as $valid) {
                $blankCorrect = $valid['is_case_sensitive']
                    ? $studentInput === $valid['text']
                    : strcasecmp($studentInput, $valid['text']) === 0;
                if ($blankCorrect) break;
            }

            $gradingItems[] = [
                'question_id' => $qid,
                'student_answer' => $studentInput,
                'is_correct' => $blankCorrect ? 1 : 0
            ];

            if ($blankCorrect) $score++;
            $total++;
        }
    }
}

// Insert assessment attempt summary
$insert = $conn->prepare("INSERT INTO assessment_attempts (assessment_id, student_id, score, submitted_at, answers) VALUES (?, ?, ?, NOW(), ?)");
$jsonAnswers = json_encode($answers);
$insert->bind_param("iiis", $assessment_id, $user_id, $score, $jsonAnswers);
$insert->execute();
$attempt_id = $insert->insert_id;

// Insert each question/blank answer breakdown
$item_stmt = $conn->prepare("INSERT INTO assessment_attempt_items (attempt_id, question_id, student_answer, is_correct) VALUES (?, ?, ?, ?)");
foreach ($gradingItems as $item) {
    $item_stmt->bind_param(
        "iisi",
        $attempt_id,
        $item['question_id'],
        $item['student_answer'],
        $item['is_correct']
    );
    $item_stmt->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assessment Submitted</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f6f8;
            text-align: center;
            padding: 3rem;
        }
        .result-card {
            background: #fff;
            display: inline-block;
            padding: 2rem 3rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .score {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        .btn {
            padding: 0.6rem 1.5rem;
            font-size: 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            background: #007bff;
            color: #fff;
            text-decoration: none;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="result-card">
        <div class="score">Thank you! You scored <?= $score ?> out of <?= $total ?>.</div>
        <a href="view-score-breakdown?attempt_id=<?= $attempt_id ?>" class="btn">View Score Breakdown</a>
    </div>
</body>
</html>
