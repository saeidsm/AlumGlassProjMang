<?php
/**
 * POST /chat/api/upload.php
 *   → Standalone file upload (detached from a message). Useful for
 *     drag-drop previews before the user hits Send. Returns the
 *     reference id so the client can attach it to the next POST.
 *
 * Body: file (required), module (default=chat), entity_type (default=message),
 *       entity_id (default=0, can be patched when the message is created)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/../../shared/services/FileService.php';
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

if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'file required']));
}

$module = preg_match('/^[a-z_]{1,50}$/', (string)($_POST['module'] ?? 'chat'))
    ? (string)$_POST['module'] : 'chat';
$entityType = preg_match('/^[a-z_]{1,50}$/', (string)($_POST['entity_type'] ?? 'message'))
    ? (string)$_POST['entity_type'] : 'message';
$entityId = max(0, (int)($_POST['entity_id'] ?? 0));

try {
    $pdo = getCommonDBConnection();
    $service = new FileService($pdo);
    $result = $service->store($_FILES['file'], $module, $entityType, $entityId, (int)$_SESSION['user_id']);
    echo json_encode(['success' => true] + $result);
} catch (Throwable $e) {
    logError('Chat upload failed: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
