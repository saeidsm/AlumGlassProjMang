<?php
// public_html/pardis/manage_reminders.php
// Web interface to manage reminder system

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
require_once __DIR__ . '/includes/TelegramBot.php';

header('Content-Type: text/html; charset=utf-8');
secureSession();

// Only admins can access
if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['admin', 'superuser'])) {
    http_response_code(403);
    die('Access Denied');
}


$pdoCommon = getCommonDBConnection();
$pdoPardis = getProjectDBConnection('pardis');
$telegramConfig = require __DIR__ . '/telegram_config.php';
$message = null;
$messageType = null;
date_default_timezone_set('Asia/Tehran');


$usersQuery = "
    SELECT 
        u.id,
        u.username,
        CONCAT(u.first_name, ' ', u.last_name) as full_name,
        u.first_name,
        u.last_name,
        u.telegram_chat_id,
        u.role,
        u.is_active,
        u.default_project_id,
        (SELECT COUNT(*) FROM pardis.daily_reports dr WHERE dr.user_id = u.id AND dr.report_date = CURDATE()) as has_todays_report,
        (SELECT MAX(reminder_date) FROM pardis.report_reminders rr WHERE rr.user_id = u.id) as last_reminder,
        CASE 
            WHEN u.default_project_id = 6 THEN 'default'
            WHEN EXISTS (SELECT 1 FROM user_projects up WHERE up.user_id = u.id AND up.project_id = 6) THEN 'assigned'
            ELSE 'no_access'
        END as project_access
    FROM hpc_common.users u
    WHERE (
        u.default_project_id = 6 
        OR EXISTS (
            SELECT 1 FROM user_projects up 
            WHERE up.user_id = u.id 
            AND up.project_id = 6
        )
    )
    ORDER BY u.is_active DESC, u.first_name, u.last_name
";

$users = $pdoCommon->query($usersQuery)->fetchAll(PDO::FETCH_ASSOC);

// Get reminder statistics (from pardis database)
$statsQuery = "
    SELECT 
        COUNT(*) as total_reminders,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        MAX(reminder_date) as last_reminder_date
    FROM report_reminders
    WHERE reminder_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
";

$stats = $pdoPardis->query($statsQuery)->fetch(PDO::FETCH_ASSOC);

// Get recent reminders (join across databases)
$recentQuery = "
    SELECT 
        rr.*,
        CONCAT(u.first_name, ' ', u.last_name) as full_name,
        u.username
    FROM pardis.report_reminders rr
    JOIN hpc_common.users u ON rr.user_id = u.id
    ORDER BY rr.created_at DESC
    LIMIT 20
";

$recentReminders = $pdoPardis->query($recentQuery)->fetchAll(PDO::FETCH_ASSOC);
// Get database connections
// Users are in hpc_common, reports and reminders are in pardis


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_telegram_id') {
        $userId = $_POST['user_id'] ?? 0;
        $telegramChatId = trim($_POST['telegram_chat_id'] ?? '');
        
        $stmt = $pdoCommon->prepare("UPDATE users SET telegram_chat_id = ? WHERE id = ?");
        if ($stmt->execute([$telegramChatId, $userId])) {
            $message = "Telegram Chat ID updated successfully";
            $messageType = 'success';
        } else {
            $message = "Failed to update Telegram Chat ID";
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'send_test_reminder') {
        $userId = $_POST['user_id'] ?? 0;
        
        $stmt = $pdoCommon->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && !empty($user['telegram_chat_id'])) {
            $telegram = new TelegramBot($telegramConfig);
            
            $testMessage = "🧪 <b>تست سیستم یادآوری</b>\n\n";
            $testMessage .= "سلام " . ($user['first_name']." ".$user['last_name'] ?: $user['username']) . "\n\n";
            $testMessage .= "این یک پیام تستی از سیستم یادآوری گزارش روزانه است.\n\n";
            $testMessage .= "اگر این پیام را دریافت کردید، یعنی سیستم به درستی کار می‌کند! ✅\n\n";
            $jy = date('Y');
$jm = date('m');
$jd = date('d');

list($jy, $jm, $jd) = gregorian_to_jalali($jy, $jm, $jd);
$testMessage .= "⏰ زمان تست: " . $jd . '-' . $jm . '-' . $jy . ' ' . date('H:i:s');
            
            $result = $telegram->sendMessage($user['telegram_chat_id'], $testMessage, 'HTML');
            
            if ($result) {
                $message = "Test reminder sent successfully to " . $user['first_name']." ".$user['last_name'];
                $messageType = 'success';
            } else {
                $message = "Failed to send test reminder. Check logs.";
                $messageType = 'error';
            }
        } else {
            $message = "User not found or Telegram Chat ID not set";
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'send_all_reminders') {
        // Trigger reminder script
        $scriptPath = __DIR__ . '/send_daily_reminders.php';
        $command = '/usr/bin/php ' . escapeshellarg($scriptPath) . ' > /dev/null 2>&1 &';
        exec($command);
        
        $message = "Reminder process started. Check results in a few moments.";
        $messageType = 'info';
    }
}

