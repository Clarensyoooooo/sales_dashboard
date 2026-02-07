<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Records - NAM Supply</title>
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

        .container {
            max-width: 1800px;
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
            flex-wrap: wrap;
            gap: 15px;
        }

        h1 {
            color: #323130;
            font-size: 28px;
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
            transition: background 0.3s;
        }

        .btn-primary {
            background: #0078d4;
            color: white;
        }

        .btn-primary:hover {
            background: #106ebe;
        }

        .btn-secondary {
            background: #f3f2f1;
            color: #323130;
            border: 1px solid #8a8886;
        }

        .btn-secondary:hover {
            background: #e1dfdd;
        }

        .btn-success {
            background: #107c10;
            color: white;
        }

        .btn-success:hover {
            background: #0e6b0e;
        }

        .btn-danger {
            background: #d13438;
            color: white;
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-danger:hover {
            background: #a4262c;
        }

        .btn-edit {
            background: #0078d4;
            color: white;
            padding: 5px 10px;
            font-size: 12px;
            margin-right: 5px;
        }

        .btn-edit:hover {
            background: #106ebe;
        }

        .filter-section {
            background: #faf9f8;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #edebe9;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #323130;
            margin-bottom: 5px;
        }

        .filter-group input,
        .filter-group select {
            padding: 8px;
            border: 1px solid #8a8886;
            border-radius: 2px;
            font-size: 14px;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f3f2f1;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .stats-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stats-value {
            font-weight: 700;
            color: #0078d4;
        }

        .table-container {
            overflow-x: auto;
            border: 1px solid #edebe9;
            border-radius: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead {
            position: sticky;
            top: 0;
            background: #f3f2f1;
            z-index: 10;
        }

        th {
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            color: #323130;
            border-bottom: 2px solid #0078d4;
            white-space: nowrap;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #f3f2f1;
            color: #323130;
        }

        tbody tr:hover {
            background: #faf9f8;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }

        .pagination button {
            padding: 8px 15px;
            border: 1px solid #8a8886;
            background: white;
            cursor: pointer;
            border-radius: 2px;
        }

        .pagination button:hover:not(:disabled) {
            background: #f3f2f1;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination .page-info {
            padding: 8px 15px;
            color: #605e5c;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 4px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #0078d4;
        }

        .modal-header h2 {
            color: #323130;
            font-size: 24px;
        }

        .close-btn {
            font-size: 28px;
            cursor: pointer;
            color: #605e5c;
            background: none;
            border: none;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-btn:hover {
            color: #323130;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 600;
            color: #323130;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 8px;
            border: 1px solid #8a8886;
            border-radius: 2px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }

        .calculated-field {
            background: #f3f2f1;
            font-weight: 600;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #edebe9;
        }

        .alert {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #dff6dd;
            color: #107c10;
            border: 1px solid #107c10;
        }

        .alert-error {
            background: #fde7e9;
            color: #a4262c;
            border: 1px solid #a4262c;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #605e5c;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #605e5c;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: stretch;
            }

            .stats-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Sales Records Management</h1>
            <div style="display: flex; gap: 10px;">
                <a href="index.php" class="btn btn-secondary">📊 Dashboard</a>
                <a href="form.php" class="btn btn-primary">➕ Add New</a>
            </div>
        </div>

        <div class="alert alert-success" id="successAlert"></div>
        <div class="alert alert-error" id="errorAlert"></div>

        <!-- Filter Section -->
        <div class="filter-section">
            <h3 style="margin-bottom: 15px; color: #323130;">🔍 Filters</h3>
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" id="dateFrom">
                </div>
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" id="dateTo">
                </div>
                <div class="filter-group">
                    <label>Company</label>
                    <select id="filterCompany">
                        <option value="">All Companies</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Category</label>
                    <select id="filterCategory">
                        <option value="">All Categories</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search Item</label>
                    <input type="text" id="searchItem" placeholder="Search by item name...">
                </div>
            </div>
            <div class="filter-actions">
                <button class="btn btn-primary" onclick="applyFilters()">🔍 Apply Filters</button>
                <button class="btn btn-secondary" onclick="clearFilters()">🔄 Clear</button>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stats-item">
                <span>Total Records:</span>
                <span class="stats-value" id="totalRecords">0</span>
            </div>
            <div class="stats-item">
                <span>Filtered Total Sales:</span>
                <span class="stats-value" id="filteredSales">₱0.00</span>
            </div>
            <div class="stats-item">
                <span>Filtered Total Profit:</span>
                <span class="stats-value" id="filteredProfit">₱0.00</span>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div class="loading" id="loading">Loading records...</div>
            <table id="recordsTable" style="display: none;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>S/N</th>
                        <th>Company</th>
                        <th>Category</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Supplier Price</th>
                        <th>NAM Price</th>
                        <th>Total Sales</th>
                        <th>Income</th>
                        <th>Profit %</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="recordsBody">
                </tbody>
            </table>
            <div class="no-data" id="noData" style="display: none;">
                No records found. Try adjusting your filters or add new records.
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination" id="pagination" style="display: none;">
            <button onclick="changePage('first')">⏮️ First</button>
            <button onclick="changePage('prev')">◀️ Prev</button>
            <span class="page-info">Page <span id="currentPage">1</span> of <span id="totalPages">1</span></span>
            <button onclick="changePage('next')">Next ▶️</button>
            <button onclick="changePage('last')">Last ⏭️</button>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Edit Record</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="editForm" onsubmit="saveEdit(event)">
                <input type="hidden" id="editId">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Date <span style="color: #d13438;">*</span></label>
                        <input type="date" id="editDate" required>
                    </div>
                    <div class="form-group">
                        <label>S/N</label>
                        <input type="text" id="editSN">
                    </div>
                    <div class="form-group">
                        <label>PO Number</label>
                        <input type="text" id="editPO">
                    </div>
                    <div class="form-group">
                        <label>Company <span style="color: #d13438;">*</span></label>
                        <input type="text" id="editCompany" required>
                    </div>
                    <div class="form-group">
                        <label>Category <span style="color: #d13438;">*</span></label>
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
                    <div class="form-group full-width">
                        <label>Item <span style="color: #d13438;">*</span></label>
                        <input type="text" id="editItem" required>
                    </div>
                    <div class="form-group">
                        <label>Quantity <span style="color: #d13438;">*</span></label>
                        <input type="number" id="editQuantity" required min="1" onchange="calculateEdit()">
                    </div>
                    <div class="form-group">
                        <label>Supplier Price (₱) <span style="color: #d13438;">*</span></label>
                        <input type="number" id="editSupplierPrice" required step="0.01" onchange="calculateEdit()">
                    </div>
                    <div class="form-group">
                        <label>Total Actual Amount (₱)</label>
                        <input type="number" id="editTotalActual" readonly class="calculated-field" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>NAM Unit Price (₱) <span style="color: #d13438;">*</span></label>
                        <input type="number" id="editNAMPrice" required step="0.01" onchange="calculateEdit()">
                    </div>
                    <div class="form-group">
                        <label>Total NAM Amount (₱)</label>
                        <input type="number" id="editTotalNAM" readonly class="calculated-field" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Income (₱)</label>
                        <input type="number" id="editIncome" readonly class="calculated-field" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Income Percent (%)</label>
                        <input type="number" id="editIncomePercent" readonly class="calculated-field" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Supplier</label>
                        <input type="text" id="editSupplier">
                    </div>
                    <div class="form-group full-width">
                        <label>Remarks</label>
                        <textarea id="editRemarks"></textarea>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">💾 Save Changes</button>
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

        // Format currency
        function formatCurrency(value) {
            return '₱' + parseFloat(value || 0).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Load records
        async function loadRecords() {
            try {
                document.getElementById('loading').style.display = 'block';
                document.getElementById('recordsTable').style.display = 'none';
                document.getElementById('noData').style.display = 'none';

                const response = await fetch('get_records.php');
                const data = await response.json();
                
                allRecords = data.records;
                
                // Populate filter dropdowns
                populateFilters(data.companies, data.categories);
                
                // Apply initial filters
                applyFilters();
                
            } catch (error) {
                console.error('Error loading records:', error);
                showAlert('Error loading records', 'error');
            }
        }

        // Populate filter dropdowns
        function populateFilters(companies, categories) {
            const companySelect = document.getElementById('filterCompany');
            const categorySelect = document.getElementById('filterCategory');
            
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

        // Apply filters
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

        // Clear filters
        function clearFilters() {
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            document.getElementById('filterCompany').value = '';
            document.getElementById('filterCategory').value = '';
            document.getElementById('searchItem').value = '';
            applyFilters();
        }

        // Display records
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
                    <td>${record.company || ''}</td>
                    <td>${record.category || ''}</td>
                    <td>${record.item || ''}</td>
                    <td>${record.quantity_requested || 0}</td>
                    <td>${formatCurrency(record.suppliers_price)}</td>
                    <td>${formatCurrency(record.nam_unit_price)}</td>
                    <td><strong>${formatCurrency(record.total_nam_amount)}</strong></td>
                    <td><strong>${formatCurrency(record.income)}</strong></td>
                    <td>${parseFloat(record.income_percent || 0).toFixed(2)}%</td>
                    <td style="white-space: nowrap;">
                        <button class="btn btn-edit" onclick="editRecord(${record.id})">✏️ Edit</button>
                        <button class="btn btn-danger" onclick="deleteRecord(${record.id})">🗑️ Delete</button>
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

        // Update statistics
        function updateStats() {
            const totalRecords = filteredRecords.length;
            const totalSales = filteredRecords.reduce((sum, r) => sum + parseFloat(r.total_nam_amount || 0), 0);
            const totalProfit = filteredRecords.reduce((sum, r) => sum + parseFloat(r.income || 0), 0);

            document.getElementById('totalRecords').textContent = totalRecords;
            document.getElementById('filteredSales').textContent = formatCurrency(totalSales);
            document.getElementById('filteredProfit').textContent = formatCurrency(totalProfit);
        }

        // Change page
        function changePage(action) {
            if (action === 'first') currentPage = 1;
            else if (action === 'prev' && currentPage > 1) currentPage--;
            else if (action === 'next' && currentPage < totalPages) currentPage++;
            else if (action === 'last') currentPage = totalPages;
            
            displayRecords();
        }

        // Edit record
        function editRecord(id) {
            const record = allRecords.find(r => r.id === id);
            if (!record) return;

            document.getElementById('editId').value = record.id;
            document.getElementById('editDate').value = record.date;
            document.getElementById('editSN').value = record.sn || '';
            document.getElementById('editPO').value = record.po_number || '';
            document.getElementById('editCompany').value = record.company || '';
            document.getElementById('editCategory').value = record.category || '';
            document.getElementById('editItem').value = record.item || '';
            document.getElementById('editQuantity').value = record.quantity_requested || 0;
            document.getElementById('editSupplierPrice').value = record.suppliers_price || 0;
            document.getElementById('editNAMPrice').value = record.nam_unit_price || 0;
            document.getElementById('editSupplier').value = record.supplier || '';
            document.getElementById('editRemarks').value = record.remarks || '';

            calculateEdit();
            document.getElementById('editModal').classList.add('active');
        }

        // Calculate edit form
        function calculateEdit() {
            const qty = parseFloat(document.getElementById('editQuantity').value) || 0;
            const supplierPrice = parseFloat(document.getElementById('editSupplierPrice').value) || 0;
            const namPrice = parseFloat(document.getElementById('editNAMPrice').value) || 0;

            const totalActual = qty * supplierPrice;
            const totalNAM = qty * namPrice;
            const income = totalNAM - totalActual;
            const incomePercent = totalNAM > 0 ? (income / totalNAM) * 100 : 0;

            document.getElementById('editTotalActual').value = totalActual.toFixed(2);
            document.getElementById('editTotalNAM').value = totalNAM.toFixed(2);
            document.getElementById('editIncome').value = income.toFixed(2);
            document.getElementById('editIncomePercent').value = incomePercent.toFixed(2);
        }

        // Save edit
        async function saveEdit(event) {
            event.preventDefault();

            const formData = new FormData();
            formData.append('id', document.getElementById('editId').value);
            formData.append('date', document.getElementById('editDate').value);
            formData.append('sn', document.getElementById('editSN').value);
            formData.append('po_number', document.getElementById('editPO').value);
            formData.append('company', document.getElementById('editCompany').value);
            formData.append('category', document.getElementById('editCategory').value);
            formData.append('item', document.getElementById('editItem').value);
            formData.append('quantity_requested', document.getElementById('editQuantity').value);
            formData.append('suppliers_price', document.getElementById('editSupplierPrice').value);
            formData.append('nam_unit_price', document.getElementById('editNAMPrice').value);
            formData.append('supplier', document.getElementById('editSupplier').value);
            formData.append('remarks', document.getElementById('editRemarks').value);

            try {
                const response = await fetch('update_record.php', {
                    method: 'POST',
                    body: formData
                });

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
                console.error(error);
            }
        }

        // Delete record
        async function deleteRecord(id) {
            if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('delete_record.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + id
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Record deleted successfully!', 'success');
                    loadRecords();
                } else {
                    showAlert('Error deleting record: ' + result.message, 'error');
                }
            } catch (error) {
                showAlert('Error deleting record', 'error');
                console.error(error);
            }
        }

        // Close modal
        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // Show alert
        function showAlert(message, type) {
            const alertId = type === 'success' ? 'successAlert' : 'errorAlert';
            const alertEl = document.getElementById(alertId);
            alertEl.textContent = message;
            alertEl.classList.add('show');
            
            setTimeout(() => {
                alertEl.classList.remove('show');
            }, 5000);
        }

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Initial load
        loadRecords();
    </script>
</body>
</html>
