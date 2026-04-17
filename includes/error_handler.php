<?php
// includes/error_handler.php — Global error and exception handlers

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    logError("PHP Error [$severity]: $message", ['file' => $file, 'line' => $line]);
    return true;
});

set_exception_handler(function (\Throwable $e): void {
    logError("Uncaught Exception: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    if (defined('APP_DEBUG') && APP_DEBUG) {
        http_response_code(500);
        echo "<pre>Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "\n"
            . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "</pre>";
    } else {
        http_response_code(500);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
        } else {
            echo '<h1>خطای سرور</h1><p>لطفاً دوباره تلاش کنید.</p>';
        }
    }
    exit();
});
