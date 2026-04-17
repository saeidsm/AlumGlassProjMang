<?php
// ghom/api/get_locked_elements.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../sercon/bootstrap.php';
$pdo = getProjectDBConnection('ghom');

// Fetch elements currently in an active workflow
// Statuses that LOCK an element: WaitingUpload, Pending, Approved
$sql = "
    SELECT pe.element_id, p.id as permit_id, p.status 
    FROM permit_elements pe 
    JOIN permits p ON pe.permit_id = p.id 
    WHERE p.status IN ('WaitingUpload', 'Pending', 'Approved')
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach($rows as $r) {
    // Key is element_id, Value is details
    $data[$r['element_id']] = [
        'status' => $r['status'],
        'permit_id' => $r['permit_id']
    ];
}
echo json_encode($data);