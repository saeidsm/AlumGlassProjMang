<?php
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

$expected_project_key = 'pardis';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}

$conn = getLetterTrackingDBConnection();

function persian_to_english_number($str) {
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $english = range(0, 9);
    $str = str_replace($persian, $english, $str);
    $str = str_replace($arabic, $english, $str);
    return $str;
}

class PersianDate {
    public static function toJalali($g_y, $g_m, $g_d) {
        $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
        $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);
        
        $gy = $g_y - 1600;
        $gm = $g_m - 1;
        $gd = $g_d - 1;
        
        $g_day_no = 365 * $gy + floor(($gy + 3) / 4) - floor(($gy + 99) / 100) + floor(($gy + 399) / 400);
        
        for ($i = 0; $i < $gm; ++$i)
            $g_day_no += $g_days_in_month[$i];
        if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)))
            $g_day_no++;
        $g_day_no += $gd;
        
        $j_day_no = $g_day_no - 79;
        
        $j_np = floor($j_day_no / 12053);
        $j_day_no = $j_day_no % 12053;
        
        $jy = 979 + 33 * $j_np + 4 * floor($j_day_no / 1461);
        
        $j_day_no %= 1461;
        
        if ($j_day_no >= 366) {
            $jy += floor(($j_day_no - 1) / 365);
            $j_day_no = ($j_day_no - 1) % 365;
        }
        
        for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i)
            $j_day_no -= $j_days_in_month[$i];
        $jm = $i + 1;
        $jd = $j_day_no + 1;
        
        return array($jy, $jm, $jd);
    }
    
    public static function toGregorian($j_y, $j_m, $j_d) {
    // --- FIX: Explicitly cast the input parameters to integers ---
    $j_y = (int)$j_y;
    $j_m = (int)$j_m;
    $j_d = (int)$j_d;
    // -----------------------------------------------------------

    $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);
    
    $jy = $j_y - 979;
    $jm = $j_m - 1;
    $jd = $j_d - 1;
    
    $j_day_no = 365 * $jy + floor($jy / 33) * 8 + floor(($jy % 33 + 3) / 4);
    for ($i = 0; $i < $jm; ++$i)
        $j_day_no += $j_days_in_month[$i];
    
    $j_day_no += $jd;
    
    $g_day_no = $j_day_no + 79;
    
    $gy = 1600 + 400 * floor($g_day_no / 146097);
    $g_day_no = $g_day_no % 146097;
    
    $leap = true;
    if ($g_day_no >= 36525) {
        $g_day_no--;
        $gy += 100 * floor($g_day_no / 36524);
        $g_day_no = $g_day_no % 36524;
        
        if ($g_day_no >= 365)
            $g_day_no++;
        $leap = false;
    }
    
    $gy += 4 * floor($g_day_no / 1461);
    $g_day_no %= 1461;
    
    if ($g_day_no >= 366) {
        $leap = false;
        
        $g_day_no--;
        $gy += floor($g_day_no / 365);
        $g_day_no = $g_day_no % 365;
    }
    
    for ($i = 0; $g_day_no >= $g_days_in_month[$i] + ($i == 1 && $leap); $i++)
        $g_day_no -= $g_days_in_month[$i] + ($i == 1 && $leap);
    $gm = $i + 1;
    $gd = $g_day_no + 1;
    
    return array($gy, $gm, $gd);
}
    
    public static function format($date) {
        if (!$date) return '';
        $parts = explode('-', $date);
        $jalali = self::toJalali($parts[0], $parts[1], $parts[2]);
        return sprintf('%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2]);
    }
    
public static function toGregorianDate($persianDate) {
    if (!$persianDate) return null;
    
    // Convert Persian/Arabic numbers to English
    $persianDate = persian_to_english_number($persianDate);
    
    // Remove any extra spaces
    $persianDate = trim($persianDate);
    
    // Split the date
    $parts = explode('/', $persianDate);
    if (count($parts) != 3) {
        error_log("Invalid Persian date format: $persianDate");
        return null;
    }
    
    // Ensure all parts are integers
    $j_y = (int)trim($parts[0]);
    $j_m = (int)trim($parts[1]);
    $j_d = (int)trim($parts[2]);
    
    // Validate Persian date ranges
    if ($j_y < 1300 || $j_y > 1500) {
        error_log("Invalid Persian year: $j_y");
        return null;
    }
    if ($j_m < 1 || $j_m > 12) {
        error_log("Invalid Persian month: $j_m");
        return null;
    }
    if ($j_d < 1 || $j_d > 31) {
        error_log("Invalid Persian day: $j_d");
        return null;
    }
    
    $gregorian = self::toGregorian($j_y, $j_m, $j_d);
    return sprintf('%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2]);
}
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_companies':
            $stmt = $conn->query("SELECT id, name, name_english, type FROM companies WHERE is_active = 1 ORDER BY name");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
            
        case 'get_relationship_types':
            $stmt = $conn->query("SELECT id, code, name_persian, name_english FROM relationship_types WHERE is_active = 1 ORDER BY display_order");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
            
        case 'search_letters':
            $search = $_GET['q'] ?? '';
            $stmt = $conn->prepare("SELECT id, letter_number, subject, letter_date FROM letters WHERE letter_number LIKE ? OR subject LIKE ? LIMIT 20");
            $searchTerm = "%$search%";
            $stmt->execute([$searchTerm, $searchTerm]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;

        case 'deep_search':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode(['results' => [], 'count' => 0]);
                exit;
            }
            
            $searchTerm = "%$query%";
            
            try {
                $sql = "SELECT DISTINCT l.id, l.letter_number, l.subject, l.letter_date, l.summary, l.category,
                        cs.name as sender_name, cr.name as receiver_name,
                        'letter' as source_type, '' as attachment_name,
                        MATCH(l.letter_number, l.subject, l.summary, l.content_text, l.notes) 
                        AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                        FROM letters l
                        LEFT JOIN companies cs ON l.company_sender_id = cs.id
                        LEFT JOIN companies cr ON l.company_receiver_id = cr.id
                        WHERE MATCH(l.letter_number, l.subject, l.summary, l.content_text, l.notes) 
                        AGAINST(? IN NATURAL LANGUAGE MODE)
                        ORDER BY relevance DESC
                        LIMIT 50";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$query, $query]);
                $letter_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $sql = "SELECT DISTINCT l.id, l.letter_number, l.subject, l.letter_date, l.summary, l.category,
                        cs.name as sender_name, cr.name as receiver_name,
                        'letter' as source_type, '' as attachment_name
                        FROM letters l
                        LEFT JOIN companies cs ON l.company_sender_id = cs.id
                        LEFT JOIN companies cr ON l.company_receiver_id = cr.id
                        WHERE l.letter_number LIKE ? OR l.subject LIKE ? 
                        OR l.summary LIKE ? OR l.content_text LIKE ? OR l.notes LIKE ?
                        ORDER BY l.letter_date DESC
                        LIMIT 50";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                $letter_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            try {
                $sql = "SELECT DISTINCT l.id, l.letter_number, l.subject, l.letter_date, l.category,
                        a.original_filename as attachment_name,
                        cs.name as sender_name, cr.name as receiver_name,
                        'attachment' as source_type,
                        CASE 
                            WHEN a.extracted_text LIKE ? THEN 'content'
                            ELSE 'filename'
                        END as match_type
                        FROM letter_attachments a
                        JOIN letters l ON a.letter_id = l.id
                        LEFT JOIN companies cs ON l.company_sender_id = cs.id
                        LEFT JOIN companies cr ON l.company_receiver_id = cr.id
                        WHERE a.original_filename LIKE ? OR a.description LIKE ? 
                        OR a.extracted_text LIKE ?
                        ORDER BY l.letter_date DESC
                        LIMIT 30";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                $attachment_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $attachment_results = [];
            }
            
            $results = array_merge($letter_results, $attachment_results);
            
            echo json_encode([
                'results' => $results,
                'count' => count($results),
                'letter_count' => count($letter_results),
                'attachment_count' => count($attachment_results)
            ]);
            exit;
            
        case 'get_letter':
            $id = $_GET['id'] ?? 0;
            $stmt = $conn->prepare("SELECT * FROM letters WHERE id = ?");
            $stmt->execute([$id]);
            $letter = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($letter) {
                $letter['letter_date_persian'] = PersianDate::format($letter['letter_date']);
            }
            
            echo json_encode($letter);
            exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_letter'])) {
    $letter_date_persian = persian_to_english_number($_POST['letter_date']);
    $letter_date = PersianDate::toGregorianDate($letter_date_persian);
    
    if (!$letter_date) {
        $_SESSION['error'] = 'فرمت تاریخ نامعتبر است. لطفا تاریخ را به صورت صحیح وارد کنید (مثال: 1403/08/26)';
        header('Location: letters.php');
        exit;
    };
            
            $tags = null;
            if (!empty($_POST['tags'])) {
                $tags_array = array_map('trim', explode(',', $_POST['tags']));
                $tags_array = array_filter($tags_array);
                $tags = json_encode($tags_array, JSON_UNESCAPED_UNICODE);
            }
            
            $stmt = $conn->prepare("INSERT INTO letters (letter_number, letter_date, company_sender_id, company_receiver_id, 
                recipient_name, recipient_position, subject, summary, content_text, notes, status, priority, category, tags) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $_POST['letter_number'],
                $letter_date,
                $_POST['company_sender_id'],
                $_POST['company_receiver_id'],
                $_POST['recipient_name'] ?? '',
                $_POST['recipient_position'] ?? '',
                $_POST['subject'],
                $_POST['summary'] ?? '',
                $_POST['content_text'] ?? '',
                $_POST['notes'] ?? '',
                $_POST['status'],
                $_POST['priority'],
                $_POST['category'] ?? '',
                $tags
            ]);
            
            $letter_id = $conn->lastInsertId();
            
            if (!empty($_FILES['attachments']['name'][0])) {
                $upload_dir = './letter_storage/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['attachments']['error'][$key] === 0) {
                        $filename = basename($_FILES['attachments']['name'][$key]);
                        $filepath = $upload_dir . time() . '_' . $filename;
                        
                        if (move_uploaded_file($tmp_name, $filepath)) {
                            $extracted_text = '';
                            $mime_type = $_FILES['attachments']['type'][$key];
                            if (strpos($mime_type, 'text/') === 0) {
                                $extracted_text = file_get_contents($filepath);
                            }
                            
                            $stmt = $conn->prepare("INSERT INTO letter_attachments (letter_id, filename, original_filename, file_path, file_size, file_type, extracted_text) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $letter_id,
                                basename($filepath),
                                $filename,
                                $filepath,
                                $_FILES['attachments']['size'][$key],
                                $mime_type,
                                $extracted_text
                            ]);
                        }
                    }
                }
            }
            
            $_SESSION['message'] = 'نامه با موفقیت ثبت شد';
            header('Location: letters.php');
            exit;
        }
        
        if (isset($_POST['update_letter'])) {
    $letter_id = $_POST['letter_id'];
    
    // Convert Persian date to Gregorian with validation
    $letter_date_persian = persian_to_english_number($_POST['letter_date']);
    $letter_date = PersianDate::toGregorianDate($letter_date_persian);
    
    // Add validation
    if (!$letter_date) {
        $_SESSION['error'] = 'فرمت تاریخ نامعتبر است. لطفا تاریخ را به صورت صحیح وارد کنید (مثال: 1403/08/26)';
        header('Location: letters.php');
        exit;
    }
    
    // Rest of the update code...
    $tags = null;
    if (!empty($_POST['tags'])) {
        $tags_array = array_map('trim', explode(',', $_POST['tags']));
        $tags_array = array_filter($tags_array);
        $tags = json_encode($tags_array, JSON_UNESCAPED_UNICODE);
    }
    
    $stmt = $conn->prepare("UPDATE letters SET 
        letter_number = ?, letter_date = ?, company_sender_id = ?, company_receiver_id = ?, 
        recipient_name = ?, recipient_position = ?, subject = ?, summary = ?, 
        content_text = ?, notes = ?, status = ?, priority = ?, category = ?, tags = ?
        WHERE id = ?");
    
    $stmt->execute([
        $_POST['letter_number'],
        $letter_date,
        $_POST['company_sender_id'],
        $_POST['company_receiver_id'],
        $_POST['recipient_name'] ?? '',
        $_POST['recipient_position'] ?? '',
        $_POST['subject'],
        $_POST['summary'] ?? '',
        $_POST['content_text'] ?? '',
        $_POST['notes'] ?? '',
        $_POST['status'],
        $_POST['priority'],
        $_POST['category'] ?? '',
        $tags,
        $letter_id
    ]);
            
            // Handle new attachments
            if (!empty($_FILES['attachments']['name'][0])) {
                $upload_dir = './letter_storage/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['attachments']['error'][$key] === 0) {
                        $filename = basename($_FILES['attachments']['name'][$key]);
                        $filepath = $upload_dir . time() . '_' . $filename;
                        
                        if (move_uploaded_file($tmp_name, $filepath)) {
                            $extracted_text = '';
                            $mime_type = $_FILES['attachments']['type'][$key];
                            if (strpos($mime_type, 'text/') === 0) {
                                $extracted_text = file_get_contents($filepath);
                            }
                            
                            $stmt = $conn->prepare("INSERT INTO letter_attachments (letter_id, filename, original_filename, file_path, file_size, file_type, extracted_text) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $letter_id,
                                basename($filepath),
                                $filename,
                                $filepath,
                                $_FILES['attachments']['size'][$key],
                                $mime_type,
                                $extracted_text
                            ]);
                        }
                    }
                }
            }
            
            $_SESSION['message'] = 'نامه با موفقیت به‌روزرسانی شد';
            header('Location: letters.php');
            exit;
        }
        
        if (isset($_POST['add_relationship'])) {
            $stmt = $conn->prepare("INSERT INTO letter_relationships (parent_letter_id, child_letter_id, relationship_type_id, notes) 
                VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['parent_letter_id'],
                $_POST['child_letter_id'],
                $_POST['relationship_type_id'],
                $_POST['notes'] ?? ''
            ]);
            
            $_SESSION['message'] = 'ارتباط با موفقیت ثبت شد';
            header('Location: letters.php');
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطا: ' . $e->getMessage();
    }
}

