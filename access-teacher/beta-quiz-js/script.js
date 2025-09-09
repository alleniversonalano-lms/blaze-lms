/* ==========================================
   Canvas-style Quiz Builder
   ========================================== */

(() => {
    // --- State ---
    let questionCounter = 0;
    const questions = [];
    let ILO_LIST = [];

    // --- Helpers ---
    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const escapeHtml = s => String(s || '').replace(/[&<>\"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));

    // --- Fetch ILOs ---
    async function fetchILOs(courseId) {
        const res = await fetch(`quiz-js/fetch-ilo.php?course_id=${courseId}`);
        ILO_LIST = await res.json();
    }

    // --- TinyMCE Editor ---
    function initEditor(selector, onChange) {
        tinymce.init({
            selector,
            height: 180,
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
                    if (onChange) onChange(editor.getContent({ format: 'html' }));
                });
            }
        });
    }

    // --- Question Card ---
    function createQuestionCard(q) {
        const iloOptions = (ILO_LIST.length ? ILO_LIST : [{ id: 1, name: 'ILO 1' }])
            .map((ilo, i) =>
                `<option value="ILO${i + 1}"${q.ilo === `ILO${i + 1}` ? ' selected' : ''}>${escapeHtml(ilo.name)}</option>`
            ).join('');
        const card = document.createElement('div');
        card.className = 'question-card';
        card.dataset.qid = q.id;
        card.innerHTML = `
            <div class="card-header" style="display:flex;align-items:center;gap:16px;">
                <span class="q-number">Q${q.index}</span>
                <input type="text" class="q-title" placeholder="Question title" value="${escapeHtml(q.title || '')}" style="flex:1;padding:6px;border-radius:4px;border:1px solid #e0e6ea;">
                <select class="q-type" style="padding:6px;border-radius:4px;border:1px solid #e0e6ea;">
                    <option value="mcq"${q.type === 'mcq' ? ' selected' : ''}>Multiple Choice</option>
                    <option value="fill"${q.type === 'fill' ? ' selected' : ''}>Fill in the Blank</option>
                    <option value="formula"${q.type === 'formula' ? ' selected' : ''}>Formula</option>
                </select>
                <input type="number" class="q-points" value="${Number(q.points || 1)}" min="0" style="width:70px;padding:6px;border-radius:4px;border:1px solid #e0e6ea;">
                <span>pts</span>
                <label style="margin:0 8px 0 0;font-weight:500;color:#555;font-size:14px;">ILO:</label>
                <select class="q-ilo" style="padding:6px;border-radius:4px;border:1px solid #e0e6ea;">${iloOptions}</select>
                <button type="button" class="btn-del" title="Delete" style="margin-left:auto;">🗑️</button>
            </div>
            <div class="card-body">
                <textarea id="editor-${q.id}" class="q-editor">${escapeHtml(q.text || '')}</textarea>
            </div>
        `;
        // Initialize TinyMCE for this question
        setTimeout(() => {
            initEditor(`#editor-${q.id}`, html => {
                q.text = html;
            });
        }, 100);
        // Bind delete
        card.querySelector('.btn-del').onclick = () => {
            if (confirm('Delete this question?')) {
                removeQuestion(q.id);
            }
        };
        // Bind type/ILO/title/points changes
        card.querySelector('.q-type').onchange = e => { q.type = e.target.value; };
        card.querySelector('.q-title').oninput = e => { q.title = e.target.value; };
        card.querySelector('.q-points').oninput = e => { q.points = Number(e.target.value); };
        card.querySelector('.q-ilo').onchange = e => { q.ilo = e.target.value; };
        return card;
    }

    // --- Remove Question ---
    function removeQuestion(qid) {
        const idx = questions.findIndex(q => q.id === qid);
        if (idx !== -1) {
            questions.splice(idx, 1);
            renderQuestions();
        }
    }

    // --- Render All Questions ---
    function renderQuestions() {
        const list = $('#questionList');
        if (!list) return;
        list.innerHTML = '';
        questions.forEach((q, idx) => {
            q.index = idx + 1;
            const card = createQuestionCard(q);
            list.appendChild(card);
        });
    }

    // --- Add Question ---
    function addQuestion(type = 'mcq') {
        questionCounter++;
        const id = 'q' + Date.now() + '-' + questionCounter;
        questions.push({
            id,
            index: questions.length + 1,
            type,
            title: '',
            text: '',
            points: 1,
            ilo: 'ILO1'
        });
        renderQuestions();
    }

    // --- Initial Load ---
    document.addEventListener('DOMContentLoaded', async () => {
        await fetchILOs(window.COURSE_ID || 1);
        renderQuestions();
        $('#add_question').onclick = () => addQuestion();
    });

    // --- Expose for debugging ---
    window.CanvasQuizBuilder = {
        questions,
        addQuestion,
        renderQuestions
    };

    document.addEventListener('DOMContentLoaded', () => {
        $all('.tab-btn').forEach(btn => {
            btn.onclick = () => {
                // Remove active from all buttons
                $all('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                // Hide all panes
                $all('.tab-pane').forEach(pane => pane.style.display = 'none');
                // Show the selected pane
                const tabId = btn.getAttribute('data-tab');
                const pane = document.getElementById(tabId);
                if (pane) pane.style.display = 'block';
            };
        });
    });
})();