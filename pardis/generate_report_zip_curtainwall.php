<?php
// public_html/pardis/generate_report_zip_curtainwall.php
require_once __DIR__ . '/../sercon/bootstrap.php';
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

function generateReportHTML($profiles, $accessories, $profiles_summary, $accessories_summary, $summary, $report_title, $current_jalali, $from_date_greg, $to_date_greg, $include_docs) {
    $html = '<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $report_title . ' - پروفیل‌ها و اکسسوری‌ها</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #fff; }
        @media print {
            body { padding: 0; }
            .page-break { page-break-after: always; }
        }
        .header { text-align: center; padding: 20px; border-bottom: 3px solid #28a745; margin-bottom: 30px; }
        .header h1 { color: #28a745; margin-bottom: 10px; }
        .header p { color: #666; }
        .section { margin-bottom: 30px; }
        .section-title { background: #28a745; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: right; }
        th { background: #f0f0f0; font-weight: bold; }
        tr:nth-child(even) { background: #f9f9f9; }
        .summary-box { background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .summary-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #ddd; }
        .summary-item:last-child { border-bottom: none; }
        .footer { margin-top: 50px; text-align: center; color: #666; font-size: 12px; border-top: 2px solid #ddd; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . $report_title . '</h1>
        <h2>گزارش پروفیل‌ها و اکسسوری‌ها - پروژه دانشگاه خاتم پردیس</h2>
        <p>تاریخ گزارش: ' . $current_jalali . '</p>';
    
    if ($from_date_greg && $to_date_greg) {
        $html .= '<p>بازه زمانی: از ' . toJalali($from_date_greg) . ' تا ' . toJalali($to_date_greg) . '</p>';
    }
    
    $html .= '</div>
    
    <div class="summary-box">
        <h3 style="margin-bottom: 15px;">خلاصه گزارش</h3>
        <div class="summary-item">
            <span>تعداد کل پروفیل‌ها:</span>
            <strong>' . number_format($summary['total_profiles']) . '</strong>
        </div>
        <div class="summary-item">
            <span>مجموع تعداد پروفیل:</span>
            <strong>' . number_format($summary['total_profile_quantity']) . '</strong>
        </div>
        <div class="summary-item">
            <span>تعداد کل اکسسوری‌ها:</span>
            <strong>' . number_format($summary['total_accessories']) . '</strong>
        </div>
        <div class="summary-item">
            <span>مجموع تعداد اکسسوری:</span>
            <strong>' . number_format($summary['total_accessory_quantity']) . '</strong>
        </div>
    </div>';
    
    // Profiles Summary
    $html .= '<div class="section">
        <div class="section-title">
            <h3>خلاصه پروفیل‌های دریافتی</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">ردیف</th>
                    <th style="width: 45%;">کد پروفیل</th>
                    <th style="width: 20%;">تعداد دریافت</th>
                    <th style="width: 15%;">مجموع تعداد</th>
                    <th style="width: 15%;">مجموع طول (m)</th>
                </tr>
            </thead>
            <tbody>';
    
    $row_num = 1;
    foreach ($profiles_summary as $code => $info) {
        $html .= '<tr>
            <td style="text-align: center;">' . $row_num++ . '</td>
            <td><strong>' . htmlspecialchars($code) . '</strong></td>
            <td style="text-align: center;">' . number_format($info['count']) . '</td>
            <td style="text-align: center;"><strong>' . number_format($info['quantity']) . '</strong></td>
            <td style="text-align: center;">' . number_format($info['total_length'] / 1000, 3) . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
            <tfoot>
                <tr style="background: #e9ecef; font-weight: bold;">
                    <td colspan="2" style="text-align: left;">جمع کل:</td>
                    <td style="text-align: center;">' . number_format($summary['total_profiles']) . '</td>
                    <td style="text-align: center;">' . number_format($summary['total_profile_quantity']) . '</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>';
    
    // Accessories Summary
    $html .= '<div class="page-break"></div>
    <div class="section">
        <div class="section-title">
            <h3>خلاصه اکسسوری‌های دریافتی</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">ردیف</th>
                    <th style="width: 55%;">کد اکسسوری</th>
                    <th style="width: 20%;">تعداد دریافت</th>
                    <th style="width: 20%;">مجموع تعداد</th>
                </tr>
            </thead>
            <tbody>';
    
    $row_num = 1;
    foreach ($accessories_summary as $code => $info) {
        $html .= '<tr>
            <td style="text-align: center;">' . $row_num++ . '</td>
            <td><strong>' . htmlspecialchars($code) . '</strong></td>
            <td style="text-align: center;">' . number_format($info['count']) . '</td>
            <td style="text-align: center;"><strong>' . number_format($info['quantity']) . '</strong></td>
        </tr>';
    }
    
    $html .= '</tbody>
            <tfoot>
                <tr style="background: #e9ecef; font-weight: bold;">
                    <td colspan="2" style="text-align: left;">جمع کل:</td>
                    <td style="text-align: center;">' . number_format($summary['total_accessories']) . '</td>
                    <td style="text-align: center;">' . number_format($summary['total_accessory_quantity']) . '</td>
                </tr>
            </tfoot>
        </table>
    </div>';
    
    $html .= '<div class="footer">
        <p>این گزارش توسط سیستم مدیریت پروژه پردیس تولید شده است</p>
        <p>تاریخ چاپ: ' . $current_jalali . ' - ساعت: ' . date('H:i') . '</p>
    </div>
</body>
</html>';
    
    return $html;
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

    // Fetch profiles data
    $pdo = getProjectDBConnection('pardis');
    
    $sql_profiles = "SELECT p.*, pd.document_path, pd.document_name 
            FROM profiles p
            LEFT JOIN packing_documents pd ON p.id = pd.item_id AND pd.item_type = 'profile'
            WHERE 1=1";
    $params_profiles = [];
    
    if ($from_date_greg && $to_date_greg) {
        $sql_profiles .= " AND p.receipt_date BETWEEN ? AND ?";
        $params_profiles[] = $from_date_greg;
        $params_profiles[] = $to_date_greg;
    }
    
    $sql_profiles .= " ORDER BY p.receipt_date DESC, p.item_code ASC";
    
    $stmt = $pdo->prepare($sql_profiles);
    $stmt->execute($params_profiles);
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch accessories data
    $sql_accessories = "SELECT a.*, pd.document_path, pd.document_name 
            FROM accessories a
            LEFT JOIN packing_documents pd ON a.id = pd.item_id AND pd.item_type = 'accessory'
            WHERE 1=1";
    $params_accessories = [];
    
    if ($from_date_greg && $to_date_greg) {
        $sql_accessories .= " AND a.receipt_date BETWEEN ? AND ?";
        $params_accessories[] = $from_date_greg;
        $params_accessories[] = $to_date_greg;
    }
    
    $sql_accessories .= " ORDER BY a.receipt_date DESC, a.item_code ASC";
    
    $stmt = $pdo->prepare($sql_accessories);
    $stmt->execute($params_accessories);
    $accessories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($profiles) && empty($accessories)) {
        throw new Exception('هیچ داده‌ای برای این بازه زمانی یافت نشد');
    }

    // Calculate summary
    $summary = [
        'total_profiles' => count($profiles),
        'total_profile_quantity' => array_sum(array_column($profiles, 'quantity')),
        'total_accessories' => count($accessories),
        'total_accessory_quantity' => array_sum(array_column($accessories, 'quantity')),
    ];
    
    $profiles_summary = [];
    foreach ($profiles as $item) {
        $code = $item['item_code'];
        if (!isset($profiles_summary[$code])) {
            $profiles_summary[$code] = ['quantity' => 0, 'count' => 0, 'total_length' => 0];
        }
        $profiles_summary[$code]['quantity'] += $item['quantity'];
        $profiles_summary[$code]['count']++;
        $profiles_summary[$code]['total_length'] += ($item['length'] ?? 0) * $item['quantity'];
    }
    
    $accessories_summary = [];
    foreach ($accessories as $item) {
        $code = $item['item_code'];
        if (!isset($accessories_summary[$code])) {
            $accessories_summary[$code] = ['quantity' => 0, 'count' => 0];
        }
        $accessories_summary[$code]['quantity'] += $item['quantity'];
        $accessories_summary[$code]['count']++;
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
    $temp_dir = sys_get_temp_dir() . '/curtainwall_report_' . uniqid();
    if (!mkdir($temp_dir, 0777, true)) {
        throw new Exception('خطا در ایجاد پوشه موقت');
    }

    // Generate HTML report
    $html = generateReportHTML($profiles, $accessories, $profiles_summary, $accessories_summary, $summary, $report_title, $current_jalali, $from_date_greg, $to_date_greg, $include_docs);
    
    $report_html_name = 'Curtainwall_Report_' . date('Y-m-d_His') . '.html';
    $report_html_path = $temp_dir . '/' . $report_html_name;
    file_put_contents($report_html_path, $html);

    // Create ZIP file
    $zip = new ZipArchive();
    $zip_filename = 'Curtainwall_Report_' . date('Y-m-d_His') . '.zip';
    $zip_path = $temp_dir . '/' . $zip_filename;
    
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        throw new Exception('خطا در ایجاد فایل ZIP');
    }

    // Add main report HTML to ZIP
    $zip->addFile($report_html_path, $report_html_name);
    
    // Add documents if requested
    if ($include_docs) {
        $docs_folder = 'documents/';
        $added_docs = [];
        
        // Add profile documents
        foreach ($profiles as $item) {
            if (!empty($item['document_path']) && !in_array($item['document_path'], $added_docs)) {
                $doc_full_path = __DIR__ . '/' . $item['document_path'];
                if (file_exists($doc_full_path)) {
                    $doc_name = basename($item['document_path']);
                    $zip->addFile($doc_full_path, $docs_folder . 'profiles/' . $doc_name);
                    $added_docs[] = $item['document_path'];
                }
            }
        }
        
        // Add accessory documents
        foreach ($accessories as $item) {
            if (!empty($item['document_path']) && !in_array($item['document_path'], $added_docs)) {
                $doc_full_path = __DIR__ . '/' . $item['document_path'];
                if (file_exists($doc_full_path)) {
                    $doc_name = basename($item['document_path']);
                    $zip->addFile($doc_full_path, $docs_folder . 'accessories/' . $doc_name);
                    $added_docs[] = $item['document_path'];
                }
            }
        }
    }
    
    // Add README file
    $readme_content = "گزارش پروفیل‌ها و اکسسوری‌ها - پروژه دانشگاه خاتم پردیس\n";
    $readme_content .= "تاریخ: " . $current_jalali . "\n\n";
    $readme_content .= "محتویات:\n";
    $readme_content .= "1. " . $report_html_name . " - گزارش اصلی (فایل HTML قابل چاپ)\n";
    if ($include_docs) {
        $readme_content .= "2. پوشه documents/ - اسناد و مدارک پیوست\n\n";
    }
    $readme_content .= "تاریخ ایجاد: " . $current_jalali . "\n";
    
    $zip->addFromString('README.txt', $readme_content);
    
    $zip->close();

    // Send ZIP file to browser
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Length: ' . filesize($zip_path));
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    readfile($zip_path);
    
    // Clean up
    unlink($report_html_path);
    unlink($zip_path);
    rmdir($temp_dir);
    
} catch (Exception $e) {
    logError("ZIP Generation Error: " . $e->getMessage());
    http_response_code(500);
    echo "خطا در تولید فایل ZIP: " . $e->getMessage();
}
?>