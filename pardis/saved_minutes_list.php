<?php
// public_html/pardis/saved_minutes_list.php
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

$pageTitle = "لیست صورتجلسات - پروژه دانشگاه خاتم پردیس";

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
try {
    $letter_pdo = getLetterTrackingDBConnection();
    $stmt = $letter_pdo->query("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    logError("Could not fetch companies for minutes filter: " . $e->getMessage());
    $companies = [];
}
?>

<link rel="stylesheet" href="/assets/css/persian-datepicker-dark.min.css">
<style>
    .badge-source { font-size: 0.8em; font-weight: bold; }
    
    /* Sortable column headers */
    .sortable-header {
        cursor: pointer;
        user-select: none;
        position: relative;
        padding-right: 25px !important;
    }
    
    .sortable-header:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }
    
    .sortable-header .sort-icon {
        position: absolute;
        left: 8px;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0.3;
        font-size: 0.8em;
    }
    
    .sortable-header.active .sort-icon {
        opacity: 1;
    }
    
    .sortable-header .sort-icon.asc::before {
        content: '▲';
    }
    
    .sortable-header .sort-icon.desc::before {
        content: '▼';
    }
    
    .sortable-header .sort-icon::before {
        content: '⇅';
    }
    
    /* Column filters */
    .column-filter {
        width: 100%;
        padding: 4px 8px;
        font-size: 0.85em;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        margin-top: 5px;
    }
    
    .filter-row th {
        padding: 8px !important;
        background-color: #f8f9fa;
    }
</style>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-file-text"></i> لیست صورتجلسات</h2>
        <div>
            <a href="meeting_minutes_form.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> ایجاد صورتجلسه جدید
            </a>
            <a href="forms_list.php" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i> بازگشت
            </a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> فیلتر و جستجو</h5>
        </div>
        <div class="card-body">
            <form id="filterForm">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">جستجو (شماره، موضوع، متن)</label>
                        <input type="text" class="form-control" name="search" id="searchBox" placeholder="جستجو در تمام محتوا...">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">منبع</label>
                        <select class="form-select" name="source">
                            <option value="">همه</option>
                            <option value="internal">داخلی</option>
                            <option value="external">خارجی</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">شرکت (برای خارجی‌ها)</label>
                        <select class="form-select" name="company_id">
                            <option value="">همه شرکت‌ها</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">از تاریخ:</label>
                        <input type="text" class="form-control persian-datepicker" name="from_date" id="fromDate">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">تا تاریخ:</label>
                        <input type="text" class="form-control persian-datepicker" name="to_date" id="toDate">
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-search"></i> اعمال فیلتر
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                        <i class="bi bi-x-circle"></i> پاک کردن
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3 id="totalCount">0</h3>
                    <p class="mb-0">کل صورتجلسات</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3 id="completedCount">0</h3>
                    <p class="mb-0">تکمیل شده</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h3 id="draftCount">0</h3>
                    <p class="mb-0">پیش‌نویس</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3 id="thisMonthCount">0</h3>
                    <p class="mb-0">ماه جاری</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Building Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h5 class="text-success">ساختمان کشاورزی</h5>
                    <h3 id="agCount">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h5 class="text-primary">ساختمان کتابخانه</h5>
                    <h3 id="lbCount">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-secondary">
                <div class="card-body text-center">
                    <h5 class="text-secondary">عمومی</h5>
                    <h3 id="gCount">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Meetings Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th class="sortable-header" data-sort="meeting_number">
                                شماره / شرکت
                                <span class="sort-icon"></span>
                            </th>
                            <th class="sortable-header" data-sort="agenda">
                                موضوع
                                <span class="sort-icon"></span>
                            </th>
                            <th class="sortable-header" data-sort="meeting_date">
                                تاریخ جلسه
                                <span class="sort-icon"></span>
                            </th>
                            <th class="sortable-header" data-sort="source">
                                منبع
                                <span class="sort-icon"></span>
                            </th>
                            <th class="sortable-header" data-sort="related_letter_number">
                                نامه مرتبط
                                <span class="sort-icon"></span>
                            </th>
                            <th class="sortable-header" data-sort="items_count">
                                موارد
                                <span class="sort-icon"></span>
                            </th>
                            <th class="sortable-header" data-sort="creator_name">
                                ایجاد کننده
                                <span class="sort-icon"></span>
                            </th>
                            <th>عملیات</th>
                        </tr>
                        <tr class="filter-row">
                            <th><input type="text" class="column-filter" data-column="meeting_number" placeholder="جستجو شماره..."></th>
                            <th><input type="text" class="column-filter" data-column="agenda" placeholder="جستجو موضوع..."></th>
                            <th><input type="text" class="column-filter" data-column="meeting_date" placeholder="جستجو تاریخ..."></th>
                            <th>
                                <select class="column-filter" data-column="source">
                                    <option value="">همه منابع</option>
                                    <option value="internal">داخلی</option>
                                    <option value="external">خارجی</option>
                                </select>
                            </th>
                            <th><input type="text" class="column-filter" data-column="related_letter_number" placeholder="جستجو نامه..."></th>
                            <th><input type="number" class="column-filter" data-column="items_count" placeholder="تعداد..."></th>
                            <th><input type="text" class="column-filter" data-column="creator_name" placeholder="جستجو ایجاد کننده..."></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="meetingsTableBody">
                        <!-- Content will be loaded by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">نمایش صورتجلسه</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="printMeeting()">
                    <i class="bi bi-printer"></i> چاپ
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/persian-date.min.js"></script>
<script src="/assets/js/persian-datepicker.min.js"></script>

