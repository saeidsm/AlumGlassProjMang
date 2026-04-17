<?php
// /pardis/api/get_element_statistics.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();

if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

$plan_file = $_GET['plan'] ?? null;

try {
    $pdo = getProjectDBConnection('pardis');

    $whereClause = $plan_file ? "WHERE plan_file = ?" : "";
    $params = $plan_file ? [$plan_file] : [];

    $sql = "SELECT 
                element_type,
                COUNT(*) as element_count,
                SUM(area_sqm) as total_area_sqm,
                SUM(width_cm * height_cm / 10000) as calculated_area_sqm,
                AVG(width_cm) as avg_width_cm,
                AVG(height_cm) as avg_height_cm,
                MIN(width_cm) as min_width_cm,
                MAX(width_cm) as max_width_cm,
                MIN(height_cm) as min_height_cm,
                MAX(height_cm) as max_height_cm
            FROM elements
            $whereClause
            GROUP BY element_type
            ORDER BY element_type";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get scaffolding data if plan_file is specified
    $drawingLayersData = [];
if ($plan_file) {
    $drawingLayersSql = "SELECT layer_type, total_length_m, total_area_sqm 
                         FROM plan_drawings 
                         WHERE plan_file = ?";
    $drawingLayersStmt = $pdo->prepare($drawingLayersSql);
    $drawingLayersStmt->execute([$plan_file]);
    $results = $drawingLayersStmt->fetchAll(PDO::FETCH_ASSOC);
    // Re-key the array by layer_type for easier access in JavaScript
    foreach ($results as $row) {
        $drawingLayersData[$row['layer_type']] = [
            'total_length_m' => $row['total_length_m'],
            'total_area_sqm' => $row['total_area_sqm']
        ];
    }
}

    echo json_encode([
        'element_statistics' => $statistics,
      
         'drawing_layers' => $drawingLayersData,
        'plan_file' => $plan_file
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}
?>