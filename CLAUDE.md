# CLAUDE.md — AlumGlass Project Management Dashboard

## 🎯 Project Overview

This is a **PHP-based engineering facade project management dashboard** (Alumglass-IR).
It manages construction/facade projects with inspection workflows, daily reports, warehouse management, calendar, messaging, and Telegram integration.

- **Language**: PHP 8.4+ / MySQL (MariaDB) / HTML / CSS / JavaScript
- **Architecture**: Traditional PHP (no framework), multi-project (ghom, pardis)
- **Server**: Apache on Linux (cPanel hosting)
- **Direction**: RTL (Persian/Farsi interface)

---

## 📁 Project Structure

```
AlumGlassProjMang/
├── CLAUDE.md                    # THIS FILE — Master instructions
├── .env.example                 # Environment template (NEVER commit .env)
├── .gitignore                   # Git ignore rules
├── .htaccess                    # Apache security + performance rules
├── docs/
│   ├── ARCHITECTURE.md          # System architecture document
│   ├── TECH_DEBT.md             # Technical debt registry
│   ├── SETUP.md                 # Installation & deployment guide
│   ├── CHANGELOG.md             # All changes log
│   └── AUDIT_REPORT.html        # Original audit report
├── sercon/                      # Server configuration (OUTSIDE document root ideally)
│   └── bootstrap.php            # Central bootstrap
├── public_html/                 # Document root
│   ├── index.php                # Entry point → login redirect
│   ├── login.php
│   ├── logout.php
│   ├── admin.php
│   ├── profile.php
│   ├── select_project.php
│   ├── messages.php
│   ├── analytics.php
│   ├── api/                     # Shared API endpoints
│   ├── assets/                  # Shared CSS/JS/fonts/images
│   ├── includes/                # Shared PHP includes
│   │   ├── security.php         # Central security middleware
│   │   ├── validation.php       # Input validation helpers
│   │   └── error_handler.php    # Unified error handling
│   ├── ghom/                    # Ghom project module
│   │   ├── api/
│   │   ├── assets/
│   │   └── includes/
│   └── pardis/                  # Pardis project module
│       ├── api/
│       ├── assets/
│       └── includes/
├── logs/                        # Application logs (OUTSIDE document root)
├── backups/                     # Database backups (OUTSIDE document root)
└── scripts/                     # Maintenance/deployment scripts
```

---

## 🔧 Development Commands

```bash
# Git workflow
git checkout -b phase-X/description   # Create feature branch
git add -A && git commit -m "type: description"  # Commit
git push origin phase-X/description   # Push
# Create PR to main when phase complete

# Test PHP syntax
find . -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"

# Find SQL injection vulnerabilities
grep -rn '->query("' --include="*.php" | grep '\$' | grep -v 'includes/mpdf\|includes/libraries'

# Find XSS vulnerabilities  
grep -rn 'echo.*\$_GET\|echo.*\$_POST\|<?=.*\$_GET\|<?=.*\$_POST' --include="*.php" | grep -v htmlspecialchars

# Count issues remaining
grep -rn '->query("' --include="*.php" | grep '\$' | wc -l

# Validate .env exists
test -f .env && echo "OK" || echo "MISSING .env"
```

---

## 📋 Commit Convention

Format: `type(scope): description`

Types:
- `security`: Security fixes (SQLi, XSS, CSRF, etc.)
- `fix`: Bug fixes
- `refactor`: Code restructuring without behavior change
- `perf`: Performance improvements
- `style`: CSS/UI changes
- `docs`: Documentation
- `chore`: Cleanup, config, dependencies
- `feat`: New features

Scopes: `global`, `ghom`, `pardis`, `api`, `auth`, `db`

Examples:
```
security(db): convert raw queries to prepared statements in daily_report_form_ps
security(global): move telegram token to .env
chore(global): remove 34 dead copy/old files
perf(pardis): add server-side pagination to daily_reports
docs: add ARCHITECTURE.md and SETUP.md
```

---

## 🚨 Phase 0 — Emergency Fixes (Day 1-3)

