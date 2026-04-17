<?php
// public_html/pardis/select_print_report.php
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

$pageTitle = "تهیه گزارش چاپی - پروژه دانشگاه خاتم پردیس";

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
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@latest/dist/css/persian-datepicker.min.css">

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-printer"></i> تهیه گزارش چاپی</h2>
        <a href="packing_lists.php" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i> بازگشت
        </a>
    </div>

    <div class="row">
        <!-- Quick Reports -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-lightning-charge"></i> گزارش‌های سریع</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-3">
                        <button class="btn btn-outline-primary btn-lg" onclick="generateReport('weekly')">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="text-start">
                                    <i class="bi bi-calendar-week fs-3"></i>
                                    <div class="mt-2">
                                        <strong>گزارش هفتگی</strong>
                                        <div class="small text-muted">7 روز اخیر</div>
                                    </div>
                                </div>
                                <i class="bi bi-arrow-left"></i>
                            </div>
                        </button>

                        <button class="btn btn-outline-success btn-lg" onclick="generateReport('monthly')">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="text-start">
                                    <i class="bi bi-calendar-month fs-3"></i>
                                    <div class="mt-2">
                                        <strong>گزارش ماهانه</strong>
                                        <div class="small text-muted">30 روز اخیر</div>
                                    </div>
                                </div>
                                <i class="bi bi-arrow-left"></i>
                            </div>
                        </button>

                        <button class="btn btn-outline-info btn-lg" onclick="generateReport('all')">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="text-start">
                                    <i class="bi bi-calendar-check fs-3"></i>
                                    <div class="mt-2">
                                        <strong>گزارش کامل</strong>
                                        <div class="small text-muted">تمام رکوردها</div>
                                    </div>
                                </div>
                                <i class="bi bi-arrow-left"></i>
                            </div>
                        </button>
                    </div>

                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="quickIncludeDocs">
                        <label class="form-check-label" for="quickIncludeDocs">
                            شامل تصاویر مدارک (برای چاپ سنگین‌تر می‌شود)
                        </label>
                    </div>

                    <hr class="my-4">

                    <div class="alert alert-success">
                        <h6 class="alert-heading"><i class="bi bi-file-zip"></i> دانلود بسته کامل</h6>
                        <p class="small mb-2">دانلود گزارش PDF + تمام اسناد پیوست در یک فایل ZIP</p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-success btn-sm" onclick="downloadZip('weekly')">
                                <i class="bi bi-download"></i> ZIP هفتگی
                            </button>
                            <button class="btn btn-success btn-sm" onclick="downloadZip('monthly')">
                                <i class="bi bi-download"></i> ZIP ماهانه
                            </button>
                            <button class="btn btn-success btn-sm" onclick="downloadZip('all')">
                                <i class="bi bi-download"></i> ZIP کامل
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Report -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="bi bi-sliders"></i> گزارش سفارشی</h5>
                </div>
                <div class="card-body">
                    <form id="customReportForm">
                        <div class="mb-3">
                            <label class="form-label">از تاریخ *</label>
                            <input type="text" class="form-control persian-datepicker" id="fromDate" placeholder="انتخاب تاریخ" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">تا تاریخ *</label>
                            <input type="text" class="form-control persian-datepicker" id="toDate" placeholder="انتخاب تاریخ" required>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="customIncludeDocs">
                            <label class="form-check-label" for="customIncludeDocs">
                                شامل تصاویر مدارک
                            </label>
                        </div>

                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle"></i>
                            گزارش سفارشی برای بازه زمانی دلخواه شما تهیه می‌شود
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning btn-lg">
                                <i class="bi bi-file-earmark-text"></i> تهیه گزارش سفارشی
                            </button>
                            
                            <button type="button" class="btn btn-outline-success" onclick="downloadCustomZip()">
                                <i class="bi bi-file-zip"></i> دانلود ZIP سفارشی
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Preview -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> آمار کلی پروژه</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center" id="statsContainer">
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Templates Info -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> محتویات گزارش</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-check-circle"></i> اطلاعات شامل:</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-dot"></i> شماره و تاریخ بارنامه‌ها</li>
                                <li><i class="bi bi-dot"></i> نوع و تعداد متریال‌های دریافتی</li>
                                <li><i class="bi bi-dot"></i> اطلاعات تامین‌کننده</li>
                                <li><i class="bi bi-dot"></i> انبار مقصد</li>
                                <li><i class="bi bi-dot"></i> یادداشت‌ها و توضیحات</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success"><i class="bi bi-bar-chart"></i> خلاصه آماری شامل:</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-dot"></i> تعداد کل بارنامه‌ها</li>
                                <li><i class="bi bi-dot"></i> مجموع تعداد دریافتی</li>
                                <li><i class="bi bi-dot"></i> تعداد انواع متریال</li>
                                <li><i class="bi bi-dot"></i> گروه‌بندی بر اساس نوع متریال</li>
                                <li><i class="bi bi-dot"></i> آمار انبارها</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/persian-date@latest/dist/persian-date.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Persian datepickers
    $('.persian-datepicker').persianDatepicker({
        format: 'YYYY/MM/DD',
        observer: true,
        autoClose: true,
        calendar: { 
            persian: { 
                locale: 'fa',
                leapYearMode: 'astronomical'
            } 
        }
    });
    
    // Load statistics
    loadStatistics();
    
    // Custom report form submission
    document.getElementById('customReportForm').addEventListener('submit', function(e) {
        e.preventDefault();
        generateCustomReport();
    });
});

function convertPersianToLatin(str) {
    if (!str) return str;
    const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    const latinNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    
    let result = str;
    for (let i = 0; i < 10; i++) {
        result = result.replace(new RegExp(persianNumbers[i], 'g'), latinNumbers[i]);
        result = result.replace(new RegExp(arabicNumbers[i], 'g'), latinNumbers[i]);
    }
    return result;
}

