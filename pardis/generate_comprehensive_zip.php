<?php
// public_html/pardis/generate_comprehensive_zip.php
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

// This function generates the complete HTML for the comprehensive report.
function generateComprehensiveReportHTML(
    $profiles,
    $accessories,
    $zirsazi,
    $zirsazi_stock,
    $documents,
    $report_details,
    $is_for_zip = false // This flag is the key to fixing the paths
) {
    $image_base_path = $is_for_zip ? 'images/' : 'output/images/'; // For ZIP, images are in the same folder
    $doc_base_path = $is_for_zip ? 'documents/' : ''; // For ZIP, docs are in the same folder

    // Calculate totals
    $total_profiles_received = array_sum(array_column($profiles, 'total_received'));
    $total_profiles_stock = array_sum(array_column($profiles, 'stock'));
    $total_accessories_received = array_sum(array_column($accessories, 'total_received'));
    $total_accessories_stock = array_sum(array_column($accessories, 'stock'));
    $total_zirsazi_received = array_sum(array_column($zirsazi, 'total_received'));
    $total_zirsazi_stock = array_sum($zirsazi_stock);

    ob_start();
?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">

    <head>
        <meta charset="UTF-8">
        <title>گزارش جامع مواد</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
        <style>
            body {
                font-family: Tahoma, Arial, sans-serif;
            }

            .table-sm th,
            .table-sm td {
                padding: .4rem;
                vertical-align: middle
            }

            .table img {
                max-width: 60px;
                max-height: 40px;
                object-fit: contain
            }

            .page-break {
                page-break-after: always
            }
        </style>
    </head>

    <body>
        <div class="container-fluid mt-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <h1 class="text-success mb-3">گزارش جامع موجودی مواد</h1>
                    <h3>پروژه دانشگاه خاتم پردیس</h3>
                    <p class="text-muted mb-0">تاریخ گزارش: <strong><?php echo htmlspecialchars($report_details['current_jalali']); ?></strong></p>
                    <?php if ($report_details['is_filtered']): ?><p class="text-primary mb-0"><strong>بازه زمانی:</strong> از <?php echo htmlspecialchars($report_details['start_date']); ?> تا <?php echo htmlspecialchars($report_details['end_date']); ?></p><?php endif; ?>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body text-center">
                            <h2><?php echo count($profiles); ?></h2>
                            <p class="mb-0">انواع پروفیل</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body text-center">
                            <h2><?php echo count($accessories); ?></h2>
                            <p class="mb-0">انواع اکسسوری</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body text-center">
                            <h2><?php echo count($zirsazi); ?></h2>
                            <p class="mb-0">انواع مواد زیرسازی</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profiles Section with corrected image path -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-box"></i> پروفیل‌ها</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light text-center">
                                <tr>
                                    <th>ردیف</th>
                                    <th>تصویر</th>
                                    <th>کد</th>
                                    <th>دریافتی</th>
                                    <th>خارج شده</th>
                                    <th>موجودی</th>
                                    <th>طول کل(m)</th>
                                </tr>
                            </thead>
                            <tbody class="text-center"><?php foreach ($profiles as $i => $item): ?><tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php if ($item['image_file']): ?><img src="<?php echo $image_base_path . htmlspecialchars($item['image_file']); ?>"><?php endif; ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['item_code']); ?></strong></td>
                                        <td><?php echo number_format($item['total_received']); ?></td>
                                        <td><?php echo number_format($item['total_taken']); ?></td>
                                        <td class="table-success"><strong><?php echo number_format($item['stock']); ?></strong></td>
                                        <td><?php echo number_format($item['total_length_mm'] / 1000, 2); ?></td>
                                    </tr><?php endforeach; ?></tbody>
                            <tfoot class="table-light fw-bold text-center">
                                <tr>
                                    <td colspan="3">جمع کل</td>
                                    <td><?php echo number_format($total_profiles_received); ?></td>
                                    <td></td>
                                    <td><?php echo number_format($total_profiles_stock); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="page-break"></div>

            <!-- Accessories Section with corrected image path -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="bi bi-tools"></i> اکسسوری‌ها</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light text-center">
                                <tr>
                                    <th>ردیف</th>
                                    <th>تصویر</th>
                                    <th>کد</th>
                                    <th>دریافتی</th>
                                    <th>خارج شده</th>
                                    <th>موجودی</th>
                                </tr>
                            </thead>
                            <tbody class="text-center"><?php foreach ($accessories as $i => $item): ?><tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php if ($item['image_file']): ?><img src="<?php echo $image_base_path . htmlspecialchars($item['image_file']); ?>"><?php endif; ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['item_code']); ?></strong></td>
                                        <td><?php echo number_format($item['total_received']); ?></td>
                                        <td><?php echo number_format($item['total_taken']); ?></td>
                                        <td class="table-success"><strong><?php echo number_format($item['stock']); ?></strong></td>
                                    </tr><?php endforeach; ?></tbody>
                            <tfoot class="table-light fw-bold text-center">
                                <tr>
                                    <td colspan="3">جمع کل</td>
                                    <td><?php echo number_format($total_accessories_received); ?></td>
                                    <td></td>
                                    <td><?php echo number_format($total_accessories_stock); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="page-break"></div>

            <!-- Zirsazi Section -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0"><i class="bi bi-bricks"></i> مواد زیرسازی</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light text-center">
                                <tr>
                                    <th>ردیف</th>
                                    <th>نوع متریال</th>
                                    <th>دریافتی</th>
                                    <th>موجودی انبار</th>
                                </tr>
                            </thead>
                            <tbody class="text-center"><?php foreach ($zirsazi as $i => $item): ?><tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['material_type']); ?></strong></td>
                                        <td><?php echo number_format($item['total_received']); ?></td>
                                        <td class="table-info"><strong><?php echo number_format($zirsazi_stock[trim($item['material_type'])] ?? 0); ?></strong></td>
                                    </tr><?php endforeach; ?></tbody>
                            <tfoot class="table-light fw-bold text-center">
                                <tr>
                                    <td colspan="2">جمع کل</td>
                                    <td><?php echo number_format($total_zirsazi_received); ?></td>
                                    <td><?php echo number_format($total_zirsazi_stock); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Documents List Section with corrected links -->
            <?php if ($report_details['include_docs'] && !empty(array_filter($documents))): ?>
                <div class="page-break"></div>
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h4 class="mb-0"><i class="bi bi-files"></i> لیست اسناد پیوست</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($documents as $type => $docs): if (!empty($docs)): ?>
                                <h5><?php echo ucfirst($type); ?></h5>
                                <ul class="list-group list-group-flush mb-3">
                                    <?php foreach ($docs as $doc):
                                        // Create the correct path for web or zip
                                        $doc_path = $is_for_zip ? 'documents/' . $type . '/' . basename($doc['path']) : $doc['path'];
                                    ?>
                                        <li class="list-group-item"><a href="<?php echo $doc_path; ?>" target="_blank"><?php echo htmlspecialchars($doc['name'] ?: basename($doc['path'])); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                        <?php endif;
                        endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </body>

    </html>
