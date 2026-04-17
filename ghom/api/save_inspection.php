<?php
// ===================================================================
// DEBUG ENHANCED save_inspection.php - FIXED VERSION
// ===================================================================
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../sercon/vendor/autoload.php';
require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/jdf.php';
require_once __DIR__ . '/../includes/notification_helper.php';
secureSession();

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
                $safeFilename = "ghom_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $elementId) . "_" . time() . "_" . uniqid() . "." . pathinfo($originalName, PATHINFO_EXTENSION);
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
error_log("=== PUBLIC KEY DEBUG START ===");
error_log("Current user ID from session: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("POST digital_signature present: " . (isset($_POST['digital_signature']) ? 'YES' : 'NO'));
error_log("POST signed_data present: " . (isset($_POST['signed_data']) ? 'YES' : 'NO'));
function handleBatchSave(PDO $pdo) {
    write_log("--- BATCH SAVE MODE DETECTED ---");

    $input = json_decode(file_get_contents('php://input'), true);
    $element_ids = $input['element_ids'] ?? [];
    $stages_data = $input['stages_data'] ?? [];
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    if (empty($element_ids) || empty($stages_data)) {
        throw new Exception("Batch save requires element_ids and stages_data.");
    }
    
    if (!in_array($user_role, ['admin', 'superuser'])) {
        throw new Exception("Only authorized users can perform batch updates.");
    }

    $processed_count = 0;
    foreach ($element_ids as $element_id) {
        write_log("Batch processing for element: {$element_id}");
        foreach ($stages_data as $stage_id => $stage_data) {
            
            // Find or create an inspection record
            $part_name = $stage_data['part_name'] ?? null;
            $stmt_find = $pdo->prepare("SELECT inspection_id FROM inspections WHERE element_id = ? AND part_name <=> ? AND stage_id = ? ORDER BY inspection_id DESC LIMIT 1");
            $stmt_find->execute([$element_id, $part_name, $stage_id]);
            $inspection_id = $stmt_find->fetchColumn();

            if (!$inspection_id) {
                // ✅ FIXED: Added history_log with default '[]' value to prevent SQL Error 1364
                $stmt_insert = $pdo->prepare("INSERT INTO inspections (element_id, part_name, stage_id, user_id, history_log) VALUES (?, ?, ?, ?, ?)");
                $stmt_insert->execute([$element_id, $part_name, $stage_id, $user_id, '[]']);
                $inspection_id = $pdo->lastInsertId();
                write_log("Created new inspection record ID {$inspection_id} for element {$element_id}, stage {$stage_id}");
            }

            // Prepare the update query
            $update_fields = [];
            $params = ['inspection_id' => $inspection_id, 'user_id' => $user_id];
            
            if (isset($stage_data['overall_status']) && !empty($stage_data['overall_status'])) {
                $update_fields[] = "overall_status = :overall_status";
                $update_fields[] = "status = :overall_status";
                $params['overall_status'] = $stage_data['overall_status'];
            }
            if (isset($stage_data['inspection_date']) && !empty($stage_data['inspection_date'])) {
                $update_fields[] = "inspection_date = :inspection_date";
                $params['inspection_date'] = toGregorian($stage_data['inspection_date']);
            }
            if (isset($stage_data['notes'])) {
                $update_fields[] = "notes = :notes";
                $params['notes'] = $stage_data['notes'];
            }

            if (!empty($update_fields)) {
                $sql = "UPDATE inspections SET " . implode(', ', $update_fields) . ", user_id = :user_id WHERE inspection_id = :inspection_id";
                $stmt_update = $pdo->prepare($sql);
                $stmt_update->execute($params);
            }

            // Update checklist items
            if (isset($stage_data['items']) && is_array($stage_data['items'])) {
                $pdo->prepare("DELETE FROM inspection_data WHERE inspection_id = ?")->execute([$inspection_id]);
                $stmt_insert_item = $pdo->prepare("INSERT INTO inspection_data (inspection_id, item_id, item_status, item_value) VALUES (?, ?, ?, ?)");
                foreach ($stage_data['items'] as $item) {
                    $stmt_insert_item->execute([$inspection_id, $item['item_id'], $item['status'] ?? 'N/A', $item['value'] ?? '']);
                }
            }
            // History logging for batch
            $historyEntry = createHistoryEntry($stage_data, $user_id, $user_role, [], []);
            appendToJsonColumn($pdo, $inspection_id, 'history_log', $historyEntry);
        }
        $processed_count++;
    }

    echo json_encode(['status' => 'success', 'message' => "اطلاعات با موفقیت برای {$processed_count} المان ثبت شد."]);
}

$pdo = getProjectDBConnection('ghom');
$common_pdo = getCommonDBConnection();
// Check if user has a public key in database
if (isset($_SESSION['user_id'])) {
    $pdo = getCommonDBConnection();
    $user_id = $_SESSION['user_id'];
    
    // Debug database connection
    error_log("Database connection status: " . ($pdo ? 'Connected' : 'Not connected'));
    
    // Check for public key in database
   try {
    // Use the common DB connection for the users table
    $stmt = $common_pdo->prepare("SELECT public_key_pem FROM users WHERE id = ?"); // CORRECTED: Checks 'users' table and 'id' column
    $stmt->execute([$user_id]);
    $key_result = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("Public key found for user $user_id: " . ($key_result ? 'YES' : 'NO'));
    if ($key_result && !empty($key_result['public_key_pem'])) {
        error_log("Public key length: " . strlen($key_result['public_key_pem']));
    }
    
} catch (PDOException $e) {
    error_log("Database query error in debug block: " . $e->getMessage());
}
}


error_log("=== PUBLIC KEY DEBUG END ===");
// --- Main execution starts ---
write_log("================== SAVE REQUEST START ==================");
write_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
write_log("POST data keys: " . json_encode(array_keys($_POST)));

// --- Main execution starts ---
write_log("================== SAVE REQUEST START ==================");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['status' => 'error', 'message' => 'Invalid request method.']));
}

