<?php
require_once 'config.php';
check_permission('admin');
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $package_name = sanitize_input($_POST['package_name']);
        $bandwidth_mbps = intval($_POST['bandwidth_mbps']);
        $monthly_fee = floatval($_POST['monthly_fee']);
        $description = sanitize_input($_POST['description']);
        $stmt = $conn->prepare("INSERT INTO packages (package_name, bandwidth_mbps, monthly_fee, description, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("sids", $package_name, $bandwidth_mbps, $monthly_fee, $description);
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'ADD_PACKAGE', 'packages', $stmt->insert_id, "Added package: $package_name");
            $success = "Package added successfully!";
        } else { $error = "Error adding package: " . $conn->error; }
        $stmt->close();
    }
    elseif ($_POST['action'] == 'edit') {
        $package_id = intval($_POST['package_id']);
        $package_name = sanitize_input($_POST['package_name']);
        $bandwidth_mbps = intval($_POST['bandwidth_mbps']);
        $monthly_fee = floatval($_POST['monthly_fee']);
        $description = sanitize_input($_POST['description']);
        $status = sanitize_input($_POST['status']);
        $stmt = $conn->prepare("UPDATE packages SET package_name=?, bandwidth_mbps=?, monthly_fee=?, description=?, status=? WHERE package_id=?");
        $stmt->bind_param("sidssi", $package_name, $bandwidth_mbps, $monthly_fee, $description, $status, $package_id);
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'EDIT_PACKAGE', 'packages', $package_id, "Updated package: $package_name");
            $success = "Package updated successfully!";
        } else { $error = "Error updating package: " . $conn->error; }
        $stmt->close();
    }
    elseif ($_POST['action'] == 'toggle_status') {
        $package_id = intval($_POST['package_id']);
        $new_status = $_POST['new_status'];
        $stmt = $conn->prepare("UPDATE packages SET status=? WHERE package_id=?");
        $stmt->bind_param("si", $new_status, $package_id);
        if ($stmt->execute()) { $success = "Package status updated!"; }
        $stmt->close();
    }
    elseif ($_POST['action'] == 'delete') {
        $package_id = intval($_POST['package_id']);
        $check = $conn->query("SELECT COUNT(*) as count FROM customers WHERE package_id = $package_id");
        $count = $check->fetch_assoc()['count'];
        if ($count > 0) { $error = "Cannot delete: $count customer(s) using this package!"; }
        else {
            $conn->query("DELETE FROM package_materials WHERE package_id = $package_id");
            $stmt = $conn->prepare("DELETE FROM packages WHERE package_id=?");
            $stmt->bind_param("i", $package_id);
            if ($stmt->execute()) { $success = "Package deleted!"; }
            $stmt->close();
        }
    }
    elseif ($_POST['action'] == 'add_material') {
        $pid = intval($_POST['package_id']);
        $mname = sanitize_input($_POST['material_name']);
        $qty = intval($_POST['quantity']);
        $unit = sanitize_input($_POST['unit']);
        if (empty($mname)) { $error = "Material name is required."; }
        else {
            $stmt = $conn->prepare("INSERT INTO package_materials (package_id, material_name, quantity, unit) VALUES (?,?,?,?)");
            $stmt->bind_param("isis", $pid, $mname, $qty, $unit);
            if ($stmt->execute()) { $success = "Material added!"; } else { $error = "Error: " . $conn->error; }
            $stmt->close();
        }
        header("Location: manage_packages.php?view_materials=$pid" . (isset($error) ? "&err=" . urlencode($error) : "&msg=success"));
        exit();
    }
    elseif ($_POST['action'] == 'edit_material') {
        $mid = intval($_POST['material_id']);
        $mname = sanitize_input($_POST['material_name']);
        $qty = intval($_POST['quantity']);
        $unit = sanitize_input($_POST['unit']);
        $r = $conn->query("SELECT package_id FROM package_materials WHERE material_id=$mid");
        $pid = $r->fetch_assoc()['package_id'];
        $stmt = $conn->prepare("UPDATE package_materials SET material_name=?, quantity=?, unit=? WHERE material_id=?");
        $stmt->bind_param("sisi", $mname, $qty, $unit, $mid);
        if ($stmt->execute()) { $success = "Material updated!"; }
        $stmt->close();
        header("Location: manage_packages.php?view_materials=$pid&msg=success");
        exit();
    }
    elseif ($_POST['action'] == 'delete_material') {
        $mid = intval($_POST['material_id']);
        $r = $conn->query("SELECT package_id FROM package_materials WHERE material_id=$mid");
        $pid = $r->fetch_assoc()['package_id'];
        $conn->query("DELETE FROM package_materials WHERE material_id=$mid");
        header("Location: manage_packages.php?view_materials=$pid&msg=success");
        exit();
    }
}

