<?php
// ===================================================================
// START: FINAL ELEMENT MANAGEMENT CONSOLE (WITH COMPLETE HISTORY VIEWER)
// ===================================================================

require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

// --- Helper Functions (Copied from dashboard for consistency) ---
function format_history_action_farsi($action, $role) {
    if ($action === 'Supervisor Action') return 'اقدام مشاور';
    if ($action === 'Contractor Action') return 'اقتام پیمانکار';
    $translations = [
        'request-opening' => 'درخواست بازگشایی', 'approve-opening' => 'تایید درخواست',
        'reject-opening' => 'رد درخواست', 'confirm-opened' => 'تایید باز شدن پانل',
        'verify-opening' => 'تایید نهایی پیش-بازرسی', 'dispute-opening' => 'رد نهایی پیش-بازرسی'
    ];
    return $translations[$action] ?? $action;
}

function format_status_farsi($status) {
    $translations = [
        'OK' => 'تایید شده', 'Repair' => 'نیاز به تعمیر', 'Reject' => 'رد شده',
        'Awaiting Re-inspection' => 'منتظر بازرسی مجدد', 'Pre-Inspection Complete' => 'پیش-بازرسی تکمیل شد',
        'Pending' => 'در انتظار'
    ];
    return $translations[$status] ?? $status;
}

function get_status_badge_class($status) {
    // This function can be simplified if you add more statuses
    $map = ['OK' => 'badge-success', 'Repair' => 'badge-warning', 'Reject' => 'badge-danger'];
    return $map[$status] ?? 'badge-secondary';
}
$element_id = $_GET['element_id'] ?? null;
$part_name = $_GET['part_name'] ?? null;
$hide_layer = $_GET['hide_layer'] ?? null;
if (!$element_id) { die("Element ID is required."); }

$element_info = null;
$all_inspections = [];
$user_map = [];
$calculated_overall_status = 'نامشخص';


