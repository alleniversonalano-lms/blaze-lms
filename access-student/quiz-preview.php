<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'student') {
    header("Location: /login?error=Access+denied");
    exit;
}

// Get assessment ID 
$assessment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$course_id = $_SESSION['ann_course_id'] ?? 0;

if (!$assessment_id || !$course_id) {
    header("Location: assessments?error=Invalid+request");
    exit;
}

require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Preview</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .quiz-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .quiz-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .quiz-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .quiz-info {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .info-item {
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 20px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .quiz-content {
            padding: 40px;
        }

        .instructions {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 4px solid #4f46e5;
        }

        .question {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .question:hover {
            border-color: #4f46e5;
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.1);
        }

        .question::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .question:hover::before {
            opacity: 1;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .question-number {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .question-points {
            background: #f1f5f9;
            color: #64748b;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .question-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 15px;
        }

        .question-text {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 25px;
        }

        .mcq-options {
            display: grid;
            gap: 15px;
        }

        .mcq-option {
            position: relative;
            padding: 0;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .mcq-option:hover {
            border-color: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.15);
        }

        .mcq-option.selected {
            border-color: #4f46e5;
            background: #f0f4ff;
        }

        .mcq-option label {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            cursor: pointer;
            width: 100%;
            font-size: 1.05rem;
            line-height: 1.5;
        }

        .mcq-option input {
            width: 20px;
            height: 20px;
            accent-color: #4f46e5;
        }

        .fill-blank {
            display: inline-block;
            min-width: 120px;
            padding: 8px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            margin: 0 5px;
        }

        .fill-blank:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .formula-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            font-family: 'Courier New', monospace;
        }

        .formula-variables {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }

        .formula-variables h4 {
            font-weight: 500;
            margin-bottom: 15px;
        }

        .variables-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .variable {
            background: white;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .variable-name {
            font-weight: 600;
            color: #4f46e5;
            font-family: 'Courier New', monospace;
        }

        .variable-value {
            color: #64748b;
            font-size: 0.95rem;
        }

        .submit-btn {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border: none;
            padding: 18px 40px;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.4);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(5, 150, 105, 0.4);
        }

        .submit-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        @media (max-width: 768px) {
            .quiz-info {
                gap: 15px;
            }

            .question {
                padding: 20px;
            }

            .question-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .variables-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="quiz-container">
        <div class="quiz-header">
            <h1 class="quiz-title">Sample Quiz</h1>
            <div class="quiz-info">
                <div class="info-item">
                    <strong>5</strong> Questions
                </div>
                <div class="info-item">
                    <strong>25</strong> Points
                </div>
                <div class="info-item">
                    <strong>30</strong> Minutes
                </div>
            </div>
        </div>

        <div class="quiz-content">
            <div class="instructions">
                <p>Please read each question carefully and select or enter your answers as appropriate.</p>
            </div>

            <div class="question">
                <div class="question-header">
                    <div class="question-number">Question 1</div>
                    <div class="question-points">5 points</div>
                </div>
                <div class="question-title">Sample Multiple Choice Question</div>
                <div class="question-text">
                    <p>What is the capital city of France?</p>
                </div>
                <div class="mcq-options">
                    <div class="mcq-option">
                        <label>
                            <input type="radio" name="q1">
                            Paris
                        </label>
                    </div>
                    <div class="mcq-option">
                        <label>
                            <input type="radio" name="q1">
                            London
                        </label>
                    </div>
                    <div class="mcq-option">
                        <label>
                            <input type="radio" name="q1">
                            Berlin
                        </label>
                    </div>
                </div>
            </div>

            <div class="question">
                <div class="question-header">
                    <div class="question-number">Question 2</div>
                    <div class="question-points">5 points</div>
                </div>
                <div class="question-title">Sample Fill in the Blank Question</div>
                <div class="question-text">
                    The <input type="text" class="fill-blank"> is the largest planet in our solar system.
                </div>
            </div>

            <div class="question">
                <div class="question-header">
                    <div class="question-number">Question 3</div>
                    <div class="question-points">5 points</div>
                </div>
                <div class="question-title">Sample Formula Question</div>
                <div class="question-text">
                    <p>Calculate the area of a rectangle with the given dimensions:</p>
                </div>
                <div class="formula-variables">
                    <h4>Given Variables:</h4>
                    <div class="variables-list">
                        <div class="variable">
                            <div class="variable-name">width = 5</div>
                            <div class="variable-value">meters</div>
                        </div>
                        <div class="variable">
                            <div class="variable-name">height = 3</div>
                            <div class="variable-value">meters</div>
                        </div>
                    </div>
                </div>
                <input type="number" class="formula-input" placeholder="Enter your answer">
            </div>
        </div>
    </div>

    <script>
        // Enhance interactive elements
        document.addEventListener('DOMContentLoaded', () => {
            // Handle MCQ option selection
            document.querySelectorAll('.mcq-option').forEach(option => {
                option.addEventListener('click', () => {
                    const input = option.querySelector('input');
                    input.checked = true;
                    
                    // For radio buttons, deselect other options
                    if (input.type === 'radio') {
                        const name = input.name;
                        document.querySelectorAll(`input[name="${name}"]`).forEach(radio => {
                            radio.closest('.mcq-option').classList.remove('selected');
                        });
                    }
                    
                    option.classList.add('selected');
                });
            });

            // Enhance input focus effects
            document.querySelectorAll('.fill-blank, .formula-input').forEach(input => {
                input.addEventListener('focus', () => {
                    input.parentElement.style.transform = 'scale(1.02)';
                });
                input.addEventListener('blur', () => {
                    input.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>
