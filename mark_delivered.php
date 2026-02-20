<?php
// mark_delivered.php
header('Content-Type: application/json');
require_once 'config.php';

date_default_timezone_set('Asia/Manila'); 

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) { echo json_encode(['success' => false, 'message' => 'No ID']); exit; }

$conn = getDBConnection();

// 1. Get Context
$stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) { echo json_encode(['success' => false, 'message' => 'Item not found']); exit; }

$origQty = intval($item['quantity_requested']);
$qtyToDeliver = intval($input['qty'] ?? $origQty);

if ($qtyToDeliver < 1 || $qtyToDeliver > $origQty) {
    echo json_encode(['success' => false, 'message' => 'Invalid delivery quantity']); exit;
}

$po = $item['po_number'];
$company = $item['company'];
$termStr = $item['payment_term'];
$today = date('Y-m-d');

// --- 2. HANDLE DELIVERY (FULL OR PARTIAL) ---

if ($qtyToDeliver < $origQty) {
    // --- PARTIAL DELIVERY LOGIC ---
    // We split the row into two to keep revenue and reporting completely accurate.
    $remainingQty = $origQty - $qtyToDeliver;
    
    // A. Insert the remaining quantity as a NEW pending record
    $ins = $conn->prepare("INSERT INTO sales (date, company, address, contact_person_contact, category, item, quantity_requested, suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount, income, income_percent, po_number, payment_term, remarks, date_delivered, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)");
    
    $sp = floatval($item['suppliers_price']);
    $np = floatval($item['nam_unit_price']);
    
    $rem_actual = $sp * $remainingQty;
    $rem_nam = $np * $remainingQty;
    $rem_income = $rem_nam - $rem_actual;
    $rem_percent = ($rem_nam > 0) ? ($rem_income / $rem_nam) * 100 : 0;
    
    $ins->bind_param("ssssssisddddssss", 
        $item['date'], $item['company'], $item['address'], $item['contact_person_contact'], 
        $item['category'], $item['item'], $remainingQty, $sp, $rem_actual, $np, $rem_nam, 
        $rem_income, $rem_percent, $item['po_number'], $item['payment_term'], $item['remarks']
    );
    $ins->execute();
    
    // B. Update the current record to show ONLY the quantity that was delivered today
    $upd = $conn->prepare("UPDATE sales SET quantity_requested = ?, total_actual_amount = ?, total_nam_amount = ?, income = ?, income_percent = ?, date_delivered = ? WHERE id = ?");
    
    $del_actual = $sp * $qtyToDeliver;
    $del_nam = $np * $qtyToDeliver;
    $del_income = $del_nam - $del_actual;
    $del_percent = ($del_nam > 0) ? ($del_income / $del_nam) * 100 : 0;
    
    $upd->bind_param("iddddsi", $qtyToDeliver, $del_actual, $del_nam, $del_income, $del_percent, $today, $id);
    $upd->execute();

} else {
    // --- FULL DELIVERY LOGIC ---
    $updateSelf = $conn->prepare("UPDATE sales SET date_delivered = ? WHERE id = ?");
    $updateSelf->bind_param("si", $today, $id);
    if (!$updateSelf->execute()) {
        echo json_encode(['success' => false, 'message' => 'Update failed']); exit;
    }
}

// 3. CHECK GROUP STATUS (Logic for Timer)
$pendingCount = 0;
if (!empty($po)) {
    // Check for NULL or Empty Date or '0000-00-00'
    $check = $conn->prepare("SELECT COUNT(*) as pending FROM sales WHERE po_number = ? AND company = ? AND (date_delivered IS NULL OR date_delivered = '0000-00-00')");
    $check->bind_param("ss", $po, $company);
    $check->execute();
    $pendingCount = $check->get_result()->fetch_assoc()['pending'];
}

// 4. START TIMER (Apply Due Date) ONLY IF Group is Complete
if ($pendingCount == 0) {
    preg_match('/(\d+)/', $termStr, $matches);
    $days = isset($matches[1]) ? intval($matches[1]) : 0;
    
    $dueDate = date('Y-m-d', strtotime("+$days days"));
    
    if (!empty($po)) {
        $updateGroup = $conn->prepare("UPDATE sales SET due_date = ? WHERE po_number = ? AND company = ?");
        $updateGroup->bind_param("sss", $dueDate, $po, $company);
        $updateGroup->execute();
    } else {
        $updateSingle = $conn->prepare("UPDATE sales SET due_date = ? WHERE id = ?");
        $updateSingle->bind_param("si", $dueDate, $id);
        $updateSingle->execute();
    }
}

echo json_encode(['success' => true, 'group_completed' => ($pendingCount == 0)]);
?>