<?php
/**
 * API for adding profiles and accessories to database
 */

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
header('Content-Type: application/json');

// Security check
secureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'superuser', 'supervisor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

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
        return implode('-', jalali_to_gregorian($parts[0], $parts[1], $parts[2]));
    }

    return null;
}

try {
    $pdo = getProjectDBConnection('pardis');
} catch (PDOException $e) {
    logError("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get_project_locations') {
        echo json_encode(getProjectLocations($pdo));
        exit();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid GET action']);
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

if (empty($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action parameter is missing']);
    exit();
}

$action = trim($_POST['action']);
$data = $_POST;

try {
    switch ($action) {
        case 'add_profile':
            $result = addProfile($pdo, $data);
            break;
        case 'add_accessory':
            $result = addAccessory($pdo, $data);
            break;
        case 'add_remaining_profile':
            $result = addRemainingProfile($pdo, $data);
            break;
        case 'add_remaining_accessory':
            $result = addRemainingAccessory($pdo, $data);
            break;
        case 'add_inventory_exit':
            $result = addInventoryExit($pdo, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit();
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    logError("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function getProjectLocations($pdo) {
    try {
        $stmt = $pdo->query("SELECT building_name, part_name FROM project_locations ORDER BY building_name, part_name");
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grouped = [];
        foreach ($locations as $loc) {
            $grouped[$loc['building_name']][] = $loc['part_name'];
        }
        return ['success' => true, 'locations' => $grouped];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function addInventoryExit($pdo, $data) {
    try {
        $sql = "INSERT INTO inventory_transactions (item_id, item_type, quantity_taken, transaction_date, taken_by, destination_building, destination_part) 
                VALUES (:item_id, :item_type, :quantity, :date, :taken_by, :building, :part)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':item_id' => $data['item_id'],
            ':item_type' => $data['item_type'],
            ':quantity' => $data['quantity'],
            ':date' => toGregorian($data['transaction_date']),
            ':taken_by' => $data['taken_by'],
            ':building' => $data['destination_building'],
            ':part' => $data['destination_part']
        ]);
        return ['success' => true, 'message' => 'خروج با موفقیت ثبت شد.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}


function addProfile($pdo, $data) {
    $gregorian_date = toGregorian($data['receipt_date'] ?? '');
    
    $sql = "INSERT INTO profiles (item_code, length, quantity, uom, column1_content, image_file, sheet_name, receipt_date) 
            VALUES (:item_code, :length, :quantity, :uom, :column1_content, :image_file, :sheet_name, :receipt_date)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':item_code' => $data['item_code'] ?? null,
        ':length' => $data['length'] ?: null,
        ':quantity' => $data['quantity'] ?? null,
        ':uom' => $data['uom'] ?? null,
        ':column1_content' => $data['column1_content'] ?? null,
        ':image_file' => $data['image_file'] ?? null,
        ':sheet_name' => $data['sheet_name'] ?? null,
        ':receipt_date' => $gregorian_date
    ]);
    
    return ['success' => true, 'message' => 'پروفیل با موفقیت ثبت شد', 'id' => $pdo->lastInsertId()];
}

function addAccessory($pdo, $data) {
    $gregorian_date = toGregorian($data['receipt_date'] ?? '');
    
    $sql = "INSERT INTO accessories (item_code, length, quantity, uom, origin, pallet_no, image_file, sheet_name, receipt_date) 
            VALUES (:item_code, :length, :quantity, :uom, :origin, :pallet_no, :image_file, :sheet_name, :receipt_date)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':item_code' => $data['item_code'] ?? null,
        ':length' => $data['length'] ?? null,
        ':quantity' => $data['quantity'] ?? null,
        ':uom' => $data['uom'] ?? null,
        ':origin' => $data['origin'] ?? null,
        ':pallet_no' => $data['pallet_no'] ?? null,
        ':image_file' => $data['image_file'] ?? null,
        ':sheet_name' => $data['sheet_name'] ?? null,
        ':receipt_date' => $gregorian_date
    ]);
    
    return ['success' => true, 'message' => 'اکسسوری با موفقیت ثبت شد', 'id' => $pdo->lastInsertId()];
}

function addRemainingProfile($pdo, $data) {
    $gregorian_date = toGregorian($data['receipt_date'] ?? '');
    
    $sql = "INSERT INTO remaining_profiles 
            (part_no, no, package, item_code, item_name, type_of_service, lot, length, 
             uom2, qty1, uom1, qty2, origin, image_file, sheet_name, receipt_date) 
            VALUES (:part_no, :no, :package, :item_code, :item_name, :type_of_service, :lot, :length,
                    :uom2, :qty1, :uom1, :qty2, :origin, :image_file, :sheet_name, :receipt_date)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':part_no' => $data['part_no'] ?? null,
        ':no' => $data['no'] ?? null,
        ':package' => $data['package'] ?? null,
        ':item_code' => $data['item_code'] ?? null,
        ':item_name' => $data['item_name'] ?? null,
        ':type_of_service' => $data['type_of_service'] ?? null,
        ':lot' => $data['lot'] ?? null,
        ':length' => $data['length'] ?? null,
        ':uom2' => $data['uom2'] ?? null,
        ':qty1' => $data['qty1'] ?? null,
        ':uom1' => $data['uom1'] ?? null,
        ':qty2' => $data['qty2'] ?? null,
        ':origin' => $data['origin'] ?? null,
        ':image_file' => $data['image_file'] ?? null,
        ':sheet_name' => $data['sheet_name'] ?? null,
        ':receipt_date' => $gregorian_date
    ]);
    
    return ['success' => true, 'message' => 'پروفیل باقی‌مانده با موفقیت ثبت شد', 'id' => $pdo->lastInsertId()];
}

function addRemainingAccessory($pdo, $data) {
    $gregorian_date = toGregorian($data['receipt_date'] ?? '');
    
    $sql = "INSERT INTO remaining_accessories 
            (no, package, item_code, item_name, uom3, qty3, description, image_file, sheet_name, receipt_date) 
            VALUES (:no, :package, :item_code, :item_name, :uom3, :qty3, :description, :image_file, :sheet_name, :receipt_date)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':no' => $data['no'] ?? null,
        ':package' => $data['package'] ?? null,
        ':item_code' => $data['item_code'] ?? null,
        ':item_name' => $data['item_name'] ?? null,
        ':uom3' => $data['uom3'] ?? null,
        ':qty3' => $data['qty3'] ?? null,
        ':description' => $data['description'] ?? null,
        ':image_file' => $data['image_file'] ?? null,
        ':sheet_name' => $data['sheet_name'] ?? null,
        ':receipt_date' => $gregorian_date
    ]);
    
    return ['success' => true, 'message' => 'اکسسوری باقی‌مانده با موفقیت ثبت شد', 'id' => $pdo->lastInsertId()];
}