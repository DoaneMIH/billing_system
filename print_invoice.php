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
           b.billing_month, b.billing_year, b.internet_fee, b.cable_fee, 
           b.service_fee, b.material_fee, b.discount, b.net_amount,
           u.full_name as cashier_name,
           a.area_name
    FROM payments p
    JOIN customers c ON p.customer_id = c.customer_id
    JOIN billings b ON p.billing_id = b.billing_id
    LEFT JOIN users u ON p.cashier_id = u.user_id
    LEFT JOIN areas a ON c.area_id = a.area_id
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

// Format invoice number (matching the form: 0002046)
$invoice_number = str_pad($payment['payment_id'], 7, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Invoice - Nova Link Digital Systems</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', Courier, monospace;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .invoice {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .company-info {
            font-size: 11px;
            line-height: 1.4;
        }
        
        .invoice-title-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
        }
        
        .payment-type {
            display: flex;
            gap: 20px;
        }
        
        .checkbox {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .checkbox-box {
            width: 15px;
            height: 15px;
            border: 2px solid #000;
            display: inline-block;
        }
        
        .checkbox-box.checked::after {
            content: '✓';
            display: block;
            text-align: center;
            line-height: 11px;
            font-weight: bold;
        }
        
        .invoice-title {
            font-size: 16px;
            font-weight: bold;
            text-decoration: underline;
        }
        
        .date-field {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 150px;
            text-align: center;
        }
        
        .sold-to {
            margin: 20px 0;
        }
        
        .field-row {
            margin: 8px 0;
            display: flex;
        }
        
        .field-label {
            font-weight: bold;
            min-width: 140px;
        }
        
        .field-value {
            border-bottom: 1px solid #000;
            flex: 1;
            padding-left: 10px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        
        .items-table th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        .items-table .amount-col {
            text-align: right;
        }
        
        .totals-section {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .disclaimer {
            border: 2px solid #000;
            padding: 10px;
            width: 45%;
            font-size: 10px;
            text-align: center;
        }
        
        .received-section {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .totals-right {
            width: 50%;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #ccc;
        }
        
        .total-row.main {
            font-weight: bold;
            font-size: 14px;
            border-bottom: 2px solid #000;
            margin-top: 10px;
        }
        
        .signatures {
            margin-top: 30px;
            padding: 20px 0;
            border-top: 1px solid #000;
        }
        
        .signature-line {
            display: inline-block;
            min-width: 200px;
            border-bottom: 1px solid #000;
            text-align: center;
        }
        
        .footer-info {
            margin-top: 20px;
            font-size: 9px;
            line-height: 1.3;
        }
        
        .invoice-number-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #000;
        }
        
        .invoice-no {
            font-size: 14px;
            font-weight: bold;
        }
        
        .invoice-no-value {
            color: #d32f2f;
            font-size: 24px;
            font-weight: bold;
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
            
            .invoice {
                box-shadow: none;
                padding: 20px;
                max-width: 100%;
            }
            
            .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print Invoice</button>
    
    <div class="invoice">
        <!-- Header -->
        <div class="header">
            <div class="company-name">NOVA LINK DIGITAL SYSTEMS CORP.</div>
            <div class="company-info">
                Non-VAT Reg. TIN: 686-114-344-00000<br>
                F. Palmares Street Poblacion Ilaya, City of Passi, Iloilo
            </div>
        </div>
        
        <!-- Invoice Title & Payment Type -->
        <div class="invoice-title-section">
            <div class="payment-type">
                <div class="checkbox">
                    <span class="checkbox-box <?php echo $payment['payment_method'] == 'cash' ? 'checked' : ''; ?>"></span>
                    <span>Cash</span>
                </div>
                <div class="checkbox">
                    <span class="checkbox-box <?php echo $payment['payment_method'] == 'check' ? 'checked' : ''; ?>"></span>
                    <span>Charge</span>
                </div>
            </div>
            <div>
                <div class="invoice-title">SERVICE INVOICE</div>
                <div style="margin-top: 10px;">
                    Date <span class="date-field"><?php echo date('F d, Y', strtotime($payment['payment_date'])); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Sold To Section -->
        <div class="sold-to">
            <div style="font-weight: bold; margin-bottom: 10px;">SOLD TO:</div>
            
            <div class="field-row">
                <span class="field-label">Registered Name</span>
                <span class="field-value"><?php echo htmlspecialchars($payment['subscriber_name']); ?></span>
            </div>
            
            <div class="field-row">
                <span class="field-label">TIN</span>
                <span class="field-value"><?php echo htmlspecialchars($payment['account_number']); ?></span>
            </div>
            
            <div class="field-row">
                <span class="field-label">Business Address</span>
                <span class="field-value"><?php echo htmlspecialchars($payment['address']); ?></span>
            </div>
        </div>
        
        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>ITEM DESCRIPTION / NATURE OF SERVICE</th>
                    <th width="10%">QTY.</th>
                    <th width="15%">Unit Price</th>
                    <th width="15%">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($payment['internet_fee'] > 0): ?>
                <tr>
                    <td>Internet Subscription - <?php echo get_month_name($payment['billing_month']) . ' ' . $payment['billing_year']; ?></td>
                    <td style="text-align: center;">1</td>
                    <td class="amount-col"><?php echo number_format($payment['internet_fee'], 2); ?></td>
                    <td class="amount-col"><?php echo number_format($payment['internet_fee'], 2); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if ($payment['cable_fee'] > 0): ?>
                <tr>
                    <td>Cable TV Service</td>
                    <td style="text-align: center;">1</td>
                    <td class="amount-col"><?php echo number_format($payment['cable_fee'], 2); ?></td>
                    <td class="amount-col"><?php echo number_format($payment['cable_fee'], 2); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if ($payment['service_fee'] > 0): ?>
                <tr>
                    <td>Service Fee</td>
                    <td style="text-align: center;">1</td>
                    <td class="amount-col"><?php echo number_format($payment['service_fee'], 2); ?></td>
                    <td class="amount-col"><?php echo number_format($payment['service_fee'], 2); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if ($payment['material_fee'] > 0): ?>
                <tr>
                    <td>Materials/Installation Fee</td>
                    <td style="text-align: center;">1</td>
                    <td class="amount-col"><?php echo number_format($payment['material_fee'], 2); ?></td>
                    <td class="amount-col"><?php echo number_format($payment['material_fee'], 2); ?></td>
                </tr>
                <?php endif; ?>
                
                <!-- Empty rows for formatting -->
                <?php for ($i = 0; $i < (6 - ($payment['internet_fee'] > 0 ? 1 : 0) - ($payment['cable_fee'] > 0 ? 1 : 0) - ($payment['service_fee'] > 0 ? 1 : 0) - ($payment['material_fee'] > 0 ? 1 : 0)); $i++): ?>
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        
        <!-- Totals Section -->
        <div class="totals-section">
            <div style="width: 45%;">
                <div class="disclaimer">
                    "THIS DOCUMENT IS NOT VALID<br>FOR CLAIM OF INPUT TAX"
                </div>
                
                <div class="received-section">
                    <span class="checkbox-box checked"></span>
                    <span>Received the amount of</span>
                </div>
                
                <div style="margin-top: 10px; border-bottom: 1px solid #000; min-height: 30px; padding: 5px;">
                    <?php 
                    $words = ['Zero', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
                    echo '₱' . number_format($payment['amount_paid'], 2);
                    ?>
                </div>
            </div>
            
            <div class="totals-right">
                <div class="total-row">
                    <span>Total Sales</span>
                    <span><?php echo number_format($payment['net_amount'], 2); ?></span>
                </div>
                
                <?php if ($payment['discount'] > 0): ?>
                <div class="total-row">
                    <span>Less: Discount</span>
                    <span>(<?php echo number_format($payment['discount'], 2); ?>)</span>
                </div>
                <?php endif; ?>
                
                <div class="total-row">
                    <span>Less: Withholding Tax</span>
                    <span>0.00</span>
                </div>
                
                <div class="total-row main">
                    <span>TOTAL AMOUNT DUE</span>
                    <span>₱ <?php echo number_format($payment['amount_paid'], 2); ?></span>
                </div>
                
                <div style="margin-top: 10px; font-size: 10px;">
                    <div>SC/PWD/NAAC/MOVI</div>
                    <div>Solo Parent ID No.: ______________</div>
                    <div>SC/PWD/NAAC/MOVI</div>
                    <div>Solo Parent Signature: ______________</div>
                </div>
            </div>
        </div>
        
        <!-- Footer Info -->
        <div class="footer-info">
            <div>Approved Series: 001-5,000 100 Bklts. (50x2)</div>
            <div>BIR ATP No.: 075AU20250000003654</div>
            <div>Date Issued: 9/29/2025</div>
            <div>D-4 ANGELS PRINT HAUS, INC.;</div>
            <div>M.V. Hechanova, Jaro, Iloilo City 0525</div>
            <div>TIN: 006-459-157-00000 VAT Tel. No. (019) 3200525</div>
            <div>Accreditation No. 071020240000000001 on 01/05/2024</div>
        </div>
        
        <!-- Signatures -->
        <div class="signatures" style="text-align: right;">
            <div style="margin-bottom: 40px;">
                <div class="signature-line"><?php echo htmlspecialchars($payment['cashier_name'] ?? 'Cashier'); ?></div>
            </div>
            <div style="font-size: 11px;">Cashier/Authorized Representative</div>
        </div>
        
        <!-- Invoice Number -->
        <div class="invoice-number-section">
            <div style="width: 60%;">
                <!-- Empty space for printing -->
            </div>
            <div style="text-align: right;">
                <div class="invoice-no">INVOICE N<sup>o</sup></div>
                <div class="invoice-no-value"><?php echo $invoice_number; ?></div>
            </div>
        </div>
    </div>
    
    <button class="print-btn" onclick="window.print()">Print Invoice</button>
</body>
</html>