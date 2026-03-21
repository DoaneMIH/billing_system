<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$conn = getDBConnection();

/* ── Handle POST actions ── */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_SESSION['role'] == 'admin') {

    if ($_POST['action'] == 'add') {
        $r = $conn->query("SELECT account_number FROM customers ORDER BY customer_id DESC LIMIT 1");
        if ($r->num_rows > 0) {
            $last = intval(substr($r->fetch_assoc()['account_number'], 4));
            $account_number = 'ACC-' . str_pad($last + 1, 3, '0', STR_PAD_LEFT);
        } else { $account_number = 'ACC-001'; }

        $first_name  = sanitize_input($_POST['first_name']);
        $middle_name = sanitize_input($_POST['middle_name'] ?? '');
        $last_name   = sanitize_input($_POST['last_name']);
        $subscriber_name = trim("$last_name, $first_name" . ($middle_name ? " $middle_name" : ''));
        $address     = sanitize_input($_POST['address']);
        $area_id     = intval($_POST['area_id']);
        $tel_no      = sanitize_input($_POST['tel_no']);
        $package_id  = intval($_POST['package_id']);
        $monthly_fee = floatval($_POST['monthly_fee']);
        $installation_date = sanitize_input($_POST['installation_date']);
        $init_status = sanitize_input($_POST['initial_status'] ?? 'active');

        if (strlen(preg_replace('/\D/', '', $tel_no)) !== 11) {
            $error = "Contact number must be exactly 11 digits.";
        } else {
            $stmt = $conn->prepare("INSERT INTO customers (account_number, subscriber_name, first_name, middle_name, last_name, address, area_id, tel_no, package_id, monthly_fee, installation_date, date_connected, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssssissdsss", $account_number, $subscriber_name, $first_name, $middle_name, $last_name, $address, $area_id, $tel_no, $package_id, $monthly_fee, $installation_date, $installation_date, $init_status);
            if ($stmt->execute()) {
                $new_cid = $stmt->insert_id;
                $uid=$_SESSION['user_id'];$nd=date('Y-m-d');$nt=date('H:i:s');
                $conn->query("INSERT INTO customer_status_log (customer_id,old_status,new_status,changed_by,change_date,change_time,remarks) VALUES ($new_cid,'','$init_status',$uid,'$nd','$nt','Initial subscriber creation')");
                log_activity($uid,'ADD_CUSTOMER','customers',$new_cid,"Added subscriber: $subscriber_name ($account_number)");
                $success = "Subscriber added! Account: $account_number";
            } else { $error = "Error: " . $conn->error; }
            $stmt->close();
        }
    }
    elseif ($_POST['action'] == 'disconnect') {
        $cid=intval($_POST['customer_id']);
        $old=$conn->query("SELECT status,subscriber_name FROM customers WHERE customer_id=$cid")->fetch_assoc();
        $nd=date('Y-m-d');$nt=date('H:i:s');$uid=$_SESSION['user_id'];
        $conn->query("UPDATE customers SET status='disconnected',disconnection_date='$nd' WHERE customer_id=$cid");
        $conn->query("INSERT INTO customer_status_log (customer_id,old_status,new_status,changed_by,change_date,change_time,remarks) VALUES ($cid,'{$old['status']}','disconnected',$uid,'$nd','$nt','Disconnected by ".$conn->real_escape_string($_SESSION['full_name'])."')");
        log_activity($uid,'DISCONNECT_CUSTOMER','customers',$cid,"Disconnected: {$old['subscriber_name']} on $nd $nt");
        $success="Subscriber disconnected on ".date('M d, Y h:i A')."!";
    }
    elseif ($_POST['action'] == 'reconnect') {
        $cid=intval($_POST['customer_id']);
        $old=$conn->query("SELECT status,subscriber_name FROM customers WHERE customer_id=$cid")->fetch_assoc();
        $nd=date('Y-m-d');$nt=date('H:i:s');$uid=$_SESSION['user_id'];
        $conn->query("UPDATE customers SET status='active',disconnection_date=NULL WHERE customer_id=$cid");
        $conn->query("INSERT INTO customer_status_log (customer_id,old_status,new_status,changed_by,change_date,change_time,remarks) VALUES ($cid,'{$old['status']}','active',$uid,'$nd','$nt','Reconnected by ".$conn->real_escape_string($_SESSION['full_name'])."')");
        log_activity($uid,'RECONNECT_CUSTOMER','customers',$cid,"Reconnected: {$old['subscriber_name']} on $nd $nt");
        $success="Subscriber reconnected!";
    }
    elseif ($_POST['action'] == 'confirm_installation') {
        $cid=intval($_POST['customer_id']);
        $old=$conn->query("SELECT status,subscriber_name FROM customers WHERE customer_id=$cid")->fetch_assoc();
        $nd=date('Y-m-d');$nt=date('H:i:s');$uid=$_SESSION['user_id'];
        $conn->query("UPDATE customers SET status='active',date_connected='$nd',installation_date='$nd' WHERE customer_id=$cid");
        $conn->query("INSERT INTO customer_status_log (customer_id,old_status,new_status,changed_by,change_date,change_time,remarks) VALUES ($cid,'{$old['status']}','active',$uid,'$nd','$nt','Installation confirmed by ".$conn->real_escape_string($_SESSION['full_name'])."')");
        log_activity($uid,'CONFIRM_INSTALLATION','customers',$cid,"Installation confirmed: {$old['subscriber_name']}");
        $success="Installation confirmed! Subscriber is now Active.";
    }
    elseif ($_POST['action'] == 'upload_sketch') {
        $cid=intval($_POST['customer_id']); $remarks=sanitize_input($_POST['sketch_remarks']??'');
        if(!empty($_POST['sketch_data'])){
            $sd=$_POST['sketch_data'];
            $stmt=$conn->prepare("INSERT INTO installation_sketches (customer_id,sketch_type,sketch_data,remarks,created_by) VALUES (?,'drawing',?,?,?)");
            $stmt->bind_param("issi",$cid,$sd,$remarks,$_SESSION['user_id']);
            if($stmt->execute()) $success="Sketch saved!"; $stmt->close();
        } elseif(isset($_FILES['sketch_file'])&&$_FILES['sketch_file']['error']==0){
            $dir='uploads/sketches/'; if(!is_dir($dir)) mkdir($dir,0755,true);
            $fn='sketch_'.$cid.'_'.time().'.'.pathinfo($_FILES['sketch_file']['name'],PATHINFO_EXTENSION);
            $fp=$dir.$fn;
            if(move_uploaded_file($_FILES['sketch_file']['tmp_name'],$fp)){
                $stmt=$conn->prepare("INSERT INTO installation_sketches (customer_id,sketch_type,file_path,remarks,created_by) VALUES (?,'upload',?,?,?)");
                $stmt->bind_param("issi",$cid,$fp,$remarks,$_SESSION['user_id']);
                if($stmt->execute()) $success="Sketch uploaded!"; $stmt->close();
            }
        }
    }
}