### Checklist:
- [ ] **0.1** Remove `info.php` (phpinfo exposure)
- [ ] **0.2** Remove `localhost.sql.txt` from repository (add to .gitignore)
- [ ] **0.3** Create `.env.example` and `.env` — move ALL secrets there:
  - Telegram bot token (from `telegram_webhook.php`, `test_webhook.php`)
  - Telegram cron secret key (from `pardis/telegram_cron.php`)
  - Weather API key
  - Database credentials (from `sercon/bootstrap.php`)
- [ ] **0.4** Create `.gitignore` with proper exclusions
- [ ] **0.5** Remove all 34 copy/old/dead files:
  ```
  adminold.php, ghom/footer copy.php, ghom/my_calendar copy.php,
  ghom/Copy of verify_signatures.php, ghom/api/save_inspection_old.php,
  ghom/api/get_element_data_old.php, ghom/api/get_cracks_for_plan copy.php,
  ghom/api/store_public_key copy.php, ghom/api/saveinspectionoldcorrect.php,
  ghom/api/getelementdataold.php, ghom/dailycopy.php,
  ghom/workshop_report copy.php, ghom/reportold.php,
  ghom/contractor_batch_update copy.php, ghom/header_ghom1.php,
  ghom/inspection_dashboard.new.php, ghom/inspection_dashboard.new1php.php,
  ghom/inspection_dashboard_diff.php, ghom/viewer_diff.php,
  pardis/letters - Copy.php, pardis/daily_report_submit - Copy.php,
  pardis/packing_list_viewer copy.php, pardis/zirsazi_api copy.php,
  pardis/zirsazi_status copy.php, pardis/meeting_minutes_form - Copy.php,
  pardis/project_schedule copy.php, pardis/daily_reports_dashboard_ps copy.php,
  pardis/daily_report_form_ps1.php, messages2 copy.php
  ```
- [ ] **0.6** Remove debug/test files from production:
  ```
  ghom/debug_test.php, ghom/vv.php, pardis/final_test.php,
  pardis/test_telegram_proxy.php, pardis/test_weather.php,
  test_webhook.php
  ```
- [ ] **0.7** Move/block log files:
  ```
  ghom/api/save_inspection_debug.log → logs/
  ghom/api/save_debug.log → logs/
  pardis/api/save_inspection_debug.log → logs/
  pardis/api/logs/*.log → logs/
  ```
- [ ] **0.8** Set `display_errors = Off` everywhere — ensure only `bootstrap.php` controls error reporting
- [ ] **0.9** Create initial Git repo, commit clean codebase
- [ ] **0.10** Create `docs/ARCHITECTURE.md`, `docs/TECH_DEBT.md`, `docs/SETUP.md`
- [ ] **0.11** Update `docs/CHANGELOG.md`

### Phase 0 Verification:
```bash
# Must return 0 results:
find . -name "info.php" -o -name "*.sql.txt" | wc -l
find . -name "* copy*" -o -name "*Copy*" -o -name "*old*" | grep -v vendor | grep -v node_modules | wc -l
find . -name "*debug*" -o -name "*test_*" | grep -v vendor | wc -l
grep -rn "display_errors.*1" --include="*.php" | wc -l
grep -rn "TELEGRAM_BOT_TOKEN.*=" --include="*.php" | grep -v "getenv\|_ENV\|\$_ENV" | wc -l
```

---

## 🔒 Phase 1 — Security Hardening (Week 1-3)

### 1.1 SQL Injection — Convert ALL raw queries to Prepared Statements

**Pattern to find:**
```php
// DANGEROUS — find all of these:
$pdo->query("SELECT * FROM table WHERE id = $variable");
$pdo->query("SELECT * FROM table WHERE id = {$variable}");
$pdo->query("SELECT * FROM table WHERE id = " . $variable);
```

**Pattern to replace with:**
```php
// SAFE:
$stmt = $pdo->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$variable]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
// or fetchAll() for multiple rows
```

**Files to fix (prioritized by risk — user-facing inputs first):**

