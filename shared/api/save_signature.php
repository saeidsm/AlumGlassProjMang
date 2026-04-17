<?php
// api/save_signature.php - FIXED PATHS
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/project_context.php';

header('Content-Type: application/json');

if (function_exists('isLoggedIn') && !isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
require_once __DIR__ . '/../../includes/security.php';
requireCsrf();

try {
    $pdo = getProjectDB();
    
    $report_id = $_POST['report_id'] ?? null;
    $signature_data = $_POST['signature_data'] ?? null;
    $role = $_POST['role'] ?? 'contractor';
    
    if (!$report_id || !$signature_data) {
        throw new Exception('داده‌های ناقص (Incomplete Data)');
    }
    
    // Validate Role
    $valid_roles = [
        'contractor' => 'signature_contractor',
        'consultant' => 'signature_consultant', 
        'employer'   => 'signature_employer'
    ];
    
    if (!array_key_exists($role, $valid_roles)) {
        throw new Exception('نقش نامعتبر است (Invalid Role)');
    }
    $db_column = $valid_roles[$role];

    // Process Base64 Image
    if (strpos($signature_data, 'data:image/png;base64,') !== 0) {
        throw new Exception('فرمت امضا نامعتبر است');
    }
    
    $img_data = str_replace('data:image/png;base64,', '', $signature_data);
    $img_data = str_replace(' ', '+', $img_data);
    $decoded = base64_decode($img_data);
    
    if (!$decoded) {
        throw new Exception('خطا در پردازش امضا');
    }
    
    // --- FIX 1: CORRECT DIRECTORY PATH ---
    // __DIR__ is '.../ghom/api'
    // We want '.../ghom/uploads/signatures'
    // So we go up ONE level: '/../uploads/signatures/'
    $upload_dir = __DIR__ . '/../uploads/signatures/';
    
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0775, true)) {
             throw new Exception('خطا در ایجاد پوشه ذخیره سازی');
        }
    }
    
    // Generate Filename
    $filename = 'sig_' . $role . '_' . $report_id . '_' . time() . '.png';
    $physical_filepath = $upload_dir . $filename;
    
    // --- FIX 2: RELATIVE WEB PATH ---
    // Instead of hardcoding '/ghom/...', we use a relative path.
    // When the frontend (daily_report_print.php) loads this, 'uploads/...' 
    // will correctly resolve relative to the project root.
    $web_path = 'uploads/signatures/' . $filename; 
    
    if (!file_put_contents($physical_filepath, $decoded)) {
        throw new Exception('خطا در ذخیره فایل امضا بر روی دیسک');
    }
    
    // Update DB
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