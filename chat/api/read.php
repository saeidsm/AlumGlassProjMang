<?php
/**
 * POST /chat/api/read.php
 *   → Mark a conversation as read up to NOW for the current user.
 *     Updates conversation_members.last_read_at.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Authentication required']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

requireCsrf();

$pdo = getCommonDBConnection();
$userId = (int)$_SESSION['user_id'];
$convId = (int)($_POST['conversation_id'] ?? 0);

if ($convId <= 0) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'conversation_id required']));
}

$stmt = $pdo->prepare('SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ?');
$stmt->execute([$convId, $userId]);
if (!$stmt->fetchColumn()) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Not a member']));
}

$pdo->prepare('UPDATE conversation_members SET last_read_at = NOW() WHERE conversation_id = ? AND user_id = ?')
    ->execute([$convId, $userId]);
$pdo->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0')
    ->execute([$convId, $userId]);

echo json_encode(['success' => true]);
