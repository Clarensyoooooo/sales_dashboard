<?php
// import.php - Centralized Import Hub
require_once 'config.php';
requireLogin();

// Only Admins (Role 1) should access this
if ($_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit();
}

$message = "";
$messageType = "";
$activeTab = 'sales'; // Default tab
$importLogs = []; // Array to hold detailed log messages

$conn = getDBConnection();

// ==========================================
// HANDLER: DATA MANAGEMENT (DELETE / TRUNCATE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'delete_month') {
        $month = intval($_POST['del_month']);
        $year = intval($_POST['del_year']);
        $stmt = $conn->prepare("DELETE FROM sales WHERE MONTH(date) = ? AND YEAR(date) = ?");
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        
        $monthName = date("F", mktime(0, 0, 0, $month, 10));
        $message = "Successfully deleted <strong>$deleted</strong> sales records from $monthName $year.";
        $messageType = "success";
        $activeTab = 'manage';
        
    } elseif ($_POST['action'] == 'truncate_sales') {
        $conn->query("TRUNCATE TABLE sales");
        $message = "All sales data has been permanently cleared.";
        $messageType = "warning";
        $activeTab = 'manage';
        
    } elseif ($_POST['action'] == 'truncate_products') {
        $conn->query("TRUNCATE TABLE products");
        $message = "All inventory products have been permanently cleared.";
        $messageType = "warning";
        $activeTab = 'manage';
    }
}

