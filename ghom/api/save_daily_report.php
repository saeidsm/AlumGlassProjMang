<?php
// api/save_daily_report.php - RESTRICT EDITING APPROVED REPORTS
require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/jdf.php';

// --- LOGGING FUNCTION ---
function write_log($message) {
    $logFile = __DIR__ . '/save_debug.log';
    $entry = date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$pdo = getProjectDBConnection('ghom');
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Role Definitions
$is_contractor = in_array($user_role, ['cat', 'car', 'coa', 'crs']);
$is_consultant = in_array($user_role, ['admin', 'superuser']);
$is_superuser  = ($user_role === 'superuser'); // Specific check for superuser

// Check POST Data
$report_id = $_POST['report_id'] ?? '';
$save_action = $_POST['save_action'] ?? 'draft';

// Helper: Convert Date
function convert_jalali_to_gregorian_safe($jDate) {
    if (empty($jDate)) return null;
    $jDate = explode(' ', trim($jDate))[0];
    $jDate = str_replace('-', '/', $jDate);
    $parts = explode('/', $jDate);
    if (count($parts) !== 3) return null;
    $g = jalali_to_gregorian((int)$parts[0], (int)$parts[1], (int)$parts[2]);
    return $g[0] . '-' . sprintf('%02d', $g[1]) . '-' . sprintf('%02d', $g[2]);
}

// Helper: File Upload
function upload_material_file($file_key) {
    if (!isset($_FILES[$file_key])) return null;
    if ($_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) return null;
    
    $upload_dir = __DIR__ . '/../uploads/materials/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
    
    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
    $new_name = uniqid('mat_') . '.' . $ext;
    if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $upload_dir . $new_name)) {
        return 'uploads/materials/' . $new_name;
    }
    return null;
}

