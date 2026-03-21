<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$conn = getDBConnection();

$stats = ['total_customers'=>0,'active_customers'=>0,'disconnected'=>0,'total_unpaid'=>0,'monthly_revenue'=>0,'pending'=>0];
$stats['total_customers']=$conn->query("SELECT COUNT(*) as t FROM customers")->fetch_assoc()['t'];
$stats['active_customers']=$conn->query("SELECT COUNT(*) as t FROM customers WHERE status='active'")->fetch_assoc()['t'];
$stats['disconnected']=$conn->query("SELECT COUNT(*) as t FROM customers WHERE status='disconnected'")->fetch_assoc()['t'];
$stats['total_unpaid']=$conn->query("SELECT COUNT(*) as t FROM billings WHERE status='unpaid'")->fetch_assoc()['t'];
$stats['pending']=$conn->query("SELECT COUNT(*) as t FROM customers WHERE status='pending_installation'")->fetch_assoc()['t'];
$cm=date('n');$cy=date('Y');
$stats['monthly_revenue']=$conn->query("SELECT COALESCE(SUM(amount_paid),0) as t FROM payments WHERE MONTH(payment_date)=$cm AND YEAR(payment_date)=$cy")->fetch_assoc()['t'];

$page=isset($_GET['page'])?max(1,intval($_GET['page'])):1;$per_page=8;$offset=($page-1)*$per_page;
if($_SESSION['role']=='admin'){
    $total_records=$conn->query("SELECT COUNT(*) as t FROM activity_logs")->fetch_assoc()['t'];
    $activity=$conn->query("SELECT al.*,u.username,u.full_name FROM activity_logs al LEFT JOIN users u ON al.user_id=u.user_id ORDER BY al.created_at DESC LIMIT $per_page OFFSET $offset");
}else{
    $uid=$_SESSION['user_id'];
    $total_records=$conn->query("SELECT COUNT(*) as t FROM activity_logs WHERE user_id=$uid")->fetch_assoc()['t'];
    $activity=$conn->query("SELECT al.*,u.username,u.full_name FROM activity_logs al LEFT JOIN users u ON al.user_id=u.user_id WHERE al.user_id=$uid ORDER BY al.created_at DESC LIMIT $per_page OFFSET $offset");
}
$total_pages=ceil($total_records/$per_page);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - AR NOVALINK</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header"><div><h1>Dashboard</h1><p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p></div></div>

        <div class="search-container">
            <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" id="global-search" placeholder="Search by subscriber name, account #, OR number..." autocomplete="off">
            <div id="search-results" class="search-results"></div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon blue"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div><div class="stat-details"><h3><?php echo number_format($stats['total_customers']); ?></h3><p>Total Subscribers</p></div></div>
            <div class="stat-card"><div class="stat-icon green"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div class="stat-details"><h3><?php echo number_format($stats['active_customers']); ?></h3><p>Active Subscribers</p></div></div>
            <div class="stat-card"><div class="stat-icon red"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><div class="stat-details"><h3><?php echo number_format($stats['total_unpaid']); ?></h3><p>Unpaid Bills</p></div></div>
            <div class="stat-card"><div class="stat-icon orange"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div class="stat-details"><h3><?php echo format_currency($stats['monthly_revenue']); ?></h3><p>Monthly Revenue</p></div></div>
        </div>

        <div class="dashboard-widgets">
            <div class="widget">
                <div class="widget-header"><h2>Quick Actions</h2></div>
                <div class="widget-content"><div class="quick-actions">
                    <?php if($_SESSION['role']=='cashier'||$_SESSION['role']=='admin'):?>
                    <a href="payments.php?action=new" class="quick-action-btn"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg><span>Record Payment</span></a>
                    <?php endif;?>
                    <a href="customers.php" class="quick-action-btn"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><span>View Subscribers</span></a>
                    <?php if($_SESSION['role']=='admin'||$_SESSION['role']=='accounting'):?>
                    <a href="reports.php" class="quick-action-btn"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span>Reports</span></a>
                    <?php endif;?>
                    <?php if($_SESSION['role']=='admin'):?>
                    <a href="billings.php" class="quick-action-btn"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><line x1="16" y1="13" x2="8" y2="13"/></svg><span>Manage Billings</span></a>
                    <?php endif;?>
                </div></div>
            </div>
            <div class="widget">
                <div class="widget-header"><h2>Recent Activity</h2><span class="badge badge-secondary"><?php echo number_format($total_records);?> total</span></div>
                <div class="widget-content widget-content-flush">
                    <?php if($activity&&$activity->num_rows>0):?>
                    <div class="table-responsive"><table class="activity-table"><thead><tr><th class="th-icon"></th><?php if($_SESSION['role']=='admin'):?><th class="th-user">User</th><?php endif;?><th>Action</th><th class="th-table">Table</th><th class="th-date">Date</th></tr></thead><tbody>
                    <?php while($a=$activity->fetch_assoc()):?>
                    <tr><td class="text-center"><?php echo match($a['action']){'LOGIN'=>'🔵','LOGOUT'=>'⚪','RECORD_PAYMENT'=>'💳','ADD_CUSTOMER'=>'👤','GENERATE_BILLING'=>'📄','DISCONNECT_CUSTOMER'=>'🔴','RECONNECT_CUSTOMER'=>'🟢','CONFIRM_INSTALLATION'=>'✅',default=>'🟣'};?></td>
                    <?php if($_SESSION['role']=='admin'):?><td><strong class="activity-user"><?php echo htmlspecialchars($a['full_name']??$a['username']);?></strong></td><?php endif;?>
                    <td class="activity-desc"><?php echo htmlspecialchars($a['description']);?></td>
                    <td><span class="badge badge-secondary badge-xs"><?php echo htmlspecialchars($a['table_name']);?></span></td>
                    <td class="activity-date"><?php echo date('M d, Y h:i A',strtotime($a['created_at']));?></td></tr>
                    <?php endwhile;?>
                    </tbody></table></div>
                    <?php if($total_pages>1):?><div class="pagination-container"><div class="pagination">
                    <?php if($page>1):?><a href="?page=<?php echo $page-1;?>" class="pagination-btn">←</a><?php endif;?>
                    <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++):?><a href="?page=<?php echo $i;?>" class="pagination-btn <?php echo $i==$page?'active':'';?>"><?php echo $i;?></a><?php endfor;?>
                    <?php if($page<$total_pages):?><a href="?page=<?php echo $page+1;?>" class="pagination-btn">→</a><?php endif;?>
                    </div></div><?php endif;?>
                    <?php else:?><div class="empty-state">No recent activity</div><?php endif;?>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="js/script.js"></script>
<script>
var si=document.getElementById('global-search'),sr=document.getElementById('search-results'),st;
si.addEventListener('input',function(){clearTimeout(st);var q=this.value.trim();if(q.length<1){sr.style.display='none';return;}st=setTimeout(function(){fetch('ajax/dashboard_search.php?q='+encodeURIComponent(q)).then(function(r){return r.json()}).then(function(d){if(d.length===0){sr.innerHTML='<div class="empty-state p-2">No results</div>';}else{sr.innerHTML=d.map(function(i){return '<div class="search-result-item" onclick="window.location=\''+i.url+'\'"><div><span class="name">'+(i.type==='customer'?'👤':'💳')+' '+i.name+'</span><br><span class="meta">'+i.detail+'</span></div><span class="badge badge-'+(i.status_class||'secondary')+'">'+(i.status||'')+'</span></div>';}).join('');}sr.style.display='block';});},250);});
si.addEventListener('focus',function(){if(this.value.trim().length>0)sr.style.display='block';});
document.addEventListener('click',function(e){if(!e.target.closest('.search-container'))sr.style.display='none';});
</script>
</body>
</html>
