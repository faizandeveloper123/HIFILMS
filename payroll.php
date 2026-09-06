<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin', 'accounts']);

$page_title = 'Payroll';

$message = '';
$error = '';

$sel_month = (int) ($_GET['month'] ?? (int) date('m'));
$sel_year  = (int) ($_GET['year'] ?? (int) date('Y'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'GeneratePayroll') {
    $month = (int) ($_POST['month'] ?? (int) date('m'));
    $year  = (int) ($_POST['year'] ?? (int) date('Y'));
    $emp_ids = $_POST['emp_ids'] ?? [];
    if (!is_array($emp_ids)) { $emp_ids = [$emp_ids]; }
    $generated = 0;
    foreach ($emp_ids as $eid) {
        $eid = (int) $eid;
        if ($eid <= 0) continue;
        $em = db_query("SELECT * FROM employees WHERE emp_id=$eid")->fetch_assoc();
        if (!$em) continue;
        $basic = (float) $em['salary'];
        $net = $basic;
        $mstr = "$month/$year";
        $stmt = db_prepare("INSERT INTO payroll (emp_id, month, year, basic_salary, allowances, deductions, net_salary, status) VALUES (?, ?, ?, ?, 0, 0, ?, 'pending')");
        $stmt->bind_param('iiddd', $eid, $mstr, $year, $basic, $net);
        $stmt->execute();
        $generated++;
    }
    $message = "$generated payroll slips generated!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'MarkPaid') {
    $pid = (int) ($_POST['payroll_id'] ?? 0);
    $st2 = db_prepare("UPDATE payroll SET status='paid' WHERE payroll_id=?");
    $st2->bind_param('i', $pid);
    $st2->execute();
    $message = 'Payroll marked as paid!';
}

$emps = [];
$res = db_query("SELECT emp_id, first_name, last_name, designation, salary FROM employees WHERE status=1 ORDER BY first_name");
while ($row = $res->fetch_assoc()) { $emps[] = $row; }

$slips = [];
$res = db_query("SELECT p.*, e.first_name, e.last_name, e.designation FROM payroll p JOIN employees e ON p.emp_id=e.emp_id WHERE p.year=$sel_year AND p.month='$sel_month/$sel_year' ORDER BY p.payroll_id");
while ($row = $res->fetch_assoc()) { $slips[] = $row; }

$total_net = 0;
$paid_count = 0;
foreach ($slips as $s) { $total_net += (float) $s['net_salary']; if ($s['status'] === 'paid') $paid_count++; }

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.analytics-cards { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:16px; }
.analytics-cards .ac { background:#fff; border:1px solid #E5E7EB; border-radius:12px; padding:14px; text-align:center; }
.analytics-cards .ac .n { font-size:20px; font-weight:800; }
.analytics-cards .ac .l { font-size:11.5px; color:#6B7280; }
@media (max-width:900px){ .analytics-cards{ grid-template-columns:repeat(1,1fr);} }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-money"></i> Payroll <span style="font-size:14px; color:#6B7280;">(<?php echo date('F Y', mktime(0,0,0,$sel_month,1,$sel_year)); ?>)</span></h3>
        </div>

        <form method="get" action="payroll.php" class="search-bar-student">
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <label>Month</label>
                <select name="month" class="form-control">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $sel_month == $m ? 'selected' : ''; ?>><?php echo date('M', mktime(0,0,0,$m,1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <label>Year</label>
                <select name="year" class="form-control">
                    <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $sel_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">Load</button>
            </div>
        </form>

        <div class="analytics-cards">
            <div class="ac"><div class="n" style="color:#374151;"><?php echo count($slips); ?></div><div class="l">Total Slips</div></div>
            <div class="ac"><div class="n" style="color:#16A34A;"><?php echo $paid_count; ?></div><div class="l">Paid</div></div>
            <div class="ac"><div class="n" style="color:#FF7A1B;"><?php echo number_format($total_net, 2); ?></div><div class="l">Total Net Salary</div></div>
        </div>

        <form method="post" action="payroll.php" style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px; margin-bottom:16px;">
            <input type="hidden" name="action" value="GeneratePayroll">
            <input type="hidden" name="month" value="<?php echo $sel_month; ?>">
            <input type="hidden" name="year" value="<?php echo $sel_year; ?>">
            <h4 style="font-size:15px; font-weight:800; padding:14px 16px; margin:0; border-bottom:1px solid #F3F4F6;">Generate Payroll Slips</h4>
            <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead>
                    <tr><th><input type="checkbox" id="selAll" checked></th><th>#</th><th>Employee</th><th>Designation</th><th>Basic Salary</th></tr>
                </thead>
                <tbody>
                    <?php if (count($emps) === 0): ?>
                        <tr><td colspan="5" style="text-align:center; color:#6B7280; padding:25px;">No employees found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($emps as $em): ?>
                        <tr>
                            <td><input type="checkbox" name="emp_ids[]" value="<?php echo $em['emp_id']; ?>" class="emp-check" checked></td>
                            <td><?php echo $em['emp_id']; ?></td>
                            <td><strong><?php echo e($em['first_name']); ?> <?php echo e($em['last_name']); ?></strong></td>
                            <td><?php echo e($em['designation'] ?? '-'); ?></td>
                            <td><?php echo number_format($em['salary'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="padding:14px; text-align:right;">
                <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Generate Slips</button>
            </div>
        </form>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <h4 style="font-size:15px; font-weight:800; padding:14px 16px; margin:0; border-bottom:1px solid #F3F4F6;">Payroll Slips</h4>
            <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead>
                    <tr><th>#</th><th>Employee</th><th>Month</th><th>Basic</th><th>Net Salary</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php if (count($slips) === 0): ?>
                        <tr><td colspan="7" style="text-align:center; color:#6B7280; padding:25px;">No payroll slips for this month.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($slips as $s): ?>
                        <tr>
                            <td><?php echo $s['payroll_id']; ?></td>
                            <td><strong><?php echo e($s['first_name']); ?> <?php echo e($s['last_name']); ?></strong></td>
                            <td><?php echo e($s['month']); ?></td>
                            <td><?php echo number_format($s['basic_salary'], 2); ?></td>
                            <td style="color:#FF7A1B; font-weight:800;"><?php echo number_format($s['net_salary'], 2); ?></td>
                            <td><span class="status-badge status-<?php echo $s['status'] === 'paid' ? 'paid' : 'unpaid'; ?>"><?php echo ucfirst($s['status']); ?></span></td>
                            <td>
                                <?php if ($s['status'] !== 'paid'): ?>
                                <form method="post" action="payroll.php" style="display:inline;">
                                    <input type="hidden" name="action" value="MarkPaid">
                                    <input type="hidden" name="payroll_id" value="<?php echo $s['payroll_id']; ?>">
                                    <button class="btn btn-success btn-xs"><i class="fa fa-check"></i> Mark Paid</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('selAll').addEventListener('change', function(){
    document.querySelectorAll('.emp-check').forEach(function(c){ c.checked = this.checked; }.bind(this));
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>