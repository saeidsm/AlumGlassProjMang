<?php
/**
 * GET /chat/api/search.php?q=<term>&conversation_id=<id?>
 *   → Full-text-ish LIKE search across the current user's conversations.
 *     Returns up to 50 most recent matching messages.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Authentication required']));
}

$pdo = getCommonDBConnection();
$userId = (int)$_SESSION['user_id'];
$query = trim((string)($_GET['q'] ?? ''));
$convId = isset($_GET['conversation_id']) && $_GET['conversation_id'] !== ''
    ? (int)$_GET['conversation_id']
    : null;

if (mb_strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit;
}

$sql = "SELECT m.id, m.conversation_id, m.message_content, m.message_type, m.timestamp,
               u.first_name, u.last_name,
               c.name AS conv_name, c.type AS conv_type
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        JOIN conversation_members cm ON cm.conversation_id = m.conversation_id AND cm.user_id = ?
        JOIN conversations c ON c.id = m.conversation_id
        WHERE m.message_content LIKE ? AND m.is_deleted = 0";
$params = [$userId, '%' . $query . '%'];

if ($convId) {
    $sql .= ' AND m.conversation_id = ?';
    $params[] = $convId;
}

$sql .= ' ORDER BY m.id DESC LIMIT 50';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['success' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'query' => $query]);
