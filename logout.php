<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id'], 'User logged out');
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login
header("Location: login.php");
exit();
?>