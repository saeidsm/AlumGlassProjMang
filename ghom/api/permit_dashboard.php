<?php
// ghom/permit_dashboard.php
require_once __DIR__ . '/../../../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php'; // Ensure jdf.php is here for Persian dates

secureSession();
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$pageTitle = "کارتابل مجوزها";
require_once __DIR__ . '/header.php'; // Add Header

$pdo = getProjectDBConnection('ghom');

// Fetch Permits
$sql = "
    SELECT p.*, 
    (SELECT COUNT(*) FROM permit_elements pe WHERE pe.permit_id = p.id) as el_count,
    (SELECT e.plan_file FROM elements e JOIN permit_elements pe ON e.element_id = pe.element_id WHERE pe.permit_id = p.id LIMIT 1) as plan_file
    FROM permits p 
    ORDER BY p.created_at DESC
";
$permits = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Fetch User Names
$userIds = array_unique(array_column($permits, 'user_id'));
$usersMap = [];
if (!empty($userIds)) {
    $commonPdo = getCommonDBConnection();
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmtUsers = $commonPdo->prepare("SELECT id, first_name, last_name FROM users WHERE id IN ($placeholders)");
    $stmtUsers->execute(array_values($userIds));
    foreach ($stmtUsers->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $usersMap[$u['id']] = trim($u['first_name'] . ' ' . $u['last_name']);
    }
}
?>

<div class="container-fluid mt-4" style="max-width: 1400px;">
    <div class="card shadow">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">📋 لیست مجوزهای کار (Permits)</h4>
            <a href="/ghom/contractor_batch_update.php" class="btn btn-light btn-sm text-primary fw-bold">بازگشت به نقشه</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>ثبت کننده</th>
                            <th>منطقه / بلوک</th>
                            <th>تعداد</th>
                            <th>وضعیت</th>
                            <th>تاریخ ثبت</th>
                            <th>یادداشت مشاور</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($permits as $p): 
                            $badge = match($p['status']) {
                                'Approved' => 'success',
                                'Rejected' => 'danger',
                                'Pending' => 'warning',
                                'WaitingUpload' => 'secondary',
                                default => 'light'
                            };
                            $statusLabel = match($p['status']) {
                                'Approved' => 'تایید شده',
                                'Rejected' => 'رد شده',
                                'Pending' => 'در انتظار بررسی',
                                'WaitingUpload' => 'منتظر آپلود فرم',
                                default => $p['status']
                            };
                            
                            // Persian Date Conversion
                            $timestamp = strtotime($p['created_at']);
                            $persianDate = jdate('Y/m/d H:i', $timestamp);
                            
                            $creatorName = $usersMap[$p['user_id']] ?? 'Unknown';
                            $plan = basename($p['plan_file'] ?? '');
                        ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            <td><?= htmlspecialchars($creatorName) ?></td>
                            <td><?= htmlspecialchars($p['zone'] . ' - ' . $p['block']) ?></td>
                            <td><?= $p['el_count'] ?></td>
                            <td><span class="badge bg-<?= $badge ?>"><?= $statusLabel ?></span></td>
                            <td style="direction: ltr;"><?= $persianDate ?></td>
                            <td class="text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($p['admin_notes'] ?? '') ?>">
                                <?= htmlspecialchars($p['admin_notes'] ?? '') ?>
                            </td>
                            <td>
                                <?php if($p['status'] === 'WaitingUpload'): ?>
                                    <button onclick="openUploadModal(<?= $p['id'] ?>)" class="btn btn-sm btn-success">
                                        📤 آپلود فرم امضا شده
                                    </button>
                                    <a href="/ghom/print_permit.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        🖨️ چاپ مجدد
                                    </a>
                                <?php else: ?>
                                    <?php if(!empty($p['file_path'])): ?>
                                     <a href="/ghom/contractor_batch_update.php?plan=<?= $plan ?>&permit_id=<?= $p['id'] ?>" 
           class="btn btn-sm btn-primary" 
           target="_blank">
           📝 بازبینی و تکمیل فرم
        </a>
                                    <?php endif; ?>
                                    <a href="/ghom/contractor_batch_update.php?plan=<?= $plan ?>&permit_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        🔍 نقشه
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="bg-white p-4 rounded shadow" style="width:400px; position:relative;">
        <h5 class="mb-3">آپلود فرم امضا شده</h5>
        <form id="uploadForm" onsubmit="submitUpload(event)">
            <input type="hidden" name="permit_id" id="upload_permit_id">
            <div class="mb-3">
                <input type="file" name="signed_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">ارسال نهایی</button>
            <button type="button" onclick="closeUploadModal()" class="btn btn-secondary w-100 mt-2">انصراف</button>
        </form>
    </div>
</div>

<script>
function openUploadModal(id) {
    document.getElementById('upload_permit_id').value = id;
    document.getElementById('uploadModal').style.display = 'flex';
}
function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
}
async function submitUpload(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true; btn.innerText = "در حال ارسال...";

    try {
        const res = await fetch('/ghom/api/upload_signed_permit.php', { method: 'POST', body: formData });
        const data = await res.json();
        if(data.status === 'success') {
            alert('فایل با موفقیت ارسال شد.');
            location.reload();
        } else {
            alert('خطا: ' + data.message);
        }
    } catch(err) {
        alert('خطا در ارتباط با سرور');
    } finally {
        btn.disabled = false; btn.innerText = "ارسال نهایی";
    }
}
</script>

<?php require_once 'footer.php'; ?>