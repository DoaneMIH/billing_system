<?php
require_once 'config.php';
check_permission('admin');

$conn = getDBConnection();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = "New passwords do not match!";
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (password_verify($current_password, $result['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
                $stmt->execute();
                $stmt->close();
                
                log_activity($_SESSION['user_id'], 'CHANGE_PASSWORD', 'users', $_SESSION['user_id'], 'Changed password');
                $success = "Password changed successfully!";
            } else {
                $error = "Current password is incorrect!";
            }
        }
    }
}

// Get areas and packages
$areas = $conn->query("SELECT * FROM areas ORDER BY area_name");
$packages = $conn->query("SELECT * FROM packages ORDER BY bandwidth_mbps");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - AR NOVALINK Billing System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>System Settings</h1>
                <p>Configure system preferences</p>
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
            
            <div class="dashboard-widgets">
                <div class="widget">
                    <div class="widget-header">
                        <h2>Change Password</h2>
                    </div>
                    <div class="widget-content">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required minlength="6">
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </form>
                    </div>
                </div>
                
                <div class="widget">
                    <div class="widget-header">
                        <h2>System Information</h2>
                    </div>
                    <div class="widget-content">
                        <div class="form-group">
                            <strong>Application Name:</strong> AR NOVALINK Billing System
                        </div>
                        <div class="form-group">
                            <strong>Version:</strong> 1.0.0
                        </div>
                        <div class="form-group">
                            <strong>Database:</strong> ar_novalink_billing
                        </div>
                        <div class="form-group">
                            <strong>PHP Version:</strong> <?php echo phpversion(); ?>
                        </div>
                        <div class="form-group">
                            <strong>Server Time:</strong> <?php echo date('F d, Y h:i:s A'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="widget mt-3">
                <div class="widget-header">
                    <h2>Service Areas (<?php echo $areas->num_rows; ?>)</h2>
                </div>
                <div class="widget-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Area Name</th>
                                <th>Description</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($area = $areas->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($area['area_name']); ?></td>
                                <td><?php echo htmlspecialchars($area['description'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($area['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="widget mt-3">
                <div class="widget-header">
                    <h2>Internet Packages (<?php echo $packages->num_rows; ?>)</h2>
                </div>
                <div class="widget-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Package Name</th>
                                <th>Bandwidth</th>
                                <th>Monthly Fee</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($package = $packages->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($package['package_name']); ?></td>
                                <td><?php echo $package['bandwidth_mbps']; ?> Mbps</td>
                                <td><?php echo format_currency($package['monthly_fee']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $package['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($package['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>