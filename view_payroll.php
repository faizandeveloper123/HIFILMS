<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin', 'accounts']);

$__migrate = [
    "ALTER TABLE payroll ADD COLUMN IF NOT EXISTS paid_date DATE DEFAULT NULL",
    "ALTER TABLE payroll ADD COLUMN IF NOT EXISTS p_bal DECIMAL(12,2) DEFAULT 0",
    "ALTER TABLE payroll ADD COLUMN IF NOT EXISTS adv_amt DECIMAL(12,2) DEFAULT 0",
    "ALTER TABLE payroll ADD COLUMN IF NOT EXISTS adv_dec DECIMAL(12,2) DEFAULT 0",
    "ALTER TABLE payroll ADD COLUMN IF NOT EXISTS security DECIMAL(12,2) DEFAULT 0",
    "ALTER TABLE payroll ADD COLUMN IF NOT EXISTS other_ded DECIMAL(12,2) DEFAULT 0",
    "ALTER TABLE payroll ADD COLUMN IF NOT EXISTS absent DECIMAL(12,2) DEFAULT 0",
    "ALTER TABLE payroll ADD COLUMN IF NOT EXISTS traveling DECIMAL(12,2) DEFAULT 0",
    "ALTER TABLE payroll ADD COLUMN IF NOT EXISTS reimb DECIMAL(12,2) DEFAULT 0",
    "ALTER TABLE payroll ADD COLUMN IF NOT EXISTS other_allow DECIMAL(12,2) DEFAULT 0",
    "ALTER TABLE payroll ADD COLUMN IF NOT EXISTS emp_id INT DEFAULT NULL",
];
foreach ($__migrate as $__sql) { try { db_query($__sql); } catch (\Throwable $e) {} }

$page_title = 'View Employees PayRoll';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'MarkPaid') {
    $pid = (int) ($_POST['payroll_id'] ?? 0);
    if ($pid > 0) {
        $st2 = db_prepare("UPDATE payroll SET status='paid', paid_date=? WHERE payroll_id=?");
        $today = date('Y-m-d');
        $st2->bind_param('si', $today, $pid);
        $st2->execute();
        $message = 'Payroll marked as PAID!';
    }
}

$month = $_GET['month'] ?? (int) date('n');
if (!is_numeric($month) || (int)$month < 1 || (int)$month > 12) { $month = (int) date('n'); }
$month = (int) $month;

$session = trim($_GET['session'] ?? (get_setting('session_year', '2025-2026') ?: '2025-2026'));
if (!preg_match('/^\d{4}-\d{2}$/', $session)) { $session = '2025-2026'; }
$year = (int) substr($session, 0, 4);

$emp_id = (int) ($_GET['emp_id'] ?? 0);

$where = [];
$params = [];
$types = '';
$mstr = (string)$month;
$legacy = "$month/$year";
$where[] = "(p.month = ? OR p.month = ?)";
$params[] = $mstr;
$params[] = $legacy;
$types .= 'ss';
$where[] = "p.year = ?";
$params[] = $year;
$types .= 'i';
if ($emp_id > 0) { $where[] = "p.emp_id = ?"; $params[] = $emp_id; $types .= 'i'; }

$sql = "SELECT p.*, e.first_name, e.last_name, e.designation, e.department, e.photo FROM payroll p LEFT JOIN employees e ON p.emp_id=e.emp_id WHERE " . implode(' AND ', $where) . " ORDER BY p.year DESC, p.month, p.payroll_id DESC";

$rows = [];
$st2 = db_prepare($sql);
$st2->bind_param($types, ...$params);
$st2->execute();
$res = $st2->get_result();
while ($row = $res->fetch_assoc()) {
    $bd = [
        'p_bal' => (float)$row['p_bal'], 'adv_amt' => (float)$row['adv_amt'], 'adv_dec' => (float)$row['adv_dec'],
        'security' => (float)$row['security'], 'other_ded' => (float)$row['other_ded'], 'absent' => (float)$row['absent'],
    ];
    $row['ded_sum'] = array_sum($bd);
    $row['ded_show'] = $row['ded_sum'] > 0 ? $row['ded_sum'] : (float)$row['deductions'];
    $ba = ['traveling' => (float)$row['traveling'], 'reimb' => (float)$row['reimb'], 'other_allow' => (float)$row['other_allow']];
    $row['allow_sum'] = array_sum($ba);
    $row['allow_show'] = $row['allow_sum'] > 0 ? $row['allow_sum'] : (float)$row['allowances'];
    $row['net_display'] = round((float)$row['basic_salary'] + $row['allow_show'] - $row['ded_show'], 2);
    $rows[] = $row;
}

