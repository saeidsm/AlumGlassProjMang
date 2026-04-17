<?php
// ===================================================================
// /public_html/ghom/inspection_dashboard_mobile.php
// FULLY OVERHAULED TO MATCH DESKTOP FUNCTIONALITY - FIXED VERSION
// ===================================================================

// --- BOOTSTRAP, SESSION, and DATA FETCHING ---
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

// --- MISSING HELPER FUNCTIONS (These were causing the errors!) ---


function getElementTypeDisplayName($type) {
    $type_map = [
        'GFRC' => 'پنل‌های GFRC',
        'Curtainwall' => 'کرتین‌وال',
        'GLASS' => 'شیشه',
        'Bazshow' => 'بازشو',
        'سایر' => 'سایر'
    ];
    return $type_map[$type] ?? $type;
}

function getTabBadgeClass($status_key) {
    $badge_map = [
        'my_tasks' => 'badge-primary',
        'in_progress' => 'badge-warning', 
        'awaiting' => 'badge-info',
        'has_ok' => 'badge-success',
        'rejected' => 'badge-danger',
        'all' => 'badge-secondary'
    ];
    return $badge_map[$status_key] ?? 'badge-secondary';
}

// --- HELPERS (Synced with Desktop Version) ---
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

function getGFRCParts(string $element_id, array $orientations_map): array {
    $orientation = $orientations_map[$element_id] ?? 'Horizontal';
    if ($orientation === 'Vertical') {
        return ['right', 'left', 'face'];
    }
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
        'Awaiting Re-inspection' => 'منتظر بازرسی',
        'Ready for Inspection' => 'آماده بازرسی',
        'Pre-Inspection Complete' => 'پیش‌بازرسی کامل',
    ];
    return $status_map[$status] ?? $status ?? 'نامشخص';
}

