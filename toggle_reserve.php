<?php
require_once 'config.php';
requireLogin();
// Fetch the current user's name for the "Delivered By" placeholder 
$currentUser = $_SESSION['username'] ?? 'System User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics Dashboard - NAM Supply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .company-card { border: none; border-radius: 12px; margin-bottom: 20px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow: hidden; }
        .company-header { background: #343a40; color: white; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; }
        .po-section { border-bottom: 1px solid #eee; padding: 0 0 10px 0; }
        .po-section:last-child { border-bottom: none; }
        .po-header { background: #f8f9fa; padding: 8px 20px; font-weight: 600; color: #555; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; }
        .item-row { padding: 12px 20px; display: flex; align-items: center; border-bottom: 1px solid #f1f1f1; transition: background 0.2s; gap: 15px; }
        .item-row:hover { background: #fafafa; }
        .item-row.done { background-color: #f0fdf4; opacity: 0.8; border-left: 4px solid #198754;}
        .item-row.done .item-name { text-decoration: line-through; color: #888; }
        .qty-box { width: 50px; text-align: center; background: #e9ecef; border-radius: 8px; padding: 5px; }
        .qty-val { font-weight: 700; font-size: 1.1rem; line-height: 1; }
        .qty-lbl { font-size: 0.65rem; text-transform: uppercase; color: #666; }
        
        .summary-box { background: #f8f9fa; border-radius: 8px; padding: 15px; margin: 15px 20px; border: 1px dashed #ced4da; }
        
        .form-check-input.bulk-check { transform: scale(1.3); cursor: pointer; border-color: #adb5bd; }
        .form-check-input.bulk-check:checked { background-color: #198754; border-color: #198754; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container py-4" style="max-width: 900px;">
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="fas fa-truck-loading me-2 text-primary"></i>Daily Logistics</h4>
                <small class="text-muted">Manage Full and Partial Deliveries</small>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success fw-bold shadow-sm" id="btnBulkAction" onclick="openBulkModal()" style="display: none;">
                    <i class="fas fa-boxes me-2"></i>Deliver Selected (<span id="selectedCount">0</span>)
                </button>
                <button class="btn btn-light shadow-sm fw-bold border" onclick="loadLogistics()"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-2 d-flex align-items-center gap-3">
                <div class="form-check ms-3">
                    <input class="form-check-input bulk-check" type="checkbox" id="selectAllGlobal" onchange="toggleSelectAll(this)">
                    <label class="form-check-label fw-bold text-muted small pt-1" for="selectAllGlobal">Select All Visible</label>
                </div>
                <input type="text" id="searchInput" class="form-control border-0 shadow-none ps-3 border-start" placeholder="Search Company, PO, or Item..." onkeyup="render()">
            </div>
        </div>

        <div id="logistics-container">
            <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
        </div>

    </div>

    <div class="modal fade" id="bulkModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-success"><i class="fas fa-truck me-2"></i>Confirm Delivery</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small"><i class="fas fa-info-circle me-1"></i> If you lower the quantity, it will be recorded as a <b>Partial Delivery</b>.</div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th>Item</th>
                                    <th>PO #</th>
                                    <th class="text-center" width="100">Pending</th>
                                    <th class="text-center" width="140">Deliver Qty</th>
                                </tr>
                            </thead>
                            <tbody id="bulkModalBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success fw-bold px-4" id="btnConfirmBulk" onclick="submitBulk()">
                        <i class="fas fa-check-double me-2"></i>Confirm Delivery
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let rawData = [];
    const bulkModal = new bootstrap.Modal(document.getElementById('bulkModal'));
    const currentUser = "<?php echo $currentUser; ?>"; // Getting user from PHP

    document.addEventListener('DOMContentLoaded', loadLogistics);

    async function loadLogistics() {
        try {
            const res = await fetch('get_deliveries.php');
            rawData = await res.json();
            render();
        } catch(e) {
            console.error(e);
            document.getElementById('logistics-container').innerHTML = `<div class="alert alert-danger">Failed to load data.</div>`;
        }
    }

    function render() {
        const container = document.getElementById('logistics-container');
        const search = document.getElementById('searchInput').value.toLowerCase();
        
        document.getElementById('selectAllGlobal').checked = false;
        updateBulkUI();

        const companyGroups = {};

        rawData.forEach(item => {
            const str = (item.company + " " + item.po_number + " " + item.item).toLowerCase();
            if(!str.includes(search)) return;

            if(!companyGroups[item.company]) {
                companyGroups[item.company] = { name: item.company, pos: {} };
            }

            const poKey = item.po_number ? item.po_number : 'NO_PO';
            if(!companyGroups[item.company].pos[poKey]) {
                companyGroups[item.company].pos[poKey] = { id: poKey, items: [], total: 0, done: 0, deliveredLog: [] };
            }

            const isDone = (item.date_delivered && item.date_delivered !== '0000-00-00');
            companyGroups[item.company].pos[poKey].items.push(item);
            companyGroups[item.company].pos[poKey].total++;
            
            if(isDone) {
                companyGroups[item.company].pos[poKey].done++;
                companyGroups[item.company].pos[poKey].deliveredLog.push(item);
            }
        });

        if(Object.keys(companyGroups).length === 0) {
            container.innerHTML = `<div class="text-center py-5 text-muted"><i class="fas fa-box-open fs-1 mb-3 text-light"></i><br>No pending deliveries found.</div>`;
            return;
        }

        let html = '';
        
        for (const [compName, compData] of Object.entries(companyGroups)) {
            html += `
            <div class="company-card">
                <div class="company-header">
                    <span class="fw-bold"><i class="fas fa-building me-2"></i>${compName}</span>
                </div>
                <div class="bg-white">`;

            for (const [poId, poData] of Object.entries(compData.pos)) {
                const poLabel = poId === 'NO_PO' ? 'No PO Number' : `PO: ${poId}`;
                const isPoComplete = poData.total === poData.done;
                const progress = Math.round((poData.done / poData.total) * 100);

                html += `
                    <div class="po-section">
                        <div class="po-header">
                            <span>${poLabel}</span>
                            <span class="badge ${isPoComplete ? 'bg-success' : 'bg-secondary'}">${poData.done}/${poData.total} items</span>
                        </div>
                        <div class="progress rounded-0" style="height: 4px;">
                            <div class="progress-bar ${isPoComplete ? 'bg-success' : 'bg-primary'}" style="width: ${progress}%"></div>
                        </div>

                        <div>
                        ${poData.items.map(item => {
                            const isDelivered = (item.date_delivered && item.date_delivered !== '0000-00-00');
                            
                            let checkHtml = !isDelivered ? `
                                    <div class="me-3">
                                        <input class="form-check-input bulk-check" type="checkbox" 
                                            value="${item.id}" data-qty="${item.quantity_requested}"
                                            data-name="${item.item.replace(/"/g, '&quot;')}" data-po="${item.po_number || '-'}"
                                            onchange="updateBulkUI()">
                                    </div>` : `<div class="me-3" style="width: 24px;"></div>`;

                            let actionHtml = isDelivered 
                                ? `<span class="text-success fw-bold small d-block"><i class="fas fa-check-circle me-1"></i>Delivered</span>
                                   <span class="text-muted" style="font-size: 0.7rem;">${item.date_delivered}</span>` 
                                : `<button onclick="markDeliveredSingle(${item.id}, ${item.quantity_requested})" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold">Deliver</button>`;

                            return `
                            <div class="item-row ${isDelivered ? 'done' : ''}">
                                ${checkHtml}
                                <div class="qty-box">
                                    <div class="qty-val text-primary">${item.quantity_requested}</div>
                                    <div class="qty-lbl">Total</div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark item-name mb-1">${item.item}</div>
                                    ${item.address ? `<small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i>${item.address}</small>` : ''}
                                </div>
                                <div class="text-end">
                                    ${actionHtml}
                                </div>
                            </div>
                            `;
                        }).join('')}
                        </div>`;

                // --- DELIVERY SUMMARY SECTION ---
                if (poData.deliveredLog.length > 0) {
                    const totalQtyDelivered = poData.deliveredLog.reduce((sum, i) => sum + parseFloat(i.quantity_requested), 0);
                    
                    html += `
                        <div class="summary-box">
                            <h6 class="fw-bold text-success mb-2"><i class="fas fa-clipboard-check me-2"></i>Delivery Summary Log</h6>
                            <div class="small text-muted mb-2">
                                <strong>Total Items Delivered:</strong> ${totalQtyDelivered} unit(s) across ${poData.deliveredLog.length} record(s).<br>
                                <strong>Logged By:</strong> ${currentUser}
                            </div>
                            <ul class="list-unstyled mb-0 small">
                                ${poData.deliveredLog.map(log => `
                                    <li class="border-bottom py-1 d-flex justify-content-between">
                                        <span><i class="fas fa-check text-success me-1"></i> [Qty: ${log.quantity_requested}] ${log.item.substring(0,30)}...</span>
                                        <span class="fst-italic">${log.date_delivered}</span>
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    `;
                }

                html += `</div>`;
            }
            html += `</div></div>`;
        }
        container.innerHTML = html;
    }

    // (The rest of the JS functions: toggleSelectAll, updateBulkUI, openBulkModal, submitBulk, markDeliveredSingle are exactly the same as previously provided)
    function toggleSelectAll(source) { document.querySelectorAll('.bulk-check').forEach(cb => cb.checked = source.checked); updateBulkUI(); }
    function updateBulkUI() {
        const checked = document.querySelectorAll('.bulk-check:checked');
        const btn = document.getElementById('btnBulkAction');
        document.getElementById('selectedCount').textContent = checked.length;
        btn.style.display = checked.length > 0 ? 'inline-block' : 'none';
    }
    function openBulkModal() {
        const checked = document.querySelectorAll('.bulk-check:checked');
        if(checked.length === 0) return;
        const tbody = document.getElementById('bulkModalBody');
        tbody.innerHTML = '';
        checked.forEach(cb => {
            const id = cb.value, name = cb.getAttribute('data-name'), po = cb.getAttribute('data-po'), qty = cb.getAttribute('data-qty');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span class="fw-bold text-dark">${name}</span></td><td class="text-muted">${po}</td><td class="text-center bg-light">${qty}</td>
                <td><input type="number" class="form-control form-control-sm text-center fw-bold border-primary bulk-qty-input" data-id="${id}" data-max="${qty}" value="${qty}" min="1" max="${qty}"></td>`;
            tbody.appendChild(tr);
        });
        bulkModal.show();
    }
    async function submitBulk() {
        const inputs = document.querySelectorAll('.bulk-qty-input');
        const deliveries = [];
        for (const input of inputs) {
            const qty = parseInt(input.value), max = parseInt(input.getAttribute('data-max')), id = input.getAttribute('data-id');
            if(isNaN(qty) || qty < 1 || qty > max) { alert("Invalid quantity. Please check."); return; }
            deliveries.push({ id: id, qty: qty });
        }
        if(deliveries.length === 0) return;
        const btn = document.getElementById('btnConfirmBulk');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...'; btn.disabled = true;
        try {
            const res = await fetch('mark_delivered.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ deliveries: deliveries }) });
            const data = await res.json();
            if(data.success) { bulkModal.hide(); loadLogistics(); alert(data.message || "Deliveries recorded successfully!"); } 
            else { alert("Error: " + data.message); }
        } catch(e) { console.error(e); alert("Network error occurred."); } 
        finally { btn.innerHTML = originalText; btn.disabled = false; }
    }
    function markDeliveredSingle(id, maxQty) {
        const cb = document.querySelector(`.bulk-check[value="${id}"]`);
        if(cb) {
            document.querySelectorAll('.bulk-check').forEach(c => c.checked = false);
            cb.checked = true; updateBulkUI(); openBulkModal(); 
        }
    }
    </script>
</body>
</html>