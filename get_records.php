<?php
header('Content-Type: application/json');
require_once 'config.php';

// Ensure the user is logged in before giving them data
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$conn = getDBConnection();

// 1. Get all records (SELECT * ensures payment_status and date_paid are included)
$sql = "SELECT * FROM sales ORDER BY date DESC, id DESC";
$result = $conn->query($sql);

$records = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}

// 2. Get unique companies for the dropdown filter
$companiesResult = $conn->query("SELECT DISTINCT company FROM sales WHERE company IS NOT NULL AND company != '' ORDER BY company");
$companies = [];
if ($companiesResult) {
    while ($row = $companiesResult->fetch_assoc()) {
        $companies[] = trim($row['company']);
    }
}

// 3. Get unique categories for the dropdown filter
$categoriesResult = $conn->query("SELECT DISTINCT category FROM sales WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = [];
if ($categoriesResult) {
    while ($row = $categoriesResult->fetch_assoc()) {
        $categories[] = trim($row['category']);
    }
}

// 4. Send the JSON Response back to Javascript
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