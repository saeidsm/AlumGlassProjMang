<?php
// ghom/admin_reports.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
requireRole(['admin']);

$pdo = getProjectDBConnection('ghom');
$pageTitle = "مدیریت گزارشات پروژه";
require_once __DIR__ . '/header.php';

$msg = '';

// ============================================================
// 0. اصلاح فرمت تاریخ‌ها
// ============================================================
try {
    $pdo->exec("UPDATE weekly_metrics SET report_date = REPLACE(report_date, '/', '-') WHERE report_date LIKE '%/%'");
    $pdo->exec("UPDATE scurve_data SET report_date = REPLACE(report_date, '/', '-') WHERE report_date LIKE '%/%'");
} catch (Exception $e) { }

// ============================================================
// 1. دریافت لیست گزارش‌های موجود
// ============================================================
$datesStmt = $pdo->query("SELECT DISTINCT report_date FROM weekly_metrics ORDER BY report_date DESC");
$availableDates = $datesStmt->fetchAll(PDO::FETCH_COLUMN);

// تاریخ انتخاب شده
$selectedDate = $_GET['edit_date'] ?? null;

// دریافت اطلاعات هفته انتخاب شده
$currentData = [];
if ($selectedDate) {
    $stmtData = $pdo->prepare("SELECT * FROM weekly_metrics WHERE report_date = ?");
    $stmtData->execute([$selectedDate]);
    $currentData = $stmtData->fetch(PDO::FETCH_ASSOC) ?: [];
}

// تابع نمایش مقدار
function val($key) {
    global $currentData;
    return isset($currentData[$key]) ? htmlspecialchars($currentData[$key]) : ''; 
}

