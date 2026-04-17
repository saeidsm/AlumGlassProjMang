<?php
// qc_edit.php 
//--- Configuration & Auth ---
function isMobileDevice() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}
//if (isMobileDevice()) { header('Location: qc_edit.php'); exit(); }

require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

if (!isLoggedIn()) { header('Location: /login.php?msg=login_required'); exit(); }
if (!in_array($_SESSION['role'], ['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])) { http_response_code(403); require 'Access_Denied.php'; exit; }

$conn = getProjectDBConnection("ghom");
$message = "";

// --- Helper Functions ---
function j2g($jDate) {
    if (empty($jDate)) return null;
    $parts = explode('/', $jDate);
    if (count($parts) !== 3) return null;
    $gDate = jalali_to_gregorian((int)$parts[0], (int)$parts[1], (int)$parts[2]);
    return implode('-', $gDate);
}

function g2j($gDate) {
    if (empty($gDate) || $gDate == '0000-00-00') return '';
    $ts = strtotime($gDate);
    $j = gregorian_to_jalali(date('Y', $ts), date('m', $ts), date('d', $ts));
    return implode('/', $j);
}

// Check ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: qc_dashboard.php");
    exit;
}
$id = (int)$_GET['id'];

// --- 1. HANDLE POST REQUESTS (UPDATE or DELETE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- DELETE LOGIC ---
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $delStmt = $conn->prepare("DELETE FROM qc_inspections WHERE id = ?");
        if ($delStmt->execute([$id])) {
            header("Location: qc_dashboard.php?msg=deleted");
            exit;
        } else {
            $message = "<div class='alert alert-danger'>خطا در حذف رکورد.</div>";
        }
    }
    
    // --- UPDATE LOGIC ---
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $prod_date = j2g($_POST['production_date']);
        $sent_date = j2g($_POST['sent_date']);
        
        $property_code = $_POST['property_code'];
        if (empty($property_code)) {
            $property_code = $_POST['product_type'] . " - " . $_POST['dim_l'] . "*" . $_POST['dim_w'] . "*" . $_POST['dim_h'];
        }

        // Determine Status
        $status = 'pending';
        if (!empty($sent_date)) $status = 'sent';
        
        $visual_cols = [
            'check_cleaning', 'check_painting', 'check_surface', 'check_cracks', 
            'check_curing', 'check_temp_humidity', 'check_plastic_cover', 'check_rest_period',
            'check_trestle_strength', 'check_packaging', 'check_packing_method', 
            'check_facade_appearance', 'check_warping_visual', 'check_qc_final', 'check_initial_drying'
        ];
        foreach($visual_cols as $vc) {
            if(($_POST[$vc] ?? '-') === 'NOK') $status = 'rejected';
        }

        $sql = "UPDATE qc_inspections SET 
            product_type=?, product_number=?, property_code=?, status=?, production_date=?, sent_date=?,
            
            check_cleaning=?, check_painting=?, check_surface=?, check_cracks=?, 
            check_curing=?, check_temp_humidity=?, check_plastic_cover=?, check_rest_period=?,
            check_trestle_strength=?, check_packaging=?, check_packing_method=?, 
            check_facade_appearance=?, check_warping_visual=?, check_qc_final=?, check_initial_drying=?,
            
            dev_length_1=?, dev_length_2=?, dev_width_1=?, dev_width_2=?, dev_thickness_1=?, dev_thickness_2=?, 
            dev_bowing_length_1=?, dev_bowing_length_2=?, dev_bowing_width_1=?, dev_bowing_width_2=?,
            
            dev_diameter_1=?, dev_diameter_2=?, dev_screw_len_1=?, dev_screw_len_2=?, dev_screw_wid_1=?, dev_screw_wid_2=?,
            
            notes=?
            WHERE id=?";

        $stmt = $conn->prepare($sql);
        
        // Prepare Params Array (PDO Style)
        $params = [
            $_POST['product_type'], $_POST['product_number'], $property_code, $status, $prod_date, $sent_date,
            
            $_POST['check_cleaning']??'-', $_POST['check_painting']??'-', $_POST['check_surface']??'-', $_POST['check_cracks']??'-', 
            $_POST['check_curing']??'-', $_POST['check_temp_humidity']??'-', $_POST['check_plastic_cover']??'-', $_POST['check_rest_period']??'-',
            $_POST['check_trestle_strength']??'-', $_POST['check_packaging']??'-', $_POST['check_packing_method']??'-', 
            $_POST['check_facade_appearance']??'-', $_POST['check_warping_visual']??'-', $_POST['check_qc_final']??'-', $_POST['check_initial_drying']??'-',
            
            $_POST['dev_length_1']?:0, $_POST['dev_length_2']?:0,
            $_POST['dev_width_1']?:0, $_POST['dev_width_2']?:0,
            $_POST['dev_thickness_1']?:0, $_POST['dev_thickness_2']?:0,
            $_POST['dev_bowing_length_1']?:0, $_POST['dev_bowing_length_2']?:0,
            $_POST['dev_bowing_width_1']?:0, $_POST['dev_bowing_width_2']?:0,

            $_POST['dev_diameter_1']?:null, $_POST['dev_diameter_2']?:null,
            $_POST['dev_screw_len_1']?:null, $_POST['dev_screw_len_2']?:null,
            $_POST['dev_screw_wid_1']?:null, $_POST['dev_screw_wid_2']?:null,

            $_POST['notes'],
            $id
        ];

        try {
            if ($stmt->execute($params)) {
                $message = "<div class='alert alert-success'>رکورد ویرایش شد. <a href='qc_dashboard.php'>بازگشت به داشبورد</a></div>";
            } else {
                $message = "<div class='alert alert-danger'>خطا در ویرایش.</div>";
            }
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>خطای پایگاه داده: " . $e->getMessage() . "</div>";
        }
    }
}

