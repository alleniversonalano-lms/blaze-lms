
        // Rich Text Editor Functions
        let activeEditor = null;

        function execCommand(command, value = null) {
            if (!activeEditor) return;
            activeEditor.focus();
            document.execCommand('styleWithCSS', false, true);
            document.execCommand(command, false, value);
            updateToolbarState();
        }

        function updateToolbarState() {
            if (!activeEditor) return;

            const commands = ['bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript'];
            commands.forEach(cmd => {
                const button = document.querySelector(`[onclick="execCommand('${cmd}')"]`);
                if (button) {
                    button.classList.toggle('active', document.queryCommandState(cmd));
                }
            });
        }

        function setActiveEditor(editor) {
            // Remove focus from previous editor
            if (activeEditor && activeEditor !== editor) {
                activeEditor.classList.remove('focused');
            }
            
            activeEditor = editor;
            if (activeEditor) {
                activeEditor.classList.add('focused');
                updateToolbarState();
            }
        }

        function insertLink() {
            const selection = window.getSelection();
            const selectedText = selection.toString().trim();
            
            // Show URL suggestions based on common patterns
            const urlSuggestions = [
                { name: "Google Search", url: "https://www.google.com/search?q=" },
                { name: "YouTube", url: "https://www.youtube.com/watch?v=" },
                { name: "Wikipedia", url: "https://wikipedia.org/wiki/" },
                { name: "Custom URL", url: "http://" }
            ];
            
            let urlOptionsHtml = urlSuggestions.map((item, index) => 
                `${index + 1}. ${item.name}`
            ).join('\\n');
            
            // First, handle the text to be linked
            let linkText = selectedText;
            if (!linkText) {
                linkText = prompt('Enter the text to be displayed for the link:');
                if (!linkText) return; // User cancelled
            }
            
            // Then, handle the URL
            const urlChoice = prompt(
                `Select a URL type by entering a number, or enter a complete URL:\\n\\n${urlOptionsHtml}\\n\\nYour choice (1-${urlSuggestions.length}) or full URL:`,
                selectedText ? 'http://' : '1'
            );
            
            if (!urlChoice) return; // User cancelled
            
            let finalUrl = '';
            if (!isNaN(urlChoice) && urlChoice > 0 && urlChoice <= urlSuggestions.length) {
                // User selected a suggestion
                const suggestion = urlSuggestions[parseInt(urlChoice) - 1];
                if (suggestion.name === "Custom URL") {
                    finalUrl = prompt('Enter the complete URL:', 'http://');
                } else {
                    const searchTerm = prompt(`Enter the ${suggestion.name} search term or ID:`, 
                        selectedText.replace(/\\s+/g, suggestion.name === "Wikipedia" ? '_' : '+'));
                    if (searchTerm) {
                        finalUrl = suggestion.url + encodeURIComponent(searchTerm);
                    }
                }
            } else {
                // User entered a custom URL
                finalUrl = urlChoice.startsWith('http') ? urlChoice : 'http://' + urlChoice;
            }
            
            if (finalUrl) {
                // If there was a selection, preserve it
                if (selectedText) {
                    const range = selection.getRangeAt(0);
                    const link = document.createElement('a');
                    link.href = finalUrl;
                    link.target = '_blank';
                    link.textContent = linkText;
                    range.deleteContents();
                    range.insertNode(link);
                } else {
                    // Insert new link at cursor position
                    document.execCommand('insertHTML', false, 
                        `<a href="${finalUrl}" target="_blank">${linkText}</a>`);
                }
            }
        }

        // Handle description image file selection
        function handleDescImageFileSelect() {
            const imagePreview = document.getElementById('descImagePreview');
            const imagePlaceholder = document.getElementById('descImagePlaceholder');

            if (this.files && this.files[0]) {
                const file = this.files[0];
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file.');
                    this.value = '';
                    imagePreview.style.display = 'none';
                    imagePlaceholder.style.display = 'block';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                    imagePlaceholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            } else {
                imagePreview.style.display = 'none';
                imagePlaceholder.style.display = 'block';
            }
        }

        // Initialize the description image modal
        function insertDescriptionImage() {
            const descEditor = document.getElementById('quizDescription');
            if (!descEditor) return;
            
            // Focus the description editor
            descEditor.focus();
            
            // Get modal elements
            const modal = document.getElementById('descriptionImageModal');
            const imageUrl = document.getElementById('descImageUrl');
            const imageFile = document.getElementById('descImageFile');
            const imagePreview = document.getElementById('descImagePreview');
            const imagePlaceholder = document.getElementById('descImagePlaceholder');
            
            // Clear previous inputs
            imageUrl.value = '';
            imageFile.value = '';
            
            // Reset preview state
            imagePreview.src = '';
            imagePreview.style.display = 'none';
            imagePlaceholder.style.display = 'block';
            
            // Show modal
            modal.style.display = 'block';
            
            // Set up file input change handler
            imageFile.onchange = handleDescImageFileSelect;
        }

        // Close and reset the description image modal
        function closeDescriptionImageModal() {
            // Get modal elements
            const modal = document.getElementById('descriptionImageModal');
            const imageUrl = document.getElementById('descImageUrl');
            const imageFile = document.getElementById('descImageFile');
            const imagePreview = document.getElementById('descImagePreview');
            const imagePlaceholder = document.getElementById('descImagePlaceholder');
            
            // Clear all inputs
            imageUrl.value = '';
            imageFile.value = '';
            
            // Reset preview
            imagePreview.src = '';
            imagePreview.style.display = 'none';
            imagePlaceholder.style.display = 'block';
            
            // Hide modal
            modal.style.display = 'none';
        }

        // Confirm and insert the description image
        function confirmDescriptionImage() {
            const descEditor = document.getElementById('quizDescription');
            if (!descEditor) return;

            const urlInput = document.getElementById('descImageUrl');
            const fileInput = document.getElementById('descImageFile');
            
            let imageUrl = '';
            
            if (urlInput.value.trim()) {
                // Use URL input
                imageUrl = urlInput.value.trim();
            } else if (fileInput.files && fileInput.files[0]) {
                // Use uploaded file
                imageUrl = document.getElementById('descImagePreview').src;
            } else {
                alert('Please provide an image URL or upload a file.');
                return;
            }

            descEditor.focus();
            document.execCommand('insertImage', false, imageUrl);
            closeDescriptionImageModal();
        }

        // Question section image functions
        // Handle image file selection
        function handleImageFileSelect() {
            const imagePreview = document.getElementById('imagePreview');
            const imagePlaceholder = document.getElementById('imagePlaceholder');

            if (this.files && this.files[0]) {
                const file = this.files[0];
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file.');
                    this.value = '';
                    imagePreview.style.display = 'none';
                    imagePlaceholder.style.display = 'block';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                    imagePlaceholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            } else {
                imagePreview.style.display = 'none';
                imagePlaceholder.style.display = 'block';
            }
        }

        // Initialize the image modal
        function insertImage() {
            if (!activeEditor) return;
            
            // Get modal elements
            const modal = document.getElementById('imageModal');
            const imageUrl = document.getElementById('imageUrl');
            const imageFile = document.getElementById('imageFile');
            const imagePreview = document.getElementById('imagePreview');
            const imagePlaceholder = document.getElementById('imagePlaceholder');
            
            // Clear previous inputs
            imageUrl.value = '';
            imageFile.value = '';
            
            // Reset preview state
            imagePreview.src = '';
            imagePreview.style.display = 'none';
            imagePlaceholder.style.display = 'block';
            
            // Show modal
            modal.style.display = 'block';
        }

        // Close and reset the image modal
        function closeImageModal() {
            // Get modal elements
            const modal = document.getElementById('imageModal');
            const imageUrl = document.getElementById('imageUrl');
            const imageFile = document.getElementById('imageFile');
            const imagePreview = document.getElementById('imagePreview');
            const imagePlaceholder = document.getElementById('imagePlaceholder');
            
            // Clear all inputs
            imageUrl.value = '';
            imageFile.value = '';
            
            // Reset preview
            imagePreview.src = '';
            imagePreview.style.display = 'none';
            imagePlaceholder.style.display = 'block';
            
            // Hide modal
            modal.style.display = 'none';
        }

        function confirmImage() {
            if (!activeEditor) return;

            const urlInput = document.getElementById('imageUrl');
            const fileInput = document.getElementById('imageFile');
            
            let imageUrl = '';
            
            if (urlInput.value.trim()) {
                // Use URL input
                imageUrl = urlInput.value.trim();
            } else if (fileInput.files && fileInput.files[0]) {
                // Use uploaded file
                imageUrl = document.getElementById('imagePreview').src;
            } else {
                alert('Please provide an image URL or upload a file.');
                return;
            }

            activeEditor.focus();
            document.execCommand('insertImage', false, imageUrl);
            closeImageModal();
        }

        let selectedRows = 0;
        let selectedCols = 0;

        function insertTable() {
            if (!activeEditor) return;
            
            // Show the modal
            const modal = document.getElementById('tableModal');
            modal.style.display = 'block';
            
            // Initialize the grid
            const gridContainer = document.querySelector('.grid-container');
            gridContainer.innerHTML = '';
            
            // Create 10x10 grid
            for (let i = 0; i < 10; i++) {
                for (let j = 0; j < 10; j++) {
                    const cell = document.createElement('div');
                    cell.className = 'grid-cell';
                    cell.dataset.row = i;
                    cell.dataset.col = j;
                    cell.addEventListener('mouseover', highlightCells);
                    cell.addEventListener('click', selectTableSize);
                    gridContainer.appendChild(cell);
                }
            }
            
            // Reset selection
            selectedRows = 0;
            selectedCols = 0;
            document.getElementById('gridSize').textContent = '0 × 0';
        }

        function highlightCells(e) {
            const row = parseInt(e.target.dataset.row);
            const col = parseInt(e.target.dataset.col);
            
            document.querySelectorAll('.grid-cell').forEach(cell => {
                const cellRow = parseInt(cell.dataset.row);
                const cellCol = parseInt(cell.dataset.col);
                
                if (cellRow <= row && cellCol <= col) {
                    cell.classList.add('active');
                } else {
                    cell.classList.remove('active');
                }
            });
            
            document.getElementById('gridSize').textContent = `${row + 1} × ${col + 1}`;
        }

        function selectTableSize(e) {
            selectedRows = parseInt(e.target.dataset.row) + 1;
            selectedCols = parseInt(e.target.dataset.col) + 1;
            // Immediately confirm the selection when clicking
            confirmTable();
        }

        function closeTableModal() {
            const modal = document.getElementById('tableModal');
            modal.style.display = 'none';
            selectedRows = 0;
            selectedCols = 0;
        }

        function confirmTable() {
            if (!activeEditor || selectedRows === 0 || selectedCols === 0) return;
            
            const bordered = document.getElementById('tableBordered').checked;
            const striped = document.getElementById('tableStriped').checked;
            
            // Create table with responsive styles
            let html = '<div style="max-width: 100%; overflow-x: auto; margin: 8px 0;">';
            html += '<table style="border-collapse: collapse; min-width: 50%; max-width: 100%;' +
                    (bordered ? ' border: 1px solid #dee2e6;' : '') + '">';
            
            for (let i = 0; i < selectedRows; i++) {
                html += '<tr' + (striped && i % 2 === 1 ? ' style="background-color: #f8f9fa;"' : '') + '>';
                for (let j = 0; j < selectedCols; j++) {
                    // Calculate a reasonable minimum width based on column count
                    const minWidth = Math.max(50, Math.min(120, Math.floor(600 / selectedCols)));
                    html += '<td style="padding: 8px; min-width: ' + minWidth + 'px;' +
                        (bordered ? ' border: 1px solid #dee2e6;' : '') +
                        '">&nbsp;</td>';
                }
                html += '</tr>';
            }
            html += '</table>';
            html += '</div>';
            
            activeEditor.focus();
            document.execCommand('insertHTML', false, html);
            closeTableModal();
        }

        function insertMathEquation() {
            // Removed LaTeX functionality
        }

        function showSymbolPicker() {
            const picker = document.getElementById('symbolPicker');
            picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
        }

        function insertSymbol(symbol) {
            document.execCommand('insertText', false, symbol);
            document.getElementById('symbolPicker').style.display = 'none';
        }

        function insertBlank(editorId) {
            const editor = document.getElementById(`editor_${editorId}`);
            if (editor) {
                editor.focus();
                document.execCommand('insertText', false, '_');
                // Trigger the blur event to update the preview
                editor.dispatchEvent(new Event('blur'));
            }
        }

        // Close symbol picker when clicking outside
        document.addEventListener('click', function(e) {
            const picker = document.getElementById('symbolPicker');
            const symbolBtn = document.querySelector('[title="Insert Symbol"]');
            if (picker && !picker.contains(e.target) && e.target !== symbolBtn) {
                picker.style.display = 'none';
            }
        });

        // Initialize editor
        document.addEventListener('DOMContentLoaded', function() {
            // Force styleWithCSS for the entire document
            document.execCommand('styleWithCSS', false, true);
            
            // Initialize all editors
            document.querySelectorAll('.rich-editor-content').forEach(editor => {
                // Force styleWithCSS for each editor
                editor.addEventListener('focus', function() {
                    document.execCommand('styleWithCSS', false, true);
                    setActiveEditor(this);
                });

                editor.addEventListener('blur', function() {
                    updateToolbarState();
                });

                // Content change handling
                editor.addEventListener('keyup', updateToolbarState);
                editor.addEventListener('mouseup', updateToolbarState);
                editor.addEventListener('input', updateToolbarState);

                // Click handling for empty editor
                editor.addEventListener('click', function(e) {
                    if (!this.innerHTML.trim()) {
                        this.focus();
                    }
                });

                // Paste handling
                editor.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const text = e.clipboardData.getData('text/plain');
                    document.execCommand('insertText', false, text);
                });
            });

            // Handle clicks on the editor container
            document.querySelectorAll('.rich-editor').forEach(container => {
                container.addEventListener('click', function(e) {
                    if (e.target === this) {
                        const editor = this.querySelector('.rich-editor-content');
                        if (editor) {
                            editor.focus();
                        }
                    }
                });
            });

            // Handle toolbar button states
            document.querySelectorAll('.toolbar-btn').forEach(button => {
                button.addEventListener('mousedown', function(e) {
                    e.preventDefault(); // Prevent losing focus from editor
                });
            });
        });

        // Floating button dropdown logic
        function toggleFloatingDropdown(e) {
            const dropdown = document.getElementById('questionTypeDropdown');
            dropdown.classList.toggle('show');
            // Position dropdown near floating button
            if (dropdown.classList.contains('show')) {
                const btn = document.getElementById('floatingNewQuestionBtn');
                const rect = btn.getBoundingClientRect();
                dropdown.style.position = 'fixed';
                dropdown.style.right = '32px';
                dropdown.style.bottom = (rect.height + 40) + 'px';
                dropdown.style.left = '';
                dropdown.style.top = '';
            } else {
                dropdown.style.position = '';
                dropdown.style.right = '';
                dropdown.style.bottom = '';
            }
            e.stopPropagation();
        }
        // Ensure dropdown closes when clicking elsewhere
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('questionTypeDropdown');
            const btn = document.getElementById('floatingNewQuestionBtn');
            if (!dropdown.contains(event.target) && event.target !== btn) {
                dropdown.classList.remove('show');
                dropdown.style.position = '';
                dropdown.style.right = '';
                dropdown.style.bottom = '';
            }
        });
        let questions = [];
        let questionCounter = 0;
        let expandedQuestion = null;
        
        // Function to save a question to the question bank
        function saveToQuestionBank(questionId) {
            const question = questions.find(q => q.id === questionId);
            if (!question) return;

            // Generate a unique ID for the banked question using a timestamp and random string
            const uniqueId = `question_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;

            // Add metadata including course_id and the unique ID
            const questionToSave = {
                ...question,
                id: uniqueId, // Use the unique ID
                savedDate: new Date().toISOString(),
                courseId: window.ann_course_id,
                savedBy: {
                    userId: window.user_id,
                    name: `${window.first_name} ${window.last_name}`
                }
            };

            // Load existing question bank
            fetch('question_bank.json?' + new Date().getTime())
                .then(response => response.json())
                .catch(() => ({ questions: [] })) // Initialize if file doesn't exist
                .then(data => {
                    const questions = data.questions || [];
                    questions.push(questionToSave);

                    // Save updated question bank
                    return fetch('save_question_bank', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ questions })
                    });
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Question saved to bank successfully!');
                    } else {
                        throw new Error(data.error || 'Failed to save question');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to save question: ' + error.message);
                });
        }

        // Function to update question counter based on existing questions
        function updateQuestionCounter() {
            // Find the highest numeric ID from existing questions
            questionCounter = questions.reduce((maxId, question) => {
                const idMatch = question.id.match(/question_(\d+)/);
                if (idMatch) {
                    const id = parseInt(idMatch[1]);
                    return Math.max(maxId, id);
                }
                return maxId;
            }, 0);
        }

        // Rich Editor Toolbar Functionality
        function formatEditor(questionId, command) {
            const editor = document.getElementById(`editor_${questionId}`);
            editor.focus();
            let sel = window.getSelection();
            let range = sel.rangeCount > 0 ? sel.getRangeAt(0) : null;
            switch (command) {
                case 'bold':
                    document.execCommand('bold');
                    break;
                case 'italic':
                    document.execCommand('italic');
                    break;
                case 'underline':
                    document.execCommand('underline');
                    break;
                case 'link':
                    var url = prompt('Enter the link URL:');
                    if (url) document.execCommand('createLink', false, url);
                    break;
                case 'image':
                    var imgUrl = prompt('Enter the image URL:');
                    if (imgUrl) document.execCommand('insertImage', false, imgUrl);
                    break;
                case 'equation':
                    var eq = prompt('Enter LaTeX equation (will be wrapped in $...$):');
                    if (eq) {
                        // Insert as plain text with $...$
                        if (range) {
                            range.deleteContents();
                            range.insertNode(document.createTextNode(`$${eq}$`));
                        } else {
                            document.execCommand('insertText', false, `$${eq}$`);
                        }
                    }
                    break;
            }
        }
        // Tab Management
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');

            // Show/hide floating button based on tab
            var btn = document.getElementById('floatingNewQuestionBtn');
            if (tabName === 'questions') {
                btn.classList.remove('hide');
            } else {
                btn.classList.add('hide');
            }

            if (tabName === 'preview') {
                updatePreview();
            }
        }

        // Dropdown Management
        function toggleDropdown() {
            const dropdown = document.getElementById('questionTypeDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.dropdown-btn')) {
                const dropdowns = document.getElementsByClassName('dropdown-content');
                for (let i = 0; i < dropdowns.length; i++) {
                    dropdowns[i].classList.remove('show');
                }
            }
        }

        // Question Management
        function addNewQuestion(type) {
            // Increment counter after checking existing questions
            updateQuestionCounter();
            questionCounter++;
            const questionId = `question_${questionCounter}`;

            const questionData = {
                id: questionId,
                type: type,
                title: '',
                content: '',
                points: 1,
                choices: type === 'multiple_choice' ? ['', '', '', ''] : [],
                correctAnswers: type === 'multiple_choice' ? [] : [],
                isMultipleAnswer: type === 'multiple_choice' ? false : null,
                formula: '',
                variables: {},
                blanks: [],
                blankText: ''
            };

            questions.push(questionData);
            renderQuestion(questionData);

            // Hide empty state
            document.getElementById('emptyState').style.display = 'none';

            // Close dropdown
            document.getElementById('questionTypeDropdown').classList.remove('show');

            // Expand the new question
            expandQuestion(questionId);
        }

        function renderQuestion(questionData) {
            const questionsList = document.getElementById('questionsList');

            const questionDiv = document.createElement('div');
            questionDiv.className = 'question-item';
            questionDiv.id = questionData.id;
            if (questionData.type === 'multiple_choice') {
                questionDiv.setAttribute('data-multiple-answer', questionData.isMultipleAnswer);
            }

            const typeClass = questionData.type.replace('_', '-');
            const typeName = getTypeName(questionData.type);
            const questionNumber = questions.findIndex(q => q.id === questionData.id) + 1;

            questionDiv.innerHTML = `
                <div class="question-header" onclick="toggleQuestion('${questionData.id}')">
                    <div class="question-title-section">
                        <span class="question-type-badge ${typeClass}">${typeName}</span>
                        <div class="question-summary">
                            <div style="font-weight: 500;">Question ${questionNumber}</div>
                            <div class="text-muted text-sm">${questionData.title || 'Untitled Question'}</div>
                        </div>
                    </div>
                    <div class="question-actions" onclick="event.stopPropagation()">
                        <button class="btn btn-sm btn-outline" 
                                onclick="saveToQuestionBank('${questionData.id}')" 
                                title="Save to Question Bank">
                            Save to Bank
                        </button>
                        <input type="number" class="points-input" value="${questionData.points}" 
                               min="0" step="1" title="Points"
                               onchange="updateQuestionPoints('${questionData.id}', this.value)">
                        <span class="text-sm text-muted">pts</span>
                        <button class="icon-btn danger" onclick="deleteQuestion('${questionData.id}')" title="Delete">
                            🗑️
                        </button>
                    </div>
                </div>
                <div class="question-content" id="content_${questionData.id}">
                    ${renderQuestionEditor(questionData)}
                </div>
            `;

            questionsList.appendChild(questionDiv);
        }

        function renderRichEditor(id, content, placeholder, extraButtons = '') {
            return `
                <div class="rich-editor">
                    <div class="rich-editor-toolbar">
                        <button class="toolbar-btn" type="button" title="Bold" onclick="formatEditor('${id}', 'bold')"><strong>B</strong></button>
                        <button class="toolbar-btn" type="button" title="Italic" onclick="formatEditor('${id}', 'italic')"><em>I</em></button>
                        <button class="toolbar-btn" type="button" title="Underline" onclick="formatEditor('${id}', 'underline')"><u>U</u></button>
                        <span style="border-left: 1px solid #ddd; margin: 0 4px;"></span>
                        <button class="toolbar-btn" type="button" title="Link" onclick="formatEditor('${id}', 'link')">🔗</button>
                        <button class="toolbar-btn" type="button" title="Image" onclick="formatEditor('${id}', 'image')">🖼️</button>
                        <button class="toolbar-btn" type="button" title="Equation" onclick="formatEditor('${id}', 'equation')">∑</button>
                        ${extraButtons}
                    </div>
                    <div class="rich-editor-content" 
                            contenteditable="true" 
                            id="editor_${id}" 
                            placeholder="${placeholder}"
                            onblur="updateQuestion('${id}', 'content', this.innerHTML)"
                            style="min-height: 100px;">${content || ''}</div>
                    </div>
                `;
        }

        function renderQuestionEditor(questionData) {
            let html = `
                <div class="form-group">
                    <label class="form-label">Question Title</label>
                    <input type="text" class="form-control" placeholder="Enter question title" 
                        value="${questionData.title}"
                        onchange="updateQuestion('${questionData.id}', 'title', this.value)">
                </div>
            `;

            // Only show Question Text field for non-fill-blank questions
            if (questionData.type !== 'fill_blank') {
                html += `
                    <div class="form-group">
                        <label class="form-label">Question Text</label>
                        ${renderRichEditor(questionData.id, questionData.content, 'Enter your question text...', '')}
                    </div>
                `;
            }
            // Rich Editor Toolbar Functionality
            function formatEditor(questionId, command) {
                const editor = document.getElementById(`editor_${questionId}`);
                editor.focus();
                let sel = window.getSelection();
                let range = sel.rangeCount > 0 ? sel.getRangeAt(0) : null;
                switch (command) {
                    case 'bold':
                        document.execCommand('bold');
                        break;
                    case 'italic':
                        document.execCommand('italic');
                        break;
                    case 'underline':
                        document.execCommand('underline');
                        break;
                    case 'link':
                        var url = prompt('Enter the link URL:');
                        if (url) document.execCommand('createLink', false, url);
                        break;
                    case 'image':
                        var imgUrl = prompt('Enter the image URL:');
                        if (imgUrl) document.execCommand('insertImage', false, imgUrl);
                        break;
                    case 'equation':
                        var eq = prompt('Enter LaTeX equation (will be wrapped in $...$):');
                        if (eq) {
                            // Insert as plain text with $...$
                            if (range) {
                                range.deleteContents();
                                range.insertNode(document.createTextNode(`$${eq}$`));
                            } else {
                                document.execCommand('insertText', false, `$${eq}$`);
                            }
                        }
                        break;
                }
            }

            // Continue with the rest of the question type specific editors
            if (questionData.type === 'multiple_choice') {
                html += renderMultipleChoiceEditor(questionData);
            } else if (questionData.type === 'fill_blank') {
                html += renderFillBlankEditor(questionData);
            } else if (questionData.type === 'formula') {
                html += renderFormulaEditor(questionData);
            }

            html += `
                <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e9ecef; display: flex; gap: 12px;">
                    <button class="btn btn-primary" onclick="saveQuestion('${questionData.id}')">Update Question</button>
                    <button class="btn btn-outline" onclick="collapseQuestion('${questionData.id}')">Cancel</button>
                </div>
            `;

            return html;
        }

        function renderMultipleChoiceEditor(questionData) {
    return `
        <div class="form-group">
            <label class="form-label">Answers</label>
            <div class="answer-type-selector mb-3">
                <label class="form-check" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" 
                           class="form-check-input" 
                           id="multipleAnswer_${questionData.id}"
                           ${questionData.isMultipleAnswer ? 'checked' : ''}
                           onchange="toggleMultipleAnswer('${questionData.id}', this.checked)">
                    <span class="form-check-label">Allow multiple correct answers</span>
                </label>
            </div>
            <div class="answer-choices" id="choices_${questionData.id}">
                ${questionData.choices.map((choice, index) => `
                    <div class="choice-item ${questionData.correctAnswers.includes(index) ? 'correct' : ''}">
                        <div class="choice-marker ${questionData.correctAnswers.includes(index) ? 'correct' : ''}" 
                             onclick="toggleCorrectAnswer('${questionData.id}', ${index})"></div>
                        <div class="choice-content">
                            <div class="math-input-container">
                                <input type="text" class="form-control choice-text"
                                       value="${choice || ''}"
                                       oninput="handleMathInput('${questionData.id}', ${index}, this.value)"
                                       placeholder="Enter answer choice">
                            </div>
                            <div class="math-preview" id="preview_${questionData.id}_${index}"></div>
                        </div>
                        ${questionData.choices.length > 2 ? `
                            <button class="icon-btn danger" 
                                    onclick="removeChoice('${questionData.id}', ${index})" 
                                    title="Remove this choice"></button>
                        ` : ''}
                    </div>
                `).join('')}
            </div>
            <button class="btn btn-outline btn-sm" onclick="addChoice('${questionData.id}')" style="margin-top: 8px;">
                + Add Another Answer Choice
            </button>
            
        </div>
    `;
}

function formatMathExpression(text) {
    if (!text) return '';
    
    return text
        // Handle fractions (e.g., (1/2), (x/y), (2x/3y) etc)
        .replace(/\(([^/]+)\/([^)]+)\)/g, (match, num, den) => {
            // Check if it's a simple numeric fraction that has a special character
            const numericFraction = /^\d+$/.test(num) && /^\d+$/.test(den);
            const fractions = {
                '1/2': '½', '1/3': '⅓', '2/3': '⅔', '1/4': '¼', '3/4': '¾',
                '1/5': '⅕', '2/5': '⅖', '3/5': '⅗', '4/5': '⅘', '1/6': '⅙',
                '5/6': '⅚', '1/7': '⅐', '1/8': '⅛', '3/8': '⅜', '5/8': '⅝',
                '7/8': '⅞', '1/9': '⅑', '1/10': '⅒'
            };
            
            if (numericFraction && fractions[`${num}/${den}`]) {
                return fractions[`${num}/${den}`];
            }
            
            // For all other fractions, use custom styling
            return `<span class="custom-fraction"><span class="numerator">${num}</span><span class="denominator">${den}</span></span>`;
        })
        // Rest of the conversions remain the same
        .replace(/\^([0-9]+)/g, (match, num) => {
            const superscripts = {
                '0': '⁰', '1': '¹', '2': '²', '3': '³', '4': '⁴',
                '5': '⁵', '6': '⁶', '7': '⁷', '8': '⁸', '9': '⁹'
            };
            return num.split('').map(n => superscripts[n] || n).join('');
        })
        .replace(/_([0-9]+)/g, (match, num) => {
            const subscripts = {
                '0': '₀', '1': '₁', '2': '₂', '3': '₃', '4': '₄',
                '5': '₅', '6': '₆', '7': '₇', '8': '₈', '9': '₉'
            };
            return num.split('').map(n => subscripts[n] || n).join('');
        })
        .replace(/sqrt\(([^)]+)\)/g, '√$1')
        .replace(/pi/g, 'π')
        .replace(/theta/g, 'θ')
        .replace(/delta/g, 'Δ')
        .replace(/inf/g, '∞')
        .replace(/times/g, '×')
        .replace(/div/g, '÷')
        .replace(/pm/g, '±');
}

function handleMathInput(questionId, index, value) {
    const question = questions.find(q => q.id === questionId);
    question.choices[index] = value;
    
    // Update preview with formatted math
    const preview = document.getElementById(`preview_${questionId}_${index}`);
    if (preview) {
        const formattedMath = formatMathExpression(value);
        if (formattedMath !== value) {
            preview.innerHTML = formattedMath;
            preview.style.display = 'block';
            preview.classList.add('math-preview-active');
        } else {
            preview.style.display = 'none';
            preview.classList.remove('math-preview-active');
        }
    }
}

// Add this CSS
const mcqStyles = `
    .answer-type-selector {
        margin-bottom: 16px;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 4px;
        border: 1px solid #e0e4e8;
    }

    .choice-marker {
        width: 20px;
        height: 20px;
        border: 2px solid #c7cdd1;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .question-item[data-multiple-answer="true"] .choice-marker {
        border-radius: 4px;
    }

    .choice-marker.correct {
        background: #4caf50;
        border-color: #4caf50;
    }

    .question-item[data-multiple-answer="true"] .choice-marker.correct::before {
        content: '✓';
        color: white;
        font-size: 14px;
    }

    .question-item:not([data-multiple-answer="true"]) .choice-marker.correct::before {
        content: '';
        width: 10px;
        height: 10px;
        background: white;
        border-radius: 50%;
    }
.math-preview {
        color: #0374b5;
        font-size: 14px;
        padding: 4px 0;
        font-family: 'Times New Roman', serif;
        min-height: 22px;
        transition: all 0.2s ease;
    }

    .choice-content {
        display: flex;
        flex-direction: column;
        gap: 4px;
        width: 100%;
    }

    .math-input-container {
        display: flex;
        flex-direction: column;
        gap: 4px;
        width: 100%;
    }

    .custom-fraction {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        vertical-align: middle;
        margin: 0 2px;
        padding: 2px;
    }

    .custom-fraction .numerator,
    .custom-fraction .denominator {
        padding: 0 2px;
        line-height: 1.2;
    }

    .custom-fraction .numerator {
        border-bottom: 1px solid currentColor;
        margin-bottom: 1px;
    }
`;

// Add the styles to the document
const styleSheet = document.createElement("style");
styleSheet.textContent = mcqStyles;
document.head.appendChild(styleSheet);

        function renderFillBlankEditor(questionData) {
    return `
        <div class="form-group">
            <label class="form-label">Question Text with Blanks</label>
            <div class="blank-template">
                <div class="text-sm text-muted mb-3">
                    Use <strong>'_'</strong> (underscore) to create fillable blanks in your question text.
                    Example: "The capital of France is _."
                </div>
                <div class="rich-editor">
                    <div class="rich-editor-toolbar">
                        <!-- ...existing toolbar buttons... -->
                        <button class="toolbar-btn" type="button" title="Insert Blank" onclick="insertBlank('${questionData.id}_blank')" style="padding: 4px 8px;">
                            Insert _
                        </button>
                    </div>
                    <div class="rich-editor-content" 
                         contenteditable="true" 
                         id="editor_${questionData.id}_blank" 
                         placeholder="Enter your question text and use _ for blanks..."
                         oninput="handleBlankTextInput('${questionData.id}')"
                         onblur="updateQuestion('${questionData.id}', 'blankText', this.innerHTML)"
                         style="min-height: 100px;">${questionData.blankText || ''}</div>
                </div>
                <div style="margin-top: 12px;">
                    <strong>Preview:</strong>
                    <div style="margin-top: 8px; padding: 12px; background: white; border: 1px solid #ddd; border-radius: 4px;">
                        ${generateBlankPreview(questionData.blankText)}
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Correct Answers</label>
            <div class="text-sm text-muted mb-2">Enter the correct answers for each blank in the order they appear</div>
            <div class="blank-answers" id="blankAnswers_${questionData.id}">
                ${renderBlankAnswers(questionData)}
            </div>
        </div>
    `;
}

function handleBlankTextInput(questionId) {
    const editor = document.getElementById(`editor_${questionId}_blank`);
    const preview = editor.closest('.blank-template').querySelector('[style*="margin-top: 8px"]');
    const answersContainer = document.getElementById(`blankAnswers_${questionId}`);
    const question = questions.find(q => q.id === questionId);
    
    // Update the question data
    question.blankText = editor.innerHTML;
    
    // Update the preview
    if (preview) {
        preview.innerHTML = generateBlankPreview(editor.innerHTML);
    }
    
    // Count blanks and update answer fields
    const blankCount = (editor.innerHTML.match(/\_/g) || []).length;
    const currentBlanksCount = question.blanks ? question.blanks.length : 0;
    
    // Initialize or adjust blanks array
    if (!question.blanks) {
        question.blanks = [];
    }
    
    // Add new blank answer fields if needed
    while (question.blanks.length < blankCount) {
        question.blanks.push(['']);
    }
    
    // Remove extra blank answer fields if needed
    if (question.blanks.length > blankCount) {
        question.blanks = question.blanks.slice(0, blankCount);
    }
    
    // Update the answer fields
    if (answersContainer) {
        answersContainer.innerHTML = renderBlankAnswers(question);
    }
}

// Add this to your existing code to ensure input event is triggered
function insertBlank(editorId) {
    const editor = document.getElementById(`editor_${editorId}`);
    if (editor) {
        editor.focus();
        document.execCommand('insertText', false, '_');
        // Manually trigger the input handler
        const questionId = editorId.replace('_blank', '');
        handleBlankTextInput(questionId);
    }
}

        function renderFormulaEditor(questionData) {
            return `
                <div class="form-group">
                    <label class="form-label">Formula</label>
                    <div class="formula-section">
                        <div class="text-sm text-muted mb-3">
                            Enter a mathematical formula using variables. Example: <code>a * x + b</code>
                        </div>
                        <input type="text" class="form-control" placeholder="e.g., a * x^2 + b * x + c"
                               value="${questionData.formula}"
                               onchange="updateQuestion('${questionData.id}', 'formula', this.value)"
                               style="font-family: monospace; font-size: 14px;">
                        
                        <div style="margin-top: 12px;">
                            <strong>Formula Preview:</strong>
                            <div id="formulaPreview_${questionData.id}" style="margin-top: 8px; padding: 12px; background: white; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
                                ${questionData.formula || 'Enter a formula above'}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Variables</label>
                    <div class="text-sm text-muted mb-2">Define the range of values for each variable in your formula</div>
                    <div class="variable-list" id="variables_${questionData.id}">
                        ${renderVariables(questionData)}
                    </div>
                    <button class="btn btn-outline btn-sm" onclick="addVariable('${questionData.id}')" style="margin-top: 8px;">
                        + Add Variable
                    </button>
                </div>

                <div class="form-group">
                    <label class="form-label">Answer Tolerance</label>
                    <div class="form-row">
                        <div class="form-col">
                            <input type="number" class="form-control" placeholder="0.01" step="0.001" 
                                   value="${questionData.tolerance || 0.01}"
                                   onchange="updateQuestion('${questionData.id}', 'tolerance', this.value)">
                        </div>
                        <div class="form-col">
                            <select class="form-control" onchange="updateQuestion('${questionData.id}', 'toleranceType', this.value)">
                                <option value="absolute" ${questionData.toleranceType === 'absolute' ? 'selected' : ''}>Absolute</option>
                                <option value="percentage" ${questionData.toleranceType === 'percentage' ? 'selected' : ''}>Percentage</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderBlankAnswers(questionData) {
    const blankCount = (questionData.blankText.match(/\_/g) || []).length;
    let html = '';

    for (let i = 0; i < Math.max(blankCount, 1); i++) {
        html += `
            <div class="blank-answer-item">
                <div class="blank-number">${i + 1}</div>
                <div style="flex: 1; display: flex; flex-direction: column; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <input type="text" class="form-control" placeholder="Primary correct answer for blank ${i + 1}"
                               value="${questionData.blanks[i] ? questionData.blanks[i][0] : ''}"
                               onchange="updateBlankAnswer('${questionData.id}', ${i}, this.value, 0)">
                        <label style="display: flex; align-items: center; gap: 6px; white-space: nowrap; font-size: 12px;">
                            <input type="checkbox" 
                                   ${questionData.caseSensitive && questionData.caseSensitive[i] && questionData.caseSensitive[i][0] ? 'checked' : ''}
                                   onchange="updateBlankCaseSensitivity('${questionData.id}', ${i}, 0, this.checked)">
                            Case Sensitive
                        </label>
                    </div>
                    <div class="alternate-answers" style="display: flex; flex-direction: column; gap: 8px;">
                        ${generateAlternateAnswerInputs(questionData, i)}
                    </div>
                    <button class="btn btn-outline btn-sm" onclick="addAlternateAnswer('${questionData.id}', ${i})" style="align-self: flex-start;">
                        + Add Alternate Answer
                    </button>
                </div>
            </div>
        `;
    }

    return html || '<div class="text-muted">Add _ markers to your question text to create answer fields</div>';
}

        function renderVariables(questionData) {
            return Object.keys(questionData.variables).map(varName => `
                <div class="variable-item">
                    <div class="variable-name">${varName}</div>
                    <div class="range-inputs">
                        <div class="range-group">
                            <span class="text-sm text-muted">Min:</span>
                            <input type="number" class="range-input" 
                                value="${questionData.variables[varName].min}"
                                onchange="updateVariableRange('${questionData.id}', '${varName}', 'min', this.value)">
                        </div>
                        <div class="range-group">
                            <span class="text-sm text-muted">Max:</span>
                            <input type="number" class="range-input"
                                value="${questionData.variables[varName].max}"
                                onchange="updateVariableRange('${questionData.id}', '${varName}', 'max', this.value)">
                        </div>
                        <div class="range-group">
                            <span class="text-sm text-muted">Step:</span>
                            <input type="number" class="range-input" min="0.001" step="0.001"
                                value="${questionData.variables[varName].step || 1}"
                                onchange="updateVariableRange('${questionData.id}', '${varName}', 'step', this.value)">
                        </div>
                        <div class="range-group">
                            <span class="text-sm text-muted">Decimals:</span>
                            <input type="number" class="range-input" min="0" max="6" step="1"
                                value="${questionData.variables[varName].decimals || 0}"
                                onchange="updateVariableRange('${questionData.id}', '${varName}', 'decimals', this.value)">
                        </div>
                    </div>
                    <button class="icon-btn danger" onclick="removeVariable('${questionData.id}', '${varName}')" title="Remove variable">
                        
                    </button>
                </div>
            `).join('');
        }

        // Helper Functions
        function getTypeName(type) {
            const types = {
                'multiple_choice': 'Multiple Choice',
                'fill_blank': 'Fill in Blank',
                'formula': 'Formula'
            };
            return types[type] || type;
        }

        function generateBlankPreview(text) {
            if (!text) return 'Enter question text with _ markers...';

            let blankCounter = 0;
            return text.replace(/\_/g, () => {
                blankCounter++;
                return `<span class="blank-indicator">Blank ${blankCounter}</span>`;
            });
        }

        // Question Interaction Functions
        function toggleQuestion(questionId) {
            if (expandedQuestion === questionId) {
                collapseQuestion(questionId);
            } else {
                expandQuestion(questionId);
            }
        }

        function expandQuestion(questionId) {
            // Collapse any currently expanded question
            if (expandedQuestion) {
                collapseQuestion(expandedQuestion);
            }

            expandedQuestion = questionId;
            const questionItem = document.getElementById(questionId);
            const content = document.getElementById(`content_${questionId}`);

            questionItem.classList.add('editing');
            content.classList.add('expanded');

            // Auto-resize textareas
            questionItem.querySelectorAll('textarea').forEach(textarea => {
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
            });
        }

        function collapseQuestion(questionId) {
            const questionItem = document.getElementById(questionId);
            const content = document.getElementById(`content_${questionId}`);

            questionItem.classList.remove('editing');
            content.classList.remove('expanded');

            if (expandedQuestion === questionId) {
                expandedQuestion = null;
            }

            // Update the question summary
            updateQuestionSummary(questionId);
        }

        function updateQuestionSummary(questionId) {
            const question = questions.find(q => q.id === questionId);
            const questionItem = document.getElementById(questionId);
            const summary = questionItem.querySelector('.question-summary');
            const questionNumber = questions.findIndex(q => q.id === questionId) + 1;

            summary.innerHTML = `
                <div style="font-weight: 500;">Question ${questionNumber}</div>
                <div class="text-muted text-sm">${question.title || 'Untitled Question'}</div>
            `;
        }

        // Question Update Functions
        function updateQuestion(questionId, field, value) {
            const question = questions.find(q => q.id === questionId);
            if (!question) return;

            // Handle special fields
            switch (field) {
                case 'content':
                    // Clean and store the content
                    question.content = value.trim();
                    break;
                case 'blankText':
                    question.blankText = value;
                    // Update blank answers when blank text changes
                    const container = document.getElementById(`blankAnswers_${questionId}`);
                    if (container) {
                        container.innerHTML = renderBlankAnswers(question);
                    }
                    break;
                case 'formula':
                    question.formula = value;
                    updateFormulaPreview(questionId);
                    break;
                default:
                    question[field] = value;
            }

            // Update preview if it's visible
            const previewTab = document.getElementById('preview');
            if (previewTab.classList.contains('active')) {
                updatePreview();
            }
        }

        function updateQuestionPoints(questionId, points) {
            const question = questions.find(q => q.id === questionId);
            question.points = parseFloat(points) || 1;
        }

        function saveQuestion(questionId) {
            collapseQuestion(questionId);
        }

        function deleteQuestion(questionId) {
            if (confirm('Are you sure you want to delete this question?')) {
                // If the deleted question was expanded, clear expandedQuestion
                if (expandedQuestion === questionId) {
                    expandedQuestion = null;
                }

                // Remove question from array
                questions = questions.filter(q => q.id !== questionId);
                
                // Remove the question element
                const questionElement = document.getElementById(questionId);
                if (questionElement) {
                    questionElement.remove();
                }

                // Handle empty state
                const emptyState = document.getElementById('emptyState');
                if (questions.length === 0 && emptyState) {
                    emptyState.style.display = 'block';
                }

                // Re-render all remaining questions to update numbering
                const questionsList = document.getElementById('questionsList');
                if (questionsList) {
                    // Clear existing questions
                    questionsList.innerHTML = questions.length === 0 ? `
                        <div class="text-muted" style="text-align: center; padding: 40px 20px;" id="emptyState">
                            <div style="font-size: 48px; margin-bottom: 16px;">📝</div>
                            <div style="font-size: 16px; margin-bottom: 8px;">No questions yet</div>
                            <div style="font-size: 14px;">Click "New Question" to get started</div>
                        </div>
                    ` : '';

                    // Re-render all questions
                    questions.forEach(question => {
                        renderQuestion(question);
                    });
                }

                // Update preview if it's active
                const previewTab = document.getElementById('preview');
                if (previewTab && previewTab.classList.contains('active')) {
                    updatePreview();
                }
            }
        }

        // Multiple Choice Functions
        function addChoice(questionId) {
            const question = questions.find(q => q.id === questionId);
            question.choices.push('');

            const container = document.getElementById(`choices_${questionId}`);
            if (container) {
                // Re-render the entire multiple choice editor content
                const questionDiv = document.getElementById(questionId);
                const content = questionDiv.querySelector('.question-content');
                content.innerHTML = renderMultipleChoiceEditor(question);
                
                // Update choice markers
                updateChoiceMarkers(questionId);
            }
        }

        function removeChoice(questionId, index) {
            const question = questions.find(q => q.id === questionId);
            if (question.choices.length > 2) {
                question.choices.splice(index, 1);
                question.correctAnswers = question.correctAnswers
                    .filter(i => i !== index)
                    .map(i => i > index ? i - 1 : i);

                const container = document.getElementById(`choices_${questionId}`);
                container.innerHTML = renderMultipleChoiceEditor(question).match(/<div class="answer-choices"[^>]*>(.*?)<\/div>/s)[1];
            }
        }

        function updateChoice(questionId, index, value) {
            const question = questions.find(q => q.id === questionId);
            question.choices[index] = value;
        }

        function toggleMultipleAnswer(questionId, isMultiple) {
            const question = questions.find(q => q.id === questionId);
            const questionDiv = document.getElementById(questionId);
            
            question.isMultipleAnswer = isMultiple;
            questionDiv.setAttribute('data-multiple-answer', isMultiple);
            
            // If switching to single answer mode, keep only the first correct answer
            if (!isMultiple && question.correctAnswers.length > 1) {
                question.correctAnswers = [question.correctAnswers[0]];
                updateChoiceMarkers(questionId);
            }
        }

        function toggleCorrectAnswer(questionId, index) {
            const question = questions.find(q => q.id === questionId);
            
            if (question.isMultipleAnswer) {
                // Toggle the answer in or out of correctAnswers array
                const answerIndex = question.correctAnswers.indexOf(index);
                if (answerIndex === -1) {
                    question.correctAnswers.push(index);
                } else {
                    question.correctAnswers.splice(answerIndex, 1);
                }
            } else {
                // Single answer mode - replace the current answer
                question.correctAnswers = [index];
            }

            updateChoiceMarkers(questionId);
        }

        function updateChoiceMarkers(questionId) {
            const question = questions.find(q => q.id === questionId);
            const container = document.getElementById(`choices_${questionId}`);
            
            container.querySelectorAll('.choice-item').forEach((item, i) => {
                const marker = item.querySelector('.choice-marker');
                if (question.correctAnswers.includes(i)) {
                    item.classList.add('correct');
                    marker.classList.add('correct');
                } else {
                    item.classList.remove('correct');
                    marker.classList.remove('correct');
                }
            });
        }

        // Fill in the Blank Functions
        function updateBlankAnswer(questionId, blankIndex, value, answerIndex) {
    const question = questions.find(q => q.id === questionId);
    if (!question.blanks) question.blanks = [];
    if (!question.blanks[blankIndex]) question.blanks[blankIndex] = [];
    question.blanks[blankIndex][answerIndex] = value;
    
    // Clean up empty answers at the end
    while (question.blanks[blankIndex].length > 0 && 
           !question.blanks[blankIndex][question.blanks[blankIndex].length - 1]) {
        question.blanks[blankIndex].pop();
        if (question.caseSensitive && question.caseSensitive[blankIndex]) {
            question.caseSensitive[blankIndex].pop();
        }
    }
}

        function updateBlankCaseSensitivity(questionId, blankIndex, answerIndex, isChecked) {
    const question = questions.find(q => q.id === questionId);
    if (!question.caseSensitive) question.caseSensitive = [];
    if (!question.caseSensitive[blankIndex]) question.caseSensitive[blankIndex] = [];
    question.caseSensitive[blankIndex][answerIndex] = isChecked;
}

        function generateAlternateAnswerInputs(questionData, blankIndex) {
    if (!questionData.blanks || !questionData.blanks[blankIndex]) return '';
    
    return questionData.blanks[blankIndex].slice(1).map((answer, altIndex) => `
        <div style="display: flex; align-items: center; gap: 8px;">
            <input type="text" class="form-control" 
                   placeholder="Alternate answer ${altIndex + 1}"
                   value="${answer || ''}"
                   onchange="updateBlankAnswer('${questionData.id}', ${blankIndex}, this.value, ${altIndex + 1})">
            <label style="display: flex; align-items: center; gap: 6px; white-space: nowrap; font-size: 12px;">
                <input type="checkbox" 
                       ${questionData.caseSensitive && 
                         questionData.caseSensitive[blankIndex] && 
                         questionData.caseSensitive[blankIndex][altIndex + 1] ? 'checked' : ''}
                       onchange="updateBlankCaseSensitivity('${questionData.id}', ${blankIndex}, ${altIndex + 1}, this.checked)">
                Case Sensitive
            </label>
            <button class="icon-btn danger" 
                    onclick="removeAlternateAnswer('${questionData.id}', ${blankIndex}, ${altIndex + 1})"
                    title="Remove this answer"></button>
        </div>
    `).join('');
}

        function addAlternateAnswer(questionId, blankIndex) {
            const question = questions.find(q => q.id === questionId);
            if (!question.blanks) question.blanks = [];
            if (!question.blanks[blankIndex]) question.blanks[blankIndex] = [''];
            question.blanks[blankIndex].push('');
            
            // Re-render the blank answers section
            const container = document.getElementById(`blankAnswers_${questionId}`);
            container.innerHTML = renderBlankAnswers(question);
        }

        function removeAlternateAnswer(questionId, blankIndex, answerIndex) {
            const question = questions.find(q => q.id === questionId);
            if (question.blanks && question.blanks[blankIndex]) {
                question.blanks[blankIndex].splice(answerIndex, 1);
                // Re-render the blank answers section
                const container = document.getElementById(`blankAnswers_${questionId}`);
                container.innerHTML = renderBlankAnswers(question);
            }
        }

        // Formula Functions
        function addVariable(questionId) {
            const varName = prompt('Enter variable name (e.g., x, y, R1, R2):');
            // Updated regex to allow letters followed by optional numbers
            if (varName && /^[a-zA-Z][a-zA-Z0-9]*$/.test(varName)) {
                const question = questions.find(q => q.id === questionId);
                if (!question.variables) question.variables = {};
                
                // Check if variable already exists
                if (question.variables[varName]) {
                    alert('Variable name already exists. Please use a different name.');
                    return;
                }

                question.variables[varName] = {
                    min: 1,
                    max: 10,
                    step: 1,
                    decimals: 0
                };

                const container = document.getElementById(`variables_${questionId}`);
                container.innerHTML = renderVariables(question);
                updateFormulaPreview(questionId);
            } else if (varName) {
                alert('Variable name must start with a letter and can contain letters and numbers (e.g., R1, V2, x, y)');
            }
        }

        function removeVariable(questionId, varName) {
            const question = questions.find(q => q.id === questionId);
            delete question.variables[varName];

            const container = document.getElementById(`variables_${questionId}`);
            container.innerHTML = renderVariables(question);
            updateFormulaPreview(questionId);
        }

        function updateVariableRange(questionId, varName, type, value) {
            const question = questions.find(q => q.id === questionId);
            question.variables[varName][type] = parseFloat(value);
            updateFormulaPreview(questionId);
        }

        function updateFormulaPreview(questionId) {
    const question = questions.find(q => q.id === questionId);
    const preview = document.getElementById(`formulaPreview_${questionId}`);

    if (!preview) return;

    if (question.formula && Object.keys(question.variables || {}).length > 0) {
        const sampleValues = {};
        
        // Generate sample values for variables
        Object.keys(question.variables).forEach(varName => {
            const variable = question.variables[varName];
            const min = parseFloat(variable.min) || 0;
            const max = parseFloat(variable.max) || 10;
            const step = parseFloat(variable.step) || 1;
            const decimals = parseInt(variable.decimals) || 0;
            
            // Validate step to ensure it's not larger than range
            const range = max - min;
            const validStep = Math.min(step, range);
            
            // Calculate possible steps within range
            const possibleSteps = Math.floor(range / validStep);
            
            // Get random step count
            const randomStepCount = Math.floor(Math.random() * possibleSteps);
            
            // Calculate final value with proper decimal handling
            const rawValue = min + (randomStepCount * validStep);
            const formattedValue = Number(rawValue.toFixed(decimals));
            
            sampleValues[varName] = formattedValue;
        });

        try {
            // Create calculation formula with proper math function handling
            let calculationFormula = question.formula
                // Constants
                .replace(/pi/g, 'Math.PI')
                .replace(/e(?!\w)/g, 'Math.E')
                
                // Basic trigonometric
                .replace(/sin\(/g, 'Math.sin(')
                .replace(/cos\(/g, 'Math.cos(')
                .replace(/tan\(/g, 'Math.tan(')
                
                // Inverse trigonometric
                .replace(/asin\(/g, 'Math.asin(')
                .replace(/acos\(/g, 'Math.acos(')
                .replace(/atan\(/g, 'Math.atan(')
                
                // Hyperbolic
                .replace(/sinh\(/g, 'Math.sinh(')
                .replace(/cosh\(/g, 'Math.cosh(')
                .replace(/tanh\(/g, 'Math.tanh(')
                
                // Inverse hyperbolic (adding new)
                .replace(/asinh\(/g, 'Math.asinh(')
                .replace(/acosh\(/g, 'Math.acosh(')
                .replace(/atanh\(/g, 'Math.atanh(')
                
                // Logarithmic
                .replace(/log\(/g, 'Math.log10(')
                .replace(/ln\(/g, 'Math.log(')
                .replace(/log2\(/g, 'Math.log2(')
                
                // Power and root
                .replace(/sqrt\(/g, 'Math.sqrt(')
                .replace(/cbrt\(/g, 'Math.cbrt(')
                .replace(/exp\(/g, 'Math.exp(')
                .replace(/pow\(/g, 'Math.pow(')
                
                // Rounding
                .replace(/floor\(/g, 'Math.floor(')
                .replace(/ceil\(/g, 'Math.ceil(')
                .replace(/round\(/g, 'Math.round(')
                
                // Other mathematical functions
                .replace(/abs\(/g, 'Math.abs(')
                .replace(/sign\(/g, 'Math.sign(')
                .replace(/\^/g, '**');

            // Replace variables with values
            Object.keys(sampleValues).forEach(varName => {
                const value = sampleValues[varName];
                calculationFormula = calculationFormula.replace(
                    new RegExp(`\\b${varName}\\b`, 'g'), 
                    `(${value})`
                );
            });

            // Calculate result
            const result = eval(calculationFormula);
            const maxDecimals = Math.max(3, ...Object.values(question.variables)
                                                    .map(v => v.decimals || 0));

            // Format display formula for better readability
            const displayFormula = question.formula
                .replace(/\*/g, '×')
                .replace(/\//g, '÷')
                .replace(/\^/g, '^')
                .replace(/pi/g, 'π')
                .replace(/sqrt\(/g, '√(')
                .replace(/cbrt\(/g, '∛(')
                .replace(/asin/g, 'sin⁻¹')
                .replace(/acos/g, 'cos⁻¹')
                .replace(/atan/g, 'tan⁻¹')
                .replace(/asinh/g, 'sinh⁻¹')
                .replace(/acosh/g, 'cosh⁻¹')
                .replace(/atanh/g, 'tanh⁻¹')
                .replace(/log2\(/g, 'log₂(')
                .replace(/exp\(/g, 'e^')
                .replace(/pow\(([^,]+),([^)]+)\)/g, '$1^$2');

            // Create step-by-step calculation display
            const substitutedFormula = calculationFormula
                .replace(/Math\./g, '')
                .replace(/\*/g, '×')
                .replace(/\//g, '÷')
                .replace(/\*\*/g, '^')
                .replace(/\(/g, ' (')
                .replace(/\)/g, ') ');

            preview.innerHTML = `
                <div class="formula-preview">
                    <div class="preview-section">
                        <div class="preview-label">Formula:</div>
                        <div class="preview-content formula-display">${displayFormula}</div>
                    </div>

                    <div class="preview-section">
                        <div class="preview-label">Given Values:</div>
                        <div class="preview-content preview-values">
                            ${Object.entries(sampleValues)
                                .map(([variable, value]) => 
                                    `<div class="value-item">
                                        ${variable} = ${value}
                                     </div>`
                                ).join('')}
                        </div>
                    </div>

                    <div class="preview-section">
                        <div class="preview-label">Solution:</div>
                        <div class="preview-content">
                            <div class="step">
                                1. Substitute values:
                                <div class="formula-step">${substitutedFormula}</div>
                            </div>
                            <div class="step">
                                2. Calculate:
                                <div class="result-step">= ${Number(result.toFixed(maxDecimals))}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Add enhanced CSS styles
            const style = document.createElement('style');
            style.textContent = `
                .formula-preview {
                    background: #f8f9fa;
                    border: 1px solid #dee2e6;
                    border-radius: 6px;
                    padding: 16px;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                }
                .preview-section {
                    margin-bottom: 16px;
                }
                .preview-section:last-child {
                    margin-bottom: 0;
                }
                .preview-label {
                    font-weight: 600;
                    color: #495057;
                    margin-bottom: 8px;
                }
                .preview-content {
                    background: white;
                    padding: 12px;
                    border-radius: 4px;
                    border: 1px solid #e9ecef;
                }
                .formula-display {
                    font-family: "Computer Modern", serif;
                    font-size: 1.1em;
                }
                .preview-values {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                    gap: 8px;
                }
                .value-item {
                    padding: 4px 8px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    font-family: monospace;
                }
                .step {
                    margin-bottom: 12px;
                }
                .formula-step, .result-step {
                    font-family: monospace;
                    margin-top: 4px;
                    padding: 8px 12px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    line-height: 1.5;
                }
                .result-step {
                    color: #0374b5;
                    font-weight: 600;
                }
            `;
            preview.appendChild(style);

        } catch (e) {
            preview.innerHTML = `
                <div style="color: #dc3545; padding: 12px; background: #fff5f5; border-radius: 4px; border: 1px solid #dc3545;">
                    <strong>Error:</strong> Invalid formula syntax or mathematical operation
                </div>
            `;
        }
    } else {
        preview.innerHTML = `
            <div style="color: #666; text-align: center; padding: 20px;">
                ${question.formula ? 
                    'Add variables to preview the calculation' : 
                    'Enter a formula above to see the preview'}
            </div>
        `;
    }
}

        // Global variables for link modal

        let currentSelection = null;
        let selectedRange = null;

        function insertLink() {
            if (!activeEditor) return;
            
            // Store the current selection
            const selection = window.getSelection();
            const selectedText = selection.toString().trim();
            currentSelection = selection;
            selectedRange = selection.rangeCount > 0 ? selection.getRangeAt(0) : null;
            
            // Show and setup the modal
            const modal = document.getElementById('linkModal');
            const linkTextInput = document.getElementById('linkText');
            const urlInputGroup = document.getElementById('urlInputGroup');
            
            // Reset the modal state
            linkTextInput.value = selectedText;
            urlInputGroup.style.display = 'none';
            document.getElementById('linkUrl').value = '';
            
            // Show the modal
            modal.style.display = 'block';
            
            // Focus on the appropriate input
            if (!selectedText) {
                linkTextInput.focus();
            }
        }

        function closeLinkModal() {
            const modal = document.getElementById('linkModal');
            modal.style.display = 'none';
            currentSelection = null;
            selectedRange = null;
        }

        function selectUrlType(type) {
            const urlInputGroup = document.getElementById('urlInputGroup');
            const linkUrl = document.getElementById('linkUrl');
            const linkText = document.getElementById('linkText').value.trim();
            
            urlInputGroup.style.display = 'block';
            
            switch(type) {
                case 'google':
                    linkUrl.value = 'https://www.google.com/search?q=' + 
                        encodeURIComponent(linkText.replace(/\s+/g, '+'));
                    break;
                case 'youtube':
                    linkUrl.value = 'https://www.youtube.com/watch?v=';
                    break;
                case 'wikipedia':
                    linkUrl.value = 'https://wikipedia.org/wiki/' + 
                        encodeURIComponent(linkText.replace(/\s+/g, '_'));
                    break;
                case 'custom':
                    linkUrl.value = 'https://';
                    break;
            }
            
            linkUrl.focus();
        }

        function confirmLink() {
            if (!activeEditor) return;
            
            const linkText = document.getElementById('linkText').value.trim();
            const linkUrl = document.getElementById('linkUrl').value.trim();
            
            if (!linkText || !linkUrl) {
                return; // Don't proceed if either field is empty
            }
            
            activeEditor.focus();
            
            if (selectedRange) {
                // Insert at selection
                const link = document.createElement('a');
                link.href = linkUrl;
                link.target = '_blank';
                link.textContent = linkText;
                selectedRange.deleteContents();
                selectedRange.insertNode(link);
            } else {
                // Insert at cursor position
                document.execCommand('insertHTML', false, 
                    `<a href="${linkUrl}" target="_blank">${linkText}</a>`);
            }
            
            closeLinkModal();
        }

        // Preview Functions
        function updatePreview() {
            const previewMode = document.getElementById('previewModeToggle') ? document.getElementById('previewModeToggle').value : 'student';
            window.previewMode = previewMode; // Make it accessible globally
            const previewContent = document.getElementById('previewContent');
            const quizTitle = document.getElementById('quizTitle').value || 'Untitled Quiz';
            const quizDescription = document.getElementById('quizDescription').innerHTML || '';
            const timeLimit = document.getElementById('timeLimit').value;

            const totalPoints = questions.reduce((sum, q) => sum + (q.points || 1), 0);

            // Get shuffle settings
            const shuffleQuestions = document.getElementById('shuffleQuestions').checked;
            const shuffleAnswers = document.getElementById('shuffleAnswers').checked;

            // Clone questions array for shuffling
            let previewQuestions = questions.map(q => {
                // Deep clone to avoid mutating original
                return JSON.parse(JSON.stringify(q));
            });

            // Shuffle questions if enabled
            if (shuffleQuestions) {
                for (let i = previewQuestions.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [previewQuestions[i], previewQuestions[j]] = [previewQuestions[j], previewQuestions[i]];
                }
            }

            // Shuffle choices for MCQ if enabled
            if (shuffleAnswers) {
                previewQuestions.forEach(q => {
                    if (q.type === 'multiple_choice' && Array.isArray(q.choices)) {
                        // Pair choices with their index
                        let choicesWithIndex = q.choices.map((choice, idx) => ({
                            choice,
                            idx
                        }));
                        for (let i = choicesWithIndex.length - 1; i > 0; i--) {
                            const j = Math.floor(Math.random() * (i + 1));
                            [choicesWithIndex[i], choicesWithIndex[j]] = [choicesWithIndex[j], choicesWithIndex[i]];
                        }
                        // Update choices and correctAnswers
                        q.choices = choicesWithIndex.map(obj => obj.choice);
                        if (Array.isArray(q.correctAnswers)) {
                            q.correctAnswers = q.correctAnswers.map(idx => choicesWithIndex.findIndex(obj => obj.idx === idx));
                        }
                    }
                });
            }

            let html = `
                <div class="preview-quiz">
                    <div class="preview-header">
                        <div class="preview-title">${quizTitle}</div>
                        ${quizDescription ? `<div style="margin-top: 8px; color: #666;">${quizDescription}</div>` : ''}
                        <div class="quiz-meta">
                            <span><strong>Time Limit:</strong> ${timeLimit} minutes</span>
                            <span><strong>Points:</strong> ${totalPoints}</span>
                            <span><strong>Questions:</strong> ${previewQuestions.length}</span>
                        </div>
                    </div>
                    
                    ${previewQuestions.map((question, index) => renderQuestionPreview(question, index + 1)).join('')}
                </div>
            `;

            if (questions.length === 0) {
                html = `
                    <div class="text-muted" style="text-align: center; padding: 60px 20px;">
                        <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                        <div style="font-size: 18px; margin-bottom: 8px;">Quiz Preview</div>
                        <div style="font-size: 14px;">Add questions to see how your quiz will look to students</div>
                    </div>
                `;
            }

            previewContent.innerHTML = html;
        }

        function renderQuestionPreview(question, number) {
            // Helper function to format math content
            function formatMathContent(content) {
            if (!content) return '';
            
            // First handle LaTeX-style math expressions (between $ signs)
            let formattedContent = content.replace(/\$([^$]+)\$/g, (match, equation) => {
                return `<span class="math-preview-equation">${equation}</span>`;
            });
            
            // Then format other mathematical expressions with proper symbols
            formattedContent = formatMathExpression(formattedContent);
            
            return formattedContent;
        }

    let contentHtml = '';
    if (question.type === 'multiple_choice') {
        contentHtml = `
            <div style="margin-bottom: 16px;">${formatMathContent(question.content) || 'Question text will appear here'}</div>
            ${question.choices.map((choice, index) => {
                let correctMark = '';
                if (window.previewMode === 'proctor' && question.correctAnswers && question.correctAnswers.includes(index)) {
                    correctMark = '<span style="color: #0374b5; font-weight: bold; margin-left: 8px;">✔ Correct</span>';
                }
                return `
                    <div style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                        <input type="${question.isMultipleAnswer ? 'checkbox' : 'radio'}" name="preview_${question.id}" disabled>
                        <span>${formatMathContent(choice) || `Choice ${index + 1}`}</span>
                        ${correctMark}
                    </div>
                `;
            }).join('')}
        `;
    } else if (question.type === 'fill_blank') {
        let text = question.blankText || question.content || 'Question with _ will appear here';
        let corrects = question.blanks || [];
        let idx = 0;
        
        // First render the question with blanks
        contentHtml = `<div class="fill-blank-question">`;
        contentHtml += text.replace(/_/g, () => {
            idx++;
            return `<input type="text" 
                    style="display: inline-block; min-width: 120px; margin: 0 4px; 
                    padding: 2px 6px; border: none; border-bottom: 2px solid #333;" 
                    placeholder="Blank ${idx}" 
                    disabled>`;
        });
        contentHtml += `</div>`;
        
        // If in proctor view, show answers with case sensitivity indicators
        if (window.previewMode === 'proctor') {
            contentHtml += `
                <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e9ecef;">
                    <div style="font-weight: 500; margin-bottom: 12px; color: #0374b5;">
                        Correct Answers:
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
                        ${corrects.map((answers, index) => {
                            const isCaseSensitive = question.caseSensitive && 
                                                question.caseSensitive[index];
                            const answerArray = Array.isArray(answers) ? answers : [answers];
                            
                            return `
                                <div style="background: #f8f9fa; padding: 12px; 
                                        border-radius: 4px; border: 1px solid #e9ecef;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div style="color: #666; font-size: 13px;">Blank ${index + 1}</div>
                                    </div>
                                    <div style="margin-top: 8px;">
                                        ${answerArray.map((answer, ansIdx) => {
                                            const isAnswerCaseSensitive = question.caseSensitive && 
                                                                        question.caseSensitive[index] && 
                                                                        question.caseSensitive[index][ansIdx];
                                            return `
                                                <div style="margin-bottom: 8px;">
                                                    <div style="display: flex; justify-content: space-between; 
                                                            align-items: center; gap: 8px;">
                                                        <div style="font-weight: ${ansIdx === 0 ? '600' : '400'}; 
                                                                color: ${ansIdx === 0 ? '#0374b5' : '#333'};">
                                                            ${ansIdx === 0 ? 'Primary Answer:' : 'Alternate Answer:'} 
                                                            ${answer || ''}
                                                        </div>
                                                        ${isAnswerCaseSensitive ? `
                                                            <span style="font-size: 11px; background: #e3f2fd; 
                                                                    color: #0277bd; padding: 2px 6px; 
                                                                    border-radius: 3px;">
                                                                Case Sensitive
                                                            </span>
                                                        ` : ''}
                                                    </div>
                                                </div>
                                            `;
                                        }).join('')}
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }
    } else if (question.type === 'formula') {
        const hasVariables = Object.keys(question.variables || {}).length > 0;
        if (question.formula && hasVariables) {
            const sampleValues = {};
            
            // Generate sample values for variables with proper decimal handling
            Object.keys(question.variables).forEach(varName => {
                const variable = question.variables[varName];
                const min = parseFloat(variable.min) || 0;
                const max = parseFloat(variable.max) || 10;
                const step = parseFloat(variable.step) || 1;
                const decimals = parseInt(variable.decimals) || 0;
                
                // Calculate value with proper decimal handling
                const range = max - min;
                const validStep = Math.min(step, range);
                const possibleSteps = Math.floor(range / validStep);
                const randomStepCount = Math.floor(Math.random() * possibleSteps);
                const rawValue = min + (randomStepCount * validStep);
                const formattedValue = Number(rawValue.toFixed(decimals));
                
                sampleValues[varName] = formattedValue;
            });

            // Replace variables in content with their values
            let displayContent = question.content || '';
            Object.keys(sampleValues).forEach(varName => {
                const regex = new RegExp(`\\[${varName}\\]`, 'g');
                displayContent = displayContent.replace(regex, `<strong>${sampleValues[varName]}</strong>`);
            });

            if (window.previewMode === 'proctor') {
            // Show formula and answer for proctors
                try {
                    let calculationFormula = question.formula
                        // Constants
                        .replace(/pi/g, 'Math.PI')
                        .replace(/e(?!\w)/g, 'Math.E')
                        
                        // Basic trigonometric
                        .replace(/sin\(/g, 'Math.sin(')
                        .replace(/cos\(/g, 'Math.cos(')
                        .replace(/tan\(/g, 'Math.tan(')
                        
                        // Inverse trigonometric
                        .replace(/asin\(/g, 'Math.asin(')
                        .replace(/acos\(/g, 'Math.acos(')
                        .replace(/atan\(/g, 'Math.atan(')
                        
                        // Hyperbolic
                        .replace(/sinh\(/g, 'Math.sinh(')
                        .replace(/cosh\(/g, 'Math.cosh(')
                        .replace(/tanh\(/g, 'Math.tanh(')
                        
                        // Inverse hyperbolic
                        .replace(/asinh\(/g, 'Math.asinh(')
                        .replace(/acosh\(/g, 'Math.acosh(')
                        .replace(/atanh\(/g, 'Math.atanh(')
                        
                        // Logarithmic
                        .replace(/log\(/g, 'Math.log10(')
                        .replace(/ln\(/g, 'Math.log(')
                        .replace(/log2\(/g, 'Math.log2(')
                        
                        // Power and root
                        .replace(/sqrt\(/g, 'Math.sqrt(')
                        .replace(/cbrt\(/g, 'Math.cbrt(')
                        .replace(/exp\(/g, 'Math.exp(')
                        .replace(/pow\(/g, 'Math.pow(')
                        
                        // Rounding
                        .replace(/floor\(/g, 'Math.floor(')
                        .replace(/ceil\(/g, 'Math.ceil(')
                        .replace(/round\(/g, 'Math.round(')
                        
                        // Other mathematical functions
                        .replace(/abs\(/g, 'Math.abs(')
                        .replace(/sign\(/g, 'Math.sign(')
                        .replace(/\^/g, '**');

                    // Replace variables with their values
                    Object.keys(sampleValues).forEach(varName => {
                        calculationFormula = calculationFormula.replace(
                            new RegExp(`\\b${varName}\\b`, 'g'), 
                            `(${sampleValues[varName]})`
                        );
                    });

                    const result = eval(calculationFormula);
                    const maxDecimals = Math.max(3, ...Object.values(question.variables)
                        .map(v => v.decimals || 0));

                    // Format the formula for display
                    const displayFormula = question.formula
                        .replace(/\*/g, '×')
                        .replace(/\//g, '÷')
                        .replace(/\^/g, '^')
                        .replace(/pi/g, 'π')
                        .replace(/sqrt\(/g, '√(')
                        .replace(/cbrt\(/g, '∛(')
                        .replace(/asin/g, 'sin⁻¹')
                        .replace(/acos/g, 'cos⁻¹')
                        .replace(/atan/g, 'tan⁻¹')
                        .replace(/asinh/g, 'sinh⁻¹')
                        .replace(/acosh/g, 'cosh⁻¹')
                        .replace(/atanh/g, 'tanh⁻¹');

                    contentHtml = `
                        <div style="margin-bottom: 16px;">${displayContent}</div>
                        <div>
                            <strong>Formula:</strong> ${displayFormula}<br>
                            <div style='color: #0374b5; font-weight: bold; margin-top: 8px;'>
                                ✔ Correct Answer: ${Number(result.toFixed(maxDecimals))}
                            </div>
                        </div>
                    `;
                } catch (e) {
                    contentHtml = `
                        <div style="margin-bottom: 16px;">${displayContent}</div>
                        <div style='color: #d32f2f; font-weight: bold;'>
                            Invalid formula or mathematical operation
                        </div>
                    `;
                }
            } else {
            // Student view - only show question and input field
            contentHtml = `
                <div style="margin-bottom: 16px;">${displayContent}</div>
                <div>
                    <input type="text" 
                        style="margin-top: 8px; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px;" 
                        placeholder="Enter your answer" 
                        disabled>
                </div>
            `;
        }
        } else {
            contentHtml = '<div>Formula question setup incomplete</div>';
        }
    }
    return `
        <div class="preview-question">
            <div class="preview-q-header">
                <div class="preview-q-number">
                    <strong>Question ${number}</strong>
                    ${question.title ? `<div style="font-weight: normal; margin-top: 4px;">${question.title}</div>` : ''}
                </div>
                <div class="preview-points">${question.points || 1} ${(question.points || 1) === 1 ? 'pt' : 'pts'}</div>
            </div>
            <div>${contentHtml}</div>
        </div>
    `;
}

const mathPreviewStyles = `
    .math-preview-equation {
        font-family: "Computer Modern", "Times New Roman", serif;
        color: #0374b5;
        font-style: italic;
    }
    
    .fill-blank-question .math-preview-equation {
        display: inline-block;
        vertical-align: middle;
        margin: 0 2px;
    }
`;

// Add the styles to the document
const mathPreviewStyleSheet = document.createElement("style");
mathPreviewStyleSheet.textContent = mathPreviewStyles;
document.head.appendChild(mathPreviewStyleSheet);

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set focus on quiz title
            document.getElementById('quizTitle').focus();
            // Hide floating button by default unless questions tab is active
            var btn = document.getElementById('floatingNewQuestionBtn');
            if (!document.getElementById('questions').classList.contains('active')) {
                btn.classList.add('hide');
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'previewModeToggle') {
                updatePreview();
            }
        });

        function saveToQuestionBank(questionId) {
    const question = questions.find(q => q.id === questionId);
    if (!question) return;

    // Add metadata including course_id
    const questionToSave = {
        ...question,
        savedDate: new Date().toISOString(),
        courseId: window.ann_course_id,
        savedBy: {
            userId: window.user_id,
            name: `${window.first_name} ${window.last_name}`
        }
    };

    // Load existing question bank
    fetch('question_bank.json')
        .then(response => response.json())
        .catch(() => ({ questions: [] })) // Initialize if file doesn't exist
        .then(data => {
            const questions = data.questions || [];
            questions.push(questionToSave);

            // Save updated question bank
            return fetch('save_question_bank', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ questions })
            });
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Question saved to bank successfully!');
            } else {
                throw new Error(data.error || 'Failed to save question');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to save question: ' + error.message);
        });
}

document.addEventListener('DOMContentLoaded', function() {
    // Add dropdown button to the question bank header
    const questionBankHeader = document.querySelector('.question-bank-header');
    if (questionBankHeader) {
        
    }
});

function toggleQuestionDropdown(event) {
    const dropdown = document.getElementById('questionTypeDropdown');
    dropdown.classList.toggle('show');
    
    if (dropdown.classList.contains('show')) {
        // Position dropdown below the button
        const button = event.target;
        const rect = button.getBoundingClientRect();
        dropdown.style.position = 'absolute';
        dropdown.style.top = `${rect.bottom + 5}px`;
        dropdown.style.right = '20px';
        dropdown.style.left = 'auto';
    }
    
    event.stopPropagation();
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('questionTypeDropdown');
    const button = document.querySelector('.dropdown-btn');
    
    if (!dropdown.contains(event.target) && event.target !== button) {
        dropdown.classList.remove('show');
    }
});

function loadFromQuestionBank() {
    const currentCourseId = window.ann_course_id;
    
    // Remove any existing modals first
    const existingModal = document.querySelector('.modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create and show modal
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.animation = 'modalFadeIn 0.2s';
    modal.style.display = 'block';
    modal.innerHTML = `
        <div class="modal-content" style="width: 90%; max-width: 1200px; max-height: 85vh; margin: 30px auto; display: flex; flex-direction: column;">
            <div class="modal-header" style="flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; padding: 15px 20px;">
                <h3 style="margin: 0;">Question Bank</h3>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button onclick="refreshQuestionBank()" class="btn btn-outline-secondary btn-sm" style="display: flex; align-items: center; gap: 4px;">
                        <span style="font-size: 16px;">🔄</span> Refresh
                    </button>
                    <button onclick="closeQuestionBankModal()" class="close-button">&times;</button>
                </div>
            </div>
            <div class="modal-body" style="padding: 20px; flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 200px;">
                <div class="filters" style="margin-bottom: 20px; flex-shrink: 0;">
                    <input type="text" 
                           class="form-control" 
                           id="bankSearchInput"
                           placeholder="Search questions..." 
                           style="margin-bottom: 12px;">
                    <select class="form-control" id="bankTypeFilter">
                        <option value="">All Types</option>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="fill_blank">Fill in Blank</option>
                        <option value="formula">Formula</option>
                    </select>
                </div>
                <div id="questionBankList" style="flex: 1; overflow-y: auto; border: 1px solid #e9ecef; border-radius: 6px; background: #fff;"></div>
            </div>
            <div class="modal-footer" style="flex-shrink: 0; border-top: 1px solid #e9ecef; padding: 15px 20px; background: #fff;">
                <button class="btn btn-outline" onclick="closeQuestionBankModal()">Cancel</button>
                <button class="btn btn-primary" onclick="importSelectedQuestions()">Import Selected</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    // Add click event listener to modal backdrop
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeQuestionBankModal();
        }
    });

    // Add escape key listener
    document.addEventListener('keydown', handleModalEscape);

    // Add event listeners for search and filter
    const searchInput = modal.querySelector('#bankSearchInput');
    const typeFilter = modal.querySelector('#bankTypeFilter');
    
    if (searchInput) {
        searchInput.addEventListener('input', filterBankQuestions);
    }
    if (typeFilter) {
        typeFilter.addEventListener('change', filterBankQuestions);
    }

    // Add close button listener
    const closeButton = modal.querySelector('.close-button');
    if (closeButton) {
        closeButton.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeQuestionBankModal();
        });
    }

    // Load questions
    loadBankQuestions(currentCourseId);
}

function loadBankQuestions(currentCourseId) {
    // Add cache-busting query parameter
    fetch('question_bank.json?' + new Date().getTime())
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load question bank');
            }
            return response.json();
        })
        .then(data => {
            if (!data.questions) {
                console.warn('No questions found in question bank');
                data.questions = [];
            }

            // Filter questions for current course, handle potential type mismatches
            window.bankQuestions = data.questions.filter(q => {
                const qCourseId = q.courseId?.toString() || '';
                const currentId = currentCourseId?.toString() || '';
                return qCourseId === currentId;
            });

            console.log(`Loaded ${window.bankQuestions.length} questions for course ${currentCourseId}`);
            renderBankQuestions(window.bankQuestions);
        })
        .catch(error => {
            console.error('Error loading question bank:', error);
            document.getElementById('questionBankList').innerHTML = `
                <div class="error-state">
                    <div style="text-align: center; padding: 20px;">
                        <div style="color: #dc3545; margin-bottom: 10px;">Failed to load questions</div>
                        <button onclick="loadBankQuestions(${currentCourseId})" class="btn btn-outline-primary btn-sm">
                            Try Again
                        </button>
                    </div>
                </div>`;
        });
}

function renderBankQuestions(questions) {
    const container = document.getElementById('questionBankList');

    function formatMathContent(content) {
            if (!content) return '';
            
            // First handle LaTeX-style math expressions (between $ signs)
            let formattedContent = content.replace(/\$([^$]+)\$/g, (match, equation) => {
                return `<span class="math-preview-equation">${equation}</span>`;
            });
            
            // Then format other mathematical expressions with proper symbols
            formattedContent = formatMathExpression(formattedContent);
            
            return formattedContent;
        }
    
    if (questions.length > 0) {
        container.innerHTML = questions.map(q => `
            <div class="question-bank-item" data-type="${q.type}">
                <label class="question-bank-label">
                    <input type="checkbox" 
                        value="${q.id}" 
                        data-timestamp="${new Date(q.savedDate).getTime()}"
                        data-course-id="${q.courseId}"
                        style="margin-top: 4px;">
                    <div class="question-bank-content">
                        <div class="question-bank-title">
                            ${q.title || 'Untitled Question'}
                        </div>
                        <div class="question-bank-meta">
                            <span class="badge ${q.type}">${getTypeName(q.type)}</span>
                            ${q.ilo_number ? `<span class="badge ilo">ILO ${q.ilo_number}</span>` : ''}
                            <span class="date">Saved: ${new Date(q.savedDate).toLocaleDateString()}</span>
                        </div>
                        <div class="question-preview">
                            ${formatMathContent(q.content) || ''}
                        </div>
                    </div>
                </label>
            </div>
        `).join('');
    } else {
        container.innerHTML = `
            <div class="empty-state">
                <div style="text-align: center; padding: 40px 20px;">
                    <div style="font-size: 48px; margin-bottom: 16px;">📚</div>
                    <div style="font-size: 16px; margin-bottom: 8px;">
                        No questions found
                    </div>
                </div>
            </div>`;
    }
}

function filterBankQuestions() {
    const searchTerm = document.getElementById('bankSearchInput').value.toLowerCase();
    const typeFilter = document.getElementById('bankTypeFilter').value;
    
    const filteredQuestions = window.bankQuestions.filter(question => {
        const matchesSearch = (
            (question.title || '').toLowerCase().includes(searchTerm) ||
            (question.content || '').toLowerCase().includes(searchTerm)
        );
        const matchesType = !typeFilter || question.type === typeFilter;
        return matchesSearch && matchesType;
    });
    
    renderBankQuestions(filteredQuestions);
}

function refreshQuestionBank() {
    const modal = document.querySelector('.modal');
    if (modal && window.ann_course_id) {
        loadBankQuestions(window.ann_course_id);
    }
}

function closeQuestionBankModal() {
    const modal = document.querySelector('.modal');
    if (!modal) return;

    // Remove event listeners before closing
    const searchInput = modal.querySelector('#bankSearchInput');
    const typeFilter = modal.querySelector('#bankTypeFilter');
    if (searchInput) {
        searchInput.removeEventListener('input', filterBankQuestions);
    }
    if (typeFilter) {
        typeFilter.removeEventListener('change', filterBankQuestions);
    }

    // Add fade out animation
    modal.style.animation = 'modalFadeOut 0.2s';
    modal.querySelector('.modal-content').style.animation = 'modalSlideOut 0.2s';

    // Clean up
    setTimeout(() => {
        modal.remove();
        // Clear any global state
        window.bankQuestions = null;
        
        // Remove any lingering event listeners
        document.removeEventListener('keydown', handleModalEscape);
    }, 200);
}

// Handle Escape key
function handleModalEscape(e) {
    if (e.key === 'Escape') {
        closeQuestionBankModal();
    }
}

function importSelectedQuestions() {
    const currentCourseId = window.ann_course_id;
    const selected = document.querySelectorAll('#questionBankList input[type="checkbox"]:checked');
    const selectedItems = Array.from(selected).map(cb => ({
        id: cb.value,
        timestamp: cb.dataset.timestamp,
        index: cb.dataset.index
    }));

    if (selectedItems.length === 0) {
        alert('Please select at least one question to import');
        return;
    }

    fetch('question_bank.json?' + new Date().getTime())
        .then(response => response.json())
        .then(data => {
            // Ensure both data.questions exists and selectedItems is not empty 
            if (!data.questions || !Array.isArray(data.questions) || selectedItems.length === 0) {
                throw new Error('No questions selected or invalid question data');
            }

            // Filter questions by matching multiple properties to ensure uniqueness
            const selectedQuestions = selectedItems.map(item => {
                const matchingQuestion = data.questions.find(q => 
                    q.id === item.id && 
                    new Date(q.savedDate).getTime().toString() === item.timestamp &&
                    q.courseId === currentCourseId
                );
                return matchingQuestion;
            }).filter(q => q !== undefined); // Remove any questions that weren't found

            // Verify we found all selected questions
            if (selectedQuestions.length !== selectedItems.length) {
                console.warn('Some selected questions were not found in the question bank');
            }

            selectedQuestions.forEach(q => {
                // Generate new ID for imported question
                const newQ = {
                    ...q,
                    id: `question_${++questionCounter}_${Date.now()}` // Add timestamp to ensure uniqueness
                };
                questions.push(newQ);
                renderQuestion(newQ);
            });

            // Hide empty state if there are questions
            const emptyState = document.getElementById('emptyState');
            if (emptyState && questions.length > 0) {
                emptyState.style.display = 'none';
            }

            // Clear and re-render questions list
            const questionsList = document.getElementById('questionsList');
            questionsList.innerHTML = ''; // Clear existing content including empty state
            questions.forEach(question => {
                renderQuestion(question);
            });

            closeQuestionBankModal();
            updatePreview();
            alert(`Successfully imported ${selectedQuestions.length} question(s)`);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to import questions: ' + error.message);
        });
}

            // Function to load a quiz for editing
            function loadQuiz(quizId) {
                fetch(`load_quiz?id=${quizId}`)
                    .then(response => response.json())
                    .then(data => {
                        // Clear existing questions
                        questions = [];
                        
                        // Load the questions and ensure they have proper IDs
                        if (data.questions && Array.isArray(data.questions)) {
                            questions = data.questions.map((q, index) => {
                                // If the question doesn't have an ID, generate one
                                if (!q.id) {
                                    q.id = `question_${index + 1}`;
                                }
                                return q;
                            });
                        }
                        
                        // Update the question counter based on loaded questions
                        updateQuestionCounter();
                        if (data.success) {
                            const quiz = data.quiz;
                            
                            // Store the file path ID and UUID separately
                            window.existingQuizId = quizId;            // For file path/URL
                            window.existingQuizUuid = quiz.uuid || quizId;  // For internal UUID

                            // Set basic info 
                            document.getElementById('quizId').value = quiz.id;
                            document.getElementById('quizTitle').value = quiz.title;
                            document.getElementById('quizDescription').innerHTML = quiz.description;
                            document.getElementById('quizType').value = quiz.type;
                            document.getElementById('timeLimit').value = quiz.options.timeLimit;
                            
                            // Set quiz options
                            document.getElementById('attempts').value = quiz.options.attempts;
                            document.getElementById('showOneQuestion').checked = quiz.options.showOneQuestion;
                            document.getElementById('shuffleQuestions').checked = quiz.options.shuffleQuestions;
                            document.getElementById('shuffleAnswers').checked = quiz.options.shuffleAnswers;
                            document.getElementById('seeResponses').checked = quiz.options.seeResponses;
                            document.getElementById('seeResponsesTiming').value = quiz.options.seeResponsesTiming;
                            document.getElementById('seeCorrectAnswers').checked = quiz.options.seeCorrectAnswers;
                            
                            // Set dates and times
                            if (quiz.options.showCorrectAnswersDate) {
                                document.getElementById('showCorrectAnswersDate').value = quiz.options.showCorrectAnswersDate;
                                document.getElementById('showCorrectAnswersTime').value = quiz.options.showCorrectAnswersTime;
                            }
                            if (quiz.options.hideCorrectAnswersDate) {
                                document.getElementById('hideCorrectAnswersDate').value = quiz.options.hideCorrectAnswersDate;
                                document.getElementById('hideCorrectAnswersTime').value = quiz.options.hideCorrectAnswersTime;
                            }
                            if (quiz.options.dueDate) {
                                document.getElementById('dueDateDate').value = quiz.options.dueDate;
                                document.getElementById('dueDateTime').value = quiz.options.dueTime;
                            }
                            if (quiz.options.availableFromDate) {
                                document.getElementById('availableFromDate').value = quiz.options.availableFromDate;
                                document.getElementById('availableFromTime').value = quiz.options.availableFromTime;
                            }
                            if (quiz.options.availableUntilDate) {
                                document.getElementById('availableUntilDate').value = quiz.options.availableUntilDate;
                                document.getElementById('availableUntilTime').value = quiz.options.availableUntilTime;
                            }
                            
                            // Load questions
                            questions = quiz.questions;
                            const questionsList = document.getElementById('questionsList');
                            questionsList.innerHTML = ''; // Clear existing questions
                            questions.forEach(question => {
                                renderQuestion(question);
                            });
                            
                            // Update empty state visibility
                            const emptyState = document.getElementById('emptyState');
                            if (emptyState) {
                                emptyState.style.display = questions.length === 0 ? 'block' : 'none';
                            }
                            
                            // Update preview if it's visible
                            const previewTab = document.getElementById('preview');
                            if (previewTab && previewTab.classList.contains('active')) {
                                updatePreview();
                            }
                        } else {
                            alert('Failed to load quiz: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to load quiz: ' + error.message);
                    });
            }

            // Check URL for quiz UUID on page load
            document.addEventListener('DOMContentLoaded', function() {
                const urlParams = new URLSearchParams(window.location.search);
                const quizId = urlParams.get('id');
                if (quizId) {
                    loadQuiz(quizId);
                }
            });

            document.getElementById('close_quiz').onclick = function() {
                window.history.back();
            };

            // Function to save quiz data to JSON
            function generateUUID() {
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                    var r = Math.random() * 16 | 0,
                        v = c == 'x' ? r : (r & 0x3 | 0x8);
                    return v.toString(16);
                });
            }

            function saveQuizToJson() {
                // Get quiz metadata
                const urlParams = new URLSearchParams(window.location.search);
                
                // Handle quiz identification
                let quizId;
                let quizUuid;
                
                if (window.existingQuizId) {
                    // If we loaded an existing quiz, use its original file ID
                    quizId = window.existingQuizId;
                    quizUuid = window.existingQuizUuid;
                } else {
                    // For new quizzes, generate both values
                    quizId = generateUUID();
                    quizUuid = quizId;
                    window.existingQuizId = quizId;
                    window.existingQuizUuid = quizUuid;
                }
                
                document.getElementById('quizId').value = quizUuid;
                const quizTitle = document.getElementById('quizTitle').value || 'Untitled Quiz';
                const quizDescription = document.getElementById('quizDescription').innerHTML || '';
                const quizType = document.getElementById('quizType').value || 'practice';
                const timeLimit = document.getElementById('timeLimit').value || 0;
                
                // Get quiz options
                const quizOptions = {
                    timeLimit: parseInt(timeLimit),
                    attempts: document.getElementById('attempts').value,
                    showOneQuestion: document.getElementById('showOneQuestion').checked,
                    shuffleQuestions: document.getElementById('shuffleQuestions').checked,
                    shuffleAnswers: document.getElementById('shuffleAnswers').checked,
                    seeResponses: document.getElementById('seeResponses').checked,
                    seeResponsesTiming: document.getElementById('seeResponsesTiming').value,
                    seeCorrectAnswers: document.getElementById('seeCorrectAnswers').checked,
                    showCorrectAnswersDate: document.getElementById('showCorrectAnswersDate').value,
                    showCorrectAnswersTime: document.getElementById('showCorrectAnswersTime').value,
                    hideCorrectAnswersDate: document.getElementById('hideCorrectAnswersDate').value,
                    hideCorrectAnswersTime: document.getElementById('hideCorrectAnswersTime').value,
                    dueDate: document.getElementById('dueDateDate').value,
                    dueTime: document.getElementById('dueDateTime').value,
                    availableFromDate: document.getElementById('availableFromDate').value,
                    availableFromTime: document.getElementById('availableFromTime').value,
                    availableUntilDate: document.getElementById('availableUntilDate').value,
                    availableUntilTime: document.getElementById('availableUntilTime').value
                };

                const questionsWithIlo = questions.map(question => {
                    const ilo = window.COURSE_ILOS.find(ilo => ilo.id == question.ilo_id);
                    return {
                        ...question,
                        ilo_id: question.ilo_id || null,
                        ilo_number: ilo ? ilo.ilo_number : null,
                        ilo_description: ilo ? ilo.ilo_description : null
                    };
                });
                
                // Prepare quiz data
                const quizData = {
                    id: quizId,        // File path ID
                    uuid: quizUuid,    // Internal UUID
                    title: quizTitle,
                    description: quizDescription,
                    type: quizType,
                    options: quizOptions,
                    questions: questionsWithIlo,
                    courseId: window.COURSE_ID,
                    createdAt: window.existingQuizId ? null : new Date().toISOString(), // Only set for new quizzes
                    lastModified: new Date().toISOString(),
                    is_published: 0  // Default to unpublished
                };
                
                // Convert to JSON string with formatting
                const jsonData = JSON.stringify(quizData, null, 2);
                
                // Show loading state
                const saveButton = document.querySelector('button[onclick="saveQuizToJson()"]');
                const originalText = saveButton.innerHTML;
                saveButton.innerHTML = '⏳ Saving...';
                saveButton.disabled = true;

                // Send the data to the server
                fetch('save_quiz_edit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: jsonData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        alert('Quiz saved successfully!');
                        window.location.href = 'assessments';
                    } else {
                        throw new Error(data.error || 'Failed to save quiz');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to save quiz: ' + error.message);
                })
                .finally(() => {
                    // Restore button state
                    saveButton.innerHTML = originalText;
                    saveButton.disabled = false;
                });
            }
        