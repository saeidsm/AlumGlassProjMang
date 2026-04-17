<?php
// public_html/pardis/zirsazi_status.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'superuser', 'user', 'supervisor', 'planner'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

$expected_project_key = 'pardis';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    logError("User ID " . ($_SESSION['user_id'] ?? 'N/A') . " tried to access pardis project page without correct session context.");
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}

$user_role = $_SESSION['role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? 0;
$user_name = 'کاربر';

try {
    $commonPdo = getCommonDBConnection();
    $stmt = $commonPdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $first_name = trim($user['first_name'] ?? '');
        $last_name = trim($user['last_name'] ?? '');
        if (!empty($first_name) && !empty($last_name)) {
            $user_name = $first_name . ' ' . $last_name;
        } elseif (!empty($first_name)) {
            $user_name = $first_name;
        } elseif (!empty($last_name)) {
            $user_name = $last_name;
        }
    }
} catch (Exception $e) {
    logError("Error fetching user name: " . $e->getMessage());
}

$pageTitle = "گزارش زیرسازی - پروژه دانشگاه خاتم پردیس";

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

$is_admin = in_array($_SESSION['role'] ?? 'user', ['admin', 'superuser', 'supervisor']);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-grid-3x3-gap"></i> داشبورد وضعیت زیرسازی</h2>
        <div>
            <?php if ($is_admin): ?>
            <button class="btn btn-primary" onclick="showAddMaterialModal()">
                <i class="bi bi-plus-circle"></i> افزودن متریال جدید
            </button>
            <?php endif; ?>
            <a href="packing_lists.php" class="btn btn-info">
                <i class="bi bi-file-earmark-text"></i> لیست بارنامه‌ها
            </a>
            <a href="warehouse_management.php" class="btn btn-success">
                <i class="bi bi-box-seam"></i> مدیریت انبار
            </a>
            <a href="select_print_report.php" class="btn btn-warning">
                <i class="bi bi-printer"></i> پرینت گزارشات
            </a>
            <a href="zirsazi_print.php" class="btn btn-warning">
                <i class="bi bi-printer"></i> پرینت این صفحه
            </a>
                        <a href="zirsazi_export_excel.php" class="btn btn-warning">
                <i class="bi bi-printer"></i> اکسل این صفحه
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-2">
                    <label class="form-label"><strong>فیلتر ساختمان:</strong></label>
                    <select class="form-select" id="buildingFilter" onchange="filterByBuilding()">
                        <option value="">همه ساختمان‌ها</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><strong>فیلتر قسمت:</strong></label>
                    <select class="form-select" id="partFilter" onchange="renderBuildingTables()">
                        <option value="">همه قسمت‌ها</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><strong>فیلتر Model:</strong></label>
                    <select class="form-select" id="modelFilter" onchange="renderBuildingTables()">
                        <option value="">همه Model‌ها</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><strong>فیلتر Type:</strong></label>
                    <select class="form-select" id="typeFilter" onchange="renderBuildingTables()">
                        <option value="">همه Type‌ها</option>
                    </select>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-secondary btn-sm" onclick="clearAllFilters()">
                        <i class="bi bi-x-circle"></i> پاک کردن همه فیلترها
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="buildingTables"></div>
</div>

<!-- SVG Viewer Modal -->
<div class="modal fade" id="svgModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="svgModalTitle"></h5>
                <button type="button" class="btn-close" onclick="closeSvgModal()"></button>
            </div>
            <div class="modal-body p-0" style="position: relative; height: 70vh; overflow: hidden; background: #f8f9fa;">
                <div style="position: absolute; top: 10px; right: 10px; z-index: 1000; display: flex; gap: 5px;">
                    <button class="btn btn-sm btn-dark" onclick="zoomIn()" title="بزرگنمایی">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                    <button class="btn btn-sm btn-dark" onclick="zoomOut()" title="کوچک‌نمایی">
                        <i class="bi bi-dash-lg"></i>
                    </button>
                    <button class="btn btn-sm btn-dark" onclick="resetZoom()" title="بازنشانی">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <span class="btn btn-sm btn-secondary" id="zoomLevel">100%</span>
                </div>
                <div id="svgViewport" style="width: 100%; height: 100%; overflow: auto; cursor: grab;">
                    <div id="svgContainer" style="display: flex; justify-content: center; align-items: center; min-width: 100%; min-height: 100%;">
                        <img id="svgModalImage" src="" style="max-width: none; transition: none; transform-origin: center center;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeSvgModal()">بستن</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Material Modal -->
