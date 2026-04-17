<?php
// public_html/ghom/index.php
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

if (!in_array($_SESSION['role'], ['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}
// 2. Define the expected project for this page
$expected_project_key = 'ghom';

// 3. Check if the user has selected a project and if it's the correct one
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    // Log the attempt for security auditing
    logError("User ID " . ($_SESSION['user_id'] ?? 'N/A') . " tried to access Ghom project page without correct session context.");
    // Redirect them to the project selection page with an error
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}
$pageTitle = "پروژه بیمارستان هزار تخت خوابی قم";
require_once __DIR__ . '/header_ghom.php';
// If all checks pass, the script continues and will render the HTML below.
$user_role = $_SESSION['role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo escapeHtml($pageTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="/ghom/assets/images/favicon.ico" />
    <link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    
    <link rel="stylesheet" href="<?php echo version_asset("/ghom/assets/css/formopen.css");?>" />
    <link rel="stylesheet" href="<?php echo version_asset("/ghom/assets/css/crack1.css");?>" />
    <script src="/ghom/assets/js/interact.min.js"></script>
    <script src="/ghom/assets/js/fabric.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/forge/1.3.1/forge.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/node-forge@1.0.0/dist/forge.min.js"></script>

 <script>
        const CSRF_TOKEN = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>';
    </script>

    <style>
        @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
                url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
                url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        .stage-fieldset {
            border: 1px solid #007bff;
            border-radius: 5px;
            padding: 10px;
            margin-top: 20px;
            background-color: #f8f9fa;
        }

        .stage-fieldset legend {
            font-weight: bold;
            color: #0056b3;
            background-color: #f8f9fa;
            padding: 0 10px;
        }

        /* This is the class for the yellow items */
        .highlight-external {
            background-color: #fff3cd !important;
            /* Light yellow background */
            border-radius: 4px;
            padding: 10px;
            border: 1px solid #ffeeba;
        }

        .ltr-text {
            direction: ltr;
            unicode-bidi: embed;
            display: inline-block;
        }

        .highlight-note {
            color: red;
            text-align: center;
            font-weight: bold;
            margin-top: 15px;
        }

        /* HEADER */
        header {
            background-color: #0056b3;
            color: white;
            padding: 15px 20px;
            width: 100%;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .header-content {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            align-items: center;
            max-width: 1200px;
            width: 100%;
            gap: 20px;
        }

        .header-content .logo-left,
        .header-content .logo-right {
            display: flex;
            align-items: center;
            height: 50px;
        }

        .header-content .logo-left img {
            height: 50px;
            width: auto;
        }

        .header-content .logo-left {
            justify-content: flex-start;
        }

        .header-content .logo-right {
            justify-content: flex-end;
        }

        .header-content h1 {
            margin: 0;
            font-size: 1.6em;
            font-weight: 600;
            text-align: center;
        }

        /* FOOTER */
        footer {
            background-color: #343a40;
            color: #f8f9fa;
            text-align: center;
            padding: 20px;
            width: 100%;
            margin-top: auto;
            font-size: 0.9em;
        }

        footer p {
            margin: 0;
        }

        /* ZONE INFO */
        #currentZoneInfo {
            margin-top: 20px;
            text-align: center;
            padding: 10px;
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 5px;
            display: none;
            font-size: 0.9em;
        }

        #zoneNameDisplay,
        #zoneContractorDisplay,
        #zoneBlockDisplay {
            margin-left: 15px;
            font-weight: bold;
        }

        #regionZoneNavContainer {
            align-self: center;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            width: 80%;
            max-width: 600px;
            background-color: #f8f9fa;
        }

        /* CONTROLS */
        .navigation-controls,
        .layer-controls {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .navigation-controls button,
        .layer-controls button {
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-family: inherit;
        }

        .navigation-controls button {
            border: 1px solid #007bff;
            background-color: #007bff;
            color: white;
        }

        .layer-controls button {
            border: 1px solid #ccc;
            background-color: #f8f9fa;
        }

        .layer-controls button.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        p.description {
            text-align: center;
            margin-bottom: 10px;
        }

        /* SVG CONTAINER */
        #svgContainer {
            width: 90vw;
            height: 65vh;
            max-width: 1200px;
            border: 1px solid #007bff;
            background-color: #e9ecef;
            overflow: hidden;
            margin: 10px auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: grab;
            position: relative;
        }

        #svgContainer.dragging {
            cursor: grabbing;
        }

        #svgContainer.loading::before {
            content: "در حال بارگذاری نقشه...";
        }

        #svgContainer svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .interactive-element {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .interactive-element:hover {
            filter: brightness(1.1);
        }

        /* START: === IMPROVED FORM POPUP CSS === */




        .highlight-issue {
            background-color: #fff3cd !important;
        }

        .zoom-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 5;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .svg-element-active {
            stroke: #ff3333 !important;
            stroke-width: 3px !important;
        }

        #gfrcSubPanelMenu {
            position: absolute;
            background: white;
            border: 1px solid #ccc;
            padding: 5px;
            z-index: 1001;
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.2);
            min-width: 150px;
        }

        #gfrcSubPanelMenu button {
            display: block;
            width: 100%;
            margin-bottom: 3px;
            text-align: right;
            padding: 5px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }

        #gfrcSubPanelMenu button:hover {
            background-color: #0056b3;
        }

        #gfrcSubPanelMenu .close-menu-btn {
            background-color: #f0f0f0;
            color: black;
            margin-top: 5px;
        }

        /* JDP Date Picker Overrides */
        .jdp-container {
            position: absolute;
            /* Position it relative to the input field */
            border: 2px solid #3498db;

            /* Give it a z-index higher than other form content */
            z-index: 10;
        }

        .jdp-header {
            background-color: #1a1b1b !important;
            color: #ecf0f1 !important;
        }

        .jdp-day.jdp-selected {
            background-color: #0b3653 !important;
            color: white !important;
            border-radius: 50% !important;
        }

        .jdp-day.jdp-today {
            border: 1px solid #3498db !important;
        }

        .region-zone-nav-title {
            margin-top: 0;
            margin-bottom: 10px;
            text-align: center;
        }

        .region-zone-nav-select-row {
            margin-bottom: 10px;
            width: 100%;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .region-zone-nav-label {
            margin-left: 5px;
            font-weight: bold;
        }

        .region-zone-nav-select {
            padding: 5px;
            border-radius: 3px;
            border: 1px solid #ccc;
            min-width: 200px;
            font-family: inherit;
        }

        #regionZoneNavContainer .region-zone-nav-zone-buttons,
        .region-zone-nav-zone-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-top: 5px;
        }

        .region-zone-nav-zone-buttons button {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #007bff;
            background-color: #007bff;
            color: white;
            cursor: pointer;
            font-family: inherit;
            margin: 4px;
            transition: background 0.2s;
        }

        .region-zone-nav-zone-buttons button:hover {
            background-color: #0056b3;
        }

        #contractor-section {
            border: 1px solid #007bff;
            border-radius: 5px;
            padding: 10px;
            margin-top: 20px;
            background-color: #f0f8ff;
        }

        #contractor-section legend {
            font-weight: bold;
            color: #007bff;
        }


        #status-legend {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            /* Space between items */
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            margin: 0 auto 15px auto;
            /* Center it and add space below */
            max-width: 800px;
            font-size: 14px;
        }

        #status-legend span {
            display: inline-flex;
            align-items: center;
        }

        .legend-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            margin-left: 6px;
            border: 1px solid rgba(0, 0, 0, 0.2);
        }

        g[id$="_circle"] {
            pointer-events: none;
        }

        #zoneSelectionMenu {
            position: fixed;
            z-index: 1005;
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-radius: 8px;
            width: 600px;
            max-width: 90vw;
            display: flex;
            flex-direction: column;
        }

        /* Style for the menu title */
        .zone-menu-title {
            margin: -5px -5px 15px -5px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
            text-align: right;
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        /* Grid container for the buttons */
        .zone-menu-grid {
            display: grid;
            /* This creates a 3-column grid for desktops. */
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            /* The space between the buttons */
        }

        /* Styling for the individual zone buttons inside the grid */
        .zone-menu-grid button {
            padding: 12px;
            border: 1px solid #ddd;
            background-color: #fff;
            cursor: pointer;
            text-align: right;
            font-size: 14px;
            border-radius: 5px;
            transition: background-color 0.2s, transform 0.2s;
        }

        .zone-menu-grid button:hover {
            background-color: #e9e9e9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Style for the main close button at the bottom */
        #zoneSelectionMenu .close-menu-btn {
            margin-top: 15px;
            padding: 10px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s;
        }

        #zoneSelectionMenu .close-menu-btn:hover {
            background-color: #5a6268;
        }


        /* --- MOBILE RESPONSIVE STYLES --- */

        /* For tablets and larger phones (screens smaller than 640px) */
        @media (max-width: 640px) {
            #zoneSelectionMenu {
                /* Make the menu take up more screen width on mobile */
                width: 90vw;
            }

            .zone-menu-grid {
                /* Switch to a 2-column grid, which is better for this screen size */
                grid-template-columns: repeat(2, 1fr);
            }

            .zone-menu-title {
                font-size: 15px;
                /* Slightly smaller title is fine */
            }

            .zone-menu-grid button {
                font-size: 13px;
                /* Adjust font to fit */
                padding: 10px;
            }
        }

        /* For smaller phones (screens smaller than 420px) */
        @media (max-width: 420px) {
            .zone-menu-grid {
                /* Switch to a single column for the best readability and touch experience */
                grid-template-columns: 1fr;
            }
        }

        .legend-item {
            cursor: pointer;
            transition: opacity 0.3s ease;
            padding: 5px;
            border-radius: 4px;
        }

        .legend-item:not(.active) {
            opacity: 0.4;
            text-decoration: line-through;
            background-color: #e9ecef;
        }

        .history-container {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #ccc;
        }

        .history-list {
            list-style: none;
            padding: 8px;
            margin: 0;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            max-height: 120px;
            overflow-y: auto;
            font-size: 13px;
        }

        .history-list li {
            padding: 4px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .history-list li:last-child {
            border-bottom: none;
        }

        .history-meta {
            font-weight: bold;
            color: #0056b3;
            margin-left: 5px;
        }

        textarea {
            resize: both;
        }

        .history-log-container {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #007bff;
        }

        .inspection-cycle-container {
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background-color: #f8f9fa;
        }

        .inspection-cycle-container h3 {
            margin: 0;
            padding: 10px 15px;
            background-color: #e9ecef;
            border-bottom: 1px solid #dee2e6;
            font-size: 1.1em;
        }

        .history-event {
            padding: 10px 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .history-event:last-child {
            border-bottom: none;
        }

        .history-event-header {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 8px;
        }

        .history-note {
            margin-bottom: 8px;
        }

        .history-file-list {
            margin: 5px 0 0;
            padding-right: 20px;
        }

        .history-checklist {
            margin-top: 10px;
        }

        .history-checklist h4 {
            margin: 0 0 5px;
            font-size: 13px;
            color: #333;
        }

        .history-checklist-item {
            font-size: 13px;
            padding: 3px 0;
            display: flex;
            align-items: center;
        }

        .history-item-status {
            display: inline-block;
            width: 60px;
            text-align: center;
            padding: 2px 5px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            margin-left: 10px;
            flex-shrink: 0;
        }

        .history-item-status.status-ok {
            background-color: #28a745;
        }

        .history-item-status.status-not-ok {
            background-color: #dc3545;
        }

        .history-item-value {
            color: #6c757d;
            margin-right: 5px;
        }

        .history-log-container {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f1f3f5;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }

        .history-log-container h4 {
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ced4da;
            font-size: 16px;
            color: #343a40;
        }

        .history-event {
            padding: 12px;
            background-color: #fff;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .history-event:last-child {
            margin-bottom: 0;
        }

        .history-event-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #495057;
            margin-bottom: 10px;
        }

        .history-event-header strong {
            color: #0056b3;
        }

        .history-note {
            font-size: 14px;
            margin-bottom: 8px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 3px;
        }

        .history-checklist {
            margin-top: 12px;
        }

        .history-checklist h4 {
            margin: 0 0 8px 0;
            font-size: 13px;
            color: #495057;
        }

        .history-checklist-item {
            display: flex;
            align-items: center;
            font-size: 13px;
            padding: 4px 0;
            border-top: 1px solid #f1f3f5;
        }

        .history-item-status {
            flex-shrink: 0;
            font-weight: bold;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            min-width: 60px;
            text-align: center;
            margin-left: 10px;
        }

        .history-item-status.status-ok {
            background-color: #28a745;
        }

        .history-item-status.status-not-ok,
        .history-item-status.status-reject {
            background-color: #dc3545;
        }

        .history-item-status.status-repair {
            background-color: #9c27b0;
        }

        .history-item-status.status-pending {
            background-color: #6c757d;
        }

        .history-item-value {
            color: #6c757d;
            margin-right: 5px;
            font-style: italic;
        }

        .workflow-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-family: inherit;
            margin: 20px auto;
            display: block;
            transition: background-color 0.3s;
        }

        .workflow-button:hover {
            background-color: #0056b3;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(3px);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border: none;
            border-radius: 12px;
            width: 95%;
            max-width: 1200px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 20px 30px;
            border-bottom: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .close {
            color: white;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.3s;
        }

        .close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 30px;
            max-height: calc(90vh - 140px);
            overflow-y: auto;
            background-color: #f8f9fa;
        }

        .workflow-svg-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .workflow-svg-container svg {
            width: 100%;
            height: auto;
            min-width: 800px;
        }

        .workflow-description {
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .workflow-description h3 {
            color: #007bff;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .workflow-description p {
            line-height: 1.8;
            margin-bottom: 12px;
            color: #495057;
        }

        .workflow-steps {
            list-style: none;
            padding: 0;
        }

        .workflow-steps li {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: flex-start;
        }

        .workflow-steps li:last-child {
            border-bottom: none;
        }

        .step-number {
            background-color: #007bff;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            margin-left: 12px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 98%;
                margin: 1% auto;
                max-height: 95vh;
            }

            .modal-header {
                padding: 15px 20px;
            }

            .modal-header h2 {
                font-size: 20px;
            }

            .modal-body {
                padding: 20px;
            }

            .workflow-svg-container svg {
                min-width: 600px;
            }
        }

         .signature-pad-container {
            border: 1px solid #007bff;
            border-radius: 5px;
            padding: 10px;
            margin-top: 15px;
            background-color: #f8f9fa;
        }
        .signature-pad-container legend {
            font-weight: bold;
            color: #0056b3;
        }
        .signature-pad-body {
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: white;
            cursor: crosshair;
        }
        #clear-signature-btn {
            margin-top: 10px;
            padding: 5px 10px;
            font-size: 12px;
            color: #fff;
            background-color: #6c757d;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        #clear-signature-btn:hover {
            background-color: #5a6268;
        }
        @keyframes pulse-highlight {
    0% {
        stroke: #ffc107; /* A bright yellow */
        stroke-width: 5px;
        stroke-opacity: 1;
    }
    50% {
        stroke: #e74c3c; /* A strong red */
        stroke-width: 8px; /* Slightly thicker */
        stroke-opacity: 0.8;
    }
    100% {
        stroke: #ffc107; /* Back to yellow */
        stroke-width: 5px;
        stroke-opacity: 1;
    }
}

