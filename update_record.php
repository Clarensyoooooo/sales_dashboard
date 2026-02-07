<?php
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$conn = getDBConnection();

// Get form data
$id = intval($_POST['id']);
$date = $_POST['date'];
$sn = $_POST['sn'];
$po_number = $_POST['po_number'];
$company = $_POST['company'];
$category = $_POST['category'];
$item = $_POST['item'];
$quantity_requested = intval($_POST['quantity_requested']);
$suppliers_price = floatval($_POST['suppliers_price']);
$nam_unit_price = floatval($_POST['nam_unit_price']);
$supplier = $_POST['supplier'];
$remarks = $_POST['remarks'];

// Calculate amounts
$total_actual_amount = $suppliers_price * $quantity_requested;
$total_nam_amount = $nam_unit_price * $quantity_requested;
$income = $total_nam_amount - $total_actual_amount;

if ($total_nam_amount > 0) {
    $income_percent = ($income / $total_nam_amount) * 100;
} else {
    $income_percent = 0;
}

// Update record
$sql = "UPDATE sales SET
    date = ?,
    sn = ?,
    po_number = ?,
    company = ?,
    category = ?,
    item = ?,
    quantity_requested = ?,
    suppliers_price = ?,
    total_actual_amount = ?,
    nam_unit_price = ?,
    total_nam_amount = ?,
    income = ?,
    income_percent = ?,
    supplier = ?,
    remarks = ?
WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "ssssssissdddssi",
    $date,
    $sn,
    $po_number,
    $company,
    $category,
    $item,
    $quantity_requested,
    $suppliers_price,
    $total_actual_amount,
    $nam_unit_price,
    $total_nam_amount,
    $income,
    $income_percent,
    $supplier,
    $remarks,
    $id
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Record updated successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating record: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
