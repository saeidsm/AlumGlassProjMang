<?php
// public_html/pardis/packing_lists.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
require_once __DIR__ . '/includes/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

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

$is_admin = in_array($_SESSION['role'] ?? 'user', ['admin', 'superuser', 'supervisor', 'user']);
$pageTitle = "مدیریت بارنامه‌ها - پروژه دانشگاه خاتم پردیس";

function toJalali($gregorian_date) {
    if (empty($gregorian_date)) return '-';
    
    $parts = explode(' ', $gregorian_date);
    $date_part = $parts[0];
    $time_part = isset($parts[1]) ? $parts[1] : '';
    
    $date_parts = explode('-', $date_part);
    if (count($date_parts) !== 3) return $gregorian_date;
    
    list($y, $m, $d) = $date_parts;
    
    if (function_exists('gregorian_to_jalali')) {
        $j = gregorian_to_jalali($y, $m, $d);
        $jalali_date = $j[0] . '/' . str_pad($j[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($j[2], 2, '0', STR_PAD_LEFT);
        return $time_part ? $jalali_date . ' ' . $time_part : $jalali_date;
    }
    
    return $gregorian_date;
}

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
<link rel="stylesheet" href="/pardis/assets/css/jalalidatepicker.min.css" />
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script src="/pardis/assets/js/jalalidatepicker.min.js"></script>
<!-- Add these lines after the persian-datepicker CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-file-earmark-text"></i> مدیریت بارنامه‌ها</h2>
        <div>
            <button class="btn btn-primary" onclick="showAddPackingModal()">
                <i class="bi bi-plus-circle"></i> افزودن بارنامه
            </button>
            <button class="btn btn-success" onclick="showBulkUploadModal()">
                <i class="bi bi-file-earmark-excel"></i> آپلود اکسل
            </button>
            <a href="download_packing_template.php" class="btn btn-info" download>
                <i class="bi bi-download"></i> دانلود قالب اکسل
            </a>
            <a href="zirsazi_status.php" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i> بازگشت
            </a>
            <a href="select_print_report.php" class="btn btn-success">
    <i class="bi bi-printer"></i> گزارش چاپی
</a>
        </div>
    </div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
    <label class="form-label">از تاریخ</label>
    <input type="text" class="form-control" id="fromDate" data-jdp placeholder="انتخاب تاریخ">
</div>
<div class="col-md-3">
    <label class="form-label">تا تاریخ</label>
    <input type="text" class="form-control" id="toDate" data-jdp placeholder="انتخاب تاریخ">
</div>
            <div class="col-md-3">
                <label class="form-label">نوع متریال</label>
                <input type="text" class="form-control" id="materialType" placeholder="جستجو...">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button class="btn btn-info flex-fill" onclick="filterPackingLists()">
                    <i class="bi bi-search"></i> جستجو
                </button>
                <button class="btn btn-secondary" onclick="clearFilters()">
                    <i class="bi bi-x-circle"></i> پاک کردن
                </button>
            </div>
        </div>
    </div>
</div>

      <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>شماره بارنامه</th>
                            <th>نوع متریال</th>
                            <th>تاریخ دریافت</th>
                            <th>تعداد</th>
                            <th>تامین‌کننده</th>
                            <th>انبار</th>
                            <th>اسناد</th>
                            <th>یادداشت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="packingTableBody">
                        <tr><td colspan="9" class="text-center p-5"><div class="spinner-border"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Packing List Modal -->
<div class="modal fade" id="addPackingModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن بارنامه جدید</h5>
                <button type="button" class="btn-close" onclick="closeAddPackingModal()"></button>
            </div>
            <div class="modal-body">
                <form id="addPackingForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">شماره بارنامه *</label>
                            <input type="text" class="form-control" name="packing_number" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">نوع متریال *</label>
                            <input type="text" class="form-control" name="material_type" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تاریخ دریافت *</label>
<input type="text" class="form-control" id="modalReceivedDate" data-jdp required>
                            <input type="hidden" name="received_date" id="receivedDateHidden">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تعداد *</label>
                            <input type="number" class="form-control" name="quantity" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تامین‌کننده</label>
                            <input type="text" class="form-control" name="supplier">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">انبار</label>
                            <select class="form-select" name="warehouse_id" id="warehouseSelect">
                                <option value="">انتخاب کنید</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">اسناد (PDF یا تصویر) - چند فایل</label>
                        <input type="file" class="form-control" name="documents[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                        <small class="text-muted">فرمت‌های مجاز: PDF, JPG, PNG - حداکثر 10MB برای هر فایل - می‌توانید چند فایل انتخاب کنید</small>
                        <div id="filePreview" class="mt-2"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">یادداشت</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddPackingModal()">لغو</button>
                <button type="button" class="btn btn-primary" onclick="savePackingList()">
                    <span id="saveSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                    ذخیره
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="documentsModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentsModalTitle">اسناد بارنامه</h5>
                <button type="button" class="btn-close" onclick="closeDocumentsModal()"></button>
            </div>
            <div class="modal-body">
                <div id="documentsViewer"></div>
            </div>
        </div>
    </div>
</div>
<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">آپلود فایل اکسل بارنامه‌ها</h5>
                <button type="button" class="btn-close" onclick="closeBulkUploadModal()"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>راهنما:</strong> لطفا فایل اکسل را با فرمت مشخص شده آپلود کنید. ابتدا قالب را دانلود کرده و اطلاعات را در آن وارد نمایید.
                </div>
                <form id="bulkUploadForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">شماره بارنامه *</label>
                        <input type="text" class="form-control" name="packing_number" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">تاریخ دریافت *</label>
<input type="text" class="form-control" id="bulkReceivedDate" data-jdp placeholder="انتخاب تاریخ" required>
                        <input type="hidden" name="received_date" id="bulkReceivedDateHidden">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">انبار</label>
                        <select class="form-select" name="warehouse_id" id="bulkWarehouseSelect">
                            <option value="">انتخاب کنید</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">فایل اکسل *</label>
                        <input type="file" class="form-control" name="excel_file" id="excelFileInput" accept=".xlsx,.xls" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">فایل PDF مدارک (اختیاری)</label>
                        <input type="file" class="form-control" name="pdf_document" accept=".pdf">
                        <small class="text-muted">این PDF برای همه آیتم‌های بارنامه استفاده خواهد شد</small>
                    </div>
                </form>
                <div id="bulkPreview" class="mt-3" style="display: none;">
                    <h6>پیش‌نمایش داده‌ها:</h6>
                    <div class="table-responsive" style="max-height: 300px;">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>ردیف</th>
                                    <th>نوع متریال</th>
                                    <th>تعداد</th>
                                </tr>
                            </thead>
                            <tbody id="bulkPreviewBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBulkUploadModal()">لغو</button>
                <button type="button" class="btn btn-primary" onclick="saveBulkPacking()">
                    <span id="bulkSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                    ذخیره همه
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Document Viewer Modal -->
<div class="modal fade" id="documentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentModalTitle">مشاهده مدرک</h5>
                <button type="button" class="btn-close" onclick="closeDocumentModal()"></button>
            </div>
            <div class="modal-body" style="min-height: 500px;">
                <div id="documentViewer" class="text-center"></div>
            </div>
        </div>
    </div>
</div>






<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="/pardis/assets/js/xlsx.full.min.js"></script>

<script>
let addPackingModalInstance, documentsModalInstance, bulkUploadModalInstance;
let packingTable;

// ==================== DATEPICKER INITIALIZATION ====================
function initFilterDatepickers() {
    $('#fromDate').persianDatepicker({
        format: 'YYYY/MM/DD',
        calendar: {
            persian: {
                locale: 'fa'
            }
        },
        observer: true,
        autoClose: true
    });
    
    $('#toDate').persianDatepicker({
        format: 'YYYY/MM/DD',
        calendar: {
            persian: {
                locale: 'fa'
            }
        },
        observer: true,
        autoClose: true
    });
}

function initModalDatepicker() {
    // Destroy any existing instance
    try {
        $('#modalReceivedDate').persianDatepicker('destroy');
    } catch(e) {}
    
    setTimeout(function() {
        $('#modalReceivedDate').persianDatepicker({
            format: 'YYYY/MM/DD',
            calendar: {
                persian: {
                    locale: 'fa'
                }
            },
            observer: true,
            autoClose: true,
            onSelect: function(unix) {
                const date = new Date(unix);
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const gregorian = `${year}-${month}-${day}`;
                document.getElementById('receivedDateHidden').value = gregorian;
                console.log('✓ Modal date selected:', gregorian);
            }
        });
        console.log('✓ Modal datepicker initialized');
    }, 350);
}

function initBulkDatepicker() {
    // Destroy any existing instance
    try {
        $('#bulkReceivedDate').persianDatepicker('destroy');
    } catch(e) {}
    
    setTimeout(function() {
        $('#bulkReceivedDate').persianDatepicker({
            format: 'YYYY/MM/DD',
            calendar: {
                persian: {
                    locale: 'fa'
                }
            },
            observer: true,
            autoClose: true,
            onSelect: function(unix) {
                const date = new Date(unix);
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const gregorian = `${year}-${month}-${day}`;
                document.getElementById('bulkReceivedDateHidden').value = gregorian;
                console.log('✓ Bulk date selected:', gregorian);
            }
        });
        console.log('✓ Bulk datepicker initialized');
    }, 350);
}

// ==================== DOCUMENT READY ====================
document.addEventListener('DOMContentLoaded', function() {
    addPackingModalInstance = new bootstrap.Modal(document.getElementById('addPackingModal'));
    documentsModalInstance = new bootstrap.Modal(document.getElementById('documentsModal'));
    bulkUploadModalInstance = new bootstrap.Modal(document.getElementById('bulkUploadModal'));
    
    // Initialize filter datepickers
    initFilterDatepickers();
    
    // Initialize modal datepickers when shown
    document.getElementById('addPackingModal').addEventListener('shown.bs.modal', function () {
        console.log('Add Packing Modal opened');
        initModalDatepicker();
    });
    
    document.getElementById('bulkUploadModal').addEventListener('shown.bs.modal', function () {
        console.log('Bulk Upload Modal opened');
        initBulkDatepicker();
    });
    
    // File preview handler
    const fileInput = document.querySelector('input[name="documents[]"]');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const preview = document.getElementById('filePreview');
            preview.innerHTML = '';
            
            if (this.files.length > 0) {
                const list = document.createElement('ul');
                list.className = 'list-group';
                
                Array.from(this.files).forEach((file, index) => {
                    const item = document.createElement('li');
                    item.className = 'list-group-item d-flex justify-content-between align-items-center';
                    item.innerHTML = `
                        <span><i class="bi bi-file-earmark"></i> ${file.name}</span>
                        <span class="badge bg-info">${formatFileSize(file.size)}</span>
                    `;
                    list.appendChild(item);
                });
                
                preview.appendChild(list);
            }
        });
    }
    
    loadWarehouses();
    loadPackingLists();
});

