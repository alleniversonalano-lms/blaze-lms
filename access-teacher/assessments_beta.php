<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Creator - Canvas LMS Style</title>
    <style>
        .floating-new-question-btn {
            /* ...existing styles... */
        }

        .floating-new-question-btn.hide {
            display: none !important;
        }

        /* Floating New Question Button */
        .floating-new-question-btn {
            position: fixed;
            right: 32px;
            bottom: 32px;
            z-index: 2000;
            background: #0374b5;
            color: white;
            border: none;
            border-radius: 50px;
            box-shadow: 0 4px 16px rgba(3, 116, 181, 0.15);
            padding: 16px 28px;
            font-size: 18px;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s, box-shadow 0.2s;
        }

        .floating-new-question-btn:hover {
            background: #025a8c;
            box-shadow: 0 6px 24px rgba(3, 116, 181, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Lato", "Helvetica Neue", Helvetica, Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            font-size: 14px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
        }

        .header {
            background: #394b59;
            color: white;
            padding: 1rem 2rem;
            border-bottom: 3px solid #2d3b47;
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 400;
        }

        .tabs {
            display: flex;
            background: #e6eaed;
            border-bottom: 1px solid #c7cdd1;
        }

        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            color: #555;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            font-family: inherit;
        }

        .tab:hover {
            background: #d1d7da;
        }

        .tab.active {
            background: white;
            color: #0374b5;
            border-bottom-color: #0374b5;
            font-weight: 500;
        }

        .tab-content {
            display: none;
            padding: 0;
        }

        .tab-content.active {
            display: block;
        }

        .content-wrapper {
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .details-title {
            font-size: 1.8rem;
            color: #2d3b47;
            font-weight: 500;
            margin-bottom: 32px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e6eaed;
        }

        /* Question Bank Styling */
        .question-bank {
            border: 1px solid #c7cdd1;
            border-radius: 4px;
            margin-bottom: 24px;
        }

        .question-bank-header {
            background: #f8f9fa;
            border-bottom: 1px solid #c7cdd1;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .question-bank-title {
            font-size: 16px;
            font-weight: 500;
            color: #2d3b47;
        }

        .add-question-dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-btn {
            background: #0374b5;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
        }

        .dropdown-btn:hover {
            background: #025a8c;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            min-width: 240px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid #c7cdd1;
            border-radius: 4px;
            z-index: 1000;
            margin-top: 4px;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-item {
            display: block;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item-title {
            font-weight: 500;
            margin-bottom: 2px;
        }

        .dropdown-item-desc {
            font-size: 12px;
            color: #666;
        }

        /* Question Item Styling */
        .question-item {
            border: 1px solid #c7cdd1;
            border-radius: 4px;
            margin-bottom: 16px;
            background: white;
            transition: all 0.2s;
        }

        .question-item:hover {
            border-color: #0374b5;
            box-shadow: 0 2px 8px rgba(3, 116, 181, 0.1);
        }

        .question-item.editing {
            border-color: #0374b5;
            box-shadow: 0 0 0 2px rgba(3, 116, 181, 0.1);
        }

        .question-header {
            background: #f8f9fa;
            padding: 12px 16px;
            border-bottom: 1px solid #c7cdd1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .question-header:hover {
            background: #e9ecef;
        }

        .question-title-section {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .question-type-badge {
            background: #e3f2fd;
            color: #0277bd;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .question-type-badge.multiple-choice {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .question-type-badge.fill-blank {
            background: #fff3e0;
            color: #f57c00;
        }

        .question-type-badge.formula {
            background: #fce4ec;
            color: #c2185b;
        }

        .question-summary {
            color: #555;
            font-weight: 400;
        }

        .question-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .points-input {
            width: 50px;
            padding: 4px 6px;
            border: 1px solid #c7cdd1;
            border-radius: 3px;
            font-size: 12px;
            text-align: center;
        }

        .question-content {
            display: none;
            padding: 20px;
            border-top: 1px solid #e9ecef;
            background: #fafafa;
        }

        .question-content.expanded {
            display: block;
        }

        /* Form Styling */
        .form-row {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-col {
            flex: 1;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2d3b47;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #c7cdd1;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s ease;
            background-color: #ffffff;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
            max-width: 100%;
            box-sizing: border-box;
        }

        .datetime-group {
            display: flex;
            gap: 6px;
            margin-top: 4px;
            flex-wrap: wrap;
            font-size: 12px;
        }

        .datetime-group input[type="date"],
        .datetime-group input[type="time"] {
            flex: none;
            min-width: 110px;
            font-size: 12px;
            padding: 4px 8px;
        }

        .datetime-group input[type="date"] {
            width: 120px;
        }

        .datetime-group input[type="time"] {
            width: 90px;
        }

        .form-control:focus {
            outline: none;
            border-color: #0374b5;
            box-shadow: 0 0 0 3px rgba(3, 116, 181, 0.1);
        }

        .form-control.large {
            min-height: 100px;
            resize: vertical;
        }

        /* Rich Text Editor */
        .rich-editor {
            border: 1px solid #c7cdd1;
            border-radius: 6px;
            background: white;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .rich-editor-toolbar {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 8px 12px;
            display: flex;
            gap: 4px;
            border-top-left-radius: 6px;
            border-top-right-radius: 6px;
            flex-wrap: wrap;
            align-items: center;
        }

        .toolbar-btn {
            background: none;
            border: 1px solid transparent;
            padding: 6px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            color: #444;
            transition: all 0.2s ease;
            min-width: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toolbar-btn:hover {
            background: #edf2f7;
            border-color: #c7cdd1;
        }

        .toolbar-btn.active {
            background: #e2e8f0;
            border-color: #a0aec0;
            color: #2d3748;
        }

        .toolbar-separator {
            width: 1px;
            height: 24px;
            background: #e2e8f0;
            margin: 0 4px;
        }

        .toolbar-select {
            padding: 4px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 13px;
            color: #444;
            background: white;
            cursor: pointer;
        }

        .toolbar-select:hover {
            border-color: #c7cdd1;
        }

        /* Symbol Picker */
        .symbol-picker {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #c7cdd1;
            border-radius: 6px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            padding: 12px;
            margin-top: 4px;
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
        }

        .symbol-section {
            margin-bottom: 16px;
        }

        .symbol-category {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
        }

        .symbol-section button {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            margin: 2px;
            font-size: 14px;
            min-width: 32px;
        }

        .symbol-section button:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
        }

        /* Table Creation Dialog */
        .table-creator {
            position: absolute;
            background: white;
            border: 1px solid #c7cdd1;
            border-radius: 6px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 12px;
            z-index: 1000;
        }

        .table-grid {
            display: grid;
            grid-template-columns: repeat(8, 24px);
            gap: 2px;
            margin-bottom: 8px;
        }

        .table-cell {
            width: 24px;
            height: 24px;
            border: 1px solid #e2e8f0;
            background: white;
            cursor: pointer;
        }

        .table-cell:hover {
            background: #ebf8ff;
            border-color: #4299e1;
        }

        /* Math Equation Editor */
        .equation-editor {
            position: absolute;
            background: white;
            border: 1px solid #c7cdd1;
            border-radius: 6px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 16px;
            z-index: 1000;
            width: 400px;
        }

        .equation-preview {
            margin-top: 8px;
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            min-height: 40px;
            background: #f8fafc;
        }

        .toolbar-btn:hover {
            background: #e9ecef;
            border-color: #c7cdd1;
        }

        .rich-editor-content {
            padding: 12px;
            min-height: 80px;
            outline: none;
            position: relative;
            cursor: text;
        }

        .rich-editor-content.focused {
            background-color: #ffffff;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .rich-editor-content[placeholder]:empty:before {
            content: attr(placeholder);
            color: #999;
            pointer-events: none;
            position: absolute;
            left: 12px;
            top: 12px;
        }

        .rich-editor-content img {
            max-width: 100%;
            height: auto;
        }

        .rich-editor-content table {
            border-collapse: collapse;
            margin: 8px 0;
        }

        .rich-editor-content table td {
            border: 1px solid #ccc;
            padding: 8px;
            min-width: 50px;
        }

        /* Answer Choices */
        .answer-choices {
            border: 1px solid #e9ecef;
            border-radius: 4px;
            background: white;
        }

        .choice-item {
            display: flex;
            align-items: flex-start;
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            gap: 12px;
        }

        .choice-item:last-child {
            border-bottom: none;
        }

        .choice-item.correct {
            background: #f0f8f0;
            border-left: 3px solid #4caf50;
        }

        .choice-marker {
            width: 20px;
            height: 20px;
            border: 2px solid #c7cdd1;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin-top: 2px;
            flex-shrink: 0;
            position: relative;
            transition: all 0.2s ease;
        }

        .choice-marker::before {
            content: '';
            width: 12px;
            height: 12px;
            transition: all 0.2s ease;
        }

        /* Style for single answer mode */
        .question-item:not([data-multiple-answer="true"]) .choice-marker {
            border-radius: 50%;
        }

        .question-item:not([data-multiple-answer="true"]) .choice-marker::before {
            border-radius: 50%;
        }

        .question-item:not([data-multiple-answer="true"]) .choice-marker.correct::before {
            background: #4caf50;
        }

        /* Style for multiple answer mode */
        .question-item[data-multiple-answer="true"] .choice-marker {
            border-radius: 4px;
        }

        .question-item[data-multiple-answer="true"] .choice-marker::before {
            content: '✓';
            opacity: 0;
            color: white;
            font-size: 14px;
            line-height: 1;
        }

        .question-item[data-multiple-answer="true"] .choice-marker.correct {
            background: #4caf50;
            border-color: #4caf50;
        }

        .question-item[data-multiple-answer="true"] .choice-marker.correct::before {
            opacity: 1;
        }

        .choice-marker.correct {
            border-color: #4caf50;
            background: #4caf50;
            color: white;
        }

        .choice-marker::after {
            content: '';
            position: absolute;
            transition: all 0.2s ease;
        }

        .choice-marker.radio::after {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0;
        }

        .choice-marker.checkbox::after {
            width: 6px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg) scale(0);
            opacity: 0;
            top: 2px;
        }

        .choice-marker.radio.correct::after {
            opacity: 1;
            background: white;
        }

        .choice-marker.checkbox.correct::after {
            opacity: 1;
            transform: rotate(45deg) scale(1);
        }

        .choice-content {
            flex: 1;
        }

        .choice-text {
            width: 100%;
            border: none;
            background: transparent;
            font-size: 14px;
            font-family: inherit;
            resize: none;
            outline: none;
            min-height: 20px;
        }

        .choice-actions {
            display: flex;
            gap: 4px;
            margin-left: auto;
        }

        .icon-btn {
            background: none;
            border: none;
            padding: 4px;
            cursor: pointer;
            border-radius: 3px;
            color: #666;
        }

        .icon-btn:hover {
            background: #f0f0f0;
            color: #333;
        }

        .icon-btn.danger:hover {
            background: #fee;
            color: #d32f2f;
        }

        /* Formula Question Styling */
        .formula-section {
            background: #f8f9fb;
            border: 1px solid #e1e5f2;
            border-radius: 4px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .variable-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .variable-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: white;
            border: 1px solid #e1e5f2;
            border-radius: 4px;
        }

        .variable-name {
            font-weight: 500;
            min-width: 30px;
            color: #2d3b47;
        }

        .range-group {
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 120px;
        }

        .range-inputs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 8px;
            flex: 1;
        }

        .range-input {
            width: 80px;
            padding: 4px 6px;
            border: 1px solid #c7cdd1;
            border-radius: 3px;
            font-size: 13px;
            text-align: right;
        }

        /* Fill in the Blank Styling */
        .blank-template {
            background: #f8fffe;
            border: 1px solid #b2dfdb;
            border-radius: 4px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .blank-indicator {
            display: inline-block;
            background: #e0f2f1;
            border: 2px dashed #26a69a;
            border-radius: 4px;
            padding: 2px 12px;
            margin: 0 2px;
            font-weight: 500;
            color: #00695c;
            font-size: 12px;
        }

        .blank-answers {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .blank-answer-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }

        .blank-number {
            background: #26a69a;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 500;
            flex-shrink: 0;
        }

        /* Button Styling */
        .btn {
            padding: 8px 16px;
            border: 1px solid #c7cdd1;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn:hover {
            background: #f8f9fa;
        }

        .btn-primary {
            background: #0374b5;
            color: white;
            border-color: #0374b5;
        }

        .btn-primary:hover {
            background: #025a8c;
        }

        .btn-success {
            background: #00ac18;
            color: white;
            border-color: #00ac18;
        }

        .btn-outline {
            background: transparent;
            color: #0374b5;
            border-color: #0374b5;
        }

        .btn-outline:hover {
            background: #0374b5;
            color: white;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }

        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }

        .form-row {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            width: 100%;
        }

        .settings-datetime-container {
            margin-left: 24px;
            margin-top: 8px;
            background: #ffffff;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #e6eaed;
        }

        .settings-datetime-group {
            margin-bottom: 10px;
            font-size: 12px;
        }

        .settings-datetime-group:last-child {
            margin-bottom: 0;
        }

        .settings-datetime-label {
            font-size: 12px;
            color: #2d3b47;
            margin-bottom: 2px;
            display: block;
            font-weight: normal;
        }

        .settings-section {
            background: #fafafa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 28px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            height: fit-content;
            overflow: visible;
            min-width: 320px;
        }

        .settings-section h3 {
            color: #2d3b47;
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e6eaed;
            letter-spacing: 0.5px;
        }

        /* Preview Styling */
        .preview-quiz {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            margin-bottom: 24px;
        }

        .preview-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .preview-title {
            font-size: 24px;
            font-weight: 400;
            color: #2d3b47;
            margin-bottom: 8px;
        }

        .quiz-meta {
            display: flex;
            gap: 24px;
            font-size: 13px;
            color: #666;
        }

        .preview-question {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .preview-question:last-child {
            border-bottom: none;
        }

        .preview-q-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .preview-q-number {
            font-weight: 500;
            color: #2d3b47;
        }

        .preview-points {
            font-size: 12px;
            color: #666;
        }

        /* Utility Classes */
        .text-muted {
            color: #666;
        }

        .text-sm {
            font-size: 12px;
        }

        .mb-2 {
            margin-bottom: 8px;
        }

        .mb-3 {
            margin-bottom: 12px;
        }

        .mb-4 {
            margin-bottom: 16px;
        }

        .flex {
            display: flex;
        }

        .justify-between {
            justify-content: space-between;
        }

        .items-center {
            align-items: center;
        }

        .gap-2 {
            gap: 8px;
        }

        .gap-3 {
            gap: 12px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 0;
            width: 90%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 500;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 0 0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .close-button {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            color: #666;
        }

        .url-suggestions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }

        .url-type-btn {
            padding: 8px 12px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }

        .url-type-btn:hover {
            background-color: #e9ecef;
            border-color: #dde2e6;
        }

        /* Table Modal Styles */
        .table-grid-selector {
            margin-top: 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 16px;
            background: #f8f9fa;
        }

        .grid-size-display {
            text-align: center;
            margin-bottom: 12px;
            color: #495057;
            font-size: 14px;
        }

        .grid-container {
            display: inline-grid;
            grid-template-columns: repeat(10, 24px);
            gap: 2px;
            background: white;
            padding: 4px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }

        .grid-cell {
            width: 24px;
            height: 24px;
            border: 1px solid #e9ecef;
            background: white;
            transition: all 0.15s ease;
        }

        .grid-cell:hover {
            border-color: #0374b5;
            background-color: #e3f2fd;
        }

        .grid-cell.active {
            background-color: #0374b5;
            border-color: #0374b5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .form-row {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <button class="floating-new-question-btn" id="floatingNewQuestionBtn" onclick="toggleFloatingDropdown(event)">
            <span style="font-size: 22px;">＋</span> New Question
        </button>
        <div class="header">
            <h1>Quiz Builder</h1>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="showTab('settings')">Settings</button>
            <button class="tab" onclick="showTab('questions')">Questions</button>
            <button class="tab" onclick="showTab('preview')">Preview</button>
        </div>

        <!-- Quiz Settings Tab -->
        <div id="settings" class="tab-content active">
            <div class="content-wrapper">
                <h2 class="details-title">Quiz Settings</h2>
                <div class="settings-grid">
                    <div class="settings-section">
                        <h3>Basic Information</h3>
                        <div class="form-group">
                            <label class="form-label" for="quizTitle">Quiz Title <span style="color: #e74c3c;">*</span></label>
                            <input type="text" id="quizTitle" class="form-control" placeholder="Enter quiz title" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="quizDescription">Description</label>
                            <div class="rich-editor">
                                <div class="rich-editor-toolbar">
                                    <!-- Text Style -->
                                    <button class="toolbar-btn" title="Bold" onclick="execCommand('bold')"><strong>B</strong></button>
                                    <button class="toolbar-btn" title="Italic" onclick="execCommand('italic')"><em>I</em></button>
                                    <button class="toolbar-btn" title="Underline" onclick="execCommand('underline')"><u>U</u></button>
                                    <button class="toolbar-btn" title="Strikethrough" onclick="execCommand('strikethrough')"><s>S</s></button>
                                    <span class="toolbar-separator"></span>

                                    <!-- Text Scripts -->
                                    <button class="toolbar-btn" title="Subscript" onclick="execCommand('subscript')">X₂</button>
                                    <button class="toolbar-btn" title="Superscript" onclick="execCommand('superscript')">X²</button>
                                    <span class="toolbar-separator"></span>

                                    <!-- Lists -->
                                    <button class="toolbar-btn" title="Bullet List" onclick="execCommand('insertunorderedlist')">•</button>
                                    <button class="toolbar-btn" title="Numbered List" onclick="execCommand('insertorderedlist')">1.</button>
                                    <button class="toolbar-btn" title="Decrease Indent" onclick="execCommand('outdent')">←</button>
                                    <button class="toolbar-btn" title="Increase Indent" onclick="execCommand('indent')">→</button>
                                    <span class="toolbar-separator"></span>

                                    <span class="toolbar-separator"></span>

                                    <!-- Insert -->
                                    <button class="toolbar-btn" title="Insert Link" onclick="insertLink()">🔗</button>
                                    <button class="toolbar-btn" title="Insert Image" onclick="insertDescriptionImage()">🖼️</button>
                                    <button class="toolbar-btn" title="Insert Table" onclick="insertTable()">📊</button>
                                    <button class="toolbar-btn" title="Insert Symbol" onclick="showSymbolPicker()">Ω</button>
                                </div>
                                <div class="rich-editor-content" contenteditable="true" id="quizDescription" placeholder="Enter quiz instructions or description..."></div>
                                
                                <!-- Description Image Modal Dialog -->
                                <div id="descriptionImageModal" class="modal" style="display: none;">
                                    <div class="modal-content" style="max-width: 500px;">
                                        <div class="modal-header" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 1rem;">
                                            <h3 style="margin: 0; font-size: 1.25rem; color: #212529;">Insert Image</h3>
                                            <button onclick="closeDescriptionImageModal()" class="close-button" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; padding: 0; color: #6c757d;">&times;</button>
                                        </div>
                                        <div class="modal-body" style="padding: 1.5rem;">
                                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Image URL</label>
                                                <input type="text" class="form-control" id="descImageUrl" 
                                                    placeholder="Enter image URL (e.g., https://example.com/image.jpg)"
                                                    style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">
                                            </div>
                                            <div class="text-center" style="margin: 1.5rem 0; position: relative;">
                                                <span style="background: #fff; padding: 0 1rem; color: #6c757d; position: relative; z-index: 1;">OR</span>
                                                <hr style="margin: -0.75rem 0 0 0; border: 0; border-top: 1px solid #dee2e6;">
                                            </div>
                                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Upload Image</label>
                                                <input type="file" class="form-control" id="descImageFile" accept="image/*"
                                                    style="width: 100%; padding: 0.375rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">
                                            </div>
                                            <div class="image-preview" style="margin-top: 1.5rem; text-align: center; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 1rem;">
                                                <img id="descImagePreview" style="max-width: 100%; max-height: 200px; display: none; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <div id="descImagePlaceholder" style="color: #6c757d; padding: 2rem;">
                                                    Image preview will appear here
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer" style="background: #f8f9fa; border-top: 1px solid #dee2e6; padding: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                                            <button onclick="closeDescriptionImageModal()" class="btn btn-outline" 
                                                style="padding: 0.5rem 1rem; border: 1px solid #6c757d; background: none; border-radius: 4px; cursor: pointer; font-size: 14px;">Cancel</button>
                                            <button onclick="confirmDescriptionImage()" class="btn btn-primary" 
                                                style="padding: 0.5rem 1rem; background: #0d6efd; border: 1px solid #0d6efd; color: white; border-radius: 4px; cursor: pointer; font-size: 14px;">Insert Image</button>
                                        </div>
                                    </div>
                                </div>
                                
                            <!-- Link Modal Dialog -->
                            <div id="linkModal" class="modal" style="display: none;">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h3>Insert Link</h3>
                                        <button onclick="closeLinkModal()" class="close-button">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="linkText" class="form-label">Link Text</label>
                                            <input type="text" id="linkText" class="form-control" placeholder="Text to display">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Link Type</label>
                                            <div class="url-suggestions">
                                                <button onclick="selectUrlType('google')" class="url-type-btn">Google Search</button>
                                                <button onclick="selectUrlType('youtube')" class="url-type-btn">YouTube</button>
                                                <button onclick="selectUrlType('wikipedia')" class="url-type-btn">Wikipedia</button>
                                                <button onclick="selectUrlType('custom')" class="url-type-btn">Custom URL</button>
                                            </div>
                                        </div>
                                        <div id="urlInputGroup" class="form-group" style="display: none;">
                                            <label for="linkUrl" class="form-label">URL</label>
                                            <input type="text" id="linkUrl" class="form-control" placeholder="https://">
                                        </div>
                                        <div class="modal-footer">
                                            <button onclick="closeLinkModal()" class="btn btn-outline">Cancel</button>
                                            <button onclick="confirmLink()" class="btn btn-primary">Insert Link</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Table Modal Dialog -->
                            <div id="tableModal" class="modal" style="display: none;">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h3>Insert Table</h3>
                                        <button onclick="closeTableModal()" class="close-button">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label class="form-label">Table Size</label>
                                            <div class="table-grid-selector" id="tableGridSelector">
                                                <div class="grid-size-display">
                                                    <span id="gridSize">0 × 0</span> table
                                                </div>
                                                <div class="grid-container">
                                                    <!-- Grid cells will be created by JavaScript -->
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Table Style</label>
                                            <div style="display: flex; gap: 12px;">
                                                <label style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="checkbox" id="tableBordered" checked>
                                                    Show borders
                                                </label>
                                                <label style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="checkbox" id="tableStriped">
                                                    Striped rows
                                                </label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button onclick="closeTableModal()" class="btn btn-outline">Cancel</button>
                                            <button onclick="confirmTable()" class="btn btn-primary">Insert Table</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                
                                <!-- Symbol Picker Dialog -->
                                <div id="symbolPicker" class="symbol-picker" style="display: none;">
                                    <div class="symbol-picker-content">
                                        <div class="symbol-section">
                                            <div class="symbol-category">Math</div>
                                            <button onclick="insertSymbol('±')">±</button>
                                            <button onclick="insertSymbol('×')">×</button>
                                            <button onclick="insertSymbol('÷')">÷</button>
                                            <button onclick="insertSymbol('≈')">≈</button>
                                            <button onclick="insertSymbol('≠')">≠</button>
                                            <button onclick="insertSymbol('≤')">≤</button>
                                            <button onclick="insertSymbol('≥')">≥</button>
                                            <button onclick="insertSymbol('∑')">∑</button>
                                            <button onclick="insertSymbol('∏')">∏</button>
                                            <button onclick="insertSymbol('√')">√</button>
                                            <button onclick="insertSymbol('∫')">∫</button>
                                            <button onclick="insertSymbol('∞')">∞</button>
                                            <button onclick="insertSymbol('∂')">∂</button>
                                            <button onclick="insertSymbol('Δ')">Δ</button>
                                        </div>
                                        <div class="symbol-section">
                                            <div class="symbol-category">Greek</div>
                                            <button onclick="insertSymbol('α')">α</button>
                                            <button onclick="insertSymbol('β')">β</button>
                                            <button onclick="insertSymbol('γ')">γ</button>
                                            <button onclick="insertSymbol('δ')">δ</button>
                                            <button onclick="insertSymbol('ε')">ε</button>
                                            <button onclick="insertSymbol('θ')">θ</button>
                                            <button onclick="insertSymbol('λ')">λ</button>
                                            <button onclick="insertSymbol('μ')">μ</button>
                                            <button onclick="insertSymbol('π')">π</button>
                                            <button onclick="insertSymbol('σ')">σ</button>
                                            <button onclick="insertSymbol('φ')">φ</button>
                                            <button onclick="insertSymbol('Ω')">Ω</button>
                                        </div>
                                        <div class="symbol-section">
                                            <div class="symbol-category">Arrows</div>
                                            <button onclick="insertSymbol('←')">←</button>
                                            <button onclick="insertSymbol('→')">→</button>
                                            <button onclick="insertSymbol('↑')">↑</button>
                                            <button onclick="insertSymbol('↓')">↓</button>
                                            <button onclick="insertSymbol('↔')">↔</button>
                                            <button onclick="insertSymbol('⇒')">⇒</button>
                                            <button onclick="insertSymbol('⇐')">⇐</button>
                                            <button onclick="insertSymbol('⇔')">⇔</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="settings-section">
                        <h3>Quiz Options</h3>
                        <div class="form-row" style="display: flex; gap: 32px; flex-wrap: wrap; align-items: flex-start;">
                            <div class="form-col" style="min-width: 220px; flex: 1;">
                                <label class="form-label" for="timeLimit">Time Limit</label>
                                <input type="number" id="timeLimit" class="form-control" min="1" value="60" placeholder="Minutes">
                            </div>
                            <div class="form-col" style="min-width: 220px; flex: 1;">
                                <label class="form-label" for="attempts">Allowed Attempts</label>
                                <select id="attempts" class="form-control">
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                    <option value="6">6</option>
                                    <option value="7">7</option>
                                    <option value="8">8</option>
                                    <option value="9">9</option>
                                    <option value="10">10</option>
                                    <option value="-1">Unlimited</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row" style="display: flex; gap: 32px; flex-wrap: wrap; align-items: flex-start; margin-top: 16px;">
                            <div class="form-col" style="min-width: 220px; flex: 1;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="showOneQuestion"> Show One Question at a Time
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 12px;">
                                    <input type="checkbox" id="shuffleQuestions"> Shuffle the order of questions
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 12px;">
                                    <input type="checkbox" id="shuffleAnswers"> Shuffle answer choices
                                </label>
                            </div>
                            <div class="form-col" style="min-width: 220px; flex: 2;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                    <input type="checkbox" id="seeResponses"> Let Students See Their Responses
                                </label>
                                <div style="margin-left: 24px; margin-bottom: 12px;">
                                    <select id="seeResponsesTiming" class="form-control" style="width: auto; display: inline-block; font-size: 12px; padding: 4px 8px;">
                                        <option value="once">Only once after each attempt</option>
                                        <option value="always">Always</option>
                                    </select>
                                </div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="seeCorrectAnswers"> Let students see correct answers
                                </label>
                                <div class="settings-datetime-container">
                                    <div class="settings-datetime-group">
                                        <label class="settings-datetime-label">Show correct answers on</label>
                                        <div class="datetime-group">
                                            <input type="date" id="showCorrectAnswersDate" class="form-control">
                                            <input type="time" id="showCorrectAnswersTime" class="form-control">
                                        </div>
                                    </div>
                                    <div class="settings-datetime-group">
                                        <label class="settings-datetime-label">Hide correct answers on</label>
                                        <div class="datetime-group">
                                            <input type="date" id="hideCorrectAnswersDate" class="form-control">
                                            <input type="time" id="hideCorrectAnswersTime" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row" style="display: flex; gap: 32px; flex-wrap: wrap; align-items: flex-start; margin-top: 16px;">
                            <div class="form-col" style="min-width: 220px; flex: 1;">
                                <label style="font-weight: 500;">Assign</label>
                                <div class="settings-datetime-container">
                                    <div class="settings-datetime-group">
                                        <label class="settings-datetime-label">Due Date</label>
                                        <div class="datetime-group">
                                            <input type="date" id="dueDateDate" class="form-control">
                                            <input type="time" id="dueDateTime" class="form-control">
                                        </div>
                                    </div>
                                    <div class="settings-datetime-group">
                                        <label class="settings-datetime-label">Available From</label>
                                        <div class="datetime-group">
                                            <input type="date" id="availableFromDate" class="form-control">
                                            <input type="time" id="availableFromTime" class="form-control">
                                        </div>
                                    </div>
                                    <div class="settings-datetime-group">
                                        <label class="settings-datetime-label">Until</label>
                                        <div class="datetime-group">
                                            <input type="date" id="availableUntilDate" class="form-control">
                                            <input type="time" id="availableUntilTime" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Questions Tab -->
        <div id="questions" class="tab-content">
            <div class="content-wrapper">
            <!-- Modal for Image Insert -->
            <div id="imageModal" class="modal" style="display: none;">
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 1rem;">
                        <h3 style="margin: 0; font-size: 1.25rem; color: #212529;">Insert Image</h3>
                        <button onclick="closeImageModal()" class="close-button" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; padding: 0; color: #6c757d;">&times;</button>
                    </div>
                    <div class="modal-body" style="padding: 1.5rem;">
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Image URL</label>
                            <input type="text" class="form-control" id="imageUrl" 
                                placeholder="Enter image URL (e.g., https://example.com/image.jpg)"
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div class="text-center" style="margin: 1.5rem 0; position: relative;">
                            <span style="background: #fff; padding: 0 1rem; color: #6c757d; position: relative; z-index: 1;">OR</span>
                            <hr style="margin: -0.75rem 0 0 0; border: 0; border-top: 1px solid #dee2e6;">
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Upload Image</label>
                            <input type="file" class="form-control" id="imageFile" accept="image/*"
                                style="width: 100%; padding: 0.375rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div class="image-preview" style="margin-top: 1.5rem; text-align: center; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 1rem;">
                            <img id="imagePreview" style="max-width: 100%; max-height: 200px; display: none; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div id="imagePlaceholder" style="color: #6c757d; padding: 2rem;">
                                Image preview will appear here
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="background: #f8f9fa; border-top: 1px solid #dee2e6; padding: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                        <button onclick="closeImageModal()" class="btn btn-outline" 
                            style="padding: 0.5rem 1rem; border: 1px solid #6c757d; background: none; border-radius: 4px; cursor: pointer; font-size: 14px;">Cancel</button>
                        <button onclick="confirmImage()" class="btn btn-primary" 
                            style="padding: 0.5rem 1rem; background: #0d6efd; border: 1px solid #0d6efd; color: white; border-radius: 4px; cursor: pointer; font-size: 14px;">Insert Image</button>
                    </div>
                </div>
            </div>
                <div class="question-bank">
                    <div class="question-bank-header">
                        <div class="question-bank-title">Questions</div>
                        <div class="add-question-dropdown">

                            <div class="dropdown-content" id="questionTypeDropdown">
                                
                                <div class="dropdown-item" onclick="addNewQuestion('multiple_choice')">
                                    <div class="dropdown-item-title">Multiple Choice</div>
                                    <div class="dropdown-item-desc">Students can select multiple correct answers</div>
                                </div>
                                <div class="dropdown-item" onclick="addNewQuestion('fill_blank')">
                                    <div class="dropdown-item-title">Fill in the Blank</div>
                                    <div class="dropdown-item-desc">Students type answers into blank spaces in text</div>
                                </div>
                                <div class="dropdown-item" onclick="addNewQuestion('formula')">
                                    <div class="dropdown-item-title">Formula Question</div>
                                    <div class="dropdown-item-desc">Mathematical problems with variable substitution</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="questionsList" style="padding: 16px;">
                        <div class="text-muted" style="text-align: center; padding: 40px 20px;" id="emptyState">
                            <div style="font-size: 48px; margin-bottom: 16px;">📝</div>
                            <div style="font-size: 16px; margin-bottom: 8px;">No questions yet</div>
                            <div style="font-size: 14px;">Click "New Question" to get started</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview Tab -->
        <div id="preview" class="tab-content">
            <div class="content-wrapper">
                <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
                    <label for="previewModeToggle" style="font-weight: 500;">Preview Mode:</label>
                    <select id="previewModeToggle" style="padding: 6px 12px; border-radius: 4px; border: 1px solid #ccc;">
                        <option value="student">Student View</option>
                        <option value="proctor">Proctor View</option>
                    </select>
                </div>
                <div id="previewContent"></div>
            </div>
        </div>
    </div>

    <script>
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
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="multipleAnswer_${questionData.id}" 
                            ${questionData.isMultipleAnswer ? 'checked' : ''} 
                            onchange="toggleMultipleAnswer('${questionData.id}', this.checked)">
                        <label class="form-check-label" for="multipleAnswer_${questionData.id}">Allow multiple correct answers</label>
                    </div>
                    <div class="text-sm text-muted mb-2">
                        ${questionData.isMultipleAnswer ? 
                            'Select correct answers by checking the boxes next to them' : 
                            'Select the correct answer by clicking the circle next to it'}
                    </div>
                    <div class="answer-choices" id="choices_${questionData.id}">
                        ${questionData.choices.map((choice, index) => `
                            <div class="choice-item ${questionData.correctAnswers.includes(index) ? 'correct' : ''}">
                                <div class="choice-marker ${questionData.correctAnswers.includes(index) ? 'correct' : ''}" 
                                     onclick="toggleCorrectAnswer('${questionData.id}', ${index})"></div>
                                <div class="choice-content">
                                    <textarea class="choice-text" placeholder="Enter answer choice..."
                                              onchange="updateChoice('${questionData.id}', ${index}, this.value)"
                                              rows="1">${choice}</textarea>
                                </div>
                                <div class="choice-actions">
                                    ${questionData.choices.length > 2 ? `
                                        <button class="icon-btn danger" onclick="removeChoice('${questionData.id}', ${index})" title="Delete choice">
                                            🗑️
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    <button class="btn btn-outline btn-sm" onclick="addChoice('${questionData.id}')" style="margin-top: 8px;">
                        + Add Another Answer Choice
                    </button>
                </div>
            `;
        }

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
                                <button class="toolbar-btn" type="button" title="Bold" onclick="formatEditor('${questionData.id}_blank', 'bold')"><strong>B</strong></button>
                                <button class="toolbar-btn" type="button" title="Italic" onclick="formatEditor('${questionData.id}_blank', 'italic')"><em>I</em></button>
                                <button class="toolbar-btn" type="button" title="Underline" onclick="formatEditor('${questionData.id}_blank', 'underline')"><u>U</u></button>
                                <span style="border-left: 1px solid #ddd; margin: 0 4px;"></span>
                                <button class="toolbar-btn" type="button" title="Link" onclick="formatEditor('${questionData.id}_blank', 'link')">🔗</button>
                                <button class="toolbar-btn" type="button" title="Image" onclick="formatEditor('${questionData.id}_blank', 'image')">🖼️</button>
                                <button class="toolbar-btn" type="button" title="Equation" onclick="formatEditor('${questionData.id}_blank', 'equation')">∑</button>
                                <span style="border-left: 1px solid #ddd; margin: 0 4px;"></span>
                                <button class="toolbar-btn" type="button" title="Insert Blank" onclick="insertBlank('${questionData.id}_blank')" style="padding: 4px 8px;">
                                    Insert _
                                </button>
                            </div>
                            <div class="rich-editor-content" contenteditable="true" id="editor_${questionData.id}_blank" 
                                 placeholder="Enter your question text and use _ for blanks..."
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
                                           ${questionData.caseSensitive && questionData.caseSensitive[i] ? 'checked' : ''}
                                           onchange="updateBlankCaseSensitivity('${questionData.id}', ${i}, this.checked)">
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
                        🗑️
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
            container.innerHTML = renderMultipleChoiceEditor(question).match(/<div class="answer-choices"[^>]*>(.*?)<\/div>/s)[1];
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
        function updateBlankAnswer(questionId, blankIndex, value, answerIndex = 0) {
            const question = questions.find(q => q.id === questionId);
            if (!question.blanks) question.blanks = [];
            if (!question.blanks[blankIndex]) question.blanks[blankIndex] = [];
            question.blanks[blankIndex][answerIndex] = value;
            // Filter out empty answers
            question.blanks[blankIndex] = question.blanks[blankIndex].filter(answer => answer && answer.trim() !== '');
        }

        function updateBlankCaseSensitivity(questionId, index, isCaseSensitive) {
            const question = questions.find(q => q.id === questionId);
            if (!question.caseSensitive) question.caseSensitive = [];
            question.caseSensitive[index] = isCaseSensitive;
        }

        function generateAlternateAnswerInputs(questionData, blankIndex) {
            if (!questionData.blanks || !questionData.blanks[blankIndex]) return '';
            
            // Skip the first answer (primary) and generate inputs for alternates
            return questionData.blanks[blankIndex].slice(1).map((answer, altIndex) => `
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="text" class="form-control" 
                           placeholder="Alternate answer ${altIndex + 1}"
                           value="${answer || ''}"
                           onchange="updateBlankAnswer('${questionData.id}', ${blankIndex}, this.value, ${altIndex + 1})">
                    <button class="icon-btn danger" onclick="removeAlternateAnswer('${questionData.id}', ${blankIndex}, ${altIndex + 1})"
                            title="Remove this answer">
                        🗑️
                    </button>
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
                .replace(/pi/g, 'Math.PI')
                .replace(/e(?!\w)/g, 'Math.E')
                .replace(/sin\(/g, 'Math.sin(')
                .replace(/cos\(/g, 'Math.cos(')
                .replace(/tan\(/g, 'Math.tan(')
                .replace(/asin\(/g, 'Math.asin(')
                .replace(/acos\(/g, 'Math.acos(')
                .replace(/atan\(/g, 'Math.atan(')
                .replace(/sinh\(/g, 'Math.sinh(')
                .replace(/cosh\(/g, 'Math.cosh(')
                .replace(/tanh\(/g, 'Math.tanh(')
                .replace(/log\(/g, 'Math.log10(')
                .replace(/ln\(/g, 'Math.log(')
                .replace(/sqrt\(/g, 'Math.sqrt(')
                .replace(/abs\(/g, 'Math.abs(')
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
                .replace(/asin/g, 'sin⁻¹')
                .replace(/acos/g, 'cos⁻¹')
                .replace(/atan/g, 'tan⁻¹');

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
    let contentHtml = '';
    if (question.type === 'multiple_choice') {
        contentHtml = `
            <div style="margin-bottom: 16px;">${question.content || 'Question text will appear here'}</div>
            ${question.choices.map((choice, index) => {
                let correctMark = '';
                if (window.previewMode === 'proctor' && question.correctAnswers && question.correctAnswers.includes(index)) {
                    correctMark = '<span style="color: #0374b5; font-weight: bold; margin-left: 8px;">✔ Correct</span>';
                }
                return `
                    <div style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                        <input type="${question.isMultipleAnswer ? 'checkbox' : 'radio'}" name="preview_${question.id}" disabled>
                        <span>${choice || `Choice ${index + 1}`}</span>
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
            return `<input type="text" style="display: inline-block; min-width: 120px; margin: 0 4px; padding: 2px 6px; border: none; border-bottom: 2px solid #333;" placeholder="Blank ${idx}" disabled>`;
        });
        contentHtml += `</div>`;
        
        // If in proctor view, show answers below
        if (window.previewMode === 'proctor') {
            contentHtml += `
                <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e9ecef;">
                    <div style="font-weight: 500; margin-bottom: 12px; color: #0374b5;">Correct Answers:</div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
                        ${corrects.map((answers, index) => {
                            const isCaseSensitive = question.caseSensitive && question.caseSensitive[index];
                            const answerArray = Array.isArray(answers) ? answers : [answers];
                            return `
                                <div style="background: #f8f9fa; padding: 12px; border-radius: 4px; border: 1px solid #e9ecef;">
                                    <div style="color: #666; font-size: 13px;">Blank ${index + 1}</div>
                                    <div style="margin-top: 8px;">
                                        ${answerArray.map((answer, ansIdx) => `
                                            <div style="margin-bottom: 4px;">
                                                <div style="font-weight: ${ansIdx === 0 ? '600' : '400'}; color: ${ansIdx === 0 ? '#0374b5' : '#333'};">
                                                    ${ansIdx === 0 ? 'Primary Answer:' : 'Alternate Answer:'} ${answer || ''}
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                    ${isCaseSensitive ? '<div style="color: #666; font-size: 12px; margin-top: 8px; padding-top: 8px; border-top: 1px solid #e9ecef;">(Case Sensitive)</div>' : ''}
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

        let correctMark = '';
        if (window.previewMode === 'proctor') {
            try {
                // Create calculation formula with proper math function handling
                let calculationFormula = question.formula
                    .replace(/pi/g, 'Math.PI')
                    .replace(/e(?!\w)/g, 'Math.E')
                    .replace(/sin\(/g, 'Math.sin(')
                    .replace(/cos\(/g, 'Math.cos(')
                    .replace(/tan\(/g, 'Math.tan(')
                    .replace(/asin\(/g, 'Math.asin(')
                    .replace(/acos\(/g, 'Math.acos(')
                    .replace(/atan\(/g, 'Math.atan(')
                    .replace(/sinh\(/g, 'Math.sinh(')
                    .replace(/cosh\(/g, 'Math.cosh(')
                    .replace(/tanh\(/g, 'Math.tanh(')
                    .replace(/log\(/g, 'Math.log10(')
                    .replace(/ln\(/g, 'Math.log(')
                    .replace(/sqrt\(/g, 'Math.sqrt(')
                    .replace(/abs\(/g, 'Math.abs(')
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

                correctMark = `<div style='color: #0374b5; font-weight: bold; margin-top: 8px;'>
                    ✔ Correct Answer: ${Number(result.toFixed(maxDecimals))}
                </div>`;
            } catch (e) {
                correctMark = `<div style='color: #d32f2f; font-weight: bold; margin-top: 8px;'>
                    Invalid formula or mathematical operation
                </div>`;
            }
        }

        // Replace variables in content with their values
        let displayContent = question.content || '';
        Object.keys(sampleValues).forEach(varName => {
            const regex = new RegExp(`\\[${varName}\\]`, 'g');
            displayContent = displayContent.replace(regex, `<strong>${sampleValues[varName]}</strong>`);
        });

        contentHtml = `
            <div style="margin-bottom: 16px;">${displayContent}</div>
            <div>
                <strong>Formula:</strong> ${question.formula}<br>
                <input type="text" 
                    style="margin-top: 8px; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px;" 
                    placeholder="Enter your answer" 
                    disabled>
                ${correctMark}
            </div>
        `;
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
    </script>
</body>

</html>