<?php
require_once 'config.php';

// Only admin and accounting can access
check_permission('accounting');

$conn = getDBConnection();

// Get filters
$area_filter = isset($_GET['area']) ? intval($_GET['area']) : 0;
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : 0;
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Build query
$sql = "SELECT c.customer_id, c.account_number, c.subscriber_name, c.address, c.tel_no,
        a.area_name, b.billing_id, b.billing_month, b.billing_year, b.net_amount,
        b.due_date, DATEDIFF(CURDATE(), b.due_date) as days_overdue
        FROM customers c
        JOIN billings b ON c.customer_id = b.customer_id
        LEFT JOIN areas a ON c.area_id = a.area_id
        WHERE b.status IN ('unpaid', 'partial')
        AND c.status = 'active'";

if ($area_filter > 0) {
    $sql .= " AND c.area_id = $area_filter";
}

if ($month_filter > 0) {
    $sql .= " AND b.billing_month = $month_filter";
}

if ($year_filter > 0) {
    $sql .= " AND b.billing_year = $year_filter";
}

$sql .= " ORDER BY b.billing_year DESC, b.billing_month DESC, c.subscriber_name ASC";

$result = $conn->query($sql);

// Get areas for filter
$areas = $conn->query("SELECT * FROM areas ORDER BY area_name");

// Calculate totals
$total_unpaid = 0;
$total_customers = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unpaid Bills - AR NOVALINK Billing System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        @media print {
            .no-print, .main-header, .sidebar, .filter-section, .btn {
                display: none !important;
            }
            .container {
                display: block !important;
            }
            .main-content {
                padding: 0 !important;
            }
            body {
                background: white !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Unpaid Subscriptions</h1>
                <p>View customers with outstanding balance</p>
            </div>
            
            <div class="table-container">
                <div class="table-header no-print">
                    <h2>Filter Unpaid Bills</h2>
                    <button onclick="window.print()" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 6 2 18 2 18 9"/>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                            <rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Print Report
                    </button>
                </div>
                
                <div style="padding: 20px; border-bottom: 1px solid var(--border-color);" class="filter-section no-print">
                    <form method="GET" action="" class="filter-group">
                        <select name="area">
                            <option value="0">All Areas/Barangays</option>
                            <?php while ($area = $areas->fetch_assoc()): ?>
                            <option value="<?php echo $area['area_id']; ?>" <?php echo $area_filter == $area['area_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($area['area_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        
                        <select name="month">
                            <option value="0">All Months</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $month_filter == $m ? 'selected' : ''; ?>>
                                <?php echo get_month_name($m); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                        
                        <select name="year">
                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year_filter == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                        
                        <button type="submit" class="btn btn-secondary">Apply Filter</button>
                        <a href="unpaid.php" class="btn btn-secondary">Reset</a>
                    </form>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Account #</th>
                            <th>Subscriber Name</th>
                            <th>Address</th>
                            <th>Area/Barangay</th>
                            <th>Contact</th>
                            <th>Billing Period</th>
                            <th>Amount Due</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): 
                                $total_unpaid += $row['net_amount'];
                                $total_customers++;
                            ?>
                            <tr <?php echo $row['days_overdue'] > 30 ? 'style="background: #fff3cd;"' : ''; ?>>
                                <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['subscriber_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['address']); ?></td>
                                <td><?php echo htmlspecialchars($row['area_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['tel_no'] ?? 'N/A'); ?></td>
                                <td><?php echo get_month_name($row['billing_month']) . ' ' . $row['billing_year']; ?></td>
                                <td><?php echo format_currency($row['net_amount']); ?></td>
                                <td><?php echo $row['due_date'] ? date('M d, Y', strtotime($row['due_date'])) : 'N/A'; ?></td>
                                <td>
                                    <?php if ($row['days_overdue'] > 0): ?>
                                        <span class="badge badge-danger"><?php echo $row['days_overdue']; ?> days</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Due soon</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="customer_ledger.php?id=<?php echo $row['customer_id']; ?>" class="btn btn-sm btn-primary">View Ledger</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <tr style="background: var(--light-gray); font-weight: bold;">
                                <td colspan="6" class="text-right">TOTAL UNPAID (<?php echo $total_customers; ?> customers):</td>
                                <td><?php echo format_currency($total_unpaid); ?></td>
                                <td colspan="3"></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center">No unpaid bills found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($result->num_rows > 0): ?>
            <div class="widget mt-3">
                <div class="widget-header">
                    <h2>Summary</h2>
                </div>
                <div class="widget-content">
                    <div class="form-row">
                        <div>
                            <strong>Total Customers with Unpaid Bills:</strong> <?php echo number_format($total_customers); ?>
                        </div>
                        <div>
                            <strong>Total Outstanding Amount:</strong> <?php echo format_currency($total_unpaid); ?>
                        </div>
                    </div>
                    <?php if ($area_filter > 0): ?>
                    <div class="mt-1">
                        <strong>Filtered by Area:</strong> 
                        <?php
                        $areas->data_seek(0);
                        while ($area = $areas->fetch_assoc()) {
                            if ($area['area_id'] == $area_filter) {
                                echo htmlspecialchars($area['area_name']);
                                break;
                            }
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>