/* Next account number */
$nr=$conn->query("SELECT account_number FROM customers ORDER BY customer_id DESC LIMIT 1");
$next_acc=$nr->num_rows>0?'ACC-'.str_pad(intval(substr($nr->fetch_assoc()['account_number'],4))+1,3,'0',STR_PAD_LEFT):'ACC-001';

$areas=$conn->query("SELECT * FROM areas ORDER BY area_name");
$packages_list=$conn->query("SELECT * FROM packages WHERE status='active' ORDER BY package_name");
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Subscribers - AR NOVALINK</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header"><div><h1>Subscriber Management</h1><p>Manage subscriber accounts and subscriptions</p></div></div>
        <?php if(isset($success)):?><div class="alert alert-success"><?php echo $success;?></div><?php endif;?>
        <?php if(isset($error)):?><div class="alert alert-error"><?php echo $error;?></div><?php endif;?>

        <div class="table-container">
            <div class="table-header">
                <h2>All Subscribers</h2>
                <?php if($_SESSION['role']=='admin'):?>
                <button onclick="document.getElementById('addSubscriberModal').classList.add('show')" class="btn btn-primary">+ Add Subscriber</button>
                <?php endif;?>
            </div>
            <div class="table-actions-bar">
                <div class="filter-group">
                    <input type="text" id="live-search" placeholder="Search subscribers..." autocomplete="off" class="search-cus">
                    <select id="area-filter"><option value="0">All Areas</option>
                        <?php $areas->data_seek(0);while($a=$areas->fetch_assoc()):?><option value="<?php echo $a['area_id'];?>"><?php echo htmlspecialchars($a['area_name']);?></option><?php endwhile;?></select>
                    <select id="status-filter"><option value="">All Status</option>
                        <option value="active">Active</option><option value="disconnected">Disconnected</option>
                        <option value="reconnected">Reconnected</option><option value="pending_installation">Pending Installation</option>
                        <option value="hold_disconnection">Hold Disconnection</option></select>
                    <button onclick="clearAllFilters()" class="btn btn-secondary btn-sm">Clear</button>
                </div>
            </div>
            <div id="customer-table-container">
                <div class="text-center p-2">Loading...</div>
            </div>
        </div>
    </main>
