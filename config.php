<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sales_dashboard');

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Initialize database and create tables if they don't exist
function initializeDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    $conn->query($sql);
    
    $conn->select_db(DB_NAME);
    
    // Create sales table with exact CSV structure
    $sql = "CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        sn VARCHAR(10),
        po_number VARCHAR(50),
        company VARCHAR(100),
        category VARCHAR(100),
        item VARCHAR(255),
        quantity_requested INT,
        suppliers_price DECIMAL(15,2),
        total_actual_amount DECIMAL(15,2),
        nam_unit_price DECIMAL(15,2),
        total_nam_amount DECIMAL(15,2),
        total_nam_amount_sub_total DECIMAL(15,2),
        income DECIMAL(15,2),
        income_percent DECIMAL(5,2),
        date_delivered DATE,
        payment_term VARCHAR(50),
        due_date DATE,
        si_number VARCHAR(50),
        remarks TEXT,
        supplier VARCHAR(100),
        address VARCHAR(255),
        tin VARCHAR(50),
        sales_invoice_no VARCHAR(50),
        contact_person_contact VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// Function to import CSV data
function importCSV($filepath) {
    $conn = getDBConnection();
    
    if (($handle = fopen($filepath, "r")) !== FALSE) {
        $row = 0;
        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            $row++;
            
            // Skip header row and empty rows
            if ($row <= 2 || empty($data[0])) {
                continue;
            }
            
            // Clean currency values
            $suppliers_price = preg_replace('/[₱,\s]/', '', $data[7]);
            $total_actual_amount = preg_replace('/[₱,\s]/', '', $data[8]);
            $nam_unit_price = preg_replace('/[₱,\s]/', '', $data[9]);
            $total_nam_amount = preg_replace('/[₱,\s]/', '', $data[10]);
            $total_nam_amount_sub_total = preg_replace('/[₱,\s]/', '', $data[11]);
            $income = preg_replace('/[₱,\s]/', '', $data[12]);
            $income_percent = preg_replace('/[%\s]/', '', $data[13]);
            
            // Format dates
            $date = !empty($data[0]) ? date('Y-m-d', strtotime($data[0])) : null;
            $date_delivered = !empty($data[15]) ? date('Y-m-d', strtotime($data[15])) : null;
            $due_date = !empty($data[17]) ? date('Y-m-d', strtotime($data[17])) : null;
            
            $sql = "INSERT INTO sales (
                date, sn, po_number, company, category, item, quantity_requested,
                suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount,
                total_nam_amount_sub_total, income, income_percent, date_delivered,
                payment_term, due_date, si_number, remarks, supplier, address, tin,
                sales_invoice_no, contact_person_contact
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssssissdddddssssssssss",
                $date, $data[1], $data[2], $data[3], $data[4], $data[5], $data[6],
                $suppliers_price, $total_actual_amount, $nam_unit_price, $total_nam_amount,
                $total_nam_amount_sub_total, $income, $income_percent, $date_delivered,
                $data[16], $due_date, $data[18], $data[19], $data[20], $data[21], $data[22],
                $data[23], $data[24]
            );
            
            $stmt->execute();
        }
        fclose($handle);
        return true;
    }
    return false;
}

// Initialize database on first run
initializeDatabase();
?>
