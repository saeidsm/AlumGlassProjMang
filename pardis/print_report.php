<?php
// public_html/pardis/print_report.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
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

// Get report parameters
$report_type = $_GET['type'] ?? 'all'; // weekly, monthly, all
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$include_docs = isset($_GET['include_docs']) && $_GET['include_docs'] === '1';

// Get current Persian date
$current_gregorian = date('Y-m-d');
$current_parts = explode('-', $current_gregorian);
$jalali = gregorian_to_jalali($current_parts[0], $current_parts[1], $current_parts[2]);
$current_jalali = $jalali[0] . '/' . str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($jalali[2], 2, '0', STR_PAD_LEFT);

// Calculate date ranges based on report type
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

// Convert custom dates if provided
if (!empty($from_date) && !empty($to_date)) {
    $from_date_greg = convertJalaliToGregorian($from_date);
    $to_date_greg = convertJalaliToGregorian($to_date);
}

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

// Fetch data
try {
    $pdo = getProjectDBConnection('pardis');
    
    // Build query
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
    
    // Calculate summary statistics
    $summary = [
        'total_items' => count($data),
        'total_quantity' => array_sum(array_column($data, 'quantity')),
        'materials_count' => count(array_unique(array_column($data, 'material_type'))),
        'warehouses' => array_unique(array_filter(array_column($data, 'warehouse_name')))
    ];
    
    // Group by material type
    $materials_summary = [];
    foreach ($data as $item) {
        $type = $item['material_type'];
        if (!isset($materials_summary[$type])) {
            $materials_summary[$type] = [
                'quantity' => 0,
                'count' => 0
            ];
        }
        $materials_summary[$type]['quantity'] += $item['quantity'];
        $materials_summary[$type]['count']++;
    }
    
} catch (Exception $e) {
    die("خطا در بارگذاری اطلاعات: " . $e->getMessage());
}

