<?php
// weekly_report_ps.php - FINAL VERSION
// Features: ZIP Download, HTML Report, Images, SINGLE EXCEL FILE with all data

ob_start();

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

$pdo = getProjectDBConnection('pardis');
$user_role = $_SESSION['role'];

// Contractors Map
$contractor_map = [
    'car' => 'شرکت آران سیج',
    'cod' => 'شرکت طرح و نقش آدرم'
];
$is_contractor = array_key_exists($user_role, $contractor_map);

// Helper: Date Conversion
function toGregorian($jDate) {
    if (empty($jDate)) return false;
    $p = explode('/', $jDate);
    if(count($p) !== 3) return false;
    return implode('-', jalali_to_gregorian((int)$p[0], (int)$p[1], (int)$p[2]));
}

// Helper: Personnel Category
function getRoleCategory($role) {
    $role = trim($role);
    $map = [
        'اجرا' => 'اجرایی',
        'استاد کار' => 'اجرایی',
        'انباردار' => 'ستادی',
        'ایمنی' => 'اجرایی',
        'حراست' => 'ستادی',
        'خدمات' => 'ستادی',
        'داربست بند' => 'اجرایی',
        'دفتر فنی' => 'ستادی',
        'رییس کارگاه' => 'ستادی',
        'ماشین آلات' => 'اجرایی',
        'مدیر پروژه' => 'ستادی',
        'نقشه برداری' => 'اجرایی',
        'نیروی تجهیزکارگاه' => 'اجرایی',
        'کارگر' => 'اجرایی',
        'کنترل پروژه' => 'ستادی'
    ];
    return isset($map[$role]) ? $map[$role] : 'سایر';
}

