# Phase 1 — Security Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate all critical security vulnerabilities (SQL injection, XSS, missing CSRF/auth) and establish centralized security infrastructure.

**Architecture:** Create three shared middleware files (`security.php`, `validation.php`, `error_handler.php`) that all pages/APIs include via `sercon/bootstrap.php`. Fix all raw SQL queries to use prepared statements. Add `.htaccess` security headers and HTTPS enforcement.

**Tech Stack:** PHP 8.4, PDO (prepared statements), Apache `.htaccess`, MariaDB 11.4

**Current State:**
- `sercon/bootstrap.php` exists on production but NOT in git — provides `getProjectDBConnection()`, `getCommonDBConnection()`, `secureSession()`, `isLoggedIn()` etc.
- `includes/security.php`, `includes/validation.php`, `includes/error_handler.php` do NOT exist
- `.htaccess` does NOT exist in git
- 30+ raw SQL queries with variable interpolation across 8 files
- 3 XSS instances in `pardis/daily_reports_dashboard_ps.php`
- No centralized CSRF on API POST endpoints

**Branch:** `claude/phase-1-security-hardening`

---

### Task 1: Create branch and security infrastructure files

**Files:**
- Create: `sercon/bootstrap.php`
- Create: `includes/security.php`
- Create: `includes/validation.php`
- Create: `includes/error_handler.php`

- [ ] **Step 1: Create Phase 1 branch**

```bash
git checkout -b claude/phase-1-security-hardening
```

- [ ] **Step 2: Create `sercon/bootstrap.php`**

This is the central bootstrap that all pages already `require_once`. It must provide:
- `.env` loading
- `getCommonDBConnection()` → PDO for `alumglas_common`
- `getProjectDBConnection('ghom'|'pardis')` → PDO for project DB
- `secureSession()` → session init with secure flags
- `isLoggedIn()` → check `$_SESSION['user_id']`
- `isAdmin()` → check `$_SESSION['role'] === 'admin'`
- `logError()` → write to `logs/` directory
- `log_activity()` → write to `activity_log` table
- Constants: `LOGIN_LOCKOUT_TIME`, `LOGIN_ATTEMPTS_LIMIT`, `SESSION_LIFETIME`
- Error reporting: `display_errors=0`, log to file

```php
<?php
// sercon/bootstrap.php — Central bootstrap for AlumGlass
// All pages require this file. It provides DB connections, session, logging.

// ── 1. Load .env ──
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// ── 2. Error handling ──
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// ── 3. Constants ──
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_DEBUG', getenv('APP_DEBUG') === 'true');
define('SESSION_LIFETIME', (int)(getenv('SESSION_LIFETIME') ?: 3600));
define('LOGIN_LOCKOUT_TIME', (int)(getenv('LOGIN_LOCKOUT_TIME') ?: 3600));
define('LOGIN_ATTEMPTS_LIMIT', (int)(getenv('LOGIN_ATTEMPTS_LIMIT') ?: 5));

// ── 4. Database connections (lazy singletons) ──
function getCommonDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = createPDO(
            getenv('DB_HOST') ?: 'localhost',
            getenv('DB_PORT') ?: '3306',
            getenv('DB_COMMON_NAME') ?: 'alumglas_common',
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
    }
    return $pdo;
}

function getProjectDBConnection(string $project): PDO {
    static $connections = [];
    if (!isset($connections[$project])) {
        $dbName = match($project) {
            'ghom' => getenv('DB_GHOM_NAME') ?: 'alumglas_hpc',
            'pardis' => getenv('DB_PARDIS_NAME') ?: 'alumglas_pardis',
            default => throw new InvalidArgumentException("Unknown project: $project"),
        };
        $connections[$project] = createPDO(
            getenv('DB_HOST') ?: 'localhost',
            getenv('DB_PORT') ?: '3306',
            $dbName,
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
    }
    return $connections[$project];
}

function createPDO(string $host, string $port, string $dbName, string $user, string $pass): PDO {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

// ── 5. Session ──
function secureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);

    session_start();

    // Session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

function initializeSession(): void {
    secureSession();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
            str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            http_response_code(401);
            exit(json_encode(['status' => 'error', 'message' => 'Authentication required']));
        }
        header('Location: /login.php');
        exit();
    }
}

function requireRole(array $allowedRoles): void {
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', $allowedRoles, true)) {
        http_response_code(403);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
            str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            exit(json_encode(['status' => 'error', 'message' => 'Access denied']));
        }
        include __DIR__ . '/../unauthorized.php';
        exit();
    }
}

// ── 6. Logging ──
function logError(string $message, array $context = []): void {
    $entry = json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'ERROR',
        'message' => $message,
        'user_id' => $_SESSION['user_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'file' => $context['file'] ?? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? 'unknown',
        'line' => $context['line'] ?? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0,
    ], JSON_UNESCAPED_UNICODE);
    error_log($entry . PHP_EOL, 3, __DIR__ . '/../logs/app_errors.log');
}

function log_activity(PDO $pdo, string $action, string $details = ''): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['user_id'] ?? 0,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    } catch (\Throwable $e) {
        logError("Activity log failed: " . $e->getMessage());
    }
}

// ── 7. Output helpers ──
function escapeHtml(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function e(?string $str): string {
    return escapeHtml($str);
}
```

