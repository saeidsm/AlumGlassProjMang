<?php
// ghom/print_permit.php
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();
if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

$pdo = getProjectDBConnection('ghom');
$permitId = $_GET['id'] ?? 0;

// 1. Fetch Permit Data
$stmt = $pdo->prepare("SELECT * FROM permits WHERE id = ?");
$stmt->execute([$permitId]);
$permitData = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$permitData) die("Permit not found");

// 2. Load Config (JSON)
$possible_paths = [
    __DIR__ . '/assets/js/allinone.json',
    $_SERVER['DOCUMENT_ROOT'] . '/ghom/assets/js/allinone.json',
];

$configFromFile = [];
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $json_content = file_get_contents($path);
        $configFromFile = json_decode($json_content, true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($configFromFile)) {
            break;
        }
    }
}

// 3. Fetch Elements
$stmtEl = $pdo->prepare("SELECT element_id FROM permit_elements WHERE permit_id = ?");
$stmtEl->execute([$permitId]);
$elements = $stmtEl->fetchAll(PDO::FETCH_COLUMN);

// 4. Prepare Data & Persian Date
$persianDate = jdate('Y/m/d', strtotime($permitData['created_at']));
$svgFilename = $permitData['zone'];
if (!str_ends_with(strtolower($svgFilename), '.svg')) {
    $svgFilename .= '.svg';
}

