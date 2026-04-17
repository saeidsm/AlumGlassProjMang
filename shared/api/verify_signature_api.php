<?php
// /shared/api/verify_signature_api.php

// Bootstrap your application
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/project_context.php';
// Include the phpseclib autoloader (adjust path if necessary)
require_once __DIR__ . '/../../../sercon/vendor/autoload.php';

use phpseclib3\Crypt\RSA;

// Secure the API endpoint
secureSession();
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit();
}

// Get the JSON data sent from the verification page
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data.']);
    exit();
}

$userId = $input['user_id'] ?? null;
$signedData = $input['signed_data'] ?? null;
$signatureBase64 = $input['digital_signature'] ?? null;

if (!$userId || !$signedData || !$signatureBase64) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters: user_id, signed_data, or digital_signature.']);
    exit();
}

try {
    // Use the common database connection to fetch user data
    $common_pdo = getCommonDBConnection();

    // 1. Fetch the user's public key from the database
    $stmt = $common_pdo->prepare("SELECT public_key_pem FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['public_key_pem'])) {
        throw new Exception('Public key not found for this user.');
    }

    $publicKeyPem = $user['public_key_pem'];

    // 2. Perform the cryptographic verification
    $isVerified = false;
    $publicKey = RSA::load($publicKeyPem);

    // FIX: Use the instanceof check to ensure we have the correct object type
    if ($publicKey instanceof \phpseclib3\Crypt\RSA\PublicKey) {
        $publicKey = $publicKey->withPadding(RSA::SIGNATURE_PKCS1)->withHash('sha256');
        
        // Decode the signature from Base64
        $signatureBytes = base64_decode($signatureBase64);

        // The verify() function returns true if the signature is valid, false otherwise
        $isVerified = $publicKey->verify($signedData, $signatureBytes);
    } else {
        throw new Exception('Failed to load the public key into a verifiable format.');
    }

    // 3. Send back the result
    echo json_encode([
        'status' => 'success',
        'verified' => $isVerified
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
