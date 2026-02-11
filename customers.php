<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();

// Handle customer addition (Admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add' && $_SESSION['role'] == 'admin') {
    $account_number = sanitize_input($_POST['account_number']);
    $subscriber_name = sanitize_input($_POST['subscriber_name']);
    $address = sanitize_input($_POST['address']);
    $area_id = intval($_POST['area_id']);
    $tel_no = sanitize_input($_POST['tel_no']);
    $package_id = intval($_POST['package_id']);
    $monthly_fee = floatval($_POST['monthly_fee']);
    $installation_date = sanitize_input($_POST['installation_date']);
    
    $stmt = $conn->prepare("INSERT INTO customers (account_number, subscriber_name, address, area_id, tel_no, package_id, monthly_fee, installation_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssisids", $account_number, $subscriber_name, $address, $area_id, $tel_no, $package_id, $monthly_fee, $installation_date);
    
    if ($stmt->execute()) {
        log_activity($_SESSION['user_id'], 'ADD_CUSTOMER', 'customers', $stmt->insert_id, "Added customer: $subscriber_name");
        $success = "Customer added successfully!";
    } else {
        $error = "Error adding customer: " . $conn->error;
    }
    $stmt->close();
}

// Get filters
$area_filter = isset($_GET['area']) ? intval($_GET['area']) : 0;
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query
$sql = "SELECT c.*, a.area_name, p.package_name 
        FROM customers c 
        LEFT JOIN areas a ON c.area_id = a.area_id 
        LEFT JOIN packages p ON c.package_id = p.package_id 
        WHERE 1=1";

if ($area_filter > 0) {
    $sql .= " AND c.area_id = $area_filter";
}

if ($status_filter) {
    $sql .= " AND c.status = '$status_filter'";
}

if ($search) {
    $sql .= " AND (c.subscriber_name LIKE '%$search%' OR c.account_number LIKE '%$search%' OR c.address LIKE '%$search%')";
}

$sql .= " ORDER BY c.subscriber_name ASC";

$result = $conn->query($sql);

// Get areas for filter
$areas = $conn->query("SELECT * FROM areas ORDER BY area_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - AR NOVALINK Billing System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Customer Management</h1>
                <p>Manage customer accounts and subscriptions</p>
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
            
            <div class="table-container">
                <div class="table-header">
                    <h2>All Customers</h2>
                    <div class="table-actions">
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                        <button onclick="openAddCustomerModal()" class="btn btn-primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Add Customer
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                    <form method="GET" action="" class="filter-group">
                        <input type="text" name="search" placeholder="Search customers..." value="<?php echo htmlspecialchars($search); ?>" class="search-box">
                        
                        <select name="area">
                            <option value="0">All Areas</option>
                            <?php while ($area = $areas->fetch_assoc()): ?>
                            <option value="<?php echo $area['area_id']; ?>" <?php echo $area_filter == $area['area_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($area['area_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="disconnected" <?php echo $status_filter == 'disconnected' ? 'selected' : ''; ?>>Disconnected</option>
                            <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                        
                        <button type="submit" class="btn btn-secondary">Filter</button>
                        <a href="customers.php" class="btn btn-secondary">Reset</a>
                    </form>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Account #</th>
                            <th>Subscriber Name</th>
                            <th>Address</th>
                            <th>Area</th>
                            <th>Package</th>
                            <th>Monthly Fee</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['subscriber_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['address']); ?></td>
                                <td><?php echo htmlspecialchars($row['area_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['package_name'] ?? 'N/A'); ?></td>
                                <td><?php echo format_currency($row['monthly_fee']); ?></td>
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
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No customers found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- Add Customer Modal -->
    <div id="addCustomerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Customer</h2>
                <button type="button" class="modal-close" onclick="closeAddCustomerModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="account_number">Account Number *</label>
                            <input type="text" id="account_number" name="account_number" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="subscriber_name">Subscriber Name *</label>
                            <input type="text" id="subscriber_name" name="subscriber_name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address *</label>
                        <input type="text" id="address" name="address" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="area_id">Area *</label>
                            <select id="area_id" name="area_id" required>
                                <option value="">Select Area</option>
                                <?php
                                $areas->data_seek(0);
                                while ($area = $areas->fetch_assoc()):
                                ?>
                                <option value="<?php echo $area['area_id']; ?>">
                                    <?php echo htmlspecialchars($area['area_name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="tel_no">Telephone Number</label>
                            <input type="text" id="tel_no" name="tel_no">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="package_id">Package *</label>
                            <select id="package_id" name="package_id" required onchange="updateMonthlyFee()">
                                <option value="">Select Package</option>
                                <?php
                                $packages = $conn->query("SELECT * FROM packages WHERE status = 'active' ORDER BY bandwidth_mbps");
                                while ($package = $packages->fetch_assoc()):
                                ?>
                                <option value="<?php echo $package['package_id']; ?>" data-fee="<?php echo $package['monthly_fee']; ?>">
                                    <?php echo htmlspecialchars($package['package_name']); ?> - <?php echo $package['bandwidth_mbps']; ?> Mbps
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="monthly_fee">Monthly Fee *</label>
                            <input type="number" step="0.01" id="monthly_fee" name="monthly_fee" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="installation_date">Installation Date *</label>
                        <input type="date" id="installation_date" name="installation_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddCustomerModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Customer</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="js/script.js"></script>
    <script>
        function openAddCustomerModal() {
            document.getElementById('addCustomerModal').classList.add('show');
        }
        
        function closeAddCustomerModal() {
            document.getElementById('addCustomerModal').classList.remove('show');
        }
        
        function updateMonthlyFee() {
            const packageSelect = document.getElementById('package_id');
            const selectedOption = packageSelect.options[packageSelect.selectedIndex];
            const fee = selectedOption.getAttribute('data-fee');
            if (fee) {
                document.getElementById('monthly_fee').value = fee;
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addCustomerModal');
            if (event.target == modal) {
                closeAddCustomerModal();
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>