// ==================== UTILITY FUNCTIONS ====================
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function convertPersianToLatin(str) {
    if (!str) return str;
    const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    const latinNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    
    let result = str;
    for (let i = 0; i < 10; i++) {
        result = result.replace(new RegExp(persianNumbers[i], 'g'), latinNumbers[i]);
        result = result.replace(new RegExp(arabicNumbers[i], 'g'), latinNumbers[i]);
    }
    return result;
}

// ==================== MODAL FUNCTIONS ====================
function showAddPackingModal() {
    document.getElementById('addPackingForm').reset();
    document.getElementById('receivedDateHidden').value = '';
    document.getElementById('filePreview').innerHTML = '';
    addPackingModalInstance.show();
}

function closeAddPackingModal() {
    if(addPackingModalInstance) addPackingModalInstance.hide();
}

function showBulkUploadModal() {
    document.getElementById('bulkUploadForm').reset();
    document.getElementById('bulkReceivedDateHidden').value = '';
    document.getElementById('bulkPreview').style.display = 'none';
    document.getElementById('bulkPreviewBody').innerHTML = '';
    bulkUploadModalInstance.show();
}

function closeBulkUploadModal() { 
    if(bulkUploadModalInstance) bulkUploadModalInstance.hide(); 
}

function closeDocumentsModal() {
    if(documentsModalInstance) documentsModalInstance.hide();
}

