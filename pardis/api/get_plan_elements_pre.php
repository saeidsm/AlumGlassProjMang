<?php
// /pardis/api/get_plan_elements_pre.php (FINAL, COMPLETE, AND CORRECTED)
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Authentication required']));
}

$plan_file = $_GET['plan'] ?? null;
if (empty($plan_file)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Plan file parameter is required.']));
}

try {
    $pdo = getProjectDBConnection('pardis');

    // This query is the most robust way to get the single, highest-priority status for each element.
    $sql = "
        WITH RankedInspections AS (
            SELECT
                i.element_id,
                i.status,
                i.pre_inspection_log,
                i.history_log,
                ROW_NUMBER() OVER(PARTITION BY i.element_id ORDER BY 
                       CASE i.status
                        WHEN 'Opening Rejected' THEN 1 WHEN 'Opening Disputed' THEN 2
                        WHEN 'Request to Open' THEN 3 WHEN 'Panel Opened' THEN 4
                        WHEN 'Opening Approved' THEN 5 WHEN 'Reject' THEN 6
                        WHEN 'Repair' THEN 7

                        WHEN 'Pre-Inspection Complete' THEN 8
                        WHEN 'OK' THEN 9 
                        ELSE 10
                    END ASC, 
                    i.created_at DESC
                ) as rn
            FROM inspections i
            WHERE i.element_id IN (SELECT element_id FROM elements WHERE plan_file = ?)
        )
        SELECT
            e.*,
            ri.status,
            ri.pre_inspection_log,
            ri.history_log
        FROM elements e
        LEFT JOIN RankedInspections ri ON e.element_id = ri.element_id AND ri.rn = 1
        WHERE e.plan_file = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plan_file, $plan_file]);

    $resultsByElementId = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $resultsByElementId[$row['element_id']] = $row;
    }

    $elements_with_data = [];
    foreach ($resultsByElementId as $element_id => $row) {
        $elements_with_data[$element_id] = [
            'type'       => $row['element_type'],
            'status'     => $row['status'] ?? 'Pending',
            'pre_inspection_log' => json_decode($row['pre_inspection_log'] ?? '[]'),
            'history_log' => json_decode($row['history_log'] ?? '[]'),
            'floor'      => $row['floor_level'],
            'axis'       => $row['axis_span'],
            'contractor' => $row['contractor'],
            'block'      => $row['block'],
            'zoneName'   => $row['zone_name']
        ];
    }

    echo json_encode($elements_with_data);
} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error get_plan_elements_pre.php: " . $e->getMessage());
    exit(json_encode(['error' => 'Database query failed.']));
}
