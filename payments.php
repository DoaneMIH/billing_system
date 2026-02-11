<?php
require_once 'config.php';

// Only cashiers and admins can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['cashier', 'admin'])) {
    header("Location: index.php");
    exit();
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
        // Insert payment
        $stmt = $conn->prepare("INSERT INTO payments (billing_id, customer_id, or_number, payment_date, amount_paid, payment_method, cashier_id, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissdsis", $billing_id, $customer_id, $or_number, $payment_date, $amount_paid, $payment_method, $_SESSION['user_id'], $remarks);
        $stmt->execute();
        $payment_id = $stmt->insert_id;
        $stmt->close();
        
        // Update billing status
        $result = $conn->query("SELECT net_amount, (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE billing_id = $billing_id) as total_paid FROM billings WHERE billing_id = $billing_id");
        $billing = $result->fetch_assoc();
        
        $new_status = 'unpaid';
        if ($billing['total_paid'] >= $billing['net_amount']) {
            $new_status = 'paid';
        } elseif ($billing['total_paid'] > 0) {
            $new_status = 'partial';
        }
        
        $conn->query("UPDATE billings SET status = '$new_status' WHERE billing_id = $billing_id");
        
        log_activity($_SESSION['user_id'], 'RECORD_PAYMENT', 'payments', $payment_id, "Recorded payment OR# $or_number for customer ID $customer_id");
        
        $conn->commit();
        $success = "Payment recorded successfully! OR# $or_number";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error recording payment: " . $e->getMessage();
    }
}

// Get recent payments
$recent_payments = $conn->query("
    SELECT p.*, c.subscriber_name, c.account_number, b.billing_month, b.billing_year 
    FROM payments p 
    JOIN customers c ON p.customer_id = c.customer_id 
    JOIN billings b ON p.billing_id = b.billing_id 
    ORDER BY p.payment_date DESC, p.created_at DESC 
    LIMIT 50
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - AR NOVALINK Billing System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Payment Processing</h1>
                <p>Record customer payments</p>
            </div>
            
            <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                </svg>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                </svg>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <div class="widget mb-3">
                <div class="widget-header">
                    <h2>Record New Payment</h2>
                </div>
                <div class="widget-content">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="record_payment">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="search_customer">Search Customer *</label>
                                <input type="text" id="search_customer" placeholder="Enter account number or name" autocomplete="off">
                                <div id="customer_results" style="position: relative;"></div>
                                <input type="hidden" id="customer_id" name="customer_id" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="billing_period">Billing Period *</label>
                                <select id="billing_period" name="billing_id" required>
                                    <option value="">Select billing period</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="or_number">OR Number *</label>
                                <input type="text" id="or_number" name="or_number" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_date">Payment Date *</label>
                                <input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="amount_paid">Amount Paid *</label>
                                <input type="number" step="0.01" id="amount_paid" name="amount_paid" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_method">Payment Method *</label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value="cash">Cash</option>
                                    <option value="check">Check</option>
                                    <option value="online">Online Transfer</option>
                                    <option value="others">Others</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" rows="3"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                            Record Payment
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="table-container">
                <div class="table-header">
                    <h2>Recent Payments</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>OR Number</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Account #</th>
                            <th>Period</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_payments->num_rows > 0): ?>
                            <?php while ($row = $recent_payments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['or_number']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['payment_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['subscriber_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                                <td><?php echo get_month_name($row['billing_month']) . ' ' . $row['billing_year']; ?></td>
                                <td><?php echo format_currency($row['amount_paid']); ?></td>
                                <td><?php echo ucfirst($row['payment_method']); ?></td>
                                <td>
                                    <a href="print_receipt.php?id=<?php echo $row['payment_id']; ?>" target="_blank" class="btn btn-sm btn-primary">Print Receipt</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No payments recorded yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script src="js/script.js"></script>
    <script src="js/payments.js"></script>
</body>
</html>
<?php $conn->close(); ?>