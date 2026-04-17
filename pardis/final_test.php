<?php
// public_html/pardis/final_test.php
// Final test with real bot token

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/TelegramBot.php';

header('Content-Type: text/html; charset=utf-8');

secureSession();

if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['admin', 'superuser'])) {
    die('Access Denied');
}

$telegramConfig = require __DIR__ . '/telegram_config.php';
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telegram = new TelegramBot($telegramConfig);
    
    if (empty($telegramConfig['chat_ids'])) {
        $error = 'هیچ Chat ID تنظیم نشده است!';
    } else {
        $chatId = $telegramConfig['chat_ids'][0];
        $testMessage = "✅ <b>تست موفق!</b>\n\n";
        $testMessage .= "🎉 اتصال به تلگرام از طریق Cloudflare Worker موفقیت‌آمیز بود!\n\n";
        $testMessage .= "⏰ زمان: " . date('Y-m-d H:i:s') . "\n";
        $testMessage .= "🌐 Proxy: pt.sabaat.ir\n";
        $testMessage .= "🚀 سیستم گزارش‌دهی آماده است!";
        
        $result = $telegram->sendMessage($chatId, $testMessage, 'HTML');
        
        if (!$result) {
            $error = 'ارسال پیام ناموفق بود. لطفا error_log سرور را بررسی کنید.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تست نهایی تلگرام</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #c3e6cb;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #f5c6cb;
            margin: 20px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #bee5eb;
            margin: 20px 0;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            width: 100%;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        button:active {
            transform: translateY(0);
        }
        h2 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            margin-top: 0;
        }
        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        .status-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .status-item strong {
            display: block;
            color: #667eea;
            margin-bottom: 5px;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            direction: ltr;
            text-align: left;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>🚀 تست نهایی اتصال تلگرام</h2>
        
        <div class="info">
            <strong>✅ Worker شما فعال است!</strong>
            <p>همه تست‌های اولیه موفق بود. حالا بیایید با توکن واقعی تست کنیم.</p>
        </div>

        <div class="status-grid">
            <div class="status-item">
                <strong>🌐 Proxy URL</strong>
                <?php echo htmlspecialchars($telegramConfig['proxy_url']); ?>
            </div>
            <div class="status-item">
                <strong>🤖 Bot Token</strong>
                <?php echo empty($telegramConfig['bot_token']) ? '❌ تنظیم نشده' : '✅ تنظیم شده'; ?>
            </div>
            <div class="status-item">
                <strong>💬 Chat IDs</strong>
                <?php echo empty($telegramConfig['chat_ids']) ? '❌ تنظیم نشده' : count($telegramConfig['chat_ids']) . ' عدد'; ?>
            </div>
            <div class="status-item">
                <strong>📸 Include Images</strong>
                <?php echo $telegramConfig['include_images'] ? '✅ فعال' : '❌ غیرفعال'; ?>
            </div>
        </div>

        <?php if ($result): ?>
        <div class="success">
            <h3 style="margin-top: 0;">🎉 تبریک! همه چیز کار می‌کند!</h3>
            <p><strong>✅ پیام با موفقیت به تلگرام ارسال شد!</strong></p>
            <p>Message ID: <?php echo $result['result']['message_id'] ?? 'نامشخص'; ?></p>
            <p>Chat ID: <?php echo $result['result']['chat']['id'] ?? 'نامشخص'; ?></p>
            
            <hr style="margin: 20px 0; border: none; border-top: 1px solid #c3e6cb;">
            
            <p><strong>مراحل بعدی:</strong></p>
            <ol style="text-align: right;">
                <li>به تلگرام بروید و پیام تست را مشاهده کنید</li>
                <li>از صفحه <a href="telegram_report_manual.php">ارسال دستی گزارش</a> برای ارسال گزارش واقعی استفاده کنید</li>
                <li>برای ارسال خودکار، Cron Job تنظیم کنید</li>
            </ol>
            
            <details style="margin-top: 15px;">
                <summary style="cursor: pointer; color: #155724; font-weight: bold;">مشاهده پاسخ کامل Telegram</summary>
                <pre><?php echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
            </details>
        </div>
        
        <div class="info">
            <strong>📌 نمونه Cron Job:</strong>
            <pre>0 18 * * * /usr/bin/php <?php echo realpath(__DIR__); ?>/send_daily_telegram_report.php</pre>
            <small>این دستور هر روز ساعت 18:00 گزارش را ارسال می‌کند</small>
        </div>
        
        <?php elseif ($error): ?>
        <div class="error">
            <h3 style="margin-top: 0;">❌ خطا در ارسال پیام</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
            
            <p><strong>مراحل عیب‌یابی:</strong></p>
            <ol style="text-align: right;">
                <li>بررسی کنید Bot Token در <code>telegram_config.php</code> صحیح باشد</li>
                <li>مطمئن شوید Chat ID صحیح است (از @userinfobot دریافت کنید)</li>
                <li>ربات را به گروه اضافه کنید و Admin کنید</li>
                <li>error_log سرور را بررسی کنید</li>
            </ol>
        </div>
        <?php endif; ?>

        <form method="POST" style="margin-top: 30px;">
            <button type="submit">
                <?php echo $result ? '🔄 ارسال مجدد پیام تست' : '📤 ارسال پیام تست به تلگرام'; ?>
            </button>
        </form>
        
        <?php if (!$result): ?>
        <div style="margin-top: 20px; text-align: center; color: #666;">
            <small>
                با کلیک روی دکمه، یک پیام تستی به اولین Chat ID تنظیم شده ارسال می‌شود
            </small>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>