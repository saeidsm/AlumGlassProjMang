# معماری سیستم — AlumGlass Project Management
# System Architecture Document

> آخرین بروزرسانی: فروردین ۱۴۰۵ / April 2026  
> نسخه: 1.0.0

---

## 1. نمای کلی (Overview)

سیستم مدیریت پروژه‌های نمای ساختمان AlumGlass یک اپلیکیشن وب PHP-based است که برای مدیریت فرآیندهای مهندسی نما شامل بازرسی، کنترل کیفیت، گزارش‌دهی روزانه، مدیریت انبار و مکاتبات طراحی شده.

### Technology Stack

| لایه | فناوری |
|------|--------|
| Backend | PHP 8.4+ |
| Database | MariaDB 11.4 |
| Frontend | HTML5, CSS3, JavaScript (Vanilla + jQuery) |
| Server | Apache (cPanel Hosting) |
| PDF Generation | mPDF, TCPDF |
| Charts | Chart.js, ApexCharts |
| UI Framework | Bootstrap 5 |
| Notifications | Telegram Bot API |
| Font | Vazir (Persian) |

---

## 2. ساختار پروژه (Project Structure)

```
AlumGlassProjMang/
│
├── sercon/                      # Server Configuration (outside doc root)
│   └── bootstrap.php            # Central bootstrap — DB connections, session, constants
│
├── public_html/                 # Apache Document Root
│   ├── index.php                # Entry redirect → login
│   ├── login.php                # Authentication
│   ├── logout.php               # Session termination
│   ├── admin.php                # User management panel
│   ├── profile.php              # User profile editor
│   ├── select_project.php       # Project switcher
│   ├── messages.php             # Internal messaging system
│   ├── analytics.php            # Cross-project analytics
│   ├── .htaccess                # Security headers, compression, HTTPS enforcement
│   │
│   ├── api/                     # Shared API endpoints
│   │   ├── send_message.php
│   │   ├── get_new_messages.php
│   │   ├── edit_message.php
│   │   ├── delete_message.php
│   │   ├── mark_messages_read.php
│   │   └── save_signature.php
│   │
│   ├── includes/                # Shared PHP includes
│   │   ├── security.php         # CSRF, XSS helpers, auth middleware
│   │   ├── validation.php       # Input validation functions
│   │   ├── error_handler.php    # Centralized error handling
│   │   ├── env_loader.php       # .env file parser
│   │   └── pagination.php       # paginate() + renderPagination() (Phase 2E)
│   │
│   ├── assets/                  # Shared static assets
│   │   ├── css/
│   │   │   ├── design-system.css   # CSS custom properties (--ag-*) (Phase 2A)
│   │   │   ├── global.css          # .ag-card/.ag-btn/.ag-toast etc. (Phase 2A)
│   │   │   ├── messages.css        # Extracted from messages.php (Phase 2C)
│   │   │   └── admin.css           # Extracted from admin.php (Phase 2C)
│   │   ├── js/
│   │   │   ├── csrf-injector.js    # Global AJAX CSRF (Phase 1.5)
│   │   │   ├── global.js           # window.AG utilities (Phase 2A)
│   │   │   └── messages.js         # Extracted from messages.php (Phase 2C)
│   │   ├── fonts/
│   │   └── images/
│   │
│   ├── ghom/                    # === Ghom Project Module ===
│   │   ├── index.php            # Ghom dashboard
│   │   ├── header.php           # Responsive header dispatcher (Phase 2B)
│   │   ├── header_ghom.php      # Desktop implementation (loaded by dispatcher)
│   │   ├── header_ghom_mobile.php  # Mobile implementation (loaded by dispatcher)
│   │   ├── footer.php
│   │   ├── api/                 # Ghom-specific APIs
│   │   │   ├── save_inspection.php
│   │   │   ├── batch_update_status.php
│   │   │   ├── get_element_data.php
│   │   │   └── ...
│   │   ├── assets/              # Ghom-specific assets
│   │   ├── includes/            # Ghom-specific includes
│   │   ├── viewer.php           # Element/panel viewer
│   │   ├── reports.php          # Reporting dashboard
│   │   ├── inspection_dashboard.php
│   │   ├── daily_report_form.php
│   │   ├── daily_reports_dashboard.php
│   │   ├── my_calendar.php
│   │   ├── checklist_manager.php
│   │   ├── permit_dashboard.php
│   │   ├── qc_dashboard.php
│   │   ├── workshop_report.php
│   │   └── ...
│   │
│   └── pardis/                  # === Pardis Project Module ===
│       ├── index.php            # Pardis dashboard
│       ├── header.php                  # Responsive header dispatcher (Phase 2B)
│       ├── header_pardis.php           # Desktop implementation
│       ├── header_pardis_mobile.php    # Mobile implementation
│       ├── footer.php
│       ├── api/                 # Pardis-specific APIs
│       ├── assets/
│       ├── includes/            # mPDF, TCPDF libraries
│       ├── daily_reports.php
│       ├── daily_report_form_ps.php
│       ├── reports.php
│       ├── letters.php          # Letter/correspondence management
│       ├── meeting_minutes_form.php
│       ├── packing_list_viewer.php
│       ├── project_schedule.php
│       ├── warehouse_management.php
│       ├── zirsazi_status.php   # Substructure tracking
│       ├── analytics_buildings.php
│       └── ...
│
├── logs/                        # Application logs (outside doc root)
└── backups/                     # DB backups (outside doc root)
```