</div>

<!-- ═══ ADD SUBSCRIBER MODAL ═══ -->
<div id="addSubscriberModal" class="modal">
    <div class="modal-content" style="max-width:700px;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);max-height:90vh;overflow-y:auto;">
        <div class="modal-header"><h2>Add New Subscriber</h2><button class="modal-close" onclick="this.closest('.modal').classList.remove('show')">&times;</button></div>
        <form method="POST" id="addSubForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group"><label>Account Number</label><input type="text" class="auto-field" value="<?php echo $next_acc;?>" readonly></div>
                <div class="form-row-3">
                    <div class="form-group"><label>First Name *</label><input type="text" name="first_name" required placeholder="Juan"></div>
                    <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" placeholder="Reyes"></div>
                    <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" required placeholder="Dela Cruz"></div>
                </div>
                <div class="form-group"><label>Complete Address *</label><textarea name="address" required rows="2" placeholder="e.g., 123 Main St, Brgy. Luz, Cebu City, Cebu, 6000"></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label>Area *</label>
                        <select name="area_id" required><option value="">Select Area</option>
                        <?php $areas->data_seek(0);while($a=$areas->fetch_assoc()):?><option value="<?php echo $a['area_id'];?>"><?php echo htmlspecialchars($a['area_name']);?></option><?php endwhile;?></select></div>
                    <div class="form-group"><label>Contact Number *</label>
                        <div class="input-wrap"><input type="text" name="tel_no" id="phoneInput" required maxlength="11" placeholder="09XXXXXXXXX" oninput="validatePhone(this)"><span class="validation-icon" id="phoneIcon"></span></div>
                        <div class="validation-msg" id="phoneMsg"></div></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Package *</label>
                        <select name="package_id" id="pkgSelect" required onchange="var f=this.options[this.selectedIndex].dataset.fee;if(f)document.getElementById('feeInput').value=f;"><option value="">Select Package</option>
                        <?php $packages_list->data_seek(0);while($pk=$packages_list->fetch_assoc()):?><option value="<?php echo $pk['package_id'];?>" data-fee="<?php echo $pk['monthly_fee'];?>"><?php echo htmlspecialchars($pk['package_name']);?> - <?php echo $pk['bandwidth_mbps'];?> Mbps</option><?php endwhile;?></select></div>
                    <div class="form-group"><label>Monthly Fee (₱) *</label><input type="number" step="0.01" id="feeInput" name="monthly_fee" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Installation Date *</label><input type="date" name="installation_date" value="<?php echo date('Y-m-d');?>" required></div>
                    <div class="form-group"><label>Initial Status</label><select name="initial_status"><option value="active">Active</option><option value="pending_installation">Pending Installation</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="this.closest('.modal').classList.remove('show')">Cancel</button><button type="submit" class="btn btn-primary">Add Subscriber</button></div>
        </form>
    </div>