- [ ] **Step 3: Create `includes/security.php`**

CSRF token generation/verification + security helpers.

```php
<?php
// includes/security.php — CSRF protection and security middleware

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
        'csv' => 'text/csv', 'txt' => 'text/plain',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
    ];
    if (isset($allowedMimes[$ext]) && $mimeType !== $allowedMimes[$ext]) {
        // Allow text/plain for CSV files
        if (!($ext === 'csv' && $mimeType === 'text/plain')) {
            $errors[] = "MIME type mismatch: expected {$allowedMimes[$ext]}, got $mimeType";
        }
    }
    return $errors;
}
```

- [ ] **Step 4: Create `includes/validation.php`**

```php
<?php
// includes/validation.php — Input validation helpers

function validateInt($value): ?int {
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    return $filtered !== false ? $filtered : null;
}

function validatePositiveInt($value): ?int {
    $val = validateInt($value);
    return ($val !== null && $val > 0) ? $val : null;
}

function validateString($value, int $maxLen = 255): ?string {
    if (!is_string($value)) return null;
    $value = trim($value);
    if ($value === '' || mb_strlen($value) > $maxLen) return null;
    return $value;
}

function validateEmail($value): ?string {
    $filtered = filter_var($value, FILTER_VALIDATE_EMAIL);
    return $filtered !== false ? $filtered : null;
}

function validateDate($value): ?string {
    if (!is_string($value)) return null;
    // Accept YYYY-MM-DD or YYYY/MM/DD
    $value = trim($value);
    if (preg_match('/^\d{4}[\/-]\d{2}[\/-]\d{2}$/', $value)) {
        return $value;
    }
    return null;
}

function validateInArray($value, array $allowed): mixed {
    return in_array($value, $allowed, true) ? $value : null;
}
```

- [ ] **Step 5: Create `includes/error_handler.php`**

```php
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
    if (APP_DEBUG) {
        http_response_code(500);
        echo "<pre>Error: " . e($e->getMessage()) . "\n" . e($e->getTraceAsString()) . "</pre>";
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
```

- [ ] **Step 6: Create `includes/` directory and verify**

```bash
mkdir -p includes
```

- [ ] **Step 7: Verify PHP syntax for all 4 files**

```bash
php -l sercon/bootstrap.php
php -l includes/security.php
php -l includes/validation.php
php -l includes/error_handler.php
```

- [ ] **Step 8: Commit**

