<?php
// /ghom/api/submit_gfrc_permit.php (FINAL, ROBUST VERSION)
require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/notification_helper.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../../includes/security.php';
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Establish connections to BOTH databases. We need them both.
$pdo_ghom = getProjectDBConnection('ghom');
$pdo_common = getCommonDBConnection(); // As per your hint
$pdo_ghom->beginTransaction();

try {
    $permit_uid = $_POST['permit_uid'] ?? '';
    $userId = $_SESSION['user_id'];

    // --- Validate User ID against the common database ---
    $stmt_user_check = $pdo_common->prepare("SELECT id FROM users WHERE id = ?");
    $stmt_user_check->execute([$userId]);
    if ($stmt_user_check->fetch() === false) {
        throw new Exception("خطای سیستمی: شناسه کاربر معتبر نمی باشد.");
    }

    if (empty($permit_uid)) {
        throw new Exception("شناسه مجوز ارسال نشده است.");
    }

    $stmt_check = $pdo_ghom->prepare("SELECT * FROM opening_permits WHERE permit_uid = ? FOR UPDATE");
    $stmt_check->execute([$permit_uid]);
    $permit = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$permit) {
        throw new Exception("خطای سیستمی: مجوز با شناسه ارسالی یافت نشد.");
    }
    
    if ($permit['status'] !== 'Pending Signature') {
        throw new Exception("این مجوز قبلاً ارسال و پردازش شده است.");
    }
    
    $permitData = json_decode($permit['panel_data'], true);
    if (!isset($_FILES['signed_form'])) {
        throw new Exception('فایل فرم امضا شده بارگذاری نشده است.');
    }
    $file = $_FILES['signed_form'];
    
    // File validation...
    $uploadDir = __DIR__ . '/../../../uploads/signed_permits/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'permit_signed_' . $permit['id'] . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('خطا در ذخیره سازی فایل آپلود شده.');
    }

    $stmt_update_permit = $pdo_ghom->prepare("UPDATE opening_permits SET signed_form_path = ?, status = 'Submitted' WHERE id = ?");
    $stmt_update_permit->execute([$filename, $permit['id']]);

    // =======================================================================
    // REVISED LOGIC: "Read, then Write" for maximum compatibility
    // This replaces the entire problematic UPSERT statement.
    // =======================================================================
    $log_entry_object = ['timestamp' => date('Y-m-d H:i:s'), 'user_id' => $userId, 'role' => $_SESSION['role'], 'action' => 'request-opening-submitted', 'permit_id' => $permit['id']];

    foreach ($permitData as $panel) {
        $parts = $panel['parts'] ?? [null];
        foreach ($parts as $part) {
            
            // 1. READ: Check if a record already exists
            $stmt_find = $pdo_ghom->prepare("SELECT inspection_id, pre_inspection_log FROM inspections WHERE element_id = ? AND part_name <=> ? AND stage_id = 0");
            $stmt_find->execute([$panel['element_id'], $part]);
            $existing_inspection = $stmt_find->fetch(PDO::FETCH_ASSOC);

            if ($existing_inspection) {
                // 2.A UPDATE logic
                $log_data = json_decode($existing_inspection['pre_inspection_log'] ?? '[]', true);
                $log_data[] = $log_entry_object;

                $stmt_update = $pdo_ghom->prepare(
                    "UPDATE inspections SET status = 'Request to Open', contractor_status = 'Request to Open', pre_inspection_log = ?, user_id = ?, contractor_date = ?, contractor_notes = ? WHERE inspection_id = ?"
                );
                $stmt_update->execute([
                    json_encode($log_data, JSON_UNESCAPED_UNICODE),
                    $userId,
                    $permit['request_date'],
                    $permit['notes'],
                    $existing_inspection['inspection_id']
                ]);
            } else {
                // 2.B INSERT logic
                $log_data = [$log_entry_object];

                $stmt_insert = $pdo_ghom->prepare(
                   "INSERT INTO inspections (element_id, part_name, stage_id, status, contractor_status, pre_inspection_log, user_id, contractor_date, contractor_notes) VALUES (?, ?, 0, 'Request to Open', 'Request to Open', ?, ?, ?, ?)"
                );
                $stmt_insert->execute([
                    $panel['element_id'],
                    $part,
                    json_encode($log_data, JSON_UNESCAPED_UNICODE),
                    $userId,
                    $permit['request_date'],
                    $permit['notes']
                ]);
            }
        }
    }

    // --- Trigger Notification (no changes needed here) ---
    $sample_element = $permitData[0] ?? null;
    $group_info = [
        'total_count' => count($permitData),
        'sample_element_details' => [
            'plan_file' => $sample_element['plan_file'] ?? '',
            'contractor' => $permit['contractor_name'],
            'block' => $permit['block_name'],
            'zone_name' => $permit['zone_name'],
        ],
        'all_ids_string' => implode(',', array_map(fn($p) => $p['element_id'], $permitData))
    ];

    trigger_workflow_task(
        $pdo_ghom, // Pass the correct PDO connection
        $group_info,
        null,
        $sample_element['plan_file'] ?? '',
        'OPENING_REQUESTED',
        $userId,
        null,
        $permit['notes'],
        0
    );

    $pdo_ghom->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'فرم با موفقیت آپلود شد و درخواست برای مشاور ارسال گردید.'
    ]);
    
} catch (Exception $e) {
    if ($pdo_ghom && $pdo_ghom->inTransaction()) $pdo_ghom->rollBack();
    http_response_code(400); 
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}