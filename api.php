<?php
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();

// Get dashboard statistics
$stats = [];

// Total Sales
$result = $conn->query("SELECT SUM(total_nam_amount) as total_sales FROM sales");
$row = $result->fetch_assoc();
$stats['total_sales'] = $row['total_sales'] ?? 0;

// Total Profit (Income)
$result = $conn->query("SELECT SUM(income) as total_profit FROM sales");
$row = $result->fetch_assoc();
$stats['total_profit'] = $row['total_profit'] ?? 0;

// Profit Margin %
if ($stats['total_sales'] > 0) {
    $stats['profit_margin'] = ($stats['total_profit'] / $stats['total_sales']) * 100;
} else {
    $stats['profit_margin'] = 0;
}

// Total Sales and Profit Margin by Day
$daily_sales = [];
$result = $conn->query("
    SELECT 
        DAY(date) as day,
        SUM(total_nam_amount) as sales,
        AVG(income_percent) as profit_margin
    FROM sales
    WHERE date IS NOT NULL
    GROUP BY DAY(date)
    ORDER BY DAY(date)
");

while ($row = $result->fetch_assoc()) {
    $daily_sales[] = [
        'day' => $row['day'],
        'sales' => floatval($row['sales']),
        'profit_margin' => floatval($row['profit_margin']) / 100
    ];
}

// Total Sales by Company
$company_sales = [];
$result = $conn->query("
    SELECT 
        company,
        SUM(total_nam_amount) as total_sales
    FROM sales
    WHERE company IS NOT NULL AND company != ''
    GROUP BY company
    ORDER BY total_sales DESC
");

while ($row = $result->fetch_assoc()) {
    $company_sales[] = [
        'company' => $row['company'],
        'total_sales' => floatval($row['total_sales'])
    ];
}

// Total Sales by Category
$category_sales = [];
$result = $conn->query("
    SELECT 
        category,
        SUM(quantity_requested) as quantity,
        SUM(total_nam_amount) as total_sales,
        SUM(income) as total_profit
    FROM sales
    WHERE category IS NOT NULL AND category != ''
    GROUP BY category
    ORDER BY total_sales DESC
");

while ($row = $result->fetch_assoc()) {
    $category_sales[] = [
        'category' => $row['category'],
        'quantity' => intval($row['quantity']),
        'total_sales' => floatval($row['total_sales']),
        'total_profit' => floatval($row['total_profit'])
    ];
}

// Calculate total quantity and total sales for percentages
$total_quantity = array_sum(array_column($category_sales, 'quantity'));
$total_category_sales = array_sum(array_column($category_sales, 'total_sales'));

foreach ($category_sales as &$cat) {
    if ($total_category_sales > 0) {
        $cat['percentage'] = ($cat['total_sales'] / $total_category_sales) * 100;
    } else {
        $cat['percentage'] = 0;
    }
}

// Compile response
$response = [
    'stats' => $stats,
    'daily_sales' => $daily_sales,
    'company_sales' => $company_sales,
    'category_sales' => $category_sales,
    'total_quantity' => $total_quantity
];

echo json_encode($response);

$conn->close();
?>
