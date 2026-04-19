<?php
// api_documents.php - API for document management
require_once __DIR__ . '/../sercon/bootstrap.php';

secureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'superuser', 'supervisor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $pdo = getProjectDBConnection('pardis');
} catch (PDOException $e) {
    logError("Database connection failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

// Handle bulk document upload
if ($action === 'bulk_upload_document') {
    try {
        $item_type = $_POST['item_type'] ?? '';
        $document_name = $_POST['document_name'] ?? '';
        $item_codes = $_POST['item_codes'] ?? [];
        
        // Validate inputs
        if (empty($item_type) || empty($document_name) || empty($item_codes)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }
        
        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File upload error']);
            exit();
        }
        
        // Validate file type
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        $file_type = $_FILES['document']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF and images allowed']);
            exit();
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = __DIR__ . '/uploads/packing_documents/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $unique_filename = uniqid('doc_') . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $unique_filename;
        $relative_path = 'uploads/packing_documents/' . $unique_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($_FILES['document']['tmp_name'], $upload_path)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save file']);
            exit();
        }
        
        // Determine document type
        $doc_type = (strpos($file_type, 'pdf') !== false) ? 'pdf' : 'image';
        
        // Get item IDs for selected item codes
        $table = ($item_type === 'profile') ? 'profiles' : 'accessories';
        $placeholders = str_repeat('?,', count($item_codes) - 1) . '?';
        
        $stmt = $pdo->prepare("SELECT id, item_code FROM $table WHERE item_code IN ($placeholders)");
        $stmt->execute($item_codes);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            unlink($upload_path); // Delete uploaded file
            echo json_encode(['success' => false, 'message' => 'No matching items found']);
            exit();
        }
        
        // Insert document record for each item
        $stmt = $pdo->prepare("
            INSERT INTO packing_documents 
            (item_id, item_type, document_name, document_path, document_type, upload_date, uploaded_by) 
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $assigned_count = 0;
        $user_id = $_SESSION['user_id'] ?? 0;
        
        foreach ($items as $item) {
            $stmt->execute([
                $item['id'],
                $item_type,
                $document_name . ' - ' . $item['item_code'],
                $relative_path,
                $doc_type,
                $user_id
            ]);
            $assigned_count++;
        }
        
        logError("User {$user_id} uploaded document '{$document_name}' and assigned to {$assigned_count} {$item_type}(s)");
        
        echo json_encode([
            'success' => true,
            'message' => 'Document assigned successfully',
            'assigned_count' => $assigned_count,
            'file_path' => $relative_path
        ]);
        
    } catch (Exception $e) {
        logError("Bulk upload error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle single document upload (existing functionality)
if ($action === 'upload_document') {
    try {
        $item_code = $_POST['item_code'] ?? '';
        $item_type = $_POST['item_type'] ?? '';
        $document_name = $_POST['document_name'] ?? '';
        
        if (empty($item_code) || empty($item_type) || empty($document_name)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }
        
        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File upload error']);
            exit();
        }
        
        // Validate file type
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        $file_type = $_FILES['document']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
            exit();
        }
        
        // Create upload directory
        $upload_dir = __DIR__ . '/uploads/packing_documents/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $unique_filename = uniqid('doc_') . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $unique_filename;
        $relative_path = 'uploads/packing_documents/' . $unique_filename;
        
        // Move file
        if (!move_uploaded_file($_FILES['document']['tmp_name'], $upload_path)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save file']);
            exit();
        }
        
        // Get item ID
        $table = ($item_type === 'profile') ? 'profiles' : 'accessories';
        $stmt = $pdo->prepare("SELECT id FROM $table WHERE item_code = ? LIMIT 1");
        $stmt->execute([$item_code]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            unlink($upload_path);
            echo json_encode(['success' => false, 'message' => 'Item not found']);
            exit();
        }
        
        // Determine document type
        $doc_type = (strpos($file_type, 'pdf') !== false) ? 'pdf' : 'image';
        
        // Insert document record
        $stmt = $pdo->prepare("
            INSERT INTO packing_documents 
            (item_id, item_type, document_name, document_path, document_type, upload_date, uploaded_by) 
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $stmt->execute([
            $item['id'],
            $item_type,
            $document_name,
            $relative_path,
            $doc_type,
            $_SESSION['user_id'] ?? 0
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'file_path' => $relative_path
        ]);
        
    } catch (Exception $e) {
        logError("Upload error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>