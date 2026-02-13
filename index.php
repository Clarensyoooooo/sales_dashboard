<?php require_once 'config.php'; requireLogin(); requirePermission('view_dashboard'); ?>
<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Dashboard - NAM Supply</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2563eb;       /* Royal Blue */
            --primary-dark: #1e40af;  /* Navy Blue */
            --secondary: #f97316;     /* Orange (for Margin) */
            --bg: #f3f4f6;            /* Light Gray Background */
            --surface: #ffffff;       /* White Cards */
            --text-main: #1f2937;     /* Dark Text */
            --text-light: #6b7280;    /* Light Text */
            --border: #e5e7eb;        /* Border Color */
            --success: #10b981;       /* Green */
            --danger: #ef4444;        /* Red */
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --radius: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text-main);
            padding: 20px;
            min-height: 100vh;
        }

        .dashboard-container {
            max-width: 1600px;
            margin: 0 auto;
        }

        /* --- Header --- */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--surface);
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-secondary { background: white; color: var(--text-main); border-color: var(--border); }
        .btn-secondary:hover { background: #f9fafb; border-color: #d1d5db; }

        /* --- Filter Bar --- */
        .filter-bar {
            background: var(--surface);
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .filter-input {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            min-width: 160px;
            background-color: #fff;
        }
        
        .filter-input:focus { outline: none; border-color: var(--primary); }

        .filter-btn {
            background: var(--primary-dark);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }

        .reset-btn {
            background: white;
            border: 1px solid var(--border);
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            color: var(--text-light);
            font-size: 14px;
        }
        .reset-btn:hover { background: #f3f4f6; }

        /* --- KPI Cards --- */
        .kpi-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .kpi-card {
            background: var(--surface);
            padding: 24px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border-left: 5px solid var(--primary);
            transition: transform 0.2s;
        }
        .kpi-card:hover { transform: translateY(-2px); }

        .kpi-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary-dark);
            margin-top: 5px;
        }

        .kpi-label {
            font-size: 13px;
            color: var(--text-light);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        /* --- Layout Grids --- */
        .full-width-chart {
            margin-bottom: 20px;
        }

        .breakdown-grid {
            display: grid;
            /* Adjusted for 3 items if needed, or stick to 2 and wrap */
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: var(--surface);
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .subtitle {
            font-size: 12px;
            color: var(--text-light);
            font-weight: 400;
        }

        /* Chart Containers */
        .sales-chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .scrollable-chart-container {
            overflow-y: auto;
            max-height: 400px;
            position: relative;
            padding-right: 10px;
        }
        
        .scrollable-chart-container::-webkit-scrollbar { width: 6px; }
        .scrollable-chart-container::-webkit-scrollbar-track { background: #f1f1f1; }
        .scrollable-chart-container::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }

        .donut-container {
            position: relative;
            height: 350px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* --- Low Stock List Styles --- */
        .alert-list {
            max-height: 350px;
            overflow-y: auto;
        }
        .alert-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--bg);
        }
        .alert-item:last-child { border-bottom: none; }
        .alert-info { display: flex; flex-direction: column; gap: 2px; }
        .alert-name { font-weight: 600; font-size: 14px; color: var(--text-main); }
        .alert-sub { font-size: 11px; color: var(--text-light); }
        .alert-badge { 
            padding: 4px 10px; 
            border-radius: 6px; 
            font-size: 12px; 
            font-weight: 700;
            background: #fee2e2; 
            color: #991b1b;
        }
        .alert-badge.out { background: #fecaca; color: #7f1d1d; }

        /* --- Matrix Table --- */
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .matrix-table th {
            text-align: left;
            padding: 14px;
            background: #f8fafc;
            color: var(--text-light);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
        }

        .matrix-table td {
            padding: 14px;
            border-bottom: 1px solid var(--bg);
            color: var(--text-main);
        }

        .category-row {
            cursor: pointer;
            font-weight: 600;
            background: white;
            transition: background 0.15s;
        }
        .category-row:hover { background: #eff6ff; }
        
        .category-row .toggle-icon {
            display: inline-block;
            width: 24px;
            height: 24px;
            text-align: center;
            line-height: 24px;
            border-radius: 4px;
            background: #eff6ff;
            color: var(--primary);
            margin-right: 8px;
            font-size: 10px;
            transition: transform 0.2s;
        }
        .category-row.expanded .toggle-icon { transform: rotate(90deg); background: var(--primary); color: white; }

        /* --- Nested Table Styles --- */
.details-row td { 
    padding: 0 !important; 
    border-bottom: 1px solid #e5e7eb; 
    background-color: #f8fafc;
}

.nested-container {
    max-height: 400px;       /* The "Smart" Part: Limits height */
    overflow-y: auto;        /* Adds scrollbar only if needed */
    padding: 15px 20px;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
}

/* Custom Scrollbar for the nested area */
.nested-container::-webkit-scrollbar { width: 6px; }
.nested-container::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 3px; }

.nested-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    position: sticky;
    top: 0;
    z-index: 10;
}

.nested-search { 
    width: 100%; 
    max-width: 300px;
    padding: 8px 12px; 
    border: 1px solid #d1d5db; 
    border-radius: 6px; 
    font-size: 13px;
    background: white;
}
.nested-search:focus { outline: none; border-color: var(--primary); }

.nested-table { width: 100%; border-collapse: collapse; }
.nested-table td { 
    padding: 8px 0; 
    border-bottom: 1px solid #e2e8f0; 
    color: #4b5563; 
    font-size: 13px; 
}
.nested-table tr:last-child td { border-bottom: none; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 1024px) {
            .breakdown-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-container" style="margin-top: 20px;">

        <div class="filter-bar">
            <div class="filter-group">
                <label>Time Period</label>
                <div style="display: flex; gap: 10px;">
                    <select id="periodFilter" class="filter-input" onchange="handlePeriodChange()">
                        <option value="today">Daily (Today)</option>
                        <option value="week">Weekly (This Week)</option>
                        <option value="month" selected>Monthly (This Month)</option>
                        <option value="custom_month">📅 Specific Month</option>
                        <option value="quarter">Quarterly (This Year)</option>
                        <option value="year">Yearly (This Year)</option>
                        <option value="all">All Time</option>
                    </select>
                    <input type="month" id="monthPicker" class="filter-input" style="display:none;" onchange="updateDashboard()">
                </div>
            </div>

            <div class="filter-group">
                <label>Filter Company</label>
                <select id="companyFilter" class="filter-input" style="min-width: 200px;">
                    <option value="">All Companies</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Daily Target (₱)</label>
                <input type="number" id="targetSales" class="filter-input" value="50000" step="1000">
            </div>

            <button onclick="updateDashboard()" class="filter-btn">Apply Filters</button>
            <button onclick="resetFilters()" class="reset-btn">Reset</button>
        </div>

        <div class="kpi-container">
            <div class="kpi-card">
                <div class="kpi-label">Total Sales</div>
                <div class="kpi-value" id="totalSales">₱0.00</div>
            </div>
            <div class="kpi-card" style="border-left-color: var(--success);">
                <div class="kpi-label">Total Profit</div>
                <div class="kpi-value" id="totalProfit" style="color: #059669;">₱0.00</div>
            </div>
            <div class="kpi-card" style="border-left-color: var(--secondary);">
                <div class="kpi-label">Profit Margin</div>
                <div class="kpi-value" id="profitMargin" style="color: #c2410c;">0%</div>
            </div>
            <div class="kpi-card" style="border-left-color: var(--danger);">
                <div class="kpi-label">Items Low Stock</div>
                <div class="kpi-value" id="lowStockCount" style="color: var(--danger);">0</div>
            </div>
        </div>

        <div class="card full-width-chart">
            <div class="card-title">
                <span>📈 Sales Performance vs Target</span>
                <span class="subtitle">
                    <span style="color:#10b981; font-weight:bold;">-- Target</span> &nbsp;|&nbsp; 
                    <span style="color:#f97316; font-weight:bold;">— Margin</span>
                </span>
            </div>
            <div class="sales-chart-container">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>

        <div class="breakdown-grid">
            <div class="card">
                <div class="card-title" style="border-bottom-color: #fee2e2;">
                    <span style="color: #991b1b;">⚠️ Restock Needed</span>
                    <a href="products.php" style="font-size:12px; color:var(--primary); text-decoration:none;">Manage Stock &rarr;</a>
                </div>
                <div class="alert-list" id="stockAlertList">
                    <div style="text-align:center; padding: 20px; color:#9ca3af;">Loading alerts...</div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">
                    <span>🏢 Company Breakdown</span>
                    <span class="subtitle">Top Clients</span>
                </div>
                <div class="scrollable-chart-container">
                    <div id="companyChartWrapper" style="width: 100%; height: 400px;">
                        <canvas id="companyChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">
                    <span>🍩 Sales by Category</span>
                </div>
                <div class="donut-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">
                <span>🗂️ Category Details</span>
                <span class="subtitle">Click rows to expand items</span>
            </div>
            <table class="matrix-table">
                <thead>
                    <tr>
                        <th width="40%">Category / Item</th>
                        <th width="15%" style="text-align: right;">Qty Sold</th>
                        <th width="20%" style="text-align: right;">Total Sales</th>
                        <th width="20%" style="text-align: right;">Total Profit</th>
                    </tr>
                </thead>
                <tbody id="matrixBody"></tbody>
            </table>
        </div>

    </div>

    <script>
    const formatMoney = (num) => '₱' + parseFloat(num).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const formatLarge = (num) => {
        if(num >= 1000000) return '₱' + (num/1000000).toFixed(2) + 'M';
        if(num >= 1000) return '₱' + (num/1000).toFixed(0) + 'K';
        return formatMoney(num);
    };

    const donutColors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316', '#6366f1', '#84cc16', '#14b8a6', '#d946ef'];
    let dailyChartInst, companyChartInst, categoryChartInst;

    function handlePeriodChange() {
        const period = document.getElementById('periodFilter').value;
        const picker = document.getElementById('monthPicker');
        
        if (period === 'custom_month') {
            picker.style.display = 'block';
            if (!picker.value) {
                const now = new Date();
                now.setMonth(now.getMonth() - 1); 
                const month = (now.getMonth() + 1).toString().padStart(2, '0');
                picker.value = `${now.getFullYear()}-${month}`;
            }
        } else {
            picker.style.display = 'none';
        }
        updateDashboard();
    }

    // MAIN UPDATE FUNCTION
    async function updateDashboard() {
        // 1. Fetch Sales Data
        await fetchSalesData();
        // 2. Fetch Inventory Data
        await fetchInventoryData();
    }

    async function fetchInventoryData() {
        try {
            const res = await fetch('get_all_products.php');
            const products = await res.json();
            
            // Filter Low Stock
            const lowStock = products.filter(p => parseInt(p.current_stock || 0) <= parseInt(p.reorder_level || 10));
            
            // Update KPI
            document.getElementById('lowStockCount').textContent = lowStock.length;

            // Update Widget
            const list = document.getElementById('stockAlertList');
            if(lowStock.length === 0) {
                list.innerHTML = `<div style="text-align:center; padding:40px; color:#10b981;">✅ All stock levels healthy!</div>`;
                return;
            }

            let html = '';
            lowStock.forEach(p => {
                const stock = parseInt(p.current_stock);
                const isOut = stock <= 0;
                html += `
                    <div class="alert-item">
                        <div class="alert-info">
                            <span class="alert-name">${p.name}</span>
                            <span class="alert-sub">${p.supplier || 'No Supplier'} • Alert at ${p.reorder_level}</span>
                        </div>
                        <span class="alert-badge ${isOut ? 'out' : ''}">
                            ${isOut ? 'OUT OF STOCK' : stock + ' left'}
                        </span>
                    </div>
                `;
            });
            list.innerHTML = html;

        } catch(e) {
            console.error("Inventory error:", e);
        }
    }

    async function fetchSalesData() {
        const period = document.getElementById('periodFilter').value;
        const company = document.getElementById('companyFilter').value;
        const target = parseFloat(document.getElementById('targetSales').value) || 0;

        const now = new Date();
        const today = now.toISOString().split('T')[0];
        let start = '', end = today, groupBy = 'day';

        // Time logic (simplified for brevity, same as original)
        if (period === 'today') { start = today; }
        else if (period === 'week') { 
            const d = new Date(now); d.setDate(d.getDate() - d.getDay()); 
            start = d.toISOString().split('T')[0]; 
        }
        else if (period === 'month') { start = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]; }
        else if (period === 'quarter') { start = new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0]; groupBy = 'quarter'; }
        else if (period === 'year') { start = new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0]; groupBy = 'month'; }
        else if (period === 'custom_month') {
            const val = document.getElementById('monthPicker').value;
            if (val) {
                const [y, m] = val.split('-');
                start = `${y}-${m}-01`;
                end = new Date(y, m, 0).toISOString().split('T')[0];
            }
        }
        else { groupBy = 'year'; } // All time

        const url = `api.php?start_date=${start}&end_date=${end}&company=${encodeURIComponent(company)}&group_by=${groupBy}`;

        try {
            const res = await fetch(url);
            const data = await res.json();

            document.getElementById('totalSales').textContent = formatLarge(data.stats.total_sales);
            document.getElementById('totalProfit').textContent = formatLarge(data.stats.total_profit);
            document.getElementById('profitMargin').textContent = data.stats.profit_margin.toFixed(2) + '%';

            renderCharts(data.chart_data, data.company_sales, data.category_matrix, target);
            renderMatrix(data.category_matrix);
            populateCompanyDropdown(data.companies);

        } catch (err) {
            console.error("Sales data error:", err);
        }
    }

    function populateCompanyDropdown(companies) {
        const select = document.getElementById('companyFilter');
        if (select.options.length <= 1) {
            companies.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c;
                select.appendChild(opt);
            });
        }
    }

    function renderCharts(chartData, companyData, categoryData, targetValue) {
        const ctx1 = document.getElementById('dailyChart').getContext('2d');
        const ctx2 = document.getElementById('companyChart').getContext('2d');
        const ctx3 = document.getElementById('categoryChart').getContext('2d');

        if (dailyChartInst) dailyChartInst.destroy();
        if (companyChartInst) companyChartInst.destroy();
        if (categoryChartInst) categoryChartInst.destroy();

        // 1. Sales Trend
        dailyChartInst = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: chartData.map(d => d.label),
                datasets: [
                    {
                        type: 'line', label: 'Target', data: Array(chartData.length).fill(targetValue),
                        borderColor: '#10b981', borderWidth: 2, borderDash: [6, 4], pointRadius: 0, order: 1, yAxisID: 'y'
                    },
                    {
                        type: 'line', label: 'Margin %', data: chartData.map(d => d.margin),
                        borderColor: '#f97316', backgroundColor: '#f97316', borderWidth: 2, tension: 0.3, yAxisID: 'y1', order: 0
                    },
                    {
                        type: 'bar', label: 'Sales', data: chartData.map(d => d.sales),
                        backgroundColor: 'rgba(37, 99, 235, 0.75)', hoverBackgroundColor: 'rgba(37, 99, 235, 1)', order: 2, yAxisID: 'y'
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false } },
                scales: {
                    y: { type: 'linear', display: true, position: 'left', grid: { borderDash: [5, 5] } },
                    y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => v + '%' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Company Breakdown
        const neededHeight = Math.max(400, companyData.length * 35);
        document.getElementById('companyChartWrapper').style.height = neededHeight + 'px';

        companyChartInst = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: companyData.map(c => c.company),
                datasets: [{ label: 'Total Sales', data: companyData.map(c => c.total_sales), backgroundColor: '#3b82f6', borderRadius: 4, barPercentage: 0.6 }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                scales: { x: { display: false }, y: { grid: { display: false }, ticks: { font: { size: 11 } } } },
                onClick: (e, elements) => {
                    const select = document.getElementById('companyFilter');
                    if (elements.length > 0) {
                        const idx = elements[0].index;
                        const name = companyChartInst.data.labels[idx];
                        select.value = (select.value === name) ? "" : name;
                    } else { select.value = ""; }
                    updateDashboard();
                },
                onHover: (e, el) => { e.native.target.style.cursor = el[0] ? 'pointer' : 'default'; }
            }
        });

        // 3. Category Breakdown
        categoryChartInst = new Chart(ctx3, {
            type: 'doughnut',
            data: {
                labels: categoryData.map(c => c.category),
                datasets: [{ data: categoryData.map(c => c.total_sales), backgroundColor: donutColors, borderWidth: 2, borderColor: '#ffffff' }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } }
            }
        });
    }

    function renderMatrix(data) {
    const tbody = document.getElementById('matrixBody');
    tbody.innerHTML = '';

    data.forEach((cat, index) => {
        // 1. Create Parent Row (Category)
        const parentRow = document.createElement('tr');
        parentRow.className = 'category-row';
        parentRow.innerHTML = `
            <td><span class="toggle-icon">▶</span> ${cat.category}</td>
            <td style="text-align: right;">${cat.quantity.toLocaleString()}</td>
            <td style="text-align: right; font-weight:700;">${formatMoney(cat.total_sales)}</td>
            <td style="text-align: right; color: #059669; font-weight:600;">${formatMoney(cat.total_profit)}</td>
        `;

        // 2. Create Details Row (Hidden Container)
        const detailsRow = document.createElement('tr');
        detailsRow.className = 'details-row';
        detailsRow.style.display = 'none'; // Hidden by default
        
        // 3. Build Nested Table HTML
        let itemsHtml = '';
        cat.items.forEach(item => {
            // Note: We match the column widths of the parent table roughly
            itemsHtml += `
                <tr>
                    <td width="40%">${item.name}</td>
                    <td width="15%" style="text-align: right;">${item.qty.toLocaleString()}</td>
                    <td width="20%" style="text-align: right;">${formatMoney(item.sales)}</td>
                    <td width="20%" style="text-align: right;">${formatMoney(item.profit)}</td>
                </tr>`;
        });

        // 4. Create the scrollable container and search bar
        const cell = document.createElement('td');
        cell.colSpan = 4; // Span across all main columns
        const tableId = `nested-table-${index}`;
        
        cell.innerHTML = `
            <div class="nested-container">
                <div class="nested-header">
                    <input type="text" placeholder="🔍 Search inside ${cat.category}..." 
                           class="nested-search" 
                           onkeyup="filterNestedTable(this, '${tableId}')"
                           onclick="event.stopPropagation()">
                    <span style="font-size:12px; color:#64748b; font-weight:500;">
                        ${cat.items.length} items found
                    </span>
                </div>
                <table class="nested-table">
                    <tbody id="${tableId}">
                        ${itemsHtml}
                    </tbody>
                </table>
            </div>
        `;

        detailsRow.appendChild(cell);

        // 5. Append to Main Table
        tbody.appendChild(parentRow);
        tbody.appendChild(detailsRow);

        // 6. Click Event to Toggle
        parentRow.onclick = function() {
            const isExpanded = this.classList.contains('expanded');
            
            // Close all others first (Optional: keeps it clean)
            document.querySelectorAll('.category-row').forEach(r => r.classList.remove('expanded'));
            document.querySelectorAll('.details-row').forEach(r => r.style.display = 'none');

            if (!isExpanded) {
                this.classList.add('expanded');
                detailsRow.style.display = 'table-row';
            }
        };
    });
}

// Helper Function for the Mini-Search
function filterNestedTable(input, tableId) {
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll(`#${tableId} tr`);
    
    rows.forEach(row => {
        const text = row.cells[0].textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

    function resetFilters() {
        document.getElementById('periodFilter').value = 'month';
        document.getElementById('companyFilter').value = '';
        document.getElementById('targetSales').value = '50000';
        updateDashboard();
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Init with auto-refresh every 30s
        updateDashboard();
        setInterval(updateDashboard, 30000); 
    });
    </script>
</body>
</html>