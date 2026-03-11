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
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Installation Sketch - <?php echo htmlspecialchars($customer['subscriber_name']); ?></title>
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:Arial,sans-serif;font-size:12px;background:#f5f5f5; }
        .page { width:210mm;min-height:297mm;margin:10px auto;background:white;padding:12mm;box-shadow:0 2px 10px rgba(0,0,0,0.1); }
        .header-logo { width:100%;max-height:65px;display:block;margin-bottom:10px; }
        .title { text-align:center;font-size:16px;font-weight:bold;color:#002060;margin-bottom:12px;text-decoration:underline; }
        .details-grid { display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;margin-bottom:15px;font-size:11px; }
        .detail-item { display:flex;margin-bottom:3px; }
        .detail-label { font-weight:bold;min-width:120px;color:#333; }
        .detail-value { border-bottom:1px solid #ccc;flex:1;padding-left:5px; }
        .sketch-section { margin-top:15px;page-break-inside:avoid; }
        .sketch-title { font-size:13px;font-weight:bold;color:#002060;margin-bottom:8px;border-bottom:2px solid #002060;padding-bottom:4px; }
        .sketch-container { border:2px solid #ddd;border-radius:8px;padding:10px;margin-bottom:12px;background:#fafafa; }
        .sketch-container img { max-width:100%;max-height:350px;display:block;margin:0 auto;border-radius:4px; }
        .sketch-meta { font-size:10px;color:#888;margin-top:6px;display:flex;justify-content:space-between; }
        .remarks { background:#fffde7;border-left:3px solid #f9a825;padding:6px 10px;margin-top:6px;font-size:11px; }
        .empty-sketch { border:2px dashed #ccc;height:200px;display:flex;align-items:center;justify-content:center;color:#999;font-style:italic; }
        .print-btn { display:block;margin:10px auto;padding:10px 30px;background:#002060;color:white;border:none;border-radius:5px;cursor:pointer;font-size:14px; }
        @media print {
            body { background:white; }
            .page { box-shadow:none;margin:0; }
            .print-btn,.no-print { display:none!important; }
            @page { size:A4;margin:0; }
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Print Sketch Page</button>
    
    <div class="page">
        <img src="images/headerlogo.png" alt="NovaLink" class="header-logo">
        <div class="title">INSTALLATION SKETCH & SUBSCRIBER DETAILS</div>
        
        <div class="details-grid">
            <div class="detail-item"><span class="detail-label">Subscriber Name:</span><span class="detail-value"><?php echo strtoupper($customer['subscriber_name']); ?></span></div>
            <div class="detail-item"><span class="detail-label">Account #:</span><span class="detail-value"><?php echo $customer['account_number']; ?></span></div>
            <div class="detail-item"><span class="detail-label">Address:</span><span class="detail-value"><?php echo strtoupper($customer['address']); ?></span></div>
            <div class="detail-item"><span class="detail-label">Area:</span><span class="detail-value"><?php echo strtoupper($customer['area_name'] ?? 'N/A'); ?></span></div>
            <div class="detail-item"><span class="detail-label">Contact #:</span><span class="detail-value"><?php echo $customer['tel_no'] ?? 'N/A'; ?></span></div>
            <div class="detail-item"><span class="detail-label">Package:</span><span class="detail-value"><?php echo strtoupper($customer['package_name'] ?? 'N/A'); ?> (<?php echo $customer['bandwidth_mbps'] ?? 0; ?> Mbps)</span></div>
            <div class="detail-item"><span class="detail-label">Monthly Fee:</span><span class="detail-value"><?php echo format_currency($customer['monthly_fee']); ?></span></div>
            <div class="detail-item"><span class="detail-label">Status:</span><span class="detail-value"><?php echo strtoupper(str_replace('_',' ',$customer['status'])); ?></span></div>
            <div class="detail-item"><span class="detail-label">Date Installed:</span><span class="detail-value"><?php echo $customer['installation_date'] ? date('F d, Y', strtotime($customer['installation_date'])) : 'Pending'; ?></span></div>
            <div class="detail-item"><span class="detail-label">Router Serial:</span><span class="detail-value"><?php echo $customer['router_serial'] ?? ''; ?></span></div>
        </div>
        
        <div class="sketch-section">
            <div class="sketch-title">Installation Sketch / Location Photo</div>
            
            <?php if ($sketches && $sketches->num_rows > 0): ?>
                <?php while ($sk = $sketches->fetch_assoc()): ?>
                <div class="sketch-container">
                    <?php if ($sk['sketch_type'] == 'upload' && $sk['file_path']): ?>
                        <img src="<?php echo htmlspecialchars($sk['file_path']); ?>" alt="Installation Sketch">
                    <?php elseif ($sk['sketch_data']): ?>
                        <img src="<?php echo $sk['sketch_data']; ?>" alt="Installation Sketch Drawing">
                    <?php endif; ?>
                    
                    <?php if ($sk['remarks']): ?>
                    <div class="remarks"><strong>Remarks:</strong> <?php echo htmlspecialchars($sk['remarks']); ?></div>
                    <?php endif; ?>
                    
                    <div class="sketch-meta">
                        <span>Created by: <?php echo htmlspecialchars($sk['creator_name'] ?? 'Unknown'); ?></span>
                        <span><?php echo date('F d, Y h:i A', strtotime($sk['created_at'])); ?></span>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-sketch">No installation sketch uploaded yet.</div>
            <?php endif; ?>
        </div>
        
        <div style="margin-top:30px;display:flex;justify-content:space-between;font-size:11px;">
            <div>Prepared by: _________________________</div>
            <div>Approved by: _________________________</div>
            <div>Date: _________________________</div>
        </div>
    </div>
    
    <button class="print-btn no-print" onclick="window.print()">🖨️ Print Sketch Page</button>
</body>
</html>