// Build query with sorting
$where = ['1=1'];
$params = [];

// Sorting
$sort_column = $_GET['sort'] ?? 'l.letter_date';
$sort_direction = $_GET['dir'] ?? 'DESC';

$allowed_columns = [
    'l.letter_number' => 'شماره نامه',
    'l.letter_date' => 'تاریخ',
    'cs.name' => 'فرستنده',
    'cr.name' => 'گیرنده',
    'l.subject' => 'موضوع',
    'l.status' => 'وضعیت',
    'l.category' => 'دسته‌بندی'
];

if (!array_key_exists($sort_column, $allowed_columns)) {
    $sort_column = 'l.letter_date';
}

$sort_direction = strtoupper($sort_direction) === 'ASC' ? 'ASC' : 'DESC';

if (!empty($_GET['search'])) {
    $searchTerm = "%{$_GET['search']}%";
    $where[] = "(l.letter_number LIKE ? OR l.subject LIKE ? OR l.summary LIKE ? OR l.content_text LIKE ? OR l.notes LIKE ?
                 OR EXISTS (SELECT 1 FROM letter_attachments la WHERE la.letter_id = l.id 
                           AND (la.original_filename LIKE ? OR la.extracted_text LIKE ? OR la.description LIKE ?)))";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($_GET['status'])) {
    $where[] = "l.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['company'])) {
    $where[] = "(l.company_sender_id = ? OR l.company_receiver_id = ?)";
    $params = array_merge($params, [$_GET['company'], $_GET['company']]);
}

