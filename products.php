<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Price List - NAM Supply</title>
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
            justify-content: center;
            gap: 6px;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-secondary { background: white; color: var(--text-main); border-color: var(--border); }
        .btn-secondary:hover { background: #f9fafb; border-color: #d1d5db; }
        
        .btn-icon { padding: 6px 10px; font-size: 14px; }
        .btn-edit { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .btn-edit:hover { background: #c7d2fe; }
        .btn-delete { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .btn-delete:hover { background: #fecaca; }

        /* --- KPI Cards --- */
        .kpi-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
        .kpi-card.warning { border-left-color: var(--secondary); }
        .kpi-card.success { border-left-color: var(--success); }

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

        /* --- Filter Bar --- */
        .filter-bar {
            background: var(--surface);
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-container {
            flex: 1;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px;
            padding-left: 40px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            background-color: #f9fafb;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            pointer-events: none;
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
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-main);
            vertical-align: middle;
        }

        tbody tr:hover {
            background-color: #f8fafc;
        }

        .category-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 11px;
            font-weight: 600;
        }

        .price-value {
            font-weight: 600;
            color: var(--text-main);
        }

        .price-nam {
            font-weight: 700;
            color: var(--primary);
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
            align-items: center;
            justify-content: center;
        }

        .modal.active { display: flex; }

        .modal-content {
            background: var(--surface);
            padding: 30px;
            width: 100%;
            max-width: 600px;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .modal-header h2 { font-size: 20px; font-weight: 700; color: var(--text-main); }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group { margin-bottom: 15px; }
        .form-group.full-width { grid-column: 1 / -1; }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background-color: #f9fafb;
            font-family: inherit;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .modal-actions {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        /* Utilities */
        .loading { text-align: center; padding: 40px; color: var(--text-light); }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: stretch; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <h1>📦 Master Price List</h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="index.php" class="btn btn-secondary">📊 Dashboard</a>
                <a href="records.php" class="btn btn-secondary">📋 View Records</a>
                <a href="form.php" class="btn btn-secondary">➕ Add Entry</a>
                <button onclick="openModal()" class="btn btn-primary">➕ Add Product</button>
            </div>
        </div>

        <div class="kpi-container">
            <div class="kpi-card">
                <div class="kpi-label">Total Products</div>
                <div class="kpi-value" id="totalItems">0</div>
            </div>
            <div class="kpi-card warning">
                <div class="kpi-label">Active Categories</div>
                <div class="kpi-value" id="totalCats">0</div>
            </div>
            <div class="kpi-card success">
                <div class="kpi-label">Average Margin</div>
                <div class="kpi-value" id="avgMargin">0%</div>
            </div>
        </div>

        <div class="filter-bar">
            <div class="search-container">
                <span class="search-icon">🔍</span>
                <input type="text" id="searchInput" class="search-input" placeholder="Search by Product Name, Category, or Supplier..." onkeyup="handleSearch()">
            </div>
        </div>

        <div class="table-card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th width="30%">Product Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Supplier</th>
                            <th>Supp. Price</th>
                            <th>NAM Price</th>
                            <th>Margin</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productTable">
                        <tr><td colspan="8" class="loading">Loading products...</td></tr>
                    </tbody>
                </table>
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

    <div class="modal" id="productModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Product</h2>
                <button onclick="closeModal()" style="border:none; background:none; font-size:24px; cursor:pointer; color:var(--text-light);">&times;</button>
            </div>
            <form id="productForm" onsubmit="saveProduct(event)">
                <input type="hidden" name="id" id="prodId">
                
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" id="prodName" required placeholder="Enter product name">
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="prodCat" required>
                            <option value="">Select Category...</option>
                            <option value="OFFICE SUPPLIES">OFFICE SUPPLIES</option>
                            <option value="CLEANING MATERIALS">CLEANING MATERIALS</option>
                            <option value="CONSUMABLES">CONSUMABLES</option>
                            <option value="OFFICE TOOLS AND EQUIPMENT">OFFICE TOOLS</option>
                            <option value="PPE">PPE</option>
                            <option value="MATERIALS">MATERIALS</option>
                            <option value="COMPANY UNIFORM">UNIFORMS</option>
                            <option value="OFFICE FURNITURE & FIXTURES">FURNITURE</option>
                            <option value="MEDICINE">MEDICINE</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Unit</label>
                        <input type="text" name="unit" id="prodUnit" placeholder="e.g. PC, BOX, SET">
                    </div>
                </div>

                <div class="form-group">
                    <label>Supplier Name</label>
                    <input type="text" name="supplier" id="prodSupplier" placeholder="Enter supplier name">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Supplier Price (₱)</label>
                        <input type="number" step="0.01" name="supplier_price" id="prodSPrice" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>NAM Price (₱)</label>
                        <input type="number" step="0.01" name="nam_price" id="prodNPrice" required placeholder="0.00">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Data & State
        let allProducts = [];
        let filteredProducts = [];
        let currentPage = 1;
        const itemsPerPage = 50;

        async function loadProducts() {
            try {
                const res = await fetch('get_all_products.php');
                allProducts = await res.json();
                
                // Initial Sort & Filter
                filteredProducts = [...allProducts];
                updateStats(allProducts);
                
                // Render Page 1
                renderTable();
            } catch (err) {
                console.error("Error loading products:", err);
                document.getElementById('productTable').innerHTML = `<tr><td colspan="8" style="text-align:center; padding:20px; color:red;">Error loading data.</td></tr>`;
            }
        }

        function handleSearch() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            
            filteredProducts = allProducts.filter(p => 
                p.name.toLowerCase().includes(term) || 
                (p.category_code && p.category_code.toLowerCase().includes(term)) ||
                (p.supplier && p.supplier.toLowerCase().includes(term))
            );

            currentPage = 1; // Reset to page 1 on search
            renderTable();
        }

        function renderTable() {
            const tbody = document.getElementById('productTable');
            tbody.innerHTML = '';
            
            if (filteredProducts.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-light);">No products found.</td></tr>`;
                document.getElementById('pagination').style.display = 'none';
                return;
            }

            // Pagination Logic
            const totalPages = Math.ceil(filteredProducts.length / itemsPerPage);
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const displayData = filteredProducts.slice(start, end);

            // Render Rows
            displayData.forEach(p => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="font-weight: 600; color: var(--text-main); white-space: normal;">${p.name}</td>
                    <td><span class="category-badge">${p.category_code}</span></td>
                    <td style="color: var(--text-light);">${p.unit || '-'}</td>
                    <td>${p.supplier || '-'}</td>
                    <td class="price-value">₱${parseFloat(p.supplier_price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    <td class="price-nam">₱${parseFloat(p.nam_price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    <td><span style="font-weight:500; color:${parseFloat(p.margin) > 0 ? 'var(--success)' : 'var(--danger)'}">${p.margin || '0%'}</span></td>
                    <td>
                        <button class="btn btn-icon btn-edit" title="Edit" onclick='editProduct(${JSON.stringify(p).replace(/'/g, "&#39;")})'>✏️</button>
                        <button class="btn btn-icon btn-delete" title="Delete" onclick="deleteProduct(${p.id})">🗑️</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            // Update Pagination Controls
            document.getElementById('pagination').style.display = 'flex';
            document.getElementById('currentPage').textContent = currentPage;
            document.getElementById('totalPages').textContent = totalPages;
        }

        function changePage(action) {
            const totalPages = Math.ceil(filteredProducts.length / itemsPerPage);
            
            if (action === 'first') currentPage = 1;
            else if (action === 'prev' && currentPage > 1) currentPage--;
            else if (action === 'next' && currentPage < totalPages) currentPage++;
            else if (action === 'last') currentPage = totalPages;

            renderTable();
            // Scroll to top of table
            document.querySelector('.table-card').scrollIntoView({ behavior: 'smooth' });
        }

        function updateStats(data) {
            // 1. Total Items
            document.getElementById('totalItems').textContent = data.length.toLocaleString();

            // 2. Count Unique Categories
            const categories = new Set(data.map(p => p.category_code).filter(c => c));
            document.getElementById('totalCats').textContent = categories.size;

            // 3. Calculate Average Margin
            let totalMargin = 0;
            let count = 0;

            data.forEach(p => {
                let marginStr = String(p.margin).replace('%', '');
                let val = parseFloat(marginStr);
                if (!isNaN(val)) {
                    totalMargin += val;
                    count++;
                }
            });

            const avg = count > 0 ? (totalMargin / count).toFixed(2) : 0;
            document.getElementById('avgMargin').textContent = avg + "%";
        }

        function editProduct(p) {
            document.getElementById('modalTitle').textContent = "Edit Product";
            document.getElementById('prodId').value = p.id;
            document.getElementById('prodName').value = p.name;
            document.getElementById('prodCat').value = p.category_code;
            document.getElementById('prodUnit').value = p.unit;
            document.getElementById('prodSupplier').value = p.supplier;
            document.getElementById('prodSPrice').value = p.supplier_price;
            document.getElementById('prodNPrice').value = p.nam_price;
            document.getElementById('productModal').classList.add('active');
        }

        function openModal() {
            document.getElementById('modalTitle').textContent = "Add Product";
            document.getElementById('productForm').reset();
            document.getElementById('prodId').value = '';
            document.getElementById('productModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('productModal').classList.remove('active');
        }

        async function saveProduct(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            try {
                const res = await fetch('save_product.php', { method: 'POST', body: formData });
                const result = await res.json();
                
                if(result.success) {
                    closeModal();
                    loadProducts();
                } else {
                    alert("Error saving: " + result.message);
                }
            } catch (err) {
                console.error(err);
                alert("An error occurred while saving.");
            }
        }

        async function deleteProduct(id) {
            if(!confirm("Are you sure you want to delete this product?")) return;
            
            try {
                const formData = new FormData();
                formData.append('id', id);
                
                const res = await fetch('delete_product.php', { method: 'POST', body: formData });
                const result = await res.json();
                
                if(result.success) loadProducts();
                else alert("Error deleting product");
            } catch (err) {
                console.error(err);
                alert("An error occurred while deleting.");
            }
        }

        // Close modal on outside click
        document.getElementById('productModal').addEventListener('click', (e) => {
            if (e.target.id === 'productModal') closeModal();
        });

        // Initialize
        loadProducts();
    </script>
</body>
</html>