// =========================================================
// GENERATE ZIP LOGIC
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_zip'])) {
    
    error_reporting(0);

    $date_from_j = $_POST['date_from'];
    $date_to_j = $_POST['date_to'];
    $target_contractor = $is_contractor ? $contractor_map[$user_role] : $_POST['contractor'];
    
    $date_from_g = toGregorian($date_from_j);
    $date_to_g = toGregorian($date_to_j);
    
    if (!$date_from_g || !$date_to_g) {
        ob_end_clean();
        die("تاریخ نامعتبر است.");
    }

    // --- FETCH DATA ---
    $where = "report_date BETWEEN ? AND ? AND status IN ('Submitted', 'Approved')";
    $params = [$date_from_g, $date_to_g];
    
    if (!empty($target_contractor)) {
        $where .= " AND contractor_fa_name LIKE ?";
        $params[] = "%$target_contractor%";
    }
    
    $stmt = $pdo->prepare("SELECT * FROM ps_daily_reports WHERE $where ORDER BY report_date ASC");
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($reports)) {
        ob_end_clean();
        die("<h3>هیچ گزارش تایید شده یا ارسال شده‌ای در این بازه یافت نشد.</h3><a href='weekly_report_ps.php'>بازگشت</a>");
    }

    $report_ids = array_column($reports, 'id');
    $placeholders = implode(',', array_fill(0, count($report_ids), '?'));

    // 1. Personnel Stats
    $stmt = $pdo->prepare("SELECT role_name, SUM(count + count_night) as total_days, SUM(consultant_count) as total_approved
                FROM ps_daily_report_personnel WHERE report_id IN ($placeholders) GROUP BY role_name");
    $stmt->execute($report_ids);
    $personnel_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_executive_days = 0;
    $total_staff_days = 0;
    $personnel_stats = [];

    foreach($personnel_raw as $p) {
        $cat = getRoleCategory($p['role_name']);
        $days = (int)$p['total_days'];
        if($cat === 'ستادی') $total_staff_days += $days;
        else $total_executive_days += $days;
        $p['category'] = $cat;
        $personnel_stats[] = $p;
    }

    // 2. Machinery Stats
    $stmt = $pdo->prepare("SELECT machine_name, SUM(active_count) as total_active, SUM(total_count) as total_present
               FROM ps_daily_report_machinery WHERE report_id IN ($placeholders) GROUP BY machine_name");
    $stmt->execute($report_ids);
    $machinery_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Materials Stats
    $stmt = $pdo->prepare("SELECT material_name, unit, type, SUM(quantity) as total_qty
               FROM ps_daily_report_materials WHERE report_id IN ($placeholders) GROUP BY material_name, unit, type");
    $stmt->execute($report_ids);
    $material_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Activities Stats
    $stmt = $pdo->prepare("SELECT pa.name, dra.unit, dr.block_name, dra.location_facade,
               SUM(COALESCE(dra.qty_day, 0) + COALESCE(dra.qty_night, 0)) as period_qty,
               MAX(dra.qty_cumulative) as max_cumulative,
               MAX(dra.vol_total) as total_volume
               FROM ps_daily_report_activities dra
               JOIN ps_daily_reports dr ON dra.report_id = dr.id
               JOIN ps_project_activities pa ON dra.activity_id = pa.id
               WHERE dra.report_id IN ($placeholders)
               GROUP BY pa.id, dr.block_name, dra.location_facade");
    $stmt->execute($report_ids);
    $activity_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Photos & Docs
    $stmt = $pdo->prepare("SELECT * FROM ps_daily_report_photos WHERE report_id IN ($placeholders)");
    $stmt->execute($report_ids);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT * FROM ps_daily_report_material_docs WHERE report_id IN ($placeholders)");
    $stmt->execute($report_ids);
    $mat_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Logs & Charts
    $log_problems = [];
    $chart_dates = [];
    $chart_vals = [];
    $day_sums = [];
    
    foreach($reports as $r) {
        $jDate = jdate('Y/m/d', strtotime($r['report_date']));
        if(!empty($r['problems_and_obstacles'])) {
            $log_problems[] = "[$jDate - {$r['block_name']}]: " . $r['problems_and_obstacles'];
        }
        
        $d = jdate('m/d', strtotime($r['report_date']));
        if(!isset($day_sums[$d])) $day_sums[$d] = 0;
        $pid = $r['id'];
        $cntStmt = $pdo->prepare("SELECT SUM(count + count_night) FROM ps_daily_report_personnel WHERE report_id = ?");
        $cntStmt->execute([$pid]);
        $cnt = $cntStmt->fetchColumn();
        $day_sums[$d] += ($cnt ?: 0);
    }
    $chart_dates = array_keys($day_sums);
    $chart_vals = array_values($day_sums);
    $total_man_days = array_sum($chart_vals);
    $avg_man_days = count($reports) > 0 ? round($total_man_days / count($reports)) : 0;

    // --- CREATE ZIP ---
    $zip = new ZipArchive();
    $zipName = "Report_" . str_replace('/', '-', $date_from_j) . "_to_" . str_replace('/', '-', $date_to_j) . ".zip";
    $tmp_file = tempnam(sys_get_temp_dir(), 'zip_ps');
    
    if ($zip->open($tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        ob_end_clean();
        die("خطا در ایجاد فایل فشرده سرور.");
    }

    // ------------------------------------------------------------------
    // A. GENERATE HTML REPORT (FOR BROWSER VIEWING)
    // ------------------------------------------------------------------
    $images_html = "";
    foreach ($photos as $ph) {
        $rel = $ph['photo_path'];
        $clean_rel = strtok($rel, '?');
        $abs_path = $_SERVER['DOCUMENT_ROOT'] . $clean_rel; 
        if (!file_exists($abs_path) && strpos($clean_rel, '/pardis') === 0) $abs_path = $_SERVER['DOCUMENT_ROOT'] . $clean_rel; 
        
        if (file_exists($abs_path)) {
            $fname = "photos\img_" . $ph['id'] . ".jpg";
            $zip->addFile($abs_path, $fname);
            $caption = $ph['caption'] ?: 'بدون عنوان';
            $images_html .= "<div class='col-md-4 mb-3'><div class='card'><img src='$fname' class='card-img-top' style='height:200px;object-fit:cover'><div class='card-body p-2 text-center small'>$caption</div></div></div>";
        }
    }
    // Add Docs to ZIP
    foreach ($mat_docs as $doc) {
        if(!empty($doc['file_path'])) {
            $cl_path = strtok($doc['file_path'], '?');
            $abs_doc = $_SERVER['DOCUMENT_ROOT'] . $cl_path;
            if(file_exists($abs_doc)) {
                $ext = pathinfo($abs_doc, PATHINFO_EXTENSION);
                $zip->addFile($abs_doc, "docs/doc_" . $doc['id'] . "." . $ext);
            }
        }
    }

    $html = "
    <!DOCTYPE html>
    <html lang='fa' dir='rtl'>
    <head>
        <meta charset='UTF-8'>
        <title>گزارش هفتگی</title>
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css' />
        <link rel='stylesheet' href='https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css' />
        <link rel='stylesheet' href='https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css' />
        <script src='https://cdn.jsdelivr.net/npm/apexcharts'></script>
        <style>
            @font-face { font-family: 'Samim'; src: url('https://cdn.fontcdn.ir/Font/Persian/Samim/Samim.woff2') format('woff2'); }
            body { font-family: 'Samim', Tahoma; background: #fff; padding: 20px; }
            .header { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center; border: 1px solid #ddd; }
            .sec-title { border-bottom: 2px solid #0d6efd; padding-bottom: 5px; margin: 40px 0 15px 0; font-weight: bold; color: #004085; }
            div.dataTables_wrapper div.dataTables_filter { text-align: left; }
            .badge-exec { background-color: #198754; color:white; }
            .badge-staff { background-color: #0d6efd; color:white; }
        </style>
    </head>
    <body>
        <div class='container-fluid'>
            <div class='header'>
                <h2>گزارش جامع پروژه پردیس</h2>
                <div class='mt-2'><strong>بازه:</strong> $date_from_j تا $date_to_j</div>
                <div><strong>پیمانکار:</strong> ".($target_contractor ?: 'کلیه پیمانکاران')."</div>
            </div>
            <div class='row mb-4'>
                <div class='col-md-3'><div class='alert alert-primary text-center p-2'><strong>تعداد گزارشات:</strong> ".count($reports)."</div></div>
                <div class='col-md-3'><div class='alert alert-success text-center p-2'><strong>نفر-روز کل:</strong> ".number_format($total_man_days)."</div></div>
                <div class='col-md-3'><div class='alert alert-info text-center p-2'><strong>میانگین نیرو:</strong> $avg_man_days</div></div>
                <div class='col-md-3'><div class='alert alert-warning text-center p-2'><strong>آیتم‌های اجرایی:</strong> ".count($activity_stats)."</div></div>
            </div>
            <div class='row mb-4'>
                <div class='col-md-6'><div class='card border-success'><div class='card-body text-center'><h5 class='text-success'>نیروی اجرایی</h5><h3>".number_format($total_executive_days)."</h3></div></div></div>
                <div class='col-md-6'><div class='card border-primary'><div class='card-body text-center'><h5 class='text-primary'>نیروی ستادی</h5><h3>".number_format($total_staff_days)."</h3></div></div></div>
            </div>
            <div class='card mb-4'><div class='card-body'><h6>نمودار نوسان نیروی انسانی</h6><div id='chart'></div></div></div>

            <h5 class='sec-title'>۱. پیشرفت فیزیکی فعالیت‌ها</h5>
            <table id='activityTable' class='table table-bordered table-striped table-hover'>
                <thead class='table-dark'><tr><th>فعالیت</th><th>بلوک</th><th>موقعیت</th><th>واحد</th><th>انجام شده</th><th>تجمعی</th><th>حجم کل</th><th>%</th></tr></thead>
                <tbody>";
                foreach($activity_stats as $a) {
                    $vol = floatval($a['total_volume']);
                    $cum = floatval($a['max_cumulative']);
                    $pct = ($vol > 0) ? round(($cum/$vol)*100, 1) : 0;
                    $loc = !empty($a['location_facade']) ? $a['location_facade'] : '-';
                    $html .= "<tr><td>{$a['name']}</td><td>{$a['block_name']}</td><td>{$loc}</td><td>{$a['unit']}</td><td class='fw-bold'>".number_format($a['period_qty'], 2)."</td><td>".number_format($cum, 2)."</td><td>".number_format($vol, 2)."</td><td>$pct%</td></tr>";
                }
    $html .= "</tbody></table>

            <h5 class='sec-title'>۲. آمار نیروی انسانی</h5>
            <table id='personnelTable' class='table table-bordered table-striped'>
                <thead class='table-dark'><tr><th>شغل</th><th>نوع</th><th>کل روزها</th><th>تایید شده</th></tr></thead>
                <tbody>";
                foreach($personnel_stats as $p) {
                    $badge = ($p['category']=='ستادی') ? 'badge-staff' : 'badge-exec';
                    $html .= "<tr><td>{$p['role_name']}</td><td><span class='badge $badge'>{$p['category']}</span></td><td>{$p['total_days']}</td><td>{$p['total_approved']}</td></tr>";
                }
    $html .= "</tbody></table>

            <h5 class='sec-title'>۷. مستندات تصویری</h5>
            <div class='row'>$images_html</div>
        </div>
        <script src='https://code.jquery.com/jquery-3.7.0.min.js'></script>
        <script src='https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js'></script>
        <script src='https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js'></script>
        <script>
            $(document).ready(function() { $('table').DataTable({dom: 'Bfrtip', pageLength: 50}); });
            new ApexCharts(document.querySelector('#chart'), {series: [{name:'نفر-روز', data:".json_encode($chart_vals)."}], chart: {type:'area', height:300}, xaxis: {categories:".json_encode($chart_dates)."}, colors: ['#0d6efd']}).render();
        </script>
    </body>
    </html>";

    $zip->addFromString('Weekly_Report.html', $html);

    // ------------------------------------------------------------------
    // B. GENERATE SINGLE EXCEL FILE (Full_Data_Report.xls)
    // ------------------------------------------------------------------
    // We create an HTML string compatible with Excel
    
    $excel_html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <style>
            body { font-family: Tahoma, Arial; direction: rtl; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
            th { background-color: #f0f0f0; border: 1px solid #000; padding: 5px; }
            td { border: 1px solid #000; padding: 5px; text-align: center; }
            .header { font-size: 16px; font-weight: bold; background-color: #d9edf7; padding: 10px; text-align: center; border: 2px solid #000; }
            .section-header { font-size: 14px; font-weight: bold; background-color: #333; color: #fff; padding: 5px; }
            .exec { background-color: #d1e7dd; }
            .staff { background-color: #cfe2ff; }
        </style>
    </head>
    <body>';

    // 1. Header
    $excel_html .= "
    <table>
        <tr><td colspan='8' class='header'>گزارش جامع هفتگی پروژه پردیس</td></tr>
        <tr><td colspan='2'><strong>پیمانکار:</strong></td><td colspan='6'>".($target_contractor ?: 'کلیه پیمانکاران')."</td></tr>
        <tr><td colspan='2'><strong>بازه زمانی:</strong></td><td colspan='6'>$date_from_j تا $date_to_j</td></tr>
        <tr>
            <td colspan='2'><strong>نفر-روز کل:</strong> " . number_format($total_man_days) . "</td>
            <td colspan='2'><strong>نیروی اجرایی:</strong> " . number_format($total_executive_days) . "</td>
            <td colspan='2'><strong>نیروی ستادی:</strong> " . number_format($total_staff_days) . "</td>
            <td colspan='2'><strong>میانگین:</strong> $avg_man_days</td>
        </tr>
    </table>";

    // 2. Activities
    $excel_html .= "<table>
        <tr><th colspan='8' class='section-header'>۱. پیشرفت فیزیکی فعالیت‌ها</th></tr>
        <tr>
            <th>فعالیت</th><th>بلوک</th><th>موقعیت (نما)</th><th>واحد</th>
            <th>انجام شده در دوره</th><th>تجمعی تا پایان</th><th>حجم کل</th><th>درصد پیشرفت</th>
        </tr>";
    foreach($activity_stats as $a) {
        $vol = floatval($a['total_volume']);
        $cum = floatval($a['max_cumulative']);
        $pct = ($vol > 0) ? round(($cum/$vol)*100, 1) : 0;
        $loc = !empty($a['location_facade']) ? $a['location_facade'] : '-';
        $excel_html .= "<tr>
            <td>{$a['name']}</td><td>{$a['block_name']}</td><td>{$loc}</td><td>{$a['unit']}</td>
            <td>".number_format($a['period_qty'], 2)."</td>
            <td>".number_format($cum, 2)."</td>
            <td>".number_format($vol, 2)."</td>
            <td>$pct%</td>
        </tr>";
    }
    $excel_html .= "</table>";

    // 3. Personnel
    $excel_html .= "<table>
        <tr><th colspan='4' class='section-header'>۲. آمار نیروی انسانی</th></tr>
        <tr><th>شغل / سمت</th><th>دسته بندی</th><th>کل روزهای کاری</th><th>تایید شده نظارت</th></tr>";
    foreach($personnel_stats as $p) {
        $bg = ($p['category']=='ستادی') ? 'class="staff"' : 'class="exec"';
        $excel_html .= "<tr>
            <td>{$p['role_name']}</td><td $bg>{$p['category']}</td>
            <td>{$p['total_days']}</td><td>{$p['total_approved']}</td>
        </tr>";
    }
    $excel_html .= "</table>";

    // 4. Machinery
    $excel_html .= "<table>
        <tr><th colspan='3' class='section-header'>۳. ماشین‌آلات و تجهیزات</th></tr>
        <tr><th>نام دستگاه</th><th>روزهای حضور</th><th>روزهای فعال</th></tr>";
    foreach($machinery_stats as $m) {
        $excel_html .= "<tr><td>{$m['machine_name']}</td><td>{$m['total_present']}</td><td>{$m['total_active']}</td></tr>";
    }
    $excel_html .= "</table>";

    // 5. Materials
    $excel_html .= "<table>
        <tr><th colspan='4' class='section-header'>۴. مصالح وارده و مصرفی</th></tr>
        <tr><th>نوع</th><th>نام کالا</th><th>مقدار</th><th>واحد</th></tr>";
    foreach($material_stats as $mt) {
        $type_fa = ($mt['type']=='IN') ? 'ورودی' : 'خروجی/مصرفی';
        $color = ($mt['type']=='IN') ? '#d1e7dd' : '#f8d7da';
        $excel_html .= "<tr>
            <td style='background-color:$color'>$type_fa</td><td>{$mt['material_name']}</td>
            <td>{$mt['total_qty']}</td><td>{$mt['unit']}</td>
        </tr>";
    }
    $excel_html .= "</table>";

    // 6. Problems
    $excel_html .= "<table>
        <tr><th class='section-header'>۵. لیست مشکلات و موانع ثبت شده</th></tr>";
    if(empty($log_problems)) {
        $excel_html .= "<tr><td>موردی ثبت نشده است.</td></tr>";
    } else {
        foreach($log_problems as $lp) $excel_html .= "<tr><td style='text-align:right'>$lp</td></tr>";
    }
    $excel_html .= "</table>";
    
    $excel_html .= "</body></html>";

    // Add Excel to ZIP
    // Add BOM for Excel UTF-8 compatibility
    $zip->addFromString('Full_Data_Report.xls', pack("CCC",0xef,0xbb,0xbf) . $excel_html);

    $zip->close();

    // =========================================================
    // SEND FILE
    // =========================================================
    ob_end_clean(); 
    
    if (file_exists($tmp_file) && filesize($tmp_file) > 0) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.$zipName.'"');
        header('Content-Length: ' . filesize($tmp_file));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($tmp_file);
        unlink($tmp_file); 
        exit();
    } else {
        die("خطا در تولید فایل.");
    }
}
ob_end_flush();
require_once __DIR__ . '/header_pardis.php';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>دانلود گزارش هفتگی</title>
    <link href="/pardis/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="/pardis/assets/css/jalalidatepicker.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/pardis/assets/css/all.min.css">
    <style>
        @font-face { font-family: "Samim"; src: "/pardis/assets/fonts/Samim-FD.woff2" format("woff2"); }
        body { font-family: "Samim", sans-serif; background: #f8f9fa; display:flex; align-items:center; justify-content:center; height:100vh; }
        .card { width:100%; max-width:500px; border-radius:15px; border:none; box-shadow:0 10px 25px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="card p-4">
        <div class="text-center mb-4">
            <h4 class="text-primary fw-bold">دانلود گزارش جامع (ZIP)</h4>
            <p class="text-muted small">شامل گزارش HTML + فایل اکسل کامل (Full_Data_Report.xls)</p>
        </div>
        
        <form method="POST">
            <?= csrfField() ?>
            <?php if(!$is_contractor): ?>
            <div class="mb-3">
                <label class="form-label">پیمانکار</label>
                <select name="contractor" class="form-select">
                    <option value="">همه موارد</option>
                    <?php foreach($contractor_map as $c_name): ?>
                        <option value="<?=$c_name?>"><?=$c_name?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="row g-2 mb-4">
                <div class="col-6">
                    <label class="form-label small">از تاریخ</label>
                    <input type="text" name="date_from" class="form-control text-center" data-jdp required autocomplete="off">
                </div>
                <div class="col-6">
                    <label class="form-label small">تا تاریخ</label>
                    <input type="text" name="date_to" class="form-control text-center" data-jdp required autocomplete="off">
                </div>
            </div>

            <button type="submit" name="generate_zip" class="btn btn-primary w-100 py-2">
                <i class="fas fa-file-excel me-2"></i> دانلود پکیج (ZIP)
            </button>
            <a href="daily_reports_dashboard_ps.php" class="btn btn-link w-100 mt-2 text-decoration-none text-secondary">بازگشت</a>
        </form>
    </div>

    <script src="/pardis/assets/js/jquery-3.7.0.min.js"></script>
    <script src="/pardis/assets/js/jalalidatepicker.min.js"></script>
    <script>jalaliDatepicker.startWatch();</script>
    <?php require_once 'footer.php'; ?>
</body>
</html>