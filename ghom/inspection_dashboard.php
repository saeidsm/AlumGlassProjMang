<?php
// ===================================================================
// ghom/inspection_dashboard.php
// FIXED PROFESSIONAL PHP FOR STAGE-BASED DASHBOARD
// ===================================================================

// ------------------- MOBILE CHECK -------------------
function isMobileDevice(): bool {
    return isset($_SERVER["HTTP_USER_AGENT"]) && preg_match(
        "/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i",
        $_SERVER["HTTP_USER_AGENT"]
    );
}

if (isMobileDevice()) {
    header('Location: inspection_dashboard_mobile.php');
    exit();
}

// ------------------- BOOTSTRAP & LIBRARIES -------------------
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

// ------------------- HELPERS -------------------
function format_history_action_farsi(string $action): string {
    $translations = [
        'request-opening'   => 'درخواست بازگشایی',
        'approve-opening'   => 'تایید بازگشایی',
        'confirm-opened'    => 'تایید باز شدن',
        'verify-opening'    => 'تصدیق باز شدن',
        'Supervisor Action' => 'اقدام مشاور',
        'Contractor Action' => 'اقدام پیمانکار'
    ];
    return $translations[$action] ?? $action;
}

// UPDATED: This function now uses a map of orientations from the database.
function getGFRCParts(string $element_id, array $orientations_map): array {
    $orientation = $orientations_map[$element_id] ?? 'Horizontal'; // Default to Horizontal
    if ($orientation === 'Vertical') {
        return ['right', 'left', 'face'];
    }
    // Default for Horizontal or if orientation is not set
    return ['face', 'up', 'down'];
}


function isGFRCElement($element_type, $element_id) {
    return (strtoupper($element_type) === 'GFRC' ||
            strpos(strtoupper($element_id), 'GC') !== false ||
            strpos(strtoupper($element_id), 'GFRC') !== false);
}

function get_status_badge_class(?string $status): string {
    return match ($status) {
        'OK' => 'badge-success',
        'Repair' => 'badge-warning',
        'Reject', 'Not OK' => 'badge-danger',
        'Awaiting Re-inspection' => 'badge-info',
        default => 'badge-secondary'
    };
}

function get_status_persian(?string $status): string {
    $status_map = [
        'OK' => 'تایید شده',
        'Repair' => 'نیاز به تعمیر',
        'Reject' => 'رد شده',
        'Not OK' => 'رد شده',
        'Awaiting Re-inspection' => 'منتظر بازرسی مجدد',
        'Ready for Inspection' => 'آماده بازرسی مجدد',
        'Pre-Inspection Complete' => 'پیش‌بازرسی کامل',
    ];
    return $status_map[$status] ?? $status ?? 'نامشخص';
}


// ------------------- SESSION & ACCESS -------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

$allowed_roles = ['admin','supervisor','user','superuser','cat','car','coa','crs'];
if (!in_array($_SESSION['role'], $allowed_roles, true)) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

// ------------------- PAGE TITLE -------------------
$pageTitle = "داشبورد جامع بازرسی";
require_once __DIR__ . '/header.php';

// ------------------- VARIABLES -------------------
$user_role        = $_SESSION['role'];
$user_id          = $_SESSION['user_id'];
$is_contractor_role = in_array($user_role, ['cat','car','coa','crs'], true);

// Initialize count arrays
$summary_counts = ['Reject' => 0, 'OK' => 0, 'Repair' => 0, 'Ready for Inspection' => 0];

$user_map         = [];
$all_plan_files   = [];
$all_zones        = [];
$all_blocks       = [];
$all_stages       = [];
$all_contractors  = [];
$all_inspectors   = [];
$all_gfrc_parts   = [];
$db_error_message = null;
$element_orientations = []; // NEW: To store panel orientations
$plan_counts = []; // NEW: To store inspection counts per plan

