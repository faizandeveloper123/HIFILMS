<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$month = (int) ($_GET['month'] ?? (int) date('n'));
$year = (int) ($_GET['year'] ?? (int) date('Y'));
if ($month < 1 || $month > 12) { $month = (int) date('n'); }
if ($year < 2000 || $year > 2100) { $year = (int) date('Y'); }

$dstart = sprintf('%04d-%02d-01', $year, $month);
$dend = date('Y-m-t', strtotime($dstart));
$day_start = new DateTime($dstart);
$day_end = new DateTime($dend);
$symbol = get_setting('currency_symbol', 'Rs.');

$mode_buckets = ['cash' => 0.0, 'jazz' => 0.0, 'easypaisa' => 0.0, 'bank' => 0.0];
$fee_total = 0.0;
$mode_labels = ['cash' => 'Cash', 'jazz' => 'Jazz Cash', 'easypaisa' => 'Easypaisa', 'bank' => 'Bank Account'];

$st = db_prepare("SELECT amount, payment_method FROM fee_payments WHERE DATE(created_at) BETWEEN ? AND ?");
$st->bind_param('ss', $dstart, $dend);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) {
    $amt = (float) $row['amount'];
    $fee_total += $amt;
    $m = strtolower($row['payment_method']);
    if (strpos($m, 'jazz') !== false) { $mode_buckets['jazz'] += $amt; }
    elseif (strpos($m, 'easi') !== false || strpos($m, 'easypaisa') !== false) { $mode_buckets['easypaisa'] += $amt; }
    elseif ($m === 'bank' || strpos($m, 'acc') !== false) { $mode_buckets['bank'] += $amt; }
    else { $mode_buckets['cash'] += $amt; }
}

$other_revenue = 0.0;
$st = db_prepare("SELECT amount FROM revenues WHERE revenue_date BETWEEN ? AND ?");
$st->bind_param('ss', $dstart, $dend);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) { $other_revenue += (float) $row['amount']; }

$pos_revenue = 0.0;
$st = db_prepare("SELECT paid_amount FROM pos_invoices WHERE status = 'paid' AND DATE(created_at) BETWEEN ? AND ?");
$st->bind_param('ss', $dstart, $dend);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) { $pos_revenue += (float) $row['paid_amount']; }

$salary_total = 0.0;
$operating_total = 0.0;
$st = db_prepare("SELECT title, category, category_id, amount FROM expenses WHERE expense_date BETWEEN ? AND ?");
$st->bind_param('ss', $dstart, $dend);
$st->execute();
$res = $st->get_result();
$is_salary = function ($row) {
    if ((int) $row['category_id'] === 2) { return true; }
    $hay = strtolower(($row['category'] ?? '') . ' ' . ($row['title'] ?? ''));
    return strpos($hay, 'salar') !== false || strpos($hay, 'wage') !== false;
};
while ($row = $res->fetch_assoc()) {
    if ($is_salary($row)) { $salary_total += (float) $row['amount']; }
    else { $operating_total += (float) $row['amount']; }
}
$expense_total = $operating_total + $salary_total;

$staff_rows = [];
$st = db_prepare("SELECT u.full_name, COUNT(p.payment_id) AS cnt, SUM(p.amount) AS amt
                  FROM fee_payments p JOIN users u ON p.received_by = u.user_id
                  WHERE DATE(p.created_at) BETWEEN ? AND ?
                  GROUP BY p.received_by, u.full_name ORDER BY u.full_name");
$st->bind_param('ss', $dstart, $dend);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) { $staff_rows[] = $row; }