// --- SECURITY AND SESSION HANDLING ---
secureSession();
if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}
if (!in_array($_SESSION['role'], ['admin', 'supervisor', 'user', 'superuser', 'cat', 'car', 'coa', 'crs'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

$pageTitle = "داشبورد بازرسی (موبایل)";
require_once __DIR__ . '/header_ghom.php';

// --- INITIALIZATION ---
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$is_contractor_role = in_array($user_role, ['cat', 'car', 'coa', 'crs']);
$data_by_status_and_type = [];
$user_map = [];
$element_orientations = [];
$plan_counts = [];
$all_plan_files = [];
$all_stages = [];

try {
    $pdo = getProjectDBConnection('ghom');
    $common_pdo = getCommonDBConnection();
    $pdo->exec("SET NAMES 'utf8mb4'");

    // --- MAIN DATA QUERY ---
    $sql = "
    SELECT 
        i.inspection_id, i.element_id, i.part_name, i.stage_id, i.user_id,
        s.stage AS stage_name,
        i.status, i.contractor_status, i.overall_status,
        e.element_type, e.plan_file, e.zone_name, e.contractor, e.block, e.axis_span, e.floor_level, e.panel_orientation,
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
            $sql .= " WHERE 1=0"; // No data if company not found
        }
    }
    $sql .= " ORDER BY i.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_inspection_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create orientation map
    foreach ($all_inspection_data as $row) {
        if (!isset($element_orientations[$row['element_id']])) {
            $element_orientations[$row['element_id']] = $row['panel_orientation'];
        }
    }
    
    // --- USER MAP CREATION ---
    $all_user_ids = [];
    foreach ($all_inspection_data as $row) {
        if (!empty($row['user_id'])) $all_user_ids[$row['user_id']] = true;
        
        // Parse logs safely
        $history_log = $row['history_log'] ? json_decode($row['history_log'], true) : [];
        $pre_inspection_log = $row['pre_inspection_log'] ? json_decode($row['pre_inspection_log'], true) : [];
        $logs = array_merge($history_log ?: [], $pre_inspection_log ?: []);
        
        foreach ($logs as $log) {
            if (!empty($log['user_id'])) $all_user_ids[$log['user_id']] = true;
        }
    }

    if (!empty($all_user_ids)) {
        $user_ids_list = array_keys($all_user_ids);
        $placeholders = implode(',', array_fill(0, count($user_ids_list), '?'));
        $user_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id IN ($placeholders)";
        $user_stmt = $common_pdo->prepare($user_sql);
        $user_stmt->execute($user_ids_list);
        $user_map = $user_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // --- GROUP INSPECTIONS ---
    $grouped_inspections = [];
    foreach ($all_inspection_data as $row) {
        $unique_key = $row['element_id'] . '::' . ($row['part_name'] ?? 'main');
        if (!isset($grouped_inspections[$unique_key])) {
            $grouped_inspections[$unique_key] = $row;
        }
    }

    // --- DATA CATEGORIZATION (Mirrors Desktop Logic) ---
    $data_by_status_and_type = [
        'my_tasks' => [], 
        'in_progress' => [], 
        'awaiting' => [], 
        'has_ok' => [], 
        'rejected' => [], 
        'all' => []
    ];
    $processed_gfrc_elements = [];

    foreach ($grouped_inspections as $unique_key => $latest) {
        $element_id = $latest['element_id'];
        $element_type = $latest['element_type'] ?: 'سایر';

        if (isGFRCElement($element_type, $element_id)) {
            // Group all parts of the same GFRC element together
            $processed_gfrc_elements[$element_id][$latest['part_name']] = $latest;
            continue;
        }

        // --- Process NON-GFRC Elements ---
        $tab_category = 'in_progress';
        if ($latest['status'] === 'OK') $tab_category = 'has_ok';
        elseif ($latest['status'] === 'Reject' || $latest['status'] === 'Not OK') $tab_category = 'rejected';
        elseif ($latest['status'] === 'Repair') $tab_category = 'in_progress';
        elseif ($latest['status'] === 'Awaiting Re-inspection') $tab_category = 'awaiting';

        $data_by_status_and_type[$tab_category][$element_type][$unique_key] = $latest;
        $data_by_status_and_type['all'][$element_type][$unique_key] = $latest;
        if (!empty($latest['user_id']) && (int)$latest['user_id'] === (int)$user_id) {
            $data_by_status_and_type['my_tasks'][$element_type][$unique_key] = $latest;
        }
    }
    
    // --- Process GFRC Elements as Whole Panels ---
    foreach ($processed_gfrc_elements as $element_id => $parts) {
        $latest_part_for_main_data = null;
        $latest_timestamp = 0;
        $tab_categories = [];
        $inspected_parts_count = 0;

        foreach ($parts as $part_name => $part_data) {
            $part_status = $part_data['status'];
            if(in_array($part_status, ['OK', 'Reject', 'Repair', 'Awaiting Re-inspection'])) {
                $inspected_parts_count++;
            }
            if ($part_status === 'OK') $tab_categories[] = 'has_ok';
            if ($part_status === 'Reject' || $part_status === 'Not OK') $tab_categories[] = 'rejected';
            if ($part_status === 'Repair') $tab_categories[] = 'in_progress';
            if ($part_status === 'Awaiting Re-inspection') $tab_categories[] = 'awaiting';

            if (strtotime($part_data['created_at']) > $latest_timestamp) {
                $latest_timestamp = strtotime($part_data['created_at']);
                $latest_part_for_main_data = $part_data;
            }
        }

        if (!$latest_part_for_main_data) continue;

        $possible_parts_count = count(getGFRCParts($element_id, $element_orientations));
        $completion_percentage = $possible_parts_count > 0 ? ($inspected_parts_count / $possible_parts_count) * 100 : 0;

        $panel_item = [
            'element_data' => $latest_part_for_main_data,
            'parts' => $parts,
            'completion_percentage' => $completion_percentage,
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
    
    // --- DROPDOWN DATA & COUNTS ---
    $plan_stmt = $pdo->query("SELECT DISTINCT plan_file FROM elements WHERE plan_file IS NOT NULL ORDER BY plan_file");
    $all_plan_files = $plan_stmt ? $plan_stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    
    $plan_counts = array_fill_keys($all_plan_files, 0);
    foreach ($grouped_inspections as $group) {
        $plan_file = $group['plan_file'];
        if ($plan_file && isset($plan_counts[$plan_file])) {
            $plan_counts[$plan_file]++;
        }
    }
    
    $stage_stmt = $pdo->query("SELECT DISTINCT stage FROM inspection_stages ORDER BY stage");
    $all_stages = $stage_stmt ? $stage_stmt->fetchAll(PDO::FETCH_COLUMN) : [];

} catch (Exception $e) {
    error_log("DB Error in inspection_dashboard_mobile.php: " . $e->getMessage());
    // Initialize empty arrays to prevent errors
    $data_by_status_and_type = ['my_tasks' => [], 'in_progress' => [], 'awaiting' => [], 'has_ok' => [], 'rejected' => [], 'all' => []];
    $all_plan_files = [];
    $all_stages = [];
    $plan_counts = [];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeHtml($pageTitle); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
                 url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
                 url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
        }
        :root {
            --primary: #3498db; --background: #f4f7f6; --surface: #ffffff;
            --text-primary: #2c3e50; --text-secondary: #7f8c8d; --border: #e0e0e0;
            --shadow: 0 2px 5px rgba(0,0,0,0.08);
        }
        body { font-family: "Samim", sans-serif; background-color: var(--background); margin: 0; }
        .container { padding: 1rem; max-width: 100%; }
        .card { background: var(--surface); border-radius: 8px; box-shadow: var(--shadow); margin-bottom: 1rem; padding: 1rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
        .card-title { font-size: 1.25rem; font-weight: bold; color: var(--text-primary); }
        .card-content { display: none; padding-top: 1rem; border-top: 1px solid var(--border); margin-top: 1rem; }
        .card.active .card-content { display: block; }
        .card.active .icon-toggle { transform: rotate(180deg); }
        .icon-toggle { transition: transform 0.3s; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { font-weight: bold; display: block; margin-bottom: 0.5rem; }
        .form-group select, .form-group input { width: 100%; padding: 0.75rem; border-radius: 6px; border: 1px solid var(--border); font-size: 1rem; box-sizing: border-box; }
        .btn { padding: 0.5rem 1rem; border-radius: 6px; border: none; color: white; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; }
        .button-group { display: flex; flex-wrap: wrap; gap: 5px; }
        .results-info { margin: 1rem 0; font-weight: bold; text-align: center; }
        .inspection-card { padding: 1rem; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 1rem; }
        .inspection-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .element-name { font-size: 1.1rem; font-weight: bold; }
        .element-name a { color: var(--primary); text-decoration: none; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; color: white; }
        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-size: 0.9rem; color: var(--text-secondary); }
        .history-toggle { color: var(--primary); cursor: pointer; text-align: center; margin-top: 1rem; padding-top: 0.5rem; border-top: 1px dashed var(--border); }
        .parts-status { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 5px; }
        .part-badge { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; color: white; }
        .gfrc-parts-container { display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee; }
        .main-tabs { display: flex; overflow-x: auto; -webkit-overflow-scrolling: touch; background: #e9ecef; border-radius: 8px; padding: 5px; margin-bottom: 1rem; }
        .main-tab-button { white-space: nowrap; padding: 10px 15px; border: none; background: transparent; font-family: inherit; font-size: 0.9rem; border-radius: 6px; cursor: pointer; }
        .main-tab-button.active { background: var(--primary); color: white; font-weight: bold; }
        .badge { font-size: 0.7rem; padding: 2px 6px; border-radius: 8px; margin-right: 5px; }
        .main-tab-content { display: none; }
        .main-tab-content.active { display: block; }
        .badge-success { background-color: #28a745; color: white; } 
        .badge-warning { background-color: #ffc107; color: #212529; }
        .badge-danger { background-color: #dc3545; color: white; } 
        .badge-secondary { background-color: #6c757d; color: white; }
        .badge-info { background-color: #17a2b8; color: white; } 
        .badge-primary { background-color: #007bff; color: white; }
        .no-data-message { text-align: center; color: var(--text-secondary); font-style: italic; padding: 2rem; }
        .error-message { color: #dc3545; background-color: #f8d7da; padding: 1rem; border-radius: 6px; margin: 1rem 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo escapeHtml($pageTitle); ?></h1>

        <!-- Map Selection -->
        <div class="card">
             <div class="form-group">
                <label>انتخاب نقشه:</label>
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
                <label>مشاهده وضعیت‌ها در نقشه:</label>
                <div class="button-group">
                    <button class="btn report-btn" data-status="Ready for Inspection" style="background-color: #ffc107; color: black; flex: 1;">آماده</button>
                    <button class="btn report-btn" data-status="Repair" style="background-color: #17a2b8; color: white; flex: 1;">تعمیر</button>
                    <button class="btn report-btn" data-status="Reject" style="background-color: #dc3545; color: white; flex: 1;">رد</button>
                    <button class="btn report-btn" data-status="OK" style="background-color: #28a745; color: white; flex: 1;">تایید</button>
                </div>
            </div>
        </div>

        <!-- Filters Accordion -->
        <div class="card" id="filters-card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-filter"></i> فیلترها و جستجو</h2>
                <i class="fas fa-chevron-down icon-toggle"></i>
            </div>
            <div class="card-content">
                <div class="form-group">
                    <label>جستجو بر اساس نام المان:</label>
                    <input type="text" id="text-filter" placeholder="مثال: Z01-CU-...">
                </div>
                 <div class="form-group">
                    <label>فیلتر مرحله:</label>
                    <select id="stage-filter">
                        <option value="">همه مراحل</option>
                        <?php foreach($all_stages as $stage): ?>
                        <option value="<?php echo escapeHtml($stage); ?>"><?php echo escapeHtml($stage); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Main Tabs -->
        <div class="main-tabs">
             <?php
                $tab_titles = [
                    'my_tasks' => 'کارهای من', 
                    'in_progress' => 'نیاز به تعمیر', 
                    'awaiting' => 'منتظر بازرسی', 
                    'has_ok' => 'دارای تاییدیه', 
                    'rejected' => 'رد شده', 
                    'all' => 'همه موارد'
                ];
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

        <!-- Tab Content -->
        <?php foreach ($data_by_status_and_type as $status_key => $element_types_group): ?>
        <div class="main-tab-content <?php echo $status_key === 'my_tasks' ? 'active' : ''; ?>" id="main-tab-<?php echo $status_key; ?>">
            <?php if (empty($element_types_group)): ?>
                <div class="card"><p class="no-data-message">موردی در این دسته یافت نشد.</p></div>
            <?php else: ?>
                <?php foreach ($element_types_group as $type => $inspections_group): ?>
                    <h2><?php echo getElementTypeDisplayName($type); ?></h2>
                     <?php foreach ($inspections_group as $key => $item): ?>
                        <?php if ($type === 'GFRC'): // GFRC Panel Card ?>
                            <?php
                                $panel_data = $item['element_data'];
                                $parts = $item['parts'];
                                $element_id = $panel_data['element_id'];
                                $expected_parts = getGFRCParts($element_id, $element_orientations);
                                $completion_percentage = $item['completion_percentage'];
                            ?>
                            <div class="inspection-card" data-name="<?php echo escapeHtml($element_id); ?>" data-stage="<?php echo escapeHtml($panel_data['stage_name']); ?>">
                                 <div class="inspection-header">
                                    <div class="element-name"><?php echo escapeHtml($element_id); ?></div>
                                    <span class="status-badge badge-secondary">پنل GFRC</span>
                                </div>
                                 <div class="details-grid">
                                    <div><strong>مرحله:</strong> <?php echo escapeHtml($panel_data['stage_name']); ?></div>
                                    <div><strong>بلوک:</strong> <?php echo escapeHtml($panel_data['block']); ?></div>
                                </div>
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
                                <div class="history-toggle" onclick="toggleHistory(this)">نمایش/پنهان کردن قطعات</div>
                                <div class="gfrc-parts-container">
                                    <?php foreach($parts as $part_item): ?>
                                        <div class="inspection-card" data-status="<?php echo escapeHtml(get_status_persian($part_item['status'])); ?>">
                                            <div class="inspection-header">
                                                <div class="element-name">
                                                    <a href="<?php echo sprintf("/ghom/index.php?plan=%s&element_id=%s", urlencode($part_item['plan_file']), urlencode($part_item['element_id'] . '-' . $part_item['part_name'])); ?>" target="_blank">
                                                        <?php echo escapeHtml($part_item['part_name']); ?>
                                                    </a>
                                                </div>
                                                <span class="status-badge <?php echo get_status_badge_class($part_item['status']); ?>">
                                                    <?php echo escapeHtml(get_status_persian($part_item['status'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: // Regular Element Card ?>
                            <?php
                                $element_display_name = $item['element_id'] . (!empty($item['part_name']) ? ' - ' . $item['part_name'] : '');
                                $element_link_id = $item['element_id'] . (!empty($item['part_name']) ? '-' . $item['part_name'] : '');
                                $deep_link = sprintf("/ghom/index.php?plan=%s&element_id=%s", urlencode($item['plan_file']), urlencode($element_link_id));
                            ?>
                            <div class="inspection-card" data-status="<?php echo escapeHtml(get_status_persian($item['status'])); ?>" data-name="<?php echo escapeHtml($element_display_name); ?>" data-stage="<?php echo escapeHtml($item['stage_name']); ?>">
                                <div class="inspection-header">
                                    <div class="element-name">
                                        <a href="<?php echo $deep_link; ?>" target="_blank">
                                            <?php echo escapeHtml($element_display_name); ?>
                                        </a>
                                    </div>
                                    <span class="status-badge <?php echo get_status_badge_class($item['status']); ?>">
                                        <?php echo escapeHtml(get_status_persian($item['status'])); ?>
                                    </span>
                                </div>
                                <div class="details-grid">
                                    <div><strong>مرحله:</strong> <?php echo escapeHtml($item['stage_name']); ?></div>
                                    <div><strong>بلوک:</strong> <?php echo escapeHtml($item['block']); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if required elements exist before proceeding
            const textFilter = document.getElementById('text-filter');
            const stageFilter = document.getElementById('stage-filter');
            const filtersCard = document.getElementById('filters-card');
            const reportPlanSelect = document.getElementById('report-plan-select');

            if (!textFilter || !stageFilter) {
                console.error('Required filter elements not found');
                return;
            }

            function applyFilters() {
                const textValue = textFilter.value.toLowerCase();
                const stageValue = stageFilter.value;
                
                let visibleCount = 0;
                const activeTabContent = document.querySelector('.main-tab-content.active');
                if (!activeTabContent) return;
                
                const activeCards = activeTabContent.querySelectorAll('.inspection-card');

                activeCards.forEach(card => {
                    // Skip cards inside GFRC parts containers unless they're being viewed
                    if (card.closest('.gfrc-parts-container')) return;

                    const nameMatch = !textValue || (card.dataset.name && card.dataset.name.toLowerCase().includes(textValue));
                    const stageMatch = !stageValue || card.dataset.stage === stageValue;
                    
                    if (nameMatch && stageMatch) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Update results info if it exists
                const resultsInfo = document.querySelector('.results-info');
                if (resultsInfo) {
                    resultsInfo.textContent = `${visibleCount} مورد یافت شد`;
                }
            }

            // Global function for toggling GFRC parts
            window.toggleHistory = function(element) {
                const container = element.nextElementSibling;
                if (container && container.classList.contains('gfrc-parts-container')) {
                    const isVisible = container.style.display === 'block';
                    container.style.display = isVisible ? 'none' : 'block';
                    element.textContent = isVisible ? 'نمایش قطعات' : 'پنهان کردن قطعات';
                }
            }
            
            // Filters card toggle
            if (filtersCard) {
                filtersCard.addEventListener('click', function(e) {
                    if (e.target.closest('.card-header')) {
                        this.classList.toggle('active');
                    }
                });
            }
            
            // Main tab switching
            document.querySelectorAll('.main-tab-button').forEach(button => {
                button.addEventListener('click', () => {
                    // Remove active from all tabs and content
                    document.querySelectorAll('.main-tab-button').forEach(btn => btn.classList.remove('active'));
                    document.querySelectorAll('.main-tab-content').forEach(content => content.classList.remove('active'));
                    
                    // Add active to clicked tab
                    button.classList.add('active');
                    const targetContent = document.getElementById(button.dataset.tabContent);
                    if (targetContent) {
                        targetContent.classList.add('active');
                    }
                    
                    // Reapply filters to new content
                    setTimeout(applyFilters, 50);
                });
            });

            // Filter event listeners
            textFilter.addEventListener('input', applyFilters);
            stageFilter.addEventListener('change', applyFilters);
            
            // --- MAP VIEWER BUTTONS ---
            function openViewer(allStatuses = false) {
                if (!reportPlanSelect) {
                    alert('خطا: انتخابگر نقشه یافت نشد.');
                    return;
                }
                
                const planFile = reportPlanSelect.value;
                if (!planFile) {
                    alert('لطفا ابتدا یک نقشه را انتخاب کنید.');
                    return;
                }
                
                let url = `/ghom/viewer.php?plan=${encodeURIComponent(planFile)}`;
                if (!allStatuses && this.dataset && this.dataset.status) {
                    const status = this.dataset.status;
                    url += `&status=${encodeURIComponent(status)}`;
                }
                window.open(url, '_blank');
            }

            // Bind map viewer buttons
            document.querySelectorAll('.report-btn').forEach(button => {
                button.addEventListener('click', function() {
                    openViewer.call(this, false);
                });
            });
            
            // Initial setup
            setTimeout(() => {
                applyFilters();
            }, 100);
        });
    </script>
</body>
</html>