---

## 3. معماری نرم‌افزار (Software Architecture)

### 3.1 الگوی فعلی

سیستم فعلاً از الگوی **Page Controller** استفاده می‌کند: هر فایل PHP یک صفحه مستقل است که هم منطق و هم نمایش را شامل می‌شود.

```
[Browser] → [Apache] → [PHP File] → [Database]
                            ↕
                    [HTML + CSS + JS Response]
```

### 3.2 الگوی هدف (Target Architecture)

هدف مهاجرت تدریجی به ساختار لایه‌ای:

```
[Browser] → [Apache] → [Router/Controller] → [Service Layer] → [Data Access] → [Database]
                              ↕
                        [Template Engine]
```

### 3.3 Shared Library (Phase 3)

با تکمیل Phase 3 ساختار زیر اضافه شد:

```
shared/
├── api/                      # 32 endpoint مشترک بین ghom و pardis
│   ├── get_stages.php        # از getProjectDB() استفاده می‌کند
│   ├── save_template.php     # پروژه را از session/URL تشخیص می‌دهد
│   └── ... (32 files)
├── includes/
│   ├── project_context.php   # getCurrentProject() + getProjectDB()
│   └── jdf.php               # Jalali date library (منبع واحد)
└── repositories/             # Data access layer
    ├── ElementRepository.php
    ├── InspectionRepository.php
    ├── DailyReportRepository.php
    └── UserRepository.php
```

**Project Context Resolver** — هر درخواست به یک endpoint مشترک پروژه جاری را به این ترتیب تشخیص می‌دهد:
1. `$_SESSION['current_project']` (تنظیم در project selector)
2. بخش URL (`/ghom/api/...` → `ghom`)
3. پارامتر GET/POST `project=...`
4. در غیر این صورت: `RuntimeException`

**Backward Compatibility** — مسیرهای قبلی (`ghom/api/get_stages.php`, `pardis/api/get_stages.php`) دست‌نخورده باقی مانده‌اند و در قالب shim یک‌خطی به `shared/api/` منتقل می‌شوند.

**Repository Factory** — `getRepository('UserRepository')` در `sercon/bootstrap.php` یک singleton lazy-load می‌کند. مخازن پروژه‌ای به صورت خودکار به دیتابیس پروژه جاری متصل می‌شوند.

### 3.4 Testing & CI/CD (Phase 3)

- **PHPUnit 10.5**: `phpunit.xml` + `tests/bootstrap.php` مستقل (بدون DB واقعی). ۵۲ تست در ۵ کلاس.
- **PHPStan level 3**: `phpstan.neon` روی `shared/` و `includes/`.
- **PHP_CodeSniffer PSR-12**: `phpcs.xml`.
- **GitHub Actions**: سه job — lint / test / security gates. فایل: `.github/workflows/ci.yml`.

### 3.5 Migration Script (Phase 3)

`scripts/migrate.sh` یک ابزار bash برای مهاجرت از استقرار قدیمی (ZIP-based) به استقرار جدید (Git-based) است:

1. گرفتن credentials مبدأ و مقصد
2. `mysqldump` هر سه دیتابیس از طریق SSH
3. `rsync` فایل‌های آپلود شده
4. `CREATE DATABASE` + import در مقصد
5. تولید `.env` با permission 0600
6. تنظیم ownership و permissions
7. نصب crontab (حفظ entry های دیگر)
8. اجرای `scripts/migrate_verify.php`