// ==================== DATA LOADING ====================
function loadWarehouses() {
    fetch('zirsazi_api.php?action=get_warehouses')
    .then(res => {
        if (!res.ok) throw new Error('Network response was not ok');
        return res.json();
    })
    .then(data => {
        if (data.success && data.data) {
            const select = document.getElementById('warehouseSelect');
            const bulkSelect = document.getElementById('bulkWarehouseSelect');
            data.data.forEach(wh => {
                const option = document.createElement('option');
                option.value = wh.id;
                option.textContent = wh.name;
                select.appendChild(option.cloneNode(true));
                if (bulkSelect) {
                    bulkSelect.appendChild(option);
                }
            });
        }
    })
    .catch(error => {
        console.error('Error loading warehouses:', error);
    });
}

function loadPackingLists(useFilters = false) {
    const params = new URLSearchParams();
    
    if (useFilters) {
        let fromDate = document.getElementById('fromDate')?.value.trim();
        let toDate = document.getElementById('toDate')?.value.trim();
        const materialType = document.getElementById('materialType')?.value.trim();
        
        if (fromDate) params.append('from_date', fromDate);
        if (toDate) params.append('to_date', toDate);
        if (materialType) params.append('material_type', materialType);
    }
    
    const url = `zirsazi_api.php?action=get_packing_lists${params.toString() ? '&' + params.toString() : ''}`;
    
    fetch(url)
    .then(res => res.json())
    .then(data => {
        if (packingTable) {
            packingTable.destroy();
            packingTable = null;
        }
        
        const tbody = document.getElementById('packingTableBody');
        tbody.innerHTML = '';
        
        if (!data.success || !data.data || data.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center">موردی یافت نشد</td></tr>';
            return;
        }
        
        data.data.forEach(item => {
            const docCount = parseInt(item.document_count) || 0;
            const docButton = docCount > 0 
                ? `<button class="btn btn-sm btn-info" onclick="viewDocuments(${item.id}, '${item.packing_number}')">
                    <i class="bi bi-files"></i> ${docCount} فایل
                   </button>`
                : '<span class="text-muted">-</span>';
            
            const row = `
                <tr>
                    <td>${item.packing_number}</td>
                    <td>${item.material_type}</td>
                    <td data-order="${item.received_date}">${item.received_date}</td>
                    <td class="text-center">${item.quantity}</td>
                    <td>${item.supplier || '-'}</td>
                    <td>${item.warehouse_name || '-'}</td>
                    <td class="text-center">${docButton}</td>
                    <td>${item.notes || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="deletePacking(${item.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>`;
            tbody.insertAdjacentHTML('beforeend', row);
        });
        
        packingTable = $('#packingTableBody').closest('table').DataTable({
            "language": {
                "lengthMenu": "نمایش _MENU_ رکورد در هر صفحه",
                "zeroRecords": "موردی یافت نشد",
                "info": "نمایش صفحه _PAGE_ از _PAGES_",
                "infoEmpty": "رکوردی موجود نیست",
                "infoFiltered": "(فیلتر شده از _MAX_ رکورد)",
                "search": "جستجو:",
                "paginate": {
                    "first": "اول",
                    "last": "آخر",
                    "next": "بعدی",
                    "previous": "قبلی"
                }
            },
            "order": [[2, "desc"]],
            "pageLength": 25,
            "columnDefs": [
                { "orderable": false, "targets": [6, 8] }
            ]
        });
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('packingTableBody').innerHTML = 
            '<tr><td colspan="9" class="text-center text-danger">خطا در بارگذاری اطلاعات</td></tr>';
    });
}

