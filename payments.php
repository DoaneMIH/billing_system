<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])||!in_array($_SESSION['role'],['cashier','admin'])){header("Location: index.php");exit();}
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD']=='POST'&&isset($_POST['action'])&&$_POST['action']=='record_payment'){
    $billing_id=intval($_POST['billing_id']);$customer_id=intval($_POST['customer_id']);
    $or_number=sanitize_input($_POST['or_number']);$payment_date=sanitize_input($_POST['payment_date']);
    $amount_paid=floatval($_POST['amount_paid']);$payment_method=sanitize_input($_POST['payment_method']);
    $remarks=sanitize_input($_POST['remarks']);
    $conn->begin_transaction();
    try{
        $stmt=$conn->prepare("INSERT INTO payments (billing_id,customer_id,or_number,payment_date,amount_paid,payment_method,cashier_id,remarks) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iissdsis",$billing_id,$customer_id,$or_number,$payment_date,$amount_paid,$payment_method,$_SESSION['user_id'],$remarks);
        $stmt->execute();$payment_id=$stmt->insert_id;$stmt->close();
        $result=$conn->query("SELECT net_amount,(SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE billing_id=$billing_id) as total_paid FROM billings WHERE billing_id=$billing_id");
        $billing=$result->fetch_assoc();
        $new_status=$billing['total_paid']>=$billing['net_amount']?'paid':($billing['total_paid']>0?'partial':'unpaid');
        $conn->query("UPDATE billings SET status='$new_status' WHERE billing_id=$billing_id");
        log_activity($_SESSION['user_id'],'RECORD_PAYMENT','payments',$payment_id,"Recorded payment OR# $or_number - ₱".number_format($amount_paid,2));
        $conn->commit();$success="Payment recorded! OR# $or_number";
    }catch(Exception $e){$conn->rollback();$error="Error: ".$e->getMessage();}
}

$or_result=$conn->query("SELECT or_number FROM payments ORDER BY payment_id DESC LIMIT 1");
if($or_result->num_rows>0){$last_or=$or_result->fetch_assoc()['or_number'];$last_num=intval(preg_replace('/\D/','',$last_or));$next_or='OR-'.str_pad($last_num+1,3,'0',STR_PAD_LEFT);}else{$next_or='OR-001';}

