<?php
// public_html/ghom/viewer.php (FINAL VERSION with new workflow logic)

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}
function toPersianDate($gregorian_date)
{
    if (empty($gregorian_date)) return null;
    $timestamp = strtotime($gregorian_date);
    if ($timestamp === false) return $gregorian_date;
    return jdate('Y/m/d', $timestamp, '', 'Asia/Tehran', 'fa');
}
// --- Get Parameters ---
$plan_file = $_GET['plan'] ?? null;
$highlight_type = $_GET['highlight'] ?? 'all';
$highlight_status = $_GET['highlight_status'] ?? 'all';
$highlight_element_id = $_GET['highlight_element'] ?? null;
$gradient_colors_str = $_GET['gradient_colors'] ?? null;
$hide_layer = $_GET['hide_layer'] ?? null;
if (!$plan_file || !preg_match('/^[\w\.-]+\.svg$/i', $plan_file)) {
    http_response_code(400);
    die("Error: Invalid plan file name.");
}
$zone_name = 'نامشخص';
$contractor_name = 'نامشخص';
$block_name = 'نامشخص';
$elements_data = [];
$status_counts = [
    'OK' => 0,
    'Reject' => 0,
    'Repair' => 0,
    'Pre-Inspection Complete' => 0,
    'Awaiting Re-inspection' => 0,
    'Pending' => 0
];
try {
    $pdo = getProjectDBConnection('ghom');

    // --- START OF NEW, ROBUST QUERY ---
    // This query uses the same logic as the dashboard to get the single, definitive latest status for each element part.
    $stmt = $pdo->prepare("
         WITH LatestStageInspections AS (
            -- Step 1: Find the most recent inspection for EACH stage of EACH element
            SELECT
                i.element_id,
                i.stage_id,
                i.inspection_date,
                i.notes,
                i.contractor_notes,
                i.contractor_date,
                i.history_log,
                i.pre_inspection_log,
                i.status,
                ROW_NUMBER() OVER(PARTITION BY i.element_id, i.stage_id ORDER BY i.created_at DESC, i.inspection_id DESC) as rn
            FROM inspections i
        ),
        OverallElementStatus AS (
            -- Step 2: Determine the single most critical status for each element
            SELECT
                lsi.element_id,
                -- Updated priority for new statuses: 1 (highest) to 6 (lowest)
                MIN(CASE
                    WHEN lsi.status = 'Reject' THEN 1
                    WHEN lsi.status = 'Repair' THEN 2
                    WHEN lsi.status = 'OK' THEN 3
                    WHEN lsi.status = 'Awaiting Re-inspection' THEN 4
                    WHEN lsi.status = 'Pre-Inspection Complete' THEN 5
                    WHEN lsi.status = 'Pending' THEN 6
                    WHEN lsi.status IS NULL THEN 7
                    ELSE 7
                END) as highest_priority_status,
                COUNT(lsi.element_id) as inspection_count,
                -- Get the most recent inspection data for additional details
                MAX(CASE WHEN lsi.rn = 1 THEN lsi.history_log END) as latest_history_log,
                MAX(CASE WHEN lsi.rn = 1 THEN lsi.pre_inspection_log END) as latest_pre_inspection_log,
                MAX(CASE WHEN lsi.rn = 1 THEN lsi.notes END) as latest_notes,
                MAX(CASE WHEN lsi.rn = 1 THEN lsi.inspection_date END) as latest_inspection_date
            FROM LatestStageInspections lsi
            WHERE lsi.rn = 1
            GROUP BY lsi.element_id
        )
        -- Step 3: Join everything together
        SELECT
            e.element_id,
            e.geometry_json,
            e.element_type,
            e.zone_name,
            e.floor_level,
            e.plan_file,
            e.area_sqm,
            e.width_cm,
            e.height_cm, 
            e.contractor, 
            e.block,
            COALESCE(s.inspection_count, 0) as inspection_count,
            s.latest_history_log,
            s.latest_pre_inspection_log,
            s.latest_notes,
            s.latest_inspection_date,
            -- Determine the final status string based on the priority
            CASE s.highest_priority_status
                WHEN 1 THEN 'Reject'
                WHEN 2 THEN 'Repair'
                WHEN 3 THEN 'OK'
                WHEN 4 THEN 'Awaiting Re-inspection'
                WHEN 5 THEN 'Pre-Inspection Complete'
                WHEN 6 THEN 'Pending'
                ELSE 'Pending' -- Default for elements with no inspections
            END as final_status
        FROM
            elements e
        LEFT JOIN OverallElementStatus s ON e.element_id = s.element_id
        WHERE
            e.plan_file = ?
            AND e.geometry_json IS NOT NULL AND JSON_VALID(e.geometry_json)");

     $stmt->execute([$plan_file]);
    $elements_data_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process data and count statuses for the legend
    $elements_data = array_map(function ($el) use (&$status_counts) {
        $status = $el['final_status'] ?? 'Pending';
        if (array_key_exists($status, $status_counts)) {
            $status_counts[$status]++;
        }
        return $el;
    }, $elements_data_raw);

    if (!empty($elements_data_raw)) {
        // Get context from the first element (they should all be the same for one plan)
        $first_element = $elements_data_raw[0];
        $zone_name = $first_element['zone_name'] ?? basename($plan_file);
        $contractor_name = $first_element['contractor'] ?? 'نامشخص';
        $block_name = $first_element['block'] ?? 'نامشخص';
    } else {
         $zone_name = basename($plan_file);
    }
    $element_styles_config = [
        'GFRC' => '#FB0200',
        'GLASS' => '#2986cc',
        'Mullion' => 'rgba(128, 128, 128, 0.9)',
        'Transom' => 'rgba(169, 169, 169, 0.9)',
        'Bazshow' => 'rgba(169, 169, 169, 0.9)',
        'Zirsazi' => '#2464ee',
        'STONE' => '#4c28a1'
    ];
    $inactive_fill = '#d3d3d3';

    $style_block = "\n";

    // Read and validate SVG file
    $svg_path = realpath(__DIR__ . '') . '/' . $plan_file;
    if (!file_exists($svg_path)) {
        http_response_code(404);
        die("Error: SVG file not found.");
    }

    $svg_content = file_get_contents($svg_path);
  if ($svg_content === false) {
        http_response_code(500);
        die("Error: Could not read SVG file.");
    }

    // Validate that we have actual SVG content
    if (empty($svg_content) || strpos($svg_content, '<svg') === false) {
        http_response_code(500);
        die("Error: Invalid SVG file content.");
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Viewer: <?php echo escapeHtml($plan_file); ?></title>
    <style>
        @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2");
        }

        body,
        html {
            margin: 0;
            padding: 0;
            font-family: 'Samim', sans-serif;
            height: 100vh;
            overflow: hidden;
            background-color: #f0f0f0;
        }

        .viewer-wrapper {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        #viewer-toolbar {
            padding: 8px;
            background: #fff;
            border-bottom: 1px solid #ccc;
            text-align: left;
            flex-shrink: 0;
            direction: ltr;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap-reverse;
            gap: 10px;
        }

        #viewer-toolbar .controls {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-grow: 1;
        }

        #viewer-toolbar button {
            padding: 8px 14px;
            margin: 0 4px;
            cursor: pointer;
            border-radius: 4px;
            border: 1px solid #ccc;
            background-color: #f8f9fa;
            font-size: 1rem;
        }

        #legend {
            padding: 5px 10px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 15px;
            flex-grow: 1;
        }

        #legend span {
            display: inline-flex;
            align-items: center;
            margin: 0;
            font-size: 13px;
        }

        .legend-color {
            display: inline-block;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            border: 1px solid #555;
            vertical-align: middle;
            margin-left: 6px;
        }

        .legend-count {
            font-weight: bold;
            margin-right: 4px;
            background-color: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }

        #svg-container-wrapper {
            flex-grow: 1;
            overflow: hidden;
            background: #e9ecef;
            cursor: grab;
            position: relative;
            touch-action: none;
            /* Prevents default touch actions like scrolling */
        }

        #svg-container-wrapper.grabbing {
            cursor: grabbing;
        }

        #svg-container {
            width: 100%;
            height: 100%;
            position: relative;
        }

        #svg-container svg {
            width: 100%;
            height: 100%;
            transform-origin: 0 0;
            position: absolute;
            top: 0;
            left: 0;
        }

        .highlight-marker {
            stroke: #000;
            stroke-width: 1px;
            transition: transform 0.15s ease, stroke-width 0.15s ease;
            cursor: pointer;
            transform-origin: center;
        }

        .highlight-marker text {
            display: none !important;
        }

        .marker-hover {
            stroke-width: 2px;
            transform: scale(1.2);
        }

        .svg-tooltip {
            position: fixed;
            background-color: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            pointer-events: none;
            z-index: 10000;
            max-width: 350px;
            line-height: 1.6;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.15s ease-in-out;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            white-space: nowrap;
        }

        .tooltip-visible {
            opacity: 1;
            visibility: visible;
        }

        .svg-tooltip strong {
            color: #ffc107;
        }

        #loading,
        #filter-no-results-message {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            font-size: 18px;
            z-index: 1000;
        }

        #loading {
            top: 50%;
            transform: translate(-50%, -50%);
        }

        #filter-no-results-message {
            top: 20px;
            background-color: rgba(255, 193, 7, 0.9);
            color: #333;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            display: none;
        }

        @media print {

            /* --- 1. Hide only the interactive/unnecessary elements --- */
            #viewer-toolbar .controls,
            /* Hide zoom, print, download buttons */
            #viewer-toolbar #legend,
            /* Hide the interactive color legend */
            #stage-filter-panel,
            /* Hide the workflow filter panel */
            .svg-tooltip {
                /* Hide the hover tooltip */
                display: none !important;
            }

            /* --- 2. Ensure the main toolbar and the new header ARE visible --- */
            #viewer-toolbar {
                display: flex !important;
                justify-content: flex-end !important;
                /* Aligns header to the right */
                border-bottom: 2px solid #000 !important;
                /* Add a solid line for print */
                padding: 10px 0 !important;
                background-color: #fff !important;
            }

            #viewer-header-info {
                display: flex !important;
                /* Make sure the header itself is visible */
                width: 100%;
                justify-content: center;
                /* Center the header info on the printed page */
                font-size: 14pt !important;
            }

            #viewer-header-info strong {
                color: #000 !important;
                /* Use black for better print contrast */
            }

            /* --- 3. Keep all the other essential print styles --- */
            body {
                background-color: #fff !important;
            }

            .viewer-wrapper {
                height: 100vh;
                display: flex;
                flex-direction: column;
            }

            #svg-container-wrapper {
                flex-grow: 1;
                height: 70vh;
                overflow: visible;
                border: 1px solid #ccc !important;
            }

            #status-chart-container {
                flex-shrink: 0;
                margin-top: 20px;
                page-break-before: auto;
            }

            #svg-container svg {
                /* Reset the view to default for a clean print */
                transform: translate(0, 0) scale(1) !important;
                width: 100%;
                height: 100%;
            }

            .chart-bar,
            .detail-color,
            #svg-container svg path,
            #svg-container svg rect {
                /* Force colors to print */
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        #status-chart-container {
            width: 90%;
            max-width: 800px;
            /* A good max-width for the chart */
            margin: 20px auto;
            padding: 15px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .chart-title {
            text-align: center;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
            font-size: 1.1em;
        }

        .chart-bar-wrapper {
            display: flex;
            width: 100%;
            height: 35px;
            /* A slightly taller bar */
            border-radius: 6px;
            overflow: hidden;
            /* This makes the corners of the child bars appear rounded */
            border: 1px solid #e0e0e0;
        }

        .chart-bar {
            height: 100%;
            transition: width 0.6s ease-in-out;
            /* A smoother transition */
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: bold;
            white-space: nowrap;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.4);
        }

        .chart-details-wrapper {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            /* Distributes items evenly */
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        .chart-detail {
            display: flex;
            align-items: center;
            margin: 5px 10px;
            font-size: 14px;
        }

        .detail-color {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            /* Changed to a square for differentiation */
            margin-left: 8px;
            flex-shrink: 0;
            /* Prevents the color box from shrinking */
        }

        .detail-label {
            color: #555;
            margin-left: 4px;
        }

        .detail-value {
            color: #000;
            font-weight: bold;
            direction: ltr;
            /* Ensures numbers and parens are displayed correctly */
            text-align: left;
        }

        /* Button Styles */
        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            color: white;
            font-family: inherit;
            font-size: 0.9em;
            transition: background-color 0.2s;
        }

        .btn-load {
            background-color: #007bff;
        }

        .btn-load:hover {
            background-color: #0069d9;
        }

        .btn-save {
            background-color: #28a745;
        }

        .btn-new {
            background-color: #6c757d;
        }

        .btn-back {
            background-color: #5a6268;
            text-decoration: none;
            display: inline-block;

        }

        path[data-status],
        rect[data-status] {
            transition: fill 0.3s ease-in-out;
        }

        #stage-filter-panel {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
            width: 300px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
            border: 1px solid #ccc;
            display: flex;
            flex-direction: column;
        }

        .panel-header {
            padding: 12px 15px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            border-radius: 8px 8px 0 0;
        }

        .panel-header h4 {
            margin: 0;
            color: #333;
        }

        .panel-body {
            padding: 15px;
        }

        .panel-body .form-group {
            margin-bottom: 15px;
        }

        .panel-body label {
            font-weight: bold;
            font-size: 13px;
            display: block;
            margin-bottom: 5px;
        }

        .panel-body select {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        .panel-footer {
            padding: 10px 15px;
            background-color: #f8f9fa;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
        }

        .panel-footer button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* --- NEW INSPECTION COUNT INDICATOR --- */
        .inspection-count-marker {
            fill: rgba(255, 255, 255, 0.9);
            stroke: #333;
            stroke-width: 0.5px;
        }

        .inspection-count-text {
            fill: #000;
            font-size: 4px;
            /* Will be scaled with the view */
            font-weight: bold;
            text-anchor: middle;
            dominant-baseline: middle;
            pointer-events: none;
            /* Text should not block clicks */
        }

        #totals-display {
            display: flex;
            gap: 20px;
            /* Space between the two total items */
            background-color: #e9ecef;
            padding: 8px 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            margin: 0 auto;
            /* Helps center it within the flex toolbar */
        }

        .total-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .total-label {
            font-size: 13px;
            color: #495057;
            font-weight: bold;
        }

        .total-value {
            font-size: 15px;
            font-weight: bold;
            color: #0056b3;
            /* A distinct blue color */
            background-color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            min-width: 40px;
            text-align: center;
        }

        .panel-summary {
            padding: 10px 15px;
            background-color: #f0f8ff;
            /* A light blue to stand out */
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            padding: 4px 0;
        }

        .summary-item strong {
            font-size: 14px;
            color: #0056b3;
        }

        #viewer-header-info {
            display: flex;
            gap: 25px;
            /* Adds space between items */
            padding: 5px 15px;
            font-size: 14px;
            color: #333;
            /* Pushes it to the far right in the LTR toolbar container */
            margin-right: auto;
            direction: rtl;
            /* Ensures text inside the header is right-to-left */
            flex-wrap: wrap;
            /* Allow wrapping on smaller screens */
        }

        #viewer-header-info span {
            white-space: nowrap;
            /* Prevents text from wrapping unnecessarily */
        }

        #viewer-header-info strong {
            color: #0056b3;
            /* Highlight the dynamic data */
            font-weight: bold;
        }
        details {
display: block;
min-width: 110px;
width: fit-content;
max-width: 100%;
}

