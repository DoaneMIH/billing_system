<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$conn = getDBConnection();

// Handle subscriber actions (Admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_SESSION['role'] == 'admin') {
    
    if ($_POST['action'] == 'add') {
        $result = $conn->query("SELECT account_number FROM customers ORDER BY customer_id DESC LIMIT 1");
        if ($result->num_rows > 0) {
            $last_num = intval(substr($result->fetch_assoc()['account_number'], 4));
            $account_number = 'ACC-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
        } else { $account_number = 'ACC-001'; }
        
        $subscriber_name = sanitize_input($_POST['subscriber_name']);
        $address = sanitize_input($_POST['address']);
        $area_id = intval($_POST['area_id']);
        $tel_no = sanitize_input($_POST['tel_no']);
        $package_id = intval($_POST['package_id']);
        $monthly_fee = floatval($_POST['monthly_fee']);
        $installation_date = sanitize_input($_POST['installation_date']);
        $init_status = sanitize_input($_POST['initial_status'] ?? 'active');
        
        $stmt = $conn->prepare("INSERT INTO customers (account_number, subscriber_name, address, area_id, tel_no, package_id, monthly_fee, installation_date, date_connected, status) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sssisidsss", $account_number, $subscriber_name, $address, $area_id, $tel_no, $package_id, $monthly_fee, $installation_date, $installation_date, $init_status);
        
        if ($stmt->execute()) {
            $new_cid = $stmt->insert_id;
            $now_date = date('Y-m-d');
            $now_time = date('H:i:s');
            $uid = $_SESSION['user_id'];
            $conn->query("INSERT INTO customer_status_log (customer_id, old_status, new_status, changed_by, change_date, change_time, remarks) VALUES ($new_cid, '', '$init_status', $uid, '$now_date', '$now_time', 'Initial subscriber creation')");
            log_activity($uid, 'ADD_CUSTOMER', 'customers', $new_cid, "Added subscriber: $subscriber_name ($account_number)");
            $success = "Subscriber added! Account: $account_number";
        } else { $error = "Error: " . $conn->error; }
        $stmt->close();
    }
    
    elseif ($_POST['action'] == 'disconnect') {
        $customer_id = intval($_POST['customer_id']);
        $old = $conn->query("SELECT status, subscriber_name FROM customers WHERE customer_id=$customer_id")->fetch_assoc();
        $now_date = date('Y-m-d');
        $now_time = date('H:i:s');
        $uid = $_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE customers SET status='disconnected', disconnection_date=? WHERE customer_id=?");
        $stmt->bind_param("si", $now_date, $customer_id);
        if ($stmt->execute()) {
            $conn->query("INSERT INTO customer_status_log (customer_id, old_status, new_status, changed_by, change_date, change_time, remarks) VALUES ($customer_id, '{$old['status']}', 'disconnected', $uid, '$now_date', '$now_time', 'Subscriber disconnected by " . $conn->real_escape_string($_SESSION['full_name']) . "')");
            log_activity($uid, 'DISCONNECT_CUSTOMER', 'customers', $customer_id, "Disconnected: {$old['subscriber_name']} on $now_date at $now_time");
            $success = "Subscriber disconnected on " . date('M d, Y h:i A') . "!";
        }
        $stmt->close();
    }
    
    elseif ($_POST['action'] == 'reconnect') {
        $customer_id = intval($_POST['customer_id']);
        $old = $conn->query("SELECT status, subscriber_name FROM customers WHERE customer_id=$customer_id")->fetch_assoc();
        $now_date = date('Y-m-d');
        $now_time = date('H:i:s');
        $uid = $_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE customers SET status='active', disconnection_date=NULL WHERE customer_id=?");
        $stmt->bind_param("i", $customer_id);
        if ($stmt->execute()) {
            $conn->query("INSERT INTO customer_status_log (customer_id, old_status, new_status, changed_by, change_date, change_time, remarks) VALUES ($customer_id, '{$old['status']}', 'active', $uid, '$now_date', '$now_time', 'Subscriber reconnected by " . $conn->real_escape_string($_SESSION['full_name']) . "')");
            log_activity($uid, 'RECONNECT_CUSTOMER', 'customers', $customer_id, "Reconnected: {$old['subscriber_name']} on $now_date at $now_time");
            $success = "Subscriber reconnected on " . date('M d, Y h:i A') . "!";
        }
        $stmt->close();
    }
    
    // NEW: Confirm Done Installation
    elseif ($_POST['action'] == 'confirm_installation') {
        $customer_id = intval($_POST['customer_id']);
        $old = $conn->query("SELECT status, subscriber_name FROM customers WHERE customer_id=$customer_id")->fetch_assoc();
        $now_date = date('Y-m-d');
        $now_time = date('H:i:s');
        $uid = $_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE customers SET status='active', date_connected=?, installation_date=? WHERE customer_id=?");
        $stmt->bind_param("ssi", $now_date, $now_date, $customer_id);
        if ($stmt->execute()) {
            $conn->query("INSERT INTO customer_status_log (customer_id, old_status, new_status, changed_by, change_date, change_time, remarks) VALUES ($customer_id, '{$old['status']}', 'active', $uid, '$now_date', '$now_time', 'Installation confirmed done by " . $conn->real_escape_string($_SESSION['full_name']) . "')");
            log_activity($uid, 'CONFIRM_INSTALLATION', 'customers', $customer_id, "Installation confirmed for: {$old['subscriber_name']} on $now_date at $now_time");
            $success = "Installation confirmed! Subscriber is now Active.";
        }
        $stmt->close();
    }
    
    elseif ($_POST['action'] == 'upload_sketch') {
        $customer_id = intval($_POST['customer_id']);
        $remarks = sanitize_input($_POST['sketch_remarks'] ?? '');
        
        if (isset($_POST['sketch_data']) && !empty($_POST['sketch_data'])) {
            $sketch_data = $_POST['sketch_data'];
            $stmt = $conn->prepare("INSERT INTO installation_sketches (customer_id, sketch_type, sketch_data, remarks, created_by) VALUES (?, 'drawing', ?, ?, ?)");
            $stmt->bind_param("issi", $customer_id, $sketch_data, $remarks, $_SESSION['user_id']);
            if ($stmt->execute()) { $success = "Sketch saved!"; }
            $stmt->close();
        }
        elseif (isset($_FILES['sketch_file']) && $_FILES['sketch_file']['error'] == 0) {
            $upload_dir = 'uploads/sketches/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = pathinfo($_FILES['sketch_file']['name'], PATHINFO_EXTENSION);
            $filename = 'sketch_' . $customer_id . '_' . time() . '.' . $ext;
            $filepath = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['sketch_file']['tmp_name'], $filepath)) {
                $stmt = $conn->prepare("INSERT INTO installation_sketches (customer_id, sketch_type, file_path, remarks, created_by) VALUES (?, 'upload', ?, ?, ?)");
                $stmt->bind_param("issi", $customer_id, $filepath, $remarks, $_SESSION['user_id']);
                if ($stmt->execute()) { $success = "Sketch uploaded!"; }
                $stmt->close();
            }
        }
    }
}

