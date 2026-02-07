<?php
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();

// Get all records
$sql = "SELECT 
    id,
    date,
    sn,
    po_number,
    company,
    category,
    item,
    quantity_requested,
    suppliers_price,
    total_actual_amount,
    nam_unit_price,
    total_nam_amount,
    income,
    income_percent,
    supplier,
    remarks,
    created_at
FROM sales
ORDER BY date DESC, id DESC";

$result = $conn->query($sql);

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

// Get unique companies for filter
$companiesResult = $conn->query("SELECT DISTINCT company FROM sales WHERE company IS NOT NULL AND company != '' ORDER BY company");
$companies = [];
while ($row = $companiesResult->fetch_assoc()) {
    $companies[] = $row['company'];
}

// Get unique categories for filter
$categoriesResult = $conn->query("SELECT DISTINCT category FROM sales WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    $categories[] = $row['category'];
}

$response = [
    'success' => true,
    'records' => $records,
    'companies' => $companies,
    'categories' => $categories,
    'total' => count($records)
];

echo json_encode($response);

$conn->close();
?>