/* A simpler, more direct class name */
.deep-link-highlight {
    /* Ensures the stroke is drawn correctly on top of the fill */
    paint-order: stroke fill markers; 
    animation: pulse-highlight 1.5s infinite ease-in-out;
}
.element-selected {
    stroke: #0d6efd !important; /* A bright blue outline */
    stroke-width: 5px !important;
    stroke-opacity: 0.8 !important;
    paint-order: stroke; /* Ensures stroke is drawn on top */
}
    </style>

</head>



<body data-user-role="<?php echo escapeHtml($user_role); ?>">
    
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

    <div class="layer-controls" id="layerControlsContainer"></div>
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
                            <style
                                id="style1">
                                .box {
                                    stroke: #333;
                                    stroke-width: 2;
                                    filter: drop-shadow(2px 2px 4px rgba(0, 0, 0, 0.1));
                                }

                                .consultant-box {
                                    fill: #e3f2fd;
                                    stroke: #1976d2;
                                }

                                .contractor-box {
                                    fill: #fff8e1;
                                    stroke: #f57c00;
                                }

                                .system-box {
                                    fill: #e8f5e8;
                                    stroke: #388e3c;
                                }

                                .decision-box {
                                    fill: #fce4ec;
                                    stroke: #c2185b;
                                }

                                .final-box {
                                    fill: #f3e5f5;
                                    stroke: #7b1fa2;
                                }

                                .text-main {
                                    font-size: 14px;
                                    text-anchor: middle;
                                    dominant-baseline: middle;
                                    font-weight: 500;
                                }

                                .text-small {
                                    font-size: 12px;
                                    text-anchor: middle;
                                    dominant-baseline: middle;
                                    fill: #666;
                                }

                                .arrow {
                                    stroke: #333;
                                    stroke-width: 2;
                                    marker-end: url(#arrowhead);
                                }

                                .arrow-reject {
                                    stroke: #d32f2f;
                                    stroke-width: 2.5;
                                    marker-end: url(#arrowhead-reject);
                                }

                                .arrow-ok {
                                    stroke: #388e3c;
                                    stroke-width: 2.5;
                                    marker-end: url(#arrowhead-ok);
                                }

                                .arrow-repair {
                                    stroke: #f57c00;
                                    stroke-width: 2;
                                    marker-end: url(#arrowhead-repair);
                                }

                                .legend-text {
                                    font-size: 13px;
                                    fill: #444;
                                }

                                .step-label {
                                    font-size: 12px;
                                    font-weight: bold;
                                    fill: #666;
                                }
                            </style>
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

    <script>
        function openWorkflowModal() {
            document.getElementById('workflowModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeWorkflowModal() {
            document.getElementById('workflowModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('workflowModal');
            if (event.target == modal) {
                closeWorkflowModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeWorkflowModal();
            }
        });
    </script>
    <?php require_once 'footer.php'; ?>




    <script type="text/javascript" src="/ghom/assets/js/jalalidatepicker.min.js"></script>


    <!-- STEP 2: LOAD THE MAIN APPLICATION LOGIC -->
    <script src="<?php echo version_asset('/ghom/assets/js/ghom_app.js'); ?>"></script>

      <script>
        // Clear all drawings function
        function clearAllDrawings() {
            if (confirm('آیا مطمئن هستید که می‌خواهید همه ترسیم‌ها را پاک کنید؟')) {
                if (fabricCanvas) {
                    // Keep only the panel background (polygon)
                    const objects = fabricCanvas.getObjects();
                    const toRemove = objects.filter(obj => obj.shapeType);
                    toRemove.forEach(obj => fabricCanvas.remove(obj));
                    fabricCanvas.renderAll();
                }
            }
        }

        // Enhanced tool switching with visual feedback
        function switchTool(toolName) {
            currentTool = toolName;
            
            // Update active states
            document.querySelectorAll('.tool-item').forEach(item => {
                item.classList.remove('active');
            });
            
            const activeToolItem = document.querySelector(`[data-tool="${toolName}"]`).closest('.tool-item');
            if (activeToolItem) {
                activeToolItem.classList.add('active');
            }
            
            // Update cursor based on tool
            if (fabricCanvas) {
                switch(toolName) {
                    case 'line':
                        fabricCanvas.defaultCursor = 'crosshair';
                        break;
                    case 'rectangle':
                        fabricCanvas.defaultCursor = 'crosshair';
                        break;
                    case 'circle':
                        fabricCanvas.defaultCursor = 'crosshair';
                        break;
                    case 'freedraw':
                        fabricCanvas.defaultCursor = 'pencil';
                        break;
                    default:
                        fabricCanvas.defaultCursor = 'default';
                }
            }
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (document.getElementById('crack-drawer-modal').style.display !== 'flex') return;
            
            switch(e.key) {
                case '1':
                    switchTool('line');
                    break;
                case '2':
                    switchTool('rectangle');
                    break;
                case '3':
                    switchTool('circle');
                    break;
                case '4':
                    switchTool('freedraw');
                    break;
                case 'Escape':
                    document.getElementById('crack-drawer-modal').style.display = 'none';
                    break;
                case 'Delete':
                case 'Backspace':
                    if (e.ctrlKey) {
                        clearAllDrawings();
                        e.preventDefault();
                    }
                    break;
            }
        });
    </script>

</body>

</html>