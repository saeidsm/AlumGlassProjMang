<?php
// ===================================================================
// FINAL, COMPLETE, AND CORRECTED save_inspection.php (Single-Key with Auto-Reset)
// ===================================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/vendor/autoload.php';
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/jdf.php';
require_once __DIR__ . '/../includes/notification_helper.php';

use phpseclib3\Crypt\RSA;

// --- Enhanced Logging Function ---
function write_log($message)
{
    $log_file = __DIR__ . '/logs/save_inspection_' . date("Y-m-d") . '.log';
    if (!file_exists(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    $timestamp = date("Y-m-d H:i:s");
    $formatted_message = is_array($message) || is_object($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message;
    file_put_contents($log_file, "[$timestamp] " . $formatted_message . "\n", FILE_APPEND);
}

// Helper function to convert Jalali date string to Gregorian for DB
function toGregorian($jalaliDate)
{
    if (empty($jalaliDate) || !is_string($jalaliDate)) {
        return null;
    }

    $parts = array_map('intval', explode('/', trim($jalaliDate)));
    if (count($parts) !== 3 || $parts[0] < 1300) {
        return null;
    }

    if (function_exists('jalali_to_gregorian')) {
        return implode('-', jalali_to_gregorian($parts[0], $parts[1], $parts[2]));
    }

    return null;
}

function appendToJsonColumn(PDO $pdo, int $inspectionId, string $columnName, array $newEntry)
{
    $stmt = $pdo->prepare("SELECT $columnName FROM inspections WHERE inspection_id = ?");
    $stmt->execute([$inspectionId]);
    $currentJson = $stmt->fetchColumn();

    $dataArray = !empty($currentJson) ? json_decode($currentJson, true) : [];
    if (!is_array($dataArray)) {
        $dataArray = [];
    }

    $dataArray[] = $newEntry;

    $newJson = json_encode($dataArray, JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare("UPDATE inspections SET $columnName = ? WHERE inspection_id = ?");
    $stmt->execute([$newJson, $inspectionId]);
}

function processAndMergeUploads(string $fileInputKey, string $elementId, ?string $existingAttachmentsJson): ?string
{
    $allPaths = ($existingAttachmentsJson) ? json_decode($existingAttachmentsJson, true) : [];
    if (!is_array($allPaths)) {
        $allPaths = [];
    }

    if (isset($_FILES[$fileInputKey]) && is_array($_FILES[$fileInputKey]['name'])) {
        $uploadDir = defined('MESSAGE_UPLOAD_PATH_SYSTEM') ? MESSAGE_UPLOAD_PATH_SYSTEM : 'uploads/';
        $uploadDirPublic = defined('MESSAGE_UPLOAD_DIR_PUBLIC') ? MESSAGE_UPLOAD_DIR_PUBLIC : '/uploads/';
        foreach ($_FILES[$fileInputKey]['tmp_name'] as $key => $tmpName) {
            if (is_uploaded_file($tmpName) && $_FILES[$fileInputKey]['error'][$key] === UPLOAD_ERR_OK) {
                $originalName = basename($_FILES[$fileInputKey]['name'][$key]);
                $safeFilename = "pardis_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $elementId) . "_" . time() . "_" . uniqid() . "." . pathinfo($originalName, PATHINFO_EXTENSION);
                if (move_uploaded_file($tmpName, $uploadDir . $safeFilename)) {
                    $allPaths[] = $uploadDirPublic . $safeFilename;
                    write_log("File Upload SUCCESS: Moved '$originalName' to '$safeFilename' for input '$fileInputKey'.");
                } else {
                    write_log("File Upload ERROR: Failed to move '$originalName'. Check permissions for '$uploadDir'.");
                }
            }
        }
    }
    return !empty($allPaths) ? json_encode(array_values($allPaths)) : null;
}

function recursive_ksort(array &$array) {
    ksort($array);
    foreach ($array as &$value) {
        if (is_array($value)) {
            recursive_ksort($value);
        }
    }
}

function canonicalJsonEncode(array $data): string {
    recursive_ksort($data);
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function createHistoryEntry(array $stageData, int $userId, string $userRole, ?array $newAttachments, ?array $newContractorAttachments): array
{
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $userId,
        'role' => $userRole,
        'action' => '',
        'data' => []
    ];

    $dataToLog = [];
    $isContractorAction = in_array($userRole, ['cat', 'car', 'coa', 'crs']);

    if ($isContractorAction || $userRole === 'superuser') {
        $entry['action'] = 'Contractor Action';
        $dataToLog['contractor_status'] = $stageData['contractor_status'] ?? null;
        $dataToLog['contractor_date'] = toGregorian($stageData['contractor_date'] ?? null);
        $dataToLog['contractor_notes'] = $stageData['contractor_notes'] ?? null;
        $dataToLog['contractor_attachments'] = $newContractorAttachments;
    }

    if ($userRole === 'admin' || $userRole === 'superuser') {
        $entry['action'] = 'Supervisor Action';
        $dataToLog['overall_status'] = $stageData['overall_status'] ?? null;
        $dataToLog['inspection_date'] = toGregorian($stageData['inspection_date'] ?? null);
        $dataToLog['notes'] = $stageData['notes'] ?? null;
        $dataToLog['attachments'] = $newAttachments;
    }

    if (isset($stageData['items']) && in_array($userRole, ['admin', 'superuser'])) {
        $dataToLog['checklist_items'] = $stageData['items'];
    }

    $entry['data'] = $dataToLog;
    return $entry;
}

// --- Main execution starts ---
write_log("================== SAVE REQUEST START ==================");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['status' => 'error', 'message' => 'Invalid request method.']));
}

secureSession();

if (!isset($_SESSION['user_id']) || !isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Your session has expired. Please log in again.']));
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Invalid security token. Please refresh the page.']));
}

$pdo = null;
$common_pdo = null;
try {
    // --- START: FINAL, CORRECTED SIGNATURE VERIFICATION BLOCK ---
    $userId = $_SESSION['user_id'];
    $signature_b64 = $_POST['digital_signature'] ?? null;
    $stagesDataJson = isset($_POST['stages']) ? stripslashes($_POST['stages']) : '[]';
    $stagesData = json_decode($stagesDataJson, true);

    if (json_last_error() !== JSON_ERROR_NONE) { throw new Exception("Invalid JSON in 'stages' field."); }
    if (!$signature_b64) { throw new Exception("Digital signature is missing."); }

    $canonicalDataForVerification = canonicalJsonEncode($stagesData);
    
    $common_pdo = getCommonDBConnection();
    // This queries the ORIGINAL users table
    $stmt_key = $common_pdo->prepare("SELECT public_key_pem FROM users WHERE id = ?");
    $stmt_key->execute([$userId]);
    $publicKeyPem = $stmt_key->fetchColumn();

    if (!$publicKeyPem) {
        http_response_code(403);
        write_log("SAVE FAILED: No public key found in users table for user {$userId}.");
        // Send the special error code
        exit(json_encode([
            'status' => 'error', 
            'message' => 'هیچ کلید عمومی برای شما ثبت نشده. کلید قدیمی حذف خواهد شد.', 
            'errorCode' => 'INVALID_SIGNATURE'
        ]));
    }

    $isVerified = false;
    try {
        $publicKey = RSA::load($publicKeyPem)->withPadding(RSA::SIGNATURE_PKCS1)->withHash('sha256');
        if ($publicKey->verify($canonicalDataForVerification, base64_decode($signature_b64))) {
            $isVerified = true;
        }
    } catch (\Exception $e) {
        write_log("Key load/verify error for user {$userId}: " . $e->getMessage());
        $isVerified = false;
    }

    if (!$isVerified) {
        http_response_code(403);
        write_log("SAVE FAILED: Signature did not match the stored public key for user {$userId}. Sending reset command.");
        // This sends the special error code that the JavaScript will understand.
        exit(json_encode([
            'status' => 'error', 
            'message' => 'امضای دیجیتال شما نامعتبر است. کلید قدیمی حذف خواهد شد.', 
            'errorCode' => 'INVALID_SIGNATURE'
        ]));
    } 
    
    write_log("Digital signature VERIFIED for user {$userId}.");
    // --- END: FINAL, CORRECTED SIGNATURE VERIFICATION BLOCK ---


    // --- 3. PROCEED WITH ORIGINAL LOGIC ---
    $fullElementId = $_POST['elementId'] ?? '';
    $planFile = $_POST['planFile'] ?? '';
    $userRole = $_SESSION['role'];
    
    write_log("User: {$userId} ({$userRole}) | Element: {$fullElementId}");

    if (empty($stagesData)) {
        throw new Exception("No stage data was submitted or JSON was invalid.");
    }

    $pdo = getProjectDBConnection('pardis');
    $pdo->beginTransaction();

    $baseElementId = $fullElementId;
    $partName = null;
    $parts = explode('-', $fullElementId);
    if (count($parts) > 1 && in_array(strtolower(end($parts)), ['face', 'up', 'down', 'left', 'right', 'default'])) {
        $partName = array_pop($parts);
        $baseElementId = implode('-', $parts);
    }

    $stmt_type = $pdo->prepare("SELECT element_type FROM elements WHERE element_id = ?");
    $stmt_type->execute([$baseElementId]);
    $elementType = $stmt_type->fetchColumn();

    $stmt_find_pre_inspection = $pdo->prepare("SELECT * FROM inspections WHERE element_id = ? AND part_name <=> ? AND stage_id = 0 LIMIT 1");
    $stmt_find_pre_inspection->execute([$baseElementId, $partName]);
    $pre_inspection_record = $stmt_find_pre_inspection->fetch(PDO::FETCH_ASSOC);

    $initialAttachments = $pre_inspection_record['attachments'] ?? null;
    $initialContractorAttachments = $pre_inspection_record['contractor_attachments'] ?? null;

    $newAttachmentsJson = processAndMergeUploads('attachments', $fullElementId, $initialAttachments);
    $newContractorAttachmentsJson = processAndMergeUploads('contractor_attachments', $fullElementId, $initialContractorAttachments);

    $newAttachmentsArray = $newAttachmentsJson ? json_decode($newAttachmentsJson, true) : [];
    $newContractorAttachmentsArray = $newContractorAttachmentsJson ? json_decode($newContractorAttachmentsJson, true) : [];

    $isFirstStageInLoop = true;

    foreach ($stagesData as $stageId => $stageData) {
        write_log("--- Processing Stage ID: $stageId for element type: $elementType ---");
        if (!is_array($stageData)) {
            write_log("WARNING: Stage data for ID '$stageId' is not an array, skipping.");
            continue;
        }

        if (isset($stageData['stageId'])) {
            unset($stageData['stageId']);
        }
        $existing_inspection = null;
        $action = '';

       
        $stmt_find_specific = $pdo->prepare("SELECT * FROM inspections WHERE element_id = ? AND part_name <=> ? AND stage_id = ? LIMIT 1");
        $stmt_find_specific->execute([$baseElementId, $partName, $stageId]);
        $specific_record = $stmt_find_specific->fetch(PDO::FETCH_ASSOC);  
        
        if ($specific_record) {
            $action = 'UPDATE';
            $existing_inspection = $specific_record;
            write_log("   Action decided: UPDATE existing stage record ID: " . $existing_inspection['inspection_id']);
        } else if ($isFirstStageInLoop && $pre_inspection_record) {
            $action = 'UPDATE';
            $existing_inspection = $pre_inspection_record;
            write_log("   Action decided: UPDATE (Transform) pre-inspection record ID: " . $existing_inspection['inspection_id']);
        } else {
            $action = 'INSERT';
            write_log("   Action decided: INSERT new record for stage {$stageId}.");
        }

        $isFirstStageInLoop = false;

        if ($elementType === 'GFRC' && !$existing_inspection && $action === 'UPDATE') {
            throw new Exception("CRITICAL: GFRC element '{$fullElementId}' requires a pre-inspection record (stage_id=0) which was not found.");
        }

        if ($action === 'UPDATE') {
            $inspectionId = $existing_inspection['inspection_id'];
            write_log("   Action decided: UPDATE existing record ID: {$inspectionId}");
            
            $params = $existing_inspection;
            unset($params['inspection_id'], $params['created_at']);
            $params['digital_signature'] = $signature_b64;
            $params['signed_data'] = $canonicalDataForVerification; // Use canonical string
            $params['stage_id'] = $stageId;

            
            if (in_array($userRole, ['admin', 'superuser']) && isset($stageData['overall_status'])) {
                $currentRejectionCount = (int)$params['repair_rejection_count'];
                $newOverallStatus = $stageData['overall_status'];
                
                if ($newOverallStatus === 'Repair' && $params['contractor_status'] === 'Awaiting Re-inspection') {
                    $currentRejectionCount++;
                    write_log("   Repair rejected. New count: {$currentRejectionCount}");
                }
                if ($currentRejectionCount >= 3) {
                    $stageData['overall_status'] = 'Reject';
                     write_log("   Rejection limit reached. Status forced to Reject.");
                }
                if ($newOverallStatus === 'OK') {
                    $currentRejectionCount = 0;
                    write_log("   Status is OK. Resetting rejection count.");
                }
                $params['repair_rejection_count'] = $currentRejectionCount;
            }

            if (in_array($userRole, ['admin', 'superuser'])) {
                if (isset($stageData['overall_status'])) {
                    $params['overall_status'] = $stageData['overall_status'];
                    $params['status'] = $stageData['overall_status']; 
                }
                if (isset($stageData['inspection_date'])) { $params['inspection_date'] = toGregorian($stageData['inspection_date']); }
                if (isset($stageData['notes'])) { $params['notes'] = $stageData['notes']; }
                $params['attachments'] = $newAttachmentsJson;
            }
            
            if (in_array($userRole, ['cat', 'car', 'coa', 'crs']) || ($userRole === 'superuser' && isset($stageData['contractor_status']))) {
                if (isset($stageData['contractor_status'])) {
                    $submitted_status = $stageData['contractor_status'];
                    
                    if (in_array($submitted_status, ['Opening Approved', 'Pre-Inspection Complete'])) {
                        $params['status'] = 'Awaiting Re-inspection'; 
                        $params['contractor_status'] = 'Awaiting Re-inspection';
                        write_log("   Contractor signaled readiness. Status columns updated to 'Awaiting Re-inspection'.");
                    } else {
                        $params['contractor_status'] = $submitted_status;
                    }
                }
                if (isset($stageData['contractor_date'])) { $params['contractor_date'] = toGregorian($stageData['contractor_date']); }
                if (isset($stageData['contractor_notes'])) { $params['contractor_notes'] = $stageData['contractor_notes']; }
                $params['contractor_attachments'] = $newContractorAttachmentsJson;
            }
            
            $params['user_id'] = $userId;

            if (isset($params['contractor_status'])) {
                $stageData['contractor_status'] = $params['contractor_status'];
            }
            if (isset($params['overall_status'])) {
                $stageData['overall_status'] = $params['overall_status'];
            }

            $update_fields = [];
            foreach (array_keys($params) as $key) { $update_fields[] = "`$key` = :$key"; }
            $sql = "UPDATE inspections SET " . implode(', ', $update_fields) . " WHERE inspection_id = :inspection_id";
            $params['inspection_id'] = $inspectionId;
            $pdo->prepare($sql)->execute($params);
            write_log("   Updated record {$inspectionId}.");

            $historyEntry = createHistoryEntry($stageData, $userId, $userRole, $newAttachmentsArray, $newContractorAttachmentsArray);
            appendToJsonColumn($pdo, $inspectionId, 'history_log', $historyEntry);

        } else { // $action === 'INSERT'
            $inspectionId = null;

            $pre_log_to_use = null;
            $cycle_to_use = 1;

            if (!empty($pre_inspection_record)) {
                $pre_log_to_use = $pre_inspection_record['pre_inspection_log'];
                $cycle_to_use = $pre_inspection_record['inspection_cycle'] ?? 1;
            } else {
                $stmt_find_latest = $pdo->prepare("SELECT pre_inspection_log, inspection_cycle FROM inspections WHERE element_id = ? AND part_name <=> ? ORDER BY inspection_id DESC LIMIT 1");
                $stmt_find_latest->execute([$baseElementId, $partName]);
                $latest_record = $stmt_find_latest->fetch(PDO::FETCH_ASSOC);
                if ($latest_record) {
                    $pre_log_to_use = $latest_record['pre_inspection_log'];
                    $cycle_to_use = $latest_record['inspection_cycle'] ?? 1;
                }
            }
            if ($pre_log_to_use === null) {
                $pre_log_to_use = '[]';
            }

            $params = [
                'element_id' => $baseElementId,
                'part_name' => $partName,
                'stage_id' => $stageId,
                'user_id' => $userId,
                'digital_signature' => $signature_b64,
                'signed_data' => $canonicalDataForVerification, // Use canonical string
                'pre_inspection_log' => $pre_log_to_use,
                'inspection_cycle' => $cycle_to_use,
                'attachments' => $newAttachmentsJson,
                'contractor_attachments' => $newContractorAttachmentsJson,
            ];

            if (in_array($userRole, ['admin', 'superuser'])) {
                if (isset($stageData['overall_status'])) {
                    $params['overall_status'] = $stageData['overall_status'];
                    $params['status'] = $stageData['overall_status'];
                }
                if (isset($stageData['inspection_date'])) {
                    $params['inspection_date'] = toGregorian($stageData['inspection_date']);
                }
                if (isset($stageData['notes'])) {
                    $params['notes'] = $stageData['notes'];
                }
            }
            if (in_array($userRole, ['cat', 'car', 'coa', 'crs']) || $userRole === 'superuser') {
                if (isset($stageData['contractor_status'])) {
                    $params['contractor_status'] = $stageData['contractor_status'];
                }
                if (isset($stageData['contractor_date'])) {
                    $params['contractor_date'] = toGregorian($stageData['contractor_date']);
                }
                if (isset($stageData['contractor_notes'])) {
                    $params['contractor_notes'] = $stageData['contractor_notes'];
                }
            }

            $historyEntry = createHistoryEntry($stageData, $userId, $userRole, $newAttachmentsArray, $newContractorAttachmentsArray);
            $params['history_log'] = json_encode([$historyEntry], JSON_UNESCAPED_UNICODE);

            $columns = implode(', ', array_map(fn($c) => "`$c`", array_keys($params)));
            $placeholders = ':' . implode(', :', array_keys($params));
            $sql = "INSERT INTO inspections ($columns) VALUES ($placeholders)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $inspectionId = $pdo->lastInsertId();
            write_log("   Inserted new record with ID: {$inspectionId}.");
        }
            
        if ($inspectionId) {
            $event_type_to_trigger = null;
            $final_consultant_status = $params['overall_status'] ?? null;
            $final_contractor_status = $params['contractor_status'] ?? null;

            if (in_array($userRole, ['admin', 'superuser'])) {
                if ($final_consultant_status === 'Repair') $event_type_to_trigger = 'REPAIR_REQUESTED';
                elseif ($final_consultant_status === 'OK') $event_type_to_trigger = 'INSPECTION_OK';
                elseif ($final_consultant_status === 'Reject') $event_type_to_trigger = 'INSPECTION_REJECT';
            } 
            elseif (in_array($userRole, ['cat', 'car', 'coa', 'crs'])) {
                if ($final_contractor_status === 'Awaiting Re-inspection') {
                    $previous_consultant_status = $existing_inspection['overall_status'] ?? null;
                    if ($previous_consultant_status === 'Repair') $event_type_to_trigger = 'REPAIR_DONE';
                    else $event_type_to_trigger = 'INSPECTION_READY';
                }
            }

             if ($event_type_to_trigger) {
                if (empty($planFile)) {
                    log_task_event("WARNING: planFile is empty in save_inspection.php for element {$baseElementId}.");
                } else {
                    $stmt_stage_name = $pdo->prepare("SELECT stage FROM inspection_stages WHERE stage_id = ?");
                    $stmt_stage_name->execute([$stageId]);
                    $stage_name = $stmt_stage_name->fetchColumn();
                    
                    $relevant_notes = (in_array($userRole, ['admin', 'superuser'])) 
                        ? ($stageData['notes'] ?? '') 
                        : ($stageData['contractor_notes'] ?? '');

                    trigger_workflow_task(
                        $pdo,
                        $baseElementId,
                        $partName,
                        $planFile,
                        $event_type_to_trigger,
                        $userId,
                        null,
                        $relevant_notes,
                        $stageId,
                        $stage_name
                    );
                }
            }
        }

        if ($inspectionId && isset($stageData['items'])) {
            if (in_array($userRole, ['admin', 'superuser'])) {
                write_log("  User is authorized. Updating checklist items for inspection ID: $inspectionId");
                $pdo->prepare("DELETE FROM inspection_data WHERE inspection_id = ?")->execute([$inspectionId]);
                $stmt_insert_item = $pdo->prepare("INSERT INTO inspection_data (inspection_id, item_id, item_status, item_value) VALUES (?, ?, ?, ?)");
                foreach ($stageData['items'] as $item) {
                    $stmt_insert_item->execute([$inspectionId, $item['item_id'], $item['status'] ?? 'N/A', $item['value'] ?? '']);
                }
            } else {
                write_log("  User is a contractor. SKIPPING update of checklist items for inspection ID: $inspectionId");
            }
        }
    }

    $pdo->commit();
    write_log("SUCCESS: Transaction committed.");
    echo json_encode(['status' => 'success', 'message' => 'اطلاعات با موفقیت ذخیره شد.']);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error_message = "File: {$e->getFile()} | Line: {$e->getLine()} | Message: {$e->getMessage()}";
    write_log("FATAL ERROR: " . $error_message);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'خطای داخلی سرور رخ داد. لطفا با پشتیبانی تماس بگیرید.', 'details' => $e->getMessage()]);
}