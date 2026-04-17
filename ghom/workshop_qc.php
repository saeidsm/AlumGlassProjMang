<?php
// ===================================================================
// WORKSHOP QC REPORT (ENHANCED WITH CHARTS & ANALYTICS)
// ===================================================================

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();
if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}
if (!in_array($_SESSION['role'], ['admin', 'superuser', 'cat', 'car', 'coa', 'crs'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

$pageTitle = "گزارش کنترل کیفیت پنل‌های باز شده";
require_once __DIR__ . '/header_ghom.php';
$message = '';
$error = '';
$user_can_manage = in_array($_SESSION['role'], ['admin', 'superuser']);

// Handle DELETE Action
if ($user_can_manage && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_qc') {
    try {
        $qc_id_to_delete = filter_input(INPUT_POST, 'qc_id', FILTER_VALIDATE_INT);
        if (!$qc_id_to_delete) {
            throw new Exception("شناسه رکورد نامعتبر است.");
        }
        $pdo = getProjectDBConnection('ghom');
        $stmt = $pdo->prepare("DELETE FROM workshop_qc WHERE qc_id = ?");
        $stmt->execute([$qc_id_to_delete]);
        
        if ($stmt->rowCount() > 0) {
            $message = "رکورد با موفقیت حذف شد.";
        } else {
            $error = "رکورد یافت نشد یا قبلاً حذف شده است.";
        }
    } catch (Exception $e) {
        $error = "خطا در حذف رکورد: " . $e->getMessage();
    }
}

// Helper Functions
function getFinalStatusText($row) {
    // Use the final_status directly from database (trim and lowercase for comparison)
    $status = strtolower(trim($row['final_status'] ?? ''));
    
    switch ($status) {
        case 'usable': return 'قابل استفاده';
        case 'in_repair': return 'در حال تعمیر';
        case 'rejected': return 'رد شده';
        default: return 'نامشخص';
    }
}

function getFinalStatusClass($statusText) {
    if ($statusText === 'رد شده') return 'status-rejected';
    if ($statusText === 'در حال تعمیر') return 'status-repair';
    if ($statusText === 'قابل استفاده') return 'status-usable';
    return '';
}

// Fetch Data
$all_qc_data = [];
$all_zones_for_filter = [];
$db_error_message = null;
$stats = [
    'total' => 0,
    'usable' => 0,
    'in_repair' => 0,
    'rejected' => 0,
    'by_zone' => [],
    'by_panel_type' => [],
    'by_date' => [],
    'common_damages' => []
];

try {
    $pdo = getProjectDBConnection('ghom');
    
    $all_qc_data = $pdo->query("SELECT * FROM workshop_qc ORDER BY qc_date DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $all_zones_for_filter = $pdo->query("SELECT DISTINCT zone_name FROM workshop_qc WHERE zone_name IS NOT NULL ORDER BY zone_name")->fetchAll(PDO::FETCH_COLUMN);

    // Calculate statistics
    $stats['total'] = count($all_qc_data);
    
    foreach ($all_qc_data as $row) {
        $status = getFinalStatusText($row);
        
        if ($status === 'قابل استفاده') $stats['usable']++;
        elseif ($status === 'در حال تعمیر') $stats['in_repair']++;
        elseif ($status === 'رد شده') $stats['rejected']++;
        
        // By zone
        $zone = $row['zone_name'] ?: 'نامشخص';
        if (!isset($stats['by_zone'][$zone])) {
            $stats['by_zone'][$zone] = ['usable' => 0, 'repair' => 0, 'rejected' => 0];
        }
        if ($status === 'قابل استفاده') $stats['by_zone'][$zone]['usable']++;
        elseif ($status === 'در حال تعمیر') $stats['by_zone'][$zone]['repair']++;
        else $stats['by_zone'][$zone]['rejected']++;
        
        // By panel type
        $panel_type = $row['panel_type'] ?: 'Unknown';
        if (!isset($stats['by_panel_type'][$panel_type])) {
            $stats['by_panel_type'][$panel_type] = 0;
        }
        $stats['by_panel_type'][$panel_type]++;
        
        // By date (for trend)
        if ($row['qc_date']) {
            $date = $row['qc_date'];
            if (!isset($stats['by_date'][$date])) {
                $stats['by_date'][$date] = 0;
            }
            $stats['by_date'][$date]++;
        }
    }
    
    // Sort and limit panel types for chart
    arsort($stats['by_panel_type']);
    $stats['by_panel_type'] = array_slice($stats['by_panel_type'], 0, 10, true);

} catch (Exception $e) {
    $db_error_message = "خطا در بارگذاری داده‌ها: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeHtml($pageTitle); ?></title>
    <link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
                url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
                url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
        }

        body { 
            font-family: 'Samim', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            margin: 0;
        }
        .container { 
            max-width: 98%; 
            margin: auto; 
            background: #fff; 
            padding: 25px; 
            border-radius: 15px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.3); 
        }
        h1 { 
            color: #667eea;
            border-bottom: 3px solid #667eea; 
            padding-bottom: 15px; 
            margin-bottom: 25px;
            font-size: 2em;
        }
        
        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 1em;
            opacity: 0.9;
        }
        .stat-card .number {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-card.usable { background: linear-gradient(135deg, #28a745 0%, #38ef7d 100%); }
        .stat-card.repair { background: linear-gradient(135deg, #ffc107 0%, #ffeb3b 100%); }
        .stat-card.rejected { background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%); }
        
        /* Charts Section */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .chart-container h3 {
            color: #667eea;
            margin-top: 0;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }
        
        /* Filters */
        .filters { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 20px; 
            padding: 20px; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 10px; 
            margin-bottom: 20px; 
        }
        .filters .form-group { 
            display: flex; 
            flex-direction: column; 
        }
        .filters label { 
            margin-bottom: 5px; 
            font-weight: bold;
            color: #333;
        }
        .filters input, .filters select { 
            padding: 10px; 
            border-radius: 8px; 
            border: 2px solid #ddd; 
            min-width: 180px;
            transition: border-color 0.3s;
        }
        .filters input:focus, .filters select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        /* Table */
        .table-wrapper { 
            overflow-x: auto; 
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 0.9em; 
        }
        th, td { 
            padding: 15px 12px; 
            border: 1px solid #ddd; 
            text-align: right; 
            white-space: nowrap; 
        }
        thead { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
        }
        th { 
            cursor: pointer; 
            user-select: none;
            font-weight: bold;
        }
        th.sort-asc::after { content: " ▲"; }
        th.sort-desc::after { content: " ▼"; }
        tbody tr:nth-child(even) { background-color: #f9f9f9; }
        tbody tr:hover { background-color: #e8f4f8; }
        
        .status-rejected { background-color: #f8d7da !important; color: #721c24; font-weight: bold; }
        .status-repair { background-color: #fff3cd !important; color: #856404; font-weight: bold; }
        .status-usable { background-color: #d4edda !important; color: #155724; font-weight: bold; }
        
        .btn { 
            padding: 8px 16px; 
            border-radius: 20px; 
            border: none; 
            color: white; 
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: scale(1.05);
        }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .btn-info { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .btn-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .btn-danger { background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%); }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-weight: 500;
        }
        .alert-success { background-color: #d4edda; color: #155724; border: 2px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 2px solid #f5c6cb; }
        
        @media (max-width: 768px) {
            .charts-grid { grid-template-columns: 1fr; }
            .filters { flex-direction: column; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo escapeHtml($pageTitle); ?></h1>
        <a href="workshop_qc.php" class="btn btn-primary" style="margin-bottom:20px;">+ ثبت رکورد جدید</a>

        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($db_error_message): ?><div class="alert alert-danger"><?php echo $db_error_message; ?></div><?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>📊 کل پنل‌های بررسی شده</h3>
                <div class="number"><?php echo number_format($stats['total']); ?></div>
            </div>
            <div class="stat-card usable">
                <h3>✅ قابل استفاده</h3>
                <div class="number"><?php echo number_format($stats['usable']); ?></div>
                <small><?php echo $stats['total'] > 0 ? round(($stats['usable']/$stats['total'])*100, 1) : 0; ?>%</small>
            </div>
            <div class="stat-card repair">
                <h3>🔧 در حال تعمیر</h3>
                <div class="number"><?php echo number_format($stats['in_repair']); ?></div>
                <small><?php echo $stats['total'] > 0 ? round(($stats['in_repair']/$stats['total'])*100, 1) : 0; ?>%</small>
            </div>
            <div class="stat-card rejected">
                <h3>❌ رد شده</h3>
                <div class="number"><?php echo number_format($stats['rejected']); ?></div>
                <small><?php echo $stats['total'] > 0 ? round(($stats['rejected']/$stats['total'])*100, 1) : 0; ?>%</small>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-container">
                <h3>📈 توزیع وضعیت پنل‌ها</h3>
                <canvas id="statusChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>🏗️ پنل‌های بررسی شده بر اساس نوع</h3>
                <canvas id="panelTypeChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>📍 توزیع پنل‌ها بر اساس زون</h3>
                <canvas id="zoneChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>📅 روند بررسی روزانه</h3>
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <div class="form-group">
                <label for="filter-zone">فیلتر بر اساس زون</label>
                <select id="filter-zone">
                    <option value="">همه زون‌ها</option>
                    <?php foreach ($all_zones_for_filter as $zone): ?>
                        <option value="<?php echo escapeHtml($zone); ?>"><?php echo escapeHtml($zone); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="filter-status">فیلتر بر اساس وضعیت</label>
                <select id="filter-status">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="رد شده">رد شده</option>
                    <option value="در حال تعمیر">در حال تعمیر</option>
                    <option value="قابل استفاده">قابل استفاده</option>
                </select>
            </div>
            <div class="form-group">
                <label for="filter-date-start">از تاریخ QC</label>
                <input type="text" id="filter-date-start" data-jdp placeholder="انتخاب تاریخ">
            </div>
            <div class="form-group">
                <label for="filter-date-end">تا تاریخ QC</label>
                <input type="text" id="filter-date-end" data-jdp placeholder="انتخاب تاریخ">
            </div>
            <div class="form-group">
                <label for="filter-text">جستجوی کد پنل</label>
                <input type="text" id="filter-text" placeholder="بخشی از کد را وارد کنید...">
            </div>
        </div>

        <!-- Table -->
        <div class="table-wrapper">
            <table id="qc-report-table">
                <thead>
                    <tr>
                        <th data-sort-col="zone_name">زون</th>
                        <th data-sort-col="panel_type">نوع پنل</th>
                        <th data-sort-col="element_id">کد پنل</th>
                        <th data-sort-col="qc_date">تاریخ QC</th>
                        <th data-sort-col="final_status">وضعیت نهایی</th>
                        <th data-sort-col="damage_description">شرح آسیب</th>
                        <th data-sort-col="repair_actions_needed">تعمیرات لازم</th>
                        <?php if ($user_can_manage): ?>
                            <th>عملیات</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_qc_data as $row): ?>
                        <?php $status_text = getFinalStatusText($row); ?>
                        <tr class="data-row" 
                            data-zone="<?php echo escapeHtml($row['zone_name']); ?>"
                            data-status="<?php echo escapeHtml($status_text); ?>"
                            data-date="<?php echo $row['qc_date'] ? strtotime($row['qc_date']) : ''; ?>">
                            <td><?php echo escapeHtml($row['zone_name'] ?: '—'); ?></td>
                            <td><strong><?php echo escapeHtml($row['panel_type'] ?: '—'); ?></strong></td>
                            <td><?php echo escapeHtml($row['element_id']); ?></td>
                            <td><?php echo $row['qc_date'] ? jdate('Y/m/d', strtotime($row['qc_date'])) : '---'; ?></td>
                            <td class="<?php echo getFinalStatusClass($status_text); ?>"><?php echo escapeHtml($status_text); ?></td>
                            <td><?php echo escapeHtml($row['damage_description'] ?: '—'); ?></td>
                            <td><?php echo escapeHtml(implode(', ', json_decode($row['repair_actions_needed'] ?? '[]', true)) ?: '—'); ?></td>
                            <?php if ($user_can_manage): ?>
                                <td style="white-space: nowrap;">
                                    <a href="workshop_qc.php?view_id=<?php echo $row['qc_id']; ?>" class="btn btn-info">👁️ مشاهده</a>
                                    <a href="workshop_qc.php?edit_id=<?php echo $row['qc_id']; ?>" class="btn btn-warning">✏️ ویرایش</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('آیا از حذف این رکورد اطمینان دارید؟ این عمل قابل بازگشت نیست.');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="qc_id" value="<?php echo $row['qc_id']; ?>">
                                        <button type="submit" name="action" value="delete_qc" class="btn btn-danger">🗑️ حذف</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="/ghom/assets/js/jalalidatepicker.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Date Pickers
            if (typeof jalaliDatepicker !== 'undefined') {
                jalaliDatepicker.startWatch({ autoHide: true, selector: '[data-jdp]' });
            }

            // Chart Data
            const statusData = {
                labels: ['قابل استفاده', 'در حال تعمیر', 'رد شده'],
                datasets: [{
                    data: [<?php echo $stats['usable']; ?>, <?php echo $stats['in_repair']; ?>, <?php echo $stats['rejected']; ?>],
                    backgroundColor: ['#38ef7d', '#ffc107', '#dc3545']
                }]
            };

            const panelTypeData = {
                labels: <?php echo json_encode(array_keys($stats['by_panel_type'])); ?>,
                datasets: [{
                    label: 'تعداد پنل',
                    data: <?php echo json_encode(array_values($stats['by_panel_type'])); ?>,
                    backgroundColor: '#667eea'
                }]
            };

            const zoneLabels = <?php echo json_encode(array_keys($stats['by_zone'])); ?>;
            const zoneData = {
                labels: zoneLabels,
                datasets: [
                    {
                        label: 'قابل استفاده',
                        data: <?php echo json_encode(array_column($stats['by_zone'], 'usable')); ?>,
                        backgroundColor: '#38ef7d'
                    },
                    {
                        label: 'در حال تعمیر',
                        data: <?php echo json_encode(array_column($stats['by_zone'], 'repair')); ?>,
                        backgroundColor: '#ffc107'
                    },
                    {
                        label: 'رد شده',
                        data: <?php echo json_encode(array_column($stats['by_zone'], 'rejected')); ?>,
                        backgroundColor: '#dc3545'
                    }
                ]
            };

            const trendData = {
                labels: <?php echo json_encode(array_keys($stats['by_date'])); ?>,
                datasets: [{
                    label: 'تعداد بررسی',
                    data: <?php echo json_encode(array_values($stats['by_date'])); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            };

            // Create Charts
            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: statusData,
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });

            new Chart(document.getElementById('panelTypeChart'), {
                type: 'bar',
                data: panelTypeData,
                options: { responsive: true, plugins: { legend: { display: false } } }
            });

            new Chart(document.getElementById('zoneChart'), {
                type: 'bar',
                data: zoneData,
                options: { 
                    responsive: true,
                    scales: { x: { stacked: true }, y: { stacked: true } }
                }
            });

            new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: trendData,
                options: { responsive: true, plugins: { legend: { display: false } } }
            });

            // Filtering Logic
            const table = document.getElementById('qc-report-table');
            const tbody = table.querySelector('tbody');
            const filters = {
                zone: document.getElementById('filter-zone'),
                status: document.getElementById('filter-status'),
                dateStart: document.getElementById('filter-date-start'),
                dateEnd: document.getElementById('filter-date-end'),
                text: document.getElementById('filter-text')
            };

            function applyFilters() {
                const zoneValue = filters.zone.value;
                const statusValue = filters.status.value;
                const textValue = filters.text.value.toLowerCase();
                
                const startDate = filters.dateStart.datepicker ? new Date(filters.dateStart.datepicker.gDate).setHours(0,0,0,0) / 1000 : null;
                const endDate = filters.dateEnd.datepicker ? new Date(filters.dateEnd.datepicker.gDate).setHours(23,59,59,999) / 1000 : null;

                tbody.querySelectorAll('tr.data-row').forEach(row => {
                    const rowZone = row.dataset.zone;
                    const rowStatus = row.dataset.status;
                    const rowDate = parseInt(row.dataset.date);
                    const rowText = row.cells[2].textContent.toLowerCase();

                    const zoneMatch = !zoneValue || rowZone === zoneValue;
                    const statusMatch = !statusValue || rowStatus.includes(statusValue);
                    const textMatch = !textValue || rowText.includes(textValue);
                    const dateMatch = (!startDate || rowDate >= startDate) && (!endDate || rowDate <= endDate);

                    row.style.display = (zoneMatch && statusMatch && textMatch && dateMatch) ? '' : 'none';
                });
            }

            Object.values(filters).forEach(filter => {
                if(filter.hasAttribute('data-jdp')) {
                    filter.addEventListener('jdp:change', applyFilters);
                } else {
                    filter.addEventListener('input', applyFilters);
                }
            });

            // Sorting Logic
            const headers = table.querySelectorAll('thead th[data-sort-col]');
            headers.forEach(header => {
                header.addEventListener('click', () => {
                    const columnKey = header.dataset.sortCol;
                    const isAsc = header.classList.contains('sort-asc');
                    const direction = isAsc ? -1 : 1;
                    
                    const rows = Array.from(tbody.querySelectorAll('tr.data-row'));
                    
                    rows.sort((a, b) => {
                        let valA = a.querySelector(`td:nth-child(${Array.from(header.parentNode.children).indexOf(header) + 1})`).textContent.trim();
                        let valB = b.querySelector(`td:nth-child(${Array.from(header.parentNode.children).indexOf(header) + 1})`).textContent.trim();
                        
                        if (columnKey === 'qc_date') {
                            valA = a.dataset.date || 0;
                            valB = b.dataset.date || 0;
                            return (valA - valB) * direction;
                        }
                        
                        return valA.localeCompare(valB, 'fa') * direction;
                    });
                    
                    tbody.innerHTML = '';
                    rows.forEach(row => tbody.appendChild(row));
                    
                    headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
                    header.classList.toggle('sort-asc', !isAsc);
                    header.classList.toggle('sort-desc', isAsc);
                });
            });
        });
    </script>
</body>
</html>