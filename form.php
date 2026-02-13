<?php require_once 'config.php'; requireLogin(); requirePermission('manage_sales'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Sales Encoder - Complete</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        .layout-grid { display: grid; grid-template-columns: 400px 1fr; gap: 20px; }
        
        h1 { color: #333; margin-bottom: 5px; font-size: 22px; }
        .subtitle { color: #666; margin-bottom: 15px; font-size: 14px; }
        
        .form-section { background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #e9ecef; margin-bottom: 15px; }
        .section-title { font-size: 14px; font-weight: 700; color: #0078d4; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }
        
        label { color: #495057; font-weight: 600; margin-bottom: 3px; font-size: 12px; }
        label .req { color: #dc3545; }
        input, select, textarea { padding: 6px 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 13px; width: 100%; }
        input:focus { outline: none; border-color: #0078d4; box-shadow: 0 0 0 2px rgba(0,120,212,0.1); }
        textarea { resize: vertical; min-height: 60px; }
        
        /* Buttons */
        .btn { padding: 8px 16px; border-radius: 4px; font-size: 13px; cursor: pointer; border: none; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 5px; }
        
        .btn-add { background: #107c10; color: white; width: 100%; margin-top: 10px; padding: 12px; font-size: 14px; }
        .btn-add:hover { background: #0b5a0b; }
        
        .btn-update { background: #f59e0b; color: #fff; width: 100%; margin-top: 10px; padding: 12px; font-size: 14px; display: none; }
        .btn-update:hover { background: #d97706; }

        .btn-cancel { background: #6c757d; color: white; width: 100%; margin-top: 5px; padding: 8px; font-size: 12px; display: none; }
        
        .btn-submit { background: #0078d4; color: white; width: 100%; padding: 15px; font-size: 16px; margin-top: 20px; }
        .btn-submit:disabled { background: #ccc; cursor: not-allowed; }

        .btn-edit { background: #ffc107; color: #333; padding: 4px 8px; font-size: 12px; }
        .btn-danger { background: #dc3545; color: white; padding: 4px 8px; font-size: 12px; }
        
        /* Queue Table */
        .queue-container { height: 100%; display: flex; flex-direction: column; }
        .table-wrap { flex: 1; overflow: auto; border: 1px solid #dee2e6; border-radius: 4px; max-height: 80vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { background: #e9ecef; text-align: left; padding: 8px; position: sticky; top: 0; z-index: 10; }
        td { padding: 8px; border-bottom: 1px solid #f1f3f5; }
        tr:hover { background: #f8f9fa; }
        tr.editing { background: #fffbeb; border-left: 3px solid #f59e0b; }
        
        .calculated { background: #e9ecef; font-weight: bold; color: #495057; pointer-events: none; }
        .lock-box { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #0078d4; font-weight: 600; cursor: pointer; margin-bottom: 10px; }

        @media (max-width: 1024px) { .layout-grid { grid-template-columns: 1fr; } .table-wrap { max-height: 400px; } }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container" style="margin-top: 20px;">
        <div class="layout-grid">
            
            <div>
                <h1>Batch Sales Encoder</h1>
                <p class="subtitle">Complete Data Entry Mode</p>
                
                <form id="entryForm" onsubmit="handleFormSubmit(event)">
                    
                    <div class="form-section" style="border-left: 4px solid #0078d4;">
                        <div style="display:flex; justify-content:space-between;">
                            <div class="section-title">1. Client & Terms</div>
                            <label class="lock-box"><input type="checkbox" id="lockHeader" checked> Keep Filled</label>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group"><label>Date <span class="req">*</span></label><input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>"></div>
                            <div class="form-group"><label>PO Number</label><input type="text" name="po_number"></div>
                            
                            <div class="form-group full"><label>Company Name <span class="req">*</span></label><input type="text" name="company" required placeholder="e.g. SUMOPAK"></div>
                            
                            <div class="form-group full"><label>Address</label><input type="text" name="address"></div>
                            <div class="form-group"><label>TIN</label><input type="text" name="tin"></div>
                            <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person_contact"></div>
                            
                            <div class="form-group"><label>Payment Term</label><input type="text" name="payment_term"></div>
                            <div class="form-group"><label>Due Date</label><input type="date" name="due_date"></div>
                            
                            <div class="form-group"><label>SI Number (Ours)</label><input type="text" name="si_number"></div>
                            <div class="form-group full"><label>Remarks</label><textarea name="remarks" style="min-height:40px;"></textarea></div>
                        </div>
                    </div>

                    <div class="form-section" style="border-left: 4px solid #107c10;">
                        <div class="section-title">2. Item & Costing</div>
                        
                        <div class="form-grid">
                            <div class="form-group full">
                                <label>Category <span class="req">*</span></label>
                                <select name="category" required id="catSelect">
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
                            
                            <div class="form-group full">
                                <label>Item Description <span class="req">*</span></label>
                                <input type="text" name="item" id="itemInput" list="productList" required placeholder="Type to search..." autocomplete="off">
                                <datalist id="productList"></datalist>
                            </div>
                            
                            <div class="form-group"><label>Quantity <span class="req">*</span></label><input type="number" name="quantity_requested" id="quantity" required min="1" value="1"></div>
                            <div class="form-group"><label>S/N (Serial)</label><input type="text" name="sn"></div>
                            
                            <div class="form-group"><label>Supplier Price</label><input type="number" name="suppliers_price" id="sPrice" step="0.01"></div>
                            <div class="form-group"><label>NAM Price <span class="req">*</span></label><input type="number" name="nam_unit_price" id="nPrice" required step="0.01"></div>
                            
                            <div class="form-group"><label>Total Actual</label><input type="text" id="tActual" class="calculated"></div>
                            <div class="form-group"><label>Total Sales</label><input type="text" id="tSales" class="calculated"></div>
                        </div>
                    </div>

                    <div class="form-section" style="border-left: 4px solid #f97316;">
                        <div class="section-title">3. Sourcing Info</div>
                        <div class="form-grid">
                            <div class="form-group full"><label>Supplier Name</label><input type="text" name="supplier" id="supplierName"></div>
                            <div class="form-group"><label>Supp. Invoice No.</label><input type="text" name="sales_invoice_no"></div>
                            <div class="form-group"><label>Date Delivered</label><input type="date" name="date_delivered"></div>
                        </div>
                    </div>

                    <button type="submit" id="addBtn" class="btn btn-add">⬇️ ADD TO LIST</button>
                    <button type="submit" id="updateBtn" class="btn btn-update">💾 UPDATE ENTRY</button>
                    <button type="button" id="cancelBtn" class="btn btn-cancel" onclick="cancelEdit()">❌ CANCEL EDIT</button>
                </form>
            </div>

            <div class="queue-container">
                <div style="background: #fff; padding: 15px; border-bottom: 1px solid #eee;">
                    <h3 style="margin:0;">Pending Items: <span id="qCount" style="color:#0078d4;">0</span></h3>
                    <div style="font-size:12px; color:#666; margin-top:5px;">Review items below before submitting.</div>
                </div>

                <div class="table-wrap">
                    <table id="qTable">
                        <thead>
                            <tr>
                                <th>Item / Category</th>
                                <th>Client / PO</th>
                                <th style="text-align:right;">Qty</th>
                                <th style="text-align:right;">Price</th>
                                <th style="text-align:right;">Total</th>
                                <th>Supplier</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#999;">List is empty. Add items from the left.</td></tr>
                        </tbody>
                    </table>
                </div>

                <button onclick="submitBatch()" class="btn btn-submit" id="submitBtn" disabled>✅ Submit All Records</button>
            </div>

        </div>
    </div>

    <script>
    let productMap = new Map();
    let batchQueue = [];
    let editingIndex = -1; // Tracks which item is being edited

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
    const ids = ['quantity', 'sPrice', 'nPrice'];
    ids.forEach(id => document.getElementById(id).addEventListener('input', calculate));

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

    // --- 3. FORM HANDLER (ADD OR UPDATE) ---
    function handleFormSubmit(e) {
        e.preventDefault();
        const form = document.getElementById('entryForm');
        const formData = new FormData(form);
        const entry = Object.fromEntries(formData.entries());

        if(!entry.item || !entry.company || !entry.nam_unit_price) {
            alert("Please fill required fields");
            return;
        }

        if (editingIndex === -1) {
            // ADD MODE
            batchQueue.push(entry);
        } else {
            // UPDATE MODE
            batchQueue[editingIndex] = entry;
            cancelEdit(); // Reset UI state
        }

        renderQueue();
        
        // Clear Form Logic
        if(editingIndex === -1) { // Only clear if we were adding
            const locked = document.getElementById('lockHeader').checked;
            if(locked) {
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

        count.textContent = batchQueue.length;
        btn.disabled = batchQueue.length === 0;

        if (batchQueue.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:#999;">List is empty. Add items from the left.</td></tr>';
            return;
        }

        let html = '';
        batchQueue.forEach((item, idx) => {
            const total = (parseFloat(item.quantity_requested) * parseFloat(item.nam_unit_price)).toFixed(2);
            // Highlight row if currently editing
            const rowClass = (idx === editingIndex) ? 'editing' : '';
            
            html += `
                <tr class="${rowClass}">
                    <td>
                        <div style="font-weight:600;">${item.item}</div>
                        <div style="font-size:11px; color:#666;">${item.category}</div>
                    </td>
                    <td>
                        <div>${item.company}</div>
                        <div style="font-size:11px; color:#666;">PO: ${item.po_number || '-'}</div>
                    </td>
                    <td style="text-align:right;">${item.quantity_requested}</td>
                    <td style="text-align:right;">${item.nam_unit_price}</td>
                    <td style="text-align:right; font-weight:bold;">${total}</td>
                    <td><small>${item.supplier || '-'}</small></td>
                    <td>
                        <button onclick="editItem(${idx})" class="btn btn-edit" title="Edit">✏️</button>
                        <button onclick="removeItem(${idx})" class="btn btn-danger" title="Remove">×</button>
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

        // Populate Form
        for (const [key, value] of Object.entries(item)) {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) input.value = value;
        }
        calculate(); // Recalc totals

        // Switch UI to Edit Mode
        document.getElementById('addBtn').style.display = 'none';
        document.getElementById('updateBtn').style.display = 'inline-flex';
        document.getElementById('cancelBtn').style.display = 'inline-flex';
        
        // Highlight logic
        renderQueue(); // Re-render to show highlight
        document.getElementById('itemInput').focus();
    }

    window.cancelEdit = function() {
        editingIndex = -1;
        document.getElementById('addBtn').style.display = 'inline-flex';
        document.getElementById('updateBtn').style.display = 'none';
        document.getElementById('cancelBtn').style.display = 'none';
        
        // Clear specific fields (optional, or reset form?)
        // Usually, user expects form to clear if they cancel
        document.getElementById('entryForm').reset();
        document.getElementById('entryForm').querySelector('[name="date"]').value = new Date().toISOString().split('T')[0];
        
        renderQueue();
    }

    window.removeItem = function(idx) {
        if(idx === editingIndex) cancelEdit(); // Cancel edit if we delete the item being edited
        batchQueue.splice(idx, 1);
        
        // Adjust index if we deleted something above the edited item
        if(idx < editingIndex) editingIndex--;
        
        renderQueue();
    }

    // --- 6. SUBMIT ---
    window.submitBatch = async function() {
        if(!confirm(`Submit ${batchQueue.length} records?`)) return;
        if(editingIndex !== -1) {
            if(!confirm("You are currently editing an item. It will NOT be updated unless you click 'Update Entry'. Continue?")) return;
        }

        const btn = document.getElementById('submitBtn');
        const origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = "Processing...";

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
                cancelEdit(); // Reset everything
                renderQueue();
            } else {
                alert('❌ Error: ' + data.message);
            }
        } catch(e) {
            console.error(e);
            alert("❌ Network Error");
        } finally {
            btn.textContent = origText;
            btn.disabled = batchQueue.length === 0;
        }
    }
    </script>
</body>
</html>