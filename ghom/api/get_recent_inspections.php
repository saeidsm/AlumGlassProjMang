<?php
// /ghom/api/get_recent_inspections.php

// Bootstrap your application to get session and database access
require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/jdf.php'; // For Persian date formatting

// Secure the API endpoint
secureSession();
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['error' => 'Authentication required.']);
    exit();
}

try {
    // Connect to the project-specific database
    $pdo = getProjectDBConnection('ghom');
    
    // The query now directly selects the stored signed_data and joins to get the user's name.
    $stmt = $pdo->prepare("
        SELECT 
            i.inspection_id as id, 
            i.element_id, 
            i.user_id,
            i.created_at as timestamp,
            i.digital_signature,
            i.signed_data, -- Fetch the new, reliable column
            COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username) as user_display_name
        FROM 
            inspections i
        LEFT JOIN 
            hpc_common.users u ON i.user_id = u.id
        WHERE 
            i.digital_signature IS NOT NULL AND i.digital_signature != ''
            AND i.signed_data IS NOT NULL AND i.signed_data != ''
        ORDER BY 
            i.inspection_id DESC
        LIMIT 50
    ");
    $stmt->execute();
    $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($inspections)) {
        echo json_encode([]);
        exit();
    }

    // Format the timestamp for display. No other data manipulation is needed.
    foreach ($inspections as &$insp) {
        $insp['persian_timestamp'] = jdate('Y/m/d H:i', strtotime($insp['timestamp']));
    }
    
    echo json_encode($inspections);

} catch (Exception $e) {
    // Return a JSON error if something goes wrong
    http_response_code(500);
    error_log("Error in get_recent_inspections.php: " . $e->getMessage());
    echo json_encode(['error' => 'Server error fetching inspections: ' . $e->getMessage()]);
}



