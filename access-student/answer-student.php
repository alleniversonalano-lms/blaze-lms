<?php
session_start();

// Check if user is logged in and is a teacher
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

// The quiz data will be loaded via AJAX in the JavaScript code
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - Student Interface</title>
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

        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            z-index: 100;
            transition: all 0.3s ease;
        }

        .timer.warning {
            background: #f59e0b;
            animation: pulse 2s infinite;
        }

        .timer.critical {
            background: #ef4444;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
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

        .question-text p {
            margin-bottom: 15px;
        }

        .question-text strong {
            color: #1e293b;
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

        .formula-input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .formula-variables {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }

        .formula-variables h4 {
            color: #475569;
            margin-bottom: 15px;
            font-size: 1rem;
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

        .submit-section {
            text-align: center;
            padding: 40px 0;
            border-top: 2px solid #f1f5f9;
            margin-top: 40px;
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

        .progress-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            transition: width 0.3s ease;
            z-index: 1000;
        }

        .results-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .results-modal.show {
            opacity: 1;
            visibility: visible;
        }

        .results-content {
            background: white;
            padding: 40px;
            border-radius: 20px;
            max-width: 600px;
            width: 90%;
            text-align: center;
            transform: scale(0.8);
            transition: transform 0.3s ease;
        }

        .results-modal.show .results-content {
            transform: scale(1);
        }

        .score-display {
            font-size: 4rem;
            font-weight: 800;
            margin: 20px 0;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .score-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 30px 0;
        }

        .score-item {
            padding: 20px;
            border-radius: 12px;
            background: #f8fafc;
        }

        .score-item h4 {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .score-item .value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1e293b;
        }

        .attempts-info {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }

        .question-status {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e2e8f0;
        }

        .question-status.answered {
            background: #10b981;
        }

        .question-status.partial {
            background: #f59e0b;
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

            .score-details {
                grid-template-columns: 1fr;
            }

            .timer {
                position: relative;
                top: auto;
                right: auto;
                margin: 0 auto 20px auto;
                display: inline-block;
            }
        }
    </style>
</head>

<body>
    <div class="progress-bar" id="progressBar"></div>

    <div class="quiz-container">
        <div class="quiz-header">
            <h1 class="quiz-title" id="quizTitle">Sample Quiz</h1>
            <div class="quiz-info">
                <div class="info-item">
                    <strong id="questionCount">5</strong> Questions
                </div>
                <div class="info-item">
                    <strong id="totalPoints">25</strong> Points
                </div>
                <div class="info-item">
                    <strong id="timeLimit">30</strong> Minutes
                </div>
            </div>
        </div>

        <div class="timer" id="timer">30:00</div>

        <div class="quiz-content">
            <div class="attempts-info" id="attemptsInfo" style="display: none;">
                Attempt <strong>1</strong> of <strong>3</strong>
            </div>

            <div class="instructions" id="instructions">
                <p>Read each question carefully and select or enter your answers. You can navigate between questions freely before submitting.</p>
            </div>

            <div id="questionsContainer">
                <!-- Questions will be dynamically generated here -->
            </div>

            <div class="submit-section">
                <button class="submit-btn" id="submitBtn">Submit Quiz</button>
            </div>
        </div>
    </div>

    <div class="results-modal" id="resultsModal">
        <div class="results-content">
            <h2>Quiz Complete!</h2>
            <div class="score-display" id="finalScore">85%</div>
            <div class="score-details">
                <div class="score-item">
                    <h4>Score</h4>
                    <div class="value" id="scoreValue">17/20</div>
                </div>
                <div class="score-item">
                    <h4>Correct</h4>
                    <div class="value" id="correctCount">4</div>
                </div>
                <div class="score-item">
                    <h4>Time Used</h4>
                    <div class="value" id="timeUsed">15:32</div>
                </div>
            </div>
            <button class="submit-btn" onclick="closeResults()">Close</button>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mathjs/11.11.0/math.min.js"></script>
    <script>
        // Sample quiz data - this would normally come from your quiz builder
        const quizData = <?= json_encode($js_quiz_data) ?>;

        // Add function to load quiz data from builder
        // Replace the loadQuizData function

        async function loadQuizData() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const quizId = urlParams.get('id');

                if (!quizId) {
                    throw new Error('No quiz ID provided');
                }

                // Load quiz data via AJAX
                const response = await fetch(`functions/load_quiz.php?id=${quizId}`, {
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error(`Failed to load quiz: ${response.status}`);
                }

                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Failed to load quiz data');
                }

                // Initialize quiz with the loaded data
                const quiz = new QuizApp(data.data);

                // Update page title
                document.title = `${data.data.title} - Quiz`;

            } catch (err) {
                console.error('Error loading quiz:', err);
                document.body.innerHTML = `
            <div style="text-align:center;padding:40px;background:white;border-radius:8px;margin:20px;">
                <h2 style="color:#ef4444;margin-bottom:20px;">Error Loading Quiz</h2>
                <p style="color:#666;margin-bottom:15px;">${err.message}</p>
                <a href="assessments" class="submit-btn" style="display:inline-block;margin-top:20px;">
                    Return to Assessments
                </a>
            </div>
        `;
            }
        }

        // Call when DOM is ready
        document.addEventListener('DOMContentLoaded', loadQuizData);

        class QuizApp {
            constructor(quizData) {
                this.quiz = quizData;
                this.answers = {};
                this.startTime = Date.now();
                this.timeRemaining = this.quiz.timeLimit * 60; // Convert to seconds
                this.timerInterval = null;
                this.submitted = false;

                this.init();
            }

            init() {
                this.renderQuizHeader();
                this.renderInstructions();
                this.renderQuestions();
                this.startTimer();
                this.bindEvents();
                this.updateProgress();
            }

            renderQuizHeader() {
                document.getElementById('quizTitle').textContent = this.quiz.title;
                document.getElementById('questionCount').textContent = this.quiz.questions.length;
                document.getElementById('totalPoints').textContent =
                    this.quiz.questions.reduce((sum, q) => sum + q.points, 0);
                document.getElementById('timeLimit').textContent = this.quiz.timeLimit;

                if (this.quiz.allowedAttempts > 1) {
                    const attemptsInfo = document.getElementById('attemptsInfo');
                    attemptsInfo.style.display = 'block';
                    attemptsInfo.innerHTML = `
                        Attempt <strong>${this.quiz.currentAttempt}</strong> of <strong>${this.quiz.allowedAttempts}</strong>
                    `;
                }
            }

            renderInstructions() {
                document.getElementById('instructions').innerHTML = this.quiz.instructions;
            }

            renderQuestions() {
                const container = document.getElementById('questionsContainer');
                container.innerHTML = '';

                this.quiz.questions.forEach(question => {
                    const questionEl = this.createQuestionElement(question);
                    container.appendChild(questionEl);
                });
            }

            createQuestionElement(question) {
                const div = document.createElement('div');
                div.className = 'question';
                div.dataset.questionId = question.id;

                div.innerHTML = `
                    <div class="question-status"></div>
                    <div class="question-header">
                        <div class="question-number">Question ${question.index}</div>
                        <div class="question-points">${question.points} points</div>
                    </div>
                    ${question.title ? `<div class="question-title">${question.title}</div>` : ''}
                    <div class="question-text">${this.processQuestionText(question)}</div>
                    <div class="question-input">
                        ${this.renderQuestionInput(question)}
                    </div>
                `;

                return div;
            }

            processQuestionText(question) {
                let text = question.text;

                if (question.type === 'fill') {
                    let blankIndex = 0;
                    text = text.replace(/\{[^}]+\}/g, () => {
                        return `<input type="text" class="fill-blank" data-blank-index="${blankIndex++}" data-question-id="${question.id}">`;
                    });
                }

                return text;
            }

            renderQuestionInput(question) {
                switch (question.type) {
                    case 'mcq':
                        return this.renderMCQInput(question);
                    case 'formula':
                        return this.renderFormulaInput(question);
                    default:
                        return '';
                }
            }

            renderMCQInput(question) {
                const inputType = question.allow_multiple ? 'checkbox' : 'radio';
                const inputName = question.allow_multiple ? '' : `name="mcq-${question.id}"`;

                return `
                    <div class="mcq-options">
                        ${question.options.map((option, index) => `
                            <div class="mcq-option">
                                <label>
                                    <input type="${inputType}" ${inputName} 
                                           data-question-id="${question.id}" 
                                           data-option-index="${index}"
                                           value="${index}">
                                    ${option.text}
                                </label>
                            </div>
                        `).join('')}
                    </div>
                `;
            }

            renderFormulaInput(question) {
                return `
                    <div class="formula-variables">
                        <h4>Given Variables:</h4>
                        <div class="variables-list">
                            ${question.variables.map(variable => `
                                <div class="variable">
                                    <div class="variable-name">${variable.name} =</div>
                                    <div class="variable-value">${variable.sample}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <input type="number" step="0.01" class="formula-input" 
                           data-question-id="${question.id}" 
                           placeholder="Enter your calculated result">
                `;
            }

            bindEvents() {
                document.addEventListener('change', (e) => {
                    if (e.target.matches('input[type="radio"], input[type="checkbox"]')) {
                        this.handleMCQAnswer(e.target);
                    }
                });

                document.addEventListener('input', (e) => {
                    if (e.target.matches('.fill-blank')) {
                        this.handleFillAnswer(e.target);
                    } else if (e.target.matches('.formula-input')) {
                        this.handleFormulaAnswer(e.target);
                    }
                });

                document.getElementById('submitBtn').addEventListener('click', () => {
                    this.submitQuiz();
                });

                window.addEventListener('beforeunload', (e) => {
                    if (!this.submitted) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });
            }

            handleMCQAnswer(input) {
                const questionId = input.dataset.questionId;
                const optionIndex = parseInt(input.dataset.optionIndex);
                const question = this.quiz.questions.find(q => q.id === questionId);

                if (!this.answers[questionId]) {
                    this.answers[questionId] = {
                        type: 'mcq',
                        selected: []
                    };
                }

                if (question.allow_multiple) {
                    if (input.checked) {
                        if (!this.answers[questionId].selected.includes(optionIndex)) {
                            this.answers[questionId].selected.push(optionIndex);
                        }
                    } else {
                        this.answers[questionId].selected = this.answers[questionId].selected.filter(
                            idx => idx !== optionIndex
                        );
                    }
                } else {
                    this.answers[questionId].selected = input.checked ? [optionIndex] : [];
                }

                this.updateQuestionStatus(questionId);
                this.updateProgress();
            }

            handleFillAnswer(input) {
                const questionId = input.dataset.questionId;
                const blankIndex = parseInt(input.dataset.blankIndex);

                if (!this.answers[questionId]) {
                    this.answers[questionId] = {
                        type: 'fill',
                        blanks: {}
                    };
                }

                this.answers[questionId].blanks[blankIndex] = input.value.trim();

                this.updateQuestionStatus(questionId);
                this.updateProgress();
            }

            handleFormulaAnswer(input) {
                const questionId = input.dataset.questionId;

                if (!this.answers[questionId]) {
                    this.answers[questionId] = {
                        type: 'formula'
                    };
                }

                this.answers[questionId].result = parseFloat(input.value) || null;

                this.updateQuestionStatus(questionId);
                this.updateProgress();
            }

            updateQuestionStatus(questionId) {
                const questionEl = document.querySelector(`[data-question-id="${questionId}"]`);
                const statusEl = questionEl.querySelector('.question-status');
                const question = this.quiz.questions.find(q => q.id === questionId);
                const answer = this.answers[questionId];

                if (!answer) {
                    statusEl.className = 'question-status';
                    return;
                }

                let isAnswered = false;
                let isPartial = false;

                switch (question.type) {
                    case 'mcq':
                        isAnswered = answer.selected && answer.selected.length > 0;
                        break;
                    case 'fill':
                        const totalBlanks = question.blanks.length;
                        const filledBlanks = Object.keys(answer.blanks || {}).filter(
                            key => answer.blanks[key] && answer.blanks[key].length > 0
                        ).length;
                        isAnswered = filledBlanks === totalBlanks;
                        isPartial = filledBlanks > 0 && filledBlanks < totalBlanks;
                        break;
                    case 'formula':
                        isAnswered = answer.result !== null && !isNaN(answer.result);
                        break;
                }

                statusEl.className = `question-status ${isPartial ? 'partial' : (isAnswered ? 'answered' : '')}`;
            }

            updateProgress() {
                const totalQuestions = this.quiz.questions.length;
                const answeredQuestions = this.quiz.questions.filter(q => {
                    const answer = this.answers[q.id];
                    if (!answer) return false;

                    switch (q.type) {
                        case 'mcq':
                            return answer.selected && answer.selected.length > 0;
                        case 'fill':
                            const filledBlanks = Object.keys(answer.blanks || {}).filter(
                                key => answer.blanks[key] && answer.blanks[key].length > 0
                            ).length;
                            return filledBlanks === q.blanks.length;
                        case 'formula':
                            return answer.result !== null && !isNaN(answer.result);
                        default:
                            return false;
                    }
                }).length;

                const progressPercent = (answeredQuestions / totalQuestions) * 100;
                document.getElementById('progressBar').style.width = `${progressPercent}%`;
            }

            startTimer() {
                this.updateTimerDisplay();

                this.timerInterval = setInterval(() => {
                    this.timeRemaining--;
                    this.updateTimerDisplay();

                    if (this.timeRemaining <= 0) {
                        this.submitQuiz(true);
                    }
                }, 1000);
            }

            // Update the updateTimerDisplay method in the QuizApp class

            updateTimerDisplay() {
                const timerEl = document.getElementById('timer');
                if (!timerEl) return; // Add this check

                const minutes = Math.floor(this.timeRemaining / 60);
                const seconds = this.timeRemaining % 60;
                const display = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                timerEl.textContent = display;

                if (this.timeRemaining <= 300) {
                    timerEl.className = 'timer critical';
                } else if (this.timeRemaining <= 600) {
                    timerEl.className = 'timer warning';
                } else {
                    timerEl.className = 'timer';
                }
            }

            calculateScore() {
                let totalScore = 0;
                let maxScore = 0;
                let correctQuestions = 0;

                this.quiz.questions.forEach(question => {
                    maxScore += question.points;
                    const answer = this.answers[question.id];

                    if (!answer) return;

                    let questionScore = 0;

                    switch (question.type) {
                        case 'mcq':
                            questionScore = this.scoreMCQ(question, answer);
                            break;
                        case 'fill':
                            questionScore = this.scoreFill(question, answer);
                            break;
                        case 'formula':
                            questionScore = this.scoreFormula(question, answer);
                            break;
                    }

                    totalScore += questionScore;
                    if (questionScore === question.points) {
                        correctQuestions++;
                    }
                });

                return {
                    score: totalScore,
                    maxScore,
                    percentage: Math.round((totalScore / maxScore) * 100),
                    correctQuestions,
                    totalQuestions: this.quiz.questions.length
                };
            }

            scoreMCQ(question, answer) {
                const correctOptions = question.options
                    .map((opt, idx) => opt.is_correct ? idx : -1)
                    .filter(idx => idx !== -1);

                const selectedOptions = answer.selected || [];

                const isCorrect = correctOptions.length === selectedOptions.length &&
                    correctOptions.every(idx => selectedOptions.includes(idx));

                return isCorrect ? question.points : 0;
            }

            scoreFill(question, answer) {
                if (!answer.blanks) return 0;

                let correctBlanks = 0;

                question.blanks.forEach((blank, index) => {
                    const userAnswer = answer.blanks[index];
                    if (!userAnswer) return;

                    const isCorrect = blank.answers.some(correctAnswer => {
                        if (blank.case_sensitive) {
                            return userAnswer === correctAnswer;
                        } else {
                            return userAnswer.toLowerCase() === correctAnswer.toLowerCase();
                        }
                    });

                    if (isCorrect) correctBlanks++;
                });

                const scorePerBlank = question.points / question.blanks.length;
                return Math.round(correctBlanks * scorePerBlank * 100) / 100;
            }

            scoreFormula(question, answer) {
                if (answer.result === null || isNaN(answer.result)) return 0;

                try {
                    const scope = {};
                    question.variables.forEach(variable => {
                        scope[variable.name] = parseFloat(variable.sample);
                    });

                    const correctResult = math.evaluate(question.formula.expression, scope);
                    const roundedCorrect = Math.round(correctResult * Math.pow(10, question.formula.decimals)) / Math.pow(10, question.formula.decimals);
                    const roundedAnswer = Math.round(answer.result * Math.pow(10, question.formula.decimals)) / Math.pow(10, question.formula.decimals);

                    return roundedAnswer === roundedCorrect ? question.points : 0;
                } catch (error) {
                    console.error('Error calculating formula score:', error);
                    return 0;
                }
            }

            submitQuiz(autoSubmit = false) {
                if (this.submitted) return;

                if (!autoSubmit) {
                    const confirmed = confirm('Are you sure you want to submit your quiz? You cannot change your answers after submission.');
                    if (!confirmed) return;
                }

                this.submitted = true;
                clearInterval(this.timerInterval);

                const results = this.calculateScore();

                const timeUsed = this.quiz.timeLimit * 60 - this.timeRemaining;
                const timeUsedMinutes = Math.floor(timeUsed / 60);
                const timeUsedSeconds = timeUsed % 60;
                const timeUsedDisplay = `${timeUsedMinutes}:${timeUsedSeconds.toString().padStart(2, '0')}`;

                // Prepare result data
                const resultData = {
                    quizId: this.quiz.id || 'sample-quiz',
                    studentId: 'current-student',
                    attemptNumber: this.quiz.currentAttempt,
                    answers: this.answers,
                    results,
                    timeUsed: timeUsedDisplay,
                    submittedAt: new Date().toISOString(),
                    autoSubmit
                };

                // Save quiz results to JSON file
                fetch('functions/save_quiz_results', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(resultData),
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.error('Failed to save quiz results:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error saving quiz results:', error);
                });

                this.showResults(results, timeUsedDisplay, autoSubmit);
            }

            showResults(results, timeUsed, autoSubmit) {
                const modal = document.getElementById('resultsModal');
                const scoreDisplay = document.getElementById('finalScore');
                const scoreValue = document.getElementById('scoreValue');
                const correctCount = document.getElementById('correctCount');
                const timeUsedEl = document.getElementById('timeUsed');

                scoreDisplay.textContent = `${results.percentage}%`;
                scoreValue.textContent = `${results.score}/${results.maxScore}`;
                correctCount.textContent = `${results.correctQuestions}/${results.totalQuestions}`;
                timeUsedEl.textContent = timeUsed;

                if (autoSubmit) {
                    modal.querySelector('h2').textContent = 'Time\'s Up! Quiz Auto-Submitted';
                    modal.querySelector('h2').style.color = '#ef4444';
                }

                modal.classList.add('show');

                document.getElementById('submitBtn').disabled = true;
                document.getElementById('submitBtn').textContent = 'Quiz Submitted';

                document.querySelectorAll('input, select, textarea').forEach(input => {
                    input.disabled = true;
                });
            }
        }

        function closeResults() {
            document.getElementById('resultsModal').classList.remove('show');
        }

        document.addEventListener('DOMContentLoaded', () => {
            new QuizApp(quizData);
        });

        document.addEventListener('DOMContentLoaded', () => {
            document.addEventListener('change', (e) => {
                if (e.target.matches('input[type="radio"], input[type="checkbox"]')) {
                    const option = e.target.closest('.mcq-option');
                    const questionEl = option.closest('.question');

                    if (e.target.type === 'radio') {
                        questionEl.querySelectorAll('.mcq-option').forEach(opt => {
                            opt.classList.remove('selected');
                        });
                        if (e.target.checked) {
                            option.classList.add('selected');
                        }
                    } else {
                        option.classList.toggle('selected', e.target.checked);
                    }
                }
            });

            const questions = document.querySelectorAll('.question');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

            questions.forEach((question, index) => {
                question.style.opacity = '0';
                question.style.transform = 'translateY(30px)';
                question.style.transition = `all 0.6s ease ${index * 0.1}s`;
                observer.observe(question);
            });

            document.addEventListener('focusin', (e) => {
                if (e.target.matches('.fill-blank, .formula-input')) {
                    e.target.parentElement.style.transform = 'scale(1.02)';
                }
            });

            document.addEventListener('focusout', (e) => {
                if (e.target.matches('.fill-blank, .formula-input')) {
                    e.target.parentElement.style.transform = 'scale(1)';
                }
            });
        });

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                const submitBtn = document.getElementById('submitBtn');
                if (!submitBtn.disabled) {
                    submitBtn.click();
                }
            }
        });
    </script>
</body>

</html>