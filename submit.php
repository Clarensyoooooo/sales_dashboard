<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    // Clean and prepare data
    $date = $_POST['date'];
    $sn = $_POST['sn'];
    $po_number = $_POST['po_number'];
    $company = $_POST['company'];
    $category = $_POST['category'];
    $item = $_POST['item'];
    $quantity_requested = intval($_POST['quantity_requested']);
    $suppliers_price = floatval($_POST['suppliers_price']);
    $nam_unit_price = floatval($_POST['nam_unit_price']);
    
    // Calculate amounts
    $total_actual_amount = $suppliers_price * $quantity_requested;
    $total_nam_amount = $nam_unit_price * $quantity_requested;
    $income = $total_nam_amount - $total_actual_amount;
    
    if ($total_nam_amount > 0) {
        $income_percent = ($income / $total_nam_amount) * 100;
    } else {
        $income_percent = 0;
    }
    
    // Optional fields
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
    
    if ($stmt->execute()) {
        header('Location: index.php?success=1');
    } else {
        header('Location: form.php?error=1');
    }
    
    $stmt->close();
    $conn->close();
    exit;
}
?>
