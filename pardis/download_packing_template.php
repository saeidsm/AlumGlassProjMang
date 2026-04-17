<?php
// public_html/pardis/download_packing_template.php

require_once __DIR__ . '/../../sercon/bootstrap.php';

// Security: Make sure the user is logged in before allowing the download.
secureSession();
if (!isLoggedIn()) {
    // We can't use a redirect header here, so just deny access.
    http_response_code(403); // Forbidden
    exit('Authentication required to download this file.');
}

// --- Simple & Robust File Download Logic ---

// 1. Define the path to your pre-made Excel file.
$filePath = __DIR__ . '/packing_template_sample.xlsx';
$downloadFilename = 'Template_Packing_Lists_' . date('Y-m-d') . '.xlsx';

// 2. Check if the file actually exists on the server.
if (!file_exists($filePath)) {
    http_response_code(404); // Not Found
    exit('Error: The template file could not be found on the server.');
}

// 3. Clear any potential stray output that might have occurred.
if (ob_get_level()) {
    ob_end_clean();
}

// 4. Send all the necessary headers for a clean download.
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath)); // This is crucial for preventing corruption.

// 5. Read the file and send its contents to the browser.
readfile($filePath);

// 6. Stop the script to ensure nothing else is sent.
exit;