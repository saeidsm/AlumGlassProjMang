<?php
// ghom/api/upload_signed_permit.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/notification_helper.php';

if (!isLoggedIn()) exit(json_encode(['status'=>'error','message'=>'Auth required']));

try {
    $pdo = getProjectDBConnection('ghom');
    
    // Ensure permit_id and file are present
    if (!isset($_POST['permit_id']) || !isset($_FILES['signed_file'])) {
        throw new Exception("Invalid request. Permit ID or File missing.");
    }

    $permitId = $_POST['permit_id'];
    $contractorName = $_POST['contractor'] ?? null; // Get company name
    $notes = $_POST['notes'] ?? null; // Get updated notes

    // 1. Upload File
    $publicPath = null;
    if ($_FILES['signed_file']['error'] == 0) {
        $ext = pathinfo($_FILES['signed_file']['name'], PATHINFO_EXTENSION);
        $filename = 'permit_' . $permitId . '_' . time() . '.' . $ext;
        
        // Use ABSOLUTE system path for move_uploaded_file
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/ghom/uploads/permits/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $targetPath = $uploadDir . $filename;
        
        if (!move_uploaded_file($_FILES['signed_file']['tmp_name'], $targetPath)) {
            throw new Exception("Failed to save file.");
        }
        
        $publicPath = '/ghom/uploads/permits/' . $filename;
    } else {
        throw new Exception("File upload error code: " . $_FILES['signed_file']['error']);
    }

    // 2. Update DB (File, Status, Contractor Name, Notes)
    // We build the query dynamically to handle optional fields, though file is mandatory here.
    $sql = "UPDATE permits SET file_path = ?, status = 'Pending', updated_at = NOW()";
    $params = [$publicPath];

    if ($contractorName) {
        $sql .= ", contractor_name = ?";
        $params[] = $contractorName;
    }
    
    if ($notes) {
        $sql .= ", notes = ?";
        $params[] = $notes;
    }

    $sql .= " WHERE id = ?";
    $params[] = $permitId;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // 3. Trigger Notification to Consultants
    // Fetch info for notification
    $stmtInfo = $pdo->prepare("SELECT p.*, (SELECT element_id FROM permit_elements WHERE permit_id = p.id LIMIT 1) as el_id FROM permits p WHERE p.id = ?");
    $stmtInfo->execute([$permitId]);
    $p = $stmtInfo->fetch(PDO::FETCH_ASSOC);

    // Get Plan File for link
    $planFile = $pdo->query("SELECT plan_file FROM elements WHERE element_id = '{$p['el_id']}'")->fetchColumn();
    $planFilename = $planFile ? basename($planFile) : 'Plan.svg';

    $group_info = [
        'total_count' => 1, // This triggers the batch logic in notification helper
        'permit_id' => $permitId
    ];

    // Create a descriptive message
    $msg = "فرم امضا شده آپلود شد.";
    if ($contractorName) $msg .= " پیمانکار: " . $contractorName;
    if ($notes) $msg .= ". توضیحات: " . $notes;

    trigger_workflow_task(
        $pdo,
        $group_info,
        null,
        $planFilename,
        'PERMIT_CREATED', // Using existing type to notify admin
        $_SESSION['user_id'],
        date('Y-m-d'),
        $msg
    );

    echo json_encode(['status'=>'success']);

} catch (Exception $e) {
    echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
}