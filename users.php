<?php
require_once 'config.php';
check_permission('admin');

$conn = getDBConnection();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = sanitize_input($_POST['username']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $full_name = sanitize_input($_POST['full_name']);
                $email = sanitize_input($_POST['email']);
                $role = sanitize_input($_POST['role']);
                
                $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $password, $full_name, $email, $role);
                
                if ($stmt->execute()) {
                    log_activity($_SESSION['user_id'], 'ADD_USER', 'users', $stmt->insert_id, "Added user: $username");
                    $success = "User added successfully!";
                } else {
                    $error = "Error adding user: " . $conn->error;
                }
                $stmt->close();
                break;
                
            case 'toggle_status':
                $user_id = intval($_POST['user_id']);
                $new_status = $_POST['status'] == 'active' ? 'inactive' : 'active';
                
                $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
                $stmt->bind_param("si", $new_status, $user_id);
                $stmt->execute();
                $stmt->close();
                
                log_activity($_SESSION['user_id'], 'UPDATE_USER_STATUS', 'users', $user_id, "Changed status to $new_status");
                $success = "User status updated!";
                break;
            
            case 'change_password':
                $user_id = intval($_POST['user_id']);
                $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $new_password, $user_id);
                
                if ($stmt->execute()) {
                    log_activity($_SESSION['user_id'], 'CHANGE_USER_PASSWORD', 'users', $user_id, "Changed user password");
                    $success = "Password updated successfully!";
                } else {
                    $error = "Error updating password: " . $conn->error;
                }
                $stmt->close();
                break;
            
            case 'delete':
                $user_id = intval($_POST['user_id']);
                
                // Prevent deleting yourself
                if ($user_id == $_SESSION['user_id']) {
                    $error = "You cannot delete your own account!";
                } else {
                    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    
                    if ($stmt->execute()) {
                        log_activity($_SESSION['user_id'], 'DELETE_USER', 'users', $user_id, "Deleted user");
                        $success = "User deleted successfully!";
                    } else {
                        $error = "Error deleting user: " . $conn->error;
                    }
                    $stmt->close();
                }
                break;
        }
    }
}

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - AR NOVALINK Billing System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>User Management</h1>
                <p>Manage system users and permissions</p>
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
                    <h2>System Users</h2>
                    <button onclick="openModal('addUserModal')" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add User
                    </button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['role'] == 'admin' ? 'danger' : 'info'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                            <td>
                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline-block; margin: 0;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <input type="hidden" name="status" value="<?php echo $user['status']; ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary">
                                        <?php echo $user['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <button onclick='openPasswordModal(<?php echo $user['user_id']; ?>, "<?php echo htmlspecialchars($user['username']); ?>")' class="btn btn-sm btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                    Change Password
                                </button>
                                <button onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="btn btn-sm btn-danger">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                    Delete
                                </button>
                                <?php else: ?>
                                <span class="badge badge-info">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content" style="position: relative; top: 50%; left: 50%; transform: translate(-50%, -50%); ">
            <div class="modal-header">
                <h2>Add New User</h2>
                <button type="button" class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="admin">Administrator</option>
                            <option value="accounting">Accounting</option>
                            <option value="cashier">Cashier</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content" style="max-width: 600px; position: relative; top: 50%; left: 50%; transform: translate(-50%, -50%); ">
            <div class="modal-header">
                <h2>Change User Password</h2>
                <button type="button" class="modal-close" onclick="closeModal('changePasswordModal')">&times;</button>
            </div>
            <form method="POST" action="" id="changePasswordForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" id="password_user_id" name="user_id">
                    
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" id="password_username" disabled style="background: #f5f5f5;">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password *</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="Enter new password">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password *</label>
                        <input type="password" id="confirm_password" required minlength="6" placeholder="Re-enter new password">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('changePasswordModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Form (hidden) -->
    <form id="deleteUserForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" id="delete_user_id" name="user_id">
    </form>
    
    <script src="js/script.js"></script>
    <script>
        // Validate password confirmation before submit
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Passwords do not match! Please try again.');
                document.getElementById('confirm_password').focus();
                return false;
            }
        });
        
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function openPasswordModal(userId, username) {
            document.getElementById('password_user_id').value = userId;
            document.getElementById('password_username').value = username;
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            document.getElementById('changePasswordModal').style.display = 'block';
        }
        
   
        function deleteUser(userId, username) {
            if (confirm(`Are you sure you want to DELETE user "${username}"?\n\nThis action cannot be undone!`)) {
                document.getElementById('delete_user_id').value = userId;
                document.getElementById('deleteUserForm').submit();
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>