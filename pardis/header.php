<?php
/**
 * pardis/header.php — Unified responsive header dispatcher
 *
 * Delegates to the desktop (header_pardis.php) or mobile (header_pardis_mobile.php)
 * implementation based on user-agent, then emits shared design-system assets.
 *
 * Callers should `require_once __DIR__ . '/header.php';` — NEVER reference the
 * underlying implementation files directly.
 */

if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../sercon/bootstrap.php';
}

if (!function_exists('ag_is_mobile_request')) {
    function ag_is_mobile_request(): bool {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua === '') return false;
        return (bool) preg_match(
            '/(Mobile|Android|iPhone|iPod|Opera Mini|IEMobile|BlackBerry|webOS|Windows Phone)/i',
            $ua
        );
    }
}

if (ag_is_mobile_request()) {
    require_once __DIR__ . '/header_pardis_mobile.php';
} else {
    require_once __DIR__ . '/header_pardis.php';
}
