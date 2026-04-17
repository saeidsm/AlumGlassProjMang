# بدهی فنی — AlumGlass Project Management
# Technical Debt Registry

> آخرین بروزرسانی: فروردین ۱۴۰۵ / April 2026  
> نسخه: 1.0.0

---

## وضعیت کلی (Summary)

| وضعیت | تعداد | شرح |
|--------|-------|------|
| 🔴 باز — بحرانی | 0 | — |
| 🟠 باز — بالا | 6 | Phase 2 برنامه‌ریزی شده |
| 🟡 باز — متوسط | 11 | برنامه‌ریزی شده |
| 🟢 رفع شده | 13 | تکمیل شده (Phase 0 + Phase 1) |

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
- **وضعیت**: 🟢 رفع شده
- **فاز**: 1
- **شرح**: ۴۱ endpoint در `ghom/api/` و `pardis/api/` فاقد CSRF
- **راهکار**: `requireCsrf()` middleware در `includes/security.php`
- **تاریخ شناسایی**: 1405/01/27
- **تاریخ رفع**: 1405/01/28
- **commit**: phase-1 branch — 41 POST API files

### TD-SEC-004: Missing Authorization on APIs
- **شدت**: 🔴 بحرانی
- **وضعیت**: 🟢 رفع شده
- **فاز**: 1
- **شرح**: ۵۰ API فاقد بررسی auth مناسب
- **راهکار**: `requireLogin()` + `requireRole()` در `sercon/bootstrap.php`
- **تاریخ شناسایی**: 1405/01/27
- **تاریخ رفع**: 1405/01/28
- **commit**: phase-1 branch — 50 API files

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
- **وضعیت**: ⏳ باز
- **فاز**: 2
- **شرح**: ۳۹ فایل بالای 50KB — HTML+CSS+JS+PHP مخلوط
- **تأثیر**: load time بالا، عدم cache مرورگر
- **تخمین**: ۱۰-۱۵ روز

### TD-PERF-002: 297 Inline CSS/JS Blocks
- **شدت**: 🟠 بالا
- **وضعیت**: ⏳ باز
- **فاز**: 2
- **شرح**: ۱۴۷ بلوک `<style>` و ۱۵۰ بلوک `<script>` inline
- **تأثیر**: cache مرورگر غیرممکن
- **تخمین**: ۵-۷ روز

### TD-PERF-003: No Pagination
- **شدت**: 🟠 بالا
- **وضعیت**: ⏳ باز
- **فاز**: 1
- **شرح**: داشبوردها تمام رکوردها را یکجا بارگذاری
- **تأثیر**: کندی شدید با رشد داده
- **تخمین**: ۳ روز

### TD-PERF-004: N+1 Queries
- **شدت**: 🟠 بالا
- **وضعیت**: ⏳ باز
- **فاز**: 2
- **شرح**: کوئری در حلقه — هر ردیف یک کوئری جدا
- **تخمین**: ۲ روز

### TD-PERF-005: No Compression
- **شدت**: 🟡 متوسط
- **وضعیت**: ⏳ باز
- **فاز**: 2
- **شرح**: Gzip/Brotli فعال نیست
- **تخمین**: ۰.۵ روز

---

## بدهی‌های معماری (Architecture Debt)

### TD-ARCH-001: No MVC / Service Layer
- **شدت**: 🟠 بالا
- **وضعیت**: ⏳ باز
- **فاز**: 3
- **شرح**: منطق، نمایش و دسترسی داده همه در یک فایل
- **تخمین**: ۲۰-۳۰ روز (تدریجی)

### TD-ARCH-002: 15 Duplicate Headers
- **شدت**: 🟠 بالا
- **وضعیت**: ⏳ باز
- **فاز**: 2
- **شرح**: هر ماژول چندین نسخه header دارد
- **تخمین**: ۳-۵ روز

### TD-ARCH-003: No Shared Library
- **شدت**: 🟠 بالا
- **وضعیت**: ⏳ باز
- **فاز**: 2
- **شرح**: کد مشترک بین ghom و pardis تکرار شده
- **تخمین**: ۵-۷ روز

### TD-ARCH-004: No Automated Tests
- **شدت**: 🟡 متوسط
- **وضعیت**: ⏳ باز
- **فاز**: 3
- **شرح**: هیچ Unit/Integration test نوشته نشده
- **تخمین**: ۱۰ روز (برای critical paths)

### TD-ARCH-005: No CI/CD
- **شدت**: 🟡 متوسط
- **وضعیت**: ⏳ باز
- **فاز**: 3
- **شرح**: deploy دستی بدون pipeline
- **تخمین**: ۲ روز

### TD-ARCH-006: No Coding Standards
- **شدت**: 🟡 متوسط
- **وضعیت**: ⏳ باز
- **فاز**: 3
- **شرح**: نام‌گذاری ناسازگار، بدون PSR
- **تخمین**: ۳ روز (setup + initial cleanup)

---

## بدهی‌های UX (UX Debt)

### TD-UX-001: Separate Mobile Pages
- **شدت**: 🟠 بالا
- **وضعیت**: ⏳ باز
- **فاز**: 2
- **شرح**: نسخه‌های جداگانه Desktop/Mobile به جای Responsive
- **تخمین**: ۷-۱۰ روز

### TD-UX-002: No Loading States
- **شدت**: 🟡 متوسط
- **وضعیت**: ⏳ باز
- **فاز**: 2
- **شرح**: عملیات AJAX بدون نشانگر بارگذاری
- **تخمین**: ۲ روز

### TD-UX-003: No Form Auto-save
- **شدت**: 🟡 متوسط
- **وضعیت**: ⏳ باز
- **فاز**: 2
- **شرح**: فرم‌های بلند بدون ذخیره خودکار
- **تخمین**: ۲ روز

### TD-UX-004: No Accessibility
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

---

*هر بدهی فنی جدید باید در این سند ثبت شود. هر رفع باید با commit hash مستند شود.*
