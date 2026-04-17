<?php
// ===================================================================
// WORKSHOP QC REPORT (WITH PIE & TYPE CHARTS)
// ===================================================================

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();
if (!isLoggedIn()) { header('Location: /login.php?msg=login_required'); exit(); }
if (!in_array($_SESSION['role'], ['admin', 'superuser', 'cat', 'car', 'coa', 'crs'])) {
    http_response_code(403); require 'Access_Denied.php'; exit;
}

$conn = getProjectDBConnection("ghom");
$commonConn = getCommonDBConnection(); 

$pageTitle = "گزارش جامع سایت (کارگاه)";
require_once __DIR__ . '/header_ghom.php';

$message = '';
$error = '';
$user_can_manage = in_array($_SESSION['role'], ['admin', 'superuser']);

// --- 1. HANDLE DELETE ---
if ($user_can_manage && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_qc') {
    try {
        $qc_id = filter_input(INPUT_POST, 'qc_id', FILTER_VALIDATE_INT);
        if ($qc_id) {
            $stmt = $conn->prepare("DELETE FROM workshop_qc WHERE qc_id = ?");
            $stmt->execute([$qc_id]);
            $message = "رکورد با موفقیت حذف شد.";
        }
    } catch (Exception $e) { $error = "خطا در حذف: " . $e->getMessage(); }
}

// --- 2. FETCH DATA ---
try {
    $sql = "SELECT * FROM workshop_qc ORDER BY qc_date DESC, created_at DESC LIMIT 2500";
    $all_qc_data = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // User Names Mapping
    $operator_ids = array_unique(array_filter(array_column($all_qc_data, 'qc_operator_id')));
    $users_map = [];
    if (!empty($operator_ids)) {
        $placeholders = implode(',', array_fill(0, count($operator_ids), '?'));
        $uStmt = $commonConn->prepare("SELECT id, first_name, last_name, username FROM users WHERE id IN ($placeholders)");
        $uStmt->execute(array_values($operator_ids));
        while ($u = $uStmt->fetch(PDO::FETCH_ASSOC)) {
            $name = trim(($u['first_name']??'') . ' ' . ($u['last_name']??''));
            $users_map[$u['id']] = $name ?: $u['username'];
        }
    }

    $zones_list = $conn->query("SELECT DISTINCT zone_name FROM workshop_qc WHERE zone_name IS NOT NULL ORDER BY zone_name")->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) { $error = "DB Error: " . $e->getMessage(); }

// --- 3. STATS PROCESSING ---
$stats = [
    'total' => count($all_qc_data), 'usable' => 0, 'in_repair' => 0, 'rejected' => 0,
    'by_zone' => [], 
    'by_type' => [], // Now stores array of statuses
    'trend' => []
];

foreach ($all_qc_data as $row) {
    // Status
    $st = strtolower(trim($row['final_status'] ?? ''));
    if ($st == 'usable') { $stats['usable']++; $stTxt='قابل استفاده'; }
    elseif ($st == 'in_repair') { $stats['in_repair']++; $stTxt='در حال تعمیر'; }
    elseif ($st == 'rejected') { $stats['rejected']++; $stTxt='رد شده'; }
    else $stTxt='نامشخص';

    // Type Extraction
    $eid = $row['element_id'] ?? '';
    $parts = preg_split('/[\s\-\*]+/', $eid);
    $type = isset($parts[0]) ? strtoupper($parts[0]) : 'نامشخص';
    
    // Init Type Stats
    if(!isset($stats['by_type'][$type])) $stats['by_type'][$type] = ['usable'=>0, 'repair'=>0, 'reject'=>0];
    
    // Init Zone Stats
    $z = $row['zone_name'] ?: 'نامشخص';
    if(!isset($stats['by_zone'][$z])) $stats['by_zone'][$z] = ['usable'=>0, 'repair'=>0, 'reject'=>0];

    // Increment Counts
    if ($stTxt == 'قابل استفاده') {
        $stats['by_type'][$type]['usable']++;
        $stats['by_zone'][$z]['usable']++;
    } elseif ($stTxt == 'در حال تعمیر') {
        $stats['by_type'][$type]['repair']++;
        $stats['by_zone'][$z]['repair']++;
    } elseif ($stTxt == 'رد شده') {
        $stats['by_type'][$type]['reject']++;
        $stats['by_zone'][$z]['reject']++;
    }

    // Trend
    if(!empty($row['qc_date'])) {
        $d = date('Y-m-d', strtotime($row['qc_date']));
        if(!isset($stats['trend'][$d])) $stats['trend'][$d] = 0;
        $stats['trend'][$d]++;
    }
}
ksort($stats['trend']);
ksort($stats['by_type']); // Sort types alphabetically

