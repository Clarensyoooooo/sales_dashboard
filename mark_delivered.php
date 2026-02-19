<?php
// mark_delivered.php
header('Content-Type: application/json');
require_once 'config.php';

// FIX: Set timezone so 'Today' in PHP matches 'Today' in your database logic
date_default_timezone_set('Asia/Manila'); 

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) { echo json_encode(['success' => false, 'message' => 'No ID']); exit; }

$conn = getDBConnection();

// 1. Get Context
$stmt = $conn->prepare("SELECT po_number, company, payment_term FROM sales WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) { echo json_encode(['success' => false, 'message' => 'Item not found']); exit; }

$po = $item['po_number'];
$company = $item['company'];
$termStr = $item['payment_term'];

// 2. Update Item Status
// Use PHP date to be consistent
$today = date('Y-m-d');
$updateSelf = $conn->prepare("UPDATE sales SET date_delivered = ? WHERE id = ?");
$updateSelf->bind_param("si", $today, $id);

if (!$updateSelf->execute()) {
    echo json_encode(['success' => false, 'message' => 'Update failed']); exit;
}

// 3. CHECK GROUP STATUS (Logic for Timer)
// We check if there are any items with the same PO & Company that are NOT delivered yet.
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
        // Fallback for no PO
        $updateSingle = $conn->prepare("UPDATE sales SET due_date = ? WHERE id = ?");
        $updateSingle->bind_param("si", $dueDate, $id);
        $updateSingle->execute();
    }
}

echo json_encode(['success' => true, 'group_completed' => ($pendingCount == 0)]);
?>