if (!empty($_GET['category'])) {
    $where[] = "l.category = ?";
    $params[] = $_GET['category'];
}

if (!empty($_GET['tags'])) {
    $where[] = "JSON_SEARCH(l.tags, 'one', ?) IS NOT NULL";
    $params[] = $_GET['tags'];
}

if (!empty($_GET['date_from'])) {
    $date_from_raw = persian_to_english_number($_GET['date_from']);
    $date_from = PersianDate::toGregorianDate($date_from_raw);
    $where[] = "l.letter_date >= ?";
    $params[] = $date_from;
}

if (!empty($_GET['date_to'])) {
    $date_to_raw = persian_to_english_number($_GET['date_to']);
    $date_to = PersianDate::toGregorianDate($date_to_raw);
    $where[] = "l.letter_date <= ?";
    $params[] = $date_to;
}

$sql = "SELECT l.*, 
        cs.name as sender_name, 
        cr.name as receiver_name,
        (SELECT COUNT(*) FROM letter_attachments WHERE letter_id = l.id) as attachment_count,
        (SELECT COUNT(*) FROM letter_relationships WHERE parent_letter_id = l.id OR child_letter_id = l.id) as relation_count
        FROM letters l
        LEFT JOIN companies cs ON l.company_sender_id = cs.id
        LEFT JOIN companies cr ON l.company_receiver_id = cr.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $sort_column $sort_direction
        LIMIT 100";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$letters = $stmt->fetchAll(PDO::FETCH_ASSOC);

