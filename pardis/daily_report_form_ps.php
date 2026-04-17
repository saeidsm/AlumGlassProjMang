<?php
// daily_report_form_ps.php - PARDIS PROJECT (Fixed All Issues)
ob_start();

function isMobileDevice() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

if (isMobileDevice() && !isset($_GET['view'])) {
    header('Location: daily_report_mobile_ps.php');
    exit();
}

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();

if (!isLoggedIn()) { header('Location: /login.php'); exit(); }

// --- 1. DB CONNECTION (PARDIS) ---
$pdo = getProjectDBConnection('pardis');

$report_id = $_GET['id'] ?? null;
$user_role = $_SESSION['role'];
$unit_list = ['عدد', 'متر طول', 'متر مربع', 'کیلوگرم', 'تن', 'دستگاه', 'سرویس', 'نفر','میلیمتر','سانتیمتر', 'متر مکعب','لیتر','گالن'];

// Roles
$contractor_roles = ["car" => 'شرکت آران سیج', "cod" => 'شرکت طرح و نقش آدرم'];
$current_contractor_name = $contractor_roles[$user_role] ?? ''; 
$contractors_db = $pdo->query("SELECT name, contract_number FROM ps_contractors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$contractor_map = []; // Map for JS and lookup
foreach($contractors_db as $c) $contractor_map[$c['name']] = $c['contract_number'];
// --- پایان بلوک جدید ---
// 2. Determine Contract Number and Block Name based on user instruction
$default_block_name = '';
$contract_number = '';

if (!empty($current_contractor_name)) {
    // Determine Block based on user rules
    if ($user_role === 'cod') {
        $default_block_name = 'ساختمان کشاورزی';
    } elseif ($user_role === 'car') {
        $default_block_name = 'ساختمان کتابخانه';
    }
    
    // Fetch contract number from the ps_contractors table
    $stmt = $pdo->prepare("SELECT contract_number FROM ps_contractors WHERE name = ?");
    $stmt->execute([$current_contractor_name]);
    $contract_number = $stmt->fetchColumn() ?: '';
}

// 3. Update the default $report initialization (حدود خط ۶۰)

$is_contractor = array_key_exists($user_role, $contractor_roles);
$is_consultant = in_array($user_role, ['admin', 'superuser', 'supervisor']);

// Lists from DB
$personnel_roles_db = $pdo->query("SELECT role_name FROM ps_personnel_roles ORDER BY sort_order")->fetchAll(PDO::FETCH_COLUMN);
$personnel_roles = !empty($personnel_roles_db) ? $personnel_roles_db : ['مدیر پروژه','رییس کارگاه','دفتر فنی','کنترل پروژه','نقشه برداری','ایمنی','اجرا','ماشین آلات','استاد کار','کارگر','حراست','خدمات'];

$tools_list_db = $pdo->query("SELECT tool_name FROM ps_tools_list ORDER BY tool_name")->fetchAll(PDO::FETCH_COLUMN);
$tools_list = !empty($tools_list_db) ? $tools_list_db : ['جرثقیل','بالابر','داربست','اره برقی','دریل'];

$materials_list_db = $pdo->query("SELECT material_name FROM ps_materials_list ORDER BY material_name")->fetchAll(PDO::FETCH_COLUMN);
$materials_list = !empty($materials_list_db) ? $materials_list_db : ['پنل GFRC', 'شیشه', 'پروفیل آلومینیوم', 'براکت', 'مولیون', 'ترنزوم', 'چسب', 'پشم سنگ', 'پیچ و مهره'];

$act_list = $pdo->query("SELECT * FROM ps_project_activities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Init Data
$report = [
    'id' => '', 'report_date' => jdate('Y/m/d'), 
    'contractor_fa_name' => $current_contractor_name, // <--- از نقش خوانده می‌شود
    'block_name' => $default_block_name,             // <--- از نقش خوانده می‌شود
    'work_hours_day' => '8', 
    'work_hours_night' => '0', 
    'contract_number' => $contract_number,           // <--- از دیتابیس خوانده می‌شود
    'temp_max' => '', 
    'temp_min' => '', 
    'weather_list' => '[]', 
    'status' => 'Draft',
    'problems_and_obstacles' => '', 
    'consultant_notes' => '',
    'consultant_note_personnel' => '',
    'consultant_note_machinery' => '',
    'consultant_note_materials' => '',
    'consultant_note_activities' => '',
    'digital_signature_path' => '',
    'signed_scan_path' => ''
];

/**
 * Calculate cumulative quantity for an activity based on:
 * - Same activity_id
 * - Same work_front
 * - Same location_facade
 * - All reports up to current date
 */
function getCumulativeQty($pdo, $activity_id, $work_front, $location_facade, $current_date, $exclude_report_id = null) {
    $sql = "
        SELECT 
            SUM(COALESCE(NULLIF(dra.consultant_qty_day, 0), dra.qty_day)) +
            SUM(COALESCE(NULLIF(dra.consultant_qty_night, 0), dra.qty_night)) as total
        FROM ps_daily_report_activities dra
        JOIN ps_daily_reports dr ON dra.report_id = dr.id
        WHERE dra.activity_id = ?
        AND dra.work_front = ?
        AND dra.location_facade = ?
        AND dr.report_date <= ?
        AND dr.status IN ('Submitted', 'Approved')
    ";
    
    $params = [$activity_id, $work_front, $location_facade, $current_date];
    
    if ($exclude_report_id) {
        $sql .= " AND dr.id != ?";
        $params[] = $exclude_report_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float)($stmt->fetchColumn() ?: 0);
}


/**
 * Convert Jalali date string (Y/m/d) to Gregorian date (Y-m-d)
 */
function convert_date($jalali_date) {
    if (empty($jalali_date)) {
        return null;
    }
    
    // Parse Jalali date (format: Y/m/d or Y-m-d)
    $parts = preg_split('/[\/\-]/', $jalali_date);
    
    if (count($parts) !== 3) {
        return null;
    }
    
    list($jy, $jm, $jd) = $parts;
    
    // Convert to Gregorian using jdate functions
    // jmktime returns Unix timestamp for Jalali date
    $timestamp = jmktime(0, 0, 0, (int)$jm, (int)$jd, (int)$jy);
    
    if ($timestamp === false) {
        return null;
    }
    
    // Return Gregorian date in Y-m-d format
    return date('Y-m-d', $timestamp);
}


$personnel_data = [];
foreach($personnel_roles as $r) $personnel_data[] = ['role_name'=>$r, 'count'=>0, 'count_night'=>0, 'consultant_count'=>'', 'consultant_comment'=>''];
$machinery = []; 
$mat_in = []; 
$mat_out = []; 
$activities = []; 
$misc_p = []; $misc_t = []; $misc_h = [];

// Load Data
if ($report_id) {
    $stmt = $pdo->prepare("SELECT * FROM ps_daily_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($fetched) {
        $report = $fetched;
        if (!empty($report['report_date'])) {
            $p = explode('-', $report['report_date']);
            $report['report_date'] = jdate('Y/m/d', mktime(0,0,0,$p[1],$p[2],$p[0]));
        }
        if (empty($report['contract_number']) && !empty($report['contractor_fa_name'])) {
            $stmt = $pdo->prepare("SELECT contract_number FROM ps_contractors WHERE name = ?");
            $stmt->execute([$report['contractor_fa_name']]);
            $report['contract_number'] = $stmt->fetchColumn() ?: '';
        }
        $stmt = $pdo->prepare("SELECT * FROM ps_daily_report_personnel WHERE report_id = ?");
        $stmt->execute([$report_id]);
        $p_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($p_db) {
            $p_map = []; foreach($p_db as $p) $p_map[$p['role_name']] = $p;
            foreach($personnel_data as &$pd) {
                if(isset($p_map[$pd['role_name']])) {
                    $pd['count'] = $p_map[$pd['role_name']]['count'];
                    $pd['count_night'] = $p_map[$pd['role_name']]['count_night'];
                    $pd['consultant_count'] = $p_map[$pd['role_name']]['consultant_count'] ?? '';
                    $pd['consultant_comment'] = $p_map[$pd['role_name']]['consultant_comment'] ?? '';
                    $pd['id'] = $p_map[$pd['role_name']]['id'];
                }
            }
        }
        
        $stmt = $pdo->prepare("SELECT * FROM ps_daily_report_machinery WHERE report_id = ?");
        $stmt->execute([$report_id]);
        $machinery = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT * FROM ps_daily_report_materials WHERE report_id = ?");
        $stmt->execute([$report_id]);
        $mats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $materials_in = []; 
        $materials_out = [];
        
        foreach($mats as $m) {
            if($m['type']=='IN') $materials_in[] = $m; 
            else $materials_out[] = $m;
        }
        
        $mat_in = $materials_in;
        $mat_out = $materials_out;
        
        $stmt = $pdo->prepare("SELECT * FROM ps_daily_report_activities WHERE report_id = ?");
        $stmt->execute([$report_id]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $actNameStmt = $pdo->prepare("SELECT name FROM ps_project_activities WHERE id = ?");
    foreach($activities as &$act) {
        $actNameStmt->execute([$act['activity_id']]);
        $n = $actNameStmt->fetchColumn();
        $act['act_name'] = $n;
        
        // Calculate cumulative from DB for this activity
        $currentDate = convert_date($report['report_date']);
        if ($currentDate) {
            $dbCumulative = getCumulativeQty(
                $pdo, 
                $act['activity_id'], 
                $act['work_front'] ?? '', 
                $act['location_facade'] ?? '', 
                $currentDate,
                $report_id // Exclude current report from calculation
            );
            
            // Add current day + night to get total cumulative
            $currentDay = (float)($act['consultant_qty_day'] ?? $act['qty_day'] ?? 0);
            $currentNight = (float)($act['consultant_qty_night'] ?? $act['qty_night'] ?? 0);
            $act['calculated_cumulative'] = $dbCumulative + $currentDay + $currentNight;
        }
    }
        
        $stmt = $pdo->prepare("SELECT * FROM ps_daily_report_misc WHERE report_id = ?");
        $stmt->execute([$report_id]);
        $miscs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($miscs as $m) {
            if($m['type']=='PERMIT') $misc_p[]=$m; 
            elseif($m['type']=='TEST') $misc_t[]=$m; 
            elseif($m['type']=='HSE') $misc_h[]=$m;
        }
    }
}

$is_approved = ($report['status'] === 'Approved');
$is_superuser = ($user_role === 'superuser');
if($report_id) {
    // Get audit logs from Pardis DB
    $auditQuery = "SELECT * FROM ps_report_audit_log WHERE report_id = ? ORDER BY edit_timestamp DESC LIMIT 10";
    $auditStmt = $pdo->prepare($auditQuery);
    $auditStmt->execute([$report_id]);
    $auditLogs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user details from Common DB
    if(!empty($auditLogs)) {
        $commonPDO = getCommonDBConnection();
        $userIds = array_column($auditLogs, 'edited_by_user_id');
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        $userQuery = "SELECT id, username, first_name, last_name FROM users WHERE id IN ($placeholders)";
        $userStmt = $commonPDO->prepare($userQuery);
        $userStmt->execute($userIds);
        $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create user lookup array
        $userMap = [];
        foreach($users as $u) {
            $userMap[$u['id']] = [
                'username' => $u['username'],
                'display_name' => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username']
            ];
        }
        
        // Attach user info to audit logs
        foreach($auditLogs as &$log) {
            if(isset($userMap[$log['edited_by_user_id']])) {
                $log['username'] = $userMap[$log['edited_by_user_id']]['username'];
                $log['display_name'] = $userMap[$log['edited_by_user_id']]['display_name'];
            } else {
                $log['username'] = 'Unknown';
                $log['display_name'] = 'کاربر نامشخص';
            }
        }
    }
}
// Lock rules:
// - If Approved: Only superuser can edit
// - If not Approved: Contractors can edit their own, Consultants can review
$can_edit_contractor = ($is_contractor && (!$is_approved || $is_superuser)); 
$can_edit_consultant = ($is_consultant && $report_id);
$form_locked = ($is_approved && !$is_superuser);

$ro = $can_edit_contractor ? '' : 'readonly';
$dis = $can_edit_contractor ? '' : 'disabled';
$cons_ro = $can_edit_consultant ? '' : 'readonly';

require_once __DIR__ . '/header.php';
?>

<style>
    .khatam-table th { background-color: #e9ecef; vertical-align: middle; text-align: center; font-size: 0.85rem; }
    .khatam-table td { padding: 2px; vertical-align: middle; position: relative; }
    
    .khatam-input { width: 100%; border: none; text-align: center; background: transparent; font-size: 0.9rem; }
    .khatam-input:focus { background: #fff; outline: 1px solid #0d6efd; }
    
    .sec-header { background: #343a40; color: #fff; padding: 5px 10px; font-weight: bold; border-radius: 5px 5px 0 0; margin-top: 20px; }
    
    .status-Submitted { background-color: #fff3cd; color: #856404; }
    .status-Approved { background-color: #d1e7dd; color: #0f5132; }
    .status-Rejected { background-color: #f8d7da; color: #842029; }
    
    .cell-wrapper { position: relative; display: flex; flex-direction: column; justify-content: center; min-height: 35px; }
    
    .val-contractor { 
        border: none; background: transparent; width: 100%; text-align: center; z-index: 2; 
        font-weight: bold; color: #333;
    }
    
    .val-consultant { 
        border: none; border-top: 1px dashed #ccc; background: #fff9e6; width: 100%; text-align: center; 
        font-size: 0.85rem; color: #dc3545; font-weight: bold; padding: 2px 0;
    }
    .val-consultant:focus { outline: none; background: #fff; }
    
    .crossed-out { text-decoration: line-through !important; opacity: 0.5; color: #999 !important; }
    
    .btn-delete-row {
        position: absolute;
        right: 2px;
        top: 50%;
        transform: translateY(-50%);
        padding: 0;
        width: 18px;
        height: 18px;
        line-height: 1;
        font-size: 12px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        display: <?= $can_edit_contractor ? 'inline-block' : 'none' ?>;
        z-index: 10;
    }
    .btn-delete-row:hover { background: #c82333; }
    
    input[data-jdp] { cursor: pointer; background-color: #fff !important; }
</style>

<link rel="stylesheet" href="/pardis/assets/css/jalalidatepicker.min.css" />
<script src="/pardis/assets/js/jalalidatepicker.min.js"></script>

<datalist id="toolsList">
    <?php foreach ($tools_list as $tool) echo "<option value='$tool'>"; ?>
</datalist>

<datalist id="materialsList">
    <?php foreach ($materials_list as $mat) echo "<option value='$mat'>"; ?>
</datalist>

<div class="container-fluid my-4">
    <form id="mainForm" enctype="multipart/form-data">
        <input type="hidden" name="report_id" value="<?=$report_id?>">
        <input type="hidden" name="save_action" value="draft">

        <!-- TOP BAR -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex gap-2 align-items-center">
                <span class="badge p-2 status-<?= $report['status'] ?>"><?= $report['status'] ?></span>
                <?php if($report_id): ?>
                    <a href="daily_report_print_ps.php?id=<?= $report_id ?>" target="_blank" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-print"></i> چاپ و امضا
                    </a>
                <?php endif; ?>
                <a href="settings_ps.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-cog"></i> تنظیمات
                </a>
                <?php if($can_edit_contractor): ?>
        <button type="button" class="btn btn-outline-info btn-sm" onclick="saveAsTemplate()">
            <i class="fas fa-save"></i> ذخیره به عنوان قالب
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadTemplate()">
            <i class="fas fa-file-import"></i> بارگذاری قالب
        </button>
    <?php endif; ?>

            </div>
            <h4 class="fw-bold text-primary m-0">📄 گزارش روزانه عملیات اجرایی نما</h4>
        </div>
<?php if($is_approved): ?>
    <div class="alert alert-warning border-warning mb-3">
        <i class="fas fa-lock"></i> 
        <strong>این گزارش تایید شده است.</strong>
        <?php if($is_superuser): ?>
            شما به عنوان سوپر ادمین می‌توانید آن را ویرایش کنید. هر تغییری ثبت خواهد شد.
        <?php else: ?>
            ویرایش فقط توسط سوپر ادمین امکان‌پذیر است.
        <?php endif; ?>
    </div>
<?php endif; ?>
        <!-- HEADER -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2"><label>تاریخ</label><input type="text" name="report_date" class="form-control form-control-sm" value="<?=$report['report_date']?>" <?=$ro?> data-jdp required></div>
            
            <div class="col-md-2"><label>پیمانکار</label>
                <select name="contractor_fa_name_disp" class="form-select form-select-sm" <?=$dis?> onchange="updateHeaderFields(this.value); document.getElementById('h_cont').value=this.value;">
                    <option value="">...</option>
                    <?php foreach($contractor_roles as $c=>$n) echo "<option value='$n' ".($report['contractor_fa_name']==$n?'selected':'').">$n</option>"; ?>
                </select>
                <input type="hidden" name="contractor_fa_name" id="h_cont" value="<?=$report['contractor_fa_name']?>">
            </div>

            <div class="col-md-2"><label>شماره قرارداد</label>
                <input type="text" name="contract_number" id="contractNumInput" class="form-control form-control-sm" value="<?=$report['contract_number']?>" readonly>
            </div>
            <div class="col-md-2"><label>بلوک</label>
                <select name="block_name_disp" id="blockSelect" class="form-select form-select-sm" <?=$dis?> onchange="document.getElementById('h_block').value=this.value">
                    <option value="">...</option>
                    <option value="ساختمان کشاورزی" <?=$report['block_name']=='ساختمان کشاورزی'?'selected':''?>>ساختمان کشاورزی</option>
                    <option value="ساختمان کتابخانه" <?=$report['block_name']=='ساختمان کتابخانه'?'selected':''?>>ساختمان کتابخانه</option>
                </select>
                <input type="hidden" name="block_name" id="h_block" value="<?=$report['block_name']?>">
            </div>
            <div class="col-md-1"><label>ساعت روز</label><input type="text" name="work_hours_day" class="form-control form-control-sm" value="<?=$report['work_hours_day']?>" <?=$ro?>></div>
            <div class="col-md-1"><label>ساعت شب</label><input type="text" name="work_hours_night" class="form-control form-control-sm" value="<?=$report['work_hours_night']?>" <?=$ro?>></div>
            <div class="col-md-2"><label>دما (Max/Min)</label><div class="d-flex gap-1"><input type="text" name="temp_max" class="form-control form-control-sm" value="<?=$report['temp_max']?>" <?=$ro?>><input type="text" name="temp_min" class="form-control form-control-sm" value="<?=$report['temp_min']?>" <?=$ro?>></div></div>
        </div>
    </div>
</div>

        <!-- SECTION 1: PERSONNEL & MACHINERY -->
        <div class="row g-0 border rounded overflow-hidden bg-white">
            
            <!-- Personnel -->
            <div class="col-md-5 border-end">
                <div class="bg-secondary text-white text-center small py-1 d-flex justify-content-between px-2">
                    <span>نیروی انسانی</span>
                    <?php if($can_edit_contractor): ?>
                        <button type="button" class="btn btn-xs btn-light py-0" onclick="addPersonnel()" style="font-size: 0.7rem; line-height: 1.5;">+</button>
                    <?php endif; ?>
                </div>
                <table class="table table-bordered mb-0 khatam-table">
                    <thead>
                        <tr>
                            <th style="width: 35%;">شغل</th>
                            <th style="width: 25%;">تعداد کل</th>
                            <th style="width: 20%;">روز</th>
                            <th style="width: 20%;">شب</th>
                        </tr>
                    </thead>
                    <tbody id="personnelBody">
                        <?php foreach($personnel_data as $i => $p): 
                             $hasReview = !empty($p['consultant_count']);
                             $crossClass = $hasReview ? 'crossed-out' : '';
                             $day = (int)$p['count'];
                             $night = (int)$p['count_night'];
                             $total = $day + $night;
                        ?>
                            <tr>
                                <td class="bg-light" style="position: relative;">
                                    <input type="hidden" name="personnel[<?=$i?>][id]" value="<?=$p['id']??''?>">
                                    <input type="text" name="personnel[<?=$i?>][role_name]" value="<?=$p['role_name']?>" class="khatam-input text-start" <?=$ro?>>
                                    <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
                                </td>
                                <td>
                                    <div class="cell-wrapper">
                                        <input type="text" readonly class="val-contractor fw-bold target-pers-<?=$i?> <?=$crossClass?>" value="<?=$total?>" id="pers_total_<?=$i?>">
                                        <?php if($is_consultant || $hasReview): ?>
                                    <input type="number" name="personnel[<?=$i?>][consultant_count]" value="<?=$p['consultant_count'] ?? ''?>" class="val-consultant" placeholder="تایید" <?=$cons_ro?> oninput="toggleCrossout(this, '.target-pers-<?=$i?>')">
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><input type="number" name="personnel[<?=$i?>][count]" value="<?=$day?>" class="khatam-input" min="0" oninput="calcPersTotal(<?=$i?>)" <?=$ro?> min="0"></td>


                                <td><input type="number" name="personnel[<?=$i?>][count_night]" value="<?=$night?>" class="khatam-input" min="0" oninput="calcPersTotal(<?=$i?>)" <?=$ro?> min="0"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if($is_consultant || !empty($report['consultant_note_personnel'])): ?>
                    <div class="p-2 bg-light border-top">
                        <small class="text-danger fw-bold">یادداشت نظارت:</small>
                        <textarea name="consultant_note_personnel" class="form-control form-control-sm" rows="2" <?=$cons_ro?>><?=$report['consultant_note_personnel']?></textarea>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Machinery -->
            <div class="col-md-4 border-end">
                <div class="bg-secondary text-white text-center small py-1 d-flex justify-content-between px-2">
                    <span>ماشین آلات</span>
                    <?php if($can_edit_contractor): ?>
                        <button type="button" class="btn btn-xs btn-light py-0" onclick="addMac()" style="font-size: 0.7rem; line-height: 1.5;">+</button>
                    <?php endif; ?>
                </div>
                <table class="table table-bordered mb-0 khatam-table">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th style="width: 55px;">واحد</th>
                            <th style="width: 40px;">کل</th>
                            <th style="width: 50px;">فعال</th>
                            <th class="consultant-col" style="width: 80px;">توضیح</th>
                        </tr>
                    </thead>
                    <tbody id="macBody">
                        <?php $mi=0; foreach($machinery as $m): 
                             $hasReview = !empty($m['consultant_active_count']);
                             $crossClass = $hasReview ? 'crossed-out' : '';
                        ?>
                            <tr>
                                <input type="hidden" name="machinery[<?=$mi?>][id]" value="<?=$m['id']?>">
                                <td style="position: relative;">
                                    <input type="text" name="machinery[<?=$mi?>][machine_name]" value="<?=$m['machine_name']?>" list="toolsList" class="khatam-input text-start" <?=$ro?>>
                                    <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
                                </td>
                                <td>
                                    <select name="machinery[<?=$mi?>][unit]" class="khatam-input" style="font-size:0.75rem; padding:0;" <?=$can_edit_contractor?'':'disabled'?>>
                                        <?php foreach($unit_list as $u) echo "<option value='$u' ".($m['unit']==$u?'selected':'').">$u</option>"; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="machinery[<?=$mi?>][total_count]" value="<?=$m['total_count']?>" class="khatam-input"  <?=$ro?> min="0">
                                </td>
                                <td>
                                    <div class="cell-wrapper">
                                        <input type="number" name="machinery[<?=$mi?>][active_count]" value="<?=$m['active_count']?>" class="val-contractor target-mac-<?=$mi?> <?=$crossClass?>" <?=$ro?> min="0">
                                        <?php if($is_consultant || $hasReview): ?>
                                            <input type="number" name="machinery[<?=$mi?>][consultant_active_count]" value="<?=$m['consultant_active_count']?>" class="val-consultant" placeholder="ت" <?=$cons_ro?> oninput="toggleCrossout(this, '.target-mac-<?=$mi?>')" min="0">
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="consultant-col p-0">
                                    <?php if($is_consultant || !empty($m['consultant_comment'])): ?>
                                        <textarea name="machinery[<?=$mi?>][consultant_comment]" class="form-control form-control-sm consultant-input border-0 p-1" rows="2" style="font-size:0.7rem; resize:none; min-height:35px;" placeholder=".." <?=$cons_ro?>><?=$m['consultant_comment']?></textarea>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php $mi++; endforeach; ?>
                    </tbody>
                </table>
                
                <?php if($is_consultant || !empty($report['consultant_note_machinery'])): ?>
                    <div class="p-2 bg-light border-top">
                        <small class="text-danger fw-bold">یادداشت نظارت:</small>
                        <textarea name="consultant_note_machinery" class="form-control form-control-sm" rows="2" <?=$cons_ro?>><?=$report['consultant_note_machinery']?></textarea>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Materials (IN/OUT) - UPDATED WITH UNIT -->
<div class="col-md-3">
    <div class="d-flex flex-column h-100">
        <?php foreach(['in'=>$mat_in, 'out'=>$mat_out] as $type => $data): 
            $lbl = $type=='in'?'ورودی (IN)':'خروجی (OUT)'; 
            $cls=$type=='in'?'success':'danger'; 
        ?>
        <div class="flex-grow-1 border-bottom">
            <div class="bg-<?=$cls?> text-white text-center small py-1 d-flex justify-content-between px-2">
                <span><?=$lbl?></span>
                <?php if($can_edit_contractor): ?>
                    <button type="button" class="btn btn-xs btn-light py-0" onclick="addMat('<?=$type?>')" style="font-size: 0.7rem; line-height: 1.5;">+</button>
                <?php endif; ?>
            </div>
            <table class="table table-bordered mb-0 khatam-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">نام</th>
                        <th style="width: 30%;">مقدار</th>
                        <th style="width: 30%;">واحد</th>
                    </tr>
                </thead>
                <tbody id="mat<?= ucfirst($type) ?>Body">
                    <?php $idx=0; foreach($data as $m): 
                        $hasReview = !empty($m['consultant_quantity']);
                        $crossClass = $hasReview ? 'crossed-out' : '';
                    ?>
                        <tr>
                            <input type="hidden" name="mat_<?=$type?>[<?=$idx?>][id]" value="<?=$m['id']?>">
                            <td style="position: relative;">
                                <input type="text" name="mat_<?=$type?>[<?=$idx?>][name]" value="<?=$m['material_name']?>" list="materialsList" class="khatam-input" placeholder="نام" <?=$ro?>>
                                <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
                            </td>
                            <td>
                                <div class="cell-wrapper">
                                    <input type="text" name="mat_<?=$type?>[<?=$idx?>][quantity]" value="<?=$m['quantity']?>" class="val-contractor target-mat-<?=$type?>-<?=$idx?> <?=$crossClass?>" <?=$ro?>>
                                    <?php if($is_consultant || $hasReview): ?>
                                        <input type="text" name="mat_<?=$type?>[<?=$idx?>][consultant_quantity]" value="<?=$m['consultant_quantity']?>" class="val-consultant" placeholder="ت" <?=$cons_ro?> oninput="toggleCrossout(this, '.target-mat-<?=$type?>-<?=$idx?>')">
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <select name="mat_<?=$type?>[<?=$idx?>][unit]" class="khatam-input" style="font-size:0.75rem; padding:1px;" <?=$can_edit_contractor?'':'disabled'?>>
                                    <?php foreach($unit_list as $u) echo "<option value='$u' ".($m['unit']==$u?'selected':'').">$u</option>"; ?>
                                </select>
                            </td>
                        </tr>
                    <?php $idx++; endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
        
        <?php if($is_consultant || !empty($report['consultant_note_materials'])): ?>
            <div class="p-2 bg-light">
                <small class="text-danger fw-bold">یادداشت نظارت:</small>
                <textarea name="consultant_note_materials" class="form-control form-control-sm" rows="1" <?=$cons_ro?>><?=$report['consultant_note_materials']?></textarea>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="sec-header mt-3">
    مستندات مواد (بارنامه، فاکتور، تصاویر)
    <?php if($can_edit_contractor): ?>
        <button type="button" class="btn btn-sm btn-light float-end py-0" onclick="addMaterialDoc()">+</button>
    <?php endif; ?>
</div>
<div class="card">
    <div class="card-body">
        <table class="table table-bordered table-sm khatam-table bg-white">
            <thead>
                <tr>
                    <th style="width: 30%;">نام ماده</th>
                    <th style="width: 20%;">نوع (ورودی/خروجی)</th>
                    <th style="width: 30%;">توضیحات</th>
                    <th style="width: 20%;">فایل</th>
                </tr>
            </thead>
            <tbody id="materialDocsBody">
                <?php 
                // Load existing material documents
                if($report_id) {
                    $stmt = $pdo->prepare("SELECT * FROM ps_daily_report_material_docs WHERE report_id = ?");
                    $stmt->execute([$report_id]);
                    $matDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $mdi = 0;
                    foreach($matDocs as $md):
                ?>
                    <tr>
                        <input type="hidden" name="material_docs[<?=$mdi?>][id]" value="<?=$md['id']?>">
                        <td style="position: relative;">
                            <input type="text" name="material_docs[<?=$mdi?>][material_name]" value="<?=$md['material_name']?>" list="materialsList" class="khatam-input" <?=$ro?>>
                            <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
                        </td>
                        <td>
                            <select name="material_docs[<?=$mdi?>][type]" class="form-select form-select-sm" <?=$dis?>>
                                <option value="IN" <?=$md['type']=='IN'?'selected':''?>>ورودی</option>
                                <option value="OUT" <?=$md['type']=='OUT'?'selected':''?>>خروجی</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="material_docs[<?=$mdi?>][description]" value="<?=$md['description']?>" class="khatam-input" <?=$ro?>>
                        </td>
                        <td>
                            <?php if(!empty($md['file_path'])): ?>
                                <a href="<?=$md['file_path']?>" target="_blank" class="btn btn-sm btn-outline-primary">مشاهده</a>
                                <input type="hidden" name="material_docs[<?=$mdi?>][existing_file]" value="<?=$md['file_path']?>">
                            <?php endif; ?>
                            <?php if($can_edit_contractor): ?>
                                <input type="file" name="material_doc_file_<?=$mdi?>" class="form-control form-control-sm mt-1" accept="image/*,.pdf">
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php 
                    $mdi++;
                    endforeach; 
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
<div class="sec-header mt-3">
    تصاویر روزانه پروژه
    <?php if($can_edit_contractor): ?>
        <button type="button" class="btn btn-sm btn-light float-end py-0" onclick="addDailyPhoto()">+</button>
    <?php endif; ?>
</div>
<div class="card">
    <div class="card-body">
        <div class="row" id="dailyPhotosContainer">
            <?php 
            // Load existing daily photos
            if($report_id) {
                $stmt = $pdo->prepare("SELECT * FROM ps_daily_report_photos WHERE report_id = ? ORDER BY id");
                $stmt->execute([$report_id]);
                $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $pi = 0;
                foreach($photos as $photo):
            ?>
                <div class="col-md-3 mb-3 photo-item" data-photo-id="<?=$photo['id']?>">
                    <div class="card">
                        <img src="<?=$photo['photo_path']?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="Photo">
                        <div class="card-body p-2">
                            <input type="hidden" name="daily_photos[<?=$pi?>][id]" value="<?=$photo['id']?>">
                            <input type="hidden" name="daily_photos[<?=$pi?>][existing_path]" value="<?=$photo['photo_path']?>">
                            <input type="text" name="daily_photos[<?=$pi?>][caption]" value="<?=$photo['caption']?>" 
                                   class="form-control form-control-sm mb-1" placeholder="عنوان تصویر" <?=$ro?>>
                            <?php if($can_edit_contractor): ?>
                                <button type="button" class="btn btn-sm btn-danger w-100" onclick="removePhoto(this)">حذف</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php 
                $pi++;
                endforeach; 
            }
            ?>
        </div>
        <?php if($can_edit_contractor): ?>
            <div class="text-center">
                <input type="file" id="newPhotoInput" multiple accept="image/*" style="display: none;" onchange="handlePhotoUpload(this)">
                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('newPhotoInput').click()">
                    <i class="fas fa-camera"></i> افزودن تصاویر جدید
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

        <!-- SECTION 2: ACTIVITIES -->
<div class="sec-header">
    عملیات اجرایی 
    <?php if($can_edit_contractor): ?>
        <button type="button" class="btn btn-sm btn-light float-end py-0" onclick="addAct()">+</button>
    <?php endif; ?>
</div>
<div class="table-responsive bg-white border">
    <table class="table table-bordered mb-0 khatam-table" style="font-size: 0.85rem;">
        <thead>
            <tr>
                <th rowspan="2" style="width: 40px;">ردیف</th>
                <th rowspan="2" style="min-width: 180px;">شرح فعالیت</th>
                <th rowspan="2" style="min-width: 120px;">جبهه کاری</th>
                <th rowspan="2" style="width: 100px;">موقعیت</th>
                <th rowspan="2" style="width: 80px;">حجم کل</th>
                <th colspan="3" style="text-align: center;">وضعیت</th>
                <th colspan="3" style="text-align: center;">مقادیر انجام شده</th>
                <th rowspan="2" style="width: 80px;">واحد اجرا</th>
                <th colspan="3" style="text-align: center;">تخصیص نیروی انسانی</th>
                <th rowspan="2" style="min-width: 150px;">توضیحات</th>
            </tr>
            <tr>
                <th style="width: 55px;">در حال انجام</th>
                <th style="width: 55px;">متوقف</th>
                <th style="width: 55px;">اتمام</th>
                <th style="width: 70px;">روز</th>
                <th style="width: 70px;">شب</th>
                <th style="width: 70px;" class="bg-warning bg-opacity-25">تاکنون (خودکار)</th>
                <th style="width: 55px;">ایمنی</th>
                <th style="width: 55px;">استاد کار</th>
                <th style="width: 55px;">کارگر</th>
            </tr>
        </thead>
        <tbody id="actBody">
            <?php $ai=1; foreach($activities as $ix=>$a): 
                $hasQtyReview = !empty($a['consultant_qty_day']) || !empty($a['consultant_qty_night']);
                $crossQty = $hasQtyReview ? 'crossed-out' : '';
                
                // Use calculated cumulative if available, otherwise use stored value
                $displayCumulative = $a['calculated_cumulative'] ?? $a['qty_cumulative'] ?? 0;
            ?>
                <tr>
                    <td style="position: relative;">
                        <?=$ai++?>
                        <input type="hidden" name="activities[<?=$ix?>][id]" value="<?=$a['id']?>">
                        <input type="hidden" name="activities[<?=$ix?>][activity_id]" value="<?=$a['activity_id']?>">
                        <?php if($can_edit_contractor): ?>
                            <button type="button" class="btn-delete-row" onclick="deleteRow(this)" style="right: -15px;">×</button>
                        <?php endif; ?>
                    </td>
                    
                    <td>
                        <select name="activities[<?=$ix?>][activity_id]" class="khatam-input" style="font-size:0.8rem;" <?=$dis?> 
                                onchange="this.nextElementSibling.value=this.options[this.selectedIndex].text; updateCumulative(<?=$ix?>)">
                            <?php foreach($act_list as $o): ?>
                                <option value="<?=$o['id']?>" <?=$a['activity_id']==$o['id']?'selected':''?>><?=$o['name']?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="activities[<?=$ix?>][activity_name]" value="<?=$a['act_name']??''?>">
                    </td>
                    
                    <td><input type="text" name="activities[<?=$ix?>][work_front]" value="<?=$a['work_front']?>" 
                              class="khatam-input" <?=$ro?> onchange="updateCumulative(<?=$ix?>)"></td>
                    
                    <td><input type="text" name="activities[<?=$ix?>][location_facade]" value="<?=$a['location_facade']?>" 
                              class="khatam-input" <?=$ro?> onchange="updateCumulative(<?=$ix?>)"></td>
                    
                    <td><input type="text" name="activities[<?=$ix?>][vol_total]" value="<?=$a['vol_total']?>" class="khatam-input" <?=$ro?>></td>
                    
                    <!-- Status Checkboxes -->
                    <td class="text-center"><input type="checkbox" name="activities[<?=$ix?>][status_ongoing]" <?=$a['status_ongoing']?'checked':''?> <?=$dis?>></td>
                    <td class="text-center"><input type="checkbox" name="activities[<?=$ix?>][status_stopped]" <?=$a['status_stopped']?'checked':''?> <?=$dis?>></td>
                    <td class="text-center"><input type="checkbox" name="activities[<?=$ix?>][status_finished]" <?=$a['status_finished']?'checked':''?> <?=$dis?>></td>
                    
                    <!-- Day -->
                    <td>
                        <div class="cell-wrapper">
                            <input type="number" step="0.01" name="activities[<?=$ix?>][qty_day]" value="<?=$a['qty_day']?>" 
                                   class="val-contractor target-act-day-<?=$ix?> <?=$crossQty?>" <?=$ro?> 
                                   min="0" oninput="updateCumulative(<?=$ix?>)">
                            <?php if($is_consultant || !empty($a['consultant_qty_day'])): ?>
                                <input type="number" step="0.01" name="activities[<?=$ix?>][consultant_qty_day]" 
                                       value="<?=$a['consultant_qty_day']??''?>" class="val-consultant" placeholder="ت" <?=$cons_ro?> 
                                       min="0" oninput="toggleCrossout(this, '.target-act-day-<?=$ix?>'); updateCumulative(<?=$ix?>)">
                            <?php endif; ?>
                        </div>
                    </td>
                    
                    <!-- Night -->
                    <td>
                        <div class="cell-wrapper">
                            <input type="number" step="0.01" name="activities[<?=$ix?>][qty_night]" value="<?=$a['qty_night']?>" 
                                   class="val-contractor target-act-night-<?=$ix?> <?=$crossQty?>" <?=$ro?> 
                                   min="0" oninput="updateCumulative(<?=$ix?>)">
                            <?php if($is_consultant || !empty($a['consultant_qty_night'])): ?>
                                <input type="number" step="0.01" name="activities[<?=$ix?>][consultant_qty_night]" 
                                       value="<?=$a['consultant_qty_night']??''?>" class="val-consultant" placeholder="ت" <?=$cons_ro?> 
                                       min="0" oninput="toggleCrossout(this, '.target-act-night-<?=$ix?>'); updateCumulative(<?=$ix?>)">
                            <?php endif; ?>
                        </div>
                    </td>
                    
                    <!-- Cumulative (READONLY - AUTO CALCULATED) -->
                    <td class="bg-warning bg-opacity-10">
                        <div class="cell-wrapper">
                            <input type="number" step="0.01" name="activities[<?=$ix?>][qty_cumulative]" 
                                   value="<?=$displayCumulative?>" 
                                   id="cumulative_<?=$ix?>"
                                   class="val-contractor fw-bold text-primary target-act-cum-<?=$ix?>" 
                                   readonly style="background: #fffbea !important; cursor: not-allowed;">
                            <?php if($is_consultant || !empty($a['consultant_qty_cumulative'])): ?>
                                <input type="number" step="0.01" name="activities[<?=$ix?>][consultant_qty_cumulative]" 
                                       value="<?=$a['consultant_qty_cumulative']??''?>" class="val-consultant" placeholder="ت" <?=$cons_ro?> 
                                       oninput="toggleCrossout(this, '.target-act-cum-<?=$ix?>')">
                            <?php endif; ?>
                        </div>
                    </td>
                    
                    <td>
                        <select name="activities[<?=$ix?>][unit]" class="khatam-input" style="font-size:0.75rem; padding:2px;" <?=$dis?>>
                            <?php foreach($unit_list as $u): ?>
                                <option value="<?=$u?>" <?=$a['unit']==$u?'selected':''?>><?=$u?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    
                    <td><input type="number" name="activities[<?=$ix?>][pers_safety]" value="<?=$a['pers_safety']?>" class="khatam-input" <?=$ro?>min="0"></td>
                    <td><input type="number" name="activities[<?=$ix?>][pers_master]" value="<?=$a['pers_master']?>" class="khatam-input" <?=$ro?> min="0"></td>
                    <td><input type="number" name="activities[<?=$ix?>][pers_worker]" value="<?=$a['pers_worker']?>" class="khatam-input" <?=$ro?> min="0"></td>
                    
                    <td>
                        <?php if($is_consultant): ?>
                            <textarea name="activities[<?=$ix?>][consultant_comment]" class="form-control form-control-sm border-0 p-1" 
                                      rows="2" style="font-size:0.75rem; resize:none;" placeholder="یادداشت..." <?=$cons_ro?>><?=$a['consultant_comment']?></textarea>
                        <?php elseif(!empty($a['consultant_comment'])): ?>
                            <span class="text-danger small"><?=htmlspecialchars($a['consultant_comment'])?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if($is_consultant || !empty($report['consultant_note_activities'])): ?>
        <div class="p-2 bg-light border-top">
            <small class="text-danger fw-bold">یادداشت نظارت (فعالیت‌ها):</small>
            <textarea name="consultant_note_activities" class="form-control form-control-sm" rows="2" <?=$cons_ro?>><?=$report['consultant_note_activities']?></textarea>
        </div>
    <?php endif; ?>
</div>
        <!-- SECTION 3: BOTTOM SPLIT -->
        <div class="row mt-3">
            <div class="col-6">
                <table class="table table-bordered table-sm khatam-table bg-white mb-2">
                    <thead class="bg-secondary text-white"><tr><th>#</th><th>جبهه</th><th>آزمایشات</th></tr></thead>
                    <tbody id="testBody">
                        <?php foreach($misc_t as $t): ?>
                            <tr>
                                <td style="position: relative;">
                                    #
                                    <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
                                </td>
                                <td><input type="text" name="misc_test[][front]" value="<?=$t['work_front']?>" class="khatam-input" <?=$ro?>></td>
                                <td><input type="text" name="misc_test[][desc]" value="<?=$t['description']?>" class="khatam-input" <?=$ro?>></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if($can_edit_contractor): ?>
                    <button type="button" class="btn btn-sm btn-light w-100 mb-3" onclick="addMiscRow('testBody','test')">+</button>
                <?php endif; ?>

                <table class="table table-bordered table-sm khatam-table bg-white">
                    <thead class="bg-secondary text-white"><tr><th>#</th><th>جبهه</th><th>مجوزات</th></tr></thead>
                    <tbody id="permitBody">
                        <?php foreach($misc_p as $p): ?>
                            <tr>
                                <td style="position: relative;">
                                    #
                                    <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
                                </td>
                                <td><input type="text" name="misc_permit[][front]" value="<?=$p['work_front']?>" class="khatam-input" <?=$ro?>></td>
                                <td><input type="text" name="misc_permit[][desc]" value="<?=$p['description']?>" class="khatam-input" <?=$ro?>></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if($can_edit_contractor): ?>
                    <button type="button" class="btn btn-sm btn-light w-100" onclick="addMiscRow('permitBody','permit')">+</button>
                <?php endif; ?>
            </div>

            <div class="col-6">
                <table class="table table-bordered table-sm khatam-table bg-white h-100">
                    <thead class="bg-secondary text-white"><tr><th>شرح اقدامات و ملاحظات HSE</th></tr></thead>
                    <tbody id="hseBody">
                        <?php foreach($misc_h as $h): ?>
                            <tr>
                                <td style="position: relative;">
                                    <input type="text" name="misc_hse[][desc]" value="<?=$h['description']?>" class="khatam-input text-start px-2" <?=$ro?>>
                                    <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php for($k=count($misc_h); $k<5; $k++): ?>
                            <tr>
                                <td style="position: relative;">
                                    <input type="text" name="misc_hse[][desc]" class="khatam-input text-start px-2" <?=$ro?>>
                                    <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Problems & Notes -->
        <div class="mt-3 card p-3">
            <label class="fw-bold">توضیحات و مشکلات (پیمانکار):</label>
            <textarea name="problems_and_obstacles" class="form-control" rows="3" <?=$ro?>><?=$report['problems_and_obstacles']?></textarea>
        </div>
        
        <div class="mt-2 card p-3 bg-warning bg-opacity-10 border-warning">
            <label class="fw-bold text-danger">توضیحات کلی نظارت:</label>
            <textarea name="consultant_notes" class="form-control" rows="3" <?=$cons_ro?>><?=$report['consultant_notes']?></textarea>
        </div>

        <!-- Actions -->
        <div class="text-center mt-4 mb-5 gap-2 d-flex justify-content-center">
    <a href="daily_reports_dashboard_ps.php" class="btn btn-secondary">بازگشت</a>
    
    <?php if($can_edit_contractor): ?>
        <button type="button" class="btn btn-outline-primary" onclick="saveForm('draft')">ذخیره موقت</button>
        <button type="button" class="btn btn-success px-4" onclick="saveForm('submit')">ارسال نهایی</button>
    <?php endif; ?>
    
    <?php if($can_edit_consultant): ?>
        <button type="button" class="btn btn-primary px-4" onclick="saveForm('consultant_review')">
            <?= $is_approved ? 'ویرایش بررسی' : 'ثبت بررسی' ?>
        </button>
    <?php endif; ?>
    
    <?php if($form_locked): ?>
        <span class="badge bg-warning text-dark p-2">
            <i class="fas fa-lock"></i> فرم قفل شده - فقط مشاهده
        </span>
    <?php endif; ?>
</div>
    </form>
</div>
<?php if(!empty($auditLogs)): ?>
<div class="card mb-3 border-info">
    <div class="card-header bg-info bg-opacity-10">
        <h6 class="mb-0"><i class="fas fa-history"></i> تاریخچه ویرایش‌ها</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>زمان</th>
                        <th>کاربر</th>
                        <th>نقش</th>
                        <th>وضعیت قبلی</th>
                        <th>وضعیت جدید</th>
                        <th>یادداشت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($auditLogs as $log): 
                        $jtime = jdate('Y/m/d H:i', strtotime($log['edit_timestamp']));
                    ?>
                    <tr>
                        <td class="small"><?= $jtime ?></td>
                        <td><?= htmlspecialchars($log['display_name']) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($log['edited_by_role']) ?></span></td>
                        <td><span class="status-badge status-<?= $log['previous_status'] ?>"><?= $log['previous_status'] ?></span></td>
                        <td><span class="status-badge status-<?= $log['new_status'] ?>"><?= $log['new_status'] ?></span></td>
                        <td class="small text-muted"><?= htmlspecialchars($log['edit_notes']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
    jalaliDatepicker.startWatch({ zIndex: 9999 });
    const unitList = <?php echo json_encode($unit_list); ?>;
    const toolsList = <?php echo json_encode($tools_list); ?>;
    const materialsList = <?php echo json_encode($materials_list); ?>;
    const isContractor = <?php echo $can_edit_contractor ? 'true' : 'false'; ?>;
    const isConsultant = <?php echo $is_consultant ? 'true' : 'false'; ?>;
    
    function toggleCrossout(input, targetSel) {
        const target = input.closest('.cell-wrapper').querySelector(targetSel) || input.closest('tr').querySelector(targetSel);
        if(target) { 
            if(input.value.trim() !== '') target.classList.add('crossed-out'); 
            else target.classList.remove('crossed-out'); 
        }
    }
    
    function deleteRow(btn) {
        if(confirm('آیا از حذف این ردیف اطمینان دارید؟')) {
            btn.closest('tr').remove();
        }
    }
    
    function addPersonnel() {
        const idx = Date.now();
        const consultantHtml = isConsultant ? 
            `<input type="number" name="personnel[${idx}][consultant_count]" class="val-consultant" placeholder="تایید" oninput="toggleCrossout(this, '.target-pers-${idx}')">` : '';

        const row = `<tr>
            <td class="bg-light" style="position: relative;">
                <input type="hidden" name="personnel[${idx}][id]" value="">
                <input type="text" name="personnel[${idx}][role_name]" class="khatam-input text-start" placeholder="شغل جدید">
                <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
            </td>
            <td>
                <div class="cell-wrapper">
                    <input type="text" readonly class="val-contractor fw-bold target-pers-${idx}" value="0" id="pers_total_${idx}">
                    ${consultantHtml}
                </div>
            </td>
            <td><input type="number" name="personnel[${idx}][count]" class="khatam-input" value="0" oninput="calcPersTotal(${idx})"></td>
            <td><input type="number" name="personnel[${idx}][count_night]" class="khatam-input" value="0" oninput="calcPersTotal(${idx})"></td>
        </tr>`;
        
        document.getElementById('personnelBody').insertAdjacentHTML('beforeend', row);
    }
    
    function addMac() {
        const i = Date.now();
        const unitOpts = unitList.map(u => `<option value="${u}">${u}</option>`).join('');
        const consNote = isConsultant ? 
            `<textarea name="machinery[${i}][consultant_comment]" class="form-control form-control-sm consultant-input border-0 p-1" rows="2" style="font-size:0.7rem; resize:none; min-height:35px;"></textarea>` : '';
        const consInput = isConsultant ? 
            `<input type="number" name="machinery[${i}][consultant_active_count]" class="val-consultant" placeholder="ت" oninput="toggleCrossout(this, '.target-mac-${i}')">` : '';

        const row = `<tr>
            <input type="hidden" name="machinery[${i}][id]" value="">
            <td style="position: relative;">
                <input type="text" name="machinery[${i}][machine_name]" list="toolsList" class="khatam-input text-start">
                <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
            </td>
            <td><select name="machinery[${i}][unit]" class="khatam-input" style="font-size:0.75rem; padding:0;">${unitOpts}</select></td>
            <td><input type="number" name="machinery[${i}][total_count]" class="khatam-input"></td>
            <td>
                <div class="cell-wrapper">
                    <input type="number" name="machinery[${i}][active_count]" class="val-contractor target-mac-${i}">
                    ${consInput}
                </div>
            </td>
            <td class="consultant-col p-0">${consNote}</td>
        </tr>`;
        
        document.getElementById('macBody').insertAdjacentHTML('beforeend', row);
    }
    
    function addMat(type) {
    const i = Date.now();
    const bodyId = type === 'in' ? 'matInBody' : 'matOutBody';
    const unitOpts = unitList.map(u => `<option value="${u}">${u}</option>`).join('');
    const consInput = isConsultant ? 
        `<input type="text" name="mat_${type}[${i}][consultant_quantity]" class="val-consultant" placeholder="ت" oninput="toggleCrossout(this, '.target-mat-${type}-${i}')">` : '';
    
    const row = `<tr>
        <input type="hidden" name="mat_${type}[${i}][id]" value="">
        <td style="position: relative;">
            <input type="text" name="mat_${type}[${i}][name]" list="materialsList" class="khatam-input" placeholder="نام">
            <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
        </td>
        <td>
            <div class="cell-wrapper">
                <input type="text" name="mat_${type}[${i}][quantity]" class="val-contractor target-mat-${type}-${i}">
                ${consInput}
            </div>
        </td>
        <td>
            <select name="mat_${type}[${i}][unit]" class="khatam-input" style="font-size:0.75rem; padding:1px;">
                ${unitOpts}
            </select>
        </td>
    </tr>`;
    
    document.getElementById(bodyId).insertAdjacentHTML('beforeend', row);
}
async function updateCumulative(idx) {
    if(!isContractor) return; // Only contractors need auto-calculation
    
    const activitySelect = document.querySelector(`select[name="activities[${idx}][activity_id]"]`);
    const workFrontInput = document.querySelector(`input[name="activities[${idx}][work_front]"]`);
    const locationInput = document.querySelector(`input[name="activities[${idx}][location_facade]"]`);
    const dayInput = document.querySelector(`input[name="activities[${idx}][qty_day]"]`);
    const nightInput = document.querySelector(`input[name="activities[${idx}][qty_night]"]`);
    const cumulativeInput = document.getElementById(`cumulative_${idx}`);
    
    if(!activitySelect || !cumulativeInput) return;
    
    const activityId = activitySelect.value;
    const workFront = workFrontInput?.value || '';
    const location = locationInput?.value || '';
    const currentDay = parseFloat(dayInput?.value || 0);
    const currentNight = parseFloat(nightInput?.value || 0);
    
    // Get consultant overrides if they exist
    const consultantDay = document.querySelector(`input[name="activities[${idx}][consultant_qty_day]"]`);
    const consultantNight = document.querySelector(`input[name="activities[${idx}][consultant_qty_night]"]`);
    
    const finalDay = (consultantDay && consultantDay.value) ? parseFloat(consultantDay.value) : currentDay;
    const finalNight = (consultantNight && consultantNight.value) ? parseFloat(consultantNight.value) : currentNight;
    
    if(!activityId) {
        cumulativeInput.value = (finalDay + finalNight).toFixed(2);
        return;
    }
    
    try {
        const reportDate = document.querySelector('input[name="report_date"]').value;
        const reportId = document.querySelector('input[name="report_id"]').value || '';
        
        const formData = new FormData();
        formData.append('action', 'get_cumulative');
        formData.append('activity_id', activityId);
        formData.append('work_front', workFront);
        formData.append('location_facade', location);
        formData.append('report_date', reportDate);
        formData.append('report_id', reportId);
        
        const response = await fetch('/pardis/api/get_cumulative_ps.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if(data.success) {
            const dbCumulative = parseFloat(data.cumulative || 0);
            const totalCumulative = dbCumulative + finalDay + finalNight;
            cumulativeInput.value = totalCumulative.toFixed(2);
        } else {
            // Fallback: just add day + night
            cumulativeInput.value = (finalDay + finalNight).toFixed(2);
        }
    } catch(e) {
        console.error('Error calculating cumulative:', e);
        cumulativeInput.value = (finalDay + finalNight).toFixed(2);
    }
}

function addAct() {
    const i = Date.now();
    const actOpts = `<?php foreach($act_list as $o) echo "<option value='{$o['id']}'>{$o['name']}</option>"; ?>`;
    const unitOpts = `<?php foreach($unit_list as $u) echo "<option value='$u'>$u</option>"; ?>`;
    
    const consQtyDay = isConsultant ? `<input type="number" step="0.01" name="activities[${i}][consultant_qty_day]" class="val-consultant" placeholder="ت" oninput="toggleCrossout(this, '.target-act-day-${i}'); updateCumulative(${i})">` : '';
    const consQtyNight = isConsultant ? `<input type="number" step="0.01" name="activities[${i}][consultant_qty_night]" class="val-consultant" placeholder="ت" oninput="toggleCrossout(this, '.target-act-night-${i}'); updateCumulative(${i})">` : '';
    const consQtyCum = isConsultant ? `<input type="number" step="0.01" name="activities[${i}][consultant_qty_cumulative]" class="val-consultant" placeholder="ت" oninput="toggleCrossout(this, '.target-act-cum-${i}')">` : '';
    const consNote = isConsultant ? `<textarea name="activities[${i}][consultant_comment]" class="form-control form-control-sm border-0 p-1" rows="2" style="font-size:0.75rem; resize:none;"></textarea>` : '';
    
    const row = `<tr>
        <td style="position: relative;">
            #
            <input type="hidden" name="activities[${i}][id]" value="">
            <input type="hidden" name="activities[${i}][activity_id]" value="1">
            <button type="button" class="btn-delete-row" onclick="deleteRow(this)" style="right: -15px;">×</button>
        </td>
        <td>
            <select name="activities[${i}][activity_id]" class="khatam-input" style="font-size:0.8rem;" onchange="this.nextElementSibling.value=this.options[this.selectedIndex].text; updateCumulative(${i})">
                ${actOpts}
            </select>
            <input type="hidden" name="activities[${i}][activity_name]">
        </td>
        <td><input type="text" name="activities[${i}][work_front]" class="khatam-input" onchange="updateCumulative(${i})"></td>
        <td><input type="text" name="activities[${i}][location_facade]" class="khatam-input" onchange="updateCumulative(${i})"></td>
        <td><input type="text" name="activities[${i}][vol_total]" class="khatam-input"></td>
        <td class="text-center"><input type="checkbox" name="activities[${i}][status_ongoing]"></td>
        <td class="text-center"><input type="checkbox" name="activities[${i}][status_stopped]"></td>
        <td class="text-center"><input type="checkbox" name="activities[${i}][status_finished]"></td>
        <td>
            <div class="cell-wrapper">
                <input type="number" step="0.01" name="activities[${i}][qty_day]" class="val-contractor target-act-day-${i}" oninput="updateCumulative(${i})">
                ${consQtyDay}
            </div>
        </td>
        <td>
            <div class="cell-wrapper">
                <input type="number" step="0.01" name="activities[${i}][qty_night]" class="val-contractor target-act-night-${i}" oninput="updateCumulative(${i})">
                ${consQtyNight}
            </div>
        </td>
        <td class="bg-warning bg-opacity-10">
            <div class="cell-wrapper">
                <input type="number" step="0.01" name="activities[${i}][qty_cumulative]" id="cumulative_${i}" class="val-contractor fw-bold text-primary target-act-cum-${i}" readonly style="background: #fffbea !important; cursor: not-allowed;" value="0">
                ${consQtyCum}
            </div>
        </td>
        <td><select name="activities[${i}][unit]" class="khatam-input" style="font-size:0.75rem; padding:2px;">${unitOpts}</select></td>
        <td><input type="number" name="activities[${i}][pers_safety]" class="khatam-input"></td>
        <td><input type="number" name="activities[${i}][pers_master]" class="khatam-input"></td>
        <td><input type="number" name="activities[${i}][pers_worker]" class="khatam-input"></td>
        <td>${consNote}</td>
    </tr>`;
    
    document.getElementById('actBody').insertAdjacentHTML('beforeend', row);
}


    function addMiscRow(id, type) {
        let html = '';
        
        if(type === 'hse') {
            html = `<tr>
                <td style="position: relative;">
                    <input type="text" name="misc_hse[][desc]" class="khatam-input text-start px-2">
                    <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
                </td>
            </tr>`;
        } else {
            const inputName = type === 'permit' ? 'misc_permit' : 'misc_test';
            html = `<tr>
                <td style="position: relative;">
                    #
                    <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
                </td>
                <td><input type="text" name="${inputName}[][front]" class="khatam-input"></td>
                <td><input type="text" name="${inputName}[][desc]" class="khatam-input"></td>
            </tr>`;
        }
        
        document.getElementById(id).insertAdjacentHTML('beforeend', html);
    }
    
    function calcPersTotal(idx) {
        const dayInput = document.querySelector(`input[name="personnel[${idx}][count]"]`);
        const nightInput = document.querySelector(`input[name="personnel[${idx}][count_night]"]`);
        const totalInput = document.getElementById(`pers_total_${idx}`);
        
        if(dayInput && nightInput && totalInput) {
            const day = parseInt(dayInput.value) || 0;
            const night = parseInt(nightInput.value) || 0;
            totalInput.value = day + night;
        }
    }

    async function saveForm(action) {
    const fd = new FormData(document.getElementById('mainForm'));
    fd.append('save_action', action);
    
    // Handle disabled inputs
    if(document.querySelector('[name="contractor_fa_name_disp"]')?.disabled) 
        fd.append('contractor_fa_name', document.getElementById('h_cont').value);
    if(document.querySelector('[name="block_name_disp"]')?.disabled) 
        fd.append('block_name', document.getElementById('h_block').value);

    // Confirmation for editing approved reports
    <?php if($is_approved && $is_superuser): ?>
        if(action !== 'consultant_review') {
            if(!confirm('این گزارش تایید شده است. آیا مطمئن هستید که می‌خواهید آن را ویرایش کنید؟\nاین تغییر ثبت خواهد شد.')) {
                return;
            }
        }
    <?php endif; ?>

    try {
        const res = await fetch('/pardis/api/save_daily_report_ps.php', { method:'POST', body:fd });
        const json = await res.json();
        if(json.success) { 
            alert('ذخیره شد'); 
            location.reload(); 
        } else {
            alert(json.message);
        }
    } catch(e) { 
        alert('خطا در ارتباط با سرور'); 
        console.error(e);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Get all input fields in tables
    const inputs = document.querySelectorAll('.khatam-input, .val-contractor, .val-consultant');
    
    inputs.forEach((input, index) => {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                const nextIndex = e.shiftKey ? index - 1 : index + 1;
                if (inputs[nextIndex]) {
                    inputs[nextIndex].focus();
                    inputs[nextIndex].select();
                }
            }
            
            // Prevent Enter from submitting form
            if (e.key === 'Enter') {
                e.preventDefault();
                const nextIndex = index + 1;
                if (inputs[nextIndex]) {
                    inputs[nextIndex].focus();
                    inputs[nextIndex].select();
                }
            }
        });
    });
    
    // Prevent negative numbers on all number inputs
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', function() {
            if (this.value < 0) {
                this.value = 0;
            }
        });
        
        // Also prevent typing minus sign
        input.addEventListener('keydown', function(e) {
            if (e.key === '-' || e.key === 'e' || e.key === 'E') {
                e.preventDefault();
            }
        });
    });
});
async function saveAsTemplate() {
    const templateName = prompt('نام قالب را وارد کنید:');
    if (!templateName) return;
    
    // Collect form data
    const templateData = {
        contractor_fa_name: document.querySelector('[name="contractor_fa_name"]').value,
        block_name: document.querySelector('[name="block_name"]').value,
        work_hours_day: document.querySelector('[name="work_hours_day"]').value,
        work_hours_night: document.querySelector('[name="work_hours_night"]').value,
        personnel: collectArrayData('personnel'),
        machinery: collectArrayData('machinery'),
        activities: collectArrayData('activities'),
        misc_permit: collectArrayData('misc_permit'),
        misc_test: collectArrayData('misc_test'),
        misc_hse: collectArrayData('misc_hse')
    };
    
    const fd = new FormData();
    fd.append('action', 'save_template');
    fd.append('template_name', templateName);
    fd.append('template_data', JSON.stringify(templateData));
    
    try {
        const res = await fetch('/pardis/api/template_manager_ps.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            alert('قالب با موفقیت ذخیره شد');
        } else {
            alert(json.message);
        }
    } catch(e) {
        alert('خطا در ذخیره قالب');
        console.error(e);
    }
}

async function loadTemplate() {
    // Get list of templates
    const fd = new FormData();
    fd.append('action', 'list_templates');
    
    try {
        const res = await fetch('/pardis/api/template_manager_ps.php', { method: 'POST', body: fd });
        const json = await res.json();
        
        if (!json.success || !json.templates || json.templates.length === 0) {
            alert('هیچ قالبی یافت نشد');
            return;
        }
        
        // Show template selection dialog
        const templateList = json.templates.map((t, i) => `${i + 1}. ${t.name} (${t.date})`).join('\n');
        const selection = prompt(`قالب مورد نظر را انتخاب کنید:\n\n${templateList}\n\nشماره را وارد کنید:`);
        
        if (!selection || isNaN(selection)) return;
        
        const templateIndex = parseInt(selection) - 1;
        if (templateIndex < 0 || templateIndex >= json.templates.length) {
            alert('انتخاب نامعتبر');
            return;
        }
        
        const template = json.templates[templateIndex];
        applyTemplate(template.data);
        
    } catch(e) {
        alert('خطا در بارگذاری قالب');
        console.error(e);
    }
}

function collectArrayData(fieldName) {
    const data = [];
    const elements = document.querySelectorAll(`[name^="${fieldName}["]`);
    const indexes = new Set();
    
    elements.forEach(el => {
        const match = el.name.match(/\[(\d+)\]/);
        if (match) indexes.add(match[1]);
    });
    
    indexes.forEach(idx => {
        const rowData = {};
        const inputs = document.querySelectorAll(`[name^="${fieldName}[${idx}]"]`);
        
        inputs.forEach(input => {
            const fieldMatch = input.name.match(/\[([^\]]+)\]$/);
            if (fieldMatch) {
                const field = fieldMatch[1];
                if (input.type === 'checkbox') {
                    rowData[field] = input.checked;
                } else {
                    rowData[field] = input.value;
                }
            }
        });
        
        if (Object.keys(rowData).length > 0) {
            data.push(rowData);
        }
    });
    
    return data;
}

function applyTemplate(templateData) {
    if (!confirm('این عملیات داده‌های فعلی را جایگزین می‌کند. ادامه می‌دهید؟')) {
        return;
    }
    
    // Apply header data
    if (templateData.contractor_fa_name) {
        document.querySelector('[name="contractor_fa_name_disp"]').value = templateData.contractor_fa_name;
        document.getElementById('h_cont').value = templateData.contractor_fa_name;
    }
    if (templateData.block_name) {
        document.querySelector('[name="block_name_disp"]').value = templateData.block_name;
        document.getElementById('h_block').value = templateData.block_name;
    }
    if (templateData.work_hours_day) {
        document.querySelector('[name="work_hours_day"]').value = templateData.work_hours_day;
    }
    if (templateData.work_hours_night) {
        document.querySelector('[name="work_hours_night"]').value = templateData.work_hours_night;
    }
    
    // Clear and rebuild personnel
    if (templateData.personnel) {
        document.getElementById('personnelBody').innerHTML = '';
        templateData.personnel.forEach((p, i) => {
            addPersonnel();
            setTimeout(() => {
                Object.keys(p).forEach(key => {
                    const input = document.querySelector(`[name="personnel[${Date.now()}][${key}]"]`);
                    if (input) input.value = p[key];
                });
            }, 100);
        });
    }
    
    // Clear and rebuild machinery
    if (templateData.machinery) {
        document.getElementById('macBody').innerHTML = '';
        templateData.machinery.forEach(() => {
            addMac();
        });
    }
    
    // Clear and rebuild activities
    if (templateData.activities) {
        document.getElementById('actBody').innerHTML = '';
        templateData.activities.forEach(() => {
            addAct();
        });
    }
    
    alert('قالب با موفقیت بارگذاری شد');
    location.reload();
}
let materialDocIndex = <?= isset($mdi) ? $mdi : 0 ?>;
let photoIndex = <?= isset($pi) ? $pi : 0 ?>;

function addMaterialDoc() {
    const i = materialDocIndex++;
    const row = `<tr>
        <input type="hidden" name="material_docs[${i}][id]" value="">
        <td style="position: relative;">
            <input type="text" name="material_docs[${i}][material_name]" list="materialsList" class="khatam-input" placeholder="نام ماده">
            <button type="button" class="btn-delete-row" onclick="deleteRow(this)">×</button>
        </td>
        <td>
            <select name="material_docs[${i}][type]" class="form-select form-select-sm">
                <option value="IN">ورودی</option>
                <option value="OUT">خروجی</option>
            </select>
        </td>
        <td>
            <input type="text" name="material_docs[${i}][description]" class="khatam-input" placeholder="توضیحات">
        </td>
        <td>
            <input type="file" name="material_doc_file_${i}" class="form-control form-control-sm" accept="image/*,.pdf">
        </td>
    </tr>`;
    
    document.getElementById('materialDocsBody').insertAdjacentHTML('beforeend', row);
}

function handlePhotoUpload(input) {
    const files = input.files;
    const container = document.getElementById('dailyPhotosContainer');
    
    for(let i = 0; i < files.length; i++) {
        const file = files[i];
        const reader = new FileReader();
        const idx = photoIndex++;
        
        reader.onload = function(e) {
            const photoHtml = `
                <div class="col-md-3 mb-3 photo-item">
                    <div class="card">
                        <img src="${e.target.result}" class="card-img-top" style="height: 200px; object-fit: cover;" alt="Preview">
                        <div class="card-body p-2">
                            <input type="hidden" name="daily_photos[${idx}][id]" value="">
                            <input type="text" name="daily_photos[${idx}][caption]" class="form-control form-control-sm mb-1" placeholder="عنوان تصویر">
                            <input type="hidden" name="daily_photos[${idx}][file_data]" value="${e.target.result}">
                            <button type="button" class="btn btn-sm btn-danger w-100" onclick="removePhoto(this)">حذف</button>
                        </div>
                    </div>
                </div>`;
            
            container.insertAdjacentHTML('beforeend', photoHtml);
        };
        
        reader.readAsDataURL(file);
    }
    
    // Reset input
    input.value = '';
}

function removePhoto(btn) {
    if(confirm('آیا از حذف این تصویر اطمینان دارید?')) {
        btn.closest('.photo-item').remove();
    }
}

function addDailyPhoto() {
    document.getElementById('newPhotoInput').click();
}
</script>
</body>
</html>