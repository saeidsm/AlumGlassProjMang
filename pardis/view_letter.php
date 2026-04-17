<?php
// Enhanced view_letter.php with PDF and text file viewing

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

$pageTitle = "جزییات نامه‌ها - پروژه دانشگاه خاتم پردیس";

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
//session_start();

$conn = getLetterTrackingDBConnection();
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
    
    public static function format($date) {
        if (!$date) return '';
        $parts = explode('-', $date);
        $jalali = self::toJalali($parts[0], $parts[1], $parts[2]);
        return sprintf('%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2]);
    }
}



if (!isset($_GET['id'])) {
    header('Location: letters.php');
    exit;
}

$letter_id = $_GET['id'];

// Get letter details
$stmt = $conn->prepare("
    SELECT l.*, 
        cs.name as sender_name, cs.name_english as sender_name_eng,
        cr.name as receiver_name, cr.name_english as receiver_name_eng
    FROM letters l
    LEFT JOIN companies cs ON l.company_sender_id = cs.id
    LEFT JOIN companies cr ON l.company_receiver_id = cr.id
    WHERE l.id = ?
");
$stmt->execute([$letter_id]);
$letter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$letter) {
    $_SESSION['error'] = 'نامه یافت نشد';
    header('Location: letters.php');
    exit;
}

