<?php
// daily_reports_dashboard.php - FINAL VERSION (Fixed Signs, Materials, Exports)
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
require_once __DIR__ . '/../includes/pagination.php';
secureSession();
require_once __DIR__ . '/header.php';
$contractor_map = [
    'cat' => 'شرکت آتیه نما',
    'car' => 'شرکت آرانسج',
    'coa' => 'شرکت عمران آذرستان',
    'crs' => 'شرکت ساختمانی رس'
];

// Get contractor name for current user if they're a contractor

$pdo = getProjectDBConnection('ghom');
$user_role = $_SESSION['role'];
$is_contractor = in_array($user_role, ['cat', 'car', 'coa', 'crs']);
$contractor_name = null;
if ($is_contractor && isset($contractor_map[$user_role])) {
    $contractor_name = $contractor_map[$user_role];
}
// --- ANALYTICS QUERIES ---

// 1. Status Distribution
$statsQuery = $pdo->query("SELECT status, COUNT(*) as count FROM daily_reports GROUP BY status");
$statsData = $statsQuery->fetchAll(PDO::FETCH_KEY_PAIR);
$statsJson = json_encode([
    $statsData['Submitted'] ?? 0,
    $statsData['Approved'] ?? 0,
    $statsData['Rejected'] ?? 0,
    $statsData['Draft'] ?? 0
]);

