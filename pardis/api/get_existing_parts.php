<?php
// /public_html/pardis/api/get_existing_parts.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Authentication required']));
}

$baseElementId = $_GET['element_id'] ?? null;
if (empty($baseElementId)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Element ID is required.']));
}

try {
    $pdo = getProjectDBConnection('pardis');

    // Your original logic to find already-saved parts. THIS IS UNCHANGED.
    $sql = "SELECT DISTINCT part_name FROM inspections WHERE element_id = ? AND part_name IS NOT NULL ORDER BY part_name;";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$baseElementId]);
    $parts = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // ===================================================================
    // START: FINAL, CORRECTED LOGIC
    // This runs ONLY if no parts have been saved for this element yet.
    // ===================================================================
    if (empty($parts)) {
        // Get the element's type and orientation from the 'elements' table.
        $metaSql = "SELECT element_type, panel_orientation FROM elements WHERE element_id = ? LIMIT 1";
        $metaStmt = $pdo->prepare($metaSql);
        $metaStmt->execute([$baseElementId]);
        $elementMeta = $metaStmt->fetch(PDO::FETCH_ASSOC);

        if ($elementMeta) {
            $elementType = $elementMeta['element_type'];
            $orientation = $elementMeta['panel_orientation'];
            
            // Define which element types get the multi-part menu by default.
            $multiPartElementTypes = ['GFRC', 'Brick'];

            if (in_array($elementType, $multiPartElementTypes)) {
                // This is a new GFRC or Brick element. Proactively return a default list of parts.

                // --- THIS IS THE EXACT LOGIC YOU REQUESTED ---
                if ($orientation === 'Horizontal' || $orientation === 'افقی') {
                    // HORIZONTAL: Has "up", "down", and "face"
                    $parts = ["face", "up", "down"];
                } else if ($orientation === 'Vertical' || $orientation === 'عمودی') {
                    // VERTICAL: Has "left", "right", and "face"
                    $parts = ["face", "left", "right"];
                } else {
                    // Fallback for unknown orientation: provide all options
                    $parts = ["face", "left", "right", "up", "down"];
                }
            }
        }
    }
    // ===================================================================
    // END: FINAL, CORRECTED LOGIC
    // ===================================================================

    // Your original fallback for simple elements. THIS IS UNCHANGED.
    if (empty($parts)) {
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM inspections WHERE element_id = ?");
        $check_stmt->execute([$baseElementId]);
        if ($check_stmt->fetchColumn() > 0) {
            $parts = ['default'];
        }
    }

    echo json_encode($parts);

} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error get_existing_parts.php: " . $e->getMessage());
    exit(json_encode(['error' => 'Database query failed.']));
}
?>