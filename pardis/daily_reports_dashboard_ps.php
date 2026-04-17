<?php
// daily_reports_dashboard_ps.php - FIXED VERSION
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
require_once __DIR__ . '/../includes/pagination.php';
secureSession();

// 1. DB CONNECTION & ROLES
$pdo = getProjectDBConnection('pardis');
$user_role = $_SESSION['role'];

// Pardis Contractors
$contractor_map = [
    'car' => 'شرکت آران سیج',
    'cod' => 'شرکت طرح و نقش آدرم'
];
$is_contractor = array_key_exists($user_role, $contractor_map);

// Load Header
if (preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"])) {
    require_once __DIR__ . '/header.php';
} else {
    require_once __DIR__ . '/header.php';
}

// --- 2. BASE QUERY (ISOLATION) ---
$baseWhere = "1=1";
$baseParams = [];

if ($is_contractor) {
    $target_name = $contractor_map[$user_role];
    $baseWhere .= " AND contractor_fa_name LIKE ?";
    $baseParams[] = "%" . $target_name . "%";
}

// --- 3. ANALYTICS QUERIES ---

// 3.1 Status
$statsQuery = $pdo->prepare("SELECT status, COUNT(*) as count FROM ps_daily_reports WHERE $baseWhere GROUP BY status");
$statsQuery->execute($baseParams);
$statsData = $statsQuery->fetchAll(PDO::FETCH_KEY_PAIR);
$statsJson = json_encode([
    $statsData['Submitted'] ?? 0,
    $statsData['Approved'] ?? 0,
    $statsData['Rejected'] ?? 0,
    $statsData['Draft'] ?? 0
]);

// 3.2 Manpower by Contractor
$manpowerQuery = $pdo->prepare("
    SELECT dr.contractor_fa_name, 
           SUM(COALESCE(NULLIF(drp.consultant_count, 0), drp.count + drp.count_night)) as total 
    FROM ps_daily_reports dr 
    JOIN ps_daily_report_personnel drp ON dr.id = drp.report_id 
    WHERE $baseWhere AND dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) 
    GROUP BY dr.contractor_fa_name 
    ORDER BY total DESC LIMIT 10");
$manpowerQuery->execute($baseParams);
$manData = $manpowerQuery->fetchAll(PDO::FETCH_ASSOC);
$manpowerLabels = json_encode(array_column($manData, 'contractor_fa_name'));
$manpowerCounts = json_encode(array_column($manData, 'total'));

// 3.3 Equipment by Type
$equipQuery = $pdo->prepare("
    SELECT machine_name, 
           SUM(COALESCE(NULLIF(drm.consultant_active_count, 0), drm.active_count)) as total 
    FROM ps_daily_report_machinery drm 
    JOIN ps_daily_reports dr ON drm.report_id = dr.id 
    WHERE $baseWhere AND dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) AND machine_name != '' 
    GROUP BY machine_name 
    ORDER BY total DESC LIMIT 10");
$equipQuery->execute($baseParams);
$eqData = $equipQuery->fetchAll(PDO::FETCH_ASSOC);
$equipmentLabels = json_encode(array_column($eqData, 'machine_name'));
$equipmentCounts = json_encode(array_column($eqData, 'total'));

// 3.4 Activities
$actQuery = $pdo->prepare("
    SELECT pa.name, COUNT(dra.id) as cnt 
    FROM ps_daily_report_activities dra 
    JOIN ps_project_activities pa ON dra.activity_id = pa.id 
    JOIN ps_daily_reports dr ON dra.report_id = dr.id 
    WHERE $baseWhere AND dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) 
    GROUP BY pa.id ORDER BY cnt DESC LIMIT 10");
$actQuery->execute($baseParams);
$actData = $actQuery->fetchAll(PDO::FETCH_ASSOC);
$activityLabels = json_encode(array_column($actData, 'name'));
$activityCounts = json_encode(array_column($actData, 'cnt'));

// 3.5 Trend (Last 30 Days)
$trendQuery = $pdo->prepare("
    SELECT DATE(created_at) as d, COUNT(*) as c 
    FROM ps_daily_reports 
    WHERE $baseWhere AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) 
    GROUP BY DATE(created_at) 
    ORDER BY d ASC");
