<?php
// public_html/pardis/meeting_minutes_form.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

$expected_project_key = 'pardis';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}

$user_role = $_SESSION['role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? 0;
$pardis_pdo = getProjectDBConnection('pardis');
$letter_pdo = getLetterTrackingDBConnection();

function getCompanies($pdo) {
    $stmt = $pdo->query("SELECT id, name FROM companies ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$companies = getCompanies($letter_pdo);
$current_jalali = jdate('Y/m/d');

$pageTitle = "فرم صورتجلسه - پروژه دانشگاه خاتم پردیس";

function isMobileDevices() {
    return preg_match(
        "/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i",
        $_SERVER["HTTP_USER_AGENT"]
    );
}

if (isMobileDevices()) {
    require_once __DIR__ . '/header_p_mobile.php';
} else {
    require_once __DIR__ . '/header_pardis.php';
}

$current_gregorian = date('Y-m-d');
$current_parts = explode('-', $current_gregorian);
$jalali = gregorian_to_jalali($current_parts[0], $current_parts[1], $current_parts[2]);
$current_jalali = $jalali[0] . '/' . str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($jalali[2], 2, '0', STR_PAD_LEFT);
?>

<link rel="stylesheet" href="/assets/css/persian-datepicker-dark.min.css">

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white; }
    .form-container { box-shadow: none !important; border: 2px solid #000 !important; }
    input, textarea { border-bottom: 1px solid #000 !important; }
}

.form-container {
    background: white;
    max-width: 210mm;
    margin: 20px auto;
    padding: 0;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
    border: 2px solid #333;
}

.form-header {
    display: grid;
    grid-template-columns: 1fr 2fr 1fr;
    border-bottom: 2px solid #333;
}

.form-header > div {
    padding: 15px;
    border-left: 2px solid #333;
}

.form-header > div:first-child {
    border-left: none;
}

.form-header > div:last-child {
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-placeholder {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-placeholder img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.form-title {
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    padding: 20px;
}

.form-row {
    display: grid;
    border-bottom: 2px solid #333;
}

.form-row.attendee-row {
    min-height: 40px !important;
}

.form-cell textarea.small-textarea {
    min-height: 35px !important;
    max-height: 50px;
}

.form-row.two-cols {
    grid-template-columns: 1fr 1fr;
}

.form-row.three-cols {
    grid-template-columns: 1fr 2fr 1fr;
}

.form-cell {
    padding: 10px;
    border-left: 2px solid #333;
    display: flex;
    align-items: center;
}

.form-cell:first-child {
    border-left: none;
}

.form-cell label {
    font-weight: bold;
    margin-left: 5px;
    white-space: nowrap;
}

.form-cell input,
.form-cell textarea,
.form-cell select {
    flex: 1;
    border: none;
    border-bottom: 1px solid #999;
    padding: 5px;
    font-family: inherit;
    background: transparent;
}

.form-cell input:focus,
.form-cell textarea:focus,
.form-cell select:focus {
    outline: none;
    border-bottom: 2px solid #007bff;
}

.number-input-group {
    display: flex;
    gap: 5px;
    flex: 1;
}

.number-input-group select {
    width: 80px;
    flex: 0 0 auto;
}

.number-input-group input {
    flex: 1;
}

.table-section {
    border-bottom: 2px solid #333;
}

.table-header {
    display: grid;
    grid-template-columns: 60px 1fr 150px 100px;
    background: #f0f0f0;
    border-bottom: 2px solid #333;
    font-weight: bold;
}

.table-header > div {
    padding: 10px;
    border-left: 2px solid #333;
    text-align: center;
}

.table-header > div:first-child {
    border-left: none;
}

.table-row {
    display: grid;
    grid-template-columns: 60px 1fr 150px 100px;
    border-bottom: 1px solid #999;
}

.table-row:last-child {
    border-bottom: 2px solid #333;
}

.table-cell {
    padding: 5px;
    border-left: 2px solid #333;
}

.table-cell:first-child {
    border-left: none;
}

.table-cell input,
.table-cell textarea {
    width: 100%;
    border: none;
    border-bottom: 1px dotted #ccc;
    padding: 3px;
    font-family: inherit;
    background: transparent;
    min-height: 60px;
}

.signature-section {
    display: block;
    padding: 10px;
    border-bottom: 2px solid #333;
}

.signature-section.single-signature {
    text-align: center;
}

.signature-box.full-width {
    width: 100%;
    margin: 0 auto;
}

.signature-box {
    padding: 10px;
    text-align: right;
}

.signature-label {
    font-weight: bold;
    margin-bottom: 5px;
    text-align: right;
    font-size: 11pt;
}

.signature-canvas {
    border: 2px dashed #999;
    width: 100%;
    height: 150px;
    cursor: crosshair;
    background: white;
}

.signature-buttons {
    margin-top: 10px;
    display: flex;
    gap: 10px;
    justify-content: center;
}

.control-panel {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-family: 'Vazir', Tahoma;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
}

.btn-warning {
    background: #ffc107;
    color: #333;
}

.btn-warning:hover {
    background: #e0a800;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}

.btn-info {
    background: #17a2b8;
    color: white;
}

.btn-info:hover {
    background: #138496;
}

.mode-toggle {
    background: white;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.toggle-switch {
    display: flex;
    gap: 10px;
    align-items: center;
}

.page-number {
    text-align: left;
    font-size: 14px;
    color: #666;
}

.page-indicator {
    background: #f8f9fa;
    padding: 8px 15px;
    margin: 10px 0;
    border-left: 4px solid #007bff;
    font-weight: bold;
}

.dynamic-row {
    position: relative;
}

.row-delete-btn {
    position: absolute;
    left: 5px;
    top: 50%;
    transform: translateY(-50%);
    background: #dc3545;
    color: white;
    border: none;
    padding: 2px 6px;
    font-size: 10px;
    cursor: pointer;
    border-radius: 3px;
    z-index: 10;
}

.row-delete-btn:hover {
    background: #c82333;
}

.ocr-editor {
    background: #fff;
    border: 2px solid #007bff;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.ocr-field-group {
    margin-bottom: 15px;
}

.ocr-field-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}

.ocr-field-group input,
.ocr-field-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
}

.ocr-field-group textarea {
    min-height: 80px;
}

@media screen and (max-width: 768px) {
    .form-container {
        max-width: 100%;
        margin: 10px;
        font-size: 14px;
    }
    
    .form-header {
        grid-template-columns: 1fr;
    }
    
    .form-header > div {
        border-left: none;
        border-bottom: 2px solid #333;
    }
    
    .form-header > div:last-child {
        border-bottom: none;
    }
    
    .page-number {
        text-align: center;
        font-size: 12px;
    }
    
    .form-title {
        font-size: 18px;
        padding: 10px;
    }
    
    .logo-placeholder {
        width: 60px;
        height: 60px;
        margin: 0 auto;
    }
    
    .form-row.two-cols,
    .form-row.three-cols {
        grid-template-columns: 1fr;
    }
    
    .form-cell {
        border-left: none;
        border-bottom: 1px solid #ddd;
        flex-direction: column;
        align-items: flex-start;
        padding: 8px;
    }
    
    .form-cell:last-child {
        border-bottom: none;
    }
    
    .form-cell label {
        margin-bottom: 5px;
        display: block;
        width: 100%;
    }
    
    .form-cell input,
    .form-cell textarea,
    .form-cell select {
        width: 100%;
        margin-top: 5px;
    }
    
    .number-input-group {
        width: 100%;
    }
    
    .number-input-group select {
        width: 100px;
    }
    
    .table-header,
    .table-row {
        grid-template-columns: 1fr;
        display: block;
    }
    
    .table-header > div,
    .table-cell {
        border-left: none;
        border-bottom: 1px solid #ddd;
        display: block;
        padding: 8px;
    }
    
    .table-cell:before {
        content: attr(data-label);
        font-weight: bold;
        display: block;
        margin-bottom: 5px;
        color: #333;
    }
    
    .table-cell input,
    .table-cell textarea {
        width: 100%;
        min-height: 40px;
    }
    
    .control-panel {
        padding: 10px;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 10px;
        padding: 12px 15px;
        font-size: 16px;
    }
    
    .mode-toggle {
        flex-direction: column;
        gap: 10px;
    }
    
    .toggle-switch {
        flex-direction: column;
        width: 100%;
    }
    
    .toggle-switch button {
        width: 100%;
    }
    
    .signature-canvas {
        width: 100%;
        height: 200px;
        touch-action: none;
    }
    
    .signature-buttons {
        flex-direction: column;
    }
    
    .signature-buttons button {
        width: 100%;
    }
    
    #pageControls .row {
        flex-direction: column;
    }
    
    #pageControls .col-md-4 {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .form-select,
    .form-control {
        font-size: 16px;
        padding: 10px;
    }
    
    #uploadSection .card-body {
        padding: 10px;
    }
    
    #uploadSection .row {
        flex-direction: column;
    }
    
    #uploadSection .col-md-6,
    #uploadSection .col-md-3,
    #uploadSection .col-md-7,
    #uploadSection .col-md-5,
    #uploadSection .col-md-12 {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .row-delete-btn {
        position: static;
        display: block;
        width: 100%;
        margin-top: 5px;
        padding: 8px;
        font-size: 14px;
    }
}

@media print {
    .print-only-signatures {
        display: grid !important;
    }
    .no-print-signatures {
        display: none !important;
    }
}
</style>

<div class="container-fluid mt-4">
    <div class="no-print">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-file-text"></i> فرم صورتجلسه</h2>
            <a href="saved_minutes_list.php" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i> بازگشت به لیست فرم‌ها
            </a>
        </div>

        <div class="mode-toggle">
            <div>
                <strong>حالت فرم:</strong>
            </div>
            <div class="toggle-switch">
                <button class="btn btn-sm btn-primary" id="digitalModeBtn" onclick="setMode('digital')">
                    <i class="bi bi-laptop"></i> پر کردن دیجیتال
                </button>
                <button class="btn btn-sm btn-secondary" id="externalModeBtn" onclick="setMode('external')">
                    <i class="bi bi-cloud-upload"></i> ثبت صورتجلسه خارجی
                </button>
                <button class="btn btn-sm btn-secondary" id="printModeBtn" onclick="setMode('print')">
                    <i class="bi bi-printer"></i> چاپ فرم خالی
                </button>
                <button class="btn btn-sm btn-secondary" id="uploadModeBtn" onclick="setMode('upload')">
                    <i class="bi bi-upload"></i> بارگذاری فرم دستی
                </button>
            </div>
        </div>

        <!-- External Section with OCR Editor -->
        <div id="externalSection" style="display: none;">
            <div class="control-panel">
                <h5 class="mb-3"><i class="bi bi-cloud-upload"></i> ثبت و بارگذاری صورتجلسه خارجی</h5>
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">شماره صورتجلسه خارجی <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="externalMeetingNumber" placeholder="e.g., ABC-MOM-2025-01" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">شرکت <span class="text-danger">*</span></label>
                                <select class="form-select" id="externalCompanyId" required>
                                    <option value="">-- انتخاب کنید --</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">نامه مرتبط (اختیاری)</label>
                                <input type="text" class="form-control" id="letterSearch" placeholder="جستجوی شماره یا موضوع نامه...">
                                <input type="hidden" id="externalLetterId">
                                <div id="letterSearchResults" class="list-group mt-1 position-absolute w-auto" style="z-index: 1000;"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">موضوع/دستور جلسه <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="externalAgenda" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">تاریخ جلسه <span class="text-danger">*</span></label>
                                <input type="text" class="form-control persian-datepicker-upload" id="externalMeetingDate" value="<?= $current_jalali ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">فایل اسکن شده <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="externalFile" accept=".pdf,.jpg,.jpeg,.png" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">متن استخراج شده (OCR)</label>
                                <textarea class="form-control" id="externalOcrText" rows="8" placeholder="متن کپی شده از فایل اسکن را اینجا وارد کنید تا قابل جستجو باشد..."></textarea>
                            </div>
                        </div>
                        <div class="text-end">
                            <button class="btn btn-primary" onclick="uploadExternalMinute()">
                                <i class="bi bi-upload"></i> بارگذاری و ثبت نهایی
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="control-panel" id="pageControls">
            <h5 class="mb-3"><i class="bi bi-files"></i> مدیریت صفحات</h5>
            <div class="row align-items-center">
                <div class="col-md-4">
                    <label class="form-label">تعداد صفحات فرم:</label>
                    <select class="form-select" id="pageCount" onchange="updatePageCount()">
                        <option value="1">1 صفحه (9 ردیف)</option>
                        <option value="2">2 صفحه (18 ردیف)</option>
                        <option value="3">3 صفحه (27 ردیف)</option>
                        <option value="4">4 صفحه (36 ردیف)</option>
                        <option value="5">5 صفحه (45 ردیف)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-success" onclick="addNewRow()">
                        <i class="bi bi-plus-circle"></i> افزودن ردیف جدید
                    </button>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-info mb-0" style="padding: 8px;">
                        <small>ردیف‌های فعلی: <strong id="currentRowCount">9</strong></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="control-panel" id="digitalControls">
            <div class="btn-group">
                <button class="btn btn-primary" onclick="saveDraft()">
                    <i class="bi bi-save"></i> ذخیره پیش‌نویس
                </button>
                <button class="btn btn-success" onclick="saveAndGeneratePDF()">
                    <i class="bi bi-file-pdf-fill"></i> ذخیره و تولید PDF
                </button>
                <button class="btn btn-warning" onclick="saveAndPrint()">
                    <i class="bi bi-printer-fill"></i> ذخیره و چاپ
                </button>
                <button class="btn btn-info" onclick="openPrintPage()">
                    <i class="bi bi-printer"></i> صفحه چاپ اختصاصی
                </button>
                <button class="btn btn-secondary" onclick="clearForm()">
                    <i class="bi bi-x-circle"></i> پاک کردن فرم
                </button>
            </div>
        </div>

        <div class="control-panel" id="printControls" style="display: none;">
            <div class="btn-group">
                <button class="btn btn-success" onclick="downloadBlankForm()">
                    <i class="bi bi-download"></i> دریافت و چاپ فرم با شماره
                </button>
            </div>
        </div>

        <!-- Upload Section with OCR Editor -->
        <div class="control-panel" id="uploadSection" style="display: none;">
            <h5 class="mb-3"><i class="bi bi-upload"></i> بارگذاری فرم پر شده دستی</h5>
            
            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>1. انتخاب صورتجلسه در انتظار:</strong> <span class="text-danger">*</span></label>
                        <select class="form-select" id="pendingMeetingSelect" onchange="fillUploadFormDetails(this)">
                            <option value="">-- یک مورد را انتخاب کنید --</option>
                        </select>
                    </div>
                    
                    <div id="uploadDetails" style="display: none;">
                        <!-- OCR Editor -->
                        <div class="ocr-editor mb-4">
                            <h6 class="mb-3"><i class="bi bi-robot"></i> ویرایشگر OCR (اختیاری)</h6>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label>متن خام OCR:</label>
                                    <textarea class="form-control" id="ocrRawText" rows="4" placeholder="متن استخراج شده از OCR را اینجا کپی کنید..."></textarea>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-info" onclick="parseOCRText()">
                                <i class="bi bi-magic"></i> تجزیه خودکار
                            </button>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>دستور جلسه / موضوع:</strong> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="uploadAgenda" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label"><strong>تاریخ جلسه:</strong> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control persian-datepicker-upload" id="uploadMeetingDate" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label"><strong>ساعت جلسه:</strong></label>
                                <input type="text" class="form-control" id="uploadMeetingTime">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label"><strong>محل تشکیل جلسه:</strong></label>
                                <input type="text" class="form-control" id="uploadLocation">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><strong>حاضرین کارفرما:</strong></label>
                                <textarea class="form-control" id="uploadAttendees" rows="2"></textarea>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><strong>حاضرین نظارت:</strong></label>
                                <textarea class="form-control" id="uploadObservers" rows="2"></textarea>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><strong>حاضرین پیمانکار:</strong></label>
                                <textarea class="form-control" id="uploadContractor" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="row align-items-end">
                            <div class="col-md-7">
                                <label class="form-label"><strong>2. انتخاب فایل اسکن شده:</strong></label>
                                <input type="file" class="form-control" id="handwrittenFile" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <div class="col-md-5">
                                <button class="btn btn-primary w-100" onclick="uploadHandwrittenForm()">
                                    <i class="bi bi-cloud-upload"></i> 3. بارگذاری و تکمیل نهایی
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="uploadProgress" style="display: none;" class="mt-3">
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="form-container" id="meetingForm">
        <!-- Header -->
        <div class="form-header">
            <div>
                <div class="logo-placeholder">
                    <img src="assets/images/خاتم.jpg" alt="لوگو">
                </div>
            </div>
            <div class="form-title">
                فرم صورتجلسه
            </div>
            <div class="page-number">
                صفحه ۱ از ۱
            </div>
        </div>

        <!-- Meeting Info -->
        <div class="form-row three-cols">
            <div class="form-cell">
                <label>شماره :</label>
                <div class="number-input-group">
                    <select id="buildingPrefix" name="buildingPrefix" onchange="generateMeetingNumber(); updateDefaultValues();">
                        <option value="AG">AG-</option>
                        <option value="LB">LB-</option>
                         <option value="SK">SK-</option>
                        <option value="G">G-</option>
                    </select>
                    <input type="text" id="meetingNumber" name="meetingNumber" readonly style="background-color: #f8f9fa;">
                </div>
            </div>
            <div class="form-cell">
                <label>دستور جلسه:</label>
                <input type="text" id="agenda" name="agenda" style="flex: 2;">
            </div>
            <div class="form-cell"></div>
        </div>

        <div class="form-row two-cols">
            <div class="form-cell">
                <label>تاریخ:</label>
                <input type="text" class="persian-datepicker-main" id="meetingDate" name="meetingDate" placeholder="انتخاب تاریخ">
            </div>
            <div class="form-cell">
                <label>ساعت:</label>
                <input type="text" id="meetingTime" name="meetingTime" value="<?php echo date('H:i'); ?>">
            </div>
        </div>

        <!-- Location -->
        <div class="form-row">
            <div class="form-cell">
                <label>محل تشکیل جلسه:</label>
                <input type="text" id="location" name="location">
            </div>
        </div>

        <!-- Attendees -->
        <div class="form-row attendee-row">
            <div class="form-cell">
                <label>حاضرین کارفرما:</label>
                <textarea id="attendees" name="attendees" class="small-textarea" rows="1"></textarea>
            </div>
        </div>

        <div class="form-row attendee-row">
            <div class="form-cell">
                <label>حاضرین نظارت:</label>
                <textarea id="observers" name="observers" class="small-textarea" rows="1"></textarea>
            </div>
        </div>

        <div class="form-row attendee-row">
            <div class="form-cell">
                <label>حاضرین پیمانکار:</label>
                <textarea id="contractor" name="contractor" class="small-textarea" rows="1"></textarea>
            </div>
        </div>

        <!-- Items Table -->
       <div class="table-section">
    <div class="table-header">
        <div>ردیف</div>
        <div>خلاصه مذاکرات و تصمیمات متخذه:</div>
        <div>پیگیری کننده</div>
        <div>تاریخ سررسید</div>
    </div>
    
    <div id="tableRowsContainer">
        <!-- Rows will be generated by JavaScript -->
    </div>
</div>
        <!-- Signatures -->
        <div class="signature-section single-signature no-print-signatures">
            <div class="signature-box full-width">
                <div class="signature-label">محل امضای حاضرین:</div>
                <canvas class="signature-canvas" id="attendeeSignature" width="500" height="150"></canvas>
                <div class="signature-buttons">
                    <button class="btn btn-sm btn-warning" onclick="clearSignature('attendeeSignature')">
                        <i class="bi bi-x"></i> پاک کردن
                    </button>
                </div>
            </div>
        </div>

        <div class="signature-section single-signature print-only-signatures" style="display: none;">
            <div class="signature-box full-width">
                <div class="signature-label">محل امضای حاضرین:</div>
                <div style="border: 2px dashed #999; height: 150px;"></div>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/persian-date.min.js"></script>
<script src="/assets/js/persian-datepicker.min.js"></script>

<script>
let currentMode = 'digital';
let signatureCanvases = {};
let isEditingExisting = false;
let originalMeetingNumber = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Persian datepickers for main date field
    $('.persian-datepicker-main').persianDatepicker({
        format: 'YYYY/MM/DD',
        autoClose: true,
        initialValue: true,
        initialValueType: 'persian',
        persianDigit: false,
        calendar: { 
            persian: { 
                locale: 'fa',
                leapYearMode: 'astronomical'
            } 
        }
    });
    
    // Set initial value to today
    $('#meetingDate').val('<?php echo $current_jalali; ?>');
    
    // Generate initial 9 rows
    generateTableRows(9);
    
    // Initialize signature canvas
    initSignatureCanvas('attendeeSignature');
    
    // Generate initial meeting number
    generateMeetingNumber();
    
    // Update default values
    updateDefaultValues();

    // Load any saved draft
    loadDraft();
    
    // Check if editing existing meeting
    const urlParams = new URLSearchParams(window.location.search);
    const meetingId = urlParams.get('id');
    if (meetingId) {
        isEditingExisting = true;
        loadMeetingForEdit(meetingId);
    }
    
    setupLetterSearch();
    
    // Setup upload date picker
    $('.persian-datepicker-upload').persianDatepicker({
        format: 'YYYY/MM/DD',
        autoClose: true,
        initialValue: true,
        initialValueType: 'persian',
        persianDigit: false,
        calendar: { 
            persian: { 
                locale: 'fa',
                leapYearMode: 'astronomical'
            } 
        }
    });
    
    $('#uploadMeetingDate, #externalMeetingDate').val('<?php echo $current_jalali; ?>');
});

