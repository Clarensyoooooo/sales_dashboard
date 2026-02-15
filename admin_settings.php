<?php
require_once 'config.php';
requireLogin();

// SECURITY: Only Super Admin (Role 1) can access this page
if ($_SESSION['role_id'] != 1) {
    die("<div style='padding:20px; color:red; font-family:sans-serif;'>⛔ Access Denied: Administrator privileges required. <a href='index.php'>Back</a></div>");
}

$conn = getDBConnection();
$message = '';
$msg_type = ''; // success or error

// --- HANDLE ACTIONS ---

// 1. CLEAR DATA
if (isset($_POST['action']) && $_POST['action'] === 'clear_data') {
    // Disable foreign key checks just in case
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // TRUNCATE is faster and resets ID to 1
    $sql = "TRUNCATE TABLE sales";
    
    if ($conn->query($sql)) {
        // Optional: Also clear products if requested? 
        // For now, we only clear sales as requested to "remove data and import again"
        $message = "✅ All Sales Records have been wiped successfully.";
        $msg_type = "success";
    } else {
        $message = "❌ Error clearing data: " . $conn->error;
        $msg_type = "error";
    }
    
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
}

// 2. IMPORT CSV
if (isset($_POST['action']) && $_POST['action'] === 'import_csv') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        
        if ($handle !== FALSE) {
            // Get Headers
            $headers = fgetcsv($handle, 1000, ",");
            
            // Map CSV Headers to DB Columns (Based on your file structure)
            // We normalize headers: trim spaces, uppercase
            $headerMap = [];
            foreach ($headers as $index => $col) {
                $cleanCol = trim(strtoupper($col));
                if (!empty($cleanCol)) {
                    $headerMap[$cleanCol] = $index;
                }
            }

            // Prepare Statement
            $stmt = $conn->prepare("INSERT INTO sales (
                date, sn, po_number, company, category, item, quantity_requested, 
                suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount, 
                income, income_percent, date_delivered, payment_term, due_date, 
                si_number, remarks, supplier, address, tin, sales_invoice_no, contact_person_contact
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $count = 0;
            $errors = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                try {
                    // Helper to get data by column name safely
                    $get = function($colName) use ($data, $headerMap) {
                        $idx = $headerMap[$colName] ?? -1;
                        if ($idx === -1) return null;
                        return isset($data[$idx]) ? trim($data[$idx]) : null;
                    };

                    // --- DATA CLEANING ---
                    
                    // 1. Dates (CSV is MM/DD/YYYY -> DB requires YYYY-MM-DD)
                    $date = date('Y-m-d', strtotime($get('DATE')));
                    $date_del = $get('DATE DELIVERED') ? date('Y-m-d', strtotime($get('DATE DELIVERED'))) : null;
                    $due_date = $get('DUE DATE') ? date('Y-m-d', strtotime($get('DUE DATE'))) : null;

                    // 2. Numbers (Remove '₱', ',', and spaces)
                    $cleanNum = function($val) {
                        return (float) preg_replace('/[^\d.-]/', '', $val);
                    };

                    $qty = (int) str_replace(',', '', $get('QUANTITY REQUESTED'));
                    $s_price = $cleanNum($get("SUPPLIER'S PRICE"));
                    $tot_act = $cleanNum($get("TOTAL ACTUAL AMOUNT"));
                    $n_price = $cleanNum($get("NAM UNIT PRICE"));
                    $tot_nam = $cleanNum($get("TOTAL NAM AMOUNT"));
                    $income  = $cleanNum($get("INCOME"));
                    $inc_pct = $cleanNum($get("INCOME PERCENT")); // Will be 44 from "44%"

                    // Bind Parameters
                    $stmt->bind_param("ssssssidddddsssssssssss", 
                        $date,
                        $get('S/N'),
                        $get('PO NUMBER'),
                        $get('COMPANY'),
                        $get('CATEGORY'),
                        $get('ITEM'),
                        $qty,
                        $s_price,
                        $tot_act,
                        $n_price,
                        $tot_nam,
                        $income,
                        $inc_pct,
                        $date_del,
                        $get('PAYMENT TERM'),
                        $due_date,
                        $get('SI NUMBER'),
                        $get('REMARKS'),
                        $get('SUPPLIER'),
                        $get('ADDRESS'),
                        $get('TIN'),
                        $get('SALES INVOICE NO.'),
                        $get('CONTACT PERSON/CONTACT #')
                    );

                    if ($stmt->execute()) {
                        $count++;
                    } else {
                        $errors++;
                    }

                } catch (Exception $e) {
                    $errors++;
                }
            }
            
            fclose($handle);
            $message = "✅ Import Complete! $count records added. ($errors skipped/failed)";
            $msg_type = "success";
            
        } else {
            $message = "❌ Error opening CSV file.";
            $msg_type = "error";
        }
    } else {
        $message = "❌ Please select a valid CSV file.";
        $msg_type = "error";
    }
}

// Get current stats
$res = $conn->query("SELECT COUNT(*) as c FROM sales");
$total_sales = $res->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - NAM Supply</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; color: #1f2937; margin: 0; }
        .container { max-width: 1000px; margin: 30px auto; padding: 20px; }
        
        .header-box { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-size: 24px; font-weight: 700; color: #1e40af; }
        
        .card { background: white; border-radius: 12px; padding: 30px; margin-bottom: 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .card-title { font-size: 18px; font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .card-desc { color: #6b7280; font-size: 14px; margin-bottom: 20px; line-height: 1.5; }
        
        .btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; font-size: 14px; transition: all 0.2s; }
        .btn-danger { background: #fee2e2; color: #991b1b; }
        .btn-danger:hover { background: #fecaca; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .file-input-wrapper { display: flex; gap: 10px; align-items: center; }
        input[type="file"] { border: 1px solid #e5e7eb; padding: 10px; border-radius: 8px; width: 100%; }
        
        .stat-badge { background: #eff6ff; color: #2563eb; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="header-box">
            <div class="page-title">⚙️ Data Management</div>
            <div class="stat-badge">Current Records: <?php echo number_format($total_sales); ?></div>
        </div>

        <?php if($message): ?>
            <div class="alert alert-<?php echo $msg_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title">📥 Import Sales Data</div>
            <div class="card-desc">
                Upload your monthly sales CSV file here. The system will automatically:
                <ul style="margin-top:5px; padding-left:20px;">
                    <li>Detect column headers automatically.</li>
                    <li>Clean currency symbols (₱, commas).</li>
                    <li>Format dates correctly for the database.</li>
                </ul>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_csv">
                <div class="file-input-wrapper">
                    <input type="file" name="csv_file" accept=".csv" required>
                    <button type="submit" class="btn btn-primary">Upload & Import</button>
                </div>
            </form>
        </div>

        <div class="card" style="border: 1px solid #fecaca;">
            <div class="card-title" style="color: #b91c1c;">⚠️ Danger Zone</div>
            <div class="card-desc">
                <strong>Reset Database:</strong> This will permanently delete <u>ALL</u> sales records. 
                User accounts and logins will NOT be deleted. 
                Use this before importing a fresh master list.
            </div>
            
            <form method="POST" onsubmit="return confirm('Are you strictly sure? This will WIPE ALL SALES DATA. This cannot be undone.');">
                <input type="hidden" name="action" value="clear_data">
                <button type="submit" class="btn btn-danger">🗑️ Clear All Sales Records</button>
            </form>
        </div>

    </div>

</body>
</html>