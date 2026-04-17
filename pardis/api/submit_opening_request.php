<?php // /pardis/api/submit_opening_request.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Forbidden: وارد نشده‌اید.']));
}
// ... security checks ...

$data = json_decode(file_get_contents('php://input'), true);
$element_ids = $data['element_ids'] ?? [];
$request_date = $data['date'] ?? date('Y-m-d');
$notes = $data['notes'] ?? '';
$userId = $_SESSION['user_id'];

if (empty($element_ids)) {
    throw new Exception("No elements selected.");
}

$pdo = getProjectDBConnection('pardis');
$pdo->beginTransaction();

$sql = "
    INSERT INTO inspections (element_id, stage_id, user_id, status, contractor_date, contractor_notes, inspection_cycle) 
    VALUES (?, ?, ?, 'Request to Open', ?, ?, 1)
    ON DUPLICATE KEY UPDATE 
    user_id = VALUES(user_id), 
    status = VALUES(status), 
    contractor_date = VALUES(contractor_date), 
    contractor_notes = VALUES(contractor_notes),
    inspection_cycle = inspection_cycle + 1;
";
$stmt = $pdo->prepare($sql);

// For this workflow, we can associate the request with a default "opening" stage (e.g., stage_id = 0 or a specific ID you create)
$default_opening_stage_id = 0;

foreach ($element_ids as $elementId) {
    $stmt->execute([$elementId, $default_opening_stage_id, $userId, $request_date, $notes]);
}

$pdo->commit();
echo json_encode(['status' => 'success', 'message' => 'درخواست بازگشایی با موفقیت ثبت شد.']);