function loadMeetingForEdit(meetingId) {
    fetch('form_api.php?action=get_meeting_minutes&id=' + meetingId)
    .then(res => res.json())
    .then(result => {
        if (!result.success || !result.data) {
            alert('خطا در بارگذاری صورتجلسه');
            return;
        }
        
        const meeting = result.data;
        
        // Store the meeting ID and original meeting number
        localStorage.setItem('current_meeting_id', meetingId);
        originalMeetingNumber = meeting.meeting_number; // Store original number
        isEditingExisting = true;
        
        // Fill form fields
        if (meeting.building_prefix) {
            const prefixElement = document.getElementById('buildingPrefix');
            if (prefixElement) {
                prefixElement.value = meeting.building_prefix;
            }
        }
        
        // Set meeting number (preserve original)
        const meetingNumberElement = document.getElementById('meetingNumber');
        if (meetingNumberElement) {
            meetingNumberElement.value = meeting.meeting_number || '';
        }
        
        // Fill other fields
        const agendaElement = document.getElementById('agenda');
        if (agendaElement) agendaElement.value = meeting.agenda || '';
        
        const dateElement = document.getElementById('meetingDate');
        if (dateElement) dateElement.value = meeting.meeting_date_jalali || '';
        
        const timeElement = document.getElementById('meetingTime');
        if (timeElement) timeElement.value = meeting.meeting_time || '';
        
        const locationElement = document.getElementById('location');
        if (locationElement) locationElement.value = meeting.location || '';
        
        const attendeesElement = document.getElementById('attendees');
        if (attendeesElement) attendeesElement.value = meeting.attendees || '';
        
        const observersElement = document.getElementById('observers');
        if (observersElement) observersElement.value = meeting.observers || '';
        
        const contractorElement = document.getElementById('contractor');
        if (contractorElement) contractorElement.value = meeting.contractor || '';
        
        // Sync to all pages
        syncToAllPages();
        
        // Generate enough rows for the items
        if (meeting.items && meeting.items.length > 0) {
            const itemCount = meeting.items.length;
            if (itemCount > 9) {
                const pages = Math.ceil(itemCount / 9);
                const pageCountElement = document.getElementById('pageCount');
                if (pageCountElement) {
                    pageCountElement.value = pages;
                }
                generateTableRows(itemCount);
            }
            
            // Fill item data
            meeting.items.forEach((item, i) => {
                const index = i + 1;
                const followerEl = document.getElementById(`follower_${index}`);
                const descEl = document.getElementById(`description_${index}`);
                const deadlineEl = document.getElementById(`deadline_${index}`);
                
                if (followerEl) followerEl.value = item.follower || '';
                if (descEl) descEl.value = item.description || '';
                if (deadlineEl) deadlineEl.value = item.deadline_jalali || '';
            });
        }
        
        // Load signature (attendee only)
        if (meeting.signatures && meeting.signatures.attendee && meeting.signatures.attendee.signature_data) {
            setTimeout(() => {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.getElementById('attendeeSignature');
                    if (canvas && signatureCanvases.attendeeSignature) {
                        const ctx = signatureCanvases.attendeeSignature.ctx;
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(img, 0, 0);
                        syncSignatureToAllPages();
                    }
                };
                img.src = meeting.signatures.attendee.signature_data;
            }, 500);
        }
        
        console.log('Meeting loaded for editing:', meetingId, 'Number:', meeting.meeting_number);
    })
    .catch(error => {
        console.error('Error loading meeting:', error);
        alert('خطا در بارگذاری صورتجلسه');
    });
}

