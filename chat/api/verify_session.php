<?php
/**
 * Verifies a PHP session token for the Node.js WebSocket relay.
 *
 * The WS server POSTs {token: "<PHPSESSID>"} and we respond with
 * {valid: true, userId, username, role} if the session is active.
 *
 * This endpoint should be restricted to loopback requests in production
 * (enforce via .htaccess or nginx). It is never linked publicly.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
$trusted = ['127.0.0.1', '::1'];
if (getenv('WS_VERIFY_ALLOW_REMOTE') !== '1' && !in_array($remote, $trusted, true)) {
    http_response_code(403);
    echo json_encode(['valid' => false, 'error' => 'remote not allowed']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
$token = is_array($input) ? ($input['token'] ?? '') : '';

if (!preg_match('/^[A-Za-z0-9_,\-]{16,128}$/', $token)) {
    echo json_encode(['valid' => false, 'error' => 'bad token']);
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
session_id($token);
session_start();

if (!empty($_SESSION['user_id'])) {
    echo json_encode([
        'valid' => true,
        'userId' => (int)$_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? '',
    ]);
} else {
    echo json_encode(['valid' => false]);
}
