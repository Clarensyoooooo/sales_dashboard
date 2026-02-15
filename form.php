<?php require_once 'config.php'; requireLogin(); requirePermission('manage_sales'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Sale - NAM Supply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-section-header { 
            font-size: 0.85rem; font-weight: 700; text-transform: uppercase; 
            letter-spacing: 0.5px; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #eee;
        }
        .form-section-header.blue { color: #0d6efd; border-color: #0d6efd; }
        .form-section-header.green { color: #198754; border-color: #198754; }
        .form-section-header.orange { color: #fd7e14; border-color: #fd7e14; }
        
        .calculated-field { background-color: #e9ecef; pointer-events: none; font-weight: 600; }
        .table-wrap { max-height: 65vh; overflow-y: auto; }
        tr.editing { background-color: #fff3cd !important; border-left: 4px solid #ffc107; }
        .required-star { color: #dc3545; }
    </style>
</head>
<body class="bg-light">

    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row g-4">
            
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-edit me-2"></i>Sales Encoder</h5>
                    </div>
                    <div class="card-body">
                        <form id="entryForm" onsubmit="handleFormSubmit(event)">
                            
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center form-section-header blue">
                                    <span>1. Client & Terms</span>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="lockHeader" checked>
                                        <label class="form-check-label small text-muted" for="lockHeader">Lock</label>
                                    </div>
                                </div>
                                
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Date <span class="required-star">*</span></label>
                                        <input type="date" name="date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">PO Number</label>
                                        <input type="text" name="po_number" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Company Name <span class="required-star">*</span></label>
                                        <input type="text" name="company" class="form-control form-control-sm" required placeholder="e.g. SUMOPAK">
                                    </div>
                                    <div class="col-12">
                                        <input type="text" name="address" class="form-control form-control-sm mt-1" placeholder="Address (Optional)">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" name="tin" class="form-control form-control-sm mt-1" placeholder="TIN">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" name="contact_person_contact" class="form-control form-control-sm mt-1" placeholder="Contact Person">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" name="payment_term" class="form-control form-control-sm mt-1" placeholder="Terms (e.g. 30 Days)">
                                    </div>
                                    <div class="col-6">
                                        <input type="date" name="due_date" class="form-control form-control-sm mt-1" title="Due Date">
                                    </div>
                                    <div class="col-12">
                                        <textarea name="remarks" class="form-control form-control-sm mt-1" rows="1" placeholder="Remarks..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-section-header green">2. Item Details</div>
                                
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Category <span class="required-star">*</span></label>
                                        <select name="category" required id="catSelect" class="form-select form-select-sm">
                                            <option value="">Select Category...</option>
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
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Item Description <span class="required-star">*</span></label>
                                        <input type="text" name="item" id="itemInput" list="productList" class="form-control form-control-sm" required placeholder="Type to search stock..." autocomplete="off">
                                        <datalist id="productList"></datalist>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Quantity <span class="required-star">*</span></label>
                                        <input type="number" name="quantity_requested" id="quantity" class="form-control form-control-sm" required min="1" value="1">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Serial / S.N.</label>
                                        <input type="text" name="sn" class="form-control form-control-sm">
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label small fw-bold text-muted">Supplier Price</label>
                                        <input type="number" name="suppliers_price" id="sPrice" class="form-control form-control-sm" step="0.01">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold text-primary">NAM Price <span class="required-star">*</span></label>
                                        <input type="number" name="nam_unit_price" id="nPrice" class="form-control form-control-sm border-primary" required step="0.01">
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Total Cost</label>
                                        <input type="text" id="tActual" class="form-control form-control-sm calculated-field">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Total Sales</label>
                                        <input type="text" id="tSales" class="form-control form-control-sm calculated-field text-primary">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-section-header orange">3. Sourcing (Optional)</div>
                                <div class="row g-2">
                                    <div class="col-12">
                                        <input type="text" name="supplier" id="supplierName" class="form-control form-control-sm" placeholder="Supplier Name">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" name="sales_invoice_no" class="form-control form-control-sm" placeholder="Supp. Invoice #">
                                    </div>
                                    <div class="col-6">
                                        <input type="date" name="date_delivered" class="form-control form-control-sm">
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" id="addBtn" class="btn btn-success fw-bold">
                                    <i class="fas fa-plus-circle me-2"></i>Add to List
                                </button>
                                
                                <div id="editButtons" style="display:none;" class="gap-2">
                                    <button type="submit" id="updateBtn" class="btn btn-warning w-100 fw-bold">
                                        <i class="fas fa-save me-2"></i>Update Entry
                                    </button>
                                    <button type="button" id="cancelBtn" class="btn btn-secondary w-100" onclick="cancelEdit()">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 fw-bold"><i class="fas fa-list-ul me-2"></i>Pending Items</h5>
                            <small class="text-muted">Review items below before submitting.</small>
                        </div>
                        <span class="badge bg-primary rounded-pill fs-6" id="qCount">0 Items</span>
                    </div>
                    
                    <div class="card-body p-0 d-flex flex-column h-100">
                        <div class="table-wrap flex-grow-1">
                            <table class="table table-hover table-striped mb-0" id="qTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Item / Category</th>
                                        <th>Client / PO</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Total</th>
                                        <th>Supplier</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fas fa-box-open fa-3x mb-3 opacity-25"></i><br>
                                            List is empty. Add items from the left.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="p-3 border-top bg-light">
                            <button onclick="submitBatch()" class="btn btn-primary btn-lg w-100" id="submitBtn" disabled>
                                <i class="fas fa-paper-plane me-2"></i>Submit All Records
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    let productMap = new Map();
    let batchQueue = [];
    let editingIndex = -1;

    // --- 1. INIT ---
    fetch('get_all_products.php')
        .then(res => res.json())
        .then(data => {
            const dl = document.getElementById('productList');
            data.forEach(p => {
                productMap.set(p.name, p);
                const opt = document.createElement('option');
                opt.value = p.name;
                opt.label = `Stock: ${p.current_stock} | ₱${p.nam_price}`;
                dl.appendChild(opt);
            });
        });

    // --- 2. CALCULATIONS ---
    ['quantity', 'sPrice', 'nPrice'].forEach(id => {
        document.getElementById(id).addEventListener('input', calculate);
    });

    function calculate() {
        const qty = parseFloat(document.getElementById('quantity').value) || 0;
        const sPrice = parseFloat(document.getElementById('sPrice').value) || 0;
        const nPrice = parseFloat(document.getElementById('nPrice').value) || 0;

        document.getElementById('tActual').value = (qty * sPrice).toFixed(2);
        document.getElementById('tSales').value = (qty * nPrice).toFixed(2);
    }

    document.getElementById('itemInput').addEventListener('input', function() {
        const p = productMap.get(this.value);
        if (p) {
            document.getElementById('sPrice').value = p.supplier_price;
            document.getElementById('nPrice').value = p.nam_price;
            document.getElementById('supplierName').value = p.supplier || '';
            
            // Auto-select category
            const cat = document.getElementById('catSelect');
            for(let i=0; i<cat.options.length; i++) {
                if(cat.options[i].value === p.category_code) {
                    cat.selectedIndex = i;
                    break;
                }
            }
            calculate();
        }
    });

    // --- 3. FORM HANDLER ---
    function handleFormSubmit(e) {
        e.preventDefault();
        const form = document.getElementById('entryForm');
        const formData = new FormData(form);
        const entry = Object.fromEntries(formData.entries());

        if(!entry.item || !entry.company || !entry.nam_unit_price) {
            alert("Please fill required fields (marked with *)");
            return;
        }

        if (editingIndex === -1) {
            batchQueue.push(entry);
        } else {
            batchQueue[editingIndex] = entry;
            cancelEdit();
        }

        renderQueue();
        
        // Clear logic based on "Lock Header" switch
        if(editingIndex === -1) {
            const locked = document.getElementById('lockHeader').checked;
            if(locked) {
                // Keep Client details, clear Item details
                ['itemInput', 'quantity', 'sn', 'sPrice', 'nPrice', 'tActual', 'tSales', 'supplierName', 'sales_invoice_no', 'date_delivered'].forEach(id => {
                    const el = document.getElementById(id);
                    if(el) el.value = (id === 'quantity') ? '1' : '';
                });
                document.getElementById('itemInput').focus();
            } else {
                form.reset();
                form.querySelector('[name="date"]').value = new Date().toISOString().split('T')[0];
            }
        }
    }

    // --- 4. RENDER QUEUE ---
    function renderQueue() {
        const tbody = document.querySelector('#qTable tbody');
        const count = document.getElementById('qCount');
        const btn = document.getElementById('submitBtn');

        count.textContent = batchQueue.length + " Items";
        btn.disabled = batchQueue.length === 0;

        if (batchQueue.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-box-open fa-3x mb-3 opacity-25"></i><br>List is empty. Add items from the left.</td></tr>';
            return;
        }

        let html = '';
        batchQueue.forEach((item, idx) => {
            const total = (parseFloat(item.quantity_requested) * parseFloat(item.nam_unit_price)).toFixed(2);
            const rowClass = (idx === editingIndex) ? 'editing' : '';
            
            html += `
                <tr class="${rowClass}">
                    <td>
                        <div class="fw-bold text-dark">${item.item}</div>
                        <div class="small text-muted">${item.category}</div>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width:150px;">${item.company}</div>
                        <div class="small text-muted">PO: ${item.po_number || '-'}</div>
                    </td>
                    <td class="text-end">${item.quantity_requested}</td>
                    <td class="text-end">${item.nam_unit_price}</td>
                    <td class="text-end fw-bold text-primary">${total}</td>
                    <td class="small text-muted">${item.supplier || '-'}</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <button onclick="editItem(${idx})" class="btn btn-outline-secondary" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                            <button onclick="removeItem(${idx})" class="btn btn-outline-danger" title="Remove"><i class="fas fa-times"></i></button>
                        </div>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    // --- 5. EDIT LOGIC ---
    window.editItem = function(idx) {
        editingIndex = idx;
        const item = batchQueue[idx];
        const form = document.getElementById('entryForm');

        for (const [key, value] of Object.entries(item)) {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) input.value = value;
        }
        calculate();

        // Toggle Buttons
        document.getElementById('addBtn').style.display = 'none';
        document.getElementById('editButtons').style.display = 'flex';
        
        renderQueue();
        document.getElementById('itemInput').focus();
    }

    window.cancelEdit = function() {
        editingIndex = -1;
        document.getElementById('addBtn').style.display = 'block';
        document.getElementById('editButtons').style.display = 'none';
        
        // Reset form to default state (respecting current date)
        const dateVal = document.getElementById('entryForm').querySelector('[name="date"]').value;
        document.getElementById('entryForm').reset();
        document.getElementById('entryForm').querySelector('[name="date"]').value = dateVal;
        
        renderQueue();
    }

    window.removeItem = function(idx) {
        if(idx === editingIndex) cancelEdit();
        batchQueue.splice(idx, 1);
        if(idx < editingIndex) editingIndex--;
        renderQueue();
    }

    // --- 6. SUBMIT ---
    window.submitBatch = async function() {
        if(!confirm(`Submit ${batchQueue.length} records to database?`)) return;
        
        const btn = document.getElementById('submitBtn');
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';

        try {
            const res = await fetch('submit_batch.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(batchQueue)
            });
            const data = await res.json();

            if(data.success) {
                alert(`✅ Success! Added ${data.count} records.`);
                batchQueue = [];
                cancelEdit();
                renderQueue();
            } else {
                alert('❌ Error: ' + data.message);
            }
        } catch(e) {
            console.error(e);
            alert("❌ Network Error");
        } finally {
            btn.innerHTML = origText;
            btn.disabled = batchQueue.length === 0;
        }
    }
    </script>
</body>
</html>