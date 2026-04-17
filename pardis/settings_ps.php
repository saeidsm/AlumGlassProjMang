<?php
// settings_ps.php - Manage Lists (Including Contractors & Contract Numbers)
require_once __DIR__ . '/../../sercon/bootstrap.php';

secureSession();
requireRole(['admin']);

$pdo = getProjectDBConnection('pardis');
$report_id = $_GET['id'] ?? null;
// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? '';
    
    try {
        if ($action === 'add') {
            $value = trim($_POST['value'] ?? '');
            if (empty($value)) throw new Exception('مقدار نمی‌تواند خالی باشد');
            
            if ($type === 'personnel') {
                $stmt = $pdo->prepare("INSERT INTO ps_personnel_roles (role_name, sort_order) VALUES (?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM ps_personnel_roles pr))");
                $stmt->execute([$value]);
           } elseif ($type === 'contractors') { // **UPDATED ADD LOGIC**
                $contract_num = $_POST['contract_number'] ?? '';
                $contract_date = $_POST['contract_date'] ?? NULL;
                $start_date = $_POST['start_date'] ?? NULL;
                $end_date = $_POST['end_date'] ?? NULL;
                $subject = $_POST['subject'] ?? '';
                $block_name = $_POST['block_name'] ?? '';
                $other_details = $_POST['other_details'] ?? '';
                
                $stmt = $pdo->prepare("INSERT INTO ps_contractors (name, contract_number, contract_date, start_date, end_date, subject, block_name, other_details) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$value, $contract_num, $contract_date, $start_date, $end_date, $subject, $block_name, $other_details]);
            } elseif ($type === 'tools') {
                $stmt = $pdo->prepare("INSERT INTO ps_tools_list (tool_name) VALUES (?)");
                $stmt->execute([$value]);
            } elseif ($type === 'materials') {
                $stmt = $pdo->prepare("INSERT INTO ps_materials_list (material_name) VALUES (?)");
                $stmt->execute([$value]);
            } elseif ($type === 'activities') {
                $category = $_POST['category'] ?? '';
                $unit = $_POST['unit'] ?? 'عدد';
                $stmt = $pdo->prepare("INSERT INTO ps_project_activities (name, category, unit) VALUES (?, ?, ?)");
                $stmt->execute([$value, $category, $unit]);
            }
            $message = 'مورد با موفقیت اضافه شد';
            $messageType = 'success';
        } elseif ($action === 'update') { // **NEW UPDATE ACTION**
            if ($type === 'contractors') {
                $id = (int)($_POST['id'] ?? 0);
                $contract_num = $_POST['contract_number'] ?? '';
                $contract_date = $_POST['contract_date'] ?? NULL;
                $start_date = $_POST['start_date'] ?? NULL;
                $end_date = $_POST['end_date'] ?? NULL;
                $subject = $_POST['subject'] ?? '';
                $block_name = $_POST['block_name'] ?? '';
                $other_details = $_POST['other_details'] ?? '';
                
                $stmt = $pdo->prepare("UPDATE ps_contractors SET 
                                        contract_number = ?, contract_date = ?, start_date = ?, end_date = ?, 
                                        subject = ?, block_name = ?, other_details = ? 
                                       WHERE id = ?");
                $stmt->execute([$contract_num, $contract_date, $start_date, $end_date, $subject, $block_name, $other_details, $id]);
                $message = 'اطلاعات قرارداد با موفقیت به‌روزرسانی شد';
                $messageType = 'success';
            }

        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($type === 'personnel') $pdo->prepare("DELETE FROM ps_personnel_roles WHERE id = ?")->execute([$id]);
           elseif ($type === 'contractors') 
                $pdo->prepare("DELETE FROM ps_contractors WHERE id = ?")->execute([$id]);
            elseif ($type === 'tools') $pdo->prepare("DELETE FROM ps_tools_list WHERE id = ?")->execute([$id]);
            elseif ($type === 'materials') $pdo->prepare("DELETE FROM ps_materials_list WHERE id = ?")->execute([$id]);
            elseif ($type === 'activities') $pdo->prepare("DELETE FROM ps_project_activities WHERE id = ?")->execute([$id]);
            
            $message = 'مورد حذف شد';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = 'خطا: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Fetch Lists
$personnel = $pdo->query("SELECT * FROM ps_personnel_roles ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
$contractors = $pdo->query("SELECT * FROM ps_contractors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$tools = $pdo->query("SELECT * FROM ps_tools_list ORDER BY tool_name")->fetchAll(PDO::FETCH_ASSOC);
$materials = $pdo->query("SELECT * FROM ps_materials_list ORDER BY material_name")->fetchAll(PDO::FETCH_ASSOC);
$activities = $pdo->query("SELECT * FROM ps_project_activities ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
$unit_list = ['عدد', 'متر طول', 'متر مربع', 'کیلوگرم', 'تن', 'دستگاه', 'سرویس', 'نفر','میلیمتر','سانتیمتر', 'متر مکعب','لیتر','گالن'];

require_once __DIR__ . '/header_pardis.php';
?>
    <link href="/ghom/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ghom/assets/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="/ghom/assets/js/apexcharts.min.js"></script>
    <link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    <script src="/ghom/assets/js/jalalidatepicker.min.js"></script>
<style>
    .settings-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; background: white; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .settings-header { background: #4a5568; color: white; padding: 10px 15px; border-radius: 8px 8px 0 0; margin: -20px -20px 20px -20px; font-weight: bold; display: flex; align-items: center; gap: 10px; }
    .list-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid #eee; transition: background 0.2s; }
    .list-item:last-child { border-bottom: none; }
    .list-item:hover { background: #f8f9fa; }
    .btn-delete { color: #dc3545; background: none; border: none; font-size: 0.9rem; cursor: pointer; }
    .btn-delete:hover { color: #a71d2a; }
</style>

<div class="container-fluid my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark">⚙️ تنظیمات سیستم گزارش‌دهی</h3>
        <a href="daily_reports_dashboard_ps.php" class="btn btn-outline-secondary">بازگشت به فرم</a>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show"><?= $message ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row">

<div class="col-md-6">
    <div class="settings-card">
        <div class="settings-header"><i class="fas fa-hard-hat"></i> مدیریت قراردادهای پیمانکاران</div>
        
        <form method="POST" class="mb-4 p-3 border rounded bg-light">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add"><input type="hidden" name="type" value="contractors">
            <h6 class="mb-3 fw-bold">افزودن پیمانکار جدید</h6>
            <div class="row g-2">
                <div class="col-md-6"><input type="text" name="value" class="form-control form-control-sm" placeholder="* نام پیمانکار (دقیق مطابق با رول)" required></div>
                <div class="col-md-6"><input type="text" name="contract_number" class="form-control form-control-sm" placeholder="شماره قرارداد"></div>
                
                <div class="col-md-6"><input type="text" name="block_name" class="form-control form-control-sm" placeholder="لوکیشن/بلوک"></div>
                <div class="col-md-6"><input type="text" name="subject" class="form-control form-control-sm" placeholder="موضوع قرارداد"></div>
                
                <div class="col-md-4"><input type="text" name="contract_date" class="form-control form-control-sm jalali-date-picker" placeholder="تاریخ قرارداد" data-jdp></div>
                <div class="col-md-4"><input type="text" name="start_date" class="form-control form-control-sm jalali-date-picker" placeholder="شروع (برنامه)" data-jdp></div>
                <div class="col-md-4"><input type="text" name="end_date" class="form-control form-control-sm jalali-date-picker" placeholder="پایان (برنامه)" data-jdp></div>

                <div class="col-12">
                    <textarea name="other_details" class="form-control form-control-sm" rows="2" placeholder="جزئیات بیشتر (مانند قیمت قرارداد، سطح زیربنای ساختمان و...)"></textarea>
                </div>
                
                <div class="col-12 mt-3 text-start"><button class="btn btn-primary btn-sm w-100">افزودن قرارداد</button></div>
            </div>
        </form>

        <h6 class="mb-3 fw-bold">لیست قراردادهای جاری</h6>
        <div style="max-height: 450px; overflow-y: auto;">
            <?php foreach ($contractors as $c): ?>
                <div class="list-item d-block p-0">
                    <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                        <div>
                            <strong><?= htmlspecialchars($c['name']) ?></strong> 
                            <small class="text-muted ms-2">(شماره: <?= htmlspecialchars($c['contract_number'] ?? 'ندارد') ?>)</small>
                            <small class="badge bg-secondary ms-2"><?= htmlspecialchars($c['block_name'] ?? 'بدون بلوک') ?></small>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary p-0 px-2 me-2" data-bs-toggle="collapse" data-bs-target="#contract-<?= $c['id'] ?>" aria-expanded="false" aria-controls="contract-<?= $c['id'] ?>">
                                <i class="fas fa-edit fa-sm"></i> ویرایش
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این قرارداد را حذف کنید؟');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="contractors"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button class="btn-delete" type="submit"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="collapse p-3 border-top bg-light" id="contract-<?= $c['id'] ?>">
                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update"><input type="hidden" name="type" value="contractors"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <p class="small text-muted fw-bold">ویرایش قرارداد برای: <?= htmlspecialchars($c['name']) ?></p>
                            <div class="row g-2">
                                <div class="col-md-6"><input type="text" name="contract_number" class="form-control form-control-sm" placeholder="شماره قرارداد" value="<?= htmlspecialchars($c['contract_number'] ?? '') ?>"></div>
                                
                                <div class="col-md-6"><input type="text" name="block_name" class="form-control form-control-sm" placeholder="لوکیشن/بلوک" value="<?= htmlspecialchars($c['block_name'] ?? '') ?>"></div>
                                <div class="col-md-6"><input type="text" name="subject" class="form-control form-control-sm" placeholder="موضوع قرارداد" value="<?= htmlspecialchars($c['subject'] ?? '') ?>"></div>

                                <div class="col-md-4"><input type="text" name="contract_date" class="form-control form-control-sm jalali-date-picker" placeholder="تاریخ قرارداد" data-jdp value="<?= htmlspecialchars($c['contract_date'] ?? '') ?>"></div>
                                <div class="col-md-4"><input type="text" name="start_date" class="form-control form-control-sm jalali-date-picker" placeholder="شروع (برنامه)" data-jdp value="<?= htmlspecialchars($c['start_date'] ?? '') ?>"></div>
                                <div class="col-md-4"><input type="text" name="end_date" class="form-control form-control-sm jalali-date-picker" placeholder="پایان (برنامه)" data-jdp value="<?= htmlspecialchars($c['end_date'] ?? '') ?>"></div>

                                <div class="col-12">
                                    <textarea name="other_details" class="form-control form-control-sm" rows="2" placeholder="جزئیات بیشتر (قیمت قرارداد، سطح زیربنا و...)"><?= htmlspecialchars($c['other_details'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="col-12 mt-3"><button class="btn btn-success btn-sm w-100">ذخیره تغییرات</button></div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
        <div class="col-md-6">
            <div class="settings-card">
                <div class="settings-header"><i class="fas fa-users"></i> نقش‌های نیروی انسانی</div>
                <form method="POST" class="mb-3 d-flex gap-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add"><input type="hidden" name="type" value="personnel">
                    <input type="text" name="value" class="form-control form-control-sm" placeholder="نام نقش" required>
                    <button class="btn btn-primary btn-sm">افزودن</button>
                </form>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($personnel as $p): ?>
                        <div class="list-item">
                            <?= htmlspecialchars($p['role_name']) ?>
                            <form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="personnel"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn-delete"><i class="fas fa-trash"></i></button></form>
                                <?= csrfField() ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="settings-card">
                <div class="settings-header"><i class="fas fa-tasks"></i> فعالیت‌های اجرایی</div>
                <form method="POST" class="mb-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add"><input type="hidden" name="type" value="activities">
                    <div class="row g-2">
                        <div class="col-md-4"><input type="text" name="value" class="form-control form-control-sm" placeholder="نام فعالیت" required></div>
                        <div class="col-md-3"><input type="text" name="category" class="form-control form-control-sm" placeholder="دسته بندی"></div>
                        <div class="col-md-3"><select name="unit" class="form-select form-select-sm"><?php foreach($unit_list as $u) echo "<option value='$u'>$u</option>"; ?></select></div>
                        <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">افزودن</button></div>
                    </div>
                </form>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($activities as $a): ?>
                        <div class="list-item">
                            <div><strong><?= htmlspecialchars($a['name']) ?></strong> <small class="text-muted ms-2">(<?= $a['category'] ?> | <?= $a['unit'] ?>)</small></div>
                            <form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="activities"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn-delete"><i class="fas fa-trash"></i></button></form>
                                <?= csrfField() ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="settings-card">
                <div class="settings-header"><i class="fas fa-tools"></i> ماشین آلات و ابزار</div>
                <form method="POST" class="mb-3 d-flex gap-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add"><input type="hidden" name="type" value="tools">
                    <input type="text" name="value" class="form-control form-control-sm" placeholder="نام ابزار" required>
                    <button class="btn btn-primary btn-sm">افزودن</button>
                </form>
                <div style="max-height: 250px; overflow-y: auto;">
                    <?php foreach ($tools as $t): ?>
                        <div class="list-item"><?= htmlspecialchars($t['tool_name']) ?><form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="tools"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="btn-delete"><i class="fas fa-trash"></i></button></form></div>
                            <?= csrfField() ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="settings-card">
                <div class="settings-header"><i class="fas fa-cubes"></i> مصالح</div>
                <form method="POST" class="mb-3 d-flex gap-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add"><input type="hidden" name="type" value="materials">
                    <input type="text" name="value" class="form-control form-control-sm" placeholder="نام مصالح" required>
                    <button class="btn btn-primary btn-sm">افزودن</button>
                </form>
                <div style="max-height: 250px; overflow-y: auto;">
                    <?php foreach ($materials as $m): ?>
                        <div class="list-item"><?= htmlspecialchars($m['material_name']) ?><form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="materials"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="btn-delete"><i class="fas fa-trash"></i></button></form></div>
                            <?= csrfField() ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>