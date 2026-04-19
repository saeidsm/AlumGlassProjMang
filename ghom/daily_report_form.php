<?php
// daily_report_form.php - LOCKS APPROVED FOR NON-SUPERUSER
ob_start(); // Start output buffering
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

$pdo = getProjectDBConnection('ghom');

// --- INTERNAL API ---
if (isset($_GET['api_action'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        if ($_GET['api_action'] === 'get_db_zones') {
            $stmt = $pdo->query("SELECT DISTINCT zone_name FROM elements WHERE zone_name IS NOT NULL AND zone_name != '' ORDER BY zone_name ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        }
        elseif ($_GET['api_action'] === 'get_db_floors') {
            $zone = $_POST['zone'] ?? '';
            $stmt = $pdo->prepare("SELECT DISTINCT floor_level FROM elements WHERE zone_name = ? ORDER BY floor_level ASC");
            $stmt->execute([$zone]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        }
        elseif ($_GET['api_action'] === 'check_permits') {
            $date_jalali = $_POST['date'] ?? '';
            $contractor = $_POST['contractor'] ?? '';
            
            $g_date = '';
            if (!empty($date_jalali)) {
                $parts = explode('/', $date_jalali);
                if (count($parts) === 3) {
                    $g = jalali_to_gregorian((int)$parts[0], (int)$parts[1], (int)$parts[2]);
                    $g_date = $g[0] . '-' . sprintf('%02d', $g[1]) . '-' . sprintf('%02d', $g[2]);
                }
            }

            if (!$g_date || !$contractor) {
                echo json_encode(['success' => false, 'message' => 'تاریخ یا پیمانکار نامعتبر است']);
                exit;
            }

            // Load JSON Map
            $json_path = __DIR__ . '/assets/js/allinone.json';
            $zone_map = [];
            if (file_exists($json_path)) {
                $json_data = json_decode(file_get_contents($json_path), true);
                if (isset($json_data['regions'])) {
                    foreach ($json_data['regions'] as $region) {
                        if (isset($region['zones'])) {
                            foreach ($region['zones'] as $z) {
                                $zone_map[$z['svgFile']] = $z['label'];
                            }
                        }
                    }
                }
            }

            $sql = "SELECT e.plan_file, COUNT(i.inspection_id) as panel_count, MAX(i.contractor_notes) as note, MAX(e.block) as block
                    FROM inspections i JOIN elements e ON i.element_id = e.element_id
                    WHERE i.contractor_date = ? AND e.contractor = ? GROUP BY e.plan_file";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$g_date, $contractor]);
            $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($permits as &$p) {
                $filename = $p['plan_file'];
                $p['zone_label'] = $zone_map[$filename] ?? $filename;
            }
            echo json_encode(['success' => true, 'data' => $permits]);
        }
        elseif ($_GET['api_action'] === 'get_element_stats') {
            $zone = $_POST['zone'] ?? '';
            $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(area_sqm) as total_area FROM elements WHERE zone_name = ?");
            $stmt->execute([$zone]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $result]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- UI HEADER ---
require_once __DIR__ . '/header.php';
ob_end_flush();

// Weather Service
$weather_data = ['temp' => '', 'condition' => [], 'text' => ''];
if (file_exists(__DIR__ . '/includes/WeatherService.php') && !isset($_GET['id'])) {
    require_once __DIR__ . '/includes/WeatherService.php';
    try {
        $ws = new WeatherService(['provider' => 'openmeteo']);
        $w = $ws->getCurrentWeather(34.6401, 50.8764);
        if ($w) {
            $weather_data['temp'] = $w['temperature'];
            $weather_data['text'] = $w['condition_fa'] . " (دما: {$w['temperature']}°C، باد: {$w['wind_speed']} km/h)";
            $cond = $w['condition'];
            if (strpos($cond, 'clear') !== false) $weather_data['condition'][] = 'آفتابی';
            if (strpos($cond, 'cloud') !== false) $weather_data['condition'][] = 'ابری';
            if (strpos($cond, 'rain') !== false) $weather_data['condition'][] = 'بارندگی';
            if (strpos($cond, 'snow') !== false) $weather_data['condition'][] = 'برف';
            if (strpos($cond, 'wind') !== false) $weather_data['condition'][] = 'باد شدید';
        }
    } catch (Exception $e) { }
}

// --- LOAD DB LISTS (FIXED SECTION) ---

// 1. Fetch Constants (Roles, Tools, Materials, Units)
// This query was missing in your code!
$const_stmt = $pdo->query("SELECT * FROM project_constants");
$all_constants = $const_stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper to extract column by type
function get_list_by_type($data, $type) {
    $out = [];
    foreach ($data as $row) {
        if ($row['type'] === $type) $out[] = $row['name'];
    }
    return $out;
}

$tools_list = get_list_by_type($all_constants, 'tool');
$material_cats = get_list_by_type($all_constants, 'material_cat');
$unit_list = get_list_by_type($all_constants, 'unit');
$default_roles_list = get_list_by_type($all_constants, 'role'); // List of role names

// 2. Fetch Activities (Grouped for Select Box)
$act_query = $pdo->query("SELECT * FROM project_activities ORDER BY category ASC, name ASC");
$raw_activities = $act_query->fetchAll(PDO::FETCH_ASSOC);
$grouped_activities = [];
foreach ($raw_activities as $act) {
    $grouped_activities[$act['category']][] = $act;
}
$act_list = $raw_activities; // Keep for legacy compatibility if needed


// Setup
$report_id = $_GET['id'] ?? null;
$user_role = $_SESSION['role'];
$is_contractor = in_array($user_role, ['cat', 'car', 'coa', 'crs']);
$is_consultant = in_array($user_role, ['admin', 'superuser']);
$is_superuser = ($user_role === 'superuser');

// Load Contractors
$config_path = __DIR__ . '/assets/js/allinone.json';
$contractor_list = [];
$project_json_data = [];
if (file_exists($config_path)) {
    $project_json_data = json_decode(file_get_contents($config_path), true);
    if (isset($project_json_data['regions'])) {
        foreach ($project_json_data['regions'] as $r) {
            if (!empty($r['contractor'])) $contractor_list[] = $r['contractor'];
        }
        $contractor_list = array_unique($contractor_list);
    }
}

// Default Data
$report = [
    'id' => '', 'report_date' => jdate('Y/m/d'), 'contractor_fa_name' => '', 'block_name' => '',
    'weather_list' => !empty($weather_data['condition']) ? json_encode($weather_data['condition'], JSON_UNESCAPED_UNICODE) : '[]',
    'temp_max' => $weather_data['temp'], 'temp_min' => $weather_data['temp'], 'problems_and_obstacles' => '',
    'status' => 'Draft', 'consultant_notes' => '',
    'consultant_note_personnel' => '', 'consultant_note_machinery' => '',
    'consultant_note_materials' => '', 'consultant_note_activities' => '',
    'digital_signature_path' => '', 'signed_scan_path' => ''
];

$activities = []; $personnel_data = []; $machinery = [];
$materials_in = []; $materials_out = [];
$misc_permits = []; $misc_tests = []; $misc_hse = [];

// Load Existing Report
if ($report_id) {
    $stmt = $pdo->prepare("SELECT * FROM daily_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched) {
        $report = $fetched;
        if (!empty($report['report_date'])) {
            list($y, $m, $d) = explode('-', $report['report_date']);
            $j = gregorian_to_jalali((int)$y, (int)$m, (int)$d);
            $report['report_date'] = $j[0] . '/' . sprintf('%02d', $j[1]) . '/' . sprintf('%02d', $j[2]);
        }
    }

    $activities = $pdo->prepare("SELECT dra.*, pa.name as act_name FROM daily_report_activities dra LEFT JOIN project_activities pa ON dra.activity_id = pa.id WHERE report_id = ?");
    $activities->execute([$report_id]);
    $activities = $activities->fetchAll(PDO::FETCH_ASSOC);

    $pers_stmt = $pdo->prepare("SELECT * FROM daily_report_personnel WHERE report_id = ?");
    $pers_stmt->execute([$report_id]);
    $personnel_data = $pers_stmt->fetchAll(PDO::FETCH_ASSOC);

    $mach_stmt = $pdo->prepare("SELECT * FROM daily_report_machinery WHERE report_id = ?");
    $mach_stmt->execute([$report_id]);
    $machinery = $mach_stmt->fetchAll(PDO::FETCH_ASSOC);

    $mat_stmt = $pdo->prepare("SELECT * FROM daily_report_materials WHERE report_id = ?");
    $mat_stmt->execute([$report_id]);
    foreach ($mat_stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        if ($m['type'] == 'IN') $materials_in[] = $m; else $materials_out[] = $m;
    }

    $misc_stmt = $pdo->prepare("SELECT * FROM daily_report_misc WHERE report_id = ?");
    $misc_stmt->execute([$report_id]);
    foreach ($misc_stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        if ($m['type'] == 'PERMIT') $misc_permits[] = $m;
        elseif ($m['type'] == 'TEST') $misc_tests[] = $m;
        elseif ($m['type'] == 'HSE') $misc_hse[] = $m;
    }
}

// Pre-fill Default Personnel
if (empty($personnel_data)) {
      foreach ($default_roles_list as $role) {
        $personnel_data[] = ['id' => '', 'role_name' => $role, 'count' => 0, 'consultant_comment' => ''];
    }
}

// Lock Logic
$is_approved = ($report['status'] === 'Approved');
$can_edit_contractor = (!$is_approved && $is_contractor);

// Consultant Edit Rules:
// 1. Must be a consultant (Admin/Superuser)
// 2. Report must exist
// 3. AND (Report NOT approved OR User IS Superuser)
$can_edit_consultant = ($is_consultant && $report_id && (!$is_approved || $is_superuser));

?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <script src="/ghom/assets/js/bootstrap.bundle.min.js"></script>
    <script src="/ghom/assets/js/signature_pad.umd.min.js"></script>
    <link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    <script src="/ghom/assets/js/jalalidatepicker.min.js"></script>
    <style>
        .section-card { border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px; background: white; overflow: visible !important; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .section-header { background-color: #f8f9fa; padding: 10px 15px; font-weight: bold; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        
        /* CONSULTANT REVIEW STYLES */
        .consultant-review-toggle { position: fixed; top: 100px; left: 20px; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .contractor-row { background-color: #fff; transition: background-color 0.2s; }
        .contractor-row.has-consultant-comment { background-color: #fff3cd; }
        
        .consultant-comment-row { background-color: #ffe5e5 !important; border-left: 4px solid #dc3545; }
        .consultant-input { background-color: #fff5f5; border: 1px solid #f5c6cb; }
        .consultant-input:focus { background-color: white; border-color: #dc3545; box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25); }
        
        /* FIXED: REMOVED display:none to ensure contractors see these columns */
        .consultant-col { background-color: #f8f9fa; }
        
        .table-scroll-wrapper { overflow-x: auto; }
        .complex-table th { background-color: #e9ecef; text-align: center; vertical-align: middle; font-size: 0.85rem; white-space: nowrap; }
        .complex-table td { padding: 0; vertical-align: middle; min-width: 80px; }
        .complex-table input, .complex-table select { border: none; width: 100%; padding: 8px; background: transparent; text-align: center; font-size: 0.9rem; }
        .complex-table input:focus { outline: 2px solid #86b7fe; background: white; }
        .bg-cumulative { background-color: #fdfdfe; }
        canvas { border: 2px dashed #ccc; border-radius: 5px; width: 100%; height: 150px; background: #fff; cursor: crosshair; }
        .table-input { width: 100%; border: none; background: transparent; text-align: center; }
        .weather-api-info { font-size: 0.85em; color: #0d6efd; margin-bottom: 5px; }
        
        .datepicker-plot-area { z-index: 9999 !important; }
        input[data-jdp] { cursor: pointer; background-color: white !important; }

        /* NEW: Red Strikethrough Style */
        .crossed-out {
            text-decoration: line-through !important;
            color: #dc3545 !important;
            font-weight: bold;
            opacity: 0.7;
        }
    </style>
</head>

<body class="bg-light">

    <datalist id="toolsList">
        <?php foreach ($tools_list as $tool) echo "<option value='$tool'>"; ?>
    </datalist>

    <!-- CONSULTANT MODE TOGGLE -->
    <?php if ($is_consultant): ?>
    <div class="consultant-review-toggle">
        <div class="card">
            <div class="card-body p-2">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="reviewModeToggle" onchange="toggleReviewMode()">
                    <label class="form-check-label small" for="reviewModeToggle"><i class="fas fa-user-check"></i> حالت بررسی</label>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="container-fluid px-4 my-4">
        <!-- WRAPPER FORM (Fixes previous nesting issue) -->
        <form id="mainForm" enctype="multipart/form-data">
            <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">
            <input type="hidden" name="is_consultant" value="<?php echo $is_consultant ? '1' : '0'; ?>">
            <!-- Hidden input for signature data -->
            <input type="hidden" name="digital_signature_data" id="digital_signature_data">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold text-primary">📄 گزارش روزانه عملیات اجرایی نما</h3>
                <div class="d-flex gap-2 align-items-center">
                    <!-- SETTINGS LINK ADDED HERE -->
                    <?php if ($is_consultant): ?>
                        <a href="settings_all.php" class="btn btn-outline-secondary btn-sm" target="_blank">
                            <i class="fas fa-cogs"></i> تنظیمات لیست‌ها
                        </a>
                    <?php endif; ?>

                    <?php if ($report_id): ?>
                        <a href="daily_report_print.php?id=<?php echo $report_id; ?>" target="_blank" class="btn btn-outline-success btn-sm"><i class="fas fa-print"></i> چاپ و امضا</a>
                    <?php endif; ?>
                    <span class="badge <?php echo $is_approved ? 'bg-success' : 'bg-warning text-dark'; ?> fs-6"><?php echo $report['status']; ?></span>
                </div>
            </div>

            <!-- SECTION 1: HEADER -->
            <div class="section-card">
                <div class="section-header">۱. اطلاعات پایه و وضعیت جوی</div>
                <div class="p-3 row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">تاریخ (شمسی)</label>
                        <input type="text" name="report_date" data-jdp class="form-control" value="<?php echo $report['report_date']; ?>" <?php echo $can_edit_contractor ? '' : 'readonly'; ?> required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">نام پیمانکار</label>
                        <select name="contractor_fa_name" class="form-select" <?php echo $can_edit_contractor ? '' : 'disabled'; ?>>
                            <option value="">انتخاب کنید...</option>
                            <?php foreach ($contractor_list as $cont): ?>
                                <option value="<?php echo $cont; ?>" <?php echo $report['contractor_fa_name'] == $cont ? 'selected' : ''; ?>><?php echo $cont; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">بلوک</label>
                        <select name="block_name" class="form-select" <?php echo $can_edit_contractor ? '' : 'disabled'; ?>>
                            <option value="A" <?php echo $report['block_name']=='A'?'selected':''; ?>>بلوک A</option>
                            <option value="B" <?php echo $report['block_name']=='B'?'selected':''; ?>>بلوک B</option>
                            <option value="C" <?php echo $report['block_name']=='C'?'selected':''; ?>>بلوک C</option>
                            <option value="Common" <?php echo $report['block_name']=='Common'?'selected':''; ?>>بلوک عمومی</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">وضعیت جوی</label>
                        <?php if (!empty($weather_data['text'])): ?><div class="weather-api-info"><i class="fas fa-cloud"></i> <?php echo $weather_data['text']; ?></div><?php endif; ?>
                        <div class="d-flex gap-2">
                            <input type="text" name="temp_max" class="form-control form-control-sm" placeholder="حداکثر °C" value="<?php echo $report['temp_max']; ?>" <?php echo $can_edit_contractor?'':'readonly'; ?>>
                            <input type="text" name="temp_min" class="form-control form-control-sm" placeholder="حداقل °C" value="<?php echo $report['temp_min']; ?>" <?php echo $can_edit_contractor?'':'readonly'; ?>>
                        </div>
                        <div class="mt-2 d-flex gap-3 flex-wrap">
                            <?php
                            $w_opts = ['آفتابی', 'نیمه ابری', 'ابری', 'بارندگی', 'برف', 'باد شدید'];
                            $saved_w = json_decode($report['weather_list'] ?? '[]', true);
                            foreach ($w_opts as $opt): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="weather_list[]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $saved_w) ? 'checked' : ''; ?> <?php echo $can_edit_contractor ? '' : 'disabled'; ?>>
                                    <label class="form-check-label small"><?php echo $opt; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: PERSONNEL -->
            <div class="section-card">
                <div class="section-header">
                    <span>۲. نیروی انسانی</span>
                    <?php if ($can_edit_contractor): ?><button type="button" class="btn btn-sm btn-success" onclick="addPersonnelRow()">+</button><?php endif; ?>
                </div>
                <div style="max-height: 600px; overflow-y: auto;">
                    <table class="table table-bordered mb-0 table-sm align-middle">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th rowspan="2" style="width: 40px;">#</th>
                                <th rowspan="2">سمت</th>
                                <th rowspan="2" style="width: 100px;">تعداد (پیمانکار)</th>
                                <th colspan="2" class="bg-warning bg-opacity-10 consultant-col text-center">نظارت</th>
                                <th rowspan="2" style="width: 40px;"></th>
                            </tr>
                            <tr>
                                <th class="consultant-col text-danger" style="width: 80px;">تعداد تایید</th>
                                <th class="consultant-col text-danger">توضیحات</th>
                            </tr>
                        </thead>
                        <tbody id="personnelBody">
                            <?php $p_idx = 0; foreach ($personnel_data as $p): ?>
                                <tr class="contractor-row" data-row-id="<?php echo $p['id']; ?>">
                                    <td><?php echo $p_idx + 1; ?></td>
                                    <td>
                                        <input type="hidden" name="personnel[<?php echo $p_idx; ?>][id]" value="<?php echo $p['id']; ?>">
                                        <input type="text" name="personnel[<?php echo $p_idx; ?>][role_name]" class="form-control form-control-sm border-0" value="<?php echo $p['role_name']; ?>" <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                    </td>
                                    <td>
                                        <!-- CONTRACTOR INPUT (Target for Crossout) -->
                                        <input type="number" name="personnel[<?php echo $p_idx; ?>][count]" 
                                               class="form-control form-control-sm border-0 text-center target-count <?php echo !empty($p['consultant_count']) ? 'crossed-out' : ''; ?>" 
                                               value="<?php echo $p['count']; ?>" 
                                               <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                    </td>
                                    <td class="consultant-col bg-warning bg-opacity-10">
                                        <!-- CONSULTANT INPUT (Trigger) -->
                                        <input type="number" name="personnel[<?php echo $p_idx; ?>][consultant_count]" 
                                               class="form-control form-control-sm text-center fw-bold text-danger consultant-input" 
                                               value="<?php echo $p['consultant_count'] ?? ''; ?>" 
                                               placeholder="<?php echo $p['count']; ?>"
                                               oninput="toggleCrossout(this, '.target-count')"
                                               <?php echo $can_edit_consultant?'':'readonly'; ?>>
                                    </td>
                                    <td class="consultant-col bg-warning bg-opacity-10">
                                        <?php if ($can_edit_consultant): ?>
                                            <textarea name="personnel[<?php echo $p_idx; ?>][consultant_comment]" class="form-control form-control-sm consultant-input" rows="1"><?php echo $p['consultant_comment']; ?></textarea>
                                        <?php else: ?>
                                            <div class="small text-danger"><?php echo $p['consultant_comment']; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php if ($can_edit_contractor): ?><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button><?php endif; ?></td>
                                </tr>
                            <?php $p_idx++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_consultant): ?>
                    <div class="p-3 bg-light border-top">
                        <label class="form-label fw-bold small">💬 یادداشت کلی نیروی انسانی:</label>
                        <textarea name="consultant_note_personnel" class="form-control form-control-sm" rows="2"><?php echo $report['consultant_note_personnel']; ?></textarea>
                    </div>
                <?php elseif (!empty($report['consultant_note_personnel'])): ?>
                    <div class="p-3 bg-warning bg-opacity-10 border-top"><strong>💬 یادداشت مشاور:</strong> <?php echo nl2br(htmlspecialchars($report['consultant_note_personnel'])); ?></div>
                <?php endif; ?>
            </div>

            <!-- SECTION 3: MACHINERY -->
            <div class="section-card">
                <div class="section-header">
                    <span>۳. ماشین آلات و تجهیزات</span>
                    <?php if ($can_edit_contractor): ?><button type="button" class="btn btn-sm btn-success" onclick="addMachineryRow()">+</button><?php endif; ?>
                </div>
                <div style="max-height: 600px; overflow-y: auto;">
                    <table class="table table-bordered mb-0 table-sm align-middle">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th rowspan="2" style="width: 40px;">#</th>
                                <th rowspan="2">نام دستگاه</th>
                                <th colspan="2" class="text-center">پیمانکار</th>
                                <th colspan="2" class="bg-warning bg-opacity-10 consultant-col text-center">نظارت</th>
                                <th rowspan="2" style="width: 40px;"></th>
                            </tr>
                            <tr>
                                <th style="width: 70px;">کل</th>
                                <th style="width: 70px;">فعال</th>
                                <th class="consultant-col text-danger" style="width: 80px;">فعال تایید</th>
                                <th class="consultant-col text-danger">توضیحات</th>
                            </tr>
                        </thead>
                        <tbody id="machineryBody">
                            <?php $m_idx = 0; foreach ($machinery as $m): ?>
                                <tr>
                                    <td><?php echo $m_idx + 1; ?></td>
                                    <td>
                                        <input type="hidden" name="machinery[<?php echo $m_idx; ?>][id]" value="<?php echo $m['id']; ?>">
                                        <input type="text" name="machinery[<?php echo $m_idx; ?>][machine_name]" list="toolsList" class="form-control form-control-sm border-0" value="<?php echo $m['machine_name']; ?>" <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                    </td>
                                    <td><input type="number" name="machinery[<?php echo $m_idx; ?>][total_count]" class="form-control form-control-sm border-0 text-center" value="<?php echo $m['total_count']; ?>" <?php echo $can_edit_contractor?'':'readonly'; ?>></td>
                                    
                                    <!-- Contractor Active (Target) -->
                                    <td>
                                        <input type="number" name="machinery[<?php echo $m_idx; ?>][active_count]" 
                                               class="form-control form-control-sm border-0 text-center target-active <?php echo !empty($m['consultant_active_count']) ? 'crossed-out' : ''; ?>" 
                                               value="<?php echo $m['active_count']; ?>" 
                                               <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                    </td>
                                    
                                    <!-- Consultant Active (Trigger) -->
                                    <td class="consultant-col bg-warning bg-opacity-10">
                                        <input type="number" name="machinery[<?php echo $m_idx; ?>][consultant_active_count]" 
                                               class="form-control form-control-sm text-center fw-bold text-danger consultant-input" 
                                               value="<?php echo $m['consultant_active_count'] ?? ''; ?>"
                                               placeholder="<?php echo $m['active_count']; ?>" 
                                               oninput="toggleCrossout(this, '.target-active')"
                                               <?php echo $can_edit_consultant?'':'readonly'; ?>>
                                    </td>
                                    <td class="consultant-col bg-warning bg-opacity-10">
                                        <?php if ($can_edit_consultant): ?>
                                            <textarea name="machinery[<?php echo $m_idx; ?>][consultant_comment]" class="form-control form-control-sm consultant-input" rows="1"><?php echo $m['consultant_comment']; ?></textarea>
                                        <?php else: ?>
                                            <div class="small text-danger"><?php echo $m['consultant_comment']; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php if ($can_edit_contractor): ?><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button><?php endif; ?></td>
                                </tr>
                            <?php $m_idx++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_consultant): ?>
                    <div class="p-3 bg-light border-top">
                        <label class="form-label fw-bold small">💬 یادداشت کلی ماشین آلات:</label>
                        <textarea name="consultant_note_machinery" class="form-control form-control-sm" rows="2"><?php echo $report['consultant_note_machinery']; ?></textarea>
                    </div>
                <?php elseif (!empty($report['consultant_note_machinery'])): ?>
                    <div class="p-3 bg-warning bg-opacity-10 border-top"><strong>💬 یادداشت مشاور:</strong> <?php echo nl2br(htmlspecialchars($report['consultant_note_machinery'])); ?></div>
                <?php endif; ?>
            </div>
  
           <!-- SECTION 4: MATERIALS -->
            <div class="section-card">
                <div class="section-header">۴. مدیریت مصالح (ورود / خروج)</div>
                <div class="row g-0">
                    <!-- INPUT (IN) -->
                    <div class="col-md-6 border-end">
                        <div class="p-2 bg-light text-success fw-bold d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-download"></i> ورودی (IN)</span>
                            <?php if ($can_edit_contractor): ?><button type="button" class="btn btn-sm btn-success py-0" onclick="addMaterial('in')">+</button><?php endif; ?>
                        </div>
                        <table class="table table-bordered mb-0 table-sm align-middle">
                            <tbody id="materialInBody">
                                <?php $mat_in_idx = 0; foreach ($materials_in as $mat): ?>
                                    <tr>
                                        <input type="hidden" name="mat_in[<?php echo $mat_in_idx; ?>][id]" value="<?php echo $mat['id']; ?>">
                                        <td>
                                            <select name="mat_in[<?php echo $mat_in_idx; ?>][category]" class="table-input" style="font-size:0.8em" <?php echo $can_edit_contractor?'':'disabled'; ?>>
                                                <option value="">...</option>
                                                <?php $currentCat = $mat['category'] ?? ''; foreach ($material_cats as $mc) echo "<option value='$mc' ".($currentCat==$mc?'selected':'').">$mc</option>"; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="mat_in[<?php echo $mat_in_idx; ?>][name]" value="<?php echo $mat['material_name']; ?>" class="table-input" placeholder="شرح" <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                            <input type="text" name="mat_in[<?php echo $mat_in_idx; ?>][date]" value="<?php echo $mat['date'] ?? ''; ?>" class="table-input text-muted small" placeholder="تاریخ" data-jdp <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                        </td>
                                        <td>
                                            <div class="d-flex">
                                                <!-- Contractor Qty (Target) -->
                                                <input type="text" name="mat_in[<?php echo $mat_in_idx; ?>][quantity]" 
                                                       class="table-input target-qty <?php echo !empty($mat['consultant_quantity']) ? 'crossed-out' : ''; ?>" 
                                                       value="<?php echo $mat['quantity']; ?>" 
                                                       placeholder="#" 
                                                       <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                                <input type="text" name="mat_in[<?php echo $mat_in_idx; ?>][unit]" value="<?php echo $mat['unit']; ?>" class="table-input" placeholder="واحد" <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                            </div>
                                            <?php if(!empty($mat['file_path'])): ?><a href="<?php echo $mat['file_path']; ?>" target="_blank" class="d-block text-center small">📄 سند</a><?php endif; ?>
                                            <?php if($can_edit_contractor): ?><input type="file" name="mat_in_file_<?php echo $mat_in_idx; ?>" class="form-control form-control-sm mt-1" style="font-size:0.7em"><?php endif; ?>
                                        </td>
                                        <td class="consultant-col bg-warning bg-opacity-10">
                                            <!-- Consultant Qty (Trigger) -->
                                            <input type="text" name="mat_in[<?php echo $mat_in_idx; ?>][consultant_quantity]" 
                                                   value="<?php echo $mat['consultant_quantity'] ?? ''; ?>" 
                                                   class="form-control form-control-sm text-center fw-bold text-danger consultant-input" 
                                                   placeholder="تایید" 
                                                   oninput="toggleCrossout(this, '.target-qty')"
                                                   <?php echo $can_edit_consultant?'':'readonly'; ?>>
                                        </td>
                                        <td class="consultant-col bg-warning bg-opacity-10">
                                            <input type="text" name="mat_in[<?php echo $mat_in_idx; ?>][consultant_comment]" value="<?php echo $mat['consultant_comment'] ?? ''; ?>" class="form-control form-control-sm consultant-input" <?php echo $can_edit_consultant?'':'readonly'; ?>>
                                        </td>
                                        <td><?php if($can_edit_contractor): ?><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button><?php endif; ?></td>
                                    </tr>
                                <?php $mat_in_idx++; endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- OUTPUT (OUT) -->
                    <div class="col-md-6">
                        <div class="p-2 bg-light text-danger fw-bold d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-upload"></i> خروجی (OUT)</span>
                            <?php if ($can_edit_contractor): ?><button type="button" class="btn btn-sm btn-danger py-0" onclick="addMaterial('out')">+</button><?php endif; ?>
                        </div>
                        <table class="table table-bordered mb-0 table-sm align-middle">
                            <tbody id="materialOutBody">
                                <?php $mat_out_idx = 0; foreach ($materials_out as $mat): ?>
                                    <tr>
                                        <input type="hidden" name="mat_out[<?php echo $mat_out_idx; ?>][id]" value="<?php echo $mat['id']; ?>">
                                        <td>
                                            <select name="mat_out[<?php echo $mat_out_idx; ?>][category]" class="table-input" style="font-size:0.8em" <?php echo $can_edit_contractor?'':'disabled'; ?>>
                                                <option value="">...</option>
                                                <?php $currentCat = $mat['category'] ?? ''; foreach ($material_cats as $mc) echo "<option value='$mc' ".($currentCat==$mc?'selected':'').">$mc</option>"; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="mat_out[<?php echo $mat_out_idx; ?>][name]" value="<?php echo $mat['material_name']; ?>" class="table-input" placeholder="شرح" <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                            <input type="text" name="mat_out[<?php echo $mat_out_idx; ?>][date]" value="<?php echo $mat['date'] ?? ''; ?>" class="table-input text-muted small" placeholder="تاریخ" data-jdp <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                        </td>
                                        <td>
                                            <div class="d-flex">
                                                <!-- Contractor Qty (Target) -->
                                                <input type="text" name="mat_out[<?php echo $mat_out_idx; ?>][quantity]" 
                                                       class="table-input target-qty <?php echo !empty($mat['consultant_quantity']) ? 'crossed-out' : ''; ?>" 
                                                       value="<?php echo $mat['quantity']; ?>" 
                                                       placeholder="#" 
                                                       <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                                <input type="text" name="mat_out[<?php echo $mat_out_idx; ?>][unit]" value="<?php echo $mat['unit']; ?>" class="table-input" placeholder="واحد" <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                            </div>
                                            <?php if(!empty($mat['file_path'])): ?><a href="<?php echo $mat['file_path']; ?>" target="_blank" class="d-block text-center small">📄 سند</a><?php endif; ?>
                                            <?php if($can_edit_contractor): ?><input type="file" name="mat_out_file_<?php echo $mat_out_idx; ?>" class="form-control form-control-sm mt-1" style="font-size:0.7em"><?php endif; ?>
                                        </td>
                                        <td class="consultant-col bg-warning bg-opacity-10">
                                            <!-- Consultant Qty (Trigger) -->
                                            <input type="text" name="mat_out[<?php echo $mat_out_idx; ?>][consultant_quantity]" 
                                                   value="<?php echo $mat['consultant_quantity'] ?? ''; ?>" 
                                                   class="form-control form-control-sm text-center fw-bold text-danger consultant-input" 
                                                   placeholder="تایید" 
                                                   oninput="toggleCrossout(this, '.target-qty')"
                                                   <?php echo $can_edit_consultant?'':'readonly'; ?>>
                                        </td>
                                        <td class="consultant-col bg-warning bg-opacity-10">
                                            <input type="text" name="mat_out[<?php echo $mat_out_idx; ?>][consultant_comment]" value="<?php echo $mat['consultant_comment'] ?? ''; ?>" class="form-control form-control-sm consultant-input" <?php echo $can_edit_consultant?'':'readonly'; ?>>
                                        </td>
                                        <td><?php if($can_edit_contractor): ?><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button><?php endif; ?></td>
                                    </tr>
                                <?php $mat_out_idx++; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SECTION 5: ACTIVITIES -->
            <div class="section-card">
                <div class="section-header d-flex justify-content-between">
                    <span>۵. شرح عملیات اجرایی و احجام</span>
                    <?php if ($can_edit_contractor): ?><button type="button" class="btn btn-sm btn-primary" onclick="addActivity()">+ افزودن فعالیت</button><?php endif; ?>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="table table-bordered complex-table mb-0">
                        <thead>
                            <tr>
                                <th rowspan="2">ردیف</th>
                                <th rowspan="2" style="min-width:200px">شرح فعالیت</th>
                                <th rowspan="2" style="min-width:100px">موقعیت (محور)</th>
                                <th rowspan="2" style="min-width:120px">زون</th>
                                <th rowspan="2" style="min-width:80px">طبقه</th>
                                <th rowspan="2">واحد</th>
                                <th colspan="3" class="bg-white">روزانه (پیمانکار)</th>
                                <th colspan="3" class="bg-warning bg-opacity-10 consultant-col">تایید نظارت</th>
                                <th colspan="4" class="bg-cumulative">تجمیعی</th>
                                <th rowspan="2"></th>
                            </tr>
                            <tr>
                                <th>تعداد</th><th>متراژ</th><th>نفرات</th>
                                <th class="consultant-col text-danger">تعداد تایید</th>
                                <th class="consultant-col text-danger">متراژ تایید</th>
                                <th class="consultant-col text-danger">توضیحات</th>
                                <th>باز</th><th>متراژ بازگشایی</th><th class="text-danger">ریجکت</th><th class="text-success">نصب</th>
                            </tr>
                        </thead>
                        <tbody id="activityBody">
                            <?php $i = 1; foreach($activities as $idx => $act): ?>
                                <tr>
                                    <td class="text-center"><?php echo $i++; ?></td>
                                    <input type="hidden" name="activities[<?php echo $idx; ?>][id]" value="<?php echo $act['id']; ?>">
                                    <td>
    <select name="activities[<?php echo $idx; ?>][activity_id]" <?php echo $can_edit_contractor?'':'disabled'; ?> class="form-select form-select-sm" style="min-width: 200px;">
        <option value="">انتخاب کنید...</option>
        <?php foreach($grouped_activities as $cat_name => $cat_items): ?>
            <optgroup label="<?php echo htmlspecialchars($cat_name); ?>">
                <?php foreach($cat_items as $o): ?>
                    <option value="<?php echo $o['id']; ?>" <?php echo ($o['id'] == $act['activity_id'] ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($o['name']); ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
        <?php endforeach; ?>
    </select>
</td>
                                    <td><input type="text" name="activities[<?php echo $idx; ?>][location_facade]" value="<?php echo $act['location_facade']; ?>" class="table-input" <?php echo $can_edit_contractor?'':'readonly'; ?>></td>
                                    <td>
                                        <select name="activities[<?php echo $idx; ?>][zone_name]" class="table-input zone-select-db" data-selected="<?php echo $act['zone_name']; ?>" onchange="loadFloorsForRow(this)" <?php echo $can_edit_contractor?'':'disabled'; ?>>
                                            <option value="">...</option>
                                            <?php if(!$can_edit_contractor && $act['zone_name']) echo "<option selected>{$act['zone_name']}</option>"; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="activities[<?php echo $idx; ?>][floor]" class="table-input floor-select-db" data-selected="<?php echo $act['floor']; ?>" <?php echo $can_edit_contractor?'':'disabled'; ?>>
                                            <option value="">...</option>
                                            <?php if(!$can_edit_contractor && $act['floor']) echo "<option selected>{$act['floor']}</option>"; ?>
                                        </select>
                                    </td>
                                    <td><select name="activities[<?php echo $idx; ?>][unit]" <?php echo $can_edit_contractor?'':'disabled'; ?>><?php foreach($unit_list as $u) echo "<option value='$u' ".($u==$act['unit']?'selected':'').">$u</option>"; ?></select></td>

                                    <!-- CONTRACTOR INPUTS -->
                                    <td style="position:relative">
                                        <input type="number" step="0.01" name="activities[<?php echo $idx; ?>][contractor_quantity]" 
                                               class="table-input target-qty <?php echo !empty($act['consultant_quantity']) ? 'crossed-out' : ''; ?>" 
                                               value="<?php echo $act['contractor_quantity']; ?>" 
                                               <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                        <?php if($can_edit_contractor): ?>
                                        <button type="button" class="btn btn-sm btn-link position-absolute top-0 end-0 p-0" onclick="checkApiStats(this)"><i class="fas fa-search"></i></button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="activities[<?php echo $idx; ?>][contractor_meterage]" 
                                               class="table-input target-met <?php echo !empty($act['consultant_meterage']) ? 'crossed-out' : ''; ?>" 
                                               value="<?php echo $act['contractor_meterage']; ?>" 
                                               <?php echo $can_edit_contractor?'':'readonly'; ?>>
                                    </td>
                                    <td><input type="number" name="activities[<?php echo $idx; ?>][personnel_count]" value="<?php echo $act['personnel_count']; ?>" class="table-input" <?php echo $can_edit_contractor?'':'readonly'; ?>></td>
                                    
                                    <!-- CONSULTANT INPUTS -->
                                    <td class="consultant-col bg-warning bg-opacity-10">
                                        <input type="number" step="0.01" name="activities[<?php echo $idx; ?>][consultant_quantity]" 
                                            value="<?php echo $act['consultant_quantity']; ?>" 
                                            class="table-input text-center fw-bold text-danger consultant-input"
                                            placeholder="<?= $act['contractor_quantity'] ?>" 
                                            oninput="toggleCrossout(this, '.target-qty')"
                                            <?php echo $can_edit_consultant?'':'readonly'; ?>>
                                    </td>
                                    <td class="consultant-col bg-warning bg-opacity-10">
                                        <input type="number" step="0.01" name="activities[<?php echo $idx; ?>][consultant_meterage]" 
                                            value="<?php echo $act['consultant_meterage']; ?>" 
                                            class="table-input text-center fw-bold text-danger consultant-input"
                                            placeholder="<?= $act['contractor_meterage'] ?>"
                                            oninput="toggleCrossout(this, '.target-met')"
                                            <?php echo $can_edit_consultant?'':'readonly'; ?>>
                                    </td>
                                    <td class="consultant-col bg-warning bg-opacity-10">
                                        <?php if ($can_edit_consultant): ?>
                                            <textarea name="activities[<?php echo $idx; ?>][consultant_comment]" class="form-control form-control-sm consultant-input" rows="1"><?php echo $act['consultant_comment'] ?? ''; ?></textarea>
                                        <?php else: ?>
                                            <small class="text-danger"><?php echo $act['consultant_comment']; ?></small>
                                        <?php endif; ?>
                                    </td>

                                    <!-- CUMULATIVE -->
                                    <td class="bg-cumulative"><input type="number" name="activities[<?php echo $idx; ?>][cum_open_count]" value="<?php echo $act['cum_open_count']; ?>" class="table-input" readonly></td>
                                    <td class="bg-cumulative"><input type="number" name="activities[<?php echo $idx; ?>][cum_open_meterage]" value="<?php echo $act['cum_open_meterage']; ?>" class="table-input" readonly></td>
                                    <td class="bg-cumulative"><input type="number" name="activities[<?php echo $idx; ?>][cum_rejected_count]" value="<?php echo $act['cum_rejected_count']; ?>" class="table-input" readonly></td>
                                    <td class="bg-cumulative"><input type="number" name="activities[<?php echo $idx; ?>][cum_installed_count]" value="<?php echo $act['cum_installed_count']; ?>" class="table-input" readonly></td>
                                    
                                    <td><?php if($can_edit_contractor): ?><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-top">
                    <label class="form-label fw-bold bg-light w-100 p-2 border-bottom">شرح توضیحات، موانع و مشکلات</label>
                    <textarea name="problems_and_obstacles" class="form-control mb-3" rows="5" placeholder="توضیحات پیمانکار..." <?php echo $can_edit_contractor?'':'readonly'; ?>><?php echo $report['problems_and_obstacles']; ?></textarea>

                    <?php if ($can_edit_consultant): ?>
                        <div class="consultant-col p-2 border rounded bg-warning bg-opacity-10">
                            <label class="form-label small fw-bold text-danger">✍️ شرح توضیحات نظارت (یادداشت مشاور):</label>
                            <textarea name="consultant_notes" class="form-control consultant-input" rows="5" placeholder="نظرات نظارت..."><?php echo $report['consultant_notes']; ?></textarea>
                        </div>
                    <?php elseif (!empty($report['consultant_notes'])): ?>
                        <div class="alert alert-warning mt-2">
                            <strong class="d-block border-bottom pb-1 mb-1 text-danger">💬 شرح توضیحات نظارت:</strong>
                            <div class="text-dark"><?php echo nl2br(htmlspecialchars($report['consultant_notes'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
                 <?php if ($can_edit_consultant): ?>
                <div class="p-3 bg-light border-top">
                    <label class="form-label fw-bold small">💬 یادداشت کلی فعالیت‌ها:</label>
                    <textarea name="consultant_note_activities" class="form-control form-control-sm" rows="2"><?php echo $report['consultant_note_activities']; ?></textarea>
                </div>
                <?php elseif (!empty($report['consultant_note_activities'])): ?>
                <div class="p-3 bg-warning bg-opacity-10 border-top">
                    <strong>💬 یادداشت مشاور:</strong> <?php echo nl2br(htmlspecialchars($report['consultant_note_activities'])); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- SECTION 6: MISC -->
            <div class="row">
                 <div class="col-md-6">
                    <div class="section-card">
                        <div class="section-header d-flex justify-content-between">
                            <span>گزارش مجوزات (Permits & HSE)</span>
                            <?php if ($can_edit_contractor): ?>
                                <div>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="checkAutoPermits()"><i class="fas fa-sync"></i> بررسی خودکار</button>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="addMiscRow('permitBody', 'PERMIT')">+ دستی</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addMiscRow('permitBody', 'HSE')">+ HSE</button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-2">
                            <div id="noPermitAlert" class="alert alert-warning small mb-2 d-none">
                                <i class="fas fa-exclamation-triangle text-danger"></i> مجوزی برای این تاریخ یافت نشد.
                                <br>
                                <a href="/ghom/contractor_batch_update.php" target="_blank" class="btn btn-sm btn-outline-danger mt-2 w-100">
                                    <i class="fas fa-external-link-alt"></i> ثبت درخواست در مراحل پیش بازرسی
                                </a>
                            </div>
                            <table class="table table-bordered mb-0 table-sm">
                                <tbody id="permitBody">
                                    <?php foreach ($misc_permits as $m): ?>
                                        <tr>
                                            <td><span class="badge bg-light text-dark border">مجوز</span> <input type="text" name="misc_permit[]" class="table-input d-inline-block w-75" value="<?php echo $m['description']; ?>" <?php echo $can_edit_contractor?'':'readonly'; ?>></td>
                                            <td><?php if ($can_edit_contractor): ?><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button><?php endif; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php foreach ($misc_hse as $m): ?>
                                        <tr>
                                            <td><span class="badge bg-warning text-dark">HSE</span> <input type="text" name="misc_hse[]" class="table-input d-inline-block w-75" value="<?php echo $m['description']; ?>" <?php echo $can_edit_contractor?'':'readonly'; ?>></td>
                                            <td><?php if ($can_edit_contractor): ?><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button><?php endif; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="section-card">
                        <div class="section-header">
                            <span>آزمایشات انجام شده</span>
                            <?php if ($can_edit_contractor): ?><button type="button" class="btn btn-sm btn-secondary" onclick="addMiscRow('testBody', 'TEST')">+ افزودن تست</button><?php endif; ?>
                        </div>
                        <table class="table table-bordered mb-0 table-sm">
                            <tbody id="testBody">
                                <?php foreach ($misc_tests as $m): ?>
                                    <tr>
                                        <td><input type="text" name="misc_test[]" class="table-input" value="<?php echo $m['description']; ?>" <?php echo $can_edit_contractor?'':'readonly'; ?>></td>
                                        <td><?php if ($can_edit_contractor): ?><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SECTION 7: APPROVAL -->
            <div class="section-card border-primary">
                <div class="section-header bg-primary text-white">تایید و امضا</div>
                <div class="p-4">
                    <?php if ($is_consultant && (!$is_approved || $is_superuser)): ?>
                        <div class="mb-4 p-3 border rounded bg-light">
                            <label class="fw-bold w-100 border-bottom pb-2 mb-2">تعیین وضعیت نهایی گزارش</label>
                            <div class="btn-group w-100">
                                <input type="radio" class="btn-check" name="status_action" id="act_approve" value="Approved">
                                <label class="btn btn-outline-success" for="act_approve">✅ تایید نهایی</label>
                                <input type="radio" class="btn-check" name="status_action" id="act_reject" value="Rejected" checked>
                                <label class="btn btn-outline-danger" for="act_reject">❌ رد / اصلاح</label>
                            </div>
                            <div class="form-text text-muted mt-2">توضیحات نظارت را در بخش بالا (زیر بخش مشکلات) وارد کنید.</div>
                        </div>
                    <?php elseif ($is_consultant && $is_approved): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> این گزارش تایید نهایی شده است.
                        </div>
                    <?php endif; ?>

                    <?php if ($report_id): ?>
                        <div class="alert alert-info mt-3"><i class="fas fa-info-circle"></i> برای امضای دیجیتال یا آپلود فایل امضا شده، به <a href="daily_report_print.php?id=<?php echo $report_id; ?>" target="_blank" class="alert-link">صفحه چاپ و امضا</a> بروید.</div>
                        <div class="d-flex gap-4 mt-3 justify-content-center">
                            <?php if (!empty($report['digital_signature_path'])): ?>
                                <div class="text-center border p-2 rounded bg-white"><div class="small text-muted mb-1">امضای دیجیتال</div><img src="<?php echo $report['digital_signature_path']; ?>" style="max-height:80px"></div>
                            <?php endif; ?>
                            <?php if (!empty($report['signed_scan_path'])): ?>
                                <div class="text-center border p-2 rounded bg-white d-flex flex-column justify-content-center"><div class="small text-muted mb-1">فایل اسکن شده</div><a href="<?php echo $report['signed_scan_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i> دانلود</a></div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary"><i class="fas fa-exclamation-triangle"></i> ابتدا گزارش را ذخیره کنید تا امکان امضا فعال شود.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ACTIONS -->
            <div class="form-actions d-flex justify-content-end gap-2 mb-5">
                <a href="daily_reports_dashboard.php" class="btn btn-secondary">بازگشت</a>
                <?php if ($can_edit_contractor): ?>
                    <button type="button" class="btn btn-outline-primary px-4" onclick="saveDraft()">ذخیره پیش‌نویس</button>
                    <button type="button" class="btn btn-success px-5" onclick="submitReport()">ارسال نهایی</button>
                <?php elseif ($can_edit_consultant): ?>
                    <button type="button" id="saveBtn" class="btn btn-success px-5">ثبت نظر مشاور</button>
                <?php endif; ?>
            </div>
        </form> <!-- Closed form correctly -->
    </div>

    <script>
        jalaliDatepicker.startWatch({ minDate: "attr", maxDate: "attr", showTodayBtn: true, showEmptyBtn: true, placement: "bottom-start", zIndex: 9999 });
        const actOptions = <?php echo json_encode($act_list); ?>;
        const groupedActOptions = <?php echo json_encode($grouped_activities, JSON_UNESCAPED_UNICODE); ?>;
        const unitOptions = <?php echo json_encode($unit_list); ?>;
        const materialCatOptions = <?php echo json_encode($material_cats); ?>;
        const isConsultant = <?php echo $is_consultant ? 'true' : 'false'; ?>;

        // Signature Pad Init (Kept original logic)
        let signaturePad;
        const canvas = document.getElementById('signature-pad');
        if (canvas) {
            signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)' });
            window.addEventListener("resize", () => {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
            });
        }

        function toggleReviewMode() {
            const isReviewMode = document.getElementById('reviewModeToggle')?.checked || false;
            document.querySelectorAll('.consultant-col').forEach(el => el.style.display = isReviewMode ? '' : 'none');
        }
        
        // === NEW: CROSSOUT LOGIC ===
        function toggleCrossout(consultantInput, targetSelector) {
            const row = consultantInput.closest('tr');
            const target = row.querySelector(targetSelector);
            if (target) {
                if (consultantInput.value.trim() !== '') target.classList.add('crossed-out');
                else target.classList.remove('crossed-out');
            }
        }

        let cachedZones = [];
        document.addEventListener('DOMContentLoaded', async () => {
            // FIXED: REMOVED LOGIC THAT HID COLUMNS FOR CONTRACTORS
            if (isConsultant) toggleReviewMode();

            try {
                const res = await fetch('?api_action=get_db_zones');
                const json = await res.json();
                if(json.success) {
                    cachedZones = json.data;
                    document.querySelectorAll('.zone-select-db').forEach(sel => {
                        populateSelect(sel, cachedZones, sel.getAttribute('data-selected'));
                        if(sel.value) loadFloorsForRow(sel);
                    });
                }
            } catch(e) { console.error(e); }
        });

        function populateSelect(selectEl, items, selectedVal = null) {
            selectEl.innerHTML = '<option value="">انتخاب...</option>';
            items.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item; opt.textContent = item;
                if(selectedVal && String(selectedVal) === String(item)) opt.selected = true;
                selectEl.appendChild(opt);
            });
        }

        async function loadFloorsForRow(zoneSelect) {
            const row = zoneSelect.closest('tr');
            const floorSelect = row.querySelector('.floor-select-db');
            const zoneName = zoneSelect.value;
            if(!zoneName) { floorSelect.innerHTML = '<option value="">...</option>'; return; }
            floorSelect.innerHTML = '<option>...</option>';
            const fd = new FormData(); fd.append('zone', zoneName);
            try {
                const res = await fetch('?api_action=get_db_floors', { method:'POST', body:fd });
                const json = await res.json();
                if(json.success) populateSelect(floorSelect, json.data, floorSelect.getAttribute('data-selected'));
            } catch(e) { console.error(e); }
        }

        // ADD ROW FUNCTIONS (UPDATED WITH STRIKETHROUGH CLASSES)
function addActivity() {
    const idx = Date.now();
    let zoneOpts = '<option value="">انتخاب...</option>';
    cachedZones.forEach(z => zoneOpts += `<option value="${z}">${z}</option>`);
    
    // BUILD SELECT WITH OPTGROUPS
    let activitySelectHtml = '<select name="activities[' + idx + '][activity_id]" class="form-select form-select-sm" style="min-width: 200px;"><option value="">انتخاب...</option>';
    
    for (const [category, items] of Object.entries(groupedActOptions)) {
        activitySelectHtml += `<optgroup label="${category}">`;
        items.forEach(item => {
            activitySelectHtml += `<option value="${item.id}">${item.name}</option>`;
        });
        activitySelectHtml += `</optgroup>`;
    }
    activitySelectHtml += '</select>';

    const consultantCols = isConsultant ? 
        `<td class="consultant-col bg-warning bg-opacity-10"><input type="number" step="0.01" name="activities[${idx}][consultant_quantity]" class="table-input text-center fw-bold text-danger consultant-input" oninput="toggleCrossout(this, '.target-qty')"></td>
         <td class="consultant-col bg-warning bg-opacity-10"><input type="number" step="0.01" name="activities[${idx}][consultant_meterage]" class="table-input text-center fw-bold text-danger consultant-input" oninput="toggleCrossout(this, '.target-met')"></td>
         <td class="consultant-col bg-warning bg-opacity-10"><textarea name="activities[${idx}][consultant_comment]" class="form-control form-control-sm consultant-input" rows="1"></textarea></td>` : '';
    
    // Note: Replaced the old <select> string with ${activitySelectHtml}
    const row = `<tr>
        <td class="text-center">#</td>
        <td>${activitySelectHtml}</td>
        <td><input type="text" name="activities[${idx}][location_facade]" class="table-input" placeholder="محور"></td>
        <td><select name="activities[${idx}][zone_name]" class="table-input zone-select-db" onchange="loadFloorsForRow(this)">${zoneOpts}</select></td>
        <td><select name="activities[${idx}][floor]" class="table-input floor-select-db"><option value="">...</option></select></td>
        <td><select name="activities[${idx}][unit]"><?php foreach($unit_list as $u) echo "<option value='$u'>$u</option>"; ?></select></td>
        <td style="position:relative"><input type="number" name="activities[${idx}][contractor_quantity]" class="table-input target-qty"><button type="button" class="btn btn-sm btn-link position-absolute top-0 end-0 p-0" onclick="checkApiStats(this)"><i class="fas fa-search"></i></button></td>
        <td><input type="number" name="activities[${idx}][contractor_meterage]" class="table-input target-met"></td>
        <td><input type="number" name="activities[${idx}][personnel_count]" class="table-input"></td>
        ${consultantCols}
        <td class="bg-cumulative"><input type="number" name="activities[${idx}][cum_open_count]" class="table-input" readonly></td>
        <td class="bg-cumulative"><input type="number" name="activities[${idx}][cum_open_meterage]" class="table-input" readonly></td>
        <td class="bg-cumulative"><input type="number" name="activities[${idx}][cum_rejected_count]" class="table-input" readonly></td>
        <td class="bg-cumulative"><input type="number" name="activities[${idx}][cum_installed_count]" class="table-input" readonly></td>
        <td><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button></td>
    </tr>`;
    document.getElementById('activityBody').insertAdjacentHTML('beforeend', row);
}
        function addPersonnelRow() {
            const tbody = document.getElementById('personnelBody');
            const rowCount = tbody.rows.length;
            const consultantCol = isConsultant ? `<td class="consultant-col bg-warning bg-opacity-10"><input type="number" name="personnel[${rowCount}][consultant_count]" class="form-control form-control-sm text-center fw-bold text-danger consultant-input" oninput="toggleCrossout(this, '.target-count')"></td><td class="consultant-col bg-warning bg-opacity-10"><textarea name="personnel[${rowCount}][consultant_comment]" class="form-control form-control-sm consultant-input" rows="1"></textarea></td>` : '';
            const row = `<tr class="contractor-row"><td>${rowCount + 1}</td><td><input type="text" name="personnel[${rowCount}][role_name]" class="form-control form-control-sm border-0"></td><td><input type="number" name="personnel[${rowCount}][count]" class="form-control form-control-sm border-0 text-center target-count"></td>${consultantCol}<td><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button></td></tr>`;
            tbody.insertAdjacentHTML('beforeend', row);
        }

        function addMachineryRow() {
            const tbody = document.getElementById('machineryBody');
            const rowCount = tbody.rows.length;
            const consultantCol = isConsultant ? `<td class="consultant-col bg-warning bg-opacity-10"><input type="number" name="machinery[${rowCount}][consultant_active_count]" class="form-control form-control-sm text-center fw-bold text-danger consultant-input" oninput="toggleCrossout(this, '.target-active')"></td><td class="consultant-col bg-warning bg-opacity-10"><textarea name="machinery[${rowCount}][consultant_comment]" class="form-control form-control-sm consultant-input" rows="1"></textarea></td>` : '';
            const row = `<tr><td>${rowCount + 1}</td><td><input type="text" name="machinery[${rowCount}][machine_name]" list="toolsList" class="form-control form-control-sm border-0"></td><td><input type="number" name="machinery[${rowCount}][total_count]" class="form-control form-control-sm border-0 text-center"></td><td><input type="number" name="machinery[${rowCount}][active_count]" class="form-control form-control-sm border-0 text-center target-active"></td>${consultantCol}<td><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button></td></tr>`;
            tbody.insertAdjacentHTML('beforeend', row);
        }

        function addMaterial(type) {
            const idx = Date.now();
            const prefix = type === 'in' ? 'mat_in' : 'mat_out';
            const tbody = type === 'in' ? 'materialInBody' : 'materialOutBody';
            // FIXED: Using materialCatOptions variable
            const catOpts = materialCatOptions.map(c => `<option value="${c}">${c}</option>`).join('');
            const consultantCols = isConsultant ? `<td class="consultant-col bg-warning bg-opacity-10"><input type="text" name="${prefix}[${idx}][consultant_quantity]" class="form-control form-control-sm text-center fw-bold text-danger consultant-input" oninput="toggleCrossout(this, '.target-qty')"></td><td class="consultant-col bg-warning bg-opacity-10"><input type="text" name="${prefix}[${idx}][consultant_comment]" class="form-control form-control-sm consultant-input"></td>` : '';
            const row = `<tr><td><select name="${prefix}[${idx}][category]" class="table-input" style="font-size:0.8em"><option value="">...</option>${catOpts}</select></td><td><input type="text" name="${prefix}[${idx}][name]" class="table-input" placeholder="شرح"><input type="text" name="${prefix}[${idx}][date]" class="table-input text-muted" style="font-size:0.8em" placeholder="تاریخ" data-jdp></td><td><div class="d-flex"><input type="text" name="${prefix}[${idx}][quantity]" class="table-input target-qty" placeholder="#"><select name="${prefix}[${idx}][unit]" class="table-input"><?php foreach($unit_list as $u) echo "<option value='$u'>$u</option>"; ?></select></div><input type="file" name="${prefix}_file_${idx}" class="form-control form-control-sm mt-1" style="font-size:0.7em"></td>${consultantCols}<td><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button></td></tr>`;
            document.getElementById(tbody).insertAdjacentHTML('beforeend', row);
            jalaliDatepicker.startWatch();
        }

        function addMiscRow(id, type) {
            let inputName = type === 'PERMIT' ? 'misc_permit[]' : (type === 'HSE' ? 'misc_hse[]' : 'misc_test[]');
            let badge = type === 'PERMIT' ? '<span class="badge bg-light text-dark border">مجوز</span> ' : (type === 'HSE' ? '<span class="badge bg-warning text-dark">HSE</span> ' : '');
            let widthClass = badge ? 'd-inline-block w-75' : 'table-input';
            document.getElementById(id).insertAdjacentHTML('beforeend', `<tr><td>${badge}<input type="text" name="${inputName}" class="${widthClass} table-input" placeholder="شرح..."></td><td><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button></td></tr>`);
        }

        async function checkAutoPermits() {
            const dateVal = document.querySelector('[name="report_date"]').value;
            const contractorVal = document.querySelector('[name="contractor_fa_name"]').value;
            const alertBox = document.getElementById('noPermitAlert');
            const tbody = document.getElementById('permitBody');

            if (!dateVal || !contractorVal) { alert('لطفا ابتدا تاریخ و نام پیمانکار را انتخاب کنید.'); return; }
            alertBox.className = 'alert alert-info small mb-2';
            alertBox.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال جستجو...';
            alertBox.classList.remove('d-none');

            const fd = new FormData(); fd.append('date', dateVal); fd.append('contractor', contractorVal);
            try {
                const res = await fetch('?api_action=check_permits', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success && json.data.length > 0) {
                    alertBox.classList.add('d-none');
                    json.data.forEach(p => {
                        const desc = `درخواست بازگشایی پنل (${p.zone_label}): ${p.panel_count} پنل - ${p.note || ''}`;
                        let exists = false; tbody.querySelectorAll('input[type="text"]').forEach(inp => { if(inp.value === desc) exists = true; });
                        if (!exists) {
                            const row = `<tr><td><span class="badge bg-success">خودکار</span><input type="text" name="misc_permit[]" class="table-input d-inline-block w-75" value="${desc}"></td><td><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()">×</button></td></tr>`;
                            tbody.insertAdjacentHTML('beforeend', row);
                        }
                    });
                    alert(`✅ ${json.data.length} مجوز یافت و اضافه شد.`);
                } else {
                    alertBox.className = 'alert alert-warning small mb-2';
                    alertBox.innerHTML = `<i class="fas fa-exclamation-triangle text-danger"></i> مجوزی برای این تاریخ یافت نشد.<br><a href="/ghom/contractor_batch_update.php" target="_blank" class="btn btn-sm btn-outline-danger mt-2 w-100"><i class="fas fa-external-link-alt"></i> ثبت درخواست در مراحل پیش بازرسی</a>`;
                }
            } catch (e) { console.error(e); alert('خطا در ارتباط با سرور'); alertBox.classList.add('d-none'); }
        }

        async function checkApiStats(btn) {
            const row = btn.closest('tr');
            const zoneInput = row.querySelector('.zone-select-db').value;
            if(!zoneInput) return alert('ابتدا نام زون را وارد کنید');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            const fd = new FormData(); fd.append('zone', zoneInput);
            try {
                const res = await fetch('?api_action=get_element_stats', { method:'POST', body:fd });
                const json = await res.json();
                if(json.success && json.data && json.data.count > 0) {
                    if(confirm(`اطلاعات یافت شد:\n\nتعداد المان: ${json.data.count}\nمساحت کل: ${json.data.total_area} مترمربع\n\nآیا جایگزین شود؟`)) {
                        row.querySelector('[name*="[contractor_meterage]"]').value = json.data.total_area;
                        row.querySelector('[name*="[contractor_quantity]"]').value = json.data.count;
                    }
                } else alert('اطلاعاتی برای این زون یافت نشد.');
            } catch(e) { alert('خطا در ارتباط با سرور'); } finally { btn.innerHTML = '<i class="fas fa-search"></i>'; }
        }

        async function saveReport(action) {
            if (signaturePad && !signaturePad.isEmpty()) document.getElementById('digital_signature_data').value = signaturePad.toDataURL();
            const formData = new FormData(document.getElementById('mainForm'));
            formData.append('save_action', action);
            try {
                const res = await fetch('/ghom/api/save_daily_report.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    alert(action === 'draft' ? '✅ پیش‌نویس ذخیره شد' : '✅ گزارش با موفقیت ارسال شد');
                    window.location.href = 'daily_reports_dashboard.php';
                } else alert(data.message);
            } catch (e) { alert('خطا در ارتباط با سرور'); }
        }
        
        function saveDraft() { saveReport('draft'); }
        function submitReport() { saveReport('submit'); }
        document.getElementById('saveBtn')?.addEventListener('click', async () => {
            const btn = document.getElementById('saveBtn'); btn.disabled = true; btn.textContent = 'در حال پردازش...';
            await saveReport('consultant_review');
        });

        const projectData = <?php echo json_encode($project_json_data); ?>;
        if(!document.getElementById('dynamicZoneList')) {
            const dl = document.createElement('datalist'); dl.id = 'dynamicZoneList'; document.body.appendChild(dl);
        }
        function updateZoneList() {
            const contractor = document.querySelector('[name="contractor_fa_name"]').value;
            const block = document.querySelector('[name="block_name"]').value;
            const datalist = document.getElementById('dynamicZoneList'); datalist.innerHTML = '';
            Object.values(projectData.regions || {}).forEach(region => {
                if(region.contractor.includes(contractor) && region.block === block && region.zones) {
                    region.zones.forEach(z => { let opt = document.createElement('option'); opt.value = z.label; datalist.appendChild(opt); });
                }
            });
        }
        document.querySelector('[name="contractor_fa_name"]').addEventListener('change', updateZoneList);
        document.querySelector('[name="block_name"]').addEventListener('change', updateZoneList);
    </script>
</body>
</html>