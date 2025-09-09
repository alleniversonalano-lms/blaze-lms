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

    <style>
        :root {
            --bg: #f5f6f8;
            --card: #ffffff;
            --muted: #6b7785;
            --accent: #8b1f1f;
            --accent-hover: #a52626;
            --text: #2d3b45;
            --border: #e6eaee;
            --shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: "Lato", sans-serif;
            background: var(--bg);
            margin: 0;
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            line-height: 1.5;
        }

        .wrap {
            max-width: 1100px;
            margin: 28px auto;
            padding: 18px;
        }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 18px;
        }

        .tab-btn {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid transparent;
            background: transparent;
            cursor: pointer;
            font-weight: 700;
            color: var(--muted);
            transition: all 0.2s ease-in-out;
        }

        .tab-btn:hover {
            background: rgba(0, 0, 0, 0.03);
        }

        .tab-btn.active {
            background: var(--card);
            border-color: var(--border);
            box-shadow: var(--shadow);
            color: var(--text);
        }

        .layout {
            display: flex;
            gap: 18px;
        }

        .main {
            flex: 1;
        }

        /* Canvas-style card */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 14px;
            overflow: hidden;
            transition: transform 0.2s ease-in-out;
        }

        .card-header {
            padding: 12px 14px;
            background: #fbfcfd;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body {
            padding: 14px;
        }

        .settings-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
            align-items: flex-start;
        }

        .label-col {
            flex: 0 0 240px;
            font-weight: 600;
            color: var(--text);
            padding-top: 6px;
        }

        .field-col {
            flex: 1;
        }

        input[type=text],
        input[type=number],
        select,
        textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #d6dde3;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--accent);
            outline: none;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .muted {
            color: var(--muted);
            font-size: 13px;
        }

        .small-btn {
            padding: 6px 8px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: #fff;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .small-btn:hover {
            background: rgba(0, 0, 0, 0.04);
        }

        .primary {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s ease;
        }

        .primary:hover {
            background: var(--accent-hover);
        }

        .question-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .question-card {
            border-radius: 8px;
            border: 1px solid #e9eef2;
            background: #fbfeff;
            padding: 0;
            overflow: visible;
            transition: box-shadow 0.2s ease-in-out;
        }

        .question-card:hover {
            box-shadow: var(--shadow);
        }

        .q-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
        }

        .q-body {
            padding: 12px;
        }

        .q-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .options-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 8px;
        }

        .mcq-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .mcq-row input[type="text"] {
            flex: 1;
        }

        .remove-opt {
            background: transparent;
            border: none;
            color: #c33;
            cursor: pointer;
            font-size: 14px;
        }

        .hidden {
            display: none !important;
        }

        .inline {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        pre.json-output {
            background: #0b1220;
            color: #d2ffb0;
            padding: 12px;
            border-radius: 6px;
            max-height: 260px;
            overflow: auto;
            font-family: monospace;
        }

        /* Responsive */
        @media (max-width: 880px) {
            .label-col {
                flex-basis: 140px;
            }

            .layout {
                flex-direction: column;
            }
        }
    </style>
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


<script>
/* =============================
   Fixed Questions Manager
   ============================= */

(() => {
  /*** State ***/
  let questionCounter = 0;
  const questions = []; // { id, index, type, title, text, points, ... }

  /*** Helpers ***/
  function $(sel, ctx = document) { return ctx.querySelector(sel); }
  function $all(sel, ctx = document) { return Array.from(ctx.querySelectorAll(sel)); }
  function safeEl(id) { return document.getElementById(id); }
  function escapeHtml(s) {
    return String(s || '').replace(/[&<>\"']/g, m => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));
  }
  function safe(fn){ try { return fn(); } catch (err) { console.error(err); } }

  /*** TinyMCE global (instructions) ***/
  if (window.tinymce && typeof tinymce.init === 'function') {
    safe(() => tinymce.init({
      selector: '#quiz_instructions',
      height: 220,
      menubar: false,
      plugins: ['link','lists','table','code','image'],
      toolbar: 'undo redo | bold italic | bullist numlist | link | code',
      branding: false
    }));
  } else {
    console.warn('TinyMCE not detected — global instructions will be plain textarea.');
  }

  /*** Tab handling (only top-level panes) ***/
  $all('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      $all('.tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const tab = btn.dataset.tab;
      $all('.main > .tab-pane').forEach(p => p.style.display = 'none');
      const target = safeEl(tab);
      if (target) target.style.display = 'block';
      if (tab === 'preview') buildPreview();
    });
  });

  /*** General toggles (guarded) ***/
  safe(() => { $('#time_limit_enable').addEventListener('change', function(){ $('#time_limit').disabled = !this.checked; }); });
  safe(() => { $('#multi_attempts_enable').addEventListener('change', function(){ const en = this.checked; $('#allowed_attempts').disabled = !en; $('#score_to_keep').disabled = !en; }); });

  /*************************
   * FIXED: Preserve content before re-rendering
   *************************/
  function preserveQuestionData() {
    $all('.question-card').forEach(card => {
      const qid = card.dataset.qid;
      const qObj = questions.find(x => x.id === qid);
      if (!qObj) return;

      // Preserve form data
      qObj.title = card.querySelector('.q-title')?.value || '';
      qObj.points = Number(card.querySelector('.q-points')?.value) || 0;
      qObj.type = card.querySelector('.q-type')?.value || qObj.type;

      // Preserve TinyMCE content
      const ed = (window.tinymce && tinymce.get(`editor-${qid}`)) || null;
      if (ed) {
        qObj.text = ed.getContent({format: 'html'});
      } else {
        qObj.text = card.querySelector('.q-editor')?.value || '';
      }

      // Preserve type-specific data
      if (qObj.type === 'mcq') {
        qObj.options = [];
        card.querySelectorAll('.mcq-row').forEach(row => {
          const txt = row.querySelector('.opt-text')?.value || '';
          const correct = !!row.querySelector('.opt-correct')?.checked;
          qObj.options.push({ text: txt, is_correct: correct });
        });
        qObj.allow_multiple = !!card.querySelector('.allow-multiple')?.checked;
      } else if (qObj.type === 'fill') {
        qObj.blanks = [];
        card.querySelectorAll('.fill-answers .fill-ans').forEach((inp, idx) => {
          const raw = inp.value || '';
          const alts = raw.split(';').map(s => s.trim()).filter(Boolean);
          const caseSensitive = !!inp.parentElement.querySelector('.fill-case')?.checked;
          qObj.blanks.push({ index: idx, answers: alts, case_sensitive: caseSensitive });
        });
      } else if (qObj.type === 'formula') {
        qObj.variables = [];
        card.querySelectorAll('.var-row').forEach(v => {
          qObj.variables.push({
            name: v.querySelector('.var-name')?.value || '',
            min: parseFloat(v.querySelector('.var-min')?.value) || null,
            max: parseFloat(v.querySelector('.var-max')?.value) || null,
            step: parseFloat(v.querySelector('.var-step')?.value) || null,
            decimals: parseInt(v.querySelector('.var-dec')?.value) || 2,
            sample: v.querySelector('.var-sample')?.value || ''
          });
        });
        qObj.formula = {
          expression: card.querySelector('.formula-expression')?.value || '',
          decimals: parseInt(card.querySelector('.formula-decimals')?.value) || 2
        };
        qObj.sample_output = card.querySelector('.sample-output')?.textContent || '';
      }
    });
  }

  /*************************
   * Question card factory
   *************************/
  function createQuestionCard(q) {
    const container = document.createElement('div');
    container.className = 'question-card';
    container.dataset.qid = q.id;

    // q-head
    const head = document.createElement('div');
    head.className = 'q-head';
    head.innerHTML = `
      <div style="display:flex;gap:12px;align-items:center">
        <strong>Q${q.index}</strong>
        <div style="min-width:140px">
          <input type="text" class="q-title" placeholder="Question title / short label" value="${escapeHtml(q.title||'')}" style="width:100%;padding:6px;border:1px solid #e0e6ea;border-radius:6px">
        </div>
        <select class="q-type" style="padding:6px;border:1px solid #e0e6ea;border-radius:6px">
          <option value="mcq"${q.type==='mcq'?' selected':''}>MCQ</option>
          <option value="fill"${q.type==='fill'?' selected':''}>Fill</option>
          <option value="formula"${q.type==='formula'?' selected':''}>Formula</option>
        </select>
        <input type="number" class="q-points" value="${Number(q.points||0)}" min="0" style="width:80px;padding:6px;border:1px solid #e0e6ea;border-radius:6px"> <span>points</span>
        <select class="q-ilo" style="padding:6px;border:1px solid #e0e6ea;border-radius:6px">
          <option value="ILO1"${q.type==='ILO1'?' selected':''}>ILO 1</option>
          <option value="ILO1"${q.type==='ILO1'?' selected':''}>ILO 2</option>
        </select>
      </div>
      <div class="q-controls">
        <button type="button" class="small-btn btn-toggle">Toggle</button>
        <button type="button" class="small-btn btn-dup">Duplicate</button>
        <button type="button" class="small-btn btn-del">Delete</button>
      </div>
    `;
    container.appendChild(head);

    // q-body
    const body = document.createElement('div');
    body.className = 'q-body';
    body.innerHTML = `
      <div style="margin-bottom:8px">
        <textarea id="editor-${q.id}" class="q-editor">${escapeHtml(q.text||'')}</textarea>
      </div>

      <!-- MCQ -->
      <div class="section mcq-section ${q.type==='mcq'?'':'hidden'}">
        <label class="muted">Options</label>
        <div class="options-container"></div>
        <div style="margin-top:8px">
          <button type="button" class="small-btn add-option">+ Add option</button>
          <label style="margin-left:12px"><input type="checkbox" class="allow-multiple"> Allow multiple correct</label>
        </div>
      </div>

      <!-- Fill -->
      <div class="section fill-section ${q.type==='fill'?'':'hidden'}">
        <div class="muted">Use <code>{answer1;answer2}</code> inside the question text to mark blanks (separate alternatives with <code>;</code>).</div>
        <div style="margin-top:8px" class="fill-answers"></div>
      </div>

      <!-- Formula -->
      <div class="section formula-section ${q.type==='formula'?'':'hidden'}">
        <label class="muted">Variables (name, min, max, step, decimals)</label>
        <div style="margin-top:8px" class="variables-container"></div>
        <div style="margin-top:8px"><button type="button" class="small-btn add-var">+ Add variable</button></div>

        <hr style="margin:10px 0">

        <label class="muted">Formula expression (use variable names or <code>[var]</code> to auto-detect)</label>
        <div style="display:flex;gap:8px;margin-top:8px">
          <input class="formula-expression" placeholder="e.g. a + b * c" style="flex:1;padding:8px;border:1px solid #e0e6ea;border-radius:6px" value="${escapeHtml(q.formula?.expression||'')}" />
          <input type="number" class="formula-decimals" value="${q.formula?.decimals||2}" min="0" style="width:90px;padding:6px;border:1px solid #e0e6ea;border-radius:6px" />
        </div>

        <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
          <label class="muted">Samples:</label>
          <select class="sample-count">${Array.from({length:10},(_,i)=>`<option value="${i+1}">${i+1}</option>`).join('')}</select>
          <button type="button" class="small-btn gen-sample">Generate Samples</button>
        </div>

        <pre class="sample-output muted" style="margin-top:8px;background:#f8f9fa;padding:8px;border-radius:6px;white-space:pre-wrap">${escapeHtml(q.sample_output||'')}</pre>
      </div>
    `;
    container.appendChild(body);
    return container;
  }

  /*************************
   * FIXED: Clean editor removal
   *************************/
  function removeAllQuestionEditors() {
    if (!window.tinymce) return;
    try {
      (tinymce.editors || []).slice().forEach(ed => {
        if (ed && ed.id && ed.id.startsWith('editor-')) {
          ed.remove();
        }
      });
    } catch (err) { 
      console.warn('error removing editors', err); 
    }
  }

  /*************************
   * FIXED: Rendering with data preservation
   *************************/
  function renderQuestions() {
    // Preserve all current data before re-rendering
    preserveQuestionData();
    
    // Remove previous dynamic editors
    removeAllQuestionEditors();

    const list = $('#questionList');
    if (!list) return;
    list.innerHTML = '';

    questions.forEach((q, idx) => {
      q.index = idx + 1;
      const card = createQuestionCard(q);
      list.appendChild(card);

      // Setup TinyMCE for this question editor
      const editorId = `editor-${q.id}`;
      if (window.tinymce && typeof tinymce.init === 'function') {
        setTimeout(() => {
          tinymce.init({
            selector: `#${editorId}`,
            height: 160,
            menubar: false,
            plugins: ['link','lists','table','code','autoresize'],
            toolbar: 'undo redo | bold italic | bullist numlist | link | code',
            branding: false,
            setup: function(editor) {
              editor.on('Change KeyUp', () => {
                safe(() => {
                  q.text = editor.getContent({format: 'html'});
                  autoDetectVariables(q, editor.getContent({format:'text'}));
                  if (q.type === 'fill') updateFillAnswers(q.id);
                  updatePreviewDebounced();
                });
              });
            }
          });
        }, 100);
      }

      // Bind event handlers
      bindQuestionCardEvents(card, q);
      
      // Restore type-specific content
      restoreQuestionContent(card, q);
    });

    updatePreviewDebounced();
  }

  /*************************
   * Event binding helper
   *************************/
  function bindQuestionCardEvents(card, q) {
    // Delete button
    const btnDel = card.querySelector('.btn-del');
    btnDel && btnDel.addEventListener('click', () => {
      const i = questions.findIndex(x => x.id === q.id);
      if (i > -1) { 
        questions.splice(i, 1); 
        renderQuestions(); 
        buildPreview(); 
      }
    });

    // Duplicate button  
    const btnDup = card.querySelector('.btn-dup');
    btnDup && btnDup.addEventListener('click', () => duplicateQuestion(q.id));

    // Toggle button
    const btnToggle = card.querySelector('.btn-toggle');
    btnToggle && btnToggle.addEventListener('click', () => {
      const body = card.querySelector('.q-body');
      body && body.classList.toggle('hidden');
    });

    // Type change - FIXED: preserve content before changing
    const selType = card.querySelector('.q-type');
    selType && selType.addEventListener('change', ev => {
      // Preserve current content first
      preserveQuestionData();
      
      q.type = ev.target.value;
      card.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
      if (q.type === 'mcq') card.querySelector('.mcq-section').classList.remove('hidden');
      if (q.type === 'fill') card.querySelector('.fill-section').classList.remove('hidden');
      if (q.type === 'formula') card.querySelector('.formula-section').classList.remove('hidden');
      
      // Re-render to update editor and sections
      renderQuestions();
    });

    // Title and points - no re-render needed
    const titleInp = card.querySelector('.q-title');
    titleInp && titleInp.addEventListener('input', ev => { 
      q.title = ev.target.value; 
      updatePreviewDebounced(); 
    });

    const pointsInp = card.querySelector('.q-points');
    pointsInp && pointsInp.addEventListener('change', ev => { 
      q.points = Number(ev.target.value) || 0; 
      updatePreviewDebounced(); 
    });

    // MCQ: add-option
    const addOptBtn = card.querySelector('.add-option');
    addOptBtn && addOptBtn.addEventListener('click', () => addMCQOptionForCard(q.id));

    // Allow-multiple checkbox
    const allowMultipleCheckbox = card.querySelector('.allow-multiple');
    allowMultipleCheckbox && allowMultipleCheckbox.addEventListener('change', ev => {
      q.allow_multiple = ev.target.checked;
      convertMCQControls(q.id, ev.target.checked);
    });

    // Formula: add-var button
    const addVarBtn = card.querySelector('.add-var');
    addVarBtn && addVarBtn.addEventListener('click', () => addFormulaVariableForCard(q.id));

    // Generate samples
    const genSampleBtn = card.querySelector('.gen-sample');
    genSampleBtn && genSampleBtn.addEventListener('click', () => generateFormulaSamples(q.id));

    // Formula expression and decimals
    const formulaExpr = card.querySelector('.formula-expression');
    formulaExpr && formulaExpr.addEventListener('input', ev => {
      if (!q.formula) q.formula = {};
      q.formula.expression = ev.target.value;
      updatePreviewDebounced();
    });

    const formulaDec = card.querySelector('.formula-decimals');
    formulaDec && formulaDec.addEventListener('change', ev => {
      if (!q.formula) q.formula = {};
      q.formula.decimals = parseInt(ev.target.value) || 2;
      updatePreviewDebounced();
    });
  }

  /*************************
   * Restore question content
   *************************/
  function restoreQuestionContent(card, q) {
    // MCQ options
    if (q.type === 'mcq' && q.options && q.options.length > 0) {
      const container = card.querySelector('.options-container');
      container.innerHTML = ''; // Clear existing
      
      q.options.forEach(opt => {
        addMCQOptionForCard(q.id, opt.text, opt.is_correct);
      });
      
      // Set allow multiple
      const allowMultiple = card.querySelector('.allow-multiple');
      if (allowMultiple) {
        allowMultiple.checked = !!q.allow_multiple;
      }
    } else if (q.type === 'mcq') {
      // Add default options if none exist
      addMCQOptionForCard(q.id);
      addMCQOptionForCard(q.id);
    }

    // Fill blanks
    if (q.type === 'fill') {
      updateFillAnswers(q.id);
    }

    // Formula variables
    if (q.type === 'formula' && q.variables && q.variables.length > 0) {
      q.variables.forEach(v => {
        addFormulaVariableForCard(q.id, v);
      });
    }
  }

  /***********************
   * Add / Duplicate
   ***********************/
  function addQuestion(type = 'mcq') {
    questionCounter++;
    const id = 'q' + Date.now() + '-' + questionCounter;
    const q = { 
      id, 
      index: questions.length + 1, 
      type, 
      title: '', 
      text: '', 
      points: 1, 
      createdAt: Date.now(), 
      options: [], 
      allow_multiple: false,
      blanks: [],
      variables: [],
      formula: { expression: '', decimals: 2 },
      sample_output: ''
    };
    questions.push(q);
    renderQuestions();
    
    // Show questions tab
    const questionsPane = safeEl('questions');
    if (questionsPane) {
      $all('.tab-btn').forEach(b => b.classList.remove('active'));
      const btn = document.querySelector('.tab-btn[data-tab="questions"]');
      btn && btn.classList.add('active');
      $all('.main > .tab-pane').forEach(p => p.style.display = 'none');
      questionsPane.style.display = 'block';
    }
  }

  function duplicateQuestion(qid) {
    const q = questions.find(x => x.id === qid);
    if (!q) return;
    
    // Preserve current data before duplicating
    preserveQuestionData();
    
    questionCounter++;
    const copy = JSON.parse(JSON.stringify(q));
    copy.id = 'q' + Date.now() + '-' + questionCounter;
    copy.createdAt = Date.now();
    copy.index = questions.length + 1;
    
    questions.push(copy);
    renderQuestions();
  }

  /*************************
   * MCQ Helpers - FIXED
   *************************/
  function addMCQOptionForCard(qid, text = '', isCorrect = false) {
    const card = document.querySelector(`.question-card[data-qid="${qid}"]`);
    if (!card) return null;
    const container = card.querySelector('.options-container');
    if (!container) return null;

    const row = document.createElement('div');
    row.className = 'mcq-row';

    const isMultiple = !!card.querySelector('.allow-multiple')?.checked;
    const control = document.createElement('input');
    control.type = isMultiple ? 'checkbox' : 'radio';
    if (!isMultiple) control.name = `correct-${qid}`;
    control.className = 'opt-correct';
    control.checked = isCorrect;
    control.addEventListener('change', () => {
      updatePreviewDebounced();
      // Update question data immediately
      const q = questions.find(x => x.id === qid);
      if (q) preserveQuestionData();
    });

    const txt = document.createElement('input');
    txt.type = 'text';
    txt.className = 'opt-text';
    txt.placeholder = 'Option text';
    txt.value = text || '';
    txt.addEventListener('input', () => {
      updatePreviewDebounced();
      // Update question data immediately
      const q = questions.find(x => x.id === qid);
      if (q) preserveQuestionData();
    });

    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'remove-opt';
    del.innerText = '✕';
    del.addEventListener('click', () => { 
      row.remove(); 
      updatePreviewDebounced();
      // Update question data immediately
      const q = questions.find(x => x.id === qid);
      if (q) preserveQuestionData();
    });

    row.append(control, txt, del);
    container.appendChild(row);
    updatePreviewDebounced();
    return row;
  }

  function convertMCQControls(qid, isMultiple) {
    const card = document.querySelector(`.question-card[data-qid="${qid}"]`);
    if (!card) return;
    const rows = card.querySelectorAll('.mcq-row');
    rows.forEach(r => {
      const old = r.querySelector('.opt-correct');
      if (!old) return;
      const checked = old.checked;
      const newInp = document.createElement('input');
      newInp.className = 'opt-correct';
      newInp.type = isMultiple ? 'checkbox' : 'radio';
      if (!isMultiple) newInp.name = `correct-${qid}`;
      newInp.checked = checked;
      newInp.addEventListener('change', updatePreviewDebounced);
      old.replaceWith(newInp);
    });
    updatePreviewDebounced();
  }

  /*************************
   * Fill-in helpers
   *************************/
  function updateFillAnswers(qid) {
    const card = document.querySelector(`.question-card[data-qid="${qid}"]`);
    if (!card) return;
    
    // Get question text
    let rawText = '';
    if (window.tinymce && tinymce.get(`editor-${qid}`)) {
      rawText = tinymce.get(`editor-${qid}`).getContent({format: 'text'});
    } else {
      rawText = card.querySelector('.q-editor')?.value || '';
    }

    const matches = [...rawText.matchAll(/\{([^}]+)\}/g)];
    const container = card.querySelector('.fill-answers');
    if (!container) return;
    
    container.innerHTML = '';
    if (!matches.length) {
      container.innerHTML = '<div class="muted">No blanks detected in question text.</div>';
      updatePreviewDebounced();
      return;
    }

    const q = questions.find(x => x.id === qid);
    matches.forEach((m, i) => {
      const raw = m[1].trim();
      const alternatives = raw.split(';').map(s => s.trim()).filter(Boolean);
      const wrap = document.createElement('div');
      wrap.style.display = 'flex'; 
      wrap.style.gap = '8px'; 
      wrap.style.alignItems = 'center'; 
      wrap.style.marginTop = '6px';

      const label = document.createElement('div');
      label.className = 'muted';
      label.textContent = `Blank ${i+1}`;

      const input = document.createElement('input');
      input.type = 'text';
      input.value = alternatives.join(';');
      input.className = 'fill-ans';
      input.addEventListener('input', updatePreviewDebounced);

      const caseCheckLabel = document.createElement('label');
      caseCheckLabel.className = 'muted';
      caseCheckLabel.style.marginLeft = '8px';
      const caseCheck = document.createElement('input');
      caseCheck.type = 'checkbox';
      caseCheck.className = 'fill-case';
      
      // Restore case sensitivity if available
      if (q && q.blanks && q.blanks[i]) {
        caseCheck.checked = q.blanks[i].case_sensitive;
      }
      
      caseCheckLabel.append(caseCheck, ' Case sensitive');
      wrap.append(label, input, caseCheckLabel);
      container.appendChild(wrap);
    });

    updatePreviewDebounced();
  }

  /*************************
   * Formula helpers
   *************************/
  function addFormulaVariableForCard(qid, preset = {name:'',min:'',max:'',step:'',decimals:2,sample:''}) {
    const card = document.querySelector(`.question-card[data-qid="${qid}"]`);
    if (!card) return null;
    const container = card.querySelector('.variables-container');
    if (!container) return null;

    const row = document.createElement('div');
    row.className = 'var-row';
    row.style.display = 'flex';
    row.style.gap = '8px';
    row.style.alignItems = 'center';
    row.style.marginTop = '8px';

    row.innerHTML = `
      <input class="var-name" placeholder="name" style="width:90px;padding:6px;border:1px solid #e0e6ea;border-radius:6px" value="${escapeHtml(preset.name)}">
      <input class="var-min" placeholder="min" style="width:90px;padding:6px;border:1px solid #e0e6ea;border-radius:6px" value="${escapeHtml(preset.min)}">
      <input class="var-max" placeholder="max" style="width:90px;padding:6px;border:1px solid #e0e6ea;border-radius:6px" value="${escapeHtml(preset.max)}">
      <input class="var-step" placeholder="step" style="width:80px;padding:6px;border:1px solid #e0e6ea;border-radius:6px" value="${escapeHtml(preset.step)}">
      <input class="var-dec" placeholder="dec" type="number" style="width:70px;padding:6px;border:1px solid #e0e6ea;border-radius:6px" value="${escapeHtml(preset.decimals)}">
      <input class="var-sample" placeholder="sample" readonly style="width:110px;padding:6px;border:1px solid #e0e6ea;border-radius:6px" value="${escapeHtml(preset.sample)}">
      <button type="button" class="small-btn recompute">Recompute</button>
      <button type="button" class="small-btn remove-var">Remove</button>
    `;
    container.appendChild(row);

    row.querySelector('.recompute')?.addEventListener('click', ev => { 
      ev.stopPropagation(); 
      recomputeVarExample(row); 
    });
    
    row.querySelector('.remove-var')?.addEventListener('click', () => { 
      row.remove(); 
      updatePreviewDebounced(); 
    });

    ['.var-name', '.var-min', '.var-max', '.var-step', '.var-dec'].forEach(sel => {
      const el = row.querySelector(sel);
      el && el.addEventListener('input', () => {
        recomputeVarExample(row);
        updatePreviewDebounced();
      });
    });

    return row;
  }

  function recomputeVarExample(row) {
    const min = parseFloat(row.querySelector('.var-min').value);
    const max = parseFloat(row.querySelector('.var-max').value);
    const dec = parseInt(row.querySelector('.var-dec').value) || 2;
    const out = row.querySelector('.var-sample');
    if (isNaN(min) || isNaN(max) || min > max) { 
      out.value = 'invalid'; 
      return; 
    }
    const mid = (min + max) / 2;
    out.value = Number(mid).toFixed(dec);
    updatePreviewDebounced();
  }

  function autoDetectVariables(q, plainText) {
    if (!q || q.type !== 'formula') return;
    const matches = [...(plainText || '').matchAll(/\[([a-zA-Z_][a-zA-Z0-9_]*)\]/g)].map(m => m[1]);
    if (!matches.length) return;
    const card = document.querySelector(`.question-card[data-qid="${q.id}"]`);
    if (!card) return;
    const existing = $all('.var-name', card).map(i => i.value);
    matches.forEach(name => {
      if (!existing.includes(name)) {
        const row = addFormulaVariableForCard(q.id, { 
          name, 
          min: '1', 
          max: '10', 
          step: '1', 
          decimals: 2, 
          sample: '5' 
        });
        recomputeVarExample(row);
      }
    });
    updatePreviewDebounced();
  }

  function generateFormulaSamples(qid) {
    const card = document.querySelector(`.question-card[data-qid="${qid}"]`);
    if (!card) return;
    const expr = (card.querySelector('.formula-expression')?.value || '').trim();
    const decimals = parseInt(card.querySelector('.formula-decimals')?.value) || 2;
    const sampleCount = parseInt(card.querySelector('.sample-count')?.value) || 3;
    const vars = [];
    
    card.querySelectorAll('.var-row').forEach(v => {
      const name = v.querySelector('.var-name').value.trim();
      const min = parseFloat(v.querySelector('.var-min').value);
      const max = parseFloat(v.querySelector('.var-max').value);
      const step = parseFloat(v.querySelector('.var-step').value) || 0;
      const dec = parseInt(v.querySelector('.var-dec').value) || 2;
      vars.push({ name, min, max, step, dec });
    });
    
    const out = card.querySelector('.sample-output');
    out.textContent = 'Generating...';
    
    if (!window.math || !math.evaluate) {
      out.textContent = 'math.js required to generate samples (include mathjs).';
      return;
    }
    
    try {
      const samples = [];
      for (let s = 0; s < sampleCount; s++) {
        const scope = {};
        vars.forEach(v => {
          if (isNaN(v.min) || isNaN(v.max) || v.min > v.max) {
            scope[v.name] = NaN;
          } else {
            if (v.step && v.step > 0) {
              const steps = Math.floor((v.max - v.min) / v.step) + 1;
              const val = v.min + ((s % steps) * v.step);
              scope[v.name] = Number(Number(val).toFixed(v.dec));
            } else {
              const val = v.min + ((v.max - v.min) * ((s + 1) / (sampleCount + 1)));
              scope[v.name] = Number(Number(val).toFixed(v.dec));
            }
          }
        });
        let exprUse = expr.replace(/\[([a-zA-Z_][a-zA-Z0-9_]*)\]/g, '$1');
        const val = math.evaluate(exprUse, scope);
        samples.push({ 
          scope, 
          result: (typeof val === 'number' ? Number(val.toFixed(decimals)) : val) 
        });
      }
      out.textContent = samples.map((s, i) => 
        `#${i+1} -> ${JSON.stringify(s.scope)} => ${s.result}`
      ).join('\n');
      
      // Update question data
      const q = questions.find(x => x.id === qid);
      if (q) {
        q.sample_output = out.textContent;
      }
    } catch (err) {
      out.textContent = 'Error: ' + (err.message || err);
    }
    updatePreviewDebounced();
  }

  /*************************
   * Preview / Serialization
   *************************/
  let previewTimer = null;
  function updatePreviewDebounced() { 
    clearTimeout(previewTimer); 
    previewTimer = setTimeout(buildPreview, 250); 
  }

  function buildPreview() {
    // Ensure all current data is preserved
    preserveQuestionData();
    
    const previewArea = $('#previewArea');
    if (!previewArea) return;
    previewArea.innerHTML = '';
    
    const title = $('#quiz_title')?.value || 'Untitled Quiz';
    const instr = (window.tinymce && tinymce.get('quiz_instructions')) ? 
      tinymce.get('quiz_instructions').getContent() : 
      ($('#quiz_instructions')?.value || '');
    
    previewArea.innerHTML += `<h3>${escapeHtml(title)}</h3><div class="muted">${instr}</div><hr style="margin:10px 0">`;

    if (!questions.length) {
      previewArea.innerHTML += `<div class="muted">No questions added.</div>`;
    } else {
      questions.forEach((q, idx) => {
        previewArea.innerHTML += `<div style="margin-bottom:12px;padding:10px;border:1px solid var(--border);border-radius:6px;background:#fff">
          <div style="display:flex;justify-content:space-between">
            <div><strong>Q${idx+1}</strong> ${escapeHtml(q.title||'')}</div>
            <div class="muted">${q.points || 0} pts • ${String(q.type||'').toUpperCase()}</div>
          </div>
          <div style="margin-top:8px">${q.text || ''}</div>
          ${renderPreviewType(q)}
        </div>`;
      });
    }

    // JSON output
    const jsonOut = $('#jsonOut');
    if (jsonOut) {
      jsonOut.textContent = JSON.stringify(
        questions.map(q => JSON.parse(JSON.stringify(q))), 
        null, 
        2
      );
    }
  }

  function renderPreviewType(q) {
    if (!q) return '';
    if (q.type === 'mcq') {
      const opts = (q.options || []).map((o, i) => 
        `<div style="margin-top:6px"><label><input type="${q.allow_multiple ? 'checkbox' : 'radio'}" name="p-${q.id}"> ${escapeHtml(o.text||'')}</label></div>`
      ).join('');
      return `<div style="margin-top:8px">${opts}</div>`;
    } else if (q.type === 'fill') {
      const blanks = (q.blanks || []).map((b,i) => 
        `<div style="margin-top:6px"><label>Blank ${i+1}: <input type="text" placeholder="${escapeHtml((b.answers||[])[0]||'')}" /></label></div>`
      ).join('');
      return `<div style="margin-top:8px">${blanks}</div>`;
    } else if (q.type === 'formula') {
      const formula = q.formula?.expression || '';
      const sampleOut = q.sample_output ? 
        `<pre style="background:#f6f9fb;padding:8px;border-radius:6px">${escapeHtml(q.sample_output)}</pre>` : 
        '';
      return `<div style="margin-top:8px"><div class="muted">Formula: ${escapeHtml(formula)}</div>${sampleOut}</div>`;
    }
    return '';
  }

  /*************************
   * Copy / Download JSON
   *************************/
  safe(() => $('#copyJSON')?.addEventListener('click', () => {
    const jsonText = $('#jsonOut')?.textContent;
    if (jsonText) {
      navigator.clipboard?.writeText(jsonText).then(() => 
        alert('JSON copied to clipboard')
      );
    }
  }));
  
  safe(() => $('#downloadJSON')?.addEventListener('click', () => {
    const data = $('#jsonOut')?.textContent;
    if (data) {
      const blob = new Blob([data], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url; 
      a.download = 'questions.json'; 
      a.click();
      URL.revokeObjectURL(url);
    }
  }));

  /*************************
   * Clear / Save demo
   *************************/
  safe(() => $('#clear_all')?.addEventListener('click', () => {
    if (!confirm('Clear all questions and settings?')) return;
    questions.length = 0; 
    questionCounter = 0;
    $('#quiz_title') && ($('#quiz_title').value = '');
    if (window.tinymce && tinymce.get('quiz_instructions')) {
      tinymce.get('quiz_instructions').setContent('');
    }
    renderQuestions(); 
    buildPreview();
  }));

  safe(() => $('#save_quiz')?.addEventListener('click', () => {
    buildPreview();
    alert('Demo: quiz JSON ready (see Preview → JSON). Implement backend POST to actually save.');
  }));

  /*************************
   * Wire add-question control
   *************************/
  const addBtn = $('#add_question');
  if (addBtn) {
    addBtn.addEventListener('click', (ev) => {
      ev.preventDefault();
      const typeSel = $('#new_q_type') || { value: 'mcq' };
      addQuestion(typeSel.value);
    });
  }

  /*************************
   * Initial render
   *************************/
  renderQuestions();
  buildPreview();

  // Expose for debugging
  window._quizBuilder = { 
    questions, 
    addQuestion, 
    renderQuestions, 
    buildPreview, 
    preserveQuestionData 
  };
})();

</script>
</body>

</html>