<script>
let currentMeetingId = null;
let allMeetings = []; // Store all meetings for client-side filtering/sorting
let currentSort = { column: 'meeting_date', direction: 'desc' };
let columnFilters = {};

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Persian datepickers
    $('.persian-datepicker').persianDatepicker({
        format: 'YYYY/MM/DD',
        autoClose: true,
        calendar: { 
            persian: { 
                locale: 'fa',
                leapYearMode: 'astronomical'
            } 
        }
    });
    
    clearFilters(); 
    loadMeetingsList();
    loadStatistics();
    
    // Main filter form
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        loadMeetingsList();
    });
    
    // Column sorting
    document.querySelectorAll('.sortable-header').forEach(header => {
        header.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            toggleSort(column);
        });
    });
    
    // Column filtering with debounce
    document.querySelectorAll('.column-filter').forEach(filter => {
        let timeout;
        filter.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const column = this.getAttribute('data-column');
                const value = this.value.trim();
                
                if (value === '') {
                    delete columnFilters[column];
                } else {
                    columnFilters[column] = value;
                }
                
                applyFiltersAndSort();
            }, 300);
        });
    });
});

function toggleSort(column) {
    if (currentSort.column === column) {
        currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort.column = column;
        currentSort.direction = 'asc';
    }
    
    updateSortUI();
    applyFiltersAndSort();
}

function updateSortUI() {
    document.querySelectorAll('.sortable-header').forEach(header => {
        header.classList.remove('active');
        const icon = header.querySelector('.sort-icon');
        icon.className = 'sort-icon';
    });
    
    const activeHeader = document.querySelector(`[data-sort="${currentSort.column}"]`);
    if (activeHeader) {
        activeHeader.classList.add('active');
        const icon = activeHeader.querySelector('.sort-icon');
        icon.classList.add(currentSort.direction);
    }
}

function loadStatistics() {
    fetch('form_api.php?action=get_form_statistics')
    .then(res => res.json())
    .then(result => {
        if (result.success && result.data) {
            document.getElementById('totalCount').textContent = result.data.total_meetings || 0;
            document.getElementById('completedCount').textContent = result.data.completed_meetings || 0;
            document.getElementById('draftCount').textContent = result.data.draft_meetings || 0;
            document.getElementById('thisMonthCount').textContent = result.data.this_month_meetings || 0;
            if (result.data.by_building) {
                document.getElementById('agCount').textContent = result.data.by_building['AG'] || 0;
                document.getElementById('lbCount').textContent = result.data.by_building['LB'] || 0;
                document.getElementById('gCount').textContent = result.data.by_building['G'] || 0;
            }
        }
    });
}

function loadMeetingsList() {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData).toString();
    const tbody = document.getElementById('meetingsTableBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center p-5"><div class="spinner-border"></div></td></tr>';

    fetch('form_api.php?action=get_meeting_minutes_list&' + params)
    .then(res => res.json())
    .then(result => {
        if (!result.success || !result.data || result.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center p-4">موردی یافت نشد</td></tr>';
            allMeetings = [];
            return;
        }
        
        allMeetings = result.data;
        applyFiltersAndSort();
    });
}

