<?php
// public_html/pardis/form_api.php
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

header('Content-Type: application/json; charset=utf-8');

secureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = getProjectDBConnection('pardis');
      $common_pdo = getCommonDBConnection(); 
       $letter_pdo = getLetterTrackingDBConnection();
} catch (Exception $e) {
    logError("Database connection failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

$common_pdo = getCommonDBConnection();
$letter_pdo = getLetterTrackingDBConnection();
// Helper function for Jalali to Gregorian conversion
function toGregorian($jalaliDate) {
    if (empty($jalaliDate) || !is_string($jalaliDate)) {
        return null;
    }

    // --- THIS IS THE FIX ---
    // Convert any Persian/Arabic numbers to English before processing.
    $jalaliDate = convertPersianToEnglish($jalaliDate);

    $parts = array_map('intval', preg_split('/[-\/]/', trim($jalaliDate)));
    if (count($parts) !== 3 || $parts[0] < 1300) {
        return null;
    }
    if (function_exists('jalali_to_gregorian')) {
        $g = jalali_to_gregorian($parts[0], $parts[1], $parts[2]);
        return $g[0] . '-' . str_pad($g[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($g[2], 2, '0', STR_PAD_LEFT);
    }
    return null;
}

function toJalali($gregorian_date) {
    if (empty($gregorian_date) || strpos($gregorian_date, '0000-00-00') === 0) {
        return '-';
    }
    $parts = explode(' ', $gregorian_date);
    $date_part = $parts[0];
    $date_parts = explode('-', $date_part);
    if (count($date_parts) !== 3 || (int)$date_parts[0] < 1900) {
        return $gregorian_date;
    }
    list($y, $m, $d) = $date_parts;
    if (function_exists('gregorian_to_jalali')) {
        $j = gregorian_to_jalali((int)$y, (int)$m, (int)$d);
        return $j[0] . '/' . str_pad($j[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($j[2], 2, '0', STR_PAD_LEFT);
    }
    return $gregorian_date;
}

switch ($action) {
    case 'get_next_meeting_number':
        echo json_encode(getNextMeetingNumber($pdo, $_GET['prefix'] ?? 'G'));
        break;
    
    case 'save_meeting_minutes':
        echo json_encode(saveMeetingMinutes($pdo, $_POST, $user_id));
        break;
    
     case 'get_meeting_minutes_list':
        // --- THIS IS THE SECOND PART OF THE FIX ---
        // The function call now correctly provides all four required arguments.
        echo json_encode(getMeetingMinutesList($pdo, $common_pdo, $letter_pdo, $_GET));
        break;
    
    case 'get_meeting_minutes':
        echo json_encode(getMeetingMinutes($pdo, $_GET['id'] ?? 0));
        break;
    
    case 'delete_meeting_minutes':
        echo json_encode(deleteMeetingMinutes($pdo, $_POST['id'] ?? 0, $user_id));
        break;
    
    case 'get_form_statistics':
        echo json_encode(getFormStatistics($pdo));
        break;
    
    case 'upload_handwritten_form':
        echo json_encode(uploadHandwrittenForm($pdo, $_FILES, $_POST, $user_id));
        break;
    
    case 'get_uploaded_files':
        echo json_encode(getUploadedFiles($pdo, $_GET['meeting_number'] ?? ''));
        break;
    
    case 'delete_uploaded_file':
        echo json_encode(deleteUploadedFile($pdo, $_POST['file_id'] ?? 0, $user_id));
        break;
    case 'update_meeting_status':
        echo json_encode(updateMeetingStatus($pdo, $_POST, $user_id));
        break;

        case 'update_meeting_date_time':
    echo json_encode(updateMeetingDateTime($pdo, $_POST, $user_id));
    break;

case 'reserve_blank_form':
    // UPDATED: Pass meeting_date from POST if provided
    $meeting_date = $_POST['meeting_date'] ?? null;
    echo json_encode(reserveBlankForm($pdo, $_POST['prefix'] ?? 'G', $user_id, $meeting_date));
    break;
     case 'get_pending_handwritten_forms':
        echo json_encode(getPendingHandwrittenForms($pdo));
        break;
    case 'get_all_uploaded_files':
         echo json_encode(getAllUploadedFiles($pdo, $common_pdo, $_GET));
         break;
    case 'generate_pdf':
    echo json_encode(generateMeetingPDF($pdo, $_GET['id'] ?? 0));
    break;
        case 'get_all_minutes':
        echo json_encode(getAllMinutes($pdo, $common_pdo, $_GET));
        break;

    // ADD THIS NEW CASE for saving external minutes
    case 'log_external_minute':
        echo json_encode(logExternalMinute($pdo, $_POST, $_FILES, $user_id));
        break;
        case 'save_default_attendees':
    echo json_encode(saveDefaultAttendees($pdo, $_POST, $user_id));
    break;

case 'get_default_attendees':
    echo json_encode(getDefaultAttendees($pdo, $user_id));
    break;
case 'get_attendees_list':
    echo json_encode(getAttendeesList($pdo, $user_id));
    break;

case 'save_attendee':
    echo json_encode(saveAttendee($pdo, $_POST, $user_id));
    break;

case 'delete_attendee':
    echo json_encode(deleteAttendee($pdo, $_POST['id'] ?? 0, $user_id));
    break;

case 'save_selected_attendees':
    echo json_encode(saveSelectedAttendees($pdo, $_POST, $user_id));
    break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}


function saveSelectedAttendees($pdo, $data, $user_id) {
    try {
        $meeting_id = $data['meeting_id'];
        $attendees = $data['attendees'] ?? '';
        $observers = $data['observers'] ?? '';
        $contractor = $data['contractor'] ?? '';
        
        $sql = "UPDATE meeting_minutes 
                SET attendees = ?, observers = ?, contractor = ?, updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$attendees, $observers, $contractor, $meeting_id]);
        
        return ['success' => true, 'message' => 'حاضرین با موفقیت ذخیره شد'];
    } catch (Exception $e) {
        logError("Error saving selected attendees: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function saveDefaultAttendees($pdo, $data, $user_id) {
    try {
        $pdo->beginTransaction();
        
        $default_attendees = $data['default_attendees'] ?? '';
        $default_observers = $data['default_observers'] ?? '';
        $default_contractor = $data['default_contractor'] ?? '';
        
        // Save or update each preference
        $preferences = [
            'default_attendees' => $default_attendees,
            'default_observers' => $default_observers,
            'default_contractor' => $default_contractor
        ];
        
        $sql = "INSERT INTO user_preferences (user_id, preference_key, preference_value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value), updated_at = NOW()";
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($preferences as $key => $value) {
            $stmt->execute([$user_id, $key, $value]);
        }
        
        // Also save to session for immediate use
        $_SESSION['default_attendees'] = $default_attendees;
        $_SESSION['default_observers'] = $default_observers;
        $_SESSION['default_contractor'] = $default_contractor;
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'تنظیمات پیش‌فرض با موفقیت ذخیره شد'
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("Error saving default attendees: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در ذخیره تنظیمات: ' . $e->getMessage()
        ];
    }
}

function getDefaultAttendees($pdo, $user_id) {
    try {
        // First check if we have them in session
        if (isset($_SESSION['default_attendees'])) {
            return [
                'success' => true,
                'data' => [
                    'default_attendees' => $_SESSION['default_attendees'] ?? '',
                    'default_observers' => $_SESSION['default_observers'] ?? '',
                    'default_contractor' => $_SESSION['default_contractor'] ?? ''
                ]
            ];
        }
        
        // Otherwise fetch from database
        $sql = "SELECT preference_key, preference_value 
                FROM user_preferences 
                WHERE user_id = ? AND preference_key IN ('default_attendees', 'default_observers', 'default_contractor')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $prefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [
            'default_attendees' => '',
            'default_observers' => '',
            'default_contractor' => ''
        ];
        
        foreach ($prefs as $pref) {
            $data[$pref['preference_key']] = $pref['preference_value'];
            $_SESSION[$pref['preference_key']] = $pref['preference_value'];
        }
        
        return [
            'success' => true,
            'data' => $data
        ];
        
    } catch (Exception $e) {
        logError("Error getting default attendees: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در بارگذاری تنظیمات: ' . $e->getMessage()
        ];
    }
}

function updateMeetingStatus($pdo, $data, $user_id) {
    $meeting_id = intval($data['meeting_id'] ?? 0);
    $new_status = trim($data['new_status'] ?? '');

    // Basic validation
    if ($meeting_id <= 0 || empty($new_status)) {
        return ['success' => false, 'message' => 'اطلاعات نامعتبر ارسال شده است.'];
    }
    
    // Security: Define which statuses a user is allowed to switch to.
    $allowed_statuses = ['completed', 'archived', 'draft'];
    if (!in_array($new_status, $allowed_statuses)) {
        return ['success' => false, 'message' => 'تغییر به این وضعیت مجاز نیست.'];
    }

    try {
        $pdo->beginTransaction();

        $sql = "UPDATE meeting_minutes SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_status, $meeting_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('صورتجلسه یافت نشد یا تغییری ایجاد نشد.');
        }

        // Log the activity for auditing
        $log_sql = "INSERT INTO form_activity_log (form_type, form_id, action, description, user_id)
                   VALUES ('meeting_minutes', ?, 'status_changed', ?, ?)";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            $meeting_id,
            "وضعیت به '{$new_status}' تغییر یافت",
            $user_id
        ]);

        $pdo->commit();

        return ['success' => true, 'message' => 'وضعیت با موفقیت به‌روزرسانی شد.'];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("Error updating meeting status: " . $e->getMessage());
        return ['success' => false, 'message' => 'خطا در به‌روزرسانی وضعیت: ' . $e->getMessage()];
    }
}



function updateMeetingDateTime($pdo, $data, $user_id) {
    try {
        $meeting_id = $data['meeting_id'] ?? 0;
        $meeting_date = $data['meeting_date'] ?? '';
        $meeting_time = $data['meeting_time'] ?? '';
        
        // Convert empty time to NULL for database
        if (empty($meeting_time) || $meeting_time === '00:00:00' || $meeting_time === '00:00' || $meeting_time === '____:____') {
            $meeting_time = null;
        }
        
        if (!$meeting_id) {
            throw new Exception('شناسه صورتجلسه یافت نشد');
        }
        
        if (empty($meeting_date)) {
            throw new Exception('تاریخ الزامی است');
        }
        
        // Convert Jalali to Gregorian
        $gregorian_date = toGregorian($meeting_date);
        
        if (!$gregorian_date) {
            throw new Exception('فرمت تاریخ نامعتبر است');
        }
        
        $pdo->beginTransaction();
        
        // Update the meeting
        $sql = "UPDATE meeting_minutes 
                SET meeting_date = ?, 
                    meeting_time = ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$gregorian_date, $meeting_time, $meeting_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('صورتجلسه یافت نشد یا تغییری ایجاد نشد');
        }
        
        // Log the activity
        $time_display = $meeting_time ? $meeting_time : 'خالی';
        $log_sql = "INSERT INTO form_activity_log 
                    (form_type, form_id, action, description, user_id)
                    VALUES ('meeting_minutes', ?, 'date_time_updated', ?, ?)";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            $meeting_id,
            "تاریخ به {$meeting_date} و ساعت به {$time_display} تغییر یافت",
            $user_id
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'تاریخ و ساعت با موفقیت به‌روزرسانی شد'
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("Error updating meeting date/time: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا: ' . $e->getMessage()
        ];
    }
}

function logExternalMinute($pdo, $data, $files, $user_id) {
    $file_path_relative = '';
    try {
        // Validation
       if (empty($data['meeting_number'])) throw new Exception('شماره صورتجلسه خارجی الزامی است.');
        if (empty($data['company_id'])) throw new Exception('شرکت انتخاب نشده است.');
        if (empty($data['agenda'])) throw new Exception('موضوع الزامی است.');
        if (!isset($files['file']) || $files['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('فایل انتخاب نشده یا در آپلود آن خطا رخ داده است.');
        }

        $pdo->beginTransaction();

        $meeting_date = toGregorian($data['meeting_date']);
        if (!$meeting_date) throw new Exception('فرمت تاریخ نامعتبر است.');
        
        // Move uploaded file
        $file = $files['file'];
        $upload_dir = __DIR__ . '/uploads/meeting_minutes/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe_agenda = preg_replace('/[^a-zA-Z0-9-_\.]/', '', str_replace(' ', '_', $data['agenda']));
        $new_filename = 'EXT_' . $safe_agenda . '_' . time() . '.' . $extension;
        $file_path_full = $upload_dir . $new_filename;
        $file_path_relative = 'uploads/meeting_minutes/' . $new_filename;

        if (!move_uploaded_file($file['tmp_name'], $file_path_full)) {
            throw new Exception('خطا در ذخیره فایل روی سرور.');
        }
        
        // Insert into meeting_minutes
         $sql = "INSERT INTO meeting_minutes 
                (meeting_number, agenda, meeting_date, source, company_id, related_letter_id, extracted_text, status, created_by)
                VALUES (?, ?, ?, 'external', ?, ?, ?, 'completed', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['meeting_number'], // Save the manually entered number
            $data['agenda'],
            $meeting_date,
            $data['company_id'],
            empty($data['related_letter_id']) ? null : $data['related_letter_id'],
            empty($data['extracted_text']) ? null : $data['extracted_text'], // Save the OCR text
            $user_id
        ]);
        $meeting_id = $pdo->lastInsertId();
        // Insert into form_attachments
        $att_sql = "INSERT INTO form_attachments
                    (form_type, form_id, file_name, file_path, file_type, file_size, uploaded_by)
                    VALUES ('meeting_minutes', ?, ?, ?, ?, ?, ?)";
        $att_stmt = $pdo->prepare($att_sql);
        $att_stmt->execute([
            $meeting_id,
            $file['name'],
            $file_path_relative,
            $file['type'],
            $file['size'],
            $user_id
        ]);
        
        $pdo->commit();
        
        return ['success' => true, 'message' => 'صورتجلسه خارجی با موفقیت ثبت شد.'];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (!empty($file_path_relative) && file_exists(__DIR__ . '/' . $file_path_relative)) {
            unlink(__DIR__ . '/' . $file_path_relative);
        }
        logError("Error in logExternalMinute: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function generateMeetingPDF($pdo, $meeting_id) {
    try {
        // Get meeting data
        $sql = "SELECT * FROM meeting_minutes WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$meeting_id]);
        $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$meeting) {
            return ['success' => false, 'message' => 'صورتجلسه یافت نشد'];
        }
        
        // --- THE CRITICAL FIX IS HERE ---
        // The column `row_number` is now correctly wrapped in backticks.
        $items_sql = "SELECT * FROM meeting_minutes_items WHERE meeting_id = ? ORDER BY `row_number`";
        
        $items_stmt = $pdo->prepare($items_sql);
        $items_stmt->execute([$meeting_id]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get signatures
        $sig_sql = "SELECT * FROM meeting_minutes_signatures WHERE meeting_id = ?";
        $sig_stmt = $pdo->prepare($sig_sql);
        $sig_stmt->execute([$meeting_id]);
        $signatures = $sig_stmt->fetchAll(PDO::FETCH_ASSOC);
        $sig_array = [];
        foreach ($signatures as $sig) {
            $sig_array[$sig['signature_type']] = $sig;
        }
        
        // Calculate pages needed
        $pages = max(1, ceil(count($items) / 9));
        if ($pages > 5) $pages = 5;
        
        // Initialize TCPDF
        require_once __DIR__ . '/includes/libraries/TCPDF-main/tcpdf.php';
        
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        $pdf->SetCreator('Pardis Forms System');
        $pdf->SetAuthor('دانشگاه خاتم پردیس');
        $pdf->SetTitle('صورتجلسه ' . $meeting['meeting_number']);
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        
        $pdf->setRTL(true);
        $pdf->SetFont('dejavusans', '', 10);
        
        $building_names = [
            'AG' => 'ساختمان دانشکده کشاورزی',
            'LB' => 'ساختمان کتابخانه',
            'SK'=> 'اسکای لایت',
            'G' => 'عمومی'
        ];
        $building_name = $building_names[$meeting['building_prefix']] ?? 'عمومی';
        
        $meeting_date_jalali = toJalali($meeting['meeting_date']);
        
        for ($page = 1; $page <= $pages; $page++) {
            $pdf->AddPage();
            $html = generatePDFPageContent($meeting, $items, $sig_array, $page, $pages, $building_name, $meeting_date_jalali);
            $pdf->writeHTML($html, true, false, true, false, '');
        }
        
        $pdf_dir = __DIR__ . '/uploads/meeting_minutes_pdf/';
        if (!is_dir($pdf_dir)) {
            mkdir($pdf_dir, 0755, true);
        }
        
        $pdf_filename = $meeting['meeting_number'] . '_' . time() . '.pdf';
        $pdf_path = $pdf_dir . $pdf_filename;
        
        $pdf->Output($pdf_path, 'F');
        
        $update_sql = "UPDATE meeting_minutes SET pdf_file = ?, pdf_generated_at = NOW() WHERE id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute(['uploads/meeting_minutes_pdf/' . $pdf_filename, $meeting_id]);
        
        return [
            'success' => true,
            'pdf_url' => 'uploads/meeting_minutes_pdf/' . $pdf_filename,
            'message' => 'فایل PDF با موفقیت ایجاد شد'
        ];
        
    } catch (Exception $e) {
        logError("Error generating PDF: " . $e->getMessage());
        return ['success' => false, 'message' => 'خطا در تولید PDF: ' . $e->getMessage()];
    }
}

function generatePDFPageContent($meeting, $items, $signatures, $page, $total_pages, $building_name, $meeting_date_jalali) {
    // Logo paths
    $logo_path_khatam = __DIR__ . '/assets/images/خاتم.jpg';
    $logo_path_alum = __DIR__ . '/assets/images/alumglass-farsi-logo-H40.png';
    
    $logo_html_khatam = '';
    if (file_exists($logo_path_khatam)) {
        $logo_html_khatam = '<img src="' . $logo_path_khatam . '" style="width: 70px; height: 70px;">';
    } else {
        $logo_html_khatam = '<div style="width: 70px; height: 70px; border: 1px solid #999;"></div>';
    }
    
    $logo_html_alum = '';
    if (file_exists($logo_path_alum)) {
        $logo_html_alum = '<img src="' . $logo_path_alum . '" style="width: 70px; height: 40px;">';
    } else {
        $logo_html_alum = '<div style="width: 70px; height: 40px; border: 1px solid #999; text-align: center; line-height: 12px; font-size: 7px; padding: 2px;">مدیریت راهبردی: آلومنیوم شیشه تهران</div>';
    }
    
    $html = '
    <style>
        table { 
            border-collapse: collapse; 
            width: 100%; 
            direction: rtl;
        }
        th, td { 
            border: 1px solid #000; 
            padding: 5px; 
            text-align: right;
            font-family: dejavusans;
        }
        th { 
            background-color: #f0f0f0; 
            font-weight: bold;
        }
        .header-table td {
            vertical-align: middle;
        }
        .form-title {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
        }
        .building-name {
            text-align: center;
            font-size: 11pt;
            margin-top: 5px;
        }
        .company-info {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 9pt;
        }
        .info-row td {
            padding: 5px 8px;
        }
        .label {
            font-weight: bold;
        }
        .items-table th {
            background-color: #f0f0f0;
            text-align: center;
            font-weight: bold;
        }
        .items-table td {
            min-height: 40px;
        }
        .row-number {
            text-align: center;
            width: 3%;
        }
        .description-col {
            width: 67%;
        }
        .follower-col {
            width: 12%;
        }
        .deadline-col {
            width: 18%;
            text-align: center;
        }
        .signature-section {
            margin-top: 10px;
            text-align: right;
            border-top: 1px solid #000;
            padding-top: 10px;
        }
        .signature-label {
            font-weight: bold;
            margin-bottom: 5px;
        }
    </style>
    
    <!-- HEADER -->
    <table class="header-table">
        <tr>
            <td style="width: 15%; text-align: center;">' . $logo_html_khatam . '</td>
            <td style="width: 70%; text-align: center;">
                <div class="form-title">فرم صورتجلسه</div>
                <div class="building-name">' . htmlspecialchars($building_name) . '</div>
                <div class="company-info">
                    <span style="text-align: right;">کارفرما: موسسه مدیریت راهبردی خاتم پاسارگاد</span>
                    <span style="text-align: left;">مدیریت راهبردی: آلومنیوم شیشه تهران</span>
                </div>
            </td>
            <td style="width: 15%; text-align: center;">' . $logo_html_alum . '</td>
        </tr>
    </table>
    
    <table class="info-row">
        <tr>
            <td style="width: 30%;"><span class="label">شماره صورتجلسه:</span> ' . htmlspecialchars($meeting['meeting_number']) . '</td>
            <td style="width: 70%;" colspan="2"><span class="label">دستور جلسه:</span> ' . htmlspecialchars($meeting['agenda'] ?? '') . '</td>
        </tr>
    </table>
    
    <table class="info-row">
        <tr>
            <td style="width: 50%;"><span class="label">تاریخ:</span> ' . $meeting_date_jalali . '</td>
            <td style="width: 50%;"><span class="label">ساعت:</span> ' . htmlspecialchars($meeting['meeting_time'] ?? '') . '</td>
        </tr>
    </table>
    
    <table class="info-row">
        <tr>
            <td><span class="label">محل تشکیل جلسه:</span> ' . htmlspecialchars($meeting['location'] ?? '') . '</td>
        </tr>
    </table>
    
    <table class="info-row">
        <tr>
            <td><span class="label">حاضرین کارفرما:</span> ' . nl2br(htmlspecialchars($meeting['attendees'] ?? '')) . '</td>
        </tr>
    </table>
    
    <table class="info-row">
        <tr>
            <td><span class="label">حاضرین نظارت:</span> ' . nl2br(htmlspecialchars($meeting['observers'] ?? '')) . '</td>
        </tr>
    </table>
    
    <table class="info-row">
        <tr>
            <td><span class="label">حاضرین پیمانکار:</span> ' . nl2br(htmlspecialchars($meeting['contractor'] ?? '')) . '</td>
        </tr>
    </table>
    
    <table class="items-table">
        <thead>
            <tr>
                <th class="row-number">ردیف</th>
                <th class="description-col">خلاصه مذاکرات و تصمیمات متخذه</th>
                <th class="follower-col">پیگیری کننده</th>
                <th class="deadline-col">تاریخ سررسید</th>
            </tr>
        </thead>
        <tbody>';
    
    // Add items for this page
    $start = ($page - 1) * 9;
    
    for ($i = $start; $i < $start + 9; $i++) {
        $row_num = $i + 1;
        $item = isset($items[$i]) ? $items[$i] : null;
        
        $description = $item ? nl2br(htmlspecialchars($item['description'] ?? '')) : '';
        $follower = $item ? htmlspecialchars($item['follower'] ?? '') : '';
        $deadline = $item ? toJalali($item['deadline']) : '';
        
        $html .= '
            <tr>
                <td class="row-number">' . $row_num . '</td>
                <td class="description-col">' . $description . '</td>
                <td class="follower-col">' . $follower . '</td>
                <td class="deadline-col">' . $deadline . '</td>
            </tr>';
    }
    
    $html .= '
        </tbody>
    </table>';
    
    // Add signature section on last page
    if ($page == $total_pages) {
        $html .= '
        <div class="signature-section">
            <div class="signature-label">محل امضای حاضرین:</div>';
        
        if (isset($signatures['attendee']) && !empty($signatures['attendee']['signature_data'])) {
            $html .= '<img src="' . $signatures['attendee']['signature_data'] . '" style="max-width: 350px; max-height: 100px; margin-top: 5px;">';
        } else {
            $html .= '<div style="height: 80px; margin-top: 5px;"></div>';
        }
        
        $html .= '</div>';
    }
    
    // ADD FOOTER WITH PAGE NUMBER - OUTSIDE SIGNATURE
    $html .= '
    <div style="margin-top: 15px; text-align: right; font-size: 10pt; color: #666;">
        صفحه ' . $page . ' از ' . $total_pages . '
    </div>';
    
    return $html;
}

function generatePDFPageHTML($meeting, $items, $signatures, $page, $total_pages) {
    // Convert dates to Jalali
    $meeting_date_jalali = toJalali($meeting['meeting_date']);
    
    $building_names = [
        'AG' => 'ساختمان دانشکده کشاورزی',
        'LB' => 'ساختمان کتابخانه',
  'SK'=> 'اسکای لایت',

        'G' => 'عمومی'
    ];
    $building_name = $building_names[$meeting['building_prefix']] ?? 'عمومی';
    
    $html = '<style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 5px; text-align: right; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .header { text-align: center; font-size: 16pt; font-weight: bold; }
        .page-num { text-align: left; font-size: 10pt; }
    </style>';
    
    $html .= '<table>';
    $html .= '<tr><td class="page-num">صفحه ' . $page . ' از ' . $total_pages . '</td>';
    $html .= '<td class="header" colspan="2">فرم صورتجلسه<br>' . $building_name . '</td></tr>';
    
    $html .= '<tr><td colspan="3"><strong>شماره:</strong> ' . htmlspecialchars($meeting['meeting_number']) . 
             ' | <strong>دستور جلسه:</strong> ' . htmlspecialchars($meeting['agenda']) . '</td></tr>';
    
    $html .= '<tr><td><strong>تاریخ:</strong> ' . $meeting_date_jalali . '</td>';
    $html .= '<td><strong>ساعت:</strong> ' . htmlspecialchars($meeting['meeting_time']) . '</td>';
    $html .= '<td><strong>محل:</strong> ' . htmlspecialchars($meeting['location']) . '</td></tr>';
    
    $html .= '<tr><td colspan="3"><strong>حاضرین کارفرما:</strong> ' . htmlspecialchars($meeting['attendees']) . '</td></tr>';
    $html .= '<tr><td colspan="3"><strong>حاضرین نظارت:</strong> ' . htmlspecialchars($meeting['observers']) . '</td></tr>';
    $html .= '<tr><td colspan="3"><strong>حاضرین پیمانکار:</strong> ' . htmlspecialchars($meeting['contractor']) . '</td></tr>';
    
    $html .= '</table><br>';
    
    // Items table
    $html .= '<table>';
    $html .= '<tr><th width="5%">ردیف</th><th width="15%">پیگیری کننده</th>';
    $html .= '<th width="60%">خلاصه مذاکرات</th><th width="20%">تاریخ سررسید</th></tr>';
    
    $start = ($page - 1) * 9;
    $end = min($start + 9, count($items));
    
    for ($i = $start; $i < $end; $i++) {
        $item = $items[$i];
        $deadline_jalali = toJalali($item['deadline']);
        $html .= '<tr>';
        $html .= '<td style="text-align: center;">' . ($i + 1) . '</td>';
        $html .= '<td>' . htmlspecialchars($item['follower']) . '</td>';
        $html .= '<td>' . nl2br(htmlspecialchars($item['description'])) . '</td>';
        $html .= '<td style="text-align: center;">' . $deadline_jalali . '</td>';
        $html .= '</tr>';
    }
    
    // Fill empty rows
    for ($i = $end; $i < $start + 9; $i++) {
        $html .= '<tr><td style="text-align: center;">' . ($i + 1) . '</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
    }
    
    $html .= '</table>';
    
    // Signature on last page
    if ($page == $total_pages) {
        $html .= '<br><div style="text-align: right;"><strong>محل امضای حاضرین:</strong></div>';
        $html .= '<div style="border: 1px solid #999; height: 80px; margin-top: 10px;"></div>';
    }
    
    return $html;
}

function getPendingHandwrittenForms($pdo) {
    try {
        $sql = "SELECT id, meeting_number, created_at 
                FROM meeting_minutes 
                WHERE status = 'handwritten_pending' AND is_handwritten = 1 
                ORDER BY id DESC";
        $stmt = $pdo->query($sql);
        $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($forms as &$form) {
            $form['created_at_jalali'] = toJalali($form['created_at']);
        }

        return ['success' => true, 'data' => $forms];
    } catch (Exception $e) {
        logError("Error getting pending forms: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function reserveBlankForm($pdo, $prefix, $user_id, $meeting_date = null) {
    try {
        $pdo->beginTransaction();

        // Get the next available meeting number
        $number_data = getNextMeetingNumber($pdo, $prefix);
        if (!$number_data['success']) {
            throw new Exception("Could not generate a meeting number.");
        }
        $meeting_number = $number_data['meeting_number'];

        // ===== FIXED: Better date handling =====
        $date = date('Y-m-d'); // Default to today
        
        if ($meeting_date) {
            // Log the incoming date for debugging
            error_log("Reserve form: Received date: " . $meeting_date);
            
            // Convert Persian/Arabic numbers to English
            $meeting_date = convertPersianToEnglish($meeting_date);
            
            // Try to convert Jalali to Gregorian
            $converted_date = toGregorian($meeting_date);
            
            if ($converted_date && $converted_date !== '0000-00-00') {
                $date = $converted_date;
                error_log("Reserve form: Converted date: " . $date);
            } else {
                error_log("Reserve form: Date conversion failed, using today's date");
                // Don't throw error, just use today's date as fallback
            }
        }

        // Updated SQL to include meeting_date column
        $sql = "INSERT INTO meeting_minutes 
                (meeting_number, building_prefix, meeting_date, status, is_handwritten, created_by, agenda)
                VALUES (?, ?, ?, 'handwritten_pending', 1, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $meeting_number,
            $prefix,
            $date,
            $user_id,
            'فرم دستی - در انتظار بارگذاری'
        ]);
        
        $meeting_id = $pdo->lastInsertId();

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'شماره صورتجلسه رزرو شد.',
            'meeting_id' => $meeting_id,
            'meeting_number' => $meeting_number
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("Error reserving blank form: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}


// Helper function if you don't have it already
function convertPersianToEnglish($str) {
    if (!$str) return $str;
    $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    $result = str_replace($persianNumbers, $englishNumbers, $str);
    $result = str_replace($arabicNumbers, $englishNumbers, $result);
    return $result;
}




function getNextMeetingNumber($pdo, $prefix) {
    try {
        // Get the last meeting number for this prefix
        $sql = "SELECT meeting_number FROM meeting_minutes 
                WHERE meeting_number LIKE ? 
                ORDER BY id DESC LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$prefix . '-%']);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($last) {
            // Extract the number part
            $parts = explode('-', $last['meeting_number']);
            if (count($parts) >= 2) {
                $last_number = intval(end($parts));
                $next_number = $last_number + 1;
            } else {
                $next_number = 1;
            }
        } else {
            $next_number = 1;
        }
        
        $meeting_number = $prefix . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
        
        return [
            'success' => true,
            'meeting_number' => $meeting_number,
            'prefix' => $prefix,
            'number' => $next_number
        ];
        
    } catch (Exception $e) {
        logError("Error generating meeting number: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در تولید شماره: ' . $e->getMessage()
        ];
    }
}

function saveMeetingMinutes($pdo, $data, $user_id) {
    try {
        $pdo->beginTransaction();
        
        $raw_date = $data['meeting_date'] ?? 'NOT_PROVIDED';
        error_log("[DATE_DEBUG] 1. API received raw date: " . $raw_date);
        
        if (empty($data['meeting_date'])) {
            throw new Exception('تاریخ جلسه خالی است. لطفاً تاریخ را وارد کنید.');
        }
        
        $meeting_date = toGregorian($data['meeting_date']);
        error_log("[DATE_DEBUG] 2. Converted to Gregorian: " . ($meeting_date ?: 'CONVERSION_FAILED'));
        
        if (!$meeting_date) {
            throw new Exception('تاریخ جلسه نامعتبر است. لطفاً فرمت صحیح (مثال: 1404/08/14) را وارد کنید.');
        }
        
        $check_sql = "SELECT id FROM meeting_minutes WHERE meeting_number = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$data['meeting_number']]);
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing && (!isset($data['meeting_id']) || $existing['id'] != $data['meeting_id'])) {
            throw new Exception('این شماره صورتجلسه قبلاً استفاده شده است');
        }
        
        $meeting_id = $data['meeting_id'] ?? null;
        
        if ($meeting_id) {
            // Update existing
            $sql = "UPDATE meeting_minutes SET 
                    meeting_number = ?, building_prefix = ?, agenda = ?, meeting_date = ?, meeting_time = ?, 
                    location = ?, attendees = ?, observers = ?, contractor = ?, status = ?, updated_at = NOW()
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['meeting_number'] ?? '', $data['building_prefix'] ?? 'G', $data['agenda'] ?? '',
                $meeting_date, $data['meeting_time'] ?? null, $data['location'] ?? '',
                $data['attendees'] ?? '', $data['observers'] ?? '', $data['contractor'] ?? '',
                $data['status'] ?? 'draft', $meeting_id
            ]);
            
            $pdo->prepare("DELETE FROM meeting_minutes_items WHERE meeting_id = ?")->execute([$meeting_id]);
            $pdo->prepare("DELETE FROM meeting_minutes_signatures WHERE meeting_id = ?")->execute([$meeting_id]);
        } else {
            // Create new
            $sql = "INSERT INTO meeting_minutes 
                    (meeting_number, building_prefix, agenda, meeting_date, meeting_time, location, attendees, observers, contractor, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['meeting_number'] ?? '', $data['building_prefix'] ?? 'G', $data['agenda'] ?? '',
                $meeting_date, $data['meeting_time'] ?? null, $data['location'] ?? '',
                $data['attendees'] ?? '', $data['observers'] ?? '', $data['contractor'] ?? '',
                $data['status'] ?? 'draft', $user_id
            ]);
            $meeting_id = $pdo->lastInsertId();
        }
        
        // Save items
        if (!empty($data['items']) && is_array($data['items'])) {
            // --- THE CRITICAL FIX IS HERE ---
            // The column `row_number` is now correctly wrapped in backticks for the INSERT.
            $item_sql = "INSERT INTO meeting_minutes_items 
                        (meeting_id, `row_number`, follower, description, deadline, status)
                        VALUES (?, ?, ?, ?, ?, ?)";
            $item_stmt = $pdo->prepare($item_sql);
            
            foreach ($data['items'] as $index => $item) {
                if (!empty($item['description']) || !empty($item['follower'])) {
                    $deadline = !empty($item['deadline']) ? toGregorian($item['deadline']) : null;
                    $item_stmt->execute([
                        $meeting_id, $index + 1, $item['follower'] ?? '',
                        $item['description'] ?? '', $deadline, 'pending'
                    ]);
                }
            }
        }

        // Save signatures
        if (!empty($data['signatures']) && is_array($data['signatures'])) {
            $sig_sql = "INSERT INTO meeting_minutes_signatures 
                       (meeting_id, signature_type, signature_data, signer_name, signer_role)
                       VALUES (?, ?, ?, ?, ?)";
            $sig_stmt = $pdo->prepare($sig_sql);
            if (!empty($data['signatures']['attendee'])) {
                $sig_stmt->execute([
                    $meeting_id, 'attendee', $data['signatures']['attendee'],
                    $data['attendee_name'] ?? '', 'حاضرین'
                ]);
            }
        }
        
        $log_sql = "INSERT INTO form_activity_log (form_type, form_id, action, description, user_id)
                   VALUES ('meeting_minutes', ?, ?, ?, ?)";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            $meeting_id, $meeting_id ? 'updated' : 'created',
            'صورتجلسه ' . ($meeting_id ? 'به‌روزرسانی' : 'ایجاد') . ' شد', $user_id
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'صورتجلسه با موفقیت ذخیره شد',
            'meeting_id' => $meeting_id
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("Error saving meeting minutes: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در ذخیره: ' . $e->getMessage()
        ];
    }
}

function getMeetingMinutesList($pardis_pdo, $common_pdo, $letter_pdo, $params) {
    try {
        $letter_db_name = $letter_pdo->query("SELECT DATABASE()")->fetchColumn();
        if (empty($letter_db_name)) {
            throw new Exception("Could not determine the database name for the Letter Tracking connection.");
        }
        
        $base_sql = "SELECT 
                        mm.id, mm.meeting_number, mm.agenda, mm.meeting_date, mm.location,
                        mm.status, mm.source, mm.building_prefix, mm.created_by, mm.is_handwritten,
                        mm.pdf_file, mm.related_letter_id, mm.created_at,
                        (SELECT COUNT(*) FROM meeting_minutes_items WHERE meeting_id = mm.id) as items_count,
                        c.name as company_name,
                        l.letter_number as related_letter_number,
                        fa.file_path
                    FROM meeting_minutes mm
                    LEFT JOIN `{$letter_db_name}`.companies c ON mm.company_id = c.id
                    LEFT JOIN `{$letter_db_name}`.letters l ON mm.related_letter_id = l.id
                    LEFT JOIN form_attachments fa ON fa.form_id = mm.id AND fa.form_type = 'meeting_minutes'";

        $where_clauses = [];
        $bind_params = [];

        // --- THE CRITICAL FIX IS HERE ---
        // 'draft' has been added to the array to make sure draft minutes appear in the list.
        $default_visible_statuses = ['completed', 'handwritten_pending', 'handwritten_uploaded', 'draft'];
        
        $status_placeholders = implode(',', array_fill(0, count($default_visible_statuses), '?'));
        $where_clauses[] = "mm.status IN (" . $status_placeholders . ")";
        $bind_params = array_merge($bind_params, $default_visible_statuses);
        
        // Apply user filters
        if (!empty($params['from_date'])) {
            $from_date = toGregorian($params['from_date']);
            if ($from_date) { $where_clauses[] = "mm.meeting_date >= ?"; $bind_params[] = $from_date; }
        }
        if (!empty($params['to_date'])) {
            $to_date = toGregorian($params['to_date']);
            if ($to_date) { $where_clauses[] = "mm.meeting_date <= ?"; $bind_params[] = $to_date; }
        }
        if (!empty($params['source'])) {
            $where_clauses[] = "mm.source = ?"; $bind_params[] = $params['source'];
        }
        if (!empty($params['company_id'])) {
            $where_clauses[] = "mm.company_id = ?"; $bind_params[] = $params['company_id'];
        }
        if (!empty($params['search'])) {
            $where_clauses[] = "(mm.meeting_number LIKE ? OR mm.agenda LIKE ? OR mm.location LIKE ? OR mm.extracted_text LIKE ?)";
            $search_term = '%' . $params['search'] . '%';
            $bind_params = array_merge($bind_params, [$search_term, $search_term, $search_term, $search_term]);
        }
        
        $final_sql = $base_sql . " WHERE " . implode(" AND ", $where_clauses) . " GROUP BY mm.id ORDER BY mm.meeting_date DESC, mm.created_at DESC";
        
        $stmt = $pardis_pdo->prepare($final_sql);
        $stmt->execute($bind_params);
        $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process results
        $user_cache = [];
        $user_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?";
        $user_stmt = $common_pdo->prepare($user_sql);
        foreach ($meetings as &$meeting) {
            $meeting['meeting_date_jalali'] = toJalali($meeting['meeting_date']);
            $meeting['created_at_jalali'] = toJalali($meeting['created_at'] ?? null);
            $creator_id = $meeting['created_by'];
            if ($creator_id) {
                if (!isset($user_cache[$creator_id])) {
                    $user_stmt->execute([$creator_id]);
                    $user_cache[$creator_id] = $user_stmt->fetchColumn() ?: 'کاربر یافت نشد';
                }
                $meeting['creator_name'] = $user_cache[$creator_id];
            } else {
                $meeting['creator_name'] = 'سیستم';
            }
        }
        return [ 'success' => true, 'data' => $meetings ];
    } catch (Exception $e) {
        logError("Error in getMeetingMinutesList: " . $e->getMessage());
        return ['success' => false, 'message' => 'خطا در بارگذاری لیست. Error: ' . $e->getMessage()];
    }
}

function getMeetingMinutes($pdo, $id) {
    try {
        // Get main meeting data
        $sql = "SELECT * FROM meeting_minutes WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$meeting) {
            return ['success' => false, 'message' => 'صورتجلسه یافت نشد'];
        }
        
        // Convert dates to Jalali
        $meeting['meeting_date_jalali'] = toJalali($meeting['meeting_date']);
        
        // Get items (and wrap row_number in backticks)
        $items_sql = "SELECT * FROM meeting_minutes_items WHERE meeting_id = ? ORDER BY `row_number`";
        $items_stmt = $pdo->prepare($items_sql);
        $items_stmt->execute([$id]);
        $meeting['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($meeting['items'] as &$item) {
            $item['deadline_jalali'] = toJalali($item['deadline']);
        }
        
        // Get signatures
        $sig_sql = "SELECT * FROM meeting_minutes_signatures WHERE meeting_id = ?";
        $sig_stmt = $pdo->prepare($sig_sql);
        $sig_stmt->execute([$id]);
        $signatures = $sig_stmt->fetchAll(PDO::FETCH_ASSOC);
        $meeting['signatures'] = [];
        foreach ($signatures as $sig) {
            $meeting['signatures'][$sig['signature_type']] = $sig;
        }

        // --- THIS IS THE NEW CODE ---
        // Fetch any associated files from the attachments table
        $att_sql = "SELECT * FROM form_attachments WHERE form_type = 'meeting_minutes' AND form_id = ?";
        $att_stmt = $pdo->prepare($att_sql);
        $att_stmt->execute([$id]);
        $meeting['attachments'] = $att_stmt->fetchAll(PDO::FETCH_ASSOC);
        // --- END OF NEW CODE ---

        return [
            'success' => true,
            'data' => $meeting
        ];
        
    } catch (Exception $e) {
        logError("Error getting meeting minutes: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در بارگذاری صورتجلسه: ' . $e->getMessage()
        ];
    }
}

function deleteMeetingMinutes($pdo, $id, $user_id) {
    try {
        $pdo->beginTransaction();
        
        // Check if meeting exists
        $stmt = $pdo->prepare("SELECT meeting_number FROM meeting_minutes WHERE id = ?");
        $stmt->execute([$id]);
        $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$meeting) {
            throw new Exception('صورتجلسه یافت نشد');
        }
        
        // Log before deletion
        $log_sql = "INSERT INTO form_activity_log (form_type, form_id, action, description, user_id)
                   VALUES ('meeting_minutes', ?, 'deleted', ?, ?)";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            $id,
            'صورتجلسه شماره ' . $meeting['meeting_number'] . ' حذف شد',
            $user_id
        ]);
        
        // Delete meeting (cascade will delete items and signatures)
        $delete_sql = "DELETE FROM meeting_minutes WHERE id = ?";
        $delete_stmt = $pdo->prepare($delete_sql);
        $delete_stmt->execute([$id]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'صورتجلسه با موفقیت حذف شد'
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("Error deleting meeting minutes: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در حذف: ' . $e->getMessage()
        ];
    }
}

