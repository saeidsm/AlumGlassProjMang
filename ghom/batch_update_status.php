<?php
// /ghom/api/batch_update_status.php (FINAL CLEANED VERSION)
header('Content-Type: application/json');
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/jdf.php';
require_once __DIR__ . '/../includes/notification_helper.php';
function logWorkflow($message)
{
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', dirname(__DIR__, 4));
    }
     $logFile = APP_ROOT . '/logs/batch_update_status/workflow_log_' . date("Y-m-d") . '.log';
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    $entry = is_array($message) || is_object($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message;
    error_log(date('[Y-m-d H:i:s] ') . $entry . "\n", 3, $logFile);
}

if (session_status() == PHP_SESSION_NONE) session_start();
if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Forbidden']));
}

function jalali_to_gregorian_for_db($jalali_date)
{
    if (empty($jalali_date)) return null;
    $parts = array_map('intval', explode('/', trim($jalali_date)));
    if (count($parts) !== 3 || $parts[0] < 1300) return null;
    if (function_exists('jalali_to_gregorian')) {
        return implode('-', jalali_to_gregorian($parts[0], $parts[1], $parts[2]));
    }
    return null;
}

function translate_for_user($term, $context = 'action')
{
    $actions = [
        'request-opening' => 'درخواست بازگشایی',
        'approve-opening' => 'تایید درخواست بازگشایی',
        'reject-opening'  => 'رد درخواست بازگشایی',
        'confirm-opened'  => 'تایید پانل باز شده',
        'verify-opening'  => 'تایید نهایی بازگشایی',
        'dispute-opening' => 'رد بازگشایی پانل'
    ];
    $statuses = [
        'Pending' => 'در انتظار',
        'Request to Open' => 'درخواست بازگشایی',
        'Opening Approved' => 'تایید شده برای بازگشایی',
        'Opening Rejected' => 'درخواست بازگشایی رد شد',
        'Panel Opened' => 'پانل بازگشایی شده',
        'Opening Disputed' => 'بازگشایی پانل رد شد',
        'Pre-Inspection Complete' => 'مراحل بازگشایی تکمیل شد',
        'OK' => 'تایید شده',
        'Reject' => 'رد شده',
        'Repair' => 'نیاز به تعمیر'
    ];
    if ($context === 'action') return $actions[$term] ?? $term;
    return $statuses[$term] ?? $term;
}

logWorkflow("--- BATCH WORKFLOW API START ---");

