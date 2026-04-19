<?php
// public_html/pardis/send_daily_reminders.php
// Automated reminder system for daily reports
// Run via cron: 0 19 * * * /usr/bin/php /path/to/send_daily_reminders.php

require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
require_once __DIR__ . '/includes/TelegramBot.php';

// Set timezone
date_default_timezone_set('Asia/Tehran');

// --- START: DO NOT RUN ON FRIDAYS AND HOLIDAYS ---

// Get today's date
$today = date('Y-m-d');
$dayOfWeek = date('w'); // 0 for Sunday, 6 for Saturday

// Exit if today is Friday (assuming Friday is the weekend)
if ($dayOfWeek == 5) { // In Iran, Friday is the weekend
    echo "Today is Friday. No reminders will be sent.\n";
    exit(0);
}

// Check for holidays from JSON file
$holidaysFile = __DIR__ . '/assets/js/all_events.json';
if (file_exists($holidaysFile)) {
    $holidaysJson = file_get_contents($holidaysFile);
    $holidays = json_decode($holidaysJson, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        foreach ($holidays as $event) {
            if (isset($event['gregorian_date_str']) && $event['gregorian_date_str'] === $today && isset($event['is_holiday']) && $event['is_holiday'] === true) {
                echo "Today ({$today}) is a holiday: {$event['event_description']}. No reminders will be sent.\n";
                exit(0);
            }
        }
    } else {
        echo "Error decoding holidays JSON file.\n";
    }
} else {
    echo "Holidays JSON file not found.\n";
}

// --- END: DO NOT RUN ON FRIDAYS AND HOLIDAYS ---


// Load Telegram configuration
$telegramConfig = require __DIR__ . '/telegram_config.php';
$telegram = new TelegramBot($telegramConfig);

// Get database connections
// Users are in hpc_common database, reports are in pardis database
$pdoCommon = getCommonDBConnection();
$pdoPardis = getProjectDBConnection('pardis');

// Convert to Jalali for display
$dateParts = explode('-', $today);
$jalali = gregorian_to_jalali($dateParts[0], $dateParts[1], $dateParts[2]);
$jalaliFormatted = sprintf('%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2]);

echo "=== Daily Report Reminder System ===\n";
echo "Date: {$today} ({$jalaliFormatted})\n";
echo "Time: " . date('H:i:s') . "\n";
echo "Using Proxy: " . (!empty($telegramConfig['proxy_url']) ? $telegramConfig['proxy_url'] : 'Direct connection') . "\n\n";

// Get all active users who should submit reports (from hpc_common.users)
// NO ROLE FILTER - Anyone with Pardis access can receive reminders
$usersQuery = "
    SELECT
        u.id,
        u.username,
        CONCAT(u.first_name, ' ', u.last_name) as full_name,
        u.first_name,
        u.last_name,
        u.telegram_chat_id,
        u.role,
        u.email
    FROM users u
    WHERE u.is_active = 1
    AND u.telegram_chat_id IS NOT NULL
    AND u.telegram_chat_id != ''
    AND (
        u.default_project_id = 6
        OR EXISTS (
            SELECT 1 FROM user_projects up
            WHERE up.user_id = u.id
            AND up.project_id = 6
        )
    )
    ORDER BY u.first_name, u.last_name
";

$usersStmt = $pdoCommon->query($usersQuery);
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "No users found with Telegram chat IDs configured.\n";
    exit(0);
}

echo "Found " . count($users) . " active users to check.\n\n";

// Get users who have already submitted reports today (from pardis.daily_reports)
$submittedQuery = "
    SELECT DISTINCT user_id
    FROM daily_reports
    WHERE report_date = ?
";

$submittedStmt = $pdoPardis->prepare($submittedQuery);
$submittedStmt->execute([$today]);
$submittedUserIds = $submittedStmt->fetchAll(PDO::FETCH_COLUMN);

echo "Users who submitted reports today: " . count($submittedUserIds) . "\n\n";

// Role translations
$roleFa = [
    'field_engineer' => 'مهندس اجرا',
    'designer' => 'طراح',
    'surveyor' => 'نقشه‌بردار',
    'control_engineer' => 'مهندس کنترل پروژه',
    'drawing_specialist' => 'شاپ'
];

// Track reminder statistics
$remindersNeeded = 0;
$remindersSent = 0;
$remindersFailed = 0;

