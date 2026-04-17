<?php
// bulk_edit_attachments.php - Bulk edit attachments with missing content
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

// Handle bulk update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    try {
        $updated = 0;
        foreach ($_POST['attachments'] as $id => $data) {
            if (!empty($data['update'])) {
                $stmt = $conn->prepare("
                    UPDATE letter_attachments 
                    SET title = ?, 
                        description = ?, 
                        attachment_type = ?, 
                        text_summary = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $data['title'] ?? '',
                    $data['description'] ?? '',
                    $data['attachment_type'] ?? 'supplement',
                    $data['text_summary'] ?? '',
                    $id
                ]);
                $updated++;
            }
        }
        
        $_SESSION['message'] = "$updated پیوست با موفقیت به‌روزرسانی شد";
        header('Location: bulk_edit_attachments.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطا در به‌روزرسانی: ' . $e->getMessage();
    }
}

// Get attachments with missing content
$filter = $_GET['filter'] ?? 'all';
$where_conditions = [];

if ($filter === 'no_title') {
    $where_conditions[] = "(title IS NULL OR title = '')";
} elseif ($filter === 'no_description') {
    $where_conditions[] = "(description IS NULL OR description = '')";
} elseif ($filter === 'no_text') {
    $where_conditions[] = "(extracted_text IS NULL OR extracted_text = '')";
} elseif ($filter === 'no_summary') {
    $where_conditions[] = "(text_summary IS NULL OR text_summary = '')";
} elseif ($filter === 'incomplete') {
    $where_conditions[] = "(title IS NULL OR title = '' OR description IS NULL OR description = '')";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$sql = "
    SELECT a.*, l.letter_number, l.subject 
    FROM letter_attachments a
    JOIN letters l ON a.letter_id = l.id
    $where_clause
    ORDER BY a.uploaded_at DESC
    LIMIT 100
";

$stmt = $conn->query($sql);
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN title IS NULL OR title = '' THEN 1 ELSE 0 END) as no_title,
        SUM(CASE WHEN description IS NULL OR description = '' THEN 1 ELSE 0 END) as no_description,
        SUM(CASE WHEN extracted_text IS NULL OR extracted_text = '' THEN 1 ELSE 0 END) as no_text,
        SUM(CASE WHEN text_summary IS NULL OR text_summary = '' THEN 1 ELSE 0 END) as no_summary,
        SUM(CASE WHEN (title IS NULL OR title = '') AND (description IS NULL OR description = '') THEN 1 ELSE 0 END) as incomplete
    FROM letter_attachments
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = "ویرایش گروهی پیوست‌ها - پروژه دانشگاه خاتم پردیس";

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
    <title>ویرایش گروهی پیوست‌ها</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f5f5;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-item {
            text-align: center;
            padding: 15px;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        .attachment-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .attachment-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .attachment-card.selected {
            border: 2px solid #4CAF50;
            background: #f1f8f4;
        }
        .file-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .missing-badge {
            background: #ff9800;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.75em;
            margin-left: 5px;
        }
        .filter-pills {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .filter-pill {
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .filter-pill.active {
            background: #007bff;
            color: white;
        }
        .sticky-actions {
            position: sticky;
            bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 100;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-edit"></i> 
                ویرایش گروهی پیوست‌ها
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

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-card">
            <div class="row">
                <div class="col-md-2 stat-item">
                    <div class="stat-number"><?= number_format($stats['total']) ?></div>
                    <div class="stat-label">کل پیوست‌ها</div>
                </div>
                <div class="col-md-2 stat-item">
                    <div class="stat-number"><?= number_format($stats['incomplete']) ?></div>
                    <div class="stat-label">ناقص</div>
                </div>
                <div class="col-md-2 stat-item">
                    <div class="stat-number"><?= number_format($stats['no_title']) ?></div>
                    <div class="stat-label">بدون عنوان</div>
                </div>
                <div class="col-md-2 stat-item">
                    <div class="stat-number"><?= number_format($stats['no_description']) ?></div>
                    <div class="stat-label">بدون توضیحات</div>
                </div>
                <div class="col-md-2 stat-item">
                    <div class="stat-number"><?= number_format($stats['no_summary']) ?></div>
                    <div class="stat-label">بدون خلاصه</div>
                </div>
                <div class="col-md-2 stat-item">
                    <div class="stat-number"><?= number_format($stats['no_text']) ?></div>
                    <div class="stat-label">بدون متن</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-pills">
            <a href="?filter=all" class="filter-pill <?= $filter === 'all' ? 'active' : 'btn btn-outline-primary' ?>">
                <i class="fas fa-list"></i> همه (<?= count($attachments) ?>)
            </a>
            <a href="?filter=incomplete" class="filter-pill <?= $filter === 'incomplete' ? 'active' : 'btn btn-outline-warning' ?>">
                <i class="fas fa-exclamation-triangle"></i> ناقص (<?= $stats['incomplete'] ?>)
            </a>
            <a href="?filter=no_title" class="filter-pill <?= $filter === 'no_title' ? 'active' : 'btn btn-outline-secondary' ?>">
                بدون عنوان (<?= $stats['no_title'] ?>)
            </a>
            <a href="?filter=no_description" class="filter-pill <?= $filter === 'no_description' ? 'active' : 'btn btn-outline-secondary' ?>">
                بدون توضیحات (<?= $stats['no_description'] ?>)
            </a>
            <a href="?filter=no_summary" class="filter-pill <?= $filter === 'no_summary' ? 'active' : 'btn btn-outline-secondary' ?>">
                بدون خلاصه (<?= $stats['no_summary'] ?>)
            </a>
        </div>

        <?php if (empty($attachments)): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-check-circle fa-3x mb-3"></i>
                <h4>پیوستی با این فیلتر یافت نشد!</h4>
                <p>همه پیوست‌ها دارای اطلاعات کامل هستند.</p>
            </div>
        <?php else: ?>
            <form method="POST" id="bulkForm">
                <div class="mb-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                        <i class="fas fa-check-double"></i> انتخاب همه
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">
                        <i class="fas fa-times"></i> لغو انتخاب
                    </button>
                    <span class="ms-3 text-muted">
                        <span id="selected-count">0</span> مورد انتخاب شده
                    </span>
                </div>

                <?php foreach ($attachments as $att): 
                    $missingFields = [];
                    if (empty($att['title'])) $missingFields[] = 'عنوان';
                    if (empty($att['description'])) $missingFields[] = 'توضیحات';
                    if (empty($att['text_summary'])) $missingFields[] = 'خلاصه';
                    if (empty($att['extracted_text'])) $missingFields[] = 'متن';
                ?>
                <div class="attachment-card" id="card-<?= $att['id'] ?>">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="form-check">
                            <input class="form-check-input select-item" 
                                   type="checkbox" 
                                   name="attachments[<?= $att['id'] ?>][update]" 
                                   value="1"
                                   id="check-<?= $att['id'] ?>"
                                   onchange="updateSelection()">
                            <label class="form-check-label fw-bold" for="check-<?= $att['id'] ?>">
                                <i class="fas fa-file"></i>
                                <?= htmlspecialchars($att['original_filename']) ?>
                            </label>
                        </div>
                        <div>
                            <?php foreach ($missingFields as $field): ?>
                                <span class="missing-badge"><?= $field ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="file-info">
                        <small class="text-muted">
                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($att['letter_number']) ?> - 
                            <?= htmlspecialchars(mb_substr($att['subject'], 0, 50)) ?>...
                            | <i class="fas fa-hdd"></i> <?= number_format($att['file_size'] / 1024, 2) ?> KB
                            | <i class="fas fa-clock"></i> <?= date('Y/m/d', strtotime($att['uploaded_at'])) ?>
                        </small>
                        <div class="mt-2">
                            <a href="<?= htmlspecialchars($att['file_path']) ?>" class="btn btn-xs btn-outline-primary" target="_blank">
                                <i class="fas fa-eye"></i> مشاهده
                            </a>
                            <a href="edit_attachment.php?id=<?= $att['id'] ?>" class="btn btn-xs btn-outline-warning">
                                <i class="fas fa-edit"></i> ویرایش تکی
                            </a>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label small">عنوان:</label>
                            <input type="text" 
                                   name="attachments[<?= $att['id'] ?>][title]" 
                                   class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($att['title'] ?? '') ?>"
                                   placeholder="عنوان پیوست...">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label small">نوع:</label>
                            <select name="attachments[<?= $att['id'] ?>][attachment_type]" class="form-select form-select-sm">
                                <option value="supplement" <?= ($att['attachment_type'] ?? '') == 'supplement' ? 'selected' : '' ?>>مکمل</option>
                                <option value="plan" <?= ($att['attachment_type'] ?? '') == 'plan' ? 'selected' : '' ?>>نقشه</option>
                                <option value="report" <?= ($att['attachment_type'] ?? '') == 'report' ? 'selected' : '' ?>>گزارش</option>
                                <option value="contract" <?= ($att['attachment_type'] ?? '') == 'contract' ? 'selected' : '' ?>>قرارداد</option>
                                <option value="other" <?= ($att['attachment_type'] ?? '') == 'other' ? 'selected' : '' ?>>سایر</option>
                            </select>
                        </div>
                        <div class="col-12 mb-2">
                            <label class="form-label small">توضیحات:</label>
                            <textarea name="attachments[<?= $att['id'] ?>][description]" 
                                      class="form-control form-control-sm" 
                                      rows="2"
                                      placeholder="توضیحات..."><?= htmlspecialchars($att['description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">خلاصه:</label>
                            <textarea name="attachments[<?= $att['id'] ?>][text_summary]" 
                                      class="form-control form-control-sm" 
                                      rows="2"
                                      placeholder="خلاصه محتوا..."><?= htmlspecialchars($att['text_summary'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="sticky-actions">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong id="save-count">0</strong> پیوست برای ذخیره انتخاب شده است
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary" onclick="deselectAll()">
                                <i class="fas fa-times"></i> انصراف
                            </button>
                            <button type="submit" name="bulk_update" class="btn btn-success">
                                <i class="fas fa-save"></i> ذخیره تغییرات
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectAll() {
            document.querySelectorAll('.select-item').forEach(cb => cb.checked = true);
            updateSelection();
        }
        
        function deselectAll() {
            document.querySelectorAll('.select-item').forEach(cb => cb.checked = false);
            updateSelection();
        }
        
        function updateSelection() {
            const checked = document.querySelectorAll('.select-item:checked').length;
            document.getElementById('selected-count').textContent = checked;
            document.getElementById('save-count').textContent = checked;
            
            // Highlight selected cards
            document.querySelectorAll('.attachment-card').forEach(card => {
                const id = card.id.replace('card-', '');
                const checkbox = document.getElementById('check-' + id);
                if (checkbox && checkbox.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
        }
        
        // Warn before leaving
        window.addEventListener('beforeunload', (e) => {
            const checked = document.querySelectorAll('.select-item:checked').length;
            if (checked > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        document.getElementById('bulkForm').addEventListener('submit', () => {
            window.removeEventListener('beforeunload', () => {});
        });
        
        // Initial count
        updateSelection();
    </script>
    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>