High priority (direct user input):
1. `ghom/api/upload_signed_permit.php` (line 70 — element_id in query)
2. `pardis/daily_reports_dashboard_ps.php` (multiple lines)
3. `pardis/daily_report_form_ps.php` (lines 181, 195, 197, 209, 211, 233, 634, 684)
4. `pardis/daily_report_print_ps.php` (lines 44, 45, 48, 61)
5. `pardis/weekly_report_ps.php` (line 154)
6. `ghom/workshop_report.php` (line 47)

Medium priority (internal variables but still vulnerable):
7. All remaining files with `->query("` containing `$` variables

**Verification:**
```bash
# Must return 0:
grep -rn '->query("' --include="*.php" | grep '\$' | grep -v 'includes/mpdf\|includes/libraries\|includes/TCPDF' | wc -l
```

### 1.2 XSS — Escape ALL output

**Create helper** `includes/security.php`:
```php
function e($str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
```

**Files to fix:**
- `pardis/daily_reports_dashboard_ps.php` lines 369-387 (GET params in HTML)
- `pardis/daily_report_mobile.php` line 214-215
- All files echoing `$_GET`/`$_POST` without `htmlspecialchars`

**Verification:**
```bash
# Must return 0:
grep -rn 'echo.*\$_GET\|echo.*\$_POST\|<?=.*\$_GET\|<?=.*\$_POST' --include="*.php" | grep -v htmlspecialchars | grep -v 'json_encode' | wc -l
```

### 1.3 CSRF Protection Middleware

Create `includes/csrf.php`:
```php
function generateCsrfToken(): string { ... }
function verifyCsrfToken(): bool { ... }
function csrfField(): string { return '<input type="hidden" name="csrf_token" value="'.e($_SESSION['csrf_token']).'">'; }
```

Apply to ALL POST endpoints in `ghom/api/` and `pardis/api/`.

### 1.4 Authorization Middleware

Create `includes/auth.php`:
```php
function requireRole(array $allowedRoles): void {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        http_response_code(403);
        exit(json_encode(['status'=>'error','message'=>'Access denied']));
    }
}
function requireAuth(): void { ... }
```

Apply to every API endpoint.

### 1.5 File Upload Validation

```php
function validateUpload(array $file, array $allowedExtensions = ['pdf','jpg','jpeg','png'], int $maxSize = 5242880): bool { ... }
```

### 1.6 Security Headers

Add to `bootstrap.php` or `.htaccess`:
```
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.googleapis.com; font-src 'self' fonts.gstatic.com cdnjs.cloudflare.com;"
```

### 1.7 HTTPS Enforcement

```apache
# .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

### 1.8 Input Validation Layer

Create `includes/validation.php`:
```php
function validateInt($value): ?int { ... }
function validateString($value, int $maxLen = 255): ?string { ... }
function validateEmail($value): ?string { ... }
function validateDate($value): ?string { ... }
```

### 1.9 Unified Error Handler

Create `includes/error_handler.php`:
```php
// Production: friendly message to user, full details to log file
// Log format: JSON with timestamp, user_id, file, line, message
```

### 1.10 Server-Side Pagination

Add to all dashboard/list pages:
```php
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;
// Add LIMIT ? OFFSET ? to queries
```

### Phase 1 Verification:
```bash
# SQL Injection — must be 0:
grep -rn '->query("' --include="*.php" | grep '\$' | grep -v 'includes/mpdf\|includes/libraries' | wc -l

# XSS — must be 0:
grep -rn 'echo.*\$_GET\|<?=.*\$_GET' --include="*.php" | grep -v htmlspecialchars | grep -v json_encode | wc -l