<div class="modal fade" id="addMaterialModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن متریال جدید</h5>
                <button type="button" class="btn-close" onclick="closeAddMaterialModal()"></button>
            </div>
            <div class="modal-body">
                <form id="addMaterialForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Model</label>
                            <input type="text" class="form-control" name="model" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type</label>
                            <input type="text" class="form-control" name="type" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">PART 1&2</label>
                            <input type="number" class="form-control" name="p12" value="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">PART 1</label>
                            <input type="number" class="form-control" name="p1" value="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">PART 2</label>
                            <input type="number" class="form-control" name="p2" value="0" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">PART 3</label>
                            <input type="number" class="form-control" name="p3" value="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">PART 4</label>
                            <input type="number" class="form-control" name="p4" value="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">PART 5</label>
                            <input type="number" class="form-control" name="p5" value="0" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">PART 6</label>
                            <input type="number" class="form-control" name="p6" value="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Weight (kg/piece)</label>
                            <input type="number" class="form-control" name="weight" step="0.001" value="0" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ساختمان</label>
                            <select class="form-select" name="building_id" id="buildingSelect" onchange="loadPartsByBuilding()" required>
                                <option value="">انتخاب کنید</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">قسمت</label>
                            <select class="form-select" name="part_id" id="partSelect" required>
                                <option value="">انتخاب کنید</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddMaterialModal()">لغو</button>
                <button type="button" class="btn btn-primary" onclick="saveMaterial()">ذخیره</button>
            </div>
        </div>
    </div>
</div>

<script>
const isAdmin = <?php echo json_encode($is_admin); ?>;
let currentScale = 1;
let isPanning = false;
let startX = 0, startY = 0, scrollLeft = 0, scrollTop = 0;
let svgModalInstance = null;
let addMaterialModalInstance = null;
let projectLocations = { buildings: [], parts: [] };
let buildingToPartIdMap = {};
let tableData = [];
let sortColumn = null;
let sortDirection = 'asc';
let currentBuildingSort = {}; // Track sort for each building separately

document.addEventListener('DOMContentLoaded', function() {
    loadProjectLocations();
    initializeSvgModal();
    
    const svgModalElement = document.getElementById('svgModal');
    if (svgModalElement) {
        svgModalInstance = new bootstrap.Modal(svgModalElement);
    }
    
    const addModalElement = document.getElementById('addMaterialModal');
    if (addModalElement) {
        addMaterialModalInstance = new bootstrap.Modal(addModalElement);
    }
    
    svgModalElement?.addEventListener('hidden.bs.modal', function() {
        currentScale = 1;
        updateZoom();
        const viewport = document.getElementById('svgViewport');
        viewport.scrollLeft = 0;
        viewport.scrollTop = 0;
    });
});

function closeSvgModal() {
    if (svgModalInstance) svgModalInstance.hide();
}
function closeAddMaterialModal() {
    if (addMaterialModalInstance) addMaterialModalInstance.hide();
}

function loadProjectLocations() {
    fetch('zirsazi_api.php?action=get_project_locations')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            projectLocations = data;
            
            data.parts.forEach(part => {
                if (!buildingToPartIdMap[part.building_name]) {
                    buildingToPartIdMap[part.building_name] = [];
                }
                buildingToPartIdMap[part.building_name].push(part);
            });
            
            const buildingSelect = document.getElementById('buildingSelect');
            buildingSelect.innerHTML = '<option value="">انتخاب کنید</option>';
            
            const buildingFilter = document.getElementById('buildingFilter');
            buildingFilter.innerHTML = '<option value="">همه ساختمان‌ها</option>';
            
            data.buildings.forEach(building => {
                const option = document.createElement('option');
                option.value = building.id;
                option.textContent = building.building_name;
                buildingSelect.appendChild(option);
                
                const filterOption = option.cloneNode(true);
                buildingFilter.appendChild(filterOption);
            });
            
            loadStatusData();
        }
    })
    .catch(error => console.error('Error loading project locations:', error));
}

