<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'student') {
    header("Location: /login?error=Access+denied");
    exit;
}

$user_id = $_SESSION['user_id'];
$quizId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$quizId) {
    header('Location: assessments.php');
    exit();
}

// Load quiz data
$quizJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/' . $quizId . '.json';
if (!file_exists($quizJsonPath)) {
    header('Location: assessments?error=quiz_not_found');
    exit();
}

$quizData = json_decode(file_get_contents($quizJsonPath), true);
if (!$quizData) {
    header('Location: assessments?error=invalid_quiz');
    exit();
}

// Load attempts
$attemptsFile = $_SERVER['DOCUMENT_ROOT'] . '/assessment-list/quizzes/attempts/' . $quizId . '_' . $user_id . '.json';
$attempts = [];
if (file_exists($attemptsFile)) {
    $attempts = json_decode(file_get_contents($attemptsFile), true) ?: [];
}

$options = $quizData['options'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Attempts - <?php echo htmlspecialchars($quizData['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0374b5;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header h1 {
            margin: 0 0 10px 0;
            color: #2d3748;
        }

        .header .subtitle {
            color: #666;
            margin-bottom: 20px;
        }

        .quiz-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-item i {
            color: var(--primary-color);
        }

        .attempts-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .attempts-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .attempts-header h2 {
            margin: 0;
            color: #2d3748;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #025a8c;
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background: #f0f9ff;
        }

        .attempts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .attempts-table th,
        .attempts-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .attempts-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #2d3748;
        }

        .attempts-table tr:hover {
            background: #f8fafc;
        }

        .score-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 500;
        }

        .score-excellent {
            background: #dcfce7;
            color: var(--success-color);
        }

        .score-good {
            background: #fef3c7;
            color: #d97706;
        }

        .score-fair {
            background: #fed7aa;
            color: #ea580c;
        }

        .score-poor {
            background: #fecaca;
            color: var(--danger-color);
        }

        .no-attempts {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .no-attempts i {
            font-size: 48px;
            color: #cbd5e0;
            margin-bottom: 16px;
        }

        .attempt-actions {
            display: flex;
            gap: 8px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 13px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-timeout {
            background: #fef3c7;
            color: #d97706;
        }

        .status-completed {
            background: #dcfce7;
            color: var(--success-color);
        }

        .best-score {
            background: #eff6ff;
            border-left: 4px solid var(--primary-color);
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 6px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 4px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .attempts-table {
                font-size: 14px;
            }
            
            .attempts-table th,
            .attempts-table td {
                padding: 8px 6px;
            }
            
            .quiz-info {
                grid-template-columns: 1fr;
            }
            
            .attempts-header {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><?php echo htmlspecialchars($quizData['title']); ?></h1>
        <div class="subtitle">Quiz Attempt History</div>
        
        <div class="quiz-info">
            <div class="info-item">
                <i class="fas fa-tasks"></i>
                <span><?php echo count($quizData['questions']); ?> Questions</span>
            </div>
            <div class="info-item">
                <i class="fas fa-star"></i>
                <span><?php 
                    $totalPoints = 0;
                    foreach ($quizData['questions'] as $question) {
                        $totalPoints += intval($question['points'] ?? 1);
                    }
                    echo $totalPoints;
                ?> Total Points</span>
            </div>
            <div class="info-item">
                <i class="fas fa-clock"></i>
                <span><?php echo $options['timeLimit'] ?? 60; ?> Minutes</span>
            </div>
            <div class="info-item">
                <i class="fas fa-redo"></i>
                <span>
                    <?php 
                    $maxAttempts = $options['attempts'] ?? 3;
                    echo count($attempts) . '/' . ($maxAttempts == -1 ? '∞' : $maxAttempts) . ' Attempts';
                    ?>
                </span>
            </div>
        </div>
    </div>

    <div class="attempts-container">
        <div class="attempts-header">
            <h2>Your Attempts</h2>
            <?php 
            $maxAttempts = intval($options['attempts'] ?? 3);
            $canRetake = ($maxAttempts == -1 || count($attempts) < $maxAttempts);
            if ($canRetake && ($quizData['is_published'] ?? 0) == 1): 
            ?>
            <a href="take-quiz.php?id=<?php echo urlencode($quizId); ?>" class="btn btn-primary">
                <i class="fas fa-play"></i> 
                <?php echo count($attempts) > 0 ? 'Retake Quiz' : 'Start Quiz'; ?>
            </a>
            <?php endif; ?>
        </div>

        <?php if (empty($attempts)): ?>
        <div class="no-attempts">
            <i class="fas fa-clipboard-list"></i>
            <h3>No Attempts Yet</h3>
            <p>You haven't taken this quiz yet. Click "Start Quiz" to begin your first attempt.</p>
        </div>
        <?php else: ?>
        
        <?php 
        // Calculate summary stats
        $bestScore = 0;
        $bestPercentage = 0;
        $totalTimeSpent = 0;
        $averageScore = 0;
        
        foreach ($attempts as $attempt) {
            if ($attempt['score'] > $bestScore) {
                $bestScore = $attempt['score'];
                $bestPercentage = $attempt['percentage'];
            }
            $totalTimeSpent += $attempt['timeUsed'];
            $averageScore += $attempt['score'];
        }
        
        $averageScore = count($attempts) > 0 ? round($averageScore / count($attempts), 1) : 0;
        $averageTimeMinutes = count($attempts) > 0 ? round($totalTimeSpent / count($attempts) / 60, 1) : 0;
        ?>
        
        <div class="summary-stats">
            <div class="stat-item">
                <div class="stat-value"><?php echo count($attempts); ?></div>
                <div class="stat-label">Total Attempts</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $bestPercentage; ?>%</div>
                <div class="stat-label">Best Score</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $averageScore; ?></div>
                <div class="stat-label">Average Score</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $averageTimeMinutes; ?>m</div>
                <div class="stat-label">Average Time</div>
            </div>
        </div>

        <table class="attempts-table">
            <thead>
                <tr>
                    <th>Attempt</th>
                    <th>Date & Time</th>
                    <th>Score</th>
                    <th>Percentage</th>
                    <th>Time Used</th>
                    <th>Status</th>
                    <?php if ($options['seeResponses'] ?? false): ?>
                    <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Sort attempts by attempt number (newest first)
                usort($attempts, function($a, $b) {
                    return $b['attemptNumber'] - $a['attemptNumber'];
                });
                
                foreach ($attempts as $attempt): 
                    $percentage = $attempt['percentage'];
                    $scoreClass = 'score-poor';
                    if ($percentage >= 90) $scoreClass = 'score-excellent';
                    elseif ($percentage >= 80) $scoreClass = 'score-good';
                    elseif ($percentage >= 70) $scoreClass = 'score-fair';
                    
                    $isBestScore = ($attempt['score'] == $bestScore);
                    
                    $submittedDate = new DateTime($attempt['submittedAt']);
                ?>
                <tr class="<?php echo $isBestScore ? 'best-score' : ''; ?>">
                    <td>
                        <strong>Attempt <?php echo $attempt['attemptNumber']; ?></strong>
                        <?php if ($isBestScore): ?>
                        <i class="fas fa-trophy" style="color: #fbbf24; margin-left: 8px;" title="Best Score"></i>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $submittedDate->format('M j, Y g:i A'); ?></td>
                    <td><?php echo $attempt['score']; ?>/<?php echo $attempt['totalPoints']; ?></td>
                    <td>
                        <span class="score-badge <?php echo $scoreClass; ?>">
                            <?php echo $percentage; ?>%
                        </span>
                    </td>
                    <td>
                        <?php 
                        $timeUsed = $attempt['timeUsed'];
                        $hours = floor($timeUsed / 3600);
                        $minutes = floor(($timeUsed % 3600) / 60);
                        $seconds = $timeUsed % 60;
                        
                        if ($hours > 0) {
                            echo "{$hours}h {$minutes}m {$seconds}s";
                        } else {
                            echo "{$minutes}m {$seconds}s";
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($attempt['isTimeout'] ?? false): ?>
                        <span class="status-badge status-timeout">
                            <i class="fas fa-clock"></i> Timeout
                        </span>
                        <?php else: ?>
                        <span class="status-badge status-completed">
                            <i class="fas fa-check"></i> Completed
                        </span>
                        <?php endif; ?>
                    </td>
                    <?php if ($options['seeResponses'] ?? false): ?>
                    <td>
                        <div class="attempt-actions">
                            <button onclick="viewAttemptDetails(<?php echo htmlspecialchars(json_encode($attempt)); ?>)" 
                                    class="btn btn-outline btn-small">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="assessments" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Assessments
            </a>
        </div>
    </div>
</div>

<!-- Attempt Details Modal -->
<div id="attemptModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div class="modal-content" style="background: white; border-radius: 12px; padding: 24px; max-width: 800px; width: 90%; max-height: 80vh; overflow-y: auto; margin: 5vh auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px;">
            <h3 id="modalTitle">Attempt Details</h3>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="modalContent">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<style>
.modal {
    display: none;
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.feedback-item {
    margin-bottom: 16px;
    padding: 12px;
    border-radius: 6px;
    border-left: 4px solid;
}

.feedback-item.correct {
    background: #f0fff4;
    border-left-color: var(--success-color);
}

.feedback-item.incorrect {
    background: #fef2f2;
    border-left-color: var(--danger-color);
}

.feedback-header {
    display: flex;
    justify-content: space-between;
    font-weight: 500;
    margin-bottom: 8px;
}

.student-answer {
    background: #f8fafc;
    padding: 8px 12px;
    border-radius: 4px;
    margin: 8px 0;
    font-family: monospace;
}
</style>

<script>
function viewAttemptDetails(attempt) {
    const modal = document.getElementById('attemptModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalContent = document.getElementById('modalContent');
    
    modalTitle.textContent = `Attempt ${attempt.attemptNumber} - Details`;
    
    let content = `
        <div class="attempt-summary" style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                <div><strong>Score:</strong> ${attempt.score}/${attempt.totalPoints} (${attempt.percentage}%)</div>
                <div><strong>Correct:</strong> ${attempt.correctQuestions}/${attempt.totalQuestions}</div>
                <div><strong>Time Used:</strong> ${formatTime(attempt.timeUsed)}</div>
                <div><strong>Status:</strong> ${attempt.isTimeout ? 'Timeout' : 'Completed'}</div>
            </div>
        </div>
    `;
    
    if (attempt.feedback && attempt.feedback.length > 0) {
        content += '<h4>Question Feedback</h4>';
        attempt.feedback.forEach(feedback => {
            content += `
                <div class="feedback-item ${feedback.correct ? 'correct' : 'incorrect'}">
                    <div class="feedback-header">
                        <span>Question ${feedback.question}</span>
                        <span>${feedback.points} / ${feedback.totalPoints} points</span>
                    </div>
                    <div>${feedback.feedback}</div>
                </div>
            `;
        });
    }
    
    modalContent.innerHTML = content;
    modal.classList.add('show');
}

function closeModal() {
    const modal = document.getElementById('attemptModal');
    modal.classList.remove('show');
}

function formatTime(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
        return `${hours}h ${minutes}m ${secs}s`;
    } else {
        return `${minutes}m ${secs}s`;
    }
}

// Close modal when clicking outside
document.getElementById('attemptModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

</body>
</html>