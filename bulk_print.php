<?php
require_once 'config.php';
check_permission('admin');
$conn = getDBConnection();

$areas = $conn->query("SELECT * FROM areas ORDER BY area_name");
$packages = $conn->query("SELECT * FROM packages WHERE status='active' ORDER BY package_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Print - AR NOVALINK</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Bulk Printing</h1>
                    <p>Print multiple billing statements or installation forms</p>
                </div>
            </div>
            
            <div class="dashboard-widgets">
                <!-- Bulk Billing Statements -->
                <div class="widget">
                    <div class="widget-header"><h2>🖨️ Bulk Print Billing Statements</h2></div>
                    <div class="widget-content">
                        <form id="bulkBillingForm" target="_blank">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Billing Month *</label>
                                    <select name="month" required>
                                        <?php for ($m=1;$m<=12;$m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m==date('n')?'selected':''; ?>><?php echo get_month_name($m); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Year *</label>
                                    <select name="year" required>
                                        <?php for ($y=date('Y');$y>=date('Y')-2;$y--): ?>
                                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Subscriber Status</label>
                                    <select name="status">
                                        <option value="">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="disconnected">Disconnected</option>
                                        <option value="reconnected">Reconnected</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Area</label>
                                    <select name="area_id">
                                        <option value="0">All Areas</option>
                                        <?php $areas->data_seek(0); while ($a = $areas->fetch_assoc()): ?>
                                        <option value="<?php echo $a['area_id']; ?>"><?php echo htmlspecialchars($a['area_name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Package</label>
                                    <select name="package_id">
                                        <option value="0">All Packages</option>
                                        <?php $packages->data_seek(0); while ($p = $packages->fetch_assoc()): ?>
                                        <option value="<?php echo $p['package_id']; ?>"><?php echo htmlspecialchars($p['package_name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="button" onclick="printBulkBilling()" class="btn btn-primary">🖨️ Print Billing Statements</button>
                        </form>
                    </div>
                </div>
                
                <!-- Bulk Installation Forms -->
                <div class="widget">
                    <div class="widget-header"><h2>📋 Bulk Print Installation Forms</h2></div>
                    <div class="widget-content">
                        <form id="bulkInstallForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Subscriber Status</label>
                                    <select name="inst_status">
                                        <option value="">All</option>
                                        <option value="active">Active</option>
                                        <option value="pending_installation">Pending Installation</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Area</label>
                                    <select name="inst_area">
                                        <option value="0">All Areas</option>
                                        <?php $areas->data_seek(0); while ($a = $areas->fetch_assoc()): ?>
                                        <option value="<?php echo $a['area_id']; ?>"><?php echo htmlspecialchars($a['area_name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="button" onclick="printBulkInstall()" class="btn btn-primary">🖨️ Print Installation Forms</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Quick Individual Print -->
            <div class="widget mt-3">
                <div class="widget-header"><h2>👤 Individual Print</h2></div>
                <div class="widget-content">
                    <div class="form-group">
                        <label>Search Subscriber</label>
                        <input type="text" id="print-search" placeholder="Type subscriber name or account #..." autocomplete="off">
                        <div id="print-results" class="mt-1"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="js/script.js"></script>
    <script>
        function printBulkBilling() {
            const form = document.getElementById('bulkBillingForm');
            const data = new FormData(form);
            let url = 'print_billing_statement.php?bulk=1';
            for (let [key, value] of data) { url += `&${key}=${value}`; }
            window.open(url, '_blank');
        }
        
        function printBulkInstall() {
            const form = document.getElementById('bulkInstallForm');
            const status = form.querySelector('[name=inst_status]').value;
            const area = form.querySelector('[name=inst_area]').value;
            let url = `ajax/search_customers.php?q=&status=${status}&area=${area}`;
            fetch(url).then(r=>r.json()).then(customers => {
                if (customers.length === 0) { alert('No subscribers found with these filters.'); return; }
                customers.forEach((c, i) => {
                    setTimeout(() => window.open(`print_installation.php?id=${c.customer_id}`, '_blank'), i * 200);
                });
            });
        }
        
        // Individual search
        let searchTimer;
        document.getElementById('print-search').addEventListener('input', function() {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            if (q.length < 1) { document.getElementById('print-results').innerHTML = ''; return; }
            searchTimer = setTimeout(() => {
                fetch(`ajax/search_customers.php?q=${encodeURIComponent(q)}`)
                    .then(r => r.json())
                    .then(customers => {
                        let html = '';
                        customers.forEach(c => {
                            html += `<div class="search-result-item">
                                <div><strong>${c.subscriber_name}</strong> (${c.account_number})</div>
                                <div>
                                    <a href="print_billing_statement.php?id=${c.customer_id}" target="_blank" class="btn btn-sm btn-primary">Print SOA</a>
                                    <a href="print_installation.php?id=${c.customer_id}" target="_blank" class="btn btn-sm btn-secondary">Install Form</a>
                                </div>
                            </div>`;
                        });
                        document.getElementById('print-results').innerHTML = html || '<div class="search-no-results">No results</div>';
                    });
            }, 300);
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
