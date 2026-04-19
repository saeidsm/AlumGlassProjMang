<?php
// public_html/pardis/meeting_minutes_print.php
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

// 1. --- VALIDATE AND INITIALIZE ---
$meeting_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($meeting_id <= 0) {
    http_response_code(400);
    die("خطا: شناسه صورتجلسه نامعتبر است.");
}

// 2. --- PREPARE VARIABLES ---
$meeting = null;
$meeting_date_timestamp = 'null'; // For JavaScript

try {
    $pdo = getProjectDBConnection('pardis');
    if (!$pdo) throw new Exception("Failed to connect to pardis database");

    // Get the main meeting record
    $sql = "SELECT * FROM meeting_minutes WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($meeting) {
        // Get Creator Name
        if ($creator_id = $meeting['created_by']) {
            $common_pdo = getCommonDBConnection();
            $user_sql = "SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?";
            $user_stmt = $common_pdo->prepare($user_sql);
            $user_stmt->execute([$creator_id]);
            $meeting['creator_name'] = $user_stmt->fetchColumn() ?: 'کاربر یافت نشد';
        } else {
            $meeting['creator_name'] = 'سیستم';
        }

        // Calculate Date Timestamp for JavaScript
        if (!empty($meeting['meeting_date']) && $meeting['meeting_date'] != '0000-00-00') {
            $meeting_date_timestamp = strtotime($meeting['meeting_date']) * 1000;
        }

        // --- THE CRITICAL FIX IS HERE ---
        // The column `row_number` is wrapped in backticks to distinguish it from the SQL keyword.
        $items_sql = "SELECT * FROM meeting_minutes_items WHERE meeting_id = ? ORDER BY `row_number`";
        
        $items_stmt = $pdo->prepare($items_sql);
        $items_stmt->execute([$meeting_id]);
        $meeting['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get Signatures
        $sig_sql = "SELECT * FROM meeting_minutes_signatures WHERE meeting_id = ?";
        $sig_stmt = $pdo->prepare($sig_sql);
        $sig_stmt->execute([$meeting_id]);
        $signatures = $sig_stmt->fetchAll(PDO::FETCH_ASSOC);
        $meeting['signatures'] = [];
        foreach ($signatures as $sig) {
            $meeting['signatures'][$sig['signature_type']] = $sig;
        }
    }
} catch (Exception $e) {
    error_log("FATAL ERROR on meeting_minutes_print.php: " . $e->getMessage());
    $meeting = null;
}

if (!$meeting) {
    http_response_code(404);
    die("خطا: صورتجلسه با شناسه " . htmlspecialchars($meeting_id) . " یافت نشد یا در بارگذاری آن مشکلی رخ داد.");
}

// Prepare remaining variables for HTML
$pages = max(1, min(5, intval($_GET['pages'] ?? 0)));
if ($pages == 1 && !isset($_GET['pages'])) {
    $pages = max(1, min(5, ceil(count($meeting['items']) / 9)));
}

$building_names = ['AG' => 'ساختمان دانشکده کشاورزی', 'LB' => 'ساختمان کتابخانه', 'G' => 'عمومی'];
$building_name = $building_names[$meeting['building_prefix']] ?? 'عمومی';