// 2. Total Manpower
$manpowerQuery = $pdo->query("
    SELECT 
        dr.contractor_fa_name,
        SUM(COALESCE(drp.consultant_count, drp.count)) as total_personnel
    FROM daily_reports dr
    JOIN daily_report_personnel drp ON dr.id = drp.report_id
    WHERE dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY dr.contractor_fa_name
    ORDER BY total_personnel DESC
    LIMIT 10
");
$manpowerData = $manpowerQuery->fetchAll(PDO::FETCH_ASSOC);
$manpowerLabels = json_encode(array_column($manpowerData, 'contractor_fa_name'));
$manpowerCounts = json_encode(array_column($manpowerData, 'total_personnel'));

// 3. Equipment Usage (Prioritize Consultant Active Count)
$equipmentQuery = $pdo->query("
    SELECT 
        machine_name,
        SUM(COALESCE(drm.consultant_active_count, drm.active_count)) as total_active
    FROM daily_report_machinery drm
    JOIN daily_reports dr ON drm.report_id = dr.id
    WHERE dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
      AND machine_name != ''
    GROUP BY machine_name
    ORDER BY total_active DESC
    LIMIT 10
");
$equipmentData = $equipmentQuery->fetchAll(PDO::FETCH_ASSOC);
$equipmentLabels = json_encode(array_column($equipmentData, 'machine_name'));
$equipmentCounts = json_encode(array_column($equipmentData, 'total_active'));

// 4. Activities
$activitiesQuery = $pdo->query("
    SELECT pa.name as activity_name, COUNT(dra.id) as occurrence_count
    FROM daily_report_activities dra
    JOIN project_activities pa ON dra.activity_id = pa.id
    JOIN daily_reports dr ON dra.report_id = dr.id
    WHERE dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY pa.id ORDER BY occurrence_count DESC LIMIT 10
");
$activitiesData = $activitiesQuery->fetchAll(PDO::FETCH_ASSOC);
$activityLabels = json_encode(array_column($activitiesData, 'activity_name'));
$activityCounts = json_encode(array_column($activitiesData, 'occurrence_count'));

// 5. Trend
$trendQuery = $pdo->query("
    SELECT DATE(created_at) as report_day, COUNT(*) as daily_count
    FROM daily_reports
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at) ORDER BY report_day ASC
");
$trendData = $trendQuery->fetchAll(PDO::FETCH_ASSOC);
$trendDates = [];
$trendCounts = [];
foreach ($trendData as $row) {
    $g_parts = explode('-', $row['report_day']);
    if (count($g_parts) === 3) {
        $j = gregorian_to_jalali((int)$g_parts[0], (int)$g_parts[1], (int)$g_parts[2]);
        $trendDates[] = sprintf('%02d', $j[1]) . '/' . sprintf('%02d', $j[2]);
        $trendCounts[] = $row['daily_count'];
    }
}
$trendDatesJson = json_encode($trendDates);
$trendCountsJson = json_encode($trendCounts);

// 6. NEW: Materials Analytics (Input vs Output)
$materialsQuery = $pdo->query("
    SELECT type, COUNT(*) as count
    FROM daily_report_materials drm
    JOIN daily_reports dr ON drm.report_id = dr.id
    WHERE dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY type
");
$matData = $materialsQuery->fetchAll(PDO::FETCH_KEY_PAIR);
$matIn = $matData['IN'] ?? 0;
$matOut = $matData['OUT'] ?? 0;
$materialsJson = json_encode([$matIn, $matOut]);

// Summary Cards
$totalReports = $pdo->query("SELECT COUNT(*) FROM daily_reports")->fetchColumn();
$totalPersonnel = $pdo->query("SELECT SUM(drp.count) FROM daily_report_personnel drp JOIN daily_reports dr ON drp.report_id = dr.id WHERE dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;
$totalEquipment = $pdo->query("SELECT SUM(active_count) FROM daily_report_machinery drm JOIN daily_reports dr ON drm.report_id = dr.id WHERE dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;
$pendingReviews = $pdo->query("SELECT COUNT(*) FROM daily_reports WHERE status = 'Submitted'")->fetchColumn();

// --- FILTERS & TABLE ---
$whereClause = "1=1";
$params = [];
if ($is_contractor && $contractor_name) {
    $whereClause .= " AND contractor_fa_name = ?";
    $params[] = $contractor_name;
}
if (!empty($_GET['status'])) { $whereClause .= " AND status = ?"; $params[] = $_GET['status']; }
if (!$is_contractor && !empty($_GET['contractor'])) { $whereClause .= " AND contractor_fa_name LIKE ?"; $params[] = "%" . $_GET['contractor'] . "%"; }
// (Date filters simplified for brevity, assume same logic as before)

if ($is_contractor) {
    $whereClause .= " AND submitted_by_user_id = ?";
    $params[] = $_SESSION['user_id'];
}

$sql = "SELECT * FROM daily_reports WHERE $whereClause ORDER BY report_date DESC, id DESC";
$pageResult = paginate($pdo, $sql, $params, 25);
$reports = $pageResult['data'];

// Batch-fetch per-report aggregates once instead of 3 queries per row.
$reportIds = array_column($reports, 'id');
$personnelStats = [];
$equipmentStats = [];
$activitiesCounts = [];
if (!empty($reportIds)) {
    $placeholders = implode(',', array_fill(0, count($reportIds), '?'));

    $pStmt = $pdo->prepare("
        SELECT report_id,
               SUM(count) AS reported,
               SUM(consultant_count) AS approved
        FROM daily_report_personnel
        WHERE report_id IN ($placeholders)
        GROUP BY report_id
    ");
    $pStmt->execute($reportIds);
    while ($row = $pStmt->fetch(PDO::FETCH_ASSOC)) {
        $personnelStats[(int)$row['report_id']] = $row;
    }

    $eStmt = $pdo->prepare("
        SELECT report_id,
               SUM(active_count) AS reported,
               SUM(consultant_active_count) AS approved
        FROM daily_report_machinery
        WHERE report_id IN ($placeholders)
        GROUP BY report_id
    ");
    $eStmt->execute($reportIds);
    while ($row = $eStmt->fetch(PDO::FETCH_ASSOC)) {
        $equipmentStats[(int)$row['report_id']] = $row;
    }

    $aStmt = $pdo->prepare("
        SELECT report_id, COUNT(*) AS total
        FROM daily_report_activities
        WHERE report_id IN ($placeholders)
        GROUP BY report_id
    ");
    $aStmt->execute($reportIds);
    $activitiesCounts = $aStmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
$matChartQuery = $pdo->query("
    SELECT material_name, type, SUM(quantity) as total, unit
    FROM daily_report_materials drm
    JOIN daily_reports dr ON drm.report_id = dr.id
    WHERE dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY material_name, type
    ORDER BY total DESC LIMIT 15
");
$matChartData = $matChartQuery->fetchAll(PDO::FETCH_ASSOC);

// Prepare Chart Data
$matLabels = [];
$matSeriesIn = [];
$matSeriesOut = [];

foreach ($matChartData as $m) {
    $key = $m['material_name'] . " (" . $m['unit'] . ")";
    if (!in_array($key, $matLabels)) $matLabels[] = $key;
    
    // Organize data for ApexCharts (IN vs OUT)
    $idx = array_search($key, $matLabels);
    if ($m['type'] === 'IN') $matSeriesIn[$idx] = $m['total'];
    else $matSeriesOut[$idx] = $m['total'];
}

// Fill gaps with 0
$matSeriesIn = array_replace(array_fill(0, count($matLabels), 0), $matSeriesIn);
$matSeriesOut = array_replace(array_fill(0, count($matLabels), 0), $matSeriesOut);

// 8. MATERIALS TABLE DATA (Recent Transactions)
$matTableQuery = $pdo->query("
    SELECT 
        drm.material_name, drm.category, drm.quantity, drm.unit, drm.type, drm.date as mat_date,
        dr.contractor_fa_name, dr.report_date
    FROM daily_report_materials drm
    JOIN daily_reports dr ON drm.report_id = dr.id
    WHERE dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY dr.report_date DESC LIMIT 20
");
$matTableRows = $matTableQuery->fetchAll(PDO::FETCH_ASSOC);


foreach ($reports as &$report) {
    $report['report_date_jalali'] = '-'; // Default
    $report['status'] = $report['status'] ?? 'Draft';
    
    // Validate date before conversion to prevent jdf error
    if (!empty($report['report_date']) && $report['report_date'] !== '0000-00-00') {
        $parts = explode('-', $report['report_date']);
        if (count($parts) === 3 && checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
            try {
                $j = gregorian_to_jalali((int)$parts[0], (int)$parts[1], (int)$parts[2]);
                $report['report_date_jalali'] = $j[0] . '/' . sprintf('%02d', $j[1]) . '/' . sprintf('%02d', $j[2]);
            } catch (Exception $e) { /* Ignore error */ }
        }
    }
}
unset($report); // Break reference
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/ghom/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ghom/assets/css/all.min.css">
 <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/ghom/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ghom/assets/css/all.min.css">
    <link rel="stylesheet" href="/ghom/assets/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="/ghom/assets/css/responsive.bootstrap5.min.css">

<!-- jQuery (Required for DataTables) -->
<script src="/ghom/assets/js/jquery-3.7.0.min.js"></script>
<!-- DataTables JS -->
<script src="/ghom/assets/js/jquery.dataTables.min.js"></script>
<script src="/ghom/assets/js/dataTables.bootstrap5.min.js"></script>
    <style>
        @font-face { font-family: "Samim"; src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"); }
        * { font-family: "Samim", Tahoma, Arial, sans-serif !important; }
        html, body { direction: rtl !important; text-align: right !important; }
        .stat-card { border: none; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); color: white; background: linear-gradient(135deg, var(--card-start), var(--card-end)); }
        .card-blue { --card-start: #4e73df; --card-end: #224abe; }
        .card-green { --card-start: #1cc88a; --card-end: #13855c; }
        .card-orange { --card-start: #f6c23e; --card-end: #dda20a; }
        .card-red { --card-start: #e74a3b; --card-end: #be2617; }
        .stat-number { font-size: 2.5rem; font-weight: bold; }
        .chart-card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); background: white; }
        .status-badge { font-size: 0.85em; padding: 5px 12px; border-radius: 20px; }
        .status-Submitted { background-color: #fff3cd; color: #856404; }
        .status-Approved { background-color: #d1e7dd; color: #0f5132; }
        .status-Rejected { background-color: #f8d7da; color: #842029; }
        .status-Draft { background-color: #e2e3e5; color: #41464b; }
        .opacity-25 { opacity: 0.25; }
          .fas, .far, .fab {
            font-family: "Font Awesome 6 Free" !important;
            font-weight: 900 !important;
        }
         div.dataTables_wrapper div.dataTables_filter { text-align: left; }
    div.dataTables_wrapper div.dataTables_length { text-align: right; }
    table.dataTable thead th { vertical-align: middle; }
    .dt-input-filter { width: 100%; font-size: 0.85rem; padding: 4px; border: 1px solid #ddd; border-radius: 4px; }
    /* Hide search/sort on Action/Signature columns */
    .no-sort { pointer-events: none; }
    .no-sort input { display: none; }
         .consultant-col { background-color: #fff8e1; } 
    
    /* Styles for Total Column (Auto-calculated) */
    .total-col input { font-weight: bold; color: #333; background-color: #f0f0f0; }
    </style>
    <script src="/ghom/assets/js/apexcharts.min.js"></script>
</head>
<body class="bg-light">

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark"><i class="fas fa-chart-line me-2"></i> داشبورد گزارشات</h2>
        <?php if ($is_contractor): ?>
            <a href="daily_report_form.php" class="btn btn-primary btn-lg shadow"><i class="fas fa-plus"></i> ثبت جدید</a>
        <?php endif; ?>
    </div>

    <!-- Cards -->
    <div class="row mb-4">
        <div class="col-md-3"><div class="stat-card card-blue"><div class="stat-number"><?= number_format($totalReports) ?></div><div>کل گزارشات</div></div></div>
        <div class="col-md-3"><div class="stat-card card-green"><div class="stat-number"><?= number_format($totalPersonnel) ?></div><div>نفر‌روز (30 روز)</div></div></div>
        <div class="col-md-3"><div class="stat-card card-orange"><div class="stat-number"><?= number_format($totalEquipment) ?></div><div>دستگاه‌روز فعال</div></div></div>
        <div class="col-md-3"><div class="stat-card card-red"><div class="stat-number"><?= $pendingReviews ?></div><div>منتظر بررسی</div></div></div>
    </div>

    <!-- Row 1: Status & Trend -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card chart-card h-100">
                <div class="card-header py-3"><h6>وضعیت گزارشات</h6></div>
                <div class="card-body"><div id="statusChart"></div></div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card chart-card h-100">
                <div class="card-header py-3"><h6>روند ثبت (30 روز)</h6></div>
                <div class="card-body"><div id="trendChart"></div></div>
            </div>
        </div>
    </div>

    <!-- Row 2: Materials (NEW) & Manpower -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card chart-card h-100">
                <div class="card-header py-3"><h6><i class="fas fa-cubes"></i> وضعیت مصالح (30 روز)</h6></div>
                <div class="card-body">
                    <div id="materialsChart"></div>
                    <div class="text-center mt-3 small text-muted">تعداد ردیف‌های ثبت شده ورود و خروج</div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card chart-card h-100">
                <div class="card-header py-3"><h6>نیروی کار پیمانکاران</h6></div>
                <div class="card-body"><div id="manpowerChart"></div></div>
            </div>
        </div>
    </div>

    <!-- Row 3: Equipment & Activities -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card chart-card">
                <div class="card-header py-3"><h6>تجهیزات</h6></div>
                <div class="card-body"><div id="equipmentChart"></div></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card chart-card">
                <div class="card-header py-3"><h6>فعالیت‌ها</h6></div>
                <div class="card-body"><div id="activitiesChart"></div></div>
            </div>
        </div>
    </div>
<!-- NEW: DETAILED MATERIALS SECTION -->
    <div class="row mb-4">
        <!-- Chart: Materials by Type/Quantity -->
        <div class="col-lg-6">
            <div class="card chart-card h-100">
                <div class="card-header py-3 bg-white border-bottom">
                    <h6 class="m-0 fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i> نمودار تجمیعی مصالح (30 روز اخیر)</h6>
                </div>
                <div class="card-body">
                    <div id="detailedMaterialsChart"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card chart-card h-100">
                <div class="card-header py-3 bg-white border-bottom">
                    <h6 class="m-0 fw-bold"><i class="fas fa-list me-2 text-success"></i> آخرین تراکنش‌های مصالح</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 350px;">
                        <table id="materialsTable" class="table table-sm table-striped mb-0" style="font-size: 0.85rem;">
    <thead class="table-light sticky-top">
        <tr>
            <th>نوع</th>
            <th>شرح کالا</th>
            <th>مقدار</th>
            <th>پیمانکار</th>
            <th>تاریخ</th>
        </tr>
    </thead>
    <!-- Add a footer for column filters -->
    <tfoot>
        <tr>
            <th>نوع</th>
            <th>شرح</th>
            <th>مقدار</th>
            <th>پیمانکار</th>
            <th>تاریخ</th>
        </tr>
    </tfoot>
                            <tbody>
                                <?php foreach ($matTableRows as $mr): 
                                    $typeClass = ($mr['type'] === 'IN') ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                                    $typeLabel = ($mr['type'] === 'IN') ? 'ورود' : 'خروج';
                                    
                                    // Jalali Date
                                    $jDate = '-';
                                    if(!empty($mr['report_date'])) {
                                        $p = explode('-', $mr['report_date']);
                                        // Basic validation to prevent jdf errors
                                        if(count($p)===3 && checkdate((int)$p[1], (int)$p[2], (int)$p[0])) {
                                            $j = gregorian_to_jalali((int)$p[0],(int)$p[1],(int)$p[2]);
                                            $jDate = $j[0].'/'.$j[1].'/'.$j[2];
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><span class="badge <?= $typeClass ?>"><?= $typeLabel ?></span></td>
                                    <td>
                                        <span class="fw-bold"><?= $mr['material_name'] ?></span>
                                        <br><small class="text-muted"><?= $mr['category'] ?? '' ?></small>
                                    </td>
                                    <!-- FIX IS HERE: Added (float) casting -->
                                    <td class="ltr"><?= number_format((float)$mr['quantity']) . ' ' . $mr['unit'] ?></td>
                                    <td><small><?= $mr['contractor_fa_name'] ?></small></td>
                                    <td><?= $jDate ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <!-- Table -->
   <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white py-3"><h5 class="m-0">لیست گزارشات</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="reportsTable" class="table table-hover align-middle mb-0">
                    <!-- HEADER: 10 Columns -->
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>تاریخ</th>
                            <th>پیمانکار</th>
                            <th>بلوک</th>
                            <th>نفرات</th>
                            <th>تجهیزات</th>
                            <th>فعالیت‌ها</th>
                            <th>وضعیت</th>
                            <th class="no-sort">امضاها</th>
                            <th class="no-sort">عملیات</th>
                        </tr>
                    </thead>
                    
                    <!-- FOOTER: Must have 10 Columns too -->
                    <tfoot>
                        <tr>
                            <th>#</th>
                            <th>تاریخ</th>
                            <th>پیمانکار</th>
                            <th>بلوک</th>
                            <th>نفرات</th>
                            <th>تجهیزات</th>
                            <th>فعالیت‌ها</th>
                            <th>وضعیت</th>
                            <!-- Empty cells for non-searchable columns -->
                            <th></th> 
                            <th></th>
                        </tr>
                    </tfoot>

                    <!-- BODY -->
                    <tbody>
                        <?php if(empty($reports)): ?>
                            <tr><td colspan="10" class="text-center py-4 text-muted">گزارشی یافت نشد.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reports as $row):
                                // Use pre-fetched batch aggregates (avoids N+1 inside the loop).
                                $pStats = $personnelStats[(int)$row['id']] ?? ['reported' => 0, 'approved' => null];
                                $eStats = $equipmentStats[(int)$row['id']] ?? ['reported' => 0, 'approved' => null];
                                $totalAct = $activitiesCounts[(int)$row['id']] ?? 0;
                                
                                // Signatures
                                $has_cont = !empty($row['signature_contractor']);
                                $has_cons = !empty($row['signature_consultant']);
                                $has_emp  = !empty($row['signature_employer']);
                            ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= $row['report_date_jalali'] ?></td>
                                    <td class="fw-bold"><?= $row['contractor_fa_name'] ?></td>
                                    <td><span class="badge bg-secondary"><?= $row['block_name'] ?></span></td>
                                    
                                    <!-- PERSONNEL COLUMN -->
                                    <td>
                                        <div class="d-flex flex-column align-items-center" style="line-height:1.2">
                                            <span class="text-muted small" title="اظهار شده"><?= $pStats['reported'] ?: 0 ?></span>
                                            <?php if(isset($pStats['approved'])): ?>
                                                <span class="fw-bold text-success border-top border-success px-2" title="تایید شده"><?= $pStats['approved'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- EQUIPMENT COLUMN -->
                                    <td>
                                        <div class="d-flex flex-column align-items-center" style="line-height:1.2">
                                            <span class="text-muted small" title="اظهار شده"><?= $eStats['reported'] ?: 0 ?></span>
                                            <?php if(isset($eStats['approved'])): ?>
                                                <span class="fw-bold text-success border-top border-success px-2" title="تایید شده"><?= $eStats['approved'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td><i class="fas fa-tasks text-info"></i> <?= $totalAct ?></td>
                                    
                                    <td>
                                        <span class="status-badge status-<?= $row['status'] ?>">
                                            <?php 
                                                $statusMap = ['Submitted'=>'منتظر بررسی', 'Approved'=>'تایید شده', 'Rejected'=>'رد شده', 'Draft'=>'پیش‌نویس'];
                                                echo $statusMap[$row['status']] ?? $row['status'];
                                            ?>
                                        </span>
                                    </td>
                                    
                                    <!-- SIGNATURES -->
                                    <td class="text-nowrap">
                                        <?php if ($has_cont): ?>
                                            <a href="javascript:void(0);" onclick="viewSign('<?= $row['signature_contractor'] ?>', 'پیمانکار')"><i class="fas fa-pen-nib fs-5 mx-1 text-success"></i></a>
                                        <?php else: ?>
                                            <i class="fas fa-pen-nib fs-5 mx-1 text-secondary opacity-25"></i>
                                        <?php endif; ?>
                                        
                                        <?php if ($has_cons): ?>
                                            <a href="javascript:void(0);" onclick="viewSign('<?= $row['signature_consultant'] ?>', 'مشاور')"><i class="fas fa-user-check fs-5 mx-1 text-success"></i></a>
                                        <?php else: ?>
                                            <i class="fas fa-user-check fs-5 mx-1 text-secondary opacity-25"></i>
                                        <?php endif; ?>
                                        
                                        <?php if ($has_emp): ?>
                                            <a href="javascript:void(0);" onclick="viewSign('<?= $row['signature_employer'] ?>', 'کارفرما')"><i class="fas fa-building fs-5 mx-1 text-success"></i></a>
                                        <?php else: ?>
                                            <i class="fas fa-building fs-5 mx-1 text-secondary opacity-25"></i>
                                        <?php endif; ?>
                                    </td>

                                    <!-- ACTIONS -->
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="daily_report_form.php?id=<?= $row['id'] ?>" class="btn btn-outline-primary"><i class="fas fa-eye"></i></a>
                                            <a href="daily_report_print.php?id=<?= $row['id'] ?>" class="btn btn-outline-success" target="_blank"><i class="fas fa-print"></i></a>
                                            <?php if (!empty($row['signed_scan_path'])): ?>
                                                <a href="<?= $row['signed_scan_path'] ?>" class="btn btn-outline-danger" download><i class="fas fa-download"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= renderPagination($pageResult, '/ghom/daily_reports_dashboard.php') ?>
        </div>
    </div>

<script>
    // GLOBAL CHART OPTIONS (Enable Export)
    const commonOptions = {
        chart: { toolbar: { show: true, tools: { download: true } } },
        fontFamily: 'Samim, Tahoma, sans-serif'
    };

    // 1. Status
    new ApexCharts(document.querySelector("#statusChart"), {
        ...commonOptions,
        series: <?php echo $statsJson; ?>,
        chart: { type: 'donut', height: 280, toolbar: {show:true} },
        labels: ['منتظر بررسی', 'تایید شده', 'رد شده', 'پیش‌نویس'],
        colors: ['#ffc107', '#198754', '#dc3545', '#6c757d'],
        legend: { position: 'bottom' }
    }).render();

    // 2. Trend
    new ApexCharts(document.querySelector("#trendChart"), {
        ...commonOptions,
        series: [{ name: 'تعداد', data: <?php echo $trendCountsJson; ?> }],
        chart: { type: 'area', height: 280, toolbar: {show:true} },
        xaxis: { categories: <?php echo $trendDatesJson; ?> },
        stroke: { curve: 'smooth' }
    }).render();

    // 3. NEW MATERIALS CHART
    new ApexCharts(document.querySelector("#materialsChart"), {
        ...commonOptions,
        series: <?php echo $materialsJson; ?>, // [InCount, OutCount]
        chart: { type: 'pie', height: 280, toolbar: {show:true} },
        labels: ['ورودی (IN)', 'خروجی (OUT)'],
        colors: ['#198754', '#dc3545'], // Green for IN, Red for OUT
        legend: { position: 'bottom' },
        dataLabels: { 
            enabled: true,
            formatter: function (val, opts) {
                return opts.w.config.series[opts.seriesIndex]
            }
        }
    }).render();

    // 4. Manpower
    new ApexCharts(document.querySelector("#manpowerChart"), {
        ...commonOptions,
        series: [{ name: 'نفر‌روز', data: <?php echo $manpowerCounts; ?> }],
        chart: { type: 'bar', height: 280, toolbar: {show:true} },
        xaxis: { categories: <?php echo $manpowerLabels; ?> },
        colors: ['#4e73df']
    }).render();

    // 5. Equipment
    new ApexCharts(document.querySelector("#equipmentChart"), {
        ...commonOptions,
        series: [{ name: 'تعداد', data: <?php echo $equipmentCounts; ?> }],
        chart: { type: 'bar', height: 280, toolbar: {show:true} },
        xaxis: { categories: <?php echo $equipmentLabels; ?> },
        colors: ['#f6c23e']
    }).render();

    // 6. Activities
    new ApexCharts(document.querySelector("#activitiesChart"), {
        ...commonOptions,
        series: [{ name: 'تعداد', data: <?php echo $activityCounts; ?> }],
        chart: { type: 'bar', height: 280, toolbar: {show:true} },
        xaxis: { categories: <?php echo $activityLabels; ?> },
        colors: ['#e74a3b']
    }).render();

      function viewSign(src, title) {
        if(!src) return;
        document.getElementById('signImage').src = src;
        document.getElementById('signModalLabel').innerText = 'امضای ' + title;
        new bootstrap.Modal(document.getElementById('signModal')).show();
    }

    // --- 2. Detailed Materials Chart (Bar) ---
    var matDetailOptions = {
        series: [
            { name: 'ورودی (IN)', data: <?php echo json_encode(array_values($matSeriesIn)); ?> },
            { name: 'خروجی (OUT)', data: <?php echo json_encode(array_values($matSeriesOut)); ?> }
        ],
        chart: { type: 'bar', height: 320, stacked: true, fontFamily: 'Samim' },
        plotOptions: { bar: { horizontal: true, dataLabels: { total: { enabled: true } } } },
        stroke: { width: 1, colors: ['#fff'] },
        xaxis: { categories: <?php echo json_encode($matLabels); ?> },
        colors: ['#198754', '#dc3545'],
        legend: { position: 'top' }
    };
    new ApexCharts(document.querySelector("#detailedMaterialsChart"), matDetailOptions).render();
    $(document).ready(function() {
    // Configuration for Persian DataTables
    const faLang = {
        "sEmptyTable":     "هیچ داده‌ای در جدول وجود ندارد",
        "sInfo":           "نمایش _START_ تا _END_ از _TOTAL_ ردیف",
        "sInfoEmpty":      "نمایش 0 تا 0 از 0 ردیف",
        "sInfoFiltered":   "(فیلتر شده از _MAX_ ردیف)",
        "sInfoPostFix":    "",
        "sInfoThousands":  ",",
        "sLengthMenu":     "نمایش _MENU_ ردیف",
        "sLoadingRecords": "در حال بارگذاری...",
        "sProcessing":     "در حال پردازش...",
        "sSearch":         "جستجو:",
        "sZeroRecords":    "رکوردی با این مشخصات پیدا نشد",
        "oPaginate": {
            "sFirst":    "ابتدا",
            "sLast":     "انتها",
            "sNext":     "بعدی",
            "sPrevious": "قبلی"
        },
        "oAria": {
            "sSortAscending":  ": فعال سازی نمایش به صورت صعودی",
            "sSortDescending": ": فعال سازی نمایش به صورت نزولی"
        }
    };

    // Initialize Function
    function initTable(selector) {
        var table = $(selector).DataTable({
            language: faLang,
            order: [[0, 'desc']], // Sort by first column (ID/Type) descending by default
            columnDefs: [
                { targets: 'no-sort', orderable: false } // Disable sorting on specific columns
            ],
            initComplete: function () {
                // Create Input Filters in Footer
                this.api().columns().every(function () {
                    var column = this;
                    var headerText = $(column.header()).text();
                    
                    // Skip Action/Signature columns
                    if($(column.header()).hasClass('no-sort') || headerText === '') {
                        $(column.footer()).html('');
                        return;
                    }

                    var input = $('<input type="text" class="dt-input-filter" placeholder="جستجو '+headerText+'" />')
                        .appendTo($(column.footer()).empty())
                        .on('keyup change clear', function () {
                            if (column.search() !== this.value) {
                                column.search(this.value).draw();
                            }
                        });
                });
                
                // Move Filters to Header (Optional, cleaner look)
                $(selector + ' tfoot tr').appendTo(selector + ' thead');
            }
        });
    }

    // Activate on tables
    initTable('#materialsTable');
    initTable('#reportsTable');
});
</script>
<script src="/ghom/assets/js/bootstrap.bundle.min.js"></script>
<div class="modal fade" id="signModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="signModalLabel">تصویر امضا</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center bg-light">
                <img id="signImage" src="" class="img-fluid" style="max-height: 200px;">
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php require_once 'footer.php'; ?>