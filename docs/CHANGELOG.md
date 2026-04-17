# تاریخچه تغییرات — AlumGlass Project Management
# Changelog

تمام تغییرات قابل توجه پروژه در این فایل مستند می‌شوند.
فرمت بر اساس [Keep a Changelog](https://keepachangelog.com/fa/1.1.0/).

---

## [Unreleased]

### Phase 0 — Emergency Fixes
#### Removed
- [ ] `info.php` — phpinfo() exposure
- [ ] `localhost.sql.txt` — database dump in document root
- [ ] 34 copy/old/dead files
- [ ] Debug/test files from production
- [ ] Log files from document root

#### Added
- [ ] `.env.example` — environment template
- [ ] `.gitignore` — proper exclusions
- [ ] `docs/ARCHITECTURE.md` — system architecture
- [ ] `docs/TECH_DEBT.md` — technical debt registry
- [ ] `docs/SETUP.md` — installation guide
- [ ] `docs/CHANGELOG.md` — this file

#### Changed
- [ ] Telegram tokens moved to `.env`
- [ ] Cron secret key moved to `.env`
- [ ] `display_errors` disabled in all files

#### Security
- [ ] Removed exposed credentials from source code
- [ ] Removed publicly accessible log files
- [ ] Removed debug endpoints

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
| 0.1.0 | 1405/01/27 | Initial Git commit — cleaned codebase |
| — | — | — |