```bash
git add sercon/bootstrap.php includes/security.php includes/validation.php includes/error_handler.php
git commit -m "feat(global): create security infrastructure — bootstrap, CSRF, validation, error handler"
```

---

### Task 2: Create `.htaccess` with security headers and HTTPS enforcement

**Files:**
- Create: `.htaccess`

- [ ] **Step 1: Create `.htaccess`**

```apache
# .htaccess — AlumGlass Security & Performance Rules

# ── HTTPS Enforcement ──
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# ── Security Headers ──
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net cdnjs.cloudflare.com unpkg.com; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.googleapis.com; font-src 'self' fonts.gstatic.com cdnjs.cloudflare.com data:; img-src 'self' data: blob: *.tile.openstreetmap.org; connect-src 'self' api.telegram.org;"
</IfModule>

# ── Block sensitive files ──
<FilesMatch "\.(env|sql|log|md|gitignore|sh|py)$">
    Require all denied
</FilesMatch>

# ── Block directory listing ──
Options -Indexes

# ── Block access to hidden files ──
<FilesMatch "^\.">
    Require all denied
</FilesMatch>

# ── Compression ──
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json text/xml application/xml
</IfModule>

# ── Caching ──
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType font/woff2 "access plus 1 year"
</IfModule>
```

- [ ] **Step 2: Commit**

```bash
git add .htaccess
git commit -m "security(global): add .htaccess with security headers, HTTPS, compression, caching"
```

---

### Task 3: Fix SQL Injection — High Priority (pardis/)

**Files:**
- Modify: `pardis/daily_report_form_ps.php` (lines 181, 195, 197, 209, 211, 233, 634, 684)
- Modify: `pardis/daily_report_print_ps.php` (lines 44, 45, 48, 61)
- Modify: `pardis/daily_report_print.php` (lines 32, 33, 36, 48)
- Modify: `pardis/daily_reports_dashboard_ps.php` (lines 423, 427, 431)
- Modify: `pardis/weekly_report_ps.php` (lines 91-154)

**Pattern:** Every `$pdo->query("... $variable ...")` becomes `$pdo->prepare("... ? ...")->execute([...])`.

For IN clauses with arrays: generate placeholders dynamically:
```php
// OLD: $pdo->query("SELECT ... WHERE id IN ($ids_str)")
// NEW:
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT ... WHERE id IN ($placeholders)");
$stmt->execute($ids);
```

- [ ] **Step 1: Fix `pardis/daily_report_form_ps.php`**

Read the file, find all 8 vulnerable queries (lines ~181, 195, 197, 209, 211, 233, 634, 684), convert each to prepared statement. Example transformations:

```php
// Line 181 — OLD:
$p_db = $pdo->query("SELECT * FROM ps_daily_report_personnel WHERE report_id=$report_id")->fetchAll(PDO::FETCH_ASSOC);
// NEW:
$stmt = $pdo->prepare("SELECT * FROM ps_daily_report_personnel WHERE report_id = ?");
$stmt->execute([$report_id]);
$p_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Line 211 — OLD:
$n = $pdo->query("SELECT name FROM ps_project_activities WHERE id={$act['activity_id']}")->fetchColumn();
// NEW:
$stmt = $pdo->prepare("SELECT name FROM ps_project_activities WHERE id = ?");
$stmt->execute([$act['activity_id']]);
$n = $stmt->fetchColumn();
```

Apply same pattern for all 8 queries.

- [ ] **Step 2: Fix `pardis/daily_report_print_ps.php`**

4 queries (lines ~44, 45, 48, 61) — all use `$report_id` directly.

