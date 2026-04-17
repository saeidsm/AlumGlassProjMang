<?php
// /ghom/api/delete_stage.php

header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();
requireRole(['admin', 'superuser']);
requireCsrf();

try {
    $pdo = getProjectDBConnection('ghom');

    // Get data from the POST request
    $stage_id = $_POST['stage_id'] ?? null;
    $template_id = $_POST['template_id'] ?? null;

    if (empty($stage_id) || empty($template_id)) {
        throw new Exception("Stage ID and Template ID are required to delete.");
    }

    $pdo->beginTransaction();

    // 1. Delete all items associated with this stage
    $stmt_items = $pdo->prepare("DELETE FROM checklist_items WHERE stage_id = ? AND template_id = ?");
    $stmt_items->execute([$stage_id, $template_id]);

    // 2. Delete the stage itself
    $stmt_stage = $pdo->prepare("DELETE FROM inspection_stages WHERE stage_id = ? AND template_id = ?");
    $stmt_stage->execute([$stage_id, $template_id]);

    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'مرحله با موفقیت حذف شد.']);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'خطای پایگاه داده: ' . $e->getMessage()]);
}
