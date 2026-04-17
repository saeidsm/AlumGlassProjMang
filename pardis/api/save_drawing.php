<?php
// /pardis/api/save_drawing.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();

if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}
require_once __DIR__ . '/../../includes/security.php';
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$plan_file = $_POST['plan_file'] ?? null;
$layer_type = $_POST['layer_type'] ?? null; // The new, crucial parameter
$drawing_json = $_POST['drawing_json'] ?? null;

if (!$plan_file || !$layer_type) {
    http_response_code(400);
    exit(json_encode(['error' => 'Plan file and layer type are required.']));
}

try {
    $pdo = getProjectDBConnection('pardis');
    $userId = $_SESSION['user_id'];

    // Handle deletion if JSON is empty
    if (empty($drawing_json) || $drawing_json === 'null' || $drawing_json === '{}') {
        $deleteSql = "DELETE FROM plan_drawings WHERE plan_file = ? AND layer_type = ?";
        $deleteStmt = $pdo->prepare($deleteSql);
        $deleteStmt->execute([$plan_file, $layer_type]);
        
        echo json_encode([
            'status' => 'success',
            'message' => "لایه '$layer_type' پاک شد",
        ]);
        exit;
    }
    
    // Use the scale factor from your JSON config file (as implemented previously)
    // For simplicity in the API, we'll recalculate here based on elements table
    $scaleFactorSql = "SELECT scale_factor FROM elements WHERE plan_file = ? LIMIT 1";
    $scaleStmt = $pdo->prepare($scaleFactorSql);
    $scaleStmt->execute([$plan_file]);
    $scaleResult = $scaleStmt->fetch(PDO::FETCH_ASSOC);
    $scaleFactor = $scaleResult ? (float)$scaleResult['scale_factor'] : 1.0;
    
    // Fallback for Plan.svg which has no elements
    if ($scaleFactor === 1.0 && $plan_file === 'Plan.svg') {
        $scaleFactorSql = "SELECT scale_factor FROM elements WHERE plan_file = 'SouthAgri.svg' LIMIT 1";
        $scaleStmt = $pdo->prepare($scaleFactorSql);
        $scaleStmt->execute();
        $scaleResult = $scaleStmt->fetch(PDO::FETCH_ASSOC);
        $scaleFactor = $scaleResult ? (float)$scaleResult['scale_factor'] : 1.0;
    }


    if ($scaleFactor <= 0) throw new Exception('Invalid scale factor found for the plan.');
    
    $drawingData = json_decode($drawing_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Invalid JSON data');

    $totalLength = 0;
    $totalArea = 0;
foreach ($drawingData as $object) {
        if (!isset($object['type'])) {
            continue; // Skip invalid objects
        }

        switch ($object['type']) {
            case 'line':
                if (isset($object['coords'])) {
                    $coords = $object['coords'];
                    $dx = $coords[2] - $coords[0];
                    $dy = $coords[3] - $coords[1];
                    $length_pixels = sqrt($dx * $dx + $dy * $dy);
                    $length_m = ($length_pixels * $scaleFactor) / 100;
                    $totalLength += $length_m;
                }
                break;

            case 'rectangle':
                if (isset($object['coords'])) {
                    $coords = $object['coords'];
                    $width_pixels = abs($coords[2] - $coords[0]);
                    $height_pixels = abs($coords[3] - $coords[1]);
                    
                    $width_m = ($width_pixels * $scaleFactor) / 100;
                    $height_m = ($height_pixels * $scaleFactor) / 100;

                    $area_sqm = $width_m * $height_m;
                    $totalArea += $area_sqm;
                    $totalLength += ($width_m + $height_m) * 2; // Perimeter
                }
                break;
      case 'circle':
                if (isset($object['coords'])) {
                    $coords = $object['coords'];
                    $width_pixels = abs($coords[2] - $coords[0]);
                    $height_pixels = abs($coords[3] - $coords[1]);

                    $radius_pixels = sqrt(pow($width_pixels, 2) + pow($height_pixels, 2)) / 2;
                    $radius_m = ($radius_pixels * $scaleFactor) / 100;

                    $area_sqm = M_PI * $radius_m * $radius_m;
                    $circumference_m = 2 * M_PI * $radius_m;
                    
                    $totalArea += $area_sqm;
                    $totalLength += $circumference_m;
                }
                break;
                case 'freeDrawings':
                     if (isset($freeDraw['points']) && count($freeDraw['points']) > 1) {
                for ($i = 1; $i < count($freeDraw['points']); $i++) {
                    $p1 = $freeDraw['points'][$i - 1];
                    $p2 = $freeDraw['points'][$i];
                    
                    $x1 = is_array($p1) ? $p1[0] : $p1['x'];
                    $y1 = is_array($p1) ? $p1[1] : $p1['y'];
                    $x2 = is_array($p2) ? $p2[0] : $p2['x'];
                    $y2 = is_array($p2) ? $p2[1] : $p2['y'];
                    
                    $dx = $x2 - $x1;
                    $dy = $y2 - $y1;
                    $length_pixels = sqrt($dx * $dx + $dy * $dy);
                    $length_m = ($length_pixels * $scaleFactor) / 100;
                    $totalLength += $length_m;
                }
            }
                     break;
              
            // Add more cases as needed
        }
    // Calculate from circles
    

    // Calculate from free drawings
    }

    // Use INSERT ... ON DUPLICATE KEY UPDATE for clean inserts/updates
    $sql = "INSERT INTO plan_drawings (plan_file, layer_type, drawing_json, total_length_m, total_area_sqm, created_by) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            drawing_json = VALUES(drawing_json), 
            total_length_m = VALUES(total_length_m), 
            total_area_sqm = VALUES(total_area_sqm), 
            created_by = VALUES(created_by)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plan_file, $layer_type, $drawing_json, $totalLength, $totalArea, $userId]);

    echo json_encode([
        'status' => 'success',
        'message' => "لایه '$layer_type' با موفقیت ذخیره شد",
        'total_length_m' => round($totalLength, 2),
        'total_area_sqm' => round($totalArea, 2)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'خطا در ذخیره‌سازی: ' . $e->getMessage()]));
}
?>