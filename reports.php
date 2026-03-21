<?php
require_once 'config.php';
check_permission('accounting');
$conn = getDBConnection();

$tab = isset($_GET['tab']) ? sanitize_input($_GET['tab']) : 'monthly';
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year_filter  = isset($_GET['year'])  ? intval($_GET['year'])  : date('Y');
$area_filter  = isset($_GET['area'])  ? intval($_GET['area'])  : 0;

/* Monthly metrics */
$m_new_subs     = $conn->query("SELECT COUNT(*) as c FROM customers WHERE MONTH(created_at)=$month_filter AND YEAR(created_at)=$year_filter")->fetch_assoc()['c'];
$m_payments     = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as s FROM payments WHERE MONTH(payment_date)=$month_filter AND YEAR(payment_date)=$year_filter")->fetch_assoc()['s'];
$m_unpaid       = $conn->query("SELECT COUNT(*) as c FROM billings WHERE status IN('unpaid','partial') AND billing_month=$month_filter AND billing_year=$year_filter")->fetch_assoc()['c'];
$m_paid         = $conn->query("SELECT COUNT(*) as c FROM billings WHERE status='paid' AND billing_month=$month_filter AND billing_year=$year_filter")->fetch_assoc()['c'];
$m_partial      = $conn->query("SELECT COUNT(*) as c FROM billings WHERE status='partial' AND billing_month=$month_filter AND billing_year=$year_filter")->fetch_assoc()['c'];
$m_active       = $conn->query("SELECT COUNT(*) as c FROM customers WHERE status='active'")->fetch_assoc()['c'];
$m_pending      = $conn->query("SELECT COUNT(*) as c FROM customers WHERE status='pending_installation'")->fetch_assoc()['c'];
$m_disconnected = $conn->query("SELECT COUNT(*) as c FROM customers WHERE status='disconnected'")->fetch_assoc()['c'];

$top_paying = $conn->query("SELECT c.subscriber_name,c.account_number,SUM(p.amount_paid) as total FROM payments p JOIN customers c ON p.customer_id=c.customer_id WHERE MONTH(p.payment_date)=$month_filter AND YEAR(p.payment_date)=$year_filter GROUP BY c.customer_id ORDER BY total DESC LIMIT 5");
$top_unpaid = $conn->query("SELECT c.subscriber_name,c.account_number,SUM(b.net_amount-COALESCE((SELECT SUM(p2.amount_paid) FROM payments p2 WHERE p2.billing_id=b.billing_id),0)) as balance FROM billings b JOIN customers c ON b.customer_id=c.customer_id WHERE b.status IN('unpaid','partial') AND b.billing_year=$year_filter GROUP BY c.customer_id ORDER BY balance DESC LIMIT 5");

/* Yearly metrics */
$y_revenue = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as s FROM payments WHERE YEAR(payment_date)=$year_filter")->fetch_assoc()['s'];
$y_prev    = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as s FROM payments WHERE YEAR(payment_date)=".($year_filter-1))->fetch_assoc()['s'];
$y_pct     = $y_prev > 0 ? round(($y_revenue - $y_prev) / $y_prev * 100, 1) : 0;
$y_new     = $conn->query("SELECT COUNT(*) as c FROM customers WHERE YEAR(created_at)=$year_filter")->fetch_assoc()['c'];
$y_disc    = $conn->query("SELECT COUNT(*) as c FROM customer_status_log WHERE new_status='disconnected' AND YEAR(change_date)=$year_filter")->fetch_assoc()['c'];

