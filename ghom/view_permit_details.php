<?php
// /ghom/view_permit_details.php (CORRECTED AND ROBUST)
require_once __DIR__ . '/../sercon/bootstrap.php';
secureSession();

// Security check: Only allow authorized roles to view these files.
if (!in_array($_SESSION['role'], ['admin', 'superuser', 'cat', 'car', 'coa', 'crs'])) {
    http_response_code(403);
    die("Access Denied.");
}

// THE FIX: Do not use the deprecated constant. Get the raw value.
$permit_file = $_GET['file'] ?? '';

// Security: Use a strict regex to validate the filename format. This is the most important security step.
if (!$permit_file || !preg_match('/^permit_[a-zA-Z0-9_]+\.(pdf|jpg|jpeg|png)$/i', $permit_file)) {
    http_response_code(400);
    // THE FIX: Add a more descriptive error message for debugging.
    die("Invalid or missing 'file' parameter. Value received: '" . htmlspecialchars($permit_file) . "'");
}

// Build the full, absolute path to the file.
$file_path = $_SERVER['DOCUMENT_ROOT'] . '/ghom/uploads/signed_permits/' . $permit_file;

if (!file_exists($file_path)) {
    http_response_code(404);
    die("File not found on server.");
}

// Serve the file to the browser.
header('Content-Type: ' . mime_content_type($file_path));
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;