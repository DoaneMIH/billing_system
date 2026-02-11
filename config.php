<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ar_novalink_billing');

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Application Settings
define('APP_NAME', 'AR NOVALINK Billing System');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Manila');

date_default_timezone_set(TIMEZONE);

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
session_start();

// Helper Functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function log_activity($user_id, $action, $table_name = null, $record_id = null, $description = null) {
    $conn = getDBConnection();
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $user_id, $action, $table_name, $record_id, $description, $ip_address);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function check_permission($required_role) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    
    $user_role = $_SESSION['role'];
    
    if ($required_role === 'admin' && $user_role !== 'admin') {
        header("Location: index.php?error=unauthorized");
        exit();
    }
    
    if ($required_role === 'accounting' && !in_array($user_role, ['admin', 'accounting'])) {
        header("Location: index.php?error=unauthorized");
        exit();
    }
}

function format_currency($amount) {
    return '₱' . number_format($amount, 2);
}

function get_month_name($month) {
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    return $months[$month] ?? '';
}
?>