details summary {
  background-color: #007bff; /* blue like a button */
  color: white;
  padding: 6px 12px;
  border-radius: 5px;
  cursor: pointer;
  font-weight: bold;
  user-select: none;
  list-style: none; 
  width: fit-content; 

}

details summary::-webkit-details-marker {
  display: none; /* remove default triangle in Chrome/Safari */
}

details[open] summary {
  background-color: #0056b3; /* darker when open */
}
 @keyframes pulse-highlight {
      0% {
        stroke: #161615ff;
        stroke-width: 24px;
      }
      50% {
        stroke: #e1de17ff;
        stroke-width: 40px;
      }
      100% {
        stroke: #0e0eecff;
        stroke-width: 24px;
      }
    }

    .highlighted-element {
      animation: pulse-highlight 1.5s infinite;
    }
    </style>
</head>

<body>
    <div class="viewer-wrapper">
        <div id="viewer-toolbar">
            <div id="viewer-header-info">
                <span>نقشه فعلی: <strong><?php echo escapeHtml($zone_name); ?></strong></span>
                <span>پیمانکار: <strong><?php echo escapeHtml($contractor_name); ?></strong></span>
                <span>بلوک: <strong><?php echo escapeHtml($block_name); ?></strong></span>
            </div>
            <div id="legend">
                <span><span class="legend-color" style="background-color: #28a745;"></span>تایید شده<span class="legend-count" id="count-ok">0</span></span>
                <span><span class="legend-color" style="background-color: #dc3545;"></span>رد شده<span class="legend-count" id="count-reject">0</span></span>
                <span><span class="legend-color" style="background-color: #9c27b0;"></span>نیاز به تعمیر<span class="legend-count" id="count-repair">0</span></span>
                <span><span class="legend-color" style="background-color: #ff8c00;"></span>آماده بازرسی<span class="legend-count" id="count-pre-inspection">0</span></span>
                <span><span class="legend-color" style="background-color: #00bfff;"></span>منتظر بازرسی مجدد<span class="legend-count" id="count-awaiting-reinspection">0</span></span>
                <span><span class="legend-color" style="background-color: #cccccc;"></span>در انتظار<span class="legend-count" id="count-pending">0</span></span>
            </div>
            <div id="totals-display">
                <div class="total-item">
                    <span class="total-label">تعداد کل:</span>
                    <span id="total-count" class="total-value">0</span>
                </div>
                <div class="total-item">
                    <span class="total-label">مساحت کل (m²):</span>
                    <span id="total-area" class="total-value">0.00</span>
                </div>
            </div>

        </div>
