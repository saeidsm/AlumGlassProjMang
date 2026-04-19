<?php
// api/save_print_settings_ps.php
require_once __DIR__ . '/../../sercon/bootstrap.php';

header('Content-Type: application/json');

session_start();

if (function_exists('isLoggedIn') && !isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
require_once __DIR__ . '/../../includes/security.php';
requireCsrf();

try {
    $pdo = getProjectDBConnection('pardis');
    
    $settings_json = $_POST['settings_json'] ?? null;
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!$settings_json || !$user_id) {
        throw new Exception('داده‌های ناقص است');
    }
    
    // Validate JSON
    $decoded = json_decode($settings_json, true);
    if (!$decoded) {
        throw new Exception('فرمت JSON نامعتبر است');
    }
    
    // Check if settings exist for this user
    $stmt = $pdo->prepare("SELECT id FROM ps_print_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $exists = $stmt->fetchColumn();
    
    if ($exists) {
        // Update existing settings
        $stmt = $pdo->prepare("UPDATE ps_print_settings SET settings_json = ? WHERE user_id = ?");
        $stmt->execute([$settings_json, $user_id]);
    } else {
        // Insert new settings
        $stmt = $pdo->prepare("INSERT INTO ps_print_settings (user_id, settings_json) VALUES (?, ?)");
        $stmt->execute([$user_id, $settings_json]);
    }
    
    echo json_encode(['success' => true, 'message' => 'تنظیمات با موفقیت ذخیره شد']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>