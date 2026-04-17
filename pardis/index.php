<?php
// public_html/pardis/index.php
function isMobileDevice() {
    // A simple but effective check for common mobile user agents
    return preg_match(
        "/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i",
        $_SERVER["HTTP_USER_AGENT"]
    );
}

// If a mobile device is detected, redirect to the mobile page and stop script execution
if (isMobileDevice()) {
    // Make sure the path to your mobile page is correct
    header('Location: mobile.php');
    exit();
}
// Include the central bootstrap file
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
// Secure the session (starts or resumes and applies security checks)
secureSession();

// --- Authorization & Project Context Check ---

// 1. Check if user is logged in at all
if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'superuser', 'user', 'supervisor','planner', 'cat', 'car', 'coa', 'crs','cod'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

$common_pdo= getCommonDBConnection();
$user_display_name = 'Unknown User'; // Default value
if (isset($_SESSION['user_display_name']) && !empty($_SESSION['user_display_name'])) {
    $user_display_name = $_SESSION['user_display_name'];
} elseif (isset($_SESSION['user_id'])) {
    // Fallback to query the database if the session variable isn't set
    $stmt = $common_pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $user_display_name = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['user_display_name'] = $user_display_name; // Store it for future requests
    }
}

// 2. Define the expected project for this page
$expected_project_key = 'pardis';

