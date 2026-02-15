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

// --- HANDLER: IMPORT FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $conn = getDBConnection();
    $file = $_FILES['csv_file'];
    $importType = $_POST['import_type']; // 'sales' or 'prices'
    $activeTab = $importType; // Keep the correct tab open after reload

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
            
            // ==========================================
            // LOGIC 1: SALES IMPORT (Jan/Feb Files)
            // ==========================================
            if ($importType == 'sales') {
                while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    $row++;
                    if ($row <= 1 || empty($data[0])) continue; // Skip header

                    // Helper: Clean Price
                    $cleanPrice = function($val) { return (float) preg_replace('/[₱,\s]/u', '', $val ?? 0); };

                    // Map Data
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

                    // Handle Column Shift (Jan vs Feb)
                    if (isset($data[24])) {
                        $sales_invoice_no = $data[23];
                        $contact_person   = $data[24];
                    } else {
                        $sales_invoice_no = '';
                        $contact_person   = $data[23] ?? '';
                    }

                    // Insert
                    $sql = "INSERT INTO sales (
                        date, sn, po_number, company, category, item, quantity_requested,
                        suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount,
                        income, income_percent, date_delivered, payment_term, due_date,
                        si_number, remarks, supplier, address, tin, sales_invoice_no, contact_person_contact
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param(
                            "ssssssiddddddssssssssss", 
                            $date, $sn, $po_number, $company, $category, $item, $quantity,
                            $suppliers_price, $total_actual, $nam_unit_price, $total_nam,
                            $income, $income_percent, $date_delivered, $payment_term, $due_date,
                            $si_number, $remarks, $supplier, $address, $tin, $sales_invoice_no, $contact_person
                        );
                        if ($stmt->execute()) $imported++;
                        $stmt->close();
                    }
                }
            }
            
            // ==========================================
            // LOGIC 2: PRICES IMPORT (Inventory/Products)
            // ==========================================
            elseif ($importType == 'prices') {
                // Clear existing products first? (Optional - uncomment if you want to wipe table first)
                // $conn->query("TRUNCATE TABLE products");

                while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    $row++;
                    if ($row <= 1 || empty($data[1])) continue; // Skip header

                    // --- ADJUST THESE INDEXES BASED ON YOUR PRICE CSV ---
                    // Assuming format: Category | Item Name | Supplier Price | SRP/Unit Price
                    $category = $data[0] ?? 'General';
                    $name = $data[1] ?? '';
                    
                    // Clean Prices
                    $cleanPrice = function($val) { return (float) preg_replace('/[₱,\s]/u', '', $val ?? 0); };
                    $supplier_price = $cleanPrice($data[2] ?? 0);
                    $unit_price = $cleanPrice($data[3] ?? 0);
                    
                    // Simple Insert or Update
                    // This uses ON DUPLICATE KEY UPDATE to avoid duplicates
                    $sql = "INSERT INTO products (category, name, supplier_price, unit_price) 
                            VALUES (?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE 
                            supplier_price = VALUES(supplier_price), 
                            unit_price = VALUES(unit_price)";
                            
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssdd", $category, $name, $supplier_price, $unit_price);
                        if ($stmt->execute()) $imported++;
                        $stmt->close();
                    }
                }
            }

            fclose($handle);
            $message = "Success! Imported $imported records into " . strtoupper($importType) . ".";
            $messageType = "success";
        } else {
            $message = "Could not open file.";
            $messageType = "danger";
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Import Data Hub - NAM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-database me-2"></i>Data Import Hub</h4>
                </div>
                <div class="card-body">
                    
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <ul class="nav nav-tabs nav-fill mb-4" id="importTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab == 'sales' ? 'active' : ''; ?>" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button" role="tab">
                                <i class="fas fa-shopping-cart me-2"></i>Import Sales (Jan/Feb)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab == 'prices' ? 'active' : ''; ?>" id="prices-tab" data-bs-toggle="tab" data-bs-target="#prices" type="button" role="tab">
                                <i class="fas fa-tags me-2"></i>Import Prices
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="importTabsContent">
                        
                        <div class="tab-pane fade <?php echo $activeTab == 'sales' ? 'show active' : ''; ?>" id="sales" role="tabpanel">
                            <div class="alert alert-info">
                                <small><i class="fas fa-info-circle"></i> Upload <strong>NAM SALE JAN.csv</strong> or <strong>NAM SALE FEB.csv</strong> files here.</small>
                            </div>
                            <form action="" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="import_type" value="sales">
                                <div class="mb-3">
                                    <label class="form-label">Select Sales CSV File</label>
                                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Import Sales Data</button>
                            </form>
                        </div>

                        <div class="tab-pane fade <?php echo $activeTab == 'prices' ? 'show active' : ''; ?>" id="prices" role="tabpanel">
                            <div class="alert alert-warning">
                                <small><i class="fas fa-exclamation-triangle"></i> This will add/update products in your inventory.</small>
                            </div>
                            <form action="" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="import_type" value="prices">
                                <div class="mb-3">
                                    <label class="form-label">Select Price List CSV File</label>
                                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                </div>
                                <button type="submit" class="btn btn-warning w-100">Import Prices</button>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>