<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();

// Get statistics
$stats = [
    'total_customers' => 0,
    'active_customers' => 0,
    'total_unpaid' => 0,
    'monthly_revenue' => 0
];

$result = $conn->query("SELECT COUNT(*) as total FROM customers");
$stats['total_customers'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM customers WHERE status = 'active'");
$stats['active_customers'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM billings WHERE status = 'unpaid'");
$stats['total_unpaid'] = $result->fetch_assoc()['total'];

$current_month = date('n');
$current_year = date('Y');
$result = $conn->query("SELECT SUM(amount_paid) as total FROM payments WHERE MONTH(payment_date) = $current_month AND YEAR(payment_date) = $current_year");
$row = $result->fetch_assoc();
$stats['monthly_revenue'] = $row['total'] ?? 0;

// Pagination for recent activity
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 5;
$offset = ($page - 1) * $per_page;

// Get total count for pagination
if ($_SESSION['role'] == 'admin') {
    $count_result = $conn->query("SELECT COUNT(*) as total FROM activity_logs");
} else {
    $user_id = $_SESSION['user_id'];
    $count_result = $conn->query("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = $user_id");
}
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Get recent activity based on user role with pagination
if ($_SESSION['role'] == 'admin') {
    // Admin sees ALL activity from all users
    $activity_query = "
        SELECT al.*, u.username, u.full_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        ORDER BY al.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
} else {
    // Other roles only see their own activity
    $user_id = $_SESSION['user_id'];
    $activity_query = "
        SELECT al.*, u.username, u.full_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        WHERE al.user_id = $user_id
        ORDER BY al.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
}

$recent_activity = $conn->query($activity_query);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AR NOVALINK Billing System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_customers']); ?></h3>
                        <p>Total Customers</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['active_customers']); ?></h3>
                        <p>Active Customers</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon red">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_unpaid']); ?></h3>
                        <p>Unpaid Bills</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo format_currency($stats['monthly_revenue']); ?></h3>
                        <p>Monthly Revenue</p>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-widgets">
                <div class="widget">
                    <div class="widget-header">
                        <h2>Quick Actions</h2>
                    </div>
                    <div class="widget-content">
                        <div class="quick-actions">
                            <?php if ($_SESSION['role'] == 'cashier' || $_SESSION['role'] == 'admin'): ?>
                            <a href="payments.php?action=new" class="quick-action-btn">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                    <line x1="1" y1="10" x2="23" y2="10"/>
                                </svg>
                                <span>Record Payment</span>
                            </a>
                            <?php endif; ?>
                            
                            <a href="customers.php" class="quick-action-btn">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                <span>View Customers</span>
                            </a>
                            
                            <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'accounting'): ?>
                            <a href="reports.php" class="quick-action-btn">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                    <polyline points="10 9 9 9 8 9"/>
                                </svg>
                                <span>Generate Report</span>
                            </a>
                            <?php endif; ?>
                            
                            <a href="search.php" class="quick-action-btn">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                </svg>
                                <span>Search Customer</span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="widget">
                    <div class="widget-header">
                        <h2>Recent Activity</h2>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <?php if ($_SESSION['role'] == 'admin'): ?>
                            <span class="badge badge-info">All Users</span>
                            <?php else: ?>
                            <span class="badge badge-secondary">Your Activity</span>
                            <?php endif; ?>
                            <span class="badge badge-secondary"><?php echo number_format($total_records); ?> total</span>
                        </div>
                    </div>
                    <div class="widget-content" style="padding: 0;">
                        <?php if ($recent_activity && $recent_activity->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="activity-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <?php if ($_SESSION['role'] == 'admin'): ?>
                                        <th style="width: 150px;">User</th>
                                        <?php endif; ?>
                                        <th>Action</th>
                                        <th style="width: 120px;">Table</th>
                                        <th style="width: 180px;">Date & Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-center">
                                            <div class="activity-icon-small <?php 
                                                echo match($activity['action']) {
                                                    'LOGIN' => 'blue',
                                                    'LOGOUT' => 'gray',
                                                    'ADD_CUSTOMER', 'ADD_USER', 'ADD_AREA', 'ADD_PACKAGE' => 'green',
                                                    'RECORD_PAYMENT' => 'success',
                                                    'GENERATE_BILLING' => 'orange',
                                                    'DELETE_USER', 'DISCONNECT_CUSTOMER', 'DELETE_AREA', 'DELETE_PACKAGE' => 'red',
                                                    'EDIT_CUSTOMER', 'EDIT_AREA', 'EDIT_PACKAGE', 'UPDATE_USER_STATUS' => 'blue',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php
                                                $icon = match($activity['action']) {
                                                    'LOGIN' => '🔵',
                                                    'LOGOUT' => '⚪',
                                                    'RECORD_PAYMENT' => '💳',
                                                    'ADD_CUSTOMER' => '👤',
                                                    'GENERATE_BILLING' => '📄',
                                                    'ADD_USER' => '👥',
                                                    'DELETE_USER' => '🗑️',
                                                    'DISCONNECT_CUSTOMER' => '🔴',
                                                    'RECONNECT_CUSTOMER' => '🟢',
                                                    'EDIT_CUSTOMER' => '✏️',
                                                    'ADD_AREA' => '📍',
                                                    'ADD_PACKAGE' => '📦',
                                                    default => '🟣'
                                                };
                                                echo $icon;
                                                ?>
                                            </div>
                                        </td>
                                        <?php if ($_SESSION['role'] == 'admin'): ?>
                                        <td>
                                            <strong style="color: var(--primary-color); font-size: 13px;">
                                                <?php echo htmlspecialchars($activity['full_name'] ?? $activity['username']); ?>
                                            </strong>
                                        </td>
                                        <?php endif; ?>
                                        <td>
                                            <div style="font-size: 13px; color: var(--text-color);">
                                                <?php echo htmlspecialchars($activity['description']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary" style="font-size: 11px;">
                                                <?php echo htmlspecialchars($activity['table_name']); ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 12px; color: #666;">
                                            <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_records); ?> of <?php echo number_format($total_records); ?> records
                            </div>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                <a href="?page=1" class="pagination-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="11 17 6 12 11 7"/>
                                        <polyline points="18 17 13 12 18 7"/>
                                    </svg>
                                </a>
                                <a href="?page=<?php echo $page - 1; ?>" class="pagination-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="15 18 9 12 15 6"/>
                                    </svg>
                                </a>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                <a href="?page=<?php echo $i; ?>" class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="pagination-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </a>
                                <a href="?page=<?php echo $total_pages; ?>" class="pagination-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="13 17 18 12 13 7"/>
                                        <polyline points="6 17 11 12 6 7"/>
                                    </svg>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <div style="padding: 40px; text-align: center;">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="opacity: 0.2; margin-bottom: 15px;">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <p style="color: #999; font-size: 14px; margin: 0;">No recent activity yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>