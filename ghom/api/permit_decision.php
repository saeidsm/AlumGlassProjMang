<?php
// ghom/api/permit_decision.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/notification_helper.php';

if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['status'=>'error', 'message'=>'Unauthorized']));
}

$data = json_decode(file_get_contents('php://input'), true);
$permitId = $data['permit_id'] ?? 0;
$decision = $data['status'] ?? ''; // 'Approved' or 'Rejected'
$notes = $data['notes'] ?? '';
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

if (!$permitId || !in_array($decision, ['Approved', 'Rejected'])) {
    exit(json_encode(['status'=>'error', 'message'=>'Invalid request data']));
}

$pdo = getProjectDBConnection('ghom');

try {
    $pdo->beginTransaction();

    // 1. Update Permit Table
    $stmtPermit = $pdo->prepare("UPDATE permits SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
    $stmtPermit->execute([$decision, $notes, $permitId]);

    // 2. Determine New Statuses for Inspections Table (The "Job Done" Logic)
    if ($decision === 'Approved') {
        // --- APPROVED: JOB DONE / CLOSED ---
        $newStatus = 'Pre-Inspection Complete'; // Turns Green/Final Status
        $newOverall = 'OK'; 
        $eventType = 'PRE_INSPECTION_COMPLETE'; // Notify as Finished
        $logAction = 'Permit Approved - Work Completed';
        $notifyMsg = "مجوز شماره {$permitId} تایید نهایی شد. عملیات این پانل‌ها تکمیل گردید.";
    } else {
        // --- REJECTED ---
        $newStatus = 'Reject'; // Turns Red
        $newOverall = 'Reject';
        $eventType = 'PERMIT_REJECTED';
        $logAction = 'Permit Rejected - Issues Found';
        $notifyMsg = "مجوز شماره {$permitId} رد شد (نقص). علت: {$notes}";
    }

    // 3. Get All Elements in this Permit
    $stmtEls = $pdo->prepare("SELECT element_id FROM permit_elements WHERE permit_id = ?");
    $stmtEls->execute([$permitId]);
    $elements = $stmtEls->fetchAll(PDO::FETCH_COLUMN);

    if (empty($elements)) {
        throw new Exception("No elements found for this permit.");
    }

    // 4. Update Inspections Table for EACH Element
    $stageId = 0; // Assuming stage 0 is the main GFRC installation/repair stage
    
    foreach ($elements as $elId) {
        // Check if inspection record exists
        $stmtCheck = $pdo->prepare("SELECT inspection_id, history_log FROM inspections WHERE element_id = ? AND stage_id = ? LIMIT 1");
        $stmtCheck->execute([$elId, $stageId]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $historyEntry = [
            'action' => $logAction,
            'status' => $newStatus,
            'user_id' => $userId,
            'role' => $userRole,
            'timestamp' => date('Y-m-d H:i:s'),
            'notes' => $notes,
            'permit_id' => $permitId
        ];

        if ($row) {
            // Update Existing Record
            $history = json_decode($row['history_log'], true) ?? [];
            $history[] = $historyEntry;
            
            // We update contractor_status too to ensure sync
            $stmtUpdate = $pdo->prepare("
                UPDATE inspections 
                SET status = ?, overall_status = ?, contractor_status = ?, history_log = ?, updated_at = NOW() 
                WHERE inspection_id = ?
            ");
            $stmtUpdate->execute([$newStatus, $newOverall, $newStatus, json_encode($history), $row['inspection_id']]);
        } else {
            // Insert New Record
            $history = [$historyEntry];
            $stmtInsert = $pdo->prepare("
                INSERT INTO inspections (element_id, stage_id, user_id, status, overall_status, contractor_status, history_log, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtInsert->execute([$elId, $stageId, $userId, $newStatus, $newOverall, $newStatus, json_encode($history)]);
        }
    }

    // 5. Send Notification
    // Get plan file for link
    $stmtPlan = $pdo->prepare("SELECT plan_file, contractor, block, zone_name FROM elements WHERE element_id = ? LIMIT 1");
    $stmtPlan->execute([$elements[0]]);
    $elInfo = $stmtPlan->fetch(PDO::FETCH_ASSOC);

    $groupInfo = [
        'total_count' => count($elements),
        'permit_id' => $permitId,
        'all_ids_string' => implode(',', $elements),
        'sample_element_details' => $elInfo
    ];

    trigger_workflow_task(
        $pdo,
        $groupInfo,
        null, 
        basename($elInfo['plan_file']),
        $eventType,
        $userId,
        date('Y-m-d'),
        $notifyMsg
    );

    $pdo->commit();
    echo json_encode(['status'=>'success', 'message' => 'وضعیت مجوز و تمام المان‌ها بروزرسانی شد.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status'=>'error', 'message' => $e->getMessage()]);
}