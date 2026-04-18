<?php
/**
 * POST /chat/api/direct.php
 *   → Find or create a direct (1-to-1) conversation between the current
 *     user and the target user. Returns {conversationId}.
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
$peerId = (int)($_POST['peer_id'] ?? 0);

if ($peerId <= 0 || $peerId === $userId) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid peer']));
}

// Validate peer exists
$stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$peerId]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => 'Peer not found']));
}

// Look for an existing direct conversation between these two users
$stmt = $pdo->prepare("
    SELECT c.id
    FROM conversations c
    JOIN conversation_members m1 ON m1.conversation_id = c.id AND m1.user_id = ?
    JOIN conversation_members m2 ON m2.conversation_id = c.id AND m2.user_id = ?
    WHERE c.type = 'direct'
    LIMIT 1
");
$stmt->execute([$userId, $peerId]);
$existing = $stmt->fetchColumn();
if ($existing) {
    echo json_encode(['success' => true, 'conversationId' => (int)$existing, 'created' => false]);
    exit;
}

try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO conversations (type, created_by) VALUES ('direct', ?)")->execute([$userId]);
    $convId = (int)$pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO conversation_members (conversation_id, user_id, role) VALUES (?, ?, ?)');
    $ins->execute([$convId, $userId, 'member']);
    $ins->execute([$convId, $peerId, 'member']);
    $pdo->commit();
    echo json_encode(['success' => true, 'conversationId' => $convId, 'created' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    logError('Direct create failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create conversation']);
}
