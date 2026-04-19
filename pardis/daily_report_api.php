<?php
// public_html/pardis/daily_report_api.php
error_reporting(0);

require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$expected_project_key = 'pardis';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    echo json_encode(['error' => 'Invalid project context']);
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'user';
$action = $_GET['action'] ?? '';
function getMissedReportDates($pdo, $user_id) {
    try {
        // Load holidays once
        $holidays = [];
        $holidays_path = __DIR__ . '/assets/js/holidays.json';
        if (file_exists($holidays_path)) {
            $json_content = file_get_contents($holidays_path);
            $holidays_data = json_decode($json_content, true);
            if (is_array($holidays_data)) {
                $holidays = array_column($holidays_data, 'gregorian_date_str');
            }
        }

        $missed_dates = [];
        // Check the last 7 days, starting from yesterday
        for ($i = 1; $i <= 7; $i++) {
            $date_to_check = date('Y-m-d', strtotime("-$i days"));
            $day_of_week = date('w', strtotime($date_to_check));

            // Skip Fridays (day 5) and holidays
            if ($day_of_week == 5 || in_array($date_to_check, $holidays)) {
                continue;
            }

            // Check if a report exists for this user on this workday
            $stmt = $pdo->prepare(
                "SELECT id FROM daily_reports WHERE user_id = ? AND report_date = ? LIMIT 1"
            );
            $stmt->execute([$user_id, $date_to_check]);
            
            if ($stmt->fetchColumn() === false) {
                // No report found, so it's a missed day
                $missed_dates[] = $date_to_check;
            }
        }
        
        // Convert dates to Jalali for the frontend
        $missed_dates_fa = array_map(function($g_date) {
            return gregorian_to_jalali_short($g_date);
        }, $missed_dates);

        return ['success' => true, 'missed_dates' => $missed_dates_fa];

    } catch (Exception $e) {
        logError("Error in getMissedReportDates: " . $e->getMessage());
        return ['success' => false, 'message' => 'API Error on missed dates'];
    }
}

