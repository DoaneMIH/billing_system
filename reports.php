<?php
require_once 'config.php';
check_permission('accounting');

$conn = getDBConnection();

// Get filters
$report_type = isset($_GET['report_type']) ? sanitize_input($_GET['report_type']) : 'monthly_billing';
$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : date('Y-m-d');
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$area_filter = isset($_GET['area']) ? intval($_GET['area']) : 0;

// Get areas for filter
$areas = $conn->query("SELECT * FROM areas ORDER BY area_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - AR NOVALINK Billing System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white;
            }
            .report-container {
                box-shadow: none;
                padding: 0;
            }
            .main-header {
                display: none !important;
            }
            .container {
                display: block !important;
            }
            .sidebar {
                display: none !important;
            }
            .main-content {
                padding: 0 !important;
                margin: 0 !important;
            }
        }
        
        .report-container {
            background: white;
            padding: 30px;
            margin: 20px 0;
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid var(--primary-color);
            padding-bottom: 20px;
        }
        
        .report-header h1 {
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .report-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header no-print">
                <h1>Reports & Analytics</h1>
                <p>Generate comprehensive reports with filters</p>
            </div>
            
            <!-- Report Type Selection -->
            <div class="widget mb-3 no-print">
                <div class="widget-header">
                    <h2>Select Report Type</h2>
                </div>
                <div class="widget-content">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="report_type">Report Type</label>
                            <select id="report_type" onchange="window.location.href='reports.php?report_type=' + this.value">
                                <option value="monthly_billing" <?php echo $report_type == 'monthly_billing' ? 'selected' : ''; ?>>Monthly Billing Report</option>
                                <option value="monthly_sales" <?php echo $report_type == 'monthly_sales' ? 'selected' : ''; ?>>Monthly Sales Report</option>
                                <option value="unpaid_accounts" <?php echo $report_type == 'unpaid_accounts' ? 'selected' : ''; ?>>Unpaid Accounts Report</option>
                                <option value="for_disconnection" <?php echo $report_type == 'for_disconnection' ? 'selected' : ''; ?>>Customers for Disconnection</option>
                                <option value="last_payment" <?php echo $report_type == 'last_payment' ? 'selected' : ''; ?>>Last Payment Dates Report</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="widget mb-3 no-print">
                <div class="widget-header">
                    <h2>Report Filters</h2>
                </div>
                <div class="widget-content">
                    <form method="GET" action="">
                        <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="month">Month</label>
                                <select id="month" name="month">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == $month_filter ? 'selected' : ''; ?>>
                                        <?php echo get_month_name($m); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="year">Year</label>
                                <select id="year" name="year">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $year_filter ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="area">Area/Barangay</label>
                                <select id="area" name="area">
                                    <option value="0">All Areas</option>
                                    <?php 
                                    $areas->data_seek(0);
                                    while ($area = $areas->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $area['area_id']; ?>" <?php echo $area_filter == $area['area_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($area['area_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="display: flex; align-items: flex-end; gap: 10px;">
                                <button type="submit" class="btn btn-primary">Generate Report</button>
                                <button type="button" onclick="window.print()" class="btn btn-secondary">Print / PDF</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Report Content -->
            <div class="report-container">
                <?php
                // Include appropriate report based on selection
                switch ($report_type) {
                    case 'monthly_billing':
                        include 'monthly_billing_report.php';
                        break;
                    case 'monthly_sales':
                        include 'monthly_sales_report.php';
                        break;
                    case 'unpaid_accounts':
                        include 'unpaid_accounts_report.php';
                        break;
                    case 'for_disconnection':
                        include 'for_disconnection_report.php';
                        break;
                    case 'last_payment':
                        include 'last_payment_report.php';
                        break;
                }
                ?>
            </div>
        </main>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>