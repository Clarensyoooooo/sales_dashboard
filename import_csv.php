<?php
require_once 'config.php';

echo "=== NAM Supply Sales Dashboard - CSV Import ===\n\n";

// Check if CSV file is provided
if ($argc < 2) {
    echo "Usage: php import_csv.php <path_to_csv_file>\n";
    echo "Example: php import_csv.php data.csv\n";
    exit(1);
}

$csvFile = $argv[1];

if (!file_exists($csvFile)) {
    echo "Error: File '$csvFile' not found!\n";
    exit(1);
}

echo "Importing data from: $csvFile\n";
echo "Please wait...\n\n";

$result = importCSV($csvFile);

if ($result) {
    echo "✓ CSV data imported successfully!\n";
    
    // Show statistics
    $conn = getDBConnection();
    $result = $conn->query("SELECT COUNT(*) as count FROM sales");
    $row = $result->fetch_assoc();
    echo "Total records in database: " . $row['count'] . "\n";
    $conn->close();
} else {
    echo "✗ Error importing CSV data.\n";
}

echo "\nYou can now access the dashboard at: http://localhost/sales_dashboard/\n";
?>
