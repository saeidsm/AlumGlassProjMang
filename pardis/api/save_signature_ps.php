<?php
// api/save_signature_ps.php
require_once __DIR__ . '/../../../sercon/bootstrap.php';

header('Content-Type: application/json');

if (function_exists('isLoggedIn') && !isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    $pdo = getProjectDBConnection('pardis');
    
    $report_id = $_POST['report_id'] ?? null;
    $signature_data = $_POST['signature_data'] ?? null;
    $role = $_POST['role'] ?? 'contractor';
    
    if (!$report_id || !$signature_data) {
        throw new Exception('داده‌های ناقص است');
    }
    
    $valid_roles = [
        'contractor' => 'signature_contractor',
        'consultant' => 'signature_consultant', 
        'employer'   => 'signature_employer'
    ];
    
    if (!array_key_exists($role, $valid_roles)) {
        throw new Exception('نقش نامعتبر است');
    }
    $db_column = $valid_roles[$role];

    if (strpos($signature_data, 'data:image/png;base64,') !== 0) {
        throw new Exception('فرمت تصویر نامعتبر است');
    }
    
    $img_data = str_replace('data:image/png;base64,', '', $signature_data);
    $img_data = str_replace(' ', '+', $img_data);
    $decoded = base64_decode($img_data);
    
    if (!$decoded) {
        throw new Exception('خطا در پردازش تصویر');
    }
    
    $upload_dir_abs = __DIR__ . '/../uploads/signatures/';
    
    if (!is_dir($upload_dir_abs)) {
        mkdir($upload_dir_abs, 0775, true);
    }
    
    $filename = 'sig_ps_' . $role . '_' . $report_id . '_' . time() . '.png';
    $file_path_abs = $upload_dir_abs . $filename;
    
    // Store relative path without /pardis/ prefix
    $web_path = 'uploads/signatures/' . $filename;
    
    // Delete old signature
    $stmt = $pdo->prepare("SELECT {$db_column} FROM ps_daily_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $old_signature = $stmt->fetchColumn();
    
    if ($old_signature) {
        $old_file = __DIR__ . '/../' . $old_signature;
        if (file_exists($old_file)) {
            unlink($old_file);
        }
    }
    
    if (!file_put_contents($file_path_abs, $decoded)) {
        throw new Exception('خطا در ذخیره فایل');
    }
    
    $stmt = $pdo->prepare("UPDATE ps_daily_reports SET {$db_column} = ? WHERE id = ?");
    $stmt->execute([$web_path, $report_id]);
    
    echo json_encode([
        'success' => true, 
        'path' => $web_path,
        'filename' => $filename
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>