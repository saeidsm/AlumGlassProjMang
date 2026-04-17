<?php
// ghom/api/save_permit.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/jdf.php'; 

if (!isLoggedIn()) exit(json_encode(['status'=>'error','message'=>'Auth required']));

function convertJalaliToGregorian($jDate) {
    if (empty($jDate)) return date('Y-m-d H:i:s'); 
    $parts = explode('/', $jDate);
    if (count($parts) === 3) {
        if (function_exists('jalali_to_gregorian')) {
            $gDate = jalali_to_gregorian($parts[0], $parts[1], $parts[2]);
            return $gDate[0] . '-' . $gDate[1] . '-' . $gDate[2] . ' ' . date('H:i:s');
        }
    }
    return date('Y-m-d H:i:s');
}

try {
    $pdo = getProjectDBConnection('ghom');
    $pdo->beginTransaction();

    // 1. HANDLE INPUT
    $contentType = $_SERVER["CONTENT_TYPE"] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        // JSON Input
        $input = json_decode(file_get_contents('php://input'), true);
        $permitDate = $input['date'] ?? '';
        $zone = $input['zone'] ?? '';
        $block = $input['block'] ?? '';
        $notes = $input['notes'] ?? '';
        $rawIds = $input['ids'] ?? ($input['element_ids'] ?? []);
        $ids = is_array($rawIds) ? $rawIds : explode(',', $rawIds);
        $parts = $input['parts'] ?? ['default'];
    } else {
        // FormData Input
        $permitDate = $_POST['permit_date'] ?? '';
        $zone = $_POST['zone'] ?? '';
        $block = $_POST['block'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $rawIds = $_POST['element_ids'] ?? '';
        $ids = !empty($rawIds) ? explode(',', $rawIds) : [];
        $parts = json_decode($_POST['parts_json'] ?? '[]', true);
        if (empty($parts)) $parts = ['default'];
    }

    if (empty($ids)) throw new Exception("لیست المان‌ها خالی است.");

    // 2. INSERT PERMIT
    $createdAt = convertJalaliToGregorian($permitDate);
    $stmt = $pdo->prepare("INSERT INTO permits (user_id, zone, block, notes, status, file_path, created_at) VALUES (?, ?, ?, ?, 'WaitingUpload', '', ?)");
    $stmt->execute([$_SESSION['user_id'], $zone, $block, $notes, $createdAt]);
    $permitId = $pdo->lastInsertId();

    // 3. INSERT ELEMENTS (With Part Name)
    $stmtEl = $pdo->prepare("INSERT IGNORE INTO permit_elements (permit_id, element_id, part_name) VALUES (?, ?, ?)");
    
    foreach($ids as $id) {
        if(empty($id)) continue;
        foreach($parts as $partName) {
            // Ensure part name is never null
            $cleanPart = empty($partName) ? 'default' : $partName;
            $stmtEl->execute([$permitId, $id, $cleanPart]);
        }
    }

    $pdo->commit();
    echo json_encode(['status'=>'success', 'permit_id' => $permitId]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Save Permit Error: " . $e->getMessage());
    echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
}