try {
    $pdo = getProjectDBConnection('ghom');
    $pdo->beginTransaction();

    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    $element_ids = $data['element_ids'] ?? [];
    $notes = $data['notes'] ?? '';
    $date = $data['date'] ?? null;
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'];

    logWorkflow("Request Payload: " . json_encode($data, JSON_UNESCAPED_UNICODE));
    logWorkflow("User: {$userId} ({$userRole}) | Action: {$action}");

    if (empty($element_ids) || empty($action)) {
        throw new Exception("عملیات و المان مورد نظر انتخاب نشده است.");
    }

    $errors = [];

    // --- ۱. جمع‌آوری اطلاعات اولیه و آمار ---
    logWorkflow("--- STARTING QUERY 1: FETCH ELEMENT DETAILS ---");

// Safety check and log if the array is empty
if (empty($element_ids)) {
    logWorkflow("CRITICAL: element_ids array is empty. Aborting.");
    throw new Exception("No element IDs were provided to the batch update API.");
}

logWorkflow("Element IDs Array Count: " . count($element_ids));
logWorkflow("Element IDs Array Content: " . json_encode($element_ids));

$placeholders = implode(',', array_fill(0, count($element_ids), '?'));
$sql = "SELECT element_id, element_type, panel_orientation, plan_file, zone_name, block, contractor FROM elements WHERE element_id IN ($placeholders)";

logWorkflow("Generated SQL: " . $sql);

try {
    $stmt_elements = $pdo->prepare($sql);
    logWorkflow("SQL Prepare successful.");

    $stmt_elements->execute($element_ids);
    logWorkflow("SQL Execute successful.");

} catch (PDOException $e) {
    logWorkflow("!!! PDO EXCEPTION during element fetch: " . $e->getMessage());
    // Re-throw the exception to be caught by the outer try-catch block
    throw $e; 
}

    $elements_details = [];
    foreach ($stmt_elements->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $elements_details[$row['element_id']] = $row;
    }

    // متغیرهایی برای جمع‌آوری آمار اعلان گروهی
    $processed_element_ids_for_link = [];
    $processed_parts_by_zone = [];
    $total_processed_parts = 0;
    $sample_element_detail_for_notification = null;
    // --- پایان بخش آمار ---

    foreach ($element_ids as $elementId) {
        $element_detail = $elements_details[$elementId] ?? null;
        if (!$element_detail) continue;

        if (!$sample_element_detail_for_notification) {
            $sample_element_detail_for_notification = $element_detail;
        }
        $processed_element_ids_for_link[] = $elementId;

        $partsToProcess = []; // Always start with an empty array.

        if ($element_detail && $element_detail['element_type'] === 'GFRC') {
            // For GFRC, ALWAYS determine parts based on the database orientation.
            if ($element_detail['panel_orientation'] === 'Vertical') {
                $partsToProcess = ['face', 'left', 'right'];
            } elseif ($element_detail['panel_orientation'] === 'Horizontal') {
                $partsToProcess = ['face', 'up', 'down'];
            } else {
                $partsToProcess = ['face']; // Fallback for GFRC with no orientation
            }
        } else {
            // For any non-GFRC element, process it as a single, null part.
            $partsToProcess = [null];
        }
        // --- END OF FIX ---
        foreach ($partsToProcess as $partName) {
            $stage_id = 0;

// --- START: Detailed Logging for Query 2 ---
logWorkflow("--- STARTING QUERY 2: FETCH INSPECTION ---");
logWorkflow("Parameters for inspection fetch: elementId='{$elementId}', partName='{$partName}', stage_id='{$stage_id}'");

try {
    $stmt_find = $pdo->prepare("SELECT * FROM inspections WHERE element_id = ? AND part_name <=> ? AND stage_id = ? LIMIT 1");
    logWorkflow("Inspection query prepare successful.");

    $stmt_find->execute([$elementId, $partName, $stage_id]);
    logWorkflow("Inspection query execute successful.");

    $current_inspection = $stmt_find->fetch(PDO::FETCH_ASSOC);
    logWorkflow("Inspection fetch successful. Found inspection: " . ($current_inspection ? "Yes, ID: {$current_inspection['inspection_id']}" : "No"));

} catch (PDOException $e) {
    logWorkflow("!!! PDO EXCEPTION during inspection fetch for element '{$elementId}': " . $e->getMessage());
    throw $e; // Re-throw to be caught by the main try-catch
}
            $current_status = $current_inspection['status'] ?? 'Pending';

            $is_allowed = false;
            switch ($action) {
                case 'request-opening':
                    $is_allowed = in_array($current_status, ['Pending', 'Opening Rejected', 'Opening Disputed']);
                    break;
                case 'approve-opening':
                case 'reject-opening':
                    $is_allowed = ($current_status === 'Request to Open');
                    break;
                case 'confirm-opened':
                    $is_allowed = ($current_status === 'Opening Approved');
                    break;
                case 'verify-opening':
                case 'dispute-opening':
                    $is_allowed = ($current_status === 'Panel Opened');
                    break;
            }

            if (!$is_allowed) {
                $element_label = $elementId . ($partName ? "-{$partName}" : "");
                $errors[] = "برای المان {$element_label}: عملیات '" . translate_for_user($action, 'action') . "' در وضعیت فعلی '" . translate_for_user($current_status, 'status') . "' مجاز نمی باشد.";
                continue;
            }

            $log_entry = ['timestamp' => date('Y-m-d H:i:s'), 'user_id' => $userId, 'role' => $userRole, 'action' => $action, 'notes' => $notes, 'date' => $date];
            $params = ['user_id' => $userId];
            $new_status = '';

            switch ($action) {
                case 'request-opening':
                    $new_status = 'Request to Open';
                    break;
                case 'approve-opening':
                    $new_status = 'Opening Approved';
                    break;
                case 'reject-opening':
                    $new_status = 'Opening Rejected';
                    break;
                case 'confirm-opened':
                    $new_status = 'Panel Opened';
                    break;
                case 'verify-opening':
                    $new_status = 'Pre-Inspection Complete';
                    break;
                case 'dispute-opening':
                    $new_status = 'Opening Disputed';
                    break;
            }
            $params['status'] = $new_status;
            $params['contractor_status'] = $new_status;

            if (in_array($userRole, ['cat', 'car', 'coa', 'crs'])) {
                $params['contractor_date'] = jalali_to_gregorian_for_db($date);
                $params['contractor_notes'] = $notes;
            } else {
                $params['inspection_date'] = date('Y-m-d H:i:s');
                $params['notes'] = $notes;
            }

            if ($current_inspection) {
    // --- UPDATE an existing inspection record ---
    $log_data = json_decode($current_inspection['pre_inspection_log'] ?? '[]', true);
    $log_data[] = $log_entry;

    // 1. Explicitly define the parameter array for the update
    $update_params = [
        ':status' => $new_status,
        ':contractor_status' => $new_status,
        ':pre_inspection_log' => json_encode($log_data, JSON_UNESCAPED_UNICODE),
        ':user_id' => $userId,
        ':inspection_id' => $current_inspection['inspection_id']
    ];

    // 2. Explicitly define the parts of the SET clause
    $sql_set_parts = [
        "`status` = :status",
        "`contractor_status` = :contractor_status",
        "`pre_inspection_log` = :pre_inspection_log",
        "`user_id` = :user_id"
    ];

    // 3. Conditionally add parameters and SET parts based on user role
    if (in_array($userRole, ['cat', 'car', 'coa', 'crs'])) {
        $sql_set_parts[] = "`contractor_date` = :contractor_date";
        $sql_set_parts[] = "`contractor_notes` = :contractor_notes";
        $update_params[':contractor_date'] = jalali_to_gregorian_for_db($date);
        $update_params[':contractor_notes'] = $notes;
    } else {
        $sql_set_parts[] = "`inspection_date` = :inspection_date";
        $sql_set_parts[] = "`notes` = :notes";
        $update_params[':inspection_date'] = date('Y-m-d H:i:s');
        $update_params[':notes'] = $notes;
    }

    // 4. Assemble the final query and execute
    $sql = "UPDATE inspections SET " . implode(', ', $sql_set_parts) . " WHERE inspection_id = :inspection_id";
    $pdo->prepare($sql)->execute($update_params);

} else {
    // --- INSERT a new inspection record ---
    $params['element_id'] = $elementId;
    $params['part_name'] = $partName;
    $params['stage_id'] = $stage_id;
    $params['pre_inspection_log'] = json_encode([$log_entry], JSON_UNESCAPED_UNICODE);
    $params['history_log'] = '[]';

    // Explicitly define columns and placeholders
    $columns = implode('`, `', array_keys($params));
    $placeholders = ':' . implode(', :', array_keys($params));

    $sql = "INSERT INTO inspections (`$columns`) VALUES ($placeholders)";
    $pdo->prepare($sql)->execute($params);
}
            $event_type_to_trigger = null;

            switch ($action) {
                case 'request-opening':
                    $event_type_to_trigger = 'OPENING_REQUESTED';
                    break;
                case 'approve-opening':
                    $event_type_to_trigger = 'OPENING_APPROVED';
                    break;
                case 'reject-opening':
                    $event_type_to_trigger = 'OPENING_REJECTED';
                    break;
                case 'confirm-opened':
                    $event_type_to_trigger = 'PANEL_OPENED';
                    break;
                case 'verify-opening':
                    $event_type_to_trigger = 'PRE_INSPECTION_COMPLETE';
                    break;
                case 'dispute-opening':
                    $event_type_to_trigger = 'OPENING_DISPUTED';
                    break;
            }

            $zone = $element_detail['zone_name'] ?? 'نامشخص';
            if (!isset($processed_parts_by_zone[$zone])) {
                $processed_parts_by_zone[$zone] = 0;
            }
            $processed_parts_by_zone[$zone]++;
            $total_processed_parts++;
        }
    }

    if (!empty($errors)) {
        throw new Exception(implode("\n", $errors));
    }

    // --- ۴. انتقال و اجرای منطق اعلان در اینجا (بعد از حلقه) ---
    if ($total_processed_parts > 0) {
    $event_type_to_trigger = null;
    switch ($action) {
        case 'request-opening': $event_type_to_trigger = 'OPENING_REQUESTED'; break;
        case 'approve-opening': $event_type_to_trigger = 'OPENING_APPROVED'; break;
        case 'reject-opening':  $event_type_to_trigger = 'OPENING_REJECTED'; break;
        case 'confirm-opened':  $event_type_to_trigger = 'PANEL_OPENED'; break;
        case 'verify-opening':  $event_type_to_trigger = 'PRE_INSPECTION_COMPLETE'; break;
        case 'dispute-opening': $event_type_to_trigger = 'OPENING_DISPUTED'; break;
    }
    if ($event_type_to_trigger) {
            $unique_processed_elements = array_unique($processed_element_ids_for_link);
            $sample_element_details = $sample_element_detail_for_notification;

            $group_info = [
                'total_count' => $total_processed_parts,
                'by_zone' => $processed_parts_by_zone,
                'sample_element_details' => $sample_element_details,
                'all_ids_string' => implode(',', $unique_processed_elements)
            ];

    // --- START: ADD THESE LOGS ---
    logWorkflow("--- PREPARING TO CALL trigger_workflow_task ---");
    logWorkflow("Event Type to Trigger: " . $event_type_to_trigger);
    logWorkflow("Group Info Package: " . json_encode($group_info, JSON_UNESCAPED_UNICODE));
    logWorkflow("Passing Plan File: " . ($sample_element_detail_for_notification['plan_file'] ?? 'NOT FOUND'));
    logWorkflow("Passing User ID: " . $userId);
    logWorkflow("Passing Notes: " . $notes);
    logWorkflow("--- CALLING trigger_workflow_task NOW ---");
    // --- END: ADD THESE LOGS ---

      trigger_workflow_task(
            $pdo,
            $group_info,                                // element_id_or_group_info
            null,                                       // part_name (is null in batch mode)
            $sample_element_details['plan_file'],       // plan_file
            $event_type_to_trigger,                     // event_type
            $userId,                                    // actor_user_id
            null,                                       // event_date (use today's date)
            $notes,
            0,
            null                                      // notes from the form
        );
}
    }
    // --- پایان بخش اعلان ---

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => "عملیات برای {$total_processed_parts} بخش از " . count(array_unique($processed_element_ids_for_link)) . " المان با موفقیت ثبت شد."]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    logWorkflow("!!! WORKFLOW/DB ERROR: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
