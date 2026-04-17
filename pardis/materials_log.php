<?php
// public_html/pardis/materials_log.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();
if (!isLoggedIn()) { header('Location: /login.php'); exit(); }

$pageTitle = "ثبت و مدیریت رسید مواد";
function isMobileDevices() {
    // A simple but effective check for common mobile user agents
    return preg_match(
        "/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i",
        $_SERVER["HTTP_USER_AGENT"]
    );
}

// If a mobile device is detected, redirect to the mobile page and stop script execution
if (isMobileDevices()) {
    // Make sure the path to your mobile page is correct
    require_once __DIR__ . '/header.php';

}
else{require_once __DIR__ . '/header.php';

}
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4"><i class="bi bi-box-seam"></i> مدیریت رسید مواد</h2>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="materialsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button">ثبت رسید جدید</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button">لیست رسیدها</button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="materialsTabsContent">
        <!-- Add New Log Tab -->
 <div class="tab-pane fade show active" id="add" role="tabpanel">
            <div class="card">
                <div class="card-header">فرم اعلامیه ورود کالا و تجهیزات به کارگاه</div>
                <div class="card-body">
                    <form id="materialLogForm" enctype="multipart/form-data">
                        <!-- Header Info -->
                        <fieldset class="border p-3 mb-3">
                            <legend class="float-none w-auto px-3 h6">اطلاعات اصلی</legend>
                            <div class="row">
                                <div class="col-md-3"><label class="form-label">شماره لیست بسته‌بندی</label><input type="text" class="form-control" name="packing_list_no" required></div>
                                <div class="col-md-3"><label class="form-label">تاریخ دریافت</label><input type="text" class="form-control" name="receipt_date" data-jdp readonly required></div>
                                <div class="col-md-3"><label class="form-label">شرکت تامین‌کننده</label><input type="text" class="form-control" name="supplier_company"></div>
                                <div class="col-md-3"><label class="form-label">محل تخلیه / انبار</label><input type="text" class="form-control" name="storage_location" value="انبار پروژه"></div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-3"><label class="form-label">نوع ورود</label><select class="form-select" name="entry_type"><option value="permanent">ورود دائم</option><option value="temporary">ورود موقت</option></select></div>
                                <div class="col-md-3"><label class="form-label">کاربرد کالا</label><select class="form-select" name="usage_type"><option value="project_goods">کالای پروژه</option><option value="workshop_equipment">تجهیز کارگاه</option><option value="office_supplies">مصرف عمومی</option></select></div>
                            </div>
                        </fieldset>
                        
                        <!-- Vehicle Info -->
                        <fieldset class="border p-3 mb-3">
                            <legend class="float-none w-auto px-3 h6">اطلاعات حمل</legend>
                            <div class="row">
                                <div class="col-md-4"><label class="form-label">نوع وسیله نقلیه</label><input type="text" class="form-control" name="vehicle_type" placeholder="مثال: وانت"></div>
                                <div class="col-md-4"><label class="form-label">شماره پلاک</label><input type="text" class="form-control" name="vehicle_plate"></div>
                                <div class="col-md-4"><label class="form-label">نام راننده</label><input type="text" class="form-control" name="driver_name"></div>
                            </div>
                        </fieldset>

                        <!-- Items Table -->
                        <fieldset class="border p-3 mb-3">
                            <legend class="float-none w-auto px-3 h6">لیست مواد</legend>
                            <div id="materialItemsContainer"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addMaterialItem()"><i class="bi bi-plus"></i> افزودن ردیف</button>
                        </fieldset>
                        
                        <!-- Notes & Attachments -->
                        <div class="row">
                            <div class="col-md-6"><label class="form-label">یادداشت‌ها</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
                            <div class="col-md-6"><label class="form-label">پیوست فایل‌ها</label><input type="file" class="form-control" name="attachments[]" multiple></div>
                        </div>
                        
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">ثبت رسید</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- List Logs Tab -->
        <div class="tab-pane fade" id="list" role="tabpanel">
             <div id="materialLogsListContainer"></div>
        </div>
    </div>
</div>

<!-- QC Modal (hidden by default) -->
<div class="modal fade" id="qcModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">ثبت نتیجه کنترل کیفی</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="qcForm">
        <div class="modal-body">
          <input type="hidden" id="qc_log_id" name="log_id">
          <div class="mb-3">
            <label class="form-label">نتیجه</label>
            <select class="form-select" name="qc_status" required>
                <option value="approved">✅ تایید شد</option>
                <option value="rejected">❌ رد شد</option>
                <option value="pending">در انتظار بررسی</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">یادداشت‌های بازرسی</label>
            <textarea class="form-control" name="qc_notes" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
          <button type="submit" class="btn btn-primary">ذخیره نتیجه</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    jalaliDatepicker.startWatch({ time: false });
    addMaterialItem(); // Add the first item automatically
    loadMaterialLogs();

    document.getElementById('list-tab').addEventListener('shown.bs.tab', loadMaterialLogs);
});

