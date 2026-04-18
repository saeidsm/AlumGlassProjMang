# بدهی فنی — AlumGlass Project Management
# Technical Debt Registry

> آخرین بروزرسانی: فروردین ۱۴۰۵ / April 2026  
> نسخه: 1.0.0

---

## وضعیت کلی (Summary)

| وضعیت | تعداد | شرح |
|--------|-------|------|
| 🔴 باز — بحرانی | 0 | — |
| 🟠 باز — بالا | 1 | Phase 3 برنامه‌ریزی شده |
| 🟡 باز — متوسط | 8 | برنامه‌ریزی شده (بیشتر Phase 3) |
| 🟢 رفع شده | 20 | تکمیل شده (Phase 0 + 1 + 1.5 + 2) |

---

## بدهی‌های امنیتی (Security Debt)

### TD-SEC-001: SQL Injection — Raw Queries
- **شدت**: 🔴 بحرانی
- **وضعیت**: 🟢 رفع شده
- **فاز**: 1
- **شرح**: ۳۰+ مورد `->query()` با متغیر PHP مستقیم در رشته SQL
- **راهکار**: تبدیل به `prepare()` + `execute()`
- **تاریخ شناسایی**: 1405/01/27
- **تاریخ رفع**: 1405/01/28
- **commit**: phase-1 branch — pardis (5 files, 28 queries), ghom (3 files)

### TD-SEC-002: XSS — Unescaped Output
- **شدت**: 🔴 بحرانی
- **وضعیت**: 🟢 رفع شده
- **فاز**: 1
- **شرح**: `$_GET` بدون `htmlspecialchars()` در HTML
- **راهکار**: تابع کمکی `e()` + htmlspecialchars مستقیم
- **تاریخ شناسایی**: 1405/01/27
- **تاریخ رفع**: 1405/01/28
- **commit**: phase-1 branch — daily_reports_dashboard_ps.php (3 instances)

### TD-SEC-003: Missing CSRF on API Endpoints
- **شدت**: 🔴 بحرانی
- **وضعیت**: 🟢 رفع شده (Phase 1.5 تکمیل شد)
- **فاز**: 1 + 1.5
- **شرح**: ۴۱ endpoint در `ghom/api/` و `pardis/api/` فاقد CSRF
- **راهکار**: `requireCsrf()` middleware در `includes/security.php`
- **Phase 1.5 تکمیل**:
  - `csrfField()` در ۵۵ فرم HTML POST در ۱۸ فایل اضافه شد
  - `assets/js/csrf-injector.js` ساخته شد — X-CSRF-Token را به‌صورت خودکار روی jQuery.ajax / fetch / XHR ست می‌کند
  - `<meta name="csrf-token">` در تمام ۱۰ header variant اضافه شد
  - `sercon/bootstrap.php` حالا `includes/security.php` را auto-load می‌کند
- **تاریخ شناسایی**: 1405/01/27
- **تاریخ رفع**: 2026-04-17 (Phase 1.5)
- **commit**: phase-1 branch — 41 POST API files + Phase 1.5 forms/injector

### TD-SEC-004: Missing Authorization on APIs
- **شدت**: 🔴 بحرانی
- **وضعیت**: 🟢 رفع شده (Phase 1.5 تکمیل شد)
- **فاز**: 1 + 1.5
- **شرح**: ۵۰ API فاقد بررسی auth مناسب
- **راهکار**: `requireLogin()` + `requireRole()` در `sercon/bootstrap.php`
- **Phase 1.5 تکمیل**: نقش‌محور شدن ۲۳ endpoint مدیریتی/ویرایشی:
  - Level 1 (`admin` only) — ۱۱ endpoint تنظیمات/ایمپورت/وزن
  - Level 2 (`admin`,`superuser`) — ۷ endpoint batch update / template / stage
  - Level 3 (`admin`,`superuser`,`cat`) — ۳ endpoint بازرسی و چک‌لیست
  - Level 3+ (`admin`,`superuser`,`cat`,`crs`) — create_permit
- **تاریخ شناسایی**: 1405/01/27
- **تاریخ رفع**: 2026-04-17 (Phase 1.5)
- **commit**: phase-1 branch — 50 API files + Phase 1.5 role tightening

