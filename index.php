<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Dashboard - NAM Supply</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f3f2f1;
            padding: 20px;
        }

        .dashboard-container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 2px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        h1 {
            color: #323130;
            font-size: 24px;
            font-weight: 600;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 2px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }

        .btn-primary {
            background: #0078d4;
            color: white;
        }

        .btn-primary:hover {
            background: #106ebe;
        }

        .kpi-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .kpi-card {
            padding: 20px;
            background: white;
            border: 1px solid #edebe9;
            border-radius: 2px;
        }

        .kpi-value {
            font-size: 32px;
            font-weight: 700;
            color: #323130;
            margin-bottom: 5px;
        }

        .kpi-label {
            font-size: 14px;
            color: #605e5c;
            font-weight: 400;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border: 1px solid #edebe9;
            border-radius: 2px;
        }

        .chart-title {
            font-size: 14px;
            color: #323130;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .chart-legend {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            font-size: 12px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .bottom-section {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }

        .table-container {
            background: white;
            padding: 20px;
            border: 1px solid #edebe9;
            border-radius: 2px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: #f3f2f1;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            color: #323130;
            border-bottom: 1px solid #edebe9;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #f3f2f1;
            color: #323130;
        }

        tr:hover {
            background: #faf9f8;
        }

        .category-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
            font-size: 11px;
        }

        .category-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .category-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }

        .refresh-info {
            text-align: center;
            color: #605e5c;
            font-size: 12px;
            margin-top: 20px;
        }

        @media (max-width: 1200px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .bottom-section {
                grid-template-columns: 1fr;
            }

            .kpi-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <h1>📊 Sales Dashboard - NAM Supply</h1>
            <div style="display: flex; gap: 10px;">
                <a href="records.php" class="btn btn-secondary">📋 View Records</a>
                <a href="form.php" class="btn btn-primary">➕ Add New Entry</a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-container">
            <div class="kpi-card">
                <div class="kpi-value" id="totalSales">₱0</div>
                <div class="kpi-label">Total Sales</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" id="totalProfit">₱0</div>
                <div class="kpi-label">Total Profit</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" id="profitMargin">0%</div>
                <div class="kpi-label">Profit Margin %</div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- Daily Sales and Profit Margin Chart -->
            <div class="chart-container">
                <div class="chart-title">Total Sales and Profit Margin % by Day</div>
                <div class="chart-legend">
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #70ad47;"></div>
                        <span>Total Sales</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #4472c4;"></div>
                        <span>Profit Margin %</span>
                    </div>
                </div>
                <canvas id="dailyChart" height="80"></canvas>
            </div>

            <!-- Company Sales Chart -->
            <div class="chart-container">
                <div class="chart-title">Total Sales by COMPANY</div>
                <canvas id="companyChart" height="120"></canvas>
            </div>
        </div>

        <!-- Bottom Section -->
        <div class="bottom-section">
            <!-- Category Pie Chart -->
            <div class="chart-container">
                <div class="chart-title">Total Sales by CATEGORY</div>
                <canvas id="categoryChart" height="200"></canvas>
                <div class="category-legend" id="categoryLegend"></div>
            </div>

            <!-- Category Table -->
            <div class="table-container">
                <div class="chart-title">CATEGORY</div>
                <table id="categoryTable">
                    <thead>
                        <tr>
                            <th>CATEGORY</th>
                            <th style="text-align: right;">Sum of QUANTITY REQUESTED</th>
                            <th style="text-align: right;">Total Sales</th>
                            <th style="text-align: right;">Total Profit</th>
                        </tr>
                    </thead>
                    <tbody id="categoryTableBody">
                    </tbody>
                </table>
            </div>
        </div>

        <div class="refresh-info">
            Dashboard auto-refreshes every 30 seconds | Last updated: <span id="lastUpdate"></span>
        </div>
    </div>

    <script>
        // Color palette matching Power BI
        const categoryColors = [
            '#70ad47', '#4472c4', '#ffc000', '#c00000', '#5b9bd5',
            '#ed7d31', '#a5a5a5', '#264478', '#9e480e', '#636363'
        ];

        let dailyChart, companyChart, categoryChart;

        // Format currency
        function formatCurrency(value) {
            return '₱' + parseFloat(value).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Format number with commas
        function formatNumber(value) {
            return parseFloat(value).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Format large numbers (K, M)
        function formatLargeNumber(value) {
            if (value >= 1000000) {
                return '₱' + (value / 1000000).toFixed(2) + 'M';
            } else if (value >= 1000) {
                return '₱' + (value / 1000).toFixed(0) + 'K';
            }
            return '₱' + value.toFixed(0);
        }

        // Fetch and update dashboard
        async function updateDashboard() {
            try {
                const response = await fetch('api.php');
                const data = await response.json();

                // Update KPIs
                document.getElementById('totalSales').textContent = formatLargeNumber(data.stats.total_sales);
                document.getElementById('totalProfit').textContent = formatLargeNumber(data.stats.total_profit);
                document.getElementById('profitMargin').textContent = data.stats.profit_margin.toFixed(2) + '%';

                // Update Daily Chart
                updateDailyChart(data.daily_sales);

                // Update Company Chart
                updateCompanyChart(data.company_sales);

                // Update Category Chart and Table
                updateCategoryData(data.category_sales, data.total_quantity);

                // Update timestamp
                document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
            }
        }

        // Daily Sales Chart
        function updateDailyChart(dailySales) {
            const ctx = document.getElementById('dailyChart').getContext('2d');
            
            if (dailyChart) {
                dailyChart.destroy();
            }

            dailyChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dailySales.map(d => d.day),
                    datasets: [
                        {
                            label: 'Total Sales',
                            data: dailySales.map(d => d.sales),
                            borderColor: '#70ad47',
                            backgroundColor: 'rgba(112, 173, 71, 0.1)',
                            yAxisID: 'y',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Profit Margin %',
                            data: dailySales.map(d => d.profit_margin),
                            borderColor: '#4472c4',
                            backgroundColor: 'transparent',
                            yAxisID: 'y1',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Day'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Total Sales'
                            },
                            ticks: {
                                callback: function(value) {
                                    return formatLargeNumber(value);
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Profit Margin %'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                callback: function(value) {
                                    return (value * 100).toFixed(0) + '%';
                                }
                            },
                            max: 0.6
                        }
                    }
                }
            });
        }

        // Company Chart
        function updateCompanyChart(companySales) {
            const ctx = document.getElementById('companyChart').getContext('2d');
            
            if (companyChart) {
                companyChart.destroy();
            }

            companyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: companySales.map(c => c.company),
                    datasets: [{
                        label: 'Total Sales',
                        data: companySales.map(c => c.total_sales),
                        backgroundColor: '#70ad47'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Total Sales'
                            },
                            ticks: {
                                callback: function(value) {
                                    return formatLargeNumber(value);
                                }
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'COMPANY'
                            }
                        }
                    }
                }
            });
        }

        // Category Chart and Table
        function updateCategoryData(categorySales, totalQuantity) {
            const ctx = document.getElementById('categoryChart').getContext('2d');
            
            if (categoryChart) {
                categoryChart.destroy();
            }

            // Pie Chart
            categoryChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: categorySales.map(c => c.category),
                    datasets: [{
                        data: categorySales.map(c => c.total_sales),
                        backgroundColor: categoryColors.slice(0, categorySales.length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const percentage = categorySales[context.dataIndex].percentage;
                                    return label + ': ' + formatCurrency(value) + ' (' + percentage.toFixed(2) + '%)';
                                }
                            }
                        }
                    }
                }
            });

            // Update Legend
            const legendHtml = categorySales.map((cat, index) => `
                <div class="category-item">
                    <div class="category-color" style="background: ${categoryColors[index]};"></div>
                    <span>${cat.category}</span>
                </div>
            `).join('');
            document.getElementById('categoryLegend').innerHTML = legendHtml;

            // Update Table
            const tableHtml = categorySales.map(cat => `
                <tr>
                    <td>${cat.category}</td>
                    <td style="text-align: right;">${cat.quantity.toLocaleString()}</td>
                    <td style="text-align: right;">${formatCurrency(cat.total_sales)}</td>
                    <td style="text-align: right;">${formatCurrency(cat.total_profit)}</td>
                </tr>
            `).join('');
            
            const totalSales = categorySales.reduce((sum, cat) => sum + cat.total_sales, 0);
            const totalProfit = categorySales.reduce((sum, cat) => sum + cat.total_profit, 0);

            document.getElementById('categoryTableBody').innerHTML = tableHtml + `
                <tr style="font-weight: 700; background: #f3f2f1;">
                    <td>Total</td>
                    <td style="text-align: right;">${totalQuantity.toLocaleString()}</td>
                    <td style="text-align: right;">${formatCurrency(totalSales)}</td>
                    <td style="text-align: right;">${formatCurrency(totalProfit)}</td>
                </tr>
            `;
        }

        // Initial load
        updateDashboard();

        // Auto-refresh every 30 seconds
        setInterval(updateDashboard, 30000);
    </script>
</body>
</html>