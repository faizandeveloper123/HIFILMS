<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin', 'accounts']);

$__migrate = [
    "CREATE TABLE IF NOT EXISTS employee_security (id INT AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, month VARCHAR(7) NOT NULL, security_amount DECIMAL(10,2) DEFAULT 0, paid DECIMAL(10,2) DEFAULT 0, note VARCHAR(255) DEFAULT NULL, UNIQUE KEY uq_emp_month (employee_id, month))",
];
foreach ($__migrate as $__sql) { try { db_query($__sql); } catch (\Throwable $e) {} }

$page_title = 'View Staff Security Fee';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'UpdateSecurity') {
    $emp_id = (int) ($_POST['emp_id'] ?? 0);
    $month = trim($_POST['month'] ?? '');
    $amount = (float) ($_POST['security_amount'] ?? 0);
    $paid = (float) ($_POST['paid'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $error = 'Month must be in YYYY-MM format (e.g. 2026-09).';
    } elseif ($emp_id <= 0) {
        $error = 'Invalid employee.';
    } else {
        $st2 = db_prepare("INSERT INTO employee_security (employee_id, month, security_amount, paid, note) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE security_amount=VALUES(security_amount), paid=VALUES(paid), note=VALUES(note)");
        $st2->bind_param('isdds', $emp_id, $month, $amount, $paid, $note);
        $st2->execute();
        $message = 'Security amount saved for ' . e($month) . '!';
    }
}

$search = trim($_GET['q'] ?? '');
$searchBad = strip_tags($search);

$sql = "SELECT e.* FROM employees e WHERE e.status=1";
$params = [];
$types = '';
if ($search !== '') {
    $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.father_name LIKE ? OR e.designation LIKE ? OR e.department LIKE ? OR e.phone LIKE ?)";
    $like = "%$search%";
    $params = array_fill(0, 6, $like);
    $types = 'ssssss';
}
$sql .= " ORDER BY e.first_name";

$employees = [];
if (count($params) > 0) { $st2 = db_prepare($sql); $st2->bind_param($types, ...$params); $st2->execute(); $res = $st2->get_result(); }
else { $res = db_query($sql); }
while ($row = $res->fetch_assoc()) {
    $row['security'] = null;
    $sec = db_prepare("SELECT month, security_amount, paid, note FROM employee_security WHERE employee_id=? ORDER BY month");
    $sec->bind_param('i', $row['emp_id']);
    $sec->execute();
    $hist = [];
    $resSec = $sec->get_result();
    while ($s = $resSec->fetch_assoc()) {
        $hist[] = $s;
        if ($row['security'] === null || strcmp($s['month'], $row['security']['month']) > 0) {
            $row['security'] = $s;
        }
    }
    $row['history'] = $hist;
    $employees[] = $row;
}

