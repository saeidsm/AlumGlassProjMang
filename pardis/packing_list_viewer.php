<?php
//public_html/pardis/packing_list_viewer.php
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'superuser', 'user', 'supervisor', 'planner'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

$expected_project_key = 'pardis';

if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    logError("User ID " . ($_SESSION['user_id'] ?? 'N/A') . " tried to access pardis project page without correct session context.");
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}

$user_role = $_SESSION['role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? 0;

// Get user's full name
$user_name = 'کاربر';
try {
    $commonPdo = getCommonDBConnection();
    $stmt = $commonPdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $first_name = trim($user['first_name'] ?? '');
        $last_name = trim($user['last_name'] ?? '');
        if (!empty($first_name) && !empty($last_name)) {
            $user_name = $first_name . ' ' . $last_name;
        } elseif (!empty($first_name)) {
            $user_name = $first_name;
        } elseif (!empty($last_name)) {
            $user_name = $last_name;
        }
    }
} catch (Exception $e) {
    logError("Error fetching user name: " . $e->getMessage());
}

try {
    $pdo = getProjectDBConnection('pardis');
} catch (PDOException $e) {
    logError("Database connection failed: " . $e->getMessage());
    die("Database connection error");
}

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Keep original Jalali dates for display
$start_date_display = $start_date;
$end_date_display = $end_date;

// Initialize all arrays
$profiles_grouped = [];
$accessories_grouped = [];
$remaining_profiles = [];
$remaining_accessories = [];
$profile_summary = [];
$accessory_summary = [];

