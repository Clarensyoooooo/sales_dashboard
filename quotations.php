<?php 
require_once 'config.php'; 
requireLogin(); 

// --- 1. APPROVE QUOTE (WAITING FOR SIGNATURES) ---
if (isset($_POST['approve_id'])) {
    $q_id = intval($_POST['approve_id']);
    $conn = getDBConnection();
    $conn->query("UPDATE quotations SET status = 'Approved' WHERE id = $q_id");
    header("Location: quotations.php?msg=approved");
    exit;
}

// --- 2. FINALIZE TO SALE (CONVERT & DEDUCT STOCK) ---
if (isset($_POST['convert_id'])) {
    $q_id = intval($_POST['convert_id']);
    $conn = getDBConnection();
    
    $q = $conn->query("SELECT * FROM quotations WHERE id = $q_id")->fetch_assoc();
    
    if ($q) {
        $check = $conn->prepare("SELECT current_stock FROM products WHERE name = ?");
        $check->bind_param("s", $q['item']);
        $check->execute();
        $stock = $check->get_result()->fetch_assoc();
        
        if ($stock && $stock['current_stock'] >= $q['quantity_requested']) {
            $income = ($q['nam_unit_price'] * $q['quantity_requested']) - ($q['suppliers_price'] * $q['quantity_requested']);
            $percent = ($q['nam_unit_price'] > 0) ? ($income / ($q['nam_unit_price'] * $q['quantity_requested'])) * 100 : 0;
            
            $stmt = $conn->prepare("INSERT INTO sales (date, company, category, item, quantity_requested, suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount, income, income_percent, po_number, payment_term, remarks, date_delivered, due_date) VALUES (CURRENT_DATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)");
            
            $total_actual = $q['suppliers_price'] * $q['quantity_requested'];
            $total_nam = $q['nam_unit_price'] * $q['quantity_requested'];
            
            $stmt->bind_param("sssidddddssss", $q['company'], $q['category'], $q['item'], $q['quantity_requested'], $q['suppliers_price'], $total_actual, $q['nam_unit_price'], $total_nam, $income, $percent, $q['po_number'], $q['payment_term'], $q['remarks']);
            
            if ($stmt->execute()) {
                $conn->query("UPDATE products SET current_stock = current_stock - {$q['quantity_requested']} WHERE name = '{$conn->real_escape_string($q['item'])}'");
                $conn->query("UPDATE quotations SET status = 'Converted' WHERE id = $q_id");
                $msg = "success";
            } else {
                $msg = "error_db";
            }
        } else {
            $msg = "error_stock"; 
        }
    }
    header("Location: quotations.php?msg=$msg");
    exit;
}

// --- 3. CREATE QUOTE ---
if (isset($_POST['action']) && $_POST['action'] == 'create_quote') {
    $conn = getDBConnection();
    $total = $_POST['n_price'] * $_POST['quantity'];
    $category = !empty($_POST['category']) ? $_POST['category'] : 'Uncategorized';

    $stmt = $conn->prepare("INSERT INTO quotations (date, quote_ref, company, category, item, quantity_requested, suppliers_price, nam_unit_price, total_amount, po_number, payment_term, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssidddsss", $_POST['date'], $_POST['quote_ref'], $_POST['company'], $category, $_POST['item'], $_POST['quantity'], $_POST['s_price'], $_POST['n_price'], $total, $_POST['po'], $_POST['term'], $_POST['remarks']);
    $stmt->execute();
    header("Location: quotations.php?msg=created");
    exit;
}