// Helper for Date
function g2j($gDate) {
    if (!$gDate) return '-';
    $t = strtotime($gDate);
    $j = gregorian_to_jalali(date('Y',$t),date('m',$t),date('d',$t));
    return implode('/',$j);
}
?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- DataTables Core -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- DataTables Buttons -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" />
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>

<!-- REQUIRED FOR EXCEL EXPORT -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<!-- REQUIRED FOR PDF EXPORT -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

<!-- Button Functionalities -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

<!-- Charts & Datepicker -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>
<script src="/ghom/assets/js/jalalidatepicker.min.js"></script>

<style>
    body { background-color: #f8fafc; font-family: "Samim", sans-serif; }
    
    /* Stats & Charts */
    .card-kpi { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-right: 4px solid #ccc; text-align: center; }
    .kpi-val { font-size: 1.8rem; font-weight: bold; }
    .kpi-label { font-size: 0.9rem; color: #64748b; margin-bottom: 5px; }
    .b-green { border-color: #10b981; } .b-yellow { border-color: #f59e0b; } .b-red { border-color: #ef4444; } .b-blue { border-color: #3b82f6; }
    
    .chart-box { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; height: 100%; }
    .chart-title { font-weight: bold; color: #334155; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }

    /* DataTables */
    .table-responsive { background: #fff; padding: 15px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    thead input, thead select { width: 100%; padding: 4px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.75rem; background: #fff; }
    table.dataTable th, table.dataTable td { vertical-align: middle; font-size: 0.85rem; text-align: center; }
    
    .badge-ok { background: #dcfce7; color: #166534; padding: 5px 10px; border-radius: 20px; }
    .badge-repair { background: #fef3c7; color: #92400e; padding: 5px 10px; border-radius: 20px; }
    .badge-reject { background: #fee2e2; color: #991b1b; padding: 5px 10px; border-radius: 20px; }
</style>

<div class="container-fluid py-4" dir="rtl">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-primary fw-bold">📊 <?= $pageTitle ?></h3>
        <a href="workshop_qc.php" class="btn btn-primary shadow-sm">➕ ثبت بازرسی جدید</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <!-- 1. KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card-kpi b-blue"><div class="kpi-label">کل بازرسی‌ها</div><div class="kpi-val text-primary"><?= number_format($stats['total']) ?></div></div></div>
        <div class="col-md-3"><div class="card-kpi b-green"><div class="kpi-label">قابل استفاده</div><div class="kpi-val text-success"><?= number_format($stats['usable']) ?></div></div></div>
        <div class="col-md-3"><div class="card-kpi b-yellow"><div class="kpi-label">در حال تعمیر</div><div class="kpi-val text-warning"><?= number_format($stats['in_repair']) ?></div></div></div>
        <div class="col-md-3"><div class="card-kpi b-red"><div class="kpi-label">رد شده</div><div class="kpi-val text-danger"><?= number_format($stats['rejected']) ?></div></div></div>
    </div>

    <!-- 2. Charts Row 1 -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="chart-box">
                <div class="chart-title">📈 روند بازرسی روزانه</div>
                <div id="trendChart"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="chart-box">
                <div class="chart-title">🍰 وضعیت کلی پنل‌ها</div>
                <div id="pieChart"></div>
            </div>
        </div>
    </div>

    <!-- 3. Charts Row 2 -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="chart-box">
                <div class="chart-title">🏗️ وضعیت به تفکیک نوع پنل (Type)</div>
                <div id="typeChart"></div>
            </div>
        </div>
    </div>
    
    <!-- 4. Charts Row 3 -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="chart-box">
                <div class="chart-title">📍 وضعیت به تفکیک زون (Zone)</div>
                <div id="zoneChart"></div>
            </div>
        </div>
    </div>

    <!-- 5. Advanced Table -->
    <div class="table-responsive">
        <table id="workshopTable" class="table table-striped table-bordered" style="width:100%">
            <thead class="bg-light">
                <tr>
                    <th>ID</th>
                    <th>کارخانه</th>
                    <th>کد پنل</th>
                    <th>زون</th>
                    <th>تاریخ QC</th>
                    <th>بازرس</th>
                    <th>وضعیت نهایی</th>
                    <th>شرح آسیب</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_qc_data as $row): 
                    $st = strtolower($row['final_status'] ?? '');
                    if ($st == 'usable') { $badge='badge-ok'; $label='قابل استفاده'; }
                    elseif ($st == 'in_repair') { $badge='badge-repair'; $label='در حال تعمیر'; }
                    elseif ($st == 'rejected') { $badge='badge-reject'; $label='رد شده'; }
                    else { $badge='bg-secondary text-white rounded-pill px-2'; $label='نامشخص'; }
                    
                    $inspector = $users_map[$row['qc_operator_id'] ?? 0] ?? ($row['qc_operator_id'] ?? '-');
                    $factory = $row['factory_name'] ?? '-';
                    $element_id = $row['element_id'] ?? '';
                    $zone_name = $row['zone_name'] ?: '-';
                    $date_ts = !empty($row['qc_date']) ? strtotime($row['qc_date']) : 0;
                    $damage_desc = $row['damage_description'] ?? '';
                ?>
                <tr>
                    <td><?= $row['qc_id'] ?></td>
                    <td><?= htmlspecialchars($factory) ?></td>
                    <td class="fw-bold" dir="ltr"><?= htmlspecialchars($element_id) ?></td>
                    <td><?= htmlspecialchars($zone_name) ?></td>
                    <td data-sort="<?= $date_ts ?>"><?= g2j($row['qc_date'] ?? '') ?></td>
                    <td><?= htmlspecialchars($inspector) ?></td>
                    <td><span class="<?= $badge ?>"><?= $label ?></span></td>
                    <td><small class="text-muted"><?= htmlspecialchars(mb_substr($damage_desc, 0, 30)) ?></small></td>
                    <td>
                        <a href="workshop_qc.php?edit_id=<?= $row['qc_id'] ?>" class="btn btn-sm btn-warning py-0 shadow-sm">✏️</a>
                        <?php if ($user_can_manage): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('حذف؟');">
                            <input type="hidden" name="action" value="delete_qc">
                            <input type="hidden" name="qc_id" value="<?= $row['qc_id'] ?>">
                            <button class="btn btn-sm btn-danger py-0 shadow-sm">🗑️</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
$(document).ready(function() {
    
    // --- 1. APEXCHARTS CONFIGURATION ---
    var commonColors = ['#10b981', '#f59e0b', '#ef4444']; 

    // Trend Chart
    new ApexCharts(document.querySelector("#trendChart"), {
        series: [{ name: 'تعداد بازرسی', data: <?= json_encode(array_values($stats['trend'])) ?> }],
        chart: { type: 'area', height: 300, fontFamily: 'Samim', toolbar: { show: true } },
        colors: ['#6366f1'],
        fill: { type: 'gradient', gradient: { opacityFrom: 0.6, opacityTo: 0.1 } },
        xaxis: { categories: <?= json_encode(array_map('g2j', array_keys($stats['trend']))) ?> }
    }).render();

    // Pie Chart
    new ApexCharts(document.querySelector("#pieChart"), {
        series: [<?= $stats['usable'] ?>, <?= $stats['in_repair'] ?>, <?= $stats['rejected'] ?>],
        chart: { type: 'pie', height: 300, fontFamily: 'Samim', toolbar: { show: true } },
        labels: ['قابل استفاده', 'در حال تعمیر', 'رد شده'],
        colors: commonColors,
        legend: { position: 'bottom' }
    }).render();

    // Type Chart
    new ApexCharts(document.querySelector("#typeChart"), {
        series: [
            { name: 'سالم', data: <?= json_encode(array_column($stats['by_type'], 'usable')) ?> },
            { name: 'تعمیر', data: <?= json_encode(array_column($stats['by_type'], 'repair')) ?> },
            { name: 'مردود', data: <?= json_encode(array_column($stats['by_type'], 'reject')) ?> }
        ],
        chart: { type: 'bar', height: 350, fontFamily: 'Samim', stacked: true, toolbar: { show: true } },
        colors: commonColors,
        xaxis: { categories: <?= json_encode(array_keys($stats['by_type'])) ?> },
        plotOptions: { bar: { borderRadius: 4 } }
    }).render();

    // Zone Chart
    new ApexCharts(document.querySelector("#zoneChart"), {
        series: [
            { name: 'سالم', data: <?= json_encode(array_column($stats['by_zone'], 'usable')) ?> },
            { name: 'تعمیر', data: <?= json_encode(array_column($stats['by_zone'], 'repair')) ?> },
            { name: 'مردود', data: <?= json_encode(array_column($stats['by_zone'], 'reject')) ?> }
        ],
        chart: { type: 'bar', height: 350, fontFamily: 'Samim', stacked: true, toolbar: { show: true } },
        colors: commonColors,
        xaxis: { categories: <?= json_encode(array_keys($stats['by_zone'])) ?> },
        plotOptions: { bar: { borderRadius: 4 } }
    }).render();


    // --- 2. DATATABLES CONFIGURATION ---
    
    // Configuration to Clean Headers on Export (Removes the Dropdowns from the CSV/Excel file)
     var exportConfig = {
        columns: ':not(:last-child)', 
        format: {
            header: function (data, columnIdx) {
                return data.split('<')[0].trim();
            }
        }
    };

    $('#workshopTable').DataTable({
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
                text: '📥 دانلود اکسل', 
                className: 'btn btn-success btn-sm',
                title: 'QC_Report_' + new Date().toISOString().slice(0,10),
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
                text: '🖨️ چاپ / PDF', // Renamed to indicate it handles PDF too
                className: 'btn btn-primary btn-sm',
                exportOptions: exportConfig,
                autoPrint: true, // Opens print dialog automatically
                title: 'گزارش جامع کنترل کیفی - ' + new Date().toLocaleDateString('fa-IR'),
                customize: function (win) {
                    // Force RTL and Font for the Print Window
                    $(win.document.body).css('direction', 'rtl').css('font-family', 'Samim, Tahoma');
                    $(win.document.body).find('table').addClass('compact').css('font-size', 'inherit');
                    
                    // Center the Title
                    $(win.document.body).find('h1').css('text-align', 'center').css('font-size', '18px').css('margin-bottom', '20px');
                }
            }
        ],
        order: [[ 4, "desc" ]],
        pageLength: 25,
        language: { 
            search: "جستجو کلی:", 
            paginate: { next: ">", previous: "<" },
            info: "نمایش _START_ تا _END_ از _TOTAL_ رکورد",
            emptyTable: "داده‌ای موجود نیست"
        },
        
        initComplete: function () {
            this.api().columns().every(function (index) {
                var column = this;
                var title = $(column.header()).text().trim(); // Get Clean Title
                if(title === 'عملیات' || title === 'ID') return;

                // Create Filter Element (Dropdown or Input)
                var filterHtml = '';

                // Factory Filter (Index 1)
                if(index === 1) {
                    filterHtml = '<br><select><option value="">همه</option><option value="تبریز">منتخب عمران (تبریز)</option><option value="میبد">موزاییک میبد یزد</option></select>';
                }
                // Zone Filter (Index 3)
                else if(index === 3) {
                    var zones = <?= json_encode($zones_list) ?>;
                    var opts = '<option value="">همه</option>';
                    zones.forEach(z => opts += '<option value="'+z+'">'+z+'</option>');
                    filterHtml = '<br><select>'+opts+'</select>';
                }
                // Date Filter (Index 4)
                else if(index === 4) {
                    filterHtml = '<br><input type="text" data-jdp placeholder="تاریخ..." />';
                }
                // Status Filter (Index 6)
                else if(index === 6) {
                    filterHtml = '<br><select><option value="">همه</option><option value="قابل استفاده">قابل استفاده</option><option value="در حال تعمیر">در حال تعمیر</option><option value="رد شده">رد شده</option></select>';
                }
                else {
                    filterHtml = '<br><input type="text" placeholder="🔍" />';
                }

                // Append the filter to the header
                var filterElem = $(filterHtml).appendTo($(column.header()));

                // Add Event Listeners
                if(filterElem.is('select')) {
                    filterElem.on('change', function(){ column.search($(this).val()).draw(); });
                } else {
                    filterElem.on('keyup change', function(){ column.search(this.value).draw(); });
                }
                
                // Restore State
                var state = this.state.loaded();
                if(state) {
                    var val = state.columns[index].search.search;
                    if(filterElem.is('select')) filterElem.val(val);
                    else filterElem.val(val);
                }
            });
            jalaliDatepicker.startWatch();
        }
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>