$permitDataJS = [
    'blockId' => $permitData['block'], 
    'svgFile' => $svgFilename, 
    'date' => $persianDate, 
    'contractor' => 'شرکت رس', // Default fallback
    'notes' => $permitData['notes'],
    'panels' => array_map(fn($id) => ['element_id' => $id], $elements)
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>چاپ مجوز - <?= $permitId ?></title>
    <style>
        @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
                 url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
                 url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
        }
        * { box-sizing: border-box; }
        body { font-family: Samim, Tahoma, sans-serif; direction: rtl; padding: 20px; background: #f0f0f0; margin: 0; }
        .form-container { max-width: 210mm; margin: 0 auto; background: white; padding: 20px; border: 1px solid #ccc; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #000; font-size: 18px; margin: 0 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #000; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; border: 1px solid #000; padding: 10px; }
        .info-item { display: flex; align-items: center; }
        .form-label { font-weight: bold; margin-left: 10px; min-width: 80px; }
        .form-value, select.form-value { flex: 1; padding: 5px; border: 1px solid #999; border-radius: 4px; font-family: inherit; font-size: 13px; background: #fff; }
        .section-title { background: #333; color: white; padding: 5px 10px; font-weight: bold; margin: 15px 0 10px 0; border-radius: 4px; font-size: 14px; }
        #svg-preview-container { border: 2px solid #000; padding: 5px; height: 400px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        #svg-preview-container svg { width: 100%; height: 100%; max-height: 100%; }
        .highlight-panel { fill: #ffff00 !important; stroke: #ff0000 !important; stroke-width: 3px !important; opacity: 1 !important; }
        .notes-box { width: 100%; min-height: 100px; padding: 10px; border: 2px solid #000; font-family: inherit; font-size: 13px; line-height: 1.6; resize: vertical; }
        .signature-area { margin-top: 30px; display: flex; justify-content: space-between; border-top: 2px solid #000; padding-top: 20px; }
        .signature-box { width: 24%; text-align: center; }
        .signature-label { font-weight: bold; font-size: 11px; margin-bottom: 40px; height: 30px; display: flex; align-items: center; justify-content: center; border-bottom: 1px dashed #ccc; white-space: pre-wrap; }
        .signature-line { border-top: 1px solid #000; margin-bottom: 5px; }
        .signature-fields { font-size: 10px; }
        .no-print { text-align: center; margin-bottom: 20px; display:flex; gap:10px; justify-content:center; }
        .btn-print { padding: 10px 25px; background: #007bff; color: white; border: none; cursor: pointer; font-size: 16px; border-radius: 5px; }
        .btn-save { padding: 10px 25px; background: #28a745; color: white; border: none; cursor: pointer; font-size: 16px; border-radius: 5px; }
        
        @media print {
            body { background: white; padding: 0; }
            .form-container { border: none; box-shadow: none; padding: 0; width: 100%; }
            .no-print { display: none; }
            .form-value { border: none; padding: 0; }
            select.form-value { appearance: none; -webkit-appearance: none; }
        }
    </style>
</head>
<body>

   <div class="no-print">
        <button class="btn-save" onclick="savePermitInfo()">💾 ذخیره اطلاعات (Save)</button>
        <button class="btn-print" onclick="window.print()">🖨️ چاپ فرم (Print)</button>
    </div>
    
    <div class="no-print" style="margin-bottom:20px; text-align:center; color:red; font-size:12px;">
        * لطفا ابتدا پیمانکار را انتخاب کرده و دکمه ذخیره را بزنید. سپس فرم را چاپ کنید.
    </div>

    <div class="form-container">
        <h2>فرم درخواست مجوز کار / بازگشایی پانل (شماره: <?= $permitId ?>)</h2>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="form-label">تاریخ:</span>
                <input type="text" id="display-date" class="form-value" readonly>
            </div>
            
            <div class="info-item">
                <span class="form-label">پیمانکار:</span>
                <select id="display-contractor" class="form-value" onchange="updateDynamicContent()">
                    <option value="">-- انتخاب کنید --</option>
                    <option value="شرکت رس">شرکت رس</option>
                    <option value="شرکت عمران آذرستان">شرکت عمران آذرستان</option>
                    <option value="شرکت آتیه نما">شرکت آتیه نما</option>
                    <option value="شرکت آرانسج">شرکت آرانسج</option>
                </select>
            </div>
            
             <div class="info-item">
                <span class="form-label">بلوک:</span>
                <input type="text" id="display-block" class="form-value" readonly>
            </div>
            <div class="info-item">
                <span class="form-label">زون:</span>
                <input type="text" id="display-zone" class="form-value" readonly>
            </div>
        </div>

        <div class="section-title">محل در نقشه:</div>
        <div id="svg-preview-container">در حال بارگذاری نقشه...</div>

        <div class="section-title">شرح درخواست:</div>
        <textarea id="display-notes" class="notes-box"></textarea>
        
        <div class="signature-area">
            <!-- 1. Selected Contractor -->
            <div class="signature-box">
                <div class="signature-label" id="sig-contractor-name">پیمانکار</div>
                <div class="signature-line"></div>
                <div class="signature-fields">مهر و امضا</div>
            </div>
            
            <!-- 2. Ros -->
            <div class="signature-box">
                <div class="signature-label">شرکت رس</div>
                <div class="signature-line"></div>
                <div class="signature-fields">مهر و امضا</div>
            </div>
            
            <!-- 3. Aluminum Shisheh -->
            <div class="signature-box">
                <div class="signature-label">شرکت آلومینیوم شیشه تهران</div>
                <div class="signature-line"></div>
                <div class="signature-fields">مهر و امضا</div>
            </div>

            <!-- 4. Nevi -->
            <div class="signature-box">
                <div class="signature-label">شرکت مهندسین مشاور نوی</div>
                <div class="signature-line"></div>
                <div class="signature-fields">مهر و امضا</div>
            </div>
        </div>
    </div>

    <script>
        // Load config from PHP (if file existed)
        let config = <?php echo json_encode($configFromFile, JSON_UNESCAPED_UNICODE); ?>;
        let permitData = <?php echo json_encode($permitDataJS); ?>;
         const permitId = <?= $permitId ?>;
        // --- 1. STRICT CONFIGURATION (Exactly as requested) ---
        if (!config || Array.isArray(config) || Object.keys(config).length === 0) {
            console.log('Using Fallback Config');
            config = {
                "regions": {
                    "Atieh": {
                        "label": "بلوک A- رس",
                        "contractor": "شرکت رس",
                        "block": "A",
                        "zones": [
                            {"label": "زون 1 (رس)", "svgFile": "Zone01AT.svg"},
                            {"label": "زون 2 (رس)", "svgFile": "Zone02AT.svg"},
                            {"label": "زون 3 (رس)", "svgFile": "Zone03AT.svg"},
                            {"label": "زون 4 (رس)", "svgFile": "Zone04AT.svg"},
                            {"label": "زون 5 (رس)", "svgFile": "Zone05AT.svg"},
                            {"label": "زون 6 (رس)", "svgFile": "Zone06AT.svg"},
                            {"label": "زون 7 (رس)", "svgFile": "Zone07AT.svg"},
                            {"label": "زون 8 (رس)", "svgFile": "Zone08AT.svg"},
                            {"label": "زون 9 (رس)", "svgFile": "Zone09AT.svg"},
                            {"label": "زون 10 (رس)", "svgFile": "Zone10AT.svg"},
                            {"label": "زون 11 (رس)", "svgFile": "Zone11AT.svg"},
                            {"label": "زون 12 (رس)", "svgFile": "Zone12AT.svg"},
                            {"label": "زون 13 (رس)", "svgFile": "Zone13AT.svg"},
                            {"label": "زون 14 (رس)", "svgFile": "Zone14AT.svg"},
                            {"label": "زون 15 (رس)", "svgFile": "Zone15AT.svg"},
                            {"label": "زون 16 (رس)", "svgFile": "Zone16AT.svg"},
                            {"label": "زون 17 (رس)", "svgFile": "Zone17AT.svg"},
                            {"label": "زون 18 (رس)", "svgFile": "Zone18AT.svg"},
                            {"label": "زون 19 (رس)", "svgFile": "Zone19AT.svg"}
                        ]
                    },
                    "org": {
                        "label": "بلوک - اورژانس A- رس",
                        "contractor": "شرکت رس",
                        "block": "A - اورژانس",
                        "zones": [
                            {"label": "زون اورژانس غربی ", "svgFile": "ZoneEmergencyWestAT.svg"},
                            {"label": "زون اورژانس شمالی ", "svgFile": "ZoneEmergencyNorthAT.svg"},
                            {"label": "زون اورژانس جنوبی ", "svgFile": "ZoneEmergencySouthAT.svg"}
                        ]
                    },
                    "AranB": {
                        "label": "بلوک B-عمران آذرستان",
                        "contractor": "شرکت عمران آذرستان",
                        "block": "B",
                        "zones": [
                            {"label": "زون 1 (عمران آذرستان B)", "svgFile": "Zone01ARJ.svg"},
                            {"label": "زون 2 (عمران آذرستان B)", "svgFile": "Zone02ARJ.svg"},
                            {"label": "زون 3 (عمران آذرستان B)", "svgFile": "Zone03ARJ.svg"},
                            {"label": "زون 11 (عمران آذرستان B)", "svgFile": "Zone11ARJ.svg"},
                            {"label": "زون 12 (عمران آذرستان B)", "svgFile": "Zone12ARJ.svg"},
                            {"label": "زون 13 (عمران آذرستان B)", "svgFile": "Zone13ARJ.svg"},
                            {"label": "زون 14 (عمران آذرستان B)", "svgFile": "Zone14ARJ.svg"},
                            {"label": "زون 19 (عمران آذرستان B)", "svgFile": "Zone19ARJ.svg"},
                            {"label": "زون 20 (عمران آذرستان B)", "svgFile": "Zone20ARJ.svg"},
                            {"label": "زون 21 (عمران آذرستان B)", "svgFile": "Zone21ARJ.svg"}
                        ]
                    },
                    "AranC": {
                        "label": "بلوک C-عمران آذرستان",
                        "contractor": "شرکت عمران آذرستان",
                        "block": "C",
                        "zones": [
                            {"label": "زون 4 (عمران آذرستان C)", "svgFile": "Zone04ARJ.svg"},
                            {"label": "زون 5 (عمران آذرستان C)", "svgFile": "Zone05ARJ.svg"},
                            {"label": "زون 6 (عمران آذرستان C)", "svgFile": "Zone06ARJ.svg"},
                            {"label": "زون 7E (عمران آذرستان C)", "svgFile": "Zone07EARJ.svg"},
                            {"label": "زون 7S (عمران آذرستان C)", "svgFile": "Zone07SARJ.svg"},
                            {"label": "زون 7N (عمران آذرستان C)", "svgFile": "Zone07NARJ.svg"},
                            {"label": "زون 8 (عمران آذرستان C)", "svgFile": "Zone08ARJ.svg"},
                            {"label": "زون 9 (عمران آذرستان C)", "svgFile": "Zone09ARJ.svg"},
                            {"label": "زون 10 (عمران آذرستان C)", "svgFile": "Zone10ARJ.svg"},
                            {"label": "زون 22 (عمران آذرستان C)", "svgFile": "Zone22ARJ.svg"},
                            {"label": "زون 23 (عمران آذرستان C)", "svgFile": "Zone23ARJ.svg"},
                            {"label": "زون 24 (عمران آذرستان C)", "svgFile": "Zone24ARJ.svg"}
                        ]
                    },
                    "hayatOmran": {
                        "label": "حیاط عمران آذرستان",
                        "contractor": "شرکت عمران آذرستان",
                        "block": "حیاط",
                        "zones": [
                            {"label": "زون 15 حیاط عمران آذرستان", "svgFile": "Zone15ARJ.svg"},
                            {"label": "زون 16 حیاط عمران آذرستان", "svgFile": "Zone16ARJ.svg"},
                            {"label": "زون 17 حیاط عمران آذرستان", "svgFile": "Zone17ARJ.svg"},
                            {"label": "زون 18 حیاط عمران آذرستان", "svgFile": "Zone18ARJ.svg"}
                        ]
                    },
                    "hayatRos": {
                        "label": "حیاط رس",
                        "contractor": "شرکت ساختمانی رس",
                        "block": "حیاط",
                        "zones": [
                            {"label": "زون 1 حیاط رس ", "svgFile": "Zone1ROS.svg"},
                            {"label": "زون 2 حیاط رس ", "svgFile": "Zone2ROS.svg"},
                            {"label": "زون 3 حیاط رس ", "svgFile": "Zone3ROS.svg"},
                            {"label": "زون 11 حیاط رس ", "svgFile": "Zone11ROS.svg"},
                            {"label": "زون 12 حیاط رس", "svgFile": "Zone12ROS.svg"},
                            {"label": "زون 13 حیاط رس", "svgFile": "Zone13ROS.svg"},
                            {"label": "زون 14 حیاط رس", "svgFile": "Zone14ROS.svg"},
                            {"label": "زون 16 حیاط رس", "svgFile": "Zone16ROS.svg"},
                            {"label": "زون 19 حیاط رس", "svgFile": "Zone19ROS.svg"},
                            {"label": "زون 20 حیاط رس", "svgFile": "Zone20ROS.svg"},
                            {"label": "زون 21 حیاط رس", "svgFile": "Zone21ROS.svg"},
                            {"label": "زون 25 حیاط رس", "svgFile": "Zone25ROS.svg"},
                            {"label": "زون 26 حیاط رس", "svgFile": "Zone26ROS.svg"}
                        ]
                    }
                }
            };
        }
        
        // --- 2. GLOBAL VARIABLES FOR LABELS ---
        let gPersianBlock = 'نامشخص';
        let gPersianZone = 'نامشخص';
        let gPanelCount = 0;

        function findPersianLabels(blockId, svgFilePath) {
            let persianBlock = 'نامشخص';
            let persianZone = 'نامشخص';
            let foundContractor = 'شرکت رس';

            if (!config || !config.regions) return { persianBlock, persianZone, foundContractor };
            
            // Normalize filename
            const cleanFileName = svgFilePath.split('/').pop().replace('.svg', '').toLowerCase();

            // 1. First, check if blockId matches a Region Key directly (e.g. "AranC")
            // This is the most accurate method based on your config structure
            let region = config.regions[blockId];
            
            // 2. If not found by key, fallback to searching by filename inside zones
            if (!region) {
                for (const rKey in config.regions) {
                    const rData = config.regions[rKey];
                    if (rData.zones) {
                        const z = rData.zones.find(z => z.svgFile.toLowerCase().includes(cleanFileName));
                        if (z) { region = rData; break; }
                    }
                }
            }

            if (region) {
                persianBlock = region.label || persianBlock;
                foundContractor = region.contractor || foundContractor;
                
                if (region.zones) {
                    const zoneInfo = region.zones.find(z => z.svgFile.toLowerCase().includes(cleanFileName));
                    if (zoneInfo) persianZone = zoneInfo.label || persianZone;
                }
            }
            
            return { persianBlock, persianZone, foundContractor };
        }

        // --- 3. DYNAMIC CONTENT UPDATER ---
        window.updateDynamicContent = function() {
            // Get selected contractor
            const select = document.getElementById('display-contractor');
            const contractorName = select.value || 'پیمانکار';
            
            // Update Signature Label
            const sigLabel = document.getElementById('sig-contractor-name');
            sigLabel.textContent = contractorName;
            
            // Update Text Note
            const message = `با سلام،\nاحتراماً این پیمانکار (${contractorName}) جهت اصلاح ایرادات موجود در زیرسازی، هوابندی و آب‌بندی پنل‌های GFRC، درخواست بازگشایی ${gPanelCount} پنل واقع در "${gPersianBlock}"، "${gPersianZone}" را دارد.`;
            document.getElementById('display-notes').value = message;
        }

            window.savePermitInfo = async function() {
            const contractor = document.getElementById('display-contractor').value;
            const notes = document.getElementById('display-notes').value;
            
            if(!contractor) return alert('لطفا نام پیمانکار را انتخاب کنید.');
            
            const btn = document.querySelector('.btn-save');
            btn.disabled = true; btn.innerText = "در حال ذخیره...";
            
            // We use a FormData trick to reuse the upload API, but without a file
            // Alternatively, create a specific update API. Here we reuse `upload_signed_permit.php` 
            // but we need to make sure it handles missing file gracefully for updates.
            
            // BETTER: Use a specific update API or fetch call. 
            // Since we modified `upload_signed_permit.php` to REQUIRE a file, let's create a quick inline fetch here
            // that targets `api/save_permit_checklist.php` logic OR creates a new simple update endpoint.
            
            // Let's use `save_permit.php` logic but Update. Actually, simpler to just add a small API handler here?
            // No, let's use `upload_signed_permit.php` logic but we need to tweak it to allow updates without file.
            
            // Assuming you updated `upload_signed_permit.php` to handle optional file:
            const formData = new FormData();
            formData.append('permit_id', permitId);
            formData.append('contractor', contractor);
            formData.append('notes', notes);
            // No file appended
            
            try {
                // IMPORTANT: You must modify `upload_signed_permit.php` to allow missing file if you want to use it here.
                // If not, we can use `save_permit.php` (if it supports update) or create `api/update_permit_info.php`.
                
                // Let's assume you will create/use `api/update_permit_info.php`.
                const res = await fetch('/ghom/api/update_permit_info.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if(data.status === 'success') {
                    alert('اطلاعات با موفقیت ذخیره شد.');
                } else {
                    alert('خطا: ' + data.message);
                }
            } catch(e) {
                alert('خطا در ارتباط با سرور');
            }
            btn.disabled = false; btn.innerText = "💾 ذخیره اطلاعات (Save)";
        };

        document.addEventListener('DOMContentLoaded', () => {
            try {
                const { persianBlock, persianZone, foundContractor } = findPersianLabels(permitData.blockId, permitData.svgFile);
                gPersianBlock = persianBlock;
                gPersianZone = persianZone;
                gPanelCount = permitData.panels ? permitData.panels.length : 0;

                document.getElementById('display-date').value = permitData.date;
                document.getElementById('display-block').value = persianBlock;
                document.getElementById('display-zone').value = persianZone;
                
                const contractorSelect = document.getElementById('display-contractor');
                const savedContractor = <?php echo json_encode($permitData['contractor_name']); ?>;
                const targetContractor = savedContractor || foundContractor;

                let matchFound = false;
                for (let i = 0; i < contractorSelect.options.length; i++) {
                    if (contractorSelect.options[i].value === targetContractor) {
                        contractorSelect.selectedIndex = i;
                        matchFound = true;
                        break;
                    }
                }
                if (!matchFound && targetContractor) {
                    const opt = document.createElement("option");
                    opt.value = targetContractor;
                    opt.text = targetContractor;
                    contractorSelect.add(opt);
                    contractorSelect.value = targetContractor;
                }
                
                // Set saved notes if available
                if(permitData.notes) {
                    document.getElementById('display-notes').value = permitData.notes;
                } else {
                    updateDynamicContent();
                }

                const svgPath = '/ghom/PreInspectionsSvg/' + permitData.svgFile.split('/').pop();
                loadAndDisplaySVG(svgPath);

            } catch (error) {
                console.error(error);
                alert('خطا: ' + error.message);
            }
        });

        async function loadAndDisplaySVG(svgPath) {
            const container = document.getElementById('svg-preview-container');
            try {
                const response = await fetch(svgPath);
                if (!response.ok) throw new Error(`خطای شبکه (کد: ${response.status})`);
                const svgText = await response.text();
                container.innerHTML = svgText;
                const svgElement = container.querySelector('svg');
                if (svgElement && permitData.panels && permitData.panels.length > 0) {
                    svgElement.style.colorInterpolation = 'sRGB';
                    const panelIds = permitData.panels.map(p => p.element_id);
                    panelIds.forEach(id => {
                        const panelElement = svgElement.getElementById(id);
                        if (panelElement) {
                            panelElement.style.fill = 'yellow';
                            panelElement.style.stroke = 'red';
                            panelElement.style.strokeWidth = '10px';
                            panelElement.style.opacity = '1';
                            panelElement.classList.add('highlight-panel');
                        }
                    });
                }
            } catch (error) {
                console.error(error);
                container.innerHTML = `<p style="color: red; text-align: center;">❌ خطا در بارگذاری نقشه.<br><small>${svgPath}</small></p>`;
            }
        }
    </script>
</body>
</html>