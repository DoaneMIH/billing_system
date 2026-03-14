<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id == 0) {
    header("Location: customers.php");
    exit();
}

$conn = getDBConnection();

// Get customer details
$stmt = $conn->prepare("SELECT c.*, a.area_name, p.package_name, p.bandwidth_mbps 
    FROM customers c 
    LEFT JOIN areas a ON c.area_id = a.area_id 
    LEFT JOIN packages p ON c.package_id = p.package_id 
    WHERE c.customer_id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    header("Location: customers.php");
    exit();
}

// Get payment history
$payments_history = $conn->query("SELECT p.payment_date, p.amount_paid, p.or_number,
    b.billing_month, b.billing_year, b.net_amount,
    (SELECT COALESCE(SUM(net_amount), 0) - COALESCE(SUM(amount_paid), 0)
     FROM billings b2
     LEFT JOIN payments p2 ON b2.billing_id = p2.billing_id
     WHERE b2.customer_id = $customer_id
     AND (b2.billing_year < b.billing_year OR (b2.billing_year = b.billing_year AND b2.billing_month <= b.billing_month))
    ) as running_balance
    FROM payments p
    JOIN billings b ON p.billing_id = b.billing_id
    WHERE p.customer_id = $customer_id
    ORDER BY p.payment_date ASC
    LIMIT 20");

// Current balance
$balance_query = $conn->query("SELECT 
    COALESCE(SUM(b.net_amount), 0) - COALESCE(SUM(p.amount_paid), 0) as total_balance
    FROM billings b
    LEFT JOIN payments p ON b.billing_id = p.billing_id
    WHERE b.customer_id = $customer_id");
$balance_row = $balance_query->fetch_assoc();
$current_balance = $balance_row['total_balance'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Ledger - AR NOVALINK</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header no-print">
                <div>
                    <h1>Customer Ledger</h1>
                    <p>Payment history and account details</p>
                </div>
                <button onclick="window.print()" class="btn btn-primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 6 2 18 2 18 9"/>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                        <rect x="6" y="14" width="12" height="8"/>
                    </svg>
                    Print Ledger
                </button>
            </div>
            
            <div class="widget no-print">
                <div class="widget-header">
                    <h2>Customer Information</h2>
                    <button onclick="window.print()" class="btn btn-primary">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 6 2 18 2 18 9"/>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                            <rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Print Technical Ledger
                    </button>
                </div>
                <div class="widget-content">
                    <div class="form-row">
                        <div><strong>Account:</strong> <?php echo htmlspecialchars($customer['account_number']); ?></div>
                        <div><strong>Name:</strong> <?php echo htmlspecialchars($customer['subscriber_name']); ?></div>
                    </div>
                    <div class="form-row mt-1">
                        <div><strong>Address:</strong> <?php echo htmlspecialchars($customer['address']); ?></div>
                        <div><strong>Package:</strong> <?php echo htmlspecialchars($customer['package_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="form-row mt-1">
                        <div><strong>Monthly Fee:</strong> <?php echo format_currency($customer['monthly_fee']); ?></div>
                        <div><strong>Current Balance:</strong> <strong class="<?php echo $current_balance > 0 ? 'ledger-balance-positive' : 'ledger-balance-zero'; ?>"><?php echo format_currency($current_balance); ?></strong></div>
                    </div>
                </div>
            </div>
            
            <!-- Payment History Table - Visible on Screen -->
            <div class="table-container no-print">
                <div class="table-header">
                    <h2>Payment History</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Date Paid</th>
                            <th>Amount Paid</th>
                            <th>Balance</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $payments_history->data_seek(0); // Reset pointer
                        if ($payments_history->num_rows > 0): 
                        ?>
                            <?php while ($payment = $payments_history->fetch_assoc()): 
                                $days_overdue = 0;
                                if ($payment['running_balance'] > 0) {
                                    $due_date = new DateTime($payment['billing_year'] . '-' . $payment['billing_month'] . '-' . date('t', strtotime($payment['billing_year'] . '-' . $payment['billing_month'])));
                                    $paid_date = new DateTime($payment['payment_date']);
                                    $days_overdue = $paid_date->diff($due_date)->days;
                                }
                            ?>
                            <tr>
                                <td><?php echo get_month_name($payment['billing_month']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td><?php echo format_currency($payment['amount_paid']); ?></td>
                                <td><?php echo format_currency($payment['running_balance']); ?></td>
                                <td>
                                    <?php if ($days_overdue > 0 && $days_overdue <= 30): ?>
                                        <span class="badge badge-warning"><?php echo $days_overdue; ?> days late</span>
                                    <?php elseif ($days_overdue > 30): ?>
                                        <span class="badge badge-danger"><?php echo $days_overdue; ?> days late</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">On time</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center">No payment history yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-2 no-print">
                <a href="customers.php" class="btn btn-secondary">Back to Customers</a>
            </div>
        </main>
    </div>
    
    <!-- PRINT LEDGER -->
    <div class="print-ledger">
        <div style="text-align: center; margin-bottom: 15px;">
            <div style="font-size: 13px; font-weight: bold;">NOVA LINK DIGITAL SYSTEMS CORP.</div>
            <div style="font-size: 10px;">F. PALMARES ST., PASSI CTY</div>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
            <tr>
                <td style="width: 50%; padding-right: 10px; vertical-align: top;">
                    <div style="margin-bottom: 3px;"><strong>Name:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px; padding-left: 5px;"><?php echo strtoupper($customer['subscriber_name']); ?></span></div>
                    <div style="margin-bottom: 3px;"><strong>Address:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px; padding-left: 5px;"><?php echo strtoupper($customer['address']); ?></span></div>
                    <div style="margin-bottom: 3px;"><strong>Contact#:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px; padding-left: 5px;"><?php echo $customer['tel_no'] ?? ''; ?></span></div>
                    <div style="margin-bottom: 3px;"><strong>Date Inst.:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px; padding-left: 5px;"><?php echo $customer['installation_date'] ? date('m/d/y', strtotime($customer['installation_date'])) : ''; ?></span></div>
                    <div style="margin-bottom: 3px;"><strong>BUNDLE:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px; padding-left: 5px;"><?php echo strtoupper($customer['package_name'] ?? ''); ?></span></div>
                    <div style="margin-bottom: 3px;"><strong>Code#:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>Router Serial#:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>MBPS:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px; padding-left: 5px;"><?php echo $customer['bandwidth_mbps'] ?? '25'; ?></span></div>
                    <div style="margin-bottom: 3px;"><strong>MONTHLY:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>PORT NUMBER:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>LCP NUMBER:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>Nap NUMBER:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>Nap output:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>Fiber Output:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>SERIAL NUMBER:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>MCADDRESS:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px;"></span></div>
                </td>
                <td style="width: 50%; padding-left: 10px; vertical-align: top;">
                    <div style="margin-bottom: 3px;"><strong>ACCT NAME:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px; padding-left: 5px;"><?php echo strtoupper($customer['subscriber_name']); ?></span></div>
                    <div style="margin-bottom: 3px;"><strong>Password:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>FIBER OPTIC 1 CORE:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px; padding-left: 5px;">311M</span></div>
                    <div style="margin-bottom: 3px;"><strong>FIBER OPTIC 2 CORE:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>SC CONNECTOR:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px; padding-left: 5px;">2PCS.</span></div>
                    <div style="margin-bottom: 3px;"><strong>RGB WIRE:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>RG6-CONNECTOR:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>2WAY SPLITTER:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>3WAY SPLITTER:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>F CLAMP:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>PATCHCORD:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>COUPLER:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>COUPLER 2WAY:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>PASSIVE NODE:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>ACTIVE NODE:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px;"></span></div>
                    <div style="margin-bottom: 3px;"><strong>TERMINAL JUNCTION BOX:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px;"></span></div>
                </td>
            </tr>
        </table>
        
        <table style="width: 50%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #000;">
            <tr style="background: #f0f0f0;">
                <td style="border: 1px solid #000; padding: 4px; font-weight: bold;">MONTHLY</td>
                <td style="border: 1px solid #000; padding: 4px; font-weight: bold; text-align: right;">PER DAY</td>
            </tr>
            <tr><td style="border: 1px solid #000; padding: 4px; text-align: right;">1,000</td><td style="border: 1px solid #000; padding: 4px; text-align: right;">33.33</td></tr>
            <tr><td style="border: 1px solid #000; padding: 4px; text-align: right;">1,299.00</td><td style="border: 1px solid #000; padding: 4px; text-align: right;">43.3</td></tr>
            <tr><td style="border: 1px solid #000; padding: 4px; text-align: right;">1,499.00</td><td style="border: 1px solid #000; padding: 4px; text-align: right;">49.97</td></tr>
            <tr><td style="border: 1px solid #000; padding: 4px; text-align: right;">1,999.00</td><td style="border: 1px solid #000; padding: 4px; text-align: right;">66.63</td></tr>
            <tr><td style="border: 1px solid #000; padding: 4px; text-align: right;">3,999.00</td><td style="border: 1px solid #000; padding: 4px; text-align: right;">133.3</td></tr>
        </table>
        
        <div style="text-align: right; margin: 10px 0;">
            <strong>INSTALLED BY:</strong> <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 300px; margin-left: 10px; text-align: center;">RENDON/REMAN</span>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #000; margin-top: 15px;">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th style="border: 1px solid #000; padding: 6px; text-align: center;">MONTH</th>
                    <th style="border: 1px solid #000; padding: 6px; text-align: center;">DATE PAID</th>
                    <th style="border: 1px solid #000; padding: 6px; text-align: center;">AMOUNT PAID</th>
                    <th style="border: 1px solid #000; padding: 6px; text-align: center;">BALANCE</th>
                    <th style="border: 1px solid #000; padding: 6px; text-align: center;">REMARK</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $row_count = 0;
                while ($payment = $payments_history->fetch_assoc()): 
                    $row_count++;
                    $days_overdue = 0;
                    if ($payment['running_balance'] > 0) {
                        $due_date = new DateTime($payment['billing_year'] . '-' . $payment['billing_month'] . '-' . date('t', strtotime($payment['billing_year'] . '-' . $payment['billing_month'])));
                        $paid_date = new DateTime($payment['payment_date']);
                        $days_overdue = $paid_date->diff($due_date)->days;
                    }
                ?>
                <tr>
                    <td style="border: 1px solid #000; padding: 5px; text-align: center;"><?php echo strtoupper(get_month_name($payment['billing_month'])); ?></td>
                    <td style="border: 1px solid #000; padding: 5px; text-align: center;"><?php echo date('m/d/y', strtotime($payment['payment_date'])); ?></td>
                    <td style="border: 1px solid #000; padding: 5px; text-align: center;"></td>
                    <td style="border: 1px solid #000; padding: 5px; text-align: center;"><?php echo number_format($payment['running_balance'], 2); ?></td>
                    <td style="border: 1px solid #000; padding: 5px; text-align: center;"><?php echo $days_overdue > 0 && $days_overdue <= 30 ? $days_overdue . ' DAYS' : ''; ?></td>
                </tr>
                <?php endwhile; ?>
                <?php for ($i = $row_count; $i < 20; $i++): ?>
                <tr>
                    <td style="border: 1px solid #000; padding: 5px;">&nbsp;</td>
                    <td style="border: 1px solid #000; padding: 5px;"></td>
                    <td style="border: 1px solid #000; padding: 5px;"></td>
                    <td style="border: 1px solid #000; padding: 5px;"></td>
                    <td style="border: 1px solid #000; padding: 5px;"></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>