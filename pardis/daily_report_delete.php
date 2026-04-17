<?php
// public_html/pardis/daily_report_delete.php

require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$expected_project_key = 'pardis';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    echo json_encode(['success' => false, 'message' => 'Invalid project context']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$report_id = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
if (!$report_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'guest';
$username = $_SESSION['username'] ?? $_SESSION['name'] ?? 'Unknown';

try {
    $pdo = getProjectDBConnection('pardis');
    
    // Check if report exists and get owner info
    $stmt = $pdo->prepare("SELECT user_id, report_date, engineer_name FROM daily_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit();
    }
    
    // Check permissions
    $is_admin = in_array($user_role, ['admin', 'superuser', 'cod']);
    $is_owner = ($report['user_id'] == $user_id);
    
    // Admin can delete all, owners can delete their own (except 'user' role)
    if (!$is_admin && (!$is_owner || $user_role === 'user')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Delete activities first (foreign key constraint)
        $stmt = $pdo->prepare("DELETE FROM report_activities WHERE report_id = ?");
        $stmt->execute([$report_id]);
        
        // Delete the report
        $stmt = $pdo->prepare("DELETE FROM daily_reports WHERE id = ?");
        $stmt->execute([$report_id]);
        
        // Commit transaction
        $pdo->commit();
        
        // Log the activity with proper parameters
        $details = "Deleted daily report (ID: $report_id, Date: {$report['report_date']}, Engineer: {$report['engineer_name']})";
        log_activity($user_id, $username, 'delete_daily_report', $details, 'pardis');
        
        echo json_encode(['success' => true, 'message' => 'Report deleted successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    logError("Error deleting report: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}