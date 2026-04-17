<?php
// pardis/api/save_daily_report_ps.php - UPDATED FOR NEW ACTIVITY COLUMNS
require_once __DIR__ . '/../../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/jdf.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$pdo = getProjectDBConnection('pardis'); 

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$is_contractor = in_array($user_role, ['car', 'cod']); 
$is_consultant = in_array($user_role, ['admin', 'superuser', 'supervisor']);
$is_superuser = ($user_role === 'superuser');

function convert_date($jDate) {
    if(empty($jDate)) return null;
    $p = explode('/', $jDate);
    if(count($p)!==3) return null;
    $g = jalali_to_gregorian($p[0], $p[1], $p[2]);
    return implode('-', $g);
}

try {
    $pdo->beginTransaction();
    
    $id = $_POST['report_id'] ?? ''; 
    $action = $_POST['save_action'] ?? 'draft';

    // --- CHECK APPROVAL STATUS LOCK ---
    if (!empty($id)) {
        $lockCheck = $pdo->prepare("SELECT status FROM ps_daily_reports WHERE id = ?");
        $lockCheck->execute([$id]);
        $currentStatus = $lockCheck->fetchColumn();
        
        if ($currentStatus === 'Approved' && !$is_superuser) {
            throw new Exception("این گزارش تایید شده است و فقط سوپر ادمین می‌تواند آن را ویرایش کند.");
        }
    }

    // --- CONTRACTOR SAVE LOGIC ---
    if ($is_contractor && $action !== 'consultant_review') {
        $date = convert_date($_POST['report_date'] ?? '');
        $new_status = ($action === 'submit') ? 'Submitted' : 'Draft';
        
        $contractor_name = $_POST['contractor_fa_name'] ?? '';
        $block_name = $_POST['block_name'] ?? '';
        
        if (!$date || !$contractor_name || !$block_name) {
            throw new Exception("تاریخ، پیمانکار و بلوک الزامی هستند.");
        }

        // Check for duplicates
        $checkSql = "SELECT id, status FROM ps_daily_reports WHERE report_date = ? AND contractor_fa_name = ? AND block_name = ?";
        $chkStmt = $pdo->prepare($checkSql);
        $chkStmt->execute([$date, $contractor_name, $block_name]);
        $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if (empty($id)) {
                $id = $existing['id'];
            } elseif ($id != $existing['id']) {
                throw new Exception("خطا: گزارشی با این تاریخ و بلوک قبلاً ثبت شده است.");
            }
            
            if ($existing['status'] === 'Approved' && !$is_superuser) {
                throw new Exception("این گزارش تایید شده و قابل تغییر نیست.");
            }
        }

        $headerData = [
            $date, $contractor_name, $block_name, 
            json_encode($_POST['weather_list']??[]), 
            $_POST['temp_max']??'', $_POST['temp_min']??'', 
            $_POST['work_hours_day']??0, $_POST['work_hours_night']??0, 
            $_POST['contract_number']??'', $_POST['problems_and_obstacles']??'', 
            $new_status
        ];

        if(empty($id)) {
            $sql = "INSERT INTO ps_daily_reports (report_date, contractor_fa_name, block_name, weather_list, temp_max, temp_min, work_hours_day, work_hours_night, contract_number, problems_and_obstacles, status, submitted_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            $headerData[] = $user_id; 
            $stmt->execute($headerData);
            $id = $pdo->lastInsertId();
        } else {
            $prevStmt = $pdo->prepare("SELECT status FROM ps_daily_reports WHERE id = ?");
            $prevStmt->execute([$id]);
            $previous_status = $prevStmt->fetchColumn();
            
            $sql = "UPDATE ps_daily_reports SET report_date=?, contractor_fa_name=?, block_name=?, weather_list=?, temp_max=?, temp_min=?, work_hours_day=?, work_hours_night=?, contract_number=?, problems_and_obstacles=?, status=?, updated_at=NOW() WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $headerData[] = $id;
            $stmt->execute($headerData);
            
            if ($previous_status === 'Approved') {
                $auditSql = "INSERT INTO ps_report_audit_log (report_id, edited_by_user_id, edited_by_role, previous_status, new_status, edit_notes) VALUES (?, ?, ?, ?, ?, ?)";
                $pdo->prepare($auditSql)->execute([
                    $id, $user_id, $user_role, $previous_status, $new_status,
                    'گزارش تایید شده ویرایش شد'
                ]);
            }
        }

        // Personnel
        $pdo->prepare("DELETE FROM ps_daily_report_personnel WHERE report_id=?")->execute([$id]);
        if(!empty($_POST['personnel'])) {
            $stmt = $pdo->prepare("INSERT INTO ps_daily_report_personnel (report_id, role_name, count, count_night) VALUES (?,?,?,?)");
            foreach($_POST['personnel'] as $p) {
                if(!empty($p['role_name'])) {
                    $stmt->execute([$id, $p['role_name'], $p['count']??0, $p['count_night']??0]);
                }
            }
        }

        // Machinery
        $pdo->prepare("DELETE FROM ps_daily_report_machinery WHERE report_id=?")->execute([$id]);
        if(!empty($_POST['machinery'])) {
            $stmt = $pdo->prepare("INSERT INTO ps_daily_report_machinery (report_id, machine_name, unit, total_count, active_count) VALUES (?,?,?,?,?)");
            foreach($_POST['machinery'] as $m) {
                if(!empty($m['machine_name'])) {
                    $stmt->execute([
                        $id, 
                        $m['machine_name'], 
                        $m['unit'] ?? 'دستگاه', 
                        $m['total_count'] ?? 0, 
                        $m['active_count'] ?? 0
                    ]);
                }
            }
        }

        // Materials - NOW WITH UNIT
        $pdo->prepare("DELETE FROM ps_daily_report_materials WHERE report_id=?")->execute([$id]);
        $stmt = $pdo->prepare("INSERT INTO ps_daily_report_materials (report_id, type, material_name, quantity, unit) VALUES (?,?,?,?,?)");
        
        if(!empty($_POST['mat_in'])) {
            foreach($_POST['mat_in'] as $m) {
                if(!empty($m['name'])) {
                    $stmt->execute([
                        $id, 
                        'IN', 
                        $m['name'], 
                        $m['quantity']??'0', 
                        $m['unit']??'عدد'
                    ]);
                }
            }
        }
        
        if(!empty($_POST['mat_out'])) {
            foreach($_POST['mat_out'] as $m) {
                if(!empty($m['name'])) {
                    $stmt->execute([
                        $id, 
                        'OUT', 
                        $m['name'], 
                        $m['quantity']??'0', 
                        $m['unit']??'عدد'
                    ]);
                }
            }
        }

        // Activities - UPDATED FOR NEW COLUMNS
        $pdo->prepare("DELETE FROM ps_daily_report_activities WHERE report_id=?")->execute([$id]);
        if(!empty($_POST['activities'])) {
            $stmt = $pdo->prepare("INSERT INTO ps_daily_report_activities (
                report_id, activity_id, work_front, location_facade, vol_total,
                status_ongoing, status_stopped, status_finished,
                qty_day, qty_night, qty_cumulative, unit,
                pers_safety, pers_master, pers_worker
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            
            foreach($_POST['activities'] as $a) {
                if(!empty($a['activity_id'])) {
                    $stmt->execute([
                        $id,
                        $a['activity_id'],
                        $a['work_front'] ?? '',
                        $a['location_facade'] ?? '',
                        $a['vol_total'] ?? '',
                        isset($a['status_ongoing']) ? 1 : 0,
                        isset($a['status_stopped']) ? 1 : 0,
                        isset($a['status_finished']) ? 1 : 0,
                        $a['qty_day'] ?? 0,
                        $a['qty_night'] ?? 0,
                        $a['qty_cumulative'] ?? 0,
                        $a['unit'] ?? 'متر مربع',
                        $a['pers_safety'] ?? 0,
                        $a['pers_master'] ?? 0,
                        $a['pers_worker'] ?? 0
                    ]);
                }
            }
        }

        // Misc
        $pdo->prepare("DELETE FROM ps_daily_report_misc WHERE report_id=?")->execute([$id]);
        $stmt = $pdo->prepare("INSERT INTO ps_daily_report_misc (report_id, type, work_front, description) VALUES (?,?,?,?)");
        
        if(!empty($_POST['misc_permit'])) {
            foreach($_POST['misc_permit'] as $m) {
                if(!empty($m['desc'])) {
                    $stmt->execute([$id, 'PERMIT', $m['front']??'', $m['desc']]);
                }
            }
        }
        
        if(!empty($_POST['misc_test'])) {
            foreach($_POST['misc_test'] as $m) {
                if(!empty($m['desc'])) {
                    $stmt->execute([$id, 'TEST', $m['front']??'', $m['desc']]);
                }
            }
        }
        
        if(!empty($_POST['misc_hse'])) {
            foreach($_POST['misc_hse'] as $m) {
                if(!empty($m['desc'])) {
                    $stmt->execute([$id, 'HSE', '', $m['desc']]);
                }
            }
        }
    }

    // --- CONSULTANT REVIEW LOGIC ---
    elseif ($is_consultant && $action === 'consultant_review') {
        
        $prevStmt = $pdo->prepare("SELECT status FROM ps_daily_reports WHERE id = ?");
        $prevStmt->execute([$id]);
        $previous_status = $prevStmt->fetchColumn();
        
        $review_status = $_POST['review_status'] ?? 'Approved';
        
        // Update main report
        $stmt = $pdo->prepare("UPDATE ps_daily_reports SET 
            status = ?, 
            consultant_notes = ?, 
            consultant_note_personnel = ?,
            consultant_note_machinery = ?,
            consultant_note_materials = ?,
            consultant_note_activities = ?,
            reviewed_by_user_id = ?, 
            reviewed_at = NOW() 
            WHERE id = ?");
            
        $stmt->execute([
            $review_status,
            $_POST['consultant_notes'] ?? '',
            $_POST['consultant_note_personnel'] ?? '',
            $_POST['consultant_note_machinery'] ?? '',
            $_POST['consultant_note_materials'] ?? '',
            $_POST['consultant_note_activities'] ?? '',
            $user_id, 
            $id
        ]);
        
        // Log review
        $auditSql = "INSERT INTO ps_report_audit_log (report_id, edited_by_user_id, edited_by_role, previous_status, new_status, edit_notes) VALUES (?, ?, ?, ?, ?, ?)";
        $pdo->prepare($auditSql)->execute([
            $id, $user_id, $user_role, $previous_status, $review_status,
            'بررسی نظارت انجام شد'
        ]);
        
        // Update Personnel reviews
        if(!empty($_POST['personnel'])) {
            $upd = $pdo->prepare("UPDATE ps_daily_report_personnel SET consultant_count=?, consultant_comment=? WHERE id=?");
            foreach($_POST['personnel'] as $p) {
                if(!empty($p['id'])) {
                    $upd->execute([
                        !empty($p['consultant_count']) ? $p['consultant_count'] : null, 
                        $p['consultant_comment']??'', 
                        $p['id']
                    ]);
                }
            }
        }

        // Update Machinery reviews
        if(!empty($_POST['machinery'])) {
            $upd = $pdo->prepare("UPDATE ps_daily_report_machinery SET consultant_active_count=?, consultant_comment=? WHERE id=?");
            foreach($_POST['machinery'] as $m) {
                if(!empty($m['id'])) {
                    $upd->execute([
                        !empty($m['consultant_active_count']) ? $m['consultant_active_count'] : null, 
                        $m['consultant_comment']??'', 
                        $m['id']
                    ]);
                }
            }
        }

        // Update Materials reviews
        $updMat = $pdo->prepare("UPDATE ps_daily_report_materials SET consultant_quantity=?, consultant_comment=? WHERE id=?");
        if(!empty($_POST['mat_in'])) {
            foreach($_POST['mat_in'] as $m) {
                if(!empty($m['id'])) {
                    $updMat->execute([
                        !empty($m['consultant_quantity']) ? $m['consultant_quantity'] : null, 
                        $m['consultant_comment']??'', 
                        $m['id']
                    ]);
                }
            }
        }
        if(!empty($_POST['mat_out'])) {
            foreach($_POST['mat_out'] as $m) {
                if(!empty($m['id'])) {
                    $updMat->execute([
                        !empty($m['consultant_quantity']) ? $m['consultant_quantity'] : null, 
                        $m['consultant_comment']??'', 
                        $m['id']
                    ]);
                }
            }
        }

        // Update Activities reviews - UPDATED FOR NEW COLUMNS
        if(!empty($_POST['activities'])) {
            $upd = $pdo->prepare("UPDATE ps_daily_report_activities SET 
                consultant_qty_day=?, 
                consultant_qty_night=?, 
                consultant_qty_cumulative=?, 
                consultant_comment=? 
                WHERE id=?");
            
            foreach($_POST['activities'] as $a) {
                if(!empty($a['id'])) {
                    $upd->execute([
                        !empty($a['consultant_qty_day']) ? $a['consultant_qty_day'] : null,
                        !empty($a['consultant_qty_night']) ? $a['consultant_qty_night'] : null,
                        !empty($a['consultant_qty_cumulative']) ? $a['consultant_qty_cumulative'] : null,
                        $a['consultant_comment'] ?? '',
                        $a['id']
                    ]);
                }
            }
        }
    }
// Material Documents
if($is_contractor && $action !== 'consultant_review') {
    $pdo->prepare("DELETE FROM ps_daily_report_material_docs WHERE report_id=?")->execute([$id]);
    
    if(!empty($_POST['material_docs'])) {
        $uploadDir = __DIR__ . '/../uploads/material_docs/';
        if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $stmt = $pdo->prepare("INSERT INTO ps_daily_report_material_docs (report_id, material_name, type, description, file_path) VALUES (?,?,?,?,?)");
        
        foreach($_POST['material_docs'] as $idx => $doc) {
            if(empty($doc['material_name'])) continue;
            
            $filePath = $doc['existing_file'] ?? '';
            
            // Handle file upload
            $fileKey = "material_doc_file_" . $idx;
            if(isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === 0) {
                $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
                $filename = 'mat_' . $id . '_' . time() . '_' . $idx . '.' . $ext;
                $targetPath = $uploadDir . $filename;
                
                if(move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetPath)) {
                    $filePath = '/pardis/uploads/material_docs/' . $filename;
                }
            }
            
            $stmt->execute([
                $id,
                $doc['material_name'],
                $doc['type'] ?? 'IN',
                $doc['description'] ?? '',
                $filePath
            ]);
        }
    }
    
    // Daily Photos
    $pdo->prepare("DELETE FROM ps_daily_report_photos WHERE report_id=?")->execute([$id]);
    
    if(!empty($_POST['daily_photos'])) {
        $uploadDir = __DIR__ . '/../uploads/daily_photos/';
        if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $stmt = $pdo->prepare("INSERT INTO ps_daily_report_photos (report_id, photo_path, caption) VALUES (?,?,?)");
        
        foreach($_POST['daily_photos'] as $idx => $photo) {
            $photoPath = $photo['existing_path'] ?? '';
            
            // Handle base64 image upload (from preview)
            if(!empty($photo['file_data']) && strpos($photo['file_data'], 'data:image') === 0) {
                list($type, $data) = explode(';', $photo['file_data']);
                list(, $data) = explode(',', $data);
                $data = base64_decode($data);
                
                $ext = 'jpg';
                if(strpos($type, 'png')) $ext = 'png';
                
                $filename = 'photo_' . $id . '_' . time() . '_' . $idx . '.' . $ext;
                $targetPath = $uploadDir . $filename;
                
                if(file_put_contents($targetPath, $data)) {
                    $photoPath = '/pardis/uploads/daily_photos/' . $filename;
                }
            }
            
            if(!empty($photoPath)) {
                $stmt->execute([
                    $id,
                    $photoPath,
                    $photo['caption'] ?? ''
                ]);
            }
        }
    }
}
    $pdo->commit();
    echo json_encode(['success' => true, 'report_id' => $id, 'message' => 'گزارش با موفقیت ذخیره شد.']);
    
} catch(Exception $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}