# CSRF — all POST API files must contain csrf check:
for f in ghom/api/*.php pardis/api/*.php api/*.php; do grep -L 'csrf\|verifyCsrf\|requireAuth' "$f" 2>/dev/null; done

# Auth — all API files must have auth check:
for f in ghom/api/*.php pardis/api/*.php; do grep -L 'requireAuth\|requireRole\|isLoggedIn' "$f" 2>/dev/null; done
```

---

## ⚡ Phase 2 — Performance & UX (Month 1-2)

### 2.1 Extract Inline CSS/JS to External Files
- Create `assets/css/global.css` from common inline styles
- Create `assets/js/global.js` from common inline scripts  
- Per-module: `ghom/assets/css/ghom.css`, `pardis/assets/css/pardis.css`

### 2.2 Unify Headers (15 → 1 responsive)
- Create single `includes/header.php` with responsive design
- Use CSS media queries instead of separate mobile files
- Delete: `header_ghom1.php`, `header_ghom_mobile.php`, `header_m_ghom.php`, `header_p_mobile.php`, `header_pardis_mobile.php`

### 2.3 Merge Desktop/Mobile Pages
- For each `*_mobile.php`, merge into the main file with responsive CSS
- Delete mobile-only files after merge

### 2.4 Design System (CSS Variables)
```css
:root {
  --color-primary: #0a4d8c;
  --color-secondary: #042454;
  --color-success: #28a745;
  --color-danger: #dc3545;
  --color-warning: #ffc107;
  --font-family: 'Vazir', Tahoma, sans-serif;
  --border-radius: 8px;
  --spacing-unit: 8px;
}
```

### 2.5 Fix N+1 Queries
- Replace loop queries with JOINs or batch fetches
- Key files: `pardis/daily_reports_dashboard_ps.php`, `pardis/daily_report_form_ps.php`

### 2.6 Enable Compression
```apache
# .htaccess
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json
</IfModule>
```

### 2.7 HTTP Caching
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 year"
</IfModule>
```

### 2.8 Auto-save Forms + Loading States
- Add localStorage draft saving for long forms
- Add spinner/skeleton loading for AJAX calls
- Add toast notifications for operation results

### 2.9 API Rate Limiting

### 2.10 Documentation
- Complete `docs/ARCHITECTURE.md`
- Create API endpoint documentation
- Update `docs/SETUP.md`

---

## 🏗️ Phase 3 — Architecture (Month 2-4)

### 3.1 Service Layer Separation
### 3.2 Shared Library for ghom/pardis
### 3.3 Asset Bundling (Vite)
### 3.4 PHPUnit Tests
### 3.5 Coding Standards (PSR-12 + PHPStan)
### 3.6 PWA Support
### 3.7 CI/CD Pipeline
### 3.8 Accessibility (WCAG 2.1)
### 3.9 Database Connection Pooling

---

## ⚠️ Critical Rules

1. **NEVER commit `.env`** — only `.env.example` with placeholder values
2. **NEVER commit SQL dumps** — add `*.sql`, `*.sql.txt` to `.gitignore`
3. **NEVER use `->query()` with variables** — always `->prepare()` + `->execute()`
4. **NEVER echo user input without `htmlspecialchars()`**
5. **NEVER store secrets in PHP files** — use `$_ENV` or `getenv()`
6. **ALL API endpoints** must have auth + CSRF checks
7. **ALL file uploads** must validate extension + MIME type
8. **ALL changes** must be committed with proper commit message format
9. **Update `docs/TECH_DEBT.md`** when adding known shortcuts
10. **Update `docs/CHANGELOG.md`** after each phase completion

---

## 🗃️ Database

Database name: `alumglas_hpc` (MariaDB 11.4)
Key tables: `users`, `login_attempts`, `activity_log`, `app_settings`, `elements`, `inspections`, `permits`, `daily_reports`, `messages`, etc.

The database schema is documented in `docs/ARCHITECTURE.md`. Never include actual data or credentials in documentation.

---

## 🌐 Environment Variables (.env)

```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_COMMON_NAME=alumglas_common
DB_GHOM_NAME=alumglas_hpc
DB_PARDIS_NAME=alumglas_pardis
DB_USERNAME=
DB_PASSWORD=

# Telegram
TELEGRAM_BOT_TOKEN=
TELEGRAM_CRON_SECRET=

# Weather API
WEATHER_API_KEY=

# App
APP_ENV=production
APP_DEBUG=false
APP_URL=https://alumglass.ir

# Security
SESSION_LIFETIME=3600
LOGIN_LOCKOUT_TIME=3600
LOGIN_ATTEMPTS_LIMIT=5
```
