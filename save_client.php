<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Fetch all details from the modal
$company = trim($_POST['company'] ?? '');
$tin = trim($_POST['tin'] ?? '');
$address = trim($_POST['address'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$term = trim($_POST['term'] ?? '');

if (empty($company)) {
    echo json_encode(['success' => false, 'message' => 'Company name is required']);
    exit;
}

$conn = getDBConnection();

// Secure variables for database
$compEsc = $conn->real_escape_string($company);
$tinEsc = $conn->real_escape_string($tin);
$addrEsc = $conn->real_escape_string($address);
$contEsc = $conn->real_escape_string($contact);
$termEsc = $conn->real_escape_string($term);

// 1. UPDATE THE DATABASE DIRECTLY 
// This instantly forces quotations.php and form.php to see the new Address, Terms, and Contact!
$conn->query("UPDATE sales SET tin='$tinEsc', address='$addrEsc', contact_person_contact='$contEsc', payment_term='$termEsc' WHERE company='$compEsc'");
$conn->query("UPDATE quotations SET payment_term='$termEsc' WHERE company='$compEsc'");

// 2. UPDATE THE CORRECT CSV FILE
$csvFile = 'CLIENT-TIN - Sheet1.csv';
$tempFile = 'CLIENT-TIN_temp.csv';
$found = false;

$rows = [];

// Read existing master CSV
if (file_exists($csvFile) && ($handle = fopen($csvFile, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (trim($data[0]) === 'COMPANY') { 
            $rows[] = $data; // Keep Header
            continue;
        }
        
        // If company matches, update with ALL data (Format: Company, Address, TIN)
        if (strcasecmp(trim($data[0]), $company) === 0) {
            $rows[] = [$company, $address, $tin]; 
            $found = true;
        } else {
            $rows[] = $data;
        }
    }
    fclose($handle);
} else {
    // If CSV is completely missing, create headers
    $rows[] = ['COMPANY', 'COMPANY ADDRESS', 'TIN NUMBER'];
}

// If company wasn't found in the CSV, append it
if (!$found) {
    $rows[] = [$company, $address, $tin]; 
}

// Write back to the CSV
if (($handle = fopen($tempFile, "w")) !== FALSE) {
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);
    
    // Safely replace the old file with the new one
    if (rename($tempFile, $csvFile)) {
        $logContext = $found ? 'Updated' : 'Added';
        logAction("Updated Client Profile", "$logContext details for $company");
        
        echo json_encode(['success' => true, 'message' => 'Client updated globally in Database and CSV']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save file permissions']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to open CSV for writing']);
}
?>