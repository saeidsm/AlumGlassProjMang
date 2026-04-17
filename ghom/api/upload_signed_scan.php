<?php
require_once __DIR__ . '/../../../sercon/bootstrap.php';

header('Content-Type: application/json');

if (function_exists('isLoggedIn') && !isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
require_once __DIR__ . '/../../includes/security.php';
requireCsrf();

try {
    $pdo = getProjectDBConnection('ghom');
    
    $report_id = $_POST['report_id'] ?? null;
    
    if (!$report_id) {
        throw new Exception('شناسه گزارش یافت نشد.');
    }
    
    if (!isset($_FILES['signed_file']) || $_FILES['signed_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('هیچ فایلی برای آپلود انتخاب نشده است یا در آپلود خطا رخ داده است.');
    }
    
    $file = $_FILES['signed_file'];
    
    // Security Check: Ensure it's a PDF
    if ($file['type'] !== 'application/pdf') {
        throw new Exception('فقط فایل‌های PDF مجاز هستند.');
    }
    
    // --- CORRECT FILE PATH LOGIC ---
    $upload_dir = dirname(__DIR__) . '/uploads/signed_scans/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0775, true)) {
             throw new Exception('خطا در ایجاد پوشه ذخیره سازی.');
        }
    }
    
    $filename = 'scan_' . $report_id . '_' . time() . '.pdf';
    $physical_filepath = $upload_dir . $filename;
    
    // The web-accessible path for the database
    $web_path = '/ghom/uploads/signed_scans/' . $filename; 
    
    if (!move_uploaded_file($file['tmp_name'], $physical_filepath)) {
        throw new Exception('خطا در انتقال فایل آپلود شده.');
    }
    
    $stmt = $pdo->prepare("UPDATE daily_reports SET signed_scan_path = ? WHERE id = ?");
    $stmt->execute([$web_path, $report_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'فایل امضا شده با موفقیت آپلود شد.'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}