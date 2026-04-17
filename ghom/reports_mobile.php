<?php
// /public_html/ghom/reports_mobile.php

// --- BOOTSTRAP & SESSION (Identical to Desktop) ---
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
$pageTitle = "گزارشات پروژه قم (موبایل)";
// Use the same header file
require_once __DIR__ . '/header_ghom_mobile.php';

// --- INITIAL DATA QUERIES (Identical to Desktop) ---
try {
    $pdo = getProjectDBConnection('ghom');
    $pdo->exec("SET NAMES 'utf8mb4'");
    $all_zones_stmt = $pdo->query("SELECT DISTINCT plan_file FROM elements WHERE plan_file IS NOT NULL AND plan_file != '' ORDER BY plan_file");
    $all_zones = $all_zones_stmt->fetchAll(PDO::FETCH_COLUMN);
    $readyfi = $pdo->query("SELECT COUNT(*) FROM inspections WHERE contractor_status = 'Ready for Inspection'")->fetchColumn();
    $readyno = $pdo->query("SELECT COUNT(*) FROM inspections WHERE overall_status = 'Not OK'")->fetchColumn();
    $readyok = $pdo->query("SELECT COUNT(*) FROM inspections WHERE overall_status = 'OK'")->fetchColumn();
    $readypen = $pdo->query("SELECT COUNT(*) FROM inspections WHERE overall_status = 'Pending'")->fetchColumn();
    $total_inspections = $pdo->query("SELECT COUNT(DISTINCT element_id, part_name) FROM inspections")->fetchColumn();
} catch (Exception $e) {
    error_log("Initial DB Error in reports_mobile.php: " . $e->getMessage());
    die("خطا در بارگذاری اولیه صفحه. لطفا با پشتیبانی تماس بگیرید.");
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    
    <!-- Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
         @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
                 url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
                 url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
        }

        /* --- MOBILE FIRST STYLES --- */
        :root {
            --primary: #1e40af;
            --primary-light: #3b82f6;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --background: #f1f5f9;
            --surface: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        html.dark {
            --background: #0f172a;
            --surface: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border: #334155;
        }
        body {
            font-family: "Samim", sans-serif;
            background: var(--background);
            color: var(--text-primary);
            margin: 0;
        }
        .dashboard-container {
            padding: 1rem;
        }
        .section-container {
            background: var(--surface);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-top: 4px solid var(--primary);
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .section-icon {
            color: var(--primary);
        }
        .filter-bar, .status-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .filter-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            background-color: var(--background);
            font-size: 1rem;
        }
        .btn {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            color: white;
        }
        .kpi-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .kpi-card {
            background: var(--background);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            border: 1px solid var(--border);
        }
        .kpi-card h3 {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        .kpi-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .chart-wrapper {
            height: 350px;
            margin-top: 1.5rem;
        }
        .table-wrapper {
            overflow-x: auto; /* Enable horizontal scrolling */
            -webkit-overflow-scrolling: touch;
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap; /* Prevent text wrapping */
        }
        th, td {
            padding: 0.75rem;
            text-align: right;
            border-bottom: 1px solid var(--border);
        }
        thead th {
            background: var(--background);
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        .status-badge {
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.75rem;
            color: white;
        }
        #loading-overlay {
             position: fixed; top: 0; left: 0; width: 100%; height: 100%;
             background: rgba(255, 255, 255, 0.9); z-index: 9999;
             display: flex; justify-content: center; align-items: center;
        }
        html.dark #loading-overlay { background: rgba(15, 23, 42, 0.9); }
        .loading-spinner {
            width: 50px; height: 50px; border: 4px solid var(--border);
            border-top: 4px solid var(--primary); border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div id="loading-overlay"><div class="loading-spinner"></div></div>

    <div class="dashboard-container" style="visibility: hidden;">
        <!-- Header Section -->
        <div class="section-container">
            <h1 class="section-title">
                <i class="fas fa-chart-line section-icon"></i>
                گزارشات پروژه
            </h1>
            <p>خلاصه وضعیت کلی پروژه و گزارشات پویا.</p>
        </div>

        <!-- Plan Viewer Section -->
        <div class="section-container">
             <h2 class="section-title"><i class="fas fa-map section-icon"></i>مشاهده در نقشه</h2>
             <div class="filter-bar">
                <div class="filter-group">
                    <label for="report-zone-select">انتخاب فایل نقشه:</label>
                    <select id="report-zone-select">
                        <?php foreach ($all_zones as $zone): ?>
                        <option value="<?php echo htmlspecialchars($zone, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($zone, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
             </div>
             <div class="status-buttons">
                <button type="button" class="btn report-btn" data-status="Ready for Inspection" style="background-color: var(--warning);"><i class="fas fa-clock"></i> آماده بازرسی (<?php echo number_format($readyfi); ?>)</button>
                <button type="button" class="btn report-btn" data-status="Not OK" style="background-color: var(--error);"><i class="fas fa-times"></i> رد شده (<?php echo number_format($readyno); ?>)</button>
                <button type="button" class="btn report-btn" data-status="OK" style="background-color: var(--success);"><i class="fas fa-check"></i> تایید شده (<?php echo number_format($readyok); ?>)</button>
                <button type="button" class="btn report-btn" data-status="Pending" style="background-color: var(--secondary);"><i class="fas fa-hourglass-half"></i> قطعی نشده (<?php echo number_format($readypen); ?>)</button>
                <button type="button" class="btn report-btn" data-status="all" style="background-color: var(--primary);"><i class="fas fa-list"></i> همه (<?php echo number_format($total_inspections); ?>)</button>
             </div>
        </div>

        <!-- Static Overall Report Section -->
        <div class="section-container">
            <h2 class="section-title"><i class="fas fa-chart-pie section-icon"></i>گزارش کلی پروژه</h2>
            <div class="kpi-container" id="static-kpi-container"></div>
            <div class="chart-wrapper">
                <div id="staticOverallProgressChart"></div>
            </div>
        </div>

        <!-- Dynamic Filterable Report Section -->
        <div class="section-container">
            <h2 class="section-title"><i class="fas fa-filter section-icon"></i>گزارشات پویا</h2>
            <div class="filter-bar">
                <div class="filter-group"><label for="filter-search">جستجو:</label><input type="text" id="filter-search" placeholder="کد، نوع، زون..."></div>
                <div class="filter-group"><label for="filter-type">نوع المان:</label><select id="filter-type"><option value="">همه</option></select></div>
                <div class="filter-group"><label for="filter-status">وضعیت:</label><select id="filter-status"><option value="">همه</option></select></div>
                <!-- Date filters can be added here if needed -->
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>کد</th><th>بخش</th><th>نوع</th><th>زون</th><th>بلوک</th><th>وضعیت</th><th>بازرس</th><th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody id="dynamic-table-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Main function to fetch data and then initialize the dashboard
        async function loadDashboard() {
            const loadingOverlay = document.getElementById('loading-overlay');
            const dashboardGrid = document.querySelector('.dashboard-container');
            
            try {
                const response = await fetch('get_chart_data.php');
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Server responded with status ${response.status}: ${errorText}`);
                }
                const chartData = await response.json();

                loadingOverlay.style.opacity = '0';
                setTimeout(() => loadingOverlay.style.display = 'none', 300);
                dashboardGrid.style.visibility = 'visible';
                
                initializeDashboard(chartData);

            } catch (error) {
                console.error("Failed to load or parse chart data:", error);
                loadingOverlay.innerHTML = `
                    <div style="text-align: center; color: var(--error);">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <div style="font-size: 1.25rem; font-weight: 600;">خطا در بارگذاری داده‌ها</div>
                        <div style="margin-top: 0.5rem;">لطفا صفحه را مجدداً بارگذاری کنید</div>
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

            const { allInspectionsData } = chartData;
            let currentlyDisplayedData = [...allInspectionsData];
            const chartInstances = {};
            let statusColors = {};

            const domRefs = {
                htmlEl: document.documentElement,
                staticKpiContainer: document.getElementById('static-kpi-container'),
                searchInput: document.getElementById('filter-search'),
                typeSelect: document.getElementById('filter-type'),
                statusSelect: document.getElementById('filter-status'),
                tableBody: document.getElementById('dynamic-table-body'),
                reportZoneSelect: document.getElementById('report-zone-select'),
                reportButtons: document.querySelectorAll('.report-btn'),
            };

            // --- HELPER FUNCTIONS ---
            function getCssVar(varName) { return getComputedStyle(document.documentElement).getPropertyValue(varName).trim(); }
            
            function updateChartColors() {
                statusColors = { 
                    'در انتظار': getCssVar('--secondary'), 
                    'آماده بازرسی اولیه': getCssVar('--primary'), 
                    'منتظر بازرسی مجدد': getCssVar('--warning'), 
                    'نیاز به تعمیر': getCssVar('--warning'), 
                    'تایید شده': getCssVar('--success'), 
                    'رد شده': getCssVar('--error') 
                };
            }

            function getBaseChartOptions() {
                const isDark = domRefs.htmlEl.classList.contains('dark');
                return {
                    chart: {
                        fontFamily: 'Samim',
                        background: 'transparent',
                        foreColor: isDark ? '#f1f5f9' : '#1e293b',
                        toolbar: { show: false },
                        animations: { enabled: true, speed: 400 }
                    },
                    theme: { mode: isDark ? 'dark' : 'light' },
                    grid: { borderColor: isDark ? '#334155' : '#e2e8f0' },
                    tooltip: { theme: isDark ? 'dark' : 'light' },
                    legend: {
                        position: 'top',
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
                if (!element) { console.warn(`Chart element '${elementId}' not found.`); return; }
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
                renderKPIs(data, domRefs.staticKpiContainer);
                renderDoughnutChart('staticOverallProgressChart', data);
            }

            function renderKPIs(data, container) {
                const kpi = data.reduce((acc, item) => {
                    acc.total++;
                    if (item.final_status === 'تایید شده') acc.ok++;
                    else if (item.final_status === 'آماده بازرسی اولیه') acc.ready++;
                    else if (['رد شده', 'نیاز به تعمیر'].includes(item.final_status)) acc.issues++;
                    return acc;
                }, { total: 0, ok: 0, ready: 0, issues: 0 });
                
                container.innerHTML = `
                    <div class="kpi-card"><h3>کل</h3><p class="value">${kpi.total.toLocaleString('fa')}</p></div>
                    <div class="kpi-card"><h3>تایید</h3><p class="value">${kpi.ok.toLocaleString('fa')}</p></div>
                    <div class="kpi-card"><h3>آماده</h3><p class="value">${kpi.ready.toLocaleString('fa')}</p></div>
                    <div class="kpi-card"><h3>ایراد</h3><p class="value">${kpi.issues.toLocaleString('fa')}</p></div>
                `;
            }

            function renderTable(data) {
                domRefs.tableBody.innerHTML = data.length === 0 ?
                    '<tr><td colspan="8" style="text-align:center;">هیچ رکوردی یافت نشد.</td></tr>' :
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

            // --- SETUP FUNCTIONS ---
            function setupFilters() {
                const elementTypes = [...new Set(allInspectionsData.map(item => item.element_type))].filter(Boolean).sort();
                const statuses = [...new Set(allInspectionsData.map(item => item.final_status))].filter(Boolean).sort();
                elementTypes.forEach(type => domRefs.typeSelect.add(new Option(type, type)));
                statuses.forEach(status => domRefs.statusSelect.add(new Option(status, status)));
            }
            
            function applyAllFilters() {
                const search = domRefs.searchInput.value.toLowerCase();
                const type = domRefs.typeSelect.value;
                const status = domRefs.statusSelect.value;
                
                currentlyDisplayedData = allInspectionsData.filter(item => {
                    const matchesType = !type || item.element_type === type;
                    const matchesStatus = !status || item.final_status === status;
                    const matchesSearch = !search || Object.values(item).some(val => String(val).toLowerCase().includes(search));
                    return matchesType && matchesStatus && matchesSearch;
                });
                renderTable(currentlyDisplayedData);
            }

            // --- EVENT LISTENERS ---
            function setupEventListeners() {
                ['input', 'change'].forEach(evt => {
                    domRefs.searchInput.addEventListener(evt, applyAllFilters);
                    domRefs.typeSelect.addEventListener(evt, applyAllFilters);
                    domRefs.statusSelect.addEventListener(evt, applyAllFilters);
                });

                domRefs.reportButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const planFile = domRefs.reportZoneSelect.value;
                        if (!planFile) { 
                            alert('لطفا ابتدا یک فایل نقشه را انتخاب کنید.'); 
                            return; 
                        }
                        const statusToHighlight = this.dataset.status;
                        let url = `/ghom/viewer.php?plan=${encodeURIComponent(planFile)}`;
                        if (statusToHighlight !== 'all') { 
                            url += `&highlight_status=${encodeURIComponent(statusToHighlight)}`; 
                        }
                        window.open(url, '_blank');
                    });
                });
            }

            // --- INITIALIZE THE DASHBOARD ---
            updateChartColors();
            setupFilters();
            setupEventListeners();
            
            // Initial render of all components
            renderStaticSection(allInspectionsData);
            renderTable(allInspectionsData);

            // Add fade-in animation to sections
            setTimeout(() => {
                document.querySelectorAll('.section-container').forEach((section, index) => {
                    setTimeout(() => { section.style.transition = 'opacity 0.5s, transform 0.5s'; section.style.opacity = '1'; section.style.transform = 'translateY(0)'; }, index * 100);
                });
            }, 200);
        }

        // Start the entire process when the DOM is ready
        document.addEventListener('DOMContentLoaded', loadDashboard);
    </script>
</body>
</html>
