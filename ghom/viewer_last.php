<?php
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/../includes/jdf.php';
secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

// --- Get Parameters ---
$plan_file = $_GET['plan'] ?? null;
$highlight_status = $_GET['highlight_status'] ?? 'all';

if (!$plan_file || !preg_match('/^[\w\.-]+\.svg$/i', $plan_file)) {
    http_response_code(400);
    die("Error: Invalid plan file name.");
}

$elements_data = [];
$status_counts = [
    'OK' => 0, 'Reject' => 0, 'Repair' => 0,
    'Pre-Inspection Complete' => 0, 'Awaiting Re-inspection' => 0, 'Pending' => 0
];
$total_area = 0;

try {
    $pdo = getProjectDBConnection('ghom');

    // --- START OF NEW, ROBUST QUERY ---
    // This query uses the same logic as the dashboard to get the single, definitive latest status for each element part.
    $stmt = $pdo->prepare("
        WITH LatestInspections AS (
            SELECT 
                i.*,
                ROW_NUMBER() OVER(PARTITION BY i.element_id, i.part_name ORDER BY i.created_at DESC, i.inspection_id DESC) as rn
            FROM inspections i
            JOIN elements e ON i.element_id = e.element_id
            WHERE e.plan_file = :plan_file_for_inspections -- Bind param here
        ),
        LatestInspectionsDetails AS (
            SELECT
                li.element_id, li.part_name, li.status, li.overall_status, li.contractor_status,
                li.notes, li.contractor_notes, li.inspection_date, li.history_log, li.pre_inspection_log,
                (SELECT COUNT(*) FROM inspection_data id WHERE id.inspection_id = li.inspection_id AND id.item_value LIKE '{%\"lines\"%}') as has_drawing
            FROM LatestInspections li
            WHERE li.rn = 1
        )
        SELECT
            e.element_id, e.element_type, e.plan_file, e.zone_name, e.floor_level, e.block, e.contractor,
            e.width_cm, e.height_cm, e.area_sqm, e.geometry_json,
            COALESCE(lid.status, 'Pending') as final_status, -- Use the latest status, or Pending if no inspection exists
            lid.has_drawing
        FROM elements e
        LEFT JOIN LatestInspectionsDetails lid ON e.element_id = lid.element_id AND lid.part_name IS NULL -- Adjust if you have parts
        WHERE e.plan_file = :plan_file_for_elements
    ");

    $stmt->execute([
        ':plan_file_for_inspections' => $plan_file,
        ':plan_file_for_elements' => $plan_file
    ]);
    $elements_data_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // --- END OF NEW, ROBUST QUERY ---

    // Process data, calculate totals, and count statuses
    foreach ($elements_data_raw as $el) {
        $status = $el['final_status'] ?? 'Pending';
        if (array_key_exists($status, $status_counts)) {
            $status_counts[$status]++;
        }
        $total_area += (float)($el['area_sqm'] ?? 0);
        $elements_data[] = $el;
    }

    if (!empty($elements_data)) {
        $first_element = $elements_data[0];
        $zone_name = $first_element['zone_name'] ?? basename($plan_file, '.svg');
        $contractor_name = $first_element['contractor'] ?? 'نامشخص';
        $block_name = $first_element['block'] ?? 'نامشخص';
    } else {
        $zone_name = basename($plan_file, '.svg');
        $contractor_name = 'نامشخص';
        $block_name = 'نامشخص';
    }

    $svg_path = realpath(__DIR__ . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $plan_file;
    if (!file_exists($svg_path)) {
        http_response_code(404); die("Error: SVG file not found.");
    }
    $svg_content = file_get_contents($svg_path);

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

        body, html {
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
    padding: 6px 8px;
    background: #fff;
    border-bottom: 1px solid #ccc;
    text-align: left;
    flex-shrink: 0;
    direction: ltr;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    min-height: 50px;
}

/* Make header info more compact */
#viewer-header-info {
    display: flex;
    gap: 15px;
    padding: 2px 10px;
    font-size: 12px;
    color: #333;
    margin-right: auto;
    direction: rtl;
    flex-wrap: wrap;
    align-items: center;
}

/* Compact legend */
#legend {
    padding: 2px 8px;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 8px;
    flex-grow: 1;
}

#legend span {
    font-size: 11px;
    white-space: nowrap;
}

.legend-color {
    width: 12px;
    height: 12px;
    margin-left: 4px;
}

.legend-count {
    font-size: 10px;
    padding: 1px 4px;
    margin-right: 3px;
}

/* Compact totals display */
#totals-display {
    gap: 10px;
    padding: 4px 10px;
    font-size: 12px;
}

.total-label {
    font-size: 11px;
}

.total-value {
    font-size: 13px;
    padding: 2px 6px;
    min-width: 30px;
}

/* Make filter panel more mobile-friendly */
details {
    min-width: 90px;
}

details summary {
    padding: 4px 8px;
    font-size: 12px;
}

