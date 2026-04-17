<?php
// /pardis/api/delete_scaffolding.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();

if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}
require_once __DIR__ . '/../../includes/security.php';
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$plan_file = $_POST['plan_file'] ?? null;

if (!$plan_file) {
    http_response_code(400);
    exit(json_encode(['error' => 'نام فایل نقشه الزامی است']));
}

try {
    $pdo = getProjectDBConnection('pardis');
    
    $sql = "DELETE FROM scaffolding_drawings WHERE plan_file = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plan_file]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'تمام ترسیم‌های داربست با موفقیت پاک شد'
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'message' => 'هیچ ترسیم داربستی برای پاک کردن یافت نشد'
        ]);
    }

} catch (Exception $e) {
    error_log("Delete scaffolding error: " . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'خطا در پاک کردن داربست: ' . $e->getMessage()]));
}
?>