function applyFiltersAndSort() {
    let filtered = [...allMeetings];
    
    // Apply column filters
    Object.keys(columnFilters).forEach(column => {
        const filterValue = columnFilters[column].toLowerCase();
        
        filtered = filtered.filter(meeting => {
            let cellValue = '';
            
            switch(column) {
                case 'meeting_number':
                    cellValue = meeting.meeting_number || '';
                    break;
                case 'agenda':
                    cellValue = meeting.agenda || '';
                    break;
                case 'meeting_date':
                    cellValue = meeting.meeting_date_jalali || '';
                    break;
                case 'source':
                    cellValue = meeting.source || '';
                    break;
                case 'related_letter_number':
                    cellValue = meeting.related_letter_number || '';
                    break;
                case 'items_count':
                    cellValue = (meeting.items_count || 0).toString();
                    break;
                case 'creator_name':
                    cellValue = meeting.creator_name || '';
                    break;
            }
            
            return cellValue.toLowerCase().includes(filterValue);
        });
    });
    
    // Apply sorting
    filtered.sort((a, b) => {
        let aVal, bVal;
        
        switch(currentSort.column) {
            case 'meeting_number':
                aVal = a.meeting_number || '';
                bVal = b.meeting_number || '';
                break;
            case 'agenda':
                aVal = a.agenda || '';
                bVal = b.agenda || '';
                break;
            case 'meeting_date':
                aVal = a.meeting_date || '';
                bVal = b.meeting_date || '';
                break;
            case 'source':
                aVal = a.source || '';
                bVal = b.source || '';
                break;
            case 'related_letter_number':
                aVal = a.related_letter_number || '';
                bVal = b.related_letter_number || '';
                break;
            case 'items_count':
                aVal = parseInt(a.items_count) || 0;
                bVal = parseInt(b.items_count) || 0;
                break;
            case 'creator_name':
                aVal = a.creator_name || '';
                bVal = b.creator_name || '';
                break;
            default:
                return 0;
        }
        
        let comparison = 0;
        if (aVal > bVal) comparison = 1;
        else if (aVal < bVal) comparison = -1;
        
        return currentSort.direction === 'asc' ? comparison : -comparison;
    });
    
    // Render table
    renderTable(filtered);
}

function renderTable(meetings) {
    const tbody = document.getElementById('meetingsTableBody');
    
    if (meetings.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center p-4">موردی یافت نشد</td></tr>';
        return;
    }
    
    tbody.innerHTML = '';
    meetings.forEach(meeting => {
        tbody.insertAdjacentHTML('beforeend', generateMeetingRow(meeting));
    });
}

function generateMeetingRow(meeting) {
    const statusBadge = getStatusBadge(meeting.status);
    const sourceBadge = meeting.source === 'internal'
        ? `<span class="badge bg-primary badge-source">داخلی</span> ${getBuildingBadge(meeting.building_prefix)}`
        : '<span class="badge bg-info badge-source">خارجی</span>';

    const mainIdentifier = `<strong>${meeting.meeting_number}</strong>`;

    let actions = '<div class="btn-group btn-group-sm" role="group">';

    if (meeting.is_handwritten == 1 && meeting.status === 'handwritten_pending') {
        actions += `<a href="meeting_minutes_print.php?id=${meeting.id}" class="btn btn-success" title="تکمیل تاریخ و چاپ فرم"><i class="bi bi-printer"></i> تکمیل و چاپ</a>`;
    } else if (meeting.source === 'internal') {
        actions += `<button class="btn btn-info" onclick="viewMeeting(${meeting.id})" title="مشاهده جزئیات"><i class="bi bi-eye"></i></button>`;
        actions += `<a href="meeting_minutes_form.php?id=${meeting.id}" class="btn btn-warning" title="ویرایش"><i class="bi bi-pencil"></i></a>`;
        
        if (meeting.status === 'draft') {
            actions += `<button class="btn btn-success" onclick="changeStatus(${meeting.id}, 'completed')" title="تکمیل کردن"><i class="bi bi-check-circle"></i></button>`;
        }
        
        if (meeting.pdf_file) {
            actions += `<a href="${meeting.pdf_file}" target="_blank" class="btn btn-danger" title="مشاهده PDF"><i class="bi bi-file-earmark-pdf"></i></a>`;
        } else if (meeting.status === 'completed') {
            actions += `<button class="btn btn-outline-danger" onclick="generatePDFForMeeting(${meeting.id}); event.stopPropagation();" title="تولید PDF"><i class="bi bi-file-pdf"></i></button>`;
        }
    } else {
        if(meeting.file_path) {
            actions += `<a href="${meeting.file_path}" target="_blank" class="btn btn-success" title="مشاهده فایل اسکن"><i class="bi bi-eye"></i></a>`;
        }
    }
    
    actions += `<button class="btn btn-danger" onclick="deleteMeeting(${meeting.id}, '${meeting.meeting_number}')" title="حذف"><i class="bi bi-trash"></i></button>`;
    actions += '</div>';

    return `
        <tr>
            <td>${mainIdentifier}</td>
            <td>${meeting.agenda || '-'}</td>
            <td>${meeting.meeting_date_jalali}</td>
            <td>${sourceBadge} ${statusBadge}</td>
            <td>${meeting.related_letter_number || '---'}</td>
            <td class="text-center"><span class="badge bg-secondary">${meeting.items_count || 0}</span></td>
            <td>${meeting.creator_name || '-'}</td>
            <td>${actions}</td>
        </tr>
    `;
}