<details>
  <summary style="cursor:pointer; font-weight:bold;">فیلتر مراحل</summary>
  
  <div id="stage-filter-panel">
    <div class="panel-header">
      <h4>نمایش وضعیت مراحل</h4>
    </div>
    <div class="panel-body">
      <div class="form-group">
        <label for="type-select">۱. نوع المان را انتخاب کنید:</label>
        <select id="type-select">
          <option value="">-- همه انواع --</option>
          <!-- Options will be added by JS -->
        </select>
      </div>
      <div id="type-summary-panel" class="panel-summary" style="display: none;">
        <div class="summary-item">
          <span>تعداد کل این نوع:</span>
          <strong id="type-total-count">0</strong>
        </div>
        <div class="summary-item">
          <span>مساحت کل این نوع (m²):</span>
          <strong id="type-total-area">0.00</strong>
        </div>
      </div>
      <div class="form-group">
        <label for="stage-select">۲. وضعیت این مرحله را نمایش بده:</label>
        <select id="stage-select" disabled>
          <option value="">-- ابتدا نوع المان را انتخاب کنید --</option>
        </select>
      </div>
    </div>
    <div class="panel-footer">
      <button id="apply-stage-filter-btn" class="btn btn-load" disabled>اعمال فیلتر</button>
      <button id="reset-view-btn" class="btn btn-back">نمایش وضعیت کلی</button>
    </div>
  </div>
