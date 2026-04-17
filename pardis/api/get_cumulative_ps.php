<?php
// pardis/api/get_cumulative_ps.php - Calculate cumulative quantity from DB
require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/jdf.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
require_once __DIR__ . '/../../includes/security.php';
requireCsrf();

$pdo = getProjectDBConnection('pardis');

function convert_date($jDate) {
    if(empty($jDate)) return null;
    $p = explode('/', $jDate);
    if(count($p) !== 3) return null;
    $g = jalali_to_gregorian($p[0], $p[1], $p[2]);
    return implode('-', $g);
}

try {
    $action = $_POST['action'] ?? '';
    
    if ($action !== 'get_cumulative') {
        throw new Exception('Invalid action');
    }
    
    $activity_id = $_POST['activity_id'] ?? '';
    $work_front = $_POST['work_front'] ?? '';
    $location_facade = $_POST['location_facade'] ?? '';
    $report_date_jalali = $_POST['report_date'] ?? '';
    $exclude_report_id = $_POST['report_id'] ?? null;
    
    if (empty($activity_id)) {
        echo json_encode(['success' => true, 'cumulative' => 0]);
        exit;
    }
    
    $report_date = convert_date($report_date_jalali);
    if (!$report_date) {
        throw new Exception('Invalid date format');
    }
    
    // Calculate sum of (day + night) for all matching activities up to this date
    $sql = "
        SELECT 
            SUM(
                COALESCE(NULLIF(dra.consultant_qty_day, 0), dra.qty_day) +
                COALESCE(NULLIF(dra.consultant_qty_night, 0), dra.qty_night)
            ) as cumulative
        FROM ps_daily_report_activities dra
        JOIN ps_daily_reports dr ON dra.report_id = dr.id
        WHERE dra.activity_id = ?
        AND dra.work_front = ?
        AND dra.location_facade = ?
        AND dr.report_date < ?
        AND dr.status IN ('Submitted', 'Approved')
    ";
    
    $params = [$activity_id, $work_front, $location_facade, $report_date];
    
    // Exclude current report to avoid double counting
    if (!empty($exclude_report_id)) {
        $sql .= " AND dr.id != ?";
        $params[] = $exclude_report_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $cumulative = (float)($stmt->fetchColumn() ?: 0);
    
    echo json_encode([
        'success' => true, 
        'cumulative' => $cumulative,
        'debug' => [
            'activity_id' => $activity_id,
            'work_front' => $work_front,
            'location' => $location_facade,
            'date' => $report_date
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}