$meeting['meeting_date_jalali'] = '-';
if (!empty($meeting['meeting_date']) && $meeting['meeting_date'] != '0000-00-00') {
    $date_parts = explode('-', $meeting['meeting_date']);
    $jalali = gregorian_to_jalali($date_parts[0], $date_parts[1], $date_parts[2]);
    $meeting['meeting_date_jalali'] = $jalali[0] . '/' . str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($jalali[2], 2, '0', STR_PAD_LEFT);
}
$display_time = '';
if (!empty($meeting['meeting_time']) && 
    $meeting['meeting_time'] !== '00:00:00' && 
    $meeting['meeting_time'] !== '00:00') {
    // Remove seconds if present
    $display_time = substr($meeting['meeting_time'], 0, 5);
} else {
    $display_time = '____:____';
}
foreach ($meeting['items'] as &$item) {
    $item['deadline_jalali'] = '';
    if (!empty($item['deadline']) && $item['deadline'] != '0000-00-00') {
        $parts = explode('-', $item['deadline']);
        $j = gregorian_to_jalali($parts[0], $parts[1], $parts[2]);
        $item['deadline_jalali'] = $j[0] . '/' . str_pad($j[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($j[2], 2, '0', STR_PAD_LEFT);
    }
}
unset($item);

$rows_per_page = 9;
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>صورتجلسه - چاپ</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@latest/dist/css/persian-datepicker.min.css">

    <style>
        @page {
    size: A4;
    margin: 15mm;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'B Nazanin', 'Tahoma', Arial, sans-serif;
    font-size: 11pt;
    line-height: 1.3;
    color: #000;
    background: white;
}

.page {
    width: 180mm;
    margin: 0 auto 20px auto;
    background: white;
    padding: 0;
    position: relative;
    page-break-after: always;
    padding-bottom: 30px;
}

.page:last-child {
    page-break-after: auto;
}

.page-footer {
    position: absolute;
    bottom: 5px;
    right: 10px;
    font-size: 10pt;
    color: #666;
}

.form-container {
    border: 1px solid #000;
    width: 100%;
    border-collapse: collapse;
}

/* Header */
.form-header {
    display: flex;
    width: 100%;
    border-bottom: 1px solid #000;
}

.header-cell {
    padding: 8px;
    
    
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.header-cell:last-child {
    border-right: 1px solid #000;

}

.header-cell:first-child {
    width: 15%;
    border-left: 1px solid #000;
}

.header-cell:nth-child(2) {
    width: 70%;
}

.header-cell .company-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    margin-top: 10px;
    font-size: 9pt;
}

.header-cell .company-info .left-text {
    text-align: left;
}

.header-cell .company-info .right-text {
    text-align: right;
}

.header-cell:last-child {
    width: 15%;
    font-size: 9pt;
}

.form-title {
    font-size: 16pt;
    font-weight: bold;
    margin: 8px 0;
}

.building-name {
    font-size: 11pt;
    color: #333;
    margin-top: 5px;
}

.logo-box {
    width: 90px;
    height: 90px;
    /*border: 1px solid #999;*/
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.logo-box img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

/* Form rows */
.form-row {
    display: flex;
    width: 100%;
    border-bottom: 1px solid #000;
}

.form-cell {
    padding: 5px 8px;
    border-right: 1px solid #000;
    display: flex;
    align-items: center;
    flex: 1;
}

.form-cell:last-child {
    border-right: none;
}

.form-cell label {
    font-weight: bold;
    margin-left: 5px;
    white-space: nowrap;
}

.form-cell .value {
    display: inline-block;
    min-width: 80px;
    border-bottom: 1px dotted #666;
    min-height: 18px;
}

.form-cell textarea {
    width: 100%;
    min-height: 22px;
    border: none;
    border-bottom: 1px dotted #666;
    font-family: inherit;
    font-size: inherit;
    resize: none;
}

/* Table section */
.table-section {
    border-bottom: 1px solid #000;
}

.table-header {
    display: flex;
    width: 100%;
    background: #f0f0f0;
    border-bottom: 1px solid #000;
}

.table-header-cell {
    padding: 6px 4px;
    border-right: 1px solid #000;
    
    text-align: center;
    font-weight: bold;
    font-size: 10pt;
    display: flex;
    align-items: center;
    justify-content: center;
}

.table-header-cell:last-child {
    border-right: none;
}

.table-header-cell:first-child {
    width: 3%;
    font-size: x-small;
}

.table-header-cell:nth-child(2) {
    width: 78%;
}

.table-header-cell:nth-child(3) {
    width: 9%;
    border-left: 1px solid #000;
}

.table-header-cell:last-child {
    width: 10%;
    
}

.table-row {
    display: flex;
    width: 100%;
    border-bottom: 1px solid #000;
}

.table-row:last-child {
    border-bottom: none;
}

.table-cell {
    padding: 4px;
    border-right: 1px solid #000;
    font-size: 10pt;
    display: flex;
    align-items: center;
}

.table-cell:last-child {
    border-right: none;
    justify-content: center;
    width: 18%;
}

.table-cell:first-child {
    justify-content: center;
    width: 3%;
    font-size: 9pt;
}

.table-cell:nth-child(2) {
    width: 78%;
}

.table-cell:nth-child(3) {
    width: 9%;
    font-size: 9pt;
    border-left: 1px solid #000;
}

.table-cell:last-child {
    width: 10%;
    justify-content: center;
}

.table-cell .content {
    min-height: 40px;
    word-wrap: break-word;
    display: flex;
    align-items: center;
    width: 100%;
}

/* Signatures */
.signature-section {
    width: 100%;
    padding: 10px;
    text-align: center;
}

.signature-box {
    width: 100%;
    padding: 8px;
    text-align: right;
}

.signature-label {
    font-weight: bold;
    margin-bottom: 5px;
    text-align: right;
    font-size: 11pt;
}

.signature-area {
    min-height: 100px;
    margin-top: 1px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.signature-area img {
    max-width: 350px;
    max-height: 70px;
}

/* Print controls */
.print-controls {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    margin-bottom: 15px;
    border-radius: 8px;
}

.print-controls button {
    padding: 8px 25px;
    margin: 0 8px;
    font-size: 13pt;
    font-family: 'B Nazanin', Tahoma;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.btn-print {
    background: #007bff;
    color: white;
}

.btn-close {
    background: #6c757d;
    color: white;
}

.attendee-row .form-cell {
    padding: 3px 8px !important;
    min-height: 30px;
}

.attendee-row .value {
    min-height: 20px !important;
    font-size: 10pt;
}

.editable-field {
    border: 1px dashed #007bff !important;
    background-color: #f8f9fa;
    padding: 2px 5px;
    cursor: text;
    width: auto !important;
    min-width: 100px;
}

.editable-field:focus {
    border: 2px solid #007bff !important;
    background-color: #ffffff;
    outline: none;
}

.edit-controls {
    background: #fff3cd;
    padding: 10px;
    border: 2px solid #ffc107;
    border-radius: 5px;
    margin-bottom: 15px;
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
    margin: 5% auto;
    padding: 20px;
    border: 2px solid #888;
    width: 80%;
    max-width: 600px;
    border-radius: 8px;
    direction: rtl;
    text-align: right;
}

.modal-header {
    padding-bottom: 10px;
    border-bottom: 2px solid #ddd;
    margin-bottom: 15px;
}

.modal-header h3 {
    margin: 0;
    font-size: 16pt;
}

.close {
    color: #aaa;
    float: left;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: #000;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: 'B Nazanin', Tahoma;
    font-size: 11pt;
    min-height: 80px;
}

.btn-save {
    background: #28a745;
    color: white;
    padding: 8px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-family: 'B Nazanin', Tahoma;
    font-size: 12pt;
}

.btn-save:hover {
    background: #218838;
}
.tab-btn {
    background: #f0f0f0;
    border: none;
    padding: 10px 20px;
    margin-left: 5px;
    cursor: pointer;
    font-family: 'B Nazanin', Tahoma;
    font-size: 12pt;
    border-radius: 5px 5px 0 0;
}

.tab-btn.active {
    background: #007bff;
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.attendees-checklist {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 5px;
}

.attendee-item {
    display: flex;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid #eee;
    justify-content: space-between;
}

.attendee-item:hover {
    background: #f8f9fa;
}

.attendee-item input[type="checkbox"] {
    width: 20px;
    height: 20px;
    margin-left: 10px;
    cursor: pointer;
}

.attendee-info {
    flex: 1;
}

.attendee-name {
    font-weight: bold;
    font-size: 11pt;
}

.attendee-role {
    color: #666;
    font-size: 10pt;
    margin-right: 5px;
}

.btn-add {
    background: #28a745;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    font-family: 'B Nazanin', Tahoma;
    font-size: 11pt;
}

.btn-add:hover {
    background: #218838;
}

.btn-delete {
    background: #dc3545;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 10pt;
}

.btn-delete:hover {
    background: #c82333;
}
@media print {
    .print-controls {
        display: none !important;
    }
    
    .edit-controls {
        display: none !important;
    }
    
    .page {
        margin: 0;
        padding: 0;
        width: 100%;
    }
    
    body {
        background: white;
    }
    
    .editable-field {
        border: none !important;
        background-color: transparent !important;
    }
    
    .page {
        margin: 0;
        padding: 0;
        width: 100%;
        height: 100%; /* Ensure page takes full height for absolute positioning */
        position: relative; /* Ensure footer positions relative to this page */
    }
    
    .page-footer {
        position: absolute; /* Change fixed to absolute */
        bottom: 5mm;
        right: 10px; /* Ensure horizontal alignment stays correct */
    }
    
    @page {
        margin: 15mm;
    }
}

@media screen {
    body {
        background: #e0e0e0;
        padding: 15px 0;
    }
}
    </style>
</head>
<body>
    <div class="print-controls">
        <div style="margin-bottom: 15px;">
            <label style="margin-left: 10px; font-weight: bold;">تعداد صفحات:</label>
            <select id="pagesToPrint" style="padding: 5px 15px; font-size: 12pt; font-family: 'B Nazanin', Tahoma;">
                <option value="1" <?php if ($pages == 1) echo 'selected'; ?>>1 صفحه</option>
                <option value="2" <?php if ($pages == 2) echo 'selected'; ?>>2 صفحه</option>
                <option value="3" <?php if ($pages == 3) echo 'selected'; ?>>3 صفحه</option>
                <option value="4" <?php if ($pages == 4) echo 'selected'; ?>>4 صفحه</option>
                <option value="5" <?php if ($pages == 5) echo 'selected'; ?>>5 صفحه</option>
            </select>
            <button class="btn-print" onclick="updatePrintPages()" style="margin-right: 10px;">
                بروزرسانی
            </button>
            <button class="btn-print" onclick="openSettingsModal()" style="margin-right: 10px;">
                ⚙️ تنظیمات حاضرین
            </button>
        </div>
        <button class="btn-print" onclick="window.print()">چاپ صورتجلسه</button>
        <button class="btn-close" onclick="window.close()">بستن</button>
    </div>

    <div class="edit-controls no-print">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-2"><i class="bi bi-pencil"></i> ویرایش سریع</h6>
                <small class="text-muted">برای ویرایش تاریخ یا ساعت، روی فیلدها کلیک کنید</small>
            </div>
            <div>
                <button class="btn btn-success btn-sm" onclick="saveQuickEdit()">
                    <i class="bi bi-check-circle"></i> ذخیره تغییرات
                </button>
                <button class="btn btn-secondary btn-sm" onclick="cancelQuickEdit()">
                    <i class="bi bi-x-circle"></i> انصراف
                </button>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
<div id="attendeesModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <span class="close" onclick="closeAttendeesModal()">&times;</span>
            <h3>انتخاب حاضرین جلسه</h3>
        </div>
        <div class="modal-body">
            <!-- Tabs -->
            <div style="border-bottom: 2px solid #ddd; margin-bottom: 20px;">
                <button class="tab-btn active" onclick="switchTab('employer')" id="tab-employer">
                    حاضرین کارفرما
                </button>
                <button class="tab-btn" onclick="switchTab('supervisor')" id="tab-supervisor">
                    مدیریت راهبردی
                </button>
                <button class="tab-btn" onclick="switchTab('contractor')" id="tab-contractor">
                    حاضرین پیمانکار
                </button>
            </div>

            <!-- Employer Tab -->
            <div id="content-employer" class="tab-content active">
                <div class="attendee-section">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                        <h4>لیست اشخاص</h4>
                        <button class="btn-add" onclick="showAddPersonForm('employer')">
                            ➕ افزودن شخص جدید
                        </button>
                    </div>
                    <div id="add-person-employer" style="display: none; background: #f0f8ff; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <select id="new-company-employer" style="width: 30%; padding: 8px; margin-left: 10px; font-family: 'B Nazanin', Tahoma;">
                            <option value="">انتخاب شرکت...</option>
                            <option value="خاتم پاسارگاد">خاتم پاسارگاد</option>
                            <option value="شارستان">شارستان</option>
                            <option value="طرح و سازه البرز">طرح و سازه البرز</option>
                        </select>
                        <input type="text" id="new-name-employer" placeholder="نام و نام خانوادگی" style="width: 35%; padding: 8px; margin-left: 10px;">
                        <input type="text" id="new-role-employer" placeholder="سمت (اختیاری)" style="width: 20%; padding: 8px; margin-left: 10px;">
                        <button onclick="addNewPerson('employer')" style="padding: 8px 15px;">✓ ذخیره</button>
                        <button onclick="cancelAddPerson('employer')" style="padding: 8px 15px; background: #999;">✗ انصراف</button>
                    </div>
                    <div id="employer-list" class="attendees-checklist"></div>
                </div>
            </div>

            <!-- Supervisor Tab -->
            <div id="content-supervisor" class="tab-content">
                <div class="attendee-section">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                        <h4>لیست اشخاص</h4>
                        <button class="btn-add" onclick="showAddPersonForm('supervisor')">
                            ➕ افزودن شخص جدید
                        </button>
                    </div>
                    <div id="add-person-supervisor" style="display: none; background: #f0f8ff; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <select id="new-company-supervisor" style="width: 30%; padding: 8px; margin-left: 10px; font-family: 'B Nazanin', Tahoma;">
                            <option value="">انتخاب شرکت...</option>
                            <option value="شرکت آلومنیوم شیشه تهران">شرکت آلومنیوم شیشه تهران</option>
                        </select>
                        <input type="text" id="new-name-supervisor" placeholder="نام و نام خانوادگی" style="width: 35%; padding: 8px; margin-left: 10px;">
                        <input type="text" id="new-role-supervisor" placeholder="سمت (اختیاری)" style="width: 20%; padding: 8px; margin-left: 10px;">
                        <button onclick="addNewPerson('supervisor')" style="padding: 8px 15px;">✓ ذخیره</button>
                        <button onclick="cancelAddPerson('supervisor')" style="padding: 8px 15px; background: #999;">✗ انصراف</button>
                    </div>
                    <div id="supervisor-list" class="attendees-checklist"></div>
                </div>
            </div>

            <!-- Contractor Tab -->
            <div id="content-contractor" class="tab-content">
                <div class="attendee-section">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                        <h4>لیست اشخاص</h4>
                        <button class="btn-add" onclick="showAddPersonForm('contractor')">
                            ➕ افزودن شخص جدید
                        </button>
                    </div>
                    <div id="add-person-contractor" style="display: none; background: #f0f8ff; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <select id="new-company-contractor" style="width: 30%; padding: 8px; margin-left: 10px; font-family: 'B Nazanin', Tahoma;">
                            <option value="">انتخاب شرکت...</option>
                            <option value="طرح و نقش آدرم">طرح و نقش آدرم</option>
                            <option value="آران سیج">آران سیج</option>
                        </select>
                        <input type="text" id="new-name-contractor" placeholder="نام و نام خانوادگی" style="width: 35%; padding: 8px; margin-left: 10px;">
                        <input type="text" id="new-role-contractor" placeholder="سمت (اختیاری)" style="width: 20%; padding: 8px; margin-left: 10px;">
                        <button onclick="addNewPerson('contractor')" style="padding: 8px 15px;">✓ ذخیره</button>
                        <button onclick="cancelAddPerson('contractor')" style="padding: 8px 15px; background: #999;">✗ انصراف</button>
                    </div>
                    <div id="contractor-list" class="attendees-checklist"></div>
                </div>
            </div>

            <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #ddd; text-align: left;">
                <button class="btn-save" onclick="saveSelectedAttendees()">
                    💾 ذخیره و اعمال حاضرین
                </button>
                <button class="btn-close" onclick="closeAttendeesModal()" style="background: #6c757d; margin-right: 10px;">
                    بستن
                </button>
            </div>
        </div>
    </div>
</div>

    <?php for ($page = 1; $page <= $pages; $page++): ?>
    <div class="page">
        <div class="form-container">
            <!-- Header (Repeats on every page) -->
            <div class="form-header">
                <div class="header-cell">
                    <div class="logo-box"><img src="assets/images/خاتم.jpg" alt="لوگو"></div>
                </div>
                <div class="header-cell">
                    <div class="form-title">فرم صورتجلسه</div>
                    <div class="building-name"><?php echo htmlspecialchars($building_name ?? ''); ?></div>
                    <div class="company-info">
                        <span class="right-text">کارفرما: موسسه مدیریت راهبردی خاتم پاسارگاد</span>
                        <span class="left-text">مدیریت راهبردی: آلومنیوم شیشه تهران</span>
                    </div>
                </div>
                <div class="header-cell">
                    <div class="logo-box"><img src="assets/images/alumglass-farsi-logo-H40.png" alt="لوگو"></div>
                </div>
            </div>

            <!-- Meeting Info (Repeats on every page) -->
            <div class="form-row">
                <div class="form-cell" style="width: 30%;">
                    <label>شماره صورتجلسه:</label>
                    <span class="value"><?php echo htmlspecialchars($meeting['meeting_number'] ?? ''); ?></span>
                </div>
                <div class="form-cell" style="width: 70%;">
                    <label>دستور جلسه:</label>
                    <span class="value" style="min-width: 250px;"><?php echo ($meeting['status'] !== 'handwritten_pending') ? htmlspecialchars($meeting['agenda'] ?? '') : ''; ?></span>
                </div>
            </div>
            <div class="form-row">
                <div class="form-cell" style="width: 50%;">
                    <label>تاریخ:</label>
                    <input type="text" 
                           class="editable-field persian-datepicker-edit" 
                           id="edit_meeting_date" 
                           value="<?php echo htmlspecialchars($meeting['meeting_date_jalali']); ?>"
                           autocomplete="off">
                </div>
                <div class="form-cell" style="width: 50%;">
                    <label>ساعت:</label>
                    <input type="text" 
       class="editable-field" 
       id="edit_meeting_time" 
       value="<?php echo htmlspecialchars($display_time); ?>"
       placeholder="HH:MM"
       autocomplete="off">
                </div>
            </div>

            <div class="form-row">
                <div class="form-cell">
                    <label>محل تشکیل جلسه:</label>
                    <span class="value" style="min-width: 350px;"><?php echo htmlspecialchars($meeting['location'] ?? ''); ?></span>
                </div>
            </div>
            <div class="form-row attendee-row">
                <div class="form-cell">
                    <label>حاضرین کارفرما:</label>
                    <span class="value editable-attendees" id="attendees_display" style="width: 100%;"><?php echo htmlspecialchars($meeting['attendees'] ?? ''); ?></span>
                </div>
            </div>
            <div class="form-row attendee-row">
                <div class="form-cell">
                    <label>حاضرین مدیریت راهبردی:</label>
                    <span class="value editable-attendees" id="observers_display" style="width: 100%;"><?php echo htmlspecialchars($meeting['observers'] ?? ''); ?></span>
                </div>
            </div>
            <div class="form-row attendee-row">
                <div class="form-cell">
                    <label>حاضرین پیمانکار:</label>
                    <span class="value editable-attendees" id="contractor_display" style="width: 100%;"><?php echo htmlspecialchars($meeting['contractor'] ?? ''); ?></span>
                </div>
            </div>

            <!-- Items Table -->
            <div class="table-section">
                <div class="table-header">
                    <div class="table-header-cell">ردیف</div>
                    <div class="table-header-cell">خلاصه مذاکرات و تصمیمات متخذه</div>
                    <div class="table-header-cell">پیگیری کننده</div>
                    <div class="table-header-cell">تاریخ سررسید</div>
                </div>
                
                <?php
                $start_row = ($page - 1) * $rows_per_page + 1;
                $end_row = $page * $rows_per_page;
                
                for ($i = $start_row; $i <= $end_row; $i++):
                    // FIX: Check if item exists (array is 0-indexed)
                    $item_index = $i - 1;
                    $item = isset($meeting['items'][$item_index]) ? $meeting['items'][$item_index] : null;
                ?>
                <div class="table-row">
                    <div class="table-cell"><?php echo $i; ?></div>
                    <div class="table-cell">
                        <div class="content"><?php echo $item ? nl2br(htmlspecialchars($item['description'] ?? '')) : ''; ?></div>
                    </div>
                    <div class="table-cell">
                        <div class="content"><?php echo $item ? htmlspecialchars($item['follower'] ?? '') : ''; ?></div>
                    </div>
                    <div class="table-cell">
                        <div class="content"><?php echo $item ? htmlspecialchars($item['deadline_jalali'] ?? '') : ''; ?></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Signatures -->
            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-label">محل امضای حاضرین:</div>
                    <div class="signature-area">
                        <?php if ($page == $pages && isset($meeting['signatures']['attendee'])): ?>
                            <!-- Only show actual signature on last page -->
                            <img src="<?php echo $meeting['signatures']['attendee']['signature_data']; ?>" alt="امضا">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="page-footer">صفحه <?php echo $page; ?> از <?php echo $pages; ?></div>
    </div>
    <?php endfor; ?>

    <script>
    function updatePrintPages() {
        const pages = document.getElementById('pagesToPrint').value;
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('pages', pages);
        currentUrl.searchParams.delete('blank');
        currentUrl.searchParams.delete('prefix');
        window.location.href = currentUrl.toString();
    }

    <?php if (isset($_GET['auto_print'])): ?>
    window.onload = function() { setTimeout(function() { window.print(); }, 500); };
    <?php endif; ?>
    </script>
    <script src="/assets/js/persian-date.min.js"></script>
    <script src="/assets/js/persian-datepicker.min.js"></script>

    <script>
    var initialTimestamp = <?php echo json_encode($meeting_date_timestamp); ?>;

    $(document).ready(function() {
        var datepickerInstance = $("#edit_meeting_date").persianDatepicker({
            format: 'YYYY/MM/DD',
            autoClose: true,
            persianDigit: false,
            calendar: {
                persian: { 
                    locale: 'fa',
                    leapYearMode: 'astronomical' 
                }
            }
        });

        if (initialTimestamp) {
            try {
                datepickerInstance.setDate(initialTimestamp);
                console.log('SUCCESS: Datepicker was set using the PHP-generated timestamp.');
            } catch(e) {
                console.error('ERROR: Failed to set date from timestamp.', e);
            }
        }
    });

    function convertPersianToEnglish(str) {
        if (!str) return str;
        const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        const englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        let result = str.toString();
        for (let i = 0; i < 10; i++) {
            result = result.replace(new RegExp(persianNumbers[i], 'g'), englishNumbers[i]);
            result = result.replace(new RegExp(arabicNumbers[i], 'g'), englishNumbers[i]);
        }
        return result;
    }

    function saveQuickEdit() {
    const meetingId = <?php echo $meeting_id; ?>;
    const newDate = convertPersianToEnglish(document.getElementById('edit_meeting_date').value);
    let newTime = document.getElementById('edit_meeting_time').value.trim();
    
    // Convert empty or placeholder time to NULL
    if (!newTime || newTime === '____:____' || newTime === '00:00' || newTime === '00:00:00') {
        newTime = '';
    }
    
    if (!newDate || newDate === '____/____/____') {
        alert('لطفاً تاریخ را وارد کنید');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_meeting_date_time');
    formData.append('meeting_id', meetingId);
    formData.append('meeting_date', newDate);
    formData.append('meeting_time', newTime);
    
    const saveBtn = event.target;
    const originalHTML = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> در حال ذخیره...';
    
    fetch('form_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            alert('✅ تغییرات با موفقیت ذخیره شد');
            location.reload();
        } else {
            alert('❌ خطا: ' + result.message);
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در ارتباط با سرور');
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalHTML;
    });
}
let attendeesData = {
    employer: [],
    supervisor: [],
    contractor: []
};

let currentTab = 'employer';

// Load attendees when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadAttendeesList();
});

function loadAttendeesList() {
    fetch('form_api.php?action=get_attendees_list')
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                // Group by category
                attendeesData = {
                    employer: result.data.filter(a => a.category === 'employer'),
                    supervisor: result.data.filter(a => a.category === 'supervisor'),
                    contractor: result.data.filter(a => a.category === 'contractor')
                };
                renderAllLists();
            }
        })
        .catch(error => console.error('Error loading attendees:', error));
}

function renderAllLists() {
    renderAttendeeList('employer');
    renderAttendeeList('supervisor');
    renderAttendeeList('contractor');
    
    // Pre-check based on current values
    preCheckAttendees();
}

function renderAttendeeList(category) {
    const listEl = document.getElementById(`${category}-list`);
    if (!listEl) return;
    
    const attendees = attendeesData[category] || [];
    
    if (attendees.length === 0) {
        listEl.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">هیچ شخصی ثبت نشده است. روی دکمه افزودن کلیک کنید.</p>';
        return;
    }
    
    // Group by company
    const grouped = {};
    attendees.forEach(person => {
        const company = person.company || 'سایر';
        if (!grouped[company]) grouped[company] = [];
        grouped[company].push(person);
    });
    
    let html = '';
    Object.keys(grouped).forEach(company => {
        html += `<div style="margin-bottom: 20px;">
            <h5 style="background: #007bff; color: white; padding: 8px; margin: 0 0 10px 0; border-radius: 3px;">${company}</h5>`;
        
        grouped[company].forEach(person => {
            html += `
            <div class="attendee-item">
                <input type="checkbox" 
                       id="person-${category}-${person.id}" 
                       data-name="${person.name}" 
                       data-role="${person.role || ''}"
                       data-company="${person.company || ''}"
                       data-category="${category}">
                <label for="person-${category}-${person.id}" style="flex: 1; cursor: pointer; display: flex; align-items: center;">
                    <span class="attendee-info">
                        <span class="attendee-name">${person.name}</span>
                        ${person.role ? `<span class="attendee-role">- ${person.role}</span>` : ''}
                    </span>
                </label>
                <button class="btn-delete" onclick="deleteAttendee(${person.id}, '${category}')">🗑️ حذف</button>
            </div>`;
        });
        
        html += '</div>';
    });
    
    listEl.innerHTML = html;
}

function preCheckAttendees() {
    const employerText = document.getElementById('attendees_display').textContent.trim();
    const supervisorText = document.getElementById('observers_display').textContent.trim();
    const contractorText = document.getElementById('contractor_display').textContent.trim();
    
    // Check employer
    document.querySelectorAll('[data-category="employer"]').forEach(cb => {
        if (employerText.includes(cb.dataset.name)) {
            cb.checked = true;
        }
    });
    
    // Check supervisor
    document.querySelectorAll('[data-category="supervisor"]').forEach(cb => {
        if (supervisorText.includes(cb.dataset.name)) {
            cb.checked = true;
        }
    });
    
    // Check contractor
    document.querySelectorAll('[data-category="contractor"]').forEach(cb => {
        if (contractorText.includes(cb.dataset.name)) {
            cb.checked = true;
        }
    });
}

function switchTab(tab) {
    currentTab = tab;
    
    // Update buttons
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`tab-${tab}`).classList.add('active');
    
    // Update content
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById(`content-${tab}`).classList.add('active');
}

