<?php
// ghom/api/save_permit_checklist.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/jdf.php';

if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['status'=>'error', 'message'=>'Unauthorized']));
}

$userRole = $_SESSION['role'];

// 1. Define allowed roles (Contractors MUST be allowed to fill the form)
$allowedRoles = ['admin', 'superuser', 'noi', 'cat', 'car', 'coa', 'crs'];

if (!in_array($userRole, $allowedRoles)) {
    http_response_code(403);
    exit(json_encode(['status'=>'error', 'message'=>'دسترسی غیرمجاز.']));
}

$data = json_decode(file_get_contents('php://input'), true);
$pdo = getProjectDBConnection('ghom');

if (empty($data['permit_id'])) {
    exit(json_encode(['status'=>'error', 'message'=>'Missing Permit ID']));
}

try {
    // Merge existing meta if needed, here we just overwrite last_user
    $checklistData = $data['checklist_data'];
    $checklistData['_meta'] = [
        'last_user_id' => $_SESSION['user_id'],
        'last_user_name' => $_SESSION['username'],
        'last_user_role' => $userRole,
        'timestamp' => time(),
        'persian_date' => jdate('Y/m/d H:i')
    ];

    $checklistJson = json_encode($checklistData);
    $permitId = $data['permit_id'];
    $elementId = $data['element_id'];

    if ($elementId === 'ALL') {
        $stmt = $pdo->prepare("UPDATE permit_elements SET checklist_data = ? WHERE permit_id = ?");
        $stmt->execute([$checklistJson, $permitId]);
    } else {
        $stmt = $pdo->prepare("UPDATE permit_elements SET checklist_data = ? WHERE permit_id = ? AND element_id = ?");
        $stmt->execute([$checklistJson, $permitId, $elementId]);
    }

    echo json_encode(['status'=>'success']);
} catch (Exception $e) {
    echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
}