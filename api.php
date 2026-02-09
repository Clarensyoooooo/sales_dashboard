<?php
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();

// --- 1. Build Filter Query ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($_GET['start_date'])) {
    $where_clauses[] = "date >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}
if (!empty($_GET['end_date'])) {
    $where_clauses[] = "date <= ?";
    $params[] = $_GET['end_date'];
    $types .= "s";
}
if (!empty($_GET['company'])) {
    $where_clauses[] = "company = ?";
    $params[] = $_GET['company'];
    $types .= "s";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

function executeQuery($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

$stats = [];

// --- 2. KPI Stats ---
$sql = "SELECT SUM(total_nam_amount) as total_sales, SUM(income) as total_profit FROM sales $where_sql";
$row = executeQuery($conn, $sql, $types, $params)->fetch_assoc();
$stats['total_sales'] = floatval($row['total_sales'] ?? 0);
$stats['total_profit'] = floatval($row['total_profit'] ?? 0);
$stats['profit_margin'] = ($stats['total_sales'] > 0) ? ($stats['total_profit'] / $stats['total_sales']) * 100 : 0;

// --- 3. Daily Sales & Margin Chart ---
$daily_sales = [];
$sql = "SELECT 
            DAY(date) as day, 
            SUM(total_nam_amount) as sales,
            SUM(income) as profit
        FROM sales $where_sql AND date IS NOT NULL 
        GROUP BY DAY(date) ORDER BY DAY(date)";
$result = executeQuery($conn, $sql, $types, $params);

while ($row = $result->fetch_assoc()) {
    $sales = floatval($row['sales']);
    $profit = floatval($row['profit']);
    $margin = ($sales > 0) ? ($profit / $sales) * 100 : 0;
    
    $daily_sales[] = [
        'day' => $row['day'],
        'sales' => $sales,
        'margin' => $margin
    ];
}

// --- 4. Company Chart (ALL COMPANIES - NO LIMIT) ---
$company_sales = [];
$sql = "SELECT company, SUM(total_nam_amount) as total_sales 
        FROM sales $where_sql AND company != '' 
        GROUP BY company ORDER BY total_sales DESC"; // Removed LIMIT 10
$result = executeQuery($conn, $sql, $types, $params);
while ($row = $result->fetch_assoc()) {
    $company_sales[] = ['company' => $row['company'], 'total_sales' => floatval($row['total_sales'])];
}

// --- 5. Matrix Data ---
$category_breakdown = [];
$sql = "SELECT category, SUM(quantity_requested) as total_qty, SUM(total_nam_amount) as total_sales, SUM(income) as total_profit 
        FROM sales $where_sql AND category != '' GROUP BY category ORDER BY total_sales DESC";
$result = executeQuery($conn, $sql, $types, $params);
while ($row = $result->fetch_assoc()) {
    $category_breakdown[$row['category']] = [
        'category' => $row['category'],
        'quantity' => intval($row['total_qty']),
        'total_sales' => floatval($row['total_sales']),
        'total_profit' => floatval($row['total_profit']),
        'items' => []
    ];
}

$sql = "SELECT category, item, SUM(quantity_requested) as qty, SUM(total_nam_amount) as sales, SUM(income) as profit 
        FROM sales $where_sql AND category != '' GROUP BY category, item ORDER BY sales DESC";
$result = executeQuery($conn, $sql, $types, $params);
while ($row = $result->fetch_assoc()) {
    if (isset($category_breakdown[$row['category']])) {
        $category_breakdown[$row['category']]['items'][] = [
            'name' => $row['item'],
            'qty' => intval($row['qty']),
            'sales' => floatval($row['sales']),
            'profit' => floatval($row['profit'])
        ];
    }
}

// --- 6. Company Dropdown ---
$companies = [];
$res = $conn->query("SELECT DISTINCT company FROM sales WHERE company != '' ORDER BY company");
while($r = $res->fetch_assoc()) $companies[] = $r['company'];

echo json_encode([
    'stats' => $stats,
    'daily_sales' => $daily_sales,
    'company_sales' => $company_sales,
    'category_matrix' => array_values($category_breakdown),
    'companies' => $companies
]);

$conn->close();
?>