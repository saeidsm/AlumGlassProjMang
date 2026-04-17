<?php
// /pardis/api/get_template_details.php (FINAL AND COMPLETE VERSION)

header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Authentication required']));
}

// --- Security and Validation ---
if (!in_array($_SESSION['role'], ['admin', 'superuser'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Access Denied']));
}

$template_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$template_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Template ID is required and must be an integer.']));
}

try {
    $pdo = getProjectDBConnection('pardis');
    $stmt_template = $pdo->prepare("SELECT * FROM checklist_templates WHERE template_id = ?");
    $stmt_template->execute([$template_id]);
    $template = $stmt_template->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        throw new Exception("Template not found.");
    }

    $stmt_stages = $pdo->prepare("SELECT stage_id, stage FROM inspection_stages WHERE template_id = ? ORDER BY display_order ASC");
    $stmt_stages->execute([$template_id]);
    $stages = $stmt_stages->fetchAll(PDO::FETCH_ASSOC);

    $stmt_items = $pdo->prepare("SELECT * FROM checklist_items WHERE template_id = ? ORDER BY item_order ASC");
    $stmt_items->execute([$template_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'template' => $template,
        'stages' => $stages,
        'items' => $items
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
