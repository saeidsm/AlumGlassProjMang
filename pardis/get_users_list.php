<?php
// public_html/pardis/get_users_list.php

require_once __DIR__ . '/../sercon/bootstrap.php';
secureSession();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Only admins can access user list
$user_role = $_SESSION['role'] ?? 'user';
if (!in_array($user_role, ['admin', 'superuser', 'coa'])) {
    echo json_encode(['error' => 'Access denied']);
    exit();
}

try {
    $commonPdo = getCommonDBConnection();
    
    $stmt = $commonPdo->prepare("
        SELECT 
            id,
            first_name,
            last_name,
            role
        FROM users
        WHERE is_active = 1
        ORDER BY first_name, last_name
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
    
} catch (Exception $e) {
    logError("Error fetching users: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطا در بارگذاری لیست کاربران'
    ]);
}