$trendQuery->execute($baseParams);
$trendData = $trendQuery->fetchAll(PDO::FETCH_ASSOC);
$tDates = []; $tCounts = [];
foreach($trendData as $r) {
    $p = explode('-', $r['d']);
    if(count($p)===3) {
        $j = gregorian_to_jalali((int)$p[0],(int)$p[1],(int)$p[2]);
        $tDates[] = $j[1].'/'.$j[2];
    }
    $tCounts[] = $r['c'];
}
$trendDatesJson = json_encode($tDates);
$trendCountsJson = json_encode($tCounts);

// 3.6 Materials Chart (BY MATERIAL NAME with IN/OUT breakdown)
$matChartQuery = $pdo->prepare("
    SELECT material_name, type, 
           SUM(CAST(COALESCE(NULLIF(consultant_quantity, ''), quantity) AS DECIMAL(10,2))) as total 
    FROM ps_daily_report_materials drm 
    JOIN ps_daily_reports dr ON drm.report_id = dr.id 
    WHERE $baseWhere AND dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) 
    GROUP BY material_name, type 
    ORDER BY material_name, type");
$matChartQuery->execute($baseParams);
$matChartData = $matChartQuery->fetchAll(PDO::FETCH_ASSOC);

// Activities Performance
// 3.9 Activities Performance Query
$actPerformanceQuery = $pdo->prepare("
    SELECT 
        pa.name,
        SUM(COALESCE(NULLIF(dra.consultant_qty_day, 0), dra.qty_day)) as qty_day,
        SUM(COALESCE(NULLIF(dra.consultant_qty_night, 0), dra.qty_night)) as qty_night,
        SUM(COALESCE(NULLIF(dra.consultant_qty_cumulative, 0), dra.qty_cumulative)) as qty_cumulative
    FROM ps_daily_report_activities dra
    JOIN ps_project_activities pa ON dra.activity_id = pa.id
    JOIN ps_daily_reports dr ON dra.report_id = dr.id
    WHERE $baseWhere AND dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY pa.id, pa.name
    ORDER BY qty_cumulative DESC
    LIMIT 10");
$actPerformanceQuery->execute($baseParams);
$actPerfData = $actPerformanceQuery->fetchAll(PDO::FETCH_ASSOC);

$actPerfLabels = json_encode(array_column($actPerfData, 'name'));
$actPerfDay = json_encode(array_map('floatval', array_column($actPerfData, 'qty_day')));
$actPerfNight = json_encode(array_map('floatval', array_column($actPerfData, 'qty_night')));
$actPerfCum = json_encode(array_map('floatval', array_column($actPerfData, 'qty_cumulative')));

// Activities Table with Detailed Info
$actTableQuery = $pdo->prepare("
    SELECT 
        dra.*, 
        pa.name as activity_name,
        dr.contractor_fa_name, 
        dr.report_date,
        dr.block_name
    FROM ps_daily_report_activities dra
    JOIN ps_project_activities pa ON dra.activity_id = pa.id
    JOIN ps_daily_reports dr ON dra.report_id = dr.id
    WHERE $baseWhere AND dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY dr.report_date DESC 
    LIMIT 30");
$actTableQuery->execute($baseParams);
$actTableRows = $actTableQuery->fetchAll(PDO::FETCH_ASSOC);

// Group by material
$materialsGrouped = [];
foreach($matChartData as $m) {
    $name = $m['material_name'];
    if(!isset($materialsGrouped[$name])) {
        $materialsGrouped[$name] = ['IN' => 0, 'OUT' => 0];
    }
    $materialsGrouped[$name][$m['type']] = (float)$m['total'];
}

// Sort by total volume and take top 15
uasort($materialsGrouped, function($a, $b) {
    return ($b['IN'] + $b['OUT']) <=> ($a['IN'] + $a['OUT']);
});
$materialsGrouped = array_slice($materialsGrouped, 0, 15, true);

$matLabels = array_keys($materialsGrouped);
$matSeriesIn = array_column($materialsGrouped, 'IN');
$matSeriesOut = array_column($materialsGrouped, 'OUT');

// Materials Table (Latest)
$matTableQuery = $pdo->prepare("
    SELECT drm.*, dr.contractor_fa_name, dr.report_date 
    FROM ps_daily_report_materials drm 
    JOIN ps_daily_reports dr ON drm.report_id = dr.id 
    WHERE $baseWhere AND dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) 
    ORDER BY dr.report_date DESC LIMIT 20");
$matTableQuery->execute($baseParams);
$matTableRows = $matTableQuery->fetchAll(PDO::FETCH_ASSOC);

// Summary Cards
$q = $pdo->prepare("SELECT COUNT(*) FROM ps_daily_reports WHERE $baseWhere"); 
$q->execute($baseParams); 
$totalReports = $q->fetchColumn();

$q = $pdo->prepare("
    SELECT SUM(COALESCE(NULLIF(drp.consultant_count, 0), drp.count + drp.count_night)) 
    FROM ps_daily_report_personnel drp 
    JOIN ps_daily_reports dr ON drp.report_id = dr.id 
    WHERE $baseWhere AND dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"); 
$q->execute($baseParams); 
$totalPersonnel = $q->fetchColumn() ?: 0;

$q = $pdo->prepare("
    SELECT SUM(COALESCE(NULLIF(drm.consultant_active_count, 0), drm.active_count)) 
    FROM ps_daily_report_machinery drm 
    JOIN ps_daily_reports dr ON drm.report_id = dr.id 
    WHERE $baseWhere AND dr.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"); 
$q->execute($baseParams); 
$totalEquipment = $q->fetchColumn() ?: 0;

$q = $pdo->prepare("SELECT COUNT(*) FROM ps_daily_reports WHERE $baseWhere AND status = 'Submitted'"); 
$q->execute($baseParams); 
$pendingReviews = $q->fetchColumn();

// --- 4. MAIN TABLE DATA & FILTERS ---
$tableWhere = $baseWhere;
$tableParams = $baseParams;

if (!empty($_GET['status'])) { 
    $tableWhere .= " AND status = ?"; 
    $tableParams[] = $_GET['status']; 
}
if (!$is_contractor && !empty($_GET['contractor'])) { 
    $tableWhere .= " AND contractor_fa_name LIKE ?"; 
    $tableParams[] = "%".$_GET['contractor']."%"; 
}
if (!empty($_GET['date_from'])) { 
    $p=explode('/',$_GET['date_from']); 
    if(count($p)===3) { 
        $g=jalali_to_gregorian($p[0],$p[1],$p[2]); 
        $tableWhere.=" AND report_date >= ?"; 
        $tableParams[]=implode('-',$g); 
    } 
}
if (!empty($_GET['date_to'])) { 
    $p=explode('/',$_GET['date_to']); 
    if(count($p)===3) { 
        $g=jalali_to_gregorian($p[0],$p[1],$p[2]); 
        $tableWhere.=" AND report_date <= ?"; 
        $tableParams[]=implode('-',$g); 
    } 
}

$tableSql = "SELECT * FROM ps_daily_reports WHERE $tableWhere ORDER BY report_date DESC, id DESC";
$pageResult = paginate($pdo, $tableSql, $tableParams, 25);
$reports = $pageResult['data'];

// Batch-fetch per-report aggregates in one query each to avoid N+1 inside the row loop.
$reportIds = array_column($reports, 'id');
$personnelTotals = [];
$equipmentTotals = [];
$activitiesCounts = [];
if (!empty($reportIds)) {
    $placeholders = implode(',', array_fill(0, count($reportIds), '?'));

    $pStmt = $pdo->prepare("
        SELECT report_id,
               SUM(COALESCE(NULLIF(consultant_count, 0), count + count_night)) AS total
        FROM ps_daily_report_personnel
        WHERE report_id IN ($placeholders)
        GROUP BY report_id
    ");
    $pStmt->execute($reportIds);
    $personnelTotals = $pStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $eStmt = $pdo->prepare("
        SELECT report_id,
               SUM(COALESCE(NULLIF(consultant_active_count, 0), active_count)) AS total
        FROM ps_daily_report_machinery
        WHERE report_id IN ($placeholders)
        GROUP BY report_id
    ");
    $eStmt->execute($reportIds);
    $equipmentTotals = $eStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $aStmt = $pdo->prepare("
        SELECT report_id, COUNT(*) AS total
        FROM ps_daily_report_activities
        WHERE report_id IN ($placeholders)
        GROUP BY report_id
    ");
    $aStmt->execute($reportIds);
    $activitiesCounts = $aStmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Helper: Date Formatter
function getJDate($gDate) {
    if(empty($gDate) || $gDate == '0000-00-00') return '-';
    $p = explode('-', $gDate);
    if(count($p) !== 3) return '-';
    $j = gregorian_to_jalali((int)$p[0],(int)$p[1],(int)$p[2]);
    return $j[0].'/'.sprintf('%02d',$j[1]).'/'.sprintf('%02d',$j[2]);
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد گزارشات پردیس</title>
    
    <link href="/pardis/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/pardis/assets/css/all.min.css">
    <link rel="stylesheet" href="/pardis/assets/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/pardis/assets/css/jalalidatepicker.min.css" />
    
    <script src="/pardis/assets/js/jquery-3.7.0.min.js"></script>
    <script src="/pardis/assets/js/bootstrap.bundle.min.js"></script>
    <script src="/pardis/assets/js/jquery.dataTables.min.js"></script>
    <script src="/pardis/assets/js/dataTables.bootstrap5.min.js"></script>
    <script src="/pardis/assets/js/apexcharts.min.js"></script>
    <script src="/pardis/assets/js/jalalidatepicker.min.js"></script>

    <style>
        @font-face { font-family: "Samim"; src: url("/pardis/assets/fonts/Samim-FD.woff2") format("woff2"); }
        * { font-family: "Samim", Tahoma, Arial, sans-serif !important; }
        body { background-color: #f8f9fa; direction: rtl; text-align: right; }
        
        .stat-card { border: none; border-radius: 12px; padding: 20px; color: white; background: linear-gradient(135deg, var(--c1), var(--c2)); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card-blue { --c1:#4e73df; --c2:#224abe; } .card-green { --c1:#1cc88a; --c2:#13855c; }
        .card-orange { --c1:#f6c23e; --c2:#dda20a; } .card-red { --c1:#e74a3b; --c2:#be2617; }
        .stat-number { font-size: 2.2rem; font-weight: 700; }
        
        .chart-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); background: white; margin-bottom: 20px; }
        .status-badge { font-size: 0.8em; padding: 4px 10px; border-radius: 15px; display: inline-block; }
        .status-Submitted { background:#fff3cd; color:#856404; } .status-Approved { background:#d1e7dd; color:#0f5132; }
        .status-Rejected { background:#f8d7da; color:#842029; } .status-Draft { background:#e2e3e5; color:#41464b; }
        
        .fas, .far, .fab { font-family: "Font Awesome 6 Free" !important; font-weight: 900 !important; }
        
        .fraction-display {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            line-height: 1.2;
        }
        .fraction-numerator {
            font-weight: bold;
            color: #333;
            font-size: 0.95rem;
        }
        .fraction-line {
            width: 100%;
            height: 1px;
            background-color: #666;
            margin: 1px 0;
        }
        .fraction-denominator {
            font-weight: bold;
            color: #dc3545;
            font-size: 0.9rem;
        }
        .crossed-out {
            text-decoration: line-through;
            opacity: 0.6;
            color: #999 !important;
        }
        
        /* FIX: DataTables RTL alignment */
        table.dataTable thead th,
        table.dataTable thead td,
        table.dataTable tbody td {
            text-align: right !important;
        }
        
        /* FIX: Datepicker positioning */
        .jalali-datepicker-wrapper {
            z-index: 9999 !important;
        }
        
        /* FIX: Table responsive wrapper */
        .dataTables_wrapper .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark"><i class="fas fa-chart-line me-2"></i> داشبورد پروژه پردیس</h3>
        <?php if ($is_contractor): ?>
            <a href="daily_report_form_ps.php" class="btn btn-primary shadow"><i class="fas fa-plus"></i> ثبت گزارش جدید</a>
        <?php endif; ?>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3"><div class="stat-card card-blue"><div class="stat-number"><?= number_format($totalReports) ?></div><div>کل گزارشات</div></div></div>
        <div class="col-md-3 mb-3"><div class="stat-card card-green"><div class="stat-number"><?= number_format($totalPersonnel) ?></div><div>نفر‌روز (30 روز)</div></div></div>
        <div class="col-md-3 mb-3"><div class="stat-card card-orange"><div class="stat-number"><?= number_format($totalEquipment) ?></div><div>دستگاه‌روز (30 روز)</div></div></div>
        <div class="col-md-3 mb-3"><div class="stat-card card-red"><div class="stat-number"><?= $pendingReviews ?></div><div>منتظر بررسی</div></div></div>
    </div>
    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="small">وضعیت</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="Submitted" <?= ($_GET['status']??'')==='Submitted'?'selected':'' ?>>منتظر بررسی</option>
                        <option value="Approved" <?= ($_GET['status']??'')==='Approved'?'selected':'' ?>>تایید شده</option>
                        <option value="Rejected" <?= ($_GET['status']??'')==='Rejected'?'selected':'' ?>>رد شده</option>
                        <option value="Draft" <?= ($_GET['status']??'')==='Draft'?'selected':'' ?>>پیش‌نویس</option>
                    </select>
                </div>
                <?php if(!$is_contractor): ?>
                <div class="col-md-3">
                    <label class="small">پیمانکار</label>
                    <input type="text" name="contractor" class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['contractor'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="جستجو...">
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <label class="small">از تاریخ</label>
                    <input type="text" name="date_from" data-jdp class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['date_from'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2">
                    <label class="small">تا تاریخ</label>
                    <input type="text" name="date_to" data-jdp class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['date_to'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-secondary w-100">فیلتر</button>
                </div>
                <div class="col-md-1">
                    <a href="?" class="btn btn-sm btn-outline-secondary w-100">پاک</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Table -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="reportsTable" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>تاریخ</th>
                            <th>پیمانکار</th>
                            <th>بلوک</th>
                            <th>نفرات</th>
                            <th>تجهیزات</th>
                            <th>فعالیت</th>
                            <th>وضعیت</th>
                            <th>امضا</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reports)): ?>
                        <tr><td colspan="10" class="text-center py-4">داده‌ای یافت نشد.</td></tr>
                        <?php else: foreach ($reports as $row):
                            // Use pre-fetched batch aggregates (avoids N+1).
                            $pTot = $personnelTotals[$row['id']] ?? 0;
                            $eTot = $equipmentTotals[$row['id']] ?? 0;
                            $aTot = $activitiesCounts[$row['id']] ?? 0;

                            // Signature status
                            $sigC = !empty($row['signature_contractor']); 
                            $sigS = !empty($row['signature_consultant']); 
                            $sigE = !empty($row['signature_employer']);
                        ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= getJDate($row['report_date']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($row['contractor_fa_name']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['block_name']) ?></span></td>
                            <td><?= number_format($pTot) ?></td>
                            <td><?= number_format($eTot) ?></td>
                            <td><span class="badge bg-info text-dark"><?= $aTot ?></span></td>
                            <td><span class="status-badge status-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
                            <td class="text-nowrap">
                                <a href="javascript:void(0)" onclick="viewSign('<?= $row['signature_contractor'] ?>','پیمانکار')" title="امضای پیمانکار">
                                    <i class="fas fa-pen-nib <?= $sigC?'text-success':'text-muted opacity-25' ?>"></i>
                                </a>
                                <a href="javascript:void(0)" onclick="viewSign('<?= $row['signature_consultant'] ?>','مشاور')" title="امضای مشاور">
                                    <i class="fas fa-user-check mx-1 <?= $sigS?'text-primary':'text-muted opacity-25' ?>"></i>
                                </a>
                                <a href="javascript:void(0)" onclick="viewSign('<?= $row['signature_employer'] ?>','کارفرما')" title="امضای کارفرما">
                                    <i class="fas fa-building <?= $sigE?'text-warning':'text-muted opacity-25' ?>"></i>
                                </a>
                            </td>
                            <td class="text-nowrap">
                                <a href="daily_report_form_ps.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="مشاهده"><i class="fas fa-eye"></i></a>
                                <a href="daily_report_print_ps.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success" title="چاپ"><i class="fas fa-print"></i></a>
                                 <?php if (!empty($row['signed_scan_path'])): ?>
                                                <a href="<?= $row['signed_scan_path'] ?>" class="btn btn-outline-danger" download><i class="fas fa-download"></i></a>
                                            <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?= renderPagination($pageResult, '/pardis/daily_reports_dashboard_ps.php') ?>
        </div>
    </div>
    <!-- Charts Row 1 -->
    <div class="row mb-3">
        <div class="col-md-4 mb-3"><div class="card chart-card h-100"><div class="card-body"><h6 class="mb-3">وضعیت</h6><div id="statusChart"></div></div></div></div>
        <div class="col-md-8 mb-3"><div class="card chart-card h-100"><div class="card-body"><h6 class="mb-3">روند ثبت (30 روز)</h6><div id="trendChart"></div></div></div></div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row mb-3">
        <div class="col-lg-6 mb-3"><div class="card chart-card h-100"><div class="card-body"><h6 class="mb-3">نیروی انسانی (پیمانکار)</h6><div id="manpowerChart"></div></div></div></div>
        <div class="col-lg-6 mb-3"><div class="card chart-card h-100"><div class="card-body"><h6 class="mb-3">ماشین‌آلات</h6><div id="equipmentChart"></div></div></div></div>
    </div>

    <!-- Charts Row 3 -->
    <div class="row mb-3">
        <div class="col-lg-6 mb-3"><div class="card chart-card h-100"><div class="card-body"><h6 class="mb-3">فعالیت‌ها</h6><div id="activityChart"></div></div></div></div>
        <div class="col-lg-6 mb-3"><div class="card chart-card h-100"><div class="card-body"><h6 class="mb-3">مصالح (ورود/خروج)</h6><div id="matChart"></div></div></div></div>
    </div>

    <!-- Activity Performance Chart -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card chart-card">
            <div class="card-body">
                <h6 class="mb-3">عملکرد فعالیت‌ها (روز/شب/تاکنون)</h6>
                <div id="actPerformanceChart"></div>
            </div>
        </div>
    </div>
</div>

    <!-- Activities Detail Table -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card chart-card">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0">جزئیات فعالیت‌ها (30 مورد اخیر)</h6>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="activitiesDetailTable" class="table table-sm table-striped table-hover mb-0" style="font-size: 0.85rem; width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th>فعالیت</th>
                                    <th>جبهه کاری</th>
                                    <th>موقعیت</th>
                                    <th>حجم کل</th>
                                    <th>روز</th>
                                    <th>شب</th>
                                    <th>تاکنون</th>
                                    <th>واحد</th>
                                    <th>وضعیت</th>
                                    <th>پیمانکار</th>
                                    <th>بلوک</th>
                                    <th>تاریخ</th>
                                </tr>
                            </thead>
                           <tbody>
    <?php foreach ($actTableRows as $ar): 
        $qtyDay = (float)$ar['qty_day'];
        $qtyDayConsultant = !empty($ar['consultant_qty_day']) ? (float)$ar['consultant_qty_day'] : null;
        
        $qtyNight = (float)$ar['qty_night'];
        $qtyNightConsultant = !empty($ar['consultant_qty_night']) ? (float)$ar['consultant_qty_night'] : null;
        
        $qtyCum = (float)$ar['qty_cumulative'];
        $qtyCumConsultant = !empty($ar['consultant_qty_cumulative']) ? (float)$ar['consultant_qty_cumulative'] : null;
        
        $statusBadges = [];
        if($ar['status_ongoing']) $statusBadges[] = '<span class="badge bg-primary">در حال انجام</span>';
        if($ar['status_stopped']) $statusBadges[] = '<span class="badge bg-warning">متوقف</span>';
        if($ar['status_finished']) $statusBadges[] = '<span class="badge bg-success">اتمام</span>';
        $statusDisplay = implode(' ', $statusBadges) ?: '-';
    ?>
    <tr>
        <td class="fw-bold"><?= htmlspecialchars($ar['activity_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($ar['work_front'] ?? '') ?></td>
        <td><?= htmlspecialchars($ar['location_facade'] ?? '') ?></td>
        <td><?= htmlspecialchars($ar['vol_total'] ?? '') ?></td>
        
        <!-- Day Quantity -->
        <td>
            <?php if($qtyDayConsultant !== null): ?>
                <span class="fraction-display">
                    <span class="fraction-numerator crossed-out"><?= number_format($qtyDay, 2) ?></span>
                    <span class="fraction-line"></span>
                    <span class="fraction-denominator"><?= number_format($qtyDayConsultant, 2) ?></span>
                </span>
            <?php else: ?>
                <?= number_format($qtyDay, 2) ?>
            <?php endif; ?>
        </td>
        
        <!-- Night Quantity -->
        <td>
            <?php if($qtyNightConsultant !== null): ?>
                <span class="fraction-display">
                    <span class="fraction-numerator crossed-out"><?= number_format($qtyNight, 2) ?></span>
                    <span class="fraction-line"></span>
                    <span class="fraction-denominator"><?= number_format($qtyNightConsultant, 2) ?></span>
                </span>
            <?php else: ?>
                <?= number_format($qtyNight, 2) ?>
            <?php endif; ?>
        </td>
        
        <!-- Cumulative Quantity -->
        <td>
            <?php if($qtyCumConsultant !== null): ?>
                <span class="fraction-display">
                    <span class="fraction-numerator crossed-out"><?= number_format($qtyCum, 2) ?></span>
                    <span class="fraction-line"></span>
                    <span class="fraction-denominator"><?= number_format($qtyCumConsultant, 2) ?></span>
                </span>
            <?php else: ?>
                <?= number_format($qtyCum, 2) ?>
            <?php endif; ?>
        </td>
        
        <td><?= htmlspecialchars($ar['unit'] ?? 'متر مربع') ?></td>
        <td><?= $statusDisplay ?></td>
        <td class="small"><?= htmlspecialchars($ar['contractor_fa_name'] ?? '') ?></td>
        <td><span class="badge bg-secondary"><?= htmlspecialchars($ar['block_name'] ?? '') ?></span></td>
        <td><?= getJDate($ar['report_date']) ?></td>
    </tr>
    <?php endforeach; ?>
</tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Materials Table -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card chart-card">
                <div class="card-header bg-white border-0"><h6 class="mb-0">آخرین مصالح (20 مورد اخیر)</h6></div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="materialsTable" class="table table-sm table-striped mb-0" style="font-size: 0.85rem;">
                            <thead class="table-light"><tr><th>نوع</th><th>نام مصالح</th><th>مقدار</th><th>واحد</th><th>پیمانکار</th><th>تاریخ</th></tr></thead>
                            <tbody>
                                <?php foreach ($matTableRows as $mr): 
                                    $qtyContractor = (float)$mr['quantity'];
                                    $qtyConsultant = !empty($mr['consultant_quantity']) ? (float)$mr['consultant_quantity'] : null;
                                    $cls = ($mr['type'] === 'IN') ? 'bg-success text-white' : 'bg-danger text-white';
                                ?>
                                <tr>
                                    <td><span class="badge <?= $cls ?>"><?= ($mr['type']=='IN'?'ورود':'خروج') ?></span></td>
                                    <td><?= htmlspecialchars($mr['material_name']) ?></td>
                                    <td>
                                        <?php if($qtyConsultant !== null): ?>
                                            <span class="fraction-display">
                                                <span class="fraction-numerator crossed-out"><?= number_format($qtyContractor, 2) ?></span>
                                                <span class="fraction-line"></span>
                                                <span class="fraction-denominator"><?= number_format($qtyConsultant, 2) ?></span>
                                            </span>
                                        <?php else: ?>
                                            <?= number_format($qtyContractor, 2) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($mr['unit']) ?></td>
                                    <td><?= htmlspecialchars($mr['contractor_fa_name']) ?></td>
                                    <td><?= getJDate($mr['report_date']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


</div>

<!-- Signature Modal -->
<div class="modal fade" id="signModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="signModalLabel">امضا</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="signImage" src="" class="img-fluid" style="max-height:300px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
        </div>
    </div>
</div>

<script>
    const opts = { chart: { fontFamily: 'Samim', toolbar: { show: true, tools: { download: true } } } };
    
    // Define Persian language for DataTables FIRST
    const faLang = { 
        "sSearch": "جستجو:", 
        "sLengthMenu": "نمایش _MENU_ ردیف", 
        "sInfo": "نمایش _START_ تا _END_ از _TOTAL_ ردیف", 
        "sInfoEmpty": "نمایش 0 تا 0 از 0 ردیف",
        "sInfoFiltered": "(فیلتر شده از _MAX_ ردیف)",
        "sZeroRecords": "رکوردی یافت نشد",
        "oPaginate": { "sNext": "بعدی", "sPrevious": "قبلی" } 
    };
    
    // 1. Status Donut
    new ApexCharts(document.querySelector("#statusChart"), { 
        ...opts,
        series: <?= $statsJson ?>,
        chart: { type: 'donut', height: 250 },
        labels: ['منتظر بررسی', 'تایید شده', 'رد شده', 'پیش‌نویس'],
        colors: ['#ffc107', '#198754', '#dc3545', '#6c757d'],
        legend: { position: 'bottom' }
    }).render();
    
    // 2. Trend Line
    new ApexCharts(document.querySelector("#trendChart"), { 
        ...opts,
        series: [{ name: 'تعداد', data: <?= $trendCountsJson ?> }],
        chart: { type: 'area', height: 250 },
        xaxis: { categories: <?= $trendDatesJson ?> },
        colors: ['#4e73df'],
        stroke: { curve: 'smooth' }
    }).render();
    
    // 3. Manpower Bar
    new ApexCharts(document.querySelector("#manpowerChart"), { 
        ...opts,
        series: [{ name: 'نفر-روز', data: <?= $manpowerCounts ?> }],
        chart: { type: 'bar', height: 300 },
        plotOptions: { bar: { horizontal: true, distributed: true } },
        xaxis: { categories: <?= $manpowerLabels ?> },
        legend: { show: false }
    }).render();
    
    // 4. Equipment Bar
    new ApexCharts(document.querySelector("#equipmentChart"), { 
        ...opts,
        series: [{ name: 'دستگاه-روز', data: <?= $equipmentCounts ?> }],
        chart: { type: 'bar', height: 300 },
        plotOptions: { bar: { horizontal: true, distributed: true } },
        xaxis: { categories: <?= $equipmentLabels ?> },
        legend: { show: false }
    }).render();
    
    // 5. Activity Bar
    new ApexCharts(document.querySelector("#activityChart"), { 
        ...opts,
        series: [{ name: 'تعداد', data: <?= $activityCounts ?> }],
        chart: { type: 'bar', height: 300 },
        plotOptions: { bar: { horizontal: false, distributed: true } },
        xaxis: { categories: <?= $activityLabels ?>, labels: { rotate: -45 } },
        legend: { show: false }
    }).render();
    
    // 6. Materials Chart
    new ApexCharts(document.querySelector("#matChart"), { 
        ...opts,
        series: [
            { name: 'ورود', data: <?= json_encode($matSeriesIn) ?> },
            { name: 'خروج', data: <?= json_encode($matSeriesOut) ?> }
        ],
        chart: { type: 'bar', height: 320, stacked: true },
        plotOptions: { bar: { horizontal: true } },
        xaxis: { categories: <?= json_encode($matLabels) ?> },
        colors: ['#198754', '#dc3545'],
        legend: { position: 'top' }
    }).render();

    // 7. Activity Performance Chart
    new ApexCharts(document.querySelector("#actPerformanceChart"), { 
        ...opts,
        series: [
            { name: 'روز', data: <?= $actPerfDay ?> },
            { name: 'شب', data: <?= $actPerfNight ?> },
            { name: 'تاکنون', data: <?= $actPerfCum ?> }
        ],
        chart: { 
            type: 'bar', 
            height: 350,
            toolbar: { show: true }
        },
        plotOptions: { 
            bar: { 
                horizontal: false,
                columnWidth: '70%',
                dataLabels: { position: 'top' }
            } 
        },
        dataLabels: {
            enabled: true,
            offsetY: -20,
            style: { 
                fontSize: '10px',
                colors: ['#333']
            }
        },
        xaxis: { 
            categories: <?= $actPerfLabels ?>,
            labels: { 
                rotate: -45,
                style: { fontSize: '11px' }
            }
        },
        yaxis: {
            title: { text: 'مقدار' }
        },
        colors: ['#4e73df', '#1cc88a', '#f6c23e'],
        legend: { 
            position: 'top',
            horizontalAlign: 'right'
        },
        tooltip: {
            y: { 
                formatter: (val) => val ? val.toFixed(2) : '0.00'
            }
        }
    }).render();

    function viewSign(src, title) { 
        if(src) { 
            document.getElementById('signImage').src = src; 
            document.getElementById('signModalLabel').innerText = 'امضای ' + title; 
            new bootstrap.Modal(document.getElementById('signModal')).show(); 
        } else {
            alert('امضایی برای نمایش موجود نیست.');
        }
    }

    // Initialize DataTables after DOM is ready
    $(document).ready(function() {
        // Main Reports Table
        $('#reportsTable').DataTable({ 
            language: faLang, 
            order: [[0, 'desc']],
            pageLength: 25
        });
        
        // Materials Table
        $('#materialsTable').DataTable({ 
            language: faLang, 
            order: [[5, 'desc']],
            pageLength: 20,
            searching: true
        });
        
        // Activities Detail Table
        $('#activitiesDetailTable').DataTable({ 
            language: faLang, 
            order: [[11, 'desc']], // Sort by date
            pageLength: 20,
            searching: true,
            scrollX: true
        });
        
        // Initialize Jalali Datepicker
        jalaliDatepicker.startWatch();
    });
</script>
</body>
</html>