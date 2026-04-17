<?php
// daily_report_print_ps.php - PARDIS PROJECT - FINAL FIXED
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

// --- 1. SETUP & DB ---
$pdo = getProjectDBConnection('pardis');
$report_id = $_GET['id'] ?? null;
$sql = "SELECT 
            r.*, 
            -- Renaming the potentially conflicting column from the report table
            c.contract_number AS real_contract_number,  -- The correct contract number from the contractors table
            c.subject AS contract_subject               -- The correct subject from the contractors table
        FROM ps_daily_reports r
        LEFT JOIN ps_contractors c ON TRIM(r.contractor_fa_name) = TRIM(c.name) 
        WHERE  TRIM(r.contractor_fa_name) = TRIM(c.name)  AND r.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$report_id]);
$report1 = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$report1) die('گزارش یافت نشد');



// Fetch Main Report
$stmt = $pdo->prepare("SELECT * FROM ps_daily_reports WHERE id = ?");
$stmt->execute([$report_id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$report) die('گزارش یافت نشد');

// Date Formatting
$report_date_jalali = '';
$day_name = '';
if (!empty($report['report_date'])) {
    $ts = strtotime($report['report_date']);
    $report_date_jalali = jdate('Y/m/d', $ts);
    $day_name = jdate('l', $ts);
}

// Fetch Related Data
$personnel = $pdo->query("SELECT * FROM ps_daily_report_personnel WHERE report_id = $report_id ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$machinery = $pdo->query("SELECT * FROM ps_daily_report_machinery WHERE report_id = $report_id ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Materials (Split In/Out)
$materials = $pdo->query("SELECT * FROM ps_daily_report_materials WHERE report_id = $report_id")->fetchAll(PDO::FETCH_ASSOC);
$mat_in = array_filter($materials, fn($m) => $m['type'] === 'IN');
$mat_out = array_filter($materials, fn($m) => $m['type'] === 'OUT');

// Activities (Fixed Join for act_name)
// We use COALESCE to get name from master list if joined, or just ID if not
$sql_act = "SELECT dra.*, pa.name as act_name 
            FROM ps_daily_report_activities dra 
            LEFT JOIN ps_project_activities pa ON dra.activity_id = pa.id 
            WHERE dra.report_id = $report_id ORDER BY dra.id";
$activities = $pdo->query($sql_act)->fetchAll(PDO::FETCH_ASSOC);

// Misc
$misc = $pdo->query("SELECT * FROM ps_daily_report_misc WHERE report_id = $report_id")->fetchAll(PDO::FETCH_ASSOC);
$misc_hse = array_filter($misc, fn($m) => $m['type'] === 'HSE');
$misc_test = array_filter($misc, fn($m) => $m['type'] === 'TEST');
$misc_permit = array_filter($misc, fn($m) => $m['type'] === 'PERMIT');

// Weather
$weather_list = json_decode($report['weather_list'] ?? '[]', true);
function isWeather($w, $list) { return in_array($w, $list) ? 'checked' : ''; }

// --- 2. PERMISSIONS & SETTINGS ---
session_start();
$user_role = $_SESSION['role'] ?? '';

// 1. Determine Contractor Logo based on Role
// Default logo
$contractor_name = trim($report['contractor_fa_name']); // Name from DB
$right_logo = 'assets/images/logo_contractor.png'; // Fallback default

// MAPPING: [ 'Exact Farsi Name in DB' => 'Image Path' ]
// !!! IMPORTANT: Edit the Farsi names below to match your database exactly !!!
$logo_map = [
    'شرکت طرح و نقش آدرم'       => 'assets/images/logo_cod.png',
    'گروه کد'       => 'assets/images/logo_cod.png',
    'کد'            => 'assets/images/logo_cod.png', // Add variations if unsure
    
    'شرکت آران سیج'      => 'assets/images/logo_car.png',
    'گروه کار'      => 'assets/images/logo_car.png',
    'کار'           => 'assets/images/logo_car.png',
];

if (array_key_exists($contractor_name, $logo_map)) {
    $right_logo = $logo_map[$contractor_name];
}

// --- 3. PERMISSIONS & SETTINGS ---

$user_role = $_SESSION['role'] ?? '';

$can_sign_contractor = in_array($user_role, ['car','cod']);
$can_sign_consultant = in_array($user_role, ['supervisor','superuser','admin']);
$can_sign_employer = in_array($user_role, ['employer','admin']);

// Fetch Print Settings
$user_id = $_SESSION['user_id'] ?? 0;
$settings_stmt = $pdo->prepare("SELECT settings_json FROM ps_print_settings WHERE user_id = ?");
$settings_stmt->execute([$user_id]);
$db_settings = $settings_stmt->fetchColumn();

// Default Settings
$settings = [
    'font' => 'BNazanin',
    'size' => 10,
    'logos' => [
        'right' => $right_logo,  // <--- Uses the logic above
        'center' => 'assets/images/logo_khatam.png',
        'left' => 'assets/images/logo_consultant.png'
    ],
    'breaks' => ['pers'=>false, 'act'=>false]
];

if ($db_settings) {
    $decoded = json_decode($db_settings, true);
    if($decoded) $settings = array_replace_recursive($settings, $decoded);
    
    // FORCE the correct logo even if user has old settings saved
    $settings['logos']['right'] = $right_logo;
}

// Permissions
$can_sign_contractor = in_array($user_role, ['car','cod']);
$can_sign_consultant = in_array($user_role, ['supervisor','superuser','admin']);
$can_sign_employer = in_array($user_role, ['employer','admin']);

// Fetch Print Settings (User specific)
$user_id = $_SESSION['user_id'] ?? 0;
$settings_stmt = $pdo->prepare("SELECT settings_json FROM ps_print_settings WHERE user_id = ?");
$settings_stmt->execute([$user_id]);
$db_settings = $settings_stmt->fetchColumn();

// Default Settings
$settings = [
    'font' => 'BNazanin',
    'size' => 10,
    'logos' => [
        'left' => 'assets/images/logo_consultant.png',  // <--- Uses the logic above
        'center' => 'assets/images/logo_khatam.png',
        'right' => $right_logo
    ],
    'breaks' => ['pers'=>false, 'act'=>false]
];

if ($db_settings) {
    $decoded = json_decode($db_settings, true);
    if($decoded) $settings = array_replace_recursive($settings, $decoded);
    
    // FORCE the correct logo even if user has old settings saved
    $settings['logos']['right'] = $right_logo;
}

// Helper for strings
function safeStr($str) { return htmlspecialchars($str ?? ''); }
function safeFloat($val) { return (float)$val == 0 ? '' : (float)$val; }
function safeNl2Br($str) { return nl2br(safeStr($str)); }

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>گزارش روزانه - <?= $report_id ?></title>
    <link href="/pardis/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="/pardis/assets/css/all.min.css" rel="stylesheet">
    <script src="/pardis/assets/js/signature_pad.umd.min.js"></script>
    
    <style>
        /* Fonts */
        @font-face { font-family: "BNazanin"; src: url("/pardis/assets/fonts/BNazanin.ttf"); }
        @font-face { font-family: "BTitr"; src: url("/pardis/assets/fonts/BTitr.ttf"); font-weight: bold; }
        @font-face { font-family: "Vazir"; src: url("/pardis/assets/fonts/Vazir.woff2"); }
        
        :root {
            --font-family: <?= $settings['font'] ?>, sans-serif;
            --font-size: <?= $settings['size'] ?>pt;
        }

        body {
            background: #525659;
            font-family: var(--font-family);
            margin: 0;
            padding: 20px;
        }

        .sheet {
            background: white;
            width: 210mm; /* A4 Portrait */
            min-height: 297mm;
            margin: 0 auto;
            padding: 5mm; /* Smaller padding */
            position: relative;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }

        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-bottom: 2px; font-size: var(--font-size); }
        th, td { 
            border: 1px solid #000; 
            padding: 2px 3px; 
            text-align: center; 
            vertical-align: middle; 
            line-height: 1.1;
        }
        
        .bg-gray { background-color: #d9d9d9 !important; font-weight: bold; -webkit-print-color-adjust: exact; }
        .text-right { text-align: right !important; }
        .text-start { text-align: right !important; }
        .titr { font-family: "BTitr", sans-serif; }
        .no-border { border: none !important; }
        
        /* Checkboxes */
        .cb { 
            display: inline-block; width: 10px; height: 10px; border: 1px solid #000; margin: 0 2px; position: relative; 
        }
        .cb.checked:after { 
            content: '✖'; position: absolute; top: -5px; left: -1px; font-size: 11px; font-weight: bold; 
        }

        /* Strikethrough for consultant edits */
        .consultant-edit {
            text-decoration: line-through;
            color: #888;
            font-size: 0.8em;
            margin-left: 3px;
        }
        .consultant-val {
            color: #000;
            font-weight: bold;
        }
        .subtitle-note {
            display: block;
            font-size: 0.75em;
            color: #444;
            border-top: 1px dotted #ccc;
            margin-top: 1px;
            text-align: right;
            white-space: pre-wrap;
        }

        /* Header Layout */
        .header-box { display: flex; align-items: center; justify-content: space-between; border: 1px solid #000; padding: 5px; margin-bottom: 2px; }
        .logo-box { width: 80px; text-align: center; }
        .logo-box img { max-width: 100%; max-height: 50px; object-fit: contain; }
        .header-title { flex: 1; text-align: center; }

        /* Signatures */
        .sig-box { height: 60px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .sig-box img { max-height: 50px; max-width: 100%; }
        .sig-placeholder { color: #ccc; font-size: 0.8em; border: 1px dashed #ccc; padding: 5px; border-radius: 4px; }

        /* Print Controls */
        .no-print { display: block; }
        @media print {
            body { background: white; padding: 0; }
            .sheet { box-shadow: none; margin: 0; width: 100%; padding: 0; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
        }

        /* Floating Action Buttons */
        .fab-container {
            position: fixed; bottom: 20px; left: 20px; display: flex; flex-direction: column; gap: 10px; z-index: 999;
        }
        .fab {
            width: 45px; height: 45px; border-radius: 50%; border: none; color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; cursor: pointer; transition: transform 0.2s;
        }
        .fab:hover { transform: scale(1.1); }
        .fab-print { background: #0d6efd; }
        .fab-settings { background: #6c757d; }
        .fab-sign { background: #198754; }
        .fab-upload { background: #ffc107; color: #000; }
    </style>
</head>
<body>

<div class="fab-container no-print">
    <button class="fab fab-print" onclick="window.print()" title="چاپ"><i class="fas fa-print"></i></button>
    <button class="fab fab-settings" onclick="openSettings()" title="تنظیمات"><i class="fas fa-cog"></i></button>
    <button class="fab fab-upload" onclick="openUpload()" title="آپلود اسکن"><i class="fas fa-file-upload"></i></button>
</div>

<div class="sheet">
    
    <table class="no-border" style="margin-bottom: 3px;">
        <tr>
            <td class="no-border" style="width: 20%;">
                <img src="/pardis/<?= $settings['logos']['right'] ?>" style="height: 50px;">
            </td>
            <td class="no-border text-center" style="width: 60%;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <div style="text-align: center;">
                         <img src="/pardis/<?= $settings['logos']['center'] ?>" style="height: 50px; display: block; margin: 0 auto;">
                         <div class="titr" style="font-size: 1.2rem; margin-top: 2px;">گــزارش روزانــه</div>
                    </div>
                </div>
                <div style="font-size: 0.8rem; margin-top: 2px;">پروژه:  دانشگاه خاتم پردیس </div>
            </td>
            <td class="no-border" style="width: 20%;">
                <img src="/pardis/<?= $settings['logos']['left'] ?>" style="height: 50px;">
            </td>
        </tr>
    </table>

   <table>
        <tr class="bg-gray">
            <td width="15%">شماره گزارش</td>
            <td width="15%">تاریخ</td>
            <td width="15%">روز هفته</td>
            <td width="25%">شماره قرارداد</td>
            <td width="30%">بلوک</td>
        </tr>
        <tr>
            <td><?= $report_id ?></td>
            <td dir="ltr"><?= $report_date_jalali ?></td>
            <td><?= $day_name ?></td>
            <td dir="ltr"><?= safeStr($report1['real_contract_number']) ?></td>
            <td><?= safeStr($report['block_name']) ?></td>
        </tr>
        <tr>
            <td class="bg-gray">موضوع قرارداد</td>
            <td colspan="4" class="text-right" style="font-weight:bold;">
                <?= safeStr($report1['contract_subject']) ?>
            </td>
        </tr>
    </table>

    <table>
        <tr class="bg-gray">
            <td width="15%">وضعیت جوی</td>
            <td width="45%" class="text-right" style="font-weight: normal; background: #fff;">
                <span class="cb <?= isWeather('آفتابی', $weather_list) ?>"></span> آفتابی
                <span class="cb <?= isWeather('نیمه ابری', $weather_list) ?>"></span> نیمه ابری
                <span class="cb <?= isWeather('ابری', $weather_list) ?>"></span> ابری
                <span class="cb <?= isWeather('بارانی', $weather_list) ?>"></span> بارانی
                <span class="cb <?= isWeather('برفی', $weather_list) ?>"></span> برفی
                <span class="cb <?= isWeather('باد', $weather_list) ?>"></span> باد
            </td>
            <td width="10%">دما (C)</td>
            <td width="15%">Max: <?= safeStr($report['temp_max']) ?></td>
            <td width="15%">Min: <?= safeStr($report['temp_min']) ?></td>
        </tr>
    </table>

    <table>
        <colgroup>
            <col width="15%"><col width="5%"><col width="5%"><col width="10%">
            <col width="15%"><col width="5%"><col width="5%"><col width="10%">
        </colgroup>
        <tr class="bg-gray">
            <td colspan="4">نیروی انسانی</td>
            <td colspan="4">ماشین آلات و تجهیزات</td>
        </tr>
        <tr class="bg-gray" style="font-size: 0.8em;">
            <td>عنوان</td><td>تعداد</td><td>تایید</td><td>توضیحات</td>
            <td>عنوان</td><td>تعداد</td><td>تایید</td><td>توضیحات</td>
        </tr>
        
        <?php
        $max_rows = max(count($personnel), count($machinery), 5); // Minimum 5 rows
        for($i=0; $i<$max_rows; $i++):
            $p = $personnel[$i] ?? null;
            $m = $machinery[$i] ?? null;
            
            // Personnel Logic
            $p_total = $p ? ((int)$p['count'] + (int)($p['count_night']??0)) : '';
            $p_cons = $p['consultant_count'] ?? '';
            $p_show_count = ($p_cons !== '' && $p_cons != $p_total) 
                ? "<span class='consultant-edit'>$p_total</span> <span class='consultant-val'>$p_cons</span>" 
                : $p_total;
            
            // Machinery Logic
            $m_active = $m['active_count'] ?? '';
            $m_cons = $m['consultant_active_count'] ?? '';
            $m_show_count = ($m_cons !== '' && $m_cons != $m_active)
                ? "<span class='consultant-edit'>$m_active</span> <span class='consultant-val'>$m_cons</span>"
                : $m_active;
        ?>
        <tr>
            <td class="text-right"><?= $p['role_name'] ?? '' ?></td>
            <td><?= $p_show_count ?></td>
            <td></td> <td class="text-right" style="font-size:0.7em"><?= safeStr($p['consultant_comment']??'') ?></td>

            <td class="text-right"><?= $m['machine_name'] ?? '' ?></td>
            <td><?= $m_show_count ?></td>
            <td></td>
            <td class="text-right" style="font-size:0.7em"><?= safeStr($m['consultant_comment']??'') ?></td>
        </tr>
        <?php endfor; ?>
    </table>

    <?php if($settings['breaks']['pers']) echo '<div class="page-break"></div>'; ?>

    <div style="margin-top: 3px;">
        <table style="margin-bottom:0;">
            <tr class="bg-gray"><td class="text-center">شرح فعالیت های اجرایی</td></tr>
        </table>
        <table>
            <colgroup>
                <col width="3%"> <col width="20%"> <col width="8%"> <col width="8%"> <col width="5%"> <col width="9%"> <col width="15%"> <col width="5%"> <col width="10%"> <col width="17%"> </colgroup>
            <tr class="bg-gray" style="font-size: 0.75em;">
                <td rowspan="2">#</td>
                <td rowspan="2">شرح فعالیت</td>
                <td rowspan="2">جبهه کاری</td>
                <td rowspan="2">موقعیت</td>
                <td rowspan="2">حجم کل</td>
                <td colspan="3">وضعیت</td>
                <td colspan="3">مقادیر (روز/شب/جمع)</td>
                <td rowspan="2">واحد</td>
                <td colspan="3">نفرات (ایمنی/استاد/کارگر)</td>
                <td rowspan="2">توضیحات نظارت</td>
            </tr>
            <tr class="bg-gray" style="font-size: 0.7em;">
                <td>جاری</td><td>توقف</td><td>اتمام</td>
                <td>روز</td><td>شب</td><td>تجمعی</td>
                <td>HSE</td><td>استاد</td><td>کارگر</td>
            </tr>

            <?php foreach($activities as $idx => $act): 
                $act_name = $act['act_name'] ?? $act['master_act_name'] ?? 'فعالیت نامشخص';
                
                // Contractor Values
                $qty_day = safeFloat($act['qty_day']);
                $qty_night = safeFloat($act['qty_night']);
                $qty_cum = safeFloat($act['qty_cumulative']);

                // Consultant Overrides (Strikethrough Logic)
                $cons_day = $act['consultant_qty_day'];
                $cons_night = $act['consultant_qty_night'];
                $cons_cum = $act['consultant_qty_cumulative'];
                
                $show_day = ($cons_day !== null && $cons_day !== '') ? "<span class='consultant-edit'>$qty_day</span> <span class='consultant-val'>$cons_day</span>" : $qty_day;
                $show_night = ($cons_night !== null && $cons_night !== '') ? "<span class='consultant-edit'>$qty_night</span> <span class='consultant-val'>$cons_night</span>" : $qty_night;
                $show_cum = ($cons_cum !== null && $cons_cum !== '') ? "<span class='consultant-val'>$cons_cum</span>" : $qty_cum;
            ?>
            <tr>
                <td><?= $idx + 1 ?></td>
                <td class="text-right" style="font-size: 0.85em;"><?= safeStr($act_name) ?></td>
                <td><?= safeStr($act['work_front']) ?></td>
                <td><?= safeStr($act['location_facade']) ?></td>
                <td><?= safeStr($act['vol_total']) ?></td>
                
                <td><span class="cb <?= $act['status_ongoing']?'checked':'' ?>"></span></td>
                <td><span class="cb <?= $act['status_stopped']?'checked':'' ?>"></span></td>
                <td><span class="cb <?= $act['status_finished']?'checked':'' ?>"></span></td>
                
                <td><?= $show_day ?></td>
                <td><?= $show_night ?></td>
                <td style="background: #f8f9fa;"><?= $show_cum ?></td>
                
                <td><?= safeStr($act['unit']) ?></td>
                
                <td><?= safeFloat($act['pers_safety']) ?></td>
                <td><?= safeFloat($act['pers_master']) ?></td>
                <td><?= safeFloat($act['pers_worker']) ?></td>
                
                <td class="text-right" style="font-size: 0.7em; white-space: pre-wrap;"><?= safeStr($act['consultant_comment']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($activities)): ?>
                <tr><td colspan="14">-</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <?php if($settings['breaks']['act']) echo '<div class="page-break"></div>'; ?>

    <table>
        <tr class="bg-gray">
            <td colspan="2">ماشین آلات / مصالح / کالا /تجهیزات وارده به کارگاه</td>
            <td colspan="2">ماشین آلات / مصالح / کالا /تجهیزات خارج شده از کارگاه</td>
        </tr>
        <tr class="bg-gray" style="font-size:0.8em">
            <td>شرح</td><td>مقدار</td>
            <td>شرح</td><td>مقدار</td>
        </tr>
        <?php
        $max_mat_rows = max(count($mat_in), count($mat_out), 3);
        $mat_in = array_values($mat_in);
        $mat_out = array_values($mat_out);
        
        for($i=0; $i<$max_mat_rows; $i++):
            $mi = $mat_in[$i] ?? null;
            $mo = $mat_out[$i] ?? null;
            
            // In Logic with subtitle
            $mi_desc = $mi ? $mi['material_name'] : '';
            $mi_qty = $mi ? ($mi['quantity'] . ' ' . $mi['unit']) : '';
            if($mi && !empty($mi['consultant_quantity'])) {
                 $mi_qty = "<span class='consultant-edit'>$mi_qty</span> <span class='consultant-val'>{$mi['consultant_quantity']}</span>";
            }
            $mi_note = ($mi && !empty($mi['consultant_comment'])) ? "<span class='subtitle-note'>{$mi['consultant_comment']}</span>" : '';
            
            // Out Logic
            $mo_desc = $mo ? $mo['material_name'] : '';
            $mo_qty = $mo ? ($mo['quantity'] . ' ' . $mo['unit']) : '';
        ?>
        <tr>
            <td width="35%" class="text-right" style="vertical-align:top">
                <?= $mi_desc ?>
                <?= $mi_note ?>
            </td>
            <td width="15%" style="vertical-align:top"><?= $mi_qty ?></td>
            
            <td width="35%" class="text-right" style="vertical-align:top"><?= $mo_desc ?></td>
            <td width="15%" style="vertical-align:top"><?= $mo_qty ?></td>
        </tr>
        <?php endfor; ?>
    </table>

    <table>
        <tr class="bg-gray"><td width="50%">آزمایشات و مجوزها</td><td width="50%">موارد HSE</td></tr>
        <tr>
            <td style="vertical-align: top; padding: 0;">
                <table class="no-border">
                    <?php foreach($misc_test as $t): ?>
                        <tr><td class="text-right no-border">- آزمایش: <?= safeStr($t['description']) ?> (<?= safeStr($t['work_front']) ?>)</td></tr>
                    <?php endforeach; ?>
                    <?php foreach($misc_permit as $p): ?>
                        <tr><td class="text-right no-border">- مجوز: <?= safeStr($p['description']) ?> (<?= safeStr($p['work_front']) ?>)</td></tr>
                    <?php endforeach; ?>
                </table>
            </td>
            <td style="vertical-align: top; padding: 0;">
                <table class="no-border">
                    <?php foreach($misc_hse as $h): ?>
                        <tr><td class="text-right no-border">- <?= safeStr($h['description']) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 3px;">
        <tr>
            <td width="50%" class="text-right" style="vertical-align: top; height: 50px;">
                <div class="bg-gray" style="display:inline-block; padding: 2px 5px; margin-bottom: 2px; font-size:0.8em">موانع و مشکلات (پیمانکار):</div><br>
                <?= safeNl2Br($report['problems_and_obstacles']) ?>
            </td>
            <td width="50%" class="text-right" style="vertical-align: top; height: 50px;">
                <div class="bg-gray" style="display:inline-block; padding: 2px 5px; margin-bottom: 2px; font-size:0.8em">توضیحات نظارت:</div><br>
                <?= safeNl2Br($report['consultant_notes']) ?>
            </td>
        </tr>
    </table>

    <table style="margin-top: 5px;">
        <tr class="bg-gray">
            <td width="33%">پیمانکار</td>
            <td width="33%">مشاور (نظارت مقیم)</td>
            <td width="34%">کارفرما (مدیریت طرح)</td>
        </tr>
        <tr>
            <td>
                <div class="sig-box" onclick="openSigModal('contractor')">
                    <?php if($report['signature_contractor']): ?>
                        <img src="/pardis/<?= $report['signature_contractor'] ?>">
                    <?php else: ?>
                        <div class="sig-placeholder"><?= $can_sign_contractor ? 'امضا' : '' ?></div>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <div class="sig-box" onclick="openSigModal('consultant')">
                    <?php if($report['signature_consultant']): ?>
                        <img src="/pardis/<?= $report['signature_consultant'] ?>">
                    <?php else: ?>
                        <div class="sig-placeholder"><?= $can_sign_consultant ? 'امضا' : '' ?></div>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <div class="sig-box" onclick="openSigModal('employer')">
                    <?php if($report['signature_employer']): ?>
                        <img src="/pardis/<?= $report['signature_employer'] ?>">
                    <?php else: ?>
                        <div class="sig-placeholder"><?= $can_sign_employer ? 'امضا' : '' ?></div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </table>
    
    <div style="font-size: 0.7em; text-align: center; color: #888; margin-top: 5px;">
        صفحه 1 از 1
    </div>

</div>

<div class="modal fade" id="sigModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">ثبت امضا</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <canvas id="sigCanvas" width="400" height="200" style="border: 1px solid #ccc; background: #fff;"></canvas>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="clearSig()">پاک کردن</button>
                <button class="btn btn-success" onclick="saveSig()">ذخیره</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form onsubmit="uploadScan(event)">
                <div class="modal-header"><h5 class="modal-title">آپلود اسکن امضا شده</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="file" name="scan_file" class="form-control" accept="image/*,application/pdf" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">آپلود</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">تنظیمات چاپ</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>فونت:</label>
                    <select id="set_font" class="form-select">
                        <option value="BNazanin">B Nazanin</option>
                        <option value="Vazir">Vazir</option>
                        <option value="Tahoma">Tahoma</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>سایز فونت (pt):</label>
                    <input type="number" id="set_size" class="form-control" value="<?= $settings['size'] ?>">
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="break_pers" <?= $settings['breaks']['pers']?'checked':'' ?>>
                    <label class="form-check-label">شکست صفحه بعد از پرسنل</label>
                </div>
                 <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="break_act" <?= $settings['breaks']['act']?'checked':'' ?>>
                    <label class="form-check-label">شکست صفحه بعد از فعالیت‌ها</label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="saveSettings()">ذخیره</button>
            </div>
        </div>
    </div>
</div>

<script src="/pardis/assets/js/bootstrap.bundle.min.js"></script>
<script>
// --- Signature Logic ---
let sigPad, currentRole;
const roles = {
    'contractor': <?= json_encode($can_sign_contractor) ?>,
    'consultant': <?= json_encode($can_sign_consultant) ?>,
    'employer': <?= json_encode($can_sign_employer) ?>
};

function openSigModal(role) {
    if(!roles[role]) { alert('شما دسترسی امضا ندارید'); return; }
    currentRole = role;
    const modal = new bootstrap.Modal(document.getElementById('sigModal'));
    modal.show();
    setTimeout(() => {
        const canvas = document.getElementById('sigCanvas');
        if(!sigPad) sigPad = new SignaturePad(canvas);
        sigPad.clear();
        const ratio =  Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
    }, 500);
}

function clearSig() { if(sigPad) sigPad.clear(); }

async function saveSig() {
    if(sigPad.isEmpty()) return alert('امضا خالی است');
    const data = sigPad.toDataURL();
    
    const fd = new FormData();
    fd.append('report_id', '<?= $report_id ?>');
    fd.append('role', currentRole);
    fd.append('signature_data', data);
    
    try {
        const res = await fetch('api/save_signature_ps.php', { method:'POST', body:fd });
        const json = await res.json();
        if(json.success) {
            bootstrap.Modal.getInstance(document.getElementById('sigModal')).hide();
            location.reload();
        } else {
            alert(json.message);
        }
    } catch(e) { 
        alert('خطا در ذخیره امضا'); 
        console.error(e);
    }
}

// --- Upload Scan ---
// --- Upload Scan ---
async function uploadScan(e) {
    e.preventDefault();
    
    // 1. UI Feedback
    const form = e.target;
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.innerText;
    
    btn.innerText = 'در حال آپلود...';
    btn.disabled = true;

    // 2. Prepare Data
    const fd = new FormData(form);
    fd.append('report_id', '<?= $report_id ?>');
    
    try {
        // 3. Send to API
        const res = await fetch('api/upload_scan_ps.php', { 
            method: 'POST', 
            body: fd 
        });
        
        // 4. Handle Response
        const rawText = await res.text();
        console.log('Server Response:', rawText); // Check console (F12) to see what came back

        let json;
        try {
            json = JSON.parse(rawText);
        } catch(err) {
            console.error('JSON Parse Error:', rawText);
            throw new Error('پاسخ سرور نامعتبر است.');
        }

        if (res.ok && json.success) {
            // FIX: Use a default string if json.message is missing
            alert(json.message || 'فایل با موفقیت آپلود شد.');
            
            // Close modal
            const modalEl = document.getElementById('uploadModal');
            if (modalEl) {
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();
            }
            
            location.reload();
        } else {
            throw new Error(json.message || 'خطا در عملیات آپلود');
        }

    } catch(e) { 
        console.error(e);
        alert(e.message || 'خطای ناشناخته رخ داده است'); 
    } finally {
        // 5. Reset UI
        btn.innerText = originalText;
        btn.disabled = false;
    }
}

function openUpload() { 
    new bootstrap.Modal(document.getElementById('uploadModal')).show(); 
}

// --- Settings ---
function openSettings() { 
    // Load current settings into form
    document.getElementById('set_font').value = '<?= $settings['font'] ?>';
    document.getElementById('set_size').value = '<?= $settings['size'] ?>';
    document.getElementById('break_pers').checked = <?= $settings['breaks']['pers'] ? 'true' : 'false' ?>;
    document.getElementById('break_act').checked = <?= $settings['breaks']['act'] ? 'true' : 'false' ?>;
    
    new bootstrap.Modal(document.getElementById('settingsModal')).show(); 
}

async function saveSettings() {
    const settings = {
        font: document.getElementById('set_font').value,
        size: parseInt(document.getElementById('set_size').value),
        logos: {
            left: '<?= $settings['logos']['left'] ?>',
            center: '<?= $settings['logos']['center'] ?>',
            right: '<?= $settings['logos']['right'] ?>'
        },
        breaks: {
            pers: document.getElementById('break_pers').checked,
            act: document.getElementById('break_act').checked
        }
    };
    
    // Save to server
    const fd = new FormData();
    fd.append('settings_json', JSON.stringify(settings));
    fd.append('report_id', '<?= $report_id ?>');
    
    try {
        const res = await fetch('api/save_print_settings_ps.php', { method:'POST', body:fd });
        const json = await res.json();
        
        if(json.success) {
            alert('تنظیمات ذخیره شد');
            bootstrap.Modal.getInstance(document.getElementById('settingsModal')).hide();
            location.reload(); // Reload to apply settings
        } else {
            alert(json.message || 'خطا در ذخیره تنظیمات');
        }
    } catch(e) { 
        alert('خطا در ذخیره تنظیمات'); 
        console.error(e);
    }
}


</script>
</body>
</html>