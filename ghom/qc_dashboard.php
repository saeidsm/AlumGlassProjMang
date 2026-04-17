<?php
//qc_dashboard.php - FIXED VERSION WITH COMMON DB USERS
// --- 1. CONFIGURATION & AUTH ---
function isMobileDevice() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

if (!isLoggedIn()) { header('Location: /login.php?msg=login_required'); exit(); }

$conn = getProjectDBConnection("ghom");
$commonPdo = getCommonDBConnection(); // Connection to the DB where 'users' table exists

// --- 2. HELPERS ---
function g2j($gDate) {
    if (empty($gDate) || $gDate == '0000-00-00' || $gDate == '0000-00-00 00:00:00') return '-';
    $ts = strtotime($gDate);
    if (!$ts) return '-';
    $j = gregorian_to_jalali(date('Y', $ts), date('m', $ts), date('d', $ts));
    return implode('/', $j);
}

// --- 3. FETCH USERS (From Common DB) ---
// We fetch all users into an array to map IDs to Names without cross-database JOINs
$userMap = [];
try {
    $sqlUsers = "SELECT id, first_name, last_name FROM users";
    $userRows = $commonPdo->query($sqlUsers)->fetchAll(PDO::FETCH_ASSOC);
    foreach($userRows as $u) {
        $userMap[$u['id']] = $u['first_name'] . ' ' . $u['last_name'];
    }
} catch (Exception $e) {
    // Fallback if common DB fails
    error_log("Failed to fetch users: " . $e->getMessage());
}

// --- 4. FETCH SITE KPI STATS (Fast Query) ---
$siteStats = ['Accepted' => 0, 'Rejected' => 0, 'Conditional' => 0, 'Pending' => 0];
$sqlKPI = "SELECT status, COUNT(*) as cnt FROM site_new_panels_qc GROUP BY status";
$kpiRes = $conn->query($sqlKPI)->fetchAll(PDO::FETCH_ASSOC);

foreach($kpiRes as $krow) {
    $st = $krow['status'] ?: 'Pending';
    if(isset($siteStats[$st])) {
        $siteStats[$st] = $krow['cnt'];
    } else {
        $siteStats['Pending'] += $krow['cnt'];
    }
}

// --- 5. FETCH MAIN DATA (JOIN STRATEGY) ---
// We join QC Inspections with Site Panels using the new column. 
// Note: We do NOT join 'users' here because it's in a different DB.
$sqlMain = "
    SELECT 
        f.*,
        -- Site Data (Joined)
        s.id AS site_record_id,
        s.status AS site_status,
        s.inspection_date AS site_inspection_date,
        s.created_at AS site_created_at,
        s.check_length AS site_check_length,
        s.inspector_id AS site_inspector_id
    FROM qc_inspections f
    LEFT JOIN site_new_panels_qc s ON s.qc_inspection_id = f.id
    ORDER BY f.production_date DESC, f.id DESC
    LIMIT 2000
";

$rowsData = $conn->query($sqlMain)->fetchAll(PDO::FETCH_ASSOC);

// --- 6. PROCESS ROWS & CALCULATE FACTORY STATS ---
$stats = ['total' => 0, 'sent' => 0, 'stock_ok' => 0, 'rejected' => 0, 'pending' => 0];
$defects = ['Cleaning' => 0, 'Painting' => 0, 'Surface' => 0, 'Cracks' => 0, 'Dimensions' => 0];
$processedRows = [];
$timelineData = [];

// Fetch Standards
$standards = [];
$stdRes = $conn->query("SELECT * FROM product_standards")->fetchAll(PDO::FETCH_ASSOC);
foreach($stdRes as $s) $standards[$s['type_name']] = $s;

