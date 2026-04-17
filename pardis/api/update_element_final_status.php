<?php
// /pardis/api/update_element_final_status.php

header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();

if (!in_array($_SESSION['role'], ['admin', 'superuser'])) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Access Denied']));
}

try {
    $element_id = $_POST['element_id'] ?? null;
    $final_status = $_POST['final_status'] ?? null;

    if (empty($element_id)) {
        throw new Exception("Element ID is required.");
    }

    // Convert empty string to NULL for the database
    if ($final_status === '') {
        $final_status = null;
    }

    $pdo = getProjectDBConnection('pardis');
    $stmt = $pdo->prepare("UPDATE elements SET final_status = ? WHERE element_id = ?");
    $stmt->execute([$final_status, $element_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'وضعیت نهایی المان با موفقیت به‌روزرسانی شد.']);
    } else {
        // This can happen if the status was already the same value. Not an error.
        echo json_encode(['status' => 'success', 'message' => 'هیچ تغییری برای ذخیره وجود نداشت.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'خطای پایگاه داده: ' . $e->getMessage()]);
}
