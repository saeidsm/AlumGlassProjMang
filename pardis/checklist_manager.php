<?php
//pardis/checklist_manager.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}
if (!in_array($_SESSION['role'], ['admin', 'superuser','supervisor'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

$pageTitle = "مدیریت قالب‌های چک‌لیست";
require_once __DIR__ . '/header_pardis.php';
// Note: We don't include header_pardis.php to keep this page self-contained and simple.
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت قالب‌های چک‌لیست</title>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <style>
        @font-face {
            font-family: "Samim";
            src: url("assets/fonts/Samim-FD.woff2") format("woff2");
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Samim", sans-serif;
            background-color: #f4f7f6;
            direction: rtl;
            margin: 0;
            padding: 10px;
            font-size: 14px;
        }

        #manager-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        h1,
        h2,
        h3 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        h1 {
            font-size: 1.8em;
            text-align: center;
        }

        h2 {
            font-size: 1.4em;
        }

        h3 {
            font-size: 1.2em;
        }

        .layout {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .template-list-container {
            flex: 1;
            min-width: 300px;
            background-color: #ecf0f1;
            padding: 20px;
            border-radius: 8px;
            height: fit-content;
        }

        .template-form-container {
            flex: 2;
            min-width: 350px;
            padding: 20px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            body {
                padding: 5px;
            }

            #manager-container {
                padding: 15px;
            }

            .layout {
                flex-direction: column;
                gap: 20px;
            }

            .template-list-container,
            .template-form-container {
                min-width: 100%;
                padding: 15px;
            }

            h1 {
                font-size: 1.5em;
            }

            h2 {
                font-size: 1.2em;
            }
        }

        /* Template List */
        #template-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        #template-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
            gap: 10px;
        }

        #template-list li span:first-child {
            flex: 1;
            min-width: 200px;
            font-weight: 500;
        }

        .template-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .template-actions button {
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s ease;
            min-width: 60px;
        }

        .copy-btn {
            background: #f39c12;
        }

        .copy-btn:hover {
            background: #e67e22;
        }

        .edit-btn {
            background: #3498db;
        }

        .edit-btn:hover {
            background: #2980b9;
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            font-family: "Samim", sans-serif;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }

        /* Stage Groups */
        .stage-group {
            border: 2px solid #3498db;
            border-radius: 12px;
            margin-bottom: 25px;
            background: #fff;
            overflow: hidden;
        }

        .stage-header {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            cursor: move;
            gap: 10px;
        }

        .stage-title {
            font-weight: bold;
            margin: 0;
            font-size: 1.1em;
        }

        .stage-items-container {
            padding: 20px;
        }

        /* Item Rows */
        .item-row {
            display: grid;
            grid-template-columns: 30px 1fr 2fr 120px 60px;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            align-items: center;
        }

        @media (max-width: 768px) {
            .item-row {
                grid-template-columns: 1fr;
                gap: 10px;
                text-align: center;
            }

            .item-row .item-drag-handle {
                display: none;
            }
        }

        .item-drag-handle {
            cursor: move;
            color: #999;
            font-size: 1.5em;
            text-align: center;
            user-select: none;
        }

        .item-stage-input,
        .item-text-input {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            font-family: "Samim", sans-serif;
            width: 100%;
        }

        .item-stage-input:focus,
        .item-text-input:focus {
            outline: none;
            border-color: #3498db;
        }

        .item-passing-status {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: "Samim", sans-serif;
            background: white;
        }

        .remove-item-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s ease;
        }

        .remove-item-btn:hover {
            background: #c0392b;
        }

        /* Headers for Item Rows */
        .item-header {
            display: grid;
            grid-template-columns: 30px 1fr 2fr 120px 60px;
            gap: 15px;
            font-weight: bold;
            margin-bottom: 10px;
            padding: 0 15px;
            color: #2c3e50;
        }

        @media (max-width: 768px) {
            .item-header {
                display: none;
            }
        }

        /* Buttons */
        button {
            font-family: "Samim", sans-serif;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            margin: 5px;
        }

        #add-item-btn {
            background: #95a5a6;
            color: white;
        }

        #add-item-btn:hover {
            background: #7f8c8d;
        }

        #clear-form-btn {
            background: #34495e;
            color: white;
        }

        #clear-form-btn:hover {
            background: #2c3e50;
        }

        .save-btn {
            background: #2ecc71;
            color: white;
            font-weight: bold;
            font-size: 16px;
            padding: 15px 30px;
        }

        .save-btn:hover {
            background: #27ae60;
        }

        /* Helper Text */
        .helper-text {
            font-size: 13px;
            color: #555;
            margin-bottom: 20px;
            line-height: 1.6;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #ecf0f1;
        }

        @media (max-width: 480px) {
            .form-actions {
                flex-direction: column;
            }

            .form-actions button {
                width: 100%;
                margin: 5px 0;
            }
        }

        /* Loading and States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Smooth Animations */
        .stage-group {
            transition: transform 0.2s ease;
        }

        .stage-group:hover {
            transform: translateY(-2px);
        }

        .item-row {
            transition: transform 0.2s ease;
        }

        .item-row:hover {
            transform: translateY(-1px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
            font-style: italic;
        }

        /* Responsive Grid Headers */
        @media (max-width: 768px) {
            .item-row {
                display: block;
                padding: 15px;
            }

            .item-row>* {
                margin-bottom: 10px;
                width: 100%;
            }

            .item-row .item-drag-handle {
                display: block;
                text-align: center;
                margin-bottom: 10px;
            }

            .item-stage-input,
            .item-text-input,
            .item-passing-status {
                font-size: 16px;
                padding: 15px;
            }
        }

        .item-header,
        .item-row {
            /* Original columns + 1 new column for "Drawing" */
            grid-template-columns: 30px 1fr 2fr 120px 100px 100px 100px 60px;
        }

        /* استایل برای چک‌باکس ترسیم */
        .draw-input-group {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-draw-input {
            width: 20px;
            height: 20px;
        }

        .weight-input-group,
        .critical-input-group {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .weight-input-group span {
            margin-right: 5px;
        }

        .item-critical-input {
            width: 20px;
            height: 20px;
        }

        .stage-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            /* This will push the total to the right */
        }

        .stage-item-total {
            font-size: 13px;
            font-weight: bold;
        }

        .total-item-weight-display.valid {
            color: #28a745;
        }

        .total-item-weight-display.invalid {
            color: #dc3545;
        }

        #save-validation-message.error {
            color: #c0392b;
            /* Red */
        }
    </style>
</head>

<body>
    <div id="manager-container">
        <h1>مدیریت قالب‌های چک‌لیست</h1>

        <div class="layout">
            <div class="template-list-container">
                <h2>قالب‌های موجود</h2>
                <ul id="template-list">
                    <!-- Demo templates -->
                    <li>
                        <span>چک لیست شیشه (GLASS)</span>
                        <div class="template-actions">
                            <button class="copy-btn" data-id="1">کپی</button>
                            <button class="edit-btn" data-id="1">ویرایش</button>
                        </div>
                    </li>
                    <li>
                        <span>چک لیست کرتین وال و وینووال (Curtainwall)</span>
                        <div class="template-actions">
                            <button class="copy-btn" data-id="2">کپی</button>
                            <button class="edit-btn" data-id="2">ویرایش</button>
                        </div>
                    </li>
                    <li>
                        <span>چک لیست کنترل کیفی GFRC - نسخه 1 (GFRC)</span>
                        <div class="template-actions">
                            <button class="copy-btn" data-id="3">کپی</button>
                            <button class="edit-btn" data-id="3">ویرایش</button>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="template-form-container">
                <h2>ایجاد / ویرایش / کپی</h2>

                <datalist id="stage-datalist">
                    <option value="مرحله اول"></option>
                    <option value="مرحله دوم"></option>
                    <option value="مرحله سوم"></option>
                </datalist>

                <form id="template-form">
                    <input type="hidden" id="template_id" name="template_id">

                    <div class="form-group">
                        <label for="template_name">نام قالب</label>
                        <input type="text" id="template_name" name="template_name" required
                            placeholder="نام قالب را وارد کنید...">
                    </div>

                    <div class="form-group">
                        <label for="element_type">نوع المان (Element Type)</label>
                        <input type="text" id="element_type" name="element_type" required
                            placeholder="مثال: GFRC, Glass, Curtainwall">
                    </div>

                    <hr style="margin: 30px 0; border: 1px solid #ecf0f1;">

                    <h3>آیتم‌های چک‌لیست</h3>

                    <div class="helper-text">
                        <strong>راهنما:</strong>
                        <br>• <strong>شرط قبولی:</strong> برای آیتم‌های بررسی نقص (مانند "آیا ترکی وجود دارد؟")، شرط قبولی را <strong>ناموفق (✗)</strong> قرار دهید.
                        <br>• <strong>وزن آیتم:</strong> مجموع وزن تمام آیتم‌ها در یک مرحله باید <strong>دقیقا ۱۰۰٪</strong> شود.
                        <br>• <strong>آیتم کلیدی:</strong> اگر این گزینه فعال باشد و وضعیت آیتم "رد شده" (NOK) ثبت شود، کل مرحله به صورت خودکار "رد شده" در نظر گرفته خواهد شد.
                    </div>

                    <div class="item-header">
                        <span></span> <!-- Drag Handle -->
                        <span>مرحله (Stage)</span>
                        <span>متن سوال</span>
                        <span>شرط قبولی</span>
                        <span>وزن آیتم (%)</span>
                        <span>آیتم کلیدی؟</span>
                        <span>نیاز به ترسیم؟</span> <!-- ADDED THIS HEADER -->
                        <span></span> <!-- Delete Button -->
                    </div>

                    <div id="items-container">
                        <!-- Demo stage group -->
                        <div class="stage-group" data-stage-name="مرحله اول">
                            <div class="stage-header">
                                <span class="item-drag-handle">☰</span>
                                <h4 class="stage-title">مرحله اول</h4>
                            </div>
                            <div class="stage-items-container">
                                <div class="item-row">
                                    <span class="item-drag-handle">☰</span>
                                    <input type="text" class="item-stage-input" value="مرحله اول"
                                        placeholder="مرحله..." list="stage-datalist">
                                    <input type="text" class="item-text-input" value="آیا مواد نصب شده‌اند؟"
                                        placeholder="متن سوال..." required>
                                    <select class="item-passing-status">
                                        <option value="OK" selected>موفق (✓)</option>
                                        <option value="Not OK">ناموفق (✗)</option>
                                    </select>
                                    <button type="button" class="remove-item-btn">حذف</button>
                                </div>
                            </div>
                        </div>
                    </div>


                    <button type="button" id="add-stage-btn" style="background: #1abc9c; color: white;">افزودن مرحله جدید</button>

                    <div class="form-actions">
                        <div id="save-validation-message" style="width: 100%; text-align: center; font-weight: bold; margin-bottom: 10px;"></div>

                        <button type="submit" class="save-btn">ذخیره نام و نوع قالب</button>

                        <button type="button" id="clear-form-btn">فرم جدید</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        // FINAL SCRIPT - This version correctly handles data grouped by stage_id
        document.addEventListener('DOMContentLoaded', function() {
            // --- VARIABLE DECLARATIONS ---
            const templateList = document.getElementById('template-list');
            const form = document.getElementById('template-form');
            const itemsContainer = document.getElementById('items-container');
            const templateIdField = document.getElementById('template_id');
            const templateNameField = document.getElementById('template_name');
            const elementTypeField = document.getElementById('element_type');
            const mainSaveBtn = form.querySelector('.save-btn');

            // --- HELPER FUNCTIONS ---
            function escapeHtml(unsafe) {
                if (typeof unsafe !== "string") return "";
                return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
            }

            // --- CORE LOGIC ---
            function loadTemplates() {
                fetch('api/get_templates.php').then(res => res.json()).then(templates => {
                    templateList.innerHTML = '';
                    templates.forEach(t => {
                        const li = document.createElement('li');
                        li.innerHTML = `<span>${escapeHtml(t.template_name)} (<b>${escapeHtml(t.element_type)}</b>)</span><span class="template-actions"><button type="button" data-id="${t.template_id}" class="copy-btn">کپی</button><button type="button" data-id="${t.template_id}" class="edit-btn">ویرایش</button></span>`;
                        templateList.appendChild(li);
                    });
                });
            }

            function loadTemplateForEditing(id, isCopy = false) {
                fetch(`api/get_template_details.php?id=${id}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                            return;
                        }
                        clearForm();

                        if (isCopy) {
                            templateIdField.value = '';
                            templateNameField.value = data.template.template_name + ' - کپی';
                            elementTypeField.value = data.template.element_type;
                        } else {
                            templateIdField.value = data.template.template_id;
                            templateNameField.value = data.template.template_name;
                            elementTypeField.value = data.template.element_type;
                        }

                        const itemsByStageId = {};
                        if (Array.isArray(data.items)) {
                            data.items.forEach(item => {
                                if (item.stage_id === null || item.stage_id === undefined) return;
                                if (!itemsByStageId[item.stage_id]) {
                                    itemsByStageId[item.stage_id] = [];
                                }
                                itemsByStageId[item.stage_id].push(item);
                            });
                        }

                        if (Array.isArray(data.stages) && data.stages.length > 0) {
                            // داده‌های مراحل از 'data.stages' خوانده می‌شود
                            data.stages.forEach(stage => {
                                const itemsForThisStage = itemsByStageId[stage.stage_id] || [];
                                // آیتم‌های فیلتر شده به تابع addStageGroup پاس داده می‌شود
                                addStageGroup(stage.stage, itemsForThisStage, stage.stage_id);
                            });
                        }

                        initializeSortables();
                    })
                    .catch(error => {
                        // این همان جایی است که خطای شما نمایش داده می‌شود
                        console.error("Error loading template details:", error);
                        alert("خطا در بارگذاری قالب. کنسول را بررسی کنید.");
                    });
            }

            function addStageGroup(stageName, items = [], stageId = '') {
                const stageGroupDiv = document.createElement('div');
                stageGroupDiv.className = 'stage-group';
                stageGroupDiv.dataset.stageId = stageId;
                stageGroupDiv.innerHTML = `
        <div class="stage-header">
            <div style="display: flex; align-items: center; gap: 10px; flex-grow: 1;"><span class="item-drag-handle">☰</span><h4 class="stage-title" contenteditable="true">${escapeHtml(stageName)}</h4></div>
            <div class="stage-actions" style="display: flex; align-items: center;">
                 <div class="stage-item-total"><span class="total-item-weight-display">0%</span></div>
                 <button type="button" class="save-stage-btn" title="ذخیره این مرحله">ذخیره مرحله</button>
                 <button type="button" class="remove-stage-btn" title="حذف این مرحله">حذف مرحله</button>
            </div>
        </div>
        <div class="stage-items-container"></div>
        <div class="stage-footer"><button type="button" class="add-item-to-stage-btn">افزودن آیتم به این مرحله</button></div>`;

                const stageItemsContainer = stageGroupDiv.querySelector('.stage-items-container');
                const stageTitle = stageGroupDiv.querySelector('.stage-title');

                stageTitle.addEventListener('input', () => {
                    stageGroupDiv.querySelectorAll('.item-stage-input').forEach(input => input.value = stageTitle.textContent);
                });

                // --- START OF FIX ---
                // این بلوک کد اصلاح شده برای اضافه کردن ردیف‌های آیتم است.
                if (items && items.length > 0) {
                    // متغیر 'items' در اینجا به پارامتر ورودی تابع اشاره دارد و صحیح است.
                    items.forEach(item => {
                        // ما اکنون تمام آرگومان‌ها، شامل 'requires_drawing' را به درستی پاس می‌دهیم.
                        addItemRow(
                            stageItemsContainer,
                            item.item_text,
                            item.stage,
                            item.passing_status,
                            item.item_weight,
                            item.is_critical,
                            item.requires_drawing // پارامتر فراموش شده
                        );
                    });
                } else {
                    // هنگام افزودن یک مرحله جدید، یک ردیف آیتم خالی ایجاد کن.
                    addItemRow(stageItemsContainer, '', stageName);
                }
                // --- END OF FIX ---

                itemsContainer.appendChild(stageGroupDiv);
                validateStageWeight(stageGroupDiv);
            }


            function addItemRow(container, text = '', stage = '', ps = 'OK', w = 0, c = false, rd = false) { // rd = requires_drawing
                const itemDiv = document.createElement('div');
                itemDiv.className = 'item-row';
                const isCriticalChecked = (c == '1' || c === true) ? 'checked' : '';
                const requiresDrawingChecked = (rd == '1' || rd === true) ? 'checked' : ''; // ADDED THIS LINE

                itemDiv.innerHTML = `
        <span class="item-drag-handle">☰</span>
        <input type="text" class="item-stage-input" value="${escapeHtml(stage)}" list="stage-datalist">
        <input type="text" class="item-text-input" value="${escapeHtml(text)}" required>
        <select class="item-passing-status">
            <option value="OK" ${ps==='OK'?'selected':''}>موفق (✓)</option>
            <option value="Not OK" ${ps==='Not OK'?'selected':''}>ناموفق (✗)</option>
        </select>
        <div class="weight-input-group"><input type="number" class="item-weight-input" value="${parseFloat(w).toFixed(2)}" min="0" max="100" step="1"><span>%</span></div>
        <div class="critical-input-group"><input type="checkbox" class="item-critical-input" ${isCriticalChecked}></div>
        <div class="draw-input-group"><input type="checkbox" class="item-draw-input" ${requiresDrawingChecked}></div> <!-- ADDED THIS CHECKBOX -->
        <button type="button" class="remove-item-btn">حذف</button>`;
                container.appendChild(itemDiv);
            }

            function clearForm() {
                form.reset();
                templateIdField.value = '';
                itemsContainer.innerHTML = '';
            }

            function initializeSortables() {
                new Sortable(itemsContainer, {
                    animation: 150,
                    handle: '.stage-header',
                    group: 'stages'
                });
                document.querySelectorAll('.stage-items-container').forEach(c => new Sortable(c, {
                    animation: 150,
                    handle: '.item-drag-handle',
                    group: 'items'
                }));
            }

            function validateStageWeight(stageGroup) {
                const totalDisplay = stageGroup.querySelector('.total-item-weight-display');
                const saveStageBtn = stageGroup.querySelector('.save-stage-btn');
                let total = 0;
                stageGroup.querySelectorAll('.item-weight-input').forEach(input => total += parseFloat(input.value) || 0);
                totalDisplay.textContent = total.toFixed(2) + '%';
                const isStageValid = Math.abs(total - 100.00) < 0.01;
                totalDisplay.classList.toggle('valid', isStageValid);
                totalDisplay.classList.toggle('invalid', !isStageValid);
                if (saveStageBtn) {
                    saveStageBtn.disabled = !isStageValid;
                    saveStageBtn.title = isStageValid ? 'ذخیره این مرحله' : 'مجموع وزن باید ۱۰۰٪ باشد';
                }
            }


            // --- EVENT LISTENERS ---
            document.getElementById('add-stage-btn').addEventListener('click', () => {
                if (!templateIdField.value) {
                    alert("ابتدا باید نام و نوع قالب را با دکمه اصلی ذخیره کنید.");
                    return;
                }
                addStageGroup(`مرحله جدید ${itemsContainer.children.length + 1}`, [], '');
                initializeSortables();
            });
            itemsContainer.addEventListener('input', e => {
                if (e.target.closest('.stage-group')) {
                    validateStageWeight(e.target.closest('.stage-group'));
                }
            });
            itemsContainer.addEventListener('click', e => {
                if (e.target.classList.contains('remove-item-btn')) {
                    const stageGroup = e.target.closest('.stage-group');
                    e.target.closest('.item-row').remove();
                    if (stageGroup) validateStageWeight(stageGroup);
                }
                if (e.target.classList.contains('remove-stage-btn')) {
                    const stageGroup = e.target.closest('.stage-group');
                    const stageId = stageGroup.dataset.stageId;
                    const templateId = templateIdField.value;
                    const stageName = stageGroup.querySelector('.stage-title').textContent;

                    if (confirm(`آیا از حذف کامل مرحله "${stageName}" و تمام آیتم‌های آن اطمینان دارید؟`)) {
                        // If the stage has no ID, it's new and not saved in the DB yet.
                        // Just remove it from the page.
                        if (!stageId) {
                            stageGroup.remove();
                            return;
                        }

                        // If it has an ID, we need to call the API to delete it from the database.
                        const formData = new FormData();
                        formData.append('stage_id', stageId);
                        formData.append('template_id', templateId);

                        fetch('api/delete_stage.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    // Only remove from the page if the database deletion was successful
                                    stageGroup.remove();
                                    alert(data.message);
                                } else {
                                    alert('خطا در حذف مرحله: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Delete failed:', error);
                                alert('خطا در ارتباط با سرور.');
                            });
                    }
                }

                if (e.target.classList.contains('add-item-to-stage-btn')) {
                    const stageGroup = e.target.closest('.stage-group');
                    const stageItemsContainer = stageGroup.querySelector('.stage-items-container');
                    const stageName = stageGroup.querySelector('.stage-title').textContent;
                    addItemRow(stageItemsContainer, '', stageName);
                    validateStageWeight(stageGroup);
                }
                if (e.target.classList.contains('save-stage-btn')) {
                    const saveStageBtn = e.target;
                    const stageGroup = saveStageBtn.closest('.stage-group');
                    saveStageBtn.textContent = '...';
                    saveStageBtn.disabled = true;
                    const stageData = new FormData();
                    stageData.append('template_id', templateIdField.value);
                    stageData.append('stage_id', stageGroup.dataset.stageId);
                    stageData.append('stage_name', stageGroup.querySelector('.stage-title').textContent);
                    const allStages = Array.from(itemsContainer.querySelectorAll('.stage-group'));
                    stageData.append('display_order', allStages.indexOf(stageGroup));
                    const items = [];
                    stageGroup.querySelectorAll('.item-row').forEach(row => {
                        items.push({
                            item_text: row.querySelector('.item-text-input').value.trim(),
                            stage: stageGroup.querySelector('.stage-title').textContent.trim(),
                            passing_status: row.querySelector('.item-passing-status').value,
                            item_weight: parseFloat(row.querySelector('.item-weight-input').value) || 0,
                            is_critical: row.querySelector('.item-critical-input').checked ? 1 : 0,
                            requires_drawing: row.querySelector('.item-draw-input').checked ? 1 : 0 // ADDED THIS LINE
                        });
                    });
                    stageData.append('items', JSON.stringify(items));
                    fetch('api/save_stage.php', {
                            method: 'POST',
                            body: stageData
                        })
                        .then(res => res.json())
                        .then(data => {
                            alert(data.message);
                            if (data.status === 'success' && data.new_stage_id) {
                                stageGroup.dataset.stageId = data.new_stage_id;
                            }
                        })
                        .finally(() => {
                            saveStageBtn.textContent = 'ذخیره مرحله';
                            validateStageWeight(stageGroup);
                        });
                }
            });
            templateList.addEventListener('click', e => {
                const target = e.target;
                // Check if the clicked element is an edit or copy button
                if (target.classList.contains('edit-btn') || target.classList.contains('copy-btn')) {
                    const templateId = target.dataset.id;

                    // Validate the templateId before proceeding
                    if (templateId && parseInt(templateId, 10) > 0) {
                        const isCopy = target.classList.contains('copy-btn');
                        loadTemplateForEditing(templateId, isCopy);
                    } else {
                        // Log an error if the ID is invalid, preventing the bad API call
                        console.error("Invalid or missing template ID on clicked element:", templateId);
                        alert("Could not edit/copy. The template ID is invalid.");
                    }
                }
            });
            document.getElementById('clear-form-btn').addEventListener('click', clearForm);
            form.addEventListener('submit', e => {
                e.preventDefault();
                const formData = new FormData();
                formData.append('template_id', templateIdField.value);
                formData.append('template_name', templateNameField.value);
                formData.append('element_type', elementTypeField.value);
                fetch('api/save_template_meta.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        alert(data.message);
                        if (data.status === 'success') {
                            loadTemplates();
                            if (!templateIdField.value && data.template_id) {
                                templateIdField.value = data.template_id;
                                alert('قالب اصلی ایجاد شد. اکنون می‌توانید مراحل را اضافه و ذخیره کنید.');
                            }
                        }
                    });
            });

            // --- INITIAL LOAD ---
            loadTemplates();
        });
    </script>
    <?php require_once 'footer.php'; ?>

</body>

</html>