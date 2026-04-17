<?php
// public_html/pardis/daily_report_view.php

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

$report_id = intval($_GET['id'] ?? 0);
if (!$report_id) {
    header('Location: daily_reports.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'user';
$pdo = getProjectDBConnection('pardis');
// Fetch report data
$stmt = $pdo->prepare("
    SELECT dr.*, tt.arrival_time, tt.departure_time, tt.break_duration
    FROM daily_reports dr
    LEFT JOIN time_tracking tt ON dr.id = tt.report_id
    WHERE dr.id = ?
");
$stmt->execute([$report_id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$report) {
    header('Location: daily_reports.php?msg=not_found');
    exit();
}

// Check permission
if (!in_array($user_role, ['admin', 'superuser', 'coa']) && $report['user_id'] != $user_id) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

// Get activities
$stmt = $pdo->prepare("SELECT * FROM report_activities WHERE report_id = ? ORDER BY id");
$stmt->execute([$report_id]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attachments
$stmt = $pdo->prepare("SELECT * FROM report_attachments WHERE report_id = ? ORDER BY id");
$stmt->execute([$report_id]);
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get issues
$stmt = $pdo->prepare("SELECT * FROM report_issues WHERE report_id = ? ORDER BY priority DESC");
$stmt->execute([$report_id]);
$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "مشاهده گزارش روزانه";
require_once __DIR__ . '/header.php';

// Role labels
$role_labels = [
    'field_engineer' => 'مهندس میدانی',
    'designer' => 'طراح',
    'surveyor' => 'نقشه‌بردار',
    'control_engineer' => 'مهندس کنترل پروژه',
    'drawing_specialist' => 'متخصص نقشه‌کشی'
];

$status_labels = [
    'not_started' => 'شروع نشده',
    'in_progress' => 'در حال انجام',
    'completed' => 'تکمیل شده',
    'blocked' => 'مسدود شده',
    'delayed' => 'تاخیر دارد'
];

$weather_labels = [
    'clear' => 'آفتابی',
    'cloudy' => 'ابری',
    'rainy' => 'بارانی',
    'hot' => 'گرم',
    'cold' => 'سرد',
    'other' => 'سایر'
];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            background: #f8f9fa;
        }
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .info-box {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-right: 4px solid #0d6efd;
        }
        .activity-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
        }
        .status-in_progress { background: #fff3cd; color: #856404; }
        .status-completed { background: #d1e7dd; color: #0f5132; }
        .status-blocked { background: #f8d7da; color: #842029; }
        .status-delayed { background: #f8d7da; color: #842029; }
        .progress-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: conic-gradient(#0d6efd 0deg, #e9ecef 0deg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
        }
        .progress-circle::before {
            content: '';
            position: absolute;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: white;
        }
        .progress-circle span {
            position: relative;
            z-index: 1;
        }
        .attachment-item {
            display: inline-block;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 5px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }
        .attachment-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
@media print {
    /* Reset and base styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        font-family: 'Tahoma', 'Arial', sans-serif;
        color: #000;
        font-size: 11pt;
        line-height: 1.5;
    }
    
    @page {
        size: A4 portrait;
        margin: 15mm;
    }
    
    /* Hide non-printable elements */
    .no-print, .btn, button, nav, .navbar, header, footer {
        display: none !important;
    }
    
    .container {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    /* Report header */
    .report-header {
        background: white !important;
        color: #000 !important;
        border: 2px solid #000;
        padding: 15px;
        margin-bottom: 15px;
        page-break-inside: avoid;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .report-header h2 {
        font-size: 16pt;
        margin-bottom: 10px;
        border-bottom: 2px solid #000;
        padding-bottom: 5px;
    }
    
    /* Cards and sections */
    .card {
        border: 1px solid #333 !important;
        margin-bottom: 10px !important;
        page-break-inside: avoid;
        background: white !important;
    }
    
    .card-header {
        background: #f0f0f0 !important;
        color: #000 !important;
        border-bottom: 2px solid #000 !important;
        padding: 10px !important;
        font-weight: bold;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .card-body {
        padding: 10px !important;
    }
    
    /* Activity cards */
    .activity-card {
        border: 1px solid #ddd;
        padding: 10px;
        margin-bottom: 10px;
        page-break-inside: avoid;
    }
    
    /* Progress bars */
    .progress {
        border: 1px solid #000 !important;
        background: white !important;
        height: 20px !important;
    }
    
    .progress-bar {
        background: #333 !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    /* Info boxes */
    .info-box {
        border: 1px solid #333;
        padding: 8px;
        margin-bottom: 8px;
        page-break-inside: avoid;
    }
    
    /* Badges */
    .badge, .status-badge {
        border: 1px solid #000 !important;
        padding: 3px 8px !important;
        background: white !important;
        color: #000 !important;
    }
}

/* Print header and footer on every page */
@media print {
    .print-header {
        display: block;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 10pt;
        padding: 5mm;
        border-bottom: 1px solid #000;
    }
    
    .print-footer {
        display: block;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 9pt;
        padding: 5mm;
        border-top: 1px solid #000;
    }
    
    .print-footer::after {
        content: "صفحه " counter(page);
    }
}
    </style>
</head>
<body>
    <div class="print-header" style="display: none;">
    سیستم گزارش روزانه - پروژه دانشگاه خاتم قم
</div>
<div class="print-footer" style="display: none;">
    <span style="float: right;">تاریخ چاپ: <?php echo jdate('Y/m/d'); ?></span>
    <span style="float: left;">گزارش روزانه - <?php echo htmlspecialchars($report['engineer_name']); ?></span>
</div>
    <div class="container mt-4 mb-5">
        <!-- Back Button -->
        <div class="mb-3 text-end no-print">
    <a href="daily_reports.php" class="btn btn-outline-dark">بازگشت به لیست</a>
    
    <!-- This button now opens the new print-only page -->
    <button class="btn btn-primary" onclick="window.open('daily_report_print.php?id=<?php echo $report_id; ?>', '_blank');">چاپ</button>
    
    <?php if ($report['user_id'] == $user_id && $user_role !== 'user' || in_array($user_role, ['admin', 'superuser'])): ?>
    <a href="daily_report_edit.php?id=<?php echo $report_id; ?>" class="btn btn-warning">ویرایش</a>
    <?php endif; ?>
</div>

        <!-- Report Header -->
        <div class="report-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-3">گزارش روزانه</h2>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2"><i class="bi bi-person-fill"></i> <strong>مهندس:</strong> <?php echo htmlspecialchars($report['engineer_name']); ?></p>
                            <p class="mb-2"><i class="bi bi-briefcase-fill"></i> <strong>نقش:</strong> <?php echo $role_labels[$report['role']] ?? $report['role']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2"><i class="bi bi-calendar3"></i> <strong>تاریخ:</strong> <?php echo gregorian_to_jalali_full($report['report_date']); ?></p>
                            <p class="mb-2"><i class="bi bi-geo-alt-fill"></i> <strong>محل:</strong> <?php echo htmlspecialchars($report['location'] ?: 'نامشخص'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <?php
                    $total_progress = 0;
                    if (count($activities) > 0) {
                        foreach ($activities as $act) {
                            $total_progress += $act['progress_percentage'];
                        }
                        $total_progress = round($total_progress / count($activities));
                    }
                    ?>
                    <div class="progress-circle mx-auto" style="background: conic-gradient(#fff <?php echo $total_progress * 3.6; ?>deg, #e9ecef 0deg);">
                        <span><?php echo $total_progress; ?>%</span>
                    </div>
                    <p class="mt-2 mb-0">میانگین پیشرفت</p>
                </div>
            </div>
        </div>

        <!-- General Information -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="info-box">
                    <h6 class="text-muted mb-2">آب و هوا</h6>
                    <p class="mb-0"><i class="bi bi-cloud-sun"></i> <?php echo $weather_labels[$report['weather']] ?? 'نامشخص'; ?></p>
                </div>
            </div>
            <div class="col-md-3">
        <div class="info-box">
            <h6 class="text-muted mb-2">ساعات کاری</h6>
            <p class="mb-0">
                <i class="bi bi-clock"></i> <?php echo number_format($report['work_hours'], 2); ?> ساعت
                <?php if ($report['had_lunch_break']): ?>
                    <small class="text-muted">(با کسر استراحت)</small>
                <?php endif; ?>
            </p>
        </div>
    </div>
            <div class="col-md-3">
                <div class="info-box">
                    <h6 class="text-muted mb-2">ورود</h6>
                    <p class="mb-0"><i class="bi bi-box-arrow-in-left"></i> <?php echo $report['arrival_time'] ?: 'ثبت نشده'; ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box">
                    <h6 class="text-muted mb-2">خروج</h6>
                    <p class="mb-0"><i class="bi bi-box-arrow-right"></i> <?php echo $report['departure_time'] ?: 'ثبت نشده'; ?></p>
                </div>
            </div>
        </div>

        <!-- Activities -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-list-task"></i> فعالیت‌های انجام شده (<?php echo count($activities); ?>)
            </div>
            <div class="card-body">
                <?php if (count($activities) > 0): ?>
                    <?php foreach ($activities as $index => $activity): ?>
                    <div class="activity-card">
                        <div class="row align-items-center">
                            <div class="col-md-1 text-center">
                                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <?php echo $index + 1; ?>
                                </div>
                            </div>
                            <div class="col-md-5">
    <h6 class="mb-1"><?php echo htmlspecialchars($activity['task_description']); ?></h6>
    <?php if ($activity['task_type']): ?>
        <small class="text-muted">
            <i class="bi bi-tag"></i> 
            <?php echo htmlspecialchars($activity['task_type']); ?> - 
            <?php echo htmlspecialchars($activity['project_name'] ?? $report['project_name']); ?> / 
            <?php echo htmlspecialchars($activity['building_name'] ?? $report['building_name']); ?>
        </small>
    <?php endif; ?>
</div>
                            <div class="col-md-2">
                                <span class="status-badge status-<?php echo $activity['status']; ?>">
                                    <?php echo $status_labels[$activity['status']] ?? $activity['status']; ?>
                                </span>
                            </div>
                            <div class="col-md-2">
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $activity['progress_percentage']; ?>%">
                                        <?php echo $activity['progress_percentage']; ?>%
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 text-center">
                                <?php if ($activity['hours_spent'] > 0): ?>
                                <p class="mb-0"><i class="bi bi-clock"></i> <?php echo $activity['hours_spent']; ?> ساعت</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center py-4">فعالیتی ثبت نشده است</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Issues and Blockers -->
        <?php if (!empty($report['issues_blockers'])): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-exclamation-triangle"></i> موانع و مشکلات
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($report['issues_blockers'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Next Day Plan -->
        <?php if (!empty($report['next_day_plan'])): ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-calendar-check"></i> برنامه فردا
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($report['next_day_plan'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- General Notes -->
        <?php if (!empty($report['general_notes'])): ?>
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <i class="bi bi-chat-left-text"></i> یادداشت‌های عمومی
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($report['general_notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Attachments -->
        <?php if (count($attachments) > 0): ?>
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-paperclip"></i> پیوست‌ها (<?php echo count($attachments); ?>)
            </div>
            <div class="card-body">
                <?php foreach ($attachments as $attachment): ?>
                    <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank" class="attachment-item">
                        <i class="bi bi-file-earmark"></i>
                        <?php echo htmlspecialchars($attachment['file_name']); ?>
                        <small class="text-muted">(<?php echo formatFileSize($attachment['file_size']); ?>)</small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Safety -->
        <?php if ($report['safety_incident'] === 'yes'): ?>
        <div class="alert alert-danger">
            <i class="bi bi-shield-exclamation"></i> <strong>حادثه ایمنی گزارش شده است</strong>
            <?php if (!empty($report['safety_notes'])): ?>
            <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($report['safety_notes'])); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer Info -->
        <div class="card">
            <div class="card-body bg-light">
                <div class="row text-center">
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="bi bi-calendar-plus"></i> ثبت شده: 
                            <?php echo jdate('Y/m/d H:i', strtotime($report['created_at'])); ?>
                        </small>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="bi bi-pencil-square"></i> آخرین ویرایش: 
                            <?php echo jdate('Y/m/d H:i', strtotime($report['updated_at'])); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
require_once __DIR__ . '/footer.php';

// Helper functions
function gregorian_to_jalali_full($date) {
    if (empty($date)) return 'نامشخص';
    $parts = explode('-', $date);
    if (count($parts) != 3) return $date;
    
    if (function_exists('gregorian_to_jalali')) {
        list($j_y, $j_m, $j_d) = gregorian_to_jalali($parts[0], $parts[1], $parts[2]);
        return "$j_y/$j_m/$j_d";
    }
    return $date;
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}
?>