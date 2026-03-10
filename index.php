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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root { --bg: #f8f9fa; --primary: #4f46e5; --text: #334155; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); padding-bottom: 50px; color: var(--text); }
        .dashboard-container { max-width: 1600px; margin: 20px auto; padding: 0 20px; }
        
        /* KPI Cards */
        .kpi-card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); transition: transform 0.2s; position: relative; overflow: hidden; background: white; }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .kpi-value { font-size: 28px; font-weight: 700; margin-top: 5px; color: #0f172a; }
        .kpi-label { font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .icon-bg { position: absolute; right: -10px; bottom: -10px; font-size: 80px; opacity: 0.05; transform: rotate(-15deg); }
        
        /* Charts */
        .chart-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); height: 100%; border: none; }
        .chart-title { font-size: 16px; font-weight: 700; color: #334155; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
        
        /* Filter Bar */
        .filter-bar { background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px; display: flex; flex-wrap: wrap; gap: 20px; align-items: center; justify-content: space-between; }
        
        /* Target Progress / Bonus */
        .target-widget { flex-grow: 1; max-width: 320px; min-width: 250px; }
        .progress-label { font-size: 12px; font-weight: 600; display: flex; justify-content: space-between; margin-bottom: 5px; }
        
        /* Drill Tags */
        .drill-tag {
            background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe;
            padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
            display: inline-flex; align-items: center; gap: 6px; margin-right: 8px;
            cursor: default; transition: all 0.2s;
        }
        .drill-tag:hover { background: #c7d2fe; }
        .drill-tag i.fa-times { cursor: pointer; color: #4338ca; opacity: 0.7; }
        .drill-tag i.fa-times:hover { opacity: 1; }

        /* Matrix Table */
        .matrix-table th { font-size: 11px; text-transform: uppercase; color: #64748b; background: #f8fafc; padding: 12px; font-weight: 700; letter-spacing: 0.5px; }
        .matrix-table td { font-size: 14px; padding: 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .category-row { font-weight: 600; cursor: pointer; transition: background 0.1s; }
        .category-row:hover { background: #f8fafc; }
        
        /* Horizontal Scroll for Company Chart */
        .scrollable-chart-wrapper { width: 100%; overflow-x: auto; padding-bottom: 10px; }
        #companyChartContainer { min-height: 400px; position: relative; }
        
        /* Hide arrows in number input */
        .no-spinners::-webkit-outer-spin-button,
        .no-spinners::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .no-spinners { -moz-appearance: textfield; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">

        <div class="filter-bar">
            <div class="d-flex gap-3 align-items-end flex-wrap">
                <div>
                    <label class="small fw-bold text-muted d-block mb-1">Time Period</label>
                    <select id="periodFilter" class="form-select form-select-sm shadow-none border-secondary-subtle" style="width: 150px; font-weight: 600;" onchange="handlePeriodChange()">
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
                    <input type="month" id="monthPicker" class="form-control form-control-sm shadow-none" onchange="updateDashboard()">
                </div>
                <div>
                    <label class="small fw-bold text-muted d-block mb-1">Targets (Min / Max)</label>
                    <div class="input-group input-group-sm">
                        <input type="number" id="minTarget" class="form-control shadow-none" value="100000" step="10000" placeholder="Min" style="width: 80px;" onchange="updateDashboard()">
                        <input type="number" id="maxTarget" class="form-control shadow-none" value="200000" step="10000" placeholder="Max" onchange="updateDashboard()" style="width: 80px;">
                    </div>
                </div>
                <div class="d-flex align-items-end pb-1">
                     <button onclick="updateDashboard()" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm"><i class="fas fa-sync-alt me-1"></i> Apply</button>
                </div>
            </div>

            <div class="target-widget mt-3 mt-lg-0 pt-3 pt-lg-0 border-top border-secondary-subtle ps-lg-4 ms-lg-2" style="border-top: none !important; @media (max-width: 991px) { border-top: 1px solid #dee2e6 !important; }">
                <div class="progress-label align-items-center mb-0">
                    <span class="text-primary"><i class="fas fa-gift me-1"></i>Performance Bonus</span>
                    <div class="input-group input-group-sm ms-2" style="width: 85px;">
                        <input type="number" id="bonusPercent" class="form-control shadow-none text-center text-primary fw-bold bg-primary bg-opacity-10 border-primary-subtle no-spinners" value="1" step="0.1" min="0" oninput="updateBonusWidget()">
                        <span class="input-group-text bg-primary bg-opacity-10 text-primary border-primary-subtle px-2">%</span>
                    </div>
                </div>
                <div class="fw-bold mt-1" id="calculatedBonus" style="font-size: 26px; color: #4338ca;">₱0.00</div>
                <small class="text-muted" style="font-size: 10px;">Calculated from currently filtered revenue</small>
            </div>
            
            <div id="activeFiltersArea" class="w-100 mt-2 pt-2 border-top d-flex align-items-center" style="display:none !important;">
                <span class="small text-muted me-2 fw-bold"><i class="fas fa-filter me-1"></i>Filtered By:</span>
                <span id="drillTags"></span>
                <button onclick="resetAllDrills()" class="btn btn-link btn-sm text-danger text-decoration-none py-0 small fw-bold" id="resetBtn" style="display:none;">Clear All</button>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="kpi-card p-4 h-100">
                    <div class="kpi-label text-primary">Total Revenue</div>
                    <div class="kpi-value" id="totalSales">₱0.00</div>
                    <div class="mt-2" id="growthBadge"></div>
                    <div class="mt-1" id="targetBadge"></div>
                    <i class="fas fa-coins icon-bg text-primary"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card p-4 h-100">
                    <div class="kpi-label text-success">Net Profit</div>
                    <div class="kpi-value" id="totalProfit">₱0.00</div>
                    <div class="small text-muted mt-1 fw-bold">Margin: <span class="text-success" id="profitMargin">0%</span></div>
                    <i class="fas fa-chart-line icon-bg text-success"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card p-4 h-100">
                    <div class="kpi-label text-info">Avg. Order Value</div>
                    <div class="kpi-value" id="avgOrder">₱0.00</div>
                    <div class="small text-muted mt-1 fw-bold" id="orderCount">0 Orders</div>
                    <i class="fas fa-shopping-cart icon-bg text-info"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card p-4 h-100">
                    <div class="kpi-label text-danger">Stock Alerts</div>
                    <div class="kpi-value" id="lowStockCount">0</div>
                    <a href="products.php" class="small text-decoration-none text-danger mt-1 d-block fw-bold">View Inventory <i class="fas fa-arrow-right ms-1"></i></a>
                    <i class="fas fa-box-open icon-bg text-danger"></i>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="chart-title">
                        <span><i class="fas fa-chart-bar text-primary me-2"></i>Sales Performance</span>
                    </div>
                    <div style="height: 320px;">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-title">
                        <span><i class="fas fa-shipping-fast text-success me-2"></i>Logistics Status</span>
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
                   <div class="chart-title mb-2">
                        <span><i class="fas fa-building text-info me-2"></i>Company Performance</span>
                        <div>
                            <small class="text-muted fw-normal me-2" style="font-size: 11px;">Click bar to filter</small>
                            <?php if($_SESSION['role_id'] == 1): ?>
                            <a href="assignments.php" class="btn btn-sm btn-outline-secondary py-0 px-2 shadow-sm" title="Assign Account Managers" style="font-size: 11px;">
                                <i class="fas fa-cog"></i> Setup
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-2 mb-3" style="font-size: 11px;">
                        <span class="badge rounded-pill text-white" style="background-color: #419CA1;">Ms. Anne</span>
                        <span class="badge rounded-pill text-dark" style="background-color: #AFD5F7;">Ms. Cherry</span>
                        <span class="badge rounded-pill text-white" style="background-color: #007725;">Ms. Glenda</span>
                        <span class="badge rounded-pill text-white" style="background-color: #AA38A;">Ms. Ivy</span>
                        <span class="badge rounded-pill text-white" style="background-color: blue;">Ms. Ally</span>
                        <span class="badge rounded-pill text-white" style="background-color: #FC0FC0;">Ms. Hannah</span>
                        <span class="badge rounded-pill text-dark" style="background-color: #cbd5e1;">Unassigned</span>
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
                        <span><i class="fas fa-money-check-alt text-secondary me-2"></i>Collection Status (Paid vs Unpaid)</span>
                    </div>
                    <div style="height: 300px; position:relative;">
                        <canvas id="collectionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-title">
                        <span><i class="fas fa-chart-pie text-warning me-2"></i>By Category</span>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-title">
                        <span><i class="fas fa-crown text-warning me-2"></i>Top Products</span>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="topProductsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-title">
                        <span><i class="fas fa-tags text-danger me-2"></i>Supplier Costs</span>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="supplierChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-5">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-table me-2 text-muted"></i>Detailed Sales Matrix</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 matrix-table table-hover">
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
    // --- UTILS ---
    const formatMoney = (num) => '₱' + parseFloat(num).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    const formatLarge = (num) => {
        return formatMoney(num);
    };

    // --- COLOR PALETTE (Pleasant/Soft) ---
    const colors = {
        primary:   '#6366f1', // Indigo-500
        primarySoft: 'rgba(99, 102, 241, 0.7)',
        secondary: '#64748b', // Slate-500
        success:   '#10b981', // Emerald-500
        warning:   '#f59e0b', // Amber-500
        danger:    '#ef4444', // Red-500
        info:      '#0ea5e9', // Sky-500
        
        transparentPalette: [
            'rgba(99, 102, 241, 0.6)', 
            'rgba(16, 185, 129, 0.6)', 
            'rgba(245, 158, 11, 0.6)', 
            'rgba(239, 68, 68, 0.6)', 
            'rgba(14, 165, 233, 0.6)', 
            'rgba(139, 92, 246, 0.6)'
        ],
        palette: [
            '#6366f1', '#10b981', '#f59e0b', '#ef4444', 
            '#0ea5e9', '#8b5cf6', '#ec4899', '#f97316'
        ]
    };

    // --- STATE ---
    let activeDrills = { company: null, category: null };
    let currentTotalSales = 0;
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
        const area = document.getElementById('activeFiltersArea');
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
        area.style.display = hasActive ? 'flex' : 'none';
        resetBtn.style.display = hasActive ? 'inline-block' : 'none';
    }

    function updateBonusWidget() {
        const percentVal = parseFloat(document.getElementById('bonusPercent').value) || 0;
        const bonusAmount = currentTotalSales * (percentVal / 100);
        document.getElementById('calculatedBonus').innerText = formatMoney(bonusAmount);
    }

    async function updateDashboard() {
        await fetchSalesData();
        await fetchInventoryData();
        updateBonusWidget();
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
        
        // --- LOCAL DATE FIX ---
        const toLocalYYYYMMDD = (d) => {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        };

        const today = toLocalYYYYMMDD(now);
        let start = '', end = today, groupBy = 'day';

        if (period === 'today') {
            start = today;
        } else if (period === 'week') { 
            const d = new Date(now); 
            d.setDate(d.getDate() - d.getDay()); 
            start = toLocalYYYYMMDD(d); 
        } else if (period === 'month') { 
            start = toLocalYYYYMMDD(new Date(now.getFullYear(), now.getMonth(), 1)); 
        } else if (period === 'quarter') { 
            start = toLocalYYYYMMDD(new Date(now.getFullYear(), 0, 1)); 
            groupBy = 'quarter'; 
        } else if (period === 'year') { 
            start = toLocalYYYYMMDD(new Date(now.getFullYear(), 0, 1)); 
            groupBy = 'month'; 
        } else if (period === 'custom_month') {
            const val = document.getElementById('monthPicker').value;
            if (val) { 
                const [y, m] = val.split('-'); 
                start = `${y}-${m}-01`; 
                end = toLocalYYYYMMDD(new Date(y, m, 0)); 
            }
        } else {
            groupBy = 'year';
        }

        let url = `api.php?start_date=${start}&end_date=${end}&group_by=${groupBy}`;
        if(activeDrills.company) url += `&company=${encodeURIComponent(activeDrills.company)}`;
        if(activeDrills.category) url += `&category=${encodeURIComponent(activeDrills.category)}`;

        try {
            const res = await fetch(url);
            const data = await res.json();

            // KPIs
            currentTotalSales = data.stats.total_sales;
            document.getElementById('totalSales').textContent = formatLarge(data.stats.total_sales);
            document.getElementById('totalProfit').textContent = formatLarge(data.stats.total_profit);
            document.getElementById('profitMargin').textContent = data.stats.profit_margin.toFixed(1) + '%';
            document.getElementById('avgOrder').textContent = formatMoney(data.stats.avg_order_value);
            document.getElementById('orderCount').textContent = `${data.stats.total_orders} Orders`;

            // --- DYNAMIC TARGET CALCULATION ---
            let targetRev = 2500000; // Base: 1 Month
            if (period === 'today') targetRev = 2500000 / 30;
            else if (period === 'week') targetRev = 2500000 / 4;
            else if (period === 'quarter') targetRev = 2500000 * 3;
            else if (period === 'year') targetRev = 2500000 * 12;
            
            const targetPct = ((data.stats.total_sales / targetRev) * 100).toFixed(1);
            let targetColor = data.stats.total_sales >= targetRev ? 'text-success' : 'text-primary';
            let targetIcon = data.stats.total_sales >= targetRev ? 'fa-arrow-up' : 'fa-bullseye';
            const formattedTarget = formatLarge(targetRev);
            
            document.getElementById('targetBadge').innerHTML = `<small class="${targetColor} fw-bold" style="font-size: 0.75rem;"><i class="fas ${targetIcon} me-1"></i>${targetPct}% of ${formattedTarget} Target</small>`;

            // Growth Badge
            const growth = data.stats.growth_sales || 0;
            const growthBadge = document.getElementById('growthBadge');
            if (period === 'custom_month' || period === 'month' || period === 'year') {
                const icon = growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                const color = growth >= 0 ? 'text-success' : 'text-danger';
                growthBadge.innerHTML = `<small class="${color} fw-bold"><i class="fas ${icon}"></i> ${Math.abs(growth).toFixed(1)}% vs prev</small>`;
            } else growthBadge.innerHTML = '';

            // Render Charts
            renderDailyChart(data.chart_data);
            renderCategoryChart(data.category_matrix);
            renderDeliveryChart(data.delivery_stats);
            renderSupplierChart(data.supplier_costs);
            renderCompanyChart(data.company_sales);
            renderCollectionChart(data.collection_status);
            renderTopProducts(data.top_products);
            renderMatrix(data.category_matrix);
            
            updateBonusWidget();

        } catch (err) { console.error("Sales data error:", err); }
    }

    // --- CHART FUNCTIONS ---
    function renderDailyChart(data) {
        const ctx = document.getElementById('dailyChart').getContext('2d');
        const minTarget = parseFloat(document.getElementById('minTarget').value) || 100000;
        const maxTarget = parseFloat(document.getElementById('maxTarget').value) || 200000;

        if (charts.daily) charts.daily.destroy();

        charts.daily = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.label),
                datasets: [
                    { 
                        type: 'line', 
                        label: 'Profit Margin', 
                        data: data.map(d => d.margin), 
                        yAxisID: 'y1',
                        borderColor: colors.warning, 
                        backgroundColor: colors.warning,
                        borderWidth: 2, 
                        borderDash: [5, 3],
                        pointRadius: 3,
                        order: 0 
                    },
                    { 
                        type: 'line', 
                        label: 'Sales Trend', 
                        data: data.map(d => d.sales), 
                        yAxisID: 'y',
                        borderColor: '#4338ca', 
                        borderWidth: 2,
                        tension: 0.3, 
                        pointRadius: 0,
                        order: 1 
                    },
                    { 
                        type: 'line', 
                        label: 'Max Target', 
                        data: Array(data.length).fill(maxTarget), 
                        yAxisID: 'y',
                        borderColor: colors.success, 
                        borderWidth: 2, 
                        borderDash: [6, 4], 
                        pointRadius: 0, 
                        order: 2 
                    },
                    { 
                        type: 'line', 
                        label: 'Min Target', 
                        data: Array(data.length).fill(minTarget), 
                        yAxisID: 'y',
                        borderColor: colors.danger, 
                        borderWidth: 2, 
                        borderDash: [2, 2], 
                        pointRadius: 0, 
                        order: 3 
                    },
                    { 
                        type: 'bar', 
                        label: 'Revenue', 
                        data: data.map(d => d.sales), 
                        yAxisID: 'y',
                        backgroundColor: colors.primarySoft, 
                        borderRadius: 4, 
                        order: 4
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 15 } } },
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        position: 'left',
                        grid: { borderDash: [4, 4], color: '#e2e8f0' },
                        ticks: { callback: function(val) { return formatMoney(val); }, color: '#64748b' },
                        title: { display: true, text: 'Revenue (PHP)', font: { weight: 'bold', size: 11 }, color: '#475569' }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { display: false },
                        ticks: { callback: function(val) { return val + '%'; }, color: '#64748b' },
                        title: { display: true, text: 'Margin (%)', font: { weight: 'bold', size: 11 }, color: '#475569' }
                    },
                    x: { 
                        grid: { display: false },
                        ticks: { color: '#64748b' },
                        title: { display: true, text: 'Timeline', font: { weight: 'bold', size: 11 }, color: '#475569' }
                    } 
                }
            }
        });
    }

    function renderDeliveryChart(data) {
        const ctx = document.getElementById('deliveryChart').getContext('2d');
        if (charts.delivery) charts.delivery.destroy();

        charts.delivery = new Chart(ctx, {
            type: 'doughnut',
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
                cutout: '70%',
                plugins: { 
                    legend: { position: 'right', labels: { boxWidth: 12, usePointStyle: true } }
                }
            }
        });
    }
    
    function renderCollectionChart(data) {
        const ctx = document.getElementById('collectionChart').getContext('2d');
        if (charts.collection) charts.collection.destroy();

        const bgColors = data.map(d => d.status === 'Paid' ? colors.success : colors.warning);

        charts.collection = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: data.map(d => d.status),
                datasets: [{
                    data: data.map(d => d.sales), 
                    backgroundColor: bgColors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: {size: 11}, usePointStyle: true } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.label + ': ' + formatLarge(context.raw);
                            }
                        }
                    }
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
                plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 12, usePointStyle: true } } },
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
        
        const employeeColors = {
            'Ms. Anne': '#419CA1',
            'Ms. Cherry': '#AFD5F7',
            'Ms. Glenda': 'green',
            'Ms. Ivy': 'purple',
            'Ms. Ally': 'blue',
            'Ms. Hannah': '#FC0FC0',
            'Unassigned': '#cbd5e1'
        };

        const backgroundColors = data.map(d => employeeColors[d.employee] || employeeColors['Unassigned']);
        
        const minTarget = parseFloat(document.getElementById('minTarget').value) || 100000;
        const maxTarget = parseFloat(document.getElementById('maxTarget').value) || 200000;

        if (charts.company) charts.company.destroy();
        charts.company = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.company),
                datasets: [
                    {
                        type: 'line', label: 'Max Target',
                        data: Array(data.length).fill(maxTarget),
                        borderColor: colors.success, borderWidth: 2, borderDash: [6, 4], pointRadius: 0, datalabels: { display: false }, order: 0
                    },
                    {
                        type: 'line', label: 'Min Target',
                        data: Array(data.length).fill(minTarget),
                        borderColor: colors.danger, borderWidth: 2, borderDash: [2, 2], pointRadius: 0, datalabels: { display: false }, order: 1
                    },
                    {
                        type: 'bar',
                        label: 'Total Sales',
                        data: data.map(d => d.total_sales),
                        backgroundColor: backgroundColors,
                        borderRadius: 3,
                        barPercentage: 0.6,
                        order: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: true, position: 'bottom', 
                        labels: { 
                            usePointStyle: true, boxWidth: 8, padding: 15,
                            filter: function(item, chart) { return item.text !== 'Total Sales'; }
                        } 
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                return 'Account Manager: ' + data[context.dataIndex].employee;
                            }
                        }
                    },
                    datalabels: {
                        color: '#444', anchor: 'end', align: 'end', offset: -5,
                        formatter: (val) => formatLarge(val),
                        font: { weight: 'bold', size: 10 }
                    }
                },
                scales: {
                    x: { display: true, title: { display: true, text: 'Company', font: { weight: 'bold', size: 10 } }, ticks: { maxRotation: 45, minRotation: 0, font: { size: 11 } } },
                    y: { display: true, title: { display: true, text: 'Revenue (PHP)', font: { weight: 'bold', size: 10 } }, beginAtZero: true, ticks: { callback: function(value) { return formatLarge(value); } } }
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
                datasets: [{ label: 'Revenue', data: data.map(d => d.sales), backgroundColor: colors.info, borderRadius: 4 }]
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
                    <td><i class="fas fa-chevron-right toggle-icon text-muted me-2"></i> ${cat.category}</td>
                    <td class="text-end fw-bold">${cat.quantity}</td>
                    <td class="text-end fw-bold text-primary">${formatLarge(cat.total_sales)}</td>
                    <td class="text-end fw-bold text-success">${formatLarge(cat.total_profit)}</td>
                </tr>
            `;
            let childrenHtml = `<tr id="${rowId}" style="display:none;"><td colspan="4"><div class="nested-container bg-light p-2 rounded"><table class="table table-sm table-borderless mb-0">`;
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