function showAddPersonForm(category) {
    document.getElementById(`add-person-${category}`).style.display = 'block';
}

function cancelAddPerson(category) {
    document.getElementById(`add-person-${category}`).style.display = 'none';
    document.getElementById(`new-company-${category}`).value = '';
    document.getElementById(`new-name-${category}`).value = '';
    document.getElementById(`new-role-${category}`).value = '';
}

function addNewPerson(category) {
    const company = document.getElementById(`new-company-${category}`).value.trim();
    const name = document.getElementById(`new-name-${category}`).value.trim();
    const role = document.getElementById(`new-role-${category}`).value.trim();
    
    if (!company) {
        alert('لطفاً شرکت را انتخاب کنید');
        return;
    }
    
    if (!name) {
        alert('لطفاً نام را وارد کنید');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'save_attendee');
    formData.append('company', company);
    formData.append('name', name);
    formData.append('role', role);
    formData.append('category', category);
    
    fetch('form_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            cancelAddPerson(category);
            loadAttendeesList();
            alert('✅ شخص با موفقیت اضافه شد');
        } else {
            alert('❌ خطا: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در ارتباط با سرور');
    });
}

function deleteAttendee(id, category) {
    if (!confirm('آیا از حذف این شخص اطمینان دارید؟')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_attendee');
    formData.append('id', id);
    
    fetch('form_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            loadAttendeesList();
            alert('✅ شخص با موفقیت حذف شد');
        } else {
            alert('❌ خطا: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در ارتباط با سرور');
    });
}

