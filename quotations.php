<?php 
require_once 'config.php'; 
requireLogin(); 

// --- CONVERT TO SALE (RESERVATION LOGIC) ---
if (isset($_POST['convert_id'])) {
    $q_id = intval($_POST['convert_id']);
    $conn = getDBConnection();
    
    $q = $conn->query("SELECT * FROM quotations WHERE id = $q_id")->fetch_assoc();
    
    if ($q) {
        // 1. Check Stock
        $check = $conn->prepare("SELECT current_stock FROM products WHERE name = ?");
        $check->bind_param("s", $q['item']);
        $check->execute();
        $stock = $check->get_result()->fetch_assoc();
        
        if ($stock && $stock['current_stock'] >= $q['quantity_requested']) {
            // 2. Create Sale Record
            $income = ($q['nam_unit_price'] * $q['quantity_requested']) - ($q['suppliers_price'] * $q['quantity_requested']);
            $percent = ($q['nam_unit_price'] > 0) ? ($income / ($q['nam_unit_price'] * $q['quantity_requested'])) * 100 : 0;
            
            $stmt = $conn->prepare("INSERT INTO sales (date, company, category, item, quantity_requested, suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount, income, income_percent, po_number, payment_term, remarks, date_delivered, due_date) VALUES (CURRENT_DATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)");
            
            $total_actual = $q['suppliers_price'] * $q['quantity_requested'];
            $total_nam = $q['nam_unit_price'] * $q['quantity_requested'];
            
            $stmt->bind_param("sssidddddssss", $q['company'], $q['category'], $q['item'], $q['quantity_requested'], $q['suppliers_price'], $total_actual, $q['nam_unit_price'], $total_nam, $income, $percent, $q['po_number'], $q['payment_term'], $q['remarks']);
            
            if ($stmt->execute()) {
                // 3. RESERVE STOCK (Deduct immediately)
                $conn->query("UPDATE products SET current_stock = current_stock - {$q['quantity_requested']} WHERE name = '{$conn->real_escape_string($q['item'])}'");
                
                // 4. Close Quote
                $conn->query("UPDATE quotations SET status = 'Converted' WHERE id = $q_id");
                $msg = "success";
            } else {
                $msg = "error_db";
            }
        } else {
            $msg = "error_stock"; // Not enough stock to reserve
        }
    }
    header("Location: quotations.php?msg=$msg");
    exit;
}

