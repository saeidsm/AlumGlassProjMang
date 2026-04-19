<?php
// /public_html/ghom/api/get_existing_parts.php (CORRECTED VERSION)
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Authentication required']));
}

$baseElementId = $_GET['element_id'] ?? null;
if (empty($baseElementId)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Element ID is required.']));
}

try {
    $pdo = getProjectDBConnection('ghom');

    // This query simply finds all unique, non-null part names that have ever been recorded for an element.
    // It also joins to find the latest status for that part, defaulting to 'Pending' if no real stage is found.
    $sql = "
        SELECT 
            p.part_name,
            COALESCE(
                (
                    SELECT 
                        CASE 
                            WHEN i.overall_status = 'OK' THEN 'OK'
                            WHEN i.status = 'Reject' THEN 'Reject'
                            WHEN i.status = 'Awaiting Re-inspection' THEN 'Awaiting Re-inspection'
                            WHEN i.status = 'Repair' OR i.overall_status = 'Repair' THEN 'Repair'
                            ELSE 'In Progress'
                        END
                    FROM inspections i
                    WHERE i.element_id = p.element_id AND i.part_name = p.part_name AND i.stage_id > 0
                    ORDER BY i.inspection_id DESC
                    LIMIT 1
                ), 
                'Pending'
            ) AS status
        FROM 
            (SELECT DISTINCT element_id, part_name FROM inspections WHERE element_id = ? AND part_name IS NOT NULL) AS p
        ORDER BY 
            p.part_name;
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$baseElementId]);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no parts are found, it might be an older element with only a default inspection.
    if (empty($parts)) {
        // Check if there is at least one inspection record for this element
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM inspections WHERE element_id = ?");
        $check_stmt->execute([$baseElementId]);
        if ($check_stmt->fetchColumn() > 0) {
            // If records exist but none have a part_name, provide a default part to open the form.
            $parts = [['part_name' => 'default', 'status' => 'Pending']];
        }
    }

    echo json_encode($parts);

} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error get_existing_parts.php: " . $e->getMessage());
    exit(json_encode(['error' => 'Database query failed.']));
}