function loadPartsByBuilding() {
    const buildingId = document.getElementById('buildingSelect').value;
    const partSelect = document.getElementById('partSelect');
    partSelect.innerHTML = '<option value="">انتخاب کنید</option>';

    if (!buildingId) return;

    const selectedBuilding = projectLocations.buildings.find(b => b.id == buildingId);
    if (!selectedBuilding) return;

    const buildingName = selectedBuilding.building_name;
    
    const filteredParts = buildingToPartIdMap[buildingName] || [];
    filteredParts.forEach(part => {
        const option = document.createElement('option');
        option.value = part.id;
        option.textContent = part.part_name;
        partSelect.appendChild(option);
    });
}

function filterByBuilding() {
    const buildingId = document.getElementById('buildingFilter').value;
    const partFilter = document.getElementById('partFilter');
    
    partFilter.innerHTML = '<option value="">همه قسمت‌ها</option>';
    
    if (buildingId) {
        const selectedBuilding = projectLocations.buildings.find(b => b.id == buildingId);
        if (selectedBuilding) {
            const buildingName = selectedBuilding.building_name;
            const filteredParts = buildingToPartIdMap[buildingName] || [];
            
            filteredParts.forEach(part => {
                const option = document.createElement('option');
                option.value = part.id;
                option.textContent = part.part_name;
                partFilter.appendChild(option);
            });
        }
    } else {
        // Show all parts with building names
        projectLocations.parts.forEach(part => {
            const option = document.createElement('option');
            option.value = part.id;
            option.textContent = `${part.building_name} - ${part.part_name}`;
            partFilter.appendChild(option);
        });
    }
    
    renderBuildingTables();
}

function clearAllFilters() {
    document.getElementById('buildingFilter').value = '';
    document.getElementById('partFilter').value = '';
    document.getElementById('modelFilter').value = '';
    document.getElementById('typeFilter').value = '';
    renderBuildingTables();
}

function loadStatusData() {
    fetch('zirsazi_api.php?action=get_zirsazi_data')
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            document.getElementById('buildingTables').innerHTML = 
                '<div class="alert alert-danger">خطا در بارگذاری اطلاعات</div>';
            return;
        }

        tableData = data.data;
        populateFilterDropdowns();
        renderBuildingTables();
    })
    .catch(error => {
        console.error('Error loading status data:', error);
        document.getElementById('buildingTables').innerHTML = 
            '<div class="alert alert-danger">خطا در بارگذاری اطلاعات</div>';
    });
}

function populateFilterDropdowns() {
    // Populate Model filter
    const models = [...new Set(tableData.map(item => item.model).filter(m => m))].sort();
    const modelFilter = document.getElementById('modelFilter');
    modelFilter.innerHTML = '<option value="">همه Model‌ها</option>';
    models.forEach(model => {
        const option = document.createElement('option');
        option.value = model;
        option.textContent = model;
        modelFilter.appendChild(option);
    });
    
    // Populate Type filter
    const types = [...new Set(tableData.map(item => item.type).filter(t => t))].sort();
    const typeFilter = document.getElementById('typeFilter');
    typeFilter.innerHTML = '<option value="">همه Type‌ها</option>';
    types.forEach(type => {
        const option = document.createElement('option');
        option.value = type;
        option.textContent = type;
        typeFilter.appendChild(option);
    });
    
    // Populate Part filter with all parts
    const partFilter = document.getElementById('partFilter');
    partFilter.innerHTML = '<option value="">همه قسمت‌ها</option>';
    projectLocations.parts.forEach(part => {
        const option = document.createElement('option');
        option.value = part.id;
        option.textContent = `${part.building_name} - ${part.part_name}`;
        partFilter.appendChild(option);
    });
}

