<?php
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
    $sql = "INSERT INTO sales (
        date, sn, po_number, company, category, item, quantity_requested, is_reserved,
        suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount,
        income, income_percent, date_delivered, payment_term, due_date, si_number,
        remarks, supplier, address, tin, sales_invoice_no, contact_person_contact
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $updateStock = $conn->prepare("UPDATE products SET current_stock = current_stock - ? WHERE name = ?");

    foreach ($entries as $entry) {
        // Calculations
        $qty = intval($entry['quantity_requested']);
        $s_price = floatval($entry['suppliers_price']);
        $n_price = floatval($entry['nam_unit_price']);
        
        $total_actual = $s_price * $qty;
        $total_nam = $n_price * $qty;
        $income = $total_nam - $total_actual;
        $income_percent = ($total_nam > 0) ? ($income / $total_nam) * 100 : 0;

        $is_reserved = isset($entry['is_reserved']) ? intval($entry['is_reserved']) : 0;
        $date_del = !empty($entry['date_delivered']) ? $entry['date_delivered'] : null;
        $due_date = !empty($entry['due_date']) ? $entry['due_date'] : null;

        $stmt->bind_param(
            "ssssssiiddddddssssssssss",
            $entry['date'], $entry['sn'], $entry['po_number'], $entry['company'], $entry['category'],
            $entry['item'], $qty, $is_reserved, $s_price, $total_actual, $n_price, $total_nam,
            $income, $income_percent, $date_del, $entry['payment_term'], $due_date, $entry['si_number'],
            $entry['remarks'], $entry['supplier'], $entry['address'], $entry['tin'],
            $entry['sales_invoice_no'], $entry['contact_person_contact']
        );

        if (!$stmt->execute()) {
            throw new Exception("Insert failed: " . $stmt->error);
        }

        // Update Inventory
        $updateStock->bind_param("is", $qty, $entry['item']);
        $updateStock->execute();
        
        // Log individual item in batch
        logAction('Added Sales Record', "Added new sales record via Batch for company: {$entry['company']} (Item: {$entry['item']})");
    }

    $conn->commit();
    echo json_encode(['success' => true, 'count' => count($entries)]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>