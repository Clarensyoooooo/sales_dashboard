<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Records - NAM Supply</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;       /* Royal Blue */
            --primary-dark: #1e40af;  /* Navy Blue */
            --secondary: #f97316;     /* Orange */
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
            max-width: 100%;
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
            flex-wrap: wrap;
            gap: 15px;
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-secondary { background: white; color: var(--text-main); border-color: var(--border); }
        .btn-secondary:hover { background: #f9fafb; border-color: #d1d5db; }
        .btn-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; padding: 6px 12px; font-size: 13px; }
        .btn-danger:hover { background: #fecaca; }
        .btn-edit { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; padding: 6px 12px; font-size: 13px; margin-right: 5px; }
        .btn-edit:hover { background: #c7d2fe; }

        /* --- Filter Bar --- */
        .filter-bar {
            background: var(--surface);
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 150px;
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
            background-color: #fff;
            width: 100%;
        }
        
        .filter-input:focus { outline: none; border-color: var(--primary); ring: 2px solid var(--primary); }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        /* --- KPI Cards --- */
        .kpi-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
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
        .kpi-card.success { border-left-color: var(--success); }
        .kpi-card.warning { border-left-color: var(--secondary); }

        .kpi-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-main);
            margin-top: 5px;
        }

        .kpi-label {
            font-size: 13px;
            color: var(--text-light);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        /* --- Table --- */
        .table-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            white-space: nowrap;
        }

        thead {
            background: #f8fafc;
            border-bottom: 2px solid var(--border);
        }

        th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-light);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--bg);
            color: var(--text-main);
            vertical-align: middle;
        }

        tbody tr:hover {
            background-color: #f1f5f9;
        }

        /* --- Pagination --- */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background: var(--surface);
            border-top: 1px solid var(--border);
        }

        .pagination button {
            padding: 8px 14px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-main);
            transition: all 0.2s;
        }

        .pagination button:hover:not(:disabled) {
            background: #f3f4f6;
            border-color: #d1d5db;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-info {
            font-size: 14px;
            color: var(--text-light);
            font-weight: 500;
        }

        /* --- Modal --- */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            backdrop-filter: blur(2px);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: var(--surface);
            padding: 30px;
            border-radius: 16px;
            max-width: 1000px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .modal-header h2 {
            font-size: 20px;
            color: var(--text-main);
            font-weight: 700;
        }

        .close-btn {
            font-size: 24px;
            color: var(--text-light);
            background: none;
            border: none;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .close-btn:hover { background: #f3f4f6; color: var(--text-main); }

        /* Form Grid in Modal */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .form-section-title {
            grid-column: 1 / -1;
            font-size: 14px;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 15px;
            margin-bottom: 5px;
            padding-bottom: 5px;
            border-bottom: 1px solid var(--border);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full-width { grid-column: 1 / -1; }
        .form-group.two-thirds { grid-column: span 2; }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-light);
        }

        .form-group input, .form-group select, .form-group textarea {
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            background: #f9fafb;
            transition: border-color 0.2s;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .calculated-field {
            background-color: #eff6ff !important;
            color: var(--primary-dark);
            font-weight: 600;
            border-color: #bfdbfe !important;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        /* Utilities */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: none;
        }
        .alert.show { display: block; animation: slideIn 0.3s ease; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .loading, .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
            font-weight: 500;
        }

        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* Responsive */
        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .header { flex-direction: column; align-items: stretch; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-actions { margin-top: 10px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <h1>📋 Sales Records Management</h1>
            <div style="display: flex; gap: 10px;">
                <a href="index.php" class="btn btn-secondary">📊 Dashboard</a>
                <a href="products.php" class="btn btn-secondary">📦 Price List</a>
                <a href="form.php" class="btn btn-primary">➕ Add New Entry</a>
            </div>
        </div>

        <div class="alert alert-success" id="successAlert"></div>
        <div class="alert alert-error" id="errorAlert"></div>

        <div class="filter-bar">
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" id="dateFrom" class="filter-input">
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" id="dateTo" class="filter-input">
            </div>
            <div class="filter-group">
                <label>Company</label>
                <select id="filterCompany" class="filter-input">
                    <option value="">All Companies</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Category</label>
                <select id="filterCategory" class="filter-input">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div class="filter-group" style="flex: 2;">
                <label>Search Item</label>
                <input type="text" id="searchItem" class="filter-input" placeholder="Search item name...">
            </div>
            <div class="filter-actions">
                <button class="btn btn-primary" onclick="applyFilters()">🔍 Apply</button>
                <button class="btn btn-secondary" onclick="clearFilters()">🔄 Reset</button>
            </div>
        </div>

        <div class="kpi-container">
            <div class="kpi-card">
                <div class="kpi-label">Total Records</div>
                <div class="kpi-value" id="totalRecords">0</div>
            </div>
            <div class="kpi-card success">
                <div class="kpi-label">Total Sales (Filtered)</div>
                <div class="kpi-value" id="filteredSales" style="color: var(--success);">₱0.00</div>
            </div>
            <div class="kpi-card success">
                <div class="kpi-label">Total Profit (Filtered)</div>
                <div class="kpi-value" id="filteredProfit" style="color: var(--success);">₱0.00</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-container">
                <div class="loading" id="loading">Loading records...</div>
                <table id="recordsTable" style="display: none;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>S/N</th>
                            <th>PO Number</th>
                            <th>Company</th>
                            <th>Category</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Supplier</th>
                            <th>Supplier Price</th>
                            <th>Total Cost</th>
                            <th>NAM Price</th>
                            <th>Total Sales</th>
                            <th>Income</th>
                            <th>Profit %</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="recordsBody"></tbody>
                </table>
                <div class="no-data" id="noData" style="display: none;">No records found. Try adjusting your filters.</div>
            </div>

            <div class="pagination" id="pagination" style="display: none;">
                <button onclick="changePage('first')">⏮️ First</button>
                <button onclick="changePage('prev')">◀️ Prev</button>
                <span class="page-info">Page <span id="currentPage">1</span> of <span id="totalPages">1</span></span>
                <button onclick="changePage('next')">Next ▶️</button>
                <button onclick="changePage('last')">Last ⏭️</button>
            </div>
        </div>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Edit Record</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="editForm" onsubmit="saveEdit(event)">
                <input type="hidden" id="editId">
                <div class="form-grid">
                    <div class="form-section-title">Record Details</div>
                    <div class="form-group">
                        <label>Date <span style="color: var(--danger);">*</span></label>
                        <input type="date" id="editDate" required>
                    </div>
                    <div class="form-group">
                        <label>Serial No (S/N)</label>
                        <input type="text" id="editSN">
                    </div>
                    <div class="form-group">
                        <label>PO Number</label>
                        <input type="text" id="editPO">
                    </div>

                    <div class="form-section-title">Client Information</div>
                    <div class="form-group full-width">
                        <label>Company Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="editCompany" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Address</label>
                        <input type="text" id="editAddress">
                    </div>
                    <div class="form-group">
                        <label>TIN</label>
                        <input type="text" id="editTIN">
                    </div>
                    <div class="form-group two-thirds">
                        <label>Contact Person / Details</label>
                        <input type="text" id="editContact">
                    </div>

                    <div class="form-section-title">Product Details</div>
                    <div class="form-group">
                        <label>Category <span style="color: var(--danger);">*</span></label>
                        <select id="editCategory" required>
                            <option value="">Select Category</option>
                            <option value="OFFICE TOOLS AND EQUIPMENT">OFFICE TOOLS AND EQUIPMENT</option>
                            <option value="CONSUMABLES">CONSUMABLES</option>
                            <option value="CLEANING MATERIALS">CLEANING MATERIALS</option>
                            <option value="OFFICE SUPPLY">OFFICE SUPPLY</option>
                            <option value="MATERIALS">MATERIALS</option>
                            <option value="OFFICE FURNITURE & FIXTURES">OFFICE FURNITURE & FIXTURES</option>
                            <option value="COMPANY UNIFORM">COMPANY UNIFORM</option>
                            <option value="PPE">PPE</option>
                            <option value="OFFICE SUPPLIES">OFFICE SUPPLIES</option>
                        </select>
                    </div>
                    <div class="form-group two-thirds">
                        <label>Item Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="editItem" required>
                    </div>

                    <div class="form-section-title">Financials</div>
                    <div class="form-group">
                        <label>Quantity <span style="color: var(--danger);">*</span></label>
                        <input type="number" id="editQuantity" required min="1" onchange="calculateEdit()">
                    </div>
                    <div class="form-group">
                        <label>Supplier Price (₱) <span style="color: var(--danger);">*</span></label>
                        <input type="number" id="editSupplierPrice" required step="0.01" onchange="calculateEdit()">
                    </div>
                    <div class="form-group">
                        <label>NAM Unit Price (₱) <span style="color: var(--danger);">*</span></label>
                        <input type="number" id="editNAMPrice" required step="0.01" onchange="calculateEdit()">
                    </div>
                    
                    <div class="form-group">
                        <label>Total Actual Cost</label>
                        <input type="number" id="editTotalActual" readonly class="calculated-field" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Total NAM Amount</label>
                        <input type="number" id="editTotalNAM" readonly class="calculated-field" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Profit (Income)</label>
                        <input type="number" id="editIncome" readonly class="calculated-field" step="0.01">
                    </div>
                    
                    <div class="form-group">
                        <label>Supplier Name</label>
                        <input type="text" id="editSupplier">
                    </div>

                    <div class="form-section-title">Logistics & Payment</div>
                    <div class="form-group">
                        <label>Date Delivered</label>
                        <input type="date" id="editDateDelivered">
                    </div>
                    <div class="form-group">
                        <label>Payment Terms (Days)</label>
                        <input type="text" id="editPaymentTerm">
                    </div>
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" id="editDueDate">
                    </div>
                    <div class="form-group">
                        <label>SI Number</label>
                        <input type="text" id="editSINumber">
                    </div>
                    <div class="form-group">
                        <label>Sales Invoice No</label>
                        <input type="text" id="editSalesInvoiceNo">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Remarks</label>
                        <textarea id="editRemarks" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Save All Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let totalPages = 1;
        let recordsPerPage = 50;
        let allRecords = [];
        let filteredRecords = [];

        function formatCurrency(value) {
            return '₱' + parseFloat(value || 0).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        async function loadRecords() {
            try {
                document.getElementById('loading').style.display = 'block';
                document.getElementById('recordsTable').style.display = 'none';
                document.getElementById('noData').style.display = 'none';

                const response = await fetch('get_records.php');
                const data = await response.json();
                
                allRecords = data.records;
                populateFilters(data.companies, data.categories);
                applyFilters();
                
            } catch (error) {
                console.error('Error loading records:', error);
                showAlert('Error loading records', 'error');
            }
        }

        function populateFilters(companies, categories) {
            const companySelect = document.getElementById('filterCompany');
            const categorySelect = document.getElementById('filterCategory');
            
            companySelect.innerHTML = '<option value="">All Companies</option>';
            categorySelect.innerHTML = '<option value="">All Categories</option>';
            
            companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company;
                option.textContent = company;
                companySelect.appendChild(option);
            });
            
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category;
                option.textContent = category;
                categorySelect.appendChild(option);
            });
        }

        function applyFilters() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const company = document.getElementById('filterCompany').value;
            const category = document.getElementById('filterCategory').value;
            const searchItem = document.getElementById('searchItem').value.toLowerCase();

            filteredRecords = allRecords.filter(record => {
                let match = true;
                if (dateFrom && record.date < dateFrom) match = false;
                if (dateTo && record.date > dateTo) match = false;
                if (company && record.company !== company) match = false;
                if (category && record.category !== category) match = false;
                if (searchItem && !record.item.toLowerCase().includes(searchItem)) match = false;
                return match;
            });

            currentPage = 1;
            displayRecords();
        }

        function clearFilters() {
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            document.getElementById('filterCompany').value = '';
            document.getElementById('filterCategory').value = '';
            document.getElementById('searchItem').value = '';
            applyFilters();
        }

        function displayRecords() {
            const tbody = document.getElementById('recordsBody');
            tbody.innerHTML = '';

            if (filteredRecords.length === 0) {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('recordsTable').style.display = 'none';
                document.getElementById('noData').style.display = 'block';
                document.getElementById('pagination').style.display = 'none';
                updateStats();
                return;
            }

            totalPages = Math.ceil(filteredRecords.length / recordsPerPage);
            const start = (currentPage - 1) * recordsPerPage;
            const end = start + recordsPerPage;
            const pageRecords = filteredRecords.slice(start, end);

            pageRecords.forEach(record => {
                const row = document.createElement('tr');
                
                row.innerHTML = `
                    <td>${record.date || ''}</td>
                    <td>${record.sn || ''}</td>
                    <td>${record.po_number || ''}</td>
                    <td>${record.company || ''}</td>
                    <td>${record.category || ''}</td>
                    <td><div style="max-width:200px; overflow:hidden; text-overflow:ellipsis;">${record.item || ''}</div></td>
                    <td>${record.quantity_requested || 0}</td>
                    <td>${record.supplier || ''}</td>
                    <td>${formatCurrency(record.suppliers_price)}</td>
                    <td>${formatCurrency(record.total_actual_amount)}</td>
                    <td>${formatCurrency(record.nam_unit_price)}</td>
                    <td style="font-weight:600; color:var(--primary);">${formatCurrency(record.total_nam_amount)}</td>
                    <td style="font-weight:600; color:var(--success);">${formatCurrency(record.income)}</td>
                    <td>${parseFloat(record.income_percent || 0).toFixed(1)}%</td>
                    <td>
                        <button class="btn btn-edit" onclick="editRecord(${record.id})">✏️</button>
                        <button class="btn btn-danger" onclick="deleteRecord(${record.id})">🗑️</button>
                    </td>
                `;
                tbody.appendChild(row);
            });

            document.getElementById('loading').style.display = 'none';
            document.getElementById('recordsTable').style.display = 'table';
            document.getElementById('noData').style.display = 'none';
            document.getElementById('pagination').style.display = 'flex';
            
            document.getElementById('currentPage').textContent = currentPage;
            document.getElementById('totalPages').textContent = totalPages;
            
            updateStats();
        }

        function updateStats() {
            const totalRecords = filteredRecords.length;
            const totalSales = filteredRecords.reduce((sum, r) => sum + parseFloat(r.total_nam_amount || 0), 0);
            const totalProfit = filteredRecords.reduce((sum, r) => sum + parseFloat(r.income || 0), 0);

            document.getElementById('totalRecords').textContent = totalRecords.toLocaleString();
            document.getElementById('filteredSales').textContent = formatCurrency(totalSales);
            document.getElementById('filteredProfit').textContent = formatCurrency(totalProfit);
        }

        function changePage(action) {
            if (action === 'first') currentPage = 1;
            else if (action === 'prev' && currentPage > 1) currentPage--;
            else if (action === 'next' && currentPage < totalPages) currentPage++;
            else if (action === 'last') currentPage = totalPages;
            
            displayRecords();
        }

        function editRecord(id) {
            const record = allRecords.find(r => r.id == id);
            if (!record) return;

            document.getElementById('editId').value = record.id;
            document.getElementById('editDate').value = record.date;
            document.getElementById('editSN').value = record.sn || '';
            document.getElementById('editPO').value = record.po_number || '';
            document.getElementById('editCompany').value = record.company || '';
            document.getElementById('editAddress').value = record.address || '';
            document.getElementById('editTIN').value = record.tin || '';
            document.getElementById('editContact').value = record.contact_person_contact || '';
            document.getElementById('editCategory').value = record.category || '';
            document.getElementById('editItem').value = record.item || '';
            document.getElementById('editQuantity').value = record.quantity_requested || 0;
            document.getElementById('editSupplierPrice').value = record.suppliers_price || 0;
            document.getElementById('editNAMPrice').value = record.nam_unit_price || 0;
            document.getElementById('editSupplier').value = record.supplier || '';
            document.getElementById('editDateDelivered').value = record.date_delivered || '';
            document.getElementById('editPaymentTerm').value = record.payment_term || '';
            document.getElementById('editDueDate').value = record.due_date || '';
            document.getElementById('editSINumber').value = record.si_number || '';
            document.getElementById('editSalesInvoiceNo').value = record.sales_invoice_no || '';
            document.getElementById('editRemarks').value = record.remarks || '';

            calculateEdit();
            document.getElementById('editModal').classList.add('active');
        }

        function calculateEdit() {
            const qty = parseFloat(document.getElementById('editQuantity').value) || 0;
            const supplierPrice = parseFloat(document.getElementById('editSupplierPrice').value) || 0;
            const namPrice = parseFloat(document.getElementById('editNAMPrice').value) || 0;

            const totalActual = qty * supplierPrice;
            const totalNAM = qty * namPrice;
            const income = totalNAM - totalActual;

            document.getElementById('editTotalActual').value = totalActual.toFixed(2);
            document.getElementById('editTotalNAM').value = totalNAM.toFixed(2);
            document.getElementById('editIncome').value = income.toFixed(2);
        }

        async function saveEdit(event) {
            event.preventDefault();
            const formData = new FormData();
            
            // Append all fields (same as before)
            formData.append('id', document.getElementById('editId').value);
            formData.append('date', document.getElementById('editDate').value);
            formData.append('sn', document.getElementById('editSN').value);
            formData.append('po_number', document.getElementById('editPO').value);
            formData.append('company', document.getElementById('editCompany').value);
            formData.append('address', document.getElementById('editAddress').value);
            formData.append('tin', document.getElementById('editTIN').value);
            formData.append('contact_person_contact', document.getElementById('editContact').value);
            formData.append('category', document.getElementById('editCategory').value);
            formData.append('item', document.getElementById('editItem').value);
            formData.append('quantity_requested', document.getElementById('editQuantity').value);
            formData.append('suppliers_price', document.getElementById('editSupplierPrice').value);
            formData.append('nam_unit_price', document.getElementById('editNAMPrice').value);
            formData.append('supplier', document.getElementById('editSupplier').value);
            formData.append('date_delivered', document.getElementById('editDateDelivered').value);
            formData.append('payment_term', document.getElementById('editPaymentTerm').value);
            formData.append('due_date', document.getElementById('editDueDate').value);
            formData.append('si_number', document.getElementById('editSINumber').value);
            formData.append('sales_invoice_no', document.getElementById('editSalesInvoiceNo').value);
            formData.append('remarks', document.getElementById('editRemarks').value);

            try {
                const response = await fetch('update_record.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    showAlert('Record updated successfully!', 'success');
                    closeModal();
                    loadRecords();
                } else {
                    showAlert('Error updating record: ' + result.message, 'error');
                }
            } catch (error) {
                showAlert('Error updating record', 'error');
            }
        }

        async function deleteRecord(id) {
            if (!confirm('Are you sure you want to delete this record?')) return;
            try {
                const response = await fetch('delete_record.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + id
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('Record deleted successfully!', 'success');
                    loadRecords();
                } else {
                    showAlert('Error deleting record', 'error');
                }
            } catch (error) {
                showAlert('Error deleting record', 'error');
            }
        }

        function closeModal() { document.getElementById('editModal').classList.remove('active'); }
        function showAlert(message, type) {
            const alertId = type === 'success' ? 'successAlert' : 'errorAlert';
            const alertEl = document.getElementById(alertId);
            alertEl.textContent = message;
            alertEl.classList.add('show');
            setTimeout(() => { alertEl.classList.remove('show'); }, 5000);
        }
        
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        loadRecords();
    </script>
</body>
</html>