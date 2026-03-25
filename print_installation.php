<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($customer_id == 0) { die("Invalid subscriber ID"); }
$conn = getDBConnection();

$stmt = $conn->prepare("SELECT c.*, a.area_name, p.package_name, p.bandwidth_mbps, p.monthly_fee as pkg_fee FROM customers c LEFT JOIN areas a ON c.area_id = a.area_id LEFT JOIN packages p ON c.package_id = p.package_id WHERE c.customer_id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$customer) { die("Subscriber not found"); }

$materials = [];
if ($customer['package_id']) {
    $mat_result = $conn->query("SELECT * FROM package_materials WHERE package_id = " . intval($customer['package_id']) . " ORDER BY material_name");
    while ($row = $mat_result->fetch_assoc()) { $materials[] = $row; }
}

$payments = $conn->query("SELECT p.payment_date, p.amount_paid, p.or_number, b.billing_month, b.billing_year, b.net_amount,
    b.net_amount - COALESCE((SELECT SUM(p2.amount_paid) FROM payments p2 WHERE p2.billing_id = b.billing_id), 0) as balance
    FROM payments p JOIN billings b ON p.billing_id = b.billing_id WHERE p.customer_id = $customer_id ORDER BY p.payment_date DESC LIMIT 12");

$fee_tiers = [
    ['monthly'=>1000,'daily'=>33.33],['monthly'=>1299,'daily'=>43.30],
    ['monthly'=>1499,'daily'=>49.97],['monthly'=>1899,'daily'=>63.30],
    ['monthly'=>2499,'daily'=>83.30],['monthly'=>3999,'daily'=>133.30],
];
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Installation Form - <?php echo htmlspecialchars($customer['subscriber_name']); ?></title>
        <link rel="shortcut icon" type="x-icon" href="images/logo.jpg">

    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:Arial,sans-serif;font-size:11px;background:#f5f5f5; }
        .page { width:210mm;min-height:297mm;margin:10px auto;background:white;padding:10mm 12mm;box-shadow:0 2px 10px rgba(0,0,0,0.1); }
        .header-logo { width:100%;max-height:60px;display:block;margin-bottom:8px; }
        .info-grid { display:flex;gap:10px;margin-bottom:10px; }
        .info-left,.info-right { flex:1; }
        .info-row { display:flex;margin-bottom:3px;font-size:10px; }
        .info-label { font-weight:bold;min-width:100px; }
        .info-value { border-bottom:1px solid #333;flex:1;padding-left:5px;min-height:14px; }
        .mat-item { font-size:10px;display:flex;margin-bottom:2px; }
        .mat-name { min-width:160px;font-weight:bold; }
        .mat-val { border-bottom:1px solid #333;flex:1;min-width:80px;text-align:center; }
        .fee-table { border-collapse:collapse;margin:10px 0;font-size:10px; }
        .fee-table th,.fee-table td { border:1px solid #999;padding:3px 8px; }
        .fee-table th { background:#f0f0f0; }
        .payment-table { width:100%;border-collapse:collapse;margin-top:10px;font-size:10px; }
        .payment-table th,.payment-table td { border:1px solid #999;padding:3px 6px; }
        .payment-table th { background:#c89632;color:white; }
        .installed-by { display:flex;align-items:center;gap:10px;margin:10px 0;font-size:11px; }
        .installed-by .line { border-bottom:1px solid #333;min-width:200px;text-align:center;padding:0 10px; }
        .print-btn { display:block;margin:10px auto;padding:10px 30px;background:#002060;color:white;border:none;border-radius:5px;cursor:pointer;font-size:14px; }
        @media print { body{background:white;} .page{box-shadow:none;margin:0;padding:8mm 10mm;} .print-btn,.no-print{display:none!important;} @page{size:A4;margin:0;} }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Print Installation Form</button>
    <div class="page">
        <img src="images/headerlogo.png" alt="NovaLink" class="header-logo">
        
        <div style="text-align:center;margin-bottom:8px;"><strong style="font-size:13px;"><?php echo strtoupper($customer['subscriber_name']); ?></strong></div>
        
        <div class="info-grid">
            <div class="info-left">
                <div class="info-row"><span class="info-label">Name:</span><span class="info-value"><?php echo strtoupper($customer['subscriber_name']); ?></span></div>
                <div class="info-row"><span class="info-label">Address:</span><span class="info-value"><?php echo strtoupper($customer['address']); ?></span></div>
                <div class="info-row"><span class="info-label">Contact#:</span><span class="info-value"><?php echo $customer['tel_no']??''; ?></span></div>
                <div class="info-row"><span class="info-label">Date Inst.:</span><span class="info-value"><?php echo $customer['installation_date']?date('n/j/y',strtotime($customer['installation_date'])):''; ?></span></div>
                <div class="info-row"><span class="info-label">BUNDLE:</span><span class="info-value"><?php echo strtoupper($customer['package_name']??''); ?></span></div>
                <div class="info-row"><span class="info-label">STATUS:</span><span class="info-value"><?php echo strtoupper(str_replace('_',' ',$customer['status']??'NEW INSTALLATION')); ?></span></div>
                <div class="info-row"><span class="info-label">Code #:</span><span class="info-value"><?php echo $customer['code_number']??''; ?></span></div>
                <div class="info-row"><span class="info-label">Router Serial#:</span><span class="info-value"><?php echo $customer['router_serial']??''; ?></span></div>
                <div class="info-row"><span class="info-label">MBPS:</span><span class="info-value"><?php echo $customer['bandwidth_mbps']??''; ?></span></div>
                <div class="info-row"><span class="info-label">MONTHLY:</span><span class="info-value"><?php echo $customer['monthly_fee']?number_format($customer['monthly_fee'],2):''; ?></span></div>
                <div class="info-row"><span class="info-label">PORT NUMBER:</span><span class="info-value"><?php echo $customer['port_number']??''; ?></span></div>
                <div class="info-row"><span class="info-label">LCP NUMBER:</span><span class="info-value"><?php echo $customer['lcp_number']??''; ?></span></div>
                <div class="info-row"><span class="info-label">Nap NUMBER:</span><span class="info-value"><?php echo $customer['nap_number']??''; ?></span></div>
                <div class="info-row"><span class="info-label">Nap output:</span><span class="info-value"><?php echo $customer['nap_output']??''; ?></span></div>
                <div class="info-row"><span class="info-label">Fiber Output:</span><span class="info-value"><?php echo $customer['fiber_output']??''; ?></span></div>
                <div class="info-row"><span class="info-label">SERIAL NUMBER:</span><span class="info-value"><?php echo $customer['serial_number']??''; ?></span></div>
                <div class="info-row"><span class="info-label">MCADDRESS:</span><span class="info-value"><?php echo $customer['mac_address']??''; ?></span></div>
            </div>
            <div class="info-right">
                <div class="info-row"><span class="info-label">ACCT NAME:</span><span class="info-value"><?php echo $customer['acct_name']??strtolower(str_replace([',',' '],['_','_'],$customer['subscriber_name'])); ?></span></div>
                <div class="info-row"><span class="info-label">Password:</span><span class="info-value"><?php echo $customer['password_field']??''; ?></span></div>
                <div style="margin-top:8px;font-size:10px;font-weight:bold;margin-bottom:3px;">INSTALLATION MATERIALS:</div>
                <?php 
                $all_names = ['FIBER OPTIC 1 CORE','FIBER OPTIC 2 CORE','SC CONNECTOR','RG6-WIRE','RG6-CONNECTOR','2WAY SPLITTER','3WAY SPLITTER','F CLAMP','PATCHCORD','COUPLER','COUPLER 2WAY','PASSIVE NODE','ACTIVE NODE','TERMINAL JUNCTION BOX'];
                $mat_lookup = [];
                foreach ($materials as $m) { $mat_lookup[strtoupper($m['material_name'])] = $m['quantity'] . ($m['unit']!='pcs'?' '.$m['unit']:''); }
                foreach ($all_names as $mname):
                    $val = '';
                    foreach ($mat_lookup as $key => $v) {
                        if (strpos(str_replace(['-',' '],'',$key), str_replace(['-',' '],'',$mname)) !== false || strpos(str_replace(['-',' '],'',$mname), str_replace(['-',' '],'',$key)) !== false) { $val = $v; break; }
                    }
                ?>
                <div class="mat-item"><span class="mat-name"><?php echo $mname; ?></span><span class="mat-val"><?php echo $val; ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div style="display:flex;gap:20px;margin-top:10px;">
            <table class="fee-table">
                <thead><tr><th>MONTHLY</th><th>PER DAY</th></tr></thead>
                <tbody><?php foreach ($fee_tiers as $tier): ?>
                <tr<?php echo abs($tier['monthly']-$customer['monthly_fee'])<1?' style="background:#ffffcc;font-weight:bold;"':''; ?>>
                    <td style="text-align:right;"><?php echo number_format($tier['monthly'],2); ?></td>
                    <td style="text-align:right;"><?php echo number_format($tier['daily'],2); ?></td>
                </tr>
                <?php endforeach; ?></tbody>
            </table>
            <div style="flex:1;">
                <div class="installed-by"><strong>INSTALLED BY:</strong><span class="line"><?php echo $customer['installed_by']??''; ?></span></div>
            </div>
        </div>
        
        <table class="payment-table">
            <thead><tr><th>MONTH</th><th>DATE PAID</th><th>AMOUNT PAID</th><th>BALANCE</th><th>REMARK</th></tr></thead>
            <tbody>
                <?php if ($payments && $payments->num_rows > 0): while ($pay = $payments->fetch_assoc()): ?>
                <tr>
                    <td><?php echo strtoupper(get_month_name($pay['billing_month'])); ?></td>
                    <td><?php echo date('n/j/y',strtotime($pay['payment_date'])); ?></td>
                    <td style="text-align:right;"><?php echo number_format($pay['amount_paid'],2); ?></td>
                    <td style="text-align:right;"><?php echo number_format(max(0,$pay['balance']),2); ?></td>
                    <td></td>
                </tr>
                <?php endwhile; endif; ?>
                <?php for ($i=($payments?$payments->num_rows:0);$i<12;$i++): ?>
                <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Print Installation Form</button>
</body>
</html>
