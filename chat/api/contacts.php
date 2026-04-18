<?php
/**
 * GET /chat/api/contacts.php
 *   → Return all users (excluding current) with their current presence
 *     status (online/away/offline) and last seen timestamp. Used for
 *     the "start a direct chat" picker and the group-create modal.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Authentication required']));
}

$pdo = getCommonDBConnection();
$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.avatar_path, u.role,
           COALESCE(p.status, 'offline') AS status,
           p.last_seen
    FROM users u
    LEFT JOIN user_presence p ON p.user_id = u.id
    WHERE u.id != ?
    ORDER BY (p.status = 'online') DESC, u.first_name ASC
");
$stmt->execute([$userId]);

echo json_encode(['success' => true, 'contacts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
