<?php
// public_html/pardis/viewer_3d_mobile.php

// --- CORE BOOTSTRAP AND SECURITY ---
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'superuser', 'user', 'supervisor','planner', 'cat','cod', 'car', 'coa', 'crs'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

$expected_project_key = 'pardis';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}

$pageTitle = "نمایشگر سه‌بعدی (موبایل)";

// Define available models (same as desktop)
$models = [
    [
        'file' => 'sementboards.glb',
        'name' => 'نمای سمنت بوردهای کشاورزی',
        'category' => 'کشاورزی'
    ],
    [
        'file' => 'agriopt.glb',
        'name' => 'نمای کشاورزی',
        'category' => 'کشاورزی'
    ],
     [
        'file' => 'lib.glb',
        'name' => 'نمای کتابخانه مرکزی',
        'category' => 'کتابخانه'
    ],

    [
        'file' => 'WestAgri1.glb',
        'name' => 'بخش یک نمای غربی کشاورزی',
        'category' => 'کشاورزی - نمای غربی'
    ],
    [
        'file' => 'WestAgri2.glb',
        'name' => 'بخش میانی نمای غربی کشاورزی',
        'category' => 'کشاورزی - نمای غربی'
    ],
    [
        'file' => 'WestAgri3.glb',
        'name' => 'بخش 3 نمای غربی کشاورزی',
        'category' => 'کشاورزی - نمای غربی'
    ],
    [
        'file' => 'EastAgri1.glb',
        'name' => 'بخش 1 نمای شرقی کشاورزی',
        'category' => 'کشاورزی - نمای شرقی'
    ],
    [
        'file' => 'EastAgri2.glb',
        'name' => 'بخش 2 نمای شرقی کشاورزی',
        'category' => 'کشاورزی - نمای شرقی'
    ],
    [
        'file' => 'northAgri.glb',
        'name' => 'نمای شمالی کشاورزی',
        'category' => 'کشاورزی - نمای شمالی'
    ],
    [
        'file' => 'SouthAgri.glb',
        'name' => 'نمای جنوبی کشاورزی',
        'category' => 'کشاورزی - نمای جنوبی'
    ]
];

