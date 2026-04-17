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
        $pdo = _createPDO(
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
        $connections[$project] = _createPDO(
            getenv('DB_HOST') ?: 'localhost',
            getenv('DB_PORT') ?: '3306',
            $dbName,
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
    }
    return $connections[$project];
}

function _createPDO(string $host, string $port, string $dbName, string $user, string $pass): PDO {
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

// ── 8. Include error handler + security helpers ──
require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/security.php';

// ── 9. Repository factory ──
/**
 * Lazy repository factory.
 *
 * Usage:
 *   $users = getRepository('UserRepository')->findByIds([1, 2, 3]);
 *   $elem  = getRepository('ElementRepository')->findById('A1');
 *
 * Repositories that query a project database resolve to the current
 * project via getCurrentProject() (see shared/includes/project_context.php).
 */
function getRepository(string $class): object
{
    static $instances = [];
    if (isset($instances[$class])) {
        return $instances[$class];
    }

    require_once __DIR__ . '/../shared/repositories/' . $class . '.php';
    require_once __DIR__ . '/../shared/includes/project_context.php';

    switch ($class) {
        case 'UserRepository':
            $instances[$class] = new UserRepository(getCommonDBConnection());
            break;
        case 'ElementRepository':
        case 'InspectionRepository':
        case 'DailyReportRepository':
            $instances[$class] = new $class(getProjectDB());
            break;
        default:
            throw new InvalidArgumentException("Unknown repository: $class");
    }

    return $instances[$class];
}