try {
    // ==========================================
    // CHECK DATE FORMAT AND CONVERT IF NEEDED
    // ==========================================
    
    // Function to convert Jalali date to Gregorian (YYYY-MM-DD)
    function jalaliToGregorian($jalali_date) {
        if (empty($jalali_date)) return '';
        
        // Handle format: 1404/07/15 or 1404-07-15
        $jalali_date = str_replace('/', '-', $jalali_date);
        $parts = explode('-', $jalali_date);
        
        if (count($parts) !== 3) return $jalali_date;
        
        list($j_y, $j_m, $j_d) = $parts;
        
        if (function_exists('jalali_to_gregorian')) {
            $g = jalali_to_gregorian($j_y, $j_m, $j_d);
            $gregorian = sprintf('%04d-%02d-%02d', $g[0], $g[1], $g[2]);
            error_log("Converted Jalali $jalali_date to Gregorian $gregorian");
            return $gregorian;
        }
        
        return $jalali_date;
    }
    
    // Convert dates if they are in Jalali format
    if (!empty($start_date)) {
        // Check if it's Jalali (year > 1400)
        $year = (int)substr($start_date, 0, 4);
        if ($year > 1400) {
            $start_date = jalaliToGregorian($start_date);
            error_log("Start date converted to: $start_date");
        }
    }
    
    if (!empty($end_date)) {
        $year = (int)substr($end_date, 0, 4);
        if ($year > 1400) {
            $end_date = jalaliToGregorian($end_date);
            error_log("End date converted to: $end_date");
        }
    }
    
    // ==========================================
    // BUILD WHERE CLAUSES WITH PARAMETERS
    // ==========================================
    $profile_where = '';
    $profile_params = [];
    
    if (!empty($start_date) && !empty($end_date)) {
        $profile_where = " WHERE p.receipt_date BETWEEN ? AND ? ";
        $profile_params = [$start_date, $end_date];
        error_log("Profile WHERE: BETWEEN $start_date AND $end_date");
    } elseif (!empty($start_date)) {
        $profile_where = " WHERE p.receipt_date >= ? ";
        $profile_params = [$start_date];
        error_log("Profile WHERE: >= $start_date");
    } elseif (!empty($end_date)) {
        $profile_where = " WHERE p.receipt_date <= ? ";
        $profile_params = [$end_date];
        error_log("Profile WHERE: <= $end_date");
    } else {
        error_log("Profile WHERE: NO FILTER");
    }
    
    $accessory_where = '';
    $accessory_params = [];
    
    if (!empty($start_date) && !empty($end_date)) {
        $accessory_where = " WHERE a.receipt_date BETWEEN ? AND ? ";
        $accessory_params = [$start_date, $end_date];
        error_log("Accessory WHERE: BETWEEN $start_date AND $end_date");
    } elseif (!empty($start_date)) {
        $accessory_where = " WHERE a.receipt_date >= ? ";
        $accessory_params = [$start_date];
        error_log("Accessory WHERE: >= $start_date");
    } elseif (!empty($end_date)) {
        $accessory_where = " WHERE a.receipt_date <= ? ";
        $accessory_params = [$end_date];
        error_log("Accessory WHERE: <= $end_date");
    } else {
        error_log("Accessory WHERE: NO FILTER");
    }
    
    // ==========================================
    // DEBUG: Check sample dates in database
    // ==========================================
    $sample_check = $pdo->query("SELECT receipt_date FROM profiles ORDER BY receipt_date DESC LIMIT 5");
    $sample_dates = $sample_check->fetchAll(PDO::FETCH_COLUMN);
    error_log("Sample profile dates in DB: " . implode(', ', $sample_dates));
    
    $sample_check = $pdo->query("SELECT receipt_date FROM accessories ORDER BY receipt_date DESC LIMIT 5");
    $sample_dates = $sample_check->fetchAll(PDO::FETCH_COLUMN);
    error_log("Sample accessory dates in DB: " . implode(', ', $sample_dates));
    
    // ==========================================
    // QUERY 1: PROFILES WITH FILTERING
    // ==========================================
    $sql = "
        SELECT p.*, 
               COALESCE(SUM(it.quantity_taken), 0) as total_taken,
               (p.quantity - COALESCE(SUM(it.quantity_taken), 0)) as remaining_stock,
               pd.id as doc_id,
               pd.document_name,
               pd.document_path,
               pd.document_type,
               pd.upload_date
        FROM profiles p
        LEFT JOIN inventory_transactions it ON p.id = it.item_id AND it.item_type = 'profile'
        LEFT JOIN packing_documents pd ON p.id = pd.item_id AND pd.item_type = 'profile'
        $profile_where
        GROUP BY p.id, pd.id
        ORDER BY p.item_code, p.receipt_date DESC
    ";
    
    error_log("Profile SQL: " . $sql);
    error_log("Profile Params: " . json_encode($profile_params));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($profile_params);
    $profile_receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Profile receipts found: " . count($profile_receipts));
    
    // Group profiles by item_code
    foreach ($profile_receipts as $receipt) {
        $code = $receipt['item_code'];
        if (!isset($profiles_grouped[$code])) {
            $profiles_grouped[$code] = [
                'item_code' => $code,
                'image_file' => $receipt['image_file'],
                'receipts' => [],
                'total_received' => 0,
                'total_taken' => 0,
                'total_stock' => 0,
                'total_length_mm' => 0,
                'documents' => []
            ];
        }
        
        $receipt_key = $receipt['id'];
        if (!isset($profiles_grouped[$code]['receipts'][$receipt_key])) {
            $profiles_grouped[$code]['receipts'][$receipt_key] = [
                'id' => $receipt['id'],
                'receipt_date' => $receipt['receipt_date'],
                'quantity' => $receipt['quantity'],
                'length' => $receipt['length'],
                'uom' => $receipt['uom'],
                'taken' => $receipt['total_taken'],
                'stock' => $receipt['remaining_stock'],
                'sheet_name' => $receipt['sheet_name'],
                'column1_content' => $receipt['column1_content']
            ];
            
            $profiles_grouped[$code]['total_received'] += floatval($receipt['quantity']);
            $profiles_grouped[$code]['total_taken'] += floatval($receipt['total_taken']);
            $profiles_grouped[$code]['total_stock'] += floatval($receipt['remaining_stock']);
            
            $length_mm = floatval($receipt['length']) * floatval($receipt['quantity']);
            $profiles_grouped[$code]['total_length_mm'] += $length_mm;
        }
        
        if ($receipt['doc_id']) {
            $doc_key = $receipt['doc_id'];
            if (!isset($profiles_grouped[$code]['documents'][$doc_key])) {
                $profiles_grouped[$code]['documents'][$doc_key] = [
                    'id' => $receipt['doc_id'],
                    'name' => $receipt['document_name'],
                    'path' => $receipt['document_path'],
                    'type' => $receipt['document_type'],
                    'upload_date' => $receipt['upload_date']
                ];
            }
        }
    }
    
    error_log("Profile groups created: " . count($profiles_grouped));
    
    // ==========================================
    // QUERY 2: ACCESSORIES WITH FILTERING
    // ==========================================
    $sql = "
        SELECT a.*, 
               COALESCE(SUM(it.quantity_taken), 0) as total_taken,
               (a.quantity - COALESCE(SUM(it.quantity_taken), 0)) as remaining_stock,
               pd.id as doc_id,
               pd.document_name,
               pd.document_path,
               pd.document_type,
               pd.upload_date
        FROM accessories a
        LEFT JOIN inventory_transactions it ON a.id = it.item_id AND it.item_type = 'accessory'
        LEFT JOIN packing_documents pd ON a.id = pd.item_id AND pd.item_type = 'accessory'
        $accessory_where
        GROUP BY a.id, pd.id
        ORDER BY a.item_code, a.receipt_date DESC
    ";
    
    error_log("Accessory SQL: " . $sql);
    error_log("Accessory Params: " . json_encode($accessory_params));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($accessory_params);
    $accessory_receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Accessory receipts found: " . count($accessory_receipts));
    
    // Group accessories by item_code
    foreach ($accessory_receipts as $receipt) {
        $code = $receipt['item_code'];
        if (!isset($accessories_grouped[$code])) {
            $accessories_grouped[$code] = [
                'item_code' => $code,
                'image_file' => $receipt['image_file'],
                'receipts' => [],
                'total_received' => 0,
                'total_taken' => 0,
                'total_stock' => 0,
                'documents' => []
            ];
        }
        
        $receipt_key = $receipt['id'];
        if (!isset($accessories_grouped[$code]['receipts'][$receipt_key])) {
            $accessories_grouped[$code]['receipts'][$receipt_key] = [
                'id' => $receipt['id'],
                'receipt_date' => $receipt['receipt_date'],
                'quantity' => $receipt['quantity'],
                'length' => $receipt['length'],
                'uom' => $receipt['uom'],
                'taken' => $receipt['total_taken'],
                'stock' => $receipt['remaining_stock'],
                'pallet_no' => $receipt['pallet_no'],
                'origin' => $receipt['origin'],
                'sheet_name' => $receipt['sheet_name']
            ];
            
            $accessories_grouped[$code]['total_received'] += floatval($receipt['quantity']);
            $accessories_grouped[$code]['total_taken'] += floatval($receipt['total_taken']);
            $accessories_grouped[$code]['total_stock'] += floatval($receipt['remaining_stock']);
        }
        
        if ($receipt['doc_id']) {
            $doc_key = $receipt['doc_id'];
            if (!isset($accessories_grouped[$code]['documents'][$doc_key])) {
                $accessories_grouped[$code]['documents'][$doc_key] = [
                    'id' => $receipt['doc_id'],
                    'name' => $receipt['document_name'],
                    'path' => $receipt['document_path'],
                    'type' => $receipt['document_type'],
                    'upload_date' => $receipt['upload_date']
                ];
            }
        }
    }
    
    error_log("Accessory groups created: " . count($accessories_grouped));
    
    // ==========================================
    // QUERY 3: REMAINING PROFILES (NO FILTER)
    // ==========================================
    $stmt = $pdo->query("SELECT * FROM remaining_profiles ORDER BY item_code");
    $remaining_profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ==========================================
    // QUERY 4: REMAINING ACCESSORIES (NO FILTER)
    // ==========================================
    $stmt = $pdo->query("SELECT * FROM remaining_accessories ORDER BY item_code");
    $remaining_accessories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ==========================================
    // QUERY 5: PROFILE SUMMARY (NO FILTER)
    // ==========================================
    $stmt = $pdo->query("
        SELECT 
            p.item_code,
            SUM(p.quantity) as total_received,
            COALESCE(SUM(it.quantity_taken), 0) as total_taken,
            (SUM(p.quantity) - COALESCE(SUM(it.quantity_taken), 0)) as stock,
            SUM(p.length * p.quantity) as total_length,
            COUNT(DISTINCT p.sheet_name) as sheet_count,
            GROUP_CONCAT(DISTINCT p.sheet_name) as sheets,
            MAX(p.image_file) as image_file
        FROM profiles p
        LEFT JOIN inventory_transactions it ON p.id = it.item_id AND it.item_type = 'profile'
        GROUP BY p.item_code
        ORDER BY p.item_code
    ");
    $profile_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ==========================================
    // QUERY 6: ACCESSORY SUMMARY (NO FILTER)
    // ==========================================
    $stmt = $pdo->query("
        SELECT 
            a.item_code,
            SUM(a.quantity) as total_received,
            COALESCE(SUM(it.quantity_taken), 0) as total_taken,
            (SUM(a.quantity) - COALESCE(SUM(it.quantity_taken), 0)) as stock,
            COUNT(DISTINCT a.sheet_name) as sheet_count,
            GROUP_CONCAT(DISTINCT a.sheet_name) as sheets,
            MAX(a.image_file) as image_file
        FROM accessories a
        LEFT JOIN inventory_transactions it ON a.id = it.item_id AND it.item_type = 'accessory'
        GROUP BY a.item_code
        ORDER BY a.item_code
    ");
    $accessory_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    logError("Error fetching data: " . $e->getMessage());
    error_log("PDO ERROR: " . $e->getMessage());
}

error_log("=== END DEBUG ===");

// Calculate statistics
$total_profile_length = 0;
$total_accessory_count = 0;
$total_profile_stock = 0;
$total_accessory_stock = 0;

foreach ($profiles_grouped as $profile) {
    $total_profile_length += floatval($profile['total_length_mm'] ?? 0);
    $total_profile_stock += floatval($profile['total_stock'] ?? 0);
}

foreach ($accessories_grouped as $accessory) {
    $total_accessory_count += floatval($accessory['total_received'] ?? 0);
    $total_accessory_stock += floatval($accessory['total_stock'] ?? 0);
}

// Jalali date conversion function
function toJalali($gregorian_date) {
    if (empty($gregorian_date)) return '-';
    
    $parts = explode('-', $gregorian_date);
    if (count($parts) !== 3) return $gregorian_date;
    
    list($y, $m, $d) = $parts;
    if (function_exists('gregorian_to_jalali')) {
        $j = gregorian_to_jalali($y, $m, $d);
        return $j[0] . '/' . str_pad($j[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($j[2], 2, '0', STR_PAD_LEFT);
    }
    
    return $gregorian_date;
}

$pageTitle = "گزارش پروفیل‌ها- پروژه دانشگاه خاتم پردیس";
function isMobileDevices() {
    // A simple but effective check for common mobile user agents
    return preg_match(
        "/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i",
        $_SERVER["HTTP_USER_AGENT"]
    );
}

// If a mobile device is detected, redirect to the mobile page and stop script execution
if (isMobileDevices()) {
    // Make sure the path to your mobile page is correct
    require_once __DIR__ . '/header.php';

}
else{require_once __DIR__ . '/header.php';

}


?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="/pardis/assets/css/packing_list_viewer.css">
</head>
<body>
    <div class="container">
    <h1>لیست بسته‌بندی - پروفیل‌ها و اکسسوری‌ها</h1>

    <div class="filter-section no-print">
        <h3>
            <span>📅</span>
            فیلتر گزارش بر اساس تاریخ دریافت
        </h3>
        <form method="GET" action="" class="filter-form">
            <div class="filter-group">
                <label>از تاریخ:</label>
                <input type="text" id="start_date_input" name="start_date" data-jdp readonly 
                    value="<?php echo htmlspecialchars($start_date_display); ?>" 
                    placeholder="انتخاب تاریخ شروع">
            </div>
            <div class="filter-group">
                <label>تا تاریخ:</label>
                <input type="text" id="end_date_input" name="end_date" data-jdp readonly 
                    value="<?php echo htmlspecialchars($end_date_display); ?>" 
                    placeholder="انتخاب تاریخ پایان">
            </div>
            <!-- NEW: Checkbox for including documents -->
            <div class="filter-group align-self-end">
                 <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="include_docs_input">
                    <label class="form-check-label text-white" for="include_docs_input">
                        شامل کردن اسناد
                    </label>
                </div>
            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn-filter btn-apply">
                    ✓ اعمال فیلتر
                </button>
                <a href="?" class="btn-filter btn-reset" style="text-decoration: none; display: inline-flex; align-items: center;">
                    ✕ حذف فیلتر
                </a>
                <!-- NEW: Print and ZIP buttons -->
                <button type="button" class="btn-filter btn-print" onclick="generateCurtainWallReport('print')">
                    🖨️ چاپ گزارش
                </button>
                <button type="button" class="btn-filter btn-print" style="background-color: #28a745;" onclick="generateCurtainWallReport('zip')">
                    <i class="bi bi-file-zip-fill"></i> دانلود ZIP
                </button>
            </div>
        </form>
    </div>
        
        <!-- Active Filter Display -->
        <?php if (!empty($start_date) || !empty($end_date)): ?>
        <div class="filter-active">
            <div class="filter-active-text">
                <strong>🔍 نمایش گزارش فیلتر شده:</strong>
                <span>از: <?php echo !empty($start_date_display) ? $start_date_display : 'ابتدا'; ?></span>
                <span>تا: <?php echo !empty($end_date_display) ? $end_date_display : 'امروز'; ?></span>
            </div>
            <div style="color: #065f46;">
                <strong>پروفیل‌ها: <?php echo count($profiles_grouped); ?> مورد</strong>
                |
                <strong>اکسسوری‌ها: <?php echo count($accessories_grouped); ?> مورد</strong>
            </div>
        </div>
        <?php endif; ?> 
  <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($profiles_grouped); ?></div>
                <div class="stat-label">کدهای پروفیل<?php echo (!empty($start_date) || !empty($end_date)) ? ' (فیلتر شده)' : ''; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $total_profile_length_filtered = 0;
                    foreach ($profiles_grouped as $item) {
                        $total_profile_length_filtered += $item['total_length_mm'];
                    }
                    echo number_format($total_profile_length_filtered / 1000, 2); 
                    ?>
                </div>
                <div class="stat-label">مجموع طول پروفیل‌ها (متر)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $total_profile_stock_filtered = 0;
                    foreach ($profiles_grouped as $item) {
                        $total_profile_stock_filtered += $item['total_stock'];
                    }
                    echo number_format($total_profile_stock_filtered, 0); 
                    ?>
                </div>
                <div class="stat-label">موجودی انبار پروفیل (عدد)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($accessories_grouped); ?></div>
                <div class="stat-label">کدهای اکسسوری<?php echo (!empty($start_date) || !empty($end_date)) ? ' (فیلتر شده)' : ''; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $total_accessory_stock_filtered = 0;
                    foreach ($accessories_grouped as $item) {
                        $total_accessory_stock_filtered += $item['total_stock'];
                    }
                    echo number_format($total_accessory_stock_filtered, 0); 
                    ?>
                </div>
                <div class="stat-label">موجودی انبار اکسسوری (عدد)</div>
            </div>
        </div>

        <div class="nav-tabs">
            <button class="nav-tab" onclick="showTab('profiles-detailed')">پروفیل‌ها (تفصیلی)</button>
            <button class="nav-tab" onclick="showTab('accessories-detailed')">اکسسوری‌ها (تفصیلی)</button>
            <button class="nav-tab" onclick="showTab('remaining-profiles')">پروفیل‌های باقی‌مانده</button>
            <button class="nav-tab" onclick="showTab('remaining-accessories')">اکسسوری‌های باقی‌مانده</button>
            <button class="nav-tab" onclick="showTab('summary_profile')">خلاصه پروفیل</button>
            <button class="nav-tab" onclick="showTab('summary_accesory')">خلاصه اکسسوری</button>
            <?php if (in_array($user_role, ['admin', 'superuser', 'supervisor'])): ?>
            <button class="nav-tab" onclick="showTab('add-items')">➕ افزودن قطعه</button>
            <button class="nav-tab" onclick="showTab('inventory-exit')">خروج از انبار</button>
            <?php endif; ?>
        </div>
        
        <!-- Profiles Tab -->
 <div id="profiles-detailed" class="tab-content active">
            <h2 style="margin-bottom: 20px;">
                پروفیل‌ها - نمایش تفصیلی دریافت‌ها
                <?php if (!empty($start_date) || !empty($end_date)): ?>
                <span style="color: #10b981; font-size: 16px;">(فیلتر اعمال شده)</span>
                <?php endif; ?>
            </h2>
            
            <div class="controls no-print">
    <div class="search-box">
        <input type="text" id="profileDetailSearch" placeholder="جستجو بر اساس کد قطعه..." onkeyup="filterProfilesDetailed()">
        <button onclick="expandAllProfiles()">باز کردن همه</button>
        <button onclick="collapseAllProfiles()">بستن همه</button>
        <?php if (in_array($user_role, ['admin', 'superuser', 'supervisor'])): ?>
        <button class="btn btn-success" onclick="openBulkUploadModal('profile')" style="background: #10b981;">
            📎 اختصاص سند به چند پروفیل
        </button>
        <?php endif; ?>
    </div>
</div>

            
            <div id="profilesDetailedContainer">
                <?php if (empty($profiles_grouped)): ?>
                <div style="text-align: center; padding: 60px; background: #fff3cd; border-radius: 8px; margin: 20px 0;">
                    <h3 style="color: #856404;">⚠️ هیچ پروفیلی در بازه زمانی انتخاب شده یافت نشد</h3>
                    <p style="color: #856404;">لطفاً بازه زمانی دیگری را انتخاب کنید</p>
                </div>
                <?php else: ?>
                <?php foreach ($profiles_grouped as $item): ?>
                <div class="receipt-group" data-itemcode="<?php echo htmlspecialchars($item['item_code']); ?>">
                    <div class="receipt-header" onclick="toggleReceiptDetails(this)">
                        <div style="display: flex; align-items: center; gap: 20px;">
                            <?php if ($item['image_file']): ?>
                                <img src="output/images/<?php echo htmlspecialchars($item['image_file']); ?>" 
                                     style="max-width: 60px; max-height: 50px; object-fit: contain;">
                            <?php endif; ?>
                            <span class="receipt-code"><?php echo htmlspecialchars($item['item_code']); ?></span>
                            <span class="expand-icon">▼</span>
                        </div>
                        <div class="receipt-summary">
                            <div class="receipt-summary-item">
                                <span class="receipt-summary-label">تعداد دریافت</span>
                                <span class="receipt-summary-value"><?php echo count($item['receipts']); ?></span>
                            </div>
                            <div class="receipt-summary-item">
                                <span class="receipt-summary-label">مجموع دریافتی</span>
                                <span class="receipt-summary-value"><?php echo number_format($item['total_received'], 2); ?></span>
                            </div>
                            <div class="receipt-summary-item">
                                <span class="receipt-summary-label">مجموع طول (متر)</span>
                                <span class="receipt-summary-value">
                                    <?php echo number_format($item['total_length_mm'] / 1000, 3); ?>
                                </span>
                            </div>
                            <div class="receipt-summary-item">
                                <span class="receipt-summary-label">خارج شده</span>
                                <span class="receipt-summary-value"><?php echo number_format($item['total_taken'], 2); ?></span>
                            </div>
                            <div class="receipt-summary-item">
                                <span class="receipt-summary-label">موجودی</span>
                                <span class="receipt-summary-value" style="color: #28a745;">
                                    <?php echo number_format($item['total_stock'], 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="receipt-details">
                        <table class="receipt-list">
                            <thead>
                                <tr>
                                    <th>تاریخ دریافت</th>
                                    <th>طول (mm)</th>
                                    <th>تعداد</th>
                                    <th>خارج شده</th>
                                    <th>موجودی</th>
                                    <th>واحد</th>
                                    <th>مجموع طول (mm)</th>
                                    <th>برگه</th>
                                    <th>توضیحات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($item['receipts'] as $receipt): ?>
                                <tr>
                                    <td><?php echo toJalali($receipt['receipt_date']); ?></td>
                                    <td><?php echo number_format(floatval($receipt['length']), 2); ?></td>
                                    <td><?php echo number_format(floatval($receipt['quantity']), 2); ?></td>
                                    <td><?php echo number_format(floatval($receipt['taken']), 2); ?></td>
                                    <td style="font-weight: bold; color: <?php echo $receipt['stock'] > 0 ? '#28a745' : '#dc3545'; ?>">
                                        <?php echo number_format(floatval($receipt['stock']), 2); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($receipt['uom']); ?></td>
                                    <td><?php echo number_format(floatval($receipt['length']) * floatval($receipt['quantity']), 2); ?></td>
                                    <td><span class="sheet-badge"><?php echo htmlspecialchars($receipt['sheet_name']); ?></span></td>
                                    <td style="font-size: 12px;"><?php echo htmlspecialchars($receipt['column1_content'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                         <?php if (!empty($item['documents'])): ?>
                <div class="document-section">
                    <strong>اسناد پیوست (<?php echo count($item['documents']); ?>):</strong>
                    <div class="document-list">
                        <?php foreach ($item['documents'] as $doc): ?>
                        <div class="document-item" onclick="viewDocument('<?php echo htmlspecialchars($doc['path']); ?>', '<?php echo htmlspecialchars($doc['type']); ?>', '<?php echo htmlspecialchars($doc['name']); ?>')">
                            <span class="document-icon">
                                <?php echo $doc['type'] === 'pdf' ? '📄' : '🖼️'; ?>
                            </span>
                            <div style="display: flex; flex-direction: column;">
                                <span><?php echo htmlspecialchars($doc['name']); ?></span>
                                <small style="color: #6c757d; font-size: 11px;">
                                    <?php echo toJalali(date('Y-m-d', strtotime($doc['upload_date']))); ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'superuser', 'supervisor'])): ?>
                <div class="upload-section">
                    <form class="upload-form" onsubmit="uploadDocument(event, '<?php echo htmlspecialchars($item['item_code']); ?>', 'profile')">
                        <strong>آپلود سند جدید:</strong>
                        <div class="file-input-wrapper">
                            <label class="file-input-label">
                                انتخاب فایل (PDF یا تصویر)
                                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                            </label>
                        </div>
                        <input type="text" name="document_name" placeholder="نام سند (مثلاً: لیست بسته‌بندی)" required style="flex: 1; min-width: 200px;">
                        <button type="submit" class="btn">آپلود</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
                    
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

<!-- Document Viewer Modal -->

</div>
<div id="documentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">نمایش سند</h3>
            <button class="close-modal" onclick="closeDocumentModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>
        
        <!-- Accessories Tab -->
  <div id="accessories-detailed" class="tab-content">
            <h2 style="margin-bottom: 20px;">
                اکسسوری‌ها - نمایش تفصیلی دریافت‌ها
                <?php if (!empty($start_date) || !empty($end_date)): ?>
                <span style="color: #10b981; font-size: 16px;">(فیلتر اعمال شده)</span>
                <?php endif; ?>
            </h2>
    
    <div class="controls">
        <div class="search-box">
            <input type="text" id="accessoryDetailSearch" placeholder="جستجو بر اساس کد قطعه..." onkeyup="filterAccessoriesDetailed()">
            <select id="accessoryDetailPalletFilter" onchange="filterAccessoriesDetailed()">
                <option value="">همه پالت‌ها</option>
                <?php
                // Get unique pallets from grouped data
                $all_pallets = [];
                foreach ($accessories_grouped as $item) {
                    foreach ($item['receipts'] as $receipt) {
                        if (!empty($receipt['pallet_no']) && !in_array($receipt['pallet_no'], $all_pallets)) {
                            $all_pallets[] = $receipt['pallet_no'];
                        }
                    }
                }
                sort($all_pallets);
                foreach ($all_pallets as $pallet) {
                    echo "<option value='" . htmlspecialchars($pallet) . "'>" . htmlspecialchars($pallet) . "</option>";
                }
                ?>
            </select>
            <select id="accessoryDetailOriginFilter" onchange="filterAccessoriesDetailed()">
                <option value="">همه مبادی</option>
                <?php
                // Get unique origins
                $all_origins = [];
                foreach ($accessories_grouped as $item) {
                    foreach ($item['receipts'] as $receipt) {
                        if (!empty($receipt['origin']) && !in_array($receipt['origin'], $all_origins)) {
                            $all_origins[] = $receipt['origin'];
                        }
                    }
                }
                sort($all_origins);
                foreach ($all_origins as $origin) {
                    echo "<option value='" . htmlspecialchars($origin) . "'>" . htmlspecialchars($origin) . "</option>";
                }
                ?>
            </select>
            <button onclick="expandAllAccessories()">باز کردن همه</button>
            <button onclick="collapseAllAccessories()">بستن همه</button>
            <button onclick="resetAccessoryDetailFilters()">بازنشانی فیلترها</button>
       <?php if (in_array($user_role, ['admin', 'superuser', 'supervisor'])): ?>
        <button class="btn btn-success" onclick="openBulkUploadModal('accessory')" style="background: #10b981;">
            📎 اختصاص سند به چند اکسسوری
        </button>
        <?php endif; ?>
    </div>
</div>
    
    <div id="accessoriesDetailedContainer">
        <?php foreach ($accessories_grouped as $item): ?>
        <div class="receipt-group accessory-group" 
             data-itemcode="<?php echo htmlspecialchars($item['item_code']); ?>"
             data-pallets="<?php echo htmlspecialchars(implode(',', array_unique(array_filter(array_column($item['receipts'], 'pallet_no'))))); ?>"
             data-origins="<?php echo htmlspecialchars(implode(',', array_unique(array_filter(array_column($item['receipts'], 'origin'))))); ?>">
            
            <div class="receipt-header" onclick="toggleReceiptDetails(this)">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <?php if ($item['image_file']): ?>
                        <img src="output/images/<?php echo htmlspecialchars($item['image_file']); ?>" 
                             style="max-width: 60px; max-height: 50px; object-fit: contain;">
                    <?php endif; ?>
                    <span class="receipt-code"><?php echo htmlspecialchars($item['item_code']); ?></span>
                    <span class="expand-icon">▼</span>
                </div>
                <div class="receipt-summary">
                    <div class="receipt-summary-item">
                        <span class="receipt-summary-label">تعداد دریافت</span>
                        <span class="receipt-summary-value"><?php echo count($item['receipts']); ?></span>
                    </div>
                    <div class="receipt-summary-item">
                        <span class="receipt-summary-label">مجموع دریافتی</span>
                        <span class="receipt-summary-value"><?php echo number_format($item['total_received'], 2); ?></span>
                    </div>
                    <div class="receipt-summary-item">
                        <span class="receipt-summary-label">خارج شده</span>
                        <span class="receipt-summary-value"><?php echo number_format($item['total_taken'], 2); ?></span>
                    </div>
                    <div class="receipt-summary-item">
                        <span class="receipt-summary-label">موجودی</span>
                        <span class="receipt-summary-value" style="color: #28a745;">
                            <?php echo number_format($item['total_stock'], 2); ?>
                        </span>
                    </div>
                    <div class="receipt-summary-item">
                        <span class="receipt-summary-label">اسناد</span>
                        <span class="receipt-summary-value" style="color: #007bff;">
                            <?php echo count($item['documents']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="receipt-details">
                <table class="receipt-list">
                    <thead>
                        <tr>
                            <th>تاریخ دریافت</th>
                            <th>طول (mm)</th>
                            <th>تعداد</th>
                            <th>خارج شده</th>
                            <th>موجودی</th>
                            <th>واحد</th>
                            <th>مبدأ</th>
                            <th>شماره پالت</th>
                            <th>برگه</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($item['receipts'] as $receipt): ?>
                        <tr>
                            <td><?php echo toJalali($receipt['receipt_date']); ?></td>
                            <td><?php echo $receipt['length'] ? number_format(floatval($receipt['length']), 2) : '-'; ?></td>
                            <td><?php echo number_format(floatval($receipt['quantity']), 2); ?></td>
                            <td><?php echo number_format(floatval($receipt['taken']), 2); ?></td>
                            <td style="font-weight: bold; color: <?php echo $receipt['stock'] > 0 ? '#28a745' : '#dc3545'; ?>">
                                <?php echo number_format(floatval($receipt['stock']), 2); ?>
                            </td>
                            <td><?php echo htmlspecialchars($receipt['uom'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($receipt['origin'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($receipt['pallet_no'] ?? '-'); ?></td>
                            <td><span class="sheet-badge"><?php echo htmlspecialchars($receipt['sheet_name']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totals row -->
                        <tr style="background: #e3f2fd; font-weight: bold;">
                            <td colspan="2" style="text-align: left;">مجموع:</td>
                            <td><?php echo number_format($item['total_received'], 2); ?></td>
                            <td><?php echo number_format($item['total_taken'], 2); ?></td>
                            <td style="color: #28a745;"><?php echo number_format($item['total_stock'], 2); ?></td>
                            <td colspan="4"></td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if (!empty($item['documents'])): ?>
                <div class="document-section">
                    <strong>اسناد پیوست (<?php echo count($item['documents']); ?>):</strong>
                    <div class="document-list">
                        <?php foreach ($item['documents'] as $doc): ?>
                        <div class="document-item" onclick="viewDocument('<?php echo htmlspecialchars($doc['path']); ?>', '<?php echo htmlspecialchars($doc['type']); ?>', '<?php echo htmlspecialchars($doc['name']); ?>')">
                            <span class="document-icon">
                                <?php echo $doc['type'] === 'pdf' ? '📄' : '🖼️'; ?>
                            </span>
                            <div style="display: flex; flex-direction: column;">
                                <span><?php echo htmlspecialchars($doc['name']); ?></span>
                                <small style="color: #6c757d; font-size: 11px;">
                                    <?php echo toJalali(date('Y-m-d', strtotime($doc['upload_date']))); ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'superuser', 'supervisor'])): ?>
                <div class="upload-section">
                    <form class="upload-form" onsubmit="uploadDocument(event, '<?php echo htmlspecialchars($item['item_code']); ?>', 'accessory')">
                        <strong>آپلود سند جدید:</strong>
                        <div class="file-input-wrapper">
                            <label class="file-input-label">
                                انتخاب فایل (PDF یا تصویر)
                                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                            </label>
                        </div>
                        <input type="text" name="document_name" placeholder="نام سند (مثلاً: لیست بسته‌بندی پالت 1)" required style="flex: 1; min-width: 200px;">
                        <button type="submit" class="btn">آپلود</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
 <?php if (empty($accessories_grouped)): ?>
            <div style="text-align: center; padding: 60px; background: #fff3cd; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #856404;">⚠️ هیچ اکسسوری در بازه زمانی انتخاب شده یافت نشد</h3>
                <p style="color: #856404;">لطفاً بازه زمانی دیگری را انتخاب کنید</p>
            </div>
            <?php endif; ?>
    </div>
</div>
        
        <!-- Remaining Profiles Tab -->
        <div id="remaining-profiles" class="tab-content">
            <h2 style="margin-bottom: 20px; color: #ff9800;">پروفیل‌های هنوز دریافت نشده</h2>
            
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>شکل</th>
                            <th>کد قطعه</th>
                            <th>نام قطعه</th>
                            <th>طول</th>
                            <th>تعداد 1</th>
                            <th>تعداد 2</th>
                            <th>مبدأ</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($remaining_profiles as $item): ?>
                        <tr>
                            <td class="shape-cell">
                                <?php if ($item['image_file']): ?>
                                    <img src="output/images/<?php echo htmlspecialchars($item['image_file']); ?>" alt="<?php echo htmlspecialchars($item['item_code']); ?>">
                                <?php else: ?>
                                    <span class="no-image">بدون تصویر</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                            <td><?php echo htmlspecialchars($item['item_name'] ?? '-'); ?></td>
                            <td><?php echo $item['length'] ? number_format(floatval($item['length']), 2) : '-'; ?></td>
                            <td><?php echo $item['qty1'] ? number_format(floatval($item['qty1']), 2) . ' ' . htmlspecialchars($item['uom1'] ?? '') : '-'; ?></td>
                            <td><?php echo $item['qty2'] ? number_format(floatval($item['qty2']), 2) . ' ' . htmlspecialchars($item['uom2'] ?? '') : '-'; ?></td>
                            <td><?php echo htmlspecialchars($item['origin'] ?? '-'); ?></td>
                            <td><span class="status-pending">در انتظار دریافت</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Remaining Accessories Tab -->
        <div id="remaining-accessories" class="tab-content">
            <h2 style="margin-bottom: 20px; color: #ff9800;">اکسسوری‌های هنوز دریافت نشده</h2>
            
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>شکل</th>
                            <th>کد قطعه</th>
                            <th>نام قطعه</th>
                            <th>تعداد</th>
                            <th>توضیحات</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($remaining_accessories as $item): ?>
                        <tr>
                            <td class="shape-cell">
                                <?php if ($item['image_file']): ?>
                                    <img src="output/images/<?php echo htmlspecialchars($item['image_file']); ?>" alt="<?php echo htmlspecialchars($item['item_code']); ?>">
                                <?php else: ?>
                                    <span class="no-image">بدون تصویر</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                            <td><?php echo htmlspecialchars($item['item_name'] ?? '-'); ?></td>
                            <td><?php echo $item['qty3'] ? number_format(floatval($item['qty3']), 2) . ' ' . htmlspecialchars($item['uom3'] ?? '') : '-'; ?></td>
                            <td><?php echo htmlspecialchars($item['description'] ?? '-'); ?></td>
                            <td><span class="status-pending">در انتظار دریافت</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Summary Tab -->
        <div id="summary_profile" class="tab-content">
            <h2 style="margin-bottom: 20px;">خلاصه آماری پروفیل‌ها</h2>
            
            <div class="data-table" style="margin-bottom: 30px;">
                    <h3 style="padding: 15px; background: #007bff; color: white; margin: 0;">پروفیل‌ها - گروه‌بندی بر اساس کد</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>شکل</th>
                                <th>کد قطعه</th>
                                <th>مجموع دریافتی</th>
                                <th>مجموع خارج شده</th>
                                <th>موجودی انبار</th>
                                <th>مجموع طول (m)</th>
                                <th>تعداد برگه‌ها</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $grandTotalLength = 0;
                            foreach ($profile_summary as $summary):
                                $totalLengthMM = floatval($summary['total_length']);
                                $totalLengthM = $totalLengthMM / 1000; // Convert mm to m
                                $grandTotalLength += $totalLengthM;
                            ?>
                            <tr>
                                <td class="shape-cell">
                                    <?php if ($summary['image_file']): ?>
                                        <img src="output/images/<?php echo htmlspecialchars($summary['image_file']); ?>" alt="<?php echo htmlspecialchars($summary['item_code']); ?>">
                                    <?php else: ?>
                                        <span class="no-image">بدون تصویر</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($summary['item_code']); ?></td>
                                <td><?php echo number_format(floatval($summary['total_received']), 2); ?></td>
                                <td><?php echo number_format(floatval($summary['total_taken']), 2); ?></td>
                                <td><?php echo number_format(floatval($summary['stock']), 2); ?></td>
                                <td><?php echo number_format($totalLengthM, 3); ?></td>
                                <td><?php echo $summary['sheet_count']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="5" style="text-align: left;">مجموع کل:</td>
                                <td><?php echo number_format($grandTotalLength, 3); ?></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
        </div>
        <div id="summary_accesory" class="tab-content">
            <h2 style="margin-bottom: 20px;">خلاصه آماری اکسسوری‌ها</h2>
            <div class="data-table">
                <h3 style="padding: 15px; background: #007bff; color: white; margin: 0;">اکسسوری‌ها - گروه‌بندی بر اساس کد</h3>
                <table>
                    <thead>
                        <tr>
                            <th>شکل</th>
                            <th>کد قطعه</th>
                            <th>مجموع دریافتی</th>
                            <th>مجموع خارج شده</th>
                            <th>موجودی انبار</th>
                            <th>تعداد برگه‌ها</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($accessory_summary as $summary):
                        ?>
                        <tr>
                            <td class="shape-cell">
                                <?php if ($summary['image_file']): ?>
                                    <img src="output/images/<?php echo htmlspecialchars($summary['image_file']); ?>" alt="<?php echo htmlspecialchars($summary['item_code']); ?>">
                                <?php else: ?>
                                    <span class="no-image">بدون تصویر</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($summary['item_code']); ?></td>
                            <td><?php echo number_format(floatval($summary['total_received']), 2); ?></td>
                            <td><?php echo number_format(floatval($summary['total_taken']), 2); ?></td>
                            <td><?php echo number_format(floatval($summary['stock']), 2); ?></td>
                            <td><?php echo $summary['sheet_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Add Items Tab (Admin/Supervisor/Superuser Only) -->
        <?php if (in_array($user_role, ['admin', 'superuser', 'supervisor'])): ?>
        <div id="add-items" class="tab-content">
            <h2 style="margin-bottom: 20px;">افزودن قطعات جدید</h2>
            
            <div id="addAlertBox" class="alert"></div>
            
            <div class="add-tabs">
                <button class="add-tab active" onclick="showAddTab('add-profile')">پروفیل</button>
                <button class="add-tab" onclick="showAddTab('add-accessory')">اکسسوری</button>
                <button class="add-tab" onclick="showAddTab('add-remaining-profile')">پروفیل باقی‌مانده</button>
                <button class="add-tab" onclick="showAddTab('add-remaining-accessory')">اکسسوری باقی‌مانده</button>
            </div>
            
            <!-- Profile Form -->
            <div id="add-profile" class="add-tab-content active">
                <form id="profileForm" class="item-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>کد قطعه *</label>
                            <input type="text" name="item_code" required>
                        </div>
                        <div class="form-group">
                            <label>طول (mm)</label>
                            <input type="number" step="0.01" name="length">
                        </div>
                        <div class="form-group">
                            <label>تاریخ دریافت *</label>
                            <input type="text" name="receipt_date" data-jdp readonly required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>تعداد *</label>
                            <input type="number" step="0.01" name="quantity" required>
                        </div>
                        <div class="form-group">
                            <label>واحد</label>
                            <select name="uom">
                                <option value="">انتخاب کنید</option>
                                <option value="BAR">BAR</option>
                                <option value="PCS">PCS</option>
                                <option value="MTR">MTR</option>
                                <option value="KG">KG</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>توضیحات (رنگ، بافت، و غیره)</label>
                        <textarea name="column1_content" placeholder="مثال: 1226324PX25 Facade 2481 RAL 7043 MAT TEXTURE"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>نام برگه *</label>
                        <input type="text" name="sheet_name" value="Part 04-2 Profile" required>
                    </div>
                    
                    <div class="form-group">
                        <label>نام فایل تصویر</label>
                        <input type="text" name="image_file" placeholder="example.png">
                        <small style="color: #666;">تصویر باید از قبل در پوشه output/images/ آپلود شده باشد</small>
                    </div>
                    
                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" onclick="resetForm('profileForm')">پاک کردن</button>
                        <button type="submit" class="btn btn-primary">ذخیره پروفیل</button>
                    </div>
                </form>
            </div>
            
            <!-- Accessory Form -->
            <div id="add-accessory" class="add-tab-content">
                <form id="accessoryForm" class="item-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>کد قطعه *</label>
                            <input type="text" name="item_code" required>
                        </div>
                        <div class="form-group">
                            <label>طول (mm)</label>
                            <input type="number" step="0.01" name="length">
                        </div>
                        <div class="form-group">
                            <label>تاریخ دریافت *</label>
                            <input type="text" name="receipt_date" data-jdp readonly required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>تعداد *</label>
                            <input type="number" step="0.01" name="quantity" required>
                        </div>
                        <div class="form-group">
                            <label>واحد</label>
                            <select name="uom">
                                <option value="">انتخاب کنید</option>
                                <option value="PCS">PCS</option>
                                <option value="SET">SET</option>
                                <option value="KG">KG</option>
                                <option value="MTR">MTR</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>مبدأ</label>
                            <input type="text" name="origin" placeholder="مثال: TURKEY">
                        </div>
                        <div class="form-group">
                            <label>شماره پالت</label>
                            <input type="text" name="pallet_no">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>نام برگه *</label>
                        <input type="text" name="sheet_name" value="Part 01-Accessori-Edited" required>
                    </div>
                    
                    <div class="form-group">
                        <label>نام فایل تصویر</label>
                        <input type="text" name="image_file" placeholder="example.png">
                        <small style="color: #666;">تصویر باید از قبل در پوشه output/images/ آپلود شده باشد</small>
                    </div>
                    
                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" onclick="resetForm('accessoryForm')">پاک کردن</button>
                        <button type="submit" class="btn btn-primary">ذخیره اکسسوری</button>
                    </div>
                </form>
            </div>
            
            <!-- Remaining Profile Form -->
            <div id="add-remaining-profile" class="add-tab-content">
                <form id="remainingProfileForm" class="item-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>شماره قطعه</label>
                            <input type="text" name="part_no">
                        </div>
                        <div class="form-group">
                            <label>شماره</label>
                            <input type="text" name="no">
                        </div>
                        <div class="form-group">
                            <label>تاریخ دریافت *</label>
                            <input type="text" name="receipt_date" data-jdp readonly required>
                        </div>                        
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>بسته</label>
                            <input type="text" name="package">
                        </div>
                        <div class="form-group">
                            <label>کد قطعه *</label>
                            <input type="text" name="item_code" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>نام قطعه</label>
                        <input type="text" name="item_name">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>نوع سرویس</label>
                            <input type="text" name="type_of_service">
                        </div>
                        <div class="form-group">
                            <label>لات</label>
                            <input type="text" name="lot">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>طول</label>
                            <input type="number" step="0.01" name="length">
                        </div>
                        <div class="form-group">
                            <label>واحد 2</label>
                            <input type="text" name="uom2">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>تعداد 1</label>
                            <input type="number" step="0.01" name="qty1">
                        </div>
                        <div class="form-group">
                            <label>واحد 1</label>
                            <input type="text" name="uom1">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>تعداد 2</label>
                            <input type="number" step="0.01" name="qty2">
                        </div>
                        <div class="form-group">
                            <label>مبدأ</label>
                            <input type="text" name="origin">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>نام برگه</label>
                        <input type="text" name="sheet_name" value="Remaining Profile Now">
                    </div>
                    
                    <div class="form-group">
                        <label>نام فایل تصویر</label>
                        <input type="text" name="image_file" placeholder="example.png">
                        <small style="color: #666;">تصویر باید از قبل در پوشه output/images/ آپلود شده باشد</small>
                    </div>
                    
                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" onclick="resetForm('remainingProfileForm')">پاک کردن</button>
                        <button type="submit" class="btn btn-primary">ذخیره</button>
                    </div>
                </form>
            </div>
            
            <!-- Remaining Accessory Form -->
            <div id="add-remaining-accessory" class="add-tab-content">
                <form id="remainingAccessoryForm" class="item-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>شماره</label>
                            <input type="text" name="no">
                        </div>
                        <div class="form-group">
                            <label>بسته</label>
                            <input type="text" name="package">
                        </div>
                        <div class="form-group">
                            <label>تاریخ دریافت *</label>
                            <input type="text" name="receipt_date" data-jdp readonly required>
                        </div>                        
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>کد قطعه *</label>
                            <input type="text" name="item_code" required>
                        </div>
                        <div class="form-group">
                            <label>نام قطعه</label>
                            <input type="text" name="item_name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>تعداد</label>
                            <input type="number" step="0.01" name="qty3">
                        </div>
                        <div class="form-group">
                            <label>واحد</label>
                            <input type="text" name="uom3">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>توضیحات</label>
                        <textarea name="description"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>نام برگه</label>
                        <input type="text" name="sheet_name" value="Remaining Accessories Now">
                    </div>
                    
                    <div class="form-group">
                        <label>نام فایل تصویر</label>
                        <input type="text" name="image_file" placeholder="example.png">
                        <small style="color: #666;">تصویر باید از قبل در پوشه output/images/ آپلود شده باشد</small>
                    </div>
                    
                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" onclick="resetForm('remainingAccessoryForm')">پاک کردن</button>
                        <button type="submit" class="btn btn-primary">ذخیره</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Inventory Exit Tab -->
        <div id="inventory-exit" class="tab-content">
            <div class="item-form">
                <h3>ثبت خروج قطعه از انبار</h3>
                <form id="inventoryExitForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>نوع قطعه</label>
                            <select id="exitItemType" name="item_type" onchange="populateExitItems()">
                                <option value="profile">پروفیل</option>
                                <option value="accessory">اکسسوری</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>کد قطعه</label>
                            <select id="exitItemId" name="item_id" required>
                                <option value="">انتخاب کنید...</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>تعداد خارج شده</label>
                            <input type="number" step="0.01" name="quantity" required>
                        </div>
                        <div class="form-group">
                            <label>تاریخ خروج</label>
                            <input type="text" name="transaction_date" data-jdp readonly required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>تحویل گیرنده (پیمانکار)</label>
                        <input type="text" name="taken_by" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>ساختمان مقصد</label>
                            <select id="exitBuilding" name="destination_building" onchange="populateExitParts()" required>
                                <option value="">انتخاب...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>بخش مقصد</label>
                            <select id="exitPart" name="destination_part" required>
                                <option value="">انتخاب...</option>
                            </select>
                        </div>
                    </div>
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">ثبت خروج</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
<div id="bulkUploadModal" class="bulk-upload-modal">
    <div class="bulk-upload-content">
        <div class="bulk-upload-header">
            <h3 id="bulkUploadTitle">اختصاص سند به چندین قطعه</h3>
            <button class="close-modal" onclick="closeBulkUploadModal()">&times;</button>
        </div>
        <div class="bulk-upload-body">
            <form id="bulkUploadForm" onsubmit="submitBulkUpload(event)">
                <input type="hidden" id="bulkItemType" name="item_type">
                
                <div class="form-group">
                    <label>انتخاب فایل (PDF یا تصویر) *</label>
                    <input type="file" id="bulkDocumentFile" name="document" accept=".pdf,.jpg,.jpeg,.png" required onchange="previewFile(this)">
                </div>
                
                <div id="filePreview" class="file-preview" style="display: none;">
                    <div class="file-preview-name" id="fileName"></div>
                    <div class="file-preview-info" id="fileInfo"></div>
                </div>
                
                <div class="form-group">
                    <label>نام سند *</label>
                    <input type="text" name="document_name" placeholder="مثلاً: لیست بسته‌بندی کانتینر 1" required style="width: 100%;">
                </div>
                
                <div class="form-group">
                    <label>انتخاب قطعات (حداقل یک مورد) *</label>
                    <div class="selection-controls">
                        <button type="button" class="btn-select-all" onclick="selectAllItems()">✓ انتخاب همه</button>
                        <button type="button" class="btn-deselect-all" onclick="deselectAllItems()">✗ حذف انتخاب همه</button>
                    </div>
                    <div class="selected-count">
                        انتخاب شده: <span id="selectedItemsCount">0</span> مورد
                    </div>
                    <div id="itemsSelectionGrid" class="items-selection-grid">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>
                
                <div class="bulk-upload-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeBulkUploadModal()">انصراف</button>
                    <button type="submit" class="btn btn-primary" style="background: #667eea;">آپلود و اختصاص به موارد انتخابی</button>
                </div>
            </form>
        </div>
    </div>
</div>
    <script>
let allProfiles = [];
let allAccessories = [];
let projectLocations = {};

// Load all data on page load
async function loadAllPackingData() {
    try {
        const response = await fetch('packing_list_api.php');
        const data = await response.json();
        if (!response.ok) throw new Error('Network response failed');
        if (!data.success) {
            console.error('Failed to load data:', data.message);
            return;
        }
        
        allProfiles = data.profiles || [];
        allAccessories = data.accessories || [];
        
        // Populate exit form dropdowns after data is loaded
        populateExitItems();
        
    } catch (error) {
        console.error('Error loading packing data:', error);
    }
}
function toggleReceiptDetails(header) {
    const details = header.nextElementSibling;
    const icon = header.querySelector('.expand-icon');
    
    details.classList.toggle('expanded');
    icon.classList.toggle('rotated');
}

// Expand/collapse all
function expandAllProfiles() {
    document.querySelectorAll('.receipt-details').forEach(details => {
        details.classList.add('expanded');
    });
    document.querySelectorAll('.expand-icon').forEach(icon => {
        icon.classList.add('rotated');
    });
}

function collapseAllProfiles() {
    document.querySelectorAll('.receipt-details').forEach(details => {
        details.classList.remove('expanded');
    });
    document.querySelectorAll('.expand-icon').forEach(icon => {
        icon.classList.remove('rotated');
    });
}

// Filter profiles detailed view
function filterProfilesDetailed() {
    const searchValue = document.getElementById('profileDetailSearch').value.toLowerCase();
    document.querySelectorAll('.receipt-group').forEach(group => {
        const itemCode = group.dataset.itemcode.toLowerCase();
        group.style.display = itemCode.includes(searchValue) ? '' : 'none';
    });
}

// View document in modal
function viewDocument(path, type, name) {
    const modal = document.getElementById('documentModal');
    const modalBody = document.getElementById('modalBody');
    const modalTitle = document.getElementById('modalTitle');
    
    modalTitle.textContent = name;
    
    if (type === 'pdf') {
        modalBody.innerHTML = `<iframe src="${path}" class="pdf-viewer"></iframe>`;
    } else {
        modalBody.innerHTML = `<img src="${path}" class="image-viewer" alt="${name}">`;
    }
    
    modal.style.display = 'block';
}

// Close modal
function closeDocumentModal() {
    document.getElementById('documentModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('documentModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}

// Upload document
async function uploadDocument(event, itemCode, itemType) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.append('action', 'upload_document');
    formData.append('item_code', itemCode);
    formData.append('item_type', itemType);
    
    try {
        const response = await fetch('api_documents.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('سند با موفقیت آپلود شد!');
            location.reload();
        } else {
            alert('خطا: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('خطا در آپلود سند');
    }
}
// Load project locations
async function loadProjectLocations() {
    try {
        const response = await fetch('api_add_items.php?action=get_project_locations');
        const data = await response.json();
        
        if (data.success) {
            projectLocations = data.locations;
            const buildingSelect = document.getElementById('exitBuilding');
            buildingSelect.innerHTML = '<option value="">انتخاب...</option>';
            for (const building in projectLocations) {
                buildingSelect.options[buildingSelect.options.length] = new Option(building, building);
            }
        }
    } catch (error) {
        console.error('Error loading project locations:', error);
    }
}

// Populate exit items dropdown
function populateExitItems() {
    const type = document.getElementById('exitItemType').value;
    const itemSelect = document.getElementById('exitItemId');
    const sourceData = (type === 'profile') ? allProfiles : allAccessories;

    itemSelect.innerHTML = '<option value="">انتخاب...</option>';
    sourceData.forEach(item => {
        itemSelect.options[itemSelect.options.length] = new Option(item.item_code, item.id);
    });
}

// Populate parts based on selected building
function populateExitParts() {
    const building = document.getElementById('exitBuilding').value;
    const partSelect = document.getElementById('exitPart');
    partSelect.innerHTML = '<option value="">انتخاب...</option>';
    if (building && projectLocations[building]) {
        projectLocations[building].forEach(part => {
            partSelect.options[partSelect.options.length] = new Option(part, part);
        });
    }
}

// Submit inventory exit form
function submitExitForm(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'add_inventory_exit');

    fetch('api_add_items.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                e.target.reset();
                location.reload();
            } else {
                alert('خطا: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('خطا در ارتباط با سرور');
        });
}

// Tab switching
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.nav-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    document.getElementById(tabId).classList.add('active');
    event.target.classList.add('active');
}

// Add tab switching
function showAddTab(tabId) {
    document.querySelectorAll('.add-tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.add-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    document.getElementById(tabId).classList.add('active');
    event.target.classList.add('active');
}

// Filter functions
function filterProfiles() {
    const searchValue = document.getElementById('profileSearch').value.toLowerCase();
    const sheetValue = document.getElementById('profileSheetFilter').value;
    const rows = document.querySelectorAll('#profilesTable .profile-row');
    
    rows.forEach(row => {
        const itemCode = row.dataset.itemcode.toLowerCase();
        const sheet = row.dataset.sheet;
        
        const matchesSearch = itemCode.includes(searchValue);
        const matchesSheet = !sheetValue || sheet === sheetValue;
        
        row.style.display = matchesSearch && matchesSheet ? '' : 'none';
    });
}

function resetProfileFilters() {
    document.getElementById('profileSearch').value = '';
    document.getElementById('profileSheetFilter').value = '';
    filterProfiles();
}

function filterAccessories() {
    const searchValue = document.getElementById('accessorySearch').value.toLowerCase();
    const palletValue = document.getElementById('accessoryPalletFilter').value;
    const rows = document.querySelectorAll('#accessoriesTable .accessory-row');
    
    rows.forEach(row => {
        const itemCode = row.dataset.itemcode.toLowerCase();
        const pallet = row.dataset.pallet;
        
        const matchesSearch = itemCode.includes(searchValue);
        const matchesPallet = !palletValue || pallet === palletValue;
        
        row.style.display = matchesSearch && matchesPallet ? '' : 'none';
    });
}

function resetAccessoryFilters() {
    document.getElementById('accessorySearch').value = '';
    document.getElementById('accessoryPalletFilter').value = '';
    filterAccessories();
}

// Form functions
function resetForm(formId) {
    document.getElementById(formId).reset();
}

function submitItemForm(formId, action) {
    const form = document.getElementById(formId);
    const formData = new FormData(form);
    formData.append('action', action);

    fetch('api_add_items.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => { throw new Error(text) });
        }
        return response.json();
    })
    .then(data => {
        const alertBox = document.getElementById('addAlertBox');
        if (data.success) {
            alertBox.className = 'alert alert-success';
            alertBox.textContent = data.message || 'با موفقیت ثبت شد!';
            alertBox.style.display = 'block';
            form.reset();
            setTimeout(() => location.reload(), 1500);
        } else {
            alertBox.className = 'alert alert-error';
            alertBox.textContent = data.message || 'خطا در ثبت!';
            alertBox.style.display = 'block';
        }
    })
    .catch((error) => {
        console.error('Fetch Error:', error);
        const alertBox = document.getElementById('addAlertBox');
        alertBox.className = 'alert alert-error';
        try {
            const errorJson = JSON.parse(error.message);
            alertBox.textContent = 'خطای سرور: ' + errorJson.message;
        } catch (e) {
            alertBox.textContent = 'خطا در ارتباط یا پردازش سرور!';
        }
        alertBox.style.display = 'block';
    });
}
function filterAccessoriesDetailed() {
    const searchValue = document.getElementById('accessoryDetailSearch').value.toLowerCase();
    const palletValue = document.getElementById('accessoryDetailPalletFilter').value;
    const originValue = document.getElementById('accessoryDetailOriginFilter').value;
    
    document.querySelectorAll('.accessory-group').forEach(group => {
        const itemCode = group.dataset.itemcode.toLowerCase();
        const pallets = group.dataset.pallets.toLowerCase();
        const origins = group.dataset.origins.toLowerCase();
        
        const matchesSearch = itemCode.includes(searchValue);
        const matchesPallet = !palletValue || pallets.includes(palletValue.toLowerCase());
        const matchesOrigin = !originValue || origins.includes(originValue.toLowerCase());
        
        group.style.display = matchesSearch && matchesPallet && matchesOrigin ? '' : 'none';
    });
}

// Reset accessory filters
function resetAccessoryDetailFilters() {
    document.getElementById('accessoryDetailSearch').value = '';
    document.getElementById('accessoryDetailPalletFilter').value = '';
    document.getElementById('accessoryDetailOriginFilter').value = '';
    filterAccessoriesDetailed();
}

// Expand all accessories
function expandAllAccessories() {
    document.querySelectorAll('.accessory-group .receipt-details').forEach(details => {
        details.classList.add('expanded');
    });
    document.querySelectorAll('.accessory-group .expand-icon').forEach(icon => {
        icon.classList.add('rotated');
    });
}

// Collapse all accessories
function collapseAllAccessories() {
    document.querySelectorAll('.accessory-group .receipt-details').forEach(details => {
        details.classList.remove('expanded');
    });
    document.querySelectorAll('.accessory-group .expand-icon').forEach(icon => {
        icon.classList.remove('rotated');
    });
}

// Export accessories data to CSV
function exportAccessoriesCSV() {
    let csv = 'کد قطعه,تعداد دریافت,مجموع دریافتی,خارج شده,موجودی,تعداد اسناد\n';
    
    document.querySelectorAll('.accessory-group').forEach(group => {
        if (group.style.display !== 'none') {
            const code = group.dataset.itemcode;
            const summary = group.querySelectorAll('.receipt-summary-value');
            const values = Array.from(summary).map(v => v.textContent.trim()).join(',');
            csv += `${code},${values}\n`;
        }
    });
    
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'accessories_summary_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
}

// Print accessories report
function printAccessoriesReport() {
    const printWindow = window.open('', '', 'height=800,width=1200');
    const content = document.getElementById('accessoriesDetailedContainer').cloneNode(true);
    
    // Expand all for printing
    content.querySelectorAll('.receipt-details').forEach(d => d.style.display = 'block');
    
    printWindow.document.write(`
        <html dir="rtl">
        <head>
            <title>گزارش اکسسوری‌ها</title>
            
        </head>
        <body>
            <h1>گزارش تفصیلی اکسسوری‌ها</h1>
            <p>تاریخ: ${new Date().toLocaleDateString('fa-IR')}</p>
            ${content.innerHTML}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

// Show statistics modal for accessories
function showAccessoryStats() {
    const groups = document.querySelectorAll('.accessory-group');
    let totalItems = 0;
    let totalReceived = 0;
    let totalTaken = 0;
    let totalStock = 0;
    let totalDocuments = 0;
    let uniquePallets = new Set();
    let uniqueOrigins = new Set();
    
    groups.forEach(group => {
        if (group.style.display !== 'none') {
            totalItems++;
            const values = group.querySelectorAll('.receipt-summary-value');
            totalReceived += parseFloat(values[1].textContent.replace(/,/g, ''));
            totalTaken += parseFloat(values[2].textContent.replace(/,/g, ''));
            totalStock += parseFloat(values[3].textContent.replace(/,/g, ''));
            totalDocuments += parseInt(values[4].textContent);
            
            const pallets = group.dataset.pallets.split(',').filter(p => p);
            pallets.forEach(p => uniquePallets.add(p));
            
            const origins = group.dataset.origins.split(',').filter(o => o);
            origins.forEach(o => uniqueOrigins.add(o));
        }
    });
    
    alert(`
📊 آمار اکسسوری‌ها (موارد نمایش داده شده):

🔢 تعداد کدهای منحصربه‌فرد: ${totalItems}
📦 مجموع دریافتی: ${totalReceived.toLocaleString('fa-IR')}
📤 مجموع خارج شده: ${totalTaken.toLocaleString('fa-IR')}
📍 موجودی فعلی: ${totalStock.toLocaleString('fa-IR')}
📄 تعداد اسناد: ${totalDocuments}
🏷️ تعداد پالت‌ها: ${uniquePallets.size}
🌍 تعداد مبادی: ${uniqueOrigins.size}
    `);
}

function exportProfilesCSV() {
    let csv = 'کد قطعه,تعداد دریافت,مجموع دریافتی,خارج شده,موجودی,مجموع طول (m)\n';
    document.querySelectorAll('.receipt-group:not(.accessory-group)').forEach(group => {
        if (group.style.display !== 'none') {
            const code = group.dataset.itemcode;
            const summary = group.querySelectorAll('.receipt-summary-value');
            // For profiles: [0]=تعداد دریافت, [1]=مجموع دریافتی, [2]=خارج شده, [3]=موجودی
            // Calculate total length in meters from details table
            let totalLengthMM = 0;
            const detailsRows = group.querySelectorAll('.receipt-details tbody tr');
            detailsRows.forEach(row => {
                const lengthCell = row.cells[1];
                const qtyCell = row.cells[2];
                if (lengthCell && qtyCell) {
                    const length = parseFloat(lengthCell.textContent.replace(/,/g, '')) || 0;
                    const qty = parseFloat(qtyCell.textContent.replace(/,/g, '')) || 0;
                    totalLengthMM += length * qty;
                }
            });
            const totalLengthM = (totalLengthMM / 1000).toFixed(3);

            const values = [
                summary[0]?.textContent.trim() ?? '',
                summary[1]?.textContent.trim() ?? '',
                summary[2]?.textContent.trim() ?? '',
                summary[3]?.textContent.trim() ?? '',
                totalLengthM
            ].join(',');
            csv += `${code},${values}\n`;
        }
    });
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'profiles_summary_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
}
// Print profiles report
function printProfilesReport() {
    const printWindow = window.open('', '', 'height=800,width=1200');
    const content = document.getElementById('profilesDetailedContainer').cloneNode(true);
    content.querySelectorAll('.receipt-details').forEach(d => d.style.display = 'block');
    printWindow.document.write(`
        <html dir="rtl">
        <head>
            <title>گزارش پروفیل‌ها</title>
            
        </head>
        <body>
            <h1>گزارش تفصیلی پروفیل‌ها</h1>
            <p>تاریخ: ${new Date().toLocaleDateString('fa-IR')}</p>
            ${content.innerHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Show statistics modal for profiles
// Show statistics modal for profiles
function showProfileStats() {
    const groups = document.querySelectorAll('.receipt-group:not(.accessory-group)');
    let totalItems = 0;
    let totalReceived = 0;
    let totalTaken = 0;
    let totalStock = 0;
    let totalLengthMM = 0;
    
    groups.forEach(group => {
        if (group.style.display !== 'none') {
            totalItems++;
            const values = group.querySelectorAll('.receipt-summary-value');
            totalReceived += parseFloat(values[1]?.textContent.replace(/,/g, '') || 0);
            totalTaken += parseFloat(values[3]?.textContent.replace(/,/g, '') || 0);
            totalStock += parseFloat(values[4]?.textContent.replace(/,/g, '') || 0);
            
            // Calculate total length from the summary value (index 2)
            const lengthText = values[2]?.textContent.replace(/,/g, '') || '0';
            totalLengthMM += parseFloat(lengthText) * 1000; // Convert meters to mm
        }
    });
    
    alert(`
📊 آمار پروفیل‌ها (موارد نمایش داده شده):

🔢 تعداد کدهای منحصربه‌فرد: ${totalItems}
📦 مجموع دریافتی: ${totalReceived.toLocaleString('fa-IR')}
📏 مجموع طول (متر): ${(totalLengthMM / 1000).toLocaleString('fa-IR', {maximumFractionDigits: 2})}
📤 مجموع خارج شده: ${totalTaken.toLocaleString('fa-IR')}
📍 موجودی فعلی: ${totalStock.toLocaleString('fa-IR')}
    `);
}


// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Jalali date picker
    if (typeof jalaliDatepicker !== 'undefined') {
        jalaliDatepicker.startWatch({
            persianDigits: true,
            autoShow: true,
            autoHide: true,
            
            hideAfterChange: true,
            date: true,
            time: false,
            zIndex: 2000
        });
    }
    
    // Load data
    loadAllPackingData();
    loadProjectLocations();
    const accessoryControls = document.querySelector('#accessories-detailed .controls');
    const profilesControls = document.querySelector('#profiles-detailed .controls');
    if (accessoryControls  ) {
        const actionButtons = document.createElement('div');
        actionButtons.className = 'action-buttons';
        actionButtons.innerHTML = `
            <button class="btn btn-success" onclick="exportAccessoriesCSV()">
                📊 خروجی Excel
            </button>
            <button class="btn btn-info" onclick="printAccessoriesReport()">
                🖨️ چاپ گزارش
            </button>
            <button class="btn btn-warning" onclick="showAccessoryStats()">
                📈 نمایش آمار
            </button>
        `;
        accessoryControls.appendChild(actionButtons);
    }
    if (profilesControls  ) {
        const actionButtons = document.createElement('div');
        actionButtons.className = 'action-buttons';
        actionButtons.innerHTML = `
            <button class="btn btn-success" onclick="exportProfilesCSV()">
                📊 خروجی Excel
            </button>
            <button class="btn btn-info" onclick="printProfilesReport()">
                🖨️ چاپ گزارش
            </button>
            <button class="btn btn-warning" onclick="showProfileStats()">
                📈 نمایش آمار
            </button>
        `;
        profilesControls.appendChild(actionButtons);
    }
    <?php if (in_array($user_role, ['admin', 'superuser', 'supervisor'])): ?>
    // Attach form handlers
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitItemForm('profileForm', 'add_profile');
    });
    
    document.getElementById('accessoryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitItemForm('accessoryForm', 'add_accessory');
    });
    
    document.getElementById('remainingProfileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitItemForm('remainingProfileForm', 'add_remaining_profile');
    });
    
    document.getElementById('remainingAccessoryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitItemForm('remainingAccessoryForm', 'add_remaining_accessory');
    });
    
    document.getElementById('inventoryExitForm').addEventListener('submit', submitExitForm);
    <?php endif; ?>
});
let currentBulkType = '';

function openBulkUploadModal(type) {
    currentBulkType = type;
    document.getElementById('bulkItemType').value = type;
    
    const modal = document.getElementById('bulkUploadModal');
    const title = document.getElementById('bulkUploadTitle');
    const grid = document.getElementById('itemsSelectionGrid');
    
    if (type === 'profile') {
        title.textContent = 'اختصاص سند به چندین پروفیل';
        populateProfileItems(grid);
    } else {
        title.textContent = 'اختصاص سند به چندین اکسسوری';
        populateAccessoryItems(grid);
    }
    
    modal.style.display = 'block';
    updateSelectedCount();
}

function closeBulkUploadModal() {
    document.getElementById('bulkUploadModal').style.display = 'none';
    document.getElementById('bulkUploadForm').reset();
    document.getElementById('filePreview').style.display = 'none';
    updateSelectedCount();
}

function populateProfileItems(grid) {
    grid.innerHTML = '';
    const groups = document.querySelectorAll('.receipt-group:not(.accessory-group)');
    
    groups.forEach((group, index) => {
        const itemCode = group.dataset.itemcode;
        const wrapper = document.createElement('div');
        wrapper.className = 'item-checkbox-wrapper';
        wrapper.innerHTML = `
            <input type="checkbox" 
                   name="item_codes[]" 
                   value="${itemCode}" 
                   id="item_${index}"
                   onchange="updateSelectedCount(); toggleWrapperSelection(this)">
            <label class="item-checkbox-label" for="item_${index}">${itemCode}</label>
        `;
        grid.appendChild(wrapper);
    });
}

function populateAccessoryItems(grid) {
    grid.innerHTML = '';
    const groups = document.querySelectorAll('.accessory-group');
    
    groups.forEach((group, index) => {
        const itemCode = group.dataset.itemcode;
        const wrapper = document.createElement('div');
        wrapper.className = 'item-checkbox-wrapper';
        wrapper.innerHTML = `
            <input type="checkbox" 
                   name="item_codes[]" 
                   value="${itemCode}" 
                   id="acc_item_${index}"
                   onchange="updateSelectedCount(); toggleWrapperSelection(this)">
            <label class="item-checkbox-label" for="acc_item_${index}">${itemCode}</label>
        `;
        grid.appendChild(wrapper);
    });
}

function toggleWrapperSelection(checkbox) {
    const wrapper = checkbox.closest('.item-checkbox-wrapper');
    if (checkbox.checked) {
        wrapper.classList.add('selected');
    } else {
        wrapper.classList.remove('selected');
    }
}

function selectAllItems() {
    const checkboxes = document.querySelectorAll('#itemsSelectionGrid input[type="checkbox"]');
    checkboxes.forEach(cb => {
        cb.checked = true;
        cb.closest('.item-checkbox-wrapper').classList.add('selected');
    });
    updateSelectedCount();
}

function deselectAllItems() {
    const checkboxes = document.querySelectorAll('#itemsSelectionGrid input[type="checkbox"]');
    checkboxes.forEach(cb => {
        cb.checked = false;
        cb.closest('.item-checkbox-wrapper').classList.remove('selected');
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('#itemsSelectionGrid input[type="checkbox"]:checked');
    document.getElementById('selectedItemsCount').textContent = checked.length;
}

function previewFile(input) {
    const preview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const fileInfo = document.getElementById('fileInfo');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        fileName.textContent = file.name;
        fileInfo.textContent = `نوع: ${file.type} | حجم: ${(file.size / 1024).toFixed(2)} KB`;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

async function submitBulkUpload(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('action', 'bulk_upload_document');
    
    // Check if at least one item is selected
    const selectedItems = formData.getAll('item_codes[]');
    if (selectedItems.length === 0) {
        alert('لطفاً حداقل یک قطعه را انتخاب کنید');
        return;
    }
    
    try {
        const response = await fetch('api_documents.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`✅ سند با موفقیت به ${data.assigned_count} قطعه اختصاص داده شد!`);
            closeBulkUploadModal();
            location.reload();
        } else {
            alert('❌ خطا: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('❌ خطا در آپلود سند');
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('bulkUploadModal');
    if (event.target === modal) {
        closeBulkUploadModal();
    }
}

function generateCurtainWallReport(outputType) {
    const startDate = document.getElementById('start_date_input').value;
    const endDate = document.getElementById('end_date_input').value;
    const includeDocs = document.getElementById('include_docs_input').checked ? '1' : '0';

    if (!startDate || !endDate) {
        alert('لطفا ابتدا بازه زمانی (از تاریخ و تا تاریخ) را مشخص کنید.');
        return;
    }

    let url = '';
    if (outputType === 'print') {
        url = `print_report_curtainwall.php?type=custom&from_date=${encodeURIComponent(startDate)}&to_date=${encodeURIComponent(endDate)}&include_docs=${includeDocs}`;
    } else if (outputType === 'zip') {
        url = `generate_report_zip_curtainwall.php?type=custom&from_date=${encodeURIComponent(startDate)}&to_date=${encodeURIComponent(endDate)}&include_docs=${includeDocs}`;
    }

    if (url) {
        window.open(url, '_blank');
    }
}
</script>
 </body>
</html>

<?php require_once __DIR__ . '/footer.php'; ?>