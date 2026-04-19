<?php
// ===================================================================
// site_qc_form.php SITE RECEIPT QC FORM (NEW PANELS FROM FACTORY)
// ===================================================================
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

if (!isLoggedIn()) { header('Location: /login.php'); exit(); }

$conn = getProjectDBConnection("ghom");
$message = "";
$mode = 'create';
$data = [];

// --- 1. Edit Mode ---
if (isset($_GET['edit_id'])) {
    $mode = 'edit';
    $id = (int)$_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM site_new_panels_qc WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
} 
// --- 2. Create Mode (Pre-fill from Dashboard) ---
elseif (isset($_GET['pre_type']) && isset($_GET['pre_num'])) {
    // Auto-generate Element ID from Factory Data
    $data['element_id'] = $_GET['pre_type'] . '-' . $_GET['pre_num'];
    // Default Factory Name (Optional logic)
    $data['factory_name'] = 'منتخب عمران (تبریز)'; 
}


// --- Handle Submit ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $params = [
            $_POST['factory_name'],
            $_POST['element_id'],
            $_POST['zone_name'] ?? null,
            $_SESSION['user_id'],
            $_POST['inspection_date'] ? j2g($_POST['inspection_date']) : date('Y-m-d'),
            
            $_POST['check_length'] ?: 0,
            $_POST['check_width'] ?: 0,
            $_POST['check_thickness'] ?: 0,
            $_POST['check_bowing_lat'] ?: 0,
            $_POST['check_bowing_long'] ?: 0,
            
            $_POST['surface_status'],
            $_POST['cracks_status'],
            $_POST['edges_status'],
            
            $_POST['status'],
            $_POST['notes']
        ];

        if ($mode === 'edit') {
            $sql = "UPDATE site_new_panels_qc SET 
                factory_name=?, element_id=?, zone_name=?, inspector_id=?, inspection_date=?,
                check_length=?, check_width=?, check_thickness=?, check_bowing_lat=?, check_bowing_long=?,
                surface_status=?, cracks_status=?, edges_status=?, status=?, notes=?
                WHERE id = ?";
            $params[] = $data['id'];
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $message = "ویرایش انجام شد.";
        } else {
            $sql = "INSERT INTO site_new_panels_qc (
                factory_name, element_id, zone_name, inspector_id, inspection_date,
                check_length, check_width, check_thickness, check_bowing_lat, check_bowing_long,
                surface_status, cracks_status, edges_status, status, notes
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $message = "پنل جدید ثبت شد.";
        }
    } catch (Exception $e) {
        $message = "خطا: " . $e->getMessage();
    }
}

// Date Converter
function j2g($jDate) {
    $parts = explode('/', $jDate);
    if(count($parts)!=3) return date('Y-m-d');
    $g = jalali_to_gregorian($parts[0],$parts[1],$parts[2]);
    return implode('-',$g);
}
function g2j($gDate) {
    if(!$gDate) return '';
    $t = strtotime($gDate);
    $j = gregorian_to_jalali(date('Y',$t),date('m',$t),date('d',$t));
    return implode('/',$j);
}

$pageTitle = "ثبت ورودی پنل‌های نو (سایت)";
require_once __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
<script src="/ghom/assets/js/jalalidatepicker.min.js"></script>

