<?php
require_once __DIR__ . '/../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

// Load the SINGLE, UNIFIED JSON file.
// Try multiple possible paths
$possible_paths = [
    __DIR__ . '/ghom/assets/js/allinone.json',
    __DIR__ . '/assets/js/allinone.json',
    __DIR__ . '/../assets/js/allinone.json',
    $_SERVER['DOCUMENT_ROOT'] . '/ghom/assets/js/allinone.json',
];

$config = [];
$config_path = '';

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $config_path = $path;
        $json_content = file_get_contents($path);
        $config = json_decode($json_content, true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($config)) {
            break;
        }
    }
}

// Debug: Log which path worked (or didn't)
if (empty($config)) {
    error_log("WARNING: allinone.json not found or invalid. Tried paths: " . implode(', ', $possible_paths));
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فرم درخواست بازگشایی پانل</title>
    <style>
        @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
                 url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
                 url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
        }
        
        * {
            box-sizing: border-box;
        }
        
        body { 
            font-family: Samim, Tahoma, sans-serif; 
            direction: rtl; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            min-height: 100vh;
        }
        
        .form-container { 
            max-width: 850px; 
            margin: 20px auto; 
            background: white; 
            padding: 30px; 
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2); 
        }
        
        h2 {
            text-align: center;
            color: #2c3e50;
            font-size: 24px;
            margin: 0 0 25px 0;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
            font-weight: bold;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 8px;
            border: 2px solid #e1e8ed;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-item.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label { 
            font-weight: bold; 
            color: #34495e;
            margin-bottom: 6px;
            font-size: 13px;
        }
        
        .form-value { 
            padding: 10px 12px; 
            background: white;
            border: 2px solid #bdc3c7;
            border-radius: 6px;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .section-title {
            font-weight: bold;
            color: #2c3e50;
            margin: 25px 0 12px 0;
            font-size: 16px;
            padding: 8px 12px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 6px;
        }
        
        #svg-preview-container {
            border: 3px solid #34495e;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            max-height: 500px;
            overflow: auto;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        
        #svg-preview-container svg {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .highlight-panel {
            fill: rgba(255, 193, 7, 0.8) !important;
            stroke: #e74c3c !important;
            stroke-width: 3px !important;
        }
        
        .notes-container {
            margin-top: 20px;
        }
        
        .notes-box {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border: 2px solid #bdc3c7;
            border-radius: 8px;
            font-family: Samim, Tahoma, sans-serif;
            font-size: 14px;
            line-height: 1.8;
            resize: vertical;
            background: white;
        }
        
        .notes-box:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .upload-section {
            margin-top: 25px;
            padding: 20px;
            background: #fff3cd;
            border: 2px dashed #ffc107;
            border-radius: 8px;
        }
        
        .upload-section input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #ffc107;
            border-radius: 6px;
            background: white;
            cursor: pointer;
        }
        
        .button-container { 
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        button { 
            padding: 14px 30px; 
            border: none; 
            color: white; 
            cursor: pointer; 
            font-size: 16px; 
            border-radius: 8px;
            font-family: Samim, Tahoma, sans-serif;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .btn-save { 
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .btn-print { 
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
        
        .no-print { }

        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm;
            }
            
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            body { 
                background: white !important;
                padding: 0;
                margin: 0;
            }
            
            .no-print { 
                display: none !important; 
            }
            
            .form-container { 
                box-shadow: none;
                border: 3px solid #000;
                padding: 15px;
                max-width: 100%;
                margin: 0;
                border-radius: 0;
            }
            
            h2 {
                font-size: 18pt;
                border-bottom: 3px solid #000;
                color: #000;
                margin-bottom: 15px;
                padding-bottom: 10px;
            }
            
            .info-grid {
                background: white !important;
                border: 2px solid #000;
                padding: 12px;
                margin-bottom: 15px;
                page-break-inside: avoid;
            }
            
            .form-label {
                color: #000;
                font-size: 10pt;
            }
            
            .form-value {
                border: 1px solid #000;
                background: white !important;
                color: #000;
                font-size: 9pt;
                padding: 6px 8px;
            }
            
            .section-title {
                background: #000 !important;
                color: white !important;
                font-size: 12pt;
                padding: 6px 10px;
                margin: 12px 0 8px 0;
                page-break-after: avoid;
            }
            
            #svg-preview-container { 
                border: 2px solid #000;
                padding: 8px;
                background: white !important;
                max-height: none !important;
                overflow: visible !important;
                page-break-inside: avoid;
            }
            
            #svg-preview-container svg { 
                max-height: 350px !important;
                width: 100% !important;
                height: auto !important;
            }
            
            .highlight-panel {
                fill: rgb(255, 235, 59) !important;
                stroke: rgb(0, 0, 0) !important;
                stroke-width: 4 !important;
                opacity: 1 !important;
            }
            
            .notes-container {
                page-break-inside: avoid;
                margin-top: 12px;
            }
            
            .notes-box {
                border: 2px solid #000;
                min-height: 70px;
                font-size: 9pt;
                padding: 8px;
                background: white !important;
                line-height: 1.6;
            }
            
            .signature-area {
                margin-top: 15px;
                page-break-inside: avoid;
                display: flex !important;
                justify-content: space-around;
                border-top: 2px solid #000;
                padding-top: 15px;
            }
            
            .signature-box {
                text-align: center;
                width: 45%;
            }
            
            .signature-line {
                border-top: 2px solid #000;
                margin: 30px auto 8px auto;
                width: 80%;
            }
            
            .signature-label {
                font-weight: bold;
                font-size: 11pt;
                margin-bottom: 5px;
            }
            
            .signature-fields {
                font-size: 9pt;
                line-height: 1.8;
            }
        }
        
        .signature-area {
            display: none;
        }
    </style>
