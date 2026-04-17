<?php
// public_html/pardis/zirsazi_print.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'superuser', 'user', 'supervisor', 'planner'])) {
    http_response_code(403);
    exit('Access Denied');
}

$expected_project_key = 'pardis';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}

// Get filter parameters
$building_id = $_GET['building_id'] ?? '';
$part_id = $_GET['part_id'] ?? '';
$model = $_GET['model'] ?? '';
$type = $_GET['type'] ?? '';

// Fetch data
try {
    $pdo = getProjectDBConnection();
    
    $sql = "SELECT 
                zb.id,
                zb.model,
                zb.type,
                zb.part1_2_qty,
                zb.part1_qty,
                zb.part2_qty,
                zb.part3_qty,
                zb.part4_qty,
                zb.part5_qty,
                zb.part6_qty,
                mm.unit_weight,
                pl_b.building_name as building,
                pl_p.part_name as part,
                zb.building_id,
                zb.part_id,
                COALESCE(
                    (SELECT SUM(quantity) 
                     FROM packing_lists pl 
                     WHERE TRIM(pl.material_type) = TRIM(zb.type)), 0
                ) as total_received,
                COALESCE(
                    (SELECT current_stock 
                     FROM warehouse_inventory wi 
                     WHERE TRIM(wi.material_type) = TRIM(zb.type) 
                     LIMIT 1), 0
                ) as warehouse_stock
            FROM zirsazi_boq zb
            LEFT JOIN materials_master mm ON TRIM(zb.type) = TRIM(mm.item_name) AND mm.category = 'زیرسازی'
            LEFT JOIN project_locations pl_b ON zb.building_id = pl_b.id
            LEFT JOIN project_locations pl_p ON zb.part_id = pl_p.id
            WHERE 1=1";
    
    $params = [];
    
    if ($building_id) {
        $sql .= " AND zb.building_id = ?";
        $params[] = $building_id;
    }
    if ($part_id) {
        $sql .= " AND zb.part_id = ?";
        $params[] = $part_id;
    }
    if ($model) {
        $sql .= " AND zb.model = ?";
        $params[] = $model;
    }
    if ($type) {
        $sql .= " AND zb.type = ?";
        $params[] = $type;
    }
    
    $sql .= " ORDER BY pl_b.building_name, zb.model, zb.type";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    logError("Error fetching zirsazi data for print: " . $e->getMessage());
    exit('Error fetching data');
}

// Group data by building
$groupedData = [];
foreach ($data as $row) {
    $building = $row['building'] ?: 'بدون ساختمان';
    if (!isset($groupedData[$building])) {
        $groupedData[$building] = [];
    }
    $groupedData[$building][] = $row;
}