function getFormStatistics($pdo) {
    try {
        $stats = [];
        
        // Total meeting minutes
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM meeting_minutes");
        $stats['total_meetings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Completed
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM meeting_minutes WHERE status = 'completed'");
        $stats['completed_meetings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Draft
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM meeting_minutes WHERE status = 'draft'");
        $stats['draft_meetings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // This month
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM meeting_minutes WHERE MONTH(meeting_date) = MONTH(CURDATE()) AND YEAR(meeting_date) = YEAR(CURDATE())");
        $stats['this_month_meetings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // By building
        $stmt = $pdo->query("SELECT building_prefix, COUNT(*) as count FROM meeting_minutes GROUP BY building_prefix");
        $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['by_building'] = [];
        foreach ($buildings as $building) {
            $stats['by_building'][$building['building_prefix']] = $building['count'];
        }
        
        return [
            'success' => true,
            'data' => $stats
        ];
        
    } catch (Exception $e) {
        logError("Error getting statistics: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در بارگذاری آمار: ' . $e->getMessage()
        ];
    }
}

function uploadHandwrittenForm($pdo, $files, $data, $user_id) {
    // The ID of the placeholder record created when the user downloaded the blank form.
    $meeting_id = $data['meeting_id'] ?? 0;
    $file_path = ''; // Define here for the catch block

    try {
        // --- 1. VALIDATION ---
        if (empty($meeting_id)) {
            throw new Exception('صورتجلسه در انتظار بارگذاری انتخاب نشده است.');
        }
        if (!isset($files['file']) || $files['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('خطا در آپلود فایل. لطفا یک فایل را انتخاب کنید.');
        }

        $file = $files['file'];
        $agenda = $data['agenda'] ?? '';
        $meeting_date = $data['meeting_date'] ?? '';

        if (empty($agenda)) {
            throw new Exception('دستور جلسه / موضوع الزامی است.');
        }
        if (empty($meeting_date)) {
            throw new Exception('تاریخ جلسه الزامی است.');
        }

        // Validate file type (your existing logic is good)
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('فقط فایل‌های PDF, JPG و PNG مجاز هستند.');
        }

        $pdo->beginTransaction();

        // --- 2. UPDATE THE PLACEHOLDER RECORD ---
        // This is the core logic change. We UPDATE instead of INSERT.
        $gregorian_date = toGregorian($meeting_date);
        if (!$gregorian_date) {
            throw new Exception('فرمت تاریخ نامعتبر است.');
        }
        
        $update_sql = "UPDATE meeting_minutes SET 
                        agenda = ?, 
                        meeting_date = ?, 
                        meeting_time = ?, 
                        location = ?, 
                        status = 'handwritten_uploaded'
                       WHERE id = ? AND status = 'handwritten_pending'";
        
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            $agenda,
            $gregorian_date,
            $data['meeting_time'] ?? '',
            $data['location'] ?? '',
            $meeting_id
        ]);
        
        // Check if the update was successful. If not, the record was already completed or didn't exist.
        if ($update_stmt->rowCount() === 0) {
            throw new Exception('صورتجلسه یافت نشد یا قبلاً برای آن فایل بارگذاری شده است.');
        }

        // --- 3. SAVE THE UPLOADED FILE ---
        $upload_dir = __DIR__ . '/uploads/meeting_minutes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $meeting_number = $data['meeting_number']; // Get the number from the form
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = $meeting_number . '_' . time() . '.' . $extension;
        $file_path = $upload_dir . $new_filename; // Assign to variable in this scope
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('خطا در ذخیره فایل روی سرور.');
        }
        
        // --- 4. CREATE THE ATTACHMENT RECORD ---
        $attachment_sql = "INSERT INTO form_attachments 
                (form_type, form_id, file_name, file_path, file_type, file_size, uploaded_by)
                VALUES ('meeting_minutes', ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($attachment_sql);
        $stmt->execute([
            $meeting_id,
            $file['name'],
            'uploads/meeting_minutes/' . $new_filename,
            $file['type'],
            $file['size'],
            $user_id
        ]);
        
        // --- 5. LOG THE ACTIVITY ---
        $log_sql = "INSERT INTO form_activity_log (form_type, form_id, action, description, user_id)
                   VALUES ('meeting_minutes', ?, 'file_uploaded', ?, ?)";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            $meeting_id,
            'فایل دستی برای صورتجلسه ' . $meeting_number . ' بارگذاری شد.',
            $user_id
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'فایل با موفقیت بارگذاری و صورتجلسه تکمیل شد.'
        ];
        
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Clean up the uploaded file if the database transaction failed
        if (!empty($file_path) && file_exists($file_path)) {
            unlink($file_path);
        }
        
        logError("Error uploading handwritten form: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا: ' . $e->getMessage()
        ];
    }
}
$letter_pdo = getLetterTrackingDBConnection();
function getAllMinutes($pdo, $common_pdo, $params) {
    try {
        global $letter_pdo;
        
        // --- DEFENSIVE FIX: Get the database name and VALIDATE it ---
        $letter_db_name = $letter_pdo->query("SELECT DATABASE()")->fetchColumn();
        if (empty($letter_db_name)) {
            throw new Exception("Could not determine the database name for the Letter Tracking connection. Please check the connection configuration.");
        }
                
        // --- Build the SQL query correctly, without the 'pardis.' prefix ---
        $sql = "SELECT 
                    mm.id, mm.meeting_number, mm.agenda, mm.meeting_date, mm.source,
                    mm.related_letter_id, mm.created_by,
                    c.name as company_name,
                    l.letter_number as related_letter_number,
                    fa.file_path
                FROM meeting_minutes mm
                LEFT JOIN `{$letter_db_name}`.companies c ON mm.company_id = c.id
                LEFT JOIN `{$letter_db_name}`.letters l ON mm.related_letter_id = l.id
                LEFT JOIN form_attachments fa ON fa.form_id = mm.id AND fa.form_type = 'meeting_minutes'
                WHERE 1=1";
        
        $bind_params = [];

        if (!empty($params['search'])) {
            $sql .= " AND (mm.meeting_number LIKE :search OR mm.agenda LIKE :search)";
            $bind_params[':search'] = '%' . $params['search'] . '%';
        }
        if (!empty($params['source'])) {
            $sql .= " AND mm.source = :source";
            $bind_params[':source'] = $params['source'];
        }
        if (!empty($params['company_id'])) {
            $sql .= " AND mm.company_id = :company_id";
            $bind_params[':company_id'] = $params['company_id'];
        }

        $sql .= " GROUP BY mm.id ORDER BY mm.meeting_date DESC, mm.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind_params);
        $minutes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $user_cache = [];
        $user_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?";
        $user_stmt = $common_pdo->prepare($user_sql);
        foreach ($minutes as &$minute) {
            $minute['meeting_date_jalali'] = toJalali($minute['meeting_date']);
            $creator_id = $minute['created_by'];
             if ($creator_id) {
                if (!isset($user_cache[$creator_id])) {
                    $user_stmt->execute([$creator_id]);
                    $user_cache[$creator_id] = $user_stmt->fetchColumn() ?: 'کاربر یافت نشد';
                }
                $minute['creator_name'] = $user_cache[$creator_id];
            } else {
                $minute['creator_name'] = 'سیستم';
            }
        }
        return ['success' => true, 'data' => $minutes];
    } catch (Exception $e) {
        logError("Error in getAllMinutes: " . $e->getMessage());
        return ['success' => false, 'message' => 'خطا در بارگذاری لیست صورتجلسات.'];
    }
}

function getUploadedFiles($pdo, $meeting_number) {
    try {
        if (empty($meeting_number)) {
            return [
                'success' => true,
                'data' => []
            ];
        }
        
        // First, try to find by meeting number
        $meeting_sql = "SELECT id FROM meeting_minutes WHERE meeting_number = ?";
        $meeting_stmt = $pdo->prepare($meeting_sql);
        $meeting_stmt->execute([$meeting_number]);
        $meeting = $meeting_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$meeting) {
            return [
                'success' => true,
                'data' => []
            ];
        }
        
        $sql = "SELECT fa.*, CONCAT(u.first_name, ' ', u.last_name) as uploader_name
                FROM form_attachments fa
                LEFT JOIN users u ON fa.uploaded_by = u.id
                WHERE fa.form_type = 'meeting_minutes' AND fa.form_id = ?
                ORDER BY fa.uploaded_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$meeting['id']]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert timestamps to Jalali
        foreach ($files as &$file) {
            $file['uploaded_at'] = toJalali($file['uploaded_at']);
        }
        
        return [
            'success' => true,
            'data' => $files
        ];
        
    } catch (Exception $e) {
        logError("Error getting uploaded files: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در دریافت فایل‌ها: ' . $e->getMessage()
        ];
    }
}