<div class="container mt-4" dir="rtl">
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><?= $mode=='edit'?'ویرایش پنل نو':'ثبت پنل ورودی (نو)' ?></h4>
        <a href="qc_dashboard.php" class="btn btn-secondary">بازگشت به لیست</a>
    </div>

    <?php if($message): ?><div class="alert alert-info"><?= $message ?></div><?php endif; ?>

    <form method="POST" class="card p-4 shadow-sm">
        
        <!-- 1. Origin Info -->
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label text-primary">کارخانه سازنده</label>
                <select class="form-select" name="factory_name" required>
                    <option value="منتخب عمران (تبریز)" <?= ($data['factory_name']??'')=='منتخب عمران (تبریز)'?'selected':'' ?>>منتخب عمران (تبریز)</option>
                    <option value="موزاییک میبد یزد" <?= ($data['factory_name']??'')=='موزاییک میبد یزد'?'selected':'' ?>>موزاییک میبد یزد</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">کد پنل (Element ID)</label>
                <input type="text" class="form-control" name="element_id" value="<?= $data['element_id']??'' ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">محل تخلیه (زون)</label>
                <input type="text" class="form-control" name="zone_name" value="<?= $data['zone_name']??'' ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">تاریخ ورود/بازرسی</label>
                <input type="text" class="form-control" data-jdp name="inspection_date" value="<?= g2j($data['inspection_date']??date('Y-m-d')) ?>" required>
            </div>
        </div>

        <hr>

        <!-- 2. Checks -->
        <div class="row">
            <div class="col-md-2 mb-3"><label>طول (mm)</label><input type="number" step="0.1" class="form-control" name="check_length" value="<?= $data['check_length']??'' ?>"></div>
            <div class="col-md-2 mb-3"><label>عرض (mm)</label><input type="number" step="0.1" class="form-control" name="check_width" value="<?= $data['check_width']??'' ?>"></div>
            <div class="col-md-2 mb-3"><label>ضخامت (mm)</label><input type="number" step="0.1" class="form-control" name="check_thickness" value="<?= $data['check_thickness']??'' ?>"></div>
            <div class="col-md-3 mb-3"><label>خیز عرضی</label><input type="number" step="0.1" class="form-control" name="check_bowing_lat" value="<?= $data['check_bowing_lat']??'' ?>"></div>
            <div class="col-md-3 mb-3"><label>خیز طولی</label><input type="number" step="0.1" class="form-control" name="check_bowing_long" value="<?= $data['check_bowing_long']??'' ?>"></div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label>وضعیت سطحی</label>
                <select class="form-select" name="surface_status">
                    <option value="OK" <?= ($data['surface_status']??'')=='OK'?'selected':'' ?>>سالم</option>
                    <option value="NOK" <?= ($data['surface_status']??'')=='NOK'?'selected':'' ?>>ایراد دارد</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label>وضعیت ترک</label>
                <select class="form-select" name="cracks_status">
                    <option value="OK" <?= ($data['cracks_status']??'')=='OK'?'selected':'' ?>>بدون ترک</option>
                    <option value="NOK" <?= ($data['cracks_status']??'')=='NOK'?'selected':'' ?>>دارای ترک</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label>لبه‌ها و گوشه‌ها</label>
                <select class="form-select" name="edges_status">
                    <option value="OK" <?= ($data['edges_status']??'')=='OK'?'selected':'' ?>>سالم</option>
                    <option value="NOK" <?= ($data['edges_status']??'')=='NOK'?'selected':'' ?>>لب‌پر/شکسته</option>
                </select>
            </div>
        </div>

        <hr>

        <!-- 3. Final Status -->
        <div class="row bg-light p-3 rounded">
            <div class="col-md-4">
                <label class="form-label fw-bold">نتیجه نهایی</label>
                <select class="form-select" name="status">
                    <option value="Accepted" class="text-success" <?= ($data['status']??'')=='Accepted'?'selected':'' ?>>تایید شده (Accepted)</option>
                    <option value="Conditional" class="text-warning" <?= ($data['status']??'')=='Conditional'?'selected':'' ?>>مشروط (نیاز به اصلاح جزئی)</option>
                    <option value="Rejected" class="text-danger" <?= ($data['status']??'')=='Rejected'?'selected':'' ?>>مردود (Rejected)</option>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">توضیحات / نقص‌ها</label>
                <input type="text" class="form-control" name="notes" value="<?= $data['notes']??'' ?>" placeholder="در صورت رد شدن، علت را بنویسید">
            </div>
        </div>

        <button type="submit" class="btn btn-success w-100 mt-4 py-2">ثبت اطلاعات</button>
    </form>
</div>

<script>jalaliDatepicker.startWatch();</script>
<?php require_once __DIR__ . '/footer.php'; ?>