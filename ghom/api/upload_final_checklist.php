<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';

if (!isLoggedIn()) exit(json_encode(['status'=>'error']));
require_once __DIR__ . '/../../includes/security.php';
requireCsrf();

try {
    $pdo = getProjectDBConnection('ghom');
    $permitId = $_POST['permit_id'];
    
    if (!isset($_FILES['checklist_files'])) {
        throw new Exception("هیچ فایلی انتخاب نشده است.");
    }

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/ghom/uploads/checklists/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $files = $_FILES['checklist_files'];
    $count = count($files['name']);
    $successCount = 0;

    $stmt = $pdo->prepare("INSERT INTO permit_checklist_files (permit_id, file_path, file_name, uploaded_by) VALUES (?, ?, ?, ?)");

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] == 0) {
            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $cleanName = basename($files['name'][$i]);
            $diskName = 'chk_' . $permitId . '_' . time() . '_' . $i . '.' . $ext;
            
            if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $diskName)) {
                $publicPath = '/ghom/uploads/checklists/' . $diskName;
                $stmt->execute([$permitId, $publicPath, $cleanName, $_SESSION['user_id']]);
                $successCount++;
            }
        }
    }

    echo json_encode(['status'=>'success', 'message'=>"$successCount فایل با موفقیت آپلود شد."]);

} catch (Exception $e) {
    echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
}