<?php
// /ghom/api/submit_opening_request.php (CORRECTED UPLOAD PATH)
require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/notification_helper.php';

// A simple logger for this critical file
function log_upload($message) {
    $log_file = __DIR__ . '/logs/permit_uploads.log';
    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

header('Content-Type: application/json');
log_upload("--- New request started ---");

if (!isLoggedIn()) {
    http_response_code(401);
    //log_upload("ERROR: User not logged in.");
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$pdo = getProjectDBConnection('ghom');
$pdo->beginTransaction();

try {
    $permitData = json_decode($_POST['permit_data'], true);
    if (empty($permitData) || empty($permitData['panels'])) {
        throw new Exception("CRITICAL: permit_data is missing.");
    }

    $userId = $_SESSION['user_id'];
    $notes = $permitData['notes'] ?? '';
    $date_gregorian = jalali_to_gregorian_for_db($permitData['date'] ?? '');

    if (!isset($_FILES['signed_form']) || $_FILES['signed_form']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error. Code: ' . ($_FILES['signed_form']['error'] ?? 'Unknown'));
    }
    $file = $_FILES['signed_form'];

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/ghom/uploads/signed_permits/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0775, true)) {
            throw new Exception("Failed to create upload directory. Check server permissions.");
        }
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'permit_' . time() . '_' . uniqid() . '.' . $extension;
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("CRITICAL: move_uploaded_file FAILED. Check permissions.");
    }

    $log_entry = json_encode(['timestamp' => date('Y-m-d H:i:s'), 'user_id' => $userId, 'role' => $_SESSION['role'], 'action' => 'request-opening']);
    foreach ($permitData['panels'] as $panel) {
        $parts = $panel['parts'] ?? [null];
        foreach ($parts as $part) {
            $stmt_find = $pdo->prepare("SELECT inspection_id, pre_inspection_log FROM inspections WHERE element_id = ? AND part_name <=> ? AND stage_id = 0");
            $stmt_find->execute([$panel['element_id'], $part]);
            $existing = $stmt_find->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $logs = json_decode($existing['pre_inspection_log'] ?? '[]', true);
                $logs[] = json_decode($log_entry, true);
                $stmt_update = $pdo->prepare("UPDATE inspections SET status = 'Request to Open', contractor_status = 'Request to Open', pre_inspection_log = ?, user_id = ?, contractor_date = ?, contractor_notes = ?, permit_file = ? WHERE inspection_id = ?");
                $stmt_update->execute([json_encode($logs), $userId, $date_gregorian, $notes, $filename, $existing['inspection_id']]);
            } else {
                $stmt_insert = $pdo->prepare("INSERT INTO inspections (element_id, part_name, stage_id, status, contractor_status, pre_inspection_log, user_id, contractor_date, contractor_notes, permit_file) VALUES (?, ?, 0, 'Request to Open', 'Request to Open', ?, ?, ?, ?, ?)");
                $stmt_insert->execute([$panel['element_id'], $part, json_encode([json_decode($log_entry)]), $userId, $date_gregorian, $notes, $filename]);
            }
        }
    }

    $sample_panel = $permitData['panels'][0] ?? [];
    
    // =======================================================================
    // THE CRITICAL FIX IS HERE
    // We MUST add the filename to the data being sent to the notification helper.
    // =======================================================================
    $group_info = [
        'total_count' => count($permitData['panels']),
        'sample_element_details' => [
            'plan_file' => $sample_panel['plan_file'] ?? '',
            'contractor' => $permitData['contractor'],
            'block' => $permitData['block'],
            'zone_name' => $permitData['zone'],
        ],
        'all_ids_string' => implode(',', array_map(fn($p) => $p['element_id'], $permitData['panels'])),
        'permit_file' => $filename // <-- THIS LINE WAS MISSING
    ];
    // =======================================================================

    trigger_workflow_task($pdo, $group_info, null, $sample_panel['plan_file'] ?? '', 'OPENING_REQUESTED', $userId, null, $notes, 0);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'درخواست شما با موفقیت ثبت و برای بازرس ارسال شد.']);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    //log_upload("!!! WORKFLOW FAILED: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


function jalali_to_gregorian_for_db($jalali_date) {
    if (empty($jalali_date)) return date('Y-m-d');
    require_once __DIR__ . '/../includes/jdf.php';
    $parts = array_map('intval', explode('/', trim($jalali_date)));
    if (count($parts) !== 3) return date('Y-m-d');
    return implode('-', jalali_to_gregorian($parts[0], $parts[1], $parts[2]));
}