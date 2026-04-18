<?php
/**
 * GET  /chat/api/conversations.php
 *   → list the current user's conversations with last message, unread count,
 *     and display name/avatar (resolved for direct chats).
 *
 * POST /chat/api/conversations.php
 *   → create a new group conversation. Body: name, members[].
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("
        SELECT
            c.id, c.type, c.name, c.avatar_path, c.updated_at,
            (SELECT m.message_content FROM messages m WHERE m.conversation_id = c.id AND m.is_deleted = 0 ORDER BY m.id DESC LIMIT 1) AS last_message,
            (SELECT m.timestamp      FROM messages m WHERE m.conversation_id = c.id AND m.is_deleted = 0 ORDER BY m.id DESC LIMIT 1) AS last_message_time,
            (SELECT m.message_type   FROM messages m WHERE m.conversation_id = c.id AND m.is_deleted = 0 ORDER BY m.id DESC LIMIT 1) AS last_message_type,
            (SELECT m.sender_id      FROM messages m WHERE m.conversation_id = c.id AND m.is_deleted = 0 ORDER BY m.id DESC LIMIT 1) AS last_message_sender,
            (SELECT COUNT(*)
               FROM messages m
              WHERE m.conversation_id = c.id
                AND m.sender_id != :user_self
                AND m.is_deleted = 0
                AND m.timestamp > COALESCE(cm.last_read_at, '1970-01-01')
            ) AS unread_count,
            CASE WHEN c.type = 'direct' THEN (
                SELECT CONCAT(u.first_name, ' ', u.last_name)
                FROM conversation_members cm2
                JOIN users u ON cm2.user_id = u.id
                WHERE cm2.conversation_id = c.id AND cm2.user_id != :user_other LIMIT 1
            ) ELSE c.name END AS display_name,
            CASE WHEN c.type = 'direct' THEN (
                SELECT u.avatar_path
                FROM conversation_members cm2
                JOIN users u ON cm2.user_id = u.id
                WHERE cm2.conversation_id = c.id AND cm2.user_id != :user_avatar LIMIT 1
            ) ELSE c.avatar_path END AS display_avatar,
            CASE WHEN c.type = 'direct' THEN (
                SELECT cm2.user_id
                FROM conversation_members cm2
                WHERE cm2.conversation_id = c.id AND cm2.user_id != :user_peer LIMIT 1
            ) ELSE NULL END AS peer_user_id
        FROM conversations c
        JOIN conversation_members cm ON cm.conversation_id = c.id AND cm.user_id = :user_member
        ORDER BY COALESCE(last_message_time, c.updated_at) DESC
    ");
    $stmt->execute([
        'user_self' => $userId,
        'user_other' => $userId,
        'user_avatar' => $userId,
        'user_peer' => $userId,
        'user_member' => $userId,
    ]);

    echo json_encode(['success' => true, 'conversations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $name = trim($_POST['name'] ?? '');
    $membersRaw = $_POST['members'] ?? '[]';
    $memberIds = is_array($membersRaw) ? $membersRaw : (json_decode($membersRaw, true) ?: []);
    $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds))));

    if ($name === '' || count($memberIds) < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Name and at least one member are required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('INSERT INTO conversations (type, name, created_by) VALUES (\'group\', ?, ?)');
        $stmt->execute([$name, $userId]);
        $convId = (int)$pdo->lastInsertId();

        $insert = $pdo->prepare('INSERT INTO conversation_members (conversation_id, user_id, role) VALUES (?, ?, ?)');
        $insert->execute([$convId, $userId, 'admin']);
        foreach ($memberIds as $memberId) {
            if ($memberId === $userId) continue;
            $insert->execute([$convId, $memberId, 'member']);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'conversationId' => $convId]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        logError('Create group failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create group']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH' || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'find_or_create_direct')) {
    // Find or create a direct conversation with the given user id.
    // Not wired from the GET/POST branches above — kept as a reference.
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