include __DIR__ . '/includes/header.php';
?>
<style>
.ss-page { padding-top: 6px; }
.ss-breadcrumb { font-size: 13px; color: #6B7280; margin-bottom: 14px; }
.ss-breadcrumb a { color: #FF7A1B; text-decoration: none; }
.ss-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; padding: 16px; margin-bottom: 16px; }
.ss-search-input { width: 100%; max-width: 380px; height: 44px; padding: 0 16px; border-radius: 999px; border: 1px solid #D1D5DB; box-shadow: 0 4px 10px rgba(145,158,171,0.12); font-size: 14px; }
.status-badge { padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; display: inline-block; }
.status-present,.status-paid { background:#DCFCE7; color:#16A34A; }
.status-pending,.status-unpaid { background:#FEF3C7; color:#D97706; }
.security-badge { font-weight: 800; }
</style>

<div class="main-content">
    <div class="container-fluid ss-page">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="ss-breadcrumb">
            <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> / <span>PayRoll</span> / <a href="<?php echo BASE_URL; ?>staff_security.php">View Staff Security Fee</a>
        </div>

        <div class="ss-header">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-shield"></i> List View Employee</h3>
            <form method="get" action="staff_security.php" style="display:flex; gap:8px; margin:0;">
                <input type="text" name="q" id="ssSearchInput" class="ss-search-input" placeholder="Search name, department, designation, cell..." value="<?php echo e($searchBad); ?>">
                <button class="btn btn-primary" type="submit"><i class="fa fa-search"></i></button>
            </form>
            <a href="<?php echo BASE_URL; ?>add_emp.php" class="btn btn-success" style="color:#fff;"><i class="fa fa-plus"></i> Add Employee</a>
        </div>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead><tr><th>S.No</th><th>EMPLOYEE</th><th>Department</th><th>Designation</th><th>Cell</th><th>SECURITY</th><th>Details</th></tr></thead>
                <tbody>
                    <?php if (count($employees) === 0): ?>
                        <tr><td colspan="7" style="text-align:center; color:#6B7280; padding:30px;">No employees found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($employees as $i => $e):
                        $sec = $e['security'];
                        $amount = $sec ? (float)$sec['security_amount'] : 0.0;
                        $paidSec = $sec ? (float)$sec['paid'] : 0.0;
                        $remaining = $amount - $paidSec;
                        $history = $e['history'];
                    ?>
                        <tr class="ss-row" data-search="<?php echo e(strtolower(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '') . ' ' . ($e['department'] ?? '') . ' ' . ($e['designation'] ?? '') . ' ' . ($e['phone'] ?? ''))); ?>">
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo e($e['first_name'] . ' ' . $e['last_name']); ?></strong></td>
                            <td><?php echo e($e['department'] ?: '-'); ?></td>
                            <td><?php echo e($e['designation'] ?: '-'); ?></td>
                            <td><?php echo e($e['phone'] ?: '-'); ?></td>
                            <td>
                                <span class="status-badge <?php echo $remaining > 0 ? 'status-pending' : 'status-present'; ?> security-badge">
                                    <?php echo number_format($remaining, 2); ?> <?php echo e(get_setting('currency_symbol', 'Rs.')); ?>
                                </span>
                                <?php if ($sec): ?><br><small style="color:#6B7280;"><?php echo e($sec['month']); ?> balance</small><?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#secHistoryModal" onclick="openHistory(<?php echo $e['emp_id']; ?>, '<?php echo e($e['first_name'] . ' ' . $e['last_name']); ?>')"><i class="fa fa-eye"></i> View</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="secHistoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-shield"></i> Security History - <span id="secEmpName"></span></h4>
            </div>
            <div class="modal-body">
                <table class="table table-bordered" style="margin-bottom:14px;">
                    <thead><tr><th>Month</th><th>Security</th><th>Paid Security</th><th>Balance</th><th>Note</th></tr></thead>
                    <tbody id="secHistoryBody"></tbody>
                </table>
                <h5 style="font-weight:800; border-top:1px solid #E5E7EB; padding-top:10px;">Add / Update Security</h5>
                <form method="post" action="staff_security.php">
                    <input type="hidden" name="action" value="UpdateSecurity">
                    <input type="hidden" name="emp_id" id="secEmpId">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Month (YYYY-MM)</label>
                                <input type="month" name="month" id="secMonth" class="form-control" value="<?php echo date('Y-m'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Security Amount</label>
                                <input type="number" step="0.01" name="security_amount" id="secAmount" class="form-control" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Paid</label>
                                <input type="number" step="0.01" name="paid" id="secPaid" class="form-control" value="0">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Note</label>
                                <input type="text" name="note" class="form-control" placeholder="Optional note">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save Security</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
var allHistory = {};
<?php foreach ($employees as $e): ?>
allHistory[<?php echo $e['emp_id']; ?>] = <?php echo json_encode($e['history']); ?>;
<?php endforeach; ?>

window.openHistory = function(id, name) {
    document.getElementById('secEmpName').textContent = name;
    document.getElementById('secEmpId').value = id;
    var hist = allHistory[id] || [];
    var html = '';
    var totSec = 0, totPaid = 0;
    if (!hist.length) {
        html = '<tr><td colspan="5" style="text-align:center; color:#6B7280; padding:16px;">No security records yet.</td></tr>';
    }
    hist.forEach(function(h){
        var bal = (parseFloat(h.security_amount) || 0) - (parseFloat(h.paid) || 0);
        totSec += parseFloat(h.security_amount) || 0;
        totPaid += parseFloat(h.paid) || 0;
        html += '<tr><td>' + h.month + '</td><td>' + Number(h.security_amount).toFixed(2) + '</td><td>' + Number(h.paid).toFixed(2) + '</td><td>' + bal.toFixed(2) + '</td><td>' + (h.note || '') + '</td></tr>';
    });
    if (hist.length) {
        html += '<tr style="background:#F9FAFB; font-weight:800;"><td>Total</td><td>' + totSec.toFixed(2) + '</td><td>' + totPaid.toFixed(2) + '</td><td>' + (totSec - totPaid).toFixed(2) + '</td><td></td></tr>';
    }
    document.getElementById('secHistoryBody').innerHTML = html;
};

(function(){
    var input = document.getElementById('ssSearchInput');
    var original = input.getAttribute('value') || '';
    input.addEventListener('input', function(){
        var q = this.value.trim().toLowerCase();
        document.querySelectorAll('.ss-row').forEach(function(tr){
            var hay = (tr.getAttribute('data-search') || '').toLowerCase();
            tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
        });
    });
    input.value = original;
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>