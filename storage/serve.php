<?php
/**
 * Secure file serving endpoint for /storage/<sharded-path>.
 *
 * Verifies the user is authenticated, enforces path format so the
 * request cannot escape the storage root, and sets immutable caching
 * headers (file name contains its SHA-256, so content never changes).
 */

require_once __DIR__ . '/../sercon/bootstrap.php';
secureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Auth required');
}

$requestPath = ltrim($_SERVER['PATH_INFO'] ?? '', '/');

if (!preg_match('#^[a-f0-9]{2}/[a-f0-9]{2}/[a-f0-9]{64}\.[a-z0-9]{1,10}$#', $requestPath)) {
    http_response_code(400);
    exit('Invalid file path');
}

$fullPath = __DIR__ . '/' . $requestPath;

if (!is_file($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

$realPath = realpath($fullPath);
$storageDir = realpath(__DIR__);
if ($realPath === false || strncmp($realPath, $storageDir, strlen($storageDir)) !== 0) {
    http_response_code(403);
    exit('Access denied');
}

$mime = function_exists('mime_content_type') ? (mime_content_type($fullPath) ?: 'application/octet-stream') : 'application/octet-stream';
$size = filesize($fullPath);
$etag = '"' . pathinfo($requestPath, PATHINFO_FILENAME) . '"';

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');

$inlineMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'application/pdf', 'video/mp4', 'audio/mpeg', 'audio/ogg', 'audio/wav'];
$disposition = in_array($mime, $inlineMimes, true) ? 'inline' : 'attachment';
header('Content-Disposition: ' . $disposition);

readfile($fullPath);