function generateMeetingNumber() {
    // Don't generate new number if editing existing meeting
    if (isEditingExisting && originalMeetingNumber) {
        const meetingNumberElement = document.getElementById('meetingNumber');
        if (meetingNumberElement) {
            meetingNumberElement.value = originalMeetingNumber;
            syncToAllPages();
        }
        return;
    }
    
    const prefix = document.getElementById('buildingPrefix')?.value;
    if (!prefix) {
        console.warn('Building prefix not found');
        return;
    }
    
    fetch('form_api.php?action=get_next_meeting_number&prefix=' + prefix)
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            const meetingNumberElement = document.getElementById('meetingNumber');
            if (meetingNumberElement) {
                meetingNumberElement.value = result.meeting_number;
                syncToAllPages();
            }
        } else {
            console.error('Error generating meeting number:', result.message);
            const meetingNumberElement = document.getElementById('meetingNumber');
            if (meetingNumberElement) {
                meetingNumberElement.value = prefix + '-001';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        const meetingNumberElement = document.getElementById('meetingNumber');
        if (meetingNumberElement) {
            meetingNumberElement.value = prefix + '-001';
        }
    });
}

function updateDefaultValues() {
    // Don't update defaults if editing existing meeting
    if (isEditingExisting) {
        return;
    }
    
    const prefix = document.getElementById('buildingPrefix')?.value;
    
    // Update observers (نظارت) - always same
    const observersElement = document.getElementById('observers');
    if (observersElement && !observersElement.value) {
        observersElement.value = 'از شرکت آلومنیوم شیشه تهران';
    }
    
    // Update attendees (کارفرما)
    const attendeesElement = document.getElementById('attendees');
    if (attendeesElement && !attendeesElement.value) {
        if (prefix === 'AG') {
            attendeesElement.value = 'از شرکت مشاور شارستان';
        } else if (prefix === 'LB') {
            attendeesElement.value = 'از شرکت مشاور طرح و سازه البرز';
        }
    }
    
    // Update contractor (پیمانکار)
    const contractorElement = document.getElementById('contractor');
    if (contractorElement && !contractorElement.value) {
        if (prefix === 'AG') {
            contractorElement.value = 'از شرکت آدرم';
        } else if (prefix === 'LB') {
            contractorElement.value = 'از شرکت آرانسیج';
        }
    }
    
    // Sync to all pages
    syncToAllPages();
}

