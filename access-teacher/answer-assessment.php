<?php
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';
if (!$user_id || strtolower($role) !== 'teacher') {
    header("Location: /login?error=Access+denied");
    exit;
}

require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

$assessment_id = $_SESSION['current_assessment_id'] ?? null;

if (!$assessment_id) {
    die("No assessment selected.");
}

// Fetch assessment time limit
$time_stmt = $conn->prepare("SELECT time_limit FROM assessments WHERE id = ?");
$time_stmt->bind_param("i", $assessment_id);
$time_stmt->execute();
$time_result = $time_stmt->get_result();
$time_row = $time_result->fetch_assoc();
$durationMinutes = (int)($time_row['time_limit'] ?? 10);

// Persist start time in session per assessment
if (!isset($_SESSION['assessment_start_times'])) {
    $_SESSION['assessment_start_times'] = [];
}
if (!isset($_SESSION['assessment_start_times'][$assessment_id])) {
    $_SESSION['assessment_start_times'][$assessment_id] = time();
}
$startTime = $_SESSION['assessment_start_times'][$assessment_id];
$endTime = $startTime + ($durationMinutes * 60);

// Fetch all questions
$stmt = $conn->prepare("SELECT * FROM questions WHERE assessment_id = ?");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();
$questions = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Answer Assessment</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 850px;
            margin: 3rem auto;
            background: #fff;
            padding: 2rem 2.5rem;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            margin-bottom: 1rem;
        }
        .timer {
            text-align: right;
            font-size: 1rem;
            font-weight: bold;
            color: #d63333;
            margin-bottom: 1rem;
        }
        .question-block {
            margin-bottom: 2rem;
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 0.5rem;
        }
        input[type="text"] {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-bottom: 0.6rem;
        }
        .option-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
        }
        .option-item label {
            font-weight: normal;
        }
        button[type="submit"] {
            display: block;
            background: #007bff;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            margin: 1rem auto 0;
        }
        button[type="submit"]:hover {
            background: #0056b3;
        }
        @media (max-width: 600px) {
            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Assessment</h1>
    <div class="timer" id="timer">Time left: --:--</div>

    <form method="POST" action="submit-answers" id="assessmentForm">
        <input type="hidden" name="assessment_id" value="<?= htmlspecialchars($assessment_id) ?>">

        <?php foreach ($questions as $index => $q): ?>
            <div class="question-block">
                <label>Q<?= $index + 1 ?>. <?= nl2br(htmlspecialchars($q['question_text'])) ?></label>

                <?php if ($q['question_type'] === 'multiple_choice'):
                    $opt_stmt = $conn->prepare("SELECT option_text FROM question_options WHERE question_id = ?");
                    $opt_stmt->bind_param("i", $q['id']);
                    $opt_stmt->execute();
                    $opt_result = $opt_stmt->get_result();
                    $options = $opt_result->fetch_all(MYSQLI_ASSOC);

                    $chk_stmt = $conn->prepare("SELECT COUNT(*) as count FROM question_options WHERE question_id = ? AND is_correct = 1");
                    $chk_stmt->bind_param("i", $q['id']);
                    $chk_stmt->execute();
                    $isMultiple = $chk_stmt->get_result()->fetch_assoc()['count'] > 1;

                    $inputType = $isMultiple ? 'checkbox' : 'radio';
                    $inputName = $isMultiple ? "answers[{$q['id']}][]" : "answers[{$q['id']}]";

                    foreach ($options as $optIndex => $opt): ?>
                        <div class="option-item">
                            <input type="<?= $inputType ?>" name="<?= $inputName ?>"
                                   value="<?= htmlspecialchars($opt['option_text']) ?>"
                                   id="q<?= $q['id'] ?>_<?= $optIndex ?>">
                            <label for="q<?= $q['id'] ?>_<?= $optIndex ?>"><?= htmlspecialchars($opt['option_text']) ?></label>
                        </div>
                    <?php endforeach; ?>

                <?php elseif ($q['question_type'] === 'identification'):
                    $blank_stmt = $conn->prepare("SELECT DISTINCT blank_index FROM question_identification_answers WHERE question_id = ? ORDER BY blank_index ASC");
                    $blank_stmt->bind_param("i", $q['id']);
                    $blank_stmt->execute();
                    $blank_result = $blank_stmt->get_result();
                    while ($row = $blank_result->fetch_assoc()):
                        $blankIndex = (int)$row['blank_index']; ?>
                        <input type="text"
                               name="answers[<?= $q['id'] ?>][<?= $blankIndex ?>]"
                               placeholder="Answer for Blank <?= $blankIndex + 1 ?>"
                               required>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit">Submit Answers</button>
    </form>
</div>

<script>
    const endTime = <?= $endTime ?> * 1000;
    const timerEl = document.getElementById('timer');
    const form = document.getElementById('assessmentForm');

    function updateTimer() {
        const now = new Date().getTime();
        const remaining = endTime - now;

        if (remaining <= 0) {
            timerEl.textContent = 'Time\'s up!';
            form.submit();
        } else {
            const mins = Math.floor(remaining / 60000);
            const secs = Math.floor((remaining % 60000) / 1000);
            timerEl.textContent = `Time left: ${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
            setTimeout(updateTimer, 1000);
        }
    }

    updateTimer();
</script>

</body>
</html>