</div>

<!-- ═══ SKETCH MODAL ═══ -->
<div id="sketchModal" class="modal">
    <div class="modal-content modal-md modal-centered">
        <div class="modal-header"><h2>Installation Sketch / Photo</h2><button class="modal-close" onclick="this.closest('.modal').classList.remove('show')">&times;</button></div>
        <form method="POST" enctype="multipart/form-data"><div class="modal-body">
            <input type="hidden" name="action" value="upload_sketch">
            <input type="hidden" id="sketch_customer_id" name="customer_id">
            <div class="form-group"><label>Upload Photo/Sketch</label><input type="file" name="sketch_file" accept="image/*"></div>
            <div class="form-group"><label>Or Draw:</label>
                <canvas id="sketchCanvas" width="500" height="300"></canvas>
                <input type="hidden" id="sketch_data" name="sketch_data">
                <div class="mt-1"><button type="button" onclick="clearCanvas()" class="btn btn-sm btn-secondary">Clear</button> <button type="button" onclick="saveCanvas()" class="btn btn-sm btn-primary">Save Drawing</button></div>
            </div>
            <div class="form-group"><label>Remarks</label><textarea name="sketch_remarks" rows="2"></textarea></div>
        </div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="this.closest('.modal').classList.remove('show')">Cancel</button><button type="submit" class="btn btn-primary">Upload</button></div></form>
    </div>
</div>

<script src="js/script.js"></script>
<script>
const isAdmin = <?php echo $_SESSION['role'] == 'admin' ? 'true' : 'false'; ?>;
const searchInput = document.getElementById('live-search');
const areaFilter = document.getElementById('area-filter');
const statusFilter = document.getElementById('status-filter');
const tableContainer = document.getElementById('customer-table-container');

/* ── Single AJAX search — no page redirect, text stays in input ── */
let debounceTimer;
searchInput.addEventListener('input', function() { clearTimeout(debounceTimer); debounceTimer = setTimeout(loadCustomers, 300); });
areaFilter.addEventListener('change', loadCustomers);
statusFilter.addEventListener('change', loadCustomers);

function loadCustomers() {
    const q = searchInput.value.trim();
    const area = areaFilter.value;
    const status = statusFilter.value;
    fetch('ajax/search_customers.php?q=' + encodeURIComponent(q) + '&area=' + area + '&status=' + status)
        .then(function(r) { return r.json(); })
        .then(function(customers) { displayCustomers(customers, q); })
        .catch(function() { tableContainer.innerHTML = '<div class="text-center p-2" style="color:red;">Error loading subscribers</div>'; });
}

function displayCustomers(customers, query) {
    if (customers.length === 0) {
        tableContainer.innerHTML = '<div class="text-center p-2" style="color:#999;">No subscribers found</div>';
        return;
    }
    var html = '<table><thead><tr><th>Account #</th><th>Subscriber</th><th>Address</th><th>Package</th><th>Monthly Fee</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    customers.forEach(function(c) {
        var sc = c.status==='active'?'success':c.status==='disconnected'?'danger':c.status==='reconnected'?'info':c.status==='pending_installation'?'warning':'secondary';
        var name = c.subscriber_name || ((c.last_name||'') + ', ' + (c.first_name||''));
        html += '<tr>';
        html += '<td>' + hl(c.account_number, query) + '</td>';
        html += '<td><strong>' + hl(name, query) + '</strong></td>';
        html += '<td>' + hl(c.address||'', query) + '</td>';
        // html += '<td>' + (c.area_name||'N/A') + '</td>';
        html += '<td>' + (c.package_name||'N/A') + '</td>';
        html += '<td>₱' + parseFloat(c.monthly_fee||0).toFixed(2) + '</td>';
        html += '<td><span class="badge badge-' + sc + '">' + c.status.replace(/_/g,' ') + '</span></td>';
        html += '<td class="actions-cell">';
        html += '<a href="customer_ledger.php?id=' + c.customer_id + '" class="btn btn-sm btn-primary">Ledger</a> ';
        // html += '<a href="print_installation.php?id=' + c.customer_id + '" target="_blank" class="btn btn-sm btn-secondary">Install Form</a> ';
        if (isAdmin) {
            if (c.status==='pending_installation') html += '<button onclick="postAction(' + c.customer_id + ',\'confirm_installation\')" class="btn btn-sm btn-success">✅ Done</button> ';
            if (c.status==='active'||c.status==='reconnected') html += '<button onclick="postAction(' + c.customer_id + ',\'disconnect\')" class="btn btn-sm btn-danger">Disconnect</button> ';
            else if (c.status==='disconnected'||c.status==='hold_disconnection') html += '<button onclick="postAction(' + c.customer_id + ',\'reconnect\')" class="btn btn-sm btn-success">Reconnect</button> ';
            html += '<button onclick="openSketchModal(' + c.customer_id + ')" class="btn btn-sm btn-secondary">Sketch</button>';
        }
        html += '</td></tr>';
    });
    html += '</tbody></table>';
    tableContainer.innerHTML = html;
}

