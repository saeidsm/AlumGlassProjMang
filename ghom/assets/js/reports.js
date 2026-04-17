/* Extracted from ghom/reports.php during Phase 2C.
 * Concatenates 1 inline <script> block(s).
 */

// Main function to fetch data and then initialize the dashboard
        async function loadDashboard() {
            const loadingOverlay = document.getElementById('loading-overlay');
            const dashboardGrid = document.querySelector('.dashboard-container');
            console.log("Fetching data from server...");
            
            try {
                const response = await fetch('get_chart_data.php');
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Server responded with status ${response.status}: ${errorText}`);
                }
                const chartData = await response.json();
                console.log("Data successfully received:", chartData);

                // Hide loading overlay and initialize the dashboard
                loadingOverlay.style.opacity = '0';
                setTimeout(() => loadingOverlay.style.display = 'none', 300);
                dashboardGrid.style.visibility = 'visible';
                
                initializeDashboard(chartData);

            } catch (error) {
                console.error("Failed to load or parse chart data:", error);
                loadingOverlay.innerHTML = `
                    <div class="loading-content">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--error); margin-bottom: 1rem;"></i>
                        <div style="font-size: 1.25rem; font-weight: 600; color: var(--error);">خطا در بارگذاری داده‌ها</div>
                        <div style="margin-top: 0.5rem; color: var(--text-secondary);">لطفا صفحه را مجدداً بارگذاری کنید</div>
                    </div>
                `;
            }
        }

        // This function holds all the logic and accepts the data as an argument
        function initializeDashboard(chartData) {
            if (typeof ApexCharts === 'undefined') {
                console.error("ApexCharts library has not loaded.");
                return;
            }

            const {
                allInspectionsData, trendData, stageProgressData,
                flexibleReportData, coverageData, performanceData
            } = chartData;

            let currentlyDisplayedData = [...allInspectionsData];
            let currentSort = { key: 'inspection_date', dir: 'desc' };
            const chartInstances = {};
            let statusColors = {}, trendStatusColors = {}, itemStatusColors = {};

            const domRefs = {
                htmlEl: document.documentElement,
                themeSwitchers: document.querySelectorAll('.theme-switcher'),
                staticKpiContainer: document.getElementById('static-kpi-container'),
                filteredKpiContainer: document.getElementById('filtered-kpi-container'),
                searchInput: document.getElementById('filter-search'),
                typeSelect: document.getElementById('filter-type'),
                statusSelect: document.getElementById('filter-status'),
                startDateEl: document.getElementById('filter-date-start'),
                endDateEl: document.getElementById('filter-date-end'),
                clearFiltersBtn: document.getElementById('clear-filters-btn'),
                tableBody: document.getElementById('dynamic-table-body'),
                resultCountEl: document.getElementById('table-result-count'),
                tableHeaders: document.querySelectorAll('th.sort'),
                dateViewButtons: document.querySelectorAll('.date-view-btn'),
                stageZoneFilter: document.getElementById('stage-filter-zone'),
                stageTypeFilter: document.getElementById('stage-filter-type'),
                stageChartTitle: document.getElementById('stage-chart-title'),
                flexibleBlockFilter: document.getElementById('flexible-filter-block'),
                flexibleContractorFilter: document.getElementById('flexible-filter-contractor'),
                flexibleChartTitle: document.getElementById('flexible-chart-title'),
                overallCoverageValue: document.getElementById('overall-coverage-value'),
                overallCoverageDetails: document.getElementById('overall-coverage-details'),
                performanceViewButtons: document.querySelectorAll('.performance-view-btn'),
                reportZoneSelect: document.getElementById('report-zone-select'),
                reportButtons: document.querySelectorAll('.report-btn'),
            };

            // --- HELPER FUNCTIONS ---
            function getCssVar(varName) { 
                return getComputedStyle(document.documentElement).getPropertyValue(varName).trim(); 
            }
            
            function updateChartColors() {
                statusColors = { 
                    'در انتظار': getCssVar('--secondary'), 
                    'آماده بازرسی اولیه': getCssVar('--primary'), 
                    'منتظر بازرسی مجدد': getCssVar('--warning'), 
                    'نیاز به تعمیر': getCssVar('--warning'), 
                    'تایید شده': getCssVar('--success'), 
                    'رد شده': getCssVar('--error') 
                };
                trendStatusColors = { 
                    'Pending': getCssVar('--secondary'), 
                    'Pre-Inspection Complete': getCssVar('--primary'), 
                    'Awaiting Re-inspection': getCssVar('--warning'), 
                    'Repair': getCssVar('--warning'), 
                    'OK': getCssVar('--success'), 
                    'Reject': getCssVar('--error') 
                };
                itemStatusColors = { 
                    'OK': getCssVar('--success'), 
                    'Not OK': getCssVar('--error'), 
                    'N/A': getCssVar('--secondary') 
                };
            }

            function getBaseChartOptions() {
                const isDark = domRefs.htmlEl.classList.contains('dark');
                return {
                    chart: { 
                        fontFamily: 'Samim', 
                        background: 'transparent', 
                        foreColor: getCssVar('--text-primary'), 
                        toolbar: { 
                            show: true, 
                            tools: { 
                                download: true, 
                                selection: false, 
                                zoom: false, 
                                zoomin: false, 
                                zoomout: false, 
                                pan: false, 
                                reset: false 
                            } 
                        }, 
                        animations: { 
                            enabled: true, 
                            easing: 'easeinout', 
                            speed: 400 
                        } 
                    },
                    theme: { mode: isDark ? 'dark' : 'light' },
                    grid: { borderColor: getCssVar('--border'), strokeDashArray: 3 },
                    tooltip: { theme: isDark ? 'dark' : 'light', style: { fontFamily: 'Samim' } },
                    legend: { 
                        fontFamily: 'Samim', 
                        fontSize: '12px', 
                        position: window.innerWidth < 768 ? 'top' : 'bottom', 
                        horizontalAlign: 'center' 
                    }
                };
            }

            function getEmptyChartOptions(message, type = 'bar') {
                const baseOptions = getBaseChartOptions();
                return {
                    ...baseOptions, 
                    series: [],
                    chart: { ...baseOptions.chart, type: type },
                    noData: { 
                        text: message, 
                        align: 'center', 
                        verticalAlign: 'middle', 
                        style: { 
                            color: getCssVar('--text-secondary'), 
                            fontSize: '14px', 
                            fontFamily: 'Samim' 
                        } 
                    },
                    labels: []
                };
            }

            function renderChart(elementId, options) {
                const element = document.getElementById(elementId);
                if (!element) { 
                    console.warn(`Chart element with ID '${elementId}' not found.`); 
                    return; 
                }
                if (chartInstances[elementId]) chartInstances[elementId].destroy();
                try {
                    chartInstances[elementId] = new ApexCharts(element, options);
                    chartInstances[elementId].render();
                } catch (error) {
                    console.error(`Error rendering chart ${elementId}:`, error);
                    element.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--error);">خطا در نمایش نمودار</div>';
                }
            }
            
            // --- RENDER FUNCTIONS ---
            function renderStaticSection(data) {
                renderKPIs(data, domRefs.staticKpiContainer, false);
                renderDoughnutChart('staticOverallProgressChart', data);
                renderStackedBarChart('staticProgressByTypeChart', data, 'element_type');
            }

            function updateFilteredSection(data) {
                renderKPIs(data, domRefs.filteredKpiContainer, true);
                renderDoughnutChart('filteredStatusChart', data);
                renderStackedBarChart('filteredTypeChart', data, 'element_type');
                renderStackedBarChart('filteredBlockChart', data, 'block');
                renderStackedBarChart('filteredZoneChart', data, 'zone_name');
                domRefs.resultCountEl.textContent = data.length.toLocaleString('fa');
                sortAndRenderTable(data);
            }

            function renderKPIs(data, container, isFiltered) {
                const kpi = data.reduce((acc, item) => {
                    acc.total++;
                    if (item.final_status === 'تایید شده') acc.ok++;
                    else if (item.final_status === 'آماده بازرسی اولیه') acc.ready++;
                    else if (['رد شده', 'نیاز به تعمیر'].includes(item.final_status)) acc.issues++;
                    return acc;
                }, { total: 0, ok: 0, ready: 0, issues: 0 });
                
                container.innerHTML = `
                    <div class="kpi-card total fade-in">
                        <h3>کل ${isFiltered ? '(فیلتر شده)' : ''}</h3>
                        <p class="value">${kpi.total.toLocaleString('fa')}</p>
                    </div>
                    <div class="kpi-card ok fade-in">
                        <h3>تایید شده</h3>
                        <p class="value">${kpi.ok.toLocaleString('fa')}</p>
                    </div>
                    <div class="kpi-card ready fade-in">
                        <h3>آماده بازرسی</h3>
                        <p class="value">${kpi.ready.toLocaleString('fa')}</p>
                    </div>
                    <div class="kpi-card issues fade-in">
                        <h3>دارای ایراد</h3>
                        <p class="value">${kpi.issues.toLocaleString('fa')}</p>
                    </div>
                `;
            }

            function renderTable(data) {
                domRefs.tableBody.innerHTML = data.length === 0 ?
                    '<tr><td colspan="9" style="text-align:center; padding: 20px;">هیچ رکوردی یافت نشد.</td></tr>' :
                    data.map(row => `
                        <tr>
                            <td>${row.element_id}</td>
                            <td>${row.part_name}</td>
                            <td>${row.element_type}</td>
                            <td>${row.zone_name}</td>
                            <td>${row.block}</td>
                            <td><span class="status-badge" style="background-color:${statusColors[row.final_status] || getCssVar('--secondary')};">${row.final_status}</span></td>
                            <td>${row.inspector}</td>
                            <td>${row.inspection_date}</td>
                            <td>${row.contractor_days_passed}</td>
                        </tr>
                    `).join('');
            }

            function renderDoughnutChart(chartId, data) {
                const counts = data.reduce((acc, item) => { 
                    acc[item.final_status] = (acc[item.final_status] || 0) + 1; 
                    return acc; 
                }, {});
                const series = Object.values(counts);
                const labels = Object.keys(counts);
                
                if (series.length === 0) { 
                    renderChart(chartId, getEmptyChartOptions('داده‌ای برای نمایش وجود ندارد', 'donut')); 
                    return; 
                }
                
                const options = { 
                    ...getBaseChartOptions(), 
                    series, 
                    labels, 
                    chart: { ...getBaseChartOptions().chart, type: 'donut' }, 
                    colors: labels.map(status => statusColors[status]), 
                    plotOptions: { pie: { donut: { size: '65%' } } }, 
                    dataLabels: { enabled: true, formatter: (val) => Math.round(val) + "%" } 
                };
                renderChart(chartId, options);
            }

            function renderStackedBarChart(chartId, data, groupBy) {
                const grouped = data.reduce((acc, item) => {
                    const key = item[groupBy] || 'نامشخص';
                    if (!acc[key]) acc[key] = {};
                    acc[key][item.final_status] = (acc[key][item.final_status] || 0) + 1;
                    return acc;
                }, {});
                
                const labels = Object.keys(grouped).sort();
                if (labels.length === 0) { 
                    renderChart(chartId, getEmptyChartOptions('داده‌ای برای نمایش وجود ندارد', 'bar')); 
                    return; 
                }
                
                const series = Object.keys(statusColors).map(status => ({ 
                    name: status, 
                    data: labels.map(label => grouped[label][status] || 0) 
                })).filter(s => s.data.some(d => d > 0));
                
                const options = { 
                    ...getBaseChartOptions(), 
                    series, 
                    chart: { ...getBaseChartOptions().chart, type: 'bar', stacked: true }, 
                    xaxis: { 
                        categories: labels, 
                        labels: { style: { fontFamily: 'Samim' } } 
                    }, 
                    colors: series.map(s => statusColors[s.name]), 
                    plotOptions: { bar: { horizontal: false, columnWidth: '60%' } }, 
                    dataLabels: { enabled: false } 
                };
                renderChart(chartId, options);
            }

            function renderCoverageCharts(data) {
                const overall = data.overall;
                const percentage = overall.total > 0 ? ((overall.inspected / overall.total) * 100).toFixed(1) : 0;
                domRefs.overallCoverageValue.textContent = `${percentage}%`;
                domRefs.overallCoverageDetails.textContent = `${overall.inspected.toLocaleString('fa')} از ${overall.total.toLocaleString('fa')} المان`;
                renderCoverageBarChart('coverageByZoneChart', data.by_zone);
                renderCoverageBarChart('coverageByBlockChart', data.by_block);
            }

            function renderCoverageBarChart(chartId, data) {
                const labels = Object.keys(data).sort();
                if (labels.length === 0) { 
                    renderChart(chartId, getEmptyChartOptions('داده‌ای برای نمایش وجود ندارد', 'bar')); 
                    return; 
                }
                
                const series = [
                    { name: 'المان‌های کل', data: labels.map(l => data[l].total) }, 
                    { name: 'المان‌های بازرسی شده', data: labels.map(l => data[l].inspected) }
                ];
                
                const options = { 
                    ...getBaseChartOptions(), 
                    series, 
                    chart: { ...getBaseChartOptions().chart, type: 'bar' }, 
                    colors: [getCssVar('--secondary') + '80', getCssVar('--primary')], 
                    xaxis: { categories: labels }, 
                    plotOptions: { bar: { horizontal: false, columnWidth: '55%' } }, 
                    dataLabels: { enabled: false } 
                };
                renderChart(chartId, options);
            }
            
            function renderTrendChart(view) {
                const dataForView = trendData[view] || {};
                const labels = Object.keys(dataForView);
                if (labels.length === 0) { 
                    renderChart('dateTrendChart', getEmptyChartOptions('داده‌ای برای این بازه زمانی وجود ندارد', 'area')); 
                    return; 
                }
                
                const series = Object.keys(trendStatusColors).map(status => ({ 
                    name: status, 
                    data: labels.map(label => dataForView[label]?.[status] || 0) 
                })).filter(s => s.data.some(d => d > 0));
                
                const options = { 
                    ...getBaseChartOptions(), 
                    series, 
                    chart: { ...getBaseChartOptions().chart, type: 'area', stacked: true }, 
                    colors: series.map(s => trendStatusColors[s.name]), 
                    xaxis: { type: 'category', categories: labels }, 
                    stroke: { curve: 'smooth', width: 2 }, 
                    fill: { 
                        type: 'gradient', 
                        gradient: { opacityFrom: 0.6, opacityTo: 0.2 } 
                    }, 
                    dataLabels: { enabled: false } 
                };
                renderChart('dateTrendChart', options);
            }

            function renderStageProgressChart() {
                const zone = domRefs.stageZoneFilter.value;
                const type = domRefs.stageTypeFilter.value;
                
                if (!zone || !type) { 
                    domRefs.stageChartTitle.textContent = 'برای مشاهده نمودار، یک زون و نوع المان انتخاب کنید'; 
                    renderChart('stageProgressChart', getEmptyChartOptions('لطفا از فیلترها انتخاب کنید', 'bar')); 
                    return; 
                }
                
                const dataForChart = stageProgressData[zone]?.[type];
                if (!dataForChart || Object.keys(dataForChart).length === 0) { 
                    domRefs.stageChartTitle.textContent = `داده‌ای برای ${type} در ${zone} یافت نشد`; 
                    renderChart('stageProgressChart', getEmptyChartOptions('داده‌ای یافت نشد', 'bar')); 
                    return; 
                }
                
                domRefs.stageChartTitle.textContent = `پیشرفت مراحل برای ${type} در ${zone}`;
                const labels = Object.keys(dataForChart);
                const series = Object.keys(itemStatusColors).map(status => ({ 
                    name: status, 
                    data: labels.map(stage => dataForChart[stage]?.[status] || 0) 
                })).filter(s => s.data.some(d => d > 0));
                
                const options = { 
                    ...getBaseChartOptions(), 
                    series, 
                    chart: { ...getBaseChartOptions().chart, type: 'bar', stacked: true }, 
                    xaxis: { categories: labels }, 
                    colors: series.map(s => itemStatusColors[s.name]), 
                    plotOptions: { bar: { horizontal: false, columnWidth: '55%' } }, 
                    dataLabels: { enabled: false } 
                };
                renderChart('stageProgressChart', options);
            }
            
            function renderFlexibleReportChart() {
                const block = domRefs.flexibleBlockFilter.value;
                const contractor = domRefs.flexibleContractorFilter.value;
                
                if (!block || !contractor) { 
                    domRefs.flexibleChartTitle.textContent = 'برای مشاهده نمودار، یک بلوک و پیمانکار انتخاب کنید'; 
                    renderChart('flexibleReportChart', getEmptyChartOptions('لطفا از فیلترها انتخاب کنید', 'bar')); 
                    return; 
                }
                
                const dataForChart = flexibleReportData[block]?.[contractor];
                if (!dataForChart || Object.keys(dataForChart).length === 0) { 
                    domRefs.flexibleChartTitle.textContent = `داده‌ای برای پیمانکار ${contractor} در بلوک ${block} یافت نشد`; 
                    renderChart('flexibleReportChart', getEmptyChartOptions('داده‌ای یافت نشد', 'bar')); 
                    return; 
                }
                
                domRefs.flexibleChartTitle.textContent = `وضعیت المان‌ها برای پیمانکار ${contractor} در بلوک ${block}`;
                const labels = Object.keys(dataForChart);
                const series = Object.keys(statusColors).map(status => ({ 
                    name: status, 
                    data: labels.map(type => dataForChart[type]?.[status] || 0) 
                })).filter(s => s.data.some(d => d > 0));
                
                const options = { 
                    ...getBaseChartOptions(), 
                    series, 
                    chart: { ...getBaseChartOptions().chart, type: 'bar', stacked: true }, 
                    xaxis: { categories: labels }, 
                    colors: series.map(s => statusColors[s.name]), 
                    plotOptions: { bar: { horizontal: false, columnWidth: '55%' } }, 
                    dataLabels: { enabled: false } 
                };
                renderChart('flexibleReportChart', options);
            }

            function renderPerformanceCharts(view = 'daily') {
                if (!performanceData || !performanceData.inspectors) return;
                
                ['inspector', 'contractor'].forEach(entity => {
                    const chartId = `${entity}PerformanceChart`;
                    const data = performanceData[`${entity}s`][view] || {};
                    const labels = Object.keys(data).sort();
                    
                    if (labels.length === 0) { 
                        renderChart(chartId, getEmptyChartOptions('داده ای نیست', 'bar')); 
                        return; 
                    }
                    
                    const allEntities = [...new Set(Object.values(data).flatMap(Object.keys))].sort();
                    const series = allEntities.map(name => ({ 
                        name: name, 
                        data: labels.map(label => data[label]?.[name] || 0) 
                    }));
                    
                    const colors = allEntities.map((_, i) => `hsl(${(entity === 'inspector' ? i * 40 : 180 + i * 40) % 360}, 70%, 60%)`);
                    const options = { 
                        ...getBaseChartOptions(), 
                        series, 
                        colors, 
                        chart: { ...getBaseChartOptions().chart, type: 'bar', stacked: true }, 
                        xaxis: { categories: labels }, 
                        plotOptions: { bar: { horizontal: false, columnWidth: '55%' } }, 
                        dataLabels: { enabled: false } 
                    };
                    renderChart(chartId, options);
                });
            }

            // --- SETUP FUNCTIONS ---
            function setupFilters() {
                const elementTypes = [...new Set(allInspectionsData.map(item => item.element_type))].filter(Boolean).sort();
                const statuses = [...new Set(allInspectionsData.map(item => item.final_status))].filter(Boolean).sort();
                elementTypes.forEach(type => domRefs.typeSelect.add(new Option(type, type)));
                statuses.forEach(status => domRefs.statusSelect.add(new Option(status, status)));
            }

            function setupStageFilters() {
                const zones = Object.keys(stageProgressData).sort();
                domRefs.stageZoneFilter.innerHTML = '<option value="">ابتدا یک زون انتخاب کنید</option>';
                zones.forEach(zone => domRefs.stageZoneFilter.add(new Option(zone, zone)));
            }
            
            function setupFlexibleReportFilters() {
                const blocks = Object.keys(flexibleReportData).sort();
                domRefs.flexibleBlockFilter.innerHTML = '<option value="">ابتدا یک بلوک انتخاب کنید</option>';
                blocks.forEach(block => domRefs.flexibleBlockFilter.add(new Option(block, block)));
            }
            
            function applyAllFilters() {
                const search = domRefs.searchInput.value.toLowerCase();
                const type = domRefs.typeSelect.value;
                const status = domRefs.statusSelect.value;
                const startDate = (domRefs.startDateEl.datepicker && domRefs.startDateEl.value) ? new Date(domRefs.startDateEl.datepicker.gDate).getTime() : 0;
                const endDate = (domRefs.endDateEl.datepicker && domRefs.endDateEl.value) ? new Date(domRefs.endDateEl.datepicker.gDate).setHours(23, 59, 59, 999) : Infinity;
                
                currentlyDisplayedData = allInspectionsData.filter(item => {
                    const itemDate = item.inspection_date_raw ? new Date(item.inspection_date_raw).getTime() : 0;
                    const matchesDate = !startDate && !isFinite(endDate) ? true : (itemDate >= startDate && itemDate <= endDate);
                    const matchesType = !type || item.element_type === type;
                    const matchesStatus = !status || item.final_status === status;
                    const matchesSearch = !search || Object.values(item).some(val => String(val).toLowerCase().includes(search));
                    return matchesDate && matchesType && matchesStatus && matchesSearch;
                });
                updateFilteredSection(currentlyDisplayedData);
            }

            function sortAndRenderTable(dataToSort) {
                const { key, dir } = currentSort;
                const direction = dir === 'asc' ? 1 : -1;
                const sortedData = [...dataToSort].sort((a, b) => {
                    let valA = a[key], valB = b[key];
                    if (key === 'inspection_date') { 
                        valA = a.inspection_date_raw ? new Date(a.inspection_date_raw).getTime() : 0; 
                        valB = b.inspection_date_raw ? new Date(b.inspection_date_raw).getTime() : 0; 
                    }
                    if (valA == null || valA === '---' || valA === 'N/A') return 1 * direction;
                    if (valB == null || valB === '---' || valB === 'N/A') return -1 * direction;
                    if (typeof valA === 'string') { 
                        return valA.localeCompare(valB, 'fa') * direction; 
                    }
                    return (valA < valB ? -1 : valA > valB ? 1 : 0) * direction;
                });
                renderTable(sortedData);
            }

            // --- EVENT LISTENERS ---
            function setupEventListeners() {
                domRefs.themeSwitchers.forEach(switcher => {
                    switcher.addEventListener('click', () => {
                        domRefs.htmlEl.classList.toggle('dark');
                        const isDark = domRefs.htmlEl.classList.contains('dark');
                        
                        // Update all theme switcher icons
                        domRefs.themeSwitchers.forEach(sw => {
                            const icon = sw.querySelector('i');
                            icon.className = isDark ? 'fas fa-moon' : 'fas fa-sun';
                        });
                        
                        setTimeout(() => {
                            updateChartColors();
                            // Re-render all charts
                            renderStaticSection(allInspectionsData);
                            renderCoverageCharts(coverageData);
                            renderTrendChart(document.querySelector('.date-view-btn.active').dataset.view);
                            updateFilteredSection(currentlyDisplayedData);
                            renderStageProgressChart();
                            renderFlexibleReportChart();
                            if (performanceData && performanceData.inspectors) {
                                const activePerformanceView = document.querySelector('.performance-view-btn.active');
                                renderPerformanceCharts(activePerformanceView ? activePerformanceView.dataset.view : 'daily');
                            }
                        }, 100);
                    });
                });

                ['input', 'change'].forEach(evt => {
                    domRefs.searchInput.addEventListener(evt, applyAllFilters);
                    domRefs.typeSelect.addEventListener(evt, applyAllFilters);
                    domRefs.statusSelect.addEventListener(evt, applyAllFilters);
                });

                domRefs.clearFiltersBtn.addEventListener('click', () => {
                    domRefs.searchInput.value = '';
                    domRefs.typeSelect.value = '';
                    domRefs.statusSelect.value = '';
                    domRefs.startDateEl.value = '';
                    domRefs.endDateEl.value = '';
                    applyAllFilters();
                });
                
                domRefs.tableHeaders.forEach(header => {
                    header.addEventListener('click', () => {
                        const key = header.dataset.sort;
                        currentSort.dir = (currentSort.key === key && currentSort.dir === 'desc') ? 'asc' : 'desc';
                        currentSort.key = key;
                        domRefs.tableHeaders.forEach(th => th.classList.remove('asc', 'desc'));
                        header.classList.add(currentSort.dir);
                        sortAndRenderTable(currentlyDisplayedData);
                    });
                });

                domRefs.dateViewButtons.forEach(btn => btn.addEventListener('click', (e) => {
                    domRefs.dateViewButtons.forEach(b => b.classList.remove('active'));
                    e.target.classList.add('active');
                    renderTrendChart(e.target.dataset.view);
                }));

                if (domRefs.performanceViewButtons.length > 0) {
                    domRefs.performanceViewButtons.forEach(btn => btn.addEventListener('click', (e) => {
                        domRefs.performanceViewButtons.forEach(b => b.classList.remove('active'));
                        e.target.classList.add('active');
                        renderPerformanceCharts(e.target.dataset.view);
                    }));
                }

                domRefs.stageZoneFilter.addEventListener('change', () => {
                    const selectedZone = domRefs.stageZoneFilter.value;
                    domRefs.stageTypeFilter.innerHTML = '<option value="">-</option>';
                    domRefs.stageTypeFilter.disabled = true;
                    
                    if (selectedZone && stageProgressData[selectedZone]) {
                        const types = Object.keys(stageProgressData[selectedZone]).sort();
                        domRefs.stageTypeFilter.innerHTML = '<option value="">نوع المان را انتخاب کنید</option>';
                        types.forEach(type => domRefs.stageTypeFilter.add(new Option(type, type)));
                        domRefs.stageTypeFilter.disabled = false;
                    }
                    renderStageProgressChart();
                });
                
                domRefs.stageTypeFilter.addEventListener('change', renderStageProgressChart);
                
                domRefs.flexibleBlockFilter.addEventListener('change', () => {
                    const selectedBlock = domRefs.flexibleBlockFilter.value;
                    domRefs.flexibleContractorFilter.innerHTML = '<option value="">-</option>';
                    domRefs.flexibleContractorFilter.disabled = true;
                    
                    if (selectedBlock && flexibleReportData[selectedBlock]) {
                        const contractors = Object.keys(flexibleReportData[selectedBlock]).sort();
                        domRefs.flexibleContractorFilter.innerHTML = '<option value="">پیمانکار را انتخاب کنید</option>';
                        contractors.forEach(c => domRefs.flexibleContractorFilter.add(new Option(c, c)));
                        domRefs.flexibleContractorFilter.disabled = false;
                    }
                    renderFlexibleReportChart();
                });
                
                domRefs.flexibleContractorFilter.addEventListener('change', renderFlexibleReportChart);

                domRefs.reportButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const planFile = domRefs.reportZoneSelect.value;
                        if (!planFile) { 
                            alert('لطفا ابتدا یک فایل نقشه را انتخاب کنید.'); 
                            return; 
                        }
                        const statusToHighlight = this.dataset.status;
                        let url = `/ghom/viewer.php?plan=${encodeURIComponent(planFile)}`;
                        if (statusToHighlight !== 'all') { 
                            url += `&highlight_status=${encodeURIComponent(statusToHighlight)}`; 
                        }
                        window.open(url, '_blank');
                    });
                });

                if (typeof jalaliDatepicker !== 'undefined') {
                    jalaliDatepicker.startWatch({ 
                        selector: '[data-jdp]', 
                        autoHide: true, 
                        onSelect: applyAllFilters 
                    });
                }
            }

            // --- INITIALIZE THE DASHBOARD ---
            console.log("Initializing dashboard with received data...");
            updateChartColors();
            setupFilters();
            setupStageFilters();
            setupFlexibleReportFilters();
            setupEventListeners();
            
            // Initial render of all components
            renderStaticSection(allInspectionsData);
            renderCoverageCharts(coverageData);
            renderTrendChart('daily');
            updateFilteredSection(allInspectionsData);
            renderStageProgressChart();
            renderFlexibleReportChart();
            
            if (performanceData && performanceData.inspectors) {
                renderPerformanceCharts('daily');
            }

            // Add fade-in animation to sections
            setTimeout(() => {
                const sections = document.querySelectorAll('.section-container');
                sections.forEach((section, index) => {
                    setTimeout(() => {
                        section.classList.add('fade-in');
                    }, index * 100);
                });
            }, 200);
        }
function openViewer(allStatuses = false) {
        const planFile = document.getElementById('report-plan-select').value;
        if (!planFile) {
            alert('لطفا ابتدا یک نقشه را انتخاب کنید.');
            return;
        }
        let url = `/ghom/viewer.php?plan=${encodeURIComponent(planFile)}`;
        if (!allStatuses) {
            const status = this.dataset.status;
            url += `&status=${encodeURIComponent(status)}`;
        }
        window.open(url, '_blank');
    }

    document.querySelectorAll('.report-btn').forEach(button => button.addEventListener('click', openViewer.bind(button, false)));
    document.getElementById('open-viewer-btn-all').addEventListener('click', openViewer.bind(null, true));
        // Start the entire process when the DOM is ready
        document.addEventListener('DOMContentLoaded', loadDashboard);
