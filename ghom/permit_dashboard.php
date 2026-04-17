<?php
// ghom/permit_dashboard.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();
if (!isLoggedIn()) { header('Location: /login.php'); exit; }

$pageTitle = "کارتابل مجوزها";
require_once __DIR__ . '/header_ghom.php';

$pdo = getProjectDBConnection('ghom');

// Fetch Permits + Contractor Name
$sql = "
    SELECT p.*, 
    (SELECT COUNT(*) FROM permit_elements pe WHERE pe.permit_id = p.id) as el_count,
    (SELECT e.plan_file FROM elements e JOIN permit_elements pe ON e.element_id = pe.element_id WHERE pe.permit_id = p.id LIMIT 1) as plan_file
    FROM permits p 
    ORDER BY p.created_at DESC
";
$permits = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Fetch User Names (Fallback if company not selected yet)
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
            <div class="d-flex align-items-center">
                <h4 class="mb-0">📋 لیست مجوزهای کار (Permits)</h4>
                
                <!-- 1. SETTINGS BUTTON (SUPERUSER ONLY) -->
                <?php if($_SESSION['role'] === 'superuser'): ?>
                    <button onclick="openSettingsModal()" class="btn btn-warning btn-sm text-dark fw-bold ms-3" style="border-radius: 20px;">
                        ⚙️ تنظیمات قرارداد
                    </button>
                <?php endif; ?>
            </div>
            
            <a href="/ghom/contractor_batch_update.php" class="btn btn-light btn-sm text-primary fw-bold">بازگشت به نقشه</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>شرکت پیمانکار</th> <!-- Changed Header -->
                            <th>کاربر ثبت کننده</th> <!-- Optional: Keep user info -->
                            <th>منطقه / بلوک</th>
                            <th>تعداد</th>
                            <th>وضعیت</th>
                            <th>تاریخ ثبت</th>
                            <th>یادداشت مشاور</th>
                            <th>عملیات</th>
                             <th>اسناد نهایی</th> 
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
                            
                            $persianDate = jdate('Y/m/d H:i', strtotime($p['created_at']));
                            $userName = $usersMap[$p['user_id']] ?? 'Unknown';
                            
                            // LOGIC: Show saved company name, or fallback to "Not Selected"
                            $companyName = !empty($p['contractor_name']) ? $p['contractor_name'] : '<span class="text-muted small">(تعیین نشده)</span>';
                            
                            $plan = basename($p['plan_file'] ?? '');
                             $badge = match($p['status']) { 'Approved'=>'success', 'Rejected'=>'danger', 'Pending'=>'warning', default=>'secondary' };
                             $statusLabel = match($p['status']) { 'Approved'=>'تایید شده', 'Rejected'=>'رد شده', 'Pending'=>'در انتظار', default=>'منتظر آپلود' };
                             $pDate = jdate('Y/m/d', strtotime($p['created_at']));
                             $plan = basename($p['plan_file'] ?? '');
                             $company = $p['contractor_name'] ?? '-';
                        
                        ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            <td class="fw-bold text-primary"><?= $companyName ?></td> <!-- New Column -->
                            <td class="small text-muted"><?= htmlspecialchars($userName) ?></td>
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
                                        📤 آپلود فرم
                                    </button>
                                    <a href="/ghom/print_permit.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        🖨️ چاپ
                                    </a>
                                <?php else: ?>
                                    <?php if(!empty($p['file_path'])): ?>
                                        <a href="<?= htmlspecialchars($p['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-info">📄 فایل</a>
                                    <?php endif; ?>
                                    <a href="/ghom/contractor_batch_update.php?plan=<?= $plan ?>&permit_id=<?= $p['id'] ?>" 
                                       class="btn btn-sm btn-primary" target="_blank">
                                       📝 بازبینی فنی
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button onclick="openChecklistFilesModal(<?= $p['id'] ?>)" class="btn btn-sm btn-outline-dark" title="فایل‌های چک‌لیست نهایی">
                                    📂 اسناد (<span id="count-<?= $p['id'] ?>">...</span>)
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

<!-- Upload Modal (Updated to include Contractor Select if missing) -->
<div id="uploadModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="bg-white p-4 rounded shadow" style="width:400px; position:relative;">
        <h5 class="mb-3">آپلود فرم امضا شده</h5>
        <form id="uploadForm" onsubmit="submitUpload(event)">
            <input type="hidden" name="permit_id" id="upload_permit_id">
            
            <div class="mb-3">
                <label class="form-label">نام شرکت پیمانکار:</label>
                <select name="contractor" class="form-select" required>
                    <option value="">-- انتخاب کنید --</option>
                    <option value="شرکت رس">شرکت رس</option>
                    <option value="شرکت عمران آذرستان">شرکت عمران آذرستان</option>
                    <option value="شرکت آتیه نما">شرکت آتیه نما</option>
                    <option value="شرکت آرانسج">شرکت آرانسج</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">فایل اسکن شده:</label>
                <input type="file" name="signed_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">ارسال نهایی</button>
            <button type="button" onclick="closeUploadModal()" class="btn btn-secondary w-100 mt-2">انصراف</button>
        </form>
    </div>
