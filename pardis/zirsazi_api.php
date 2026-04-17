<?php
// public_html/pardis/zirsazi_api.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
require_once __DIR__ . '/includes/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

secureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

$user_role = $_SESSION['role'] ?? 'user';
$user_id = $_SESSION['user_id'] ?? 0;
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = getProjectDBConnection('pardis');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Helper function for Jalali conversion
function toGregorian($jalaliDate)
{
    if (empty($jalaliDate) || !is_string($jalaliDate)) {
        return null;
    }
    $parts = array_map('intval', preg_split('/[-\/]/', trim($jalaliDate)));
    if (count($parts) !== 3 || $parts[0] < 1300) {
        return null;
    }
    if (function_exists('jalali_to_gregorian')) {
        $g = jalali_to_gregorian($parts[0], $parts[1], $parts[2]);
        return $g[0] . '-' . str_pad($g[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($g[2], 2, '0', STR_PAD_LEFT);
    }
    return null;
}

function toJalali($gregorian_date_time) {
    if (empty($gregorian_date_time) || strpos($gregorian_date_time, '0000-00-00') === 0) {
        return '-';
    }
    
    $parts = explode(' ', $gregorian_date_time);
    $date_part = $parts[0];
    $time_part = isset($parts[1]) ? substr($parts[1], 0, 5) : null;

    $date_parts = explode('-', $date_part);
    if (count($date_parts) !== 3 || (int)$date_parts[0] < 1900) {
        return $gregorian_date_time;
    }
    
    list($y, $m, $d) = $date_parts;
    
    if (function_exists('gregorian_to_jalali')) {
        $j = gregorian_to_jalali((int)$y, (int)$m, (int)$d);
        $jalali_date = $j[0] . '/' . str_pad($j[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($j[2], 2, '0', STR_PAD_LEFT);
        return $time_part ? $jalali_date . ' ' . $time_part : $jalali_date;
    }
    
    return $gregorian_date_time;
}


function normalizeMaterialType($material) {
    if (empty($material)) return '';
    
    // Remove extra whitespace
    $material = preg_replace('/\s+/', ' ', trim($material));
    
    // Common patterns to fix
    $patterns = [
        // Fix BR patterns: BR1 -> BR-01, BR 1 -> BR-01, etc.
        '/\bBR\s*(\d{1,2})\b/i' => function($matches) {
            return 'BR-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        },
        // Fix other letter-number patterns: AB1 -> AB-01
        '/\b([A-Z]{1,3})\s*(\d{1,2})\b/i' => function($matches) {
            return strtoupper($matches[1]) . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        },
        // Fix multiple dashes: BR--01 -> BR-01
        '/\-{2,}/' => '-',
        // Fix space before dash: BR -01 -> BR-01
        '/\s+\-/' => '-',
        // Fix space after dash: BR- 01 -> BR-01
        '/\-\s+/' => '-',
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        if (is_callable($replacement)) {
            $material = preg_replace_callback($pattern, $replacement, $material);
        } else {
            $material = preg_replace($pattern, $replacement, $material);
        }
    }
    
    return trim($material);
}

// NEW: Find best match for material type
function findBestMaterialMatch($input, $valid_materials) {
    $normalized_input = normalizeMaterialType($input);
    $input_lower = mb_strtolower($normalized_input, 'UTF-8');
    
    // Check for exact match first (case-insensitive)
    foreach ($valid_materials as $key => $original) {
        if ($key === $input_lower) {
            return ['matched' => true, 'original' => $original, 'normalized' => $normalized_input];
        }
    }
    
    // Check for fuzzy match after normalization
    foreach ($valid_materials as $key => $original) {
        $normalized_valid = mb_strtolower(normalizeMaterialType($original), 'UTF-8');
        if ($normalized_valid === $input_lower) {
            return ['matched' => true, 'original' => $original, 'normalized' => $normalized_input];
        }
    }
    
    // No match found
    return ['matched' => false, 'normalized' => $normalized_input];
}

// Helper function to handle file uploads
function handleFileUpload($file, $upload_dir = 'uploads/packing_docs/') {
    // Debug logging
    error_log("=== handleFileUpload Start ===");
    error_log("File received: " . print_r($file, true));
    error_log("Target dir: " . $upload_dir);
    
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $error_msg = 'Upload error code: ' . ($file['error'] ?? 'not set');
        error_log("Upload failed: " . $error_msg);
        return ['success' => false, 'path' => null, 'type' => null, 'message' => $error_msg];
    }

    // Validate file size (10MB max)
    if ($file['size'] > 10 * 1024 * 1024) {
        error_log("File too large: " . $file['size']);
        return ['success' => false, 'message' => 'حجم فایل نباید بیشتر از 10 مگابایت باشد'];
    }

    // Get file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];

    if (!in_array($file_extension, $allowed_extensions)) {
        error_log("Invalid extension: " . $file_extension);
        return ['success' => false, 'message' => 'فرمت فایل مجاز نیست'];
    }

    // Create upload directory if it doesn't exist
    $full_upload_dir = __DIR__ . '/' . $upload_dir;
    error_log("Full upload dir: " . $full_upload_dir);
    
    if (!is_dir($full_upload_dir)) {
        error_log("Creating directory...");
        if (!mkdir($full_upload_dir, 0755, true)) {
            error_log("Failed to create directory");
            return ['success' => false, 'message' => 'خطا در ایجاد پوشه آپلود'];
        }
    }

    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $full_path = $full_upload_dir . $filename;
    $relative_path = $upload_dir . $filename;
    
    error_log("Attempting to move file from: " . $file['tmp_name']);
    error_log("To: " . $full_path);

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $full_path)) {
        error_log("File moved successfully!");
        error_log("File exists: " . (file_exists($full_path) ? 'YES' : 'NO'));
        error_log("File size: " . (file_exists($full_path) ? filesize($full_path) : 'N/A'));
        
        $document_type = ($file_extension === 'pdf') ? 'pdf' : 'image';
        return [
            'success' => true,
            'path' => $relative_path,
            'type' => $document_type
        ];
    }

    error_log("move_uploaded_file FAILED!");
    error_log("Source exists: " . (file_exists($file['tmp_name']) ? 'YES' : 'NO'));
    error_log("Destination writable: " . (is_writable($full_upload_dir) ? 'YES' : 'NO'));
    return ['success' => false, 'message' => 'خطا در آپلود فایل'];
}

switch ($action) {
    case 'get_zirsazi_data':
        echo json_encode(getZirsaziData($pdo));
        break;
    case 'update_boq_item':
        if (!in_array($user_role, ['admin','superuser','supervisor'])) { 
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            break; 
        }
        echo json_encode(updateBoqItem($pdo, $_POST));
        break;
    case 'add_material':
        if (!in_array($user_role, ['admin','superuser','supervisor'])) { 
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            break; 
        }
        echo json_encode(addMaterial($pdo, $_POST, $_FILES));
        break;
    case 'get_project_locations':
        echo json_encode(getProjectLocations($pdo));
        break;
    case 'get_warehouses':
        echo json_encode(getWarehouses($pdo));
        break;
    case 'add_packing_list':
        if (!in_array($user_role, ['admin','superuser','user','supervisor'])) { 
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            break; 
        }
        echo json_encode(addPackingList($pdo, $_POST, $_FILES, $user_id));
        break;
    case 'add_bulk_packing':
        if (!in_array($user_role, ['admin','superuser','user','supervisor'])) { 
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            break; 
        }
        echo json_encode(addBulkPacking($pdo, $_POST, $_FILES, $user_id));
        break;
    case 'get_packing_lists':
        echo json_encode(getPackingLists($pdo, $_GET));
        break;
    case 'delete_packing':
        if (!in_array($user_role, ['admin','superuser','supervisor'])) { 
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            break; 
        }
        echo json_encode(deletePacking($pdo, $_POST));
        break;
    case 'add_warehouse_transaction':
        if (!in_array($user_role, ['admin','superuser','user','supervisor'])) { 
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            break; 
        }
        echo json_encode(addWarehouseTransaction($pdo, $_POST, $user_id));
        break;
    case 'get_warehouse_inventory':
        echo json_encode(getWarehouseInventory($pdo, $_GET));
        break;
   case 'get_warehouse_transactions':
        echo json_encode(getWarehouseTransactions($pdo, $_GET));
        break;
    case 'get_item_documents':
        echo json_encode(getItemDocuments($pdo, $_GET['item_id'], $_GET['item_type']));
        break;
        
    case 'delete_document':
        if (!in_array($user_role, ['admin','superuser','supervisor'])) { 
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            break; 
        }
        echo json_encode(deleteDocument($pdo, $_POST['document_id'], $user_role));
        break;
    // NEW ACTION for bulk exit
    case 'add_bulk_warehouse_transaction':
        if (!in_array($user_role, ['admin','superuser','user','supervisor'])) { 
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            break; 
        }
        echo json_encode(addBulkWarehouseTransaction($pdo, $_POST, $_FILES, $user_id));
        break;


    case 'debug_materials':
    echo json_encode(debugMaterialMatching($pdo));
    break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleMultipleFileUploads($files, $item_id, $item_type, $user_id, $pdo) {
    $uploaded_files = [];
    $errors = [];
    
    if (!isset($files['documents'])) {
        return ['success' => true, 'files' => []];
    }
    
    // Handle both single and multiple file uploads
    $file_count = is_array($files['documents']['name']) ? count($files['documents']['name']) : 1;
    
    for ($i = 0; $i < $file_count; $i++) {
        if (is_array($files['documents']['name'])) {
            $file = [
                'name' => $files['documents']['name'][$i],
                'type' => $files['documents']['type'][$i],
                'tmp_name' => $files['documents']['tmp_name'][$i],
                'error' => $files['documents']['error'][$i],
                'size' => $files['documents']['size'][$i]
            ];
        } else {
            $file = $files['documents'];
        }
        
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "خطا در آپلود فایل: {$file['name']}";
            continue;
        }
        
        // Validate file
        if ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = "فایل {$file['name']} بیش از 10 مگابایت است";
            continue;
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "فرمت فایل {$file['name']} مجاز نیست";
            continue;
        }
        
        // Create upload directory
        $upload_dir = __DIR__ . '/uploads/packing_docs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $filename = uniqid() . '_' . time() . '.' . $file_extension;
        $full_path = $upload_dir . $filename;
        $relative_path = 'uploads/packing_docs/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            $document_type = ($file_extension === 'pdf') ? 'pdf' : 'image';
            
            // Insert into database
            try {
                $sql = "INSERT INTO packing_documents 
                        (item_id, item_type, document_name, document_path, document_type, file_size, uploaded_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $item_id,
                    $item_type,
                    $file['name'],
                    $relative_path,
                    $document_type,
                    $file['size'],
                    $user_id
                ]);
                
                $uploaded_files[] = [
                    'id' => $pdo->lastInsertId(),
                    'name' => $file['name'],
                    'path' => $relative_path,
                    'type' => $document_type,
                    'size' => $file['size']
                ];
            } catch (Exception $e) {
                $errors[] = "خطا در ذخیره اطلاعات فایل {$file['name']}: " . $e->getMessage();
                @unlink($full_path);
            }
        } else {
            $errors[] = "خطا در آپلود فایل {$file['name']}";
        }
    }
    
    return [
        'success' => empty($errors),
        'files' => $uploaded_files,
        'errors' => $errors
    ];
}


