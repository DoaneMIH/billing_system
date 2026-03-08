<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar">
    <ul class="sidebar-menu">
        <li><a href="index.php" class="<?php echo $current_page=='index.php'?'active':''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Dashboard</span></a></li>
        
        <li><a href="customers.php" class="<?php echo $current_page=='customers.php'?'active':''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            <span>Customers</span></a></li>
        
        <?php if ($_SESSION['role'] == 'cashier' || $_SESSION['role'] == 'admin'): ?>
        <li><a href="payments.php" class="<?php echo $current_page=='payments.php'?'active':''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            <span>Payments</span></a></li>
        <?php endif; ?>
        
        <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'accounting'): ?>
        <li><a href="billings.php" class="<?php echo $current_page=='billings.php'?'active':''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <span>Billings</span></a></li>
        
        <li><a href="unpaid.php" class="<?php echo $current_page=='unpaid.php'?'active':''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>Unpaid Bills</span></a></li>
        
        <li><a href="reports.php" class="<?php echo $current_page=='reports.php'?'active':''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <span>Reports</span></a></li>
        <?php endif; ?>
        
        <?php if ($_SESSION['role'] == 'admin'): ?>
        <li><a href="users.php" class="<?php echo $current_page=='users.php'?'active':''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="11" x2="23" y2="11"/></svg>
            <span>User Management</span></a></li>

        <li><a href="manage_areas.php" class="<?php echo $current_page=='manage_areas.php'?'active':''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span>Manage Areas</span></a></li>
        
        <li><a href="manage_packages.php" class="<?php echo $current_page=='manage_packages.php'?'active':''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            <span>Manage Packages</span></a></li>
        
        <li><a href="bulk_print.php" class="<?php echo $current_page=='bulk_print.php'?'active':''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            <span>Bulk Print</span></a></li>
        
        <li><a href="settings.php" class="<?php echo $current_page=='settings.php'?'active':''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6m9.5-9h-6m-6 0h-6"/></svg>
            <span>Settings</span></a></li>
        <?php endif; ?>
    </ul>
</aside>
