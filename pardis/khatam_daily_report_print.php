<?php
// khatam_daily_report_print.php - PIXEL PERFECT REPLICA
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

$pdo = getProjectDBConnection('ghom');
$report_id = $_GET['id'] ?? null;

if (!$report_id) die('Report ID missing');

// --- 1. FETCH DATA ---
$stmt = $pdo->prepare("SELECT * FROM daily_reports WHERE id = ?");
$stmt->execute([$report_id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

// Dates
$jDate = '';
if (!empty($report['report_date'])) {
    $p = explode('-', $report['report_date']);
    $j = gregorian_to_jalali((int)$p[0], (int)$p[1], (int)$p[2]);
    $jDate = $j[0] . '/' . sprintf('%02d', $j[1]) . '/' . sprintf('%02d', $j[2]);
    $dayName = jdate('l', strtotime($report['report_date'])); // Day of week
}

// Fetch Children
$personnel = $pdo->prepare("SELECT * FROM daily_report_personnel WHERE report_id = ?");
$personnel->execute([$report_id]);
$pers_data = $personnel->fetchAll(PDO::FETCH_ASSOC);

$machinery = $pdo->prepare("SELECT * FROM daily_report_machinery WHERE report_id = ?");
$machinery->execute([$report_id]);
$mach_data = $machinery->fetchAll(PDO::FETCH_ASSOC);

$materials = $pdo->prepare("SELECT * FROM daily_report_materials WHERE report_id = ?");
$materials->execute([$report_id]);
$mat_all = $materials->fetchAll(PDO::FETCH_ASSOC);

$activities = $pdo->prepare("SELECT dra.*, pa.name as act_name FROM daily_report_activities dra LEFT JOIN project_activities pa ON dra.activity_id = pa.id WHERE report_id = ?");
$activities->execute([$report_id]);
$acts_data = $activities->fetchAll(PDO::FETCH_ASSOC);

$misc = $pdo->prepare("SELECT * FROM daily_report_misc WHERE report_id = ?");
$misc->execute([$report_id]);
$misc_data = $misc->fetchAll(PDO::FETCH_ASSOC);

// --- 2. DATA MAPPING & PREPARATION ---

// Map Personnel to Fixed List (Exact match to image)
$fixed_roles = [
    'مدیر پروژه', 'رییس کارگاه', 'دفتر فنی', 'کنترل پروژه', 'نقشه برداری',
    'ایمنی', 'اجرا', 'ماشین آلات', 'استاد کار', 'کارگر', 'حراست', 'خدمات'
];
$pers_map = [];
foreach ($pers_data as $p) $pers_map[trim($p['role_name'])] = $p['count'];

// Split Materials
$mat_in = array_filter($mat_all, fn($m) => $m['type'] == 'IN');
$mat_out = array_filter($mat_all, fn($m) => $m['type'] == 'OUT');

// Group Misc
$tests = array_filter($misc_data, fn($m) => $m['type'] == 'TEST');
$permits = array_filter($misc_data, fn($m) => $m['type'] == 'PERMIT' || $m['type'] == 'HSE');

// Weather Checkbox Logic
$w_list = json_decode($report['weather_list'] ?? '[]', true);
function is_checked($needle, $haystack) {
    foreach ($haystack as $item) if (strpos($item, $needle) !== false) return 'checked';
    return '';
}

// Logos (You can customize these paths)
$logo_right = 'assets/images/logo-consultant.png'; // Consultant
$logo_center = 'assets/images/logo-employer.png';   // Employer
$logo_left = 'assets/images/logo-contractor.png';   // Contractor
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>گزارش روزانه - فرمت خاتم</title>
    <link href="assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        @font-face {
            font-family: "B Nazanin";
            src: url("assets/fonts/BNazanin.ttf") format("truetype");
        }
        @font-face {
            font-family: "B Titr";
            src: url("assets/fonts/BTitr.ttf") format("truetype");
        }
        
        body {
            font-family: "B Nazanin", Tahoma, sans-serif;
            font-size: 11px; /* Small font to fit everything */
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
        }

        /* MASTER LAYOUT */
        .report-container {
            width: 210mm; /* A4 Width */
            margin: 0 auto;
            border: 2px solid #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #000;
            padding: 2px 4px;
            text-align: center;
            vertical-align: middle;
            line-height: 1.3;
        }

        /* Specific Styles to match image */
        .bg-gray { background-color: #e6e6e6 !important; -webkit-print-color-adjust: exact; }
        .fw-bold { font-weight: bold; }
        .font-titr { font-family: "B Titr", sans-serif; font-weight: normal; }
        .text-start { text-align: right !important; }
        .no-border-top { border-top: none !important; }
        .no-border-bottom { border-bottom: none !important; }
        
        /* Header Logos */
        .logo-img { height: 50px; object-fit: contain; }
        
        /* Checkbox simulation */
        .cb-box {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #000;
            margin-left: 3px;
            position: relative;
            top: 2px;
        }
        .cb-box.checked::after {
            content: '✓';
            position: absolute;
            top: -4px;
            left: 1px;
            font-weight: bold;
            font-size: 12px;
        }

        /* Inputs simulation (for empty fields) */
        .fill-space { min-height: 18px; }

        @media print {
            @page { size: A4 portrait; margin: 5mm; }
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="text-center no-print p-2 bg-light border-bottom">
    <button onclick="window.print()" class="btn btn-primary btn-sm">🖨️ چاپ گزارش</button>
</div>

<div class="report-container">
    
    <!-- 1. HEADER -->
    <table style="border-bottom: 2px solid #000;">
        <tr style="height: 80px;">
            <td style="width: 15%; border-left: none;">
                <img src="<?= $logo_right ?>" class="logo-img" onerror="this.style.display='none'"><br>
                <small>مهندسین مشاور</small>
            </td>
            <td style="width: 70%; border: none;">
                <div class="d-flex justify-content-between px-4">
                    <span>پروژه پردیس دانشگاه خاتم</span>
                    <div class="text-center">
                        <img src="<?= $logo_center ?>" style="height: 40px;" onerror="this.style.display='none'"><br>
                        <span class="fw-bold font-titr">دانشگاه خاتم</span>
                    </div>
                    <span>کارفرما: دانشگاه خاتم</span>
                </div>
                <h2 class="font-titr mt-2 mb-1" style="font-size: 16px;">گـزارش روزانـه</h2>
                <div class="fw-bold">عنوان قرارداد : عملیات اجرایی نما</div>
            </td>
            <td style="width: 15%; border-right: none;">
                <div class="mb-4">لوگوی پیمانکار</div>
                <img src="<?= $logo_left ?>" class="logo-img" onerror="this.style.display='none'">
            </td>
        </tr>
    </table>

    <!-- 2. INFO BAR -->
    <table>
        <colgroup>
            <col style="width: 18%">
            <col style="width: 10%">
            <col style="width: 5%">
            <col style="width: 5%">
            <col style="width: 8%">
            <col style="width: 8%">
            <col style="width: 8%">
            <col style="width: 8%">
            <col style="width: 30%">
        </colgroup>
        <tr>
            <td class="text-start bg-gray">نظارت : شرکت طرح و سازه البرز</td>
            <td class="bg-gray">شماره گزارش:</td>
            <td><?= $report_id ?></td>
            <td rowspan="2" class="bg-gray">ساعت کار</td>
            <td class="bg-gray">روز</td>
            <td>8</td> <!-- Hardcoded or from DB -->
            <td rowspan="2" class="bg-gray">وضعیت کارگاه</td>
            <td class="text-start">
                <span class="cb-box checked"></span> فعال
            </td>
            <td class="text-start bg-gray">پیمانکار: <?= $report['contractor_fa_name'] ?></td>
        </tr>
        <tr>
            <td class="text-start bg-gray">تاریخ : <?= $jDate ?></td>
            <td class="bg-gray">روز هفته:</td>
            <td><?= $dayName ?? '' ?></td>
            <td class="bg-gray">شب</td>
            <td>0</td>
            <td class="text-start">
                <span class="cb-box"></span> غیر فعال
            </td>
            <td class="text-start bg-gray">شماره قرارداد : -</td>
        </tr>
        <tr>
            <td colspan="3" class="text-start bg-gray">
                دمای هوا : Max: <?= $report['temp_max'] ?>&deg; | Min: <?= $report['temp_min'] ?>&deg;
            </td>
            <td colspan="2" class="bg-gray">وضعیت آب و هوا :</td>
            <td colspan="4" class="text-start">
                <span class="cb-box <?= is_checked('آفتابی', $w_list) ?>"></span> صاف و آفتابی &nbsp;
                <span class="cb-box <?= is_checked('ابری', $w_list) ?>"></span> ابری و نیمه ابری &nbsp;
                <span class="cb-box <?= is_checked('باران', $w_list) ?>"></span> بارش باران &nbsp;
                <span class="cb-box <?= is_checked('برف', $w_list) ?>"></span> بارش برف &nbsp;
                <span class="cb-box <?= is_checked('باد', $w_list) ?>"></span> باد شدید &nbsp;
                <span class="cb-box <?= is_checked('مه', $w_list) ?>"></span> مه
            </td>
        </tr>
    </table>

    <!-- 3. TOP GRID (Personnel, Machinery, Materials, HSE) -->
    <!-- This matches the exact column structure of the image -->
    <table>
        <!-- Define Columns Widths -->
        <colgroup>
            <!-- Personnel (4 cols) -->
            <col style="width: 10%"> <col style="width: 3%"> <col style="width: 3%"> <col style="width: 3%">
            <!-- Machinery (4 cols) -->
            <col style="width: 10%"> <col style="width: 3%"> <col style="width: 3%"> <col style="width: 3%">
            <!-- Materials (2 cols) -->
            <col style="width: 15%"> <col style="width: 5%">
            <!-- HSE (1 col) -->
            <col style="width: 42%">
        </colgroup>
        
        <!-- Header Row -->
        <tr class="bg-gray fw-bold" style="font-size: 10px;">
            <td colspan="4">مجموع نیروی انسانی حاضر در کارگاه</td>
            <td colspan="4">مجموع ماشین آلات و تجهیزات فعال در کارگاه</td>
            <td colspan="2">ماشین آلات / مصالح / کالا /تجهیزات وارده به کارگاه</td>
            <td>شرح اقدامات و ملاحظات HSE</td>
        </tr>
        <tr class="bg-gray" style="font-size: 10px;">
            <td>شغل</td> <td>کل</td> <td>روز</td> <td>شب</td>
            <td>شغل</td> <td>کل</td> <td>روز</td> <td>شب</td>
            <td>شرح</td> <td>مقدار</td>
            <td>شرح</td>
        </tr>

        <!-- Data Rows (Fixed 12 Rows to match image lines) -->
        <?php 
        // Prepare Arrays for iteration
        $mach_list = array_values($mach_data);
        $mat_in_list = array_values($mat_in);
        $mat_out_list = array_values($mat_out);
        $hse_list = array_filter($misc_data, fn($m)=>$m['type']=='HSE');
        $hse_text = !empty($hse_list) ? implode(' - ', array_column($hse_list, 'description')) : '';
        
        for ($i = 0; $i < 12; $i++) {
            // Personnel Data
            $roleName = $fixed_roles[$i] ?? '';
            $persCount = $pers_map[$roleName] ?? '';
            
            // Machinery Data
            $machName = $mach_list[$i]['machine_name'] ?? '';
            $machCount = $mach_list[$i]['active_count'] ?? '';
            
            // Materials IN Data (First 6 rows)
            $matInDesc = ''; $matInQty = '';
            if ($i < 6) {
                $matInDesc = $mat_in_list[$i]['material_name'] ?? '';
                $matInQty = isset($mat_in_list[$i]) ? $mat_in_list[$i]['quantity'] . ' ' . $mat_in_list[$i]['unit'] : '';
            }
            
            // Materials OUT Header (Row 6)
            if ($i == 6) {
                echo '<tr class="bg-gray fw-bold"><td colspan="4" class="no-border-bottom"></td><td colspan="4" class="no-border-bottom"></td><td colspan="2">ماشین آلات / مصالح / کالا /تجهیزات خارج شده از کارگاه</td><td rowspan="6" class="text-start" style="vertical-align:top">' . ($i==6 ? '' : '') . '</td></tr>';
                echo '<tr class="bg-gray"><td colspan="4" class="no-border-top"></td><td colspan="4" class="no-border-top"></td><td>شرح</td><td>مقدار</td></tr>';
                continue; // Skip normal rendering for this index
            }
            
            // Materials OUT Data (Rows 7-11)
            $matOutDesc = ''; $matOutQty = '';
            if ($i > 6) {
                $idxOut = $i - 7; // Adjust index
                $matOutDesc = $mat_out_list[$idxOut]['material_name'] ?? '';
                $matOutQty = isset($mat_out_list[$idxOut]) ? $mat_out_list[$idxOut]['quantity'] . ' ' . $mat_out_list[$idxOut]['unit'] : '';
            }

            echo '<tr>';
            // Personnel
            echo '<td class="bg-gray text-start">' . $roleName . '</td>';
            echo '<td>' . $persCount . '</td><td>' . $persCount . '</td><td></td>'; // Assuming Night is 0/blank
            
            // Machinery
            echo '<td>' . $machName . '</td>';
            echo '<td>' . $machCount . '</td><td>' . $machCount . '</td><td></td>';
            
            // Materials (IN or OUT based on row)
            if ($i < 6) {
                echo '<td>' . $matInDesc . '</td><td>' . $matInQty . '</td>';
            } else {
                echo '<td>' . $matOutDesc . '</td><td>' . $matOutQty . '</td>';
            }
            
            // HSE (Rowspan 6 then Rowspan 5) - To keep simple, just fill first cell
            if ($i == 0) {
                echo '<td rowspan="6" class="text-start" style="vertical-align: top; padding: 5px;">' . nl2br($hse_text) . '</td>';
            } elseif ($i > 6 && $i == 7) {
                 echo '<td rowspan="5" class="text-start" style="vertical-align: top;"></td>';
            } elseif ($i > 0 && $i < 6) {
                // Do nothing, covered by rowspan
            } elseif ($i > 7) {
                // Do nothing
            }
            
            echo '</tr>';
        }
        ?>
    </table>

    <!-- 4. OPERATIONS / ACTIVITIES -->
    <table style="border-top: 2px solid #000;">
        <colgroup>
            <col style="width: 3%"> <!-- Row -->
            <col style="width: 25%"> <!-- Desc -->
            <col style="width: 10%"> <!-- Front -->
            <col style="width: 10%"> <!-- Loc -->
            <col style="width: 6%"> <!-- Volume -->
            <col style="width: 12%"> <!-- Status (3 subcols) -->
            <col style="width: 15%"> <!-- Qty (3 subcols) -->
            <col style="width: 5%"> <!-- Unit -->
            <col style="width: 15%"> <!-- Pers (3 subcols) -->
            <col style="width: 10%"> <!-- Comment -->
        </colgroup>
        
        <tr class="bg-gray fw-bold">
            <td colspan="13">عملیات اجرایی</td>
        </tr>
        <tr class="bg-gray" style="font-size: 9px;">
            <td rowspan="2">ردیف</td>
            <td rowspan="2">شــرح فعالیت</td>
            <td rowspan="2">جبهه کاری</td>
            <td rowspan="2">موقعیت</td>
            <td rowspan="2">حجم کل</td>
            <td colspan="3">وضعیت</td>
            <td colspan="3">مقادیر انجام شده</td>
            <td rowspan="2">واحد اجرا</td>
            <td colspan="3">تخصیص نیروی انسانی</td>
            <td rowspan="2">توضیحات</td>
        </tr>
        <tr class="bg-gray" style="font-size: 9px;">
            <td>در حال انجام</td> <td>اتمام</td> <td>متوقف</td>
            <td>روز</td> <td>شب</td> <td>تاکنون</td>
            <td>ایمنی</td> <td>استاد کار</td> <td>کارگر</td>
        </tr>

        <?php
        // 12 Fixed Rows for Activities
        for ($i = 0; $i < 12; $i++) {
            $act = $acts_data[$i] ?? null;
            echo '<tr>';
            echo '<td>' . ($i + 1) . '</td>';
            echo '<td class="text-start">' . ($act['act_name'] ?? '') . '</td>';
            echo '<td>' . ($act['zone_name'] ?? '') . '</td>';
            echo '<td>' . ($act['location_facade'] ?? '') . '</td>';
            echo '<td></td>'; // Total Volume not in DB usually
            
            // Status checkboxes (logic based on progress?)
            echo '<td><span class="cb-box checked"></span></td><td><span class="cb-box"></span></td><td><span class="cb-box"></span></td>';
            
            // Quantities
            echo '<td>' . ($act['contractor_quantity'] ?? '') . '</td>';
            echo '<td></td>'; // Night
            echo '<td>' . ($act['cum_installed_count'] ?? '') . '</td>';
            
            echo '<td>' . ($act['unit'] ?? '') . '</td>';
            
            // Personnel Allocation
            echo '<td></td><td></td><td>' . ($act['personnel_count'] ?? '') . '</td>';
            
            echo '<td class="text-start small">' . ($act['consultant_comment'] ?? '') . '</td>';
            echo '</tr>';
        }
        ?>
    </table>

    <!-- 5. BOTTOM SPLIT (Tests & Permits) -->
    <table style="border-top: 2px solid #000;">
        <colgroup>
            <col style="width: 5%"> <col style="width: 15%"> <col style="width: 30%"> <!-- Left side -->
            <col style="width: 5%"> <col style="width: 15%"> <col style="width: 30%"> <!-- Right side -->
        </colgroup>
        <tr class="bg-gray fw-bold">
            <td colspan="3">آزمایشات انجام شده</td>
            <td colspan="3">گزارش مجوزات</td>
        </tr>
        <tr class="bg-gray">
            <td>ردیف</td> <td>جبهه کاری</td> <td>شرح</td>
            <td>ردیف</td> <td>جبهه کاری</td> <td>شرح</td>
        </tr>
        <?php 
        // Prepare Data
        $t_list = array_values($tests);
        $p_list = array_values($permits);
        
        for($i=0; $i<5; $i++) {
            echo '<tr>';
            // Tests
            echo '<td>'.($i+1).'</td>';
            echo '<td></td>';
            echo '<td class="text-start">'.($t_list[$i]['description'] ?? '').'</td>';
            
            // Permits
            echo '<td>'.($i+1).'</td>';
            echo '<td></td>';
            echo '<td class="text-start">'.($p_list[$i]['description'] ?? '').'</td>';
            echo '</tr>';
        }
        ?>
    </table>

    <!-- 6. PROBLEMS -->
    <table style="border-top: 2px solid #000;">
        <colgroup><col style="width: 5%"><col style="width: 15%"><col style="width: 80%"></colgroup>
        <tr class="bg-gray fw-bold"><td colspan="3">شرح توضیحات، موانع و مشکلات</td></tr>
        <tr class="bg-gray"><td>ردیف</td><td>جبهه کاری</td><td>شرح</td></tr>
        <?php 
        $probs = array_filter(explode("\n", $report['problems_and_obstacles'] ?? ''));
        for($i=0; $i<6; $i++) {
            echo '<tr>';
            echo '<td>'.($i+1).'</td><td></td>';
            echo '<td class="text-start">'.($probs[$i] ?? '').'</td>';
            echo '</tr>';
        }
        ?>
    </table>

    <!-- 7. CONSULTANT NOTES -->
    <table style="border-top: 2px solid #000;">
        <colgroup><col style="width: 5%"><col style="width: 95%"></colgroup>
        <tr class="bg-gray fw-bold"><td colspan="2">شرح توضیحات نظارت</td></tr>
        <tr class="bg-gray"><td>ردیف</td><td>شرح</td></tr>
        <?php 
        $notes = array_filter(explode("\n", $report['consultant_notes'] ?? ''));
        for($i=0; $i<6; $i++) {
            echo '<tr>';
            echo '<td>'.($i+1).'</td>';
            echo '<td class="text-start">'.($notes[$i] ?? '').'</td>';
            echo '</tr>';
        }
        ?>
    </table>

    <!-- 8. SIGNATURES -->
    <table style="border-top: 2px solid #000; margin-top: 5px; border-bottom: 2px solid #000;">
        <tr class="bg-gray fw-bold text-center">
            <td style="width: 25%;">امضاء نماینده پیمانکار -رییس کارگاه</td>
            <td style="width: 25%;">امضاء ناظر مقیم</td>
            <td style="width: 25%;">امضاء HSE</td>
            <td style="width: 25%;">امضاء سرپرست نظارت</td>
        </tr>
        <tr style="height: 80px;">
            <td>
                <?php if($report['signature_contractor']): ?>
                    <img src="<?= $report['signature_contractor'] ?>" style="max-height: 60px;">
                <?php endif; ?>
            </td>
            <td>
                <?php if($report['signature_consultant']): ?>
                    <img src="<?= $report['signature_consultant'] ?>" style="max-height: 60px;">
                <?php endif; ?>
            </td>
            <td><!-- HSE Sign if available --></td>
            <td><!-- Supervisor Sign if available --></td>
        </tr>
    </table>

</div>

</body>
</html>