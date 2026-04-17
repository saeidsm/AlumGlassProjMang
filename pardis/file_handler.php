<?php
// file_handler.php - Secure file serving and viewing
session_start();


$conn = getLetterTrackingDBConnection();

// Get file ID from request
$file_id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? 'download'; // download, view, or inline

if (!$file_id) {
    http_response_code(404);
    die('File not found');
}

// Get file information from database
$stmt = $conn->prepare("SELECT * FROM letter_attachments WHERE id = ?");
$stmt->execute([$file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file || !file_exists($file['file_path'])) {
    http_response_code(404);
    die('File not found');
}

// Security check: Verify file is within allowed directory
$realPath = realpath($file['file_path']);
$allowedPath = realpath('./letter_storage/');

if (strpos($realPath, $allowedPath) !== 0) {
    http_response_code(403);
    die('Access denied');
}

// Get file information
$fileSize = filesize($file['file_path']);
$fileName = $file['original_filename'];
$mimeType = $file['file_type'] ?: 'application/octet-stream';

// Handle different actions
if ($action === 'view' || $action === 'inline') {
    // For inline viewing (PDFs, images, text)
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    header('Cache-Control: public, max-age=3600');
    
    readfile($file['file_path']);
    exit;
    
} elseif ($action === 'text') {
    // Extract text content for viewing
    header('Content-Type: text/plain; charset=utf-8');
    
    $extension = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
    
    // For text files, read directly
    if (in_array($extension, ['txt', 'log', 'csv', 'json', 'xml', 'html', 'css', 'js', 'php', 'md'])) {
        readfile($file['file_path']);
        exit;
    }
    
    // For other files, try to extract text if available in database
    if ($file['extracted_text']) {
        echo $file['extracted_text'];
        exit;
    }
    
    // If no text available
    echo "No text content available for this file.";
    exit;
    
} else {
    // Default: Force download
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    
    // Support for large files and resume
    if (isset($_SERVER['HTTP_RANGE'])) {
        // Handle range requests for large files
        $range = $_SERVER['HTTP_RANGE'];
        preg_match('/bytes=(\d+)-(\d*)/i', $range, $matches);
        
        $start = intval($matches[1]);
        $end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;
        $length = $end - $start + 1;
        
        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        header('Content-Length: ' . $length);
        
        $fp = fopen($file['file_path'], 'rb');
        fseek($fp, $start);
        echo fread($fp, $length);
        fclose($fp);
    } else {
        // Normal download
        readfile($file['file_path']);
    }
    exit;
}
?>