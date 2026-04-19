<?php
// project_schedule.php

// 1. SETUP & AUTHORIZATION
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

$expected_project_key = 'pardis';
if (($_SESSION['current_project_config_key'] ?? null) !== $expected_project_key) {
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}

$allowed_roles = ['admin', 'superuser', 'planner'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    exit('Access Denied. You do not have permission for this page.');
}

$tableColumns = [
    'task_name' => ['title' => 'نام فعالیت', 'width' => '300px', 'always_show' => true],
    'wbs' => ['title' => 'WBS', 'width' => '80px', 'always_show' => false],
    'baseline_duration' => ['title' => 'مدت (روز)', 'width' => '80px', 'always_show' => false],
    'baseline_start_date' => ['title' => 'شروع', 'width' => '100px', 'always_show' => false],
    'baseline_finish_date' => ['title' => 'پایان', 'width' => '100px', 'always_show' => false],
    'work_front' => ['title' => 'جبهه کاری', 'width' => '120px', 'always_show' => false],
    'role_distribution' => ['title' => 'توزیع نقش', 'width' => '120px', 'always_show' => false],
    'facade_zone' => ['title' => 'منطقه نما', 'width' => '100px', 'always_show' => false],
    'facade_type' => ['title' => 'نوع نما', 'width' => '100px', 'always_show' => false],
    'materials' => ['title' => 'مواد', 'width' => '100px', 'always_show' => false],
    'total_planned_progress' => ['title' => 'پیشرفت برنامه (%)', 'width' => '80px', 'always_show' => false],
    'cost_weight' => ['title' => 'وزن هزینه‌ای', 'width' => '90px', 'always_show' => true],
    'time_weight' => ['title' => 'وزن زمانی', 'width' => '90px', 'always_show' => true],
    'hybrid_weight' => ['title' => 'وزن ترکیبی', 'width' => '90px', 'always_show' => true]
];

// Function to check if a column has any non-empty values
function hasColumnData($tasks, $columnName) {
    foreach ($tasks as $task) {
        if (!empty($task[$columnName]) && $task[$columnName] !== '0.00' && $task[$columnName] !== '0.0000') {
            return true;
        }
    }
    return false;
}
$selected_project_id = $_SESSION['pardis_project_id'] ?? null;
// Determine which columns to actually show
$columnsToShow = [];
if ($selected_project_id && !empty($tasks)) {
    foreach ($tableColumns as $columnKey => $columnConfig) {
        if ($columnConfig['always_show'] || hasColumnData($tasks, $columnKey)) {
            $columnsToShow[$columnKey] = $columnConfig;
        }
    }
} else {
    // Default columns when no data
    $columnsToShow = array_filter($tableColumns, function($config) {
        return $config['always_show'];
    });
}

// Convert to JSON for JavaScript
$columnsToShowJson = json_encode($columnsToShow, JSON_UNESCAPED_UNICODE);

// Function to log activity
function log_activitypardis($action, $details = '') {
    try {
        $common_pdo = getCommonDBConnection();
        $stmt = $common_pdo->prepare(
            "INSERT INTO activity_log (user_id, username, project_id, activity_type, action, details) 
             VALUES (:user_id, :username, :project_id, :activity_type, :action, :details)"
        );
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':username' => $_SESSION['username'],
            ':project_id' => $_SESSION['current_project_id'],
            ':activity_type' => 'Pardis Task Import',
            ':action' => $action,
            ':details' => $details
        ]);
    } catch (Exception $e) {
        logError("Failed to log activity: " . $e->getMessage());
    }
}
function getTableColumns($pdo) {
    try {
        // Get column information from the tasks table
        $stmt = $pdo->query("DESCRIBE tasks");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $tableColumns = [];
        $excludeColumns = ['task_id', 'project_id', 'created_at', 'updated_at', 'parent_task_id', 'outline_level'];
        
        foreach ($columns as $column) {
            $columnName = $column['Field'];
            
            if (in_array($columnName, $excludeColumns)) {
                continue;
            }
            
            // Define column properties based on column name and type
            $columnConfig = [
                'title' => getColumnTitle($columnName),
                'width' => getColumnWidth($columnName, $column['Type']),
                'always_show' => in_array($columnName, ['task_name', 'cost_weight', 'time_weight', 'hybrid_weight']),
                'type' => getColumnType($columnName, $column['Type']),
                'sortable' => true,
                'filterable' => true
            ];
            
            $tableColumns[$columnName] = $columnConfig;
        }
        
        return $tableColumns;
    } catch (Exception $e) {
        logError("Failed to get table columns: " . $e->getMessage());
        return [];
    }
}

function getColumnTitle($columnName) {
    $titles = [
        'task_name' => 'نام فعالیت',
        'wbs' => 'WBS',
        'baseline_duration' => 'مدت (روز)',
        'baseline_start_date' => 'شروع',
        'baseline_finish_date' => 'پایان',
        'work_front' => 'جبهه کاری',
        'role_distribution' => 'توزیع نقش',
        'facade_zone' => 'منطقه نما',
        'facade_type' => 'نوع نما',
        'materials' => 'مواد',
        'total_planned_progress' => 'پیشرفت برنامه (%)',
        'cost_weight' => 'وزن هزینه‌ای',
        'time_weight' => 'وزن زمانی',
        'hybrid_weight' => 'وزن ترکیبی'
    ];
    
    return $titles[$columnName] ?? ucwords(str_replace('_', ' ', $columnName));
}

