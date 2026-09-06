<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Accounts Dashboard';

$today = date('Y-m-d');
$month = date('m');
$year = date('Y');
$userName = e($_SESSION['user_name'] ?? 'Accounts');

$totalCollected = (float) (@db_query("SELECT COALESCE(SUM(amount),0) t FROM fee_payments")->fetch_assoc()['t'] ?? 0);
$pendingFee = (float) (@db_query("SELECT COALESCE(SUM(total_amount - paid_amount),0) t FROM fee_challans WHERE status != 'paid'")->fetch_assoc()['t'] ?? 0);
$todayCollection = (float) (@db_query("SELECT COALESCE(SUM(amount),0) t FROM fee_payments WHERE DATE(created_at)='$today'")->fetch_assoc()['t'] ?? 0);
$monthlyCollection = (float) (@db_query("SELECT COALESCE(SUM(amount),0) t FROM fee_payments WHERE MONTH(created_at)=$month AND YEAR(created_at)=$year")->fetch_assoc()['t'] ?? 0);
$monthlyExpenses = (float) (@db_query("SELECT COALESCE(SUM(amount),0) t FROM expenses WHERE MONTH(expense_date)=$month AND YEAR(expense_date)=$year")->fetch_assoc()['t'] ?? 0);
$todayExpenses = (float) (@db_query("SELECT COALESCE(SUM(amount),0) t FROM expenses WHERE expense_date='$today'")->fetch_assoc()['t'] ?? 0);

$netBalance = $monthlyCollection - $monthlyExpenses;

$recentPayments = [];
$res = @db_query("SELECT fp.amount, fp.created_at, fc.student_id, s.first_name, s.last_name FROM fee_payments fp LEFT JOIN fee_challans fc ON fp.challan_id = fc.challan_id LEFT JOIN students s ON fc.student_id = s.student_id ORDER BY fp.created_at DESC LIMIT 5");
if ($res) { while ($row = $res->fetch_assoc()) { $recentPayments[] = $row; } }

$recentExpenses = [];
$res = @db_query("SELECT title, amount, expense_date FROM expenses ORDER BY expense_date DESC, expense_id DESC LIMIT 5");
if ($res) { while ($row = $res->fetch_assoc()) { $recentExpenses[] = $row; } }

