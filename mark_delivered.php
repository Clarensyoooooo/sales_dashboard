<?php
// mark_delivered.php
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'No ID provided']);
        exit;
    }

    $conn = getDBConnection();
    
    // Update date_delivered to CURRENT DATE
    $stmt = $conn->prepare("UPDATE sales SET date_delivered = CURRENT_DATE() WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }

    $stmt->close();
    $conn->close();
}
?>