function getAllUploadedFiles($pdo, $common_pdo, $params = [])  {
    try {
        $sql = "SELECT 
                    fa.id, fa.form_id, fa.file_name, fa.file_path, fa.file_type, 
                    fa.file_size, fa.uploaded_at, fa.uploaded_by,
                    mm.meeting_number, mm.agenda, mm.meeting_date, mm.meeting_time, 
                    mm.location, mm.building_prefix, mm.is_handwritten, mm.created_by
                FROM form_attachments fa
                INNER JOIN meeting_minutes mm ON fa.form_id = mm.id
                WHERE fa.form_type = 'meeting_minutes'";
        
        // Add filters if provided
        $bind_params = [];
        
        if (!empty($params['building'])) {
            $sql .= " AND mm.building_prefix = ?";
            $bind_params[] = $params['building'];
        }
        
        if (!empty($params['from_date'])) {
            $from_date = toGregorian($params['from_date']);
            if ($from_date) {
                $sql .= " AND mm.meeting_date >= ?";
                $bind_params[] = $from_date;
            }
        }
        
        if (!empty($params['to_date'])) {
            $to_date = toGregorian($params['to_date']);
            if ($to_date) {
                $sql .= " AND mm.meeting_date <= ?";
                $bind_params[] = $to_date;
            }
        }
        
        $sql .= " ORDER BY mm.meeting_date DESC, fa.uploaded_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind_params);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
         $user_cache = [];
        $user_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?";
        $user_stmt = $common_pdo->prepare($user_sql);

        // Convert dates to Jalali and add permissions
          foreach ($files as &$file) {
            $file['meeting_date_jalali'] = toJalali($file['meeting_date']);
            $file['uploaded_at'] = toJalali($file['uploaded_at']);
            
            $uploader_id = $file['uploaded_by'];
            if ($uploader_id) {
                if (!isset($user_cache[$uploader_id])) {
                    $user_stmt->execute([$uploader_id]);
                    $user_cache[$uploader_id] = $user_stmt->fetchColumn() ?: 'نامشخص';
                }
                $file['uploader_name'] = $user_cache[$uploader_id];
            } else {
                $file['uploader_name'] = 'نامشخص';
            }
            
            $file['can_edit'] = ($file['created_by'] == ($_SESSION['user_id'] ?? 0));
        }
        
        return ['success' => true, 'data' => $files];
        
    } catch (Exception $e) {
        logError("Error getting all uploaded files: " . $e->getMessage());
        return ['success' => false, 'message' => 'خطا در دریافت فایل‌ها'];
    }
}

