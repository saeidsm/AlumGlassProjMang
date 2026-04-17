<?php
// /pardis/api/get_stage_specific_status.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();

$plan_file = $_GET['plan'] ?? null;
$element_type = $_GET['type'] ?? null;
$stage_id = $_GET['stage'] ?? null;

if (!$plan_file || !$element_type || !$stage_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing required parameters.']));
}

try {
    $pdo = getProjectDBConnection('pardis');
    // and joins it back to the main elements table.
    $stmt = $pdo->prepare("
        SELECT
            e.element_id,
            e.geometry_json, -- This is not needed by the JS but good to have for debugging
            e.element_type,
            e.floor_level,
            e.area_sqm,
            e.width_cm,
            e.height_cm,
            -- Determine the status for this specific stage, defaulting to 'Pending' if no inspection exists for it.
            COALESCE(i.final_status, 'Pending') as final_status
        FROM elements e
        LEFT JOIN (
            -- Subquery to find the latest inspection status ONLY for the selected stage
            SELECT
                element_id,
                CASE
                    WHEN status = 'OK' THEN 'OK'
                    WHEN status = 'Reject' THEN 'Reject'
                    WHEN status = 'Repair' THEN 'Repair'
                    WHEN status = 'Pre-Inspection Complete' THEN 'Pre-Inspection Complete'
                    WHEN status = 'Awaiting Re-inspection' THEN 'Awaiting Re-inspection'
                    WHEN status IS NULL THEN 'Pending'
                    ELSE 'Pending' -- Default for elements with no inspections
                END as final_status
            FROM inspections
            WHERE (element_id, created_at) IN (
                SELECT element_id, MAX(created_at)
                FROM inspections
                WHERE stage_id = :stage_id_sub
                GROUP BY element_id
            ) AND stage_id = :stage_id_main
        ) AS i ON e.element_id = i.element_id
        WHERE e.plan_file = :plan_file 
          AND e.element_type = :element_type
          AND e.geometry_json IS NOT NULL
    ");

    $stmt->execute([
        ':stage_id_sub' => $stage_id,
        ':stage_id_main' => $stage_id,
        ':plan_file' => $plan_file,
        ':element_type' => $element_type
    ]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error in get_stage_specific_status.php: " . $e->getMessage());
    exit(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}
