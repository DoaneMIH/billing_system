<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($customer_id == 0) { die("Invalid subscriber ID"); }
$conn = getDBConnection();

$stmt = $conn->prepare("SELECT c.*, a.area_name, p.package_name, p.bandwidth_mbps FROM customers c LEFT JOIN areas a ON c.area_id = a.area_id LEFT JOIN packages p ON c.package_id = p.package_id WHERE c.customer_id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$customer) { die("Subscriber not found"); }

$sketches = $conn->query("SELECT s.*, u.full_name as creator_name FROM installation_sketches s LEFT JOIN users u ON s.created_by = u.user_id WHERE s.customer_id = $customer_id ORDER BY s.created_at DESC");

// Fetch package materials (same logic as print_installation.php)
$materials = [];
if ($customer['package_id']) {
    $mat_result = $conn->query("SELECT * FROM package_materials WHERE package_id = " . intval($customer['package_id']) . " ORDER BY material_name");
    while ($row = $mat_result->fetch_assoc()) { $materials[] = $row; }
}

// Build a normalised lookup: stripped-key => display quantity string
$mat_lookup = [];
foreach ($materials as $m) {
    $qty = $m['quantity'] . ($m['unit'] != 'pcs' ? ' ' . $m['unit'] : '');
    $mat_lookup[strtoupper($m['material_name'])] = trim($qty);
}

// Helper: fuzzy match a display label against the lookup
function matVal($label, $lookup) {
    $needle = strtoupper(preg_replace('/[\s\-\.]+/', '', $label));
    foreach ($lookup as $key => $val) {
        $hay = preg_replace('/[\s\-\.]+/', '', $key);
        if (strpos($hay, $needle) !== false || strpos($needle, $hay) !== false) {
            return $val;
        }
    }
    return '';
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Installation Sketch - <?php echo htmlspecialchars($customer['subscriber_name']); ?></title>
    <link rel="shortcut icon" type="x-icon" href="images/logo.jpg">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; background: #e0e0e0; }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 10px auto;
            background: white;
            padding: 10mm 12mm;
            box-shadow: 0 2px 10px rgba(0,0,0,0.25);
        }

        /* ── HEADER BANNER ── */
        .header-banner {
            width: 100%;
            border-bottom: 3px solid #003399;
            display: block;
        }
        .header-banner img {
            width: 100%;
            height: auto;
            object-fit: cover;
            display: block;
        }

        /* ── SUBSCRIBER INFO ── */
        .sub-info {
            padding: 6px 10px 4px;
            border-bottom: 1px solid #ccc;
            font-size: 10.5px;
        }
        .sub-info-title {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            text-decoration: underline;
            margin-bottom: 5px;
        }
        .sub-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .sub-grid td {
            padding: 2px 4px;
            vertical-align: bottom;
        }
        .sub-grid .lbl {
            font-weight: bold;
            white-space: nowrap;
            width: 105px;
        }
        .sub-grid .val {
            border-bottom: 1px solid #555;
            padding-left: 4px;
            padding-right: 12px;
        }

        /* ── BODY CONTENT ── */
        .body-content { padding: 0 10px 10px; }

        /* ── TECHNICAL / MATERIALS / OTHERS TABLE ── */
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 10px;
        }
        .main-table th, .main-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            vertical-align: top;
        }
        .main-table th {
            text-align: center;
            font-size: 10.5px;
            font-weight: bold;
            background: white;
            letter-spacing: 0.3px;
        }
        .main-table .row-label {
            font-weight: bold;
            white-space: nowrap;
            font-size: 9.5px;
            line-height: 1.3;
        }
        .main-table .val-cell {
            min-width: 55px;
        }
        .main-table .item-label {
            font-size: 9.5px;
            white-space: nowrap;
        }
        /* sub-label inside technical column */
        .sub-lbl {
            font-size: 9px;
            white-space: nowrap;
        }

        /* ── SKETCH AREA ── */
        .sketch-label {
            font-weight: bold;
            font-size: 11px;
            margin: 8px 0 3px;
            letter-spacing: 0.5px;
        }
        .sketch-box {
            border: 1.5px solid #000;
            width: 100%;
            min-height: 210px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .sketch-box img {
            max-width: 100%;
            max-height: 280px;
            display: block;
        }
        .sketch-empty {
            color: #bbb;
            font-style: italic;
            font-size: 11px;
            padding: 40px 0;
        }
        .sketch-meta {
            font-size: 9px;
            color: #777;
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 4px 6px 2px;
            border-top: 1px solid #eee;
        }

        /* ── FOOTER TABLE ── */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 10px;
        }
        .footer-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
        }
        .footer-label {
            font-weight: bold;
            font-size: 9.5px;
            display: block;
            margin-bottom: 18px;
        }
        .footer-name {
            font-weight: bold;
            font-size: 10px;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 2px;
            margin-top: 4px;
        }

        /* ── PRINT BUTTON ── */
        .print-btn {
            display: block;
            margin: 10px auto;
            padding: 10px 30px;
            background: #002060;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        @media print {
            body { background: white; }
            .page { box-shadow: none; margin: 0; }
            .print-btn, .no-print { display: none !important; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

<button class="print-btn no-print" onclick="window.print()">🖨️ Print Sketch Page</button>

<div class="page">

    <!-- ── HEADER BANNER ── -->
    <div class="header-banner">
        <img src="images/headerlogo.png" alt="Nova Link Digital Systems Corp.">
    </div>

    <!-- ── SUBSCRIBER INFO ── -->
    <!-- <div class="sub-info">
        <div class="sub-info-title">INSTALLATION SKETCH &amp; SUBSCRIBER DETAILS</div>
        <table class="sub-grid">
            <tr>
                <td class="lbl">Subscriber Name:</td>
                <td class="val"><?php echo strtoupper($customer['subscriber_name']); ?></td>
                <td class="lbl">Account #:</td>
                <td class="val"><?php echo $customer['account_number']; ?></td>
            </tr>
            <tr>
                <td class="lbl">Address:</td>
                <td class="val"><?php echo strtoupper($customer['address']); ?></td>
                <td class="lbl">Area:</td>
                <td class="val"><?php echo strtoupper($customer['area_name'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td class="lbl">Contact #:</td>
                <td class="val"><?php echo $customer['tel_no'] ?? 'N/A'; ?></td>
                <td class="lbl">Package:</td>
                <td class="val"><?php echo strtoupper($customer['package_name'] ?? 'N/A'); ?> (<?php echo $customer['bandwidth_mbps'] ?? 0; ?> Mbps)</td>
            </tr>
            <tr>
                <td class="lbl">Monthly Fee:</td>
                <td class="val"><?php echo format_currency($customer['monthly_fee']); ?></td>
                <td class="lbl">Status:</td>
                <td class="val"><?php echo strtoupper(str_replace('_', ' ', $customer['status'])); ?></td>
            </tr>
            <tr>
                <td class="lbl">Date Installed:</td>
                <td class="val"><?php echo $customer['installation_date'] ? date('F d, Y', strtotime($customer['installation_date'])) : 'Pending'; ?></td>
                <td class="lbl">Router Serial:</td>
                <td class="val"><?php echo $customer['router_serial'] ?? ''; ?></td>
            </tr>
        </table>
    </div> -->

    <!-- ── BODY ── -->
    <div class="body-content">

        <!-- ── TECHNICAL / MATERIALS / OTHERS TABLE ── -->
        <table class="main-table">
            <thead>
                <tr>
                    <th colspan="2">TECHNICAL REQUIREMENTS</th>
                    <th colspan="2">MATERIALS USED</th>
                    <th colspan="2">OTHERS</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="row-label">ROUTER<br>SN #</td>
                    <td class="val-cell"><?php echo htmlspecialchars($customer['router_serial'] ?? ''); ?></td>
                    <td class="item-label">NAP NUMBER</td>
                    <td class="val-cell"><?php echo htmlspecialchars($customer['nap_number'] ?? ''); ?></td>
                    <td class="item-label">FIBER OPTIC WIRE 1 CORE</td>
                    <td class="val-cell"><?php echo matVal('FIBER OPTIC 1 CORE', $mat_lookup); ?></td>
                </tr>
                <tr>
                    <td class="row-label">PORT<br>NUMBER</td>
                    <td class="val-cell"><?php echo htmlspecialchars($customer['port_number'] ?? ''); ?></td>
                    <td class="item-label">NAP OUT</td>
                    <td class="val-cell"><?php echo htmlspecialchars($customer['nap_output'] ?? ''); ?></td>
                    <td class="item-label">FIBER OPTIC WIRE 2 CORE</td>
                    <td class="val-cell"><?php echo matVal('FIBER OPTIC 2 CORE', $mat_lookup); ?></td>
                </tr>
                <tr>
                    <td class="row-label">LCP<br>NUMBER</td>
                    <td class="val-cell"><?php echo htmlspecialchars($customer['lcp_number'] ?? ''); ?></td>
                    <td class="item-label">FIBER OUTPUT</td>
                    <td class="val-cell"><?php echo htmlspecialchars($customer['fiber_output'] ?? ''); ?></td>
                    <td class="item-label">FIBER OPTIC SC CONN.</td>
                    <td class="val-cell"><?php echo matVal('SC CONNECTOR', $mat_lookup); ?></td>
                </tr>
                <tr>
                    <td class="row-label">NODE<br>NUMBER</td>
                    <td class="val-cell"><?php echo htmlspecialchars($customer['node_number'] ?? ''); ?></td>
                    <td class="item-label"></td>
                    <td class="val-cell"></td>
                    <td class="item-label">RG6 WIRE</td>
                    <td class="val-cell"><?php echo matVal('RG6-WIRE', $mat_lookup); ?></td>
                </tr>
                <tr>
                    <td class="row-label">MAC<br>ADDRESS</td>
                    <td class="val-cell"><?php echo htmlspecialchars($customer['mac_address'] ?? ''); ?></td>
                    <td class="item-label"></td>
                    <td class="val-cell"></td>
                    <td class="item-label">RG6 CONNECTOR</td>
                    <td class="val-cell"><?php echo matVal('RG6-CONNECTOR', $mat_lookup); ?></td>
                </tr>
                <tr>
                    <td class="row-label">VLAN</td>
                    <td class="val-cell"><?php echo htmlspecialchars($customer['vlan'] ?? ''); ?></td>
                    <td class="item-label">SPLITTER 2 WAYS</td>
                    <td class="val-cell"><?php echo matVal('2WAY SPLITTER', $mat_lookup); ?></td>
                    <td class="item-label">COUPLER</td>
                    <td class="val-cell"><?php echo matVal('COUPLER', $mat_lookup); ?></td>
                </tr>
                <tr>
                    <td class="row-label"></td>
                    <td class="val-cell"></td>
                    <td class="item-label">SPLITTER 3 WAYS</td>
                    <td class="val-cell"><?php echo matVal('3WAY SPLITTER', $mat_lookup); ?></td>
                    <td class="item-label">COUPLER 2 WAY</td>
                    <td class="val-cell"><?php echo matVal('COUPLER 2WAY', $mat_lookup); ?></td>
                </tr>
                <tr>
                    <td class="row-label"></td>
                    <td class="val-cell"></td>
                    <td class="item-label"></td>
                    <td class="val-cell"></td>
                    <td class="item-label">PASSIVE NODE</td>
                    <td class="val-cell"><?php echo matVal('PASSIVE NODE', $mat_lookup); ?></td>
                </tr>
                <tr>
                    <td class="row-label"></td>
                    <td class="val-cell"></td>
                    <td class="item-label"></td>
                    <td class="val-cell"></td>
                    <td class="item-label">ACTIVE NODE</td>
                    <td class="val-cell"><?php echo matVal('ACTIVE NODE', $mat_lookup); ?></td>
                </tr>
                <tr>
                    <td class="row-label"></td>
                    <td class="val-cell"></td>
                    <td class="item-label"></td>
                    <td class="val-cell"></td>
                    <td class="item-label">TERMINAL J. BOX</td>
                    <td class="val-cell"><?php echo matVal('TERMINAL JUNCTION BOX', $mat_lookup); ?></td>
                </tr>
                <tr>
                    <td class="row-label"></td>
                    <td class="val-cell"></td>
                    <td class="item-label"></td>
                    <td class="val-cell"></td>
                    <td class="item-label">F CLAMP</td>
                    <td class="val-cell"><?php echo matVal('F CLAMP', $mat_lookup); ?></td>
                </tr>
                <tr>
                    <td class="row-label"></td>
                    <td class="val-cell"></td>
                    <td class="item-label"></td>
                    <td class="val-cell"></td>
                    <td class="item-label">PATCH CORD</td>
                    <td class="val-cell"><?php echo matVal('PATCHCORD', $mat_lookup); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- ── SKETCH ── -->
        <div class="sketch-label">SKETCH</div>
        <div class="sketch-box">
            <?php if ($sketches && $sketches->num_rows > 0):
                $sk = $sketches->fetch_assoc(); ?>
                <?php if ($sk['sketch_type'] == 'upload' && $sk['file_path']): ?>
                    <img src="<?php echo htmlspecialchars($sk['file_path']); ?>" alt="Installation Sketch">
                <?php elseif ($sk['sketch_data']): ?>
                    <img src="<?php echo $sk['sketch_data']; ?>" alt="Installation Sketch Drawing">
                <?php endif; ?>
                <div class="sketch-meta">
                    <span>Created by: <?php echo htmlspecialchars($sk['creator_name'] ?? 'System Administrator'); ?></span>
                    <span><?php echo date('F d, Y h:i A', strtotime($sk['created_at'])); ?></span>
                </div>
            <?php else: ?>
                <div class="sketch-empty">No installation sketch uploaded yet.</div>
            <?php endif; ?>
        </div>

        <!-- ── FOOTER TABLE ── -->
        <table class="footer-table">
            <tr>
                <td style="width:34%">
                    <span class="footer-label">DATE INSTALLED:</span>
                    <?php echo $customer['installation_date'] ? date('F d, Y', strtotime($customer['installation_date'])) : ''; ?>
                </td>
                <td style="width:34%" colspan="3">
                    <span class="footer-label">INSTALLED BY:</span>
                    <?php echo htmlspecialchars($customer['installed_by'] ?? ''); ?>
                </td>
                
            </tr>
            <tr>
                <td style="width:34%">
                    <span class="footer-label">PREPARED BY:</span>
                    <br><br>
                    <div class="footer-name">MELANIA PALOMARIA</div>
                    <br>
                </td>
                <td style="width:34%">
                    <span class="footer-label">APPROVED BY:</span>
                    <br><br>
                    <div class="footer-name">ROGELIO PALOMARIA, JR.</div>
                    <br>
                </td>
                <td style="width:32%" rowspan="2">
                    <span class="footer-label">REMARKS:</span>
                    <br><br><br><br><br>
                </td>
            </tr>
        </table>

    </div><!-- /body-content -->
</div><!-- /page -->

<button class="print-btn no-print" onclick="window.print()">🖨️ Print Sketch Page</button>

</body>
</html>