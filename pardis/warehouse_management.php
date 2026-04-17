<?php
// public_html/pardis/warehouse_management.php
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

$is_admin = in_array($_SESSION['role'] ?? 'user', ['admin', 'superuser', 'supervisor', 'user']);
$pageTitle = "مدیریت انبار - پروژه دانشگاه خاتم پردیس";

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
        return $time_part ? $jalali_date . ' ' . substr($time_part, 0, 5) : $jalali_date;
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
    require_once __DIR__ . '/header.php';
} else {
    require_once __DIR__ . '/header.php';
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@latest/dist/css/persian-datepicker.min.css">
<style>
    .table-history th, .table-history td {
        white-space: nowrap; /* Prevents text from wrapping and keeps rows compact */
        vertical-align: middle;
    }
    .table-history .notes-col {
        white-space: normal; /* Allow notes to wrap */
        min-width: 250px;
    }
</style>
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-box-seam"></i> مدیریت انبار</h2>
        <div>
            <button class="btn btn-success" onclick="showTransactionModal('in')">
                <i class="bi bi-box-arrow-in-down"></i> ورود کالا
            </button>
            <button class="btn btn-warning" onclick="showTransactionModal('out')">
                <i class="bi bi-box-arrow-up"></i> خروج کالا
            </button>
            <button class="btn btn-danger" onclick="showBulkExitModal()">
                    <i class="bi bi-file-earmark-excel"></i> خروج اکسل
            </button>
            <a href="zirsazi_status.php" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i> بازگشت
            </a>
        </div>
    </div>

    <!-- Warehouse Selector -->
     <div class="card mb-3">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label class="form-label">انتخاب انبار</label>
                    <select class="form-select" id="warehouseFilter" onchange="loadInventory(); loadTransactions();">
                        <option value="">همه انبارها</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <!-- UPDATED: Tab names are now clearer -->
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory" type="button">
                                <i class="bi bi-box-seam"></i> موجودی فعلی
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button">
                                <i class="bi bi-clock-history"></i> تاریخچه تراکنش‌ها
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>


    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Inventory Tab -->
        <div class="tab-pane fade show active" id="inventory" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>انبار</th>
                                    <th>نوع متریال</th>
                                    <th>موجودی فعلی</th>
                                    <th>سطح موجودی</th> <!-- CORRECTED HEADER -->
                                </tr>
                            </thead>
                            <tbody id="inventoryTableBody">
                                <!-- Data will be loaded by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>


        <!-- Transactions Tab -->
          <div class="tab-pane fade" id="transactions" role="tabpanel">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4"><label class="form-label">از تاریخ</label><input type="text" class="form-control persian-datepicker" id="transFromDate"></div>
                        <div class="col-md-4"><label class="form-label">تا تاریخ</label><input type="text" class="form-control persian-datepicker" id="transToDate"></div>
                        <div class="col-md-4 d-flex align-items-end"><button class="btn btn-info w-100" onclick="loadTransactions()"><i class="bi bi-search"></i> جستجو</button></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <!-- Redesigned history table -->
                        <table class="table table-bordered table-hover table-history">
                            <thead class="table-light">
                                <tr>
                                    <th>تاریخ</th>
                                    <th>نوع</th>
                                    <th>متریال</th>
                                    <th>تعداد</th>
                                    <th>انبار</th>
                                    <th>پیمانکار </th>
                                    <th>محل پروژه </th>
                                    <th>ثبت توسط</th>
                                    <th class="notes-col">یادداشت / سند</th>
                                </tr>
                            </thead>
                            <tbody id="transactionsTableBody">
                                <!-- Data will be loaded by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Transaction Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transactionModalTitle">تراکنش انبار</h5>
                <button type="button" class="btn-close" onclick="closeTransactionModal()"></button>
            </div>
            <div class="modal-body">
                <form id="transactionForm">
                    <input type="hidden" name="transaction_type" id="transactionType">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">انبار *</label>
                            <select class="form-select" name="warehouse_id" id="transWarehouseSelect" required>
                                <option value="">انتخاب کنید</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">نوع متریال *</label>
                            <input type="text" class="form-control" name="material_type" id="transMaterialType" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تعداد *</label>
                            <input type="number" class="form-control" name="quantity" required min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تاریخ و ساعت تراکنش *</label>
                            <input type="text" class="form-control persian-datepicker-time" id="transactionDatePicker" placeholder="انتخاب تاریخ و ساعت" required>
                            <input type="hidden" name="transaction_date" id="transactionDateHidden">
                        </div>
                    </div>

                    <div id="outFields" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نام پیمانکار</label>
                                <input type="text" class="form-control" name="contractor_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ساختمان</label>
                                <select class="form-select" id="transBuilding" onchange="loadTransParts()">
                                    <option value="">انتخاب کنید</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">قسمت پروژه</label>
                            <select class="form-select" name="project_location_id" id="transPartSelect">
                                <option value="">انتخاب کنید</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">یادداشت</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeTransactionModal()">لغو</button>
                <button type="button" class="btn btn-primary" onclick="saveTransaction()">
                    <span id="transSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                    ثبت تراکنش
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="bulkExitModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">خروج گروهی مواد با اکسل</h5>
                <button type="button" class="btn-close" onclick="closeBulkExitModal()"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info d-flex justify-content-between align-items-center">
                    <!-- UNIFIED TEMPLATE LINK -->
                    <span>لطفا از قالب استاندارد ورود/خروج استفاده کنید.</span>
                    <a href="download_packing_template.php" class="btn btn-sm btn-light" download>
                        <i class="bi bi-download"></i> دانلود قالب
                    </a>
                </div>
                <form id="bulkExitForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">انبار *</label>
                            <select class="form-select" name="warehouse_id" id="bulkExitWarehouseSelect" required></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تاریخ و ساعت تراکنش *</label>
                            <input type="text" class="form-control persian-datepicker-time" id="bulkExitDatePicker" required>
                            <input type="hidden" name="transaction_date" id="bulkExitDateHidden">
                        </div>
                    </div>
                    <div class="row">
                         <div class="col-md-6 mb-3">
                            <label class="form-label">نام پیمانکار</label>
                            <input type="text" class="form-control" name="contractor_name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ساختمان</label>
                            <select class="form-select" id="bulkExitBuildingSelect" onchange="loadBulkExitTransParts()"></select>
                        </div>
                    </div>
                     <div class="mb-3">
                        <label class="form-label">قسمت پروژه</label>
                        <select class="form-select" name="project_location_id" id="bulkExitPartSelect"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">فایل اکسل *</label>
                        <input type="file" class="form-control" name="excel_file" id="bulkExitExcelInput" accept=".xlsx,.xls" required>
                    </div>
                    <!-- NEW: Document upload field -->
                    <div class="mb-3">
                        <label class="form-label">سند خروج (اختیاری)</label>
                        <input type="file" class="form-control" name="document_file" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">می‌توانید سند امضا شده را اینجا پیوست کنید.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">یادداشت</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </form>

                <!-- NEW: Preview table -->
                <div id="bulkExitPreview" class="mt-3" style="display: none;">
                    <h6>پیش‌نمایش داده‌ها:</h6>
                    <div class="table-responsive" style="max-height: 250px;">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light"><tr><th>ردیف</th><th>شرح کالا</th><th>تعداد</th></tr></thead>
                            <tbody id="bulkExitPreviewBody"></tbody>
                        </table>
                    </div>
                </div>
                
                <div id="bulkExitErrors" class="alert alert-danger mt-3" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBulkExitModal()">لغو</button>
                <button type="button" class="btn btn-primary" onclick="saveBulkExit()">
                    <span id="bulkExitSpinner" class="spinner-border spinner-border-sm d-none"></span>
                    ثبت خروج
                </button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-date@latest/dist/persian-date.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>

<script>
let transactionModalInstance, bulkExitModalInstance;
let projectLocations = { buildings: [], parts: [] };

document.addEventListener('DOMContentLoaded', function() {
    transactionModalInstance = new bootstrap.Modal(document.getElementById('transactionModal'));
       bulkExitModalInstance = new bootstrap.Modal(document.getElementById('bulkExitModal')); 
    $('.persian-datepicker').persianDatepicker({
        format: 'YYYY/MM/DD',
        observer: true,
        autoClose: true,
         calendar: {
            persian: {
                locale: 'fa',
                leapYearMode: 'astronomical'
            }
        },
    });
    
    $('.persian-datepicker-time').persianDatepicker({
        format: 'YYYY/MM/DD HH:mm',
        timePicker: {
            enabled: true,
            meridiem: {
                enabled: false
            }
        },
        observer: true,
        autoClose: true,
         calendar: {
            persian: {
                locale: 'fa',
                leapYearMode: 'astronomical'
            }
        },
        onSelect: function(unix) {
            const date = new persianDate(unix);
            const gregorian = date.toCalendar('gregorian');
            document.getElementById('transactionDateHidden').value = 
                gregorian.format('YYYY-MM-DD HH:mm:00');
        }
    });
    $('#bulkExitDatePicker').persianDatepicker({
        format: 'YYYY/MM/DD HH:mm',
        timePicker: { enabled: true, meridiem: { enabled: false } },
        observer: true, autoClose: true,
        onSelect: function(unix) {
            // Use reliable native JS Date conversion
            const date = new Date(unix);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            document.getElementById('bulkExitDateHidden').value = `${year}-${month}-${day} ${hours}:${minutes}:00`;
        }
    });

    document.getElementById('bulkExitExcelInput').addEventListener('change', previewExitExcel); 
    loadWarehouses();
    loadInventory();
    loadTransactions();
    loadProjectLocations();
});



function formatNotes(notes) {
    if (!notes || notes.trim() === '') {
        return '-';
    }
    // The API now sends ready-to-use HTML. We just return it.
    // The browser will render the <a> tag automatically.
    return notes;
}
// MODAL CLOSE FUNCTION
function closeTransactionModal() { if (transactionModalInstance) transactionModalInstance.hide(); }
function closeBulkExitModal() { if (bulkExitModalInstance) bulkExitModalInstance.hide(); }

function previewExitExcel(e) {
    const file = e.target.files[0];
    const previewDiv = document.getElementById('bulkExitPreview');
    const previewBody = document.getElementById('bulkExitPreviewBody');
    previewDiv.style.display = 'none';
    previewBody.innerHTML = '';
    if (!file) return;
    
    if (typeof XLSX === 'undefined') {
        alert('خطا: کتابخانه اکسل بارگذاری نشده است.');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            const jsonData = XLSX.utils.sheet_to_json(firstSheet, {header: 1});
            
            let rowNum = 0;
            // Find header row to start processing after it
            let dataStarted = false;
            jsonData.forEach((row) => {
                if (!dataStarted && row[1] && String(row[1]).includes('شرح کالا')) {
                    dataStarted = true;
                    return; // Skip header row
                }
                if (!dataStarted) return; // Skip rows before header

                // Check for content in material (col B) and quantity (col C)
                if (row[1] && row[2]) {
                    rowNum++;
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td>${rowNum}</td><td>${row[1]}</td><td>${row[2]}</td>`;
                    previewBody.appendChild(tr);
                }
            });
            
            if (rowNum > 0) {
                previewDiv.style.display = 'block';
            } else {
                alert('هیچ داده معتبری در فایل اکسل یافت نشد.');
            }
        } catch(error) {
            console.error('Error reading Excel for exit:', error);
            alert('خطا در خواندن فایل اکسل: ' + error.message);
        }
    };
    reader.readAsArrayBuffer(file);
}

function loadWarehouses() {
    fetch('zirsazi_api.php?action=get_warehouses')
    .then(res => res.json())
    .then(data => {
        if (data.success && data.data) {
            // Populate all warehouse dropdowns
            const selects = document.querySelectorAll('#warehouseFilter, #transWarehouseSelect, #bulkExitWarehouseSelect');
            selects.forEach(select => {
                // Clear existing options except the first one
                while (select.options.length > 1) select.remove(1);
                data.data.forEach(wh => {
                    const option = new Option(wh.name, wh.id);
                    select.add(option);
                });
            });
        }
    });
}


document.getElementById('inventory-tab').addEventListener('shown.bs.tab', function() {
    loadInventory();
});

document.getElementById('transactions-tab').addEventListener('shown.bs.tab', function() {
    loadTransactions();
});


function loadProjectLocations() {
    fetch('zirsazi_api.php?action=get_project_locations')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            projectLocations = data;
            const buildingSelects = document.querySelectorAll('#transBuilding, #bulkExitBuildingSelect');
            
            buildingSelects.forEach(select => {
                select.innerHTML = '<option value="">انتخاب کنید</option>';
                // CORRECTED: Loop through the array of building objects
                data.buildings.forEach(building => {
                    const option = document.createElement('option');
                    // Set the value to the building's ID
                    option.value = building.id; 
                    // Set the displayed text to the building's name
                    option.textContent = building.building_name; 
                    select.appendChild(option);
                });
            });
        }
    })
    .catch(error => console.error('Error loading project locations:', error));
}



function loadTransParts() { loadPartsForSelects('#transBuilding', '#transPartSelect'); }
function loadBulkExitTransParts() { loadPartsForSelects('#bulkExitBuildingSelect', '#bulkExitPartSelect'); }

function loadPartsForSelects(buildingSelectId, partSelectId) {
    const buildingId = document.querySelector(buildingSelectId).value;
    const partSelect = document.querySelector(partSelectId);
    partSelect.innerHTML = '<option value="">انتخاب کنید</option>';
    
    if (!buildingId) return;

    // CORRECTED: Find the building object by its ID to get its name
    const selectedBuilding = projectLocations.buildings.find(b => b.id == buildingId);
    if (!selectedBuilding) return;
    
    const buildingName = selectedBuilding.building_name;
    
    const filteredParts = projectLocations.parts.filter(p => p.building_name === buildingName);
    filteredParts.forEach(part => {
        partSelect.add(new Option(part.part_name, part.id));
    });
}



function loadInventory() {
    const warehouseId = document.getElementById('warehouseFilter').value;
    const params = new URLSearchParams();
    if (warehouseId) params.append('warehouse_id', warehouseId);
    
    fetch(`zirsazi_api.php?action=get_warehouse_inventory&${params}`)
    .then(res => res.json())
    .then(data => {
        const tbody = document.getElementById('inventoryTableBody');
        tbody.innerHTML = '';
        
        if (!data.success || !data.data || data.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center">موجودی یافت نشد</td></tr>';
            return;
        }

        data.data.forEach(item => {
            const stock = parseInt(item.current_stock);

            // --- CORRECTED LOGIC FOR STATUS AND COLOR ---
            let stockClass = '';
            let statusText = '';

            if (stock > 100) {
                stockClass = 'table-success'; // Green for high stock
                statusText = '<i class="bi bi-check-circle-fill"></i> موجودی کافی';
            } else if (stock > 0) {
                stockClass = 'table-warning'; // Yellow for normal stock
                statusText = '<i class="bi bi-box-seam"></i> در انبار';
            } else { // stock is 0 or less
                stockClass = 'table-secondary'; // Neutral grey for empty
                statusText = '<i class="bi bi-archive-fill"></i> خالی';
            }
            
            const row = `
                <tr class="${stockClass}">
                    <td>${item.warehouse_name}</td>
                    <td><strong>${item.material_type}</strong></td>
                    <td class="text-center"><h5 class="mb-0">${stock}</h5></td>
                    <td>${statusText}</td>
                </tr>`;
            tbody.insertAdjacentHTML('beforeend', row);
        });
    })
    .catch(error => {
        console.error('Error loading inventory:', error);
        document.getElementById('inventoryTableBody').innerHTML = '<tr><td colspan="4" class="text-center text-danger">خطا در بارگذاری موجودی.</td></tr>';
    });
}




function showBulkExitModal() {
    document.getElementById('bulkExitForm').reset();
    document.getElementById('bulkExitErrors').style.display = 'none';
    document.getElementById('bulkExitErrors').innerHTML = '';
    document.getElementById('bulkExitPreview').style.display = 'none';
    document.getElementById('bulkExitPreviewBody').innerHTML = '';
    
    // Use reliable native JS Date for default value
    const now = new Date();
    const nowPersian = new persianDate(now);
    
    // Set the visible Persian date for the user
    $('#bulkExitDatePicker').persianDatepicker('setDate', now.getTime());
    
    // Set the hidden Gregorian date for the API
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    document.getElementById('bulkExitDateHidden').value = `${year}-${month}-${day} ${hours}:${minutes}:00`;

    bulkExitModalInstance.show();
}

// Function to save the bulk exit
function saveBulkExit() {
    const form = document.getElementById('bulkExitForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const saveBtn = document.querySelector('#bulkExitModal .btn-primary');
    const spinner = document.getElementById('bulkExitSpinner');
    const errorDiv = document.getElementById('bulkExitErrors');
    saveBtn.disabled = true;
    spinner.classList.remove('d-none');
    errorDiv.style.display = 'none';

    const formData = new FormData(form);
    formData.append('action', 'add_bulk_warehouse_transaction');

    fetch('zirsazi_api.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            bulkExitModalInstance.hide();
            loadInventory();
            loadTransactions();
            alert(`تعداد ${result.count} تراکنش خروج با موفقیت ثبت شد.`);
        } else {
            // Show detailed validation errors
            if (result.errors && Array.isArray(result.errors)) {
                let errorHtml = `<strong>${result.message}</strong><ul>`;
                result.errors.forEach(err => { errorHtml += `<li>${err}</li>`; });
                errorHtml += '</ul>';
                errorDiv.innerHTML = errorHtml;
                errorDiv.style.display = 'block';
            } else {
                alert('خطا: ' + (result.message || 'خطایی ناشناخته'));
            }
        }
    })
    .catch(error => {
        console.error('Error saving bulk exit:', error);
        alert('خطا در ثبت تراکنش‌ها.');
    })
    .finally(() => {
        saveBtn.disabled = false;
        spinner.classList.add('d-none');
    });
}

function loadTransactions() {
    const warehouseId = document.getElementById('warehouseFilter').value;
    const params = new URLSearchParams();
    if (warehouseId) params.append('warehouse_id', warehouseId);
    
    const fromDate = document.getElementById('transFromDate')?.value;
    const toDate = document.getElementById('transToDate')?.value;
    if (fromDate) params.append('from_date', fromDate);
    if (toDate) params.append('to_date', toDate);
    
    fetch(`zirsazi_api.php?action=get_warehouse_transactions&${params}`)
    .then(res => res.json())
    .then(data => {
        const tbody = document.getElementById('transactionsTableBody');
        tbody.innerHTML = '';
        
        if (!data.success || !data.data || data.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center">تراکنشی یافت نشد</td></tr>';
            return;
        }

        data.data.forEach(item => {
            const typeClass = item.transaction_type === 'in' ? 'text-success' : 'text-danger';
            const typeText = item.transaction_type === 'in' ? 'ورود' : 'خروج';
            const locationText = (item.building_name && item.part_name) 
                ? `${item.building_name} - ${item.part_name}` 
                : (item.building_name || '-');
            
            // The row structure is the same, but now `formatNotes` will return HTML
            const row = `
                <tr>
                    <td>${item.transaction_date}</td>
                    <td class="${typeClass}"><strong>${typeText}</strong></td>
                    <td><strong>${item.material_type}</strong></td>
                    <td class="text-center"><strong>${item.quantity}</strong></td>
                    <td>${item.warehouse_name}</td>
                    <td>${item.contractor_name || '-'}</td>
                    <td>${locationText}</td>
                    <td>${item.user_name || '-'}</td>
                    <td class="notes-col">${formatNotes(item.notes)}</td>
                </tr>`;
            tbody.insertAdjacentHTML('beforeend', row);
        });
    });
}


function showTransactionModal(type) {
    document.getElementById('transactionForm').reset();
    document.getElementById('transactionType').value = type;
    
    const title = type === 'in' ? 'ورود کالا به انبار' : 'خروج کالا از انبار';
    document.getElementById('transactionModalTitle').textContent = title;
    
    document.getElementById('outFields').style.display = type === 'out' ? 'block' : 'none';
    
    // FIX for the TypeError
    const now = new Date();
    const nowPersian = new persianDate(now);
    
    // Set the visible value for the user
    document.getElementById('transactionDatePicker').value = nowPersian.format('YYYY/MM/DD HH:mm');
    
    // Set the hidden Gregorian value for the API
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    document.getElementById('transactionDateHidden').value = `${year}-${month}-${day} ${hours}:${minutes}:00`;
    
    transactionModalInstance.show();
}


function saveTransaction() {
    const form = document.getElementById('transactionForm');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const hiddenDate = document.getElementById('transactionDateHidden').value;
    if (!hiddenDate) {
        alert('لطفا تاریخ و ساعت تراکنش را انتخاب کنید');
        return;
    }
    
    const saveBtn = document.querySelector('#transactionModal .btn-primary');
    const spinner = document.getElementById('transSpinner');
    saveBtn.disabled = true;
    spinner.classList.remove('d-none');
    
    const formData = new FormData(form);
    formData.append('action', 'add_warehouse_transaction');

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
            transactionModalInstance.hide();
            loadInventory();
            loadTransactions();
            alert('تراکنش با موفقیت ثبت شد');
        } else { 
            alert('خطا: ' + (result.message || 'خطای ناشناخته')); 
        }
    })
    .catch(error => {
        console.error('Error saving transaction:', error);
        alert('خطا در ثبت تراکنش');
    })
    .finally(() => {
        saveBtn.disabled = false;
        spinner.classList.add('d-none');
    });
}

// Auto-refresh inventory every 30 seconds
setInterval(() => {
    const activeTab = document.querySelector('.tab-pane.active');
    if (activeTab && activeTab.id === 'inventory') {
        loadInventory();
    }
}, 30000);
</script>

<?php require_once __DIR__ . '/footer.php'; ?>