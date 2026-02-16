<?php
require_once 'config.php';
check_permission('accounting');

$conn = getDBConnection();

// Handle billing generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'generate' && $_SESSION['role'] == 'admin') {
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    
    $generated = 0;
    $skipped = 0;
    $errors = [];
    
    // Get all customers
    $customers = $conn->query("
        SELECT 
            c.customer_id, 
            c.account_number,
            c.subscriber_name,
            c.monthly_fee,
            c.status,
            c.date_connected,
            c.disconnection_date
        FROM customers c
        ORDER BY c.account_number
    ");
    
    while ($customer = $customers->fetch_assoc()) {
        $customer_id = $customer['customer_id'];
        $status = $customer['status'];
        
        // Check if billing already exists
        $check = $conn->query("
            SELECT billing_id 
            FROM billings 
            WHERE customer_id = $customer_id 
            AND billing_month = $month 
            AND billing_year = $year
        ");
        
        if ($check->num_rows > 0) {
            $skipped++;
            continue;
        }
        
        // BILLING LOGIC
        
        // 1. If customer is disconnected, check if disconnection was AFTER the billing month
        $billing_date = "$year-$month-01";
        $should_bill = true;
        
        if ($status == 'disconnected' && $customer['disconnection_date']) {
            $disconnection_month = date('Y-m', strtotime($customer['disconnection_date']));
            $current_billing = date('Y-m', strtotime($billing_date));
            
            // If disconnected BEFORE or DURING this billing month, don't create new bills
            if ($disconnection_month <= $current_billing) {
                $should_bill = false;
            }
        }
        
        // 2. Calculate previous balance (unpaid from previous months)
        $previous_balance = 0.00;
        
        if ($status == 'active' || $status == 'hold_disconnection') {
            // Get unpaid balance from all previous billings
            $prev_billings = $conn->query("
                SELECT 
                    b.billing_id,
                    b.net_amount,
                    COALESCE(SUM(p.amount_paid), 0) as total_paid
                FROM billings b
                LEFT JOIN payments p ON b.billing_id = p.billing_id
                WHERE b.customer_id = $customer_id
                AND (
                    b.billing_year < $year 
                    OR (b.billing_year = $year AND b.billing_month < $month)
                )
                AND b.status IN ('unpaid', 'partial')
                GROUP BY b.billing_id, b.net_amount
            ");
            
            while ($prev = $prev_billings->fetch_assoc()) {
                $balance = $prev['net_amount'] - $prev['total_paid'];
                $previous_balance += $balance;
            }
        }
        
        // 3. Only generate billing if customer should be billed
        if (!$should_bill) {
            $skipped++;
            continue;
        }
        
        // 4. Calculate current month charges
        $internet_fee = $customer['monthly_fee'];
        $cable_fee = 0.00;
        $service_fee = 0.00;
        $material_fee = 0.00;
        
        // 5. Calculate totals
        $current_charges = $internet_fee + $cable_fee + $service_fee + $material_fee;
        $total_amount = $previous_balance + $current_charges;
        $discount = 0.00;
        $net_amount = $total_amount - $discount;
        
        // 6. Set due date (end of month)
        $last_day = date('t', strtotime($billing_date));
        $due_date = "$year-$month-$last_day";
        
        // 7. Insert billing
        $stmt = $conn->prepare("
            INSERT INTO billings (
                customer_id, 
                billing_month, 
                billing_year, 
                internet_fee, 
                cable_fee, 
                service_fee, 
                material_fee,
                previous_balance,
                total_amount, 
                discount,
                net_amount, 
                due_date,
                auto_generated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->bind_param(
            "iiidddddddds", 
            $customer_id, 
            $month, 
            $year, 
            $internet_fee,
            $cable_fee,
            $service_fee,
            $material_fee,
            $previous_balance,
            $total_amount,
            $discount,
            $net_amount,
            $due_date
        );
        
        if ($stmt->execute()) {
            $generated++;
        } else {
            $errors[] = "Error for {$customer['account_number']}: " . $stmt->error;
        }
        $stmt->close();
    }
    
    log_activity($_SESSION['user_id'], 'GENERATE_BILLING', 'billings', null, "Generated $generated billings for " . get_month_name($month) . " $year");
    
    if (count($errors) > 0) {
        $error = "Generated $generated billings, skipped $skipped. Errors: " . implode(', ', $errors);
    } else {
        $success = "Successfully generated $generated new billings. Skipped $skipped existing billings.";
    }
}

// Add manual fees to existing billing (Admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_fees' && $_SESSION['role'] == 'admin') {
    $billing_id = intval($_POST['billing_id']);
    $additional_service = floatval($_POST['additional_service'] ?? 0);
    $additional_material = floatval($_POST['additional_material'] ?? 0);
    $fee_description = sanitize_input($_POST['fee_description'] ?? '');
    
    // Get current billing
    $stmt = $conn->prepare("SELECT * FROM billings WHERE billing_id = ?");
    $stmt->bind_param("i", $billing_id);
    $stmt->execute();
    $billing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($billing) {
        // Update fees
        $new_service_fee = $billing['service_fee'] + $additional_service;
        $new_material_fee = $billing['material_fee'] + $additional_material;
        
        // Recalculate totals
        $current_charges = $billing['internet_fee'] + $billing['cable_fee'] + $new_service_fee + $new_material_fee;
        $total_amount = $billing['previous_balance'] + $current_charges;
        $net_amount = $total_amount - $billing['discount'];
        
        $stmt = $conn->prepare("
            UPDATE billings 
            SET service_fee = ?,
                material_fee = ?,
                total_amount = ?,
                net_amount = ?,
                auto_generated = 0
            WHERE billing_id = ?
        ");
        $stmt->bind_param("ddddi", $new_service_fee, $new_material_fee, $total_amount, $net_amount, $billing_id);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'ADD_FEES', 'billings', $billing_id, "Added fees: Service ₱$additional_service, Material ₱$additional_material - $fee_description");
            $success = "Additional fees added successfully! Billing updated.";
        } else {
            $error = "Error adding fees: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get billings
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

$sql = "SELECT b.*, c.account_number, c.subscriber_name, c.status as customer_status, a.area_name,
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
                <p>Generate and manage monthly billings with automatic balance carryover</p>
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
            
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <div class="widget mb-3">
                <div class="widget-header">
                    <h2>Generate Monthly Billing</h2>
                </div>
                <div class="widget-content">
                    <div class="alert alert-info mb-2">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                        </svg>
                        <strong>Automatic Billing Logic:</strong>
                        <ul style="margin-top: 10px; margin-left: 20px;">
                            <li><strong>Active customers:</strong> Bills generated with previous balance carryover</li>
                            <li><strong>Hold Disconnection:</strong> Bills generated with balance carryover (grace period)</li>
                            <li><strong>Disconnected:</strong> No new bills generated after disconnection date</li>
                            <li><strong>Due date:</strong> End of month (last day)</li>
                            <li><strong>Previous unpaid balances:</strong> Automatically added to current bill</li>
                        </ul>
                    </div>
                    
                    <form method="POST" action="" onsubmit="return confirm('Generate billing for all eligible customers? This will calculate previous balances automatically.');">
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
                            <th>Period</th>
                            <th>Prev Balance</th>
                            <th>Current Charges</th>
                            <th>Total Amount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <?php if ($_SESSION['role'] == 'admin'): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($billings->num_rows > 0): ?>
                            <?php while ($row = $billings->fetch_assoc()): 
                                $current_charges = $row['internet_fee'] + $row['cable_fee'] + $row['service_fee'] + $row['material_fee'];
                                $balance = $row['net_amount'] - $row['total_paid'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($row['subscriber_name']); ?>
                                    <?php if ($row['customer_status'] == 'hold_disconnection'): ?>
                                        <span class="badge badge-warning">Hold</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo get_month_name($row['billing_month']) . ' ' . $row['billing_year']; ?></td>
                                <td><?php echo format_currency($row['previous_balance']); ?></td>
                                <td><?php echo format_currency($current_charges); ?></td>
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
                                <?php if ($_SESSION['role'] == 'admin'): ?>
                                <td>
                                    <button onclick="openAddFeesModal(<?php echo $row['billing_id']; ?>, '<?php echo htmlspecialchars($row['subscriber_name']); ?>', '<?php echo get_month_name($row['billing_month']) . ' ' . $row['billing_year']; ?>')" class="btn btn-sm btn-secondary">Add Fees</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $_SESSION['role'] == 'admin' ? '10' : '9'; ?>" class="text-center">No billings found for <?php echo get_month_name($month_filter) . ' ' . $year_filter; ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <?php if ($_SESSION['role'] == 'admin'): ?>
    <!-- Add Fees Modal -->
    <div id="addFeesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Additional Fees</h2>
                <button type="button" class="modal-close" onclick="closeModal('addFeesModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_fees">
                    <input type="hidden" id="modal_billing_id" name="billing_id">
                    
                    <div class="alert alert-info">
                        <strong>Customer:</strong> <span id="modal_customer_name"></span><br>
                        <strong>Billing Period:</strong> <span id="modal_billing_period"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="additional_service">Additional Service Fee</label>
                        <input type="number" step="0.01" id="additional_service" name="additional_service" value="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label for="additional_material">Additional Material Fee</label>
                        <input type="number" step="0.01" id="additional_material" name="additional_material" value="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label for="fee_description">Description/Reason</label>
                        <textarea id="fee_description" name="fee_description" rows="3" placeholder="e.g., Router replacement, Repair service, etc."></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Note:</strong> Billing total will be automatically recalculated after adding fees.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addFeesModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Fees & Update Billing</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="js/script.js"></script>
    <script>
        function openAddFeesModal(billingId, customerName, billingPeriod) {
            document.getElementById('modal_billing_id').value = billingId;
            document.getElementById('modal_customer_name').textContent = customerName;
            document.getElementById('modal_billing_period').textContent = billingPeriod;
            document.getElementById('addFeesModal').classList.add('show');
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>