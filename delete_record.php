<?php
header('Content-Type: application/json');
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$conn = getDBConnection();
$id = intval($_POST['id']);

// Get record details before deleting for the log
$check = $conn->prepare("SELECT company, item FROM sales WHERE id = ?");
$check->bind_param("i", $id);
$check->execute();
$record = $check->get_result()->fetch_assoc();
$check->close();

if (!$record) {
    echo json_encode(['success' => false, 'message' => 'Record not found']);
    exit;
}

// Delete record
$sql = "DELETE FROM sales WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    logAction('Deleted Sale', "Deleted sale record for {$record['company']} (Item: {$record['item']})");
    echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting record: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>