<?php
    return ob_get_clean();
}



secureSession();
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}
$expected_project_key = 'pardis';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    http_response_code(403);
    exit('Access denied');
}

$from_date_jalali = $_GET['start_date'] ?? '';
$to_date_jalali = $_GET['end_date'] ?? '';
$include_docs = isset($_GET['include_docs']) && $_GET['include_docs'] === '1';

function toGregorian($jalali_date)
{
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
function toJalali($gregorian)
{
    if (empty($gregorian)) return '-';
    $parts = explode('-', $gregorian);
    if (count($parts) === 3) {
        $j = gregorian_to_jalali((int)$parts[0], (int)$parts[1], (int)$parts[2]);
        return $j[0] . '/' . str_pad($j[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($j[2], 2, '0', STR_PAD_LEFT);
    }
    return $gregorian;
}

try {
    $pdo = getProjectDBConnection('pardis');
    $from_date_greg = toGregorian($from_date_jalali);
    $to_date_greg = toGregorian($to_date_jalali);
    $date_filter_applied = !empty($from_date_greg) && !empty($to_date_greg);
    $params = $date_filter_applied ? [$from_date_greg, $to_date_greg] : [];

    // --- DATA FETCHING (Same as the main report page) ---
    $profiles_sql = "SELECT p.item_code, SUM(p.quantity) as total_received, COALESCE(SUM(it.quantity_taken), 0) as total_taken, (SUM(p.quantity) - COALESCE(SUM(it.quantity_taken), 0)) as stock, SUM(p.length * p.quantity) as total_length_mm, MAX(p.image_file) as image_file FROM profiles p LEFT JOIN inventory_transactions it ON p.id = it.item_id AND it.item_type = 'profile'" . ($date_filter_applied ? " WHERE p.receipt_date BETWEEN ? AND ?" : "") . " GROUP BY p.item_code ORDER BY p.item_code";
    $stmt = $pdo->prepare($profiles_sql);
    $stmt->execute($params);
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $accessories_sql = "SELECT a.item_code, SUM(a.quantity) as total_received, COALESCE(SUM(it.quantity_taken), 0) as total_taken, (SUM(a.quantity) - COALESCE(SUM(it.quantity_taken), 0)) as stock, MAX(a.image_file) as image_file FROM accessories a LEFT JOIN inventory_transactions it ON a.id = it.item_id AND it.item_type = 'accessory'" . ($date_filter_applied ? " WHERE a.receipt_date BETWEEN ? AND ?" : "") . " GROUP BY a.item_code ORDER BY a.item_code";
    $stmt = $pdo->prepare($accessories_sql);
    $stmt->execute($params);
    $accessories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $zirsazi_sql = "SELECT TRIM(material_type) as material_type, SUM(quantity) as total_received FROM packing_lists" . ($date_filter_applied ? " WHERE received_date BETWEEN ? AND ?" : "") . " GROUP BY TRIM(material_type) ORDER BY material_type";
    $stmt = $pdo->prepare($zirsazi_sql);
    $stmt->execute($params);
    $zirsazi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $zirsazi_stock = $pdo->query("SELECT material_type, SUM(current_stock) as stock FROM warehouse_inventory GROUP BY material_type")->fetchAll(PDO::FETCH_KEY_PAIR);

    // --- DOCUMENT FETCHING ---
    $documents = ['zirsazi' => [], 'profiles' => [], 'accessories' => []];
    $zirsazi_doc_sql = "SELECT DISTINCT packing_number as name, document_path as path FROM packing_lists WHERE document_path IS NOT NULL AND document_path != ''" . ($date_filter_applied ? " AND received_date BETWEEN ? AND ?" : "");
    $stmt_docs = $pdo->prepare($zirsazi_doc_sql);
    $stmt_docs->execute($params);
    $documents['zirsazi'] = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

    $profiles_doc_sql = "SELECT DISTINCT pd.document_name as name, pd.document_path as path FROM packing_documents pd JOIN profiles p ON pd.item_id = p.id WHERE pd.item_type = 'profile' AND pd.document_path IS NOT NULL AND pd.document_path != ''" . ($date_filter_applied ? " AND p.receipt_date BETWEEN ? AND ?" : "");
    $stmt_docs = $pdo->prepare($profiles_doc_sql);
    $stmt_docs->execute($params);
    $documents['profiles'] = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

    $accessories_doc_sql = "SELECT DISTINCT pd.document_name as name, pd.document_path as path FROM packing_documents pd JOIN accessories a ON pd.item_id = a.id WHERE pd.item_type = 'accessory' AND pd.document_path IS NOT NULL AND pd.document_path != ''" . ($date_filter_applied ? " AND a.receipt_date BETWEEN ? AND ?" : "");
    $stmt_docs = $pdo->prepare($accessories_doc_sql);
    $stmt_docs->execute($params);
    $documents['accessories'] = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

    $report_details = [ 'current_jalali' => toJalali(date('Y-m-d')), 'is_filtered' => $date_filter_applied, 'start_date' => $from_date_jalali, 'end_date' => $to_date_jalali, 'include_docs' => $include_docs ];
    $html_content = generateComprehensiveReportHTML($profiles, $accessories, $zirsazi, $zirsazi_stock, $documents, $report_details, true); // Pass true for is_for_zip
    
    $report_html_path = tempnam(sys_get_temp_dir(), 'report') . '.html';
    file_put_contents($report_html_path, $html_content);
    
    $zip = new ZipArchive();
    $zip_filename = 'Comprehensive_Report_' . date('Y-m-d_His') . '.zip';
    $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) { throw new Exception('Cannot create ZIP file.'); }
    $zip->addFile($report_html_path, 'Comprehensive_Report.html');
    
    $added_files = [];

    // --- CORRECTED FOLDER CREATION AND FILE ADDITION ---
    // Create the directory structure first
    $zip->addEmptyDir('images');
    $zip->addEmptyDir('documents');
    $zip->addEmptyDir('documents/zirsazi');
    $zip->addEmptyDir('documents/profiles');
    $zip->addEmptyDir('documents/accessories');

    if ($include_docs) {
        foreach ($documents as $type => $docs) {
            foreach ($docs as $doc) {
                if (!empty($doc['path']) && !in_array($doc['path'], $added_files)) {
                    $full_path = __DIR__ . '/' . $doc['path'];
                    if (file_exists($full_path)) {
                        // Add the document to its specific subfolder
                        $zip->addFile($full_path, 'documents/' . $type . '/' . basename($doc['path']));
                        $added_files[] = $doc['path'];
                    }
                }
            }
        }
    }
    
    // Add Product Images to the 'images' folder
    $all_items = array_merge($profiles, $accessories);
    foreach($all_items as $item) {
        if (!empty($item['image_file']) && !in_array($item['image_file'], $added_files)) {
            $full_path = __DIR__ . '/output/images/' . $item['image_file'];
            if (file_exists($full_path)) {
                // Add the image to the 'images' folder
                $zip->addFile($full_path, 'images/' . $item['image_file']);
                $added_files[] = $item['image_file'];
            }
        }
    }
    // --- END CORRECTION ---
    
    $zip->close();
    header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="' . $zip_filename . '"'); header('Content-Length: ' . filesize($zip_path));
    readfile($zip_path);

    unlink($report_html_path); unlink($zip_path);

} catch (Exception $e) {
    logError("Comprehensive ZIP Error: " . $e->getMessage());
    http_response_code(500);
    echo "Error generating ZIP file: " . $e->getMessage();
}
?>