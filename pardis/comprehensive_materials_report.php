<?php
// public_html/pardis/comprehensive_materials_report.php
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

$expected_project_key = 'pardis';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}

$pageTitle = "گزارش جامع مواد - پروژه دانشگاه خاتم پردیس";

// --- DATE HANDLING ---
$start_date_jalali = $_GET['start_date'] ?? '';
$end_date_jalali = $_GET['end_date'] ?? '';

function toGregorian($jalali_date) {
    if (empty($jalali_date)) return null;
    
    // Standardize separator to dash
    $jalali_date = str_replace('/', '-', $jalali_date);
    $parts = explode('-', $jalali_date);
    
    // Basic validation
    if (count($parts) !== 3 || !is_numeric($parts[0]) || !is_numeric($parts[1]) || !is_numeric($parts[2])) {
        return null; // Invalid format
    }
    
    // Ensure parts are integers
    $j_y = (int)$parts[0];
    $j_m = (int)$parts[1];
    $j_d = (int)$parts[2];

    // Check if the function exists before calling it
    if (function_exists('jalali_to_gregorian')) {
        $gregorian_parts = jalali_to_gregorian($j_y, $j_m, $j_d);
        // Format the output correctly with leading zeros
        return sprintf('%04d-%02d-%02d', $gregorian_parts[0], $gregorian_parts[1], $gregorian_parts[2]);
    }
    
    return null; // Return null if function doesn't exist
}

