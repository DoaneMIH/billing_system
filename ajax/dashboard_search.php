<?php
require_once '../config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit(); }

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
if (strlen($query) < 1) { echo json_encode([]); exit(); }

$conn = getDBConnection();
$results = [];
$search = $conn->real_escape_string($query);

$sql = "SELECT c.customer_id, c.account_number, c.subscriber_name, c.account_name, c.address, c.status, a.area_name 
        FROM customers c LEFT JOIN areas a ON c.area_id = a.area_id
        WHERE c.subscriber_name LIKE '%$search%' OR c.account_number LIKE '%$search%' OR c.account_name LIKE '%$search%' OR c.address LIKE '%$search%'
        ORDER BY c.subscriber_name LIMIT 10";
$r = $conn->query($sql);
while ($row = $r->fetch_assoc()) {
    $sc = match($row['status']) { 'active'=>'success', 'disconnected'=>'danger', 'reconnected'=>'info', 'pending_installation'=>'warning', default=>'secondary' };
    $results[] = ['type'=>'customer','name'=>$row['subscriber_name'],'detail'=>$row['account_number'].' | '.($row['area_name']??$row['address']),
        'url'=>'customer_ledger.php?id='.$row['customer_id'],'status'=>ucfirst(str_replace('_',' ',$row['status'])),'status_class'=>$sc];
}

$sql2 = "SELECT p.payment_id, p.or_number, p.amount_paid, p.payment_date, c.subscriber_name, c.customer_id
         FROM payments p JOIN customers c ON p.customer_id = c.customer_id WHERE p.or_number LIKE '%$search%' ORDER BY p.payment_date DESC LIMIT 5";
$r2 = $conn->query($sql2);
while ($row = $r2->fetch_assoc()) {
    $results[] = ['type'=>'payment','name'=>'OR# '.$row['or_number'],
        'detail'=>$row['subscriber_name'].' | ₱'.number_format($row['amount_paid'],2).' | '.date('M d, Y',strtotime($row['payment_date'])),
        'url'=>'customer_ledger.php?id='.$row['customer_id'],'status'=>'Payment','status_class'=>'primary'];
}
$conn->close();
echo json_encode($results);
?>