if (!isLoggedIn()) {
    http_response_code(403);
    write_log("SAVE FAILED: User not logged in.");
    exit(json_encode(['status' => 'error', 'message' => 'Forbidden']));
}

$request_data = [];
$contentType = trim($_SERVER["CONTENT_TYPE"] ?? '');

if (strpos($contentType, 'application/json') !== false) {
    // This is a JSON request (Batch Save)
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $request_data = $input;
        // Batch save does not typically use CSRF tokens in the same way, or passed in headers
    }
    // Execute Batch Save if detected
    if (isset($request_data['element_ids'])) {
        try {
            handleBatchSave($pdo);
            exit;
        } catch (Exception $e) {
             http_response_code(400);
             exit(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
        }
    }
} else {
    // Standard form-data request
    $request_data = $_POST;
}

// --- CSRF TOKEN VERIFICATION ---
if (!isset($request_data['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $request_data['csrf_token'])) {
    http_response_code(403);
    write_log("SAVE FAILED: Invalid CSRF token.");
    exit(json_encode(['status' => 'error', 'message' => 'Invalid security token. Please refresh the page.']));
}

$fullElementId = $_POST['elementId'] ?? ''; 
$userId = $_SESSION['user_id'];
$requestHash = md5($userId . $fullElementId . ($_POST['digital_signature'] ?? ''));
$duplicateKey = "save_inspection_" . $requestHash;

if (isset($_SESSION[$duplicateKey]) && (time() - $_SESSION[$duplicateKey]) < 10) {
    http_response_code(429);
    exit(json_encode(['status' => 'error', 'message' => 'درخواست تکراری شناسایی شد. لطفا چند ثانیه صبر کنید.']));
}
$_SESSION[$duplicateKey] = time();
// Clean up old duplicate tracking entries (older than 1 minute)
foreach ($_SESSION as $key => $timestamp) {
    if (strpos($key, 'save_inspection_') === 0 && (time() - $timestamp) > 60) {
        unset($_SESSION[$key]);
    }
}

write_log("Request marked as processed to prevent duplicates");

$pdo = null;
$common_pdo = null;

try {
    // Digital Signature Logic
    write_log("Checking digital signature fields...");
    $signature_b64 = $_POST['digital_signature'] ?? null;
    $dataToVerify = $_POST['signed_data'] ?? null;
    $userId = $_SESSION['user_id'];
    
    if (!$signature_b64) { throw new Exception("Digital signature is missing."); }
    if (!$dataToVerify) { throw new Exception("Signed data is missing."); }
    
    $common_pdo = getCommonDBConnection();
    $stmt_key = $common_pdo->prepare("SELECT public_key_pem FROM users WHERE id = ?");
    $stmt_key->execute([$userId]);
    $publicKeyPem = $stmt_key->fetchColumn();
    
    if (!$publicKeyPem) { throw new Exception("Public key not found for user ID {$userId}."); }
    
    $isVerified = false;
    $publicKey = RSA::load($publicKeyPem);
    if ($publicKey instanceof \phpseclib3\Crypt\RSA\PublicKey) {
        $publicKey = $publicKey->withPadding(RSA::SIGNATURE_PKCS1)->withHash('sha256');
        $isVerified = $publicKey->verify($dataToVerify, base64_decode($signature_b64));
    }
    
    if (!$isVerified) {
        http_response_code(403);
        write_log("SAVE FAILED: Invalid digital signature.");
        exit(json_encode(['status' => 'error', 'message' => 'امضای دیجیتال نامعتبر است.']));
    }
    write_log("Digital signature VERIFIED.");

    // Element Processing List
    $elementIdList = [];
    $is_batch = false;

    if (isset($request_data['element_ids'])) {
        $is_batch = true; // Though logic suggests batch is handled above, this handles form-data batch if exists
        $elementIdList = is_array($request_data['element_ids']) ? $request_data['element_ids'] : explode(',', $request_data['element_ids']);
    } elseif (isset($request_data['elementId'])) {
        $elementIdList[] = $request_data['elementId'];
    }

    if (empty($elementIdList)) {
        throw new Exception("No elementId or element_ids provided.");
    }

    $planFile = $_POST['planFile'] ?? '';
    $userRole = $_SESSION['role'];
    $stagesData = json_decode($_POST['stages'] ?? '[]', true);

    if (empty($stagesData)) {
        throw new Exception("No stage data was submitted.");
    }

    $pdo = getProjectDBConnection('ghom');
    $pdo->beginTransaction();

    foreach ($elementIdList as $fullElementId) {
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

        $stmt_find_pre = $pdo->prepare("SELECT * FROM inspections WHERE element_id = ? AND part_name <=> ? AND stage_id = 0 LIMIT 1");
        $stmt_find_pre->execute([$baseElementId, $partName]);
        $pre_inspection_record = $stmt_find_pre->fetch(PDO::FETCH_ASSOC);

        // Upload Handling
        $initialAttachments = $is_batch ? null : ($pre_inspection_record['attachments'] ?? null);
        $initialContractorAttachments = $is_batch ? null : ($pre_inspection_record['contractor_attachments'] ?? null);

        $newAttachmentsJson = $is_batch ? null : processAndMergeUploads('attachments', $fullElementId, $initialAttachments);
        $newContractorAttachmentsJson = $is_batch ? null : processAndMergeUploads('contractor_attachments', $fullElementId, $initialContractorAttachments);

        // ✅ FIXED: Decode JSON to Arrays for use in createHistoryEntry
        $newAttachmentsArray = !empty($newAttachmentsJson) ? json_decode($newAttachmentsJson, true) : [];
        $newContractorAttachmentsArray = !empty($newContractorAttachmentsJson) ? json_decode($newContractorAttachmentsJson, true) : [];

        $isFirstStageInLoop = true;

        foreach ($stagesData as $stageId => $stageData) {
            if (!is_array($stageData)) continue;
            if (isset($stageData['stageId'])) unset($stageData['stageId']);

            $existing_inspection = null;
            $action = '';

            $stmt_find_specific = $pdo->prepare("SELECT * FROM inspections WHERE element_id = ? AND part_name <=> ? AND stage_id = ? LIMIT 1");
            $stmt_find_specific->execute([$baseElementId, $partName, $stageId]);
            $specific_record = $stmt_find_specific->fetch(PDO::FETCH_ASSOC);

            if ($specific_record) {
                $action = 'UPDATE';
                $existing_inspection = $specific_record;
            } else if ($isFirstStageInLoop && $pre_inspection_record) {
                $action = 'UPDATE';
                $existing_inspection = $pre_inspection_record;
            } else {
                $action = 'INSERT';
            }
            $isFirstStageInLoop = false;

            if ($elementType === 'GFRC' && !$existing_inspection && $action === 'UPDATE') {
                 // Fallback to insert if pre-inspection missing for GFRC logic (prevent crash)
                 $action = 'INSERT';
            }

            if ($action === 'UPDATE') {
                $inspectionId = $existing_inspection['inspection_id'];
                $params = $existing_inspection;
                unset($params['inspection_id'], $params['created_at']);
                
                $params['digital_signature'] = $signature_b64;
                $params['signed_data'] = $dataToVerify;
                $params['stage_id'] = $stageId;

                // Status Logic
                if (in_array($userRole, ['admin', 'superuser']) && isset($stageData['overall_status'])) {
                    $currentRejectionCount = (int)$params['repair_rejection_count'];
                    $newOverallStatus = $stageData['overall_status'];
                    if ($newOverallStatus === 'Repair' && $params['contractor_status'] === 'Awaiting Re-inspection') {
                        $currentRejectionCount++;
                    }
                    if ($currentRejectionCount >= 3) {
                        $stageData['overall_status'] = 'Reject';
                    }
                    if ($newOverallStatus === 'OK') {
                        $currentRejectionCount = 0;
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

            } else { // INSERT
                $inspectionId = null;
                $pre_log_to_use = '[]';
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

                $params = [
                    'element_id' => $baseElementId,
                    'part_name' => $partName,
                    'stage_id' => $stageId,
                    'user_id' => $userId,
                    'digital_signature' => $signature_b64,
                    'signed_data' => $dataToVerify,
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
                    if (isset($stageData['inspection_date'])) $params['inspection_date'] = toGregorian($stageData['inspection_date']);
                    if (isset($stageData['notes'])) $params['notes'] = $stageData['notes'];
                }
                if (in_array($userRole, ['cat', 'car', 'coa', 'crs']) || $userRole === 'superuser') {
                    if (isset($stageData['contractor_status'])) $params['contractor_status'] = $stageData['contractor_status'];
                    if (isset($stageData['contractor_date'])) $params['contractor_date'] = toGregorian($stageData['contractor_date']);
                    if (isset($stageData['contractor_notes'])) $params['contractor_notes'] = $stageData['contractor_notes'];
                }

                // ✅ FIXED: Pass defined arrays
                $historyEntry = createHistoryEntry($stageData, $userId, $userRole, $newAttachmentsArray, $newContractorAttachmentsArray);
                // ✅ FIXED: Explicitly set history_log in INSERT params
                $params['history_log'] = json_encode([$historyEntry], JSON_UNESCAPED_UNICODE);

                $columns = implode(', ', array_map(fn($c) => "`$c`", array_keys($params)));
                $placeholders = ':' . implode(', :', array_keys($params));
                $sql = "INSERT INTO inspections ($columns) VALUES ($placeholders)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $inspectionId = $pdo->lastInsertId();
            }

            // Trigger Notifications
            if ($inspectionId) {
                $event_type = null;
                $f_consult = $params['overall_status'] ?? null;
                $f_contract = $params['contractor_status'] ?? null;

                if (in_array($userRole, ['admin', 'superuser'])) {
                    if ($f_consult === 'Repair') $event_type = 'REPAIR_REQUESTED';
                    elseif ($f_consult === 'OK') $event_type = 'INSPECTION_OK';
                    elseif ($f_consult === 'Reject') $event_type = 'INSPECTION_REJECT';
                } elseif (in_array($userRole, ['cat', 'car', 'coa', 'crs'])) {
                    if ($f_contract === 'Awaiting Re-inspection') {
                        $prev_status = $existing_inspection['overall_status'] ?? null;
                        $event_type = ($prev_status === 'Repair') ? 'REPAIR_DONE' : 'INSPECTION_READY';
                    }
                }

                if ($event_type && !empty($planFile)) {
                    $stmt_s = $pdo->prepare("SELECT stage FROM inspection_stages WHERE stage_id = ?");
                    $stmt_s->execute([$stageId]);
                    $stage_name = $stmt_s->fetchColumn();
                    $r_notes = (in_array($userRole, ['admin', 'superuser'])) ? ($stageData['notes'] ?? '') : ($stageData['contractor_notes'] ?? '');
                    
                    trigger_workflow_task($pdo, $baseElementId, $partName, $planFile, $event_type, $userId, null, $r_notes, $stageId, $stage_name);
                }
            }

            // Update Checklist Items
            if ($inspectionId && isset($stageData['items']) && in_array($userRole, ['admin', 'superuser'])) {
                 $pdo->prepare("DELETE FROM inspection_data WHERE inspection_id = ?")->execute([$inspectionId]);
                 $stmt_item = $pdo->prepare("INSERT INTO inspection_data (inspection_id, item_id, item_status, item_value) VALUES (?, ?, ?, ?)");
                 foreach ($stageData['items'] as $item) {
                     $stmt_item->execute([$inspectionId, $item['item_id'], $item['status'] ?? 'N/A', $item['value'] ?? '']);
                 }
            }
        }
    }

    $pdo->commit();
    ob_end_clean();
    $successMessage = "اطلاعات با موفقیت برای " . count($elementIdList) . " المان ذخیره شد.";
    echo json_encode(['status' => 'success', 'message' => $successMessage]);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    write_log("FATAL ERROR: " . $e->getMessage());
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'خطای داخلی سرور رخ داد.', 'details' => $e->getMessage()]);
}