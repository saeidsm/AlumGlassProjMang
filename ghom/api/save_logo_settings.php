<?php
// api/save_logo_settings.php - ABSOLUTE PATHS VERSION
require_once __DIR__ . '/../../../sercon/bootstrap.php';

header('Content-Type: application/json');

if (function_exists('isLoggedIn') && !isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    $pdo = getProjectDBConnection('ghom');
    $user_id = $_SESSION['user_id'] ?? 0;

    // 1. AUTO-MIGRATION: Create Columns
    $required_columns = ['logo_right', 'logo_middle', 'logo_left'];
    foreach ($required_columns as $col) {
        try {
            $pdo->query("SELECT $col FROM print_settings LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("ALTER TABLE print_settings ADD COLUMN $col VARCHAR(255) DEFAULT ''");
        }
    }

    // 2. DEFINE ABSOLUTE PATHS
    // Web Path: Always starts with /ghom/uploads/logos/
    $web_folder = '/ghom/uploads/logos/';
    
    // Physical Path: C:/xampp/htdocs/ghom/uploads/logos/
    $physical_folder = $_SERVER['DOCUMENT_ROOT'] . $web_folder;

    if (!is_dir($physical_folder)) {
        if (!mkdir($physical_folder, 0775, true)) {
            throw new Exception('خطا در ایجاد پوشه: ' . $physical_folder);
        }
    }

    // Ensure DB Row Exists
    $check = $pdo->prepare("SELECT 1 FROM print_settings WHERE user_id = ?");
    $check->execute([$user_id]);
    if (!$check->fetchColumn()) {
        $pdo->prepare("INSERT INTO print_settings (user_id) VALUES (?)")->execute([$user_id]);
    }

    $updated = false;
    $messages = [];
    $errors = [];
    $saved_paths = [];
    
    $logo_types = ['logo_right', 'logo_middle', 'logo_left'];
    
    foreach ($logo_types as $input_name) {
        if (isset($_FILES[$input_name])) {
            $file = $_FILES[$input_name];
            $clean_col_name = $input_name; // matches db column
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                if ($file['error'] !== UPLOAD_ERR_NO_FILE) $errors[] = "$input_name: Error " . $file['error'];
                continue;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
                $errors[] = "$input_name: Invalid format";
                continue;
            }
            
            // Unique Filename
            $filename = str_replace('logo_', '', $input_name) . '_' . $user_id . '_' . time() . '.' . $ext;
            
            $target_path = $physical_folder . $filename;
            $db_web_path = $web_folder . $filename; // /ghom/uploads/logos/file.png
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Update DB with ABSOLUTE WEB PATH
                $sql = "UPDATE print_settings SET $clean_col_name = ? WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$db_web_path, $user_id]);
                
                $updated = true;
                $messages[] = "$input_name Saved";
                $saved_paths[$clean_col_name] = $db_web_path;
            } else {
                $errors[] = "Failed to move file to $target_path";
            }
        }
    }

    if ($updated) {
        echo json_encode([
            'success' => true, 
            'message' => implode(' | ', $messages),
            'logos' => $saved_paths,
            'debug_errors' => $errors
        ]);
    } else {
        $msg = empty($errors) ? 'No file selected' : implode(' | ', $errors);
        echo json_encode(['success' => false, 'message' => $msg]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}