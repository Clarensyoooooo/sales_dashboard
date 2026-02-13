<?php
// submit_batch.php
header('Content-Type: application/json');
require_once 'config.php';

// Disable display_errors to prevent HTML from breaking JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Get JSON input
$input = file_get_contents('php://input');
$entries = json_decode($input, true);

if (!$entries || empty($entries)) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

$conn = getDBConnection();

// Start Transaction
$conn->begin_transaction();

try {
    // 1. Prepare INSERT Statement
    // We have exactly 23 columns here
    $sql = "INSERT INTO sales (
        date, sn, po_number, company, category, item, quantity_requested,
        suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount,
        income, income_percent, date_delivered, payment_term, due_date, si_number,
        remarks, supplier, address, tin, sales_invoice_no, contact_person_contact
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    
    // 2. Prepare Inventory Update Statement
    $updateStock = $conn->prepare("UPDATE products SET current_stock = current_stock - ? WHERE name = ?");

    foreach ($entries as $entry) {
        // --- CALCULATION LOGIC ---
        $qty = intval($entry['quantity_requested']);
        $s_price = floatval($entry['suppliers_price']);
        $n_price = floatval($entry['nam_unit_price']);
        
        $total_actual = $s_price * $qty;
        $total_nam = $n_price * $qty;
        $income = $total_nam - $total_actual;
        $income_percent = ($total_nam > 0) ? ($income / $total_nam) * 100 : 0;

        // --- BINDING PARAMETERS ---
        // The type string MUST be exactly 23 characters long to match the 23 ?s
        // s = string, i = integer, d = double (float)
        
        // 1-6 (Strings): date, sn, po, company, category, item
        // 7 (Int): quantity
        // 8-13 (Doubles): s_price, tot_actual, n_price, tot_nam, income, income_pct
        // 14-23 (Strings): dates, terms, remarks, supplier, etc.
        
        $types = "ssssssiddddddssssssssss"; // EXACTLY 23 CHARACTERS
        
        $stmt->bind_param(
            $types,
            $entry['date'], 
            $entry['sn'], 
            $entry['po_number'], 
            $entry['company'], 
            $entry['category'], 
            $entry['item'], 
            $qty,
            $s_price, 
            $total_actual, 
            $n_price, 
            $total_nam,
            $income, 
            $income_percent, 
            $entry['date_delivered'], 
            $entry['payment_term'], 
            $entry['due_date'], 
            $entry['si_number'],
            $entry['remarks'], 
            $entry['supplier'], 
            $entry['address'], 
            $entry['tin'], 
            $entry['sales_invoice_no'], 
            $entry['contact_person_contact']
        );

        if (!$stmt->execute()) {
            throw new Exception("Error inserting item: " . $entry['item'] . " - " . $stmt->error);
        }

        // --- UPDATE INVENTORY ---
        $updateStock->bind_param("is", $qty, $entry['item']);
        if (!$updateStock->execute()) {
            throw new Exception("Error updating stock for: " . $entry['item']);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'count' => count($entries)]);

} catch (Exception $e) {
    $conn->rollback();
    // Return error as JSON, not HTML
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if(isset($stmt)) $stmt->close();
if(isset($updateStock)) $updateStock->close();
$conn->close();
?>