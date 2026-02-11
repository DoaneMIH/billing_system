<header class="main-header">
    <div class="header-content">
        <div class="header-logo">
            <svg width="40" height="40" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="30" cy="30" r="28" stroke="#ffffff" stroke-width="4"/>
                <path d="M20 30L27 37L40 24" stroke="#ffffff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <h1>AR NOVALINK Billing System</h1>
        </div>
        <div class="header-user">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
            </div>
            <a href="logout.php" class="btn btn-danger btn-sm">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Logout
            </a>
        </div>
    </div>
</header>