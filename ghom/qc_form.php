<?php
//qc_form.php
// --- Configuration & Auth ---
function isMobileDevice() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}
//if (isMobileDevice()) { header('Location: mobile.php'); exit(); }

require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

if (!isLoggedIn()) { header('Location: /login.php?msg=login_required'); exit(); }
if (!in_array($_SESSION['role'], ['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])) { http_response_code(403); require 'Access_Denied.php'; exit; }

$conn = getProjectDBConnection("ghom");
$message = "";

// --- 1. FETCH DATA FOR AUTOCOMPLETE ---
$types_array = [];
$standards_map = []; 
$std_res = $conn->query("SELECT * FROM product_standards");
if ($std_res) {
    while ($row = $std_res->fetch(PDO::FETCH_ASSOC)) {
        $types_array[] = $row['type_name'];
        $standards_map[$row['type_name']] = [
            'l' => $row['length_mm'], 'w' => $row['width_mm'], 'h' => 20 
        ];
    }
}
$used_types = $conn->query("SELECT DISTINCT product_type FROM qc_inspections ORDER BY product_type ASC");
while ($row = $used_types->fetch(PDO::FETCH_ASSOC)) {
    if (!in_array($row['product_type'], $types_array) && !empty($row['product_type'])) $types_array[] = $row['product_type'];
}
$numbers_array = [];
$num_stmt = $conn->query("SELECT DISTINCT product_number FROM qc_inspections ORDER BY id DESC LIMIT 500");
while ($row = $num_stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!empty($row['product_number'])) $numbers_array[] = $row['product_number'];
}
$props_array = [];
$prop_stmt = $conn->query("SELECT DISTINCT property_code FROM qc_inspections ORDER BY id DESC LIMIT 500");
while ($row = $prop_stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!empty($row['property_code'])) $props_array[] = $row['property_code'];
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    function j2g($jDate) {
        if (empty($jDate)) return null;
        $parts = explode('/', $jDate);
        if (count($parts) !== 3) return null;
        $gDate = jalali_to_gregorian((int)$parts[0], (int)$parts[1], (int)$parts[2]);
        return implode('-', $gDate);
    }

    $prod_date = j2g($_POST['production_date']);
    $sent_date = j2g($_POST['sent_date']);
    
    // Auto-Generate Property Code
    $property_code = $_POST['property_code'];
    if (empty($property_code)) {
        $property_code = $_POST['product_type'] . " - " . $_POST['dim_l'] . "*" . $_POST['dim_w'] . "*" . $_POST['dim_h'];
    }

    // UPDATED SQL: Added factory_name
    $sql = "INSERT INTO qc_inspections (
        factory_name, product_type, product_number, property_code, status, production_date, sent_date,
        
        check_cleaning, check_painting, check_surface, check_cracks, 
        check_curing, check_temp_humidity, check_plastic_cover, check_rest_period,
        check_trestle_strength, check_packaging, check_packing_method, 
        check_facade_appearance, check_warping_visual, check_qc_final, check_initial_drying,
        
        dev_length_1, dev_length_2, dev_width_1, dev_width_2, dev_thickness_1, dev_thickness_2, 
        dev_bowing_length_1, dev_bowing_length_2, dev_bowing_width_1, dev_bowing_width_2,
        
        dev_diameter_1, dev_diameter_2, dev_screw_len_1, dev_screw_len_2, dev_screw_wid_1, dev_screw_wid_2,
        
        inspector_name, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $status = 'pending'; 
    $inspector = $_SESSION['username'] ?? 'Unknown';

    // Params with "?? '-'" to fix Undefined Warning
    $params = [
        $_POST['factory_name'], // Added Factory Name first
        $_POST['product_type'], $_POST['product_number'], $property_code, $status, $prod_date, $sent_date,
        
        // Visuals
        $_POST['check_cleaning'] ?? '-', 
        $_POST['check_painting'] ?? '-', 
        $_POST['check_surface'] ?? '-', 
        $_POST['check_cracks'] ?? '-', 
        $_POST['check_curing'] ?? '-', 
        $_POST['check_temp_humidity'] ?? '-', 
        $_POST['check_plastic_cover'] ?? '-', 
        $_POST['check_rest_period'] ?? '-',
        $_POST['check_trestle_strength'] ?? '-', 
        $_POST['check_packaging'] ?? '-', 
        $_POST['check_packing_method'] ?? '-', 
        $_POST['check_facade_appearance'] ?? '-', 
        $_POST['check_warping_visual'] ?? '-',
        $_POST['check_qc_final'] ?? '-',
        $_POST['check_initial_drying'] ?? '-',
        
        // Critical Dimensions
        $_POST['dev_length_1']?:0, $_POST['dev_length_2']?:0,
        $_POST['dev_width_1']?:0, $_POST['dev_width_2']?:0,
        $_POST['dev_thickness_1']?:0, $_POST['dev_thickness_2']?:0,
        $_POST['dev_bowing_length_1']?:0, $_POST['dev_bowing_length_2']?:0,
        $_POST['dev_bowing_width_1']?:0, $_POST['dev_bowing_width_2']?:0,

        // Optional Embedded
        $_POST['dev_diameter_1']?:null, $_POST['dev_diameter_2']?:null,
        $_POST['dev_screw_len_1']?:null, $_POST['dev_screw_len_2']?:null,
        $_POST['dev_screw_wid_1']?:null, $_POST['dev_screw_wid_2']?:null,

        $inspector, $_POST['notes']
    ];

    try {
        if ($stmt->execute($params)) {
            $message = "<div class='alert alert-success'>اطلاعات با موفقیت ثبت شد.</div>";
        } else {
            $message = "<div class='alert alert-danger'>خطا در ثبت اطلاعات.</div>";
        }
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>خطای پایگاه داده: " . $e->getMessage() . "</div>";
    }
}

$pageTitle = "فرم ثبت کنترل کیفی";
require_once __DIR__ . '/header_ghom.php';
?>

<style>
    @font-face { font-family: "Samim"; src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"); }
    body { font-family: "Samim", sans-serif; background: var(--background); color: var(--text-primary); }
    .card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; box-shadow: var(--shadow); padding: 20px; margin-bottom: 20px; }
    .form-label { font-weight: bold; font-size: 0.9rem; color: var(--text-secondary); }
    .section-title { border-bottom: 2px solid var(--primary); padding-bottom: 10px; margin-bottom: 20px; color: var(--primary); font-size: 1.1rem; }
    
    .critical-section { border: 1px solid #fca5a5; background-color: #fef2f2; }
    .critical-title { color: #dc2626; border-bottom-color: #dc2626; }
    .required-star { color: red; margin-right: 3px; }
</style>

<link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
<script src="/ghom/assets/js/jalalidatepicker.min.js"></script>

<div class="container mt-4" dir="rtl">
    <?= $message ?>
    <form method="POST" action="">
        <!-- 1. Identification -->
        <div class="card">
            <h5 class="section-title">مشخصات قطعه (اجباری)</h5>
            <div class="row">
                <!-- Added Factory Name -->
                <div class="col-md-6 mb-3">
                    <label class="form-label"><span class="required-star">*</span>نام کارخانه</label>
                    <select class="form-select" name="factory_name" required>
                        <option value="منتخب عمران (تبریز)">منتخب عمران (تبریز)</option>
                        <option value="موزاییک میبد یزد">موزاییک میبد یزد</option>
                    </select>
                </div>

                <div class="col-md-3 mb-3">
                    <label class="form-label"><span class="required-star">*</span>نوع قطعه (Type)</label>
                    <input type="text" class="form-control" name="product_type" id="product_type" list="type_list" required autocomplete="off">
                    <datalist id="type_list"><?php foreach($types_array as $t) echo "<option value='$t'>"; ?></datalist>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label"><span class="required-star">*</span>شماره قطعه</label>
                    <input type="text" class="form-control" name="product_number" list="number_list" required autocomplete="off">
                    <datalist id="number_list"><?php foreach($numbers_array as $n) echo "<option value='$n'>"; ?></datalist>
                </div>
                
                <!-- Auto Dimensions -->
                <div class="col-md-2 mb-3">
                    <label class="form-label">طول اسمی</label>
                    <input type="number" class="form-control bg-light" name="dim_l" id="dim_l">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">عرض اسمی</label>
                    <input type="number" class="form-control bg-light" name="dim_w" id="dim_w">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">ضخامت اسمی</label>
                    <input type="number" class="form-control bg-light" name="dim_h" id="dim_h" value="20">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">کد ویژگی (Property Code)</label>
                    <input type="text" class="form-control" name="property_code" id="property_code" list="prop_list">
                    <datalist id="prop_list"><?php foreach($props_array as $p) echo "<option value='$p'>"; ?></datalist>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><span class="required-star">*</span>تاریخ تولید</label>
                    <input type="text" class="form-control" data-jdp name="production_date" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">تاریخ ارسال</label>
                    <input type="text" class="form-control" data-jdp name="sent_date">
                </div>
            </div>
        </div>

        <!-- 2. CRITICAL CHECKS -->
        <div class="card critical-section">
            <h5 class="section-title critical-title">کنترل ابعاد و خیز (اجباری برای ارسال)</h5>
            <div class="alert alert-danger py-2 small">
                <strong>توجه:</strong> پر کردن این بخش الزامی است (حتی اگر مقدار 0 باشد).
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><span class="required-star">*</span>انحراف طول</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_length_1" placeholder="1" required>
                        <input type="number" step="0.1" class="form-control" name="dev_length_2" placeholder="2" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><span class="required-star">*</span>انحراف عرض</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_width_1" placeholder="1" required>
                        <input type="number" step="0.1" class="form-control" name="dev_width_2" placeholder="2" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><span class="required-star">*</span>انحراف ضخامت</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_thickness_1" placeholder="1" required>
                        <input type="number" step="0.1" class="form-control" name="dev_thickness_2" placeholder="2" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><span class="required-star">*</span>کمانش طول</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_bowing_length_1" placeholder="1" required>
                        <input type="number" step="0.1" class="form-control" name="dev_bowing_length_2" placeholder="2" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><span class="required-star">*</span>کمانش عرض</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_bowing_width_1" placeholder="1" required>
                        <input type="number" step="0.1" class="form-control" name="dev_bowing_width_2" placeholder="2" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. OPTIONAL EMBEDDED -->
        <div class="card">
            <h5 class="section-title text-secondary">کنترل اتصالات و قطعات مدفون (اختیاری)</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">انحراف قطر</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_diameter_1" placeholder="1">
                        <input type="number" step="0.1" class="form-control" name="dev_diameter_2" placeholder="2">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">انحراف پیچ (طول)</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_screw_len_1" placeholder="1">
                        <input type="number" step="0.1" class="form-control" name="dev_screw_len_2" placeholder="2">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">انحراف پیچ (عرض)</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="dev_screw_wid_1" placeholder="1">
                        <input type="number" step="0.1" class="form-control" name="dev_screw_wid_2" placeholder="2">
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. VISUAL CHECKS (ALL INCLUDED) -->
        <div class="card">
            <h5 class="section-title">کنترل‌های چشمی (Visual)</h5>
            <div class="row">
                <?php 
                $visuals = [
                    'check_cleaning' => 'نظافت سطح',
                    'check_painting' => 'اجرای رنگ',
                    'check_surface' => 'کیفیت سطح',
                    'check_cracks' => 'ترک‌های سطحی',
                    'check_curing' => 'عمل‌آوری (Curing)',
                    'check_temp_humidity' => 'دما و رطوبت',
                    'check_plastic_cover' => 'پوشش پلاستیکی',
                    'check_rest_period' => 'دوره استراحت',
                    'check_qc_final' => 'QC نهایی',
                    'check_initial_drying' => 'خشک شدن اولیه',
                    'check_packaging' => 'بسته بندی',
                    'check_packing_method' => 'نحوه چیدمان',
                    'check_trestle_strength' => 'استحکام خرک',
                    'check_facade_appearance' => 'نمای ظاهری',
                    'check_warping_visual' => 'پیچیدگی (چشمی)',
                ];
                foreach ($visuals as $name => $label): ?>
                <div class="col-md-3 mb-3">
                    <label class="form-label"><?= $label ?></label>
                    <select class="form-select" name="<?= $name ?>">
                        <option value="-">-</option>
                        <option value="OK">OK</option>
                        <option value="NOK">NOK</option>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="mb-3">
                <label class="form-label">توضیحات</label>
                <textarea class="form-control" name="notes" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold">ثبت نهایی اطلاعات</button>
        </div>
    </form>
</div>

<script>
    jalaliDatepicker.startWatch();
    const standardsMap = <?= json_encode($standards_map) ?>;
    const typeInput = document.getElementById('product_type');
    const dimL = document.getElementById('dim_l');
    const dimW = document.getElementById('dim_w');
    const dimH = document.getElementById('dim_h');
    const propInput = document.getElementById('property_code');

    function updatePropertyCode() {
        const t = typeInput.value.trim();
        const l = dimL.value.trim();
        const w = dimW.value.trim();
        const h = dimH.value.trim();
        if(t && l && w && h) propInput.value = `${t} - ${l}*${w}*${h}`;
    }

    typeInput.addEventListener('input', function() {
        if (standardsMap[this.value]) {
            dimL.value = standardsMap[this.value].l;
            dimW.value = standardsMap[this.value].w;
            dimH.value = standardsMap[this.value].h;
        }
        updatePropertyCode();
    });
    dimL.addEventListener('input', updatePropertyCode);
    dimW.addEventListener('input', updatePropertyCode);
    dimH.addEventListener('input', updatePropertyCode);
</script>

<?php require_once __DIR__ . '/footer.php'; ?>