<?php
// ghom/api/get_permit_element_data.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';

if (!isLoggedIn()) { http_response_code(403); exit; }

$permitId = $_GET['permit_id'] ?? 0;
$elementId = $_GET['element_id'] ?? '';

$pdo = getProjectDBConnection('ghom');

// 1. Fetch Checklist Data
$sqlData = "SELECT checklist_data FROM permit_elements WHERE permit_id = ?";
$paramsData = [$permitId];
if ($elementId !== 'ALL') {
    $sqlData .= " AND element_id = ?";
    $paramsData[] = $elementId;
} else {
    $sqlData .= " LIMIT 1";
}
$stmt = $pdo->prepare($sqlData);
$stmt->execute($paramsData);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$checklistData = $row && $row['checklist_data'] ? json_decode($row['checklist_data'], true) : null;

// 2. Fetch Permit Info (Sub-Contractor & Code Num)
$stmtPermit = $pdo->prepare("SELECT contractor_name, code_num FROM permits WHERE id = ?");
$stmtPermit->execute([$permitId]);
$permitRow = $stmtPermit->fetch(PDO::FETCH_ASSOC);

// 3. Fetch Technical Meta + MAIN CONTRACTOR from elements table
$meta = [
    'block' => '-', 'zone' => '-', 'floor' => '-', 'axis' => '-',
    'contractor' => $permitRow['contractor_name'] ?? 'تعیین نشده', // Sub Contractor (Dropdown)
    'main_contractor' => 'نامشخص', // Main Contractor (From DB Elements)
    'code_num' => $permitRow['code_num'] ?? '',
    'element_count' => 0
];

try {
    // Count
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM permit_elements WHERE permit_id = ?");
    $stmtCount->execute([$permitId]);
    $meta['element_count'] = $stmtCount->fetchColumn();

    // Get Data
    $stmtMeta = $pdo->prepare("
        SELECT DISTINCT e.block, e.zone_name, e.floor_level, e.axis_span, e.contractor
        FROM elements e
        JOIN permit_elements pe ON e.element_id = pe.element_id
        WHERE pe.permit_id = ?
    ");
    $stmtMeta->execute([$permitId]);
    $rows = $stmtMeta->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        // Main Contractor (Take first found)
        if (!empty($rows[0]['contractor'])) {
            $meta['main_contractor'] = $rows[0]['contractor'];
        }

        // Aggregate Data
        $blocks = array_unique(array_column($rows, 'block'));
        $meta['block'] = implode(' / ', array_filter($blocks));

        $zones = array_unique(array_column($rows, 'zone_name'));
        $meta['zone'] = implode(' / ', array_filter($zones));

        $floors = array_unique(array_column($rows, 'floor_level'));
        natsort($floors); 
        $meta['floor'] = implode('، ', array_filter($floors));

        $allAxes = [];
        foreach ($rows as $r) {
            if (!empty($r['axis_span'])) {
                $parts = preg_split('/[\-\s]+/', $r['axis_span']);
                foreach ($parts as $p) { $p = trim($p); if ($p) $allAxes[] = $p; }
            }
        }
        if (!empty($allAxes)) {
            $allAxes = array_unique($allAxes);
            natsort($allAxes); 
            $meta['axis'] = (count($allAxes) > 1) ? (reset($allAxes) . " - " . end($allAxes)) : reset($allAxes);
        }
    }
} catch (Exception $e) {}

echo json_encode([
    'checklist_data' => $checklistData,
    'meta_info' => $meta
]);