function addBulkWarehouseTransaction($pdo, $data, $files, $user_id) {
    // 1. Basic Validation (unchanged)
    if (!isset($files['excel_file']) || $files['excel_file']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'فایل اکسل آپلود نشده است'];
    }
    if (empty($data['warehouse_id']) || empty($data['transaction_date'])) {
        return ['success' => false, 'message' => 'انبار و تاریخ تراکنش الزامی است'];
    }

    try {
        // --- START OF CORRECTION ---
        $document_html_link = '';
        if (isset($files['document_file']) && $files['document_file']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handleFileUpload($files['document_file'], 'uploads/exit_docs/');
            if ($upload_result['success']) {
                // Build the full URL path from the web root
                $full_url = '/pardis/' . $upload_result['path'];
                // Create a full, clickable HTML <a> tag
                $document_html_link = '<a href="' . htmlspecialchars($full_url) . '" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-file-earmark-text"></i> مشاهده سند</a>';
            } else {
                throw new Exception($upload_result['message'] ?? 'خطا در آپلود سند');
            }
        }
        
        // Sanitize user-provided notes to prevent XSS
        $final_notes = !empty($data['notes']) ? htmlspecialchars(trim($data['notes']), ENT_QUOTES, 'UTF-8') : '';

        // Combine the user notes with the generated HTML link
        if (!empty($document_html_link)) {
            // Use <br> for line breaks as this will be rendered as HTML
            $final_notes = $final_notes . (!empty($final_notes) ? '<br><br>' : '') . $document_html_link;
        }
        // 2. Load Excel File and process data (unchanged)
        $spreadsheet = IOFactory::load($files['excel_file']['tmp_name']);
         $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        $data_start_row = 1;
        $requested_items = [];
        for ($i = $data_start_row; $i < count($rows); $i++) {
            $row = $rows[$i];
            $material_type = isset($row[1]) ? trim($row[1]) : '';
            $quantity = isset($row[2]) ? floatval(str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], ['0','1','2','3','4','5','6','7','8','9'], $row[2])) : 0;
            if (!empty($material_type) && $quantity > 0) {
                $requested_items[$material_type] = ($requested_items[$material_type] ?? 0) + $quantity;
            }
        }

        if (empty($requested_items)) {
            return ['success' => false, 'message' => 'هیچ داده معتبری در فایل اکسل یافت نشد.'];
        }

        // 3. Stock Validation (Unchanged)
        $errors = [];
        $placeholders = rtrim(str_repeat('?,', count($requested_items)), ',');
        $sql_stock = "SELECT material_type, current_stock FROM warehouse_inventory WHERE warehouse_id = ? AND material_type IN ($placeholders)";
        
        $stmt_stock = $pdo->prepare($sql_stock);
        $stmt_stock->execute(array_merge([$data['warehouse_id']], array_keys($requested_items)));
        $current_stock = $stmt_stock->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($requested_items as $material => $requested_qty) {
            $stock = floatval($current_stock[$material] ?? 0);
            if (!isset($current_stock[$material])) {
                $errors[] = "ماده <strong>'${material}'</strong> در انبار یافت نشد.";
            } elseif ($stock < $requested_qty) {
                $errors[] = "موجودی ناکافی برای <strong>'${material}'</strong> (درخواست: ${requested_qty}, موجودی: ${stock})";
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'message' => "خطا در اعتبارسنجی موجودی:", 'errors' => $errors];
        }

        // 4. Database Insertion
         $pdo->beginTransaction();
        $count = 0;
        $sql_insert = "INSERT INTO warehouse_transactions 
                (warehouse_id, material_type, transaction_type, quantity, transaction_date, contractor_name, project_location_id, notes, created_by)
                VALUES (?, ?, 'out', ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $pdo->prepare($sql_insert);

        foreach ($requested_items as $material => $quantity) {
             $stmt_insert->execute([
                $data['warehouse_id'],
                $material,
                $quantity,
                $data['transaction_date'],
                !empty($data['contractor_name']) ? $data['contractor_name'] : null,
                !empty($data['project_location_id']) ? $data['project_location_id'] : null,
                $final_notes, // This now contains the proper HTML
                $user_id
            ]);
            $count++;
        }

        $pdo->commit();
        return ['success' => true, 'count' => $count];

    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'خطا: ' . $e->getMessage()];
    }
}


