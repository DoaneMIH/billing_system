<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

if ($customer_id == 0) {
    echo json_encode([]);
    exit();
}

$conn = getDBConnection();

$stmt = $conn->prepare("
    SELECT 
        b.billing_id,
        b.billing_month,
        b.billing_year,
        b.net_amount,
        b.status,
        b.due_date,
        COALESCE((SELECT SUM(amount_paid) FROM payments WHERE billing_id = b.billing_id), 0) as total_paid
    FROM billings b
    WHERE b.customer_id = ?
    AND b.status IN ('unpaid', 'partial')
    ORDER BY b.billing_year DESC, b.billing_month DESC
");

$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

$billings = [];
while ($row = $result->fetch_assoc()) {
    $billings[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($billings);
?>