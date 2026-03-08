<?php
require_once 'config.php';
check_permission('admin');
$conn = getDBConnection();

// Ensure system_settings table exists
$conn->query("CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Insert defaults if missing
$conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES 
('billing_reminder', '1. Please disregard this bill if already paid.\n2. If you wish to clarify any item on this bill please come to our office.\n3. Due date every End of the Month with 7 days grace period.\n4. If payment is not made after a span of 7 days, automatically\nTEMPORARY DISCONNECTION.'),
('company_tagline', 'Thank you for keeping your account current. We value your continued patronage.')");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) { $error = "Passwords do not match!"; }
        else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (password_verify($current_password, $result['password'])) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $hashed, $_SESSION['user_id']);
                $stmt->execute();
                $stmt->close();
                log_activity($_SESSION['user_id'], 'CHANGE_PASSWORD', 'users', $_SESSION['user_id'], 'Changed password');
                $success = "Password changed!";
            } else { $error = "Current password is incorrect!"; }
        }
    }
    
    if ($_POST['action'] == 'update_reminder') {
        $reminder = $_POST['billing_reminder'];
        $tagline = sanitize_input($_POST['company_tagline']);
        
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'billing_reminder'");
        $stmt->bind_param("s", $reminder);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'company_tagline'");
        $stmt->bind_param("s", $tagline);
        $stmt->execute();
        $stmt->close();
        
        log_activity($_SESSION['user_id'], 'UPDATE_SETTINGS', 'system_settings', null, 'Updated billing reminder text');
        $success = "Billing reminder updated!";
    }
}

// Get current settings
$reminder = '';
$tagline = '';
$r = $conn->query("SELECT * FROM system_settings");
while ($s = $r->fetch_assoc()) {
    if ($s['setting_key'] == 'billing_reminder') $reminder = $s['setting_value'];
    if ($s['setting_key'] == 'company_tagline') $tagline = $s['setting_value'];
}

$areas = $conn->query("SELECT * FROM areas ORDER BY area_name");
$packages = $conn->query("SELECT * FROM packages ORDER BY bandwidth_mbps");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - AR NOVALINK</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header"><h1>System Settings</h1><p>Configure system preferences</p></div>
            
            <?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <?php if (isset($error)): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            
            <div class="dashboard-widgets">
                <!-- Billing Reminder Editor -->
                <div class="widget">
                    <div class="widget-header"><h2>📋 Billing Statement Reminder</h2></div>
                    <div class="widget-content">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_reminder">
                            <div class="form-group">
                                <label>Announcement / Reminder Text (appears on billing statements):</label>
                                <textarea name="billing_reminder" rows="6" style="font-family:monospace;"><?php echo htmlspecialchars($reminder); ?></textarea>
                                <small style="color:#666;">Use \n for line breaks. Text like "TEMPORARY DISCONNECTION" will be highlighted in red.</small>
                            </div>
                            <div class="form-group">
                                <label>Thank You Tagline (bottom of statement):</label>
                                <input type="text" name="company_tagline" value="<?php echo htmlspecialchars($tagline); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Save Reminder</button>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password -->
                <div class="widget">
                    <div class="widget-header"><h2>🔒 Change Password</h2></div>
                    <div class="widget-content">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
                            <div class="form-group"><label>New Password</label><input type="password" name="new_password" required minlength="6"></div>
                            <div class="form-group"><label>Confirm</label><input type="password" name="confirm_password" required minlength="6"></div>
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="widget mt-3">
                <div class="widget-header"><h2>System Information</h2></div>
                <div class="widget-content">
                    <div class="form-group"><strong>Application:</strong> AR NOVALINK Billing System v2.0</div>
                    <div class="form-group"><strong>Database:</strong> ar_novalink_billing</div>
                    <div class="form-group"><strong>PHP Version:</strong> <?php echo phpversion(); ?></div>
                    <div class="form-group"><strong>Server Time:</strong> <?php echo date('F d, Y h:i:s A'); ?></div>
                </div>
            </div>
            
            <div class="widget mt-3">
                <div class="widget-header"><h2>Areas (<?php echo $areas->num_rows; ?>)</h2></div>
                <div class="widget-content">
                    <table><thead><tr><th>Area</th><th>Description</th><th>Created</th></tr></thead><tbody>
                        <?php while ($a = $areas->fetch_assoc()): ?>
                        <tr><td><?php echo htmlspecialchars($a['area_name']); ?></td><td><?php echo htmlspecialchars($a['description']??'N/A'); ?></td><td><?php echo date('M d, Y', strtotime($a['created_at'])); ?></td></tr>
                        <?php endwhile; ?>
                    </tbody></table>
                </div>
            </div>
        </main>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>
