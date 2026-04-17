<?php
require_once __DIR__ . '/../../../sercon/bootstrap.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$pdo = getProjectDBConnection('ghom');
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$is_contractor = in_array($user_role, ['cat', 'car', 'coa', 'crs']);

try {
    $pdo->beginTransaction();
    $report_id = $_POST['report_id'] ?? '';

    // 1. Save/Update Main Report
    $weather_json = json_encode($_POST['weather_list'] ?? [], JSON_UNESCAPED_UNICODE);
    
    if (empty($report_id)) {
        $sql = "INSERT INTO daily_reports (report_date, contractor_fa_name, block_name, weather_list, temp_max, temp_min, problems_and_obstacles, submitted_by_user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Submitted')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['report_date'], $_POST['contractor_fa_name'], $_POST['block_name'], 
            $weather_json, $_POST['temp_max'], $_POST['temp_min'], 
            $_POST['problems_and_obstacles'], $user_id
        ]);
        $report_id = $pdo->lastInsertId();
    } else {
        if ($is_contractor) {
            $sql = "UPDATE daily_reports SET weather_list=?, temp_max=?, temp_min=?, problems_and_obstacles=? WHERE id=?";
            $pdo->prepare($sql)->execute([$weather_json, $_POST['temp_max'], $_POST['temp_min'], $_POST['problems_and_obstacles'], $report_id]);
        } else {
            $pdo->prepare("UPDATE daily_reports SET status='Reviewed_by_Consultant' WHERE id=?")->execute([$report_id]);
        }
    }

    // Helper to replace child table data (Delete All -> Insert New is safest for simple lists)
    function replace_child_rows($pdo, $table, $report_id, $data_arrays, $col_names) {
        $pdo->prepare("DELETE FROM $table WHERE report_id = ?")->execute([$report_id]);
        if (empty($data_arrays[0])) return;
        
        $count = count($data_arrays[0]);
        $placeholders = implode(',', array_fill(0, count($col_names) + 1, '?')); // +1 for report_id
        $sql = "INSERT INTO $table (report_id, " . implode(',', $col_names) . ") VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);

        for ($i = 0; $i < $count; $i++) {
            $params = [$report_id];
            $has_data = false;
            foreach ($data_arrays as $arr) {
                $val = $arr[$i] ?? '';
                if (trim($val) !== '') $has_data = true;
                $params[] = $val;
            }
            // Only insert if at least one field has data
            if ($has_data) $stmt->execute($params);
        }
    }

    if ($is_contractor) {
        // 2. Save Personnel
        replace_child_rows($pdo, 'daily_report_personnel', $report_id, 
            [$_POST['personnel_role'], $_POST['personnel_count']], 
            ['role_name', 'count']);

        // 3. Save Machinery
        replace_child_rows($pdo, 'daily_report_machinery', $report_id, 
            [$_POST['machinery_name'], $_POST['machinery_total'], $_POST['machinery_active']], 
            ['machine_name', 'total_count', 'active_count']);

        // 4. Save Materials (IN & OUT)
        $pdo->prepare("DELETE FROM daily_report_materials WHERE report_id = ?")->execute([$report_id]);
        // IN
        if (!empty($_POST['mat_in_name'])) {
            $stmt = $pdo->prepare("INSERT INTO daily_report_materials (report_id, material_name, quantity, unit, type) VALUES (?, ?, ?, ?, 'IN')");
            for($i=0; $i<count($_POST['mat_in_name']); $i++) {
                if($_POST['mat_in_name'][$i]) $stmt->execute([$report_id, $_POST['mat_in_name'][$i], $_POST['mat_in_qty'][$i], $_POST['mat_in_unit'][$i]]);
            }
        }
        // OUT
        if (!empty($_POST['mat_out_name'])) {
            $stmt = $pdo->prepare("INSERT INTO daily_report_materials (report_id, material_name, quantity, unit, type) VALUES (?, ?, ?, ?, 'OUT')");
            for($i=0; $i<count($_POST['mat_out_name']); $i++) {
                if($_POST['mat_out_name'][$i]) $stmt->execute([$report_id, $_POST['mat_out_name'][$i], $_POST['mat_out_qty'][$i], $_POST['mat_out_unit'][$i]]);
            }
        }

        // 5. Save Misc (Permits, Tests, HSE)
        $pdo->prepare("DELETE FROM daily_report_misc WHERE report_id = ?")->execute([$report_id]);
        $stmt = $pdo->prepare("INSERT INTO daily_report_misc (report_id, type, description) VALUES (?, ?, ?)");
        
        foreach($_POST['misc_permit'] ?? [] as $v) if($v) $stmt->execute([$report_id, 'PERMIT', $v]);
        foreach($_POST['misc_test'] ?? [] as $v) if($v) $stmt->execute([$report_id, 'TEST', $v]);
        foreach($_POST['misc_hse'] ?? [] as $v) if($v) $stmt->execute([$report_id, 'HSE', $v]);
    }

    // 6. Save Activities (Complex logic handled previously is fine, ensuring IDs are passed)
    if (isset($_POST['activities']) && is_array($_POST['activities'])) {
         foreach ($_POST['activities'] as $act) {
            // (Insert/Update logic remains similar to previous correct version)
             if ($is_contractor) {
                if (isset($act['id']) && !empty($act['id'])) {
                     $pdo->prepare("UPDATE daily_report_activities SET activity_id=?, zone_name=?, floor=?, contractor_quantity=?, contractor_meterage=? WHERE id=?")
                         ->execute([$act['activity_id'], $act['zone_name'], $act['floor'], $act['contractor_quantity'], $act['contractor_meterage'], $act['id']]);
                } else {
                     $pdo->prepare("INSERT INTO daily_report_activities (report_id, activity_id, zone_name, floor, contractor_quantity, contractor_meterage) VALUES (?, ?, ?, ?, ?, ?)")
                         ->execute([$report_id, $act['activity_id'], $act['zone_name'], $act['floor'], $act['contractor_quantity'], $act['contractor_meterage']]);
                }
            } else {
                if (isset($act['id']) && !empty($act['id'])) {
                    $pdo->prepare("UPDATE daily_report_activities SET consultant_quantity=?, consultant_meterage=? WHERE id=?")
                        ->execute([$act['consultant_quantity'], $act['consultant_meterage'], $act['id']]);
                }
            }
         }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>