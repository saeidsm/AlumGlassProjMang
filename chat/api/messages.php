<?php
/**
 * GET  /chat/api/messages.php?conversation_id=...&before=<id>&limit=<n>
 *   → Load a page of messages (newest first, cursor-paged by id).
 *
 * POST /chat/api/messages.php
 *   → Send a new message. Supports text, file/image/audio/video (via FileService),
 *     reply_to_id, and conversation_id.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/../../shared/services/FileService.php';
secureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Authentication required']));
}

$pdo = getCommonDBConnection();
$userId = (int)$_SESSION['user_id'];

function assertMembership(PDO $pdo, int $convId, int $userId): void
{
    $stmt = $pdo->prepare('SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ?');
    $stmt->execute([$convId, $userId]);
    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'message' => 'Not a member of this conversation']));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $convId = (int)($_GET['conversation_id'] ?? 0);
    $before = isset($_GET['before']) && $_GET['before'] !== '' ? (int)$_GET['before'] : null;
    $limit = max(10, min(100, (int)($_GET['limit'] ?? 30)));

    if ($convId <= 0) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'conversation_id required']));
    }
    assertMembership($pdo, $convId, $userId);

    $sql = "SELECT m.id, m.conversation_id, m.sender_id, m.message_content, m.message_type,
                   m.file_path, m.caption, m.timestamp, m.is_read, m.is_deleted,
                   m.reply_to_id, m.reactions, m.file_ref_id,
                   u.first_name AS sender_fname, u.last_name AS sender_lname,
                   u.avatar_path AS sender_avatar,
                   rm.message_content AS reply_content, rm.message_type AS reply_type,
                   ru.first_name AS reply_fname, ru.last_name AS reply_lname
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            LEFT JOIN messages rm ON m.reply_to_id = rm.id
            LEFT JOIN users ru ON rm.sender_id = ru.id
            WHERE m.conversation_id = ? AND m.is_deleted = 0";
    $params = [$convId];

    if ($before !== null) {
        $sql .= ' AND m.id < ?';
        $params[] = $before;
    }

    $sql .= ' ORDER BY m.id DESC LIMIT ?';
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $i => $p) {
        $stmt->bindValue($i + 1, $p, PDO::PARAM_INT);
    }
    $stmt->execute();
    $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    // Mark this conversation as read up to now
    $pdo->prepare('UPDATE conversation_members SET last_read_at = NOW() WHERE conversation_id = ? AND user_id = ?')
        ->execute([$convId, $userId]);

    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'has_more' => count($messages) === $limit,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $convId = (int)($_POST['conversation_id'] ?? 0);
    $content = trim((string)($_POST['content'] ?? ''));
    $replyToId = !empty($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;
    $caption = trim((string)($_POST['caption'] ?? ''));

    if ($convId <= 0) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'conversation_id required']));
    }
    assertMembership($pdo, $convId, $userId);

    $hasFile = !empty($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    if ($content === '' && !$hasFile) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Content or file required']));
    }

    $messageType = 'text';
    $filePath = null;
    $fileRefId = null;

    try {
        $pdo->beginTransaction();

        // 1. Insert message row first to get an id (FileService needs entity id).
        $stmt = $pdo->prepare('
            INSERT INTO messages
                (conversation_id, sender_id, receiver_id, message_content, message_type,
                 file_path, caption, reply_to_id, timestamp, is_read, is_deleted)
            VALUES (?, ?, 0, ?, ?, ?, ?, ?, NOW(), 0, 0)
        ');
        $stmt->execute([$convId, $userId, $content !== '' ? $content : null, $messageType, null, $caption !== '' ? $caption : null, $replyToId]);
        $msgId = (int)$pdo->lastInsertId();

        // 2. If there is a file, store it via FileService (dedup), then patch the row.
        if ($hasFile) {
            $fileService = new FileService($pdo);
            $res = $fileService->store($_FILES['file'], 'chat', 'message', $msgId, $userId);

            $mime = $res['mime_type'];
            if (str_starts_with($mime, 'image/'))      $messageType = 'image';
            elseif (str_starts_with($mime, 'audio/'))  $messageType = 'audio';
            elseif (str_starts_with($mime, 'video/'))  $messageType = 'video';
            else                                       $messageType = 'file';

            $filePath = $res['url'];
            $fileRefId = $res['ref_id'];

            $upd = $pdo->prepare('UPDATE messages SET file_path = ?, message_type = ?, file_ref_id = ? WHERE id = ?');
            $upd->execute([$filePath, $messageType, $fileRefId, $msgId]);
        }

        $pdo->prepare('UPDATE conversations SET updated_at = NOW() WHERE id = ?')->execute([$convId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        logError('Send message failed: ' . $e->getMessage());
        http_response_code(500);
        exit(json_encode(['success' => false, 'message' => 'Failed to send message']));
    }

    // Fetch the full row back for the response
    $stmt = $pdo->prepare('
        SELECT m.id, m.conversation_id, m.sender_id, m.message_content, m.message_type,
               m.file_path, m.caption, m.timestamp, m.is_read, m.reply_to_id, m.file_ref_id,
               u.first_name AS sender_fname, u.last_name AS sender_lname, u.avatar_path AS sender_avatar
        FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.id = ?
    ');
    $stmt->execute([$msgId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Collect member ids for WebSocket relay targeting
    $stmt = $pdo->prepare('SELECT user_id FROM conversation_members WHERE conversation_id = ?');
    $stmt->execute([$convId]);
    $memberIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'user_id'));

    echo json_encode([
        'success' => true,
        'message' => $row,
        'member_ids' => $memberIds,
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
