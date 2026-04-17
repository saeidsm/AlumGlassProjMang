<?php
// api/upload_scan_ps.php
require_once __DIR__ . '/../../../sercon/bootstrap.php';

header('Content-Type: application/json');

session_start();

if (function_exists('isLoggedIn') && !isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    $pdo = getProjectDBConnection('pardis');
    
    $report_id = $_POST['report_id'] ?? null;
    
    if (!$report_id) {
        throw new Exception('شناسه گزارش مشخص نشده است');
    }
    
    if (!isset($_FILES['scan_file']) || $_FILES['scan_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('فایلی انتخاب نشده است');
    }
    
    $file = $_FILES['scan_file'];
    
    // Get actual mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    
    if (!in_array($mime, $allowed)) {
        throw new Exception('فرمت فایل مجاز نیست (فقط JPG, PNG, PDF)');
    }
    
    // Max 10MB
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('حجم فایل نباید بیشتر از 10 مگابایت باشد');
    }
    
    // Create upload directory
    $upload_dir_abs = __DIR__ . '/../uploads/scans/';
    if (!is_dir($upload_dir_abs)) {
        if (!mkdir($upload_dir_abs, 0775, true)) {
            throw new Exception('خطا در ایجاد پوشه آپلود');
        }
    }
    
    // Generate filename with proper extension
    $ext = ($mime === 'application/pdf') ? 'pdf' : 'jpg';
    $filename = 'scan_ps_' . $report_id . '_' . time() . '.' . $ext;
    $file_path_abs = $upload_dir_abs . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path_abs)) {
        throw new Exception('خطا در ذخیره فایل');
    }
    
    // Store relative path (without leading slash)
    $web_path = 'uploads/scans/' . $filename;
    
    // Delete old scan if exists
    $stmt = $pdo->prepare("SELECT signed_scan_path FROM ps_daily_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $old_scan = $stmt->fetchColumn();
    
    if ($old_scan) {
        $old_file = __DIR__ . '/../' . $old_scan;
        if (file_exists($old_file)) {
            @unlink($old_file);
        }
    }
    
    // Update database
    $stmt = $pdo->prepare("UPDATE ps_daily_reports SET signed_scan_path = ? WHERE id = ?");
    $stmt->execute([$web_path, $report_id]);
    
    echo json_encode([
        'success' => true, 
        'path' => $web_path,
        'filename' => $filename,
        'message' => 'فایل با موفقیت آپلود شد'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>