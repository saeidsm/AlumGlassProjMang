<?php
// public_html/pardis/print_report_curtainwall.php
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

// Fetch profiles and accessories data
try {
    $pdo = getProjectDBConnection('pardis');
    
    // Build query for profiles
    $sql_profiles = "SELECT p.*, pd.document_path 
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
    
    // Build query for accessories
    $sql_accessories = "SELECT a.*, pd.document_path 
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
    
    // Calculate summary statistics
    $summary = [
        'total_profiles' => count($profiles),
        'total_profile_quantity' => array_sum(array_column($profiles, 'quantity')),
        'total_accessories' => count($accessories),
        'total_accessory_quantity' => array_sum(array_column($accessories, 'quantity')),
        'unique_profile_codes' => count(array_unique(array_column($profiles, 'item_code'))),
        'unique_accessory_codes' => count(array_unique(array_column($accessories, 'item_code')))
    ];
    
    // Group by item_code for profiles
    $profiles_summary = [];
    foreach ($profiles as $item) {
        $code = $item['item_code'];
        if (!isset($profiles_summary[$code])) {
            $profiles_summary[$code] = [
                'quantity' => 0,
                'count' => 0,
                'total_length' => 0
            ];
        }
        $profiles_summary[$code]['quantity'] += $item['quantity'];
        $profiles_summary[$code]['count']++;
        $profiles_summary[$code]['total_length'] += ($item['length'] ?? 0) * $item['quantity'];
    }
    
    // Group by item_code for accessories
    $accessories_summary = [];
    foreach ($accessories as $item) {
        $code = $item['item_code'];
        if (!isset($accessories_summary[$code])) {
            $accessories_summary[$code] = [
                'quantity' => 0,
                'count' => 0
            ];
        }
        $accessories_summary[$code]['quantity'] += $item['quantity'];
        $accessories_summary[$code]['count']++;
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
    <title><?php echo $report_title; ?> - پروفیل‌ها و اکسسوری‌ها</title>
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
            border-bottom: 3px solid #28a745;
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
            background: #28a745;
            color: white;
            padding: 12px 8px;
            font-weight: bold;
            border: 1px solid #1e7e34;
        }
        
        .data-table td {
            padding: 10px 8px;
            border: 1px solid #dee2e6;
        }
        
        .data-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .section-header {
            background: #28a745;
            color: white;
            padding: 15px;
            margin-top: 30px;
            margin-bottom: 20px;
            border-radius: 5px;
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
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <!-- Print Control Buttons -->
        <div class="no-print mb-3 d-flex justify-content-between">
            <div>
                <button onclick="window.print()" class="btn btn-success">
                    <i class="bi bi-printer"></i> چاپ گزارش
                </button>
                <a href="packing_list_viewer.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-right"></i> بازگشت
                </a>
            </div>
        </div>

        <!-- Report Header -->
        <div class="report-header">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <img src="assets/logo.png" alt="Logo" class="report-logo" onerror="this.style.display='none'">
                </div>
                <div class="col-md-6 text-center">
                    <h2 class="mb-1">پروژه دانشگاه خاتم پردیس</h2>
                    <h4 class="text-success"><?php echo $report_title; ?> پروفیل‌ها و اکسسوری‌ها</h4>
                    <p class="text-muted mb-0">
                        تاریخ گزارش: <strong><?php echo $current_jalali; ?></strong>
                    </p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="small text-muted">
                        <div>کد گزارش: CW-<?php echo date('YmdHis'); ?></div>
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
                        <span>تعداد کل پروفیل‌ها:</span>
                        <strong class="text-success"><?php echo number_format($summary['total_profiles']); ?></strong>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-item">
                        <span>مجموع تعداد پروفیل:</span>
                        <strong class="text-primary"><?php echo number_format($summary['total_profile_quantity']); ?></strong>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-item">
                        <span>تعداد کل اکسسوری‌ها:</span>
                        <strong class="text-success"><?php echo number_format($summary['total_accessories']); ?></strong>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-item">
                        <span>مجموع تعداد اکسسوری:</span>
                        <strong class="text-primary"><?php echo number_format($summary['total_accessory_quantity']); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profiles Summary -->
        <div class="section-header">
            <h5 class="mb-0"><i class="bi bi-box-seam"></i> خلاصه پروفیل‌های دریافتی</h5>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">ردیف</th>
                    <th style="width: 45%;">کد پروفیل</th>
                    <th style="width: 20%;">تعداد دریافت</th>
                    <th style="width: 15%;">مجموع تعداد</th>
                    <th style="width: 15%;">مجموع طول (m)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $row_num = 1;
                foreach ($profiles_summary as $code => $info): 
                ?>
                <tr>
                    <td class="text-center"><?php echo $row_num++; ?></td>
                    <td><strong><?php echo htmlspecialchars($code); ?></strong></td>
                    <td class="text-center"><?php echo number_format($info['count']); ?></td>
                    <td class="text-center"><strong><?php echo number_format($info['quantity']); ?></strong></td>
                    <td class="text-center"><?php echo number_format($info['total_length'] / 1000, 3); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #e9ecef; font-weight: bold;">
                    <td colspan="2" class="text-end">جمع کل:</td>
                    <td class="text-center"><?php echo number_format($summary['total_profiles']); ?></td>
                    <td class="text-center"><?php echo number_format($summary['total_profile_quantity']); ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="page-break"></div>

        <!-- Accessories Summary -->
        <div class="section-header">
            <h5 class="mb-0"><i class="bi bi-tools"></i> خلاصه اکسسوری‌های دریافتی</h5>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">ردیف</th>
                    <th style="width: 55%;">کد اکسسوری</th>
                    <th style="width: 20%;">تعداد دریافت</th>
                    <th style="width: 20%;">مجموع تعداد</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $row_num = 1;
                foreach ($accessories_summary as $code => $info): 
                ?>
                <tr>
                    <td class="text-center"><?php echo $row_num++; ?></td>
                    <td><strong><?php echo htmlspecialchars($code); ?></strong></td>
                    <td class="text-center"><?php echo number_format($info['count']); ?></td>
                    <td class="text-center"><strong><?php echo number_format($info['quantity']); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #e9ecef; font-weight: bold;">
                    <td colspan="2" class="text-end">جمع کل:</td>
                    <td class="text-center"><?php echo number_format($summary['total_accessories']); ?></td>
                    <td class="text-center"><?php echo number_format($summary['total_accessory_quantity']); ?></td>
                </tr>
            </tfoot>
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
        function exportToPDF() {
            window.print();
        }
    </script>
</body>
</html>