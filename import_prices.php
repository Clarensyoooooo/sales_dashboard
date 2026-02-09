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
    'OS' => 'OFFICE SUPPLIES', // Maps to your form's category
    'PPE' => 'PPE',
    'TE' => 'OFFICE TOOLS AND EQUIPMENT'
];

echo "<h1>Importing Price List...</h1>";

$csvFile = "Centralized Suppliers' Price - CENTRALIZED WITH SUPPLIERS' PRICE.csv"; 

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
        
        // Column mapping based on your CSV:
        // 0=Product, 1=Cat, 2=Unit, 3=Supplier, 4=Supp Price, 5=NAM Price
        $name = trim($data[0]);
        
        // Stop if no product name
        if (empty($name)) continue;

        $catCode = trim($data[1]);
        $unit = trim($data[2]);
        $supplier = trim($data[3]);
        
        // Clean prices (Remove '₱', commas, and spaces)
        $s_price = (float) preg_replace('/[₱,\s]/', '', $data[4]);
        $n_price = (float) preg_replace('/[₱,\s]/', '', $data[5]);
        $margin = trim($data[6]);

        // Map Category Code to Full Name if possible
        $fullCategory = isset($categoryMap[$catCode]) ? $categoryMap[$catCode] : $catCode;

        $stmt = $conn->prepare("INSERT INTO products (name, category_code, unit, supplier, supplier_price, nam_price, margin) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdds", $name, $fullCategory, $unit, $supplier, $s_price, $n_price, $margin);
        
        if ($stmt->execute()) {
            $success++;
        }
    }
    fclose($handle);
    echo "<h3>✅ Successfully imported $success products!</h3>";
    echo "<a href='form.php'>Go to Entry Form</a>";
}

$conn->close();
?>