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
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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

        /* --- Charts Grid --- */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr; /* 2/3 for Trend, 1/3 for Companies */
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

        /* Company Chart Scroll Container */
        .scrollable-chart-container {
            overflow-y: auto;
            max-height: 400px; /* Limits height and enables scroll */
            position: relative;
            padding-right: 10px; /* Space for scrollbar */
        }
        
        /* Custom Scrollbar for Chrome/Safari */
        .scrollable-chart-container::-webkit-scrollbar { width: 6px; }
        .scrollable-chart-container::-webkit-scrollbar-track { background: #f1f1f1; }
        .scrollable-chart-container::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }

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

        /* Parent Rows */
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

        /* Child Rows (Items) */
        .item-row {
            display: none;
            background: #f8fafc;
            font-size: 13px;
            color: #475569;
        }
        
        .item-row td:first-child {
            padding-left: 50px; /* Indent items */
            border-left: 3px solid var(--primary);
        }

        .item-row.visible {
            display: table-row;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* Responsive */
        @media (max-width: 1024px) {
            .charts-grid { grid-template-columns: 1fr; }
            .scrollable-chart-container { max-height: 300px; }
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <div class="header">
            <h1>
                🚀 NAM Supply 
                <span style="font-weight:400; font-size: 16px; color: var(--text-light); margin-left: 10px;">| Sales Dashboard</span>
            </h1>
            <div style="display: flex; gap: 12px;">
    <a href="products.php" class="btn btn-secondary">📦 Price List</a>
    <a href="records.php" class="btn btn-secondary">📋 View Records</a>
    <a href="form.php" class="btn btn-primary">➕ Add Entry</a>
</div>
        </div>

        <div class="filter-bar">
            <div class="filter-group">
    <label>Time Period</label>
    <div style="display: flex; gap: 10px;">
        <select id="periodFilter" class="filter-input" onchange="handlePeriodChange()">
            <option value="today">Daily (Today)</option>
            <option value="week">Weekly (This Week)</option>
            <option value="month" selected>Monthly (This Month)</option>
            <option value="custom_month">📅 Specific Month</option> <option value="quarter">Quarterly (This Year)</option>
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
        </div>

        <div class="charts-grid">
            <div class="card">
                <div class="card-title">
                    <span>📈 Sales Performance vs Target</span>
                    <span class="subtitle">
                        <span style="color:#10b981; font-weight:bold;">-- Target</span> &nbsp;|&nbsp; 
                        <span style="color:#f97316; font-weight:bold;">— Margin</span>
                    </span>
                </div>
                <canvas id="dailyChart" height="120"></canvas>
            </div>

            <div class="card">
                <div class="card-title">
                    <span>🏢 Company Breakdown</span>
                    <span class="subtitle">Sorted by Sales</span>
                </div>
                <div class="scrollable-chart-container">
                    <div id="companyChartWrapper" style="width: 100%; height: 400px;">
                        <canvas id="companyChart"></canvas>
                    </div>
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
                        <th width="15%" style="text-align: right;">Qty</th>
                        <th width="20%" style="text-align: right;">Total Sales</th>
                        <th width="20%" style="text-align: right;">Total Profit</th>
                    </tr>
                </thead>
                <tbody id="matrixBody">
                    </tbody>
            </table>
        </div>

    </div>

    <script>
    // Formatting Helpers
    const formatMoney = (num) => '₱' + parseFloat(num).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const formatLarge = (num) => {
        if(num >= 1000000) return '₱' + (num/1000000).toFixed(2) + 'M';
        if(num >= 1000) return '₱' + (num/1000).toFixed(0) + 'K';
        return formatMoney(num);
    };

    let dailyChartInst, companyChartInst;

    function handlePeriodChange() {
        const period = document.getElementById('periodFilter').value;
        const picker = document.getElementById('monthPicker');
        
        // Show/Hide Month Picker
        if (period === 'custom_month') {
            picker.style.display = 'block';
            // Auto-select previous month if empty
            if (!picker.value) {
                const now = new Date();
                // Go back 1 month for convenience
                now.setMonth(now.getMonth() - 1); 
                const month = (now.getMonth() + 1).toString().padStart(2, '0');
                picker.value = `${now.getFullYear()}-${month}`;
            }
        } else {
            picker.style.display = 'none';
        }
        
        // Auto-update dashboard when switching modes
        updateDashboard();
    }

    async function updateDashboard() {
        const period = document.getElementById('periodFilter').value;
        const company = document.getElementById('companyFilter').value;
        const target = parseFloat(document.getElementById('targetSales').value) || 0;

        // --- 1. Determine Dates & Grouping ---
        const now = new Date();
        const today = now.toISOString().split('T')[0];
        let start = '', end = today, groupBy = 'day';

        if (period === 'today') {
            start = today;
            groupBy = 'day';
        } else if (period === 'week') {
            const firstDay = new Date(now.setDate(now.getDate() - now.getDay()));
            start = firstDay.toISOString().split('T')[0];
            groupBy = 'day';
        } else if (period === 'month') {
            start = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
            groupBy = 'day';
        } else if (period === 'quarter') {
            // Start of Year for Quarterly View
            start = new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0];
            groupBy = 'quarter'; 
        } else if (period === 'year') {
            start = new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0];
            groupBy = 'month'; // Show months for the year
            } else if (period === 'custom_month') {
            // Get value from the month picker
            const val = document.getElementById('monthPicker').value;
            if (val) {
                const [y, m] = val.split('-');
                start = `${y}-${m}-01`;
                // Get last day of that month (using 0th day of next month trick)
                end = new Date(y, m, 0).toISOString().split('T')[0];
                groupBy = 'day'; // Show daily trend for that specific month
            }
        } else {
            // All Time
            start = ''; end = '';
            groupBy = 'year'; // Show years for all time
        }

        // --- 2. Fetch Data ---
        const url = `api.php?start_date=${start}&end_date=${end}&company=${encodeURIComponent(company)}&group_by=${groupBy}`;

        try {
            const res = await fetch(url);
            const data = await res.json();

            // Update KPIs
            document.getElementById('totalSales').textContent = formatLarge(data.stats.total_sales);
            document.getElementById('totalProfit').textContent = formatLarge(data.stats.total_profit);
            document.getElementById('profitMargin').textContent = data.stats.profit_margin.toFixed(2) + '%';

            // Render Charts
            renderCharts(data.chart_data, data.company_sales, target); // Use chart_data
            renderMatrix(data.category_matrix);

            // Populate Dropdown
            populateCompanyDropdown(data.companies);

        } catch (err) {
            console.error("Error loading dashboard data:", err);
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

    function renderCharts(chartData, companyData, targetValue) {
        const ctx1 = document.getElementById('dailyChart').getContext('2d');
        const ctx2 = document.getElementById('companyChart').getContext('2d');

        if (dailyChartInst) dailyChartInst.destroy();
        if (companyChartInst) companyChartInst.destroy();

        // --- Chart 1: Sales Trend (Dynamic Labels) ---
        dailyChartInst = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: chartData.map(d => d.label), // Uses dynamic labels (e.g. "Jan", "Q1", "2025")
                datasets: [
                    {
                        type: 'line',
                        label: 'Target Goal',
                        data: Array(chartData.length).fill(targetValue),
                        borderColor: '#10b981',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        order: 1,
                        yAxisID: 'y'
                    },
                    {
                        type: 'line',
                        label: 'Profit Margin %',
                        data: chartData.map(d => d.margin),
                        borderColor: '#f97316',
                        backgroundColor: '#f97316',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y1',
                        order: 0
                    },
                    {
                        type: 'bar',
                        label: 'Sales Amount',
                        data: chartData.map(d => d.sales),
                        backgroundColor: 'rgba(37, 99, 235, 0.75)',
                        hoverBackgroundColor: 'rgba(37, 99, 235, 1)',
                        order: 2,
                        yAxisID: 'y'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: { borderDash: [5, 5] },
                        title: { display: true, text: 'Sales (₱)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: { callback: v => v + '%' },
                        title: { display: true, text: 'Margin (%)' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });

        // --- Chart 2: Company Breakdown (Unchanged) ---
        const neededHeight = Math.max(400, companyData.length * 35);
        document.getElementById('companyChartWrapper').style.height = neededHeight + 'px';

        companyChartInst = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: companyData.map(c => c.company),
                datasets: [{
                    label: 'Total Sales',
                    data: companyData.map(c => c.total_sales),
                    backgroundColor: '#3b82f6',
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false },
                    y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                }
            }
        });
    }

    function renderMatrix(data) {
        const tbody = document.getElementById('matrixBody');
        tbody.innerHTML = '';

        data.forEach((cat, index) => {
            const parentRow = document.createElement('tr');
            parentRow.className = 'category-row';
            parentRow.innerHTML = `
                <td><span class="toggle-icon">▶</span> ${cat.category}</td>
                <td style="text-align: right;">${cat.quantity.toLocaleString()}</td>
                <td style="text-align: right; font-weight:700;">${formatMoney(cat.total_sales)}</td>
                <td style="text-align: right; color: #059669; font-weight:600;">${formatMoney(cat.total_profit)}</td>
            `;
            parentRow.onclick = function() {
                this.classList.toggle('expanded');
                document.querySelectorAll(`.child-${index}`).forEach(r => r.classList.toggle('visible'));
            };
            tbody.appendChild(parentRow);

            cat.items.forEach(item => {
                const childRow = document.createElement('tr');
                childRow.className = `item-row child-${index}`;
                childRow.innerHTML = `
                    <td>${item.name}</td>
                    <td style="text-align: right;">${item.qty.toLocaleString()}</td>
                    <td style="text-align: right;">${formatMoney(item.sales)}</td>
                    <td style="text-align: right;">${formatMoney(item.profit)}</td>
                `;
                tbody.appendChild(childRow);
            });
        });
    }

    function resetFilters() {
        document.getElementById('periodFilter').value = 'month';
        document.getElementById('companyFilter').value = '';
        document.getElementById('targetSales').value = '50000';
        updateDashboard();
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateDashboard();
    });
</script>
</body>
</html>