<?php
// /public_html/ghom/reports.php (FINAL VERSION)
function isMobileDevice() {
    // A simple but effective check for common mobile user agents
    return preg_match(
        "/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i",
        $_SERVER["HTTP_USER_AGENT"]
    );
}

// If a mobile device is detected, redirect to the mobile page and stop script execution
if (isMobileDevice()) {
    // Make sure the path to your mobile page is correct
    header('Location: reports_mobile.php');
    exit();
}
// --- BOOTSTRAP & SESSION ---
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();
if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}
$user_role = $_SESSION['role'];
$has_full_access = in_array($user_role, ['admin', 'user', 'superuser']);
if (!$has_full_access && !in_array($user_role, ['cat', 'car', 'coa', 'crs'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}
$pageTitle = "گزارشات پروژه قم";
require_once __DIR__ . '/header.php';
function get_status_persian(?string $status): string {
    $status_map = [
        'OK' => 'تایید شده',
        'Repair' => 'نیاز به تعمیر',
        'Reject' => 'رد شده',
        'Not OK' => 'رد شده',
        'Awaiting Re-inspection' => 'منتظر بازرسی مجدد',
        'Ready for Inspection' => 'آماده بازرسی مجدد',
        'Pre-Inspection Complete' => 'پیش‌بازرسی کامل',
    ];
    return $status_map[$status] ?? $status ?? 'نامشخص';
}
$summary_counts = ['Reject' => 0, 'OK' => 0, 'Repair' => 0, 'Ready for Inspection' => 0];
// --- QUERIES FOR INITIAL PAGE RENDER ONLY ---
try {
    $pdo = getProjectDBConnection('ghom');
    $pdo->exec("SET NAMES 'utf8mb4'");

    // Get all unique plan files for the dropdown menu

     $sql = "
        SELECT
            i.inspection_id, i.element_id, i.part_name, i.stage_id, i.user_id,
            i.status, i.overall_status, i.inspection_date, i.notes,
            i.contractor_status, i.contractor_date, i.contractor_notes,
            i.inspection_cycle, i.repair_rejection_count,
            e.element_type, e.plan_file, e.zone_name, e.contractor,
            e.block, e.axis_span, e.floor_level, e.panel_orientation,
            s.stage AS stage_name, s.display_order,
            i.history_log, i.pre_inspection_log,
            i.created_at
        FROM inspections i
        JOIN elements e ON i.element_id = e.element_id
        LEFT JOIN inspection_stages s ON i.stage_id = s.stage_id
    ";

    $params = [];
    

    $sql .= " ORDER BY i.element_id, i.part_name, i.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_inspection_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
     $grouped_inspections = [];
    foreach ($all_inspection_data as $row) {
        $unique_key = $row['element_id'] . '::' . ($row['part_name'] ?? 'main');
        if (!isset($grouped_inspections[$unique_key])) {
            $grouped_inspections[$unique_key] = ['latest_data' => $row, 'history' => []];
        }
        $grouped_inspections[$unique_key]['history'][] = $row;
        if (strtotime($row['created_at']) > strtotime($grouped_inspections[$unique_key]['latest_data']['created_at'])) {
            $grouped_inspections[$unique_key]['latest_data'] = $row;
        }
    }
     $all_plan_files = $pdo->query("SELECT DISTINCT plan_file FROM elements WHERE plan_file IS NOT NULL ORDER BY plan_file")
     ->fetchAll(PDO::FETCH_COLUMN);
    $plan_counts = array_fill_keys($all_plan_files, 0);
    foreach ($grouped_inspections as $group) {
        $plan_file = $group['latest_data']['plan_file'];
        if ($plan_file && isset($plan_counts[$plan_file])) {
            $plan_counts[$plan_file]++;
        }
    }
     foreach ($grouped_inspections as $group) {
        $latest = $group['latest_data'];
        if ($latest['overall_status'] === 'OK') {
            $summary_counts['OK']++;
        } elseif ($latest['overall_status'] === 'Has Issues' || $latest['overall_status'] === 'Reject') {
             if ($latest['status'] === 'Not OK' || $latest['overall_status'] === 'Reject') {
                $summary_counts['Reject']++;
            } else {
                $summary_counts['Repair']++;
            }
        } elseif ($latest['contractor_status'] === 'Ready for Inspection' || $latest['status'] === 'Ready for Inspection' || $latest['status'] === 'Awaiting Re-inspection') {
            $summary_counts['Ready for Inspection']++;
        }
    }

} catch (Exception $e) {
    error_log("Initial DB Error in reports.php: " . $e->getMessage());
    die("خطا در بارگذاری اولیه صفحه. لطفا با پشتیبانی تماس بگیرید.");
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>
    
    <!-- Jalali Date Picker -->
    <link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    <script src="/ghom/assets/js/jalalidatepicker.min.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="/ghom/assets/css/reports.css">
</head>

<body>
    <!-- Loading Indicator -->
    <div id="loading-overlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div style="font-size: 1.25rem; font-weight: 600;">در حال بارگذاری داده‌های گزارش...</div>
        </div>
    </div>

    <div class="dashboard-container" style="visibility: hidden;">
        <!-- Header -->
        <div class="header-section">
            <div class="header-content">
                <h1 class="header-title">
                    <i class="fas fa-chart-line section-icon"></i>
                    گزارشات پروژه قم
                </h1>
                <button class="theme-switcher">
                    <i class="fas fa-sun"></i>
                </button>
            </div>
        </div>

        <!-- SECTION: PLAN VIEWER -->
        <div class="table-container summary-box">
            <h2>خلاصه وضعیت و مشاهده در نقشه</h2>
            <div class="filters">
                <div class="form-group">
                    <label>۱. انتخاب نقشه:</label>
                    <select id="report-plan-select">
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach ($all_plan_files as $plan):
                            $count = $plan_counts[$plan] ?? 0;
                            $style = $count === 0 ? 'style="background-color: #eeeeee; color: #888;"' : 'style="font-weight: bold;"';
                        ?>
                            <option value="<?php echo escapeHtml($plan); ?>" <?php echo $style; ?>>
                                <?php echo escapeHtml($plan); ?> (<?php echo number_format($count); ?> بازرسی)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="form-group">
                    <label>۲. مشاهده همه المان‌ها با وضعیت:</label>
                    <div class="button-group">
                        <button class="btn report-btn" data-status="Ready for Inspection" style="background-color: #ffc107; color: black;">آماده بازرسی (<?php echo number_format($summary_counts['Ready for Inspection']); ?>)</button>
                        <button class="btn report-btn" data-status="Repair" style="background-color: #17a2b8; color: white;">نیاز به تعمیر (<?php echo number_format($summary_counts['Repair']); ?>)</button>
                        <button class="btn report-btn" data-status="Reject" style="background-color: #dc3545; color: white;">رد شده (<?php echo number_format($summary_counts['Reject']); ?>)</button>
                        <button class="btn report-btn" data-status="OK" style="background-color: #28a745; color: white;">تایید شده (<?php echo number_format($summary_counts['OK']); ?>)</button>
                        <button class="btn" id="open-viewer-btn-all" style="background-color: #6c757d; color: white;">همه وضعیت‌ها</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 1: STATIC OVERALL REPORT -->
        <div class="section-container">
            <div class="section-header">
                <h1 class="section-title">
                    <i class="fas fa-chart-pie section-icon"></i>
                    گزارش کلی پروژه
                </h1>
                <button class="theme-switcher">
                    <i class="fas fa-sun"></i>
                </button>
            </div>
            <div class="kpi-container" id="static-kpi-container"></div>
            <div class="charts-container">
                <div class="chart-wrapper">
                    <h3>خلاصه وضعیت کلی</h3>
                    <div id="staticOverallProgressChart"></div>
                </div>
                <div class="chart-wrapper">
                    <h3>تفکیک نوع المان</h3>
                    <div id="staticProgressByTypeChart"></div>
                </div>
            </div>
        </div>
        
        <!-- SECTION 2: INSPECTION COVERAGE -->
        <div class="section-container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-search section-icon"></i>
                    پوشش بازرسی
                </h2>
            </div>
            <div class="coverage-card" id="overall-coverage-kpi">
                <h3 style="margin-bottom: 1rem; color: white; opacity: 0.9;">پوشش کلی بازرسی</h3>
                <p class="coverage-value" id="overall-coverage-value">0%</p>
                <div id="overall-coverage-details" style="opacity: 0.9;">0 از 0 المان</div>
            </div>
            <div class="charts-container">
                <div class="chart-wrapper">
                    <h3>پوشش بازرسی به تفکیک زون</h3>
                    <div id="coverageByZoneChart"></div>
                </div>
                <div class="chart-wrapper">
                    <h3>پوشش بازرسی به تفکیک بلوک</h3>
                    <div id="coverageByBlockChart"></div>
                </div>
            </div>
        </div>

        <!-- SECTION 3: STAGE PROGRESS REPORT -->
        <div class="section-container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-tasks section-icon"></i>
                    گزارش پیشرفت مراحل
                </h2>
            </div>
            <div class="filter-bar">
                <div class="filter-group">
                    <label for="stage-filter-zone">انتخاب زون:</label>
                    <select id="stage-filter-zone"></select>
                </div>
                <div class="filter-group">
                    <label for="stage-filter-type">انتخاب نوع المان:</label>
                    <select id="stage-filter-type" disabled></select>
                </div>
            </div>
            <div class="chart-wrapper" style="height: 500px; width: 100%;">
                <h3 id="stage-chart-title">برای مشاهده نمودار، یک زون و نوع المان انتخاب کنید</h3>
                <div id="stageProgressChart"></div>
            </div>
        </div>

        <!-- SECTION 4: FLEXIBLE BLOCK/CONTRACTOR REPORT -->
        <div class="section-container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-building section-icon"></i>
                    گزارش جامع پیمانکاران و بلوک‌ها
                </h2>
            </div>
            <div class="filter-bar">
                <div class="filter-group">
                    <label for="flexible-filter-block">انتخاب بلوک:</label>
                    <select id="flexible-filter-block"></select>
                </div>
                <div class="filter-group">
                    <label for="flexible-filter-contractor">انتخاب پیمانکار:</label>
                    <select id="flexible-filter-contractor" disabled></select>
                </div>
            </div>
            <div class="chart-wrapper" style="height: 500px; width: 100%;">
                <h3 id="flexible-chart-title">برای مشاهده نمودار، یک بلوک و پیمانکار انتخاب کنید</h3>
                <div id="flexibleReportChart"></div>
            </div>
        </div>

        <!-- SECTION 5: DATE TRENDS -->
        <div class="section-container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-chart-line section-icon"></i>
                    روند بازرسی‌ها در طول زمان
                </h2>
            </div>
            <div class="chart-wrapper" style="height: 500px; width: 100%;">
                <div class="date-trend-controls">
                    <button class="date-view-btn active" data-view="daily">روزانه</button>
                    <button class="date-view-btn" data-view="weekly">هفتگی</button>
                    <button class="date-view-btn" data-view="monthly">ماهانه</button>
                </div>
                <div id="dateTrendChart"></div>
            </div>
        </div>

        <!-- SECTION 6: PERFORMANCE REPORT -->
        <?php if ($has_full_access): ?>
        <div class="section-container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-trophy section-icon"></i>
                    گزارش عملکرد
                </h2>
            </div>
            <div class="chart-wrapper" style="height: 500px; width: 100%;">
                <div class="date-trend-controls">
                    <button class="performance-view-btn active" data-view="daily">روزانه</button>
                    <button class="performance-view-btn" data-view="weekly">هفتگی</button>
                    <button class="performance-view-btn" data-view="monthly">ماهانه</button>
                </div>
                <div class="charts-container">
                    <div class="chart-wrapper" style="padding: 15px;">
                        <h3>عملکرد بازرسان</h3>
                        <div class="scrollable-chart-container">
                            <div id="inspectorPerformanceChart"></div>
                        </div>
                    </div>
                    <div class="chart-wrapper" style="padding: 15px;">
                        <h3>عملکرد پیمانکاران</h3>
                        <div class="scrollable-chart-container">
                            <div id="contractorPerformanceChart"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SECTION 7: DYNAMIC & FILTERABLE REPORT -->
        <div class="section-container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-filter section-icon"></i>
                    گزارشات پویا و فیلترها
                </h2>
            </div>
            <div class="filter-bar">
                <div class="filter-group">
                    <label for="filter-search">جستجوی کلی:</label>
                    <input type="text" id="filter-search" placeholder="کد، نوع، زون...">
                </div>
                <div class="filter-group">
                    <label for="filter-type">نوع المان:</label>
                    <select id="filter-type">
                        <option value="">همه</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-status">وضعیت:</label>
                    <select id="filter-status">
                        <option value="">همه</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-date-start">تاریخ از:</label>
                    <input type="text" id="filter-date-start" data-jdp>
                </div>
                <div class="filter-group">
                    <label for="filter-date-end">تاریخ تا:</label>
                    <input type="text" id="filter-date-end" data-jdp>
                </div>
                <div class="filter-group">
                    <button id="clear-filters-btn" class="btn btn-secondary">
                        <i class="fas fa-eraser"></i>
                        پاک کردن
                    </button>
                </div>
            </div>
            
            <div class="kpi-container" id="filtered-kpi-container"></div>
            
            <div class="charts-container">
                <div class="chart-wrapper">
                    <h3>خلاصه وضعیت (فیلتر شده)</h3>
                    <div id="filteredStatusChart"></div>
                </div>
                <div class="chart-wrapper">
                    <h3>تفکیک نوع (فیلتر شده)</h3>
                    <div id="filteredTypeChart"></div>
                </div>
                <div class="chart-wrapper">
                    <h3>تفکیک بلوک (فیلتر شده)</h3>
                    <div id="filteredBlockChart"></div>
                </div>
                <div class="chart-wrapper">
                    <h3>تفکیک زون (فیلتر شده)</h3>
                    <div id="filteredZoneChart"></div>
                </div>
            </div>
            
            <div class="table-container">
                <h3>
                    نتایج جستجو
                    <span class="result-badge">
                        <span id="table-result-count">0</span> رکورد
                    </span>
                </h3>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th class="sort" data-sort="element_id">
                                    <i class="fas fa-hashtag" style="margin-left: 0.5rem;"></i>
                                    کد
                                </th>
                                <th class="sort" data-sort="part_name">
                                    <i class="fas fa-puzzle-piece" style="margin-left: 0.5rem;"></i>
                                    بخش
                                </th>
                                <th class="sort" data-sort="element_type">
                                    <i class="fas fa-cube" style="margin-left: 0.5rem;"></i>
                                    نوع
                                </th>
                                <th class="sort" data-sort="zone_name">
                                    <i class="fas fa-map-marker-alt" style="margin-left: 0.5rem;"></i>
                                    زون
                                </th>
                                <th class="sort" data-sort="block">
                                    <i class="fas fa-building" style="margin-left: 0.5rem;"></i>
                                    بلوک
                                </th>
                                <th class="sort" data-sort="final_status">
                                    <i class="fas fa-flag" style="margin-left: 0.5rem;"></i>
                                    وضعیت
                                </th>
                                <th class="sort" data-sort="inspector">
                                    <i class="fas fa-user" style="margin-left: 0.5rem;"></i>
                                    بازرس
                                </th>
                                <th class="sort" data-sort="inspection_date">
                                    <i class="fas fa-calendar" style="margin-left: 0.5rem;"></i>
                                    تاریخ بازرسی
                                </th>
                                <th class="sort" data-sort="contractor_days_passed">
                                    <i class="fas fa-clock" style="margin-left: 0.5rem;"></i>
                                    فاصله از تاریخ پیمانکار
                                </th>
                            </tr>
                        </thead>
                        <tbody id="dynamic-table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="/ghom/assets/js/reports.js" defer></script>

    <?php require_once 'footer.php'; ?>
</body>
</html>