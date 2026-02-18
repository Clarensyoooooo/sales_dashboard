<?php
// mark_delivered.php
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) { echo json_encode(['success' => false, 'message' => 'No ID']); exit; }

$conn = getDBConnection();

// 1. Get context: What Group (PO + Company) does this item belong to?
$stmt = $conn->prepare("SELECT po_number, company, payment_term FROM sales WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) { echo json_encode(['success' => false, 'message' => 'Item not found']); exit; }

$po = $item['po_number'];
$company = $item['company'];
$termStr = $item['payment_term'];

// 2. Mark THIS item as delivered
$updateSelf = $conn->prepare("UPDATE sales SET date_delivered = CURRENT_DATE() WHERE id = ?");
$updateSelf->bind_param("i", $id);
if (!$updateSelf->execute()) {
    echo json_encode(['success' => false, 'message' => 'Update failed']); exit;
}

// 3. CHECK GROUP STATUS: Are there any undelivered items left in this PO?
// We check for any row with same PO & Company that has date_delivered IS NULL
$pendingCount = 0;
if (!empty($po)) {
    $check = $conn->prepare("SELECT COUNT(*) as pending FROM sales WHERE po_number = ? AND company = ? AND date_delivered IS NULL");
    $check->bind_param("ss", $po, $company);
    $check->execute();
    $pendingCount = $check->get_result()->fetch_assoc()['pending'];
}

// 4. START TIMER (Apply Due Date) ONLY IF Group is Complete
if ($pendingCount == 0) {
    // Parse "30 Days" to integer 30
    preg_match('/(\d+)/', $termStr, $matches);
    $days = isset($matches[1]) ? intval($matches[1]) : 0;
    
    // Calculate Due Date
    $dueDate = date('Y-m-d', strtotime("+$days days"));
    
    // Update ALL items in this Group with the calculated Due Date
    if (!empty($po)) {
        $updateGroup = $conn->prepare("UPDATE sales SET due_date = ? WHERE po_number = ? AND company = ?");
        $updateGroup->bind_param("sss", $dueDate, $po, $company);
        $updateGroup->execute();
    } else {
        // Fallback for items without PO
        $updateSingle = $conn->prepare("UPDATE sales SET due_date = ? WHERE id = ?");
        $updateSingle->bind_param("si", $dueDate, $id);
        $updateSingle->execute();
    }
}

echo json_encode(['success' => true, 'group_completed' => ($pendingCount == 0)]);
?>