<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($customer_id == 0) { header("Location: customers.php"); exit(); }
$conn = getDBConnection();

$stmt = $conn->prepare("SELECT c.*, a.area_name, p.package_name, p.bandwidth_mbps FROM customers c LEFT JOIN areas a ON c.area_id = a.area_id LEFT JOIN packages p ON c.package_id = p.package_id WHERE c.customer_id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$customer) { header("Location: customers.php"); exit(); }

/* ── Admin-only POST actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] === 'admin' && isset($_POST['action'])) {

    if ($_POST['action'] === 'edit_subscriber') {
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
        $uid = $_SESSION['user_id'];

        if (strlen(preg_replace('/\D/', '', $tel_no)) !== 11) {
            $edit_error = "Contact number must be exactly 11 digits.";
        } else {
            $stmt = $conn->prepare(
                "UPDATE customers SET
                    first_name=?, middle_name=?, last_name=?, subscriber_name=?,
                    address=?, area_id=?, tel_no=?, package_id=?,
                    monthly_fee=?, installation_date=?
                 WHERE customer_id=?"
            );
            $stmt->bind_param("sssssisissi",
                $first_name, $middle_name, $last_name, $subscriber_name,
                $address, $area_id, $tel_no, $package_id,
                $monthly_fee, $installation_date, $customer_id
            );
            if ($stmt->execute()) {
                log_activity($uid, 'EDIT_SUBSCRIBER', 'customers', $customer_id,
                    "Edited: {$customer['subscriber_name']} ({$customer['account_number']})");
                $edit_success = "Subscriber updated successfully.";
                /* Reload fresh customer data so the page reflects changes */
                $stmt2 = $conn->prepare("SELECT c.*, a.area_name, p.package_name, p.bandwidth_mbps FROM customers c LEFT JOIN areas a ON c.area_id = a.area_id LEFT JOIN packages p ON c.package_id = p.package_id WHERE c.customer_id = ?");
                $stmt2->bind_param("i", $customer_id);
                $stmt2->execute();
                $customer = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
            } else {
                $edit_error = "Update failed: " . $conn->error;
            }
            $stmt->close();
        }
    }

    elseif ($_POST['action'] === 'delete_subscriber') {
        $uid = $_SESSION['user_id'];
        $conn->begin_transaction();
        try {
            $conn->query("DELETE bf FROM billing_fees bf INNER JOIN billings b ON bf.billing_id = b.billing_id WHERE b.customer_id = $customer_id");
            $conn->query("DELETE FROM payments WHERE customer_id = $customer_id");
            $conn->query("DELETE FROM billings WHERE customer_id = $customer_id");
            $conn->query("DELETE FROM installation_sketches WHERE customer_id = $customer_id");
            $conn->query("DELETE FROM customer_status_log WHERE customer_id = $customer_id");
            $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $stmt->close();
            log_activity($uid, 'DELETE_SUBSCRIBER', 'customers', $customer_id,
                "Deleted: {$customer['subscriber_name']} ({$customer['account_number']})");
            $conn->commit();
            /* Redirect back to subscribers list after successful deletion */
            header("Location: customers.php?deleted=" . urlencode($customer['subscriber_name']));
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $delete_error = "Delete failed: " . $e->getMessage();
        }
    }
}

$last_payment = $conn->query("SELECT MAX(payment_date) as lp FROM payments WHERE customer_id = $customer_id")->fetch_assoc()['lp'];
$billings = $conn->query("SELECT b.*, (SELECT SUM(amount_paid) FROM payments WHERE billing_id = b.billing_id) as total_paid FROM billings b WHERE b.customer_id = $customer_id ORDER BY b.billing_year DESC, b.billing_month DESC");
$status_log = $conn->query("SELECT sl.*, u.full_name as staff_name FROM customer_status_log sl LEFT JOIN users u ON sl.changed_by = u.user_id WHERE sl.customer_id = $customer_id ORDER BY sl.created_at DESC LIMIT 20");
$sketches = $conn->query("SELECT s.*, u.full_name as creator_name FROM installation_sketches s LEFT JOIN users u ON s.created_by = u.user_id WHERE s.customer_id = $customer_id ORDER BY s.created_at DESC");