</details>

        <div id="svg-container-wrapper">
            <div id="loading">در حال بارگذاری...</div>
            <div id="svg-container"></div>
            <div id="filter-no-results-message"></div>
            <div class="svg-tooltip"></div>
        </div>
        <div id="status-chart-container">
            <div class="chart-title">خلاصه وضعیت المان‌ها</div>

            <div class="chart-bar-wrapper">
                <div class="chart-bar" id="chart-bar-ok" style="background-color: #28a745;"></div>
                <div class="chart-bar" id="chart-bar-reject" style="background-color: #dc3545;"></div>
                <div class="chart-bar" id="chart-bar-repair" style="background-color: #9c27b0;"></div>
                <div class="chart-bar" id="chart-bar-pre-inspection" style="background-color: #ff8c00;"></div>
                <div class="chart-bar" id="chart-bar-awaiting-reinspection" style="background-color: #00bfff;"></div>
                <div class="chart-bar" id="chart-bar-pending" style="background-color: #cccccc;"></div>
            </div>

            <div class="chart-details-wrapper">
                <div class="chart-detail" id="detail-ok">
                    <span class="detail-color" style="background-color: #28a745;"></span>
                    <span class="detail-label">تایید شده:</span>
                    <span class="detail-value">0 (0%)</span>
                </div>
                <div class="chart-detail" id="detail-reject">
                    <span class="detail-color" style="background-color: #dc3545;"></span>
                    <span class="detail-label">رد شده:</span>
                    <span class="detail-value">0 (0%)</span>
                </div>
                <div class="chart-detail" id="detail-repair">
                    <span class="detail-color" style="background-color: #9c27b0;"></span>
                    <span class="detail-label">نیاز به تعمیر:</span>
                    <span class="detail-value">0 (0%)</span>
                </div>
                <div class="chart-detail" id="detail-pre-inspection">
                    <span class="detail-color" style="background-color: #ff8c00;"></span>
                    <span class="detail-label">آماده بازرسی:</span>
                    <span class="detail-value">0 (0%)</span>
                </div>
                <div class="chart-detail" id="detail-awaiting-reinspection">
                    <span class="detail-color" style="background-color: #00bfff;"></span>
                    <span class="detail-label">منتظر بازرسی مجدد:</span>
                    <span class="detail-value">0 (0%)</span>
                </div>
                <div class="chart-detail" id="detail-pending">
                    <span class="detail-color" style="background-color: #cccccc;"></span>
                    <span class="detail-label">در انتظار:</span>
                    <span class="detail-value">0 (0%)</span>
                </div>
            </div>
        </div>

        <script>
            const ALL_ELEMENTS_DATA = <?php echo json_encode($elements_data ?? []); ?>;
            const STATUS_COUNTS = <?php echo json_encode($status_counts ?? []); ?>;
            const SVG_CONTENT = <?php echo json_encode($svg_content ?? ''); ?>;
            const HIDE_LAYER_BY_DEFAULT = <?php echo json_encode($hide_layer); ?>;
            const HIGHLIGHT_STATUS = <?php echo json_encode($highlight_status ?? 'all'); ?>;
            const HIGHLIGHT_ELEMENT_ID = <?php echo json_encode($highlight_element_id); ?>;
            const GRADIENT_COLORS = <?php echo json_encode($gradient_colors_str ? explode(',', $gradient_colors_str) : null); ?>;

            const STATUS_COLORS = {
                'OK': '#28a745',
                'Reject': '#dc3545',
                'Repair': '#9c27b0',
                'Pre-Inspection Complete': '#ff8c00',
                'Awaiting Re-inspection': '#00bfff',
                'Pending': '#cccccc'
            };


            const STATUS_PERSIAN = {
    "OK": "تایید شده",
    "Reject": "رد شده",
    "Repair": "نیاز به تعمیر",
    "Pre-Inspection Complete": "آماده بازرسی",
    "Awaiting Re-inspection": "منتظر بازرسی مجدد",
    "Pending": "در انتظار",
    // Add pre-inspection statuses for tooltips if needed
    "Request to Open": "درخواست بازگشایی",
    "Opening Approved": "تایید شده برای بازگشایی",
    "Opening Rejected": "درخواست بازگشایی رد شد",
    "Panel Opened": "پانل بازگشایی شده",
    "Opening Disputed": "بازگشایی پانل رد شد"
};
function applyGradientFill(elementId, colors) {
    if (!svgElement || !colors || colors.length < 2) {
        return; // Need at least two colors for a gradient
    }

    let defs = svgElement.querySelector('defs');
    if (!defs) {
        defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        svgElement.prepend(defs);
    }

    const gradientId = `grad-${elementId}`;
    const gradient = document.createElementNS('http://www.w3.org/2000/svg', 'linearGradient');
    gradient.setAttribute('id', gradientId);
    gradient.setAttribute('x1', '0%');
    gradient.setAttribute('y1', '0%');
    gradient.setAttribute('x2', '100%');
    gradient.setAttribute('y2', '0%');

    const stopCount = colors.length;
    colors.forEach((color, i) => {
        const stop = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
        const offset = (i / (stopCount - 1)) * 100;
        stop.setAttribute('offset', `${offset}%`);
        stop.setAttribute('stop-color', `#${color}`);
        gradient.appendChild(stop);
    });

    defs.appendChild(gradient);

    const elementToFill = svgElement.getElementById(elementId);
    if (elementToFill) {
        elementToFill.style.fill = `url(#${gradientId})`;
    }
}
            function parseHistoryLog(historyLogJson) {
                if (!historyLogJson) return null;

                try {
                    const historyData = JSON.parse(historyLogJson);
                    if (!Array.isArray(historyData) || historyData.length === 0) return null;

                    // Get the latest entry
                    const latestEntry = historyData[historyData.length - 1];

                    let cracksInfo = '';
                    let checklistInfo = '';

                    if (latestEntry.data && latestEntry.data.checklist_items) {
                        const checklistItems = latestEntry.data.checklist_items;

                        // Count cracks (items with line coordinates)
                        const cracksCount = checklistItems.filter(item => {
                            try {
                                const value = JSON.parse(item.value);
                                return value.lines && Array.isArray(value.lines) && value.lines.length > 0;
                            } catch (e) {
                                return false;
                            }
                        }).length;

                        if (cracksCount > 0) {
                            cracksInfo = `<br><strong>تعداد ترک‌ها:</strong> ${cracksCount}`;
                        }

                        // Count checklist statuses
                        const okCount = checklistItems.filter(item => item.status === 'OK').length;
                        const notOkCount = checklistItems.filter(item => item.status === 'Not OK').length;

                        if (checklistItems.length > 0) {
                            checklistInfo = `<br><strong>چک‌لیست:</strong> ${okCount} تایید، ${notOkCount} رد`;
                        }
                    }

                    const notes = latestEntry.data && latestEntry.data.notes ?
                        `<br><strong>یادداشت:</strong> ${latestEntry.data.notes}` : '';

                    const inspectionDate = latestEntry.data && latestEntry.data.inspection_date ?
                       `<br><strong>تاریخ بازرسی:</strong> ${toPersianDate(latestEntry.data.inspection_date)}` : '';

                    return cracksInfo + checklistInfo + notes + inspectionDate;

                } catch (e) {
                    console.error('Error parsing history log:', e);
                    return null;
                }
            }

            // Helper function to parse and format pre-inspection log data
            function parsePreInspectionLog(preInspectionLogJson) {
                if (!preInspectionLogJson) return null;

                try {
                    const preInspectionData = JSON.parse(preInspectionLogJson);
                    if (!Array.isArray(preInspectionData) || preInspectionData.length === 0) return null;

                    const actionPersian = {
                        'Request to Open': 'درخواست باز کردن',
                        'Opening Approved': 'تایید باز کردن',
                        'Opening Rejected': 'رد باز کردن',
                        'Panel Opened': 'پنل باز شده',
                        'Opening Disputed': 'تایید باز شدن',
                        'Pre-Inspection Complete': 'پیش‌بازرسی کامل شده',
                        'verify-opening':'پیش‌بازرسی کامل شده',
                        'confirm-opened': 'پنل باز شده',
                        'approve-opening': 'تایید باز کردن',
                        'request-opening': 'درخواست باز کردن',
                    };

                    const latestAction = preInspectionData[preInspectionData.length - 1];
                    const actionText = actionPersian[latestAction.action] || latestAction.action;

                    return `<br><strong>آخرین مرحله پیش‌بازرسی:</strong> ${actionText}`;

                } catch (e) {
                    console.error('Error parsing pre-inspection log:', e);
                    return null;
                }
            }

            let scale = 1,
                panX = 0,
                panY = 0,
                svgElement = null,
                tooltip = null,
                tooltipTimeout = null;

            document.addEventListener('DOMContentLoaded', () => {
                const container = document.getElementById('svg-container');
                const loadingEl = document.getElementById('loading');

                // Validate SVG content before proceeding
                if (!SVG_CONTENT || SVG_CONTENT.trim() === '') {
                    loadingEl.textContent = 'خطا: محتوای SVG خالی است';
                    loadingEl.style.color = 'red';
                    return;
                }

                if (SVG_CONTENT.indexOf('<svg') === -1) {
                    loadingEl.textContent = 'خطا: فایل SVG معتبر نیست';
                    loadingEl.style.color = 'red';
                    return;
                }

                try {
                    container.innerHTML = SVG_CONTENT;
                    svgElement = container.querySelector('svg');

                    if (!svgElement) {
                        loadingEl.textContent = 'خطا: فایل SVG بارگیری نشد';
                        loadingEl.style.color = 'red';
                        return;
                    }

                    loadingEl.style.display = 'none';
                    tooltip = document.querySelector('.svg-tooltip');

                    requestAnimationFrame(() => {
                        initializeViewer(document.getElementById('svg-container-wrapper'));
                    });

                } catch (error) {
                    console.error('Error loading SVG:', error);
                    loadingEl.textContent = 'خطا در بارگذاری SVG: ' + error.message;
                    loadingEl.style.color = 'red';
                }
            });

            function initializeViewer(wrapper) {
                updateLegendCounts(STATUS_COUNTS);
                updateStatusChart(STATUS_COUNTS);
                setupPanAndZoom(wrapper);

                setupGlobalTooltipHandling();
                setupStageFilterPanel();

                // Initial render with overall status by default
                renderElementColors(ALL_ELEMENTS_DATA, HIGHLIGHT_STATUS);
                setupLayerControls();
                if (GRADIENT_COLORS && HIGHLIGHT_ELEMENT_ID) {
        applyGradientFill(HIGHLIGHT_ELEMENT_ID, GRADIENT_COLORS);
    }
            }
