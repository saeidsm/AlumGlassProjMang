<?php
// /public_html/pardis/mobile_plan.php

// --- CORE BOOTSTRAP AND SECURITY ---
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();

// All security and project context checks are still important
if (!isLoggedIn() || !isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== 'pardis') {
    header('Location: /login.php');
    exit();
}

// --- GET AND VALIDATE THE PLAN FILE FROM THE URL ---
$plan_file_to_load = $_GET['plan'] ?? null;

// SECURITY: Basic validation to prevent directory traversal or loading arbitrary files.
if (!$plan_file_to_load || !preg_match('/^[a-zA-Z0-9_.-]+\.svg$/', $plan_file_to_load)) {
    http_response_code(400);
    die("Error: Invalid or missing plan file specified.");
}

// --- PAGE SETUP ---
$pageTitle = "نقشه: " . htmlspecialchars(str_replace('.svg', '', $plan_file_to_load));
require_once __DIR__ . '/header_p_mobile.php'; 
$user_role = $_SESSION['role'] ?? 'guest';
?>

<link rel="stylesheet" href="<?php echo version_asset("/pardis/assets/css/mobile.css");?>">

<body data-user-role="<?php echo htmlspecialchars($user_role); ?>">

<div id="app-container">
    <main id="main-view">
        <div id="info-bar">
            <span id="info-bar-text">Loading...</span>
            <!-- This button now simply goes back to the main mobile page -->
            <a href="mobile.php" id="backToPlanBtn">بازگشت به نقشه اصلی</a>
        </div>
        <div id="svgContainer">
            <!-- The SVG will be loaded here by JavaScript -->
        </div>
    </main>

    <!-- The bottom nav is still here for Layers, Status, and Actions -->
    <nav id="bottom-nav">
        <a href="mobile.php" class="nav-button">
            <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            <span>خانه</span>
        </a>
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

    <!-- All the other necessary HTML elements -->
    <div id="bottom-sheet" class="bottom-sheet">...</div>
    <div id="universalChecklistForm" class="form-popup-mobile"></div>
    <div id="crack-drawer-modal" class="crack-drawer-modal"></div>
    <div id="loader-overlay" style="display: none;">...</div>
    <div id="layerControlsContainer" style="display: none;"></div>
</div>

<!-- All your scripts -->
<script src="/pardis/assets/js/interact.min.js"></script> 
<script src="/pardis/assets/js/jalalidatepicker.min.js"></script>
<script src="/pardis/assets/js/forge.min.js"></script>
<script src="<?php echo version_asset('/pardis/assets/js/PlanDrawingModule.js'); ?>"></script>
<script src="<?php echo version_asset("/pardis/assets/js/mobile_appNew.js");?>"></script>

<!-- =================================================================== -->
<!-- THIS SCRIPT IS THE KEY: It tells the JS which plan to load on startup -->
<!-- =================================================================== -->
<script>
    document.addEventListener("DOMContentLoaded", () => {
        // On a zone plan page, SHOW the Layers and Status buttons.
        const layersBtn = document.getElementById('nav-btn-layers');
        const statusBtn = document.getElementById('nav-btn-status');
        if (layersBtn) layersBtn.style.display = 'inline-flex';
        if (statusBtn) statusBtn.style.display = 'inline-flex';

        // HIDE the main navigation button, as "Back" is more useful here.
        const navBtn = document.getElementById('nav-btn-navigate');
        if (navBtn) navBtn.style.display = 'none';

        // Get the plan file name from PHP and load it.
        const planToLoad = '<?php echo $plan_file_to_load; ?>';
        loadAndDisplaySVG(planToLoad);
    });
</script>

</body>
</html>