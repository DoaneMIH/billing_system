<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])||!in_array($_SESSION['role'],['cashier','admin'])){header("Location: index.php");exit();}
$conn = getDBConnection();

/* Ensure advance_payments table exists */
$conn->query("CREATE TABLE IF NOT EXISTS advance_payments (
    advance_id          INT PRIMARY KEY AUTO_INCREMENT,
    customer_id         INT NOT NULL,
    or_number           VARCHAR(50) UNIQUE NOT NULL,
    payment_date        DATE NOT NULL,
    amount              DECIMAL(10,2) NOT NULL,
    payment_method      ENUM('cash','check','online','others') DEFAULT 'cash',
    cashier_id          INT,
    remarks             TEXT,
    applied_billing_id  INT DEFAULT NULL,
    applied_at          TIMESTAMP NULL DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id)        REFERENCES customers(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (cashier_id)         REFERENCES users(user_id)         ON DELETE SET NULL,
    FOREIGN KEY (applied_billing_id) REFERENCES billings(billing_id)   ON DELETE SET NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_applied  (applied_billing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($_SERVER['REQUEST_METHOD']=='POST'&&isset($_POST['action'])&&$_POST['action']=='record_payment'){
    $billing_id=intval($_POST['billing_id']);$customer_id=intval($_POST['customer_id']);
    $or_number=sanitize_input($_POST['or_number']);$payment_date=sanitize_input($_POST['payment_date']);
    $amount_paid=floatval($_POST['amount_paid']);$payment_method=sanitize_input($_POST['payment_method']);
    $remarks=sanitize_input($_POST['remarks']);
    $conn->begin_transaction();
    try{
        $stmt=$conn->prepare("INSERT INTO payments (billing_id,customer_id,or_number,payment_date,amount_paid,payment_method,cashier_id,remarks) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iissdsis",$billing_id,$customer_id,$or_number,$payment_date,$amount_paid,$payment_method,$_SESSION['user_id'],$remarks);
        $stmt->execute();$payment_id=$stmt->insert_id;$stmt->close();
        $result=$conn->query("SELECT net_amount,(SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE billing_id=$billing_id) as total_paid FROM billings WHERE billing_id=$billing_id");
        $billing=$result->fetch_assoc();
        $new_status=$billing['total_paid']>=$billing['net_amount']?'paid':($billing['total_paid']>0?'partial':'unpaid');
        $conn->query("UPDATE billings SET status='$new_status' WHERE billing_id=$billing_id");
        log_activity($_SESSION['user_id'],'RECORD_PAYMENT','payments',$payment_id,"Recorded payment OR# $or_number - ₱".number_format($amount_paid,2));
        $conn->commit();$success="Payment recorded! OR# $or_number";
    }catch(Exception $e){$conn->rollback();$error="Error: ".$e->getMessage();}
}

/* Next OR — check both tables for highest number */
$or_rows=[];
$r1=$conn->query("SELECT or_number FROM payments        ORDER BY payment_id  DESC LIMIT 1");
$r2=$conn->query("SELECT or_number FROM advance_payments ORDER BY advance_id  DESC LIMIT 1");
if($r1->num_rows) $or_rows[]=$r1->fetch_assoc()['or_number'];
if($r2->num_rows) $or_rows[]=$r2->fetch_assoc()['or_number'];
// $next_or=count($or_rows)?'OR-'.str_pad(max(array_map(fn($v)=>intval(preg_replace('/\D/','',$v)),$or_rows))+1,3,'0',STR_PAD_LEFT):'OR-001';
$next_or = 'OR-' . date('Y') . '-' . str_pad(
    count($or_rows) ? 
    (int)preg_replace('/[^\d]/', '', max(array_map(fn($v) => substr($v, -3), $or_rows))) + 1 
    : 1, 
    3, '0', STR_PAD_LEFT
);
/* Advance payment POST handler */
if ($_SERVER['REQUEST_METHOD']=='POST'&&isset($_POST['action'])&&$_POST['action']=='record_advance'){
    $customer_id    = intval($_POST['customer_id']);
    $or_number      = sanitize_input($_POST['or_number']);
    $payment_date   = sanitize_input($_POST['payment_date']);
    $amount         = floatval($_POST['amount_paid']);
    $payment_method = sanitize_input($_POST['payment_method']);
    $remarks        = sanitize_input($_POST['remarks']??'');
    if($amount<=0){ $error="Amount must be greater than zero."; }
    else {
        $stmt=$conn->prepare("INSERT INTO advance_payments (customer_id,or_number,payment_date,amount,payment_method,cashier_id,remarks) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("issdsis",$customer_id,$or_number,$payment_date,$amount,$payment_method,$_SESSION['user_id'],$remarks);
        if($stmt->execute()){
            log_activity($_SESSION['user_id'],'ADVANCE_PAYMENT','advance_payments',$stmt->insert_id,"Advance OR# $or_number - ".number_format($amount,2)." cust#$customer_id");
            $success="Advance payment saved! OR# $or_number &mdash; <strong>&#8369;".number_format($amount,2)."</strong> credit stored. Auto-applies when billing is generated.";
        } else { $error="Error: ".$conn->error; }
        $stmt->close();
    }
}

$report_date=isset($_GET['report_date'])?sanitize_input($_GET['report_date']):date('Y-m-d');
$recent_payments=$conn->query("SELECT p.*,c.subscriber_name,c.account_number,b.billing_month,b.billing_year,u.full_name as cashier_name FROM payments p JOIN customers c ON p.customer_id=c.customer_id JOIN billings b ON p.billing_id=b.billing_id LEFT JOIN users u ON p.cashier_id=u.user_id ORDER BY p.payment_date DESC,p.created_at DESC LIMIT 50");
$daily_payments=$conn->query("SELECT p.*,c.subscriber_name,c.account_number,b.billing_month,b.billing_year,u.full_name as cashier_name FROM payments p JOIN customers c ON p.customer_id=c.customer_id JOIN billings b ON p.billing_id=b.billing_id LEFT JOIN users u ON p.cashier_id=u.user_id WHERE p.payment_date='$report_date' ORDER BY p.created_at ASC");
$daily_total=$conn->query("SELECT COALESCE(SUM(amount_paid),0) as total FROM payments WHERE payment_date='$report_date'")->fetch_assoc()['total'];
$daily_count=$conn->query("SELECT COUNT(*) as cnt FROM payments WHERE payment_date='$report_date'")->fetch_assoc()['cnt'];
$daily_advance=$conn->query("SELECT ap.*,c.subscriber_name,c.account_number,u.full_name as cashier_name FROM advance_payments ap JOIN customers c ON ap.customer_id=c.customer_id LEFT JOIN users u ON ap.cashier_id=u.user_id WHERE ap.payment_date='$report_date' ORDER BY ap.created_at ASC");
$advance_total=$conn->query("SELECT COALESCE(SUM(amount),0) as t FROM advance_payments WHERE payment_date='$report_date'")->fetch_assoc()['t'];
$advance_count=$conn->query("SELECT COUNT(*) as c FROM advance_payments WHERE payment_date='$report_date'")->fetch_assoc()['c'];
$pending_advances=$conn->query("SELECT ap.advance_id,ap.or_number,ap.payment_date,ap.amount,ap.remarks,c.subscriber_name,c.account_number FROM advance_payments ap JOIN customers c ON ap.customer_id=c.customer_id WHERE ap.applied_billing_id IS NULL ORDER BY ap.payment_date ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments - AR NOVALINK</title>
<link rel="shortcut icon" type="x-icon" href="images/logo.jpg">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header no-print">
            <div>
            <h1>Payment Processing</h1><p>Record payments and generate daily reports</p></div>
            </div>

        <!-- Print header for daily report -->
        <div class="print-header">
            <img src="images/headerlogo.png" alt="NovaLink" class="print-header-logo">
            <h2>DAILY PAYMENT REPORT</h2>
            <h3><?php echo date('F d, Y', strtotime($report_date)); ?></h3>
            <p class="text-muted">Generated: <?php echo date('F d, Y h:i A'); ?></p>
        </div>

        <?php if(isset($success)):?><div class="alert alert-success no-print"><?php echo $success;?></div><?php endif;?>
        <?php if(isset($error)):?><div class="alert alert-error no-print"><?php echo $error;?></div><?php endif;?>

        <!-- Record Payment Form (hidden on print) -->
        <div class="widget mb-3 no-print">
            <div class="widget-header">
                <h2 id="payment-form-title">Record New Payment</h2>
                <div class="payment-mode-tabs">
                    <button class="btn btn-secondary btn-sm" id="tab-regular" onclick="switchPayTab('regular')">Regular Payment</button>
                    <button class="btn btn-sm btn-info" id="tab-advance" onclick="switchPayTab('advance')">Advance Payment</button>
                </div>
            </div>
            <div class="widget-content">
                <!-- <div class="or-number-banner">
                    <div><span class="or-label">Official Receipt Number</span></div>
                    <div class="or-value"><?php echo $next_or; ?></div>
                </div> -->
                <!-- <div id="tab-desc-regular" class="tab-desc">Pay against an existing billing period.</div> -->
                <div id="tab-desc-advance" class="tab-desc" style="display:none;"><span class="advance-badge">Credit</span> Subscriber pays before billing is generated. Credit auto-applies when billing is generated.</div>

                <form method="POST" id="regular-form">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="or_number" value="<?php echo $next_or;?>">
                    <div class="form-row">
                        <div class="form-group"><label>OR Number</label><input type="text" class="or-number-field" value="<?php echo $next_or;?>" readonly></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Search Subscriber *</label>
                            <input type="text" id="search_customer" placeholder="Type to search..." autocomplete="off">
                            <div id="customer_results" class="position-relative"></div>
                            <input type="hidden" id="customer_id" name="customer_id" required>
                            <div id="selected_customer" class="d-none payment-selected-box mt-1"><strong>Selected:</strong> <span id="selected_customer_name"></span></div>
                        </div>
                        <div class="form-group"><label>Billing Period *</label>
                            <select id="billing_period" name="billing_id" required disabled><option value="">Select subscriber first</option></select>
                            <div id="billing_info" class="validation-msg"></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Payment Date *</label><input type="date" name="payment_date" value="<?php echo date('Y-m-d');?>" required></div>
                        <div class="form-group"><label>Amount Paid (&#8369;) *</label><input type="number" step="0.01" name="amount_paid" id="amount_paid" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Payment Method *</label><select name="payment_method" required><option value="cash">Cash</option><option value="check">Check</option><option value="online">Online</option><option value="others">Others</option></select></div>
                        <div class="form-group"><label>Remarks</label><textarea name="remarks" rows="2"></textarea></div>
                    </div>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </form>

                <!-- Advance Payment Form -->
                <form method="POST" id="advance-form" style="display:none;">
                    <input type="hidden" name="action" value="record_advance">
                    <input type="hidden" name="or_number" value="<?php echo $next_or;?>">
                    <div class="advance-info-box">
                        <strong>How it works:</strong> Payment is stored as a credit against the subscriber's account.
                        When the admin generates next month's billing, the credit automatically reduces their balance.
                        Any leftover credit carries over to the following month.
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>OR Number</label><input type="text" class="or-number-field" value="<?php echo $next_or;?>" readonly></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Search Subscriber *</label>
                            <input type="text" id="adv_search_customer" placeholder="Type to search..." autocomplete="off">
                            <div id="adv_customer_results" class="position-relative"></div>
                            <input type="hidden" id="adv_customer_id" name="customer_id" required>
                            <div id="adv_selected_customer" class="d-none payment-selected-box mt-1">
                                <strong>Selected:</strong> <span id="adv_selected_name"></span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Existing Advance Credit</label>
                            <div id="adv_credit_info" class="validation-msg" style="padding:10px 0;">
                                <em style="color:var(--dark-gray);">Select a subscriber to check existing credits.</em>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Payment Date *</label><input type="date" name="payment_date" value="<?php echo date('Y-m-d');?>" required></div>
                        <div class="form-group"><label>Amount (&#8369;) *</label><input type="number" step="0.01" name="amount_paid" id="adv_amount" required min="0.01"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Payment Method *</label>
                            <select name="payment_method" required>
                                <option value="cash">Cash</option><option value="check">Check</option>
                                <option value="online">Online</option><option value="others">Others</option>
                            </select></div>
                        <div class="form-group"><label>Remarks</label>
                            <textarea name="remarks" rows="2" placeholder="e.g., Advance for July 2025"></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-advance">Save Advance Payment</button>
                </form>
            </div>
        </div>

        <!-- Pending Advance Credits -->
        <?php if(isset($pending_advances) && $pending_advances && $pending_advances->num_rows>0): ?>
        <div class="widget mb-3 no-print">
            <div class="widget-header">
                <h2>&#9203; Pending Advance Credits</h2>
                <span class="badge badge-warning"><?php echo $pending_advances->num_rows;?> unapplied</span>
            </div>
            <div class="widget-content" style="padding:0;">
                <table>
                    <thead><tr><th>OR #</th><th>Subscriber</th><th>Account #</th><th>Date Paid</th><th>Credit Amount</th><th>Remarks</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php while($ap=$pending_advances->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ap['or_number']);?></td>
                        <td><?php echo htmlspecialchars($ap['subscriber_name']);?></td>
                        <td><?php echo htmlspecialchars($ap['account_number']);?></td>
                        <td><?php echo date('M d, Y',strtotime($ap['payment_date']));?></td>
                        <td><strong class="ledger-balance-positive"><?php echo format_currency($ap['amount']);?></strong></td>
                        <td><?php echo htmlspecialchars($ap['remarks']??'—');?></td>
                        <td><span class="badge badge-warning">Waiting for billing</span></td>
                    </tr>
                    <?php endwhile;?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif;?>

        <!-- Daily Payment Report (this section prints) -->
        <div class="widget mb-3">
            <div class="widget-header">
                <h2>Daily Payment Report</h2>
                <div class="d-flex gap-10 no-print">
                    <form method="GET" class="d-flex gap-10"><input type="date" name="report_date" value="<?php echo $report_date;?>"><button type="submit" class="btn btn-secondary btn-sm">View</button></form>
                    <button onclick="window.print()" class="btn btn-primary btn-sm">🖨️ Print</button>
                </div>
            </div>
            <div class="widget-content">
                <!-- Summary line (hidden on print — the print-header covers it) -->
                <div class="form-row mb-2 no-print">
                    <div><strong>Date:</strong> <?php echo date('F d, Y',strtotime($report_date));?></div>
                    <div><strong>Regular:</strong>  <?php echo format_currency($daily_total);?></div>
                    <div><strong>Advance:</strong>  <?php echo format_currency(isset($advance_total)?$advance_total:0);?></div>
                    <div><strong>Total:</strong> <?php echo format_currency($daily_total+(isset($advance_total)?$advance_total:0));?></div>
                </div>
                <table>
                    <thead><tr><th>OR #</th><th>Subscriber</th><th>Account #</th><th>Period</th><th>Amount</th><th>Method</th><th>Cashier</th></tr></thead>
                    <tbody>
                    <?php if($daily_payments->num_rows>0):while($dp=$daily_payments->fetch_assoc()):?>
                    <tr>
                        <td><?php echo htmlspecialchars($dp['or_number']);?></td>
                        <td><?php echo htmlspecialchars($dp['subscriber_name']);?></td>
                        <td><?php echo htmlspecialchars($dp['account_number']);?></td>
                        <td><?php echo get_month_name($dp['billing_month']).' '.$dp['billing_year'];?></td>
                        <td><?php echo format_currency($dp['amount_paid']);?></td>
                        <td><?php echo ucfirst($dp['payment_method']);?></td>
                        <td><?php echo htmlspecialchars($dp['cashier_name']??'N/A');?></td>
                    </tr>
                    <?php endwhile;else:?>
                    <tr><td colspan="7" class="text-center">No payments for this date</td></tr>
                    <?php endif;?>
                    <?php if(isset($daily_advance)&&$daily_advance&&$daily_advance->num_rows>0): while($da=$daily_advance->fetch_assoc()): ?>
                    <tr class="advance-row">
                        <td><?php echo htmlspecialchars($da['or_number']);?></td>
                        <td><?php echo htmlspecialchars($da['subscriber_name']);?></td>
                        <td><?php echo htmlspecialchars($da['account_number']);?></td>
                        <td><span class="badge badge-info">Advance Credit</span></td>
                        <td><?php echo format_currency($da['amount']);?></td>
                        <td><?php echo ucfirst($da['payment_method']);?></td>
                        <td><?php echo htmlspecialchars($da['cashier_name']??'N/A');?></td>
                    </tr>
                    <?php endwhile; endif;?>
                    <tr class="table-row-total">
                        <td colspan="4" class="text-right">TOTAL COLLECTIONS:</td>
                        <td><?php echo format_currency($daily_total+(isset($advance_total)?$advance_total:0));?></td>
                        <td colspan="2"><?php echo $daily_count+(isset($advance_count)?$advance_count:0);?> payment(s)</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Payments (hidden on print) -->
        <div class="table-container no-print">
            <div class="table-header"><h2>Recent Payments</h2></div>
            <table>
                <thead><tr><th>OR #</th><th>Date</th><th>Subscriber</th><th>Account #</th><th>Period</th><th>Amount</th><th>Method</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if($recent_payments->num_rows>0):while($row=$recent_payments->fetch_assoc()):?>
                <tr>
                    <td><?php echo htmlspecialchars($row['or_number']);?></td>
                    <td><?php echo date('M d, Y',strtotime($row['payment_date']));?></td>
                    <td><?php echo htmlspecialchars($row['subscriber_name']);?></td>
                    <td><?php echo htmlspecialchars($row['account_number']);?></td>
                    <td><?php echo get_month_name($row['billing_month']).' '.$row['billing_year'];?></td>
                    <td><?php echo format_currency($row['amount_paid']);?></td>
                    <td><?php echo ucfirst($row['payment_method']);?></td>
                    <td class="actions-cell">
                        <a href="print_invoice.php?id=<?php echo $row['payment_id'];?>" target="_blank" class="btn btn-sm btn-primary">Invoice</a>
                        <!-- <a href="print_receipt.php?id=<?php echo $row['payment_id'];?>" target="_blank" class="btn btn-sm btn-secondary">Receipt</a> -->
                    </td>
                </tr>
                <?php endwhile;else:?>
                <tr><td colspan="8" class="text-center">No payments</td></tr>
                <?php endif;?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<script src="js/script.js"></script>
<script src="js/payment.js"></script>
<script>
function switchPayTab(tab) {
    var adv = (tab==='advance');
    document.getElementById('regular-form').style.display     = adv?'none':'';
    document.getElementById('advance-form').style.display     = adv?'':'none';
    document.getElementById('tab-desc-regular').style.display = adv?'none':'';
    document.getElementById('tab-desc-advance').style.display = adv?'':'none';
    document.getElementById('tab-regular').classList.toggle('active',!adv);
    document.getElementById('tab-advance').classList.toggle('active', adv);
    document.getElementById('payment-form-title').textContent = adv?'Record Advance Payment':'Record New Payment';
}
/* ── Advance form subscriber search (mirrors regular form behaviour) ── */
document.addEventListener('DOMContentLoaded', function() {
    var advSearch   = document.getElementById('adv_search_customer');
    var advResults  = document.getElementById('adv_customer_results');
    var advCid      = document.getElementById('adv_customer_id');
    var advSel      = document.getElementById('adv_selected_customer');
    var advName     = document.getElementById('adv_selected_name');
    var advCredit   = document.getElementById('adv_credit_info');
    if (!advSearch) return;

    function renderAdvList(list, showAll) {
        if (!list.length) {
            advResults.innerHTML = '<div style="padding:10px;background:#f8f9fa;border:1px solid #dee2e6;margin-top:5px;border-radius:5px;">No subscribers found</div>';
            return;
        }
        var display = showAll ? list.slice(0,10) : list;
        var html = '<div style="position:absolute;width:100%;background:white;border:2px solid var(--primary-color);max-height:300px;overflow-y:auto;z-index:1000;margin-top:5px;box-shadow:0 4px 8px rgba(0,0,0,0.15);border-radius:5px;">';
        display.forEach(function(c) {
            html += '<div class="customer-result-item" data-id="'+c.customer_id+'" data-name="'+c.subscriber_name+'" data-account="'+c.account_number+'"'
                  + ' style="padding:12px 15px;cursor:pointer;border-bottom:1px solid #e9ecef;transition:background 0.2s;"'
                  + ' onmouseover="this.style.background=\'#e7f3ff\'" onmouseout="this.style.background=\'white\'">'
                  + '<div style="display:flex;justify-content:space-between;align-items:center;">'
                  + '<div><strong style="color:var(--primary-color);font-size:14px;">'+c.subscriber_name+'</strong><br>'
                  + '<small style="color:#6c757d;"><strong>Account:</strong> '+c.account_number+' | <strong>Area:</strong> '+(c.area_name||'N/A')+' | <strong>Monthly:</strong> &#8369;'+parseFloat(c.monthly_fee).toFixed(2)+'</small></div>'
                  + '<div style="background:var(--success-color);color:white;padding:4px 8px;border-radius:12px;font-size:11px;">Select</div>'
                  + '</div></div>';
        });
        if (showAll && list.length > 10) {
            html += '<div style="padding:10px;text-align:center;background:#f8f9fa;font-size:12px;color:#666;">Showing 10 of '+list.length+' subscribers. Type to search...</div>';
        }
        html += '</div>';
        advResults.innerHTML = html;
        advResults.querySelectorAll('.customer-result-item').forEach(function(item) {
            item.addEventListener('click', function() {
                var id      = this.getAttribute('data-id');
                var name    = this.getAttribute('data-name');
                var account = this.getAttribute('data-account');
                advSearch.value = name + ' (' + account + ')';
                advCid.value    = id;
                advResults.innerHTML = '';
                advName.textContent  = name + ' — ' + account;
                advSel.classList.remove('d-none');
                /* Load existing advance credit for this subscriber */
                if (advCredit) {
                    advCredit.innerHTML = '<em>Checking existing credit...</em>';
                    fetch('ajax/get_customer_billings.php?customer_id=' + id)
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            var credit = Array.isArray(data) ? 0 : (parseFloat(data.advance_credit) || 0);
                            if (credit > 0) {
                                advCredit.innerHTML = '<span style="color:var(--info-color);font-weight:600;">&#9432; Existing credit: <strong>&#8369;' + credit.toFixed(2) + '</strong> — new payment will stack on top.</span>';
                            } else {
                                advCredit.innerHTML = '<span style="color:var(--success-color);">&#10003; No existing advance credit.</span>';
                            }
                        })
                        .catch(function() { advCredit.innerHTML = ''; });
                }
            });
        });
    }

    function advLoadAll() {
        fetch('ajax/search_customers.php?q=')
            .then(function(r){return r.json();})
            .then(function(list){ renderAdvList(list, true); })
            .catch(function(){});
    }

    advSearch.addEventListener('focus', function() {
        if (!this.value.trim()) advLoadAll();
    });

    var advTimer;
    advSearch.addEventListener('input', function() {
        clearTimeout(advTimer);
        var q = this.value.trim();
        if (!q) {
            advLoadAll();
            advCid.value = '';
            advSel.classList.add('d-none');
            if (advCredit) advCredit.innerHTML = '<em style="color:var(--dark-gray);">Select a subscriber to check existing credits.</em>';
            return;
        }
        advTimer = setTimeout(function() {
            fetch('ajax/search_customers.php?q='+encodeURIComponent(q))
                .then(function(r){return r.json();})
                .then(function(list){ renderAdvList(list, false); })
                .catch(function(){});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!advSearch.contains(e.target) && !advResults.contains(e.target)) {
            setTimeout(function(){ advResults.innerHTML=''; }, 200);
        }
    });

    document.getElementById('advance-form').addEventListener('submit', function(e) {
        if (!advCid.value) {
            e.preventDefault();
            alert('Please select a subscriber first.');
            advSearch.focus();
        }
    });
});
</script>
<?php include "includes/footer.php"; ?>
</body>
</html>
<?php $conn->close(); ?>