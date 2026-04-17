<?php
// /public_html/pardis/api/get_plan_elements.php (DEFINITIVE CORRECTED VERSION V4)
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Authentication required']));
}

$plan_file = $_GET['plan'] ?? null;
if (empty($plan_file)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Plan file parameter is required.']));
}
$user_role = $_SESSION['role'] ?? 'guest';

try {
    $pdo = getProjectDBConnection('pardis');
    $pdo->exec("SET NAMES 'utf8mb4'");

    // Step 1: Get all elements for this plan
    $elements_sql = "SELECT element_id, element_type, floor_level, axis_span, width_cm, height_cm, area_sqm, contractor, block, zone_name, geometry_json FROM elements WHERE plan_file = ? ORDER BY element_id";
    $stmt = $pdo->prepare($elements_sql);
    $stmt->execute([$plan_file]);
    $elements = $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
    
    if (empty($elements)) {
        echo json_encode([]);
        exit;
    }

    // Step 1.5: Get stage counts from templates for accurate percentages
    $templates_sql = "SELECT t.element_type, COUNT(s.stage_id) as stage_count FROM checklist_templates t JOIN inspection_stages s ON t.template_id = s.template_id GROUP BY t.element_type";
    $stage_counts_by_type = $pdo->query($templates_sql)->fetchAll(PDO::FETCH_KEY_PAIR);

    $element_ids = array_keys($elements);
    $placeholders = str_repeat('?,', count($element_ids) - 1) . '?';
    
    // Step 2: Get latest record for EVERY stage to determine status
    $stages_sql = "
        WITH LatestStageInspections AS (
            SELECT 
                i.element_id, COALESCE(i.part_name, 'default') as part_name, i.stage_id, i.status,
                i.overall_status, i.contractor_status,
                ROW_NUMBER() OVER(
                    PARTITION BY i.element_id, COALESCE(i.part_name, 'default'), i.stage_id 
                    ORDER BY i.inspection_id DESC
                ) as rn
            FROM inspections i WHERE i.element_id IN ($placeholders)
        ),
        StageStatusPriority AS (
            SELECT 
                element_id, part_name, stage_id,
                CASE WHEN status = 'Reject' THEN 1 WHEN status = 'Awaiting Re-inspection' THEN 1.5 WHEN status = 'Repair' THEN 2 WHEN overall_status = 'OK' THEN 3 WHEN status = 'Pre-Inspection Complete' OR contractor_status = 'Pre-Inspection Complete' THEN 4 ELSE 5 END as status_priority,
                CASE WHEN overall_status = 'OK' THEN 'OK' WHEN status = 'Reject' THEN 'Reject' WHEN status = 'Awaiting Re-inspection' THEN 'Awaiting Re-inspection' WHEN status = 'Repair' OR overall_status = 'Repair' THEN 'Repair' WHEN status = 'Pre-Inspection Complete' OR contractor_status = 'Pre-Inspection Complete' THEN 'Pre-Inspection Complete' ELSE 'Pending' END as final_stage_status
            FROM LatestStageInspections WHERE rn = 1
        )
        SELECT element_id, part_name, stage_id, final_stage_status, status_priority
        FROM StageStatusPriority ORDER BY element_id, part_name, stage_id
    ";
    
    $stages_stmt = $pdo->prepare($stages_sql);
    $stages_stmt->execute($element_ids);
    $stage_results = $stages_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Step 3: Process stages into a structured array
    $element_parts_data = [];
    foreach ($stage_results as $row) {
        $element_id = $row['element_id'];
        $part_name = $row['part_name'];
        if (!isset($element_parts_data[$element_id][$part_name])) {
            $element_parts_data[$element_id][$part_name] = ['stages' => [], 'worst_status_priority' => 5];
        }
        $part_data = &$element_parts_data[$element_id][$part_name];
        $part_data['stages'][] = ['stage_id' => $row['stage_id'], 'status' => $row['final_stage_status']];
        if ($row['status_priority'] < $part_data['worst_status_priority']) {
            $part_data['worst_status_priority'] = (float)$row['status_priority'];
        }
    }
    unset($part_data);

    // Step 4: Calculate completion and status for each part
    foreach ($element_parts_data as $element_id => &$parts) {
        $element_type = $elements[$element_id]['element_type'];
        $total_template_stages = (int)($stage_counts_by_type[$element_type] ?? 1);

        foreach ($parts as &$data) {
            $completed_real_stages = array_filter($data['stages'], fn($s) => $s['stage_id'] > 0 && $s['status'] === 'OK');
            
            $data['completion_percentage'] = $total_template_stages > 0 ? round((count($completed_real_stages) * 100.0) / $total_template_stages, 1) : 0.0;
            $data['total_stages'] = $total_template_stages;
            $data['completed_stages'] = count($completed_real_stages);

            switch ($data['worst_status_priority']) {
                case 1: $data['overall_status'] = 'Reject'; break;
                case 1.5: $data['overall_status'] = 'Awaiting Re-inspection'; break;
                case 2: $data['overall_status'] = 'Repair'; break;
               case 3: $data['overall_status'] = 'OK'; break;
                case 4: $data['overall_status'] = 'Pre-Inspection Complete'; break;
                default: $data['overall_status'] = 'Pending'; break;
            }
        }
    }
    unset($parts, $data);

    // Step 5: Build the final response, ensuring all keys match the frontend's expectations
    $final_elements_data = [];
    foreach ($elements as $element_id => $element) {
        $parts = $element_parts_data[$element_id] ?? null;
        
        // ==================================================================
        // START: THE DEFINITIVE FIX - Manual key mapping
        // ==================================================================
        $final_data = [
            'type' => $element['element_type'],
            'floor' => $element['floor_level'], // Map floor_level to floor
            'axis' => $element['axis_span'],     // Map axis_span to axis
            'width' => $element['width_cm'],      
            'height' => $element['height_cm'],     
            'area' => $element['area_sqm'],        
            'contractor' => $element['contractor'],
            'block' => $element['block'],
            'zoneName' => $element['zone_name'],
            'geometry' => $element['geometry_json'],
            'status' => 'Pending', // Default status
            'is_interactive' => false,
            'completion_percentage' => 0.0,
            'stages_data' => [],
            'total_stages' => (int)($stage_counts_by_type[$element['element_type']] ?? 0),
            'completed_stages' => 0
        ];
        // ==================================================================
        // END: THE DEFINITIVE FIX
        // ==================================================================

        if ($parts) {
            if ($element['element_type'] === 'GFRC' && count($parts) > 0) {
                 $total_completion = 0; $worst_priority = 5;
                 foreach($parts as $part) {
                     $total_completion += $part['completion_percentage'];
                     if ($part['worst_status_priority'] < $worst_priority) $worst_priority = $part['worst_status_priority'];
                 }
                 $final_data['completion_percentage'] = round($total_completion / count($parts), 1);
                 $final_data['total_stages'] = $parts[array_key_first($parts)]['total_stages'];
                 $final_data['stages_data'] = array_values($parts);

                 switch ($worst_priority) {
                     case 1: $final_data['status'] = 'Reject'; break;
                     case 1.5: $final_data['status'] = 'Awaiting Re-inspection'; break;
                     case 2: $final_data['status'] = 'Repair'; break;
                    case 3: $final_data['status'] = 'OK'; break;
                     case 4: $final_data['status'] = 'Pre-Inspection Complete'; break;
                     default: $final_data['status'] = 'Pending'; break;
                 }
            } else { // For non-GFRC elements
                $single_part = array_values($parts)[0];
                $final_data['status'] = $single_part['overall_status'];
                $final_data['completion_percentage'] = $single_part['completion_percentage'];
                $final_data['total_stages'] = $single_part['total_stages'];
                $final_data['completed_stages'] = $single_part['completed_stages'];
                $final_data['stages_data'] = $single_part['stages'];
            }
        }
        
        // Final interactivity check
        if ($user_role === 'superuser' || ($user_role === 'admin'|| $user_role === 'supervisor' || $user_role === 'planner'&& $final_data['status'] !== 'OK') || (in_array($user_role, ['cat', 'car', 'coa', 'crs']) && $final_data['status'] === 'Repair')) {
           $final_data['is_interactive'] = true;
        }

        $final_elements_data[$element_id] = $final_data;
    }
    
    echo json_encode($final_elements_data);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error get_plan_elements.php: " . $e->getMessage() . " on line " . $e->getLine());
    exit(json_encode(['error' => 'Database query failed.', 'details' => $e->getMessage()]));
}