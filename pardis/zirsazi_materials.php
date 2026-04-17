<?php
// public_html/pardis/zirsazi_materials.php
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
$is_admin = in_array($user_role, ['admin', 'superuser', 'supervisor']);

// Get user's full name
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

try {
    $pdo = getProjectDBConnection('pardis');
} catch (PDOException $e) {
    logError("Database connection failed: " . $e->getMessage());
    die("Database connection error");
}

$pageTitle = "دریافت مصالح زیرسازی - پروژه دانشگاه خاتم پردیس";

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

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Vazir', Tahoma, Arial, sans-serif; 
            padding: 20px; 
            background: #f5f5f5; 
        }
        
        .container { max-width: 1600px; margin: 0 auto; }
        
        h1 { 
            text-align: center; 
            margin-bottom: 30px; 
            color: #333;
            font-size: 28px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .controls {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-family: 'Vazir', Tahoma;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover { background: #0056b3; }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover { background: #218838; }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover { background: #545b62; }
        
        .data-table {
            background: white;
            border-radius: 8px;
            overflow-x: auto;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        
        .data-table th {
            background: #007bff;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table tr:hover { background: #f5f5f5; }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            width: 90%;
            max-width: 1000px;
            border-radius: 8px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .close-modal {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
        }
        
        .close-modal:hover { color: #f8f9fa; }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: 'Vazir', Tahoma;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .document-badge {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            margin: 2px;
        }
        
        .document-badge:hover {
            background: #0056b3;
        }
        
        .items-selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid #e0e0e0;
            padding: 15px;
            border-radius: 8px;
            background: #f9f9f9;
        }
        
        .item-checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .item-checkbox-wrapper:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .item-checkbox-wrapper.selected {
            border-color: #667eea;
            background: #e8eeff;
        }
        
        .item-checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .selected-count {
            padding: 10px 15px;
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 6px;
            margin: 15px 0;
            font-weight: bold;
            color: #856404;
        }
        
        .pdf-viewer {
            width: 100%;
            height: 70vh;
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ثبت دریافت مصالح زیرسازی</h1>
        
        <div class="stats" id="statsContainer">
            <div class="stat-card">
                <div class="stat-number" id="totalEntries">0</div>
                <div class="stat-label">کل ورودی‌ها</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="totalMaterials">0</div>
                <div class="stat-label">انواع مصالح</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="totalWeight">0</div>
                <div class="stat-label">وزن کل (تن)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="totalDocuments">0</div>
                <div class="stat-label">اسناد پیوست</div>
            </div>
        </div>
        
        <div class="controls">
            <?php if ($is_admin): ?>
            <button class="btn btn-primary" onclick="openReceiveModal()">
                ➕ ثبت دریافت جدید
            </button>
            <button class="btn btn-success" onclick="openBulkDocumentModal()">
                📎 اختصاص سند به چند ورودی
            </button>
            <?php endif; ?>
            <button class="btn btn-secondary" onclick="exportToExcel()">
                📊 خروجی Excel
            </button>
        </div>
        
        <div class="data-table">
            <table id="materialsTable">
                <thead>
                    <tr>
                        <th>تاریخ ورود</th>
                        <th>نوع مصالح</th>
                        <th>مقدار</th>
                        <th>واحد</th>
                        <th>وزن کل (kg)</th>
                        <th>شماره حواله</th>
                        <th>راننده</th>
                        <th>پلاک</th>
                        <th>مقصد</th>
                        <th>توضیحات</th>
                        <th>اسناد</th>
                        <?php if ($is_admin): ?>
                        <th>عملیات</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="materialsTableBody">
                    <tr>
                        <td colspan="12" class="text-center" style="padding: 40px;">
                            <div>در حال بارگذاری...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Receive Material Modal -->
    <div id="receiveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>ثبت دریافت مصالح زیرسازی</h3>
                <button class="close-modal" onclick="closeReceiveModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="receiveForm" onsubmit="submitReceive(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label>تاریخ ورود *</label>
                            <input type="text" name="entry_date" data-jdp readonly required>
                        </div>
                        <div class="form-group">
                            <label>نوع مصالح *</label>
                            <select name="material_id" id="materialSelect" required onchange="updateWeightInfo()">
                                <option value="">انتخاب کنید...</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>مقدار *</label>
                            <input type="number" name="quantity" step="0.01" required onchange="calculateWeight()">
                        </div>
                        <div class="form-group">
                            <label>واحد</label>
                            <input type="text" name="unit" readonly id="unitDisplay">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>وزن کل محاسبه شده (kg)</label>
                        <input type="number" name="total_weight" step="0.01" id="totalWeightDisplay" readonly>
                        <small style="color: #666; display: block; margin-top: 5px;" id="weightInfo"></small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>شماره حواله</label>
                            <input type="text" name="havale_number">
                        </div>
                        <div class="form-group">
                            <label>نام راننده</label>
                            <input type="text" name="driver_name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>پلاک خودرو</label>
                            <input type="text" name="vehicle_plate">
                        </div>
                        <div class="form-group">
                            <label>مقصد</label>
                            <select name="destination">
                                <option value="">انتخاب کنید...</option>
                                <option value="Building 1">Building 1</option>
                                <option value="Building 2">Building 2</option>
                                <option value="Building 3">Building 3</option>
                                <option value="Building 4">Building 4</option>
                                <option value="Building 5">Building 5</option>
                                <option value="Building 6">Building 6</option>
                                <option value="انبار">انبار</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>توضیحات</label>
                        <textarea name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>آپلود سند (PDF)</label>
                        <input type="file" name="document" accept=".pdf">
                        <small style="color: #666;">اختیاری - می‌توانید بعداً سند را اضافه کنید</small>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeReceiveModal()">انصراف</button>
                        <button type="submit" class="btn btn-primary">ثبت دریافت</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Document Assignment Modal -->
    <div id="bulkDocumentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>اختصاص سند به چندین ورودی</h3>
                <button class="close-modal" onclick="closeBulkDocumentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="bulkDocumentForm" onsubmit="submitBulkDocument(event)">
                    <div class="form-group">
                        <label>انتخاب فایل PDF *</label>
                        <input type="file" name="document" accept=".pdf" required>
                    </div>
                    
                    <div class="form-group">
                        <label>نام سند *</label>
                        <input type="text" name="document_name" placeholder="مثلاً: حواله شماره 1234" required>
                    </div>
                    
                    <div class="form-group">
                        <label>انتخاب ورودی‌ها (حداقل یک مورد) *</label>
                        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                            <button type="button" class="btn btn-success" style="font-size: 13px; padding: 8px 15px;" onclick="selectAllEntries()">✓ انتخاب همه</button>
                            <button type="button" class="btn btn-secondary" style="font-size: 13px; padding: 8px 15px;" onclick="deselectAllEntries()">✗ حذف انتخاب همه</button>
                        </div>
                        <div class="selected-count">
                            انتخاب شده: <span id="selectedEntriesCount">0</span> مورد
                        </div>
                        <div id="entriesSelectionGrid" class="items-selection-grid">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeBulkDocumentModal()">انصراف</button>
                        <button type="submit" class="btn btn-primary">آپلود و اختصاص</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- PDF Viewer Modal -->
    <div id="pdfViewerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="pdfViewerTitle">نمایش سند</h3>
                <button class="close-modal" onclick="closePdfViewer()">&times;</button>
            </div>
            <div class="modal-body" style="padding: 0;">
                <iframe id="pdfViewerFrame" class="pdf-viewer"></iframe>
            </div>
        </div>
    </div>

    <script>
    const isAdmin = <?php echo json_encode($is_admin); ?>;
    let materialsData = [];
    let allEntries = [];
    
    document.addEventListener('DOMContentLoaded', function() {
        loadMaterialsData();
        if (isAdmin) {
            loadMaterialsList();
        }
        
        // Initialize Jalali date picker
        if (typeof jalaliDatepicker !== 'undefined') {
            jalaliDatepicker.startWatch({
                persianDigits: true,
                autoShow: true,
                autoHide: true,
                hideAfterChange: true,
                date: true,
                time: false
            });
        }
    });
    
    async function loadMaterialsData() {
        try {
            const response = await fetch('zirsazi_api.php?action=get_material_entries');
            const data = await response.json();
            
            if (data.success) {
                allEntries = data.entries;
                displayMaterialsTable(data.entries);
                updateStats(data.stats);
            }
        } catch (error) {
            console.error('Error loading materials:', error);
        }
    }
    
    async function loadMaterialsList() {
        try {
            const response = await fetch('zirsazi_api.php?action=get_materials_list');
            const data = await response.json();
            
            if (data.success) {
                materialsData = data.materials;
                const select = document.getElementById('materialSelect');
                select.innerHTML = '<option value="">انتخاب کنید...</option>';
                data.materials.forEach(mat => {
                    select.innerHTML += `<option value="${mat.id}" data-unit="${mat.unit}" data-weight="${mat.unit_weight}">${mat.item_name}</option>`;
                });
            }
        } catch (error) {
            console.error('Error loading materials list:', error);
        }
    }
    
    function displayMaterialsTable(entries) {
        const tbody = document.getElementById('materialsTableBody');
        tbody.innerHTML = '';
        
        if (entries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="12" class="text-center" style="padding: 40px;">هیچ ورودی ثبت نشده است</td></tr>';
            return;
        }
        
        entries.forEach(entry => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${toJalali(entry.entry_date)}</td>
                <td><strong>${entry.material_name}</strong></td>
                <td>${parseFloat(entry.quantity).toFixed(2)}</td>
                <td>${entry.unit}</td>
                <td>${parseFloat(entry.total_weight).toFixed(2)}</td>
                <td>${entry.havale_number || '-'}</td>
                <td>${entry.driver_name || '-'}</td>
                <td>${entry.vehicle_plate || '-'}</td>
                <td>${entry.destination || '-'}</td>
                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">${entry.notes || '-'}</td>
                <td>${getDocumentBadges(entry.documents)}</td>
                ${isAdmin ? `<td>
                    <button class="btn btn-success" style="font-size: 12px; padding: 5px 10px;" onclick="uploadDocumentForEntry(${entry.id})">📎 سند</button>
                    <button class="btn btn-secondary" style="font-size: 12px; padding: 5px 10px; background: #dc3545;" onclick="deleteEntry(${entry.id})">🗑️</button>
                </td>` : ''}
            `;
            tbody.appendChild(row);
        });
    }
    
    function getDocumentBadges(documents) {
        if (!documents || documents.length === 0) {
            return '<span style="color: #999;">بدون سند</span>';
        }
        
        return documents.map(doc => 
            `<span class="document-badge" onclick="viewPdf('${doc.document_path}', '${doc.document_name}')">
                📄 ${doc.document_name}
            </span>`
        ).join(' ');
    }
    
    function updateStats(stats) {
        document.getElementById('totalEntries').textContent = stats.total_entries || 0;
        document.getElementById('totalMaterials').textContent = stats.unique_materials || 0;
        document.getElementById('totalWeight').textContent = ((stats.total_weight || 0) / 1000).toFixed(2);
        document.getElementById('totalDocuments').textContent = stats.total_documents || 0;
    }
    
    function updateWeightInfo() {
        const select = document.getElementById('materialSelect');
        const option = select.options[select.selectedIndex];
        const unit = option.getAttribute('data-unit');
        const weight = option.getAttribute('data-weight');
        
        document.getElementById('unitDisplay').value = unit || '';
        document.getElementById('weightInfo').textContent = weight ? `وزن واحد: ${weight} kg` : '';
        calculateWeight();
    }
    
    function calculateWeight() {
        const select = document.getElementById('materialSelect');
        const option = select.options[select.selectedIndex];
        const unitWeight = parseFloat(option.getAttribute('data-weight')) || 0;
        const quantity = parseFloat(document.querySelector('[name="quantity"]').value) || 0;
        
        const totalWeight = unitWeight * quantity;
        document.getElementById('totalWeightDisplay').value = totalWeight.toFixed(2);
    }
    
    function openReceiveModal() {
        document.getElementById('receiveModal').style.display = 'block';
    }
    
    function closeReceiveModal() {
        document.getElementById('receiveModal').style.display = 'none';
        document.getElementById('receiveForm').reset();
    }
    
    async function submitReceive(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        formData.append('action', 'add_material_entry');
        
        try {
            const response = await fetch('zirsazi_api.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('✅ دریافت با موفقیت ثبت شد!');
                closeReceiveModal();
                loadMaterialsData();
            } else {
                alert('❌ خطا: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ خطا در ثبت دریافت');
        }
    }
    
    function openBulkDocumentModal() {
        document.getElementById('bulkDocumentModal').style.display = 'block';
        populateEntriesSelection();
    }
    
    function closeBulkDocumentModal() {
        document.getElementById('bulkDocumentModal').style.display = 'none';
        document.getElementById('bulkDocumentForm').reset();
    }
    
    function populateEntriesSelection() {
        const grid = document.getElementById('entriesSelectionGrid');
        grid.innerHTML = '';
        
        allEntries.forEach((entry, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'item-checkbox-wrapper';
            wrapper.innerHTML = `
                <input type="checkbox" 
                       name="entry_ids[]" 
                       value="${entry.id}" 
                       id="entry_${index}"
                       onchange="updateSelectedEntriesCount(); toggleWrapperSelection(this)">
                <label style="font-size: 13px; cursor: pointer;" for="entry_${index}">
                    ${toJalali(entry.entry_date)}<br>
                    <strong>${entry.material_name}</strong>
                </label>
            `;
            grid.appendChild(wrapper);
        });
    }
    
    function toggleWrapperSelection(checkbox) {
        const wrapper = checkbox.closest('.item-checkbox-wrapper');
        if (checkbox.checked) {
            wrapper.classList.add('selected');
        } else {
            wrapper.classList.remove('selected');
        }
    }
    
    function selectAllEntries() {
        document.querySelectorAll('#entriesSelectionGrid input[type="checkbox"]').forEach(cb => {
            cb.checked = true;
            cb.closest('.item-checkbox-wrapper').classList.add('selected');
        });
        updateSelectedEntriesCount();
    }
    
    function deselectAllEntries() {
        document.querySelectorAll('#entriesSelectionGrid input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
            cb.closest('.item-checkbox-wrapper').classList.remove('selected');
        });
        updateSelectedEntriesCount();
    }
    
    function updateSelectedEntriesCount() {
        const count = document.querySelectorAll('#entriesSelectionGrid input[type="checkbox"]:checked').length;
        document.getElementById('selectedEntriesCount').textContent = count;
    }
    
    async function submitBulkDocument(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        formData.append('action', 'bulk_assign_document');
        
        const selectedEntries = formData.getAll('entry_ids[]');
        if (selectedEntries.length === 0) {
            alert('لطفاً حداقل یک ورودی را انتخاب کنید');
            return;
        }
        
        try {
            const response = await fetch('zirsazi_api.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert(`✅ سند با موفقیت به ${data.assigned_count} ورودی اختصاص داده شد!`);
                closeBulkDocumentModal();
                loadMaterialsData();
            } else {
                alert('❌ خطا: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ خطا در آپلود سند');
        }
    }
    
    function viewPdf(path, name) {
        document.getElementById('pdfViewerTitle').textContent = name;
        document.getElementById('pdfViewerFrame').src = path;
        document.getElementById('pdfViewerModal').style.display = 'block';
    }
    
    function closePdfViewer() {
        document.getElementById('pdfViewerModal').style.display = 'none';
        document.getElementById('pdfViewerFrame').src = '';
    }
    
    async function uploadDocumentForEntry(entryId) {
        const docName = prompt('نام سند را وارد کنید:');
        if (!docName) return;
        
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.pdf';
        input.onchange = async function() {
            if (this.files.length === 0) return;
            
            const formData = new FormData();
            formData.append('action', 'upload_entry_document');
            formData.append('entry_id', entryId);
            formData.append('document_name', docName);
            formData.append('document', this.files[0]);
            
            try {
                const response = await fetch('zirsazi_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ سند با موفقیت آپلود شد!');
                    loadMaterialsData();
                } else {
                    alert('❌ خطا: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ خطا در آپلود سند');
            }
        };
        input.click();
    }
    
    async function deleteEntry(entryId) {
        if (!confirm('آیا از حذف این ورودی اطمینان دارید؟')) return;
        
        const formData = new FormData();
        formData.append('action', 'delete_entry');
        formData.append('entry_id', entryId);
        
        try {
            const response = await fetch('zirsazi_api.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('✅ ورودی حذف شد');
                loadMaterialsData();
            } else {
                alert('❌ خطا: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ خطا در حذف ورودی');
        }
    }
    
    function exportToExcel() {
        let csv = '\ufeff'; // UTF-8 BOM
        csv += 'تاریخ ورود,نوع مصالح,مقدار,واحد,وزن کل (kg),شماره حواله,راننده,پلاک,مقصد,توضیحات\n';
        
        allEntries.forEach(entry => {
            csv += `${toJalali(entry.entry_date)},${entry.material_name},${entry.quantity},${entry.unit},${entry.total_weight},${entry.havale_number || ''},${entry.driver_name || ''},${entry.vehicle_plate || ''},${entry.destination || ''},"${(entry.notes || '').replace(/"/g, '""')}"\n`;
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'zirsazi_materials_' + new Date().toISOString().split('T')[0] + '.csv';
        link.click();
    }
    
    function toJalali(gregorian_date) {
        if (!gregorian_date) return '-';
        
        const parts = gregorian_date.split('-');
        if (parts.length !== 3) return gregorian_date;
        
        const [y, m, d] = parts;
        if (typeof gregorian_to_jalali === 'function') {
            const j = gregorian_to_jalali(y, m, d);
            return j[0] + '/' + String(j[1]).padStart(2, '0') + '/' + String(j[2]).padStart(2, '0');
        }
        
        return gregorian_date;
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
    </script>
</body>
</html>

<?php require_once __DIR__ . '/footer.php'; ?>