// ==================== FILTER FUNCTIONS ====================
function filterPackingLists() {
    let fromDate = document.getElementById('fromDate')?.value.trim();
    let toDate = document.getElementById('toDate')?.value.trim();
    
    fromDate = convertPersianToLatin(fromDate);
    toDate = convertPersianToLatin(toDate);
    
    if (!fromDate && !toDate && !document.getElementById('materialType')?.value.trim()) {
        alert('لطفاً حداقل یک فیلتر را وارد کنید');
        return;
    }
    
    if ((fromDate && !toDate) || (!fromDate && toDate)) {
        alert('لطفاً هر دو تاریخ (از و تا) را وارد کنید');
        return;
    }
    
    if (fromDate && toDate) {
        const fromParts = fromDate.split('/').map(Number);
        const toParts = toDate.split('/').map(Number);
        
        const fromValue = fromParts[0] * 10000 + fromParts[1] * 100 + fromParts[2];
        const toValue = toParts[0] * 10000 + toParts[1] * 100 + toParts[2];
        
        if (fromValue > toValue) {
            alert('تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد');
            return;
        }
    }
    
    loadPackingLists(true);
}

function clearFilters() {
    document.getElementById('fromDate').value = '';
    document.getElementById('toDate').value = '';
    document.getElementById('materialType').value = '';
    loadPackingLists(false);
}

