<?php
// FINAL store_public_key.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/vendor/autoload.php';
require_once __DIR__ . '/../../sercon/bootstrap.php';

function write_key_log($message) {
    $log_file = __DIR__ . '/logs/store_key_' . date("Y-m-d") . '.log';
    if (!file_exists(__DIR__ . '/logs')) mkdir(__DIR__ . '/logs', 0755, true);
    $timestamp = date("Y-m-d H:i:s");
    $formatted_message = is_array($message) || is_object($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message;
    file_put_contents($log_file, "[$timestamp] " . $formatted_message . "\n", FILE_APPEND);
}

secureSession();

if (!isset($_SESSION['user_id']) || !isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Authentication required.']));
}

$userId = $_SESSION['user_id'];
$json_str = file_get_contents('php://input');
$data = json_decode($json_str, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['public_key_pem'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid or missing public key.']));
}

$publicKeyPem = trim($data['public_key_pem']);
$deviceInfo = isset($data['device_info']) ? trim($data['device_info']) : 'Unknown Device';

if (strpos($publicKeyPem, '-----BEGIN PUBLIC KEY-----') === false) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid public key format.']));
}

try {
    $common_pdo = getCommonDBConnection();
    
    $sql = "INSERT INTO user_public_keys (user_id, public_key_pem, device_info) VALUES (?, ?, ?)";
    $stmt = $common_pdo->prepare($sql);
    
    if ($stmt->execute([$userId, $publicKeyPem, $deviceInfo])) {
        write_key_log("SUCCESS: Stored new key for user {$userId} from device '{$deviceInfo}'.");
        echo json_encode(['success' => true, 'message' => 'New device key stored successfully.']);
    } else {
        throw new Exception("Database execution failed.");
    }
} catch (Exception $e) {
    http_response_code(500);
    write_key_log("DATABASE ERROR storing key for user {$userId}: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error while storing key.']);
}