function changeStatus(id, newStatus) {
    const statusText = newStatus === 'completed' ? 'تکمیل شده' : newStatus;
    if (!confirm(`آیا از تغییر وضعیت این صورتجلسه به "${statusText}" اطمینان دارید؟`)) return;

    const formData = new FormData();
    formData.append('action', 'update_meeting_status');
    formData.append('meeting_id', id);
    formData.append('new_status', newStatus);

    const button = event.target.closest('button');
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('form_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            loadMeetingsList();
            loadStatistics();
        } else {
            alert('❌ ' + result.message);
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-check-circle"></i>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در ارتباط با سرور.');
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-check-circle"></i>';
    });
}

function getStatusBadge(status) {
    const badges = {
        'draft': '<span class="badge bg-warning">پیش‌نویس</span>',
        'completed': '<span class="badge bg-success">تکمیل شده</span>',
        'approved': '<span class="badge bg-primary">تایید شده</span>',
        'archived': '<span class="badge bg-secondary">بایگانی شده</span>',
        'handwritten_pending': '<span class="badge bg-info">در انتظار بارگذاری</span>',
        'handwritten_uploaded': '<span class="badge bg-success">فایل بارگذاری شده</span>'
    };
    return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
}

function getBuildingBadge(prefix) {
    const badges = {
        'AG': '<small class="badge bg-success">AG</small>',
        'LB': '<small class="badge bg-primary">LB</small>',
        'G': '<small class="badge bg-secondary">G</small>'
    };
    return badges[prefix] || '';
}

function clearFilters() {
    document.getElementById('filterForm').reset();
    document.querySelectorAll('.column-filter').forEach(filter => filter.value = '');
    columnFilters = {};
    currentSort = { column: 'meeting_date', direction: 'desc' };
    updateSortUI();
    loadMeetingsList();
}

function viewMeeting(id) {
    currentMeetingId = id;
    
    fetch('form_api.php?action=get_meeting_minutes&id=' + id)
    .then(res => res.json())
    .then(result => {
        if (!result.success) {
            alert('خطا: ' + result.message);
            return;
        }
        
        const meeting = result.data;
        let html = '<div class="meeting-view">';
        
        html += '<div class="row mb-3">';
        html += `<div class="col-md-4"><strong>شماره:</strong> ${meeting.meeting_number}</div>`;
        html += `<div class="col-md-4"><strong>ساختمان:</strong> ${getBuildingName(meeting.building_prefix)}</div>`;
        html += `<div class="col-md-4"><strong>تاریخ:</strong> ${meeting.meeting_date_jalali} ${meeting.meeting_time || ''}</div>`;
        html += '</div>';
        html += `<div class="row mb-3"><div class="col-12"><strong>دستور جلسه:</strong> ${meeting.agenda || '-'}</div></div>`;

        if (meeting.attachments && meeting.attachments.length > 0) {
            html += '<h5 class="mt-4 border-top pt-3">فایل پیوست شده:</h5>';
            html += '<div class="list-group">';
            meeting.attachments.forEach(file => {
                html += `
                    <a href="${file.file_path}" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-file-earmark-arrow-down-fill me-2"></i>
                            <strong>${file.file_name}</strong>
                        </span>
                        <span class="badge bg-primary rounded-pill">${formatFileSize(file.file_size)}</span>
                    </a>`;
            });
            html += '</div>';
        }

        html += '<h5 class="mt-4">موارد مذاکره:</h5>';
        html += '<table class="table table-bordered table-sm">';
        html += '<thead><tr><th>ردیف</th><th>پیگیری کننده</th><th>شرح</th><th>مهلت</th></tr></thead>';
        html += '<tbody>';
        
        if (meeting.items && meeting.items.length > 0) {
            meeting.items.forEach((item, index) => {
                html += `<tr><td>${index + 1}</td><td>${item.follower || '-'}</td><td>${(item.description || '-').replace(/\n/g, '<br>')}</td><td>${item.deadline_jalali || '-'}</td></tr>`;
            });
        } else {
            html += '<tr><td colspan="4" class="text-center">موردی ثبت نشده</td></tr>';
        }
        html += '</tbody></table></div>';
        
        document.getElementById('viewModalBody').innerHTML = html;

        const modalFooter = document.querySelector('#viewModal .modal-footer');
        let footerHtml = '';

        if (meeting.attachments && meeting.attachments.length > 0) {
            footerHtml += `
                <a href="${meeting.attachments[0].file_path}" target="_blank" class="btn btn-primary">
                    <i class="bi bi-printer"></i> مشاهده و چاپ فایل اصلی
                </a>`;
        } else {
            footerHtml += `
                <button type="button" class="btn btn-primary" onclick="printCurrentMeeting()">
                    <i class="bi bi-printer"></i> چاپ
                </button>`;
        }
        footerHtml += '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>';
        modalFooter.innerHTML = footerHtml;
        
        new bootstrap.Modal(document.getElementById('viewModal')).show();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در بارگذاری صورتجلسه');
    });
}