function debugMaterialMatching($pdo) {
    try {
        // Get all material types from packing lists
        $packing_sql = "SELECT DISTINCT TRIM(material_type) as material_type FROM packing_lists ORDER BY material_type";
        $packing_materials = $pdo->query($packing_sql)->fetchAll(PDO::FETCH_COLUMN);
        
        // Get all types from BOQ
        $boq_sql = "SELECT DISTINCT TRIM(type) as type FROM zirsazi_boq ORDER BY type";
        $boq_types = $pdo->query($boq_sql)->fetchAll(PDO::FETCH_COLUMN);
        
        return [
            'success' => true,
            'packing_materials' => $packing_materials,
            'boq_types' => $boq_types,
            'note' => 'Compare these lists to find mismatches'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getZirsaziData($pdo) {
    try {
        // 1. Get "Total Received" - This is the total intake for the project from packing lists. It is static.
        $received_sql = "
            SELECT TRIM(material_type) as material_type, SUM(quantity) as total_received
            FROM packing_lists GROUP BY TRIM(material_type)
        ";
        $received_result = $pdo->query($received_sql)->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // 2. NEW: Get "Warehouse Stock" - This is the current, dynamic inventory level.
        $stock_sql = "SELECT material_type, current_stock FROM warehouse_inventory";
        $stock_result = $pdo->query($stock_sql)->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Create case-insensitive lookup arrays
        $received_totals = array_change_key_case($received_result, CASE_LOWER);
        $warehouse_stocks = array_change_key_case($stock_result, CASE_LOWER);

        // 3. Get BOQ data
        $boq_sql = "
            SELECT zb.*, mm.unit_weight, pl_b.building_name as building, pl_p.part_name as part
            FROM zirsazi_boq zb
            LEFT JOIN materials_master mm ON TRIM(zb.type) = TRIM(mm.item_name) AND mm.category = 'زیرسازی'
            LEFT JOIN project_locations pl_b ON zb.building_id = pl_b.id
            LEFT JOIN project_locations pl_p ON zb.part_id = pl_p.id
            ORDER BY zb.model, zb.type
        ";
        $boq_items = $pdo->query($boq_sql)->fetchAll(PDO::FETCH_ASSOC);

        // 4. Combine all data
        foreach ($boq_items as &$item) {
            $boq_type_lower = mb_strtolower(trim($item['type']), 'UTF-8');
            
            // Assign "Total Received" (static)
            $item['total_received'] = floatval($received_totals[$boq_type_lower] ?? 0);
            
            // Assign "Warehouse Stock" (dynamic)
            $item['warehouse_stock'] = floatval($warehouse_stocks[$boq_type_lower] ?? 0);
        }

        return ['success' => true, 'data' => $boq_items];
    } catch (Exception $e) {
        error_log("Error in getZirsaziData: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}


function updateBoqItem($pdo, $data) {
    try {
        $pdo->beginTransaction();

        // UPDATED: We now only care about the new p1 and p2 values.
        $part1_qty = $data['p1'] ?? 0;
        $part2_qty = $data['p2'] ?? 0;
        
        // REMOVED: We no longer calculate a combined sum or touch the old part1_2_qty column.

        $building_id = !empty($data['building_id']) ? intval($data['building_id']) : null;
        $part_id = !empty($data['part_id']) ? intval($data['part_id']) : null;

        // UPDATED: The SQL statement now only updates the new columns and leaves part1_2_qty alone.
        $sql = "UPDATE zirsazi_boq SET 
                part1_qty=?, part2_qty=?,
                part3_qty=?, part4_qty=?, part5_qty=?, part6_qty=?,
                building_id=?, part_id=?
                WHERE id=?";
        
        $stmt = $pdo->prepare($sql);
        
        // UPDATED: The execute array matches the new SQL statement.
        $stmt->execute([
            $part1_qty,
            $part2_qty,
            $data['p3'] ?? 0, 
            $data['p4'] ?? 0, 
            $data['p5'] ?? 0, 
            $data['p6'] ?? 0,
            $building_id,
            $part_id,
            $data['id']
        ]);

        if (isset($data['weight'])) {
            $type_sql = "SELECT type FROM zirsazi_boq WHERE id = ?";
            $type_stmt = $pdo->prepare($type_sql);
            $type_stmt->execute([$data['id']]);
            $material_type = $type_stmt->fetchColumn();
            
            if ($material_type) {
                $sql_weight = "
                    INSERT INTO materials_master (item_name, category, unit_weight)
                    VALUES (?, 'زیرسازی', ?)
                    ON DUPLICATE KEY UPDATE unit_weight = VALUES(unit_weight)
                ";
                $stmt_weight = $pdo->prepare($sql_weight);
                $stmt_weight->execute([$material_type, floatval($data['weight'])]);
            }
        }

        $pdo->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}


function addMaterial($pdo, $data, $files) {
    try {
        $pdo->beginTransaction();

        $part1_qty = $data['p1'] ?? 0;
        $part2_qty = $data['p2'] ?? 0;
        // REMOVED: No longer calculating a combined sum. The old part1_2_qty will be 0 for new materials.

        $building_id = !empty($data['building_id']) ? intval($data['building_id']) : null;
        $part_id = !empty($data['part_id']) ? intval($data['part_id']) : null;

        $sql_master = "
            INSERT INTO materials_master (item_name, category, unit_weight)
            VALUES (?, 'زیرسازی', ?)
            ON DUPLICATE KEY UPDATE unit_weight = VALUES(unit_weight)
        ";
        $stmt_master = $pdo->prepare($sql_master);
        $stmt_master->execute([trim($data['type']), floatval($data['weight'] ?? 0)]);
        
        // UPDATED: The INSERT statement now includes the new columns. part1_2_qty is included to be set to 0.
        $sql_boq = "INSERT INTO zirsazi_boq 
                    (model, type, part1_qty, part2_qty, part3_qty, part4_qty, part5_qty, part6_qty, building_id, part_id, part1_2_qty) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"; // Set legacy column to 0 for new entries
        
        $stmt_boq = $pdo->prepare($sql_boq);
        
        $stmt_boq->execute([
            trim($data['model']),
            trim($data['type']),
            $part1_qty,
            $part2_qty,
            $data['p3'] ?? 0,
            $data['p4'] ?? 0,
            $data['p5'] ?? 0,
            $data['p6'] ?? 0,
            $building_id,
            $part_id
        ]);

        $pdo->commit();
        return ['success' => true, 'id' => $pdo->lastInsertId()];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}


function getProjectLocations($pdo) {
    try {
        // Get a unique list of buildings, each with one of its associated IDs.
        $sql_buildings = "
            SELECT MIN(id) as id, building_name 
            FROM project_locations 
            WHERE building_name IS NOT NULL AND building_name != '' 
            GROUP BY building_name 
            ORDER BY building_name
        ";
        $buildings = $pdo->query($sql_buildings)->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all parts for filtering on the client-side.
        $sql_parts = "SELECT id, building_name, part_name FROM project_locations ORDER BY building_name, part_name";
        $parts = $pdo->query($sql_parts)->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'buildings' => $buildings, 'parts' => $parts];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}


function getWarehouses($pdo) {
    try {
        $sql = "SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name";
        $warehouses = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => $warehouses];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function addPackingList($pdo, $data, $files, $user_id) {
    try {
        $pdo->beginTransaction();

        // Insert packing list
        $sql = "INSERT INTO packing_lists 
                (packing_number, material_type, received_date, quantity, supplier, notes, warehouse_id, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['packing_number'],
            $data['material_type'],
            $data['received_date'],
            $data['quantity'],
            !empty($data['supplier']) ? $data['supplier'] : null,
            !empty($data['notes']) ? $data['notes'] : null,
            !empty($data['warehouse_id']) ? $data['warehouse_id'] : null,
            $user_id
        ]);
        $packing_id = $pdo->lastInsertId();

        // Handle multiple document uploads
        $upload_result = handleMultipleFileUploads($files, $packing_id, 'packing_list', $user_id, $pdo);
        
        // Add warehouse transaction if warehouse is selected
        if (!empty($data['warehouse_id'])) {
            $trans_sql = "INSERT INTO warehouse_transactions 
                         (warehouse_id, material_type, transaction_type, quantity, transaction_date, packing_list_id, created_by)
                         VALUES (?, ?, 'in', ?, ?, ?, ?)";
            $trans_stmt = $pdo->prepare($trans_sql);
            $trans_stmt->execute([
                $data['warehouse_id'],
                $data['material_type'],
                $data['quantity'],
                $data['received_date'] . ' ' . date('H:i:s'),
                $packing_id,
                $user_id
            ]);
        }

        $pdo->commit();
        
        return [
            'success' => true,
            'id' => $packing_id,
            'uploaded_files' => $upload_result['files'],
            'upload_errors' => $upload_result['errors'] ?? []
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Add new function to get documents for an item
function getItemDocuments($pdo, $item_id, $item_type) {
    try {
        $sql = "SELECT * FROM packing_documents 
                WHERE item_id = ? AND item_type = ? 
                ORDER BY upload_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$item_id, $item_type]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'data' => $documents];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Add new function to delete a document
function deleteDocument($pdo, $document_id, $user_role) {
    try {
        if (!in_array($user_role, ['admin', 'superuser', 'supervisor'])) {
            return ['success' => false, 'message' => 'دسترسی غیرمجاز'];
        }
        
        $pdo->beginTransaction();
        
        // Get document info
        $sql = "SELECT document_path FROM packing_documents WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$document_id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$doc) {
            return ['success' => false, 'message' => 'سند یافت نشد'];
        }
        
        // Delete from database
        $sql = "DELETE FROM packing_documents WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$document_id]);
        
        // Delete physical file
        $file_path = __DIR__ . '/' . $doc['document_path'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
        
        $pdo->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}




function addBulkPacking($pdo, $data, $files, $user_id) {
    error_log("=== addBulkPacking Start ===");
    
    try {
        if (!isset($files['excel_file']) || $files['excel_file']['error'] !== UPLOAD_ERR_OK) {
            $error_code = $files['excel_file']['error'] ?? 'not set';
            error_log("Excel file not received or error: " . $error_code);
            return ['success' => false, 'message' => 'فایل اکسل آپلود نشده است'];
        }

        error_log("Loading Excel file...");
        $spreadsheet = IOFactory::load($files['excel_file']['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        error_log("Excel loaded, total rows: " . count($rows));

        // Get valid materials from BOQ
        $boq_sql = "SELECT DISTINCT TRIM(type) as type FROM zirsazi_boq WHERE type IS NOT NULL AND type != ''";
        $valid_materials_result = $pdo->query($boq_sql)->fetchAll(PDO::FETCH_COLUMN);
        
        $valid_materials = [];
        foreach ($valid_materials_result as $material) {
            $key = mb_strtolower(normalizeMaterialType($material), 'UTF-8');
            $valid_materials[$key] = trim($material);
        }
        
        error_log("Valid materials from BOQ: " . count($valid_materials));

        $data_start_row = 3;
        for ($i = 0; $i < min(10, count($rows)); $i++) {
            $row_str = implode('|', array_map('strval', $rows[$i]));
            if (stripos($row_str, 'شرح') !== false || stripos($row_str, 'تعداد') !== false) {
                $data_start_row = $i + 1;
                error_log("Found header at row $i, data starts at row $data_start_row");
                break;
            }
        }

        // VALIDATION PASS with typo correction
        $invalid_materials = [];
        $corrected_materials = [];
        $valid_rows = [];
        
        for ($i = $data_start_row; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            $material_type_raw = isset($row[1]) ? trim(strval($row[1])) : '';
            $quantity_raw = isset($row[2]) ? trim(strval($row[2])) : '';
            
            if (empty($material_type_raw) || empty($quantity_raw)) {
                continue;
            }
            
            // Convert Persian/Arabic numbers
            $quantity_raw = str_replace(
                ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
                ['0','1','2','3','4','5','6','7','8','9'],
                $quantity_raw
            );
            
            $quantity_raw = preg_replace('/[^\d.]/', '', $quantity_raw);
            $quantity = floatval($quantity_raw);
            
            if ($quantity <= 0) {
                error_log("Row " . ($i + 1) . ": Skipping - invalid quantity: $quantity_raw");
                continue;
            }
            
            // Try to find match with typo correction
            $match_result = findBestMaterialMatch($material_type_raw, $valid_materials);
            
            if ($match_result['matched']) {
                $valid_rows[] = [
                    'excel_row' => $i + 1,
                    'material' => $match_result['original'],
                    'quantity' => $quantity
                ];
                
                // Track if correction was made
                if ($material_type_raw !== $match_result['original']) {
                    $corrected_materials[] = [
                        'row' => $i + 1,
                        'original' => $material_type_raw,
                        'corrected' => $match_result['original']
                    ];
                    error_log("Row " . ($i + 1) . ": AUTO-CORRECTED '$material_type_raw' => '{$match_result['original']}'");
                } else {
                    error_log("Row " . ($i + 1) . ": VALID - '{$match_result['original']}'");
                }
            } else {
                $invalid_materials[] = [
                    'row' => $i + 1,
                    'material' => $material_type_raw,
                    'quantity' => $quantity,
                    'suggestion' => $match_result['normalized']
                ];
                error_log("Row " . ($i + 1) . ": INVALID - '$material_type_raw' not found");
            }
        }
        
        // Return error if invalid materials found
        if (!empty($invalid_materials)) {
            error_log("VALIDATION FAILED - Invalid materials found: " . count($invalid_materials));
            return [
                'success' => false,
                'message' => 'مواردی در لیست مواد یافت نشد',
                'invalid_materials' => $invalid_materials,
                'corrected_materials' => $corrected_materials,
                'valid_count' => count($valid_rows)
            ];
        }
        
        if (empty($valid_rows)) {
            error_log("No valid data found in Excel");
            return ['success' => false, 'message' => 'هیچ داده معتبری در فایل اکسل یافت نشد.'];
        }

        error_log("VALIDATION PASSED - Proceeding with upload...");

        // Handle PDF document upload
        $document_path = null;
        $document_type = null;
        
        if (isset($files['pdf_document']) && $files['pdf_document']['error'] === UPLOAD_ERR_OK) {
            error_log("Processing PDF document...");
            $upload_result = handleFileUpload($files['pdf_document']);
            if ($upload_result['success']) {
                $document_path = $upload_result['path'];
                $document_type = $upload_result['type'];
                error_log("PDF uploaded: " . $document_path);
            } else {
                error_log("PDF upload failed: " . ($upload_result['message'] ?? 'unknown'));
                if (isset($upload_result['message'])) {
                    throw new Exception($upload_result['message']);
                }
            }
        }

        $pdo->beginTransaction();
        $count = 0;

        foreach ($valid_rows as $row_data) {
            $material_type = $row_data['material'];
            $quantity = $row_data['quantity'];

            $sql = "INSERT INTO packing_lists 
                    (packing_number, material_type, received_date, quantity, document_path, document_type, warehouse_id, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['packing_number'],
                $material_type,
                $data['received_date'],
                $quantity,
                $document_path,
                $document_type,
                !empty($data['warehouse_id']) ? $data['warehouse_id'] : null,
                $user_id
            ]);
            $packing_id = $pdo->lastInsertId();

            if (!empty($data['warehouse_id'])) {
                $trans_sql = "INSERT INTO warehouse_transactions 
                             (warehouse_id, material_type, transaction_type, quantity, transaction_date, packing_list_id, created_by) 
                             VALUES (?, ?, 'in', ?, ?, ?, ?)";
                $trans_stmt = $pdo->prepare($trans_sql);
                $trans_stmt->execute([
                    $data['warehouse_id'],
                    $material_type,
                    $quantity,
                    $data['received_date'] . ' ' . date('H:i:s'),
                    $packing_id,
                    $user_id
                ]);
            }
            
            $count++;
        }

        $pdo->commit();
        error_log("SUCCESS! Inserted $count records");
        
        $response = ['success' => true, 'count' => $count];
        
        // Add correction info if any corrections were made
        if (!empty($corrected_materials)) {
            $response['corrected_materials'] = $corrected_materials;
        }
        
        return $response;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("Transaction rolled back due to error");
        }
        error_log("Exception: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return ['success' => false, 'message' => 'خطا: ' . $e->getMessage()];
    }
}

function getPackingLists($pdo, $params) {
    try {
        $sql = "SELECT pl.*, w.name as warehouse_name,
                (SELECT COUNT(*) FROM packing_documents WHERE item_id = pl.id AND item_type = 'packing_list') as document_count
                FROM packing_lists pl
                LEFT JOIN warehouses w ON pl.warehouse_id = w.id
                WHERE 1=1";
        $bind_params = [];

        if (!empty($params['from_date'])) {
            $gregorian_from = toGregorian($params['from_date']);
            if ($gregorian_from) {
                $sql .= " AND pl.received_date >= ?";
                $bind_params[] = $gregorian_from;
            }
        }
        if (!empty($params['to_date'])) {
            $gregorian_to = toGregorian($params['to_date']);
            if ($gregorian_to) {
                $sql .= " AND pl.received_date <= ?";
                $bind_params[] = $gregorian_to;
            }
        }

        if (!empty($params['material_type'])) {
            $sql .= " AND pl.material_type LIKE ?";
            $bind_params[] = '%' . $params['material_type'] . '%';
        }
        
        $sql .= " ORDER BY pl.received_date DESC, pl.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind_params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert dates to Jalali for display
        foreach ($data as &$row) {
            $row['received_date'] = toJalali($row['received_date']);
        }

        return ['success' => true, 'data' => $data];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deletePacking($pdo, $data) {
    try {
        $pdo->beginTransaction();
        
        // Get packing info before deletion
        $sql = "SELECT document_path FROM packing_lists WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['id']]);
        $packing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$packing) {
            return ['success' => false, 'message' => 'بارنامه یافت نشد'];
        }

        // Delete related warehouse transactions
        $trans_sql = "DELETE FROM warehouse_transactions WHERE packing_list_id = ?";
        $trans_stmt = $pdo->prepare($trans_sql);
        $trans_stmt->execute([$data['id']]);

        // Delete packing list
        $sql = "DELETE FROM packing_lists WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['id']]);

        // Delete file if exists
        if ($packing['document_path'] && file_exists(__DIR__ . '/' . $packing['document_path'])) {
            @unlink(__DIR__ . '/' . $packing['document_path']);
        }

        $pdo->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function addWarehouseTransaction($pdo, $data, $user_id) {
    try {
        $sql = "INSERT INTO warehouse_transactions 
                (warehouse_id, material_type, transaction_type, quantity, transaction_date, contractor_name, contractor_id, project_location_id, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['warehouse_id'],
            $data['material_type'],
            $data['transaction_type'],
            $data['quantity'],
            $data['transaction_date'],
            !empty($data['contractor_name']) ? $data['contractor_name'] : null,
            !empty($data['contractor_id']) ? $data['contractor_id'] : null,
            !empty($data['project_location_id']) ? $data['project_location_id'] : null,
            !empty($data['notes']) ? $data['notes'] : null,
            $user_id
        ]);

        return ['success' => true, 'id' => $pdo->lastInsertId()];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getWarehouseInventory($pdo, $params) {
    try {
        $sql = "SELECT * FROM warehouse_inventory WHERE 1=1";
        $bind_params = [];

        if (!empty($params['warehouse_id'])) {
            $sql .= " AND warehouse_id = ?";
            $bind_params[] = $params['warehouse_id'];
        }

        $sql .= " ORDER BY warehouse_name, material_type";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind_params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['success' => true, 'data' => $data];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getWarehouseTransactions($pdo, $params) {
    try {
        // Step 1: Get transactions from the project database (without user info)
        $sql = "SELECT 
                    wt.*, 
                    w.name as warehouse_name, 
                    pl.building_name, 
                    pl.part_name
                FROM warehouse_transactions wt
                LEFT JOIN warehouses w ON wt.warehouse_id = w.id
                LEFT JOIN project_locations pl ON wt.project_location_id = pl.id
                WHERE 1=1";
        
        $bind_params = [];

        if (!empty($params['warehouse_id'])) {
            $sql .= " AND wt.warehouse_id = ?";
            $bind_params[] = $params['warehouse_id'];
        }

         if (!empty($params['from_date'])) {
            $gregorian_from = toGregorian($params['from_date']);
            if ($gregorian_from) {
                $sql .= " AND DATE(wt.transaction_date) >= ?";
                $bind_params[] = $gregorian_from;
            }
        }
        if (!empty($params['to_date'])) {
            $gregorian_to = toGregorian($params['to_date']);
            if ($gregorian_to) {
                $sql .= " AND DATE(wt.transaction_date) <= ?";
                $bind_params[] = $gregorian_to;
            }
        }
        $sql .= " ORDER BY wt.transaction_date DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind_params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Step 2: Collect all unique user IDs from the transactions
        $user_ids = [];
        foreach ($transactions as $transaction) {
            if (!empty($transaction['created_by'])) {
                $user_ids[] = $transaction['created_by'];
            }
        }
        $user_ids = array_unique($user_ids);
        
        $user_map = [];
        // Step 3: If we have user IDs, fetch their names from the common database
        if (!empty($user_ids)) {
            $commonDb = getCommonDBConnection();
            $placeholders = rtrim(str_repeat('?,', count($user_ids)), ',');
            
            $user_sql = "SELECT id, first_name, last_name FROM users WHERE id IN ($placeholders)";
            $user_stmt = $commonDb->prepare($user_sql);
            $user_stmt->execute($user_ids);
            $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Create a simple map of [id => name] for easy lookup
            foreach ($users as $user) {
                $user_map[$user['id']] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            }
        }

        // Step 4: Combine the data
        foreach ($transactions as &$row) {
            $row['transaction_date'] = toJalali($row['transaction_date']);
            // Look up the user's name from the map
            $row['user_name'] = $user_map[$row['created_by']] ?? 'کاربر حذف شده';
        }
        unset($row); // Unset the reference

        return ['success' => true, 'data' => $transactions];

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}