function saveSelectedAttendees() {
    const employerChecked = {};
    const supervisorChecked = {};
    const contractorChecked = {};
    
    // Group by company
    document.querySelectorAll('[data-category="employer"]:checked').forEach(cb => {
        const company = cb.dataset.company || 'سایر';
        if (!employerChecked[company]) employerChecked[company] = [];
        const text = cb.dataset.role ? 
            `${cb.dataset.name} (${cb.dataset.role})` : 
            cb.dataset.name;
        employerChecked[company].push(text);
    });
    
    document.querySelectorAll('[data-category="supervisor"]:checked').forEach(cb => {
        const company = cb.dataset.company || 'سایر';
        if (!supervisorChecked[company]) supervisorChecked[company] = [];
        const text = cb.dataset.role ? 
            `${cb.dataset.name} (${cb.dataset.role})` : 
            cb.dataset.name;
        supervisorChecked[company].push(text);
    });
    
    document.querySelectorAll('[data-category="contractor"]:checked').forEach(cb => {
        const company = cb.dataset.company || 'سایر';
        if (!contractorChecked[company]) contractorChecked[company] = [];
        const text = cb.dataset.role ? 
            `${cb.dataset.name} (${cb.dataset.role})` : 
            cb.dataset.name;
        contractorChecked[company].push(text);
    });
    
    // Format with company names
    const formatByCompany = (grouped) => {
        return Object.keys(grouped).map(company => {
            return `${company}: ${grouped[company].join('، ')}`;
        }).join('\n');
    };
    
    const employerText = formatByCompany(employerChecked);
    const supervisorText = formatByCompany(supervisorChecked);
    const contractorText = formatByCompany(contractorChecked);
    
    const formData = new FormData();
    formData.append('action', 'save_selected_attendees');
    formData.append('meeting_id', <?php echo $meeting_id; ?>);
    formData.append('attendees', employerText);
    formData.append('observers', supervisorText);
    formData.append('contractor', contractorText);
    
    fetch('form_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            // Update display
            document.getElementById('attendees_display').innerHTML = employerText.replace(/\n/g, '<br>');
            document.getElementById('observers_display').innerHTML = supervisorText.replace(/\n/g, '<br>');
            document.getElementById('contractor_display').innerHTML = contractorText.replace(/\n/g, '<br>');
            
            closeAttendeesModal();
            alert('✅ حاضرین با موفقیت ذخیره شد');
        } else {
            alert('❌ خطا: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در ارتباط با سرور');
    });
}

function openAttendeesModal() {
    loadAttendeesList();
    document.getElementById('attendeesModal').style.display = 'block';
}

function closeAttendeesModal() {
    document.getElementById('attendeesModal').style.display = 'none';
}

// Update the button in print controls
function openSettingsModal() {
    openAttendeesModal();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('attendeesModal');
    if (event.target == modal) {
        closeAttendeesModal();
    }
}
    </script>
</body>
</html>