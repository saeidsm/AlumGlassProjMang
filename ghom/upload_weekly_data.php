<?php
ob_start();
require_once __DIR__ . '/../../sercon/bootstrap.php';
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

try {
    secureSession();
    
    // Security Check
    if (!isLoggedIn() || !in_array($_SESSION['role'], ['superuser', 'pco', 'admin'])) {
        throw new Exception('دسترسی غیرمجاز');
    }

    $pdo = getProjectDBConnection('ghom');
    $pdo->beginTransaction();
    
    // Get report date
    if (empty($_POST['report_date'])) {
        throw new Exception('لطفا تاریخ گزارش را وارد نمایید');
    }
    
    $report_date = cleanNum($_POST['report_date']);
    $user_id = $_SESSION['user_id'] ?? 0;
    $uploadResults = [];
    
    // Process Main Data
    if (isset($_FILES['main_data']) && $_FILES['main_data']['error'] === UPLOAD_ERR_OK) {
        $count = processMainDataCSV($_FILES['main_data']['tmp_name'], $report_date, $pdo);
        logUpload($pdo, $report_date, 'main_data', $_FILES['main_data']['name'], $user_id, $count);
        $uploadResults[] = "جدول اصلی: $count رکورد";
    }
    
    // Process Activity Data
    if (isset($_FILES['activity_data']) && $_FILES['activity_data']['error'] === UPLOAD_ERR_OK) {
        $count = processActivityDataCSV($_FILES['activity_data']['tmp_name'], $report_date, $pdo);
        logUpload($pdo, $report_date, 'activity_data', $_FILES['activity_data']['name'], $user_id, $count);
        $uploadResults[] = "فعالیت‌ها: $count رکورد";
    }
    
    // Process S-Curve Data (ACTUAL ONLY - Plan is set once)
    if (isset($_FILES['scurve_data']) && $_FILES['scurve_data']['error'] === UPLOAD_ERR_OK) {
        $count = processSCurveDataCSV($_FILES['scurve_data']['tmp_name'], $report_date, $pdo);
        logUpload($pdo, $report_date, 'scurve_data', $_FILES['scurve_data']['name'], $user_id, $count);
        $uploadResults[] = "منحنی S: $count رکورد";
    }
    
    // Process S-Curve PLAN (One-time upload)
    if (isset($_FILES['scurve_plan']) && $_FILES['scurve_plan']['error'] === UPLOAD_ERR_OK) {
        $count = processSCurvePlanCSV($_FILES['scurve_plan']['tmp_name'], $pdo);
        logUpload($pdo, $report_date, 'scurve_plan', $_FILES['scurve_plan']['name'], $user_id, $count);
        $uploadResults[] = "برنامه S-Curve: $count رکورد";
    }
    
    $pdo->commit();
    
    if (empty($uploadResults)) {
        throw new Exception('هیچ فایلی آپلود نشد');
    }
    
    ob_clean();
    echo json_encode([
        'success' => true, 
        'message' => "آپلود موفق برای $report_date\n" . implode("\n", $uploadResults)
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ==================== HELPER FUNCTIONS ====================

function logUpload($pdo, $date, $type, $name, $uid, $count) {
    $stmt = $pdo->prepare("INSERT INTO upload_history (report_date, file_type, file_name, uploaded_by, records_count, created_at) 
                          VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$date, $type, $name, $uid, $count]);
}

function cleanNum($string) {
    if ($string === null || $string === '') return '0';
    
    // Persian to English numbers
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $string = str_replace($persian, $english, $string);
    
    // Remove formatting
    $string = str_replace(['%', ',', ' '], '', $string);
    return trim($string);
}

function processMainDataCSV($file, $report_date, $pdo) {
    $handle = fopen($file, 'r');
    if (!$handle) throw new Exception('خطا در خواندن فایل جدول اصلی');
    
    // Skip BOM if exists
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    
    fgetcsv($handle); // Skip header
    
    // Delete existing data for this report date
    $stmtDel = $pdo->prepare("DELETE FROM weekly_reports WHERE report_date = ?");
    $stmtDel->execute([$report_date]);
    
    $stmt = $pdo->prepare("INSERT INTO weekly_reports (
        report_date, activity_id, wbs, activity_name, duration, 
        start_date, end_date, weight_factor, 
        prev_plan, prev_actual, prev_deviation,
        cumulative_plan, cumulative_actual, cumulative_deviation,
        current_plan, current_actual, current_deviation
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $count = 0;
    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) < 16) continue;
        
        $stmt->execute([
            $report_date,
            $data[0] ?? 0,
            $data[1] ?? '',
            $data[2] ?? '',
            cleanNum($data[3]),
            $data[4] ?? '',
            $data[5] ?? '',
            cleanNum($data[6]),
            cleanNum($data[7]),
            cleanNum($data[8]),
            cleanNum($data[9]),
            cleanNum($data[10]),
            cleanNum($data[11]),
            cleanNum($data[12]),
            cleanNum($data[13]),
            cleanNum($data[14]),
            cleanNum($data[15])
        ]);
        $count++;
    }
    
    fclose($handle);
    return $count;
}

