<?php

/**
 * Post-migration verification.
 *
 * Runs a set of non-destructive checks against the freshly migrated
 * environment. Exits 0 if everything passes, 1 otherwise.
 *
 * Usage:
 *   php scripts/migrate_verify.php
 */

// Load the full bootstrap so we exercise the real DB connections and helpers.
require_once __DIR__ . '/../sercon/bootstrap.php';

$checks = [];
$record = function (string $name, bool $pass, string $detail = '') use (&$checks): void {
    $checks[] = ['name' => $name, 'pass' => $pass, 'detail' => $detail];
};

// ── 1. Database connectivity ────────────────────────────────────────────────
try {
    $common = getCommonDBConnection();
    $users = (int) $common->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $record('DB: common (users)', $users >= 0, "$users rows");
} catch (Throwable $e) {
    $record('DB: common (users)', false, $e->getMessage());
}

foreach (['ghom', 'pardis'] as $project) {
    try {
        $pdo = getProjectDBConnection($project);
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $record("DB: $project", count($tables) > 0, count($tables) . ' tables');
    } catch (Throwable $e) {
        $record("DB: $project", false, $e->getMessage());
    }
}

// ── 2. Environment variables ────────────────────────────────────────────────
$required = [
    'DB_HOST', 'DB_USERNAME', 'DB_PASSWORD',
    'TELEGRAM_BOT_TOKEN', 'APP_ENV', 'APP_URL',
];
foreach ($required as $key) {
    $value = getenv($key);
    $record("ENV: $key", $value !== false && $value !== '', $value === false ? 'missing' : 'set');
}

// ── 3. Filesystem ───────────────────────────────────────────────────────────
$projectRoot = dirname(__DIR__);
$record('File: .env present', file_exists("$projectRoot/.env"));
if (file_exists("$projectRoot/.env")) {
    $perms = fileperms("$projectRoot/.env") & 0777;
    $record('File: .env mode 0600', $perms === 0600, sprintf('mode=0%o', $perms));
}
$record('Dir: logs writable', is_dir("$projectRoot/logs") && is_writable("$projectRoot/logs"));
$record('Dir: ghom/uploads', is_dir("$projectRoot/ghom/uploads"));
$record('Dir: pardis/uploads', is_dir("$projectRoot/pardis/uploads"));

// ── 4. Code layout (Phase 3 additions) ──────────────────────────────────────
$record('Code: shared/api/ exists', is_dir("$projectRoot/shared/api"));
$record('Code: shared/includes/project_context.php', file_exists("$projectRoot/shared/includes/project_context.php"));
$record('Code: shared/repositories/', is_dir("$projectRoot/shared/repositories"));

// ── 5. Dangerous files must be absent ───────────────────────────────────────
foreach (['info.php', 'phpinfo.php', 'localhost.sql.txt', 'test_webhook.php'] as $bad) {
    $record("Clean: no $bad", !file_exists("$projectRoot/$bad"));
}

// ── 6. Key application files ────────────────────────────────────────────────
foreach (['index.php', 'login.php', 'sercon/bootstrap.php', 'includes/security.php', '.htaccess'] as $f) {
    $record("App: $f", file_exists("$projectRoot/$f"));
}

// ── Output ──────────────────────────────────────────────────────────────────
echo "AlumGlass — post-migration verification\n";
echo str_repeat('=', 50) . "\n";

$failed = 0;
foreach ($checks as $c) {
    $icon = $c['pass'] ? '[OK] ' : '[FAIL]';
    $line = $icon . ' ' . $c['name'];
    if ($c['detail'] !== '') {
        $line .= ' — ' . $c['detail'];
    }
    echo $line . "\n";
    if (!$c['pass']) {
        $failed++;
    }
}

echo str_repeat('=', 50) . "\n";
if ($failed === 0) {
    echo "All " . count($checks) . " checks passed.\n";
    exit(0);
}
echo "$failed of " . count($checks) . " checks FAILED.\n";
exit(1);
