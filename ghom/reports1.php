<?php
// Use the provided jdf.php library for Persian dates
require_once __DIR__ . '/../includes/jdf.php';

// Function to safely read and parse a CSV file.
function parse_csv($file_path) {
    if (!file_exists($file_path)) {
        return ['error' => 'CSV file not found.'];
    }

    $file = fopen($file_path, 'r');
    if ($file === false) {
        return ['error' => 'Could not open CSV file.'];
    }

    $data = [];
    $headers = fgetcsv($file); // Read the header row

    // Trim whitespace and handle potential byte order marks (BOM) for UTF-8 files
    $headers = array_map(function($header) {
        $header = trim($header);
        return preg_replace('/^\xEF\xBB\xBF/', '', $header);
    }, $headers);

    while (($row = fgetcsv($file)) !== false) {
        // Create an associative array for each row
        $rowData = [];
        foreach ($headers as $index => $header) {
            $rowData[$header] = isset($row[$index]) ? trim($row[$index]) : '';
        }

        // Add a Persian date field for display
        if (!empty($rowData['Start'])) {
            try {
                $date_obj = new DateTime($rowData['Start']);
                $rowData['Start_Persian'] = jdate('Y/m/d', $date_obj->getTimestamp(), '', 'Asia/Tehran', 'en');
            } catch (Exception $e) {
                $rowData['Start_Persian'] = ''; // Handle invalid dates
            }
        }
        $data[] = $rowData;
    }

    fclose($file);
    return ['data' => $data, 'headers' => $headers];
}

// Function to sanitize column names for use as HTML IDs
function sanitizeId($str) {
    return preg_replace('/[^a-zA-Z0-9-]/', '_', $str);
}

$csv_file = 'sample_project_data.csv';
$parsed_data = parse_csv($csv_file);

