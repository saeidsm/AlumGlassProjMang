<?php
// /pardis/api/save_scaffolding.php
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
$drawing_json = $_POST['drawing_json'] ?? null;

if (!$plan_file) {
    http_response_code(400);
    exit(json_encode(['error' => 'Plan file is required.']));
}

try {
    $pdo = getProjectDBConnection('pardis');
    $userId = $_SESSION['user_id'];

    // This logic correctly handles deleting all drawings if the JSON is empty
    if (empty($drawing_json) || $drawing_json === 'null' || $drawing_json === '{}') {
        $deleteSql = "DELETE FROM scaffolding_drawings WHERE plan_file = ?";
        $deleteStmt = $pdo->prepare($deleteSql);
        $deleteStmt->execute([$plan_file]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'تمام ترسیم‌های داربست پاک شد',
            'total_length_m' => 0,
            'total_area_sqm' => 0
        ]);
        exit;
    }

    // --- START: MODIFICATION - FETCH SCALE FACTOR ---
    $scaleFactorSql = "SELECT scale_factor FROM elements WHERE plan_file = ? LIMIT 1";
    $scaleStmt = $pdo->prepare($scaleFactorSql);
    $scaleStmt->execute([$plan_file]);
    $scaleResult = $scaleStmt->fetch(PDO::FETCH_ASSOC);

    // Default to 1.0 if not found to prevent errors, but log this event if possible.
    $scaleFactor = $scaleResult ? (float)$scaleResult['scale_factor'] : 1.0;

    if ($scaleFactor <= 0) {
        // A scale factor of 0 or less is invalid.
        throw new Exception('Invalid scale factor found for the plan.');
    }
    // --- END: MODIFICATION ---

    // Validate JSON
    $drawingData = json_decode($drawing_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    $totalLength = 0;
    $totalArea = 0;

    // --- APPLY SCALE FACTOR TO ALL CALCULATIONS ---

    // Calculate from lines
    if (isset($drawingData['lines'])) {
        foreach ($drawingData['lines'] as $line) {
            $coords = $line['coords'];
            $dx = $coords[2] - $coords[0];
            $dy = $coords[3] - $coords[1];
            $length_pixels = sqrt($dx * $dx + $dy * $dy);
            $length_m = ($length_pixels * $scaleFactor) / 100; // pixels * (cm/pixel) / (cm/m) = meters
            $totalLength += $length_m;
        }
    }

    // Calculate from rectangles
    if (isset($drawingData['rectangles'])) {
        foreach ($drawingData['rectangles'] as $rect) {
            $coords = $rect['coords'];
            $width_pixels = abs($coords[2] - $coords[0]);
            $height_pixels = abs($coords[3] - $coords[1]);
            
            $width_m = ($width_pixels * $scaleFactor) / 100;
            $height_m = ($height_pixels * $scaleFactor) / 100;

            $area_sqm = $width_m * $height_m;
            $totalArea += $area_sqm;
            $totalLength += ($width_m + $height_m) * 2;
        }
    }

    // Calculate from circles
    if (isset($drawingData['circles'])) {
        foreach ($drawingData['circles'] as $circle) {
            $coords = $circle['coords'];
            $width_pixels = abs($coords[2] - $coords[0]);
            $height_pixels = abs($coords[3] - $coords[1]);

            $radius_pixels = sqrt(pow($width_pixels, 2) + pow($height_pixels, 2)) / 2;
            $radius_m = ($radius_pixels * $scaleFactor) / 100;

            $area_sqm = M_PI * $radius_m * $radius_m;
            $circumference_m = 2 * M_PI * $radius_m;
            
            $totalArea += $area_sqm;
            $totalLength += $circumference_m;
        }
    }

    // Calculate from free drawings
    if (isset($drawingData['freeDrawings'])) {
        foreach ($drawingData['freeDrawings'] as $freeDraw) {
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
        }
    }

    $checkSql = "SELECT id FROM scaffolding_drawings WHERE plan_file = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$plan_file]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $sql = "UPDATE scaffolding_drawings SET drawing_json = ?, total_length_m = ?, total_area_sqm = ?, updated_at = CURRENT_TIMESTAMP, created_by = ? WHERE plan_file = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$drawing_json, $totalLength, $totalArea, $userId, $plan_file]);
    } else {
        $sql = "INSERT INTO scaffolding_drawings (plan_file, drawing_json, total_length_m, total_area_sqm, created_by) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$plan_file, $drawing_json, $totalLength, $totalArea, $userId]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'داربست با موفقیت ذخیره شد',
        'total_length_m' => round($totalLength, 2),
        'total_area_sqm' => round($totalArea, 2)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'خطا در ذخیره‌سازی: ' . $e->getMessage()]));
}
?>