```php
// Each: $pdo->query("SELECT * FROM table WHERE report_id = $report_id")
// Becomes:
$stmt = $pdo->prepare("SELECT * FROM table WHERE report_id = ?");
$stmt->execute([$report_id]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

- [ ] **Step 3: Fix `pardis/daily_report_print.php`**

4 queries (lines ~32, 33, 36, 48) — same pattern as print_ps.

- [ ] **Step 4: Fix `pardis/daily_reports_dashboard_ps.php`**

3 queries in loop (lines ~423, 427, 431) using `{$row['id']}`. Convert to prepared statements. Since these are in a loop, prepare once outside, execute inside:

```php
// BEFORE the loop:
$pStmt = $pdo->prepare("SELECT SUM(count) as total_count, SUM(count_night) as total_night FROM ps_daily_report_personnel WHERE report_id = ?");
$eStmt = $pdo->prepare("SELECT SUM(count) as total_count, SUM(count_night) as total_night FROM ps_daily_report_machinery WHERE report_id = ?");
$aStmt = $pdo->prepare("SELECT COUNT(*) FROM ps_daily_report_activities WHERE report_id = ?");

// INSIDE the loop:
$pStmt->execute([$row['id']]);
$pRow = $pStmt->fetch();
// ... etc
```

- [ ] **Step 5: Fix `pardis/weekly_report_ps.php`**

Multiple queries with `IN ($ids_str)` and direct `$pid`. Use dynamic placeholders for IN clauses:

```php
// For IN clause:
$placeholders = implode(',', array_fill(0, count($report_ids), '?'));
$stmt = $pdo->prepare("SELECT ... WHERE report_id IN ($placeholders)");
$stmt->execute($report_ids);