$balance_row = $conn->query("SELECT COALESCE(SUM(b.net_amount),0) - COALESCE((SELECT SUM(p.amount_paid) FROM payments p JOIN billings b2 ON p.billing_id = b2.billing_id WHERE b2.customer_id = $customer_id),0) as bal FROM billings b WHERE b.customer_id = $customer_id")->fetch_assoc();
$current_balance = $balance_row['bal'];

$payments_history = $conn->query("SELECT p.payment_date, p.amount_paid, p.or_number, b.billing_month, b.billing_year FROM payments p JOIN billings b ON p.billing_id = b.billing_id WHERE p.customer_id = $customer_id ORDER BY p.payment_date ASC LIMIT 20");
$materials = [];
if ($customer['package_id']) { $mr = $conn->query("SELECT * FROM package_materials WHERE package_id = " . intval($customer['package_id'])); while ($m = $mr->fetch_assoc()) $materials[] = $m; }
$total_billed = 0; $total_paid = 0; $total_balance = 0;

/* For Edit modal dropdowns */
$areas_list    = $conn->query("SELECT * FROM areas ORDER BY area_name");
$packages_list = $conn->query("SELECT * FROM packages WHERE status='active' ORDER BY package_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ledger - <?php echo htmlspecialchars($customer['subscriber_name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container no-print">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">

            <?php if(isset($edit_success)):?><div class="alert alert-success no-print"><?php echo $edit_success;?></div><?php endif;?>
            <?php if(isset($edit_error)):?><div class="alert alert-error no-print"><?php echo $edit_error;?></div><?php endif;?>
            <?php if(isset($delete_error)):?><div class="alert alert-error no-print"><?php echo $delete_error;?></div><?php endif;?>

            <div class="widget mb-3">
                <div class="widget-header">
                    <h2><?php echo htmlspecialchars($customer['subscriber_name']); ?></h2>
                    <div class="ledger-header-right">
                        <?php $sc = match($customer['status']){'active'=>'success','disconnected'=>'danger','reconnected'=>'info','pending_installation'=>'warning',default=>'secondary'}; ?>
                        <span class="badge badge-<?php echo $sc; ?>"><?php echo ucfirst(str_replace('_',' ',$customer['status'])); ?></span>
                        <?php if($_SESSION['role']==='admin'): ?>
                        <button onclick="document.getElementById('editSubscriberModal').classList.add('show')" class="btn btn-edit btn-sm no-print">✏️ Edit</button>
                        <button onclick="document.getElementById('deleteConfirmModal').classList.add('show')" class="btn btn-delete btn-sm no-print">🗑 Delete</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="widget-content">
                    <div class="ledger-info-grid">
                        <div><strong>Account #:</strong> <?php echo $customer['account_number']; ?></div>
                        <div><strong>Address:</strong> <?php echo htmlspecialchars($customer['address']); ?></div>
                        <div><strong>Area:</strong> <?php echo htmlspecialchars($customer['area_name']??'N/A'); ?></div>
                        <div><strong>Package:</strong> <?php echo htmlspecialchars($customer['package_name']??'N/A'); ?> (<?php echo $customer['bandwidth_mbps']??0; ?> Mbps)</div>
                        <div><strong>Monthly Fee:</strong> <?php echo format_currency($customer['monthly_fee']); ?></div>
                        <div><strong>Contact:</strong> <?php echo $customer['tel_no']??'N/A'; ?></div>
                        <div><strong>Installed:</strong> <?php echo $customer['installation_date']?date('M d, Y',strtotime($customer['installation_date'])):'N/A'; ?></div>
                        <div><strong>Last Payment:</strong> <?php echo $last_payment?date('M d, Y',strtotime($last_payment)):'None'; ?></div>
                        <div><strong>Current Balance:</strong> <span class="<?php echo $current_balance>0?'ledger-balance-positive':'ledger-balance-zero'; ?>"><?php echo format_currency($current_balance); ?></span></div>
                    </div>
                    <div class="ledger-actions">
                        <a href="print_installation.php?id=<?php echo $customer_id; ?>" target="_blank" class="btn btn-primary btn-sm">🖨️ Installation Form</a>
                        <a href="print_billing_statement.php?id=<?php echo $customer_id; ?>" target="_blank" class="btn btn-secondary btn-sm">🖨️ Billing Statement</a>
                        <a href="print_sketch.php?id=<?php echo $customer_id; ?>" target="_blank" class="btn btn-sm btn-info">🖨️ Print Sketch</a>
                    </div>
                </div>
            </div>
            
            <div class="table-container mb-3">
                <div class="table-header"><h2>Billing History</h2></div>
                <table>
                    <thead><tr><th>Period</th><th>Prev Balance</th><th>Internet</th><th>Service Fee</th><th>Material</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if ($billings->num_rows > 0): while ($b = $billings->fetch_assoc()):
                            $paid = $b['total_paid']??0; $bal = $b['net_amount']-$paid;
                            $total_billed += $b['net_amount']; $total_paid += $paid; $total_balance += $bal;
                        ?>
                        <tr>
                            <td><?php echo get_month_name($b['billing_month']).' '.$b['billing_year']; ?></td>
                            <td><?php echo format_currency($b['previous_balance']); ?></td>
                            <td><?php echo format_currency($b['internet_fee']); ?></td>
                            <td><?php echo format_currency($b['service_fee']); ?></td>
                            <td><?php echo format_currency($b['material_fee']); ?></td>
                            <td><?php echo format_currency($b['net_amount']); ?></td>
                            <td><?php echo format_currency($paid); ?></td>
                            <td><?php echo format_currency($bal); ?></td>
                            <td><?php $s=$b['status']=='paid'?'success':($b['status']=='partial'?'warning':'danger'); ?><span class="badge badge-<?php echo $s; ?>"><?php echo ucfirst($b['status']); ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                        <tr class="table-row-total">
                            <td colspan="5" class="text-right">TOTAL:</td>
                            <td><?php echo format_currency($total_billed); ?></td>
                            <td><?php echo format_currency($total_paid); ?></td>
                            <td><?php echo format_currency($total_balance); ?></td><td></td>
                        </tr>
                        <?php else: ?><tr><td colspan="9" class="text-center">No billing records</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($status_log && $status_log->num_rows > 0): ?>
            <div class="widget mb-3">
                <div class="widget-header"><h2>Status Change History</h2></div>
                <div class="widget-content widget-content-flush">
                    <table>
                        <thead><tr><th>Date</th><th>Time</th><th>Old Status</th><th>New Status</th><th>Staff</th><th>Remarks</th></tr></thead>
                        <tbody>
                            <?php while ($sl = $status_log->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y',strtotime($sl['change_date'])); ?></td>
                                <td><strong><?php echo date('h:i:s A',strtotime($sl['change_time'])); ?></strong></td>
                                <td><?php echo $sl['old_status']?ucfirst(str_replace('_',' ',$sl['old_status'])):'-'; ?></td>
                                <td><strong><?php echo ucfirst(str_replace('_',' ',$sl['new_status'])); ?></strong></td>
                                <td><?php echo htmlspecialchars($sl['staff_name']??'System'); ?></td>
                                <td><?php echo htmlspecialchars($sl['remarks']??''); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($sketches && $sketches->num_rows > 0): ?>
            <div class="widget mb-3">
                <div class="widget-header"><h2>Installation Sketches / Photos</h2>
                    <a href="print_sketch.php?id=<?php echo $customer_id; ?>" target="_blank" class="btn btn-sm btn-primary">🖨️ Print All Sketches</a>
                </div>
                <div class="widget-content">
                    <?php while ($sk = $sketches->fetch_assoc()): ?>
                    <div class="sketch-card">
                        <?php if ($sk['sketch_type']=='upload' && $sk['file_path']): ?>
                            <img src="<?php echo htmlspecialchars($sk['file_path']); ?>" class="sketch-card-img">
                        <?php elseif ($sk['sketch_data']): ?>
                            <img src="<?php echo $sk['sketch_data']; ?>" class="sketch-card-img">
                        <?php endif; ?>
                        <?php if ($sk['remarks']): ?><p class="sketch-card-remarks"><strong>Remarks:</strong> <?php echo htmlspecialchars($sk['remarks']); ?></p><?php endif; ?>
                        <p class="sketch-card-meta">By: <?php echo htmlspecialchars($sk['creator_name']??'Unknown'); ?> | <?php echo date('M d, Y h:i A',strtotime($sk['created_at'])); ?></p>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <a href="customers.php" class="btn btn-secondary">← Back to Subscribers</a>
        </main>
    </div>
    
    <!-- Print Ledger -->
    <div class="print-ledger">
        <img src="images/headerlogo.png" alt="NovaLink" style="max-height:55px;display:block;margin:0 auto 8px auto;">
        <table style="width:100%;border-collapse:collapse;margin-bottom:8px;">
            <tr>
                <td style="width:50%;vertical-align:top;">
                    <div style="margin-bottom:3px;"><strong>Name:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:200px;padding-left:5px;"><?php echo strtoupper($customer['subscriber_name']); ?></span></div>
                    <div style="margin-bottom:3px;"><strong>Address:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:200px;padding-left:5px;"><?php echo strtoupper($customer['address']); ?></span></div>
                    <div style="margin-bottom:3px;"><strong>Contact#:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:200px;padding-left:5px;"><?php echo $customer['tel_no']??''; ?></span></div>
                    <div style="margin-bottom:3px;"><strong>Date Inst.:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:200px;padding-left:5px;"><?php echo $customer['date_connected']?date('m/d/y',strtotime($customer['date_connected'])):''; ?></span></div>
                    <div style="margin-bottom:3px;"><strong>BUNDLE:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:200px;padding-left:5px;"><?php echo strtoupper($customer['package_name']??''); ?></span></div>
                    <div style="margin-bottom:3px;"><strong>MBPS:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:200px;padding-left:5px;"><?php echo $customer['bandwidth_mbps']??''; ?></span></div>
                </td>
                <td style="width:50%;vertical-align:top;padding-left:10px;">
                    <div style="margin-bottom:3px;"><strong>ACCT NAME:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:180px;padding-left:5px;"><?php echo strtoupper($customer['subscriber_name']); ?></span></div>
                    <?php foreach ($materials as $m): ?>
                    <div style="margin-bottom:2px;font-size:9px;"><strong><?php echo strtoupper($m['material_name']); ?>:</strong> <span style="border-bottom:1px solid #000;display:inline-block;min-width:100px;text-align:center;"><?php echo $m['quantity']; ?></span></div>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
        <table style="width:100%;border-collapse:collapse;font-size:9px;">
            <thead><tr style="background:#c89632;color:white;"><th style="border:1px solid #999;padding:3px;">MONTH</th><th style="border:1px solid #999;padding:3px;">DATE PAID</th><th style="border:1px solid #999;padding:3px;">AMOUNT PAID</th><th style="border:1px solid #999;padding:3px;">BALANCE</th><th style="border:1px solid #999;padding:3px;">REMARK</th></tr></thead>
            <tbody>
                <?php if ($payments_history->num_rows > 0): while ($ph = $payments_history->fetch_assoc()): ?>
                <tr><td style="border:1px solid #ccc;padding:2px;"><?php echo strtoupper(get_month_name($ph['billing_month'])); ?></td><td style="border:1px solid #ccc;padding:2px;"><?php echo date('n/j/y',strtotime($ph['payment_date'])); ?></td><td style="border:1px solid #ccc;padding:2px;text-align:right;"><?php echo number_format($ph['amount_paid'],2); ?></td><td style="border:1px solid #ccc;padding:2px;text-align:right;"></td><td style="border:1px solid #ccc;padding:2px;"></td></tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
    <script src="js/script.js"></script>

<!-- ═══ EDIT SUBSCRIBER MODAL ═══ -->
<div id="editSubscriberModal" class="modal">
    <div class="modal-content modal-edit modal-centered">
        <div class="modal-header modal-header-edit">
            <h2>✏️ Edit Subscriber</h2>
            <button class="modal-close" onclick="this.closest('.modal').classList.remove('show')">&times;</button>
        </div>
        <form method="POST" id="editSubForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit_subscriber">

                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" class="auto-field" value="<?php echo htmlspecialchars($customer['account_number']); ?>" readonly>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" id="edit_first_name" required value="<?php echo htmlspecialchars($customer['first_name']??''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" id="edit_middle_name" value="<?php echo htmlspecialchars($customer['middle_name']??''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" id="edit_last_name" required value="<?php echo htmlspecialchars($customer['last_name']??''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Complete Address *</label>
                    <textarea name="address" required rows="2"><?php echo htmlspecialchars($customer['address']??''); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Area *</label>
                        <select name="area_id" required>
                            <option value="">Select Area</option>
                            <?php while($a=$areas_list->fetch_assoc()): ?>
                            <option value="<?php echo $a['area_id']; ?>" <?php echo $customer['area_id']==$a['area_id']?'selected':''; ?>>
                                <?php echo htmlspecialchars($a['area_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact Number *</label>
                        <div class="input-wrap">
                            <input type="text" name="tel_no" id="edit_tel_no" required maxlength="11"
                                   placeholder="09XXXXXXXXX" oninput="validateEditPhone(this)"
                                   value="<?php echo htmlspecialchars($customer['tel_no']??''); ?>">
                            <span class="validation-icon" id="editPhoneIcon"></span>
                        </div>
                        <div class="validation-msg" id="editPhoneMsg"></div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Package *</label>
                        <select name="package_id" id="edit_package_id" required
                                onchange="var f=this.options[this.selectedIndex].dataset.fee;if(f)document.getElementById('edit_monthly_fee').value=parseFloat(f).toFixed(2);">
                            <option value="">Select Package</option>
                            <?php while($pk=$packages_list->fetch_assoc()): ?>
                            <option value="<?php echo $pk['package_id']; ?>"
                                    data-fee="<?php echo $pk['monthly_fee']; ?>"
                                    <?php echo $customer['package_id']==$pk['package_id']?'selected':''; ?>>
                                <?php echo htmlspecialchars($pk['package_name']); ?> — <?php echo $pk['bandwidth_mbps']; ?> Mbps
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Monthly Fee (₱) *</label>
                        <input type="number" step="0.01" name="monthly_fee" id="edit_monthly_fee" required
                               value="<?php echo number_format($customer['monthly_fee'],2,'.',''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Installation Date</label>
                        <input type="date" name="installation_date"
                               value="<?php echo $customer['installation_date'] ? substr($customer['installation_date'],0,10) : ''; ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ DELETE SUBSCRIBER CONFIRMATION MODAL ═══ -->
<div id="deleteConfirmModal" class="modal">
    <div class="modal-content modal-sm modal-centered">
        <div class="modal-header modal-header-danger">
            <h2>⚠️ Delete Subscriber</h2>
            <button class="modal-close" onclick="this.closest('.modal').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="delete-warning-box">
                <p class="delete-warning-title">This action is <strong>permanent and cannot be undone.</strong></p>
                <p class="delete-warning-sub">All records will be permanently removed, including:</p>
                <ul class="delete-warning-list">
                    <li>All billing history</li>
                    <li>All payment records</li>
                    <li>Installation sketches &amp; photos</li>
                    <li>Status change history</li>
                </ul>
            </div>
            <div class="delete-target-box">
                <div class="delete-target-row">
                    <span class="delete-target-label">Subscriber</span>
                    <strong class="delete-target-value"><?php echo htmlspecialchars($customer['subscriber_name']); ?></strong>
                </div>
                <div class="delete-target-row">
                    <span class="delete-target-label">Account #</span>
                    <strong class="delete-target-value"><?php echo htmlspecialchars($customer['account_number']); ?></strong>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <form method="POST" id="deleteSubForm">
                <input type="hidden" name="action" value="delete_subscriber">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-danger">Yes, Delete Permanently</button>
            </form>
        </div>
    </div>
</div>

<script>
/* Live phone validation in Edit modal */
function validateEditPhone(el) {
    var d = el.value.replace(/\D/g, ''); el.value = d;
    var ic = document.getElementById('editPhoneIcon'), mg = document.getElementById('editPhoneMsg');
    if (!ic || !mg) return;
    if (d.length === 11) {
        el.classList.remove('input-invalid'); el.classList.add('input-valid');
        ic.textContent = '✓'; ic.className = 'validation-icon valid';
        mg.textContent = 'Valid (11 digits)'; mg.className = 'validation-msg ok';
    } else {
        el.classList.remove('input-valid'); el.classList.add('input-invalid');
        ic.textContent = '✗'; ic.className = 'validation-icon invalid';
        mg.textContent = 'Must be exactly 11 digits (' + d.length + '/11)'; mg.className = 'validation-msg error';
    }
}
document.getElementById('editSubForm')?.addEventListener('submit', function(e) {
    var ph = document.getElementById('edit_tel_no').value.replace(/\D/g, '');
    if (ph.length !== 11) { e.preventDefault(); alert('Contact number must be exactly 11 digits.'); }
});
/* Re-open edit modal automatically if there was a validation error */
<?php if(isset($edit_error)): ?>
document.getElementById('editSubscriberModal').classList.add('show');
<?php endif; ?>
</script>
<?php include "includes/footer.php"; ?>
</body>
</html>
<?php $conn->close(); ?>