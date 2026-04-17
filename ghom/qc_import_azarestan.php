<?php
// qc_import_azarestan.php - نسخه اصلاح شده برای فایل CSV جدید
require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

if (!isLoggedIn()) { die("دسترسی غیرمجاز"); }

$conn = getProjectDBConnection("ghom");
$message = "";

// تابع کمکی برای تمیز کردن اعداد (تبدیل خط تیره و خالی به صفر)
function cleanNum($val) {
    $val = trim($val);
    if ($val === '' || $val === '-' || $val === '_') return 0;
    return floatval($val);
}

// تابع تشخیص و تبدیل تاریخ
function parseDate($dateStr) {
    $dateStr = trim($dateStr);
    if (empty($dateStr) || $dateStr == '-') return null;
    
    // نرمال سازی جداکننده ها
    $dateStr = str_replace(['-', '.'], '/', $dateStr);
    $parts = explode('/', $dateStr);
    
    if (count($parts) !== 3) return null;
    
    $y = (int)$parts[0];
    $m = (int)$parts[1];
    $d = (int)$parts[2];

    // اگر سال بزرگتر از 1900 باشد، یعنی میلادی است
    if ($y > 1900) {
        return "$y-$m-$d";
    }
    // اگر سال شمسی است، تبدیل کن
    $g = jalali_to_gregorian($y, $m, $d);
    return implode('-', $g);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    
    if (($handle = fopen($file, "r")) !== FALSE) {
        
        // خواندن هدر (سطر اول) و رد کردن آن
        $header = fgetcsv($handle); 

        $count = 0;
        $errCount = 0;

        $sql = "INSERT INTO qc_inspections (
            factory_name, product_type, product_number, property_code, status, production_date, sent_date,
            
            dev_length_1, dev_length_2, 
            dev_width_1, dev_width_2, 
            dev_thickness_1, dev_thickness_2, 
            dev_bowing_length_1, dev_bowing_length_2, 
            dev_bowing_width_1, dev_bowing_width_2, 
            dev_diameter_1, dev_diameter_2, 
            dev_screw_len_1, dev_screw_len_2, 
            dev_screw_wid_1, dev_screw_wid_2,

            check_facade_appearance, check_warping_visual, check_surface, check_cracks, 
            check_curing, check_plastic_cover, check_temp_humidity, check_rest_period, 
            check_qc_final, check_cleaning, check_painting, check_initial_drying, 
            check_packaging, check_trestle_strength, check_packing_method,

            inspector_name, notes, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, NOW()
        )";

        $stmt = $conn->prepare($sql);

        while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
            // اگر سطر خالی بود رد شو
            if (count($row) < 5) continue;

            // نگاشت داده‌ها طبق فایل جدید شما
            // [0] factory_name
            // [1] product_type
            // [2] product_number
            // [3] property_code
            // [4] status
            // [5] production_date
            // [6] sent_date
            // [7] dev_length_1 ...

            $factory_name = trim($row[0]);
            // اگر نام کارخانه خالی بود، پیش فرض آذرستان بگذار
            if(empty($factory_name)) $factory_name = 'منتخب عمران - آذرستان';

            $pType = trim($row[1]);
            $pNum  = trim($row[2]);
            $propCode = trim($row[3]);
            
            // اصلاح وضعیت
            $statusRaw = strtolower(trim($row[4]));
            $status = 'stock'; // پیش فرض
            if ($statusRaw == 'send' || $statusRaw == 'sent') $status = 'sent';
            if ($statusRaw == 'rejected') $status = 'rejected';

            $prodDate = parseDate($row[5]);
            $sentDate = parseDate($row[6]);

            // مقادیر عددی (از ستون 7 تا 22)
            $d_l1 = cleanNum($row[7]);
            $d_l2 = cleanNum($row[8]);
            $d_w1 = cleanNum($row[9]);
            $d_w2 = cleanNum($row[10]);
            $d_t1 = cleanNum($row[11]);
            $d_t2 = cleanNum($row[12]);
            $d_bl1 = cleanNum($row[13]);
            $d_bl2 = cleanNum($row[14]);
            $d_bw1 = cleanNum($row[15]);
            $d_bw2 = cleanNum($row[16]);
            $d_dia1 = cleanNum($row[17]);
            $d_dia2 = cleanNum($row[18]);
            $d_sl1 = cleanNum($row[19]);
            $d_sl2 = cleanNum($row[20]);
            $d_sw1 = cleanNum($row[21]);
            $d_sw2 = cleanNum($row[22]);

            // مقادیر متنی چک لیست (از ستون 23 تا 37)
            // اگر خالی بود یا خط تیره، OK در نظر می گیریم (مگر اینکه Rejected باشد)
            $checks = [];
            for($i=23; $i<=37; $i++) {
                $val = strtoupper(trim($row[$i] ?? ''));
                if($val == 'NOK') $checks[] = 'NOK';
                else $checks[] = 'OK';
            }

            $inspector = trim($row[38] ?? 'System');
            $notes = trim($row[39] ?? '');

            try {
                $stmt->execute([
                    $factory_name, $pType, $pNum, $propCode, $status, $prodDate, $sentDate,
                    $d_l1, $d_l2, $d_w1, $d_w2, $d_t1, $d_t2,
                    $d_bl1, $d_bl2, $d_bw1, $d_bw2,
                    $d_dia1, $d_dia2, $d_sl1, $d_sl2, $d_sw1, $d_sw2,
                    $checks[0], $checks[1], $checks[2], $checks[3], $checks[4],
                    $checks[5], $checks[6], $checks[7], $checks[8], $checks[9],
                    $checks[10], $checks[11], $checks[12], $checks[13], $checks[14],
                    $inspector, $notes
                ]);
                $count++;
            } catch (Exception $e) {
                // اگر خطایی رخ داد (مثلا فرمت دیتای غلط)
                $errCount++;
                // error_log($e->getMessage()); // برای دیباگ
            }
        }
        fclose($handle);
        $message = "تعداد $count رکورد با موفقیت وارد شد. ($errCount خطا)";
    } else {
        $message = "خطا در باز کردن فایل.";
    }
}

require_once __DIR__ . '/header_ghom.php';
?>

<div class="container mt-5" dir="rtl">
    <div class="card shadow">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">ایمپورت داده‌های آذرستان (فرمت جدید - اصلاح شده)</h5>
        </div>
        <div class="card-body">
            <?php if($message): ?>
                <div class="alert alert-info fw-bold"><?= $message ?></div>
            <?php endif; ?>
            
            <div class="alert alert-warning small">
                <strong>نکته مهم:</strong> فایل CSV باید دقیقاً مطابق ساختار دیتای ارسالی شما باشد (ستون‌های اول نام کارخانه و تیپ، سپس تاریخ‌ها و بعد اعداد انحراف).
                <br>مقادیر "-" یا خالی در ستون‌های عددی به صورت خودکار به 0 تبدیل می‌شوند.
            </div>

            <form method="post" enctype="multipart/form-data">
                <?= csrfField() ?>
                <div class="mb-4">
                    <label class="form-label">فایل CSV را انتخاب کنید</label>
                    <input type="file" name="csv_file" class="form-control" required accept=".csv">
                </div>
                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-success px-5">شروع ایمپورت</button>
                    <a href="qc_dashboard.php" class="btn btn-secondary">بازگشت به داشبورد</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>