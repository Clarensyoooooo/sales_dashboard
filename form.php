<?php 
require_once 'config.php'; 
requireLogin(); 
requirePermission('manage_sales'); 

$conn = getDBConnection();
$clientData = [];

// 1. FETCH FROM DATABASE: Get details for every company
$res = $conn->query("
    SELECT company, tin, address, contact_person_contact, payment_term
    FROM sales 
    WHERE company IS NOT NULL AND company != '' 
    ORDER BY date DESC, id DESC
");

while($row = $res->fetch_assoc()) {
    $comp = trim($row['company']);
    if (!isset($clientData[$comp])) {
        $clientData[$comp] = [
            'tin' => trim($row['tin'] ?? ''),
            'address' => trim($row['address'] ?? ''),
            'contact' => trim($row['contact_person_contact'] ?? ''),
            'term' => trim($row['payment_term'] ?? '')
        ];
    } else {
        if (empty($clientData[$comp]['tin']) && !empty($row['tin'])) $clientData[$comp]['tin'] = trim($row['tin']);
        if (empty($clientData[$comp]['address']) && !empty($row['address'])) $clientData[$comp]['address'] = trim($row['address']);
        if (empty($clientData[$comp]['contact']) && !empty($row['contact_person_contact'])) $clientData[$comp]['contact'] = trim($row['contact_person_contact']);
        if (empty($clientData[$comp]['term']) && !empty($row['payment_term'])) $clientData[$comp]['term'] = trim($row['payment_term']);
    }
}
$conn->close();

// 2. MERGE WITH CSV
$csvFile = 'CLIENT-TIN.csv'; 
if (file_exists($csvFile) && ($handle = fopen($csvFile, "r")) !== FALSE) {
    fgetcsv($handle); 
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $compName = trim($data[0]);
        $tin = isset($data[1]) ? trim($data[1]) : '';
        if (!empty($compName)) {
            if (!isset($clientData[$compName])) {
                $clientData[$compName] = ['tin' => $tin, 'address' => '', 'contact' => '', 'term' => ''];
            } elseif (empty($clientData[$compName]['tin'])) {
                $clientData[$compName]['tin'] = $tin;
            }
        }
    }
    fclose($handle);
}

