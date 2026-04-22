/**
 * Dashboard JavaScript
 * Handles real-time clock, AmCharts initialization, and table filtering
 */

// ===== REAL-TIME CLOCK =====
function initializeClock() {
    updateClock();
    setInterval(updateClock, 1000);
}

function updateClock() {
    const now = new Date();
    const timeElement = document.getElementById('current-time');
    if (timeElement) {
        timeElement.textContent = now.toLocaleTimeString('id-ID', { hour12: false });
    }
}

// ===== AMCHARTS INITIALIZATION =====
function initializeCharts() {
    // Ensure am5 is available
    if (typeof am5 === 'undefined') {
        console.error('AmCharts library not loaded');
        return;
    }

    // Get data from data attributes or global variables
    const dataKehadiran = window.chartDataKehadiran || [];
    const hadirHariIni = window.hadirHariIni || 0;
    const terlambatHariIni = window.terlambatHariIni || 0;
    const belumHadir = window.belumHadir || 0;
    const kelasData = window.chartDataKelas || [];

    am5.ready(function() {
        // ===== LINE CHART - KEHADIRAN =====
        if (document.getElementById('chart-kehadiran')) {
            var root1 = am5.Root.new("chart-kehadiran");
            root1.setThemes([am5themes_Animated.new(root1)]);

            var chart1 = root1.container.children.push(am5xy.XYChart.new(root1, {
                panX: false,
                panY: false,
                wheelX: "panX",
                wheelY: "zoomX"
            }));

            var xAxis1 = chart1.xAxes.push(am5xy.DateAxis.new(root1, {
                baseInterval: { timeUnit: "day", count: 1 },
                renderer: am5xy.AxisRendererX.new(root1, {})
            }));

            var yAxis1 = chart1.yAxes.push(am5xy.ValueAxis.new(root1, {
                renderer: am5xy.AxisRendererY.new(root1, {})
            }));

            var series1 = chart1.series.push(am5xy.LineSeries.new(root1, {
                name: "Hadir",
                xAxis: xAxis1,
                yAxis: yAxis1,
                valueYField: "hadir",
                valueXField: "date",
                strokeOpacity: 2,
                tooltip: am5.Tooltip.new(root1, { 
                    labelText: "{name}: {valueY}",
                    background: am5.color(0x0d6efd),
                    textFormat: "bold"
                })
            }));

            series1.strokes.template.setAll({
                strokeWidth: 2,
                stroke: am5.color(0x10b981)
            });

            series1.data.setAll(dataKehadiran);

            var series2 = chart1.series.push(am5xy.LineSeries.new(root1, {
                name: "Terlambat",
                xAxis: xAxis1,
                yAxis: yAxis1,
                valueYField: "telat",
                valueXField: "date",
                strokeOpacity: 2,
                tooltip: am5.Tooltip.new(root1, { 
                    labelText: "{name}: {valueY}",
                    background: am5.color(0xf59e0b),
                    textFormat: "bold"
                })
            }));

            series2.strokes.template.setAll({
                strokeWidth: 2,
                stroke: am5.color(0xf59e0b)
            });

            series2.data.setAll(dataKehadiran);

            chart1.set("cursor", am5xy.XYCursor.new(root1, {
                behavior: "zoomX"
            }));

            // Add legend
            var legend1 = root1.container.children.push(am5.Legend.new(root1, {
                layout: root1.verticalLayout
            }));
            legend1.data.setAll(chart1.series.values);
        }

        // ===== PIE CHART - DISTRIBUSI STATUS =====
        if (document.getElementById('chart-pie')) {
            var root2 = am5.Root.new("chart-pie");
            root2.setThemes([am5themes_Animated.new(root2)]);

            var chart2 = root2.container.children.push(am5percent.PieChart.new(root2, {
                layout: root2.verticalLayout,
                innerRadius: am5.percent(50)
            }));

            var series3 = chart2.series.push(am5percent.PieSeries.new(root2, {
                valueField: "value",
                categoryField: "category"
            }));

            series3.labels.template.set("text", "{category}: {value}");
            series3.ticks.template.set("visible", true);
            series3.slices.template.set("tooltipText", "{category}: {value}");

            series3.data.setAll([
                { 
                    category: "Hadir", 
                    value: hadirHariIni,
                    fill: am5.color(0x10b981)
                },
                { 
                    category: "Terlambat", 
                    value: terlambatHariIni,
                    fill: am5.color(0xf59e0b)
                },
                { 
                    category: "Belum Hadir", 
                    value: belumHadir,
                    fill: am5.color(0xd1d5db)
                }
            ]);

            // Add legend
            var legend2 = root2.container.children.push(am5.Legend.new(root2, {
                layout: root2.verticalLayout,
                y: am5.percent(100)
            }));
            legend2.data.setAll(series3.children.values);
        }

        // ===== BAR CHART - KEHADIRAN PER KELAS =====
        if (document.getElementById('chart-kelas')) {
            var root3 = am5.Root.new("chart-kelas");
            root3.setThemes([am5themes_Animated.new(root3)]);

            var chart3 = root3.container.children.push(am5xy.XYChart.new(root3, {
                panX: false,
                panY: false,
                layout: root3.verticalLayout
            }));

            var yAxis3 = chart3.yAxes.push(am5xy.CategoryAxis.new(root3, {
                categoryField: "kelas",
                renderer: am5xy.AxisRendererY.new(root3, {})
            }));

            var xAxis3 = chart3.xAxes.push(am5xy.ValueAxis.new(root3, {
                min: 0,
                max: 100,
                renderer: am5xy.AxisRendererX.new(root3, {})
            }));

            var series4 = chart3.series.push(am5xy.ColumnSeries.new(root3, {
                name: "Persentase (%)",
                xAxis: xAxis3,
                yAxis: yAxis3,
                valueXField: "persen",
                categoryYField: "kelas",
                tooltip: am5.Tooltip.new(root3, { 
                    labelText: "{categoryY}: {valueX}%",
                    background: am5.color(0x0d6efd),
                    textFormat: "bold"
                })
            }));

            series4.columns.template.setAll({
                strokeOpacity: 0
            });

            series4.columns.template.adapters.add("fill", function(fill, target) {
                const value = target.dataItem.get("valueX");
                if (value >= 80) {
                    return am5.color(0x10b981);
                } else if (value >= 60) {
                    return am5.color(0xf59e0b);
                } else {
                    return am5.color(0xef4444);
                }
            });

            series4.data.setAll(kelasData);
            yAxis3.data.setAll(kelasData);

            // Add legend
            var legend3 = root3.container.children.push(am5.Legend.new(root3, {
                layout: root3.verticalLayout
            }));
            legend3.data.setAll([series4]);
        }
    });
}

