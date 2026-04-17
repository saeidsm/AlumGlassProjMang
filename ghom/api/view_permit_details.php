<?php
// /ghom/view_permit_details.php (CORRECTED FILE PATH)
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Authentication required']));
}

if (!in_array($_SESSION['role'], ['admin', 'superuser'])) {
    http_response_code(403);
    die("Access Denied.");
}

$permit_file = filter_input(INPUT_GET, 'file', FILTER_SANITIZE_STRING);

if (!$permit_file || !preg_match('/^permit_[a-zA-Z0-9_]+\.(pdf|jpg|jpeg|png)$/i', $permit_file)) {
    http_response_code(400);
    die("Invalid file name specified.");
}

// =======================================================================
// THE FIX: Use an absolute server path for maximum reliability.
// =======================================================================
$file_path = $_SERVER['DOCUMENT_ROOT'] . '/ghom/uploads/signed_permits/' . $permit_file;
// =======================================================================


if (!file_exists($file_path)) {
    http_response_code(404);
    die("File not found on server at path: " . htmlspecialchars($file_path));
}

header('Content-Type: ' . mime_content_type($file_path));
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;