<?php
// /public_html/ghom/get_chart_data.php

// Ensure these files are correctly included and configure your database connection.
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

// Secure the API endpoint
secureSession();
if (!isLoggedIn()) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Authentication required']);
    exit();
}
$user_role = $_SESSION['role'];
$has_full_access = in_array($user_role, ['admin', 'user', 'superuser']);

try {
    // --- DATABASE CONNECTION ---
    $pdo = getProjectDBConnection('ghom');
    $pdo->exec("SET NAMES 'utf8mb4'");

    // --- ALL DATABASE QUERIES ---

    // Query 1: For the main dashboard
    $all_inspections_raw = $pdo->query("
        WITH LatestInspections AS (
            SELECT i.*, ROW_NUMBER() OVER(PARTITION BY i.element_id, i.part_name ORDER BY i.created_at DESC, i.inspection_id DESC) as rn
            FROM inspections i
        )
        SELECT 
            li.inspection_id, li.element_id, li.part_name, e.element_type, e.zone_name, e.block, e.contractor,
            li.status as final_db_status,
            li.overall_status, li.contractor_status, li.inspection_date, li.contractor_date,
            u.first_name, u.last_name
        FROM LatestInspections li
        JOIN elements e ON li.element_id = e.element_id
        LEFT JOIN hpc_common.users u ON li.user_id = u.id
        WHERE li.rn = 1
        ORDER BY li.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Query 2: For the Stage Progress chart
    $stage_progress_raw = $pdo->query("
        SELECT
            e.zone_name, e.element_type, ci.stage, id.item_status,
            COUNT(id.id) as status_count
        FROM elements e
        JOIN inspections i ON e.element_id = i.element_id
        JOIN inspection_data id ON i.inspection_id = id.inspection_id
        JOIN checklist_items ci ON id.item_id = ci.item_id
        WHERE e.zone_name IS NOT NULL AND e.element_type IS NOT NULL AND ci.stage IS NOT NULL AND id.item_status IS NOT NULL
        GROUP BY e.zone_name, e.element_type, ci.stage, id.item_status
        ORDER BY e.zone_name, e.element_type, ci.item_order
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Query 3: For the Flexible Report (by Block and Contractor)
    $flexible_report_raw = $pdo->query("
        WITH LatestInspections AS (
            SELECT 
                i.element_id, i.part_name, i.status,
                ROW_NUMBER() OVER(PARTITION BY i.element_id, i.part_name ORDER BY i.created_at DESC, i.inspection_id DESC) as rn
            FROM inspections i
        )
        SELECT
            e.block, e.contractor, e.element_type, li.status as final_db_status,
            COUNT(*) as inspection_count
        FROM LatestInspections li
        JOIN elements e ON li.element_id = e.element_id
        WHERE li.rn = 1 
        AND e.block IS NOT NULL AND e.block != '' 
        AND e.contractor IS NOT NULL AND e.contractor != ''
        GROUP BY e.block, e.contractor, e.element_type, li.status
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Query 4: For Coverage Charts
    $total_elements_by_zone = $pdo->query("SELECT zone_name, COUNT(element_id) as total_count FROM elements WHERE zone_name IS NOT NULL AND zone_name != '' GROUP BY zone_name")->fetchAll(PDO::FETCH_KEY_PAIR);
    $inspected_elements_by_zone = $pdo->query("SELECT e.zone_name, COUNT(DISTINCT e.element_id) as inspected_count FROM elements e JOIN inspections i ON e.element_id = i.element_id WHERE e.zone_name IS NOT NULL AND e.zone_name != '' GROUP BY e.zone_name")->fetchAll(PDO::FETCH_KEY_PAIR);
    $total_elements_by_block = $pdo->query("SELECT block, COUNT(element_id) as total_count FROM elements WHERE block IS NOT NULL AND block != '' GROUP BY block")->fetchAll(PDO::FETCH_KEY_PAIR);
    $inspected_elements_by_block = $pdo->query("SELECT e.block, COUNT(DISTINCT e.element_id) as inspected_count FROM elements e JOIN inspections i ON e.element_id = i.element_id WHERE e.block IS NOT NULL AND e.block != '' GROUP BY e.block")->fetchAll(PDO::FETCH_KEY_PAIR);
    $total_elements_overall = (int)$pdo->query("SELECT COUNT(element_id) FROM elements")->fetchColumn();
    $inspected_elements_overall = (int)$pdo->query("SELECT COUNT(DISTINCT element_id) FROM inspections")->fetchColumn();

    // Query 5: For Performance Charts
    $performance_data_raw = [];
    if ($has_full_access) {
        $performance_data_raw = $pdo->query("
            SELECT 
                DATE(i.inspection_date) as inspection_day,
                e.contractor, 
                CONCAT(u.first_name, ' ', u.last_name) as inspector_name,
                COUNT(i.inspection_id) as inspection_count
            FROM inspections i
            JOIN elements e ON i.element_id = e.element_id
            LEFT JOIN hpc_common.users u ON i.user_id = u.id
            WHERE i.inspection_date IS NOT NULL 
                AND i.stage_id > 0 
                AND u.first_name IS NOT NULL
                AND e.contractor IS NOT NULL AND e.contractor != ''
            GROUP BY inspection_day, e.contractor, inspector_name
            ORDER BY inspection_day
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Query 6: For Trend Chart
    $all_history_for_trends = $pdo->query("
        SELECT i.status, i.created_at 
        FROM inspections i WHERE i.created_at IS NOT NULL AND i.status IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);


    // --- ALL DATA PROCESSING LOGIC ---

    // 1. Process main dashboard data
    $status_map = [
        'Pending' => 'در انتظار', 'Pre-Inspection Complete' => 'آماده بازرسی اولیه',
        'Awaiting Re-inspection' => 'منتظر بازرسی مجدد', 'Repair' => 'نیاز به تعمیر',
        'OK' => 'تایید شده', 'Reject' => 'رد شده'
    ];
    $allInspectionsData = array_map(function ($row) use ($status_map) {
        $final_status = $status_map[$row['final_db_status']] ?? $row['final_db_status'];
        $inspector_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $contractor_days_passed = '---';
        if (!empty($row['contractor_date']) && $row['contractor_date'] !== '0000-00-00') {
            try {
                $today = new DateTime(); $contractor_date_obj = new DateTime($row['contractor_date']);
                $interval = $today->diff($contractor_date_obj);
                if ($today > $contractor_date_obj) { $contractor_days_passed = $interval->days . ' روز پیش'; } 
                else if ($interval->days == 0) { $contractor_days_passed = 'موعد امروز'; } 
                else { $contractor_days_passed = $interval->days . ' روز مانده'; }
            } catch (Exception $e) {}
        }
        $inspection_date_formatted = !empty($row['inspection_date']) && strpos($row['inspection_date'], '0000-00-00') === false ? jdate('Y/m/d', strtotime($row['inspection_date'])) : '---';
        return [
            'element_id' => $row['element_id'], 'part_name' => $row['part_name'] ?: '---', 'element_type' => $row['element_type'],
            'zone_name' => $row['zone_name'] ?: 'N/A', 'block' => $row['block'] ?: 'N/A', 'final_status' => $final_status,
            'contractor' => $row['contractor'], 'inspector' => $inspector_name ?: 'نامشخص',
            'inspection_date_raw' => $row['inspection_date'], 'inspection_date' => $inspection_date_formatted,
            'contractor_days_passed' => $contractor_days_passed
        ];
    }, $all_inspections_raw);

    // 2. Process trend data
    $trendData = ['daily' => [], 'weekly' => [], 'monthly' => []];
    foreach ($all_history_for_trends as $row) {
        if (empty($row['created_at']) || empty($row['status'])) continue;
        $timestamp = strtotime($row['created_at']); $status = $row['status'];
        $day = jdate('Y-m-d', $timestamp); $trendData['daily'][$day][$status] = ($trendData['daily'][$day][$status] ?? 0) + 1;
        $week = jdate('o-W', $timestamp); $trendData['weekly'][$week][$status] = ($trendData['weekly'][$week][$status] ?? 0) + 1;
        $month = jdate('Y-m', $timestamp); $trendData['monthly'][$month][$status] = ($trendData['monthly'][$month][$status] ?? 0) + 1;
    }
    foreach ($trendData as $view => &$data) { ksort($data); } unset($data);

    // 3. Process Stage Progress data
    $stageProgressData = [];
    foreach ($stage_progress_raw as $row) {
        $zone = $row['zone_name']; $type = $row['element_type']; $stage = $row['stage'];
        $status = $row['item_status']; $count = $row['status_count'];
        $stageProgressData[$zone][$type][$stage][$status] = $count;
    }

    // 4. Process Flexible Report data
    $flexibleReportData = [];
    foreach ($flexible_report_raw as $row) {
        $final_status_text = $status_map[$row['final_db_status']] ?? $row['final_db_status'];
        $block = $row['block']; $contractor = $row['contractor'];
        $type = $row['element_type']; $count = $row['inspection_count'];
        $flexibleReportData[$block][$contractor][$type][$final_status_text] = ($flexibleReportData[$block][$contractor][$type][$final_status_text] ?? 0) + $count;
    }

    // 5. Process Coverage Data
    $coverageData = ['by_zone' => [], 'by_block' => [], 'overall' => []];
    foreach ($total_elements_by_zone as $zone => $total) {
        $coverageData['by_zone'][$zone] = ['total' => (int)$total, 'inspected' => (int)($inspected_elements_by_zone[$zone] ?? 0)];
    }
    foreach ($total_elements_by_block as $block => $total) {
        $coverageData['by_block'][$block] = ['total' => (int)$total, 'inspected' => (int)($inspected_elements_by_block[$block] ?? 0)];
    }
    $coverageData['overall'] = ['total' => $total_elements_overall, 'inspected' => $inspected_elements_overall];

    // 6. Process Performance Data
    $performanceData = [];
    if ($has_full_access) {
        $inspector_performance = ['daily' => [], 'weekly' => [], 'monthly' => []];
        $contractor_performance = ['daily' => [], 'weekly' => [], 'monthly' => []];
        foreach ($performance_data_raw as $row) {
            $timestamp = strtotime($row['inspection_day']);
            $day = jdate('Y-m-d', $timestamp); $week = jdate('o-W', $timestamp); $month = jdate('Y-m', $timestamp);
            $count = (int)$row['inspection_count'];
            $inspector = $row['inspector_name']; $contractor = $row['contractor'];
            $inspector_performance['daily'][$day][$inspector] = ($inspector_performance['daily'][$day][$inspector] ?? 0) + $count;
            $inspector_performance['weekly'][$week][$inspector] = ($inspector_performance['weekly'][$week][$inspector] ?? 0) + $count;
            $inspector_performance['monthly'][$month][$inspector] = ($inspector_performance['monthly'][$month][$inspector] ?? 0) + $count;
            $contractor_performance['daily'][$day][$contractor] = ($contractor_performance['daily'][$day][$contractor] ?? 0) + $count;
            $contractor_performance['weekly'][$week][$contractor] = ($contractor_performance['weekly'][$week][$contractor] ?? 0) + $count;
            $contractor_performance['monthly'][$month][$contractor] = ($contractor_performance['monthly'][$month][$contractor] ?? 0) + $count;
        }
        $performanceData = ['inspectors' => $inspector_performance, 'contractors' => $contractor_performance];
    }
    
    // --- FINAL OUTPUT ---
    header('Content-Type: application/json; charset=utf-8');
    $output = [
        'allInspectionsData' => $allInspectionsData,
        'trendData'          => $trendData,
        'stageProgressData'  => $stageProgressData,
        'flexibleReportData' => $flexibleReportData,
        'coverageData'       => $coverageData,
        'performanceData'    => $performanceData
    ];
    echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    error_log("API Error in get_chart_data.php: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to retrieve data from server.']);
    exit;
}