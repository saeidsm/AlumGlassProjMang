<?php
// public_html/ghom/guide/index.php

// --- BOOTSTRAP AND SESSION ---
// Include the central bootstrap file
require_once __DIR__ . '/../../sercon/bootstrap.php';
// require_once __DIR__ . '/../includes/jdf.php';
// For demonstration, we'll simulate the session and user role.
// In your actual application, you would use your real session handling.
session_start();


// --- A mock function for isLoggedIn() for this example ---


// Secure the session (starts or resumes and applies security checks)
// secureSession(); // Assuming this function is in your bootstrap file

// --- AUTHORIZATION & PROJECT CONTEXT CHECK ---

// 1. Check if user is logged in at all
if (!isLoggedIn()) {
  header('Location: /login.php?msg=login_required');
  exit();
}

// 2. Check for allowed roles
if (!in_array($_SESSION['role'], ['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])) {
  http_response_code(403);
  // By including a file like 'Access_Denied.php' you can show a user-friendly error page.
  // For this example, we'll just exit.
  exit('Access Denied');
}

// 3. Define the expected project for this page
$expected_project_key = 'ghom';

// 4. Check if the user has selected a project and if it's the correct one
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
  // Log the attempt for security auditing
  // logError("User ID " . ($_SESSION['user_id'] ?? 'N/A') . " tried to access Ghom project page without correct session context.");
  // Redirect them to the project selection page with an error
  header('Location: /select_project.php?msg=context_mismatch');
  exit();
}

// --- PAGE SETUP ---
$pageTitle = "راهنمای پروژه بیمارستان هزار تخت خوابی قم";
//require_once __DIR__ . '/header_ghom.php';
// Include the project-specific header
require_once __DIR__ . '/header_ins.php';
// The header content is included directly in the HTML below for this self-contained example.

$user_role = $_SESSION['role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($pageTitle); ?> - راهنمای تعاملی</title>
  <style>
    @font-face {
      font-family: "Samim";
      src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
        url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
        url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
    }

    /* --- General Styles --- */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: "Samim", "Tahoma", sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      color: #333;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 20px;
    }

    h1 {
      text-align: center;

      margin-bottom: 30px;
      font-size: 2.5rem;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }

    /* --- Tabs --- */
    .workflow-tabs {
      display: flex;
      justify-content: center;
      margin-bottom: 30px;
      gap: 10px;
      flex-wrap: wrap;
    }

    .tab-button {
      background: rgba(255, 255, 255, 0.2);

      border: 2px solid rgba(255, 255, 255, 0.3);
      padding: 12px 25px;
      border-radius: 25px;
      cursor: pointer;
      transition: all 0.3s ease;
      font-weight: bold;
      font-size: 1rem;
    }

    .tab-button:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-2px);
    }

    .tab-button.active {
      background: white;
      color: #4a5568;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    /* --- Workflow Content --- */
    .workflow-content {
      display: none;
    }

    .workflow-content.active {
      display: block;
      animation: fadeIn 0.5s ease-in;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .workflow-section {
      background: white;
      border-radius: 20px;
      padding: 25px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
      flex: 1;
      min-width: 500px;
      transition: transform 0.3s ease;
      margin-bottom: 20px;
    }

    .workflow-section:hover {
      transform: translateY(-5px);
    }

    .workflow-title {
      text-align: center;
      color: #4a5568;
      margin-bottom: 20px;
      font-size: 1.5rem;
      font-weight: bold;
      padding-bottom: 10px;
      border-bottom: 3px solid #e2e8f0;
    }

    .svg-container {
      text-align: center;
      margin-bottom: 20px;
      background: #f8fafc;
      padding: 20px;
      border-radius: 15px;
      border: 2px solid #e2e8f0;
      overflow-x: auto;
    }

    /* --- Clickable SVG Elements --- */
    .clickable-element {
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .clickable-element:hover {
      filter: brightness(1.1) drop-shadow(0 0 10px rgba(59, 130, 246, 0.5));
      transform: scale(1.05);
    }

    .clickable-element.active {
      filter: drop-shadow(0 0 15px rgba(34, 197, 94, 0.8));
      animation: pulse 2s infinite;
    }

    @keyframes pulse {

      0%,
      100% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.08);
      }
    }

    /* --- Info Panel (Modal) --- */
    .info-panel {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: white;
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
      width: 90%;
      max-width: 800px;
      max-height: 85vh;
      overflow-y: auto;
      z-index: 1001;
      /* Above overlay */
      display: none;
      border: 3px solid #3b82f6;
    }

    .info-panel.show {
      display: block;
      animation: slideIn 0.4s ease-out;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translate(-50%, -60%);
      }

      to {
        opacity: 1;
        transform: translate(-50%, -50%);
      }
    }

    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      z-index: 1000;
      display: none;
      -webkit-backdrop-filter: blur(5px);
      backdrop-filter: blur(5px);
    }

    .overlay.show {
      display: block;
    }

    .close-btn {
      position: absolute;
      top: 15px;
      left: 20px;
      /* Adjusted for LTR close button on RTL page */
      background: #ef4444;
      color: white;
      border: none;
      width: 35px;
      height: 35px;
      border-radius: 50%;
      cursor: pointer;
      font-size: 18px;
      font-weight: bold;
      transition: all 0.3s ease;
      z-index: 1002;
    }

    .close-btn:hover {
      background: #dc2626;
      transform: scale(1.1);
    }

    /* --- Info Panel Content --- */
    .step-title {
      color: #1f2937;
      font-size: 1.8rem;
      margin-bottom: 20px;
      text-align: center;
      padding-bottom: 15px;
      border-bottom: 2px solid #e5e7eb;
    }

    .step-content {
      line-height: 1.8;
      color: #4b5563;
    }

    .step-role {
      background: linear-gradient(135deg, #3b82f6, #1d4ed8);
      color: white;
      padding: 8px 16px;
      border-radius: 25px;
      display: inline-block;
      margin-bottom: 15px;
      font-weight: bold;
      font-size: 0.9rem;
    }

    .step-actions {
      background: #f0f9ff;
      border: 2px solid #0ea5e9;
      border-radius: 12px;
      padding: 20px;
      margin: 20px 0;
    }

    .step-actions h4 {
      color: #0c4a6e;
      margin-bottom: 12px;
      font-size: 1.1rem;
    }

    .step-actions ol {
      padding-right: 20px;
    }

    .step-actions li {
      margin: 8px 0;
      color: #075985;
    }

    /* --- Multi-Image Display --- */
    .step-image-container {
      margin: 20px 0;
      text-align: center;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      overflow: hidden;
      background: #f9fafb;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .workflow-image {
      width: 100%;
      height: auto;
      display: block;
      border-bottom: 1px solid #e5e7eb;
      cursor: zoom-in;
      transition: transform 0.3s ease;
    }

    .workflow-image:last-child {
      border-bottom: none;
    }

    .workflow-image:hover {
      transform: scale(1.02);
    }

    .image-caption {
      padding: 12px 16px;
      background: #f8fafc;
      color: #374151;
      font-size: 14px;
      font-style: italic;
      border-top: 1px solid #e5e7eb;
    }

    /* --- Image Lightbox --- */
    .lightbox {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.85);
      z-index: 1002;
      display: none;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }

    .lightbox.show {
      display: flex;
    }

    .lightbox img {
      max-width: 95%;
      max-height: 95%;
      border-radius: 10px;
      box-shadow: 0 0 40px rgba(255, 255, 255, 0.2);
    }

    .lightbox-close {
      position: absolute;
      top: 20px;
      left: 30px;
      font-size: 3rem;
      color: white;
      cursor: pointer;
      font-weight: bold;
    }

    /* --- Main Plan Section (from screenshot) --- */
    .main-plan-section {
      border: 2px solid #b0c4de;
      border-radius: 15px;
      padding: 15px;
      background-color: #f0f8ff;
      margin-bottom: 20px;
    }

    .main-plan-title {
      font-size: 1.2rem;
      font-weight: bold;
      color: #4682b4;
      margin-bottom: 15px;
    }

    .main-plan-layout {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
    }

    .main-plan-map {
      flex: 2;
      min-width: 300px;
    }

    .main-plan-map img {
      width: 100%;
      border-radius: 10px;
      border: 2px solid #ddd;
    }

    .main-plan-zones {
      flex: 1;
      min-width: 200px;
    }

    .main-plan-zones h4 {
      margin-bottom: 10px;
      color: #333;
    }

    .zone-buttons {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 10px;
    }

    .zone-btn {
      background-color: white;
      border: 1px solid #ccc;
      padding: 10px;
      border-radius: 8px;
      cursor: pointer;
      text-align: center;
      transition: all 0.2s ease;
    }

    .zone-btn:hover {
      background-color: #e6f7ff;
      border-color: #3b82f6;
      color: #3b82f6;
    }

    /* --- Responsive --- */
    @media (max-width: 768px) {
      .container {
        padding: 10px;
      }

      h1 {
        font-size: 2rem;

      }

      .workflow-section {
        min-width: 100%;
      }

      .info-panel {
        width: calc(100% - 20px);
        max-height: calc(100vh - 20px);
      }

      .main-plan-layout {
        flex-direction: column;
      }
    }
  </style>
