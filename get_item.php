<?php
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();

if (isset($_GET['q'])) {
    $search = "%" . $_GET['q'] . "%";
    $sql = "SELECT * FROM products WHERE name LIKE ? LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'label' => $row['name'], // What shows in the dropdown
            'value' => $row['name'], // What goes in the input
            's_price' => $row['supplier_price'],
            'n_price' => $row['nam_price'],
            'cat' => $row['category_code'],
            'supplier' => $row['supplier']
        ];
    }
    echo json_encode($data);
} else if (isset($_GET['exact'])) {
    // Exact match fetch
    $name = $_GET['exact'];
    $sql = "SELECT * FROM products WHERE name = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    echo json_encode($result->fetch_assoc());
}

$conn->close();
?>