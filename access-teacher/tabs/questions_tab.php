<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Quiz Builder — Canvas-style Tabs</title>

    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #f5f6f8;
            --card: #fff;
            --muted: #6b7785;
            --accent: #008EE2;
            --text: #2d3b45;
            --border: #e5e8eb;
        }

        body {
            font-family: "Lato", sans-serif;
            background: var(--bg);
            margin: 0;
            color: var(--text);
        }

        /* Tabs (Canvas-like) */
        .tabs {
            display: flex;
            gap: 1rem;
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 0.5rem 1rem;
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .tab-btn {
            padding: 0.6rem 1rem;
            border-radius: 6px 6px 0 0;
            cursor: pointer;
            background: transparent;
            border: none;
            font-weight: 600;
            color: var(--muted);
        }

        .tab-btn.active {
            background: var(--card);
            color: var(--text);
            box-shadow: 0 -2px 0 0 var(--accent) inset;
        }

        .page {
            max-width: 1100px;
            margin: 1rem auto;
            padding: 1rem;
        }

        .row {
            display: flex;
            gap: 1rem;
        }

        .col-2 {
            flex: 0 0 320px;
        }

        .col-1 {
            flex: 1;
        }

        /* Header Add button */
        .top-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }

        button.primary-btn {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 700;
        }

        /* Card */
        .card {
            background: var(--card);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(20, 20, 20, 0.06);
            border: 1px solid var(--border);
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .card .card-header {
            padding: 10px 14px;
            background: #fbfcfd;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .card .card-header h4 {
            margin: 0;
            font-size: 15px;
            font-weight: 600
        }

        .card .card-body {
            padding: 14px;
            display: block;
        }

        /* Question card specifics */
        .question-card {
            margin-bottom: 12px;
            border-radius: 8px;
            overflow: visible;
        }

        .q-header {
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: space-between;
        }

        .q-left {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .q-meta {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .q-meta input,
        .q-meta select {
            padding: 6px;
            border-radius: 6px;
            border: 1px solid #d6dde3;
            background: #fff;
        }

        .card-body-inner {
            padding: 12px 0 0 0;
        }

        label {
            display: block;
            margin: 8px 0 6px 0;
            font-weight: 600;
            font-size: 13px;
        }

        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #d6dde3;
            box-sizing: border-box;
            font-size: 14px;
        }

        textarea {
            min-height: 70px;
            resize: vertical;
        }

        .section {
            background: #fbfdff;
            border: 1px solid #edf1f4;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
        }

        .mcq-row {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-top: 8px;
        }

        .mcq-row input[type="text"] {
            flex: 1;
        }

        .mcq-row .remove-opt {
            background: transparent;
            border: none;
            color: #d9534f;
            cursor: pointer;
            font-size: 14px;
        }

        .small-btn {
            font-size: 13px;
            padding: 6px 8px;
            border-radius: 6px;
            border: 1px solid #d6dde3;
            background: #fff;
            cursor: pointer;
        }

        .muted {
            color: var(--muted);
            font-weight: 400;
            font-size: 13px;
        }

        .preview-box {
            background: #fff;
            border: 1px solid #e8edf2;
            padding: 12px;
            border-radius: 6px;
        }

        pre.json-output {
            background: #0f1720;
            color: #d1ffb8;
            padding: 12px;
            border-radius: 6px;
            max-height: 300px;
            overflow: auto;
            font-size: 13px;
        }

        .inline {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .toggle {
            display: inline-flex;
            gap: 6px;
            align-items: center;
        }

        .hidden {
            display: none !important;
        }

        /* make header controls not trigger collapse when clicked */
        .no-toggle {
            pointer-events: auto;
        }

        /* responsiveness */
        @media (max-width: 880px) {
            .row {
                flex-direction: column;
            }

            .col-2 {
                flex: unset;
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="tabs" role="tablist" aria-label="Quiz tabs">
        <button class="tab-btn active" data-tab="settings" onclick="openTab('settings', this)">Quiz Settings</button>
        <button class="tab-btn" data-tab="questions" onclick="openTab('questions', this)">Questions</button>
        <button class="tab-btn" data-tab="preview" onclick="openTab('preview', this)">Preview</button>
    </div>

    <!-- Top-level form (submit to your backend) -->
    <form id="quizForm" class="page" method="POST" onsubmit="return submitQuiz(event)">
        <div id="settings" class="tab-pane">
            <div class="card">
                <div class="card-header">
                    <h4>Quiz Settings</h4>
                    <div class="muted">Configure the quiz before adding questions</div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-1">
                            <label>Quiz Title</label>
                            <input name="title" type="text" placeholder="e.g., Midterm Exam">
                            <label style="margin-top:12px">Description</label>
                            <textarea name="description" rows="4" placeholder="Instructions / description"></textarea>
                        </div>

                        <div class="col-2">
                            <label>Availability</label>
                            <div class="inline">
                                <input name="start_time" type="datetime-local">
                                <input name="end_time" type="datetime-local">
                            </div>

                            <label style="margin-top:12px">Timing & Attempts</label>
                            <div style="display:flex; gap:8px; flex-direction:column;">
                                <input name="time_limit" type="number" placeholder="Time limit (minutes)">
                                <input name="max_attempts" type="number" placeholder="Max attempts">
                            </div>

                            <label style="margin-top:12px">Other settings</label>
                            <div style="display:flex; flex-direction:column; gap:8px;">
                                <label class="toggle"><input name="shuffle" type="checkbox"> Shuffle questions</label>
                                <label class="toggle"><input name="highest_only" type="checkbox"> Record highest score only</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="questions" class="tab-pane hidden">
            <div class="top-actions">
                <button type="button" class="primary-btn" onclick="addQuestion()">+ Add Question</button>
            </div>

            <div id="questionList" aria-live="polite"></div>

            <!-- Hidden field to send questions JSON to backend -->
            <textarea id="questions_json" name="questions_json" class="hidden"></textarea>
        </div>

        <div id="preview" class="tab-pane hidden">
            <div class="row">
                <div class="col-1">
                    <div class="card">
                        <div class="card-header">
                            <h4>Preview</h4>
                        </div>
                        <div class="card-body">
                            <div id="previewList" class="preview-box"></div>
                        </div>
                    </div>
                </div>

                <div class="col-2">


                    <div style="margin-top:12px; text-align:right;">
                        <button type="submit" class="primary-btn">Save & Publish</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Libraries (ensure these load before main script) -->
    <script src="https://cdn.tiny.cloud/1/3meu9fvsi79o1afk1s1kb1s10s81u6vau3n4l4fqwh8vkjz5/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script src="https://cdn.jsdelivr.net/npm/mathjs@12.4.1/lib/browser/math.js"></script>

    <script>
        /* ===========================
   State & configuration
   =========================== */
        let questionCounter = 0;
        const singleOpen = true; // if true, opening a question will close others
        const questionListEl = document.getElementById('questionList');

        /* ===========================
           TAB handling
           =========================== */
        function openTab(tabId, btnEl) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btnEl.classList.add('active');

            document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
            document.getElementById(tabId).classList.remove('hidden');

            // If switching to preview, update contents
            if (tabId === 'preview') updatePreview();
        }

        /* ===========================
           Add / remove / reorder questions
           =========================== */
        function addQuestion() {
            questionCounter++;
            const qIndex = questionCounter;
            const qId = `q-${qIndex}`;

            // Create DOM
            const wrapper = document.createElement('div');
            wrapper.className = 'card question-card';
            wrapper.dataset.qid = qId;

            wrapper.innerHTML = `
    <div class="card-header">
      <div class="q-header" style="width:100%">
        <div class="q-left">
          <div style="min-width:110px"><strong>Question ${qIndex}</strong></div>
          <div class="q-meta no-toggle">
            <select onchange="changeQuestionType('${qId}', this.value)" title="Type" aria-label="Question type">
              <option value="mcq">Multiple Choice</option>
              <option value="fill">Fill in the Blank</option>
              <option value="formula">Formula</option>
            </select>
            <input type="number" min="0" value="1" title="Points" style="width:80px" onchange="updatePreview()">
          </div>
        </div>

        <div style="display:flex; gap:8px; align-items:center;">
          <button class="small-btn no-toggle" onclick="toggleCardBody(event, '${qId}')">Toggle</button>
          <button class="small-btn no-toggle" onclick="duplicateQuestion(event, '${qId}')">Duplicate</button>
          <button class="small-btn no-toggle" onclick="removeQuestion(event, '${qId}')">Delete</button>
        </div>
      </div>
    </div>

    <div class="card-body" id="${qId}" style="display:block">
      <div class="card-body-inner">
        <label>Question Text</label>
        <textarea id="editor-${qId}" class="q-editor" placeholder="Write the question..."></textarea>

        <!-- Multiple Choice -->
        <div class="section type-mcq" data-qid="${qId}">
          <label style="margin-bottom:6px;">
            <input type="checkbox" class="mcq-multiple" onchange="toggleMCQMultiple('${qId}')" /> Allow multiple correct answers
          </label>
          <div class="options-container" data-qid="${qId}"></div>
          <div style="margin-top:8px;">
            <button class="add-btn" onclick="addMCQOption(event, '${qId}')">+ Add option</button>
          </div>
        </div>

        <!-- Fill in the blank -->
        <div class="section type-fill hidden" data-qid="${qId}">
          <p class="muted">Use <code>{answer}</code> inside the question text to create blanks. Example: The capital of France is <code>{Paris}</code></p>
          <label>Detected blanks / correct answers</label>
          <div class="fill-answers" data-qid="${qId}"></div>
        </div>

        <!-- Formula -->
<div class="section type-formula hidden" data-qid="${qId}">

  <!-- Variable Definition Table -->
  <label style="font-weight:bold;">Variable Definitions</label>
  <table class="variable-table" data-qid="${qId}" style="width:100%; border-collapse:collapse; margin-top:6px; font-size:14px;">
    <thead>
      <tr style="background:#f8f9fa; border-bottom:1px solid #ccc;">
        <th style="padding:6px; text-align:left;">Variable</th>
        <th style="padding:6px; text-align:center;">Min</th>
        <th style="padding:6px; text-align:center;">Max</th>
        <th style="padding:6px; text-align:center;">Increment</th>
        <th style="padding:6px; text-align:center;">Decimals</th>
        <th style="padding:6px; text-align:center;">Sample</th>
        <th style="width:40px;"></th>
      </tr>
    </thead>
    <tbody>
      <!-- Rows dynamically added -->
    </tbody>
  </table>
  <div style="margin-top:8px;">
    <button class="add-btn" onclick="addFormulaVariable(event,'${qId}')">+ Add Variable</button>
  </div>

  <hr style="margin:12px 0; border:none; border-top:1px solid #ddd;">

  <!-- Formula Definition -->
  <label style="font-weight:bold;">Formula Expression</label>
  <div style="display:flex; align-items:center; gap:8px; margin-top:4px;">
    <input class="formula-expression" data-qid="${qId}" placeholder="e.g., a + b * c" style="flex:1; padding:6px;">
    <label style="white-space:nowrap;">Decimals:</label>
    <input type="number" class="decimal-places" data-qid="${qId}" value="2" min="0" max="10" style="width:70px; padding:6px;">
  </div>

  <div style="margin-top:10px;">
    <button class="add-btn" onclick="generateSampleSolution(event,'${qId}')">Generate Sample Solution</button>
  </div>
  <div class="sample-solution" data-qid="${qId}" style="margin-top:8px; font-weight:bold; color:#2c3e50;"></div>

  <hr style="margin:12px 0; border:none; border-top:1px solid #ddd;">

  <!-- Sample Generator -->
  <div style="display:flex; align-items:center; gap:8px;">
    <label style="white-space:nowrap;">Number of Samples:</label>
    <select class="sample-count" data-qid="${qId}" style="padding:4px;">
      ${Array.from({ length: 100 }, (_, i) => ` < option value = "${i+1}" > $ {
                    i + 1
                } < /option>`).join('')} <
                /select> <
                button class = "add-btn"
            onclick = "generateMultipleSamples(event,'${qId}')" > Generate Samples < /button> <
                /div>

                <
                pre class = "sample-output muted"
            data - qid = "${qId}"
            style = "margin-top:8px; white-space:pre-wrap; background:#f8f9fa; padding:6px; border-radius:4px;" > < /pre> <
                /div>


                <
                /div> <
                /div>
            `;

            questionListEl.appendChild(wrapper);

            // Add two default options for MCQ
            addMCQOption(null, qId);
            addMCQOption(null, qId);

            // init editor
            initEditor(`
            editor - $ {
                qId
            }
            `, qId);

            // ensure only this is open if singleOpen
            if (singleOpen) {
                document.querySelectorAll('.card-body').forEach(b => {
                    if (b.id !== qId) b.style.display = 'none';
                });
            }

            // update detected blanks initially
            setTimeout(() => updateFillAnswers(qId), 120);
            updatePreview();
        }

        /* Duplicate a question (deep-ish copy) */
        function duplicateQuestion(e, qId) {
            e.stopPropagation();
            const card = document.querySelector(` [data - qid = "${qId}"] `);
            if (!card) return;

            const copy = card.cloneNode(true);
            // Remove editors from copy to avoid id collision — we'll rebuild
            const newIndex = ++questionCounter;
            const newQId = `
            q - $ {
                newIndex
            }
            `;
            copy.dataset.qid = newQId;

            // update header title / ids / editor id / callbacks
            copy.querySelectorAll('[id]').forEach(el => {
                if (el.id.includes(qId)) {
                    const newId = el.id.replace(qId, newQId);
                    el.id = newId;
                }
            });

            // update inline data-qid attributes
            copy.querySelectorAll('[data-qid]').forEach(el => {
                if (el.dataset.qid === qId) el.dataset.qid = newQId;
            });

            // update onclick attributes that reference qId (simple replace)
            copy.innerHTML = copy.innerHTML.replaceAll(qId, newQId);

            // append and init editor
            questionListEl.appendChild(copy);
            const editorId = `
            editor - $ {
                newQId
            }
            `;
            const oldEditor = tinymce.get(`
            editor - $ {
                qId
            }
            `);
            const content = oldEditor ? oldEditor.getContent() : '';

            // remove any tinymce instance that may have been copied (ensure uniqueness)
            if (tinymce.get(editorId)) tinymce.get(editorId).remove();

            setTimeout(() => {
                initEditor(editorId, newQId, content);
                updateFillAnswers(newQId);
                updatePreview();
            }, 50);
        }

        /* Remove question */
        function removeQuestion(e, qId) {
            e.stopPropagation();
            const body = document.getElementById(qId);
            if (!body) return;
            // remove tinyMCE instance
            const editor = body.querySelector('.q-editor');
            if (editor && tinymce.get(editor.id)) {
                tinymce.get(editor.id).remove();
            }
            // remove card
            const card = document.querySelector(` [data - qid = "${qId}"] `);
            if (card) card.remove();
            updatePreview();
        }

        /* Toggle body (used by buttons) */
        function toggleCardBody(e, qId) {
            e.stopPropagation();
            const body = document.getElementById(qId);
            if (!body) return;
            const showing = body.style.display !== 'none' && body.style.display !== '';
            // close others
            if (singleOpen && !showing) {
                document.querySelectorAll('.card-body').forEach(b => {
                    if (b.id !== qId) b.style.display = 'none';
                });
            }
            body.style.display = showing ? 'none' : 'block';
        }

        /* ===========================
           QUESTION TYPE & MCQ handling
           =========================== */
        function changeQuestionType(qId, type) {
            const container = document.getElementById(qId);
            if (!container) return;
            container.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            const section = container.querySelector(`.type - $ {
                type
            }
            `);
            if (section) section.classList.remove('hidden');

            // when switching to fill, re-run detection
            if (type === 'fill') updateFillAnswers(qId);
            updatePreview();
        }

        function addMCQOption(e, qId) {
            if (e) e.stopPropagation();
            const container = document.querySelector(`
            #$ {
                qId
            }.options - container`);
            if (!container) return;

            // default: single-select (radio) unless mcq-multiple checked
            const mcqSection = container.closest('.type-mcq');
            const isMultiple = mcqSection.querySelector('.mcq-multiple').checked;
            const opt = document.createElement('div');
            opt.className = 'mcq-row';

            const controlHtml = isMultiple ?
                ` < input type = "checkbox"
            class = "mcq-correct" > ` :
                ` < input type = "radio"
            class = "mcq-correct"
            name = "correct-${qId}" > `;

            opt.innerHTML = `
            $ {
                controlHtml
            } <
            input type = "text"
            class = "mcq-text"
            placeholder = "Option text"
            oninput = "updatePreview()" >
                <
                button class = "remove-opt"
            title = "Remove option"
            onclick = "this.parentElement.remove(); updatePreview();" > ✕ < /button>
            `;

            container.appendChild(opt);
            updatePreview();
        }

        /* Toggle MCQ single/multiple and convert existing controls */
        function toggleMCQMultiple(qId) {
            const mcqSection = document.querySelector(`
            #$ {
                qId
            }.type - mcq`);
            const isMultiple = mcqSection.querySelector('.mcq-multiple').checked;
            const container = mcqSection.querySelector('.options-container');

            // convert each .mcq-correct to checkbox or radio
            container.querySelectorAll('.mcq-row').forEach(row => {
                const old = row.querySelector('.mcq-correct');
                const checked = old.checked;
                const newInput = document.createElement('input');
                newInput.className = 'mcq-correct';
                newInput.type = isMultiple ? 'checkbox' : 'radio';
                if (!isMultiple) newInput.name = `
            correct - $ {
                qId
            }
            `; // group radios
                newInput.checked = checked;
                old.replaceWith(newInput);
            });

            updatePreview();
        }

        /* ===========================
           Fill in the blank detection
           =========================== */
        function updateFillAnswers(qId) {
            const body = document.getElementById(qId);
            if (!body) return;
            const editor = body.querySelector('.q-editor');
            let raw = '';

            // try tinymce
            if (editor && tinymce.get(editor.id)) {
                raw = tinymce.get(editor.id).getContent({
                    format: 'text'
                });
            } else if (editor) {
                raw = editor.value;
            }

            // find {answer} occurrences
            const matches = [...raw.matchAll(/\{([^}]+)\}/g)];
            const container = body.querySelector('.fill-answers');
            container.innerHTML = '';
            if (matches.length === 0) {
                container.innerHTML = '<div class="muted">No blanks detected in question text.</div>';
                updatePreview();
                return;
            }

            matches.forEach((m, idx) => {
                const answer = m[1];
                const row = document.createElement('div');
                row.style.display = 'flex';
                row.style.gap = '8px';
                row.style.marginTop = '6px';
                row.innerHTML = ` <
            div style = "flex:1" >
                <
                label class = "muted" > Blank $ {
                    idx + 1
                } < /label> <
                input type = "text"
            class = "fill-ans"
            value = "${escapeHtml(answer)}"
            oninput = "updatePreview()" >
                <
                /div> <
                div style = "width:120px; display:flex; align-items:flex-end; gap:6px;" >
                <
                label class = "toggle muted" > < input type = "checkbox"
            class = "fill-case" > Case sensitive < /label> <
                /div>
            `;
                container.appendChild(row);
            });

            updatePreview();
        }

        /* escape html for safe insertion */
        function escapeHtml(text) {
            return String(text).replace(/[&<>"']/g, function(m) {
                return ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[m];
            });
        }

        /* ===========================
           Formula variable handling & sample generation
           =========================== */
        function addFormulaVariable(e, qId) {
            if (e) e.stopPropagation();
            const container = document.querySelector(`
            #$ {
                qId
            }.variables - container`);
            const id = `
            var -$ {
                Date.now()
            }
            `;
            const el = document.createElement('div');
            el.style.display = 'flex';
            el.style.gap = '8px';
            el.style.alignItems = 'center';
            el.style.marginTop = '8px';
            el.className = 'var-row';
            el.innerHTML = ` <
            input type = "text"
            class = "var-name"
            placeholder = "name (e.g., i)"
            style = "width:80px"
            oninput = "updatePreview()" >
                <
                input type = "number"
            class = "var-min"
            placeholder = "min"
            style = "width:90px"
            oninput = "updatePreview()" >
                <
                input type = "number"
            class = "var-max"
            placeholder = "max"
            style = "width:90px"
            oninput = "updatePreview()" >
                <
                input type = "number"
            class = "var-dec"
            placeholder = "dec"
            value = "2"
            style = "width:70px" >
                <
                input type = "text"
            class = "var-example"
            placeholder = "example"
            readonly style = "width:110px" >
                <
                button class = "small-btn"
            onclick = "recomputeExample(event, this)" > Recompute < /button> <
                button class = "small-btn"
            onclick = "this.parentElement.remove(); updatePreview();" > Remove < /button>
            `;
            container.appendChild(el);
            updatePreview();
        }

        /* recompute example midpoint or random in range */
        function recomputeExample(e, btn) {
            e.stopPropagation();
            const row = btn.closest('.var-row');
            const min = parseFloat(row.querySelector('.var-min').value);
            const max = parseFloat(row.querySelector('.var-max').value);
            const dec = parseInt(row.querySelector('.var-dec').value) || 2;
            const out = row.querySelector('.var-example');

            if (isNaN(min) || isNaN(max) || min > max) {
                out.value = 'invalid range';
                return;
            }
            const val = ((min + max) / 2);
            out.value = Number(val).toFixed(dec);
            updatePreview();
        }

        /* generate possible solutions (uses mathjs) */
        function generatePossibleSolutions(e, qId) {
            e.stopPropagation();
            const body = document.getElementById(qId);
            if (!body) return;

            const expr = body.querySelector('.formula-expression').value.trim();
            const solveFor = body.querySelector('.expected-answer').value.trim();
            const tol = parseFloat(body.querySelector('.tolerance').value) || 0.01;
            const dec = parseInt(body.querySelector('.decimal-places').value) || 2;

            // prepare scope from variable examples or random midpoints
            const vars = body.querySelectorAll('.var-row');
            const scope = {
                pi: Math.PI
            };
            vars.forEach(v => {
                const name = v.querySelector('.var-name').value.trim();
                const min = parseFloat(v.querySelector('.var-min').value);
                const max = parseFloat(v.querySelector('.var-max').value);
                const example = v.querySelector('.var-example').value;
                let val = NaN;
                if (example && !isNaN(parseFloat(example))) val = parseFloat(example);
                else if (!isNaN(min) && !isNaN(max) && min <= max) val = (min + max) / 2;
                if (name && !isNaN(val)) scope[name] = val;
            });

            const out = body.querySelector('.sample-output');
            out.textContent = 'Computing...';

            try {
                // naive solve: if formula contains '=', we isolate; else we evaluate expression directly
                if (!expr.includes('=')) {
                    // direct eval: compute expression using variables
                    const val = math.evaluate(expr.replace(/\[([a-zA-Z0-9_]+)\]/g, '$1'), scope);
                    out.textContent = `
            Expression result: $ {
                round(val, dec)
            }±
            $ {
                tol
            }
            `;
                } else {
                    const [lhsRaw, rhsRaw] = expr.split('=').map(s => s.trim());
                    const lhs = lhsRaw.replace(/\[([a-zA-Z0-9_]+)\]/g, '$1');
                    const rhs = rhsRaw.replace(/\[([a-zA-Z0-9_]+)\]/g, '$1');

                    let final;
                    if (lhs === solveFor) final = math.evaluate(rhs, scope);
                    else if (rhs === solveFor) final = math.evaluate(lhs, scope);
                    else {
                        // try to symbolically solve (mathjs limited); fallback: evaluate both sides and attempt solve
                        const eq = math.parse(`
            $ {
                lhs
            } = $ {
                rhs
            }
            `);
                        const solved = math.solve ? math.solve(eq, solveFor) : null;
                        if (solved && solved.length) final = math.evaluate(solved[0], scope);
                        else {
                            // fallback: try to evaluate rhs/lhs if variables resolved
                            final = math.evaluate(rhs, scope);
                        }
                    }
                    out.textContent = `
            Solve
            for $ {
                solveFor
            }: $ {
                round(final, dec)
            }±
            $ {
                tol
            }\
            nVariables: $ {
                JSON.stringify(scope)
            }
            `;
                }
            } catch (err) {
                out.textContent = `
            Error: $ {
                err
            }
            `;
            }
            updatePreview();
        }

        function round(v, dec) {
            return Number(v).toFixed(dec);
        }

        /* ===========================
           Editor (TinyMCE) init
           =========================== */
        function initEditor(editorId, qId, initialContent = '') {
            // ensure uniqueness
            if (tinymce.get(editorId)) tinymce.get(editorId).remove();

            tinymce.init({
                selector: `
            #$ {
                editorId
            }
            `,
                height: 200,
                menubar: false,
                plugins: ['link', 'lists', 'table', 'code', 'image', 'media'],
                toolbar: 'undo redo | bold italic underline | bullist numlist | alignleft aligncenter alignright | link image | code',
                branding: false,
                init_instance_callback: function(editor) {
                    if (initialContent) editor.setContent(initialContent);
                    editor.on('Change KeyUp', function() {
                        // small debounce: update fill answers and preview
                        updateFillAnswers(qId);
                        updatePreview();
                    });
                }
            });
        }

        /* ===========================
           PREVIEW / SERIALIZE
           =========================== */
        function updatePreview() {
            const previewList = document.getElementById('previewList');
            
            const questions = [];

            previewList.innerHTML = '';

            document.querySelectorAll('.question-card').forEach((card, idx) => {
                const qId = card.dataset.qid;
                const body = document.getElementById(qId);
                if (!body) return;

                const typeSelect = card.querySelector('select');
                const qType = typeSelect ? typeSelect.value : 'mcq';
                const points = card.querySelector('input[type="number"]') ? Number(card.querySelector('input[type="number"]').value) : 1;

                // get text (tinymce if available)
                const editorEl = body.querySelector('.q-editor');
                let text = '';
                if (editorEl) {
                    const ed = tinymce.get(editorEl.id);
                    text = ed ? ed.getContent() : editorEl.value;
                }

                const question = {
                    type: qType,
                    points: points,
                    text: text
                };

                const wrapper = document.createElement('div');
                wrapper.style.padding = '8px 0';
                wrapper.innerHTML = ` < strong > Q$ {
                idx + 1
            }($ {
                qType.replace('_', ' ')
            }): < /strong>`;

            if (qType === 'mcq') {
                const options = [];
                const mcqRows = body.querySelectorAll('.options-container .mcq-row');
                const isMultiple = body.querySelector('.mcq-multiple').checked;
                const ul = document.createElement('ul');
                mcqRows.forEach(r => {
                    const txt = r.querySelector('.mcq-text')?.value || '';
                    const correct = r.querySelector('.mcq-correct')?.checked || false;
                    options.push({
                        text: txt,
                        is_correct: correct
                    });
                    const li = document.createElement('li');
                    li.innerHTML = `${escapeHtml(txt)} ${correct ? '<strong style="color:green"> (Correct)</strong>' : ''}`;
                    ul.appendChild(li);
                });
                wrapper.appendChild(ul);
                question.options = options;
                question.allow_multiple = isMultiple;
            } else if (qType === 'fill') {
                const blanks = [];
                const answers = body.querySelectorAll('.fill-answers .fill-ans');
                answers.forEach((a, i) => {
                    const val = a.value || '';
                    const caseSensitive = a.closest('div').querySelector('.fill-case')?.checked || false;
                    blanks.push({
                        index: i,
                        answer: val,
                        case_sensitive: caseSensitive
                    });
                });
                if (blanks.length === 0) wrapper.appendChild(document.createElement('div')).innerHTML = '<em class="muted">No blanks detected.</em>';
                question.blanks = blanks;
            } else if (qType === 'formula') {
                const vars = [];
                body.querySelectorAll('.var-row').forEach(v => {
                    const name = v.querySelector('.var-name')?.value || '';
                    const min = parseFloat(v.querySelector('.var-min')?.value || NaN);
                    const max = parseFloat(v.querySelector('.var-max')?.value || NaN);
                    const dec = parseInt(v.querySelector('.var-dec')?.value || 2);
                    const example = v.querySelector('.var-example')?.value || '';
                    if (name) vars.push({
                        name,
                        min,
                        max,
                        decimals: dec,
                        example
                    });
                });
                const expr = body.querySelector('.formula-expression')?.value || '';
                const expected = body.querySelector('.expected-answer')?.value || '';
                const decimals = parseInt(body.querySelector('.decimal-places')?.value || 2);
                const tolerance = parseFloat(body.querySelector('.tolerance')?.value || 0.01);
                question.variables = vars;
                question.formula = {
                    expression: expr,
                    expected: expected,
                    decimals,
                    tolerance
                };
                // include sample-output (if present)
                question.sample_output = body.querySelector('.sample-output')?.textContent || '';
                // add a short formula preview
                const pre = document.createElement('div');
                pre.className = 'muted';
                pre.style.marginTop = '6px';
                pre.textContent = `Formula: ${expr} | Solve for: ${expected}`;
                wrapper.appendChild(pre);
            }

            questions.push(question);
            previewList.appendChild(wrapper);
        });

        // JSON stringify
        const json = JSON.stringify(questions, null, 2);
        out.textContent = json;

        // update hidden field for backend
        document.getElementById('questions_json').value = json;
        }

        

        /* Serialize and submit form */
        function submitQuiz(e) {
            // ensure preview and questions json are up-to-date
            updatePreview();
            // you can perform validation here
            // let payload = document.getElementById('questions_json').value;
            // send to backend via regular POST or fetch — form submits by default
            // For debug, prevent submit:
            // e.preventDefault(); console.log('Would submit:', payload); return false;
            return true; // allow normal submit
        }

        /* when switching to preview tab, ensure it's freshly updated */
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.dataset.tab === 'preview') updatePreview();
            });
        });

        /* update preview on general input using delegation */
        document.addEventListener('input', function(e) {
            if (e.target.matches('.mcq-text') || e.target.matches('.fill-ans') || e.target.matches('.var-min') || e.target.matches('.var-max') || e.target.matches('.formula-expression') || e.target.matches('.expected-answer') || e.target.matches('.decimal-places') || e.target.matches('.tolerance')) {
                // small throttle not implemented — it's fine for reasonable number of questions
                updatePreview();
            }
        });

        /* remove any tinymce editors before page unload to avoid issues */
        window.addEventListener('beforeunload', function() {
            tinymce.remove();
        });
    </script>
</body>

</html>