function loadDraft() {
    const draft = localStorage.getItem('meeting_minutes_draft');
    if (!draft) return;

    const data = JSON.parse(draft);
    
    if (data.buildingPrefix) {
        document.getElementById('buildingPrefix').value = data.buildingPrefix;
    }
    document.getElementById('meetingNumber').value = data.meetingNumber || '';
    document.getElementById('agenda').value = data.agenda || '';
    document.getElementById('meetingDate').value = data.meetingDate || '';
    document.getElementById('meetingTime').value = data.meetingTime || '';
    document.getElementById('location').value = data.location || '';
    document.getElementById('attendees').value = data.attendees || '';
    document.getElementById('observers').value = data.observers || '';
    document.getElementById('contractor').value = data.contractor || '';

    // Restore page count and rows if saved
    if (data.rowCount && data.rowCount > 9) {
        generateTableRows(data.rowCount);
        if (data.pageCount) {
            document.getElementById('pageCount').value = data.pageCount;
        }
    }

    // Load items
    if (data.items) {
        data.items.forEach((item, i) => {
            const index = i + 1;
            const followerEl = document.getElementById(`follower_${index}`);
            const descEl = document.getElementById(`description_${index}`);
            const deadlineEl = document.getElementById(`deadline_${index}`);
            
            if (followerEl) followerEl.value = item.follower || '';
            if (descEl) descEl.value = item.description || '';
            if (deadlineEl) deadlineEl.value = item.deadline || '';
        });
    }

    // Load signature (only one now)
    if (data.signatures && data.signatures.attendee) {
        const img = new Image();
        img.onload = function() {
            const { ctx } = signatureCanvases.attendeeSignature;
            ctx.drawImage(img, 0, 0);
        };
        img.src = data.signatures.attendee;
    }
}

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

function updateDefaultValues() {
    const prefix = document.getElementById('buildingPrefix').value;
    
    // Update observers (نظارت) - always same
    if (!document.getElementById('observers').value) {
        document.getElementById('observers').value = 'از شرکت آلومنیوم شیشه تهران';
    }
    
    // Update attendees (کارفرما)
    if (!document.getElementById('attendees').value) {
        if (prefix === 'AG') {
            document.getElementById('attendees').value = 'از شرکت مشاور شارستان';
        } else if (prefix === 'LB') {
            document.getElementById('attendees').value = 'از شرکت مشاور طرح و سازه البرز';
        }
    }
    
    // Update contractor (پیمانکار)
    if (!document.getElementById('contractor').value) {
        if (prefix === 'AG') {
            document.getElementById('contractor').value = 'از شرکت آدرم';
        } else if (prefix === 'LB') {
            document.getElementById('contractor').value = 'از شرکت آرانسیج';
        }
    }
}

