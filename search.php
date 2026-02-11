<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();
$search_results = null;
$search_term = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = sanitize_input($_GET['search']);
    
    $stmt = $conn->prepare("
        SELECT c.*, a.area_name, p.package_name,
        (SELECT COUNT(*) FROM billings WHERE customer_id = c.customer_id AND status = 'unpaid') as unpaid_count,
        (SELECT COALESCE(SUM(net_amount), 0) FROM billings WHERE customer_id = c.customer_id AND status = 'unpaid') as unpaid_total
        FROM customers c
        LEFT JOIN areas a ON c.area_id = a.area_id
        LEFT JOIN packages p ON c.package_id = p.package_id
        WHERE c.account_number LIKE ? OR c.subscriber_name LIKE ? OR c.address LIKE ? OR c.tel_no LIKE ?
        ORDER BY c.subscriber_name
    ");
    
    $search_like = "%$search_term%";
    $stmt->bind_param("ssss", $search_like, $search_like, $search_like, $search_like);
    $stmt->execute();
    $search_results = $stmt->get_result();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - AR NOVALINK Billing System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Customer Search</h1>
                <p>Search for customer accounts and billing information</p>
            </div>
            
            <div class="widget mb-3">
                <div class="widget-header">
                    <h2>Search Customer</h2>
                </div>
                <div class="widget-content">
                    <form method="GET" action="">
                        <div class="form-group">
                            <label for="search">Enter account number, name, address, or phone number</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search..." style="flex: 1;" autofocus>
                                <button type="submit" class="btn btn-primary">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"/>
                                        <path d="m21 21-4.35-4.35"/>
                                    </svg>
                                    Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($search_results !== null): ?>
                <?php if ($search_results->num_rows > 0): ?>
                    <div class="table-container">
                        <div class="table-header">
                            <h2>Search Results (<?php echo $search_results->num_rows; ?> found)</h2>
                        </div>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>Account #</th>
                                    <th>Subscriber Name</th>
                                    <th>Address</th>
                                    <th>Area</th>
                                    <th>Tel No</th>
                                    <th>Package</th>
                                    <th>Monthly Fee</th>
                                    <th>Unpaid Bills</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $search_results->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['subscriber_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['address']); ?></td>
                                    <td><?php echo htmlspecialchars($row['area_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['tel_no'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['package_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo format_currency($row['monthly_fee']); ?></td>
                                    <td>
                                        <?php if ($row['unpaid_count'] > 0): ?>
                                            <span class="badge badge-danger">
                                                <?php echo $row['unpaid_count']; ?> bills (<?php echo format_currency($row['unpaid_total']); ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-success">All Paid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'secondary';
                                        if ($row['status'] == 'active') $status_class = 'success';
                                        elseif ($row['status'] == 'disconnected') $status_class = 'danger';
                                        elseif ($row['status'] == 'suspended') $status_class = 'warning';
                                        ?>
                                        <span class="badge badge-<?php echo $status_class; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="customer_ledger.php?id=<?php echo $row['customer_id']; ?>" class="btn btn-sm btn-primary">View Ledger</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                        </svg>
                        No customers found matching "<?php echo htmlspecialchars($search_term); ?>"
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                    </svg>
                    Enter a search term to find customers
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>