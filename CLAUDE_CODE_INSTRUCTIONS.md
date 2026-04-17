# دستورالعمل اجرایی Claude Code
# Claude Code Execution Instructions

## 🎯 ماموریت (Mission)

تو یک مهندس نرم‌افزار ارشد هستی که مسئول بازسازی و بهبود امنیتی، کارایی و کیفیت کد پروژه AlumGlass هستی.
این پروژه یک داشبورد مدیریت پروژه‌های مهندسی نمای ساختمان (PHP/MySQL) است.

---

## 📋 فرآیند کار (Workflow)

### قبل از شروع هر فاز:
1. فایل `CLAUDE.md` را بخوان — این راهنمای اصلی توست
2. فایل `docs/TECH_DEBT.md` را بخوان — وضعیت فعلی بدهی‌ها
3. فایل `docs/CHANGELOG.md` را بخوان — آخرین تغییرات

### در حین کار:
1. هر تغییر را با commit message مناسب ثبت کن (فرمت در CLAUDE.md)
2. برای هر فاز یک branch جدا بساز: `phase-0/emergency-fixes`
3. بعد از تکمیل فاز، Pull Request بزن به `main`
4. تست‌های تأیید (verification) هر فاز را اجرا کن

### بعد از تکمیل هر فاز:
1. `docs/TECH_DEBT.md` — بدهی‌های رفع‌شده را بروزرسانی کن
2. `docs/CHANGELOG.md` — تغییرات را مستند کن
3. `docs/ARCHITECTURE.md` — اگر ساختار تغییر کرد، بروزرسانی کن
4. یک گزارش خلاصه بده

---

## 🚀 مراحل اجرا

### مرحله ۱: اتصال به GitHub و ایجاد ریپو

```bash
# 1. با توکن GitHub احراز هویت کن
gh auth login --with-token < token.txt
# یا
export GITHUB_TOKEN="ghp_your_token_here"

# 2. ریپو بساز
gh repo create AlumGlassProjMang --private \
  --description "AlumGlass Engineering Facade Project Management Dashboard"

# 3. کلون کن
git clone https://github.com/USERNAME/AlumGlassProjMang.git
cd AlumGlassProjMang
```

### مرحله ۲: فایل zip را extract و تمیز کن

```bash
# 1. Extract
unzip Alumglass-ir.zip -d temp
cp -r temp/Alumglass-ir/* ./
rm -rf temp

# 2. فایل‌های framework را کپی کن
# CLAUDE.md, .gitignore, .env.example, docs/* 
# (از فایل‌هایی که ساختیم)

# 3. فایل‌های خطرناک و بلااستفاده را حذف کن
# لیست کامل در CLAUDE.md → Phase 0 موجود است
```

### مرحله ۳: فاز ۰ — رفع اورژانسی

چک‌لیست Phase 0 در `CLAUDE.md` را قدم به قدم اجرا کن.
بعد از هر گروه تغییر، commit بزن.

**ترتیب commitها:**
```
chore(global): add .gitignore and .env.example
security(global): remove info.php and database dump
chore(global): remove 34 dead copy/old files
chore(global): remove debug/test files and exposed logs
security(global): move telegram token to environment variable
security(global): move cron secret to environment variable
docs: add ARCHITECTURE.md, TECH_DEBT.md, SETUP.md, CHANGELOG.md
```

### مرحله ۴: فاز ۱ — تحکیم امنیتی

```bash
git checkout -b phase-1/security-hardening
```

ترتیب اجرا:
1. **ابتدا** `includes/security.php` و `includes/validation.php` بساز
2. **سپس** SQL Injection‌ها را فایل به فایل رفع کن (هر فایل = ۱ commit)
3. **سپس** XSS‌ها را رفع کن
4. **سپس** CSRF middleware اضافه کن
5. **سپس** Auth middleware اضافه کن
6. **سپس** Security headers و HTTPS
7. **در آخر** verification اجرا کن

### مرحله ۵: فاز ۲ و ۳

مشابه بالا — جزئیات در `CLAUDE.md`.

---

## ⚠️ قوانین مهم

1. **هرگز `.env` را commit نکن** — فقط `.env.example`
2. **هرگز فایل SQL/dump را commit نکن**
3. **قبل از هر تغییر، فایل فعلی را بخوان** — مطمئن شو متن درست جایگزین می‌شود
4. **تست PHP syntax** بعد از هر تغییر: `php -l filename.php`
5. **هر commit باید atomic باشد** — یک نوع تغییر در هر commit
6. **اگر مطمئن نیستی، بپرس** — قبل از حذف کدی که ممکن است مورد استفاده باشد

---

## 📊 گزارش نهایی

بعد از تکمیل هر فاز، این گزارش را تولید کن:

```markdown
## گزارش فاز [X]

### خلاصه
- تعداد فایل‌های تغییریافته: N
- تعداد commit‌ها: N
- بدهی‌های رفع شده: N
- بدهی‌های باقیمانده: N

### تغییرات کلیدی
1. ...
2. ...

### مسائل یافت‌شده حین کار
1. ...

### مراحل بعدی
1. ...
```
