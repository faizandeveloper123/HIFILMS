<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin', 'accounts']);

$page_title = 'Create Employees PayRoll';

$message = '';
$error = '';

$month = $_GET['month'] ?? (int) date('n');
if (!is_numeric($month) || (int)$month < 1 || (int)$month > 12) { $month = (int) date('n'); }
$month = (int) $month;

$session = trim($_GET['session'] ?? (get_setting('session_year', '2025-2026') ?: '2025-2026'));
if (!preg_match('/^\d{4}-\d{2}$/', $session)) { $session = '2025-2026'; }
$sessStart = (int) substr($session, 0, 4);
$year = $sessStart;

$designation = trim($_GET['designation'] ?? '');
$department = trim($_GET['department'] ?? '');

$allowancePct = (float) get_setting('payroll_allowance_percent', '0');
$deductionPct = (float) get_setting('payroll_deduction_percent', '0');

$allDesignations = [];
$res = db_query("SELECT DISTINCT designation FROM employees WHERE designation IS NOT NULL AND designation <> '' ORDER BY designation");
while ($row = $res->fetch_assoc()) { $allDesignations[] = $row['designation']; }

$allDepartments = [];
$res = db_query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department <> '' ORDER BY department");
while ($row = $res->fetch_assoc()) { $allDepartments[] = $row['department']; }

