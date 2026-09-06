<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = date('Y-m-d'); }

$payments = [];
$st = db_prepare("SELECT p.amount, p.payment_method, u.full_name FROM fee_payments p JOIN users u ON p.received_by = u.user_id WHERE DATE(p.created_at) = ? ORDER BY p.created_at");
$st->bind_param('s', $date);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) { $payments[] = $row; }

$fee_total = 0.0;
$staff = [];
$cash_count = 0;
$cash_total = 0.0;
foreach ($payments as $p) {
    $amt = (float) $p['amount'];
    $fee_total += $amt;
    if (strtolower($p['payment_method']) === 'cash') { $cash_count++; $cash_total += $amt; }
    $key = $p['full_name'] . '|' . $p['payment_method'];
    if (!isset($staff[$key])) {
        $staff[$key] = ['full_name' => $p['full_name'], 'method' => $p['payment_method'], 'count' => 0, 'amount' => 0.0];
    }
    $staff[$key]['count']++;
    $staff[$key]['amount'] += $amt;
}
ksort($staff);

$revenue_total = 0.0;
$st = db_prepare("SELECT description, amount FROM revenues WHERE revenue_date = ? ORDER BY revenue_id");
$st->bind_param('s', $date);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) { $revenue_total += (float) $row['amount']; }

$expenses = [];
$expense_total = 0.0;
$st = db_prepare("SELECT title, category, amount FROM expenses WHERE expense_date = ? ORDER BY expense_id");
$st->bind_param('s', $date);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) {
    $expenses[] = $row;
    $expense_total += (float) $row['amount'];
}