const materialTypes = {
    'زیرسازی': 'کیلوگرم',
    'پروفیل آلومنیومی': 'متر مربع',
    'شادوباکس': 'متر مربع',
    'شیشه': 'متر مربع',
    'فلاشینگ': 'متر مربع',
    'دودبند': 'متر طول',
    'سمنت بورد': 'متر مربع',
    'پنل آجری': 'متر مربع',
    'پنل بتنی': 'متر مربع',
    'هندریل': 'متر طول',
    'شیشه هندریل': 'متر مربع',
    'قرارداد ساخت': 'درصد',
    'پروفیل هندریل': 'متر طول',
    'قرارداد نصب': 'درصد',
    'داربست': 'متر مکعب',
    'رفع نقص': 'درصد',
    'ازبیلت': 'درصد',
    'برچیدن کارگاه': 'درصد'
};

let materialItemCount = 0;
function addMaterialItem() {
    materialItemCount++;
    const container = document.getElementById('materialItemsContainer');
    const optionsHtml = Object.keys(materialTypes).map(name => `<option value="${name}">${name}</option>`).join('');
    
    // New, more detailed item row
    const itemHtml = `
        <div class="row g-2 mb-2 align-items-center border-bottom pb-2" id="item-${materialItemCount}">
            <div class="col-md-3"><select class="form-select" name="items[${materialItemCount}][material_name]" onchange="updateUnit(this, ${materialItemCount})"><option value="">انتخاب ماده...</option>${optionsHtml}</select></div>
            <div class="col-md-1"><input type="number" class="form-control" name="items[${materialItemCount}][quantity]" placeholder="مقدار" step="0.01"></div>
            <div class="col-md-2"><input type="text" class="form-control" name="items[${materialItemCount}][unit]" id="unit-${materialItemCount}" readonly placeholder="واحد"></div>
            <div class="col-md-1"><input type="text" class="form-control" name="items[${materialItemCount}][package_no]" placeholder="بسته"></div>
            <div class="col-md-2"><input type="text" class="form-control" name="items[${materialItemCount}][dimensions]" placeholder="ابعاد"></div>
            <div class="col-md-1"><input type="number" class="form-control" name="items[${materialItemCount}][weight]" placeholder="وزن"></div>
            <div class="col-md-2"><button type="button" class="btn btn-sm btn-danger" onclick="document.getElementById('item-${materialItemCount}').remove()">حذف</button></div>
        </div>`;
    container.insertAdjacentHTML('beforeend', itemHtml);
}

function updateUnit(selectElement, id) {
    const materialName = selectElement.value;
    const unitInput = document.getElementById(`unit-${id}`);
    if (materialName && materialTypes[materialName]) {
        unitInput.value = materialTypes[materialName];
    } else {
        unitInput.value = '';
    }
}

document.getElementById('materialLogForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'add_material_log');
    
    fetch('materials_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            this.reset();
            addMaterialItem(); // Reset to one item
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => console.error('Error:', error));
});

function loadMaterialLogs() {
    const container = document.getElementById('materialLogsListContainer');
    container.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';

      fetch('materials_api.php?action=get_material_logs')
        .then(res => res.json())
        .then(data => {
            // Change the display to a table
            let tableHtml = `<div class="table-responsive"><table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>تاریخ</th>
                        <th>شماره لیست</th>
                        <th>تامین کننده</th>
                        <th>خلاصه مواد</th>
                        <th>محل انبار</th>
                        <th>کنترل کیفی (QC)</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>`;

            data.logs.forEach(log => {
                const qc_badges = {
                    pending: '<span class="badge bg-warning">در انتظار</span>',
                    approved: '<span class="badge bg-success">تایید شده</span>',
                    rejected: '<span class="badge bg-danger">رد شده</span>'
                };
                const isAdmin = <?php echo json_encode(in_array($_SESSION['role'] ?? 'user', ['admin', 'superuser', 'coa'])); ?>;
                let qc_button = '';
                if (isAdmin) {
                    qc_button = `<button class="btn btn-sm btn-info" onclick="showQcModal(${log.id})">بررسی QC</button>`;
                }
                
                tableHtml += `
                    <tr>
                        <td>${log.receipt_date}</td>
                        <td>${log.packing_list_no}</td>
                        <td>${log.supplier_company}</td>
                        <td>${log.items_summary ? log.items_summary.replace(/;/g, '<br>') : ''}</td>
                        <td>${log.storage_location}</td>
                        <td>${qc_badges[log.qc_status] || ''}<br><small>${log.qc_inspector_name || ''}</small></td>
                        <td>${qc_button}</td>
                    </tr>`;
            });
            tableHtml += `</tbody></table></div>`;
            container.innerHTML = tableHtml;
        });
}
const qcModal = new bootstrap.Modal(document.getElementById('qcModal'));
function showQcModal(logId) {
    document.getElementById('qc_log_id').value = logId;
    qcModal.show();
}

document.getElementById('qcForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'update_qc_status');
    
    fetch('materials_api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                qcModal.hide();
                loadMaterialLogs(); // Refresh the list
            } else {
                alert('Error: ' + data.message);
            }
        });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>