<?php
// public_html/pardis/analytics_api.php

require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['admin', 'superuser', 'coa'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit();
}

$pdo = getProjectDBConnection('pardis');

// Helper to convert Jalali to Gregorian
function toGregorian($jalaliDate) {
    if (empty($jalaliDate)) return null;
    $parts = array_map('intval', preg_split('/[-\/]/', $jalaliDate));
    return (count($parts) === 3 && function_exists('jalali_to_gregorian')) ? implode('-', jalali_to_gregorian($parts[0], $parts[1], $parts[2])) : null;
}

// --- INPUT FILTERS ---
$start_date = toGregorian($_GET['start_date'] ?? '') ?? date('Y-m-d', strtotime('-30 days'));
$end_date = toGregorian($_GET['end_date'] ?? '') ?? date('Y-m-d');
$project_name = $_GET['project_name'] ?? 'all';
$group_by = $_GET['group_by'] ?? 'daily'; // daily, weekly, monthly
$project_name_filter = $_GET['project_name'] ?? 'all';

// --- 1. GET DETAILED TASK LIST ---
$params = [$start_date, $end_date];
$sql = "
    SELECT 
        ra.task_description,
        ra.progress_percentage,
        ra.hours_spent,
        ra.status,
        dr.report_date,
        dr.engineer_name,

    COALESCE(ra.project_name, dr.project_name) as project_name,
    COALESCE(ra.building_name, dr.building_name) as building_name,
    COALESCE(ra.building_part, dr.building_part) as building_part
    FROM report_activities ra
    JOIN daily_reports dr ON ra.report_id = dr.id
    WHERE dr.report_date BETWEEN ? AND ?
    AND ra.status IN ('in_progress', 'completed')
";

if ($project_name !== 'all') {
    $sql .= " AND dr.project_name = ?";
    $params[] = $project_name;
}
$sql .= " ORDER BY dr.report_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($tasks as &$task) { // Use '&' to modify the array directly
    $g_date = explode('-', $task['report_date']);
    if (count($g_date) === 3) {
        $j_date = gregorian_to_jalali($g_date[0], $g_date[1], $g_date[2]);
        // Add a new key 'report_date_fa' with the Jalali date
        $task['report_date_fa'] = sprintf('%04d/%02d/%02d', $j_date[0], $j_date[1], $j_date[2]);
    } else {
        $task['report_date_fa'] = $task['report_date']; // Fallback
    }
}
unset($task); // Important: unset the reference

// --- 2. GET AGGREGATED CHART DATA ---
$date_group_sql = "DATE(dr.report_date)"; // Daily
if ($group_by === 'weekly') {
    $date_group_sql = "DATE_FORMAT(dr.report_date, '%x-%v')"; // ISO 8601 week number
} elseif ($group_by === 'monthly') {
    $date_group_sql = "DATE_FORMAT(dr.report_date, '%Y-%m')"; // Year-Month
}

$chart_params = [$start_date, $end_date];
$chart_sql = "
    SELECT
        $date_group_sql as period,
        MIN(dr.report_date) as period_start_date,
        AVG(ra.progress_percentage) as avg_progress,
        SUM(ra.hours_spent) as total_hours
    FROM report_activities ra
    JOIN daily_reports dr ON ra.report_id = dr.id
    WHERE dr.report_date BETWEEN ? AND ?
";

if ($project_name !== 'all') {
    $chart_sql .= " AND dr.project_name = ?";
    $chart_params[] = $project_name;
}
$chart_sql .= " GROUP BY period ORDER BY period_start_date ASC";

$stmt = $pdo->prepare($chart_sql);
$stmt->execute($chart_params);
$chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($chart_data as &$row) {
    $g_date = explode('-', $row['period_start_date']);
    if (count($g_date) === 3) {
        $j_date = gregorian_to_jalali($g_date[0], $g_date[1], $g_date[2]);
        // Add a new key 'period_start_date_fa' with the Jalali date
        $row['period_start_date_fa'] = sprintf('%04d/%02d/%02d', $j_date[0], $j_date[1], $j_date[2]);
    } else {
        $row['period_start_date_fa'] = $row['period_start_date']; // Fallback
    }
}
$chart_data = array_reverse($chart_data);
unset($row); // Important: unset the reference
// --- COMPILE AND RETURN JSON RESPONSE ---
header('Content-Type: application/json');
echo json_encode([
    'tasks' => $tasks,
    'chartData' => $chart_data
]);
