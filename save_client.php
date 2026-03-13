<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$company = trim($_POST['company'] ?? '');
$tin = trim($_POST['tin'] ?? '');

if (empty($company)) {
    echo json_encode(['success' => false, 'message' => 'Company name is required']);
    exit;
}

$csvFile = 'CLIENT-TIN.csv';
$tempFile = 'CLIENT-TIN_temp.csv';
$found = false;

$rows = [];

// Read existing CSV
if (file_exists($csvFile) && ($handle = fopen($csvFile, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (trim($data[0]) === 'COMPANY') { 
            $rows[] = $data; // Keep Header
            continue;
        }
        // If company matches, update the TIN
        if (strcasecmp(trim($data[0]), $company) === 0) {
            $rows[] = [$company, $tin]; 
            $found = true;
        } else {
            $rows[] = $data;
        }
    }
    fclose($handle);
} else {
    // If CSV got deleted, recreate header
    $rows[] = ['COMPANY', 'TIN NUMBER'];
}

// If company wasn't found, append it
if (!$found) {
    $rows[] = [$company, $tin]; 
}

// Write back to the CSV
if (($handle = fopen($tempFile, "w")) !== FALSE) {
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);
    
    // Safely replace the old file with the new one
    if (rename($tempFile, $csvFile)) {
        // --- ADDED LOGGING HERE ---
        $logContext = $found ? 'Updated' : 'Added';
        logAction("Updated Client Data", "$logContext TIN for $company ($tin)");
        
        echo json_encode(['success' => true, 'message' => 'Client saved to CSV']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save file permissions']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to open CSV for writing']);
}
?>