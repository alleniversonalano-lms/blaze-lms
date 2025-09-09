<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Canvas-Style Quiz Builder — Fixed</title>

    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/3meu9fvsi79o1afk1s1kb1s10s81u6vau3n4l4fqwh8vkjz5/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <!-- mathjs for formula evaluation -->
    <script src="https://cdn.jsdelivr.net/npm/mathjs@12.4.1/lib/browser/math.js"></script>

    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="quiz-style/styles.css">
</head>

<body>
    <div class="wrap">

        <!-- Tabs -->
        <div class="tabs" role="tablist" aria-label="Quiz tabs">
            <button class="tab-btn active" data-tab="general">Quiz Settings</button>
            <button class="tab-btn" data-tab="questions">Questions</button>
            <button class="tab-btn" data-tab="preview">Preview</button>
        </div>

        <div class="layout">
            <div class="main">
                <!-- GENERAL -->
                <div id="general" class="card tab-pane" style="display:block;">
                    <div class="card-header">
                        <strong>General</strong>
                        <span class="muted">Quiz metadata & settings</span>
                    </div>
                    <div class="card-body">

                        <!-- Quiz Settings Card -->
                        <div class="card">
                            <div class="card-header">
                                <span>Quiz Settings</span>
                            </div>
                            <div class="card-body">

                                <!-- Quiz Title -->
                                <div class="settings-row">
                                    <div class="label-col"><label for="quiz_title">Quiz Title</label></div>
                                    <div class="field-col">
                                        <input id="quiz_title" type="text" placeholder="e.g., Midterm Exam">
                                    </div>
                                </div>

                                <!-- Instructions -->
                                <div class="settings-row">
                                    <div class="label-col"><label for="quiz_instructions">Instructions</label></div>
                                    <div class="field-col">
                                        <textarea id="quiz_instructions"></textarea>
                                    </div>
                                </div>

                                <!-- Quiz Type -->
                                <div class="settings-row">
                                    <div class="label-col"><label for="quiz_type">Quiz Type</label></div>
                                    <div class="field-col">
                                        <select id="quiz_type">
                                            <option value="practice">Practice</option>
                                            <option value="graded">Graded</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Time Limit -->
                                <div class="settings-row">
                                    <div class="label-col"><label for="time_limit_enable">Time Limit</label></div>
                                    <div class="field-col inline">
                                        <input id="time_limit_enable" type="checkbox">
                                        <label for="time_limit_enable" class="muted">Enable</label>
                                        <input id="time_limit" type="number" placeholder="Minutes" class="small-input" disabled>
                                    </div>
                                </div>

                                <!-- Multiple Attempts -->
                                <div class="settings-row">
                                    <div class="label-col"><label for="multi_attempts_enable">Multiple Attempts</label></div>
                                    <div class="field-col inline">
                                        <input id="multi_attempts_enable" type="checkbox">
                                        <label for="multi_attempts_enable" class="muted">Allow</label>
                                        <select id="score_to_keep" class="muted small-select">
                                            <option value="highest">Keep highest</option>
                                            <option value="latest">Keep latest</option>
                                            <option value="average">Average</option>
                                        </select>
                                        <input id="allowed_attempts" type="number" min="1" placeholder="Allowed attempts" style="width:140px" disabled>
                                    </div>
                                </div>

                                <!-- Shuffle Questions -->
                                <div class="settings-row">
                                    <div class="label-col"><label for="shuffle_questions">Shuffle Questions</label></div>
                                    <div class="field-col inline">
                                        <input id="shuffle_questions" type="checkbox">
                                        <label for="shuffle_questions" class="muted">Shuffle questions</label>
                                    </div>
                                </div>

                                <!-- Shuffle Answers -->
                                <div class="settings-row">
                                    <div class="label-col"><label for="shuffle_answers">Shuffle Answers</label></div>
                                    <div class="field-col inline">
                                        <input id="shuffle_answers" type="checkbox">
                                        <label for="shuffle_answers" class="muted">Shuffle answer choices</label>
                                    </div>
                                </div>

                                <!-- One Question at a Time -->
                                <div class="settings-row">
                                    <div class="label-col"><label for="one_question">Show One Question at a Time</label></div>
                                    <div class="field-col inline">
                                        <input id="one_question" type="checkbox">
                                        <label for="one_question" class="muted">Lock questions after answering</label>
                                    </div>
                                </div>

                            </div>
                        </div>


                        <!-- Responses -->
                        <div class="card">
                            <div class="card-header">
                                <span class="label-col">Responses</span>
                            </div>
                            <div class="card-body">

                                <div class="subheading" style="font-weight:600; margin-bottom:6px;">
                                    Let Students See Their Responses
                                </div>

                                <div class="options-container" style="margin-bottom:14px;">
                                    <label class="inline">
                                        <input type="checkbox">
                                        <span>Only once after each attempt</span>
                                    </label>
                                    <label class="inline">
                                        <input type="checkbox">
                                        <span>Let students see correct answers</span>
                                    </label>
                                </div>

                                <div class="settings-row">
                                    <div class="label-col">Show correct answers at</div>
                                    <div class="field-col date-time-row inline">
                                        <input type="date">
                                        <input type="time">
                                    </div>
                                </div>

                                <div class="settings-row">
                                    <div class="label-col">Hide correct answers at</div>
                                    <div class="field-col date-time-row inline">
                                        <input type="date">
                                        <input type="time">
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Assign -->
                        <div class="card">
                            <div class="card-header">
                                <span class="label-col">Assign</span>
                            </div>
                            <div class="card-body">

                                <div class="settings-row">
                                    <div class="label-col">Due Date</div>
                                    <div class="field-col date-time-row inline">
                                        <input type="date">
                                        <input type="time">
                                    </div>
                                </div>

                                <div class="settings-row">
                                    <div class="label-col">Available From</div>
                                    <div class="field-col date-time-row inline">
                                        <input type="date">
                                        <input type="time">
                                    </div>
                                </div>

                                <div class="settings-row">
                                    <div class="label-col">Until</div>
                                    <div class="field-col date-time-row inline">
                                        <input type="date">
                                        <input type="time">
                                    </div>
                                </div>

                            </div>
                        </div>


                        <!-- Buttons -->
                        <div class="inline" style="margin-top:8px">
                            <button id="save_quiz" class="primary">Save Quiz (demo)</button>
                            <button id="clear_all" class="small-btn">Clear All (demo)</button>
                        </div>

                    </div>
                </div>


                <!-- QUESTIONS -->
                <div id="questions" class="card tab-pane" style="display:none;">
                    <div class="card-header">
                        <strong>Questions</strong>
                        <div class="q-controls">
                            <select id="new_q_type" class="muted">
                                <option value="mcq">Multiple Choice</option>
                                <option value="fill">Fill in the Blank</option>
                                <option value="formula">Formula</option>
                            </select>
                            <button id="add_question" class="primary">+ Add Question</button>
                        </div>
                    </div>

                    <div class="card-body">
                        <div id="questionList" class="question-list" aria-live="polite"></div>
                    </div>
                </div>

                <!-- PREVIEW -->
                <div id="preview" class="card tab-pane" style="display:none;">
                    <div class="card-header"><strong>Preview</strong><span class="muted">What students will see</span></div>
                    <div class="card-body">
                        <div id="previewArea" style="margin-bottom:10px"></div>
                        <div class="card" style="border-radius:6px;padding:10px">
                            <div style="display:flex;justify-content:space-between;align-items:center">
                                <strong>Serialized JSON</strong>
                                <div>
                                    <button class="small-btn" id="copyJSON">Copy</button>
                                    <button class="small-btn" id="downloadJSON">Download</button>
                                </div>
                            </div>
                            <div style="margin-top:8px">
                                <pre id="jsonOut" class="json-output"></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- /layout -->

    </div> <!-- /wrap -->

    <script src="quiz-js/script.js"></script>
</body>

</html>