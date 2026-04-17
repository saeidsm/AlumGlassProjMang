<?php

/**
 * PHPUnit test bootstrap.
 *
 * Provides a minimal runtime so unit tests can exercise helper functions
 * (validation, CSRF, pagination, project context) without any database,
 * HTTP, or full app bootstrap dependency.
 */

define('TESTING', true);

// Some helpers rely on constants defined in sercon/bootstrap.php. We set
// minimal equivalents here to avoid loading the full bootstrap (which
// touches DB, logs, and error handlers).
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 3600);
}
if (!defined('LOGIN_LOCKOUT_TIME')) {
    define('LOGIN_LOCKOUT_TIME', 3600);
}
if (!defined('LOGIN_ATTEMPTS_LIMIT')) {
    define('LOGIN_ATTEMPTS_LIMIT', 5);
}
if (!defined('APP_ENV')) {
    define('APP_ENV', 'testing');
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}

// Ensure $_SESSION exists as a plain array so CSRF/session-touching
// helpers work without session_start() (unavailable in CLI).
if (!isset($_SESSION)) {
    $GLOBALS['_SESSION'] = [];
    $_SESSION =& $GLOBALS['_SESSION'];
}

// Provide escapeHtml/e since security.php references e().
if (!function_exists('escapeHtml')) {
    function escapeHtml(?string $str): string
    {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('e')) {
    function e(?string $str): string
    {
        return escapeHtml($str);
    }
}

// Load subject-under-test files. Order matters: security.php uses e().
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/pagination.php';
require_once __DIR__ . '/../shared/includes/project_context.php';
require_once __DIR__ . '/../shared/repositories/ElementRepository.php';
require_once __DIR__ . '/../shared/repositories/InspectionRepository.php';
require_once __DIR__ . '/../shared/repositories/DailyReportRepository.php';
require_once __DIR__ . '/../shared/repositories/UserRepository.php';
