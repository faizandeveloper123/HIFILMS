<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$pid = (int) ($_GET['payroll_id'] ?? 0);
$row = null;
if ($pid > 0) {
    $st2 = db_prepare("SELECT p.*, e.first_name, e.last_name, e.father_name, e.designation, e.department, e.reg_no, e.cnic FROM payroll p LEFT JOIN employees e ON p.emp_id=e.emp_id WHERE p.payroll_id=?");
    $st2->bind_param('i', $pid);
    $st2->execute();
    $row = $st2->get_result()->fetch_assoc();
}

if (!$row) { die('Invalid payroll slip.'); }

$ded_sum = (float)$row['p_bal'] + (float)$row['adv_amt'] + (float)$row['adv_dec'] + (float)$row['security'] + (float)$row['other_ded'] + (float)$row['absent'];
$ded_show = $ded_sum > 0 ? $ded_sum : (float)$row['deductions'];
$allow_sum = (float)$row['traveling'] + (float)$row['reimb'] + (float)$row['other_allow'];
$allow_show = $allow_sum > 0 ? $allow_sum : (float)$row['allowances'];
$net = round((float)$row['basic_salary'] + $allow_show - $ded_show, 2);

$school = get_setting('school_name', 'LAPS School & College');
$monthName = date('F', mktime(0, 0, 0, (int)$row['month'], 1));
$period = trim($monthName . ' ' . ($row['year'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Salary Sheet - <?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #111827; margin: 24px; font-size: 13px; }
        .sheet { border: 1px solid #d1d5db; border-radius: 8px; padding: 20px; max-width: 700px; margin: 0 auto; }
        .school-logo { text-align: center; margin-bottom: 12px; }
        .school-logo .name { font-size: 22px; font-weight: 800; color: #ff7800; }
        .school-logo .tag { color: #6b7280; font-size: 13px; }
        .school-logo .slip-title { font-size: 15px; font-weight: 700; margin-top: 4px; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td, th { border: 1px solid #d1d5db; padding: 7px 10px; }
        th { background: #f3f4f6; text-align: left; font-size: 12px; text-transform: uppercase; }
        .right { text-align: right; }
        .amount { font-weight: 700; }
        .header-row th { background: #ff7800; color: #fff; }
        .net-row { background: #f3f4f6; font-size: 14px; }
        .sign { margin-top: 30px; display: flex; justify-content: space-between; }
        .sign div { text-align: center; width: 40%; }
        .sign .line { border-top: 1px solid #9ca3af; margin-top: 26px; }
        .status-paid { color: #16a34a; font-weight: 800; }
        .status-pending { color: #d97706; font-weight: 800; }
        .badge { display: inline-block; padding: 3px 12px; border-radius: 999px; }
        @media print { body { margin: 8px; } .sheet { border: none; padding: 0; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="school-logo">
        <div class="name"><?php echo e($school); ?></div>
        <div class="tag"><?php echo e(get_setting('school_tagline', 'Education Portal')); ?></div>
        <div class="slip-title">Employee Salary Sheet</div>
        <div><?php echo e($period); ?></div>
    </div>

    <table>
        <tr><th colspan="4" class="header-row"><center>Employee Details</center></th></tr>
        <tr><td><strong>Name</strong></td><td><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></td><td><strong>Father / Husband</strong></td><td><?php echo e($row['father_name'] ?: '-'); ?></td></tr>
        <tr><td><strong>Designation</strong></td><td><?php echo e($row['designation'] ?: '-'); ?></td><td><strong>Department</strong></td><td><?php echo e($row['department'] ?: '-'); ?></td></tr>
        <tr><td><strong>Reg No</strong></td><td><?php echo e($row['reg_no'] ?: '-'); ?></td><td><strong>CNIC</strong></td><td><?php echo e($row['cnic'] ?: '-'); ?></td></tr>
    </table>

    <table>
        <tr><th colspan="2">Earnings / Allowances</th><th class="right">Amount</th></tr>
        <tr><td colspan="2">Basic Salary</td><td class="right amount"><?php echo number_format((float)$row['basic_salary'], 2); ?></td></tr>
        <tr><td colspan="2">Traveling Allowance</td><td class="right"><?php echo number_format((float)$row['traveling'], 2); ?></td></tr>
        <tr><td colspan="2">Reimbursement</td><td class="right"><?php echo number_format((float)$row['reimb'], 2); ?></td></tr>
        <tr><td colspan="2">Other Allowances</td><td class="right"><?php echo number_format((float)$row['other_allow'], 2); ?></td></tr>
        <tr><td colspan="2"><strong>Total Allowances</strong></td><td class="right amount"><?php echo number_format($allow_show, 2); ?></td></tr>

        <tr><th colspan="2">Deductions</th><th class="right">Amount</th></tr>
        <tr><td colspan="2">Previous Balance (P.Bal)</td><td class="right"><?php echo number_format((float)$row['p_bal'], 2); ?></td></tr>
        <tr><td colspan="2">Advance Amount</td><td class="right"><?php echo number_format((float)$row['adv_amt'], 2); ?></td></tr>
        <tr><td colspan="2">Advance Deduction</td><td class="right"><?php echo number_format((float)$row['adv_dec'], 2); ?></td></tr>
        <tr><td colspan="2">Security</td><td class="right"><?php echo number_format((float)$row['security'], 2); ?></td></tr>
        <tr><td colspan="2">Other Deductions</td><td class="right"><?php echo number_format((float)$row['other_ded'], 2); ?></td></tr>
        <tr><td colspan="2">Absent</td><td class="right"><?php echo number_format((float)$row['absent'], 2); ?></td></tr>
        <tr><td colspan="2"><strong>Total Deductions</strong></td><td class="right amount"><?php echo number_format($ded_show, 2); ?></td></tr>

        <tr class="net-row">
            <td colspan="2"><strong>Net Payable Salary</strong></td>
            <td class="right amount"><?php echo number_format($net, 2); ?> <?php echo e(get_setting('currency_symbol', 'Rs.')); ?></td>
        </tr>
        <tr>
            <td colspan="2"><strong>Status</strong></td>
            <td class="right">
                <span class="badge status-<?php echo $row['status'] == 'paid' ? 'paid' : 'pending'; ?>"><?php echo strtoupper($row['status']); ?></span>
                <?php if (!empty($row['paid_date'])): ?> (<?php echo e($row['paid_date']); ?>)<?php endif; ?>
            </td>
        </tr>
    </table>

    <div class="sign">
        <div><span class="line"></span><div>Prepared By</div></div>
        <div><span class="line"></span><div>Received By (Signature)</div></div>
    </div>
</div>
<script>window.onload = function(){ window.print(); };</script>
</body>
</html>