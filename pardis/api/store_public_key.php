<?php
// api/store_public_key.php
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$publicKeyPem = $data['public_key_pem'] ?? null;

if (empty($publicKeyPem) || strpos($publicKeyPem, '-----BEGIN PUBLIC KEY-----') === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing public key.']);
    exit;
}

try {
    $pdo = getCommonDBConnection();
    $stmt = $pdo->prepare("UPDATE users SET public_key_pem = ? WHERE id = ?");
    $stmt->execute([$publicKeyPem, $_SESSION['user_id']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Public key stored successfully.']);
    } else {
        throw new Exception("User not found or key was not updated.");
    }
} catch (Exception $e) {
    logError("Failed to store public key for user {$_SESSION['user_id']}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save public key to the server.']);
}
?>
