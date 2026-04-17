<?php
// /ghom/api/create_opening_permit_request.php (DEFINITIVELY CORRECTED)
require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/notification_helper.php';

header('Content-Type: application/json');
if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Forbidden']));
}

try {
    $pdo = getProjectDBConnection('ghom');
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['panels'])) {
        throw new Exception("No panels selected for the permit request.");
    }

    $permit_uid = 'PERMIT-' . time() . '-' . uniqid();
    $requester_user_id = $_SESSION['user_id'];
    $request_date = jalali_to_gregorian_for_db($data['date']);

    $sql = "INSERT INTO opening_permits (permit_uid, requester_user_id, request_date, panel_data, notes, zone_name, block_name, contractor_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending Signature')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $permit_uid, $requester_user_id, $request_date,
        json_encode($data['panels']), $data['notes'],
        $data['zone'], $data['block'], $data['contractor']
    ]);
    
    // This triggers the notification for the CONTRACTOR
    $group_info = [
        'total_count' => count($data['panels']),
        'sample_element_details' => [
             'plan_file' => ($data['panels'][0]['plan_file'] ?? ''),
             'block' => $data['block'],
             'zone_name' => $data['zone'],
             'contractor' => $data['contractor']
        ],
        'permit_uid' => $permit_uid
    ];

    trigger_workflow_task(
        $pdo, $group_info, null, ($data['panels'][0]['plan_file'] ?? ''),
        'PERMIT_GENERATED_PENDING_UPLOAD', // This event is for the contractor
        $requester_user_id, null, $data['notes'], 0
    );

    echo json_encode(['success' => true, 'permit_uid' => $permit_uid]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function jalali_to_gregorian_for_db($jalali_date) {
    if (empty($jalali_date)) return date('Y-m-d');
    require_once __DIR__ . '/../includes/jdf.php';
    $parts = array_map('intval', explode('/', trim($jalali_date)));
    return (count($parts) === 3) ? implode('-', jalali_to_gregorian($parts[0], $parts[1], $parts[2])) : date('Y-m-d');
}