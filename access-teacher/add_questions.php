<?php
session_start();

// Store session details into variables
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$email_address = $_SESSION['email_address'];
$role = $_SESSION['role'];
$profile_pic = $_SESSION['profile_pic'];

// Redirect if not logged in or not a teacher
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'teacher') {
    header("Location: /login?error=Access+denied");
    exit;
}


// Get unread message count
require_once $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

// Debug connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
}

try {
    // Check if messages table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'messages'");
    if ($table_check->num_rows == 0) {
        error_log("Messages table does not exist");
        $unread_count = 0;
    } else {
        $unread_query = "SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0";
        $stmt = $conn->prepare($unread_query);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            $unread_count = 0;
        } else {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $unread_count = $row['unread_count'];
            error_log("User ID: " . $user_id . " | Unread count: " . $unread_count);
        }
    }
} catch (Exception $e) {
    error_log("Error getting unread count: " . $e->getMessage());
    $unread_count = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $_SESSION['ann_course_id'] = (int) $_POST['course_id'];
    header("Location: assessment");
    exit;
}

$course_id = $_SESSION['ann_course_id'] ?? 0;

if ($course_id) {
    $stmt = $conn->prepare("
        SELECT c.user_id AS owner_id,
               EXISTS(SELECT 1 FROM course_collaborators WHERE course_id = ? AND teacher_id = ?) AS is_collaborator,
               EXISTS(SELECT 1 FROM course_enrollments WHERE course_id = ? AND student_id = ?) AS is_enrolled
        FROM courses c
        WHERE c.id = ?
    ");
    $stmt->bind_param("iiiii", $course_id, $user_id, $course_id, $user_id, $course_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        // Course doesn't exist
        unset($_SESSION['ann_course_id']);
        header("Location: dashboard?error=Course+not+found");
        exit;
    }

    $row = $res->fetch_assoc();
    $is_owner = $row['owner_id'] == $user_id;
    $is_collaborator = $row['is_collaborator'];
    $is_enrolled = $row['is_enrolled'];

    if (!($is_owner || $is_collaborator || $is_enrolled)) {
        // Not part of the course
        unset($_SESSION['ann_course_id']);
        header("Location: dashboard?error=Access+denied+to+course");
        exit;
    }
}

// Fetch course code and title
$course_code = '';
$course_title = '';

if ($course_id) {
    $course_stmt = $conn->prepare("SELECT course_code, course_title FROM courses WHERE id = ?");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    if ($course_result && $course_row = $course_result->fetch_assoc()) {
        $course_code = htmlspecialchars($course_row['course_code']);
        $course_title = htmlspecialchars($course_row['course_title']);
    }
}

$assessment_id = $_SESSION['current_assessment_id'] ?? null;

if (!$assessment_id) {
    die("No assessment selected.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Question</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar {
            background-color: #B71C1C;
            color: white;
            width: 240px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            padding: 120px 20px 20px;
            /* extra top padding to clear the logo */
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
        }

        .logo {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            z-index: 10;
            text-align: center;
            user-select: none;
            /* Prevent text/image selection */
            pointer-events: none;
            /* Optional: ignore pointer events like drag */
        }

        .logo img {
            width: 100%;
            max-height: 150px;
            height: auto;
            /* For Safari */
            user-select: none;
            /* For other browsers */
            -webkit-user-drag: none;
            /* For Safari */
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            pointer-events: none;
            /* Prevent dragging */
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .nav a {
            color: white;
            text-decoration: none;
            font-size: 1rem;
            padding: 10px 15px;
            border-radius: 8px;
            transition: background 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

.unread-count {
            background-color: #fff;
            color: #B71C1C;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.8rem;
            font-weight: bold;
            min-width: 20px;
            text-align: center;
        }

        .nav a:hover {
            background-color: rgba(254, 80, 80, 0.73);
        }

        .main-content {
            margin-left: 240px;
            padding: 100px 40px 40px;
            /* Increased top padding to make space for fixed topbar */
            flex-grow: 1;
            background: #f5f5f5;
            overflow-y: auto;
            position: relative;
            z-index: 1;
        }


        .main-content::before {
            content: "BLAZE";
            position: fixed;
            top: 40%;
            left: 58%;
            transform: translate(-50%, -50%);
            font-size: 12rem;
            font-weight: 900;
            color: rgb(60, 60, 60);
            opacity: 0.08;
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
        }

        .main-content::after {
            content: "BatStateU Learning and Academic Zone for Excellence";
            position: fixed;
            top: 58%;
            left: 58%;
            transform: translate(-50%, -50%);
            font-size: 1.8rem;
            font-weight: 600;
            color: rgb(60, 60, 60);
            opacity: 0.08;
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
            text-align: center;
        }

        h1 {
            font-size: 2rem;
            color: #333;
            position: relative;
            z-index: 2;
        }

        p {
            position: relative;
            z-index: 2;
            color: #555;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }

            .main-content {
                margin-left: 200px;
                padding: 20px;
            }

            .main-content::before {
                font-size: 8rem;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                position: absolute;
                z-index: 10;
                height: 100%;
            }

            .main-content {
                margin-left: 0;
            }

            .main-content::before {
                font-size: 5rem;
            }
        }

        .topbar {
            position: fixed;
            /* Fix it to the top */
            top: 0;
            left: 240px;
            /* Offset to match sidebar width */
            right: 0;
            height: 60px;
            /* Optional: consistent height */
            background-color: white;
            display: flex;
            align-items: center;
            padding: 0 30px;
            gap: 30px;
            border-bottom: 1px solid #ddd;
            z-index: 1000;
            /* Ensure it's on top of all layers */
        }


        .topbar-link {
            text-decoration: none;
            color: #444;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .topbar-link:hover {
            background-color: #f2f2f2;
        }

        .topbar-link.active {
            background-color: #b71c1c;
            color: white;
        }

        .header {
            margin-top: -45px;
            margin-bottom: 10px;
            padding: 20px 0;
            border-bottom: 1px solid #ddd;
            text-align: left;
            font-size: 0.95rem;
            color: #666;
        }

        .nav-btn {
            display: inline-block;
            background: rgba(0, 0, 0, 0.4);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.95rem;
            backdrop-filter: blur(2px);
            transition: all 0.3s ease;
        }

        .nav-btn:hover {
            transform: scale(1.05);
            background: rgba(255, 255, 255, 0.2);
            color: black;
            transform: scale(1.05);
            text-decoration: none;
        }

        .nav-button {
            text-align: right;
            margin-bottom: 20px;
        }

        h2 {
            margin-bottom: 1rem;
            color: #333;
        }

        form {
            background: #fff;
            padding: 2rem;
            max-width: 700px;
            margin-left: 0;
            /* Aligns form to the left */
            margin-right: auto;
            /* Keeps right margin flexible */
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }


        label {
            font-weight: bold;
            display: block;
            margin-top: 1.5rem;
        }

        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-top: 0.3rem;
            font-size: 1rem;
        }

        .option-item,
        .answer-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .option-item input[type="text"],
        .answer-item input[type="text"] {
            flex: 1;
        }

        button {
            margin-top: 2rem;
            background: #28a745;
            color: #fff;
            border: none;
            padding: 0.8rem 1.6rem;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
        }

        button:hover {
            background-color: #218838;
        }

        .add-option-btn {
            background: #007bff;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .remove-btn {
            background: #dc3545;
            padding: 0.3rem 0.6rem;
        }

        .remove-btn:hover {
            background: #c82333;
        }

        .info-text {
            font-size: 0.9rem;
            color: #555;
            margin-top: 0.5rem;
        }

        .hidden {
            display: none;
        }

        .question-card {
            background: #fff;
            border: 1px solid #ddd;
            border-left: 4px solid #007bff;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
            transition: all 0.2s ease-in-out;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .question-type {
            background: #f0f0f0;
            color: #555;
            padding: 2px 8px;
            font-size: 0.8rem;
            border-radius: 4px;
            margin-left: 10px;
            text-transform: capitalize;
        }

        .question-text {
            margin: 8px 0;
            font-weight: 500;
            color: #333;
        }

        .option-list,
        .answer-list {
            padding-left: 20px;
            margin-top: 5px;
            margin-bottom: 0;
        }

        .option-list li,
        .answer-list li {
            margin-bottom: 4px;
            color: #333;
        }

        .correct {
            font-weight: bold;
            color: green;
        }

        .check {
            margin-left: 8px;
            color: green;
        }

        .sensitive-tag {
            background: #f8d7da;
            color: #842029;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 5px;
        }

        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 10px;
            font-size: 0.9rem;
            border-radius: 4px;
            cursor: pointer;
        }

        .delete-btn:hover {
            background: #b02a37;
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="/img/left-logo.png" alt="BLAZE Logo">
        </div>

        <div class="nav">
            <a href="dashboard">Dashboard</a>
            <a href="unpublished">Unpublished</a>
            <a href="collaboration">Collaboration</a>
            <a href="msg" target="_blank">Chat</a>
            <a href="profile">Profile</a>
            <a href="logout">Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <a href="announcements" class="topbar-link">Bulletin</a>
            <a href="modules" class="topbar-link">Modules</a>
            <a href="assessments" class="topbar-link active">Assessments</a>
            <a href="question-bank" class="topbar-link">Question Bank</a>
            <a href="history" class="topbar-link">History</a>
            <a href="people" class="topbar-link">People</a>
            <a href="grades" class="topbar-link">Grades</a>
            <a href="ilo" class="topbar-link">ILO</a>
        </div>

        <!-- Header -->
        <div class="header">
            <p><strong><?= $course_code ?>:</strong> <?= $course_title ?></p>
        </div>

        <h2>Add Question to Assessment</h2>

        <form id="questionForm">
            <input type="hidden" name="assessment_id" id="assessment_id" value="<?= htmlspecialchars($assessment_id) ?>">

            <label for="question_type">Question Type</label>
            <select name="question_type" id="question_type" required>
                <option value="multiple_choice">Multiple Choice</option>
                <option value="identification">Fill in the Blanks</option>
                <option value="formula">Formula-Based Question</option>

            </select>

            <label for="question_text">Question Text</label>
            <textarea name="question_text" id="question_text" rows="3" placeholder="Enter your question..." required></textarea>

            <!-- Multiple Choice Section -->
            <div id="mcq-section">
                <label>Options (check the correct one/s)</label>
                <div id="option-container">
                    <div class="option-item">
                        <input type="checkbox" name="correct_mcq[]" value="0">
                        <input type="text" name="options[]" placeholder="Option 1">
                    </div>
                    <div class="option-item">
                        <input type="checkbox" name="correct_mcq[]" value="1">
                        <input type="text" name="options[]" placeholder="Option 2">
                    </div>
                </div>
                <button type="button" class="add-option-btn" onclick="addOption()">+ Add Option</button>
            </div>

            <!-- Identification Section -->
            <div id="identification-section" class="hidden">
                <label>Correct Answer(s) for each blank</label>
                <div id="identification-answers"></div>
                <p class="info-text">
                    Use <code>_</code> in the question text to indicate a blank. One answer field per blank will appear.<br>
                    For case-insensitive inputs, separate multiple correct answers with commas (e.g. <em>oxygen, o2</em>).<br>
                    Tick the checkbox beside an answer if it is case sensitive.
                </p>
            </div>

            <!-- Formula Section -->
            <div id="formula-section" class="hidden">
                <label for="formula_input">Formula</label>
                <input type="text" id="formula_input" name="formula" placeholder="e.g. [x] + [y] * 2 or sin([angle])" />

                <div id="variables-container"></div>

                <p class="info-text">
                    Use <code>[x]</code> for variables, <code>{...}</code> for answers in question text.<br>
                    Define variable ranges below for dynamic evaluation.
                </p>
            </div>


            <button type="button" onclick="saveToLocal()">+ Add Question</button>
        </form>

        <br>
        <hr>
        <br>
        <h2>Pending Questions</h2>

        <div id="previewContainer"></div>
        <button onclick="submitAll()">Submit All Questions</button>

        <!-- TinyMCE -->
        <script src="https://cdn.tiny.cloud/1/3meu9fvsi79o1afk1s1kb1s10s81u6vau3n4l4fqwh8vkjz5/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
        <script>
            tinymce.init({
                selector: '#question_text',
                height: 300,
                menubar: 'file edit view insert format tools table help',
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount'
                ],
                toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | ' +
                    'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ' +
                    'link image media table | removeformat | code fullscreen help',
                branding: false,
                setup: function(editor) {
                    editor.on('input', () => {
                        if (typeSelect.value === 'identification') {
                            updateIdentificationFields();
                        }
                    });

                    editor.on('input', () => {
                        if (typeSelect.value === 'formula') {
                            updateFormulaVariables();
                        } else if (typeSelect.value === 'identification') {
                            updateIdentificationFields();
                        }
                    });

                }


            });
        </script>
        <script>
            const typeSelect = document.getElementById('question_type');
            const mcqSection = document.getElementById('mcq-section');
            const idSection = document.getElementById('identification-section');
            const questionText = document.getElementById('question_text');
            const idAnswerContainer = document.getElementById('identification-answers');
            const optionContainer = document.getElementById('option-container');
            const previewContainer = document.getElementById('previewContainer');
            const formulaSection = document.getElementById('formula-section');

            let optionCount = 2;

            function toggleFieldRequirements() {
                const isMCQ = typeSelect.value === 'multiple_choice';
                const isID = typeSelect.value === 'identification';
                const isFormula = typeSelect.value === 'formula';

                // Show/hide section blocks
                mcqSection.classList.toggle('hidden', !isMCQ);
                idSection.classList.toggle('hidden', !isID);
                formulaSection.classList.toggle('hidden', !isFormula);

                // Set required attributes
                document.querySelectorAll('input[name="options[]"]').forEach(el => el.required = isMCQ);
                document.querySelectorAll('input[name="correct_answers[]"]').forEach(el => el.required = isID);
                document.getElementById('formula_input').required = isFormula;
            }


            function updateIdentificationFields() {
                const text = tinymce.get('question_text').getContent({
                    format: 'text'
                });
                const blanks = (text.match(/_/g) || []).length;
                const currentItems = Array.from(idAnswerContainer.querySelectorAll('.answer-item'));

                const existing = currentItems.map(item => ({
                    value: item.querySelector('input[name="correct_answers[]"]').value,
                    checked: item.querySelector('input[name="case_sensitive_flags[]"]').checked
                }));

                // If count doesn't change, don't update
                if (currentItems.length === blanks) return;

                idAnswerContainer.innerHTML = '';

                for (let i = 0; i < blanks; i++) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'answer-item';

                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = 'correct_answers[]';
                    input.placeholder = `Answer for Blank ${i + 1}`;
                    input.required = true;
                    input.value = existing[i]?.value || '';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'case_sensitive_flags[]';
                    checkbox.title = 'Case Sensitive';
                    checkbox.checked = existing[i]?.checked || false;

                    wrapper.appendChild(input);
                    wrapper.appendChild(checkbox);
                    idAnswerContainer.appendChild(wrapper);
                }
            }

            function updateFormulaVariables() {
                const text = tinymce.get('question_text').getContent({
                    format: 'text'
                });
                const variables = Array.from(new Set([...text.matchAll(/\[([a-zA-Z0-9_]+)\]/g)].map(m => m[1])));
                const container = document.getElementById('variables-container');
                container.innerHTML = '';

                variables.forEach(varName => {
                    const row = document.createElement('div');
                    row.className = 'variable-row';
                    row.innerHTML = `
            <label><strong>${varName}</strong></label>
            Min: <input type="number" name="var_min[${varName}]" required style="width: 70px;">
            Max: <input type="number" name="var_max[${varName}]" required style="width: 70px;">
        `;
                    container.appendChild(row);
                });
            }



            function addOption() {
                const div = document.createElement('div');
                div.className = 'option-item';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'correct_mcq[]';
                checkbox.value = optionCount;
                const input = document.createElement('input');
                input.type = 'text';
                input.name = 'options[]';
                input.placeholder = 'Option ' + (optionCount + 1);
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-btn';
                removeBtn.textContent = '✕';
                removeBtn.onclick = () => div.remove();
                div.appendChild(checkbox);
                div.appendChild(input);
                div.appendChild(removeBtn);
                optionContainer.appendChild(div);
                optionCount++;
            }

            function saveToLocal() {
                const questionData = {
                    assessment_id: document.getElementById('assessment_id').value,
                    type: typeSelect.value,
                    text: tinymce.get('question_text').getContent(),
                    options: [],
                    correct_mcq: [],
                    correct_answers: [],
                    case_sensitive: [],
                    formula: '',
                    variables: {}
                };

                if (questionData.type === 'multiple_choice') {
                    document.querySelectorAll('#option-container .option-item').forEach((el, i) => {
                        const text = el.querySelector('input[name="options[]"]').value;
                        const checked = el.querySelector('input[name="correct_mcq[]"]').checked;
                        questionData.options.push(text);
                        if (checked) questionData.correct_mcq.push(i);
                    });
                } else if (questionData.type === 'identification') {
                    document.querySelectorAll('input[name="correct_answers[]"]').forEach(el =>
                        questionData.correct_answers.push(el.value)
                    );
                    document.querySelectorAll('input[name="case_sensitive_flags[]"]').forEach(el =>
                        questionData.case_sensitive.push(el.checked)
                    );
                } else if (questionData.type === 'formula') {
                    questionData.formula = document.getElementById('formula_input').value;
                    questionData.variables = {};

                    document.querySelectorAll('#variables-container .variable-row').forEach(row => {
                        const varName = row.querySelector('label strong').textContent;
                        const min = row.querySelector(`input[name="var_min[${varName}]"]`).value;
                        const max = row.querySelector(`input[name="var_max[${varName}]"]`).value;
                        questionData.variables[varName] = {
                            min: parseFloat(min),
                            max: parseFloat(max)
                        };
                    });
                }

                const stored = JSON.parse(localStorage.getItem('assessment_questions') || '[]');
                stored.push(questionData);
                localStorage.setItem('assessment_questions', JSON.stringify(stored));

                renderPreview();
                document.getElementById('questionForm').reset();
                tinymce.get('question_text').setContent('');
                optionCount = 2;
                optionContainer.innerHTML = `
        <div class="option-item">
            <input type="checkbox" name="correct_mcq[]" value="0">
            <input type="text" name="options[]" placeholder="Option 1">
        </div>
        <div class="option-item">
            <input type="checkbox" name="correct_mcq[]" value="1">
            <input type="text" name="options[]" placeholder="Option 2">
        </div>`;
                updateIdentificationFields();
                updateFormulaVariables();
            }


            function renderPreview() {
                const stored = JSON.parse(localStorage.getItem('assessment_questions') || '[]');
                previewContainer.innerHTML = '';

                stored.forEach((q, i) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'question-card';

                    let extraContent = '';

                    if (q.type === 'multiple_choice') {
                        extraContent += '<ul class="option-list">';
                        q.options.forEach((opt, idx) => {
                            const isCorrect = q.correct_mcq.includes(idx);
                            extraContent += `<li class="${isCorrect ? 'correct' : ''}">
                    ${opt} ${isCorrect ? '<span class="check">✔</span>' : ''}
                </li>`;
                        });
                        extraContent += '</ul>';
                    } else if (q.type === 'identification') {
                        extraContent += '<ol class="answer-list">';
                        q.correct_answers.forEach((ans, idx) => {
                            const sensitive = q.case_sensitive[idx];
                            extraContent += `<li>${ans} ${sensitive ? '<span class="sensitive-tag">Case Sensitive</span>' : ''}</li>`;
                        });
                        extraContent += '</ol>';
                    } else if (q.type === 'formula') {
                        // Show formula string
                        extraContent += `
                <p><strong>Formula:</strong> <code>${q.formula}</code></p>
                <div class="variable-list">
                    <strong>Variables:</strong>
                    <ul>`;
                        for (const [varName, range] of Object.entries(q.variables || {})) {
                            extraContent += `<li><code>[${varName}]</code>: Min = ${range.min}, Max = ${range.max}</li>`;
                        }
                        extraContent += `</ul></div>`;

                        // Optional: show where the {} placeholders are in the question
                        const highlighted = q.text.replace(/{(.*?)}/g, '<mark>{$1}</mark>');
                        q.text = highlighted;
                    }

                    wrapper.innerHTML = `
            <div class="question-header">
                <div>
                    <strong>Q${i + 1}:</strong>
                    <span class="question-type">${q.type.replace('_', ' ')}</span>
                </div>
                <button onclick="removeQuestion(${i})" class="delete-btn">✕</button>
            </div>
            <div class="question-text">${q.text}</div>
            <div class="question-extra">${extraContent}</div>
            <div class="question-actions">
                <button class="save-bank-btn" onclick="saveToQuestionBank(${i})">💾 Save to Question Bank</button>
            </div>
        `;

                    previewContainer.appendChild(wrapper);
                });
            }



            // Remove individual question from localStorage
            function removeQuestion(index) {
                const stored = JSON.parse(localStorage.getItem('assessment_questions') || '[]');
                stored.splice(index, 1);
                localStorage.setItem('assessment_questions', JSON.stringify(stored));
                renderPreview();
            }

            function submitAll() {
                const data = JSON.parse(localStorage.getItem('assessment_questions') || '[]');
                if (!data.length) return alert('No questions to submit.');
                fetch('functions/save_questions_bulk', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                }).then(res => res.json()).then(res => {
                    if (res.success) {
                        localStorage.removeItem('assessment_questions');
                        alert('Questions saved!');
                        renderPreview();
                    }
                });
            }

            typeSelect.addEventListener('change', () => {
                toggleFieldRequirements();
                updateIdentificationFields();
            });

            questionText.addEventListener('input', () => {
                if (typeSelect.value === 'identification') {
                    updateIdentificationFields();
                }
            });

            window.addEventListener('DOMContentLoaded', () => {
                toggleFieldRequirements();
                updateIdentificationFields();
                renderPreview();
            });

            function saveToQuestionBank(index) {
                const stored = JSON.parse(localStorage.getItem('assessment_questions') || '[]');
                const q = stored[index];

                fetch('functions/save_to_question_bank', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(q)
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            alert('Question saved to question bank!');
                        } else {
                            alert('Failed to save question to bank.');
                        }
                    });
            }
        </script>

    </div>

</body>

</html>