// Build query
$area_filter = isset($_GET['area']) ? intval($_GET['area']) : 0;
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

$sql = "SELECT c.*, a.area_name, p.package_name FROM customers c LEFT JOIN areas a ON c.area_id = a.area_id LEFT JOIN packages p ON c.package_id = p.package_id WHERE 1=1";
if ($area_filter > 0) $sql .= " AND c.area_id = $area_filter";
if ($status_filter) $sql .= " AND c.status = '" . $conn->real_escape_string($status_filter) . "'";
if ($search) { $s = $conn->real_escape_string($search); $sql .= " AND (c.subscriber_name LIKE '%$s%' OR c.account_number LIKE '%$s%' OR c.address LIKE '%$s%')"; }
$sql .= " ORDER BY c.subscriber_name ASC";
$result = $conn->query($sql);

$areas = $conn->query("SELECT * FROM areas ORDER BY area_name");
$packages_list = $conn->query("SELECT * FROM packages WHERE status='active' ORDER BY package_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribers - AR NOVALINK</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header">
                <div>
                <h1>Subscriber Management</h1>
                <p>Manage subscriber accounts and subscriptions</p>
                </div>
            </div>
            
            <?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <?php if (isset($error)): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            
            <div class="table-container">
                <div class="table-header">
                    <h2>All Subscribers</h2>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                    <button onclick="document.getElementById('addCustomerModal').classList.add('show')" class="btn btn-primary">+ Add Subscriber</button>
                    <?php endif; ?>
                </div>
                
                <div class="table-filter-bar">
                    <div class="filter-group">
                        <input type="text" id="live-search" placeholder="Search subscribers..." autocomplete="off" class="customer-search-input">
                        <select id="area-filter">
                            <option value="0">All Areas</option>
                            <?php $areas->data_seek(0); while ($a = $areas->fetch_assoc()): ?>
                            <option value="<?php echo $a['area_id']; ?>"><?php echo htmlspecialchars($a['area_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <select id="status-filter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="disconnected">Disconnected</option>
                            <option value="reconnected">Reconnected</option>
                            <option value="pending_installation">Pending Installation</option>
                            <option value="hold_disconnection">Hold Disconnection</option>
                        </select>
                        <button onclick="clearAllFilters()" class="btn btn-secondary btn-sm">Clear</button>
                    </div>
                </div>
                
                <div id="customer-table-container">
                    <table>
                        <thead><tr><th>Account #</th><th>Subscriber</th><th>Address</th><th>Area</th><th>Package</th><th>Monthly Fee</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): while ($c = $result->fetch_assoc()):
                                $sc = match($c['status']) { 'active'=>'success', 'disconnected'=>'danger', 'reconnected'=>'info', 'pending_installation'=>'warning', default=>'secondary' };
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['account_number']); ?></td>
                                <td><strong><?php echo htmlspecialchars($c['subscriber_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['address']); ?></td>
                                <td><?php echo htmlspecialchars($c['area_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($c['package_name'] ?? 'N/A'); ?></td>
                                <td><?php echo format_currency($c['monthly_fee']); ?></td>
                                <td><span class="badge badge-<?php echo $sc; ?>"><?php echo ucfirst(str_replace('_',' ',$c['status'])); ?></span></td>
                                <td class="col-nowrap">
                                    <a href="customer_ledger.php?id=<?php echo $c['customer_id']; ?>" class="btn btn-sm btn-primary">Ledger</a>
                                    <a href="print_installation.php?id=<?php echo $c['customer_id']; ?>" target="_blank" class="btn btn-sm btn-secondary">Install Form</a>
                                    <?php if ($_SESSION['role'] == 'admin'): ?>
                                        <?php if ($c['status'] == 'pending_installation'): ?>
                                        <button onclick="statusAction(<?php echo $c['customer_id']; ?>,'confirm_installation')" class="btn btn-sm btn-success" title="Confirm Done Installation">✅ Done Install</button>
                                        <?php endif; ?>
                                        <?php if ($c['status'] == 'active' || $c['status'] == 'reconnected'): ?>
                                        <button onclick="statusAction(<?php echo $c['customer_id']; ?>,'disconnect')" class="btn btn-sm btn-danger">Disconnect</button>
                                        <?php elseif ($c['status'] == 'disconnected' || $c['status'] == 'hold_disconnection'): ?>
                                        <button onclick="statusAction(<?php echo $c['customer_id']; ?>,'reconnect')" class="btn btn-sm btn-success">Reconnect</button>
                                        <?php endif; ?>
                                        <button onclick="openSketchModal(<?php echo $c['customer_id']; ?>)" class="btn btn-sm btn-secondary">Sketch</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="8" class="text-center">No subscribers found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add Subscriber Modal -->
    <div id="addCustomerModal" class="modal">
        <div class="modal-content modal-content-lg">
            <div class="modal-header"><h2>Add New Subscriber</h2><button class="modal-close" onclick="this.closest('.modal').classList.remove('show')">&times;</button></div>
            <form method="POST"><div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group"><label>Subscriber Name *</label><input type="text" name="subscriber_name" required placeholder="LAST NAME, FIRST NAME"></div>
                <div class="form-group"><label>Address *</label><textarea name="address" required rows="2"></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label>Area *</label>
                        <select name="area_id" required><option value="">Select Area</option>
                            <?php $areas->data_seek(0); while ($a = $areas->fetch_assoc()): ?>
                            <option value="<?php echo $a['area_id']; ?>"><?php echo htmlspecialchars($a['area_name']); ?></option>
                            <?php endwhile; ?>
                        </select></div>
                    <div class="form-group"><label>Contact #</label><input type="text" name="tel_no"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Package *</label>
                        <select name="package_id" id="package_id" required onchange="updateMonthlyFee()"><option value="">Select Package</option>
                            <?php while ($pk = $packages_list->fetch_assoc()): ?>
                            <option value="<?php echo $pk['package_id']; ?>" data-fee="<?php echo $pk['monthly_fee']; ?>"><?php echo htmlspecialchars($pk['package_name']); ?> - <?php echo $pk['bandwidth_mbps']; ?> Mbps</option>
                            <?php endwhile; ?>
                        </select></div>
                    <div class="form-group"><label>Monthly Fee (₱) *</label><input type="number" step="0.01" id="monthly_fee" name="monthly_fee" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Installation Date *</label><input type="date" name="installation_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="form-group"><label>Initial Status</label>
                        <select name="initial_status">
                            <option value="active">Active</option>
                            <option value="pending_installation">Pending Installation</option>
                        </select></div>
                </div>
            </div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="this.closest('.modal').classList.remove('show')">Cancel</button><button type="submit" class="btn btn-primary">Add Subscriber</button></div></form>
        </div>
    </div>
    
    <!-- Sketch Upload Modal -->
    <div id="sketchModal" class="modal">
        <div class="modal-content modal-content-md">
            <div class="modal-header"><h2>Installation Sketch / Photo</h2><button class="modal-close" onclick="this.closest('.modal').classList.remove('show')">&times;</button></div>
            <form method="POST" enctype="multipart/form-data"><div class="modal-body">
                <input type="hidden" name="action" value="upload_sketch">
                <input type="hidden" id="sketch_customer_id" name="customer_id">
                <div class="form-group"><label>Upload Photo/Sketch</label><input type="file" name="sketch_file" accept="image/*"></div>
                <div class="form-group">
                    <label>Or Draw Sketch Below:</label>
                    <canvas id="sketchCanvas" width="500" height="300" ></canvas>
                    <input type="hidden" id="sketch_data" name="sketch_data">
                    <div class="mt-1">
                        <button type="button" onclick="clearCanvas()" class="btn btn-sm btn-secondary">Clear Drawing</button>
                        <button type="button" onclick="saveCanvas()" class="btn btn-sm btn-primary">Save Drawing</button>
                    </div>
                </div>
                <div class="form-group"><label>Remarks</label><textarea name="sketch_remarks" rows="2" placeholder="Describe installation path..."></textarea></div>
            </div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="this.closest('.modal').classList.remove('show')">Cancel</button><button type="submit" class="btn btn-primary">Upload Sketch</button></div></form>
        </div>
    </div>
    
    <script src="js/script.js"></script>
    <script>
        const isAdmin = <?php echo $_SESSION['role'] == 'admin' ? 'true' : 'false'; ?>;
        const searchInput = document.getElementById('live-search');
        const areaFilter = document.getElementById('area-filter');
        const statusFilter = document.getElementById('status-filter');
        const tableContainer = document.getElementById('customer-table-container');
        
        let debounceTimer;
        searchInput.addEventListener('input', () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(loadCustomers, 300); });
        areaFilter.addEventListener('change', loadCustomers);
        statusFilter.addEventListener('change', loadCustomers);
        
        function loadCustomers() {
            const q = searchInput.value.trim();
            fetch(`ajax/search_customers.php?q=${encodeURIComponent(q)}&area=${areaFilter.value}&status=${statusFilter.value}`)
                .then(r => r.json())
                .then(customers => displayCustomers(customers, q))
                .catch(() => { tableContainer.innerHTML = '<div class="text-center" style="padding:30px;color:red;">Error loading</div>'; });
        }
        
        function displayCustomers(customers, query) {
            if (customers.length === 0) { tableContainer.innerHTML = '<div class="text-center no-activity">No subscribers found</div>'; return; }
            let html = '<table><thead><tr><th>Account #</th><th>Subscriber</th><th>Address</th><th>Area</th><th>Package</th><th>Monthly Fee</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
            customers.forEach(c => {
                const sc = c.status==='active'?'success':c.status==='disconnected'?'danger':c.status==='reconnected'?'info':c.status==='pending_installation'?'warning':'secondary';
                html += `<tr><td>${hl(c.account_number,query)}</td><td><strong>${hl(c.subscriber_name,query)}</strong></td><td>${hl(c.address,query)}</td><td>${c.area_name||'N/A'}</td><td>${c.package_name||'N/A'}</td><td>₱${parseFloat(c.monthly_fee).toFixed(2)}</td><td><span class="badge badge-${sc}">${c.status.replace(/_/g,' ')}</span></td><td class="col-nowrap">
                    <a href="customer_ledger.php?id=${c.customer_id}" class="btn btn-sm btn-primary">Ledger</a>
                    <a href="print_installation.php?id=${c.customer_id}" target="_blank" class="btn btn-sm btn-secondary">Install Form</a>`;
                if (isAdmin) {
                    if (c.status==='pending_installation') html += `<button onclick="statusAction(${c.customer_id},'confirm_installation')" class="btn btn-sm btn-success" title="Confirm Done Installation">✅ Done Install</button>`;
                    if (c.status==='active'||c.status==='reconnected') html += `<button onclick="statusAction(${c.customer_id},'disconnect')" class="btn btn-sm btn-danger">Disconnect</button>`;
                    else if (c.status==='disconnected'||c.status==='hold_disconnection') html += `<button onclick="statusAction(${c.customer_id},'reconnect')" class="btn btn-sm btn-success">Reconnect</button>`;
                }
                html += '</td></tr>';
            });
            html += '</tbody></table>';
            tableContainer.innerHTML = html;
        }
        
        function hl(text, q) { if (!text||!q) return text||''; return text.replace(new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')})`,'gi'),'<mark>$1</mark>'); }
        
        function statusAction(id, action) {
            let msg = action === 'confirm_installation' ? 'Confirm this installation is DONE? Subscriber will become Active.' :
                      action === 'disconnect' ? 'Disconnect this subscriber?' : 'Reconnect this subscriber?';
            if (confirm(msg)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="${action}"><input type="hidden" name="customer_id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function clearAllFilters() { searchInput.value=''; areaFilter.value='0'; statusFilter.value=''; loadCustomers(); }
        function updateMonthlyFee() {
            const sel = document.getElementById('package_id');
            const fee = sel.options[sel.selectedIndex].getAttribute('data-fee');
            if (fee) document.getElementById('monthly_fee').value = fee;
        }
        
        function openSketchModal(cid) {
            document.getElementById('sketch_customer_id').value = cid;
            document.getElementById('sketchModal').classList.add('show');
            initCanvas();
        }
        
        let canvas, ctx, drawing = false;
        function initCanvas() {
            canvas = document.getElementById('sketchCanvas');
            ctx = canvas.getContext('2d');
            ctx.strokeStyle = '#000'; ctx.lineWidth = 2;
            canvas.onmousedown = (e) => { drawing=true; ctx.beginPath(); ctx.moveTo(e.offsetX,e.offsetY); };
            canvas.onmousemove = (e) => { if(drawing) { ctx.lineTo(e.offsetX,e.offsetY); ctx.stroke(); } };
            canvas.onmouseup = () => drawing=false;
            canvas.onmouseleave = () => drawing=false;
        }
        function clearCanvas() { if(ctx) ctx.clearRect(0,0,canvas.width,canvas.height); }
        function saveCanvas() { if(canvas) document.getElementById('sketch_data').value = canvas.toDataURL(); alert('Drawing saved! Click Upload to submit.'); }
    </script>
</body>
</html>
<?php $conn->close(); ?>
