<?php
// public_html/pardis/mobile.php

// --- CORE BOOTSTRAP AND SECURITY (IDENTICAL TO DESKTOP) ---
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

// 1. Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

// 2. Check for required role
if (!in_array($_SESSION['role'], ['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

// 3. Check for project context
$expected_project_key = 'pardis';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    logError("User ID " . ($_SESSION['user_id'] ?? 'N/A') . " tried to access pardis project page without correct session context.");
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}

// --- PAGE SETUP ---
$pageTitle = "پروژه دانشگاه پردیس (موبایل)";
// Use the same header, as it likely contains necessary meta tags and scripts
require_once __DIR__ . '/header.php'; 
$user_role = $_SESSION['role'] ?? 'guest';
?>

<link rel="stylesheet" href="<?php echo version_asset("/pardis/assets/css/mobile.css");?>">

<body data-user-role="<?php echo htmlspecialchars($user_role); ?>">
 
<div id="app-container">
    <main id="main-view">
        <div id="info-bar" style="display: none;">
            <span id="info-bar-text"></span>
            <button id="backToPlanBtn" style="display: none;">بازگشت به نقشه اصلی</button>
        </div>
        <div id="svgContainer">
             </div>
    </main>

    <nav id="bottom-nav">
        <button id="nav-btn-navigate" class="nav-button">
            <svg viewBox="0 0 24 24"><path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"/></svg>
            <span>ناوبری</span>
        </button>
        <button id="nav-btn-layers" class="nav-button">
            <svg viewBox="0 0 24 24"><path d="M12 16l-5-5h3V4h4v7h3l-5 5zm-5 4v-2h14v2H7z"/></svg>
            <span>لایه‌ها</span>
        </button>
        <button id="nav-btn-status" class="nav-button">
           <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            <span>وضعیت‌ها</span>
        </button>
         <button id="nav-btn-actions" class="nav-button">
            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            <span>اقدامات</span>
        </button>
    </nav>

    <div id="bottom-sheet" class="bottom-sheet">
        <div class="sheet-header">
            <h3 id="sheet-title"></h3>
            <button id="sheet-close-btn">&times;</button>
        </div>
        <div id="sheet-content">
            </div>
    </div>
</div>

<div id="universalChecklistForm" class="form-popup-mobile">
    </div>

  <p class="description">برای مشاهده چک لیست، روی المان مربوطه در نقشه کلیک کنید.</p>
    <div id="svgContainer"></div>
   


<div id="loader-overlay" style="display: none;">
    <div class="loader-spinner"></div>
</div>

<div id="layerControlsContainer" style="display: none;"></div>
<script src="/pardis/assets/js/fabric.min.js"></script>
<script src="/pardis/assets/js/interact.min.js"></script> 
 <script type="text/javascript" src="/pardis/assets/js/jalalidatepicker.min.js"></script>
<script src="/pardis/assets/js/forge.min.js"></script>
<script src="<?php echo version_asset("/pardis/assets/js/mobile_appNew.js");?>"></script>
<script src="<?php echo version_asset('/pardis/assets/js/PlanDrawingModule.js'); ?>"></script>
<script>
    function closeForm(formId) {
  const form = document.getElementById(formId);
  if (form) form.classList.remove("show");

  const gfrcMenu = document.getElementById("gfrcSubPanelMenu");
  if (gfrcMenu) gfrcMenu.remove();
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>