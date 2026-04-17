<?php
// api/delete_logo.php
require_once __DIR__ . '/../../../sercon/bootstrap.php';
header('Content-Type: application/json');

if (function_exists('isLoggedIn') && !isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    $pdo = getProjectDBConnection('ghom');
    $user_id = $_SESSION['user_id'] ?? 0;
    $logo_type = $_POST['logo_type'] ?? '';

    if (!in_array($logo_type, ['logo_right', 'logo_middle', 'logo_left'])) {
        throw new Exception('Invalid logo type');
    }

    // Set the column to NULL/Empty
    $stmt = $pdo->prepare("UPDATE print_settings SET $logo_type = '' WHERE user_id = ?");
    $stmt->execute([$user_id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}