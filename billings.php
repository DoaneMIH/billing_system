<?php
require_once 'config.php';
check_permission('accounting');
$conn = getDBConnection();

// Handle billing generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'generate' && $_SESSION['role'] == 'admin') {
        $month = intval($_POST['month']);
        $year = intval($_POST['year']);
        $generated = 0; $skipped = 0; $errors = [];
        
        $customers = $conn->query("SELECT c.customer_id, c.account_number, c.subscriber_name, c.monthly_fee, c.status, c.date_connected, c.disconnection_date FROM customers c ORDER BY c.account_number");
        
        while ($customer = $customers->fetch_assoc()) {
            $customer_id = $customer['customer_id'];
            $status = $customer['status'];
            
            $check = $conn->query("SELECT billing_id FROM billings WHERE customer_id = $customer_id AND billing_month = $month AND billing_year = $year");
            if ($check->num_rows > 0) { $skipped++; continue; }
            
            $billing_date = "$year-$month-01";
            $should_bill = true;
            
            if ($status == 'disconnected' && $customer['disconnection_date']) {
                $disconnection_month = date('Y-m', strtotime($customer['disconnection_date']));
                $current_billing = date('Y-m', strtotime($billing_date));
                if ($disconnection_month <= $current_billing) $should_bill = false;
            }
            if ($status == 'pending_installation') $should_bill = false;
            
            $previous_balance = 0.00;
            if ($status == 'active' || $status == 'hold_disconnection' || $status == 'reconnected') {
                $prev_billings = $conn->query("SELECT b.billing_id, b.net_amount, COALESCE(SUM(p.amount_paid),0) as total_paid FROM billings b LEFT JOIN payments p ON b.billing_id = p.billing_id WHERE b.customer_id = $customer_id AND (b.billing_year < $year OR (b.billing_year = $year AND b.billing_month < $month)) AND b.status IN ('unpaid','partial') GROUP BY b.billing_id, b.net_amount");
                while ($prev = $prev_billings->fetch_assoc()) {
                    $previous_balance += ($prev['net_amount'] - $prev['total_paid']);
                }
            }
            
            if (!$should_bill) { $skipped++; continue; }
            
            $internet_fee = $customer['monthly_fee'];
            $total_amount = $previous_balance + $internet_fee;
            $net_amount = $total_amount;
            $last_day = date('t', strtotime($billing_date));
            $due_date = "$year-$month-$last_day";
            
            $stmt = $conn->prepare("INSERT INTO billings (customer_id, billing_month, billing_year, internet_fee, cable_fee, service_fee, material_fee, previous_balance, total_amount, discount, net_amount, due_date, auto_generated) VALUES (?,?,?,?,0,0,0,?,?,0,?,?,1)");
            $stmt->bind_param("iiiddddds", $customer_id, $month, $year, $internet_fee, $previous_balance, $total_amount, $net_amount, $due_date);
            if ($stmt->execute()) { $generated++; } else { $errors[] = $stmt->error; }
            $stmt->close();
        }
        
        log_activity($_SESSION['user_id'], 'GENERATE_BILLING', 'billings', null, "Generated $generated billings for " . get_month_name($month) . " $year");
        $success = "Generated $generated billings. Skipped $skipped.";
        if (count($errors) > 0) $error = implode(', ', $errors);
    }
    
    // Add Fees (improved with specific fee types)
    if ($_POST['action'] == 'add_fees' && $_SESSION['role'] == 'admin') {
        $billing_id = intval($_POST['billing_id']);
        $fee_type = sanitize_input($_POST['fee_type']);
        $fee_amount = floatval($_POST['fee_amount']);
        $fee_description = sanitize_input($_POST['fee_description'] ?? '');
        
        if ($fee_amount > 0) {
            // Insert into billing_fees table
            $stmt = $conn->prepare("INSERT INTO billing_fees (billing_id, fee_type, fee_description, amount, created_by) VALUES (?,?,?,?,?)");
            $stmt->bind_param("issdi", $billing_id, $fee_type, $fee_description, $fee_amount, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
            
            // Update billing totals
            $billing = $conn->query("SELECT * FROM billings WHERE billing_id = $billing_id")->fetch_assoc();
            if ($billing) {
                $new_service = $billing['service_fee'] + $fee_amount;
                $current_charges = $billing['internet_fee'] + $billing['cable_fee'] + $new_service + $billing['material_fee'];
                $total = $billing['previous_balance'] + $current_charges;
                $net = $total - $billing['discount'];
                
                $conn->query("UPDATE billings SET service_fee = $new_service, total_amount = $total, net_amount = $net WHERE billing_id = $billing_id");
            }
            
            log_activity($_SESSION['user_id'], 'ADD_FEES', 'billings', $billing_id, "Added $fee_type fee: ₱$fee_amount - $fee_description");
            $success = "Fee added successfully!";
        } else {
            $error = "Fee amount must be greater than 0.";
        }
    }
}

