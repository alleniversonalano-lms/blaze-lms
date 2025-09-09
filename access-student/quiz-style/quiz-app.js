class QuizApp {
    constructor(quizData, mode = 'preview') {
        this.quiz = quizData;
        this.mode = mode;
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
        if (this.mode === 'preview') {
            this.startTimer();
        }
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
            if (attemptsInfo) {
                attemptsInfo.style.display = 'block';
                attemptsInfo.innerHTML = `
                    Attempt <strong>${this.quiz.currentAttempt}</strong> of <strong>${this.quiz.allowedAttempts}</strong>
                `;
            }
        }
    }

    renderInstructions() {
        const instructions = document.getElementById('instructions');
        if (instructions) {
            instructions.innerHTML = this.quiz.instructions || 
                'Please read each question carefully and select or enter your answers as appropriate.';
        }
    }

    renderQuestions() {
        const container = document.getElementById('questionsContainer');
        container.innerHTML = '';

        this.quiz.questions.forEach((question, index) => {
            const questionEl = this.createQuestionElement(question, index + 1);
            container.appendChild(questionEl);
        });
    }

    createQuestionElement(question, index) {
        const div = document.createElement('div');
        div.className = 'question';
        div.dataset.questionId = question.id;

        div.innerHTML = `
            <div class="question-status"></div>
            <div class="question-header">
                <div class="question-number">Question ${index}</div>
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
        let text = question.text || '';

        if (question.type === 'fill_blank') {
            let blankIndex = 0;
            text = text.replace(/\{[^}]+\}/g, () => {
                return `<input type="text" class="fill-blank" 
                        data-blank-index="${blankIndex++}" 
                        data-question-id="${question.id}">`;
            });
        }

        return text;
    }

    renderQuestionInput(question) {
        switch (question.type) {
            case 'multiple_choice':
                return this.renderMCQInput(question);
            case 'formula':
                return this.renderFormulaInput(question);
            default:
                return '';
        }
    }

    renderMCQInput(question) {
        const inputType = question.allowMultiple ? 'checkbox' : 'radio';
        const inputName = question.allowMultiple ? '' : `mcq-${question.id}`;

        // Check for options in various possible formats and locations
        let options = [];
        try {
            if (Array.isArray(question.options)) {
                options = question.options;
            } else if (typeof question.choices === 'string') {
                options = JSON.parse(question.choices);
            } else if (Array.isArray(question.choices)) {
                options = question.choices;
            } else if (typeof question.options === 'string') {
                options = JSON.parse(question.options);
            }
        } catch (e) {
            console.error('Error parsing options:', e);
            options = [];
        }

        // Ensure we have an array
        if (!Array.isArray(options)) {
            options = [];
        }

        return `
            <div class="mcq-options">
                ${options.length ? options.map((option, index) => {
                    const optionText = typeof option === 'object' ? (option.text || option.value || option.label || '') : option;
                    return `
                        <div class="mcq-option">
                            <label>
                                <input type="${inputType}" 
                                       ${inputName ? `name="${inputName}"` : ''} 
                                       data-question-id="${question.id}" 
                                       data-option-index="${index}"
                                       value="${index}">
                                ${optionText}
                            </label>
                        </div>
                    `;
                }).join('') : '<div class="mcq-error">No options available for this question.</div>'}
            </div>
        `;
    }

    renderFormulaInput(question) {
        return `
            <div class="formula-variables">
                <h4>Given Variables:</h4>
                <div class="variables-list">
                    ${Object.entries(question.variables).map(([name, variable]) => `
                        <div class="variable">
                            <div class="variable-name">${name} = ${variable.sample}</div>
                            <div class="variable-value">${variable.unit || ''}</div>
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

        if (this.mode === 'preview') {
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.addEventListener('click', () => this.submitQuiz());
            }
        }
    }

    handleMCQAnswer(input) {
        const questionId = input.dataset.questionId;
        const optionIndex = parseInt(input.dataset.optionIndex);
        const question = this.quiz.questions.find(q => q.id === questionId);

        if (!this.answers[questionId]) {
            this.answers[questionId] = {
                type: 'multiple_choice',
                selected: []
            };
        }

        if (question.allowMultiple) {
            if (input.checked) {
                if (!this.answers[questionId].selected.includes(optionIndex)) {
                    this.answers[questionId].selected.push(optionIndex);
                }
            } else {
                this.answers[questionId].selected = 
                    this.answers[questionId].selected.filter(idx => idx !== optionIndex);
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
                type: 'fill_blank',
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
            case 'multiple_choice':
                isAnswered = answer.selected && answer.selected.length > 0;
                break;
            case 'fill_blank':
                const totalBlanks = (question.text.match(/\{[^}]+\}/g) || []).length;
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
                case 'multiple_choice':
                    return answer.selected && answer.selected.length > 0;
                case 'fill_blank':
                    const totalBlanks = (q.text.match(/\{[^}]+\}/g) || []).length;
                    const filledBlanks = Object.keys(answer.blanks || {}).filter(
                        key => answer.blanks[key] && answer.blanks[key].length > 0
                    ).length;
                    return filledBlanks === totalBlanks;
                case 'formula':
                    return answer.result !== null && !isNaN(answer.result);
                default:
                    return false;
            }
        }).length;

        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            progressBar.style.width = `${(answeredQuestions / totalQuestions) * 100}%`;
        }
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

    updateTimerDisplay() {
        const timerEl = document.getElementById('timer');
        if (!timerEl) return;

        const minutes = Math.floor(this.timeRemaining / 60);
        const seconds = this.timeRemaining % 60;
        const display = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        timerEl.textContent = display;

        if (this.timeRemaining <= 300) { // 5 minutes
            timerEl.className = 'timer critical';
        } else if (this.timeRemaining <= 600) { // 10 minutes
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
                case 'multiple_choice':
                    questionScore = this.scoreMCQ(question, answer);
                    break;
                case 'fill_blank':
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
            .map((opt, idx) => opt.isCorrect ? idx : -1)
            .filter(idx => idx !== -1);

        const selectedOptions = answer.selected || [];

        const isCorrect = correctOptions.length === selectedOptions.length &&
            correctOptions.every(idx => selectedOptions.includes(idx));

        return isCorrect ? question.points : 0;
    }

    scoreFill(question, answer) {
        if (!answer.blanks) return 0;

        const blanks = question.text.match(/\{([^}]+)\}/g) || [];
        let correctBlanks = 0;

        blanks.forEach((blank, index) => {
            const correctAnswers = blank.slice(1, -1).split('|');
            const userAnswer = answer.blanks[index];
            if (!userAnswer) return;

            const isCorrect = correctAnswers.some(correct => {
                if (question.caseSensitive) {
                    return userAnswer === correct;
                } else {
                    return userAnswer.toLowerCase() === correct.toLowerCase();
                }
            });

            if (isCorrect) correctBlanks++;
        });

        const scorePerBlank = question.points / blanks.length;
        return Math.round(correctBlanks * scorePerBlank * 100) / 100;
    }

    scoreFormula(question, answer) {
        if (!answer.result || isNaN(answer.result)) return 0;

        const result = parseFloat(answer.result);
        const correctResult = this.evaluateFormula(question.formula, question.variables);
        const tolerance = question.tolerance || 0.01;

        return Math.abs(result - correctResult) <= tolerance ? question.points : 0;
    }

    evaluateFormula(formula, variables) {
        try {
            const mathScope = {};
            Object.entries(variables).forEach(([name, value]) => {
                mathScope[name] = parseFloat(value.sample);
            });

            return math.evaluate(formula, mathScope);
        } catch (error) {
            console.error('Error evaluating formula:', error);
            return null;
        }
    }

    submitQuiz(autoSubmit = false) {
        if (this.submitted) return;

        if (!autoSubmit) {
            const confirmed = confirm(
                'Are you sure you want to submit your quiz? You cannot change your answers after submission.'
            );
            if (!confirmed) return;
        }

        this.submitted = true;
        clearInterval(this.timerInterval);

        const results = this.calculateScore();
        const timeUsed = this.quiz.timeLimit * 60 - this.timeRemaining;
        const timeUsedMinutes = Math.floor(timeUsed / 60);
        const timeUsedSeconds = timeUsed % 60;
        const timeUsedDisplay = `${timeUsedMinutes}:${timeUsedSeconds.toString().padStart(2, '0')}`;

        const resultData = {
            quizId: this.quiz.id,
            studentId: window.studentId || '', // This will be set from PHP
            attemptNumber: this.quiz.currentAttempt,
            answers: this.answers,
            results,
            timeUsed: timeUsedDisplay,
            submittedAt: new Date().toISOString(),
            autoSubmit
        };

        this.saveResults(resultData);
        this.showResults(results, timeUsedDisplay, autoSubmit);
    }

    async saveResults(resultData) {
        try {
            const response = await fetch('functions/save-quiz-results.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(resultData)
            });

            if (!response.ok) {
                throw new Error('Failed to save quiz results');
            }

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Failed to save quiz results');
            }

            console.log('Quiz results saved:', data);
        } catch (error) {
            console.error('Error saving quiz results:', error);
            alert('Failed to save your quiz results. Please take a screenshot of your results and contact your teacher.');
        }
    }

    showResults(results, timeUsed, autoSubmit) {
        const modal = document.getElementById('resultsModal');
        if (!modal) return;

        const scoreDisplay = modal.querySelector('#finalScore');
        const scoreValue = modal.querySelector('#scoreValue');
        const correctCount = modal.querySelector('#correctCount');
        const timeUsedEl = modal.querySelector('#timeUsed');
        const title = modal.querySelector('h2');

        if (scoreDisplay) scoreDisplay.textContent = `${results.percentage}%`;
        if (scoreValue) scoreValue.textContent = `${results.score}/${results.maxScore}`;
        if (correctCount) correctCount.textContent = `${results.correctQuestions}/${results.totalQuestions}`;
        if (timeUsedEl) timeUsedEl.textContent = timeUsed;
        
        if (title && autoSubmit) {
            title.textContent = 'Time\'s Up! Quiz Auto-Submitted';
            title.style.color = '#ef4444';
        }

        modal.classList.add('show');

        // Disable inputs
        document.querySelectorAll('input, select, textarea').forEach(input => {
            input.disabled = true;
        });

        // Update submit button
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Quiz Submitted';
        }
    }
}