function renderBuildingTables() {
    const container = document.getElementById('buildingTables');
    container.innerHTML = '';
    
    const buildingFilterValue = document.getElementById('buildingFilter').value;
    const partFilterValue = document.getElementById('partFilter').value;
    const modelFilterValue = document.getElementById('modelFilter').value;
    const typeFilterValue = document.getElementById('typeFilter').value;
    
    // Filter data
    let filteredData = tableData;
    
    if (buildingFilterValue) {
        filteredData = filteredData.filter(item => item.building_id == buildingFilterValue);
    }
    if (partFilterValue) {
        filteredData = filteredData.filter(item => item.part_id == partFilterValue);
    }
    if (modelFilterValue) {
        filteredData = filteredData.filter(item => item.model === modelFilterValue);
    }
    if (typeFilterValue) {
        filteredData = filteredData.filter(item => item.type === typeFilterValue);
    }
    
    // Group by building
    const groupedByBuilding = {};
    filteredData.forEach(item => {
        const buildingName = item.building || 'بدون ساختمان';
        if (!groupedByBuilding[buildingName]) {
            groupedByBuilding[buildingName] = [];
        }
        groupedByBuilding[buildingName].push(item);
    });
    
    // Render each building
    Object.keys(groupedByBuilding).sort().forEach(buildingName => {
        const buildingData = groupedByBuilding[buildingName];
        
        const card = document.createElement('div');
        card.className = 'card mb-4';
        
        const cardHeader = document.createElement('div');
        cardHeader.className = 'card-header bg-primary text-white';
        cardHeader.innerHTML = `<h4 class="mb-0"><i class="bi bi-building"></i> ${buildingName} <span class="badge bg-light text-dark">${buildingData.length} items</span></h4>`;
        
        const cardBody = document.createElement('div');
        cardBody.className = 'card-body';
        
        const tableResponsive = document.createElement('div');
        tableResponsive.className = 'table-responsive';
        
        const table = document.createElement('table');
        table.className = 'table table-bordered table-hover';
        table.style.fontSize = '0.8rem';
        
        table.innerHTML = `
            <thead class="table-light text-center">
                <tr>
                    <th rowspan="2" class="sortable" onclick="sortTableData('model')">Model</th>
                    <th rowspan="2" class="sortable" onclick="sortTableData('type')">Type</th>
                    <th rowspan="2">Plan</th>
                    <th colspan="7">Ordered Qty</th>
                    <th rowspan="2" class="sortable" onclick="sortTableData('total_ordered')">Total Ordered</th>
                    <th rowspan="2" class="sortable" onclick="sortTableData('total_received')">Total Received</th>
                    <th rowspan="2" class="sortable" onclick="sortTableData('warehouse_stock')">موجودی انبار</th>
                    <th rowspan="2" class="sortable" onclick="sortTableData('remaining')">Remaining</th>
                    <th rowspan="2" style="width: 10%;">Progress</th>
                    <th rowspan="2" class="sortable" onclick="sortTableData('unit_weight')">Weight (kg/pc)</th>
                    <th rowspan="2">Total Weight (kg)</th>
                    <th rowspan="2" class="sortable" onclick="sortTableData('part')">Part</th>
                    ${isAdmin ? '<th rowspan="2">Action</th>' : ''}
                </tr>
                <tr>
                    <th class="sortable" onclick="sortTableData('part1_2_qty')">PART 1&2</th>
                    <th class="sortable" onclick="sortTableData('part1_qty')">PART 1</th>
                    <th class="sortable" onclick="sortTableData('part2_qty')">PART 2</th>
                    <th class="sortable" onclick="sortTableData('part3_qty')">PART 3</th>
                    <th class="sortable" onclick="sortTableData('part4_qty')">PART 4</th>
                    <th class="sortable" onclick="sortTableData('part5_qty')">PART 5</th>
                    <th class="sortable" onclick="sortTableData('part6_qty')">PART 6</th>
                </tr>
            </thead>
            <tbody></tbody>
        `;
        
        const tbody = table.querySelector('tbody');
        
        buildingData.forEach(item => {
            const part12 = parseInt(item.part1_2_qty || 0);
            const part1 = parseInt(item.part1_qty || 0);
            const part2 = parseInt(item.part2_qty || 0);
            
            const totalOrdered = part12 + part1 + part2 + parseInt(item.part3_qty || 0) + 
                                parseInt(item.part4_qty || 0) + parseInt(item.part5_qty || 0) + 
                                parseInt(item.part6_qty || 0);
            
            const totalReceived = parseInt(item.total_received || 0);
            const warehouseStock = parseInt(item.warehouse_stock || 0);
            const remaining = totalOrdered - totalReceived;
            const progress = totalOrdered > 0 ? ((totalReceived / totalOrdered) * 100).toFixed(0) : 0;
            const totalWeight = (totalReceived * parseFloat(item.unit_weight || 0)).toFixed(2);
            
            const readonly = isAdmin ? '' : 'readonly disabled';
            
            const partOptions = (buildingToPartIdMap[buildingName] || []).map(p => 
                `<option value="${p.id}" ${item.part_id == p.id ? 'selected' : ''}>${p.part_name}</option>`
            ).join('');

            const adminButton = isAdmin ? `<td><button class="btn btn-sm btn-success" onclick="saveBoqRow(${item.id})">Save</button></td>` : '';

            const row = `
                <tr id="row-${item.id}" data-building-id="${item.building_id || ''}" data-part-id="${item.part_id || ''}">
                    <td>${item.model || '-'}</td>
                    <td><strong>${item.type}</strong></td>
                    <td class="text-center"><img src="zsvg/${item.type}.svg" style="height:20px; cursor:pointer;" onclick="showSvg('${item.type}')" onerror="this.style.display='none'"></td>
                    <td><input type="number" class="form-control form-control-sm editable-cell" value="${part12}" data-col="p12" ${readonly}></td>
                    <td><input type="number" class="form-control form-control-sm editable-cell" value="${part1}" data-col="p1" ${readonly}></td>
                    <td><input type="number" class="form-control form-control-sm editable-cell" value="${part2}" data-col="p2" ${readonly}></td>
                    <td><input type="number" class="form-control form-control-sm editable-cell" value="${item.part3_qty || 0}" data-col="p3" ${readonly}></td>
                    <td><input type="number" class="form-control form-control-sm editable-cell" value="${item.part4_qty || 0}" data-col="p4" ${readonly}></td>
                    <td><input type="number" class="form-control form-control-sm editable-cell" value="${item.part5_qty || 0}" data-col="p5" ${readonly}></td>
                    <td><input type="number" class="form-control form-control-sm editable-cell" value="${item.part6_qty || 0}" data-col="p6" ${readonly}></td>
                    <td class="text-center"><strong>${totalOrdered}</strong></td>
                    <td class="text-center table-info"><strong>${totalReceived}</strong></td>
                    <td class="text-center table-primary"><strong>${warehouseStock}</strong></td>
                    <td class="text-center ${remaining > 0 ? 'text-danger' : 'text-success'}"><strong>${remaining}</strong></td>
                    <td>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar ${progress == 100 ? 'bg-success' : progress > 50 ? 'bg-info' : 'bg-warning'}" 
                                 role="progressbar" style="width: ${progress}%">${progress}%</div>
                        </div>
                    </td>
                    <td><input type="number" class="form-control form-control-sm editable-cell" value="${item.unit_weight || 0}" data-col="weight" step="0.001" ${readonly}></td>
                    <td class="text-center">${totalWeight}</td>
                    <td style="min-width:120px;">
                        ${isAdmin ? `
                            <select class="form-select form-select-sm" id="part-select-${item.id}" data-col="part" ${readonly} style="font-size:0.7rem;padding:2px;">
                                <option value="">-</option>
                                ${partOptions}
                            </select>
                        ` : `<small>${item.part || '-'}</small>`}
                    </td>
                    ${adminButton}
                </tr>`;
            tbody.insertAdjacentHTML('beforeend', row);
        });
        
        tableResponsive.appendChild(table);
        cardBody.appendChild(tableResponsive);
        card.appendChild(cardHeader);
        card.appendChild(cardBody);
        container.appendChild(card);
    });
    
    if (Object.keys(groupedByBuilding).length === 0) {
        container.innerHTML = '<div class="alert alert-info">هیچ داده‌ای یافت نشد</div>';
    }
}