// ===== TABLE FILTERING =====
function initializeTableFilter() {
    const searchInput = document.getElementById('search-input');
    const statusFilter = document.getElementById('status-filter');

    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', filterTable);
    }
}

function filterTable() {
    const search = document.getElementById('search-input')?.value.toLowerCase() || '';
    const status = document.getElementById('status-filter')?.value || '';
    const rows = document.querySelectorAll('#absensi-table tbody tr');

    rows.forEach(row => {
        const name = row.cells[0]?.textContent.toLowerCase() || '';
        const rowStatus = row.cells[3]?.textContent.trim() || '';
        
        const matchesSearch = name.includes(search);
        const matchesStatus = !status || rowStatus === status;
        
        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}

// ===== AUTO-REFRESH STAT CARDS (polling tiap 30 detik) =====
function startStatPolling() {
    const basePath = (window.BASE_PATH || '').replace(/\/+$/, '');
    const statsUrl = basePath + '/api/get_stats.php';

    function fetchStats() {
        fetch(statsUrl, { credentials: 'same-origin' })
            .then(function(res) { return res.ok ? res.json() : null; })
            .then(function(data) {
                if (!data) return;
                var elTotal     = document.getElementById('stat-total');
                var elHadir     = document.getElementById('stat-hadir');
                var elTerlambat = document.getElementById('stat-terlambat');
                var elBelum     = document.getElementById('stat-belum-hadir');
                if (elTotal)     elTotal.textContent     = data.total;
                if (elHadir)     elHadir.textContent     = data.hadir;
                if (elTerlambat) elTerlambat.textContent = data.terlambat;
                if (elBelum)     elBelum.textContent     = data.belum;
            })
            .catch(function() { /* silent fail — jangan ganggu UI */ });
    }

    setInterval(fetchStats, 30000); // polling tiap 30 detik
}

// ===== INITIALIZE ON DOM READY =====
document.addEventListener('DOMContentLoaded', function() {
    initializeClock();
    initializeTableFilter();
    startStatPolling();
    
    // Initialize charts after a short delay to ensure AmCharts library is ready
    setTimeout(initializeCharts, 100);
});