ksort($clientData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Sale / Client - NAM Supply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-section-header { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #eee; }
        .form-section-header.blue { color: #0d6efd; border-color: #0d6efd; }
        .form-section-header.green { color: #198754; border-color: #198754; }
        .form-section-header.orange { color: #fd7e14; border-color: #fd7e14; }
        .calculated-field { background-color: #e9ecef; pointer-events: none; font-weight: 600; }
        .table-wrap { max-height: 65vh; overflow-y: auto; }
        tr.editing { background-color: #fff3cd !important; border-left: 4px solid #ffc107; }
        .required-star { color: #dc3545; }
        .cursor-pointer { cursor: pointer; }
    </style>
</head>
<body class="bg-light">

    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4 pb-5">
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
                                    <div class="col-12">
                                        <label class="form-label small fw-bold d-flex justify-content-between align-items-end mb-1">
                                            <span>Company Name <span class="required-star">*</span></span>
                                            <div class="d-flex gap-2">
                                                <a href="javascript:void(0)" onclick="openClientListModal()" class="text-decoration-none small text-dark fw-bold"><i class="fas fa-list me-1"></i>List</a>
                                                <a href="javascript:void(0)" onclick="openClientModal()" class="text-decoration-none small text-primary fw-bold"><i class="fas fa-plus-circle me-1"></i>Add</a>
                                            </div>
                                        </label>
                                        <input type="text" name="company" id="companyInput" class="form-control form-control-sm" list="companyList" required placeholder="Type or search..." autocomplete="off">
                                        <datalist id="companyList">
                                            <?php foreach(array_keys($clientData) as $comp): ?>
                                                <option value="<?php echo htmlspecialchars($comp); ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Date <span class="required-star">*</span></label>
                                        <input type="date" name="date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">PO / Inquiry #</label>
                                        <input type="text" name="po_number" class="form-control form-control-sm" placeholder="PO or Inquiry Ref">
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
                                    <div class="col-12">
                                        <textarea name="remarks" class="form-control form-control-sm mt-1" rows="1" placeholder="Remarks..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-section-header green">2. Item Details</div>

                                 <div class="col-12">
                                        <label class="form-label small fw-bold">Item Description <span class="required-star">*</span></label>
                                        <input type="text" name="item" id="itemInput" list="productList" class="form-control form-control-sm" required placeholder="Type to search stock..." autocomplete="off">
                                        <datalist id="productList"></datalist>
                                </div>
                                
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Category <span class="required-star">*</span></label>
                                        <select name="category" required id="catSelect" class="form-select form-select-sm">
                                            <option value="OFFICE SUPPLIES">OFFICE SUPPLIES</option>
                                            <option value="CLEANING MATERIALS">CLEANING MATERIALS</option>
                                            <option value="CONSUMABLES">CONSUMABLES</option>
                                            <option value="OFFICE TOOLS AND EQUIPMENT">OFFICE TOOLS</option>
                                            <option value="PPE">PPE</option>
                                            <option value="MATERIALS">MATERIALS</option>
                                            <option value="COMPANY UNIFORM">UNIFORMS</option>
                                            <option value="OFFICE FURNITURE & FIXTURES">FURNITURE</option>
                                            <option value="MEDICINE">MEDICINE</option>
                                            <option value="OTHERS">OTHERS</option>
                                        </select>
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
                            <small class="text-muted">Review items below before submitting to Sales or Quotations.</small>
                        </div>
                        <span class="badge bg-primary rounded-pill fs-6" id="qCount">0 Items</span>
                    </div>
                    <div class="card-body p-0 d-flex flex-column h-100">
                        <div class="table-wrap flex-grow-1">
                            <table class="table table-hover table-striped mb-0" id="qTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Item / Category</th>
                                        <th>Client / Inquiry</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fas fa-box-open fa-3x mb-3 opacity-25"></i><br>
                                            List is empty. Add items from the left.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="p-3 border-top bg-light d-flex gap-2">
                            <button onclick="submitBatch('sales')" class="btn btn-primary w-100 fw-bold shadow-sm" id="submitBtn" disabled>
                                <i class="fas fa-save me-2"></i>Save to Sales Records
                            </button>
                            <button onclick="submitBatch('quotations')" class="btn btn-warning text-dark w-100 fw-bold shadow-sm" id="quoteBtn" disabled>
                                <i class="fas fa-file-invoice me-2"></i>Send to Quotations
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="clientListModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-users me-2"></i>Select Client to Edit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" id="clientSearch" class="form-control mb-3" placeholder="Search Company Name...">
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover table-sm">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Company</th>
                                    <th>TIN</th>
                                    <th>Contact</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="clientListBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="clientModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-building me-2"></i>Save Client Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="clientForm" onsubmit="saveClient(event)">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Company Name</label>
                            <input type="text" id="modalClientName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">TIN</label>
                            <input type="text" id="modalClientTIN" class="form-control" placeholder="000-000-000-000">
                        </div>
                        <div class="d-grid">
                            <button type="submit" id="btnSaveClient" class="btn btn-primary fw-bold">Save Details</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let productMap = new Map();
        let batchQueue = [];
        let editingIndex = -1;
        const clients = <?php echo json_encode($clientData); ?>;

        // --- 0. SMART CLIENT AUTO-FILL ---
        document.getElementById('companyInput').addEventListener('input', function() {
            fillClientDetails(this.value);
        });

        function fillClientDetails(compName) {
            if (clients.hasOwnProperty(compName)) {
                const c = clients[compName];
                document.querySelector('[name="tin"]').value = c.tin || '';
                document.querySelector('[name="address"]').value = c.address || '';
                document.querySelector('[name="contact_person_contact"]').value = c.contact || '';
                document.querySelector('[name="payment_term"]').value = c.term || '';
            }
        }

        // --- MANAGE CLIENT LOGIC ---
        let clientModalInstance;
        let clientListModalInstance;

        document.addEventListener('DOMContentLoaded', () => {
            clientModalInstance = new bootstrap.Modal(document.getElementById('clientModal'));
            clientListModalInstance = new bootstrap.Modal(document.getElementById('clientListModal'));
        });

        function openClientListModal() {
            renderClientList();
            clientListModalInstance.show();
        }

        function renderClientList(filter = '') {
            const tbody = document.getElementById('clientListBody');
            tbody.innerHTML = '';
            filter = filter.toLowerCase();

            Object.keys(clients).forEach(comp => {
                if(comp.toLowerCase().includes(filter)) {
                    const c = clients[comp];
                    const tr = document.createElement('tr');
                    tr.classList.add('cursor-pointer');
                    tr.innerHTML = `
                        <td class="fw-bold text-primary">${comp}</td>
                        <td><small>${c.tin}</small></td>
                        <td><small>${c.contact}</small></td>
                        <td class="text-end"><button class="btn btn-sm btn-outline-primary py-0">Select</button></td>
                    `;
                    tr.onclick = () => {
                        document.getElementById('companyInput').value = comp;
                        fillClientDetails(comp);
                        clientListModalInstance.hide();
                    };
                    tbody.appendChild(tr);
                }
            });
        }

        document.getElementById('clientSearch').addEventListener('input', (e) => {
            renderClientList(e.target.value);
        });

        function openClientModal() {
            document.getElementById('modalClientName').value = document.getElementById('companyInput').value;
            document.getElementById('modalClientTIN').value = document.querySelector('[name="tin"]').value;
            clientModalInstance.show();
        }

        async function saveClient(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveClient');
            const origText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            btn.disabled = true;

            const company = document.getElementById('modalClientName').value;
            const tin = document.getElementById('modalClientTIN').value;
            const formData = new FormData();
            formData.append('company', company);
            formData.append('tin', tin);

            try {
                const res = await fetch('save_client.php', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    document.getElementById('companyInput').value = company;
                    document.querySelector('[name="tin"]').value = tin;
                    if(!clients[company]) clients[company] = {};
                    clients[company].tin = tin;
                    clientModalInstance.hide();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch(err) {
                console.error(err);
            } finally {
                btn.innerHTML = origText;
                btn.disabled = false;
            }
        }

        // --- 1. INIT PRODUCTS ---
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

        // --- 2. CALCULATIONS & AUTOFILL ---
        document.getElementById('itemInput').addEventListener('input', function() {
            const p = productMap.get(this.value);
            if (p) {
                document.getElementById('sPrice').value = p.supplier_price;
                document.getElementById('nPrice').value = p.nam_price;
                document.getElementById('catSelect').value = p.category_code || 'OFFICE SUPPLIES';
                if(p.supplier) document.getElementById('supplierName').value = p.supplier;
                calculate();
            }
        });

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

        // --- 3. HANDLE FORM SUBMIT (QUEUE) ---
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
            
            // Reset logic based on Lock
            if(editingIndex === -1) {
                if(document.getElementById('lockHeader').checked) {
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

        function renderQueue() {
            const tbody = document.querySelector('#qTable tbody');
            const count = document.getElementById('qCount');
            const btnSales = document.getElementById('submitBtn');
            const btnQuote = document.getElementById('quoteBtn');
            
            count.textContent = batchQueue.length + " Items";
            
            // Toggle both buttons depending on if queue has items
            btnSales.disabled = batchQueue.length === 0;
            btnQuote.disabled = batchQueue.length === 0;

            if (batchQueue.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-box-open fa-3x mb-3 opacity-25"></i><br>List is empty. Add items from the left.</td></tr>';
                return;
            }

            let html = '';
            batchQueue.forEach((item, idx) => {
                const total = (parseFloat(item.quantity_requested) * parseFloat(item.nam_unit_price)).toFixed(2);
                
                html += `
                    <tr class="${idx === editingIndex ? 'editing' : ''}">
                        <td>
                            <div class="fw-bold text-dark">${item.item}</div>
                            <div class="small text-muted">${item.category}</div>
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width:150px;">${item.company}</div>
                            <div class="small text-muted">${item.po_number || '-'}</div>
                        </td>
                        <td class="text-center">${item.quantity_requested}</td>
                        <td class="text-end">${item.nam_unit_price}</td>
                        <td class="text-end fw-bold text-primary">${total}</td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <button onclick="editItem(${idx})" class="btn btn-outline-secondary"><i class="fas fa-pencil-alt"></i></button>
                                <button onclick="removeItem(${idx})" class="btn btn-outline-danger"><i class="fas fa-times"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        window.editItem = function(idx) {
            editingIndex = idx;
            const item = batchQueue[idx];
            const form = document.getElementById('entryForm');
            for (const [key, value] of Object.entries(item)) {
                const input = form.querySelector(`[name="${key}"]`);
                if(input) input.value = value;
            }
            
            document.getElementById('addBtn').style.display = 'none';
            document.getElementById('editButtons').style.display = 'flex';
            renderQueue();
        }

        window.cancelEdit = function() {
            editingIndex = -1;
            document.getElementById('entryForm').reset();
            document.getElementById('addBtn').style.display = 'block';
            document.getElementById('editButtons').style.display = 'none';
            renderQueue();
        }

        window.removeItem = function(idx) {
            batchQueue.splice(idx, 1);
            if(idx < editingIndex) editingIndex--;
            renderQueue();
        }

        // --- 4. NEW BATCH SUBMIT LOGIC ---
        window.submitBatch = async function(destination) {
            const actionText = destination === 'quotations' ? 'SEND BATCH TO QUOTATIONS' : 'SAVE AS SALES';
            if(!confirm(`Are you sure you want to ${actionText} with ${batchQueue.length} items?`)) return;
            
            const btnSales = document.getElementById('submitBtn');
            const btnQuote = document.getElementById('quoteBtn');
            
            // Disable both while processing
            btnSales.disabled = true;
            btnQuote.disabled = true;

            if (destination === 'quotations') {
                btnQuote.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                
                // Package the data exactly how quotations.php expects it
                const header = {
                    date: batchQueue[0].date,
                    quote_ref: 'RES-' + Date.now().toString().slice(-6), // Auto temp ref
                    company: batchQueue[0].company,
                    po: batchQueue[0].po_number,
                    term: batchQueue[0].payment_term,
                    remarks: batchQueue[0].remarks
                };
                
                const items = batchQueue.map(r => ({
                    item: r.item,
                    category: r.category,
                    quantity: r.quantity_requested,
                    s_price: r.suppliers_price,
                    n_price: r.nam_unit_price
                }));

                try {
                    const res = await fetch('quotations.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            action: 'create_quote_batch',
                            header: header,
                            items: items,
                            status: 'Reserved' // Automatically tags them as Reserved in quotations
                        })
                    });
                    const d = await res.json();
                    if(d.success) {
                        // INSTANT REDIRECT TO QUOTATIONS
                        window.location.href = 'quotations.php?msg=created';
                    } else {
                        alert('Error saving to quotations: ' + d.message);
                        btnQuote.innerHTML = '<i class="fas fa-file-invoice me-2"></i>Send to Quotations';
                        btnSales.disabled = false; btnQuote.disabled = false;
                    }
                } catch(e) { 
                    alert('Network Error connecting to quotations.php'); 
                    btnQuote.innerHTML = '<i class="fas fa-file-invoice me-2"></i>Send to Quotations';
                    btnSales.disabled = false; btnQuote.disabled = false;
                }

            } else {
                // destination === 'sales'
                btnSales.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                
                try {
                    const res = await fetch('submit_batch.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(batchQueue)
                    });
                    const d = await res.json();
                    if(d.success) { 
                        // INSTANT REDIRECT TO RECORDS
                        window.location.href = 'records.php?success=1';
                    } else { 
                        alert('Error saving sales: ' + d.message); 
                        btnSales.innerHTML = '<i class="fas fa-save me-2"></i>Save to Sales Records';
                        btnSales.disabled = false; btnQuote.disabled = false;
                    }
                } catch(e) { 
                    alert('Network Error connecting to submit_batch.php');
                    btnSales.innerHTML = '<i class="fas fa-save me-2"></i>Save to Sales Records';
                    btnSales.disabled = false; btnQuote.disabled = false;
                }
            }
        }
    </script>
</body>
</html>