<?php require_once 'config.php'; requireLogin(); requirePermission('view_dashboard'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard - NAM Supply</title>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root { --bg: #f8f9fa; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); padding-bottom: 50px; }
        .dashboard-container { max-width: 1600px; margin: 20px auto; padding: 0 20px; }
        
        /* KPI Cards */
        .kpi-card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .kpi-value { font-size: 28px; font-weight: 700; margin-top: 5px; color: #1e293b; }
        .kpi-label { font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase; }
        
        /* Charts */
        .chart-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); height: 100%; border: none; }
        .chart-title { font-size: 16px; font-weight: 700; color: #334155; display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        
        /* Filter Bar */
        .filter-bar { background: white; padding: 15px 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px; }
        
        /* Drill Tags */
        .drill-tag {
            background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe;
            padding: 5px 12px; border-radius: 20px; font-size: 13px; font-weight: 600;
            display: inline-flex; align-items: center; gap: 8px; margin-right: 8px;
            cursor: default;
        }
        .drill-tag i.fa-times { cursor: pointer; color: #3b82f6; transition: color 0.2s; }
        .drill-tag i.fa-times:hover { color: #1d4ed8; }

        /* Matrix Table */
        .matrix-table th { font-size: 12px; text-transform: uppercase; color: #64748b; background: #f8fafc; padding: 12px; }
        .matrix-table td { font-size: 14px; padding: 12px; border-bottom: 1px solid #f1f5f9; }
        .category-row { font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .category-row:hover { background: #f1f5f9; }
        
        /* Horizontal Scroll for Company Chart */
        .scrollable-chart-wrapper { width: 100%; overflow-x: auto; padding-bottom: 10px; }
        #companyChartContainer { min-height: 400px; position: relative; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">

        <div class="filter-bar">
            <div class="d-flex flex-wrap gap-3 align-items-end justify-content-between mb-3">
                <div class="d-flex gap-3 align-items-end flex-wrap">
                    <div>
                        <label class="small fw-bold text-muted d-block mb-1">Time Period</label>
                        <select id="periodFilter" class="form-select form-select-sm" style="width: 160px;" onchange="handlePeriodChange()">
                            <option value="today">Daily (Today)</option>
                            <option value="week">Weekly (This Week)</option>
                            <option value="month" selected>Monthly (This Month)</option>
                            <option value="quarter">Quarterly (This Year)</option>
                            <option value="year">Yearly (This Year)</option>
                            <option value="custom_month">Specific Month</option>
                        </select>
                    </div>
                    <div id="monthPickerGroup" style="display:none;">
                        <label class="small fw-bold text-muted d-block mb-1">Select Month</label>
                        <input type="month" id="monthPicker" class="form-control form-control-sm" onchange="updateDashboard()">
                    </div>
                    <div>
                        <label class="small fw-bold text-muted d-block mb-1">Min Target</label>
                        <input type="number" id="minTarget" class="form-control form-control-sm" value="100000" step="10000" style="width: 110px;">
                    </div>
                    <div>
                        <label class="small fw-bold text-muted d-block mb-1">Max Target</label>
                        <input type="number" id="maxTarget" class="form-control form-control-sm" value="200000" step="10000" style="width: 110px;">
                    </div>
                    <div class="d-flex align-items-end pb-1">
                         <button onclick="updateDashboard()" class="btn btn-primary btn-sm fw-bold px-3 ms-2">Apply</button>
                    </div>
                </div>
            </div>
            
            <div id="activeFiltersArea" class="d-flex flex-wrap align-items-center" style="min-height: 30px;">
                <span class="small text-muted me-2"><i class="fas fa-filter me-1"></i>Active Filters:</span>
                <span id="drillTags"></span>
                <button onclick="resetAllDrills()" class="btn btn-link btn-sm text-muted text-decoration-none py-0 small" id="resetBtn" style="display:none;">Reset All</button>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="kpi-card p-3 bg-white h-100">
                    <div class="kpi-label">Total Revenue</div>
                    <div class="kpi-value" id="totalSales">₱0.00</div>
                    <div class="mt-2" id="growthBadge"></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card p-3 bg-white h-100">
                    <div class="kpi-label">Net Profit</div>
                    <div class="kpi-value text-success" id="totalProfit">₱0.00</div>
                    <div class="small text-muted mt-1">Margin: <span class="fw-bold" id="profitMargin">0%</span></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card p-3 bg-white h-100">
                    <div class="kpi-label">Avg. Order Value</div>
                    <div class="kpi-value text-info" id="avgOrder">₱0.00</div>
                    <div class="small text-muted mt-1" id="orderCount">0 Orders</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card p-3 bg-white h-100">
                    <div class="kpi-label">Stock Alerts</div>
                    <div class="kpi-value text-danger" id="lowStockCount">0</div>
                    <a href="products.php" class="small text-decoration-none text-danger mt-1 d-block">View Inventory &rarr;</a>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="chart-title">
                        <span><i class="fas fa-chart-line text-primary me-2"></i>Sales Performance</span>
                        <small class="text-muted fw-normal" style="font-size:11px;">Min/Max Targets Active</small>
                    </div>
                    <div style="height: 320px;">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-title">
                        <span><i class="fas fa-truck text-success me-2"></i>Logistics Status</span>
                    </div>
                    <div style="height: 320px; position:relative;">
                        <canvas id="deliveryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="chart-title">
                        <span><i class="fas fa-building text-info me-2"></i>Company Sales Performance</span>
                        <small class="text-muted fw-normal">Scroll right if needed • Click bar to filter</small>
                    </div>
                    <div class="scrollable-chart-wrapper">
                        <div id="companyChartContainer">
                            <canvas id="companyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-title">
                        <span><i class="fas fa-file-invoice-dollar text-secondary me-2"></i>Revenue by Terms</span>
                    </div>
                    <div style="height: 320px; position:relative;">
                        <canvas id="termsChart"></canvas>
                    </div>
                    <div class="text-center text-muted small mt-2">Cash Flow Impact</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-title">
                        <span><i class="fas fa-chart-pie text-warning me-2"></i>Sales by Category</span>
                        <small class="text-muted fw-normal" style="font-size:11px;">Click to Filter</small>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-title">
                        <span><i class="fas fa-crown text-warning me-2"></i>Top 10 Products</span>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="topProductsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-title">
                        <span><i class="fas fa-tags text-danger me-2"></i>Top 5 Suppliers (Cost)</span>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="supplierChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-table me-2"></i>Detailed Sales Matrix</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 matrix-table">
                        <thead>
                            <tr>
                                <th width="40%">Category / Item</th>
                                <th width="20%" class="text-end">Qty Sold</th>
                                <th width="20%" class="text-end">Total Revenue</th>
                                <th width="20%" class="text-end">Net Profit</th>
                            </tr>
                        </thead>
                        <tbody id="matrixBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script>
    const formatMoney = (num) => '₱' + parseFloat(num).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const formatLarge = (num) => {
        if(num >= 1000000) return '₱' + (num/1000000).toFixed(2) + 'M';
        if(num >= 1000) return '₱' + (num/1000).toFixed(0) + 'K';
        return formatMoney(num);
    };

    // --- PROFESSIONAL COLOR PALETTE ---
    const colors = {
        primary:   '#4f46e5', // Indigo
        success:   '#10b981', // Emerald
        warning:   '#f59e0b', // Amber
        danger:    '#ef4444', // Red
        info:      '#0ea5e9', // Sky
        purple:    '#8b5cf6', // Violet
        pink:      '#ec4899', // Pink
        orange:    '#f97316', // Orange
        teal:      '#14b8a6', // Teal
        slate:     '#64748b', // Slate
        
        // Transparent palette for Polar Area
        transparentPalette: [
            'rgba(79, 70, 229, 0.6)', 
            'rgba(16, 185, 129, 0.6)', 
            'rgba(245, 158, 11, 0.6)', 
            'rgba(239, 68, 68, 0.6)', 
            'rgba(14, 165, 233, 0.6)', 
            'rgba(139, 92, 246, 0.6)'
        ],
        // Solid palette for others
        palette: [
            '#4f46e5', '#10b981', '#f59e0b', '#ef4444', 
            '#0ea5e9', '#8b5cf6', '#ec4899', '#f97316', 
            '#14b8a6', '#6366f1'
        ]
    };

    // --- STATE ---
    let activeDrills = { company: null, category: null };
    let charts = {}; 

    function handlePeriodChange() {
        const period = document.getElementById('periodFilter').value;
        const pickerGroup = document.getElementById('monthPickerGroup');
        const picker = document.getElementById('monthPicker');
        
        if (period === 'custom_month') {
            pickerGroup.style.display = 'block';
            if (!picker.value) {
                const now = new Date();
                picker.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2,'0')}`;
            }
        } else {
            pickerGroup.style.display = 'none';
        }
        updateDashboard();
    }

    function toggleDrill(type, value) {
        if (activeDrills[type] === value) activeDrills[type] = null;
        else activeDrills[type] = value;
        renderDrillTags();
        updateDashboard();
    }

    function resetAllDrills() {
        activeDrills = { company: null, category: null };
        renderDrillTags();
        updateDashboard();
    }

    function renderDrillTags() {
        const container = document.getElementById('drillTags');
        const resetBtn = document.getElementById('resetBtn');
        let html = '';
        let hasActive = false;

        if (activeDrills.company) {
            html += `<span class="drill-tag"><i class="fas fa-building"></i> ${activeDrills.company} <i class="fas fa-times ms-1" onclick="toggleDrill('company', '${activeDrills.company}')"></i></span>`;
            hasActive = true;
        }
        if (activeDrills.category) {
            html += `<span class="drill-tag"><i class="fas fa-chart-pie"></i> ${activeDrills.category} <i class="fas fa-times ms-1" onclick="toggleDrill('category', '${activeDrills.category}')"></i></span>`;
            hasActive = true;
        }
        
        container.innerHTML = html;
        resetBtn.style.display = hasActive ? 'inline-block' : 'none';
    }

    async function updateDashboard() {
        await fetchSalesData();
        await fetchInventoryData();
    }

    async function fetchInventoryData() {
        try {
            const res = await fetch('get_all_products.php');
            const products = await res.json();
            const lowStock = products.filter(p => parseInt(p.current_stock || 0) <= parseInt(p.reorder_level || 10));
            document.getElementById('lowStockCount').textContent = lowStock.length;
        } catch(e) { console.error("Inventory error:", e); }
    }

    async function fetchSalesData() {
        const period = document.getElementById('periodFilter').value;
        const now = new Date();
        const today = now.toISOString().split('T')[0];
        let start = '', end = today, groupBy = 'day';

        if (period === 'today') start = today;
        else if (period === 'week') { const d = new Date(now); d.setDate(d.getDate() - d.getDay()); start = d.toISOString().split('T')[0]; }
        else if (period === 'month') start = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
        else if (period === 'quarter') { start = new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0]; groupBy = 'quarter'; }
        else if (period === 'year') { start = new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0]; groupBy = 'month'; }
        else if (period === 'custom_month') {
            const val = document.getElementById('monthPicker').value;
            if (val) { const [y, m] = val.split('-'); start = `${y}-${m}-01`; end = new Date(y, m, 0).toISOString().split('T')[0]; }
        } else groupBy = 'year';

        let url = `api.php?start_date=${start}&end_date=${end}&group_by=${groupBy}`;
        if(activeDrills.company) url += `&company=${encodeURIComponent(activeDrills.company)}`;
        if(activeDrills.category) url += `&category=${encodeURIComponent(activeDrills.category)}`;

        try {
            const res = await fetch(url);
            const data = await res.json();

            // KPIs
            document.getElementById('totalSales').textContent = formatLarge(data.stats.total_sales);
            document.getElementById('totalProfit').textContent = formatLarge(data.stats.total_profit);
            document.getElementById('profitMargin').textContent = data.stats.profit_margin.toFixed(1) + '%';
            document.getElementById('avgOrder').textContent = formatMoney(data.stats.avg_order_value);
            document.getElementById('orderCount').textContent = `${data.stats.total_orders} Orders`;

            // Growth
            const growth = data.stats.growth_sales || 0;
            const growthBadge = document.getElementById('growthBadge');
            if (period === 'custom_month' || period === 'month' || period === 'year') {
                const icon = growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                const cls = growth >= 0 ? 'trend-up' : 'trend-down';
                growthBadge.innerHTML = `<span class="badge ${cls} text-dark"><i class="fas ${icon}"></i> ${Math.abs(growth).toFixed(1)}% vs prev</span>`;
            } else growthBadge.innerHTML = '';

            // Render Charts
            renderDailyChart(data.chart_data);
            renderCategoryChart(data.category_matrix);
            renderDeliveryChart(data.delivery_stats);
            renderSupplierChart(data.supplier_costs);
            renderCompanyChart(data.company_sales);
            renderTermsChart(data.payment_terms);
            renderTopProducts(data.top_products);
            renderMatrix(data.category_matrix);

        } catch (err) { console.error("Sales data error:", err); }
    }

    // --- CHART FUNCTIONS ---
    function renderDailyChart(data) {
        const ctx = document.getElementById('dailyChart').getContext('2d');
        const minTarget = parseFloat(document.getElementById('minTarget').value) || 100000;
        const maxTarget = parseFloat(document.getElementById('maxTarget').value) || 150000;

        if (charts.daily) charts.daily.destroy();

        charts.daily = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.label),
                datasets: [
                    { type: 'line', label: 'Max', data: Array(data.length).fill(maxTarget), borderColor: '#166534', borderWidth: 2, borderDash: [10, 5], pointRadius: 0, order: 0 },
                    { type: 'line', label: 'Min', data: Array(data.length).fill(minTarget), borderColor: '#dc2626', borderWidth: 2, borderDash: [2, 2], pointRadius: 0, order: 1 },
                    { type: 'bar', label: 'Revenue', data: data.map(d => d.sales), backgroundColor: colors.primary, borderRadius: 4, order: 2 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { borderDash: [5, 5] } }, x: { grid: { display: false } } }
            }
        });
    }

    function renderDeliveryChart(data) {
        const ctx = document.getElementById('deliveryChart').getContext('2d');
        if (charts.delivery) charts.delivery.destroy();

        charts.delivery = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Delivered', 'Pending'],
                datasets: [{
                    data: [data.delivered, data.pending],
                    backgroundColor: [colors.success, colors.warning],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } }
            }
        });
    }
    
    // --- UPDATED: POLAR AREA CHART FOR REVENUE ---
    function renderTermsChart(data) {
        const ctx = document.getElementById('termsChart').getContext('2d');
        if (charts.terms) charts.terms.destroy();

        charts.terms = new Chart(ctx, {
            type: 'polarArea',
            data: {
                labels: data.map(d => d.term),
                datasets: [{
                    data: data.map(d => d.sales), // Use SALES, not count
                    backgroundColor: colors.transparentPalette,
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    r: { ticks: { display: false }, grid: { color: '#e5e7eb' } }
                },
                plugins: { 
                    legend: { position: 'right', labels: { boxWidth: 12, font: {size: 11} } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const val = formatLarge(ctx.raw);
                                const count = data[ctx.dataIndex].count;
                                return `${ctx.label}: ${val} (${count} orders)`;
                            }
                        }
                    },
                    datalabels: { display: false } // Too cluttered for polar
                }
            }
        });
    }

    function renderCategoryChart(data) {
        const ctx = document.getElementById('categoryChart').getContext('2d');
        if (charts.category) charts.category.destroy();

        charts.category = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(c => c.category),
                datasets: [{ data: data.map(c => c.total_sales), backgroundColor: colors.palette, borderWidth: 0 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 12 } } },
                onClick: (e, elements) => {
                    if (elements.length > 0) {
                        const idx = elements[0].index;
                        const label = charts.category.data.labels[idx];
                        toggleDrill('category', label);
                    }
                },
                onHover: (e, el) => { e.native.target.style.cursor = el[0] ? 'pointer' : 'default'; }
            }
        });
    }

    function renderSupplierChart(data) {
        const ctx = document.getElementById('supplierChart').getContext('2d');
        if (charts.supplier) charts.supplier.destroy();

        charts.supplier = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.supplier),
                datasets: [{ label: 'Total Cost', data: data.map(d => d.cost), backgroundColor: colors.danger, borderRadius: 4 }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { display: false }, y: { grid: { display: false }, ticks: { font: { size: 11 } } } }
            }
        });
    }

    function renderCompanyChart(data) {
        const container = document.getElementById('companyChartContainer');
        const minWidth = Math.max(800, data.length * 60); 
        container.style.width = minWidth + 'px';
        container.style.height = '400px'; 

        const ctx = document.getElementById('companyChart').getContext('2d');
        if (charts.company) charts.company.destroy();

        charts.company = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.company),
                datasets: [{ 
                    label: 'Total Sales', 
                    data: data.map(d => d.total_sales), 
                    backgroundColor: colors.palette, // Cycle through colors
                    borderRadius: 3,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    datalabels: {
                        color: '#444',
                        anchor: 'end',
                        align: 'end',
                        offset: -5,
                        formatter: (val) => formatLarge(val),
                        font: { weight: 'bold', size: 10 }
                    }
                },
                scales: { 
                    x: { 
                        display: true,
                        title: { display: true, text: 'Company', font: { weight: 'bold' } },
                        ticks: { maxRotation: 45, minRotation: 0, font: { size: 11 } }
                    }, 
                    y: { 
                        display: true,
                        title: { display: true, text: 'Revenue (PHP)', font: { weight: 'bold' } },
                        beginAtZero: true,
                        ticks: { callback: function(value) { return formatLarge(value); } }
                    } 
                },
                onClick: (e, elements) => {
                    if (elements.length > 0) {
                        const idx = elements[0].index;
                        const label = charts.company.data.labels[idx];
                        toggleDrill('company', label);
                    }
                },
                onHover: (e, el) => { e.native.target.style.cursor = el[0] ? 'pointer' : 'default'; }
            },
            plugins: [ChartDataLabels]
        });
    }

    function renderTopProducts(data) {
        const ctx = document.getElementById('topProductsChart').getContext('2d');
        if (charts.topProd) charts.topProd.destroy();
        
        charts.topProd = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.name.substring(0, 15) + (d.name.length>15 ? '...' : '')),
                datasets: [{ label: 'Revenue', data: data.map(d => d.sales), backgroundColor: colors.teal, borderRadius: 4 }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { display: false }, y: { grid: { display: false } } }
            }
        });
    }

    function renderMatrix(data) {
        const tbody = document.getElementById('matrixBody');
        tbody.innerHTML = '';

        data.forEach((cat, index) => {
            const rowId = `cat-${index}`;
            const parentHtml = `
                <tr class="category-row" onclick="toggleRow('${rowId}', this)">
                    <td><i class="fas fa-chevron-right toggle-icon"></i> ${cat.category}</td>
                    <td class="text-end fw-bold">${cat.quantity}</td>
                    <td class="text-end fw-bold text-primary">${formatLarge(cat.total_sales)}</td>
                    <td class="text-end fw-bold text-success">${formatLarge(cat.total_profit)}</td>
                </tr>
            `;
            let childrenHtml = `<tr id="${rowId}" style="display:none;"><td colspan="4"><div class="nested-container"><table class="table table-sm table-borderless mb-0">`;
            cat.items.forEach(item => {
                childrenHtml += `
                    <tr>
                        <td width="40%" class="ps-4 text-muted small">${item.name}</td>
                        <td width="20%" class="text-end small">${item.qty}</td>
                        <td width="20%" class="text-end small">${formatMoney(item.sales)}</td>
                        <td width="20%" class="text-end small">${formatMoney(item.profit)}</td>
                    </tr>`;
            });
            childrenHtml += `</table></div></td></tr>`;
            tbody.insertAdjacentHTML('beforeend', parentHtml + childrenHtml);
        });
    }

    window.toggleRow = function(id, el) {
        const row = document.getElementById(id);
        const icon = el.querySelector('.toggle-icon');
        if (row.style.display === 'none') {
            row.style.display = 'table-row';
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-down');
            el.classList.add('bg-light');
        } else {
            row.style.display = 'none';
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-right');
            el.classList.remove('bg-light');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        handlePeriodChange();
        updateDashboard(); 
    });
    </script>
</body>
</html>