// Filters
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$area_filter = isset($_GET['area']) ? intval($_GET['area']) : 0;
$package_filter = isset($_GET['package']) ? intval($_GET['package']) : 0;

$sql = "SELECT b.*, c.account_number, c.subscriber_name, c.status as customer_status, c.package_id, a.area_name,
        COALESCE((SELECT SUM(amount_paid) FROM payments WHERE billing_id = b.billing_id), 0) as total_paid
        FROM billings b
        JOIN customers c ON b.customer_id = c.customer_id
        LEFT JOIN areas a ON c.area_id = a.area_id
        WHERE b.billing_month = $month_filter AND b.billing_year = $year_filter";

if ($status_filter) $sql .= " AND b.status = '" . $conn->real_escape_string($status_filter) . "'";
if ($area_filter > 0) $sql .= " AND c.area_id = $area_filter";
if ($package_filter > 0) $sql .= " AND c.package_id = $package_filter";

$sql .= " ORDER BY c.subscriber_name";
$billings = $conn->query($sql);

$areas = $conn->query("SELECT * FROM areas ORDER BY area_name");
$pkgs = $conn->query("SELECT * FROM packages WHERE status='active' ORDER BY package_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billings - AR NOVALINK</title>
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
            
            <?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <?php if (isset($error)): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <div class="widget mb-3">
                <div class="widget-header"><h2>Generate Monthly Billing</h2></div>
                <div class="widget-content">
                    <form method="POST" onsubmit="return confirm('Generate billing for all eligible customers?');">
                        <input type="hidden" name="action" value="generate">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Month</label>
                                <select name="month" required>
                                    <?php for ($m=1;$m<=12;$m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m==date('n')?'selected':''; ?>><?php echo get_month_name($m); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Year</label>
                                <select name="year" required>
                                    <?php for ($y=date('Y');$y<=date('Y')+1;$y++): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group" style="display:flex;align-items:flex-end;">
                                <button type="submit" class="btn btn-primary">Generate Billings</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="table-container">
                <div class="table-header">
                    <h2>View Billings</h2>
                    <div class="table-actions">
                        <?php 
                        $print_params = "month=$month_filter&year=$year_filter";
                        if ($status_filter) $print_params .= "&status=$status_filter";
                        if ($area_filter) $print_params .= "&area_id=$area_filter";
                        if ($package_filter) $print_params .= "&package_id=$package_filter";
                        ?>
                        <a href="print_billing_statement.php?bulk=1&<?php echo $print_params; ?>" target="_blank" class="btn btn-primary btn-sm">🖨️ Bulk Print Statements</a>
                    </div>
                </div>
                
                <div style="padding:15px;border-bottom:1px solid var(--border-color);">
                    <form method="GET" class="filter-group" style="flex-wrap:wrap;gap:8px;">
                        <select name="month">
                            <?php for ($m=1;$m<=12;$m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m==$month_filter?'selected':''; ?>><?php echo get_month_name($m); ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="year">
                            <?php for ($y=date('Y');$y>=date('Y')-2;$y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y==$year_filter?'selected':''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="unpaid" <?php echo $status_filter=='unpaid'?'selected':''; ?>>Unpaid</option>
                            <option value="partial" <?php echo $status_filter=='partial'?'selected':''; ?>>Partial</option>
                            <option value="paid" <?php echo $status_filter=='paid'?'selected':''; ?>>Paid</option>
                        </select>
                        <select name="area">
                            <option value="0">All Areas</option>
                            <?php while ($a = $areas->fetch_assoc()): ?>
                            <option value="<?php echo $a['area_id']; ?>" <?php echo $area_filter==$a['area_id']?'selected':''; ?>><?php echo htmlspecialchars($a['area_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <select name="package">
                            <option value="0">All Packages</option>
                            <?php while ($pk = $pkgs->fetch_assoc()): ?>
                            <option value="<?php echo $pk['package_id']; ?>" <?php echo $package_filter==$pk['package_id']?'selected':''; ?>><?php echo htmlspecialchars($pk['package_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" class="btn btn-secondary">Filter</button>
                    </form>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Account #</th><th>Subscriber</th><th>Period</th><th>Prev Balance</th>
                            <th>Current</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($billings->num_rows > 0): while ($row = $billings->fetch_assoc()):
                            $current_charges = $row['internet_fee'] + $row['cable_fee'] + $row['service_fee'] + $row['material_fee'];
                            $balance = $row['net_amount'] - $row['total_paid'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['subscriber_name']); ?></td>
                            <td><?php echo get_month_name($row['billing_month']).' '.$row['billing_year']; ?></td>
                            <td><?php echo format_currency($row['previous_balance']); ?></td>
                            <td><?php echo format_currency($current_charges); ?></td>
                            <td><?php echo format_currency($row['net_amount']); ?></td>
                            <td><?php echo format_currency($row['total_paid']); ?></td>
                            <td><?php echo format_currency($balance); ?></td>
                            <td>
                                <?php $sc = $row['status']=='paid'?'success':($row['status']=='partial'?'warning':'danger'); ?>
                                <span class="badge badge-<?php echo $sc; ?>"><?php echo ucfirst($row['status']); ?></span>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="print_billing_statement.php?id=<?php echo $row['customer_id']; ?>&month=<?php echo $row['billing_month']; ?>&year=<?php echo $row['billing_year']; ?>" target="_blank" class="btn btn-sm btn-primary">Print SOA</a>
                                <?php if ($_SESSION['role'] == 'admin'): ?>
                                <button onclick="openAddFeesModal(<?php echo $row['billing_id']; ?>, '<?php echo htmlspecialchars($row['subscriber_name']); ?>', '<?php echo get_month_name($row['billing_month']).' '.$row['billing_year']; ?>')" class="btn btn-sm btn-secondary">Add Fees</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="10" class="text-center">No billings found for <?php echo get_month_name($month_filter).' '.$year_filter; ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <?php if ($_SESSION['role'] == 'admin'): ?>
    <div id="addFeesModal" class="modal">
        <div class="modal-content" style="max-width:550px;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);">
            <div class="modal-header"><h2>Add Fee</h2><button class="modal-close" onclick="closeModal('addFeesModal')">&times;</button></div>
            <form method="POST"><div class="modal-body">
                <input type="hidden" name="action" value="add_fees">
                <input type="hidden" id="modal_billing_id" name="billing_id">
                <div class="alert alert-info">
                    <strong>Customer:</strong> <span id="modal_customer_name"></span><br>
                    <strong>Period:</strong> <span id="modal_billing_period"></span>
                </div>
                <div class="form-group">
                    <label>Fee Type *</label>
                    <select name="fee_type" required>
                        <option value="installation">Installation Fee</option>
                        <option value="reconnection">Reconnection Fee</option>
                        <option value="adjustment">Adjustment Fee</option>
                        <option value="other">Other Charges</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (₱) *</label>
                    <input type="number" step="0.01" name="fee_amount" required min="0.01">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="fee_description" rows="2" placeholder="Reason for the fee..."></textarea>
                </div>
            </div><div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addFeesModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Fee</button>
            </div></form>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="js/script.js"></script>
    <script>
        function openAddFeesModal(billingId, name, period) {
            document.getElementById('modal_billing_id').value = billingId;
            document.getElementById('modal_customer_name').textContent = name;
            document.getElementById('modal_billing_period').textContent = period;
            document.getElementById('addFeesModal').classList.add('show');
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
