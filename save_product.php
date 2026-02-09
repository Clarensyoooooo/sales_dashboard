<?php
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();

$id = $_POST['id'] ?? '';
$name = $_POST['name'];
$category = $_POST['category'];
$unit = $_POST['unit'];
$supplier = $_POST['supplier'];
$s_price = $_POST['supplier_price'];
$n_price = $_POST['nam_price'];

// Calculate margin automatically
if ($n_price > 0) {
    $marginVal = (($n_price - $s_price) / $n_price) * 100;
    $margin = number_format($marginVal, 2) . '%';
} else {
    $margin = '0%';
}

if (!empty($id)) {
    // UPDATE
    $stmt = $conn->prepare("UPDATE products SET name=?, category_code=?, unit=?, supplier=?, supplier_price=?, nam_price=?, margin=? WHERE id=?");
    $stmt->bind_param("ssssddsi", $name, $category, $unit, $supplier, $s_price, $n_price, $margin, $id);
} else {
    // INSERT
    $stmt = $conn->prepare("INSERT INTO products (name, category_code, unit, supplier, supplier_price, nam_price, margin) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssdds", $name, $category, $unit, $supplier, $s_price, $n_price, $margin);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}

$conn->close();
?>