function getColumnWidth($columnName, $dbType) {
    $widths = [
        'task_name' => '300px',
        'wbs' => '80px',
        'baseline_duration' => '80px',
        'baseline_start_date' => '100px',
        'baseline_finish_date' => '100px',
        'work_front' => '120px',
        'role_distribution' => '120px',
        'facade_zone' => '100px',
        'facade_type' => '100px',
        'materials' => '100px',
        'total_planned_progress' => '80px',
        'cost_weight' => '90px',
        'time_weight' => '90px',
        'hybrid_weight' => '90px'
    ];
    
    if (isset($widths[$columnName])) {
        return $widths[$columnName];
    }
    
    // Auto-detect width based on database type
    if (strpos($dbType, 'varchar') !== false) {
        return '120px';
    } elseif (strpos($dbType, 'text') !== false) {
        return '200px';
    } elseif (strpos($dbType, 'int') !== false || strpos($dbType, 'decimal') !== false) {
        return '80px';
    } elseif (strpos($dbType, 'date') !== false) {
        return '100px';
    }
    
    return '100px';
}

function getColumnType($columnName, $dbType) {
    if (strpos($columnName, 'date') !== false) {
        return 'date';
    } elseif (strpos($columnName, 'weight') !== false || strpos($columnName, 'progress') !== false) {
        return 'number';
    } elseif (strpos($dbType, 'decimal') !== false || strpos($dbType, 'float') !== false) {
        return 'number';
    } elseif (strpos($dbType, 'int') !== false) {
        return 'integer';
    }
    
    return 'text';
}
$pdo = getProjectDBConnection('pardis');
// Replace the existing $tableColumns definition with:
$tableColumns = getTableColumns($pdo);

// Add API endpoint for column preferences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'save_column_preferences':
            $preferences = json_decode($_POST['preferences'], true);
            $_SESSION['column_preferences'] = $preferences;
            echo json_encode(['success' => true]);
            break;
            
        case 'get_column_preferences':
            $preferences = $_SESSION['column_preferences'] ?? [];
            echo json_encode(['preferences' => $preferences]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}
// =================================================================
// 2. DB CONNECTIONS & INITIALIZATION
// =================================================================

// Determine current view based on GET parameter, default to 'projects'
$current_view = $_GET['view'] ?? 'projects';
if (!in_array($current_view, ['projects', 'importer'])) {
    $current_view = 'projects';
}


try {
    $pdo = getProjectDBConnection('pardis');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    logError("Pardis DB connection error: " . $e->getMessage());
    die("A database error occurred.");
}
// ... (تمام کدهای امنیتی و اتصال به دیتابیس مانند فایل‌های قبلی) ...
if (!isLoggedIn()) { header('Location: /login.php'); exit(); }
$allowed_roles = ['admin', 'superuser', 'planner', 'user']; // Add more roles if needed
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    exit('Access Denied.');
}

$pageTitle = "داشبورد برنامه زمانبندی";
$pdo = getProjectDBConnection('pardis');
$projects = $pdo->query("SELECT project_id, project_name FROM projects ORDER BY project_name")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['project_id'])) {
    $_SESSION['pardis_project_id'] = $_GET['project_id'];
}
$selected_project_id = $_SESSION['pardis_project_id'] ?? null;
$tasks_json = '[]'; // Initialize empty JSON

