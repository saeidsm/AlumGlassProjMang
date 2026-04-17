<?php
// public_html/pardis/generate_report_zip.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$expected_project_key = 'pardis';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    http_response_code(403);
    exit('Access denied');
}

// Get parameters
$report_type = $_GET['type'] ?? 'all';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$include_docs = isset($_GET['include_docs']) && $_GET['include_docs'] === '1';

function convertJalaliToGregorian($jalali) {
    $parts = explode('/', $jalali);
    if (count($parts) === 3) {
        $greg = jalali_to_gregorian($parts[0], $parts[1], $parts[2]);
        return $greg[0] . '-' . str_pad($greg[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($greg[2], 2, '0', STR_PAD_LEFT);
    }
    return null;
}

function toJalali($gregorian_date) {
    if (empty($gregorian_date)) return '-';
    $parts = explode(' ', $gregorian_date);
    $date_part = $parts[0];
    $date_parts = explode('-', $date_part);
    if (count($date_parts) !== 3) return $gregorian_date;
    list($y, $m, $d) = $date_parts;
    $j = gregorian_to_jalali($y, $m, $d);
    return $j[0] . '/' . str_pad($j[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($j[2], 2, '0', STR_PAD_LEFT);
}

try {
    // Calculate date ranges
    if ($report_type === 'weekly') {
        $to_date_greg = date('Y-m-d');
        $from_date_greg = date('Y-m-d', strtotime('-7 days'));
    } elseif ($report_type === 'monthly') {
        $to_date_greg = date('Y-m-d');
        $from_date_greg = date('Y-m-d', strtotime('-30 days'));
    } else {
        $from_date_greg = null;
        $to_date_greg = null;
    }

    if (!empty($from_date) && !empty($to_date)) {
        $from_date_greg = convertJalaliToGregorian($from_date);
        $to_date_greg = convertJalaliToGregorian($to_date);
    }

    // Fetch data
    $pdo = getProjectDBConnection('pardis');
    
    $sql = "SELECT pl.*, w.name as warehouse_name 
            FROM packing_lists pl
            LEFT JOIN warehouses w ON pl.warehouse_id = w.id
            WHERE 1=1";
    $params = [];
    
    if ($from_date_greg && $to_date_greg) {
        $sql .= " AND pl.received_date BETWEEN ? AND ?";
        $params[] = $from_date_greg;
        $params[] = $to_date_greg;
    }
    
    $sql .= " ORDER BY pl.received_date DESC, pl.material_type ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        throw new Exception('هیچ داده‌ای برای این بازه زمانی یافت نشد');
    }

    // Calculate summary
  $summary = [
        'total_items' => count($data),
        'unique_packing_slips' => count(array_unique(array_column($data, 'packing_number'))),
        'total_quantity' => array_sum(array_column($data, 'quantity')),
        'materials_count' => count(array_unique(array_column($data, 'material_type'))),
    ];
    
    $materials_summary = [];
    foreach ($data as $item) {
        $type = $item['material_type'];
        if (!isset($materials_summary[$type])) {
            $materials_summary[$type] = ['quantity' => 0, 'count' => 0];
        }
        $materials_summary[$type]['quantity'] += $item['quantity'];
        $materials_summary[$type]['count']++;
    }

    // Get current date
    $current_gregorian = date('Y-m-d');
    $current_parts = explode('-', $current_gregorian);
    $jalali = gregorian_to_jalali($current_parts[0], $current_parts[1], $current_parts[2]);
    $current_jalali = $jalali[0] . '/' . str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($jalali[2], 2, '0', STR_PAD_LEFT);

    $report_titles = [
        'weekly' => 'گزارش هفتگی',
        'monthly' => 'گزارش ماهانه',
        'all' => 'گزارش کامل'
    ];
    $report_title = $report_titles[$report_type] ?? 'گزارش';

    // Create temporary directory for ZIP contents
    $temp_dir = sys_get_temp_dir() . '/report_' . uniqid();
    if (!mkdir($temp_dir, 0777, true)) {
        throw new Exception('خطا در ایجاد پوشه موقت');
    }

    // Generate HTML report and save as HTML file (instead of PDF)
    $html = generateReportHTML($data, $materials_summary, $summary, $report_title, $current_jalali, $from_date_greg, $to_date_greg, $include_docs);
    
    $report_html_name = 'Report_' . date('Y-m-d_His') . '.html';
    $report_html_path = $temp_dir . '/' . $report_html_name;
    file_put_contents($report_html_path, $html);

    // Create ZIP file
    $zip = new ZipArchive();
    $zip_filename = 'Materials_Report_' . date('Y-m-d_His') . '.zip';
    $zip_path = $temp_dir . '/' . $zip_filename;
    
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        throw new Exception('خطا در ایجاد فایل ZIP');
    }

    // Add main report HTML to ZIP
    $zip->addFile($report_html_path, $report_html_name);
    
    // Add README file with instructions
    $readme_content = "گزارش مواد زیرسازی - پروژه دانشگاه خاتم پردیس\n";
    $readme_content .= "تاریخ: " . $current_jalali . "\n\n";
    $readme_content .= "محتویات:\n";
    $readme_content .= "1. " . $report_html_name . " - گزارش اصلی (فایل HTML قابل چاپ)\n";
    if ($include_docs) {
        $readme_content .= "2. پوشه documents/ - اسناد و مدارک پیوست\n\n";
        $readme_content .= "برای مشاهده گزارش، فایل HTML را در مرورگر باز کنید.\n";
        $readme_content .= "برای چاپ: فایل را باز کرده و از منوی Print مرورگر استفاده کنید.\n";
    }
    $zip->addFromString('README.txt', $readme_content);

    // Add document PDFs if requested
    if ($include_docs) {
        $docs_folder = 'documents/';
        $zip->addEmptyDir($docs_folder);
        
        $doc_counter = 0;
        foreach ($data as $item) {
            if (!empty($item['document_path']) && file_exists(__DIR__ . '/' . $item['document_path'])) {
                $doc_path = __DIR__ . '/' . $item['document_path'];
                $extension = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
                
                // Only include PDFs and images
                if (in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'])) {
$safe_packing_number = preg_replace('/[^a-zA-Z0-9_-]/', '_', $item['packing_number']);                   
                     $new_filename = $docs_folder . $safe_packing_number . '.' . $extension;
                    
                    $zip->addFile($doc_path, $new_filename);
                    $doc_counter++;
                }
            }
        }
    }

    $zip->close();

    // Send ZIP file to browser
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Length: ' . filesize($zip_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    readfile($zip_path);

    // Cleanup
    unlink($report_html_path);
    unlink($zip_path);
    
    // Remove temp directory if empty
    @rmdir($temp_dir);
    
} catch (Exception $e) {
    error_log("ZIP Generation Error: " . $e->getMessage());
    http_response_code(500);
    echo "خطا در تولید فایل ZIP: " . $e->getMessage();
}

function generateReportHTML($data, $materials_summary, $summary, $report_title, $current_jalali, $from_date_greg, $to_date_greg, $include_docs) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $report_title; ?> - گزارش زیرسازی</title>
        <style>
            @media print {
                .no-print { display: none !important; }
                .page-break { page-break-after: always; }
                body { font-size: 10pt; }
                button { display: none; }
            }
            
            body {
                font-family: Tahoma, Arial, sans-serif;
                direction: rtl;
                text-align: right;
                margin: 20px;
                background: #fff;
            }
            .header {
                text-align: center;
                border-bottom: 3px solid #0066cc;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            .header h1 {
                color: #0066cc;
                margin: 10px 0;
            }
            .summary-box {
                background: #f5f5f5;
                border: 2px solid #ddd;
                padding: 15px;
                margin: 20px 0;
            }
            .summary-item {
                display: inline-block;
                width: 23%;
                text-align: center;
                padding: 10px;
                margin: 5px;
                background: white;
                border: 1px solid #ddd;
            }
            .summary-item strong {
                display: block;
                font-size: 20px;
                color: #0066cc;
                margin-bottom: 5px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                font-size: 9pt;
            }
            th {
                background: #0066cc;
                color: white;
                padding: 10px 5px;
                border: 1px solid #0052a3;
                font-weight: bold;
            }
            td {
                padding: 8px 5px;
                border: 1px solid #ddd;
            }
            tr:nth-child(even) {
                background: #f9f9f9;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 2px solid #ddd;
                text-align: center;
                font-size: 9pt;
                color: #666;
            }
            .page-break {
                page-break-after: always;
            }
            .doc-link {
                color: #0066cc;
                font-size: 8pt;
            }
            .print-btn {
                position: fixed;
                top: 20px;
                left: 20px;
                background: #0066cc;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                z-index: 1000;
            }
            .print-btn:hover {
                background: #0052a3;
            }
        </style>
    </head>
    <body>
        <button class="print-btn no-print" onclick="window.print()">
            🖨️ چاپ گزارش
        </button>
        
        <div class="header">
            <h1>پروژه دانشگاه خاتم پردیس</h1>
            <h2><?php echo $report_title; ?> - بارنامه‌های دریافتی</h2>
            <p>تاریخ گزارش: <strong><?php echo $current_jalali; ?></strong></p>
            <?php if ($from_date_greg && $to_date_greg): ?>
            <p style="color: #666;">
                از تاریخ: <?php echo toJalali($from_date_greg); ?> 
                تا تاریخ: <?php echo toJalali($to_date_greg); ?>
            </p>
            <?php endif; ?>
        </div>

        <div class="summary-box">
            <h3 style="margin-top: 0;">خلاصه گزارش</h3>
             <div class="summary-item">
                <strong><?php echo number_format($summary['unique_packing_slips']); ?></strong>
                <span>تعداد بارنامه</span>
            </div>
            <div class="summary-item">
                <strong><?php echo number_format($summary['total_quantity']); ?></strong>
                <span>مجموع موارد</span>
            </div>
            <div class="summary-item">
                <strong><?php echo number_format($summary['materials_count']); ?></strong>
                <span>تعداد متریال</span>
            </div>
        </div>

        <h3>خلاصه مواد دریافتی</h3>
        <table>
            <thead>
                <tr>
                    <th width="8%">ردیف</th>
                    <th width="50%">نوع متریال</th>
                    <th width="17%">تعداد بارنامه</th>
                    <th width="25%">مجموع تعداد</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $row_num = 1;
                foreach ($materials_summary as $material => $info): 
                ?>
                <tr>
                    <td style="text-align: center;"><?php echo $row_num++; ?></td>
                    <td><strong><?php echo htmlspecialchars($material); ?></strong></td>
                    <td style="text-align: center;"><?php echo number_format($info['count']); ?></td>
                    <td style="text-align: center;"><strong><?php echo number_format($info['quantity']); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #e0e0e0; font-weight: bold;">
                    <td colspan="2" style="text-align: left; padding-right: 10px;">جمع کل:</td>
                    <td style="text-align: center;"><?php echo number_format($summary['total_items']); ?></td>
                    <td style="text-align: center;"><?php echo number_format($summary['total_quantity']); ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="page-break"></div>

        <h3>جزئیات بارنامه‌ها</h3>
        <table>
            <thead>
                <tr>
                    <th width="5%">ردیف</th>
                    <th width="10%">شماره</th>
                    <th width="20%">متریال</th>
                    <th width="10%">تاریخ</th>
                    <th width="8%">تعداد</th>
                    <th width="12%">تامین‌کننده</th>
                    <th width="10%">انبار</th>
                    <?php if ($include_docs): ?>
                    <th width="10%">سند</th>
                    <?php endif; ?>
                    <th width="15%">یادداشت</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $row_num = 1;
                foreach ($data as $item): 
                    $doc_status = '-';
                    if (!empty($item['document_path'])) {
                        $safe_packing = preg_replace('/[^a-zA-Z0-9_-]/', '_', $item['packing_number']);
                        $extension = pathinfo($item['document_path'], PATHINFO_EXTENSION);
                        $doc_filename = 'documents/' . $safe_packing . '.' . $extension;
                        // Create a clickable link to the document inside the zip
                        $doc_status = '<a href="' . htmlspecialchars($doc_filename) . '" target="_blank" class="doc-link">📎 مشاهده سند</a>';
                    }
                ?>
                <tr>
                    <td style="text-align: center;"><?php echo $row_num++; ?></td>
                    <td><?php echo htmlspecialchars($item['packing_number']); ?></td>
                    <td><strong><?php echo htmlspecialchars($item['material_type']); ?></strong></td>
                    <td><?php echo toJalali($item['received_date']); ?></td>
                    <td style="text-align: center;"><strong><?php echo number_format($item['quantity']); ?></strong></td>
                    <td><?php echo htmlspecialchars($item['supplier'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($item['warehouse_name'] ?? '-'); ?></td>
                    <?php if ($include_docs): ?>
                    <td style="font-size: 7pt;"><?php echo $doc_status; ?></td>
                    <?php endif; ?>
                    <td style="font-size: 8pt;"><?php echo htmlspecialchars(substr($item['notes'] ?? '-', 0, 50)); ?></td>
                </tr>
                <?php 
                if ($row_num % 20 === 0 && $row_num < count($data)): 
                ?>
            </tbody>
        </table>
        <div class="page-break"></div>
        <table>
            <thead>
                <tr>
                    <th width="5%">ردیف</th>
                    <th width="10%">شماره</th>
                    <th width="20%">متریال</th>
                    <th width="10%">تاریخ</th>
                    <th width="8%">تعداد</th>
                    <th width="12%">تامین‌کننده</th>
                    <th width="10%">انبار</th>
                    <?php if ($include_docs): ?>
                    <th width="10%">سند</th>
                    <?php endif; ?>
                    <th width="15%">یادداشت</th>
                </tr>
            </thead>
            <tbody>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="footer">
            <p><strong>پروژه دانشگاه خاتم پردیس</strong></p>
            <p>تاریخ تولید گزارش: <?php echo $current_jalali; ?> - ساعت: <?php echo date('H:i'); ?></p>
            <?php if ($include_docs): ?>
            <p style="color: #0066cc;">📎 اسناد پیوست در پوشه "documents" قرار دارند</p>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>