// For single variable in loop:
$cntStmt = $pdo->prepare("SELECT SUM(count + count_night) FROM ps_daily_report_personnel WHERE report_id = ?");
// In loop:
$cntStmt->execute([$pid]);
```

- [ ] **Step 6: Verify — PHP syntax check all modified files**

```bash
php -l pardis/daily_report_form_ps.php
php -l pardis/daily_report_print_ps.php
php -l pardis/daily_report_print.php
php -l pardis/daily_reports_dashboard_ps.php
php -l pardis/weekly_report_ps.php
```

- [ ] **Step 7: Commit**

```bash
git add pardis/daily_report_form_ps.php pardis/daily_report_print_ps.php pardis/daily_report_print.php pardis/daily_reports_dashboard_ps.php pardis/weekly_report_ps.php
git commit -m "security(pardis): convert raw SQL queries to prepared statements in 5 high-priority files"
```

---

### Task 4: Fix SQL Injection — ghom/ files

**Files:**
- Modify: `ghom/api/upload_signed_permit.php` (line 70)
- Modify: `ghom/workshop_report.php` (line 47)
- Modify: `ghom/api/save_logo_settings.php` (lines 20, 22)

- [ ] **Step 1: Fix `ghom/api/upload_signed_permit.php`**

```php
// Line 70 — OLD:
$planFile = $pdo->query("SELECT plan_file FROM elements WHERE element_id = '{$p['el_id']}'")->fetchColumn();
// NEW:
$stmt = $pdo->prepare("SELECT plan_file FROM elements WHERE element_id = ?");
$stmt->execute([$p['el_id']]);
$planFile = $stmt->fetchColumn();
```

- [ ] **Step 2: Fix `ghom/workshop_report.php`**

```php
// Line 47 — OLD:
$uStmt = $commonConn->query("SELECT id, first_name, last_name, username FROM users WHERE id IN ($ids)");
// NEW:
$idArray = array_column($rows, 'user_id'); // or however $ids is built
$placeholders = implode(',', array_fill(0, count($idArray), '?'));
$uStmt = $commonConn->prepare("SELECT id, first_name, last_name, username FROM users WHERE id IN ($placeholders)");
$uStmt->execute($idArray);
```

- [ ] **Step 3: Fix `ghom/api/save_logo_settings.php`**

For column name interpolation — use a whitelist:

```php
// OLD:
$pdo->query("SELECT $col FROM print_settings LIMIT 1");
$pdo->exec("ALTER TABLE print_settings ADD COLUMN $col VARCHAR(255) DEFAULT ''");
// NEW — whitelist allowed column names:
$allowedColumns = ['logo_left', 'logo_right', 'logo_center']; // actual column names
if (!in_array($col, $allowedColumns, true)) {
    throw new Exception("Invalid column name");
}
// Column names cannot be parameterized, but whitelist makes it safe
$pdo->query("SELECT `$col` FROM print_settings LIMIT 1");
$pdo->exec("ALTER TABLE print_settings ADD COLUMN `$col` VARCHAR(255) DEFAULT ''");
```

- [ ] **Step 4: Verify PHP syntax**

```bash
php -l ghom/api/upload_signed_permit.php
php -l ghom/workshop_report.php
php -l ghom/api/save_logo_settings.php
```

- [ ] **Step 5: Commit**

```bash
git add ghom/api/upload_signed_permit.php ghom/workshop_report.php ghom/api/save_logo_settings.php
git commit -m "security(ghom): convert raw SQL queries to prepared statements in 3 files"
```

---

### Task 5: Fix remaining SQL injection across all other files

**Files:** All files from the 168-query audit that use `->query()` with variables but weren't covered in Tasks 3-4. These are primarily queries where the variable comes from the database (lower risk) but still need fixing.

Run this to find them all:
```bash
grep -rn '->query("' --include="*.php" | grep '\$' | grep -v 'includes/mpdf\|includes/libraries\|includes/TCPDF'
```

- [ ] **Step 1: Scan for remaining vulnerable queries**

Categorize by file and fix each one using the same `prepare()/execute()` pattern.

- [ ] **Step 2: Fix all remaining files systematically**

Go file by file. For each `->query("...{$var}...")` convert to `->prepare("...?...")->execute([$var])`.

- [ ] **Step 3: Run verification — must return 0**

```bash
grep -rn '->query("' --include="*.php" | grep '\$' | grep -v 'includes/mpdf\|includes/libraries\|includes/TCPDF' | wc -l
```

- [ ] **Step 4: PHP syntax check all modified files**

```bash
find . -name "*.php" -newer .git/refs/heads/claude/phase-1-security-hardening -exec php -l {} \; 2>&1 | grep -v "No syntax errors"
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "security(global): convert all remaining raw SQL queries to prepared statements"
```

---

### Task 6: Fix XSS vulnerabilities

**Files:**
- Modify: `pardis/daily_reports_dashboard_ps.php` (lines 378, 383, 387)
- Audit: All files echoing `$_GET`/`$_POST` without escaping

- [ ] **Step 1: Fix `pardis/daily_reports_dashboard_ps.php`**

```php
// Line 378 — OLD:
value="<?= $_GET['contractor']??'' ?>"
// NEW:
value="<?= e($_GET['contractor'] ?? '') ?>"

// Line 383 — OLD:
value="<?= $_GET['date_from']??'' ?>"
// NEW:
value="<?= e($_GET['date_from'] ?? '') ?>"

