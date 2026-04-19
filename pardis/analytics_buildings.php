<?php
// public_html/pardis/analytics_buildings.php
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['admin', 'superuser', 'cod'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

$pageTitle = "تحلیل‌های ساختمان‌محور - پروژه دانشگاه خاتم پردیس";
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحلیل ساختمان‌ها</title>
    
    <!-- CSS Libraries -->
    <link href="/pardis/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/pardis/assets/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/pardis/assets/css/jalalidatepicker.min.css" />
    
    <style>
        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            background: transparent;
            padding: 15px;
            margin: 0;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 16px 20px;
            font-weight: 600;
        }
        .stat-box {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: transform 0.2s;
        }
        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .stat-box .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .stat-box .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .progress-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }
        .table-sm td, .table-sm th {
            padding: 0.5rem;
            font-size: 0.9rem;
        }
        .filter-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        #loadingOverlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .building-card {
            border-right: 4px solid #667eea;
            margin-bottom: 15px;
        }
        .part-row:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay">
        <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">در حال بارگذاری...</span>
        </div>
    </div>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2>
                    <i class="bi bi-building text-primary"></i>
                    تحلیل پیشرفت به تفکیک ساختمان و بخش
                </h2>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">از تاریخ</label>
                    <input type="text" id="startDate" class="form-control" data-jdp readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">تا تاریخ</label>
                    <input type="text" id="endDate" class="form-control" data-jdp readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">پروژه</label>
                    <select id="projectFilter" class="form-select">
                        <option value="all">همه</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">ساختمان</label>
                    <select id="buildingFilter" class="form-select">
                        <option value="all">همه</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button id="applyFilters" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> اعمال فیلتر
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="analyticsTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" 
                        data-bs-target="#overview" type="button">
                    <i class="bi bi-grid"></i> نمای کلی
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="buildings-tab" data-bs-toggle="tab" 
                        data-bs-target="#buildings" type="button">
                    <i class="bi bi-building"></i> ساختمان‌ها
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="parts-tab" data-bs-toggle="tab" 
                        data-bs-target="#parts" type="button">
                    <i class="bi bi-puzzle"></i> بخش‌های ساختمان
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="engineers-tab" data-bs-toggle="tab" 
                        data-bs-target="#engineers" type="button">
                    <i class="bi bi-people"></i> عملکرد مهندسین
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="timeline-tab" data-bs-toggle="tab" 
                        data-bs-target="#timeline" type="button">
                    <i class="bi bi-graph-up"></i> روند زمانی
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="work-details-tab" data-bs-toggle="tab" 
                        data-bs-target="#work-details" type="button">
                    <i class="bi bi-table"></i> جزئیات کارها
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="part-timeline-tab" data-bs-toggle="tab" 
                        data-bs-target="#part-timeline" type="button">
                    <i class="bi bi-graph-up-arrow"></i> تایم‌لاین بخش‌ها
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="analyticsTabsContent">
            
            <!-- Overview Tab -->
            <div class="tab-pane fade show active" id="overview" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="row" id="overviewStats">
                            <!-- Will be populated by JS -->
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">توزیع ساعات کاری به تفکیک ساختمان</div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="buildingHoursChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">وضعیت تکمیل ساختمان‌ها</div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="buildingProgressChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Buildings Tab -->
            <div class="tab-pane fade" id="buildings" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-body">
                        <div id="buildingSummaryContainer">
                            <!-- Will be populated by JS -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parts Tab -->
            <div class="tab-pane fade" id="parts" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="partsTable" class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>ساختمان</th>
                                        <th>بخش</th>
                                        <th>تعداد فعالیت</th>
                                        <th>ساعات کل</th>
                                        <th>میانگین پیشرفت</th>
                                        <th>حداکثر پیشرفت</th>
                                        <th>وضعیت</th>
                                        <th>مهندسین</th>
                                        <th>آخرین کار</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Engineers Tab -->
            <div class="tab-pane fade" id="engineers" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="engineersTable" class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>نام مهندس</th>
                                        <th>ساختمان</th>
                                        <th>تعداد گزارش</th>
                                        <th>تعداد فعالیت</th>
                                        <th>ساعات کل</th>
                                        <th>میانگین پیشرفت</th>
                                        <th>آخرین گزارش</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline Tab -->
            <div class="tab-pane fade" id="timeline" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">روند ساعات کاری روزانه</div>
                    <div class="card-body">
                        <div style="height: 400px;">
                            <canvas id="timelineChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Work Details Tab -->
            <div class="tab-pane fade" id="work-details" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="bi bi-table"></i> جزئیات کامل کارهای انجام شده
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="workDetailsTable" class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>تاریخ</th>
                                        <th>پروژه</th>
                                        <th>ساختمان</th>
                                        <th>بخش</th>
                                        <th>شرح کار</th>
                                        <th>نوع فعالیت</th>
                                        <th>پیشرفت</th>
                                        <th>ساعات</th>
                                        <th>وضعیت</th>
                                        <th>مهندس</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Part Timeline Tab -->
            <div class="tab-pane fade" id="part-timeline" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-graph-up-arrow"></i> تایم‌لاین پیشرفت بخش‌های ساختمان</span>
                            <select id="partTimelineBuilding" class="form-select" style="width: auto;">
                                <option value="all">همه ساختمان‌ها</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="height: 450px;">
                            <canvas id="partTimelineChart"></canvas>
                        </div>
                        <hr>
                        <div class="table-responsive mt-3">
                            <table id="partTimelineTable" class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>تاریخ</th>
                                        <th>ساختمان</th>
                                        <th>بخش</th>
                                        <th>حداکثر پیشرفت</th>
                                        <th>ساعات روز</th>
                                        <th>تعداد مهندسین</th>
                                        <th>مهندسین</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- JS Libraries -->
    <script src="/pardis/assets/js/jquery-3.6.0.min.js"></script>
    <script src="/pardis/assets/js/bootstrap.bundle.min.js"></script>
    <script src="/pardis/assets/js/jquery.dataTables.min.js"></script>
    <script src="/pardis/assets/js/dataTables.bootstrap5.min.js"></script>
    <script src="/pardis/assets/js/chart.js"></script>
    <script src="/pardis/assets/js/jalalidatepicker.min.js"></script>

    <script>
        let partsTable, engineersTable, workDetailsTable, partTimelineTable;
        let buildingHoursChart, buildingProgressChart, timelineChart, partTimelineChart;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date pickers
            jalaliDatepicker.startWatch({ time: false, zIndex: 2000 });
            
            // Initialize DataTables
            partsTable = $('#partsTable').DataTable({
                language: { url: '/pardis/assets/js/fa.json' },
                order: [[4, 'desc']],
                pageLength: 25
            });
            
            engineersTable = $('#engineersTable').DataTable({
                language: { url: '/pardis/assets/js/fa.json' },
                order: [[4, 'desc']],
                pageLength: 25
            });
            
            workDetailsTable = $('#workDetailsTable').DataTable({
                language: { url: '/pardis/assets/js/fa.json' },
                order: [[0, 'desc']],
                pageLength: 50
            });
            
            partTimelineTable = $('#partTimelineTable').DataTable({
                language: { url: '/pardis/assets/js/fa.json' },
                order: [[0, 'desc']],
                pageLength: 25
            });
            
            // Initialize charts
            initializeCharts();
            
            // Load initial data
            loadData();
            
            // Event listeners
            document.getElementById('applyFilters').addEventListener('click', loadData);
            document.getElementById('partTimelineBuilding')?.addEventListener('change', function() {
                updatePartTimelineChart(window.currentData);
            });
        });
        
        function initializeCharts() {
            // Building Hours Chart
            const hoursCtx = document.getElementById('buildingHoursChart').getContext('2d');
            buildingHoursChart = new Chart(hoursCtx, {
                type: 'bar',
                data: { labels: [], datasets: [{ label: 'ساعات کاری', data: [], backgroundColor: 'rgba(102, 126, 234, 0.8)' }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: { x: { beginAtZero: true } }
                }
            });
            
            // Building Progress Chart
            const progressCtx = document.getElementById('buildingProgressChart').getContext('2d');
            buildingProgressChart = new Chart(progressCtx, {
                type: 'doughnut',
                data: { labels: [], datasets: [{ data: [], backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'] }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
            
            // Timeline Chart
            const timelineCtx = document.getElementById('timelineChart').getContext('2d');
            timelineChart = new Chart(timelineCtx, {
                type: 'line',
                data: { labels: [], datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });
            
            // Part Timeline Chart
            const partTimelineCtx = document.getElementById('partTimelineChart').getContext('2d');
            partTimelineChart = new Chart(partTimelineCtx, {
                type: 'line',
                data: { labels: [], datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { 
                        y: { 
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'درصد پیشرفت' }
                        }
                    },
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        async function loadData() {
            showLoading();
            
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const project = document.getElementById('projectFilter').value;
            const building = document.getElementById('buildingFilter').value;
            
            const apiUrl = `analytics_building_api.php?start_date=${startDate}&end_date=${endDate}&project_name=${project}&building_name=${building}`;
            
            try {
                const response = await fetch(apiUrl);
                const data = await response.json();
                
                if (!data.success) {
                    alert('خطا در دریافت اطلاعات');
                    return;
                }
                
                // Update filters
                updateFilters(data.filters);
                
                // Populate all sections
                populateOverview(data.building_summary);
                populateBuildingSummary(data.building_summary);
                populatePartsTable(data.part_details);
                populateEngineersTable(data.engineer_performance);
                populateTimeline(data.timeline_data);
                populateWorkDetailsTable(data.work_details);
                populatePartTimeline(data.part_timeline);
                updateCharts(data);
                
                // Store data for filtering
                window.currentData = data;
                
            } catch (error) {
                console.error('Error loading data:', error);
                alert('خطا در بارگذاری اطلاعات. لطفا دوباره تلاش کنید.');
            } finally {
                hideLoading();
            }
        }
        
        function updateFilters(filters) {
            const projectSelect = document.getElementById('projectFilter');
            const buildingSelect = document.getElementById('buildingFilter');
            
            // Update project filter
            const currentProject = projectSelect.value;
            projectSelect.innerHTML = '<option value="all">همه</option>';
            filters.projects.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p;
                opt.textContent = p;
                projectSelect.appendChild(opt);
            });
            projectSelect.value = currentProject;
            
            // Update building filter
            const currentBuilding = buildingSelect.value;
            buildingSelect.innerHTML = '<option value="all">همه</option>';
            filters.buildings.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b;
                opt.textContent = b;
                buildingSelect.appendChild(opt);
            });
            buildingSelect.value = currentBuilding;
        }
        
        function populateOverview(buildingSummary) {
            const container = document.getElementById('overviewStats');
            
            // Calculate totals
            const totalBuildings = buildingSummary.length;
            const totalHours = buildingSummary.reduce((sum, b) => sum + parseFloat(b.total_hours || 0), 0);
            const totalActivities = buildingSummary.reduce((sum, b) => sum + parseInt(b.total_activities || 0), 0);
            const avgProgress = buildingSummary.reduce((sum, b) => sum + parseFloat(b.avg_progress || 0), 0) / totalBuildings || 0;
            const completedActivities = buildingSummary.reduce((sum, b) => sum + parseInt(b.completed_count || 0), 0);
            const inProgressActivities = buildingSummary.reduce((sum, b) => sum + parseInt(b.in_progress_count || 0), 0);
            const blockedActivities = buildingSummary.reduce((sum, b) => sum + parseInt(b.blocked_count || 0), 0);
            
            container.innerHTML = `
                <div class="col-md-3">
                    <div class="stat-box">
                        <div class="stat-label">تعداد ساختمان‌ها</div>
                        <div class="stat-value text-primary">${totalBuildings}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <div class="stat-label">کل ساعات کاری</div>
                        <div class="stat-value text-info">${totalHours.toFixed(1)}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <div class="stat-label">کل فعالیت‌ها</div>
                        <div class="stat-value text-success">${totalActivities}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <div class="stat-label">میانگین پیشرفت کل</div>
                        <div class="stat-value text-warning">${avgProgress.toFixed(1)}%</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="stat-label"><i class="bi bi-check-circle text-success"></i> فعالیت‌های تکمیل شده</div>
                        <div class="stat-value text-success">${completedActivities}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="stat-label"><i class="bi bi-hourglass-split text-primary"></i> در حال انجام</div>
                        <div class="stat-value text-primary">${inProgressActivities}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="stat-label"><i class="bi bi-exclamation-triangle text-danger"></i> مسدود شده</div>
                        <div class="stat-value text-danger">${blockedActivities}</div>
                    </div>
                </div>
            `;
        }
        
        function populateBuildingSummary(buildingSummary) {
            const container = document.getElementById('buildingSummaryContainer');
            
            if (!buildingSummary || buildingSummary.length === 0) {
                container.innerHTML = '<div class="alert alert-info">داده‌ای یافت نشد</div>';
                return;
            }
            
            let html = '';
            buildingSummary.forEach(building => {
                const progressColor = building.avg_progress >= 80 ? 'bg-success' : 
                                     building.avg_progress >= 50 ? 'bg-warning' : 'bg-danger';
                
                html += `
                    <div class="card building-card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <h5 class="mb-1">${building.building_name || 'نامشخص'}</h5>
                                    <small class="text-muted">${building.project_name || ''}</small>
                                </div>
                                <div class="col-md-2 text-center">
                                    <div class="progress-circle ${progressColor} text-white">
                                        ${building.avg_progress}%
                                    </div>
                                    <small class="text-muted d-block mt-1">میانگین پیشرفت</small>
                                </div>
                                <div class="col-md-7">
                                    <div class="row g-2">
                                        <div class="col-4">
                                            <small class="text-muted">ساعات کل</small>
                                            <div class="fw-bold">${building.total_hours} ساعت</div>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">فعالیت‌ها</small>
                                            <div class="fw-bold">${building.total_activities}</div>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">مهندسین</small>
                                            <div class="fw-bold">${building.engineers_count} نفر</div>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">تکمیل شده</small>
                                            <div class="text-success fw-bold">${building.completed_count}</div>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">در حال انجام</small>
                                            <div class="text-primary fw-bold">${building.in_progress_count}</div>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">مسدود</small>
                                            <div class="text-danger fw-bold">${building.blocked_count}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <small><i class="bi bi-calendar-check text-success"></i> اولین فعالیت: ${building.first_activity_date_fa}</small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <small><i class="bi bi-calendar text-primary"></i> آخرین فعالیت: ${building.last_activity_date_fa}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function populatePartsTable(partDetails) {
            partsTable.clear();
            
            if (!partDetails || partDetails.length === 0) return;
            
            const rows = partDetails.map(part => {
                const statusClass = part.status === 'completed' ? 'bg-success' :
                                   part.status === 'in_progress' ? 'bg-primary' :
                                   part.status === 'blocked' ? 'bg-danger' : 'bg-secondary';
                
                return [
                    part.building_name || '-',
                    part.building_part || '-',
                    part.activities_count || 0,
                    parseFloat(part.total_hours || 0).toFixed(1),
                    `<div class="progress" style="height: 20px; min-width: 80px;">
                        <div class="progress-bar" style="width: ${part.avg_progress}%">${part.avg_progress}%</div>
                    </div>`,
                    `<span class="badge bg-info">${part.max_progress}%</span>`,
                    `<span class="status-badge ${statusClass} text-white">${part.status_label}</span>`,
                    `<small title="${part.engineer_names}">${part.engineers_count} نفر</small>`,
                    part.last_worked_date_fa || '-'
                ];
            });
            
            partsTable.rows.add(rows).draw();
        }
        
        function populateEngineersTable(engineerPerformance) {
            engineersTable.clear();
            
            if (!engineerPerformance || engineerPerformance.length === 0) return;
            
            const rows = engineerPerformance.map(eng => [
                eng.engineer_name || '-',
                eng.building_name || '-',
                eng.reports_count || 0,
                eng.activities_count || 0,
                parseFloat(eng.total_hours || 0).toFixed(1),
                `<div class="progress" style="height: 20px; min-width: 60px;">
                    <div class="progress-bar bg-success" style="width: ${eng.avg_progress}%">${eng.avg_progress}%</div>
                </div>`,
                eng.last_report_date_fa || '-'
            ]);
            
            engineersTable.rows.add(rows).draw();
        }
        
        function populateTimeline(timelineData) {
            if (!timelineData || timelineData.length === 0) return;
            
            // Group by building
            const buildingGroups = {};
            timelineData.forEach(item => {
                const building = item.building_name || 'نامشخص';
                if (!buildingGroups[building]) {
                    buildingGroups[building] = [];
                }
                buildingGroups[building].push(item);
            });
            
            // Prepare datasets
            const labels = [...new Set(timelineData.map(d => d.work_date_fa))].sort();
            const datasets = Object.keys(buildingGroups).map((building, index) => {
                const colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'];
                const data = labels.map(date => {
                    const item = buildingGroups[building].find(d => d.work_date_fa === date);
                    return item ? parseFloat(item.daily_hours) : 0;
                });
                
                return {
                    label: building,
                    data: data,
                    borderColor: colors[index % colors.length],
                    backgroundColor: colors[index % colors.length] + '33',
                    tension: 0.1
                };
            });
            
            timelineChart.data.labels = labels;
            timelineChart.data.datasets = datasets;
            timelineChart.update();
        }
        
        function populateWorkDetailsTable(workDetails) {
            workDetailsTable.clear();
            
            if (!workDetails || workDetails.length === 0) return;
            
            const rows = workDetails.map(work => {
                const statusClass = work.completion_status === 'completed' ? 'bg-success' :
                                   work.completion_status === 'in_progress' ? 'bg-primary' :
                                   work.completion_status === 'blocked' ? 'bg-danger' : 
                                   work.completion_status === 'delayed' ? 'bg-warning' : 'bg-secondary';
                
                return [
                    work.report_date_fa || '-',
                    work.project_name || '-',
                    work.building_name || '-',
                    work.building_part || '-',
                    work.task_description || '-',
                    work.activity_type || '-',
                    `<div class="progress" style="height: 18px; min-width: 60px;">
                        <div class="progress-bar" style="width: ${work.progress_percentage}%">${work.progress_percentage}%</div>
                    </div>`,
                    work.hours_spent || 0,
                    `<span class="status-badge ${statusClass} text-white">${work.status_label}</span>`,
                    work.engineer_name || '-'
                ];
            });
            
            workDetailsTable.rows.add(rows).draw();
        }
        
        function populatePartTimeline(partTimeline) {
            if (!partTimeline || partTimeline.length === 0) return;
            
            // Populate table
            partTimelineTable.clear();
            const tableRows = partTimeline.map(item => [
                item.work_date_fa || '-',
                item.building_name || '-',
                item.building_part || '-',
                `<span class="badge bg-info">${item.max_progress}%</span>`,
                item.daily_hours || 0,
                item.engineers_count || 0,
                `<small title="${item.engineer_names}">${item.engineer_names || '-'}</small>`
            ]);
            partTimelineTable.rows.add(tableRows).draw();
            
            // Update building filter dropdown
            const buildings = [...new Set(partTimeline.map(d => d.building_name))].filter(b => b);
            const buildingSelect = document.getElementById('partTimelineBuilding');
            const currentValue = buildingSelect.value;
            buildingSelect.innerHTML = '<option value="all">همه ساختمان‌ها</option>';
            buildings.forEach(building => {
                const opt = document.createElement('option');
                opt.value = building;
                opt.textContent = building;
                buildingSelect.appendChild(opt);
            });
            buildingSelect.value = currentValue;
            
            // Update chart
            updatePartTimelineChart({ part_timeline: partTimeline });
        }
        
        function updatePartTimelineChart(data) {
            const partTimeline = data.part_timeline || [];
            if (partTimeline.length === 0) return;
            
            const selectedBuilding = document.getElementById('partTimelineBuilding')?.value || 'all';
            
            // Filter data by selected building
            let filteredData = partTimeline;
            if (selectedBuilding !== 'all') {
                filteredData = partTimeline.filter(d => d.building_name === selectedBuilding);
            }
            
            // Group by building part
            const partGroups = {};
            filteredData.forEach(item => {
                const partKey = `${item.building_name} - ${item.building_part}`;
                if (!partGroups[partKey]) {
                    partGroups[partKey] = [];
                }
                partGroups[partKey].push(item);
            });
            
            // Get all unique dates
            const allDates = [...new Set(filteredData.map(d => d.work_date_fa))].sort();
            
            // Create datasets
            const colors = [
                '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', 
                '#858796', '#5a5c69', '#2e59d9', '#17a673', '#2c9faf',
                '#dda20a', '#cc3939', '#6c757d', '#495057'
            ];
            
            const datasets = Object.keys(partGroups).slice(0, 10).map((partKey, index) => {
                const partData = partGroups[partKey];
                const data = allDates.map(date => {
                    const item = partData.find(d => d.work_date_fa === date);
                    return item ? parseFloat(item.max_progress) : null;
                });
                
                return {
                    label: partKey,
                    data: data,
                    borderColor: colors[index % colors.length],
                    backgroundColor: colors[index % colors.length] + '33',
                    tension: 0.3,
                    fill: false,
                    spanGaps: true
                };
            });
            
            partTimelineChart.data.labels = allDates;
            partTimelineChart.data.datasets = datasets;
            partTimelineChart.update();
        }
        
        function updateCharts(data) {
            const buildingSummary = data.building_summary || [];
            
            // Building Hours Chart
            const sortedByHours = [...buildingSummary].sort((a, b) => parseFloat(b.total_hours) - parseFloat(a.total_hours)).slice(0, 10);
            buildingHoursChart.data.labels = sortedByHours.map(b => b.building_name || 'نامشخص');
            buildingHoursChart.data.datasets[0].data = sortedByHours.map(b => parseFloat(b.total_hours || 0));
            buildingHoursChart.update();
            
            // Building Progress Chart
            buildingProgressChart.data.labels = buildingSummary.map(b => b.building_name || 'نامشخص');
            buildingProgressChart.data.datasets[0].data = buildingSummary.map(b => parseFloat(b.avg_progress || 0));
            buildingProgressChart.update();
        }
        
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
    </script>
</body>
</html>