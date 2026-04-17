<?php
// public_html/pardis/telegram_report_api.php

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
require_once __DIR__ . '/includes/TelegramBot.php';

header('Content-Type: application/json; charset=utf-8');

secureSession();

// Only admins can trigger reports
if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['admin', 'superuser'])) {
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}
require_once __DIR__ . '/includes/WeatherService.php';

// Get weather
$weatherConfig = require __DIR__ . '/weather_config.php';
$weatherService = new WeatherService($weatherConfig);
$weather = $weatherService->getCurrentWeather();

// Add to report message
if ($weather) {
    $reportMsg .= "\n🌤 <b>آب و هوا:</b>\n";
    $reportMsg .= $weatherService->formatWeatherText($weather) . "\n";
}
// Get parameters
$reportDateOption = $_POST['report_date'] ?? 'today';
$includeImages = isset($_POST['include_images']);

// Determine date
switch ($reportDateOption) {
    case 'yesterday':
        $targetDate = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'custom':
        $targetDate = $_POST['custom_date'] ?? date('Y-m-d');
        // Validate date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            exit;
        }
        break;
    default:
        $targetDate = date('Y-m-d');
}

// Load Telegram configuration
$telegramConfig = require __DIR__ . '/telegram_config.php';
$telegram = new TelegramBot($telegramConfig);

// Override include_images setting
$telegramConfig['include_images'] = $includeImages;

// Set timezone
date_default_timezone_set($telegramConfig['timezone']);

// Get database connection
$pdo = getProjectDBConnection('pardis');