try {
    $pdo->beginTransaction();
    
    // --- CHECK EXISTING REPORT & PERMISSIONS ---
    if ($report_id) {
        $stmt = $pdo->prepare("SELECT status FROM daily_reports WHERE id = ?");
        $stmt->execute([$report_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // CRITICAL: If Approved, ONLY Superuser can edit
        if ($existing && $existing['status'] === 'Approved') {
            if (!$is_superuser) {
                throw new Exception("⛔ خطا: این گزارش تایید نهایی شده است. فقط کاربر ارشد (Superuser) مجاز به ویرایش آن است.");
            }
        }
    }

    // ==================================================================================
    // BLOCK A: CONTRACTOR LOGIC (INSERT / FULL UPDATE)
    // ==================================================================================
    if ($is_contractor && $save_action !== 'consultant_review') {
        
        $new_status = ($save_action === 'submit') ? 'Submitted' : 'Draft';
        $g_date = convert_jalali_to_gregorian_safe($_POST['report_date'] ?? '');
        
        // A1. HEADER
        if (empty($report_id)) {
            // INSERT NEW
            $sql = "INSERT INTO daily_reports (report_date, contractor_fa_name, block_name, weather_list, temp_max, temp_min, problems_and_obstacles, submitted_by_user_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $g_date, 
                $_POST['contractor_fa_name'], 
                $_POST['block_name'], 
                json_encode($_POST['weather_list']??[], JSON_UNESCAPED_UNICODE), 
                $_POST['temp_max'], $_POST['temp_min'], 
                $_POST['problems_and_obstacles'], 
                $user_id, 
                $new_status
            ]);
            $report_id = $pdo->lastInsertId();
        } else {
            // UPDATE EXISTING
            $sql = "UPDATE daily_reports SET report_date=?, contractor_fa_name=?, block_name=?, weather_list=?, temp_max=?, temp_min=?, problems_and_obstacles=?, status=?, updated_at=NOW() WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $g_date, 
                $_POST['contractor_fa_name'], 
                $_POST['block_name'], 
                json_encode($_POST['weather_list']??[], JSON_UNESCAPED_UNICODE), 
                $_POST['temp_max'], $_POST['temp_min'], 
                $_POST['problems_and_obstacles'], 
                $new_status, 
                $report_id
            ]);
        }

        // A2. PERSONNEL
        $pdo->prepare("DELETE FROM daily_report_personnel WHERE report_id=?")->execute([$report_id]);
        if (!empty($_POST['personnel'])) {
            $stmt = $pdo->prepare("INSERT INTO daily_report_personnel (report_id, role_name, count) VALUES (?, ?, ?)");
            foreach ($_POST['personnel'] as $p) {
                if (empty($p['role_name'])) continue;
                $count = !empty($p['count']) ? $p['count'] : 0; // Fix here
                $stmt->execute([$report_id, $p['role_name'], $count]);
            }
        }

        // A3. MACHINERY
        $pdo->prepare("DELETE FROM daily_report_machinery WHERE report_id=?")->execute([$report_id]);
        if (!empty($_POST['machinery'])) {
            $stmt = $pdo->prepare("INSERT INTO daily_report_machinery (report_id, machine_name, total_count, active_count) VALUES (?, ?, ?, ?)");
           foreach ($_POST['machinery'] as $m) {
                if (empty($m['machine_name'])) continue;
                $total = !empty($m['total_count']) ? $m['total_count'] : 0; // Fix
                $active = !empty($m['active_count']) ? $m['active_count'] : 0; // Fix
                $stmt->execute([$report_id, $m['machine_name'], $total, $active]);
            }
        }

        // A4. MATERIALS
        $pdo->prepare("DELETE FROM daily_report_materials WHERE report_id=?")->execute([$report_id]);
        $mat_stmt = $pdo->prepare("INSERT INTO daily_report_materials (report_id, type, material_name, quantity, unit, category, date, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!empty($_POST['mat_in'])) {
            foreach ($_POST['mat_in'] as $idx => $mat) {
                if (empty($mat['name'])) continue;
                $f_path = upload_material_file('mat_in_file_' . $idx);
                $mat_stmt->execute([$report_id, 'IN', $mat['name'], $mat['quantity'], $mat['unit'], $mat['category']??'', $mat['date']??'', $f_path]);
            }
        }
        if (!empty($_POST['mat_out'])) {
            foreach ($_POST['mat_out'] as $idx => $mat) {
                if (empty($mat['name'])) continue;
                $f_path = upload_material_file('mat_out_file_' . $idx);
                $mat_stmt->execute([$report_id, 'OUT', $mat['name'], $mat['quantity'], $mat['unit'], $mat['category']??'', $mat['date']??'', $f_path]);
            }
        }

        // A5. ACTIVITIES
          $pdo->prepare("DELETE FROM daily_report_activities WHERE report_id=?")->execute([$report_id]);
        if (!empty($_POST['activities'])) {
            $act_stmt = $pdo->prepare("INSERT INTO daily_report_activities (
                report_id, activity_id, location_facade, zone_name, floor, unit, 
                contractor_quantity, contractor_meterage, personnel_count, 
                cum_open_count, cum_open_meterage, cum_rejected_count, cum_installed_count
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            
            foreach ($_POST['activities'] as $act) {
                if (empty($act['activity_id'])) continue;
                
                // FIX: Force empty strings to 0
                $qty = !empty($act['contractor_quantity']) ? $act['contractor_quantity'] : 0;
                $met = !empty($act['contractor_meterage']) ? $act['contractor_meterage'] : 0;
                $per = !empty($act['personnel_count']) ? $act['personnel_count'] : 0;
                
                // Cumulative fields
                $cum_open_cnt = !empty($act['cum_open_count']) ? $act['cum_open_count'] : 0;
                $cum_open_met = !empty($act['cum_open_meterage']) ? $act['cum_open_meterage'] : 0;
                $cum_rej      = !empty($act['cum_rejected_count']) ? $act['cum_rejected_count'] : 0;
                $cum_inst     = !empty($act['cum_installed_count']) ? $act['cum_installed_count'] : 0;

                $act_stmt->execute([
                    $report_id, 
                    $act['activity_id'], 
                    $act['location_facade'] ?? '', 
                    $act['zone_name'] ?? '', 
                    $act['floor'] ?? '', 
                    $act['unit'] ?? '', 
                    $qty, 
                    $met, 
                    $per,
                    $cum_open_cnt, 
                    $cum_open_met, 
                    $cum_rej, 
                    $cum_inst
                ]);
            }
        }
        
        // A6. MISC
        $pdo->prepare("DELETE FROM daily_report_misc WHERE report_id=?")->execute([$report_id]);
        $misc_stmt = $pdo->prepare("INSERT INTO daily_report_misc (report_id, type, description) VALUES (?, ?, ?)");
        if(!empty($_POST['misc_permit'])) foreach($_POST['misc_permit'] as $v) if($v) $misc_stmt->execute([$report_id, 'PERMIT', $v]);
        if(!empty($_POST['misc_test'])) foreach($_POST['misc_test'] as $v) if($v) $misc_stmt->execute([$report_id, 'TEST', $v]);
        if(!empty($_POST['misc_hse'])) foreach($_POST['misc_hse'] as $v) if($v) $misc_stmt->execute([$report_id, 'HSE', $v]);
    }

    // ==================================================================================
    // BLOCK B: CONSULTANT LOGIC
    // ==================================================================================
    elseif ($is_consultant && $save_action === 'consultant_review') {
        if (empty($report_id)) throw new Exception("Report ID is missing for consultant review");

        $stmt = $pdo->prepare("UPDATE daily_reports SET status=?, consultant_notes=?, consultant_note_personnel=?, consultant_note_machinery=?, consultant_note_materials=?, consultant_note_activities=?, reviewed_by_user_id=?, reviewed_at=NOW() WHERE id=?");
        $stmt->execute([$_POST['status_action'] ?? 'Submitted', $_POST['consultant_notes'] ?? '', $_POST['consultant_note_personnel'] ?? '', $_POST['consultant_note_machinery'] ?? '', $_POST['consultant_note_materials'] ?? '', $_POST['consultant_note_activities'] ?? '', $user_id, $report_id]);

        // Update Children Comments
        if (!empty($_POST['personnel'])) {
            $stmt = $pdo->prepare("UPDATE daily_report_personnel SET consultant_comment=?, consultant_count=? WHERE id=?");
            foreach ($_POST['personnel'] as $p) if(isset($p['id'])) $stmt->execute([$p['consultant_comment']??'', ($p['consultant_count']!=='')?$p['consultant_count']:null, $p['id']]);
        }
        if (!empty($_POST['machinery'])) {
            $stmt = $pdo->prepare("UPDATE daily_report_machinery SET consultant_comment=?, consultant_active_count=? WHERE id=?");
            foreach ($_POST['machinery'] as $m) if(isset($m['id'])) $stmt->execute([$m['consultant_comment']??'', ($m['consultant_active_count']!=='')?$m['consultant_active_count']:null, $m['id']]);
        }
        // Materials & Activities Updates...
        $stmt = $pdo->prepare("UPDATE daily_report_materials SET consultant_comment=?, consultant_quantity=? WHERE id=?");
        if (!empty($_POST['mat_in'])) foreach($_POST['mat_in'] as $m) if(isset($m['id'])) $stmt->execute([$m['consultant_comment']??'', ($m['consultant_quantity']!=='')?$m['consultant_quantity']:null, $m['id']]);
        if (!empty($_POST['mat_out'])) foreach($_POST['mat_out'] as $m) if(isset($m['id'])) $stmt->execute([$m['consultant_comment']??'', ($m['consultant_quantity']!=='')?$m['consultant_quantity']:null, $m['id']]);
        
        $stmt = $pdo->prepare("UPDATE daily_report_activities SET consultant_comment=?, consultant_quantity=?, consultant_meterage=? WHERE id=?");
        if (!empty($_POST['activities'])) foreach($_POST['activities'] as $act) if(isset($act['id'])) $stmt->execute([$act['consultant_comment']??'', ($act['consultant_quantity']!=='')?$act['consultant_quantity']:null, ($act['consultant_meterage']!=='')?$act['consultant_meterage']:null, $act['id']]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Saved successfully', 'id' => $report_id]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    
    // HANDLE DUPLICATE ENTRY ERROR (1062)
    if ($e->getCode() == '23000' || $e->errorInfo[1] == 1062) {
        echo json_encode([
            'success' => false, 
            'message' => '⚠️ خطا: گزارشی برای این تاریخ، پیمانکار و بلوک قبلاً ثبت شده است. لطفاً از داشبورد گزارش موجود را باز کرده و ویرایش کنید.'
        ]);
    } else {
        write_log("DB Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'خطای پایگاه داده: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    write_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>