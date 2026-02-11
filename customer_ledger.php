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
$stmt = $conn->prepare("SELECT c.*, a.area_name, p.package_name FROM customers c LEFT JOIN areas a ON c.area_id = a.area_id LEFT JOIN packages p ON c.package_id = p.package_id WHERE c.customer_id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    header("Location: customers.php");
    exit();
}

// Get billing history
$billings = $conn->query("SELECT b.*, 
    (SELECT SUM(amount_paid) FROM payments WHERE billing_id = b.billing_id) as total_paid
    FROM billings b 
    WHERE b.customer_id = $customer_id 
    ORDER BY b.billing_year DESC, b.billing_month DESC");

// Calculate totals
$total_billed = 0;
$total_paid = 0;
$total_balance = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Ledger - AR NOVALINK Billing System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Customer Ledger</h1>
                <p>Payment history and billing summary</p>
            </div>
            
            <div class="widget" style="margin-bottom: 20px;">
                <div class="widget-header">
                    <h2>Customer Information</h2>
                </div>
                <div class="widget-content">
                    <div class="form-row">
                        <div>
                            <strong>Account Number:</strong> <?php echo htmlspecialchars($customer['account_number']); ?>
                        </div>
                        <div>
                            <strong>Name:</strong> <?php echo htmlspecialchars($customer['subscriber_name']); ?>
                        </div>
                    </div>
                    <div class="form-row mt-1">
                        <div>
                            <strong>Address:</strong> <?php echo htmlspecialchars($customer['address']); ?>
                        </div>
                        <div>
                            <strong>Area:</strong> <?php echo htmlspecialchars($customer['area_name'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    <div class="form-row mt-1">
                        <div>
                            <strong>Package:</strong> <?php echo htmlspecialchars($customer['package_name'] ?? 'N/A'); ?>
                        </div>
                        <div>
                            <strong>Monthly Fee:</strong> <?php echo format_currency($customer['monthly_fee']); ?>
                        </div>
                    </div>
                    <div class="form-row mt-1">
                        <div>
                            <strong>Installation Date:</strong> <?php echo date('F d, Y', strtotime($customer['installation_date'])); ?>
                        </div>
                        <div>
                            <strong>Status:</strong> 
                            <span class="badge badge-<?php echo $customer['status'] == 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($customer['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <div class="table-header">
                    <h2>Billing History</h2>
                    <button onclick="window.print()" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 6 2 18 2 18 9"/>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                            <rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Print Ledger
                    </button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Internet Fee</th>
                            <th>Cable Fee</th>
                            <th>Service Fee</th>
                            <th>Total Amount</th>
                            <th>Amount Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($billings->num_rows > 0): ?>
                            <?php while ($row = $billings->fetch_assoc()): 
                                $paid = $row['total_paid'] ?? 0;
                                $balance = $row['net_amount'] - $paid;
                                $total_billed += $row['net_amount'];
                                $total_paid += $paid;
                                $total_balance += $balance;
                            ?>
                            <tr>
                                <td><?php echo get_month_name($row['billing_month']) . ' ' . $row['billing_year']; ?></td>
                                <td><?php echo format_currency($row['internet_fee']); ?></td>
                                <td><?php echo format_currency($row['cable_fee']); ?></td>
                                <td><?php echo format_currency($row['service_fee']); ?></td>
                                <td><?php echo format_currency($row['net_amount']); ?></td>
                                <td><?php echo format_currency($paid); ?></td>
                                <td><?php echo format_currency($balance); ?></td>
                                <td>
                                    <?php
                                    $status_class = 'danger';
                                    if ($row['status'] == 'paid') $status_class = 'success';
                                    elseif ($row['status'] == 'partial') $status_class = 'warning';
                                    ?>
                                    <span class="badge badge-<?php echo $status_class; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $row['due_date'] ? date('M d, Y', strtotime($row['due_date'])) : 'N/A'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <tr style="background: var(--light-gray); font-weight: bold;">
                                <td colspan="4" class="text-right">TOTAL:</td>
                                <td><?php echo format_currency($total_billed); ?></td>
                                <td><?php echo format_currency($total_paid); ?></td>
                                <td><?php echo format_currency($total_balance); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">No billing records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-2">
                <a href="customers.php" class="btn btn-secondary">Back to Customers</a>
            </div>
        </main>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>