### TD-SEC-005: File Upload Without Validation
- **شدت**: 🔴 بحرانی
- **وضعیت**: 🟢 رفع شده
- **فاز**: 1
- **شرح**: آپلود فایل بدون بررسی نوع و اندازه
- **راهکار**: `validateUpload()` در `includes/security.php` — extension + MIME check
- **تاریخ شناسایی**: 1405/01/27
- **تاریخ رفع**: 1405/01/28
- **commit**: phase-1 branch — includes/security.php

### TD-SEC-006: No Security Headers
- **شدت**: 🟠 بالا
- **وضعیت**: 🟢 رفع شده
- **فاز**: 1
- **شرح**: CSP, HSTS, X-Frame-Options, X-Content-Type-Options تنظیم نشده
- **راهکار**: `.htaccess` با تمام هدرهای امنیتی
- **تاریخ شناسایی**: 1405/01/27
- **تاریخ رفع**: 1405/01/28
- **commit**: phase-1 branch — .htaccess

### TD-SEC-007: No HTTPS Enforcement
- **شدت**: 🟠 بالا
- **وضعیت**: 🟢 رفع شده
- **فاز**: 1
- **شرح**: Redirect HTTP→HTTPS وجود ندارد
- **راهکار**: `.htaccess` RewriteRule
- **تاریخ شناسایی**: 1405/01/27
- **تاریخ رفع**: 1405/01/28
- **commit**: phase-1 branch — .htaccess

### TD-SEC-008: Input Validation Missing
- **شدت**: 🟠 بالا
- **وضعیت**: 🟢 رفع شده
- **فاز**: 1
- **شرح**: فقط ۱۰ فایل از `filter_var` / `filter_input` استفاده می‌کنند
- **راهکار**: `includes/validation.php` — validateInt, validateString, validateDate, validateEmail
- **تاریخ شناسایی**: 1405/01/27
- **تاریخ رفع**: 1405/01/28
- **commit**: phase-1 branch — includes/validation.php

---

## بدهی‌های کارایی (Performance Debt)

### TD-PERF-001: Monolithic Files
- **شدت**: 🟠 بالا
- **وضعیت**: 🟡 کاهش یافته
- **فاز**: 2
- **شرح**: ۳۹ فایل بالای 50KB — HTML+CSS+JS+PHP مخلوط
- **Phase 2 نتیجه**: ۱۰ فایل بزرگ کاهش یافتند؛ بزرگ‌ترین‌ها ۱۰–۷۲٪ کوچک‌تر شدند (بعد از استخراج CSS/JS)
- **باقیمانده**: فایل‌های <50KB در سطح متوسط هنوز نیاز به تجزیه دارند — Phase 3 (MVC)

### TD-PERF-002: 297 Inline CSS/JS Blocks
- **شدت**: 🟠 بالا
- **وضعیت**: 🟢 رفع شده (در ۱۰ فایل اصلی)
- **فاز**: 2
- **شرح**: بلاک‌های inline به CSS/JS خارجی منتقل شدند
- **تاریخ رفع**: 2026-04-17 (Phase 2C)
- **commit**: phase-2c-extract-inline-assets — 17 CSS + 6 JS external files

### TD-PERF-003: No Pagination
- **شدت**: 🟠 بالا
- **وضعیت**: 🟢 رفع شده
- **فاز**: 2
- **شرح**: helper مرکزی `includes/pagination.php` اضافه شد و در ۲ داشبورد کلیدی اعمال شد
- **تاریخ رفع**: 2026-04-17 (Phase 2E)
- **commit**: phase-2e-query-optimization — includes/pagination.php

### TD-PERF-004: N+1 Queries
- **شدت**: 🟠 بالا
- **وضعیت**: 🟢 رفع شده (۴ مورد اصلی)
- **فاز**: 2
- **شرح**: کوئری در حلقه — هر ردیف یک کوئری جدا
- **تاریخ رفع**: 2026-04-17 (Phase 2E)
- **commit**: phase-2e-query-optimization — 4 files fixed with batched IN queries

