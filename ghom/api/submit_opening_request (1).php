<?php
// /ghom/api/submit_opening_request.php (SMART FILTER VERSION)

error_reporting(0);
ob_start();

try {
    @require_once __DIR__ . '/../../sercon/bootstrap.php';
    require_once __DIR__ . '/../includes/notification_helper.php';

    if (ob_get_length()) ob_clean();

    header('Content-Type: application/json');

    if (!isLoggedIn()) {
        http_response_code(401);
        throw new Exception('Unauthorized');
    }

    $pdo = getProjectDBConnection('ghom');
    
    // دریافت اطلاعات فرم
    $permitData = json_decode($_POST['permit_data'] ?? '{}', true);
    if (empty($permitData) || empty($permitData['panels'])) {
        throw new Exception("اطلاعات فرم (permit_data) ناقص است.");
    }

    // =======================================================================
    // 🛡️ بخش امنیتی هوشمند: فیلتر کردن موارد تکراری
    // =======================================================================
    $blocked_statuses = [
        'Request to Open',
        'Opening Approved',
        'Panel Opened',
        'Opening Disputed',
        'Pre-Inspection Complete'
    ];

    $check_stmt = $pdo->prepare("SELECT status FROM inspections WHERE element_id = ? AND part_name <=> ? AND stage_id = 0");

    $valid_panels = []; // لیست پنل‌های تمیز شده
    $duplicate_count = 0; // شمارنده تکراری‌ها

    foreach ($permitData['panels'] as $panel) {
        $valid_parts = [];
        $parts = $panel['parts'] ?? [null]; // اگر پارت نداشت، null در نظر بگیر

        foreach ($parts as $part) {
            $check_stmt->execute([$panel['element_id'], $part]);
            $existing_status = $check_stmt->fetchColumn();

            // اگر وضعیت مسدود بود، به شمارنده اضافه کن و رد شو
            if ($existing_status && in_array($existing_status, $blocked_statuses)) {
                $duplicate_count++;
            } else {
                // اگر وضعیت آزاد بود، به لیست مجاز اضافه کن
                $valid_parts[] = $part;
            }
        }

        // اگر این پنل هنوز پارت مجازی دارد، آن را به لیست نهایی اضافه کن
        if (!empty($valid_parts)) {
            $clean_panel = $panel;
            $clean_panel['parts'] = $valid_parts; // جایگزینی پارت‌های فیلتر شده
            $valid_panels[] = $clean_panel;
        }
    }

    // ⛔ حالت ۱: اگر هیچ مورد مجازی باقی نماند (همه تکراری بودند)
    if (empty($valid_panels)) {
        throw new Exception("⛔ خطا: تمام پانل‌های انتخاب شده در حال حاضر فعال هستند و امکان ثبت درخواست مجدد ندارند.");
    }

    // ✅ جایگزینی لیست پنل‌ها با لیست فیلتر شده برای ادامه پردازش
    $permitData['panels'] = $valid_panels;

    // =======================================================================
    // ادامه پردازش با لیست تمیز شده ($valid_panels)
    // =======================================================================

    $pdo->beginTransaction();

    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'] ?? 'contractor';
    $notes = $permitData['notes'] ?? '';
    $contractor_name_log = $permitData['contractor'] ?? 'نامشخص'; 

    $date_gregorian = date('Y-m-d');
    if (function_exists('jalali_to_gregorian') && !empty($permitData['date'])) {
        $parts = array_map('intval', explode('/', trim($permitData['date'])));
        if (count($parts) === 3) {
            $date_gregorian = implode('-', jalali_to_gregorian($parts[0], $parts[1], $parts[2]));
        }
    }

    // --- مدیریت فایل آپلود ---
    if (!isset($_FILES['signed_form']) || $_FILES['signed_form']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('خطا در دریافت فایل فرم امضا شده.');
    }
    $file = $_FILES['signed_form'];

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/ghom/uploads/signed_permits/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0775, true)) {
            throw new Exception("خطا در ساخت پوشه آپلود.");
        }
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'permit_' . time() . '_' . uniqid() . '.' . $extension;
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("خطا در ذخیره فایل روی سرور.");
    }

    $webPath = '/ghom/uploads/signed_permits/' . $filename;
    $notes_for_notification = $notes . "\n\n📎 فایل پیوست:\n" . $webPath;

    // --- آماده‌سازی لاگ ---
    $logEntryArray = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $userId,
        'role' => $userRole,
        'action' => 'Contractor Action',
        'data' => [
            'contractor_status' => 'Request to Open',
            'description' => "درخواست بازگشایی توسط {$contractor_name_log} ثبت شد",
            'permit_file' => $filename,
            'contractor_notes' => $notes
        ]
    ];
    $initial_log_json = json_encode([$logEntryArray], JSON_UNESCAPED_UNICODE);

    // --- ثبت در دیتابیس (فقط برای موارد مجاز) ---
    foreach ($permitData['panels'] as $panel) {
        $parts = $panel['parts'] ?? [null];
        foreach ($parts as $part) {
            $stmt_find = $pdo->prepare("SELECT inspection_id, pre_inspection_log, history_log FROM inspections WHERE element_id = ? AND part_name <=> ? AND stage_id = 0");
            $stmt_find->execute([$panel['element_id'], $part]);
            $existing = $stmt_find->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // UPDATE
                $pre_logs = json_decode($existing['pre_inspection_log'] ?? '[]', true);
                if (!is_array($pre_logs)) $pre_logs = [];
                $pre_logs[] = $logEntryArray;

                $history_logs = json_decode($existing['history_log'] ?? '[]', true);
                if (!is_array($history_logs)) $history_logs = [];
                $history_logs[] = $logEntryArray;

                $stmt_update = $pdo->prepare(
                    "UPDATE inspections SET 
                        status = 'Request to Open', 
                        contractor_status = 'Request to Open', 
                        pre_inspection_log = ?, 
                        history_log = ?, 
                        user_id = ?, 
                        contractor_date = ?, 
                        contractor_notes = ?, 
                        permit_file = ? 
                    WHERE inspection_id = ?"
                );
                $stmt_update->execute([
                    json_encode($pre_logs, JSON_UNESCAPED_UNICODE), 
                    json_encode($history_logs, JSON_UNESCAPED_UNICODE), 
                    $userId, 
                    $date_gregorian, 
                    $notes, 
                    $filename, 
                    $existing['inspection_id']
                ]);
            } else {
                // INSERT
                $stmt_insert = $pdo->prepare(
                    "INSERT INTO inspections (
                        element_id, part_name, stage_id, status, contractor_status, 
                        pre_inspection_log, history_log, user_id, contractor_date, 
                        contractor_notes, permit_file, inspection_cycle
                    ) VALUES (?, ?, 0, 'Request to Open', 'Request to Open', ?, ?, ?, ?, ?, ?, 1)"
                );
                $stmt_insert->execute([
                    $panel['element_id'], 
                    $part, 
                    $initial_log_json, 
                    $initial_log_json, 
                    $userId, 
                    $date_gregorian, 
                    $notes, 
                    $filename
                ]);
            }
        }
    }

    // --- ارسال نوتیفیکیشن ---
    $sample_panel = $permitData['panels'][0] ?? [];
    $group_info = [
        'total_count' => count($permitData['panels']), // تعداد جدید (فیلتر شده)
        'sample_element_details' => [
            'element_id' => $sample_panel['element_id'],
            'plan_file' => $sample_panel['plan_file'] ?? '',
            'contractor' => $permitData['contractor'],
            'block' => $permitData['block'],
            'zone_name' => $permitData['zone'],
        ],
        'all_ids_string' => implode(',', array_map(fn($p) => $p['element_id'], $permitData['panels'])),
        'permit_file' => $webPath
    ];

    trigger_workflow_task($pdo, $group_info, null, $sample_panel['plan_file'] ?? '', 'OPENING_REQUESTED', $userId, null, $notes_for_notification, 0);

    $pdo->commit();
    
    // پاکسازی بافر خروجی
    if (ob_get_length()) ob_clean();
    
    // --- تولید پیام نهایی ---
    $final_message = 'درخواست شما با موفقیت ثبت شد.';
    if ($duplicate_count > 0) {
        $final_message .= " (توجه: $duplicate_count بخش تکراری از لیست حذف شد)";
    }

    echo json_encode(['success' => true, 'message' => $final_message]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    
    if (ob_get_length()) ob_clean();
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

ob_end_flush();
?>