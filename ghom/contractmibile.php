<?php
// ghom/contractor_batch_update_mobile.php - DYNAMIC MOBILE VERSION V2
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
    echo "Access Denied.";
    exit;
}
$pageTitle = "انتخاب نقشه";
//define('APP_ROOT', __DIR__); // Define a constant to prevent re-inclusion issues
require_once __DIR__ . '/header_mobile.php'; 
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo escapeHtml($pageTitle); ?></title>
    <link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    <style>
        
        /* --- Base & Mobile UI CSS (Unchanged) --- */
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Segoe UI', Tahoma, Verdana, sans-serif; overflow: hidden; background: #f5f5f5; touch-action: none; }

        /* --- NEW: Navigation Menu CSS --- */
        #navigationMenu {
            position: absolute;
            top: 56px; /* Below header */
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
            padding: 15px 10px;
            font-size: 0.9em;
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
        .mobile-header { position: fixed; top: 0; left: 0; right: 0; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-content { display: flex; align-items: center; justify-content: space-between; padding: 8px 16px; min-height: 56px; }
        .logo { font-size: 18px; font-weight: bold; color: #007cba; }
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
        .slide-menu-content { padding: 16px; }
        .menu-section { margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-control { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; }
        .btn { padding: 12px 20px; border: none; border-radius: 8px; font-size: 16px; width: 100%; margin-bottom: 8px; }
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
        .jdp-container {
    z-index: 1003 !important;
}
    </style>
</head>
<body data-user-role="<?php echo escapeHtml($_SESSION['role']); ?>">
    <div class="mobile-header" id="mobileHeader">
        <div class="header-content">
            <div class="logo">AlumGlass</div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="toggleMenu()" id="menu-hamburger-btn" style="display: none;">☰</button>
            </div>
        </div>
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
            <div class="menu-section" id="gfrcSection" style="display: none;">
                <h3>بخش‌های GFRC</h3>
                <div class="form-group">
                    <label><input type="checkbox" class="gfrc-part-checkbox" value="face" checked> نما</label><br>
                    <label><input type="checkbox" class="gfrc-part-checkbox" value="up" checked> بالا</label><br>
                    <label><input type="checkbox" class="gfrc-part-checkbox" value="down" checked> پایین</label><br>
                    <label><input type="checkbox" class="gfrc-part-checkbox" value="left" checked> چپ</label><br>
                    <label><input type="checkbox" class="gfrc-part-checkbox" value="right" checked> راست</label>
                </div>
            </div>
            <div class="menu-section" id="detailsSection">
                <h3>جزئیات</h3>
               <div class="form-group">
                    <label>تاریخ:</label>
                    <input type="text" class="form-control" id="batchDate" data-jdp readonly>
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
  const urlParams = new URLSearchParams(window.location.search);
  const planFile = urlParams.get("plan");
  if (planFile) {
    fetchAndDisplaySVG(SVG_BASE_PATH + planFile);
  } else {
    buildNavigationMenu();
  }
jalaliDatepicker.startWatch({ 
            container: document.getElementById('slideMenu'), 
            minDate: "today" // Now it only prevents selecting past dates
        });
});

function setupEventListeners() {
  const container = document.getElementById("svgContainer");
  container.addEventListener("touchstart", handleTouchStart, {
    passive: false,
  });
  container.addEventListener("touchmove", handleTouchMove, { passive: false });
  container.addEventListener("touchend", handleTouchEnd, { passive: false });

  document.getElementById("actionSelect").addEventListener("change", (e) => {
    const consultantActions = [
      "approve-opening",
      "reject-opening",
      "verify-opening",
      "dispute-opening",
    ];
    document.getElementById("detailsSection").style.display =
      consultantActions.includes(e.target.value) ? "none" : "block";
  });
}

// --- SECTION 3: NAVIGATION MENU & VIEW MANAGEMENT ---
async function buildNavigationMenu() {
  showMenuView();
  const menuContainer = document.getElementById("navigationMenu");
  menuContainer.innerHTML = ""; // Clear previous content
  const loadingDiv = document.createElement("div");
  loadingDiv.className = "loading-menu";
  loadingDiv.style.textAlign = "center";
  loadingDiv.style.padding = "40px";
  loadingDiv.textContent = "در حال بارگذاری منو...";
  menuContainer.appendChild(loadingDiv);

  try {
    const [regionRes, groupRes] = await Promise.all([
      fetch("/ghom/assets/js/regionToZoneMap.json"),
      fetch("/ghom/assets/js/svgGroupConfig.json"),
    ]);
    if (!regionRes.ok || !groupRes.ok)
      throw new Error("Failed to load configuration files.");

    const regionToZoneMap = await regionRes.json();
    const svgGroupConfig = await groupRes.json();

    menuContainer.innerHTML = ""; // Clear loading message

    const userRole = document.body.dataset.userRole;
    const isAdmin = userRole === "admin" || userRole === "superuser";
    let accessibleRegionsFound = 0;

    for (const regionKey in regionToZoneMap) {
      const regionConfig = svgGroupConfig[regionKey];
      if (!regionConfig) continue;

      const hasPermission =
        isAdmin ||
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

      zones.forEach((zone) => {
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
      menuContainer.innerHTML = `<p style="text-align:center; padding: 20px;">هیچ محدوده ای برای شما تعریف نشده است.</p>`;
    }
  } catch (error) {
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
  document.getElementById("menu-hamburger-btn").style.display = "none";
  clearSelection();
  history.pushState(
    { path: window.location.pathname },
    "",
    window.location.pathname
  );
}

function showSvgView(planName = "نقشه") {
        document.title = planName;

        // This safer version checks if each element exists before changing its style
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

        const hamburger = document.getElementById('menu-hamburger-btn');
        if (hamburger) hamburger.style.display = 'block';
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

// --- SECTION 4: SVG & DATA HANDLING ---
// --- SECTION 4: SVG & DATA HANDLING (REVISED WITH BETTER ERROR HANDLING) ---
async function fetchAndDisplaySVG(svgPath) {
  const baseFilename = svgPath.substring(svgPath.lastIndexOf("/") + 1);
  showSvgView(baseFilename.replace(".svg", ""));
  const container = document.getElementById("svgContainer");
  container.classList.add("loading");
  container.innerHTML = "";

  try {
    // Step 1: Fetch both the SVG file and the data from the API
    const [svgRes, dataRes] = await Promise.all([
      fetch(svgPath),
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

function initializeInteractiveElements() {
        const svg = document.querySelector('#svgContainer svg');
        if (!svg) return;

        // --- ADD THESE TWO LINES ---
        // This is the crucial fix. It removes hardcoded dimensions from the SVG file,
        // forcing it to scale correctly within its container.
        svg.removeAttribute('width');
        svg.removeAttribute('height');
        // --- END OF FIX ---
        
        const STATUS_COLORS = { "Request to Open": "rgba(13, 202, 240, 0.7)", "Opening Approved": "rgba(25, 135, 84, 0.7)", "Opening Rejected": "rgba(220, 53, 69, 0.7)", "Panel Opened": "rgba(255, 193, 7, 0.7)", "Opening Disputed": "rgba(220, 53, 69, 0.9)", "Pre-Inspection Complete": "rgba(40, 167, 69, 0.7)", "Pending": "rgba(108, 117, 125, 0.4)" };
        
        svg.querySelectorAll('path, rect, polygon, circle').forEach(el => {
            if (el.id) {
                el.classList.add('interactive-element');
                el.dataset.uniqueId = el.id;
                if (currentPlanDbData[el.id]) {
                    const data = currentPlanDbData[el.id];
                    el.dataset.elementType = data.element_type || 'Unknown';
                    el.dataset.status = data.status || 'Pending';
                    el.style.fill = STATUS_COLORS[data.status] || STATUS_COLORS['Pending'];
                }
            }
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
function updateSelectionUI() {
  const count = selectedElements.size;
  const badge = document.getElementById("selectionBadge");
  document.getElementById("selectionCount").textContent = count;
  badge.classList.toggle("visible", count > 0);
  const hasGFRC = Array.from(selectedElements.values()).some(
    (item) => item.type === "GFRC"
  );
  document.getElementById("gfrcSection").style.display = hasGFRC
    ? "block"
    : "none";
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
    parts: Array.from(
      document.querySelectorAll(".gfrc-part-checkbox:checked")
    ).map((cb) => cb.value),
  };
  try {
    const response = await fetch("/ghom/api/batch_update_status.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
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
</body>
</html>