### TD-PERF-005: No Compression
- **شدت**: 🟡 متوسط
- **وضعیت**: 🟢 رفع شده
- **فاز**: 1
- **شرح**: Gzip/Brotli فعال نیست
- **راهکار**: `.htaccess` با mod_deflate (Phase 1)

---

## بدهی‌های معماری (Architecture Debt)

### TD-ARCH-001: No MVC / Service Layer
- **شدت**: 🟠 بالا
- **وضعیت**: 🟡 جزئی رفع شده (Repository pattern)
- **فاز**: 3
- **شرح**: منطق، نمایش و دسترسی داده همه در یک فایل
- **Phase 3B نتیجه**: Repository pattern اضافه شد — `shared/repositories/` با `ElementRepository`, `InspectionRepository`, `DailyReportRepository`, `UserRepository`. لایه Controller/View هنوز یکپارچه نشده.
- **تاریخ**: 2026-04-17 (Phase 3B)
- **commit**: phase-3b-data-access-layer

### TD-ARCH-002: 15 Duplicate Headers
- **شدت**: 🟠 بالا
- **وضعیت**: 🟢 رفع شده
- **فاز**: 2
- **شرح**: هر ماژول چندین نسخه header داشت
- **Phase 2 نتیجه**: ۱۱ فایل header → ۶ فایل (کاهش ۴۵٪)
  - `ghom/header.php` + `pardis/header.php` — dispatcher responsive جدید
  - ۵ فایل تکراری حذف شد (`header_m_ghom.php`, `header_mobile.php`, `header_ins.php`, `header_p_mobile.php`, root-level `header_m_ghom.php`)
  - ۹۱ require_once در ۷۰+ فایل به header یکپارچه هدایت شدند
- **تاریخ رفع**: 2026-04-17 (Phase 2B)
- **commit**: phase-2b-header-unification

### TD-ARCH-003: No Shared Library
- **شدت**: 🟠 بالا
- **وضعیت**: 🟢 رفع شده
- **فاز**: 3
- **شرح**: کد مشترک بین ghom و pardis تکرار شده
- **Phase 3A نتیجه**: `shared/api/` (32 endpoint) + `shared/includes/project_context.php` (getCurrentProject) + `shared/includes/jdf.php`. فایل‌های ghom/api و pardis/api به shim تبدیل شدند. `shared/repositories/` در Phase 3B اضافه شد.
- **تاریخ رفع**: 2026-04-17 (Phase 3A + 3B)
- **commit**: phase-3a-shared-api, phase-3b-data-access-layer

### TD-ARCH-004: No Automated Tests
- **شدت**: 🟡 متوسط
- **وضعیت**: 🟢 رفع شده
- **فاز**: 3
- **شرح**: هیچ Unit/Integration test نوشته نشده
- **Phase 3D نتیجه**: PHPUnit 10.5 setup + ۵۲ تست در ۵ کلاس (SecurityTest, ValidationTest, ProjectContextTest, PaginationTest, UserRepositoryTest با SQLite in-memory).
- **تاریخ رفع**: 2026-04-17 (Phase 3D)
- **commit**: phase-3d-testing

### TD-ARCH-005: No CI/CD
- **شدت**: 🟡 متوسط
- **وضعیت**: 🟢 رفع شده
- **فاز**: 3
- **شرح**: deploy دستی بدون pipeline
- **Phase 3E نتیجه**: `.github/workflows/ci.yml` با ۳ job — lint (syntax + PHPStan + PHPCS), test (PHPUnit), security (SQLi/XSS/secret/dump gates).
- **تاریخ رفع**: 2026-04-17 (Phase 3E)
- **commit**: phase-3e-cicd

### TD-ARCH-006: No Coding Standards
- **شدت**: 🟡 متوسط
- **وضعیت**: 🟢 رفع شده
- **فاز**: 3
- **شرح**: نام‌گذاری ناسازگار، بدون PSR
- **Phase 3C نتیجه**: `composer.json` + `phpstan.neon` (level 3) + `phpcs.xml` (PSR-12) + `.editorconfig`. Scripts: `composer test | analyse | lint | lint-fix`.
- **تاریخ رفع**: 2026-04-17 (Phase 3C)
- **commit**: phase-3c-coding-standards

