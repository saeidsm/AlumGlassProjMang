<?php
//public_html/pardis/analytics.php (Optimized for iframe)
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['admin', 'superuser', 'cod'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

// Fetch unique project names for the filter dropdown
$pdo = getProjectDBConnection('pardis');
$projects = $pdo->query("SELECT DISTINCT project_name FROM daily_reports WHERE project_name IS NOT NULL AND project_name != '' ORDER BY project_name")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحلیل‌های عمومی</title>
    
    <!-- Libraries -->
    <link href="/pardis/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/pardis/assets/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/pardis/assets/css/jalalidatepicker.min.css" />
    <style>
        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            background: transparent;
            padding: 15px;
            margin: 0;
        }
        .chart-container { 
            position: relative; 
            height: 350px; 
            width: 100%; 
        }
        #loadingSpinner { 
            display: none; 
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px 8px 0 0 !important;
            padding: 12px 16px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-bar-chart-line"></i> تحلیل‌های تفصیلی فعالیت‌ها</h5>
            </div>
            <div class="card-body">
                <!-- Filter Section -->
                <div class="row g-3 p-3 mb-4 bg-light border rounded">
                    <div class="col-md-3">
                        <label for="startDate" class="form-label">از تاریخ</label>
                        <input type="text" id="startDate" class="form-control" data-jdp readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="endDate" class="form-label">تا تاریخ</label>
                        <input type="text" id="endDate" class="form-control" data-jdp readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="projectFilter" class="form-label">پروژه</label>
                        <select id="projectFilter" class="form-select">
                            <option value="all" selected>همه پروژه‌ها</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo htmlspecialchars($project); ?>"><?php echo htmlspecialchars($project); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="col-md-3 d-flex align-items-end">
                        <button id="applyFilters" class="btn btn-primary w-100">
                            <span id="filterBtnText">اعمال فیلتر</span>
                            <div id="loadingSpinner" class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Tabs for Content -->
                <ul class="nav nav-tabs" id="analyticsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="table-tab" data-bs-toggle="tab" data-bs-target="#table-content" type="button" role="tab">لیست فعالیت‌ها</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="charts-tab" data-bs-toggle="tab" data-bs-target="#charts-content" type="button" role="tab">نمودارها</button>
                    </li>
                </ul>
                <div class="tab-content" id="analyticsTabsContent">
                    <!-- Detailed Table Tab -->
                    <div class="tab-pane fade show active" id="table-content" role="tabpanel">
                        <div class="table-responsive mt-3">
                            <table id="tasksTable" class="table table-striped table-bordered" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>شرح فعالیت</th>
                                        <th>تاریخ</th>
                                        <th>پروژه</th>
                                        <th>ساختمان</th>
                                        <th>بخش</th>
                                        <th>پیشرفت (%)</th>
                                        <th>ساعات</th>
                                        <th>وضعیت</th>
                                        <th>مهندس</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Charts Tab -->
                    <div class="tab-pane fade" id="charts-content" role="tabpanel">
                         <div class="row mt-4">
                             <div class="col-md-4">
                                <label for="groupBy" class="form-label">گروه‌بندی زمانی</label>
                                <select id="groupBy" class="form-select">
                                    <option value="daily" selected>روزانه</option>
                                    <option value="weekly">هفتگی</option>
                                    <option value="monthly">ماهانه</option>
                                </select>
                             </div>
                         </div>
                        <div class="row mt-4">
                            <div class="col-lg-6 mb-4">
                                <h5>میانگین پیشرفت</h5>
                                <div class="chart-container">
                                    <canvas id="progressChart"></canvas>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-4">
                                <h5>مجموع ساعات کاری</h5>
                                <div class="chart-container">
                                    <canvas id="hoursChart"></canvas>
                                </div>
                            </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Date Pickers
            jalaliDatepicker.startWatch({ time: false, zIndex: 2000 });

            // Initialize DataTable
            const tasksTable = $('#tasksTable').DataTable({
                language: {
                    url: '/pardis/assets/js/fa.json'
                },
                pageLength: 25
            });

            // Initialize Charts
            const progressChartCtx = document.getElementById('progressChart').getContext('2d');
            const progressChart = new Chart(progressChartCtx, {
                type: 'bar',
                data: { labels: [], datasets: [{ label: 'میانگین پیشرفت', data: [], backgroundColor: 'rgba(54, 162, 235, 0.6)' }] },
                options: { responsive: true, maintainAspectRatio: false }
            });

            const hoursChartCtx = document.getElementById('hoursChart').getContext('2d');
            const hoursChart = new Chart(hoursChartCtx, {
                type: 'line',
                data: { labels: [], datasets: [{ label: 'مجموع ساعات', data: [], borderColor: 'rgba(255, 99, 132, 1)', tension: 0.1, fill: false }] },
                options: { responsive: true, maintainAspectRatio: false }
            });

            // Main data loading function
            async function loadData() {
                document.getElementById('loadingSpinner').style.display = 'inline-block';
                document.getElementById('filterBtnText').style.display = 'none';

                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                const project = document.getElementById('projectFilter').value;
                const groupBy = document.getElementById('groupBy').value;

                const apiUrl = `analytics_api.php?start_date=${startDate}&end_date=${endDate}&project_name=${project}&group_by=${groupBy}`;
                
                try {
                    const response = await fetch(apiUrl);
                    const data = await response.json();

                    // Populate Table
                    tasksTable.clear();
                    const tableData = data.tasks.map(task => [
                        task.task_description,
                        task.report_date_fa,
                        task.project_name,
                        task.building_name,
                        task.building_part,
                        task.progress_percentage,
                        task.hours_spent,
                        task.status,
                        task.engineer_name
                    ]);
                    tasksTable.rows.add(tableData).draw();

                    // Populate Charts
                    const labels = data.chartData.map(d => d.period_start_date_fa);
                    const progressData = data.chartData.map(d => d.avg_progress);
                    const hoursData = data.chartData.map(d => d.total_hours);

                    progressChart.data.labels = labels;
                    progressChart.data.datasets[0].data = progressData;
                    progressChart.update();

                    hoursChart.data.labels = labels;
                    hoursChart.data.datasets[0].data = hoursData;
                    hoursChart.update();

                } catch (error) {
                    console.error('Error fetching analytics data:', error);
                    alert('خطا در دریافت اطلاعات. لطفا دوباره تلاش کنید.');
                } finally {
                    document.getElementById('loadingSpinner').style.display = 'none';
                    document.getElementById('filterBtnText').style.display = 'inline-block';
                }
            }

            // Event Listeners
            document.getElementById('applyFilters').addEventListener('click', loadData);
            document.getElementById('groupBy').addEventListener('change', loadData);

            // Initial load
            loadData();
        });
    </script>
</body>
</html>