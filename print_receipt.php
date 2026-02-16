<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($payment_id == 0) {
    die("Invalid payment ID");
}

$conn = getDBConnection();

// Get payment details
$stmt = $conn->prepare("
    SELECT p.*, c.account_number, c.subscriber_name, c.address, c.tel_no,
           b.billing_month, b.billing_year, b.net_amount,
           u.full_name as cashier_name
    FROM payments p
    JOIN customers c ON p.customer_id = c.customer_id
    JOIN billings b ON p.billing_id = b.billing_id
    LEFT JOIN users u ON p.cashier_id = u.user_id
    WHERE p.payment_id = ?
");

$stmt->bind_param("i", $payment_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$payment) {
    die("Payment not found");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - AR NOVALINK</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #002060;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #002060;
            font-size: 32px;
            margin-bottom: 5px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .receipt-title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #002060;
            margin-bottom: 30px;
        }
        
        .receipt-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #666;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .payment-details {
            border: 2px solid #002060;
            padding: 20px;
            margin: 30px 0;
            border-radius: 5px;
        }
        
        .payment-details h3 {
            color: #002060;
            margin-bottom: 15px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-row.total {
            font-size: 20px;
            font-weight: bold;
            color: #002060;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid #002060;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #002060;
        }
        
        .signature {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 50px;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 10px;
            font-weight: bold;
        }
        
        .signature-label {
            color: #666;
            font-size: 12px;
        }
        
        .print-btn {
            background: #002060;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            margin: 20px auto;
            display: block;
        }
        
        .print-btn:hover {
            background: #001540;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .receipt {
                box-shadow: none;
                padding: 20px;
            }
            
            .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print Receipt</button>
    
    <div class="receipt">
        <div class="header">
            <h1>AR NOVALINK</h1>
            <p>Internet Service Provider</p>
            <p>Official Receipt</p>
        </div>
        
        <div class="receipt-title">
            PAYMENT RECEIPT
        </div>
        
        <div class="receipt-info">
            <div>
                <div class="info-group">
                    <div class="info-label">OR Number:</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($payment['or_number']); ?></strong></div>
                </div>
                
                <div class="info-group">
                    <div class="info-label">Date:</div>
                    <div class="info-value"><?php echo date('F d, Y', strtotime($payment['payment_date'])); ?></div>
                </div>
                
                <div class="info-group">
                    <div class="info-label">Payment Method:</div>
                    <div class="info-value"><?php echo ucfirst($payment['payment_method']); ?></div>
                </div>
            </div>
            
            <div>
                <div class="info-group">
                    <div class="info-label">Account Number:</div>
                    <div class="info-value"><?php echo htmlspecialchars($payment['account_number']); ?></div>
                </div>
                
                <div class="info-group">
                    <div class="info-label">Customer Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($payment['subscriber_name']); ?></div>
                </div>
                
                <div class="info-group">
                    <div class="info-label">Address:</div>
                    <div class="info-value"><?php echo htmlspecialchars($payment['address']); ?></div>
                </div>
            </div>
        </div>
        
        <div class="payment-details">
            <h3>Payment Details</h3>
            
            <div class="detail-row">
                <span>Billing Period:</span>
                <span><strong><?php echo get_month_name($payment['billing_month']) . ' ' . $payment['billing_year']; ?></strong></span>
            </div>
            
            <div class="detail-row">
                <span>Bill Amount:</span>
                <span><?php echo format_currency($payment['net_amount']); ?></span>
            </div>
            
            <div class="detail-row total">
                <span>Amount Paid:</span>
                <span><?php echo format_currency($payment['amount_paid']); ?></span>
            </div>
            
            <?php if ($payment['remarks']): ?>
            <div class="detail-row">
                <span>Remarks:</span>
                <span><?php echo htmlspecialchars($payment['remarks']); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p><strong>Processed by:</strong> <?php echo htmlspecialchars($payment['cashier_name'] ?? 'System'); ?></p>
            <p><strong>Issued on:</strong> <?php echo date('F d, Y h:i A', strtotime($payment['created_at'])); ?></p>
            
            <div class="signature">
                <div class="signature-box">
                    <div class="signature-line">
                        <?php echo htmlspecialchars($payment['cashier_name'] ?? 'Cashier'); ?>
                    </div>
                    <div class="signature-label">Cashier Signature</div>
                </div>
                
                <div class="signature-box">
                    <div class="signature-line">
                        <?php echo htmlspecialchars($payment['subscriber_name']); ?>
                    </div>
                    <div class="signature-label">Customer Signature</div>
                </div>
            </div>
            
            <p style="text-align: center; margin-top: 30px; color: #666; font-size: 12px;">
                Thank you for your payment!<br>
                This is an official receipt. Please keep for your records.
            </p>
        </div>
    </div>
    
    <button class="print-btn" onclick="window.print()">Print Receipt</button>
</body>
</html>