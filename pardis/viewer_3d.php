<?php
// public_html/pardis/viewer_3d.php
function isMobileDevice() {
    return preg_match(
        "/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i",
        $_SERVER["HTTP_USER_AGENT"]
    );
}

if (isMobileDevice()) {
    header('Location: viewer_3d.php');
    exit();
}

require_once __DIR__ . '/../sercon/bootstrap.php';
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
    logError("User ID " . ($_SESSION['user_id'] ?? 'N/A') . " tried to access pardis 3D viewer without correct session context.");
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}

$pageTitle = "نمایشگر مدل‌های سه‌بعدی - پروژه دانشگاه خاتم پردیس";

// Define available models with Persian names
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

// Group models by category
$groupedModels = [];
foreach ($models as $model) {
    $groupedModels[$model['category']][] = $model;
}
?>



<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeHtml($pageTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="/pardis/assets/images/favicon.ico">
    
    <style>
        @font-face {
            font-family: "Samim";
            src: url("/pardis/assets/fonts/Samim-FD.woff2") format("woff2"),
                url("/pardis/assets/fonts/Samim-FD.woff") format("woff"),
                url("/pardis/assets/fonts/Samim-FD.ttf") format("truetype");
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Samim", "Tahoma", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            direction: rtl;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            color: #2d3748;
            font-size: 24px;
            font-weight: 600;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .main-content {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
            min-height: calc(100vh - 180px);
        }

        .sidebar {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            height: fit-content;
            max-height: calc(100vh - 180px);
            overflow-y: auto;
        }

        .sidebar h2 {
            color: #2d3748;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .model-category {
            margin-bottom: 25px;
        }

        .category-title {
            font-size: 14px;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 10px;
            padding: 8px 12px;
            background: #f7fafc;
            border-radius: 6px;
        }

        .model-item {
            padding: 12px 15px;
            margin-bottom: 8px;
            background: #f7fafc;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .model-item:hover {
            background: #edf2f7;
            transform: translateX(-3px);
            border-color: #667eea;
        }

        .model-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            border-color: #764ba2;
        }

        .model-item-name {
            font-size: 14px;
        }

        .viewer-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .viewer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .viewer-header h3 {
            font-size: 18px;
            font-weight: 600;
        }

        .viewer-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .control-btn {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            transition: all 0.3s;
        }

        .control-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .viewer-body {
            flex: 1;
            position: relative;
            min-height: 600px;
            background: #f4f7f6;
        }

        #gltfViewerContainer {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
        }

        .gltf-loader {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(244, 247, 246, 0.95);
            z-index: 10;
        }

        .gltf-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .gltf-loader p {
            color: #2d3748;
            font-size: 16px;
            font-weight: 500;
        }

        .welcome-screen {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #718096;
            text-align: center;
            padding: 40px;
        }

        .welcome-screen svg {
            width: 120px;
            height: 120px;
            margin-bottom: 25px;
            opacity: 0.5;
        }

        .welcome-screen h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #2d3748;
        }

        .welcome-screen p {
            font-size: 16px;
            color: #718096;
        }

        .settings-panel {
            background: white;
            border-top: 1px solid #e2e8f0;
            padding: 20px 25px;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            align-items: center;
        }

        .setting-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .setting-group label {
            font-size: 13px;
            color: #4a5568;
            font-weight: 500;
        }

        .setting-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .setting-group input[type="range"] {
            width: 150px;
            cursor: pointer;
        }

        .exposure-value {
            min-width: 30px;
            font-weight: 600;
            color: #667eea;
        }

        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }

            .sidebar {
                max-height: none;
            }
        }

        @media (max-width: 640px) {
            body {
                padding: 10px;
            }

            .header h1 {
                font-size: 18px;
            }

            .viewer-body {
                min-height: 400px;
            }
        }
        
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏗️ نمایشگر مدل‌های سه‌بعدی پروژه</h1>
            <a href="/pardis/index.php" class="back-button">
                ← بازگشت به صفحه اصلی
            </a>
        </div>

        <div class="main-content">
            <div class="sidebar">
                <h2>📋 لیست مدل‌ها</h2>
                
                <?php foreach ($groupedModels as $category => $categoryModels): ?>
                <div class="model-category">
                    <div class="category-title"><?php echo escapeHtml($category); ?></div>
                    <?php foreach ($categoryModels as $model): ?>
                    <div class="model-item" 
                         data-file="<?php echo escapeHtml($model['file']); ?>"
                         onclick="loadModel('<?php echo escapeHtml($model['file']); ?>', '<?php echo escapeHtml($model['name']); ?>')">
                        <div class="model-item-name"><?php echo escapeHtml($model['name']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="viewer-container">
                <div class="viewer-header">
                    <h3 id="currentModelName">مدل سه‌بعدی</h3>
                    <div class="viewer-controls">
                        <button class="control-btn" onclick="resetCamera()">🔄 بازنشانی دوربین</button>
                        <button class="control-btn" onclick="takeScreenshot()">📷 عکس‌برداری</button>
                        <button class="control-btn" onclick="debugModel()">🔍 اطلاعات مدل</button>
                        <button class="control-btn" onclick="toggleFullscreen()">⛶ تمام‌صفحه</button>
                    </div>
                </div>

                <div class="viewer-body">
                    <div id="gltfViewerContainer">
                        <div class="welcome-screen">
                            <svg fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 12v3c0 1.657 3.134 3 7 3s7-1.343 7-3v-3c0 1.657-3.134 3-7 3s-7-1.343-7-3z"/>
                                <path d="M3 7v3c0 1.657 3.134 3 7 3s7-1.343 7-3V7c0 1.657-3.134 3-7 3S3 8.657 3 7z"/>
                                <path d="M17 5c0 1.657-3.134 3-7 3S3 6.657 3 5s3.134-3 7-3 7 1.343 7 3z"/>
                            </svg>
                            <h3>خوش آمدید</h3>
                            <p>لطفاً یک مدل از لیست سمت راست انتخاب کنید</p>
                        </div>
                    </div>
                </div>

                <div class="settings-panel">
                    <div class="setting-group">
                        <input type="checkbox" id="gridToggle" checked onchange="toggleGrid(this.checked)">
                        <label for="gridToggle">نمایش شبکه</label>
                    </div>
                    <div class="setting-group">
                        <input type="checkbox" id="axesToggle" checked onchange="toggleAxes(this.checked)">
                        <label for="axesToggle">نمایش محورها</label>
                    </div>
                    <div class="setting-group">
                        <input type="checkbox" id="wireframeToggle" onchange="toggleWireframe(this.checked)">
                        <label for="wireframeToggle">حالت سیمی</label>
                    </div>
                    <div class="setting-group">
                        <label for="exposureSlider">روشنایی:</label>
                        <input type="range" id="exposureSlider" min="0.5" max="3" step="0.1" value="1.2" 
                               onchange="adjustExposure(this.value)">
                        <span class="exposure-value" id="exposureValue">1.2</span>
                    </div>
                </div>
                <div style="background: white; border-top: 1px solid #e2e8f0; padding: 15px 25px; display: flex; gap: 10px; flex-wrap: wrap;">
    <button class="control-btn" onclick="setView('top')">⬆️ بالا</button>
    <button class="control-btn" onclick="setView('bottom')">⬇️ پایین</button>
    <button class="control-btn" onclick="setView('front')">⏺️ جلو</button>
    <button class="control-btn" onclick="setView('back')">🔙 پشت</button>
    <button class="control-btn" onclick="setView('left')">⬅️ چپ</button>
    <button class="control-btn" onclick="setView('right')">➡️ راست</button>
    <button class="control-btn" onclick="setView('isometric')">📊 ایزومتریک</button>
</div>
            </div>
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

        // Initialize viewer on page load
        window.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('gltfViewerContainer');
            if (container) {
                // Wait a bit for container to be fully rendered
                setTimeout(() => {
                    gltfViewer = new GLTFViewer('gltfViewerContainer');
                }, 100);
            }
        });

        function loadModel(filename, displayName) {
            if (!gltfViewer) {
                alert('نمایشگر هنوز آماده نیست. لطفاً کمی صبر کنید.');
                return;
            }

            // Hide welcome screen
            const welcomeScreen = document.querySelector('.welcome-screen');
            if (welcomeScreen) {
                welcomeScreen.style.display = 'none';
            }

            // Update active state in sidebar
            document.querySelectorAll('.model-item').forEach(item => {
                item.classList.remove('active');
            });
            event.currentTarget.classList.add('active');

            // Update header
            document.getElementById('currentModelName').textContent = displayName;
            currentModelFile = filename;

            // Load the model
            const path = `/pardis/gltf/${filename}`;
            gltfViewer.loadGLTF(path, (progress) => {
                console.log(`بارگذاری ${filename}: ${progress.toFixed(0)}%`);
            });
        }

        function resetCamera() {
            if (gltfViewer) {
                gltfViewer.resetCamera();
            }
        }

        function toggleGrid(show) {
            if (gltfViewer) {
                gltfViewer.toggleGrid(show);
            }
        }

        function toggleAxes(show) {
            if (gltfViewer) {
                gltfViewer.toggleAxes(show);
            }
        }

        function toggleWireframe(enabled) {
            if (gltfViewer) {
                gltfViewer.setWireframe(enabled);
            }
        }

        function setView(viewName) {
    if (gltfViewer && currentModelFile) {
        gltfViewer.setView(viewName);
    } else {
        alert('لطفاً ابتدا یک مدل را بارگذاری کنید.');
    }
}

        function adjustExposure(value) {
            if (gltfViewer) {
                gltfViewer.setExposure(parseFloat(value));
                document.getElementById('exposureValue').textContent = parseFloat(value).toFixed(1);
            }
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

        function toggleFullscreen() {
            const container = document.querySelector('.viewer-container');
            if (!document.fullscreenElement) {
                container.requestFullscreen().catch(err => {
                    console.error('خطا در حالت تمام‌صفحه:', err);
                });
            } else {
                document.exitFullscreen();
            }
        }

        function debugModel() {
            if (!gltfViewer || !gltfViewer.model) {
                alert('هیچ مدلی بارگذاری نشده است.');
                return;
            }

            let info = {
                meshes: 0,
                materials: [],
                hasVertexColors: false,
                hasTextures: false,
                colorSamples: []
            };

            gltfViewer.model.traverse((node) => {
                if (node.isMesh) {
                    info.meshes++;
                    
                    // Check for vertex colors
                    if (node.geometry && node.geometry.attributes.color) {
                        info.hasVertexColors = true;
                    }
                    
                    // Check materials
                    if (node.material) {
                        const mats = Array.isArray(node.material) ? node.material : [node.material];
                        mats.forEach(mat => {
                            info.materials.push({
                                name: mat.name || 'بدون نام',
                                type: mat.type,
                                color: mat.color ? '#' + mat.color.getHexString() : 'ندارد',
                                vertexColors: mat.vertexColors ? 'بله' : 'خیر',
                                hasMap: mat.map ? 'بله' : 'خیر'
                            });
                            
                            if (mat.map) info.hasTextures = true;
                        });
                    }
                }
            });

            // Create debug info display
            let msg = `📊 اطلاعات مدل:\n\n`;
            msg += `تعداد Mesh: ${info.meshes}\n`;
            msg += `تعداد Material: ${info.materials.length}\n`;
            msg += `رنگ‌های Vertex: ${info.hasVertexColors ? 'دارد ✅' : 'ندارد ❌'}\n`;
            msg += `تکسچر: ${info.hasTextures ? 'دارد ✅' : 'ندارد ❌'}\n\n`;
            
            msg += `نمونه Materials:\n`;
            info.materials.slice(0, 5).forEach((mat, i) => {
                msg += `${i + 1}. ${mat.name}\n`;
                msg += `   نوع: ${mat.type}\n`;
                msg += `   رنگ: ${mat.color}\n`;
                msg += `   Vertex Colors: ${mat.vertexColors}\n\n`;
            });

            console.log('Debug Info:', info);
            alert(msg);
        }

        // Handle fullscreen changes
        document.addEventListener('fullscreenchange', function() {
            if (gltfViewer) {
                setTimeout(() => {
                    gltfViewer.onWindowResize();
                }, 100);
            }
        });
    </script>
</body>
</html>