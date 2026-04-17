<?php
// ghom/contractor_batch_update.php
function isMobileDevice() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

if (isMobileDevice()) {
    $mobile_page_url = '/ghom/contractor_batch_update_mobile.php';
    if (!empty($_SERVER['QUERY_STRING'])) {
        $mobile_page_url .= '?' . $_SERVER['QUERY_STRING'];
    }
    header('Location: ' . $mobile_page_url);
    exit();
}
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}
$user_role = $_SESSION['role'];
$is_contractor = in_array($user_role, ['cat', 'car', 'coa', 'crs']);
$is_consultant = in_array($user_role, ['admin', 'superuser']);

if (!$is_contractor && !$is_consultant) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}
$pageTitle = "اعلام وضعیت گروهی";
require_once __DIR__ . '/header_ghom.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title><?php echo escapeHtml($pageTitle); ?></title>
    <link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    
    <script>
        window.USER_ROLE = "<?php echo $user_role; ?>";
        document.addEventListener('DOMContentLoaded', () => {
            document.body.setAttribute('data-user-role', window.USER_ROLE);
        });
    </script>

<style>
        @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
                url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
                url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: "Samim", "Tahoma", sans-serif;
            background-color: #f4f7f6;
            direction: rtl;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0;
            text-align: right;
            min-height: 100vh;
        }

        .top-bar-container {
            width: 100%; max-width: 1600px; padding: 10px;
            display: flex; flex-direction: column; gap: 10px;
            background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 10px;
        }

        .controls-toolbar { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        #status-legend { display: flex; flex-wrap: wrap; justify-content: center; gap: 15px; padding: 8px; background: #f8f9fa; border-radius: 6px; border: 1px solid #dee2e6; font-size: 13px; }
        .layer-controls { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; padding: 5px; }
        .layer-controls button { padding: 5px 10px; border: 1px solid #ccc; background-color: #fff; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .layer-controls button.active { background-color: #007bff; color: white; border-color: #0056b3; }

        #svgContainer {
            width: 100%; height: 65vh; max-width: 1600px;
            border: 1px solid #007bff; background-color: #e9ecef;
            overflow: hidden; margin: 0 auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            display: flex; justify-content: center; align-items: center;
            cursor: grab; position: relative;
        }
        #svgContainer svg { display: block; width: 100%; height: 100%; }
        
        .interactive-element { cursor: pointer; transition: stroke 0.1s ease-out; }
        .element-selected { stroke: #007cba !important; stroke-width: 3px !important; filter: drop-shadow(0 0 8px rgba(0, 124, 186, 0.8)); }

        .action-btn { padding: 8px 15px; border-radius: 4px; cursor: pointer; border: none; font-weight: bold; font-size: 13px; }
        .action-btn.primary { background-color: #007bff; color: white; }
        .action-btn.secondary { background-color: #6c757d; color: white; }

        #batch-update-panel {
            display: none; position: fixed; top: 120px; right: 20px; z-index: 1000;
            background: #ffffff; padding: 20px; border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); width: 340px;
            border-top: 5px solid #007bff; max-height: 85vh; overflow-y: auto;
        }
        
        #batch-update-panel .form-group { margin-bottom: 15px; }
        #batch-update-panel label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; }
        #batch-update-panel select, #batch-update-panel textarea, #batch-update-panel input[type="text"] { 
            width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; 
        }
        #submitBatchUpdate { width: 100%; padding: 12px; background-color: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer; margin-top: 10px; font-weight:bold; }

        #currentZoneInfo { text-align: center; padding: 8px; background-color: #e9ecef; border-bottom: 1px solid #ccc; width: 100%; font-size: 14px; display: none; }
        .permit-label { font-family: Tahoma, sans-serif; font-size: 80px; font-weight: bold; fill: #f60808ff; stroke: # #e70e0eff; stroke-width: 1px; pointer-events: none; text-anchor: middle; dominant-baseline: middle; }
        .permit-label.hidden { display: none; }
        
        jdp-container { z-index: 99999 !important; }

        /* --- FIXED: Styles for Zone Buttons --- */
        .zone-nav-btn {
            background-color: #fff;
            border: 1px solid #007bff;
            color: #007bff;
            padding: 6px 12px;
            margin: 3px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
            font-family: inherit;
        }
        .zone-nav-btn:hover {
            background-color: #007bff;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        /* Zone Menu Popup */
        #zoneSelectionMenu {
            position: absolute;
            background: white;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            padding: 15px;
            z-index: 2000;
            min-width: 250px;
        }
        .zone-menu-title { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 8px; margin-bottom: 10px; font-size: 14px; font-weight: bold; }
        .zone-menu-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .zone-menu-grid button { 
            padding: 8px; border: 1px solid #ddd; background: #f9f9f9; 
            border-radius: 4px; cursor: pointer; font-size: 12px;
        }
        .zone-menu-grid button:hover { background: #e9ecef; border-color: #bbb; }
        .close-menu-btn { width: 100%; margin-top: 10px; background: #6c757d; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer; }
    </style>
</head>

<body>

    <div class="top-bar-container">
        <div class="controls-toolbar">
            <button id="toggle-batch-panel-btn" class="action-btn primary">اعلام وضعیت / مجوز</button>
            <a href="/ghom/permit_dashboard.php" class="action-btn secondary" target="_blank" style="text-decoration:none; display:flex; align-items:center;">📂 کارتابل مجوزها</a>
            <button id="toggle-permit-labels-btn" class="action-btn secondary" style="background-color: #fd7e14;">🔢 نمایش شماره مجوز</button>
            <button id="backToPlanBtn" class="action-btn primary">بازگشت به پلن اصلی</button>
            <button id="show-instructions-btn" class="action-btn secondary">راهنما</button>
        </div>
        <div id="status-legend"></div>
        <div class="layer-controls" id="layerControlsContainer"></div>
    </div>

    <!-- FIXED: Added 'zoneBlockDisplay' -->
    <div id="currentZoneInfo">
        نقشه: <span id="zoneNameDisplay"></span> | 
        پیمانکار: <span id="zoneContractorDisplay"></span> | 
        بلوک: <span id="zoneBlockDisplay"></span>
    </div>

    <div id="regionZoneNavContainer" style="width: 90%; max-width: 800px; margin: 10px auto; text-align:center;">
        <label for="regionSelect" style="font-weight:bold;">انتخاب محدوده:</label>
        <select id="regionSelect" style="padding:5px; margin:0 10px;"></select>
        <div id="zoneButtonsContainer" style="margin-top:10px; display:flex; flex-wrap:wrap; gap:5px; justify-content:center;"></div>
    </div>

    <div id="svgContainer"></div>

    <!-- PANEL (SIDEBAR) -->
    <div id="batch-update-panel">
        <button onclick="document.getElementById('batch-update-panel').style.display='none'" style="position:absolute; top:10px; left:10px; background:#ff4d4d; color:white; border:none; border-radius:4px; padding:4px 10px; cursor:pointer;">✕</button>
        <h3>مدیریت</h3>
        <p><strong><span id="selectionCount">0</span> المان انتخاب شده</strong></p>
        <hr>
        
        <div class="form-group">
            <label>نوع عملیات:</label>
            <select id="action-type-select">
                <?php if ($is_contractor) : ?>
                    <option value="create-permit" style="font-weight: bold; color: #007bff;">📄 ایجاد مجوز کار (Permit)</option>
                <?php endif; ?>
                <?php if ($is_consultant) : ?>
                    <option value="verify-opening">بازبینی و ثبت چک‌لیست</option>
                <?php endif; ?>
            </select>
        </div>
        
        <div id="dynamic-form-container">
            <!-- GFRC Parts -->
            <div id="gfrc-parts-container" class="form-group" style="display: none; background: #f0f8ff; padding: 10px; border: 1px solid #b8daff; border-radius: 5px;">
                <label style="color:#004085;">انتخاب وجه‌های تعمیر:</label>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <label><input type="checkbox" class="gfrc-part-checkbox" value="face" checked> روبرو</label>
                    <label><input type="checkbox" class="gfrc-part-checkbox" value="left" checked> چپ</label>
                    <label><input type="checkbox" class="gfrc-part-checkbox" value="right" checked> راست</label>
                    <label><input type="checkbox" class="gfrc-part-checkbox" value="up" checked> بالا</label>
                    <label><input type="checkbox" class="gfrc-part-checkbox" value="down" checked> پایین</label>
                </div>
            </div>

            <!-- Date & Notes -->
            <div id="permit-extra-fields" class="form-group" style="display: none;">
                <label for="batch_date">تاریخ درخواست (الزامی):</label>
                <input type="text" id="batch_date" data-jdp placeholder="تاریخ را انتخاب کنید..." autocomplete="off">
                
                <label for="batch_notes" style="margin-top:10px;">شرح عملیات / توضیحات:</label>
                <textarea id="batch_notes" rows="3" placeholder="مثال: اصلاح زیرسازی و ..."></textarea>
            </div>
        </div>

        <button id="submitBatchUpdate">شروع فرآیند</button>
    </div>

    <!-- Scripts -->
    <script src="/ghom/assets/js/jalalidatepicker.min.js"></script>
    <script>
        jalaliDatepicker.startWatch({
            zIndex: 99999,
            hideAfterChange: true,
            autoShow: true
        });

        async function initializeApp() {
            try {
                const [groupRes, regionRes, planRes] = await Promise.all([
                    fetch('/ghom/assets/js/svgGroupConfig.json'),
                    fetch('/ghom/assets/js/regionToZoneMap.json'),
                    fetch('/ghom/assets/js/planNavigationMappings.json')
                ]);
                window.svgGroupConfig = await groupRes.json();
                window.regionToZoneMap = await regionRes.json();
                window.planNavigationMappings = await planRes.json();
            } catch (error) {
                console.error(error);
                document.getElementById('svgContainer').innerHTML = `<p style="color:red;">خطا در بارگذاری تنظیمات</p>`;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initializeApp();
            
            const actionSelect = document.getElementById('action-type-select');
            const gfrcContainer = document.getElementById('gfrc-parts-container');
            const extrasContainer = document.getElementById('permit-extra-fields');
            const submitBtn = document.getElementById('submitBatchUpdate');

            function updateUI() {
                const action = actionSelect.value;
                if (action === 'create-permit') {
                    extrasContainer.style.display = 'block'; 
                    submitBtn.textContent = '📄 ایجاد مجوز کار (Permit)';
                    submitBtn.className = 'btn-success';
                    gfrcContainer.style.display = 'block'; 
                } else {
                    extrasContainer.style.display = 'none';
                    gfrcContainer.style.display = 'none';
                    submitBtn.textContent = 'شروع فرآیند';
                }
            }

            actionSelect?.addEventListener('change', updateUI);
            setTimeout(updateUI, 500);
        });
    </script>
    
    <script type="module" src="<?= version_asset('/ghom/assets/js/shared_svg_logic.js') ?>"></script>
    <div id="element-tooltip" style="display: none; position: absolute; background: rgba(0,0,0,0.85); color: white; padding: 10px; border-radius: 5px; z-index: 9999; max-width: 350px; font-size: 13px; pointer-events: none;"></div>
    <?php require_once 'footer.php'; ?>
</body>
</html>