---

## بدهی‌های UX (UX Debt)

### TD-UX-001: Separate Mobile Pages
- **شدت**: 🟠 بالا
- **وضعیت**: 🟢 رفع شده
- **فاز**: 2
- **شرح**: نسخه‌های جداگانه Desktop/Mobile به جای Responsive
- **Phase 2 نتیجه**: ۱۰ فایل mobile به redirect ۳۰۱ تبدیل شدند؛ نمای موبایل حالا از طریق dispatcher و مدیا کوئری انجام می‌شود
- **تاریخ رفع**: 2026-04-17 (Phase 2D)
- **commit**: phase-2d-responsive-merge

### TD-UX-002: Contractor Batch Update Feature Parity
- **شدت**: 🟡 متوسط
- **وضعیت**: ⏳ باز
- **فاز**: 3
- **شرح**: `ghom/contractor_batch_update_mobile.php` در اصل ۱۶۸۲ خط بود و نسبت به نسخه دسکتاپ (۲۹۳ خط) قابلیت‌های بیشتری داشت؛ redirect حالا هر دو را به دسکتاپ می‌فرستد
- **اقدام**: قابلیت‌های منحصربه‌فرد موبایل به `contractor_batch_update.php` responsive منتقل شوند

### TD-UX-003: No Loading States
- **شدت**: 🟡 متوسط
- **وضعیت**: 🟢 رفع شده (ابزار اضافه شد)
- **فاز**: 2
- **شرح**: عملیات AJAX بدون نشانگر بارگذاری
- **Phase 2 نتیجه**: `AG.showLoading()` / `AG.hideLoading()` / `.ag-spinner` / `.ag-toast` در `assets/js/global.js` و `assets/css/global.css` در دسترس — کدهای AJAX می‌توانند به صورت افزایشی از آنها استفاده کنند
- **تاریخ رفع**: 2026-04-17 (Phase 2A)

### TD-UX-004: No Form Auto-save
- **شدت**: 🟡 متوسط
- **وضعیت**: 🟢 رفع شده (ابزار اضافه شد)
- **فاز**: 2
- **شرح**: فرم‌های بلند بدون ذخیره خودکار
- **Phase 2 نتیجه**: `AG.autoSaveForm(formId)` و `AG.restoreForm(formId)` در `assets/js/global.js` با localStorage
- **تاریخ رفع**: 2026-04-17 (Phase 2A)

### TD-UX-005: No Accessibility
- **شدت**: 🟡 متوسط
- **وضعیت**: ⏳ باز
- **فاز**: 3
- **شرح**: فاقد ARIA labels و semantic HTML
- **تخمین**: ۵ روز

---

## لاگ تغییرات (Resolution Log)

