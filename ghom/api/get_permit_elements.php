<?php
// ghom/api/get_permit_elements.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';

// 1. Ensure Session is Started (Fix for $_SESSION error)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Optional: Auth Check
// if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Auth required']); exit; }

$pdo = getProjectDBConnection('ghom');
$id = $_GET['permit_id'] ?? 0;

// 2. Get Permit Details from Project DB
$stmt = $pdo->prepare("SELECT * FROM permits WHERE id = ?");
$stmt->execute([$id]);
$permit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permit) {
    echo json_encode(['error' => 'Permit not found']);
    exit;
}

// 3. Get User Details from Common DB (Safe Fetch)
$creatorName = 'نامشخص';
if (!empty($permit['user_id'])) {
    try {
        if (function_exists('getCommonDBConnection')) {
            $commonPdo = getCommonDBConnection();
            $stmtUser = $commonPdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $stmtUser->execute([$permit['user_id']]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $f = $user['first_name'] ?? '';
                $l = $user['last_name'] ?? '';
                $creatorName = trim("$f $l");
            }
        }
    } catch (Exception $e) {
        // Silent fail for user name, keep 'نامشخص'
    }
}

// 4. Get Associated Elements
$stmtEl = $pdo->prepare("SELECT element_id FROM permit_elements WHERE permit_id = ?");
$stmtEl->execute([$id]);
$ids = $stmtEl->fetchAll(PDO::FETCH_COLUMN);

// 5. Status Labels
$labels = [
    'Pending' => 'در انتظار بررسی', 
    'Approved' => 'تایید شده (OK)', 
    'Rejected' => 'رد شده (نقص)', 
    'WaitingUpload' => 'منتظر آپلود',
    'Pre-Inspection Complete' => 'تکمیل شده'
];

$status = $permit['status'] ?? 'Pending';

// 6. Return JSON (Safe)
echo json_encode([
    'elements' => $ids,
    'status' => $status,
    'status_label' => $labels[$status] ?? $status,
    'file_path' => $permit['file_path'] ?? '',
    'notes' => $permit['notes'] ?? '',
    'admin_notes' => $permit['admin_notes'] ?? '',
    'creator' => $creatorName,
    'contractor_name' => $permit['contractor_name'] ?? 'نامشخص', // Fix for potential null
    'current_user_role' => $_SESSION['role'] ?? 'guest' // Fix for session error
]);