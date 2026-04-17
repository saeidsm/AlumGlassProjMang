<?php
// /shared/api/get_stages.php (FINAL)
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/project_context.php';
secureSession();
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Authentication required']));
}

$element_type = $_GET['type'] ?? null;
if (!$element_type) {
    exit(json_encode([]));
} // Return empty array if no type

try {
    $pdo = getProjectDB();
    $stmt = $pdo->prepare(
        "SELECT s.stage_id, s.stage FROM inspection_stages s
         JOIN checklist_templates t ON s.template_id = t.template_id
         WHERE t.element_type = ? ORDER BY s.display_order ASC"
    );
    $stmt->execute([$element_type]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    exit(json_encode(['error' => 'Database error.']));
}
