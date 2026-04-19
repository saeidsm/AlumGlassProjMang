<?php
// /pardis/api/get_scaffolding_for_plan.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();

if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

$plan_file = $_GET['plan'] ?? null;
if (!$plan_file) {
    http_response_code(400);
    exit(json_encode(['error' => 'Plan file is required.']));
}

try {
    $pdo = getProjectDBConnection('pardis');

    $sql = "SELECT 
                plan_file,
                drawing_json,
                total_length_m,
                total_area_sqm,
                created_at,
                updated_at
            FROM scaffolding_drawings
            WHERE plan_file = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plan_file]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode($result);
    } else {
        echo json_encode([
            'plan_file' => $plan_file,
            'drawing_json' => null,
            'total_length_m' => 0,
            'total_area_sqm' => 0
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}
?>