$groupedModels = [];
foreach ($models as $model) {
    $groupedModels[$model['category']][] = $model;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="/pardis/assets/images/favicon.ico">
    <style>
        /* Basic Mobile Reset & Font */
        @font-face {
            font-family: "Samim";
            src: url("/pardis/assets/fonts/Samim-FD.woff2") format("woff2");
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            font-family: "Samim", "Tahoma", sans-serif;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            background-color: #f0f2f5;
        }

        /* App Layout */
  #app-container {
    display: flex;
    flex-direction: column;
    height: 90vh; /* <-- This is the corrected line */
    width: 100%;
}

        /* Header */
        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex-shrink: 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .app-header h1 { font-size: 16px; font-weight: 600; }
        .back-button {
            color: white;
            text-decoration: none;
            font-size: 14px;
            padding: 5px 10px;
            border-radius: 5px;
            background-color: rgba(255,255,255,0.2);
        }

        /* Viewer Area */
        #viewer-container {
            flex: 1;
            position: relative;
            background-color: #e0e0e0;
            overflow: hidden;
        }
        #gltfViewerContainer {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
        }
        .welcome-screen { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; color: #718096; padding: 20px; }
        .welcome-screen h3 { font-size: 20px; color: #2d3748; margin-bottom: 10px; }

        /* Loader (Copied from desktop, works well) */
        .gltf-loader { position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(244, 247, 246, 0.95); z-index: 10; }
        .gltf-spinner { border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin-bottom: 15px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .gltf-loader p { color: #2d3748; font-size: 14px; }

        /* Bottom Navigation Bar */
        #bottom-nav {
            display: flex;
            justify-content: space-around;
            background-color: #ffffff;
            border-top: 1px solid #ddd;
            flex-shrink: 0;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
        }
        .nav-button {
            flex: 1;
            padding: 10px 5px;
            border: none;
            background: none;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-family: inherit;
            color: #555;
            font-size: 11px;
        }
        .nav-button svg { width: 24px; height: 24px; fill: #555; }
        .nav-button:active { background-color: #f0f0f0; }

        /* Bottom Sheet (for Models & Settings) */
        #bottom-sheet {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background-color: white;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.15);
            transform: translateY(100%);
            transition: transform 0.3s ease-in-out;
            z-index: 100;
            max-height: 70vh;
            display: flex;
            flex-direction: column;
        }
        #bottom-sheet.show { transform: translateY(0); }
        .sheet-header { padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        #sheet-title { font-size: 16px; font-weight: 600; }
        #sheet-close-btn { font-size: 24px; border: none; background: none; cursor: pointer; }
        #sheet-content { padding: 15px; overflow-y: auto; }
        
        /* Content inside the sheet */
        .model-category { margin-bottom: 20px; }
        .category-title { font-weight: 600; color: #667eea; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #eee; }
        .model-item { display: block; width: 100%; padding: 12px; margin-bottom: 8px; background: #f7fafc; border-radius: 8px; cursor: pointer; border: none; text-align: right; }
        .model-item:active { background-color: #e2e8f0; }
        
        .setting-group { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; }
        .setting-group label { font-size: 14px; }
    </style>
</head>
<body>

    <div id="app-container">
        <header class="app-header">
            <a href="/pardis/mobile.php" class="back-button">→ بازگشت</a>
            <h1 id="currentModelName">نمایشگر سه‌بعدی</h1>
        </header>

        <main id="viewer-container">
            <div id="gltfViewerContainer">
                <div class="welcome-screen">
                    <h3>خوش آمدید</h3>
                    <p>برای شروع، یک مدل را از لیست انتخاب کنید.</p>
                </div>
            </div>
        </main>

        <nav id="bottom-nav">
            <button id="nav-btn-models" class="nav-button">
                <svg fill="currentColor" viewBox="0 0 20 20"><path d="M3 12v3c0 1.657 3.134 3 7 3s7-1.343 7-3v-3c0 1.657-3.134 3-7 3s-7-1.343-7-3zM3 7v3c0 1.657 3.134 3 7 3s7-1.343 7-3V7c0 1.657-3.134 3-7 3S3 8.657 3 7zm14 5c0 1.657-3.134 3-7 3S3 13.657 3 12s3.134-3 7-3 7 1.343 7 3z"/></svg>
                <span>مدل‌ها</span>
            </button>
            <button id="nav-btn-settings" class="nav-button">
                 <svg fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a1 1 0 00-2 0v7.268a2 2 0 000 3.464V16a1 1 0 102 0v-1.268a2 2 0 000-3.464V4zM11 4a1 1 0 10-2 0v1.268a2 2 0 000 3.464V16a1 1 0 102 0V8.732a2 2 0 000-3.464V4zM16 3a1 1 0 011 1v7.268a2 2 0 010 3.464V16a1 1 0 11-2 0v-1.268a2 2 0 010-3.464V4a1 1 0 011-1z"/></svg>
                <span>تنظیمات</span>
            </button>
            <button id="nav-btn-reset" class="nav-button">
                <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 110 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/></svg>
                <span>ریست دوربین</span>
            </button>
            <button id="nav-btn-screenshot" class="nav-button">
                <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
                <span>عکس</span>
            </button>
        </nav>
    </div>

    <!-- Bottom Sheet Structure -->
    <div id="bottom-sheet">
        <div class="sheet-header">
            <h3 id="sheet-title"></h3>
            <button id="sheet-close-btn">&times;</button>
        </div>
        <div id="sheet-content">
            <!-- Content will be injected by JavaScript -->
        </div>
    </div>

   <script src="/pardis/assets/js/three.min.js"></script>
<script src="/pardis/assets/js/GLTFLoader.js"></script>
<script src="/pardis/assets/js/DRACOLoader.js"></script>

<!-- ✅ Add this line -->
<script src="/pardis/assets/js/meshopt_decoder.js"></script>

<script src="<?php echo version_asset('/pardis/assets/js/gltf_viewer_module.js'); ?>"></script>

    <script>
        let gltfViewer = null;
        let currentModelFile = null;
        const groupedModels = <?php echo json_encode($groupedModels, JSON_UNESCAPED_UNICODE); ?>;

        // --- Bottom Sheet Controls ---
        const bottomSheet = document.getElementById('bottom-sheet');
        function openSheet(title) {
            document.getElementById('sheet-title').textContent = title;
            bottomSheet.classList.add('show');
        }
        function closeSheet() {
            bottomSheet.classList.remove('show');
        }

        // --- Content Population for Sheets ---
        function populateModelsSheet() {
            const content = document.getElementById('sheet-content');
            content.innerHTML = '';
            for (const category in groupedModels) {
                const categoryDiv = document.createElement('div');
                categoryDiv.className = 'model-category';
                categoryDiv.innerHTML = `<div class="category-title">${category}</div>`;
                
                groupedModels[category].forEach(model => {
                    const button = document.createElement('button');
                    button.className = 'model-item';
                    button.textContent = model.name;
                    button.onclick = () => loadModel(model.file, model.name);
                    categoryDiv.appendChild(button);
                });
                content.appendChild(categoryDiv);
            }
            openSheet('انتخاب مدل');
        }

        function populateSettingsSheet() {
            const content = document.getElementById('sheet-content');
            content.innerHTML = `
                <div class="setting-group">
                    <label for="gridToggle">نمایش شبکه</label>
                    <input type="checkbox" id="gridToggle" checked onchange="gltfViewer.toggleGrid(this.checked)">
                </div>
                <div class="setting-group">
                    <label for="axesToggle">نمایش محورها</label>
                    <input type="checkbox" id="axesToggle" checked onchange="gltfViewer.toggleAxes(this.checked)">
                </div>
                <div class="setting-group">
                    <label for="wireframeToggle">حالت سیمی</label>
                    <input type="checkbox" id="wireframeToggle" onchange="gltfViewer.setWireframe(this.checked)">
                </div>
                <div class="setting-group">
                    <label for="exposureSlider">روشنایی</label>
                    <input type="range" id="exposureSlider" min="0.5" max="3" step="0.1" value="1.2" oninput="gltfViewer.setExposure(this.value)">
                </div>
            `;
            openSheet('تنظیمات نمایش');
        }

        // --- Main Viewer Functions ---
        function loadModel(filename, displayName) {
            closeSheet();
            if (!gltfViewer) {
                alert('نمایشگر آماده نیست.');
                return;
            }
            document.querySelector('.welcome-screen').style.display = 'none';
            document.getElementById('currentModelName').textContent = displayName;
            currentModelFile = filename;

            const path = `/pardis/gltf/${filename}`;
            gltfViewer.loadGLTF(path);
        }

        function takeScreenshot() {
            if (gltfViewer && currentModelFile) {
                const dataUrl = gltfViewer.takeScreenshot();
                const link = document.createElement('a');
                link.download = `${currentModelFile.replace('.glb', '')}_screenshot.png`;
                link.href = dataUrl;
                link.click();
            } else {
                alert('لطفاً ابتدا یک مدل را بارگذاری کنید.');
            }
        }
        
        // --- Initialization ---
        document.addEventListener('DOMContentLoaded', function() {
            gltfViewer = new GLTFViewer('gltfViewerContainer');
            
            // Event Listeners for controls
            document.getElementById('nav-btn-models').addEventListener('click', populateModelsSheet);
            document.getElementById('nav-btn-settings').addEventListener('click', populateSettingsSheet);
            document.getElementById('nav-btn-reset').addEventListener('click', () => { if(gltfViewer) gltfViewer.resetCamera(); });
            document.getElementById('nav-btn-screenshot').addEventListener('click', takeScreenshot);
            document.getElementById('sheet-close-btn').addEventListener('click', closeSheet);
        });

    </script>
</body>
</html>