function hl(text, q) {
    if (!text || !q) return text || '';
    var safe = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return text.replace(new RegExp('(' + safe + ')', 'gi'), '<mark>$1</mark>');
}

function clearAllFilters() {
    searchInput.value = '';
    areaFilter.value = '0';
    statusFilter.value = '';
    loadCustomers();
    searchInput.focus();
}

function postAction(id, action) {
    var msg = action==='confirm_installation' ? 'Confirm installation done?' : action==='disconnect' ? 'Disconnect this subscriber?' : 'Reconnect this subscriber?';
    if (!confirm(msg)) return;
    var f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = '<input type="hidden" name="action" value="' + action + '"><input type="hidden" name="customer_id" value="' + id + '">';
    document.body.appendChild(f);
    f.submit();
}

/* Phone validation */
function validatePhone(el) {
    var d = el.value.replace(/\D/g, ''); el.value = d;
    var ic = document.getElementById('phoneIcon'), mg = document.getElementById('phoneMsg');
    if (d.length === 11) { el.classList.remove('input-invalid'); el.classList.add('input-valid'); ic.textContent = '✓'; ic.className = 'validation-icon valid'; mg.textContent = 'Valid (11 digits)'; mg.className = 'validation-msg ok'; }
    else { el.classList.remove('input-valid'); el.classList.add('input-invalid'); ic.textContent = '✗'; ic.className = 'validation-icon invalid'; mg.textContent = 'Must be exactly 11 digits (' + d.length + '/11)'; mg.className = 'validation-msg error'; }
}
document.getElementById('addSubForm')?.addEventListener('submit', function(e) {
    var ph = document.getElementById('phoneInput').value.replace(/\D/g, '');
    if (ph.length !== 11) { e.preventDefault(); alert('Contact number must be exactly 11 digits.'); return false; }
});

/* Sketch canvas */
function openSketchModal(id) { document.getElementById('sketch_customer_id').value = id; document.getElementById('sketchModal').classList.add('show'); initCanvas(); }
var canvas, ctx, drawing = false;
function initCanvas() { canvas = document.getElementById('sketchCanvas'); ctx = canvas.getContext('2d'); ctx.strokeStyle = '#000'; ctx.lineWidth = 2; canvas.onmousedown = function(e) { drawing = true; ctx.beginPath(); ctx.moveTo(e.offsetX, e.offsetY); }; canvas.onmousemove = function(e) { if (drawing) { ctx.lineTo(e.offsetX, e.offsetY); ctx.stroke(); } }; canvas.onmouseup = function() { drawing = false; }; canvas.onmouseleave = function() { drawing = false; }; }
function clearCanvas() { if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height); }
function saveCanvas() { if (canvas) document.getElementById('sketch_data').value = canvas.toDataURL(); alert('Drawing saved!'); }

/* Load subscribers on page load */
loadCustomers();
</script>
</body>
</html>
<?php $conn->close(); ?>