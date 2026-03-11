<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['cashier', 'admin'])) {
    header("Location: index.php"); exit();
}
$conn = getDBConnection();

// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'record_payment') {
    $billing_id = intval($_POST['billing_id']);
    $customer_id = intval($_POST['customer_id']);
    $or_number = sanitize_input($_POST['or_number']);
    $payment_date = sanitize_input($_POST['payment_date']);
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_method = sanitize_input($_POST['payment_method']);
    $remarks = sanitize_input($_POST['remarks']);
    
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO payments (billing_id, customer_id, or_number, payment_date, amount_paid, payment_method, cashier_id, remarks) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iissdsis", $billing_id, $customer_id, $or_number, $payment_date, $amount_paid, $payment_method, $_SESSION['user_id'], $remarks);
        $stmt->execute();
        $payment_id = $stmt->insert_id;
        $stmt->close();
        
        $result = $conn->query("SELECT net_amount, (SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE billing_id = $billing_id) as total_paid FROM billings WHERE billing_id = $billing_id");
        $billing = $result->fetch_assoc();
        $new_status = $billing['total_paid'] >= $billing['net_amount'] ? 'paid' : ($billing['total_paid'] > 0 ? 'partial' : 'unpaid');
        $conn->query("UPDATE billings SET status = '$new_status' WHERE billing_id = $billing_id");
        
        log_activity($_SESSION['user_id'], 'RECORD_PAYMENT', 'payments', $payment_id, "Recorded payment OR# $or_number - ₱" . number_format($amount_paid, 2));
        $conn->commit();
        $success = "Payment recorded! OR# $or_number";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

// Daily report filter
$report_date = isset($_GET['report_date']) ? sanitize_input($_GET['report_date']) : date('Y-m-d');

