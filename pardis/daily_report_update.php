<?php
// public_html/pardis/daily_report_update.php

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
$user_role = $_SESSION['role'] ?? 'user';
$report_id = intval($_POST['report_id'] ?? 0);

if (!$report_id) {
    header('Location: daily_reports.php?msg=invalid_id');
    exit();
}

try {
    $pdo = getProjectDBConnection('pardis');
    
    // Verify report exists and check permissions
    $stmt = $pdo->prepare("SELECT user_id FROM daily_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        header('Location: daily_reports.php?msg=not_found');
        exit();
    }
    
    // Check edit permission
    $can_edit = in_array($user_role, ['admin', 'superuser']) || 
                ($report['user_id'] == $user_id && $user_role !== 'user');
    
    if (!$can_edit) {
        header('Location: daily_reports.php?msg=access_denied');
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Validate and sanitize inputs
  $report_date = toGregorian($_POST['report_date'] ?? '') ?? date('Y-m-d');
    $engineer_name = trim($_POST['engineer_name'] ?? '');
    $role = $_POST['role'] ?? '';
    $weather = $_POST['weather'] ?? 'clear';
    $safety_incident = $_POST['safety_incident'] ?? 'no';
    $issues_blockers = trim($_POST['issues_blockers'] ?? '');
    $next_day_plan = trim($_POST['next_day_plan'] ?? '');
    $general_notes = trim($_POST['general_notes'] ?? '');
    
    // 2. DEFINE TIME-RELATED VARIABLES
    $arrival_time = !empty($_POST['arrival_time']) ? $_POST['arrival_time'] : null;
    $departure_time = !empty($_POST['departure_time']) ? $_POST['departure_time'] : null;
    $had_lunch_break = isset($_POST['had_lunch_break']) ? 1 : 0;
    $work_hours = 0;
    
    // 3. PERFORM SERVER-SIDE TIME CALCULATION
    $work_hours = floatval($_POST['work_hours'] ?? 0); // Use the JS calculation as a fallback
     if ($arrival_time && $departure_time) {
        $start = strtotime($arrival_time);
        $end = strtotime($departure_time);
        if ($end < $start) { $end += 86400; }
        $diff_seconds = $end - $start;
        $calculated_hours = $diff_seconds / 3600;
        if ($had_lunch_break) {
            $calculated_hours -= 1;
        }
        $work_hours = max(0, round($calculated_hours, 2));
    }

    // 4. DETERMINE MAIN REPORT LOCATION from the first activity
     $first_activity_id = array_key_first($_POST['activities'] ?? $_POST['new_activities'] ?? []);
    $first_activity = ($_POST['activities'][$first_activity_id] ?? $_POST['new_activities'][$first_activity_id] ?? null);
    $project_name = trim($first_activity['project_name'] ?? '');
    $building_name = trim($first_activity['building_name'] ?? '');
    $building_part = !empty($first_activity['custom_building_part']) 
                     ? trim($first_activity['custom_building_part']) 
                     : trim($first_activity['building_part'] ?? '');
    // Handle location - check if custom location is provided
    $location = '';
    if (!empty($_POST['custom_location'])) {
        $location = trim($_POST['custom_location']);
    } elseif (!empty($_POST['location'])) {
        $location = trim($_POST['location']);
    }
    
    $weather = $_POST['weather'] ?? 'clear';
    $work_hours = floatval($_POST['work_hours'] ?? 8);
    $safety_incident = $_POST['safety_incident'] ?? 'no';
    $issues_blockers = trim($_POST['issues_blockers'] ?? '');
    $next_day_plan = trim($_POST['next_day_plan'] ?? '');
    $general_notes = trim($_POST['general_notes'] ?? '');
    
    // Validate required fields
    if (empty($engineer_name) || empty($role)) {
        throw new Exception('نام مهندس و نقش الزامی است');
    }
    
    // Update main report
    $first_activity_id = array_key_first($_POST['activities'] ?? $_POST['new_activities'] ?? []);
$first_activity = ($_POST['activities'][$first_activity_id] ?? $_POST['new_activities'][$first_activity_id] ?? null);
$project_name = trim($first_activity['project_name'] ?? '');
$building_name = trim($first_activity['building_name'] ?? '');
$building_part = trim($first_activity['building_part'] ?? '');
   $stmt = $pdo->prepare("
    UPDATE daily_reports 
    SET report_date = ?,
    engineer_name = ?,
    role = ?, 
    project_name = ?,
    building_name = ?,
    building_part = ?, 
    weather = ?,
    work_hours = ?,
    had_lunch_break = ?,
    safety_incident = ?,
    issues_blockers = ?, 
    next_day_plan = ?,
    general_notes = ?,
    updated_at = NOW()
    WHERE id = ?
");
$stmt->execute([
    $report_date, $engineer_name, $role,
    $project_name, $building_name, $building_part,
    $weather, $work_hours,$had_lunch_break, $safety_incident, $issues_blockers, 
    $next_day_plan, $general_notes, $report_id
]);
    // Update time tracking
     $arrival_time = $_POST['arrival_time'] ?? null;
    $departure_time = $_POST['departure_time'] ?? null;
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
        $work_hours = max(0, $work_hours);
    }
    
    if (!empty($arrival_time) || !empty($departure_time)) {
        // Check if time tracking record exists
        $stmt = $pdo->prepare("SELECT id FROM time_tracking WHERE report_id = ?");
        $stmt->execute([$report_id]);
        $time_tracking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($time_tracking) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE time_tracking 
                SET arrival_time = ?, departure_time = ?
                WHERE report_id = ?
            ");
            $stmt->execute([$arrival_time, $departure_time, $report_id]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO time_tracking (report_id, arrival_time, departure_time)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$report_id, $arrival_time, $departure_time]);
        }
    }
    
    // Handle deleted activities
    if (isset($_POST['deleted_activities']) && is_array($_POST['deleted_activities'])) {
        $stmt = $pdo->prepare("DELETE FROM report_activities WHERE id = ? AND report_id = ?");
        foreach ($_POST['deleted_activities'] as $activity_id) {
            $stmt->execute([intval($activity_id), $report_id]);
        }
    }
    
    // Update existing activities
   if (isset($_POST['activities']) && is_array($_POST['activities'])) {
    $stmt = $pdo->prepare("
        UPDATE report_activities 
        SET project_name = ?, building_name = ?, building_part = ?, 
            task_description = ?, task_type = ?, progress_percentage = ?, 
            status = ?, hours_spent = ?, priority = ?
        WHERE id = ? AND report_id = ?
    ");
        
        foreach ($_POST['activities'] as $activity_id => $activity) {
            if (!empty($activity['description']) && isset($activity['id'])) {
                $description = trim($activity['description']);
                $type = trim($activity['type'] ?? '');
                $progress = intval($activity['progress'] ?? 0);
                $status = $activity['status'] ?? 'in_progress';
                $hours = floatval($activity['hours'] ?? 0);
                $priority = $activity['priority'] ?? 'medium';
                
                $stmt->execute([
                     trim($activity['project_name'] ?? ''),
            trim($activity['building_name'] ?? ''),
            trim($activity['building_part'] ?? ''),
                    $description, $type, $progress, $status, $hours, $priority,
                    intval($activity['id']), $report_id
                ]);
            }
        }
    }
    
    // Insert new activities
    if (isset($_POST['new_activities']) && is_array($_POST['new_activities'])) {
    $stmt = $pdo->prepare("
        INSERT INTO report_activities 
        (report_id, project_name, building_name, building_part, task_description, task_type, progress_percentage, status, hours_spent, priority)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
            if (!empty($activity['description'])) {
                $description = trim($activity['description']);
                $type = trim($activity['type'] ?? '');
                $progress = intval($activity['progress'] ?? 0);
                $status = $activity['status'] ?? 'in_progress';
                $hours = floatval($activity['hours'] ?? 0);
                $priority = $activity['priority'] ?? 'medium';
                
                $stmt->execute([
                     $report_id,
            trim($activity['project_name'] ?? ''),
            trim($activity['building_name'] ?? ''),
            trim($activity['building_part'] ?? ''),
            $description, $type, $progress, $status, $hours, $priority
                ]);
            }
        }
    
    
    // Handle deleted attachments
    if (isset($_POST['deleted_attachments']) && is_array($_POST['deleted_attachments'])) {
        $stmt = $pdo->prepare("SELECT file_path FROM report_attachments WHERE id = ? AND report_id = ?");
        $deleteStmt = $pdo->prepare("DELETE FROM report_attachments WHERE id = ? AND report_id = ?");
        
        foreach ($_POST['deleted_attachments'] as $attachment_id) {
            $attachment_id = intval($attachment_id);
            
            // Get file path to delete physical file
            $stmt->execute([$attachment_id, $report_id]);
            $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($attachment && !empty($attachment['file_path'])) {
                $file_path = __DIR__ . '/' . $attachment['file_path'];
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            
            // Delete from database
            $deleteStmt->execute([$attachment_id, $report_id]);
        }
    }
    
    // Handle new file uploads
    if (isset($_FILES['new_attachments']) && !empty($_FILES['new_attachments']['name'][0])) {
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
        
        $file_count = count($_FILES['new_attachments']['name']);
        for ($i = 0; $i < min($file_count, 5); $i++) {
            if ($_FILES['new_attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['new_attachments']['tmp_name'][$i];
                $file_name = $_FILES['new_attachments']['name'][$i];
                $file_size = $_FILES['new_attachments']['size'][$i];
                $file_type = $_FILES['new_attachments']['type'][$i];
                
                // Validate file
                if (!in_array($file_type, $allowed_types)) {
                    continue;
                }
                
                if ($file_size > $max_size) {
                    continue;
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
    
    // Update dashboard metrics
    updateDashboardMetrics($pdo, 'pardis', $report_date);
    
    // Commit transaction
    $pdo->commit();
    
    // Log success
    logError("Daily report updated successfully by user ID: $user_id, Report ID: $report_id");
    
    // Redirect with success message
    header('Location: daily_report_view.php?id=' . $report_id . '&msg=updated');
    exit();
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    logError("Error updating daily report: " . $e->getMessage());
    header('Location: daily_report_edit.php?id=' . $report_id . '&msg=error&error=' . urlencode($e->getMessage()));
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