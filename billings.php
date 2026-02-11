<?php
require_once 'config.php';
check_permission('accounting');

$conn = getDBConnection();

// Handle billing generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'generate') {
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    
    // Get all active customers
    $customers = $conn->query("SELECT customer_id, monthly_fee FROM customers WHERE status = 'active'");
    
    $generated = 0;
    $skipped = 0;
    
    while ($customer = $customers->fetch_assoc()) {
        // Check if billing already exists
        $check = $conn->query("SELECT billing_id FROM billings WHERE customer_id = {$customer['customer_id']} AND billing_month = $month AND billing_year = $year");
        
        if ($check->num_rows == 0) {
            // Generate billing
            $due_date = date('Y-m-d', strtotime("$year-$month-10")); // Due on 10th of month
            $stmt = $conn->prepare("INSERT INTO billings (customer_id, billing_month, billing_year, internet_fee, total_amount, net_amount, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiddds", $customer['customer_id'], $month, $year, $customer['monthly_fee'], $customer['monthly_fee'], $customer['monthly_fee'], $due_date);
            $stmt->execute();
            $stmt->close();
            $generated++;
        } else {
            $skipped++;
        }
    }
    
    log_activity($_SESSION['user_id'], 'GENERATE_BILLING', 'billings', null, "Generated $generated billings for " . get_month_name($month) . " $year");
    $success = "Generated $generated new billings. Skipped $skipped existing billings.";
}

// Get billings
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

$sql = "SELECT b.*, c.account_number, c.subscriber_name, a.area_name,
        COALESCE((SELECT SUM(amount_paid) FROM payments WHERE billing_id = b.billing_id), 0) as total_paid
        FROM billings b
        JOIN customers c ON b.customer_id = c.customer_id
        LEFT JOIN areas a ON c.area_id = a.area_id
        WHERE b.billing_month = $month_filter AND b.billing_year = $year_filter";

if ($status_filter) {
    $sql .= " AND b.status = '$status_filter'";
}

$sql .= " ORDER BY c.subscriber_name";

$billings = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billings - AR NOVALINK Billing System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Billing Management</h1>
                <p>Generate and manage monthly billings</p>
            </div>
            
            <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                </svg>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <div class="widget mb-3">
                <div class="widget-header">
                    <h2>Generate Monthly Billing</h2>
                </div>
                <div class="widget-content">
                    <form method="POST" action="" onsubmit="return confirm('Generate billing for all active customers?');">
                        <input type="hidden" name="action" value="generate">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="month">Month</label>
                                <select id="month" name="month" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>>
                                        <?php echo get_month_name($m); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="year">Year</label>
                                <select id="year" name="year" required>
                                    <?php for ($y = date('Y'); $y <= date('Y') + 1; $y++): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-primary">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                    </svg>
                                    Generate Billings
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="table-container">
                <div class="table-header">
                    <h2>View Billings</h2>
                </div>
                
                <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                    <form method="GET" action="" class="filter-group">
                        <select name="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $month_filter ? 'selected' : ''; ?>>
                                <?php echo get_month_name($m); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                        
                        <select name="year">
                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year_filter ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                        
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="unpaid" <?php echo $status_filter == 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                            <option value="partial" <?php echo $status_filter == 'partial' ? 'selected' : ''; ?>>Partial</option>
                            <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>
                        
                        <button type="submit" class="btn btn-secondary">Filter</button>
                    </form>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Account #</th>
                            <th>Subscriber</th>
                            <th>Area</th>
                            <th>Period</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($billings->num_rows > 0): ?>
                            <?php while ($row = $billings->fetch_assoc()): 
                                $balance = $row['net_amount'] - $row['total_paid'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['subscriber_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['area_name'] ?? 'N/A'); ?></td>
                                <td><?php echo get_month_name($row['billing_month']) . ' ' . $row['billing_year']; ?></td>
                                <td><?php echo format_currency($row['net_amount']); ?></td>
                                <td><?php echo format_currency($row['total_paid']); ?></td>
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
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">No billings found for <?php echo get_month_name($month_filter) . ' ' . $year_filter; ?></td>
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