function deleteUploadedFile($pdo, $file_id, $user_id) {
    try {
        $pdo->beginTransaction();
        
        // Get file info
        $sql = "SELECT fa.*, mm.is_handwritten, mm.meeting_number 
                FROM form_attachments fa
                LEFT JOIN meeting_minutes mm ON fa.form_id = mm.id
                WHERE fa.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$file_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            throw new Exception('فایل یافت نشد');
        }
        
        // Delete physical file
        $file_path = __DIR__ . '/' . $file['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from database
        $delete_sql = "DELETE FROM form_attachments WHERE id = ?";
        $delete_stmt = $pdo->prepare($delete_sql);
        $delete_stmt->execute([$file_id]);
        
        // If this was a handwritten meeting with no other files, delete the meeting record too
        if ($file['is_handwritten'] == 1) {
            // Check if there are other files for this meeting
            $check_sql = "SELECT COUNT(*) as count FROM form_attachments WHERE form_id = ? AND form_type = 'meeting_minutes'";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$file['form_id']]);
            $count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count == 0) {
                // No other files, delete the meeting record
                $delete_meeting_sql = "DELETE FROM meeting_minutes WHERE id = ?";
                $delete_meeting_stmt = $pdo->prepare($delete_meeting_sql);
                $delete_meeting_stmt->execute([$file['form_id']]);
                
                $description = 'صورتجلسه دستی و فایل حذف شد: ' . $file['meeting_number'];
            } else {
                $description = 'فایل حذف شد: ' . $file['file_name'];
            }
        } else {
            $description = 'فایل حذف شد: ' . $file['file_name'];
        }
        
        // Log activity
        if ($file['form_id'] > 0) {
            $log_sql = "INSERT INTO form_activity_log (form_type, form_id, action, description, user_id)
                       VALUES (?, ?, 'file_deleted', ?, ?)";
            $log_stmt = $pdo->prepare($log_sql);
            $log_stmt->execute([
                $file['form_type'],
                $file['form_id'],
                $description,
                $user_id
            ]);
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'فایل با موفقیت حذف شد'
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("Error deleting uploaded file: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در حذف فایل: ' . $e->getMessage()
        ];
    }
}


function getAttendeesList($pdo, $user_id) {
    try {
        $sql = "SELECT id, name, role, category, company FROM meeting_attendees 
                WHERE user_id = ? OR is_global = 1 
                ORDER BY category, company, name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'data' => $attendees];
    } catch (Exception $e) {
        logError("Error getting attendees list: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function saveAttendee($pdo, $data, $user_id) {
    try {
        $sql = "INSERT INTO meeting_attendees (name, role, category, company, user_id) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['role'] ?? '',
            $data['category'], // 'employer', 'supervisor', 'contractor'
            $data['company'] ?? '',
            $user_id
        ]);
        
        return ['success' => true, 'message' => 'فرد با موفقیت اضافه شد'];
    } catch (Exception $e) {
        logError("Error saving attendee: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deleteAttendee($pdo, $id, $user_id) {
    try {
        $sql = "DELETE FROM meeting_attendees WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $user_id]);
        
        return ['success' => true, 'message' => 'فرد با موفقیت حذف شد'];
    } catch (Exception $e) {
        logError("Error deleting attendee: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

?>