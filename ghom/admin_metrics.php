<?php
// ghom/admin_metrics.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
if ($_SESSION['role'] !== 'superuser') die("Access Denied");
$pdo = getProjectDBConnection('ghom');

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cols = [
            'report_date', 'progress_plan_period', 'progress_plan_cumulative', 'progress_actual_period', 'progress_actual_cumulative',
            'manpower_plan', 'manpower_actual', 'opening_area_plan', 'opening_area_actual',
            'sealing_plan_period', 'sealing_plan_cum', 'sealing_actual_cum',
            'substruct_plan_period', 'substruct_plan_cum', 'substruct_actual_period', 'substruct_actual_cum',
            'install_plan_period', 'install_plan_cum', 'install_actual_period', 'install_actual_cum',
            'repair_plan_period', 'repair_plan_cum', 'repair_actual_period', 'repair_actual_cum',
            'new_panel_plan_period', 'new_panel_plan_cum', 'new_panel_actual_period', 'new_panel_actual_cum',
            'panel_healthy_qty', 'panel_rejected_qty', 'panel_repaired_qty'
        ];
        
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $updates = implode(',', array_map(fn($c) => "$c=VALUES($c)", $cols));
        
        $sql = "INSERT INTO weekly_metrics (" . implode(',', $cols) . ") VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updates";
        $stmt = $pdo->prepare($sql);
        
        $values = [];
        foreach($cols as $c) $values[] = $_POST[$c] ?? 0;
        
        $stmt->execute($values);
        $msg = "✅ داده‌ها با موفقیت ذخیره شد.";
    } catch (Exception $e) {
        $msg = "❌ خطا: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ورود اطلاعات گزارش هفتگی</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <style>body{font-family:Tahoma; padding:20px; background:#f8f9fa;} label{font-size:12px; font-weight:bold; margin-top:10px;}</style>
</head>
<body>
<div class="container bg-white p-4 rounded shadow">
    <h3>📝 ورود اطلاعات جدول گزارش هفتگی</h3>
    <?php if($msg) echo "<div class='alert alert-info'>$msg</div>"; ?>
    
    <form method="POST">
        <div class="row">
            <div class="col-md-4">
                <label>تاریخ گزارش:</label>
                <input type="date" name="report_date" class="form-control" required>
            </div>
        </div>
        <hr>
        
        <h5>1. پیشرفت فیزیکی (%)</h5>
        <div class="row">
            <div class="col-3"><label>برنامه (دوره):</label><input type="number" step="0.01" name="progress_plan_period" class="form-control"></div>
            <div class="col-3"><label>برنامه (تجمعی):</label><input type="number" step="0.01" name="progress_plan_cumulative" class="form-control"></div>
            <div class="col-3"><label>واقعی (دوره):</label><input type="number" step="0.01" name="progress_actual_period" class="form-control"></div>
            <div class="col-3"><label>واقعی (تجمعی):</label><input type="number" step="0.01" name="progress_actual_cumulative" class="form-control"></div>
        </div>

        <h5 class="mt-4">2. نیروی انسانی (نفر)</h5>
        <div class="row">
            <div class="col-6"><label>برنامه:</label><input type="number" name="manpower_plan" class="form-control"></div>
            <div class="col-6"><label>واقعی:</label><input type="number" name="manpower_actual" class="form-control"></div>
        </div>

        <h5 class="mt-4">3. متراژ باز کردن پنل (m2)</h5>
        <div class="row">
            <div class="col-6"><label>برنامه (تجمعی):</label><input type="number" step="0.01" name="opening_area_plan" class="form-control"></div>
            <div class="col-6"><label>واقعی (تجمعی):</label><input type="number" step="0.01" name="opening_area_actual" class="form-control"></div>
        </div>

        <h5 class="mt-4">4. اصلاحات آب‌بندی (m2)</h5>
        <div class="row">
            <div class="col-4"><label>برنامه (دوره):</label><input type="number" step="0.01" name="sealing_plan_period" class="form-control"></div>
            <div class="col-4"><label>برنامه (تجمعی):</label><input type="number" step="0.01" name="sealing_plan_cum" class="form-control"></div>
            <div class="col-4"><label>واقعی (تجمعی):</label><input type="number" step="0.01" name="sealing_actual_cum" class="form-control"></div>
        </div>

        <h5 class="mt-4">5. اصلاحات زیرسازی (m2)</h5>
        <div class="row">
            <div class="col-3"><label>برنامه (دوره):</label><input type="number" step="0.01" name="substruct_plan_period" class="form-control"></div>
            <div class="col-3"><label>برنامه (تجمعی):</label><input type="number" step="0.01" name="substruct_plan_cum" class="form-control"></div>
            <div class="col-3"><label>واقعی (دوره):</label><input type="number" step="0.01" name="substruct_actual_period" class="form-control"></div>
            <div class="col-3"><label>واقعی (تجمعی):</label><input type="number" step="0.01" name="substruct_actual_cum" class="form-control"></div>
        </div>

        <h5 class="mt-4">6. نصب پنل (m2)</h5>
        <div class="row">
            <div class="col-3"><label>برنامه (دوره):</label><input type="number" step="0.01" name="install_plan_period" class="form-control"></div>
            <div class="col-3"><label>برنامه (تجمعی):</label><input type="number" step="0.01" name="install_plan_cum" class="form-control"></div>
            <div class="col-3"><label>واقعی (دوره):</label><input type="number" step="0.01" name="install_actual_period" class="form-control"></div>
            <div class="col-3"><label>واقعی (تجمعی):</label><input type="number" step="0.01" name="install_actual_cum" class="form-control"></div>
        </div>
        
        <h5 class="mt-4">7. ترمیم پنل (m2)</h5>
        <div class="row">
            <div class="col-3"><label>برنامه (دوره):</label><input type="number" step="0.01" name="repair_plan_period" class="form-control"></div>
            <div class="col-3"><label>برنامه (تجمعی):</label><input type="number" step="0.01" name="repair_plan_cum" class="form-control"></div>
            <div class="col-3"><label>واقعی (دوره):</label><input type="number" step="0.01" name="repair_actual_period" class="form-control"></div>
            <div class="col-3"><label>واقعی (تجمعی):</label><input type="number" step="0.01" name="repair_actual_cum" class="form-control"></div>
        </div>

        <h5 class="mt-4">8. ورود پنل جدید (m2)</h5>
        <div class="row">
            <div class="col-3"><label>برنامه (دوره):</label><input type="number" step="0.01" name="new_panel_plan_period" class="form-control"></div>
            <div class="col-3"><label>برنامه (تجمعی):</label><input type="number" step="0.01" name="new_panel_plan_cum" class="form-control"></div>
            <div class="col-3"><label>واقعی (دوره):</label><input type="number" step="0.01" name="new_panel_actual_period" class="form-control"></div>
            <div class="col-3"><label>واقعی (تجمعی):</label><input type="number" step="0.01" name="new_panel_actual_cum" class="form-control"></div>
        </div>

        <h5 class="mt-4">9. صورت وضعیت پنل‌ها (m2)</h5>
        <div class="row">
            <div class="col-4"><label>سالم:</label><input type="number" step="0.01" name="panel_healthy_qty" class="form-control"></div>
            <div class="col-4"><label>ریجکت:</label><input type="number" step="0.01" name="panel_rejected_qty" class="form-control"></div>
            <div class="col-4"><label>اصلاح:</label><input type="number" step="0.01" name="panel_repaired_qty" class="form-control"></div>
        </div>

        <button type="submit" class="btn btn-success w-100 mt-5">💾 ذخیره اطلاعات</button>
    </form>
</div>
</body>
</html>