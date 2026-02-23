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

// --- 2. DELETE QUOTE ITEM ---
if (isset($_POST['delete_id'])) {
    $d_id = intval($_POST['delete_id']);
    $conn = getDBConnection();
    // Only allow deleting if not converted yet to maintain data integrity
    $conn->query("DELETE FROM quotations WHERE id = $d_id AND status != 'Converted'");
    header("Location: quotations.php?msg=deleted");
    exit;
}

// --- 3. FINALIZE TO SALE (CONVERT & DEDUCT STOCK WITH TRANSACTION) ---
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
            
            // --- START TRANSACTION ---
            $conn->begin_transaction();
            
            try {
                $income = ($q['nam_unit_price'] * $q['quantity_requested']) - ($q['suppliers_price'] * $q['quantity_requested']);
                $percent = ($q['nam_unit_price'] > 0) ? ($income / ($q['nam_unit_price'] * $q['quantity_requested'])) * 100 : 0;
                
                $stmt = $conn->prepare("INSERT INTO sales (date, company, category, item, quantity_requested, suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount, income, income_percent, po_number, payment_term, remarks, date_delivered, due_date) VALUES (CURRENT_DATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)");
                
                $total_actual = $q['suppliers_price'] * $q['quantity_requested'];
                $total_nam = $q['nam_unit_price'] * $q['quantity_requested'];
                
                $stmt->bind_param("sssidddddssss", $q['company'], $q['category'], $q['item'], $q['quantity_requested'], $q['suppliers_price'], $total_actual, $q['nam_unit_price'], $total_nam, $income, $percent, $q['po_number'], $q['payment_term'], $q['remarks']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert sale record.");
                }
                
                // Update stock safely
                $updateStock = $conn->prepare("UPDATE products SET current_stock = current_stock - ? WHERE name = ?");
                $updateStock->bind_param("is", $q['quantity_requested'], $q['item']);
                if (!$updateStock->execute()) {
                    throw new Exception("Failed to deduct inventory.");
                }

                // Mark Quote as Converted
                $conn->query("UPDATE quotations SET status = 'Converted' WHERE id = $q_id");
                
                // COMMIT IF ALL SUCCEEDED
                $conn->commit();
                
                // FIXED LINE BELOW: Added curly braces {} around array variables
                logAction('Converted Quotation', "Converted quote for {$q['company']} to a sale (Item: {$q['item']})");
                
                $msg = "success";
                
            } catch (Exception $e) {
                // ROLLBACK IF ANYTHING FAILED
                $conn->rollback();
                error_log($e->getMessage());
                $msg = "error_db";
            }
        } else {
            $msg = "error_stock"; 
        }
    }
    header("Location: quotations.php?msg=$msg");
    exit;
}

// --- 4. CREATE QUOTE ---
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

