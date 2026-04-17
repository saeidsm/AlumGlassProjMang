# Phase 3 — Architecture Refactoring & Migration
# فاز ۳ — بازسازی معماری و مهاجرت

> **حالت:** خودکار — بدون توقف بین زیرفازها
> **شروع از:** `main` (v0.3.0-performance-ux)
> **مدت تخمینی:** ۵-۷ روز اجرا
> **خروجی نهایی:** v1.0.0 + migration script + final report

---

## ⚠️ CRITICAL INSTRUCTIONS

1. Execute sub-phases 3A → 3B → 3C → 3D → 3E → 3F sequentially, no approval needed.
2. Each sub-phase: branch → work → commit → merge to main → next.
3. Run `php -l` on every changed PHP file. If PHP unavailable, verify with regex.
4. NEVER break existing functionality — each API must keep its current URL working.
5. Migration script (3F) is the FINAL deliverable — test it thoroughly.
6. Update all docs at the end.

---

## 📊 Analysis Summary (Pre-Phase 3)

### Code duplication between ghom/ and pardis/:
- **49 duplicate API files** total
- **42 near-identical** (only ~8 lines differ — just DB connection name)
- **1 fully identical** (jdf.php)
- **3 heavily diverged** (save_inspection, save_inspection_old, submit_opening_request)
- **3 moderately diverged** (get_element_data, get_existing_parts, store_public_key)

### The 8-line difference pattern:
```php
// In ghom/api/get_stages.php:
$pdo = getProjectDBConnection('ghom');

// In pardis/api/get_stages.php:
$pdo = getProjectDBConnection('pardis');
```
This means 42 files can be unified by reading the project name from the session context.

### Current file count:
- ghom/: 164 PHP files
- pardis/: 164 PHP files  
- root: 22 PHP files
- **Target: eliminate ~42 duplicate API files + ~10 duplicate pages**

---

## 3A — Shared API Layer
> **Branch:** `claude/phase-3a-shared-api`
> **Goal:** Eliminate 42 duplicate API files by creating a project-aware shared API.

### Architecture:

```
BEFORE (current):
  ghom/api/get_stages.php      → $pdo = getProjectDBConnection('ghom');
  pardis/api/get_stages.php    → $pdo = getProjectDBConnection('pardis');
  
AFTER (target):
  shared/api/get_stages.php    → $pdo = getProjectDBConnection(getCurrentProject());
  ghom/api/get_stages.php      → require_once '../../shared/api/get_stages.php'; (backward compat)
  pardis/api/get_stages.php    → require_once '../../shared/api/get_stages.php'; (backward compat)
```

### Step 1: Create project context resolver

**Create `includes/project_context.php`:**
```php
<?php
/**
 * Resolves the current project context from session or URL.
 * Every shared API uses this instead of hardcoded project names.
 */

function getCurrentProject(): string {
    // 1. From session (set during project selection)
    if (!empty($_SESSION['current_project'])) {
        return $_SESSION['current_project'];
    }
    
    // 2. From URL path: /ghom/api/... → 'ghom', /pardis/api/... → 'pardis'
    $path = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/(ghom|pardis)/#', $path, $m)) {
        return $m[1];
    }
    
    // 3. From POST/GET parameter (for explicit switching)
    $project = $_GET['project'] ?? $_POST['project'] ?? '';
    if (in_array($project, getAvailableProjects())) {
        return $project;
    }
    
    // 4. Fallback
    throw new RuntimeException('Could not determine current project context');
}

function getAvailableProjects(): array {
    // This can later be loaded from DB or config
    return ['ghom', 'pardis'];
}

function getProjectDB(): PDO {
    return getProjectDBConnection(getCurrentProject());
}
```

### Step 2: Create shared API directory

```
shared/
├── api/                        # Shared API endpoints (project-agnostic)
│   ├── get_stages.php
│   ├── get_templates.php
│   ├── get_calendar_events.php
│   ├── get_element_data.php
│   ├── get_element_details.php
│   ├── get_notifications.php
│   ├── save_stage.php
│   ├── save_template.php
│   ├── save_workflow_order.php
│   ├── batch_update.php
│   ├── batch_update_status.php
│   ├── ... (42 files total)
│   └── jdf.php
├── includes/                   # Shared PHP includes
│   └── project_context.php
└── js/                         # Shared JavaScript
    └── (moved from duplicate ghom/pardis assets)
```

### Step 3: Convert each near-identical API

For each of the 42 near-identical files:

1. Copy `ghom/api/{file}.php` to `shared/api/{file}.php`
2. Replace `getProjectDBConnection('ghom')` with `getProjectDB()` 
3. Add `require_once __DIR__ . '/../includes/project_context.php';` at top
4. Replace `ghom/api/{file}.php` content with:
   ```php
   <?php
   // Backward compatibility — delegates to shared API
   require_once __DIR__ . '/../../shared/api/' . basename(__FILE__);
   ```
5. Replace `pardis/api/{file}.php` with same redirect
6. Verify: both old URLs still work

### Step 4: Handle the 3 diverged files

For `save_inspection.php`, `save_inspection_old.php`, `submit_opening_request.php`:
- These stay as separate files in ghom/api/ and pardis/api/
- Do NOT try to merge them — the logic is genuinely different
- Document in ARCHITECTURE.md why they're separate

### Step 5: Shared utilities

Move these truly identical files to shared/:
- `jdf.php` (Jalali date functions) → `shared/includes/jdf.php`
- `weather_config.php` (duplicated) → unified via .env
- Common notification helpers

### Commits:
```
refactor(global): create project context resolver (includes/project_context.php)
refactor(global): create shared/api/ with 42 unified API endpoints
refactor(ghom): redirect 42 ghom/api files to shared/api (backward compat)
refactor(pardis): redirect 42 pardis/api files to shared/api (backward compat)
refactor(global): move jdf.php and shared utilities to shared/includes
docs: update ARCHITECTURE.md with shared API layer
```

### Verification:
```bash
# Shared API directory should have ~42 files
ls shared/api/*.php | wc -l

# All old URLs must still work (backward compat redirects)
for f in ghom/api/get_stages.php pardis/api/get_stages.php ghom/api/save_template.php pardis/api/save_template.php; do
  if grep -q "require_once.*shared/api" "$f" 2>/dev/null; then
    echo "✅ $f → redirects to shared"
  else
    echo "⚠️  $f — not redirected"
  fi
done

# No hardcoded project names in shared API
grep -rn "getProjectDBConnection('ghom')\|getProjectDBConnection('pardis')" shared/api/ | wc -l
# Must be 0
```

---

## 3B — Data Access Layer (Repository Pattern)
> **Branch:** `claude/phase-3b-data-access-layer`
> **Goal:** Separate SQL queries from presentation logic for the most critical operations.

