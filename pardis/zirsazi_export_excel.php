<?php
// public_html/pardis/zirsazi_export_excel.php
require_once __DIR__ . '/../../sercon/bootstrap.php';

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
    logError("Error fetching zirsazi data for export: " . $e->getMessage());
    exit('Error fetching data');
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="zirsazi_status_' . date('Y-m-d_H-i-s') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Output BOM for UTF-8
echo "\xEF\xBB\xBF";

// Start HTML table for Excel
echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<style>';
echo 'table { border-collapse: collapse; width: 100%; }';
echo 'th, td { border: 1px solid #000; padding: 8px; text-align: center; }';
echo 'th { background-color: #4472C4; color: white; font-weight: bold; }';
echo '.building-header { background-color: #2F5597; color: white; font-weight: bold; text-align: left; }';
echo '.numeric { text-align: right; }';
echo '</style>';
echo '</head>';
echo '<body>';

echo '<h2>گزارش وضعیت زیرسازی - پروژه دانشگاه خاتم پردیس</h2>';
echo '<p>تاریخ گزارش: ' . date('Y-m-d H:i:s') . '</p>';

// Show filter info if any
if ($building_id || $part_id || $model || $type) {
    echo '<p><strong>فیلترهای اعمال شده:</strong> ';
    if ($building_id) {
        $stmt = $pdo->prepare("SELECT building_name FROM project_locations WHERE id = ?");
        $stmt->execute([$building_id]);
        $bname = $stmt->fetchColumn();
        if ($bname) echo "ساختمان: <strong>$bname</strong> | ";
    }
    if ($part_id) {
        $stmt = $pdo->prepare("SELECT part_name FROM project_locations WHERE id = ?");
        $stmt->execute([$part_id]);
        $pname = $stmt->fetchColumn();
        if ($pname) echo "قسمت: <strong>$pname</strong> | ";
    }
    if ($model) echo "Model: <strong>$model</strong> | ";
    if ($type) echo "Type: <strong>$type</strong>";
    echo '</p>';
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

// Output table for each building
foreach ($groupedData as $building => $rows) {
    echo '<h3>' . htmlspecialchars($building) . '</h3>';
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th rowspan="2">Model</th>';
    echo '<th rowspan="2">Type</th>';
    echo '<th colspan="7">Ordered Qty</th>';
    echo '<th rowspan="2">Total Ordered</th>';
    echo '<th rowspan="2">Total Received</th>';
    echo '<th rowspan="2">موجودی انبار</th>';
    echo '<th rowspan="2">Remaining</th>';
    echo '<th rowspan="2">Progress %</th>';
    echo '<th rowspan="2">Weight (kg/pc)</th>';
    echo '<th rowspan="2">Total Weight (kg)</th>';
    echo '<th rowspan="2">Part</th>';
    echo '</tr>';
    echo '<tr>';
    echo '<th>PART 1&2</th>';
    echo '<th>PART 1</th>';
    echo '<th>PART 2</th>';
    echo '<th>PART 3</th>';
    echo '<th>PART 4</th>';
    echo '<th>PART 5</th>';
    echo '<th>PART 6</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($rows as $row) {
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
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['model']) . '</td>';
        echo '<td><strong>' . htmlspecialchars($row['type']) . '</strong></td>';
        echo '<td class="numeric">' . $part12 . '</td>';
        echo '<td class="numeric">' . $part1 . '</td>';
        echo '<td class="numeric">' . $part2 . '</td>';
        echo '<td class="numeric">' . $part3 . '</td>';
        echo '<td class="numeric">' . $part4 . '</td>';
        echo '<td class="numeric">' . $part5 . '</td>';
        echo '<td class="numeric">' . $part6 . '</td>';
        echo '<td class="numeric"><strong>' . $totalOrdered . '</strong></td>';
        echo '<td class="numeric"><strong>' . $totalReceived . '</strong></td>';
        echo '<td class="numeric"><strong>' . $warehouseStock . '</strong></td>';
        echo '<td class="numeric"><strong>' . $remaining . '</strong></td>';
        echo '<td class="numeric">' . $progress . '%</td>';
        echo '<td class="numeric">' . number_format($unitWeight, 3) . '</td>';
        echo '<td class="numeric">' . number_format($totalWeight, 2) . '</td>';
        echo '<td>' . htmlspecialchars($row['part'] ?: '-') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '<br><br>';
}

echo '</body>';
echo '</html>';
?>