<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$query = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';

$conn = getDBConnection();

// If query is empty or very short, show all active customers (for dropdown)
if (strlen($query) < 1) {
    $result = $conn->query("
        SELECT c.customer_id, c.account_number, c.subscriber_name, c.address, c.tel_no, c.monthly_fee, a.area_name
        FROM customers c
        LEFT JOIN areas a ON c.area_id = a.area_id
        WHERE c.status IN ('active', 'hold_disconnection')
        ORDER BY c.subscriber_name
        LIMIT 50
    ");
    
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
} else {
    // Search by account number, name, or address
    $stmt = $conn->prepare("
        SELECT c.customer_id, c.account_number, c.subscriber_name, c.address, c.tel_no, c.monthly_fee, a.area_name
        FROM customers c
        LEFT JOIN areas a ON c.area_id = a.area_id
        WHERE c.status IN ('active', 'hold_disconnection')
        AND (c.account_number LIKE ? OR c.subscriber_name LIKE ? OR c.address LIKE ?)
        ORDER BY c.subscriber_name
        LIMIT 20
    ");
    
    $search_term = "%$query%";
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    
    $stmt->close();
}

$conn->close();

echo json_encode($customers);
?>