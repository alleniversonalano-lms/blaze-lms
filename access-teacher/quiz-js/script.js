/* =============================
   Quiz Builder with Auto-Save and TinyMCE
   ============================= */

(() => {
    // --- State ---
    let questionCounter = 0;
    const questions = [];

    // --- Helpers ---
    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $all = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
    const safeEl = id => document.getElementById(id);
    const escapeHtml = s => String(s || '').replace(/[&<>\"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    const safe = fn => { try { return fn(); } catch (err) { console.error(err); } };

    // --- Auto-save TinyMCE content ---
    function autoSaveAllTinyMCE() {
        if (!window.tinymce) return;
        const instructionsEditor = tinymce.get('quiz_instructions');
        if (instructionsEditor) instructionsEditor.save();
        questions.forEach(q => {
            const editor = tinymce.get(`editor-${q.id}`);
            if (editor) {
                q.text = editor.getContent({ format: 'html' });
                editor.save();
            }
        });
    }

    // --- Clean HTML ---
    function cleanHtml(html) {
        if (!html) return '';
        const temp = document.createElement('div');
        temp.innerHTML = html;
        temp.querySelectorAll('script, style').forEach(el => el.remove());
        temp.querySelectorAll('*').forEach(el => {
            ['onload', 'onerror', 'onclick', 'onmouseover', 'onfocus', 'onblur', 'onchange', 'onsubmit', 'onreset', 'onselect', 'onabort'].forEach(attr => el.removeAttribute(attr));
            if (el.hasAttribute('href') && el.getAttribute('href').toLowerCase().startsWith('javascript:')) el.removeAttribute('href');
            if (el.hasAttribute('src') && el.getAttribute('src').toLowerCase().startsWith('javascript:')) el.removeAttribute('src');
        });
        return temp.innerHTML;
    }

    // --- TinyMCE for instructions ---
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.tinymce.get('quiz_instructions')) {
            tinymce.init({
                selector: '#quiz_instructions',
                height: 200,
                menubar: 'edit insert format tools table',
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'help', 'wordcount', 'autosave', 'autoresize'
                ],
                toolbar: [
                    'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor |',
                    'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent |',
                    'link image media table | removeformat | code fullscreen'
                ].join(' '),
                branding: false,
                statusbar: true,
                resize: true,
                autoresize_bottom_margin: 20,
                paste_data_images: false,
                entity_encoding: 'raw',
                forced_root_block: 'p',
                convert_urls: false,
                browser_spellcheck: true,
                contextmenu: false,
                setup: function (editor) {
                    let timer;
                    editor.on('Change KeyUp Paste Undo Redo', () => {
                        safe(() => {
                            autoSaveAllTinyMCE();
                            updatePreviewDebounced();
                        });
                    });
                    editor.on('blur', () => {
                        safe(() => {
                            autoSaveAllTinyMCE();
                            updatePreviewDebounced();
                        });
                    });
                },
                init_instance_callback: function (editor) {
                    editor.on('focus', function () {
                        editor.getContainer().style.borderColor = '#2196F3';
                    });
                    editor.on('blur', function () {
                        editor.getContainer().style.borderColor = '#ddd';
                    });
                }
            });
        }
    });

    // --- Tab handling ---
    $all('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            autoSaveAllTinyMCE();
            $all('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const tab = btn.dataset.tab;
            $all('.main > .tab-pane').forEach(p => p.style.display = 'none');
            const target = safeEl(tab);
            if (target) target.style.display = 'block';
            if (tab === 'preview') buildPreview();
        });
    });

    // --- General toggles ---
    safe(() => {
        $('#time_limit_enable')?.addEventListener('change', function () {
            $('#time_limit').disabled = !this.checked;
            autoSaveAllTinyMCE();
            updatePreviewDebounced();
        });
        $('#multi_attempts_enable')?.addEventListener('change', function () {
            const en = this.checked;
            $('#allowed_attempts').disabled = !en;
            $('#score_to_keep').disabled = !en;
            autoSaveAllTinyMCE();
            updatePreviewDebounced();
        });
    });

    // --- Preserve question data ---
    function preserveQuestionData() {
        autoSaveAllTinyMCE();
        $all('.question-card').forEach(card => {
            const qid = card.dataset.qid;
            const qObj = questions.find(x => x.id === qid);
            if (!qObj) return;
            qObj.title = card.querySelector('.q-title')?.value || '';
            qObj.points = Number(card.querySelector('.q-points')?.value) || 0;
            qObj.type = card.querySelector('.q-type')?.value || qObj.type;
            const ed = tinymce.get(`editor-${qid}`) || null;
            qObj.text = ed ? cleanHtml(ed.getContent({ format: 'html' })) : cleanHtml(card.querySelector('.q-editor')?.value || '');
            // MCQ, fill, formula data preserved as before...
        });
    }

    // --- Remove all question editors ---
    function removeAllQuestionEditors() {
        if (!window.tinymce) return;
        try {
            (tinymce.editors || []).slice().forEach(ed => {
                if (ed && ed.id && ed.id.startsWith('editor-')) ed.remove();
            });
        } catch (err) { console.warn('error removing editors', err); }
    }


    // --- Render questions ---
    function renderQuestions() {
        // Save focused editor id before re-rendering
        let lastFocusedEditorId = null;
        if (window.tinymce && tinymce.activeEditor) {
            lastFocusedEditorId = tinymce.activeEditor.id;
        }

        preserveQuestionData();
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
                        height: 200,
                        menubar: 'edit insert format tools table',
                        plugins: [
                            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                            'insertdatetime', 'media', 'table', 'help', 'wordcount', 'autosave', 'autoresize'
                        ],
                        toolbar: [
                            'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor |',
                            'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent |',
                            'link image media table | removeformat | code fullscreen'
                        ].join(' '),
                        branding: false,
                        statusbar: true,
                        resize: true,
                        autoresize_bottom_margin: 20,
                        paste_data_images: false,
                        entity_encoding: 'raw',
                        forced_root_block: 'p',
                        convert_urls: false,
                        browser_spellcheck: true,
                        contextmenu: false,
                        setup: function (editor) {
                            editor.on('Change KeyUp Paste Undo Redo', () => {
                                safe(() => {
                                    const content = cleanHtml(editor.getContent({ format: 'html' }));
                                    q.text = content;
                                    editor.save();
                                    autoDetectVariables(q, editor.getContent({ format: 'text' }));
                                    if (q.type === 'fill') updateFillAnswers(q.id);
                                    updatePreviewDebounced();
                                });
                            });
                            editor.on('blur', () => {
                                safe(() => {
                                    autoSaveAllTinyMCE();
                                    updatePreviewDebounced();
                                });
                            });
                        },
                        init_instance_callback: function (editor) {
                            editor.on('focus', function () {
                                editor.getContainer().style.borderColor = '#2196F3';
                            });
                            editor.on('blur', function () {
                                editor.getContainer().style.borderColor = '#ddd';
                            });
                        }
                    });
                }, 100);
            }

            bindQuestionCardEvents(card, q);
            restoreQuestionContent(card, q);
        });

        // Restore focus to the previously focused editor
        setTimeout(() => {
            if (lastFocusedEditorId && window.tinymce.get(lastFocusedEditorId)) {
                window.tinymce.get(lastFocusedEditorId).focus();
            }
        }, 400);

        updatePreviewDebounced();
    }
    /*************************
     * Question card factory
     *************************/

    let ILO_LIST = [];

    function fetchILOs(courseId) {
        return fetch(`quiz-js/fetch-ilo.php?course_id=${courseId}`)
            .then(res => res.json())
            .then(data => {
                ILO_LIST = data;
            });
    }

    // Call this once on page load
    fetchILOs(window.COURSE_ID);

    function createQuestionCard(q) {
        const container = document.createElement('div');
        container.className = 'question-card';
        container.dataset.qid = q.id;

        // Dynamic ILO select
        const iloOptions = (ILO_LIST.length ? ILO_LIST : [{ id: 1, name: 'ILO 1' }])
            .map((ilo, i) =>
                `<option value="ILO${i + 1}"${q.ilo === `ILO${i + 1}` ? ' selected' : ''}>ILO ${escapeHtml(ilo.name)}</option>`
            ).join('');
        const iloSelect = `<select class="q-ilo" style="padding:6px;border:1px solid #e0e6ea;border-radius:6px">${iloOptions}</select>`;

        // q-head
        const head = document.createElement('div');
        head.className = 'q-head';
        head.innerHTML = `
      <div style="display:flex;gap:12px;align-items:center">
        <strong>Q${q.index}</strong>
        <div style="min-width:140px">
          <input type="text" class="q-title" placeholder="Question title / short label" value="${escapeHtml(q.title || '')}" style="width:100%;padding:6px;border:1px solid #e0e6ea;border-radius:6px">
        </div>
        <select class="q-type" style="padding:6px;border:1px solid #e0e6ea;border-radius:6px">
          <option value="mcq"${q.type === 'mcq' ? ' selected' : ''}>MCQ</option>
          <option value="fill"${q.type === 'fill' ? ' selected' : ''}>Fill</option>
          <option value="formula"${q.type === 'formula' ? ' selected' : ''}>Formula</option>
        </select>
        <input type="number" class="q-points" value="${Number(q.points || 0)}" min="0" style="width:80px;padding:6px;border:1px solid #e0e6ea;border-radius:6px"> <span>points</span>
        <div style="
    display: flex;
    gap: 8px;
    align-items: center;
    min-width: 120px;
    padding: 0 4px;
">
    <label style="
        margin: 0;
        font-weight: 500;
        color: #555;
        font-size: 14px;
        white-space: nowrap;
    ">ILO:</label>
    ${iloSelect}
</div>
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
        <textarea id="editor-${q.id}" class="q-editor">${escapeHtml(q.text || '')}</textarea>
      </div>

      <!-- MCQ -->
      <div class="section mcq-section ${q.type === 'mcq' ? '' : 'hidden'}">
        <label class="muted">Options</label>
        <div class="options-container"></div>
        <div style="margin-top:8px">
          <button type="button" class="small-btn add-option">+ Add option</button>
          <label style="margin-left:12px"><input type="checkbox" class="allow-multiple"> Allow multiple correct</label>
        </div>
      </div>

      <!-- Fill -->
      <div class="section fill-section ${q.type === 'fill' ? '' : 'hidden'}">
        <div class="muted">Use <code>{answer1;answer2}</code> inside the question text to mark blanks (separate alternatives with <code>;</code>).</div>
        <div style="margin-top:8px" class="fill-answers"></div>
      </div>

      <!-- Formula -->
      <div class="section formula-section ${q.type === 'formula' ? '' : 'hidden'}">
        <label class="muted">Variables (name, min, max, step, decimals)</label>
        <div style="margin-top:8px" class="variables-container"></div>
        <div style="margin-top:8px"><button type="button" class="small-btn add-var">+ Add variable</button></div>

        <hr style="margin:10px 0">

        <label class="muted">Formula expression (use variable names or <code>[var]</code> to auto-detect)</label>
        <div style="display:flex;gap:8px;margin-top:8px">
          <input class="formula-expression" placeholder="e.g. a + b * c" style="flex:1;padding:8px;border:1px solid #e0e6ea;border-radius:6px" value="${escapeHtml(q.formula?.expression || '')}" />
          <input type="number" class="formula-decimals" value="${q.formula?.decimals || 2}" min="0" style="width:90px;padding:6px;border:1px solid #e0e6ea;border-radius:6px" />
        </div>

        <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
          <label class="muted">Samples:</label>
          <select class="sample-count">${Array.from({ length: 10 }, (_, i) => `<option value="${i + 1}">${i + 1}</option>`).join('')}</select>
          <button type="button" class="small-btn gen-sample">Generate Samples</button>
        </div>

        <pre class="sample-output muted" style="margin-top:8px;background:#f8f9fa;padding:8px;border-radius:6px;white-space:pre-wrap">${escapeHtml(q.sample_output || '')}</pre>
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
        preserveQuestionData();
        removeAllQuestionEditors();

        const list = $('#questionList');
        if (!list) return;
        list.innerHTML = '';

        questions.forEach((q, idx) => {
            q.index = idx + 1;
            const card = createQuestionCard(q);
            list.appendChild(card);

            // Setup TinyMCE for this question editor with auto-save
            const editorId = `editor-${q.id}`;
            setTimeout(() => {
                const textarea = document.getElementById(editorId);
                if (textarea && window.tinymce && typeof tinymce.init === 'function') {
                    tinymce.init({
                        selector: `#${editorId}`,
                        height: 160,
                        menubar: 'edit insert format tools table',
                        plugins: [
                            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                            'insertdatetime', 'media', 'table', 'help', 'wordcount', 'autosave', 'autoresize'
                        ],
                        toolbar: [
                            'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor |',
                            'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent |',
                            'link image media table | removeformat | code fullscreen'
                        ].join(' '),
                        branding: false,
                        statusbar: true,
                        resize: true,
                        autoresize_bottom_margin: 20,
                        paste_data_images: false,
                        entity_encoding: 'raw',
                        forced_root_block: 'p',
                        convert_urls: false,
                        browser_spellcheck: true,
                        contextmenu: false,
                        auto_focus: false,
                        setup: function (editor) {
                            editor.on('Change KeyUp Paste Undo Redo', () => {
                                safe(() => {
                                    const content = cleanHtml(editor.getContent({ format: 'html' }));
                                    q.text = content;
                                    editor.save();
                                    autoDetectVariables(q, editor.getContent({ format: 'text' }));
                                    if (q.type === 'fill') updateFillAnswers(q.id);
                                    updatePreviewDebounced();
                                });
                            });
                            editor.on('blur', () => {
                                safe(() => {
                                    autoSaveAllTinyMCE();
                                    updatePreviewDebounced();
                                });
                            });
                        }
                    });
                }
            }, 300); // Increased delay for DOM readiness

            bindQuestionCardEvents(card, q);
            restoreQuestionContent(card, q);
        });

        updatePreviewDebounced();
    }

    /*************************
     * Event binding helper with auto-save
     *************************/
    function bindQuestionCardEvents(card, q) {
        // Delete button
        const btnDel = card.querySelector('.btn-del');
        btnDel && btnDel.addEventListener('click', () => {
            autoSaveAllTinyMCE(); // Auto-save before deleting
            const i = questions.findIndex(x => x.id === q.id);
            if (i > -1) {
                questions.splice(i, 1);
                renderQuestions();
                buildPreview();
            }
        });

        // Duplicate button  
        const btnDup = card.querySelector('.btn-dup');
        btnDup && btnDup.addEventListener('click', () => {
            autoSaveAllTinyMCE(); // Auto-save before duplicating
            duplicateQuestion(q.id);
        });

        // Toggle button
        const btnToggle = card.querySelector('.btn-toggle');
        btnToggle && btnToggle.addEventListener('click', () => {
            autoSaveAllTinyMCE(); // Auto-save before toggling
            const body = card.querySelector('.q-body');
            body && body.classList.toggle('hidden');
        });

        // Type change - FIXED: preserve content before changing
        const selType = card.querySelector('.q-type');
        selType && selType.addEventListener('change', ev => {
            // Auto-save and preserve current content first
            autoSaveAllTinyMCE();
            preserveQuestionData();

            q.type = ev.target.value;
            card.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            if (q.type === 'mcq') card.querySelector('.mcq-section').classList.remove('hidden');
            if (q.type === 'fill') card.querySelector('.fill-section').classList.remove('hidden');
            if (q.type === 'formula') card.querySelector('.formula-section').classList.remove('hidden');

            // Re-render to update editor and sections
            renderQuestions();
        });

        // Title and points - with auto-save
        const titleInp = card.querySelector('.q-title');
        titleInp && titleInp.addEventListener('input', ev => {
            q.title = ev.target.value;
            autoSaveAllTinyMCE();
            updatePreviewDebounced();
        });

        const pointsInp = card.querySelector('.q-points');
        pointsInp && pointsInp.addEventListener('change', ev => {
            q.points = Number(ev.target.value) || 0;
            autoSaveAllTinyMCE();
            updatePreviewDebounced();
        });

        // MCQ: add-option
        const addOptBtn = card.querySelector('.add-option');
        addOptBtn && addOptBtn.addEventListener('click', () => {
            autoSaveAllTinyMCE();
            addMCQOptionForCard(q.id);
        });

        // Allow-multiple checkbox
        const allowMultipleCheckbox = card.querySelector('.allow-multiple');
        allowMultipleCheckbox && allowMultipleCheckbox.addEventListener('change', ev => {
            q.allow_multiple = ev.target.checked;
            autoSaveAllTinyMCE();
            convertMCQControls(q.id, ev.target.checked);
        });

        // Formula: add-var button
        const addVarBtn = card.querySelector('.add-var');
        addVarBtn && addVarBtn.addEventListener('click', () => {
            autoSaveAllTinyMCE();
            addFormulaVariableForCard(q.id);
        });

        // Generate samples
        const genSampleBtn = card.querySelector('.gen-sample');
        genSampleBtn && genSampleBtn.addEventListener('click', () => {
            autoSaveAllTinyMCE();
            generateFormulaSamples(q.id);
        });

        // Formula expression and decimals
        const formulaExpr = card.querySelector('.formula-expression');
        formulaExpr && formulaExpr.addEventListener('input', ev => {
            if (!q.formula) q.formula = {};
            q.formula.expression = ev.target.value;
            autoSaveAllTinyMCE();
            updatePreviewDebounced();
        });

        const formulaDec = card.querySelector('.formula-decimals');
        formulaDec && formulaDec.addEventListener('change', ev => {
            if (!q.formula) q.formula = {};
            q.formula.decimals = parseInt(ev.target.value) || 2;
            autoSaveAllTinyMCE();
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
     * Add / Duplicate with auto-save
     ***********************/
    function addQuestion(type = 'mcq') {
        autoSaveAllTinyMCE(); // Auto-save before adding

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

        // --- Ensure TinyMCE is initialized for the new question ---
        setTimeout(() => {
            if (window.tinymce && typeof tinymce.init === 'function') {
                tinymce.init({
                    selector: `#editor-${id}`,
                    height: 200,
                    menubar: 'edit insert format tools table',
                    plugins: [
                        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                        'insertdatetime', 'media', 'table', 'help', 'wordcount', 'autosave', 'autoresize'
                    ],
                    toolbar: [
                        'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor |',
                        'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent |',
                        'link image media table | removeformat | code fullscreen'
                    ].join(' '),
                    branding: false,
                    statusbar: true,
                    resize: true,
                    autoresize_bottom_margin: 20,
                    paste_data_images: false,
                    entity_encoding: 'raw',
                    forced_root_block: 'p',
                    convert_urls: false,
                    browser_spellcheck: true,
                    contextmenu: false,
                    setup: function (editor) {
                        editor.on('Change KeyUp Paste Undo Redo', () => {
                            safe(() => {
                                const content = cleanHtml(editor.getContent({ format: 'html' }));
                                q.text = content;
                                editor.save();
                                autoDetectVariables(q, editor.getContent({ format: 'text' }));
                                if (q.type === 'fill') updateFillAnswers(q.id);
                                updatePreviewDebounced();
                            });
                        });
                        editor.on('blur', () => {
                            safe(() => {
                                autoSaveAllTinyMCE();
                                updatePreviewDebounced();
                            });
                        });
                    },
                    init_instance_callback: function (editor) {
                        editor.on('focus', function () {
                            editor.getContainer().style.borderColor = '#2196F3';
                        });
                        editor.on('blur', function () {
                            editor.getContainer().style.borderColor = '#ddd';
                        });
                    }
                });
            }
        }, 150); // Slight delay to ensure DOM is ready
    }

    function duplicateQuestion(qid) {
        const q = questions.find(x => x.id === qid);
        if (!q) return;

        // Auto-save and preserve current data before duplicating
        autoSaveAllTinyMCE();
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
     * MCQ Helpers - FIXED with auto-save
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
            autoSaveAllTinyMCE();
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
            autoSaveAllTinyMCE();
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
            autoSaveAllTinyMCE();
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
            newInp.addEventListener('change', () => {
                autoSaveAllTinyMCE();
                updatePreviewDebounced();
            });
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
            rawText = tinymce.get(`editor-${qid}`).getContent({ format: 'text' });
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
            label.textContent = `Blank ${i + 1}`;

            const input = document.createElement('input');
            input.type = 'text';
            input.value = alternatives.join(';');
            input.className = 'fill-ans';
            input.addEventListener('input', () => {
                autoSaveAllTinyMCE();
                updatePreviewDebounced();
            });

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

            caseCheck.addEventListener('change', () => {
                autoSaveAllTinyMCE();
                updatePreviewDebounced();
            });

            caseCheckLabel.append(caseCheck, ' Case sensitive');
            wrap.append(label, input, caseCheckLabel);
            container.appendChild(wrap);
        });

        updatePreviewDebounced();
    }

    /*************************
     * Formula helpers with auto-save
     *************************/
    function addFormulaVariableForCard(qid, preset = { name: '', min: '', max: '', step: '', decimals: 2, sample: '' }) {
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
            autoSaveAllTinyMCE();
            recomputeVarExample(row);
        });

        row.querySelector('.remove-var')?.addEventListener('click', () => {
            autoSaveAllTinyMCE();
            row.remove();
            updatePreviewDebounced();
        });

        ['.var-name', '.var-min', '.var-max', '.var-step', '.var-dec'].forEach(sel => {
            const el = row.querySelector(sel);
            el && el.addEventListener('input', () => {
                autoSaveAllTinyMCE();
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
        autoSaveAllTinyMCE(); // Auto-save before generating samples

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
                `#${i + 1} -> ${JSON.stringify(s.scope)} => ${s.result}`
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
     * Preview / Serialization with auto-save
     *************************/
    let previewTimer = null;
    function updatePreviewDebounced() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(buildPreview, 250);
    }

    function buildPreview() {
        // Ensure all current data is preserved and auto-saved
        autoSaveAllTinyMCE();
        preserveQuestionData();

        const previewArea = $('#previewArea');
        if (!previewArea) return;
        previewArea.innerHTML = '';

        const title = $('#quiz_title')?.value || 'Untitled Quiz';
        const instr = (window.tinymce && tinymce.get('quiz_instructions')) ?
            cleanHtml(tinymce.get('quiz_instructions').getContent()) :
            cleanHtml($('#quiz_instructions')?.value || '');

        previewArea.innerHTML += `<h3>${escapeHtml(title)}</h3><div class="muted">${instr}</div><hr style="margin:10px 0">`;

        if (!questions.length) {
            previewArea.innerHTML += `<div class="muted">No questions added.</div>`;
        } else {
            questions.forEach((q, idx) => {
                previewArea.innerHTML += `<div style="margin-bottom:12px;padding:10px;border:1px solid var(--border);border-radius:6px;background:#fff">
          <div style="display:flex;justify-content:space-between">
            <div><strong>Q${idx + 1}</strong> ${escapeHtml(q.title || '')}</div>
            <div class="muted">${q.points || 0} pts • ${String(q.type || '').toUpperCase()}</div>
          </div>
          <div style="margin-top:8px">${cleanHtml(q.text || '')}</div>
          ${renderPreviewType(q)}
        </div>`;
            });
        }

        // JSON output

    }

    function renderPreviewType(q) {
        if (!q) return '';
        if (q.type === 'mcq') {
            const opts = (q.options || []).map((o, i) =>
                `<div style="margin-top:6px"><label><input type="${q.allow_multiple ? 'checkbox' : 'radio'}" name="p-${q.id}"> ${escapeHtml(o.text || '')}</label></div>`
            ).join('');
            return `<div style="margin-top:8px">${opts}</div>`;
        } else if (q.type === 'fill') {
            const blanks = (q.blanks || []).map((b, i) =>
                `<div style="margin-top:6px"><label>Blank ${i + 1}: <input type="text" placeholder="${escapeHtml((b.answers || [])[0] || '')}" /></label></div>`
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

    // --- Clear All ---
    safe(() => $('#clear_all')?.addEventListener('click', () => {
        if (!confirm('Clear all questions and settings?')) return;
        autoSaveAllTinyMCE();
        questions.length = 0;
        questionCounter = 0;
        $('#quiz_title') && ($('#quiz_title').value = '');
        if (window.tinymce && tinymce.get('quiz_instructions')) {
            tinymce.get('quiz_instructions').setContent('');
        }
        renderQuestions();
        buildPreview();
    }));


    // --- Save Quiz ---
    document.addEventListener('DOMContentLoaded', () => {
        const saveBtn = $('#save_quiz');
        if (saveBtn) {
            saveBtn.replaceWith(saveBtn.cloneNode(true));
            $('#save_quiz').addEventListener('click', async () => {
                const originalText = saveBtn.textContent || 'Save Quiz';
                try {
                    saveBtn.textContent = 'Saving...';
                    saveBtn.disabled = true;
                    autoSaveAllTinyMCE();
                    preserveQuestionData();
                    const quizData = {
                        title: $('#quiz_title')?.value || 'Untitled Quiz',
                        instructions: window.tinymce?.get('quiz_instructions')?.getContent() || '',
                        timeLimit: $('#time_limit_enable')?.checked ? Number($('#time_limit')?.value) || 30 : null,
                        allowedAttempts: $('#multi_attempts_enable')?.checked ? Number($('#allowed_attempts')?.value) || 1 : 1,
                        scoreToKeep: $('#score_to_keep')?.value || 'highest',
                        questions: questions.map(q => {
                            const clean = JSON.parse(JSON.stringify(q));
                            clean.text = cleanHtml(clean.text);
                            return clean;
                        })
                    };
                    const response = await fetch('quiz-js/save_quiz', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-cache' },
                        body: JSON.stringify(quizData)
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.error || 'Failed to save quiz');
                    window.location.href = 'assessments';
                } catch (err) {
                    console.error('Error saving quiz:', err);
                    alert('Error saving quiz: ' + (err.message || 'Please try again'));
                } finally {
                    saveBtn.textContent = originalText;
                    saveBtn.disabled = false;
                }
            });
        }
    });

    /*************************
     * Wire add-question control with auto-save
     *************************/
    const addBtn = $('#add_question');
    if (addBtn) {
        addBtn.addEventListener('click', (ev) => {
            ev.preventDefault();
            autoSaveAllTinyMCE(); // Auto-save before adding
            const typeSel = $('#new_q_type') || { value: 'mcq' };
            addQuestion(typeSel.value);
        });
    }

    // --- Global auto-save ---
    window.addEventListener('beforeunload', autoSaveAllTinyMCE);
    window.addEventListener('blur', autoSaveAllTinyMCE);
    setInterval(autoSaveAllTinyMCE, 30000);

    // --- Initial render ---
    renderQuestions();
    buildPreview();

    // Expose for debugging
    window._quizBuilder = {
        questions,
        addQuestion,
        renderQuestions,
        buildPreview,
        preserveQuestionData,
        autoSaveAllTinyMCE,
        cleanHtml
    };
    
})();