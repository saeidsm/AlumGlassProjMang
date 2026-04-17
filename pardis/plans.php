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

class PersianDate {
    public static function format($date) {
        if (!$date || $date === '0000-00-00') return '---';
        return jdate('Y/m/d', strtotime($date));
    }
}

$conn = getLetterTrackingDBConnection();

// AJAX Request Handler
if (isset($_GET['action']) && $_GET['action'] === 'get_plan_history') {
    header('Content-Type: application/json');
    $family_id = $_GET['family_id'] ?? 0;

    if (!$family_id) {
        echo json_encode(['error' => 'Invalid Plan Family ID']);
        exit;
    }

    try {
        $sql = "SELECT pr.*, la.original_filename, l.letter_number FROM plan_revisions pr
                LEFT JOIN letter_attachments la ON pr.attachment_id = la.id
                LEFT JOIN letters l ON pr.letter_id = l.id
                WHERE pr.family_id = ? ORDER BY pr.revision_number DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$family_id]);
        $revisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $revision_ids = array_column($revisions, 'id');
        $change_logs = [];
        if (!empty($revision_ids)) {
            $placeholders = implode(',', array_fill(0, count($revision_ids), '?'));
            $log_sql = "SELECT * FROM plan_change_log WHERE revision_id IN ($placeholders) ORDER BY detected_at DESC";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->execute($revision_ids);
            
            while ($log = $log_stmt->fetch(PDO::FETCH_ASSOC)) {
                $change_logs[$log['revision_id']][] = $log;
            }
        }

        foreach ($revisions as &$revision) {
            $revision['changes'] = $change_logs[$revision['id']] ?? [];
        }

        echo json_encode(['revisions' => $revisions]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Sorting parameters
$sort_column = $_GET['sort'] ?? 'la.original_filename';
$sort_direction = $_GET['dir'] ?? 'ASC';

$allowed_columns = [
    'la.original_filename' => 'شناسه نقشه',
    'l.subject' => 'عنوان',
    'latest_rev.revision_number' => 'آخرین ویرایش',
    'latest_rev.revision_date' => 'تاریخ ویرایش',
    'latest_rev.status' => 'وضعیت',
    'l.letter_number' => 'نامه مرتبط'
];

if (!array_key_exists($sort_column, $allowed_columns)) {
    $sort_column = 'la.original_filename';
}

$sort_direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';

// Build query
$where = ['latest_rev.is_latest = 1'];
$params = [];

if (!empty($_GET['search'])) {
    $searchTerm = "%{$_GET['search']}%";
    $where[] = "(la.original_filename LIKE ? OR l.subject LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($_GET['status'])) {
    $where[] = "latest_rev.status = ?";
    $params[] = $_GET['status'];
}

$where_clause = 'WHERE ' . implode(' AND ', $where);

$sql = "SELECT 
            latest_rev.family_id,
            la.original_filename AS plan_identifier,
            l.subject AS plan_title,
            latest_rev.id AS latest_revision_id,
            latest_rev.revision_number,
            latest_rev.revision_date,
            latest_rev.status,
            latest_rev.file_path,
            l.letter_number,
            l.id AS letter_id,
            (SELECT COUNT(*) FROM plan_revisions pr_count WHERE pr_count.family_id = latest_rev.family_id) as revision_count
        FROM plan_revisions AS latest_rev
        LEFT JOIN letter_attachments la ON latest_rev.attachment_id = la.id
        LEFT JOIN letters l ON latest_rev.letter_id = l.id
        $where_clause
        ORDER BY $sort_column $sort_direction";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$plan_families = $stmt->fetchAll(PDO::FETCH_ASSOC);

function canViewInline($file_path) {
    if (empty($file_path)) return false;
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    if ($extension === 'pdf') return 'pdf';
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg'])) return 'image';
    return false;
}

$pageTitle = "مدیریت نقشه‌ها - پروژه دانشگاه خاتم پردیس";

if (function_exists('isMobileDevices') && isMobileDevices()) {
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
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Tahoma, Arial, sans-serif; background-color: #f4f7f6; }
        .table-hover tbody tr { cursor: pointer; }
        .card { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .filter-section { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .timeline { list-style: none; padding: 0; position: relative; }
        .timeline:before { content: ''; position: absolute; top: 0; bottom: 0; width: 3px; background: #e9ecef; right: 20px; }
        .timeline-item { margin-bottom: 20px; position: relative; padding-right: 50px; }
        .timeline-icon { position: absolute; right: 0; top: 0; width: 42px; height: 42px; border-radius: 50%; background: #3498db; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2em; border: 3px solid #e9ecef; }
        .timeline-item.superseded .timeline-icon { background: #6c757d; }
        .timeline-content { background: #f8f9fa; border-radius: 5px; padding: 15px; }
        .change-log-list { font-size: 0.9em; }
        .file-viewer-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 1060; }
        .file-viewer-content { position: relative; width: 90%; height: 90%; margin: 3% auto; background: white; border-radius: 10px; overflow: hidden; }
        .file-viewer-header { background: #343a40; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center; }
        .file-viewer-body { height: calc(100% - 60px); overflow: auto; }
        .file-viewer-close { background: #dc3545; border: none; color: white; padding: 8px 15px; border-radius: 5px; cursor: pointer; }
        #pdfViewer, #imageViewer img { width: 100%; height: 100%; border: none; max-width: 100%; max-height: 100%; object-fit: contain; }
        .sortable { cursor: pointer; user-select: none; }
        .sortable:hover { background-color: #e9ecef; }
        .sort-icon { font-size: 0.8em; margin-right: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <h2 class="mb-4"><i class="fas fa-drafting-compass"></i> مدیریت نقشه‌ها</h2>
        <a href="letters.php" class="btn btn-secondary mb-3">
            <i class="fas fa-arrow-right"></i> بازگشت
        </a>
        
        <div class="filter-section card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="search" class="form-label">جستجو در نام فایل یا موضوع نامه</label>
                    <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">وضعیت آخرین ویرایش</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">همه</option>
                        <option value="active" <?= ($_GET['status'] ?? '') == 'active' ? 'selected' : '' ?>>فعال (Active)</option>
                        <option value="superseded" <?= ($_GET['status'] ?? '') == 'superseded' ? 'selected' : '' ?>>جایگزین شده (Superseded)</option>
                        <option value="obsolete" <?= ($_GET['status'] ?? '') == 'obsolete' ? 'selected' : '' ?>>منسوخ (Obsolete)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> فیلتر</button>
                    <a href="plans.php" class="btn btn-outline-secondary w-100 mt-2"><i class="fas fa-redo"></i> پاک کردن فیلتر</a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>لیست آخرین ویرایش نقشه‌ها (<?= count($plan_families) ?> مورد)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="sortable" onclick="sortTable('la.original_filename')">
                                    <?php if ($sort_column == 'la.original_filename'): ?>
                                        <i class="fas fa-sort-<?= $sort_direction == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                    شناسه نقشه (نام فایل)
                                </th>
                                <th class="sortable" onclick="sortTable('l.subject')">
                                    <?php if ($sort_column == 'l.subject'): ?>
                                        <i class="fas fa-sort-<?= $sort_direction == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                    عنوان (موضوع نامه)
                                </th>
                                <th class="sortable" onclick="sortTable('latest_rev.revision_number')">
                                    <?php if ($sort_column == 'latest_rev.revision_number'): ?>
                                        <i class="fas fa-sort-<?= $sort_direction == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                    آخرین ویرایش
                                </th>
                                <th class="sortable" onclick="sortTable('latest_rev.revision_date')">
                                    <?php if ($sort_column == 'latest_rev.revision_date'): ?>
                                        <i class="fas fa-sort-<?= $sort_direction == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                    تاریخ ویرایش
                                </th>
                                <th class="sortable" onclick="sortTable('latest_rev.status')">
                                    <?php if ($sort_column == 'latest_rev.status'): ?>
                                        <i class="fas fa-sort-<?= $sort_direction == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                    وضعیت
                                </th>
                                <th class="sortable" onclick="sortTable('l.letter_number')">
                                    <?php if ($sort_column == 'l.letter_number'): ?>
                                        <i class="fas fa-sort-<?= $sort_direction == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                    نامه مرتبط
                                </th>
                                <th>تعداد ویرایش‌ها</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($plan_families) > 0): ?>
                                <?php foreach ($plan_families as $family): ?>
                                    <tr onclick="showHistory(<?= $family['family_id'] ?>, '<?= htmlspecialchars(addslashes($family['plan_identifier'])) ?>')">
                                        <td><strong><?= htmlspecialchars($family['plan_identifier']) ?></strong></td>
                                        <td><?= htmlspecialchars($family['plan_title']) ?></td>
                                        <td>Rev. <?= htmlspecialchars($family['revision_number'] ?? 'N/A') ?></td>
                                        <td><?= PersianDate::format($family['revision_date']) ?></td>
                                        <td>
                                            <?php
                                            $status = $family['status'] ?? 'unknown';
                                            $statusColors = ['active' => 'success', 'superseded' => 'secondary', 'obsolete' => 'danger'];
                                            $statusNames = ['active' => 'فعال', 'superseded' => 'جایگزین شده', 'obsolete' => 'منسوخ'];
                                            ?>
                                            <span class="badge bg-<?= $statusColors[$status] ?? 'dark' ?>">
                                                <?= $statusNames[$status] ?? 'نامشخص' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($family['letter_id']): ?>
                                                <a href="view_letter.php?id=<?= $family['letter_id'] ?>" onclick="event.stopPropagation();">
                                                    <?= htmlspecialchars($family['letter_number']) ?>
                                                </a>
                                            <?php else: echo '---'; endif; ?>
                                        </td>
                                        <td><span class="badge bg-info"><?= $family['revision_count'] ?></span></td>
                                        <td onclick="event.stopPropagation();">
                                            <button class="btn btn-sm btn-outline-primary" onclick="showHistory(<?= $family['family_id'] ?>, '<?= htmlspecialchars(addslashes($family['plan_identifier'])) ?>');">
                                                <i class="fas fa-history"></i> تاریخچه
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center p-4">هیچ نقشه‌ای با این فیلترها یافت نشد.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalTitle">تاریخچه نقشه</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="historyModalBody">
                    <div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">در حال بارگذاری...</span></div></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- File Viewer Modal -->
    <div id="fileViewerModal" class="file-viewer-modal">
        <div class="file-viewer-content">
            <div class="file-viewer-header">
                <h5 id="viewerTitle"></h5>
                <button class="file-viewer-close" onclick="closeFileViewer()"><i class="fas fa-times"></i></button>
            </div>
            <div class="file-viewer-body">
                <iframe id="pdfViewer" style="display:none;"></iframe>
                <div id="imageViewer" style="display:none;"><img /></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const historyModal = new bootstrap.Modal(document.getElementById('historyModal'));

        // Sorting function
        function sortTable(column) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort');
            const currentDir = urlParams.get('dir') || 'ASC';
            
            if (currentSort === column) {
                urlParams.set('dir', currentDir === 'ASC' ? 'DESC' : 'ASC');
            } else {
                urlParams.set('sort', column);
                urlParams.set('dir', 'ASC');
            }
            
            window.location.search = urlParams.toString();
        }

        function showHistory(familyId, planIdentifier) {
            const modalTitle = document.getElementById('historyModalTitle');
            const modalBody = document.getElementById('historyModalBody');
            
            modalTitle.innerText = `تاریخچه نقشه: ${planIdentifier}`;
            modalBody.innerHTML = `<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>`;
            historyModal.show();

            fetch(`?action=get_plan_history&family_id=${familyId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    let html = '<ul class="timeline">';
                    data.revisions.forEach(rev => {
                        const statusClass = rev.status === 'superseded' ? 'superseded' : '';
                        const icon = rev.is_latest == 1 ? 'fa-star' : 'fa-file-alt';

                        let changesHtml = '<p><em>بدون تغییر ثبت شده.</em></p>';
                        if (rev.changes && rev.changes.length > 0) {
                            changesHtml = '<ul class="list-group list-group-flush change-log-list">';
                            rev.changes.forEach(change => {
                                changesHtml += `<li class="list-group-item"><strong>${change.change_type}:</strong> ${change.change_details || ''}</li>`;
                            });
                            changesHtml += '</ul>';
                        }
                        
                        const viewBtnHtml = canViewInline(rev.file_path) ?
                            `<button class="btn btn-sm btn-info me-2" onclick="openFileViewer('${rev.file_path}', '${rev.original_filename}')"><i class="fas fa-eye"></i> مشاهده</button>` : '';

                        const letterLinkHtml = rev.letter_id ? 
                            `<a href="view_letter.php?id=${rev.letter_id}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary ms-2"><i class="fas fa-envelope"></i> مشاهده نامه (${rev.letter_number})</a>` : '';

                        html += `
                            <li class="timeline-item ${statusClass}">
                                <div class="timeline-icon"><i class="fas ${icon}"></i></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="mb-0">ویرایش ${rev.revision_number}</h5>
                                        <span class="badge bg-${rev.status === 'active' ? 'success' : 'secondary'}">${rev.status}</span>
                                    </div>
                                    <small class="text-muted">تاریخ: ${rev.revision_date ? new Date(rev.revision_date).toLocaleDateString('fa-IR') : 'N/A'}</small>
                                    <p class="mt-2"><strong>فایل:</strong> ${rev.original_filename || 'N/A'}</p>
                                    <p><strong>توضیحات:</strong> ${rev.change_description || '---'}</p>
                                    <div class="mb-3">
                                        ${viewBtnHtml}
                                        <a href="${rev.file_path}" class="btn btn-sm btn-primary" download><i class="fas fa-download"></i> دانلود فایل</a>
                                        ${letterLinkHtml}
                                    </div>
                                    <h6><i class="fas fa-clipboard-list"></i> لاگ تغییرات شناسایی شده:</h6>
                                    ${changesHtml}
                                </div>
                            </li>`;
                    });
                    html += '</ul>';
                    modalBody.innerHTML = html;
                })
                .catch(err => {
                    modalBody.innerHTML = `<div class="alert alert-danger">خطا در بارگذاری اطلاعات: ${err.message}</div>`;
                });
        }
        
        function canViewInline(filePath) { 
            if (!filePath) return false; 
            const extension = filePath.split('.').pop().toLowerCase(); 
            return ['pdf', 'jpg', 'jpeg', 'png', 'gif'].includes(extension); 
        }
        
        function openFileViewer(filePath, filename) { 
            const modal = document.getElementById('fileViewerModal'); 
            const title = document.getElementById('viewerTitle'); 
            const pdfViewer = document.getElementById('pdfViewer'); 
            const imageViewer = document.getElementById('imageViewer'); 
            const imageContent = imageViewer.querySelector('img'); 
            pdfViewer.style.display = 'none'; 
            imageViewer.style.display = 'none'; 
            title.textContent = filename; 
            const extension = filePath.split('.').pop().toLowerCase(); 
            if (extension === 'pdf') { 
                pdfViewer.src = filePath; 
                pdfViewer.style.display = 'block'; 
            } else { 
                imageContent.src = filePath; 
                imageViewer.style.display = 'block'; 
            } 
            modal.style.display = 'block'; 
        }
        
        function closeFileViewer() { 
            document.getElementById('fileViewerModal').style.display = 'none'; 
            document.getElementById('pdfViewer').src = ''; 
            document.getElementById('imageViewer').querySelector('img').src = ''; 
        }
        
        document.addEventListener('keydown', e => e.key === 'Escape' && closeFileViewer());
    </script>
    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>