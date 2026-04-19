<?php
// ghom/print_checklist.php
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();
if (!isLoggedIn()) die("Access Denied");

$pdo = getProjectDBConnection('ghom');
$permitId = $_GET['permit_id'] ?? 0;
$elementId = $_GET['element_id'] ?? '';
$tab = $_GET['tab'] ?? 'zirsazi'; 

// 1. Fetch Checklist Data
$sql = "SELECT checklist_data FROM permit_elements WHERE permit_id = ?";
$params = [$permitId];
if ($elementId !== 'ALL') {
    $sql .= " AND element_id = ?";
    $params[] = $elementId;
} else {
    $sql .= " LIMIT 1";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$data = $row && $row['checklist_data'] ? json_decode($row['checklist_data'], true) : [];
$stmtMain = $pdo->prepare("SELECT contractor FROM elements e JOIN permit_elements pe ON e.element_id = pe.element_id WHERE pe.permit_id = ? LIMIT 1");
$stmtMain->execute([$permitId]);
$mainContractor = $stmtMain->fetchColumn() ?: 'نامشخص';
// 2. Fetch Permit Info
$stmtPermit = $pdo->prepare("SELECT * FROM permits WHERE id = ?");
$stmtPermit->execute([$permitId]);
$permit = $stmtPermit->fetch(PDO::FETCH_ASSOC);

if (!$permit) die("Permit not found");

// 3. Load Config
$possible_paths = [
    __DIR__ . '/assets/js/allinone.json',
    $_SERVER['DOCUMENT_ROOT'] . '/ghom/assets/js/allinone.json',
];
$config = [];
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $config = json_decode(file_get_contents($path), true);
        break;
    }
}

// 4. Resolve Labels (Zone & Block)
$svgFile = $permit['zone'];
$cleanFileName = strtolower(basename($svgFile, ".svg") . ".svg");
$persianZone = 'نامشخص';
$persianBlock = 'نامشخص';
$subContractor = !empty($permit['contractor_name']) ? $permit['contractor_name'] : 'شرکت رس';
$finalContractorDisplay = "$mainContractor / $subContractor";

if (!empty($config['regions'])) {
    if (isset($config['regions'][$permit['block']])) {
        $rData = $config['regions'][$permit['block']];
        $persianBlock = $rData['label'];
        if (isset($rData['zones'])) {
            foreach ($rData['zones'] as $z) {
                if (strtolower(basename($z['svgFile'])) === str_replace('.svg','',$cleanFileName) || strtolower(basename($z['svgFile'])) === $cleanFileName) {
                    $persianZone = $z['label'];
                    break;
                }
            }
        }
    } 
    if ($persianZone === 'نامشخص') {
        foreach ($config['regions'] as $rKey => $rData) {
            if (isset($rData['zones'])) {
                foreach ($rData['zones'] as $z) {
                    if (strtolower(basename($z['svgFile'])) === str_replace('.svg','',$cleanFileName) || strtolower(basename($z['svgFile'])) === $cleanFileName) {
                        $persianZone = $z['label'];
                        $persianBlock = $rData['label'];
                        break 2;
                    }
                }
            }
        }
    }
}
$stmtSet = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($r = $stmtSet->fetch(PDO::FETCH_ASSOC)) {
    $settings[$r['setting_key']] = $r['setting_value'];
}
$contractNum = '-';
if (strpos($mainContractor, 'رس') !== false) {
    $contractNum = $settings['contract_ros'] ?? '-';
} elseif (strpos($mainContractor, 'عمران آذرستان') !== false) {
    $contractNum = $settings['contract_omran'] ?? '-';
}
$codeNum = $permit['code_num'] ?? '-';

// 5. CRITICAL FIX: Calculate Floor & Axis Aggregates
$axisSpan = '-';
$floorLevel = '-';
$elCount = 0;