</div>
<div id="settingsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="bg-white p-4 rounded shadow" style="width:500px; direction:rtl; text-align:right;">
        <h5 class="mb-3 border-bottom pb-2">⚙️ تنظیمات شماره قراردادها</h5>
        <form id="settingsForm">
            <div class="mb-3">
                <label>شرکت رس (Ros):</label>
                <input type="text" name="contract_ros" id="contract_ros" class="form-control">
            </div>
            <div class="mb-3">
                <label>شرکت عمران آذرستان:</label>
                <input type="text" name="contract_omran" id="contract_omran" class="form-control">
            </div>
            <div class="mb-3">
                <label>شرکت آتیه نما:</label>
                <input type="text" name="contract_atieh" id="contract_atieh" class="form-control">
            </div>
             <div class="mb-3">
                <label>شرکت آرانسج:</label>
                <input type="text" name="contract_aransaj" id="contract_aransaj" class="form-control">
            </div>
            <button type="button" onclick="saveSettings()" class="btn btn-primary w-100">ذخیره</button>
            <button type="button" onclick="document.getElementById('settingsModal').style.display='none'" class="btn btn-secondary w-100 mt-2">بستن</button>
        </form>
    </div>
</div>

<div id="checklistFilesModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="bg-white p-4 rounded shadow" style="width:600px; max-height:80vh; overflow-y:auto; direction:rtl; text-align:right;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5>📂 اسناد و چک‌لیست‌های نهایی (مجوز <span id="modalPermitId"></span>)</h5>
            <button onclick="document.getElementById('checklistFilesModal').style.display='none'" class="btn-close"></button>
        </div>

        <!-- Upload Form -->
        <div class="p-3 bg-light border rounded mb-3">
            <label class="form-label fw-bold">افزودن فایل جدید (PDF/عکس):</label>
            <div class="input-group">
                <input type="file" id="finalChecklistFiles" class="form-control" multiple accept=".pdf,.jpg,.png,.jpeg">
                <button onclick="uploadChecklistFiles()" class="btn btn-success">آپلود</button>
            </div>
            <div class="form-text">می‌توانید چندین فایل را همزمان انتخاب کنید.</div>
        </div>

        <!-- List -->
        <div id="filesListContainer">
            <div class="text-center text-muted">در حال بارگذاری...</div>
        </div>
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

async function openSettingsModal() {
    document.getElementById('settingsModal').style.display = 'flex';
    try {
        const res = await fetch('/ghom/api/get_settings.php');
        const data = await res.json();
        if(data) {
            ['ros', 'omran', 'atieh', 'aransaj'].forEach(key => {
                const el = document.getElementById('contract_'+key);
                if(el) el.value = data['contract_'+key] || '';
            });
        }
    } catch(e) {}
}

async function saveSettings() {
    const formData = new FormData(document.getElementById('settingsForm'));
    const res = await fetch('/ghom/api/save_settings.php', { method: 'POST', body: formData });
    const data = await res.json();
    if(data.status === 'success') {
        alert('تنظیمات ذخیره شد.');
        document.getElementById('settingsModal').style.display = 'none';
    } else {
        alert('خطا: ' + data.message);
    }
}

// --- Checklist Files Logic ---
let currentPermitId = 0;

function openChecklistFilesModal(id) {
    currentPermitId = id;
    document.getElementById('modalPermitId').textContent = id;
    document.getElementById('checklistFilesModal').style.display = 'flex';
    loadChecklistFiles(id);
}

async function loadChecklistFiles(id) {
    const container = document.getElementById('filesListContainer');
    container.innerHTML = 'loading...';
    
    try {
        const res = await fetch(`/ghom/api/get_final_checklists.php?permit_id=${id}`);
        const files = await res.json();
        
        // Update count on dashboard button
        const countSpan = document.getElementById(`count-${id}`);
        if(countSpan) countSpan.textContent = files.length;

        if(files.length === 0) {
            container.innerHTML = '<div class="alert alert-info">هیچ فایلی آپلود نشده است.</div>';
            return;
        }

        let html = '<table class="table table-striped table-sm"><thead><tr><th>نام فایل</th><th>تاریخ</th><th>دانلود</th></tr></thead><tbody>';
        files.forEach(f => {
            html += `
                <tr>
                    <td>${f.file_name}</td>
                    <td style="direction:ltr; font-size:12px;">${f.date_persian}</td>
                    <td><a href="${f.file_path}" target="_blank" class="btn btn-sm btn-primary">⬇</a></td>
                </tr>
            `;
        });
        html += '</tbody></table>';
        container.innerHTML = html;

    } catch(e) {
        container.innerHTML = 'خطا در دریافت لیست.';
    }
}

async function uploadChecklistFiles() {
    const input = document.getElementById('finalChecklistFiles');
    if(input.files.length === 0) return alert('فایلی انتخاب نشده است.');

    const formData = new FormData();
    formData.append('permit_id', currentPermitId);
    
    for (let i = 0; i < input.files.length; i++) {
        formData.append('checklist_files[]', input.files[i]);
    }

    const btn = document.querySelector('#checklistFilesModal .btn-success');
    btn.disabled = true; btn.textContent = '...';

    try {
        const res = await fetch('/ghom/api/upload_final_checklist.php', { method: 'POST', body: formData });
        const data = await res.json();
        if(data.status === 'success') {
            alert(data.message);
            input.value = ''; // clear input
            loadChecklistFiles(currentPermitId); // refresh list
        } else {
            alert('خطا: ' + data.message);
        }
    } catch(e) {
        alert('خطا در آپلود');
    }
    btn.disabled = false; btn.textContent = 'آپلود';
}
</script>

<?php require_once 'footer.php'; ?>