</head>
<body>
     <div class="form-container">
         <h2>فرم درخواست بازگشایی پانل</h2>
        <div class="info-grid">
            <div class="info-item">
                <label for="display-date" class="form-label">تاریخ درخواست:</label>
                <input type="text" id="display-date" class="form-value" readonly>
            </div>
            <div class="info-item">
                <label for="display-contractor" class="form-label">نام پیمانکار:</label>
                <input type="text" id="display-contractor" class="form-value" readonly>
            </div>
             <div class="info-item">
                <label for="display-block" class="form-label">بلوک:</label>
                <input type="text" id="display-block" class="form-value" readonly>
            </div>
            <div class="info-item">
                <label for="display-zone" class="form-label">زون:</label>
                <input type="text" id="display-zone" class="form-value" readonly>
            </div>
        </div>
        <div class="section-title">🗺️ نقشه محدوده کاری (پانل‌های درخواستی با زرد مشخص شده)</div>
        <div id="svg-preview-container"><p style="text-align: center; color: #666;">در حال بارگذاری نقشه...</p></div>
        <div class="notes-container">
            <div class="section-title">موضوع: درخواست بازگشایی پنل های GFRC</div>
            <textarea id="display-notes" class="notes-box" readonly></textarea>
        </div>
        
        <div class="signature-area">
            <div class="signature-box">
                <div class="signature-label">امضای پیمانکار</div><div class="signature-line"></div><div class="signature-fields">نام و نام خانوادگی: ________________<br>تاریخ: ________________</div>
            </div>
            <div class="signature-box">
                <div class="signature-label">تایید بازرس</div><div class="signature-line"></div><div class="signature-fields">نام و نام خانوادگی: ________________<br>تاریخ: ________________</div>
            </div>
        </div>
        
        <div class="upload-section no-print">
            <div class="section-title" style="margin: 0 0 15px 0;">📎 بارگذاری فرم امضا شده</div>
            <input type="file" id="signed-form-upload" accept=".pdf,.jpg,.jpeg,.png" required>
            <p style="margin: 10px 0 0 0; font-size: 13px; color: #856404;">⚠️ لطفا فرم را چاپ کرده، امضا نمایید و سپس اسکن شده را بارگذاری کنید</p>
        </div>
        
        <div class="button-container no-print">
            <button class="btn-save" onclick="submitRequest()">✓ ثبت نهایی و ارسال</button>
            <button class="btn-print" onclick="window.print()">🖨️ چاپ فرم</button>
        </div>
    </div>

    <script>
        // Load config from PHP
        let config = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>;
        
        // Fallback: If config is empty or array, try to load it directly
        if (!config || Array.isArray(config) || Object.keys(config).length === 0) {
            console.warn('Config not loaded from PHP, attempting to fetch directly...');
            
            // Fallback inline config (copy from your allinone.json)
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
            console.log('✓ Using fallback inline config');
        }
        
        let permitData = null;
        
        // Debug: Log config to verify it's loaded
        console.log('Config loaded:', config);
        console.log('Config regions:', config?.regions);

        document.addEventListener('DOMContentLoaded', () => {
            const savedDataString = sessionStorage.getItem('gfrcPermitData');
            if (!savedDataString) {
                document.body.innerHTML = '<h1>❌ خطا: اطلاعات یافت نشد</h1>';
                return;
            }
            
            try {
                permitData = JSON.parse(savedDataString);

                // ✅ Get Persian labels from JSON config
                const { persianBlock, persianZone } = findPersianLabels(permitData.blockId, permitData.svgFile);
                
                // ✅ Use .value to set input field content
                document.getElementById('display-date').value = permitData.date || 'نامشخص';
                document.getElementById('display-contractor').value = permitData.contractor || 'نامشخص';
                document.getElementById('display-block').value = persianBlock;
                document.getElementById('display-zone').value = persianZone;
                
                const panelCount = permitData.panels ? permitData.panels.length : 0;
                const professionalMessage = `با سلام،
احتراماً این پیمانکار (${permitData.contractor}) جهت اصلاح ایرادات زیرسازی، هوابندی و آب‌بندی پنل‌های GFRC، درخواست بازگشایی ${panelCount} پنل واقع در "${persianBlock}"، "${persianZone}" را دارد.
                
توضیحات بیشتر پیمانکار:
${permitData.notes || '(توضیحات خاصی ثبت نشده است)'}`;
                document.getElementById('display-notes').value = professionalMessage;

                if (permitData.svgFile) {
                    loadAndDisplaySVG(permitData.svgFile);
                } else {
                    document.getElementById('svg-preview-container').innerHTML = '<p style="color: red; text-align: center;">❌ خطا: فایل نقشه مشخص نشده است</p>';
                }

            } catch (error) {
                console.error('Error parsing permit data:', error);
                alert('خطا در بارگذاری اطلاعات: ' + error.message);
            }
        });

        /**
         * ✅ FIXED FUNCTION: Find Persian labels from JSON config
         * @param {string} blockId - The region key (e.g., "Atieh", "AranB")
         * @param {string} svgFilePath - The SVG filename (e.g., "Zone08AT.svg")
         * @returns {Object} - { persianBlock, persianZone }
         */
        function findPersianLabels(blockId, svgFilePath) {
            let persianBlock = 'نامشخص';
            let persianZone = 'نامشخص';

            // Debug logging
            console.log('Finding labels for:', { blockId, svgFilePath });
            console.log('Config available:', !!config);
            console.log('Config regions:', config?.regions);
            
            // Check if config and regions exist
            if (!config) {
                console.error('Config is null or undefined');
                return { persianBlock, persianZone };
            }
            
            if (!config.regions) {
                console.error('Config.regions is missing');
                return { persianBlock, persianZone };
            }
            
            if (!config.regions[blockId]) {
                console.warn(`Block ID '${blockId}' not found in config. Available blocks:`, Object.keys(config.regions));
                return { persianBlock, persianZone };
            }

            const region = config.regions[blockId];
            console.log('Found region:', region);
            
            // ✅ Use the region's label (e.g., "بلوک A- رس")
            persianBlock = region.label || persianBlock;

            // ✅ Extract filename from path
            const svgFileName = svgFilePath.split('/').pop();
            console.log('Looking for SVG file:', svgFileName);
            
            // ✅ Find the zone by matching svgFile
            const zoneInfo = region.zones?.find(zone => zone.svgFile === svgFileName);
            
            if (zoneInfo) {
                // ✅ Use the zone's label (e.g., "زون 8 (رس)")
                persianZone = zoneInfo.label || persianZone;
                console.log('Found zone:', zoneInfo);
            } else {
                console.warn(`SVG file '${svgFileName}' not found in block '${blockId}'. Available zones:`, region.zones?.map(z => z.svgFile));
            }
            
            console.log('Result:', { persianBlock, persianZone });
            return { persianBlock, persianZone };
        }

        async function loadAndDisplaySVG(svgPath) {
            const container = document.getElementById('svg-preview-container');
            try {
                const response = await fetch(svgPath);
                if (!response.ok) {
                    throw new Error(`خطای شبکه (کد: ${response.status})`);
                }
                const svgText = await response.text();
                container.innerHTML = svgText;

                const svgElement = container.querySelector('svg');
                if (svgElement && permitData.panels && permitData.panels.length > 0) {
                    svgElement.style.colorInterpolation = 'sRGB';
                    
                    const panelIds = permitData.panels.map(p => p.element_id);
                    let highlightedCount = 0;
                    
                    panelIds.forEach(id => {
                        const panelElement = svgElement.getElementById(id);
                        if (panelElement) {
                            panelElement.style.fill = 'rgb(255, 235, 59)';
                            panelElement.style.stroke = 'rgb(0, 0, 0)';
                            panelElement.style.strokeWidth = '4';
                            panelElement.style.opacity = '1';
                            panelElement.classList.add('highlight-panel');
                            highlightedCount++;
                        } else {
                            console.warn(`Panel ID '${id}' not found in SVG`);
                        }
                    });
                    
                    console.log(`✓ ${highlightedCount} panels highlighted successfully`);
                } else {
                    console.warn('No panels to highlight');
                }
            } catch (error) {
                console.error('Error loading SVG:', error);
                container.innerHTML = `<p style="color: red; text-align: center;">❌ خطا در بارگذاری نقشه: ${error.message}</p>`;
            }
        }

        async function submitRequest() {
            const fileInput = document.getElementById('signed-form-upload');
            const signedFormFile = fileInput.files[0];
            const saveBtn = document.querySelector('.btn-save');
            
            if (!permitData) {
                alert('❌ خطا: اطلاعات درخواست نامعتبر است');
                return;
            }
            
            if (!signedFormFile) {
                alert('⚠️ لطفا ابتدا فایل فرم امضا شده را انتخاب کنید');
                fileInput.focus();
                return;
            }
            
            const formData = new FormData();
            formData.append('permit_data', JSON.stringify(permitData));
            formData.append('signed_form', signedFormFile);
            
            saveBtn.disabled = true;
            saveBtn.textContent = '⏳ در حال ارسال...';
            
            try {
                const response = await fetch('/ghom/api/submit_opening_request.php', { 
                    method: 'POST', 
                    body: formData 
                });
                
                const result = await response.json();
                
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'خطای نامشخص در ارسال');
                }
                
                sessionStorage.removeItem('gfrcPermitData');
                alert('✓ ' + result.message);
                saveBtn.textContent = '✓ ارسال شد';
                
                setTimeout(() => { window.close(); }, 1500);
                
            } catch (error) {
                console.error('Submit error:', error);
                alert(`❌ خطا در ارسال: ${error.message}`);
                saveBtn.disabled = false;
                saveBtn.textContent = '✓ ثبت نهایی و ارسال';
            }
        }
    </script>
</body>
</html>