$grand = 0.0; $paid = 0.0; $pending = 0.0;
$totBasic = 0.0; $totDed = 0.0; $totAllow = 0.0;
foreach ($rows as $r) {
    $grand += $r['net_display'];
    $totBasic += (float)$r['basic_salary'];
    $totDed += $r['ded_show'];
    $totAllow += $r['allow_show'];
    if ($r['status'] == 'paid') { $paid += $r['net_display']; } else { $pending += $r['net_display']; }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.status-badge { padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; display:inline-block; }
.status-paid,.status-present,.status-active { background:#DCFCE7; color:#16A34A; }
.status-pending,.status-unpaid { background:#FEF3C7; color:#D97706; }
.group-ded { background:#00afef; color:#fff; text-align:center; }
.group-allow { background:#22C55E; color:#fff; text-align:center; }
.cellcolor { background:#F3F4F6; }
.tbl-sheet th, .tbl-sheet td { white-space:nowrap; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-file-text"></i> View Employees PayRoll</h3>
            <div>
                <a href="<?php echo BASE_URL; ?>creat_payroll.php" class="btn btn-success" style="color:#fff;"><i class="fa fa-plus"></i> Generate PayRoll</a>
                <a href="<?php echo BASE_URL; ?>payroll_setting.php" class="btn btn-warning" style="color:#fff;"><i class="fa fa-cog"></i> Settings</a>
            </div>
        </div>

        <form method="get" action="view_payroll.php" class="search-bar-student">
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
                <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa fa-filter"></i> Filter</button>
            </div>
        </form>

        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:16px;">
            <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px;"><div style="color:#6B7280; font-size:12px; text-transform:uppercase;">Slips</div><div style="font-size:22px; font-weight:800;"><?php echo count($rows); ?></div></div>
            <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px;"><div style="color:#6B7280; font-size:12px; text-transform:uppercase;">Total Payroll</div><div style="font-size:22px; font-weight:800;"><?php echo number_format($grand, 2); ?></div></div>
            <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px;"><div style="color:#6B7280; font-size:12px; text-transform:uppercase;">Paid / Pending</div><div style="font-size:22px; font-weight:800;"><span style="color:#16A34A;"><?php echo number_format($paid, 2); ?></span> / <span style="color:#DC2626;"><?php echo number_format($pending, 2); ?></span></div></div>
        </div>

        <?php if (count($rows) > 0): ?>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
            <button type="button" class="btn btn-warning" onclick="printSelectedSlips()"><i class="fa fa-print"></i> Print Selected Salary Slips</button>
            <a href="<?php echo BASE_URL . 'view_payroll.php?session=' . urlencode($session) . '&month=' . $month; ?>" class="btn btn-default btn-xs" style="display:none;"></a>
        </div>
        <?php endif; ?>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <table class="table table-bordered tbl-sheet" style="width:100%; background:#fff; margin-bottom:0; font-size:12px;">
                <thead>
                    <tr>
                        <th colspan="4" style="text-align:center;">Emp Detail</th>
                        <th colspan="7" class="group-ded">Deductions</th>
                        <th colspan="4" class="group-allow">Allowances</th>
                        <th colspan="5" style="text-align:center;">Totals</th>
                    </tr>
                    <tr>
                        <th><input type="checkbox" id="checkAll"></th>
                        <th>Emp Name</th>
                        <th class="cellcolor">Basic sal</th>
                        <th>P.Bal</th>
                        <th>Adv Amt</th>
                        <th>Adv Dec</th>
                        <th>Security</th>
                        <th>Other</th>
                        <th>Absent</th>
                        <th class="cellcolor">Total</th>
                        <th>Traveling</th>
                        <th>Reimb</th>
                        <th>Other</th>
                        <th class="cellcolor">Total</th>
                        <th class="cellcolor">Net Total</th>
                        <th style="width:13%;">Action</th>
                        <th>Paid</th>
                        <th>Remaining</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rows) === 0): ?>
                        <tr><td colspan="19" style="text-align:center; color:#6B7280; padding:25px;">No payroll slips found for this session / month.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $rem = $r['status'] == 'paid' ? 0.0 : $r['net_display'];
                        $pay = $r['net_display'] - $rem;
                    ?>
                        <tr id="pr-<?php echo $r['payroll_id']; ?>">
                            <td><input type="checkbox" class="pp-check" value="<?php echo $r['payroll_id']; ?>"></td>
                            <td><strong><?php echo e($r['first_name'] . ' ' . $r['last_name']); ?></strong><br><small style="color:#6B7280;"><?php echo e($r['designation'] ?? ''); ?></small></td>
                            <td class="cellcolor"><strong><?php echo number_format($r['basic_salary'], 2); ?></strong></td>
                            <td><?php echo number_format($r['p_bal'], 2); ?></td>
                            <td><?php echo number_format($r['adv_amt'], 2); ?></td>
                            <td><?php echo number_format($r['adv_dec'], 2); ?></td>
                            <td><?php echo number_format($r['security'], 2); ?></td>
                            <td><?php echo number_format($r['other_ded'], 2); ?></td>
                            <td><?php echo number_format($r['absent'], 2); ?></td>
                            <td class="cellcolor" style="color:#DC2626; font-weight:700;"><?php echo number_format($r['ded_show'], 2); ?></td>
                            <td><?php echo number_format($r['traveling'], 2); ?></td>
                            <td><?php echo number_format($r['reimb'], 2); ?></td>
                            <td><?php echo number_format($r['other_allow'], 2); ?></td>
                            <td class="cellcolor" style="color:#16A34A; font-weight:700;"><?php echo number_format($r['allow_show'], 2); ?></td>
                            <td class="cellcolor"><strong><?php echo number_format($r['net_display'], 2); ?></strong></td>
                            <td>
                                <a href="<?php echo BASE_URL . 'print_emp_salary_sheet.php?payroll_id=' . $r['payroll_id']; ?>" target="_blank" class="btn btn-info btn-xs" title="Print Employee Sheet"><i class="fa fa-print"></i></a>
                                <?php if ($r['status'] != 'paid'): ?>
                                    <form method="post" action="view_payroll.php" style="display:inline;">
                                        <input type="hidden" name="action" value="MarkPaid">
                                        <input type="hidden" name="payroll_id" value="<?php echo $r['payroll_id']; ?>">
                                        <button class="btn btn-success btn-xs"><i class="fa fa-check"></i> Pay</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td style="color:#16A34A;"><?php echo number_format($pay, 2); ?></td>
                            <td style="color:#DC2626;"><?php echo number_format($rem, 2); ?></td>
                            <td><span class="status-badge status-<?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#F9FAFB; font-weight:800;">
                        <td colspan="2"></td>
                        <td class="cellcolor"><?php echo number_format($totBasic, 2); ?></td>
                        <td colspan="6"></td>
                        <td><?php echo number_format($totDed, 2); ?></td>
                        <td colspan="3"></td>
                        <td><?php echo number_format($totAllow, 2); ?></td>
                        <td class="cellcolor"><?php echo number_format($grand, 2); ?></td>
                        <td colspan="2">Total Net</td>
                        <td><?php echo number_format($paid, 2); ?></td>
                        <td><?php echo number_format($pending, 2); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('checkAll').addEventListener('change', function(){
    document.querySelectorAll('.pp-check').forEach(function(c){ c.checked = this.checked; }.bind(this));
});
function printSelectedSlips() {
    var ids = [];
    document.querySelectorAll('.pp-check:checked').forEach(function(c){ ids.push(c.value); });
    if (!ids.length) { alert('Select at least one payroll slip.'); return; }
    var form = document.createElement('form');
    form.method = 'post';
    form.action = '<?php echo BASE_URL; ?>print_all_salary_slip.php';
    form.target = '_blank';
    ids.forEach(function(id){
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'payroll_id[]';
        inp.value = id;
        form.appendChild(inp);
    });
    document.body.appendChild(form);
    form.submit();
    form.remove();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>