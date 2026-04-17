<?php
// /public_html/ghom/reports.php (FINAL VERSION)

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

// --- QUERIES FOR INITIAL PAGE RENDER ONLY ---
// These are small, fast queries needed to build the HTML shell before the data loads.
try {
    $pdo = getProjectDBConnection('ghom');
    $pdo->exec("SET NAMES 'utf8mb4'");

    // Get all unique plan files for the dropdown menu
    $all_zones_stmt = $pdo->query("SELECT DISTINCT plan_file FROM elements WHERE plan_file IS NOT NULL AND plan_file != '' ORDER BY plan_file");
    $all_zones = $all_zones_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get counts for the status buttons
    $readyfi = $pdo->query("SELECT COUNT(*) FROM inspections WHERE contractor_status = 'Ready for Inspection'")->fetchColumn();
    $readyno = $pdo->query("SELECT COUNT(*) FROM inspections WHERE overall_status = 'Not OK'")->fetchColumn();
    $readyok = $pdo->query("SELECT COUNT(*) FROM inspections WHERE overall_status = 'OK'")->fetchColumn();
    $readypen = $pdo->query("SELECT COUNT(*) FROM inspections WHERE overall_status = 'Pending'")->fetchColumn();
    $total_inspections = $pdo->query("SELECT COUNT(DISTINCT element_id, part_name) FROM inspections")->fetchColumn();

} catch (Exception $e) {
    // A simple error message is enough here, as the main data loading has its own error handling.
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
    
    <!-- Use a specific, stable version of ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>
    
    <link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    <style>
        /* All of your existing CSS styles go here. */
        @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
                 url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
                 url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
        }
        :root {
            --bg-color: #f8fafc; --text-color: #1e293b; --card-bg: #ffffff;
            --border-color: #e2e8f0; --primary: #3b82f6; --success: #10b981;
            --warning: #f59e0b; --danger: #ef4444; --secondary: #64748b;
            --accent: #8b5cf6; --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --gradient: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
        }
        html.dark {
            --bg-color: #0f172a; --text-color: #f1f5f9; --card-bg: #1e293b;
            --border-color: #334155; --primary: #60a5fa; --success: #34d399;
            --warning: #fbbf24; --danger: #f87171; --secondary: #94a3b8; --accent: #a78bfa;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Samim", -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-color); color: var(--text-color); direction: rtl;
            padding: 20px; line-height: 1.6; min-height: 100vh;
            background-image: radial-gradient(circle at 25% 25%, rgba(59, 130, 246, 0.05) 0%, transparent 50%),
                              radial-gradient(circle at 75% 75%, rgba(139, 92, 246, 0.05) 0%, transparent 50%);
        }
        .dashboard-grid { max-width: 1900px; margin: 0 auto; display: grid; gap: 30px; }
        .section-container {
            background: var(--card-bg); border-radius: 16px; box-shadow: var(--shadow);
            padding: 30px; border: 1px solid var(--border-color); transition: all 0.3s ease;
            position: relative; overflow: hidden;
        }
        .section-container:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .section-container::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0;
            height: 4px; background: var(--gradient);
        }
        .section-header {
            display: flex; justify-content: space-between; align-items: center;
            padding-bottom: 20px; margin-bottom: 30px; border-bottom: 1px solid var(--border-color);
        }
        .section-header h1, .section-header h2 { font-size: 1.8em; font-weight: 700; color: var(--text-color); }
        .theme-switcher {
            padding: 10px; cursor: pointer; border-radius: 12px; border: none;
            background: var(--bg-color); color: var(--text-color); font-size: 1.4em; transition: all 0.3s ease;
        }
        .theme-switcher:hover { transform: scale(1.1); }
        .kpi-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; }
        .kpi-card {
            background: var(--bg-color); padding: 25px; border-radius: 12px;
            border-left: 5px solid var(--primary); transition: all 0.3s ease; text-align: center;
        }
        .kpi-card:hover { transform: translateY(-4px); box-shadow: var(--shadow); }
        .kpi-card h3 { margin: 0 0 10px; font-size: 1em; font-weight: 600; color: var(--secondary); }
        .kpi-card .value { font-size: 2.5em; font-weight: 700; color: var(--text-color); }
        .kpi-card .details { font-size: 0.9em; color: var(--secondary); margin-top: 5px; }
        .kpi-card.ok { border-color: var(--success); }
        .kpi-card.ready { border-color: var(--warning); }
        .kpi-card.issues { border-color: var(--danger); }
        .charts-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 30px; margin-top: 30px; }
        .chart-wrapper {
            background: var(--bg-color); padding: 25px; border-radius: 12px; height: 450px;
            display: flex; flex-direction: column; /* This is important for chart height */
        }
        .chart-wrapper > h3 { text-align: center; margin: 0 0 20px; font-size: 1.1em; font-weight: 600; flex-shrink: 0; }
        /* This rule makes the chart div fill the available space */
        .chart-wrapper > div[id] { flex-grow: 1; min-height: 0; }
        .date-trend-controls { text-align: center; margin-bottom: 20px; }
        .date-trend-controls button {
            padding: 8px 16px; margin: 0 5px; cursor: pointer; border-radius: 8px;
            border: 1px solid var(--border-color); background: var(--card-bg);
            color: var(--text-color); font-weight: 600; transition: all 0.2s ease;
        }
        .date-trend-controls button.active { background: var(--primary); color: white; border-color: var(--primary); }
        .filter-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 20px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { margin-bottom: 8px; font-size: 0.9em; font-weight: 600; }
        .filter-group input, .filter-group select {
            padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);
            background-color: var(--bg-color); color: var(--text-color); font-family: inherit;
        }
        .filter-group .btn {
            padding: 10px; border-radius: 8px; border: none; cursor: pointer;
            color: white; font-weight: bold; height: 40px; transition: background-color 0.2s;
        }
        .btn-secondary { background-color: var(--secondary); }
        .btn-secondary:hover { background-color: #7c8a9e; }
        .table-container { margin-top: 30px; }
        .table-container h3 { margin-bottom: 15px; }
        .table-wrapper { max-height: 600px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: right; border-bottom: 1px solid var(--border-color); }
        thead th { background-color: var(--card-bg); position: sticky; top: 0; z-index: 1; cursor: pointer; user-select: none; }
        thead th.sort.asc::after, thead th.sort.desc::after { font-size: 0.8em; }
        thead th.sort.asc::after { content: " ▲"; }
        thead th.sort.desc::after { content: " ▼"; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background-color: rgba(120, 120, 120, 0.05); }
        .status-badge { padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 0.85em; color: white; }
        .scrollable-chart-container {
            position: relative; height: calc(100% - 60px); width: 100%;
            overflow-x: auto; -webkit-overflow-scrolling: touch;
        }
        #loading-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.8); z-index: 9999;
            display: flex; justify-content: center; align-items: center;
            font-size: 1.5em; font-weight: bold; color: #333;
            transition: opacity 0.3s ease;
        }
        html.dark #loading-overlay { background: rgba(15, 23, 42, 0.8); color: #f1f5f9; }
    </style>
