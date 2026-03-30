<?php
require_once 'config.php';
requireLogin();
requirePermission('manage_sales');

header('Content-Type: application/json');

// Ensure this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$conn = getDBConnection();

// Helper function to safely handle empty dates so MySQL doesn't crash
function cleanDate($val) {
    return empty(trim($val ?? '')) ? null : trim($val);
}

// 1. Catch all basic text and date fields
$id = intval($_POST['id']);
$date = cleanDate($_POST['date']);
$sn = trim($_POST['sn'] ?? '');
$po_number = trim($_POST['po_number'] ?? '');
$company = trim($_POST['company'] ?? '');
$address = trim($_POST['address'] ?? '');
$tin = trim($_POST['tin'] ?? '');
$contact = trim($_POST['contact_person_contact'] ?? '');
$category = trim($_POST['category'] ?? '');
$item = trim($_POST['item'] ?? '');
$qty = intval($_POST['quantity_requested'] ?? 0);

// 2. Catch financial inputs
$s_price = floatval($_POST['suppliers_price'] ?? 0);
$n_price = floatval($_POST['nam_unit_price'] ?? 0);

// 3. Auto-calculate totals on the server to prevent front-end tampering
$t_actual = $qty * $s_price;
$t_nam = $qty * $n_price;
$income = $t_nam - $t_actual;
$income_pct = ($t_nam > 0) ? ($income / $t_nam) * 100 : 0;

// 4. Catch the newly added fields
$supplier = trim($_POST['supplier'] ?? '');
$date_delivered = cleanDate($_POST['date_delivered']);
$payment_term = trim($_POST['payment_term'] ?? '');
$due_date = cleanDate($_POST['due_date']);
$si_number = trim($_POST['si_number'] ?? '');
$buyer = trim($_POST['buyer'] ?? '');
$sales_invoice_no = trim($_POST['sales_invoice_no'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');

// 5. Build the massive update query
$sql = "UPDATE sales SET 
        date=?, sn=?, po_number=?, company=?, address=?, tin=?, contact_person_contact=?, 
        category=?, item=?, quantity_requested=?, suppliers_price=?, total_actual_amount=?, 
        nam_unit_price=?, total_nam_amount=?, income=?, income_percent=?, 
        supplier=?, date_delivered=?, payment_term=?, due_date=?, 
        si_number=?, buyer=?, sales_invoice_no=?, remarks=? 
        WHERE id=?";

$stmt = $conn->prepare($sql);

if ($stmt) {
    // Bind all 25 parameters (s = string, i = integer, d = double/decimal)
    $stmt->bind_param("sssssssssidddddsssssssssi", 
        $date, $sn, $po_number, $company, $address, $tin, $contact, 
        $category, $item, $qty, $s_price, $t_actual, 
        $n_price, $t_nam, $income, $income_pct, 
        $supplier, $date_delivered, $payment_term, $due_date, 
        $si_number, $buyer, $sales_invoice_no, $remarks, 
        $id
    );

    if ($stmt->execute()) {
        // Log the edit for security/tracking
        logAction('Updated Record', "Updated sales record ID $id ($item for $company)");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
}

$conn->close();
?>