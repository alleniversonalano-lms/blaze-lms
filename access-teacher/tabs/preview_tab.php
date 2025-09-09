<!-- Preview Tab -->
<div id="preview" class="tab-pane fade">
    <div class="assessment-card">
        <h3 class="mb-3">Assessment Preview</h3>
        <div id="preview-list">
            <p class="text-muted">No questions added yet. Add some questions to preview them here.</p>
        </div>
    </div>
</div>

<!-- Styles -->
<style>
    .preview-question {
        background: #f9f9f9;
        border: 1px dashed #ccc;
        padding: 15px 20px;
        border-radius: 6px;
        margin-bottom: 20px;
    }

    .preview-question h5 {
        margin-bottom: 10px;
        font-size: 17px;
        font-weight: 600;
    }

    .preview-question ul {
        padding-left: 20px;
        margin-bottom: 0;
    }

    .preview-question .answer {
        color: #155724;
        font-weight: bold;
        margin-top: 8px;
    }

    .text-muted {
        font-style: italic;
        color: #888;
    }
</style>

<!-- Preview Renderer -->
<script>
    function renderPreviewFromJSON() {
        const previewList = document.getElementById('preview-list');
        previewList.innerHTML = '';

        const rawJSON = document.getElementById('questions_json').value;
        if (!rawJSON || rawJSON.trim() === '') {
            previewList.innerHTML = '<p class="text-muted">No questions added yet.</p>';
            return;
        }

        let data;
        try {
            data = JSON.parse(rawJSON);
        } catch (e) {
            previewList.innerHTML = '<p class="text-danger">Error parsing questions JSON.</p>';
            return;
        }

        if (!Array.isArray(data) || data.length === 0) {
            previewList.innerHTML = '<p class="text-muted">No questions to preview.</p>';
            return;
        }

        data.forEach((q, index) => {
            const div = document.createElement('div');
            div.className = 'preview-question';

            div.innerHTML = `
                <h5>Q${index + 1} (${q.type.replace(/_/g, ' ')}):</h5>
                <p>${q.text}</p>
                ${q.ilo_text ? `<p><strong>ILO:</strong> ${q.ilo_text}</p>` : ''}
            `;

            if (q.type === 'multiple_choice' && Array.isArray(q.options)) {
                const ul = document.createElement('ul');
                q.options.forEach(opt => {
                    const li = document.createElement('li');
                    li.innerHTML = `${opt.text} ${opt.is_correct ? '<strong>(Correct)</strong>' : ''}`;
                    ul.appendChild(li);
                });
                div.appendChild(ul);
            }

            if (q.type === 'fill_in_the_blank' && Array.isArray(q.blanks)) {
                const ul = document.createElement('ul');
                q.blanks.forEach(b => {
                    const li = document.createElement('li');
                    li.innerHTML = `Blank ${b.index + 1}: ${b.answer} ${b.case_sensitive ? '<em>(Case Sensitive)</em>' : ''}`;
                    ul.appendChild(li);
                });
                div.appendChild(ul);
            }

            if (q.type === 'formula') {
                if (q.steps && Array.isArray(q.steps)) {
                    q.steps.forEach((step, i) => {
                        div.innerHTML += `
                            <p><strong>Step ${i + 1}:</strong> ${step.formula}</p>
                            <div class="answer">
                                Expected: ${step.expected}<br>
                                Decimal Places: ${step.decimals}<br>
                                Tolerance: ±${step.tolerance}
                            </div>
                        `;
                    });
                }

                if (q.variables && Array.isArray(q.variables)) {
                    div.innerHTML += '<p><strong>Variables:</strong></p><ul>';
                    q.variables.forEach(v => {
                        div.innerHTML += `
                            <li>${v.name}: ${v.min} to ${v.max}, Decimals: ${v.decimals}, ${v.is_constant ? 'Constant' : 'Randomized'}</li>
                        `;
                    });
                    div.innerHTML += '</ul>';
                }
            }

            previewList.appendChild(div);
        });
    }

    // Trigger preview render on tab click
    document.querySelector('[href="#preview"]').addEventListener('click', () => {
        // Trigger serialization (fake form submit)
        const form = document.querySelector('form');
        if (form) {
            const event = new Event('submit', { cancelable: true });
            form.dispatchEvent(event);
        }

        renderPreviewFromJSON();
    });
</script>
