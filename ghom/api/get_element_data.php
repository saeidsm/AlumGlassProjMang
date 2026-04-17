<?php
// /public_html/ghom/api/get_element_data.php (MULTI-STAGE SUPPORT)

header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/jdf.php';

if (session_status() == PHP_SESSION_NONE) session_start();
if (!isLoggedIn() || ($_SESSION['current_project_config_key'] ?? '') !== 'ghom') {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

$user_role = $_SESSION['role'] ?? 'guest';

$fullElementId = filter_input(INPUT_GET, 'element_id', FILTER_DEFAULT);
$elementType = filter_input(INPUT_GET, 'element_type', FILTER_DEFAULT);

if (empty($fullElementId) || empty($elementType)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Element ID and Type are required']));
}

try {
    $pdo = getProjectDBConnection('ghom');

    // Get user names for history log display
    $users_stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM hpc_common.users");
    $user_map = $users_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $baseElementId = $fullElementId;
    $partName = null;
    $parts = explode('-', $fullElementId);
    if (count($parts) > 1 && in_array(strtolower(end($parts)), ['face', 'up', 'down', 'left', 'right', 'default'])) {
        $partName = array_pop($parts);
        $baseElementId = implode('-', $parts);
    }

    // Get all possible items for this element type
    $stmt_all_items = $pdo->prepare("
        SELECT i.item_id, i.item_text 
        FROM checklist_items i
        JOIN checklist_templates t ON i.template_id = t.template_id
        WHERE t.element_type = ?
    ");
    $stmt_all_items->execute([$elementType]);
    $all_possible_items_map = $stmt_all_items->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get the structured template for the form renderer
    $stmt_stages = $pdo->prepare("SELECT s.stage_id, s.stage AS stage_name FROM checklist_templates t JOIN inspection_stages s ON t.template_id = s.template_id WHERE t.element_type = ? ORDER BY s.display_order");
    $stmt_stages->execute([$elementType]);
    $stages = $stmt_stages->fetchAll(PDO::FETCH_ASSOC);

    $stmt_items = $pdo->prepare("SELECT * FROM checklist_items i JOIN checklist_templates t ON i.template_id = t.template_id WHERE t.element_type = ? ORDER BY i.item_order ASC");
    $stmt_items->execute([$elementType]);
    $template_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    foreach ($stages as &$stage) {
        $stage['items'] = array_values(array_filter($template_items, fn($item) => $item['stage_id'] == $stage['stage_id']));
    }
    unset($stage);

    // CRITICAL FIX: Get ALL inspection records for this element/part, grouped by stage
    $stmt_history = $pdo->prepare("SELECT * FROM inspections WHERE element_id = ? AND part_name <=> ? ORDER BY stage_id ASC, inspection_id ASC");
    $stmt_history->execute([$baseElementId, $partName]);
    $all_inspections = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

    // Group inspections by stage_id to get the latest record for each stage
    $stages_history = [];
    foreach ($all_inspections as $inspection) {
        $stage_id = $inspection['stage_id'];
        
        // Keep only the most recent record for each stage
        if (!isset($stages_history[$stage_id]) || 
            $inspection['inspection_id'] > $stages_history[$stage_id]['inspection_id']) {
            $stages_history[$stage_id] = $inspection;
        }
    }

    // Server-side security logic to determine edit permissions
    $can_user_edit = false;
    if (!empty($stages_history)) {
        if (in_array($user_role, ['admin', 'superuser'])) {
            // Consultant can edit if any stage is not finalized
            $can_user_edit = !empty(array_filter($stages_history, function($record) {
                return !in_array($record['overall_status'], ['OK', 'Reject']);
            }));
        } elseif (in_array($user_role, ['cat', 'car', 'coa', 'crs'])) {
            // Contractor can edit if any stage has "Repair" status with rejection count < 3
            $can_user_edit = !empty(array_filter($stages_history, function($record) {
                return $record['overall_status'] === 'Repair' && 
                       (int)$record['repair_rejection_count'] < 3 &&
                       $record['contractor_status'] !== 'Awaiting Re-inspection';
            }));
        }
    } else {
        // No history yet - allow consultants to start the process
        $can_user_edit = in_array($user_role, ['admin', 'superuser']);
    }

    // Process each stage's history and enrich it
    $processed_history = [];
    foreach ($stages as $stage) {
        $stage_id = $stage['stage_id'];
        $stage_history = $stages_history[$stage_id] ?? null;
        
        if ($stage_history) {
            // Enrich history_log with user names and Jalali dates
            $history_log = json_decode($stage_history['history_log'] ?? '[]', true);
            if (is_array($history_log)) {
                foreach ($history_log as &$entry) {
                    $userId = $entry['user_id'] ?? null;
                    $entry['user_display_name'] = $user_map[$userId] ?? 'کاربر ناشناس';
                    if (!empty($entry['timestamp'])) {
                        $entry['persian_timestamp'] = jdate('Y/m/d H:i:s', strtotime($entry['timestamp']));
                    }
                }
                unset($entry);
            }
            $stage_history['history_log'] = $history_log;

            // Add Jalali dates for main fields
            if (!empty($stage_history['inspection_date'])) {
                $stage_history['inspection_date_jalali'] = jdate("Y/m/d", strtotime($stage_history['inspection_date']));
            }
            if (!empty($stage_history['contractor_date'])) {
                $stage_history['contractor_date_jalali'] = jdate("Y/m/d", strtotime($stage_history['contractor_date']));
            }

            // Get inspection items for this stage
            $stmt_items = $pdo->prepare("SELECT * FROM inspection_data WHERE inspection_id = ?");
            $stmt_items->execute([$stage_history['inspection_id']]);
            $stage_history['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            // Add stage_id to the record for frontend processing
            $stage_history['stage_id'] = $stage_id;
            
            $processed_history[] = $stage_history;
        } else {
            // Create empty record for stages with no history
            $processed_history[] = [
                'stage_id' => $stage_id,
                'overall_status' => null,
                'contractor_status' => null,
                'status' => 'Pending',
                'inspection_date' => null,
                'contractor_date' => null,
                'notes' => null,
                'contractor_notes' => null,
                'attachments' => null,
                'contractor_attachments' => null,
                'history_log' => [],
                'items' => [],
                'repair_rejection_count' => 0
            ];
        }
    }

    // Final JSON Response
    echo json_encode([
        'template' => $stages,
        'all_items_map' => $all_possible_items_map,
        'history' => $processed_history,
        'can_edit' => $can_user_edit
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error in get_element_data.php: " . $e->getMessage() . " on line " . $e->getLine());
    exit(json_encode(['error' => 'Database query failed.', 'details' => $e->getMessage()]));
}