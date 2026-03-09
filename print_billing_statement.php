<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$conn = getDBConnection();

// Support single or bulk printing
$customer_ids = [];
if (isset($_GET['id'])) {
    $customer_ids[] = intval($_GET['id']);
}
if (isset($_GET['ids'])) {
    $customer_ids = array_map('intval', explode(',', $_GET['ids']));
}
// Bulk filters
if (isset($_GET['bulk'])) {
    $sql = "SELECT c.customer_id FROM customers c WHERE 1=1";
    if (isset($_GET['status']) && $_GET['status'] != '') $sql .= " AND c.status = '" . $conn->real_escape_string($_GET['status']) . "'";
    if (isset($_GET['area_id']) && $_GET['area_id'] > 0) $sql .= " AND c.area_id = " . intval($_GET['area_id']);
    if (isset($_GET['package_id']) && $_GET['package_id'] > 0) $sql .= " AND c.package_id = " . intval($_GET['package_id']);
    $sql .= " ORDER BY c.subscriber_name";
    $r = $conn->query($sql);
    while ($row = $r->fetch_assoc()) $customer_ids[] = $row['customer_id'];
}

$billing_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$billing_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get reminder text
$reminder_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='billing_reminder'");
$reminder_text = $reminder_result && $reminder_result->num_rows > 0 ? $reminder_result->fetch_assoc()['setting_value'] : 
    "1. Please disregard this bill if already paid.\n2. If you wish to clarify any item on this bill please come to our office.\n3. Due date every End of the Month with 7 days grace period.\n4. If payment is not made after a span of 7 days, automatically\nTEMPORARY DISCONNECTION.";

$tagline_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='company_tagline'");
$tagline = $tagline_result && $tagline_result->num_rows > 0 ? $tagline_result->fetch_assoc()['setting_value'] : 'Thank you for keeping your account current. We value your continued patronage.';