try {
    // Get Count
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM permit_elements WHERE permit_id = ?");
    $stmtCount->execute([$permitId]);
    $elCount = $stmtCount->fetchColumn();

    // Get Technical Specs
    $stmtMeta = $pdo->prepare("
        SELECT DISTINCT e.floor_level, e.axis_span 
        FROM elements e
        JOIN permit_elements pe ON e.element_id = pe.element_id
        WHERE pe.permit_id = ?
    ");
    $stmtMeta->execute([$permitId]);
    $rows = $stmtMeta->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        // Floors
        $floors = array_unique(array_column($rows, 'floor_level'));
        natsort($floors); 
        $floorLevel = implode('، ', array_filter($floors));

        // Axis
        $allAxes = [];
        foreach ($rows as $r) {
            if (!empty($r['axis_span'])) {
                $parts = preg_split('/[\-\s]+/', $r['axis_span']);
                foreach ($parts as $p) {
                    $p = trim($p);
                    if ($p) $allAxes[] = $p;
                }
            }
        }
        if (!empty($allAxes)) {
            $allAxes = array_unique($allAxes);
            natsort($allAxes); 
            if (count($allAxes) > 1) {
                $first = reset($allAxes);
                $last = end($allAxes);
                $axisSpan = "$first - $last";
            } else {
                $axisSpan = reset($allAxes);
            }
        }
    }
} catch (Exception $e) {}


// 6. Helpers
function renderCheck($val, $target) {
    return ($val === $target) ? 'X' : '';
}
function renderNote($val) {
    return htmlspecialchars($val ?? '');
}

$mainTitle = "چک لیست پایان عملیات اصلاحات نما و پرمیت شروع عملیات نصب پنل‌های GFRC";
$subTitle = ($tab === 'zirsazi') 
    ? "اصلاحات زیرسازی نمای GFRC" 
    : "نصب پنل های جدید و اصلاحی GFRC";

