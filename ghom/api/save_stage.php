<?php
// /ghom/api/save_stage.php (MODIFIED FOR DRAWING FLAG)

header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();
requireRole(['admin', 'superuser']);
requireCsrf();

try {
    $pdo = getProjectDBConnection('ghom');
    $pdo->beginTransaction();

    // 1. Get Data (No changes here)
    $template_id = $_POST['template_id'] ?? null;
    $stage_id = $_POST['stage_id'] ?: null;
    $stage_name = trim($_POST['stage_name'] ?? '');
    $display_order = $_POST['display_order'] ?? 0;
    $items = json_decode($_POST['items'] ?? '[]', true);

    if (empty($template_id) || empty($stage_name)) {
        throw new Exception("Template ID and Stage Name are required.");
    }

    // 2. Insert or Update the Stage (No changes here)
    if ($stage_id) {
        $stmt = $pdo->prepare("UPDATE inspection_stages SET stage = ?, display_order = ? WHERE stage_id = ? AND template_id = ?");
        $stmt->execute([$stage_name, $display_order, $stage_id, $template_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO inspection_stages (template_id, stage, display_order) VALUES (?, ?, ?)");
        $stmt->execute([$template_id, $stage_name, $display_order]);
        $stage_id = $pdo->lastInsertId();
    }

    // 3. WIPE old items for this stage ONLY (No changes here)
    $stmt_delete_items = $pdo->prepare("DELETE FROM checklist_items WHERE stage_id = ?");
    $stmt_delete_items->execute([$stage_id]);

    // 4. Re-insert the items for this stage
    if (!empty($items)) {
        // --- MODIFICATION START ---
        // Add the 'requires_drawing' column to the INSERT statement
        $sql = "INSERT INTO checklist_items 
                    (template_id, stage_id, item_text, stage, item_order, passing_status, item_weight, is_critical, requires_drawing) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_items = $pdo->prepare($sql);
        // --- MODIFICATION END ---

        foreach ($items as $index => $item) {
            if (!empty(trim($item['item_text']))) {
                // --- MODIFICATION START ---
                // Get the value from the submitted item data, default to 0 (false) if not present
                $requires_drawing = isset($item['requires_drawing']) && $item['requires_drawing'] ? 1 : 0;

                $stmt_items->execute([
                    $template_id,
                    $stage_id,
                    trim($item['item_text']),
                    $stage_name,
                    $index,
                    $item['passing_status'],
                    $item['item_weight'],
                    $item['is_critical'],
                    $requires_drawing // Add the new value to the execute array
                ]);
                // --- MODIFICATION END ---
            }
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'مرحله با موفقیت ذخیره شد!', 'new_stage_id' => $stage_id]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'خطای پایگاه داده: ' . $e->getMessage()]);
}
