<?php require_once 'config.php'; requireLogin(); requirePermission('manage_products'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - NAM Supply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .table-responsive { max-height: 70vh; overflow-y: auto; }
        thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
        .kpi-card { border-left: 4px solid; transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-3px); }
        .kpi-card.blue { border-color: #0d6efd; }
        .kpi-card.red { border-color: #dc3545; }
        .kpi-card.green { border-color: #198754; }
        .col-truncate { max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body class="bg-light">

    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4 px-4 pb-5">
        
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 kpi-card blue h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase fw-bold small mb-1">Total Products</h6>
                                <h2 class="mb-0 fw-bold text-dark" id="totalItems">0</h2>
                            </div>
                            <div class="text-primary opacity-50"><i class="fas fa-boxes fa-2x"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 kpi-card red h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase fw-bold small mb-1">Low Stock Items</h6>
                                <h2 class="mb-0 fw-bold text-danger" id="lowStockCount">0</h2>
                            </div>
                            <div class="text-danger opacity-50"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 kpi-card green h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase fw-bold small mb-1">Avg. Margin</h6>
                                <h2 class="mb-0 fw-bold text-success" id="avgMargin">0%</h2>
                            </div>
                            <div class="text-success opacity-50"><i class="fas fa-chart-line fa-2x"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="alertContainer"></div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body bg-white py-3">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search by Product Name, Category, or Supplier..." onkeyup="handleSearch()">
                        </div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <button id="mergeBtn" onclick="openMergeModal()" class="btn btn-warning fw-bold shadow-sm me-2 d-none">
                            <i class="fas fa-object-group me-2"></i>Merge Selected
                        </button>
                        <button onclick="openModal()" class="btn btn-primary fw-bold shadow-sm">
                            <i class="fas fa-plus-circle me-2"></i>Add New Product
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped table-sm mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3 py-3" style="width: 40px;">
                                    <input class="form-check-input" type="checkbox" onchange="toggleAllMerge(this)">
                                </th>
                                <th>Product Name</th>
                                <th>Unit</th>
                                <th>Category</th>
                                <th>Supplier</th>
                                <th class="text-end">Supp. Price</th>
                                <th class="text-end">NAM Price</th>
                                <th class="text-center">Margin</th>
                                <th class="text-center">Inventory</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productTable">
                            <tr><td colspan="10" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading products...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center p-3 border-top" id="paginationBar">
                    <span class="small text-muted">Showing page <span id="currentPage" class="fw-bold">1</span> of <span id="totalPages">1</span></span>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" onclick="changePage('first')"><i class="fas fa-angle-double-left"></i></button>
                        <button class="btn btn-outline-secondary" onclick="changePage('prev')"><i class="fas fa-angle-left"></i></button>
                        <button class="btn btn-outline-secondary" onclick="changePage('next')"><i class="fas fa-angle-right"></i></button>
                        <button class="btn btn-outline-secondary" onclick="changePage('last')"><i class="fas fa-angle-double-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="mergeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title fw-bold text-dark"><i class="fas fa-object-group me-2"></i>Merge Duplicate Products</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Select the primary product to keep. The other selected products will be deleted, their stock will be added to the primary, and all sales records will be updated to point to the primary product.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Primary Product to Keep:</label>
                        <select id="primaryProductSelect" class="form-select border-warning"></select>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning fw-bold" onclick="executeMerge()">
                        <i class="fas fa-check me-2"></i>Merge Now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalTitle"><i class="fas fa-box me-2"></i>Add Product</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="productForm" onsubmit="saveProduct(event)">
                    <div class="modal-body bg-light">
                        <input type="hidden" name="id" id="prodId">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="prodName" class="form-control" required placeholder="Enter product name">
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label fw-bold small text-muted text-uppercase">Category <span class="text-danger">*</span></label>
                                <select name="category" id="prodCat" class="form-select" required>
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
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted text-uppercase">Unit</label>
                                <input type="text" name="unit" id="prodUnit" class="form-control" placeholder="e.g. PC, BOX">
                            </div>
                        </div>

                        <div class="card bg-white border-secondary-subtle mb-3">
                            <div class="card-body py-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-success text-uppercase">Current Stock</label>
                                        <input type="number" name="current_stock" id="prodStock" class="form-control" required value="0">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-danger text-uppercase">Low Stock Alert Level</label>
                                        <input type="number" name="reorder_level" id="prodReorder" class="form-control" required value="10">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Supplier Name</label>
                            <input type="text" name="supplier" id="prodSupplier" class="form-control" placeholder="Enter supplier name">
                        </div>

                        <div class="card bg-white border-primary-subtle shadow-sm">
                            <div class="card-header bg-primary text-white py-2">
                                <h6 class="mb-0 fw-bold small"><i class="fas fa-calculator me-2"></i>Pricing & Margin Calculator</h6>
                            </div>
                            <div class="card-body py-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Supplier Price <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">₱</span>
                                            <input type="number" step="0.01" name="supplier_price" id="prodSPrice" class="form-control" required placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-primary text-uppercase">Selling (NAM) Price <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-primary text-white border-primary">₱</span>
                                            <input type="number" step="0.01" name="nam_price" id="prodNPrice" class="form-control border-primary" required placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Markup (%)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" id="prodMarkup" class="form-control" placeholder="e.g. 35">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Margin (%)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" id="prodMargin" class="form-control" placeholder="Auto-calculated">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer bg-white">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-2"></i>Save Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let allProducts = [];
        let filteredProducts = [];
        let currentPage = 1;
        const itemsPerPage = 50;
        let productModal;
        let mergeModal;

        document.addEventListener('DOMContentLoaded', () => {
            productModal = new bootstrap.Modal(document.getElementById('productModal'));
            mergeModal = new bootstrap.Modal(document.getElementById('mergeModal'));
            attachPriceCalculators('prodSPrice', 'prodNPrice', 'prodMarkup', 'prodMargin');
            loadProducts();
        });

        // --- MERGE LOGIC ---
        function toggleAllMerge(source) {
            const checkboxes = document.querySelectorAll('.merge-checkbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
            checkMergeButton();
        }

        function checkMergeButton() {
            const checked = document.querySelectorAll('.merge-checkbox:checked');
            const mergeBtn = document.getElementById('mergeBtn');
            if (checked.length >= 2) {
                mergeBtn.classList.remove('d-none');
            } else {
                mergeBtn.classList.add('d-none');
            }
        }

        function openMergeModal() {
            const checked = document.querySelectorAll('.merge-checkbox:checked');
            const select = document.getElementById('primaryProductSelect');
            select.innerHTML = '';
            
            checked.forEach(cb => {
                const option = document.createElement('option');
                option.value = cb.value;
                option.textContent = cb.getAttribute('data-name');
                select.appendChild(option);
            });
            
            mergeModal.show();
        }

        async function executeMerge() {
            const primaryId = document.getElementById('primaryProductSelect').value;
            const checkedBoxes = Array.from(document.querySelectorAll('.merge-checkbox:checked'));
            const duplicateIds = checkedBoxes.map(cb => cb.value).filter(id => id !== primaryId);

            if(!confirm("Are you sure? This will consolidate inventory and permanently delete the duplicate products.")) return;

            try {
                const formData = new FormData();
                formData.append('primary_id', primaryId);
                formData.append('duplicate_ids', JSON.stringify(duplicateIds));

                const res = await fetch('merge_products.php', { method: 'POST', body: formData });
                const result = await res.json();
                
                if(result.success) {
                    mergeModal.hide();
                    loadProducts(); 
                    document.getElementById('mergeBtn').classList.add('d-none');
                    showAlert("Items merged successfully!", "success");
                } else {
                    showAlert("Merge Error: " + result.message, "danger");
                }
            } catch (err) { 
                console.error(err);
                showAlert("Error merging items.", "danger"); 
            }
        }

        // --- PRICING CALCULATOR LOGIC ---
        function attachPriceCalculators(sPriceId, nPriceId, markupId, marginId) {
            const sPrice = document.getElementById(sPriceId);
            const nPrice = document.getElementById(nPriceId);
            const markup = document.getElementById(markupId);
            const margin = document.getElementById(marginId);

            function calcFromPrice() {
                let s = parseFloat(sPrice.value) || 0;
                let n = parseFloat(nPrice.value) || 0;
                if (s > 0 && n > 0) {
                    markup.value = (((n - s) / s) * 100).toFixed(2);
                    margin.value = (((n - s) / n) * 100).toFixed(2);
                } else {
                    markup.value = ''; margin.value = '';
                }
            }

            function calcFromMarkup() {
                let s = parseFloat(sPrice.value) || 0;
                let mk = parseFloat(markup.value) || 0;
                if (s > 0) {
                    let n = s * (1 + (mk / 100));
                    nPrice.value = n.toFixed(2);
                    margin.value = (((n - s) / n) * 100).toFixed(2);
                }
            }

            function calcFromMargin() {
                let s = parseFloat(sPrice.value) || 0;
                let mg = parseFloat(margin.value) || 0;
                if (s > 0 && mg < 100) {
                    let n = s / (1 - (mg / 100));
                    nPrice.value = n.toFixed(2);
                    markup.value = (((n - s) / s) * 100).toFixed(2);
                }
            }

            if(sPrice) sPrice.addEventListener('input', calcFromPrice);
            if(nPrice) nPrice.addEventListener('input', calcFromPrice);
            if(markup) markup.addEventListener('input', calcFromMarkup);
            if(margin) margin.addEventListener('input', calcFromMargin);
        }

        function showAlert(message, type = 'success') {
            const container = document.getElementById('alertContainer');
            container.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            setTimeout(() => {
                const alert = bootstrap.Alert.getOrCreateInstance(container.querySelector('.alert'));
                if(alert) alert.close();
            }, 3000);
        }

        async function loadProducts() {
            try {
                const res = await fetch('get_all_products.php');
                allProducts = await res.json();
                
                filteredProducts = [...allProducts];
                updateStats(allProducts);
                renderTable();
            } catch (err) {
                console.error("Error loading products:", err);
                showAlert("Failed to load products.", "danger");
            }
        }

        function handleSearch() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            filteredProducts = allProducts.filter(p => 
                p.name.toLowerCase().includes(term) || 
                (p.category_code && p.category_code.toLowerCase().includes(term)) ||
                (p.supplier && p.supplier.toLowerCase().includes(term))
            );
            currentPage = 1;
            renderTable();
        }

        function renderTable() {
            const tbody = document.getElementById('productTable');
            tbody.innerHTML = '';
            
            if (filteredProducts.length === 0) {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted"><i class="fas fa-box-open fa-3x mb-3 opacity-25"></i><br>No products found.</td></tr>`;
                document.getElementById('paginationBar').classList.add('d-none');
                return;
            }
            document.getElementById('paginationBar').classList.remove('d-none');

            const totalPages = Math.ceil(filteredProducts.length / itemsPerPage);
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const displayData = filteredProducts.slice(start, end);

            displayData.forEach(p => {
                const tr = document.createElement('tr');
                const stock = parseInt(p.current_stock || 0);
                const reorder = parseInt(p.reorder_level || 10);
                const isLow = stock <= reorder;

                let stockBadge = isLow 
                    ? `<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>${stock}</span>` 
                    : `<span class="badge bg-success">${stock}</span>`;

                let draftBadge = (p.is_draft == 1 || p.is_draft == '1') 
                    ? `<span class="badge bg-warning text-dark ms-2 shadow-sm" title="Added from Quotation - Needs Review"><i class="fas fa-pencil-alt me-1"></i> QUOTE DRAFT</span>` 
                    : '';

                tr.innerHTML = `
                    <td class="ps-3">
                        <input class="form-check-input merge-checkbox" type="checkbox" value="${p.id}" data-name="${p.name.replace(/"/g, '&quot;')}" onchange="checkMergeButton()">
                    </td>
                    <td class="fw-bold text-dark col-truncate" title="${p.name.replace(/"/g, '&quot;')}">${p.name} ${draftBadge}</td>
                    <td class="text-muted small">${p.unit || '-'}</td>
                    <td><span class="badge bg-light text-dark border border-secondary-subtle">${p.category_code}</span></td>
                    <td class="small">${p.supplier || '-'}</td>
                    <td class="text-end font-monospace">₱${parseFloat(p.supplier_price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    <td class="text-end font-monospace fw-bold text-primary">₱${parseFloat(p.nam_price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    <td class="text-center"><span class="badge bg-soft-success text-success border border-success-subtle">${p.margin || '0%'}</span></td>
                    <td class="text-center">${stockBadge}</td>
                    <td class="text-end pe-3">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick='editProduct(${JSON.stringify(p).replace(/'/g, "&#39;")})' title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteProduct(${p.id})" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });

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
        }

        function updateStats(data) {
            document.getElementById('totalItems').textContent = data.length.toLocaleString();
            
            const lowStock = data.filter(p => parseInt(p.current_stock || 0) <= parseInt(p.reorder_level || 10)).length;
            document.getElementById('lowStockCount').textContent = lowStock;

            let totalMargin = 0, count = 0;
            data.forEach(p => {
                let val = parseFloat(String(p.margin).replace('%', ''));
                if (!isNaN(val)) { totalMargin += val; count++; }
            });
            const avg = count > 0 ? (totalMargin / count).toFixed(2) : 0;
            document.getElementById('avgMargin').textContent = avg + "%";
        }

        function editProduct(p) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Product';
            document.getElementById('prodId').value = p.id;
            document.getElementById('prodName').value = p.name;
            document.getElementById('prodCat').value = p.category_code;
            document.getElementById('prodUnit').value = p.unit;
            document.getElementById('prodSupplier').value = p.supplier;
            document.getElementById('prodSPrice').value = p.supplier_price;
            document.getElementById('prodNPrice').value = p.nam_price;
            document.getElementById('prodStock').value = p.current_stock || 0;
            document.getElementById('prodReorder').value = p.reorder_level || 10;
            
            document.getElementById('prodNPrice').dispatchEvent(new Event('input'));
            
            productModal.show();
        }

        function openModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-box me-2"></i>Add Product';
            document.getElementById('productForm').reset();
            document.getElementById('prodId').value = '';
            document.getElementById('prodStock').value = 0;
            document.getElementById('prodReorder').value = 10;
            document.getElementById('prodMarkup').value = '';
            document.getElementById('prodMargin').value = '';
            productModal.show();
        }

        async function saveProduct(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            try {
                const res = await fetch('save_product.php', { method: 'POST', body: formData });
                const result = await res.json();
                if(result.success) { 
                    productModal.hide(); 
                    loadProducts(); 
                    showAlert("Product saved successfully!", "success");
                } else { 
                    showAlert("Error: " + result.message, "danger"); 
                }
            } catch (err) { showAlert("Error saving product", "danger"); }
        }

        async function deleteProduct(id) {
            if(!confirm("Are you sure you want to delete this product? This cannot be undone.")) return;
            try {
                const formData = new FormData();
                formData.append('id', id);
                const res = await fetch('delete_product.php', { method: 'POST', body: formData });
                const result = await res.json();
                if(result.success) {
                    loadProducts();
                    showAlert("Product deleted.", "success");
                }
                else showAlert("Error deleting product.", "danger");
            } catch (err) { showAlert("Error deleting product.", "danger"); }
        }
    </script>
</body>
</html>