// Get recent payments
$recent_payments = $conn->query("SELECT p.*, c.subscriber_name, c.account_number, b.billing_month, b.billing_year, u.full_name as cashier_name
    FROM payments p JOIN customers c ON p.customer_id = c.customer_id JOIN billings b ON p.billing_id = b.billing_id LEFT JOIN users u ON p.cashier_id = u.user_id
    ORDER BY p.payment_date DESC, p.created_at DESC LIMIT 50");

// Daily report data
$daily_payments = $conn->query("SELECT p.*, c.subscriber_name, c.account_number, b.billing_month, b.billing_year, u.full_name as cashier_name
    FROM payments p JOIN customers c ON p.customer_id = c.customer_id JOIN billings b ON p.billing_id = b.billing_id LEFT JOIN users u ON p.cashier_id = u.user_id
    WHERE p.payment_date = '$report_date' ORDER BY p.created_at ASC");

$daily_total = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as total FROM payments WHERE payment_date = '$report_date'")->fetch_assoc()['total'];
$daily_count = $conn->query("SELECT COUNT(*) as cnt FROM payments WHERE payment_date = '$report_date'")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - AR NOVALINK</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-section { display: block !important; }
            body * { visibility: hidden; }
            .daily-report, .daily-report * { visibility: visible; }
            .daily-report { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container no-print">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header">
                <h1>Payment Processing</h1>
                <p>Record payments and generate daily reports</p>
            </div>
            
            <?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <?php if (isset($error)): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            
            <div class="widget mb-3">
                <div class="widget-header"><h2>Record New Payment</h2></div>
                <div class="widget-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="record_payment">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Search Subscriber *</label>
                                <input type="text" id="search_customer" placeholder="Type to search..." autocomplete="off">
                                <div id="customer_results" style="position:relative;"></div>
                                <input type="hidden" id="customer_id" name="customer_id" required>
                                <div id="selected_customer" style="margin-top:8px;padding:8px;background:#e7f3ff;border-radius:5px;display:none;">
                                    <strong>Selected:</strong> <span id="selected_customer_name"></span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Billing Period *</label>
                                <select id="billing_period" name="billing_id" required disabled>
                                    <option value="">Select subscriber first</option>
                                </select>
                                <div id="billing_info" style="margin-top:5px;font-size:12px;color:#666;"></div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>OR Number *</label><input type="text" name="or_number" required></div>
                            <div class="form-group"><label>Payment Date *</label><input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Amount Paid *</label><input type="number" step="0.01" name="amount_paid" required></div>
                            <div class="form-group"><label>Payment Method *</label>
                                <select name="payment_method" required>
                                    <option value="cash">Cash</option><option value="check">Check</option><option value="online">Online</option><option value="others">Others</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group"><label>Remarks</label><textarea name="remarks" rows="2"></textarea></div>
                        <button type="submit" class="btn btn-primary">Record Payment</button>
                    </form>
                </div>
            </div>
            
            <!-- Daily Payment Report -->
            <div class="widget mb-3">
                <div class="widget-header">
                    <h2>Daily Payment Report</h2>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <form method="GET" style="display:flex;gap:8px;">
                            <input type="date" name="report_date" value="<?php echo $report_date; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">View</button>
                        </form>
                        <button onclick="window.print()" class="btn btn-primary btn-sm">🖨️ Print Report</button>
                    </div>
                </div>
                <div class="widget-content">
                    <div style="display:flex;gap:20px;margin-bottom:10px;">
                        <div><strong>Date:</strong> <?php echo date('F d, Y', strtotime($report_date)); ?></div>
                        <div><strong>Total Payments:</strong> <?php echo $daily_count; ?></div>
                        <div><strong>Total Collections:</strong> <?php echo format_currency($daily_total); ?></div>
                    </div>
                    <table>
                        <thead><tr><th>OR #</th><th>Customer</th><th>Account #</th><th>Period</th><th>Amount</th><th>Method</th><th>Cashier</th></tr></thead>
                        <tbody>
                            <?php if ($daily_payments->num_rows > 0): while ($dp = $daily_payments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($dp['or_number']); ?></td>
                                <td><?php echo htmlspecialchars($dp['subscriber_name']); ?></td>
                                <td><?php echo htmlspecialchars($dp['account_number']); ?></td>
                                <td><?php echo get_month_name($dp['billing_month']).' '.$dp['billing_year']; ?></td>
                                <td><?php echo format_currency($dp['amount_paid']); ?></td>
                                <td><?php echo ucfirst($dp['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($dp['cashier_name'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="7" class="text-center">No payments for this date</td></tr>
                            <?php endif; ?>
                            <tr style="background:#f0f0f0;font-weight:bold;">
                                <td colspan="4" style="text-align:right;">TOTAL:</td>
                                <td><?php echo format_currency($daily_total); ?></td>
                                <td colspan="2"><?php echo $daily_count; ?> payment(s)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Payments -->
            <div class="table-container">
                <div class="table-header"><h2>Recent Payments</h2></div>
                <table>
                    <thead><tr><th>OR #</th><th>Date</th><th>Customer</th><th>Account #</th><th>Period</th><th>Amount</th><th>Method</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if ($recent_payments->num_rows > 0): while ($row = $recent_payments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['or_number']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['payment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['subscriber_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                            <td><?php echo get_month_name($row['billing_month']).' '.$row['billing_year']; ?></td>
                            <td><?php echo format_currency($row['amount_paid']); ?></td>
                            <td><?php echo ucfirst($row['payment_method']); ?></td>
                            <td>
                                <a href="print_invoice.php?id=<?php echo $row['payment_id']; ?>" target="_blank" class="btn btn-sm btn-primary">Invoice</a>
                                <a href="print_receipt.php?id=<?php echo $row['payment_id']; ?>" target="_blank" class="btn btn-sm btn-secondary">Receipt</a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="8" class="text-center">No payments recorded</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- Print-only daily report -->
    <div class="daily-report" style="display:none;font-family:Arial;padding:20px;">
        <div style="text-align:center;margin-bottom:15px;">
            <h2 style="margin:0;">NOVA LINK DIGITAL SYSTEMS CORP.</h2>
            <p style="margin:2px 0;">F. Palmares St., Passi City, Iloilo</p>
            <h3 style="margin:10px 0;">DAILY PAYMENT REPORT</h3>
            <p><strong>Date: <?php echo date('F d, Y', strtotime($report_date)); ?></strong></p>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:11px;">
            <thead><tr style="background:#002060;color:white;">
                <th style="border:1px solid #333;padding:5px;">OR #</th><th style="border:1px solid #333;padding:5px;">Customer</th>
                <th style="border:1px solid #333;padding:5px;">Account #</th><th style="border:1px solid #333;padding:5px;">Period</th>
                <th style="border:1px solid #333;padding:5px;">Amount</th><th style="border:1px solid #333;padding:5px;">Method</th>
                <th style="border:1px solid #333;padding:5px;">Cashier</th>
            </tr></thead>
            <tbody>
                <?php $daily_payments->data_seek(0); while ($dp = $daily_payments->fetch_assoc()): ?>
                <tr>
                    <td style="border:1px solid #ccc;padding:4px;"><?php echo $dp['or_number']; ?></td>
                    <td style="border:1px solid #ccc;padding:4px;"><?php echo $dp['subscriber_name']; ?></td>
                    <td style="border:1px solid #ccc;padding:4px;"><?php echo $dp['account_number']; ?></td>
                    <td style="border:1px solid #ccc;padding:4px;"><?php echo get_month_name($dp['billing_month']).' '.$dp['billing_year']; ?></td>
                    <td style="border:1px solid #ccc;padding:4px;text-align:right;">₱<?php echo number_format($dp['amount_paid'],2); ?></td>
                    <td style="border:1px solid #ccc;padding:4px;"><?php echo ucfirst($dp['payment_method']); ?></td>
                    <td style="border:1px solid #ccc;padding:4px;"><?php echo $dp['cashier_name']??''; ?></td>
                </tr>
                <?php endwhile; ?>
                <tr style="font-weight:bold;background:#f0f0f0;">
                    <td colspan="4" style="border:1px solid #333;padding:5px;text-align:right;">TOTAL COLLECTIONS:</td>
                    <td style="border:1px solid #333;padding:5px;text-align:right;">₱<?php echo number_format($daily_total,2); ?></td>
                    <td colspan="2" style="border:1px solid #333;padding:5px;"><?php echo $daily_count; ?> payment(s)</td>
                </tr>
            </tbody>
        </table>
        <div style="margin-top:40px;display:flex;justify-content:space-between;">
            <div>Prepared by: ___________________</div>
            <div>Verified by: ___________________</div>
        </div>
    </div>
    
    <script src="js/script.js"></script>
    <script src="js/payment.js"></script>
</body>
</html>
<?php $conn->close(); ?>
