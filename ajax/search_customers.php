<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$query = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';
$area_filter = isset($_GET['area']) ? intval($_GET['area']) : 0;
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

$conn = getDBConnection();

// Build the SQL query
$sql = "SELECT c.customer_id, c.account_number, c.subscriber_name, c.address, c.tel_no, c.monthly_fee, c.status, a.area_name, p.package_name
        FROM customers c
        LEFT JOIN areas a ON c.area_id = a.area_id
        LEFT JOIN packages p ON c.package_id = p.package_id
        WHERE 1=1";

// Add search filter
if (strlen($query) > 0) {
    $search_term = "%$query%";
    $sql .= " AND (c.account_number LIKE '$search_term' OR c.subscriber_name LIKE '$search_term' OR c.address LIKE '$search_term' OR c.tel_no LIKE '$search_term')";
}

// Add area filter
if ($area_filter > 0) {
    $sql .= " AND c.area_id = $area_filter";
}

// Add status filter  
if ($status_filter !== '') {
    $sql .= " AND c.status = '$status_filter'";
}

$sql .= " ORDER BY c.subscriber_name LIMIT 100";

$result = $conn->query($sql);

$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}

$conn->close();

echo json_encode($customers);
?>