$employees = [];
if (isset($_GET['search'])) {
    $where = "WHERE status = 1";
    $params = [];
    $types = '';
    if ($designation !== '') { $where .= " AND designation = ?"; $params[] = $designation; $types .= 's'; }
    if ($department !== '') { $where .= " AND department = ?"; $params[] = $department; $types .= 's'; }
    $sql = "SELECT * FROM employees $where ORDER BY first_name";
    $res = count($params) > 0 ? (function() use ($sql, $types, $params) {
        $st2 = db_prepare($sql); $st2->bind_param($types, ...$params); $st2->execute(); return $st2->get_result();
    })() : db_query($sql);
    while ($row = $res->fetch_assoc()) {
        $chk = db_prepare("SELECT COUNT(*) c FROM payroll WHERE emp_id=? AND month=? AND year=?");
        $mstr = (string)$month;
        $chk->bind_param('isi', $row['emp_id'], $mstr, $year);
        $chk->execute();
        $row['already'] = $chk->get_result()->fetch_assoc()['c'] > 0;
        $employees[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'GeneratePayroll') {
    $year = (int) ($_POST['year'] ?? 0);
    $month = (int) ($_POST['month'] ?? 0);
    $sel = $_POST['emp_ids'] ?? [];
    $basics = $_POST['basic'] ?? [];
    $allws = $_POST['allowance_pct'] ?? [];
    $dedcs = $_POST['deduction_pct'] ?? [];
    $created = 0;

    if ($year > 0 && $month >= 1 && $month <= 12 && is_array($sel)) {
        foreach ($sel as $emp_id) {
            $emp_id = (int)$emp_id;
            $emp = null;
            $st2 = db_prepare("SELECT * FROM employees WHERE emp_id=?");
            $st2->bind_param('i', $emp_id);
            $st2->execute();
            $emp = $st2->get_result()->fetch_assoc();
            if (!$emp) { continue; }

            $basic = isset($basics[$emp_id]) && $basics[$emp_id] !== '' ? (float)$basics[$emp_id] : (float)($emp['salary'] ?? 0);
            $alPct = isset($allws[$emp_id]) && $allws[$emp_id] !== '' ? (float)$allws[$emp_id] : $allowancePct;
            $ddPct = isset($dedcs[$emp_id]) && $dedcs[$emp_id] !== '' ? (float)$dedcs[$emp_id] : $deductionPct;
            $allowances = round($basic * ($alPct / 100), 2);
            $deductions = round($basic * ($ddPct / 100), 2);
            $traveling = isset($_POST['traveling'][$emp_id]) ? (float)$_POST['traveling'][$emp_id] : 0;
            $reimb = isset($_POST['reimb'][$emp_id]) ? (float)$_POST['reimb'][$emp_id] : 0;
            $other_allow = isset($_POST['other_allow'][$emp_id]) ? (float)$_POST['other_allow'][$emp_id] : 0;
            $absent = isset($_POST['absent'][$emp_id]) ? (float)$_POST['absent'][$emp_id] : 0;
            $security = isset($_POST['security'][$emp_id]) ? (float)$_POST['security'][$emp_id] : 0;
            $other_ded = isset($_POST['other_ded'][$emp_id]) ? (float)$_POST['other_ded'][$emp_id] : 0;
            $adv_dec = isset($_POST['adv_dec'][$emp_id]) ? (float)$_POST['adv_dec'][$emp_id] : 0;
            $totalAllow = $allowances + $traveling + $reimb + $other_allow;
            $totalDed = $deductions + $absent + $security + $other_ded + $adv_dec;
            $net = round($basic + $totalAllow - $totalDed, 2);

            $mstr = (string)$month;
            $chk = db_prepare("SELECT COUNT(*) c FROM payroll WHERE emp_id=? AND month=? AND year=?");
            $chk->bind_param('isi', $emp_id, $mstr, $year);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()['c'] > 0) { continue; }

            $ins = db_prepare("INSERT INTO payroll (emp_id, month, year, basic_salary, allowances, deductions, traveling, reimb, other_allow, absent, security, other_ded, adv_dec, net_salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $ins->bind_param('isidddddddddid', $emp_id, $mstr, $year, $basic, $allowances, $deductions, $traveling, $reimb, $other_allow, $absent, $security, $other_ded, $adv_dec, $net);
            $ins->execute();
            $created++;
        }
        $message = "Payroll generated for $created employee(s) for $month/$year!";
    } else {
        $error = 'Select at least one employee, a valid month and session.';
    }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.status-badge { padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; display:inline-block; }
.status-paid,.status-present,.status-active { background:#DCFCE7; color:#16A34A; }
.status-pending,.status-unpaid { background:#FEF3C7; color:#D97706; }
input.pct-input { width:80px; display:inline-block; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-money"></i> Create Employees PayRoll</h3>
            <div>
                <a href="<?php echo BASE_URL; ?>view_payroll.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-file-text"></i> View PayRoll</a>
                <a href="<?php echo BASE_URL; ?>payroll_setting.php" class="btn btn-warning" style="color:#fff;"><i class="fa fa-cog"></i> Settings</a>
            </div>
        </div>

        <form method="get" action="creat_payroll.php" class="search-bar-student">
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <label>Session</label>
                <select name="session" class="form-control">
                    <?php for ($y = 2018; $y <= 2030; $y++): $s = "$y-" . str_pad($y + 1, 2, '0', STR_PAD_LEFT); ?>
                        <option value="<?php echo $s; ?>" <?php echo $session === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <label>Month</label>
                <select name="month" class="form-control">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $month === $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <label>Designation</label>
                <select name="designation" class="form-control">
                    <option value="">All Designations</option>
                    <?php foreach ($allDesignations as $d): ?>
                        <option value="<?php echo e($d); ?>" <?php echo $designation === $d ? 'selected' : ''; ?>><?php echo e($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <label>Department</label>
                <select name="department" class="form-control">
                    <option value="">All Departments</option>
                    <?php foreach ($allDepartments as $d): ?>
                        <option value="<?php echo e($d); ?>" <?php echo $department === $d ? 'selected' : ''; ?>><?php echo e($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <button type="submit" name="search" value="1" class="btn btn-primary" style="width:100%;"><i class="fa fa-search"></i> Search PayRoll</button>
            </div>
        </form>

        <?php if (!isset($_GET['search'])): ?>
            <div style="background:#EFF6FF; border:1px solid #BFDBFE; color:#1D4ED8; border-radius:12px; padding:20px; text-align:center;">
                <i class="fa fa-search" style="font-size:28px; margin-bottom:6px;"></i>
                <p style="margin:0; font-size:14px;">Select a session and month, then click <b>Search PayRoll</b> to load employees for payroll generation.</p>
            </div>
        <?php elseif (count($employees) === 0): ?>
            <div style="background:#FFF7ED; border:1px solid #FFEDD5; color:#9A3412; border-radius:12px; padding:20px; text-align:center;">No active employees found for the selected filters.</div>
        <?php else: ?>

        <form method="post" action="creat_payroll.php" style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <input type="hidden" name="action" value="GeneratePayroll">
            <input type="hidden" name="month" value="<?php echo $month; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">
            <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="selAll"></th>
                        <th>Employee</th>
                        <th>Designation</th>
                        <th>Basic</th>
                        <th>Allow %</th>
                        <th>Allow Amt</th>
                        <th>Traveling</th>
                        <th>Reimb</th>
                        <th>Other Allow</th>
                        <th>Ded %</th>
                        <th>Ded Amt</th>
                        <th>Absent</th>
                        <th>Security</th>
                        <th>Other Ded</th>
                        <th>Adv Dec</th>
                        <th>Net</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $e):
                        $basic = (float)$e['salary'];
                        $alAmt = round($basic * ($allowancePct / 100), 2);
                        $ddAmt = round($basic * ($deductionPct / 100), 2);
                        $net = $basic + $alAmt - $ddAmt;
                    ?>
                        <tr class="pay-row">
                            <td><input type="checkbox" name="emp_ids[]" value="<?php echo $e['emp_id']; ?>" <?php echo $e['already'] ? 'disabled' : ''; ?>></td>
                            <td><strong><?php echo e($e['first_name'] . ' ' . $e['last_name']); ?></strong>
                                <?php if ($e['already']): ?><span class="status-badge status-present" style="margin-left:6px;">Already</span><?php endif; ?></td>
                            <td><?php echo e($e['designation'] ?? '-'); ?></td>
                            <td>
                                <input type="number" step="0.01" class="form-control input-sm basic-input" style="width:100px;" name="basic[<?php echo $e['emp_id']; ?>]" value="<?php echo $basic; ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" class="form-control input-sm al-pct" style="width:65px;" name="allowance_pct[<?php echo $e['emp_id']; ?>]" value="<?php echo $allowancePct; ?>">
                            </td>
                            <td><span class="al-amt" style="color:#16A34A; font-weight:700;"><?php echo number_format($alAmt, 2); ?></span></td>
                            <td><input type="number" step="0.01" class="form-control input-sm extra-field" style="width:80px;" name="traveling[<?php echo $e['emp_id']; ?>]" value="0"></td>
                            <td><input type="number" step="0.01" class="form-control input-sm extra-field" style="width:80px;" name="reimb[<?php echo $e['emp_id']; ?>]" value="0"></td>
                            <td><input type="number" step="0.01" class="form-control input-sm extra-field" style="width:80px;" name="other_allow[<?php echo $e['emp_id']; ?>]" value="0"></td>
                            <td>
                                <input type="number" step="0.01" class="form-control input-sm dd-pct" style="width:65px;" name="deduction_pct[<?php echo $e['emp_id']; ?>]" value="<?php echo $deductionPct; ?>">
                            </td>
                            <td><span class="dd-amt" style="color:#DC2626; font-weight:700;"><?php echo number_format($ddAmt, 2); ?></span></td>
                            <td><input type="number" step="0.01" class="form-control input-sm extra-field" style="width:80px;" name="absent[<?php echo $e['emp_id']; ?>]" value="0"></td>
                            <td><input type="number" step="0.01" class="form-control input-sm extra-field" style="width:80px;" name="security[<?php echo $e['emp_id']; ?>]" value="0"></td>
                            <td><input type="number" step="0.01" class="form-control input-sm extra-field" style="width:80px;" name="other_ded[<?php echo $e['emp_id']; ?>]" value="0"></td>
                            <td><input type="number" step="0.01" class="form-control input-sm extra-field" style="width:80px;" name="adv_dec[<?php echo $e['emp_id']; ?>]" value="0"></td>
                            <td><strong class="net-amt"><?php echo number_format($net, 2); ?></strong></td>
                            <td><span class="status-badge status-pending">Pending</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="padding:14px; text-align:right;">
                <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Generate Selected Payroll</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('selAll').addEventListener('change', function(){
    document.querySelectorAll('.pay-row input[name="emp_ids[]"]:not(:disabled)').forEach(function(c){ c.checked = this.checked; }.bind(this));
});
function recalc(row) {
    var basic = parseFloat(row.querySelector('.basic-input').value) || 0;
    var alPct = parseFloat(row.querySelector('.al-pct').value) || 0;
    var ddPct = parseFloat(row.querySelector('.dd-pct').value) || 0;
    var al = basic * alPct / 100;
    var dd = basic * ddPct / 100;
    var extras = row.querySelectorAll('.extra-field');
    var traveling = parseFloat(extras[0].value) || 0;
    var reimb = parseFloat(extras[1].value) || 0;
    var otherAllow = parseFloat(extras[2].value) || 0;
    var absent = parseFloat(extras[3].value) || 0;
    var security = parseFloat(extras[4].value) || 0;
    var otherDed = parseFloat(extras[5].value) || 0;
    var advDec = parseFloat(extras[6].value) || 0;
    var totalAllow = al + traveling + reimb + otherAllow;
    var totalDed = dd + absent + security + otherDed + advDec;
    row.querySelector('.al-amt').textContent = totalAllow.toFixed(2);
    row.querySelector('.dd-amt').textContent = totalDed.toFixed(2);
    row.querySelector('.net-amt').textContent = (basic + totalAllow - totalDed).toFixed(2);
}
document.querySelectorAll('.pay-row').forEach(function(row){
    ['input', 'change'].forEach(function(ev){
        row.querySelectorAll('.basic-input, .al-pct, .dd-pct, .extra-field').forEach(function(inp){
            inp.addEventListener(ev, function(){ recalc(row); });
        });
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>