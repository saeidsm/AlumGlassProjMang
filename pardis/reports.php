<?php
// /public_html/pardis/reports.php (FINAL VERSION)
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
$pageTitle = "گزارشات پروژه دانشگاه خاتم";
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
    $pdo = getProjectDBConnection('pardis');
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
    <link rel="stylesheet" href="/pardis/assets/css/jalalidatepicker.min.css" />
    <script src="/pardis/assets/js/jalalidatepicker.min.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        @font-face {
            font-family: "Samim";
            src: url("/pardis/assets/fonts/Samim-FD.woff2") format("woff2"),
                 url("/pardis/assets/fonts/Samim-FD.woff") format("woff"),
                 url("/pardis/assets/fonts/Samim-FD.ttf") format("truetype");
        }

        :root {
            --primary: #1e40af;
            --primary-light: #3b82f6;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --background: #f8fafc;
            --surface: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            --gradient-success: linear-gradient(135deg, #059669 0%, var(--success) 100%);
            --gradient-warning: linear-gradient(135deg, #d97706 0%, var(--warning) 100%);
            --gradient-error: linear-gradient(135deg, #dc2626 0%, var(--error) 100%);
        }

        html.dark {
            --primary: #60a5fa;
            --primary-light: #93c5fd;
            --secondary: #94a3b8;
            --success: #34d399;
            --warning: #fbbf24;
            --error: #f87171;
            --background: #0f172a;
            --surface: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border: #334155;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Samim", -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            transition: all 0.3s ease;
            padding: 0;
        }

        .dashboard-container {
            max-width: 1900px;
            margin: 0 auto;
            padding: 20px;
        }

        .header-section {
            background: var(--surface);
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--gradient-primary);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .section-container {
            background: var(--surface);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 2rem;
            border: 1px solid var(--border);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .section-container:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .section-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-icon {
            color: var(--primary);
            font-size: 1.5rem;
        }

        .theme-switcher {
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 50%;
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.5rem;
            color: var(--text-primary);
        }

        .theme-switcher:hover {
            transform: scale(1.1);
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }

        .kpi-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: var(--background);
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            transition: all 0.3s ease;
        }

        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .kpi-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .kpi-card .value {
            font-size: 3rem;
            font-weight: 700;
            margin: 0.5rem 0;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--primary) 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .kpi-card .details {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .kpi-card.total::before { background: var(--gradient-primary); }
        .kpi-card.ok::before { background: var(--gradient-success); }
        .kpi-card.ready::before { background: var(--gradient-warning); }
        .kpi-card.issues::before { background: var(--gradient-error); }

        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
        }

        .chart-wrapper {
            background: var(--background);
            padding: 1.5rem;
            border-radius: 12px;
            height: 450px;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--border);
        }

        .chart-wrapper h3 {
            text-align: center;
            margin: 0 0 1rem;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            flex-shrink: 0;
        }

        .chart-wrapper > div[id] {
            flex-grow: 1;
            min-height: 0;
        }

        .filter-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: flex-end;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--background);
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .filter-group input,
        .filter-group select {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 2px solid var(--border);
            background-color: var(--surface);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-family: inherit;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background-color: var(--secondary);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7c8a9e;
        }

        .report-btn {
            margin: 0.25rem;
            font-size: 0.9rem;
        }

        .status-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .table-container {
            margin-top: 2rem;
        }

        .table-container h3 {
            margin-bottom: 1rem;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .result-badge {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .table-wrapper {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem 1.25rem;
            text-align: right;
            border-bottom: 1px solid var(--border);
        }

        thead th {
            background: var(--primary);
            color: white;
            position: sticky;
            top: 0;
            z-index: 1;
            cursor: pointer;
            user-select: none;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        thead th:hover {
            background: var(--primary-light);
        }

        thead th.sort.asc::after {
            content: " ▲";
            font-size: 0.8em;
            margin-right: 0.5rem;
        }

        thead th.sort.desc::after {
            content: " ▼";
            font-size: 0.8em;
            margin-right: 0.5rem;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }

        tbody td {
            background: var(--surface);
        }

        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.8rem;
            color: white;
        }

        .date-trend-controls,
        .tab-buttons {
            text-align: center;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            background: var(--background);
            padding: 0.25rem;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .date-trend-controls button,
        .tab-button {
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .date-trend-controls button.active,
        .tab-button.active {
            background: var(--primary);
            color: white;
            box-shadow: var(--shadow);
        }

        .date-trend-controls button:hover:not(.active),
        .tab-button:hover:not(.active) {
            background: var(--border);
            color: var(--text-primary);
        }

        .coverage-card {
            text-align: center;
            padding: 2.5rem;
            background: var(--gradient-primary);
            color: white;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            max-width: 400px;
            margin: 0 auto 2rem auto;
        }

        .coverage-value {
            font-size: 4rem;
            font-weight: 700;
            margin: 1rem 0;
        }

        .scrollable-chart-container {
            position: relative;
            height: calc(100% - 60px);
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.3s ease;
        }

        html.dark #loading-overlay {
            background: rgba(15, 23, 42, 0.95);
        }

        .loading-content {
            text-align: center;
            color: var(--text-primary);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .plan-viewer-section {
            margin-bottom: 2rem;
        }

        .plan-viewer-section > div {
            margin-bottom: 1.5rem;
        }

        .plan-viewer-section label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1024px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .kpi-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            .header-title {
                font-size: 2rem;
            }
            
            .kpi-container {
                grid-template-columns: 1fr;
            }
            
            .filter-bar {
                grid-template-columns: 1fr;
            }
            
            .status-buttons {
                flex-direction: column;
            }
            
            .date-trend-controls,
            .tab-buttons {
                flex-direction: column;
            }
        }
    </style>
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
                    گزارشات پروژه پردیس
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

    <script>
     
     let unfinishedTasksData = [];
let assignedTasksData = [];
     // Main function to fetch data and then initialize the dashboard
        async function loadDashboard() {
            const loadingOverlay = document.getElementById('loading-overlay');
            const dashboardGrid = document.querySelector('.dashboard-container');
            console.log("Fetching data from server...");
            
            try {
                const response = await fetch('get_chart_data.php');
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Server responded with status ${response.status}: ${errorText}`);
                }
                const chartData = await response.json();
                console.log("Data successfully received:", chartData);

                // Hide loading overlay and initialize the dashboard
                loadingOverlay.style.opacity = '0';
                setTimeout(() => loadingOverlay.style.display = 'none', 300);
                dashboardGrid.style.visibility = 'visible';
                
                initializeDashboard(chartData);

            } catch (error) {
                console.error("Failed to load or parse chart data:", error);
                loadingOverlay.innerHTML = `
                    <div class="loading-content">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--error); margin-bottom: 1rem;"></i>
                        <div style="font-size: 1.25rem; font-weight: 600; color: var(--error);">خطا در بارگذاری داده‌ها</div>
                        <div style="margin-top: 0.5rem; color: var(--text-secondary);">لطفا صفحه را مجدداً بارگذاری کنید</div>
                    </div>
                `;
            }
        }

        // This function holds all the logic and accepts the data as an argument
        function initializeDashboard(chartData) {
            if (typeof ApexCharts === 'undefined') {
                console.error("ApexCharts library has not loaded.");
                return;
            }

            const {
                allInspectionsData, trendData, stageProgressData,
                flexibleReportData, coverageData, performanceData
            } = chartData;

            let currentlyDisplayedData = [...allInspectionsData];
            let currentSort = { key: 'inspection_date', dir: 'desc' };
            const chartInstances = {};
            let statusColors = {}, trendStatusColors = {}, itemStatusColors = {};

            const domRefs = {
                htmlEl: document.documentElement,
                themeSwitchers: document.querySelectorAll('.theme-switcher'),
                staticKpiContainer: document.getElementById('static-kpi-container'),
                filteredKpiContainer: document.getElementById('filtered-kpi-container'),
                searchInput: document.getElementById('filter-search'),
                typeSelect: document.getElementById('filter-type'),
                statusSelect: document.getElementById('filter-status'),
                startDateEl: document.getElementById('filter-date-start'),
                endDateEl: document.getElementById('filter-date-end'),
                clearFiltersBtn: document.getElementById('clear-filters-btn'),
                tableBody: document.getElementById('dynamic-table-body'),
                resultCountEl: document.getElementById('table-result-count'),
                tableHeaders: document.querySelectorAll('th.sort'),
                dateViewButtons: document.querySelectorAll('.date-view-btn'),
                stageZoneFilter: document.getElementById('stage-filter-zone'),
                stageTypeFilter: document.getElementById('stage-filter-type'),
                stageChartTitle: document.getElementById('stage-chart-title'),
                flexibleBlockFilter: document.getElementById('flexible-filter-block'),
                flexibleContractorFilter: document.getElementById('flexible-filter-contractor'),
                flexibleChartTitle: document.getElementById('flexible-chart-title'),
                overallCoverageValue: document.getElementById('overall-coverage-value'),
                overallCoverageDetails: document.getElementById('overall-coverage-details'),
                performanceViewButtons: document.querySelectorAll('.performance-view-btn'),
                reportZoneSelect: document.getElementById('report-zone-select'),
                reportButtons: document.querySelectorAll('.report-btn'),
            };

            // --- HELPER FUNCTIONS ---
            function getCssVar(varName) { 
                return getComputedStyle(document.documentElement).getPropertyValue(varName).trim(); 
            }
            
            function updateChartColors() {
                statusColors = { 
                    'در انتظار': getCssVar('--secondary'), 
                    'آماده بازرسی اولیه': getCssVar('--primary'), 
                    'منتظر بازرسی مجدد': getCssVar('--warning'), 
                    'نیاز به تعمیر': getCssVar('--warning'), 
                    'تایید شده': getCssVar('--success'), 
                    'رد شده': getCssVar('--error') 
                };
                trendStatusColors = { 
                    'Pending': getCssVar('--secondary'), 
                    'Pre-Inspection Complete': getCssVar('--primary'), 
                    'Awaiting Re-inspection': getCssVar('--warning'), 
                    'Repair': getCssVar('--warning'), 
                    'OK': getCssVar('--success'), 
                    'Reject': getCssVar('--error') 
                };
                itemStatusColors = { 
                    'OK': getCssVar('--success'), 
                    'Not OK': getCssVar('--error'), 
                    'N/A': getCssVar('--secondary') 
                };
            }

            function getBaseChartOptions() {
                const isDark = domRefs.htmlEl.classList.contains('dark');
                return {
                    chart: { 
                        fontFamily: 'Samim', 
                        background: 'transparent', 
                        foreColor: getCssVar('--text-primary'), 
                        toolbar: { 
                            show: true, 
                            tools: { 
                                download: true, 
                                selection: false, 
                                zoom: false, 
                                zoomin: false, 
                                zoomout: false, 
                                pan: false, 
                                reset: false 
                            } 
                        }, 
                        animations: { 
                            enabled: true, 
                            easing: 'easeinout', 
                            speed: 400 
                        } 
                    },
                    theme: { mode: isDark ? 'dark' : 'light' },
                    grid: { borderColor: getCssVar('--border'), strokeDashArray: 3 },
                    tooltip: { theme: isDark ? 'dark' : 'light', style: { fontFamily: 'Samim' } },
                    legend: { 
                        fontFamily: 'Samim', 
                        fontSize: '12px', 
                        position: window.innerWidth < 768 ? 'top' : 'bottom', 
                        horizontalAlign: 'center' 
                    }
                };
            }

            function getEmptyChartOptions(message, type = 'bar') {
                const baseOptions = getBaseChartOptions();
                return {
                    ...baseOptions, 
                    series: [],
                    chart: { ...baseOptions.chart, type: type },
                    noData: { 
                        text: message, 
                        align: 'center', 
                        verticalAlign: 'middle', 
                        style: { 
                            color: getCssVar('--text-secondary'), 
                            fontSize: '14px', 
                            fontFamily: 'Samim' 
                        } 
                    },
                    labels: []
                };
            }

            function renderChart(elementId, options) {
                const element = document.getElementById(elementId);
                if (!element) { 
                    console.warn(`Chart element with ID '${elementId}' not found.`); 
                    return; 
                }
                if (chartInstances[elementId]) chartInstances[elementId].destroy();
                try {
                    chartInstances[elementId] = new ApexCharts(element, options);
                    chartInstances[elementId].render();
                } catch (error) {
                    console.error(`Error rendering chart ${elementId}:`, error);
                    element.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--error);">خطا در نمایش نمودار</div>';
                }
            }
            
            // --- RENDER FUNCTIONS ---
            function renderStaticSection(data) {
                renderKPIs(data, domRefs.staticKpiContainer, false);
                renderDoughnutChart('staticOverallProgressChart', data);
                renderStackedBarChart('staticProgressByTypeChart', data, 'element_type');
            }

            function updateFilteredSection(data) {
                renderKPIs(data, domRefs.filteredKpiContainer, true);
                renderDoughnutChart('filteredStatusChart', data);
                renderStackedBarChart('filteredTypeChart', data, 'element_type');
                renderStackedBarChart('filteredBlockChart', data, 'block');
                renderStackedBarChart('filteredZoneChart', data, 'zone_name');
                domRefs.resultCountEl.textContent = data.length.toLocaleString('fa');
                sortAndRenderTable(data);
            }

            function renderKPIs(data, container, isFiltered) {
                const kpi = data.reduce((acc, item) => {
                    acc.total++;
                    if (item.final_status === 'تایید شده') acc.ok++;
                    else if (item.final_status === 'آماده بازرسی اولیه') acc.ready++;
                    else if (['رد شده', 'نیاز به تعمیر'].includes(item.final_status)) acc.issues++;
                    return acc;
                }, { total: 0, ok: 0, ready: 0, issues: 0 });
                
                container.innerHTML = `
                    <div class="kpi-card total fade-in">
                        <h3>کل ${isFiltered ? '(فیلتر شده)' : ''}</h3>
                        <p class="value">${kpi.total.toLocaleString('fa')}</p>
                    </div>
                    <div class="kpi-card ok fade-in">
                        <h3>تایید شده</h3>
                        <p class="value">${kpi.ok.toLocaleString('fa')}</p>
                    </div>
                    <div class="kpi-card ready fade-in">
                        <h3>آماده بازرسی</h3>
                        <p class="value">${kpi.ready.toLocaleString('fa')}</p>
                    </div>
                    <div class="kpi-card issues fade-in">
                        <h3>دارای ایراد</h3>
                        <p class="value">${kpi.issues.toLocaleString('fa')}</p>
                    </div>
                `;
            }

            function renderTable(data) {
                domRefs.tableBody.innerHTML = data.length === 0 ?
                    '<tr><td colspan="9" style="text-align:center; padding: 20px;">هیچ رکوردی یافت نشد.</td></tr>' :
                    data.map(row => `
                        <tr>
                            <td>${row.element_id}</td>
                            <td>${row.part_name}</td>
                            <td>${row.element_type}</td>
                            <td>${row.zone_name}</td>
                            <td>${row.block}</td>
                            <td><span class="status-badge" style="background-color:${statusColors[row.final_status] || getCssVar('--secondary')};">${row.final_status}</span></td>
                            <td>${row.inspector}</td>
                            <td>${row.inspection_date}</td>
                            <td>${row.contractor_days_passed}</td>
                        </tr>
                    `).join('');
            }

            function renderDoughnutChart(chartId, data) {
                const counts = data.reduce((acc, item) => { 
                    acc[item.final_status] = (acc[item.final_status] || 0) + 1; 
                    return acc; 
                }, {});
                const series = Object.values(counts);
                const labels = Object.keys(counts);
                
                if (series.length === 0) { 
                    renderChart(chartId, getEmptyChartOptions('داده‌ای برای نمایش وجود ندارد', 'donut')); 
                    return; 
                }
                
                const options = { 
                    ...getBaseChartOptions(), 
                    series, 
                    labels, 
                    chart: { ...getBaseChartOptions().chart, type: 'donut' }, 
                    colors: labels.map(status => statusColors[status]), 
                    plotOptions: { pie: { donut: { size: '65%' } } }, 
                    dataLabels: { enabled: true, formatter: (val) => Math.round(val) + "%" } 
                };
                renderChart(chartId, options);
            }

            function renderStackedBarChart(chartId, data, groupBy) {
                const grouped = data.reduce((acc, item) => {
                    const key = item[groupBy] || 'نامشخص';
                    if (!acc[key]) acc[key] = {};
                    acc[key][item.final_status] = (acc[key][item.final_status] || 0) + 1;
                    return acc;
                }, {});
                
                const labels = Object.keys(grouped).sort();
                if (labels.length === 0) { 
                    renderChart(chartId, getEmptyChartOptions('داده‌ای برای نمایش وجود ندارد', 'bar')); 
                    return; 
                }
                
                const series = Object.keys(statusColors).map(status => ({ 
                    name: status, 
                    data: labels.map(label => grouped[label][status] || 0) 
                })).filter(s => s.data.some(d => d > 0));
                
                const options = { 
                    ...getBaseChartOptions(), 
                    series, 
                    chart: { ...getBaseChartOptions().chart, type: 'bar', stacked: true }, 
                    xaxis: { 
                        categories: labels, 
                        labels: { style: { fontFamily: 'Samim' } } 
                    }, 
                    colors: series.map(s => statusColors[s.name]), 
                    plotOptions: { bar: { horizontal: false, columnWidth: '60%' } }, 
                    dataLabels: { enabled: false } 
                };
                renderChart(chartId, options);
            }

            function renderCoverageCharts(data) {
                const overall = data.overall;
                const percentage = overall.total > 0 ? ((overall.inspected / overall.total) * 100).toFixed(1) : 0;
                domRefs.overallCoverageValue.textContent = `${percentage}%`;
                domRefs.overallCoverageDetails.textContent = `${overall.inspected.toLocaleString('fa')} از ${overall.total.toLocaleString('fa')} المان`;
                renderCoverageBarChart('coverageByZoneChart', data.by_zone);
                renderCoverageBarChart('coverageByBlockChart', data.by_block);
            }

            function renderCoverageBarChart(chartId, data) {
                const labels = Object.keys(data).sort();
                if (labels.length === 0) { 
                    renderChart(chartId, getEmptyChartOptions('داده‌ای برای نمایش وجود ندارد', 'bar')); 
                    return; 
                }
                
                const series = [
                    { name: 'المان‌های کل', data: labels.map(l => data[l].total) }, 
                    { name: 'المان‌های بازرسی شده', data: labels.map(l => data[l].inspected) }
                ];
                
                const options = { 
                    ...getBaseChartOptions(), 
                    series, 
                    chart: { ...getBaseChartOptions().chart, type: 'bar' }, 
                    colors: [getCssVar('--secondary') + '80', getCssVar('--primary')], 
                    xaxis: { categories: labels }, 
                    plotOptions: { bar: { horizontal: false, columnWidth: '55%' } }, 
                    dataLabels: { enabled: false } 
                };
                renderChart(chartId, options);
            }
            
            function renderTrendChart(view) {
                const dataForView = trendData[view] || {};
                const labels = Object.keys(dataForView);
                if (labels.length === 0) { 
                    renderChart('dateTrendChart', getEmptyChartOptions('داده‌ای برای این بازه زمانی وجود ندارد', 'area')); 
                    return; 
                }
                
                const series = Object.keys(trendStatusColors).map(status => ({ 
                    name: status, 
                    data: labels.map(label => dataForView[label]?.[status] || 0) 
                })).filter(s => s.data.some(d => d > 0));
                
                const options = { 
                    ...getBaseChartOptions(), 
                    series, 
                    chart: { ...getBaseChartOptions().chart, type: 'area', stacked: true }, 
                    colors: series.map(s => trendStatusColors[s.name]), 
                    xaxis: { type: 'category', categories: labels }, 
                    stroke: { curve: 'smooth', width: 2 }, 
                    fill: { 
                        type: 'gradient', 
                        gradient: { opacityFrom: 0.6, opacityTo: 0.2 } 
                    }, 
                    dataLabels: { enabled: false } 
                };
                renderChart('dateTrendChart', options);
            }

            function renderStageProgressChart() {
                const zone = domRefs.stageZoneFilter.value;
                const type = domRefs.stageTypeFilter.value;
                
                if (!zone || !type) { 
                    domRefs.stageChartTitle.textContent = 'برای مشاهده نمودار، یک زون و نوع المان انتخاب کنید'; 
                    renderChart('stageProgressChart', getEmptyChartOptions('لطفا از فیلترها انتخاب کنید', 'bar')); 
                    return; 
                }
                
                const dataForChart = stageProgressData[zone]?.[type];
                if (!dataForChart || Object.keys(dataForChart).length === 0) { 
                    domRefs.stageChartTitle.textContent = `داده‌ای برای ${type} در ${zone} یافت نشد`; 
                    renderChart('stageProgressChart', getEmptyChartOptions('داده‌ای یافت نشد', 'bar')); 
                    return; 
                }
                
                domRefs.stageChartTitle.textContent = `پیشرفت مراحل برای ${type} در ${zone}`;
                const labels = Object.keys(dataForChart);
                const series = Object.keys(itemStatusColors).map(status => ({ 
                    name: status, 
                    data: labels.map(stage => dataForChart[stage]?.[status] || 0) 
                })).filter(s => s.data.some(d => d > 0));
                
                const options = { 
                    ...getBaseChartOptions(), 
                    series, 
                    chart: { ...getBaseChartOptions().chart, type: 'bar', stacked: true }, 
                    xaxis: { categories: labels }, 
                    colors: series.map(s => itemStatusColors[s.name]), 
                    plotOptions: { bar: { horizontal: false, columnWidth: '55%' } }, 
                    dataLabels: { enabled: false } 
                };
                renderChart('stageProgressChart', options);
            }
            
            function renderFlexibleReportChart() {
                const block = domRefs.flexibleBlockFilter.value;
                const contractor = domRefs.flexibleContractorFilter.value;
                
                if (!block || !contractor) { 
                    domRefs.flexibleChartTitle.textContent = 'برای مشاهده نمودار، یک بلوک و پیمانکار انتخاب کنید'; 
                    renderChart('flexibleReportChart', getEmptyChartOptions('لطفا از فیلترها انتخاب کنید', 'bar')); 
                    return; 
                }
                
                const dataForChart = flexibleReportData[block]?.[contractor];
                if (!dataForChart || Object.keys(dataForChart).length === 0) { 
                    domRefs.flexibleChartTitle.textContent = `داده‌ای برای پیمانکار ${contractor} در بلوک ${block} یافت نشد`; 
                    renderChart('flexibleReportChart', getEmptyChartOptions('داده‌ای یافت نشد', 'bar')); 
                    return; 
                }
                
                domRefs.flexibleChartTitle.textContent = `وضعیت المان‌ها برای پیمانکار ${contractor} در بلوک ${block}`;
                const labels = Object.keys(dataForChart);
                const series = Object.keys(statusColors).map(status => ({ 
                    name: status, 
                    data: labels.map(type => dataForChart[type]?.[status] || 0) 
                })).filter(s => s.data.some(d => d > 0));
                
                const options = { 
                    ...getBaseChartOptions(), 
                    series, 
                    chart: { ...getBaseChartOptions().chart, type: 'bar', stacked: true }, 
                    xaxis: { categories: labels }, 
                    colors: series.map(s => statusColors[s.name]), 
                    plotOptions: { bar: { horizontal: false, columnWidth: '55%' } }, 
                    dataLabels: { enabled: false } 
                };
                renderChart('flexibleReportChart', options);
            }

            function renderPerformanceCharts(view = 'daily') {
                if (!performanceData || !performanceData.inspectors) return;
                
                ['inspector', 'contractor'].forEach(entity => {
                    const chartId = `${entity}PerformanceChart`;
                    const data = performanceData[`${entity}s`][view] || {};
                    const labels = Object.keys(data).sort();
                    
                    if (labels.length === 0) { 
                        renderChart(chartId, getEmptyChartOptions('داده ای نیست', 'bar')); 
                        return; 
                    }
                    
                    const allEntities = [...new Set(Object.values(data).flatMap(Object.keys))].sort();
                    const series = allEntities.map(name => ({ 
                        name: name, 
                        data: labels.map(label => data[label]?.[name] || 0) 
                    }));
                    
                    const colors = allEntities.map((_, i) => `hsl(${(entity === 'inspector' ? i * 40 : 180 + i * 40) % 360}, 70%, 60%)`);
                    const options = { 
                        ...getBaseChartOptions(), 
                        series, 
                        colors, 
                        chart: { ...getBaseChartOptions().chart, type: 'bar', stacked: true }, 
                        xaxis: { categories: labels }, 
                        plotOptions: { bar: { horizontal: false, columnWidth: '55%' } }, 
                        dataLabels: { enabled: false } 
                    };
                    renderChart(chartId, options);
                });
            }

            // --- SETUP FUNCTIONS ---
            function setupFilters() {
                const elementTypes = [...new Set(allInspectionsData.map(item => item.element_type))].filter(Boolean).sort();
                const statuses = [...new Set(allInspectionsData.map(item => item.final_status))].filter(Boolean).sort();
                elementTypes.forEach(type => domRefs.typeSelect.add(new Option(type, type)));
                statuses.forEach(status => domRefs.statusSelect.add(new Option(status, status)));
            }

            function setupStageFilters() {
                const zones = Object.keys(stageProgressData).sort();
                domRefs.stageZoneFilter.innerHTML = '<option value="">ابتدا یک زون انتخاب کنید</option>';
                zones.forEach(zone => domRefs.stageZoneFilter.add(new Option(zone, zone)));
            }
            
            function setupFlexibleReportFilters() {
                const blocks = Object.keys(flexibleReportData).sort();
                domRefs.flexibleBlockFilter.innerHTML = '<option value="">ابتدا یک بلوک انتخاب کنید</option>';
                blocks.forEach(block => domRefs.flexibleBlockFilter.add(new Option(block, block)));
            }
            
            function applyAllFilters() {
                const search = domRefs.searchInput.value.toLowerCase();
                const type = domRefs.typeSelect.value;
                const status = domRefs.statusSelect.value;
                const startDate = (domRefs.startDateEl.datepicker && domRefs.startDateEl.value) ? new Date(domRefs.startDateEl.datepicker.gDate).getTime() : 0;
                const endDate = (domRefs.endDateEl.datepicker && domRefs.endDateEl.value) ? new Date(domRefs.endDateEl.datepicker.gDate).setHours(23, 59, 59, 999) : Infinity;
                
                currentlyDisplayedData = allInspectionsData.filter(item => {
                    const itemDate = item.inspection_date_raw ? new Date(item.inspection_date_raw).getTime() : 0;
                    const matchesDate = !startDate && !isFinite(endDate) ? true : (itemDate >= startDate && itemDate <= endDate);
                    const matchesType = !type || item.element_type === type;
                    const matchesStatus = !status || item.final_status === status;
                    const matchesSearch = !search || Object.values(item).some(val => String(val).toLowerCase().includes(search));
                    return matchesDate && matchesType && matchesStatus && matchesSearch;
                });
                updateFilteredSection(currentlyDisplayedData);
            }

            function sortAndRenderTable(dataToSort) {
                const { key, dir } = currentSort;
                const direction = dir === 'asc' ? 1 : -1;
                const sortedData = [...dataToSort].sort((a, b) => {
                    let valA = a[key], valB = b[key];
                    if (key === 'inspection_date') { 
                        valA = a.inspection_date_raw ? new Date(a.inspection_date_raw).getTime() : 0; 
                        valB = b.inspection_date_raw ? new Date(b.inspection_date_raw).getTime() : 0; 
                    }
                    if (valA == null || valA === '---' || valA === 'N/A') return 1 * direction;
                    if (valB == null || valB === '---' || valB === 'N/A') return -1 * direction;
                    if (typeof valA === 'string') { 
                        return valA.localeCompare(valB, 'fa') * direction; 
                    }
                    return (valA < valB ? -1 : valA > valB ? 1 : 0) * direction;
                });
                renderTable(sortedData);
            }

            // --- EVENT LISTENERS ---
            function setupEventListeners() {
                domRefs.themeSwitchers.forEach(switcher => {
                    switcher.addEventListener('click', () => {
                        domRefs.htmlEl.classList.toggle('dark');
                        const isDark = domRefs.htmlEl.classList.contains('dark');
                        
                        // Update all theme switcher icons
                        domRefs.themeSwitchers.forEach(sw => {
                            const icon = sw.querySelector('i');
                            icon.className = isDark ? 'fas fa-moon' : 'fas fa-sun';
                        });
                        
                        setTimeout(() => {
                            updateChartColors();
                            // Re-render all charts
                            renderStaticSection(allInspectionsData);
                            renderCoverageCharts(coverageData);
                            renderTrendChart(document.querySelector('.date-view-btn.active').dataset.view);
                            updateFilteredSection(currentlyDisplayedData);
                            renderStageProgressChart();
                            renderFlexibleReportChart();
                            if (performanceData && performanceData.inspectors) {
                                const activePerformanceView = document.querySelector('.performance-view-btn.active');
                                renderPerformanceCharts(activePerformanceView ? activePerformanceView.dataset.view : 'daily');
                            }
                        }, 100);
                    });
                });

                ['input', 'change'].forEach(evt => {
                    domRefs.searchInput.addEventListener(evt, applyAllFilters);
                    domRefs.typeSelect.addEventListener(evt, applyAllFilters);
                    domRefs.statusSelect.addEventListener(evt, applyAllFilters);
                });

                domRefs.clearFiltersBtn.addEventListener('click', () => {
                    domRefs.searchInput.value = '';
                    domRefs.typeSelect.value = '';
                    domRefs.statusSelect.value = '';
                    domRefs.startDateEl.value = '';
                    domRefs.endDateEl.value = '';
                    applyAllFilters();
                });
                
                domRefs.tableHeaders.forEach(header => {
                    header.addEventListener('click', () => {
                        const key = header.dataset.sort;
                        currentSort.dir = (currentSort.key === key && currentSort.dir === 'desc') ? 'asc' : 'desc';
                        currentSort.key = key;
                        domRefs.tableHeaders.forEach(th => th.classList.remove('asc', 'desc'));
                        header.classList.add(currentSort.dir);
                        sortAndRenderTable(currentlyDisplayedData);
                    });
                });

                domRefs.dateViewButtons.forEach(btn => btn.addEventListener('click', (e) => {
                    domRefs.dateViewButtons.forEach(b => b.classList.remove('active'));
                    e.target.classList.add('active');
                    renderTrendChart(e.target.dataset.view);
                }));

                if (domRefs.performanceViewButtons.length > 0) {
                    domRefs.performanceViewButtons.forEach(btn => btn.addEventListener('click', (e) => {
                        domRefs.performanceViewButtons.forEach(b => b.classList.remove('active'));
                        e.target.classList.add('active');
                        renderPerformanceCharts(e.target.dataset.view);
                    }));
                }

                domRefs.stageZoneFilter.addEventListener('change', () => {
                    const selectedZone = domRefs.stageZoneFilter.value;
                    domRefs.stageTypeFilter.innerHTML = '<option value="">-</option>';
                    domRefs.stageTypeFilter.disabled = true;
                    
                    if (selectedZone && stageProgressData[selectedZone]) {
                        const types = Object.keys(stageProgressData[selectedZone]).sort();
                        domRefs.stageTypeFilter.innerHTML = '<option value="">نوع المان را انتخاب کنید</option>';
                        types.forEach(type => domRefs.stageTypeFilter.add(new Option(type, type)));
                        domRefs.stageTypeFilter.disabled = false;
                    }
                    renderStageProgressChart();
                });
                
                domRefs.stageTypeFilter.addEventListener('change', renderStageProgressChart);
                
                domRefs.flexibleBlockFilter.addEventListener('change', () => {
                    const selectedBlock = domRefs.flexibleBlockFilter.value;
                    domRefs.flexibleContractorFilter.innerHTML = '<option value="">-</option>';
                    domRefs.flexibleContractorFilter.disabled = true;
                    
                    if (selectedBlock && flexibleReportData[selectedBlock]) {
                        const contractors = Object.keys(flexibleReportData[selectedBlock]).sort();
                        domRefs.flexibleContractorFilter.innerHTML = '<option value="">پیمانکار را انتخاب کنید</option>';
                        contractors.forEach(c => domRefs.flexibleContractorFilter.add(new Option(c, c)));
                        domRefs.flexibleContractorFilter.disabled = false;
                    }
                    renderFlexibleReportChart();
                });
                
                domRefs.flexibleContractorFilter.addEventListener('change', renderFlexibleReportChart);

                domRefs.reportButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const planFile = domRefs.reportZoneSelect.value;
                        if (!planFile) { 
                            alert('لطفا ابتدا یک فایل نقشه را انتخاب کنید.'); 
                            return; 
                        }
                        const statusToHighlight = this.dataset.status;
                        let url = `/pardis/viewer.php?plan=${encodeURIComponent(planFile)}`;
                        if (statusToHighlight !== 'all') { 
                            url += `&highlight_status=${encodeURIComponent(statusToHighlight)}`; 
                        }
                        window.open(url, '_blank');
                    });
                });

                if (typeof jalaliDatepicker !== 'undefined') {
                    jalaliDatepicker.startWatch({ 
                        selector: '[data-jdp]', 
                        autoHide: true, 
                        onSelect: applyAllFilters 
                    });
                }
            }

            // --- INITIALIZE THE DASHBOARD ---
            console.log("Initializing dashboard with received data...");
            updateChartColors();
            setupFilters();
            setupStageFilters();
            setupFlexibleReportFilters();
            setupEventListeners();
            
            // Initial render of all components
            renderStaticSection(allInspectionsData);
            renderCoverageCharts(coverageData);
            renderTrendChart('daily');
            updateFilteredSection(allInspectionsData);
            renderStageProgressChart();
            renderFlexibleReportChart();
            
            if (performanceData && performanceData.inspectors) {
                renderPerformanceCharts('daily');
            }

            // Add fade-in animation to sections
            setTimeout(() => {
                const sections = document.querySelectorAll('.section-container');
                sections.forEach((section, index) => {
                    setTimeout(() => {
                        section.classList.add('fade-in');
                    }, index * 100);
                });
            }, 200);
        }
function openViewer(allStatuses = false) {
        const planFile = document.getElementById('report-plan-select').value;
        if (!planFile) {
            alert('لطفا ابتدا یک نقشه را انتخاب کنید.');
            return;
        }
        let url = `/pardis/viewer.php?plan=${encodeURIComponent(planFile)}`;
        if (!allStatuses) {
            const status = this.dataset.status;
            url += `&status=${encodeURIComponent(status)}`;
        }
        window.open(url, '_blank');
    }

    document.querySelectorAll('.report-btn').forEach(button => button.addEventListener('click', openViewer.bind(button, false)));
 
    </script>

    <?php require_once 'footer.php'; ?>
</body>
</html>