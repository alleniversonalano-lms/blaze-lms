 <!-- Load TinyMCE -->
 <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

 <style>
     body {
         background: #f9f9f9;
         font-family: "Lato", "Helvetica Neue", Helvetica, Arial, sans-serif;
     }

     .canvas-settings {
         max-width: 900px;
         margin: 2rem auto;
         background: #fff;
         border: 1px solid #ddd;
         border-radius: 6px;
         overflow: hidden;
         box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
     }

     .settings-group {
         border-bottom: 1px solid #e5e5e5;
         padding: 1rem 1.5rem;
     }

     .settings-header {
         cursor: pointer;
         font-weight: 600;
         display: flex;
         justify-content: space-between;
         align-items: center;
         color: #2d3b45;
         font-size: 1rem;
     }

     .settings-header:hover {
         background: #f6f9fc;
     }

     .settings-body {
         margin-top: 1rem;
     }

     .form-row {
         display: flex;
         align-items: flex-start;
         margin-bottom: 1rem;
     }

     .form-label-col {
         flex: 0 0 250px;
         font-weight: 500;
         padding-top: 0.25rem;
         color: #2d3b45;
     }

     .form-field-col {
         flex: 1;
     }

     .collapsed .settings-body {
         display: none;
     }

     .toggle-icon {
         font-size: 0.85rem;
         color: #666;
     }

     .btn-primary {
         background-color: #2d3b45;
         border-color: #2d3b45;
     }

     .btn-primary:hover {
         background-color: #1a252f;
         border-color: #1a252f;
     }

     .datetime-row {
         display: flex;
         gap: 8px;
         align-items: center;
     }

     .datetime-row input[type="date"],
     .datetime-row input[type="time"] {
         width: auto;
     }
 </style>


 <div class="canvas-settings tab-pane active" id="general">
     <!-- Title Section -->
     <div class="settings-group">
         <div class="form-row">
             <div class="form-label-col">Quiz Title</div>
             <div class="form-field-col">
                 <input type="text" class="form-control" name="quiz_title" placeholder="e.g. Quiz 1, Final Exam">
             </div>
         </div>
         <div class="form-row">
             <div class="form-label-col">Quiz Instructions</div>
             <div class="form-field-col">
                 <textarea id="quiz_instructions" name="quiz_instructions"></textarea>
             </div>
         </div>
         <div class="form-row">
             <div class="form-label-col">Quiz Type</div>
             <div class="form-field-col">
                 <select name="quiz_type" class="form-select">
                     <option value="practice">Practice Quiz</option>
                     <option value="graded">Graded Quiz</option>
                 </select>
             </div>
         </div>
     </div>

     <!-- Options -->
     <div class="settings-group collapsible">
         <div class="settings-header">
             Options <span class="toggle-icon">▼</span>
         </div>
         <div class="settings-body">
             <div class="form-row">
                 <div class="form-label-col">Shuffle Answers</div>
                 <div class="form-field-col">
                     <input type="checkbox" name="shuffle_answers"> Shuffle answer choices
                 </div>
             </div>
             <div class="form-row">
                 <div class="form-label-col">Time Limit</div>
                 <div class="form-field-col">
                     <input type="checkbox" id="time_limit_enable">
                     <input type="number" name="time_limit" class="form-control d-inline-block" style="width:120px" placeholder="Minutes" disabled>
                 </div>
             </div>
             <div class="form-row">
                 <div class="form-label-col">Multiple Attempts</div>
                 <div class="form-field-col">
                     <input type="checkbox" id="multi_attempts_enable">
                     <div class="mt-2" id="multi_attempts_fields" style="display:none;">
                         <label>Score to Keep:</label>
                         <select name="score_to_keep" class="form-select mb-2">
                             <option value="highest">Highest</option>
                             <option value="latest">Latest</option>
                             <option value="average">Average</option>
                         </select>
                         <label>Allowed Attempts:</label>
                         <input type="number" name="allowed_attempts" class="form-control" min="1">
                     </div>
                 </div>
             </div>
             <div class="form-row">
                 <div class="form-label-col">Show One Question at a Time</div>
                 <div class="form-field-col">
                     <input type="checkbox" id="one_question">
                     <label class="ms-2">
                         <input type="checkbox" id="lock_questions" disabled> Lock questions after answering
                     </label>
                 </div>
             </div>
         </div>
     </div>

     <!-- Responses -->
     <div class="settings-group collapsible">
         <div class="settings-header">
             Responses <span class="toggle-icon">▼</span>
         </div>
         <div class="settings-body">
             <div class="form-row">
                 <div class="form-label-col">Let Students See Their Responses</div>
                 <div class="form-field-col">
                     <input type="checkbox" id="see_responses">
                     <div id="see_responses_options" style="display:none;" class="mt-2">
                         <input type="checkbox" name="only_once"> Only once after each attempt<br>
                         <input type="checkbox" name="see_correct"> Let students see correct answers
                         <div class="mt-2">
                             <label>Show correct answers at:</label>
                             <div class="datetime-row">
                                 <input type="date" name="show_correct_date">
                                 <input type="time" name="show_correct_time">
                             </div>
                             <label class="mt-2">Hide correct answers at:</label>
                             <div class="datetime-row">
                                 <input type="date" name="hide_correct_date">
                                 <input type="time" name="hide_correct_time">
                             </div>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
     </div>

     <!-- Assign -->
     <div class="settings-group collapsible">
         <div class="settings-header">
             Assign <span class="toggle-icon">▼</span>
         </div>
         <div class="settings-body">
             <div class="form-row">
                 <div class="form-label-col">Due Date</div>
                 <div class="form-field-col datetime-row">
                     <input type="date" name="due_date">
                     <input type="time" name="due_time">
                 </div>
             </div>
             <div class="form-row">
                 <div class="form-label-col">Available From</div>
                 <div class="form-field-col datetime-row">
                     <input type="date" name="available_from_date">
                     <input type="time" name="available_from_time">
                 </div>
             </div>
             <div class="form-row">
                 <div class="form-label-col">Until</div>
                 <div class="form-field-col datetime-row">
                     <input type="date" name="until_date">
                     <input type="time" name="until_time">
                 </div>
             </div>
         </div>
     </div>

     <!-- Save -->
     <div class="p-3">
         <button class="btn btn-primary">Save Quiz</button>
     </div>
 </div>

 <script>
     document.addEventListener("DOMContentLoaded", function() {
         // TinyMCE Init
         tinymce.init({
             selector: '#quiz_instructions',
             height: 300,
             menubar: 'file edit view insert format tools table help',
             plugins: [
                 'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                 'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                 'insertdatetime', 'media', 'table', 'help', 'wordcount'
             ],
             toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | ' +
                 'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ' +
                 'link image media table | removeformat | code fullscreen help',
             branding: false
         });

         // Collapsible toggle
         document.querySelectorAll('.settings-header').forEach(header => {
             header.addEventListener('click', () => {
                 header.parentElement.classList.toggle('collapsed');
                 header.querySelector('.toggle-icon').textContent =
                     header.parentElement.classList.contains('collapsed') ? '►' : '▼';
             });
         });

         // Enable time limit
         document.getElementById('time_limit_enable').addEventListener('change', function() {
             document.querySelector('input[name="time_limit"]').disabled = !this.checked;
         });

         // Multiple attempts toggle
         document.getElementById('multi_attempts_enable').addEventListener('change', function() {
             document.getElementById('multi_attempts_fields').style.display = this.checked ? 'block' : 'none';
         });

         // One question at a time
         document.getElementById('one_question').addEventListener('change', function() {
             document.getElementById('lock_questions').disabled = !this.checked;
         });

         // See responses toggle
         document.getElementById('see_responses').addEventListener('change', function() {
             document.getElementById('see_responses_options').style.display = this.checked ? 'block' : 'none';
         });
     });
 </script>