function processActivityDataCSV($file, $report_date, $pdo) {
    $handle = fopen($file, 'r');
    if (!$handle) throw new Exception('خطا در خواندن فایل فعالیت‌ها');
    
    // Skip BOM
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    
    fgetcsv($handle); // Skip header
    
    $stmtDel = $pdo->prepare("DELETE FROM activity_summary WHERE report_date = ?");
    $stmtDel->execute([$report_date]);
    
    $stmt = $pdo->prepare("INSERT INTO activity_summary (
        report_date, row_num, activity_name, plan_area, actual_area, deviation_area
    ) VALUES (?, ?, ?, ?, ?, ?)");
    
    $count = 0;
    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) < 5) continue;
        
        $stmt->execute([
            $report_date,
            $data[0] ?? 0,
            $data[1] ?? '',
            cleanNum($data[2]),
            cleanNum($data[3]),
            cleanNum($data[4])
        ]);
        $count++;
    }
    
    fclose($handle);
    return $count;
}

/**
 * Process S-Curve ACTUAL data (weekly updates)
 * CSV Format: date_point, block_type, actual_cumulative
 * Example: 2025-09-05, total, 1.85
 */
function processSCurveDataCSV($file, $report_date, $pdo) {
    $handle = fopen($file, 'r');
    if (!$handle) throw new Exception('خطا در خواندن فایل S-Curve');
    
    // Skip BOM
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    
    fgetcsv($handle); // Skip header: date_point,block_type,actual_cumulative
    
    // Strategy: Update only actual_cumulative for existing date_point + block_type
    // If no plan exists yet, insert with plan=0
    
    $stmt = $pdo->prepare("
        INSERT INTO scurve_data (report_date, date_point, block_type, plan_periodic, plan_cumulative, actual_cumulative)
        VALUES (?, ?, ?, 0, 0, ?)
        ON DUPLICATE KEY UPDATE 
            actual_cumulative = VALUES(actual_cumulative),
            report_date = VALUES(report_date)
    ");
    
    $count = 0;
    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) < 3) continue;
        
        $date_point = $data[0] ?? '';
        $block_type = $data[1] ?? 'total';
        $actual = cleanNum($data[2]);
        
        if (empty($date_point)) continue;
        
        $stmt->execute([$report_date, $date_point, $block_type, $actual]);
        $count++;
    }
    
    fclose($handle);
    return $count;
}

/**
 * Process S-Curve PLAN data (one-time setup)
 * CSV Format: date_point, block_type, plan_periodic, plan_cumulative
 * Example: 2025-09-05, total, 2.50, 2.50
 */
function processSCurvePlanCSV($file, $pdo) {
    $handle = fopen($file, 'r');
    if (!$handle) throw new Exception('خطا در خواندن فایل برنامه S-Curve');
    
    // Skip BOM
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    
    fgetcsv($handle); // Skip header: date_point,block_type,plan_periodic,plan_cumulative
    
    // Strategy: Insert/Update plan values, keep existing actuals
    $stmt = $pdo->prepare("
        INSERT INTO scurve_data (report_date, date_point, block_type, plan_periodic, plan_cumulative, actual_cumulative)
        VALUES ('PLAN_BASELINE', ?, ?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE 
            plan_periodic = VALUES(plan_periodic),
            plan_cumulative = VALUES(plan_cumulative)
    ");
    
    $count = 0;
    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) < 4) continue;
        
        $date_point = $data[0] ?? '';
        $block_type = $data[1] ?? 'total';
        $plan_periodic = cleanNum($data[2]);
        $plan_cumulative = cleanNum($data[3]);
        
        if (empty($date_point)) continue;
        
        $stmt->execute([$date_point, $block_type, $plan_periodic, $plan_cumulative]);
        $count++;
    }
    
    fclose($handle);
    return $count;
}