function toJalali($gregorian_date) {
    if (empty($gregorian_date)) return '-';
    $parts = explode(' ', $gregorian_date);
    $date_part = $parts[0];
    $date_parts = explode('-', $date_part);
    if (count($date_parts) !== 3) return $gregorian_date;
    list($y, $m, $d) = $date_parts;
    $j = gregorian_to_jalali((int)$y, (int)$m, (int)$d);
    return $j[0] . '/' . str_pad($j[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($j[2], 2, '0', STR_PAD_LEFT);
}

$start_date_greg = toGregorian($start_date_jalali);
$end_date_greg = toGregorian($end_date_jalali);

// --- Build WHERE clauses ---
$date_filter_applied = $start_date_greg && $end_date_greg;
$whereClauses = [
    'zirsazi' => '',
    'profiles' => '',
    'accessories' => ''
];
$params = [];

if ($date_filter_applied) {
    $whereClauses['zirsazi'] = " WHERE received_date BETWEEN ? AND ? ";
    $whereClauses['profiles'] = " WHERE p.receipt_date BETWEEN ? AND ? ";
    $whereClauses['accessories'] = " WHERE a.receipt_date BETWEEN ? AND ? ";
    $params = [$start_date_greg, $end_date_greg];
}

function isMobileDevices() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

if (isMobileDevices()) {
    require_once __DIR__ . '/header.php';
} else {
    require_once __DIR__ . '/header.php';
}



function generateComprehensiveReportHTML(
    $profiles, 
    $accessories, 
    $zirsazi, 
    $zirsazi_stock, 
    $documents, 
    $report_details,
    $is_for_zip = false // This flag is the key to fixing the paths
) {
   $image_base_path = $is_for_zip ? 'images/' : 'output/images/';  // For ZIP, images are in the same folder
    $doc_base_path = $is_for_zip ? '' : ''; // For ZIP, docs are in the same folder

    // Calculate totals
    $total_profiles_received = array_sum(array_column($profiles, 'total_received'));
    $total_profiles_stock = array_sum(array_column($profiles, 'stock'));
    $total_accessories_received = array_sum(array_column($accessories, 'total_received'));
    $total_accessories_stock = array_sum(array_column($accessories, 'stock'));
    $total_zirsazi_received = array_sum(array_column($zirsazi, 'total_received'));
    $total_zirsazi_stock = array_sum($zirsazi_stock);

    ob_start();
    ?>
    <!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>گزارش جامع مواد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>body{font-family:Tahoma,Arial,sans-serif;}.table-sm th,.table-sm td{padding:.4rem;vertical-align:middle}.table img{max-width:60px;max-height:40px;object-fit:contain}.page-break{page-break-after:always}</style>
    </head><body>
    <div class="container-fluid mt-4">
        <div class="card mb-4"><div class="card-body text-center">
            <h1 class="text-success mb-3">گزارش جامع موجودی مواد</h1><h3>پروژه دانشگاه خاتم پردیس</h3>
            <p class="text-muted mb-0">تاریخ گزارش: <strong><?php echo htmlspecialchars($report_details['current_jalali']); ?></strong></p>
            <?php if($report_details['is_filtered']): ?><p class="text-primary mb-0"><strong>بازه زمانی:</strong> از <?php echo htmlspecialchars($report_details['start_date']); ?> تا <?php echo htmlspecialchars($report_details['end_date']); ?></p><?php endif; ?>
        </div></div>
        <div class="row mb-4"><div class="col-md-4"><div class="card bg-primary text-white h-100"><div class="card-body text-center"><h2><?php echo count($profiles); ?></h2><p class="mb-0">انواع پروفیل</p></div></div></div><div class="col-md-4"><div class="card bg-success text-white h-100"><div class="card-body text-center"><h2><?php echo count($accessories); ?></h2><p class="mb-0">انواع اکسسوری</p></div></div></div><div class="col-md-4"><div class="card bg-info text-white h-100"><div class="card-body text-center"><h2><?php echo count($zirsazi); ?></h2><p class="mb-0">انواع مواد زیرسازی</p></div></div></div></div>
        
        <!-- Profiles Section with corrected image path -->
        <div class="card mb-4"><div class="card-header bg-primary text-white"><h4 class="mb-0"><i class="bi bi-box"></i> پروفیل‌ها</h4></div><div class="card-body"><div class="table-responsive"><table class="table table-bordered table-sm">
            <thead class="table-light text-center"><tr><th>ردیف</th><th>تصویر</th><th>کد</th><th>دریافتی</th><th>خارج شده</th><th>موجودی</th><th>طول کل(m)</th></tr></thead>
            <tbody class="text-center"><?php foreach($profiles as $i=>$item): ?><tr><td><?php echo $i+1; ?></td><td><?php if($item['image_file']):?><img src="<?php echo $image_base_path . htmlspecialchars($item['image_file']);?>"><?php endif;?></td><td><strong><?php echo htmlspecialchars($item['item_code']);?></strong></td><td><?php echo number_format($item['total_received']);?></td><td><?php echo number_format($item['total_taken']);?></td><td class="table-success"><strong><?php echo number_format($item['stock']);?></strong></td><td><?php echo number_format($item['total_length_mm']/1000,2);?></td></tr><?php endforeach;?></tbody>
            <tfoot class="table-light fw-bold text-center"><tr><td colspan="3">جمع کل</td><td><?php echo number_format($total_profiles_received);?></td><td></td><td><?php echo number_format($total_profiles_stock);?></td><td></td></tr></tfoot>
        </table></div></div></div><div class="page-break"></div>
        
        <!-- Accessories Section with corrected image path -->
        <div class="card mb-4"><div class="card-header bg-success text-white"><h4 class="mb-0"><i class="bi bi-tools"></i> اکسسوری‌ها</h4></div><div class="card-body"><div class="table-responsive"><table class="table table-bordered table-sm">
            <thead class="table-light text-center"><tr><th>ردیف</th><th>تصویر</th><th>کد</th><th>دریافتی</th><th>خارج شده</th><th>موجودی</th></tr></thead>
            <tbody class="text-center"><?php foreach($accessories as $i=>$item): ?><tr><td><?php echo $i+1; ?></td><td><?php if($item['image_file']):?><img src="<?php echo $image_base_path . htmlspecialchars($item['image_file']);?>"><?php endif;?></td><td><strong><?php echo htmlspecialchars($item['item_code']);?></strong></td><td><?php echo number_format($item['total_received']);?></td><td><?php echo number_format($item['total_taken']);?></td><td class="table-success"><strong><?php echo number_format($item['stock']);?></strong></td></tr><?php endforeach;?></tbody>
            <tfoot class="table-light fw-bold text-center"><tr><td colspan="3">جمع کل</td><td><?php echo number_format($total_accessories_received);?></td><td></td><td><?php echo number_format($total_accessories_stock);?></td></tr></tfoot>
        </table></div></div></div><div class="page-break"></div>
        
        <!-- Zirsazi Section -->
        <div class="card mb-4"><div class="card-header bg-info text-white"><h4 class="mb-0"><i class="bi bi-bricks"></i> مواد زیرسازی</h4></div><div class="card-body"><div class="table-responsive"><table class="table table-bordered table-sm">
            <thead class="table-light text-center"><tr><th>ردیف</th><th>نوع متریال</th><th>دریافتی</th><th>موجودی انبار</th></tr></thead>
            <tbody class="text-center"><?php foreach($zirsazi as $i=>$item): ?><tr><td><?php echo $i+1;?></td><td><strong><?php echo htmlspecialchars($item['material_type']);?></strong></td><td><?php echo number_format($item['total_received']);?></td><td class="table-info"><strong><?php echo number_format($zirsazi_stock[trim($item['material_type'])]??0);?></strong></td></tr><?php endforeach;?></tbody>
            <tfoot class="table-light fw-bold text-center"><tr><td colspan="2">جمع کل</td><td><?php echo number_format($total_zirsazi_received);?></td><td><?php echo number_format($total_zirsazi_stock);?></td></tr></tfoot>
        </table></div></div></div>
        
        <!-- Documents List Section with corrected links -->
        <?php if ($report_details['include_docs'] && !empty(array_filter($documents))): ?>
        <div class="page-break"></div>
        <div class="card mb-4"><div class="card-header bg-secondary text-white"><h4 class="mb-0"><i class="bi bi-files"></i> لیست اسناد پیوست</h4></div><div class="card-body">
            <?php foreach($documents as $type => $docs): if(!empty($docs)): ?>
                <h5><?php echo ucfirst($type); ?></h5><ul class="list-group list-group-flush mb-3">
                <?php foreach($docs as $doc): ?>
                    <li class="list-group-item"><a href="<?php echo $doc_base_path . basename($doc['path']); ?>" target="_blank"><?php echo htmlspecialchars($doc['name'] ?: basename($doc['path'])); ?></a></li>
                <?php endforeach; ?></ul>
            <?php endif; endforeach; ?>
        </div></div>
        <?php endif; ?>
    </div></body></html>
    <?php
    return ob_get_clean();
}



$current_jalali = toJalali(date('Y-m-d'));

try {
    $pdo = getProjectDBConnection('pardis');
     $documents = ['zirsazi' => [], 'profiles' => [], 'accessories' => []];
    $zirsazi_doc_sql = "SELECT DISTINCT packing_number as name, document_path as path FROM packing_lists WHERE document_path IS NOT NULL AND document_path != ''" . ($date_filter_applied ? " AND received_date BETWEEN ? AND ?" : "");
    $stmt_docs = $pdo->prepare($zirsazi_doc_sql); $stmt_docs->execute($params); $documents['zirsazi'] = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);


       $profiles_doc_sql = "SELECT DISTINCT pd.document_name as name, pd.document_path as path FROM packing_documents pd JOIN profiles p ON pd.item_id = p.id WHERE pd.item_type = 'profile' AND pd.document_path IS NOT NULL AND pd.document_path != ''" . ($date_filter_applied ? " AND p.receipt_date BETWEEN ? AND ?" : "");
    $stmt_docs = $pdo->prepare($profiles_doc_sql); $stmt_docs->execute($params); $documents['profiles'] = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

    $accessories_doc_sql = "SELECT DISTINCT pd.document_name as name, pd.document_path as path FROM packing_documents pd JOIN accessories a ON pd.item_id = a.id WHERE pd.item_type = 'accessory' AND pd.document_path IS NOT NULL AND pd.document_path != ''" . ($date_filter_applied ? " AND a.receipt_date BETWEEN ? AND ?" : "");
    $stmt_docs = $pdo->prepare($accessories_doc_sql); $stmt_docs->execute($params); $documents['accessories'] = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);


    // Fetch profiles (with date filter)
    $profiles_sql = "SELECT p.item_code, SUM(p.quantity) as total_received, codLESCE(SUM(it.quantity_taken), 0) as total_taken, (SUM(p.quantity) - codLESCE(SUM(it.quantity_taken), 0)) as stock, SUM(p.length * p.quantity) as total_length_mm, MAX(p.image_file) as image_file FROM profiles p LEFT JOIN inventory_transactions it ON p.id = it.item_id AND it.item_type = 'profile' {$whereClauses['profiles']} GROUP BY p.item_code ORDER BY p.item_code";
    $stmt = $pdo->prepare($profiles_sql);
    $stmt->execute($params);
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch accessories (with date filter)
    $accessories_sql = "SELECT a.item_code, SUM(a.quantity) as total_received, codLESCE(SUM(it.quantity_taken), 0) as total_taken, (SUM(a.quantity) - codLESCE(SUM(it.quantity_taken), 0)) as stock, MAX(a.image_file) as image_file FROM accessories a LEFT JOIN inventory_transactions it ON a.id = it.item_id AND it.item_type = 'accessory' {$whereClauses['accessories']} GROUP BY a.item_code ORDER BY a.item_code";
    $stmt = $pdo->prepare($accessories_sql);
    $stmt->execute($params);
    $accessories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch zirsazi (with date filter)
    $zirsazi_sql = "SELECT TRIM(material_type) as material_type, SUM(quantity) as total_received FROM packing_lists {$whereClauses['zirsazi']} GROUP BY TRIM(material_type) ORDER BY material_type";
    $stmt = $pdo->prepare($zirsazi_sql);
    $stmt->execute($params);
    $zirsazi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    

    
    // Get warehouse inventory (always current, not date-filtered)
    $zirsazi_stock_sql = "SELECT material_type, SUM(current_stock) as stock FROM warehouse_inventory GROUP BY material_type";
    $zirsazi_stock = $pdo->query($zirsazi_stock_sql)->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Calculate totals
    $total_profiles_received = array_sum(array_column($profiles, 'total_received'));
    $total_profiles_stock = array_sum(array_column($profiles, 'stock'));
    $total_accessories_received = array_sum(array_column($accessories, 'total_received'));
    $total_accessories_stock = array_sum(array_column($accessories, 'stock'));
    $total_zirsazi_received = array_sum(array_column($zirsazi, 'total_received'));
    $total_zirsazi_stock = array_sum($zirsazi_stock); // This is always the grand total stock

    
    
} catch (Exception $e) {
    logError("Error fetching comprehensive data: " . $e->getMessage());
    $profiles = $accessories = $zirsazi = [];
}


