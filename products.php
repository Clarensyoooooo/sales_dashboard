<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Price List - NAM Supply</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f3f2f1; padding: 20px; }
        .container { max-width: 1600px; margin: 0 auto; background: white; padding: 30px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        h1 { color: #323130; font-size: 24px; margin: 0; }
        
        /* Buttons */
        .btn { padding: 10px 20px; border: none; border-radius: 2px; cursor: pointer; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; }
        .btn-primary { background: #0078d4; color: white; }
        .btn-secondary { background: #f3f2f1; color: #323130; border: 1px solid #8a8886; }
        .btn-danger { background: #d13438; color: white; }
        .btn:hover { opacity: 0.9; }

        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .kpi-card { background: white; padding: 15px 20px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-left: 5px solid #0078d4; display: flex; flex-direction: column; border: 1px solid #edebe9; border-left-width: 5px; }
        .kpi-card.warning { border-left-color: #d13438; }
        .kpi-card.success { border-left-color: #107c10; }
        .kpi-label { font-size: 12px; font-weight: 600; color: #605e5c; text-transform: uppercase; margin-bottom: 5px; }
        .kpi-value { font-size: 24px; font-weight: 700; color: #323130; }

        /* Table */
        .table-container { overflow-x: auto; margin-top: 15px; border: 1px solid #edebe9; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f3f2f1; padding: 12px; text-align: left; border-bottom: 2px solid #0078d4; white-space: nowrap; }
        td { padding: 10px; border-bottom: 1px solid #f3f2f1; color: #201f1e; }
        tr:hover { background: #faf9f8; }

        /* Search */
        .search-box { padding: 10px; width: 100%; max-width: 400px; border: 1px solid #8a8886; margin-bottom: 10px; border-radius: 2px; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; width: 600px; border-radius: 4px; max-height: 90vh; overflow-y: auto; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #8a8886; box-sizing: border-box; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Master Price List</h1>
            <div style="display: flex; gap: 10px;">
                <a href="index.php" class="btn btn-secondary">📊 Dashboard</a>
                <a href="records.php" class="btn btn-secondary">📋 View Records</a>
                <a href="form.php" class="btn btn-secondary">➕ Add Entry</a>
                <button onclick="openModal()" class="btn btn-primary">➕ Add Product</button>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <span class="kpi-label">Total Products</span>
                <span class="kpi-value" id="totalItems">0</span>
            </div>
            <div class="kpi-card warning">
                <span class="kpi-label">Active Categories</span>
                <span class="kpi-value" id="totalCats">0</span>
            </div>
            <div class="kpi-card success">
                <span class="kpi-label">Avg. Margin</span>
                <span class="kpi-value" id="avgMargin">0%</span>
            </div>
        </div>

        <input type="text" id="searchInput" class="search-box" placeholder="🔍 Search by Product Name or Category..." onkeyup="filterProducts()">

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
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
                    </tbody>
            </table>
        </div>
    </div>

    <div class="modal" id="productModal">
        <div class="modal-content">
            <h2 id="modalTitle" style="margin-bottom: 20px;">Add Product</h2>
            <form id="productForm" onsubmit="saveProduct(event)">
                <input type="hidden" name="id" id="prodId">
                
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" id="prodName" required>
                </div>
                
                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label>Category</label>
                        <select name="category" id="prodCat" required>
                            <option value="">Select...</option>
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
                    <div>
                        <label>Unit</label>
                        <input type="text" name="unit" id="prodUnit" placeholder="e.g. PC, BOX">
                    </div>
                </div>

                <div class="form-group">
                    <label>Supplier</label>
                    <input type="text" name="supplier" id="prodSupplier">
                </div>

                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label>Supplier Price (₱)</label>
                        <input type="number" step="0.01" name="supplier_price" id="prodSPrice" required>
                    </div>
                    <div>
                        <label>NAM Price (₱)</label>
                        <input type="number" step="0.01" name="nam_price" id="prodNPrice" required>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let allProducts = [];

        async function loadProducts() {
            try {
                const res = await fetch('get_all_products.php');
                allProducts = await res.json();
                renderTable(allProducts);
            } catch (err) {
                console.error("Error loading products:", err);
            }
        }

        function renderTable(data) {
            updateStats(data); // This was likely the missing line!
            
            const tbody = document.getElementById('productTable');
            tbody.innerHTML = '';
            
            // Show only top 100 for performance
            const displayData = data.slice(0, 100); 

            displayData.forEach(p => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="font-weight: 500;">${p.name}</td>
                    <td>${p.category_code}</td>
                    <td>${p.unit || '-'}</td>
                    <td>${p.supplier || '-'}</td>
                    <td>₱${parseFloat(p.supplier_price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    <td style="color: #0078d4; font-weight: bold;">₱${parseFloat(p.nam_price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    <td>${p.margin || '-'}</td>
                    <td>
                        <button class="btn-secondary" style="padding: 5px 10px; font-size: 12px;" onclick='editProduct(${JSON.stringify(p).replace(/'/g, "&#39;")})'>✏️</button>
                        <button class="btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="deleteProduct(${p.id})">🗑️</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
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
                // Remove % sign and convert to float
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

        function filterProducts() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            const filtered = allProducts.filter(p => 
                p.name.toLowerCase().includes(term) || 
                (p.category_code && p.category_code.toLowerCase().includes(term))
            );
            renderTable(filtered);
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
            
            const res = await fetch('save_product.php', { method: 'POST', body: formData });
            const result = await res.json();
            
            if(result.success) {
                closeModal();
                loadProducts();
                alert("Saved successfully!");
            } else {
                alert("Error saving: " + result.message);
            }
        }

        async function deleteProduct(id) {
            if(!confirm("Delete this product?")) return;
            
            const formData = new FormData();
            formData.append('id', id);
            
            const res = await fetch('delete_product.php', { method: 'POST', body: formData });
            const result = await res.json();
            
            if(result.success) loadProducts();
            else alert("Error deleting");
        }

        // Initialize
        loadProducts();
    </script>
</body>
</html>