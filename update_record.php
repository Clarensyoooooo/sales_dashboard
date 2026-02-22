<?php
header('Content-Type: application/json');
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$conn = getDBConnection();
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit;
}

$date = $_POST['date'] ?? '';
$sn = $_POST['sn'] ?? '';
$po_number = $_POST['po_number'] ?? '';
$company = $_POST['company'] ?? '';
$category = $_POST['category'] ?? '';
$item = $_POST['item'] ?? '';
$quantity = floatval($_POST['quantity_requested'] ?? 0);
$supplier_price = floatval($_POST['suppliers_price'] ?? 0);
$nam_price = floatval($_POST['nam_unit_price'] ?? 0);
$supplier = $_POST['supplier'] ?? '';
$remarks = $_POST['remarks'] ?? '';
$date_delivered = !empty($_POST['date_delivered']) ? $_POST['date_delivered'] : NULL;
$payment_term = $_POST['payment_term'] ?? '';
$due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : NULL;
$si_number = $_POST['si_number'] ?? '';
$sales_invoice_no = $_POST['sales_invoice_no'] ?? '';
$address = $_POST['address'] ?? '';
$tin = $_POST['tin'] ?? '';
$contact_person_contact = $_POST['contact_person_contact'] ?? '';

$total_actual = $quantity * $supplier_price;
$total_nam = $quantity * $nam_price;
$income = $total_nam - $total_actual;
$income_percent = ($total_nam > 0) ? ($income / $total_nam) * 100 : 0;

$sql = "UPDATE sales SET 
    date=?, sn=?, po_number=?, company=?, category=?, item=?, quantity_requested=?, suppliers_price=?, 
    total_actual_amount=?, nam_unit_price=?, total_nam_amount=?, income=?, income_percent=?, supplier=?, 
    remarks=?, date_delivered=?, payment_term=?, due_date=?, si_number=?, sales_invoice_no=?, address=?, 
    tin=?, contact_person_contact=? WHERE id=?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssidddddsssssssssssi", 
    $date, $sn, $po_number, $company, $category, $item, $quantity, $supplier_price, $total_actual, 
    $nam_price, $total_nam, $income, $income_percent, $supplier, $remarks, $date_delivered, $payment_term, 
    $due_date, $si_number, $sales_invoice_no, $address, $tin, $contact_person_contact, $id
);

if ($stmt->execute()) {
    logAction('Updated Sale', "Updated sale details for $company (Item: $item)");
    echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Execute failed']);
}

$stmt->close();
$conn->close();
?>