$report_details = [
    'current_jalali' => $current_jalali,
    'is_filtered'    => $date_filter_applied,
    'start_date'     => $start_date_jalali,
    'end_date'       => $end_date_jalali,
    'include_docs'   => true // Always show links on the webpage
];
$report_html = generateComprehensiveReportHTML(
    $profiles,
    $accessories,
    $zirsazi,
    $zirsazi_stock,
    $documents, // Pass the new documents array
    $report_details,
    false // is_for_zip is false
);

?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@latest/dist/css/persian-datepicker.min.css">
<style>
    .table-sm th, .table-sm td { padding: 0.4rem; vertical-align: middle; }
    .table img { max-width: 60px; max-height: 40px; object-fit: contain; }
   @media print {
        /* Hide everything by default */
        body * {
            visibility: hidden;
        }
        
        /* Make only the report container and its children visible */
        #printable-report-area, #printable-report-area * {
            visibility: visible;
        }

        /* Position the report at the top of the page */
        #printable-report-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }

        .no-print { display: none !important; }
        .page-break { page-break-after: always; }
        body { font-size: 10pt; background: #fff !important; }
        .card { border: none !important; box-shadow: none !important; }
        .card-header { 
            background-color: #f8f9fa !important; 
            color: black !important; 
            border-bottom: 2px solid #ddd !important;
            -webkit-print-color-adjust: exact; /* Force background color printing in Chrome */
            print-color-adjust: exact;
        }
        .table-success {
             background-color: #d1e7dd !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .table-info {
             background-color: #cff4fc !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2><i class="bi bi-clipboard-data"></i> گزارش جامع مواد پروژه</h2>
        <a href="select_print_report.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> بازگشت به مرکز گزارشات</a>
    </div>

    <!-- FILTER SECTION -->
    <div class="card mb-4 no-print">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> فیلتر و عملیات گزارش</h5>
        </div>
        <div class="card-body">
            <form id="filterForm" method="GET" action="">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">از تاریخ</label>
                        <input type="text" class="form-control" id="start_date" name="start_date" data-jdp readonly value="<?php echo htmlspecialchars($start_date_jalali); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تا تاریخ</label>
                        <input type="text" class="form-control" id="end_date" name="end_date" data-jdp readonly value="<?php echo htmlspecialchars($end_date_jalali); ?>">
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search"></i> اعمال فیلتر</button>
                        <a href="comprehensive_materials_report.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i> حذف فیلتر</a>
                        <button type="button" class="btn btn-outline-info" onclick="generateQuickReport('weekly')">هفتگی</button>
                        <button type="button" class="btn btn-outline-info" onclick="generateQuickReport('monthly')">ماهانه</button>
                    </div>
                </div>
            </form>
            <hr>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-success" onclick="window.print()"><i class="bi bi-printer"></i> چاپ گزارش</button>
                <button class="btn btn-warning" onclick="downloadComprehensiveZip()"><i class="bi bi-file-zip"></i> دانلود بسته ZIP</button>
                <div class="form-check form-switch pt-1">
                    <input class="form-check-input" type="checkbox" id="include_docs">
                    <label class="form-check-label" for="include_docs">شامل کردن اسناد در ZIP</label>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Header -->
    <div class="card mb-4">
        <div class="card-body text-center">
            <h1 class="text-success mb-3">گزارش جامع موجودی مواد</h1>
            <h3>پروژه دانشگاه خاتم پردیس</h3>
            <p class="text-muted mb-0">تاریخ گزارش: <strong><?php echo $current_jalali; ?></strong></p>
            <?php if ($date_filter_applied): ?>
            <p class="text-primary mb-0"><strong>بازه زمانی فیلتر:</strong> از <?php echo htmlspecialchars($start_date_jalali); ?> تا <?php echo htmlspecialchars($end_date_jalali); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-4"><div class="card bg-primary text-white h-100"><div class="card-body text-center"><h2><?php echo count($profiles); ?></h2><p class="mb-0">انواع پروفیل دریافت شده</p></div></div></div>
        <div class="col-md-4"><div class="card bg-success text-white h-100"><div class="card-body text-center"><h2><?php echo count($accessories); ?></h2><p class="mb-0">انواع اکسسوری دریافت شده</p></div></div></div>
        <div class="col-md-4"><div class="card bg-info text-white h-100"><div class="card-body text-center"><h2><?php echo count($zirsazi); ?></h2><p class="mb-0">انواع مواد زیرسازی دریافت شده</p></div></div></div>
    </div>

    <!-- Profiles Section -->
    <div class="card mb-4"><div class="card-header bg-primary text-white"><h4 class="mb-0"><i class="bi bi-box"></i> پروفیل‌ها (Curtain Wall Profiles)</h4></div><div class="card-body"><div class="table-responsive"><table class="table table-bordered table-hover table-sm">
        <thead class="table-light text-center"><tr><th style="width: 5%;">ردیف</th><th style="width: 10%;">تصویر</th><th style="width: 25%;">کد پروفیل</th><th style="width: 15%;">جمع دریافتی</th><th style="width: 15%;">خارج شده</th><th style="width: 15%;">موجودی انبار</th><th style="width: 15%;">طول کل (متر)</th></tr></thead>
        <tbody class="text-center">
            <?php foreach($profiles as $index => $item): ?>
            <tr><td><?php echo $index + 1; ?></td><td><?php if($item['image_file']): ?><img src="output/images/<?php echo htmlspecialchars($item['image_file']); ?>"><?php endif; ?></td><td><strong><?php echo htmlspecialchars($item['item_code']); ?></strong></td><td><?php echo number_format($item['total_received']); ?></td><td><?php echo number_format($item['total_taken']); ?></td><td class="table-success"><strong><?php echo number_format($item['stock']); ?></strong></td><td><?php echo number_format($item['total_length_mm'] / 1000, 2); ?></td></tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light fw-bold text-center"><tr><td colspan="3">جمع کل</td><td><?php echo number_format($total_profiles_received); ?></td><td></td><td><?php echo number_format($total_profiles_stock); ?></td><td></td></tr></tfoot>
    </table></div></div></div>

    <div class="page-break"></div>

    <!-- Accessories Section -->
    <div class="card mb-4"><div class="card-header bg-success text-white"><h4 class="mb-0"><i class="bi bi-tools"></i> اکسسوری‌ها (Accessories)</h4></div><div class="card-body"><div class="table-responsive"><table class="table table-bordered table-hover table-sm">
        <thead class="table-light text-center"><tr><th style="width: 5%;">ردیف</th><th style="width: 10%;">تصویر</th><th style="width: 40%;">کد اکسسوری</th><th style="width: 15%;">جمع دریافتی</th><th style="width: 15%;">خارج شده</th><th style="width: 15%;">موجودی انبار</th></tr></thead>
        <tbody class="text-center">
             <?php foreach($accessories as $index => $item): ?>
            <tr><td><?php echo $index + 1; ?></td><td><?php if($item['image_file']): ?><img src="output/images/<?php echo htmlspecialchars($item['image_file']); ?>"><?php endif; ?></td><td><strong><?php echo htmlspecialchars($item['item_code']); ?></strong></td><td><?php echo number_format($item['total_received']); ?></td><td><?php echo number_format($item['total_taken']); ?></td><td class="table-success"><strong><?php echo number_format($item['stock']); ?></strong></td></tr>
            <?php endforeach; ?>
        </tbody>
         <tfoot class="table-light fw-bold text-center"><tr><td colspan="3">جمع کل</td><td><?php echo number_format($total_accessories_received); ?></td><td></td><td><?php echo number_format($total_accessories_stock); ?></td></tr></tfoot>
    </table></div></div></div>
    
    <div class="page-break"></div>

    <!-- Zirsazi Section -->
    <div class="card mb-4"><div class="card-header bg-info text-white"><h4 class="mb-0"><i class="bi bi-bricks"></i> مواد زیرسازی (Infrastructure)</h4></div><div class="card-body"><div class="table-responsive"><table class="table table-bordered table-hover table-sm">
        <thead class="table-light text-center"><tr><th style="width: 5%;">ردیف</th><th style="width: 55%;">نوع متریال</th><th style="width: 20%;">جمع دریافتی</th><th style="width: 20%;">موجودی کل انبارها</th></tr></thead>
        <tbody class="text-center">
            <?php foreach($zirsazi as $index => $item): ?>
            <tr><td><?php echo $index + 1; ?></td><td><strong><?php echo htmlspecialchars($item['material_type']); ?></strong></td><td><?php echo number_format($item['total_received']); ?></td><td class="table-info"><strong><?php echo number_format($zirsazi_stock[trim($item['material_type'])] ?? 0); ?></strong></td></tr>
            <?php endforeach; ?>
        </tbody>
         <tfoot class="table-light fw-bold text-center"><tr><td colspan="2">جمع کل</td><td><?php echo number_format($total_zirsazi_received); ?></td><td><?php echo number_format($total_zirsazi_stock); ?></td></tr></tfoot>
    </table></div></div></div>
    <div id="printable-report-area">
        <?php
        // Echo the generated report HTML
        echo $report_html;
        ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/persian-date@latest/dist/persian-date.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    jalaliDatepicker.startWatch({
        selector: '[data-jdp]',
        persianDigits: true,
        autoHide: true,
        zIndex: 2000
    });
});

function generateQuickReport(type) {
    const today = new persianDate();
    const endDate = today.format('YYYY/MM/DD');
    let startDate;

    if (type === 'weekly') {
        startDate = today.subtract('days', 7).format('YYYY/MM/DD');
    } else if (type === 'monthly') {
        startDate = today.subtract('days', 30).format('YYYY/MM/DD');
    }
    
    document.getElementById('start_date').value = startDate;
    document.getElementById('end_date').value = endDate;
    document.getElementById('filterForm').submit();
}

function downloadComprehensiveZip() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const includeDocs = document.getElementById('include_docs').checked ? '1' : '0';

    if (!startDate || !endDate) {
        if (!confirm('بازه زمانی مشخص نشده است. آیا می‌خواهید برای کل پروژه فایل ZIP تهیه کنید؟')) {
            return;
        }
    }
    
    const url = `generate_comprehensive_zip.php?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&include_docs=${includeDocs}`;
    
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = url;
    document.body.appendChild(iframe);
    
    setTimeout(() => {
        document.body.removeChild(iframe);
    }, 5000);
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>