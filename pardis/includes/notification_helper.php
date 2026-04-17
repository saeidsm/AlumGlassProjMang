<?php
// ===================================================================
// NOTIFICATION & CALENDAR EVENT HELPER (FINAL PROFESSIONAL VERSION)
//public_html/pardis/includes/notification_helper.php
// ===================================================================

if (function_exists('trigger_workflow_task')) {
    return; // Prevent function redeclaration
}

/**
 * A dedicated logging function for the notification/task system.
 *
 * @param string $message The message to log.
 */
function log_task_event($message)
{
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', dirname(__DIR__, 4));
    }
    $log_dir = APP_ROOT . '/logs/tasks';
    if (!file_exists(dirname($log_dir))) {
        mkdir(dirname($log_dir), 0755, true);
    }
    $log_file = $log_dir . '/task_log_' . date("Y-m-d") . '.log';
    $timestamp = date("Y-m-d H:i:s");
    $formatted_message = is_array($message) || is_object($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message;
    file_put_contents($log_file, "[$timestamp] " . $formatted_message . "\n", FILE_APPEND);
}

/**
 * Creates notifications, tasks, and calendar events based on a workflow action.
 */
function trigger_workflow_task(
    PDO $pdo,
    $element_id_or_group_info,
    ?string $part_name,
    string $plan_file,
    string $event_type,
    int $actor_user_id,
    ?string $event_date,
    ?string $notes = '',
    ?int $stage_id = null,
    ?string $stage_name = null
) {
    log_task_event("--- Task Creation Triggered ---");
    log_task_event("Event Type: '{$event_type}', Actor ID: {$actor_user_id}");
    // -----------------------------------------------------------------
    // ۱. آماده‌سازی داده‌های اولیه
    // -----------------------------------------------------------------
    $is_batch_mode = is_array($element_id_or_group_info);
    $common_pdo = getCommonDBConnection();

    $element_details = null;
    $link_element_ids_string = '';
    $display_name = '';
    $base_element_id_for_history = '';

    if ($is_batch_mode) {
        $group_info = $element_id_or_group_info;
        $element_details = $group_info['sample_element_details'];
        $link_element_ids_string = $group_info['all_ids_string'];
        $base_element_id_for_history = $element_details['element_id'];

        $zone_summary = [];
        foreach ($group_info['by_zone'] as $zone => $count) {
            $zone_summary[] = "{$zone} ({$count} بخش)";
        }
        $display_name = "مجموع {$group_info['total_count']} بخش در: " . implode('، ', $zone_summary);
    } else { // Single mode
        $element_id = $element_id_or_group_info;
        $display_name = $element_id . ($part_name ? ' - ' . $part_name : '');
        $link_element_ids_string = $display_name;
        $base_element_id_for_history = $element_id;

        $stmt_element_details = $pdo->prepare("SELECT contractor, block, zone_name FROM elements WHERE element_id = ?");
        $stmt_element_details->execute([$element_id]);
        $element_details = $stmt_element_details->fetch(PDO::FETCH_ASSOC);
    }


    // Logging: Start of function

    if (!$element_details) {
        log_task_event("CRITICAL ERROR: Could not find element details. Aborting.");
        return;
    }

    // Log element details
 if ($stage_id === 0) {
        $stage_display_name = "مراحل پیش-بازرسی";
    } else if (!empty($stage_name)) {
        $stage_display_name = "مرحله: {$stage_name}";
    } else {
        $stage_display_name = ''; // No stage info to display
    }
    
    // Append stage info to the main display name if it exists
    $display_name_with_stage = $display_name . ($stage_display_name ? " ({$stage_display_name})" : '');
    $block = $element_details['block'] ?? null;
    $zone_name = $element_details['zone_name'] ?? null;
    $contractor_name = $element_details['contractor'] ?? null;
   
  
    // پیدا کردن نام کاربر عامل
    $stmt_actor = $common_pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
    $stmt_actor->execute([$actor_user_id]);
    $actor_name = $stmt_actor->fetchColumn() ?: "سیستم";
    log_task_event("Processing for Display Name: '{$display_name}', Contractor: '{$contractor_name}'");

    // -----------------------------------------------------------------
    // ۲. پیدا کردن گیرندگان پیام
    // -----------------------------------------------------------------
    $contractor_name = $element_details['contractor'];
    $contractor_role_map = ['شرکت آتیه نما' => 'cat', 'شرکت آرانسج' => 'car', 'شرکت عمران آذرستان' => 'coa', 'شرکت ساختمانی رس' => 'crs'];
    $target_role = $contractor_role_map[$contractor_name] ?? null;

    $recipients_list = [];
    if ($target_role) {
        $stmt_contractors = $common_pdo->prepare("SELECT id FROM users WHERE role = ?");
        $stmt_contractors->execute([$target_role]);
        $recipients_list['contractor'] = $stmt_contractors->fetchAll(PDO::FETCH_COLUMN);
    }
    $stmt_admins = $common_pdo->prepare("SELECT id FROM users WHERE role IN ('admin')");
    $stmt_admins->execute();
    $recipients_list['admin'] = $stmt_admins->fetchAll(PDO::FETCH_COLUMN);
    $stmt_superusers = $common_pdo->prepare("SELECT id FROM users WHERE role = 'superuser'");
    $stmt_superusers->execute();
    $recipients_list['superuser'] = $stmt_superusers->fetchAll(PDO::FETCH_COLUMN);
    log_task_event("Recipients Found: " . json_encode($recipients_list));
    // -----------------------------------------------------------------
    // ۳. تعریف قالب پیام‌ها و رویدادها
    // -----------------------------------------------------------------
    $task_data = null;
    $event_date = $event_date ?: date('Y-m-d');
    $notes_text = empty(trim($notes)) ? '' : "\n\n<b>یادداشت:</b><i> \"{$notes}\"</i>";
    $due_date = (new DateTime())->modify('+3 day')->format('Y-m-d');

    // نگاشت لینک‌ها برای هدایت کاربران

       switch ($event_type) {
        // --- وظایف برای مشاور ---
        case 'OPENING_REQUESTED':
            $task_data = ['recipients' => $recipients_list['admin'], 'type' => 'task', 'status' => 'pending', 'due_date' => $due_date, 'event_title' => "وظیفه: تایید بازگشایی {$display_name_with_stage}", 'message' => "<b>{$actor_name}</b> درخواست بازگشایی برای <b>{$display_name_with_stage}</b> ثبت کرد.{$notes_text}", 'event_color' => '#6f42c1'];
            break;
        case 'PANEL_OPENED':
            $task_data = ['recipients' => $recipients_list['admin'], 'type' => 'task', 'status' => 'pending', 'due_date' => $due_date, 'event_title' => "وظیفه: بازبینی پانل باز شده {$display_name_with_stage}", 'message' => "<b>{$actor_name}</b> بازگشایی فیزیکی <b>{$display_name_with_stage}</b> را انجام داد. لطفاً بازبینی نهایی کنید.{$notes_text}", 'event_color' => '#20c997'];
            break;
        case 'INSPECTION_READY': // **NEW** For the VERY FIRST inspection of a stage
            $task_data = [
                'recipients' => $recipients_list['admin'], 'type' => 'task', 'status' => 'pending', 'due_date' => $due_date,
                'event_title' => "وظیفه: بازرسی اولیه {$display_name_with_stage}",
                'message' => "<b>{$actor_name}</b> آمادگی المان <b>{$display_name_with_stage}</b> را برای بازرسی اولیه اعلام کرد.{$notes_text}",
                'event_color' => '#17A2B8' // Cyan
            ];
        break;
       case 'REPAIR_DONE': // **MODIFIED** Now ONLY for re-inspection after a repair
        $task_data = [
            'recipients' => $recipients_list['admin'], 'type' => 'task', 'status' => 'pending', 'due_date' => $due_date,
            'event_title' => "وظیفه: بازرسی مجدد {$display_name_with_stage}",
            'message' => "<b>{$actor_name}</b> اتمام تعمیر المان <b>{$display_name_with_stage}</b> را اعلام کرد. لطفاً بازرسی مجدد کنید.{$notes_text}",
            'event_color' => '#20c997' // Teal
        ];
        break;

        // --- وظایف برای پیمانکار ---
        case 'OPENING_APPROVED':
            $task_data = ['recipients' => $recipients_list['contractor'], 'type' => 'task', 'status' => 'pending', 'due_date' => $due_date, 'event_title' => "وظیفه: باز کردن پانل {$display_name_with_stage}", 'message' => "<b>{$actor_name}</b> درخواست بازگشایی <b>{$display_name_with_stage}</b> را تایید کرد. لطفاً پانل را باز کنید.{$notes_text}", 'event_color' => '#fd7e14'];
            break;
        case 'REPAIR_REQUESTED':
            $task_data = ['recipients' => $recipients_list['contractor'], 'type' => 'task', 'status' => 'pending', 'due_date' => $due_date, 'event_title' => "وظیفه: تعمیر {$display_name_with_stage}", 'message' => "<b>{$actor_name}</b> درخواست تعمیر برای <b>{$display_name_with_stage}</b> ثبت کرد.{$notes_text}", 'event_color' => '#FFC107'];
            break;
        case 'OPENING_REJECTED':
             $task_data = ['recipients' => $recipients_list['contractor'], 'type' => 'task', 'status' => 'pending', 'due_date' => $due_date, 'event_title' => "وظیفه: اصلاح درخواست بازگشایی {$display_name_with_stage}", 'message' => "<b>{$actor_name}</b> درخواست بازگشایی <b>{$display_name_with_stage}</b> را رد کرد. لطفاً مشکل را برطرف و مجدداً درخواست دهید.{$notes_text}", 'event_color' => '#6c757d'];
            break;
        case 'OPENING_DISPUTED':
            $task_data = ['recipients' => $recipients_list['contractor'], 'type' => 'task', 'status' => 'pending', 'due_date' => $due_date, 'event_title' => "وظیفه: اصلاح پانل باز شده {$display_name_with_stage}", 'message' => "<b>{$actor_name}</b> بازگشایی پانل <b>{$display_name_with_stage}</b> را رد کرد. لطفاً مشکل را برطرف کنید.{$notes_text}", 'event_color' => '#dc3545'];
            break;
        case 'INSPECTION_REJECT':
            $task_data = ['recipients' => $recipients_list['contractor'], 'type' => 'task', 'status' => 'pending', 'due_date' => $due_date, 'event_title' => "وظیفه: اصلاح ایرادات {$display_name_with_stage}", 'message' => "<b>{$actor_name}</b> بازرسی <b>{$display_name_with_stage}</b> را رد کرد. لطفاً ایرادات را برطرف کنید.{$notes_text}", 'event_color' => '#DC3545'];
            break;

        // --- اعلان‌های ساده (فقط اطلاع‌رسانی) ---
        case 'PRE_INSPECTION_COMPLETE':
        case 'INSPECTION_OK':
            $task_data = [
                'recipients' => array_merge($recipients_list['contractor'] ?? [], $recipients_list['admin'] ?? []), // اطلاع به هر دو
                'type' => 'notification', 
                'status' => 'pending', 'due_date' => null,
                'event_title' => "اطلاع: " . ($event_type === 'INSPECTION_OK' ? "تایید نهایی {$display_name_with_stage}" : "تکمیل پیش-بازرسی {$display_name_with_stage}"),
                'message' => "<b>{$actor_name}</b> فرآیند مربوط به <b>{$display_name_with_stage}</b> را با موفقیت به اتمام رساند.",
                'event_color' => '#28A745'
            ];
            break;
    }

    if (!$task_data) {
        log_task_event("No task/notification definition found for event type '{$event_type}'. Exiting.");
        return;
    }

    log_task_event("Task Data Prepared: " . json_encode($task_data));

    // -----------------------------------------------------------------
    // ۴. درج داده‌ها در دیتابیس
    // -----------------------------------------------------------------
   $final_recipients = array_unique(array_merge($task_data['recipients'], $recipients_list['superuser'] ?? []));
    
    // --- START OF CRITICAL FIX ---
    // افزودن `actor_user_id` به لیست نهایی گیرندگان برای ثبت سابقه
    if ($actor_user_id != 0) {
        $final_recipients[] = $actor_user_id;
        $final_recipients = array_unique($final_recipients);
    }
  log_task_event("Preparing to insert notifications/events for " . count($final_recipients) . " recipients.");
// Prepare the statement for notifications (9 columns/placeholders)
  $stmt_notification = $pdo->prepare(
        "INSERT INTO notifications (user_id, created_by_user_id, link, message, `type`, `status`, due_date, event_type, block, zone_name, stage_id) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)" // 11 placeholders
    );
// Prepare the statement for calendar events (6 columns/placeholders)
 $stmt_event = $pdo->prepare(
        "INSERT INTO calendar_events (user_id, element_id, title, start_date, color, related_link) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );

// This loop correctly creates one notification AND one event for each user.
foreach ($final_recipients as $user_id) {
    if ($user_id == 0) continue;

    $stmt_role = $common_pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role->execute([$user_id]);
    $recipient_role = $stmt_role->fetchColumn();

    $final_link = '';
    

    if (in_array($recipient_role, ['superuser', 'user'])) {
        $final_link = sprintf("/pardis/view_element_history.php?element_id=%s&part_name=%s", urlencode($base_element_id_for_history), urlencode($is_batch_mode ? '' : ($part_name ?? '')));
    } else {
        $is_pre_inspection_event = in_array($event_type, ['OPENING_REQUESTED', 'OPENING_APPROVED', 'OPENING_REJECTED', 'PANEL_OPENED', 'PRE_INSPECTION_COMPLETE', 'OPENING_DISPUTED']);
        $target_page = $is_pre_inspection_event ? '/pardis/contractor_batch_update.php' : '/pardis/index.php';
        $final_link = sprintf("%s?plan=%s&element_id=%s", $target_page, urlencode($plan_file), urlencode($link_element_ids_string));
    }

     $is_for_actor = ($user_id == $actor_user_id);
        $current_type = $is_for_actor ? 'notification' : $task_data['type'];
        $current_status = $is_for_actor ? 'viewed' : $task_data['status'];
        // --- END IMPROVEMENT ---

        log_task_event("Executing insert for user ID {$user_id}. Type: {$current_type}, Status: {$current_status}");

        // --- FIX: اجرای کوئری اعلان با ۱۰ پارامتر ---
        $stmt_notification->execute([
            $user_id,
            $actor_user_id,
            $final_link,
            $task_data['message'],
            $current_type,
            $current_status,
            ($current_type === 'task' ? $task_data['due_date'] : null),
            $event_type,
            $block,
            $zone_name,
            $stage_id // <-- پارامتر جدید و مهم
        ]);

        // --- FIX: اجرای کوئری تقویم با ۶ پارامتر ---
        if (isset($task_data['event_title']) && isset($task_data['event_color'])) {
            $element_id_for_event = $is_batch_mode ? $base_element_id_for_history : $element_id_or_group_info;
            $stmt_event->execute([
                $user_id,
                $element_id_for_event,
                $task_data['event_title'],
                $event_date,
                $task_data['event_color'],
                $final_link
            ]);
        }
    }
    log_task_event("--- Task Creation Finished Successfully ---");
}

