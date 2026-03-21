<?php
/**
 * Real-time sync endpoint
 * Returns a data fingerprint: latest activity timestamp + key counts.
 * The client polls this and compares to detect changes.
 */
require_once '../config.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'unauthorized']);
    exit();
}

$conn = getDBConnection();

// Latest activity timestamp (main change indicator)
$latest = $conn->query("SELECT MAX(created_at) as ts FROM activity_logs")->fetch_assoc()['ts'] ?? '';

// Key counts that pages care about
$total_customers   = $conn->query("SELECT COUNT(*) as c FROM customers")->fetch_assoc()['c'];
$active_customers  = $conn->query("SELECT COUNT(*) as c FROM customers WHERE status='active'")->fetch_assoc()['c'];
$total_unpaid      = $conn->query("SELECT COUNT(*) as c FROM billings WHERE status='unpaid'")->fetch_assoc()['c'];
$total_payments    = $conn->query("SELECT COUNT(*) as c FROM payments")->fetch_assoc()['c'];
$total_billings    = $conn->query("SELECT COUNT(*) as c FROM billings")->fetch_assoc()['c'];
$disconnected      = $conn->query("SELECT COUNT(*) as c FROM customers WHERE status='disconnected'")->fetch_assoc()['c'];

$cm = date('n'); $cy = date('Y');
$monthly_revenue   = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as t FROM payments WHERE MONTH(payment_date)=$cm AND YEAR(payment_date)=$cy")->fetch_assoc()['t'];

$conn->close();

echo json_encode([
    'ts'                => $latest,
    'total_customers'   => (int)$total_customers,
    'active_customers'  => (int)$active_customers,
    'total_unpaid'      => (int)$total_unpaid,
    'total_payments'    => (int)$total_payments,
    'total_billings'    => (int)$total_billings,
    'disconnected'      => (int)$disconnected,
    'monthly_revenue'   => (float)$monthly_revenue
]);
?>
