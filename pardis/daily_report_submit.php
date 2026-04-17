<?php
// public_html/pardis/daily_report_submit.php

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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: daily_reports.php');
    exit();
}
function toGregorian($jalaliDate)
{
    if (empty($jalaliDate) || !is_string($jalaliDate)) {
        return null;
    }

    $parts = array_map('intval', explode('/', trim($jalaliDate)));
    if (count($parts) !== 3 || $parts[0] < 1300) {
        return null;
    }

    if (function_exists('jalali_to_gregorian')) {
        return implode('-', jalali_to_gregorian($parts[0], $parts[1], $parts[2]));
    }

    return null;
}

$user_id = $_SESSION['user_id'] ?? 0;
$project_key = $_SESSION['current_project_config_key'] ?? 'pardis';

try {
    // Start transaction
    $pdo = getProjectDBConnection('pardis');
    
    // Validate and sanitize inputs
      $report_date = toGregorian($_POST['report_date'] ?? '') ?? date('Y-m-d');
    $engineer_name = trim($_POST['engineer_name'] ?? '');
    $role = $_POST['role'] ?? '';
  $arrival_time = !empty($_POST['arrival_time']) ? $_POST['arrival_time'] : null;
    $departure_time = !empty($_POST['departure_time']) ? $_POST['departure_time'] : null;
    $had_lunch_break = isset($_POST['had_lunch_break']) ? 1 : 0;
    $work_hours = 0;

    if ($arrival_time && $departure_time) {
        $start = strtotime($arrival_time);
        $end = strtotime($departure_time);
        if ($end < $start) { $end += 86400; }
        $diff_seconds = $end - $start;
        $work_hours = $diff_seconds / 3600;
        if ($had_lunch_break) {
            $work_hours -= 1;
        }
        $work_hours = max(0, round($work_hours, 2));
    }

    if (empty($engineer_name) || empty($role)) {
        throw new Exception('نام مهندس و نقش الزامی است');
    }

    // Get project/location details from the FIRST activity for the main report entry
      $first_activity = $_POST['activities'][array_key_first($_POST['activities'])] ?? null;
    $project_name = trim($first_activity['project_name'] ?? '');
    $building_name = trim($first_activity['building_name'] ?? '');
   
    // BUG FIX: You were using `$activity` here, which is not defined yet. It should be `$first_activity`.
    $building_part = !empty($first_activity['custom_building_part']) 
                     ? trim($first_activity['custom_building_part']) 
                     : trim($first_activity['building_part'] ?? '');

    // Insert main report
   $stmt = $pdo->prepare("
        INSERT INTO daily_reports 
        (report_date, user_id, engineer_name, role, project_key, project_name, 
         building_name, building_part, weather, work_hours, arrival_time, departure_time, had_lunch_break, safety_incident, 
         issues_blockers, next_day_plan, general_notes, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')
    ");
    
    $stmt->execute([
        $report_date, $user_id, $engineer_name, $role, $project_key, 
        $project_name, $building_name, $building_part, $_POST['weather'] ?? 'clear', $work_hours, $arrival_time, $departure_time, $had_lunch_break,
        $_POST['safety_incident'] ?? 'no', trim($_POST['issues_blockers'] ?? ''), trim($_POST['next_day_plan'] ?? ''), trim($_POST['general_notes'] ?? '')
    ]);
    
    $report_id = $pdo->lastInsertId();

    if (isset($_POST['issues']) && is_array($_POST['issues'])) {
    $issue_stmt = $pdo->prepare("
        INSERT INTO report_issues 
        (report_id, reporter_id, reporter_name, issue_description, assignee_role, status)
        VALUES (?, ?, ?, ?, ?, 'open')
    ");
    
    foreach ($_POST['issues'] as $issue) {
        if (!empty($issue['description']) && !empty($issue['assignee_role'])) {
            $issue_stmt->execute([
                $report_id,
                $user_id,
                $engineer_name, // The name from the top of the report
                trim($issue['description']),
                trim($issue['assignee_role'])
            ]);
        }
    }
}



 /*  if ($arrival_time || $departure_time) {
        $time_stmt = $pdo->prepare(
            "INSERT INTO time_tracking (report_id, arrival_time, departure_time) VALUES (?, ?, ?)"
        );
        $time_stmt->execute([$report_id, $arrival_time, $departure_time]);
    } */
    // Insert activities with their specific locations
     if (isset($_POST['activities']) && is_array($_POST['activities'])) {
        $activity_stmt = $pdo->prepare("
            INSERT INTO report_activities 
            (report_id, project_name, building_name, building_part, task_description, task_type, progress_percentage, status, hours_spent, priority)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($_POST['activities'] as $activity) {
            if (!empty($activity['description'])) {
                // Handle custom part for each activity
                $activity_building_part = !empty($activity['custom_building_part']) 
                                          ? trim($activity['custom_building_part']) 
                                          : trim($activity['building_part'] ?? '');

                $activity_stmt->execute([
                    $report_id,
                    trim($activity['project_name'] ?? ''),
                    trim($activity['building_name'] ?? ''),
                    $activity_building_part,
                    trim($activity['description']),
                    trim($activity['type'] ?? ''),
                    intval($activity['progress'] ?? 0),
                    $activity['status'] ?? 'in_progress',
                    floatval($activity['hours'] ?? 0),
                    $activity['priority'] ?? 'medium'
                ]);
            }
        }
    }
    
    // Handle file uploads
    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        $upload_dir = __DIR__ . '/uploads/daily_reports/' . date('Y/m/');
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                          'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $stmt = $pdo->prepare("
            INSERT INTO report_attachments 
            (report_id, file_name, file_path, file_type, file_size)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $file_count = count($_FILES['attachments']['name']);
        for ($i = 0; $i < min($file_count, 5); $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                $file_name = $_FILES['attachments']['name'][$i];
                $file_size = $_FILES['attachments']['size'][$i];
                $file_type = $_FILES['attachments']['type'][$i];
                
                // Validate file
                if (!in_array($file_type, $allowed_types)) {
                    continue; // Skip invalid file types
                }
                
                if ($file_size > $max_size) {
                    continue; // Skip files that are too large
                }
                
                // Generate unique filename
                $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_name = uniqid() . '_' . time() . '.' . $extension;
                $file_path = $upload_dir . $unique_name;
                
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $relative_path = 'uploads/daily_reports/' . date('Y/m/') . $unique_name;
                    $stmt->execute([
                        $report_id, $file_name, $relative_path, $file_type, $file_size
                    ]);
                }
            }
        }
    }
    
    // Parse issues if provided
    if (!empty($issues_blockers)) {
        // You can add logic here to parse and categorize issues
        // For now, we'll just store as general note
    }
    
    // Update dashboard metrics
    updateDashboardMetrics($pdo, $project_key, $report_date);
    
    // Commit transaction
    $pdo->commit();
    
    // Log success
    logError("Daily report submitted successfully by user ID: $user_id, Report ID: $report_id");
    
    // Redirect with success message
    header('Location: daily_reports.php?msg=success&report_id=' . $report_id);
    exit();
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    logError("Error submitting daily report: " . $e->getMessage());
    header('Location: daily_reports.php?msg=error&error=' . urlencode($e->getMessage()));
    exit();
}

/**
 * Update dashboard metrics cache
 */
function updateDashboardMetrics($pdo, $project_key, $date) {
    try {
        // Calculate metrics for the day
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT dr.id) as total_reports,
                COUNT(DISTINCT dr.user_id) as active_engineers,
                AVG(ra.progress_percentage) as avg_progress,
                COUNT(DISTINCT ri.id) as total_issues,
                COUNT(DISTINCT CASE WHEN ri.priority IN ('high', 'critical') THEN ri.id END) as critical_issues,
                COUNT(DISTINCT CASE WHEN ra.status = 'completed' THEN ra.id END) as completed_tasks,
                COUNT(DISTINCT CASE WHEN ra.status = 'delayed' THEN ra.id END) as delayed_tasks
            FROM daily_reports dr
            LEFT JOIN report_activities ra ON dr.id = ra.report_id
            LEFT JOIN report_issues ri ON dr.id = ri.report_id
            WHERE dr.project_key = ? AND dr.report_date = ?
        ");
        
        $stmt->execute([$project_key, $date]);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Insert or update metrics
        $stmt = $pdo->prepare("
            INSERT INTO dashboard_metrics 
            (metric_date, project_key, total_reports, active_engineers, avg_progress, 
             total_issues, critical_issues, completed_tasks, delayed_tasks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_reports = VALUES(total_reports),
                active_engineers = VALUES(active_engineers),
                avg_progress = VALUES(avg_progress),
                total_issues = VALUES(total_issues),
                critical_issues = VALUES(critical_issues),
                completed_tasks = VALUES(completed_tasks),
                delayed_tasks = VALUES(delayed_tasks)
        ");
        
        $stmt->execute([
            $date, $project_key,
            $metrics['total_reports'] ?? 0,
            $metrics['active_engineers'] ?? 0,
            $metrics['avg_progress'] ?? 0,
            $metrics['total_issues'] ?? 0,
            $metrics['critical_issues'] ?? 0,
            $metrics['completed_tasks'] ?? 0,
            $metrics['delayed_tasks'] ?? 0
        ]);
        
    } catch (Exception $e) {
        // Don't fail the main transaction if metrics update fails
        logError("Error updating dashboard metrics: " . $e->getMessage());
    }
}
?>