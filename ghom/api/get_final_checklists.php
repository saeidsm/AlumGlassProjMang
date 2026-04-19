<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/jdf.php';

if (!isLoggedIn()) exit(json_encode(['status'=>'error']));

$permitId = $_GET['permit_id'];
$pdo = getProjectDBConnection('ghom');

$stmt = $pdo->prepare("SELECT * FROM permit_checklist_files WHERE permit_id = ? ORDER BY created_at DESC");
$stmt->execute([$permitId]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($files as &$f) {
    $f['date_persian'] = jdate('Y/m/d H:i', strtotime($f['created_at']));
}

echo json_encode($files);