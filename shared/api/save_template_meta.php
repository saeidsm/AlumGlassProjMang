<?php
// /shared/api/save_template_meta.php
// This script ONLY saves the template's name and element type. It does NOT touch items or stages.

header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/project_context.php';
secureSession();
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Authentication required']));
}

if (!in_array($_SESSION['role'], ['admin', 'superuser'])) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Access Denied']));
}
require_once __DIR__ . '/../../includes/security.php';
requireCsrf();

try {
    $pdo = getProjectDB();

    $template_id = $_POST['template_id'] ?: null;
    $template_name = trim($_POST['template_name'] ?? '');
    $element_type = trim($_POST['element_type'] ?? '');

    if (empty($template_name) || empty($element_type)) {
        throw new Exception('نام قالب و نوع المان الزامی است.');
    }

    $pdo->beginTransaction();

    if ($template_id) { // UPDATE existing template
        $stmt = $pdo->prepare("UPDATE checklist_templates SET template_name = ?, element_type = ? WHERE template_id = ?");
        $stmt->execute([$template_name, $element_type, $template_id]);
    } else { // INSERT new template
        $stmt = $pdo->prepare("INSERT INTO checklist_templates (template_name, element_type) VALUES (?, ?)");
        $stmt->execute([$template_name, $element_type]);
        $template_id = $pdo->lastInsertId();
    }

    $pdo->commit();
    echo json_encode([
        'status' => 'success',
        'message' => 'نام و نوع قالب با موفقیت ذخیره شد.',
        'template_id' => $template_id // Send back the new ID
    ]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'خطای پایگاه داده: ' . $e->getMessage()]);
}
