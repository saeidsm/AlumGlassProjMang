<?php
//ghom/api/mark_notifications_as_read.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) { http_response_code(403); exit(json_encode(['status' => 'error'])); }

$user_id = $_SESSION['user_id'];
$pdo = getProjectDBConnection('ghom');

// Correctly update ONLY the is_read flag for inbound messages.
$sql = "UPDATE notifications SET is_read = 1, viewed_at = NOW() 
        WHERE user_id = ? AND is_read = 0";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);

echo json_encode(['status' => 'success', 'marked_count' => $stmt->rowCount()]);