$elementDisplayId = ($elementId === 'ALL') ? "همه موارد (گروهی - $elCount عدد)" : $elementId;
$lastUpdateDate = $data['_meta']['persian_date'] ?? '---';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>چک لیست - <?= $tab ?> - <?= $permitId ?></title>
    <style>
        @font-face { font-family: "Samim"; src: url("/ghom/assets/fonts/Samim-FD.woff") format("woff"); }
        body { font-family: "Samim", Tahoma; font-size: 11px; margin: 0; padding: 10px; box-sizing: border-box; }
        table { width: 100%; border-collapse: collapse; text-align: center; margin-bottom: 5px; }
        td, th { border: 1px solid black; padding: 4px; vertical-align: middle; }
        .logo-img { height: 50px; object-fit: contain; }
        .section-header { background: #f0f0f0; font-weight: bold; width: 30px; }
        .rotate-text { 
            writing-mode: vertical-rl; 
            transform: rotate(180deg); 
            white-space: nowrap;
            margin: 0 auto;
        }
        .gray-bg { background-color: #e0e0e0; font-weight: bold; }
        .light-gray { background-color: #f9f9f9; }
        .no-print { text-align: center; margin-bottom: 20px; padding: 10px; background: #eee; border-bottom: 1px solid #ccc; }
        @media print { 
            .no-print { display: none; } 
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" style="padding:10px 20px; background:#007bff; color:white; border:none; cursor:pointer; border-radius: 4px; font-family: inherit; font-size: 14px;">🖨️ چاپ چک لیست</button>
        <div style="margin-top: 5px; color: #555;">(مجوز شماره: <?= $permitId ?> | <?= $tab === 'zirsazi' ? 'زیرسازی' : 'نصب' ?>)</div>
    </div>

    <!-- 1. LOGOS HEADER -->
 <table style="border:none; margin-bottom:5px;">
        <tr style="border:none;">
            <!-- LEFT SIDE: NOI, ROS, ALUMGLASS -->
            <td style="border:1px solid black; width:30%; text-align: center;">
                            <img src="/ghom/assets/images/liman_logo.png" class="logo-img" style="height:45px; margin:0 5px;"> 
                <img src="/ghom/assets/images/khatam_logo.png" class="logo-img" style="height:45px; margin:0 5px;">
            </td>
            
            <!-- CENTER: TITLE -->
            <td style="border:1px solid black; width:40%; font-size: 18px; font-weight: bold;"><?= $mainTitle ?></td>

            <!-- RIGHT SIDE: LIMAN, KHATAM -->
            <td style="border:1px solid black; width:30%; text-align: center;">
                <img src="/ghom/assets/images/alumglass.png" class="logo-img" style="height:45px; margin:0 2px;">  
             <img src="/ghom/assets/images/noi_logo.png" class="logo-img" style="height:45px; margin:0 2px;"> 
                <img src="/ghom/assets/images/ros_logo.png" class="logo-img" style="height:45px; margin:0 2px;">
                
            </td>
             
        </tr>
    </table>


    <!-- 2. PROJECT INFO -->
    <table class="gray-bg">
        <tr>
            <td colspan="4" style="text-align: right; padding-right: 10px;">عنوان چک لیست: <strong><?= $subTitle ?></strong></td>
        </tr>
        <tr>
            <td>کد نقشه: <?= $permit['zone'] ?></td>
            <td>طبقه: <?= $floorLevel ?></td>
            <td>محور: <?= $axisSpan ?></td>
            <td>زون: <?= $persianZone ?></td>
        </tr>
        <tr>
            <td colspan="2">مجوز شماره: <?= $permitId ?></td></td>
            <td colspan="2">موقعیت: <?= $persianBlock ?></td>
        </tr>
        <tr>
             <td>شماره قرارداد: <?= $contractNum ?></td> 
            <td>نظارت: مهندسین مشاور نوی</td>
            <td>مشاور: آلومینیوم شیشه تهران</td>
          <td>پیمانکار: <?= $finalContractorDisplay ?></td>
        </tr>
    </table>

    <br>

    <!-- 3. CHECKLIST ITEMS -->
    <table>
        <!-- Header Row -->
        <tr class="gray-bg">
            <th rowspan="2">موارد</th>
            <th rowspan="2">ردیف</th>
            <th rowspan="2" style="width:35%;">موارد کنترلی</th>
            <th rowspan="2">واحد کنترل</th>
            <th rowspan="2">مرجع</th>
            <th colspan="3">بازدید اولیه</th>
            <th colspan="3">بازدید نهایی</th>
            <th rowspan="2" style="width:15%;">توضیحات</th>
        </tr>
        <tr class="light-gray">
            <th>OK</th><th>NC</th><th>NA</th><th>OK</th><th>NC</th><th>NA</th>
        </tr>

    <?php if ($tab === 'zirsazi'): ?>
        <!-- ================= ZIRSAZI TAB ================= -->
        <?php
        $items_s1 = [
            '1' => 'جنس و ضخامت ورق گالوانیزه',
            '2' => 'دانسیته و ضخامت پشم سنگ',
            '3' => 'نحوه درزبندی ها با چسب سیلیکونی ضد رطوبت و فوم پلی اورتان',
            '4' => 'ابعاد و اندازه و خمکاری ساندویچ پنل ها',
            '5' => 'اتصالات با بدنه فلزی'
        ];
        foreach($items_s1 as $k => $txt) {
            $rid = "s1_r$k";
            echo "<tr>";
            if($k==1) echo "<td rowspan='5' class='section-header'><div class='rotate-text'>آب بندی و هوابندی</div></td>";
            echo "<td>$k</td>
                  <td style='text-align:right;'>$txt</td>
                  <td>QC</td><td>نقشه</td>
                  <td>".renderCheck($data[$rid]['init']??'','OK')."</td><td>".renderCheck($data[$rid]['init']??'','NC')."</td><td>".renderCheck($data[$rid]['init']??'','NA')."</td>
                  <td>".renderCheck($data[$rid]['final']??'','OK')."</td><td>".renderCheck($data[$rid]['final']??'','NC')."</td><td>".renderCheck($data[$rid]['final']??'','NA')."</td>
                  <td>".renderNote($data[$rid]['note']??'')."</td>";
            echo "</tr>";
        }
        
        $items_s2 = [
            '1' => 'جنس و ضخامت ورق و پروفیل و مقاطع فلزی',
            '2' => 'ابعاد و اندازه پروفیل ها و یونیت های فلزی',
            '3' => 'کیفیت اتصالات جوشی',
            '4' => 'کیفیت اتصالات پیچ و مهره و ...',
            '5' => 'کنترل جانمایی ارتفاعی، طولی و انحراف بیرونی نبشی ها',
            '6' => 'اجرای ضد زنگ با جنس و ضخامت مناسب'
        ];
        foreach($items_s2 as $k => $txt) {
            $rid = "s2_r$k";
            $unit = ($k==5) ? '* نقشه بردار' : 'QC';
            echo "<tr>";
            if($k==1) echo "<td rowspan='6' class='section-header'><div class='rotate-text'>شاسی کشی فلزی</div></td>";
            echo "<td>$k</td>
                  <td style='text-align:right;'>$txt</td>
                  <td>$unit</td><td>نقشه</td>
                  <td>".renderCheck($data[$rid]['init']??'','OK')."</td><td>".renderCheck($data[$rid]['init']??'','NC')."</td><td>".renderCheck($data[$rid]['init']??'','NA')."</td>
                  <td>".renderCheck($data[$rid]['final']??'','OK')."</td><td>".renderCheck($data[$rid]['final']??'','NC')."</td><td>".renderCheck($data[$rid]['final']??'','NA')."</td>
                  <td>".renderNote($data[$rid]['note']??'')."</td>";
            echo "</tr>";
        }
        ?>

    <?php else: ?>
        <!-- ================= NASB TAB ================= -->
        <?php
        $items_n1 = [
            '1' => 'کنترل جانمایی ارتفاعی، طولی و انحراف بیرونی پنل نصب شده روی نما',
            '2' => 'کنترل ریسمانی و شاغولی بودن کل پنل های مقطع مورد نظر',
            '3' => 'مشخصات ظاهری پنل: ابعاد، اندازه، ضخامت و ترک و لب پریدگی',
            '4' => 'کنترل نوع و بافت و یکنواختی رنگ در پنل GFRC',
            '5' => 'کنترل مشخصات مقاومت های کششی و خمشی و فشاری پنل',
            '6' => 'کنترل جنس و نوع اتصالات و کلمپ های گالوانیزه و پیچ و مهره',
            '7' => 'کنترل ابعاد و اندازه ی درزهای اجرایی افقی و عمودی'
        ];
        foreach($items_n1 as $k => $txt) {
            $rid = "n1_r$k";
            $unit = ($k<=2) ? '* نقشه بردار' : 'QC';
            $ref = ($k==4 || $k==5) ? 'دیتا شیت/تست' : 'نقشه';
            echo "<tr>";
            if($k==1) echo "<td rowspan='7' class='section-header'><div class='rotate-text'>کنترل پنل نصب شده</div></td>";
            echo "<td>$k</td>
                  <td style='text-align:right;'>$txt</td>
                  <td>$unit</td><td>$ref</td>
                  <td>".renderCheck($data[$rid]['init']??'','OK')."</td><td>".renderCheck($data[$rid]['init']??'','NC')."</td><td>".renderCheck($data[$rid]['init']??'','NA')."</td>
                  <td>".renderCheck($data[$rid]['final']??'','OK')."</td><td>".renderCheck($data[$rid]['final']??'','NC')."</td><td>".renderCheck($data[$rid]['final']??'','NA')."</td>
                  <td>".renderNote($data[$rid]['note']??'')."</td>";
            echo "</tr>";
        }
        
        $items_n2 = [
            '1' => 'کنترل جنس و ابعاد و ضخامت پروفیل و مقاطع آلومینیومی خاص',
            '2' => 'کنترل فیزیکی و شیمیایی چسب بکار رفته در اتصال',
            '3' => 'کنترل جانمایی مقاطع اجرای پروفیل های آلومینیومی در نما',
            '4' => 'کنترل برش و نحوه قرارگیری پروفیل های آلومینیومی در گوشه ها',
            '5' => 'کنترل نوع رنگ و بافت پروفیل های آلومینیومی آببندی'
        ];
        foreach($items_n2 as $k => $txt) {
            $rid = "n2_r$k";
            $ref = ($k==2 || $k==5) ? 'دیتا شیت' : 'نقشه';
            echo "<tr>";
            if($k==1) echo "<td rowspan='5' class='section-header'><div class='rotate-text'>آب بندی درز پنل ها</div></td>";
            echo "<td>$k</td>
                  <td style='text-align:right;'>$txt</td>
                  <td>QC</td><td>$ref</td>
                  <td>".renderCheck($data[$rid]['init']??'','OK')."</td><td>".renderCheck($data[$rid]['init']??'','NC')."</td><td>".renderCheck($data[$rid]['init']??'','NA')."</td>
                  <td>".renderCheck($data[$rid]['final']??'','OK')."</td><td>".renderCheck($data[$rid]['final']??'','NC')."</td><td>".renderCheck($data[$rid]['final']??'','NA')."</td>
                  <td>".renderNote($data[$rid]['note']??'')."</td>";
            echo "</tr>";
        }
        ?>

    <?php endif; ?>

        <tr>
            <td colspan="12" style="text-align:right; padding:5px; font-size:10px;">* مسئولیت جمع آوری متریال، ضایعات پای کار و نظافت کارگاه با پیمانکار میباشد.</td>
        </tr>
         <tr>
            <td colspan="12" style="text-align:right; padding:5px; font-size:10px;">احتراماَ این پیمانکار اعلام می‌داردکلیه ایرادات موجود در زیرسازی، هوابندی، آببندی پنل هایGFRC را برطرف نموده و درخواست نصب پنل‌ها را دارد</td>
        </tr>
    </table>
    
    <br><br>

    <!-- SIGNATURES -->
    <table style="height:100px; text-align:center;">
        <tr>
            <td style="vertical-align:top; width:25%;">
                <strong><?= $subContractor ?></strong><br><br><br>
                <small>مهر و امضا و تاریخ</small>
            </td>
            <td style="vertical-align:top; width:25%;">
                <strong><?= $mainContractor ?></strong><br><br><br>
                <small>مهر و امضا و تاریخ</small>
            </td>
            <td style="vertical-align:top; width:25%;">
                <strong>شرکت آلومینیوم شیشه تهران</strong><br><br><br>
                <small>مهر و امضا و تاریخ</small>
            </td>
            <td style="vertical-align:top; width:25%;">
                <strong>شرکت مهندسین مشاور نوی</strong><br><br><br>
                <small>مهر و امضا و تاریخ</small>
            </td>
        </tr>
    </table>

    <!-- FOOTER INFO -->
    <div style="border:1px solid black; padding:5px; margin-top:5px; display:flex; justify-content:space-between; font-size:10px; font-weight:bold;">
        
        <strong dir="ltr">Code Num: <?= $codeNum ?></strong> 
        <span>OK: OK &nbsp;&nbsp; N.A: NOT APPLICABLE &nbsp;&nbsp; N.C: NOT CONFORMANCE</span>
        <span>تاریخ آخرین ویرایش: <?= $lastUpdateDate ?></span>
    </div>

</body>
</html>