</head>

<body>
    <!-- Loading Indicator -->
    <div id="loading-overlay">
        <div>در حال بارگذاری داده‌های گزارش...</div>
    </div>

    <div class="dashboard-grid" style="visibility: hidden;"> <!-- Hide dashboard until data is loaded -->
        <!-- SECTION: PLAN VIEWER -->
        <div class="section-container">
            <h2>مشاهده وضعیت کلی در نقشه</h2>
            <div class="filter-bar">
                <div class="filter-group">
                    <label for="report-zone-select">۱. انتخاب فایل نقشه:</label>
                    <select id="report-zone-select">
                        <?php foreach ($all_zones as $zone): ?>
                        <option value="<?php echo htmlspecialchars($zone, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($zone, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>۲. مشاهده همه المان‌ها با وضعیت:</label>
                    <div>
                        <button type="button" class="btn report-btn" data-status="Ready for Inspection" style="background-color:rgb(178, 220, 53);">آماده بازرسی (<?php echo number_format($readyfi); ?>)</button>
                        <button type="button" class="btn report-btn" data-status="Not OK" style="background-color: #dc3545;">رد شده (<?php echo number_format($readyno); ?>)</button>
                        <button type="button" class="btn report-btn" data-status="OK" style="background-color: #28a745;">تایید شده (<?php echo number_format($readyok); ?>)</button>
                        <button type="button" class="btn report-btn" data-status="Pending" style="background-color:rgb(85, 40, 167);">قطعی نشده (<?php echo number_format($readypen); ?>)</button>
                        <button type="button" class="btn report-btn" data-status="all" style="background-color: #17a2b8;">همه وضعیت‌ها (<?php echo number_format($total_inspections); ?>)</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 1: STATIC OVERALL REPORT -->
        <div class="section-container">
            <div class="section-header">
                <h1>گزارش کلی پروژه</h1>
                <button class="theme-switcher">🌓</button>
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
            <div class="section-header"><h2>پوشش بازرسی</h2></div>
            <div class="kpi-container" style="grid-template-columns: 1fr; max-width: 400px; margin: 0 auto 30px auto;">
                <div class="kpi-card" id="overall-coverage-kpi">
                    <h3>پوشش کلی بازرسی</h3>
                    <p class="value" id="overall-coverage-value">0%</p>
                    <div class="details" id="overall-coverage-details">0 از 0 المان</div>
                </div>
            </div>
            <div class="charts-container">
                <div class="chart-wrapper">
                    <h3>پوشش بازرسی به تفکیک زون</h3><div id="coverageByZoneChart"></div>
                </div>
                <div class="chart-wrapper">
                    <h3>پوشش بازرسی به تفکیک بلوک</h3><div id="coverageByBlockChart"></div>
                </div>
            </div>
        </div>

        <!-- SECTION 3: STAGE PROGRESS REPORT -->
        <div class="section-container">
            <div class="section-header"><h2>گزارش پیشرفت مراحل</h2></div>
            <div class="filter-bar" style="gap: 20px 30px;">
                <div class="filter-group"><label for="stage-filter-zone">انتخاب زون:</label><select id="stage-filter-zone"></select></div>
                <div class="filter-group"><label for="stage-filter-type">انتخاب نوع المان:</label><select id="stage-filter-type" disabled></select></div>
            </div>
            <div class="chart-wrapper" style="height: 500px; width: 100%; margin-top: 30px;">
                <h3 id="stage-chart-title">برای مشاهده نمودار، یک زون و نوع المان انتخاب کنید</h3>
                <div id="stageProgressChart"></div>
            </div>
        </div>

        <!-- SECTION 4: FLEXIBLE BLOCK/CONTRACTOR REPORT -->
        <div class="section-container">
            <div class="section-header"><h2>گزارش جامع پیمانکاران و بلوک‌ها</h2></div>
            <div class="filter-bar" style="gap: 20px 30px;">
                <div class="filter-group"><label for="flexible-filter-block">انتخاب بلوک:</label><select id="flexible-filter-block"></select></div>
                <div class="filter-group"><label for="flexible-filter-contractor">انتخاب پیمانکار:</label><select id="flexible-filter-contractor" disabled></select></div>
            </div>
            <div class="chart-wrapper" style="height: 500px; width: 100%; margin-top: 30px;">
                <h3 id="flexible-chart-title">برای مشاهده نمودار، یک بلوک و پیمانکار انتخاب کنید</h3>
                <div id="flexibleReportChart"></div>
            </div>
        </div>

        <!-- SECTION 5: DATE TRENDS -->
        <div class="section-container">
            <div class="section-header"><h2>روند بازرسی‌ها در طول زمان</h2></div>
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
            <div class="section-header"><h2>گزارش عملکرد</h2></div>
            <div class="chart-wrapper" style="height: 500px; width: 100%;">
                <div class="date-trend-controls">
                    <button class="performance-view-btn active" data-view="daily">روزانه</button>
                    <button class="performance-view-btn" data-view="weekly">هفتگی</button>
                    <button class="performance-view-btn" data-view="monthly">ماهانه</button>
                </div>
                <div class="charts-container">
                    <div class="chart-wrapper" style="padding: 15px;">
                        <h3>عملکرد بازرسان</h3>
                        <div class="scrollable-chart-container"><div id="inspectorPerformanceChart"></div></div>
                    </div>
                    <div class="chart-wrapper" style="padding: 15px;">
                        <h3>عملکرد پیمانکاران</h3>
                        <div class="scrollable-chart-container"><div id="contractorPerformanceChart"></div></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SECTION 7: DYNAMIC & FILTERABLE REPORT -->
        <div class="section-container">
            <div class="section-header"><h2>گزارشات پویا و فیلترها</h2></div>
            <div class="filter-bar">
                <div class="filter-group"><label for="filter-search">جستجوی کلی:</label><input type="text" id="filter-search" placeholder="کد، نوع، زون..."></div>
                <div class="filter-group"><label for="filter-type">نوع المان:</label><select id="filter-type"><option value="">همه</option></select></div>
                <div class="filter-group"><label for="filter-status">وضعیت:</label><select id="filter-status"><option value="">همه</option></select></div>
                <div class="filter-group"><label for="filter-date-start">تاریخ از:</label><input type="text" id="filter-date-start" data-jdp></div>
                <div class="filter-group"><label for="filter-date-end">تاریخ تا:</label><input type="text" id="filter-date-end" data-jdp></div>
                <div class="filter-group"><button id="clear-filters-btn" class="btn btn-secondary">پاک کردن</button></div>
            </div>
            <hr style="border:none; border-top: 1px solid var(--border-color); margin: 30px 0;">
            <div class="kpi-container" id="filtered-kpi-container"></div>
            <div class="charts-container">
                <div class="chart-wrapper"><h3>خلاصه وضعیت (فیلتر شده)</h3><div id="filteredStatusChart"></div></div>
                <div class="chart-wrapper"><h3>تفکیک نوع (فیلتر شده)</h3><div id="filteredTypeChart"></div></div>
                <div class="chart-wrapper"><h3>تفکیک بلوک (فیلتر شده)</h3><div id="filteredBlockChart"></div></div>
                <div class="chart-wrapper"><h3>تفکیک زون (فیلتر شده)</h3><div id="filteredZoneChart"></div></div>
            </div>
            <div class="table-container">
                <h3>نتایج (<span id="table-result-count">0</span> رکورد)</h3>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th class="sort" data-sort="element_id">کد</th>
                                <th class="sort" data-sort="part_name">بخش</th>
                                <th class="sort" data-sort="element_type">نوع</th>
                                <th class="sort" data-sort="zone_name">زون</th>
                                <th class="sort" data-sort="block">بلوک</th>
                                <th class="sort" data-sort="final_status">وضعیت</th>
                                <th class="sort" data-sort="inspector">بازرس</th>
                                <th class="sort" data-sort="inspection_date">تاریخ بازرسی</th>
                                <th class="sort" data-sort="contractor_days_passed">فاصله از تاریخ پیمانکار</th>
                            </tr>
                        </thead>
                        <tbody id="dynamic-table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="/ghom/assets/js/jalalidatepicker.min.js"></script>
    <script>
        // Main function to fetch data and then initialize the dashboard
        async function loadDashboard() {
            const loadingOverlay = document.getElementById('loading-overlay');
            const dashboardGrid = document.querySelector('.dashboard-grid');
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
                loadingOverlay.innerHTML = `<div style="padding: 20px; text-align: center; color: #ef4444;">Failed to load report data.<br>Please check the console (F12) and try again later.</div>`;
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
                htmlEl: document.documentElement, themeSwitcher: document.querySelector('.theme-switcher'),
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
            function getCssVar(varName) { return getComputedStyle(document.documentElement).getPropertyValue(varName).trim(); }
            
            function updateChartColors() {
                statusColors = { 'در انتظار': getCssVar('--secondary'), 'آماده بازرسی اولیه': getCssVar('--primary'), 'منتظر بازرسی مجدد': getCssVar('--accent'), 'نیاز به تعمیر': getCssVar('--warning'), 'تایید شده': getCssVar('--success'), 'رد شده': getCssVar('--danger') };
                trendStatusColors = { 'Pending': getCssVar('--secondary'), 'Pre-Inspection Complete': getCssVar('--primary'), 'Awaiting Re-inspection': getCssVar('--accent'), 'Repair': getCssVar('--warning'), 'OK': getCssVar('--success'), 'Reject': getCssVar('--danger') };
                itemStatusColors = { 'OK': getCssVar('--success'), 'Not OK': getCssVar('--danger'), 'N/A': getCssVar('--secondary') };
            }

            function getBaseChartOptions() {
                const isDark = domRefs.htmlEl.classList.contains('dark');
                return {
                    chart: { fontFamily: 'Samim', background: 'transparent', foreColor: getCssVar('--text-color'), toolbar: { show: true, tools: { download: true, selection: false, zoom: false, zoomin: false, zoomout: false, pan: false, reset: false } }, animations: { enabled: true, easing: 'easeinout', speed: 400 } },
                    theme: { mode: isDark ? 'dark' : 'light' },
                    grid: { borderColor: getCssVar('--border-color'), strokeDashArray: 3 },
                    tooltip: { theme: isDark ? 'dark' : 'light', style: { fontFamily: 'Samim' } },
                    legend: { fontFamily: 'Samim', fontSize: '12px', position: window.innerWidth < 768 ? 'top' : 'bottom', horizontalAlign: 'center' }
                };
            }

            function getEmptyChartOptions(message, type = 'bar') {
                const baseOptions = getBaseChartOptions();
                return {
                    ...baseOptions, series: [],
                    chart: { ...baseOptions.chart, type: type },
                    noData: { text: message, align: 'center', verticalAlign: 'middle', style: { color: getCssVar('--secondary'), fontSize: '14px', fontFamily: 'Samim' } },
                    labels: []
                };
            }

            function renderChart(elementId, options) {
                const element = document.getElementById(elementId);
                if (!element) { console.warn(`Chart element with ID '${elementId}' not found.`); return; }
                if (chartInstances[elementId]) chartInstances[elementId].destroy();
                try {
                    chartInstances[elementId] = new ApexCharts(element, options);
                    chartInstances[elementId].render();
                } catch (error) {
                    console.error(`Error rendering chart ${elementId}:`, error);
                    element.innerHTML = '<div style="text-align: center; padding: 20px; color: #f44336;">Error</div>';
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
                    <div class="kpi-card"><h3>کل ${isFiltered ? '(فیلتر شده)' : ''}</h3><p class="value">${kpi.total.toLocaleString('fa')}</p></div>
                    <div class="kpi-card ok"><h3>تایید شده</h3><p class="value">${kpi.ok.toLocaleString('fa')}</p></div>
                    <div class="kpi-card ready"><h3>آماده بازرسی</h3><p class="value">${kpi.ready.toLocaleString('fa')}</p></div>
                    <div class="kpi-card issues"><h3>دارای ایراد</h3><p class="value">${kpi.issues.toLocaleString('fa')}</p></div>`;
            }

            function renderTable(data) {
                domRefs.tableBody.innerHTML = data.length === 0 ?
                    '<tr><td colspan="9" style="text-align:center; padding: 20px;">هیچ رکوردی یافت نشد.</td></tr>' :
                    data.map(row => `
                        <tr>
                            <td>${row.element_id}</td><td>${row.part_name}</td><td>${row.element_type}</td>
                            <td>${row.zone_name}</td><td>${row.block}</td>
                            <td><span class="status-badge" style="background-color:${statusColors[row.final_status] || getCssVar('--secondary')};">${row.final_status}</span></td>
                            <td>${row.inspector}</td><td>${row.inspection_date}</td><td>${row.contractor_days_passed}</td>
                        </tr>`).join('');
            }

            function renderDoughnutChart(chartId, data) {
                const counts = data.reduce((acc, item) => { acc[item.final_status] = (acc[item.final_status] || 0) + 1; return acc; }, {});
                const series = Object.values(counts);
                const labels = Object.keys(counts);
                if (series.length === 0) { renderChart(chartId, getEmptyChartOptions('داده‌ای برای نمایش وجود ندارد', 'donut')); return; }
                const options = { ...getBaseChartOptions(), series, labels, chart: { ...getBaseChartOptions().chart, type: 'donut' }, colors: labels.map(status => statusColors[status]), plotOptions: { pie: { donut: { size: '65%' } } }, dataLabels: { enabled: true, formatter: (val) => Math.round(val) + "%" } };
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
                if (labels.length === 0) { renderChart(chartId, getEmptyChartOptions('داده‌ای برای نمایش وجود ندارد', 'bar')); return; }
                const series = Object.keys(statusColors).map(status => ({ name: status, data: labels.map(label => grouped[label][status] || 0) })).filter(s => s.data.some(d => d > 0));
                const options = { ...getBaseChartOptions(), series, chart: { ...getBaseChartOptions().chart, type: 'bar', stacked: true }, xaxis: { categories: labels, labels: { style: { fontFamily: 'Samim' } } }, colors: series.map(s => statusColors[s.name]), plotOptions: { bar: { horizontal: false, columnWidth: '60%' } }, dataLabels: { enabled: false } };
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
                if (labels.length === 0) { renderChart(chartId, getEmptyChartOptions('داده‌ای برای نمایش وجود ندارد', 'bar')); return; }
                const series = [{ name: 'المان‌های کل', data: labels.map(l => data[l].total) }, { name: 'المان‌های بازرسی شده', data: labels.map(l => data[l].inspected) }];
                const options = { ...getBaseChartOptions(), series, chart: { ...getBaseChartOptions().chart, type: 'bar' }, colors: [getCssVar('--secondary') + '80', getCssVar('--primary')], xaxis: { categories: labels }, plotOptions: { bar: { horizontal: false, columnWidth: '55%' } }, dataLabels: { enabled: false } };
                renderChart(chartId, options);
            }
            
            function renderTrendChart(view) {
                const dataForView = trendData[view] || {};
                const labels = Object.keys(dataForView);
                if (labels.length === 0) { renderChart('dateTrendChart', getEmptyChartOptions('داده‌ای برای این بازه زمانی وجود ندارد', 'area')); return; }
                const series = Object.keys(trendStatusColors).map(status => ({ name: status, data: labels.map(label => dataForView[label]?.[status] || 0) })).filter(s => s.data.some(d => d > 0));
                const options = { ...getBaseChartOptions(), series, chart: { ...getBaseChartOptions().chart, type: 'area', stacked: true }, colors: series.map(s => trendStatusColors[s.name]), xaxis: { type: 'category', categories: labels }, stroke: { curve: 'smooth', width: 2 }, fill: { type: 'gradient', gradient: { opacityFrom: 0.6, opacityTo: 0.2 } }, dataLabels: { enabled: false } };
                renderChart('dateTrendChart', options);
            }

            function renderStageProgressChart() {
                const zone = domRefs.stageZoneFilter.value;
                const type = domRefs.stageTypeFilter.value;
                if (!zone || !type) { domRefs.stageChartTitle.textContent = 'برای مشاهده نمودار، یک زون و نوع المان انتخاب کنید'; renderChart('stageProgressChart', getEmptyChartOptions('لطفا از فیلترها انتخاب کنید', 'bar')); return; }
                const dataForChart = stageProgressData[zone]?.[type];
                if (!dataForChart || Object.keys(dataForChart).length === 0) { domRefs.stageChartTitle.textContent = `داده‌ای برای ${type} در ${zone} یافت نشد`; renderChart('stageProgressChart', getEmptyChartOptions('داده‌ای یافت نشد', 'bar')); return; }
                domRefs.stageChartTitle.textContent = `پیشرفت مراحل برای ${type} در ${zone}`;
                const labels = Object.keys(dataForChart);
                const series = Object.keys(itemStatusColors).map(status => ({ name: status, data: labels.map(stage => dataForChart[stage]?.[status] || 0) })).filter(s => s.data.some(d => d > 0));
                const options = { ...getBaseChartOptions(), series, chart: { ...getBaseChartOptions().chart, type: 'bar', stacked: true }, xaxis: { categories: labels }, colors: series.map(s => itemStatusColors[s.name]), plotOptions: { bar: { horizontal: false, columnWidth: '55%' } }, dataLabels: { enabled: false } };
                renderChart('stageProgressChart', options);
            }
            
            function renderFlexibleReportChart() {
                const block = domRefs.flexibleBlockFilter.value;
                const contractor = domRefs.flexibleContractorFilter.value;
                if (!block || !contractor) { domRefs.flexibleChartTitle.textContent = 'برای مشاهده نمودار، یک بلوک و پیمانکار انتخاب کنید'; renderChart('flexibleReportChart', getEmptyChartOptions('لطفا از فیلترها انتخاب کنید', 'bar')); return; }
                const dataForChart = flexibleReportData[block]?.[contractor];
                if (!dataForChart || Object.keys(dataForChart).length === 0) { domRefs.flexibleChartTitle.textContent = `داده‌ای برای پیمانکار ${contractor} در بلوک ${block} یافت نشد`; renderChart('flexibleReportChart', getEmptyChartOptions('داده‌ای یافت نشد', 'bar')); return; }
                domRefs.flexibleChartTitle.textContent = `وضعیت المان‌ها برای پیمانکار ${contractor} در بلوک ${block}`;
                const labels = Object.keys(dataForChart);
                const series = Object.keys(statusColors).map(status => ({ name: status, data: labels.map(type => dataForChart[type]?.[status] || 0) })).filter(s => s.data.some(d => d > 0));
                const options = { ...getBaseChartOptions(), series, chart: { ...getBaseChartOptions().chart, type: 'bar', stacked: true }, xaxis: { categories: labels }, colors: series.map(s => statusColors[s.name]), plotOptions: { bar: { horizontal: false, columnWidth: '55%' } }, dataLabels: { enabled: false } };
                renderChart('flexibleReportChart', options);
            }

            function renderPerformanceCharts(view = 'daily') {
                if (!performanceData || !performanceData.inspectors) return;
                ['inspector', 'contractor'].forEach(entity => {
                    const chartId = `${entity}PerformanceChart`;
                    const data = performanceData[`${entity}s`][view] || {};
                    const labels = Object.keys(data).sort();
                    if (labels.length === 0) { renderChart(chartId, getEmptyChartOptions('داده ای نیست', 'bar')); return; }
                    const allEntities = [...new Set(Object.values(data).flatMap(Object.keys))].sort();
                    const series = allEntities.map(name => ({ name: name, data: labels.map(label => data[label]?.[name] || 0) }));
                    const colors = allEntities.map((_, i) => `hsl(${(entity === 'inspector' ? i * 40 : 180 + i * 40) % 360}, 70%, 60%)`);
                    const options = { ...getBaseChartOptions(), series, colors, chart: { ...getBaseChartOptions().chart, type: 'bar', stacked: true }, xaxis: { categories: labels }, plotOptions: { bar: { horizontal: false, columnWidth: '55%' } }, dataLabels: { enabled: false } };
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
                    if (key === 'inspection_date') { valA = a.inspection_date_raw ? new Date(a.inspection_date_raw).getTime() : 0; valB = b.inspection_date_raw ? new Date(b.inspection_date_raw).getTime() : 0; }
                    if (valA == null || valA === '---' || valA === 'N/A') return 1 * direction;
                    if (valB == null || valB === '---' || valB === 'N/A') return -1 * direction;
                    if (typeof valA === 'string') { return valA.localeCompare(valB, 'fa') * direction; }
                    return (valA < valB ? -1 : valA > valB ? 1 : 0) * direction;
                });
                renderTable(sortedData);
            }

            // --- EVENT LISTENERS ---
            function setupEventListeners() {
                domRefs.themeSwitcher.addEventListener('click', () => {
                    domRefs.htmlEl.classList.toggle('dark');
                    setTimeout(() => {
                        updateChartColors();
                        // Re-render all charts
                        renderStaticSection(allInspectionsData);
                        renderCoverageCharts(coverageData);
                        renderTrendChart(document.querySelector('.date-view-btn.active').dataset.view);
                        updateFilteredSection(currentlyDisplayedData);
                        renderStageProgressChart();
                        renderFlexibleReportChart();
                        if (performanceData && performanceData.inspectors) renderPerformanceCharts(document.querySelector('.performance-view-btn.active').dataset.view);
                    }, 100);
                });

                ['input', 'change'].forEach(evt => {
                    domRefs.searchInput.addEventListener(evt, applyAllFilters);
                    domRefs.typeSelect.addEventListener(evt, applyAllFilters);
                    domRefs.statusSelect.addEventListener(evt, applyAllFilters);
                });

                domRefs.clearFiltersBtn.addEventListener('click', () => {
                    domRefs.searchInput.value = ''; domRefs.typeSelect.value = ''; domRefs.statusSelect.value = '';
                    domRefs.startDateEl.value = ''; domRefs.endDateEl.value = '';
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

                if (domRefs.performanceViewButtons) {
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
                        if (!planFile) { alert('لطفا ابتدا یک فایل نقشه را انتخاب کنید.'); return; }
                        const statusToHighlight = this.dataset.status;
                        let url = `/ghom/viewer.php?plan=${encodeURIComponent(planFile)}`;
                        if (statusToHighlight !== 'all') { url += `&highlight_status=${encodeURIComponent(statusToHighlight)}`; }
                        window.open(url, '_blank');
                    });
                });

                if (typeof jalaliDatepicker !== 'undefined') {
                    jalaliDatepicker.startWatch({ selector: '[data-jdp]', autoHide: true, onSelect: applyAllFilters });
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
        }

        // Start the entire process when the DOM is ready
        document.addEventListener('DOMContentLoaded', loadDashboard);
    </script>

    <?php require_once 'footer.php'; ?>
</body>
</html>