function sortTableData(column) {
    // Toggle direction if same column, else set to ascending
    if (sortColumn === column) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = column;
        sortDirection = 'asc';
    }
    
    // Sort the main tableData array
    tableData.sort((a, b) => {
        let aVal, bVal;
        
        // Handle calculated columns
        if (column === 'total_ordered') {
            aVal = parseInt(a.part1_2_qty || 0) + parseInt(a.part1_qty || 0) + parseInt(a.part2_qty || 0) + 
                   parseInt(a.part3_qty || 0) + parseInt(a.part4_qty || 0) + 
                   parseInt(a.part5_qty || 0) + parseInt(a.part6_qty || 0);
            bVal = parseInt(b.part1_2_qty || 0) + parseInt(b.part1_qty || 0) + parseInt(b.part2_qty || 0) + 
                   parseInt(b.part3_qty || 0) + parseInt(b.part4_qty || 0) + 
                   parseInt(b.part5_qty || 0) + parseInt(b.part6_qty || 0);
        } else if (column === 'remaining') {
            const aTotalOrdered = parseInt(a.part1_2_qty || 0) + parseInt(a.part1_qty || 0) + parseInt(a.part2_qty || 0) + 
                                  parseInt(a.part3_qty || 0) + parseInt(a.part4_qty || 0) + 
                                  parseInt(a.part5_qty || 0) + parseInt(a.part6_qty || 0);
            const bTotalOrdered = parseInt(b.part1_2_qty || 0) + parseInt(b.part1_qty || 0) + parseInt(b.part2_qty || 0) + 
                                  parseInt(b.part3_qty || 0) + parseInt(b.part4_qty || 0) + 
                                  parseInt(b.part5_qty || 0) + parseInt(b.part6_qty || 0);
            aVal = aTotalOrdered - parseInt(a.total_received || 0);
            bVal = bTotalOrdered - parseInt(b.total_received || 0);
        } else {
            aVal = a[column] || '';
            bVal = b[column] || '';
        }
        
        // Handle numeric columns
        if (['part1_2_qty', 'part1_qty', 'part2_qty', 'part3_qty', 'part4_qty', 'part5_qty', 'part6_qty',
             'total_received', 'warehouse_stock', 'unit_weight', 'total_ordered', 'remaining'].includes(column)) {
            aVal = parseFloat(aVal) || 0;
            bVal = parseFloat(bVal) || 0;
        } else {
            aVal = String(aVal).toLowerCase();
            bVal = String(bVal).toLowerCase();
        }
        
        if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
        if (aVal > bVal) return sortDirection === 'asc' ? 1 : -1;
        return 0;
    });
    
    // Re-render with sorted data
    renderBuildingTables();
    
    // Update sort indicators on all tables
    document.querySelectorAll('.sortable').forEach(header => {
        header.classList.remove('asc', 'desc');
    });
    document.querySelectorAll('.sortable').forEach(header => {
        if (header.textContent.trim() === getColumnLabel(column)) {
            header.classList.add(sortDirection);
        }
    });
}