// --- 2. FETCH EXISTING DATA (PDO Style) ---
$stmt = $conn->prepare("SELECT * FROM qc_inspections WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("Record not found (ID: $id). <a href='qc_dashboard.php'>بازگشت</a>");
}

// --- 3. PREPARE DROPDOWNS ---
$types_array = []; $standards_map = []; 
$std_res = $conn->query("SELECT * FROM product_standards");
while ($r = $std_res->fetch(PDO::FETCH_ASSOC)) {
    $types_array[] = $r['type_name'];
    $standards_map[$r['type_name']] = ['l'=>$r['length_mm'], 'w'=>$r['width_mm'], 'h'=>20];
}

$pageTitle = "ویرایش رکورد QC #$id";
require_once __DIR__ . '/header_ghom.php';
?>

<style>
    @font-face { font-family: "Samim"; src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"); }
    body { font-family: "Samim", sans-serif; background: #f8fafc; }
    .card { box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: none; }
    .section-title { border-bottom: 2px solid #1e40af; padding-bottom: 10px; margin-bottom: 20px; color: #1e40af; }
    .critical-section { border: 2px solid #fca5a5; background-color: #fff1f2; }
    .required-star { color: red; }
</style>

<link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
<script src="/ghom/assets/js/jalalidatepicker.min.js"></script>

<div class="container mt-4" dir="rtl">
    <?= $message ?>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>ویرایش اطلاعات (ID: <?= $id ?>)</h4>
        <a href="qc_dashboard.php" class="btn btn-outline-secondary">بازگشت</a>
    </div>

    <form method="POST" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update">
        
        <!-- 1. Identification -->
        <div class="card p-3 mb-3">
            <h5 class="section-title">مشخصات قطعه</h5>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label"><span class="required-star">*</span>نوع قطعه</label>
                    <input type="text" class="form-control" name="product_type" list="type_list" value="<?= htmlspecialchars($row['product_type'] ?? '') ?>" required>
                    <datalist id="type_list"><?php foreach($types_array as $t) echo "<option value='$t'>"; ?></datalist>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label"><span class="required-star">*</span>شماره قطعه</label>
                    <input type="text" class="form-control" name="product_number" value="<?= htmlspecialchars($row['product_number'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">کد ویژگی (Property Code)</label>
                    <input type="text" class="form-control" name="property_code" value="<?= htmlspecialchars($row['property_code'] ?? '') ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><span class="required-star">*</span>تاریخ تولید</label>
                    <input type="text" class="form-control" data-jdp name="production_date" value="<?= g2j($row['production_date']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">تاریخ ارسال</label>
                    <input type="text" class="form-control" data-jdp name="sent_date" value="<?= g2j($row['sent_date']) ?>">
                </div>
            </div>
            <!-- Hidden inputs for auto-calc logic if needed -->
            <input type="hidden" name="dim_l" id="dim_l"><input type="hidden" name="dim_w" id="dim_w"><input type="hidden" name="dim_h" id="dim_h">
        </div>

        <!-- 2. Critical Checks -->
        <div class="card p-3 mb-3 critical-section">
            <h5 class="section-title text-danger">کنترل ابعاد و خیز (اجباری)</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">انحراف طول</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_length_1" value="<?= $row['dev_length_1'] ?>" required>
                        <input type="number" step="0.1" class="form-control" name="dev_length_2" value="<?= $row['dev_length_2'] ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">انحراف عرض</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_width_1" value="<?= $row['dev_width_1'] ?>" required>
                        <input type="number" step="0.1" class="form-control" name="dev_width_2" value="<?= $row['dev_width_2'] ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">انحراف ضخامت</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_thickness_1" value="<?= $row['dev_thickness_1'] ?>" required>
                        <input type="number" step="0.1" class="form-control" name="dev_thickness_2" value="<?= $row['dev_thickness_2'] ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">کمانش طول</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_bowing_length_1" value="<?= $row['dev_bowing_length_1'] ?>" required>
                        <input type="number" step="0.1" class="form-control" name="dev_bowing_length_2" value="<?= $row['dev_bowing_length_2'] ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">کمانش عرض</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_bowing_width_1" value="<?= $row['dev_bowing_width_1'] ?>" required>
                        <input type="number" step="0.1" class="form-control" name="dev_bowing_width_2" value="<?= $row['dev_bowing_width_2'] ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Optional Checks -->
        <div class="card p-3 mb-3">
            <h5 class="section-title text-secondary">قطعات مدفون (اختیاری)</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">انحراف قطر</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_diameter_1" value="<?= $row['dev_diameter_1'] ?>">
                        <input type="number" step="0.1" class="form-control" name="dev_diameter_2" value="<?= $row['dev_diameter_2'] ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">پیچ (طول)</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_screw_len_1" value="<?= $row['dev_screw_len_1'] ?>">
                        <input type="number" step="0.1" class="form-control" name="dev_screw_len_2" value="<?= $row['dev_screw_len_2'] ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">پیچ (عرض)</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_screw_wid_1" value="<?= $row['dev_screw_wid_1'] ?>">
                        <input type="number" step="0.1" class="form-control" name="dev_screw_wid_2" value="<?= $row['dev_screw_wid_2'] ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Visual Checks -->
        <div class="card p-3 mb-3">
            <h5 class="section-title">کنترل‌های چشمی</h5>
            <div class="row">
                <?php 
                $visuals = [
                    'check_cleaning' => 'نظافت سطح', 'check_painting' => 'اجرای رنگ', 
                    'check_surface' => 'کیفیت سطح', 'check_cracks' => 'ترک‌های سطحی',
                    'check_curing' => 'عمل‌آوری', 'check_temp_humidity' => 'دما و رطوبت',
                    'check_plastic_cover' => 'پوشش پلاستیکی', 'check_rest_period' => 'دوره استراحت',
                    'check_qc_final' => 'QC نهایی', 'check_initial_drying' => 'خشک شدن اولیه',
                    'check_packaging' => 'بسته بندی', 'check_packing_method' => 'نحوه چیدمان',
                    'check_trestle_strength' => 'استحکام خرک', 'check_facade_appearance' => 'نمای ظاهری',
                    'check_warping_visual' => 'پیچیدگی (چشمی)',
                ];
                foreach ($visuals as $name => $label): 
                    $val = $row[$name] ?? '-';
                ?>
                <div class="col-md-3 mb-3">
                    <label class="form-label"><?= $label ?></label>
                    <select class="form-select" name="<?= $name ?>">
                        <option value="-" <?= $val=='-'?'selected':'' ?>>-</option>
                        <option value="OK" <?= $val=='OK'?'selected':'' ?>>OK</option>
                        <option value="NOK" <?= $val=='NOK'?'selected':'' ?>>NOK</option>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card p-3 mb-3">
            <label class="form-label">توضیحات</label>
            <textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars($row['notes'] ?? '') ?></textarea>
        </div>

        <div class="d-flex justify-content-between">
            <!-- DELETE BUTTON -->
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                🗑️ حذف رکورد
            </button>
            
            <!-- UPDATE BUTTON -->
            <button type="submit" class="btn btn-primary px-5 fw-bold">
                💾 ذخیره تغییرات
            </button>
        </div>
    </form>
    
    <!-- Hidden Delete Form -->
    <form id="deleteForm" method="POST" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete">
    </form>

</div>

<script>
    jalaliDatepicker.startWatch();
    
    function confirmDelete() {
        if(confirm("آیا از حذف این رکورد اطمینان دارید؟ این عملیات غیرقابل بازگشت است.")) {
            document.getElementById('deleteForm').submit();
        }
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>