foreach ($rowsData as $row) {
    // A. Factory Status Logic
    $isOk = true;
    
    // Visual Checks
    foreach (['check_cleaning', 'check_painting', 'check_surface', 'check_cracks'] as $chk) {
        if (($row[$chk]??'') === 'NOK') { 
            $isOk = false; 
            $key = ucfirst(str_replace('check_', '', $chk));
            if(isset($defects[$key])) $defects[$key]++;
        }
    }
    
    // Check Dimensions
    $std = $standards[$row['product_type'] ?? ''] ?? [];
    $lMin = $std['tol_length_min'] ?? -99; $lMax = $std['tol_length_max'] ?? 99;
    $devL1 = floatval($row['dev_length_1'] ?? 0);
    
    if ($devL1 < $lMin || $devL1 > $lMax) { 
        $isOk = false; 
        $defects['Dimensions']++; 
    }

    // Determine Factory Status
    $st = strtolower($row['status'] ?? '');
    
    if ($st == 'sent' || $st == 'send') {
        $stats['sent']++; $fBadge = 'bg-primary'; $fStatus = 'ارسال شده';
    } elseif ($st == 'rejected' || !$isOk) {
        $stats['rejected']++; $fBadge = 'bg-danger'; $fStatus = 'مردود';
    } elseif ($st == 'not_checked') {
        $stats['pending']++; $fBadge = 'bg-warning text-dark'; $fStatus = 'ناقص';
    } else {
        $stats['stock_ok']++; $fBadge = 'bg-success'; $fStatus = 'موجود';
    }
    $stats['total']++;

    // B. Timeline Data
    $pDate = $row['production_date'] ?? null;
    if($pDate) {
        if (!isset($timelineData[$pDate])) $timelineData[$pDate] = ['prod'=>0, 'sent'=>0];
        $timelineData[$pDate]['prod']++;
        if ($st == 'sent' || $st == 'send') $timelineData[$pDate]['sent']++;
    }

    // C. Site Status Logic (Using JOINed data)
    if (!empty($row['site_record_id'])) {
        // --- Record Exists in Site Table ---
        $sStatus = $row['site_status'] ?? 'Pending';
        
        if ($sStatus == 'Accepted') { $sBadge = 'bg-success'; $sLabel = 'تایید سایت'; }
        elseif ($sStatus == 'Conditional') { $sBadge = 'bg-warning text-dark'; $sLabel = 'مشروط'; }
        else { $sBadge = 'bg-danger'; $sLabel = 'مردود سایت'; }
        
        $row['site_html'] = "<a href='site_qc_form.php?edit_id={$row['site_record_id']}' class='badge $sBadge text-decoration-none'>$sLabel</a>";
        $row['site_sort'] = 2;
        $row['site_date'] = g2j($row['site_inspection_date'] ?? $row['site_created_at'] ?? '');
        $row['site_date_raw'] = $row['site_inspection_date'] ?? $row['site_created_at'] ?? '';
        
        // --- Map Inspector ID to Name from Common DB Array ---
        $inspID = $row['site_inspector_id'];
        $row['site_inspector_display'] = isset($userMap[$inspID]) ? $userMap[$inspID] : '-';

    } else {
        // --- No Record in Site Table ---
        $pType = $row['product_type'] ?? '';
        $pNum = $row['product_number'] ?? '';
        
        if ($fStatus == 'ارسال شده') {
            // Pass qc_ref_id to link properly
            $row['site_html'] = "<a href='site_qc_form.php?qc_ref_id={$row['id']}&pre_type={$pType}&pre_num={$pNum}' class='btn btn-sm btn-outline-primary py-0' style='font-size:0.7rem'>➕ ثبت ورود</a>";
            $row['site_sort'] = 1; 
        } else {
            $row['site_html'] = '<span class="text-muted">-</span>';
            $row['site_sort'] = 0;
        }
        $row['site_date'] = '-';
        $row['site_date_raw'] = '';
        $row['site_inspector_display'] = '-';
    }

    // UI Formatting
    $row['ui_f_status'] = $fStatus;
    $row['ui_f_badge'] = $fBadge;
    $row['ui_date_prod'] = g2j($pDate);
    $row['ui_date_sent'] = g2j($row['sent_date'] ?? '');
    
    $processedRows[] = $row;
}