function getColumnLabel(column) {
    const labels = {
        'model': 'Model',
        'type': 'Type',
        'part1_2_qty': 'PART 1&2',
        'part1_qty': 'PART 1',
        'part2_qty': 'PART 2',
        'part3_qty': 'PART 3',
        'part4_qty': 'PART 4',
        'part5_qty': 'PART 5',
        'part6_qty': 'PART 6',
        'total_ordered': 'Total Ordered',
        'total_received': 'Total Received',
        'warehouse_stock': 'موجودی انبار',
        'remaining': 'Remaining',
        'unit_weight': 'Weight (kg/pc)',
        'part': 'Part'
    };
    return labels[column] || column;
}

function showAddMaterialModal() {
    document.getElementById('addMaterialForm').reset();
    document.getElementById('partSelect').innerHTML = '<option value="">انتخاب کنید</option>';
    addMaterialModalInstance.show();
}

function saveMaterial() {
    const form = document.getElementById('addMaterialForm');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('action', 'add_material');

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
            addMaterialModalInstance.hide();
            loadStatusData();
            alert('متریال با موفقیت اضافه شد');
        } else { 
            alert('خطا: ' + (result.message || 'خطای ناشناخته')); 
        }
    })
    .catch(error => {
        console.error('Error saving material:', error);
        alert('خطا در ذخیره متریال');
    });
}

function saveBoqRow(id) {
    const row = document.getElementById(`row-${id}`);
    const inputs = row.querySelectorAll('input[type="number"]');
    const selects = row.querySelectorAll('select');
    
    const data = new FormData();
    data.append('action', 'update_boq_item');
    data.append('id', id);
    
    inputs.forEach(input => {
        data.append(input.dataset.col, input.value || 0);
    });

    selects.forEach(select => {
        const col = select.dataset.col;
        if (col === 'part') {
            data.append('part_id', select.value || '');
            row.dataset.partId = select.value;
        }
    });
    
    // Keep building_id from row data
    data.append('building_id', row.dataset.buildingId || '');

    fetch('zirsazi_api.php', { 
        method: 'POST', 
        body: data 
    })
    .then(res => {
        if (!res.ok) throw new Error('Network response was not ok');
        return res.json();
    })
    .then(result => {
        if(result.success) {
            row.classList.add('table-success');
            setTimeout(() => {
                row.classList.remove('table-success');
                loadStatusData();
            }, 1500);
        } else { 
            alert('خطا در ذخیره: ' + (result.message || 'خطای ناشناخته')); 
        }
    })
    .catch(error => {
        console.error('Error saving BOQ row:', error);
        alert('خطا در ذخیره اطلاعات');
    });
}

