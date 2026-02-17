<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();

// Handle customer actions (Admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_SESSION['role'] == 'admin') {
    
    if ($_POST['action'] == 'add') {
        // Auto-generate account number
        $result = $conn->query("SELECT account_number FROM customers ORDER BY customer_id DESC LIMIT 1");
        if ($result->num_rows > 0) {
            $last_account = $result->fetch_assoc()['account_number'];
            // Extract number from ACC-001 format
            $last_num = intval(substr($last_account, 4));
            $new_num = $last_num + 1;
            $account_number = 'ACC-' . str_pad($new_num, 3, '0', STR_PAD_LEFT);
        } else {
            $account_number = 'ACC-001';
        }
        
        $subscriber_name = sanitize_input($_POST['subscriber_name']);
        $address = sanitize_input($_POST['address']);
        $area_id = intval($_POST['area_id']);
        $tel_no = sanitize_input($_POST['tel_no']);
        $package_id = intval($_POST['package_id']);
        $monthly_fee = floatval($_POST['monthly_fee']);
        $installation_date = sanitize_input($_POST['installation_date']);
        $date_connected = $installation_date; // Same as installation date
        
        $stmt = $conn->prepare("INSERT INTO customers (account_number, subscriber_name, address, area_id, tel_no, package_id, monthly_fee, installation_date, date_connected) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssisidss", $account_number, $subscriber_name, $address, $area_id, $tel_no, $package_id, $monthly_fee, $installation_date, $date_connected);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'ADD_CUSTOMER', 'customers', $stmt->insert_id, "Added customer: $subscriber_name ($account_number)");
            $success = "Customer added successfully! Account Number: $account_number";
        } else {
            $error = "Error adding customer: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Disconnect customer
    elseif ($_POST['action'] == 'disconnect') {
        $customer_id = intval($_POST['customer_id']);
        $disconnection_date = date('Y-m-d');
        
        $stmt = $conn->prepare("UPDATE customers SET status = 'disconnected', disconnection_date = ? WHERE customer_id = ?");
        $stmt->bind_param("si", $disconnection_date, $customer_id);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'DISCONNECT_CUSTOMER', 'customers', $customer_id, "Disconnected customer");
            $success = "Customer disconnected successfully!";
        }
        $stmt->close();
    }
    
    // Reconnect customer
    elseif ($_POST['action'] == 'reconnect') {
        $customer_id = intval($_POST['customer_id']);
        
        $stmt = $conn->prepare("UPDATE customers SET status = 'active', disconnection_date = NULL WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'RECONNECT_CUSTOMER', 'customers', $customer_id, "Reconnected customer");
            $success = "Customer reconnected successfully!";
        }
        $stmt->close();
    }
    
    // Suspend customer
    elseif ($_POST['action'] == 'suspend') {
        $customer_id = intval($_POST['customer_id']);
        
        $stmt = $conn->prepare("UPDATE customers SET status = 'suspended' WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'SUSPEND_CUSTOMER', 'customers', $customer_id, "Suspended customer");
            $success = "Customer suspended successfully!";
        }
        $stmt->close();
    }
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
                    <div class="filter-group" style="gap: 10px;">
                        <div style="flex: 1; position: relative;">
                            <input type="text" id="live-search" placeholder="Start typing to search..." class="search-box" autocomplete="off" style="width: 100%; padding: 12px 16px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; color: #333; outline: none; transition: all 0.3s ease; box-sizing: border-box;" onfocus="this.style.borderColor='#0066cc'; this.style.boxShadow='0 0 0 3px rgba(76, 175, 80, 0.1)'" onblur="this.style.borderColor='#ddd'; this.style.boxShadow='none'">
                            <!-- <div style="margin-top: 8px; font-size: 12px; color: #666;">
                                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle;">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                                </svg>
                                <em>Results appear instantly as you type...</em>
                            </div> -->
                        </div>
                        
                        <select id="area-filter" style="min-width: 150px;">
                            <option value="0">All Areas</option>
                            <?php 
                            $areas->data_seek(0);
                            while ($area = $areas->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $area['area_id']; ?>">
                                <?php echo htmlspecialchars($area['area_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        
                        <select id="status-filter" style="min-width: 130px;">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="disconnected">Disconnected</option>
                            <option value="hold_disconnection">Hold</option>
                        </select>
                        
                        <button type="button" onclick="clearAllFilters()" class="btn btn-secondary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                            Clear
                        </button>
                    </div>
                </div>
                
                <div id="customer-table-container">
                    <!-- Results will be loaded here via AJAX -->
                </div>
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
                    
                    <div class="alert alert-info" style="margin-bottom: 20px;">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle;">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                        </svg>
                        <strong>Account Number will be auto-generated</strong> (e.g., ACC-007, ACC-008, etc.)
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subscriber_name">Subscriber Name *</label>
                            <input type="text" id="subscriber_name" name="subscriber_name" required placeholder="Enter full name">
                        </div>
                        
                        <div class="form-group">
                            <label for="tel_no">Telephone Number</label>
                            <input type="text" id="tel_no" name="tel_no" placeholder="09XX XXX XXXX">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address *</label>
                        <input type="text" id="address" name="address" required placeholder="Complete address">
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
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="monthly_fee">Monthly Fee *</label>
                            <input type="number" step="0.01" id="monthly_fee" name="monthly_fee" required readonly style="background: #f8f9fa;">
                            <small style="color: #666;">Auto-filled from selected package</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="installation_date">Installation Date *</label>
                            <input type="date" id="installation_date" name="installation_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
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
        // Live Search Functionality
        let searchTimeout;
        const searchInput = document.getElementById('live-search');
        const areaFilter = document.getElementById('area-filter');
        const statusFilter = document.getElementById('status-filter');
        const tableContainer = document.getElementById('customer-table-container');
        const isAdmin = <?php echo $_SESSION['role'] == 'admin' ? 'true' : 'false'; ?>;
        
        // Load all customers on page load
        loadCustomers();
        
        // Real-time search
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadCustomers();
            }, 300);
        });
        
        // Filter changes
        areaFilter.addEventListener('change', loadCustomers);
        statusFilter.addEventListener('change', loadCustomers);
        
        function loadCustomers() {
            const query = searchInput.value.trim();
            const area = areaFilter.value;
            const status = statusFilter.value;
            
            tableContainer.innerHTML = '<div style="padding: 40px; text-align: center; color: #666;">Loading...</div>';
            
            let url = `ajax/search_customers.php?q=${encodeURIComponent(query)}&area=${area}&status=${status}`;
            
            fetch(url)
                .then(response => response.json())
                .then(customers => {
                    displayCustomers(customers, query);
                })
                .catch(error => {
                    console.error('Error:', error);
                    tableContainer.innerHTML = '<div style="padding: 40px; text-align: center; color: red;">Error loading customers</div>';
                });
        }
        
        function displayCustomers(customers, query) {
            if (customers.length === 0) {
                tableContainer.innerHTML = `
                    <div style="padding: 40px; text-align: center;">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="opacity: 0.2; margin-bottom: 15px;">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        <p style="color: #999; font-size: 14px; margin: 0;">No customers found${query ? ' matching "' + query + '"' : ''}</p>
                    </div>
                `;
                return;
            }
            
            let html = `
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
            `;
            
            customers.forEach(customer => {
                const statusClass = customer.status === 'active' ? 'success' : 
                                  customer.status === 'disconnected' ? 'danger' : 
                                  customer.status === 'hold_disconnection' ? 'warning' : 'secondary';
                
                const highlightedName = highlightMatch(customer.subscriber_name, query);
                const highlightedAccount = highlightMatch(customer.account_number, query);
                const highlightedAddress = highlightMatch(customer.address, query);
                
                html += `
                    <tr>
                        <td>${highlightedAccount}</td>
                        <td><strong>${highlightedName}</strong></td>
                        <td>${highlightedAddress}</td>
                        <td>${customer.area_name || 'N/A'}</td>
                        <td>${customer.package_name || 'N/A'}</td>
                        <td>₱${parseFloat(customer.monthly_fee).toFixed(2)}</td>
                        <td><span class="badge badge-${statusClass}">${customer.status.charAt(0).toUpperCase() + customer.status.slice(1).replace('_', ' ')}</span></td>
                        <td>
                            <a href="customer_ledger.php?id=${customer.customer_id}" class="btn btn-sm btn-primary">View Ledger</a>
                `;
                
                if (isAdmin) {
                    if (customer.status === 'active') {
                        html += `
                            <button onclick="disconnectCustomer(${customer.customer_id})" class="btn btn-sm btn-danger">Disconnect</button>
                        `;
                    } else if (customer.status === 'disconnected' || customer.status === 'hold_disconnection') {
                        html += `
                            <button onclick="reconnectCustomer(${customer.customer_id})" class="btn btn-sm btn-success">Reconnect</button>
                        `;
                    }
                }
                
                html += `
                        </td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
                <style>
                    .highlight {
                        background: yellow;
                        font-weight: bold;
                        padding: 2px 4px;
                        border-radius: 2px;
                    }
                </style>
            `;
            
            tableContainer.innerHTML = html;
        }
        
        function highlightMatch(text, query) {
            if (!text || !query) return text;
            const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
            return text.replace(regex, '<span class="highlight">$1</span>');
        }
        
        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        
        function disconnectCustomer(customerId) {
            if (confirm('Disconnect this customer?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="disconnect">
                    <input type="hidden" name="customer_id" value="${customerId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function reconnectCustomer(customerId) {
            if (confirm('Reconnect this customer?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reconnect">
                    <input type="hidden" name="customer_id" value="${customerId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function clearAllFilters() {
            searchInput.value = '';
            areaFilter.value = '0';
            statusFilter.value = '';
            loadCustomers();
            searchInput.focus();
        }
        
        // Modal functions
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