$packages = $conn->query("SELECT p.*, (SELECT COUNT(*) FROM customers WHERE package_id = p.package_id) as customer_count FROM packages p ORDER BY p.bandwidth_mbps");

$view_package_id = isset($_GET['view_materials']) ? intval($_GET['view_materials']) : 0;
$materials = [];
$view_package = null;
if ($view_package_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM packages WHERE package_id = ?");
    $stmt->bind_param("i", $view_package_id);
    $stmt->execute();
    $view_package = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $mat_result = $conn->query("SELECT * FROM package_materials WHERE package_id = $view_package_id ORDER BY material_name");
    while ($row = $mat_result->fetch_assoc()) { $materials[] = $row; }
}

// Get ALL distinct material names for the searchable dropdown
$all_material_names = [];
$mn_result = $conn->query("SELECT DISTINCT material_name FROM package_materials ORDER BY material_name ASC");
if ($mn_result) { while ($row = $mn_result->fetch_assoc()) { $all_material_names[] = $row['material_name']; } }

if (isset($_GET['msg']) && $_GET['msg'] == 'success') $success = "Operation completed successfully!";
if (isset($_GET['err'])) $error = $_GET['err'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Packages - AR NOVALINK</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Searchable Combo Dropdown */
        .combo-container { position: relative; }
        .combo-input { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        .combo-input:focus { border-color: #0066cc; outline: none; box-shadow: 0 0 0 3px rgba(0,102,204,0.1); }
        .combo-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ddd; border-top: none; border-radius: 0 0 6px 6px; max-height: 220px; overflow-y: auto; z-index: 100; display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
        .combo-dropdown.show { display: block; }
        .combo-option { padding: 9px 14px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f5f5f5; transition: background 0.15s; }
        .combo-option:hover, .combo-option.highlighted { background: #e8f0fe; }
        .combo-option.add-new { color: #0066cc; font-weight: bold; font-style: italic; border-top: 2px solid #e0e0e0; }
        .combo-option .match { background: #fff3cd; font-weight: bold; border-radius: 2px; padding: 0 1px; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header">
                <div><h1>Manage Internet Packages</h1><p>Manage packages, pricing, and installation materials</p></div>
                <button onclick="document.getElementById('addModal').classList.add('show')" class="btn btn-primary">+ Add New Package</button>
            </div>
            <?php if (isset($success)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if (isset($error)): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            
            <?php if ($view_package): ?>
            <!-- ===== MATERIALS SECTION ===== -->
            <div class="table-container" style="margin-bottom:20px;border:2px solid #17a2b8;">
                <div class="table-header" style="background:#e8f4f8;">
                    <h2>📦 Installation Materials — <?php echo htmlspecialchars($view_package['package_name']); ?> (<?php echo $view_package['bandwidth_mbps']; ?> Mbps)</h2>
                    <div class="table-actions">
                        <button onclick="openAddMaterialModal()" class="btn btn-primary btn-sm">+ Add Material</button>
                        <a href="manage_packages.php" class="btn btn-secondary btn-sm">← Back</a>
                    </div>
                </div>
                <table>
                    <thead><tr><th style="width:40px;">#</th><th>Material Name</th><th style="width:80px;">Qty</th><th style="width:80px;">Unit</th><th style="width:180px;">Actions</th></tr></thead>
                    <tbody>
                        <?php if (count($materials) > 0): $i=1; foreach ($materials as $mat): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($mat['material_name']); ?></strong></td>
                            <td><?php echo $mat['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($mat['unit']); ?></td>
                            <td>
                                <button onclick='openEditMat(<?php echo json_encode($mat); ?>)' class="btn btn-sm btn-secondary">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this material?')">
                                    <input type="hidden" name="action" value="delete_material">
                                    <input type="hidden" name="material_id" value="<?php echo $mat['material_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center" style="padding:25px;color:#888;">No materials yet. Click <strong>"+ Add Material"</strong> to begin.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- ===== PACKAGES TABLE ===== -->
            <div class="table-container">
                <div class="table-header"><h2>All Packages (<?php echo $packages->num_rows; ?>)</h2></div>
                <table>
                    <thead><tr><th>ID</th><th>Package</th><th>Speed</th><th>Monthly Fee</th><th>Description</th><th>Materials</th><th>Customers</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if ($packages->num_rows > 0): while ($p = $packages->fetch_assoc()): 
                            $mc = $conn->query("SELECT COUNT(*) as c FROM package_materials WHERE package_id=".$p['package_id'])->fetch_assoc()['c'];
                        ?>
                        <tr>
                            <td><?php echo $p['package_id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($p['package_name']); ?></strong></td>
                            <td><span class="badge badge-info"><?php echo $p['bandwidth_mbps']; ?> Mbps</span></td>
                            <td><strong><?php echo format_currency($p['monthly_fee']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['description'] ?? 'N/A'); ?></td>
                            <td><a href="manage_packages.php?view_materials=<?php echo $p['package_id']; ?>" class="btn btn-sm" style="background:#17a2b8;color:#fff;"><?php echo $mc; ?> items</a></td>
                            <td><?php echo $p['customer_count'] > 0 ? '<span class="badge badge-primary">'.$p['customer_count'].'</span>' : '<span class="badge badge-secondary">0</span>'; ?></td>
                            <td><span class="badge badge-<?php echo $p['status']=='active'?'success':'secondary'; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                            <td>
                                <button onclick='openEditModal(<?php echo json_encode($p); ?>)' class="btn btn-sm btn-secondary">Edit</button>
                                <?php if ($p['customer_count'] == 0): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this package?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="package_id" value="<?php echo $p['package_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="9" class="text-center">No packages found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- ===== ADD PACKAGE MODAL ===== -->
    <div id="addModal" class="modal">
        <div class="modal-content" style="max-width:600px;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);">
            <div class="modal-header"><h2>Add New Package</h2><button class="modal-close" onclick="this.closest('.modal').classList.remove('show')">&times;</button></div>
            <form method="POST"><div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group"><label>Package Name *</label><input type="text" name="package_name" required></div>
                <div class="form-row">
                    <div class="form-group"><label>Speed (Mbps) *</label><input type="number" name="bandwidth_mbps" required min="1"></div>
                    <div class="form-group"><label>Monthly Fee (₱) *</label><input type="number" step="0.01" name="monthly_fee" required min="0"></div>
                </div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="3"></textarea></div>
            </div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="this.closest('.modal').classList.remove('show')">Cancel</button><button type="submit" class="btn btn-primary">Add Package</button></div></form>
        </div>
    </div>
    
    <!-- ===== EDIT PACKAGE MODAL ===== -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width:600px;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);">
            <div class="modal-header"><h2>Edit Package</h2><button class="modal-close" onclick="this.closest('.modal').classList.remove('show')">&times;</button></div>
            <form method="POST"><div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_package_id" name="package_id">
                <div class="form-group"><label>Package Name *</label><input type="text" id="edit_package_name" name="package_name" required></div>
                <div class="form-row">
                    <div class="form-group"><label>Speed (Mbps) *</label><input type="number" id="edit_bandwidth_mbps" name="bandwidth_mbps" required min="1"></div>
                    <div class="form-group"><label>Monthly Fee (₱) *</label><input type="number" step="0.01" id="edit_monthly_fee" name="monthly_fee" required min="0"></div>
                </div>
                <div class="form-group"><label>Description</label><textarea id="edit_description" name="description" rows="3"></textarea></div>
                <div class="form-group"><label>Status</label><select id="edit_status" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            </div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="this.closest('.modal').classList.remove('show')">Cancel</button><button type="submit" class="btn btn-primary">Update</button></div></form>
        </div>
    </div>
    
    <!-- ===== ADD MATERIAL MODAL (with searchable dropdown) ===== -->
    <div id="addMaterialModal" class="modal">
        <div class="modal-content" style="max-width:520px;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);">
            <div class="modal-header"><h2>Add Material</h2><button class="modal-close" onclick="closeMaterialModal()">&times;</button></div>
            <form method="POST" id="addMaterialForm" onsubmit="return submitMaterialForm()">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_material">
                    <input type="hidden" name="package_id" value="<?php echo $view_package_id; ?>">
                    
                    <div class="form-group">
                        <label>Material Name *</label>
                        <div class="combo-container" id="materialCombo">
                            <input type="text" 
                                   class="combo-input" 
                                   id="materialSearchInput" 
                                   placeholder="Search or type new material name..." 
                                   autocomplete="off"
                                   required>
                            <input type="hidden" name="material_name" id="materialNameHidden">
                            <div class="combo-dropdown" id="materialDropdown"></div>
                        </div>
                        <small style="color:#888;display:block;margin-top:5px;">Choose from existing materials or type a new name to add it.</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" required min="1" value="1">
                        </div>
                        <div class="form-group">
                            <label>Unit</label>
                            <select name="unit">
                                <option value="pcs">pcs</option>
                                <option value="roll">roll</option>
                                <option value="meters">meters</option>
                                <option value="set">set</option>
                                <option value="box">box</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeMaterialModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Material</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- ===== EDIT MATERIAL MODAL (also with searchable dropdown) ===== -->
    <div id="editMaterialModal" class="modal">
        <div class="modal-content" style="max-width:520px;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);">
            <div class="modal-header"><h2>Edit Material</h2><button class="modal-close" onclick="this.closest('.modal').classList.remove('show')">&times;</button></div>
            <form method="POST" onsubmit="document.getElementById('editMatNameHidden').value = document.getElementById('editMatSearchInput').value; return true;">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_material">
                    <input type="hidden" id="edit_material_id" name="material_id">
                    
                    <div class="form-group">
                        <label>Material Name *</label>
                        <div class="combo-container" id="editMaterialCombo">
                            <input type="text" 
                                   class="combo-input" 
                                   id="editMatSearchInput" 
                                   placeholder="Search or type material name..." 
                                   autocomplete="off"
                                   required>
                            <input type="hidden" name="material_name" id="editMatNameHidden">
                            <div class="combo-dropdown" id="editMaterialDropdown"></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group"><label>Quantity *</label><input type="number" id="edit_mat_qty" name="quantity" required min="1"></div>
                        <div class="form-group"><label>Unit</label><select id="edit_mat_unit" name="unit"><option value="pcs">pcs</option><option value="roll">roll</option><option value="meters">meters</option><option value="set">set</option><option value="box">box</option></select></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').classList.remove('show')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="js/script.js"></script>
    <script>
        // ============================================================
        // All known material names (loaded from DB, kept in-memory)
        // ============================================================
        let allMaterialNames = <?php echo json_encode($all_material_names); ?>;
        
        // ============================================================
        // Generic Searchable Combo Dropdown
        // ============================================================
        function initCombo(inputId, dropdownId, hiddenId) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);
            const hidden = document.getElementById(hiddenId);
            let highlightIdx = -1;
            
            function renderDropdown(filter) {
                const query = (filter || '').toLowerCase().trim();
                let filtered = allMaterialNames;
                
                if (query.length > 0) {
                    filtered = allMaterialNames.filter(n => n.toLowerCase().includes(query));
                }
                
                let html = '';
                filtered.forEach((name, idx) => {
                    let display = name;
                    if (query.length > 0) {
                        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                        display = name.replace(regex, '<span class="match">$1</span>');
                    }
                    html += `<div class="combo-option" data-value="${escapeHtml(name)}" data-idx="${idx}">${display}</div>`;
                });
                
                // Show "add new" option if typed text doesn't exactly match any existing
                if (query.length > 0 && !allMaterialNames.some(n => n.toLowerCase() === query)) {
                    html += `<div class="combo-option add-new" data-value="${escapeHtml(input.value.trim())}">+ Add new: "${escapeHtml(input.value.trim())}"</div>`;
                }
                
                dropdown.innerHTML = html;
                highlightIdx = -1;
                
                if (html) {
                    dropdown.classList.add('show');
                    // Click handlers
                    dropdown.querySelectorAll('.combo-option').forEach(opt => {
                        opt.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            selectOption(input, hidden, dropdown, this.getAttribute('data-value'));
                        });
                    });
                } else {
                    dropdown.classList.remove('show');
                }
            }
            
            function selectOption(input, hidden, dropdown, value) {
                input.value = value;
                hidden.value = value;
                dropdown.classList.remove('show');
                // If it's a brand new material, add it to the list so it appears next time
                if (!allMaterialNames.includes(value)) {
                    allMaterialNames.push(value);
                    allMaterialNames.sort();
                }
            }
            
            // Events
            input.addEventListener('focus', function() {
                renderDropdown(this.value);
            });
            
            input.addEventListener('input', function() {
                hidden.value = this.value.trim();
                renderDropdown(this.value);
            });
            
            input.addEventListener('keydown', function(e) {
                const options = dropdown.querySelectorAll('.combo-option');
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    highlightIdx = Math.min(highlightIdx + 1, options.length - 1);
                    updateHighlight(options);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    highlightIdx = Math.max(highlightIdx - 1, 0);
                    updateHighlight(options);
                } else if (e.key === 'Enter' && highlightIdx >= 0 && options[highlightIdx]) {
                    e.preventDefault();
                    selectOption(input, hidden, dropdown, options[highlightIdx].getAttribute('data-value'));
                } else if (e.key === 'Escape') {
                    dropdown.classList.remove('show');
                }
            });
            
            // Close dropdown on outside click
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#' + inputId) && !e.target.closest('#' + dropdownId)) {
                    dropdown.classList.remove('show');
                }
            });
            
            function updateHighlight(options) {
                options.forEach((opt, idx) => {
                    opt.classList.toggle('highlighted', idx === highlightIdx);
                    if (idx === highlightIdx) opt.scrollIntoView({ block: 'nearest' });
                });
            }
        }
        
        function escapeHtml(text) {
            const d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }
        
        // ============================================================
        // Initialize combos
        // ============================================================
        initCombo('materialSearchInput', 'materialDropdown', 'materialNameHidden');
        initCombo('editMatSearchInput', 'editMaterialDropdown', 'editMatNameHidden');
        
        // ============================================================
        // Add Material form submit — sync hidden field
        // ============================================================
        function submitMaterialForm() {
            const input = document.getElementById('materialSearchInput');
            const hidden = document.getElementById('materialNameHidden');
            hidden.value = input.value.trim();
            if (!hidden.value) { alert('Please enter a material name.'); return false; }
            return true;
        }
        
        // ============================================================
        // Modal open/close
        // ============================================================
        function openAddMaterialModal() {
            document.getElementById('materialSearchInput').value = '';
            document.getElementById('materialNameHidden').value = '';
            document.getElementById('addMaterialModal').classList.add('show');
            // Refresh the material names list from server
            fetch('ajax/get_material_names.php')
                .then(r => r.json())
                .then(names => { allMaterialNames = names; })
                .catch(() => {});
            setTimeout(() => document.getElementById('materialSearchInput').focus(), 200);
        }
        
        function closeMaterialModal() {
            document.getElementById('addMaterialModal').classList.remove('show');
        }
        
        // ============================================================
        // Edit modals
        // ============================================================
        function openEditModal(p) {
            document.getElementById('edit_package_id').value = p.package_id;
            document.getElementById('edit_package_name').value = p.package_name;
            document.getElementById('edit_bandwidth_mbps').value = p.bandwidth_mbps;
            document.getElementById('edit_monthly_fee').value = p.monthly_fee;
            document.getElementById('edit_description').value = p.description || '';
            document.getElementById('edit_status').value = p.status;
            document.getElementById('editModal').classList.add('show');
        }
        
        function openEditMat(m) {
            document.getElementById('edit_material_id').value = m.material_id;
            document.getElementById('editMatSearchInput').value = m.material_name;
            document.getElementById('editMatNameHidden').value = m.material_name;
            document.getElementById('edit_mat_qty').value = m.quantity;
            document.getElementById('edit_mat_unit').value = m.unit;
            document.getElementById('editMaterialModal').classList.add('show');
            // Refresh list
            fetch('ajax/get_material_names.php')
                .then(r => r.json())
                .then(names => { allMaterialNames = names; })
                .catch(() => {});
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
