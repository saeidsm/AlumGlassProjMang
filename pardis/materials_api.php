<?php
// public_html/pardis/materials_api.php

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { /* ... error handling ... */ }

$user_id = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['name'] ?? 'Unknown';
$user_role = $_SESSION['role'] ?? 'user';
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getProjectDBConnection('pardis');

switch ($action) {
    case 'add_material_log':
        echo json_encode(addMaterialLog($pdo, $user_id, $user_name));
        break;
    case 'get_material_logs':
        echo json_encode(getMaterialLogs($pdo));
        break;
    case 'update_qc_status':
        echo json_encode(updateQcStatus($pdo, $user_name, $user_role));
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function addMaterialLog($pdo, $user_id, $user_name) {
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => 'Invalid request method'];
    }

    try {
        $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "INSERT INTO material_log (packing_list_no, receipt_date, supplier_company, entry_type, usage_type, storage_location, vehicle_type, vehicle_plate, driver_name, notes, created_by_id, created_by_name) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $receipt_date = toGregorian($_POST['receipt_date']) ?? date('Y-m-d');
    $stmt->execute([
        $_POST['packing_list_no'], $receipt_date, $_POST['supplier_company'],
        $_POST['entry_type'], $_POST['usage_type'], $_POST['storage_location'],
        $_POST['vehicle_type'], $_POST['vehicle_plate'], $_POST['driver_name'],
        $_POST['notes'], $user_id, $user_name
    ]);
    $log_id = $pdo->lastInsertId();

    // Now update the items INSERT to include the new detailed fields
    if (!empty($_POST['items']) && is_array($_POST['items'])) {
        $item_stmt = $pdo->prepare(
            "INSERT INTO material_log_items (log_id, material_name, unit_of_measure, quantity, package_number, dimensions, weight, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($_POST['items'] as $item) {
            if (!empty($item['material_name']) && !empty($item['quantity'])) {
                $item_stmt->execute([
                    $log_id, $item['material_name'], $item['unit'], $item['quantity'],
                    $item['package_no'], $item['dimensions'], $item['weight'] ?: null, $item['remarks']
                ]);
            }
        }
    }
    if (!empty($_FILES['attachments'])) {
            $upload_dir = __DIR__ . '/uploads/materials/' . date('Y/m/');
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $att_stmt = $pdo->prepare(
                "INSERT INTO material_log_attachments (log_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)"
            );
            
            foreach ($_FILES['attachments']['name'] as $key => $name) {
                if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['attachments']['tmp_name'][$key];
                    $unique_name = uniqid() . '-' . basename($name);
                    $file_path = $upload_dir . $unique_name;

                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $relative_path = 'uploads/materials/' . date('Y/m/') . $unique_name;
                        $att_stmt->execute([
                            $log_id,
                            $name,
                            $relative_path,
                            $_FILES['attachments']['type'][$key],
                            $_FILES['attachments']['size'][$key]
                        ]);
                    }
                }
            }
        }

        $pdo->commit();
        return ['success' => true, 'message' => 'رسید مواد با موفقیت ثبت شد.', 'log_id' => $log_id];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("Error in addMaterialLog: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database Error: ' . $e->getMessage()];
    }
}

function getMaterialLogs($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                ml.*,
                GROUP_CONCAT(DISTINCT CONCAT(mli.quantity, ' ', mli.unit_of_measure, ' ', mli.material_name) SEPARATOR ';') as items_summary,
                GROUP_CONCAT(DISTINCT mla.file_path SEPARATOR ';') as attachment_paths,
                GROUP_CONCAT(DISTINCT mla.file_name SEPARATOR ';') as attachment_names
            FROM material_log ml
            LEFT JOIN material_log_items mli ON ml.id = mli.log_id
            LEFT JOIN material_log_attachments mla ON ml.id = mla.log_id
            GROUP BY ml.id
            ORDER BY ml.receipt_date DESC, ml.id DESC
        ");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'logs' => $logs];
    } catch (Exception $e) {
        logError("Error in getMaterialLogs: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database Error: ' . $e->getMessage()];
    }
}

function updateQcStatus($pdo, $inspector_name, $user_role) {
    // Security check: Only admins can perform QC
    if (!in_array($user_role, ['admin', 'superuser', 'coa'])) {
        return ['success' => false, 'message' => 'Access Denied'];
    }

    $log_id = $_POST['log_id'] ?? 0;
    $status = $_POST['qc_status'] ?? '';
    $notes = $_POST['qc_notes'] ?? '';

    if (empty($log_id) || empty($status)) {
        return ['success' => false, 'message' => 'Invalid input'];
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE material_log SET qc_status = ?, qc_notes = ?, qc_inspector_name = ? WHERE id = ?"
        );
        $stmt->execute([$status, $notes, $inspector_name, $log_id]);
        return ['success' => true, 'message' => 'وضعیت کنترل کیفی به‌روز شد.'];
    } catch (Exception $e) {
        logError("Error in updateQcStatus: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database Error'];
    }
}