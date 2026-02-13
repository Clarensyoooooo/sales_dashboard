<?php require_once 'config.php'; requireLogin(); requirePermission('manage_sales'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Records - NAM Supply</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;       
            --primary-dark: #1e40af;  
            --secondary: #f97316;     
            --bg: #f3f4f6;            
            --surface: #ffffff;       
            --text-main: #1f2937;     
            --text-light: #6b7280;    
            --border: #e5e7eb;        
            --success: #10b981;       
            --danger: #ef4444;        
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --radius: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text-main);
            padding: 0;
            min-height: 100vh;
        }

        .dashboard-container {
            max-width: 100%;
            margin: 20px auto;
            padding: 0 20px;
        }

        /* --- Buttons --- */
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
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
        .btn-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .btn-edit { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; margin-right: 5px; }

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
            min-width: 140px;
        }

        .filter-group label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .filter-input {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            background-color: #fff;
            width: 100%;
        }
        
        .filter-actions { display: flex; gap: 10px; }

        /* --- KPI Cards --- */
        .kpi-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .kpi-card {
            background: var(--surface);
            padding: 24px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border-left: 5px solid var(--primary);
        }
        .kpi-card.success { border-left-color: var(--success); }
        .kpi-card.warning { border-left-color: var(--secondary); }

        .kpi-value { font-size: 28px; font-weight: 700; color: var(--text-main); margin-top: 5px; }
        .kpi-label { font-size: 12px; color: var(--text-light); text-transform: uppercase; font-weight: 600; }

        /* --- Table --- */
        .table-card { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        
        /* IMPORTANT: SCROLLABLE TABLE */
        .table-container { 
            overflow-x: auto; 
            max-width: 100%;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 13px; 
            white-space: nowrap; 
        }
        
        thead { background: #f8fafc; border-bottom: 2px solid var(--border); }
        
        th { 
            padding: 14px 16px; 
            text-align: left; 
            font-weight: 700; 
            color: var(--text-light); 
            font-size: 11px; 
            text-transform: uppercase; 
            border-right: 1px solid #f1f5f9;
        }
        
        td { 
            padding: 12px 16px; 
            border-bottom: 1px solid #f1f5f9; 
            color: var(--text-main); 
            vertical-align: middle;
            border-right: 1px solid #f8fafc;
        }
        
        tbody tr:hover { background-color: #f8fafc; }

        /* Column Width Overrides for Readability */
        .col-item { min-width: 250px; white-space: normal; }
        .col-company { min-width: 180px; font-weight: 600; }
        .col-money { font-family: 'Consolas', monospace; font-weight: 600; }
        .col-remarks { min-width: 200px; white-space: normal; font-size: 12px; color: #666; }
        .col-address { min-width: 200px; white-space: normal; font-size: 12px; }

        /* Status Badges */
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-delivered { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }

        /* --- Pagination --- */
        .pagination { display: flex; justify-content: center; gap: 10px; padding: 20px; background: var(--surface); border-top: 1px solid var(--border); }
        .pagination button { padding: 8px 14px; border: 1px solid var(--border); background: white; border-radius: 6px; cursor: pointer; }

        /* --- Modal --- */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal.active { display: flex; }
        .modal-content { background: var(--surface); padding: 30px; border-radius: 16px; width: 95%; max-width: 1100px; max-height: 90vh; overflow-y: auto; }
        
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border); }
        .close-btn { font-size: 24px; cursor: pointer; background: none; border: none; }
        
        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .form-section-title { grid-column: 1 / -1; font-weight: 700; color: var(--primary); text-transform: uppercase; font-size: 12px; border-bottom: 1px solid #eee; margin-top: 20px; padding-bottom: 5px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%; font-size: 13px; }
        
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px; }
        .alert { padding: 10px; border-radius: 6px; margin-bottom: 15px; display: none; }
        .alert.show { display: block; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">

        <div class="kpi-container">
            <div class="kpi-card">
                <div class="kpi-label">Total Records</div>
                <div class="kpi-value" id="totalRecords">0</div>
            </div>
            <div class="kpi-card warning">
                <div class="kpi-label">Pending Delivery</div>
                <div class="kpi-value" id="pendingCount" style="color: #f97316;">0</div>
            </div>
            <div class="kpi-card success">
                <div class="kpi-label">Total Sales (Filtered)</div>
                <div class="kpi-value" id="filteredSales" style="color: var(--success);">₱0.00</div>
            </div>
        </div>

        <div class="alert alert-success" id="successAlert"></div>
        <div class="alert alert-error" id="errorAlert"></div>

        <div class="filter-bar">
            <div class="filter-group">
                <label>Status</label>
                <select id="filterStatus" class="filter-input" onchange="applyFilters()">
                    <option value="">All Statuses</option>
                    <option value="pending">⏳ Pending</option>
                    <option value="delivered">✅ Delivered</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Date Range</label>
                <div style="display:flex; gap:5px;">
                    <input type="date" id="dateFrom" class="filter-input">
                    <input type="date" id="dateTo" class="filter-input">
                </div>
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
                <label>Search</label>
                <input type="text" id="searchItem" class="filter-input" placeholder="Search item, PO, S/N, Remarks..." onkeyup="applyFilters()">
            </div>
            <div class="filter-actions">
                <button class="btn btn-primary" onclick="applyFilters()">🔍 Apply</button>
                <button class="btn btn-secondary" onclick="clearFilters()">🔄 Reset</button>
            </div>
        </div>

        <div class="table-card">
            <div class="table-container">
                <div id="loading" style="text-align:center; padding:30px;">Loading records...</div>
                
                <table id="recordsTable" style="display: none;">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Date</th>
                            <th>S/N</th>
                            <th>PO No.</th>
                            <th>Company</th>
                            <th>Address</th>
                            <th>TIN</th>
                            <th>Contact</th>
                            <th>Category</th>
                            <th>Item Description</th>
                            <th>Qty</th>
                            <th>Unit Cost</th>
                            <th>Total Cost</th>
                            <th>Unit Price</th>
                            <th>Total Sales</th>
                            <th>Income</th>
                            <th>Margin</th>
                            <th>Supplier</th>
                            <th>Delivered</th>
                            <th>Pay Term</th>
                            <th>Due Date</th>
                            <th>SI No.</th>
                            <th>Inv No.</th>
                            <th>Remarks</th>
                            <th style="position:sticky; right:0; background:#f8fafc; box-shadow: -2px 0 5px rgba(0,0,0,0.05);">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="recordsBody"></tbody>
                </table>
                <div id="noData" style="display: none; text-align:center; padding:30px; color:#666;">No records found.</div>
            </div>

            <div class="pagination" id="pagination" style="display: none;">
                <button onclick="changePage('first')">First</button>
                <button onclick="changePage('prev')">Prev</button>
                <span class="page-info">Page <span id="currentPage">1</span> of <span id="totalPages">1</span></span>
                <button onclick="changePage('next')">Next</button>
                <button onclick="changePage('last')">Last</button>
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
                    <div class="form-group"><label>Date *</label><input type="date" id="editDate" required></div>
                    <div class="form-group"><label>Serial No (S/N)</label><input type="text" id="editSN"></div>
                    <div class="form-group"><label>PO Number</label><input type="text" id="editPO"></div>

                    <div class="form-section-title">Client Information</div>
                    <div class="form-group full-width"><label>Company Name *</label><input type="text" id="editCompany" required></div>
                    <div class="form-group full-width"><label>Address</label><input type="text" id="editAddress"></div>
                    <div class="form-group"><label>TIN</label><input type="text" id="editTIN"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Contact Person</label><input type="text" id="editContact"></div>

                    <div class="form-section-title">Product Details</div>
                    <div class="form-group">
                        <label>Category *</label>
                        <select id="editCategory" required>
                            <option value="">Select Category</option>
                            <option value="OFFICE SUPPLIES">OFFICE SUPPLIES</option>
                            <option value="CLEANING MATERIALS">CLEANING MATERIALS</option>
                            <option value="CONSUMABLES">CONSUMABLES</option>
                            <option value="OFFICE TOOLS AND EQUIPMENT">OFFICE TOOLS</option>
                            <option value="PPE">PPE</option>
                            <option value="MATERIALS">MATERIALS</option>
                            <option value="COMPANY UNIFORM">UNIFORMS</option>
                            <option value="OFFICE FURNITURE & FIXTURES">FURNITURE</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;"><label>Item Name *</label><input type="text" id="editItem" required></div>

                    <div class="form-section-title">Financials</div>
                    <div class="form-group"><label>Quantity *</label><input type="number" id="editQuantity" required min="1" onchange="calculateEdit()"></div>
                    <div class="form-group"><label>Supp. Price *</label><input type="number" id="editSupplierPrice" required step="0.01" onchange="calculateEdit()"></div>
                    <div class="form-group"><label>NAM Price *</label><input type="number" id="editNAMPrice" required step="0.01" onchange="calculateEdit()"></div>
                    
                    <div class="form-group"><label>Total Actual</label><input type="number" id="editTotalActual" readonly style="background:#eee;"></div>
                    <div class="form-group"><label>Total Sales</label><input type="number" id="editTotalNAM" readonly style="background:#eee;"></div>
                    <div class="form-group"><label>Income</label><input type="number" id="editIncome" readonly style="background:#eee;"></div>
                    
                    <div class="form-section-title">Logistics & Payment</div>
                    <div class="form-group"><label>Date Delivered</label><input type="date" id="editDateDelivered"></div>
                    <div class="form-group"><label>Payment Terms</label><input type="text" id="editPaymentTerm"></div>
                    <div class="form-group"><label>Due Date</label><input type="date" id="editDueDate"></div>
                    <div class="form-group"><label>Supplier Name</label><input type="text" id="editSupplier"></div>
                    <div class="form-group"><label>SI Number</label><input type="text" id="editSINumber"></div>
                    <div class="form-group"><label>Invoice No</label><input type="text" id="editSalesInvoiceNo"></div>
                    
                    <div class="form-group full-width"><label>Remarks</label><textarea id="editRemarks" rows="2"></textarea></div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let allRecords = [];
        let filteredRecords = [];
        let currentPage = 1;
        const perPage = 50;

        function formatCurrency(val) {
            return '₱' + parseFloat(val || 0).toLocaleString('en-PH', {minimumFractionDigits: 2});
        }

        async function loadRecords() {
            try {
                const res = await fetch('get_records.php');
                const data = await res.json();
                allRecords = data.records;
                
                // Populate filters
                const compSelect = document.getElementById('filterCompany');
                const catSelect = document.getElementById('filterCategory');
                
                compSelect.innerHTML = '<option value="">All Companies</option>';
                catSelect.innerHTML = '<option value="">All Categories</option>';

                data.companies.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c; opt.textContent = c; compSelect.appendChild(opt);
                });
                data.categories.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c; opt.textContent = c; catSelect.appendChild(opt);
                });

                applyFilters();
            } catch(e) {
                console.error(e);
                alert("Error loading records");
            }
        }

        function applyFilters() {
            const status = document.getElementById('filterStatus').value;
            const company = document.getElementById('filterCompany').value;
            const category = document.getElementById('filterCategory').value;
            const search = document.getElementById('searchItem').value.toLowerCase();
            const dFrom = document.getElementById('dateFrom').value;
            const dTo = document.getElementById('dateTo').value;

            filteredRecords = allRecords.filter(r => {
                // Status Logic
                const isDelivered = (r.date_delivered && r.date_delivered !== '0000-00-00');
                if (status === 'pending' && isDelivered) return false;
                if (status === 'delivered' && !isDelivered) return false;

                if (company && r.company !== company) return false;
                if (category && r.category !== category) return false;
                if (search && !r.item.toLowerCase().includes(search) && !r.po_number.toLowerCase().includes(search) && !r.remarks.toLowerCase().includes(search)) return false;
                if (dFrom && r.date < dFrom) return false;
                if (dTo && r.date > dTo) return false;

                return true;
            });

            // Update KPIs
            document.getElementById('totalRecords').textContent = filteredRecords.length.toLocaleString();
            
            const totalSales = filteredRecords.reduce((sum, r) => sum + parseFloat(r.total_nam_amount || 0), 0);
            document.getElementById('filteredSales').textContent = formatCurrency(totalSales);

            const pending = filteredRecords.filter(r => !r.date_delivered || r.date_delivered === '0000-00-00').length;
            document.getElementById('pendingCount').textContent = pending;

            currentPage = 1;
            renderTable();
        }

        function clearFilters() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterCompany').value = '';
            document.getElementById('filterCategory').value = '';
            document.getElementById('searchItem').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            applyFilters();
        }

        function renderTable() {
            const tbody = document.getElementById('recordsBody');
            tbody.innerHTML = '';
            
            document.getElementById('loading').style.display = 'none';
            document.getElementById('recordsTable').style.display = 'table';

            if(filteredRecords.length === 0) {
                document.getElementById('noData').style.display = 'block';
                document.getElementById('recordsTable').style.display = 'none';
                document.getElementById('pagination').style.display = 'none';
                return;
            } else {
                document.getElementById('noData').style.display = 'none';
            }

            const totalPages = Math.ceil(filteredRecords.length / perPage);
            const start = (currentPage - 1) * perPage;
            const end = start + perPage;
            const pageData = filteredRecords.slice(start, end);

            pageData.forEach(r => {
                const tr = document.createElement('tr');
                const isDelivered = (r.date_delivered && r.date_delivered !== '0000-00-00');

                tr.innerHTML = `
                    <td><span class="status-badge ${isDelivered ? 'status-delivered' : 'status-pending'}">${isDelivered ? '✅ Delivered' : '⏳ Pending'}</span></td>
                    <td>${r.date}</td>
                    <td>${r.sn || ''}</td>
                    <td>${r.po_number || ''}</td>
                    <td class="col-company">${r.company}</td>
                    <td class="col-address">${r.address || ''}</td>
                    <td>${r.tin || ''}</td>
                    <td>${r.contact_person_contact || ''}</td>
                    <td>${r.category}</td>
                    <td class="col-item">${r.item}</td>
                    <td style="text-align:center;">${r.quantity_requested}</td>
                    <td class="col-money">${formatCurrency(r.suppliers_price)}</td>
                    <td class="col-money">${formatCurrency(r.total_actual_amount)}</td>
                    <td class="col-money">${formatCurrency(r.nam_unit_price)}</td>
                    <td class="col-money" style="color:#2563eb;">${formatCurrency(r.total_nam_amount)}</td>
                    <td class="col-money" style="color:#10b981;">${formatCurrency(r.income)}</td>
                    <td>${parseFloat(r.income_percent || 0).toFixed(1)}%</td>
                    <td>${r.supplier || ''}</td>
                    <td style="color:${isDelivered ? '#166534' : '#9ca3af'}; font-weight:600;">${isDelivered ? r.date_delivered : '-'}</td>
                    <td>${r.payment_term || ''}</td>
                    <td>${r.due_date || ''}</td>
                    <td>${r.si_number || ''}</td>
                    <td>${r.sales_invoice_no || ''}</td>
                    <td class="col-remarks">${r.remarks || ''}</td>
                    <td style="position:sticky; right:0; background:white; border-left:1px solid #eee;">
                        <button class="btn btn-edit" onclick="editRecord(${r.id})">✏️</button>
                        <button class="btn btn-danger" onclick="deleteRecord(${r.id})">🗑️</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('pagination').style.display = 'flex';
            document.getElementById('currentPage').textContent = currentPage;
            document.getElementById('totalPages').textContent = totalPages;
        }

        function changePage(action) {
            const totalPages = Math.ceil(filteredRecords.length / perPage);
            if(action === 'first') currentPage = 1;
            else if(action === 'prev' && currentPage > 1) currentPage--;
            else if(action === 'next' && currentPage < totalPages) currentPage++;
            else if(action === 'last') currentPage = totalPages;
            renderTable();
        }

        function editRecord(id) {
            const r = allRecords.find(item => item.id == id);
            if(!r) return;

            document.getElementById('editId').value = r.id;
            document.getElementById('editDate').value = r.date;
            document.getElementById('editSN').value = r.sn;
            document.getElementById('editPO').value = r.po_number;
            document.getElementById('editCompany').value = r.company;
            document.getElementById('editAddress').value = r.address;
            document.getElementById('editTIN').value = r.tin;
            document.getElementById('editContact').value = r.contact_person_contact;
            document.getElementById('editCategory').value = r.category;
            document.getElementById('editItem').value = r.item;
            document.getElementById('editQuantity').value = r.quantity_requested;
            document.getElementById('editSupplierPrice').value = r.suppliers_price;
            document.getElementById('editNAMPrice').value = r.nam_unit_price;
            document.getElementById('editSupplier').value = r.supplier;
            document.getElementById('editDateDelivered').value = r.date_delivered; 
            document.getElementById('editPaymentTerm').value = r.payment_term;
            document.getElementById('editDueDate').value = r.due_date;
            document.getElementById('editSINumber').value = r.si_number;
            document.getElementById('editSalesInvoiceNo').value = r.sales_invoice_no;
            document.getElementById('editRemarks').value = r.remarks;

            calculateEdit();
            document.getElementById('editModal').classList.add('active');
        }

        function calculateEdit() {
            const qty = parseFloat(document.getElementById('editQuantity').value) || 0;
            const sPrice = parseFloat(document.getElementById('editSupplierPrice').value) || 0;
            const nPrice = parseFloat(document.getElementById('editNAMPrice').value) || 0;

            const tAct = qty * sPrice;
            const tNam = qty * nPrice;
            
            document.getElementById('editTotalActual').value = tAct.toFixed(2);
            document.getElementById('editTotalNAM').value = tNam.toFixed(2);
            document.getElementById('editIncome').value = (tNam - tAct).toFixed(2);
        }

        async function saveEdit(e) {
            e.preventDefault();
            const formData = new FormData();
            
            const map = {
                'id': 'editId', 'date': 'editDate', 'sn': 'editSN', 'po_number': 'editPO',
                'company': 'editCompany', 'address': 'editAddress', 'tin': 'editTIN',
                'contact_person_contact': 'editContact', 'category': 'editCategory', 'item': 'editItem',
                'quantity_requested': 'editQuantity', 'suppliers_price': 'editSupplierPrice',
                'nam_unit_price': 'editNAMPrice', 'supplier': 'editSupplier', 'date_delivered': 'editDateDelivered',
                'payment_term': 'editPaymentTerm', 'due_date': 'editDueDate', 'si_number': 'editSINumber',
                'sales_invoice_no': 'editSalesInvoiceNo', 'remarks': 'editRemarks'
            };

            for(const [key, id] of Object.entries(map)) {
                formData.append(key, document.getElementById(id).value);
            }

            try {
                const res = await fetch('update_record.php', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    closeModal();
                    loadRecords();
                    showAlert("Record updated!", "success");
                } else {
                    showAlert(data.message || "Error updating", "error");
                }
            } catch(err) {
                showAlert("Network error", "error");
            }
        }

        async function deleteRecord(id) {
            if(!confirm("Delete this record?")) return;
            try {
                const res = await fetch('delete_record.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'id=' + id
                });
                const data = await res.json();
                if(data.success) {
                    loadRecords();
                    showAlert("Deleted successfully", "success");
                } else {
                    showAlert("Error deleting", "error");
                }
            } catch(e) {
                showAlert("Network error", "error");
            }
        }

        function closeModal() { document.getElementById('editModal').classList.remove('active'); }
        function showAlert(msg, type) {
            const el = document.getElementById(type === 'success' ? 'successAlert' : 'errorAlert');
            el.textContent = msg;
            el.classList.add('show');
            setTimeout(() => el.classList.remove('show'), 3000);
        }

        document.getElementById('editModal').addEventListener('click', (e) => {
            if(e.target.id === 'editModal') closeModal();
        });

        loadRecords();
    </script>
</body>
</html>