| تاریخ | شناسه | اقدام | commit |
|--------|--------|--------|--------|
| 2026-04-17 | Phase-0 | Removed info.php, localhost.sql.txt (gitignored) | phase-0 branch |
| 2026-04-17 | Phase-0 | Removed 37 copy/old/dead files | phase-0 branch |
| 2026-04-17 | Phase-0 | Removed 6 debug/test files | phase-0 branch |
| 2026-04-17 | Phase-0 | Moved log files to logs/ directory | phase-0 branch |
| 2026-04-17 | Phase-0 | Moved 3 hardcoded secrets to env vars | phase-0 branch |
| 2026-04-17 | Phase-0 | Disabled display_errors in 3 files | phase-0 branch |
| 2026-04-17 | TD-SEC-001 | Converted 30+ raw SQL queries to prepared statements | phase-1 branch |
| 2026-04-17 | TD-SEC-002 | Fixed XSS — htmlspecialchars on all $_GET/$_POST output | phase-1 branch |
| 2026-04-17 | TD-SEC-003 | Added CSRF protection to 41 POST API endpoints | phase-1 branch |
| 2026-04-17 | TD-SEC-004 | Added auth checks (isLoggedIn) to 50 API endpoints | phase-1 branch |
| 2026-04-17 | TD-SEC-005 | Created validateUpload() with extension + MIME check | phase-1 branch |
| 2026-04-17 | TD-SEC-006 | Added security headers via .htaccess (CSP, HSTS, etc.) | phase-1 branch |
| 2026-04-17 | TD-SEC-007 | Added HTTPS enforcement via .htaccess RewriteRule | phase-1 branch |
| 2026-04-17 | TD-SEC-008 | Created includes/validation.php — centralized input validation | phase-1 branch |
| 2026-04-17 | Phase-1 | Created sercon/bootstrap.php — central bootstrap | phase-1 branch |
| 2026-04-17 | Phase-1 | Centralized display_errors — removed from 9 per-file overrides | phase-1 branch |
| 2026-04-17 | Phase-1.5 | Added csrfField() to 55 HTML POST forms across 18 files | phase-1 branch |
| 2026-04-17 | Phase-1.5 | Added assets/js/csrf-injector.js — global CSRF on all AJAX (jQuery/fetch/XHR) | phase-1 branch |
| 2026-04-17 | Phase-1.5 | Added CSRF meta tag + injector script to all 10 header variants | phase-1 branch |
| 2026-04-17 | Phase-1.5 | Replaced ad-hoc auth with requireRole() on 23 admin/management endpoints | phase-1 branch |
| 2026-04-17 | Phase-1.5 | Auto-load includes/security.php from sercon/bootstrap.php | phase-1 branch |
| 2026-04-17 | Phase-2A | Created assets/css/design-system.css (CSS custom properties) | phase-2a branch |
| 2026-04-17 | Phase-2A | Created assets/css/global.css (shared .ag-* components) | phase-2a branch |
| 2026-04-17 | Phase-2A | Created assets/js/global.js (toast, loading, CSRF, form helpers) | phase-2a branch |
| 2026-04-17 | TD-ARCH-002 | 11 header files → 6 via ghom/header.php + pardis/header.php dispatchers | phase-2b branch |
| 2026-04-17 | Phase-2B | Rewrote 91 header require_once statements across 70+ files | phase-2b branch |
| 2026-04-17 | TD-PERF-002 | Extracted inline CSS/JS from 10 largest PHP files → 17 CSS + 6 JS externals | phase-2c branch |
| 2026-04-17 | TD-UX-001 | Converted 10 mobile-only pages to 301 redirects | phase-2d branch |
| 2026-04-17 | TD-PERF-003 | Created includes/pagination.php (paginate + renderPagination) | phase-2e branch |
| 2026-04-17 | TD-PERF-004 | Fixed N+1 queries in 4 files with batched IN (...) queries | phase-2e branch |

---

## بدهی‌های جدید — Phase 4

### TD-UX-002: Wizardise `pardis/daily_report_form_ps.php`
- **شدت**: 🟡 متوسط
- **وضعیت**: 🟠 باز (FormWizard component is ready — integration pending)
- **فاز**: 4C (postponed)
- **شرح**: The 1,599-line daily report form still runs as a single page. The new FormWizard component (`/assets/js/form-wizard.js`) is ready but the surgical integration (wrapping existing sections with `data-step` markers) was postponed because the form contains tightly coupled inline JS (`addMac()`, `collectArrayData()`, etc.) that needs careful testing against a running PHP environment before being split across wizard steps.
- **راهکار**: Follow the 7-step breakdown documented in `docs/examples/form-wizard.md`. Wrap each existing card block in `<div data-step="N" data-title="…">` without touching the inner markup; the wizard JS handles show/hide via CSS `hidden`. Test submit, draft-save, and file-attach flows end-to-end before merging.
- **تاریخ شناسایی**: 2026-04-18

### TD-UX-003: Wizardise `pardis/meeting_minutes_form.php`
- **شدت**: 🟡 متوسط
- **وضعیت**: 🟠 باز (FormWizard component is ready — integration pending)
- **فاز**: 4C (postponed)
- **شرح**: Same pattern as TD-UX-002; 1,970 lines, 4-step breakdown suggested.
- **راهکار**: See `docs/examples/form-wizard.md`.
- **تاریخ شناسایی**: 2026-04-18

---

*هر بدهی فنی جدید باید در این سند ثبت شود. هر رفع باید با commit hash مستند شود.*