include __DIR__ . '/includes/header.php';
?>
<style>
.aqib-dash{padding-top:10px;padding-bottom:30px}.aqib-dash*{box-sizing:border-box}.kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px}.kpi-card{background:#fff;border:1px solid #E5E7EB;border-radius:16px;padding:18px;box-shadow:0 1px 3px rgba(16,24,40,.06)}.kpi-card:hover{box-shadow:0 8px 24px rgba(15,23,42,.08)}.kpi-top{display:flex;align-items:center;gap:11px}.kpi-icon{width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}.kpi-label{font-size:12.5px;color:#6B7280;font-weight:600}.kpi-value{font-size:23px;font-weight:800;color:#111827;margin-top:14px;line-height:1.2}.row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}.card-head{display:flex;align-items:center;justify-content:space-between;padding:16px 18px 0 18px}.card-title{font-size:15px;font-weight:700;color:#111827}.quick-links{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;padding:16px 18px}.quick-link{display:flex;align-items:center;gap:10px;padding:14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;text-decoration:none;color:#111827;transition:all .2s ease}.quick-link:hover{background:#fff7ed;border-color:#ffd8b3;color:#ff7c00;transform:translateY(-1px);box-shadow:0 4px 12px rgba(255,124,0,.1)}.quick-link i{font-size:18px;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.quick-link .ql-text{font-size:13px;font-weight:600}.quick-link .ql-sub{font-size:11px;color:#6b7280}
@media(max-width:1400px){.kpi-row{grid-template-columns:repeat(2,1fr)}.row2{grid-template-columns:1fr}.quick-links{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.kpi-row{grid-template-columns:1fr}.quick-links{grid-template-columns:1fr}}
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="aqib-dash">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding:4px 4px 14px">
                <h3 style="font-size:18px;font-weight:800;color:#111827;margin:0"><i class="fa fa-tachometer"></i> Welcome, <?php echo $userName; ?></h3>
                <span style="font-size:12.5px;color:#6B7280;background:#F3F4F6;border:1px solid #E5E7EB;padding:5px 12px;border-radius:999px"><?php echo date('d M, Y'); ?></span>
            </div>

            <div class="kpi-row">
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#D3F3E4;color:#22C55E"><i class="fa fa-wallet"></i></div><div class="kpi-label">Total Collected</div></div>
                    <div class="kpi-value"><?php echo e(get_setting('currency_symbol','Rs.') . number_format($totalCollected)); ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#FFD4D1;color:#DC2626"><i class="fa fa-file-invoice-dollar"></i></div><div class="kpi-label">Pending Fee</div></div>
                    <div class="kpi-value"><?php echo e(get_setting('currency_symbol','Rs.') . number_format($pendingFee)); ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#E9F2FF;color:#377DFF"><i class="fa fa-coins"></i></div><div class="kpi-label">Today Collection</div></div>
                    <div class="kpi-value"><?php echo e(get_setting('currency_symbol','Rs.') . number_format($todayCollection)); ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#FFE5D1;color:#FF7C1B"><i class="fa fa-receipt"></i></div><div class="kpi-label">Monthly Expenses</div></div>
                    <div class="kpi-value"><?php echo e(get_setting('currency_symbol','Rs.') . number_format($monthlyExpenses)); ?></div>
                </div>
            </div>

            <div style="margin-bottom:16px">
                <div class="kpi-card">
                    <div class="card-head" style="padding:0 0 12px"><div class="card-title"><i class="fa fa-bolt" style="color:#FF7C1B;margin-right:8px"></i> Quick Links</div></div>
                    <div class="quick-links">
                        <a href="<?php echo BASE_URL; ?>multi_fee_reports.php" class="quick-link"><i style="background:#E9F2FF;color:#377DFF"><i class="fa fa-chart-bar"></i></i><div><div class="ql-text">Fee Reports</div><div class="ql-sub">View fee collection reports</div></div></a>
                        <a href="<?php echo BASE_URL; ?>manage_expenses.php" class="quick-link"><i style="background:#FFE5D1;color:#FF7C1B"><i class="fa fa-money"></i></i><div><div class="ql-text">Expenses</div><div class="ql-sub">Manage expenses</div></div></a>
                        <a href="<?php echo BASE_URL; ?>creat_payroll.php" class="quick-link"><i style="background:#EADAFF;color:#9747FF"><i class="fab fa-paypal"></i></i><div><div class="ql-text">Payroll</div><div class="ql-sub">Generate staff payroll</div></div></a>
                        <a href="<?php echo BASE_URL; ?>print_daily_income_exp_report.php" class="quick-link"><i style="background:#D3F3E4;color:#22C55E"><i class="fa fa-calendar"></i></i><div><div class="ql-text">Daily Closing</div><div class="ql-sub">Daily income & expense</div></div></a>
                    </div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:16px">
                <div class="kpi-card" style="text-align:center">
                    <div style="font-size:12.5px;color:#6B7280;font-weight:600">Today Expenses</div>
                    <div style="font-size:20px;font-weight:800;color:#DC2626;margin-top:8px"><?php echo e(get_setting('currency_symbol','Rs.') . number_format($todayExpenses)); ?></div>
                </div>
                <div class="kpi-card" style="text-align:center">
                    <div style="font-size:12.5px;color:#6B7280;font-weight:600">Monthly Collection</div>
                    <div style="font-size:20px;font-weight:800;color:#22C55E;margin-top:8px"><?php echo e(get_setting('currency_symbol','Rs.') . number_format($monthlyCollection)); ?></div>
                </div>
                <div class="kpi-card" style="text-align:center">
                    <div style="font-size:12.5px;color:#6B7280;font-weight:600">Net Balance (This Month)</div>
                    <div style="font-size:20px;font-weight:800;color:<?php echo $netBalance >= 0 ? '#22C55E' : '#DC2626'; ?>;margin-top:8px"><?php echo $netBalance >= 0 ? '+' : ''; ?><?php echo e(get_setting('currency_symbol','Rs.') . number_format($netBalance)); ?></div>
                </div>
            </div>

            <div class="row2">
                <div class="kpi-card">
                    <div class="card-head" style="padding:0 0 12px"><div class="card-title"><i class="fa fa-money" style="color:#22C55E;margin-right:8px"></i> Recent Payments</div></div>
                    <div style="overflow-x:auto">
                        <table class="table table-striped table-bordered" style="width:100%;background:#fff;margin-bottom:0;font-size:13px">
                            <thead><tr><th>Student</th><th>Amount</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php if (count($recentPayments) === 0): ?><tr><td colspan="3" style="text-align:center;color:#6B7280;padding:20px">No payments recorded.</td></tr><?php endif; ?>
                                <?php foreach ($recentPayments as $rp): ?>
                                    <tr>
                                        <td><strong><?php echo e(($rp['first_name'] ?? '') . ' ' . ($rp['last_name'] ?? '')); ?></strong></td>
                                        <td style="color:#22C55E;font-weight:700"><?php echo e(get_setting('currency_symbol','Rs.') . number_format($rp['amount'] ?? 0)); ?></td>
                                        <td><?php echo date('d M', strtotime($rp['created_at'] ?? 'now')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="card-head" style="padding:0 0 12px"><div class="card-title"><i class="fa fa-receipt" style="color:#DC2626;margin-right:8px"></i> Recent Expenses</div></div>
                    <div style="overflow-x:auto">
                        <table class="table table-striped table-bordered" style="width:100%;background:#fff;margin-bottom:0;font-size:13px">
                            <thead><tr><th>Description</th><th>Amount</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php if (count($recentExpenses) === 0): ?><tr><td colspan="3" style="text-align:center;color:#6B7280;padding:20px">No expenses recorded.</td></tr><?php endif; ?>
                                <?php foreach ($recentExpenses as $re): ?>
                                    <tr>
                                        <td><strong><?php echo e(mb_strimwidth($re['description'] ?? '', 0, 40, '...')); ?></strong></td>
                                        <td style="color:#DC2626;font-weight:700"><?php echo e(get_setting('currency_symbol','Rs.') . number_format($re['amount'] ?? 0)); ?></td>
                                        <td><?php echo date('d M', strtotime($re['expense_date'] ?? 'now')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
