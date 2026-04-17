<?php
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}
$user_role = $_SESSION['role'];
$is_contractor = in_array($user_role, ['cat', 'crs', 'coa', 'crs']);
$is_consultant = in_array($user_role, ['admin', 'superuser']);

if (!$is_contractor && !$is_consultant) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}
?>
<link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
</head>
<body data-user-role="<?php echo htmlspecialchars($_SESSION['role']); ?>">

<?php

// Get header data manually
$pdo_common_header = null;
$totalUnreadCount = 0;
$avatarPath = '/assets/images/default-avatar.jpg';
$all_user_accessible_projects = [];
$current_project_name = $_SESSION['current_project_name'] ?? 'پروژه';

try {
    $pdo_common_header = getCommonDBConnection();
    if ($pdo_common_header && isset($_SESSION['user_id'])) {
        $currentUserIdHeader = $_SESSION['user_id'];

        // Get unread count
      
        // Get avatar
        $stmt_avatar = $pdo_common_header->prepare("SELECT avatar_path FROM users WHERE id = ?");
        $stmt_avatar->execute([$currentUserIdHeader]);
        $potentialAvatarWebPath = $stmt_avatar->fetchColumn();
        if ($potentialAvatarWebPath && file_exists($_SERVER['DOCUMENT_ROOT'] . $potentialAvatarWebPath)) {
            $avatarPath = '/' . ltrim($potentialAvatarWebPath, '/');
        }

        // Get projects
        $stmt_all_projects = $pdo_common_header->prepare("SELECT p.project_id, p.project_name FROM projects p JOIN user_projects up ON p.project_id = up.project_id WHERE up.user_id = ? AND p.is_active = TRUE ORDER BY p.project_name");
        $stmt_all_projects->execute([$currentUserIdHeader]);
        $all_user_accessible_projects = $stmt_all_projects->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Log error but continue
}
?>

<!-- Mobile Header -->
<header style="position: fixed; top: 0; left: 0; right: 0; height: 60px; background: #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between; padding: 0 16px; z-index: 1000;">
    <div>
        <a href="/index.php" style="display: flex; align-items: center; text-decoration: none;">
            <img src="/assets/images/alumglass-farsi-logo-H40.png" alt="AlumGlass Logo" style="height: 35px; width: auto;">
        </a>
    </div>
    <button onclick="openMobileNav()" style="background: none; border: none; cursor: pointer; padding: 8px;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="#333"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
    </button>
</header>

<!-- Mobile Navigation -->
<div id="mobileNavOverlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1001; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease;" onclick="closeMobileNav()"></div>

<nav id="mobileNavMenu" style="position: fixed; top: 0; right: -300px; width: 300px; height: 100%; background: #fff; z-index: 1002; transition: right 0.3s ease; display: flex; flex-direction: column;">
    <div style="display: flex; align-items: center; gap: 12px; padding: 16px; background-color: #007cba; color: white;">
        <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Profile" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid white;">
        <div>
            <div style="font-weight: bold;"><?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?></div>
            <div style="font-size: 0.9em; opacity: 0.9;"><?php echo htmlspecialchars($current_project_name); ?></div>
        </div>
    </div>
    
    <div style="flex-grow: 1; overflow-y: auto; padding: 8px 0;">
        <a href="/ghom/index.php" style="display: flex; align-items: center; gap: 12px; padding: 14px 16px; text-decoration: none; color: #333; font-size: 1.1em;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="#555"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            <span>خانه</span>
        </a>
        <a href="/messages.php" style="display: flex; align-items: center; gap: 12px; padding: 14px 16px; text-decoration: none; color: #333; font-size: 1.1em;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="#555"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z"/></svg>
            <span>پیام‌ها</span>
            <?php if ($totalUnreadCount > 0): ?>
                <span style="margin-right: auto; background-color: #dc3545; color: white; font-size: 0.8em; padding: 2px 8px; border-radius: 12px; font-weight: bold;"><?php echo $totalUnreadCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="/index.php" style="display: flex; align-items: center; gap: 12px; padding: 14px 16px; text-decoration: none; color: #333; font-size: 1.1em;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="#555"><path d="M20 6h-2.18c.11-.31.18-.65.18-1 0-1.66-1.34-3-3-3s-3 1.34-3 3c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM4 19V8h16v11H4z"/></svg>
            <span>بازگشت به سایت اصلی</span>
        </a>
    </div>
    
    <div style="padding: 16px; border-top: 1px solid #eee;">
        <a href="/logout.php" style="text-decoration: none;">
            <button style="width: 100%; padding: 12px; border: none; background-color: #dc3545; color: white; border-radius: 8px; font-size: 1.1em; cursor: pointer;">خروج</button>
        </a>
    </div>
</nav>

<!-- Add padding to body for fixed header -->
<div style="padding-top: 60px;">
    <style>
        
        /* --- Base & Mobile UI CSS (Unchanged) --- */
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Segoe UI', Tahoma, Verdana, sans-serif; overflow: hidden; background: #f5f5f5; touch-action: none; }

        /* --- NEW: Navigation Menu CSS --- */
       .page-content {
    padding-top: 0; /* Remove since header_mobile.php already adds padding-top: 60px to body */
}

/* Adjust navigation menu position */
#navigationMenu {
    position: absolute;
    top: 0; /* Changed from 56px to 0 */
    left: 0;
    right: 0;
    bottom: 0;
    padding: 15px;
    overflow-y: auto;
    background-color: #f0f2f5;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

/* Update page header styles */
.page-header {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.page-header .btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    background: #007cba;
    color: white;
}
        .region-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.2s ease;
        }
        .region-card-header {
            padding: 16px;
            background-color: #007cba;
            color: white;
            font-size: 1.1em;
            font-weight: 600;
        }
        .zone-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            padding: 15px;
        }
        .zone-button {
             padding: 20px 10px;
            font-size: 1em; 
            text-align: center;
            background-color: #e9f5ff;
            color: #005a8d;
            border: 1px solid #bde0ff;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .zone-button:active {
            transform: scale(0.96);
            background-color: #d1eaff;
        }

        /* --- SVG Container (Now hidden by default) --- */
        #svgContainer {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: #e8f4f8; overflow: hidden; cursor: grab;
            touch-action: none;
            display: none; /* Initially hidden */
        }
        #svgContainer.visible {
            display: block; /* Shown when a zone is loaded */
        }

        /* --- Other UI Elements (Unchanged) --- */
        
        .fab-container { position: fixed; bottom: 20px; right: 20px; display: flex; flex-direction: column; gap: 12px; z-index: 1000; }
        .fab { width: 56px; height: 56px; border-radius: 50%; border: none; background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.3); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 24px; transition: all 0.3s ease; touch-action: manipulation; }
        .fab:active { transform: scale(0.95); }
        .fab.selection-mode { background: #4CAF50; color: white; }
        .fab.back-button { background: #6c757d; color: white; display: none; } /* Back button is hidden by default */
        .zoom-controls { position: fixed; top: 50%; right: 20px; transform: translateY(-50%); display: none; flex-direction: column; gap: 8px; z-index: 1000; } /* Hidden by default */
        .zoom-btn { width: 48px; height: 48px; background: rgba(255, 255, 255, 0.95); border: none; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); font-size: 20px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; touch-action: manipulation; }
        .selection-badge { position: fixed; top: 70px; right: 20px; background: #4CAF50; color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: bold; z-index: 1000; transform: scale(0); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .selection-badge.visible { transform: scale(1); }
        .mobile-toast { position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%); background: rgba(0, 0, 0, 0.8); color: white; padding: 12px 20px; border-radius: 25px; font-size: 14px; z-index: 2000; max-width: 90%; text-align: center; opacity: 0; transition: opacity 0.3s ease, transform 0.3s ease; pointer-events: none; }
        .mobile-toast.visible { opacity: 1; transform: translateX(-50%) translateY(-20px); }
        .element-selected { stroke: #007cba !important; stroke-width: 4px !important; filter: drop-shadow(0 0 5px #007cba); }
        .loading::after { content: ''; position: absolute; top: 50%; left: 50%; width: 40px; height: 40px; margin: -20px 0 0 -20px; border: 4px solid #f3f3f3; border-top: 4px solid #007cba; border-radius: 50%; animation: spin 1s linear infinite; z-index: 500; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        /* Other styles for slide menu, bottom sheet etc. are unchanged */
        .slide-menu { position: fixed; top: 0; right: -100%; width: 280px; height: 100vh; background: white; z-index: 1002; transition: right 0.3s ease; overflow-y: auto; box-shadow: -4px 0 12px rgba(0,0,0,0.2); }
        .slide-menu.open { right: 0; }
        .slide-menu-header { background: #007cba; color: white; padding: 16px; display: flex; align-items: center; justify-content: space-between; }
        .slide-menu h2 {
    font-size: 1.4em; /* Make the main title bigger */
}

.slide-menu h3 {
    font-size: 1.1em; /* Make section titles bigger */
    margin-bottom: 12px;
}

        .slide-menu-content { padding: 20px; }
        .menu-section { margin-bottom: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-control { width: 100%; padding: 15px 12px; min-height: 50px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; }
        .btn { padding: 16px 20px; border: none; border-radius: 10px; font-size: 16px; width: 100%; margin-bottom: 8px; }
        .btn-primary { background: #007cba; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .menu-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1001; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
        .menu-overlay.visible { opacity: 1; visibility: visible; }
        .error-message {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            padding: 20px;
            text-align: center;
            font-size: 1.1em;
            color: #d9534f; /* Red color for errors */
        }
        .error-message code {
            background: #f7f7f7;
            border: 1px solid #ddd;
            padding: 5px 10px;
            border-radius: 4px;
            margin-top: 15px;
            color: #333;
            direction: ltr; /* Ensure file path is shown correctly */
        }
      /* Add these CSS rules to fix the datepicker display */

/* Fix for Jalali Datepicker in slide menu */
.jdp-container {
    z-index: 1008 !important; /* Higher than slide menu z-index */
    position: fixed !important;
}

.jdp-overlay {
    z-index: 1008 !important;
    background: rgba(0, 0, 0, 0.5) !important;
}

/* Ensure the datepicker calendar is properly styled */
.jdp-calendar {
    background: white !important;
    border: 1px solid #ddd !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
    font-family: 'Segoe UI', Tahoma, Verdana, sans-serif !important;
    max-width: 320px !important;
    width: 100% !important;
}

/* Fix datepicker header */
.jdp-header {
    background: #007cba !important;
    color: white !important;
    padding: 10px !important;
    border-radius: 8px 8px 0 0 !important;
}

/* Fix datepicker navigation buttons */
.jdp-nav-btn {
    background: transparent !important;
    color: white !important;
    border: none !important;
    font-size: 18px !important;
    cursor: pointer !important;
}

/* Fix datepicker days */
.jdp-day {
    width: 32px !important;
    height: 32px !important;
    line-height: 32px !important;
    text-align: center !important;
    cursor: pointer !important;
    border-radius: 4px !important;
    margin: 1px !important;
}

.jdp-day:hover {
    background: #f0f8ff !important;
}

.jdp-day.jdp-selected {
    background: #007cba !important;
    color: white !important;
}

.jdp-day.jdp-today {
    background: #e8f4f8 !important;
    font-weight: bold !important;
}

/* Fix for mobile responsiveness */
@media (max-width: 480px) {
    .jdp-calendar {
        max-width: 300px !important;
        margin: 10px !important;
    }
    
    .jdp-container {
        padding: 20px 10px !important;
    }
}

.gfrc-checkboxes-grid {
    display: block; /* Changed from flex to block */
    padding: 10px 0;
}

.gfrc-checkbox-label {
    display: inline-flex; /* Changed from flex to inline-flex */
    align-items: center;
    gap: 10px;
    min-width: 80px;
    cursor: pointer;
     padding: 12px 15px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    transition: background-color 0.2s ease;
    margin: 4px; /* Add margin instead of using flex gap */
}

.gfrc-checkbox-label:hover {
    background-color: #e9ecef;
}

.gfrc-checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin: 0;
    cursor: pointer;
    appearance: auto; /* Make sure checkbox appearance is visible */
}

.checkbox-text {
    font-size: 15px;
    color: #333;
    user-select: none;
}

.gfrc-checkbox-label input[type="checkbox"]:checked + .checkbox-text {
    color: #007cba;
    font-weight: 600;
}

/* Make sure the container is visible */
#gfrc-parts-container {
    display: block;
}

#gfrc-parts-checkboxes {
    display: block !important; /* Force display */
}

/* Override any hiding styles */
.gfrc-checkbox-label[style*="display: none"] {
    display: none !important;
}

    </style>
</head>
<div class="page-content" data-user-role="<?php echo escapeHtml($_SESSION['role']); ?>">
    <!-- Add a custom header bar for this page if needed -->
    <div class="page-header" style="position: fixed; top: 60px; left: 0; right: 0; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); z-index: 999; padding: 8px 16px; display: none;" id="pageHeader">
        <button class="btn btn-primary" onclick="toggleMenu()" id="menu-hamburger-btn">☰</button>
    </div>

    <div id="navigationMenu">
        </div>

    <div id="svgContainer"></div>

    <div class="fab-container">
        <button class="fab back-button" id="backToMenuBtn" onclick="showMenuView()" title="بازگشت به منو">↰</button>
        <button class="fab" id="selectionFab" onclick="toggleSelectionMode()" title="Selection Mode">👆</button>
        <button class="fab" id="centerMapBtn" onclick="fitSvgToScreen()" title="Center Map">🎯</button>
    </div>

    <div class="zoom-controls" id="zoomControls">
        <button class="zoom-btn" onclick="zoomIn()">+</button>
        <button class="zoom-btn" onclick="zoomOut()">−</button>
        <button class="zoom-btn" onclick="fitSvgToScreen()">⌂</button>
    </div>
    
    <div class="selection-badge" id="selectionBadge"><span id="selectionCount">0</span> انتخاب شده</div>
    <div class="slide-menu" id="slideMenu">
        <div class="slide-menu-header">
            <h2>تنظیمات عملیات</h2>
            <button class="btn btn-secondary" onclick="toggleMenu()">✕</button>
        </div>
        <div class="slide-menu-content">
            <div class="menu-section">
                <h3>نوع عملیات</h3>
                <div class="form-group">
                    <select class="form-control" id="actionSelect">
                        <?php if ($is_contractor) : ?>
                            <option value="request-opening">۱. درخواست بازگشایی پانل</option>
                            <option value="confirm-opened">۳. بازگشایی انجام شد</option>
                        <?php endif; ?>
                        <?php if ($is_consultant) : ?>
                            <option value="approve-opening">۲. تایید درخواست بازگشایی</option>
                            <option value="reject-opening">۲. رد درخواست بازگشایی</option>
                            <option value="verify-opening">۴. بازبینی نهایی (تایید)</option>
                            <option value="dispute-opening">۴. بازبینی نهایی (رد)</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="menu-section" id="gfrcSection" style=" border: 2px solid red;">
                <h3>بخش‌های مورد نظر (برای GFRC):</h3>
                <div id="gfrc-parts-container">
                    <div id="gfrc-no-parts-message" style="color: #666; font-style: italic; padding: 10px; text-align: center;">
                        هیچ بخشی برای این عملیات در وضعیت مناسب قرار ندارد.
                    </div>
                    <div id="gfrc-parts-checkboxes" class="gfrc-checkboxes-grid">
                        <label class="gfrc-checkbox-label">
                            <input type="checkbox" class="gfrc-part-checkbox" value="face" checked>
                            <span class="checkbox-text">نما</span>
                        </label>
                        <label class="gfrc-checkbox-label">
                            <input type="checkbox" class="gfrc-part-checkbox" value="up" checked>
                            <span class="checkbox-text">بالا</span>
                        </label>
                        <label class="gfrc-checkbox-label">
                            <input type="checkbox" class="gfrc-part-checkbox" value="down" checked>
                            <span class="checkbox-text">پایین</span>
                        </label>
                        <label class="gfrc-checkbox-label">
                            <input type="checkbox" class="gfrc-part-checkbox" value="left" checked>
                            <span class="checkbox-text">چپ</span>
                        </label>
                        <label class="gfrc-checkbox-label">
                            <input type="checkbox" class="gfrc-part-checkbox" value="right" checked>
                            <span class="checkbox-text">راست</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="menu-section" id="detailsSection">
                <h3>جزئیات</h3>
               <div class="form-group">
    <label>تاریخ:</label>
    <div style="position: relative;">
        <input type="text" 
               class="form-control" 
               id="batchDate" 
               data-jdp 
               readonly 
               placeholder="انتخاب تاریخ..."
               style="cursor: pointer; background-color: white;">
        <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #666;">📅</span>
    </div>
</div>
                <div class="form-group">
                    <label>یادداشت:</label>
                    <textarea class="form-control" id="batchNotes" rows="3"></textarea>
                </div>
            </div>
            <div class="menu-section">
                <button class="btn btn-primary" onclick="submitUpdate()" id="submitBtn">ثبت عملیات</button>
                <button class="btn btn-secondary" onclick="clearSelection()">پاک کردن انتخاب</button>
            </div>
        </div>
    </div>
    <div class="mobile-toast" id="mobileToast"></div>
    <div class="menu-overlay" id="menuOverlay" onclick="toggleMenu()"></div>
<script src="/ghom/assets/js/jalalidatepicker.min.js"></script>
<script>
// ===================================================================================
// DYNAMIC MOBILE SVG APPLICATION SCRIPT V2.2 - FINAL & COMPLETE
// ===================================================================================

// --- SECTION 1: GLOBAL STATE ---
let selectedElements = new Map();
let isMobileSelectionMode = false;
let isMenuOpen = false;
let currentPlanDbData = {};
const SVG_BASE_PATH = "/ghom/PreInspectionsSvg/";
// Pan & Zoom State
let isPanning = false,
  isMarqueeSelecting = false;
let panX = 0,
  panY = 0,
  zoom = 1;
let panStart = { x: 0, y: 0 },
  marqueeStartPoint = { x: 0, y: 0 },
  selectionBox = null;
// Touch State
let longPressTimer = null,
  hasTouchMoved = false,
  touchStartPos = { x: 0, y: 0 },
  initialTouchDistance = 0;
const LONG_PRESS_DURATION = 500,
  TOUCH_MOVE_THRESHOLD = 15;

// --- SECTION 2: INITIALIZATION & VIEW ROUTING ---
document.addEventListener("DOMContentLoaded", () => {
    setupEventListeners();
    
    // Handle deep links first
    handleDeepLink().then(handled => {
        if (!handled) {
            const urlParams = new URLSearchParams(window.location.search);
            const planFile = urlParams.get("plan");
            if (planFile) {
                fetchAndDisplaySVG(SVG_BASE_PATH + planFile);
            } else {
                buildNavigationMenu();
            }
        }
    });
    
    // Improved Jalali Datepicker initialization
    setTimeout(() => {
        try {
            jalaliDatepicker.startWatch({ 
                container: document.body, // Use body instead of slideMenu for better positioning
                minDate: "today",
                format: "YYYY-MM-DD",
                showTodayBtn: true,
                showEmptyBtn: true,
                todayBtnText: "امروز",
                emptyBtnText: "خالی",
                showCloseBtn: true,
                closeBtnText: "بستن",
                showGoToToday: true,
                locale: "fa",
                zIndex: 1006, // Ensure it's above all other elements
                // Add event handlers
                onSelect: function(date) {
                    console.log('Date selected:', date);
                },
                onShow: function() {
                    // Ensure proper positioning when shown
                    const calendar = document.querySelector('.jdp-container');
                    if (calendar) {
                        calendar.style.zIndex = '1006';
                        calendar.style.position = 'fixed';
                    }
                }
            });
        } catch (error) {
            console.error('Datepicker initialization error:', error);
        }
    }, 500);
});

// Optional: Add a function to manually trigger the datepicker if needed
function openDatePicker() {
    const dateInput = document.getElementById('batchDate');
    if (dateInput) {
        dateInput.focus();
        dateInput.click();
    }
}
const CONTRACTOR_MAPPINGS = {
    'ROS': 'Atieh',      // آتیه نما -> Atieh regions
    'ROS': 'rosB',     // رس -> rosB and rosC regions  
    'OMAZ': 'hayatOmran', // عمران آذرستان -> hayatOmran regions
    'ROS': 'hayatRos', 
     'OMAZ': ' rosC ',
      // ساختمانی رس -> hayatRos regions
};

// If your user roles in the session are different, you might need to check:
// 1. What exact value is stored in $_SESSION['role'] for contractors
// 2. Are contractor roles stored as 'AT', 'ARJ', etc. or something else?
function debugSelection() {
    console.log('=== Selection Debug ===');
    console.log('Selected elements:', selectedElements);
    console.log('Selected elements array:', Array.from(selectedElements.values()));
    
    Array.from(selectedElements.values()).forEach((item, index) => {
        console.log(`Element ${index}:`, {
            id: item.id,
            type: item.type,
            element: item.element,
            dataset: item.element.dataset
        });
    });
    
    const hasGFRC = Array.from(selectedElements.values()).some(item => item.type === "GFRC");
    console.log('Has GFRC elements:', hasGFRC);
}
// You can also add this debug function to see what's happening:
function debugUserAccess() {
    const userRole = document.body.dataset.userRole;
    console.log('=== DEBUG INFO ===');
    console.log('Current user role:', userRole);
    console.log('Available contractor codes:', Object.keys(CONTRACTOR_MAPPINGS));
    console.log('Regions in regionToZoneMap:', Object.keys(regionToZoneMap));
    
    // Check which regions this user should have access to
    Object.keys(regionToZoneMap).forEach(regionKey => {
        const regionConfig = svgGroupConfig[regionKey];
        if (regionConfig && regionConfig.contractoren === userRole) {
            console.log(`User ${userRole} should have access to region: ${regionKey}`);
        }
    });
}
function showPageHeader() {
    document.getElementById('pageHeader').style.display = 'flex';
}

function hidePageHeader() {
    document.getElementById('pageHeader').style.display = 'none';
}
function setupEventListeners() {
    const container = document.getElementById("svgContainer");
    container.addEventListener("touchstart", handleTouchStart, { passive: false });
    container.addEventListener("touchmove", handleTouchMove, { passive: false });
    container.addEventListener("touchend", handleTouchEnd, { passive: false });

    // Updated action select event listener
    document.getElementById("actionSelect").addEventListener("change", (e) => {
        const consultantActions = ["approve-opening", "reject-opening", "verify-opening", "dispute-opening"];
        document.getElementById("detailsSection").style.display = 
            consultantActions.includes(e.target.value) ? "none" : "block";
        
        // Update GFRC checklist and existing submissions check when action changes
        updateGfrcPartsChecklist();
        checkAndUpdateOption(e.target.value);
    });
}

async function updateGfrcPartsChecklist() {
    const gfrcContainer = document.getElementById("gfrc-parts-container");
    const actionSelect = document.getElementById("actionSelect");
    const messageDiv = document.getElementById("gfrc-no-parts-message");
    const checkboxesDiv = document.getElementById("gfrc-parts-checkboxes");
    
    if (!gfrcContainer || !actionSelect || !messageDiv || !checkboxesDiv) {
        console.log('Missing GFRC elements:', {
            gfrcContainer: !!gfrcContainer,
            actionSelect: !!actionSelect, 
            messageDiv: !!messageDiv,
            checkboxesDiv: !!checkboxesDiv
        });
        return;
    }

    const elements = Array.from(selectedElements.values());
    const gfrcElements = elements.filter(el => el.type === "GFRC");

    if (gfrcElements.length === 0) {
        gfrcContainer.style.display = "none";
        return;
    }

    gfrcContainer.style.display = "block";
    const action = actionSelect.value;
    const element_ids = gfrcElements.map(el => el.id);

    // Hide both elements initially to prevent flicker
    messageDiv.style.display = "none";
    checkboxesDiv.style.display = "none";

    if (action === "request-opening") {
        // For the initial request, show the default checklist
        checkboxesDiv.style.display = "flex";
        const allCheckboxes = document.querySelectorAll(".gfrc-part-checkbox");
        allCheckboxes.forEach(checkbox => {
            const partLabel = checkbox.closest("label");
            if (partLabel) {
                partLabel.style.display = "flex";
                checkbox.checked = true;
                checkbox.disabled = false;
            }
        });
    } else {
        // For all other actions, use the smart filtering
        try {
            const response = await fetch("/ghom/api/get_eligible_parts.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action, element_ids })
            });

            if (!response.ok) throw new Error("API response was not ok.");
            
            const eligiblePartsByElement = await response.json();
            const allEligibleParts = new Set(Object.values(eligiblePartsByElement).flat());

            if (allEligibleParts.size === 0) {
                // No eligible parts found, show the message
                messageDiv.textContent = "هیچ بخشی برای این عملیات در وضعیت مناسب قرار ندارد.";
                messageDiv.style.display = "block";
                checkboxesDiv.style.display = "none";
            } else {
                // Eligible parts found, show the checklist
                checkboxesDiv.style.display = "flex";
                messageDiv.style.display = "none";

                const allCheckboxes = document.querySelectorAll(".gfrc-part-checkbox");
                allCheckboxes.forEach(checkbox => {
                    const partLabel = checkbox.closest("label");
                    if (partLabel) {
                        if (allEligibleParts.has(checkbox.value)) {
                            partLabel.style.display = "flex";
                            checkbox.checked = true;
                            checkbox.disabled = false;
                        } else {
                            partLabel.style.display = "none";
                            checkbox.checked = false;
                        }
                    }
                });
            }
        } catch (error) {
            console.error("Error updating GFRC checklist:", error);
            messageDiv.textContent = "خطا در بارگذاری اطلاعات بخش‌ها.";
            messageDiv.style.display = "block";
            checkboxesDiv.style.display = "none";
        }
    }
}

function debugGfrcSection() {
    console.log('=== GFRC Debug ===');
    console.log('Selected elements:', Array.from(selectedElements.values()));
    console.log('GFRC elements:', Array.from(selectedElements.values()).filter(el => el.type === "GFRC"));
    console.log('gfrcSection display:', document.getElementById("gfrcSection")?.style.display);
    console.log('Checkboxes found:', document.querySelectorAll(".gfrc-part-checkbox").length);
    
    // Call the update function
    updateGfrcPartsChecklist();
}

/**
 * Check for existing submissions and update UI
 */
async function checkAndUpdateOption(stageId) {
    const updateContainer = document.getElementById("update-existing-container");
    const updateCheckbox = document.getElementById("update_existing_checkbox");
    
    // Always hide and reset first
    if (updateContainer) updateContainer.style.display = "none";
    if (updateCheckbox) updateCheckbox.checked = false;

    if (!stageId || selectedElements.size === 0) return;

    try {
        const selectedIds = Array.from(selectedElements.keys());
        const checkResponse = await fetch(`/ghom/api/check_existing_for_stage.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                element_ids: selectedIds,
                stage_id: stageId
            })
        });

        const existingData = await checkResponse.json();
        if (existingData.count > 0 && updateContainer) {
            document.getElementById("existingCount").textContent = existingData.count;
            updateContainer.style.display = "block";
        }
    } catch (error) {
        console.error("Error checking existing submissions:", error);
    }
}

const svgGroupConfig = {
  GFRC: {
    label: "GFRC",
    colors: {
      v: "rgba(13, 110, 253, 0.7)", // A clear, standard Blue
      h: "rgba(0, 150, 136, 0.75)", // A contrasting Teal/Cyan
    },
    defaultVisible: true,
    interactive: true,
    elementType: "GFRC",
  },
  Box_40x80x4: {
    label: "Box_40x80x4",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  Box_40x20: {
    label: "Box_40x20",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  tasme: {
    label: "تسمه",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  nabshi_tooli: {
    label: "نبشی طولی",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  Gasket: {
    label: "Gasket",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  SPACER: {
    label: "فاصله گذار",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  Smoke_Barrier: {
    label: "دودبند",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  uchanel: {
    label: "یو چنل",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  unolite: {
    label: "یونولیت",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  "GFRC-Part6": {
    label: "GFRC - قسمت 6",
    defaultVisible: true,
    interactive: true,
    elementType: "GFRC",
  },
  "GFRC-Part_4": {
    label: "GFRC - قسمت 4",
    defaultVisible: true,
    interactive: true,
    elementType: "GFRC",
  },
  Atieh: {
    label: "بلوک A- آتیه نما",
    color: "#0de16d",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت آتیه نما",
    block: "A",
    elementType: "Region",
    contractor_id: "crs",
  },
  org: {
    label: "بلوک - اورژانس A- آتیه نما",
    color: "#ebb00d",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت آتیه نما",
    block: "A - اورژانس",
    elementType: "Region",
    contractor_id: "crs",
  },
  rosB: {
    label: "بلوک B-رس",
    color: "#38abee",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت رس",
    block: "B",
    elementType: "Region",
    contractor_id: "crs",
  },
  rosC: {
    label: "بلوک C-رس",
    color: "#ee3838",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت رس",
    block: "C",
    elementType: "Region",
    contractor_id: "coa",
  },
  hayatOmran: {
    label: " حیاط عمران آذرستان",
    color: "#eef595da",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت عمران آذرستان",
    block: "حیاط",
    elementType: "Region",
    contractor_id: "coa",
  },
  hayatRos: {
    label: " حیاط رس",
    color: "#eb0de7da",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت ساختمانی رس",
    block: "حیاط",
    elementType: "Region",
    contractor_id: "crs",
  },
  handrail: {
    label: "نقشه ندارد",
    color: "rgba(238, 56, 56, 0.3)",
    defaultVisible: true,
    interactive: true,
  },
  "glass_40%": {
    label: "شیشه 40%",
    color: "rgba(173, 216, 230, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Glass",
  },
  "glass_30%": {
    label: "شیشه 30%",
    color: "rgba(173, 216, 230, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Glass",
  },
  "glass_50%": {
    label: "شیشه 50%",
    color: "rgba(173, 216, 230, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Glass",
  },
  glass_opaque: {
    label: "شیشه مات",
    color: "rgba(144, 238, 144, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Glass",
  },
  "glass_80%": {
    label: "شیشه 80%",
    color: "rgba(255, 255, 102, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Glass",
  },
  Mullion: {
    label: "مولیون",
    color: "rgba(128, 128, 128, 0.9)",
    defaultVisible: true,
    interactive: true,
    elementType: "Mullion",
  },
  Transom: {
    label: "ترنزوم",
    color: "rgba(169, 169, 169, 0.9)",
    defaultVisible: true,
    interactive: true,
    elementType: "Transom",
  },
  Bazshow: {
    label: "بازشو",
    color: "rgba(169, 169, 169, 0.9)",
    defaultVisible: true,
    interactive: true,
    elementType: "Bazshow",
  },
  GLASS: {
    label: "شیشه",
    color: "#eef595da",
    defaultVisible: true,
    interactive: true,
    elementType: "Glass",
  },
  STONE: {
    label: "سنگ",
    color: "#4c28a1",
    defaultVisible: true,
    interactive: true,
    elementType: "STONE",
  },
  Zirsazi: {
    label: "زیرسازی",
    color: "#2464ee",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  Curtainwall: {
    label: "کرتین وال",
    color: "#4c28a1",
    defaultVisible: true,
    interactive: true,
    elementType: "Curtainwall",
  },
};

async function buildNavigationMenu() {
    showMenuView();
    const menuContainer = document.getElementById("navigationMenu");
    menuContainer.innerHTML = "";
    
    const loadingDiv = document.createElement("div");
    loadingDiv.className = "loading-menu";
    loadingDiv.style.textAlign = "center";
    loadingDiv.style.padding = "40px";
    loadingDiv.textContent = "در حال بارگذاری منو...";
    menuContainer.appendChild(loadingDiv);

    try {
      const cacheBuster = '?t=' + new Date().getTime();
        const regionRes = await fetch("/ghom/assets/js/regionToZoneMap.json" + cacheBuster);
        if (!regionRes.ok) throw new Error("Failed to load regionToZoneMap.json");
        
        const regionToZoneMap = await regionRes.json();
        menuContainer.innerHTML = "";

        const userRole = document.body.dataset.userRole;
        const isAdmin = userRole === 'admin' || userRole === 'superuser';
        let accessibleRegionsFound = 0;

        console.log('User Role:', userRole);

        for (const regionKey in regionToZoneMap) {
            const regionConfig = svgGroupConfig[regionKey];
            if (!regionConfig) continue;

            const hasPermission = isAdmin || 
                (regionConfig.contractor_id && regionConfig.contractor_id === userRole);

            if (!hasPermission) continue;

            const zones = regionToZoneMap[regionKey];
            if (!zones || zones.length === 0) continue;

            accessibleRegionsFound++;
            const card = document.createElement("div");
            card.className = "region-card";
            card.innerHTML = `<div class="region-card-header">${regionConfig.label}</div>`;

            const zoneList = document.createElement("div");
            zoneList.className = "zone-list";

            zones.forEach(zone => {
                const zoneButton = document.createElement("button");
                zoneButton.className = "zone-button";
                zoneButton.textContent = zone.label;
                zoneButton.onclick = () => {
                    const newUrl = `${window.location.pathname}?plan=${zone.svgFile}`;
                    history.pushState({ path: newUrl }, "", newUrl);
                    fetchAndDisplaySVG(SVG_BASE_PATH + zone.svgFile);
                };
                zoneList.appendChild(zoneButton);
            });

            card.appendChild(zoneList);
            menuContainer.appendChild(card);
        }

        if (accessibleRegionsFound === 0) {
            menuContainer.innerHTML = `
                <div style="text-align:center; padding: 20px;">
                    <p>هیچ محدوده ای برای شما تعریف نشده است.</p>
                    <p style="font-size: 0.8em; color: #666; margin-top: 10px;">نقش شما: ${userRole}</p>
                </div>`;
        }
        
    } catch (error) {
        console.error('Navigation menu error:', error);
        menuContainer.innerHTML = `<p style="color:red;padding:20px;">خطا در بارگذاری منو: ${error.message}</p>`;
    }
}


function showMenuView() {
    document.title = "انتخاب نقشه";
    document.getElementById("navigationMenu").style.display = "flex";
    document.getElementById("svgContainer").classList.remove("visible");
    document.getElementById("zoomControls").style.display = "none";
    document.getElementById("selectionFab").style.display = "none";
    document.getElementById("centerMapBtn").style.display = "none";
    document.getElementById("backToMenuBtn").style.display = "none";
    hidePageHeader(); // Hide the page-specific header
    clearSelection();
    history.pushState(
        { path: window.location.pathname },
        "",
        window.location.pathname
    );
}

function showSvgView(planName = "نقشه") {
    document.title = planName;

    const navMenu = document.getElementById('navigationMenu');
    if (navMenu) navMenu.style.display = 'none';
    
    const svgContainer = document.getElementById('svgContainer');
    if (svgContainer) svgContainer.classList.add('visible');

    const zoomControls = document.getElementById('zoomControls');
    if (zoomControls) zoomControls.style.display = 'flex';

    const fabButtons = ['selectionFab', 'centerMapBtn', 'backToMenuBtn'];
    fabButtons.forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.style.display = 'flex';
    });

    showPageHeader(); // Show the page-specific header
}


window.onpopstate = () => {
  const urlParams = new URLSearchParams(window.location.search);
  const planFile = urlParams.get("plan");
  if (planFile) {
    fetchAndDisplaySVG(SVG_BASE_PATH + planFile);
  } else {
    showMenuView();
  }
};

function openMobileNav() {
    document.getElementById('mobileNavMenu').style.right = '0';
    document.getElementById('mobileNavOverlay').style.opacity = '1';
    document.getElementById('mobileNavOverlay').style.visibility = 'visible';
}

function closeMobileNav() {
    document.getElementById('mobileNavMenu').style.right = '-300px';
    document.getElementById('mobileNavOverlay').style.opacity = '0';
    document.getElementById('mobileNavOverlay').style.visibility = 'hidden';
}
// --- SECTION 4: SVG & DATA HANDLING (REVISED WITH BETTER ERROR HANDLING) ---
async function fetchAndDisplaySVG(svgPath) {
  const baseFilename = svgPath.substring(svgPath.lastIndexOf("/") + 1);
  showSvgView(baseFilename.replace(".svg", ""));
  const container = document.getElementById("svgContainer");
  container.classList.add("loading");
  container.innerHTML = "";
  const cacheBuster = '?t=' + new Date().getTime();
  const urlWithCacheBuster = svgPath + cacheBuster;
  try {
    // Step 1: Fetch both the SVG file and the data from the API
    const [svgRes, dataRes] = await Promise.all([
      fetch(urlWithCacheBuster),
      fetch(`/ghom/api/get_plan_elements_pre.php?plan=${baseFilename}`),
    ]);

    // Step 2: Check if the SVG file was found (e.g., handle 404 Not Found)
    if (!svgRes.ok) {
      throw new Error(
        `فایل نقشه یافت نشد (خطای ${svgRes.status}). مسیر بررسی شده:`
      );
    }
    const svgData = await svgRes.text();

    // Step 3: Check if the returned SVG content is valid
    if (!svgData || !svgData.includes("<svg")) {
      throw new Error(`محتوای فایل نقشه نامعتبر یا خالی است. مسیر بررسی شده:`);
    }

    // Step 4: Check if the API data was found
    if (!dataRes.ok) {
      throw new Error(
        `اطلاعات نقشه از سرور دریافت نشد (خطای ${dataRes.status}).`
      );
    }
    currentPlanDbData = await dataRes.json();

    // Step 5: If all checks pass, display the SVG
    container.innerHTML = svgData;
    initializeInteractiveElements();
    fitSvgToScreen();
  } catch (error) {
    // If any step fails, display a clear error message on the screen
    container.innerHTML = `
                <div class="error-message">
                    <p><strong>خطا در بارگذاری نقشه</strong></p>
                    <p>${error.message}</p>
                    <code>${svgPath}</code>
                </div>
            `;
  } finally {
    container.classList.remove("loading");
  }
}

// REPLACE your old function with this new, corrected version
function initializeInteractiveElements() {
    const svg = document.querySelector('#svgContainer svg');
    if (!svg) return;

    svg.removeAttribute('width');
    svg.removeAttribute('height');
    
    const STATUS_COLORS = { "Request to Open": "rgba(13, 202, 240, 0.7)", "Opening Approved": "rgba(25, 135, 84, 0.7)", "Opening Rejected": "rgba(220, 53, 69, 0.7)", "Panel Opened": "rgba(255, 193, 7, 0.7)", "Opening Disputed": "rgba(220, 53, 69, 0.9)", "Pre-Inspection Complete": "rgba(40, 167, 69, 0.7)", "Pending": "rgba(108, 117, 125, 0.4)" };
    
    svg.querySelectorAll('path, rect, polygon, circle').forEach(el => {
        if (!el.id) return; // Skip elements without an ID

        el.classList.add('interactive-element');
        el.dataset.uniqueId = el.id;

        let elementType = 'Unknown';
        let status = 'Pending';

        // **FIXED LOGIC STARTS HERE**

        // 1. Prioritize the specific data from the API if it exists
        if (currentPlanDbData[el.id] && currentPlanDbData[el.id].element_type) {
            const data = currentPlanDbData[el.id];
            elementType = data.element_type;
            status = data.status || 'Pending';
        } 
        // 2. If not found, fall back to the parent group's config
        else {
            const parentGroup = el.closest('g'); // Find the parent <g> tag
            const groupId = parentGroup ? parentGroup.id : null;

            if (groupId && svgGroupConfig[groupId] && svgGroupConfig[groupId].elementType) {
                elementType = svgGroupConfig[groupId].elementType;
            }
        }
        
        // If there's still API data for status (even without type), use it
        if (currentPlanDbData[el.id] && currentPlanDbData[el.id].status) {
            status = currentPlanDbData[el.id].status;
        }

        // 3. Assign the determined properties
        el.dataset.elementType = elementType;
        el.dataset.status = status;
        el.style.fill = STATUS_COLORS[status] || STATUS_COLORS['Pending'];
        
        // **FIXED LOGIC ENDS HERE**
    });
}
// --- SECTION 5: TOUCH, PAN, & ZOOM LOGIC ---
function handleTouchStart(e) {
  const touches = e.touches;
  hasTouchMoved = false;
  if (longPressTimer) clearTimeout(longPressTimer);
  if (touches.length === 1) {
    const touch = touches[0],
      rect = e.currentTarget.getBoundingClientRect();
    touchStartPos = {
      x: touch.clientX - rect.left,
      y: touch.clientY - rect.top,
    };
    const targetElement = e.target.closest(".interactive-element");
    if (!targetElement) {
      longPressTimer = setTimeout(() => {
        if (!hasTouchMoved && isMobileSelectionMode) {
          startMarqueeSelection(touchStartPos);
          if (navigator.vibrate) navigator.vibrate(50);
        }
      }, LONG_PRESS_DURATION);
      startPanning(touch.clientX, touch.clientY);
    } else {
      longPressTimer = setTimeout(() => {
        if (!hasTouchMoved) {
          enableSelectionMode();
          toggleElementSelection(targetElement);
          if (navigator.vibrate) navigator.vibrate(50);
        }
      }, LONG_PRESS_DURATION);
    }
  } else if (touches.length === 2) {
    e.preventDefault();
    const [t1, t2] = touches;
    initialTouchDistance = Math.hypot(
      t2.clientX - t1.clientX,
      t2.clientY - t1.clientY
    );
  }
}
function handleTouchMove(e) {
  if (!e.touches || e.touches.length === 0) return;
  const touch = e.touches[0];
  const dist = Math.hypot(
    touch.clientX - touchStartPos.x,
    touch.clientY - touchStartPos.y
  );
  if (dist > TOUCH_MOVE_THRESHOLD) {
    hasTouchMoved = true;
    if (longPressTimer) clearTimeout(longPressTimer);
  }
  if (e.touches.length === 1) {
    if (isPanning) continuePanning(touch.clientX, touch.clientY);
    if (isMarqueeSelecting)
      continueMarqueeSelection(touch.clientX, touch.clientY);
  } else if (e.touches.length === 2) {
    e.preventDefault();
    const [t1, t2] = e.touches;
    const currentDist = Math.hypot(
      t2.clientX - t1.clientX,
      t2.clientY - t1.clientY
    );
    if (initialTouchDistance > 0) {
      const scale = currentDist / initialTouchDistance,
        center = {
          x: (t1.clientX + t2.clientX) / 2,
          y: (t1.clientY + t2.clientY) / 2,
        };
      zoomToPoint(scale, center);
    }
    initialTouchDistance = currentDist;
  }
}
function handleTouchEnd(e) {
  if (longPressTimer) clearTimeout(longPressTimer);
  if (isPanning) stopPanning();
  if (isMarqueeSelecting) finishMarqueeSelection();
  const targetElement = e.target.closest(".interactive-element");
  if (targetElement && !hasTouchMoved) {
    e.preventDefault();
    if (isMobileSelectionMode) {
      toggleElementSelection(targetElement);
    }
  }
  if (e.touches.length === 0) {
    initialTouchDistance = 0;
    hasTouchMoved = false;
  }
}
function startPanning(clientX, clientY) {
  isPanning = true;
  panStart = { x: clientX, y: clientY };
  document.getElementById("svgContainer").style.cursor = "grabbing";
}
function continuePanning(clientX, clientY) {
  if (!isPanning) return;
  panX += clientX - panStart.x;
  panY += clientY - panStart.y;
  panStart = { x: clientX, y: clientY };
  updateTransform();
}
function stopPanning() {
  isPanning = false;
  document.getElementById("svgContainer").style.cursor = "grab";
}
function zoomToPoint(scale, point) {
  const rect = document.getElementById("svgContainer").getBoundingClientRect();
  const centerPoint = { x: point.x - rect.left, y: point.y - rect.top };
  const newZoom = Math.max(0.2, Math.min(10, zoom * scale));
  const zoomRatio = newZoom / zoom;
  panX = centerPoint.x - (centerPoint.x - panX) * zoomRatio;
  panY = centerPoint.y - (centerPoint.y - panY) * zoomRatio;
  zoom = newZoom;
  updateTransform();
}
function zoomIn() {
  const c = document.getElementById("svgContainer").getBoundingClientRect();
  zoomToPoint(1.4, { x: c.left + c.width / 2, y: c.top + c.height / 2 });
}
function zoomOut() {
  const c = document.getElementById("svgContainer").getBoundingClientRect();
  zoomToPoint(0.7, { x: c.left + c.width / 2, y: c.top + c.height / 2 });
}
function fitSvgToScreen() {
        const container = document.getElementById('svgContainer');
        const svg = container.querySelector('svg');
        if (!svg || !svg.viewBox?.baseVal) return;

        const containerRect = container.getBoundingClientRect();
        const svgViewBox = svg.viewBox.baseVal;

        // Calculate the best scale to fit the whole SVG inside the container
        const scaleX = containerRect.width / svgViewBox.width;
        const scaleY = containerRect.height / svgViewBox.height;
        const bestScale = Math.min(scaleX, scaleY) * 0.95; // Use 95% scale for a little padding

        // Calculate the position to center the SVG
        const newWidth = svgViewBox.width * bestScale;
        const newHeight = svgViewBox.height * bestScale;
        const newPanX = (containerRect.width - newWidth) / 2;
        const newPanY = (containerRect.height - newHeight) / 2;
        
        // Apply the new transform
        zoom = bestScale;
        panX = newPanX;
        panY = newPanY;
        updateTransform();
        showToast('نقشه در مرکز قرار گرفت', 'info');
    }

function updateTransform() {
  const svg = document.querySelector("#svgContainer svg");
  if (svg)
    svg.style.transform = `translate(${panX}px, ${panY}px) scale(${zoom})`;
}

// --- SECTION 6: SELECTION LOGIC ---
function startMarqueeSelection(startPos) {
  isMarqueeSelecting = true;
  marqueeStartPoint = startPos;
  selectionBox = document.createElement("div");
  selectionBox.id = "selection-box";
  Object.assign(selectionBox.style, {
    position: "absolute",
    border: "2px dashed #007cba",
    background: "rgba(0, 124, 186, 0.1)",
    pointerEvents: "none",
    zIndex: "999",
    left: `${startPos.x}px`,
    top: `${startPos.y}px`,
    width: "0",
    height: "0",
  });
  document.getElementById("svgContainer").appendChild(selectionBox);
}
function continueMarqueeSelection(clientX, clientY) {
  if (!isMarqueeSelecting || !selectionBox) return;
  const rect = document.getElementById("svgContainer").getBoundingClientRect();
  const currentX = clientX - rect.left,
    currentY = clientY - rect.top;
  const left = Math.min(currentX, marqueeStartPoint.x),
    top = Math.min(currentY, marqueeStartPoint.y);
  const width = Math.abs(currentX - marqueeStartPoint.x),
    height = Math.abs(currentY - marqueeStartPoint.y);
  Object.assign(selectionBox.style, {
    left: `${left}px`,
    top: `${top}px`,
    width: `${width}px`,
    height: `${height}px`,
  });
}
function finishMarqueeSelection() {
  if (!isMarqueeSelecting || !selectionBox) return;
  const boxRect = selectionBox.getBoundingClientRect();
  let count = 0;
  document.querySelectorAll(".interactive-element").forEach((el) => {
    if (el.style.display === "none") return;
    const elRect = el.getBoundingClientRect();
    if (
      boxRect.right > elRect.left &&
      boxRect.left < elRect.right &&
      boxRect.bottom > elRect.top &&
      boxRect.top < elRect.bottom
    ) {
      toggleElementSelection(el, true);
      count++;
    }
  });
  if (count > 0) showToast(`${count} المان انتخاب شد`, "success");
  selectionBox.remove();
  selectionBox = null;
  isMarqueeSelecting = false;
}
function toggleSelectionMode() {
  isMobileSelectionMode = !isMobileSelectionMode;
  const fab = document.getElementById("selectionFab");
  fab.classList.toggle("selection-mode", isMobileSelectionMode);
  fab.innerHTML = isMobileSelectionMode ? "🎯" : "👆";
  showToast(
    isMobileSelectionMode ? "حالت انتخاب فعال شد" : "حالت انتخاب غیرفعال شد",
    "info"
  );
  if (!isMobileSelectionMode) clearSelection();
}
function enableSelectionMode() {
  if (!isMobileSelectionMode) toggleSelectionMode();
}
function toggleElementSelection(element, forceSelect = false) {
  const id = element.dataset.uniqueId;
  if (!id) return;
  if (forceSelect || !selectedElements.has(id)) {
    element.classList.add("element-selected");
    selectedElements.set(id, {
      element: element,
      id: id,
      type: element.dataset.elementType,
    });
  } else {
    element.classList.remove("element-selected");
    selectedElements.delete(id);
  }
  updateSelectionUI();
}
function clearSelection() {
  selectedElements.forEach((item) =>
    item.element.classList.remove("element-selected")
  );
  selectedElements.clear();
  updateSelectionUI();
  if (isMenuOpen) toggleMenu();
}

// --- SECTION 7: UI & API CALLS ---
// Replace your existing updateSelectionUI() function with this updated version
function updateSelectionUI() {
    const count = selectedElements.size;
    const badge = document.getElementById("selectionBadge");
    document.getElementById("selectionCount").textContent = count;
    badge.classList.toggle("visible", count > 0);
    
    const hasGFRC = Array.from(selectedElements.values()).some(item => item.type === "GFRC");
    
    console.log('=== DEBUG updateSelectionUI ===');
    console.log('Selected elements count:', count);
    console.log('Has GFRC:', hasGFRC);
    console.log('Selected elements types:', Array.from(selectedElements.values()).map(item => item.type));
    
    const gfrcSection = document.getElementById("gfrcSection");
    if (gfrcSection) {
        if (hasGFRC) {
            gfrcSection.style.display = "block"; // Show the section
            console.log('GFRC section shown');
        } else {
            gfrcSection.style.display = "none"; // Hide the section
            console.log('GFRC section hidden');
        }
    } else {
        console.log('GFRC section element not found!');
    }
    
    // Update GFRC parts checklist when selection changes
    if (hasGFRC) {
        updateGfrcPartsChecklist();
    }
    
    // Check for existing submissions if an action is selected
    const actionSelect = document.getElementById("actionSelect");
    if (actionSelect && actionSelect.value && count > 0) {
        checkAndUpdateOption(actionSelect.value);
    }
}


function toggleMenu() {
  isMenuOpen = !isMenuOpen;
  document.getElementById("slideMenu").classList.toggle("open", isMenuOpen);
  document
    .getElementById("menuOverlay")
    .classList.toggle("visible", isMenuOpen);
}
function showToast(message, type = "info", duration = 3000) {
  const toast = document.getElementById("mobileToast");
  toast.textContent = message;
  toast.style.backgroundColor =
    type === "error" ? "#dc3545" : type === "success" ? "#28a745" : "#333";
  toast.classList.add("visible");
  setTimeout(() => toast.classList.remove("visible"), duration);
}
// Add this function to handle deep links
async function handleDeepLink() {
    const urlParams = new URLSearchParams(window.location.search);
    const planFile = urlParams.get("plan");
    const elementIdsFromUrl = urlParams.get("element_id")?.split(",");
    
    if (planFile && elementIdsFromUrl && elementIdsFromUrl.length > 0) {
        console.log(`Deep Link Detected. Plan: '${planFile}', Element IDs:`, elementIdsFromUrl);
        
        await fetchAndDisplaySVG(SVG_BASE_PATH + planFile);
        
        setTimeout(() => {
            let elementsFound = 0;
            clearSelection();
            
            elementIdsFromUrl.forEach(rawId => {
                const cleanedId = rawId.trim().replace(/\s/g, "");
                let baseElementId = cleanedId.split("-").slice(0, -1).join("-");
                
                if (!["face", "up", "down", "left", "right", "default"].includes(cleanedId.split("-").pop())) {
                    baseElementId = cleanedId;
                }
                
                const elementInSvg = document.getElementById(baseElementId);
                if (elementInSvg) {
                    toggleElementSelection(elementInSvg, true);
                    elementsFound++;
                } else {
                    console.warn(`Deep-linked element with base ID '${baseElementId}' not found.`);
                }
            });
            
            if (elementsFound > 0) {
                console.log(`Successfully selected ${elementsFound} element(s).`);
                enableSelectionMode();
                toggleMenu(); // Open the menu to show selection
            } else {
                showToast(`خطا: هیچکدام از المان‌های مشخص شده در نقشه '${planFile}' یافت نشدند.`, 'error');
            }
        }, 500);
        
        return true;
    }
    return false;
}

// Update your DOMContentLoaded event to include deep link handling
document.addEventListener("DOMContentLoaded", () => {
    setupEventListeners();
    
    // Handle deep links first
    handleDeepLink().then(handled => {
        if (!handled) {
            // No deep link, proceed with normal initialization
            const urlParams = new URLSearchParams(window.location.search);
            const planFile = urlParams.get("plan");
            if (planFile) {
                fetchAndDisplaySVG(SVG_BASE_PATH + planFile);
            } else {
                buildNavigationMenu();
            }
        }
    });
    
    jalaliDatepicker.startWatch({ 
        container: document.getElementById('slideMenu'), 
        minDate: "today"
    });
});

async function submitUpdate() {
    if (selectedElements.size === 0) 
        return showToast("لطفاً ابتدا المانی را انتخاب کنید", "error");

    const submitBtn = document.getElementById("submitBtn");
    submitBtn.disabled = true;
    submitBtn.textContent = "در حال ارسال...";

    const payload = {
        action: document.getElementById("actionSelect").value,
        element_ids: Array.from(selectedElements.keys()),
        date: document.getElementById("batchDate").value,
        notes: document.getElementById("batchNotes").value,
        parts: Array.from(document.querySelectorAll(".gfrc-part-checkbox:checked")).map(cb => cb.value),
        update_existing: document.getElementById("update_existing_checkbox")?.checked || false
    };

    try {
        const response = await fetch("/ghom/api/batch_update_status.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        if (!response.ok) throw new Error(result.message || "خطای سرور");

        showToast(result.message, "success");
        clearSelection();

        const urlParams = new URLSearchParams(window.location.search);
        const planFile = urlParams.get("plan");
        if (planFile) fetchAndDisplaySVG(SVG_BASE_PATH + planFile);

    } catch (error) {
        showToast(`خطا: ${error.message}`, "error");
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = "ثبت عملیات";
    }
}

</script>
</div>
</body>
</html>