function setupLayerControls() {
        const toolbar = document.getElementById('viewer-toolbar');
         if (!toolbar) return; 
        const controlsContainer = document.createElement('div');
        controlsContainer.className = 'controls';
        
        // Define which layers should have a toggle button
        const layerIds = ['Curtainwall', 'GLASS', 'GFRC', 'Bazshow'];
        let layerVisibility = {}; // Store visibility state

        layerIds.forEach(layerId => {
        const groupElement = svgElement.getElementById(layerId);
        if (groupElement) {
            const button = document.createElement('button');
            button.textContent = layerId;
            button.dataset.layerId = layerId;

            // Set initial state
            let isVisible = true; // Default to visible
            // THE NEW LOGIC IS HERE: If this layer is the one to be hidden, set it to invisible
            if (HIDE_LAYER_BY_DEFAULT && HIDE_LAYER_BY_DEFAULT === layerId) {
                isVisible = false;
            }

            button.classList.toggle('active', isVisible);
            groupElement.style.display = isVisible ? '' : 'none';
            layerVisibility[layerId] = isVisible;

            controlsContainer.appendChild(button);
        }
    });

        // Add a single smart event listener
        controlsContainer.addEventListener('click', (e) => {
            const button = e.target.closest('button[data-layer-id]');
            if (!button) return;

            const layerId = button.dataset.layerId;
            const layerElement = svgElement.getElementById(layerId);
            
            // Toggle visibility
            layerVisibility[layerId] = !layerVisibility[layerId];
            layerElement.style.display = layerVisibility[layerId] ? '' : 'none';
            button.classList.toggle('active', layerVisibility[layerId]);

            // --- Special Rule for Curtainwall and Glass ---
            if (layerId === 'Curtainwall' || layerId === 'GLASS') {
                const curtainwallLayer = svgElement.getElementById('Curtainwall');
                const glassLayer = svgElement.getElementById('GLASS');
                const glassButton = controlsContainer.querySelector('button[data-layer-id="GLASS"]');

                if (curtainwallLayer && glassLayer && glassButton) {
                    const isCurtainwallVisible = layerVisibility['Curtainwall'];

                    if (isCurtainwallVisible) {
                        // If Curtainwall is ON, force Glass to be OFF
                        glassLayer.style.display = 'none';
                        glassButton.classList.remove('active');
                        glassButton.disabled = true;
                    } else {
                        // If Curtainwall is OFF, re-enable the Glass button and restore its state
                        glassButton.disabled = false;
                        glassLayer.style.display = layerVisibility['GLASS'] ? '' : 'none';
                        glassButton.classList.toggle('active', layerVisibility['GLASS']);
                    }
                }
            }
        });

        toolbar.prepend(controlsContainer);
         if (HIDE_LAYER_BY_DEFAULT === 'Curtainwall') {
         const glassButton = controlsContainer.querySelector('button[data-layer-id="GLASS"]');
         if (glassButton) glassButton.disabled = false; // Ensure glass is enabled
    }
    }
            /**
             * The main rendering function. Finds SVG elements, colorizes them,
             * and calculates total count and area of visible elements.
             * @param {Array} elementsToRender - The data for elements to be colored.
             * @param {string} [filterStatus='all'] - Optional status to filter by.
             */
            // Replace the existing renderElementColors function with this updated version

  function renderElementColors(elementsToRender, filterStatus = 'all') {
        console.log(`Rendering colors. Filtering by status: ${filterStatus}`);
        const msgDiv = document.getElementById('filter-no-results-message');
        msgDiv.style.display = 'none';

        let visibleElementsCount = 0;
        let visibleElementsArea = 0.0;
        let activeTooltipElement = null;
        const elementDataMap = new Map(elementsToRender.map(el => [el.element_id, el]));
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

        // Reset all elements first
        svgElement.querySelectorAll('path[id], rect[id]').forEach(el => {
            el.style.fill = 'rgba(108, 117, 125, 0.2)'; // Default inactive color
            el.style.display = 'none';
            el.style.cursor = 'default';
            el.classList.remove('highlighted-element'); // Remove highlight class
            
            // Clone to remove old event listeners
            const newEl = el.cloneNode(true);
            el.parentNode.replaceChild(newEl, el);
        });
        
        if (isTouchDevice) {
            document.addEventListener('touchstart', (e) => {
                if (activeTooltipElement && !activeTooltipElement.contains(e.target)) {
                    hideTooltip();
                    if (activeTooltipElement) {
                        activeTooltipElement.style.stroke = '';
                        activeTooltipElement.style.strokeWidth = '';
                        activeTooltipElement = null;
                    }
                }
            }, { passive: true });
        }

        svgElement.querySelectorAll('path[id], rect[id]').forEach(el => {
            if (elementDataMap.has(el.id)) {
                const element = elementDataMap.get(el.id);
                const status = element.final_status || 'Pending';

                if (filterStatus === 'all' || status === filterStatus) {
                    visibleElementsCount++;
                    visibleElementsArea += parseFloat(element.area_sqm || 0);

                    el.style.display = '';
                    el.style.fill = STATUS_COLORS[status] || STATUS_COLORS['Pending'];
                    el.style.cursor = 'pointer';

                    // ===============================================
                    // START: NEW HIGHLIGHTING LOGIC
                    // ===============================================
                    if (HIGHLIGHT_ELEMENT_ID && el.id === HIGHLIGHT_ELEMENT_ID) {
                        el.classList.add('highlighted-element');
                    }
                    // ===============================================
                    // END: NEW HIGHLIGHTING LOGIC
                    // ===============================================


                    // Function to show tooltip
                    function showElementTooltip() {
                        let content = `<strong>شناسه:</strong> ${element.element_id}<br>` +
                            `<strong>نوع:</strong> ${element.element_type}<br>` +
                            `<strong>ابعاد (cm):</strong> ${element.width_cm || 'N/A'} x ${element.height_cm || 'N/A'}<br>` +
                            `<strong>مساحت:</strong> ${element.area_sqm || 'N/A'} m²<br>` +
                            `<strong>وضعیت کلی:</strong> ${STATUS_PERSIAN[status] || status}`;

                        const historyInfo = parseHistoryLog(element.latest_history_log);
                        if (historyInfo) content += historyInfo;

                        const preInspectionInfo = parsePreInspectionLog(element.latest_pre_inspection_log);
                        if (preInspectionInfo) content += preInspectionInfo;

                        if (element.latest_notes) content += `<br><strong>یادداشت‌ها:</strong> ${element.latest_notes}`;

                        if (element.latest_inspection_date) {
                            const persianDate = toPersianDate(element.latest_inspection_date);
                            content += `<br><strong>تاریخ بازرسی:</strong> ${persianDate || element.latest_inspection_date}`;
                        }
                        
                        showTooltip(content);
                        if (!el.classList.contains('highlighted-element')) {
                            el.style.stroke = '#000';
                            el.style.strokeWidth = '2px';
                        }
                    }

                    // Function to hide tooltip
                    function hideElementTooltip() {
                        hideTooltip();
                        if (!el.classList.contains('highlighted-element')) {
                            el.style.stroke = '';
                            el.style.strokeWidth = '';
                        }
                        if (activeTooltipElement === el) {
                            activeTooltipElement = null;
                        }
                    }

                    if (isTouchDevice) {
                        // Mobile/Touch Events
                        el.addEventListener('touchstart', (e) => {
                            e.preventDefault();
                            if (activeTooltipElement === el) {
                                hideElementTooltip();
                                return;
                            }
                            if (activeTooltipElement) {
                                activeTooltipElement.style.stroke = '';
                                activeTooltipElement.style.strokeWidth = '';
                            }
                            activeTooltipElement = el;
                            showElementTooltip();
                        }, { passive: false });

                        el.addEventListener('touchend', (e) => e.preventDefault(), { passive: false });

                    } else {
                        // Desktop/Mouse Events
                        el.addEventListener('mouseenter', showElementTooltip);
                        el.addEventListener('mouseleave', hideElementTooltip);
                    }

                    el.addEventListener('click', (e) => {
                        e.stopPropagation();
                        console.log(`Element clicked: ${element.element_id}`);
                    });
                }
            }
        });

        document.getElementById('total-count').textContent = visibleElementsCount;
        document.getElementById('total-area').textContent = visibleElementsArea.toFixed(2);

        if (visibleElementsCount === 0 && filterStatus !== 'all') {
            const statusPersian = STATUS_PERSIAN[filterStatus] || filterStatus;
            msgDiv.textContent = `هیچ موردی با وضعیت "${statusPersian}" یافت نشد.`;
            msgDiv.style.display = 'block';
        }
    }
            // Add Persian date conversion function if not already present
            function toPersianDate(gregorianDate) {
                if (!gregorianDate) return null;

                // Simple date conversion - you may want to use your existing jdf.php function here
                const date = new Date(gregorianDate);
                if (isNaN(date.getTime())) return gregorianDate;

                // For now, return the formatted Gregorian date
                // You can enhance this with proper Persian calendar conversion
                return date.toLocaleDateString('fa-IR');
            }
            /**
             * The main rendering function. Clears old markers and draws new ones.
             * @param {Array} elementsToRender - The array of element data to display.
             */
            function renderMarkers(elementsToRender) {
                // Clear any existing markers
                svgElement.querySelectorAll('.status-marker-group').forEach(g => g.remove());

                if (!elementsToRender) return;

                const fragment = document.createDocumentFragment();

                elementsToRender.forEach(element => {
                    if (!element.geometry_json) return;

                    let geometry;
                    try {
                        geometry = JSON.parse(element.geometry_json);
                    } catch (e) {
                        console.error(`Invalid JSON for element ${element.element_id}:`, element.geometry_json);
                        return;
                    }

                    if (!Array.isArray(geometry) || geometry.length === 0) return;

                    // Calculate the center point for the marker
                    const totalPoints = geometry.length;
                    const sum = geometry.reduce((acc, point) => [acc[0] + point[0], acc[1] + point[1]], [0, 0]);
                    const centerX = sum[0] / totalPoints;
                    const centerY = sum[1] / totalPoints;

                    const status = element.final_status || 'Pending';
                    const color = STATUS_COLORS[status] || STATUS_COLORS['Pending'];
                    const inspectionCount = element.inspection_count || 0;

                    const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                    group.classList.add('status-marker-group');

                    // Main status circle
                    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    circle.setAttribute('cx', centerX);
                    circle.setAttribute('cy', centerY);
                    circle.setAttribute('r', 5); // Radius can be adjusted
                    circle.setAttribute('fill', color);
                    circle.setAttribute('stroke', '#333');
                    circle.setAttribute('stroke-width', 0.5);
                    circle.style.cursor = 'pointer';
                    group.appendChild(circle);

                    // Inspection count indicator
                    if (inspectionCount > 0) {
                        const countCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                        countCircle.setAttribute('cx', centerX + 4);
                        countCircle.setAttribute('cy', centerY - 4);
                        countCircle.setAttribute('r', 2.5);
                        countCircle.classList.add('inspection-count-marker');
                        group.appendChild(countCircle);

                        const countText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                        countText.setAttribute('x', centerX + 4);
                        countText.setAttribute('y', centerY - 4);
                        countText.classList.add('inspection-count-text');
                        countText.textContent = inspectionCount;
                        group.appendChild(countText);
                    }

                    // --- Tooltip Event Listeners ---
                    group.addEventListener('mouseenter', () => {
                        let content = `<strong>شناسه:</strong> ${element.element_id}<br>` +
                            `<strong>وضعیت:</strong> ${status}<br>` +
                            `<strong>تعداد بازرسی:</strong> ${inspectionCount}`;
                        showTooltip(content);
                        circle.style.strokeWidth = '1.5px';
                    });
                    group.addEventListener('mouseleave', () => {
                        hideTooltip();
                        circle.style.strokeWidth = '0.5px';
                    });

                    fragment.appendChild(group);
                });

                svgElement.appendChild(fragment);
            }

            function setupStageFilterPanel() {
                const typeSelect = document.getElementById('type-select');
                const stageSelect = document.getElementById('stage-select');
                const applyBtn = document.getElementById('apply-stage-filter-btn');
                const resetBtn = document.getElementById('reset-view-btn');

                const summaryPanel = document.getElementById('type-summary-panel');
                const typeCountEl = document.getElementById('type-total-count');
                const typeAreaEl = document.getElementById('type-total-area');

                // Populate the element type dropdown
                const types = [...new Set(ALL_ELEMENTS_DATA.map(el => el.element_type))];
                types.sort().forEach(type => {
                    const option = document.createElement('option');
                    option.value = type;
                    option.textContent = type;
                    typeSelect.appendChild(option);
                });

                // Event when a type is selected
                typeSelect.addEventListener('change', async () => {
                    const selectedType = typeSelect.value;
                    stageSelect.innerHTML = '<option value="">در حال بارگذاری...</option>';
                    stageSelect.disabled = true;
                    applyBtn.disabled = true;

                    if (selectedType) {
                        const elementsOfType = ALL_ELEMENTS_DATA.filter(el => el.element_type === selectedType);
                        const totalCount = elementsOfType.length;
                        const totalArea = elementsOfType.reduce((sum, el) => sum + parseFloat(el.area_sqm || 0), 0);

                        typeCountEl.textContent = totalCount;
                        typeAreaEl.textContent = totalArea.toFixed(2);
                        summaryPanel.style.display = 'block';
                    } else {
                        summaryPanel.style.display = 'none';
                    }

                    if (!selectedType) {
                        stageSelect.innerHTML = '<option value="">-- ابتدا نوع المان را انتخاب کنید --</option>';
                        return;
                    }

                    try {
                        const response = await fetch(`/ghom/api/get_stages.php?type=${selectedType}`);
                        const stages = await response.json();

                        stageSelect.innerHTML = '<option value="">-- یک مرحله را انتخاب کنید --</option>';
                        if (stages && stages.length > 0) {
                            stages.forEach(stage => {
                                const option = document.createElement('option');
                                option.value = stage.stage_id;
                                option.textContent = stage.stage;
                                stageSelect.appendChild(option);
                            });
                            stageSelect.disabled = false;
                        } else {
                            stageSelect.innerHTML = '<option value="">مرحله‌ای یافت نشد</option>';
                        }
                    } catch (error) {
                        console.error("Error fetching stages:", error);
                        stageSelect.innerHTML = '<option value="">خطا در بارگذاری</option>';
                    }
                });

                stageSelect.addEventListener('change', () => {
                    applyBtn.disabled = !stageSelect.value;
                });

                // ===========================================================
                // START: THIS IS THE CORRECTED AND COMPLETE CLICK HANDLER
                // ===========================================================
                applyBtn.addEventListener('click', async () => {
                    const type = typeSelect.value;
                    const stageId = stageSelect.value;
                    if (!type || !stageId) return;

                    // Show a loading indicator and disable the button to prevent double-clicks
                    document.getElementById('loading').style.display = 'block';
                    applyBtn.disabled = true;
                    applyBtn.textContent = 'در حال بارگذاری...';

                    try {
                        // Fetch the data for the specific stage from the API
                        const response = await fetch(`/ghom/api/get_stage_specific_status.php?plan=<?php echo $plan_file; ?>&type=${type}&stage=${stageId}`);
                        if (!response.ok) {
                            throw new Error(`Network response was not ok: ${response.statusText}`);
                        }
                        const stageSpecificData = await response.json();

                        // Re-render the map using only the data for the selected stage
                        renderElementColors(stageSpecificData, 'all');

                    } catch (error) {
                        console.error("Error fetching stage-specific data:", error);
                        alert('خطا در اعمال فیلتر. لطفا دوباره تلاش کنید.');
                    } finally {
                        // Hide loading indicator and re-enable the button
                        document.getElementById('loading').style.display = 'none';
                        applyBtn.disabled = false;
                        applyBtn.textContent = 'اعمال فیلتر';
                    }
                });
                // ===========================================================
                // END: CORRECTED CLICK HANDLER
                // ===========================================================

                // Reset button logic (this was already correct)
                resetBtn.addEventListener('click', () => {
                    renderElementColors(ALL_ELEMENTS_DATA, 'all');
                    typeSelect.value = '';
                    stageSelect.innerHTML = '<option value="">-- ابتدا نوع المان را انتخاب کنید --</option>';
                    stageSelect.disabled = true;
                    applyBtn.disabled = true;
                    summaryPanel.style.display = 'none';
                });
            }

            function updateLegendCounts(counts) {
                document.getElementById('count-ok').textContent = counts['OK'] || 0;
                document.getElementById('count-reject').textContent = counts['Reject'] || 0;
                document.getElementById('count-repair').textContent = counts['Repair'] || 0;
                document.getElementById('count-pre-inspection').textContent = counts['Pre-Inspection Complete'] || 0;
                document.getElementById('count-awaiting-reinspection').textContent = counts['Awaiting Re-inspection'] || 0;
                document.getElementById('count-pending').textContent = counts['Pending'] || 0;
            }

            function setupGlobalTooltipHandling() {
                document.addEventListener('mousemove', e => {
                    if (tooltip.classList.contains('tooltip-visible')) {
                        tooltip.style.left = `${e.clientX + 15}px`;
                        tooltip.style.top = `${e.clientY - 10}px`;
                    }
                });
                document.getElementById('svg-container-wrapper').addEventListener('mouseleave', () => hideTooltip());
            }

            function showTooltip(content) {
                if (tooltipTimeout) clearTimeout(tooltipTimeout);
                tooltip.innerHTML = content;
                tooltip.classList.add('tooltip-visible');
            }

            function hideTooltip() {
                tooltipTimeout = setTimeout(() => {
                    tooltip.classList.remove('tooltip-visible');
                }, 50);
            }

            function setupPanAndZoom(wrapper) {
                let isPanning = false,
                    startX = 0,
                    startY = 0;
                let initialPinchDistance = null;

                const updateTransform = () => {
                    svgElement.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
                };

                const handlePanStart = (clientX, clientY) => {
                    isPanning = true;
                    wrapper.classList.add('grabbing');
                    startX = clientX - panX;
                    startY = clientY - panY;
                };

                const handlePanMove = (clientX, clientY) => {
                    if (!isPanning) return;
                    panX = clientX - startX;
                    panY = clientY - startY;
                    updateTransform();
                };

                const handlePanEnd = () => {
                    isPanning = false;
                    wrapper.classList.remove('grabbing');
                    initialPinchDistance = null;
                };

                const getPinchDistance = (touches) => Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY);

                const handlePinchStart = (touches) => {
                    initialPinchDistance = getPinchDistance(touches);
                };

                const handlePinchMove = (touches) => {
                    if (initialPinchDistance === null) return;
                    const newDist = getPinchDistance(touches);
                    const delta = newDist / initialPinchDistance;

                    const rect = wrapper.getBoundingClientRect();
                    const midX = (touches[0].clientX + touches[1].clientX) / 2;
                    const midY = (touches[0].clientY + touches[1].clientY) / 2;
                    const xs = (midX - rect.left - panX) / scale;
                    const ys = (midY - rect.top - panY) / scale;

                    const newScale = Math.max(0.1, Math.min(10, scale * delta));
                    panX += xs * scale - xs * newScale;
                    panY += ys * scale - ys * newScale;
                    scale = newScale;

                    updateTransform();
                    initialPinchDistance = newDist; // Update for continuous zoom
                };

                // Mouse Events
                wrapper.addEventListener('mousedown', e => {
                    if (e.target.closest('.highlight-marker')) return;
                    handlePanStart(e.clientX, e.clientY);
                    e.preventDefault();
                });
                wrapper.addEventListener('mousemove', e => handlePanMove(e.clientX, e.clientY));
                wrapper.addEventListener('mouseup', handlePanEnd);
                wrapper.addEventListener('wheel', e => {
                    e.preventDefault();
                    const r = wrapper.getBoundingClientRect();
                    const xs = (e.clientX - r.left - panX) / scale;
                    const ys = (e.clientY - r.top - panY) / scale;
                    const d = e.deltaY > 0 ? 0.9 : 1.1;
                    const newScale = Math.max(0.1, Math.min(10, scale * d));
                    panX += xs * scale - xs * newScale;
                    panY += ys * scale - ys * newScale;
                    scale = newScale;
                    updateTransform();
                });

                // Touch Events
                wrapper.addEventListener('touchstart', e => {
                    if (e.target.closest('.highlight-marker')) return;
                    e.preventDefault();
                    if (e.touches.length === 1) handlePanStart(e.touches[0].clientX, e.touches[0].clientY);
                    else if (e.touches.length === 2) handlePinchStart(e.touches);
                }, {
                    passive: false
                });

                wrapper.addEventListener('touchmove', e => {
                    e.preventDefault();
                    if (e.touches.length === 1) handlePanMove(e.touches[0].clientX, e.touches[0].clientY);
                    else if (e.touches.length === 2) handlePinchMove(e.touches);
                }, {
                    passive: false
                });

                wrapper.addEventListener('touchend', e => {
                    if (e.touches.length < 2) initialPinchDistance = null;
                    if (e.touches.length === 0) handlePanEnd();
                });
            }

            // REPLACE your old addStatusCircles function with this one.
            function applyStatusStyles() {
                const msgDiv = document.getElementById('filter-no-results-message');
                msgDiv.style.display = 'none';
                let visibleElements = 0;

                // A map for status colors, now with more opacity
                const STATUS_COLORS = {
                    'OK': 'rgba(40, 167, 69, 0.8)', // Green
                    'Not OK': 'rgba(220, 53, 69, 0.8)', // Red
                    'Ready for Inspection': 'rgba(255, 193, 7, 0.8)', // Yellow
                    'Pending': 'rgba(108, 117, 125, 0.5)' // Grey
                };

                // First, reset all panel colors to a default "pending" state
                svgElement.querySelectorAll('path, rect').forEach(el => {
                    if (el.id && el.id.startsWith('Z')) { // Target only elements with your generated IDs
                        el.style.setProperty('fill', STATUS_COLORS['Pending'], 'important');
                    }
                });

                ELEMENTS_DATA.forEach(element => {
                    if (!element.element_id) return;

                    // Find the main panel element directly by its ID
                    const panelElement = svgElement.getElementById(element.element_id);
                    if (!panelElement) return;

                    const status = (element.final_status || 'Pending').trim();
                    const matchesFilter = (HIGHLIGHT_STATUS === 'all' || status === HIGHLIGHT_STATUS);

                    // Add a data-status attribute for CSS and tooltips
                    panelElement.dataset.status = status;

                    if (matchesFilter) {
                        visibleElements++;
                        panelElement.style.display = '';

                        // Apply the status color directly to the panel's fill
                        const color = STATUS_COLORS[status] || STATUS_COLORS['Pending'];
                        panelElement.style.setProperty('fill', color, 'important');

                        // --- Attach Event Listeners for Tooltip ---
                        panelElement.addEventListener('mouseenter', e => {
                            let content = `<strong>شناسه:</strong> ${element.element_id}<br>` +
                                `<strong>وضعیت:</strong> ${status}<br>` +
                                `<strong>طبقه:</strong> ${element.floor_level || 'N/A'}`;
                            showTooltip(content);
                        });

                        panelElement.addEventListener('mouseleave', e => {
                            hideTooltip();
                        });

                    } else {
                        // If it doesn't match the filter, hide it
                        panelElement.style.display = 'none';
                    }
                });

                if (visibleElements === 0 && ELEMENTS_DATA.length > 0 && HIGHLIGHT_STATUS !== 'all') {
                    msgDiv.textContent = `هیچ موردی با وضعیت "${HIGHLIGHT_STATUS}" یافت نشد.`;
                    msgDiv.style.display = 'block';
                }
            }


            function updateStatusChart() {
                const counts = STATUS_COUNTS;
                const total = (counts['OK'] || 0) + (counts['Reject'] || 0) + (counts['Repair'] || 0) +
                    (counts['Pre-Inspection Complete'] || 0) + (counts['Awaiting Re-inspection'] || 0) +
                    (counts['Pending'] || 0);

                const chartContainer = document.getElementById('status-chart-container');
                if (total === 0) {
                    if (chartContainer) chartContainer.style.display = 'none';
                    return;
                }

                if (chartContainer) chartContainer.style.display = 'block';

                const statusMap = {
                    'ok': 'OK',
                    'reject': 'Reject',
                    'repair': 'Repair',
                    'pre-inspection': 'Pre-Inspection Complete',
                    'awaiting-reinspection': 'Awaiting Re-inspection',
                    'pending': 'Pending'
                };

                for (const [key, statusName] of Object.entries(statusMap)) {
                    const count = counts[statusName] || 0;
                    const percentage = total > 0 ? (count / total) * 100 : 0;

                    // Update the visual percentage bar
                    const bar = document.getElementById(`chart-bar-${key}`);
                    if (bar) {
                        bar.style.width = `${percentage}%`;
                        const persianStatus = STATUS_PERSIAN[statusName] || statusName;
                        bar.title = `${persianStatus}: ${count} (${Math.round(percentage)}%)`;
                    }

                    // Update the detailed text label
                    const detailValue = document.querySelector(`#detail-${key} .detail-value`);
                    if (detailValue) {
                        detailValue.textContent = `${count} (${Math.round(percentage)}%)`;
                    }
                }
            }
        </script>
</body>

</html>


