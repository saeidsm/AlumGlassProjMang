<?php
// edit_attachment.php - Edit attachment metadata and content
require_once __DIR__ . '/../../sercon/bootstrap.php';

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

if (!isset($_GET['id'])) {
    header('Location: letters.php');
    exit;
}

$attachment_id = $_GET['id'];

// Get attachment details
$stmt = $conn->prepare("
    SELECT a.*, l.letter_number, l.subject 
    FROM letter_attachments a
    JOIN letters l ON a.letter_id = l.id
    WHERE a.id = ?
");
$stmt->execute([$attachment_id]);
$attachment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attachment) {
    $_SESSION['error'] = 'پیوست یافت نشد';
    header('Location: letters.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attachment'])) {
    try {
        $stmt = $conn->prepare("
            UPDATE letter_attachments 
            SET title = ?, 
                description = ?, 
                attachment_type = ?, 
                text_summary = ?,
                extracted_text = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['title'] ?? '',
            $_POST['description'] ?? '',
            $_POST['attachment_type'] ?? 'supplement',
            $_POST['text_summary'] ?? '',
            $_POST['extracted_text'] ?? '',
            $attachment_id
        ]);
        
        $_SESSION['message'] = 'اطلاعات پیوست با موفقیت به‌روزرسانی شد';
        header('Location: view_letter.php?id=' . $attachment['letter_id']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطا در به‌روزرسانی: ' . $e->getMessage();
    }
}

// Auto-extract text if file exists and no extracted text
$autoExtractedText = '';
if (empty($attachment['extracted_text']) && file_exists($attachment['file_path'])) {
    $extension = strtolower(pathinfo($attachment['file_path'], PATHINFO_EXTENSION));
    
    // Try to extract text from text files
    if (in_array($extension, ['txt', 'log', 'csv', 'json', 'xml', 'html', 'css', 'js', 'php', 'md'])) {
        $autoExtractedText = file_get_contents($attachment['file_path']);
    }
}

$pageTitle = "ویرایش پیوست - پروژه دانشگاه خاتم پردیس";

function isMobileDevices() {
    return preg_match(
        "/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i",
        $_SERVER["HTTP_USER_AGENT"]
    );
}