// --- 4. EDIT QUOTE ---
if (isset($_POST['action']) && $_POST['action'] == 'edit_quote') {
    $conn = getDBConnection();
    $id = intval($_POST['edit_id']);
    $qty = intval($_POST['edit_quantity']);
    $s_price = floatval($_POST['edit_s_price']);
    $n_price = floatval($_POST['edit_n_price']);
    $total = $qty * $n_price;
    
    $stmt = $conn->prepare("UPDATE quotations SET quantity_requested=?, suppliers_price=?, nam_unit_price=?, total_amount=? WHERE id=?");
    $stmt->bind_param("idddi", $qty, $s_price, $n_price, $total, $id);
    $stmt->execute();
    header("Location: quotations.php?msg=edited");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quotations - NAM Supply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .preview-box { border: 2px dashed #ccc; padding: 20px; background: #fff; font-family: 'Courier New', Courier, monospace; }
        .receipt-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .receipt-total { border-top: 1px solid #000; margin-top: 10px; padding-top: 5px; text-align: right; font-weight: bold; }
        .accordion-button:not(.collapsed) { background-color: #e0e7ff; color: #4338ca; font-weight: bold; }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container-fluid mt-4 pb-5">
        <div class="row g-4">
            
            <div class="col-lg-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white fw-bold"><i class="fas fa-file-invoice me-2"></i>New Quotation</div>
                    <div class="card-body bg-light">
                        <form method="POST" id="quoteForm">
                            <input type="hidden" name="action" value="create_quote">
                            <input type="hidden" name="category" id="categoryField">
                            
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="small text-muted fw-bold">Date</label>
                                    <input type="date" name="date" id="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted fw-bold">Quote Reference</label>
                                    <input type="text" name="quote_ref" id="quote_ref" class="form-control" required>
                                </div>
                                <div class="col-12">
                                    <label class="small text-muted fw-bold">Client Company</label>
                                    <input type="text" name="company" id="company" class="form-control" placeholder="Client Name" required>
                                </div>
                                <div class="col-12">
                                    <label class="small text-muted fw-bold">Item</label>
                                    <input type="text" name="item" id="itemInput" list="productList" class="form-control" placeholder="Search Item..." required autocomplete="off">
                                    <datalist id="productList"></datalist>
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted fw-bold">Quantity</label>
                                    <input type="number" name="quantity" id="quantity" class="form-control" placeholder="Qty" required value="1" min="1">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted fw-bold">PO Number</label>
                                    <input type="text" name="po" id="po" class="form-control" placeholder="Optional PO #">
                                </div>
                                
                                <div class="col-12 mt-3">
                                    <div class="border rounded p-2 bg-white border-secondary-subtle">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="small text-muted fw-bold">Supplier Cost</label>
                                                <input type="number" step="0.01" name="s_price" id="s_price" class="form-control" placeholder="Cost" required>
                                            </div>
                                            <div class="col-6">
                                                <label class="small text-primary fw-bold">Selling Price</label>
                                                <input type="number" step="0.01" name="n_price" id="n_price" class="form-control border-primary" placeholder="Final Price" required>
                                            </div>
                                            <div class="col-6">
                                                <label class="small text-muted fw-bold">Markup (%)</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" step="0.01" id="markup_pct" class="form-control" placeholder="e.g. 35">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <label class="small text-muted fw-bold">Margin (%)</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" step="0.01" id="margin_pct" class="form-control" placeholder="Margin">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mt-2">
                                    <label class="small text-muted fw-bold">Payment Terms</label>
                                    <input type="text" name="term" id="term" class="form-control" placeholder="e.g. 30 Days">
                                </div>
                                <div class="col-12">
                                    <textarea name="remarks" id="remarks" class="form-control" placeholder="Remarks / Notes"></textarea>
                                </div>
                                
                                <div class="col-12 mt-2">
                                    <div class="border rounded p-2 bg-white border-secondary-subtle">
                                        <label class="small text-muted fw-bold d-block mb-1"><i class="fas fa-eye me-1"></i> Client Print Options</label>
                                        <div class="d-flex flex-wrap gap-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="showPo" checked>
                                                <label class="form-check-label small">Show PO</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="showTerms" checked>
                                                <label class="form-check-label small">Show Terms</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="showRemarks" checked>
                                                <label class="form-check-label small">Show Remarks</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-6 mt-3">
                                    <button type="button" class="btn btn-outline-dark w-100 fw-bold" onclick="showPreview()">
                                        <i class="fas fa-search me-1"></i> Preview
                                    </button>
                                </div>
                                <div class="col-6 mt-3">
                                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                                        <i class="fas fa-save me-1"></i> Save
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <?php if(isset($_GET['msg'])): ?>
                    <?php if($_GET['msg']=='error_stock'): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Cannot Finalize: <b>Insufficient Stock</b>.</div>
                    <?php elseif($_GET['msg']=='approved'): ?>
                        <div class="alert alert-warning"><i class="fas fa-file-signature"></i> Quotation marked as Approved (Pending Signatures).</div>
                    <?php elseif($_GET['msg']=='success'): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Sale Finalized and Stock Deducted!</div>
                    <?php elseif($_GET['msg']=='edited'): ?>
                        <div class="alert alert-info"><i class="fas fa-edit"></i> Quotation successfully updated.</div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-layer-group me-2"></i>Grouped Quotations</h5>
                    <input type="text" class="form-control form-control-sm w-25" placeholder="Search companies or items..." id="searchBox" onkeyup="filterAccordions(this)">
                </div>

                <div class="accordion" id="quotesAccordion">
                    <?php
                    $conn = getDBConnection();
                    $grouped = [];
                    $res = $conn->query("SELECT * FROM quotations ORDER BY company ASC, id DESC");
                    while($row = $res->fetch_assoc()) {
                        $grouped[$row['company']][] = $row;
                    }

                    $i = 0;
                    foreach($grouped as $company => $quotes):
                        $i++;
                        $pendingCount = 0;
                        foreach($quotes as $q) if($q['status'] != 'Converted') $pendingCount++;
                    ?>
                    <div class="accordion-item border-0 mb-3 shadow-sm rounded overflow-hidden company-group">
                        <h2 class="accordion-header" id="heading<?= $i ?>">
                            <button class="accordion-button <?= $i==1?'':'collapsed' ?> bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $i ?>">
                                <div>
                                    <i class="fas fa-building text-primary me-2"></i> <strong class="company-name"><?= htmlspecialchars($company) ?></strong>
                                    <span class="badge bg-secondary ms-2"><?= count($quotes) ?> Total Items</span>
                                    <?php if($pendingCount > 0): ?>
                                        <span class="badge bg-warning text-dark ms-1"><?= $pendingCount ?> Action Required</span>
                                    <?php endif; ?>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?= $i ?>" class="accordion-collapse collapse <?= $i==1?'show':'' ?>" data-bs-parent="#quotesAccordion">
                            <div class="accordion-body p-0 table-responsive bg-white">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light text-muted small uppercase">
                                        <tr>
                                            <th>Ref & Date</th>
                                            <th>Item Details</th>
                                            <th class="text-end">Qty</th>
                                            <th class="text-end">Unit / Total</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($quotes as $row): 
                                            $status = $row['status'] ?: 'Pending';
                                            $badge = 'secondary';
                                            if ($status == 'Approved') $badge = 'warning text-dark';
                                            if ($status == 'Converted') $badge = 'success';
                                            $totalAmt = $row['quantity_requested'] * $row['nam_unit_price'];
                                        ?>
                                        <tr class="item-row <?= $status=='Converted'?'opacity-50':'' ?>">
                                            <td>
                                                <small class="text-primary fw-bold d-block"><?= $row['quote_ref'] ?? 'N/A' ?></small>
                                                <small class="text-muted"><?= $row['date'] ?></small>
                                            </td>
                                            <td><strong class="item-name"><?= $row['item'] ?></strong></td>
                                            <td class="text-end"><?= $row['quantity_requested'] ?></td>
                                            <td class="text-end">
                                                <small class="text-muted d-block">₱<?= number_format($row['nam_unit_price'], 2) ?></small>
                                                <strong class="text-dark">₱<?= number_format($totalAmt, 2) ?></strong>
                                            </td>
                                            <td><span class="badge bg-<?= $badge ?>"><?= $status ?></span></td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end gap-1">
                                                    <?php if($status != 'Converted'): ?>
                                                        <button class="btn btn-sm btn-outline-primary" onclick='openEditModal(<?= json_encode($row) ?>)' title="Edit Details"><i class="fas fa-edit"></i></button>
                                                    <?php endif; ?>

                                                    <?php if($status == 'Pending'): ?>
                                                        <form method="POST" onsubmit="return confirm('Mark as Approved?');">
                                                            <input type="hidden" name="approve_id" value="<?= $row['id'] ?>">
                                                            <button class="btn btn-sm btn-warning fw-bold" title="Approve"><i class="fas fa-file-signature"></i> Approve</button>
                                                        </form>
                                                    <?php elseif($status == 'Approved'): ?>
                                                        <form method="POST" onsubmit="return confirm('Finalize to Sale? This will DEDUCT stock and record the sale.');">
                                                            <input type="hidden" name="convert_id" value="<?= $row['id'] ?>">
                                                            <button class="btn btn-sm btn-success fw-bold" title="Finalize to Sale"><i class="fas fa-check-double"></i> Finalize</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Edit Quotation Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body bg-light">
                        <input type="hidden" name="action" value="edit_quote">
                        <input type="hidden" name="edit_id" id="edit_id">
                        
                        <div class="mb-2">
                            <label class="small text-muted fw-bold">Item</label>
                            <input type="text" id="edit_item_display" class="form-control bg-light" readonly>
                        </div>
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="small text-muted fw-bold">Quantity</label>
                                <input type="number" name="edit_quantity" id="edit_quantity" class="form-control" required min="1">
                            </div>
                            <div class="col-6">
                                <label class="small text-muted fw-bold">Supplier Cost</label>
                                <input type="number" step="0.01" name="edit_s_price" id="edit_s_price" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="small text-primary fw-bold">Selling Price</label>
                                <input type="number" step="0.01" name="edit_n_price" id="edit_n_price" class="form-control border-primary" required>
                            </div>
                            <div class="col-6">
                                <label class="small text-muted fw-bold">Markup (%)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" id="edit_markup_pct" class="form-control">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="small text-muted fw-bold">Margin (%)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" id="edit_margin_pct" class="form-control">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-white">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Client Quotation Print</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <div id="receiptContent" class="preview-box shadow-sm"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-1"></i> Print</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let productMap = new Map();

    document.addEventListener("DOMContentLoaded", () => {
        const rand = Math.floor(1000 + Math.random() * 9000);
        const dateStr = new Date().toISOString().slice(0,10).replace(/-/g,'');
        document.getElementById('quote_ref').value = `QTE-${dateStr}-${rand}`;
        
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

        // Initialize Price Calculators for both forms
        attachPriceCalculators('s_price', 'n_price', 'markup_pct', 'margin_pct');
        attachPriceCalculators('edit_s_price', 'edit_n_price', 'edit_markup_pct', 'edit_margin_pct');
    });

    // Handle Item Selection Auto-fill
    document.getElementById('itemInput').addEventListener('input', function() {
        const p = productMap.get(this.value);
        if (p) {
            document.getElementById('s_price').value = p.supplier_price;
            document.getElementById('n_price').value = p.nam_price;
            document.getElementById('categoryField').value = p.category_code || 'General';
            // Trigger calculation so markup/margin auto-fills
            document.getElementById('n_price').dispatchEvent(new Event('input'));
        }
    });

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

    // --- EDIT MODAL LOGIC ---
    function openEditModal(row) {
        document.getElementById('edit_id').value = row.id;
        document.getElementById('edit_item_display').value = row.item;
        document.getElementById('edit_quantity').value = row.quantity_requested;
        document.getElementById('edit_s_price').value = parseFloat(row.suppliers_price).toFixed(2);
        document.getElementById('edit_n_price').value = parseFloat(row.nam_unit_price).toFixed(2);
        
        // Trigger calculation to fill markup/margin fields
        document.getElementById('edit_n_price').dispatchEvent(new Event('input'));
        
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    // --- PREVIEW LOGIC ---
    function showPreview() {
        const form = document.getElementById('quoteForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const d = {
            date: document.getElementById('date').value,
            ref: document.getElementById('quote_ref').value,
            client: document.getElementById('company').value,
            item: document.getElementById('itemInput').value,
            qty: document.getElementById('quantity').value,
            price: parseFloat(document.getElementById('n_price').value) || 0,
            po: document.getElementById('po').value,
            term: document.getElementById('term').value,
            remarks: document.getElementById('remarks').value
        };
        
        const optPo = document.getElementById('showPo').checked;
        const optTerms = document.getElementById('showTerms').checked;
        const optRemarks = document.getElementById('showRemarks').checked;
        
        const total = (d.qty * d.price).toFixed(2);
        
        const html = `
            <div class="receipt-header">
                <h4 class="fw-bold mb-0">NAM SUPPLY</h4>
                <small>Official Quotation</small>
            </div>
            <div class="mb-3 d-flex justify-content-between">
                <div>
                    <strong>To:</strong> ${d.client}<br>
                    <strong>Date:</strong> ${d.date}<br>
                    ${(d.po && optPo) ? `<strong>PO #:</strong> ${d.po}<br>` : ''}
                    ${(d.term && optTerms) ? `<strong>Terms:</strong> ${d.term}<br>` : ''}
                </div>
                <div class="text-end">
                    <strong class="text-primary">Ref:</strong> ${d.ref}
                </div>
            </div>
            <table class="table table-sm table-bordered mb-2">
                <thead class="table-light">
                    <tr><th>Item Description</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Amount</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>${d.item}</td>
                        <td class="text-end">${d.qty}</td>
                        <td class="text-end">₱${d.price.toFixed(2)}</td>
                        <td class="text-end fw-bold">₱${total}</td>
                    </tr>
                </tbody>
            </table>
            <div class="receipt-total fs-5 text-primary">
                TOTAL: ₱${total}
            </div>
            ${(d.remarks && optRemarks) ? `<div class="mt-3 small text-muted border-top pt-2"><strong>Notes:</strong><br>${d.remarks.replace(/\\n/g, '<br>')}</div>` : ''}
            
            <div class="mt-4 text-center small text-muted">
                This is a system generated quotation.<br>Valid for 30 days pending standard approvals.
            </div>
        `;
        document.getElementById('receiptContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('previewModal')).show();
    }
    
    // --- SEARCH ACCORDIONS ---
    function filterAccordions(input) {
        const filter = input.value.toLowerCase();
        const groups = document.querySelectorAll('.company-group');
        
        groups.forEach(group => {
            const compName = group.querySelector('.company-name').innerText.toLowerCase();
            const itemsText = group.querySelector('tbody').innerText.toLowerCase();
            
            if (compName.includes(filter) || itemsText.includes(filter)) {
                group.style.display = '';
            } else {
                group.style.display = 'none';
            }
        });
    }
    </script>
</body>
</html>