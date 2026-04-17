<?php
// daily_report_print.php - COMPACT VERSION with Enhanced Fonts & Colors
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = getProjectDBConnection('ghom');
$report_id = $_GET['id'] ?? null;
if (!$report_id) die('شناسه گزارش یافت نشد');

// --- Fetch Data ---
$stmt = $pdo->prepare("SELECT * FROM daily_reports WHERE id = ?");
$stmt->execute([$report_id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$report) die('گزارش یافت نشد');

if (!empty($report['report_date'])) {
    list($y, $m, $d) = explode('-', $report['report_date']);
    $j = gregorian_to_jalali((int)$y, (int)$m, (int)$d);
    $report['report_date_jalali'] = $j[0] . '/' . sprintf('%02d', $j[1]) . '/' . sprintf('%02d', $j[2]);
}

$personnel = $pdo->prepare("SELECT * FROM daily_report_personnel WHERE report_id = ? ORDER BY id ASC");
$personnel->execute([$report_id]);
$personnel = $personnel->fetchAll(PDO::FETCH_ASSOC);

$machinery = $pdo->prepare("SELECT * FROM daily_report_machinery WHERE report_id = ? ORDER BY id ASC");
$machinery->execute([$report_id]);
$machinery = $machinery->fetchAll(PDO::FETCH_ASSOC);

$materials = $pdo->prepare("SELECT * FROM daily_report_materials WHERE report_id = ? ORDER BY id ASC");
$materials->execute([$report_id]);
$materials_all = $materials->fetchAll(PDO::FETCH_ASSOC);

$mat_in = []; $mat_out = [];
foreach ($materials_all as $m) {
    if (strtoupper($m['type']) === 'IN') $mat_in[] = $m; else $mat_out[] = $m;
}

$activities = $pdo->prepare("SELECT dra.*, pa.name as activity_name FROM daily_report_activities dra LEFT JOIN project_activities pa ON dra.activity_id = pa.id WHERE report_id = ? ORDER BY dra.id ASC");
$activities->execute([$report_id]);
$activities = $activities->fetchAll(PDO::FETCH_ASSOC);

$misc_items = $pdo->prepare("SELECT * FROM daily_report_misc WHERE report_id = ? ORDER BY id ASC");
$misc_items->execute([$report_id]);
$misc_items_all = $misc_items->fetchAll(PDO::FETCH_ASSOC);

$list_tests = [];
$list_permits = []; 

foreach ($misc_items_all as $item) {
    if ($item['type'] === 'TEST') {
        $list_tests[] = $item['description'];
    } else {
        $prefix = ($item['type'] === 'HSE') ? '(HSE) ' : '';
        $list_permits[] = $prefix . $item['description'];
    }
}

$rows_problems = array_filter(explode("\n", $report['problems_and_obstacles'] ?? ''));
$rows_notes = array_filter(explode("\n", $report['consultant_notes'] ?? ''));

function pad_array($arr, $min_rows) {
    while (count($arr) < $min_rows) $arr[] = '';
    return array_values($arr);
}

$list_tests = pad_array($list_tests, 3);
$list_permits = pad_array($list_permits, 3);
$rows_problems = pad_array($rows_problems, 3);
$rows_notes = pad_array($rows_notes, 3);

// --- Load Settings ---
$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? '';

$contractor_roles = ['cat', 'car', 'coa', 'crs'];
$consultant_roles = ['admin', 'superuser']; 
$employer_roles   = ['user'];

$permissions = [
    'contractor' => in_array($user_role, $contractor_roles),
    'consultant' => in_array($user_role, $consultant_roles),
    'employer'   => in_array($user_role, $employer_roles)
];

$permissionsJson = json_encode($permissions);
$stmt = $pdo->prepare("SELECT settings_json, logo_right, logo_middle, logo_left FROM print_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$db_row = $stmt->fetch(PDO::FETCH_ASSOC);

$default_settings = [
    'global' => ['fontFamily' => 'Vazir', 'fontSize' => 8, 'pageMargin' => 8, 'textColor' => '#000000', 'headerColor' => '#343a40'],
    'logos' => ['right' => '', 'middle' => '', 'left' => ''],
    'breaks' => ['personnel' => false, 'machinery' => false, 'materials' => false, 'activities' => false],
    'footer' => ['height' => 50, 'signatureSpacing' => 3],
    'sectionColors' => [
        'personnel' => '#000000',
        'machinery' => '#000000',
        'materials' => '#000000',
        'activities' => '#000000',
        'misc' => '#000000'
    ],
    'personnel' => ['rowHeight' => 20, 'columns' => [5, 35, 15, 45]],
    'machinery' => ['rowHeight' => 20, 'columns' => [5, 35, 10, 10, 40]],
    'materials_in' => ['rowHeight' => 20, 'columns' => [20, 30, 15, 15, 20]],
    'materials_out' => ['rowHeight' => 20, 'columns' => [20, 30, 15, 15, 20]],
    'activities' => ['rowHeight' => 22, 'columns' => [4, 18, 8, 6, 5, 5, 6, 6, 5, 7, 7, 23]]
];

$json_settings = [];
if ($db_row && !empty($db_row['settings_json'])) {
    $raw = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $db_row['settings_json']);
    $json_settings = json_decode(stripslashes($raw), true) ?? json_decode($raw, true) ?? [];
}
$settings = array_replace_recursive($default_settings, $json_settings);

function cleanPath($path) {
    if (empty($path)) return '';
    $path = preg_replace('#^/ghom/#', '', $path); 
    $path = preg_replace('#^/ghom#', '', $path);
    return ltrim($path, '/');
}

if (isset($db_row['logo_right'])) $settings['logos']['right'] = cleanPath($db_row['logo_right']);
if (isset($db_row['logo_middle'])) $settings['logos']['middle'] = cleanPath($db_row['logo_middle']);
if (isset($db_row['logo_left'])) $settings['logos']['left'] = cleanPath($db_row['logo_left']);

$settingsJson = json_encode($settings);

function get_valid_image_src($path) {
    if (empty($path)) return '';
    if (file_exists(__DIR__ . '/' . $path)) return $path . '?v=' . time();
    return ''; 
}

$logo_right_src = get_valid_image_src($settings['logos']['right']);
$logo_middle_src = get_valid_image_src($settings['logos']['middle']);
$logo_left_src = get_valid_image_src($settings['logos']['left']);

if (empty($logo_right_src) && empty($settings['logos']['right'])) {
    $clean_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $report['contractor_fa_name']);
    $fallback = 'uploads/contractor_logos/' . $clean_name . '.png';
    if (file_exists(__DIR__ . '/' . $fallback)) $logo_right_src = $fallback;
    else $logo_right_src = 'assets/images/logo-right.png';
}