$report_date=isset($_GET['report_date'])?sanitize_input($_GET['report_date']):date('Y-m-d');
$recent_payments=$conn->query("SELECT p.*,c.subscriber_name,c.account_number,b.billing_month,b.billing_year,u.full_name as cashier_name FROM payments p JOIN customers c ON p.customer_id=c.customer_id JOIN billings b ON p.billing_id=b.billing_id LEFT JOIN users u ON p.cashier_id=u.user_id ORDER BY p.payment_date DESC,p.created_at DESC LIMIT 50");
$daily_payments=$conn->query("SELECT p.*,c.subscriber_name,c.account_number,b.billing_month,b.billing_year,u.full_name as cashier_name FROM payments p JOIN customers c ON p.customer_id=c.customer_id JOIN billings b ON p.billing_id=b.billing_id LEFT JOIN users u ON p.cashier_id=u.user_id WHERE p.payment_date='$report_date' ORDER BY p.created_at ASC");
$daily_total=$conn->query("SELECT COALESCE(SUM(amount_paid),0) as total FROM payments WHERE payment_date='$report_date'")->fetch_assoc()['total'];
$daily_count=$conn->query("SELECT COUNT(*) as cnt FROM payments WHERE payment_date='$report_date'")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments - AR NOVALINK</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header no-print"><h1>Payment Processing</h1><p>Record payments and generate daily reports</p></div>

        <!-- Print header for daily report -->
        <div class="print-header">
            <img src="images/headerlogo.png" alt="NovaLink" class="print-header-logo">
            <h2>DAILY PAYMENT REPORT</h2>
            <h3><?php echo date('F d, Y', strtotime($report_date)); ?></h3>
            <p class="text-muted">Generated: <?php echo date('F d, Y h:i A'); ?></p>
        </div>

        <?php if(isset($success)):?><div class="alert alert-success no-print"><?php echo $success;?></div><?php endif;?>
        <?php if(isset($error)):?><div class="alert alert-error no-print"><?php echo $error;?></div><?php endif;?>

        <!-- Record Payment Form (hidden on print) -->
        <div class="widget mb-3 no-print">
            <div class="widget-header"><h2>Record New Payment</h2></div>
            <div class="widget-content">
                <!-- <div class="or-number-banner">
                    <div><span class="or-label">Official Receipt Number</span></div>
                    <div class="or-value"><?php echo $next_or; ?></div>
                </div> -->
                <form method="POST">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="or_number" value="<?php echo $next_or;?>">
                    <div class="form-row">
                        <div class="form-group"><label>OR Number</label><input type="text" class="or-number-field" value="<?php echo $next_or;?>" readonly></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Search Subscriber *</label>
                            <input type="text" id="search_customer" placeholder="Type to search..." autocomplete="off">
                            <div id="customer_results" class="position-relative"></div>
                            <input type="hidden" id="customer_id" name="customer_id" required>
                            <div id="selected_customer" class="d-none payment-selected-box mt-1"><strong>Selected:</strong> <span id="selected_customer_name"></span></div>
                        </div>
                        <div class="form-group"><label>Billing Period *</label>
                            <select id="billing_period" name="billing_id" required disabled><option value="">Select subscriber first</option></select>
                            <div id="billing_info" class="validation-msg"></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Payment Date *</label><input type="date" name="payment_date" value="<?php echo date('Y-m-d');?>" required></div>
                        <div class="form-group"><label>Advance Payment *</label><input type="number" step="0.01" name="amount_paid" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Payment Method *</label><select name="payment_method" required><option value="cash">Cash</option><option value="check">Check</option><option value="online">Online</option><option value="others">Others</option></select></div>
                        <div class="form-group"><label>Remarks</label><textarea name="remarks" rows="2"></textarea></div>
                    </div>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </form>
            </div>
        </div>

        <!-- Daily Payment Report (this section prints) -->
        <div class="widget mb-3">
            <div class="widget-header">
                <h2>Daily Payment Report</h2>
                <div class="d-flex gap-10 no-print">
                    <form method="GET" class="d-flex gap-10"><input type="date" name="report_date" value="<?php echo $report_date;?>"><button type="submit" class="btn btn-secondary btn-sm">View</button></form>
                    <button onclick="window.print()" class="btn btn-primary btn-sm">🖨️ Print</button>
                </div>
            </div>
            <div class="widget-content">
                <!-- Summary line (hidden on print — the print-header covers it) -->
                <div class="form-row mb-2 no-print">
                    <div><strong>Date:</strong> <?php echo date('F d, Y',strtotime($report_date));?></div>
                    <div><strong>Total:</strong> <?php echo $daily_count;?> payments</div>
                    <div><strong>Collections:</strong> <?php echo format_currency($daily_total);?></div>
                </div>
                <table>
                    <thead><tr><th>OR #</th><th>Subscriber</th><th>Account #</th><th>Period</th><th>Amount</th><th>Method</th><th>Cashier</th></tr></thead>
                    <tbody>
                    <?php if($daily_payments->num_rows>0):while($dp=$daily_payments->fetch_assoc()):?>
                    <tr>
                        <td><?php echo htmlspecialchars($dp['or_number']);?></td>
                        <td><?php echo htmlspecialchars($dp['subscriber_name']);?></td>
                        <td><?php echo htmlspecialchars($dp['account_number']);?></td>
                        <td><?php echo get_month_name($dp['billing_month']).' '.$dp['billing_year'];?></td>
                        <td><?php echo format_currency($dp['amount_paid']);?></td>
                        <td><?php echo ucfirst($dp['payment_method']);?></td>
                        <td><?php echo htmlspecialchars($dp['cashier_name']??'N/A');?></td>
                    </tr>
                    <?php endwhile;else:?>
                    <tr><td colspan="7" class="text-center">No payments for this date</td></tr>
                    <?php endif;?>
                    <tr class="table-row-total">
                        <td colspan="4" class="text-right">TOTAL COLLECTIONS:</td>
                        <td><?php echo format_currency($daily_total);?></td>
                        <td colspan="2"><?php echo $daily_count;?> payment(s)</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Payments (hidden on print) -->
        <div class="table-container no-print">
            <div class="table-header"><h2>Recent Payments</h2></div>
            <table>
                <thead><tr><th>OR #</th><th>Date</th><th>Subscriber</th><th>Account #</th><th>Period</th><th>Amount</th><th>Method</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if($recent_payments->num_rows>0):while($row=$recent_payments->fetch_assoc()):?>
                <tr>
                    <td><?php echo htmlspecialchars($row['or_number']);?></td>
                    <td><?php echo date('M d, Y',strtotime($row['payment_date']));?></td>
                    <td><?php echo htmlspecialchars($row['subscriber_name']);?></td>
                    <td><?php echo htmlspecialchars($row['account_number']);?></td>
                    <td><?php echo get_month_name($row['billing_month']).' '.$row['billing_year'];?></td>
                    <td><?php echo format_currency($row['amount_paid']);?></td>
                    <td><?php echo ucfirst($row['payment_method']);?></td>
                    <td class="actions-cell">
                        <a href="print_invoice.php?id=<?php echo $row['payment_id'];?>" target="_blank" class="btn btn-sm btn-primary">Invoice</a>
                        <!-- <a href="print_receipt.php?id=<?php echo $row['payment_id'];?>" target="_blank" class="btn btn-sm btn-secondary">Receipt</a> -->
                    </td>
                </tr>
                <?php endwhile;else:?>
                <tr><td colspan="8" class="text-center">No payments</td></tr>
                <?php endif;?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<script src="js/script.js"></script>
<script src="js/payment.js"></script>
<?php include "includes/footer.php"; ?>
</body>
</html>
<?php $conn->close(); ?>
