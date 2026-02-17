<?php
require_once 'config.php';
check_permission('admin');

$conn = getDBConnection();

// Handle area actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] == 'add') {
        $area_name = sanitize_input($_POST['area_name']);
        $description = sanitize_input($_POST['description']);
        
        $stmt = $conn->prepare("INSERT INTO areas (area_name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $area_name, $description);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'ADD_AREA', 'areas', $stmt->insert_id, "Added area: $area_name");
            $success = "Area added successfully!";
        } else {
            $error = "Error adding area: " . $conn->error;
        }
        $stmt->close();
    }
    
    elseif ($_POST['action'] == 'edit') {
        $area_id = intval($_POST['area_id']);
        $area_name = sanitize_input($_POST['area_name']);
        $description = sanitize_input($_POST['description']);
        
        $stmt = $conn->prepare("UPDATE areas SET area_name = ?, description = ? WHERE area_id = ?");
        $stmt->bind_param("ssi", $area_name, $description, $area_id);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'EDIT_AREA', 'areas', $area_id, "Updated area: $area_name");
            $success = "Area updated successfully!";
        } else {
            $error = "Error updating area: " . $conn->error;
        }
        $stmt->close();
    }
    
    elseif ($_POST['action'] == 'delete') {
        $area_id = intval($_POST['area_id']);
        
        // Check if area is being used
        $check = $conn->query("SELECT COUNT(*) as count FROM customers WHERE area_id = $area_id");
        $count = $check->fetch_assoc()['count'];
        
        if ($count > 0) {
            $error = "Cannot delete area: $count customer(s) are assigned to this area!";
        } else {
            $stmt = $conn->prepare("DELETE FROM areas WHERE area_id = ?");
            $stmt->bind_param("i", $area_id);
            
            if ($stmt->execute()) {
                log_activity($_SESSION['user_id'], 'DELETE_AREA', 'areas', $area_id, "Deleted area");
                $success = "Area deleted successfully!";
            } else {
                $error = "Error deleting area: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Get all areas
$areas = $conn->query("SELECT a.*, 
    (SELECT COUNT(*) FROM customers WHERE area_id = a.area_id) as customer_count
    FROM areas a 
    ORDER BY a.area_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Areas - AR NOVALINK Billing System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Manage Service Areas / Barangays</h1>
                    <p>Add, edit, or remove service coverage areas</p>
                </div>
                <button onclick="openAddModal()" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="16"/>
                        <line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                    Add New Area
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
                    <h2>Service Areas (<?php echo $areas->num_rows; ?>)</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Area / Barangay Name</th>
                            <th>Description</th>
                            <th>Customers</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($areas->num_rows > 0): ?>
                            <?php while ($area = $areas->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $area['area_id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($area['area_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($area['description'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($area['customer_count'] > 0): ?>
                                        <span class="badge badge-info"><?php echo $area['customer_count']; ?> customers</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">0 customers</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($area['created_at'])); ?></td>
                                <td>
                                    <button onclick='openEditModal(<?php echo json_encode($area); ?>)' class="btn btn-sm btn-primary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                        Edit
                                    </button>
                                    <?php if ($area['customer_count'] == 0): ?>
                                    <button onclick="deleteArea(<?php echo $area['area_id']; ?>, '<?php echo htmlspecialchars($area['area_name']); ?>')" class="btn btn-sm btn-danger">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                        Delete
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled title="Cannot delete area with customers">
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
                                <td colspan="6" class="text-center">No areas found. Click "Add New Area" to create one.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- Add Area Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content" style="position: absolute; top: 50%;
        left: 50%; transform: translate(-50%, -50%);">
            <div class="modal-header">
                <h2>Add New Service Area</h2>
                <button type="button" class="modal-close" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label for="area_name">Area / Barangay Name *</label>
                        <input type="text" id="area_name" name="area_name" required placeholder="e.g., Barangay 1, Poblacion, San Jose">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" placeholder="Optional description or notes about this area"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeAddModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Area</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Area Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="position: relative; top: 50%; left: 50%; transform: translate(-50%, -50%);">
            <div class="modal-header">
                <h2>Edit Service Area</h2>
                <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="edit_area_id" name="area_id">
                    
                    <div class="form-group">
                        <label for="edit_area_name">Area / Barangay Name *</label>
                        <input type="text" id="edit_area_name" name="area_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Area</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Form (hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" id="delete_area_id" name="area_id">
    </form>
    
    <script src="js/script.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function openEditModal(area) {
            document.getElementById('edit_area_id').value = area.area_id;
            document.getElementById('edit_area_name').value = area.area_name;
            document.getElementById('edit_description').value = area.description || '';
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function deleteArea(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"?\n\nThis action cannot be undone.`)) {
                document.getElementById('delete_area_id').value = id;
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