// ==================== SAVE FUNCTIONS ====================
function savePackingList() {
    const form = document.getElementById('addPackingForm');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const hiddenDate = document.getElementById('receivedDateHidden').value;
    if (!hiddenDate) {
        alert('لطفا تاریخ دریافت را انتخاب کنید');
        return;
    }
    
    const saveBtn = document.querySelector('#addPackingModal .btn-primary');
    const spinner = document.getElementById('saveSpinner');
    saveBtn.disabled = true;
    spinner.classList.remove('d-none');
    
    const formData = new FormData(form);
    formData.append('action', 'add_packing_list');

    fetch('zirsazi_api.php', { 
        method: 'POST', 
        body: formData 
    })
    .then(res => res.json())
    .then(result => {
        if(result.success) {
            addPackingModalInstance.hide();
            loadPackingLists();
            
            let message = 'بارنامه با موفقیت اضافه شد';
            if (result.uploaded_files && result.uploaded_files.length > 0) {
                message += `\n${result.uploaded_files.length} فایل آپلود شد`;
            }
            if (result.upload_errors && result.upload_errors.length > 0) {
                message += '\n\nخطاها:\n' + result.upload_errors.join('\n');
            }
            alert(message);
        } else {
            alert('خطا: ' + (result.message || 'خطایی ناشناخته'));
        }
    })
    .catch(error => {
        alert('خطا در ذخیره اطلاعات: ' + error.message);
    })
    .finally(() => {
        saveBtn.disabled = false;
        spinner.classList.add('d-none');
    });
}

