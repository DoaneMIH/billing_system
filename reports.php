<?php
require_once 'config.php';
check_permission('accounting');

$conn = getDBConnection();

// Get date range
$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : date('Y-m-d');
$area_filter = isset($_GET['area']) ? intval($_GET['area']) : 0;

// Get payment summary
$sql = "SELECT 
        COUNT(DISTINCT p.payment_id) as total_payments,
        COUNT(DISTINCT p.customer_id) as unique_customers,
        SUM(p.amount_paid) as total_collected,
        SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount_paid ELSE 0 END) as cash_payments,
        SUM(CASE WHEN p.payment_method = 'check' THEN p.amount_paid ELSE 0 END) as check_payments,
        SUM(CASE WHEN p.payment_method = 'online' THEN p.amount_paid ELSE 0 END) as online_payments
        FROM payments p
        JOIN customers c ON p.customer_id = c.customer_id
        WHERE p.payment_date BETWEEN '$start_date' AND '$end_date'";

if ($area_filter > 0) {
    $sql .= " AND c.area_id = $area_filter";
}

$summary = $conn->query($sql)->fetch_assoc();

// Get detailed payments
$sql = "SELECT p.*, c.subscriber_name, c.account_number, a.area_name, 
        b.billing_month, b.billing_year
        FROM payments p
        JOIN customers c ON p.customer_id = c.customer_id
        JOIN billings b ON p.billing_id = b.billing_id
        LEFT JOIN areas a ON c.area_id = a.area_id
        WHERE p.payment_date BETWEEN '$start_date' AND '$end_date'";

if ($area_filter > 0) {
    $sql .= " AND c.area_id = $area_filter";
}

$sql .= " ORDER BY p.payment_date DESC, p.created_at DESC";
$payments = $conn->query($sql);

// Get areas for filter
$areas = $conn->query("SELECT * FROM areas ORDER BY area_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - AR NOVALINK Billing System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Reports & Analytics</h1>
                <p>Generate payment and billing reports</p>
            </div>
            
            <div class="widget mb-3">
                <div class="widget-header">
                    <h2>Report Filters</h2>
                </div>
                <div class="widget-content">
                    <form method="GET" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="area">Area/Barangay</label>
                                <select id="area" name="area">
                                    <option value="0">All Areas</option>
                                    <?php while ($area = $areas->fetch_assoc()): ?>
                                    <option value="<?php echo $area['area_id']; ?>" <?php echo $area_filter == $area['area_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($area['area_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="display: flex; align-items: flex-end; gap: 10px;">
                                <button type="submit" class="btn btn-primary">Generate Report</button>
                                <button type="button" onclick="window.print()" class="btn btn-secondary">Print</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="stats-grid mb-3">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($summary['total_payments']); ?></h3>
                        <p>Total Payments</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                        </svg>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($summary['unique_customers']); ?></h3>
                        <p>Paying Customers</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo format_currency($summary['total_collected']); ?></h3>
                        <p>Total Collected</p>
                    </div>
                </div>
            </div>
            
            <div class="widget mb-3">
                <div class="widget-header">
                    <h2>Payment Method Breakdown</h2>
                </div>
                <div class="widget-content">
                    <div class="form-row">
                        <div>
                            <strong>Cash:</strong> <?php echo format_currency($summary['cash_payments']); ?>
                        </div>
                        <div>
                            <strong>Check:</strong> <?php echo format_currency($summary['check_payments']); ?>
                        </div>
                        <div>
                            <strong>Online:</strong> <?php echo format_currency($summary['online_payments']); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <div class="table-header">
                    <h2>Payment Transactions (<?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>)</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>OR Number</th>
                            <th>Customer</th>
                            <th>Account #</th>
                            <th>Area</th>
                            <th>Period</th>
                            <th>Amount</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($payments->num_rows > 0): ?>
                            <?php while ($row = $payments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['payment_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['or_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['subscriber_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['area_name'] ?? 'N/A'); ?></td>
                                <td><?php echo get_month_name($row['billing_month']) . ' ' . $row['billing_year']; ?></td>
                                <td><?php echo format_currency($row['amount_paid']); ?></td>
                                <td><?php echo ucfirst($row['payment_method']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No payments found for selected period</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>