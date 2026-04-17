<?php
// /pardis/api/get_eligible_parts.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';

if (session_status() == PHP_SESSION_NONE) session_start();
if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$element_ids = $data['element_ids'] ?? [];

if (empty($action) || empty($element_ids)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Action and element IDs are required.']));
}

// Determine the prerequisite status for the given action
$prerequisite_status = '';
switch ($action) {
    case 'approve-opening':
    case 'reject-opening':
        $prerequisite_status = 'Request to Open';
        break;
    case 'confirm-opened':
        $prerequisite_status = 'Opening Approved';
        break;
    case 'verify-opening':
    case 'dispute-opening':
        $prerequisite_status = 'Panel Opened';
        break;
    // For 'request-opening', any part that is 'Pending', 'Opening Rejected', or 'Opening Disputed' is eligible.
    case 'request-opening':
        $prerequisite_status = ['Pending', 'Opening Rejected', 'Opening Disputed'];
        break;
    default:
        // For other actions or if no prerequisite, return an empty set.
        echo json_encode([]);
        exit();
}

try {
    $pdo = getProjectDBConnection('pardis');
    
    // Base SQL query
    $sql = "SELECT element_id, part_name FROM inspections WHERE element_id IN ";
    
    // Create placeholders for element IDs
    $element_placeholders = implode(',', array_fill(0, count($element_ids), '?'));
    $sql .= "($element_placeholders)";
    
    $params = $element_ids;
    
    // Add status condition
    if (is_array($prerequisite_status)) {
        $status_placeholders = implode(',', array_fill(0, count($prerequisite_status), '?'));
        $sql .= " AND status IN ($status_placeholders)";
        $params = array_merge($params, $prerequisite_status);
    } else {
        $sql .= " AND status = ?";
        $params[] = $prerequisite_status;
    }

    // Filter by pre-inspection stage
    $sql .= " AND stage_id = 0";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group the results by element_id
    $eligible_parts = [];
    foreach ($results as $row) {
        if (!isset($eligible_parts[$row['element_id']])) {
            $eligible_parts[$row['element_id']] = [];
        }
        // Only add non-null part names
        if ($row['part_name']) {
            $eligible_parts[$row['element_id']][] = $row['part_name'];
        }
    }

    echo json_encode($eligible_parts);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}