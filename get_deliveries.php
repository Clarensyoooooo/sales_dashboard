<?php
// get_deliveries.php
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();

// Set Timezone to Philippines to match your operations
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// LOGIC:
// 1. Fetch ALL pending items (date_delivered IS NULL or '0000-00-00')
// 2. Fetch ALL items delivered TODAY (so they don't vanish immediately)
// 3. Sort by Company -> PO -> Date
$sql = "SELECT id, date, company, address, contact_person_contact, 
               item, quantity_requested, po_number, date_delivered, payment_term
        FROM sales 
        WHERE date_delivered IS NULL 
           OR date_delivered = '0000-00-00' 
           OR date_delivered >= '$today'
        ORDER BY company ASC, po_number ASC, item ASC";

$result = $conn->query($sql);

$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode($data);
?>