// --- 5. EDIT QUOTE ---
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
        .accordion-button:not(.collapsed) { background-color: #e0e7ff; color: #4338ca; font-weight: bold; }
        .total-display { font-size: 1.1rem; font-weight: bold; color: #0d6efd; background: #e9ecef; }
        
        /* Interactive inputs for formal print document */
        .print-input { border: none; border-bottom: 1px dashed #aaa; background: transparent; padding: 2px 5px; outline: none; transition: border 0.3s; }
        .print-input:focus { border-bottom: 1px solid #0d6efd; }
        .preview-box { border: 1px solid #dee2e6; background: #fff; padding: 0; box-shadow: 0 0 15px rgba(0,0,0,0.05); }

        /* Print Specific CSS */
        @media print {
            body > :not(#printContainer) { display: none !important; }
            #printContainer { display: block !important; position: absolute; top: 0; left: 0; width: 100%; margin: 0; padding: 0; }
            .print-input { border-bottom: none !important; }
            .print-input::-webkit-input-placeholder { color: transparent; }
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container-fluid mt-4 pb-5">
        <div class="row g-4">
            
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
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
                                            <div class="col-12 mt-2 pt-2 border-top">
                                                <label class="small text-muted fw-bold">Total Quote Amount</label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-primary text-white border-primary">₱</span>
                                                    <input type="text" id="total_display" class="form-control total-display" readonly value="0.00">
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

                                <div class="col-6 mt-4">
                                    <button type="button" class="btn btn-outline-dark w-100 fw-bold" onclick="showPreviewNew()">
                                        <i class="fas fa-search me-1"></i> Preview Item
                                    </button>
                                </div>
                                <div class="col-6 mt-4">
                                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                                        <i class="fas fa-save me-1"></i> Save to List
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
                    <?php elseif($_GET['msg']=='error_db'): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> System Error: Failed to process transaction.</div>
                    <?php elseif($_GET['msg']=='approved'): ?>
                        <div class="alert alert-warning"><i class="fas fa-file-signature"></i> Quotation marked as Approved (Pending Signatures).</div>
                    <?php elseif($_GET['msg']=='success'): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Sale Finalized and Stock Deducted!</div>
                    <?php elseif($_GET['msg']=='edited'): ?>
                        <div class="alert alert-info"><i class="fas fa-edit"></i> Quotation successfully updated.</div>
                    <?php elseif($_GET['msg']=='deleted'): ?>
                        <div class="alert alert-danger"><i class="fas fa-trash-alt"></i> Item removed from list.</div>
                    <?php elseif($_GET['msg']=='created'): ?>
                        <div class="alert alert-success"><i class="fas fa-check"></i> Quotation Created.</div>
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
                    // SMART GROUPING: Sort by Company, then newest Date first!
                    $res = $conn->query("SELECT * FROM quotations ORDER BY company ASC, date DESC, id DESC");
                    
                    while($row = $res->fetch_assoc()) {
                        // Group first by Company, then sub-group by Quote Reference
                        $ref = $row['quote_ref'] ? $row['quote_ref'] : 'Unknown Ref';
                        $grouped[$row['company']][$ref][] = $row;
                    }

                    $i = 0;
                    foreach($grouped as $company => $refs):
                        $i++;
                        
                        $totalItems = 0;
                        $pendingCount = 0;
                        foreach($refs as $ref => $quotes) {
                            foreach($quotes as $q) {
                                $totalItems++;
                                if($q['status'] != 'Converted') $pendingCount++;
                            }
                        }
                    ?>
                    <div class="accordion-item border-0 mb-3 shadow-sm rounded overflow-hidden company-group">
                        <h2 class="accordion-header" id="heading<?= $i ?>">
                            <button class="accordion-button <?= $i==1?'':'collapsed' ?> bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $i ?>" aria-expanded="<?= $i==1?'true':'false' ?>" aria-controls="collapse<?= $i ?>">
                                <div>
                                    <i class="fas fa-building text-primary me-2"></i> <strong class="company-name"><?= htmlspecialchars($company) ?></strong>
                                    <span class="badge bg-secondary ms-2"><?= $totalItems ?> Items</span>
                                    <?php if($pendingCount > 0): ?>
                                        <span class="badge bg-warning text-dark ms-1"><?= $pendingCount ?> Action Required</span>
                                    <?php endif; ?>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?= $i ?>" class="accordion-collapse collapse <?= $i==1?'show':'' ?>" data-bs-parent="#quotesAccordion">
                            <div class="accordion-body p-0 bg-light">
                                
                                <?php foreach($refs as $ref => $quotes): 
                                    $quoteDate = date('F d, Y', strtotime($quotes[0]['date'])); // Format the date nicely
                                ?>
                                <div class="p-3 border-bottom bg-white">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="text-primary fw-bold mb-0">
                                            <i class="fas fa-file-invoice me-1"></i> Ref: <?= $ref ?>
                                            <button class="btn btn-sm btn-outline-dark ms-3 shadow-sm fw-bold" 
                                                    onclick='printGroupedQuote(<?= htmlspecialchars(json_encode($quotes), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($company), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($ref), ENT_QUOTES, "UTF-8") ?>)' 
                                                    title="Print Formal Document">
                                                <i class="fas fa-print me-1"></i> Print Formal Quote
                                            </button>
                                        </h6>
                                        <span class="text-muted small fw-bold"><i class="far fa-calendar-alt me-1"></i> <?= $quoteDate ?></span>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm mb-0 align-middle">
                                            <thead class="table-light text-muted small uppercase">
                                                <tr>
                                                    <th width="35%">Item Details</th>
                                                    <th class="text-end" width="10%">Qty</th>
                                                    <th class="text-end" width="20%">Unit / Total</th>
                                                    <th width="15%">Status</th>
                                                    <th class="text-end" width="20%">Actions</th>
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
                                                <tr class="item-row <?= $status=='Converted'?'opacity-50 bg-light':'' ?>">
                                                    <td><strong class="item-name text-dark"><?= $row['item'] ?></strong></td>
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
                                                                
                                                                <form method="POST" onsubmit="return confirm('Are you sure you want to remove this item?');">
                                                                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                                                    <button class="btn btn-sm btn-outline-danger" title="Remove Item"><i class="fas fa-trash-alt"></i></button>
                                                                </form>
                                                            <?php endif; ?>

                                                            <?php if($status == 'Pending'): ?>
                                                                <form method="POST" onsubmit="return confirm('Mark as Approved?');">
                                                                    <input type="hidden" name="approve_id" value="<?= $row['id'] ?>">
                                                                    <button class="btn btn-sm btn-warning fw-bold" title="Approve"><i class="fas fa-file-signature"></i></button>
                                                                </form>
                                                            <?php elseif($status == 'Approved'): ?>
                                                                <form method="POST" onsubmit="return confirm('Finalize to Sale? This will DEDUCT stock and record the sale.');">
                                                                    <input type="hidden" name="convert_id" value="<?= $row['id'] ?>">
                                                                    <button class="btn btn-sm btn-success fw-bold" title="Finalize to Sale"><i class="fas fa-check-double"></i> Convert</button>
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
                                <?php endforeach; ?>
                                
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
                            <div class="col-12 mt-2 pt-2 border-top">
                                <label class="small text-muted fw-bold">New Total Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white border-primary">₱</span>
                                    <input type="text" id="edit_total_display" class="form-control total-display" readonly value="0.00">
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-print me-2"></i>Formal Document Preview</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-secondary bg-opacity-10 p-4">
                    <p class="text-center text-muted small mb-2"><i class="fas fa-info-circle me-1"></i> You can click the names at the bottom to edit them before printing.</p>
                    <div id="receiptContent" class="preview-box p-5 mx-auto" style="max-width: 800px; min-height: 1000px;"></div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary px-4 fw-bold" onclick="executePrint()"><i class="fas fa-print me-2"></i> Print Document</button>
                </div>
            </div>
        </div>
    </div>

    <div id="printContainer" class="d-none d-print-block"></div>

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
        attachPriceCalculators('s_price', 'n_price', 'markup_pct', 'margin_pct', 'quantity', 'total_display');
        attachPriceCalculators('edit_s_price', 'edit_n_price', 'edit_markup_pct', 'edit_margin_pct', 'edit_quantity', 'edit_total_display');
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

    // --- PRICING CALCULATOR LOGIC (Enhanced) ---
    function attachPriceCalculators(sPriceId, nPriceId, markupId, marginId, qtyId, totalId) {
        const sPrice = document.getElementById(sPriceId);
        const nPrice = document.getElementById(nPriceId);
        const markup = document.getElementById(markupId);
        const margin = document.getElementById(marginId);
        const qty = document.getElementById(qtyId);
        const total = document.getElementById(totalId);

        function updateTotals() {
            let n = parseFloat(nPrice.value) || 0;
            let q = parseFloat(qty.value) || 0;
            if(total) total.value = (n * q).toFixed(2);
        }

        function calcFromPrice() {
            let s = parseFloat(sPrice.value) || 0;
            let n = parseFloat(nPrice.value) || 0;
            if (s > 0 && n > 0) {
                markup.value = (((n - s) / s) * 100).toFixed(2);
                margin.value = (((n - s) / n) * 100).toFixed(2);
            } else {
                markup.value = ''; margin.value = '';
            }
            updateTotals();
        }

        function calcFromMarkup() {
            let s = parseFloat(sPrice.value) || 0;
            let mk = parseFloat(markup.value) || 0;
            if (s > 0) {
                let n = s * (1 + (mk / 100));
                nPrice.value = n.toFixed(2);
                margin.value = (((n - s) / n) * 100).toFixed(2);
                updateTotals();
            }
        }

        function calcFromMargin() {
            let s = parseFloat(sPrice.value) || 0;
            let mg = parseFloat(margin.value) || 0;
            if (s > 0 && mg < 100) {
                let n = s / (1 - (mg / 100));
                nPrice.value = n.toFixed(2);
                markup.value = (((n - s) / s) * 100).toFixed(2);
                updateTotals();
            }
        }

        if(sPrice) sPrice.addEventListener('input', calcFromPrice);
        if(nPrice) nPrice.addEventListener('input', calcFromPrice);
        if(markup) markup.addEventListener('input', calcFromMarkup);
        if(margin) margin.addEventListener('input', calcFromMargin);
        if(qty) qty.addEventListener('input', updateTotals);
    }

    // --- EDIT MODAL LOGIC ---
    function openEditModal(row) {
        document.getElementById('edit_id').value = row.id;
        document.getElementById('edit_item_display').value = row.item;
        document.getElementById('edit_quantity').value = row.quantity_requested;
        document.getElementById('edit_s_price').value = parseFloat(row.suppliers_price).toFixed(2);
        document.getElementById('edit_n_price').value = parseFloat(row.nam_unit_price).toFixed(2);
        
        // Trigger calculation to fill markup/margin fields AND total
        document.getElementById('edit_n_price').dispatchEvent(new Event('input'));
        
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    // --- FORMAL DOCUMENT PRINT RENDERING ---
    function renderFormalPrint(date, ref, client, tbodyHtml, grandTotal, po, term, remarks) {
        const html = `
            <div id="printArea">
                <div class="text-center mb-5 pb-3 border-bottom border-dark border-2">
                    <h2 class="fw-bold mb-0" style="letter-spacing: 2px;">NAM SUPPLY</h2>
                    <p class="mb-0 text-muted">Business Address / Contact Details Here</p>
                    <h4 class="mt-4 fw-bold text-uppercase">Formal Quotation</h4>
                </div>

                <div class="row mb-4">
                    <div class="col-8">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><th width="100" class="text-muted">To:</th><td><input type="text" class="print-input w-100 fw-bold fs-6" value="${client}"></td></tr>
                            <tr><th class="text-muted">Date:</th><td class="fw-bold">${date}</td></tr>
                            ${po ? `<tr><th class="text-muted">PO #:</th><td>${po}</td></tr>` : ''}
                            ${term ? `<tr><th class="text-muted">Terms:</th><td>${term}</td></tr>` : ''}
                        </table>
                    </div>
                    <div class="col-4 text-end">
                        <table class="table table-sm table-borderless mb-0 text-end">
                            <tr><th class="text-muted">Quote Ref:</th><td class="fw-bold">${ref}</td></tr>
                        </table>
                    </div>
                </div>

                <table class="table table-bordered border-dark mb-4">
                    <thead class="table-light border-dark text-center">
                        <tr>
                            <th class="py-2 text-uppercase">Description</th>
                            <th width="12%" class="py-2 text-uppercase">Qty</th>
                            <th width="20%" class="py-2 text-uppercase">Unit Price</th>
                            <th width="25%" class="py-2 text-uppercase">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="border-dark">
                        ${tbodyHtml}
                    </tbody>
                    <tfoot class="border-dark">
                        <tr>
                            <th colspan="3" class="text-end py-3">GRAND TOTAL:</th>
                            <th class="text-end py-3 fs-5 text-dark fw-bold">₱${parseFloat(grandTotal).toLocaleString('en-US', {minimumFractionDigits: 2})}</th>
                        </tr>
                    </tfoot>
                </table>

                ${remarks ? `<div class="mt-4 mb-5 p-3 border border-dark rounded bg-light"><strong class="d-block mb-2 text-uppercase text-muted small">Remarks / Notes:</strong>${remarks.replace(/\\n/g, '<br>')}</div>` : '<div class="mb-5 pb-5"></div>'}

                <div class="row mt-5 pt-5">
                    <div class="col-5">
                        <p class="mb-5 text-muted small text-uppercase">Prepared By:</p>
                        <input type="text" class="print-input text-center w-100 fw-bold fs-6 mb-1" value="NAM Supply Representative">
                        <div class="border-top border-dark text-center small pt-1 text-muted">Signature over printed name</div>
                    </div>
                    <div class="col-2"></div>
                    <div class="col-5">
                        <p class="mb-5 text-muted small text-uppercase">Conforme:</p>
                        <input type="text" class="print-input text-center w-100 fw-bold fs-6 mb-1" placeholder="Type Client Name Here">
                        <div class="border-top border-dark text-center small pt-1 text-muted">Signature over printed name</div>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('receiptContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('previewModal')).show();
    }

    // Preview Single Item from Form
    function showPreviewNew() {
        const form = document.getElementById('quoteForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const date = document.getElementById('date').value;
        const ref = document.getElementById('quote_ref').value;
        const client = document.getElementById('company').value;
        const item = document.getElementById('itemInput').value;
        const qty = document.getElementById('quantity').value;
        const price = parseFloat(document.getElementById('n_price').value) || 0;
        const po = document.getElementById('po').value;
        const term = document.getElementById('term').value;
        const remarks = document.getElementById('remarks').value;
        
        const total = (qty * price);
        
        const tbodyHtml = `
            <tr>
                <td class="py-2">${item}</td>
                <td class="text-center py-2">${qty}</td>
                <td class="text-end py-2">₱${price.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                <td class="text-end fw-bold py-2">₱${total.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
            </tr>
        `;

        renderFormalPrint(date, ref, client, tbodyHtml, total, po, term, remarks);
    }

    // Print Multiple Items from Group
    function printGroupedQuote(quotes, company, ref) {
        let tbody = '';
        let grandTotal = 0;
        let date = quotes[0].date;
        let po = quotes[0].po_number || '';
        let term = quotes[0].payment_term || '';
        let remarks = quotes[0].remarks || '';

        quotes.forEach(q => {
            let total = q.quantity_requested * q.nam_unit_price;
            grandTotal += total;
            tbody += `
                <tr>
                    <td class="py-2">${q.item}</td>
                    <td class="text-center py-2">${q.quantity_requested}</td>
                    <td class="text-end py-2">₱${parseFloat(q.nam_unit_price).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="text-end fw-bold py-2">₱${total.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                </tr>
            `;
        });

        renderFormalPrint(date, ref, company, tbody, grandTotal, po, term, remarks);
    }

    // Execute Print Logic (Isolates print area to prevent modal cropping bugs)
    function executePrint() {
        // Copy the content from the modal box to the hidden print container attached to the body
        const content = document.getElementById('printArea').outerHTML;
        document.getElementById('printContainer').innerHTML = content;
        
        // Trigger browser print
        window.print();
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