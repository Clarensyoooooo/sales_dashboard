<?php
require_once 'config.php';

// MAPPING: CSV Code -> Database/Form Category
$categoryMap = [
    'CM' => 'CLEANING MATERIALS',
    'CO' => 'CONSUMABLES',
    'CU' => 'COMPANY UNIFORM',
    'FF' => 'OFFICE FURNITURE & FIXTURES',
    'MA' => 'MATERIALS',
    'MD' => 'MEDICINE',
    'OS' => 'OFFICE SUPPLIES', 
    'PPE' => 'PPE',
    'TE' => 'OFFICE TOOLS AND EQUIPMENT'
];

echo "<h1>Importing Price List...</h1>";

$csvFile = "Centralized Suppliers' Price - CENTRALIZED WITH SUPPLIERS' PRICE (1).csv"; // Ensure this filename matches exactly

if (!file_exists($csvFile)) {
    die("Error: File '$csvFile' not found. Make sure it is in the sales_dashboard folder.");
}

$conn = getDBConnection();
// Clear old price list to prevent duplicates
$conn->query("TRUNCATE TABLE products");

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $row = 0;
    $success = 0;
    
    while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
        $row++;
        // Skip header rows (Rows 1 and 2 in your file)
        if ($row <= 2) continue;
        
        // NEW MAPPING: 0=Product, 1=Unit, 2=Cat, 3=Supplier, 4=Supp Price, 5=NAM Price, 6=Margin, 7=Inventory
        $name = trim($data[0] ?? '');
        
        // Stop if no product name
        if (empty($name)) continue;

        $unit = trim($data[1] ?? '');
        $catCode = trim($data[2] ?? '');
        $supplier = trim($data[3] ?? '');
        
        // Clean prices (Remove '₱', commas, and spaces)
        $s_price = (float) preg_replace('/[₱,\s]/', '', $data[4] ?? '0');
        $n_price = (float) preg_replace('/[₱,\s]/', '', $data[5] ?? '0');
        $margin = trim($data[6] ?? '');
        
        // Grab Inventory (Defaults to 0 if blank)
        $inventory = (int) trim($data[7] ?? '0');

        // Map Category Code to Full Name if possible
        $fullCategory = isset($categoryMap[$catCode]) ? $categoryMap[$catCode] : $catCode;

        // Added current_stock to the import query
        $stmt = $conn->prepare("INSERT INTO products (name, category_code, unit, supplier, supplier_price, nam_price, margin, current_stock, reorder_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 10)");
        $stmt->bind_param("ssssddsi", $name, $fullCategory, $unit, $supplier, $s_price, $n_price, $margin, $inventory);
        
        if ($stmt->execute()) {
            $success++;
        }
    }
    fclose($handle);
    echo "<h3>✅ Successfully imported $success products!</h3>";
    echo "<a href='products.php'>Go to Inventory Dashboard</a>";
}

$conn->close();
?>