function toGregorian($jalaliDate)
{
    if (empty($jalaliDate) || !is_string($jalaliDate)) {
        return null;
    }

    // Supports both / and - as separators
    $parts = array_map('intval', preg_split('/[-\/]/', trim($jalaliDate)));
    if (count($parts) !== 3 || $parts[0] < 1300) {
        return null;
    }

    if (function_exists('jalali_to_gregorian')) {
        return implode('-', jalali_to_gregorian($parts[0], $parts[1], $parts[2]));
    }

    return null;
}
try {
    $pdo = getProjectDBConnection('pardis');
    
    switch ($action) {
        case 'dashboard':
            echo json_encode(getDashboardData($pdo, $user_id, $user_role));
            break;
            
        case 'list':
            $date = $_GET['date'] ?? '';
            $role = $_GET['role'] ?? '';
            echo json_encode(getReportsList($pdo, $user_id, $user_role, $date, $role));
            break;
            case 'pending_tasks':
    echo json_encode(getPendingTasks($pdo, $user_id));
    break;

case 'time_summary':
    $project = $_GET['project'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    echo json_encode(getTimeSummary($pdo, $user_id, $user_role, $project, $startDate, $endDate));
    break;

case 'project_kpis':
    $project = $_GET['project'] ?? '';
    echo json_encode(getProjectKPIs($pdo, $user_role, $project));
    break;
     case 'unfinished_tasks':
        echo json_encode(getUnfinishedTasks($pdo, $user_id, $user_role));
        break;
        
    case 'task_timeline':
        $activity_id = $_GET['activity_id'] ?? 0;
        echo json_encode(getTaskTimeline($pdo, $activity_id));
        break;
        
    case 'assign_task':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            break;
        }
        echo json_encode(assignTaskToUser($pdo, $user_id, $user_role, $_POST));
        break;
        
    case 'assigned_tasks':
        echo json_encode(getAssignedTasks($pdo, $user_id, $user_role));
        break;
    case 'update_task_status':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            break;
        }
        $task_id = $_POST['task_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        $notes = $_POST['notes'] ?? null;
        echo json_encode(updateAssignedTaskStatus($pdo, $user_id, $user_role, $task_id, $status, $notes));
        break;
    case 'get_task_details':
    $task_id = $_GET['task_id'] ?? 0;
    echo json_encode(getTaskDetails($pdo, $task_id, $user_id, $user_role));
    break;
    case 'check_submission_status':
    echo json_encode(checkUserSubmissionStatus($pdo, $user_id));
    break;
    case 'get_previous_day_plan':
    echo json_encode(getPreviousDayPlan($pdo, $user_id));
    break;
 
    case 'get_all_issues':
    header('Content-Type: application/json');
    try {
        // Improved SQL: CONCAT names on the server for cleaner data
        $sql = "
            SELECT 
                ri.id, 
                dr.report_date, 
                CONCAT(u.first_name, ' ', u.last_name) AS reporter_name,
                ri.issue_description, 
                ri.assignee_role, 
                ri.status, 
                ri.due_date 
            FROM report_issues ri 
            JOIN daily_reports dr ON ri.report_id = dr.id 
            JOIN hpc_common.users u ON dr.user_id = u.id 
            ORDER BY dr.report_date DESC, ri.id DESC
        ";
        $stmt = $pdo->query($sql);
        $issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Loop through the results to format the dates server-side
        foreach ($issues as &$issue) { // Use '&' to modify the array directly
            // Convert report_date
            if (!empty($issue['report_date'])) {
                $dateParts = explode('-', $issue['report_date']);
                if (count($dateParts) === 3) {
                    $jalali = gregorian_to_jalali($dateParts[0], $dateParts[1], $dateParts[2]);
                    $issue['report_date_fa'] = sprintf('%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2]);
                } else {
                     $issue['report_date_fa'] = $issue['report_date']; // Fallback
                }
            } else {
                $issue['report_date_fa'] = 'نامشخص';
            }
            
            // Convert due_date
            if (!empty($issue['due_date'])) {
                $datePartsDue = explode('-', $issue['due_date']);
                 if (count($datePartsDue) === 3) {
                    $jalaliDue = gregorian_to_jalali($datePartsDue[0], $datePartsDue[1], $datePartsDue[2]);
                    $issue['due_date_fa'] = sprintf('%04d/%02d/%02d', $jalaliDue[0], $jalaliDue[1], $jalaliDue[2]);
                } else {
                    $issue['due_date_fa'] = $issue['due_date']; // Fallback
                }
            } else {
                 $issue['due_date_fa'] = null;
            }
        }

        echo json_encode(['success' => true, 'issues' => $issues]);

    } catch (Exception $e) {
        logError("API Error in get_all_issues: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
    }
    break;
 
    case 'calendar_data':
        $gregorian_start_str = $_GET['start'] ?? '';
        $gregorian_end_str = $_GET['end'] ?? '';

        if (empty($gregorian_start_str)) {
            echo json_encode([]);
            exit;
        }

        require_once __DIR__ . '/includes/jdf.php';

        // --- FINAL, ROBUST JALALI ALIGNMENT LOGIC ---
        $start_date_obj = new DateTime($gregorian_start_str);
        $end_date_obj = new DateTime($gregorian_end_str);
        $interval = $start_date_obj->diff($end_date_obj);

        // This is the key: A month view always requests more than 25 days of data.
        // This is a reliable way to detect month views without needing a 'viewType' parameter.
        if ($interval->days > 25) { 
            $mid_timestamp = $start_date_obj->getTimestamp() + ($end_date_obj->getTimestamp() - $start_date_obj->getTimestamp()) / 2;
            list($jy, $jm, $jd) = gregorian_to_jalali(date('Y', $mid_timestamp), date('m', $mid_timestamp), date('d', $mid_timestamp));

            $start_of_jalali_month_g = jalali_to_gregorian($jy, $jm, 1);
            $startDate = sprintf('%04d-%02d-%02d', $start_of_jalali_month_g[0], $start_of_jalali_month_g[1], $start_of_jalali_month_g[2]);

            $firstDayTimestamp = jmktime(0, 0, 0, $jm, 1, $jy);
            $isLeap = jdate('L', $firstDayTimestamp) == 1;
            $daysInMonth = ($jm <= 6) ? 31 : (($jm <= 11) ? 30 : ($isLeap ? 30 : 29));
            $end_of_jalali_month_g = jalali_to_gregorian($jy, $jm, $daysInMonth);
            // Use +1 on the end day to create a non-inclusive range for the SQL query (WHERE date < endDate)
            $endDate = date('Y-m-d', strtotime(sprintf('%04d-%02d-%02d', $end_of_jalali_month_g[0], $end_of_jalali_month_g[1], $end_of_jalali_month_g[2]) . ' +1 day'));

        } else {
            // For ALL other views (week, day, etc.), use the exact Gregorian range.
            $startDate = $gregorian_start_str;
            $endDate = $gregorian_end_str;
        }
        // --- END OF ALIGNMENT LOGIC ---

        // The rest of the API remains unchanged and correct.
        $sql = "SELECT r.id, r.report_date, CONCAT(u.first_name, ' ', u.last_name) AS engineer_full_name, ra.task_description
                FROM daily_reports r
                LEFT JOIN report_activities ra ON r.id = ra.report_id
                LEFT JOIN hpc_common.users u ON r.user_id = u.id
                WHERE r.report_date >= ? AND r.report_date < ?";
        $params = [$startDate, $endDate];
        if (!in_array($user_role, ['admin', 'superuser'])) { $sql .= " AND r.user_id = ?"; $params[] = $user_id; }
        $sql .= " ORDER BY r.report_date, u.first_name";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        foreach ($activities as $activity) {
            if (empty($activity['task_description'])) continue;
            $events[] = ['title' => $activity['engineer_full_name'] . ': ' . $activity['task_description'], 'start' => $activity['report_date'], 'allDay' => true, 'url' => "daily_report_view.php?id=" . $activity['id'], 'className' => 'fc-event-primary'];
        }
        echo json_encode($events);
    break;

    case 'calendar_day_list_admin': // For ADMINS list-day view
            if (!in_array($user_role, ['admin', 'superuser'])) { echo json_encode([]); exit; }
            $date = $_GET['date'] ?? date('Y-m-d');
            if (empty($date)) { echo json_encode([]); exit; }

            $sql = "SELECT r.id, r.report_date, r.engineer_name FROM daily_reports r WHERE r.report_date = ? ORDER BY r.created_at";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$date]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $events = [];
            foreach ($reports as $report) {
                 $events[] = [ 'title' => $report['engineer_name'], 'start' => $report['report_date'], 'allDay' => true, 'url' => "daily_report_view.php?id=" . $report['id'], 'className' => 'fc-event-primary' ];
            }
            echo json_encode($events);
        break;

     case 'calendar_plans':
            // Fetches the "next_day_plan" and displays it on the following day for the logged-in user.
            $sql = "SELECT report_date, next_day_plan FROM daily_reports WHERE user_id = ? AND next_day_plan IS NOT NULL AND next_day_plan != '' AND report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $events = [];
            foreach ($plans as $plan) {
                $plan_date = date('Y-m-d', strtotime($plan['report_date'] . ' +1 day'));
                $tasks = array_filter(array_map('trim', preg_split('/\\r\\n|\\r|\\n/', $plan['next_day_plan'])));
                foreach($tasks as $index => $task) {
                    $events[] = [
                        'title' => "برنامه: " . $task,
                        'start' => $plan_date,
                        'allDay' => true,
                    ];
                }
            }
            echo json_encode($events);
            break;

        case 'calendar_missed_days':
            // Finds past workdays where the user did not submit a report.
            $holidays = [];
            $holidays_path = __DIR__ . '/assets/js/holidays.json';
            if (file_exists($holidays_path)) {
                $holidays = json_decode(file_get_contents($holidays_path), true);
                $holidays = array_column($holidays, 'gregorian_date_str');
            }
            
            $stmt = $pdo->prepare("SELECT DISTINCT report_date FROM daily_reports WHERE user_id = ? AND report_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)");
            $stmt->execute([$user_id]);
            $submitted_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $events = [];
            for ($i = 1; $i <= 60; $i++) { // Check the last 60 days
                $date_to_check = date('Y-m-d', strtotime("-$i days"));
                $day_of_week = date('w', strtotime($date_to_check));
                
                // Skip Fridays (day 5) and official holidays
                if ($day_of_week == 5 || in_array($date_to_check, $holidays)) {
                    continue;
                }

                if (!in_array($date_to_check, $submitted_dates)) {
                    $events[] = [
                        'title' => 'گزارش ثبت نشده',
                        'start' => $date_to_check,
                        'allDay' => true,
                        'display' => 'marker',
                        'color' => '#f10a0aff' // Light red background
                    ];
                }
            }
            echo json_encode($events);
            break;

        case 'calendar_unfinished_tasks':
            // Gets the user's unfinished tasks with an estimated completion date.
            $unfinished_tasks = _getConsolidatedUnfinishedTasks($pdo, $user_id, $user_role);
            $events = [];
            foreach ($unfinished_tasks as $task) {
                if (!empty($task['estimated_completion_date'])) {
                     $events[] = [
                        'title' => "ناتمام: " . $task['task_description'],
                        'start' => $task['estimated_completion_date'],
                        'allDay' => true,
                        'url' => '#tasks' // Link to the tasks tab
                    ];
                }
            }
            echo json_encode($events);
            break;
        
        case 'calendar_assigned_tasks':
            // Gets tasks assigned to the user that are not yet completed and have a due date.
            $sql = "SELECT task_description, due_date FROM assigned_tasks WHERE assigned_to_user_id = ? AND status != 'completed' AND due_date IS NOT NULL";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $assigned_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $events = [];
            foreach ($assigned_tasks as $task) {
                $events[] = [
                    'title' => "تخصیصی: " . $task['task_description'],
                    'start' => $task['due_date'],
                    'allDay' => true,
                    'url' => '#tasks' // Link to the tasks tab
                ];
            }
            echo json_encode($events);
            break;
       

        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    logError("API Error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}
function getAllIssues($pdo, $user_id, $user_role) {
    // Admins see all issues. Regular users only see issues they reported.
    $sql = "SELECT i.*, dr.report_date 
            FROM report_issues i
            JOIN daily_reports dr ON i.report_id = dr.id";
    
    if (!in_array($user_role, ['admin', 'superuser', 'cod'])) {
        $sql .= " WHERE i.reporter_id = " . intval($user_id);
    }
    
    $sql .= " ORDER BY i.status ASC, i.created_at DESC";
    
    $stmt = $pdo->query($sql);
    $issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ['success' => true, 'issues' => $issues];
}
function getPreviousDayPlan($pdo, $user_id) {
    try {
        // Find the most recent report from this user that is NOT from today
        $stmt = $pdo->prepare(
            "SELECT next_day_plan 
             FROM daily_reports 
             WHERE user_id = ? AND report_date < CURDATE()
             ORDER BY report_date DESC 
             LIMIT 1"
        );
        $stmt->execute([$user_id]);
        
        $plan_text = $stmt->fetchColumn();

        if (empty($plan_text)) {
            return ['success' => true, 'tasks' => []]; // No plan found
        }

        // Split the text into an array of tasks by line breaks
        $tasks_raw = preg_split('/\\r\\n|\\r|\\n/', $plan_text);

        // Clean up the array: trim whitespace and remove any empty lines
        $tasks_clean = array_filter(array_map('trim', $tasks_raw));

        // Re-index the array to ensure it's a simple JSON array
        $tasks = array_values($tasks_clean);

        return ['success' => true, 'tasks' => $tasks];

    } catch (Exception $e) {
        logError("Error in getPreviousDayPlan: " . $e->getMessage());
        return ['success' => false, 'message' => 'API Error'];
    }
}
function checkUserSubmissionStatus($pdo, $user_id) {
    try {
        $today_gregorian = date('Y-m-d');
        $day_of_week = date('w'); // 0 (Sun) to 6 (Sat). Friday is 5.

        // 1. Check if today is Friday
        if ($day_of_week == 5) {
            return ['status' => 'not_applicable', 'reason' => 'Friday'];
        }

        // 2. Check if today is a holiday from the JSON file
        $holidays_path = __DIR__ . '/assets/js/holidays.json';
        if (file_exists($holidays_path)) {
            $json_content = file_get_contents($holidays_path);
            $holidays_data = json_decode($json_content, true);
            
            if (is_array($holidays_data)) {
                // Create a simple lookup array of holiday dates
                $holiday_dates = array_column($holidays_data, 'gregorian_date_str');
                
                if (in_array($today_gregorian, $holiday_dates)) {
                    return ['status' => 'not_applicable', 'reason' => 'Holiday'];
                }
            }
        }

        // 3. If it's a working day, check the database for a report
        $stmt = $pdo->prepare(
            "SELECT id FROM daily_reports WHERE user_id = ? AND report_date = ? LIMIT 1"
        );
        $stmt->execute([$user_id, $today_gregorian]);
        
        $report_exists = $stmt->fetchColumn();

        if ($report_exists) {
            // User has already submitted their report
            return ['status' => 'submitted'];
        } else {
            // User has not submitted their report yet
            return ['status' => 'pending'];
        }

    } catch (Exception $e) {
        logError("Error in checkUserSubmissionStatus: " . $e->getMessage());
        // Return 'not_applicable' in case of an error to avoid annoying users
        return ['status' => 'not_applicable', 'reason' => 'API Error'];
    }
}
function getTaskDetails($pdo, $task_id, $user_id, $user_role) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM assigned_tasks 
            WHERE id = ?
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            throw new Exception("کار یافت نشد");
        }
        
        // Check permission
        $is_admin = in_array($user_role, ['admin', 'superuser', 'cod']);
        $is_assignee = ($task['assigned_to_user_id'] == $user_id);
        
        if (!$is_admin && !$is_assignee) {
            throw new Exception("دسترسی غیرمجاز");
        }
        
        return [
            'success' => true,
            'task' => $task
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
/**
 * Get dashboard statistics
 */
function getDashboardData($pdo, $user_id, $user_role)
{
    $data = [
        'total_reports' => 0,
        'my_reports' => 0,
        'avg_progress' => 0,
        'active_issues' => 0,
        'weekly_chart' => [
            'labels' => [],
            'data' => []
        ],
        'role_chart' => [
            'labels' => [],
            'data' => []
        ],
        'engineer_stats' => []
    ];

    // Base WHERE for user permissions
    $where = '1=1';
    $params = [];
    $is_admin = in_array($user_role, ['admin', 'superuser']);
    
    if (!$is_admin) {
        $where .= ' AND dr.user_id = ?';
        $params[] = $user_id;
    }

    // Total Reports (filtered by user if not admin)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM daily_reports dr WHERE $where");
    $stmt->execute($params);
    $data['total_reports'] = $stmt->fetchColumn();

    // My Reports (always user-specific)
    $stmt_my = $pdo->prepare("SELECT COUNT(*) FROM daily_reports WHERE user_id = ?");
    $stmt_my->execute([$user_id]);
    $data['my_reports'] = $stmt_my->fetchColumn();

    // Active Issues (from last 30 days)
    $recent_date = date('Y-m-d', strtotime('-30 days'));
    $issue_where = $where . " AND dr.report_date >= ? AND (dr.issues_blockers IS NOT NULL AND TRIM(dr.issues_blockers) != '')";
    $issue_params = array_merge($params, [$recent_date]);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM daily_reports dr WHERE $issue_where");
    $stmt->execute($issue_params);
    $data['active_issues'] = $stmt->fetchColumn();

    // Average Progress from Activities
    $stmt = $pdo->prepare("
        SELECT AVG(ra.progress_percentage) 
        FROM report_activities ra
        JOIN daily_reports dr ON ra.report_id = dr.id
        WHERE $where
    ");
    $stmt->execute($params);
    $data['avg_progress'] = round($stmt->fetchColumn() ?: 0);

    // Weekly Progress Chart
    $chart_data_map = [];
    for ($i = 6; $i >= 0; $i--) {
        $g_date_str = date('Y-m-d', strtotime("-$i days"));
        $g_date_parts = explode('-', $g_date_str);
        $j_parts = gregorian_to_jalali($g_date_parts[0], $g_date_parts[1], $g_date_parts[2]);
        $j_label = sprintf('%02d/%02d', $j_parts[1], $j_parts[2]);
        
        $data['weekly_chart']['labels'][] = $j_label;
        $chart_data_map[$g_date_str] = 0;
    }

    $week_ago = date('Y-m-d', strtotime('-6 days'));
    $chart_params = array_merge($params, [$week_ago]);
    $chart_where = $where . " AND dr.report_date >= ?";
    
    $stmt = $pdo->prepare("
        SELECT dr.report_date, AVG(ra.progress_percentage) as daily_avg
        FROM daily_reports dr
        JOIN report_activities ra ON dr.id = ra.report_id
        WHERE $chart_where
        GROUP BY dr.report_date
    ");
    $stmt->execute($chart_params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($chart_data_map[$row['report_date']])) {
            $chart_data_map[$row['report_date']] = round($row['daily_avg']);
        }
    }
    $data['weekly_chart']['data'] = array_values($chart_data_map);

    // Role Distribution
    $role_labels_map = [
        'field_engineer' => 'مهندس اجرا',
        'designer' => 'طراح',
        'surveyor' => 'نقشه‌بردار',
        'control_engineer' => 'کنترل پروژه',
        'drawing_specialist' => 'شاپ'
    ];
    $stmt = $pdo->prepare("SELECT dr.role, COUNT(*) as count FROM daily_reports dr WHERE $where GROUP BY dr.role");
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data['role_chart']['labels'][] = $role_labels_map[$row['role']] ?? $row['role'];
        $data['role_chart']['data'][] = $row['count'];
    }

    // Engineer Statistics (last 30 days)
    $stats_date = date('Y-m-d', strtotime('-30 days'));
    $stats_where = $where . " AND dr.report_date >= ?";
    $stats_params = array_merge($params, [$stats_date]);

    // For regular users, show only their own stats
    // For admins, show all engineers
    $stmt = $pdo->prepare("
        SELECT 
            dr.engineer_name,
            dr.user_id,
            COUNT(DISTINCT dr.id) as report_count,
            COUNT(DISTINCT dr.report_date) as days_count,
            AVG(ra.progress_percentage) as avg_progress,
            MAX(dr.report_date) as last_report_date
        FROM daily_reports dr
        LEFT JOIN report_activities ra ON dr.id = ra.report_id
        WHERE $stats_where
        GROUP BY dr.engineer_name, dr.user_id
        ORDER BY report_count DESC
    ");
    $stmt->execute($stats_params);
    $data['engineer_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert dates to Jalali
    foreach ($data['engineer_stats'] as &$stat) {
        $stat['last_report_date_fa'] = gregorian_to_jalali_short($stat['last_report_date']);
        $stat['avg_progress'] = round($stat['avg_progress'] ?? 0);
    }

    return $data;
}

/**
 * Get reports list
 */
/**
 * Get reports list
 */
function getReportsList($pdo, $user_id, $user_role, $date = '', $role = '') {
    $where_clauses = [];
    $params = [];
    
    // Admin, superuser, and 'user' role can see all reports
    // Other roles (supervisor, planner, etc.) only see their own reports
    if (!in_array($user_role, ['admin', 'superuser', 'user'])) {
        $where_clauses[] = 'dr.user_id = ?';
        $params[] = $user_id;
    }
    
    // Date filter
    if (!empty($date)) {
        $gregorian_date = toGregorian($date);
        if ($gregorian_date) {
            $where_clauses[] = 'dr.report_date = ?';
            $params[] = $gregorian_date;
        }
    }
    
    // Role filter
    if (!empty($role)) {
        $where_clauses[] = 'dr.role = ?';
        $params[] = $role;
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            dr.id,
            dr.report_date,
            dr.engineer_name,
            dr.role,
            dr.location,
            dr.work_hours,
            dr.user_id,
            COUNT(DISTINCT ra.id) as activities_count,
            AVG(ra.progress_percentage) as avg_progress,
            dr.created_at
        FROM daily_reports dr
        LEFT JOIN report_activities ra ON dr.id = ra.report_id
        {$where_sql}
        GROUP BY dr.id
        ORDER BY dr.report_date DESC, dr.created_at DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add Persian translations and permission flags
    $roleFa = [
        'field_engineer' => 'مهندس اجرا',
        'designer' => 'طراح',
        'surveyor' => 'نقشه‌بردار',
        'control_engineer' => ' مهندس کنترل پروژه',
        'drawing_specialist' => 'شاپیست'
    ];
    
    foreach ($reports as &$report) {
        $report['role_fa'] = $roleFa[$report['role']] ?? $report['role'];
        $report['date_fa'] = gregorian_to_jalali_short($report['report_date']);
        $report['avg_progress'] = round($report['avg_progress'] ?? 0, 1);
        
        // Permissions:
        // - Admin/superuser can edit and delete all reports
        // - Regular user role cannot edit or delete (view only)
        // - Report owner can edit and delete their own reports (if not 'user' role)
        $is_owner = ($report['user_id'] == $user_id);
        $is_admin = in_array($user_role, ['admin', 'superuser']);
        
        $report['can_edit'] = $is_admin || ($is_owner && $user_role !== 'user');
        $report['can_delete'] = $is_admin || ($is_owner && $user_role !== 'user');
    }
    
    return [
        'reports' => $reports,
        'total' => count($reports)
    ];
}

/**
 * Convert Gregorian date to Jalali (short format)
 */
function gregorian_to_jalali_short($date) {
    if (empty($date)) return '';
    $parts = explode('-', $date);
    if (count($parts) != 3) return $date;
    
    if (function_exists('gregorian_to_jalali')) {
        list($j_y, $j_m, $j_d) = gregorian_to_jalali($parts[0], $parts[1], $parts[2]);
        // Use sprintf to format the date with leading zeros
        return sprintf('%04d/%02d/%02d', $j_y, $j_m, $j_d);
    }
    return $date;
}
function assignTaskToUser($pdo, $admin_id, $admin_role, $data) {
    try {
        // Check admin permission
        if (!in_array($admin_role, ['admin', 'superuser', 'cod'])) {
            throw new Exception("فقط مدیران می‌توانند کار تخصیص دهند");
        }
        
        // Validate required fields
        $required = ['assigned_to_user_id', 'task_description', 'project_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("فیلد ضروری خالی است: $field");
            }
        }
        
        $pdo->beginTransaction();
        
        // Get user info
        $commonPdo = getCommonDBConnection();
        $stmt = $commonPdo->prepare("
            SELECT id, first_name, last_name 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$data['assigned_to_user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception("کاربر یافت نشد");
        }
        
        $engineer_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        
        // Convert dates
        $est_completion = null;
        if (!empty($data['estimated_completion_date'])) {
            $est_completion = toGregorian($data['estimated_completion_date']);
        }
        
        $due_date = null;
        if (!empty($data['due_date'])) {
            $due_date = toGregorian($data['due_date']);
        }
        
        // Insert into assigned_tasks table
        $stmt = $pdo->prepare("
            INSERT INTO assigned_tasks (
                assigned_by_user_id,
                assigned_to_user_id,
                assigned_to_name,
                project_name,
                building_name,
                building_part,
                task_description,
                task_type,
                priority,
                estimated_hours,
                estimated_completion_date,
                due_date,
                notes,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'assigned', NOW())
        ");
        
        $stmt->execute([
            $admin_id,
            $data['assigned_to_user_id'],
            $engineer_name,
            $data['project_name'],
            $data['building_name'] ?? null,
            $data['building_part'] ?? null,
            $data['task_description'],
            $data['task_type'] ?? null,
            $data['priority'] ?? 'medium',
            $data['estimated_hours'] ?? null,
            $est_completion,
            $due_date,
            $data['notes'] ?? null
        ]);
        
        $task_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        return [
            'success' => true,
            'task_id' => $task_id,
            'message' => 'کار با موفقیت تخصیص داده شد'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logError("Assign task error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
function getPendingTasks($pdo, $user_id) {
    try {
        // This function MUST only return tasks for the logged-in user.
        // It's for carrying over THEIR OWN tasks to a new report.

        // Step 1: Get ALL activities ever submitted by THIS specific user.
        $stmt = $pdo->prepare("
            SELECT ra.id, ra.task_description, ra.task_type, ra.progress_percentage,
                   ra.completion_status, ra.hours_spent, ra.estimated_hours,
                   ra.estimated_completion_date, ra.blocked_reason, ra.priority,
                   ra.created_at, dr.report_date, dr.engineer_name, dr.user_id,
                   ra.project_name, ra.building_name, ra.building_part
            FROM report_activities ra
            JOIN daily_reports dr ON ra.report_id = dr.id
            WHERE dr.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $all_user_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Step 2: Use the consolidation logic on this user-specific dataset.
        $completed_task_keys = [];
        $unfinished_candidates = [];

        foreach ($all_user_activities as $activity) {
            $task_key = normalizeTaskDescription($activity['task_description']);
            if (empty($task_key)) continue;

            if ($activity['progress_percentage'] >= 100) {
                $completed_task_keys[$task_key] = true;
            }
        }

        foreach ($all_user_activities as $activity) {
            $task_key = normalizeTaskDescription($activity['task_description']);
            if (empty($task_key) || isset($completed_task_keys[$task_key])) {
                continue;
            }

            if (!isset($unfinished_candidates[$task_key]) || $activity['progress_percentage'] > $unfinished_candidates[$task_key]['progress_percentage']) {
                $unfinished_candidates[$task_key] = $activity;
            }
        }
        
        $tasks = array_values($unfinished_candidates);

        // Step 3: Format the final tasks array for display.
        foreach ($tasks as &$task) {
            $task['report_date_fa'] = gregorian_to_jalali_short($task['report_date']);
            $task['days_overdue'] = 0; 
            if (!empty($task['estimated_completion_date'])) {
                $task['estimated_completion_date_fa'] = gregorian_to_jalali_short($task['estimated_completion_date']);
                $est_completion_ts = strtotime($task['estimated_completion_date']);
                $today_ts = strtotime(date('Y-m-d'));
                if ($est_completion_ts < $today_ts) { 
                    $task['days_overdue'] = floor(($today_ts - $est_completion_ts) / 86400);
                }
            }
        }
        unset($task); // Unset reference
        
        return ['tasks' => $tasks];

    } catch (Exception $e) {
        logError("Error in getPendingTasks: " . $e->getMessage());
        return ['tasks' => []]; // Return empty on error
    }
}
function addDailyReport($pdo, $user_id, $data) {
    try {
        $pdo->beginTransaction();
        
        // Validate required fields
        $required = ['report_date', 'engineer_name', 'role'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Convert Jalali date to Gregorian
        $gregorian_date = toGregorian($data['report_date']);
        if (!$gregorian_date) {
            throw new Exception("Invalid date format");
        }
        
        // Check for duplicate report on same date
        $stmt = $pdo->prepare("
            SELECT id FROM daily_reports 
            WHERE user_id = ? AND report_date = ?
        ");
        $stmt->execute([$user_id, $gregorian_date]);
        if ($stmt->fetch()) {
            throw new Exception("گزارش برای این تاریخ قبلاً ثبت شده است");
        }
        
        // Insert main report
        $stmt = $pdo->prepare("
            INSERT INTO daily_reports (
                user_id, report_date, engineer_name, role, location,
                project_name, building_name, building_part,
                weather, work_hours, safety_incident,
                arrival_time, departure_time,
                issues_blockers, next_day_plan, general_notes,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $gregorian_date,
            $data['engineer_name'],
            $data['role'],
            $data['location'] ?? null,
            $data['project_name'] ?? null,
            $data['building_name'] ?? null,
            $data['building_part'] ?? $data['custom_building_part'] ?? null,
            $data['weather'] ?? 'clear',
            $data['work_hours'] ?? 8,
            $data['safety_incident'] ?? 'no',
            $data['arrival_time'] ?? null,
            $data['departure_time'] ?? null,
            $data['issues_blockers'] ?? null,
            $data['next_day_plan'] ?? null,
            $data['general_notes'] ?? null
        ]);
        
        $report_id = $pdo->lastInsertId();
        
        // Insert activities
        if (!empty($data['activities']) && is_array($data['activities'])) {
            $activity_stmt = $pdo->prepare("
                INSERT INTO report_activities (
                    report_id, task_description, task_type, 
                    progress_percentage, completion_status, 
                    hours_spent, priority, estimated_hours,
                    estimated_completion_date, blocked_reason,
                    is_carryover, parent_activity_id,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            foreach ($data['activities'] as $activity) {
                if (empty($activity['task_description'])) continue;
                
                $est_completion = null;
                if (!empty($activity['estimated_completion'])) {
                    $est_completion = toGregorian($activity['estimated_completion']);
                }
                
                $activity_stmt->execute([
                    $report_id,
                    $activity['task_description'],
                    $activity['type'] ?? null,
                    $activity['progress'] ?? 0,
                    $activity['status'] ?? 'in_progress',
                    $activity['hours'] ?? 0,
                    $activity['priority'] ?? 'medium',
                    $activity['estimated_hours'] ?? null,
                    $est_completion,
                    $activity['blocked_reason'] ?? null,
                    $activity['is_carryover'] ?? 0,
                    $activity['parent_activity_id'] ?? null
                ]);
            }
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'report_id' => $report_id,
            'message' => 'گزارش با موفقیت ثبت شد'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logError("Add report error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
/**
 * Get unfinished tasks for a user
 * Returns tasks that need to be carried over to next reports
 */

function normalizeTaskDescription($description) {
    if (!is_string($description)) {
        return '';
    }
    // 1. Trim whitespace from start and end
    $normalized = trim($description);
    
    // 2. Remove common punctuation characters (English and Persian)
    $normalized = preg_replace('/[.,\/\\-_:؛،]/u', '', $normalized);
    
    // 3. Replace multiple whitespace characters (spaces, tabs, newlines) with a single space
    $normalized = preg_replace('/\s+/u', ' ', $normalized);
    
    return $normalized;
}
/**
 * Get unfinished tasks for a user
 * Returns tasks that are the most recent in their chain and are not yet complete.
 */
function _getConsolidatedUnfinishedTasks($pdo, $user_id, $user_role) {
    $params = [];
    $sql = "SELECT 
                ra.id, ra.task_description, ra.task_type, ra.progress_percentage,
                ra.completion_status, ra.hours_spent, ra.estimated_hours,
                ra.estimated_completion_date, ra.blocked_reason, ra.priority,
                ra.created_at, dr.report_date, dr.engineer_name, dr.user_id,
                ra.project_name, ra.building_name, ra.building_part
            FROM report_activities ra
            JOIN daily_reports dr ON ra.report_id = dr.id";
    
    $where_clauses = [];
    if (!in_array($user_role, ['admin', 'superuser', 'cod'])) {
        $where_clauses[] = 'dr.user_id = ?';
        $params[] = $user_id;
    }
    
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $completed_task_keys = [];
    $unfinished_task_candidates = [];

    foreach ($all_activities as $activity) {
        $task_key = normalizeTaskDescription($activity['task_description']);
        if (empty($task_key)) continue;

        if ($activity['progress_percentage'] >= 100) {
            $completed_task_keys[$task_key] = true;
        }
    }

    foreach ($all_activities as $activity) {
        $task_key = normalizeTaskDescription($activity['task_description']);
        if (empty($task_key)) continue;

        if (isset($completed_task_keys[$task_key])) {
            continue;
        }

        if (isset($unfinished_task_candidates[$task_key])) {
            if ($activity['progress_percentage'] > $unfinished_task_candidates[$task_key]['progress_percentage']) {
                $unfinished_task_candidates[$task_key] = $activity;
            }
        } else {
            $unfinished_task_candidates[$task_key] = $activity;
        }
    }

    return array_values($unfinished_task_candidates);
}
function getUnfinishedTasks($pdo, $user_id, $user_role) {
    try {
        $params = [];
        $sql = "SELECT 
                    ra.id, ra.task_description, ra.task_type, ra.progress_percentage,
                    ra.completion_status, ra.hours_spent, ra.estimated_hours,
                    ra.estimated_completion_date, ra.blocked_reason, ra.priority,
                    ra.created_at, dr.report_date, dr.engineer_name, dr.user_id,
                    ra.project_name, ra.building_name, ra.building_part
                FROM report_activities ra
                JOIN daily_reports dr ON ra.report_id = dr.id";
        
        $where_clauses = [];
        if (!in_array($user_role, ['admin', 'superuser', 'cod'])) {
            $where_clauses[] = 'dr.user_id = ?';
            $params[] = $user_id;
        }
        
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $all_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- START: LOGIC WITH NORMALIZATION ---

        $completed_task_descriptions = [];
        $unfinished_task_candidates = [];

        // First, find all task descriptions that have ever reached 100%
        foreach ($all_activities as $activity) {
            // **USE NORMALIZED KEY**
            $task_desc_key = normalizeTaskDescription($activity['task_description']);
            if (empty($task_desc_key)) continue;

            if ($activity['progress_percentage'] >= 100) {
                $completed_task_descriptions[$task_desc_key] = true;
            }
        }

        // Now, find the highest progress for all other tasks
        foreach ($all_activities as $activity) {
            // **USE NORMALIZED KEY**
            $task_desc_key = normalizeTaskDescription($activity['task_description']);
            if (empty($task_desc_key)) continue;

            // If this task is in the completed list, skip it entirely
            if (isset($completed_task_descriptions[$task_desc_key])) {
                continue;
            }

            // Check if we have a candidate for this task already
            if (isset($unfinished_task_candidates[$task_desc_key])) {
                // We do. Is the current activity's progress HIGHER?
                if ($activity['progress_percentage'] > $unfinished_task_candidates[$task_desc_key]['progress_percentage']) {
                    // Yes, replace the old candidate with this better one.
                    $unfinished_task_candidates[$task_desc_key] = $activity;
                }
            } else {
                // No candidate yet. This is our first one for this task.
                $unfinished_task_candidates[$task_desc_key] = $activity;
            }
        }

        // The final list is the values from our candidates array
        $tasks = _getConsolidatedUnfinishedTasks($pdo, $user_id, $user_role);

        // --- END: LOGIC WITH NORMALIZATION ---
        
        // Final processing (same as before)
        foreach ($tasks as &$task) {
            $task['report_date_fa'] = gregorian_to_jalali_short($task['report_date']);
            $task['created_at_fa'] = gregorian_to_jalali_short(substr($task['created_at'], 0, 10));
            $task['days_overdue'] = 0;
            if (!empty($task['estimated_completion_date'])) {
                $task['estimated_completion_date_fa'] = gregorian_to_jalali_short($task['estimated_completion_date']);
                if (strtotime($task['estimated_completion_date']) < strtotime(date('Y-m-d'))) {
                    $task['days_overdue'] = floor((strtotime(date('Y-m-d')) - strtotime($task['estimated_completion_date'])) / 86400);
                }
            } else {
                $task['estimated_completion_date_fa'] = null;
            }
            $task['remaining_hours'] = isset($task['estimated_hours']) ? max(0, $task['estimated_hours'] - $task['hours_spent']) : null;
            $task['is_overdue'] = ($task['days_overdue'] > 0);
            $task['is_urgent'] = ($task['priority'] === 'urgent' || $task['days_overdue'] > 7);
            $task['is_blocked'] = ($task['completion_status'] === 'blocked');
        }
        
        return [
            'success' => true,
            'tasks' => $tasks,
            'total' => count($tasks),
            'overdue_count' => count(array_filter($tasks, fn($t) => $t['is_overdue'])),
            'blocked_count' => count(array_filter($tasks, fn($t) => $t['is_blocked'])),
            'urgent_count' => count(array_filter($tasks, fn($t) => $t['is_urgent']))
        ];
        
    } catch (Exception $e) {
        logError("Get unfinished tasks error: " . $e->getMessage());
        return ['success' => false, 'message' => 'خطا در بارگذاری کارهای ناتمام'];
    }
}
/**
 * Get time summary for reports
 * Provides detailed time tracking analytics
 */
function getTimeSummary($pdo, $user_id, $user_role, $project = '', $startDate = '', $endDate = '') {
    try {
        $where_clauses = ['1=1'];
        $params = [];
        
        // Permission check - non-admin users see only their data
        if (!in_array($user_role, ['admin', 'superuser'])) {
            $where_clauses[] = 'dr.user_id = ?';
            $params[] = $user_id;
        }
        
        // Project filter
        if (!empty($project)) {
            $where_clauses[] = 'dr.project_name = ?';
            $params[] = $project;
        }
        
        // Date range filter
        if (!empty($startDate)) {
            $gregorian_start = toGregorian($startDate);
            if ($gregorian_start) {
                $where_clauses[] = 'dr.report_date >= ?';
                $params[] = $gregorian_start;
            }
        }
        
        if (!empty($endDate)) {
            $gregorian_end = toGregorian($endDate);
            if ($gregorian_end) {
                $where_clauses[] = 'dr.report_date <= ?';
                $params[] = $gregorian_end;
            }
        }
        
        // Default to last 30 days if no dates specified
        if (empty($startDate) && empty($endDate)) {
            $where_clauses[] = 'dr.report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Total hours summary
        $stmt = $pdo->prepare("
            SELECT 
                SUM(dr.work_hours) as total_work_hours,
                AVG(dr.work_hours) as avg_daily_hours,
                COUNT(DISTINCT dr.report_date) as working_days,
                COUNT(DISTINCT dr.id) as total_reports
            FROM daily_reports dr
            WHERE {$where_sql}
        ");
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Activity time breakdown
        $stmt = $pdo->prepare("
            SELECT 
                ra.task_type,
                SUM(ra.hours_spent) as total_hours,
                COUNT(ra.id) as activity_count,
                AVG(ra.progress_percentage) as avg_progress
            FROM report_activities ra
            JOIN daily_reports dr ON ra.report_id = dr.id
            WHERE {$where_sql}
            GROUP BY ra.task_type
            ORDER BY total_hours DESC
        ");
        $stmt->execute($params);
        $activity_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Time by engineer
        $stmt = $pdo->prepare("
            SELECT 
                dr.engineer_name,
                dr.role,
                SUM(dr.work_hours) as total_hours,
                COUNT(DISTINCT dr.report_date) as days_worked,
                AVG(dr.work_hours) as avg_hours_per_day,
                SUM(ra.hours_spent) as activity_hours
            FROM daily_reports dr
            LEFT JOIN report_activities ra ON dr.id = ra.report_id
            WHERE {$where_sql}
            GROUP BY dr.engineer_name, dr.role
            ORDER BY total_hours DESC
        ");
        $stmt->execute($params);
        $engineer_time = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Daily time trend
        $stmt = $pdo->prepare("
            SELECT 
                dr.report_date,
                SUM(dr.work_hours) as daily_hours,
                COUNT(DISTINCT dr.engineer_name) as engineers_count
            FROM daily_reports dr
            WHERE {$where_sql}
            GROUP BY dr.report_date
            ORDER BY dr.report_date
        ");
        $stmt->execute($params);
        $daily_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert dates to Jalali
        foreach ($daily_trend as &$day) {
            $day['report_date_fa'] = gregorian_to_jalali_short($day['report_date']);
        }
        
        // Time efficiency metrics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN ra.completion_status = 'completed' THEN 1 END) as completed_activities,
                COUNT(CASE WHEN ra.completion_status = 'in_progress' THEN 1 END) as in_progress_activities,
                COUNT(CASE WHEN ra.completion_status = 'blocked' THEN 1 END) as blocked_activities,
                AVG(CASE WHEN ra.hours_spent > 0 THEN ra.progress_percentage / ra.hours_spent END) as efficiency_ratio
            FROM report_activities ra
            JOIN daily_reports dr ON ra.report_id = dr.id
            WHERE {$where_sql}
        ");
        $stmt->execute($params);
        $efficiency = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'summary' => [
                'total_hours' => round($summary['total_work_hours'] ?? 0, 2),
                'avg_daily_hours' => round($summary['avg_daily_hours'] ?? 0, 2),
                'working_days' => $summary['working_days'] ?? 0,
                'total_reports' => $summary['total_reports'] ?? 0
            ],
            'activity_breakdown' => $activity_breakdown,
            'engineer_time' => $engineer_time,
            'daily_trend' => $daily_trend,
            'efficiency' => [
                'completed' => $efficiency['completed_activities'] ?? 0,
                'in_progress' => $efficiency['in_progress_activities'] ?? 0,
                'blocked' => $efficiency['blocked_activities'] ?? 0,
                'efficiency_ratio' => round($efficiency['efficiency_ratio'] ?? 0, 2)
            ]
        ];
        
    } catch (Exception $e) {
        logError("Time summary error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در بارگذاری خلاصه زمانی'
        ];
    }
}

/**
 * Get Project KPIs (Key Performance Indicators)
 * Provides comprehensive project performance metrics
 */
function getProjectKPIs($pdo, $user_role, $project = '') {
    try {
        // Only admin users can access project KPIs
        if (!in_array($user_role, ['admin', 'superuser', 'cod'])) {
            return [
                'success' => false,
                'message' => 'دسترسی محدود - فقط مدیران'
            ];
        }
        
        $where_clauses = ['1=1'];
        $params = [];
        
        if (!empty($project)) {
            $where_clauses[] = 'dr.project_name = ?';
            $params[] = $project;
        }
        
        // Last 30 days data
        $where_clauses[] = 'dr.report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
        $where_sql = implode(' AND ', $where_clauses);
        
        // Overall project health metrics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT dr.id) as total_reports,
                COUNT(DISTINCT dr.engineer_name) as active_engineers,
                COUNT(DISTINCT dr.report_date) as reporting_days,
                AVG(dr.work_hours) as avg_work_hours,
                COUNT(CASE WHEN dr.safety_incident = 'yes' THEN 1 END) as safety_incidents,
                COUNT(CASE WHEN dr.issues_blockers IS NOT NULL AND TRIM(dr.issues_blockers) != '' THEN 1 END) as reports_with_issues
            FROM daily_reports dr
            WHERE {$where_sql}
        ");
        $stmt->execute($params);
        $health = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Progress metrics by building
        $stmt = $pdo->prepare("
            SELECT 
                dr.building_name,
                COUNT(DISTINCT dr.id) as report_count,
                AVG(ra.progress_percentage) as avg_progress,
                SUM(dr.work_hours) as total_hours,
                COUNT(DISTINCT ra.id) as total_activities,
                COUNT(CASE WHEN ra.completion_status = 'completed' THEN 1 END) as completed_activities
            FROM daily_reports dr
            LEFT JOIN report_activities ra ON dr.id = ra.report_id
            WHERE {$where_sql}
            GROUP BY dr.building_name
            ORDER BY report_count DESC
        ");
        $stmt->execute($params);
        $building_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Activity status distribution
        $stmt = $pdo->prepare("
            SELECT 
                ra.completion_status,
                COUNT(ra.id) as count,
                AVG(ra.progress_percentage) as avg_progress,
                SUM(ra.hours_spent) as total_hours
            FROM report_activities ra
            JOIN daily_reports dr ON ra.report_id = dr.id
            WHERE {$where_sql}
            GROUP BY ra.completion_status
        ");
        $stmt->execute($params);
        $activity_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Role performance
        $stmt = $pdo->prepare("
            SELECT 
                dr.role,
                COUNT(DISTINCT dr.engineer_name) as engineer_count,
                COUNT(DISTINCT dr.id) as report_count,
                AVG(ra.progress_percentage) as avg_progress,
                SUM(dr.work_hours) as total_hours
            FROM daily_reports dr
            LEFT JOIN report_activities ra ON dr.id = ra.report_id
            WHERE {$where_sql}
            GROUP BY dr.role
            ORDER BY report_count DESC
        ");
        $stmt->execute($params);
        $role_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top issues/blockers
        $stmt = $pdo->prepare("
            SELECT 
                dr.issues_blockers,
                dr.report_date,
                dr.engineer_name,
                dr.building_name
            FROM daily_reports dr
            WHERE {$where_sql}
            AND dr.issues_blockers IS NOT NULL 
            AND TRIM(dr.issues_blockers) != ''
            ORDER BY dr.report_date DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        $recent_issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert dates
        foreach ($recent_issues as &$issue) {
            $issue['report_date_fa'] = gregorian_to_jalali_short($issue['report_date']);
        }
        
        // Productivity trends (last 7 days)
        $stmt = $pdo->prepare("
            SELECT 
                dr.report_date,
                COUNT(DISTINCT dr.id) as reports,
                AVG(ra.progress_percentage) as avg_progress,
                SUM(dr.work_hours) as total_hours,
                COUNT(DISTINCT dr.engineer_name) as engineers
            FROM daily_reports dr
            LEFT JOIN report_activities ra ON dr.id = ra.report_id
            WHERE {$where_sql}
            AND dr.report_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY dr.report_date
            ORDER BY dr.report_date
        ");
        $stmt->execute($params);
        $productivity_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($productivity_trend as &$day) {
            $day['report_date_fa'] = gregorian_to_jalali_short($day['report_date']);
            $day['avg_progress'] = round($day['avg_progress'] ?? 0, 1);
        }
        
        // Delayed activities analysis
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN ra.estimated_completion_date < CURDATE() AND ra.completion_status != 'completed' THEN 1 END) as overdue_activities,
                AVG(CASE WHEN ra.estimated_completion_date < CURDATE() AND ra.completion_status != 'completed' 
                    THEN DATEDIFF(CURDATE(), ra.estimated_completion_date) END) as avg_delay_days
            FROM report_activities ra
            JOIN daily_reports dr ON ra.report_id = dr.id
            WHERE {$where_sql}
        ");
        $stmt->execute($params);
        $delay_metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate KPI scores (0-100)
        $total_activities = array_sum(array_column($activity_status, 'count'));
        $completed_count = 0;
        foreach ($activity_status as $status) {
            if ($status['completion_status'] == 'completed') {
                $completed_count = $status['count'];
                break;
            }
        }
        
        $completion_rate = $total_activities > 0 ? ($completed_count / $total_activities) * 100 : 0;
        $safety_score = $health['total_reports'] > 0 
            ? ((($health['total_reports'] - $health['safety_incidents']) / $health['total_reports']) * 100) 
            : 100;
        $reporting_compliance = $health['reporting_days'] > 0 
            ? min(100, ($health['total_reports'] / $health['reporting_days']) * 50) 
            : 0;
        
        return [
            'success' => true,
            'kpis' => [
                'completion_rate' => round($completion_rate, 1),
                'safety_score' => round($safety_score, 1),
                'reporting_compliance' => round($reporting_compliance, 1),
                'avg_progress' => round(array_sum(array_column($building_progress, 'avg_progress')) / max(count($building_progress), 1), 1)
            ],
            'health' => [
                'total_reports' => $health['total_reports'],
                'active_engineers' => $health['active_engineers'],
                'reporting_days' => $health['reporting_days'],
                'avg_work_hours' => round($health['avg_work_hours'] ?? 0, 1),
                'safety_incidents' => $health['safety_incidents'],
                'reports_with_issues' => $health['reports_with_issues']
            ],
            'building_progress' => array_map(function($b) {
                $b['avg_progress'] = round($b['avg_progress'] ?? 0, 1);
                $b['completion_rate'] = $b['total_activities'] > 0 
                    ? round(($b['completed_activities'] / $b['total_activities']) * 100, 1)
                    : 0;
                return $b;
            }, $building_progress),
            'activity_status' => array_map(function($s) {
                $s['avg_progress'] = round($s['avg_progress'] ?? 0, 1);
                return $s;
            }, $activity_status),
            'role_performance' => array_map(function($r) {
                $r['avg_progress'] = round($r['avg_progress'] ?? 0, 1);
                return $r;
            }, $role_performance),
            'recent_issues' => $recent_issues,
            'productivity_trend' => $productivity_trend,
            'delay_metrics' => [
                'overdue_activities' => $delay_metrics['overdue_activities'] ?? 0,
                'avg_delay_days' => round($delay_metrics['avg_delay_days'] ?? 0, 1)
            ]
        ];
        
    } catch (Exception $e) {
        logError("Project KPIs error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در بارگذاری شاخص‌های پروژه'
        ];
    }
}


function getTaskTimeline($pdo, $activity_id) {
    try {
        // Get the main task
        $stmt = $pdo->prepare("
            SELECT 
                ra.*,
                dr.report_date,
                dr.engineer_name,
                dr.project_name,
                dr.building_name
            FROM report_activities ra
            JOIN daily_reports dr ON ra.report_id = dr.id
            WHERE ra.id = ?
        ");
        $stmt->execute([$activity_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            throw new Exception("کار یافت نشد");
        }
        
        // Build timeline - find all related activities (carryovers)
        $timeline = [];
        $current_id = $activity_id;
        
        // Go back to find the original task
        $stmt = $pdo->prepare("
            SELECT 
                ra.id,
                ra.parent_activity_id,
                ra.task_description,
                ra.progress_percentage,
                ra.completion_status,
                ra.hours_spent,
                ra.blocked_reason,
                ra.created_at,
                dr.report_date,
                dr.engineer_name
            FROM report_activities ra
            JOIN daily_reports dr ON ra.report_id = dr.id
            WHERE ra.id = ?
        ");
        
        // Trace back to original
        $visited = [];
        while ($current_id && !in_array($current_id, $visited)) {
            $visited[] = $current_id;
            $stmt->execute([$current_id]);
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($entry) {
                array_unshift($timeline, $entry);
                $current_id = $entry['parent_activity_id'];
            } else {
                break;
            }
        }
        
        // Now find all child activities (future carryovers)
        $stmt = $pdo->prepare("
            SELECT 
                ra.id,
                ra.parent_activity_id,
                ra.task_description,
                ra.progress_percentage,
                ra.completion_status,
                ra.hours_spent,
                ra.blocked_reason,
                ra.created_at,
                dr.report_date,
                dr.engineer_name
            FROM report_activities ra
            JOIN daily_reports dr ON ra.report_id = dr.id
            WHERE ra.parent_activity_id = ?
            ORDER BY dr.report_date
        ");
        
        $to_check = [$activity_id];
        while (!empty($to_check)) {
            $checking_id = array_shift($to_check);
            $stmt->execute([$checking_id]);
            $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($children as $child) {
                if (!in_array($child['id'], $visited)) {
                    $timeline[] = $child;
                    $visited[] = $child['id'];
                    $to_check[] = $child['id'];
                }
            }
        }
        
        // Calculate progress metrics
        $total_hours = 0;
        $progress_changes = [];
        $previous_progress = 0;
        
        foreach ($timeline as &$entry) {
            $entry['report_date_fa'] = gregorian_to_jalali_short($entry['report_date']);
            $entry['created_at_fa'] = gregorian_to_jalali_short(substr($entry['created_at'], 0, 10));
            $total_hours += $entry['hours_spent'] ?? 0;
            
            $progress_change = $entry['progress_percentage'] - $previous_progress;
            $entry['progress_change'] = $progress_change;
            $previous_progress = $entry['progress_percentage'];
        }
        
        return [
            'success' => true,
            'task' => $task,
            'timeline' => $timeline,
            'metrics' => [
                'total_entries' => count($timeline),
                'total_hours' => $total_hours,
                'days_span' => count($timeline) > 0 
                    ? (strtotime($timeline[count($timeline)-1]['report_date']) - strtotime($timeline[0]['report_date'])) / 86400
                    : 0,
                'current_progress' => $task['progress_percentage'],
                'is_completed' => $task['completion_status'] === 'completed'
            ]
        ];
        
    } catch (Exception $e) {
        logError("Get task timeline error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در بارگذاری تاریخچه کار'
        ];
    }
}

function getAssignedTasks($pdo, $user_id, $user_role) {
    try {
        $where_clauses = [];
        $params = [];
        
        if (in_array($user_role, ['admin', 'superuser', 'cod'])) {
            // Admin sees all tasks
            $where_clauses[] = '1=1';
        } else {
            // Regular users see only their assigned tasks
            $where_clauses[] = 'at.assigned_to_user_id = ?';
            $params[] = $user_id;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $stmt = $pdo->prepare("
            SELECT 
                at.*,
                u1.first_name as assigned_by_first,
                u1.last_name as assigned_by_last,
                CASE 
                    WHEN at.due_date IS NOT NULL AND at.due_date < CURDATE()
                    THEN DATEDIFF(CURDATE(), at.due_date)
                    ELSE 0
                END as days_overdue,
                ra.id as activity_id,
                ra.progress_percentage,
                ra.completion_status as activity_status
            FROM assigned_tasks at
            LEFT JOIN hpc_common.users u1 ON at.assigned_by_user_id = u1.id
            LEFT JOIN report_activities ra ON at.id = ra.assigned_task_id
            WHERE {$where_sql}
            ORDER BY 
                CASE at.status
                    WHEN 'assigned' THEN 1
                    WHEN 'in_progress' THEN 2
                    WHEN 'completed' THEN 3
                    WHEN 'cancelled' THEN 4
                END,
                at.due_date ASC,
                at.created_at DESC
        ");
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tasks as &$task) {
            $task['assigned_by_name'] = trim(($task['assigned_by_first'] ?? '') . ' ' . ($task['assigned_by_last'] ?? ''));
            $task['created_at_fa'] = gregorian_to_jalali_short(substr($task['created_at'], 0, 10));
            $task['due_date_fa'] = $task['due_date'] 
                ? gregorian_to_jalali_short($task['due_date'])
                : null;
            $task['estimated_completion_date_fa'] = $task['estimated_completion_date']
                ? gregorian_to_jalali_short($task['estimated_completion_date'])
                : null;
            
            $task['is_overdue'] = ($task['days_overdue'] > 0);
            $task['has_started'] = !is_null($task['activity_id']);
        }
        
        return [
            'success' => true,
            'tasks' => $tasks,
            'total' => count($tasks),
            'pending_count' => count(array_filter($tasks, fn($t) => $t['status'] === 'assigned')),
            'overdue_count' => count(array_filter($tasks, fn($t) => $t['is_overdue']))
        ];
        
    } catch (Exception $e) {
        logError("Get assigned tasks error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در بارگذاری کارهای تخصیص یافته'
        ];
    }
}

function updateAssignedTaskStatus($pdo, $user_id, $user_role, $task_id, $status, $notes = null) {
    try {
        $stmt = $pdo->prepare("
            SELECT assigned_to_user_id, assigned_by_user_id 
            FROM assigned_tasks 
            WHERE id = ?
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            throw new Exception("کار یافت نشد");
        }
        
        // Check permission
        $is_admin = in_array($user_role, ['admin', 'superuser', 'cod']);
        $is_assignee = ($task['assigned_to_user_id'] == $user_id);
        $is_assigner = ($task['assigned_by_user_id'] == $user_id);
        
        if (!$is_admin && !$is_assignee && !$is_assigner) {
            throw new Exception("شما مجاز به تغییر وضعیت این کار نیستید");
        }
        
        $stmt = $pdo->prepare("
            UPDATE assigned_tasks 
            SET status = ?, 
                status_notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $notes, $task_id]);
        
        return [
            'success' => true,
            'message' => 'وضعیت کار به‌روز شد'
        ];
        
    } catch (Exception $e) {
        logError("Update task status error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}


?>