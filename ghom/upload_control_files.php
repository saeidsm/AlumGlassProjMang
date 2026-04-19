<?php
require_once __DIR__ . '/../sercon/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
    secureSession();
    
    $allowed_roles = ['superuser', 'pco'];
    if (!isLoggedIn() || !in_array($_SESSION['role'], $allowed_roles)) {
        throw new Exception('دسترسی غیرمجاز');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Invalid Request');

    $pdo = getProjectDBConnection('ghom');
    $report_date = $_POST['report_date'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;

    if (empty($report_date)) throw new Exception('تاریخ گزارش الزامی است');

    $uploadDir = __DIR__ . '/uploads/control_files/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $uploadedCount = 0;

    // Map HTML input names to Database Enum types
    // Note the [] in names are handled by PHP automatically
    $fileCategories = [
        'msp_files'   => 'msp',
        'excel_files' => 'excel',
        'pdf_files'   => 'pdf',
        'word_files'  => 'word'
    ];

    foreach ($fileCategories as $inputName => $dbType) {
        if (!isset($_FILES[$inputName])) continue;

        // Normalize array structure for multiple files
        $files = $_FILES[$inputName];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

            $tmpName = $files['tmp_name'][$i];
            $orgName = $files['name'][$i];
            $ext = strtolower(pathinfo($orgName, PATHINFO_EXTENSION));
            
            // Generate unique name: DATE_TYPE_RANDOM.ext
            $safeDate = str_replace('/', '-', $report_date);
            $uniqId = substr(md5(uniqid()), 0, 5);
            $targetName = "{$safeDate}_{$dbType}_{$uniqId}.{$ext}";
            $targetPath = $uploadDir . $targetName;
            $dbPath = 'uploads/control_files/' . $targetName;

            if (move_uploaded_file($tmpName, $targetPath)) {
                $stmt = $pdo->prepare("INSERT INTO project_control_files 
                    (report_date, file_type, file_name, file_path, uploaded_by) 
                    VALUES (?, ?, ?, ?, ?)");
                
                $stmt->execute([$report_date, $dbType, $orgName, $dbPath, $user_id]);
                $uploadedCount++;
            }
        }
    }

    if ($uploadedCount === 0) {
        throw new Exception('هیچ فایلی انتخاب نشده یا آپلود ناموفق بود.');
    }

    echo json_encode(['success' => true, 'message' => "$uploadedCount فایل با موفقیت آپلود شد."]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}