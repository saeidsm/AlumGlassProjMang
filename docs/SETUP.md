# راهنمای نصب و راه‌اندازی — AlumGlass Project Management
# Installation & Setup Guide

> آخرین بروزرسانی: فروردین ۱۴۰۵ / April 2026  
> نسخه: 1.0.0

---

## پیش‌نیازها (Prerequisites)

| نرم‌افزار | نسخه حداقل | توضیحات |
|-----------|-----------|---------|
| PHP | 8.1+ (ترجیحاً 8.4) | با افزونه‌های: pdo_mysql, mbstring, gd, curl, zip, xml |
| MariaDB / MySQL | 10.6+ / 8.0+ | با پشتیبانی UTF-8 (utf8mb4) |
| Apache | 2.4+ | با mod_rewrite, mod_headers, mod_deflate فعال |
| Git | 2.30+ | برای version control |
| Composer | 2.x | (اختیاری — برای مدیریت وابستگی‌ها در آینده) |

### افزونه‌های PHP مورد نیاز:
```bash
# بررسی افزونه‌ها:
php -m | grep -E "pdo_mysql|mbstring|gd|curl|zip|xml|json"

# نصب در Ubuntu/Debian:
sudo apt install php8.4-mysql php8.4-mbstring php8.4-gd php8.4-curl php8.4-zip php8.4-xml
```

---

## نصب سریع (Quick Start)

### ۱. کلون ریپازیتوری

```bash
git clone https://github.com/YOUR_USERNAME/AlumGlassProjMang.git
cd AlumGlassProjMang
```

### ۲. تنظیم Environment

```bash
cp .env.example .env
nano .env   # مقادیر واقعی را وارد کنید
```

فایل `.env` باید شامل این مقادیر باشد:

```env
DB_HOST=localhost
DB_PORT=3306
DB_COMMON_NAME=alumglas_common
DB_GHOM_NAME=alumglas_hpc
DB_PARDIS_NAME=alumglas_pardis
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CRON_SECRET=your_random_secret_key

WEATHER_API_KEY=your_api_key

APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

### ۳. ساخت دیتابیس

```sql
-- ایجاد دیتابیس‌ها
CREATE DATABASE IF NOT EXISTS alumglas_common CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS alumglas_hpc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS alumglas_pardis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ایجاد کاربر
CREATE USER 'alumglass_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON alumglas_common.* TO 'alumglass_user'@'localhost';
GRANT ALL PRIVILEGES ON alumglas_hpc.* TO 'alumglass_user'@'localhost';
GRANT ALL PRIVILEGES ON alumglas_pardis.* TO 'alumglass_user'@'localhost';
FLUSH PRIVILEGES;
```

سپس schema را import کنید:
```bash
mysql -u alumglass_user -p alumglas_common < sql/schema_common.sql
mysql -u alumglass_user -p alumglas_hpc < sql/schema_ghom.sql
mysql -u alumglass_user -p alumglas_pardis < sql/schema_pardis.sql
```

### ۴. تنظیم Apache

```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /path/to/AlumGlassProjMang/public_html

    <Directory /path/to/AlumGlassProjMang/public_html>
        AllowOverride All
        Require all granted
    </Directory>

    # SSL
    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem

    # Logging
    ErrorLog /path/to/AlumGlassProjMang/logs/apache_error.log
    CustomLog /path/to/AlumGlassProjMang/logs/apache_access.log combined
</VirtualHost>

# HTTP → HTTPS redirect
<VirtualHost *:80>
    ServerName your-domain.com
    Redirect permanent / https://your-domain.com/
</VirtualHost>
```

### ۵. تنظیم مجوزهای فایل

```bash
# مالکیت
sudo chown -R www-data:www-data /path/to/AlumGlassProjMang/

# مجوزها
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# دایرکتوری‌های قابل نوشتن
chmod 775 logs/
chmod 775 public_html/ghom/uploads/
chmod 775 public_html/pardis/uploads/

# محافظت از .env
chmod 600 .env
```

### ۶. بررسی نهایی

```bash
# تست PHP
php -r "echo 'PHP OK: ' . phpversion() . PHP_EOL;"