function getBuildingName(prefix) {
    const names = {
        'AG': 'ساختمان کشاورزی',
        'LB': 'ساختمان کتابخانه',
        'G': 'عمومی'
    };
    return names[prefix] || prefix;
}

function deleteMeeting(id, meetingNumber) {
    if (!confirm('آیا از حذف صورتجلسه ' + meetingNumber + ' اطمینان دارید؟')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_meeting_minutes');
    formData.append('id', id);
    
    fetch('form_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            alert('✅ ' + result.message);
            loadMeetingsList();
            loadStatistics();
        } else {
            alert('❌ ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در حذف صورتجلسه');
    });
}

function printMeeting(id) {
    window.open('meeting_minutes_print.php?id=' + id, '_blank');
}

function printCurrentMeeting() {
    if (currentMeetingId) {
        printMeeting(currentMeetingId);
    }
}

function generatePDFForMeeting(meetingId) {
    if (!confirm('آیا می‌خواهید برای این صورتجلسه فایل PDF تولید کنید؟')) return;
    
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> در حال تولید...';
    
    fetch('form_api.php?action=generate_pdf&id=' + meetingId)
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show';
            alertDiv.style.position = 'fixed';
            alertDiv.style.top = '80px';
            alertDiv.style.right = '20px';
            alertDiv.style.zIndex = '9999';
            alertDiv.style.minWidth = '400px';
            alertDiv.innerHTML = `
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
                <h6><i class="bi bi-check-circle-fill"></i> ${result.message}</h6>
                <div class="d-flex gap-2 mt-2">
                    <a href="${result.pdf_url}" target="_blank" class="btn btn-sm btn-danger">
                        <i class="bi bi-file-pdf-fill"></i> مشاهده PDF
                    </a>
                    <a href="${result.pdf_url}" download class="btn btn-sm btn-success">
                        <i class="bi bi-download"></i> دانلود
                    </a>
                </div>
            `;
            document.body.appendChild(alertDiv);
            loadMeetingsList();
            setTimeout(() => {
                if (alertDiv.parentElement) alertDiv.remove();
            }, 10000);
        } else {
            alert('❌ ' + result.message);
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در تولید PDF: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

function viewUploadedFiles(meetingNumber) {
    fetch('form_api.php?action=get_uploaded_files&meeting_number=' + encodeURIComponent(meetingNumber))
    .then(res => res.json())
    .then(result => {
        if (!result.success || !result.data || result.data.length === 0) {
            alert('هیچ فایلی یافت نشد');
            return;
        }
        
        let html = '<div class="list-group">';
        result.data.forEach(file => {
            const fileIcon = file.file_type === 'application/pdf' ? 'file-earmark-pdf' : 'file-earmark-image';
            const fileColor = file.file_type === 'application/pdf' ? 'text-danger' : 'text-primary';
            
            html += `
                <a href="${file.file_path}" target="_blank" class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-${fileIcon} ${fileColor}" style="font-size: 20px; margin-left: 10px;"></i>
                            <strong>${file.file_name}</strong>
                            <br>
                            <small class="text-muted">بارگذاری: ${file.uploaded_at}</small>
                        </div>
                        <div>
                            <span class="badge bg-secondary">${formatFileSize(file.file_size)}</span>
                        </div>
                    </div>
                </a>
            `;
        });
        html += '</div>';
        
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">فایل‌های بارگذاری شده - ${meetingNumber}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">${html}</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        modal.addEventListener('hidden.bs.modal', () => modal.remove());
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در بارگذاری فایل‌ها');
    });
}

function formatFileSize(bytes) {
    if (!bytes || bytes == 0) return '0 B';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
}   
</script>

<?php require_once __DIR__ . '/footer.php'; ?>