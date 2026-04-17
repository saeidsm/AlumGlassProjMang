<?php
// daily_report_print.php - FIXED PATH READING FROM DB
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

$pdo = getProjectDBConnection('ghom');
$report_id = $_GET['id'] ?? null;
if (!$report_id) die('شناسه گزارش یافت نشد');

// --- 1. Fetch Report Data ---
$stmt = $pdo->prepare("SELECT * FROM daily_reports WHERE id = ?");
$stmt->execute([$report_id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$report) die('گزارش یافت نشد');

if (!empty($report['report_date'])) {
    list($y, $m, $d) = explode('-', $report['report_date']);
    $j = gregorian_to_jalali((int)$y, (int)$m, (int)$d);
    $report['report_date_jalali'] = $j[0] . '/' . sprintf('%02d', $j[1]) . '/' . sprintf('%02d', $j[2]);
}

// --- 2. Fetch Sub-Tables ---
$personnel = $pdo->prepare("SELECT * FROM daily_report_personnel WHERE report_id = ? ORDER BY id ASC");
$personnel->execute([$report_id]);
$personnel = $personnel->fetchAll(PDO::FETCH_ASSOC);

$machinery = $pdo->prepare("SELECT * FROM daily_report_machinery WHERE report_id = ? ORDER BY id ASC");
$machinery->execute([$report_id]);
$machinery = $machinery->fetchAll(PDO::FETCH_ASSOC);

$materials = $pdo->prepare("SELECT * FROM daily_report_materials WHERE report_id = ? ORDER BY id ASC");
$materials->execute([$report_id]);
$materials = $materials->fetchAll(PDO::FETCH_ASSOC);

$activities = $pdo->prepare("SELECT dra.*, pa.name as activity_name FROM daily_report_activities dra LEFT JOIN project_activities pa ON dra.activity_id = pa.id WHERE report_id = ? ORDER BY dra.id ASC");
$activities->execute([$report_id]);
$activities = $activities->fetchAll(PDO::FETCH_ASSOC);

$misc_items = $pdo->prepare("SELECT * FROM daily_report_misc WHERE report_id = ? ORDER BY id ASC");
$misc_items->execute([$report_id]);
$misc_items = $misc_items->fetchAll(PDO::FETCH_ASSOC);

// --- 3. LOAD SETTINGS (FIXED LOGIC) ---
$user_id = $_SESSION['user_id'] ?? 0;

// Fetch JSON AND specific logo columns
$stmt = $pdo->prepare("SELECT settings_json, logo_right, logo_middle, logo_left FROM print_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$db_row = $stmt->fetch(PDO::FETCH_ASSOC);

$default_settings = [
    'global' => ['fontFamily' => 'Samim', 'fontSize' => 9, 'pageMargin' => 10, 'textColor' => '#000000', 'headerColor' => '#343a40', 'footerNote' => ''],
    'logos' => ['right' => '', 'middle' => '', 'left' => ''],
    'footer' => ['height' => 60, 'signatureSpacing' => 5],
    'personnel' => ['rowHeight' => 30, 'columns' => [5, 35, 15, 45]],
    'machinery' => ['rowHeight' => 30, 'columns' => [5, 35, 10, 10, 40]],
    'materials_in' => ['rowHeight' => 30, 'columns' => [40, 15, 15, 30]],
    'materials_out' => ['rowHeight' => 30, 'columns' => [40, 15, 15, 30]],
    'activities' => ['rowHeight' => 35, 'columns' => [4, 18, 8, 6, 5, 5, 6, 6, 5, 7, 7, 23]]
];

// Decode JSON
$json_settings = ($db_row && !empty($db_row['settings_json'])) ? json_decode($db_row['settings_json'], true) : [];
if (!is_array($json_settings)) $json_settings = [];
$settings = array_replace_recursive($default_settings, $json_settings);

// --- CRITICAL FIX: CLEAN PATHS FROM DB ---
// This function removes '/ghom/' from the start of the string to make it relative
function cleanPath($path) {
    if (empty($path)) return '';
    // Remove /ghom/ or /ghom from the start
    $path = preg_replace('#^/ghom/#', '', $path); 
    $path = preg_replace('#^/ghom#', '', $path);
    // Remove leading slash
    return ltrim($path, '/');
}

// Force DB Columns to overwrite JSON if they exist
if (!empty($db_row['logo_right'])) $settings['logos']['right'] = cleanPath($db_row['logo_right']);
if (!empty($db_row['logo_middle'])) $settings['logos']['middle'] = cleanPath($db_row['logo_middle']);
if (!empty($db_row['logo_left'])) $settings['logos']['left'] = cleanPath($db_row['logo_left']);

$settingsJson = json_encode($settings);

// Helper to verify existence and add cache buster
function get_logo_src($path) {
    if (empty($path)) return '';
    // Now we check existence relative to this file
    if (file_exists(__DIR__ . '/' . $path)) {
        return $path . '?v=' . time();
    }
    // If not found, maybe return empty or keep path to see error in console
    return ''; 
}

$logo_right_src = get_logo_src($settings['logos']['right']);
$logo_middle_src = get_logo_src($settings['logos']['middle']);
$logo_left_src = get_logo_src($settings['logos']['left']);

// Fallback for Right Logo (Contractor Name)
if (empty($logo_right_src)) {
    $clean_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $report['contractor_fa_name']);
    $fallback = 'uploads/contractor_logos/' . $clean_name . '.png';
    if (file_exists(__DIR__ . '/' . $fallback)) {
        $logo_right_src = $fallback;
    } else {
        $logo_right_src = 'assets/images/logo-right.png';
    }
}
if (empty($logo_left_src)) {
    $logo_left_src = 'assets/images/logo-left.png';
}
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
        body { background-color: #f0f2f5; margin: 0; padding: 20px; }
        .data-rejected { text-decoration: line-through; color: #dc3545 !important; opacity: 0.7; }
        .action-buttons { position: fixed; bottom: 20px; left: 20px; z-index: 1050; display: flex; flex-direction: column; gap: 10px; }
        .fab-button { width: 56px; height: 56px; border-radius: 50%; font-size: 1.4rem; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        .main-print-table { width: 100%; border-collapse: collapse; background: white; }
        .main-print-table thead { display: table-header-group; }
        .logo-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 10px; width: 100%; }
        .header-col { flex: 1; display: flex; justify-content: center; align-items: center; }
        .header-col img { max-height: 60px; object-fit: contain; }
        .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 10px; }
        .data-table th, .data-table td { border: 1px solid #ccc; text-align: center; padding: 4px; font-size: 0.95em; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .data-table th { background-color: #e9ecef; font-weight: bold; }
        .text-start { text-align: right !important; }
        .text-wrap { white-space: normal !important; }
        .signature-box { width: 30%; text-align: center; cursor: pointer; border: 1px dashed transparent; }
        .signature-box:hover { border-color: #0d6efd; background: #f0f8ff; }
        .signature-box img { max-height: 60px; }
        @media print { 
            @page { size: A4; margin: 8mm; }
            body { padding: 0; margin: 0; background: white; }
            .no-print { display: none !important; }
            .data-rejected { -webkit-text-decoration: line-through; text-decoration: line-through; }
        }
    </style>
    <style id="dynamic-styles"></style>
</head>
<body>
    <div class="action-buttons no-print">
        <button class="btn btn-primary fab-button" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
        <button class="btn btn-info fab-button" data-bs-toggle="modal" data-bs-target="#settingsModal"><i class="fa-solid fa-gear"></i></button>
    </div>

    <table class="main-print-table">
        <thead>
            <tr>
                <td>
                    <header class="logo-header">
                        <div class="header-col right">
                            <img src="<?php echo $logo_right_src; ?>" onerror="this.style.display='none'">
                        </div>
                        <div class="header-col" style="flex:2; flex-direction:column;">
                            <?php if($logo_middle_src): ?><img src="<?php echo $logo_middle_src; ?>" style="margin-bottom:5px;"><?php endif; ?>
                            <h2 style="font-size:1.4em; font-weight:bold; margin:0;">گزارش روزانه عملیات اجرایی</h2>
                            <p style="margin:5px 0 0 0; font-size:0.9em;">شماره: <?php echo $report_id; ?></p>
                        </div>
                        <div class="header-col left">
                            <img src="<?php echo $logo_left_src; ?>" onerror="this.style.display='none'">
                        </div>
                    </header>
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <main id="printContent" class="print-content-wrapper mt-3">
                        <!-- Info -->
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid #ddd; padding:10px; margin-bottom:15px;">
                            <span><strong>تاریخ:</strong> <?php echo $report['report_date_jalali']; ?></span>
                            <span><strong>پیمانکار:</strong> <?php echo $report['contractor_fa_name']; ?></span>
                            <span><strong>بلوک:</strong> <?php echo $report['block_name']; ?></span>
                            <span><strong>هوا:</strong> <?php echo implode(',', json_decode($report['weather_list'] ?? '[]', true)); ?></span>
                        </div>

                        <!-- Personnel -->
                        <div class="mb-3">
                            <h6 class="p-1 bg-light border fw-bold">۱. نیروی انسانی</h6>
                            <table class="data-table" id="personnel-table">
                                <colgroup><col><col><col><col></colgroup>
                                <thead><tr><th>#</th><th>سمت</th><th>تعداد</th><th>توضیحات</th></tr></thead>
                                <tbody>
                                    <?php foreach ($personnel as $i => $p): 
                                        $cls = !empty($p['consultant_comment']) ? 'data-rejected' : ''; ?>
                                        <tr>
                                            <td><?= $i+1 ?></td>
                                            <td class="text-start <?= $cls ?>"><?= $p['role_name'] ?></td>
                                            <td class="<?= $cls ?>"><?= $p['count'] ?></td>
                                            <td class="text-start text-wrap"><?= $p['consultant_comment'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Machinery -->
                        <div class="mb-3">
                            <h6 class="p-1 bg-light border fw-bold">۲. ماشین آلات</h6>
                            <table class="data-table" id="machinery-table">
                                <colgroup><col><col><col><col><col></colgroup>
                                <thead><tr><th>#</th><th>دستگاه</th><th>کل</th><th>فعال</th><th>توضیحات</th></tr></thead>
                                <tbody>
                                    <?php foreach ($machinery as $i => $m): 
                                        $cls = !empty($m['consultant_comment']) ? 'data-rejected' : ''; ?>
                                        <tr>
                                            <td><?= $i+1 ?></td>
                                            <td class="text-start <?= $cls ?>"><?= $m['machine_name'] ?></td>
                                            <td class="<?= $cls ?>"><?= $m['total_count'] ?></td>
                                            <td class="<?= $cls ?>"><?= $m['active_count'] ?></td>
                                            <td class="text-start text-wrap"><?= $m['consultant_comment'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Activities -->
                        <div class="mb-3">
                            <h6 class="p-1 bg-light border fw-bold">۳. فعالیت های اجرایی</h6>
                            <table class="data-table" id="activities-table">
                                <colgroup><col><col><col><col><col><col><col><col><col><col><col><col></colgroup>
                                <thead>
                                    <tr>
                                        <th rowspan="2">#</th><th rowspan="2">شرح</th><th rowspan="2">موقعیت</th><th rowspan="2">زون</th><th rowspan="2">طبقه</th><th rowspan="2">واحد</th>
                                        <th colspan="3">عملکرد روزانه</th><th colspan="2">تجمعی</th><th rowspan="2">توضیحات</th>
                                    </tr>
                                    <tr><th>تعداد</th><th>متر</th><th>نفر</th><th>نصب</th><th>ریجکت</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $i => $a): 
                                        $cls = !empty($a['consultant_comment']) ? 'data-rejected' : ''; ?>
                                        <tr>
                                            <td><?= $i+1 ?></td>
                                            <td class="text-start text-wrap <?= $cls ?>"><?= $a['activity_name'] ?></td>
                                            <td class="<?= $cls ?>"><?= $a['location_facade'] ?></td>
                                            <td class="<?= $cls ?>"><?= $a['zone_name'] ?></td>
                                            <td class="<?= $cls ?>"><?= $a['floor'] ?></td>
                                            <td class="<?= $cls ?>"><?= $a['unit'] ?></td>
                                            <td class="<?= $cls ?>"><?= $a['contractor_quantity'] ?></td>
                                            <td class="<?= $cls ?>"><?= $a['contractor_meterage'] ?></td>
                                            <td class="<?= $cls ?>"><?= $a['personnel_count'] ?></td>
                                            <td><?= $a['cum_installed_count'] ?></td>
                                            <td class="text-danger"><?= $a['cum_rejected_count'] ?></td>
                                            <td class="text-start text-wrap small"><?= $a['consultant_comment'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($report['consultant_notes']): ?>
                            <div class="border p-2 bg-light mb-3 rounded">
                                <strong>نظر کلی مشاور:</strong> <?= nl2br($report['consultant_notes']) ?>
                            </div>
                        <?php endif; ?>
                    </main>
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td>
                    <div class="d-flex justify-content-around mt-4 pt-3 border-top">
                        <div class="signature-box">
                            <div>امضای پیمانکار</div>
                            <?php if($report['signature_contractor']): ?><img src="<?= $report['signature_contractor'] ?>"><?php endif; ?>
                        </div>
                        <div class="signature-box">
                            <div>امضای مشاور</div>
                            <?php if($report['signature_consultant']): ?><img src="<?= $report['signature_consultant'] ?>"><?php endif; ?>
                        </div>
                        <div class="signature-box">
                            <div>امضای کارفرما</div>
                        </div>
                    </div>
                    <div class="text-center mt-2 small text-muted footer-note"></div>
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
                    <ul class="nav nav-tabs" id="myTab"><li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#home">عمومی</a></li><li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#logos">لوگوها</a></li></ul>
                    <div class="tab-content pt-3">
                        <!-- General -->
                        <div class="tab-pane fade show active" id="home">
                            <div class="row g-3">
                                <div class="col-6"><label>فونت</label><select class="form-select" id="inputFont"><option value="Samim">Samim</option><option value="Tahoma">Tahoma</option></select></div>
                                <div class="col-6"><label>سایز فونت (pt)</label><input type="number" class="form-control" id="inputSize"></div>
                                <div class="col-6"><label>رنگ هدر</label><input type="color" class="form-control" id="inputHeaderColor"></div>
                                <div class="col-12"><label>پاورقی</label><input type="text" class="form-control" id="inputFooterNote"></div>
                                <div class="col-12 text-end"><button class="btn btn-primary" onclick="saveGeneralSettings()">ذخیره تنظیمات</button></div>
                            </div>
                        </div>
                        <!-- Logos -->
                        <div class="tab-pane fade" id="logos">
                            <form id="logoForm">
                                <div class="row g-3">
                                    <div class="col-4"><label>لوگو راست</label><input type="file" name="logo_right" class="form-control"></div>
                                    <div class="col-4"><label>لوگو وسط</label><input type="file" name="logo_middle" class="form-control"></div>
                                    <div class="col-4"><label>لوگو چپ</label><input type="file" name="logo_left" class="form-control"></div>
                                    <div class="col-12 text-center"><button type="button" class="btn btn-success" onclick="uploadLogos()">آپلود و ذخیره</button></div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load Settings from PHP
        let settings = <?php echo $settingsJson; ?>;

        function applyStyles() {
            let css = `
                body { font-family: "${settings.global.fontFamily}", Tahoma !important; }
                .print-content-wrapper { font-size: ${settings.global.fontSize}pt; color: ${settings.global.textColor}; }
                .data-table th, .bg-light { background-color: ${settings.global.headerColor} !important; color: white !important; }
                ${Object.keys(settings).filter(k => settings[k].columns).map(k => {
                    let id = '#' + k.replace('_','-') + '-table';
                    return settings[k].columns.map((w,i) => `${id} colgroup col:nth-child(${i+1}) { width: ${w}% }`).join('\n');
                }).join('\n')}
            `;
            document.getElementById('dynamic-styles').innerHTML = css;
            document.querySelector('.footer-note').innerText = settings.global.footerNote || '';
            
            // Update Modal Inputs
            document.getElementById('inputFont').value = settings.global.fontFamily;
            document.getElementById('inputSize').value = settings.global.fontSize;
            document.getElementById('inputHeaderColor').value = settings.global.headerColor;
            document.getElementById('inputFooterNote').value = settings.global.footerNote;
        }

        async function saveGeneralSettings() {
            settings.global.fontFamily = document.getElementById('inputFont').value;
            settings.global.fontSize = document.getElementById('inputSize').value;
            settings.global.headerColor = document.getElementById('inputHeaderColor').value;
            settings.global.footerNote = document.getElementById('inputFooterNote').value;

            try {
                let res = await fetch('api/save_print_settings.php', {
                    method: 'POST',
                    body: JSON.stringify(settings)
                });
                let data = await res.json();
                if(data.success) {
                    alert('تنظیمات ذخیره شد');
                    applyStyles();
                    bootstrap.Modal.getInstance(document.getElementById('settingsModal')).hide();
                } else {
                    alert('خطا: ' + data.message);
                }
            } catch(e) { alert('خطا در ارتباط'); }
        }

        async function uploadLogos() {
            let form = document.getElementById('logoForm');
            let formData = new FormData(form);
            try {
                let res = await fetch('api/save_logo_settings.php', { method: 'POST', body: formData });
                let data = await res.json();
                if(data.success) {
                    alert('لوگوها ذخیره شدند. صفحه رفرش میشود.');
                    location.reload();
                } else {
                    alert('خطا: ' + (data.message || 'Unknown'));
                }
            } catch(e) { alert('خطا در ارتباط'); }
        }

        document.addEventListener('DOMContentLoaded', applyStyles);
    </script>
</body>
</html>