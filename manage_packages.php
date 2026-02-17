<?php
require_once 'config.php';
check_permission('admin');

$conn = getDBConnection();

// Handle package actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] == 'add') {
        $package_name = sanitize_input($_POST['package_name']);
        $bandwidth_mbps = intval($_POST['bandwidth_mbps']);
        $monthly_fee = floatval($_POST['monthly_fee']);
        $description = sanitize_input($_POST['description']);
        
        $stmt = $conn->prepare("INSERT INTO packages (package_name, bandwidth_mbps, monthly_fee, description, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("sids", $package_name, $bandwidth_mbps, $monthly_fee, $description);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'ADD_PACKAGE', 'packages', $stmt->insert_id, "Added package: $package_name");
            $success = "Package added successfully!";
        } else {
            $error = "Error adding package: " . $conn->error;
        }
        $stmt->close();
    }
    
    elseif ($_POST['action'] == 'edit') {
        $package_id = intval($_POST['package_id']);
        $package_name = sanitize_input($_POST['package_name']);
        $bandwidth_mbps = intval($_POST['bandwidth_mbps']);
        $monthly_fee = floatval($_POST['monthly_fee']);
        $description = sanitize_input($_POST['description']);
        $status = sanitize_input($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE packages SET package_name = ?, bandwidth_mbps = ?, monthly_fee = ?, description = ?, status = ? WHERE package_id = ?");
        $stmt->bind_param("sidssi", $package_name, $bandwidth_mbps, $monthly_fee, $description, $status, $package_id);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'EDIT_PACKAGE', 'packages', $package_id, "Updated package: $package_name");
            $success = "Package updated successfully!";
        } else {
            $error = "Error updating package: " . $conn->error;
        }
        $stmt->close();
    }
    
    elseif ($_POST['action'] == 'toggle_status') {
        $package_id = intval($_POST['package_id']);
        $new_status = $_POST['new_status'];
        
        $stmt = $conn->prepare("UPDATE packages SET status = ? WHERE package_id = ?");
        $stmt->bind_param("si", $new_status, $package_id);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'TOGGLE_PACKAGE_STATUS', 'packages', $package_id, "Changed status to: $new_status");
            $success = "Package status updated successfully!";
        } else {
            $error = "Error updating status: " . $conn->error;
        }
        $stmt->close();
    }
    
    elseif ($_POST['action'] == 'delete') {
        $package_id = intval($_POST['package_id']);
        
        // Check if package is being used
        $check = $conn->query("SELECT COUNT(*) as count FROM customers WHERE package_id = $package_id");
        $count = $check->fetch_assoc()['count'];
        
        if ($count > 0) {
            $error = "Cannot delete package: $count customer(s) are using this package!";
        } else {
            $stmt = $conn->prepare("DELETE FROM packages WHERE package_id = ?");
            $stmt->bind_param("i", $package_id);
            
            if ($stmt->execute()) {
                log_activity($_SESSION['user_id'], 'DELETE_PACKAGE', 'packages', $package_id, "Deleted package");
                $success = "Package deleted successfully!";
            } else {
                $error = "Error deleting package: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Get all packages
$packages = $conn->query("SELECT p.*, 
    (SELECT COUNT(*) FROM customers WHERE package_id = p.package_id) as customer_count
    FROM packages p 
    ORDER BY p.bandwidth_mbps");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Packages - AR NOVALINK Billing System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Manage Internet Packages</h1>
                    <p>Add, edit, or manage subscription packages and pricing</p>
                </div>
                <button onclick="openAddModal()" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="16"/>
                        <line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                    Add New Package
                </button>
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
                    <h2>Internet Packages (<?php echo $packages->num_rows; ?>)</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Package Name</th>
                            <th>Speed</th>
                            <th>Monthly Fee</th>
                            <th>Description</th>
                            <th>Customers</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($packages->num_rows > 0): ?>
                            <?php while ($package = $packages->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $package['package_id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($package['package_name']); ?></strong></td>
                                <td><span class="badge badge-info"><?php echo $package['bandwidth_mbps']; ?> Mbps</span></td>
                                <td><strong><?php echo format_currency($package['monthly_fee']); ?></strong></td>
                                <td><?php echo htmlspecialchars($package['description'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($package['customer_count'] > 0): ?>
                                        <span class="badge badge-primary"><?php echo $package['customer_count']; ?> customers</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">0 customers</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $package['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($package['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button onclick='openEditModal(<?php echo json_encode($package); ?>)' class="btn btn-sm btn-primary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                        Edit
                                    </button>
                                    <?php if ($package['status'] == 'active'): ?>
                                    <button onclick="toggleStatus(<?php echo $package['package_id']; ?>, 'inactive', '<?php echo htmlspecialchars($package['package_name']); ?>')" class="btn btn-sm btn-warning">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <line x1="15" y1="9" x2="9" y2="15"/>
                                            <line x1="9" y1="9" x2="15" y2="15"/>
                                        </svg>
                                        Deactivate
                                    </button>
                                    <?php else: ?>
                                    <button onclick="toggleStatus(<?php echo $package['package_id']; ?>, 'active', '<?php echo htmlspecialchars($package['package_name']); ?>')" class="btn btn-sm btn-success">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                        Activate
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($package['customer_count'] == 0): ?>
                                    <button onclick="deletePackage(<?php echo $package['package_id']; ?>, '<?php echo htmlspecialchars($package['package_name']); ?>')" class="btn btn-sm btn-danger">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                        Delete
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled title="Cannot delete package with customers">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                        </svg>
                                        Locked
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No packages found. Click "Add New Package" to create one.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- Add Package Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content" style="max-width: 600px; position: absolute; top: 50%;
        left: 50%; transform: translate(-50%, -50%);">
            <div class="modal-header">
                <h2>Add New Internet Package</h2>
                <button type="button" class="modal-close" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label for="package_name">Package Name *</label>
                        <input type="text" id="package_name" name="package_name" required placeholder="e.g., Basic Plan, Premium, Ultra Fast">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bandwidth_mbps">Speed (Mbps) *</label>
                            <input type="number" id="bandwidth_mbps" name="bandwidth_mbps" required min="1" placeholder="e.g., 25, 50, 100">
                        </div>
                        
                        <div class="form-group">
                            <label for="monthly_fee">Monthly Fee (₱) *</label>
                            <input type="number" step="0.01" id="monthly_fee" name="monthly_fee" required min="0" placeholder="e.g., 599.00">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" placeholder="Optional description, features, or target users"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeAddModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Package</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Package Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 600px; position: relative; top: 50%; left: 50%; transform: translate(-50%, -50%); ">
            <div class="modal-header">
                <h2>Edit Internet Package</h2>
                <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="edit_package_id" name="package_id">
                    
                    <div class="form-group">
                        <label for="edit_package_name">Package Name *</label>
                        <input type="text" id="edit_package_name" name="package_name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_bandwidth_mbps">Speed (Mbps) *</label>
                            <input type="number" id="edit_bandwidth_mbps" name="bandwidth_mbps" required min="1">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_monthly_fee">Monthly Fee (₱) *</label>
                            <input type="number" step="0.01" id="edit_monthly_fee" name="monthly_fee" required min="0">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status">Status *</label>
                        <select id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Package</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Toggle Status Form (hidden) -->
    <form id="toggleForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" id="toggle_package_id" name="package_id">
        <input type="hidden" id="toggle_new_status" name="new_status">
    </form>
    
    <!-- Delete Form (hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" id="delete_package_id" name="package_id">
    </form>
    
    <script src="js/script.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function openEditModal(pkg) {
            document.getElementById('edit_package_id').value = pkg.package_id;
            document.getElementById('edit_package_name').value = pkg.package_name;
            document.getElementById('edit_bandwidth_mbps').value = pkg.bandwidth_mbps;
            document.getElementById('edit_monthly_fee').value = pkg.monthly_fee;
            document.getElementById('edit_description').value = pkg.description || '';
            document.getElementById('edit_status').value = pkg.status;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function toggleStatus(id, newStatus, name) {
            const action = newStatus === 'active' ? 'activate' : 'deactivate';
            if (confirm(`Are you sure you want to ${action} "${name}"?`)) {
                document.getElementById('toggle_package_id').value = id;
                document.getElementById('toggle_new_status').value = newStatus;
                document.getElementById('toggleForm').submit();
            }
        }
        
        function deletePackage(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"?\n\nThis action cannot be undone.`)) {
                document.getElementById('delete_package_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>