// Get users data (join across databases)
// NO ROLE FILTER - Anyone with Pardis access
$pageTitle = "مدیریت سیستم یادآوری گزارش روزانه";
function isMobileDevices() {
    return preg_match(
        "/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i",
        $_SERVER["HTTP_USER_AGENT"]
    );
}

if (isMobileDevices()) {
    require_once __DIR__ . '/header_p_mobile.php';
} else {
    require_once __DIR__ . '/header_pardis.php';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت سیستم یادآوری</title>
    <link href="/pardis/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 16px 20px;
            font-weight: 600;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-card h3 {
            font-size: 2.5rem;
            margin: 0;
        }
        .stat-card p {
            margin: 0;
            opacity: 0.9;
        }
        .user-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .has-telegram { color: #28a745; }
        .no-telegram { color: #dc3545; }
        .has-report { color: #28a745; }
        .no-report { color: #ffc107; }
        .btn-test {
            padding: 4px 12px;
            font-size: 12px;
        }
        table {
            font-size: 14px;
        }
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col">
               
                <p class="text-muted">مدیریت Telegram Chat IDs و ارسال یادآوری به کاربران</p>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'error' ? 'danger' : 'info'); ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?php echo count($users); ?></h3>
                    <p>کل کاربران</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h3><?php echo count(array_filter($users, fn($u) => !empty($u['telegram_chat_id']))); ?></h3>
                    <p>Telegram فعال</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <h3><?php echo $stats['successful'] ?? 0; ?></h3>
                    <p>یادآوری (30 روز)</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <h3><?php echo count(array_filter($users, fn($u) => $u['has_todays_report'] > 0)); ?></h3>
                    <p>گزارش امروز</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightning-charge"></i> عملیات سریع
            </div>
            <div class="card-body">
                <form method="POST" style="display: inline;" onsubmit="return confirm('آیا مطمئن هستید؟');">
                    <input type="hidden" name="action" value="send_all_reminders">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> ارسال یادآوری به همه کاربران بدون گزارش
                    </button>
                </form>
                <a href="send_daily_reminders.php" class="btn btn-secondary" target="_blank">
                    <i class="bi bi-terminal"></i> اجرای اسکریپت (خروجی CLI)
                </a>
                <a href="?refresh=1" class="btn btn-info">
                    <i class="bi bi-arrow-clockwise"></i> بروزرسانی
                </a>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-people"></i> لیست کاربران و Telegram Chat IDs
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>نام</th>
                                <th>نقش</th>
                                <th>وضعیت</th>
                                <th>Telegram Chat ID</th>
                                <th>گزارش امروز</th>
                                <th>آخرین یادآوری</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($user['role']); ?>                                </td>
                                <td>
                                    <span class="user-status status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                                    </span>
                                    <?php if ($user['project_access'] === 'default'): ?>
                                        <small class="text-muted" title="پروژه پیش‌فرض">📌</small>
                                    <?php elseif ($user['project_access'] === 'assigned'): ?>
                                        <small class="text-muted" title="دسترسی اختصاصی">🔗</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: flex; gap: 5px;">
                                        <input type="hidden" name="action" value="update_telegram_id">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input 
                                            type="text" 
                                            name="telegram_chat_id" 
                                            class="form-control form-control-sm" 
                                            value="<?php echo htmlspecialchars($user['telegram_chat_id'] ?? ''); ?>"
                                            placeholder="Chat ID"
                                            style="max-width: 150px; direction: ltr; text-align: left;"
                                        >
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="bi bi-check"></i>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <?php if ($user['has_todays_report'] > 0): ?>
                                        <i class="bi bi-check-circle-fill has-report"></i> ثبت شده
                                    <?php else: ?>
                                        <i class="bi bi-exclamation-circle-fill no-report"></i> ثبت نشده
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php


echo $user['last_reminder']
    ? gregorian_to_jalali(
        date('Y', strtotime($user['last_reminder'])),
        date('m', strtotime($user['last_reminder'])),
        date('d', strtotime($user['last_reminder'])),
        '-'
      )
    : '-';
?>
                                </td>
                                <td>
                                    <?php if (!empty($user['telegram_chat_id'])): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="send_test_reminder">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary btn-test">
                                            <i class="bi bi-send"></i> تست
                                        </button>
                                    </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Reminders -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history"></i> آخرین یادآوری‌ها (20 مورد اخیر)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>زمان</th>
                                <th>کاربر</th>
                                <th>تاریخ گزارش</th>
                                <th>وضعیت</th>
                                <th>خطا</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentReminders as $reminder): ?>
                            <tr>
                                <td><?php echo gregorian_to_jalali(
        date('Y', strtotime($reminder['created_at'])),
        date('m', strtotime($reminder['created_at'])),
        date('d', strtotime($reminder['created_at'])),
        '-'
     )
     . ' ' .
     date('H:i', strtotime($reminder['created_at']));
?></td>
                                <td><?php echo htmlspecialchars($reminder['full_name'] ?: $reminder['username']); ?></td>
                                <td><?php echo gregorian_to_jalali(
        date('Y', strtotime($reminder['reminder_date'])),
        date('m', strtotime($reminder['reminder_date'])),
        date('d', strtotime($reminder['reminder_date'])),
        '-'
     )
?></td>
                                <td>
                                    <?php if ($reminder['status'] === 'sent'): ?>
                                        <span class="badge bg-success">ارسال شده</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">خطا</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($reminder['error_message'])): ?>
                                        <small class="text-danger"><?php echo htmlspecialchars($reminder['error_message']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> راهنمای استفاده
            </div>
            <div class="card-body">
                <h5>نحوه دریافت Telegram Chat ID:</h5>
                <ol>
                    <li>کاربر باید به ربات <code>@userinfobot</code> یا <code>@RawDataBot</code> در تلگرام پیام بدهد</li>
                    <li>Chat ID نمایش داده می‌شود (یک عدد مثل: 123456789)</li>
                    <li>Chat ID را در جدول بالا وارد کرده و ذخیره کنید</li>
                    <li>دکمه "تست" را بزنید تا یک پیام تستی ارسال شود</li>
                </ol>

                <h5 class="mt-4">تنظیم Cron Job برای ارسال خودکار:</h5>
                <pre class="bg-light p-3 rounded">
# ارسال یادآوری هر روز ساعت 19:00 (7 PM)
0 19 * * * /usr/bin/php <?php echo realpath(__DIR__); ?>/send_daily_reminders.php >> /home/alumglas/telpat/logs/reminders.log 2>&1

# یا ارسال در چند زمان مختلف:
0 17 * * * /usr/bin/php <?php echo realpath(__DIR__); ?>/send_daily_reminders.php  # 5 PM
0 19 * * * /usr/bin/php <?php echo realpath(__DIR__); ?>/send_daily_reminders.php  # 7 PM
0 21 * * * /usr/bin/php <?php echo realpath(__DIR__); ?>/send_daily_reminders.php  # 9 PM
</pre>

                <h5 class="mt-4">ویژگی‌های سیستم:</h5>
                <ul>
                    <li>✅ فقط به کاربرانی که گزارش ثبت نکرده‌اند یادآوری ارسال می‌شود</li>
                    <li>✅ استفاده از Cloudflare Worker Proxy برای دور زدن محدودیت</li>
                    <li>✅ ثبت تاریخچه تمام یادآوری‌ها در دیتابیس</li>
                    <li>✅ ارسال خلاصه به گروه مدیران</li>
                    <li>✅ قابلیت تست تک‌تک کاربران</li>
                </ul>

                <div class="alert alert-warning mt-3">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>نکته:</strong> اطمینان حاصل کنید که Cloudflare Worker Proxy در 
                    <code>telegram_config.php</code> تنظیم شده باشد: 
                    <code>'proxy_url' => 'https://pt.sabaat.ir'</code>
                </div>
            </div>
        </div>
    </div>

    <script src="/pardis/assets/js/jquery-3.6.0.min.js"></script>
    <script src="/pardis/assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000);

        // Show confirmation for bulk send
        document.querySelector('form[action*="send_all_reminders"]')?.addEventListener('submit', function(e) {
            if (!confirm('آیا مطمئنید که می‌خواهید به همه کاربران بدون گزارش یادآوری ارسال شود؟')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>