try {
    // ------------------- DATABASE CONNECTIONS -------------------
    $pdo        = getProjectDBConnection('ghom');
    $common_pdo = getCommonDBConnection();
    $pdo->exec("SET NAMES 'utf8mb4'");

    // ------------------- MAIN QUERY (UPDATED to fetch panel_orientation) -------------------
    $sql = "
        SELECT
            i.inspection_id, i.element_id, i.part_name, i.stage_id, i.user_id,
            i.status, i.overall_status, i.inspection_date, i.notes,
            i.contractor_status, i.contractor_date, i.contractor_notes,
            i.inspection_cycle, i.repair_rejection_count,
            e.element_type, e.plan_file, e.zone_name, e.contractor,
            e.block, e.axis_span, e.floor_level, e.panel_orientation,
            s.stage AS stage_name, s.display_order,
            i.history_log, i.pre_inspection_log,
            i.created_at
        FROM inspections i
        JOIN elements e ON i.element_id = e.element_id
        LEFT JOIN inspection_stages s ON i.stage_id = s.stage_id
    ";

    $params = [];
    if ($is_contractor_role) {
        $user_stmt = $common_pdo->prepare("SELECT company FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user_company = $user_stmt->fetchColumn();

        if ($user_company) {
            $sql .= " WHERE e.contractor = :contractor_company";
            $params[':contractor_company'] = $user_company;
        } else {
            $sql .= " WHERE 1=0";
        }
    }

    $sql .= " ORDER BY i.element_id, i.part_name, i.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_inspection_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ------------------- CREATE MAP of ELEMENT ORIENTATIONS -------------------
    foreach ($all_inspection_data as $row) {
        if (!isset($element_orientations[$row['element_id']])) {
            $element_orientations[$row['element_id']] = $row['panel_orientation'];
        }
    }

    // ------------------- GET ALL POSSIBLE PARTS FOR ELEMENTS -------------------
    $element_parts_sql = "SELECT DISTINCT element_id, part_name FROM inspections ORDER BY element_id, part_name";
    $element_parts_stmt = $pdo->prepare($element_parts_sql);
    $element_parts_stmt->execute();
    $all_element_parts = $element_parts_stmt->fetchAll(PDO::FETCH_ASSOC);

    $element_possible_parts = [];
    foreach ($all_element_parts as $ep) {
        $element_possible_parts[$ep['element_id']][] = $ep['part_name'];
    }

    // ------------------- USER ID COLLECTION & MAP -------------------
    $all_user_ids = [];
    foreach ($all_inspection_data as $row) {
        if (!empty($row['user_id'])) $all_user_ids[$row['user_id']] = true;
        $logs = array_merge(json_decode($row['history_log'] ?? '[]', true), json_decode($row['pre_inspection_log'] ?? '[]', true));
        foreach ($logs as $log) {
            if (!empty($log['user_id'])) $all_user_ids[$log['user_id']] = true;
        }
    }

    if ($all_user_ids) {
        $user_ids_list = array_keys($all_user_ids);
        $placeholders  = implode(',', array_fill(0, count($user_ids_list), '?'));
        $user_sql      = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id IN ($placeholders)";
        $user_stmt     = $common_pdo->prepare($user_sql);
        $user_stmt->execute($user_ids_list);
        $user_map = $user_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // ------------------- GROUPING BY ELEMENT + PART -------------------
    $grouped_inspections = [];
    foreach ($all_inspection_data as $row) {
        $unique_key = $row['element_id'] . '::' . ($row['part_name'] ?? 'main');
        if (!isset($grouped_inspections[$unique_key])) {
            $grouped_inspections[$unique_key] = ['latest_data' => $row, 'history' => []];
        }
        $grouped_inspections[$unique_key]['history'][] = $row;
        if (strtotime($row['created_at']) > strtotime($grouped_inspections[$unique_key]['latest_data']['created_at'])) {
            $grouped_inspections[$unique_key]['latest_data'] = $row;
        }
    }
    
    // ------------------- DROPDOWN DATA & COUNTS -------------------
    $all_plan_files = $pdo->query("SELECT DISTINCT plan_file FROM elements WHERE plan_file IS NOT NULL ORDER BY plan_file")->fetchAll(PDO::FETCH_COLUMN);
    $plan_counts = array_fill_keys($all_plan_files, 0);
    foreach ($grouped_inspections as $group) {
        $plan_file = $group['latest_data']['plan_file'];
        if ($plan_file && isset($plan_counts[$plan_file])) {
            $plan_counts[$plan_file]++;
        }
    }

    // ------------------- CALCULATE SUMMARY COUNTS (BEFORE TAB CATEGORIZATION) -------------------
    foreach ($grouped_inspections as $group) {
        $latest = $group['latest_data'];
        if ($latest['overall_status'] === 'OK') {
            $summary_counts['OK']++;
        } elseif ($latest['overall_status'] === 'Has Issues' || $latest['overall_status'] === 'Reject') {
             if ($latest['status'] === 'Not OK' || $latest['overall_status'] === 'Reject') {
                $summary_counts['Reject']++;
            } else {
                $summary_counts['Repair']++;
            }
        } elseif ($latest['contractor_status'] === 'Ready for Inspection' || $latest['status'] === 'Ready for Inspection' || $latest['status'] === 'Awaiting Re-inspection') {
            $summary_counts['Ready for Inspection']++;
        }
    }


    // ------------------- DATA CATEGORIZATION FOR TABS -------------------
    $data_by_status_and_type = ['my_tasks' => [], 'in_progress' => [], 'awaiting' => [], 'has_ok' => [], 'rejected' => [], 'all' => []];
    $processed_gfrc_elements = [];

    foreach ($grouped_inspections as $unique_key => $group) {
        $latest = $group['latest_data'];
        $element_id = $latest['element_id'];
        $element_type = $latest['element_type'] ?: 'سایر';

        if (isGFRCElement($element_type, $element_id)) {
            $processed_gfrc_elements[$element_id][$latest['part_name']] = $group;
            continue;
        }

        // --- Process NON-GFRC Elements ---
        $tab_category = 'in_progress';
        if ($latest['status'] === 'OK') {
            $tab_category = 'has_ok';
        } elseif ($latest['status'] === 'Reject' || $latest['status'] === 'Not OK') {
            $tab_category = 'rejected';
        } elseif ($latest['status'] === 'Repair') {
            $tab_category = 'in_progress';
        } elseif ($latest['status'] === 'Awaiting Re-inspection') {
            $tab_category = 'awaiting';
        }

        $item = [
            'element_id' => $latest['element_id'], 'part_name' => $latest['part_name'], 'stage_name' => $latest['stage_name'],
            'status' => $latest['status'], 'overall_status' => $latest['overall_status'], 'axis_span' => $latest['axis_span'],
            'floor_level' => $latest['floor_level'], 'contractor' => $latest['contractor'], 'user_id' => $latest['user_id'],
            'inspection_date' => $latest['inspection_date'], 'zone_name' => $latest['zone_name'], 'block' => $latest['block'],
            'plan_file' => $latest['plan_file'], 'inspection_cycle' => $latest['inspection_cycle'], 'full_history' => $group['history'],
            'unique_key' => $unique_key
        ];

        $data_by_status_and_type[$tab_category][$element_type][] = $item;
        $data_by_status_and_type['all'][$element_type][] = $item;
        if (!empty($latest['user_id']) && (int)$latest['user_id'] === (int)$user_id) {
            $data_by_status_and_type['my_tasks'][$element_type][] = $item;
        }
    }

    // --- Process GFRC Elements as Whole Panels ---
    foreach ($processed_gfrc_elements as $element_id => $parts) {
        $latest_part_for_main_data = null;
        $latest_timestamp = 0;
        $panel_parts_for_display = [];
        $tab_categories = [];
        $inspected_parts_count = 0;
        $panel_overall_status = 'In Progress';

        foreach ($parts as $part_name => $part_group) {
            $latest_part = $part_group['latest_data'];
            
            $part_status = $latest_part['status'];
            if(in_array($part_status, ['OK', 'Reject', 'Repair', 'Awaiting Re-inspection'])) {
                $inspected_parts_count++;
            }
            if ($part_status === 'OK') $tab_categories[] = 'has_ok';
            if ($part_status === 'Reject' || $part_status === 'Not OK') $tab_categories[] = 'rejected';
            if ($part_status === 'Repair') $tab_categories[] = 'in_progress';
            if ($part_status === 'Awaiting Re-inspection') $tab_categories[] = 'awaiting';

            if (strtotime($latest_part['created_at']) > $latest_timestamp) {
                $latest_timestamp = strtotime($latest_part['created_at']);
                $latest_part_for_main_data = $latest_part;
            }
            
            $panel_parts_for_display[] = [
                 'element_id' => $latest_part['element_id'], 'part_name' => $latest_part['part_name'], 'stage_name' => $latest_part['stage_name'],
                 'status' => $part_status, 'overall_status' => $latest_part['overall_status'], 'axis_span' => $latest_part['axis_span'],
                 'floor_level' => $latest_part['floor_level'], 'contractor' => $latest_part['contractor'], 'user_id' => $latest_part['user_id'],
                 'inspection_date' => $latest_part['inspection_date'], 'zone_name' => $latest_part['zone_name'], 'block' => $latest_part['block'],
                 'plan_file' => $latest_part['plan_file'], 'inspection_cycle' => $latest_part['inspection_cycle'], 'full_history' => $part_group['history'],
                 'unique_key' => $element_id . '::' . $part_name
            ];
        }

        if (!$latest_part_for_main_data) continue;

        $possible_parts_count = count(getGFRCParts($element_id, $element_orientations));
        $completion_percentage = $possible_parts_count > 0 ? ($inspected_parts_count / $possible_parts_count) * 100 : 0;

        $panel_item = [
            'element_data' => [
                'element_id' => $element_id, 'status' => 'mixed', 'axis_span' => $latest_part_for_main_data['axis_span'],
                'stage_name' => $latest_part_for_main_data['stage_name'], 'overall_status' => $latest_part_for_main_data['overall_status'],
                'floor_level' => $latest_part_for_main_data['floor_level'], 'contractor' => $latest_part_for_main_data['contractor'],
                'user_id' => $latest_part_for_main_data['user_id'], 'inspection_cycle' => $latest_part_for_main_data['inspection_cycle'],
                'inspection_date' => $latest_part_for_main_data['inspection_date'], 'zone_name' => $latest_part_for_main_data['zone_name'],
                'block' => $latest_part_for_main_data['block'], 'plan_file' => $latest_part_for_main_data['plan_file'],
            ],
            'parts' => $panel_parts_for_display, 'completion_percentage' => $completion_percentage,
        ];
        
        $unique_categories = array_unique($tab_categories);
        foreach($unique_categories as $category) {
            $data_by_status_and_type[$category]['GFRC'][$element_id] = $panel_item;
        }

        $data_by_status_and_type['all']['GFRC'][$element_id] = $panel_item;
        if (!empty($latest_part_for_main_data['user_id']) && (int)$latest_part_for_main_data['user_id'] === (int)$user_id) {
            $data_by_status_and_type['my_tasks']['GFRC'][$element_id] = $panel_item;
        }
    }

    // ------------------- DROPDOWN DATA (QUERIES) -------------------
    $all_zones = $pdo->query("SELECT DISTINCT zone_name FROM elements WHERE zone_name IS NOT NULL AND zone_name != '' ORDER BY zone_name")->fetchAll(PDO::FETCH_COLUMN);
    $all_blocks = $pdo->query("SELECT DISTINCT block FROM elements WHERE block IS NOT NULL AND block != '' ORDER BY block")->fetchAll(PDO::FETCH_COLUMN);
    $all_stages = $pdo->query("SELECT DISTINCT stage FROM inspection_stages ORDER BY stage")->fetchAll(PDO::FETCH_COLUMN);
    $all_contractors = $pdo->query("SELECT DISTINCT contractor FROM elements WHERE contractor IS NOT NULL AND contractor != '' ORDER BY contractor")->fetchAll(PDO::FETCH_COLUMN);
    $all_inspectors = $common_pdo->query("SELECT DISTINCT CONCAT(first_name, ' ', last_name) as inspector FROM users WHERE role IN ('admin', 'superuser', 'user') ORDER BY inspector")->fetchAll(PDO::FETCH_COLUMN);
    $all_gfrc_parts = $pdo->query("SELECT DISTINCT part_name FROM inspections i JOIN elements e ON i.element_id = e.element_id WHERE e.element_type = 'GFRC' ORDER BY part_name")->fetchAll(PDO::FETCH_COLUMN);

} catch (Throwable $e) {
    $db_error_message = "خطای پایگاه داده: " . $e->getMessage();
    error_log($db_error_message);
    $data_by_status_and_type = ['my_tasks' => [], 'in_progress' => [], 'awaiting' => [], 'has_ok' => [], 'rejected' => [], 'all' => []];
}

// ------------------- HELPER FUNCTIONS FOR VIEW -------------------
function getStatusRowClass($status, $overall_status) {
    if ($overall_status === 'OK') return 'status-ok-cell';
    if ($overall_status === 'Has Issues' || $overall_status === 'Reject' || $status === 'رد شده' || $status === 'Not OK') return 'status-not-ok-cell';
    if ($status === 'آماده بازرسی مجدد' || $status === 'Awaiting Re-inspection') return 'status-ready-cell';
    return '';
}

function getTabBadgeClass($tab_key) {
    return match($tab_key) {
        'has_ok' => 'badge-success', 'rejected' => 'badge-danger',
        'in_progress' => 'badge-warning', 'awaiting' => 'badge-info',
        'my_tasks' => 'badge-primary',
        default => 'badge-secondary'
    };
}

function getElementTypeDisplayName($element_type) {
    $type_names = ['GFRC' => 'GFRC پانل‌ها', 'Concrete' => 'بتن', 'Steel' => 'فولاد', 'Masonry' => 'بنایی'];
    return $type_names[$element_type] ?? $element_type;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title><?php echo escapeHtml($pageTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="/ghom/assets/images/favicon.ico" />
    <link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    <link rel="stylesheet" href="/ghom/assets/css/formopen.css" />
    <script src="/ghom/assets/js/interact.min.js"></script>
<style>
    @font-face {
        font-family: "Samim";
        src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
             url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
             url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
    }
    body { font-family: "Samim", sans-serif; background-color: #f4f7f6; direction: rtl; margin: 0; padding: 20px; font-size: 14px; }
    .dashboard-container { max-width: 98%; margin: auto; }
    h1, h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; }
    .table-container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); margin-bottom: 20px; }
    .summary-box { background-color: #e9f5ff; }
    .filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 15px; margin-bottom: 20px; }
    .form-group { display: flex; flex-direction: column; }
    .form-group label { margin-bottom: 5px; font-weight: bold; font-size: 0.9em; }
    .form-group input, .form-group select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-width: 150px; }
    .btn { border: none; padding: 9px 15px; border-radius: 4px; cursor: pointer; font-family: inherit; }
    .button-group { display: flex; flex-wrap: wrap; gap: 5px; }
    /* TABS */
    .main-tab-buttons { display: flex; flex-wrap: wrap; border-bottom: 3px solid #ddd; margin-bottom: 0; background: #f8f9fa; border-radius: 8px 8px 0 0; }
    .main-tab-button { padding: 15px 25px; cursor: pointer; background: #e9ecef; border: none; border-bottom: 3px solid transparent; font-size: 14px; font-weight: 500; transition: all 0.3s ease; }
    .main-tab-button:hover { background: #dee2e6; }
    .main-tab-button.active { background: #fff; border-bottom: 3px solid #007bff; font-weight: bold; color: #007bff; }
    .main-tab-content { display: none; background: #fff; border-radius: 0 0 8px 8px; border: 1px solid #ddd; border-top: none; }
    .main-tab-content.active { display: block; }
    .inner-tab-buttons { display: flex; flex-wrap: wrap; background: #f1f3f4; border-bottom: 2px solid #ccc; margin: 0; padding: 10px 20px 0; }
    .inner-tab-button { padding: 10px 20px; cursor: pointer; background: #e9ecef; border: 1px solid #ccc; border-bottom: none; border-radius: 5px 5px 0 0; margin-left: 5px; font-size: 13px; }
    .inner-tab-button.active { background: #fff; border-bottom: 2px solid #fff; font-weight: bold; position: relative; z-index: 1; }
    .inner-tab-content { display: none; padding: 20px; }
    .inner-tab-content.active { display: block; }
    /* TABLE */
    .table-wrapper { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { padding: 8px 6px; text-align: right; border-bottom: 1px solid #ddd; white-space: nowrap; }
    th { background-color: #f8f9fa; font-weight: bold; cursor: pointer; position: sticky; top: 0; z-index: 10; }
    .filter-row input { width: 100%; padding: 4px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 3px; font-size: 12px; }
    .results-info { margin: 15px 0; font-weight: bold; color: #495057; }
    .badge { padding: 3px 8px; border-radius: 12px; font-size: 0.75em; font-weight: bold; margin-right: 5px; }
    .badge-success { background-color: #28a745; color: white; } .badge-warning { background-color: #ffc107; color: #212529; }
    .badge-danger { background-color: #dc3545; color: white; } .badge-secondary { background-color: #6c757d; color: white; }
    .badge-info { background-color: #17a2b8; color: white; } .badge-primary { background-color: #007bff; color: white; }
    .status-ok-cell { background-color: #d4edda !important; } .status-ready-cell { background-color: #fff3cd !important; }
    .status-not-ok-cell { background-color: #f8d7da !important; }
    .sort-asc::after { content: " ↑"; } .sort-desc::after { content: " ↓"; }
    .no-data-message { text-align: center; padding: 40px; color: #6c757d; font-style: italic; }
    /* GFRC STYLES */
    .gfrc-main-row { background-color: #e3f2fd !important; border-right: 4px solid #2196f3; }
    .gfrc-part-row { background-color: #f8f9fa; border-right: 2px solid #dee2e6; }
    .gfrc-part-row:hover { background-color: #e9ecef; }
    .element-name-cell { cursor: pointer; color: #007bff; font-weight: bold; }
    .element-name-cell:hover { text-decoration: underline; }
    .parts-status { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 5px; }
    .part-badge { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; color: white; }
    /* COMPLETION BAR */
    .completion-bar { width: 100%; height: 20px; background-color: #e9ecef; border-radius: 10px; overflow: hidden; position: relative; }
    .completion-fill { height: 100%; transition: width 0.3s ease; }
    .completion-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 11px; font-weight: bold; color: #495057; }
</style>
</head>
<body>
    <div class="dashboard-container">
        <h1><?php echo escapeHtml($pageTitle); ?></h1>

        <?php if ($db_error_message): ?>
            <div class="table-container" style="background-color: #f8d7da; color: #721c24;"><?php echo escapeHtml($db_error_message); ?></div>
        <?php endif; ?>

        <!-- Summary Section -->
        <div class="table-container summary-box">
            <h2>خلاصه وضعیت و مشاهده در نقشه</h2>
            <div class="filters">
                <div class="form-group">
                    <label>۱. انتخاب نقشه:</label>
                    <select id="report-plan-select">
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach ($all_plan_files as $plan):
                            $count = $plan_counts[$plan] ?? 0;
                            $style = $count === 0 ? 'style="background-color: #eeeeee; color: #888;"' : 'style="font-weight: bold;"';
                        ?>
                            <option value="<?php echo escapeHtml($plan); ?>" <?php echo $style; ?>>
                                <?php echo escapeHtml($plan); ?> (<?php echo number_format($count); ?> بازرسی)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="form-group">
                    <label>۲. مشاهده همه المان‌ها با وضعیت:</label>
                    <div class="button-group">
                        <button class="btn report-btn" data-status="Ready for Inspection" style="background-color: #ffc107; color: black;">آماده بازرسی مجدد (<?php echo number_format($summary_counts['Ready for Inspection']); ?>)</button>
                        <button class="btn report-btn" data-status="Repair" style="background-color: #17a2b8; color: white;">نیاز به تعمیر (<?php echo number_format($summary_counts['Repair']); ?>)</button>
                        <button class="btn report-btn" data-status="Reject" style="background-color: #dc3545; color: white;">رد شده (<?php echo number_format($summary_counts['Reject']); ?>)</button>
                        <button class="btn report-btn" data-status="OK" style="background-color: #28a745; color: white;">تایید شده (<?php echo number_format($summary_counts['OK']); ?>)</button>
                        <button class="btn" id="open-viewer-btn-all" style="background-color: #6c757d; color: white;">همه وضعیت‌ها</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Dashboard -->
        <div id="dashboard-view">
            <div class="main-tab-buttons">
                <?php
                $tab_titles = ['my_tasks' => 'کارهای من', 'in_progress' => 'نیاز به تعمیر', 'awaiting' => 'منتظر بازرسی مجدد', 'has_ok' => 'دارای تاییدیه', 'rejected' => 'رد شده', 'all' => 'همه موارد'];
                foreach ($tab_titles as $status_key => $title):
                    $total_count = 0;
                    if (!empty($data_by_status_and_type[$status_key])) {
                        foreach ($data_by_status_and_type[$status_key] as $type_group) {
                            $total_count += count($type_group);
                        }
                    }
                ?>
                <button class="main-tab-button <?php echo $status_key === 'my_tasks' ? 'active' : ''; ?>" data-tab-content="main-tab-<?php echo $status_key; ?>">
                    <?php echo $title; ?>
                    <span class="badge <?php echo getTabBadgeClass($status_key); ?>"><?php echo $total_count; ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($data_by_status_and_type as $status_key => $element_types_group): ?>
            <div id="main-tab-<?php echo $status_key; ?>" class="main-tab-content <?php echo $status_key === 'my_tasks' ? 'active' : ''; ?>">
                <?php if (empty($element_types_group)): ?>
                    <div class="no-data-message">موردی در این دسته یافت نشد.</div>
                <?php else: ksort($element_types_group); ?>
                    <div class="inner-tab-buttons">
                        <?php $first_inner = true; foreach ($element_types_group as $element_type => $data): $safe_type_id = str_replace([' ', '.', '-'], '_', $element_type); ?>
                        <button class="inner-tab-button <?php if($first_inner) { echo 'active'; $first_inner = false; } ?>" data-tab-content="inner-tab-<?php echo $status_key . '-' . $safe_type_id; ?>">
                            <?php echo escapeHtml(getElementTypeDisplayName($element_type)); ?>
                            <span class="badge badge-secondary"><?php echo count($data); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <?php $first_inner_content = true; foreach ($element_types_group as $element_type => $data): $safe_type_id = str_replace([' ', '.', '-'], '_', $element_type); ?>
                    <div id="inner-tab-<?php echo $status_key . '-' . $safe_type_id; ?>" class="inner-tab-content <?php if($first_inner_content) { echo 'active'; $first_inner_content = false; } ?>">
                        <div class="filters">
                            <!-- General Filters -->
                            <div class="form-group"><label>فیلتر مرحله:</label><select class="filter-select" data-column="3"><option value="">همه</option><?php foreach ($all_stages as $stage):?><option value="<?php echo escapeHtml($stage);?>"><?php echo escapeHtml($stage);?></option><?php endforeach;?></select></div>
                            <div class="form-group"><label>فیلتر بازرس:</label><select class="filter-select" data-column="7"><option value="">همه</option><?php foreach ($all_inspectors as $inspector):?><option value="<?php echo escapeHtml($inspector);?>"><?php echo escapeHtml($inspector);?></option><?php endforeach;?></select></div>
                            <div class="form-group"><label>فیلتر پیمانکار:</label><select class="filter-select" data-column="8"><option value="">همه</option><?php foreach ($all_contractors as $contractor):?><option value="<?php echo escapeHtml($contractor);?>"><?php echo escapeHtml($contractor);?></option><?php endforeach;?></select></div>
                            <div class="form-group"><label>فیلتر زون:</label><select class="filter-select" data-column="11"><option value="">همه</option><?php foreach ($all_zones as $zone):?><option value="<?php echo escapeHtml($zone);?>"><?php echo escapeHtml($zone);?></option><?php endforeach;?></select></div>
                            <div class="form-group"><label>فیلتر بلوک:</label><select class="filter-select" data-column="12"><option value="">همه</option><?php foreach ($all_blocks as $block):?><option value="<?php echo escapeHtml($block);?>"><?php echo escapeHtml($block);?></option><?php endforeach;?></select></div>
                            <!-- GFRC-Specific Filter -->
                            <?php if ($element_type === 'GFRC'): ?>
                            <div class="form-group"><label>فیلتر قطعه GFRC:</label><select class="filter-select gfrc-part-filter"><option value="">همه</option><?php foreach ($all_gfrc_parts as $part):?><option value="<?php echo escapeHtml($part);?>"><?php echo escapeHtml($part);?></option><?php endforeach;?></select></div>
                            <?php endif; ?>
                            <div class="form-group"><label>&nbsp;</label><button class="btn clear-filters" style="background-color: #6c757d; color: white;">پاک کردن</button></div>
                        </div>

                        <div class="results-info">نمایش <span class="visible-count"><?php echo count($data); ?></span> از <span class="total-count"><?php echo count($data); ?></span></div>
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th data-sort>نام المان</th><th data-sort>نام قطعه</th><th data-sort>آدرس</th>
                                        <th data-sort>آخرین مرحله</th><th data-sort>وضعیت نهایی</th><th data-sort>پیشرفت بازرسی</th>
                                        <th data-sort>تراز</th><th data-sort>آخرین بازرس</th><th data-sort>پیمانکار</th>
                                        <th data-sort>چرخه</th><th data-sort>تاریخ</th><th data-sort>زون</th>
                                        <th data-sort>بلوک</th><th>عملیات</th>
                                    </tr>
                                    <tr class="filter-row">
                                        <td><input type="text" data-column="0" placeholder="جستجو..."></td><td><input type="text" data-column="1" placeholder="جستجو..."></td>
                                        <td><input type="text" data-column="2" placeholder="جستجو..."></td><td></td><td><input type="text" data-column="4" placeholder="جستجو..."></td>
                                        <td></td><td><input type="text" data-column="6" placeholder="جستجو..."></td><td></td><td></td><td></td><td></td>
                                        <td><input type="text" data-column="11" placeholder="جستجو..."></td><td><input type="text" data-column="12" placeholder="جستجو..."></td><td></td>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if ($element_type === 'GFRC'): ?>
    
                                    <?php foreach ($data as $element_id => $element_group):
                                        $main_item = $element_group['element_data'];
                                        $parts = $element_group['parts'];
                                        $completion_percentage = $element_group['completion_percentage'];
                                        $completion_color = $completion_percentage >= 100 ? '#28a745' : ($completion_percentage >= 50 ? '#ffc107' : '#dc3545');
                                        $expected_parts = getGFRCParts($element_id, $element_orientations);
                                    ?>
                                    <tr class="data-row main-inspection-row gfrc-main-row" data-element-id="<?php echo escapeHtml($element_id); ?>">
                                        <td class="element-name-cell" onclick="toggleGFRCParts('<?php echo escapeHtml($element_id); ?>')">
                                            <strong><?php echo escapeHtml($element_id); ?></strong>
                                            <div class="parts-status">
                                            <?php
                                            $part_statuses = array_column($parts, 'status', 'part_name');
                                            foreach ($expected_parts as $expected_part):
                                                $part_status = $part_statuses[$expected_part] ?? null;
                                                $status_class = get_status_badge_class($part_status);
                                            ?>
                                                <span class="part-badge <?php echo $status_class; ?>"><?php echo escapeHtml($expected_part); ?></span>
                                            <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td>-</td>
                                        <td><?php echo escapeHtml($main_item['axis_span']); ?></td>
                                        <td><?php echo escapeHtml($main_item['stage_name']); ?></td>
                                        <td><span class="badge badge-secondary">چند وضعیتی</span></td>
                                        <td><div class="completion-bar"><div class="completion-fill" style="width: <?php echo $completion_percentage; ?>%; background-color: <?php echo $completion_color; ?>;"></div><div class="completion-text"><?php echo round($completion_percentage); ?>%</div></div></td>
                                        <td><?php echo escapeHtml($main_item['floor_level']); ?></td>
                                        <td><?php echo escapeHtml($user_map[$main_item['user_id']] ?? 'ناشناس'); ?></td>
                                        <td><?php echo escapeHtml($main_item['contractor']); ?></td>
                                        <td><?php echo $main_item['inspection_cycle'] ?: '1'; ?></td>
                                        <td data-persian-date="<?php echo $main_item['inspection_date'] ? jdate('Y/m/d', strtotime($main_item['inspection_date'])) : ''; ?>"><?php echo $main_item['inspection_date'] ? jdate('Y/m/d', strtotime($main_item['inspection_date'])) : '---'; ?></td>
                                        <td><?php echo escapeHtml($main_item['zone_name']); ?></td>
                                        <td><?php echo escapeHtml($main_item['block']); ?></td>
                                        <td>
                                    <?php
                                        // --- START: CRITICAL FIX ---
                                        // Base URL for the history page
                                        $history_url = "/ghom/view_element_history.php?element_id=" . urlencode($main_item['element_id'] ?? '') . "&part_name=" . urlencode($main_item['part_name'] ?? '');

                                        // If the element type is GLASS, add the special instruction to the URL
                                        if (strtoupper($main_item['element_type'] ?? '') === 'GLASS') {
                                            $history_url .= "&hide_layer=Curtainwall";
                                        }
                                    ?>
                                    <a href="<?php echo $history_url; ?>" class="btn" target="_blank" style="font-size:10px; padding: 2px 5px; background-color: #6c757d; color:white;">تاریخچه</a>
                                    </td>
                                    </tr>
                                    <?php foreach ($parts as $part_item): ?>
                                    <tr class="gfrc-part-row" data-parent-element="<?php echo escapeHtml($element_id); ?>" style="display: none;">
                                        <td style="padding-right: 30px;">└ <?php echo escapeHtml($part_item['element_id']); ?></td>
                                        <td><strong><?php echo escapeHtml($part_item['part_name']); ?></strong></td>
                                        <td><?php echo escapeHtml($part_item['axis_span']); ?></td>
                                        <td><?php echo escapeHtml($part_item['stage_name']); ?></td>
                                        <td><span class="badge <?php echo get_status_badge_class($part_item['status']); ?>"><?php echo escapeHtml(get_status_persian($part_item['status'])); ?></span></td>
                                        <td>-</td>
                                        <td><?php echo escapeHtml($part_item['floor_level']); ?></td>
                                        <td><?php echo escapeHtml($user_map[$part_item['user_id']] ?? 'ناشناس'); ?></td>
                                        <td><?php echo escapeHtml($part_item['contractor']); ?></td>
                                        <td><?php echo $part_item['inspection_cycle'] ?: '1'; ?></td>
                                        <td data-persian-date="<?php echo $part_item['inspection_date'] ? jdate('Y/m/d', strtotime($part_item['inspection_date'])) : ''; ?>"><?php echo $part_item['inspection_date'] ? jdate('Y/m/d', strtotime($part_item['inspection_date'])) : '---'; ?></td>
                                        <td><?php echo escapeHtml($part_item['zone_name']); ?></td>
                                        <td><?php echo escapeHtml($part_item['block']); ?></td>
                                        <td>
                                            <a href="/ghom/view_element_history.php?element_id=<?php echo urlencode($part_item['element_id']); ?>&part_name=<?php echo urlencode($part_item['part_name']); ?>" class="btn" target="_blank" style="font-size:10px; padding: 2px 5px; background-color: #6c757d; color:white;">تاریخچه</a>
                                            <a href="<?php echo sprintf("/ghom/index.php?plan=%s&element_id=%s", urlencode($part_item['plan_file']), urlencode($part_item['element_id'] . '-' . $part_item['part_name'])); ?>" class="btn" target="_blank" style="font-size:10px; padding: 2px 5px; background-color: #17a2b8; color:white;">نقشه</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php else: // Non-GFRC Elements ?>
                                    <?php foreach ($data as $item): $row_class = getStatusRowClass($item['status'], $item['overall_status']); ?>
                                    <tr class="data-row main-inspection-row <?php echo $row_class; ?>" data-unique-key="<?php echo escapeHtml($item['unique_key']); ?>">
                                        <td><?php echo escapeHtml($item['element_id']); ?></td>
                                        <td><?php echo escapeHtml($item['part_name']); ?></td>
                                        <td><?php echo escapeHtml($item['axis_span']); ?></td>
                                        <td><?php echo escapeHtml($item['stage_name']); ?></td>
                                        <td><span class="badge <?php echo get_status_badge_class($item['status']); ?>"><?php echo escapeHtml(get_status_persian($item['status'])); ?></span></td>
                                        <td>-</td>
                                        <td><?php echo escapeHtml($item['floor_level']); ?></td>
                                        <td><?php echo escapeHtml($user_map[$item['user_id']] ?? 'ناشناس'); ?></td>
                                        <td><?php echo escapeHtml($item['contractor']); ?></td>
                                        <td><?php echo $item['inspection_cycle'] ?: '1'; ?></td>
                                        <td data-persian-date="<?php echo $item['inspection_date'] ? jdate('Y/m/d', strtotime($item['inspection_date'])) : ''; ?>"><?php echo $item['inspection_date'] ? jdate('Y/m/d', strtotime($item['inspection_date'])) : '---'; ?></td>
                                        <td><?php echo escapeHtml($item['zone_name']); ?></td>
                                        <td><?php echo escapeHtml($item['block']); ?></td>
                                        <td>
<a href="/ghom/view_element_history.php?element_id=<?php echo urlencode($item['element_id'] ?? ''); ?>&part_name=<?php echo urlencode($item['part_name'] ?? ''); ?>" class="btn" target="_blank" style="font-size:10px; padding: 2px 5px; background-color: #6c757d; color:white;">تاریخچه</a>                                            <a href="<?php echo sprintf("/ghom/index.php?plan=%s&element_id=%s", urlencode($item['plan_file']), urlencode($item['element_id'])); ?>" class="btn" target="_blank" style="font-size:10px; padding: 2px 5px; background-color: #17a2b8; color:white;">نقشه</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php require_once 'footer.php'; ?>

    <script src="/ghom/assets/js/jalalidatepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    // --- HELPER: Convert Persian numbers in a string to Latin ---
    function persianToLatinDigits(str) {
        if (!str) return '';
        const persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        const latin   = ['0','1','2','3','4','5','6','7','8','9'];
        let result = String(str);
        for (let i = 0; i < 10; i++) {
            result = result.replace(new RegExp(persian[i], 'g'), latin[i]);
        }
        return result;
    }

    // --- GFRC: Toggle visibility of part sub-rows ---
    window.toggleGFRCParts = function(elementId) {
        const partRows = document.querySelectorAll(`tr.gfrc-part-row[data-parent-element="${elementId}"]`);
        if (!partRows.length) return;
        const isVisible = partRows[0].style.display !== 'none';
        partRows.forEach(row => {
            row.style.display = isVisible ? 'none' : '';
        });
    };

    // --- TAB SWITCHING LOGIC ---
    document.querySelectorAll('.main-tab-button').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.main-tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.main-tab-content').forEach(content => content.classList.remove('active'));
            button.classList.add('active');
            const targetContent = document.getElementById(button.dataset.tabContent);
            if (targetContent) {
                targetContent.classList.add('active');
                const firstInnerTab = targetContent.querySelector('.inner-tab-button');
                if (firstInnerTab && !firstInnerTab.classList.contains('active')) {
                   firstInnerTab.click();
                }
            }
        });
    });

    document.querySelectorAll('.inner-tab-button').forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            const parentContainer = button.closest('.main-tab-content');
            if (!parentContainer) return;
            parentContainer.querySelectorAll('.inner-tab-button').forEach(btn => btn.classList.remove('active'));
            parentContainer.querySelectorAll('.inner-tab-content').forEach(content => content.classList.remove('active'));
            button.classList.add('active');
            const targetContent = document.getElementById(button.dataset.tabContent);
            if (targetContent) targetContent.classList.add('active');
        });
    });

    // --- FILTERING AND SORTING LOGIC FOR EACH TABLE ---
    document.querySelectorAll('.data-table').forEach(table => {
        const tableContainer = table.closest('.inner-tab-content');
        if (!tableContainer) return;

        const tbody = table.querySelector('tbody');
        const headers = table.querySelectorAll('thead th[data-sort]');
        const allFilters = tableContainer.querySelectorAll('.filter-select, .filter-row input');
        const clearButton = tableContainer.querySelector('.clear-filters');

        const applyFilters = () => {
            const filters = Array.from(allFilters).map(el => ({
                col: el.dataset.column ? parseInt(el.dataset.column, 10) : -1,
                val: el.value.toLowerCase().trim(),
                isGfrcPartFilter: el.classList.contains('gfrc-part-filter')
            }));

            let visibleCount = 0;
            const mainRows = tbody.querySelectorAll('tr.main-inspection-row');

            mainRows.forEach(row => {
                let isVisible = true;

                for (const f of filters) {
                    if (!f.val) continue;

                    if (f.isGfrcPartFilter) {
                        const elementId = row.dataset.elementId;
                        if (elementId) {
                            let partMatch = false;
                            const partRows = tbody.querySelectorAll(`tr.gfrc-part-row[data-parent-element="${elementId}"]`);
                            for (const partRow of partRows) {
                                const partNameCell = partRow.cells[1];
                                if (partNameCell && partNameCell.textContent.toLowerCase().trim().includes(f.val)) {
                                    partMatch = true;
                                    break;
                                }
                            }
                            if (!partMatch) isVisible = false;
                        }
                    }
                    else if (f.col !== -1) {
                        const cell = row.cells[f.col];
                        if (!cell || !cell.textContent.toLowerCase().trim().includes(f.val)) {
                            isVisible = false;
                        }
                    }
                    if (!isVisible) break;
                }

                row.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;

                if (row.classList.contains('gfrc-main-row')) {
                    const elementId = row.dataset.elementId;
                    tbody.querySelectorAll(`tr.gfrc-part-row[data-parent-element="${elementId}"]`).forEach(partRow => {
                        if (!isVisible) partRow.style.display = 'none';
                    });
                }
            });
            tableContainer.querySelector('.visible-count').textContent = visibleCount;
        };

        headers.forEach((header, headerIndex) => {
            header.addEventListener('click', () => {
                const isAsc = header.classList.contains('sort-asc');
                const dir = isAsc ? -1 : 1;
                const rows = Array.from(tbody.querySelectorAll('tr.main-inspection-row'));

                const sortedRows = rows.sort((a, b) => {
                    const cellA = a.cells[headerIndex];
                    const cellB = b.cells[headerIndex];
                    let valA = cellA?.textContent.trim() || '';
                    let valB = cellB?.textContent.trim() || '';

                    if (cellA?.dataset.persianDate) {
                        valA = parseInt(persianToLatinDigits(cellA.dataset.persianDate).replace(/\//g, ''), 10) || 0;
                        valB = parseInt(persianToLatinDigits(cellB.dataset.persianDate).replace(/\//g, ''), 10) || 0;
                    }

                    const numA = parseFloat(valA);
                    const numB = parseFloat(valB);

                    if (!isNaN(numA) && !isNaN(numB)) {
                        return (numA - numB) * dir;
                    }
                    return valA.localeCompare(valB, 'fa') * dir;
                });

                sortedRows.forEach(row => {
                    tbody.appendChild(row);
                    if (row.classList.contains('gfrc-main-row')) {
                        const elementId = row.dataset.elementId;
                        tbody.querySelectorAll(`tr.gfrc-part-row[data-parent-element="${elementId}"]`).forEach(partRow => tbody.appendChild(partRow));
                    }
                });

                headers.forEach(th => th.classList.remove('sort-asc', 'sort-desc'));
                header.classList.toggle('sort-asc', !isAsc);
                header.classList.toggle('sort-desc', isAsc);
            });
        });

        allFilters.forEach(input => {
            const eventType = input.tagName.toLowerCase() === 'select' ? 'change' : 'input';
            input.addEventListener(eventType, applyFilters);
        });

        if (clearButton) {
            clearButton.addEventListener('click', () => {
                allFilters.forEach(input => { input.value = ''; });
                applyFilters();
            });
        }
    });

    // --- MAP VIEWER BUTTONS ---
    function openViewer(allStatuses = false) {
        const planFile = document.getElementById('report-plan-select').value;
        if (!planFile) {
            alert('لطفا ابتدا یک نقشه را انتخاب کنید.');
            return;
        }
        let url = `/ghom/viewer.php?plan=${encodeURIComponent(planFile)}`;
        if (!allStatuses) {
            const status = this.dataset.status;
            url += `&status=${encodeURIComponent(status)}`;
        }
        window.open(url, '_blank');
    }

    document.querySelectorAll('.report-btn').forEach(button => button.addEventListener('click', openViewer.bind(button, false)));
    document.getElementById('open-viewer-btn-all').addEventListener('click', openViewer.bind(null, true));


    // --- INITIALIZE FIRST TAB ---
    document.querySelector('.main-tab-button.active')?.click();
});
</script>

</body>
</html>