// Check each user and send reminder if needed
foreach ($users as $user) {
    $userName = $user['full_name'] ?: $user['username'];
    $userRole = $roleFa[$user['role']] ?? $user['role'];

    // Check if user has submitted report today
    if (in_array($user['id'], $submittedUserIds)) {
        echo "✅ {$userName} ({$userRole}) - Report submitted\n";
        continue;
    }

    // User hasn't submitted - send reminder
    echo "⚠️  {$userName} ({$userRole}) - No report, sending reminder...\n";
    $remindersNeeded++;

    // Compose reminder message
    $reminderMessage = "🔔 <b>یادآوری ثبت گزارش روزانه</b>\n\n";
    $reminderMessage .= "سلام {$userName} عزیز\n\n";
    $reminderMessage .= "📅 <b>تاریخ:</b> {$jalaliFormatted}\n";
    $reminderMessage .= "👤 <b>نقش:</b> {$userRole}\n\n";
    $reminderMessage .= "⚠️ گزارش کاری امروز شما هنوز ثبت نشده است.\n\n";
    $reminderMessage .= "لطفاً در اسرع وقت گزارش روزانه خود را در سامانه ثبت کنید.\n\n";
    $reminderMessage .= "🔗 لینک ثبت گزارش:\n";
    $reminderMessage .= "https://alumglass.ir/pardis/submit_report.php\n\n";
    $reminderMessage .= "⏰ زمان یادآوری: " . date('H:i') . "\n\n";
    $reminderMessage .= "با تشکر\n";
    $reminderMessage .= "سیستم مدیریت پروژه پردیس";

    // Send reminder
    $result = $telegram->sendMessage($user['telegram_chat_id'], $reminderMessage, 'HTML');

    if ($result) {
        echo "   ✅ Reminder sent successfully\n";
        $remindersSent++;

        // Log the reminder in database (optional)
        try {
            $logStmt = $pdoPardis->prepare("
                INSERT INTO report_reminders (user_id, reminder_date, reminder_time, status)
                VALUES (?, ?, ?, 'sent')
            ");
            $logStmt->execute([$user['id'], $today, date('H:i:s')]);
        } catch (Exception $e) {
            // Table might not exist, just log error
            error_log("Failed to log reminder: " . $e->getMessage());
        }
    } else {
        echo "   ❌ Failed to send reminder\n";
        $remindersFailed++;

        // Log failure
        try {
            $logStmt = $pdoPardis->prepare("
                INSERT INTO report_reminders (user_id, reminder_date, reminder_time, status, error_message)
                VALUES (?, ?, ?, 'failed', 'Telegram API error')
            ");
            $logStmt->execute([$user['id'], $today, date('H:i:s')]);
        } catch (Exception $e) {
            error_log("Failed to log reminder failure: " . $e->getMessage());
        }
    }

    // Small delay to avoid rate limiting
    usleep(500000); // 0.5 seconds
}

echo "\n=== Summary ===\n";
echo "Total users checked: " . count($users) . "\n";
echo "Reports submitted: " . count($submittedUserIds) . "\n";
echo "Reminders needed: {$remindersNeeded}\n";
echo "Reminders sent: {$remindersSent}\n";
echo "Reminders failed: {$remindersFailed}\n";

// Send summary to admin group if configured
if (!empty($telegramConfig['chat_ids']) && $remindersNeeded > 0) {
    $adminChatId = $telegramConfig['chat_ids'][0]; // First chat ID is admin group

    $summaryMessage = "📊 <b>خلاصه یادآوری گزارش روزانه</b>\n\n";
    $summaryMessage .= "📅 تاریخ: {$jalaliFormatted}\n";
    $summaryMessage .= "⏰ زمان: " . date('H:i') . "\n\n";
    $summaryMessage .= "👥 کل کاربران: " . count($users) . "\n";
    $summaryMessage .= "✅ گزارش ثبت شده: " . count($submittedUserIds) . "\n";
    $summaryMessage .= "⚠️ یادآوری ارسال شده: {$remindersSent}\n";

    if ($remindersFailed > 0) {
        $summaryMessage .= "❌ یادآوری ناموفق: {$remindersFailed}\n";
    }

    $summaryMessage .= "\n━━━━━━━━━━━━━━━━━━━━━━";

    $telegram->sendMessage($adminChatId, $summaryMessage, 'HTML');
    echo "\nAdmin summary sent to group.\n";
}

echo "\nReminder process completed at " . date('H:i:s') . "\n";
exit(0);