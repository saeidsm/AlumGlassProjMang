<?php
// public_html/pardis/forms_list.php
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

$pageTitle = "مدیریت فرم‌ها - پروژه دانشگاه خاتم پردیس";

function isMobileDevices() {
    return preg_match(
        "/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i",
        $_SERVER["HTTP_USER_AGENT"]
    );
}

if (isMobileDevices()) {
    require_once __DIR__ . '/header.php';
} else {
    require_once __DIR__ . '/header.php';
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-folder-fill"></i> مدیریت فرم‌ها و اسناد</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i> بازگشت
        </a>
    </div>

    <div class="row">
        <!-- Meeting Minutes Form -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-file-text" style="font-size: 48px; color: #007bff;"></i>
                    </div>
                    <h5 class="card-title">فرم صورتجلس</h5>
                    <p class="card-text text-muted">
                        ثبت و مدیریت صورتجلسات جلسات پروژه با امکان امضای دیجیتال
                    </p>
                    <div class="d-grid gap-2">
                        <a href="meeting_minutes_form.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> ایجاد صورتجلس جدید
                        </a>
                        <a href="saved_minutes_list.php" class="btn btn-outline-secondary">
                            <i class="bi bi-list-ul"></i> مشاهده صورتجلسات
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delivery Receipt Form -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-truck" style="font-size: 48px; color: #28a745;"></i>
                    </div>
                    <h5 class="card-title">فرم حواله تحویل</h5>
                    <p class="card-text text-muted">
                        ثبت و مدیریت حواله‌های تحویل مصالح و تجهیزات
                    </p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-success" disabled>
                            <i class="bi bi-plus-circle"></i> ایجاد حواله جدید
                        </button>
                        <button class="btn btn-outline-secondary" disabled>
                            <i class="bi bi-list-ul"></i> مشاهده حواله‌ها
                        </button>
                    </div>
                    <small class="text-muted d-block mt-2">به زودی...</small>
                </div>
            </div>
        </div>

        <!-- Inspection Form -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-clipboard-check" style="font-size: 48px; color: #ffc107;"></i>
                    </div>
                    <h5 class="card-title">فرم بازرسی و کنترل</h5>
                    <p class="card-text text-muted">
                        ثبت نتایج بازرسی‌ها و کنترل کیفیت پروژه
                    </p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-warning" disabled>
                            <i class="bi bi-plus-circle"></i> ایجاد فرم بازرسی
                        </button>
                        <button class="btn btn-outline-secondary" disabled>
                            <i class="bi bi-list-ul"></i> مشاهده بازرسی‌ها
                        </button>
                    </div>
                    <small class="text-muted d-block mt-2">به زودی...</small>
                </div>
            </div>
        </div>

        <!-- Daily Report Form -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-calendar-day" style="font-size: 48px; color: #17a2b8;"></i>
                    </div>
                    <h5 class="card-title">گزارش روزانه</h5>
                    <p class="card-text text-muted">
                        ثبت گزارش فعالیت‌های روزانه پروژه
                    </p>
                     <?php if ($is_contractor): ?>
            <a href="daily_report_form_ps.php" class="btn btn-primary shadow"><i class="fas fa-plus"></i> ثبت گزارش جدید</a>
        <?php endif; ?>
                    <div class="d-grid gap-2">
                           <?php if ($is_contractor): ?>
                         <a href="daily_report_form_ps.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> ایجاد گزارش روزانه جدید
                        </a>
                        <?php endif; ?>
                        <a href="saved_minutes_list.php" class="btn btn-outline-secondary">
                            <i class="bi bi-list-ul"></i> مشاهده صورتجلسات
                        </a>
                        <button class="btn btn-info" disabled>
                            
                            <i class="bi bi-plus-circle"></i> ایجاد گزارش روزانه
                        </button>
                        <button class="btn btn-outline-secondary" disabled>
                            <a href="saved_minutes_list.php" class="btn btn-outline-secondary">
                            <i class="bi bi-list-ul"></i> مشاهده صورتجلسات
                        </a> مشاهده گزارش‌ها
                        </button>
                    </div>
                    <small class="text-muted d-block mt-2">به زودی...</small>
                </div>
            </div>
        </div>

        <!-- RFI Form -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-question-circle" style="font-size: 48px; color: #dc3545;"></i>
                    </div>
                    <h5 class="card-title">فرم درخواست اطلاعات (RFI)</h5>
                    <p class="card-text text-muted">
                        ثبت و پیگیری درخواست‌های اطلاعات فنی
                    </p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-danger" disabled>
                            <i class="bi bi-plus-circle"></i> ایجاد RFI جدید
                        </button>
                        <button class="btn btn-outline-secondary" disabled>
                            <i class="bi bi-list-ul"></i> مشاهده RFI‌ها
                        </button>
                    </div>
                    <small class="text-muted d-block mt-2">به زودی...</small>
                </div>
            </div>
        </div>

        <!-- Work Order Form -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-wrench" style="font-size: 48px; color: #6c757d;"></i>
                    </div>
                    <h5 class="card-title">دستور کار</h5>
                    <p class="card-text text-muted">
                        صدور و مدیریت دستورات کار پروژه
                    </p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-secondary" disabled>
                            <i class="bi bi-plus-circle"></i> ایجاد دستور کار
                        </button>
                        <button class="btn btn-outline-secondary" disabled>
                            <i class="bi bi-list-ul"></i> مشاهده دستورات
                        </button>
                    </div>
                    <small class="text-muted d-block mt-2">به زودی...</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> آمار کلی فرم‌ها</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h3 class="text-primary mb-2" id="totalForms">0</h3>
                                <p class="text-muted mb-0">کل فرم‌های ایجاد شده</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h3 class="text-success mb-2" id="completedForms">0</h3>
                                <p class="text-muted mb-0">فرم‌های تکمیل شده</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h3 class="text-warning mb-2" id="pendingForms">0</h3>
                                <p class="text-muted mb-0">در حال پیگیری</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h3 class="text-info mb-2" id="thisMonthForms">0</h3>
                                <p class="text-muted mb-0">فرم‌های ماه جاری</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load statistics (placeholder for now)
    document.getElementById('totalForms').textContent = '0';
    document.getElementById('completedForms').textContent = '0';
    document.getElementById('pendingForms').textContent = '0';
    document.getElementById('thisMonthForms').textContent = '0';
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>