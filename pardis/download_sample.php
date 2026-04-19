<?php
// download_sample.php

// 1. Include bootstrap and run security checks
require_once __DIR__ . '/../sercon/bootstrap.php';
secureSession();

if (!isLoggedIn()) {
    // We can't redirect, so just send an error
    http_response_code(403);
    exit('Access Denied: Please log in.');
}

// 2. Define file path and name
$fileName = 'sample_tasks_import.csv';
$filePath = __DIR__ . '/' . $fileName;

// 3. Check if the file exists
if (!file_exists($filePath)) {
    http_response_code(404);
    exit("Error: Sample file not found on the server at path: " . $filePath);
}

// 4. Set headers to force download
header('Content-Description: File Transfer');
header('Content-Type: text/csv; charset=utf-8'); // Specify UTF-8 for Persian characters
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

// 5. Read the file and send it to the browser
ob_clean(); // Clean (erase) the output buffer
flush(); // Flush the system output buffer
readfile($filePath);
exit;