</head>

<body>
  <div class="container">
    <h1>🔍 راهنمای تعاملی فرآیند بازرسی</h1>

    <div class="workflow-tabs">
      <button
        class="tab-button active"
        onclick="showWorkflow('pre-inspection')">
        📋 پیش‌بازرسی
      </button>
      <button class="tab-button" onclick="showWorkflow('main-inspection')">
        🔧 بازرسی اصلی
      </button>
      <button class="tab-button" onclick="showWorkflow('summary')">
        📊 خلاصه بازرسی‌ها
      </button>
    </div>

    <!-- Pre-Inspection Workflow -->
    <div id="pre-inspection" class="workflow-content active">
      <div class="workflow-section">
        <h2 class="workflow-title">
          🚀 فرآیند پیش‌بازرسی و بازگشایی پانل (صفحه پیشا بازرسی)
        </h2>
        <div class="svg-container">
          <!-- SVG for Pre-Inspection remains the same -->
          <svg width="400" height="800" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <marker
                id="arrowhead"
                markerWidth="10"
                markerHeight="7"
                refX="10"
                refY="3.5"
                orient="auto">
                <polygon points="0 0, 10 3.5, 0 7" fill="#333" />
              </marker>
            </defs>
            <rect
              x="140"
              y="30"
              width="120"
              height="60"
              rx="5"
              fill="rgba(220, 53, 69, 0.7)"
              stroke="#333"
              stroke-width="2"
              class="clickable-element"
              data-step="waiting-rejected" />
            <text
              x="200"
              y="55"
              text-anchor="middle"
              fill="white"
              font-size="11px">
              در انتظار /
            </text>
            <text
              x="200"
              y="70"
              text-anchor="middle"
              fill="white"
              font-size="11px">
              رد شده
            </text>
            <rect
              x="140"
              y="130"
              width="120"
              height="60"
              rx="5"
              fill="rgba(13, 202, 240, 0.7)"
              stroke="#333"
              stroke-width="2"
              class="clickable-element"
              data-step="reopen-request" />
            <text
              x="200"
              y="155"
              text-anchor="middle"
              fill="black"
              font-size="11px">
              درخواست
            </text>
            <text
              x="200"
              y="170"
              text-anchor="middle"
              fill="black"
              font-size="11px">
              بازگشایی
            </text>
            <polygon
              points="200,230 250,255 200,280 150,255"
              fill="#ffc107"
              stroke="#333"
              stroke-width="2"
              class="clickable-element"
              data-step="consultant-decision" />
            <text
              x="200"
              y="250"
              text-anchor="middle"
              fill="black"
              font-size="11px">
              تصمیم
            </text>
            <text
              x="200"
              y="265"
              text-anchor="middle"
              fill="black"
              font-size="11px">
              مشاور؟
            </text>
            <rect
              x="40"
              y="330"
              width="120"
              height="50"
              rx="5"
              fill="rgba(220, 53, 69, 0.7)"
              stroke="#333"
              stroke-width="2"
              class="clickable-element"
              data-step="request-rejected" />
            <text
              x="100"
              y="360"
              text-anchor="middle"
              fill="white"
              font-size="11px">
              درخواست رد شده
            </text>
            <rect
              x="240"
              y="330"
              width="120"
              height="50"
              rx="5"
              fill="rgba(25, 135, 84, 0.7)"
              stroke="#333"
              stroke-width="2"
              class="clickable-element"
              data-step="reopen-approved" />
            <text
              x="300"
              y="360"
              text-anchor="middle"
              fill="white"
              font-size="11px">
              تایید بازگشایی
            </text>
            <rect
              x="140"
              y="430"
              width="120"
              height="60"
              rx="5"
              fill="rgba(255, 193, 7, 0.7)"
              stroke="#333"
              stroke-width="2"
              class="clickable-element"
              data-step="panel-reopened" />
            <text
              x="200"
              y="455"
              text-anchor="middle"
              fill="black"
              font-size="11px">
              پانل بازگشایی
            </text>
            <text
              x="200"
              y="470"
              text-anchor="middle"
              fill="black"
              font-size="11px">
              شده
            </text>
            <polygon
              points="200,530 250,555 200,580 150,555"
              fill="#ffc107"
              stroke="#333"
              stroke-width="2"
              class="clickable-element"
              data-step="final-review" />
            <text
              x="200"
              y="550"
              text-anchor="middle"
              fill="black"
              font-size="11px">
              بازبینی
            </text>
            <text
              x="200"
              y="565"
              text-anchor="middle"
              fill="black"
              font-size="11px">
              بازگشایی؟
            </text>
            <rect
              x="40"
              y="630"
              width="120"
              height="50"
              rx="5"
              fill="rgba(220, 53, 69, 0.9)"
              stroke="#333"
              stroke-width="2"
              class="clickable-element"
              data-step="reopen-not-approved" />
            <text
              x="100"
              y="650"
              text-anchor="middle"
              fill="white"
              font-size="11px">
              بازگشایی
            </text>
            <text
              x="100"
              y="665"
              text-anchor="middle"
              fill="white"
              font-size="11px">
              مورد تایید نیست
            </text>
            <circle
              cx="300"
              cy="655"
              r="40"
              fill="rgba(255, 193, 7, 0.9)"
              stroke="#d4a024"
              stroke-width="4"
              class="clickable-element"
              data-step="ready-for-inspection" />
            <text
              x="300"
              y="645"
              text-anchor="middle"
              fill="black"
              font-size="11px"
              font-weight="bold">
              (آماده برای
            </text>
            <text
              x="300"
              y="665"
              text-anchor="middle"
              fill="black"
              font-size="11px"
              font-weight="bold">
              بازرسی اصلی)
            </text>
            <line
              x1="200"
              y1="90"
              x2="200"
              y2="130"
              stroke="#333"
              stroke-width="2"
              marker-end="url(#arrowhead)" />
            <text x="250" y="115" fill="black" font-size="12px">
              پیمانکار
            </text>
            <line
              x1="200"
              y1="190"
              x2="200"
              y2="230"
              stroke="#333"
              stroke-width="2"
              marker-end="url(#arrowhead)" />
            <text x="250" y="215" fill="black" font-size="12px">مشاور</text>
            <line
              x1="150"
              y1="255"
              x2="100"
              y2="255"
              stroke="#333"
              stroke-width="2" />
            <line
              x1="100"
              y1="255"
              x2="100"
              y2="330"
              stroke="#333"
              stroke-width="2"
              marker-end="url(#arrowhead)" />
            <text
              x="125"
              y="250"
              text-anchor="middle"
              fill="black"
              font-size="12px">
              رد
            </text>
            <line
              x1="250"
              y1="255"
              x2="300"
              y2="255"
              stroke="#333"
              stroke-width="2" />
            <line
              x1="300"
              y1="255"
              x2="300"
              y2="330"
              stroke="#333"
              stroke-width="2"
              marker-end="url(#arrowhead)" />
            <text
              x="275"
              y="250"
              text-anchor="middle"
              fill="black"
              font-size="12px">
              تایید
            </text>
            <line
              x1="40"
              y1="355"
              x2="20"
              y2="355"
              stroke="#333"
              stroke-width="2" />
            <line
              x1="20"
              y1="355"
              x2="20"
              y2="160"
              stroke="#333"
              stroke-width="2" />
            <line
              x1="20"
              y1="160"
              x2="140"
              y2="160"
              stroke="#333"
              stroke-width="2"
              marker-end="url(#arrowhead)" />
            <text
              x="10"
              y="255"
              text-anchor="middle"
              fill="black"
              font-size="12px"
              transform="rotate(-90, 10, 255)">
              پیمانکار
            </text>
            <line
              x1="300"
              y1="380"
              x2="300"
              y2="410"
              stroke="#333"
              stroke-width="2" />
            <line
              x1="300"
              y1="410"
              x2="200"
              y2="410"
              stroke="#333"
              stroke-width="2" />
            <line
              x1="200"
              y1="410"
              x2="200"
              y2="430"
              stroke="#333"
              stroke-width="2"
              marker-end="url(#arrowhead)" />
            <text
              x="250"
              y="405"
              text-anchor="middle"
              fill="black"
              font-size="12px">
              پیمانکار
            </text>
            <line
              x1="200"
              y1="490"
              x2="200"
              y2="530"
              stroke="#333"
              stroke-width="2"
              marker-end="url(#arrowhead)" />
            <text x="250" y="515" fill="black" font-size="12px">مشاور</text>
            <line
              x1="150"
              y1="555"
              x2="100"
              y2="555"
              stroke="#333"
              stroke-width="2" />
            <line
              x1="100"
              y1="555"
              x2="100"
              y2="630"
              stroke="#333"
              stroke-width="2"
              marker-end="url(#arrowhead)" />
            <text
              x="125"
              y="550"
              text-anchor="middle"
              fill="black"
              font-size="12px">
              رد
            </text>
            <line
              x1="250"
              y1="555"
              x2="300"
              y2="555"
              stroke="#333"
              stroke-width="2" />
            <line
              x1="300"
              y1="555"
              x2="300"
              y2="615"
              stroke="#333"
              stroke-width="2"
              marker-end="url(#arrowhead)" />
            <text
              x="275"
              y="545"
              text-anchor="middle"
              fill="black"
              font-size="12px">
              تایید نهایی
            </text>
            <line
              x1="40"
              y1="655"
              x2="20"
              y2="655"
              stroke="#333"
              stroke-width="2" />
            <line
              x1="20"
              y1="655"
              x2="20"
              y2="460"
              stroke="#333"
              stroke-width="2" />
            <line
              x1="20"
              y1="460"
              x2="140"
              y2="460"
              stroke="#333"
              stroke-width="2"
              marker-end="url(#arrowhead)" />
            <text
              x="10"
              y="555"
              text-anchor="middle"
              fill="black"
              font-size="12px"
              transform="rotate(-90, 10, 555)">
              پیمانکار
            </text>
          </svg>
        </div>
      </div>
    </div>

    <!-- Main Inspection Workflow -->
    <div id="main-inspection" class="workflow-content">
      <div class="workflow-section">
        <h2 class="workflow-title">🔧 فرآیند بازرسی اصلی (صفحه خانه)</h2>
        <div class="svg-container">
          <!-- SVG for Main Inspection remains the same -->
          <svg
            width="800"
            height="500"
            viewBox="0 0 900 750"
            xmlns="http://www.w3.org/2000/svg"
            font-family="Samim, Tahoma, sans-serif">
            <defs>
              <style>
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
                  marker-end: url(#arrowhead2);
                }

                .arrow-reject {
                  stroke: #d32f2f;
                  stroke-width: 2.5;
                  marker-end: url(#arrowhead-reject2);
                }

                .arrow-ok {
                  stroke: #388e3c;
                  stroke-width: 2.5;
                  marker-end: url(#arrowhead-ok2);
                }

                .arrow-repair {
                  stroke: #f57c00;
                  stroke-width: 2;
                  marker-end: url(#arrowhead-repair2);
                }
              </style>
              <marker
                id="arrowhead2"
                viewBox="0 0 10 10"
                refX="9"
                refY="5"
                markerWidth="6"
                markerHeight="6"
                orient="auto">
                <path d="M 0 0 L 10 5 L 0 10 z" fill="#333" />
              </marker>
              <marker
                id="arrowhead-reject2"
                viewBox="0 0 10 10"
                refX="9"
                refY="5"
                markerWidth="6"
                markerHeight="6"
                orient="auto">
                <path d="M 0 0 L 10 5 L 0 10 z" fill="#d32f2f" />
              </marker>
              <marker
                id="arrowhead-ok2"
                viewBox="0 0 10 10"
                refX="9"
                refY="5"
                markerWidth="6"
                markerHeight="6"
                orient="auto">
                <path d="M 0 0 L 10 5 L 0 10 z" fill="#388e3c" />
              </marker>
              <marker
                id="arrowhead-repair2"
                viewBox="0 0 10 10"
                refX="9"
                refY="5"
                markerWidth="6"
                markerHeight="6"
                orient="auto">
                <path d="M 0 0 L 10 5 L 0 10 z" fill="#f57c00" />
              </marker>
            </defs>
            <ellipse
              cx="500"
              cy="70"
              rx="120"
              ry="35"
              class="system-box box clickable-element"
              data-step="start-inspection" />
            <text x="500" y="65" class="text-main">شروع فرآیند</text>
            <text x="500" y="80" class="text-small">
              المان با وضعیت "آماده بازرسی"
            </text>
            <rect
              x="400"
              y="150"
              width="200"
              height="50"
              rx="8"
              class="consultant-box box clickable-element"
              data-step="open-form" />
            <text x="500" y="175" class="text-main">
              ۱. مشاور فرم را باز می‌کند
            </text>
            <path
              d="M 500 250 L 580 300 L 500 350 L 420 300 Z"
              class="decision-box box clickable-element"
              data-step="status-decision" />
            <text x="500" y="295" class="text-main">۲. ثبت وضعیت</text>
            <text x="500" y="310" class="text-small">
              (OK / Repair / Reject)
            </text>
            <ellipse
              cx="730"
              cy="300"
              rx="80"
              ry="35"
              class="final-box box clickable-element"
              data-step="process-complete" />
            <text x="730" y="300" class="text-main">اتمام فرآیند</text>
            <rect
              x="150"
              y="275"
              width="170"
              height="50"
              rx="8"
              class="contractor-box box clickable-element"
              data-step="contractor-repair" />
            <text x="235" y="300" class="text-main">
              ۳. پیمانکار تعمیر می‌کند
            </text>
            <rect
              x="150"
              y="375"
              width="170"
              height="50"
              rx="8"
              class="contractor-box box clickable-element"
              data-step="repair-complete" />
            <text x="235" y="400" class="text-main">
              ۴. پیمانکار اعلام اتمام تعمیر
            </text>
            <rect
              x="130"
              y="535"
              width="200"
              height="50"
              rx="9"
              class="consultant-box box clickable-element"
              data-step="consultant-recheck" />
            <text x="222" y="560" class="text-main">
              ۵. مشاور مجدداً بررسی می‌کند
            </text>
            <path
              d="m 496,502 80,50 -80,50 -80,-50 z"
              class="decision-box box clickable-element"
              data-step="repair-review" />
            <text x="500" y="545" class="text-main">بررسی نتیجه تعمیر</text>
            <rect
              x="352"
              y="652"
              width="300"
              height="70"
              rx="8"
              class="system-box box clickable-element"
              data-step="reject-count-check" />
            <text x="500" y="672" class="text-main" font-weight="bold">
              بررسی تعداد رد تعمیر (سیستم)
            </text>
            <text x="500" y="692" class="text-small">
              اگر کمتر از ۳ بار: بازگشت به تعمیر
            </text>
            <text x="500" y="707" class="text-small">
              اگر ۳ بار یا بیشتر: رد نهایی
            </text>
            <rect
              x="400"
              y="420"
              width="200"
              height="80"
              rx="8"
              class="final-box box clickable-element"
              data-step="final-reject" />
            <text x="500" y="445" class="text-main">۶. رد نهایی المان</text>
            <text x="500" y="465" class="text-small">
              نیاز به ساخت/خرید المان جدید
            </text>
            <text x="500" y="480" class="text-small">
              و بازگشت به شروع فرآیند
            </text>
            <line x1="500" y1="105" x2="500" y2="150" class="arrow" />
            <line x1="500" y1="200" x2="500" y2="250" class="arrow" />
            <line x1="580" y1="300" x2="650" y2="300" class="arrow-ok" />
            <text
              x="620"
              y="290"
              style="font-size: 12px; font-weight: bold; fill: #388e3c">
              OK
            </text>
            <line x1="420" y1="300" x2="320" y2="300" class="arrow-repair" />
            <text
              x="370"
              y="290"
              style="font-size: 12px; font-weight: bold; fill: #f57c00">
              Repair
            </text>
            <line x1="235" y1="325" x2="235" y2="375" class="arrow" />
            <line x1="235" y1="425" x2="235" y2="535" class="arrow" />
            <line x1="330" y1="552" x2="415" y2="552" class="arrow" />
            <path
              d="m 571,552 q 81,0 140,-82 l 30,-135"
              stroke="#388e3c"
              stroke-width="2.5"
              fill="none"
              marker-end="url(#arrowhead-ok2)" />
            <text
              x="700"
              y="470"
              style="font-size: 12px; font-weight: bold; fill: #388e3c">
              OK
            </text>
            <path
              d="M 420,552 H 350 V 300"
              stroke="#f57c00"
              stroke-width="2"
              fill="none"
              marker-end="url(#arrowhead-repair2)"
              stroke-dasharray="5, 5" />
            <text
              x="360"
              y="420"
              style="
                  font-size: 12px;
                  font-weight: bold;
                  fill: #f57c00;
                  writing-mode: vertical-rl;
                ">
              Reject Repair
            </text>
            <line x1="498" y1="601" x2="498" y2="651" class="arrow" />
            <path
              d="M 350,680 H 110 v -380 H 150"
              stroke="#333"
              stroke-width="2"
              fill="none"
              marker-end="url(#arrowhead2)"
              stroke-dasharray="5, 5" />
            <text
              x="110"
              y="490"
              style="font-size: 12px; fill: #666; writing-mode: vertical-rl">
              کمتر از ۳ بار
            </text>
            <path
              d="M 650,680 H 850 v -610 H 618"
              stroke="#d32f2f"
              stroke-width="2"
              fill="none"
              marker-end="url(#arrowhead-reject2)"
              stroke-dasharray="5, 5" />
            <text
              x="700"
              y="673"
              style="font-size: 12px; font-weight: bold; fill: #d32f2f">
              ۳ بار رد
            </text>
            <line x1="500" y1="350" x2="500" y2="420" class="arrow-reject" />
            <text
              x="540"
              y="385"
              style="font-size: 12px; font-weight: bold; fill: #d32f2f">
              Reject
            </text>
            <path
              d="M 850,450 H 600"
              stroke="#d32f2f"
              stroke-width="2"
              fill="none"
              marker-end="url(#arrowhead-reject2)"
              stroke-dasharray="8, 8" />
            <text x="725" y="440" style="font-size: 12px; fill: #d32f2f">
              بازگشت به شروع
            </text>
          </svg>
        </div>
      </div>
    </div>

    <!-- Summary Workflow -->
    <div id="summary" class="workflow-content">
      <div class="workflow-section">
        <h2 class="workflow-title">📊 خلاصه و گزارش‌گیری بازرسی‌ها</h2>
        <div class="step-content" style="padding: 20px; text-align: center">
          <p>
            این بخش برای نمایش گزارش‌های کلی و خلاصه‌ای از وضعیت تمام
            بازرسی‌ها در نظر گرفته شده است.
          </p>
          <div class="step-actions">
            <h4>🎯 قابلیت‌های این صفحه:</h4>
            <ol>
              <li>
                مشاهده آمار کلی بازرسی‌ها (تایید شده، در حال تعمیر، رد شده).
              </li>
              <li>فیلتر کردن بازرسی‌ها بر اساس زون، بلوک، وضعیت و تاریخ.</li>
              <li>جستجو بر اساس شماره پانل یا المان.</li>
              <li>خروجی گرفتن از گزارش‌ها به صورت فایل اکسل یا PDF.</li>
              <li>نمایش نمودارهای تحلیلی از روند پیشرفت و مشکلات رایج.</li>
            </ol>
          </div>
          <div class="step-image-container">
            <img
              src="/ghom/assets/images/summary/summary_dashboard.png"
              alt="داشبورد خلاصه بازرسی‌ها"
              class="workflow-image" />
            <div class="image-caption">
              نمایی از داشبورد گزارش‌گیری با نمودارها و آمار کلی
            </div>
          </div>
          <div class="step-image-container">
            <img
              src="/ghom/assets/images/summary/summary_table.png"
              alt="جدول گزارشات با فیلتر"
              class="workflow-image" />
            <div class="image-caption">
              جدول داده‌ها با قابلیت فیلتر و جستجوی پیشرفته
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Overlay and Info Panel -->
  <div class="overlay" onclick="closeInfo()"></div>
  <div class="info-panel">
    <button class="close-btn" onclick="closeInfo()">×</button>
    <div id="info-content"></div>
  </div>

  <!-- Image Lightbox -->
  <div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close">&times;</span>
    <img src="" alt="بزرگنمایی تصویر" id="lightbox-img" />
  </div>

  <script>
    // --- Data Definitions ---
    const workflowSteps = {
      // --- Pre-inspection steps ---
      "waiting-rejected": {
        title: "📋 وضعیت در انتظار / رد شده",
        role: "وضعیت اولیه",
        content: `
                <p>این مرحله نقطه شروع در <strong>صفحه پیشا بازرسی</strong> است. پانل‌ها در این وضعیت منتظر اقدام از سوی پیمانکار برای ارسال درخواست بازگشایی هستند.</p>
                <div class="main-plan-section">
                    <h4 class="main-plan-title">۱. انتخاب زون از روی نقشه</h4>
                    <p>ابتدا از روی نقشه اصلی، زون مورد نظر را انتخاب کنید تا لیست پانل‌های آن نمایش داده شود.</p>
                    <div class="main-plan-layout">
                        <div class="main-plan-map"><img src="/ghom/assets/images/plans/main_plan.jpeg" alt="نقشه اصلی پروژه"></div>
                        <div class="main-plan-zones">
                            <h4>زون‌های موجود برای بلوک A</h4>
                            <div class="zone-buttons">
                                <button class="zone-btn">زون ۱ (نقشه نما)</button>
                                <button class="zone-btn">زون ۲ (نقشه نما)</button>
                                <button class="zone-btn">زون ۳ (نقشه نما)</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="step-actions"><h4>۲. مشاهده لیست و اقدام</h4><ol><li>پس از انتخاب زون، لیست پانل‌ها نمایش داده می‌شود.</li><li>پانل مورد نظر با وضعیت "در انتظار" یا "رد شده" را پیدا کنید.</li><li>برای ادامه، باید "درخواست بازگشایی" ثبت کنید.</li></ol></div>
                <div class="step-image-container">
                    <img src="/ghom/assets/images/pre-inspection/1_panel_list.png" alt="لیست وضعیت پانل‌ها" class="workflow-image">
                    <img src="/ghom/assets/images/pre-inspection/2_panel_details.png" alt="جزئیات وضعیت پانل‌ها" class="workflow-image">
                    <img src="/ghom/assets/images/pre-inspection/3_panel_details.png" alt="جزئیات وضعیت پانل‌ها" class="workflow-image">
                    <div class="image-caption">صفحه لیست پانل‌ها و جزئیات هر پانل</div>
                </div>`,
      },
      "reopen-request": {
        title: "🔄 درخواست بازگشایی",
        role: "پیمانکار",
        content: `<p>پیمانکار برای پانلی که نیاز به اصلاح دارد، درخواست بازگشایی ثبت می‌کند تا بتواند تغییرات لازم را اعمال کند.</p><div class="step-actions"><h4>مراحل ارسال درخواست:</h4><ol><li>در صفحه "پیشا بازرسی"، پانل مورد نظر را انتخاب کنید.</li><li>روی دکمه "درخواست بازگشایی" کلیک کنید.</li><li>دلیل و توضیحات لازم برای بازگشایی را در فرم وارد نمایید.</li><li>درخواست را برای بررسی مشاور ارسال کنید.</li></ol></div><div class="step-image-container"><img src="/ghom/assets/images/pre-inspection/3_reopen_request_form.png" alt="فرم درخواست بازگشایی" class="workflow-image"><div class="image-caption">فرم ثبت درخواست بازگشایی</div></div>`,
      },
      "consultant-decision": {
        title: "⚖️ تصمیم‌گیری مشاور",
        role: "مشاور",
        content: `<p>مشاور درخواست ارسال شده توسط پیمانکار را بررسی کرده و آن را تایید یا رد می‌کند.</p><div class="step-actions"><h4>مراحل بررسی:</h4><ol><li>مشاور در پنل خود نوتیفیکیشن درخواست جدید را مشاهده می‌کند.</li><li>با بررسی دلیل و وضعیت پانل، تصمیم خود را اعلام می‌کند.</li></ol></div><div class="step-image-container"><img src="/ghom/assets/images/pre-inspection/4_consultant_approval.png" alt="پنل بررسی درخواست مشاور" class="workflow-image"><div class="image-caption">صفحه بررسی درخواست‌ها در پنل مشاور</div></div>`,
      },
      "request-rejected": {
        title: "❌ درخواست رد شده",
        role: "نتیجه",
        content: `<p>اگر مشاور درخواست را رد کند، پانل به وضعیت قبلی بازمی‌گردد و پیمانکار باید پس از رفع ایرادات، مجدداً درخواست دهد.</p><div class="step-actions"><h4>اقدامات بعدی:</h4><ol><li>پیمانکار دلیل رد درخواست را از توضیحات مشاور مطالعه می‌کند.</li><li>ایرادات را برطرف کرده و مجدداً درخواست بازگشایی ثبت می‌کند.</li></ol></div><div class="step-image-container"><img src="/ghom/assets/images/pre-inspection/5_request_rejected_notification.png" alt="پیام رد درخواست" class="workflow-image"><div class="image-caption">اطلاع‌رسانی رد درخواست به پیمانکار</div></div>`,
      },
      "reopen-approved": {
        title: "✅ تایید بازگشایی",
        role: "نتیجه مثبت",
        content: `<p>در صورت تایید مشاور، پانل برای اعمال تغییرات توسط پیمانکار بازگشایی می‌شود.</p><div class="step-actions"><h4>مراحل بعدی:</h4><ol><li>وضعیت پانل به "بازگشایی شده" تغییر می‌کند.</li><li>پیمانکار می‌تواند اطلاعات و فایل‌های پانل را ویرایش کند.</li></ol></div>`,
      },
      "panel-reopened": {
        title: "🔓 پانل بازگشایی شده",
        role: "وضعیت جدید",
        content: `<p>پیمانکار پس از اعمال تغییرات لازم، پانل را برای بازبینی نهایی توسط مشاور آماده می‌کند.</p><div class="step-actions"><h4>اقدامات پیمانکار:</h4><ol><li>اعمال تغییرات و اصلاحات مورد نیاز.</li><li>آپلود مستندات و نقشه‌های جدید در صورت لزوم.</li><li>ارسال پانل برای بازبینی نهایی مشاور.</li></ol></div><div class="step-image-container"><img src="/ghom/assets/images/pre-inspection/6_reopened_panel_edit.png" alt="ویرایش پانل بازگشایی شده" class="workflow-image"><div class="image-caption">صفحه ویرایش پانل پس از بازگشایی</div></div>`,
      },
      "final-review": {
        title: "🔍 بازبینی نهایی",
        role: "مشاور",
        content: `<p>مشاور تغییرات اعمال شده روی پانل بازگشایی شده را بررسی کرده و تصمیم نهایی را برای ورود به مرحله بازرسی اصلی می‌گیرد.</p><div class="step-actions"><h4>مراحل بازبینی:</h4><ol><li>بررسی تغییرات و مستندات جدید.</li><li>در صورت تایید، وضعیت پانل به "آماده بازرسی" تغییر می‌کند.</li><li>در صورت عدم تایید، پانل به وضعیت "بازگشایی شده" بازگردانده می‌شود تا پیمانکار ایرادات را برطرف کند.</li></ol></div>`,
      },
      "reopen-not-approved": {
        title: "⚠️ بازگشایی مورد تایید نیست",
        role: "عدم تایید",
        content: `<p>مشاور پس از بازبینی نهایی، ایراداتی را مشاهده کرده و پانل را برای اصلاح مجدد به پیمانکار بازمی‌گرداند.</p><div class="step-actions"><h4>اقدامات مورد نیاز:</h4><ol><li>پیمانکار نظرات مشاور را بررسی و اصلاحات لازم را انجام می‌دهد.</li><li>پس از اصلاح، مجدداً پانل را برای بازبینی نهایی ارسال می‌کند.</li></ol></div>`,
      },
      "ready-for-inspection": {
        title: "🎯 آماده بازرسی اصلی",
        role: "وضعیت نهایی",
        content: `<p>پانل با موفقیت مراحل پیش‌بازرسی را طی کرده و اکنون در <strong>صفحه خانه</strong> در لیست "آماده بازرسی" نمایش داده می‌شود تا فرآیند بازرسی اصلی روی آن انجام شود.</p><div class="step-image-container"><img src="/ghom/assets/images/main-inspection/1_ready_list.png" alt="لیست آماده بازرسی" class="workflow-image"><div class="image-caption">نمایش پانل در لیست "آماده بازرسی" در صفحه خانه</div></div>`,
      },
      // --- Main inspection steps ---
      "start-inspection": {
        title: "🚀 شروع فرآیند بازرسی",
        role: "سیستم",
        content: `<p>این فرآیند در <strong>صفحه خانه</strong> انجام می‌شود. المان‌هایی که وضعیت "آماده بازرسی" دارند، در لیست بازرسی‌های جدید برای مشاور نمایش داده می‌شوند.</p><div class="main-plan-section"><h4 class="main-plan-title">۱. انتخاب المان برای بازرسی</h4><p>مشاور از لیست المان‌های آماده، یکی را برای شروع بازرسی انتخاب می‌کند.</p></div><div class="step-image-container"><img src="/ghom/assets/images/main-inspection/1_ready_list.png" alt="لیست المان‌های آماده بازرسی" class="workflow-image"><div class="image-caption">صفحه خانه با لیست المان‌های آماده برای بازرسی</div></div>`,
      },
      "open-form": {
        title: "📝 باز کردن فرم بازرسی",
        role: "مشاور",
        content: `<p>مشاور با کلیک روی دکمه "شروع بازرسی"، فرم مربوطه را باز کرده و اطلاعات و چک‌لیست‌ها را تکمیل می‌کند.</p><div class="step-actions"><h4>مراحل:</h4><ol><li>انتخاب المان از لیست "آماده بازرسی".</li><li>باز شدن فرم بازرسی با چک‌لیست‌های مربوطه.</li><li>تکمیل فرم و ثبت مشاهدات.</li></ol></div><div class="step-image-container"><img src="/ghom/assets/images/main-inspection/2_inspection_form.png" alt="فرم بازرسی" class="workflow-image"><div class="image-caption">فرم بازرسی با چک‌لیست‌ها و محل ثبت وضعیت</div></div>`,
      },
      "status-decision": {
        title: "⚖️ ثبت وضعیت بازرسی",
        role: "مشاور",
        content: `<p>پس از تکمیل فرم، مشاور یکی از سه وضعیت زیر را برای المان ثبت می‌کند.</p><div class="step-actions"><h4>گزینه‌ها:</h4><ol><li><strong style="color: #059669;">OK:</strong> المان تایید نهایی شده و فرآیند تمام می‌شود.</li><li><strong style="color: #dc6600;">Repair:</strong> المان نیاز به تعمیر دارد و به کارتابل پیمانکار ارجاع داده می‌شود.</li><li><strong style="color: #dc2626;">Reject:</strong> المان به طور کامل رد شده و باید از ابتدا ساخته شود.</li></ol></div><div class="step-image-container"><img src="/ghom/assets/images/main-inspection/3_status_decision.png" alt="ثبت نتیجه بازرسی" class="workflow-image"><div class="image-caption">بخش ثبت وضعیت نهایی در فرم بازرسی</div></div>`,
      },
      "process-complete": {
        title: "✅ اتمام موفق فرآیند",
        role: "نتیجه نهایی",
        content: `<p>المان با موفقیت تایید شده و در سیستم به عنوان "تایید شده" ثبت می‌گردد.</p><div class="step-image-container"><img src="/ghom/assets/images/main-inspection/4_process_complete.png" alt="پیام تایید نهایی" class="workflow-image"><div class="image-caption">نمایش وضعیت "تایید شده" برای المان</div></div>`,
      },
      "contractor-repair": {
        title: "🔧 شروع تعمیرات",
        role: "پیمانکار",
        content: `<p>المان‌هایی که نیاز به تعمیر دارند، در کارتابل پیمانکار نمایش داده می‌شوند. پیمانکار پس از مشاهده گزارش مشاور، تعمیرات را آغاز می‌کند.</p><div class="step-image-container"><img src="/ghom/assets/images/main-inspection/5_repair_list.png" alt="لیست تعمیرات پیمانکار" class="workflow-image"><div class="image-caption">کارتابل پیمانکار با لیست المان‌های نیازمند تعمیر</div></div>`,
      },
      "repair-complete": {
        title: "📋 اعلام اتمام تعمیر",
        role: "پیمانکار",
        content: `<p>پس از انجام تعمیرات، پیمانکار فرم مربوطه را تکمیل و با ارائه توضیحات و مستندات، المان را برای بازبررسی توسط مشاور ارسال می‌کند.</p><div class="step-image-container"><img src="/ghom/assets/images/main-inspection/6_repair_complete_form.png" alt="فرم اعلام اتمام تعمیر" class="workflow-image"><div class="image-caption">فرم ثبت جزئیات تعمیرات انجام شده</div></div>`,
      },
      "consultant-recheck": {
        title: "🔍 بازبررسی مشاور",
        role: "مشاور",
        content: `<p>مشاور تعمیرات انجام شده را مجدداً بررسی کرده و نتیجه را اعلام می‌کند.</p>`,
      },
      "repair-review": {
        title: "📊 بررسی نتیجه تعمیر",
        role: "مشاور",
        content: `<p>مشاور تصمیم می‌گیرد که آیا تعمیرات کافی بوده (OK) یا همچنان مشکل پابرجاست (Reject Repair).</p><div class="step-actions"><h4>تصمیم‌ها:</h4><ol><li><strong>OK:</strong> المان تایید نهایی می‌شود.</li><li><strong>Reject Repair:</strong> المان مجدداً به کارتابل پیمانکار برای تعمیر بازمی‌گردد.</li></ol></div>`,
      },
      "reject-count-check": {
        title: "🤖 بررسی تعداد رد تعمیر",
        role: "سیستم",
        content: `<p>سیستم به طور خودکار تعداد دفعاتی که تعمیر یک المان رد شده را می‌شمارد. اگر این تعداد به حد نصاب (مثلاً ۳ بار) برسد، المان به طور خودکار به وضعیت "رد نهایی" منتقل می‌شود.</p>`,
      },
      "final-reject": {
        title: "❌ رد نهایی المان",
        role: "نتیجه نهایی",
        content: `<p>المان به دلیل عدم موفقیت در تعمیرات مکرر، به طور کامل رد می‌شود و باید فرآیند ساخت و بازرسی از ابتدا برای یک المان جدید آغاز شود.</p>`,
      },
    };

    // --- Core Functions ---
    function showWorkflow(workflowId) {
      document
        .querySelectorAll(".workflow-content")
        .forEach((content) => content.classList.remove("active"));
      document.getElementById(workflowId).classList.add("active");
      document
        .querySelectorAll(".tab-button")
        .forEach((btn) => btn.classList.remove("active"));
      event.currentTarget.classList.add("active");
      window.scrollTo(0, 0);
    }

    function showStepInfo(stepId) {
      const step = workflowSteps[stepId];
      if (!step) return;

      const infoContent = document.getElementById("info-content");
      infoContent.innerHTML = `
            <h2 class="step-title">${step.title}</h2>
            <div class="step-role">${step.role}</div>
            <div class="step-content">${step.content}</div>`;

      document.querySelector(".overlay").classList.add("show");
      document.querySelector(".info-panel").classList.add("show");

      // Add click listeners to images inside the newly created info panel
      infoContent.querySelectorAll(".workflow-image").forEach((img) => {
        img.addEventListener("click", (e) => {
          e.stopPropagation(); // Prevent closing info panel
          openLightbox(img.src);
        });
      });
    }

    function closeInfo() {
      document.querySelector(".overlay").classList.remove("show");
      document.querySelector(".info-panel").classList.remove("show");
      document
        .querySelectorAll(".clickable-element.active")
        .forEach((el) => el.classList.remove("active"));
    }

    function openLightbox(src) {
      document.getElementById("lightbox-img").src = src;
      document.getElementById("lightbox").classList.add("show");
    }

    function closeLightbox() {
      document.getElementById("lightbox").classList.remove("show");
    }

    // --- Event Listeners ---
    document.addEventListener("DOMContentLoaded", function() {
      document.querySelectorAll(".clickable-element").forEach((element) => {
        element.addEventListener("click", function(event) {
          event.stopPropagation();
          document
            .querySelectorAll(".clickable-element.active")
            .forEach((el) => el.classList.remove("active"));
          this.classList.add("active");
          const stepId = this.getAttribute("data-step");
          showStepInfo(stepId);
        });
      });

      document.querySelector(".overlay").addEventListener("click", closeInfo);

      document.addEventListener("keydown", function(e) {
        if (e.key === "Escape") {
          if (
            document.getElementById("lightbox").classList.contains("show")
          ) {
            closeLightbox();
          } else {
            closeInfo();
          }
        }
      });

      // Make images in the main content clickable too
      document
        .querySelectorAll(".workflow-content .workflow-image")
        .forEach((img) => {
          img.addEventListener("click", (e) => {
            e.stopPropagation();
            openLightbox(img.src);
          });
        });
    });
  </script>

  <footer class="footer">
    <div class="footer-container">
      <div class="footer-content">
        <p class="footer-text">تمامی حقوق محفوظ است © <script>
            document.write(new Date().getFullYear());
          </script> شرکت آلومینیوم شیشه تهران</p>
      </div>
      <!-- Scroll to Top Button -->
      <button id="scrollToTopBtn" title="برو بالا" class="scroll-to-top-btn">
        <!-- Up Arrow SVG -->
        <svg xmlns="http://www.w3.org/2000/svg" class="scroll-icon" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
        </svg>
      </button>
    </div>
  </footer>

  <script>
    // Scroll to Top Button Functionality
    document.addEventListener('DOMContentLoaded', function() {
      const scrollToTopButton = document.getElementById('scrollToTopBtn');

      // Show/Hide button based on scroll position
      window.onscroll = function() {
        if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
          scrollToTopButton.classList.add('show');
        } else {
          scrollToTopButton.classList.remove('show');
        }
      };

      // Smooth scroll to top on click
      scrollToTopButton.addEventListener('click', function() {
        // For modern browsers
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });

        // For older browsers (IE/Edge)
        document.body.scrollTop = 0; // For Safari
        document.documentElement.scrollTop = 0; // For Chrome, Firefox, IE, and Opera
      });
    });
  </script>
</body>

</html>