<?php
/**
 * Backward-compatibility shim.
 *
 * The legacy messages.php page has been replaced by the /chat/ module
 * (Phase 4A). Existing bookmarks and links like
 *   /messages.php?user_id=42
 * are redirected to the equivalent chat deep-link, which ChatApp
 * resolves by find-or-creating a direct conversation.
 */

require_once __DIR__ . '/sercon/bootstrap.php';
secureSession();

$target = '/chat/';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId > 0) {
    $target .= '?user_id=' . $userId;
} elseif (!empty($_GET['conversation'])) {
    $target .= '?conversation=' . (int)$_GET['conversation'];
}

header('Location: ' . $target, true, 301);
exit;
