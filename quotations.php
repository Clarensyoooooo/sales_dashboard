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
    $total = $_POST['n_price'] * $_POST['quantity'];
    $stmt = $conn->prepare("INSERT INTO quotations (date, company, category, item, quantity_requested, suppliers_price, nam_unit_price, total_amount, po_number, payment_term, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssidddsss", $_POST['date'], $_POST['company'], $_POST['category'], $_POST['item'], $_POST['quantity'], $_POST['s_price'], $_POST['n_price'], $total, $_POST['po'], $_POST['term'], $_POST['remarks']);
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
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container-fluid mt-4">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white fw-bold"><i class="fas fa-plus me-2"></i>New Quotation</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_quote">
                            <div class="row g-2">
                                <div class="col-12"><input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                                <div class="col-12"><input type="text" name="company" class="form-control" placeholder="Client Name" required></div>
                                <div class="col-12"><input type="text" name="item" class="form-control" placeholder="Item Name" required></div>
                                <div class="col-6"><input type="number" name="quantity" class="form-control" placeholder="Qty" required></div>
                                <div class="col-6"><input type="text" name="po" class="form-control" placeholder="PO #"></div>
                                <div class="col-6"><input type="number" step="0.01" name="s_price" class="form-control" placeholder="Supplier Price"></div>
                                <div class="col-6"><input type="number" step="0.01" name="n_price" class="form-control" placeholder="NAM Price" required></div>
                                <div class="col-12"><input type="text" name="term" class="form-control" placeholder="Terms (e.g. 30 Days)"></div>
                                <div class="col-12"><textarea name="remarks" class="form-control" placeholder="Remarks"></textarea></div>
                                <div class="col-12 mt-2"><button class="btn btn-primary w-100">Save Quote</button></div>
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
                    <div class="card-header bg-white fw-bold">Active Quotations</div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover mb-0 align-middle">
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
</body>
</html>