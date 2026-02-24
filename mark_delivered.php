<?php
// mark_delivered.php
header('Content-Type: application/json');
require_once 'config.php';

date_default_timezone_set('Asia/Manila'); 

$input = json_decode(file_get_contents('php://input'), true);

$deliveries = [];
if (isset($input['deliveries']) && is_array($input['deliveries'])) {
    $deliveries = $input['deliveries']; // Array from bulk operation
} elseif (isset($input['id'])) {
    $deliveries = [ ['id' => $input['id'], 'qty' => $input['qty'] ?? null] ]; // Fallback for single item delivery calls
}

if (empty($deliveries)) {
    echo json_encode(['success' => false, 'message' => 'No items provided for delivery']);
    exit;
}

$conn = getDBConnection();
$today = date('Y-m-d');
$completedGroups = []; // Keeping track of entirely completed POs to return a notification

// --- START TRANSACTION ---
$conn->begin_transaction();

try {
    foreach ($deliveries as $del) {
        $id = intval($del['id']);
        
        $stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        
        if (!$item) continue; 
        
        $origQty = intval($item['quantity_requested']);
        $qtyToDeliver = intval($del['qty'] ?? $origQty);
        
        if ($qtyToDeliver < 1 || $qtyToDeliver > $origQty) {
            continue; // Skip invalid amounts
        }

        $po = $item['po_number'];
        $company = $item['company'];
        $termStr = $item['payment_term'];
        
        // --- 2. HANDLE DELIVERY (FULL OR PARTIAL) ---
        if ($qtyToDeliver < $origQty) {
            $remainingQty = $origQty - $qtyToDeliver;
            
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
            
            $upd = $conn->prepare("UPDATE sales SET quantity_requested = ?, total_actual_amount = ?, total_nam_amount = ?, income = ?, income_percent = ?, date_delivered = ? WHERE id = ?");
            
            $del_actual = $sp * $qtyToDeliver;
            $del_nam = $np * $qtyToDeliver;
            $del_income = $del_nam - $del_actual;
            $del_percent = ($del_nam > 0) ? ($del_income / $del_nam) * 100 : 0;
            
            $upd->bind_param("iddddsi", $qtyToDeliver, $del_actual, $del_nam, $del_income, $del_percent, $today, $id);
            $upd->execute();
            
            // LOG ACTION
            logAction('Partial Delivery', "Delivered $qtyToDeliver out of $origQty items for $company (Item: {$item['item']})");

        } else {
            $updateSelf = $conn->prepare("UPDATE sales SET date_delivered = ? WHERE id = ?");
            $updateSelf->bind_param("si", $today, $id);
            $updateSelf->execute();
            
            // LOG ACTION
            logAction('Full Delivery', "Delivered all $origQty items for $company (Item: {$item['item']})");
        }

        // 3. CHECK GROUP STATUS FOR THE TIMER
        // (Ensures Timer is initialized *only* when an entire PO finishes execution successfully without pending counts!)
        $pendingCount = 0;
        if (!empty($po)) {
            $check = $conn->prepare("SELECT COUNT(*) as pending FROM sales WHERE po_number = ? AND company = ? AND (date_delivered IS NULL OR date_delivered = '0000-00-00')");
            $check->bind_param("ss", $po, $company);
            $check->execute();
            $pendingCount = $check->get_result()->fetch_assoc()['pending'];
        }

        // 4. START TIMER (Apply Due Date) ONLY IF the PO or specific unassociated item Group is Fully Complete
        if ($pendingCount == 0) {
            
            // Fix: Default to 30 days instead of 0 if payment term is blank. 
            // Handles parsing of terms like "30 Days", "Net 30", or "COD"
            $days = 30; 
            if (!empty($termStr)) {
                if (preg_match('/(\d+)/', $termStr, $matches)) {
                    $days = intval($matches[1]);
                } elseif (stripos($termStr, 'cod') !== false || stripos($termStr, 'cash') !== false) {
                    $days = 0;
                }
            }
            
            $dueDate = date('Y-m-d', strtotime("$today +$days days"));
            
            if (!empty($po)) {
                $updateGroup = $conn->prepare("UPDATE sales SET due_date = ? WHERE po_number = ? AND company = ?");
                $updateGroup->bind_param("sss", $dueDate, $po, $company);
                $updateGroup->execute();
                
                $completedGroups[] = $po; // Add to Notification Array 
            } else {
                $updateSingle = $conn->prepare("UPDATE sales SET due_date = ? WHERE id = ?");
                $updateSingle->bind_param("si", $dueDate, $id);
                $updateSingle->execute();
            }
        }
    }

    $conn->commit();
    
    // Formatting a helpful alert indicating exactly if PO's got closed and triggered timestamps correctly!
    $msg = "Deliveries updated successfully.";
    if (count($completedGroups) > 0) {
        $msg .= " PO(s) " . implode(', ', array_unique($completedGroups)) . " are fully delivered. The due date timers have started!";
    }

    echo json_encode(['success' => true, 'message' => $msg, 'group_completed' => (count($completedGroups) > 0)]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>