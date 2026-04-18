# تاریخچه تغییرات — AlumGlass Project Management
# Changelog

تمام تغییرات قابل توجه پروژه در این فایل مستند می‌شوند.
فرمت بر اساس [Keep a Changelog](https://keepachangelog.com/fa/1.1.0/).

---

## [Unreleased]

### Phase 4C — Form Wizard & Auto-save (2026-04-18)
#### Added
- [x] `assets/js/form-wizard.js` — ES6 `FormWizard` class: progress bar + step indicator, per-step validation via `checkValidity()`, Enter-key-advances (not submit), Prev/Next nav, debounced + interval draft auto-save to `localStorage`, restore-toast integration, `ag:wizard-step` event
- [x] `assets/css/form-wizard.css` — stepper, progress bar, animations, mobile-responsive (stacks 2 per row under 640px)
- [x] `docs/examples/form-wizard.md` — integration guide + suggested step breakdowns for the two target forms

#### Deferred (captured as tech debt)
- [ ] TD-UX-002 — Convert `pardis/daily_report_form_ps.php` (1,599 lines) into 7 wizard steps
- [ ] TD-UX-003 — Convert `pardis/meeting_minutes_form.php` (1,970 lines) into 4 wizard steps

Both targets were postponed: the component is ready and tested, but wrapping the existing inline-state-heavy forms safely requires a running PHP environment to verify. The integration path is fully documented in the tech-debt entries.

#### Changed
- [x] `header_common.php` — pulls in `form-wizard.css` so every logged-in page can opt into wizardised forms without extra `<link>` tags

---

### Phase 4B — Mobile Experience & PWA (2026-04-18)
#### Added
- [x] `assets/css/mobile-nav.css` + `assets/js/mobile-nav.js` — fixed bottom nav (Home, Reports, Chat+badge, Calendar, More) under 768px; 44px targets; safe-area-inset-bottom
- [x] `assets/css/responsive-tables.css` + `assets/js/responsive-tables.js` — auto card-view on phones via `table.ag-table-responsive`; MutationObserver picks up dynamic tables
- [x] `assets/css/touch-gestures.css` + `assets/js/touch-gestures.js` — pull-to-refresh (`data-ag-pull-refresh`), `AG.onSwipe()` helper, min-height safety net on small screens
- [x] `manifest.webmanifest` — installable PWA (RTL, Persian name, shortcuts)
- [x] `service-worker.js` — network-first nav + offline.html; stale-while-revalidate for static; cache-first immutable `/storage/`; network-only for APIs; `SKIP_WAITING` message handler
- [x] `offline.html` — standalone offline fallback
- [x] `assets/js/pwa-register.js` — registration + update-available toast

#### Changed
- [x] `header_common.php` — pulls in all four Phase-4B stylesheets + scripts + `<link rel=manifest>` + `theme-color`

---

### Phase 4A — Real-time Chat + File Deduplication (2026-04-18)
#### Added
- [x] `shared/services/FileService.php` — content-addressable SHA-256 storage; deduplication via ref-counting; cleanup on last-reference removal
- [x] `storage/serve.php` + `storage/.htaccess` — auth-gated file serving with immutable caching + path-traversal guard
- [x] `scripts/migrations/004_chat_tables.sql` — `conversations`, `conversation_members`, `user_presence`; idempotent ALTERs on `messages` for `conversation_id`, `reply_to_id`, `reactions`, `message_type`, `file_ref_id`
- [x] `scripts/migrations/005_file_storage.sql` — `file_store` + `file_references` tables
- [x] `scripts/cleanup_files.php` — nightly orphan sweep (cron)
- [x] `websocket/` — Node.js + `ws` WebSocket relay with PHP session auth, heartbeat, presence, typing, read receipts; PM2 ecosystem file
- [x] `chat/api/` — `conversations.php`, `direct.php`, `messages.php`, `search.php`, `read.php`, `contacts.php`, `upload.php`, `verify_session.php` (loopback-only)
- [x] `chat/assets/js/` — ES6 module frontend: `chat-socket.js`, `chat-ui.js`, `chat-search.js`, `chat-notifications.js`, `chat-app.js`
- [x] `chat/assets/css/chat.css` — RTL-first, responsive (mobile switches to single-pane)
- [x] `chat/index.php` — thin semantic skeleton with progressive-enhancement module script

#### Changed
- [x] `messages.php` — full rewrite: 1,575-line monolith → 301 redirect to `/chat/` (preserves `user_id` / `conversation` query params)

#### Security
- [x] Upload MIME + size validation at FileService boundary
- [x] Storage directory blocks script execution (`.htaccess` `FilesMatch` deny)
- [x] `verify_session.php` restricted to loopback (`127.0.0.1`, `::1`) unless `WS_VERIFY_ALLOW_REMOTE=1`

---

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

### Phase 1.5 — CSRF Completion & Role-Based Authorization (2026-04-17)
#### Security
- [x] Added `csrfField()` to 55 HTML POST forms across 18 files (admin, settings, reporting, management pages)
- [x] Created `assets/js/csrf-injector.js` — automatically attaches `X-CSRF-Token` header and `csrf_token` body param to every non-GET request (jQuery.ajax, fetch, XMLHttpRequest)
- [x] Added `<meta name="csrf-token">` to all 10 header variants (common, ghom, pardis, and mobile siblings)
- [x] Auto-loaded `includes/security.php` from `sercon/bootstrap.php` so `csrfField()` and `requireCsrf()` are globally available
- [x] Replaced ad-hoc `isLoggedIn()` + `in_array` role checks with centralized `requireRole()` on 23 admin/management endpoints:
  - **Admin only** (11): save_settings, save_print_settings, save_logo_settings, delete_logo, save_workflow_order, admin_reports, admin_metrics, settings_all, settings_ps, pardis_importer, manage_weights
  - **Admin + Superuser** (7): batch_update, batch_update_plan_files, batch_update_status, delete_stage, update_element_final_status, save_template, save_stage
  - **Admin + Superuser + Cat** (3): save_inspection, confirm_panels_opened, save_permit_checklist
  - **Admin + Superuser + Cat + Crs** (1): create_permit

#### Added
- [x] `assets/js/csrf-injector.js` — global AJAX CSRF token injector

#### Changed
- [x] `sercon/bootstrap.php` now requires `includes/security.php`
- [x] Admin-only pages like `admin_reports.php` and `settings_ps.php` now use `requireRole(['admin'])` instead of inline role checks

---

### Phase 2 — Performance & UX (2026-04-17)

#### Phase 2A — Design System & Global Assets
##### Added
- [x] `assets/css/design-system.css` — CSS custom properties (`--ag-*`) for brand colors, semantic colors, neutrals, typography, spacing, radii, shadows, z-index; Vazir font-face declarations
- [x] `assets/css/global.css` — shared components (`.ag-card`, `.ag-btn`, `.ag-badge`, `.ag-table`, `.ag-form-control`, `.ag-alert`, `.ag-spinner`, `.ag-toast`, `.ag-pagination`)
- [x] `assets/js/global.js` — `window.AG` utilities: `toast()`, `showLoading/hideLoading()`, `getCsrfToken()`, `fetch()` with CSRF injection, `confirm()`, `formatNumber()`, `formatPersianDate()`, `debounce()`, `autoSaveForm()/restoreForm()`

#### Phase 2B — Header Unification
##### Added
- [x] `ghom/header.php` — responsive dispatcher (mobile/desktop UA detection)
- [x] `pardis/header.php` — responsive dispatcher
- [x] design-system.css + global.css + global.js wired into all remaining header implementations plus `header_common.php`

##### Changed
- [x] Rewrote 91 `require_once` statements across 70+ files to reference `header.php`

##### Removed
- [x] `ghom/header_m_ghom.php`, `ghom/header_mobile.php`, `ghom/header_ins.php` (subsumed by dispatcher)
- [x] `pardis/header_p_mobile.php` (duplicate of `header_pardis_mobile.php`)
- [x] Root-level `header_m_ghom.php` (stray copy)
- Result: **11 header files → 6** (–45%)

#### Phase 2C — Inline CSS/JS Extraction
##### Added
- [x] 17 external CSS files, 6 external JS files extracted from the 10 largest PHP pages

##### Changed
- Notable size reductions (bytes before → after):
  - `ghom/reports.php`: 74KB → 21KB (–72%)
  - `pardis/index.php`: 71KB → 33KB (–53%)
  - `ghom/index.php`: 65KB → 32KB (–51%)
  - `messages.php`: 98KB → 76KB (–22%)
  - `ghom/viewer.php`: 70KB → 55KB (–22%)
  - `pardis/letters.php`: 72KB → 58KB (–20%)
  - `admin.php`: 65KB → 53KB (–17%)
  - `pardis/packing_list_viewer.php`: 121KB → 101KB (–16%)
  - `pardis/daily_reports.php`: 139KB → 124KB (–11%)
  - `pardis/meeting_minutes_form.php`: 89KB → 80KB (–10%)

#### Phase 2D — Responsive Mobile Consolidation
##### Changed
- [x] Converted 10 mobile-only pages to 301 redirects to their responsive desktop counterparts: `ghom/mobile.php`, `ghom/contractmibile.php`, `ghom/contractor_batch_update_mobile.php`, `ghom/inspection_dashboard_mobile.php`, `ghom/reports_mobile.php`, `pardis/mobile.php`, `pardis/mobile_plan.php`, `pardis/daily_report_mobile.php`, `pardis/viewer_3d_mobile.php`, `messages_mobile.php`

#### Phase 2E — N+1 Fixes & Pagination
##### Added
- [x] `includes/pagination.php` — `paginate($pdo, $sql, $params, $perPage)` + `renderPagination($result, $baseUrl)`

##### Fixed
- [x] **N+1 in `pardis/daily_reports_dashboard_ps.php`** — 3 queries per row (personnel, equipment, activity count) collapsed into 3 batched GROUP BY queries
- [x] **N+1 in `ghom/daily_reports_dashboard.php`** — same pattern, same fix
- [x] **N+1 in `pardis/weekly_report_ps.php`** — per-report personnel count query batched
- [x] **N+1 in `pardis/daily_report_form_ps.php`** — per-activity name lookup batched with `IN (...)` query

##### Changed
- [x] Applied `paginate()` (25/page) with `<nav class="ag-pagination">` to `pardis/daily_reports_dashboard_ps.php` and `ghom/daily_reports_dashboard.php`

---

### Phase 3 — Architecture Refactoring (2026-04-17)

#### Phase 3A — Shared API Layer
##### Added
- [x] `shared/includes/project_context.php` — `getCurrentProject()` resolver (session → URL → GET/POST) and `getProjectDB()` helper
- [x] `shared/api/` — 32 unified endpoints (get_stages, save_template, get_calendar_events, batch_update_status, …) previously duplicated per project
- [x] `shared/includes/jdf.php` — Jalali date library, single source of truth

##### Changed
- [x] `ghom/api/*.php` and `pardis/api/*.php` (32 files each) — now one-line shims that `require_once '../../shared/api/' . basename(__FILE__)`. Original URLs unchanged.

##### Removed
- [x] ~7500 lines of duplicated logic across ghom/api/ and pardis/api/

#### Phase 3B — Data Access Layer
##### Added
- [x] `shared/repositories/ElementRepository.php` — element queries (findById, findByZone, status counts, updateStatus)
- [x] `shared/repositories/InspectionRepository.php` — inspection queries (findByElement, getStatsByStage, getRecentByUser)
- [x] `shared/repositories/DailyReportRepository.php` — report queries + `getActivityCountsByReportIds()` batch helper
- [x] `shared/repositories/UserRepository.php` — user queries with batch `findByIds()`
- [x] `getRepository()` factory in `sercon/bootstrap.php` — lazy singletons, auto-binds project repos to current project DB

#### Phase 3C — Coding Standards
##### Added
- [x] `composer.json` — dev deps (PHPUnit 10.5, PHPStan 1.10, phpcs 3.9) + scripts (test/analyse/lint/lint-fix)
- [x] `phpstan.neon` — level 3 over shared/ and includes/
- [x] `phpcs.xml` — PSR-12 baseline
- [x] `.editorconfig` — LF endings, 4-space PHP, 2-space frontend

#### Phase 3D — Tests
##### Added
- [x] `phpunit.xml` + `tests/bootstrap.php` (standalone, no DB)
- [x] `tests/Unit/SecurityTest.php` — escape + CSRF lifecycle (10 tests)
- [x] `tests/Unit/ValidationTest.php` — int/string/email/date/in-array (19 tests)
- [x] `tests/Unit/ProjectContextTest.php` — session/URL/GET/POST precedence (8 tests)
- [x] `tests/Unit/PaginationTest.php` — renderPagination branches (8 tests)
- [x] `tests/Unit/UserRepositoryTest.php` — in-memory SQLite integration (7 tests)

#### Phase 3E — CI/CD
##### Added
- [x] `.github/workflows/ci.yml` — three jobs: lint (syntax + PHPStan + PHPCS), test (PHPUnit), security (SQLi/XSS/secret/dump gates)

#### Phase 3F — Migration
##### Added
- [x] `scripts/migrate.sh` — 8-step bash migrator (credentials → dump → rsync → import → .env → permissions → cron → verify)
- [x] `scripts/migrate_verify.php` — post-migration health check (DB connectivity, env vars, filesystem perms, Phase 3 layout, dangerous-file absence)

---

## [1.0.0] — 2026-04-17
First stable release. Phases 0 → 3 complete: hardened, performant, tested, documented, and deployable via `scripts/migrate.sh`.

---

## Version History

| نسخه | تاریخ | شرح |
|------|--------|------|
| 1.0.0 | 2026-04-17 | Phase 3 — Shared API + repositories + tests + CI/CD + migration script |
| 0.3.0 | 2026-04-17 | Phase 2 — Design system, header unification, inline-asset extraction, responsive mobile consolidation, N+1 fixes, pagination |
| 0.2.1 | 2026-04-17 | Phase 1.5 — CSRF forms + global AJAX injector + role-based auth |
| 0.2.0 | 2026-04-17 | Phase 1 — Security hardening, prepared statements, CSRF, auth, headers |
| 0.1.0 | 2026-04-17 | Phase 0 — Emergency fixes, cleanup, secrets removal |