// --- CREATE QUOTE ---
if (isset($_POST['action']) && $_POST['action'] == 'create_quote') {
    $conn = getDBConnection();
    
    // Calculate totals
    $total = $_POST['n_price'] * $_POST['quantity'];
    
    // Fallback for category if JS didn't catch it
    $category = !empty($_POST['category']) ? $_POST['category'] : 'Uncategorized';

    $stmt = $conn->prepare("INSERT INTO quotations (date, company, category, item, quantity_requested, suppliers_price, nam_unit_price, total_amount, po_number, payment_term, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssidddsss", $_POST['date'], $_POST['company'], $category, $_POST['item'], $_POST['quantity'], $_POST['s_price'], $_POST['n_price'], $total, $_POST['po'], $_POST['term'], $_POST['remarks']);
    $stmt->execute();
    header("Location: quotations.php?msg=created");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quotations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .preview-box { border: 2px dashed #ccc; padding: 20px; background: #fff; font-family: 'Courier New', Courier, monospace; }
        .receipt-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .receipt-total { border-top: 1px solid #000; margin-top: 10px; padding-top: 5px; text-align: right; font-weight: bold; }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container-fluid mt-4">
        <div class="row g-4">
            
            <div class="col-lg-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white fw-bold"><i class="fas fa-plus me-2"></i>New Quotation</div>
                    <div class="card-body">
                        <form method="POST" id="quoteForm">
                            <input type="hidden" name="action" value="create_quote">
                            <input type="hidden" name="category" id="categoryField">
                            
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="small text-muted fw-bold">Date</label>
                                    <input type="date" name="date" id="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="small text-muted fw-bold">Client</label>
                                    <input type="text" name="company" id="company" class="form-control" placeholder="Client Name" required>
                                </div>
                                <div class="col-12">
                                    <label class="small text-muted fw-bold">Item (Auto-fills details)</label>
                                    <input type="text" name="item" id="itemInput" list="productList" class="form-control" placeholder="Search Item..." required autocomplete="off">
                                    <datalist id="productList"></datalist>
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted fw-bold">Quantity</label>
                                    <input type="number" name="quantity" id="quantity" class="form-control" placeholder="Qty" required value="1">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted fw-bold">PO Number</label>
                                    <input type="text" name="po" id="po" class="form-control" placeholder="PO #">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted fw-bold">Supplier Price</label>
                                    <input type="number" step="0.01" name="s_price" id="s_price" class="form-control" placeholder="Cost">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted fw-bold text-primary">Selling Price</label>
                                    <input type="number" step="0.01" name="n_price" id="n_price" class="form-control border-primary" placeholder="Price" required>
                                </div>
                                <div class="col-12">
                                    <label class="small text-muted fw-bold">Terms</label>
                                    <input type="text" name="term" id="term" class="form-control" placeholder="Terms (e.g. 30 Days)">
                                </div>
                                <div class="col-12">
                                    <textarea name="remarks" id="remarks" class="form-control" placeholder="Remarks"></textarea>
                                </div>
                                
                                <div class="col-6 mt-3">
                                    <button type="button" class="btn btn-outline-dark w-100" onclick="showPreview()">
                                        <i class="fas fa-eye me-1"></i> Preview
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
                <?php if(isset($_GET['msg']) && $_GET['msg']=='error_stock'): ?>
                    <div class="alert alert-danger">Cannot convert: <b>Insufficient Stock</b>.</div>
                <?php endif; ?>
                
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                        <span>Active Quotations</span>
                        <input type="text" class="form-control form-control-sm w-25" placeholder="Search..." onkeyup="filterTable(this)">
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover mb-0 align-middle" id="mainTable">
                            <thead class="table-light"><tr><th>Date</th><th>Details</th><th class="text-end">Qty</th><th class="text-end">Price</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                                <?php
                                $conn = getDBConnection();
                                $res = $conn->query("SELECT * FROM quotations ORDER BY id DESC");
                                while($row = $res->fetch_assoc()):
                                ?>
                                <tr class="<?= $row['status']=='Converted'?'opacity-50':'' ?>">
                                    <td><?= $row['date'] ?></td>
                                    <td><?= $row['company'] ?><br><small class="text-muted"><?= $row['item'] ?></small></td>
                                    <td class="text-end"><?= $row['quantity_requested'] ?></td>
                                    <td class="text-end"><?= number_format($row['nam_unit_price'],2) ?></td>
                                    <td><span class="badge bg-<?= $row['status']=='Converted'?'success':'secondary' ?>"><?= $row['status'] ?></span></td>
                                    <td class="text-end">
                                        <?php if($row['status'] == 'Pending'): ?>
                                        <form method="POST" onsubmit="return confirm('Convert to Sale? This RESERVES stock.');">
                                            <input type="hidden" name="convert_id" value="<?= $row['id'] ?>">
                                            <button class="btn btn-sm btn-outline-success"><i class="fas fa-check"></i> Convert</button>
                                        </form>
                                        <?php else: ?><i class="fas fa-check text-success"></i><?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Quote Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <div id="receiptContent" class="preview-box shadow-sm">
                        </div>
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

    // 1. Fetch Products for Prefill
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

    // 2. Prefill Logic (Same as Form.php)
    document.getElementById('itemInput').addEventListener('input', function() {
        const p = productMap.get(this.value);
        if (p) {
            // Auto-fill price and cost
            document.getElementById('s_price').value = p.supplier_price;
            document.getElementById('n_price').value = p.nam_price;
            
            // Auto-fill hidden Category
            document.getElementById('categoryField').value = p.category_code || 'General';
            
            // Optional: You could auto-fill a description if you had one
        }
    });

    // 3. Preview Logic
    function showPreview() {
        const d = {
            date: document.getElementById('date').value,
            client: document.getElementById('company').value || 'Unknown Client',
            item: document.getElementById('itemInput').value || 'Item Name',
            qty: document.getElementById('quantity').value || 0,
            price: parseFloat(document.getElementById('n_price').value) || 0,
            po: document.getElementById('po').value,
            term: document.getElementById('term').value,
            remarks: document.getElementById('remarks').value
        };
        
        const total = (d.qty * d.price).toFixed(2);
        
        const html = `
            <div class="receipt-header">
                <h4 class="fw-bold mb-0">NAM SUPPLY</h4>
                <small>Official Quotation</small>
            </div>
            <div class="mb-3">
                <strong>To:</strong> ${d.client}<br>
                <strong>Date:</strong> ${d.date}<br>
                ${d.po ? `<strong>PO #:</strong> ${d.po}<br>` : ''}
                ${d.term ? `<strong>Terms:</strong> ${d.term}<br>` : ''}
            </div>
            <table class="table table-sm table-bordered mb-2">
                <thead class="table-light">
                    <tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Amt</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>${d.item}</td>
                        <td class="text-end">${d.qty}</td>
                        <td class="text-end">${d.price.toFixed(2)}</td>
                        <td class="text-end">${total}</td>
                    </tr>
                </tbody>
            </table>
            <div class="receipt-total">
                TOTAL: PHP ${total}
            </div>
            ${d.remarks ? `<div class="mt-3 small text-muted border-top pt-2"><strong>Notes:</strong> ${d.remarks}</div>` : ''}
            
            <div class="mt-4 text-center small text-muted">
                This is a system generated quotation.<br>Valid for 30 days.
            </div>
        `;
        
        document.getElementById('receiptContent').innerHTML = html;
        const modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();
    }
    
    // 4. Simple Search for Table
    function filterTable(input) {
        const filter = input.value.toLowerCase();
        const trs = document.querySelectorAll('#mainTable tbody tr');
        trs.forEach(tr => {
            const txt = tr.innerText.toLowerCase();
            tr.style.display = txt.includes(filter) ? '' : 'none';
        });
    }
    </script>
</body>
</html>