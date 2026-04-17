<?php
// /pardis/api/get_statuses_for_stage.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();

$stage_id = filter_input(INPUT_GET, 'stage_id', FILTER_VALIDATE_INT);
$plan_file = filter_input(INPUT_GET, 'plan', FILTER_DEFAULT);

if ($stage_id === false || !$plan_file) {
    http_response_code(400);
    exit(json_encode(['error' => 'Valid Stage ID and Plan File are required.']));
}

try {
    $pdo = getProjectDBConnection('pardis');

    // This query finds the status for a specific stage, but only for elements on the current plan
    $sql = "
        SELECT 
            i.element_id,
            COALESCE(i.overall_status, i.status, 'Pending') as status
        FROM inspections i
        INNER JOIN elements e ON i.element_id = e.element_id
        WHERE i.stage_id = ? AND e.plan_file = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$stage_id, $plan_file]);

    // Fetch into a simple key->value map of element_id => status
    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error get_statuses_for_stage.php: " . $e->getMessage());
    exit(json_encode(['error' => 'Database query failed.']));
}
