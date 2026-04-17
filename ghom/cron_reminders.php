<?php
// public_html/ghom/cron_reminders.php
// This script runs as a cron job to send reminders for overdue tasks
require_once __DIR__ . '/../../sercon/bootstrap.php';

echo "Cron Job Started...\n";
$pdo = getProjectDBConnection('ghom');

// پیدا کردن وظایف تعمیر و بازرسی که سررسیدشان گذشته و هنوز انجام نشده‌اند
$sql = "SELECT * FROM notifications WHERE type = 'task' AND status = 'pending' AND due_date < CURDATE()";
$stmt = $pdo->query($sql);
$overdue_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($overdue_tasks)) {
    echo "No overdue tasks found. Exiting.\n";
    exit;
}

echo "Found " . count($overdue_tasks) . " overdue tasks.\n";
$reminder_sql = "INSERT INTO notifications (user_id, created_by_user_id, link, message, type, status) VALUES (?, ?, ?, ?, 'reminder', 'pending')";
$reminder_stmt = $pdo->prepare($reminder_sql);

foreach ($overdue_tasks as $task) {
    $days_overdue = (new DateTime())->diff(new DateTime($task['due_date']))->days;
    $message = "یادآوری: وظیفه '{$task['title']}' {$days_overdue} روز تاخیر دارد. لطفاً بررسی کنید.";

    // ارسال یادآور به همان شخصی که وظیفه اصلی را داشته
    $reminder_stmt->execute([
        $task['user_id'],
        $task['created_by_user_id'], // فرستنده اصلی
        $task['link'],
        $message
    ]);
    echo "Reminder sent for task ID: {$task['notification_id']}\n";
}
echo "Cron Job Finished.\n";
