<?php
// /ghom/api/submit_gfrc_permit.php (FINAL CORRECTED VERSION)
require_once __DIR__ . '/../../sercon/bootstrap.php';
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

$pdo = getProjectDBConnection('ghom');
$pdo->beginTransaction();

try {
    $permit_uid = $_POST['permit_uid'] ?? '';
    $userId = $_SESSION['user_id'];

    if (empty($permit_uid)) {
        throw new Exception("شناسه مجوز ارسال نشده است.");
    }

    // First, check if the permit exists at all, regardless of status.
    $stmt_check = $pdo->prepare("SELECT * FROM opening_permits WHERE permit_uid = ? FOR UPDATE");
    $stmt_check->execute([$permit_uid]);
    $permit = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$permit) {
        throw new Exception("خطای سیستمی: مجوز با شناسه ارسالی یافت نشد.");
    }
    
    if ($permit['status'] !== 'Pending Signature') {
        throw new Exception("این مجوز قبلاً ارسال و پردازش شده است و نمی‌توان آن را مجدداً ارسال کرد.");
    }
    
    $permitData = json_decode($permit['panel_data'], true);

    if (!isset($_FILES['signed_form'])) {
        throw new Exception('فایل فرم امضا شده بارگذاری نشده است.');
    }
    $file = $_FILES['signed_form'];
    
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('نوع فایل معتبر نیست. لطفا از PDF, JPG, or PNG استفاده کنید.');
    }

    $uploadDir = __DIR__ . '/../uploads/signed_permits/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'permit_signed_' . $permit['id'] . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('خطا در ذخیره سازی فایل آپلود شده.');
    }

    $stmt_update_permit = $pdo->prepare("UPDATE opening_permits SET signed_form_path = ?, status = 'Submitted' WHERE id = ?");
    $stmt_update_permit->execute([$filename, $permit['id']]);

    // =======================================================================
    // THE FIX IS HERE: Removed the incompatible "CAST(? AS JSON)"
    // =======================================================================
    $stmt_upsert = $pdo->prepare("
        INSERT INTO inspections (element_id, part_name, stage_id, status, contractor_status, pre_inspection_log, user_id, contractor_date, contractor_notes)
        VALUES (?, ?, 0, 'Request to Open', 'Request to Open', ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        status = VALUES(status),
        contractor_status = VALUES(contractor_status),
        pre_inspection_log = JSON_ARRAY_APPEND(IFNULL(pre_inspection_log, '[]'), '$', ?),
        user_id = VALUES(user_id),
        contractor_date = VALUES(contractor_date),
        contractor_notes = VALUES(contractor_notes)
    ");
    // =======================================================================
    // END OF FIX
    // =======================================================================

    $log_entry = json_encode(['timestamp' => date('Y-m-d H:i:s'), 'user_id' => $userId, 'role' => $_SESSION['role'], 'action' => 'request-opening-submitted', 'permit_id' => $permit['id']]);

    foreach ($permitData as $panel) {
        $parts = $panel['parts'] ?? [null];
        foreach ($parts as $part) {
             $stmt_upsert->execute([
                $panel['element_id'],
                $part,
                json_encode([json_decode($log_entry)]), // For INSERT
                $userId,
                $permit['request_date'],
                $permit['notes'],
                $log_entry // For UPDATE (This now matches the single placeholder in the fixed query)
            ]);
        }
    }

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
        $pdo,
        $group_info,
        null,
        $sample_element['plan_file'] ?? '',
        'OPENING_REQUESTED',
        $userId,
        null,
        $permit['notes'],
        0
    );

    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'فرم با موفقیت آپلود شد و درخواست برای مشاور ارسال گردید.'
    ]);
    
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400); 
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}