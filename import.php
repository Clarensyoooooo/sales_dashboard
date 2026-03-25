<?php
// import.php - Centralized Import Hub
require_once 'config.php';
requireLogin();

// Only Super Admins (Role 1) and Admins (Role 4) should access this
if (!in_array($_SESSION['role_id'], [1, 4])) {
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
        
        logAction('Deleted Sales Data', "Deleted $deleted sales records for the month of $monthName $year.");
        
    } elseif ($_POST['action'] == 'truncate_sales') {
        $conn->query("TRUNCATE TABLE sales");
        $message = "All sales data has been permanently cleared.";
        $messageType = "warning";
        $activeTab = 'manage';
        
        logAction('Cleared Sales Data', "WARNING: Truncated ALL sales data in the system.");
        
    } elseif ($_POST['action'] == 'truncate_products') {
        $conn->query("TRUNCATE TABLE products");
        $message = "All inventory products have been permanently cleared.";
        $messageType = "warning";
        $activeTab = 'manage';
        
        logAction('Cleared Inventory Data', "WARNING: Truncated ALL inventory products in the system.");
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
            $imported = 0;
            
            // LOGIC 1: SALES IMPORT (UPGRADED DYNAMIC PARSER)
            if ($importType == 'sales') {
                
                // --- DATE FIX: Smart Date Parser ---
                $parseDate = function($val) {
                    $val = trim($val ?? '');
                    if (empty($val) || $val == '-' || $val == 'N/A') return null;
                    
                    // Format 1: MM/DD/YYYY or YYYY-MM-DD (natively supported by strtotime)
                    $time = strtotime($val); 
                    if ($time) return date('Y-m-d', $time);
                    
                    // Format 2: Fallback for DD/MM/YYYY
                    $d = DateTime::createFromFormat('d/m/Y', $val);
                    if ($d) return $d->format('Y-m-d');
                    
                    return null;
                };

                // --- MONEY FIX: Bulletproof Num Filter (Ignores Pesos and strange encodings) ---
                $cleanPrice = function($val) { 
                    return (float) preg_replace('/[^0-9\.-]/', '', $val ?? '0'); 
                };

                // Read header row first to dynamically map columns
                $headers = fgetcsv($handle, 10000, ",");
                
                // Default fallback map just in case headers are completely missing
                $idx = [
                    'date' => 0, 'sn' => 1, 'po' => 2, 'company' => 3, 'category' => 4,
                    'item' => 5, 'qty' => 6, 's_price' => 7, 't_actual' => 8, 'n_price' => 9,
                    't_nam' => 10, 'income' => 12, 'income_pct' => 13, 'date_del' => 14,
                    'term' => 15, 'due' => 16, 'si' => 17, 'buyer' => 18, 'remarks' => 19,
                    'supplier' => 20, 'address' => 21, 'tin' => 22, 'contact' => 23
                ];
                
                // Dynamically reassign indices based on header names
                if ($headers) {
                    foreach($headers as $i => $col) {
                        $col = strtoupper(trim($col));
                        if($col === 'DATE') $idx['date'] = $i;
                        if(strpos($col, 'S/N') !== false) $idx['sn'] = $i;
                        if(strpos($col, 'PO NUMBER') !== false) $idx['po'] = $i;
                        if(strpos($col, 'COMPANY') !== false) $idx['company'] = $i;
                        if(strpos($col, 'CATEGORY') !== false) $idx['category'] = $i;
                        if($col === 'ITEM') $idx['item'] = $i;
                        if(strpos($col, 'QUANTITY') !== false) $idx['qty'] = $i;
                        if(strpos($col, 'SUPPLIER') !== false && strpos($col, 'PRICE') !== false) $idx['s_price'] = $i;
                        if(strpos($col, 'ACTUAL AMOUNT') !== false) $idx['t_actual'] = $i;
                        if(strpos($col, 'NAM UNIT PRICE') !== false) $idx['n_price'] = $i;
                        if(strpos($col, 'TOTAL NAM AMOUNT') !== false && strpos($col, 'SUB') === false) $idx['t_nam'] = $i;
                        if($col === 'INCOME') $idx['income'] = $i;
                        if(strpos($col, 'PERCENT') !== false) $idx['income_pct'] = $i;
                        if(strpos($col, 'DELIVERED') !== false) $idx['date_del'] = $i;
                        if(strpos($col, 'TERM') !== false) $idx['term'] = $i;
                        if(strpos($col, 'DUE') !== false) $idx['due'] = $i;
                        if(strpos($col, 'SI NUMBER') !== false) $idx['si'] = $i;
                        if($col === 'BUYER') $idx['buyer'] = $i;
                        if(strpos($col, 'REMARKS') !== false) $idx['remarks'] = $i;
                        if($col === 'SUPPLIER') $idx['supplier'] = $i;
                        if(strpos($col, 'ADDRESS') !== false) $idx['address'] = $i;
                        if(strpos($col, 'TIN') !== false) $idx['tin'] = $i;
                        if(strpos($col, 'CONTACT PERSON') !== false) $idx['contact'] = $i;
                    }
                }

                $row = 1; // Start at 1 because we consumed the header
                while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    $row++;
                    // Skip empty rows (blank date check)
                    if (empty(trim($data[$idx['date']] ?? ''))) continue; 

                    // Extract data using our dynamic indices
                    $date = $parseDate($data[$idx['date']]) ?? date('Y-m-d');
                    
                    $sn = $data[$idx['sn']] ?? '';
                    $po_number = $data[$idx['po']] ?? '';
                    $company = $data[$idx['company']] ?? '';
                    $category = $data[$idx['category']] ?? '';
                    $item = $data[$idx['item']] ?? '';
                    $quantity = (int) str_replace(',', '', $data[$idx['qty']] ?? 0);
                    
                    $suppliers_price = $cleanPrice($data[$idx['s_price']] ?? 0);
                    $total_actual    = $cleanPrice($data[$idx['t_actual']] ?? 0);
                    $nam_unit_price  = $cleanPrice($data[$idx['n_price']] ?? 0);
                    $total_nam       = $cleanPrice($data[$idx['t_nam']] ?? 0);
                    
                    $income          = $cleanPrice($data[$idx['income']] ?? 0);
                    $income_percent  = (float) preg_replace('/[^0-9\.-]/', '', $data[$idx['income_pct']] ?? 0);
                    
                    $date_delivered = $parseDate($data[$idx['date_del']] ?? null);
                    $payment_term = $data[$idx['term']] ?? '';
                    $due_date = $parseDate($data[$idx['due']] ?? null);
                    
                    $si_number = $data[$idx['si']] ?? '';
                    $buyer = $data[$idx['buyer']] ?? ''; 
                    $remarks = $data[$idx['remarks']] ?? '';
                    $supplier = $data[$idx['supplier']] ?? '';
                    $address = $data[$idx['address']] ?? '';
                    $tin = $data[$idx['tin']] ?? '';
                    $contact_person   = $data[$idx['contact']] ?? '';
                    
                    $sales_invoice_no = '';

                    $sql = "INSERT INTO sales (
                        date, sn, po_number, company, category, item, quantity_requested,
                        suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount,
                        income, income_percent, date_delivered, payment_term, due_date,
                        si_number, buyer, remarks, supplier, address, tin, sales_invoice_no, contact_person_contact
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssssssiddddddsssssssssss", $date, $sn, $po_number, $company, $category, $item, $quantity, $suppliers_price, $total_actual, $nam_unit_price, $total_nam, $income, $income_percent, $date_delivered, $payment_term, $due_date, $si_number, $buyer, $remarks, $supplier, $address, $tin, $sales_invoice_no, $contact_person);
                        
                        if ($stmt->execute()) {
                            $imported++;
                            $importLogs[] = "<span class='text-success'>[Row $row]</span> Inserted Sale: $company - <strong>$item</strong> (Qty: $quantity)";
                        } else {
                            $importLogs[] = "<span class='text-danger'>[Row $row]</span> Failed to insert $item: " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $importLogs[] = "<span class='text-danger'>[Row $row]</span> SQL Prepare Error (Sales): " . $conn->error;
                    }
                }
            }
            
            // LOGIC 2: PRICES IMPORT
            elseif ($importType == 'prices') {
                $categoryMap = [
                    'CM' => 'CLEANING MATERIALS',
                    'CO' => 'CONSUMABLES',
                    'CU' => 'COMPANY UNIFORM',
                    'FF' => 'OFFICE FURNITURE & FIXTURES',
                    'MA' => 'MATERIALS',
                    'MD' => 'MEDICINE',
                    'OS' => 'OFFICE SUPPLIES',
                    'PPE' => 'PPE',
                    'TE' => 'OFFICE TOOLS AND EQUIPMENT'
                ];

                $cleanPrice = function($val) { 
                    return (float) preg_replace('/[^0-9\.-]/', '', $val ?? '0'); 
                };

                $row = 0;
                while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    $row++;
                    if ($row <= 2 || empty(trim($data[0]))) continue; 

                    $name = trim($data[0]);
                    $unit = trim($data[1] ?? '');

                    $raw_category = trim($data[2] ?? 'General');
                    if (empty($raw_category)) $raw_category = 'General';
                    $category_code = isset($categoryMap[$raw_category]) ? $categoryMap[$raw_category] : $raw_category;
                    
                    $supplier = trim($data[3] ?? '');

                    $supplier_price = $cleanPrice($data[4] ?? 0);
                    $nam_price = $cleanPrice($data[5] ?? 0);
                    $margin = trim($data[6] ?? '');
                    $inventory = (int) trim($data[7] ?? 0);
                    
                    $sql = "INSERT INTO products (name, category_code, unit, supplier, supplier_price, nam_price, margin, current_stock, reorder_level) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 10) 
                            ON DUPLICATE KEY UPDATE 
                            category_code = VALUES(category_code),
                            unit = VALUES(unit),
                            supplier = VALUES(supplier),
                            supplier_price = VALUES(supplier_price), 
                            nam_price = VALUES(nam_price),
                            margin = VALUES(margin),
                            current_stock = VALUES(current_stock)";
                            
                    $stmt = $conn->prepare($sql);
                    
                    if ($stmt) {
                        $stmt->bind_param("ssssddsi", $name, $category_code, $unit, $supplier, $supplier_price, $nam_price, $margin, $inventory);
                        if ($stmt->execute()) {
                            $imported++;
                            $importLogs[] = "<span class='text-primary'>[Row $row]</span> Updated Inventory: <strong>$name</strong> (SRP: ₱$nam_price | Stock: $inventory)";
                        } else {
                            $importLogs[] = "<span class='text-danger'>[Row $row]</span> Error executing update for $name: " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $importLogs[] = "<span class='text-danger'>[Row $row]</span> SQL Prepare Error (Products): " . $conn->error;
                    }
                }
            }

            fclose($handle);
            $message = "Import Complete! Processed $imported records.";
            $messageType = "success";
            
            if ($imported > 0) {
                if ($importType == 'sales') {
                    logAction('Imported Sales Data', "Successfully bulk-imported $imported sales records via CSV.");
                } elseif ($importType == 'prices') {
                    logAction('Imported Inventory Data', "Successfully bulk-imported/updated $imported products via CSV.");
                }
            }
        } else {
            $message = "Could not open file.";
            $messageType = "danger";
        }
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
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