<?php
// ghom/weekly_report.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/metrics_calculator.php';
secureSession();
if (!isLoggedIn()) { header('Location: /login.php'); exit; }

$pdo = getProjectDBConnection('ghom');
$pageTitle = "گزارشات هفتگی";
require_once __DIR__ . '/header_ghom.php';
$datesStmt = $pdo->query("SELECT DISTINCT report_date FROM scurve_data ORDER BY report_date DESC");
$availableDates = $datesStmt->fetchAll(PDO::FETCH_COLUMN);

// If no date selected in URL, use the latest available date from DB
$currentWeek = $_GET['date'] ?? ($availableDates[0] ?? null);
// 1. Get Date


// 2. Fetch Base Metrics from DB (manually entered data)
$metrics = getBaseMetricsFromDB($pdo, $currentWeek);

// 3. Calculate Live Metrics (Panel Status from QC tables)
$liveMetrics = calculateWeeklyMetrics($pdo, $currentWeek);

// 4. Merge: Live data overrides/supplements manual data
if ($metrics) {
    // Add live calculated values
    $metrics['new_panel_actual_cum'] = $liveMetrics['new_panel_actual_cum'];
    $metrics['panel_healthy_qty'] = $liveMetrics['panel_healthy_qty'];
    $metrics['panel_rejected_qty'] = $liveMetrics['panel_rejected_qty'];
    $metrics['total_panel_healthy_area'] = $liveMetrics['total_panel_healthy_area'];
    $metrics['total_panel_repaired_area'] = $liveMetrics['total_panel_repaired_area'];
    $metrics['total_panel_rejected_area'] = $liveMetrics['total_panel_rejected_area'];
} else {
    // No manual record? Use live defaults
    $metrics = $liveMetrics;
}

// 5. Fetch S-Curve Data
$scurveData = getSCurveData($pdo, $currentWeek);

// Helper functions
function fmt($val, $dec = 0) {
    return isset($val) && $val !== null ? number_format((float)$val, $dec) : '-';
}

function dev($act, $pl) {
    if (!isset($act) || !isset($pl) || $pl == 0) return '-';
    $d = $act - $pl;
    $c = $d < 0 ? 'red' : 'green';
    return "<span style='color:$c; direction:ltr'>" . number_format($d, 2) . "%</span>";
}