function showSvg(type) {
    document.getElementById('svgModalTitle').innerText = type;
    document.getElementById('svgModalImage').src = `zsvg/${type}.svg`;
    currentScale = 1;
    updateZoom();
    if (svgModalInstance) {
        svgModalInstance.show();
    }
}

function initializeSvgModal() {
    const viewport = document.getElementById('svgViewport');
    const svgImage = document.getElementById('svgModalImage');
    
    if(!viewport) return;

    viewport.addEventListener('mousedown', function(e) {
        if (e.target === viewport || e.target === svgImage || e.target.id === 'svgContainer') {
            isPanning = true;
            viewport.style.cursor = 'grabbing';
            startX = e.pageX - viewport.offsetLeft;
            startY = e.pageY - viewport.offsetTop;
            scrollLeft = viewport.scrollLeft;
            scrollTop = viewport.scrollTop;
            e.preventDefault();
        }
    });
    
    viewport.addEventListener('mousemove', function(e) {
        if (!isPanning) return;
        e.preventDefault();
        const x = e.pageX - viewport.offsetLeft;
        const y = e.pageY - viewport.offsetTop;
        viewport.scrollLeft = scrollLeft - (x - startX);
        viewport.scrollTop = scrollTop - (y - startY);
    });
    
    viewport.addEventListener('mouseup', () => { isPanning = false; viewport.style.cursor = 'grab'; });
    viewport.addEventListener('mouseleave', () => { isPanning = false; viewport.style.cursor = 'grab'; });
    
    viewport.addEventListener('wheel', function(e) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        currentScale = Math.max(0.5, Math.min(5, currentScale + delta));
        updateZoom();
    }, { passive: false });
}

function zoomIn() {
    currentScale = Math.min(5, currentScale + 0.25);
    updateZoom();
}

function zoomOut() {
    currentScale = Math.max(0.5, currentScale - 0.25);
    updateZoom();
}

function resetZoom() {
    currentScale = 1;
    updateZoom();
    const viewport = document.getElementById('svgViewport');
    viewport.scrollLeft = 0;
    viewport.scrollTop = 0;
}

function updateZoom() {
    const svgImage = document.getElementById('svgModalImage');
    const container = document.getElementById('svgContainer');
    if(!svgImage || !container) return;

    svgImage.style.transform = `scale(${currentScale})`;
    
    if (svgImage.naturalWidth > 0) {
        const scaledWidth = svgImage.naturalWidth * currentScale;
        const scaledHeight = svgImage.naturalHeight * currentScale;
        container.style.width = Math.max(scaledWidth, container.parentElement.offsetWidth) + 'px';
        container.style.height = Math.max(scaledHeight, container.parentElement.offsetHeight) + 'px';
    }
    
    document.getElementById('zoomLevel').textContent = Math.round(currentScale * 100) + '%';
}
</script>

<style>
.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    padding-right: 20px !important;
}

.sortable:hover {
    background-color: #e9ecef;
}

.sortable::after {
    content: '⇅';
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0.3;
    font-size: 0.8rem;
}

.sortable.asc::after {
    content: '↑';
    opacity: 1;
    color: #0d6efd;
}

.sortable.desc::after {
    content: '↓';
    opacity: 1;
    color: #0d6efd;
}

.editable-cell select,
.editable-cell input[type="number"] {
    width: 100%;
    padding: 2px 4px;
    font-size: 0.75rem;
}

table th, table td {
    white-space: nowrap;
    padding: 4px 6px !important;
}

table td select {
    max-width: 100%;
    font-size: 0.7rem;
}

.card-header h4 {
    margin: 0;
}

.progress {
    background-color: #e9ecef;
}

.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,.075);
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>