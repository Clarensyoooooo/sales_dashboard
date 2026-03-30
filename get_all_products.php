<?php
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();
// Added is_draft to the query
$sql = "SELECT id, name, category_code, unit, supplier, supplier_price, nam_price, margin, current_stock, reorder_level, is_draft FROM products ORDER BY name ASC";
$result = $conn->query($sql);

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode($products);
$conn->close();
?>