---

## 4. پایگاه داده (Database Architecture)

### 4.1 دیتابیس‌ها

| دیتابیس | شرح | کاربرد |
|---------|------|--------|
| `alumglas_common` | جداول مشترک | users, login_attempts, activity_log |
| `alumglas_hpc` | پروژه قم | elements, inspections, permits, daily_reports |
| `alumglas_pardis` | پروژه پردیس | ps_daily_reports, letters, packing_lists |

### 4.2 جداول اصلی

**Common Database:**
- `users` — کاربران سیستم (id, username, password_hash, role, is_active)
- `login_attempts` — تلاش‌های ناموفق ورود (brute-force protection)
- `activity_log` — لاگ فعالیت‌ها

**Ghom Database (alumglas_hpc):**
- `elements` — المان‌های نما (panel_id, element_id, type, status, contractor)
- `inspections` — بازرسی‌ها (element_id, stage_id, status, inspector)
- `permits` — مجوزها (type, status, contractor_name)
- `daily_reports` — گزارش‌های روزانه
- `app_settings` — تنظیمات اپلیکیشن

**Pardis Database (alumglas_pardis):**
- `ps_daily_reports` — گزارش‌های روزانه پردیس
- `ps_daily_report_personnel` — پرسنل گزارش
- `ps_daily_report_machinery` — ماشین‌آلات
- `ps_daily_report_materials` — مصالح
- `ps_daily_report_activities` — فعالیت‌ها
- `letters` — مکاتبات
- `packing_lists` — لیست بسته‌بندی
- `meeting_minutes` — صورتجلسات

---

## 5. احراز هویت و مجوزها (Authentication & Authorization)

### 5.1 Authentication Flow

```
login.php → CSRF check → Rate limit check → Query users → password_verify()
    → Session regeneration → Set session vars → Redirect to select_project.php
```

### 5.2 نقش‌ها (Roles)

| نقش | شرح | دسترسی |
|-----|------|--------|
| `admin` | مدیر سیستم | تمام بخش‌ها |
| `superuser` | مدیر پروژه | مدیریت + گزارش |
| `cat` | ناظر | مشاهده + بازرسی |
| `car` | پیمانکار | گزارش + به‌روزرسانی وضعیت |
| `coa` | مشاور | مشاهده + تأیید |
| `crs` | دبیرخانه | مکاتبات + مجوزها |

### 5.3 Session Management

- Session lifetime: configurable via `SESSION_LIFETIME`
- Session regeneration on login
- Activity-based timeout
- Secure cookie flags (HttpOnly, SameSite)

---

## 6. ماژول‌ها (Modules)

### 6.1 بازرسی (Inspection)
مدیریت بازرسی المان‌های نما در مراحل مختلف ساخت و نصب.

### 6.2 گزارش روزانه (Daily Reports)
ثبت گزارش‌های روزانه شامل پرسنل، ماشین‌آلات، مصالح، فعالیت‌ها و عکس.

### 6.3 مکاتبات (Letters)
سیستم نامه‌نگاری بین شرکت‌ها با قابلیت جستجو و فیلتر.

### 6.4 تقویم (Calendar)
تقویم شمسی با قابلیت ثبت رویداد و یادآوری.

### 6.5 پیام‌رسانی (Messaging)
چت داخلی بین کاربران سیستم.

### 6.6 انبار (Warehouse)
مدیریت ورود و خروج مصالح و موجودی.

### 6.7 تلگرام (Telegram Bot)
ارسال خودکار گزارش‌ها و یادآورها از طریق ربات تلگرام.

### 6.8 نمایشگر سه‌بعدی (3D Viewer)
نمایش مدل‌های GLTF المان‌های نما.

---

## 7. یکپارچه‌سازی‌ها (Integrations)

| سرویس | هدف | فایل |
|--------|------|------|
| Telegram Bot API | ارسال گزارش و نوتیفیکیشن | telegram_webhook.php, telegram_cron.php |
| Weather API | نمایش آب‌وهوا در داشبورد | weather_api.php |
| mPDF / TCPDF | تولید PDF گزارش‌ها | pardis/includes/ |

---

## 8. امنیت (Security Measures)

