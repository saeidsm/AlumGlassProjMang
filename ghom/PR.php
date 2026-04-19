<?php
// /public_html/ghom/reports.php (FINAL VERSION)

// --- BOOTSTRAP & SESSION ---
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();
if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}
$user_role = $_SESSION['role'];
$has_full_access = in_array($user_role, ['admin', 'user', 'superuser']);
if (!$has_full_access && !in_array($user_role, ['cat', 'car', 'coa', 'crs'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}
$pageTitle = "پرزنت پروژه قم";
require_once __DIR__ . '/header.php';


?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سامانه یکپارچه مدیریت، بازرسی و هوش تجاری پروژه</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Vazirmatn', Arial, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            
            background: white;
            border-radius: 0;
            box-shadow: none;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

       .header-content {
    position: relative;
    z-index: 2;
    
    /* --- Add these three lines --- */
    display: flex;
    flex-direction: column;
    align-items: center;
}

        .main-title,
.subtitle {
    display: block;           /* ensure each is its own line */
    text-align: center;       /* optional: center align */
}

.main-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.3em;     /* smaller gap before subtitle */
    text-shadow: 0 2px 20px rgba(0,0,0,0.3);
    background: linear-gradient(45deg, #ffd700, #fff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.subtitle {
    font-size: 1.2rem;
    font-weight: 400;
    opacity: 0.9;
    margin: 0.2em 0;          /* consistent small vertical gaps */
}

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 30px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #ffd700;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-top: 5px;
        }

        .content {
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section {
            margin-bottom: 50px;
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 0.8s ease forwards;
        }

        .section:nth-child(2) { animation-delay: 0.2s; }
        .section:nth-child(3) { animation-delay: 0.4s; }
        .section:nth-child(4) { animation-delay: 0.6s; }
        .section:nth-child(5) { animation-delay: 0.8s; }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .section-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-left: 20px;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .section-subtitle {
            font-size: 1.2rem;
            color: #7f8c8d;
            font-weight: 400;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            margin-bottom: 25px;
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .feature-description {
            color: #5a6c7d;
            line-height: 1.8;
            font-size: 1rem;
        }

        .tech-stack {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 20px;
            padding: 40px;
            margin: 40px 0;
        }

        .tech-items {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .tech-item {
            background: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .tech-item:hover {
            transform: scale(1.05);
        }

        .tech-item i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }

        .security-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }

        .security-item {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            padding: 30px;
            border-radius: 15px;
            position: relative;
            overflow: hidden;
        }

        .security-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), transparent);
        }

        .security-item:nth-child(2n) {
            background: linear-gradient(135deg, #4ecdc4, #44a08d);
        }

        .security-item:nth-child(3n) {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .innovation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .innovation-card {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
            position: relative;
        }

        .innovation-number {
            position: absolute;
            top: -10px;
            right: 20px;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .dashboard-preview {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            border-radius: 20px;
            padding: 40px;
            margin: 40px 0;
            color: white;
            text-align: center;
        }

        .dashboard-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .dashboard-feature {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }

        .future-modules {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .module-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 40px;
            border-radius: 20px;
            position: relative;
            overflow: hidden;
        }

        .module-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(255,255,255,0.1), transparent);
            transform: translate(50%, -50%);
        }

        .conclusion {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            margin-top: 40px;
        }

        .conclusion-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .conclusion-text {
            font-size: 1.2rem;
            line-height: 2;
            max-width: 800px;
            margin: 0 auto 40px;
        }

        .cta-button {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #2c3e50;
            padding: 15px 40px;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255,215,0,0.4);
        }

        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .floating-element {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; }
        .floating-element:nth-child(2) { top: 20%; right: 10%; animation-delay: 2s; }
        .floating-element:nth-child(3) { bottom: 20%; left: 20%; animation-delay: 4s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        @media (max-width: 768px) {
            .main-title { font-size: 1.8rem; }
            .section-title { font-size: 1.5rem; }
            .cards-grid { grid-template-columns: 1fr; }
            .innovation-grid { grid-template-columns: 1fr; }
            .future-modules { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .content { padding: 20px; }
            .header { padding: 20px; }
            .conclusion { padding: 30px 20px; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="floating-elements">
        <i class="fas fa-hospital floating-element" style="font-size: 3rem;"></i>
        <i class="fas fa-chart-line floating-element" style="font-size: 2.5rem;"></i>
        <i class="fas fa-shield-alt floating-element" style="font-size: 2rem;"></i>
    </div>

    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <div class="header-content">
                <h1 class="main-title">سامانه یکپارچه مدیریت، بازرسی و هوش تجاری</h1>
                
                <p class="subtitle">پروژه بیمارستان هزار تختخوابی قم</p>
                <p class="subtitle">شفافیت، امنیت و تصمیم‌گیری مبتنی بر داده در بزرگترین پروژه‌های عمرانی</p>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number">1000</div>
                        <div class="stat-label">تخت بیمارستانی</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">امنیت داده‌ها</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">نظارت زنده</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">∞</div>
                        <div class="stat-label">مقیاس‌پذیری</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <!-- Challenge Section -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h2 class="section-title">چالش‌های بزرگ، راه‌حل هوشمند</h2>
                        <p class="section-subtitle">چرا یک سیستم جدید؟</p>
                    </div>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <h3 class="feature-title">چشم‌انداز دیجیتال</h3>
                    <p class="feature-description">
                        پروژه‌های عمرانی در مقیاس بزرگ، همواره با چالش‌های پیچیده‌ای در زمینه نظارت، کنترل کیفیت و هماهنگی بین تیم‌های مختلف روبرو هستند. ما یک اکوسیستم دیجیتال یکپارچه ایجاد کرده‌ایم که نه تنها این چالش‌ها را برطرف می‌کند، بلکه سطح جدیدی از دقت، امنیت و هوشمندی را در مدیریت پروژه تعریف می‌نماید.
                    </p>
                </div>
            </div>

            <!-- Architecture Section -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div>
                        <h2 class="section-title">معماری و تکنولوژی‌های زیرساخت</h2>
                        <p class="section-subtitle">این سیستم بر چه پایه‌ای ساخته شده است؟</p>
                    </div>
                </div>

                <div class="tech-stack">
                    <h3 style="text-align: center; font-size: 1.5rem; margin-bottom: 20px;">معماری ماژولار و مقیاس‌پذیر</h3>
                    <div class="tech-items">
                        <div class="tech-item">
                            <i class="fab fa-php"></i>
                            <h4>Backend PHP</h4>
                            <p>مدرن و امن</p>
                        </div>
                        <div class="tech-item">
                            <i class="fas fa-database"></i>
                            <h4>MySQL Database</h4>
                            <p>چند-دیتابیسی</p>
                        </div>
                        <div class="tech-item">
                            <i class="fab fa-js-square"></i>
                            <h4>JavaScript</h4>
                            <p>خالص و بهینه</p>
                        </div>
                        <div class="tech-item">
                            <i class="fab fa-python"></i>
                            <h4>Python Pipeline</h4>
                            <p>پردازش نقشه‌ها</p>
                        </div>
                    </div>
                </div>

                <div class="cards-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <h3 class="feature-title">معماری چند-دیتابیسی</h3>
                        <p class="feature-description">
                            یک دیتابیس مشترک برای مدیریت متمرکز کاربران و پروژه‌ها، همراه با دیتابیس‌های ایزوله برای هر پروژه که تضمین می‌کند داده‌های حساس کاملاً مجزا باقی بماند.
                        </p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-map"></i>
                        </div>
                        <h3 class="feature-title">پردازش هوشمند نقشه‌ها</h3>
                        <p class="feature-description">
                            خط لوله پردازش خودکار با پایتون که فایل‌های اتوکد را به فرمت بهینه SVG تبدیل کرده و به هر المان شناسه منحصر به فرد اختصاص می‌دهد.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Security Section -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h2 class="section-title">امنیت در سطح بانکی</h2>
                        <p class="section-subtitle">دفاع در عمق برای محافظت کامل</p>
                    </div>
                </div>

                <div class="security-grid">
                    <div class="security-item">
                        <h4><i class="fas fa-user-shield"></i> کنترل دسترسی RBAC</h4>
                        <p>ماتریس دسترسی دقیق با اصل حداقل دسترسی و اعتبارسنجی در سمت سرور</p>
                    </div>
                    <div class="security-item">
                        <h4><i class="fas fa-code"></i> امن‌سازی برنامه</h4>
                        <p>جلوگیری از SQL Injection، XSS و CSRF با استانداردهای امنیتی روز دنیا</p>
                    </div>
                    <div class="security-item">
                        <h4><i class="fas fa-key"></i> مدیریت جلسات امن</h4>
                        <p>هشینگ قوی رمزها، جلوگیری از Brute-Force و بازتولید Session ID</p>
                    </div>
                    <div class="security-item">
                        <h4><i class="fas fa-clipboard-list"></i> ثبت وقایع</h4>
                        <p>لاگ کامل تمام فعالیت‌ها برای حسابرسی و پیگیری امنیتی</p>
                    </div>
                </div>
            </div>

            <!-- Innovation Section -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div>
                        <h2 class="section-title">نوآوری‌های کلیدی</h2>
                        <p class="section-subtitle">فراتر از یک نرم‌افزار بازرسی</p>
                    </div>
                </div>

                <div class="innovation-grid">
                    <div class="innovation-card">
                        <div class="innovation-number">1</div>
                        <h3 class="feature-title">بازرسی ویژوال مبتنی بر نقشه</h3>
                        <p class="feature-description">
                            تبدیل فرآیند بازرسی از فرم‌های متنی به تجربه بصری. المان‌ها مستقیماً روی نقشه انتخاب شده و وضعیت آن‌ها به صورت رنگی نمایش داده می‌شود.
                        </p>
                    </div>

                    <div class="innovation-card">
                        <div class="innovation-number">2</div>
                        <h3 class="feature-title">موتور گردش کار هوشمند</h3>
                        <p class="feature-description">
                            مدیریت خودکار نوبت کاری بین مشاور و پیمانکار، اجرای خودکار قوانین کسب‌وکار و کنترل دسترسی بر اساس وضعیت پروژه.
                        </p>
                    </div>

                    <div class="innovation-card">
                        <div class="innovation-number">3</div>
                        <h3 class="feature-title">تجربه یکپارچه موبایل و دسکتاپ</h3>
                        <p class="feature-description">
                            PWA بهینه شده برای موبایل با قابلیت انتخاب گروهی و نسخه دسکتاپ کامل برای مدیریت پیشرفته و گزارش‌گیری تحلیلی.
                        </p>
                    </div>

                     <div class="innovation-card">
                        <div class="innovation-number">4</div>
                        <h3 class="feature-title">بارکد QR برای هر المان</h3>
                        <p class="feature-description">
                            هر المان دارای یک صفحه تاریخچه منحصر به فرد است که به یک بارکد QR متصل است و روی المان فیزیکی نصب می‌شود. هر فردی با اسکن بارکد می‌تواند فوراً به تمام سوابق بازرسی آن المان دسترسی پیدا کند.
                        </p>
                    </div>

                    <div class="innovation-card">
                        <div class="innovation-number">5</div>
                        <h3 class="feature-title">اعتبارسنجی با امضای دیجیتال</h3>
                        <p class="feature-description">
                            سیستم امضای دیجیتال مبتنی بر رمزنگاری RSA 2048-bit که یک مدرک دیجیتال غیرقابل انکار ایجاد می‌کند. این ویژگی اعتبار و قابلیت استناد گزارش‌های سیستم را در سطح حقوقی و قراردادی به شدت افزایش می‌دهد.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Business Intelligence Section -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <h2 class="section-title">داشبورد هوش تجاری</h2>
                        <p class="section-subtitle">تبدیل داده به دانش برای تصمیم‌گیری بهتر</p>
                    </div>
                </div>

                <div class="dashboard-preview">
                    <h3>گزارشات تحلیلی پیشرفته</h3>
                    <div class="dashboard-features">
                        <div class="dashboard-feature">
                            <i class="fas fa-tachometer-alt" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <h4>KPI های کلیدی</h4>
                            <p>نمایش لحظه‌ای وضعیت پروژه</p>
                        </div>
                        <div class="dashboard-feature">
                            <i class="fas fa-trending-up" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <h4>تحلیل روند</h4>
                            <p>نمودارهای پیشرفت روزانه تا ماهانه</p>
                        </div>
                        <div class="dashboard-feature">
                            <i class="fas fa-map-marked-alt" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <h4>گزارش پوشش</h4>
                            <p>درصد بازرسی به تفکیک زون</p>
                        </div>
                        <div class="dashboard-feature">
                            <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <h4>گزارش عملکرد</h4>
                            <p>مقایسه عملکرد تیم‌ها</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Future Vision Section -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div>
                        <h2 class="section-title">چشم‌انداز آینده</h2>
                        <p class="section-subtitle">توسعه یک پلتفرم جامع مدیریتی</p>
                    </div>
                </div>

                <div class="future-modules">
                    <div class="module-card">
                        <h3><i class="fas fa-envelope" style="margin-left: 10px;"></i>ماژول اتوماسیون اداری</h3>
                        <p>سیستم کامل ثبت، ارجاع و پیگیری نامه‌های اداری بین کارفرما، مشاور و پیمانکاران با قابلیت تعریف الگو، امضای دیجیتال و بایگانی خودکار.</p>
                    </div>

                    <div class="module-card">
                        <h3><i class="fas fa-calendar-day" style="margin-left: 10px;"></i>ماژول گزارشات روزانه</h3>
                        <p>سیستم یکپارچه دریافت گزارشات روزانه شامل نیروی انسانی، ماشین‌آلات، مصالح مصرفی و تحلیل هوشمند داده‌ها در داشبورد مدیریتی.</p>
                    </div>

                    <div class="module-card">
                        <h3><i class="fas fa-cube" style="margin-left: 10px;"></i>یکپارچه‌سازی BIM</h3>
                        <p>اتصال به مدل‌های BIM برای ایجاد دوقلوی دیجیتال که وضعیت هر المان در مدل سه‌بعدی به صورت زنده نمایش داده می‌شود.</p>
                    </div>
                </div>
            </div>

            <!-- ROI Section -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div>
                        <h2 class="section-title">بازدهی سرمایه‌گذاری</h2>
                        <p class="section-subtitle">ارزش افزوده قابل اندازه‌گیری</p>
                    </div>
                </div>

                <div class="cards-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="feature-title">صرفه‌جویی در زمان</h3>
                        <p class="feature-description">
                            کاهش ۷۰٪ زمان صرف شده برای گزارش‌گیری و نظارت از طریق اتوماسیون فرآیندها و حذف کارهای دستی و تکراری.
                        </p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <h3 class="feature-title">کاهش ریسک</h3>
                        <p class="feature-description">
                            شناسایی زودهنگام مشکلات کیفی و عدم انطباق با استانداردها که منجر به پیشگیری از هزینه‌های غیرضروری تعمیرات آینده می‌شود.
                        </p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h3 class="feature-title">شفافیت کامل</h3>
                        <p class="feature-description">
                            رهگیری لحظه‌ای پیشرفت پروژه و دسترسی آنی به تمام اطلاعات برای تصمیم‌گیری‌های مدیریتی بر اساس داده‌های واقعی.
                        </p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-expand-arrows-alt"></i>
                        </div>
                        <h3 class="feature-title">مقیاس‌پذیری</h3>
                        <p class="feature-description">
                            قابلیت توسعه سیستم برای پروژه‌های متعدد همزمان بدون افزایش هزینه‌های زیرساختی قابل توجه.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Implementation Timeline -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div>
                        <h2 class="section-title">مراحل پیاده‌سازی</h2>
                        <p class="section-subtitle">برنامه زمان‌بندی شده و قابل کنترل</p>
                    </div>
                </div>

                <div style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 20px; padding: 40px; margin-top: 30px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 30px;">
                        <div style="text-align: center; position: relative;">
                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #28a745, #20c997); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; margin: 0 auto 20px;">
                                <i class="fas fa-play"></i>
                            </div>
                            <h4 style="color: #2c3e50; margin-bottom: 10px;">فاز ۱: راه‌اندازی</h4>
                            <p style="color: #6c757d; font-size: 0.9rem;">نصب زیرساخت و آموزش تیم</p>
                            <div style="background: #28a745; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; margin-top: 10px;">۲-۳ ماه</div>
                        </div>

                        <div style="text-align: center; position: relative;">
                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #ffc107, #fd7e14); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; margin: 0 auto 20px;">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <h4 style="color: #2c3e50; margin-bottom: 10px;">فاز ۲: تنظیمات</h4>
                            <p style="color: #6c757d; font-size: 0.9rem;">پردازش نقشه‌ها و تعریف المان‌ها</p>
                            <div style="background: #ffc107; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; margin-top: 10px;">۱-۲ ماه</div>
                        </div>

                        <div style="text-align: center; position: relative;">
                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #17a2b8, #6f42c1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; margin: 0 auto 20px;">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4 style="color: #2c3e50; margin-bottom: 10px;">فاز ۳: آزمایش</h4>
                            <p style="color: #6c757d; font-size: 0.9rem;">تست و بازخورد کاربران</p>
                            <div style="background: #17a2b8; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; margin-top: 10px;">۱ ماه</div>
                        </div>

                        <div style="text-align: center; position: relative;">
                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #dc3545, #e83e8c); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; margin: 0 auto 20px;">
                                <i class="fas fa-rocket"></i>
                            </div>
                            <h4 style="color: #2c3e50; margin-bottom: 10px;">فاز ۴: راه‌اندازی کامل</h4>
                            <p style="color: #6c757d; font-size: 0.9rem;">شروع بهره‌برداری رسمی</p>
                            <div style="background: #dc3545; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; margin-top: 10px;">فوری</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Technical Support -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div>
                        <h2 class="section-title">پشتیبانی و خدمات</h2>
                        <p class="section-subtitle">همراهی کامل در تمام مراحل</p>
                    </div>
                </div>

                <div class="cards-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h3 class="feature-title">پشتیبانی ۲۴/۷</h3>
                        <p class="feature-description">
                            تیم پشتیبانی فنی مجرب آماده پاسخگویی به تمام سوالات و رفع مشکلات احتمالی در کمترین زمان ممکن.
                        </p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h3 class="feature-title">آموزش جامع</h3>
                        <p class="feature-description">
                            برگزاری دوره‌های آموزشی کاربردی برای تمام سطوح کاربران از مدیران ارشد تا بازرسان میدانی.
                        </p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <h3 class="feature-title">به‌روزرسانی مداوم</h3>
                        <p class="feature-description">
                            ارائه به‌روزرسانی‌های منظم سیستم شامل ویژگی‌های جدید و بهبود عملکرد بر اساس نیازهای پروژه.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conclusion -->
        <div class="conclusion">
            <h2 class="conclusion-title">سرمایه‌گذاری در آینده</h2>
            <p class="conclusion-text">
                این سامانه فراتر از یک ابزار نرم‌افزاری است؛ سرمایه‌گذاری استراتژیک در شفافیت، کنترل و امنیت محسوب می‌شود. 
                ما با ترکیب امنیت در سطح بانکی، گردش کار هوشمند و نوآوری در تجسم‌سازی داده‌ها، ابزاری ساخته‌ایم که 
                ریسک‌ها را کاهش داده، سرعت را افزایش می‌دهد و به مدیران قدرت تصمیم‌گیری مبتنی بر داده‌های واقعی را می‌بخشد.
            </p>
            <p class="conclusion-text">
                این پلتفرم نه تنها برای پروژه فعلی، بلکه به عنوان یک زیرساخت قابل توسعه برای تمام پروژه‌های آتی، 
                یک دارایی ارزشمند و بلندمدت خواهد بود.
            </p>
            <a href="#" class="cta-button" onclick="alert('آماده شروع همکاری هستیم!')">
                <i class="fas fa-handshake" style="margin-left: 10px;"></i>
                شروع همکاری
            </a>
        </div>
    </div>

    <script>
        // Smooth scroll animation for cards
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.feature-card, .innovation-card, .module-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });

        // Interactive stats counter animation
        function animateStats() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const text = stat.textContent;
                if (text === '∞') return;
                
                const target = parseInt(text) || 100;
                let current = 0;
                const increment = target / 50;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    stat.textContent = Math.floor(current) + (text.includes('%') ? '%' : '');
                }, 50);
            });
        }

        // Trigger stats animation when header comes into view
        const headerObserver = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                animateStats();
                headerObserver.disconnect();
            }
        });

        headerObserver.observe(document.querySelector('.header'));

        // Add interactive hover effects
        document.querySelectorAll('.feature-card, .innovation-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
                this.style.boxShadow = '0 25px 80px rgba(0,0,0,0.2)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.boxShadow = '0 10px 40px rgba(0,0,0,0.1)';
            });
        });

        // Parallax effect for floating elements
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const elements = document.querySelectorAll('.floating-element');
            
            elements.forEach((element, index) => {
                const speed = 0.5 + (index * 0.1);
                element.style.transform = `translateY(${scrolled * speed}px) rotate(${scrolled * 0.1}deg)`;
            });
        });
    </script>

     <?php require_once 'footer.php'; ?>
</body>
</html>