$sig_contractor = get_valid_image_src(cleanPath($report['signature_contractor']));
$sig_consultant = get_valid_image_src(cleanPath($report['signature_consultant']));
$sig_employer   = get_valid_image_src(cleanPath($report['signature_employer']));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>گزارش روزانه</title>
    <link href="assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="assets/css/all.min.css" rel="stylesheet">
    <script src="assets/js/signature_pad.umd.min.js"></script>
    <style>
        @font-face { font-family: "Samim"; src: url("assets/fonts/Samim-FD.woff2") format("woff2"); }
        @font-face { font-family: "Vazir"; src: url("https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/Vazir-Regular.woff2") format("woff2"); }
        @font-face { font-family: "IRANSans"; src: url("https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/Vazir-Regular.woff2") format("woff2"); }
        @font-face { font-family: "Yekan"; src: url("https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/Vazir-Regular.woff2") format("woff2"); }
        
        body { background-color: #f0f2f5; margin: 0; padding: 15px; }
        
        .data-rejected { text-decoration: line-through !important; color: #dc3545 !important; opacity: 0.7; }
        
        .action-buttons { position: fixed; bottom: 20px; left: 20px; z-index: 1050; display: flex; flex-direction: column; gap: 10px; }
        .fab-button { width: 56px; height: 56px; border-radius: 50%; font-size: 1.4rem; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        
        .main-print-table { width: 100%; border-collapse: collapse; background: white; }
        .main-print-table thead { display: table-header-group; }
        
        .header-title-area { text-align: center; margin-bottom: 3px; padding-bottom: 3px; border-bottom: 2px solid #333; }
        .header-logos-area { display: flex; justify-content: space-between; align-items: center; margin-top: 5px; width: 100%; }
        .logo-slot { flex: 1; display: flex; align-items: center; }
        .logo-slot.right { justify-content: flex-start; }
        .logo-slot.middle { justify-content: center; }
        .logo-slot.left { justify-content: flex-end; }
        .header-logos-area img { max-height: 60px; object-fit: contain; } 
        
        .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 5px; }
        .data-table th, .data-table td { 
            border: 1px solid #ccc; 
            text-align: center; 
            padding: 2px 3px; 
            font-size: 0.85em; 
            line-height: 1.3;
            overflow: hidden; 
            text-overflow: ellipsis; 
            white-space: nowrap; 
            position: relative; 
        }
        .data-table th { background-color: #e9ecef; font-weight: bold; }
        .text-start { text-align: right !important; }
        .text-wrap { white-space: normal !important; }
        
        .report-section h6 { 
            font-size: 0.9em; 
            margin: 5px 0 3px 0; 
            padding: 3px 5px;
        }
        
        .signature-box { width: 30%; text-align: center; cursor: pointer; border: 1px dashed transparent; min-height: 60px; }
        .signature-box:hover { border-color: #0d6efd; background: #f0f8ff; }
        .signature-box img { max-height: 50px; max-width: 100%; display: block; margin: 3px auto; }
        .signature-box .fw-bold { font-size: 0.85em; }
        
        .break-row-btn { 
            position: absolute; 
            right: 2px;
            top: 50%; 
            transform: translateY(-50%); 
            cursor: pointer; 
            color: #ccc; 
            font-size: 11px; 
            opacity: 0; 
            transition: opacity 0.2s; 
            z-index: 10;
        }
        tr:hover .break-row-btn { opacity: 1; }
        .break-active { color: red !important; opacity: 1 !important; }
        
        @media print { 
            @page { 
                size: A4; margin: 6mm; 
                @bottom-center { content: "صفحه " counter(page) " از " counter(pages); font-family: "Vazir"; font-size: 9pt; }
            }
            body { padding: 0; margin: 0; background: white; }
            
            .no-print, .break-row-btn { display: none !important; }
            .data-rejected { -webkit-text-decoration: line-through !important; text-decoration: line-through !important; }
            
            .section-page-break { page-break-before: always !important; margin-top: 15px; display: block; }
            
            .page-break-row { display: table-row; height: 0; border: none; }
            .page-break-row td { border: none !important; padding: 0 !important; height: 0; }
            .page-break-div { page-break-after: always !important; break-after: page !important; height: 1px; display: block; }
        }
        
        .signature-box.can-sign { cursor: pointer; }
        .signature-box.can-sign:hover { border-color: #0d6efd; background: #f0f8ff; }
        .signature-box.cannot-sign { cursor: not-allowed; opacity: 0.7; }
        .signature-box.cannot-sign:hover::after {
            content: "\f023";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            font-size: 20px;
            color: #999;
        }
        
        .info-row { 
            display: flex; 
            justify-content: space-between; 
            border-bottom: 1px solid #ddd; 
            padding: 5px 8px; 
            margin-bottom: 8px; 
            font-size: 0.85em;
        }
    </style>
    <style id="dynamic-styles"></style>
</head>
<body>
    <div class="action-buttons no-print">
        <button class="btn btn-primary fab-button" onclick="window.print()" title="چاپ"><i class="fa-solid fa-print"></i></button>
        <button class="btn btn-danger fab-button" data-bs-toggle="modal" data-bs-target="#uploadModal" title="آپلود امضا"><i class="fa-solid fa-upload"></i></button>
        <button class="btn btn-info fab-button" data-bs-toggle="modal" data-bs-target="#settingsModal" title="تنظیمات"><i class="fa-solid fa-gear"></i></button>
    </div>

    <div class="container-fluid mt-2 mb-2 no-print">
        <div class="alert alert-info d-flex align-items-center shadow-sm py-2">
            <i class="fa-solid fa-scissors me-3 fs-5"></i>
            <div>
                <strong>راهنما:</strong>
                <span class="small">برای شکستن صفحه روی سطر کلیک کنید (قیچی قرمز می‌شود) • برای حذف برش مجدداً کلیک کنید</span>
            </div>
        </div>
    </div>

    <table class="main-print-table">
        <thead>
            <tr>
                <td>
                    <header class="header-title-area">
                        <h2 style="font-size:1.3em; font-weight:bold; margin:0;">گزارش روزانه عملیات اجرایی</h2>
                        <p style="margin:3px 0 0 0; font-size:0.85em;">شماره گزارش: <?php echo $report_id; ?></p>
                        <div class="header-logos-area">
                            <div class="logo-slot right"><?php if($logo_right_src): ?><img src="<?php echo $logo_right_src; ?>"><?php endif; ?></div>
                            <div class="logo-slot middle"><?php if($logo_middle_src): ?><img src="<?php echo $logo_middle_src; ?>"><?php endif; ?></div>
                            <div class="logo-slot left"><?php if($logo_left_src): ?><img src="<?php echo $logo_left_src; ?>"><?php endif; ?></div>
                        </div>
                    </header>
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <main id="printContent" class="print-content-wrapper mt-1">
                        <div class="info-row">
                            <span><strong>تاریخ:</strong> <?php echo $report['report_date_jalali']; ?></span>
                            <span><strong>پیمانکار:</strong> <?php echo $report['contractor_fa_name']; ?></span>
                            <span><strong>بلوک:</strong> <?php echo $report['block_name']; ?></span>
                            <span><strong>هوا:</strong> <?php echo implode(', ', json_decode($report['weather_list'] ?? '[]', true)); ?> <span dir="ltr">(<?php echo $report['temp_min']; ?>° - <?php echo $report['temp_max']; ?>°)</span></span>
                        </div>

                        <div id="sec-personnel" class="report-section">
                            <h6 class="p-1 bg-light border fw-bold" id="personnel-header">۱. نیروی انسانی</h6>
                            <table class="data-table" id="personnel-table">
    <!-- Adjusted widths to fit comment -->
    <colgroup>
        <col style="width: 5%;">
        <col style="width: 25%;">
        <col style="width: 15%;">
        <col style="width: 15%;">
        <col style="width: 40%;">
    </colgroup>
    <thead>
        <tr>
            <th>#</th>
            <th>سمت</th>
            <th>تعداد (پیمانکار)</th>
            <th class="text-success">تایید (مشاور)</th>
            <th>توضیحات مشاور</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($personnel as $i => $p): 
            $cls = !empty($p['consultant_comment']) ? 'data-rejected' : ''; ?>
            <tr onclick="toggleRowBreak(this)">
                <td><?= $i+1 ?></td>
                <td class="text-start <?= $cls ?>"><?= $p['role_name'] ?></td>
                <td class="<?= $cls ?>"><?= $p['count'] ?></td>
                
                <!-- Consultant Number -->
                <td class="fw-bold text-success"><?= $p['consultant_count'] ?? '-' ?></td>
                
                <!-- Consultant Comment -->
                <td class="text-start text-wrap small"><?= $p['consultant_comment'] ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

                        <div id="sec-machinery" class="report-section">
                            <h6 class="p-1 bg-light border fw-bold" id="machinery-header">۲. ماشین آلات</h6>
                            <table class="data-table" id="machinery-table">
                                <colgroup>
                                    <col style="width: 5%;">
                                    <col style="width: 25%;">
                                    <col style="width: 10%;">
                                    <col style="width: 10%;">
                                    <col style="width: 10%;">
                                    <col style="width: 40%;">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>نام دستگاه</th>
                                        <th>کل</th>
                                        <th>فعال</th>
                                        <th class="text-success">تایید فعال</th>
                                        <th>توضیحات مشاور</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($machinery as $i => $m): 
                                        $cls = !empty($m['consultant_comment']) ? 'data-rejected' : ''; ?>
                                        <tr onclick="toggleRowBreak(this)">
                                            <td><?= $i+1 ?></td>
                                            <td class="text-start <?= $cls ?>"><?= $m['machine_name'] ?></td>
                                            <td class="<?= $cls ?>"><?= $m['total_count'] ?></td>
                                            <td class="<?= $cls ?>"><?= $m['active_count'] ?></td>
                                            
                                            <!-- Consultant Number -->
                                            <td class="fw-bold text-success"><?= $m['consultant_active_count'] ?? '-' ?></td>
                                            
                                            <!-- Consultant Comment -->
                                            <td class="text-start text-wrap small"><?= $m['consultant_comment'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div id="sec-materials" class="report-section">
                            <h6 class="p-1 bg-light border fw-bold" id="materials-header">۳. مصالح</h6>
                            <div class="row g-1">
                                <div class="col-6">
                                    <div class="text-center small fw-bold text-success" style="font-size:0.8em;">ورودی (IN)</div>
                                    <table class="data-table" id="materials-in-table">
                                    <colgroup>
                                        <col style="width: 15%;">
                                        <col style="width: 25%;">
                                        <col style="width: 15%;">
                                        <col style="width: 15%;">
                                        <col style="width: 30%;">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>دسته</th>
                                            <th>شرح / تاریخ</th>
                                            <th>مقدار (پیمانکار)</th>
                                            <th class="text-success">تایید (مشاور)</th>
                                            <th>توضیحات مشاور</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($mat_in)) echo "<tr><td colspan='5'>-</td></tr>"; ?>
                                        <?php foreach ($mat_in as $m): 
                                            $cls = !empty($m['consultant_comment']) ? 'data-rejected' : ''; 
                                            $desc = $m['material_name'];
                                            if(!empty($m['file_path'])) $desc .= ' (📎)';
                                        ?>
                                            <tr onclick="toggleRowBreak(this)">
                                                <td class="<?= $cls ?>" style="font-size:0.8em">
                                                    <i class="fa-solid fa-scissors break-row-btn"></i>
                                                    <?= $m['category'] ?? '-' ?>
                                                </td>
                                                <td class="text-start <?= $cls ?>">
                                                    <?= $desc ?>
                                                    <?php if($m['date']): ?><br><span class="text-muted small"><?= $m['date'] ?></span><?php endif; ?>
                                                </td>
                                                <td class="<?= $cls ?>"><?= $m['quantity'] . ' ' . $m['unit'] ?></td>
                                                
                                                <!-- Consultant Number -->
                                                <td class="fw-bold text-success"><?= $m['consultant_quantity'] ?? '-' ?></td>
                                                
                                                <!-- Consultant Comment -->
                                                <td class="text-start text-wrap small"><?= $m['consultant_comment'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                                <div class="col-6">
                                    <div class="text-center small fw-bold text-danger" style="font-size:0.8em;">خروجی (OUT)</div>
                                    <table class="data-table" id="materials-out-table">
                                        <colgroup><col><col><col><col><col></colgroup>
                                        <thead><tr><th>دسته</th><th>شرح</th><th>تعداد</th><th>واحد</th><th>یادداشت</th></tr></thead>
                                        <tbody>
                                            <?php if(empty($mat_out)) echo "<tr><td colspan='5'>-</td></tr>"; ?>
                                            <?php foreach ($mat_out as $m): 
                                                $cls = !empty($m['consultant_comment']) ? 'data-rejected' : ''; ?>
                                                <tr onclick="toggleRowBreak(this)">
                                                    <td class="text-start <?= $cls ?>" style="font-size:0.75em">
                                                        <i class="fa-solid fa-scissors break-row-btn"></i>
                                                        <?= $m['category'] ?? '-' ?>
                                                    </td>
                                                    <td class="text-start <?= $cls ?>"><?= $m['material_name'] ?></td>
                                                    <td class="<?= $cls ?>"><?= $m['quantity'] ?></td>
                                                    <td class="<?= $cls ?>"><?= $m['unit'] ?></td>
                                                    <td class="text-start text-wrap"><?= $m['consultant_comment'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div id="sec-activities" class="report-section">
                            <h6 class="p-1 bg-light border fw-bold" id="activities-header">۴. فعالیت های اجرایی</h6>
                            <table class="data-table" id="activities-table">
                                <colgroup>
                                    <!-- Optimized widths for A4 -->
                                    <col style="width:3%"> <!-- # -->
                                    <col style="width:15%"> <!-- Name -->
                                    <col style="width:5%"> <!-- Loc -->
                                    <col style="width:5%"> <!-- Zone -->
                                    <col style="width:4%"> <!-- Floor -->
                                    <col style="width:4%"> <!-- Unit -->
                                    <col style="width:5%"> <!-- Cont Qty -->
                                    <col style="width:5%"> <!-- Cont Met -->
                                    <col style="width:4%"> <!-- Pers -->
                                    <col style="width:5%"> <!-- Cons Qty -->
                                    <col style="width:5%"> <!-- Cons Met -->
                                    <col style="width:5%"> <!-- Cum Inst -->
                                    <col style="width:5%"> <!-- Cum Rej -->
                                    <col style="width:20%"> <!-- Note -->
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th rowspan="2">#</th>
                                        <th rowspan="2">شرح</th>
                                        <th rowspan="2">محور</th>
                                        <th rowspan="2">زون</th>
                                        <th rowspan="2">طبقه</th>
                                        <th rowspan="2">واحد</th>
                                        <th colspan="3">پیمانکار (روزانه)</th>
                                        <th colspan="2" class="text-success">تایید نظارت</th>
                                        <th colspan="2">تجمعی</th>
                                        <th rowspan="2">توضیحات مشاور</th>
                                    </tr>
                                    <tr>
                                        <th>تعداد</th><th>متر</th><th>نفر</th>
                                        <th class="text-success">تعداد</th><th class="text-success">متر</th>
                                        <th>نصب</th><th>ریجکت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $i => $a): 
                                        $cls = !empty($a['consultant_comment']) ? 'data-rejected' : ''; ?>
                                        <tr onclick="toggleRowBreak(this)">
                                            <td><i class="fa-solid fa-scissors break-row-btn"></i> <?= $i+1 ?></td>
                                            <td class="text-start text-wrap <?= $cls ?>"><?= $a['activity_name'] ?></td>
                                            <td class="<?= $cls ?>"><?= $a['location_facade'] ?></td>
                                            <td class="<?= $cls ?>"><?= $a['zone_name'] ?></td>
                                            <td class="<?= $cls ?>"><?= $a['floor'] ?></td>
                                            <td class="<?= $cls ?>"><?= $a['unit'] ?></td>
                                            
                                            <!-- Contractor -->
                                            <td class="<?= $cls ?>"><?= $a['contractor_quantity'] ?></td>
                                            <td class="<?= $cls ?>"><?= $a['contractor_meterage'] ?></td>
                                            <td class="<?= $cls ?>"><?= $a['personnel_count'] ?></td>
                                            
                                            <!-- Consultant Numbers -->
                                            <td class="fw-bold text-success"><?= $a['consultant_quantity'] ?? '-' ?></td>
                                            <td class="fw-bold text-success"><?= $a['consultant_meterage'] ?? '-' ?></td>
                                            
                                            <!-- Cumulative -->
                                            <td><?= $a['cum_installed_count'] ?></td>
                                            <td class="text-danger"><?= $a['cum_rejected_count'] ?></td>
                                            
                                            <!-- Consultant Comment -->
                                            <td class="text-start text-wrap small"><?= $a['consultant_comment'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row g-0 mt-2" id="misc-section">
                            <div class="col-6 ps-1">
                                <table class="data-table" style="margin:0;">
                                    <colgroup><col style="width:10%"><col style="width:90%"></colgroup>
                                    <thead>
                                        <tr><th colspan="2" style="background-color:#e0e0e0; font-size:0.85em;">آزمایشات</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($list_tests as $i => $text): ?>
                                            <tr style="height:18px;">
                                                <td class="text-center"><?= $i + 1 ?></td>
                                                <td class="text-start px-2"><?= htmlspecialchars($text) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="col-6 pe-1">
                                <table class="data-table" style="margin:0;">
                                    <colgroup><col style="width:10%"><col style="width:90%"></colgroup>
                                    <thead>
                                        <tr><th colspan="2" style="background-color:#e0e0e0; font-size:0.85em;">مجوزات</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($list_permits as $i => $text): ?>
                                            <tr style="height:18px;">
                                                <td class="text-center"><?= $i + 1 ?></td>
                                                <td class="text-start px-2"><?= htmlspecialchars($text) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mt-2">
                            <table class="data-table" style="margin:0;">
                                <colgroup><col style="width:5%"><col style="width:95%"></colgroup>
                                <thead>
                                    <tr><th colspan="2" style="background-color:#e0e0e0; font-size:0.85em;">موانع و مشکلات</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach($rows_problems as $i => $text): ?>
                                        <tr style="height:20px;">
                                            <td class="text-center"><?= $i + 1 ?></td>
                                            <td class="text-start px-2"><?= htmlspecialchars($text) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-2 mb-2">
                            <table class="data-table" style="margin:0;">
                                <colgroup><col style="width:5%"><col style="width:95%"></colgroup>
                                <thead>
                                    <tr><th colspan="2" style="background-color:#e0e0e0; font-size:0.85em;">توضیحات نظارت</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach($rows_notes as $i => $text): ?>
                                        <tr style="height:20px;">
                                            <td class="text-center"><?= $i + 1 ?></td>
                                            <td class="text-start px-2"><?= htmlspecialchars($text) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </main>
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td>
                    <div class="d-flex justify-content-around mt-2 pt-2 border-top">
                        <div class="signature-box <?php echo $permissions['contractor'] ? 'can-sign' : 'cannot-sign'; ?>" 
                             onclick="openSignatureModal('contractor')">
                            <div class="fw-bold">امضای پیمانکار</div>
                            <?php if($sig_contractor): ?>
                                <img src="<?php echo $sig_contractor; ?>" alt="Contractor Signature">
                            <?php endif; ?>
                        </div>
                        <div class="signature-box <?php echo $permissions['consultant'] ? 'can-sign' : 'cannot-sign'; ?>" 
                             onclick="openSignatureModal('consultant')">
                            <div class="fw-bold">امضای مشاور</div>
                            <?php if($sig_consultant): ?>
                                <img src="<?php echo $sig_consultant; ?>" alt="Consultant Signature">
                            <?php endif; ?>
                        </div>
                        <div class="signature-box <?php echo $permissions['employer'] ? 'can-sign' : 'cannot-sign'; ?>" 
                             onclick="openSignatureModal('employer')">
                            <div class="fw-bold">امضای کارفرما</div>
                            <?php if($sig_employer): ?>
                                <img src="<?php echo $sig_employer; ?>" alt="Employer Signature">
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">تنظیمات چاپ</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <ul class="nav nav-tabs" id="myTab">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#home">عمومی</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#colors">رنگ بخش‌ها</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#logos">لوگوها</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#breaks">شکست صفحات</a></li>
                    </ul>
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="home">
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label">فونت اصلی</label>
                                    <select class="form-select" id="inputFont">
                                        <option value="Vazir">وزیر (Vazir)</option>
                                        <option value="Samim">صمیم (Samim)</option>
                                        <option value="IRANSans">ایران سنس (IRANSans)</option>
                                        <option value="Yekan">یکان (Yekan)</option>
                                        <option value="Tahoma">تاهوما (Tahoma)</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">سایز فونت (pt)</label>
                                    <input type="number" class="form-control" id="inputSize" min="6" max="14" step="0.5">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">رنگ پس‌زمینه هدر</label>
                                    <input type="color" class="form-control" id="inputHeaderColor">
                                </div>
                                <div class="col-12 text-end">
                                    <button class="btn btn-primary" onclick="saveGeneralSettings()">ذخیره تنظیمات</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="colors">
                            <div class="alert alert-info small">
                                <i class="fa-solid fa-info-circle me-2"></i>
                                می‌توانید رنگ متن هر بخش را جداگانه تنظیم کنید
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label">رنگ بخش نیروی انسانی</label>
                                    <input type="color" class="form-control" id="colorPersonnel">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">رنگ بخش ماشین‌آلات</label>
                                    <input type="color" class="form-control" id="colorMachinery">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">رنگ بخش مصالح</label>
                                    <input type="color" class="form-control" id="colorMaterials">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">رنگ بخش فعالیت‌ها</label>
                                    <input type="color" class="form-control" id="colorActivities">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">رنگ سایر بخش‌ها</label>
                                    <input type="color" class="form-control" id="colorMisc">
                                </div>
                                <div class="col-12 text-end">
                                    <button class="btn btn-outline-secondary me-2" onclick="resetColors()">بازگشت به پیش‌فرض</button>
                                    <button class="btn btn-primary" onclick="saveGeneralSettings()">ذخیره رنگ‌ها</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="logos">
                            <form id="logoForm">
                                <div class="row g-3">
                                    <div class="col-4 text-center">
                                        <label class="form-label fw-bold">لوگو راست</label>
                                        <input type="file" name="logo_right" class="form-control form-control-sm mb-2" accept="image/*">
                                        <button type="button" class="btn btn-danger btn-sm w-100" onclick="deleteLogo('logo_right')">
                                            <i class="fa-solid fa-trash"></i> حذف
                                        </button>
                                    </div>
                                    <div class="col-4 text-center">
                                        <label class="form-label fw-bold">لوگو وسط</label>
                                        <input type="file" name="logo_middle" class="form-control form-control-sm mb-2" accept="image/*">
                                        <button type="button" class="btn btn-danger btn-sm w-100" onclick="deleteLogo('logo_middle')">
                                            <i class="fa-solid fa-trash"></i> حذف
                                        </button>
                                    </div>
                                    <div class="col-4 text-center">
                                        <label class="form-label fw-bold">لوگو چپ</label>
                                        <input type="file" name="logo_left" class="form-control form-control-sm mb-2" accept="image/*">
                                        <button type="button" class="btn btn-danger btn-sm w-100" onclick="deleteLogo('logo_left')">
                                            <i class="fa-solid fa-trash"></i> حذف
                                        </button>
                                    </div>
                                    <div class="col-12 text-center mt-3">
                                        <button type="button" class="btn btn-success" onclick="uploadLogos()">
                                            <i class="fa-solid fa-upload"></i> آپلود لوگوها
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <div class="tab-pane fade" id="breaks">
                            <div class="alert alert-warning small">
                                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                با فعال کردن این گزینه‌ها، هر بخش از صفحه جدید شروع می‌شود
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="break_personnel">
                                <label class="form-check-label">شکستن قبل از بخش نیروی انسانی</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="break_machinery">
                                <label class="form-check-label">شکستن قبل از بخش ماشین‌آلات</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="break_materials">
                                <label class="form-check-label">شکستن قبل از بخش مصالح</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="break_activities">
                                <label class="form-check-label">شکستن قبل از بخش فعالیت‌ها</label>
                            </div>
                            <div class="text-end mt-4">
                                <button class="btn btn-primary" onclick="saveGeneralSettings()">ذخیره تنظیمات</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="uploadForm" onsubmit="uploadSignedFile(event)">
                    <div class="modal-header">
                        <h5 class="modal-title">آپلود فایل PDF امضا شده</h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">
                        <input type="file" class="form-control" name="signed_file" accept=".pdf" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">ارسال فایل</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Signature Modal -->
    <div class="modal fade" id="signatureModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">امضای دیجیتال <span id="sig-role-title"></span></h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="p-2 border rounded bg-white">
                        <canvas id="signaturePad" width="500" height="200"></canvas>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" onclick="clearSignature()">
                        <i class="fa-solid fa-eraser"></i> پاک کردن
                    </button>
                    <button class="btn btn-primary" onclick="saveSignature()">
                        <i class="fa-solid fa-check"></i> ذخیره امضا
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        let settings = <?php echo $settingsJson; ?>;
        let currentSigningRole = '';
        let signaturePad;
        
        function applyStyles() {
            // Ensure sectionColors exists
            if (!settings.sectionColors) {
                settings.sectionColors = {
                    personnel: '#000000',
                    machinery: '#000000',
                    materials: '#000000',
                    activities: '#000000',
                    misc: '#000000'
                };
            }
            
            let css = `
                body { font-family: "${settings.global.fontFamily}", Tahoma !important; }
                .print-content-wrapper { font-size: ${settings.global.fontSize}pt; color: ${settings.global.textColor}; }
                .data-table th, .bg-light { background-color: ${settings.global.headerColor} !important; color: white !important; }
                
                #sec-personnel, #personnel-header, #personnel-table { color: ${settings.sectionColors.personnel} !important; }
                #sec-machinery, #machinery-header, #machinery-table { color: ${settings.sectionColors.machinery} !important; }
                #sec-materials, #materials-header, #materials-in-table, #materials-out-table { color: ${settings.sectionColors.materials} !important; }
                #sec-activities, #activities-header, #activities-table { color: ${settings.sectionColors.activities} !important; }
                #misc-section { color: ${settings.sectionColors.misc} !important; }
                
                ${Object.keys(settings).filter(k => settings[k].columns).map(k => {
                    let id = '#' + k.replace('_','-') + '-table';
                    return settings[k].columns.map((w,i) => `${id} colgroup col:nth-child(${i+1}) { width: ${w}% }`).join('\n');
                }).join('\n')}
            `;
            document.getElementById('dynamic-styles').innerHTML = css;
            
            // Load general settings
            document.getElementById('inputFont').value = settings.global.fontFamily;
            document.getElementById('inputSize').value = settings.global.fontSize;
            document.getElementById('inputHeaderColor').value = settings.global.headerColor;
            
            // Load color settings
            document.getElementById('colorPersonnel').value = settings.sectionColors.personnel;
            document.getElementById('colorMachinery').value = settings.sectionColors.machinery;
            document.getElementById('colorMaterials').value = settings.sectionColors.materials;
            document.getElementById('colorActivities').value = settings.sectionColors.activities;
            document.getElementById('colorMisc').value = settings.sectionColors.misc;
            
            // Load break settings
            if (!settings.breaks) settings.breaks = {};
            document.getElementById('break_personnel').checked = settings.breaks.personnel || false;
            document.getElementById('break_machinery').checked = settings.breaks.machinery || false;
            document.getElementById('break_materials').checked = settings.breaks.materials || false;
            document.getElementById('break_activities').checked = settings.breaks.activities || false;
            
            toggleSectionBreak('sec-personnel', settings.breaks.personnel);
            toggleSectionBreak('sec-machinery', settings.breaks.machinery);
            toggleSectionBreak('sec-materials', settings.breaks.materials);
            toggleSectionBreak('sec-activities', settings.breaks.activities);
        }

        function toggleSectionBreak(id, active) {
            let el = document.getElementById(id);
            if(el) active ? el.classList.add('section-page-break') : el.classList.remove('section-page-break');
        }

        window.toggleRowBreak = function(row) {
            let icon = row.querySelector('.break-row-btn');
            let nextSibling = row.nextElementSibling;

            if (nextSibling && nextSibling.classList.contains('page-break-row')) {
                nextSibling.remove();
                if(icon) icon.classList.remove('break-active');
            } else {
                let breakRow = document.createElement('tr');
                breakRow.className = 'page-break-row';
                breakRow.innerHTML = '<td colspan="100"><div class="page-break-div"></div></td>';
                row.parentNode.insertBefore(breakRow, row.nextSibling);
                if(icon) icon.classList.add('break-active');
            }
        };

        function resetColors() {
            document.getElementById('colorPersonnel').value = '#000000';
            document.getElementById('colorMachinery').value = '#000000';
            document.getElementById('colorMaterials').value = '#000000';
            document.getElementById('colorActivities').value = '#000000';
            document.getElementById('colorMisc').value = '#000000';
        }

        async function saveGeneralSettings() {
            settings.global.fontFamily = document.getElementById('inputFont').value;
            settings.global.fontSize = document.getElementById('inputSize').value;
            settings.global.headerColor = document.getElementById('inputHeaderColor').value;
            
            settings.sectionColors = {
                personnel: document.getElementById('colorPersonnel').value,
                machinery: document.getElementById('colorMachinery').value,
                materials: document.getElementById('colorMaterials').value,
                activities: document.getElementById('colorActivities').value,
                misc: document.getElementById('colorMisc').value
            };
            
            settings.breaks = {
                personnel: document.getElementById('break_personnel').checked,
                machinery: document.getElementById('break_machinery').checked,
                materials: document.getElementById('break_materials').checked,
                activities: document.getElementById('break_activities').checked
            };

            try {
                let res = await fetch('api/save_print_settings.php', {
                    method: 'POST', 
                    headers: {'Content-Type': 'application/json'}, 
                    body: JSON.stringify(settings)
                });
                let data = await res.json();
                if(data.success) { 
                    alert('✅ تنظیمات با موفقیت ذخیره شد'); 
                    applyStyles(); 
                    bootstrap.Modal.getInstance(document.getElementById('settingsModal')).hide(); 
                } else {
                    alert('❌ خطا: ' + data.message);
                }
            } catch(e) { 
                alert('❌ خطا در ارتباط با سرور'); 
                console.error(e);
            }
        }

        async function uploadLogos() {
            let form = document.getElementById('logoForm');
            try {
                let res = await fetch('api/save_logo_settings.php', { 
                    method: 'POST', 
                    body: new FormData(form) 
                });
                let data = await res.json();
                if(data.success) {
                    alert('✅ لوگوها با موفقیت آپلود شدند');
                    location.reload();
                } else {
                    alert('❌ خطا: ' + data.message);
                }
            } catch(e) { 
                alert('❌ خطا در آپلود'); 
                console.error(e);
            }
        }

        async function deleteLogo(type) {
            if(!confirm('آیا از حذف این لوگو اطمینان دارید؟')) return;
            let fd = new FormData(); 
            fd.append('logo_type', type);
            try {
                let res = await fetch('api/delete_logo.php', { method: 'POST', body: fd });
                let data = await res.json();
                if(data.success) {
                    alert('✅ لوگو حذف شد');
                    location.reload();
                } else {
                    alert('❌ خطا: ' + data.message);
                }
            } catch(e) { 
                alert('❌ خطا در حذف'); 
                console.error(e);
            }
        }

        async function uploadSignedFile(event) {
            event.preventDefault();
            try {
                let res = await fetch('api/upload_signed_scan.php', { 
                    method: 'POST', 
                    body: new FormData(event.target) 
                });
                let data = await res.json();
                if(data.success) { 
                    alert('✅ فایل با موفقیت آپلود شد'); 
                    location.reload(); 
                } else {
                    alert('❌ خطا: ' + data.message);
                }
            } catch(e) { 
                alert('❌ خطا در آپلود'); 
                console.error(e);
            }
        }

        let userPerms = <?php echo $permissionsJson; ?>;

        function openSignatureModal(role) {
            if (!userPerms[role]) {
                alert('⛔ شما دسترسی لازم برای امضای این بخش را ندارید.');
                return;
            }

            currentSigningRole = role;
            const titles = { 
                'contractor': 'پیمانکار', 
                'consultant': 'مشاور', 
                'employer': 'کارفرما' 
            };
            document.getElementById('sig-role-title').innerText = `(${titles[role]})`;
            
            const modal = new bootstrap.Modal(document.getElementById('signatureModal'));
            modal.show();
        }

        document.addEventListener('DOMContentLoaded', () => {
            applyStyles();
            document.getElementById('signatureModal').addEventListener('shown.bs.modal', () => {
                if(signaturePad) signaturePad.clear(); 
                else signaturePad = new SignaturePad(document.getElementById('signaturePad'), { 
                    backgroundColor: 'rgb(255, 255, 255)' 
                });
            });
        });
        
        window.clearSignature = () => signaturePad && signaturePad.clear();
        
        window.saveSignature = async () => {
            if (!signaturePad || signaturePad.isEmpty()) {
                alert('⚠️ لطفاً ابتدا امضا کنید');
                return;
            }
            
            let fd = new FormData();
            fd.append('report_id', <?php echo $report_id; ?>);
            fd.append('role', currentSigningRole);
            fd.append('signature_data', signaturePad.toDataURL('image/png'));
            
            try {
                let res = await fetch('api/save_signature.php', { method: 'POST', body: fd });
                let data = await res.json();
                if(data.success) {
                    alert('✅ امضا با موفقیت ذخیره شد');
                    location.reload();
                } else {
                    alert('❌ خطا: ' + data.message);
                }
            } catch(e) { 
                alert('❌ خطا در ذخیره امضا'); 
                console.error(e);
            }
        };
    </script>
</body>
</html>