try {
    $pdo = getProjectDBConnection('ghom');
    $pdo->exec("SET NAMES 'utf8mb4'");

    // 1. GET ELEMENT INFO
    $stmt_el = $pdo->prepare("SELECT * FROM elements WHERE element_id = ?");
    $stmt_el->execute([$element_id]);
    $element_info = $stmt_el->fetch(PDO::FETCH_ASSOC);

    if ($element_info) {
        // 2. GET ALL INSPECTIONS FOR THIS ELEMENT (AND OPTIONAL PART_NAME)
        $sql_inspections = "SELECT i.*, s.stage as stage_name 
                            FROM inspections i
                            LEFT JOIN inspection_stages s ON i.stage_id = s.stage_id
                            WHERE i.element_id = ?";
        
        $params = [$element_id];
       if (!empty($part_name)) {
    // If a specific part is requested (e.g., ...&part_name=face), filter by it.
    $sql_inspections .= " AND i.part_name = ?";
    $params[] = $part_name;
} elseif ($element_info['element_type'] === 'GFRC') {
    // If it's a GFRC element and NO specific part is requested, get records for ALL parts.
    // We do this by not adding any part_name filter to the query.
} else {
    // For other types, get records where part_name is NULL OR the default value.
    $sql_inspections .= " AND (i.part_name IS NULL OR i.part_name = 'default')";
}
        $sql_inspections .= " ORDER BY i.created_at ASC";
        
        $stmt_inspections = $pdo->prepare($sql_inspections);
        $stmt_inspections->execute($params);
        $all_inspections = $stmt_inspections->fetchAll(PDO::FETCH_ASSOC);
$pre_inspection_record = null;
$main_inspection_records = [];
foreach ($all_inspections as $insp) {
    if ($insp['stage_id'] == 0) {
        // For pre-inspection, we only care about the first one that has a log.
        if (!$pre_inspection_record && !empty($insp['pre_inspection_log'])) {
            $pre_inspection_record = $insp;
        }
    } else {
        // Collect ALL main inspection records.
        $main_inspection_records[] = $insp;
    }
}
        // 3. DETERMINE OVERALL STATUS (WITH DEFAULT)
        if (!empty($element_info['final_status'])) {
            // If a final status is officially set, it takes priority.
            $calculated_overall_status = format_status_farsi($element_info['final_status']);
        } elseif (empty($all_inspections)) {
            // If there are NO inspections at all, set a clear default.
            $calculated_overall_status = 'در انتظار';
        } else {
            // Fallback to a calculated status if no official one is set
            $calculated_overall_status = 'در حال بازرسی';
        }

        // 4. GET ALL USER INFO FOR LOGS
        $all_user_ids = [];
        foreach ($all_inspections as $inspection) {
            $pre_log = json_decode($inspection['pre_inspection_log'] ?? '[]', true);
            $history_log = json_decode($inspection['history_log'] ?? '[]', true);
            $logs = array_merge(is_array($pre_log) ? $pre_log : [], is_array($history_log) ? $history_log : []);
            
            foreach ($logs as $log) {
                if (!empty($log['user_id'])) {
                    $all_user_ids[$log['user_id']] = true;
                }
            }
        }

        if (!empty($all_user_ids)) {
            $common_pdo = getCommonDBConnection();
            $user_ids_list = array_keys($all_user_ids);
            $placeholders = implode(',', array_fill(0, count($user_ids_list), '?'));
            $user_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id IN ($placeholders)";
            $user_stmt = $common_pdo->prepare($user_sql);
            $user_stmt->execute($user_ids_list);
            $user_map = $user_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = "کنسول مدیریت المان: " . escapeHtml($element_id);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>سابقه بازرسی: <?php echo escapeHtml($element_id); ?></title>
    <link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    <style>
        /* --- NEW, PROFESSIONAL CONSOLE STYLES --- */
        @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2");
        }

        body {
            font-family: "Samim", sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 20px;
        }

        .console-container {
    display: flex;
    flex-direction: row;
    gap: 15px;
    max-width: none; /* Remove max-width restriction */
    margin: 0;
    padding: 0 10px;
    align-items: flex-start;
    height: calc(100vh - 40px);
}

/* History column - much smaller on desktop */
.history-column {
    flex: 0 0 300px; /* Fixed width, much smaller */
    min-width: 280px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
}

/* Form column - removed since you don't seem to have active forms */
.form-column {
    flex: 1;
    position: sticky;
    top: 20px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
}

/* Viewer column - takes most of the space */
.viewer-column {
    flex: 1; /* Takes remaining space */
    min-width: 0; /* Allow shrinking */
    height: calc(100vh - 40px);
    position: relative;
}

.viewer-column iframe {
    width: 100%;
    height: 100%;
    border: 1px solid #ccc;
    border-radius: 8px;
}

/* Mobile Responsive Design */
@media screen and (max-width: 1200px) {
    .console-container {
        flex-direction: column;
        height: auto;
        padding: 0 5px;
    }
    
    .history-column {
        flex: none;
        width: 100%;
        max-height: 40vh;
        margin-bottom: 10px;
        order: 2; /* Move history below viewer on mobile */
    }
    
    .viewer-column {
        flex: none;
        width: 100%;
        height: 60vh; /* Give more height to viewer on mobile */
        order: 1; /* Viewer appears first on mobile */
    }
}

@media screen and (max-width: 768px) {
    body {
        padding: 10px 5px;
    }
    
    .console-container {
        gap: 10px;
    }
    
    .history-column {
        max-height: 30vh; /* Even less space for history on small screens */
    }
    
    .viewer-column {
        height: 70vh; /* More space for viewer on mobile */
    }
    
    /* Make panels more mobile-friendly */
    .panel {
        padding: 15px;
        margin-bottom: 10px;
    }
    
    /* Compress element info on mobile */
    .element-info {
        flex-direction: column;
        gap: 8px;
    }
    
    .element-info span {
        font-size: 0.9em;
    }
    
    /* Make final status panel more compact */
    .final-status-panel {
        padding: 15px;
    }
    
    .final-status-panel h2 {
        font-size: 1.2em;
        margin-bottom: 15px;
    }
    
    /* Compress history details */
    .history-details {
        padding: 12px;
    }
    
    .history-details h3 {
        font-size: 1.1em;
        margin-bottom: 10px;
    }
    
    .history-details h4 {
        font-size: 1em;
        margin-bottom: 8px;
    }
    
    /* Stack history columns on mobile */
    .history-details > div[style*="display: flex"] {
        flex-direction: column !important;
        gap: 15px !important;
    }
    
    .history-details > div > div {
        min-width: auto !important;
    }
    
    /* Make timeline more compact */
    .history-timeline {
        padding-right: 15px;
    }
    
    .history-timeline li {
        margin-bottom: 10px;
    }
    
    .history-content {
        padding: 8px;
        font-size: 0.9em;
    }
    
    .history-meta {
        font-size: 0.75em;
    }
    
    /* Better button spacing on mobile */
    .btn-back {
        margin-bottom: 15px;
        padding: 10px 15px;
    }
}

/* Tablet-specific adjustments */
@media screen and (min-width: 769px) and (max-width: 1199px) {
    .console-container {
        flex-direction: row;
        gap: 12px;
    }
    
    .history-column {
        flex: 0 0 320px;
        min-width: 300px;
    }
    
    .viewer-column {
        flex: 1;
        height: calc(100vh - 40px);
    }
}

/* Ultra-wide screen optimization */
@media screen and (min-width: 1400px) {
    .console-container {
        gap: 20px;
    }
    
    .history-column {
        flex: 0 0 350px; /* Slightly wider on large screens */
        min-width: 330px;
    }
}

/* Landscape mobile optimization */
@media screen and (max-height: 500px) and (orientation: landscape) {
    .console-container {
        flex-direction: row;
        height: calc(100vh - 20px);
    }
    
    .history-column {
        flex: 0 0 280px;
        max-height: calc(100vh - 20px);
        order: 1;
    }
    
    .viewer-column {
        flex: 1;
        height: calc(100vh - 20px);
        order: 2;
    }
    
    body {
        padding: 10px 5px;
    }
}

        .panel {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
        }

        h1,
        h2 {
            color: #1d2129;
        }

        h1 {
            margin-top: 0;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        h2 {
            font-size: 1.4em;
            color: #0056b3;
            margin-bottom: 20px;
        }

        .element-info {
    background: #e9f5ff;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    display: flex;
    flex-direction: column; /* <-- ADD THIS LINE */
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    border: 1px solid #bce0fd;
}

        .element-info span {
            font-size: 0.95em;
        }

        details {
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            overflow: hidden;
        }

        summary {
            cursor: pointer;
            padding: 12px 15px;
            background-color: #f8f9fa;
            list-style: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        summary:hover {
            background-color: #e9ecef;
        }

        .history-content {
            padding: 15px;
            border-top: 1px solid #ddd;
        }

        /* Button Styles */
        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            color: white;
            font-family: inherit;
            font-size: 0.9em;
            transition: background-color 0.2s;
        }

        .btn-load {
            background-color: #007bff;
        }

        .btn-load:hover {
            background-color: #0069d9;
        }

        .btn-save {
            background-color: #28a745;
        }

        .btn-new {
            background-color: #6c757d;
        }

        .btn-back {
            background-color: #5a6268;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }

        #form-container {
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            padding-right: 15px;
        }

        /* Form Element Styles (As seen in your screenshot) */
        .form-stage {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .stage-title {
            font-size: 1.2em;
            color: #0d6efd;
            margin-bottom: 18px;
            padding-right: 12px;
            border-right: 4px solid #0d6efd;
            font-weight: bold;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 0.95em;
            color: #495057;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #fff;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 .2rem rgba(0, 123, 255, .25);
        }

        .checklist-item-row {
            margin-bottom: 20px;
        }

        .checklist-item-row .item-text {
            font-weight: bold;
            margin-bottom: 10px;
            display: block;
        }

        .status-selector {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 20px;
            margin-bottom: 8px;
        }

        .status-selector label {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        fieldset {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        legend {
            font-weight: bold;
            color: #333;
            padding: 0 10px;
        }

        .final-status-panel {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .final-status-panel h2 {
            color: #856404;
            border-color: #856404;
        }

        .readonly-field {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            border: 1px solid #ced4da;
        }

        .readonly-field strong {
            color: #0056b3;
        }

        .history-viewer {
            max-height: 80vh;
            overflow-y: auto;
        }

        .history-details {
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }

        .history-details h3 {
            margin-top: 0;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }

        .history-timeline {
            list-style: none;
            padding-right: 20px;
            position: relative;
        }

        .history-timeline:before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            right: 5px;
            width: 2px;
            background: #ddd;
        }

        .history-timeline li {
            margin-bottom: 15px;
            position: relative;
        }

        .history-timeline li:before {
            content: '';
            position: absolute;
            top: 8px;
            right: -5px;
            width: 12px;
            height: 12px;
            background: #fff;
            border: 2px solid #3498db;
            border-radius: 50%;
            z-index: 1;
        }

        .history-meta {
            font-size: 0.8em;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .history-content {
            background: #fff;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #eee;
        }

        .badge-success {
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8em;
        }

        .badge-warning {
            background-color: #ffc107;
            color: black;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8em;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8em;
        }

        .badge-secondary {
            background-color: #6c757d;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8em;
        }

       .viewer-column {
    position: relative;
    background: #f8f9fa;
    border-radius: 8px;
    overflow: hidden;
}

.viewer-column::before {
    content: "نمایشگر نقشه";
    position: absolute;
    top: 8px;
    right: 15px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8em;
    z-index: 1;
}

/* Scrollbar improvements for history column */
.history-column::-webkit-scrollbar {
    width: 6px;
}

.history-column::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.history-column::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.history-column::-webkit-scrollbar-thumb:hover {
    background: #555;
}
    </style>
</head>

<body data-user-role="<?php echo escapeHtml($_SESSION['role']); ?>">
    <div class="console-container">
        <div class="history-column panel">
            <a href="/ghom/inspection_dashboard.php" class="btn btn-back">بازگشت به داشبورد</a>
            <h1>کنسول مدیریت المان</h1>
            <?php if ($element_info): ?>
                <div class="element-info">
    <div>
        <strong>کد:</strong> <?php echo escapeHtml($element_info['element_id']); ?>
        <?php if (!empty($part_name)): ?>
            (<?php echo escapeHtml($part_name); ?>)
        <?php endif; ?>
    </div>
    <div><strong>نوع:</strong> <?php echo escapeHtml($element_info['element_type']); ?></div>
    <div><strong>پیمانکار:</strong> <?php echo escapeHtml($element_info['contractor']); ?></div>
    <div style="font-weight:bold;">وضعیت کلی فعلی: <span class="<?php echo get_status_badge_class($calculated_overall_status); ?>"><?php echo escapeHtml($calculated_overall_status); ?></span></div>
</div>
                <!-- ====================================================== -->
                <!-- NEW: FINAL STATUS MANAGEMENT PANEL -->
                <!-- ====================================================== -->
                <div class="final-status-panel">
                    <h2>وضعیت نهایی کل المان</h2>
                    <form id="final-status-form">
                        <input type="hidden" name="element_id" value="<?php echo escapeHtml($element_id); ?>">
                        <div class="form-group">
                            <label for="final_status">ثبت / تغییر وضعیت نهایی:</label>
                            <select name="final_status" id="final_status" <?php echo !in_array($_SESSION['role'], ['admin', 'superuser']) ? 'disabled' : ''; ?>>
                                <option value="" <?php echo is_null($element_info['final_status']) ? 'selected' : ''; ?>>-- انتخاب نشده --</option>
                                <option value="Installation Complete" <?php echo $element_info['final_status'] === 'Installation Complete' ? 'selected' : ''; ?>>نصب تکمیل شد</option>
                                <option value="Final QA Passed" <?php echo $element_info['final_status'] === 'Final QA Passed' ? 'selected' : ''; ?>>کنترل کیفیت نهایی تایید شد</option>
                                <option value="Needs Replacement" <?php echo $element_info['final_status'] === 'Needs Replacement' ? 'selected' : ''; ?>>نیاز به جایگزینی</option>
                                <option value="On Hold" <?php echo $element_info['final_status'] === 'On Hold' ? 'selected' : ''; ?>>در حالت تعلیق</option>
                            </select>
                        </div>
                        <?php if (in_array($_SESSION['role'], ['admin', 'superuser'])): ?>
                            <button type="submit" class="btn btn-save">ذخیره وضعیت نهایی</button>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="history-details">
                    <h3>تاریخچه کامل </h3>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">

                        <!-- Pre-Inspection History Column -->
                        <div style="flex: 1; min-width: 400px;">
    <h4>۱. تاریخچه پیشا-بازرسی</h4>
    <ul class="history-timeline">
        <?php
        // CRITICAL FIX: Use the $pre_inspection_record variable your code already creates.
        if (!empty($pre_inspection_record) && !empty($pre_inspection_record['pre_inspection_log'])) {
            $pre_log_items = json_decode($pre_inspection_record['pre_inspection_log'], true);
            if (is_array($pre_log_items) && !empty($pre_log_items)) {
                foreach ($pre_log_items as $log) {
                    $user_name = $user_map[$log['user_id']] ?? 'کاربر ناشناس';
                    echo '<li>';
                    echo '<div class="history-meta">' . jdate('Y/m/d H:i:s', strtotime($log['timestamp'])) . ' - توسط: ' . escapeHtml($user_name) . '</div>';
                    echo '<div class="history-content">';
                    echo '<strong>' . format_history_action_farsi($log['action'], $log['role']) . '</strong>';
                    if (!empty($log['notes'])) echo '<br><small>یادداشت: ' . escapeHtml($log['notes']) . '</small>';
                    echo '</div></li>';
                }
            } else {
                echo "<li><div class='history-content'>موردی یافت نشد.</div></li>";
            }
        } else {
            echo "<li><div class='history-content'>موردی یافت نشد.</div></li>";
        }
        ?>
    </ul>
</div>

                        <!-- Main Inspection History Column -->
                        <div style="flex: 2; min-width: 500px;">
                            <h4>۲. تاریخچه بازرسی مراحل</h4>
                             <?php
                        // Group history_log entries by stage
                        $stages_with_history = [];
                        foreach ($all_inspections as $inspection) {
                            if ($inspection['stage_id'] > 0 && !empty($inspection['history_log'])) {
                                $stages_with_history[$inspection['stage_id']]['name'] = $inspection['stage_name'];
                                $stages_with_history[$inspection['stage_id']]['logs'] = json_decode($inspection['history_log'], true);
                            }
                        }

                        if (empty($stages_with_history)): ?>
                            <ul class="history-timeline"><li><div class='history-content'>موردی یافت نشد.</div></li></ul>
                        <?php else: ?>
                            <?php foreach ($stages_with_history as $stage_id => $stage_data): ?>
                                <details open>
                                    <summary>
                                        <strong>مرحله: <?php echo escapeHtml($stage_data['name']); ?></strong>
                                    </summary>
                                    <div class="history-content">
                                        <?php
                                        // --- Group logs into inspection cycles ---
                                        $cycles = [];
                                        $current_cycle = [];
                                        foreach (array_reverse($stage_data['logs']) as $log) {
                                            $current_cycle[] = $log;
                                            if ($log['action'] === 'Supervisor Action') {
                                                $cycles[] = array_reverse($current_cycle);
                                                $current_cycle = [];
                                            }
                                        }
                                        if (!empty($current_cycle)) $cycles[] = array_reverse($current_cycle);
                                        $cycles = array_reverse($cycles);
                                        ?>

                                        <?php foreach ($cycles as $index => $cycle): ?>
                                            <fieldset style="margin-top: 15px;">
                                                <legend>سیکل بازرسی #<?php echo $index + 1; ?></legend>
                                                <ul class="history-timeline">
                                                <?php foreach ($cycle as $log): 
                                                    $user_name = $user_map[$log['user_id']] ?? 'کاربر ناشناس';
                                                    $status_key = $log['data']['overall_status'] ?? ($log['data']['contractor_status'] ?? null);
                                                ?>
                                                    <li>
                                                        <div class="history-meta"><?php echo jdate('Y/m/d H:i:s', strtotime($log['timestamp'])); ?> - توسط: <?php echo escapeHtml($user_name); ?></div>
                                                        <div class="history-content">
                                                            <strong><?php echo format_history_action_farsi($log['action'], $log['role']); ?></strong>
                                                            <?php if ($status_key): ?>
                                                                | وضعیت: <span class="<?php echo get_status_badge_class($status_key); ?>"><?php echo format_status_farsi($status_key); ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($log['data']['notes'])) echo '<br><small>یادداشت مشاور: ' . escapeHtml($log['data']['notes']) . '</small>'; ?>
                                                            <?php if (!empty($log['data']['contractor_notes'])) echo '<br><small>یادداشت پیمانکار: ' . escapeHtml($log['data']['contractor_notes']) . '</small>'; ?>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                                </ul>
                                            </fieldset>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    </div>
                </div>


            <?php else: ?>
                <p>المان مورد نظر یافت نشد.</p>
            <?php endif; ?>
        </div>
        <div class="viewer-column panel">
            <?php if ($element_info): ?>
              <?php
// Base URL for the iframe
$viewer_url = "viewer_column.php?plan=" . urlencode($element_info['plan_file']) . "&highlight_element=" . urlencode($element_info['element_id']);

// ===================================================================
// START: CRITICAL FIX FOR GFRC RAINBOW EFFECT
// ===================================================================
if ($element_info && $element_info['element_type'] === 'GFRC' && empty($part_name)) {

    // Helper function to map a status to a color code
    function get_color_for_status($status) {
        $colors = [
            'OK' => '28a745', 'Reject' => 'dc3545', 'Repair' => '9c27b0',
            'Pre-Inspection Complete' => 'ff8c00', 'Awaiting Re-inspection' => '00bfff',
            'In Progress' => '0dcaf0', 'Pending' => 'cccccc'
        ];
        return $colors[$status] ?? $colors['Pending'];
    }

    // This corrected query finds the LATEST status for each part of the GFRC element.
    $parts_stmt = $pdo->prepare("
        WITH LatestPartInspections AS (
            SELECT
                i.part_name,
                i.status,
                i.overall_status,
                ROW_NUMBER() OVER(PARTITION BY i.part_name ORDER BY i.created_at DESC, i.inspection_id DESC) as rn
            FROM inspections i
            WHERE i.element_id = ? AND i.part_name IS NOT NULL
        )
        SELECT
            lpi.part_name,
            CASE 
                WHEN lpi.overall_status = 'OK' THEN 'OK'
                WHEN lpi.status = 'Reject' THEN 'Reject'
                WHEN lpi.status = 'Awaiting Re-inspection' THEN 'Awaiting Re-inspection'
                WHEN lpi.status = 'Repair' OR lpi.overall_status = 'Repair' THEN 'Repair'
                WHEN lpi.status = 'Pre-Inspection Complete' THEN 'Pre-Inspection Complete'
                ELSE 'Pending'
            END as final_status
        FROM LatestPartInspections lpi
        WHERE lpi.rn = 1
        ORDER BY lpi.part_name
    ");
    $parts_stmt->execute([$element_id]);
    $gfrc_parts_summary = $parts_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($gfrc_parts_summary)) {
        $color_array = [];
        foreach ($gfrc_parts_summary as $part) {
            // Use the 'final_status' column returned by our new query
            $color_array[] = get_color_for_status($part['final_status']);
        }
        // Pass the colors as a comma-separated string in the URL
        if (!empty($color_array)) {
             $viewer_url .= "&gradient_colors=" . implode(',', $color_array);
        }
    }
}
// ===================================================================
// END: CRITICAL FIX
// ===================================================================
?>
                <iframe src="<?php echo $viewer_url; ?>" title="نمایشگر نقشه"></iframe>
                
            <?php else: ?>
                <p>نقشه‌ای برای نمایش وجود ندارد.</p>
            <?php endif; ?>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const finalStatusForm = document.getElementById('final-status-form');
            if (finalStatusForm) {
                finalStatusForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const saveBtn = this.querySelector('.btn-save');
                    saveBtn.textContent = 'در حال ذخیره...';
                    saveBtn.disabled = true;

                    try {
                        const formData = new FormData(this);
                        // شما باید یک API جدید برای این کار ایجاد کنید
                        const response = await fetch('/ghom/api/update_element_final_status.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (response.ok && result.status === 'success') {
                            alert(result.message);
                        } else {
                            throw new Error(result.message || 'خطای ناشناخته از سرور');
                        }
                    } catch (error) {
                        alert('خطا در ذخیره‌سازی: ' + error.message);
                    } finally {
                        saveBtn.textContent = 'ذخیره وضعیت نهایی';
                        saveBtn.disabled = false;
                    }
                });
            }
        });
    </script>
</body>

</html>