// Report title based on type
$report_titles = [
    'weekly' => 'گزارش هفتگی',
    'monthly' => 'گزارش ماهانه',
    'all' => 'گزارش کامل'
];
$report_title = $report_titles[$report_type] ?? 'گزارش';

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $report_title; ?> - بارنامه‌های زیرسازی</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .page-break { page-break-after: always; }
            body { font-size: 11pt; }
            table { font-size: 10pt; }
            .container-fluid { padding: 0; }
        }
        
        body {
            font-family: 'Tahoma', 'Vazir', sans-serif;
            background: #fff;
        }
        
        .report-header {
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .report-logo {
            max-height: 80px;
        }
        
        .summary-card {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #0d6efd;
            color: white;
            padding: 12px 8px;
            font-weight: bold;
            border: 1px solid #0a58ca;
        }
        
        .data-table td {
            padding: 10px 8px;
            border: 1px solid #dee2e6;
        }
        
        .data-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .doc-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .signature-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid #dee2e6;
        }
        
        .signature-box {
            border: 1px solid #ddd;
            padding: 40px 20px;
            text-align: center;
            min-height: 120px;
        }
        
        .materials-summary-table {
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <!-- Print Control Buttons -->
        <div class="no-print mb-3 d-flex justify-content-between">
            <div>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="bi bi-printer"></i> چاپ گزارش
                </button>
                <a href="packing_lists.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-right"></i> بازگشت
                </a>
            </div>
            <div>
                <button onclick="exportToPDF()" class="btn btn-success">
                    <i class="bi bi-file-pdf"></i> خروجی PDF
                </button>
            </div>
        </div>

        <!-- Report Header -->
        <div class="report-header">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <!-- Add your logo here -->
                    <img src="assets/logo.png" alt="Logo" class="report-logo" onerror="this.style.display='none'">
                </div>
                <div class="col-md-6 text-center">
                    <h2 class="mb-1">پروژه دانشگاه خاتم پردیس</h2>
                    <h4 class="text-primary"><?php echo $report_title; ?> بارنامه‌های دریافتی</h4>
                    <p class="text-muted mb-0">
                        تاریخ گزارش: <strong><?php echo $current_jalali; ?></strong>
                    </p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="small text-muted">
                        <div>کد گزارش: RPT-<?php echo date('YmdHis'); ?></div>
                        <div>تعداد صفحات: <span id="pageCount">-</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Date Range Info -->
        <?php if ($from_date_greg && $to_date_greg): ?>
        <div class="alert alert-info">
            <strong>بازه زمانی گزارش:</strong> 
            از تاریخ <?php echo toJalali($from_date_greg); ?> 
            تا تاریخ <?php echo toJalali($to_date_greg); ?>
        </div>
        <?php endif; ?>

        <!-- Summary Section -->
        <div class="summary-card">
            <h5 class="mb-3"><i class="bi bi-graph-up"></i> خلاصه گزارش</h5>
            <div class="row">
                <div class="col-md-3">
                    <div class="summary-item">
                        <span>تعداد کل بارنامه‌ها:</span>
                        <strong class="text-primary"><?php echo number_format($summary['total_items']); ?></strong>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-item">
                        <span>مجموع تعداد دریافتی:</span>
                        <strong class="text-success"><?php echo number_format($summary['total_quantity']); ?></strong>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-item">
                        <span>تعداد انواع متریال:</span>
                        <strong class="text-info"><?php echo number_format($summary['materials_count']); ?></strong>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-item">
                        <span>انبارهای فعال:</span>
                        <strong class="text-warning"><?php echo count($summary['warehouses']); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Materials Summary Table -->
        <div class="materials-summary-table">
            <h5 class="mb-3"><i class="bi bi-box-seam"></i> خلاصه مواد دریافتی</h5>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">ردیف</th>
                        <th style="width: 50%;">نوع متریال</th>
                        <th style="width: 20%;">تعداد بارنامه</th>
                        <th style="width: 25%;">مجموع تعداد</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $row_num = 1;
                    foreach ($materials_summary as $material => $info): 
                    ?>
                    <tr>
                        <td class="text-center"><?php echo $row_num++; ?></td>
                        <td><strong><?php echo htmlspecialchars($material); ?></strong></td>
                        <td class="text-center"><?php echo number_format($info['count']); ?></td>
                        <td class="text-center"><strong><?php echo number_format($info['quantity']); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #e9ecef; font-weight: bold;">
                        <td colspan="2" class="text-end">جمع کل:</td>
                        <td class="text-center"><?php echo number_format($summary['total_items']); ?></td>
                        <td class="text-center"><?php echo number_format($summary['total_quantity']); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="page-break"></div>

        <!-- Detailed Data Table -->
        <h5 class="mt-4 mb-3"><i class="bi bi-table"></i> جزئیات بارنامه‌ها</h5>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">ردیف</th>
                    <th style="width: 10%;">شماره بارنامه</th>
                    <th style="width: 20%;">نوع متریال</th>
                    <th style="width: 10%;">تاریخ</th>
                    <th style="width: 8%;">تعداد</th>
                    <th style="width: 12%;">تامین‌کننده</th>
                    <th style="width: 10%;">انبار</th>
                    <?php if ($include_docs): ?>
                    <th style="width: 10%;">مدرک</th>
                    <?php endif; ?>
                    <th style="width: 15%;">یادداشت</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $row_num = 1;
                foreach ($data as $item): 
                ?>
                <tr>
                    <td class="text-center"><?php echo $row_num++; ?></td>
                    <td><?php echo htmlspecialchars($item['packing_number']); ?></td>
                    <td><strong><?php echo htmlspecialchars($item['material_type']); ?></strong></td>
                    <td><?php echo toJalali($item['received_date']); ?></td>
                    <td class="text-center"><strong><?php echo number_format($item['quantity']); ?></strong></td>
                    <td><?php echo htmlspecialchars($item['supplier'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($item['warehouse_name'] ?? '-'); ?></td>
                    <?php if ($include_docs): ?>
                    <td class="text-center">
                        <?php if (!empty($item['document_path'])): ?>
                            <?php if ($item['document_type'] === 'pdf'): ?>
                                <i class="bi bi-file-pdf text-danger"></i>
                                <small class="d-block">PDF</small>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($item['document_path']); ?>" 
                                     class="doc-thumbnail" 
                                     alt="Document">
                            <?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td><small><?php echo htmlspecialchars($item['notes'] ?? '-'); ?></small></td>
                </tr>
                <?php 
                // Add page break every 25 rows for better printing
                if ($row_num % 25 === 0 && $row_num < count($data)): 
                ?>
                </tbody>
            </table>
            <div class="page-break"></div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">ردیف</th>
                        <th style="width: 10%;">شماره بارنامه</th>
                        <th style="width: 20%;">نوع متریال</th>
                        <th style="width: 10%;">تاریخ</th>
                        <th style="width: 8%;">تعداد</th>
                        <th style="width: 12%;">تامین‌کننده</th>
                        <th style="width: 10%;">انبار</th>
                        <?php if ($include_docs): ?>
                        <th style="width: 10%;">مدرک</th>
                        <?php endif; ?>
                        <th style="width: 15%;">یادداشت</th>
                    </tr>
                </thead>
                <tbody>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Signature Section -->
        <div class="signature-section no-print">
            <div class="row">
                <div class="col-md-4">
                    <div class="signature-box">
                        <p class="mb-4">تهیه‌کننده</p>
                        <div class="border-top pt-2 mt-5">
                            <small>نام و نام خانوادگی / امضا</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="signature-box">
                        <p class="mb-4">بررسی‌کننده</p>
                        <div class="border-top pt-2 mt-5">
                            <small>نام و نام خانوادگی / امضا</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="signature-box">
                        <p class="mb-4">تایید‌کننده</p>
                        <div class="border-top pt-2 mt-5">
                            <small>نام و نام خانوادگی / امضا</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4 text-muted small">
            <p class="mb-0">این گزارش توسط سیستم مدیریت پروژه پردیس تولید شده است</p>
            <p>تاریخ چاپ: <?php echo $current_jalali; ?> - ساعت: <?php echo date('H:i'); ?></p>
        </div>
    </div>

    <script>
        // Calculate page count
        window.addEventListener('load', function() {
            // Approximate page count based on content height
            const pageHeight = 1100; // A4 page height in pixels
            const contentHeight = document.body.scrollHeight;
            const pageCount = Math.ceil(contentHeight / pageHeight);
            document.getElementById('pageCount').textContent = pageCount;
        });

        function exportToPDF() {
            window.print();
        }
    </script>
</body>
</html>