// ============================================================
// 2. پردازش فرم‌ها
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- A. ذخیره تمام اطلاعات جدول (دستی) ---
    if (isset($_POST['action']) && $_POST['action'] === 'save_metrics') {
        try {
            $cols = [
                'report_date', 
                'progress_plan_period', 'progress_plan_cumulative', 
                'progress_actual_period', 'progress_actual_cumulative',
                'manpower_plan', 'manpower_actual', 
                'opening_area_plan', 'opening_area_actual',
                'sealing_plan_period', 'sealing_plan_cum', 'sealing_actual_cum',
                'substruct_plan_period', 'substruct_plan_cum', 'substruct_actual_period', 'substruct_actual_cum',
                'install_plan_period', 'install_plan_cum', 'install_actual_period', 'install_actual_cum',
                'repair_plan_period', 'repair_plan_cum', 'repair_actual_period', 'repair_actual_cum',
                'new_panel_plan_period', 'new_panel_plan_cum', 
                'new_panel_actual_period', 'new_panel_actual_cum',
                'panel_healthy_qty', 'panel_rejected_qty', 'panel_repaired_qty'
            ];
            
            if (isset($_POST['report_date'])) {
                $_POST['report_date'] = str_replace('/', '-', $_POST['report_date']);
            }

            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $updates = implode(',', array_map(fn($c) => "$c=VALUES($c)", $cols));
            
            $sql = "INSERT INTO weekly_metrics (" . implode(',', $cols) . ") 
                    VALUES ($placeholders) 
                    ON DUPLICATE KEY UPDATE $updates";
            $stmt = $pdo->prepare($sql);
            
            $values = [];
            foreach($cols as $c) {
                // *** FIX: تبدیل رشته خالی به صفر برای جلوگیری از خطای دیتابیس ***
                $val = $_POST[$c] ?? '';
                if ($val === '') {
                    $values[] = 0; // اگر خالی بود، 0 بفرست
                } else {
                    $values[] = $val;
                }
            }
            
            $stmt->execute($values);
            $msg = "✅ اطلاعات جدول ذخیره شد.";
            
            // رفرش
            $selectedDate = $_POST['report_date'];
            $stmtData = $pdo->prepare("SELECT * FROM weekly_metrics WHERE report_date = ?");
            $stmtData->execute([$selectedDate]);
            $currentData = $stmtData->fetch(PDO::FETCH_ASSOC) ?: [];

        } catch (Exception $e) {
            $msg = "❌ خطا: " . $e->getMessage();
        }
    }

    // --- B. ذخیره S-CURVE ---
    if (isset($_POST['action']) && $_POST['action'] === 'save_scurve_manual') {
        try {
            $reportDate = str_replace('/', '-', $_POST['report_date']); 
            $pointDate  = $_POST['point_date']; 
            $actualCum  = floatval($_POST['actual_cumulative']);
            $blockType  = 'total'; 

            // 1. ساخت هفته جدید (اگر نیست)
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM scurve_data WHERE report_date = ?");
            $stmtCheck->execute([$reportDate]);
            if ($stmtCheck->fetchColumn() == 0) {
                $stmtLast = $pdo->query("SELECT MAX(report_date) FROM scurve_data");
                $lastDate = $stmtLast->fetchColumn();
                if ($lastDate) {
                    $sqlCopy = "INSERT IGNORE INTO scurve_data 
                        (report_date, date_point, block_type, plan_periodic, plan_cumulative, actual_periodic, actual_cumulative)
                        SELECT ?, date_point, block_type, plan_periodic, plan_cumulative, actual_periodic, actual_cumulative
                        FROM scurve_data WHERE report_date = ?";
                    $pdo->prepare($sqlCopy)->execute([$reportDate, $lastDate]);
                }
            }

            // 2. تعمیر سوابق
            $sqlRepair = "UPDATE scurve_data AS target
                          INNER JOIN (
                              SELECT date_point, MAX(actual_cumulative) as best_actual
                              FROM scurve_data WHERE report_date < ? AND block_type = 'total' GROUP BY date_point
                          ) AS source ON target.date_point = source.date_point
                          SET target.actual_cumulative = source.best_actual
                          WHERE target.report_date = ? AND target.block_type = 'total' 
                            AND (target.actual_cumulative = 0 OR target.actual_cumulative IS NULL)
                            AND target.date_point < ?";
            $pdo->prepare($sqlRepair)->execute([$reportDate, $reportDate, $pointDate]);

            // 3. آپدیت نقطه جاری
            $stmtPrev = $pdo->prepare("SELECT actual_cumulative FROM scurve_data 
                                       WHERE report_date = ? AND date_point < ? AND block_type = ? 
                                       ORDER BY date_point DESC LIMIT 1");
            $stmtPrev->execute([$reportDate, $pointDate, $blockType]);
            $prevCum = floatval($stmtPrev->fetchColumn() ?: 0);
            $actualPeriodic = max(0, $actualCum - $prevCum);

            $sqlUpsert = "INSERT INTO scurve_data 
                          (report_date, date_point, block_type, plan_periodic, plan_cumulative, actual_periodic, actual_cumulative)
                          VALUES (?, ?, ?, 0, 0, ?, ?)
                          ON DUPLICATE KEY UPDATE 
                          actual_cumulative = VALUES(actual_cumulative),
                          actual_periodic = VALUES(actual_periodic)";
            $pdo->prepare($sqlUpsert)->execute([$reportDate, $pointDate, $blockType, $actualPeriodic, $actualCum]);

            $msg = "✅ نمودار S-Curve آپدیت شد.";

        } catch (Exception $e) {
            $msg = "❌ خطا: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    <style>
        @font-face { font-family: "Samim"; src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"); }
        body { font-family: "Samim", Tahoma; background: #f5f7fa; padding: 20px; }
        .admin-container { max-width: 1100px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 30px; border-top: 5px solid #667eea; }
        h3, h5 { color: #2d3748; border-bottom: 2px solid #edf2f7; padding-bottom: 10px; margin-top: 0; }
        label { font-weight: bold; font-size: 13px; color: #4a5568; display: block; margin-bottom: 5px; }
        input[type="text"], input[type="number"], input[type="date"], select { width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; box-sizing: border-box; font-family: 'Samim'; }
        
        .btn-save { background: #48bb78; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; width: 100%; margin-top: 15px; font-size: 14px; } 
        .btn-save:hover { background: #38a169; }
        .btn-blue { background: #4299e1; }
        
        .row { display: flex; gap: 15px; margin-bottom: 15px; align-items: flex-end; }
        .col-3 { flex: 0 0 23%; } .col-4 { flex: 0 0 32%; } .col-6 { flex: 0 0 48%; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .tab { padding: 10px 20px; cursor: pointer; border-radius: 8px 8px 0 0; background: #edf2f7; font-weight: bold; color: #718096; }
        .tab.active { background: #667eea; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .selector-box { background: #ebf8ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #90cdf4; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; text-align: center; }
        .alert-success { background: #f0fff4; border: 1px solid #9ae6b4; color: #22543d; }
        .alert-danger { background: #fff5f5; border: 1px solid #fed7d7; color: #c53030; }
        .note-box { background: #fffaf0; border-left: 4px solid #ed8936; padding: 12px; margin-bottom: 15px; font-size: 12px; }
        
        jdp-container { z-index: 99999 !important; }
    </style>
</head>
<body>

<?php if($msg): ?>
<div class="alert <?= strpos($msg, '✅') !== false ? 'alert-success' : 'alert-danger' ?>">
    <?= $msg ?>
</div>
<?php endif; ?>

<!-- SELECTOR -->
<div class="admin-container" style="padding: 15px; margin-bottom: 20px;">
    <form method="GET" class="selector-box">
        <label style="margin:0; width: 150px; font-size: 14px;">📅 انتخاب تاریخ گزارش:</label>
        <select name="edit_date" onchange="this.form.submit()" style="flex: 1; font-weight: bold;">
            <option value="">+++ ثبت هفته جدید +++</option>
            <?php foreach($availableDates as $d): ?>
                <option value="<?= $d ?>" <?= ($selectedDate == $d) ? 'selected' : '' ?>><?= $d ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="tabs">
    <div class="tab active" onclick="switchTab('metrics')">1. جدول خلاصه وضعیت (ورود دستی)</div>
    <div class="tab" onclick="switchTab('scurve')">2. آپدیت S-Curve (نمودار)</div>
</div>

<!-- TAB 1: MANUAL TABLE -->
<div id="metrics" class="tab-content active admin-container">
    <h3>📝 ویرایش جدول گزارش: <span style="color:#667eea"><?= $selectedDate ?: 'هفته جدید' ?></span></h3>
    <div class="note-box">
        ⚠️ تمامی فیلدها دستی هستند. فیلدهای خالی به صورت 0 ذخیره می‌شوند.
    </div>
    
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_metrics">
        
        <div class="row">
            <div class="col-4">
                <label>تاریخ گزارش (هفته جاری):</label>
                <input type="text" name="report_date" value="<?= $selectedDate ?>" data-jdp required placeholder="1404-09-20">
            </div>
        </div>

        <!-- Row 1 -->
        <div style="background:#fff; padding:15px; border-radius:8px; margin-bottom:15px; border: 1px dashed #cbd5e0;">
            <h5>1. پیشرفت فیزیکی (%)</h5>
            <div class="row">
                <div class="col-3"><label>برنامه (دوره)</label><input type="number" step="0.01" name="progress_plan_period" value="<?= val('progress_plan_period') ?>"></div>
                <div class="col-3"><label>برنامه (تجمعی)</label><input type="number" step="0.01" name="progress_plan_cumulative" value="<?= val('progress_plan_cumulative') ?>"></div>
                <div class="col-3"><label>واقعی (دوره)</label><input type="number" step="0.01" name="progress_actual_period" value="<?= val('progress_actual_period') ?>"></div>
                <div class="col-3"><label>واقعی (تجمعی)</label><input type="number" step="0.01" name="progress_actual_cumulative" value="<?= val('progress_actual_cumulative') ?>"></div>
            </div>
        </div>

        <!-- Row 2 -->
        <div style="background:#fff; padding:15px; border-radius:8px; margin-bottom:15px; border: 1px dashed #cbd5e0;">
            <h5>2. نیروی انسانی (نفر)</h5>
            <div class="row">
                <div class="col-6"><label>برنامه</label><input type="number" name="manpower_plan" value="<?= val('manpower_plan') ?>"></div>
                <div class="col-6"><label>واقعی</label><input type="number" name="manpower_actual" value="<?= val('manpower_actual') ?>"></div>
            </div>
        </div>

        <!-- Row 3 -->
        <div style="background:#fff; padding:15px; border-radius:8px; margin-bottom:15px; border: 1px dashed #cbd5e0;">
            <h5>3. متراژ باز کردن پنل (m²)</h5>
            <div class="row">
                <div class="col-6"><label>برنامه تجمعی</label><input type="number" step="0.01" name="opening_area_plan" value="<?= val('opening_area_plan') ?>"></div>
                <div class="col-6"><label>واقعی تجمعی</label><input type="number" step="0.01" name="opening_area_actual" value="<?= val('opening_area_actual') ?>"></div>
            </div>
        </div>

        <!-- Row 4 -->
        <div style="background:#fff; padding:15px; border-radius:8px; margin-bottom:15px; border: 1px dashed #cbd5e0;">
            <h5>4. اصلاحات آب‌بندی (m²)</h5>
            <div class="row">
                <div class="col-4"><label>برنامه دوره</label><input type="number" step="0.01" name="sealing_plan_period" value="<?= val('sealing_plan_period') ?>"></div>
                <div class="col-4"><label>برنامه تجمعی</label><input type="number" step="0.01" name="sealing_plan_cum" value="<?= val('sealing_plan_cum') ?>"></div>
                <div class="col-4"><label>واقعی تجمعی</label><input type="number" step="0.01" name="sealing_actual_cum" value="<?= val('sealing_actual_cum') ?>"></div>
            </div>
        </div>

        <!-- Row 5 -->
        <div style="background:#fff; padding:15px; border-radius:8px; margin-bottom:15px; border: 1px dashed #cbd5e0;">
            <h5>5. اصلاحات زیرسازی (m²)</h5>
            <div class="row">
                <div class="col-4"><label>برنامه دوره</label><input type="number" step="0.01" name="substruct_plan_period" value="<?= val('substruct_plan_period') ?>"></div>
                <div class="col-4"><label>برنامه تجمعی</label><input type="number" step="0.01" name="substruct_plan_cum" value="<?= val('substruct_plan_cum') ?>"></div>
                <div class="col-4"><label>واقعی دوره</label><input type="number" step="0.01" name="substruct_actual_period" value="<?= val('substruct_actual_period') ?>"></div>
            </div>
            <div class="row">
                <div class="col-4"><label>واقعی تجمعی</label><input type="number" step="0.01" name="substruct_actual_cum" value="<?= val('substruct_actual_cum') ?>"></div>
            </div>
        </div>
        
        <!-- سایر ردیف ها -->
        <div style="background:#fff; padding:15px; border-radius:8px; margin-bottom:15px; border: 1px dashed #cbd5e0;">
            <h5>سایر آیتم‌ها (نصب / تعمیرات / پنل جدید)</h5>
            <div class="row">
                <div class="col-3"><label>نصب برنامه دوره</label><input type="number" step="0.01" name="install_plan_period" value="<?= val('install_plan_period') ?>"></div>
                <div class="col-3"><label>نصب برنامه تجمعی</label><input type="number" step="0.01" name="install_plan_cum" value="<?= val('install_plan_cum') ?>"></div>
                <div class="col-3"><label>نصب واقعی دوره</label><input type="number" step="0.01" name="install_actual_period" value="<?= val('install_actual_period') ?>"></div>
                <div class="col-3"><label>نصب واقعی تجمعی</label><input type="number" step="0.01" name="install_actual_cum" value="<?= val('install_actual_cum') ?>"></div>
            </div>
            <hr>
            <div class="row">
                <div class="col-6"><label>پنل جدید واقعی تجمعی</label><input type="number" step="0.01" name="new_panel_actual_cum" value="<?= val('new_panel_actual_cum') ?>"></div>
                <div class="col-6"><label>پنل سالم (QC)</label><input type="number" step="0.01" name="panel_healthy_qty" value="<?= val('panel_healthy_qty') ?>"></div>
            </div>
        </div>
        
        <button type="submit" class="btn-save">💾 ذخیره تمام اطلاعات</button>
    </form>
</div>

<!-- TAB 2: SCURVE -->
<div id="scurve" class="tab-content admin-container">
    <h3>📈 آپدیت S-Curve (فقط نمودار)</h3>
    <div class="note-box">
        این بخش فقط نمودار را آپدیت می‌کند.
    </div>
    
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_scurve_manual">
        <div class="row">
            <div class="col-6">
                <label>تاریخ گزارش:</label>
                <input type="text" name="report_date" value="<?= $selectedDate ?>" data-jdp required placeholder="1404-09-20">
            </div>
            <div class="col-6">
                <label>تاریخ پوینت نمودار (میلادی):</label>
                <input type="date" name="point_date" required style="direction: ltr;">
            </div>
        </div>
        <div style="background:#ebf8ff; padding:20px; border-radius:8px; margin: 20px 0;">
            <label>مقدار واقعی تجمعی (%):</label>
            <input type="number" step="0.01" name="actual_cumulative" required style="font-size:18px;">
        </div>
        <button type="submit" class="btn-save btn-blue">💾 ذخیره در نمودار</button>
    </form>
</div>

<script src="/ghom/assets/js/jalalidatepicker.min.js"></script>
<script>
    jalaliDatepicker.startWatch({ zIndex: 99999 });
    function switchTab(id) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        const index = ['metrics', 'scurve'].indexOf(id);
        document.querySelectorAll('.tab')[index].classList.add('active');
        document.getElementById(id).classList.add('active');
    }
</script>
</body>
</html>