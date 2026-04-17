<?php
// daily_report_form_ps.php - PARDIS PROJECT (Fixed Permissions & Review UI)
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
$unit_list = ['عدد', 'متر طول', 'متر مربع', 'کیلوگرم', 'تن', 'دستگاه', 'سرویس', 'نفر','میلیمتر','سامنتیمتر', 'متر مکعب','لیتر','گالن'];

// Roles
$contractor_roles = ["car" => 'شرکت آران سیج', "cod" => 'شرکت طرح و نقش آدرم'];
$is_contractor = array_key_exists($user_role, $contractor_roles);
$is_consultant = in_array($user_role, ['admin', 'superuser', 'supervisor']);

// Lists
$personnel_roles = ['مدیر پروژه','رییس کارگاه','دفتر فنی','کنترل پروژه','نقشه برداری','ایمنی','اجرا','ماشین آلات','استاد کار','کارگر','حراست','خدمات'];
$act_list = $pdo->query("SELECT * FROM ps_project_activities")->fetchAll(PDO::FETCH_ASSOC);
$material_cats = ["پنل GFRC", "شیشه", "پروفیل", "بیس پلیت", "انکر بولت", "چسب", "پشم سنگ", "سایر"];

// Init Data
$report = [
    'id' => '',
    'report_date' => jdate('Y/m/d'),
    'contractor_fa_name' => ($is_contractor && isset($contractor_roles[$user_role]) ? $contractor_roles[$user_role] : ''), 
    'block_name' => '', 
    'work_hours_day' => '8', 
    'work_hours_night' => '0', 
    'contract_number' => '',
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

$personnel_data = [];
foreach($personnel_roles as $r) $personnel_data[] = ['role_name'=>$r, 'count'=>0, 'count_night'=>0];
$machinery = []; 
// Correct Variable Names initialized here
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
        
        $p_db = $pdo->query("SELECT * FROM ps_daily_report_personnel WHERE report_id=$report_id")->fetchAll(PDO::FETCH_ASSOC);
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
        
        $machinery = $pdo->query("SELECT * FROM ps_daily_report_machinery WHERE report_id=$report_id")->fetchAll(PDO::FETCH_ASSOC);
        
        // Fix: Populate $mat_in and $mat_out
        $mats = $pdo->query("SELECT * FROM ps_daily_report_materials WHERE report_id=$report_id")->fetchAll(PDO::FETCH_ASSOC);
        
        // Initialize arrays to avoid undefined variable errors
        $materials_in = []; 
        $materials_out = [];
        
        foreach($mats as $m) {
            if($m['type']=='IN') $materials_in[] = $m; 
            else $materials_out[] = $m;
        }
        
        // Map them to short names if you prefer, but let's stick to one naming convention
        $mat_in = $materials_in;
        $mat_out = $materials_out;

        
        $activities = $pdo->query("SELECT * FROM ps_daily_report_activities WHERE report_id=$report_id")->fetchAll(PDO::FETCH_ASSOC);
        foreach($activities as &$act) {
            $n = $pdo->query("SELECT name FROM ps_project_activities WHERE id={$act['activity_id']}")->fetchColumn();
            $act['act_name'] = $n;
        }
        
        $miscs = $pdo->query("SELECT * FROM ps_daily_report_misc WHERE report_id=$report_id")->fetchAll(PDO::FETCH_ASSOC);
        foreach($miscs as $m) {
            if($m['type']=='PERMIT') $misc_p[]=$m; 
            elseif($m['type']=='TEST') $misc_t[]=$m; 
            elseif($m['type']=='HSE') $misc_h[]=$m;
        }
    }
}

// FIX: Explicitly define permission variables used in HTML
$is_approved = ($report['status'] === 'Approved');
$can_edit_contractor = ($is_contractor && !$is_approved); 
$can_edit_consultant = ($is_consultant && $report_id);

$ro = $can_edit_contractor ? '' : 'readonly';
$dis = $can_edit_contractor ? '' : 'disabled';
$cons_ro = $can_edit_consultant ? '' : 'readonly';

require_once __DIR__ . '/header_pardis.php';
?>

