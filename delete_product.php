<?php
header('Content-Type: application/json');
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$id = intval($_POST['id']);

// Get name before delete
$check = $conn->prepare("SELECT name FROM products WHERE id = ?");
$check->bind_param("i", $id);
$check->execute();
$prod = $check->get_result()->fetch_assoc();
$check->close();

if (!$prod) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    logAction('Deleted Product', "Deleted inventory item: {$prod['name']}");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}

$conn->close();
?>