// Get attachments
$stmt = $conn->prepare("
    SELECT * FROM letter_attachments 
    WHERE letter_id = ? 
    ORDER BY uploaded_at DESC
");
$stmt->execute([$letter_id]);
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get parent relationships
$stmt = $conn->prepare("
    SELECT lr.*, l.letter_number, l.subject, l.letter_date, 
        rt.name_persian as rel_name, rt.name_english as rel_name_eng
    FROM letter_relationships lr
    JOIN letters l ON lr.parent_letter_id = l.id
    JOIN relationship_types rt ON lr.relationship_type_id = rt.id
    WHERE lr.child_letter_id = ?
    ORDER BY l.letter_date DESC
");
$stmt->execute([$letter_id]);
$parent_relations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get child relationships
$stmt = $conn->prepare("
    SELECT lr.*, l.letter_number, l.subject, l.letter_date,
        rt.name_persian as rel_name, rt.name_english as rel_name_eng
    FROM letter_relationships lr
    JOIN letters l ON lr.child_letter_id = l.id
    JOIN relationship_types rt ON lr.relationship_type_id = rt.id
    WHERE lr.parent_letter_id = ?
    ORDER BY l.letter_date DESC
");
$stmt->execute([$letter_id]);
$child_relations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle delete attachment
if (isset($_POST['delete_attachment'])) {
    $att_id = $_POST['attachment_id'];
    $stmt = $conn->prepare("SELECT file_path FROM letter_attachments WHERE id = ? AND letter_id = ?");
    $stmt->execute([$att_id, $letter_id]);
    $att = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($att && file_exists($att['file_path'])) {
        unlink($att['file_path']);
    }
    
    $stmt = $conn->prepare("DELETE FROM letter_attachments WHERE id = ? AND letter_id = ?");
    $stmt->execute([$att_id, $letter_id]);
    
    $_SESSION['message'] = 'پیوست حذف شد';
    header("Location: view_letter.php?id=$letter_id");
    exit;
}

// Handle delete relationship
if (isset($_POST['delete_relation'])) {
    $rel_id = $_POST['relation_id'];
    $stmt = $conn->prepare("DELETE FROM letter_relationships WHERE id = ?");
    $stmt->execute([$rel_id]);
    
    $_SESSION['message'] = 'ارتباط حذف شد';
    header("Location: view_letter.php?id=$letter_id");
    exit;
}

// Function to determine if file can be viewed inline
function canViewInline($file_type, $file_path) {
    // **FIX:** Ensure $file_type is a string to prevent deprecated notice if it's null.
    $file_type = (string) $file_type;
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    // PDF files
    if ($file_type === 'application/pdf' || $extension === 'pdf') {
        return 'pdf';
    }
    
    // Text files
    if (strpos($file_type, 'text/') === 0 || in_array($extension, ['txt', 'log', 'csv', 'json', 'xml', 'html', 'css', 'js', 'php'])) {
        return 'text';
    }
    
    // Image files
    if (strpos($file_type, 'image/') === 0 || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'])) {
        return 'image';
    }
    
    return false;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات نامه - <?= htmlspecialchars($letter['letter_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f5f5;
        }
        .main-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        .info-row {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            width: 150px;
        }
        .info-value {
            color: #333;
        }
        .section-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .attachment-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }
        .attachment-item:hover {
            background: #e9ecef;
        }
        .attachment-item-clickable {
            cursor: pointer;
        }
        .attachment-item-clickable:hover {
             transform: translateX(-5px);
        }
        .relation-card {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .relation-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .badge-custom {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .content-box {
            background: #f8f9fa;
            border-right: 4px solid #3498db;
            padding: 20px;
            border-radius: 5px;
            white-space: pre-wrap;
            line-height: 1.8;
        }
        .btn-action {
            margin: 0 5px;
        }
        
        /* File Viewer Styles */
        .file-viewer-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 10000;
        }
        .file-viewer-content {
            position: relative;
            width: 90%;
            height: 90%;
            margin: 2% auto;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        .file-viewer-header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .file-viewer-body {
            height: calc(100% - 60px);
            overflow: auto;
            padding: 20px;
            background: white;
        }
        .file-viewer-close {
            background: #e74c3c;
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.2em;
        }
        .file-viewer-close:hover {
            background: #c0392b;
        }
        #pdfViewer {
            width: 100%;
            height: 100%;
            border: none;
        }
        #textViewer {
            width: 100%;
            height: 100%;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            white-space: pre-wrap;
            background: #f8f9fa;
            padding: 20px;
            border: 1px solid #ddd;
            overflow: auto;
            direction: ltr;
            text-align: left;
        }
        #imageViewer {
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #000;
        }
        #imageViewer img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .view-file-icon {
            color: #3498db;
            font-size: 1.2em;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-envelope-open-text"></i> 
                جزئیات نامه: <?= htmlspecialchars($letter['letter_number']) ?>
            </h2>
            <a href="letters.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> بازگشت
            </a>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible">
                <?= $_SESSION['message']; unset($_SESSION['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Main Information -->
        <div class="main-card">
            <h3 class="section-title">اطلاعات اصلی</h3>
            
            <div class="info-row">
                <span class="info-label">شماره نامه:</span>
                <span class="info-value"><strong><?= htmlspecialchars($letter['letter_number']) ?></strong></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">تاریخ نامه:</span>
                <span class="info-value"><?= PersianDate::format($letter['letter_date']) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">تاریخ ثبت:</span>
                <span class="info-value"><?= PersianDate::format(date('Y-m-d', strtotime($letter['registration_date']))) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">فرستنده:</span>
                <span class="info-value"><?= htmlspecialchars($letter['sender_name']) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">گیرنده:</span>
                <span class="info-value"><?= htmlspecialchars($letter['receiver_name']) ?></span>
            </div>
            
            <?php if ($letter['recipient_name']): ?>
            <div class="info-row">
                <span class="info-label">نام گیرنده:</span>
                <span class="info-value"><?= htmlspecialchars($letter['recipient_name']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($letter['recipient_position']): ?>
            <div class="info-row">
                <span class="info-label">سمت گیرنده:</span>
                <span class="info-value"><?= htmlspecialchars($letter['recipient_position']) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <span class="info-label">وضعیت:</span>
                <span class="info-value">
                    <?php
                    $statusColors = [
                        'draft' => 'secondary',
                        'sent' => 'primary',
                        'received' => 'success',
                        'pending' => 'warning',
                        'replied' => 'info',
                        'archived' => 'dark'
                    ];
                    $statusNames = [
                        'draft' => 'پیش‌نویس',
                        'sent' => 'ارسال شده',
                        'received' => 'دریافت شده',
                        'pending' => 'در انتظار',
                        'replied' => 'پاسخ داده شده',
                        'archived' => 'بایگانی شده'
                    ];
                    ?>
                    <span class="badge bg-<?= $statusColors[$letter['status']] ?? 'secondary' ?> badge-custom">
                        <?= $statusNames[$letter['status']] ?? $letter['status'] ?>
                    </span>
                </span>
            </div>
            
            <div class="info-row">
                <span class="info-label">اولویت:</span>
                <span class="info-value">
                    <?php
                    $priorityColors = ['low' => 'success', 'normal' => 'info', 'high' => 'warning', 'urgent' => 'danger'];
                    $priorityNames = ['low' => 'پایین', 'normal' => 'عادی', 'high' => 'بالا', 'urgent' => 'فوری'];
                    ?>
                    <span class="badge bg-<?= $priorityColors[$letter['priority']] ?> badge-custom">
                        <?= $priorityNames[$letter['priority']] ?>
                    </span>
                </span>
            </div>
            
            <?php if ($letter['category']): ?>
            <div class="info-row">
                <span class="info-label">دسته‌بندی:</span>
                <span class="info-value"><span class="badge bg-secondary badge-custom"><?= htmlspecialchars($letter['category']) ?></span></span>
            </div>
            <?php endif; ?>
            
            <?php if ($letter['tags']): 
                $tags = json_decode($letter['tags'], true);
                if (is_array($tags) && count($tags) > 0):
            ?>
            <div class="info-row">
                <span class="info-label">برچسب‌ها:</span>
                <span class="info-value">
                    <?php foreach ($tags as $tag): ?>
                        <span class="badge bg-info badge-custom"><?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </span>
            </div>
            <?php endif; endif; ?>
        </div>

        <!-- **NEW** Original Letter Document -->
        <?php if (!empty($letter['document_path']) && file_exists($letter['document_path'])): ?>
        <div class="main-card">
            <h3 class="section-title">
                <i class="fas fa-file-alt"></i> اصل نامه
            </h3>
            <?php
                $letter_file_path = $letter['document_path'];
                $letter_view_type = canViewInline(null, $letter_file_path); 
                $letter_original_filename = 'اصل نامه - ' . $letter['letter_number'] . '.' . pathinfo($letter_file_path, PATHINFO_EXTENSION);
            ?>
            <div class="attachment-item">
                <div>
                    <?php
                    $iconClass = 'fa-file';
                    if ($letter_view_type === 'pdf') $iconClass = 'fa-file-pdf text-danger';
                    elseif ($letter_view_type === 'image') $iconClass = 'fa-file-image text-success';
                    ?>
                    <i class="fas <?= $iconClass ?> fa-2x"></i>
                    <strong class="ms-3">فایل اصلی نامه (<?= htmlspecialchars(basename($letter_file_path)) ?>)</strong>
                </div>
                <div>
                    <?php if ($letter_view_type): ?>
                        <button class="btn btn-sm btn-info btn-action" onclick="openFileViewer('<?= htmlspecialchars($letter_file_path) ?>', '<?= $letter_view_type ?>', '<?= htmlspecialchars($letter_original_filename, ENT_QUOTES) ?>')">
                            <i class="fas fa-eye"></i> مشاهده
                        </button>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($letter_file_path) ?>" class="btn btn-sm btn-primary btn-action" download>
                        <i class="fas fa-download"></i> دانلود
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Subject and Summary -->
        <div class="main-card">
            <h3 class="section-title">موضوع و خلاصه</h3>
            
            <div class="mb-3">
                <strong>موضوع:</strong>
                <p class="mt-2"><?= htmlspecialchars($letter['subject']) ?></p>
            </div>
            
            <?php if ($letter['summary']): ?>
            <div class="mb-3">
                <strong>خلاصه:</strong>
                <p class="mt-2"><?= nl2br(htmlspecialchars($letter['summary'])) ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($letter['content_text']): ?>
            <div>
                <strong>متن کامل:</strong>
                <div class="content-box mt-2">
                    <?= nl2br(htmlspecialchars($letter['content_text'])) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Attachments with Viewer -->
        <?php if (count($attachments) > 0): ?>
<div class="main-card">
    <h3 class="section-title">
        <i class="fas fa-paperclip"></i> پیوست‌ها (<?= count($attachments) ?>)
    </h3>
    
    <?php foreach ($attachments as $att): 
        $viewType = canViewInline($att['file_type'], $att['file_path']);
        $item_class = $viewType ? 'attachment-item-clickable' : '';
        $hasExtractedText = !empty($att['extracted_text']) && trim($att['extracted_text']) != '';
        $hasDescription = !empty($att['description']) && trim($att['description']) != '';
        $hasTitle = !empty($att['title']) && trim($att['title']) != '';
        $hasSummary = !empty($att['text_summary']) && trim($att['text_summary']) != '';
        $attachmentType = $att['attachment_type'] ?? 'supplement';
        
        // Translate attachment types
        $typeLabels = [
            'supplement' => 'مکمل',
            'plan' => 'نقشه',
            'report' => 'گزارش',
            'contract' => 'قرارداد',
            'other' => 'سایر'
        ];
        $typeLabel = $typeLabels[$attachmentType] ?? $attachmentType;
    ?>
    <div class="attachment-card mb-4 p-3 border rounded shadow-sm">
        <!-- Attachment Header with File Info -->
        <div class="attachment-item <?= $item_class ?>" onclick="<?= $viewType ? "openFileViewer('{$att['file_path']}', '{$viewType}', '" . htmlspecialchars($att['original_filename'], ENT_QUOTES) . "')" : '' ?>">
            <div>
                <?php
                $iconClass = 'fa-file';
                $iconColor = '';
                if ($viewType === 'pdf') {
                    $iconClass = 'fa-file-pdf';
                    $iconColor = 'text-danger';
                } elseif ($viewType === 'text') {
                    $iconClass = 'fa-file-alt';
                    $iconColor = 'text-primary';
                } elseif ($viewType === 'image') {
                    $iconClass = 'fa-file-image';
                    $iconColor = 'text-success';
                }
                ?>
                <i class="fas <?= $iconClass ?> <?= $iconColor ?> fa-2x"></i>
                <strong class="ms-3"><?= htmlspecialchars($att['original_filename']) ?></strong>
                <?php if ($viewType): ?>
                    <i class="fas fa-eye view-file-icon" title="کلیک برای مشاهده فایل"></i>
                <?php endif; ?>
                <br>
                <small class="text-muted ms-5">
                    <span class="badge bg-<?= $attachmentType === 'plan' ? 'primary' : ($attachmentType === 'report' ? 'info' : 'secondary') ?>">
                        <?= htmlspecialchars($typeLabel) ?>
                    </span>
                    | حجم: <?= number_format($att['file_size'] / 1024, 2) ?> KB
                    <?php if ($att['page_count']): ?>
                        | صفحات: <?= $att['page_count'] ?>
                    <?php endif; ?>
                    <?php if ($hasExtractedText): ?>
                        | <span class="badge bg-success"><i class="fas fa-check-circle"></i> متن استخراج شده</span>
                    <?php endif; ?>
                    <br>
                    <i class="fas fa-clock"></i> آپلود: <?= date('Y/m/d H:i', strtotime($att['uploaded_at'])) ?>
                </small>
            </div>
            <div onclick="event.stopPropagation();">
                <?php if (file_exists($att['file_path'])): ?>
                    <a href="<?= htmlspecialchars($att['file_path']) ?>" class="btn btn-sm btn-primary btn-action" download>
                        <i class="fas fa-download"></i> دانلود
                    </a>
                <?php endif; ?>
                <a href="edit_attachment.php?id=<?= $att['id'] ?>" class="btn btn-sm btn-warning btn-action">
                    <i class="fas fa-edit"></i> ویرایش محتوا
                </a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('آیا از حذف این پیوست مطمئن هستید؟');">
                    <input type="hidden" name="attachment_id" value="<?= $att['id'] ?>">
                    <button type="submit" name="delete_attachment" class="btn btn-sm btn-danger btn-action">
                        <i class="fas fa-trash"></i> حذف
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Attachment Title -->
        <?php if ($hasTitle): ?>
        <div class="mt-3 p-3 bg-light rounded border-start border-4 border-primary">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-heading text-primary me-2"></i>
                <strong class="text-primary">عنوان پیوست:</strong>
            </div>
            <p class="mb-0"><?= nl2br(htmlspecialchars($att['title'])) ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Attachment Description -->
        <?php if ($hasDescription): ?>
        <div class="mt-3 p-3 bg-light rounded border-start border-4 border-info">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-info-circle text-info me-2"></i>
                <strong class="text-info">توضیحات:</strong>
            </div>
            <p class="mb-0" style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars($att['description'])) ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Text Summary (AI-generated or manual summary) -->
        <?php if ($hasSummary): ?>
        <div class="mt-3 p-3 bg-warning bg-opacity-10 rounded border border-warning">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-compress-alt text-warning me-2"></i>
                <strong class="text-warning">خلاصه متن:</strong>
            </div>
            <p class="mb-0" style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars($att['text_summary'])) ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Extracted Text Content (Full OCR or parsed text) -->
        <?php if ($hasExtractedText): ?>
        <div class="mt-3 border rounded">
            <div class="p-3 bg-success bg-opacity-10 border-bottom d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="toggleExtractedText(<?= $att['id'] ?>)">
                <div>
                    <i class="fas fa-file-alt text-success me-2"></i>
                    <strong class="text-success">متن استخراج شده از فایل</strong>
                    <small class="text-muted ms-3">
                        <i class="fas fa-ruler"></i> 
                        <?= number_format(strlen($att['extracted_text'])) ?> کاراکتر
                        | 
                        <?= number_format(str_word_count($att['extracted_text'], 0, 'آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی۰۱۲۳۴۵۶۷۸۹')) ?> کلمه
                    </small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-chevron-down" id="toggle-icon-<?= $att['id'] ?>"></i>
                    <span id="toggle-text-<?= $att['id'] ?>">نمایش متن</span>
                </button>
            </div>
            <div id="extracted-text-<?= $att['id'] ?>" class="extracted-text-content p-3" style="display: none;">
                <div class="content-box bg-white" style="max-height: 500px; overflow-y: auto; font-family: 'Courier New', 'Tahoma', monospace; font-size: 0.9em; white-space: pre-wrap; line-height: 1.6; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
<?= htmlspecialchars($att['extracted_text']) ?>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('extracted-text-content-<?= $att['id'] ?>')">
                        <i class="fas fa-copy"></i> کپی متن
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="searchInText(<?= $att['id'] ?>)">
                        <i class="fas fa-search"></i> جستجو در متن
                    </button>
                </div>
                <!-- Search box (hidden by default) -->
                <div id="search-box-<?= $att['id'] ?>" class="mt-2" style="display: none;">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="search-input-<?= $att['id'] ?>" placeholder="عبارت جستجو...">
                        <button class="btn btn-outline-secondary" type="button" onclick="performSearch(<?= $att['id'] ?>)">
                            <i class="fas fa-search"></i> جستجو
                        </button>
                        <button class="btn btn-outline-danger" type="button" onclick="clearSearch(<?= $att['id'] ?>)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <small class="text-muted" id="search-results-<?= $att['id'] ?>"></small>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- If no content available -->
        <?php if (!$hasTitle && !$hasDescription && !$hasSummary && !$hasExtractedText): ?>
        <div class="mt-3 p-3 bg-light rounded text-center text-muted">
            <i class="fas fa-inbox"></i>
            <p class="mb-0">اطلاعات تکمیلی برای این پیوست ثبت نشده است</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- ADD this CSS to the existing <style> section in the <head> -->
<style>
.attachment-card {
    background: #fff;
    transition: all 0.3s ease;
}
.attachment-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}
.extracted-text-content {
    animation: slideDown 0.3s ease-out;
}
@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 600px;
    }
}
.content-box::-webkit-scrollbar {
    width: 8px;
}
.content-box::-webkit-scrollbar-track {
    background: #f1f1f1;
}
.content-box::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}
.content-box::-webkit-scrollbar-thumb:hover {
    background: #555;
}
mark.search-highlight {
    background-color: yellow;
    padding: 2px 4px;
    border-radius: 2px;
}
</style>