#stage-filter-panel {
    width: 280px;
    top: 60px;
    right: 10px;
}

/* Status chart improvements */
#status-chart-container {
    width: 95%;
    margin: 15px auto;
    padding: 10px;
}

.chart-title {
    font-size: 1em;
    margin-bottom: 10px;
}

.chart-bar-wrapper {
    height: 30px;
}

.chart-bar {
    font-size: 11px;
}

.chart-details-wrapper {
    margin-top: 10px;
    padding-top: 8px;
}

.chart-detail {
    font-size: 12px;
    margin: 3px 8px;
}

/* ===== MOBILE RESPONSIVE BREAKPOINTS ===== */

@media screen and (max-width: 768px) {
    #viewer-toolbar {
        flex-direction: column;
        align-items: stretch;
        padding: 8px;
        gap: 8px;
        min-height: auto;
    }
    
    #viewer-header-info {
        order: 1;
        justify-content: center;
        font-size: 11px;
        gap: 10px;
        margin: 0;
        padding: 4px 0;
    }
    
    #viewer-header-info span {
        display: flex;
        align-items: center;
    }
    
    #legend {
        order: 2;
        padding: 4px 0;
        gap: 6px;
        justify-content: center;
    }
    
    #legend span {
        font-size: 10px;
    }
    
    .legend-color {
        width: 10px;
        height: 10px;
    }
    
    .legend-count {
        font-size: 9px;
        padding: 1px 3px;
    }
    
    #totals-display {
        order: 3;
        gap: 15px;
        padding: 4px 8px;
        justify-content: center;
        margin: 0;
    }
    
    .total-label {
        font-size: 10px;
    }
    
    .total-value {
        font-size: 12px;
        min-width: 25px;
        padding: 2px 4px;
    }
    
    /* Filter panel adjustments */
    details {
        order: 4;
        align-self: center;
        min-width: 100px;
    }
    
    details summary {
        padding: 6px 10px;
        font-size: 11px;
    }
    
    #stage-filter-panel {
        width: calc(100vw - 40px);
        max-width: 320px;
        left: 50%;
        transform: translateX(-50%);
        right: auto;
        top: 120px;
    }
    
    /* Status chart mobile optimization */
    #status-chart-container {
        width: 98%;
        margin: 10px auto;
        padding: 8px;
    }
    
    .chart-title {
        font-size: 0.9em;
        margin-bottom: 8px;
    }
    
    .chart-bar-wrapper {
        height: 25px;
    }
    
    .chart-bar {
        font-size: 9px;
    }
    
    .chart-details-wrapper {
        justify-content: center;
        gap: 5px;
    }
    
    .chart-detail {
        font-size: 10px;
        margin: 2px 4px;
        flex: 1;
        min-width: 0;
        text-align: center;
    }
    
    .detail-color {
        width: 10px;
        height: 10px;
        margin-left: 4px;
    }
    
    /* SVG container gets more space */
    #svg-container-wrapper {
        flex-grow: 1;
    }
    
    /* Tooltip improvements for mobile */
    .svg-tooltip {
        max-width: 250px;
        font-size: 11px;
        padding: 6px 10px;
        line-height: 1.4;
        white-space: normal;
        word-wrap: break-word;
    }
    
    /* Loading message */
    #loading {
        font-size: 16px;
    }
    
    #filter-no-results-message {
        font-size: 12px;
        padding: 8px 15px;
        top: 15px;
        left: 50%;
        transform: translateX(-50%);
        right: auto;
        max-width: calc(100vw - 40px);
        text-align: center;
    }
}

/* Very small screens */
@media screen and (max-width: 480px) {
    #viewer-header-info {
        font-size: 10px;
        gap: 8px;
    }
    
    #legend span {
        font-size: 9px;
    }
    
    #totals-display {
        flex-direction: column;
        gap: 5px;
        padding: 6px;
    }
    
    .total-item {
        justify-content: center;
        gap: 5px;
    }
    
    .chart-details-wrapper {
        flex-direction: column;
        align-items: center;
    }
    
    .chart-detail {
        margin: 2px 0;
        width: auto;
    }
    
    #stage-filter-panel {
        width: calc(100vw - 20px);
        left: 10px;
        transform: none;
        top: 100px;
    }
    
    .panel-body select {
        font-size: 14px;
        padding: 6px;
    }
    
    .svg-tooltip {
        max-width: calc(100vw - 40px);
        font-size: 10px;
        padding: 5px 8px;
    }
}

