<?php
// public_html/ghom/api/get_notifications.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();

// Add error reporting for debugging
function log_notif_event($message)
{
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', dirname(__DIR__, 4));
    }

    $log_file = 'noyif_log_1' . '.log';
    $timestamp = date("Y-m-d H:i:s");
    $formatted_message = is_array($message) || is_object($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message;
    file_put_contents($log_file, "[$timestamp] " . $formatted_message . "\n", FILE_APPEND);
}

if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getProjectDBConnection('ghom');

    // Debug: Log the user_id being used
    log_notif_event("Fetching notifications for user_id: " . $user_id);

    // FIRST: Let's see what's actually in the database for this user
    $debug_stmt = $pdo->prepare("SELECT notification_id, type, status, message FROM notifications WHERE user_id = ? LIMIT 5");
    $debug_stmt->execute([$user_id]);
    $debug_data = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
    log_notif_event("Sample data for user_id $user_id: " . json_encode($debug_data));

    // SECOND: Check what types exist
    $type_stmt = $pdo->prepare("SELECT DISTINCT type FROM notifications WHERE user_id = ?");
    $type_stmt->execute([$user_id]);
    $types = $type_stmt->fetchAll(PDO::FETCH_COLUMN);
    log_notif_event("Available types for user_id $user_id: " . json_encode($types));

    // THIRD: Check what statuses exist
    $status_stmt = $pdo->prepare("SELECT DISTINCT status FROM notifications WHERE user_id = ?");
    $status_stmt->execute([$user_id]);
    $statuses = $status_stmt->fetchAll(PDO::FETCH_COLUMN);
    log_notif_event("Available statuses for user_id $user_id: " . json_encode($statuses));

    // --- MODIFIED QUERY: More flexible to catch existing data ---
    // Instead of filtering by type, let's get all notifications and classify them
    $stmt_all = $pdo->prepare(
        "SELECT notification_id, user_id, created_by_user_id, message, link, block, zone_name, type, status, due_date, is_read, viewed_at, created_at 
         FROM notifications 
         WHERE user_id = ? 
         ORDER BY created_at DESC"
    );
    $stmt_all->execute([$user_id]);
    $all_notifications = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

    log_notif_event("Total notifications found for user_id $user_id: " . count($all_notifications));

    // INITIALIZE ALL ARRAYS BEFORE USING THEM
    $active_tasks = [];
    $archived_notifications = [];
    $notifications = []; // FIX: Initialize this array

    // Separate into active tasks, notifications, and archived notifications
    foreach ($all_notifications as $notif) {
        // Consider as active task if:
        // 1. Has a due_date (is a task)
        // 2. Status is pending or viewed (not completed)
        // 3. OR if type suggests it's a task/reminder

        if ($notif['type'] === 'notification') {
            // Type 'notification' goes to separate notifications section
            $notifications[] = $notif;
        } else if ($notif['status'] === 'completed') {
            // Completed tasks go to archive
            $archived_notifications[] = $notif;
        } else {
            // Everything else (pending, viewed) goes to active tasks
            $active_tasks[] = $notif;
        }
    }

    log_notif_event("Active tasks found: " . count($active_tasks));
    log_notif_event("Notifications found: " . count($notifications));
    log_notif_event("Archived notifications found: " . count($archived_notifications));

    if (count($active_tasks) > 0) {
        log_notif_event("First active task: " . json_encode($active_tasks[0]));
    }

    // --- Get total UNREAD count for the header badge ---
    $total_unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND status = 'pending'");
    $total_unread_stmt->execute([$user_id]);
    $total_unread_count = $total_unread_stmt->fetchColumn();

    log_notif_event("Total unread count: " . $total_unread_count);

    // --- Helper function to group data for the UI ---
    function group_data($items)
    {
        $grouped = [];
        
        // FIX: Check if items is actually an array and not null
        if (!is_array($items)) {
            return $grouped;
        }
        
        foreach ($items as $item) {
            // Make sure we handle null/empty values properly
            $block = !empty($item['block']) ? $item['block'] : 'نامشخص';
            $zone = !empty($item['zone_name']) ? $item['zone_name'] : 'نامشخص';

            if (!isset($grouped[$block])) {
                $grouped[$block] = [];
            }
            if (!isset($grouped[$block][$zone])) {
                $grouped[$block][$zone] = [];
            }
            $grouped[$block][$zone][] = $item;
        }
        return $grouped;
    }

    $response = [
        'total_unread' => (int)$total_unread_count,
        'active_tasks' => group_data($active_tasks),
        'archived_notifications' => group_data($archived_notifications),
        'notifications' => group_data($notifications)
    ];

    // Debug: Log the final response structure
    log_notif_event("Final response structure: " . json_encode([
        'total_unread' => $response['total_unread'],
        'active_tasks_count' => count($active_tasks),
        'notifications_count' => count($notifications),
        'archived_count' => count($archived_notifications),
        'active_tasks_structure' => array_keys($response['active_tasks'])
    ]));

    echo json_encode($response);
} catch (Exception $e) {
    log_notif_event("Error in get_notifications.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}