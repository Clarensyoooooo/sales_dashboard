<?php 
require_once 'config.php'; 
requireLogin(); 

$conn = getDBConnection();

// --- AUTO-HEAL DATABASE: Add draft column to products ---
$checkDraft = $conn->query("SHOW COLUMNS FROM products LIKE 'is_draft'");
if($checkDraft && $checkDraft->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN is_draft TINYINT(1) DEFAULT 0");
}

// --- FETCH STATS FOR DASHBOARD CARDS ---
$stats = $conn->query("SELECT status, COUNT(*) as cnt, SUM(total_amount) as val FROM quotations GROUP BY status");
$statData = [
    'Pending' => ['cnt' => 0, 'val' => 0],
    'Approved' => ['cnt' => 0, 'val' => 0],
    'Reserved' => ['cnt' => 0, 'val' => 0],
    'Converted' => ['cnt' => 0, 'val' => 0]
];
if ($stats) {
    while ($r = $stats->fetch_assoc()) {
        $status = $r['status'] ? $r['status'] : 'Pending';
        if (isset($statData[$status])) {
            $statData[$status]['cnt'] = $r['cnt'];
            $statData[$status]['val'] = $r['val'];
        }
    }
}

// --- AUTO-GENERATE NEXT REFERENCE NUMBER (YYYY-XXX) ---
function getNextQuoteRef($conn) {
    $yr = date('Y');
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
    $conn->begin_transaction();
    try {
        $status = isset($input['status']) ? $input['status'] : 'Pending'; 
        $stmt = $conn->prepare("INSERT INTO quotations (date, quote_ref, company, category, item, quantity_requested, suppliers_price, nam_unit_price, total_amount, po_number, payment_term, remarks, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $checkProd = $conn->prepare("SELECT id FROM products WHERE name = ?");
        $insertProd = $conn->prepare("INSERT INTO products (name, category_code, supplier_price, nam_price, margin, current_stock, is_draft) VALUES (?, ?, ?, ?, ?, 0, 1)");

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

            $checkProd->bind_param("s", $item['item']);
            $checkProd->execute();
            if ($checkProd->get_result()->num_rows === 0) {
                $marginVal = ($item['n_price'] > 0) ? (($item['n_price'] - $item['s_price']) / $item['n_price']) * 100 : 0;
                $marginStr = number_format($marginVal, 2) . '%';
                $insertProd->bind_param("ssdds", $item['item'], $cat, $item['s_price'], $item['n_price'], $marginStr);
                $insertProd->execute();
            }
        }
        
        logAction('Created Quotation', "Created {$status} quotation for {$header['company']} (Ref: {$header['quote_ref']}) with " . count($items) . " items.");
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit; 
}

// --- 1. APPROVE QUOTE & DEDUCT STOCK ---
if (isset($_POST['approve_id'])) {
    $q_id = intval($_POST['approve_id']);
    $conn = getDBConnection();

    $q = $conn->query("SELECT * FROM quotations WHERE id = $q_id")->fetch_assoc();
    
    if ($q && $q['status'] != 'Approved' && $q['status'] != 'Converted') {
        $check = $conn->prepare("SELECT current_stock FROM products WHERE name = ?");
        $check->bind_param("s", $q['item']);
        $check->execute();
        $stock = $check->get_result()->fetch_assoc();
        
        if ($stock && $stock['current_stock'] >= $q['quantity_requested']) {
            $conn->begin_transaction();
            try {
                $upd = $conn->prepare("UPDATE products SET current_stock = current_stock - ? WHERE name = ?");
                $upd->bind_param("is", $q['quantity_requested'], $q['item']);
                $upd->execute();
                
                $conn->query("UPDATE quotations SET status = 'Approved' WHERE id = $q_id");
                logAction('Approved Quotation', "Approved quote for {$q['company']} (Item: {$q['item']}) and deducted stock.");
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

// --- 8. MERGE DUPLICATE COMPANIES ---
if (isset($_POST['action']) && $_POST['action'] == 'merge_companies') {
    $conn = getDBConnection();
    $target = $conn->real_escape_string($_POST['target_company']);
    $duplicates = json_decode($_POST['duplicates'], true);
    
    if (!empty($target) && !empty($duplicates)) {
        foreach ($duplicates as $dup) {
            if ($dup !== $target) {
                $dupEsc = $conn->real_escape_string($dup);
                // Update both tables to fix history entirely
                $conn->query("UPDATE quotations SET company = '$target' WHERE company = '$dupEsc'");
                $conn->query("UPDATE sales SET company = '$target' WHERE company = '$dupEsc'");
            }
        }
        logAction('Merged Companies', "Merged multiple duplicate client entries into '$target'");
    }
    header("Location: quotations.php?msg=merged");
    exit;
}

// --- 2. DELETE QUOTE ITEM ---
if (isset($_POST['delete_id'])) {
    $d_id = intval($_POST['delete_id']);
    $conn = getDBConnection();
    
    $q = $conn->query("SELECT * FROM quotations WHERE id = $d_id")->fetch_assoc();
    if ($q) {
        if ($q['status'] != 'Converted') {
            if ($q['status'] == 'Approved') {
                $conn->query("UPDATE products SET current_stock = current_stock + {$q['quantity_requested']} WHERE name = '{$conn->real_escape_string($q['item'])}'");
            }
            $conn->query("DELETE FROM quotations WHERE id = $d_id");
            logAction('Deleted Quotation Item', "Deleted quote item: {$q['item']} for {$q['company']}");
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
        $conn->begin_transaction();
        try {
            $income = ($q['nam_unit_price'] * $q['quantity_requested']) - ($q['suppliers_price'] * $q['quantity_requested']);
            $percent = ($q['nam_unit_price'] > 0) ? ($income / ($q['nam_unit_price'] * $q['quantity_requested'])) * 100 : 0;
            
            $stmt = $conn->prepare("INSERT INTO sales (date, company, category, item, quantity_requested, suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount, income, income_percent, po_number, payment_term, remarks, date_delivered, due_date) VALUES (CURRENT_DATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)");
            $total_actual = $q['suppliers_price'] * $q['quantity_requested'];
            $total_nam = $q['nam_unit_price'] * $q['quantity_requested'];
            
            $stmt->bind_param("sssidddddssss", $q['company'], $q['category'], $q['item'], $q['quantity_requested'], $q['suppliers_price'], $total_actual, $q['nam_unit_price'], $total_nam, $income, $percent, $q['po_number'], $q['payment_term'], $q['remarks']);
            
            if (!$stmt->execute()) throw new Exception("Failed to insert sale record.");
            
            if ($q['status'] != 'Approved') {
                $check = $conn->prepare("SELECT current_stock FROM products WHERE name = ?");
                $check->bind_param("s", $q['item']);
                $check->execute();
                $stock = $check->get_result()->fetch_assoc();
                
                if (!$stock || $stock['current_stock'] < $q['quantity_requested']) throw new Exception("Insufficient Stock");

                $updateStock = $conn->prepare("UPDATE products SET current_stock = current_stock - ? WHERE name = ?");
                $updateStock->bind_param("is", $q['quantity_requested'], $q['item']);
                if (!$updateStock->execute()) throw new Exception("Failed to deduct inventory.");
            }

            $conn->query("UPDATE quotations SET status = 'Converted' WHERE id = $q_id");
            $conn->commit();
            logAction('Converted Quotation', "Converted quote for {$q['company']} to a sale (Item: {$q['item']})");
            $msg = "success";
            
        } catch (Exception $e) {
            $conn->rollback();
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
        logAction('Updated Quote Status', "Changed quote status of item ID $r_id to $new_status");
    }
    header("Location: quotations.php?msg=reserved_toggled");
    exit;
}

// --- 5. EDIT QUOTE ---
if (isset($_POST['action']) && $_POST['action'] == 'edit_quote') {
    $conn = getDBConnection();
    $id = intval($_POST['edit_id']);
    $item = $_POST['edit_item']; // Added item variable
    $qty = intval($_POST['edit_quantity']);
    $s_price = floatval($_POST['edit_s_price']);
    $n_price = floatval($_POST['edit_n_price']);
    $total = $qty * $n_price;
    
    // Updated query to include 'item=?'
    $stmt = $conn->prepare("UPDATE quotations SET item=?, quantity_requested=?, suppliers_price=?, nam_unit_price=?, total_amount=? WHERE id=?");
    $stmt->bind_param("sidddi", $item, $qty, $s_price, $n_price, $total, $id);
    $stmt->execute();
    logAction('Edited Quotation', "Updated quote ID $id details (New Item: $item, Qty: $qty).");
    header("Location: quotations.php?msg=edited");
    exit;
}

// --- 6. DELETE ENTIRE QUOTE GROUP ---
if (isset($_POST['delete_quote_ref'])) {
    $d_ref = $_POST['delete_quote_ref'];
    $conn = getDBConnection();
    
    $res = $conn->query("SELECT item, quantity_requested, status FROM quotations WHERE quote_ref = '$d_ref' AND status = 'Approved'");
    while($q = $res->fetch_assoc()) {
        $conn->query("UPDATE products SET current_stock = current_stock + {$q['quantity_requested']} WHERE name = '{$conn->real_escape_string($q['item'])}'");
    }
    
    $conn->query("DELETE FROM quotations WHERE quote_ref = '$d_ref' AND status != 'Converted'");
    logAction('Deleted Quotation Group', "Deleted entire quote group Ref: $d_ref");
    header("Location: quotations.php?msg=deleted");
    exit;
}

// --- 7. EDIT QUOTE GROUP DETAILS ---
if (isset($_POST['action']) && $_POST['action'] == 'edit_quote_group') {
    $conn = getDBConnection();
    $ref = $conn->real_escape_string($_POST['group_ref']);
    $po = $conn->real_escape_string($_POST['group_po']);
    $term = $conn->real_escape_string($_POST['group_term']);
    $remarks = $conn->real_escape_string($_POST['group_remarks']);
    
    $conn->query("UPDATE quotations SET po_number='$po', payment_term='$term', remarks='$remarks' WHERE quote_ref='$ref'");
    logAction('Edited Quotation Group', "Updated details for quote Ref: $ref");
    header("Location: quotations.php?msg=edited");
    exit;
}

$conn = getDBConnection();
$next_ref_default = getNextQuoteRef($conn);

// --- UNIFIED CLIENT DATA FETCHING ---
$clientData = [];
$resSales = $conn->query("SELECT company, address, payment_term, contact_person_contact FROM sales WHERE company IS NOT NULL AND company != '' ORDER BY date DESC, id DESC");
if ($resSales) {
    while($row = $resSales->fetch_assoc()) {
        $comp = trim($row['company']);
        if (!isset($clientData[$comp])) {
            $clientData[$comp] = ['address' => trim($row['address'] ?? ''), 'term' => trim($row['payment_term'] ?? ''), 'po' => '', 'remarks' => ''];
        }
    }
}
$resQuotes = $conn->query("SELECT company, po_number, payment_term, remarks FROM quotations WHERE company IS NOT NULL AND company != '' ORDER BY date DESC, id DESC");
if ($resQuotes) {
    while($row = $resQuotes->fetch_assoc()) {
        $comp = trim($row['company']);
        if (!isset($clientData[$comp])) {
            $clientData[$comp] = ['address' => '', 'term' => trim($row['payment_term'] ?? ''), 'po' => trim($row['po_number'] ?? ''), 'remarks' => trim($row['remarks'] ?? '')];
        } else {
            if (empty($clientData[$comp]['po'])) $clientData[$comp]['po'] = trim($row['po_number'] ?? '');
            if (empty($clientData[$comp]['term'])) $clientData[$comp]['term'] = trim($row['payment_term'] ?? '');
        }
    }
}
$csvFile = 'CLIENT-TIN - Sheet1.csv'; 
if (file_exists($csvFile) && ($handle = fopen($csvFile, "r")) !== FALSE) {
    fgetcsv($handle); 
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $compName = trim($data[0] ?? '');
        $address  = trim($data[1] ?? '');
        if (!empty($compName)) {
            if (!isset($clientData[$compName])) {
                $clientData[$compName] = ['address' => $address, 'term' => '', 'po' => '', 'remarks' => ''];
            } else if (empty($clientData[$compName]['address'])) {
                $clientData[$compName]['address'] = $address;
            }
        }
    }
    fclose($handle);
}
ksort($clientData);

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
        body { background-color: #f4f6f9; }
        
        /* Dashboard Stat Cards */
        .stat-card { transition: transform 0.2s, box-shadow 0.2s; border-radius: 12px; border: none; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
        .stat-icon { font-size: 2.5rem; opacity: 0.2; position: absolute; right: 20px; top: 20px; }
        
        /* Custom Accordion / Table Styling */
        .accordion-button:not(.collapsed) { background-color: #f8f9fa; color: #212529; box-shadow: inset 0 -1px 0 rgba(0,0,0,.125); }
        .company-group { border-radius: 10px !important; margin-bottom: 15px; border: 1px solid #e0e0e0; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .table > :not(caption) > * > * { padding: 0.75rem 1rem; }
        
        /* Modal Form UI */
        .form-section-header { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #eee; }
        .total-display { font-size: 1.1rem; font-weight: bold; color: #0d6efd; background: #e9ecef; }
        
        /* Interactive Print Inputs */
        .print-input { border: none; border-bottom: 1px dashed #aaa; background: transparent; padding: 2px 5px; outline: none; transition: border 0.3s; }
        .print-input:focus { border-bottom: 1px solid #0d6efd; }
        .preview-box { border: 1px solid #dee2e6; background: #fff; padding: 0; box-shadow: 0 0 15px rgba(0,0,0,0.05); }
        .inline-edit { color: #0d6efd; cursor: pointer; }
        .inline-edit:focus { color: #000; background-color: #f8f9fa; border-bottom: 1px solid #0d6efd !important; }
        [contenteditable]:empty:before { content: attr(placeholder); color: #adb5bd; pointer-events: none; display: block; font-style: italic; }

        @media print {
            body > :not(#printContainer) { display: none !important; }
            #printContainer { display: block !important; position: absolute; top: 0; left: 0; width: 100%; margin: 0; padding: 0; }
            .print-input { border-bottom: none !important; color: #000 !important; }
            .inline-edit { color: #000 !important; } 
            .print-input::-webkit-input-placeholder { color: transparent; }
            [contenteditable]:empty:before { display: none !important; }
            input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
            @page { size: A4 portrait; margin: 5mm; }
            #printArea { font-size: 11px !important; line-height: 1.1 !important; }
            #printArea h3 { font-size: 16px !important; margin-bottom: 2px !important; }
            #printArea h1 { font-size: 24px !important; margin-bottom: 0px !important; }
            #printArea .mb-4 { margin-bottom: 8px !important; }
            table { page-break-inside: auto; width: 100% !important; border-collapse: collapse; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
            tfoot { display: table-row-group; }
        }
        .formal-text { font-family: "Times New Roman", Times, serif; }
        .formal-sans { font-family: Arial, Helvetica, sans-serif; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4 pb-5 px-md-4">
        
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card shadow-sm bg-white position-relative overflow-hidden">
                    <div class="card-body">
                        <i class="fas fa-clock stat-icon text-warning"></i>
                        <h6 class="text-muted fw-bold text-uppercase mb-1">Pending Review</h6>
                        <h3 class="mb-0 text-dark fw-bolder"><?= number_format($statData['Pending']['cnt']) ?> <small class="fs-6 text-muted fw-normal">Items</small></h3>
                        <div class="mt-2 text-warning fw-bold small">₱<?= number_format($statData['Pending']['val'], 2) ?> Value</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card shadow-sm bg-white position-relative overflow-hidden">
                    <div class="card-body">
                        <i class="fas fa-file-signature stat-icon text-info"></i>
                        <h6 class="text-muted fw-bold text-uppercase mb-1">Approved & Ready</h6>
                        <h3 class="mb-0 text-dark fw-bolder"><?= number_format($statData['Approved']['cnt']) ?> <small class="fs-6 text-muted fw-normal">Items</small></h3>
                        <div class="mt-2 text-info fw-bold small">₱<?= number_format($statData['Approved']['val'], 2) ?> Value</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card shadow-sm bg-white position-relative overflow-hidden">
                    <div class="card-body">
                        <i class="fas fa-bookmark stat-icon text-danger"></i>
                        <h6 class="text-muted fw-bold text-uppercase mb-1">Reserved Stock</h6>
                        <h3 class="mb-0 text-dark fw-bolder"><?= number_format($statData['Reserved']['cnt']) ?> <small class="fs-6 text-muted fw-normal">Items</small></h3>
                        <div class="mt-2 text-danger fw-bold small">₱<?= number_format($statData['Reserved']['val'], 2) ?> Value</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card shadow-sm bg-white position-relative overflow-hidden border-start border-4 border-success">
                    <div class="card-body">
                        <i class="fas fa-check-double stat-icon text-success"></i>
                        <h6 class="text-muted fw-bold text-uppercase mb-1">Converted to Sales</h6>
                        <h3 class="mb-0 text-dark fw-bolder"><?= number_format($statData['Converted']['cnt']) ?> <small class="fs-6 text-muted fw-normal">Items</small></h3>
                        <div class="mt-2 text-success fw-bold small">₱<?= number_format($statData['Converted']['val'], 2) ?> Value</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4 bg-white rounded">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3 p-3">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-primary fw-bold shadow-sm px-4 py-2 rounded-pill" onclick="openEncoderModal()">
                        <i class="fas fa-plus-circle me-2"></i>Create New Quotation
                    </button>
                    
                    <button class="btn btn-warning text-dark fw-bold shadow-sm px-4 py-2 rounded-pill" onclick="openMergeModal()">
    <i class="fas fa-object-group me-2"></i>Merge Duplicate Clients
</button>

                    <div class="btn-group shadow-sm" role="group" id="quoteFilters">
                        <input type="radio" class="btn-check" name="qFilter" id="filterAll" value="all" checked onchange="applyQuoteFilters()">
                        <label class="btn btn-outline-secondary fw-bold" for="filterAll">All Quotes</label>

                        <input type="radio" class="btn-check" name="qFilter" id="filterAction" value="action" onchange="applyQuoteFilters()">
                        <label class="btn btn-outline-secondary fw-bold" for="filterAction"><i class="fas fa-exclamation-circle me-1 text-warning"></i>Action Needed</label>

                        <input type="radio" class="btn-check" name="qFilter" id="filterConverted" value="converted" onchange="applyQuoteFilters()">
                        <label class="btn btn-outline-secondary fw-bold" for="filterConverted"><i class="fas fa-check-double me-1 text-success"></i>Converted</label>
                    </div>
                </div>
                
                <div class="input-group w-auto shadow-sm" style="max-width: 350px;">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="mainSearchInput" class="form-control border-start-0 ps-0 bg-light" placeholder="Search companies, refs, or items..." onkeyup="applyQuoteFilters()">
                </div>
            </div>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-info shadow-sm mb-4 alert-dismissible fade show">
                <i class="fas fa-info-circle me-2"></i> 
                <?php 
                $msgs = [
                    'error_stock' => 'Cannot Finalize/Approve: Insufficient Stock.',
                    'error_db' => 'System Error: Failed to process transaction.',
                    'approved' => 'Quotation Approved & Stock Deducted (Pending Signatures).',
                    'success' => 'Sale Finalized Successfully!',
                    'edited' => 'Quotation successfully updated.',
                    'deleted' => 'Item removed from list (and stock restored if approved).',
                    'created' => 'Quotation Created Successfully.',
                    'reserved_toggled' => 'Item reservation status successfully updated.'
                ];
                echo isset($msgs[$_GET['msg']]) ? $msgs[$_GET['msg']] : 'Action completed.';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="accordion" id="quotesAccordion">
            <?php
            $grouped = [];
            $res = $conn->query("SELECT * FROM quotations ORDER BY company ASC, date DESC, id DESC");
            while($row = $res->fetch_assoc()) {
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
            <div class="accordion-item company-group bg-white overflow-hidden">
                <h2 class="accordion-header" id="heading<?= $i ?>">
                    <button class="accordion-button <?= $i==1?'':'collapsed' ?> py-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $i ?>">
                        <div class="w-100 d-flex justify-content-between align-items-center pe-3">
                            <div>
                                <i class="fas fa-building text-primary me-2 fs-5 align-middle"></i> 
                                <strong class="company-name fs-5 align-middle"><?= htmlspecialchars($company) ?></strong>
                            </div>
                            <div class="text-end">
                                <?php if($pendingCount > 0): ?>
                                    <span class="badge bg-warning text-dark me-2 px-3 py-2 rounded-pill"><i class="fas fa-exclamation-circle me-1"></i><?= $pendingCount ?> Action Required</span>
                                <?php endif; ?>
                                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill"><?= $totalItems ?> Items Total</span>
                            </div>
                        </div>
                    </button>
                </h2>
                
                <div id="collapse<?= $i ?>" class="accordion-collapse collapse <?= $i==1?'show':'' ?>" data-bs-parent="#quotesAccordion">
                    <div class="accordion-body p-4 bg-light bg-opacity-50">
                        
                        <?php foreach($refs as $ref => $quotes): 
                            $quoteDate = date('F d, Y', strtotime($quotes[0]['date'])); 
                        ?>
                        <div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden">
                            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-3">
                                    <h5 class="text-dark fw-bolder mb-0"><i class="fas fa-file-invoice text-secondary me-2"></i>Ref: <span class="text-primary"><?= $ref ?></span></h5>
                                    <span class="badge bg-light text-dark border"><i class="far fa-calendar-alt me-1"></i> <?= $quoteDate ?></span>
                                    <?php if(!empty($quotes[0]['po_number'])): ?>
                                        <span class="badge bg-info text-dark bg-opacity-25 border border-info">Inquiry #: <?= $quotes[0]['po_number'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-success fw-bold" 
                                            onclick='addItemsToExisting(<?= htmlspecialchars(json_encode($company), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($ref), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($quotes[0]['po_number'] ?? ''), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($quotes[0]['payment_term'] ?? ''), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($quotes[0]['remarks'] ?? ''), ENT_QUOTES, "UTF-8") ?>)' 
                                            title="Add more items to this specific Quotation">
                                        <i class="fas fa-plus me-1"></i> Add Item
                                    </button>

                                    <button class="btn btn-sm btn-outline-primary fw-bold" 
                                            onclick='editGroupDetails(<?= json_encode($ref) ?>, <?= json_encode($quotes[0]) ?>)' 
                                            title="Edit Group Info (PO, Terms, Remarks)">
                                        <i class="fas fa-edit"></i> Edit Group
                                    </button>

                                    <button class="btn btn-sm btn-dark fw-bold shadow-sm" 
                                            onclick='printGroupedQuote(<?= htmlspecialchars(json_encode($quotes), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($company), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($ref), ENT_QUOTES, "UTF-8") ?>)' 
                                            title="Print Formal Document">
                                        <i class="fas fa-print me-1"></i> Print Formal Quote
                                    </button>

                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this ENTIRE quotation?');" class="d-inline">
                                        <input type="hidden" name="delete_quote_ref" value="<?= $ref ?>">
                                        <button class="btn btn-sm btn-outline-danger fw-bold" title="Delete Entire Quotation">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light text-muted small text-uppercase fw-bold border-bottom">
                                        <tr>
                                            <th width="35%" class="ps-4">Item Details</th>
                                            <th class="text-center" width="10%">Qty</th>
                                            <th class="text-end" width="20%">Unit Price / Total</th>
                                            <th width="15%" class="text-center">Status</th>
                                            <th class="text-end pe-4" width="20%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach(array_reverse($quotes) as $row): 
                                            $status = $row['status'] ?: 'Pending';
                                            $badge = 'secondary';
                                            if ($status == 'Approved') $badge = 'info text-dark';
                                            if ($status == 'Reserved') $badge = 'danger';
                                            if ($status == 'Converted') $badge = 'success';
                                            $totalAmt = $row['quantity_requested'] * $row['nam_unit_price'];
                                        ?>
                                        <tr class="item-row <?= $status=='Converted'?'opacity-50 bg-light':'' ?>">
                                            <td class="ps-4 py-3"><strong class="item-name text-dark fs-6"><?= $row['item'] ?></strong></td>
                                            <td class="text-center py-3"><span class="fs-6 fw-bold"><?= $row['quantity_requested'] ?></span></td>
                                            <td class="text-end py-3">
                                                <small class="text-muted d-block">₱<?= number_format($row['nam_unit_price'], 2) ?></small>
                                                <strong class="text-primary fs-6">₱<?= number_format($totalAmt, 2) ?></strong>
                                            </td>
                                            <td class="text-center py-3"><span class="badge bg-<?= $badge ?> fs-6 rounded-pill px-3"><?= $status ?></span></td>
                                            <td class="text-end pe-4 py-3">
                                                <div class="d-flex justify-content-end gap-2">
                                                    
                                                    <button class="btn btn-sm btn-light border text-secondary fw-bold shadow-sm" onclick='buyAgain(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)' title="Buy Again / Duplicate to Draft"><i class="fas fa-redo-alt"></i></button>

                                                    <?php if($status != 'Converted'): ?>
                                                        <button class="btn btn-sm btn-light border text-primary shadow-sm" onclick='openEditModal(<?= json_encode($row) ?>)' title="Edit Details"><i class="fas fa-edit"></i></button>
                                                        
                                                        <form method="POST" onsubmit="return confirm('Are you sure you want to remove this item?');" class="d-inline">
                                                            <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                                            <button class="btn btn-sm btn-light border text-danger shadow-sm" title="Remove Item"><i class="fas fa-trash-alt"></i></button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <?php if($status == 'Pending' || $status == 'Reserved'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="toggle_reserve_id" value="<?= $row['id'] ?>">
                                                            <button class="btn btn-sm shadow-sm <?= $status == 'Reserved' ? 'btn-danger' : 'btn-outline-danger bg-white' ?>" title="<?= $status == 'Reserved' ? 'Remove Reservation' : 'Reserve Item' ?>">
                                                                <i class="fas fa-bookmark"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <form method="POST" onsubmit="return confirm('Approve this item? This will DEDUCT stock.');" class="d-inline">
                                                            <input type="hidden" name="approve_id" value="<?= $row['id'] ?>">
                                                            <button class="btn btn-sm btn-warning fw-bold shadow-sm" title="Approve & Deduct Stock"><i class="fas fa-file-signature"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($status == 'Pending' || $status == 'Approved' || $status == 'Reserved'): ?>
                                                        <form method="POST" onsubmit="return confirm('Finalize to Sale?');" class="d-inline">
                                                            <input type="hidden" name="convert_id" value="<?= $row['id'] ?>">
                                                            <button class="btn btn-sm btn-success fw-bold shadow-sm px-3" title="Finalize to Sale"><i class="fas fa-check"></i> Finalize</button>
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

    <div class="modal fade" id="encoderModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold fs-5"><i class="fas fa-magic me-2"></i>Quotation Draft Workspace</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="checkQueueBeforeClose()"></button>
                </div>
                <div class="modal-body bg-light p-4">
                    <div class="row g-4 h-100">
                        
                        <div class="col-lg-4 border-end pe-lg-4">
                            <h6 class="text-primary fw-bold text-uppercase border-bottom pb-2 mb-3"><i class="fas fa-edit me-2"></i>Item Entry Form</h6>
                            
                            <form id="quoteForm" onsubmit="event.preventDefault(); addToQuote();">
                                <input type="hidden" id="categoryField">
                                
                                <div class="mb-4 bg-white p-3 rounded shadow-sm border border-secondary-subtle">
                                    <div class="form-section-header text-dark border-secondary">1. Document Header</div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="small text-muted fw-bold">Date</label>
                                            <input type="date" id="date" class="form-control form-control-sm bg-light" value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted fw-bold">Quote Reference</label>
                                            <input type="text" id="quote_ref" class="form-control form-control-sm bg-light fw-bold" value="<?= $next_ref_default ?>" required>
                                        </div>
                                        
                                        <div class="col-12 mt-3">
                                            <label class="small text-muted fw-bold d-flex justify-content-between align-items-end mb-1">
                                                <span>Client Company</span>
                                                <a href="javascript:void(0)" onclick="openClientListModal()" class="text-decoration-none small text-primary fw-bold"><i class="fas fa-list me-1"></i>Select from List</a>
                                            </label>
                                            <input type="text" id="company" class="form-control form-control-sm border-primary shadow-sm" placeholder="Search client..." required autocomplete="off">
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="small text-muted fw-bold mt-2">Company Address</label>
                                            <input type="text" id="address" class="form-control form-control-sm" placeholder="Address">
                                        </div>

                                        <div class="col-6 mt-2">
                                            <label class="small text-muted fw-bold">Inquiry #</label>
                                            <input type="text" id="po" class="form-control form-control-sm" placeholder="Optional">
                                        </div>
                                        <div class="col-6 mt-2">
                                            <label class="small text-muted fw-bold">Terms</label>
                                            <input type="text" id="term" class="form-control form-control-sm" placeholder="e.g. 30 Days">
                                        </div>
                                        <div class="col-12 mt-2">
                                            <textarea id="remarks" class="form-control form-control-sm" placeholder="Group Remarks / Notes..." rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white p-3 rounded shadow-sm border border-success-subtle">
                                    <div class="form-section-header text-success border-success">2. Add Item to Draft</div>
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <label class="small text-muted fw-bold">Item Description</label>
                                            <input type="text" id="itemInput" list="productList" class="form-control form-control-sm border-success shadow-sm" placeholder="Search inventory..." autocomplete="off">
                                            <datalist id="productList"></datalist>
                                        </div>
                                        <div class="col-12 mt-2">
                                            <label class="small text-muted fw-bold">Quantity</label>
                                            <input type="number" id="quantity" class="form-control form-control-sm text-center fw-bold fs-6" placeholder="Qty" value="1" min="1">
                                            
                                            <div id="stockTracker" class="mt-2 p-2 bg-light border rounded border-info-subtle" style="display: none; font-size: 0.75rem;">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">On-Hand Stock:</span>
                                                    <span class="fw-bold text-dark" id="infoStock">0</span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1 border-bottom pb-1">
                                                    <span class="text-danger">Reserved:</span>
                                                    <span class="fw-bold text-danger" id="infoReserved">0</span>
                                                </div>
                                                <div class="d-flex justify-content-between mt-1">
                                                    <span class="text-success fw-bold">True Available:</span>
                                                    <span class="fw-bold text-success" id="infoAvailable">0</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 mt-3">
                                            <div class="border rounded p-2 bg-light border-secondary-subtle">
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <label class="small text-muted fw-bold">Supplier Cost</label>
                                                        <input type="number" step="0.01" id="s_price" class="form-control form-control-sm text-end" placeholder="0.00">
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="small text-primary fw-bold">Selling Price</label>
                                                        <input type="number" step="0.01" id="n_price" class="form-control form-control-sm border-primary text-end fw-bold" placeholder="0.00">
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="small text-muted fw-bold">Markup (%)</label>
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" step="0.01" id="markup_pct" class="form-control text-end">
                                                            <span class="input-group-text">%</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="small text-muted fw-bold">Margin (%)</label>
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" step="0.01" id="margin_pct" class="form-control text-end">
                                                            <span class="input-group-text">%</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 mt-2 pt-2 border-top">
                                                        <label class="small text-muted fw-bold">Item Total</label>
                                                        <div class="input-group input-group-sm shadow-sm">
                                                            <span class="input-group-text bg-primary text-white border-primary">₱</span>
                                                            <input type="text" id="total_display" class="form-control fw-bold text-primary bg-white text-end fs-6" readonly value="0.00">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-4">
                                            <button type="submit" class="btn btn-success w-100 fw-bold shadow py-2 fs-6">
                                                <i class="fas fa-arrow-right me-2"></i> Add to Draft Queue
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="col-lg-8 d-flex flex-column h-100">
                            <h6 class="text-warning text-dark fw-bold text-uppercase border-bottom pb-2 mb-3 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-shopping-cart me-2"></i>Current Draft List</span>
                                <span class="badge bg-dark rounded-pill" id="queueCount">0 Items</span>
                            </h6>
                            
                            <div class="flex-grow-1 bg-white border rounded shadow-sm overflow-auto mb-3" style="min-height: 400px; max-height: 60vh;">
                                <table class="table table-hover align-middle mb-0" id="queueCardTable">
                                    <thead class="table-light text-muted small text-uppercase sticky-top shadow-sm">
                                        <tr>
                                            <th class="ps-3 py-3">Description</th>
                                            <th class="text-center py-3">Qty</th>
                                            <th class="text-end py-3">Unit Price</th>
                                            <th class="text-end py-3">Total Amount</th>
                                            <th class="text-end pe-3 py-3">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="queueBody">
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i><br>
                                                Queue is empty. Add items from the left panel.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center bg-white p-3 border rounded shadow-sm">
                                <div>
                                    <button class="btn btn-outline-danger fw-bold shadow-sm" onclick="clearQueue()" title="Clear Entire Draft">
                                        <i class="fas fa-trash-alt me-2"></i>Clear Queue
                                    </button>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-dark fw-bold shadow-sm px-4" onclick="showPreviewNew()">
                                        <i class="fas fa-search me-2"></i> Preview Formal Document
                                    </button>
                                    <button class="btn btn-danger fw-bold shadow-sm px-4" onclick="saveQuoteBatch('Reserved')">
                                        <i class="fas fa-bookmark me-2"></i> Save & Reserve
                                    </button>
                                    <button class="btn btn-primary fw-bold shadow-sm px-4" onclick="saveQuoteBatch('Pending')">
                                        <i class="fas fa-save me-2"></i> Save Quote Draft
                                    </button>
                                </div>
                            </div>

                        </div>

                    </div>
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
    <label class="small text-muted fw-bold">Item Description</label>
    <input type="text" name="edit_item" id="edit_item_display" class="form-control border-primary fw-bold text-dark" required>
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

    <div class="modal fade" id="editGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Edit Quotation Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body bg-light">
                        <input type="hidden" name="action" value="edit_quote_group">
                        <input type="hidden" name="group_ref" id="edit_group_ref">
                        
                        <div class="mb-2">
                            <label class="small text-muted fw-bold">Inquiry / PO Number</label>
                            <input type="text" name="group_po" id="edit_group_po" class="form-control">
                        </div>
                        <div class="mb-2">
                            <label class="small text-muted fw-bold">Payment Term</label>
                            <input type="text" name="group_term" id="edit_group_term" class="form-control">
                        </div>
                        <div class="mb-2">
                            <label class="small text-muted fw-bold">Remarks / Notes</label>
                            <textarea name="group_remarks" id="edit_group_remarks" class="form-control" rows="3"></textarea>
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
            <div class="modal-content border-0 shadow-lg">
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

    <div class="modal fade" id="mergeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark py-3">
                <h5 class="modal-title fw-bold fs-5"><i class="fas fa-object-group me-2"></i>Merge Duplicate Clients</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="submitMerge(event)">
                <div class="modal-body bg-light p-4">
                    <input type="hidden" name="action" value="merge_companies">
                    <input type="hidden" name="duplicates" id="duplicatesList">
                    
                    <div class="mb-4">
                        <label class="fw-bold text-dark small mb-2">1. Select the CORRECT (Target) Name:</label>
                        <select name="target_company" id="mergeTarget" class="form-select border-warning shadow-sm fw-bold text-primary" required>
                            <option value="">-- Choose the Primary Name --</option>
                            <?php foreach(array_keys($clientData) as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-bold text-dark small mb-2">2. Check all the TYPOS/DUPLICATES to merge into the target:</label>
                        <div class="bg-white border rounded p-2 shadow-sm" style="max-height: 250px; overflow-y: auto;">
                            <?php foreach(array_keys($clientData) as $c): ?>
                                <div class="form-check border-bottom py-1">
                                    <input class="form-check-input duplicate-checkbox" type="checkbox" value="<?= htmlspecialchars($c) ?>" id="chk_<?= md5($c) ?>">
                                    <label class="form-check-label w-100" style="cursor: pointer;" for="chk_<?= md5($c) ?>"><?= htmlspecialchars($c) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="alert alert-danger py-2 small mb-0 fw-bold border-danger shadow-sm">
                        <i class="fas fa-exclamation-triangle me-1"></i> Warning: This permanently updates all past quotes and sales to use the correct name.
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark px-4"><i class="fas fa-check-double me-2"></i>Execute Merge</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <div class="modal fade" id="clientListModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-users me-2"></i>Select Client</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <input type="text" id="clientSearch" class="form-control mb-3 shadow-sm" placeholder="Search Company Name..." onkeyup="renderClientList(this.value)">
                    <div style="max-height: 400px; overflow-y: auto;" class="bg-white border rounded">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light sticky-top">
                                <tr><th>Company</th><th>Address</th><th class="text-end pe-3">Action</th></tr>
                            </thead>
                            <tbody id="clientListBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="printContainer" class="d-none d-print-block"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let productMap = new Map();
    let quoteQueue = []; 
    let encoderModalInstance;
    
    const clientData = <?php echo json_encode($clientData); ?>;
    const reservedData = <?php echo json_encode($reservedData); ?>;

    document.addEventListener("DOMContentLoaded", () => {
        encoderModalInstance = new bootstrap.Modal(document.getElementById('encoderModal'));
        clientListModalInstance = new bootstrap.Modal(document.getElementById('clientListModal'));

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
                    
                    opt.label = `Avail: ${available} (Res: ${reserved}) | ₱${p.nam_price}`;
                    dl.appendChild(opt);
                });
            });

        attachPriceCalculators('s_price', 'n_price', 'markup_pct', 'margin_pct', 'quantity', 'total_display');
        attachPriceCalculators('edit_s_price', 'edit_n_price', 'edit_markup_pct', 'edit_margin_pct', 'edit_quantity', 'edit_total_display');
    });

    // --- ENCODER MODAL CONTROLS ---
    function openEncoderModal() {
        encoderModalInstance.show();
    }

    function checkQueueBeforeClose() {
        if(quoteQueue.length > 0) {
            if(!confirm("You have unsaved items in your draft queue. Are you sure you want to close? Your draft will be lost if you refresh the page.")) {
                encoderModalInstance.show(); 
            }
        }
    }

    // --- CLIENT LIST LOGIC ---
    let clientListModalInstance;
    function openClientListModal() {
        renderClientList('');
        document.getElementById('clientSearch').value = '';
        clientListModalInstance.show();
        setTimeout(() => document.getElementById('clientSearch').focus(), 500);
    }

    function renderClientList(filter = '') {
        const tbody = document.getElementById('clientListBody');
        tbody.innerHTML = '';
        filter = filter.toLowerCase();

        Object.keys(clientData).forEach(comp => {
            if(comp.toLowerCase().includes(filter)) {
                const c = clientData[comp];
                const safeComp = comp.replace(/'/g, "\\'"); 
                
                tbody.innerHTML += `
                    <tr>
                        <td class="fw-bold text-primary align-middle ps-3">${comp}</td>
                        <td class="align-middle"><small class="d-inline-block text-truncate" style="max-width: 200px;">${c.address || '-'}</small></td>
                        <td class="text-end align-middle pe-3">
                            <button class="btn btn-sm btn-primary py-1 px-3 shadow-sm" onclick="selectClient('${safeComp}')"><i class="fas fa-check me-1"></i>Select</button>
                        </td>
                    </tr>
                `;
            }
        });
    }

    function selectClient(comp) {
        const companyInput = document.getElementById('company');
        companyInput.value = comp;
        companyInput.dispatchEvent(new Event('input')); 
        clientListModalInstance.hide();
    }

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
            if (!document.getElementById('address').value && client.address) {
                document.getElementById('address').value = client.address;
            }
        }
    });

    // --- ITEM & QUEUE LOGIC ---
    document.getElementById('itemInput').addEventListener('input', function() {
        const p = productMap.get(this.value);
        if (p) {
            document.getElementById('s_price').value = p.supplier_price;
            document.getElementById('n_price').value = p.nam_price;
            document.getElementById('categoryField').value = p.category_code || 'General';
            
            const stock = parseInt(p.current_stock) || 0;
            const reserved = reservedData[p.name] || 0;
            const available = stock - reserved;
            
            document.getElementById('infoStock').innerText = stock;
            document.getElementById('infoReserved').innerText = reserved;
            document.getElementById('infoAvailable').innerText = available;
            
            document.getElementById('stockTracker').style.display = 'block';
            
            document.getElementById('n_price').dispatchEvent(new Event('input'));
            document.getElementById('quantity').dispatchEvent(new Event('input'));
        } else {
            document.getElementById('stockTracker').style.display = 'none';
        }
    });

    function buyAgain(row) {
        if (!document.getElementById('company').value) {
            document.getElementById('company').value = row.company;
            document.getElementById('po').value = row.po_number || '';
            document.getElementById('term').value = row.payment_term || '';
            document.getElementById('remarks').value = row.remarks || '';
            
            if (clientData.hasOwnProperty(row.company) && clientData[row.company].address) {
                document.getElementById('address').value = clientData[row.company].address;
            }
        }

        quoteQueue.push({
            item: row.item,
            quantity: parseFloat(row.quantity_requested) || 1,
            s_price: parseFloat(row.suppliers_price) || 0,
            n_price: parseFloat(row.nam_unit_price) || 0,
            category: row.category || 'Uncategorized'
        });

        renderQueue();
        encoderModalInstance.show();
    }

    function clearQueue() {
        if(confirm("Are you sure you want to clear your current Quote Draft?")) {
            quoteQueue = [];
            renderQueue();
        }
    }

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
        const body = document.getElementById('queueBody');
        const count = document.getElementById('queueCount');

        count.textContent = quoteQueue.length + " Items";

        if (quoteQueue.length === 0) {
            body.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-3 opacity-25"></i><br>Queue is empty. Add items from the left panel.</td></tr>';
            return;
        }

        let html = '';
        quoteQueue.forEach((q, idx) => {
            const total = q.quantity * q.n_price;
            html += `
                <tr>
                    <td class="ps-3 fw-bold align-middle">${q.item}</td>
                    <td class="text-center align-middle">${q.quantity}</td>
                    <td class="text-end align-middle">₱${q.n_price.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="text-end text-primary fw-bold align-middle fs-6">₱${total.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="text-end pe-3 align-middle">
                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromQueue(${idx})" title="Remove"><i class="fas fa-times"></i></button>
                    </td>
                </tr>
            `;
        });
        body.innerHTML = html;
        
        // Auto scroll to bottom of queue
        const tableContainer = document.getElementById('queueCardTable').parentElement;
        tableContainer.scrollTop = tableContainer.scrollHeight;
    }

    function removeFromQueue(idx) {
        quoteQueue.splice(idx, 1);
        renderQueue();
    }

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
            status: statusMode,
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

    function openEditModal(row) {
        document.getElementById('edit_id').value = row.id;
        document.getElementById('edit_item_display').value = row.item;
        document.getElementById('edit_quantity').value = row.quantity_requested;
        document.getElementById('edit_s_price').value = parseFloat(row.suppliers_price).toFixed(2);
        document.getElementById('edit_n_price').value = parseFloat(row.nam_unit_price).toFixed(2);
        
        document.getElementById('edit_n_price').dispatchEvent(new Event('input'));
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    window.editGroupDetails = function(ref, quoteData) {
        document.getElementById('edit_group_ref').value = ref;
        document.getElementById('edit_group_po').value = quoteData.po_number || '';
        document.getElementById('edit_group_term').value = quoteData.payment_term || '';
        document.getElementById('edit_group_remarks').value = quoteData.remarks || '';
        new bootstrap.Modal(document.getElementById('editGroupModal')).show();
    }

    window.addItemsToExisting = function(company, ref, po, term, remarks) {
        document.getElementById('company').value = company;
        document.getElementById('quote_ref').value = ref;
        document.getElementById('po').value = po || '';
        document.getElementById('term').value = term || '';
        document.getElementById('remarks').value = remarks || '';
        
        if (clientData.hasOwnProperty(company) && clientData[company].address) {
            document.getElementById('address').value = clientData[company].address;
        } else {
            document.getElementById('address').value = '';
        }
        
        encoderModalInstance.show();
        setTimeout(() => document.getElementById('itemInput').focus(), 500);
    }

    function applyQuoteFilters() {
        const searchQuery = document.getElementById('mainSearchInput').value.toLowerCase();
        const filterState = document.querySelector('input[name="qFilter"]:checked').value;
        const groups = document.querySelectorAll('.company-group');

        groups.forEach(group => {
            const compName = group.querySelector('.company-name').innerText.toLowerCase();
            const itemsText = group.querySelector('.accordion-body').innerText.toLowerCase();
            
            const matchesSearch = compName.includes(searchQuery) || itemsText.includes(searchQuery);
            
            let matchesStatus = true;
            const hasPending = group.querySelector('.badge.bg-warning.text-dark') !== null; 
            
            if (filterState === 'action') {
                matchesStatus = hasPending;
            } else if (filterState === 'converted') {
                matchesStatus = !hasPending && group.querySelectorAll('.item-row').length > 0; 
            }

            if (matchesSearch && matchesStatus) {
                group.style.display = '';
                if (searchQuery.length > 2 || filterState !== 'all') {
                    const collapseTarget = group.querySelector('.accordion-collapse');
                    const button = group.querySelector('.accordion-button');
                    if (!collapseTarget.classList.contains('show')) {
                        button.classList.remove('collapsed');
                        collapseTarget.classList.add('show');
                    }
                }
            } else {
                group.style.display = 'none';
            }
        });
    }

    // --- FORMAL PREVIEW LOGIC ---
    function recalcPreview() {
        let modalContent = document.getElementById('receiptContent');
        if (!modalContent) return;

        let rows = modalContent.querySelectorAll('#previewTbody tr');
        let rawTotal = 0;
        
        rows.forEach(row => {
            let qtyInput = row.querySelector('.prev-qty');
            let priceInput = row.querySelector('.prev-price');
            
            if (qtyInput && priceInput) {
                let qty = parseFloat(qtyInput.value) || 0;
                let price = parseFloat(priceInput.value) || 0;
                let rowTotal = Math.round(qty * price * 100) / 100;
                
                row.querySelector('.prev-total').innerText = rowTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                rawTotal += rowTotal;
            }
        });
        
        let vatType = document.getElementById('prevVatType').value;
        let vatable = 0, vatAmt = 0, grandTotal = rawTotal;
        let vatLabel = 'VAT (12%):';
        
        if (vatType === 'inclusive') {
            vatable = Math.round((rawTotal / 1.12) * 100) / 100;
            vatAmt = Math.round((rawTotal - vatable) * 100) / 100;
        } else if (vatType === 'exclusive') {
            vatAmt = Math.round((rawTotal * 0.12) * 100) / 100;
            vatable = rawTotal;
            grandTotal = rawTotal + vatAmt;
        } else {
            vatable = rawTotal;
            vatLabel = 'VAT (0%):';
        }
        
        let applyWht = document.getElementById('prevWhtToggle') && document.getElementById('prevWhtToggle').checked;
        let whtAmt = 0;
        
        if (applyWht) {
            whtAmt = Math.round((vatable * 0.01) * 100) / 100; 
            modalContent.querySelector('#whtRow').classList.remove('d-none');
        } else {
            modalContent.querySelector('#whtRow').classList.add('d-none');
        }
        
        let netPayable = Math.round((grandTotal - whtAmt) * 100) / 100;
        
        modalContent.querySelector('#vatLabel').innerText = vatLabel;
        modalContent.querySelector('#prevVatable').innerText = '₱' + vatable.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        modalContent.querySelector('#prevVatAmt').innerText = '₱' + vatAmt.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        modalContent.querySelector('#prevWhtAmt').innerText = '-₱' + whtAmt.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        modalContent.querySelector('#prevGrandTotal').innerText = '₱' + netPayable.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    window.loadPreviewImg = function(input, storageKey) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const wrapper = input.closest('.item-img-wrapper');
                const img = wrapper.querySelector('.preview-img');
                const lbl = wrapper.querySelector('.upload-lbl');
                
                img.src = e.target.result;
                img.classList.remove('d-none');
                img.classList.add('d-print-block');
                lbl.classList.add('d-none');

                if (storageKey) {
                    try { localStorage.setItem('cache_img_' + storageKey, e.target.result); } 
                    catch(err) {}
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function buildItemRowHTML(q, index) {
        let total = q.quantity * q.n_price;
        let sn = String(index + 1).padStart(3, '0');
        let itemNameSafe = q.item ? q.item.replace(/['"\W]+/g, '_') : 'unknown';
        let cachedImg = localStorage.getItem('cache_img_' + itemNameSafe);
        
        let imgTag = cachedImg ? `src="${cachedImg}" class="preview-img d-print-block"` : `src="" class="preview-img d-none"`;
        let lblDisplay = cachedImg ? `d-none` : `d-flex`;

        return `
            <tr>
                <td class="text-center py-1 align-middle">${sn}</td>
                <td class="text-center py-1 align-middle">
                    <div class="position-relative item-img-wrapper d-inline-block mx-auto">
                        <img ${imgTag} style="width: 100px; height: 100px; object-fit: contain; cursor: pointer; border: 1px solid #eee; border-radius: 4px;" onclick="this.parentElement.querySelector('input').click()" title="Click to change image">
                        <label class="btn btn-outline-secondary btn-sm p-0 m-0 d-print-none ${lblDisplay} align-items-center justify-content-center upload-lbl shadow-sm" style="width: 100px; height: 100px; cursor: pointer; border-style: dashed; font-size: 0.85rem;" title="Add Image">
                            <i class="fas fa-camera text-muted fa-lg"></i>
                            <input type="file" accept="image/*" class="d-none" onchange="loadPreviewImg(this, '${itemNameSafe}')">
                        </label>
                    </div>
                </td>
                <td class="py-1 text-start align-middle">
                    <div contenteditable="true" class="print-input inline-edit w-100 p-0 m-0 fw-bold" style="outline: none; word-break: break-word;">${q.item ? q.item.replace(/"/g, '&quot;') : ''}</div>
                </td>
                <td class="text-center py-1 align-middle"><input type="text" class="print-input inline-edit text-center w-100 p-0 m-0" placeholder="SET/PCS" value="SET"></td>
                <td class="text-center py-1 align-middle"><input type="number" class="print-input inline-edit text-center w-100 p-0 m-0 prev-qty" value="${q.quantity}" oninput="recalcPreview()"></td>
                <td class="text-end py-1 align-middle"><input type="number" step="0.01" class="print-input inline-edit text-end w-100 p-0 m-0 prev-price" value="${(parseFloat(q.n_price) || 0).toFixed(2)}" oninput="recalcPreview()"></td>
                <td class="text-end py-1 fw-bold align-middle"><span class="prev-total">${total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span></td>
            </tr>
        `;
    }

    function renderFormalPrint(date, ref, client, address, tbodyHtml, grandTotal, po, term, remarks) {
        let vatable = grandTotal / 1.12;
        let vatAmt = grandTotal - vatable;

        let cachedSig1 = localStorage.getItem('cache_img_sig_1') || '';
        let cachedSig2 = localStorage.getItem('cache_img_sig_2') || '';
        
        let sig1Img = cachedSig1 ? `src="${cachedSig1}" class="preview-img d-print-block w-100 h-100"` : `src="" class="preview-img d-none w-100 h-100"`;
        let sig1Lbl = cachedSig1 ? `d-none` : `d-flex`;

        let sig2Img = cachedSig2 ? `src="${cachedSig2}" class="preview-img d-print-block w-100 h-100"` : `src="" class="preview-img d-none w-100 h-100"`;
        let sig2Lbl = cachedSig2 ? `d-none` : `d-flex`;

        const html = `
            <div class="d-print-none alert alert-info py-2 d-flex justify-content-between align-items-center mb-4 border border-info shadow-sm">
                <span class="small fw-bold text-dark"><i class="fas fa-magic me-1"></i> Live Editing: Click any text to type. Click the dashed box to add item images!</span>
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="small fw-bold mb-0 text-dark text-nowrap">VAT Mode:</label>
                        <select id="prevVatType" class="form-select form-select-sm fw-bold border-info text-primary" style="width: 180px;" onchange="recalcPreview()">
                            <option value="inclusive">VAT Inclusive (12%)</option>
                            <option value="exclusive">VAT Exclusive (+12%)</option>
                            <option value="exempt">VAT Exempt (0%)</option>
                        </select>
                    </div>
                    <div class="form-check mb-0 mt-1">
                        <input class="form-check-input border-info" type="checkbox" id="prevWhtToggle" onchange="recalcPreview()">
                        <label class="form-check-label small fw-bold text-dark" for="prevWhtToggle">Less 1% WHT</label>
                    </div>
                </div>
            </div>

            <div id="printArea" class="bg-white formal-sans" style="color: #000; line-height: 1.4;">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div class="d-flex align-items-start">
                        <img src="YOUR_LOGO_HERE.png" alt="Logo" style="height: 60px; width: auto; margin-right: 15px; margin-top: 5px; object-fit: contain;" onerror="this.style.display='none'">
                        <div>
                            <h3 class="fw-bolder mb-1" style="color: #003366; letter-spacing: 0.5px; font-size: 1.2rem;">NAM BUILDERS AND SUPPLY CORP.</h3>
                            <div style="font-size: 0.85rem; line-height: 1.3;">
                                <span class="fw-bold">MAIN:</span> RNA BUILDING, BRGY SANTIAGO, MALVAR, BATANGAS, 4233<br>
                                <span class="fw-bold text-primary">SATELLITE OFFICE:</span> <span class="text-primary fw-bold">Yatco Subdivision, Barangay 4, Tanauan City, Batangas</span><br>
                                <span class="fw-bold">CONTACT NO:</span> 0963-732-6844 / 0917-834-8811 / 0901-556-352<br>
                                <span class="fw-bold">EMAIL:</span> nam.nswt@myyahoo.com
                            </div>
                        </div>
                    </div>
                    <div class="text-end pt-1">
                        <h1 class="fw-bolder text-uppercase mb-0" style="color: #475569; font-size: 26px; letter-spacing: 2px;">QUOTATION</h1>
                    </div>
                </div>

                <hr class="border-dark border-2 opacity-100 mb-2 mt-2">

                <div class="mb-3 mt-3">
                    <h6 class="fw-bold mb-2 text-uppercase" style="font-size: 0.95rem;">CUSTOMER DETAIL</h6>
                    <div class="row" style="font-size: 0.85rem;">
                        <div class="col-8">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><th width="150" class="p-0 pb-0 align-top">COMPANY NAME:</th><td class="p-0 pb-0"><div contenteditable="true" class="print-input inline-edit w-100 fw-bold" style="outline: none;">${client}</div></td></tr>
                                <tr><th class="p-0 pb-0 align-top">COMPANY ADDRESS:</th><td class="p-0 pb-0"><div contenteditable="true" class="print-input inline-edit w-100" style="outline: none; min-height: 1.4em;" placeholder="[Enter Address]">${address || ''}</div></td></tr>
                                <tr><th class="p-0 pb-0 align-top">CONTACT PERSON:</th><td class="p-0 pb-0"><div contenteditable="true" class="print-input inline-edit w-100" style="outline: none; min-height: 1.4em;" placeholder="[Enter Contact Person]"></div></td></tr>
                                <tr><th class="p-0 pb-0 align-top">CONTACT NUMBER:</th><td class="p-0 pb-0"><div contenteditable="true" class="print-input inline-edit w-100" style="outline: none; min-height: 1.4em;" placeholder="[Enter Contact Number]"></div></td></tr>
                                <tr><th class="p-0 pb-0 align-top">EMAIL ADDRESS:</th><td class="p-0 pb-0"><div contenteditable="true" class="print-input inline-edit w-100" style="outline: none; min-height: 1.4em;" placeholder="[Enter Email]"></div></td></tr>
                                <tr><th class="p-0 pb-0 mt-1 d-block align-top">TERMS:</th><td class="p-0 pb-0 mt-1"><div contenteditable="true" class="print-input inline-edit w-100" style="outline: none;">${term}</div></td></tr>
                                <tr><th class="p-0 pb-0 align-top">TRANSPORT:</th><td class="p-0 pb-0"><div contenteditable="true" class="print-input inline-edit w-100" style="outline: none; min-height: 1.4em;" placeholder="[Enter Transport]"></div></td></tr>
                            </table>
                        </div>
                        <div class="col-4">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><th width="130" class="p-0 pb-0 align-top">QUOTATION NO:</th><td class="p-0 pb-0 fw-bold"><div contenteditable="true" class="print-input inline-edit w-100" style="outline: none;">${ref}</div></td></tr>
                                <tr><th class="p-0 pb-0 align-top">QUOTATION DATE:</th><td class="p-0 pb-0 fw-bold"><div contenteditable="true" class="print-input inline-edit w-100" style="outline: none;">${date}</div></td></tr>
                                <tr><th class="p-0 pb-0 mt-4 d-block align-top">TRANSPORT ID:</th><td class="p-0 pb-0 mt-4"><div contenteditable="true" class="print-input inline-edit w-100" style="outline: none; min-height: 1.4em;" placeholder="[Transport ID]"></div></td></tr>
                                <tr><th class="p-0 pb-0 align-top">VEHICLE NO:</th><td class="p-0 pb-0"><div contenteditable="true" class="print-input inline-edit w-100" style="outline: none; min-height: 1.4em;" placeholder="[Vehicle No]"></div></td></tr>
                                <tr><th class="p-0 pb-0 text-muted align-top">INQUIRY REF #:</th><td class="p-0 pb-0 text-muted"><div contenteditable="true" class="print-input inline-edit w-100" style="outline: none; min-height: 1.4em;" placeholder="[Inquiry Ref]">${po}</div></td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                <table class="table table-bordered border-dark mb-3" style="font-size: 0.85rem;">
                    <thead class="text-center align-middle bg-light fw-bold" style="-webkit-print-color-adjust: exact; print-color-adjust: exact;">
                        <tr>
                            <th width="5%" class="py-1">S/N</th>
                            <th width="15%" class="py-1">IMAGE</th>
                            <th width="28%" class="py-1">DESCRIPTION</th>
                            <th width="12%" class="py-1">UOM</th>
                            <th width="10%" class="py-1">QUANTITY</th>
                            <th width="15%" class="py-1">UNIT PRICE</th>
                            <th width="15%" class="py-1">TOTAL AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody id="previewTbody" class="border-dark align-middle">
                        ${tbodyHtml}
                    </tbody>
                    <tfoot class="border-dark">
                        <tr><td colspan="6" class="text-end py-0 pt-1 fw-bold pe-3 border-bottom-0">VATABLE SALES:</td><td class="text-end py-0 pt-1 fw-bold border-bottom-0" id="prevVatable">₱${vatable.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td></tr>
                        <tr><td colspan="6" class="text-end py-0 pb-1 fw-bold pe-3 border-bottom-0" id="vatLabel">VAT (12%):</td><td class="text-end py-0 pb-1 fw-bold border-bottom-0" id="prevVatAmt">₱${vatAmt.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td></tr>
                        <tr id="whtRow" class="d-none"><td colspan="6" class="text-end py-0 pb-1 fw-bold pe-3 border-bottom-0 text-danger">LESS 1% WHT:</td><td class="text-end py-0 pb-1 fw-bold border-bottom-0 text-danger" id="prevWhtAmt">-₱0.00</td></tr>
                        <tr class="bg-light" style="-webkit-print-color-adjust: exact; print-color-adjust: exact;"><td colspan="6" class="text-end py-1 fw-bolder pe-3 fs-6">GRAND TOTAL AMOUNT</td><td class="text-end py-1 fs-6 fw-bolder" id="prevGrandTotal">₱${parseFloat(grandTotal).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td></tr>
                    </tfoot>
                </table>

                <div class="row" style="font-size: 0.70rem;">
                    <div class="col-7 pe-4">
                        <h6 class="fw-bold mb-1" style="font-size: 0.80rem;">PAYMENT DETAILS</h6>
                        <table class="table table-sm table-borderless mb-2 p-0">
                            <tr><th width="100" class="p-0">BANK NAME</th><td class="p-0">: SECURITY BANK</td></tr>
                            <tr><th class="p-0">ACCOUNT NAME</th><td class="p-0">: NAM BUILDERS AND SUPPLY CORP.</td></tr>
                            <tr><th class="p-0">ACCOUNT NO.</th><td class="p-0">: 0000079551887</td></tr>
                        </table>

                        <h6 class="fw-bold mb-1" style="font-size: 0.80rem;">CHECK DETAILS</h6>
                        <table class="table table-sm table-borderless mb-2 p-0">
                            <tr><th width="100" class="p-0">Name</th><td class="p-0">: NAM BUILDERS AND SUPPLY CORP</td></tr>
                        </table>

                        <h6 class="fw-bold mb-1 mt-2" style="font-size: 0.80rem;">TERMS AND CONDITION</h6>
                        
                        <p class="fw-bold mb-0 text-decoration-underline mt-1">Payment Terms</p>
                        <ul class="mb-1 ps-3">
                            <li>If there are any price change NAM BUILDERS AND SUPPLY CORP will resend a quotation prior to process an order.</li>
                            <li>Check or Cash Payment must be collected by NAM BUILDERS AND SUPPLY CORP.</li>
                            <li>Only items stated in this quotation shall be stated in the PURCHASE ORDER.</li>
                        </ul>

                        <p class="fw-bold mb-0 text-decoration-underline">Delivery Terms</p>
                        <ul class="mb-1 ps-3">
                            <li>Client shall provide weekly projected requirements and <input type="text" class="print-input inline-edit text-center fw-bold p-0 m-0" style="width: 40px;" value="4-6"> days lead time for planning purpose. Any modification in the daily should be communicated twenty-four (24) hours before the schedule.</li>
                            <li>Client Scheduled delivery on Monday-Friday.</li>
                            <li>Client Authorized Representative must be present at the company to acknowledge the products and quantity described on the Delivery Receiving.</li>
                        </ul>

                        <p class="fw-bold mb-0 text-decoration-underline">Quality Terms</p>
                        <ul class="mb-1 ps-3">
                            <li>Client Authorized Representative must signed the Receiving Inspection Stamp.</li>
                            <li>Items reported as damaged or wrong items must be replaced within <input type="text" class="print-input inline-edit text-center fw-bold p-0 m-0" style="width: 25px;" value="7"> days of the reported date (Receiving Inspection Stamp), provided all eligibility criteria are met.</li>
                        </ul>

                        <p class="fw-bold mb-0 text-decoration-underline">Validity</p>
                        <ul class="mb-2 ps-3">
                            <li><input type="text" class="print-input inline-edit text-center fw-bold p-0 m-0" style="width: 65px;" value="1 month"> validity effective receipt of this quotation.</li>
                        </ul>
                        
                        <p class="fw-bold mb-0 text-decoration-underline mt-2">Remarks / Notes</p>
                        <div contenteditable="true" class="print-input inline-edit w-100 p-2 bg-light border border-secondary border-opacity-25 rounded mt-1" style="outline: none; min-height: 25px;" placeholder="Type additional remarks here...">${remarks ? remarks.replace(/\\n/g, '<br>') : ''}</div>
                    </div>

                    <div class="col-5 border-start border-dark ps-4">
                        <p class="mb-2 text-justify">Thank you for giving us the opportunity to do business with you.</p>
                        <p class="mb-4 text-justify">If the terms and conditions in this quotation are acceptable, please indicate your acceptance of them by signing in the space provided below and returning signed counterpart of this proposal to NAM BUILDERS AND SUPPLY CORP. Upon NAM BUILDERS AND SUPPLY CORP received of this quotation, the terms and conditions contained herein shall constitute a binding agreement between your company and NAM BUILDERS AND SUPPLY CORP, effective as of the date NAM BUILDERS AND SUPPLY CORP received.</p>
                        
                        <div class="mt-4 pt-2">
                            <p class="mb-0">Sincerely,</p>
                            <div class="position-relative item-img-wrapper d-print-inline-block mt-2 mb-1" style="width: 180px; height: 60px;">
                                <img ${sig1Img} style="object-fit: contain; border-bottom: 1px solid #333; cursor: pointer;" onclick="this.parentElement.querySelector('input').click()" title="Click to change signature">
                                <label class="btn btn-outline-secondary btn-sm p-0 m-0 w-100 h-100 d-print-none ${sig1Lbl} align-items-center justify-content-center upload-lbl shadow-sm" style="cursor: pointer; border-style: dashed;" title="Add Signature">
                                    <i class="fas fa-signature text-muted me-2"></i> Add E-Sign
                                    <input type="file" accept="image/*" class="d-none" onchange="loadPreviewImg(this, 'sig_1')">
                                </label>
                            </div>
                            <input type="text" class="print-input inline-edit w-100 fw-bold fs-6 mb-0" value="ALLYSON ASHLEY AGUILERA">
                            <div class="small">Sales and Technical Officer</div>
                        </div>

                        <div class="mt-4 pt-2">
                            <p class="mb-0">Conforme:</p>
                            <div class="position-relative item-img-wrapper d-print-inline-block mt-2 mb-1" style="width: 180px; height: 60px;">
                                <img ${sig2Img} style="object-fit: contain; border-bottom: 1px solid #333; cursor: pointer;" onclick="this.parentElement.querySelector('input').click()" title="Click to change signature">
                                <label class="btn btn-outline-secondary btn-sm p-0 m-0 w-100 h-100 d-print-none ${sig2Lbl} align-items-center justify-content-center upload-lbl shadow-sm" style="cursor: pointer; border-style: dashed;" title="Add Signature">
                                    <i class="fas fa-signature text-muted me-2"></i> Add E-Sign
                                    <input type="file" accept="image/*" class="d-none" onchange="loadPreviewImg(this, 'sig_2')">
                                </label>
                            </div>
                            <input type="text" class="print-input inline-edit w-100 fw-bold fs-6 mb-0" placeholder="[Client Signature / Name]">
                            <div class="small">Signature over printed name</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('receiptContent').innerHTML = html;
        
        encoderModalInstance.hide();
        new bootstrap.Modal(document.getElementById('previewModal')).show();
        
        document.getElementById('previewModal').addEventListener('hidden.bs.modal', function () {
            encoderModalInstance.show();
        }, { once: true });
    }

    function showPreviewNew() {
        if (quoteQueue.length === 0) {
            alert("Please add at least one item to the Quote Draft before previewing.");
            return;
        }

        const date = document.getElementById('date').value;
        const ref = document.getElementById('quote_ref').value;
        const client = document.getElementById('company').value;
        const address = document.getElementById('address').value;
        const po = document.getElementById('po').value;
        const term = document.getElementById('term').value;
        const remarks = document.getElementById('remarks').value;
        
        let tbodyHtml = '';
        let grandTotal = 0;

        quoteQueue.forEach((q, index) => {
            let total = q.quantity * q.n_price;
            grandTotal += total;
            tbodyHtml += buildItemRowHTML(q, index);
        });

        renderFormalPrint(date, ref, client, address, tbodyHtml, grandTotal, po, term, remarks);
    }

    function printGroupedQuote(quotes, company, ref) {
    let tbody = '';
    let grandTotal = 0;
    
    // Grab the header info from the newest entry
    let date = quotes[0].date;
    let po = quotes[0].po_number || '';
    let term = quotes[0].payment_term || '';
    let remarks = quotes[0].remarks || '';
    
    let address = '';
    if (clientData.hasOwnProperty(company) && clientData[company].address) {
        address = clientData[company].address;
    }

    // --- THE FIX: Reverse the array so new items go to the bottom of the printout! ---
    let printItems = [...quotes].reverse();

    printItems.forEach((q, index) => {
        let itemObj = { item: q.item, quantity: parseFloat(q.quantity_requested)||0, n_price: parseFloat(q.nam_unit_price)||0 };
        grandTotal += itemObj.quantity * itemObj.n_price;
        tbody += buildItemRowHTML(itemObj, index);
    });

    renderFormalPrint(date, ref, company, address, tbody, grandTotal, po, term, remarks);
}

    function executePrint() {
        const printArea = document.getElementById('printArea');
        const inputs = printArea.querySelectorAll('input');
        inputs.forEach(input => {
            if(input.type !== 'file') {
                input.setAttribute('value', input.value);
            }
        });

        const content = printArea.outerHTML;
        document.getElementById('printContainer').innerHTML = content;
        window.print();
        
        setTimeout(() => { document.getElementById('printContainer').innerHTML = ''; }, 1000);
    }

    // --- MERGE CLIENTS LOGIC ---
let mergeModalInstance;

document.addEventListener("DOMContentLoaded", () => {
    mergeModalInstance = new bootstrap.Modal(document.getElementById('mergeModal'));
});

function openMergeModal() {
    mergeModalInstance.show();
}

function submitMerge(e) {
    const checkboxes = document.querySelectorAll('.duplicate-checkbox:checked');
    const target = document.getElementById('mergeTarget').value;
    
    let duplicates = [];
    checkboxes.forEach(chk => {
        if(chk.value !== target) {
            duplicates.push(chk.value);
        }
    });
    
    if(duplicates.length === 0) {
        e.preventDefault();
        alert("Please check at least one duplicate name to merge.");
        return;
    }
    
    document.getElementById('duplicatesList').value = JSON.stringify(duplicates);
    
    if(!confirm(`Are you completely sure you want to merge ${duplicates.length} duplicate(s) into "${target}"?`)) {
        e.preventDefault();
    }
}

    </script>
</body>
</html>