$chart_data = [];
for ($m=1;$m<=12;$m++) { $chart_data[$m] = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as s FROM payments WHERE MONTH(payment_date)=$m AND YEAR(payment_date)=$year_filter")->fetch_assoc()['s']; }
$chart_max = max(array_values($chart_data)) ?: 1;

$quarters = [];
for ($q=1;$q<=4;$q++) { $ms=(($q-1)*3+1); $me=$q*3; $quarters[$q]=$conn->query("SELECT COALESCE(SUM(amount_paid),0) as s FROM payments WHERE MONTH(payment_date) BETWEEN $ms AND $me AND YEAR(payment_date)=$year_filter")->fetch_assoc()['s']; }

$areas_list = $conn->query("SELECT * FROM areas ORDER BY area_name");
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Reports - AR NOVALINK</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">

        <!-- Screen-only page header -->
        <div class="page-header no-print"><div><h1>Reports &amp; Analytics</h1><p>Comprehensive monthly and yearly reports</p></div></div>

        <!-- PRINT HEADER — visible ONLY when printing -->
        <div class="print-header">
            <img src="images/headerlogo.png" alt="NovaLink" class="print-header-logo">
            <?php if ($tab == 'monthly'): ?>
                <h2>MONTHLY REPORT</h2>
                <h3><?php echo get_month_name($month_filter) . ' ' . $year_filter; ?></h3>
            <?php elseif ($tab == 'yearly'): ?>
                <h2>ANNUAL REPORT — <?php echo $year_filter; ?></h2>
            <?php else: ?>
                <h2>REPORT</h2>
            <?php endif; ?>
            <p class="text-muted">Generated: <?php echo date('F d, Y h:i A'); ?></p>
        </div>

        <!-- TABS — screen only -->
        <div class="report-tabs no-print">
            <button class="report-tab <?php echo $tab=='monthly'?'active':''; ?>" onclick="location='reports.php?tab=monthly&month=<?php echo $month_filter;?>&year=<?php echo $year_filter;?>'">Monthly Report</button>
            <button class="report-tab <?php echo $tab=='yearly'?'active':''; ?>" onclick="location='reports.php?tab=yearly&year=<?php echo $year_filter;?>'">Yearly Report</button>
            <button class="report-tab <?php echo $tab=='legacy'?'active':''; ?>" onclick="location='reports.php?tab=legacy&report_type=monthly_billing&month=<?php echo $month_filter;?>&year=<?php echo $year_filter;?>'">Legacy Reports</button>
            <button class="report-tab" onclick="window.print()">🖨️ Print</button>
        </div>

        <?php if ($tab == 'monthly'): ?>
        <!-- ═══════════════ MONTHLY REPORT ═══════════════ -->

        <!-- Filters — no-print so hidden when printing -->
        <form method="GET" class="widget mb-3 no-print">
            <input type="hidden" name="tab" value="monthly">
            <div class="widget-header"><h2>Filters</h2></div> 
            <div class="widget-content"> 
            <div class="form-group"><label>Month</label>
                <select name="month"><?php for($m=1;$m<=12;$m++):?><option value="<?php echo $m;?>" <?php echo $m==$month_filter?'selected':'';?>><?php echo get_month_name($m);?></option><?php endfor;?></select></div>
            <div class="form-group"><label>Year</label>
                <select name="year"><?php for($y=date('Y');$y>=date('Y')-3;$y--):?><option value="<?php echo $y;?>" <?php echo $y==$year_filter?'selected':'';?>><?php echo $y;?></option><?php endfor;?></select></div>
            <div class="form-group form-group-btn"><button type="submit" class="btn btn-primary">Generate</button></div>
            </div>      
        </form>

        <!-- Report content — NOT inside no-print so it WILL print -->
        <div class="report-content active">
            <div class="report-summary-grid">
                <div class="report-summary-card green"><h4>PAYMENTS COLLECTED</h4><div class="metric"><?php echo format_currency($m_payments); ?></div></div>
                <div class="report-summary-card"><h4>NEW SUBSCRIBERS</h4><div class="metric"><?php echo $m_new_subs; ?></div></div>
                <div class="report-summary-card red"><h4>UNPAID BILLS</h4><div class="metric"><?php echo $m_unpaid; ?></div></div>
                <div class="report-summary-card orange"><h4>PAID BILLS</h4><div class="metric"><?php echo $m_paid; ?></div></div>
            </div>

            <div class="report-two-col">
                <div class="widget">
                    <div class="widget-header"><h2>Payment Status</h2></div>
                    <div class="widget-content">
                        <table class="report-table"><thead><tr><th>Status</th><th>Count</th></tr></thead>
                        <tbody><tr><td>Paid</td><td><?php echo $m_paid; ?></td></tr><tr><td>Partial</td><td><?php echo $m_partial; ?></td></tr><tr><td>Unpaid</td><td><?php echo $m_unpaid; ?></td></tr></tbody></table>
                    </div>
                </div>
                <div class="widget">
                    <div class="widget-header"><h2>Subscriber Status</h2></div>
                    <div class="widget-content">
                        <table class="report-table"><thead><tr><th>Status</th><th>Count</th></tr></thead>
                        <tbody><tr><td>Active</td><td><?php echo $m_active; ?></td></tr><tr><td>Pending</td><td><?php echo $m_pending; ?></td></tr><tr><td>Disconnected</td><td><?php echo $m_disconnected; ?></td></tr></tbody></table>
                    </div>
                </div>
            </div>

            <div class="report-two-col-mt">
                <div class="widget">
                    <div class="widget-header"><h2>Top 5 Paying Accounts</h2></div>
                    <div class="widget-content">
                        <table class="report-table"><thead><tr><th>#</th><th>Subscriber</th><th>Account</th><th>Total Paid</th></tr></thead><tbody>
                        <?php $n=1; if($top_paying&&$top_paying->num_rows>0): while($tp=$top_paying->fetch_assoc()):?>
                        <tr><td><?php echo $n++;?></td><td><?php echo htmlspecialchars($tp['subscriber_name']);?></td><td><?php echo $tp['account_number'];?></td><td><?php echo format_currency($tp['total']);?></td></tr>
                        <?php endwhile; else:?><tr><td colspan="4" class="text-center">No data</td></tr><?php endif;?></tbody></table>
                    </div>
                </div>
                <div class="widget">
                    <div class="widget-header"><h2>Top 5 Unpaid Accounts</h2></div>
                    <div class="widget-content">
                        <table class="report-table"><thead><tr><th>#</th><th>Subscriber</th><th>Account</th><th>Balance</th></tr></thead><tbody>
                        <?php $n=1; if($top_unpaid&&$top_unpaid->num_rows>0): while($tu=$top_unpaid->fetch_assoc()):?>
                        <tr><td><?php echo $n++;?></td><td><?php echo htmlspecialchars($tu['subscriber_name']);?></td><td><?php echo $tu['account_number'];?></td><td class="report-danger-text"><?php echo format_currency($tu['balance']);?></td></tr>
                        <?php endwhile; else:?><tr><td colspan="4" class="text-center">No data</td></tr><?php endif;?></tbody></table>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($tab == 'yearly'): ?>
        <!-- ═══════════════ YEARLY REPORT ═══════════════ -->
        
        <form method="GET" class="widget mb-3 no-print">
            <input type="hidden" name="tab" value="yearly">
<div class="widget-header">
                    <h2>Select Year</h2>
                </div>
                <div class="widget-content">
            <div class="form-group"><label>Year</label>
                <select name="year"><?php for($y=date('Y');$y>=date('Y')-5;$y--):?><option value="<?php echo $y;?>" <?php echo $y==$year_filter?'selected':'';?>><?php echo $y;?></option><?php endfor;?></select></div>
            <div class="form-group form-group-btn"><button type="submit" class="btn btn-primary">Generate</button></div>
          </div>          
        </form>
    

        <div class="report-content active">
            <div class="report-summary-grid">
                <div class="report-summary-card green"><h4>ANNUAL REVENUE</h4><div class="metric"><?php echo format_currency($y_revenue); ?></div></div>
                <div class="report-summary-card"><h4>NEW SUBSCRIBERS</h4><div class="metric"><?php echo $y_new; ?></div></div>
                <div class="report-summary-card red"><h4>DISCONNECTED</h4><div class="metric"><?php echo $y_disc; ?></div></div>
                <div class="report-summary-card orange"><h4>YOY CHANGE</h4><div class="metric"><?php echo ($y_pct>=0?'+':'').$y_pct; ?>%</div></div>
            </div>

            <div class="widget mb-3">
                <div class="widget-header"><h2>Monthly Revenue Trend — <?php echo $year_filter; ?></h2></div>
                <div class="widget-content">
                    <div class="chart-bars report-chart-wrapper">
                        <?php for($m=1;$m<=12;$m++): $pct=$chart_max>0?($chart_data[$m]/$chart_max*100):0; ?>
                        <div class="chart-bar" style="height:<?php echo max(4,$pct);?>%">
                            <span class="chart-bar-value"><?php echo $chart_data[$m]>0?'₱'.number_format($chart_data[$m]/1000,0).'k':''; ?></span>
                            <span class="chart-bar-label"><?php echo substr(get_month_name($m),0,3); ?></span>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div class="widget mb-3">
                <div class="widget-header"><h2>Quarterly Summary</h2></div>
                <div class="widget-content">
                    <table class="report-table"><thead><tr><th>Quarter</th><th>Period</th><th>Revenue</th></tr></thead><tbody>
                    <?php $qn=['','Jan–Mar','Apr–Jun','Jul–Sep','Oct–Dec']; for($q=1;$q<=4;$q++):?>
                    <tr><td>Q<?php echo $q;?></td><td><?php echo $qn[$q];?></td><td><?php echo format_currency($quarters[$q]);?></td></tr>
                    <?php endfor;?>
                    <tr class="report-annual-total"><td colspan="2">Annual Total</td><td><?php echo format_currency($y_revenue);?></td></tr>
                    </tbody></table>
                </div>
            </div>

            <div class="widget mb-3">
                <div class="widget-header"><h2>Year-over-Year Comparison</h2></div>
                <div class="widget-content">
                    <table class="report-table"><thead><tr><th>Metric</th><th><?php echo $year_filter-1;?></th><th><?php echo $year_filter;?></th><th>Change</th></tr></thead><tbody>
                    <tr><td>Total Revenue</td><td><?php echo format_currency($y_prev);?></td><td><?php echo format_currency($y_revenue);?></td><td class="<?php echo $y_pct>=0?'report-yoy-positive':'report-yoy-negative';?>"><?php echo ($y_pct>=0?'+':'').$y_pct;?>%</td></tr>
                    </tbody></table>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- ═══════════════ LEGACY REPORTS ═══════════════ -->
        <?php $report_type = isset($_GET['report_type']) ? sanitize_input($_GET['report_type']) : 'monthly_billing'; ?>

        <div class="widget mb-3 no-print">
            <div class="widget-header"><h2>Report Type</h2></div>
            <div class="widget-content">
                <div class="form-row">
                    <div class="form-group">
                <select id="report_type" onchange="location='reports.php?tab=legacy&report_type='+this.value+'&month=<?php echo $month_filter;?>&year=<?php echo $year_filter;?>&area=<?php echo $area_filter;?>'">
                    <option value="monthly_billing" <?php echo $report_type=='monthly_billing'?'selected':'';?>>Monthly Billing</option>
                    <option value="monthly_sales" <?php echo $report_type=='monthly_sales'?'selected':'';?>>Monthly Sales</option>
                    <option value="unpaid_accounts" <?php echo $report_type=='unpaid_accounts'?'selected':'';?>>Unpaid Accounts</option>
                    <option value="for_disconnection" <?php echo $report_type=='for_disconnection'?'selected':'';?>>For Disconnection</option>
                    <option value="last_payment" <?php echo $report_type=='last_payment'?'selected':'';?>>Last Payment Dates</option>
                </select>
                </div>
                </div>
            </div>
        </div>
        <div class="widget mb-3 no-print">
            <div class="widget-header"><h2>Filters</h2></div>
            <div class="widget-content">
                <form method="GET"><input type="hidden" name="tab" value="legacy"><input type="hidden" name="report_type" value="<?php echo $report_type;?>">
                <div class="form-row">
                    <div class="form-group"><label>Month</label><select name="month"><?php for($m=1;$m<=12;$m++):?><option value="<?php echo $m;?>" <?php echo $m==$month_filter?'selected':'';?>><?php echo get_month_name($m);?></option><?php endfor;?></select></div>
                    <div class="form-group"><label>Year</label><select name="year"><?php for($y=date('Y');$y>=date('Y')-3;$y--):?><option value="<?php echo $y;?>" <?php echo $y==$year_filter?'selected':'';?>><?php echo $y;?></option><?php endfor;?></select></div>
                    <div class="form-group"><label>Area</label><select name="area"><option value="0">All</option><?php $areas_list->data_seek(0); while($a=$areas_list->fetch_assoc()):?><option value="<?php echo $a['area_id'];?>" <?php echo $area_filter==$a['area_id']?'selected':'';?>><?php echo htmlspecialchars($a['area_name']);?></option><?php endwhile;?></select></div>
                    <div class="form-group form-group-btn"><button type="submit" class="btn btn-primary">Generate</button></div>
                </div></form>
            </div>
        </div>

        <!-- Legacy report content — prints -->
        <div class="report-content active">
            <?php
            switch($report_type){
                case 'monthly_billing': include 'monthly_billing_report.php'; break;
                case 'monthly_sales': include 'monthly_sales_report.php'; break;
                case 'unpaid_accounts': include 'unpaid_accounts_report.php'; break;
                case 'for_disconnection': include 'for_disconnection_report.php'; break;
                case 'last_payment': include 'last_payment_report.php'; break;
            }
            ?>
        </div>
        <?php endif; ?>

    </main>
</div>
<script src="js/script.js"></script>
<?php include "includes/footer.php"; ?>
</body>
</html>
<?php $conn->close(); ?>