/* Landscape mobile optimization */
@media screen and (max-height: 500px) and (orientation: landscape) {
    #viewer-toolbar {
        padding: 4px 8px;
        min-height: 40px;
    }
    
    #viewer-header-info {
        font-size: 10px;
        gap: 8px;
    }
    
    #legend {
        padding: 2px 0;
        gap: 4px;
    }
    
    #legend span {
        font-size: 9px;
    }
    
    #totals-display {
        padding: 2px 6px;
        gap: 8px;
    }
    
    details summary {
        padding: 3px 6px;
        font-size: 10px;
    }
    
    #status-chart-container {
        margin: 8px auto;
        padding: 6px;
    }
    
    .chart-title {
        font-size: 0.8em;
        margin-bottom: 6px;
    }
    
    .chart-bar-wrapper {
        height: 20px;
    }
    
    .chart-details-wrapper {
        margin-top: 6px;
    }
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
            <div class="controls">
                <button id="zoom-in-btn">+</button> <button id="zoom-out-btn">-</button> <button id="zoom-reset-btn">ریست</button>
                <button id="print-btn">چاپ</button> <button id="download-btn">دانلود SVG</button><a href="/ghom/inspection_dashboard.php" class="btn btn-back">بازگشت به داشبورد</a>
            </div>
        </div>
        <details class="stage-filter-wrapper">
        <summary>فیلتر نمایش وضعیت مراحل</summary>
        
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
                <!-- ... content of summary ... -->
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
            const HIGHLIGHT_STATUS = <?php echo json_encode($highlight_status ?? 'all'); ?>;

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
                        `<br><strong>تاریخ بازرسی:</strong> ${latestEntry.data.inspection_date}` : '';

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
                        'request-opening': 'درخواست باز کردن',
                        'approve-opening': 'تایید باز کردن',
                        'confirm-opened': 'تایید باز شدن',
                        'verify-opening': 'راستی‌آزمایی باز کردن'
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
                setupToolbar();
                setupGlobalTooltipHandling();
                setupStageFilterPanel();

                // Initial render with overall status by default
                renderElementColors(ALL_ELEMENTS_DATA, HIGHLIGHT_STATUS);
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

                const elementDataMap = new Map(elementsToRender.map(el => [el.element_id, el]));

                svgElement.querySelectorAll('path[id], rect[id]').forEach(el => {
                    // Reset styles
                    el.style.fill = 'rgba(108, 117, 125, 0.2)';
                    el.style.display = 'none';
                    el.style.cursor = 'default';
                    // Remove old event listeners by cloning the element
                    const newEl = el.cloneNode(true);
                    el.parentNode.replaceChild(newEl, el);
                });

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

                            // Enhanced tooltip with history and pre-inspection data
                            el.addEventListener('mouseenter', () => {
                                let content = `<strong>شناسه:</strong> ${element.element_id}<br>` +
                                    `<strong>نوع:</strong> ${element.element_type}<br>` +
                                    `<strong>ابعاد (cm):</strong> ${element.width_cm || 'N/A'} x ${element.height_cm || 'N/A'}<br>` +
                                    `<strong>مساحت:</strong> ${element.area_sqm || 'N/A'} m²<br>` +
                                    `<strong>وضعیت کلی:</strong> ${STATUS_PERSIAN[status] || status}`;

                                // Add history log information
                                const historyInfo = parseHistoryLog(element.latest_history_log);
                                if (historyInfo) {
                                    content += historyInfo;
                                }

                                // Add pre-inspection log information
                                const preInspectionInfo = parsePreInspectionLog(element.latest_pre_inspection_log);
                                if (preInspectionInfo) {
                                    content += preInspectionInfo;
                                }

                                // Add latest notes if available
                                if (element.latest_notes) {
                                    content += `<br><strong>یادداشت‌ها:</strong> ${element.latest_notes}`;
                                }

                                // Add inspection date if available
                                if (element.latest_inspection_date) {
                                    const persianDate = toPersianDate(element.latest_inspection_date);
                                    content += `<br><strong>تاریخ بازرسی:</strong> ${persianDate || element.latest_inspection_date}`;
                                }

                                showTooltip(content);
                                el.style.stroke = '#000';
                                el.style.strokeWidth = '1px';
                            });

                            el.addEventListener('mouseleave', () => {
                                hideTooltip();
                                el.style.stroke = '';
                                el.style.strokeWidth = '';
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

            function setupToolbar() {
                const updateAndZoom = (newScale) => {
                    scale = newScale;
                    svgElement.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
                };
                document.getElementById('zoom-in-btn').onclick = () => updateAndZoom(Math.min(10, scale * 1.2));
                document.getElementById('zoom-out-btn').onclick = () => updateAndZoom(Math.max(0.1, scale / 1.2));
                document.getElementById('zoom-reset-btn').onclick = () => {
                    panX = 0;
                    panY = 0;
                    updateAndZoom(1);
                };
                document.getElementById('print-btn').onclick = () => window.print();
                document.getElementById('download-btn').onclick = () => {
                    try {
                        const originalTransform = svgElement.style.transform;
                        svgElement.style.transform = '';
                        const svgData = new XMLSerializer().serializeToString(svgElement);
                        const blob = new Blob([svgData], {
                            type: 'image/svg+xml;charset=utf-8'
                        });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = '<?php echo escapeHtml($plan_file); ?>';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                        svgElement.style.transform = originalTransform;
                    } catch (error) {
                        alert('خطا در دانلود فایل: ' + error.message);
                    }
                };
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