// 3. Check if the user has selected a project and if it's the correct one
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    // Log the attempt for security auditing
    logError("User ID " . ($_SESSION['user_id'] ?? 'N/A') . " tried to access pardis project page without correct session context.");
    // Redirect them to the project selection page with an error
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}
$pageTitle = "پروژه دانشگاه خاتم پردیس";
require_once __DIR__ . '/header.php';
// If all checks pass, the script continues and will render the HTML below.
$user_role = $_SESSION['role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo escapeHtml($pageTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="/pardis/assets/images/favicon.ico" />
    <link rel="stylesheet" href="/pardis/assets/css/jalalidatepicker.min.css" />
    
    <link rel="stylesheet" href="<?php echo version_asset("/pardis/assets/css/formopen.css");?>" />
    <link rel="stylesheet" href="<?php echo version_asset("/pardis/assets/css/crack1.css");?>" />

    <script src="/pardis/assets/js/interact.min.js"></script>
    <script src="/pardis/assets/js/fabric.min.js"></script>
  <script src="/pardis/assets/js/forge.min.js"></script>



 <script>
        const CSRF_TOKEN = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>';
    </script>

    <link rel="stylesheet" href="/pardis/assets/css/index.css">

</head>



<body data-user-role="<?php echo escapeHtml($user_role); ?>" data-user-display-name="<?php echo escapeHtml($user_display_name); ?>">
  <button class="workflow-button" onclick="window.location.href='/pardis/viewer_3d.php'">
    🏗️ نمایشگر مدل‌های سه‌بعدی
</button>  
    <div id="status-legend">
        
    <strong>راهنمای وضعیت:</strong>
    <span class="legend-item active" data-status="OK">
        <span class="legend-dot" style="background-color:rgba(40, 167, 69, 0.7);"></span>تایید شده
    </span>

    <span class="legend-item active" data-status="Reject">
        <span class="legend-dot" style="background-color: rgba(220, 53, 69, 0.7);"></span>رد شده
    </span>

    <span class="legend-item active" data-status="Repair">
        <span class="legend-dot" style="background-color: rgba(156, 39, 176, 0.7);"></span>نیاز به تعمیر
    </span>
    
    <!-- START OF ADDITION -->
    <span class="legend-item active" data-status="Awaiting Re-inspection">
        <span class="legend-dot" style="background-color: rgba(0, 191, 255, 0.8);"></span>منتظر بازرسی مجدد
    </span>
    <!-- END OF ADDITION -->

    <span class="legend-item active" data-status="Pre-Inspection Complete">
        <span class="legend-dot" style="background-color: rgba(255, 140, 0, 0.8);"></span>آماده بازرسی
    </span>
    
    <span class="legend-item active" data-status="Pending">
        <span class="legend-dot" style="background-color: rgba(108, 117, 125, 0.4);"></span>در انتظار
    </span>
    
    <span class="legend-item" onclick="openWorkflowModal()">
        <span class="legend-dot" style="background-color: rgba(8, 139, 253, 0.4)"></span>راهنمای گردش کار
    </span>
</div>


    <div id="currentZoneInfo">
        <strong>نقشه فعلی:</strong> <span id="zoneNameDisplay"></span>
        <strong>پیمانکار:</strong> <span id="zoneContractorDisplay"></span>
        <strong>بلوک:</strong> <span id="zoneBlockDisplay"></span>
    </div>

    
    <div class="navigation-controls"><button id="backToPlanBtn">بازگشت به پلن اصلی</button>

    </div>
    <div id="regionZoneNavContainer">
        <h3 class="region-zone-nav-title">ناوبری سریع به زون‌ها</h3>
        <div class="region-zone-nav-select-row">
            <label for="regionSelect" class="region-zone-nav-label">انتخاب محدوده:</label>
            <select id="regionSelect" class="region-zone-nav-select">
                <option value="">-- ابتدا یک محدوده انتخاب کنید --</option>
            </select>
        </div>
        <div id="zoneButtonsContainer" class="region-zone-nav-zone-buttons"></div>
    </div>

    <p class="description">برای مشاهده چک لیست، روی المان مربوطه در نقشه کلیک کنید.</p>
   
    <div id="batch-selection-bar" style="display: none; text-align: center; padding: 10px; background-color: #fff3cd; border: 1px solid #ffeeba; margin: 10px auto; border-radius: 5px; max-width: 500px;">
    <span id="selection-count">0</span> المان انتخاب شده است.
    <button id="batch-inspect-btn" style="margin-right: 15px; padding: 5px 10px; background-color: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer;">
        تکمیل فرم گروهی
    </button>
    <button id="clear-selection-btn" style="padding: 5px 10px; background: none; border: none; color: #dc3545; cursor: pointer;">X</button>
</div>
<div class="layer-controls" id="layerControlsContainer"></div>

    <div id="svgContainer"></div>
    <div id="crack-drawer-modal" class="crack-drawer-modal">
        <div class="drawer-content">
            <div class="drawer-header">
                <button class="drawer-close-x" onclick="document.getElementById('crack-drawer-modal').style.display='none'">×</button>
                <h3 id="drawer-title" class="drawer-title">ترسیم ترک‌ها</h3>
            </div>
            
            <div class="drawer-tools">
                <div class="tool-item">
                    <button class="tool-btn" data-tool="line">
                    <span>خط</span>
                    </button>
                    <input type="color" class="tool-color" value="#FF0000">
                </div>
                
                <div class="tool-item">
                    <button class="tool-btn" data-tool="rectangle">
                    <span>مستطیل</span>
                    </button>
                    <input type="color" class="tool-color" value="#0000FF">
                </div>
                
                <div class="tool-item">
                    <button class="tool-btn" data-tool="circle">
                        <i class="tool-icon circle"></i>
                        <span class="tool-label">دایره</span>
                    </button>
                    <input type="color" class="tool-color" value="#00FF00" title="رنگ دایره">
                </div>
                
               
                
                <div class="tool-item">
                    <button class="clear-all-btn" onclick="clearAllDrawings()">
                        پاک کردن همه
                    </button>
                </div>
            </div>
            
            <div class="drawer-canvas-container">
                <div id="ruler-top" class="ruler horizontal"></div>
                <div id="ruler-left" class="ruler vertical"></div>
                <canvas id="crack-canvas"></canvas>
            </div>
            
            <div class="drawer-footer">
                <button id="drawer-close-btn" class="drawer-btn drawer-btn-close">بستن</button>
                <button id="drawer-save-btn" class="drawer-btn drawer-btn-save">ذخیره</button>
            </div>
        </div>
    </div>

    <div class="form-popup" id="universalChecklistForm">
        <!-- This container will be completely filled by JavaScript -->
        <div id="universal-form-element">

        </div>
    </div>

    <div id="workflowModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>راهنمای گردش کار بازرسی</h2>
                <button class="close" onclick="closeWorkflowModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="workflow-svg-container">
                    <svg width="800" height="500" viewBox="0 0 900 750" xmlns="http://www.w3.org/2000/svg" font-family="Samim, Tahoma, sans-serif">
                        <defs
                            id="defs4">
                            
                            <marker
                                id="arrowhead"
                                viewBox="0 0 10 10"
                                refX="9"
                                refY="5"
                                markerWidth="6"
                                markerHeight="6"
                                orient="auto">
                                <path
                                    d="M 0 0 L 10 5 L 0 10 z"
                                    fill="#333"
                                    id="path1" />
                            </marker>
                            <marker
                                id="arrowhead-reject"
                                viewBox="0 0 10 10"
                                refX="9"
                                refY="5"
                                markerWidth="6"
                                markerHeight="6"
                                orient="auto">
                                <path
                                    d="M 0 0 L 10 5 L 0 10 z"
                                    fill="#d32f2f"
                                    id="path2" />
                            </marker>
                            <marker
                                id="arrowhead-ok"
                                viewBox="0 0 10 10"
                                refX="9"
                                refY="5"
                                markerWidth="6"
                                markerHeight="6"
                                orient="auto">
                                <path
                                    d="M 0 0 L 10 5 L 0 10 z"
                                    fill="#388e3c"
                                    id="path3" />
                            </marker>
                            <marker
                                id="arrowhead-repair"
                                viewBox="0 0 10 10"
                                refX="9"
                                refY="5"
                                markerWidth="6"
                                markerHeight="6"
                                orient="auto">
                                <path
                                    d="M 0 0 L 10 5 L 0 10 z"
                                    fill="#f57c00"
                                    id="path4" />
                            </marker>
                        </defs>
                        <!-- Legend -->
                        <rect
                            x="645.66626"
                            y="86.972206"
                            width="156.06853"
                            height="158.38419"
                            fill="#f9f9f9"
                            stroke="#dddddd"
                            stroke-width="1.05048"
                            rx="7.8034267"
                            id="rect4" />
                        <text
                            x="743.20831"
                            y="105.15524"
                            class="text-main"
                            font-weight="bold"
                            fill="#333333"
                            id="text4">راهنما</text>
                        <rect
                            x="672.66626"
                            y="120.15524"
                            width="25"
                            height="20"
                            class="consultant-box box"
                            rx="3"
                            id="rect5" />
                        <text
                            x="795.66626"
                            y="135.97672"
                            class="legend-text"
                            id="text5">مشاور</text>
                        <rect
                            x="673.20831"
                            y="150.15524"
                            width="25"
                            height="20"
                            class="contractor-box box"
                            rx="3"
                            id="rect6" />
                        <text
                            x="795.66626"
                            y="161.12549"
                            class="legend-text"
                            id="text6">پیمانکار</text>
                        <rect
                            x="673.20831"
                            y="180.15523"
                            width="25"
                            height="20"
                            class="system-box box"
                            rx="3"
                            id="rect7" />
                        <text
                            x="795.66626"
                            y="193.06598"
                            class="legend-text"
                            id="text7">سیستم / فرآیند</text>
                        <rect
                            x="673.20831"
                            y="210.15524"
                            width="25"
                            height="15"
                            class="decision-box box"
                            rx="3"
                            id="rect8" />
                        <text
                            x="795.66626"
                            y="219.09573"
                            class="legend-text"
                            id="text8">تصمیم‌گیری</text>
                        <!-- Start Process -->
                        <ellipse
                            cx="500"
                            cy="70"
                            rx="120"
                            ry="35"
                            class="system-box box"
                            id="ellipse8" />
                        <text
                            x="500"
                            y="65"
                            class="text-main"
                            id="text9">شروع فرآیند</text>
                        <text
                            x="500"
                            y="80"
                            class="text-small"
                            id="text10">المان با وضعیت &quot;آماده بازرسی&quot;</text>
                        <!-- Arrow to Consultant -->
                        <line
                            x1="500"
                            y1="105"
                            x2="500"
                            y2="150"
                            class="arrow"
                            id="line10" />
                        <!-- Step 1: Consultant Opens Form -->
                        <rect
                            x="400"
                            y="150"
                            width="200"
                            height="50"
                            rx="8"
                            class="consultant-box box"
                            id="rect10" />
                        <text
                            x="500"
                            y="175"
                            class="text-main"
                            id="text11">۱. مشاور فرم را باز می‌کند</text>
                        <!-- Arrow to Decision -->
                        <line
                            x1="500"
                            y1="200"
                            x2="500"
                            y2="250"
                            class="arrow"
                            id="line11" />
                        <!-- Step 2: Decision Diamond -->
                        <path
                            d="M 500 250 L 580 300 L 500 350 L 420 300 Z"
                            class="decision-box box"
                            id="path11" />
                        <text
                            x="500"
                            y="295"
                            class="text-main"
                            id="text12">۲. ثبت وضعیت</text>
                        <text
                            x="500"
                            y="310"
                            class="text-small"
                            id="text13">(OK / Repair / Reject)</text>
                        <!-- OK Path -->
                        <line
                            x1="580"
                            y1="300"
                            x2="650"
                            y2="300"
                            class="arrow-ok"
                            id="line13" />
                        <text
                            x="620"
                            y="290"
                            class="step-label"
                            fill="#388e3c"
                            id="text14">OK</text>
                        <!-- OK Final State -->
                        <ellipse
                            cx="730"
                            cy="300"
                            rx="80"
                            ry="35"
                            class="final-box box"
                            id="ellipse14" />
                        <text
                            x="730"
                            y="300"
                            class="text-main"
                            id="text15">اتمام فرآیند</text>
                        <!-- Repair Path -->
                        <line
                            x1="420"
                            y1="300"
                            x2="320"
                            y2="300"
                            class="arrow-repair"
                            id="line15" />
                        <text
                            x="370"
                            y="290"
                            class="step-label"
                            fill="#f57c00"
                            id="text16">Repair</text>
                        <!-- Step 3: Contractor Repair -->
                        <rect
                            x="150"
                            y="275"
                            width="170"
                            height="50"
                            rx="8"
                            class="contractor-box box"
                            id="rect16" />
                        <text
                            x="235"
                            y="300"
                            class="text-main"
                            id="text17">۳. پیمانکار تعمیر می‌کند</text>
                        <!-- Arrow to Step 4 -->
                        <line
                            x1="235"
                            y1="325"
                            x2="235"
                            y2="375"
                            class="arrow"
                            id="line17" />
                        <!-- Step 4: Contractor Reports Completion -->
                        <rect
                            x="150"
                            y="375"
                            width="170"
                            height="50"
                            rx="8"
                            class="contractor-box box"
                            id="rect17" />
                        <text
                            x="235"
                            y="400"
                            class="text-main"
                            id="text18">۴. پیمانکار اعلام اتمام تعمیر</text>
                        <!-- Arrow to Step 5 -->
                        <line
                            x1="235"
                            y1="425"
                            x2="235"
                            y2="535"
                            class="arrow"
                            id="line18" />
                        <!-- Step 5: Consultant Re-check -->
                        <rect
                            x="130"
                            y="535"
                            width="199.91238"
                            height="49.834759"
                            rx="9.4076414"
                            class="consultant-box box"
                            id="rect18" />
                        <text
                            x="222"
                            y="560"
                            class="text-main"
                            id="text19">۵. مشاور مجدداً بررسی می‌کند</text>
                        <!-- Arrow to Second Decision -->
                        <line x1="330" y1="552" x2="415" y2="552" class="arrow" id="line19"></line>
                        <!-- Second Decision Diamond -->
                        <path
                            d="m 496.11902,502.01811 80,50 -80,50 -80,-50 z"
                            class="decision-box box"
                            id="path19" />
                        <text
                            x="500"
                            y="544.8396"
                            class="text-main"
                            id="text20">بررسی نتیجه تعمیر</text>
                        <text
                            x="664.15741"
                            y="422.1864"
                            class="text-small"
                            id="text21"
                            transform="scale(0.74772052,1.3373981)"
                            style="stroke-width:0.747721">(OK / Reject Repair)</text>
                        <!-- Second OK Path -->
                        <path
                            d=" m 571.25873,551.79618 q 81.48999,0 139.69713,-81.62549 l 30,-135""
                            stroke=" #388e3c"
                            stroke-width="2.91278"
                            fill="none"
                            marker-end="url(#arrowhead-ok)"
                            id="path21" />
                        <text x="700" y="470" class="step-label" fill="#388e3c" id="text22">OK</text>
                        <!-- Repair Loop -->
                        <path
                            d="M 419.86956,552.0126 H 350.10985 V 300.03458"
                            stroke="#f57c00"
                            stroke-width="2.13675"
                            fill="none"
                            marker-end="url(#arrowhead-repair)"
                            stroke-dasharray="5, 5"
                            id="path22" />
                        <text
                            x="-460"
                            y="130"
                            class="step-label"
                            fill="#f57c00"
                            transform="rotate(-90)"
                            id="text23">Reject Repair</text>
                        <!-- System Check for Reject Count -->
                        <line
                            x1="498.17853"
                            y1="600.92883"
                            x2="498.17853"
                            y2="650.92883"
                            class="arrow"
                            id="line23" />
                        <rect
                            x="351.94049"
                            y="652.45276"
                            width="300"
                            height="70"
                            rx="8"
                            class="system-box box"
                            id="rect23" />
                        <text
                            x="500"
                            y="672"
                            class="text-main"
                            font-weight="bold"
                            id="text24">بررسی تعداد رد تعمیر (سیستم)</text>
                        <text
                            x="500"
                            y="692"
                            class="text-small"
                            id="text25">اگر کمتر از ۳ بار: بازگشت به تعمیر</text>
                        <text
                            x="500"
                            y="707"
                            class="text-small"
                            id="text26">اگر ۳ بار یا بیشتر: رد نهایی</text>
                        <!-- Loop back to repair -->
                        <path
                            d="M 350.06733,680 H 110 v -380 H 150,150"
                            stroke="#333333"
                            stroke-width="2"
                            fill="none"
                            marker-end="url(#arrowhead)"
                            stroke-dasharray="5, 5"
                            id="path26" />
                        <text
                            x="-580"
                            y="95"
                            class="text-small"
                            fill="#666"
                            transform="rotate(-90)"
                            id="text27">کمتر از ۳ بار</text>
                        <!-- Final Reject Path -->
                        <path d="M 650,680  H 850 v -610 H 618" stroke="#d32f2f" stroke-width="2" fill="none" marker-end="url(#arrowhead-reject)" stroke-dasharray="5, 5" id="reject3time"></path>

                        <text
                            x="700"
                            y="673"
                            class="step-label"
                            stroke="#d32f2f"
                            stroke-width="0.9"
                            fill="#d32f2f"
                            id="text28">۳ بار رد</text>
                        <!-- Reject Path from Initial Decision -->
                        <line
                            x1="500"
                            y1="350"
                            x2="500"
                            y2="420"
                            class="arrow-reject"
                            id="line28" />
                        <text
                            x="540"
                            y="385"
                            class="step-label"
                            fill="#d32f2f"
                            id="text29">Reject</text>
                        <!-- Step 6: Final Rejection -->
                        <rect
                            x="400"
                            y="420"
                            width="200"
                            height="80"
                            rx="8"
                            class="final-box box"
                            id="rect29" />
                        <text
                            x="500"
                            y="445"
                            class="text-main"
                            id="text30">۶. رد نهایی المان</text>
                        <text
                            x="500"
                            y="465"
                            class="text-small"
                            id="text31">نیاز به ساخت/خرید المان جدید</text>
                        <text
                            x="500"
                            y="480"
                            class="text-small"
                            id="text32">و بازگشت به شروع فرآیند</text>
                        <!-- Final Reject State -->


                        <!-- Loop back from rejection to start -->
                        <path
                            d="M 850,450  H 600 "
                            stroke=" #d32f2f"
                            stroke-width="2.02312"
                            fill="none"
                            marker-end="url(#arrowhead-reject)"
                            stroke-dasharray="8, 8"
                            id="path34" />

                        <text
                            x="-210"
                            y="870"
                            class="text-small"
                            fill="#d32f2f"
                            stroke="#d32f2f"
                            stroke-width="0.9"
                            transform="rotate(-90)"
                            id="textreject">بازگشت به شروع</text>
                    </svg>
                </div>

                <div class="workflow-description">
                    <h3>توضیحات گردش کار</h3>
                    <p>این فرآیند نشان‌دهنده مراحل بازرسی و کنترل کیفیت المان‌های ساختمانی است که شامل مراحل زیر می‌باشد:</p>

                    <ul class="workflow-steps">
                        <li>
                            <span class="step-number">1</span>
                            <div>
                                <strong>شروع فرآیند:</strong> المان با وضعیت "آماده بازرسی" وارد سیستم می‌شود.
                            </div>
                        </li>
                        <li>
                            <span class="step-number">2</span>
                            <div>
                                <strong>بازرسی مشاور:</strong> مشاور فرم بازرسی را باز کرده و وضعیت المان را بررسی می‌کند.
                            </div>
                        </li>
                        <li>
                            <span class="step-number">3</span>
                            <div>
                                <strong>تصمیم‌گیری:</strong> مشاور یکی از سه وضعیت OK، Repair یا Reject را انتخاب می‌کند.
                            </div>
                        </li>
                        <li>
                            <span class="step-number">4</span>
                            <div>
                                <strong>مسیر تعمیر:</strong> در صورت انتخاب Repair، پیمانکار اقدام به تعمیر المان می‌کند.
                            </div>
                        </li>
                        <li>
                            <span class="step-number">5</span>
                            <div>
                                <strong>بازرسی مجدد:</strong> پس از تعمیر، مشاور مجدداً المان را بررسی می‌کند.
                            </div>
                        </li>
                        <li>
                            <span class="step-number">6</span>
                            <div>
                                <strong>کنترل تعداد رد:</strong> سیستم تعداد دفعات رد تعمیر را بررسی می‌کند و در صورت رسیدن به ۳ بار، المان را به طور نهایی رد می‌کند.
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>


    <script src="/pardis/assets/js/index.js" defer></script>
    <?php require_once 'footer.php'; ?>


<script src="/pardis/assets/js/jspdf.umd.min.js"></script>
<script src="/pardis/assets/js/html2canvas.min.js"></script>
<script src="/pardis/assets/js/jspdf.plugin.autotable.min.js"></script>
<!-- For Persian font support -->


    <script type="text/javascript" src="/pardis/assets/js/jalalidatepicker.min.js"></script>
<!-- Replace the THREE.js script tag with this: -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>

<!-- Add GLTFLoader from CDN -->
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>

<!-- Add DRACOLoader for compressed models (optional but recommended) -->
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/DRACOLoader.js"></script>

<!-- Then load your enhanced viewer -->
<script src="<?php echo version_asset('/pardis/assets/js/gltf_viewer_module.js'); ?>"></script>

    <!-- STEP 2: LOAD THE MAIN APPLICATION LOGIC -->
    <script src="<?php echo version_asset('/pardis/assets/js/ghom_app.js'); ?>"></script>
 <script src="<?php echo version_asset('/pardis/assets/js/PlanDrawingModule.js'); ?>"></script>

      


</body>

</html>