function saveBulkPacking() {
    const form = document.getElementById('bulkUploadForm');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const hiddenDate = document.getElementById('bulkReceivedDateHidden').value;
    if (!hiddenDate) {
        alert('لطفاً تاریخ دریافت را انتخاب کنید');
        return;
    }
    
    const saveBtn = document.querySelector('#bulkUploadModal .btn-primary');
    const spinner = document.getElementById('bulkSpinner');
    saveBtn.disabled = true;
    spinner.classList.remove('d-none');
    
    const formData = new FormData(form);
    formData.append('action', 'add_bulk_packing');

    fetch('zirsazi_api.php', { 
        method: 'POST', 
        body: formData 
    })
    .then(res => {
        if (!res.ok) throw new Error('Network response was not ok');
        return res.json();
    })
    .then(result => {
        if(result.success) {
            bulkUploadModalInstance.hide();
            loadPackingLists();
            
            if (result.corrected_materials && result.corrected_materials.length > 0) {
                showCorrectedMaterialsNotification(result.corrected_materials, result.count);
            } else {
                alert(`تعداد ${result.count} بارنامه با موفقیت اضافه شد`);
            }
        } else {
            if (result.invalid_materials && result.invalid_materials.length > 0) {
                showInvalidMaterialsModal(result.invalid_materials, result.valid_count, result.corrected_materials);
            } else {
                alert('خطا: ' + (result.message || 'خطایی ناشناخته'));
            }
        }
    })
    .catch(error => {
        console.error('Error saving bulk packing:', error);
        alert('خطا در ذخیره اطلاعات: ' + error.message);
    })
    .finally(() => {
        saveBtn.disabled = false;
        spinner.classList.add('d-none');
    });
}

// ==================== DELETE FUNCTIONS ====================
function deletePacking(id) {
    if (!confirm('آیا از حذف این بارنامه اطمینان دارید؟')) return;
    
    fetch('zirsazi_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=delete_packing&id=${id}`
    })
    .then(res => res.json())
    .then(result => {
        if(result.success) {
            loadPackingLists();
            alert('بارنامه حذف شد');
        } else {
            alert('خطا: ' + (result.message || 'خطایی ناشناخته'));
        }
    })
    .catch(error => {
        console.error('Error deleting packing:', error);
        alert('خطا در حذف بارنامه: ' + error.message);
    });
}

function deleteDocument(docId, itemId, packingNumber) {
    if (!confirm('آیا از حذف این سند اطمینان دارید؟')) return;
    
    fetch('zirsazi_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=delete_document&document_id=${docId}`
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            viewDocuments(itemId, packingNumber);
            alert('سند حذف شد');
        } else {
            alert('خطا: ' + result.message);
        }
    });
}

// ==================== DOCUMENT VIEWING ====================
function viewDocuments(itemId, packingNumber) {
    document.getElementById('documentsModalTitle').textContent = `اسناد بارنامه: ${packingNumber}`;
    const viewer = document.getElementById('documentsViewer');
    viewer.innerHTML = '<div class="text-center"><div class="spinner-border"></div></div>';
    
    documentsModalInstance.show();
    
    fetch(`zirsazi_api.php?action=get_item_documents&item_id=${itemId}&item_type=packing_list`)
    .then(res => res.json())
    .then(data => {
        if (!data.success || !data.data || data.data.length === 0) {
            viewer.innerHTML = '<div class="alert alert-info">هیچ سندی یافت نشد</div>';
            return;
        }
        
        let html = '<div class="row g-3">';
        data.data.forEach(doc => {
            const icon = doc.document_type === 'pdf' ? 'file-pdf' : 'file-image';
            const color = doc.document_type === 'pdf' ? 'danger' : 'primary';
            
            html += `
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-${icon} text-${color} fs-3 me-2"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">${doc.document_name}</h6>
                                    <small class="text-muted">${formatFileSize(doc.file_size)}</small>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="/pardis/${doc.document_path}" target="_blank" class="btn btn-sm btn-primary flex-fill">
                                    <i class="bi bi-download"></i> دانلود
                                </a>
                                <button class="btn btn-sm btn-danger" onclick="deleteDocument(${doc.id}, ${itemId}, '${packingNumber}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        viewer.innerHTML = html;
    })
    .catch(error => {
        viewer.innerHTML = '<div class="alert alert-danger">خطا در بارگذاری اسناد</div>';
    });
}

// ==================== EXCEL FUNCTIONS ====================
function previewExcel(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    if (typeof XLSX === 'undefined') {
        alert('خطا: کتابخانه اکسل بارگذاری نشده است. لطفا صفحه را رفرش کنید.');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            const jsonData = XLSX.utils.sheet_to_json(firstSheet, {header: 1});
            
            const tbody = document.getElementById('bulkPreviewBody');
            tbody.innerHTML = '';
            
            let rowNum = 0;
            jsonData.slice(3).forEach((row) => {
                if (row[1] && row[2]) {
                    rowNum++;
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${rowNum}</td>
                        <td>${row[1]}</td>
                        <td>${row[2]}</td>
                    `;
                    tbody.appendChild(tr);
                }
            });
            
            if (rowNum > 0) {
                document.getElementById('bulkPreview').style.display = 'block';
            } else {
                alert('هیچ داده‌ای در فایل اکسل یافت نشد. لطفا از قالب استاندارد استفاده کنید.');
            }
        } catch(error) {
            console.error('Error reading Excel:', error);
            alert('خطا در خواندن فایل اکسل: ' + error.message);
        }
    };
    reader.onerror = function() {
        alert('خطا در خواندن فایل');
    };
    reader.readAsArrayBuffer(file);
}