$companies = $conn->query("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$categories = $conn->query("SELECT DISTINCT category FROM letters WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$all_tags = [];
$tags_result = $conn->query("SELECT tags FROM letters WHERE tags IS NOT NULL");
while ($row = $tags_result->fetch(PDO::FETCH_ASSOC)) {
    if ($row['tags']) {
        $tags_array = json_decode($row['tags'], true);
        if (is_array($tags_array)) {
            $all_tags = array_merge($all_tags, $tags_array);
        }
    }
}
$all_tags = array_unique($all_tags);
sort($all_tags);

$pageTitle = "مدیریت نامه‌ها - پروژه دانشگاه خاتم پردیس";

function isMobileDevices() {
    return preg_match(
        "/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i",
        $_SERVER["HTTP_USER_AGENT"]
    );
}

if (isMobileDevices()) {
    require_once __DIR__ . '/header.php';
} else {
    require_once __DIR__ . '/header.php';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم مدیریت نامه‌ها</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@latest/dist/css/persian-datepicker.min.css">
    <style>
        body { font-family: Tahoma, Arial, sans-serif; }
        .card { margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .letter-row:hover { background-color: #f8f9fa; cursor: pointer; }
        .badge { font-size: 0.85em; }
        .filter-section { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .search-results { position: absolute; z-index: 1000; background: white; border: 1px solid #ddd; 
                         border-radius: 5px; max-height: 400px; overflow-y: auto; width: 100%; margin-top: 5px; }
        .search-result-item { padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; }
        .search-result-item:hover { background: #f8f9fa; }
        .search-highlight { background-color: yellow; font-weight: bold; }
        .deep-search-badge { font-size: 0.7em; margin-left: 5px; }
        .sortable { cursor: pointer; user-select: none; }
        .sortable:hover { background-color: #e9ecef; }
        .sort-icon { font-size: 0.8em; margin-right: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fas fa-envelope-open-text"></i> سیستم مدیریت نامه‌ها
                </h2>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <?= $_SESSION['message']; unset($_SESSION['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="mb-3">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLetterModal">
        <i class="fas fa-plus"></i> افزودن نامه جدید
    </button>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRelationModal">
        <i class="fas fa-link"></i> افزودن ارتباط
    </button>
    <a href="plans.php" class="btn btn-info">
        <i class="fas fa-drafting-compass"></i> نقشه‌ها
    </a>
    <!-- NEW: Bulk edit attachments button -->
    <a href="bulk_edit_attachments.php" class="btn btn-warning">
        <i class="fas fa-edit"></i> ویرایش گروهی پیوست‌ها
        <?php
        // Show count of incomplete attachments
        $incomplete_stmt = $conn->query("
            SELECT COUNT(*) as count 
            FROM letter_attachments 
            WHERE (title IS NULL OR title = '') 
               OR (description IS NULL OR description = '')
        ");
        $incomplete = $incomplete_stmt->fetch(PDO::FETCH_ASSOC);
        if ($incomplete['count'] > 0):
        ?>
        <span class="badge bg-danger"><?= $incomplete['count'] ?></span>
        <?php endif; ?>
    </a>
</div>

                
                <!-- Enhanced Search Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-12 mb-3 position-relative">
                            <label class="form-label">
                                <i class="fas fa-search"></i> جستجوی پیشرفته (در تمام محتوا)
                            </label>
                            <div class="input-group">
                                <input type="text" id="deepSearch" class="form-control" 
                                       placeholder="جستجو در شماره نامه، موضوع، خلاصه، محتوا، یادداشت‌ها و محتوای فایل‌های پیوست..." 
                                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div id="searchResults" class="search-results" style="display: none;"></div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                جستجو شامل محتوای کامل نامه‌ها، یادداشت‌ها و متن استخراج شده از فایل‌های پیوست (TXT, PDF, DOC) می‌شود
                            </small>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">جستجوی ساده</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="شماره نامه یا موضوع..." 
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">وضعیت</label>
                            <select name="status" class="form-select">
                                <option value="">همه وضعیت‌ها</option>
                                <option value="draft" <?= ($_GET['status'] ?? '') == 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
                                <option value="sent" <?= ($_GET['status'] ?? '') == 'sent' ? 'selected' : '' ?>>ارسال شده</option>
                                <option value="received" <?= ($_GET['status'] ?? '') == 'received' ? 'selected' : '' ?>>دریافت شده</option>
                                <option value="replied" <?= ($_GET['status'] ?? '') == 'replied' ? 'selected' : '' ?>>پاسخ داده شده</option>
                                <option value="pending" <?= ($_GET['status'] ?? '') == 'pending' ? 'selected' : '' ?>>در انتظار</option>
                                <option value="archived" <?= ($_GET['status'] ?? '') == 'archived' ? 'selected' : '' ?>>بایگانی شده</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">شرکت</label>
                            <select name="company" class="form-select">
                                <option value="">همه شرکت‌ها</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?= $company['id'] ?>" <?= ($_GET['company'] ?? '') == $company['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($company['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">دسته‌بندی</label>
                            <select name="category" class="form-select">
                                <option value="">همه دسته‌ها</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>" <?= ($_GET['category'] ?? '') == $category ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">برچسب</label>
                            <select name="tags" class="form-select">
                                <option value="">همه برچسب‌ها</option>
                                <?php foreach ($all_tags as $tag): ?>
                                    <option value="<?= htmlspecialchars($tag) ?>" <?= ($_GET['tags'] ?? '') == $tag ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tag) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">از تاریخ</label>
                            <input type="text" name="date_from" class="form-control persian-datepicker" 
                                   placeholder="از تاریخ" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">تا تاریخ</label>
                            <input type="text" name="date_to" class="form-control persian-datepicker" 
                                   placeholder="تا تاریخ" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-filter"></i> فیلتر
                                </button>
                                <a href="letters.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Letters Table -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            نامه‌های یافت شده: <?= count($letters) ?>
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th class="sortable" onclick="sortTable('l.letter_number')">
                                            <?php if ($sort_column == 'l.letter_number'): ?>
                                                <i class="fas fa-sort-<?= $sort_direction == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                            <?php endif; ?>
                                            شماره نامه
                                        </th>
                                        <th class="sortable" onclick="sortTable('l.letter_date')">
                                            <?php if ($sort_column == 'l.letter_date'): ?>
                                                <i class="fas fa-sort-<?= $sort_direction == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                            <?php endif; ?>
                                            تاریخ
                                        </th>
                                        <th class="sortable" onclick="sortTable('cs.name')">
                                            <?php if ($sort_column == 'cs.name'): ?>
                                                <i class="fas fa-sort-<?= $sort_direction == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                            <?php endif; ?>
                                            فرستنده
                                        </th>
                                        <th class="sortable" onclick="sortTable('cr.name')">
                                            <?php if ($sort_column == 'cr.name'): ?>
                                                <i class="fas fa-sort-<?= $sort_direction == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                            <?php endif; ?>
                                            گیرنده
                                        </th>
                                        <th class="sortable" onclick="sortTable('l.subject')">
                                            <?php if ($sort_column == 'l.subject'): ?>
                                                <i class="fas fa-sort-<?= $sort_direction == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                            <?php endif; ?>
                                            موضوع
                                        </th>
                                        <th>دسته/برچسب</th>
                                        <th class="sortable" onclick="sortTable('l.status')">
                                            <?php if ($sort_column == 'l.status'): ?>
                                                <i class="fas fa-sort-<?= $sort_direction == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                            <?php endif; ?>
                                            وضعیت
                                        </th>
                                        <th>پیوست‌ها</th>
                                        <th>ارتباطات</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($letters as $letter): ?>
                                        <tr class="letter-row" onclick="viewLetter(<?= $letter['id'] ?>)">
                                            <td><strong><?= htmlspecialchars($letter['letter_number']) ?></strong></td>
                                            <td><?= PersianDate::format($letter['letter_date']) ?></td>
                                            <td><?= htmlspecialchars($letter['sender_name']) ?></td>
                                            <td><?= htmlspecialchars($letter['receiver_name']) ?></td>
                                            <td><?= htmlspecialchars(mb_substr($letter['subject'], 0, 50)) ?>...</td>
                                            <td>
                                                <?php if ($letter['category']): ?>
                                                    <span class="badge bg-secondary" style="font-size: 0.75em;">
                                                        <?= htmlspecialchars($letter['category']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php 
                                                if ($letter['tags']) {
                                                    $tags = json_decode($letter['tags'], true);
                                                    if (is_array($tags)) {
                                                        foreach (array_slice($tags, 0, 2) as $tag) {
                                                            echo '<br><span class="badge bg-info" style="font-size: 0.7em;">' . 
                                                                 htmlspecialchars($tag) . '</span> ';
                                                        }
                                                        if (count($tags) > 2) {
                                                            echo '<span class="badge bg-light text-dark" style="font-size: 0.7em;">+' . 
                                                                 (count($tags) - 2) . '</span>';
                                                        }
                                                    }
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusColors = [
                                                    'draft' => 'secondary',
                                                    'sent' => 'primary',
                                                    'received' => 'success',
                                                    'replied' => 'info',
                                                    'pending' => 'warning',
                                                    'archived' => 'dark'
                                                ];
                                                $statusNames = [
                                                    'draft' => 'پیش‌نویس',
                                                    'sent' => 'ارسال شده',
                                                    'received' => 'دریافت شده',
                                                    'replied' => 'پاسخ داده شده',
                                                    'pending' => 'در انتظار',
                                                    'archived' => 'بایگانی'
                                                ];
                                                ?>
                                                <span class="badge bg-<?= $statusColors[$letter['status']] ?? 'secondary' ?>">
                                                    <?= $statusNames[$letter['status']] ?? $letter['status'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <i class="fas fa-paperclip"></i> <?= $letter['attachment_count'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-link"></i> <?= $letter['relation_count'] ?>
                                                </span>
                                            </td>
                                            <td onclick="event.stopPropagation();">
                                                <button class="btn btn-sm btn-warning" onclick="editLetter(<?= $letter['id'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Letter Modal -->
    <div class="modal fade" id="addLetterModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">افزودن نامه جدید</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">شماره نامه *</label>
                                <input type="text" name="letter_number" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاریخ نامه *</label>
                                <input type="text" name="letter_date" class="form-control persian-datepicker" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">فرستنده *</label>
                                <select name="company_sender_id" class="form-select" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">گیرنده *</label>
                                <select name="company_receiver_id" class="form-select" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نام گیرنده</label>
                                <input type="text" name="recipient_name" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">سمت گیرنده</label>
                                <input type="text" name="recipient_position" class="form-control">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">موضوع *</label>
                                <input type="text" name="subject" class="form-control" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">خلاصه</label>
                                <textarea name="summary" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">وضعیت</label>
                                <select name="status" class="form-select">
                                    <option value="draft">پیش‌نویس</option>
                                    <option value="sent">ارسال شده</option>
                                    <option value="received">دریافت شده</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">اولویت</label>
                                <select name="priority" class="form-select">
                                    <option value="normal">عادی</option>
                                    <option value="high">بالا</option>
                                    <option value="urgent">فوری</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">دسته‌بندی</label>
                                <input type="text" name="category" class="form-control" placeholder="مثال: فنی، مالی، اداری" value="فنی">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">برچسب‌ها (Tags)</label>
                                <input type="text" name="tags" class="form-control" 
                                       placeholder="برچسب‌ها را با کاما جدا کنید: مهم، فوری، پیگیری">
                                <small class="text-muted">برچسب‌ها را با کاما (,) از هم جدا کنید</small>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">محتوای کامل نامه</label>
                                <textarea name="content_text" class="form-control" rows="4" 
                                          placeholder="متن کامل نامه را وارد کنید (برای جستجو در محتوا)"></textarea>
                                <small class="text-muted">این متن در جستجوهای پیشرفته قابل جستجو خواهد بود</small>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">یادداشت‌ها</label>
                                <textarea name="notes" class="form-control" rows="2" 
                                          placeholder="یادداشت‌های داخلی"></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">پیوست‌ها</label>
                                <input type="file" name="attachments[]" class="form-control" multiple>
                                <small class="text-muted">می‌توانید چند فایل را انتخاب کنید (PDF, TXT, DOC و...)</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="add_letter" class="btn btn-primary">ذخیره نامه</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Letter Modal -->
    <div class="modal fade" id="editLetterModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" id="editLetterForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="letter_id" id="edit_letter_id">
                    <div class="modal-header">
                        <h5 class="modal-title">ویرایش نامه</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">شماره نامه *</label>
                                <input type="text" name="letter_number" id="edit_letter_number" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاریخ نامه *</label>
                                <input type="text" name="letter_date" id="edit_letter_date" class="form-control persian-datepicker" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">فرستنده *</label>
                                <select name="company_sender_id" id="edit_company_sender_id" class="form-select" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">گیرنده *</label>
                                <select name="company_receiver_id" id="edit_company_receiver_id" class="form-select" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نام گیرنده</label>
                                <input type="text" name="recipient_name" id="edit_recipient_name" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">سمت گیرنده</label>
                                <input type="text" name="recipient_position" id="edit_recipient_position" class="form-control">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">موضوع *</label>
                                <input type="text" name="subject" id="edit_subject" class="form-control" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">خلاصه</label>
                                <textarea name="summary" id="edit_summary" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">وضعیت</label>
                                <select name="status" id="edit_status" class="form-select">
                                    <option value="draft">پیش‌نویس</option>
                                    <option value="sent">ارسال شده</option>
                                    <option value="received">دریافت شده</option>
                                    <option value="replied">پاسخ داده شده</option>
                                    <option value="pending">در انتظار</option>
                                    <option value="archived">بایگانی شده</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">اولویت</label>
                                <select name="priority" id="edit_priority" class="form-select">
                                    <option value="low">پایین</option>
                                    <option value="normal">عادی</option>
                                    <option value="high">بالا</option>
                                    <option value="urgent">فوری</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">دسته‌بندی</label>
                                <input type="text" name="category" id="edit_category" class="form-control">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">برچسب‌ها (Tags)</label>
                                <input type="text" name="tags" id="edit_tags" class="form-control">
                                <small class="text-muted">برچسب‌ها را با کاما (,) از هم جدا کنید</small>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">محتوای کامل نامه</label>
                                <textarea name="content_text" id="edit_content_text" class="form-control" rows="4"></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">یادداشت‌ها</label>
                                <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">افزودن پیوست‌های جدید</label>
                                <input type="file" name="attachments[]" class="form-control" multiple>
                                <small class="text-muted">پیوست‌های جدید به نامه اضافه می‌شوند (پیوست‌های قبلی حفظ می‌شوند)</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="update_letter" class="btn btn-primary">به‌روزرسانی نامه</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Relationship Modal -->
    <div class="modal fade" id="addRelationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">افزودن ارتباط بین نامه‌ها</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">نامه والد *</label>
                            <input type="text" id="parent_search" class="form-control" placeholder="جستجوی نامه...">
                            <input type="hidden" name="parent_letter_id" id="parent_letter_id" required>
                            <div id="parent_results" class="list-group mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">نامه فرزند *</label>
                            <input type="text" id="child_search" class="form-control" placeholder="جستجوی نامه...">
                            <input type="hidden" name="child_letter_id" id="child_letter_id" required>
                            <div id="child_results" class="list-group mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">نوع ارتباط *</label>
                            <select name="relationship_type_id" class="form-select" id="relationship_type" required>
                                <option value="">انتخاب کنید</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">توضیحات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="add_relationship" class="btn btn-success">ذخیره ارتباط</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-date@latest/dist/persian-date.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
    
    <script>
        // Initialize Persian Datepicker
        $('.persian-datepicker').persianDatepicker({
            format: 'YYYY/MM/DD',
            initialValue: false,
            autoClose: true,
            calendar: { 
                persian: { 
                    locale: 'fa',
                    leapYearMode: 'astronomical'
                } 
            }
        });
        
        // Sorting function
        function sortTable(column) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort');
            const currentDir = urlParams.get('dir') || 'DESC';
            
            if (currentSort === column) {
                urlParams.set('dir', currentDir === 'ASC' ? 'DESC' : 'ASC');
            } else {
                urlParams.set('sort', column);
                urlParams.set('dir', 'ASC');
            }
            
            window.location.search = urlParams.toString();
        }
        
        // Edit letter function
        function editLetter(id) {
            fetch(`?action=get_letter&id=${id}`)
                .then(r => r.json())
                .then(letter => {
                    if (!letter) {
                        alert('نامه یافت نشد');
                        return;
                    }
                    
                    document.getElementById('edit_letter_id').value = letter.id;
                    document.getElementById('edit_letter_number').value = letter.letter_number || '';
                    document.getElementById('edit_letter_date').value = letter.letter_date_persian || '';
                    document.getElementById('edit_company_sender_id').value = letter.company_sender_id || '';
                    document.getElementById('edit_company_receiver_id').value = letter.company_receiver_id || '';
                    document.getElementById('edit_recipient_name').value = letter.recipient_name || '';
                    document.getElementById('edit_recipient_position').value = letter.recipient_position || '';
                    document.getElementById('edit_subject').value = letter.subject || '';
                    document.getElementById('edit_summary').value = letter.summary || '';
                    document.getElementById('edit_status').value = letter.status || 'draft';
                    document.getElementById('edit_priority').value = letter.priority || 'normal';
                    document.getElementById('edit_category').value = letter.category || '';
                    document.getElementById('edit_content_text').value = letter.content_text || '';
                    document.getElementById('edit_notes').value = letter.notes || '';
                    
                    // Handle tags
                    if (letter.tags) {
                        try {
                            const tags = JSON.parse(letter.tags);
                            document.getElementById('edit_tags').value = Array.isArray(tags) ? tags.join(', ') : '';
                        } catch(e) {
                            document.getElementById('edit_tags').value = '';
                        }
                    } else {
                        document.getElementById('edit_tags').value = '';
                    }
                    
                    // Reinitialize datepicker for edit modal
                    $('#edit_letter_date').persianDatepicker({
                        format: 'YYYY/MM/DD',
                        initialValue: true,
                        autoClose: true,
                        calendar: { 
                            persian: { 
                                locale: 'fa',
                                leapYearMode: 'astronomical'
                            } 
                        }
                    });
                    
                    const editModal = new bootstrap.Modal(document.getElementById('editLetterModal'));
                    editModal.show();
                })
                .catch(err => {
                    console.error('Error loading letter:', err);
                    alert('خطا در بارگذاری اطلاعات نامه');
                });
        }
        
        // Load relationship types
        fetch('?action=get_relationship_types')
            .then(r => r.json())
            .then(data => {
                const select = document.getElementById('relationship_type');
                data.forEach(type => {
                    select.innerHTML += `<option value="${type.id}">${type.name_persian}</option>`;
                });
            });
        
        // Deep search functionality
        let searchTimeout;
        const deepSearchInput = document.getElementById('deepSearch');
        const searchResults = document.getElementById('searchResults');
        
        deepSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch(`?action=deep_search&q=${encodeURIComponent(query)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.count > 0) {
                            let html = '<div style="padding: 10px; background: #f0f0f0; font-weight: bold;">' +
                                      `یافت شد: ${data.count} مورد</div>`;
                            
                            data.results.forEach(result => {
                                const sourceIcon = result.source_type === 'attachment' ? 
                                    '<i class="fas fa-paperclip"></i>' : '<i class="fas fa-envelope"></i>';
                                const sourceBadge = result.source_type === 'attachment' ?
                                    '<span class="badge bg-info deep-search-badge">پیوست</span>' :
                                    '<span class="badge bg-primary deep-search-badge">نامه</span>';
                                
                                const matchBadge = result.match_type === 'content' ?
                                    '<span class="badge bg-success deep-search-badge">محتوای فایل</span>' : '';
                                
                                const categoryBadge = result.category ? 
                                    `<span class="badge bg-secondary deep-search-badge">${result.category}</span>` : '';
                                
                                // Format Persian date
                                const persianDate = result.letter_date ? formatPersianDate(result.letter_date) : '';
                                
                                html += `<div class="search-result-item" onclick="viewLetter(${result.id})">
                                    <div>
                                        ${sourceIcon} <strong>${result.letter_number}</strong> ${sourceBadge} ${matchBadge} ${categoryBadge}
                                        ${result.attachment_name ? '<br><small class="text-muted">📎 ' + result.attachment_name + '</small>' : ''}
                                    </div>
                                    <div><small>${result.subject}</small></div>
                                    <div class="text-muted" style="font-size: 0.85em;">
                                        ${result.sender_name} → ${result.receiver_name}
                                        ${persianDate ? ' | تاریخ: ' + persianDate : ''}
                                    </div>
                                </div>`;
                            });
                            
                            searchResults.innerHTML = html;
                            searchResults.style.display = 'block';
                        } else {
                            searchResults.innerHTML = '<div style="padding: 15px; text-align: center; color: #999;">موردی یافت نشد</div>';
                            searchResults.style.display = 'block';
                        }
                    })
                    .catch(err => {
                        console.error('Search error:', err);
                        searchResults.style.display = 'none';
                    });
            }, 500);
        });
        
        // Format Persian date helper function using Jalali conversion
        function formatPersianDate(gregorianDate) {
            if (!gregorianDate) return '';
            const parts = gregorianDate.split('-');
            if (parts.length !== 3) return '';
            
            const g_y = parseInt(parts[0]);
            const g_m = parseInt(parts[1]);
            const g_d = parseInt(parts[2]);
            
            // Convert Gregorian to Jalali
            const jalali = gregorianToJalali(g_y, g_m, g_d);
            return `${jalali[0]}/${jalali[1].toString().padStart(2, '0')}/${jalali[2].toString().padStart(2, '0')}`;
        }
        
        // Gregorian to Jalali conversion
        function gregorianToJalali(g_y, g_m, g_d) {
            const g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            const j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
            
            let gy = g_y - 1600;
            let gm = g_m - 1;
            let gd = g_d - 1;
            
            let g_day_no = 365 * gy + Math.floor((gy + 3) / 4) - Math.floor((gy + 99) / 100) + Math.floor((gy + 399) / 400);
            
            for (let i = 0; i < gm; ++i)
                g_day_no += g_days_in_month[i];
            if (gm > 1 && ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)))
                g_day_no++;
            g_day_no += gd;
            
            let j_day_no = g_day_no - 79;
            
            let j_np = Math.floor(j_day_no / 12053);
            j_day_no = j_day_no % 12053;
            
            let jy = 979 + 33 * j_np + 4 * Math.floor(j_day_no / 1461);
            
            j_day_no %= 1461;
            
            if (j_day_no >= 366) {
                jy += Math.floor((j_day_no - 1) / 365);
                j_day_no = (j_day_no - 1) % 365;
            }
            
            let jm = 0;
            for (let i = 0; i < 11 && j_day_no >= j_days_in_month[i]; ++i) {
                j_day_no -= j_days_in_month[i];
                jm++;
            }
            let jd = j_day_no + 1;
            
            return [jy, jm + 1, jd];
        }
        
        // Clear search
        document.getElementById('clearSearch').addEventListener('click', function() {
            deepSearchInput.value = '';
            searchResults.style.display = 'none';
        });
        
        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!deepSearchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
        
        // Letter search for relationships
        function setupLetterSearch(inputId, resultsId, hiddenId) {
            const input = document.getElementById(inputId);
            const results = document.getElementById(resultsId);
            const hidden = document.getElementById(hiddenId);
            
            let timeout;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                const query = this.value;
                
                if (query.length < 2) {
                    results.innerHTML = '';
                    return;
                }
                
                timeout = setTimeout(() => {
                    fetch(`?action=search_letters&q=${encodeURIComponent(query)}`)
                        .then(r => r.json())
                        .then(data => {
                            results.innerHTML = '';
                            data.forEach(letter => {
                                const item = document.createElement('a');
                                item.className = 'list-group-item list-group-item-action';
                                item.href = '#';
                                item.innerHTML = `
                                    <strong>${letter.letter_number}</strong><br>
                                    <small>${letter.subject}</small>
                                `;
                                item.onclick = function(e) {
                                    e.preventDefault();
                                    input.value = letter.letter_number + ' - ' + letter.subject;
                                    hidden.value = letter.id;
                                    results.innerHTML = '';
                                };
                                results.appendChild(item);
                            });
                        });
                }, 300);
            });
        }
        
        setupLetterSearch('parent_search', 'parent_results', 'parent_letter_id');
        setupLetterSearch('child_search', 'child_results', 'child_letter_id');
        
        // View letter details
        function viewLetter(id) {
            window.location.href = `view_letter.php?id=${id}`;
        }
    </script>
    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html> 