$total_income = $fee_total + $revenue_total;
$cash_in_hand = $total_income - $expense_total;
$symbol = get_setting('currency_symbol', 'Rs.');
?>
<!DOCTYPE HTML>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily Income & Expense Report | HIIFI LMS</title>
    <style type="text/css">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 30px;
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #2c3e50;
        }
        
        .report-title {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .report-subtitle {
            font-size: 18px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .report-date {
            font-size: 16px;
            color: #34495e;
            font-weight: 600;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        
        .income-section .section-title {
            border-bottom-color: #27ae60;
        }
        
        .expense-section .section-title {
            border-bottom-color: #e74c3c;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        thead tr:first-child th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 18px;
            font-weight: 600;
            padding: 15px;
            text-align: center;
            border: 1px solid #5a67d8;
        }
        
        thead tr:nth-child(2) th {
            background: #ecf0f1;
            color: #2c3e50;
            font-size: 16px;
            font-weight: 600;
            padding: 12px;
            text-align: center;
            border: 1px solid #bdc3c7;
        }
        
        tbody tr {
            transition: background-color 0.2s;
        }
        
        tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        tbody td {
            padding: 12px;
            text-align: left;
            border: 1px solid #dee2e6;
            font-size: 15px;
        }
        
        tbody td.center {
            text-align: center;
        }
        
        tbody td.amount {
            text-align: right;
            font-weight: 600;
            color: #2c3e50;
            font-family: 'Courier New', monospace;
        }
        
        .total-row {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            font-weight: 700;
        }
        
        .total-row th {
            background: transparent !important;
            color: white !important;
            font-size: 18px;
            padding: 15px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .cash-row {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            font-weight: 700;
        }
        
        .cash-row th {
            background: transparent !important;
            color: white !important;
            font-size: 18px;
            padding: 15px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .positive {
            color: #27ae60;
        }
        
        .negative {
            color: #e74c3c;
        }
        
        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .summary-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 20px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .summary-item {
            background: rgba(255,255,255,0.15);
            padding: 15px;
            border-radius: 6px;
            backdrop-filter: blur(10px);
        }
        
        .summary-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .report-container {
                box-shadow: none;
                padding: 20px;
            }
            
            tbody tr:hover {
                background-color: transparent;
            }
            
            .summary-box {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="report-header">
            <h1 class="report-title">Daily Income & Expense Report</h1>
            <div class="report-subtitle">Session: <?php echo e(get_setting('session_year', '2026-2027')); ?></div>
            <div class="report-date">Report Date: <?php echo date('d-M-Y', strtotime($date)); ?></div>
        </div>

        <div class="income-section">
            <h2 class="section-title">
                <i class="fa fa-arrow-down" style="color: #27ae60;"></i> Income Details
            </h2>
            
            <table>
                <thead>
                    <tr>
                        <th colspan="3">Income Summary</th>
                    </tr>
                    <tr>
                        <th width="10%">S.No</th>
                        <th width="60%">Particular</th>
                        <th width="30%">Amount (<?php echo e($symbol); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="center">1</td>
                        <td><strong>Today's Fee Collection</strong><br><small style="color: #7f8c8d;">Fee payments received today</small></td>
                        <td class="amount positive"><?php echo number_format($fee_total, 2); ?></td>
                    </tr>
                    <tr>
                        <td class="center">2</td>
                        <td><strong>Other Revenue</strong><br><small style="color: #7f8c8d;">Additional income sources</small></td>
                        <td class="amount positive"><?php echo number_format($revenue_total, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <th colspan="2">Total Income</th>
                        <th><?php echo number_format($total_income, 2); ?></th>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="collection-by-user-section">
            <h2 class="section-title" style="border-bottom-color: #3498db;">
                <i class="fa fa-users" style="color: #3498db;"></i> Fee Collection by Staff
            </h2>

            <table>
                <thead>
                    <tr>
                        <th colspan="3">Today's Fee Collection Breakdown by Person</th>
                    </tr>
                    <tr>
                        <th width="60%">Particular</th>
                        <th width="20%">Transactions</th>
                        <th width="20%">Amount (<?php echo e($symbol); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $si = 0; foreach ($staff as $row): $si++; ?>
                        <tr>
                            <td>User: <strong><?php echo e($row['full_name']); ?> (<?php echo e(ucfirst($row['method'])); ?>)</strong></td>
                            <td class="center"><?php echo $row['count']; ?> Transaction<?php echo $row['count'] > 1 ? 's' : ''; ?></td>
                            <td class="amount positive"><?php echo e(number_format($row['amount'], 0)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="border-top:2px solid #333;">
                        <td colspan="3" style="padding:3px;border-left:1px solid #dee2e6;border-right:1px solid #dee2e6;"></td>
                    </tr>
                    <tr class="total-row">
                        <th>Total Amount Received</th>
                        <th></th>
                        <th><?php echo e(number_format($fee_total, 0)); ?></th>
                    </tr>
                    <tr class="cash-row">
                        <th>Amount Received in Cash &ndash; <?php echo $cash_count; ?> Transaction<?php echo $cash_count > 1 ? 's' : ''; ?></th>
                        <th></th>
                        <th><?php echo e(number_format($cash_total, 0)); ?></th>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="expense-section">
            <h2 class="section-title">
                <i class="fa fa-arrow-up" style="color: #e74c3c;"></i> Expense Details
            </h2>
            
            <table>
                <thead>
                    <tr>
                        <th colspan="3">Expense Summary</th>
                    </tr>
                    <tr>
                        <th width="10%">S.No</th>
                        <th width="60%">Particular</th>
                        <th width="30%">Amount (<?php echo e($symbol); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expenses) === 0): ?>
                        <tr>
                            <td colspan="3" class="center" style="padding: 20px; color: #7f8c8d;">
                                <em>No expenses recorded for today</em>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php $ei = 0; foreach ($expenses as $ex): $ei++; ?>
                        <tr>
                            <td class="center"><?php echo $ei; ?></td>
                            <td><strong><?php echo e($ex['title']); ?></strong><br><small style="color: #7f8c8d;"><?php echo e($ex['category'] ?: 'General'); ?></small></td>
                            <td class="amount negative"><?php echo number_format($ex['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <th colspan="2">Total Expenses</th>
                        <th><?php echo number_format($expense_total, 2); ?></th>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="summary-box">
            <div class="summary-title">Financial Summary</div>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Total Income</div>
                    <div class="summary-value"><?php echo e($symbol); ?> <?php echo number_format($total_income, 2); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total Expenses</div>
                    <div class="summary-value"><?php echo e($symbol); ?> <?php echo number_format($expense_total, 2); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Cash In Hand</div>
                    <div class="summary-value" style="color: <?php echo $cash_in_hand >= 0 ? '#2ecc71' : '#ff6b6b'; ?>;">
                        <?php echo e($symbol); ?> <?php echo number_format($cash_in_hand, 2); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>