function generateReport(type) {
    const includeDocs = document.getElementById('quickIncludeDocs').checked ? '1' : '0';
    const url = `print_report.php?type=${type}&include_docs=${includeDocs}`;
    window.open(url, '_blank');
}

function generateCustomReport() {
    let fromDate = document.getElementById('fromDate').value.trim();
    let toDate = document.getElementById('toDate').value.trim();
    
    if (!fromDate || !toDate) {
        alert('لطفاً هر دو تاریخ را انتخاب کنید');
        return;
    }
    
    // Convert Persian/Arabic numerals to Latin
    fromDate = convertPersianToLatin(fromDate);
    toDate = convertPersianToLatin(toDate);
    
    // Validate date range
    const fromParts = fromDate.split('/').map(Number);
    const toParts = toDate.split('/').map(Number);
    
    const fromValue = fromParts[0] * 10000 + fromParts[1] * 100 + fromParts[2];
    const toValue = toParts[0] * 10000 + toParts[1] * 100 + toParts[2];
    
    if (fromValue > toValue) {
        alert('تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد');
        return;
    }
    
    const includeDocs = document.getElementById('customIncludeDocs').checked ? '1' : '0';
    const url = `print_report.php?type=custom&from_date=${encodeURIComponent(fromDate)}&to_date=${encodeURIComponent(toDate)}&include_docs=${includeDocs}`;
    window.open(url, '_blank');
}

function downloadZip(type) {
    const includeDocs = document.getElementById('quickIncludeDocs').checked ? '1' : '0';
    
    // Show loading indicator
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> در حال آماده‌سازی...';
    
    const url = `generate_report_zip.php?type=${type}&include_docs=${includeDocs}`;
    
    // Create hidden iframe for download
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = url;
    document.body.appendChild(iframe);
    
    // Reset button after delay
    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        document.body.removeChild(iframe);
    }, 3000);
}

function downloadCustomZip() {
    let fromDate = document.getElementById('fromDate').value.trim();
    let toDate = document.getElementById('toDate').value.trim();
    
    if (!fromDate || !toDate) {
        alert('لطفاً هر دو تاریخ را انتخاب کنید');
        return;
    }
    
    // Convert Persian/Arabic numerals to Latin
    fromDate = convertPersianToLatin(fromDate);
    toDate = convertPersianToLatin(toDate);
    
    // Validate date range
    const fromParts = fromDate.split('/').map(Number);
    const toParts = toDate.split('/').map(Number);
    
    const fromValue = fromParts[0] * 10000 + fromParts[1] * 100 + fromParts[2];
    const toValue = toParts[0] * 10000 + toParts[1] * 100 + toParts[2];
    
    if (fromValue > toValue) {
        alert('تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد');
        return;
    }
    
    const includeDocs = document.getElementById('customIncludeDocs').checked ? '1' : '0';
    
    // Show loading indicator
    const btn = event.target;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> در حال آماده‌سازی...';
    
    const url = `generate_report_zip.php?type=custom&from_date=${encodeURIComponent(fromDate)}&to_date=${encodeURIComponent(toDate)}&include_docs=${includeDocs}`;
    
    // Create hidden iframe for download
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = url;
    document.body.appendChild(iframe);
    
    // Reset button after delay
    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        document.body.removeChild(iframe);
    }, 3000);
}

function loadStatistics() {
    fetch('zirsazi_api.php?action=get_packing_lists')
    .then(res => res.json())
    .then(result => {
        if (!result.success || !result.data) {
            throw new Error('Failed to load data');
        }
        
        const data = result.data;
        
        // Calculate statistics
        const totalItems = data.length;
        const totalQuantity = data.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0);
        const uniqueMaterials = [...new Set(data.map(item => item.material_type))].length;
        const uniqueWarehouses = [...new Set(data.map(item => item.warehouse_name).filter(w => w))].length;
        
        // Display statistics
        const statsHtml = `
            <div class="col-md-3">
                <div class="border rounded p-3 bg-light">
                    <div class="text-primary fs-1 mb-2">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <h3 class="text-primary mb-1">${totalItems.toLocaleString('fa-IR')}</h3>
                    <p class="text-muted mb-0">بارنامه ثبت شده</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 bg-light">
                    <div class="text-success fs-1 mb-2">
                        <i class="bi bi-boxes"></i>
                    </div>
                    <h3 class="text-success mb-1">${totalQuantity.toLocaleString('fa-IR')}</h3>
                    <p class="text-muted mb-0">تعداد کل دریافتی</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 bg-light">
                    <div class="text-info fs-1 mb-2">
                        <i class="bi bi-grid-3x3"></i>
                    </div>
                    <h3 class="text-info mb-1">${uniqueMaterials.toLocaleString('fa-IR')}</h3>
                    <p class="text-muted mb-0">نوع متریال</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 bg-light">
                    <div class="text-warning fs-1 mb-2">
                        <i class="bi bi-building"></i>
                    </div>
                    <h3 class="text-warning mb-1">${uniqueWarehouses.toLocaleString('fa-IR')}</h3>
                    <p class="text-muted mb-0">انبار فعال</p>
                </div>
            </div>
        `;
        
        document.getElementById('statsContainer').innerHTML = statsHtml;
    })
    .catch(error => {
        console.error('Error loading statistics:', error);
        document.getElementById('statsContainer').innerHTML = `
            <div class="col-12">
                <div class="alert alert-danger">
                    خطا در بارگذاری آمار: ${error.message}
                </div>
            </div>
        `;
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>