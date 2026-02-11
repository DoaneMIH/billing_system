<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$query = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

$conn = getDBConnection();

$stmt = $conn->prepare("
    SELECT customer_id, account_number, subscriber_name, address, tel_no
    FROM customers
    WHERE status = 'active'
    AND (account_number LIKE ? OR subscriber_name LIKE ? OR address LIKE ?)
    ORDER BY subscriber_name
    LIMIT 10
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
$conn->close();

echo json_encode($customers);
?>