if (empty($customer_ids)) { die("No customer selected"); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing Statement</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; background: #f5f5f5; }
        .statement-page { width: 210mm; min-height: 148mm; margin: 10px auto; background: white; padding: 8mm 10mm; box-shadow: 0 2px 10px rgba(0,0,0,0.1); page-break-after: always; }
        .statement-page:last-child { page-break-after: auto; }
        
        .logo-header { display: flex; align-items: center; margin-bottom: 5px; width: 100%;}
        .logo-header img { height: 20px; margin-right: 10px; }
        .logo-header .company-text { }
        .logo-header .company-text h1 { font-size: 20px; color: #002060; font-weight: bold; margin: 0; }
        .logo-header .company-text p { font-size: 10px; color: #333; margin: 0; }
        
        .title-bar { text-align: center; margin: 5px 0 8px 0; }
        .title-bar h2 { font-size: 14px; color: #002060; text-decoration: underline; }
        
        .info-section { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 11px; }
        .info-left { }
        .info-right { text-align: right; }
        .info-row { margin-bottom: 2px; }
        .info-label { font-weight: bold; }
        
        .content-area { display: flex; gap: 15px; margin-bottom: 8px; }
        .bill-summary { flex: 1; }
        .reminder-box { flex: 1; font-size: 10px; }
        .reminder-box p { margin-bottom: 3px; line-height: 1.4; }
        .reminder-box .important { color: #d32f2f; font-weight: bold; text-transform: uppercase; }
        
        .summary-table { width: 100%; font-size: 11px; }
        .summary-table td { padding: 2px 5px; }
        .summary-table .label-col { width: 180px; }
        .summary-table .sep-col { width: 10px; }
        .summary-table .amount-col { text-align: right; min-width: 80px; }
        .summary-header { font-weight: bold; background: #eee; border: 1px solid #999; padding: 3px 5px; margin-bottom: 5px; font-size: 10px; display: inline-block; }
        
        .total-box { border: 2px solid #000; padding: 4px 8px; font-weight: bold; font-size: 12px; display: flex; justify-content: space-between; margin-top: 8px; }
        
        .payment-info { margin-top: 8px; font-size: 10px; display: flex; justify-content: space-between; }
        .payment-info .left-side { }
        .payment-info .right-side { }
        .sig-line { border-bottom: 1px solid #333; min-width: 150px; display: inline-block; }
        
        .tagline { margin-top: 12px; font-family: 'Brush Script MT', 'Segoe Script', cursive; font-size: 14px; text-align: center; color: #333; font-style: italic; }
        
        .italic-note { font-style: italic; font-size: 10px; margin-bottom: 5px; }
        
        .print-btn { display: block; margin: 10px auto; padding: 10px 30px; background: #002060; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        @media print {
            body { background: white; }
            .statement-page { box-shadow: none; margin: 0; }
            .print-btn, .no-print { display: none !important; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Print Billing Statement(s)</button>
    
    <?php foreach ($customer_ids as $cid):
        $stmt = $conn->prepare("SELECT c.*, a.area_name, p.package_name FROM customers c LEFT JOIN areas a ON c.area_id = a.area_id LEFT JOIN packages p ON c.package_id = p.package_id WHERE c.customer_id = ?");
        $stmt->bind_param("i", $cid);
        $stmt->execute();
        $cust = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$cust) continue;
        
        // Get billing for this month
        $billing = $conn->query("SELECT b.*, COALESCE((SELECT SUM(amount_paid) FROM payments WHERE billing_id = b.billing_id), 0) as total_paid FROM billings b WHERE b.customer_id = $cid AND b.billing_month = $billing_month AND b.billing_year = $billing_year")->fetch_assoc();
        
        // Get last payment
        $last_pay = $conn->query("SELECT p.or_number, p.amount_paid, p.payment_date FROM payments p WHERE p.customer_id = $cid ORDER BY p.payment_date DESC LIMIT 1")->fetch_assoc();
        
        // Get additional fees
        $fees = [];
        if ($billing) {
            $fee_result = $conn->query("SELECT * FROM billing_fees WHERE billing_id = " . $billing['billing_id']);
            if ($fee_result) { while ($f = $fee_result->fetch_assoc()) $fees[] = $f; }
        }
        
        $inst_fee = 0;
        $reconnection_fee = 0;
        $adjustment = 0;
        foreach ($fees as $f) {
            if ($f['fee_type'] == 'installation') $inst_fee += $f['amount'];
            elseif ($f['fee_type'] == 'reconnection') $reconnection_fee += $f['amount'];
            else $adjustment += $f['amount'];
        }
        
        $current_monthly = $billing ? $billing['internet_fee'] : $cust['monthly_fee'];
        $prev_balance = $billing ? $billing['previous_balance'] : 0;
        $service_fee = $billing ? ($billing['service_fee'] + $inst_fee + $reconnection_fee) : 0;
        $total_paid_on_bill = $billing ? $billing['total_paid'] : 0;
        $discount = $billing ? $billing['discount'] : 0;
        $net = $billing ? $billing['net_amount'] : $cust['monthly_fee'];
        $total_due = $net - $total_paid_on_bill;
        
        $period_start = "$billing_year " . strtoupper(substr(get_month_name($billing_month), 0, 3)) . " 1";
        $last_day = date('t', mktime(0, 0, 0, $billing_month, 1, $billing_year));
        $period_end = "$billing_year " . strtoupper(substr(get_month_name($billing_month), 0, 3)) . " $last_day";
        $due_date = $billing ? date('Y M d', strtotime($billing['due_date'])) : "$billing_year " . strtoupper(substr(get_month_name($billing_month), 0, 3)) . " $last_day";
    ?>
    <div class="statement-page">
        <img src="images/headerlogo.png" alt="" class="logo-header">
        <!-- <div class="logo-header"> -->
            <!-- <img src="images/logo.jpg" alt="Logo" onerror="this.style.display='none'">
            <div class="company-text">
                <h1>NOVA LINK DIGITAL SYSTEMS CORP.</h1>
                <p>F. PALMARES STREET, PASSI CITY, ILOILO</p>
                <p>0962-782-9066</p>
            </div> -->
        <!-- </div> -->
        
        <div class="title-bar"><h2>STATEMENT OF ACCOUNT</h2></div>
        
        <div class="info-section">
            <div class="info-left">
                <div class="info-row"><span class="info-label">Subscriber:</span> <?php echo strtoupper($cust['subscriber_name']); ?></div>
                <div class="info-row"><span class="info-label">Address:</span> <?php echo strtoupper($cust['area_name'] ?? $cust['address']); ?></div>
            </div>
            <div class="info-right">
                <div class="info-row"><span class="info-label">Billing Period:</span> <?php echo $period_start; ?>-<?php echo $last_day; ?></div>
                <div class="info-row"><span class="info-label">Due Date:</span> <?php echo $due_date; ?></div>
            </div>
        </div>
        
        <div class="italic-note"><em>Please pay on or before the due date to prevent service interruptions.</em></div>
        
        <div class="content-area">
            <div class="bill-summary">
                <div class="summary-header">Bill Summary :</div>
                <table class="summary-table">
                    <?php if ($service_fee > 0): ?>
                    <tr><td class="label-col">&nbsp;&nbsp;Inst./Service Fee</td><td class="sep-col">:</td><td class="amount-col"><?php echo number_format($service_fee, 2); ?></td></tr>
                    <?php else: ?>
                    <tr><td class="label-col">&nbsp;&nbsp;Inst./Service Fee</td><td class="sep-col">:</td><td class="amount-col">-</td></tr>
                    <?php endif; ?>
                    <tr><td class="label-col">&nbsp;&nbsp;Current/Monthly Subs. :</td><td class="sep-col"></td><td class="amount-col"><?php echo number_format($current_monthly, 2); ?></td></tr>
                    <tr><td class="label-col">&nbsp;&nbsp;Balance Prev Bill</td><td class="sep-col">:</td><td class="amount-col"><?php echo $prev_balance > 0 ? number_format($prev_balance, 2) : '-'; ?></td></tr>
                    <tr><td colspan="3" style="height:5px;"></td></tr>
                    <tr><td class="label-col">Less: Payments- Thank you!</td><td class="sep-col">-</td><td class="amount-col"><?php echo $total_paid_on_bill > 0 ? number_format($total_paid_on_bill, 2) : '-'; ?></td></tr>
                    <tr><td class="label-col">&nbsp;&nbsp;Discount/Adjustment</td><td class="sep-col">:</td><td class="amount-col"><?php echo ($discount + $adjustment) > 0 ? number_format($discount + $adjustment, 2) : '-'; ?></td></tr>
                </table>
                
                <div class="total-box">
                    <span>Total Amount Due :</span>
                    <span><?php echo number_format(max(0, $total_due), 2); ?></span>
                </div>
            </div>
            
            <div class="reminder-box">
                <?php 
                $lines = explode("\n", $reminder_text);
                foreach ($lines as $line):
                    $line = trim($line);
                    if (empty($line)) continue;
                    $is_important = (stripos($line, 'TEMPORARY DISCONNECTION') !== false || stripos($line, 'DISCONNECTION') !== false);
                ?>
                <p<?php echo $is_important ? ' class="important"' : ''; ?>><?php echo htmlspecialchars($line); ?></p>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="payment-info">
            <div class="left-side">
                <div><strong>Last Payment</strong> &nbsp; OR #: <span class="sig-line"><?php echo $last_pay ? htmlspecialchars($last_pay['or_number']) : ''; ?></span> &nbsp; Amount: <span class="sig-line"><?php echo $last_pay ? number_format($last_pay['amount_paid'], 2) : ''; ?></span></div>
            </div>
            <div class="right-side">
                <div>Received by &nbsp;&nbsp;&nbsp;: <span class="sig-line"></span></div>
                <div style="margin-top:3px;">Date Received &nbsp;: <span class="sig-line"></span></div>
            </div>
        </div>
        
        <div class="tagline"><?php echo htmlspecialchars($tagline); ?></div>
    </div>
    <?php endforeach; ?>
    
    <button class="print-btn no-print" onclick="window.print()">🖨️ Print Billing Statement(s)</button>
</body>
</html>
<?php $conn->close(); ?>
