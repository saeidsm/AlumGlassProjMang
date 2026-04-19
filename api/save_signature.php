<?php
// api/save_signature.php - UPDATED FOR ROLES
require_once __DIR__ . '/../sercon/bootstrap.php';

header('Content-Type: application/json');

if (function_exists('isLoggedIn') && !isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
require_once __DIR__ . '/../includes/security.php';
requireCsrf();

try {
    $pdo = getProjectDBConnection('ghom');
    
    $report_id = $_POST['report_id'] ?? null;
    $signature_data = $_POST['signature_data'] ?? null;
    $role = $_POST['role'] ?? 'contractor'; // Default to contractor
    
    if (!$report_id || !$signature_data) {
        throw new Exception('داده‌های ناقص');
    }
    
    // Define valid roles and their DB columns
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
        throw new Exception('فرمت امضا نامعتبر است');
    }
    
    $img_data = str_replace('data:image/png;base64,', '', $signature_data);
    $img_data = str_replace(' ', '+', $img_data);
    $decoded = base64_decode($img_data);
    
    if (!$decoded) {
        throw new Exception('خطا در پردازش امضا');
    }
    
    $upload_dir = dirname(__DIR__) . '/uploads/signatures/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0775, true)) {
             throw new Exception('خطا در ایجاد پوشه ذخیره سازی');
        }
    }
    
    // Append role to filename to keep them separate
    $filename = 'sig_' . $role . '_' . $report_id . '_' . time() . '.png';
    $physical_filepath = $upload_dir . $filename;
    $web_path = '/ghom/uploads/signatures/' . $filename; 
    
    if (!file_put_contents($physical_filepath, $decoded)) {
        throw new Exception('خطا در ذخیره فایل امضا بر روی دیسک');
    }
    
    // Update the specific column based on role
    $stmt = $pdo->prepare("UPDATE daily_reports SET {$db_column} = ? WHERE id = ?");
    $stmt->execute([$web_path, $report_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'امضا با موفقیت ذخیره شد',
        'signature_path' => $web_path,
        'role' => $role
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}