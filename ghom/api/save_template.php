<?php
// /ghom/api/save_template.php (CORRECTED to handle JSON items)

header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
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
    $pdo = getProjectDBConnection('ghom');
    $pdo->beginTransaction();

    // --- 1. GET AND VALIDATE INPUT (MODIFIED) ---
    $template_id = $_POST['template_id'] ?: null;
    $template_name = trim($_POST['template_name'] ?? '');
    $element_type = trim($_POST['element_type'] ?? '');

    // Get the items from the single JSON string sent by the new JavaScript
    $items_json = $_POST['items'] ?? '[]';
    $items = json_decode($items_json, true);

    if (empty($template_name) || empty($element_type)) {
        throw new Exception('نام قالب و نوع المان الزامی است.');
    }

    // --- 2. SAVE OR UPDATE THE MAIN TEMPLATE (No changes here) ---
    if ($template_id) { // UPDATE
        $stmt = $pdo->prepare("UPDATE checklist_templates SET template_name = ?, element_type = ? WHERE template_id = ?");
        $stmt->execute([$template_name, $element_type, $template_id]);
    } else { // INSERT
        $stmt = $pdo->prepare("INSERT INTO checklist_templates (template_name, element_type) VALUES (?, ?)");
        $stmt->execute([$template_name, $element_type]);
        $template_id = $pdo->lastInsertId();
    }

    // --- 3. WIPE OLD DATA FOR THIS TEMPLATE (No changes here, this is the correct method) ---
    $pdo->prepare("DELETE FROM inspection_stages WHERE template_id = ?")->execute([$template_id]);
    $pdo->prepare("DELETE FROM checklist_items WHERE template_id = ?")->execute([$template_id]);

    // --- 4. RE-INSERT STAGES AND ITEMS FROM THE NEW JSON DATA (Completely rewritten) ---
    if (is_array($items) && !empty($items)) {

        // First, get an ordered list of unique stage names from the items
        $stage_names_ordered = [];
        foreach ($items as $item) {
            $stage_name = trim($item['stage']);
            if (!empty($stage_name) && !in_array($stage_name, $stage_names_ordered)) {
                $stage_names_ordered[] = $stage_name;
            }
        }

        // Insert the stages with their correct display order
        $stmt_stage = $pdo->prepare("INSERT INTO inspection_stages (template_id, stage, display_order) VALUES (?, ?, ?)");
        foreach ($stage_names_ordered as $index => $stage_name) {
            $stmt_stage->execute([$template_id, $stage_name, $index]);
        }

        // Now, re-insert all the checklist items
        $stmt_items = $pdo->prepare(
            "INSERT INTO checklist_items 
                (template_id, item_text, stage, item_order, passing_status, item_weight, is_critical) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($items as $index => $item) {
            // Ensure we don't insert items with empty text
            if (!empty(trim($item['item_text']))) {
                $stmt_items->execute([
                    $template_id,
                    trim($item['item_text']),
                    trim($item['stage']),
                    $index, // Use the overall item index for ordering
                    $item['passing_status'],
                    $item['item_weight'],
                    $item['is_critical']
                ]);
            }
        }
    }
    // If $items is empty (e.g., all stages were deleted), it will correctly save a template with no items.

    // --- 5. COMMIT AND RESPOND ---
    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'قالب با موفقیت ذخیره شد!']);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    // More specific error handling
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode(['status' => 'error', 'message' => 'خطا: برای هر نوع المان تنها یک قالب میتوان تعریف کرد.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'خطای پایگاه داده: ' . $e->getMessage()]);
    }
}
