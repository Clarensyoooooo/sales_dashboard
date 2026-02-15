<?php
// submit_batch.php
header('Content-Type: application/json');
require_once 'config.php';

// Disable error display to avoid breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

$input = file_get_contents('php://input');
$entries = json_decode($input, true);

if (!$entries || empty($entries)) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

$conn = getDBConnection();
$conn->begin_transaction();

try {
    // We insert NULL for date_delivered by default to mark it as PENDING
    $sql = "INSERT INTO sales (
        date, sn, po_number, company, category, item, quantity_requested,
        suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount,
        income, income_percent, 
        date_delivered, 
        payment_term, due_date, si_number,
        remarks, supplier, address, tin, sales_invoice_no, contact_person_contact
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    
    // Inventory update query
    $updateStock = $conn->prepare("UPDATE products SET current_stock = current_stock - ? WHERE name = ?");

    foreach ($entries as $entry) {
        // 1. Calculations
        $qty = intval($entry['quantity_requested']);
        $s_price = floatval($entry['suppliers_price']);
        $n_price = floatval($entry['nam_unit_price']);
        
        $total_actual = $s_price * $qty;
        $total_nam = $n_price * $qty;
        $income = $total_nam - $total_actual;
        $income_percent = ($total_nam > 0) ? ($income / $total_nam) * 100 : 0;

        // 2. Data Cleaning
        // CRITICAL: If date_delivered is empty, we MUST send NULL
        $date_del = !empty($entry['date_delivered']) ? $entry['date_delivered'] : null;
        
        $due_date = !empty($entry['due_date']) ? $entry['due_date'] : null;

        // 3. Bind Params (23 items)
        // types: s=string, i=int, d=double
        // Pattern: ssssssidddddssssssssss (Adjust based on your exact column types)
        $stmt->bind_param(
            "ssssssiddddddssssssssss",
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
            $date_del,      // <--- This is now strictly NULL if empty
            $entry['payment_term'],
            $due_date,
            $entry['si_number'],
            $entry['remarks'],
            $entry['supplier'],
            $entry['address'],
            $entry['tin'],
            $entry['sales_invoice_no'],
            $entry['contact_person_contact']
        );

        if (!$stmt->execute()) {
            throw new Exception("Insert failed: " . $stmt->error);
        }

        // 4. Update Inventory
        $updateStock->bind_param("is", $qty, $entry['item']);
        $updateStock->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'count' => count($entries)]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}