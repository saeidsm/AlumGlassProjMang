<?php
// includes/security.php — CSRF protection, file upload validation

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token = null): bool {
    $token = $token ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(generateCsrfToken()) . '">';
}

function requireCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken()) {
        http_response_code(403);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
            str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            exit(json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']));
        }
        exit('Invalid CSRF token');
    }
}

function validateUpload(array $file, array $allowedExtensions = ['pdf','jpg','jpeg','png'], int $maxSize = 5242880): array {
    $errors = [];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload failed with error code: ' . $file['error'];
        return $errors;
    }
    if ($file['size'] > $maxSize) {
        $errors[] = 'File too large. Max: ' . round($maxSize / 1048576, 1) . 'MB';
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions);
    }
    // MIME type check
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'csv' => ['text/csv', 'text/plain'],
        'txt' => 'text/plain',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
    ];
    if (isset($allowedMimes[$ext])) {
        $expected = (array)$allowedMimes[$ext];
        if (!in_array($mimeType, $expected, true)) {
            $errors[] = "MIME type mismatch for .$ext: got $mimeType";
        }
    }
    return $errors;
}