if ($selected_project_id) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE project_id = ?");
    $stmt->execute([$selected_project_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tasks_json = json_encode($tasks, JSON_UNESCAPED_UNICODE);
}


?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد برنامه زمانبندی - بهینه شده</title>
    <?php require_once __DIR__ . '/header.php'; ?>
<style>
      

       @font-face {
        font-family: "Samim";
        src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
          url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
          url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
      }

      * { margin: 0; padding: 0; box-sizing: border-box; }

      html, body {
        height: 100%;
        overflow: hidden; /* Prevent main page scroll */
      }
      
      body {
        font-family: "Samim", "Tahoma", sans-serif;
        background-color: #ffffff; /* Clean white background */
        direction: rtl;
        color: #333;
        display: flex;
        flex-direction: column;
        /* No padding on body, let header and container manage it */
      }
      
      /* --- THE NEW SINGLE-ROW HEADER --- */
      .header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
    border-radius: 0 0 15px 15px;
    margin-bottom: 10px;
}

      .header h1 {
        font-size: 1.8em;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        margin: 0; /* Remove default margin */
      }
      
      .controls {
        display: flex;
        gap: 10px;
        align-items: center;
      }

      /* --- BUTTON STYLE FROM IMAGE --- */
     .btn {
    background: linear-gradient(135deg, #48bb78, #38a169);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    font-family: 'Samim', sans-serif;
    text-decoration: none;
    display: inline-block;
    white-space: nowrap;
    font-size: 13px;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.btn.save-btn {
    background: linear-gradient(135deg, #ed8936, #dd6b20);
}

.btn.secondary {
    background: linear-gradient(135deg, #4299e1, #3182ce);
}



      .btn.nav-link {
        background: #495057;
        border: 1px solid rgba(255,255,255,0.3);
      }
      .btn.nav-link.exit-link { background: #343a40; }
      
      /* --- SUMMARY CARDS IN HEADER --- */
      .summary-cards {
        display: flex; /* Makes cards align horizontally */
        gap: 15px;
      }
      .summary-card {

        color: #343a40;
        padding: 4px 8px; /* Compact padding */
        border-radius: 10px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.2);
        min-width: 150px;
      }
      .summary-card h3 { font-size: 0.5em; opacity: 0.9; margin-bottom: 5px; }
      .summary-card .value { font-size: 1em; font-weight: bold; }
      
      /* --- MAIN CONTENT AREA & TABLE --- */
.container {
    width: 100%;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    max-width: 100% !important;
    background-color: #f4f7fa;
    /* Remove overflow: hidden - this was causing the scroll issue */
}
.main-content {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    min-height: 0; /* CRITICAL for flex scrolling */
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    background: white;
    margin: 10px;
}
      
  .table-container {
    background: white;
    overflow: auto; /* This enables scrolling */
    flex-grow: 1;
    border-radius: 8px;
    /* Add max height to ensure it doesn't exceed viewport */
    max-height: calc(100vh - 200px);
}
      
      table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 1200px;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

th, td {
    padding: 12px 15px;
    text-align: right;
    border-bottom: 1px solid #f0f0f0;
    white-space: nowrap;
    font-size: 13px;
    vertical-align: middle;
}

th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 20;
    border-bottom: 2px solid #5a67d8;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

/* Enhanced Level Row Styling */
.level-1-row {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    font-weight: 700;
    border-left: 4px solid #2196f3;
}

.level-2-row {
    background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
    font-weight: 600;
    border-left: 4px solid #ff9800;
}

.level-3-row {
    background: white;
    border-left: 2px solid #e0e0e0;
}

.level-4-row {
    background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f4 100%);
    border-left: 2px solid #bdbdbd;
    font-size: 12px;
}

/* Enhanced Hover Effects */
.data-row {
    transition: all 0.2s ease;
    cursor: pointer;
}

.data-row:hover {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%) !important;
    transform: translateX(2px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

      
      /* (Dropdown, forms, and other utility styles remain the same) */
      .dropdown-menu { position: relative; display: inline-block; }
      .dropdown-content { display: none; position: absolute; background-color: #ffffff; min-width: 160px; box-shadow: 0 8px 16px rgba(0,0,0,0.1); z-index: 100; border-radius: 5px; overflow: hidden; border: 1px solid #ddd; }
      .dropdown-content button { color: #333; padding: 12px 16px; display: block; width: 100%; text-align: right; background: none; border: none; }
      .dropdown-content button:hover { background-color: #f1f1f1; }
      .show { display:block; }
      .drag-handle { cursor: grab; color: #aaa; font-size: 1.2em; padding: 0 10px; }
      .action-btn {
    padding: 6px 14px;
    font-size: 11px;
    margin: 0 3px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    color: white;
    font-weight: 500;
    transition: all 0.2s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
      .delete-btn { background: #dc3545; }
      .edit-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.edit-btn:hover {
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
}
 .add-sub-btn { background: #28a745; } .copy-btn { background: #ffc107; color: #212529; }
      .hidden { display: none; }
      .indent-1 { padding-right: 40px; font-size: 14px; } .indent-2 { padding-right: 60px;font-size: 12px; } .indent-3 { padding-right: 80px; }
      .form-container {
        background-color: #fef9e7; /* Light yellow from image */
        border: 1px solid #f7dc6f; /* Yellow border */
        border-radius: 12px;
        padding: 2px 3px;
        margin-bottom: 20px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
      }
      
      .form-container.active {
        border-color: #f7dc6f; /* Keep same border on active */
        transform: none;
      }
      
      .form-container h3 {
        color: #333;
        margin-bottom: 30px;
        text-align: center;
        font-size: 2em;
        font-weight: bold;
      }

      .form-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr); /* A fixed 4-column grid */
        gap: 25px; /* Spacing between items */
        margin-bottom: 25px;
      }

      /* Helper classes for spanning columns */
      .grid-col-span-2 { grid-column: span 2; }
      .grid-col-span-4 { grid-column: span 4; }

      .form-group {
        display: flex;
        flex-direction: column;
      }

      .form-group label {
        margin-bottom: 8px;
        font-weight: bold;
        color: #555;
        font-size: 1em;
      }

      .form-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
        
      }
      
      /* Re-style form buttons to match image */
      .form-actions .btn {
        padding: 6px 15px;
        border-radius: 12px;
        font-size: 0.5em;
        color: white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      }
      .form-actions .btn[type="submit"] { background: linear-gradient(45deg, #ff6b6b, #ff8e53); } /* Save */
      .form-actions .btn.secondary { background: linear-gradient(45deg, #4ecdc4, #44a08d); } /* Clear/Close */
input[type="number"] {
    width: 100% !important;
    border: 2px solid #e2e8f0;
    padding: 6px 10px;
    border-radius: 6px;
    font-family: 'Samim', sans-serif;
    font-size: 12px;
    background: white;
    transition: all 0.2s ease;
}

input[type="number"]:focus {
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
    outline: none;
}
select {
    padding: 8px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    font-family: 'Samim', sans-serif;
    font-size: 14px;
    color: #2d3748;
    cursor: pointer;
    transition: all 0.2s ease;
}

select:focus {
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
    outline: none;
}
input[type="number"]:hover {
    border-color: #cbd5e0;
}
      /* Input and Link styles from your previous good versions */
      input[type="text"], select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1em; font-family: "Samim", sans-serif; }
      input:focus, select:focus, textarea:focus { border-color: #f7dc6f; box-shadow: 0 0 5px rgba(247, 220, 111, 0.5); }
      .link-controls { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
      .link-group { display: flex; flex-direction: column; gap: 5px; }
      .link-group label { font-size: 0.9em; color: #666; }
      .link-input { display: flex; gap: 5px; }
      .link-input select { flex: 2; }
      .link-input input { flex: 1; min-width: 60px; }
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-content {
    background: #fff;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    display: flex;
    flex-direction: column;
    max-height: 80vh;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}
.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h2 { margin: 0; font-size: 1.5em; }
.modal-header .close-btn { background: none; border: none; font-size: 2em; cursor: pointer; color: #aaa; }
.modal-body {
    padding: 20px;
    overflow-y: auto;
    flex-grow: 1;
}
.comment {
    border-bottom: 1px solid #f0f0f0;
    padding: 10px 0;
    margin-bottom: 10px;
}
.comment-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 5px; }
.comment-author { font-weight: bold; color: #007bff; }
.comment-date { font-size: 0.8em; color: #888; }
.comment-body { color: #555; line-height: 1.6; }
.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #eee;
}
#commentForm { display: flex; gap: 10px; }
#commentForm textarea { flex-grow: 1; padding: 10px; border-radius: 5px; border: 1px solid #ddd; resize: vertical; min-height: 50px; font-family: "Samim", sans-serif; }
#commentForm .btn { flex-shrink: 0; }
.comment-icon {
    margin-right: 10px;
    cursor: pointer;
    font-size: 0.9em;
    color: #888;
    background: #eee;
    padding: 2px 6px;
    border-radius: 5px;
}
.comment-icon:hover { color: #007bff; background: #e0e0e0; }
/* Find and REPLACE your entire existing @media print block with this one */
.table-container {
    background: white;
    overflow: auto;
    flex-grow: 1;
    border-radius: 8px;
    max-height: calc(100vh - 200px);
    position: relative;
}

/* Enhanced Sticky Headers */
thead {
    position: sticky;
    top: 0;
    z-index: 100;
}

th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 101;
    border-bottom: 2px solid #5a67d8;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Column Filter Styles */
.column-filter {
    margin-top: 5px;
    width: 100%;
    padding: 4px 8px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.9);
    color: #333;
    font-size: 11px;
    font-family: 'Samim', sans-serif;
}

.column-filter:focus {
    outline: none;
    border-color: rgba(255, 255, 255, 0.8);
    background: white;
}

/* Sort Indicator Styles */
.sort-indicator {
    display: inline-block;
    margin-right: 5px;
    font-size: 10px;
    opacity: 0.7;
    transition: all 0.2s ease;
}

.sort-indicator.active {
    opacity: 1;
    color: #ffeb3b;
}

/* Column Drag and Drop */
.column-draggable {
    cursor: move;
    user-select: none;
    position: relative;
}

.column-draggable:hover {
    background: rgba(255, 255, 255, 0.1) !important;
}

.column-dragging {
    opacity: 0.5;
    transform: rotate(2deg);
}

.column-drag-over {
    border-left: 3px solid #ffeb3b !important;
}

/* Header Tools */
.header-tools {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 5px;
    min-height: 20px;
}

.header-title {
    font-weight: 600;
    flex-grow: 1;
    cursor: pointer;
    user-select: none;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 3px;
}

.sort-btn {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.8);
    cursor: pointer;
    padding: 2px 4px;
    border-radius: 2px;
    font-size: 10px;
    transition: all 0.2s ease;
}

.sort-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.sort-btn.active {
    background: rgba(255, 255, 255, 0.3);
    color: #ffeb3b;
}
.level-filter-container {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    display: none;
    animation: slideDown 0.3s ease;
}

.level-filter-header {
    background: linear-gradient(135deg, #48bb78, #38a169);
    color: white;
    padding: 12px 20px;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.level-filter-header h3 {
    margin: 0;
    font-size: 1.1em;
    font-weight: 600;
}

.level-filter-content {
    padding: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
}

.level-filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    transition: all 0.2s ease;
}

.level-filter-group:hover {
    background: #e9ecef;
}

.level-filter-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #48bb78;
}

.level-filter-group label {
    cursor: pointer;
    font-size: 14px;
    color: #495057;
    font-weight: 500;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.level-indicator {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-radius: 4px;
    text-align: center;
    line-height: 20px;
    font-size: 11px;
    font-weight: bold;
    color: white;
    margin-left: 5px;
}

.level-1-indicator { background: linear-gradient(135deg, #2196f3, #1976d2); }
.level-2-indicator { background: linear-gradient(135deg, #ff9800, #f57c00); }
.level-3-indicator { background: linear-gradient(135deg, #4caf50, #388e3c); }
.level-4-indicator { background: linear-gradient(135deg, #9c27b0, #7b1fa2); }

.level-filter-actions {
    display: flex;
    gap: 10px;
    margin-right: auto;
}

.level-stats {
    font-size: 12px;
    color: #666;
    background: #f0f4f8;
    padding: 4px 8px;
    border-radius: 4px;
    margin-right: 10px;
}

/* Row hiding animation */
.row-hidden {
    display: none !important;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.row-filtering {
    opacity: 0.6;
    transition: opacity 0.2s ease;
}
/* Enhanced Print Styles - COMPLETE REPLACEMENT */
@media print {
    @page {
        size: A4 landscape; /* Changed to landscape for better column visibility */
        margin: 1.5cm;
    }

    /* Force print colors */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    /* Reset page structure for print */
    html, body {
        height: auto !important;
        overflow: visible !important;
        background: white !important;
        font-size: 9pt !important;
    }

    /* Show only the table container */
    body > *:not(.container) {
        display: none !important;
    }

    .container {
        display: block !important;
        width: 100% !important;
        height: auto !important;
        overflow: visible !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        box-shadow: none !important;
    }

    .main-content, .table-container {
        display: block !important;
        width: 100% !important;
        height: auto !important;
        overflow: visible !important;
        max-height: none !important;
        position: static !important;
        box-shadow: none !important;
        border: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Hide non-essential elements */
    .header, .column-selector-container, .form-container,
    .action-btn, .drag-handle, .comment-icon, 
    input[type="checkbox"], .collapse-btn, .column-filter,
    .sort-btn, .header-actions {
        display: none !important;
    }

    /* Table formatting for print */
    table {
        width: 100% !important;
        border-collapse: collapse !important;
        page-break-inside: auto !important;
        background: white !important;
    }

    /* Ensure headers repeat on each page */
    thead {
        display: table-header-group !important;
        position: static !important;
    }

    tbody {
        display: table-row-group !important;
    }

    tr {
        page-break-inside: avoid !important;
        page-break-after: auto !important;
    }

    th, td {
        padding: 6px 8px !important;
        border: 1px solid #333 !important;
        font-size: 8pt !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    th {
        background: #f0f0f0 !important;
        color: #000 !important;
        font-weight: bold !important;
        text-align: center !important;
    }

    /* Level-based row styling for print */
    .level-1-row {
        background: #e8f4f8 !important;
        font-weight: bold !important;
    }

    .level-2-row {
        background: #f0f8e8 !important;
        font-weight: 600 !important;
    }

    .level-3-row {
        background: white !important;
    }

    .level-4-row {
        background: #f8f8f8 !important;
        font-size: 7pt !important;
    }

    /* Hide input fields and show their values */
    input[type="number"] {
        border: none !important;
        background: transparent !important;
        font-size: 8pt !important;
        padding: 0 !important;
        width: auto !important;
    }

    /* Adjust column widths for print */
    th:first-child, td:first-child {
        width: 30% !important;
        max-width: 200px !important;
    }

    th:not(:first-child), td:not(:first-child) {
        width: auto !important;
        min-width: 60px !important;
        max-width: 100px !important;
    }
}

/* Loading indicator */
.loading-indicator {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 20px 40px;
    border-radius: 8px;
    z-index: 10000;
    font-family: 'Samim', sans-serif;
}

.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s ease-in-out infinite;
    margin-left: 10px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Add this rule OUTSIDE the @media print block */
.print-only {
    display: none; /* Hide the 'Level' column on the screen */
}
/* Add these CSS styles for the column selector */

.column-selector-container {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.column-selector-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.column-selector-header h3 {
    margin: 0;
    font-size: 1.2em;
    font-weight: 600;
}

.column-selector-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    padding: 20px;
}

.column-group {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
}

.column-group h4 {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 1em;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    padding-bottom: 8px;
}

.column-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    cursor: pointer;
    font-size: 14px;
    color: #495057;
    transition: color 0.2s ease;
}

.column-group label:hover {
    color: #007bff;
}

.column-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #007bff;
}

.column-group input[type="checkbox"]:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.column-group label:has(input:disabled) {
    opacity: 0.6;
    cursor: not-allowed;
}

.column-selector-actions {
    padding: 15px 20px;
    background: #f8f9fa;
    border-radius: 0 0 12px 12px;
    border-top: 1px solid #e9ecef;
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.column-selector-actions .btn {
    padding: 8px 16px;
    font-size: 13px;
}

/* Responsive design for column selector */
@media (max-width: 768px) {
    .column-selector-grid {
        grid-template-columns: 1fr;
        gap: 15px;
        padding: 15px;
    }
    
    .column-selector-header {
        padding: 12px 15px;
    }
    
    .column-selector-header h3 {
        font-size: 1em;
    }
    
    .column-selector-actions {
        padding: 12px 15px;
    }
    
    .column-selector-actions .btn {
        font-size: 12px;
        padding: 6px 12px;
    }
}

        
        /* Optimized table container for virtual scrolling */
        .table-container {
            height: calc(100vh - 200px);
            overflow: auto;
            position: relative;
            background: white;
            border-radius: 8px;
        }
        
        /* Loading states */
        .loading-indicator {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            font-family: 'Samim', sans-serif;
        }
        
        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .loading-spinner {
            width: 30px;
            height: 30px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Optimized row styles */
        .data-row {
            transition: none; /* Remove transitions for better performance */
            height: 35px;
        }
        
        .data-row:nth-child(even) {
            background: #f9f9f9;
        }
        
        /* Performance optimized inputs */
        .weight-input {
            width: 100%;
            border: 1px solid #ddd;
            padding: 4px;
            border-radius: 4px;
            font-size: 12px;
            background: white;
        }
        
        .weight-input:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 3px rgba(0, 123, 255, 0.3);
        }
        
        .weight-input[data-changed="true"] {
            border-color: #ffc107;
            background: #fff3cd;
        }
        
        /* Progress indicator */
        .progress-bar {
            width: 100%;
            height: 4px;
            background: #f0f0f0;
            border-radius: 2px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #007bff, #0056b3);
            transition: width 0.3s ease;
        }
        
        /* Sticky header enhancement */
        .table-header-sticky {
            position: sticky;
            top: 0;
            z-index: 100;
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Virtual scrolling styles */
        .virtual-spacer {
            pointer-events: none;
        }
        
        .virtual-spacer td {
            padding: 0 !important;
            border: none !important;
        }
        
        /* Error message styles */
        .error-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            z-index: 1000;
            font-family: 'Samim', sans-serif;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        /* Pagination controls */
        .pagination-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 10px;
        }
        
        .pagination-controls button {
            padding: 6px 12px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Samim', sans-serif;
        }
        
        .pagination-controls button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-controls button.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .pagination-info {
            font-size: 14px;
            color: #666;
        }
</style>
</head>
<body>
    <!-- Header remains the same -->
    <div class="header">
        <h1>داشبورد برنامه زمانبندی - بهینه شده</h1>
        <div class="controls">
            <form method="GET" action="project_schedule.php">
                <select name="project_id" onchange="this.form.submit()">
                    <option value="">-- انتخاب پروژه --</option>
                    <!-- PHP populated options -->
                </select>
            </form>
            
            <!-- Optimized control buttons -->
            <button class="btn secondary" onclick="OptimizedSchedule.toggleColumnSelector()">⚙️ انتخاب ستون‌ها</button>
            <button class="btn" onclick="OptimizedSchedule.expandAll()">📂 باز کردن همه</button>
            <button class="btn" onclick="OptimizedSchedule.collapseAll()">📁 بستن همه</button>
            <button class="btn save-btn" onclick="OptimizedSchedule.saveWeights()" id="saveButton">💾 ذخیره وزن‌ها</button>
            <button class="btn secondary" onclick="OptimizedSchedule.prepareForPrint()">🖨️ چاپ</button>
        </div>
    </div>

    <!-- Progress indicator for loading -->
    <div class="progress-bar" id="progressBar" style="display: none;">
        <div class="progress-fill" id="progressFill"></div>
    </div>

    <!-- Summary cards -->
    <div class="summary-cards" id="summaryCards" style="display: none; margin: 10px;">
        <div class="summary-card">
            <h3>کل فعالیت‌ها</h3>
            <div class="value" id="totalTasks">0</div>
        </div>
        <div class="summary-card">
            <h3>وزن‌های تکمیل شده</h3>
            <div class="value" id="completedWeights">0</div>
        </div>
        <div class="summary-card">
            <h3>میانگین وزن هزینه‌ای</h3>
            <div class="value" id="avgCostWeight">0.00</div>
        </div>
    </div>

    <!-- Main table container -->
    <div class="container">
        <div class="main-content">
            <div class="table-container" id="tableContainer">
                <table id="projectTable">
                    <thead class="table-header-sticky">
                        <tr id="tableHeader">
                            <!-- Headers will be populated by JavaScript -->
                        </tr>
                    </thead>
                    <tbody id="projectTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination controls -->
    <div class="pagination-controls" id="paginationControls" style="display: none;">
        <button id="firstPageBtn" onclick="OptimizedSchedule.goToPage(1)">اول</button>
        <button id="prevPageBtn" onclick="OptimizedSchedule.previousPage()">قبلی</button>
        <span class="pagination-info" id="paginationInfo">صفحه 1 از 1</span>
        <button id="nextPageBtn" onclick="OptimizedSchedule.nextPage()">بعدی</button>
        <button id="lastPageBtn" onclick="OptimizedSchedule.goToLastPage()">آخر</button>
    </div>

    <script>
        // Global variables for optimized system
        let OptimizedSchedule = {
            data: [],
            filteredData: [],
            currentPage: 1,
            totalPages: 1,
            itemsPerPage: 50,
            totalRecords: 0,
            isLoading: false,
            hasUnsavedChanges: false,
            
            // Initialize the optimized system
            initialize: function(initialData = [], projectSummary = {}) {
                console.log('Initializing optimized schedule...');
                this.showProgress('بارگذاری داده‌ها...', 0);
                
                this.data = initialData;
                this.filteredData = [...initialData];
                this.totalRecords = projectSummary.total_tasks || initialData.length;
                this.totalPages = Math.ceil(this.totalRecords / this.itemsPerPage);
                
                this.updateSummaryCards(projectSummary);
                this.buildOptimizedHeader();
                this.renderTable();
                this.setupEventListeners();
                this.updatePagination();
                this.hideProgress();
                
                console.log(`Loaded ${initialData.length} records successfully`);
            },
            
            // Show loading progress
            showProgress: function(message, percentage = 0) {
                const progressBar = document.getElementById('progressBar');
                const progressFill = document.getElementById('progressFill');
                
                progressBar.style.display = 'block';
                progressFill.style.width = percentage + '%';
                
                if (percentage >= 100) {
                    setTimeout(() => this.hideProgress(), 500);
                }
            },
            
            hideProgress: function() {
                const progressBar = document.getElementById('progressBar');
                progressBar.style.display = 'none';
            },
            
            // Update summary cards
            updateSummaryCards: function(summary) {
                if (Object.keys(summary).length === 0) return;
                
                document.getElementById('totalTasks').textContent = summary.total_tasks || 0;
                document.getElementById('completedWeights').textContent = 
                    (summary.weight_completion?.cost || 0) + '/' + (summary.total_tasks || 0);
                document.getElementById('avgCostWeight').textContent = 
                    (summary.weight_averages?.cost || 0).toFixed(2);
                
                document.getElementById('summaryCards').style.display = 'flex';
            },
            
            // Build optimized table header
            buildOptimizedHeader: function() {
                const headerRow = document.getElementById('tableHeader');
                const visibleColumns = this.getVisibleColumns();
                
                let headerHTML = `
                    <th style="width: 300px; position: sticky; left: 0; background: inherit; z-index: 101;">
                        نام فعالیت
                        <input type="text" class="column-filter" data-column="task_name" placeholder="فیلتر...">
                    </th>
                `;
                
                visibleColumns.forEach(column => {
                    if (column.key !== 'task_name') {
                        headerHTML += `
                            <th style="width: ${column.width};" data-column="${column.key}">
                                <div class="header-tools">
                                    <span class="header-title">${column.title}</span>
                                    <div class="sort-controls">
                                        <button class="sort-btn" data-column="${column.key}" data-direction="asc">▲</button>
                                        <button class="sort-btn" data-column="${column.key}" data-direction="desc">▼</button>
                                    </div>
                                </div>
                                <input type="text" class="column-filter" data-column="${column.key}" placeholder="فیلتر...">
                            </th>
                        `;
                    }
                });
                
                headerHTML += '<th style="width: 120px;">عملیات</th>';
                headerRow.innerHTML = headerHTML;
            },
            
            // Get visible columns configuration
            getVisibleColumns: function() {
                return [
                    { key: 'task_name', title: 'نام فعالیت', width: '300px' },
                    { key: 'wbs', title: 'WBS', width: '80px' },
                    { key: 'baseline_duration', title: 'مدت (روز)', width: '80px' },
                    { key: 'baseline_start_date', title: 'شروع', width: '100px' },
                    { key: 'baseline_finish_date', title: 'پایان', width: '100px' },
                    { key: 'cost_weight', title: 'وزن هزینه‌ای', width: '90px' },
                    { key: 'time_weight', title: 'وزن زمانی', width: '90px' },
                    { key: 'hybrid_weight', title: 'وزن ترکیبی', width: '90px' }
                ];
            },
            
            // Optimized table rendering with virtual scrolling
            renderTable: function() {
                const tbody = document.getElementById('projectTableBody');
                const startIndex = (this.currentPage - 1) * this.itemsPerPage;
                const endIndex = Math.min(startIndex + this.itemsPerPage, this.filteredData.length);
                
                let html = '';
                
                for (let i = startIndex; i < endIndex; i++) {
                    const item = this.filteredData[i];
                    if (item) {
                        html += this.generateRowHTML(item);
                    }
                }
                
                tbody.innerHTML = html;
                this.attachRowEventListeners();
            },
            
            // Generate HTML for a single row
            generateRowHTML: function(item) {
                const level = item.outline_level || 1;
                const indent = (level - 1) * 25;
                
                let html = `
                    <tr class="data-row level-${level}-row" data-task-id="${item.task_id}">
                        <td style="padding-right: ${indent + 15}px;">
                            <span class="level-indicator level-${level}-indicator">${level}</span>
                            <strong>${this.escapeHtml(item.task_name || '')}</strong>
                        </td>
                `;
                
                const visibleColumns = this.getVisibleColumns();
                visibleColumns.forEach(column => {
                    if (column.key !== 'task_name') {
                        html += this.generateCellHTML(item, column.key);
                    }
                });
                
                html += `
                        <td>
                            <button class="action-btn edit-btn" data-task-id="${item.task_id}">ویرایش</button>
                        </td>
                    </tr>
                `;
                
                return html;
            },
            
            // Generate cell HTML based on column type
            generateCellHTML: function(item, columnKey) {
                const value = item[columnKey] || '';
                
                if (['cost_weight', 'time_weight', 'hybrid_weight'].includes(columnKey)) {
                    const step = columnKey === 'cost_weight' ? '0.01' : '0.0001';
                    const defaultValue = columnKey === 'cost_weight' ? '0.00' : '0.0000';
                    return `<td><input type="number" step="${step}" data-task-id="${item.task_id}" data-column="${columnKey}" value="${value || defaultValue}" class="weight-input"></td>`;
                } else if (columnKey.includes('date')) {
                    return `<td>${this.formatDate(value)}</td>`;
                } else {
                    return `<td>${this.escapeHtml(value)}</td>`;
                }
            },
            
            // Setup event listeners for the table
            setupEventListeners: function() {
                // Filter inputs
                document.getElementById('tableHeader').addEventListener('input', (e) => {
                    if (e.target.classList.contains('column-filter')) {
                        this.debounce(this.handleFilter.bind(this), 300)(e.target.dataset.column, e.target.value);
                    }
                });
                
                // Sort buttons
                document.getElementById('tableHeader').addEventListener('click', (e) => {
                    if (e.target.classList.contains('sort-btn')) {
                        this.handleSort(e.target.dataset.column, e.target.dataset.direction);
                    }
                });
                
                // Save detection for unsaved changes
                window.addEventListener('beforeunload', (e) => {
                    if (this.hasUnsavedChanges) {
                        e.preventDefault();
                        e.returnValue = 'تغییرات ذخیره نشده دارید. آیا مطمئن هستید؟';
                    }
                });
            },
            
            // Attach event listeners to table rows
            attachRowEventListeners: function() {
                // Weight input changes
                const weightInputs = document.querySelectorAll('.weight-input');
                weightInputs.forEach(input => {
                    input.addEventListener('change', (e) => {
                        e.target.setAttribute('data-changed', 'true');
                        this.markUnsaved();
                    });
                });
                
                // Edit buttons
                const editButtons = document.querySelectorAll('.action-btn');
                editButtons.forEach(button => {
                    button.addEventListener('click', (e) => {
                        this.editTask(e.target.dataset.taskId);
                    });
                });
            },
            
            // Mark as having unsaved changes
            markUnsaved: function() {
                this.hasUnsavedChanges = true;
                const saveBtn = document.getElementById('saveButton');
                if (saveBtn) {
                    saveBtn.style.background = 'linear-gradient(45deg, #ff4757, #ff6348)';
                    saveBtn.innerHTML = '💾 ذخیره وزن‌ها *';
                }
            },
            
            // Save weights with batch processing
            saveWeights: async function() {
                if (this.isLoading) return;
                
                this.isLoading = true;
                this.showProgress('در حال ذخیره...', 0);
                
                const changedInputs = document.querySelectorAll('.weight-input[data-changed="true"]');
                const payload = {};
                
                changedInputs.forEach(input => {
                    const taskId = input.dataset.taskId;
                    const column = input.dataset.column;
                    
                    if (!payload[taskId]) {
                        payload[taskId] = {};
                    }
                    payload[taskId][column] = input.value;
                });
                
                if (Object.keys(payload).length === 0) {
                    this.showMessage('هیچ تغییری برای ذخیره وجود ندارد');
                    this.hideProgress();
                    this.isLoading = false;
                    return;
                }
                
                try {
                    this.showProgress('در حال ذخیره...', 50);
                    
                    const response = await fetch('project_schedule.php?api=data', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'update_weights_batch', data: payload })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.showProgress('ذخیره موفق', 100);
                        this.showMessage(`${result.updated} مورد با موفقیت ذخیره شد`);
                        
                        // Reset changed markers
                        changedInputs.forEach(input => {
                            input.removeAttribute('data-changed');
                        });
                        
                        this.hasUnsavedChanges = false;
                        const saveBtn = document.getElementById('saveButton');
                        if (saveBtn) {
                            saveBtn.style.background = '';
                            saveBtn.innerHTML = '💾 ذخیره وزن‌ها';
                        }
                    } else {
                        throw new Error(result.message || 'خطا در ذخیره‌سازی');
                    }
                    
                } catch (error) {
                    this.showError('خطا در ذخیره: ' + error.message);
                } finally {
                    this.isLoading = false;
                    setTimeout(() => this.hideProgress(), 1000);
                }
            },
            
            // Pagination methods
            goToPage: function(page) {
                if (page < 1 || page > this.totalPages || page === this.currentPage) return;
                
                this.currentPage = page;
                this.renderTable();
                this.updatePagination();
            },
            
            nextPage: function() {
                this.goToPage(this.currentPage + 1);
            },
            
            previousPage: function() {
                this.goToPage(this.currentPage - 1);
            },
            
            goToLastPage: function() {
                this.goToPage(this.totalPages);
            },
            
            updatePagination: function() {
                const controls = document.getElementById('paginationControls');
                const info = document.getElementById('paginationInfo');
                
                if (this.totalPages > 1) {
                    controls.style.display = 'flex';
                    info.textContent = `صفحه ${this.currentPage} از ${this.totalPages} (${this.totalRecords} رکورد)`;
                    
                    document.getElementById('firstPageBtn').disabled = this.currentPage === 1;
                    document.getElementById('prevPageBtn').disabled = this.currentPage === 1;
                    document.getElementById('nextPageBtn').disabled = this.currentPage === this.totalPages;
                    document.getElementById('lastPageBtn').disabled = this.currentPage === this.totalPages;
                } else {
                    controls.style.display = 'none';
                }
            },
            
            // Utility functions
            escapeHtml: function(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return String(text).replace(/[&<>"']/g, m => map[m]);
            },
            
            formatDate: function(dateStr) {
                if (!dateStr) return '';
                try {
                    return new Date(dateStr).toLocaleDateString('fa-IR');
                } catch {
                    return dateStr;
                }
            },
            
            debounce: function(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            },
            
            showMessage: function(message, type = 'success') {
                const messageDiv = document.createElement('div');
                messageDiv.className = type === 'error' ? 'error-message' : 'success-message';
                messageDiv.textContent = message;
                messageDiv.style.cssText = `
                    position: fixed; top: 20px; right: 20px; 
                    background: ${type === 'error' ? '#dc3545' : '#28a745'};
                    color: white; padding: 12px 24px; border-radius: 8px; z-index: 1000;
                    font-family: 'Samim', sans-serif; max-width: 300px;
                `;
                document.body.appendChild(messageDiv);
                
                setTimeout(() => messageDiv.remove(), 3000);
            },
            
            showError: function(message) {
                this.showMessage(message, 'error');
            },
            
            // Stub functions for compatibility
            toggleColumnSelector: function() {
                // Implement column selector if needed
                console.log('Column selector not implemented in optimized version');
            },
            
            expandAll: function() {
                console.log('Expand all not implemented in optimized version');
            },
            
            collapseAll: function() {
                console.log('Collapse all not implemented in optimized version');
            },
            
            prepareForPrint: function() {
                window.print();
            },
            
            handleFilter: function(column, value) {
                // Implement filtering if needed
                console.log(`Filter ${column}: ${value}`);
            },
            
            handleSort: function(column, direction) {
                // Implement sorting if needed
                console.log(`Sort ${column}: ${direction}`);
            },
            
            editTask: function(taskId) {
                console.log(`Edit task: ${taskId}`);
            }
        };
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Get data from PHP
            const initialData = <?php echo $tasks_json; ?>;
            const projectSummary = <?php echo json_encode($project_summary ?? [], JSON_UNESCAPED_UNICODE); ?>;
            
            OptimizedSchedule.initialize(initialData, projectSummary);
        });
    </script>
    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>

