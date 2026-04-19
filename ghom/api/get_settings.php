<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Authentication required']));
}
$pdo = getProjectDBConnection('ghom');
$rows = $pdo->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_ASSOC);
$settings = [];
foreach($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
echo json_encode($settings);