### Why:
Currently SQL queries are embedded directly in page files mixed with HTML. This makes:
- Testing impossible (can't test a query without rendering HTML)
- Reuse hard (same query copied to multiple files)
- Maintenance painful (change a table = hunt through 50 files)

### Scope (pragmatic — NOT full rewrite):
Only extract the **most reused queries** into repository classes. We're NOT converting everything to MVC — that would break the entire app.

### Create `shared/repositories/`:

**`shared/repositories/ElementRepository.php`:**
```php
<?php
class ElementRepository {
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function findById(string $elementId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM elements WHERE element_id = ?");
        $stmt->execute([$elementId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    public function findByZone(string $zone): array {
        $stmt = $this->pdo->prepare("SELECT * FROM elements WHERE zone = ? ORDER BY element_id");
        $stmt->execute([$zone]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getStatusCounts(): array {
        return $this->pdo->query("SELECT status, COUNT(*) as count FROM elements GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateStatus(string $elementId, string $status, int $userId): bool {
        $stmt = $this->pdo->prepare("UPDATE elements SET status = ?, updated_by = ?, updated_at = NOW() WHERE element_id = ?");
        return $stmt->execute([$status, $userId, $elementId]);
    }
    
    // ... other element queries used across multiple files
}
```

**`shared/repositories/InspectionRepository.php`:**
```php
<?php
class InspectionRepository {
    private PDO $pdo;
    
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    
    public function findByElement(string $elementId, ?string $partName = null): array { ... }
    public function save(array $data): int { ... }
    public function getRecentByUser(int $userId, int $limit = 10): array { ... }
    public function getStatsByStage(int $stageId): array { ... }
}
```

**`shared/repositories/DailyReportRepository.php`:**
```php
<?php
class DailyReportRepository {
    private PDO $pdo;
    
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    
    public function findById(int $id): ?array { ... }
    public function findByDateRange(string $from, string $to, array $filters = []): array { ... }
    public function getWithPagination(array $filters, int $page, int $perPage): array { ... }
    public function getPersonnel(int $reportId): array { ... }
    public function getMachinery(int $reportId): array { ... }
    public function getMaterials(int $reportId): array { ... }
    public function getActivities(int $reportId): array { ... }
    // Replaces the N+1 queries with batch methods:
    public function getActivityCountsByReportIds(array $ids): array { ... }
}
```

**`shared/repositories/UserRepository.php`:**
```php
<?php
class UserRepository {
    private PDO $pdo; // uses common DB
    
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    
    public function findById(int $id): ?array { ... }
    public function findByUsername(string $username): ?array { ... }
    public function findByIds(array $ids): array { ... }
    public function getActive(): array { ... }
}
```

### How to integrate WITHOUT breaking existing code:

Add repository factory to bootstrap.php:
```php
function getRepository(string $class): object {
    static $instances = [];
    if (!isset($instances[$class])) {
        switch ($class) {
            case 'UserRepository':
                require_once __DIR__ . '/../shared/repositories/UserRepository.php';
                $instances[$class] = new UserRepository(getCommonDBConnection());
                break;
            case 'ElementRepository':
            case 'InspectionRepository':
            case 'DailyReportRepository':
                require_once __DIR__ . "/../shared/repositories/$class.php";
                $instances[$class] = new $class(getProjectDB());
                break;
        }
    }
    return $instances[$class];
}
```

Then gradually replace inline queries:
```php
// BEFORE (in ghom/reports.php):
$stmt = $pdo->prepare("SELECT * FROM elements WHERE zone = ?");
$stmt->execute([$zone]);
$elements = $stmt->fetchAll();

// AFTER:
$elements = getRepository('ElementRepository')->findByZone($zone);
```

**⚠️ IMPORTANT:** Only refactor queries that are used in 3+ files. Leave single-use queries inline — moving them to a repository adds complexity without benefit.

### Commits:
```
refactor(global): create ElementRepository with shared element queries
refactor(global): create InspectionRepository with shared inspection queries
refactor(global): create DailyReportRepository with shared report queries
refactor(global): create UserRepository with shared user queries
refactor(global): add repository factory to bootstrap.php
refactor(ghom): replace 15 most-duplicated inline queries with repository calls
refactor(pardis): replace 15 most-duplicated inline queries with repository calls
```

### Verification:
```bash
# Repository files exist
ls shared/repositories/*.php | wc -l
# Should be 4+

# Repositories are loadable (syntax check)
for f in shared/repositories/*.php; do php -l "$f" 2>&1; done
```

---

## 3C — Coding Standards & Static Analysis
> **Branch:** `claude/phase-3c-coding-standards`
> **Goal:** Add automated code quality tooling.

### Step 1: Create `composer.json` (project root)
```json
{
    "name": "alumglass/project-management",
    "description": "AlumGlass Engineering Facade Project Management Dashboard",
    "type": "project",
    "require": {
        "php": ">=8.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5",
        "phpstan/phpstan": "^1.10",
        "squizlabs/php_codesniffer": "^3.9"
    },
    "autoload": {
        "classmap": [
            "shared/repositories/"
        ]
    },
    "scripts": {
        "test": "phpunit --configuration phpunit.xml",
        "analyse": "phpstan analyse -c phpstan.neon --memory-limit=512M",
        "lint": "phpcs --standard=PSR12 --extensions=php shared/ includes/",
        "lint-fix": "phpcbf --standard=PSR12 --extensions=php shared/ includes/"
    }
}
```

### Step 2: Create `phpstan.neon`
```neon
parameters:
    level: 3
    paths:
        - shared/
        - includes/
    excludePaths:
        - pardis/includes/mpdf/
        - pardis/includes/libraries/
    reportUnmatchedIgnoredErrors: false
```

### Step 3: Create `.editorconfig`
```ini
root = true

[*]
charset = utf-8
end_of_line = lf
indent_style = space
indent_size = 4
insert_final_newline = true
trim_trailing_whitespace = true

[*.md]
trim_trailing_whitespace = false

[*.{js,css}]
indent_size = 2
```

### Commits:
```
chore(global): add composer.json with dev dependencies (phpunit, phpstan, phpcs)
chore(global): add phpstan.neon and .editorconfig
chore(global): apply PSR-12 formatting to shared/ and includes/
```

---

## 3D — PHPUnit Tests (Critical Paths)
> **Branch:** `claude/phase-3d-testing`
> **Goal:** Test the most critical operations — auth, CSRF, validation, repositories.

### Create `phpunit.xml`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true" stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### Create `tests/bootstrap.php`:
```php
<?php
// Minimal bootstrap for testing — no DB, no session
define('TESTING', true);
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/validation.php';
```

### Test files:

**`tests/Unit/SecurityTest.php`:**
```php
<?php
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase {
    public function testEscapeHtml(): void { ... }
    public function testCsrfTokenGeneration(): void { ... }
    public function testCsrfTokenValidation(): void { ... }
    public function testCsrfTokenMismatchFails(): void { ... }
}
```

**`tests/Unit/ValidationTest.php`:**
```php
<?php
use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase {
    public function testValidateIntRejectsString(): void { ... }
    public function testValidateIntAcceptsNumber(): void { ... }
    public function testValidateEmailRejectsInvalid(): void { ... }
    public function testValidateEmailAcceptsValid(): void { ... }
    public function testValidateStringTruncatesOverLength(): void { ... }
    public function testValidateUploadRejectsDisallowedExtension(): void { ... }
    public function testValidateUploadAcceptsPdf(): void { ... }
}
```

**`tests/Unit/ProjectContextTest.php`:**
```php
<?php
use PHPUnit\Framework\TestCase;

class ProjectContextTest extends TestCase {
    public function testGetCurrentProjectFromSession(): void { ... }
    public function testGetCurrentProjectFromUrl(): void { ... }
    public function testGetCurrentProjectThrowsOnUnknown(): void { ... }
    public function testGetAvailableProjects(): void { ... }
}
```

**`tests/Unit/PaginationTest.php`:**
```php
<?php
use PHPUnit\Framework\TestCase;

class PaginationTest extends TestCase {
    public function testRenderPaginationHidesForSinglePage(): void { ... }
    public function testRenderPaginationShowsCorrectPages(): void { ... }
    public function testPageNumberClampsToMinimum(): void { ... }
}
```

### Commits:
```
test(global): add PHPUnit config and test bootstrap
test(global): add SecurityTest (CSRF, XSS escape)
test(global): add ValidationTest (input validation helpers)
test(global): add ProjectContextTest (project resolver)
test(global): add PaginationTest (pagination helper)
```

### Verification:
```bash
# Run tests
./vendor/bin/phpunit --colors=always
# All must pass
```

---

## 3E — CI/CD Pipeline (GitHub Actions)
> **Branch:** `claude/phase-3e-cicd`
> **Goal:** Automated checks on every push.

### Create `.github/workflows/ci.yml`:
```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          tools: composer, phpcs, phpstan
      - run: composer install --prefer-dist --no-progress
      - name: PHP Syntax Check
        run: find . -name "*.php" -not -path "*/vendor/*" -not -path "*/mpdf/*" -not -path "*/TCPDF*" -not -path "*/libraries/*" | xargs -P4 -I{} php -l {} | grep -v "No syntax errors" || true
      - name: PHPStan
        run: composer analyse || true
        
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pdo, pdo_mysql, mbstring, gd
      - run: composer install --prefer-dist --no-progress
      - name: PHPUnit
        run: composer test

  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Check SQL Injection
        run: |
          COUNT=$(grep -rn '->query("' --include="*.php" | grep '\$' | grep -v 'vendor\|mpdf\|libraries\|TCPDF' | wc -l)
          echo "Unsafe queries: $COUNT"
          if [ "$COUNT" -gt 0 ]; then echo "::error::SQL injection risk found!"; exit 1; fi
      - name: Check XSS
        run: |
          COUNT=$(grep -rn 'echo.*\$_GET\|<?=.*\$_GET\|echo.*\$_POST\|<?=.*\$_POST' --include="*.php" | grep -v htmlspecialchars | grep -v json_encode | grep -v 'vendor\|mpdf\|libraries' | wc -l)
          echo "XSS candidates: $COUNT"
      - name: Check Hardcoded Secrets
        run: |
          COUNT=$(grep -rn 'TELEGRAM_BOT_TOKEN.*=' --include="*.php" | grep -v 'getenv\|_ENV\|\.env\|example' | wc -l)
          if [ "$COUNT" -gt 0 ]; then echo "::error::Hardcoded secrets found!"; exit 1; fi
      - name: Check No SQL Dumps
        run: |
          if find . -name "*.sql" -o -name "*.sql.txt" | grep -q .; then echo "::error::SQL dump in repo!"; exit 1; fi
```

### Commits:
```
ci(global): add GitHub Actions CI pipeline (lint, test, security checks)
```

---

## 3F — Migration Script (Old → New)
> **Branch:** `claude/phase-3f-migration`
> **Goal:** Script that migrates from the original ZIP deployment to the new Git-based deployment.

### What needs to migrate:

| Source (old server) | Destination (new server) | Method |
|---------------------|--------------------------|--------|
| MySQL databases (3) | New MySQL server | mysqldump → import |
| Uploaded files (permits, photos, logos, documents) | New uploads directory | rsync / scp |
| `.env` values (DB creds, tokens) | New `.env` file | Interactive prompt |
| Cron jobs | New crontab | Auto-configure |
| Apache/SSL config | New virtualhost | Template generation |

### Create `scripts/migrate.sh`:

```bash
#!/bin/bash
# ==============================================================
# AlumGlass Migration Script
# Migrates from old deployment (ZIP-based) to new (Git-based)
# ==============================================================
# 
# Usage:
#   ./scripts/migrate.sh --source-host old-server.com --source-user root
#
# Prerequisites:
#   - SSH access to old server
#   - MySQL credentials for both servers
#   - New server has: PHP 8.1+, MySQL/MariaDB, Apache, Git
#
# What this script does:
#   1. Connects to old server via SSH
#   2. Dumps all 3 databases
#   3. Copies uploaded files (permits, photos, logos)
#   4. Imports databases to new server
#   5. Generates .env from interactive prompts
#   6. Sets file permissions
#   7. Configures cron jobs
#   8. Verifies migration
#
# ==============================================================

set -euo pipefail

# Colors
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
log_ok() { echo -e "${GREEN}✅ $1${NC}"; }
log_warn() { echo -e "${YELLOW}⚠️  $1${NC}"; }
log_err() { echo -e "${RED}❌ $1${NC}"; }
log_step() { echo -e "\n${YELLOW}━━━ $1 ━━━${NC}"; }

# Parse arguments
SOURCE_HOST=""
SOURCE_USER="root"
SOURCE_PATH="/home/*/public_html"
LOCAL_PROJECT_DIR=""
BACKUP_DIR="./migration_backup_$(date +%Y%m%d_%H%M%S)"

while [[ $# -gt 0 ]]; do
    case $1 in
        --source-host) SOURCE_HOST="$2"; shift 2;;
        --source-user) SOURCE_USER="$2"; shift 2;;
        --source-path) SOURCE_PATH="$2"; shift 2;;
        --project-dir) LOCAL_PROJECT_DIR="$2"; shift 2;;
        --help) echo "Usage: $0 --source-host <host> [--source-user <user>] [--source-path <path>] [--project-dir <dir>]"; exit 0;;
        *) echo "Unknown option: $1"; exit 1;;
    esac
done

if [ -z "$SOURCE_HOST" ]; then
    echo "Error: --source-host is required"
    echo "Usage: $0 --source-host old-server.com"
    exit 1
fi

if [ -z "$LOCAL_PROJECT_DIR" ]; then
    LOCAL_PROJECT_DIR=$(pwd)
fi

mkdir -p "$BACKUP_DIR"/{databases,uploads,config}

echo "╔══════════════════════════════════════════╗"
echo "║   AlumGlass Migration Tool v1.0         ║"
echo "║   Source: $SOURCE_HOST                   "
echo "║   Target: $(hostname)                    "
echo "╚══════════════════════════════════════════╝"

# ==============================================================
# STEP 1: Collect credentials
# ==============================================================
log_step "Step 1: Collecting credentials"

echo "--- Source server (old) ---"
read -p "Source MySQL username: " SRC_DB_USER
read -sp "Source MySQL password: " SRC_DB_PASS; echo
read -p "Source document root path [/home/alumglas/public_html]: " SRC_DOC_ROOT
SRC_DOC_ROOT=${SRC_DOC_ROOT:-/home/alumglas/public_html}

echo ""
echo "--- Destination server (new) ---"
read -p "New MySQL host [localhost]: " DST_DB_HOST
DST_DB_HOST=${DST_DB_HOST:-localhost}
read -p "New MySQL username: " DST_DB_USER
read -sp "New MySQL password: " DST_DB_PASS; echo
read -p "New document root path [$LOCAL_PROJECT_DIR]: " DST_DOC_ROOT
DST_DOC_ROOT=${DST_DOC_ROOT:-$LOCAL_PROJECT_DIR}

echo ""
echo "--- Application secrets ---"
read -p "Telegram Bot Token (from old .env or config): " TELEGRAM_TOKEN
read -p "Weather API Key (leave empty to skip): " WEATHER_KEY

log_ok "Credentials collected"

# ==============================================================
# STEP 2: Dump databases from source
# ==============================================================
log_step "Step 2: Dumping databases from source server"

DATABASES=("alumglas_common" "alumglas_hpc" "alumglas_pardis")

for DB in "${DATABASES[@]}"; do
    echo "  Dumping $DB..."
    ssh "${SOURCE_USER}@${SOURCE_HOST}" \
        "mysqldump -u $SRC_DB_USER -p'$SRC_DB_PASS' --single-transaction --routines --triggers $DB" \
        > "$BACKUP_DIR/databases/${DB}.sql" 2>/dev/null
    
    SIZE=$(du -h "$BACKUP_DIR/databases/${DB}.sql" | cut -f1)
    log_ok "$DB → $SIZE"
done

# ==============================================================
# STEP 3: Copy uploaded files from source
# ==============================================================
log_step "Step 3: Copying uploaded files from source server"

# Permit files, photos, logos, documents
UPLOAD_DIRS=(
    "ghom/uploads"
    "pardis/uploads"
    "assets/images"
    "ghom/assets"
    "pardis/assets"
)

for DIR in "${UPLOAD_DIRS[@]}"; do
    echo "  Syncing $DIR..."
    rsync -avz --progress \
        "${SOURCE_USER}@${SOURCE_HOST}:${SRC_DOC_ROOT}/${DIR}/" \
        "$BACKUP_DIR/uploads/${DIR}/" \
        2>/dev/null || log_warn "Directory $DIR not found on source (may not exist)"
done

# Copy any user-uploaded files (permits, signatures, etc.)
echo "  Syncing permit files..."
rsync -avz \
    "${SOURCE_USER}@${SOURCE_HOST}:${SRC_DOC_ROOT}/ghom/uploads/" \
    "$DST_DOC_ROOT/ghom/uploads/" \
    2>/dev/null || log_warn "No ghom/uploads on source"

rsync -avz \
    "${SOURCE_USER}@${SOURCE_HOST}:${SRC_DOC_ROOT}/pardis/uploads/" \
    "$DST_DOC_ROOT/pardis/uploads/" \
    2>/dev/null || log_warn "No pardis/uploads on source"

log_ok "Files synced"

# ==============================================================
# STEP 4: Create databases and import
# ==============================================================
log_step "Step 4: Creating databases and importing data"

for DB in "${DATABASES[@]}"; do
    echo "  Creating database $DB..."
    mysql -h "$DST_DB_HOST" -u "$DST_DB_USER" -p"$DST_DB_PASS" \
        -e "CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
        2>/dev/null
    
    echo "  Importing $DB..."
    mysql -h "$DST_DB_HOST" -u "$DST_DB_USER" -p"$DST_DB_PASS" "$DB" \
        < "$BACKUP_DIR/databases/${DB}.sql" \
        2>/dev/null
    
    TABLES=$(mysql -h "$DST_DB_HOST" -u "$DST_DB_USER" -p"$DST_DB_PASS" "$DB" \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB';" -sN 2>/dev/null)
    log_ok "$DB imported — $TABLES tables"
done

# ==============================================================
# STEP 5: Generate .env file
# ==============================================================
log_step "Step 5: Generating .env file"

CRON_SECRET=$(openssl rand -hex 16)

cat > "$DST_DOC_ROOT/.env" << EOF
# Generated by migration script on $(date)
# AlumGlass Project Management

# Database
DB_HOST=$DST_DB_HOST
DB_PORT=3306
DB_COMMON_NAME=alumglas_common
DB_GHOM_NAME=alumglas_hpc
DB_PARDIS_NAME=alumglas_pardis
DB_USERNAME=$DST_DB_USER
DB_PASSWORD=$DST_DB_PASS

# Telegram
TELEGRAM_BOT_TOKEN=$TELEGRAM_TOKEN
TELEGRAM_CRON_SECRET=$CRON_SECRET

# Weather
WEATHER_API_KEY=$WEATHER_KEY

# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://$(hostname -f)
APP_TIMEZONE=Asia/Tehran

# Security
SESSION_LIFETIME=3600
LOGIN_LOCKOUT_TIME=3600
LOGIN_ATTEMPTS_LIMIT=5

# Upload
UPLOAD_MAX_SIZE=5242880
ALLOWED_UPLOAD_EXTENSIONS=pdf,jpg,jpeg,png,csv,xlsx
EOF

chmod 600 "$DST_DOC_ROOT/.env"
log_ok ".env generated with secure permissions (600)"

# ==============================================================
# STEP 6: Set file permissions
# ==============================================================
log_step "Step 6: Setting file permissions"

# Find web server user
WEB_USER=$(ps aux | grep -E 'apache|httpd|nginx' | grep -v grep | head -1 | awk '{print $1}')
WEB_USER=${WEB_USER:-www-data}

echo "  Web server user: $WEB_USER"

find "$DST_DOC_ROOT" -type f -exec chmod 644 {} \;
find "$DST_DOC_ROOT" -type d -exec chmod 755 {} \;
chmod 600 "$DST_DOC_ROOT/.env"

# Writable directories
for DIR in logs ghom/uploads pardis/uploads; do
    mkdir -p "$DST_DOC_ROOT/$DIR"
    chmod 775 "$DST_DOC_ROOT/$DIR"
done

chown -R "$WEB_USER:$WEB_USER" "$DST_DOC_ROOT" 2>/dev/null || log_warn "Could not chown — run manually"

log_ok "Permissions set"

# ==============================================================
# STEP 7: Configure cron jobs
# ==============================================================
log_step "Step 7: Configuring cron jobs"

PHP_BIN=$(which php 2>/dev/null || echo "/usr/local/bin/php")

CRON_ENTRIES="
# AlumGlass — Daily reminders (8 AM Tehran time = 4:30 AM UTC)
30 4 * * * $PHP_BIN $DST_DOC_ROOT/pardis/send_daily_reminders.php >> $DST_DOC_ROOT/logs/cron.log 2>&1

# AlumGlass — Telegram daily report (6 PM Tehran = 2:30 PM UTC)  
30 14 * * * $PHP_BIN $DST_DOC_ROOT/pardis/telegram_cron.php >> $DST_DOC_ROOT/logs/cron.log 2>&1
"

echo "$CRON_ENTRIES" | crontab - 2>/dev/null || log_warn "Could not set crontab — add manually"
log_ok "Cron jobs configured"

# ==============================================================
# STEP 8: Verify migration
# ==============================================================
log_step "Step 8: Verification"

ERRORS=0

# Check .env
test -f "$DST_DOC_ROOT/.env" && log_ok ".env exists" || { log_err ".env MISSING"; ERRORS=$((ERRORS+1)); }

# Check databases
for DB in "${DATABASES[@]}"; do
    TABLES=$(mysql -h "$DST_DB_HOST" -u "$DST_DB_USER" -p"$DST_DB_PASS" "$DB" \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB';" -sN 2>/dev/null)
    if [ "$TABLES" -gt 0 ]; then
        log_ok "Database $DB: $TABLES tables"
    else
        log_err "Database $DB: empty or missing"
        ERRORS=$((ERRORS+1))
    fi
done

# Check user count
USERS=$(mysql -h "$DST_DB_HOST" -u "$DST_DB_USER" -p"$DST_DB_PASS" alumglas_common \
    -e "SELECT COUNT(*) FROM users;" -sN 2>/dev/null)
log_ok "Users migrated: $USERS"

# Check key files
for F in login.php index.php sercon/bootstrap.php includes/security.php .htaccess; do
    test -f "$DST_DOC_ROOT/$F" && log_ok "$F exists" || { log_err "$F MISSING"; ERRORS=$((ERRORS+1)); }
done

# Check upload dirs
for DIR in ghom/uploads pardis/uploads logs; do
    test -d "$DST_DOC_ROOT/$DIR" && log_ok "$DIR directory exists" || log_warn "$DIR missing"
done

echo ""
echo "╔══════════════════════════════════════════╗"
if [ "$ERRORS" -eq 0 ]; then
    echo "║   ✅ Migration completed successfully!  ║"
else
    echo "║   ⚠️  Migration completed with $ERRORS error(s) ║"
fi
echo "║                                          ║"
echo "║   Backup location: $BACKUP_DIR           "
echo "║   Users migrated: $USERS                  "
echo "║                                          ║"
echo "║   Next steps:                            ║"
echo "║   1. Test login at https://your-domain   ║"
echo "║   2. Test with each role (admin/car/cat) ║"
echo "║   3. Verify uploaded files are visible   ║"
echo "║   4. Update DNS if changing servers      ║"
echo "╚══════════════════════════════════════════╝"
```

### Also create `scripts/migrate_verify.php`:

```php
<?php
/**
 * Post-migration verification script
 * Run from CLI: php scripts/migrate_verify.php
 */

require_once __DIR__ . '/../sercon/bootstrap.php';

echo "AlumGlass Migration Verification\n";
echo str_repeat('=', 40) . "\n\n";

$checks = [];

// 1. Database connections
try {
    $common = getCommonDBConnection();
    $users = $common->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $checks[] = ['DB Common', true, "$users users"];
} catch (Exception $e) {
    $checks[] = ['DB Common', false, $e->getMessage()];
}

try {
    $ghom = getProjectDBConnection('ghom');
    $elements = $ghom->query("SELECT COUNT(*) FROM elements")->fetchColumn();
    $checks[] = ['DB Ghom', true, "$elements elements"];
} catch (Exception $e) {
    $checks[] = ['DB Ghom', false, $e->getMessage()];
}

try {
    $pardis = getProjectDBConnection('pardis');
    // Check a pardis-specific table
    $tables = $pardis->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $checks[] = ['DB Pardis', true, count($tables) . " tables"];
} catch (Exception $e) {
    $checks[] = ['DB Pardis', false, $e->getMessage()];
}

// 2. Environment
$checks[] = ['APP_ENV', getenv('APP_ENV') !== false, getenv('APP_ENV') ?: 'not set'];
$checks[] = ['TELEGRAM_TOKEN', !empty(getenv('TELEGRAM_BOT_TOKEN')), 'configured'];
$checks[] = ['.env file', file_exists(__DIR__ . '/../.env'), ''];

// 3. File permissions
$checks[] = ['logs/ writable', is_writable(__DIR__ . '/../logs'), ''];
$checks[] = ['.env not world-readable', !is_readable(__DIR__ . '/../.env') || (fileperms(__DIR__ . '/../.env') & 0004) === 0, ''];

// 4. Security files
$checks[] = ['info.php absent', !file_exists(__DIR__ . '/../info.php'), ''];
$checks[] = ['SQL dumps absent', empty(glob(__DIR__ . '/../*.sql*')), ''];

// Output
foreach ($checks as [$name, $pass, $detail]) {
    $icon = $pass ? '✅' : '❌';
    echo "$icon $name" . ($detail ? " — $detail" : "") . "\n";
}

echo "\n" . str_repeat('=', 40) . "\n";
$failed = count(array_filter($checks, fn($c) => !$c[1]));
echo $failed === 0 ? "All checks passed!\n" : "$failed check(s) failed.\n";
exit($failed > 0 ? 1 : 0);
```

### Commits:
```
feat(global): create migration script (scripts/migrate.sh)
feat(global): create post-migration verification (scripts/migrate_verify.php)
docs: add migration guide to SETUP.md
```

---

## 📋 Post-Completion Tasks

### Update docs:

1. **`docs/TECH_DEBT.md`** — Mark resolved:
   - TD-ARCH-001 (No MVC) → Partially resolved (Repository pattern)
   - TD-ARCH-003 (No Shared Library) → Resolved (shared/api, shared/repositories)
   - TD-ARCH-004 (No Tests) → Resolved (PHPUnit)
   - TD-ARCH-005 (No CI/CD) → Resolved (GitHub Actions)
   - TD-ARCH-006 (No Coding Standards) → Resolved (PSR-12 + PHPStan)

2. **`docs/CHANGELOG.md`** — Add v1.0.0 entry

3. **`docs/ARCHITECTURE.md`** — Update with:
   - New `shared/` directory structure
   - Repository pattern explanation
   - Project context resolver
   - CI/CD pipeline description
   - Migration process

4. **`docs/SETUP.md`** — Add migration section

### Tag release:
```bash
git tag v1.0.0 -m "Phase 3: Architecture refactoring + migration script"
git push origin v1.0.0
```

### Final report:
Create `docs/reports/phase3-final-report.md` with:
- Files before/after count
- Duplicate code eliminated
- Test coverage
- CI/CD pipeline status
- Migration script documentation
- Complete tech debt status
- Overall project summary (Phase 0 → Phase 3)

Provide download link:
```
📥 Final Report: https://github.com/saeidsm/AlumGlassProjMang/blob/main/docs/reports/phase3-final-report.md
📥 Full repo: https://github.com/saeidsm/AlumGlassProjMang/archive/refs/tags/v1.0.0.zip
```

---

## 🚀 START COMMAND

Begin execution now. No user approval needed between steps.
Start with: `git checkout main && git pull origin main && git checkout -b claude/phase-3a-shared-api`