<style>
    /* General Table Styles */
    .khatam-table th { background-color: #e9ecef; vertical-align: middle; text-align: center; font-size: 0.85rem; }
    .khatam-table td { padding: 2px; vertical-align: middle; position: relative; }
    
    /* Inputs */
    .khatam-input { width: 100%; border: none; text-align: center; background: transparent; font-size: 0.9rem; }
    .khatam-input:focus { background: #fff; outline: 1px solid #0d6efd; }
    
    /* Section Headers */
    .sec-header { background: #343a40; color: #fff; padding: 5px 10px; font-weight: bold; border-radius: 5px 5px 0 0; margin-top: 20px; }
    
    /* Status Badges */
    .status-Submitted { background-color: #fff3cd; color: #856404; }
    .status-Approved { background-color: #d1e7dd; color: #0f5132; }
    .status-Rejected { background-color: #f8d7da; color: #842029; }
    
    /* Review Mode Styles */
    .cell-wrapper { position: relative; display: flex; flex-direction: column; justify-content: center; min-height: 35px; }
    
    /* Contractor Input (Top) */
    .val-contractor { 
        border: none; background: transparent; width: 100%; text-align: center; z-index: 2; 
        font-weight: bold; color: #333;
    }
    
    /* Consultant Input (Subtitle / Bottom) */
    .val-consultant { 
        border: none; border-top: 1px dashed #ccc; background: #fff9e6; width: 100%; text-align: center; 
        font-size: 0.85rem; color: #dc3545; font-weight: bold; padding: 2px 0;
    }
    .val-consultant:focus { outline: none; background: #fff; }
    
    /* Strikethrough Effect */
    .crossed-out { text-decoration: line-through !important; opacity: 0.5; color: #999 !important; }
    
    /* Hide add buttons for non-contractors */
    .btn-add { display: <?= $can_edit ? 'inline-block' : 'none' ?> !important; }

    input[data-jdp] { cursor: pointer; background-color: #fff !important; }
</style>

<!-- Assets -->
<link rel="stylesheet" href="/pardis/assets/css/jalalidatepicker.min.css" />
<script src="/pardis/assets/js/jalalidatepicker.min.js"></script>
<datalist id="toolsList">
        <?php foreach ($tools_list as $tool) echo "<option value='$tool'>"; ?>
    </datalist>

    <datalist id="materialsList">
        <?php foreach ($common_materials as $mat) echo "<option value='$mat'>"; ?>
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
            </div>
            <h4 class="fw-bold text-primary m-0">📄 گزارش روزانه عملیات اجرایی نما</h4>
        </div>

        <!-- HEADER -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2"><label>تاریخ</label><input type="text" name="report_date" class="form-control form-control-sm" value="<?=$report['report_date']?>" <?=$ro?> data-jdp required></div>
                    <div class="col-md-2"><label>پیمانکار</label>
                        <select name="contractor_fa_name_disp" class="form-select form-select-sm" <?=$dis?> onchange="document.getElementById('h_cont').value=this.value">
                            <option value="">...</option>
                            <?php foreach($contractor_roles as $c=>$n) echo "<option value='$n' ".($report['contractor_fa_name']==$n?'selected':'').">$n</option>"; ?>
                        </select>
                        <input type="hidden" name="contractor_fa_name" id="h_cont" value="<?=$report['contractor_fa_name']?>">
                    </div>
                    <div class="col-md-2"><label>بلوک</label>
                        <select name="block_name_disp" class="form-select form-select-sm" <?=$dis?> onchange="document.getElementById('h_block').value=this.value">
                            <option value="">...</option>
                            <option value="A" <?=$report['block_name']=='ساختمان کشاورزی'?'selected':''?>>ساختمان کشاورزی</option>
                            <option value="B" <?=$report['block_name']=='ساختمان کتابخانه'?'selected':''?>>ساختمان کتابخانه</option>
                            
                        </select>
                        <input type="hidden" name="block_name" id="h_block" value="<?=$report['block_name']?>">
                    </div>
                    <div class="col-md-1"><label>ساعت روز</label><input type="text" name="work_hours_day" class="form-control form-control-sm" value="<?=$report['work_hours_day']?>" <?=$ro?>></div>
                    <div class="col-md-1"><label>ساعت شب</label><input type="text" name="work_hours_night" class="form-control form-control-sm" value="<?=$report['work_hours_night']?>" <?=$ro?>></div>
                    <div class="col-md-2"><label>دما (Max/Min)</label><div class="d-flex gap-1"><input type="text" name="temp_max" class="form-control form-control-sm" value="<?=$report['temp_max']?>" <?=$ro?>><input type="text" name="temp_min" class="form-control form-control-sm" value="<?=$report['temp_min']?>" <?=$ro?>></div></div>
                </div>
            </div>
        </div>

        <!-- SECTION 1: PERSONNEL & MACHINERY (Reviewable) -->
        <div class="row g-0 border rounded overflow-hidden bg-white">
            
            <!-- Personnel -->
           <!-- SECTION 1: PERSONNEL & MACHINERY (Reviewable) -->
        <div class="row g-0 border rounded overflow-hidden bg-white">
            
            <!-- Personnel (Updated Columns: Job, Total, Day, Night) -->
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
                             // Calculate Total
                             $day = (int)$p['count'];
                             $night = (int)$p['count_night'];
                             $total = $day + $night;
                        ?>
                            <tr>
                                <td class="bg-light">
                                    <input type="hidden" name="personnel[<?=$i?>][id]" value="<?=$p['id']??''?>">
                                    <input type="text" name="personnel[<?=$i?>][role_name]" value="<?=$p['role_name']?>" class="khatam-input text-start" <?=$ro?>>
                                </td>
                                
                                <!-- Total (Calculated + Review) -->
                                <td>
                                    <div class="cell-wrapper">
                                        <input type="text" readonly class="val-contractor fw-bold target-pers-<?=$i?> <?=$crossClass?>" value="<?=$total?>" id="pers_total_<?=$i?>">
                                        <?php if($is_consultant || $hasReview): ?>
                                            <input type="number" name="personnel[<?=$i?>][consultant_count]" value="<?=$p['consultant_count']?>" class="val-consultant" placeholder="تایید" <?=$cons_ro?> oninput="toggleCrossout(this, '.target-pers-<?=$i?>')">
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Day Input -->
                                <td><input type="number" name="personnel[<?=$i?>][count]" value="<?=$day?>" class="khatam-input" oninput="calcPersTotal(<?=$i?>)" <?=$ro?>></td>
                                
                                <!-- Night Input -->
                                <td><input type="number" name="personnel[<?=$i?>][count_night]" value="<?=$night?>" class="khatam-input" oninput="calcPersTotal(<?=$i?>)" <?=$ro?>></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Consultant Note (Visible to Contractor as Readonly) -->
                <?php if($is_consultant || !empty($report['consultant_note_personnel'])): ?>
                    <div class="p-2 bg-light border-top">
                        <small class="text-danger fw-bold">یادداشت نظارت:</small>
                        <textarea name="consultant_note_personnel" class="form-control form-control-sm" rows="2" <?=$cons_ro?>><?=$report['consultant_note_personnel']?></textarea>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Machinery -->
           <!-- Machinery -->
            <div class="col-md-4 border-end">
                <div class="bg-secondary text-white text-center small py-1 d-flex justify-content-between px-2">
                    <span>ماشین آلات</span>
                    <?php if($can_edit_contractor): ?>
                        <button type="button" class="btn btn-xs btn-light py-0 btn-add" onclick="addMac()" style="font-size: 0.7rem; line-height: 1.5;">+</button>
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
                                
                                <!-- Name -->
                                <td>
                                    <input type="text" name="machinery[<?=$mi?>][machine_name]" value="<?=$m['machine_name']?>" list="toolsList" class="khatam-input text-start" <?=$ro?>>
                                </td>
                                
                                <!-- Unit -->
                                <td>
                                    <select name="machinery[<?=$mi?>][unit]" class="khatam-input" style="font-size:0.75rem; padding:0;" <?=$can_edit_contractor?'':'disabled'?>>
                                        <?php foreach($unit_list as $u) echo "<option value='$u' ".($m['unit']==$u?'selected':'').">$u</option>"; ?>
                                    </select>
                                </td>
                                
                                <!-- Total -->
                                <td>
                                    <input type="number" name="machinery[<?=$mi?>][total_count]" value="<?=$m['total_count']?>" class="khatam-input" <?=$ro?>>
                                </td>
                                
                                <!-- Active (Stacked Review) -->
                                <td>
                                    <div class="cell-wrapper">
                                        <input type="number" name="machinery[<?=$mi?>][active_count]" value="<?=$m['active_count']?>" class="val-contractor target-mac-<?=$mi?> <?=$crossClass?>" <?=$ro?>>
                                        <?php if($is_consultant || $hasReview): ?>
                                            <input type="number" name="machinery[<?=$mi?>][consultant_active_count]" value="<?=$m['consultant_active_count']?>" class="val-consultant" placeholder="ت" <?=$cons_ro?> oninput="toggleCrossout(this, '.target-mac-<?=$mi?>')">
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <!-- Consultant Note -->
                                <td class="consultant-col p-0">
                                    <?php if($is_consultant || !empty($m['consultant_comment'])): ?>
                                        <textarea name="machinery[<?=$mi?>][consultant_comment]" class="form-control form-control-sm consultant-input border-0 p-1" rows="2" style="font-size:0.7rem; resize:none; min-height:35px;" placeholder=".." <?=$cons_ro?>><?=$m['consultant_comment']?></textarea>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php $mi++; endforeach; ?>
                    </tbody>
                </table>
            
                
                <!-- Consultant Note -->
                <?php if($is_consultant || !empty($report['consultant_note_machinery'])): ?>
                    <div class="p-2 bg-light border-top">
                        <small class="text-danger fw-bold">یادداشت نظارت:</small>
                        <textarea name="consultant_note_machinery" class="form-control form-control-sm" rows="2" <?=$cons_ro?>><?=$report['consultant_note_machinery']?></textarea>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Materials (IN/OUT) -->
            <div class="col-md-3">
                <div class="d-flex flex-column h-100">
                    <?php foreach(['in'=>$materials_in, 'out'=>$materials_out] as $type => $data): 
                        $lbl = $type=='in'?'ورودی (IN)':'خروجی (OUT)'; $cls=$type=='in'?'success':'danger'; 
                    ?>
                    <div class="flex-grow-1 border-bottom">
                        <div class="bg-<?=$cls?> text-white text-center small py-1 d-flex justify-content-between px-2">
                            <span><?=$lbl?></span>
                            <?php if($can_edit_contractor): ?>
                                <button type="button" class="btn btn-xs btn-light py-0 btn-add" onclick="addMat('<?=$type?>')" style="font-size: 0.7rem; line-height: 1.5;">+</button>
                            <?php endif; ?>
                        </div>
                        <table class="table table-bordered mb-0 khatam-table"><tbody id="mat<?= ucfirst($type) ?>Body">
                            <?php $idx=0; foreach($data as $m): 
                                $hasReview = !empty($m['consultant_quantity']);
                                $crossClass = $hasReview ? 'crossed-out' : '';
                            ?>
                                <tr>
                                    <input type="hidden" name="mat_<?=$type?>[<?=$idx?>][id]" value="<?=$m['id']?>">
                                    <td>
                                        <input type="text" name="mat_<?=$type?>[<?=$idx?>][name]" value="<?=$m['material_name']?>" class="khatam-input" placeholder="نام" <?=$ro?>>
                                    </td>
                                    <td style="width:60px">
                                        <div class="cell-wrapper">
                                            <input type="text" name="mat_<?=$type?>[<?=$idx?>][quantity]" value="<?=$m['quantity']?>" class="val-contractor target-mat-<?=$type?>-<?=$idx?> <?=$crossClass?>" <?=$ro?>>
                                            <?php if($is_consultant || $hasReview): ?>
                                                <input type="text" name="mat_<?=$type?>[<?=$idx?>][consultant_quantity]" value="<?=$m['consultant_quantity']?>" class="val-consultant" placeholder="ت" <?=$cons_ro?> oninput="toggleCrossout(this, '.target-mat-<?=$type?>-<?=$idx?>')">
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php $idx++; endforeach; ?>
                        </tbody></table>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Consultant Note -->
                    <?php if($is_consultant || !empty($report['consultant_note_materials'])): ?>
                        <div class="p-2 bg-light">
                            <small class="text-danger fw-bold">یادداشت نظارت:</small>
                            <textarea name="consultant_note_materials" class="form-control form-control-sm" rows="1" <?=$cons_ro?>><?=$report['consultant_note_materials']?></textarea>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <!-- SECTION 3: BOTTOM SPLIT -->
        <div class="row mt-3">
            <!-- Tests & Permits (Left) -->
            <div class="col-6">
                <!-- Tests Table -->
                <table class="table table-bordered table-sm khatam-table bg-white mb-2">
                    <thead class="bg-secondary text-white"><tr><th>#</th><th>جبهه</th><th>آزمایشات</th></tr></thead>
                    <tbody id="testBody">
                        <?php foreach($misc_t as $t): ?>
                            <tr>
                                <td>#</td>
                                <td><input type="text" name="misc_test[][front]" value="<?=$t['work_front']?>" class="khatam-input"></td>
                                <td><input type="text" name="misc_test[][desc]" value="<?=$t['description']?>" class="khatam-input"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if($can_edit_contractor): ?><button type="button" class="btn btn-sm btn-light w-100 mb-3" onclick="addMiscRow('testBody','test')">+</button><?php endif; ?>

                <!-- Permits Table -->
                <table class="table table-bordered table-sm khatam-table bg-white">
                    <thead class="bg-secondary text-white"><tr><th>#</th><th>جبهه</th><th>مجوزات</th></tr></thead>
                    <tbody id="permitBody">
                        <?php foreach($misc_p as $p): ?>
                            <tr>
                                <td>#</td>
                                <td><input type="text" name="misc_permit[][front]" value="<?=$p['work_front']?>" class="khatam-input"></td>
                                <td><input type="text" name="misc_permit[][desc]" value="<?=$p['description']?>" class="khatam-input"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if($can_edit_contractor): ?><button type="button" class="btn btn-sm btn-light w-100" onclick="addMiscRow('permitBody','permit')">+</button><?php endif; ?>
            </div>

            <!-- HSE Table (Right - Matching Image) -->
            <div class="col-6">
                <table class="table table-bordered table-sm khatam-table bg-white h-100">
                    <thead class="bg-secondary text-white"><tr><th>شرح اقدامات و ملاحظات HSE</th></tr></thead>
                    <tbody id="hseBody">
                        <?php foreach($misc_h as $h): ?>
                            <tr><td><input type="text" name="misc_hse[][desc]" value="<?=$h['description']?>" class="khatam-input text-start px-2"></td></tr>
                        <?php endforeach; ?>
                        <!-- Empty rows to fill space if needed -->
                        <?php for($k=count($misc_h); $k<5; $k++): ?>
                            <tr><td><input type="text" name="misc_hse[][desc]" class="khatam-input text-start px-2"></td></tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>        

        <!-- SECTION 2: ACTIVITIES -->
        <div class="sec-header">عملیات اجرایی <button type="button" class="btn btn-sm btn-light float-end py-0 btn-add" onclick="addAct()">+</button></div>
        <div class="table-responsive bg-white border">
            <table class="table table-bordered mb-0 khatam-table">
                <thead>
                    <tr>
                        <th rowspan="2">#</th><th rowspan="2" style="min-width:150px">شرح</th><th rowspan="2">موقعیت</th><th colspan="2">مقادیر (روزانه)</th><th rowspan="2">نفرات</th><th colspan="2">تجمیعی</th><th rowspan="2">توضیحات</th>
                    </tr>
                    <tr><th>تعداد</th><th>متر</th><th>نصب</th><th>ریجکت</th></tr>
                </thead>
                <tbody id="actBody">
                    <?php $ai=1; foreach($activities as $ix=>$a): 
                        $hasQReview = !empty($a['consultant_quantity']);
                        $crossQ = $hasQReview ? 'crossed-out' : '';
                        $hasMReview = !empty($a['consultant_meterage']);
                        $crossM = $hasMReview ? 'crossed-out' : '';
                    ?>
                        <tr>
                            <td><?=$ai++?><input type="hidden" name="activities[<?=$ix?>][id]" value="<?=$a['id']?>"></td>
                            <td><input type="text" value="<?=$a['act_name']?>" class="khatam-input" readonly></td>
                            <td><input type="text" name="activities[<?=$ix?>][location_facade]" value="<?=$a['location_facade']?>" class="khatam-input" <?=$ro?>></td>
                            
                            <!-- Quantity (With Review) -->
                            <td>
                                <div class="cell-wrapper">
                                    <input type="number" step="0.01" name="activities[<?=$ix?>][contractor_quantity]" value="<?=$a['contractor_quantity']?>" class="val-contractor target-act-q-<?=$ix?> <?=$crossQ?>" <?=$ro?>>
                                    <?php if($is_consultant || $hasQReview): ?>
                                        <input type="number" step="0.01" name="activities[<?=$ix?>][consultant_quantity]" value="<?=$a['consultant_quantity']?>" class="val-consultant" placeholder="تایید" <?=$cons_ro?> oninput="toggleCrossout(this, '.target-act-q-<?=$ix?>')">
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <!-- Meterage (With Review) -->
                            <td>
                                <div class="cell-wrapper">
                                    <input type="number" step="0.01" name="activities[<?=$ix?>][contractor_meterage]" value="<?=$a['contractor_meterage']?>" class="val-contractor target-act-m-<?=$ix?> <?=$crossM?>" <?=$ro?>>
                                    <?php if($is_consultant || $hasMReview): ?>
                                        <input type="number" step="0.01" name="activities[<?=$ix?>][consultant_meterage]" value="<?=$a['consultant_meterage']?>" class="val-consultant" placeholder="تایید" <?=$cons_ro?> oninput="toggleCrossout(this, '.target-act-m-<?=$ix?>')">
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td><input type="number" name="activities[<?=$ix?>][personnel_count]" value="<?=$a['personnel_count']?>" class="khatam-input" <?=$ro?>></td>
                            <td><?=$a['cum_installed_count']?></td>
                            <td class="text-danger"><?=$a['cum_rejected_count']?></td>
                            <td><input type="text" name="activities[<?=$ix?>][desc]" value="<?=$a['consultant_comment']?>" class="khatam-input" <?=$cons_ro?> placeholder="یادداشت..."></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
             <?php if($is_consultant || !empty($report['consultant_note_activities'])): ?>
                <div class="p-2 bg-light border-top"><small class="text-danger fw-bold">یادداشت نظارت (فعالیت‌ها):</small><textarea name="consultant_note_activities" class="form-control form-control-sm" <?=$cons_ro?>><?=$report['consultant_note_activities']?></textarea></div>
            <?php endif; ?>
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
            <?php if($is_consultant): ?>
                <button type="button" class="btn btn-primary px-4" onclick="saveForm('consultant_review')">ثبت بررسی</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
    jalaliDatepicker.startWatch({ zIndex: 9999 });
    const unitList = <?php echo json_encode($unit_list); ?>;
    // Strikethrough Logic
    function toggleCrossout(input, targetSel) {
        const target = input.closest('.cell-wrapper').querySelector(targetSel) || input.closest('tr').querySelector(targetSel);
        if(target) { 
            if(input.value.trim() !== '') target.classList.add('crossed-out'); 
            else target.classList.remove('crossed-out'); 
        }
    }


    
    
    
   function addMac() {
        const i = Date.now();
        // Pass PHP Unit List to JS variable if not already done
        const unitOpts = `<?php foreach($unit_list as $u) echo "<option value='$u'>$u</option>"; ?>`;
        
        // Consultant Columns HTML (Only show if consultant)
        const consNote = <?php echo $is_consultant ? 'true' : 'false'; ?> ? 
            `<textarea name="machinery[${i}][consultant_comment]" class="form-control form-control-sm consultant-input border-0 p-1" rows="2" style="font-size:0.7rem; resize:none; min-height:35px;"></textarea>` : '';
        
        const consInput = <?php echo $is_consultant ? 'true' : 'false'; ?> ? 
            `<input type="number" name="machinery[${i}][consultant_active_count]" class="val-consultant" placeholder="ت" oninput="toggleCrossout(this, '.target-mac-${i}')">` : '';

        const row = `<tr>
            <input type="hidden" name="machinery[${i}][id]" value="">
            
            <!-- Name -->
            <td><input type="text" name="machinery[${i}][machine_name]" list="toolsList" class="khatam-input text-start"></td>
            
            <!-- Unit -->
            <td><select name="machinery[${i}][unit]" class="khatam-input" style="font-size:0.75rem; padding:0;">${unitOpts}</select></td>
            
            <!-- Total -->
            <td><input type="number" name="machinery[${i}][total_count]" class="khatam-input"></td>
            
            <!-- Active -->
            <td>
                <div class="cell-wrapper">
                    <input type="number" name="machinery[${i}][active_count]" class="val-contractor target-mac-${i}">
                    ${consInput}
                </div>
            </td>
            
            <!-- Note -->
            <td class="consultant-col p-0">${consNote}</td>
        </tr>`;
        
        document.getElementById('macBody').insertAdjacentHTML('beforeend', row);
    }

    function checkNewUnit(select) {
        if(select.value === 'new') {
            const newUnit = prompt("نام واحد جدید را وارد کنید:");
            if(newUnit) {
                const opt = document.createElement('option');
                opt.value = newUnit;
                opt.text = newUnit;
                opt.selected = true;
                select.add(opt, select.options[0]); // Add to top
                // Optional: Send AJAX to save this new unit to DB immediately
            } else {
                select.value = unitList[0]; // Revert
            }
        }
    }
    
    function addAct() {
        const i = Date.now();
        const actOpts = `<?php foreach($act_list as $o) echo "<option value='{$o['id']}'>{$o['name']}</option>"; ?>`;
        document.getElementById('actBody').insertAdjacentHTML('beforeend', `<tr><td>#<input type="hidden" name="activities[${i}][activity_id]" value="1"></td><td><select name="activities[${i}][activity_id]" class="khatam-input">${actOpts}</select></td><td><input type="text" name="activities[${i}][location_facade]" class="khatam-input"></td><td><input type="number" name="activities[${i}][contractor_quantity]" class="khatam-input"></td><td><input type="number" name="activities[${i}][contractor_meterage]" class="khatam-input"></td><td><input type="number" name="activities[${i}][personnel_count]" class="khatam-input"></td><td>-</td><td>-</td><td></td></tr>`);
    }

    async function saveForm(action) {
        const fd = new FormData(document.getElementById('mainForm'));
        fd.append('save_action', action);
        // Handle disabled inputs
        if(document.querySelector('[name="contractor_fa_name_disp"]').disabled) 
            fd.append('contractor_fa_name', document.getElementById('h_cont').value);
        if(document.querySelector('[name="block_name_disp"]').disabled) 
            fd.append('block_name', document.getElementById('h_block').value);

        try {
            const res = await fetch('/pardis/api/save_daily_report_ps.php', { method:'POST', body:fd });
            const json = await res.json();
            if(json.success) { alert('ذخیره شد'); location.reload(); } else alert(json.message);
        } catch(e) { alert('Error'); }
    }
        function addPersonnel() {
        const idx = Date.now();
        const consultantHtml = <?php echo $is_consultant ? 'true' : 'false'; ?> ? 
            `<input type="number" name="personnel[${idx}][consultant_count]" class="val-consultant" placeholder="تایید" oninput="toggleCrossout(this, '.target-pers-${idx}')">` : '';

        const row = `<tr>
            <td class="bg-light">
                <input type="hidden" name="personnel[${idx}][id]" value="">
                <input type="text" name="personnel[${idx}][role_name]" class="khatam-input text-start" placeholder="شغل جدید">
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
  function addMiscRow(id, type) {
        let inputName = type === 'permit' ? 'misc_permit[][desc]' : (type === 'test' ? 'misc_test[][desc]' : 'misc_hse[][desc]');
        let frontName = type === 'permit' ? 'misc_permit[][front]' : (type === 'test' ? 'misc_test[][front]' : '');
        
        // HSE doesn't have "Work Front" column in the specific layout we made, others do
        let html = '';
        
        if(type === 'hse') {
            // HSE only has description
            html = `<tr><td><input type="text" name="${inputName}" class="khatam-input text-start px-2"></td></tr>`;
        } else {
            // Permits and Tests have #, Front, Desc
            html = `<tr>
                <td>#</td>
                <td><input type="text" name="${frontName}" class="khatam-input"></td>
                <td><input type="text" name="${inputName}" class="khatam-input"></td>
            </tr>`;
        }
        
        document.getElementById(id).insertAdjacentHTML('beforeend', html);
    }
    // --- JS: Calculate Total (Day + Night) ---
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
</script>
</body>
</html>