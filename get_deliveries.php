<?php
// get_deliveries.php
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();

// FILTER: specific logic for the driver view
// We only want items that are NOT delivered yet (date_delivered is NULL)
// OR items delivered TODAY (so the driver can see what they just finished)

$sql = "SELECT id, date, company, address, contact_person_contact, 
               item, quantity_requested, po_number, date_delivered 
        FROM sales 
        WHERE date_delivered IS NULL 
           OR date_delivered = '0000-00-00' 
           OR date_delivered = CURRENT_DATE()
        ORDER BY date_delivered ASC, id DESC";

$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>