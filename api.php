<?php
// api.php - Updated with Payment Terms
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();

// --- Helper ---
function executeQuery($conn, $sql, $types = "", $params = []) {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// --- 1. Filter Logic ---
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
if (!empty($_GET['category'])) {
    $where_clauses[] = "category = ?";
    $params[] = $_GET['category'];
    $types .= "s";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// --- 2. KPI Stats ---
$sql = "SELECT 
            SUM(total_nam_amount) as total_sales, 
            SUM(income) as total_profit,
            COUNT(*) as total_orders
        FROM sales $where_sql";
$row = executeQuery($conn, $sql, $types, $params)->fetch_assoc();

$stats = [
    'total_sales' => floatval($row['total_sales'] ?? 0),
    'total_profit' => floatval($row['total_profit'] ?? 0),
    'total_orders' => intval($row['total_orders'] ?? 0),
    'avg_order_value' => 0,
    'profit_margin' => 0,
    'growth_sales' => 0
];

if ($stats['total_orders'] > 0) $stats['avg_order_value'] = $stats['total_sales'] / $stats['total_orders'];
if ($stats['total_sales'] > 0) $stats['profit_margin'] = ($stats['total_profit'] / $stats['total_sales']) * 100;

// Growth Logic
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $start = new DateTime($_GET['start_date']);
    $end = new DateTime($_GET['end_date']);
    $diff = $start->diff($end)->days + 1;
    
    $prev_end = $start->modify('-1 day')->format('Y-m-d');
    $prev_start = $start->modify("-$diff days")->format('Y-m-d');
    
    $prev_where = ["date >= ?", "date <= ?"];
    $prev_params = [$prev_start, $prev_end];
    $prev_types = "ss";
    
    if (!empty($_GET['company'])) { $prev_where[] = "company = ?"; $prev_params[] = $_GET['company']; $prev_types .= "s"; }
    if (!empty($_GET['category'])) { $prev_where[] = "category = ?"; $prev_params[] = $_GET['category']; $prev_types .= "s"; }
    
    $prev_sql = "SELECT SUM(total_nam_amount) as old_sales FROM sales WHERE " . implode(" AND ", $prev_where);
    $prev_row = executeQuery($conn, $prev_sql, $prev_types, $prev_params)->fetch_assoc();
    $old_sales = floatval($prev_row['old_sales'] ?? 0);
    
    if ($old_sales > 0) $stats['growth_sales'] = (($stats['total_sales'] - $old_sales) / $old_sales) * 100;
    else $stats['growth_sales'] = ($stats['total_sales'] > 0) ? 100 : 0;
}

// --- 3. Top Products ---
$top_products = [];
$sql = "SELECT item, SUM(total_nam_amount) as sales, SUM(quantity_requested) as qty 
        FROM sales $where_sql GROUP BY item ORDER BY sales DESC LIMIT 10";
$result = executeQuery($conn, $sql, $types, $params);
while ($r = $result->fetch_assoc()) {
    $top_products[] = ['name' => $r['item'], 'sales' => floatval($r['sales']), 'qty' => intval($r['qty'])];
}

// --- 4. Chart Data ---
$groupBy = $_GET['group_by'] ?? 'day'; 
if ($groupBy === 'month') {
    $sql = "SELECT DATE_FORMAT(date, '%b') as label, SUM(total_nam_amount) as sales, SUM(income) as profit FROM sales $where_sql AND date IS NOT NULL GROUP BY MONTH(date), label ORDER BY MONTH(date)";
} elseif ($groupBy === 'year') {
    $sql = "SELECT YEAR(date) as label, SUM(total_nam_amount) as sales, SUM(income) as profit FROM sales $where_sql AND date IS NOT NULL GROUP BY YEAR(date) ORDER BY YEAR(date)";
} elseif ($groupBy === 'quarter') {
    $sql = "SELECT CONCAT('Q', QUARTER(date), ' ', YEAR(date)) as label, SUM(total_nam_amount) as sales, SUM(income) as profit FROM sales $where_sql AND date IS NOT NULL GROUP BY YEAR(date), QUARTER(date) ORDER BY YEAR(date), QUARTER(date)";
} else {
    $sql = "SELECT DATE_FORMAT(date, '%d') as label, SUM(total_nam_amount) as sales, SUM(income) as profit FROM sales $where_sql AND date IS NOT NULL GROUP BY date ORDER BY date";
}

$chart_data = [];
$result = executeQuery($conn, $sql, $types, $params);
while ($row = $result->fetch_assoc()) {
    $sales = floatval($row['sales']);
    $profit = floatval($row['profit']);
    $margin = ($sales > 0) ? ($profit / $sales) * 100 : 0;
    $chart_data[] = ['label' => $row['label'], 'sales' => $sales, 'margin' => $margin];
}

// --- 5. Delivery Stats ---
$delivery_stats = ['delivered' => 0, 'pending' => 0];
$sql = "SELECT 
            CASE WHEN date_delivered IS NOT NULL AND date_delivered != '0000-00-00' THEN 'delivered' ELSE 'pending' END as status,
            COUNT(*) as count
        FROM sales $where_sql
        GROUP BY status";
$result = executeQuery($conn, $sql, $types, $params);
while($row = $result->fetch_assoc()) {
    $delivery_stats[$row['status']] = intval($row['count']);
}

// --- 6. Top Suppliers (By Cost) ---
$supplier_costs = [];
$sql = "SELECT supplier, SUM(total_actual_amount) as total_cost 
        FROM sales $where_sql AND supplier != '' 
        GROUP BY supplier ORDER BY total_cost DESC LIMIT 5";
$result = executeQuery($conn, $sql, $types, $params);
while($row = $result->fetch_assoc()) {
    $supplier_costs[] = ['supplier' => $row['supplier'], 'cost' => floatval($row['total_cost'])];
}

// --- 7. Company Sales ---
$company_sales = [];
$sql = "SELECT company, SUM(total_nam_amount) as total_sales FROM sales $where_sql AND company != '' GROUP BY company ORDER BY total_sales DESC";
$result = executeQuery($conn, $sql, $types, $params);
while ($row = $result->fetch_assoc()) {
    $company_sales[] = ['company' => $row['company'], 'total_sales' => floatval($row['total_sales'])];
}

// --- 8. Category Matrix ---
$category_breakdown = [];
$sql = "SELECT category, SUM(quantity_requested) as total_qty, SUM(total_nam_amount) as total_sales, SUM(income) as total_profit FROM sales $where_sql AND category != '' GROUP BY category ORDER BY total_sales DESC";
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

$sql = "SELECT category, item, SUM(quantity_requested) as qty, SUM(total_nam_amount) as sales, SUM(income) as profit FROM sales $where_sql AND category != '' GROUP BY category, item ORDER BY sales DESC";
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

// --- 9. NEW: Payment Terms ---
$payment_terms = [];
$sql = "SELECT UPPER(payment_term) as term, COUNT(*) as count 
        FROM sales $where_sql AND payment_term != '' 
        GROUP BY UPPER(payment_term) ORDER BY count DESC";
$result = executeQuery($conn, $sql, $types, $params);
while($row = $result->fetch_assoc()) {
    $payment_terms[] = ['term' => $row['term'], 'count' => intval($row['count'])];
}

// --- 10. Dropdown Data ---
$companies = [];
$res = $conn->query("SELECT DISTINCT company FROM sales WHERE company != '' ORDER BY company");
while($r = $res->fetch_assoc()) $companies[] = $r['company'];

echo json_encode([
    'stats' => $stats,
    'chart_data' => $chart_data,
    'delivery_stats' => $delivery_stats,
    'supplier_costs' => $supplier_costs,
    'company_sales' => $company_sales,
    'top_products' => $top_products,
    'category_matrix' => array_values($category_breakdown),
    'payment_terms' => $payment_terms, // NEW
    'companies' => $companies
]);

$conn->close();
?>