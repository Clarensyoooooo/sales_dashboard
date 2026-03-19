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
            $row = 0;
            $imported = 0;
            
            // LOGIC 1: SALES IMPORT
            if ($importType == 'sales') {
                
                // --- DATE FIX: Helper function to force DD/MM/YYYY formatting ---
                $parseDate = function($val) {
                    $val = trim($val ?? '');
                    if (empty($val) || $val == '-' || $val == 'N/A') return null;
                    
                    // Priority 1: DD/MM/YYYY (This is what your Excel outputs)
                    $d = DateTime::createFromFormat('d/m/Y', $val);
                    if ($d) return $d->format('Y-m-d');
                    
                    // Priority 2: Fallback to standard
                    $time = strtotime(str_replace('/', '-', $val));
                    return $time ? date('Y-m-d', $time) : null;
                };

                while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    $row++;
                    // Skip header and empty rows (like those blank subtotal spacer rows)
                    if ($row <= 1 || empty(trim($data[0]))) continue; 

                    $cleanPrice = function($val) { return (float) preg_replace('/[₱,\s]/u', '', $val ?? 0); };

                    // Parse exact dates using the helper
                    $date = $parseDate($data[0]) ?? date('Y-m-d');
                    
                    $sn = $data[1] ?? '';
                    $po_number = $data[2] ?? '';
                    $company = $data[3] ?? '';
                    $category = $data[4] ?? '';
                    $item = $data[5] ?? '';
                    $quantity = (int) str_replace(',', '', $data[6] ?? 0);
                    $suppliers_price = $cleanPrice($data[7] ?? 0);
                    $total_actual    = $cleanPrice($data[8] ?? 0);
                    $nam_unit_price  = $cleanPrice($data[9] ?? 0);
                    $total_nam       = $cleanPrice($data[10] ?? 0);
                    
                    // index 11 is "TOTAL NAM AMOUNT SUB TOTAL" - We skip this!
                    
                    $income          = $cleanPrice($data[12] ?? 0);
                    $income_percent  = (float) str_replace('%', '', $data[13] ?? 0);
                    
                    // Parse delivery and due dates correctly
                    $date_delivered = $parseDate($data[14]);
                    $payment_term = $data[15] ?? '';
                    $due_date = $parseDate($data[16]);
                    
                    $si_number = $data[17] ?? '';
                    $buyer = $data[18] ?? ''; 
                    $remarks = $data[19] ?? '';
                    $supplier = $data[20] ?? '';
                    $address = $data[21] ?? '';
                    $tin = $data[22] ?? '';
                    $contact_person   = $data[23] ?? '';
                    
                    // Sales invoice is missing from this sheet format, so default empty
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

                while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    $row++;
                    if ($row <= 2 || empty(trim($data[0]))) continue; 

                    $name = trim($data[0]);
                    $unit = trim($data[1] ?? '');

                    $raw_category = trim($data[2] ?? 'General');
                    if (empty($raw_category)) $raw_category = 'General';
                    $category_code = isset($categoryMap[$raw_category]) ? $categoryMap[$raw_category] : $raw_category;
                    
                    $supplier = trim($data[3] ?? '');

                    $cleanPrice = function($val) { return (float) preg_replace('/[₱,\s]/u', '', $val ?? 0); };
                    
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