// ==========================================
// HANDLER: IMPORT FORM SUBMISSION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $importType = $_POST['import_type']; 
    $activeTab = $importType; 

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "File upload failed. Error code: " . $file['error'];
        $messageType = "danger";
    } elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        $message = "Please upload a valid CSV file.";
        $messageType = "warning";
    } else {
        $handle = fopen($file['tmp_name'], "r");
        if ($handle !== FALSE) {
            $row = 0;
            $imported = 0;
            
            // LOGIC 1: SALES IMPORT
            if ($importType == 'sales') {
                while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    $row++;
                    if ($row <= 1 || empty($data[0])) continue; // Skip header

                    $cleanPrice = function($val) { return (float) preg_replace('/[₱,\s]/u', '', $val ?? 0); };

                    $date = date('Y-m-d', strtotime($data[0]));
                    $sn = $data[1] ?? '';
                    $po_number = $data[2] ?? '';
                    $company = $data[3] ?? '';
                    $category = $data[4] ?? '';
                    $item = $data[5] ?? '';
                    $quantity = (int) str_replace(',', '', $data[6] ?? 0);
                    $suppliers_price = $cleanPrice($data[7]);
                    $total_actual    = $cleanPrice($data[8]);
                    $nam_unit_price  = $cleanPrice($data[9]);
                    $total_nam       = $cleanPrice($data[10]);
                    $income          = $cleanPrice($data[12]);
                    $income_percent  = (float) str_replace('%', '', $data[13] ?? 0);
                    
                    $date_delivered = !empty($data[15]) ? date('Y-m-d', strtotime($data[15])) : null;
                    $payment_term = $data[16] ?? '';
                    $due_date = !empty($data[17]) ? date('Y-m-d', strtotime($data[17])) : null;
                    $si_number = $data[18] ?? '';
                    $remarks = $data[19] ?? '';
                    $supplier = $data[20] ?? '';
                    $address = $data[21] ?? '';
                    $tin = $data[22] ?? '';

                    if (isset($data[24])) {
                        $sales_invoice_no = $data[23];
                        $contact_person   = $data[24];
                    } else {
                        $sales_invoice_no = '';
                        $contact_person   = $data[23] ?? '';
                    }

                    $sql = "INSERT INTO sales (
                        date, sn, po_number, company, category, item, quantity_requested,
                        suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount,
                        income, income_percent, date_delivered, payment_term, due_date,
                        si_number, remarks, supplier, address, tin, sales_invoice_no, contact_person_contact
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssssssiddddddssssssssss", $date, $sn, $po_number, $company, $category, $item, $quantity, $suppliers_price, $total_actual, $nam_unit_price, $total_nam, $income, $income_percent, $date_delivered, $payment_term, $due_date, $si_number, $remarks, $supplier, $address, $tin, $sales_invoice_no, $contact_person);
                        
                        if ($stmt->execute()) {
                            $imported++;
                            $importLogs[] = "<span class='text-success'>[Row $row]</span> Inserted Sale: $company - <strong>$item</strong> (Qty: $quantity)";
                        } else {
                            $importLogs[] = "<span class='text-danger'>[Row $row]</span> Failed to insert $item: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
            }
            
            // LOGIC 2: PRICES IMPORT
            elseif ($importType == 'prices') {
                while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    $row++;
                    if ($row <= 1 || empty($data[1])) continue; 

                    $category = $data[0] ?? 'General';
                    $name = $data[1] ?? '';
                    $cleanPrice = function($val) { return (float) preg_replace('/[₱,\s]/u', '', $val ?? 0); };
                    $supplier_price = $cleanPrice($data[2] ?? 0);
                    $unit_price = $cleanPrice($data[3] ?? 0);
                    
                    $sql = "INSERT INTO products (category, name, supplier_price, unit_price) 
                            VALUES (?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE 
                            supplier_price = VALUES(supplier_price), 
                            unit_price = VALUES(unit_price)";
                            
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssdd", $category, $name, $supplier_price, $unit_price);
                        if ($stmt->execute()) {
                            $imported++;
                            $importLogs[] = "<span class='text-primary'>[Row $row]</span> Updated Inventory: <strong>$name</strong> (SRP: ₱$unit_price)";
                        }
                        $stmt->close();
                    }
                }
            }

            fclose($handle);
            $message = "Import Complete! Processed $imported records.";
            $messageType = "success";
        } else {
            $message = "Could not open file.";
            $messageType = "danger";
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Data & Import Hub - NAM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container mt-5 pb-5">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-database me-2"></i>Data & Import Hub</h4>
                </div>
                <div class="card-body">
                    
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show shadow-sm">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <ul class="nav nav-tabs nav-fill mb-4" id="importTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab == 'sales' ? 'active' : ''; ?>" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button" role="tab"><i class="fas fa-shopping-cart me-2"></i>Import Sales</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab == 'prices' ? 'active' : ''; ?>" id="prices-tab" data-bs-toggle="tab" data-bs-target="#prices" type="button" role="tab"><i class="fas fa-tags me-2"></i>Import Prices</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab == 'manage' ? 'active text-danger fw-bold' : 'text-danger'; ?>" id="manage-tab" data-bs-toggle="tab" data-bs-target="#manage" type="button" role="tab"><i class="fas fa-trash-alt me-2"></i>Data Management</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="importTabsContent">
                        
                        <div class="tab-pane fade <?php echo $activeTab == 'sales' ? 'show active' : ''; ?>" id="sales" role="tabpanel">
                            <form action="" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="import_type" value="sales">
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-muted">Select Sales CSV File</label>
                                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 fw-bold">Import Sales Data</button>
                            </form>
                        </div>

                        <div class="tab-pane fade <?php echo $activeTab == 'prices' ? 'show active' : ''; ?>" id="prices" role="tabpanel">
                            <form action="" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="import_type" value="prices">
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-muted">Select Price List CSV File</label>
                                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                </div>
                                <button type="submit" class="btn btn-warning w-100 fw-bold">Import & Update Inventory</button>
                            </form>
                        </div>

                        <div class="tab-pane fade <?php echo $activeTab == 'manage' ? 'show active' : ''; ?>" id="manage" role="tabpanel">
                            <div class="alert alert-danger bg-opacity-10">
                                <i class="fas fa-exclamation-triangle me-2"></i> <strong>Warning:</strong> Actions here are permanent. Use these tools to clean up test data before launching.
                            </div>
                            
                            <div class="card border-secondary-subtle mb-3">
                                <div class="card-header bg-light fw-bold">Delete Sales by Month</div>
                                <div class="card-body">
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete ALL sales for this specific month?');">
                                        <input type="hidden" name="action" value="delete_month">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-5">
                                                <select name="del_month" class="form-select" required>
                                                    <?php for($m=1; $m<=12; $m++): ?>
                                                        <option value="<?= $m ?>"><?= date('F', mktime(0,0,0,$m,10)) ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <input type="number" name="del_year" class="form-control" value="<?= date('Y') ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <button type="submit" class="btn btn-outline-danger w-100">Delete</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <form method="POST" onsubmit="return confirm('WARNING! This will WIPE ALL SALES RECORDS. Type YES in your head before clicking OK.');">
                                        <input type="hidden" name="action" value="truncate_sales">
                                        <button type="submit" class="btn btn-danger w-100 fw-bold"><i class="fas fa-skull-crossbones me-2"></i>Clear ALL Sales Data</button>
                                    </form>
                                </div>
                                <div class="col-md-6">
                                    <form method="POST" onsubmit="return confirm('WARNING! This will WIPE YOUR ENTIRE INVENTORY. Are you sure?');">
                                        <input type="hidden" name="action" value="truncate_products">
                                        <button type="submit" class="btn btn-danger w-100 fw-bold"><i class="fas fa-skull-crossbones me-2"></i>Clear ALL Products</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <?php if (!empty($importLogs)): ?>
            <div class="card mt-4 shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0"><i class="fas fa-terminal me-2"></i>Import Execution Log</h6>
                </div>
                <div class="card-body bg-light p-0">
                    <div style="max-height: 350px; overflow-y: auto; padding: 15px; font-family: monospace; font-size: 13px;">
                        <?php foreach($importLogs as $log): ?>
                            <div class="border-bottom border-secondary-subtle pb-1 mb-1">
                                <?= $log ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>