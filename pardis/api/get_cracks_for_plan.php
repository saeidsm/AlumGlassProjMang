<?php
// /pardis/api/get_cracks_for_plan.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();

if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

$plan_file = $_GET['plan'] ?? null;
if (!$plan_file) {
    http_response_code(400);
    exit(json_encode(['error' => 'Plan file is required.']));
}

try {
    $pdo = getProjectDBConnection('pardis');

    // Enhanced query to find all saved drawings for elements within the specified plan file.
    // This now searches for JSON that contains any of the drawing shape types.
    $sql = "
        SELECT 
            i.element_id, 
            e.geometry_json,
            id.item_value as drawing_json
        FROM inspection_data id
        JOIN inspections i ON id.inspection_id = i.inspection_id
        JOIN elements e ON i.element_id = e.element_id
        WHERE e.plan_file = ?
        AND (
            id.item_value LIKE '{%\"lines\"%}' OR          -- Contains lines
            id.item_value LIKE '{%\"rectangles\"%}' OR     -- Contains rectangles
            id.item_value LIKE '{%\"circles\"%}' OR        -- Contains circles
            id.item_value LIKE '{%\"freeDrawings\"%}' OR   -- Contains free drawings
            (
                -- Legacy support: JSON that looks like drawing data but may not have explicit shape keys
                id.item_value LIKE '{%\"coords\"%}' AND    -- Has coordinate data
                id.item_value LIKE '%\"color\"%'           -- Has color data
            )
        )
        AND JSON_VALID(id.item_value) = 1                  -- Ensure it's valid JSON
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plan_file]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Additional validation and enhancement of results
    $validResults = [];
    foreach ($results as $result) {
        try {
            $drawingData = json_decode($result['drawing_json'], true);
            
            // Validate that the JSON actually contains drawing data
            $hasDrawingData = false;
            
            if (isset($drawingData['lines']) && is_array($drawingData['lines']) && !empty($drawingData['lines'])) {
                $hasDrawingData = true;
            }
            if (isset($drawingData['rectangles']) && is_array($drawingData['rectangles']) && !empty($drawingData['rectangles'])) {
                $hasDrawingData = true;
            }
            if (isset($drawingData['circles']) && is_array($drawingData['circles']) && !empty($drawingData['circles'])) {
                $hasDrawingData = true;
            }
            if (isset($drawingData['freeDrawings']) && is_array($drawingData['freeDrawings']) && !empty($drawingData['freeDrawings'])) {
                $hasDrawingData = true;
            }
            
            // Legacy support for old format that might just have coords and color
            if (!$hasDrawingData && isset($drawingData['coords']) && isset($drawingData['color'])) {
                $hasDrawingData = true;
                // Convert legacy format to new format
                $result['drawing_json'] = json_encode([
                    'lines' => [$drawingData]
                ]);
            }
            
            if ($hasDrawingData) {
                $validResults[] = $result;
            }
            
        } catch (Exception $jsonError) {
            // Skip invalid JSON entries
            error_log("Invalid JSON in drawing data for element {$result['element_id']}: " . $jsonError->getMessage());
            continue;
        }
    }

    echo json_encode($validResults);
    
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}
?>