// Prepare Charts
ksort($timelineData);
$cDates = array_map('g2j', array_keys($timelineData));
$cProd = array_column($timelineData, 'prod');
$cSent = array_column($timelineData, 'sent');

$pageTitle = "داشبورد جامع (کارخانه + سایت)";
require_once __DIR__ . '/header_ghom.php';
?>

<!-- Libs -->
<script src="/ghom/assets/js/jquery-3.7.0.min.js"></script>
<link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
<!-- DataTables Core -->
<link rel="stylesheet" href="/ghom/assets/css/dataTables.bootstrap5.min.css" />
<script src="/ghom/assets/js/jquery.dataTables.min.js"></script>
<script src="/ghom/assets/js/dataTables.bootstrap5.min.js"></script>

<!-- DataTables Buttons -->
<link rel="stylesheet" href="/ghom/assets/css/buttons.bootstrap5.min.css" />
<script src="/ghom/assets/js/dataTables.buttons.min.js"></script>
<script src="/ghom/assets/js/buttons.bootstrap5.min.js"></script>
<script src="/ghom/assets/js/jszip.min.js"></script>
<script src="/ghom/assets/js/pdfmake.min.js"></script>
<script src="/ghom/assets/js/vfs_fonts.js"></script>
<script src="/ghom/assets/js/buttons.html5.min.js"></script>
<script src="/ghom/assets/js/buttons.print.min.js"></script>
<script src="/ghom/assets/js/buttons.colVis.min.js"></script>

<!-- Charts & Datepicker -->
<script src="/ghom/assets/js/apexcharts.min.js"></script>
<script src="/ghom/assets/js/jalalidatepicker.min.js"></script>