// Line 387 — OLD:
value="<?= $_GET['date_to']??'' ?>"
// NEW:
value="<?= e($_GET['date_to'] ?? '') ?>"
```

- [ ] **Step 2: Scan for any remaining XSS**

```bash
grep -rn 'echo.*\$_GET\|echo.*\$_POST\|<?=.*\$_GET\|<?=.*\$_POST' --include="*.php" | grep -v htmlspecialchars | grep -v json_encode | grep -v '\.bak'
```

Fix any additional findings.

- [ ] **Step 3: Verify — must return 0**

```bash
grep -rn 'echo.*\$_GET\|echo.*\$_POST\|<?=.*\$_GET\|<?=.*\$_POST' --include="*.php" | grep -v htmlspecialchars | grep -v json_encode | wc -l
```

- [ ] **Step 4: Commit**

```bash
git add pardis/daily_reports_dashboard_ps.php
git commit -m "security(pardis): fix XSS — escape all GET/POST output with htmlspecialchars"
```

---

### Task 7: Add CSRF protection to POST API endpoints

**Files:**
- Modify: All `ghom/api/*.php` and `pardis/api/*.php` that accept POST

- [ ] **Step 1: Identify all POST API endpoints**

```bash
grep -rln '\$_POST\|\$_FILES\|REQUEST_METHOD.*POST' ghom/api/*.php pardis/api/*.php api/*.php
```

- [ ] **Step 2: Add CSRF check to each POST endpoint**

At the top of each POST-handling API file, after the `require bootstrap` and `secureSession()` lines, add:

```php
require_once __DIR__ . '/../../includes/security.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
}
```

For AJAX endpoints that send JSON, they should pass the CSRF token via `X-CSRF-TOKEN` header. The `verifyCsrfToken()` function already checks both `$_POST['csrf_token']` and `$_SERVER['HTTP_X_CSRF_TOKEN']`.

- [ ] **Step 3: Add `csrfField()` to all HTML forms**

In form pages that POST to these APIs, add `<?= csrfField() ?>` inside each `<form>` tag.

For AJAX calls, add the token to JavaScript:
```html
<meta name="csrf-token" content="<?= e(generateCsrfToken()) ?>">
<script>
// Add to all AJAX calls:
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
// Include in fetch/XMLHttpRequest headers: 'X-CSRF-TOKEN': csrfToken
</script>
```

- [ ] **Step 4: Verify all POST endpoints have CSRF**

```bash
for f in ghom/api/*.php pardis/api/*.php api/*.php; do
    if grep -q '\$_POST\|\$_FILES\|REQUEST_METHOD.*POST' "$f" 2>/dev/null; then
        grep -qL 'csrf\|verifyCsrf\|requireCsrf' "$f" && echo "MISSING CSRF: $f"
    fi
done
```

- [ ] **Step 5: Commit**

```bash
git add ghom/api/ pardis/api/ api/ includes/security.php
git commit -m "security(global): add CSRF protection to all POST API endpoints"
```

---

### Task 8: Standardize auth checks on API endpoints

**Files:**
- Modify: All API files in `ghom/api/`, `pardis/api/`, `api/`

- [ ] **Step 1: Add `requireLogin()` to every API file**

At the top of every API file, ensure:
```php
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();
requireLogin();
```

Exceptions: `jdf.php` (utility library — no auth needed).

- [ ] **Step 2: Add role-based access where appropriate**

For admin-only endpoints (e.g., settings, user management):
```php
requireRole(['admin', 'superuser']);
```

- [ ] **Step 3: Verify**

```bash
for f in ghom/api/*.php pardis/api/*.php api/*.php; do
    basename "$f" | grep -q 'jdf.php' && continue
    grep -qL 'requireLogin\|requireRole\|isLoggedIn' "$f" && echo "MISSING AUTH: $f"
done
```

- [ ] **Step 4: Commit**

```bash
git add ghom/api/ pardis/api/ api/
git commit -m "security(global): standardize auth checks on all API endpoints"
```

---

### Task 9: File upload validation

**Files:**
- Modify: `ghom/api/upload_signed_permit.php`
- Modify: `ghom/api/save_logo_settings.php`
- Modify: `ghom/qc_import_azarestan.php`
- Modify: Any other file using `$_FILES` without proper validation

- [ ] **Step 1: Add `validateUpload()` calls**

In each file that handles `$_FILES`, add:

```php
require_once __DIR__ . '/../../includes/security.php';

$errors = validateUpload($_FILES['field_name'], ['pdf', 'jpg', 'jpeg', 'png']);
if (!empty($errors)) {
    echo json_encode(['status' => 'error', 'message' => implode(', ', $errors)]);
    exit();
}
```

For CSV imports (`qc_import_azarestan.php`):
```php
$errors = validateUpload($_FILES['csv_file'], ['csv'], 10485760);
```

- [ ] **Step 2: Verify all upload endpoints have validation**

```bash
grep -rln '\$_FILES' --include="*.php" | while read f; do
    grep -qL 'validateUpload\|pathinfo.*PATHINFO_EXTENSION' "$f" && echo "WEAK UPLOAD: $f"
done
```

- [ ] **Step 3: Commit**

```bash
git add ghom/api/upload_signed_permit.php ghom/api/save_logo_settings.php ghom/qc_import_azarestan.php
git commit -m "security(global): add file upload validation with extension + MIME checks"
```

---

### Task 10: Remove remaining `display_errors` and add error handler integration

**Files:**
- Modify: Any file still setting `display_errors`
- Modify: `sercon/bootstrap.php` to include error handler

- [ ] **Step 1: Update bootstrap to include error handler**

Add at the end of `sercon/bootstrap.php`:

```php
// ── 8. Include error handler ──
require_once __DIR__ . '/../includes/error_handler.php';
```

- [ ] **Step 2: Remove per-file `display_errors` overrides**

These files still set `display_errors` — remove those lines since bootstrap handles it:
- `ghom/api/save_inspection.php` line 5
- `ghom/api/submit_opening_request (1).php` line 4
- `pardis/daily_report_api.php` line 4
- `pardis/weekly_report_ps.php` line 58

```bash
# Verify which files still set it:
grep -rn 'display_errors' --include="*.php"
```

Remove `ini_set('display_errors', ...)` lines from all files — bootstrap.php handles this globally.

- [ ] **Step 3: Verify — no display_errors outside bootstrap**

```bash
grep -rn "display_errors" --include="*.php" | grep -v 'sercon/bootstrap.php' | wc -l
```

Should be 0.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "security(global): centralize error handling, remove per-file display_errors"
```

---

### Task 11: Update documentation

**Files:**
- Modify: `docs/ARCHITECTURE.md`
- Modify: `docs/TECH_DEBT.md`
- Modify: `docs/CHANGELOG.md`
- Modify: `docs/SETUP.md`

- [ ] **Step 1: Update `docs/ARCHITECTURE.md`**

Update Section 8 (Security Measures) to mark Phase 1 items as complete:
```markdown
- ✅ CSRF token on login
- ✅ Password hashing (bcrypt)
- ✅ Brute-force protection (login_attempts)
- ✅ Session regeneration
- ✅ Prepared statements (all queries)
- ✅ XSS prevention (htmlspecialchars)
- ✅ CSRF on all POST endpoints
- ✅ Security headers (CSP, HSTS, X-Frame-Options, X-Content-Type-Options)
- ✅ HTTPS enforcement
- ✅ Input validation layer
- ✅ Centralized error handler
- ✅ File upload validation
```

Add new section documenting the security middleware:
```markdown
## Security Middleware

| File | Purpose |
|------|---------|
| `sercon/bootstrap.php` | Central bootstrap — DB, session, auth, logging |
| `includes/security.php` | CSRF tokens, upload validation |
| `includes/validation.php` | Input validation (int, string, date, email) |
| `includes/error_handler.php` | Global error/exception handlers |
| `.htaccess` | Security headers, HTTPS, compression |
```

- [ ] **Step 2: Update `docs/TECH_DEBT.md`**

Mark all Phase 1 security items as resolved:
- TD-SEC-001 (SQL Injection) → 🟢 Resolved
- TD-SEC-002 (XSS) → 🟢 Resolved
- TD-SEC-003 (Missing CSRF) → 🟢 Resolved
- TD-SEC-004 (Missing Auth) → 🟢 Resolved
- TD-SEC-005 (File Upload) → 🟢 Resolved
- TD-SEC-006 (No Security Headers) → 🟢 Resolved
- TD-SEC-007 (No HTTPS) → 🟢 Resolved
- TD-SEC-008 (Input Validation) → 🟢 Resolved

Add resolution dates and commit references.

- [ ] **Step 3: Update `docs/CHANGELOG.md`**

Fill in the Phase 1 section with actual completion details:
```markdown
### Phase 1 — Security Hardening (2026-04-17)
#### Security
- [x] Converted all raw SQL queries to prepared statements (30+ queries across 8+ files)
- [x] Fixed XSS vulnerabilities with htmlspecialchars() / e() helper
- [x] Added CSRF middleware to all POST API endpoints
- [x] Added requireLogin()/requireRole() auth checks to all API endpoints
- [x] Added file upload validation (extension + MIME type)
- [x] Added security headers via .htaccess (CSP, HSTS, X-Frame-Options)
- [x] Enforced HTTPS redirect via .htaccess

#### Added
- [x] `sercon/bootstrap.php` — centralized bootstrap (DB, session, auth, logging)
- [x] `includes/security.php` — CSRF protection, upload validation
- [x] `includes/validation.php` — input validation helpers
- [x] `includes/error_handler.php` — global error/exception handlers
- [x] `.htaccess` — security headers, HTTPS, compression, caching
```

- [ ] **Step 4: Update `docs/SETUP.md`**

Add a section about the new security infrastructure files and their purpose.

- [ ] **Step 5: Commit docs**

```bash
git add docs/
git commit -m "docs: update ARCHITECTURE, TECH_DEBT, CHANGELOG, SETUP for Phase 1 completion"
```

---

### Task 12: Final verification

- [ ] **Step 1: Run all Phase 1 verification checks**

```bash
echo "=== SQL Injection (must be 0) ==="
grep -rn '->query("' --include="*.php" | grep '\$' | grep -v 'includes/mpdf\|includes/libraries\|includes/TCPDF' | wc -l

echo "=== XSS (must be 0) ==="
grep -rn 'echo.*\$_GET\|echo.*\$_POST\|<?=.*\$_GET\|<?=.*\$_POST' --include="*.php" | grep -v htmlspecialchars | grep -v json_encode | wc -l

echo "=== display_errors outside bootstrap (must be 0) ==="
grep -rn 'display_errors' --include="*.php" | grep -v 'sercon/bootstrap.php' | wc -l

echo "=== Hardcoded secrets (must be 0) ==="
grep -rn 'TELEGRAM_BOT_TOKEN.*=' --include="*.php" | grep -v "getenv\|_ENV\|\$_ENV" | wc -l

echo "=== PHP Syntax ==="
find . -name "*.php" -not -path "*/includes/mpdf/*" -not -path "*/includes/libraries/*" -not -path "*/includes/TCPDF/*" -not -path "*/vendor/*" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"
```

- [ ] **Step 2: Review git log**

```bash
git log --oneline main..HEAD
```

- [ ] **Step 3: Create report**

```bash
cat > /tmp/report.txt << 'RPT'
## Task: Phase 1 — Security Hardening
## Branch: claude/phase-1-security-hardening
## Changes:
- sercon/bootstrap.php: Central bootstrap with DB, session, auth, logging
- includes/security.php: CSRF protection, file upload validation
- includes/validation.php: Input validation helpers
- includes/error_handler.php: Global error/exception handlers
- .htaccess: Security headers, HTTPS enforcement, compression
- pardis/*.php: Converted all raw SQL to prepared statements
- ghom/*.php: Converted all raw SQL to prepared statements
- pardis/daily_reports_dashboard_ps.php: Fixed XSS
- All API endpoints: Added CSRF + auth checks
- docs/: Updated ARCHITECTURE, TECH_DEBT, CHANGELOG, SETUP
## Tests: PHP syntax checks passed on all files
## Status: ready for review
## Next Steps: Review PR, then Phase 2 (Performance & UX)
RPT
/opt/shahrzad-devops/scripts/make-report.sh phase1-security /tmp/report.txt
```
