<?php
// public_html/pardis/analytics_building_api.php

require_once __DIR__ . '/../../sercon/bootstrap.php';
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
    return (count($parts) === 3 && function_exists('jalali_to_gregorian')) 
        ? implode('-', jalali_to_gregorian($parts[0], $parts[1], $parts[2])) 
        : null;
}

// Helper to convert Gregorian to Jalali
function toJalali($gregorianDate) {
    if (empty($gregorianDate)) return '';
    $parts = explode('-', $gregorianDate);
    if (count($parts) === 3) {
        $j_date = gregorian_to_jalali($parts[0], $parts[1], $parts[2]);
        return sprintf('%04d/%02d/%02d', $j_date[0], $j_date[1], $j_date[2]);
    }
    return $gregorianDate;
}

// Get filters
$start_date = toGregorian($_GET['start_date'] ?? '') ?? date('Y-m-d', strtotime('-30 days'));
$end_date = toGregorian($_GET['end_date'] ?? '') ?? date('Y-m-d');
$project_filter = $_GET['project_name'] ?? 'all';
$building_filter = $_GET['building_name'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// Base query parameters
$params = [$start_date, $end_date];
$where_clauses = ["dr.report_date BETWEEN ? AND ?"];

if ($project_filter !== 'all') {
    $where_clauses[] = "COALESCE(ra.project_name, dr.project_name) = ?";
    $params[] = $project_filter;
}

if ($building_filter !== 'all') {
    $where_clauses[] = "COALESCE(ra.building_name, dr.building_name) = ?";
    $params[] = $building_filter;
}

if ($status_filter !== 'all') {
    $where_clauses[] = "ra.completion_status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(' AND ', $where_clauses);

// 1. Building Summary Statistics
$building_summary_sql = "
    SELECT 
        COALESCE(ra.project_name, dr.project_name) as project_name,
        COALESCE(ra.building_name, dr.building_name) as building_name,
        COUNT(DISTINCT ra.id) as total_activities,
        COUNT(DISTINCT dr.id) as total_reports,
        COUNT(DISTINCT dr.user_id) as engineers_count,
        SUM(ra.hours_spent) as total_hours,
        AVG(ra.progress_percentage) as avg_progress,
        SUM(CASE WHEN ra.completion_status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN ra.completion_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
        SUM(CASE WHEN ra.completion_status = 'blocked' THEN 1 ELSE 0 END) as blocked_count,
        MIN(dr.report_date) as first_activity_date,
        MAX(dr.report_date) as last_activity_date
    FROM report_activities ra
    JOIN daily_reports dr ON ra.report_id = dr.id
    WHERE $where_sql
    GROUP BY project_name, building_name
    ORDER BY total_hours DESC
";

$stmt = $pdo->prepare($building_summary_sql);
$stmt->execute($params);
$building_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert dates to Jalali
foreach ($building_summary as &$row) {
    $row['first_activity_date_fa'] = toJalali($row['first_activity_date']);
    $row['last_activity_date_fa'] = toJalali($row['last_activity_date']);
    $row['avg_progress'] = round($row['avg_progress'], 1);
    $row['total_hours'] = round($row['total_hours'], 1);
    
    // Calculate days active
    $first = new DateTime($row['first_activity_date']);
    $last = new DateTime($row['last_activity_date']);
    $row['days_active'] = $first->diff($last)->days + 1;
}

// 2. Building Part Details
$part_details_sql = "
    SELECT 
        COALESCE(ra.project_name, dr.project_name) as project_name,
        COALESCE(ra.building_name, dr.building_name) as building_name,
        COALESCE(ra.building_part, dr.building_part) as building_part,
        COUNT(DISTINCT ra.id) as activities_count,
        SUM(ra.hours_spent) as total_hours,
        AVG(ra.progress_percentage) as avg_progress,
        MAX(ra.progress_percentage) as max_progress,
        COUNT(DISTINCT dr.user_id) as engineers_count,
        GROUP_CONCAT(DISTINCT dr.engineer_name SEPARATOR ', ') as engineer_names,
        SUM(CASE WHEN ra.completion_status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN ra.completion_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
        SUM(CASE WHEN ra.completion_status = 'blocked' THEN 1 ELSE 0 END) as blocked_count,
        MAX(dr.report_date) as last_worked_date
    FROM report_activities ra
    JOIN daily_reports dr ON ra.report_id = dr.id
    WHERE $where_sql
    GROUP BY project_name, building_name, building_part
    ORDER BY building_name, avg_progress DESC
";

$stmt = $pdo->prepare($part_details_sql);
$stmt->execute($params);
$part_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert dates and format data
foreach ($part_details as &$row) {
    $row['last_worked_date_fa'] = toJalali($row['last_worked_date']);
    $row['avg_progress'] = round($row['avg_progress'], 1);
    $row['max_progress'] = round($row['max_progress'], 1);
    $row['total_hours'] = round($row['total_hours'], 1);
    
    // Determine completion status
    if ($row['max_progress'] >= 100 || $row['completed_count'] == $row['activities_count']) {
        $row['status'] = 'completed';
        $row['status_label'] = 'تکمیل شده';
    } elseif ($row['blocked_count'] > 0) {
        $row['status'] = 'blocked';
        $row['status_label'] = 'مسدود';
    } elseif ($row['in_progress_count'] > 0) {
        $row['status'] = 'in_progress';
        $row['status_label'] = 'در حال انجام';
    } else {
        $row['status'] = 'not_started';
        $row['status_label'] = 'شروع نشده';
    }
}

// 3. Engineer Performance by Building
$engineer_performance_sql = "
    SELECT 
        dr.engineer_name,
        dr.user_id,
        COALESCE(ra.building_name, dr.building_name) as building_name,
        COUNT(DISTINCT dr.id) as reports_count,
        COUNT(DISTINCT ra.id) as activities_count,
        SUM(ra.hours_spent) as total_hours,
        AVG(ra.progress_percentage) as avg_progress,
        MAX(dr.report_date) as last_report_date
    FROM daily_reports dr
    JOIN report_activities ra ON dr.id = ra.report_id
    WHERE $where_sql
    GROUP BY dr.user_id, dr.engineer_name, building_name
    ORDER BY total_hours DESC
";

$stmt = $pdo->prepare($engineer_performance_sql);
$stmt->execute($params);
$engineer_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($engineer_performance as &$row) {
    $row['last_report_date_fa'] = toJalali($row['last_report_date']);
    $row['avg_progress'] = round($row['avg_progress'], 1);
    $row['total_hours'] = round($row['total_hours'], 1);
}

// 4. Activity Type Distribution by Building
$activity_type_sql = "
    SELECT 
        COALESCE(ra.building_name, dr.building_name) as building_name,
        ra.activity_type,
        COUNT(*) as count,
        SUM(ra.hours_spent) as total_hours,
        AVG(ra.progress_percentage) as avg_progress
    FROM report_activities ra
    JOIN daily_reports dr ON ra.report_id = dr.id
    WHERE $where_sql AND ra.activity_type IS NOT NULL AND ra.activity_type != ''
    GROUP BY building_name, ra.activity_type
    ORDER BY building_name, total_hours DESC
";

$stmt = $pdo->prepare($activity_type_sql);
$stmt->execute($params);
$activity_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($activity_types as &$row) {
    $row['avg_progress'] = round($row['avg_progress'], 1);
    $row['total_hours'] = round($row['total_hours'], 1);
}

// 5. Timeline Data for Charts (by building)
$timeline_sql = "
    SELECT 
        DATE(dr.report_date) as work_date,
        COALESCE(ra.building_name, dr.building_name) as building_name,
        SUM(ra.hours_spent) as daily_hours,
        AVG(ra.progress_percentage) as daily_progress,
        COUNT(DISTINCT dr.user_id) as engineers_count
    FROM report_activities ra
    JOIN daily_reports dr ON ra.report_id = dr.id
    WHERE $where_sql
    GROUP BY work_date, building_name
    ORDER BY work_date ASC
";

$stmt = $pdo->prepare($timeline_sql);
$stmt->execute($params);
$timeline_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($timeline_data as &$row) {
    $row['work_date_fa'] = toJalali($row['work_date']);
    $row['daily_progress'] = round($row['daily_progress'], 1);
    $row['daily_hours'] = round($row['daily_hours'], 1);
}

// 6. Work Details Table - All activities by building/part
$work_details_sql = "
    SELECT 
        dr.report_date,
        COALESCE(ra.project_name, dr.project_name) as project_name,
        COALESCE(ra.building_name, dr.building_name) as building_name,
        COALESCE(ra.building_part, dr.building_part) as building_part,
        ra.task_description,
        ra.activity_type,
        ra.progress_percentage,
        ra.hours_spent,
        ra.completion_status,
        dr.engineer_name,
        dr.id as report_id,
        ra.id as activity_id
    FROM report_activities ra
    JOIN daily_reports dr ON ra.report_id = dr.id
    WHERE $where_sql
    ORDER BY dr.report_date DESC, building_name, building_part
";

$stmt = $pdo->prepare($work_details_sql);
$stmt->execute($params);
$work_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($work_details as &$row) {
    $row['report_date_fa'] = toJalali($row['report_date']);
    $row['progress_percentage'] = round($row['progress_percentage'], 1);
    $row['hours_spent'] = round($row['hours_spent'], 1);
    
    // Status label
    $status_labels = [
        'completed' => 'تکمیل شده',
        'in_progress' => 'در حال انجام',
        'blocked' => 'مسدود',
        'delayed' => 'تاخیر دارد',
        'not_started' => 'شروع نشده'
    ];
    $row['status_label'] = $status_labels[$row['completion_status']] ?? $row['completion_status'];
}

// 7. Building Part Timeline - Progress over time for each part
$part_timeline_sql = "
    SELECT 
        DATE(dr.report_date) as work_date,
        COALESCE(ra.building_name, dr.building_name) as building_name,
        COALESCE(ra.building_part, dr.building_part) as building_part,
        MAX(ra.progress_percentage) as max_progress,
        SUM(ra.hours_spent) as daily_hours,
        COUNT(DISTINCT dr.user_id) as engineers_count,
        GROUP_CONCAT(DISTINCT dr.engineer_name SEPARATOR ', ') as engineer_names
    FROM report_activities ra
    JOIN daily_reports dr ON ra.report_id = dr.id
    WHERE $where_sql
    GROUP BY work_date, building_name, building_part
    ORDER BY work_date ASC, building_name, building_part
";

$stmt = $pdo->prepare($part_timeline_sql);
$stmt->execute($params);
$part_timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($part_timeline as &$row) {
    $row['work_date_fa'] = toJalali($row['work_date']);
    $row['max_progress'] = round($row['max_progress'], 1);
    $row['daily_hours'] = round($row['daily_hours'], 1);
}

// 8. Get unique values for filters
$projects_sql = "SELECT DISTINCT COALESCE(ra.project_name, dr.project_name) as project_name 
                 FROM report_activities ra 
                 JOIN daily_reports dr ON ra.report_id = dr.id 
                 WHERE COALESCE(ra.project_name, dr.project_name) IS NOT NULL 
                 ORDER BY project_name";
$projects = $pdo->query($projects_sql)->fetchAll(PDO::FETCH_COLUMN);

$buildings_sql = "SELECT DISTINCT COALESCE(ra.building_name, dr.building_name) as building_name 
                  FROM report_activities ra 
                  JOIN daily_reports dr ON ra.report_id = dr.id 
                  WHERE COALESCE(ra.building_name, dr.building_name) IS NOT NULL 
                  ORDER BY building_name";
$buildings = $pdo->query($buildings_sql)->fetchAll(PDO::FETCH_COLUMN);

// Return JSON response
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'building_summary' => $building_summary,
    'part_details' => $part_details,
    'engineer_performance' => $engineer_performance,
    'activity_types' => $activity_types,
    'timeline_data' => $timeline_data,
    'work_details' => $work_details,
    'part_timeline' => $part_timeline,
    'filters' => [
        'projects' => $projects,
        'buildings' => $buildings
    ]
], JSON_UNESCAPED_UNICODE);