function setupLetterSearch() {
    const input = document.getElementById('letterSearch');
    const resultsContainer = document.getElementById('letterSearchResults');
    const hiddenInput = document.getElementById('externalLetterId');
    let timeout;

    input.addEventListener('input', function() {
        clearTimeout(timeout);
        const query = this.value.trim();
        if (query.length < 2) {
            resultsContainer.innerHTML = '';
            return;
        }
        timeout = setTimeout(() => {
            fetch(`letters.php?action=search_letters&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    resultsContainer.innerHTML = '';
                    data.forEach(letter => {
                        const item = document.createElement('a');
                        item.href = '#';
                        item.className = 'list-group-item list-group-item-action';
                        item.innerHTML = `<strong>${letter.letter_number}</strong> - ${letter.subject}`;
                        item.onclick = (e) => {
                            e.preventDefault();
                            input.value = `${letter.letter_number} - ${letter.subject}`;
                            hiddenInput.value = letter.id;
                            resultsContainer.innerHTML = '';
                        };
                        resultsContainer.appendChild(item);
                    });
                });
        }, 300);
    });
    
    document.addEventListener('click', (e) => {
        if (!resultsContainer.contains(e.target) && e.target !== input) {
            resultsContainer.innerHTML = '';
        }
    });
}

function parseOCRText() {
    const rawText = document.getElementById('ocrRawText').value;
    if (!rawText) {
        alert('لطفاً متن OCR را وارد کنید');
        return;
    }
    
    // Simple parsing logic - can be enhanced
    const lines = rawText.split('\n');
    
    lines.forEach(line => {
        line = line.trim();
        
        // Try to extract date
        const dateMatch = line.match(/(\d{4}\/\d{1,2}\/\d{1,2})/);
        if (dateMatch && !document.getElementById('uploadMeetingDate').value) {
            document.getElementById('uploadMeetingDate').value = convertPersianToEnglish(dateMatch[1]);
        }
        
        // Try to extract time
        const timeMatch = line.match(/(\d{1,2}:\d{2})/);
        if (timeMatch && !document.getElementById('uploadMeetingTime').value) {
            document.getElementById('uploadMeetingTime').value = convertPersianToEnglish(timeMatch[1]);
        }
        
        // Try to extract agenda
        if (line.includes('دستور') && line.includes('جلسه')) {
            const agendaText = line.split(':')[1] || line.split('جلسه')[1];
            if (agendaText && !document.getElementById('uploadAgenda').value) {
                document.getElementById('uploadAgenda').value = agendaText.trim();
            }
        }
        
        // Try to extract location
        if (line.includes('محل')) {
            const locationText = line.split(':')[1] || line.split('محل')[1];
            if (locationText && !document.getElementById('uploadLocation').value) {
                document.getElementById('uploadLocation').value = locationText.trim();
            }
        }
        
        // Try to extract attendees
        if (line.includes('کارفرما')) {
            const attendeesText = line.split(':')[1] || line.split('کارفرما')[1];
            if (attendeesText && !document.getElementById('uploadAttendees').value) {
                document.getElementById('uploadAttendees').value = attendeesText.trim();
            }
        }
        
        if (line.includes('نظارت')) {
            const observersText = line.split(':')[1] || line.split('نظارت')[1];
            if (observersText && !document.getElementById('uploadObservers').value) {
                document.getElementById('uploadObservers').value = observersText.trim();
            }
        }
        
        if (line.includes('پیمانکار')) {
            const contractorText = line.split(':')[1] || line.split('پیمانکار')[1];
            if (contractorText && !document.getElementById('uploadContractor').value) {
                document.getElementById('uploadContractor').value = contractorText.trim();
            }
        }
    });
    
    alert('✅ تجزیه خودکار انجام شد. لطفاً اطلاعات را بررسی و در صورت نیاز ویرایش کنید.');
}

function saveAndGeneratePDF() {
    const data = getFormData();
    
    if (!data) {
        console.error('Cannot save: form data is invalid');
        return;
    }
    
    const progressDiv = document.createElement('div');
    progressDiv.id = 'pdfProgress';
    progressDiv.className = 'alert alert-info';
    progressDiv.style.position = 'fixed';
    progressDiv.style.top = '20px';
    progressDiv.style.left = '50%';
    progressDiv.style.transform = 'translateX(-50%)';
    progressDiv.style.zIndex = '9999';
    progressDiv.style.minWidth = '300px';
    progressDiv.innerHTML = '<i class="bi bi-hourglass-split"></i> در حال ذخیره فرم...';
    document.body.appendChild(progressDiv);
    
    data.status = 'completed';
    
    const formData = new FormData();
    formData.append('action', 'save_meeting_minutes');
    
    const meetingId = localStorage.getItem('current_meeting_id');
    if (meetingId) {
        formData.append('meeting_id', meetingId);
    }
    
    formData.append('meeting_number', data.meetingNumber);
    formData.append('building_prefix', data.buildingPrefix);
    formData.append('agenda', data.agenda);
    formData.append('meeting_date', data.meetingDate);
    formData.append('meeting_time', data.meetingTime);
    formData.append('location', data.location);
    formData.append('attendees', data.attendees);
    formData.append('observers', data.observers);
    formData.append('contractor', data.contractor);
    formData.append('status', 'completed');
    
    data.items.forEach((item, index) => {
        formData.append(`items[${index}][follower]`, item.follower);
        formData.append(`items[${index}][description]`, item.description);
        formData.append(`items[${index}][deadline]`, item.deadline);
    });
    
    if (data.signatures.attendee) {
        formData.append('signatures[attendee]', data.signatures.attendee);
    }
    
    fetch('form_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            const savedMeetingId = result.meeting_id || meetingId;
            localStorage.setItem('current_meeting_id', savedMeetingId);
            
            progressDiv.innerHTML = '<i class="bi bi-hourglass-split"></i> در حال تولید فایل PDF...';
            
            return fetch('form_api.php?action=generate_pdf&id=' + savedMeetingId);
        } else {
            throw new Error(result.message);
        }
    })
    .then(res => res.json())
    .then(pdfResult => {
        progressDiv.remove();
        
        if (pdfResult.success) {
            const successDiv = document.createElement('div');
            successDiv.className = 'alert alert-success alert-dismissible fade show';
            successDiv.style.position = 'fixed';
            successDiv.style.top = '20px';
            successDiv.style.left = '50%';
            successDiv.style.transform = 'translateX(-50%)';
            successDiv.style.zIndex = '9999';
            successDiv.style.minWidth = '400px';
            successDiv.innerHTML = `
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
                <h5><i class="bi bi-check-circle"></i> موفق!</h5>
                <p>${pdfResult.message}</p>
                <div class="d-flex gap-2 mt-3">
                    <a href="${pdfResult.pdf_url}" target="_blank" class="btn btn-sm btn-danger">
                        <i class="bi bi-file-pdf-fill"></i> مشاهده PDF
                    </a>
                    <a href="${pdfResult.pdf_url}" download class="btn btn-sm btn-success">
                        <i class="bi bi-download"></i> دانلود PDF
                    </a>
                    <a href="saved_minutes_list.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-list"></i> بازگشت به لیست
                    </a>
                </div>
            `;
            document.body.appendChild(successDiv);
            
            setTimeout(() => {
                if (successDiv.parentElement) {
                    successDiv.remove();
                }
            }, 15000);
        } else {
            alert('❌ خطا در تولید PDF: ' + pdfResult.message);
        }
    })
    .catch(error => {
        progressDiv.remove();
        console.error('Error:', error);
        alert('❌ خطا: ' + error.message);
    });
}

function loadPendingForms() {
    fetch('form_api.php?action=get_pending_handwritten_forms')
    .then(res => res.json())
    .then(result => {
        const select = document.getElementById('pendingMeetingSelect');
        select.innerHTML = '<option value="">-- یک مورد را انتخاب کنید --</option>';
        
        if (result.success && result.data.length > 0) {
            result.data.forEach(form => {
                const option = document.createElement('option');
                option.value = form.id;
                option.textContent = `${form.meeting_number} (رزرو شده در ${form.created_at_jalali})`;
                option.dataset.meetingNumber = form.meeting_number;
                option.dataset.buildingPrefix = form.building_prefix;
                select.appendChild(option);
            });
        } else {
            select.innerHTML = '<option value="">هیچ فرم در انتظاری یافت نشد. ابتدا یک فرم خالی دانلود کنید.</option>';
        }
    });
}

function fillUploadFormDetails(select) {
    const detailsDiv = document.getElementById('uploadDetails');
    if (select.value) {
        detailsDiv.style.display = 'block';
        
        // Auto-fill default values based on prefix
        const selectedOption = select.options[select.selectedIndex];
        const prefix = selectedOption.dataset.buildingPrefix;
        
        if (prefix === 'AG') {
            document.getElementById('uploadAttendees').value = 'از شرکت مشاور شارستان';
            document.getElementById('uploadContractor').value = 'از شرکت آدرم';
        } else if (prefix === 'LB') {
            document.getElementById('uploadAttendees').value = 'از شرکت مشاور طرح و سازه البرز';
            document.getElementById('uploadContractor').value = 'از شرکت آرانسیج';
        }
        
        document.getElementById('uploadObservers').value = 'از شرکت آلومنیوم شیشه تهران';
    } else {
        detailsDiv.style.display = 'none';
    }
}

function uploadExternalMinute() {
    const meetingNumber = document.getElementById('externalMeetingNumber').value.trim();
    const companyId = document.getElementById('externalCompanyId').value;
    const letterId = document.getElementById('externalLetterId').value;
    const agenda = document.getElementById('externalAgenda').value.trim();
    const meetingDate = document.getElementById('externalMeetingDate').value.trim();
    const ocrText = document.getElementById('externalOcrText').value.trim();
    const fileInput = document.getElementById('externalFile');
    const file = fileInput.files[0];

    if (!meetingNumber || !companyId || !agenda || !meetingDate || !file) {
        alert('لطفاً تمامی فیلدهای ستاره‌دار را تکمیل کنید.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'log_external_minute');
    formData.append('meeting_number', meetingNumber);
    formData.append('company_id', companyId);
    formData.append('related_letter_id', letterId);
    formData.append('agenda', agenda);
    formData.append('meeting_date', convertPersianToEnglish(meetingDate));
    formData.append('extracted_text', ocrText);
    formData.append('file', file);

    const uploadBtn = document.querySelector('#externalSection button');
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> در حال بارگذاری...';

    fetch('form_api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                alert('✅ ' + result.message);
                window.location.href = 'saved_minutes_list.php';
            } else {
                alert('❌ ' + result.message);
            }
        })
        .catch(err => alert('خطا در ارتباط با سرور.'))
        .finally(() => {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="bi bi-upload"></i> بارگذاری و ثبت نهایی';
        });
}
function setMode(mode) {
    currentMode = mode;
    
    // Reset all buttons to secondary
    document.getElementById('digitalModeBtn').classList.remove('btn-primary');
    document.getElementById('digitalModeBtn').classList.add('btn-secondary');
    document.getElementById('externalModeBtn').classList.remove('btn-primary');
    document.getElementById('externalModeBtn').classList.add('btn-secondary');
    document.getElementById('printModeBtn').classList.remove('btn-primary');
    document.getElementById('printModeBtn').classList.add('btn-secondary');
    document.getElementById('uploadModeBtn').classList.remove('btn-primary');
    document.getElementById('uploadModeBtn').classList.add('btn-secondary');
    
    // Hide all panels
    document.getElementById('digitalControls').style.display = 'none';
    document.getElementById('printControls').style.display = 'none';
    document.getElementById('uploadSection').style.display = 'none';
    document.getElementById('externalSection').style.display = 'none';
    document.getElementById('pageControls').style.display = 'none';
    
    if (mode === 'digital') {
        document.getElementById('digitalModeBtn').classList.remove('btn-secondary');
        document.getElementById('digitalModeBtn').classList.add('btn-primary');
        document.getElementById('digitalControls').style.display = 'block';
        document.getElementById('pageControls').style.display = 'block';
        
        // Show signature canvases
        const noSignatures = document.querySelector('.no-print-signatures');
        const printSignatures = document.querySelector('.print-only-signatures');
        if (noSignatures) noSignatures.style.display = 'block';
        if (printSignatures) printSignatures.style.display = 'none';
        
        // Enable inputs
        document.querySelectorAll('#meetingForm input:not(#meetingNumber), #meetingForm textarea, #meetingForm select').forEach(el => {
            el.removeAttribute('readonly');
            el.removeAttribute('disabled');
        });
        
    } else if (mode === 'external') {
        document.getElementById('externalModeBtn').classList.remove('btn-secondary');
        document.getElementById('externalModeBtn').classList.add('btn-primary');
        document.getElementById('externalSection').style.display = 'block';
        
    } else if (mode === 'print') {
        document.getElementById('printModeBtn').classList.remove('btn-secondary');
        document.getElementById('printModeBtn').classList.add('btn-primary');
        document.getElementById('printControls').style.display = 'block';
        document.getElementById('pageControls').style.display = 'block';
        
        // Show empty signature boxes
        const noSignatures = document.querySelector('.no-print-signatures');
        const printSignatures = document.querySelector('.print-only-signatures');
        if (noSignatures) noSignatures.style.display = 'none';
        if (printSignatures) printSignatures.style.display = 'block';
        
        // Disable inputs (for blank form)
        document.querySelectorAll('#meetingForm input, #meetingForm textarea, #meetingForm select').forEach(el => {
            el.setAttribute('readonly', 'readonly');
        });
        
    } else if (mode === 'upload') {
        document.getElementById('uploadModeBtn').classList.remove('btn-secondary');
        document.getElementById('uploadModeBtn').classList.add('btn-primary');
        document.getElementById('uploadSection').style.display = 'block';
        
        // Load pending forms for upload
        loadPendingForms();
    }
}

function uploadHandwrittenForm(event) {
    // --- 1. GET DATA FROM THE FORM ---
    const fileInput = document.getElementById('handwrittenFile');
    const file = fileInput.files[0];
    const select = document.getElementById('pendingMeetingSelect');
    const selectedOption = select.options[select.selectedIndex];
    
    // The ID of the placeholder record we are updating
    const meetingId = select.value;
    // The meeting number, stored in the option's data attribute
    const meetingNumber = selectedOption ? selectedOption.dataset.meetingNumber : '';

    // --- 2. VALIDATION (Essential for good user experience) ---
    if (!meetingId) {
        alert('لطفاً یک صورتجلسه در انتظار را از لیست انتخاب کنید.');
        return;
    }
    if (!file) {
        alert('لطفاً فایل اسکن شده را انتخاب کنید.');
        return;
    }
    const agenda = document.getElementById('uploadAgenda').value.trim();
    if (!agenda) {
        alert('دستور جلسه / موضوع الزامی است.');
        return;
    }
    const meetingDate = document.getElementById('uploadMeetingDate').value.trim();
    if (!meetingDate) {
        alert('تاریخ جلسه الزامی است.');
        return;
    }
    
    // --- 3. PREPARE FORM DATA FOR THE API ---
    // This now sends the meeting_id, which is what the backend needs.
     const formData = new FormData();
    formData.append('action', 'upload_handwritten_form');
    formData.append('meeting_id', meetingId);
    formData.append('meeting_number', meetingNumber);
    formData.append('agenda', agenda);
    formData.append('meeting_date', convertPersianToEnglish(meetingDate)); // Convert here
    formData.append('meeting_time', document.getElementById('uploadMeetingTime').value.trim());
    formData.append('location', document.getElementById('uploadLocation').value.trim());
    formData.append('file', file);
    
    // --- 4. HANDLE UI FEEDBACK (Progress Bar, Button State) ---
    const progressBar = document.querySelector('#uploadProgress .progress-bar');
    const uploadProgressDiv = document.getElementById('uploadProgress');
    const uploadBtn = document.querySelector('#uploadDetails button'); // More specific selector
    
    uploadProgressDiv.style.display = 'block';
    progressBar.style.width = '50%'; // Show immediate progress
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> در حال بارگذاری...';
    
    // --- 5. SEND TO SERVER ---
    fetch('form_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(result => {
        progressBar.style.width = '100%';
        
        setTimeout(() => { // Short delay to show completion
            if (result.success) {
                alert('✅ ' + result.message);
                // Reset the form for the next upload
                document.getElementById('uploadDetails').style.display = 'none';
                fileInput.value = '';
                document.getElementById('uploadAgenda').value = '';
                // Reload the list of pending forms, which will now be shorter
                loadPendingForms(); 
            } else {
                alert('❌ ' + result.message);
            }
        }, 500);
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در بارگذاری فایل. لطفا اتصال اینترنت خود را بررسی کنید.');
    })
    .finally(() => {
        // This runs after success or failure
        setTimeout(() => {
            uploadProgressDiv.style.display = 'none';
            progressBar.style.width = '0%';
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="bi bi-cloud-upload"></i> 3. بارگذاری و تکمیل نهایی';
        }, 1000);
    });
}


function generateTableRows(totalRowCount) {
    const container = document.getElementById('tableRowsContainer');
    if (!container) {
        console.error('tableRowsContainer not found!');
        return;
    }
    
    container.innerHTML = '';
    
    for (let i = 1; i <= totalRowCount; i++) {
        const row = document.createElement('div');
        row.className = 'table-row dynamic-row';
        row.id = `row_${i}`;
        
        row.innerHTML = `
            <div class="table-cell" data-label="ردیف" style="text-align: center; display: flex; align-items: center; justify-content: center; position: relative;">
                ${i}
                ${i > 9 ? `<button class="row-delete-btn no-print" onclick="deleteRow(${i})" title="حذف ردیف">×</button>` : ''}
            </div>
            <div class="table-cell" data-label="خلاصه مذاکرات">
                <textarea name="description_${i}" id="description_${i}"></textarea>
            </div>
            <div class="table-cell" data-label="پیگیری کننده">
                <input type="text" name="follower_${i}" id="follower_${i}">
            </div>
            <div class="table-cell" data-label="تاریخ سررسید">
                <input type="text" class="persian-datepicker-small" name="deadline_${i}" id="deadline_${i}">
            </div>
        `;
        
        container.appendChild(row);
    }
    
    currentRowCount = totalRowCount;
    document.getElementById('currentRowCount').textContent = totalRowCount;
    
    // Reinitialize Persian datepickers for new deadline fields
    setTimeout(() => {
        $('.persian-datepicker-small').persianDatepicker({
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
    }, 100);
}

function saveCurrentFormData() {
    const data = {
        buildingPrefix: document.getElementById('buildingPrefix')?.value || 'AG',
        meetingNumber: document.getElementById('meetingNumber')?.value || '',
        agenda: document.getElementById('agenda')?.value || '',
        meetingDate: document.getElementById('meetingDate')?.value || '',
        meetingTime: document.getElementById('meetingTime')?.value || '',
        location: document.getElementById('location')?.value || '',
        attendees: document.getElementById('attendees')?.value || '',
        observers: document.getElementById('observers')?.value || '',
        contractor: document.getElementById('contractor')?.value || '',
        items: [],
        signature: null
    };
    
    for (let i = 1; i <= currentRowCount; i++) {
        const followerEl = document.getElementById(`follower_${i}`);
        const descEl = document.getElementById(`description_${i}`);
        const deadlineEl = document.getElementById(`deadline_${i}`);
        
        if (followerEl && descEl && deadlineEl) {
            data.items.push({
                follower: followerEl.value,
                description: descEl.value,
                deadline: deadlineEl.value
            });
        }
    }
    
    const signatureCanvas = document.getElementById('attendeeSignature');
    if (signatureCanvas) {
        data.signature = signatureCanvas.toDataURL();
    }
    
    return data;
}

function restoreFormData(data) {
    if (document.getElementById('buildingPrefix')) {
        document.getElementById('buildingPrefix').value = data.buildingPrefix;
    }
    if (document.getElementById('meetingNumber')) {
        document.getElementById('meetingNumber').value = data.meetingNumber;
    }
    if (document.getElementById('agenda')) {
        document.getElementById('agenda').value = data.agenda;
    }
    if (document.getElementById('meetingDate')) {
        document.getElementById('meetingDate').value = data.meetingDate;
    }
    if (document.getElementById('meetingTime')) {
        document.getElementById('meetingTime').value = data.meetingTime;
    }
    if (document.getElementById('location')) {
        document.getElementById('location').value = data.location;
    }
    if (document.getElementById('attendees')) {
        document.getElementById('attendees').value = data.attendees;
    }
    if (document.getElementById('observers')) {
        document.getElementById('observers').value = data.observers;
    }
    if (document.getElementById('contractor')) {
        document.getElementById('contractor').value = data.contractor;
    }
    
    syncToAllPages();
    
    data.items.forEach((item, index) => {
        const rowNum = index + 1;
        if (rowNum <= currentRowCount) {
            const followerEl = document.getElementById(`follower_${rowNum}`);
            const descEl = document.getElementById(`description_${rowNum}`);
            const deadlineEl = document.getElementById(`deadline_${rowNum}`);
            
            if (followerEl) followerEl.value = item.follower;
            if (descEl) descEl.value = item.description;
            if (deadlineEl) deadlineEl.value = item.deadline;
        }
    });
    
    if (data.signature) {
        setTimeout(() => {
            const img = new Image();
            img.onload = function() {
                const canvas = document.getElementById('attendeeSignature');
                if (canvas && signatureCanvases.attendeeSignature) {
                    const ctx = signatureCanvases.attendeeSignature.ctx;
                    ctx.drawImage(img, 0, 0);
                    syncSignatureToAllPages();
                }
            };
            img.src = data.signature;
        }, 100);
    }
}

function reinitializeFormElements(totalPages) {
    const currentDate = document.getElementById('meetingDate')?.value;
    
    $('.persian-datepicker-main').persianDatepicker({
        format: 'YYYY/MM/DD',
        autoClose: true,
        initialValue: false,
        persianDigit: false,
        calendar: { 
            persian: { 
                locale: 'fa',
                leapYearMode: 'astronomical'
            } 
        },
        onSelect: function() {
            syncToAllPages();
        }
    });
    
    if (currentDate && !document.getElementById('meetingDate').value) {
        document.getElementById('meetingDate').value = currentDate;
        syncToAllPages();
    }
    
    $('.persian-datepicker-small').persianDatepicker({
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
    
    for (let pageNum = 1; pageNum <= totalPages; pageNum++) {
        const canvasId = pageNum === 1 ? 'attendeeSignature' : `attendeeSignature_p${pageNum}`;
        const canvas = document.getElementById(canvasId);
        if (canvas) {
            initSignatureCanvas(canvasId);
        }
    }
    
    // Setup field syncing for multi-page forms
    setupFieldSyncing();
}

function setupFieldSyncing() {
    // Add event listeners to first page fields to sync to all other pages
    const fieldsToSync = [
        'buildingPrefix', 'meetingNumber', 'agenda', 'meetingDate', 
        'meetingTime', 'location', 'attendees', 'observers', 'contractor'
    ];
    
    fieldsToSync.forEach(fieldId => {
        const element = document.getElementById(fieldId);
        if (element) {
            element.addEventListener('input', syncToAllPages);
            element.addEventListener('change', syncToAllPages);
        }
    });
}

function addNewRow() {
    const newRowNumber = currentRowCount + 1;
    
    // Check if we need to suggest adding a page
    const currentPages = Math.ceil(currentRowCount / 9);
    const newPages = Math.ceil(newRowNumber / 9);
    
    if (newPages > currentPages && newPages <= 5) {
        if (confirm(`برای ${newRowNumber} ردیف، به ${newPages} صفحه نیاز است. آیا می‌خواهید تعداد صفحات را افزایش دهید؟`)) {
            document.getElementById('pageCount').value = newPages;
            updatePageCount();
            return;
        }
    }
    
    if (newRowNumber > 45) {
        alert('حداکثر 45 ردیف (5 صفحه) مجاز است');
        return;
    }
    
    generateTableRows(newRowNumber);
}

function deleteRow(rowNumber) {
    if (!confirm('آیا از حذف این ردیف اطمینان دارید?')) return;
    
    // Get all row data
    const allData = [];
    for (let i = 1; i <= currentRowCount; i++) {
        if (i === rowNumber) continue; // Skip the deleted row
        
        allData.push({
            follower: document.getElementById(`follower_${i}`)?.value || '',
            description: document.getElementById(`description_${i}`)?.value || '',
            deadline: document.getElementById(`deadline_${i}`)?.value || ''
        });
    }
    
    // Regenerate with one less row
    generateTableRows(currentRowCount - 1);
    
    // Restore data
    allData.forEach((data, index) => {
        const i = index + 1;
        if (document.getElementById(`follower_${i}`)) {
            document.getElementById(`follower_${i}`).value = data.follower;
            document.getElementById(`description_${i}`).value = data.description;
            document.getElementById(`deadline_${i}`).value = data.deadline;
        }
    });
}

function updatePageCount() {
    const pageCount = parseInt(document.getElementById('pageCount').value);
    const rowsPerPage = 9;
    const totalRows = pageCount * rowsPerPage;
    
    currentPageCount = pageCount;
    generateTableRows(totalRows);
}

function debugFormData() {
    console.log('=== Form Debug Info ===');
    console.log('Current page count:', currentPageCount);
    console.log('Current row count:', currentRowCount);
    console.log('Meeting Date element:', document.getElementById('meetingDate'));
    console.log('Meeting Date value:', document.getElementById('meetingDate')?.value);
    console.log('Meeting Number:', document.getElementById('meetingNumber')?.value);
    console.log('Building Prefix:', document.getElementById('buildingPrefix')?.value);
    console.log('Agenda:', document.getElementById('agenda')?.value);
    
    const data = getFormData();
    console.log('Form Data:', data);
    
    return data;
}

function syncSignatureToAllPages() {
    const sourceCanvas = document.getElementById('attendeeSignature');
    if (!sourceCanvas) return;
    
    const allCanvases = document.querySelectorAll('.signature-canvas');
    allCanvases.forEach(canvas => {
        if (canvas.id !== 'attendeeSignature' && signatureCanvases[canvas.id]) {
            const targetCtx = signatureCanvases[canvas.id].ctx;
            targetCtx.clearRect(0, 0, canvas.width, canvas.height);
            targetCtx.drawImage(sourceCanvas, 0, 0);
        }
    });
}

function downloadBlankForm() {
    const prefix = document.getElementById('buildingPrefix').value;
    let meetingDate = document.getElementById('meetingDate').value;
    
    // Convert Persian/Arabic numbers to English BEFORE sending
    if (meetingDate) {
        meetingDate = convertPersianToEnglish(meetingDate);
        console.log('Date being sent:', meetingDate);
    }
    
    // Ask user if they want to use the current form date
    let useCustomDate = false;
    if (meetingDate && meetingDate.trim() !== '') {
        useCustomDate = confirm(`آیا می‌خواهید تاریخ ${meetingDate} برای این فرم استفاده شود؟\n\nاگر "لغو" را بزنید، تاریخ امروز استفاده خواهد شد.`);
    }
    
    const formData = new FormData();
    formData.append('action', 'reserve_blank_form');
    formData.append('prefix', prefix);
    
    // Send the date if user confirmed
    if (useCustomDate && meetingDate) {
        formData.append('meeting_date', meetingDate);
    }

    fetch('form_api.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            alert(`شماره صورتجلسه ${result.meeting_number} رزرو شد. صفحه چاپ باز می‌شود.`);
            window.open('meeting_minutes_print.php?id=' + result.meeting_id, '_blank');
        } else {
            alert('خطا در رزرو شماره: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در ارتباط با سرور');
    });
}

// Add page break styles
const pageBreakStyle = document.createElement('style');
pageBreakStyle.textContent = `
    @media print {
        .form-container {
            page-break-after: always;
            margin-bottom: 0 !important;
        }
        
        .form-container:last-of-type {
            page-break-after: auto;
        }
        
        .print-only-signatures {
            display: grid !important;
        }
        
        .no-print-signatures {
            display: none !important;
        }
    }
`;
if (!document.getElementById('pageBreakStyles')) {
    pageBreakStyle.id = 'pageBreakStyles';
    document.head.appendChild(pageBreakStyle);
}
function generateSinglePage(pageNum, totalPages, startRow, rowsPerPage) {
    const isFirstPage = pageNum === 1;
    const rowsInThisPage = Math.min(rowsPerPage, currentRowCount - startRow);
    
    let html = `
    <div class="form-container" id="meetingForm${pageNum}" style="${pageNum < totalPages ? 'page-break-after: always;' : ''}">
        <!-- Header - Appears on EVERY page -->
        <div class="form-header">
            <div>
                <div class="logo-placeholder">
                    <img src="assets/images/خاتم.jpg" alt="لوگو" style="max-width: 100%; max-height: 100%;" onerror="this.parentElement.innerHTML='لوگو'">
                </div>
            </div>
            <div class="form-title">فرم صورتجلسه</div>
            <div class="page-number">صفحه ${pageNum} از ${totalPages}</div>
        </div>

        <!-- Meeting Info - Appears on EVERY page -->
        <div class="form-row three-cols">
            <div class="form-cell">
                <label>شماره :</label>
                <div class="number-input-group">
                    <select id="${isFirstPage ? 'buildingPrefix' : 'buildingPrefix_p' + pageNum}" 
                            class="sync-field-prefix" 
                            ${!isFirstPage ? 'disabled' : ''}
                            onchange="${isFirstPage ? 'generateMeetingNumber(); updateDefaultValues(); syncToAllPages();' : ''}">
                        <option value="AG">AG-</option>
                        <option value="LB">LB-</option>
                        <option value="SK">SK-</option>
                        <option value="G">G-</option>
                    </select>
                    <input type="text" 
                           id="${isFirstPage ? 'meetingNumber' : 'meetingNumber_p' + pageNum}" 
                           class="sync-field-number"
                           readonly 
                           style="background-color: #f8f9fa;">
                </div>
            </div>
            <div class="form-cell">
                <label>دستور جلسه:</label>
                <input type="text" 
                       id="${isFirstPage ? 'agenda' : 'agenda_p' + pageNum}" 
                       class="sync-field-agenda"
                       ${!isFirstPage ? 'readonly' : ''}
                       oninput="${isFirstPage ? 'syncToAllPages()' : ''}"
                       style="flex: 2;">
            </div>
            <div class="form-cell"></div>
        </div>

        <div class="form-row two-cols">
            <div class="form-cell">
                <label>تاریخ:</label>
                <input type="text" 
                       class="${isFirstPage ? 'persian-datepicker-main' : ''} sync-field-date" 
                       id="${isFirstPage ? 'meetingDate' : 'meetingDate_p' + pageNum}"
                       ${!isFirstPage ? 'readonly' : ''}
                       placeholder="انتخاب تاریخ">
            </div>
            <div class="form-cell">
                <label>ساعت:</label>
                <input type="text" 
                       id="${isFirstPage ? 'meetingTime' : 'meetingTime_p' + pageNum}" 
                       class="sync-field-time"
                       ${!isFirstPage ? 'readonly' : ''}
                       oninput="${isFirstPage ? 'syncToAllPages()' : ''}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-cell">
                <label>محل تشکیل جلسه:</label>
                <input type="text" 
                       id="${isFirstPage ? 'location' : 'location_p' + pageNum}" 
                       class="sync-field-location"
                       ${!isFirstPage ? 'readonly' : ''}
                       oninput="${isFirstPage ? 'syncToAllPages()' : ''}">
            </div>
        </div>

        <div class="form-row attendee-row">
            <div class="form-cell">
                <label>حاضرین کارفرما:</label>
                <textarea id="${isFirstPage ? 'attendees' : 'attendees_p' + pageNum}" 
                          class="small-textarea sync-field-attendees" 
                          ${!isFirstPage ? 'readonly' : ''}
                          oninput="${isFirstPage ? 'syncToAllPages()' : ''}"
                          rows="1"></textarea>
            </div>
        </div>

        <div class="form-row attendee-row">
            <div class="form-cell">
                <label>حاضرین نظارت:</label>
                <textarea id="${isFirstPage ? 'observers' : 'observers_p' + pageNum}" 
                          class="small-textarea sync-field-observers" 
                          ${!isFirstPage ? 'readonly' : ''}
                          oninput="${isFirstPage ? 'syncToAllPages()' : ''}"
                          rows="1"></textarea>
            </div>
        </div>

        <div class="form-row attendee-row">
            <div class="form-cell">
                <label>حاضرین پیمانکار:</label>
                <textarea id="${isFirstPage ? 'contractor' : 'contractor_p' + pageNum}" 
                          class="small-textarea sync-field-contractor" 
                          ${!isFirstPage ? 'readonly' : ''}
                          oninput="${isFirstPage ? 'syncToAllPages()' : ''}"
                          rows="1"></textarea>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-section">
            <div class="table-header">
                <div>ردیف</div>
                <div>خلاصه مذاکرات و تصمیمات متخذه:</div>
                <div>پیگیری کننده</div>
                <div>تاریخ سررسید</div>
            </div>
            <div id="tableRowsContainer${pageNum}">`;
    
    // Add table rows for this page
    for (let i = 0; i < rowsInThisPage; i++) {
        const rowNum = startRow + i + 1;
        html += generateTableRow(rowNum);
    }
    
    html += `
            </div>
        </div>

        <!-- Signatures - Appears on EVERY page -->
        <div class="signature-section single-signature no-print-signatures">
            <div class="signature-box full-width">
                <div class="signature-label">محل امضای حاضرین:</div>
                <canvas class="signature-canvas" 
                        id="${isFirstPage ? 'attendeeSignature' : 'attendeeSignature_p' + pageNum}" 
                        width="500" 
                        height="150"></canvas>
                ${isFirstPage ? `
                <div class="signature-buttons">
                    <button class="btn btn-sm btn-warning" onclick="clearSignature('attendeeSignature')">
                        <i class="bi bi-x"></i> پاک کردن
                    </button>
                </div>` : ''}
            </div>
        </div>

        <div class="signature-section single-signature print-only-signatures" style="display: none;">
            <div class="signature-box full-width">
                <div class="signature-label">محل امضای حاضرین:</div>
                <div style="border: 2px dashed #999; height: 150px;"></div>
            </div>
        </div>
    </div>`;
    
    return html;
}

function generateTableRow(rowNum) {
    return `
    <div class="table-row dynamic-row" id="row_${rowNum}">
        <div class="table-cell" data-label="ردیف" style="text-align: center; display: flex; align-items: center; justify-content: center; position: relative;">
            ${rowNum}
            ${rowNum > 9 ? `<button class="row-delete-btn no-print" onclick="deleteRow(${rowNum})" title="حذف ردیف">×</button>` : ''}
        </div>
        <div class="table-cell" data-label="خلاصه مذاکرات">
            <textarea name="description_${rowNum}" id="description_${rowNum}"></textarea>
        </div>
        <div class="table-cell" data-label="پیگیری کننده">
            <input type="text" name="follower_${rowNum}" id="follower_${rowNum}">
        </div>
        <div class="table-cell" data-label="تاریخ سررسید">
            <input type="text" class="persian-datepicker-small" name="deadline_${rowNum}" id="deadline_${rowNum}">
        </div>
    </div>`;
}

function syncToAllPages() {
    // Sync building prefix
    const prefix = document.getElementById('buildingPrefix')?.value;
    if (prefix !== undefined) {
        document.querySelectorAll('.sync-field-prefix').forEach(el => {
            if (el.id !== 'buildingPrefix') el.value = prefix;
        });
    }
    
    // Sync meeting number
    const number = document.getElementById('meetingNumber')?.value;
    if (number !== undefined) {
        document.querySelectorAll('.sync-field-number').forEach(el => {
            if (el.id !== 'meetingNumber') el.value = number;
        });
    }
    
    // Sync agenda
    const agenda = document.getElementById('agenda')?.value;
    if (agenda !== undefined) {
        document.querySelectorAll('.sync-field-agenda').forEach(el => {
            if (el.id !== 'agenda') el.value = agenda;
        });
    }
    
    // Sync date
    const date = document.getElementById('meetingDate')?.value;
    if (date !== undefined) {
        document.querySelectorAll('.sync-field-date').forEach(el => {
            if (el.id !== 'meetingDate') el.value = date;
        });
    }
    
    // Sync time
    const time = document.getElementById('meetingTime')?.value;
    if (time !== undefined) {
        document.querySelectorAll('.sync-field-time').forEach(el => {
            if (el.id !== 'meetingTime') el.value = time;
        });
    }
    
    // Sync location
    const location = document.getElementById('location')?.value;
    if (location !== undefined) {
        document.querySelectorAll('.sync-field-location').forEach(el => {
            if (el.id !== 'location') el.value = location;
        });
    }
    
    // Sync attendees
    const attendees = document.getElementById('attendees')?.value;
    if (attendees !== undefined) {
        document.querySelectorAll('.sync-field-attendees').forEach(el => {
            if (el.id !== 'attendees') el.value = attendees;
        });
    }
    
    // Sync observers
    const observers = document.getElementById('observers')?.value;
    if (observers !== undefined) {
        document.querySelectorAll('.sync-field-observers').forEach(el => {
            if (el.id !== 'observers') el.value = observers;
        });
    }
    
    // Sync contractor
    const contractor = document.getElementById('contractor')?.value;
    if (contractor !== undefined) {
        document.querySelectorAll('.sync-field-contractor').forEach(el => {
            if (el.id !== 'contractor') el.value = contractor;
        });
    }
}
function initSignatureCanvas(canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.warn(`Canvas not found: ${canvasId}`);
        return;
    }
    
    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let lastX = 0;
    let lastY = 0;

    signatureCanvases[canvasId] = { canvas, ctx };

    function getCoordinates(e) {
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX || e.touches?.[0]?.clientX) - rect.left;
        const y = (e.clientY || e.touches?.[0]?.clientY) - rect.top;
        return { x, y };
    }

    function startDrawing(e) {
        isDrawing = true;
        const coords = getCoordinates(e);
        lastX = coords.x;
        lastY = coords.y;
        e.preventDefault();
    }

    function draw(e) {
        if (!isDrawing) return;
        
        const coords = getCoordinates(e);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(coords.x, coords.y);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.stroke();
        
        lastX = coords.x;
        lastY = coords.y;
        e.preventDefault();
        
        // Sync signature to all pages if this is the first page canvas
        if (canvasId === 'attendeeSignature') {
            syncSignatureToAllPages();
        }
    }

    function stopDrawing() {
        isDrawing = false;
    }

    // Mouse events
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    
    // Touch events for mobile
    canvas.addEventListener('touchstart', startDrawing);
    canvas.addEventListener('touchmove', draw);
    canvas.addEventListener('touchend', stopDrawing);
    canvas.addEventListener('touchcancel', stopDrawing);
}

function clearSignature(canvasId) {
    if (!signatureCanvases[canvasId]) {
        console.warn(`Signature canvas not initialized: ${canvasId}`);
        return;
    }
    
    const { canvas, ctx } = signatureCanvases[canvasId];
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Clear all page signatures if clearing the main one
    if (canvasId === 'attendeeSignature') {
        Object.keys(signatureCanvases).forEach(id => {
            if (id !== 'attendeeSignature') {
                const { canvas: c, ctx: context } = signatureCanvases[id];
                context.clearRect(0, 0, c.width, c.height);
            }
        });
    }
}

function generateMeetingNumber() {
    const prefix = document.getElementById('buildingPrefix')?.value;
    if (!prefix) {
        console.warn('Building prefix not found');
        return;
    }
    
    fetch('form_api.php?action=get_next_meeting_number&prefix=' + prefix)
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            const meetingNumberElement = document.getElementById('meetingNumber');
            if (meetingNumberElement) {
                meetingNumberElement.value = result.meeting_number;
                syncToAllPages(); // Sync to other pages if they exist
            }
        } else {
            console.error('Error generating meeting number:', result.message);
            // Fallback to manual number
            const meetingNumberElement = document.getElementById('meetingNumber');
            if (meetingNumberElement) {
                meetingNumberElement.value = prefix + '-001';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Fallback to manual number
        const meetingNumberElement = document.getElementById('meetingNumber');
        if (meetingNumberElement) {
            meetingNumberElement.value = prefix + '-001';
        }
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