- ✅ CSRF token on login
- ✅ Password hashing (bcrypt)
- ✅ Brute-force protection (login_attempts)
- ✅ Session regeneration
- ✅ Prepared statements (all queries — Phase 1)
- ✅ XSS prevention — htmlspecialchars on all user output (Phase 1)
- ✅ CSRF on all POST endpoints — via includes/security.php (Phase 1)
- ✅ Auth checks on all API endpoints — isLoggedIn() + requireRole() (Phase 1)
- ✅ Security headers — CSP, HSTS, X-Frame-Options, X-Content-Type-Options (Phase 1)
- ✅ HTTPS enforcement — via .htaccess redirect (Phase 1)
- ✅ File upload validation — extension + MIME type check (Phase 1)
- ✅ Centralized error handling — display_errors=Off, JSON logging (Phase 1)
- ✅ Input validation layer — includes/validation.php (Phase 1)

---

## 9. Security Middleware (Phase 1)

| فایل | هدف |
|------|------|
| `sercon/bootstrap.php` | بوت‌استرپ مرکزی — DB, session, auth, logging, output helpers |
| `includes/security.php` | CSRF token generation/verification, file upload validation |
| `includes/validation.php` | Input validation (int, string, date, email, array check) |
| `includes/error_handler.php` | Global error/exception handlers — log to JSON, friendly output |
| `.htaccess` | Security headers, HTTPS enforcement, compression, caching |

### Authentication Flow (Updated)

```
API Request → bootstrap.php → secureSession() → isLoggedIn()
    → requireCsrf() (POST only) → Business Logic → Response
```

### CSRF Token Flow

```
Form Page: generateCsrfToken() → <input hidden> or <meta> tag
AJAX POST: X-CSRF-TOKEN header or csrf_token POST field
API: verifyCsrfToken() → validates hash_equals against session
```

---

## 10. Phase 4 Additions (2026-04-18)

### 10.1 Real-time Chat (`/chat/`)

```
Browser (chat-app.js)
    ↓ HTTPS fetch                            ↓ WebSocket
/chat/api/… (PHP + PDO)               ws://host:8080/?token=<PHPSESSID>
    ↓                                         ↓
MariaDB (conversations,               Node.js relay (websocket/)
messages, members,                    - auths via verify_session.php (loopback)
user_presence)                        - relays: chat_message, typing, read_receipt
                                      - tracks per-user presence
```

The PHP API persists and authorizes; Node.js only relays.

### 10.2 Content-Addressable File Storage

Every upload is deduplicated by SHA-256 hash.

```
POST /chat/api/upload.php  (or messages.php with file)
  → FileService::store()
    → hash = sha256(content)
    → existing? ref_count++  :  move to /storage/<h[0:2]>/<h[2:4]>/<h>.ext
    → file_references row (module, entity_type, entity_id, uploaded_by)

GET  /storage/<sharded-path>
  → storage/serve.php  (auth + path-traversal guard)
  → Cache-Control: public, max-age=31536000, immutable
```

Tables: `file_store` (hash unique, ref_count), `file_references`.
Nightly `scripts/cleanup_files.php` removes orphans.

### 10.3 Frontend Module Architecture (`/chat/assets/js/`, ES6)

- `chat-socket.js` — WebSocket client: reconnect with exp-backoff, pings, event emitter
- `chat-ui.js` — pure render helpers (no state)
- `chat-search.js` — debounced LIKE search with AbortController
- `chat-notifications.js` — browser Notifications API wrapper
- `chat-app.js` — controller (state, optimistic UI, URL sync)

### 10.4 PWA

- `manifest.webmanifest` — installable PWA (RTL, Persian locale)
- `service-worker.js` — versioned caches, strategies:
  - Navigation: network-first + `offline.html` fallback
  - Static assets: stale-while-revalidate
  - `/storage/` (content-addressable): cache-first immutable
  - `/chat/api/` + `/api/`: network-only (never cached)

### 10.5 Design System Extensions

- `assets/css/dark-mode.css` — `[data-theme="dark"]` overrides all tokens
- `assets/js/theme-toggle.js` — pre-paint theme apply + nav button toggle
- `includes/breadcrumbs.php` — with Schema.org BreadcrumbList JSON-LD
- `includes/empty_state.php` — reusable component with 6 icon variants
- `assets/js/form-wizard.js` — multi-step form wrapper with localStorage auto-save

---

*This document is updated as the architecture evolves. Last audit: Phase 1 completion (2026-04-17).*
