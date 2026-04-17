<?php
// /ghom/api/confirm_panels_opened.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Authentication required']));
}

$data = json_decode(file_get_contents('php://input'), true);
$element_ids = $data['element_ids'] ?? [];
$userId = $_SESSION['user_id'];

if (empty($element_ids)) {
    throw new Exception("No elements selected.");
}

$pdo = getProjectDBConnection('ghom');

// Prepare a query that ONLY updates panels that were approved
// This prevents contractors from marking unapproved panels as opened.
$placeholders = implode(',', array_fill(0, count($element_ids), '?'));
$sql = "
    UPDATE inspections 
    SET status = 'Panel Opened', user_id = ?
    WHERE element_id IN ($placeholders) AND status = 'Opening Approved'
";

$stmt = $pdo->prepare($sql);
$params = array_merge([$userId], $element_ids);
$stmt->execute($params);

$affectedRows = $stmt->rowCount();

echo json_encode([
    'status' => 'success',
    'message' => "تعداد $affectedRows پانل با موفقیت به عنوان 'بازگشایی شده' ثبت شد."
]);