// Convert to Jalali
$dateParts = explode('-', $targetDate);
$jalali = gregorian_to_jalali($dateParts[0], $dateParts[1], $dateParts[2]);
$jalaliFormatted = sprintf('%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2]);

// Fetch reports
$sql = "
    SELECT 
        dr.id as report_id,
        dr.user_id,
        dr.engineer_name,
        dr.role,
        dr.location,
        dr.weather,
        dr.work_hours,
        dr.arrival_time,
        dr.departure_time,
        dr.safety_incident,
        dr.next_day_plan,
        dr.general_notes,
        dr.created_at,
        COUNT(DISTINCT ra.id) as activities_count,
        AVG(ra.progress_percentage) as avg_progress,
        SUM(ra.hours_spent) as total_hours_activities,
        COUNT(DISTINCT CASE WHEN ra.completion_status = 'completed' THEN ra.id END) as completed_count,
        COUNT(DISTINCT CASE WHEN ra.completion_status = 'blocked' THEN ra.id END) as blocked_count
    FROM daily_reports dr
    LEFT JOIN report_activities ra ON dr.id = ra.report_id
    WHERE dr.report_date = ?
    GROUP BY dr.id
    ORDER BY dr.engineer_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$targetDate]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($reports)) {
    $message = "📋 <b>گزارش روزانه پروژه پردیس</b>\n\n";
    $message .= "📅 تاریخ: {$jalaliFormatted}\n\n";
    $message .= "⚠️ هیچ گزارشی برای این تاریخ ثبت نشده است.";
    
    foreach ($telegramConfig['chat_ids'] as $chatId) {
        $telegram->sendMessage($chatId, $message, 'HTML');
    }
    
    echo json_encode([
        'success' => true,
        'reports_count' => 0,
        'messages_sent' => 1,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

$messagesSent = 0;
$imagesSent = 0;

// Role and weather mappings
$roleFa = [
    'field_engineer' => 'اجرا',
    'designer' => 'طراح',
    'surveyor' => 'نقشه‌بردار',
    'control_engineer' => 'کنترل پروژه',
    'drawing_specialist' => 'شاپیست'
];

$weather = [
    'clear' => '☀️ آفتابی',
    'cloudy' => '☁️ ابری',
    'rainy' => '🌧 بارانی',
    'hot' => '🌡 گرم',
    'cold' => '❄️ سرد',
    'other' => 'سایر'
];

// Send summary
$totalActivities = array_sum(array_column($reports, 'activities_count'));
$totalWorkHours = array_sum(array_column($reports, 'work_hours'));
$totalCompleted = array_sum(array_column($reports, 'completed_count'));
$totalBlocked = array_sum(array_column($reports, 'blocked_count'));

$summaryMsg = "📋 <b>گزارش روزانه پروژه دانشگاه خاتم پردیس</b>\n";
$summaryMsg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
$summaryMsg .= "📅 <b>تاریخ:</b> {$jalaliFormatted}\n";
$summaryMsg .= "👥 <b>تعداد گزارش‌ها:</b> " . count($reports) . "\n\n";
$summaryMsg .= "📊 <b>آمار کلی:</b>\n";
$summaryMsg .= "▫️ کل فعالیت‌ها: {$totalActivities}\n";
$summaryMsg .= "▫️ کل ساعات کاری: " . round($totalWorkHours, 1) . " ساعت\n";
$summaryMsg .= "▫️ فعالیت‌های تکمیل شده: {$totalCompleted}\n";
if ($totalBlocked > 0) {
    $summaryMsg .= "▫️ ⚠️ فعالیت‌های مسدود: {$totalBlocked}\n";
}
$summaryMsg .= "\n━━━━━━━━━━━━━━━━━━━━━━━━\n";

foreach ($telegramConfig['chat_ids'] as $chatId) {
    $telegram->sendMessage($chatId, $summaryMsg, 'HTML');
    $messagesSent++;
}

// Process each report
foreach ($reports as $report) {
    // Fetch activities
    $actSql = "
        SELECT 
            project_name, building_name, building_part,
            task_description, activity_type,
            progress_percentage, hours_spent,
            completion_status, blocked_reason
        FROM report_activities
        WHERE report_id = ?
        ORDER BY id
    ";
    
    $actStmt = $pdo->prepare($actSql);
    $actStmt->execute([$report['report_id']]);
    $activities = $actStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch issues
    $issuesSql = "SELECT issue_description FROM report_issues WHERE report_id = ? AND status = 'open'";
    $issuesStmt = $pdo->prepare($issuesSql);
    $issuesStmt->execute([$report['report_id']]);
    $issues = $issuesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Build message
    $reportMsg = "👤 <b>" . TelegramBot::escapeHtml($report['engineer_name']) . "</b>\n";
    $reportMsg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $reportMsg .= "🔹 نقش: " . ($roleFa[$report['role']] ?? $report['role']) . "\n";
    $reportMsg .= "📍 محل: " . TelegramBot::escapeHtml($report['location']) . "\n";
    $reportMsg .= "🌤 آب و هوا: " . ($weatherFa[$report['weather']] ?? $report['weather']) . "\n";
    $reportMsg .= "⏱ ساعات کاری: " . $report['work_hours'] . " ساعت\n";
    $reportMsg .= "🕐 ورود: " . $report['arrival_time'] . " | خروج: " . $report['departure_time'] . "\n";
    
    if ($report['safety_incident'] === 'yes') {
        $reportMsg .= "⚠️ <b>حادثه ایمنی گزارش شده</b>\n";
    }
    
    $reportMsg .= "\n📌 <b>فعالیت‌ها ({$report['activities_count']}):</b>\n\n";
    
    // Add activities
    $statusEmoji = [
        'completed' => '✅',
        'in_progress' => '🔄',
        'blocked' => '🚫',
        'delayed' => '⏳',
        'not_started' => '⭕️'
    ];
    
    $statusText = [
        'completed' => 'تکمیل شده',
        'in_progress' => 'در حال انجام',
        'blocked' => 'مسدود',
        'delayed' => 'تاخیر دارد',
        'not_started' => 'شروع نشده'
    ];
    
    foreach ($activities as $i => $activity) {
        $num = $i + 1;
        $reportMsg .= "<b>{$num}.</b> ";
        
        if (!empty($activity['building_name'])) {
            $reportMsg .= "🏢 " . TelegramBot::escapeHtml($activity['building_name']);
            if (!empty($activity['building_part'])) {
                $reportMsg .= " - " . TelegramBot::escapeHtml($activity['building_part']);
            }
            $reportMsg .= "\n";
        }
        
        $reportMsg .= "   📝 " . TelegramBot::escapeHtml($activity['task_description']) . "\n";
        
        if (!empty($activity['activity_type'])) {
            $reportMsg .= "   🔖 " . TelegramBot::escapeHtml($activity['activity_type']) . "\n";
        }
        
        // Progress bar
        $progress = $activity['progress_percentage'];
        $progressBar = str_repeat('█', round($progress / 10)) . str_repeat('░', 10 - round($progress / 10));
        $reportMsg .= "   📊 {$progressBar} {$progress}%\n";
        
        if ($activity['hours_spent']) {
            $reportMsg .= "   ⏱ " . $activity['hours_spent'] . " ساعت\n";
        }
        
        $statusIcon = $statusEmoji[$activity['completion_status']] ?? '•';
        $statusLabel = $statusText[$activity['completion_status']] ?? $activity['completion_status'];
        $reportMsg .= "   {$statusIcon} {$statusLabel}\n";
        
        if ($activity['completion_status'] === 'blocked' && !empty($activity['blocked_reason'])) {
            $reportMsg .= "   ⚠️ " . TelegramBot::escapeHtml($activity['blocked_reason']) . "\n";
        }
        
        $reportMsg .= "\n";
    }
    
    // Add issues
    if (!empty($issues)) {
        $reportMsg .= "🚨 <b>مشکلات ({" . count($issues) . "}):</b>\n";
        foreach ($issues as $j => $issue) {
            $reportMsg .= ($j + 1) . ". " . TelegramBot::escapeHtml($issue) . "\n";
        }
        $reportMsg .= "\n";
    }
    
    // Add tomorrow's plan
    if (!empty($report['next_day_plan'])) {
        $reportMsg .= "📅 <b>برنامه فردا:</b>\n";
        $reportMsg .= TelegramBot::escapeHtml($report['next_day_plan']) . "\n\n";
    }
    
    if (!empty($report['general_notes'])) {
        $reportMsg .= "💬 <b>یادداشت:</b>\n";
        $reportMsg .= TelegramBot::escapeHtml($report['general_notes']) . "\n\n";
    }
    
    $reportMsg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    // Send message
    $chunks = TelegramBot::splitMessage($reportMsg);
    foreach ($telegramConfig['chat_ids'] as $chatId) {
        foreach ($chunks as $chunk) {
            $telegram->sendMessage($chatId, $chunk, 'HTML');
            $messagesSent++;
            usleep(100000);
        }
    }
    
    // Send images
    if ($includeImages) {
        $imagesSql = "SELECT file_path FROM report_attachments WHERE report_id = ? AND file_type LIKE 'image/%'";
        $imagesStmt = $pdo->prepare($imagesSql);
        $imagesStmt->execute([$report['report_id']]);
        $images = $imagesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($images as $imagePath) {
            $fullPath = __DIR__ . '/../../' . $imagePath;
            if (file_exists($fullPath)) {
                $caption = "📷 " . TelegramBot::escapeHtml($report['engineer_name']);
                foreach ($telegramConfig['chat_ids'] as $chatId) {
                    $telegram->sendPhoto($chatId, $fullPath, $caption);
                    $imagesSent++;
                    usleep(100000);
                }
            }
        }
    }
}

// Send footer
$footerMsg = "\n✅ <b>گزارش با موفقیت ارسال شد</b>\n";
$footerMsg .= "🕐 زمان: " . date('H:i:s') . "\n";
$footerMsg .= "━━━━━━━━━━━━━━━━━━━━━━━━";

foreach ($telegramConfig['chat_ids'] as $chatId) {
    $telegram->sendMessage($chatId, $footerMsg, 'HTML');
    $messagesSent++;
}

echo json_encode([
    'success' => true,
    'reports_count' => count($reports),
    'messages_sent' => $messagesSent,
    'images_sent' => $imagesSent,
    'timestamp' => date('Y-m-d H:i:s')
]);