// Get filter names for display
$filterInfo = [];
if ($building_id) {
    $stmt = $pdo->prepare("SELECT building_name FROM project_locations WHERE id = ?");
    $stmt->execute([$building_id]);
    $bname = $stmt->fetchColumn();
    if ($bname) $filterInfo[] = "ساختمان: $bname";
}
if ($part_id) {
    $stmt = $pdo->prepare("SELECT part_name FROM project_locations WHERE id = ?");
    $stmt->execute([$part_id]);
    $pname = $stmt->fetchColumn();
    if ($pname) $filterInfo[] = "قسمت: $pname";
}
if ($model) $filterInfo[] = "Model: $model";
if ($type) $filterInfo[] = "Type: $type";
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش چاپی زیرسازی - پروژه دانشگاه خاتم پردیس</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 9pt; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
            .page-break { page-break-before: always; }
            @page { 
                margin: 0.5cm;
                size: A4 landscape;
            }
        }
        
        body {
            font-family: Tahoma, Arial, sans-serif;
            background-color: #f8f9fa;
        }
        
        .print-container {
            background-color: white;
            padding: 20px;
            margin: 20px auto;
            max-width: 100%;
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 15px;
        }
        
        .report-title {
            font-size: 24px;
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 5px;
        }
        
        .report-subtitle {
            font-size: 18px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .report-info {
            font-size: 12px;
            color: #666;
        }
        
        .building-section {
            margin-bottom: 40px;
        }
        
        .building-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 12px 20px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 8px 8px 0 0;
            margin-top: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            background-color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        th {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            font-weight: bold;
            padding: 10px 4px;
            border: 1px solid #0a58ca;
            text-align: center;
            font-size: 7.5pt;
            white-space: nowrap;
        }
        
        td {
            border: 1px solid #dee2e6;
            padding: 8px 4px;
            text-align: center;
            vertical-align: middle;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        tbody tr:hover {
            background-color: #e7f3ff;
        }
        
        .text-danger { color: #dc3545 !important; font-weight: bold; }
        .text-success { color: #198754 !important; font-weight: bold; }
        .text-warning { color: #ffc107 !important; font-weight: bold; }
        
        .table-info {
            background-color: #d1ecf1 !important;
        }
        
        .table-primary {
            background-color: #cfe2ff !important;
        }
        
        .progress-bar-container {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-bar {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 10px;
            transition: width 0.3s;
        }
        
        .progress-bar.bg-success {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
        }
        
        .progress-bar.bg-info {
            background: linear-gradient(135deg, #0dcaf0 0%, #31d2f2 100%);
        }
        
        .progress-bar.bg-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ffcd39 100%);
            color: #000;
        }
        
        .btn-print-fixed {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-close-fixed {
            position: fixed;
            top: 20px;
            left: 180px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .filter-info {
            background: linear-gradient(135deg, #e7f3ff 0%, #cfe2ff 100%);
            border: 2px solid #0d6efd;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            font-size: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filter-badge {
            display: inline-block;
            background-color: #0d6efd;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            margin: 3px;
            font-size: 11px;
        }
        
        .summary-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .summary-box .count {
            font-size: 32px;
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 5px;
        }
        
        .summary-box .label {
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="btn btn-primary btn-lg no-print btn-print-fixed">
        <i class="bi bi-printer-fill"></i> چاپ گزارش
    </button>
    
    <button onclick="window.close()" class="btn btn-secondary btn-lg no-print btn-close-fixed">
        <i class="bi bi-x-circle"></i> بستن
    </button>
    
    <div class="print-container">
        <div class="report-header">
            <div class="report-title">گزارش وضعیت زیرسازی</div>
            <div class="report-subtitle">پروژه دانشگاه خاتم پردیس</div>
            <div class="report-info">
                <strong>تاریخ گزارش:</strong> <?php echo date('Y/m/d'); ?> | 
                <strong>ساعت:</strong> <?php echo date('H:i'); ?>
                <?php if (!empty($filterInfo)): ?>
                | <strong>فیلترشده</strong>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($filterInfo)): ?>
        <div class="filter-info no-print">
            <strong><i class="bi bi-funnel-fill"></i> فیلترهای اعمال شده:</strong><br>
            <?php foreach ($filterInfo as $info): ?>
                <span class="filter-badge"><?php echo htmlspecialchars($info); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="row mb-4 no-print">
            <div class="col-md-12">
                <div class="summary-box">
                    <div class="count"><?php echo count($data); ?></div>
                    <div class="label">تعداد کل آیتم‌ها در گزارش</div>
                </div>
            </div>
        </div>
        
        <?php if (empty($groupedData)): ?>
        <div class="alert alert-warning text-center" style="margin: 50px 0;">
            <i class="bi bi-exclamation-triangle fs-1"></i>
            <h4 class="mt-3">هیچ داده‌ای برای نمایش وجود ندارد</h4>
            <p>لطفاً فیلترهای خود را تغییر دهید یا به صفحه قبل بازگردید.</p>
        </div>
        <?php else: ?>
            <?php foreach ($groupedData as $building => $rows): ?>
            <div class="building-section <?php echo $building !== array_key_first($groupedData) ? 'page-break' : ''; ?>">
                <div class="building-header">
                    <i class="bi bi-building"></i> <?php echo htmlspecialchars($building); ?> 
                    <span style="font-size: 14px; opacity: 0.9;">(<?php echo count($rows); ?> آیتم)</span>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 50px;">ردیف</th>
                            <th rowspan="2" style="width: 60px;">Model</th>
                            <th rowspan="2" style="width: 80px;">Type</th>
                            <th colspan="7">Ordered Qty</th>
                            <th rowspan="2" style="width: 50px;">Total<br>Ordered</th>
                            <th rowspan="2" style="width: 50px;">Total<br>Received</th>
                            <th rowspan="2" style="width: 50px;">موجودی<br>انبار</th>
                            <th rowspan="2" style="width: 50px;">Remaining</th>
                            <th rowspan="2" style="width: 70px;">Progress</th>
                            <th rowspan="2" style="width: 45px;">Weight<br>(kg/pc)</th>
                            <th rowspan="2" style="width: 55px;">Total<br>Weight<br>(kg)</th>
                            <th rowspan="2" style="width: 70px;">Part</th>
                        </tr>
                        <tr>
                            <th style="width: 35px;">P1&2</th>
                            <th style="width: 35px;">P1</th>
                            <th style="width: 35px;">P2</th>
                            <th style="width: 35px;">P3</th>
                            <th style="width: 35px;">P4</th>
                            <th style="width: 35px;">P5</th>
                            <th style="width: 35px;">P6</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rowNum = 1;
                        foreach ($rows as $row): 
                            $part12 = intval($row['part1_2_qty']);
                            $part1 = intval($row['part1_qty']);
                            $part2 = intval($row['part2_qty']);
                            $part3 = intval($row['part3_qty']);
                            $part4 = intval($row['part4_qty']);
                            $part5 = intval($row['part5_qty']);
                            $part6 = intval($row['part6_qty']);
                            
                            $totalOrdered = $part12 + $part1 + $part2 + $part3 + $part4 + $part5 + $part6;
                            $totalReceived = intval($row['total_received']);
                            $warehouseStock = intval($row['warehouse_stock']);
                            $remaining = $totalOrdered - $totalReceived;
                            $progress = $totalOrdered > 0 ? round(($totalReceived / $totalOrdered) * 100, 0) : 0;
                            $unitWeight = floatval($row['unit_weight']);
                            $totalWeight = round($totalReceived * $unitWeight, 2);
                            
                            $progressClass = $progress == 100 ? 'bg-success' : ($progress > 50 ? 'bg-info' : 'bg-warning');
                            $remainingClass = $remaining > 0 ? 'text-danger' : 'text-success';
                        ?>
                        <tr>
                            <td><strong><?php echo $rowNum++; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['model']); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['type']); ?></strong></td>
                            <td><?php echo $part12; ?></td>
                            <td><?php echo $part1; ?></td>
                            <td><?php echo $part2; ?></td>
                            <td><?php echo $part3; ?></td>
                            <td><?php echo $part4; ?></td>
                            <td><?php echo $part5; ?></td>
                            <td><?php echo $part6; ?></td>
                            <td><strong><?php echo $totalOrdered; ?></strong></td>
                            <td class="table-info"><strong><?php echo $totalReceived; ?></strong></td>
                            <td class="table-primary"><strong><?php echo $warehouseStock; ?></strong></td>
                            <td class="<?php echo $remainingClass; ?>"><strong><?php echo $remaining; ?></strong></td>
                            <td>
                                <div class="progress-bar-container">
                                    <div class="progress-bar <?php echo $progressClass; ?>" style="width: <?php echo $progress; ?>%">
                                        <?php echo $progress; ?>%
                                    </div>
                                </div>
                            </td>
                            <td><?php echo number_format($unitWeight, 3); ?></td>
                            <td><?php echo number_format($totalWeight, 2); ?></td>
                            <td style="font-size: 7pt;"><?php echo htmlspecialchars($row['part'] ?: '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="text-center mt-5 text-muted" style="font-size: 10px; padding-top: 20px; border-top: 1px solid #dee2e6;">
            <p class="mb-1">این گزارش به صورت خودکار توسط سیستم مدیریت پروژه تولید شده است</p>
            <p>پروژه دانشگاه خاتم پردیس - سیستم مدیریت زیرسازی</p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>