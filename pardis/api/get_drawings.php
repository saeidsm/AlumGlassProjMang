<?php
// /pardis/api/get_drawings.php
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
    $sql = "SELECT layer_type, drawing_json FROM plan_drawings WHERE plan_file = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plan_file]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize results by layer_type for easier use on the frontend
    $layers = [];
    foreach ($results as $row) {
        $layers[$row['layer_type']] = json_decode($row['drawing_json'], true);
    }

    echo json_encode($layers);

} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}
?>