<!-- ADD this JavaScript before the closing </body> tag (after existing scripts) -->
<script>
// Toggle extracted text visibility
function toggleExtractedText(attachmentId) {
    const content = document.getElementById('extracted-text-' + attachmentId);
    const icon = document.getElementById('toggle-icon-' + attachmentId);
    const text = document.getElementById('toggle-text-' + attachmentId);
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
        text.textContent = 'پنهان کردن';
    } else {
        content.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
        text.textContent = 'نمایش متن';
    }
}

// Copy text to clipboard
function copyToClipboard(elementId) {
    const element = document.querySelector('#extracted-text-' + elementId.split('-')[2] + ' .content-box');
    if (!element) return;
    
    const text = element.textContent;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            alert('متن با موفقیت کپی شد!');
        }).catch(err => {
            console.error('خطا در کپی:', err);
            fallbackCopyText(text);
        });
    } else {
        fallbackCopyText(text);
    }
}

function fallbackCopyText(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        alert('متن کپی شد!');
    } catch (err) {
        alert('خطا در کپی متن');
    }
    document.body.removeChild(textarea);
}

// Show search box
function searchInText(attachmentId) {
    const searchBox = document.getElementById('search-box-' + attachmentId);
    if (searchBox.style.display === 'none') {
        searchBox.style.display = 'block';
        document.getElementById('search-input-' + attachmentId).focus();
    } else {
        searchBox.style.display = 'none';
    }
}

