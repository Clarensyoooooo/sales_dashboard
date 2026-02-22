<?php
require_once 'config.php';
requireLogin(); // Ensure they are authenticated

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    // Collect all data exactly as you had it
    $date = $_POST['date'];
    $sn = $_POST['sn']; // Serial Number or Reference
    $po_number = $_POST['po_number'];
    $company = $_POST['company'];
    $category = $_POST['category'];
    $item = $_POST['item'];
    $quantity_requested = intval($_POST['quantity_requested']);
    $suppliers_price = floatval($_POST['suppliers_price']);
    $nam_unit_price = floatval($_POST['nam_unit_price']);
    
    // Calculations
    $total_actual_amount = $suppliers_price * $quantity_requested;
    $total_nam_amount = $nam_unit_price * $quantity_requested;
    $income = $total_nam_amount - $total_actual_amount;
    $income_percent = ($total_nam_amount > 0) ? ($income / $total_nam_amount) * 100 : 0;
    
    $date_delivered = !empty($_POST['date_delivered']) ? $_POST['date_delivered'] : null;
    $payment_term = $_POST['payment_term'] ?? '';
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $si_number = $_POST['si_number'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $supplier = $_POST['supplier'] ?? '';
    $address = $_POST['address'] ?? '';
    $tin = $_POST['tin'] ?? '';
    $sales_invoice_no = $_POST['sales_invoice_no'] ?? '';
    $contact_person_contact = $_POST['contact_person_contact'] ?? '';

    // --- CONCURRENCY CHECK: Prevent duplicate entries ---
    // If two encoders try to enter the same PO Number and Item, block the second one.
    $check_dup = $conn->prepare("SELECT id FROM sales WHERE po_number = ? AND item = ?");
    $check_dup->bind_param("ss", $po_number, $item);
    $check_dup->execute();
    if ($check_dup->get_result()->num_rows > 0) {
        $check_dup->close();
        $conn->close();
        // Redirect back with an error indicating this was already encoded
        header('Location: form.php?error=duplicate_entry');
        exit;
    }
    $check_dup->close();

    // --- START TRANSACTION ---
    $conn->begin_transaction();

    try {
        // 1. Insert the Sale
        $sql = "INSERT INTO sales (
            date, sn, po_number, company, category, item, quantity_requested,
            suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount,
            income, income_percent, date_delivered, payment_term, due_date, si_number,
            remarks, supplier, address, tin, sales_invoice_no, contact_person_contact
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssissddddsssssssss",
            $date, $sn, $po_number, $company, $category, $item, $quantity_requested,
            $suppliers_price, $total_actual_amount, $nam_unit_price, $total_nam_amount,
            $income, $income_percent, $date_delivered, $payment_term, $due_date, $si_number,
            $remarks, $supplier, $address, $tin, $sales_invoice_no, $contact_person_contact
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert sale.");
        }

        // 2. Update Inventory
        // This query is naturally atomic in InnoDB, making it safe for concurrent transactions
        $updateStock = $conn->prepare("UPDATE products SET current_stock = current_stock - ? WHERE name = ?");
        $updateStock->bind_param("is", $quantity_requested, $item);
        
        if (!$updateStock->execute()) {
             throw new Exception("Failed to update inventory.");
        }
        
        // --- COMMIT TRANSACTION ---
        // If both the insert and update succeeded, save it permanently.
        $conn->commit();
        // ADD THIS LINE
        logAction('Created Sale', "Added sale for $company (Item: $item, Qty: $quantity_requested)");
        $stmt->close();
        $updateStock->close();
        $conn->close();
        
        header('Location: index.php?success=1');
        exit;

    } catch (Exception $e) {
        // --- ROLLBACK TRANSACTION ---
        // If anything fails, undo the whole process to prevent corrupted data
        $conn->rollback();
        error_log("Transaction failed: " . $e->getMessage());
        header('Location: form.php?error=system_error');
        exit;
    }
}
?>