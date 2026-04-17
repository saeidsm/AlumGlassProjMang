<?php
// api/save_print_settings.php - CORRECT VERSION FOR GENERAL SETTINGS
require_once __DIR__ . '/../../../sercon/bootstrap.php';

header('Content-Type: application/json');

requireRole(['admin']);

try {
    $pdo = getProjectDBConnection('ghom');
    $user_id = $_SESSION['user_id'] ?? 0;
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $new_settings = json_decode($input, true);

    if (!$new_settings) {
        throw new Exception('Invalid JSON received');
    }

    // Load existing to prevent overwriting logos
    $stmt = $pdo->prepare("SELECT settings_json FROM print_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $existing_json = $stmt->fetchColumn();
    $existing_settings = $existing_json ? json_decode($existing_json, true) : [];

    // Merge
    $final_settings = array_replace_recursive($existing_settings, $new_settings);

    // Save
    $final_json = json_encode($final_settings, JSON_UNESCAPED_UNICODE);
    
    $check = $pdo->prepare("SELECT 1 FROM print_settings WHERE user_id = ?");
    $check->execute([$user_id]);
    
    if ($check->fetchColumn()) {
        $stmt = $pdo->prepare("UPDATE print_settings SET settings_json = ? WHERE user_id = ?");
        $stmt->execute([$final_json, $user_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO print_settings (user_id, settings_json) VALUES (?, ?)");
        $stmt->execute([$user_id, $final_json]);
    }

    echo json_encode(['success' => true, 'message' => 'تنظیمات عمومی ذخیره شد']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}