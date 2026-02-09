<?php
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();
$sql = "SELECT * FROM products ORDER BY name ASC";
$result = $conn->query($sql);

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode($products);
$conn->close();
?>