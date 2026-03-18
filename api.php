<?php
// api.php - Updated for Collection Status & Account Manager Tracking + Exact Growth Value
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();

// AUTO-CREATE ASSIGNMENTS TABLE IF IT DOESN'T EXIST
$conn->query("CREATE TABLE IF NOT EXISTS company_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) UNIQUE,
    employee_name VARCHAR(100)
)");

// FETCH CURRENT ASSIGNMENTS
$assignments = [];
$res = $conn->query("SELECT company_name, employee_name FROM company_assignments");
if ($res) {
    while($r = $res->fetch_assoc()) $assignments[$r['company_name']] = $r['employee_name'];
}

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
if (!empty($_GET['manager'])) {
    if ($_GET['manager'] === 'Unassigned') {
        $where_clauses[] = "company NOT IN (SELECT company_name FROM company_assignments)";
    } else {
        $where_clauses[] = "company IN (SELECT company_name FROM company_assignments WHERE employee_name = ?)";
        $params[] = $_GET['manager'];
        $types .= "s";
    }
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
    'growth_sales' => 0,
    'growth_value' => 0 // Exact difference
];

if ($stats['total_orders'] > 0) $stats['avg_order_value'] = $stats['total_sales'] / $stats['total_orders'];
if ($stats['total_sales'] > 0) $stats['profit_margin'] = ($stats['total_profit'] / $stats['total_sales']) * 100;

// Growth Logic - Always compare against the FULL previous month
if (!empty($_GET['start_date'])) {
    $start = new DateTime($_GET['start_date']);
    
    // Get the exact first and last day of the previous month
    $prev_start_obj = clone $start;
    $prev_start_obj->modify('first day of last month');
    $prev_start = $prev_start_obj->format('Y-m-d');

    $prev_end_obj = clone $start;
    $prev_end_obj->modify('last day of last month');
    $prev_end = $prev_end_obj->format('Y-m-d');
    
    $prev_where = ["date >= ?", "date <= ?"];
    $prev_params = [$prev_start, $prev_end];
    $prev_types = "ss";
    
    // Apply the same drills/filters to the previous month's query
    if (!empty($_GET['company'])) { $prev_where[] = "company = ?"; $prev_params[] = $_GET['company']; $prev_types .= "s"; }
    if (!empty($_GET['category'])) { $prev_where[] = "category = ?"; $prev_params[] = $_GET['category']; $prev_types .= "s"; }
    if (!empty($_GET['manager'])) { 
        if ($_GET['manager'] === 'Unassigned') {
            $prev_where[] = "company NOT IN (SELECT company_name FROM company_assignments)";
        } else {
            $prev_where[] = "company IN (SELECT company_name FROM company_assignments WHERE employee_name = ?)";
            $prev_params[] = $_GET['manager'];
            $prev_types .= "s";
        }
    }
    
    $prev_sql = "SELECT SUM(total_nam_amount) as old_sales FROM sales WHERE " . implode(" AND ", $prev_where);
    $prev_row = executeQuery($conn, $prev_sql, $prev_types, $prev_params)->fetch_assoc();
    $old_sales = floatval($prev_row['old_sales'] ?? 0);
    
    $stats['growth_value'] = $stats['total_sales'] - $old_sales; 
    
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

// --- 7. Company Sales (WITH EMPLOYEE DATA MAPPED) ---
$company_sales = [];
$sql = "SELECT company, SUM(total_nam_amount) as total_sales FROM sales $where_sql AND company != '' GROUP BY company ORDER BY total_sales DESC";
$result = executeQuery($conn, $sql, $types, $params);
while ($row = $result->fetch_assoc()) {
    $emp = isset($assignments[$row['company']]) ? $assignments[$row['company']] : 'Unassigned';
    $company_sales[] = [
        'company' => $row['company'], 
        'total_sales' => floatval($row['total_sales']),
        'employee' => $emp
    ];
}

// --- Account Manager Sales Aggregation ---
$manager_sales = [];
foreach ($company_sales as $cs) {
    $emp = $cs['employee'];
    if (!isset($manager_sales[$emp])) $manager_sales[$emp] = 0;
    $manager_sales[$emp] += $cs['total_sales'];
}

$manager_sales_arr = [];
foreach ($manager_sales as $emp => $sales) {
    $manager_sales_arr[] = ['employee' => $emp, 'sales' => $sales];
}
// Sort by highest sales
usort($manager_sales_arr, function($a, $b) { return $b['sales'] <=> $a['sales']; });

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

// --- 9. Collection Status (Actual Paid vs Unpaid based on payment_status) ---
$collection_status = [];
$sql = "SELECT 
            CASE WHEN payment_status = 'Paid' THEN 'Paid' ELSE 'Unpaid' END as status, 
            SUM(total_nam_amount) as sales 
        FROM sales $where_sql 
        GROUP BY status ORDER BY sales DESC";
$result = executeQuery($conn, $sql, $types, $params);
while($row = $result->fetch_assoc()) {
    $collection_status[] = [
        'status' => $row['status'], 
        'sales' => floatval($row['sales'])
    ];
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
    'collection_status' => $collection_status,
    'manager_sales' => $manager_sales_arr,
    'companies' => $companies
]);

$conn->close();
?>