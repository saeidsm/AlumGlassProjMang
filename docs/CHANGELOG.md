# تاریخچه تغییرات — AlumGlass Project Management
# Changelog

تمام تغییرات قابل توجه پروژه در این فایل مستند می‌شوند.
فرمت بر اساس [Keep a Changelog](https://keepachangelog.com/fa/1.1.0/).

---

## [Unreleased]

### Phase 0 — Emergency Fixes (2026-04-17)
#### Removed
- [x] `info.php` — phpinfo() exposure (gitignored, deleted from disk)
- [x] `localhost.sql.txt` — database dump (gitignored, deleted from disk)
- [x] 37 copy/old/dead files across ghom/, pardis/, root
- [x] 6 debug/test files (debug_test, vv, final_test, test_telegram_proxy, test_weather, test_webhook)
- [x] Log files moved from document root to `logs/`

#### Added
- [x] `.env.example` — environment template
- [x] `.gitignore` — proper exclusions (logs, sql, env, IDE files)
- [x] `docs/ARCHITECTURE.md` — system architecture
- [x] `docs/TECH_DEBT.md` — technical debt registry
- [x] `docs/SETUP.md` — installation guide
- [x] `docs/CHANGELOG.md` — this file
- [x] `logs/` directory structure (outside document root)

#### Changed
- [x] Telegram bot tokens moved to `getenv('TELEGRAM_BOT_TOKEN')`
- [x] Cron secret key moved to `getenv('TELEGRAM_CRON_SECRET')`
- [x] `display_errors` disabled in api/send_message.php, ghom/upload_weekly_data.php, api/get_new_messages.php

#### Security
- [x] Removed 2 hardcoded Telegram bot tokens from source code
- [x] Removed hardcoded cron secret key from source code
- [x] Removed publicly accessible log files from document root
- [x] Removed debug/test endpoints that expose system internals

---

### Phase 1 — Security Hardening (2026-04-17)
#### Security
- [x] Converted 30+ raw SQL queries to prepared statements across 8 files (pardis + ghom)
- [x] Fixed XSS vulnerabilities — htmlspecialchars() on all $_GET/$_POST output
- [x] Added CSRF middleware (`requireCsrf()`) to 41 POST API endpoints
- [x] Added `isLoggedIn()` auth checks to 50 API endpoints
- [x] Created file upload validation (`validateUpload()`) with extension + MIME check
- [x] Added security headers via .htaccess (CSP, HSTS, X-Frame-Options, X-Content-Type-Options)
- [x] Enforced HTTPS redirect via .htaccess RewriteRule
- [x] Created centralized input validation layer (`includes/validation.php`)
- [x] Centralized error handling — removed 9 per-file `display_errors` overrides

#### Added
- [x] `sercon/bootstrap.php` — central bootstrap (DB connections, session, auth, logging, output helpers)
- [x] `includes/security.php` — CSRF tokens, file upload validation
- [x] `includes/validation.php` — input validation (int, string, date, email)
- [x] `includes/error_handler.php` — global error/exception handlers
- [x] `.htaccess` — security headers, HTTPS, compression, caching

#### Changed
- [x] All API endpoints now require authentication before processing
- [x] All POST API endpoints now validate CSRF tokens
- [x] Error display disabled globally via bootstrap (was per-file)

---

### Phase 2 — Performance & UX (Planned)
*To be documented upon completion*

### Phase 3 — Architecture (Planned)
*To be documented upon completion*

---

## Version History

| نسخه | تاریخ | شرح |
|------|--------|------|
| 0.2.0 | 2026-04-17 | Phase 1 — Security hardening, prepared statements, CSRF, auth, headers |
| 0.1.0 | 2026-04-17 | Phase 0 — Emergency fixes, cleanup, secrets removal |
