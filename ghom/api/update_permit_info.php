<?php
// ghom/api/update_permit_info.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';

if (!isLoggedIn()) exit(json_encode(['status'=>'error','message'=>'Auth required']));
require_once __DIR__ . '/../../includes/security.php';
requireCsrf();

try {
    $pdo = getProjectDBConnection('ghom');
    
    if (!isset($_POST['permit_id'])) {
        throw new Exception("Invalid request.");
    }

    $stmt = $pdo->prepare("UPDATE permits SET contractor_name = ?, notes = ? WHERE id = ?");
    $stmt->execute([
        $_POST['contractor'],
        $_POST['notes'],
        $_POST['permit_id']
    ]);

    echo json_encode(['status'=>'success']);

} catch (Exception $e) {
    echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
}