// ==================== NOTIFICATION MODALS ====================
function showCorrectedMaterialsNotification(correctedMaterials, totalCount) {
    let message = '<div class="alert alert-success">';
    message += '<h5 class="alert-heading"><i class="bi bi-check-circle-fill"></i> آپلود موفقیت‌آمیز</h5>';
    message += `<p><strong>تعداد ${totalCount} بارنامه با موفقیت ثبت شد.</strong></p>`;
    message += '<hr>';
    message += '<p><strong>تصحیحات خودکار انجام شده:</strong></p>';
    message += '<div class="table-responsive mt-2">';
    message += '<table class="table table-sm table-bordered">';
    message += '<thead><tr><th>ردیف اکسل</th><th>نام اصلی</th><th><i class="bi bi-arrow-left-right"></i></th><th>نام تصحیح شده</th></tr></thead>';
    message += '<tbody>';
    
    correctedMaterials.forEach(item => {
        message += `<tr>
            <td class="text-center">${item.row}</td>
            <td><span class="text-muted">${item.original}</span></td>
            <td class="text-center"><i class="bi bi-arrow-right text-success"></i></td>
            <td><strong class="text-success">${item.corrected}</strong></td>
        </tr>`;
    });
    
    message += '</tbody></table></div>';
    message += '<div class="alert alert-info mb-0 mt-3">';
    message += '<i class="bi bi-info-circle"></i> سیستم به صورت خودکار نام‌های مواد را با فرمت صحیح مطابقت داده است.';
    message += '</div>';
    message += '</div>';
    
    message += '<div class="d-flex justify-content-end mt-3">';
    message += '<button class="btn btn-success" onclick="closeCorrectedMaterialsModal()">متوجه شدم</button>';
    message += '</div>';
    
    const modalId = 'correctedMaterialsModal';
    let modal = document.getElementById(modalId);
    
    if (!modal) {
        modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'modal fade';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('data-bs-backdrop', 'static');
        modal.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">اطلاعیه تصحیحات خودکار</h5>
                        <button type="button" class="btn-close btn-close-white" onclick="closeCorrectedMaterialsModal()"></button>
                    </div>
                    <div class="modal-body" id="correctedMaterialsBody"></div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    document.getElementById('correctedMaterialsBody').innerHTML = message;
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    window.correctedMaterialsModalInstance = bsModal;
}

function closeCorrectedMaterialsModal() {
    if (window.correctedMaterialsModalInstance) {
        window.correctedMaterialsModalInstance.hide();
    }
}

function showInvalidMaterialsModal(invalidMaterials, validCount, correctedMaterials) {
    let message = '<div class="alert alert-danger">';
    message += '<h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> مواد نامعتبر یافت شد</h5>';
    
    // Show corrections summary if any
    if (correctedMaterials && correctedMaterials.length > 0) {
        message += `<div class="alert alert-success mb-3">
            <i class="bi bi-check-circle"></i> <strong>${correctedMaterials.length}</strong> مورد به صورت خودکار تصحیح شد
        </div>`;
    }
    
    message += '<p><strong>موارد زیر در لیست موادهای سفارش داده شده وجود ندارند:</strong></p>';
    message += '<div class="table-responsive mt-3">';
    message += '<table class="table table-sm table-bordered">';
    message += '<thead><tr><th>ردیف اکسل</th><th>نام مواد</th><th>تعداد</th><th>پیشنهاد</th></tr></thead>';
    message += '<tbody>';
    
    invalidMaterials.forEach(item => {
        const suggestionText = item.suggestion && item.suggestion !== item.material 
            ? `<span class="text-muted small">${item.suggestion}</span>` 
            : '-';
        message += `<tr>
            <td class="text-center">${item.row}</td>
            <td><strong>${item.material}</strong></td>
            <td class="text-center">${item.quantity}</td>
            <td>${suggestionText}</td>
        </tr>`;
    });
    
    message += '</tbody></table></div>';
    message += '<hr>';
    message += '<p class="mb-2"><strong>راه حل:</strong></p>';
    message += '<ol class="mb-0">';
    message += '<li>به صفحه <strong>داشبورد وضعیت زیرسازی</strong> بروید</li>';
    message += '<li>روی دکمه <strong>"افزودن متریال جدید"</strong> کلیک کنید</li>';
    message += '<li>موادهای بالا را اضافه کنید</li>';
    message += '<li>سپس دوباره فایل اکسل را آپلود کنید</li>';
    message += '</ol>';
    
    if (validCount > 0) {
        message += `<div class="alert alert-info mt-3 mb-0">
            <i class="bi bi-info-circle"></i> توجه: ${validCount} مواد معتبر در فایل شما یافت شد که آپلود نشدند.
        </div>`;
    }
    
    message += '</div>';
    
    message += '<div class="d-flex gap-2 justify-content-end mt-3">';
    message += '<button class="btn btn-secondary" onclick="closeInvalidMaterialsModal()">بستن</button>';
    message += '<a href="zirsazi_status.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> افزودن موادها</a>';
    message += '</div>';
    
    // Create and show modal
    const modalId = 'invalidMaterialsModal';
    let modal = document.getElementById(modalId);
    
    if (!modal) {
        modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'modal fade';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('data-bs-backdrop', 'static');
        modal.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">هشدار: موادهای نامعتبر</h5>
                        <button type="button" class="btn-close btn-close-white" onclick="closeInvalidMaterialsModal()"></button>
                    </div>
                    <div class="modal-body" id="invalidMaterialsBody"></div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    document.getElementById('invalidMaterialsBody').innerHTML = message;
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    window.invalidMaterialsModalInstance = bsModal;
}

function closeInvalidMaterialsModal() {
    if (window.invalidMaterialsModalInstance) {
        window.invalidMaterialsModalInstance.hide();
    }
}

// Add CSS styles
const style = document.createElement('style');
style.textContent = `
    /* DataTables RTL Support */
    .dataTables_wrapper .dataTables_filter {
        text-align: left;
    }
    
    .dataTables_wrapper .dataTables_filter input {
        margin-left: 0.5em;
        margin-right: 0;
    }
    
    .dataTables_wrapper .dataTables_length {
        text-align: right;
    }
    
    .dataTables_wrapper .dataTables_info {
        text-align: right;
    }
    
    .dataTables_wrapper .dataTables_paginate {
        text-align: left;
    }
    
    table.dataTable thead th {
        cursor: pointer;
    }
    
    #filePreview .list-group-item {
        font-size: 0.9rem;
    }
    
    #invalidMaterialsModal .table td, 
    #invalidMaterialsModal .table th,
    #correctedMaterialsModal .table td,
    #correctedMaterialsModal .table th {
        vertical-align: middle;
    }
    
    #documentsViewer .card {
        transition: transform 0.2s;
    }
    
    #documentsViewer .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
`;
document.head.appendChild(style);
</script>
<?php require_once __DIR__ . '/footer.php'; ?>