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

### Phase 1 — Security Hardening
#### Security
- [ ] Converted 211 raw SQL queries to prepared statements
- [ ] Fixed XSS vulnerabilities with htmlspecialchars()
- [ ] Added CSRF middleware to all POST endpoints
- [ ] Added authorization checks to all API endpoints
- [ ] Added file upload validation (extension + MIME)
- [ ] Added security headers (CSP, HSTS, X-Frame-Options)
- [ ] Enforced HTTPS redirect
- [ ] Created centralized input validation layer

#### Added
- [ ] `includes/security.php` — security middleware
- [ ] `includes/validation.php` — input validation
- [ ] `includes/error_handler.php` — unified error handling

#### Changed
- [ ] Server-side pagination added to dashboards

---

### Phase 2 — Performance & UX (Planned)
*To be documented upon completion*

### Phase 3 — Architecture (Planned)
*To be documented upon completion*

---

## Version History

| نسخه | تاریخ | شرح |
|------|--------|------|
| 0.1.0 | 2026-04-17 | Phase 0 — Emergency fixes, cleanup, secrets removal |