// Perform search in extracted text
function performSearch(attachmentId) {
    const searchInput = document.getElementById('search-input-' + attachmentId);
    const searchTerm = searchInput.value.trim();
    
    if (!searchTerm) {
        alert('لطفا عبارت جستجو را وارد کنید');
        return;
    }
    
    const contentBox = document.querySelector('#extracted-text-' + attachmentId + ' .content-box');
    const originalText = contentBox.textContent;
    
    // Clear previous highlights
    contentBox.innerHTML = '';
    
    // Case-insensitive search
    const regex = new RegExp(searchTerm, 'gi');
    const matches = originalText.match(regex);
    
    if (matches) {
        // Highlight matches
        const highlightedText = originalText.replace(regex, match => `<mark class="search-highlight">${match}</mark>`);
        contentBox.innerHTML = highlightedText;
        
        // Show results count
        const resultsDiv = document.getElementById('search-results-' + attachmentId);
        resultsDiv.textContent = `${matches.length} مورد یافت شد`;
        
        // Scroll to first match
        const firstMark = contentBox.querySelector('mark');
        if (firstMark) {
            firstMark.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    } else {
        contentBox.textContent = originalText;
        const resultsDiv = document.getElementById('search-results-' + attachmentId);
        resultsDiv.textContent = 'موردی یافت نشد';
    }
}

// Clear search
function clearSearch(attachmentId) {
    const contentBox = document.querySelector('#extracted-text-' + attachmentId + ' .content-box');
    const searchInput = document.getElementById('search-input-' + attachmentId);
    const resultsDiv = document.getElementById('search-results-' + attachmentId);
    const searchBox = document.getElementById('search-box-' + attachmentId);
    
    // Get original text without HTML
    const originalText = contentBox.textContent;
    contentBox.textContent = originalText;
    
    searchInput.value = '';
    resultsDiv.textContent = '';
    searchBox.style.display = 'none';
}
</script>

<?php endif; ?>

        <!-- Parent Relationships -->
        <?php if (count($parent_relations) > 0): ?>
        <div class="main-card">
            <h3 class="section-title">
                <i class="fas fa-link"></i> نامه‌های مرتبط (والد)
            </h3>
            <p class="text-muted">این نامه در ارتباط با نامه‌های زیر است:</p>
            
            <?php foreach ($parent_relations as $rel): ?>
            <div class="relation-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <span class="badge bg-info"><?= htmlspecialchars($rel['rel_name']) ?></span>
                        <h5 class="mt-2">
                            <a href="view_letter.php?id=<?= $rel['parent_letter_id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($rel['letter_number']) ?>
                            </a>
                        </h5>
                        <p class="mb-1"><?= htmlspecialchars($rel['subject']) ?></p>
                        <small class="text-muted">
                            <i class="fas fa-calendar"></i> <?= PersianDate::format($rel['letter_date']) ?>
                        </small>
                        <?php if ($rel['notes']): ?>
                            <p class="mt-2 mb-0"><small><strong>توضیحات:</strong> <?= htmlspecialchars($rel['notes']) ?></small></p>
                        <?php endif; ?>
                    </div>
                    <form method="POST" onsubmit="return confirm('آیا مطمئن هستید؟');">
                        <input type="hidden" name="relation_id" value="<?= $rel['id'] ?>">
                        <button type="submit" name="delete_relation" class="btn btn-sm btn-danger">
                            <i class="fas fa-times"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Child Relationships -->
        <?php if (count($child_relations) > 0): ?>
        <div class="main-card">
            <h3 class="section-title">
                <i class="fas fa-reply"></i> نامه‌های پاسخ (فرزند)
            </h3>
            <p class="text-muted">نامه‌های زیر در پاسخ به این نامه هستند:</p>
            
            <?php foreach ($child_relations as $rel): ?>
            <div class="relation-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <span class="badge bg-success"><?= htmlspecialchars($rel['rel_name']) ?></span>
                        <h5 class="mt-2">
                            <a href="view_letter.php?id=<?= $rel['child_letter_id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($rel['letter_number']) ?>
                            </a>
                        </h5>
                        <p class="mb-1"><?= htmlspecialchars($rel['subject']) ?></p>
                        <small class="text-muted">
                            <i class="fas fa-calendar"></i> <?= PersianDate::format($rel['letter_date']) ?>
                        </small>
                        <?php if ($rel['notes']): ?>
                            <p class="mt-2 mb-0"><small><strong>توضیحات:</strong> <?= htmlspecialchars($rel['notes']) ?></small></p>
                        <?php endif; ?>
                    </div>
                    <form method="POST" onsubmit="return confirm('آیا مطمئن هستید؟');">
                        <input type="hidden" name="relation_id" value="<?= $rel['id'] ?>">
                        <button type="submit" name="delete_relation" class="btn btn-sm btn-danger">
                            <i class="fas fa-times"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Additional Notes -->
        <?php if ($letter['notes']): ?>
        <div class="main-card">
            <h3 class="section-title">
                <i class="fas fa-sticky-note"></i> یادداشت‌ها
            </h3>
            <div class="content-box">
                <?= nl2br(htmlspecialchars($letter['notes'])) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- File Viewer Modal -->
    <div id="fileViewerModal" class="file-viewer-modal">
        <div class="file-viewer-content">
            <div class="file-viewer-header">
                <h5 id="viewerTitle"></h5>
                <div>
                    <button class="btn btn-sm btn-light" onclick="downloadCurrentFile()">
                        <i class="fas fa-download"></i> دانلود
                    </button>
                    <button class="file-viewer-close" onclick="closeFileViewer()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="file-viewer-body" id="fileViewerBody">
                <iframe id="pdfViewer" style="display:none;"></iframe>
                <pre id="textViewer" style="display:none;"></pre>
                <div id="imageViewer" style="display:none;"><img id="imageContent" /></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentFilePath = '';
        
        function openFileViewer(filePath, type, filename) {
            currentFilePath = filePath;
            const modal = document.getElementById('fileViewerModal');
            const title = document.getElementById('viewerTitle');
            const pdfViewer = document.getElementById('pdfViewer');
            const textViewer = document.getElementById('textViewer');
            const imageViewer = document.getElementById('imageViewer');
            const imageContent = document.getElementById('imageContent');
            
            // Hide all viewers
            pdfViewer.style.display = 'none';
            textViewer.style.display = 'none';
            imageViewer.style.display = 'none';
            
            title.textContent = filename;
            
            if (type === 'pdf') {
                pdfViewer.src = filePath;
                pdfViewer.style.display = 'block';
            } else if (type === 'text') {
                fetch(filePath)
                    .then(response => response.text())
                    .then(text => {
                        textViewer.textContent = text;
                        textViewer.style.display = 'block';
                    })
                    .catch(err => {
                        textViewer.textContent = 'خطا در بارگذاری فایل: ' + err.message;
                        textViewer.style.display = 'block';
                    });
            } else if (type === 'image') {
                imageContent.src = filePath;
                imageViewer.style.display = 'flex';
            }
            
            modal.style.display = 'block';
        }
        
        function closeFileViewer() {
            const modal = document.getElementById('fileViewerModal');
            modal.style.display = 'none';
            document.getElementById('pdfViewer').src = '';
            document.getElementById('textViewer').textContent = '';
            document.getElementById('imageContent').src = '';
        }
        
        function downloadCurrentFile() {
            if (currentFilePath) {
                const link = document.createElement('a');
                link.href = currentFilePath;
                link.download = '';
                link.click();
            }
        }
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeFileViewer();
            }
        });
        
        // Close modal when clicking outside
        document.getElementById('fileViewerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeFileViewer();
            }
        });
    </script>
    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>