// Function to convert Gregorian date to Persian (Jalali)
function gregorianToPersian($gregorianDate) {
    if (empty($gregorianDate)) return '';
    
    $parts = explode('-', $gregorianDate);
    if (count($parts) !== 3) return $gregorianDate;
    
    $gy = (int)$parts[0];
    $gm = (int)$parts[1];
    $gd = (int)$parts[2];
    
    // Simple Gregorian to Jalali conversion algorithm
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    
    if ($gy > 1600) {
        $jy = 979;
        $gy -= 1600;
    } else {
        $jy = 0;
        $gy -= 621;
    }
    
    if ($gm > 2) {
        $gy2 = $gy + 1;
    } else {
        $gy2 = $gy;
    }
    
    $days = (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * (int)($days / 12053);
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;
    
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}

// Convert S-Curve dates to Persian
function convertSCurveDatesToPersian($scurveData) {
    foreach ($scurveData as $blockType => &$data) {
        foreach ($data as &$point) {
            $point['x'] = gregorianToPersian($point['x']);
        }
    }
    return $scurveData;
}

$scurveData = convertSCurveDatesToPersian($scurveData);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        @font-face { font-family: "Samim"; src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"); }
        body { font-family: "Samim", Tahoma; background: #f5f7fa; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .chart-box { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .chart-box h3 { margin-top: 0; color: #2d3748; border-bottom: 2px solid #edf2f7; padding-bottom: 10px; }
        .data-table { width: 100%; border-collapse: collapse; background: white; font-size: 13px; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        .head-yl { background: #fffacd; font-weight: bold; }
        .form-control { padding: 10px; margin-bottom: 15px; }
        .form-control input { padding: 8px; margin-right: 10px; border: 1px solid #cbd5e0; border-radius: 4px; }
        .form-control button { padding: 8px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .form-control button:hover { background: #5a67d8; }
        .form-control a { float: left; color: #667eea; text-decoration: none; padding: 8px; }
    </style>
</head>
<body>

<div class="container">
    <!-- Date Selector -->
     <div class="chart-box">
        <form method="GET" class="form-control" style="display: flex; align-items: center; gap: 10px;">
            <label style="font-weight: bold;">تاریخ گزارش:</label>
            
            <select name="date" onchange="this.form.submit()" style="padding: 8px; border: 1px solid #cbd5e0; border-radius: 4px; font-family: 'Samim', Tahoma;">
                <?php if (empty($availableDates)): ?>
                    <option value="">هیچ گزارشی یافت نشد</option>
                <?php else: ?>
                    <?php foreach ($availableDates as $date): ?>
                        <option value="<?= htmlspecialchars($date) ?>" <?= ($currentWeek == $date) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($date) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <noscript>
                <button type="submit">نمایش</button>
            </noscript>

            <?php if ($_SESSION['role'] === 'superuser' || $_SESSION['role'] === 'admin'): ?>
                <a href="admin_reports.php" style="margin-right: auto;">مدیریت گزارشات</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- S-CURVES -->
    <div class="chart-box">
        <h3>منحنی پیشرفت کلی پروژه (S-Curve)</h3>
        <div id="chartTotal"></div>
    </div>

    <?php if (!empty($scurveData['block_a'])): ?>
    <div class="chart-box">
        <h3>منحنی پیشرفت بلوک A</h3>
        <div id="chartBlockA"></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($scurveData['block_b'])): ?>
    <div class="chart-box">
        <h3>منحنی پیشرفت بلوک B</h3>
        <div id="chartBlockB"></div>
    </div>
    <?php endif; ?>

    <!-- METRICS TABLE -->
    <div class="chart-box">
        <h3>جدول خلاصه وضعیت پروژه</h3>
        <table class="data-table">
            <thead>
                <tr class="head-yl">
                    <th rowspan="2">ردیف</th>
                    <th rowspan="2">موضوع</th>
                    <th rowspan="2">واحد</th>
                    <th colspan="2">برنامه‌ای</th>
                    <th colspan="2">واقعی</th>
                    <th rowspan="2">انحراف تجمعی</th>
                </tr>
                <tr class="head-yl">
                    <th>تجمعی</th>
                    <th>دوره</th>
                    <th>تجمعی</th>
                    <th>دوره</th>
                </tr>
            </thead>
            <tbody>
                <!-- 1. Physical Progress -->
                <tr>
                    <td>1</td>
                    <td>پیشرفت فیزیکی</td>
                    <td>%</td>
                    <td><?= fmt($metrics['progress_plan_cumulative'] ?? 0, 2) ?></td>
                    <td><?= fmt($metrics['progress_plan_period'] ?? 0, 2) ?></td>
                    <td><?= fmt($metrics['progress_actual_cumulative'] ?? 0, 2) ?></td>
                    <td><?= fmt($metrics['progress_actual_period'] ?? 0, 2) ?></td>
                    <td><?= dev($metrics['progress_actual_cumulative'] ?? 0, $metrics['progress_plan_cumulative'] ?? 0) ?></td>
                </tr>
                
                <!-- 2. Manpower -->
                <tr>
                    <td>2</td>
                    <td>نیروی انسانی</td>
                    <td>نفر</td>
                    <td>-</td>
                    <td><?= fmt($metrics['manpower_plan'] ?? 0) ?></td>
                    <td>-</td>
                    <td><?= fmt($metrics['manpower_actual'] ?? 0) ?></td>
                    <td><?= dev($metrics['manpower_actual'] ?? 0, $metrics['manpower_plan'] ?? 0) ?></td>
                </tr>

                <!-- 6. Substructure -->
                <tr>
                    <td>6</td>
                    <td>اصلاحات زیرسازی</td>
                    <td>m²</td>
                    <td><?= fmt($metrics['substruct_plan_cum'] ?? 0) ?></td>
                    <td><?= fmt($metrics['substruct_plan_period'] ?? 0) ?></td>
                    <td><?= fmt($metrics['substruct_actual_cum'] ?? 0) ?></td>
                    <td><?= fmt($metrics['substruct_actual_period'] ?? 0) ?></td>
                    <td>-</td>
                </tr>

                <!-- 9. New Panel Entry (Live Calculated) -->
                <tr>
                    <td>9</td>
                    <td>ورود پنل جدید</td>
                    <td>m²</td>
                    <td><?= fmt($metrics['new_panel_plan_cum'] ?? 0) ?></td>
                    <td>-</td>
                    <td><?= fmt($metrics['new_panel_actual_cum'] ?? 0) ?></td>
                    <td>-</td>
                    <td>-</td>
                </tr>

                <!-- 9-1. Healthy Panels (Site QC) -->
                <tr>
                    <td>9-1</td>
                    <td>پنل‌های سالم (تایید شده)</td>
                    <td>m²</td>
                    <td>-</td>
                    <td>-</td>
                    <td><?= fmt($metrics['panel_healthy_qty'] ?? 0) ?></td>
                    <td>-</td>
                    <td>-</td>
                </tr>

                <!-- 9-2. Rejected Panels (Site QC) -->
                <tr>
                    <td>9-2</td>
                    <td>پنل‌های رد شده</td>
                    <td>m²</td>
                    <td>-</td>
                    <td>-</td>
                    <td><?= fmt($metrics['panel_rejected_qty'] ?? 0) ?></td>
                    <td>-</td>
                    <td>-</td>
                </tr>

                <!-- 10. Workshop Sorting Results -->
                <tr class="head-yl">
                    <td>10</td>
                    <td colspan="6">نتایج سورت کارگاهی (Workshop QC)</td>
                    <td>-</td>
                </tr>
                <tr>
                    <td>10-1</td>
                    <td>پنل‌های سالم</td>
                    <td>m²</td>
                    <td>-</td>
                    <td>-</td>
                    <td><?= fmt($metrics['total_panel_healthy_area'] ?? 0) ?></td>
                    <td>-</td>
                    <td>-</td>
                </tr>
                <tr>
                    <td>10-2</td>
                    <td>پنل‌های نیاز به تعمیر</td>
                    <td>m²</td>
                    <td>-</td>
                    <td>-</td>
                    <td><?= fmt($metrics['total_panel_repaired_area'] ?? 0) ?></td>
                    <td>-</td>
                    <td>-</td>
                </tr>
                <tr>
                    <td>10-3</td>
                    <td>پنل‌های رد شده</td>
                    <td>m²</td>
                    <td>-</td>
                    <td>-</td>
                    <td><?= fmt($metrics['total_panel_rejected_area'] ?? 0) ?></td>
                    <td>-</td>
                    <td>-</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function renderChart(elementId, data) {
    if (!data || !data.length) return;

    const planPeriodic = [];
    const actualPeriodic = [];
    
    // We need to rebuild these arrays to handle nulls correctly
    const planCumulative = []; 
    const actualCumulative = [];

    // Flag to stop plotting actuals if we hit the "future" (where data drops to 0)
    let stopPlottingActuals = false;

    data.forEach((d, i) => {
        // --- 1. Handle Plan Data ---
        planCumulative.push(d.plan);
        
        if (i === 0) {
            planPeriodic.push(d.plan);
        } else {
            // Calculate plan periodic normally
            let pPeriod = d.plan - data[i - 1].plan;
            // Optional: prevent negative plan bars if DB data is messy
            planPeriodic.push(pPeriod < 0 ? 0 : pPeriod);
        }

        // --- 2. Handle Actual Data (The Fix) ---
        let prevActual = (i === 0) ? 0 : data[i - 1].actual;
        
        // Calculate the periodic difference
        let aPeriod = (i === 0) ? d.actual : (d.actual - prevActual);

        // LOGIC: If the periodic value is negative (meaning cumulative dropped),
        // or if we have already determined we are in the "future" (stopPlottingActuals),
        // or if actual is 0 and previous was valid (reset to 0), set to null.
        if (stopPlottingActuals || aPeriod < 0 || (d.actual === 0 && prevActual > 0)) {
            stopPlottingActuals = true; // Mark that we reached the end of valid data
            actualPeriodic.push(null);
            actualCumulative.push(null);
        } else {
            // If actual is 0 at the very start, keep it as 0, otherwise push values
            if (d.actual === 0 && i === 0) {
                 actualPeriodic.push(0);
                 actualCumulative.push(0);
            } else if (d.actual === 0 && prevActual === 0) {
                 // Still valid 0 (project hasn't started or no progress)
                 actualPeriodic.push(0);
                 actualCumulative.push(0);
            } else {
                 actualPeriodic.push(aPeriod);
                 actualCumulative.push(d.actual);
            }
        }
    });

    const options = {
        series: [
            {
                name: 'برنامه دوره‌ای',
                type: 'column',
                data: planPeriodic
            },
            {
                name: 'واقعی دوره‌ای',
                type: 'column',
                data: actualPeriodic
            },
            {
                name: 'برنامه تجمعی',
                type: 'line',
                data: planCumulative
            },
            {
                name: 'واقعی تجمعی',
                type: 'line',
                data: actualCumulative
            }
        ],
        chart: {
            height: 420,
            type: 'line',
            stacked: false,
            fontFamily: 'Samim, Tahoma',
            toolbar: { show: true },
            animations: { enabled: false } // Disable animation for clearer rendering of nulls
        },
        stroke: {
            width: [0, 0, 3, 3],
            curve: 'smooth'
        },
        plotOptions: {
            bar: {
                columnWidth: '55%',
                borderRadius: 3
            }
        },
        colors: ['#3182ce', '#38a169', '#f6ad55', '#e53e3e'],
        xaxis: {
            categories: data.map(d => d.x),
            labels: {
                rotate: -45
            }
        },
        yaxis: {
            labels: {
                formatter: val => val !== null ? val.toFixed(2) + '%' : ''
            }
        },
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: function (val) {
                    if (val === null || typeof val === 'undefined') return "ثبت نشده"; // "Not Recorded"
                    return val.toFixed(2) + '%';
                }
            }
        },
        legend: {
            position: 'top'
        }
    };

    new ApexCharts(document.querySelector(elementId), options).render();
}

// Pass PHP Data to JS
const scurveTotal  = <?= json_encode($scurveData['total'] ?? []) ?>;
const scurveBlockA = <?= json_encode($scurveData['block_a'] ?? []) ?>;
const scurveBlockB = <?= json_encode($scurveData['block_b'] ?? []) ?>;

if (scurveTotal.length)  renderChart("#chartTotal", scurveTotal);
if (scurveBlockA.length) renderChart("#chartBlockA", scurveBlockA);
if (scurveBlockB.length) renderChart("#chartBlockB", scurveBlockB);

</script>

<?php require_once 'footer.php'; ?>
</body>
</html>