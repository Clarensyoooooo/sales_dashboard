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

// --- 2. KPI Stats (Unchanged) ---
function executeQuery($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

$stats = [];
$sql = "SELECT SUM(total_nam_amount) as total_sales, SUM(income) as total_profit FROM sales $where_sql";
$row = executeQuery($conn, $sql, $types, $params)->fetch_assoc();
$stats['total_sales'] = floatval($row['total_sales'] ?? 0);
$stats['total_profit'] = floatval($row['total_profit'] ?? 0);
$stats['profit_margin'] = ($stats['total_sales'] > 0) ? ($stats['total_profit'] / $stats['total_sales']) * 100 : 0;

// --- 3. DYNAMIC CHART DATA (The Fix) ---
$groupBy = $_GET['group_by'] ?? 'day'; // Default to day

if ($groupBy === 'month') {
    // For Yearly view: Show Jan, Feb, Mar...
    $sql = "SELECT 
                DATE_FORMAT(date, '%b') as label, 
                MONTH(date) as sort_key,
                SUM(total_nam_amount) as sales,
                SUM(income) as profit
            FROM sales $where_sql AND date IS NOT NULL 
            GROUP BY MONTH(date), DATE_FORMAT(date, '%b') 
            ORDER BY MONTH(date)";
} elseif ($groupBy === 'year') {
    // For All Time view: Show 2024, 2025...
    $sql = "SELECT 
                YEAR(date) as label, 
                YEAR(date) as sort_key,
                SUM(total_nam_amount) as sales,
                SUM(income) as profit
            FROM sales $where_sql AND date IS NOT NULL 
            GROUP BY YEAR(date) 
            ORDER BY YEAR(date)";
} elseif ($groupBy === 'quarter') {
    // NEW: Quarterly View
    $sql = "SELECT 
                CONCAT('Q', QUARTER(date), ' ', YEAR(date)) as label, 
                CONCAT(YEAR(date), QUARTER(date)) as sort_key,
                SUM(total_nam_amount) as sales,
                SUM(income) as profit
            FROM sales $where_sql AND date IS NOT NULL 
            GROUP BY YEAR(date), QUARTER(date) 
            ORDER BY YEAR(date), QUARTER(date)";
} else {
    // Default (Daily): Show Day 1, 2, 3...
    $sql = "SELECT 
                DATE_FORMAT(date, '%d') as label, 
                DAY(date) as sort_key,
                SUM(total_nam_amount) as sales,
                SUM(income) as profit
            FROM sales $where_sql AND date IS NOT NULL 
            GROUP BY date 
            ORDER BY date";
}

$chart_data = [];
$result = executeQuery($conn, $sql, $types, $params);

while ($row = $result->fetch_assoc()) {
    $sales = floatval($row['sales']);
    $profit = floatval($row['profit']);
    $margin = ($sales > 0) ? ($profit / $sales) * 100 : 0;
    
    $chart_data[] = [
        'label' => $row['label'], // This is now dynamic (e.g. "Jan", "2025", "Q1")
        'sales' => $sales,
        'margin' => $margin
    ];
}

// --- 4. Company Chart (Unchanged) ---
$company_sales = [];
$sql = "SELECT company, SUM(total_nam_amount) as total_sales 
        FROM sales $where_sql AND company != '' 
        GROUP BY company ORDER BY total_sales DESC";
$result = executeQuery($conn, $sql, $types, $params);
while ($row = $result->fetch_assoc()) {
    $company_sales[] = ['company' => $row['company'], 'total_sales' => floatval($row['total_sales'])];
}

// --- 5. Matrix Data (Unchanged) ---
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

// Fill Items
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

// --- 6. Company Dropdown (Unchanged) ---
$companies = [];
$res = $conn->query("SELECT DISTINCT company FROM sales WHERE company != '' ORDER BY company");
while($r = $res->fetch_assoc()) $companies[] = $r['company'];

echo json_encode([
    'stats' => $stats,
    'chart_data' => $chart_data, // Renamed from daily_sales to chart_data
    'company_sales' => $company_sales,
    'category_matrix' => array_values($category_breakdown),
    'companies' => $companies
]);

$conn->close();
?>