<?php
require_once 'config.php';
requireLogin();
requirePermission('manage_products');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $primary_id = $_POST['primary_id'] ?? null;
    $duplicate_ids = isset($_POST['duplicate_ids']) ? json_decode($_POST['duplicate_ids'], true) : [];

    if (!$primary_id || empty($duplicate_ids)) {
        echo json_encode(['success' => false, 'message' => 'Missing data.']);
        exit;
    }

    $conn = getDBConnection();
    $conn->begin_transaction();

    try {
        // 1. Fetch Primary Product
        $stmt = $conn->prepare("SELECT name, current_stock FROM products WHERE id = ?");
        $stmt->bind_param("i", $primary_id);
        $stmt->execute();
        $primary_res = $stmt->get_result()->fetch_assoc();
        
        if(!$primary_res) throw new Exception("Primary product not found.");
        
        $primary_name = $primary_res['name'];
        $new_stock = intval($primary_res['current_stock']);

        // 2. Fetch Duplicates to sum their stock and get their names
        $placeholders = implode(',', array_fill(0, count($duplicate_ids), '?'));
        $types = str_repeat('i', count($duplicate_ids));

        $stmt_dupes = $conn->prepare("SELECT id, name, current_stock FROM products WHERE id IN ($placeholders)");
        $stmt_dupes->bind_param($types, ...$duplicate_ids);
        $stmt_dupes->execute();
        $dupes_res = $stmt_dupes->get_result();
        
        $dupe_names = [];
        while ($row = $dupes_res->fetch_assoc()) {
            $new_stock += intval($row['current_stock']);
            $dupe_names[] = $row['name'];
        }

        // 3. Update Primary Product Stock
        $stmt_update = $conn->prepare("UPDATE products SET current_stock = ? WHERE id = ?");
        $stmt_update->bind_param("ii", $new_stock, $primary_id);
        $stmt_update->execute();

        // 4. Safely Update the `sales` table so previous records reflect the primary name
        if (!empty($dupe_names)) {
            $name_placeholders = implode(',', array_fill(0, count($dupe_names), '?'));
            $name_types = str_repeat('s', count($dupe_names));
            
            // Format param array: First item is primary name, followed by duplicate names
            $update_sales_types = "s" . $name_types;
            $update_sales_params = array_merge([$primary_name], $dupe_names);
            
            $stmt_sales = $conn->prepare("UPDATE sales SET item = ? WHERE item IN ($name_placeholders)");
            $stmt_sales->bind_param($update_sales_types, ...$update_sales_params);
            $stmt_sales->execute();
        }

        // 5. Purge the duplicates
        $stmt_delete = $conn->prepare("DELETE FROM products WHERE id IN ($placeholders)");
        $stmt_delete->bind_param($types, ...$duplicate_ids);
        $stmt_delete->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Products merged successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    $conn->close();
}
?>