# تست اتصال دیتابیس
php -r "
    \$env = parse_ini_file('.env');
    try {
        \$pdo = new PDO(
            'mysql:host='.\$env['DB_HOST'].';dbname='.\$env['DB_COMMON_NAME'],
            \$env['DB_USERNAME'],
            \$env['DB_PASSWORD']
        );
        echo 'Database OK' . PHP_EOL;
    } catch (Exception \$e) {
        echo 'Database FAILED: ' . \$e->getMessage() . PHP_EOL;
    }
"

# تست Apache
curl -I https://your-domain.com/login.php
```

---

## استقرار در cPanel (cPanel Deployment)

اگر از cPanel استفاده می‌کنید:

### ۱. ساختار فایل‌ها

```
/home/username/
├── sercon/                    # خارج از document root
│   └── bootstrap.php
├── .env                       # خارج از document root
├── public_html/               # Document root (cPanel default)
│   ├── .htaccess
│   ├── index.php
│   ├── login.php
│   ├── ghom/
│   ├── pardis/
│   └── ...
├── logs/                      # خارج از document root
└── backups/                   # خارج از document root
```

### ۲. انتقال فایل‌ها

```bash
# از طریق Git (توصیه شده)
cd /home/username
git clone https://github.com/YOUR_USERNAME/AlumGlassProjMang.git temp_clone
cp -r temp_clone/public_html/* public_html/
cp -r temp_clone/sercon ./
cp temp_clone/.env.example .env
nano .env

# یا از طریق File Manager cPanel
# ZIP فایل را آپلود و extract کنید
```

### ۳. تنظیم .htaccess

فایل `.htaccess` در `public_html/` باید شامل این باشد:

```apache
# Force HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Security Headers
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

# Block sensitive files
<FilesMatch "\.(env|sql|log|md|gitignore)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Block directory listing
Options -Indexes

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json
</IfModule>

# Caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType font/woff2 "access plus 1 year"
</IfModule>
```

### ۴. Cron Jobs (cPanel)

در بخش Cron Jobs در cPanel:

```bash
# یادآور روزانه تلگرام — هر روز ساعت ۸ صبح
0 8 * * * /usr/local/bin/php /home/username/public_html/pardis/send_daily_reminders.php

# گزارش تلگرام — هر روز ساعت ۱۸
0 18 * * * /usr/local/bin/php /home/username/public_html/pardis/telegram_cron.php
```

---

## بروزرسانی (Update Process)

```bash
# 1. پشتیبان‌گیری
mysqldump -u user -p alumglas_hpc > backups/hpc_$(date +%Y%m%d).sql
mysqldump -u user -p alumglas_pardis > backups/pardis_$(date +%Y%m%d).sql

# 2. دریافت تغییرات
cd /path/to/project
git fetch origin
git pull origin main

# 3. بررسی .env.example برای متغیرهای جدید
diff .env .env.example

# 4. اعمال مهاجرت‌های دیتابیس (در صورت وجود)
# mysql -u user -p alumglas_hpc < sql/migrations/YYYYMMDD_description.sql

# 5. پاکسازی cache (در صورت استفاده)
# php scripts/clear_cache.php
```

---

## عیب‌یابی (Troubleshooting)

### خطای "Access Denied"
- بررسی مقادیر `.env` — نام کاربری و رمز دیتابیس
- بررسی مجوزهای MySQL: `SHOW GRANTS FOR 'user'@'localhost';`

### صفحه سفید (Blank Page)
- بررسی لاگ PHP: `tail -f /var/log/php_errors.log`
- بررسی لاگ Apache: `tail -f logs/apache_error.log`
- موقتاً debug فعال: `APP_DEBUG=true` در `.env`

### مشکل فونت فارسی
- بررسی وجود فایل‌های فونت در `assets/fonts/`
- بررسی مسیر فونت در CSS

### مشکل تلگرام
- بررسی `TELEGRAM_BOT_TOKEN` در `.env`
- تست: `curl https://api.telegram.org/bot{TOKEN}/getMe`
- بررسی Webhook: `curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo`

---

## ارتباط (Contact)

برای مشکلات فنی، Issue در GitHub ایجاد کنید.

---

*این سند با هر تغییر در فرآیند نصب بروزرسانی می‌شود.*
