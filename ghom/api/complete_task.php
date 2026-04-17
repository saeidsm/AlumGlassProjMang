<?php
// ===================================================================
// ghom/api/complete_task.php (FINAL VERSION WITH STAGE_ID AWARENESS)
// ===================================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';

/**
 * Logging function
 */
function log_completion($message) {
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', dirname(__DIR__, 4));
    }
    $logDir = APP_ROOT . '/logs/complete_task';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/completion_log_' . date("Y-m-d") . '.log';
    $entry = is_array($message) || is_object($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message;
    error_log(date('[Y-m-d H:i:s] ') . $entry . "\n", 3, $logFile);
}

/**
 * Check if all elements in a stage have the expected status
 */
function check_all_elements_status(PDO $pdo, array $element_ids, ?int $stage_id, string $expected_status, string $status_column) {
    if (empty($element_ids)) {
        log_completion("ERROR: Empty element_ids array provided.");
        return false;
    }
    try {
        $placeholders = implode(',', array_fill(0, count($element_ids), '?'));
        $sql = "SELECT COUNT(DISTINCT element_id) FROM inspections 
                WHERE element_id IN ($placeholders) AND stage_id = ? AND {$status_column} = ?";
        $params = array_merge($element_ids, [$stage_id, $expected_status]);

        log_completion("Executing status check query: $sql");
        log_completion("Parameters: " . json_encode($params));

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count_with_status = (int)$stmt->fetchColumn();

        log_completion("Elements with status '{$expected_status}' for stage '{$stage_id}': {$count_with_status} / " . count($element_ids));
        return $count_with_status === count($element_ids);

    } catch (Exception $e) {
        log_completion("ERROR in check_all_elements_status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current element statuses for debug
 */
function get_elements_current_status(PDO $pdo, array $element_ids) {
    if (empty($element_ids)) return [];
    try {
        $placeholders = implode(',', array_fill(0, count($element_ids), '?'));
        $sql = "SELECT element_id, stage_id, contractor_status, overall_status, status, created_at 
                FROM inspections 
                WHERE element_id IN ($placeholders) 
                ORDER BY element_id, created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($element_ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        log_completion("ERROR getting current status: " . $e->getMessage());
        return [];
    }
}

// --- 1. Authentication & Input Validation ---
secureSession();
if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Forbidden']));
}

$data = json_decode(file_get_contents('php://input'), true);
$task_id = $data['task_id'] ?? null;
$user_id = $_SESSION['user_id'];

log_completion("--- Task Completion Attempt START ---");
log_completion(["user_id" => $user_id, "task_id" => $task_id]);

if (empty($task_id) || !is_numeric($task_id)) {
    log_completion("ERROR: Input validation failed.");
    http_response_code(400);
    exit(json_encode(['status' => 'error', 'message' => 'Task ID is invalid.']));
}

try {
    $pdo = getProjectDBConnection('ghom');

    // --- 2. Find the Task (includes stage_id) ---
    $stmt_task = $pdo->prepare("SELECT notification_id, status, event_type, link, stage_id FROM notifications WHERE notification_id = ? AND user_id = ? AND `type` = 'task'");
    $stmt_task->execute([$task_id, $user_id]);
    $task = $stmt_task->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        log_completion("ERROR: Task not found.");
        throw new Exception("وظیفه یافت نشد یا شما اجازه انجام آن را ندارید.");
    }

    if ($task['status'] === 'completed') {
        echo json_encode(['status' => 'success', 'message' => 'این وظیفه قبلاً تکمیل شده است.']);
        exit();
    }

    // --- 3. Extract element_id and stage_id ---
    $stage_id = (int)$task['stage_id'];
parse_str(parse_url($task['link'], PHP_URL_QUERY), $query_params);
$full_element_id = $query_params['element_id'] ?? null;

if (!$full_element_id) {
    log_completion("ERROR: No element_id found in task link.");
    throw new Exception("شناسه المان در لینک وظیفه مشخص نیست.");
}

// === FIX STARTS HERE ===
// 1. Explode the string of IDs from the URL parameter.
$raw_element_ids = explode(',', $full_element_id);
log_completion("Raw Element IDs from link: " . json_encode($raw_element_ids));

// 2. Clean each ID in the array to remove the " - [port_name]" part.
$element_ids_array = array_map(function($id) {
    // Split the ID by " - " and take only the first part.
    $parts = explode(' - ', trim($id));
    // Return the first part, which is the actual element_id we want.
    return $parts[0];
}, $raw_element_ids);
// === FIX ENDS HERE ===


log_completion("Stage ID: {$stage_id}");
// The log will now show the cleaned IDs
log_completion("Cleaned Element IDs: " . json_encode($element_ids_array));

    $current_statuses = get_elements_current_status($pdo, $element_ids_array);
    log_completion("Current statuses: " . json_encode($current_statuses));

    // --- 4. Determine if task is truly complete ---
    $task_is_truly_complete = false;
    $required_status_message = '';
$is_contractor_task = in_array($task['event_type'], ['OPENING_APPROVED', 'REPAIR_REQUESTED', 'OPENING_REJECTED', 'OPENING_DISPUTED', 'INSPECTION_REJECT']);
$is_consultant_task = in_array($task['event_type'], ['OPENING_REQUESTED', 'PANEL_OPENED', 'REPAIR_DONE']);

    if ($stage_id === 0) {
        switch ($task['event_type']) {
            case 'OPENING_APPROVED':
                $task_is_truly_complete = check_all_elements_status($pdo, $element_ids_array, 0, 'Panel Opened', 'contractor_status');
                $required_status_message = 'پانل‌ها باید باز شده باشند.';
                break;

            case 'PANEL_OPENED':
                $task_is_truly_complete = check_all_elements_status($pdo, $element_ids_array, 0, 'Pre-Inspection Complete', 'contractor_status');
                $required_status_message = 'پیش‌-بازرسی پانل‌ها باید انجام شود.';
                break;

            case 'REPAIR_REQUESTED':
                $task_is_truly_complete = check_all_elements_status($pdo, $element_ids_array, 0, 'Awaiting Re-inspection', 'status');
                $required_status_message = 'وضعیت باید "منتظر بازرسی مجدد" باشد.';
                break;

            case 'REPAIR_DONE':
                $task_is_truly_complete =
                    check_all_elements_status($pdo, $element_ids_array, 0, 'OK', 'overall_status') ||
                    check_all_elements_status($pdo, $element_ids_array, 0, 'Reject', 'overall_status');
                $required_status_message = 'بازرسی نهایی باید انجام شده باشد.';
                break;

            default:
                $task_is_truly_complete = true;
                break;
        }
    } else {
        switch ($task['event_type']) {
             case 'REPAIR_REQUESTED': // This is a contractor's task
            $task_is_truly_complete = check_all_elements_status($pdo, $element_ids_array, $stage_id, 'Awaiting Re-inspection', 'status');
            $required_status_message = 'شما باید ابتدا اتمام تعمیر این مرحله را از طریق فرم بازرسی اعلام کنید.';
            break;

            case 'REPAIR_DONE': // This is a consultant's task
            $is_ok = check_all_elements_status($pdo, $element_ids_array, $stage_id, 'OK', 'overall_status');
            $is_rejected = check_all_elements_status($pdo, $element_ids_array, $stage_id, 'Reject', 'overall_status');
            $is_repair_again = check_all_elements_status($pdo, $element_ids_array, $stage_id, 'Repair', 'overall_status');
            $task_is_truly_complete = $is_ok || $is_rejected || $is_repair_again; // It's complete if consultant has given ANY feedback
            $required_status_message = 'شما باید ابتدا بازرسی مجدد این مرحله را انجام داده و نظر نهایی خود (تایید، رد، یا تعمیر مجدد) را ثبت کنید.';
            break;

            default:
                log_completion("WARNING: Unknown event_type '{$task['event_type']}' for stage {$stage_id} - allowing completion.");
                $task_is_truly_complete = true;
                break;
        }
    }

    log_completion("Completion check result: " . ($task_is_truly_complete ? 'PASSED' : 'FAILED'));

    if (!$task_is_truly_complete) {
        throw new Exception("وظیفه هنوز قابل تکمیل نیست. " . $required_status_message);
    }

    // --- 5. Mark task as completed ---
    $pdo->beginTransaction();
    $update_stmt = $pdo->prepare("UPDATE notifications SET status = 'completed', completed_at = NOW() WHERE notification_id = ?");
    $update_stmt->execute([$task_id]);

    if ($update_stmt->rowCount() === 0) {
        throw new Exception("خطا در به‌روزرسانی وضعیت وظیفه.");
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'وظیفه با موفقیت تکمیل شد.', 'task_id' => $task_id]);
    log_completion("--- Task Completion Attempt SUCCESS ---");

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    log_completion("!!! ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'task_id' => $task_id ?? null]);
}

log_completion("--- Task Completion Attempt END ---");