<style>
    body { background-color: #f8fafc; font-family: "Samim", sans-serif; }
    .card-kpi { background: #fff; padding: 15px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-right: 4px solid #ccc; }
    .kpi-val { font-size: 1.5rem; font-weight: bold; }
    .kpi-label { font-size: 0.85rem; color: #666; }
    .b-blue { border-color: #0d6efd; } .b-green { border-color: #198754; } .b-red { border-color: #dc3545; }
    .chart-box { background: #fff; padding: 15px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); height: 100%; }
    .chart-title { font-size: 0.95rem; color: #555; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; font-weight: bold; }
    table.dataTable th, table.dataTable td { vertical-align: middle; font-size: 0.85rem; }
    thead input, thead select { width: 100%; padding: 4px; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.75rem; }
</style>

<div class="container-fluid py-4" dir="rtl">

    <!-- KPI ROW -->
    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="card-kpi b-blue"><div class="kpi-label">تولید کل</div><div class="kpi-val text-primary"><?= number_format($stats['total']) ?></div></div></div>
        <div class="col-md-2"><div class="card-kpi b-blue"><div class="kpi-label">ارسال شده</div><div class="kpi-val text-info"><?= number_format($stats['sent']) ?></div></div></div>
        <div class="col-md-2"><div class="card-kpi b-green"><div class="kpi-label">دریافت در سایت</div><div class="kpi-val text-success"><?= number_format($siteStats['Accepted'] + $siteStats['Conditional'] + $siteStats['Rejected']) ?></div></div></div>
        <div class="col-md-2"><div class="card-kpi b-green"><div class="kpi-label">تایید شده سایت</div><div class="kpi-val text-success"><?= number_format($siteStats['Accepted']) ?></div></div></div>
        <div class="col-md-2"><div class="card-kpi b-red"><div class="kpi-label">مردود سایت</div><div class="kpi-val text-danger"><?= number_format($siteStats['Rejected'] + $siteStats['Conditional']) ?></div></div></div>
        <div class="col-md-2"><div class="card-kpi b-red"><div class="kpi-label">منتظر بازرسی</div><div class="kpi-val text-warning"><?= number_format($siteStats['Pending']) ?></div></div></div>
    </div>

    <!-- CHARTS ROW 1: Timeline -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="chart-box">
                <div class="chart-title">روند تولید و ارسال (کارخانه)</div>
                <div id="chart-timeline"></div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW 2: Analysis -->
    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="chart-box">
                <div class="chart-title">وضعیت تولید کارخانه</div>
                <div id="chart-factory-pie"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="chart-box">
                <div class="chart-title">علل خرابی (کارخانه)</div>
                <div id="chart-factory-defects"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="chart-box">
                <div class="chart-title">وضعیت بازرسی سایت</div>
                <div id="chart-site-donut"></div>
            </div>
        </div>
    </div>

    <!-- Main Table -->
    <div class="chart-box">
        <div class="d-flex justify-content-between mb-3">
            <h5 class="m-0">جدول جامع ردیابی (Factory -> Site)</h5>
            <div>
                <a href="qc_import.php" class="btn btn-sm btn-outline-primary">📥 ایمپورت کارخانه</a>
                <a href="qc_form.php" class="btn btn-sm btn-primary">➕ ثبت تولید جدید</a>
            </div>
        </div>
        <div class="table-responsive">
            <table id="mainTable" class="table table-striped table-bordered text-center" style="width:100%">
                <thead class="bg-light">
                    <tr>
                        <th>ID</th>
                        <th>کارخانه</th>
                        <th>وضعیت کارخانه</th>
                        <th>نوع (Type)</th>
                        <th>شماره (No)</th>
                        <th>تاریخ تولید</th>
                        <th>تاریخ ارسال</th>
                        <th>کنترل سایت</th>
                        <th>تاریخ کنترل سایت</th>
                        <th>بازرس سایت</th>
                        <th>انحراف طول</th>
                        <th>بازرس کارخانه</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processedRows as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['factory_name'] ?? 'تبریز') ?></td>
                        <td><span class="badge <?= $r['ui_f_badge'] ?>"><?= $r['ui_f_status'] ?></span></td>
                        <td><?= htmlspecialchars($r['product_type'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['product_number'] ?? '') ?></td>
                        <td data-sort="<?= strtotime($r['production_date'] ?? '') ?>"><?= $r['ui_date_prod'] ?></td>
                        <td data-sort="<?= strtotime($r['sent_date'] ?? '') ?>"><?= $r['ui_date_sent'] ?></td>
                        
                        <td data-order="<?= $r['site_sort'] ?>">
                            <?= $r['site_html'] ?>
                        </td>

                        <td data-sort="<?= strtotime($r['site_date_raw'] ?? '') ?>">
                            <?= $r['site_date'] ?>
                        </td>

                        <td><?= htmlspecialchars($r['site_inspector_display']) ?></td>

                        <td class="<?= (abs(floatval($r['dev_length_1']??0))>2)?'text-danger fw-bold':'' ?>">
                            <?= htmlspecialchars($r['dev_length_1'] ?? '0') ?>
                        </td>
                        
                        <td><?= htmlspecialchars($r['inspector_name'] ?? '') ?></td>
                        <td>
                            <a href="qc_edit.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-light py-0">📝</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
$(document).ready(function() {

    // --- 1. APEXCHARTS CONFIG ---
    
    // Timeline Chart
    var optT = {
        series: [{name:'تولید', data:<?= json_encode($cProd) ?>}, {name:'ارسال', data:<?= json_encode($cSent) ?>}],
        chart: {
            type:'area', 
            height:250, 
            fontFamily:'Samim', 
            toolbar:{ show: true, export: { csv: { filename: 'Factory_Timeline' }, png: { filename: 'Factory_Timeline' } } }
        },
        colors: ['#adb5bd', '#0d6efd'],
        xaxis: {categories:<?= json_encode($cDates) ?>}
    };
    new ApexCharts(document.querySelector("#chart-timeline"), optT).render();

    // Factory Status Pie
    var optFP = {
        series: [<?= $stats['sent'] ?>, <?= $stats['stock_ok'] ?>, <?= $stats['rejected'] ?>, <?= $stats['pending'] ?>],
        chart: {
            type:'pie', 
            height:250, 
            fontFamily:'Samim',
            toolbar:{ show: true }
        },
        labels: ['ارسال شده', 'موجود', 'مردود', 'ناقص'],
        colors: ['#0d6efd', '#198754', '#dc3545', '#ffc107'],
        legend: {position:'bottom'}
    };
    new ApexCharts(document.querySelector("#chart-factory-pie"), optFP).render();

    // Factory Defects Bar
    var optFB = {
        series: [{name:'تعداد', data:[<?= $defects['Cleaning']?>, <?= $defects['Painting']?>, <?= $defects['Surface']?>, <?= $defects['Cracks']?>, <?= $defects['Dimensions']?>]}],
        chart: {
            type:'bar', 
            height:250, 
            fontFamily:'Samim', 
            toolbar:{ show: true }
        },
        xaxis: {categories:['نظافت', 'رنگ', 'سطح', 'ترک', 'ابعادی']},
        colors: ['#dc3545'],
        plotOptions: {bar: {horizontal: true, borderRadius: 4}}
    };
    new ApexCharts(document.querySelector("#chart-factory-defects"), optFB).render();

    // Site Status Donut
    var optS = {
        series: [<?= $siteStats['Accepted'] ?>, <?= $siteStats['Conditional'] ?>, <?= $siteStats['Rejected'] ?>, <?= $siteStats['Pending'] ?>],
        chart: {
            type:'donut', 
            height:250, 
            fontFamily:'Samim',
            toolbar:{ show: true }
        },
        labels: ['تایید شده', 'مشروط', 'مردود', 'منتظر بازرسی'],
        colors: ['#198754', '#ffc107', '#dc3545', '#0d6efd'],
        legend: {position:'bottom'}
    };
    new ApexCharts(document.querySelector("#chart-site-donut"), optS).render();


    // --- 2. DATATABLES CONFIG ---
    
    var exportConfig = {
        columns: ':not(:last-child)', 
        format: {
            header: function (data) {
                return data.split('<')[0].trim();
            }
        }
    };

    var table = $('#mainTable').DataTable({
        stateSave: true,
        dom: 'Bfrtip',
        buttons: [
            { 
                extend: 'copy', 
                text: '📋 کپی', 
                className: 'btn btn-secondary btn-sm',
                exportOptions: exportConfig
            },
            { 
                extend: 'excelHtml5', 
                text: '📥 اکسل', 
                className: 'btn btn-success btn-sm',
                title: 'Factory_QC_Report_' + new Date().toISOString().slice(0,10),
                autoFilter: true,
                exportOptions: exportConfig
            },
            { 
                extend: 'csvHtml5', 
                text: '📄 CSV', 
                className: 'btn btn-info btn-sm',
                charset: 'utf-8', 
                bom: true,
                exportOptions: exportConfig
            },
            { 
                extend: 'print', 
                text: '🖨️ چاپ / PDF', 
                className: 'btn btn-primary btn-sm',
                exportOptions: exportConfig,
                autoPrint: true,
                title: 'گزارش کنترل کیفی کارخانه - ' + new Date().toLocaleDateString('fa-IR'),
                customize: function (win) {
                    $(win.document.body).css('direction', 'rtl').css('font-family', 'Samim, Tahoma');
                    $(win.document.body).find('h1').css('text-align', 'center').css('font-size', '18px');
                    $(win.document.body).find('table').addClass('compact').css('font-size', 'inherit');
                }
            }
        ],
        order: [[ 5, "desc" ]], // Sort by Production Date (index 5)
        pageLength: 25,
        language: { 
            search: "جستجو کلی:", 
            paginate: { next: ">", previous: "<" },
            info: "نمایش _START_ تا _END_ از _TOTAL_ رکورد",
            emptyTable: "داده‌ای موجود نیست"
        },
        
        initComplete: function () {
            var api = this.api();
            
            api.columns().every(function (index) {
                var column = this;
                var title = $(column.header()).text().trim();
                
                // Skip ID and Operations columns
                if(title === 'عملیات' || title === 'ID') return;

                var filterHtml = '';

                // Column 1: Factory Name
                if(index === 1) { 
                    filterHtml = '<br><select><option value="">همه</option><option value="تبریز">منتخب عمران (تبریز)</option><option value="میبد">موزاییک میبد یزد</option></select>';
                } 
                // Column 2: Factory Status
                else if(index === 2) { 
                    filterHtml = '<br><select><option value="">همه</option><option value="ارسال شده">ارسال شده</option><option value="موجود">موجود</option><option value="مردود">مردود</option><option value="ناقص">ناقص</option></select>';
                } 
                // Column 5: Production Date (تاریخ تولید)
                else if(index === 5) {
                    filterHtml = '<br><input type="text" data-jdp placeholder="تاریخ..." />';
                }
                // Column 6: Sent Date (تاریخ ارسال)
                else if(index === 6) {
                    filterHtml = '<br><input type="text" data-jdp placeholder="تاریخ..." />';
                }
                // Column 7: Site QC Status (کنترل سایت)
                else if(index === 7) {
                    filterHtml = '<br><select><option value="">همه</option><option value="تایید سایت">تایید سایت</option><option value="مشروط">مشروط</option><option value="مردود سایت">مردود</option><option value="ثبت ورود">منتظر بازرسی</option></select>';
                }
                // Column 8: Site Inspection Date (تاریخ کنترل سایت)
                else if(index === 8) {
                    filterHtml = '<br><input type="text" data-jdp placeholder="تاریخ..." />';
                }
                // Other columns: Text input
                else {
                    filterHtml = '<br><input type="text" placeholder="🔍" />';
                }

                // Append filter element
                var filterElem = $(filterHtml).appendTo($(column.header()));
                
                // Bind events
                if(filterElem.is('select')) {
                    filterElem.on('change', function(){
                        column.search($(this).val()).draw();
                    });
                } else {
                    filterElem.on('keyup change', function(){
                        var searchValue = this.value.trim();
                        
                        // For date columns, normalize the search
                        if(index === 5 || index === 6 || index === 8) {
                            if(searchValue) {
                                // Normalize date: 1404/9/4 -> match both 1404/9/4 and 1404/09/04
                                var parts = searchValue.split('/');
                                if(parts.length >= 2) {
                                    var year = parts[0];
                                    var month = parts[1] ? parts[1].padStart(2, '0') : '';
                                    var day = parts[2] ? parts[2].padStart(2, '0') : '';
                                    
                                    // Build regex pattern
                                    var pattern = year;
                                    if(month) pattern += '\\/' + '0?' + parseInt(month);
                                    if(day) pattern += '\\/' + '0?' + parseInt(day);
                                    
                                    column.search(pattern, true, false).draw();
                                } else {
                                    column.search(searchValue, false, false).draw();
                                }
                            } else {
                                column.search('').draw();
                            }
                        } else {
                            column.search(searchValue).draw();
                        }
                    });
                }

                // Restore saved state
                var state = api.state.loaded();
                if(state && state.columns[index]) {
                    var savedValue = state.columns[index].search.search;
                    filterElem.val(savedValue);
                }
            });

            // Initialize Jalali Date Pickers AFTER DataTables creates all inputs
            setTimeout(function() {
                // Find all date inputs with data-jdp attribute
                $('input[data-jdp]').each(function() {
                    jalaliDatepicker.startWatch({
                        el: this,
                        changeMonthRotate: false,
                        changeYearRotate: false
                    });
                });
            }, 200);
        }
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>