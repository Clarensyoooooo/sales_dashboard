<?php
require_once 'config.php';

// Increase memory limit and execution time for large files
ini_set('memory_limit', '512M');
set_time_limit(300);

echo "<h1>Importing Sales Data...</h1>";
echo "<pre>";

// List of files to import
$files = [
    'JAN' => 'NAM SUPPLY-SALES ONLY ENCODER - NAM SALE JAN.csv',
    'FEB' => 'NAM SUPPLY-SALES ONLY ENCODER - NAM SALE FEB (2).csv'
];

$conn = getDBConnection();

foreach ($files as $month => $fileName) {
    if (!file_exists($fileName)) {
        echo "⚠️ Warning: File '$fileName' not found. Skipping.\n";
        continue;
    }

    echo "<strong>Processing $month ($fileName)...</strong>\n";
    
    if (($handle = fopen($fileName, "r")) !== FALSE) {
        $row = 0;
        $imported = 0;
        
        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            $row++;
            
            // Skip header row (Row 1) or empty rows
            if ($row <= 1 || empty($data[0])) {
                continue;
            }

            // --- DATA CLEANING & MAPPING ---
            
            // 1. Date Formatting
            $date = date('Y-m-d', strtotime($data[0]));
            
            // 2. Basic Text Fields
            $sn = $data[1] ?? '';
            $po_number = $data[2] ?? '';
            $company = $data[3] ?? '';
            $category = $data[4] ?? '';
            $item = $data[5] ?? '';
            
            // 3. Numbers & Currency
            $quantity = (int) str_replace(',', '', $data[6] ?? 0);
            
            // Helper to clean currency
            $cleanPrice = function($val) {
                return (float) preg_replace('/[₱,\s]/u', '', $val ?? 0);
            };

            $suppliers_price    = $cleanPrice($data[7]);
            $total_actual       = $cleanPrice($data[8]);
            $nam_unit_price     = $cleanPrice($data[9]);
            $total_nam          = $cleanPrice($data[10]);
            $income             = $cleanPrice($data[12]);
            $income_percent     = (float) str_replace('%', '', $data[13] ?? 0);

            // 4. Dates & Optionals
            $date_delivered_raw = $data[15] ?? null;
            $date_delivered = !empty($date_delivered_raw) ? date('Y-m-d', strtotime($date_delivered_raw)) : null;
            
            $payment_term = $data[16] ?? '';
            
            $due_date_raw = $data[17] ?? null;
            $due_date = !empty($due_date_raw) ? date('Y-m-d', strtotime($due_date_raw)) : null;
            
            $si_number = $data[18] ?? '';
            $remarks = $data[19] ?? '';
            $supplier = $data[20] ?? '';
            $address = $data[21] ?? '';
            $tin = $data[22] ?? '';
            
            // Handle column shift between Jan/Feb
            if ($month === 'JAN') {
                $sales_invoice_no = $data[23] ?? '';
                $contact_person   = $data[24] ?? '';
            } else {
                $sales_invoice_no = ''; 
                $contact_person   = $data[23] ?? '';
            }

            // --- DATABASE INSERT ---
            
            $sql = "INSERT INTO sales (
                date, sn, po_number, company, category, item, quantity_requested,
                suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount,
                income, income_percent, date_delivered, payment_term, due_date,
                si_number, remarks, supplier, address, tin, sales_invoice_no, contact_person_contact
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                // FIXED: 'd' is used for all decimal/float fields (money)
                $stmt->bind_param(
                    "ssssssiddddddssssssssss", 
                    $date, $sn, $po_number, $company, $category, $item, $quantity,
                    $suppliers_price, $total_actual, $nam_unit_price, $total_nam,
                    $income, $income_percent, $date_delivered, $payment_term, $due_date,
                    $si_number, $remarks, $supplier, $address, $tin, $sales_invoice_no, $contact_person
                );
                
                if ($stmt->execute()) {
                    $imported++;
                } else {
                    echo "Error inserting row $row: " . $stmt->error . "\n";
                }
                $stmt->close();
            } else {
                echo "Prepare failed: " . $conn->error . "\n";
            }
        }
        fclose($handle);
        echo "✅ Successfully imported $imported records from $fileName.\n\n";
    }
}

echo "</pre>";
echo "<a href='index.php'>Go back to Dashboard</a>";
?>