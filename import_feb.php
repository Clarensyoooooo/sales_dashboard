<?php
// import_feb.php
require_once 'config.php';

// Exact filename of your new Feb sales file
$csv_file = "NAM SUPPLY-SALES ONLY ENCODER - NAM SALE FEB.csv";

echo "<h1>Importing February Sales...</h1>";

if (!file_exists($csv_file)) {
    die("<h3 style='color:red'>Error: Could not find file: $csv_file</h3>");
}

// Check database connection before starting
$conn = getDBConnection();
if ($conn->connect_error) {
    die("<h3 style='color:red'>Database Connection Failed: " . $conn->connect_error . "</h3>");
}

// Call the function from config.php
if (importCSV($csv_file)) {
    echo "<h3 style='color:green'>✅ Success! February data has been imported.</h3>";
    echo "<p>You can now go back to the dashboard.</p>";
    echo "<a href='index.php' style='padding:10px 20px; background:#0078d4; color:white; text-decoration:none; border-radius:4px;'>Go to Dashboard</a>";
} else {
    echo "<h3 style='color:red'>❌ Failed to import data.</h3>";
}
?>