<?php
require_once 'config.php';
check_permission('accounting');
$conn = getDBConnection();

$area_filter = isset($_GET['area']) ? intval($_GET['area']) : 0;
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : 0;
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$sql = "SELECT c.customer_id, c.account_number, c.subscriber_name, c.address, c.tel_no,
        a.area_name, b.billing_id, b.billing_month, b.billing_year, b.net_amount,
        b.due_date, DATEDIFF(CURDATE(), b.due_date) as days_overdue
        FROM customers c
        JOIN billings b ON c.customer_id = b.customer_id
        LEFT JOIN areas a ON c.area_id = a.area_id
        WHERE b.status IN ('unpaid', 'partial')
        AND c.status IN ('active','reconnected','hold_disconnection')";

if ($area_filter > 0) $sql .= " AND c.area_id = $area_filter";
if ($month_filter > 0) $sql .= " AND b.billing_month = $month_filter";
if ($year_filter > 0) $sql .= " AND b.billing_year = $year_filter";
$sql .= " ORDER BY b.billing_year DESC, b.billing_month DESC, c.subscriber_name ASC";
$result = $conn->query($sql);
$areas = $conn->query("SELECT * FROM areas ORDER BY area_name");
$total_unpaid = 0; $total_customers = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unpaid Bills - AR NOVALINK</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header no-print">
                <div>
                    <h1>Unpaid Subscriptions</h1>
                    <p>View subscribers with outstanding balance</p>
                </div>
            </div>
            
            <!-- Print header -->
            <div class="print-header">
                <img src="images/headerlogo.png" alt="NovaLink" class="print-header-logo">
                <h2>UNPAID SUBSCRIPTIONS REPORT</h2>
                <?php if ($month_filter > 0): ?>
                <h3><?php echo get_month_name($month_filter) . ' ' . $year_filter; ?></h3>
                <?php else: ?>
                <h3>Year <?php echo $year_filter; ?></h3>
                <?php endif; ?>
                <p class="text-muted">Generated: <?php echo date('F d, Y h:i A'); ?></p>
            </div>
            
            <div class="table-container">
                <div class="table-header no-print">
                    <h2>Unpaid Bills</h2>
                    <button onclick="window.print()" class="btn btn-primary">🖨️ Print Report</button>
                </div>
                
                <div class="table-filter-bar no-print">
                    <form method="GET" class="filter-group">
                        <select name="area"><option value="0">All Areas</option>
                            <?php while ($area = $areas->fetch_assoc()): ?>
                            <option value="<?php echo $area['area_id']; ?>" <?php echo $area_filter==$area['area_id']?'selected':''; ?>><?php echo htmlspecialchars($area['area_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <select name="month"><option value="0">All Months</option>
                            <?php for ($m=1;$m<=12;$m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $month_filter==$m?'selected':''; ?>><?php echo get_month_name($m); ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="year">
                            <?php for ($y=date('Y');$y>=date('Y')-2;$y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year_filter==$y?'selected':''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn btn-secondary">Apply Filter</button>
                        <a href="unpaid.php" class="btn btn-secondary">Reset</a>
                    </form>
                </div>
                
                <table>
                    <thead><tr>
                        <th>Account #</th>
                        <th>Subscriber Name</th>
                        <th>Area</th>
                        <th>Billing Period</th>
                        <th>Amount Due</th>
                        <th>Due Date</th>
                        <th>Days Overdue</th>
                        <th class="actions-col no-print">Actions</th>
                    </tr></thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): while ($row = $result->fetch_assoc()): $total_unpaid += $row['net_amount']; $total_customers++; ?>
                        <tr class="<?php echo $row['days_overdue']>30?'table-row-overdue':''; ?>">
                            <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['subscriber_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['area_name']??'N/A'); ?></td>
                            <td><?php echo get_month_name($row['billing_month']).' '.$row['billing_year']; ?></td>
                            <td><?php echo format_currency($row['net_amount']); ?></td>
                            <td><?php echo $row['due_date']?date('M d, Y',strtotime($row['due_date'])):'N/A'; ?></td>
                            <td>
                                <?php if ($row['days_overdue'] > 0): ?>
                                    <span class="badge badge-danger"><?php echo $row['days_overdue']; ?> days</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Due soon</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-col no-print">
                                <a href="customer_ledger.php?id=<?php echo $row['customer_id']; ?>" class="btn btn-sm btn-primary">Ledger</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <tr class="table-row-total">
                            <td colspan="4" class="text-right">TOTAL UNPAID (<?php echo $total_customers; ?> subscribers):</td>
                            <td><?php echo format_currency($total_unpaid); ?></td>
                            <td colspan="3"></td>
                        </tr>
                        <?php else: ?>
                        <tr><td colspan="8" class="text-center">No unpaid bills found</td></tr>
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