if (isMobileDevices()) {
    require_once __DIR__ . '/header_p_mobile.php';
} else {
    require_once __DIR__ . '/header_pardis.php';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش پیوست - <?= htmlspecialchars($attachment['original_filename']) ?></title>
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
        .section-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .file-preview {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .char-counter {
            font-size: 0.85em;
            color: #6c757d;
            text-align: left;
            margin-top: 5px;
        }
        .help-text {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .btn-action {
            min-width: 120px;
        }
        .preview-box {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            white-space: pre-wrap;
            direction: ltr;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-edit"></i> 
                ویرایش محتوای پیوست
            </h2>
            <a href="view_letter.php?id=<?= $attachment['letter_id'] ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> بازگشت به نامه
            </a>
        </div>

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

        <!-- File Information -->
        <div class="main-card">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i> اطلاعات فایل
            </h3>
            
            <div class="file-preview">
                <i class="fas fa-file fa-3x text-primary mb-3"></i>
                <h5><?= htmlspecialchars($attachment['original_filename']) ?></h5>
                <p class="text-muted mb-0">
                    نامه: <strong><?= htmlspecialchars($attachment['letter_number']) ?></strong> - 
                    <?= htmlspecialchars($attachment['subject']) ?>
                </p>
                <small class="text-muted">
                    حجم: <?= number_format($attachment['file_size'] / 1024, 2) ?> KB | 
                    نوع: <?= htmlspecialchars($attachment['file_type']) ?>
                    <?php if ($attachment['page_count']): ?>
                        | صفحات: <?= $attachment['page_count'] ?>
                    <?php endif; ?>
                </small>
                <div class="mt-3">
                    <?php if (file_exists($attachment['file_path'])): ?>
                        <a href="<?= htmlspecialchars($attachment['file_path']) ?>" class="btn btn-sm btn-primary" target="_blank">
                            <i class="fas fa-external-link-alt"></i> باز کردن فایل
                        </a>
                        <a href="<?= htmlspecialchars($attachment['file_path']) ?>" class="btn btn-sm btn-success" download>
                            <i class="fas fa-download"></i> دانلود
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="main-card">
            <h3 class="section-title">
                <i class="fas fa-pencil-alt"></i> ویرایش محتوا و اطلاعات
            </h3>

            <div class="help-text">
                <i class="fas fa-lightbulb"></i>
                <strong>راهنما:</strong> 
                با پر کردن این فیلدها، جستجو در محتوای پیوست‌ها بهبود می‌یابد و اطلاعات کامل‌تری در صفحه نمایش نامه نشان داده می‌شود.
            </div>

            <form method="POST">
                <!-- Title -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-heading text-primary"></i>
                        <strong>عنوان پیوست</strong>
                    </label>
                    <input type="text" 
                           name="title" 
                           class="form-control" 
                           value="<?= htmlspecialchars($attachment['title'] ?? '') ?>"
                           placeholder="مثال: نقشه طبقه همکف، گزارش پیشرفت پروژه، ..."
                           maxlength="255">
                    <small class="text-muted">عنوان کوتاه و توصیفی برای این پیوست</small>
                </div>

                <!-- Attachment Type -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-tag text-secondary"></i>
                        <strong>نوع پیوست</strong>
                    </label>
                    <select name="attachment_type" class="form-select">
                        <option value="supplement" <?= ($attachment['attachment_type'] ?? 'supplement') == 'supplement' ? 'selected' : '' ?>>
                            مکمل (Supplement)
                        </option>
                        <option value="plan" <?= ($attachment['attachment_type'] ?? '') == 'plan' ? 'selected' : '' ?>>
                            نقشه (Plan)
                        </option>
                        <option value="report" <?= ($attachment['attachment_type'] ?? '') == 'report' ? 'selected' : '' ?>>
                            گزارش (Report)
                        </option>
                        <option value="contract" <?= ($attachment['attachment_type'] ?? '') == 'contract' ? 'selected' : '' ?>>
                            قرارداد (Contract)
                        </option>
                        <option value="other" <?= ($attachment['attachment_type'] ?? '') == 'other' ? 'selected' : '' ?>>
                            سایر (Other)
                        </option>
                    </select>
                </div>

                <!-- Description -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-align-left text-info"></i>
                        <strong>توضیحات</strong>
                    </label>
                    <textarea name="description" 
                              class="form-control" 
                              rows="4" 
                              id="description"
                              placeholder="توضیحات تکمیلی درباره این پیوست..."><?= htmlspecialchars($attachment['description'] ?? '') ?></textarea>
                    <div class="char-counter">
                        <span id="desc-counter">0</span> کاراکتر
                    </div>
                    <small class="text-muted">توضیحات کامل درباره محتوا و کاربرد این فایل</small>
                </div>

                <!-- Text Summary -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-compress-alt text-warning"></i>
                        <strong>خلاصه متن</strong>
                    </label>
                    <textarea name="text_summary" 
                              class="form-control" 
                              rows="5"
                              id="summary"
                              placeholder="خلاصه‌ای از نکات مهم و کلیدی موجود در این فایل..."><?= htmlspecialchars($attachment['text_summary'] ?? '') ?></textarea>
                    <div class="char-counter">
                        <span id="summary-counter">0</span> کاراکتر
                    </div>
                    <small class="text-muted">خلاصه مختصر و مفید از محتوای اصلی فایل (برای مرور سریع)</small>
                </div>

                <!-- Extracted Text -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-file-alt text-success"></i>
                        <strong>متن کامل استخراج شده</strong>
                    </label>
                    
                    <?php if ($autoExtractedText): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-magic"></i>
                            متن فایل به صورت خودکار استخراج شد. می‌توانید آن را ویرایش کنید.
                            <button type="button" class="btn btn-sm btn-primary float-end" onclick="loadAutoExtracted()">
                                <i class="fas fa-download"></i> بارگذاری متن استخراج شده
                            </button>
                        </div>
                        <div id="auto-extracted" style="display:none;"><?= htmlspecialchars($autoExtractedText) ?></div>
                    <?php endif; ?>
                    
                    <textarea name="extracted_text" 
                              class="form-control" 
                              rows="15"
                              id="extracted"
                              style="font-family: 'Courier New', monospace; direction: ltr; text-align: left;"
                              placeholder="متن کامل موجود در فایل (برای جستجوی دقیق در محتوا)..."><?= htmlspecialchars($attachment['extracted_text'] ?? '') ?></textarea>
                    <div class="char-counter">
                        <span id="extracted-counter">0</span> کاراکتر | 
                        <span id="word-counter">0</span> کلمه
                    </div>
                    <small class="text-muted">
                        متن کامل محتوای فایل (برای فایل‌های متنی، PDF، یا سایر فرمت‌ها). 
                        این متن در جستجوهای پیشرفته قابل جستجو خواهد بود.
                    </small>
                </div>

                <!-- Preview -->
                <?php if (!empty($attachment['extracted_text']) && strlen($attachment['extracted_text']) > 100): ?>
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-eye text-secondary"></i>
                        <strong>پیش‌نمایش متن فعلی</strong>
                    </label>
                    <div class="preview-box">
<?= htmlspecialchars(substr($attachment['extracted_text'], 0, 1000)) ?>
<?php if (strlen($attachment['extracted_text']) > 1000): ?>
... (<?= number_format(strlen($attachment['extracted_text']) - 1000) ?> کاراکتر دیگر)
<?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="d-flex gap-2 justify-content-end">
                    <a href="view_letter.php?id=<?= $attachment['letter_id'] ?>" class="btn btn-secondary btn-action">
                        <i class="fas fa-times"></i> انصراف
                    </a>
                    <button type="submit" name="update_attachment" class="btn btn-success btn-action">
                        <i class="fas fa-save"></i> ذخیره تغییرات
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character counters
        function updateCounter(textareaId, counterId, wordCounterId = null) {
            const textarea = document.getElementById(textareaId);
            const counter = document.getElementById(counterId);
            
            textarea.addEventListener('input', function() {
                counter.textContent = this.value.length.toLocaleString();
                
                if (wordCounterId) {
                    const words = this.value.trim().split(/\s+/).filter(w => w.length > 0);
                    document.getElementById(wordCounterId).textContent = words.length.toLocaleString();
                }
            });
            
            // Initial count
            textarea.dispatchEvent(new Event('input'));
        }
        
        updateCounter('description', 'desc-counter');
        updateCounter('summary', 'summary-counter');
        updateCounter('extracted', 'extracted-counter', 'word-counter');
        
        // Load auto-extracted text
        function loadAutoExtracted() {
            const autoText = document.getElementById('auto-extracted');
            const textarea = document.getElementById('extracted');
            
            if (autoText && textarea) {
                if (confirm('این عمل محتوای فعلی را جایگزین می‌کند. ادامه می‌دهید؟')) {
                    textarea.value = autoText.textContent;
                    textarea.dispatchEvent(new Event('input'));
                    alert('متن با موفقیت بارگذاری شد!');
                }
            }
        }
        
        // Warn before leaving if form is dirty
        let formChanged = false;
        document.querySelectorAll('input, textarea, select').forEach(el => {
            el.addEventListener('change', () => formChanged = true);
        });
        
        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        document.querySelector('form').addEventListener('submit', () => {
            formChanged = false;
        });
    </script>
    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>