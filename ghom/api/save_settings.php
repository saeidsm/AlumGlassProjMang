<?php
// ghom/api/save_settings.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';

if (!isLoggedIn()) exit(json_encode(['status'=>'error', 'message'=>'Auth required']));
require_once __DIR__ . '/../../includes/security.php';
requireCsrf();

// --- SECURITY CHECK ---
if ($_SESSION['role'] !== 'superuser') {
    http_response_code(403);
    exit(json_encode(['status'=>'error', 'message'=>'دسترسی غیرمجاز. فقط مدیر ارشد مجاز است.']));
}

$pdo = getProjectDBConnection('ghom');

try {
    // Check if key/value exist
    if (!isset($_POST['key']) || !isset($_POST['value'])) throw new Exception("Invalid Data");
    
    $stmt = $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute([$_POST['key'], $_POST['value']]);

    echo json_encode(['status'=>'success']);
} catch (Exception $e) {
    echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
}