$total_revenue = $fee_total + $other_revenue + $pos_revenue;
$profit = $total_revenue - $expense_total;
?>
<!DOCTYPE HTML>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monthly Closing Report | HIIFI LMS</title>
    <style type="text/css">
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            position: relative;
        }
        .filter-btn-wrap {
            position: absolute;
            top: 0;
            right: 0;
        }
        .btn-filter {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            background: #1a237e;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
        }
        .btn-filter:hover { background: #283593; transform: translateY(-1px); }
        .report-title {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .report-subtitle { font-size: 18px; color: #7f8c8d; margin-bottom: 5px; }
        .report-range {
            font-size: 17px;
            font-weight: 600;
            color: #2c3e50;
            margin: 12px 0 6px;
        }
        .report-range-detail { font-size: 14px; color: #5c6bc0; }
        .report-meta {
            font-size: 14px;
            color: #95a5a6;
            margin-top: 14px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        .expense-section .section-title { border-bottom-color: #e74c3c; }
        .revenue-section .section-title { border-bottom-color: #27ae60; }
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
        tbody tr { transition: background-color 0.2s; }
        tbody tr:hover { background-color: #f8f9fa; }
        tbody td {
            padding: 12px;
            text-align: left;
            border: 1px solid #dee2e6;
            font-size: 15px;
        }
        tbody td.center { text-align: center; }
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
        .profit-row {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            font-weight: 700;
        }
        .profit-row.loss {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }
        .profit-row th {
            background: transparent !important;
            color: white !important;
            font-size: 20px;
            padding: 18px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .positive { color: #27ae60; }
        .negative { color: #e74c3c; }
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
        .payment-mode-item { padding: 8px 0; border-bottom: 1px solid #ecf0f1; }
        .payment-mode-item:last-child { border-bottom: none; }

        .dr-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .dr-overlay.open { display: flex; }
        .dr-modal {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            display: flex;
            max-width: 720px;
            width: 100%;
            overflow: hidden;
            max-height: 90vh;
        }
        .dr-main { flex: 1; padding: 20px 24px; min-width: 0; overflow: auto; }
        .dr-field {
            margin-bottom: 20px;
        }
        .dr-field label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #37474f;
            margin-bottom: 8px;
        }
        .dr-field select {
            width: 100%;
            padding: 10px 12px;
            font-size: 15px;
            border: 1px solid #cfd8dc;
            border-radius: 8px;
            background: #fff;
            color: #37474f;
        }
        .dr-apply {
            margin-top: 6px;
            padding: 14px;
            background: #1a237e;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
        }
        .dr-apply:hover { background: #283593; }
        .dr-close-x {
            position: absolute;
            top: 12px;
            right: 12px;
            border: none;
            background: transparent;
            font-size: 22px;
            color: #90a4ae;
            cursor: pointer;
            line-height: 1;
        }
        .dr-close-x:hover { color: #37474f; }

        @media print {
            body { background: white; padding: 0; }
            .report-container { box-shadow: none; padding: 20px; }
            tbody tr:hover { background-color: transparent; }
            .summary-box { box-shadow: none; }
            .filter-btn-wrap, .dr-overlay { display: none !important; }
        }
        @media (max-width: 640px) {
            .dr-modal { flex-direction: column; max-height: 85vh; }
            .filter-btn-wrap { position: static; margin-bottom: 16px; text-align: right; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="report-header">
            <div class="filter-btn-wrap">
                <button type="button" class="btn-filter" id="openDateFilter" aria-haspopup="dialog">
                    <i class="fa fa-filter"></i> Filter
                </button>
            </div>
            <h1 class="report-title">Monthly Profit & Loss Report</h1>
            <div class="report-subtitle">Branch closing — revenue & expenses</div>
            <div class="report-range"><?php echo $day_start->format('d M Y') . ' — ' . $day_end->format('d M Y'); ?></div>
            <div class="report-range-detail"><?php echo $day_start->format('l, j F Y') . ' to ' . $day_end->format('l, j F Y'); ?></div>
            <div class="report-meta">
                <span>Generated: <?php echo date('d-M-Y h:i:s A'); ?></span>
            </div>
        </div>

        <div class="expense-section">
            <h2 class="section-title">
                <i class="fa fa-arrow-up" style="color: #e74c3c;"></i> Expenses
            </h2>
            <table>
                <thead>
                    <tr>
                        <th colspan="3">Expense Summary</th>
                    </tr>
                    <tr>
                        <th width="10%">S.No</th>
                        <th width="60%">Category</th>
                        <th width="30%">Amount (<?php echo e($symbol); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="center">1</td>
                        <td><strong>Operating Expenses</strong><br><small style="color: #7f8c8d;">General expenses and overheads</small></td>
                        <td class="amount negative"><?php echo number_format($operating_total, 2); ?></td>
                    </tr>
                    <tr>
                        <td class="center">2</td>
                        <td><strong>Wages & Salary</strong><br><small style="color: #7f8c8d;">Employee compensation and benefits</small></td>
                        <td class="amount negative"><?php echo number_format($salary_total, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <th colspan="2">Total Expenses</th>
                        <th><?php echo number_format($expense_total, 2); ?></th>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="revenue-section">
            <h2 class="section-title">
                <i class="fa fa-arrow-down" style="color: #27ae60;"></i> Revenue
            </h2>
            <table>
                <thead>
                    <tr>
                        <th colspan="3">Revenue Summary</th>
                    </tr>
                    <tr>
                        <th width="10%">S.No</th>
                        <th width="60%">Category</th>
                        <th width="30%">Amount (<?php echo e($symbol); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="center" rowspan="5">3</td>
                        <td rowspan="5"><strong>Fee Collection</strong><br><small style="color: #7f8c8d;">Student fee payments by payment mode</small></td>
                        <td class="payment-mode-item">
                            <strong>Cash:</strong> <?php echo number_format($mode_buckets['cash'], 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="payment-mode-item">
                            <strong>Jazz Cash:</strong> <?php echo number_format($mode_buckets['jazz'], 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="payment-mode-item">
                            <strong>Easypaisa:</strong> <?php echo number_format($mode_buckets['easypaisa'], 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="payment-mode-item">
                            <strong>Bank Account:</strong> <?php echo number_format($mode_buckets['bank'], 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="amount positive" style="font-weight: 700; border-top: 2px solid #3498db;">
                            <strong>Total Fee Revenue:</strong> <?php echo number_format($fee_total, 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="center">4</td>
                        <td><strong>Other Revenue</strong><br><small style="color: #7f8c8d;">Additional income sources</small></td>
                        <td class="amount positive"><?php echo number_format($other_revenue, 2); ?></td>
                    </tr>
                    <tr>
                        <td class="center">5</td>
                        <td><strong>POS Revenue</strong><br><small style="color: #7f8c8d;">Canteen/POS sales</small></td>
                        <td class="amount positive"><?php echo number_format($pos_revenue, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <th colspan="2">Total Revenue</th>
                        <th><?php echo number_format($total_revenue, 2); ?></th>
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
                        <th colspan="4">Fee Collection Breakdown by Person (<?php echo $day_start->format('d M Y') . ' — ' . $day_end->format('d M Y'); ?>)</th>
                    </tr>
                    <tr>
                        <th width="10%">S.No</th>
                        <th width="40%">Staff Name</th>
                        <th width="25%">Transactions</th>
                        <th width="25%">Amount Collected (<?php echo e($symbol); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($staff_rows) === 0): ?>
                        <tr>
                            <td colspan="4" class="center" style="padding: 20px; color: #7f8c8d;">
                                <em>No fee collection recorded in this period</em>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php $si = 0; foreach ($staff_rows as $sr): $si++; ?>
                        <tr>
                            <td class="center"><?php echo $si; ?></td>
                            <td><strong><?php echo e($sr['full_name']); ?></strong></td>
                            <td class="center"><?php echo (int) $sr['cnt']; ?> payment(s)</td>
                            <td class="amount positive"><?php echo number_format($sr['amt'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <th colspan="3">Total Collected by All Staff</th>
                        <th><?php echo number_format($fee_total, 2); ?></th>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="summary-box">
            <div class="summary-title">Financial Summary</div>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Total Revenue</div>
                    <div class="summary-value"><?php echo e($symbol); ?> <?php echo number_format($total_revenue, 2); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total Expenses</div>
                    <div class="summary-value"><?php echo e($symbol); ?> <?php echo number_format($expense_total, 2); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Profit</div>
                    <div class="summary-value" style="color: <?php echo $profit >= 0 ? '#2ecc71' : '#ff6b6b'; ?>;">
                        <?php echo e($symbol); ?> <?php echo number_format($profit, 2); ?>
                    </div>
                </div>
            </div>
        </div>

        <table>
            <tbody>
                <tr class="profit-row <?php echo $profit < 0 ? 'loss' : ''; ?>">
                    <th colspan="2" style="text-align: center; font-size: 22px;">
                        <?php echo $profit < 0 ? 'NET LOSS' : 'NET PROFIT'; ?>
                    </th>
                    <th style="font-size: 22px;">
                        <?php echo e($symbol); ?> <?php echo number_format(abs($profit), 2); ?>
                    </th>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="dr-overlay" id="dateRangeOverlay" role="dialog" aria-modal="true" aria-labelledby="drModalTitle">
        <div class="dr-modal" style="position:relative;">
            <button type="button" class="dr-close-x" id="closeDateFilter" aria-label="Close">&times;</button>
            <div class="dr-main">
                <h2 id="drModalTitle">Select Month</h2>
                <form method="get" action="<?php echo BASE_URL; ?>print_profitloss_report.php" id="monthForm" style="margin-top:12px;">
                    <div class="dr-field">
                        <label for="month">Month</label>
                        <select name="month" id="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $month === $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="dr-field">
                        <label for="year">Year</label>
                        <select name="year" id="year">
                            <?php for ($y = (int) date('Y') - 4; $y <= (int) date('Y') + 1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="dr-apply">Apply</button>
                </form>
            </div>
        </div>
    </div>

    <script>
(function() {
    var overlay = document.getElementById('dateRangeOverlay');
    document.getElementById('openDateFilter').addEventListener('click', function() {
        overlay.classList.add('open');
    });
    document.getElementById('closeDateFilter').addEventListener('click', function() {
        overlay.classList.remove('open');
    });
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.classList.remove('open');
    });
})();
    </script>
</body>
</html>