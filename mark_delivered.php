<?php
// mark_delivered.php
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'No ID']);
    exit;
}

$conn = getDBConnection();
// We simply stamp NOW() into the column.
// This removes it from the "Pending" list in get_deliveries.php
$stmt = $conn->prepare("UPDATE sales SET date_delivered = CURRENT_DATE() WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
?>