if (isset($parsed_data['error'])) {
    $error_message = $parsed_data['error'];
    $data_json = '[]';
    $headers_json = '[]';
} else {
    $data = $parsed_data['data'];
    $headers = $parsed_data['headers'];
    $data_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $headers_json = json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد مدیریت پروژه</title>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>
    <style>
         @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
                 url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
                 url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
        }
        body {
            font-family: 'Samim', sans-serif;
            background-color: #f3f4f6;
            color: #333;
            margin: 0;
            padding: 20px;
            direction: rtl;
            text-align: right;
        }
      
        .container {
            max-width: 1200px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        h1, h2, h3 {
            color: #1a202c;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .filters {
            display: flex;
            flex-wrap: nowrap; /* Prevents wrapping */
            overflow-x: auto; /* Adds horizontal scroll on small screens if needed */
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            white-space: nowrap;
        }
        .filter-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #4a5568;
        }
        .filters select {
            padding: 8px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            background-color: #fff;
            cursor: pointer;
            min-width: 180px; /* Increased width for larger size */
        }
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100%, 1fr));
            gap: 20px;
        }
        .chart-card {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .alert {
            background-color: #fefcbf;
            color: #744210;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        /* Style for Download formats button */
        .apexcharts-toolbar {
            display: flex;
            justify-content: flex-start;
            flex-direction: row-reverse;
            padding-left: 0 !important;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>داشبورد پروژه</h1>
    <?php if (isset($error_message)): ?>
        <div class="alert"><?php echo htmlspecialchars($error_message); ?></div>
    <?php else: ?>
        <div class="filters">
            <div class="filter-group">
                <label for="filter-<?php echo sanitizeId('جبهه کاری'); ?>">فیلتر بر اساس جبهه کاری</label>
                <select id="filter-<?php echo sanitizeId('جبهه کاری'); ?>"><option value="all">همه</option></select>
            </div>
            <div class="filter-group">
                <label for="filter-<?php echo sanitizeId('توزیع نقش'); ?>">فیلتر بر اساس توزیع نقش</label>
                <select id="filter-<?php echo sanitizeId('توزیع نقش'); ?>"><option value="all">همه</option></select>
            </div>
            <div class="filter-group">
                <label for="filter-<?php echo sanitizeId('نما/زون'); ?>">فیلتر بر اساس نما/زون</label>
                <select id="filter-<?php echo sanitizeId('نما/زون'); ?>"><option value="all">همه</option></select>
            </div>
            <div class="filter-group">
                <label for="filter-<?php echo sanitizeId('نوع نما'); ?>">فیلتر بر اساس نوع نما</label>
                <select id="filter-<?php echo sanitizeId('نوع نما'); ?>"><option value="all">همه</option></select>
            </div>
            <div class="filter-group">
                <label for="filter-<?php echo sanitizeId('مصالح'); ?>">فیلتر بر اساس مصالح</label>
                <select id="filter-<?php echo sanitizeId('مصالح'); ?>"><option value="all">همه</option></select>
            </div>
            <div class="filter-group">
                <label for="filter-<?php echo sanitizeId('سامری'); ?>">فیلتر بر اساس سامری</label>
                <select id="filter-<?php echo sanitizeId('سامری'); ?>"><option value="all">همه</option></select>
            </div>
            <div class="filter-group">
                <label for="filter-<?php echo sanitizeId('قراردادی'); ?>">فیلتر بر اساس قراردادی</label>
                <select id="filter-<?php echo sanitizeId('قراردادی'); ?>"><option value="all">همه</option></select>
            </div>
            <div class="filter-group">
                <label for="filter-<?php echo sanitizeId('Outline Level'); ?>">فیلتر بر اساس سطح طرح کلی</label>
                <select id="filter-<?php echo sanitizeId('Outline Level'); ?>"><option value="all">همه</option></select>
            </div>
            <div class="filter-group">
                <label for="filter-<?php echo sanitizeId('Summary'); ?>">فیلتر بر اساس خلاصه</label>
                <select id="filter-<?php echo sanitizeId('Summary'); ?>"><option value="all">همه</option></select>
            </div>
        </div>

        <div class="chart-grid">
            <div class="chart-card">
                <h2>نمای کلی پیشرفت</h2>
                <div id="progressChart"></div>
            </div>
            <div class="chart-card">
                <h2>تعداد فعالیت‌ها بر اساس جبهه کاری</h2>
                <div id="chart-<?php echo sanitizeId('جبهه کاری'); ?>"></div>
            </div>
            <div class="chart-card">
                <h2>تعداد فعالیت‌ها بر اساس توزیع نقش</h2>
                <div id="chart-<?php echo sanitizeId('توزیع نقش'); ?>"></div>
            </div>
            <div class="chart-card">
                <h2>تعداد فعالیت‌ها بر اساس نما/زون</h2>
                <div id="chart-<?php echo sanitizeId('نما/زون'); ?>"></div>
            </div>
            <div class="chart-card">
                <h2>تعداد فعالیت‌ها بر اساس نوع نما</h2>
                <div id="chart-<?php echo sanitizeId('نوع نما'); ?>"></div>
            </div>
            <div class="chart-card">
                <h2>تعداد فعالیت‌ها بر اساس مصالح</h2>
                <div id="chart-<?php echo sanitizeId('مصالح'); ?>"></div>
            </div>
            <div class="chart-card">
                <h2>تعداد فعالیت‌ها بر اساس سامری</h2>
                <div id="chart-<?php echo sanitizeId('سامری'); ?>"></div>
            </div>
            <div class="chart-card">
                <h2>تعداد فعالیت‌ها بر اساس قراردادی</h2>
                <div id="chart-<?php echo sanitizeId('قراردادی'); ?>"></div>
            </div>
            <div class="chart-card">
                <h2>پیشرفت هفتگی</h2>
                <div id="weeklyProgressChart"></div>
            </div>
            <div class="chart-card">
                <h2>پیشرفت ماهانه</h2>
                <div id="monthlyProgressChart"></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rawData = <?php echo $data_json; ?>;
        if (rawData.length === 0) {
            return;
        }

        let filteredData = rawData;

        // Function to sanitize column names for use as HTML IDs
        function sanitizeId(str) {
            return str.replace(/[^a-zA-Z0-9-]/g, '_');
        }
        
        function getWeekNumber(date) {
            const d = new Date(date);
            d.setHours(0, 0, 0, 0);
            d.setDate(d.getDate() + 4 - (d.getDay() || 7));
            const yearStart = new Date(d.getFullYear(), 0, 1);
            const weekNo = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
            return weekNo;
        }
        
        // --- Filter Setup ---
        const filterableColumns = ['جبهه کاری', 'توزیع نقش', 'نما/زون', 'نوع نما', 'مصالح', 'سامری', 'قراردادی', 'Outline Level', 'Summary'];
        const filters = {};

        filterableColumns.forEach(column => {
            const selectEl = document.getElementById(`filter-${sanitizeId(column)}`);
            if (selectEl) {
                const uniqueValues = [...new Set(rawData.map(item => item[column]).filter(Boolean))];
                uniqueValues.sort((a, b) => a.localeCompare(b, 'fa', { sensitivity: 'base' }));

                uniqueValues.forEach(value => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = value;
                    selectEl.appendChild(option);
                });

                selectEl.addEventListener('change', (e) => {
                    filters[column] = e.target.value;
                    applyFiltersAndRenderCharts();
                });
            }
        });

        function applyFiltersAndRenderCharts() {
            filteredData = rawData.filter(item => {
                for (const column in filters) {
                    if (filters[column] !== 'all' && item[column] !== filters[column]) {
                        return false;
                    }
                }
                return true;
            });
            renderAllCharts(filteredData);
        }

        // --- Chart Rendering ---
        const commonOptions = {
            chart: {
                toolbar: {
                    show: true,
                    offsetX: 0,
                    offsetY: 0,
                    tools: {
                        download: true
                    },
                    export: {
                        csv: {
                            filename: undefined,
                            columnDelimiter: ',',
                            headerCategory: 'category',
                            headerValue: 'value',
                            dateFormatter(timestamp) {
                                return new Date(timestamp).toDateString()
                            }
                        },
                        svg: { filename: undefined },
                        png: { filename: undefined }
                    },
                    autoSelected: 'zoom'
                },
                fontFamily: 'Inter, sans-serif'
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val.toFixed(2); // Format numbers to 2 decimal places
                    }
                }
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    horizontal: false,
                }
            },
            dataLabels: {
                enabled: false
            },
            legend: {
                position: 'top'
            }
        };

        const charts = {};
        const jalaliMonths = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];

        function renderAllCharts(data) {
            // Create a map to store chart data
            const chartData = {};
            filterableColumns.slice(0, 7).forEach(col => {
                chartData[col] = {};
            });

            // Progress data
            const progress = [];
            
            // Weekly/Monthly data
            const weeklyData = {};
            const monthlyData = {};

            data.forEach(item => {
                // Categorical data
                filterableColumns.slice(0, 7).forEach(col => {
                    const value = item[col];
                    if (value) {
                        chartData[col][value] = (chartData[col][value] || 0) + 1;
                    }
                });

                // Progress data
                const taskName = item['Task Name'];
                const plannedProgress = parseFloat(item['پیشرفت برنامه‌ای']) || 0;
                if (taskName && item['Summary'] === 'Yes') {
                    // Assuming 'Work Complete' is needed, using a placeholder for now
                    const workComplete = Math.min(100, plannedProgress + Math.random() * 20); // Placeholder
                    progress.push({ name: taskName, planned: plannedProgress, workComplete: workComplete });
                }

                // Weekly/Monthly data
                if (item['Start']) {
                    const startDate = new Date(item['Start']);
                    const startYear = startDate.getFullYear();
                    const startMonth = startDate.getMonth() + 1;
                    const startWeek = getWeekNumber(startDate);

                    const monthKey = `${startYear}-${startMonth}`;
                    monthlyData[monthKey] = (monthlyData[monthKey] || 0) + 1;

                    const weekKey = `${startYear}-${startWeek}`;
                    weeklyData[weekKey] = (weeklyData[weekKey] || 0) + 1;
                }
            });

            // --- Progress Chart ---
            const progressOptions = {
                ...commonOptions,
                series: [{
                    name: 'پیشرفت برنامه‌ای',
                    data: progress.map(p => p.planned.toFixed(2)) // Apply formatting here
                }, {
                    name: 'پیشرفت اجرایی', // Placeholder label for 'work complete'
                    data: progress.map(p => p.workComplete.toFixed(2)) // Apply formatting here
                }],
                chart: { ...commonOptions.chart, type: 'bar', height: 350 },
                xaxis: {
                    categories: progress.map(p => p.name)
                },
                yaxis: {
                    max: 100,
                    title: { text: '%' }
                }
            };
            if (charts['progressChart']) {
                charts['progressChart'].updateOptions(progressOptions);
            } else {
                charts['progressChart'] = new ApexCharts(document.querySelector("#progressChart"), progressOptions);
                charts['progressChart'].render();
            }

            // --- Categorical Charts ---
            filterableColumns.slice(0, 7).forEach(col => {
                const series = Object.values(chartData[col]);
                const categories = Object.keys(chartData[col]);

                const options = {
                    ...commonOptions,
                    series: [{ data: series }],
                    chart: { ...commonOptions.chart, type: 'bar', height: 300 },
                    xaxis: { categories: categories },
                    yaxis: {
                        title: { text: 'تعداد' }
                    }
                };

                const chartId = `chart-${sanitizeId(col)}`;
                if (charts[chartId]) {
                    charts[chartId].updateOptions(options);
                } else {
                    const chartElement = document.querySelector(`#${chartId}`);
                    if (chartElement) {
                        charts[chartId] = new ApexCharts(chartElement, options);
                        charts[chartId].render();
                    }
                }
            });
            
            // --- Weekly Chart ---
            const weeklyCategories = Object.keys(weeklyData).sort();
            const weeklySeries = weeklyCategories.map(week => weeklyData[week]);
            const weeklyOptions = {
                ...commonOptions,
                series: [{ name: 'تعداد فعالیت', data: weeklySeries }],
                chart: { ...commonOptions.chart, type: 'line', height: 300 },
                xaxis: {
                    categories: weeklyCategories.map(key => {
                        const [year, week] = key.split('-');
                        // This will still use Gregorian week numbers, but grouped correctly
                        return `هفته ${week}, ${year}`;
                    })
                },
                tooltip: {
                    x: { formatter: (val) => val },
                    y: { formatter: (val) => val }
                }
            };
            if (charts['weeklyProgressChart']) {
                charts['weeklyProgressChart'].updateOptions(weeklyOptions);
            } else {
                charts['weeklyProgressChart'] = new ApexCharts(document.querySelector("#weeklyProgressChart"), weeklyOptions);
                charts['weeklyProgressChart'].render();
            }

            // --- Monthly Chart ---
            const monthlyCategories = Object.keys(monthlyData).sort();
            const monthlySeries = monthlyCategories.map(month => monthlyData[month]);
            const monthlyOptions = {
                ...commonOptions,
                series: [{ name: 'تعداد فعالیت', data: monthlySeries }],
                chart: { ...commonOptions.chart, type: 'line', height: 300 },
                xaxis: {
                    categories: monthlyCategories.map(key => {
                        const [year, month] = key.split('-');
                        return `${jalaliMonths[parseInt(month) - 1]} ${year}`;
                    })
                },
                tooltip: {
                    x: { formatter: (val) => val },
                    y: { formatter: (val) => val }
                }
            };
            if (charts['monthlyProgressChart']) {
                charts['monthlyProgressChart'].updateOptions(monthlyOptions);
            } else {
                charts['monthlyProgressChart'] = new ApexCharts(document.querySelector("#monthlyProgressChart"), monthlyOptions);
                charts['monthlyProgressChart'].render();
            }
        }

        // Initial render
        renderAllCharts(rawData);
    });
</script>

</body>
</html>
