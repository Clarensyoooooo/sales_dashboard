<?php 
require_once 'config.php'; 
requireLogin(); 

// --- AUTO-GENERATE NEXT REFERENCE NUMBER (YYYY-XXX) ---
function getNextQuoteRef($conn) {
    $yr = date('Y');
    // Find the last reference starting with current year
    $res = $conn->query("SELECT quote_ref FROM quotations WHERE quote_ref LIKE '$yr-%' ORDER BY id DESC LIMIT 1");
    $last = $res->fetch_assoc();
    $num = 1;
    if ($last) {
        $parts = explode('-', $last['quote_ref']);
        if (isset($parts[1]) && is_numeric($parts[1])) {
            $num = intval($parts[1]) + 1;
        }
    }
    return $yr . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
}

// --- 0. BATCH QUOTE CREATION (JSON API ENDPOINT) ---
$input = json_decode(file_get_contents('php://input'), true);
if ($input && isset($input['action']) && $input['action'] == 'create_quote_batch') {
    $conn = getDBConnection();
    $conn->begin_transaction();
    try {
        // Allows form.php to send "Reserved" status
        $status = isset($input['status']) ? $input['status'] : 'Pending'; 
        $stmt = $conn->prepare("INSERT INTO quotations (date, quote_ref, company, category, item, quantity_requested, suppliers_price, nam_unit_price, total_amount, po_number, payment_term, remarks, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $header = $input['header'];
        $items = $input['items'];
        
        foreach($items as $item) {
            $total = $item['quantity'] * $item['n_price'];
            $cat = !empty($item['category']) ? $item['category'] : 'Uncategorized';
            $stmt->bind_param("sssssidddssss", 
                $header['date'], $header['quote_ref'], $header['company'], 
                $cat, $item['item'], $item['quantity'], 
                $item['s_price'], $item['n_price'], $total, 
                $header['po'], $header['term'], $header['remarks'],
                $status
            );
            $stmt->execute();
        }
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit; // Stop execution after handling the JSON POST
}

// --- 1. APPROVE QUOTE & DEDUCT STOCK ---
if (isset($_POST['approve_id'])) {
    $q_id = intval($_POST['approve_id']);
    $conn = getDBConnection();

    $q = $conn->query("SELECT * FROM quotations WHERE id = $q_id")->fetch_assoc();
    
    if ($q && $q['status'] != 'Approved' && $q['status'] != 'Converted') {
        // Check Stock First
        $check = $conn->prepare("SELECT current_stock FROM products WHERE name = ?");
        $check->bind_param("s", $q['item']);
        $check->execute();
        $stock = $check->get_result()->fetch_assoc();
        
        if ($stock && $stock['current_stock'] >= $q['quantity_requested']) {
            $conn->begin_transaction();
            try {
                // Deduct Stock
                $upd = $conn->prepare("UPDATE products SET current_stock = current_stock - ? WHERE name = ?");
                $upd->bind_param("is", $q['quantity_requested'], $q['item']);
                $upd->execute();
                
                // Update Status
                $conn->query("UPDATE quotations SET status = 'Approved' WHERE id = $q_id");
                
                $conn->commit();
                $msg = "approved";
            } catch (Exception $e) {
                $conn->rollback();
                $msg = "error_db";
            }
        } else {
            $msg = "error_stock";
        }
    } else {
        $msg = "already_approved";
    }
    header("Location: quotations.php?msg=$msg");
    exit;
}

// --- 2. DELETE QUOTE ITEM (AND RESTORE STOCK IF APPROVED) ---
if (isset($_POST['delete_id'])) {
    $d_id = intval($_POST['delete_id']);
    $conn = getDBConnection();
    
    $q = $conn->query("SELECT * FROM quotations WHERE id = $d_id")->fetch_assoc();
    if ($q) {
        // Only allow deleting if not converted yet to maintain data integrity
        if ($q['status'] != 'Converted') {
            // If it was already approved, return the stock to inventory before deleting
            if ($q['status'] == 'Approved') {
                $conn->query("UPDATE products SET current_stock = current_stock + {$q['quantity_requested']} WHERE name = '{$conn->real_escape_string($q['item'])}'");
            }
            $conn->query("DELETE FROM quotations WHERE id = $d_id");
        }
    }
    header("Location: quotations.php?msg=deleted");
    exit;
}

// --- 3. FINALIZE TO SALE (CONVERT) ---
if (isset($_POST['convert_id'])) {
    $q_id = intval($_POST['convert_id']);
    $conn = getDBConnection();
    
    $q = $conn->query("SELECT * FROM quotations WHERE id = $q_id")->fetch_assoc();
    
    if ($q) {
        // --- START TRANSACTION ---
        $conn->begin_transaction();
        
        try {
            // 1. Insert into Sales
            $income = ($q['nam_unit_price'] * $q['quantity_requested']) - ($q['suppliers_price'] * $q['quantity_requested']);
            $percent = ($q['nam_unit_price'] > 0) ? ($income / ($q['nam_unit_price'] * $q['quantity_requested'])) * 100 : 0;
            
            $stmt = $conn->prepare("INSERT INTO sales (date, company, category, item, quantity_requested, suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount, income, income_percent, po_number, payment_term, remarks, date_delivered, due_date) VALUES (CURRENT_DATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)");
            
            $total_actual = $q['suppliers_price'] * $q['quantity_requested'];
            $total_nam = $q['nam_unit_price'] * $q['quantity_requested'];
            
            $stmt->bind_param("sssidddddssss", $q['company'], $q['category'], $q['item'], $q['quantity_requested'], $q['suppliers_price'], $total_actual, $q['nam_unit_price'], $total_nam, $income, $percent, $q['po_number'], $q['payment_term'], $q['remarks']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert sale record.");
            }
            
            // 2. Deduct Stock ONLY if it wasn't already deducted via 'Approve'
            if ($q['status'] != 'Approved') {
                $check = $conn->prepare("SELECT current_stock FROM products WHERE name = ?");
                $check->bind_param("s", $q['item']);
                $check->execute();
                $stock = $check->get_result()->fetch_assoc();
                
                if (!$stock || $stock['current_stock'] < $q['quantity_requested']) {
                    throw new Exception("Insufficient Stock");
                }

                $updateStock = $conn->prepare("UPDATE products SET current_stock = current_stock - ? WHERE name = ?");
                $updateStock->bind_param("is", $q['quantity_requested'], $q['item']);
                if (!$updateStock->execute()) {
                    throw new Exception("Failed to deduct inventory.");
                }
            }

            // 3. Mark Quote as Converted
            $conn->query("UPDATE quotations SET status = 'Converted' WHERE id = $q_id");
            
            // COMMIT IF ALL SUCCEEDED
            $conn->commit();
            
            logAction('Converted Quotation', "Converted quote for {$q['company']} to a sale (Item: {$q['item']})");
            
            $msg = "success";
            
        } catch (Exception $e) {
            // ROLLBACK IF ANYTHING FAILED
            $conn->rollback();
            error_log($e->getMessage());
            $msg = ($e->getMessage() == "Insufficient Stock") ? "error_stock" : "error_db";
        }
    }
    header("Location: quotations.php?msg=$msg");
    exit;
}

// --- 4. TOGGLE RESERVE STATUS ---
if (isset($_POST['toggle_reserve_id'])) {
    $r_id = intval($_POST['toggle_reserve_id']);
    $conn = getDBConnection();
    
    $q = $conn->query("SELECT status FROM quotations WHERE id = $r_id")->fetch_assoc();
    if ($q && ($q['status'] == 'Pending' || $q['status'] == 'Reserved')) {
        $new_status = ($q['status'] == 'Reserved') ? 'Pending' : 'Reserved';
        $conn->query("UPDATE quotations SET status = '$new_status' WHERE id = $r_id");
    }
    header("Location: quotations.php?msg=reserved_toggled");
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
    
    // NOTE: Editing an "Approved" quote's quantity would require stock adjustments.
    // For simplicity and safety, we allow standard updates. If major edits are needed, delete and recreate.
    $stmt = $conn->prepare("UPDATE quotations SET quantity_requested=?, suppliers_price=?, nam_unit_price=?, total_amount=? WHERE id=?");
    $stmt->bind_param("idddi", $qty, $s_price, $n_price, $total, $id);
    $stmt->execute();
    header("Location: quotations.php?msg=edited");
    exit;
}

$conn = getDBConnection();
$next_ref_default = getNextQuoteRef($conn);

// --- PRE-FILL COMPANY DATA ---
$clientData = [];

// 1. Fetch from past quotations (to get previous quote remarks/po/terms)
$resQuotes = $conn->query("SELECT company, po_number, payment_term, remarks FROM quotations WHERE company IS NOT NULL AND company != '' ORDER BY date DESC, id DESC");
if ($resQuotes) {
    while($row = $resQuotes->fetch_assoc()) {
        $comp = trim($row['company']);
        if (!isset($clientData[$comp])) {
            $clientData[$comp] = [
                'po' => trim($row['po_number'] ?? ''),
                'term' => trim($row['payment_term'] ?? ''),
                'remarks' => trim($row['remarks'] ?? '')
            ];
        }
    }
}

// 2. Fetch from past sales (to expand the list of known companies and their standard terms)
$resSales = $conn->query("SELECT company, payment_term FROM sales WHERE company IS NOT NULL AND company != '' ORDER BY date DESC, id DESC");
if ($resSales) {
    while($row = $resSales->fetch_assoc()) {
        $comp = trim($row['company']);
        if (!isset($clientData[$comp])) {
            $clientData[$comp] = [
                'po' => '',
                'term' => trim($row['payment_term'] ?? ''),
                'remarks' => ''
            ];
        }
    }
}
ksort($clientData); // Alphabetize the client list


// --- FETCH RESERVED QUANTITIES PER ITEM ---
$reservedData = [];
$resReserved = $conn->query("SELECT item, SUM(quantity_requested) as total_reserved FROM quotations WHERE status = 'Reserved' GROUP BY item");
if ($resReserved) {
    while ($r = $resReserved->fetch_assoc()) {
        $reservedData[$r['item']] = (int)$r['total_reserved'];
    }
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

        /* Highlight editable inline inputs in preview (Turns black when printed) */
        .inline-edit { color: #0d6efd; cursor: pointer; }
        .inline-edit:focus { color: #000; background-color: #f8f9fa; border-bottom: 1px solid #0d6efd !important; }

        /* Print Specific CSS */
        @media print {
            body > :not(#printContainer) { display: none !important; }
            #printContainer { display: block !important; position: absolute; top: 0; left: 0; width: 100%; margin: 0; padding: 0; }
            .print-input { border-bottom: none !important; color: #000 !important; }
            .inline-edit { color: #000 !important; } 
            .print-input::-webkit-input-placeholder { color: transparent; }
            /* Hide the spinner arrows on number inputs during print */
            input[type=number]::-webkit-inner-spin-button, 
            input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            @page { size: A4 portrait; margin: 15mm; }
        }
        
        .formal-text { font-family: "Times New Roman", Times, serif; }
        .formal-sans { font-family: Arial, Helvetica, sans-serif; }
        
        .form-section-header { 
            font-size: 0.85rem; font-weight: 700; text-transform: uppercase; 
            letter-spacing: 0.5px; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container-fluid mt-4 pb-5">
        <div class="row g-4">
            
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-primary text-white fw-bold"><i class="fas fa-file-invoice me-2"></i>New Quotation Encoder</div>
                    <div class="card-body bg-light">
                        <form id="quoteForm" onsubmit="event.preventDefault(); addToQuote();">
                            <input type="hidden" id="categoryField">
                            
                            <div class="mb-4">
                                <div class="form-section-header text-primary border-primary">1. Quotation Details</div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="small text-muted fw-bold">Date</label>
                                        <input type="date" id="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="small text-muted fw-bold">Quote Reference</label>
                                        <input type="text" id="quote_ref" class="form-control form-control-sm" value="<?= $next_ref_default ?>" required>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label class="small text-muted fw-bold">Client Company</label>
                                        <input type="text" id="company" class="form-control form-control-sm" placeholder="Search Client..." list="companyList" required autocomplete="off">
                                        <datalist id="companyList">
                                            <?php foreach(array_keys($clientData) as $comp): ?>
                                                <option value="<?= htmlspecialchars($comp); ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>

                                    <div class="col-6">
                                        <label class="small text-muted fw-bold">Client Inquiry Num</label>
                                        <input type="text" id="po" class="form-control form-control-sm" placeholder="Inquiry #">
                                    </div>
                                    <div class="col-6">
                                        <label class="small text-muted fw-bold">Payment Terms</label>
                                        <input type="text" id="term" class="form-control form-control-sm" placeholder="e.g. 30 Days">
                                    </div>
                                    <div class="col-12">
                                        <textarea id="remarks" class="form-control form-control-sm" placeholder="Remarks / Notes (Applies to entire quote)"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="form-section-header text-success border-success">2. Add Item</div>
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="small text-muted fw-bold">Item Description</label>
                                        <input type="text" id="itemInput" list="productList" class="form-control form-control-sm" placeholder="Search Item..." autocomplete="off">
                                        <datalist id="productList"></datalist>
                                    </div>
                                    <div class="col-12">
                                        <label class="small text-muted fw-bold">Quantity</label>
                                        <input type="number" id="quantity" class="form-control form-control-sm" placeholder="Qty" value="1" min="1">
                                        
                                        <div id="stockTracker" class="mt-2 p-2 bg-white border rounded border-info-subtle" style="display: none; font-size: 0.75rem;">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-muted">On-Hand Stock:</span>
                                                <span class="fw-bold text-dark" id="infoStock">0</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-danger">Currently Reserved:</span>
                                                <span class="fw-bold text-danger" id="infoReserved">0</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-1 border-bottom pb-1">
                                                <span class="text-success">True Available:</span>
                                                <span class="fw-bold text-success" id="infoAvailable">0</span>
                                            </div>
                                            <div class="d-flex justify-content-between mt-1">
                                                <span class="text-primary fw-bold">Left After This Quote:</span>
                                                <span class="fw-bold text-primary" id="infoLeft">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12 mt-3">
                                        <div class="border rounded p-2 bg-white border-secondary-subtle">
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <label class="small text-muted fw-bold">Supplier Cost</label>
                                                    <input type="number" step="0.01" id="s_price" class="form-control form-control-sm" placeholder="Cost">
                                                </div>
                                                <div class="col-6">
                                                    <label class="small text-primary fw-bold">Selling Price</label>
                                                    <input type="number" step="0.01" id="n_price" class="form-control form-control-sm border-primary" placeholder="Final Price">
                                                </div>
                                                <div class="col-6">
                                                    <label class="small text-muted fw-bold">Markup (%)</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" step="0.01" id="markup_pct" class="form-control">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <label class="small text-muted fw-bold">Margin (%)</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" step="0.01" id="margin_pct" class="form-control">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 mt-2 pt-2 border-top">
                                                    <label class="small text-muted fw-bold">Item Total</label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-primary text-white border-primary">₱</span>
                                                        <input type="text" id="total_display" class="form-control fw-bold text-primary bg-light" readonly value="0.00">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 mt-3">
                                        <button type="submit" class="btn btn-success w-100 fw-bold shadow-sm">
                                            <i class="fas fa-plus-circle me-1"></i> Add to Quote
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                
                <?php if(isset($_GET['msg'])): ?>
                    <?php if($_GET['msg']=='error_stock'): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Cannot Finalize/Approve: <b>Insufficient Stock</b>.</div>
                    <?php elseif($_GET['msg']=='error_db'): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> System Error: Failed to process transaction.</div>
                    <?php elseif($_GET['msg']=='approved'): ?>
                        <div class="alert alert-warning"><i class="fas fa-file-signature"></i> Quotation Approved & Stock Deducted (Pending Signatures).</div>
                    <?php elseif($_GET['msg']=='success'): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Sale Finalized Successfully!</div>
                    <?php elseif($_GET['msg']=='edited'): ?>
                        <div class="alert alert-info"><i class="fas fa-edit"></i> Quotation successfully updated.</div>
                    <?php elseif($_GET['msg']=='deleted'): ?>
                        <div class="alert alert-danger"><i class="fas fa-trash-alt"></i> Item removed from list (and stock restored if approved).</div>
                    <?php elseif($_GET['msg']=='created'): ?>
                        <div class="alert alert-success"><i class="fas fa-check"></i> Quotation Created Successfully.</div>
                    <?php elseif($_GET['msg']=='reserved_toggled'): ?>
                        <div class="alert alert-info"><i class="fas fa-bookmark"></i> Item reservation status successfully updated.</div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="card shadow-sm border-0 mb-4" id="queueCard" style="display: none;">
                    <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-shopping-cart me-2"></i>Current Quote Draft</div>
                        <span class="badge bg-dark rounded-pill"><span id="queueCount">0</span> Items</span>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover table-sm mb-0 align-middle">
                            <thead class="table-light text-muted small uppercase">
                                <tr>
                                    <th class="ps-3">Item Description</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Total Amount</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody id="queueBody"></tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-light d-flex justify-content-end gap-2 p-3">
                        <button class="btn btn-outline-danger fw-bold px-3" onclick="clearQueue()" title="Clear Draft">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <button class="btn btn-outline-dark fw-bold px-4" onclick="showPreviewNew()">
                            <i class="fas fa-search me-1"></i> Preview Formal Document
                        </button>
                        <button class="btn btn-danger fw-bold px-4" onclick="saveQuoteBatch('Reserved')">
                            <i class="fas fa-bookmark me-1"></i> Save & Reserve
                        </button>
                        <button class="btn btn-primary fw-bold px-4" onclick="saveQuoteBatch('Pending')">
                            <i class="fas fa-save me-1"></i> Save Draft
                        </button>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-end mb-3 mt-2">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-layer-group me-2"></i>Grouped Quotations</h5>
                    <input type="text" class="form-control form-control-sm w-25 shadow-sm" placeholder="Search companies or items..." onkeyup="filterAccordions(this)">
                </div>

                <div class="accordion shadow-sm" id="quotesAccordion">
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
                    <div class="accordion-item border-0 border-bottom overflow-hidden company-group">
                        <h2 class="accordion-header" id="heading<?= $i ?>">
                            <button class="accordion-button <?= $i==1?'':'collapsed' ?> bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $i ?>">
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
                                <div class="p-3 border-bottom bg-white shadow-sm mb-2 rounded mx-2 mt-2">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="text-primary fw-bold mb-0">
                                            <i class="fas fa-file-invoice me-1"></i> Ref: <?= $ref ?>
                                            
                                            <button class="btn btn-sm btn-outline-info ms-3 shadow-sm fw-bold" 
                                                    onclick='batchBuyAgain(<?= htmlspecialchars(json_encode($quotes), ENT_QUOTES, "UTF-8") ?>)' 
                                                    title="Duplicate Entire Quotation to Draft">
                                                <i class="fas fa-redo-alt me-1"></i> Batch Buy Again
                                            </button>

                                            <button class="btn btn-sm btn-outline-dark ms-2 shadow-sm fw-bold" 
                                                    onclick='printGroupedQuote(<?= htmlspecialchars(json_encode($quotes), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($company), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($ref), ENT_QUOTES, "UTF-8") ?>)' 
                                                    title="Print Formal Document">
                                                <i class="fas fa-print me-1"></i> Print Formal Quote
                                            </button>
                                        </h6>
                                        <div class="text-end">
                                            <span class="text-muted small fw-bold"><i class="far fa-calendar-alt me-1"></i> <?= $quoteDate ?></span>
                                            <span class="badge border bg-light text-dark ms-2">Inquiry #: <?= $quotes[0]['po_number'] ?? 'N/A' ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm mb-0 align-middle">
                                            <thead class="table-light text-muted small uppercase border-top border-bottom">
                                                <tr>
                                                    <th width="35%" class="ps-2">Item Details</th>
                                                    <th class="text-center" width="10%">Qty</th>
                                                    <th class="text-end" width="20%">Unit / Total</th>
                                                    <th width="15%" class="text-center">Status</th>
                                                    <th class="text-end pe-2" width="20%">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($quotes as $row): 
                                                    $status = $row['status'] ?: 'Pending';
                                                    $badge = 'secondary';
                                                    if ($status == 'Approved') $badge = 'warning text-dark';
                                                    if ($status == 'Reserved') $badge = 'danger';
                                                    if ($status == 'Converted') $badge = 'success';
                                                    $totalAmt = $row['quantity_requested'] * $row['nam_unit_price'];
                                                ?>
                                                <tr class="item-row <?= $status=='Converted'?'opacity-50 bg-light':'' ?>">
                                                    <td class="ps-2"><strong class="item-name text-dark"><?= $row['item'] ?></strong></td>
                                                    <td class="text-center"><?= $row['quantity_requested'] ?></td>
                                                    <td class="text-end">
                                                        <small class="text-muted d-block">₱<?= number_format($row['nam_unit_price'], 2) ?></small>
                                                        <strong class="text-dark">₱<?= number_format($totalAmt, 2) ?></strong>
                                                    </td>
                                                    <td class="text-center"><span class="badge bg-<?= $badge ?>"><?= $status ?></span></td>
                                                    <td class="text-end pe-2">
                                                        <div class="d-flex justify-content-end gap-1">
                                                            
                                                            <button class="btn btn-sm btn-outline-info fw-bold" onclick='buyAgain(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)' title="Buy Again / Duplicate to Draft"><i class="fas fa-redo-alt"></i></button>

                                                            <?php if($status != 'Converted'): ?>
                                                                <button class="btn btn-sm btn-outline-primary" onclick='openEditModal(<?= json_encode($row) ?>)' title="Edit Details"><i class="fas fa-edit"></i></button>
                                                                
                                                                <form method="POST" onsubmit="return confirm('Are you sure you want to remove this item?');" class="d-inline">
                                                                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                                                    <button class="btn btn-sm btn-outline-danger" title="Remove Item"><i class="fas fa-trash-alt"></i></button>
                                                                </form>
                                                            <?php endif; ?>

                                                            <?php if($status == 'Pending' || $status == 'Reserved'): ?>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="toggle_reserve_id" value="<?= $row['id'] ?>">
                                                                    <button class="btn btn-sm <?= $status == 'Reserved' ? 'btn-danger' : 'btn-outline-danger' ?> fw-bold" title="<?= $status == 'Reserved' ? 'Remove Reservation' : 'Reserve Item' ?>">
                                                                        <i class="fas fa-bookmark"></i>
                                                                    </button>
                                                                </form>
                                                                
                                                                <form method="POST" onsubmit="return confirm('Approve this item? This will DEDUCT stock.');" class="d-inline">
                                                                    <input type="hidden" name="approve_id" value="<?= $row['id'] ?>">
                                                                    <button class="btn btn-sm btn-warning fw-bold" title="Approve & Deduct Stock"><i class="fas fa-file-signature"></i></button>
                                                                </form>
                                                            <?php endif; ?>
                                                            
                                                            <?php if($status == 'Pending' || $status == 'Approved' || $status == 'Reserved'): ?>
                                                                <form method="POST" onsubmit="return confirm('Finalize to Sale?');" class="d-inline">
                                                                    <input type="hidden" name="convert_id" value="<?= $row['id'] ?>">
                                                                    <button class="btn btn-sm btn-success fw-bold" title="Finalize to Sale"><i class="fas fa-check-double"></i></button>
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
                    <div id="receiptContent" class="preview-box p-4 p-md-5 mx-auto" style="max-width: 850px; min-height: 1000px;"></div>
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
    let quoteQueue = []; // Holds items for the current batch
    
    // Pass PHP client and reserved data to Javascript
    const clientData = <?php echo json_encode($clientData); ?>;
    const reservedData = <?php echo json_encode($reservedData); ?>;

    document.addEventListener("DOMContentLoaded", () => {
        fetch('get_all_products.php')
            .then(res => res.json())
            .then(data => {
                const dl = document.getElementById('productList');
                data.forEach(p => {
                    productMap.set(p.name, p);
                    const opt = document.createElement('option');
                    opt.value = p.name;
                    
                    const stock = parseInt(p.current_stock) || 0;
                    const reserved = reservedData[p.name] || 0;
                    const available = stock - reserved;
                    
                    // Added Available & Reserved to the search list label
                    opt.label = `Avail: ${available} (Res: ${reserved}) | ₱${p.nam_price}`;
                    dl.appendChild(opt);
                });
            });

        // Initialize Price Calculators for both forms
        attachPriceCalculators('s_price', 'n_price', 'markup_pct', 'margin_pct', 'quantity', 'total_display');
        attachPriceCalculators('edit_s_price', 'edit_n_price', 'edit_markup_pct', 'edit_margin_pct', 'edit_quantity', 'edit_total_display');
    });

    // Handle Client Company Pre-fill Event
    document.getElementById('company').addEventListener('input', function() {
        const compName = this.value;
        if (clientData.hasOwnProperty(compName)) {
            const client = clientData[compName];
            if (!document.getElementById('po').value && client.po) {
                document.getElementById('po').value = client.po;
            }
            if (!document.getElementById('term').value && client.term) {
                document.getElementById('term').value = client.term;
            }
            if (!document.getElementById('remarks').value && client.remarks) {
                document.getElementById('remarks').value = client.remarks;
            }
        }
    });

    // Handle Item Selection Auto-fill & Stock Tracking
    document.getElementById('itemInput').addEventListener('input', function() {
        const p = productMap.get(this.value);
        if (p) {
            document.getElementById('s_price').value = p.supplier_price;
            document.getElementById('n_price').value = p.nam_price;
            document.getElementById('categoryField').value = p.category_code || 'General';
            
            // --- STOCK TRACKER COMPUTATION ---
            const stock = parseInt(p.current_stock) || 0;
            const reserved = reservedData[p.name] || 0;
            const available = stock - reserved;
            
            document.getElementById('infoStock').innerText = stock;
            document.getElementById('infoReserved').innerText = reserved;
            document.getElementById('infoAvailable').innerText = available;
            
            // Show the Tracker box
            document.getElementById('stockTracker').style.display = 'block';
            
            // Trigger calculation so markup/margin auto-fills & computes "Left After Quote"
            document.getElementById('n_price').dispatchEvent(new Event('input'));
            document.getElementById('quantity').dispatchEvent(new Event('input'));
        } else {
            // Hide if invalid item
            document.getElementById('stockTracker').style.display = 'none';
        }
    });

    // Update the "Left After This Quote" computation dynamically when user types quantity
    document.getElementById('quantity').addEventListener('input', function() {
        if (document.getElementById('stockTracker').style.display !== 'none') {
            const reqQty = parseFloat(this.value) || 0;
            const available = parseInt(document.getElementById('infoAvailable').innerText) || 0;
            const left = available - reqQty;
            
            const leftEl = document.getElementById('infoLeft');
            leftEl.innerText = left;
            
            // Color code it based on if it goes into the negative
            if (left < 0) {
                leftEl.classList.remove('text-primary');
                leftEl.classList.add('text-danger');
            } else {
                leftEl.classList.remove('text-danger');
                leftEl.classList.add('text-primary');
            }
        }
    });

    // --- BUY AGAIN FUNCTION (SINGLE ITEM) ---
    function buyAgain(row) {
        if (!document.getElementById('company').value) {
            document.getElementById('company').value = row.company;
            document.getElementById('po').value = row.po_number || '';
            document.getElementById('term').value = row.payment_term || '';
            document.getElementById('remarks').value = row.remarks || '';
        }

        quoteQueue.push({
            item: row.item,
            quantity: parseFloat(row.quantity_requested) || 1,
            s_price: parseFloat(row.suppliers_price) || 0,
            n_price: parseFloat(row.nam_unit_price) || 0,
            category: row.category || 'Uncategorized'
        });

        renderQueue();
        document.getElementById('queueCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // --- BATCH BUY AGAIN FUNCTION (ENTIRE QUOTE) ---
    function batchBuyAgain(quotesArray) {
        if (!quotesArray || quotesArray.length === 0) return;
        
        const first = quotesArray[0];

        if (!document.getElementById('company').value) {
            document.getElementById('company').value = first.company || '';
            document.getElementById('po').value = first.po_number || '';
            document.getElementById('term').value = first.payment_term || '';
            document.getElementById('remarks').value = first.remarks || '';
        }

        quotesArray.forEach(row => {
            quoteQueue.push({
                item: row.item,
                quantity: parseFloat(row.quantity_requested) || 1,
                s_price: parseFloat(row.suppliers_price) || 0,
                n_price: parseFloat(row.nam_unit_price) || 0,
                category: row.category || 'Uncategorized'
            });
        });

        renderQueue();
        document.getElementById('queueCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function clearQueue() {
        if(confirm("Are you sure you want to clear your current Quote Draft?")) {
            quoteQueue = [];
            renderQueue();
        }
    }

    // --- BATCH QUEUE LOGIC ---
    function addToQuote() {
        const item = document.getElementById('itemInput').value;
        const qty = parseFloat(document.getElementById('quantity').value) || 0;
        const sPrice = parseFloat(document.getElementById('s_price').value) || 0;
        const nPrice = parseFloat(document.getElementById('n_price').value) || 0;
        const category = document.getElementById('categoryField').value || 'Uncategorized';

        if (!item || qty <= 0 || nPrice <= 0) {
            alert("Please enter a valid item, quantity, and selling price before adding.");
            return;
        }

        quoteQueue.push({
            item: item,
            quantity: qty,
            s_price: sPrice,
            n_price: nPrice,
            category: category
        });

        // Reset the form fields and hide the tracker
        document.getElementById('itemInput').value = '';
        document.getElementById('quantity').value = '1';
        document.getElementById('s_price').value = '';
        document.getElementById('n_price').value = '';
        document.getElementById('total_display').value = '0.00';
        document.getElementById('markup_pct').value = '';
        document.getElementById('margin_pct').value = '';
        document.getElementById('categoryField').value = '';
        document.getElementById('stockTracker').style.display = 'none';
        document.getElementById('itemInput').focus();

        renderQueue();
    }

    function renderQueue() {
        const card = document.getElementById('queueCard');
        const body = document.getElementById('queueBody');
        const count = document.getElementById('queueCount');

        if (quoteQueue.length === 0) {
            card.style.display = 'none';
            return;
        }

        card.style.display = 'block';
        count.textContent = quoteQueue.length;

        let html = '';
        quoteQueue.forEach((q, idx) => {
            const total = q.quantity * q.n_price;
            html += `
                <tr>
                    <td class="ps-3 fw-bold">${q.item}</td>
                    <td class="text-center">${q.quantity}</td>
                    <td class="text-end">₱${q.n_price.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="text-end text-primary fw-bold">₱${total.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="text-end pe-3">
                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromQueue(${idx})" title="Remove"><i class="fas fa-times"></i></button>
                    </td>
                </tr>
            `;
        });
        body.innerHTML = html;
    }

    function removeFromQueue(idx) {
        quoteQueue.splice(idx, 1);
        renderQueue();
    }

    // UPDATED FUNCTION: Now accepts the status mode directly from the button click
    async function saveQuoteBatch(statusMode = 'Pending') {
        if (quoteQueue.length === 0) return;

        const date = document.getElementById('date').value;
        const ref = document.getElementById('quote_ref').value;
        const company = document.getElementById('company').value;

        if (!date || !ref || !company) {
            alert("Please ensure the Date, Quote Reference, and Client Company are filled in.");
            return;
        }

        const actionText = statusMode === 'Reserved' ? "save and RESERVE" : "save";
        if(!confirm(`Are you sure you want to ${actionText} this quotation with ${quoteQueue.length} items?`)) return;

        const payload = {
            action: 'create_quote_batch',
            status: statusMode, // Passes "Reserved" or "Pending" to the API
            header: {
                date: date,
                quote_ref: ref,
                company: company,
                po: document.getElementById('po').value,
                term: document.getElementById('term').value,
                remarks: document.getElementById('remarks').value
            },
            items: quoteQueue
        };

        try {
            const res = await fetch('quotations.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (data.success) {
                window.location.href = 'quotations.php?msg=created';
            } else {
                alert("Error saving quotation: " + data.message);
            }
        } catch (e) {
            alert("Network Error: Could not reach the server.");
        }
    }

    // --- PRICING CALCULATOR LOGIC ---
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
        
        document.getElementById('edit_n_price').dispatchEvent(new Event('input'));
        
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    // --- DYNAMIC PREVIEW RECALCULATION ---
    function recalcPreview() {
        let rows = document.querySelectorAll('#previewTbody tr');
        let rawTotal = 0;
        
        rows.forEach(row => {
            let qtyInput = row.querySelector('.prev-qty');
            let priceInput = row.querySelector('.prev-price');
            
            if (qtyInput && priceInput) {
                let qty = parseFloat(qtyInput.value) || 0;
                let price = parseFloat(priceInput.value) || 0;
                let rowTotal = qty * price;
                
                row.querySelector('.prev-total').innerText = rowTotal.toLocaleString('en-US', {minimumFractionDigits: 2});
                rawTotal += rowTotal;
            }
        });
        
        let vatType = document.getElementById('prevVatType').value;
        let vatable = 0;
        let vatAmt = 0;
        let grandTotal = 0;
        let vatLabel = 'VAT (12%):';
        
        if (vatType === 'inclusive') {
            vatable = rawTotal / 1.12;
            vatAmt = rawTotal - vatable;
            grandTotal = rawTotal;
        } else if (vatType === 'exclusive') {
            vatable = rawTotal;
            vatAmt = rawTotal * 0.12;
            grandTotal = rawTotal + vatAmt;
        } else {
            vatable = rawTotal;
            vatAmt = 0;
            grandTotal = rawTotal;
            vatLabel = 'VAT (0%):';
        }
        
        document.getElementById('vatLabel').innerText = vatLabel;
        document.getElementById('prevVatable').innerText = '₱' + vatable.toLocaleString('en-US', {minimumFractionDigits: 2});
        document.getElementById('prevVatAmt').innerText = '₱' + vatAmt.toLocaleString('en-US', {minimumFractionDigits: 2});
        document.getElementById('prevGrandTotal').innerText = '₱' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2});
    }

    // --- FORMAL DOCUMENT PRINT RENDERING ---
    function renderFormalPrint(date, ref, client, tbodyHtml, grandTotal, po, term, remarks) {
        
        let vatable = grandTotal / 1.12;
        let vatAmt = grandTotal - vatable;

        const html = `
            <div class="d-print-none alert alert-info py-2 d-flex justify-content-between align-items-center mb-4 border border-info shadow-sm">
                <span class="small fw-bold text-dark"><i class="fas fa-magic me-1"></i> Live Calculations Active: You can edit Quantities and Prices directly below.</span>
                <div class="d-flex align-items-center gap-2">
                    <label class="small fw-bold mb-0 text-dark text-nowrap">VAT Mode:</label>
                    <select id="prevVatType" class="form-select form-select-sm fw-bold border-info text-primary" style="width: 180px;" onchange="recalcPreview()">
                        <option value="inclusive">VAT Inclusive (12%)</option>
                        <option value="exclusive">VAT Exclusive (+12%)</option>
                        <option value="exempt">VAT Exempt (0%)</option>
                    </select>
                </div>
            </div>

            <div id="printArea" class="bg-white formal-sans" style="color: #000; line-height: 1.4;">
                <div class="row mb-4">
                    <div class="col-8">
                        <h2 class="fw-bolder mb-1" style="color: #003366; letter-spacing: 0.5px;">NAM BUILDERS AND SUPPLY CORP.</h2>
                        <p class="mb-0" style="font-size: 0.85rem;">RNA BUILDING, BRGY SANTIAGO</p>
                        <p class="mb-0" style="font-size: 0.85rem;">MALVAR, BATANGAS, PHILIPPINES, 4233</p>
                        <p class="mb-0" style="font-size: 0.85rem;">CONTACT NO: 0963-732-6844 / 0917-834-8811 / 0901-556-352</p>
                        <p class="mb-0" style="font-size: 0.85rem;">EMAIL: <input type="text" class="print-input inline-edit" style="width: 250px;" placeholder="Enter email"></p>
                    </div>
                    <div class="col-4 text-end">
                        <h1 class="fw-bolder text-uppercase mt-2" style="color: #475569; font-size: 32px; letter-spacing: 2px;">QUOTATION</h1>
                    </div>
                </div>

                <hr class="border-dark border-2 opacity-100 mb-4">

                <div class="mb-4">
                    <h6 class="fw-bold mb-3 text-uppercase" style="font-size: 0.95rem;">CUSTOMER DETAIL</h6>
                    <div class="row" style="font-size: 0.85rem;">
                        <div class="col-8">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <th width="150" class="p-0 pb-1">COMPANY NAME:</th>
                                    <td class="p-0 pb-1 fw-bold"><input type="text" class="print-input inline-edit w-100 fw-bold" value="${client}"></td>
                                </tr>
                                <tr>
                                    <th class="p-0 pb-1">COMPANY ADDRESS:</th>
                                    <td class="p-0 pb-1"><input type="text" class="print-input inline-edit w-100" placeholder="[Enter Address]"></td>
                                </tr>
                                <tr>
                                    <th class="p-0 pb-1">CONTACT PERSON:</th>
                                    <td class="p-0 pb-1"><input type="text" class="print-input inline-edit w-100" placeholder="[Enter Contact Person]"></td>
                                </tr>
                                <tr>
                                    <th class="p-0 pb-1">CONTACT NUMBER:</th>
                                    <td class="p-0 pb-1"><input type="text" class="print-input inline-edit w-100" placeholder="[Enter Contact Number]"></td>
                                </tr>
                                <tr>
                                    <th class="p-0 pb-1">EMAIL ADDRESS:</th>
                                    <td class="p-0 pb-1"><input type="text" class="print-input inline-edit w-100" placeholder="[Enter Email]"></td>
                                </tr>
                                <tr>
                                    <th class="p-0 pb-1 mt-2 d-block">TERMS:</th>
                                    <td class="p-0 pb-1 mt-2"><input type="text" class="print-input inline-edit w-100" value="${term}"></td>
                                </tr>
                                <tr>
                                    <th class="p-0 pb-1">TRANSPORT:</th>
                                    <td class="p-0 pb-1"><input type="text" class="print-input inline-edit w-100" placeholder="[Enter Transport]"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-4">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <th width="130" class="p-0 pb-1">QUOTATION NO:</th>
                                    <td class="p-0 pb-1 fw-bold">${ref}</td>
                                </tr>
                                <tr>
                                    <th class="p-0 pb-1">QUOTATION DATE:</th>
                                    <td class="p-0 pb-1 fw-bold">${date}</td>
                                </tr>
                                <tr>
                                    <th class="p-0 pb-1 mt-5 d-block">TRANSPORT ID:</th>
                                    <td class="p-0 pb-1 mt-5"><input type="text" class="print-input inline-edit w-100" placeholder="[Transport ID]"></td>
                                </tr>
                                <tr>
                                    <th class="p-0 pb-1">VEHICLE NO:</th>
                                    <td class="p-0 pb-1"><input type="text" class="print-input inline-edit w-100" placeholder="[Vehicle No]"></td>
                                </tr>
                                <tr>
                                    <th class="p-0 pb-1 text-muted">INQUIRY REF #:</th>
                                    <td class="p-0 pb-1 text-muted"><input type="text" class="print-input inline-edit w-100" value="${po}"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <table class="table table-bordered border-dark mb-4" style="font-size: 0.85rem;">
                    <thead class="text-center align-middle bg-light fw-bold" style="-webkit-print-color-adjust: exact; print-color-adjust: exact;">
                        <tr>
                            <th width="8%" class="py-2">S/N</th>
                            <th width="40%" class="py-2">DESCRIPTION</th>
                            <th width="12%" class="py-2">UOM</th>
                            <th width="10%" class="py-2">QUANTITY</th>
                            <th width="15%" class="py-2">UNIT PRICE</th>
                            <th width="15%" class="py-2">TOTAL AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody id="previewTbody" class="border-dark align-middle">
                        ${tbodyHtml}
                    </tbody>
                    <tfoot class="border-dark">
                        <tr>
                            <td colspan="5" class="text-end py-1 fw-bold pe-3 border-bottom-0">VATABLE SALES:</td>
                            <td class="text-end py-1 fw-bold border-bottom-0" id="prevVatable">₱${vatable.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        </tr>
                        <tr>
                            <td colspan="5" class="text-end py-1 fw-bold pe-3 border-bottom-0" id="vatLabel">VAT (12%):</td>
                            <td class="text-end py-1 fw-bold border-bottom-0" id="prevVatAmt">₱${vatAmt.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        </tr>
                        <tr class="bg-light" style="-webkit-print-color-adjust: exact; print-color-adjust: exact;">
                            <td colspan="5" class="text-end py-2 fw-bolder pe-3 fs-6">GRAND TOTAL AMOUNT</td>
                            <td class="text-end py-2 fs-6 fw-bolder" id="prevGrandTotal">₱${parseFloat(grandTotal).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="row" style="font-size: 0.75rem;">
                    <div class="col-7 pe-4">
                        <h6 class="fw-bold mb-1" style="font-size: 0.85rem;">PAYMENT DETAILS</h6>
                        <table class="table table-sm table-borderless mb-3 p-0">
                            <tr><th width="120" class="p-0">BANK NAME</th><td class="p-0">: BANK OF COMMERCE</td></tr>
                            <tr><th class="p-0">ACCOUNT NAME</th><td class="p-0">: NAM BUILDERS AND SUPPLY CORP</td></tr>
                            <tr><th class="p-0">ACCOUNT NO.</th><td class="p-0">: 106-20-000556-1</td></tr>
                        </table>

                        <h6 class="fw-bold mb-1" style="font-size: 0.85rem;">CHECK DETAILS</h6>
                        <table class="table table-sm table-borderless mb-3 p-0">
                            <tr><th width="120" class="p-0">Name</th><td class="p-0">: NAM BUILDERS AND SUPPLY CORP</td></tr>
                        </table>

                        <h6 class="fw-bold mb-1" style="font-size: 0.85rem;">TERMS AND CONDITION</h6>
                        
                        <p class="fw-bold mb-0 text-decoration-underline mt-2">Payment Terms</p>
                        <ul class="mb-2 ps-3">
                            <li>If there are any price change NAM BUILDERS AND SUPPLY CORP will resend a quotation prior to process an order.</li>
                            <li>Check or Cash Payment must be collected by NAM BUILDERS AND SUPPLY CORP.</li>
                            <li>Only items stated in this quotation shall be stated in the PURCHASE ORDER.</li>
                        </ul>

                        <p class="fw-bold mb-0 text-decoration-underline">Delivery Terms</p>
                        <ul class="mb-2 ps-3">
                            <li>Client shall provide weekly projected requirements and <input type="text" class="print-input inline-edit text-center fw-bold p-0 m-0" style="width: 40px;" value="4-6"> days lead time for planning purpose. Any modification in the daily should be communicated twenty-four (24) hours before the schedule.</li>
                            <li>Client Scheduled delivery on Monday-Friday.</li>
                            <li>Client Authorized Representative must be present at the company to acknowledge the products and quantity described on the Delivery Receiving.</li>
                        </ul>

                        <p class="fw-bold mb-0 text-decoration-underline">Quality Terms</p>
                        <ul class="mb-2 ps-3">
                            <li>Client Authorized Representative must signed the Receiving Inspection Stamp.</li>
                            <li>Items reported as damaged or wrong items must be replaced within <input type="text" class="print-input inline-edit text-center fw-bold p-0 m-0" style="width: 25px;" value="7"> days of the reported date (Receiving Inspection Stamp), provided all eligibility criteria are met.</li>
                        </ul>

                        <p class="fw-bold mb-0 text-decoration-underline">Validity</p>
                        <ul class="mb-4 ps-3">
                            <li><input type="text" class="print-input inline-edit text-center fw-bold p-0 m-0" style="width: 65px;" value="1 month"> validity effective receipt of this quotation.</li>
                        </ul>
                        
                        ${remarks ? `<div class="p-2 mt-2 border border-dark rounded bg-light"><strong class="d-block mb-1">Additional Remarks:</strong>${remarks.replace(/\\n/g, '<br>')}</div>` : ''}
                    </div>

                    <div class="col-5 border-start border-dark ps-4">
                        <p class="mb-3 text-justify">Thank you for giving us the opportunity to do business with you.</p>
                        <p class="mb-5 text-justify">If the terms and conditions in this quotation are acceptable, please indicate your acceptance of them by signing in the space provided below and returning signed counterpart of this proposal to NAM BUILDERS AND SUPPLY CORP. Upon NAM BUILDERS AND SUPPLY CORP received of this quotation, the terms and conditions contained herein shall constitute a binding agreement between your company and NAM BUILDERS AND SUPPLY CORP, effective as of the date NAM BUILDERS AND SUPPLY CORP received.</p>
                        
                        <div class="mt-5 pt-3">
                            <p class="mb-0">Sincerely,</p>
                            <input type="text" class="print-input inline-edit w-100 fw-bold fs-6 mb-0 mt-3" value="ALLYSON ASHLEY AGUILERA">
                            <div class="small">Sales and Technical Officer</div>
                        </div>

                        <div class="mt-5 pt-3">
                            <p class="mb-0">Conforme:</p>
                            <input type="text" class="print-input inline-edit w-100 fw-bold fs-6 mb-0 mt-3" placeholder="[Client Signature / Name]">
                            <div class="small">Signature over printed name</div>
                        </div>
                    </div>
                </div>

            </div>
        `;
        document.getElementById('receiptContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('previewModal')).show();
    }

    // Preview Multiple Items from Queue (Before Saving)
    function showPreviewNew() {
        if (quoteQueue.length === 0) {
            alert("Please add at least one item to the Quote Draft before previewing.");
            return;
        }

        const date = document.getElementById('date').value;
        const ref = document.getElementById('quote_ref').value;
        const client = document.getElementById('company').value;
        const po = document.getElementById('po').value;
        const term = document.getElementById('term').value;
        const remarks = document.getElementById('remarks').value;
        
        let tbodyHtml = '';
        let grandTotal = 0;

        quoteQueue.forEach((q, index) => {
            let total = q.quantity * q.n_price;
            grandTotal += total;
            let sn = String(index + 1).padStart(3, '0');
            
            tbodyHtml += `
                <tr>
                    <td class="text-center py-2">${sn}</td>
                    <td class="py-2 fw-bold"><input type="text" class="print-input inline-edit w-100 p-0 m-0 fw-bold" value="${q.item.replace(/"/g, '&quot;')}"></td>
                    <td class="text-center py-2"><input type="text" class="print-input inline-edit text-center w-100 p-0 m-0" placeholder="SET/PCS" value="SET"></td>
                    <td class="text-center py-2"><input type="number" class="print-input inline-edit text-center w-100 p-0 m-0 prev-qty" value="${q.quantity}" oninput="recalcPreview()"></td>
                    <td class="text-end py-2"><input type="number" step="0.01" class="print-input inline-edit text-end w-100 p-0 m-0 prev-price" value="${q.n_price}" oninput="recalcPreview()"></td>
                    <td class="text-end py-2 fw-bold"><span class="prev-total">${total.toLocaleString('en-US', {minimumFractionDigits: 2})}</span></td>
                </tr>
            `;
        });

        renderFormalPrint(date, ref, client, tbodyHtml, grandTotal, po, term, remarks);
    }

    // Print Multiple Items from Saved Group
    function printGroupedQuote(quotes, company, ref) {
        let tbody = '';
        let grandTotal = 0;
        let date = quotes[0].date;
        let po = quotes[0].po_number || '';
        let term = quotes[0].payment_term || '';
        let remarks = quotes[0].remarks || '';

        quotes.forEach((q, index) => {
            let total = q.quantity_requested * q.nam_unit_price;
            grandTotal += total;
            let sn = String(index + 1).padStart(3, '0');
            
            tbody += `
                <tr>
                    <td class="text-center py-2">${sn}</td>
                    <td class="py-2 fw-bold"><input type="text" class="print-input inline-edit w-100 p-0 m-0 fw-bold" value="${q.item.replace(/"/g, '&quot;')}"></td>
                    <td class="text-center py-2"><input type="text" class="print-input inline-edit text-center w-100 p-0 m-0" placeholder="SET/PCS" value="SET"></td>
                    <td class="text-center py-2"><input type="number" class="print-input inline-edit text-center w-100 p-0 m-0 prev-qty" value="${q.quantity_requested}" oninput="recalcPreview()"></td>
                    <td class="text-end py-2"><input type="number" step="0.01" class="print-input inline-edit text-end w-100 p-0 m-0 prev-price" value="${q.nam_unit_price}" oninput="recalcPreview()"></td>
                    <td class="text-end py-2 fw-bold"><span class="prev-total">${total.toLocaleString('en-US', {minimumFractionDigits: 2})}</span></td>
                </tr>
            `;
        });

        renderFormalPrint(date, ref, company, tbody, grandTotal, po, term, remarks);
    }

    function executePrint() {
        const printArea = document.getElementById('printArea');
        const inputs = printArea.querySelectorAll('input');
        inputs.forEach(input => {
            input.setAttribute('value', input.value);
        });

        const content = printArea.outerHTML;
        document.getElementById('printContainer').innerHTML = content;
        window.print();
    }
    
    function filterAccordions(input) {
        const filter = input.value.toLowerCase();
        const groups = document.querySelectorAll('.company-group');
        groups.forEach(group => {
            const compName = group.querySelector('.company-name').innerText.toLowerCase();
            const itemsText = group.querySelector('tbody').innerText.toLowerCase();
            group.style.display = (compName.includes(filter) || itemsText.includes(filter)) ? '' : 'none';
        });
    }
    </script>
</body>
</html>