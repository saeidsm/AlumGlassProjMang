<?php
//shared/api/get_templates.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/project_context.php';
secureSession();
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Authentication required']));
}
try {
    $pdo = getProjectDB();
    $stmt = $pdo->query("SELECT template_id, template_name, element_type FROM checklist_templates ORDER BY template_name");
    echo json_encode($stmt->fetchAll());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
