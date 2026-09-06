<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin', 'accounts']);

$page_title = 'PayRoll Settings';

$message = '';
$error = '';

$keys = [
    'payroll_absent_dec', 'payroll_absent_percentage',
    'payroll_leave_dec', 'payroll_no_of_leave', 'payroll_leave_percentage',
    'payroll_late_dec', 'payroll_no_of_late', 'payroll_late_percentage',
    'payroll_short_dec', 'payroll_no_of_short', 'payroll_short_percentage',
    'payroll_deduction_percent', 'payroll_allowance_percent',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'UpdatePayRoll') {
    foreach ($keys as $k) {
        $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : '0';
        $v = ($v === '') ? '0' : $v;
        $st2 = db_prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $st2->bind_param('ss', $k, $v);
        $st2->execute();
    }
    $message = 'Payroll settings updated successfully!';
}

$groups = [
    'absent' => [
        'title' => 'Absent Deduction in PayRoll',
        'enable_key' => 'payroll_absent_dec',
        'allow_key' => null,
        'allow_label' => null,
        'pct_key' => 'payroll_absent_percentage',
        'pct_label' => 'Deduction Percentage On Absent',
        'pct_id' => 'absentPer',
    ],
    'leave' => [
        'title' => 'Leave Deduction in PayRoll',
        'enable_key' => 'payroll_leave_dec',
        'allow_key' => 'payroll_no_of_leave',
        'allow_label' => 'Number Of Leaves Allow',
        'pct_key' => 'payroll_leave_percentage',
        'pct_label' => 'Deduction Percentage On Leave',
        'pct_id' => 'leavePer',
    ],
    'late' => [
        'title' => 'Late Arrival Deduction in PayRoll',
        'enable_key' => 'payroll_late_dec',
        'allow_key' => 'payroll_no_of_late',
        'allow_label' => 'Number Of Late Arrival Allow',
        'pct_key' => 'payroll_late_percentage',
        'pct_label' => 'Deduction Percentage On Late Arrival',
        'pct_id' => 'latePer',
    ],
    'short' => [
        'title' => 'Short Leave Deduction in PayRoll',
        'enable_key' => 'payroll_short_dec',
        'allow_key' => 'payroll_no_of_short',
        'allow_label' => 'Number Of Short Leave Allow',
        'pct_key' => 'payroll_short_percentage',
        'pct_label' => 'Deduction Percentage On Short Leave',
        'pct_id' => 'shortPer',
    ],
];

include __DIR__ . '/includes/header.php';
?>
<style>
.status-badge { padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; display:inline-block; }
.ps-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:18px; margin-bottom:16px; }
.ps-card h4 { font-size:15px; font-weight:800; color:#111827; margin:0 0 16px; padding-bottom:12px; border-bottom:1px solid #F3F4F6; }
.ps-card h4 i { color:#FF7A1B; margin-right:8px; }
.enable-switch { font-weight:700; font-size:14px; color:#111827; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-cog"></i> Update PayRoll Settings</h3>
        </div>

        <form method="post" action="payroll_setting.php">
            <input type="hidden" name="action" value="UpdatePayRoll">

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(340px,1fr)); gap:16px;">
                <?php foreach ($groups as $g): $enabled = (int)get_setting($g['enable_key'], '1'); ?>
                <div class="ps-card">
                    <h4><i class="fa fa-check-circle-o"></i> <?php echo e($g['title']); ?></h4>
                    <div class="form-group">
                        <label class="enable-switch">
                            <input type="checkbox" name="<?php echo e($g['enable_key']); ?>" value="1" class="ps-flag" id="flag-<?php echo e($g['pct_id']); ?>" <?php echo $enabled ? 'checked' : ''; ?>>
                            <?php echo e($g['title']); ?>
                        </label>
                    </div>
                    <?php if ($g['allow_key'] !== null): ?>
                    <div class="form-group">
                        <label><?php echo e($g['allow_label']); ?></label>
                        <select name="<?php echo e($g['allow_key']); ?>" class="form-control ps-extra">
                            <?php for ($n = 1; $n <= 30; $n++): ?>
                                <option value="<?php echo $n; ?>" <?php echo (int)get_setting($g['allow_key'], '0') === $n ? 'selected' : ''; ?>><?php echo $n; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group" id="<?php echo e($g['pct_id']); ?>">
                        <label><?php echo e($g['pct_label']); ?></label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control ps-extra" name="<?php echo e($g['pct_key']); ?>" value="<?php echo e(get_setting($g['pct_key'], '100')); ?>">
                            <span class="input-group-addon">%</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="ps-card">
                <h4><i class="fa fa-percent"></i> Default Payroll Percentages</h4>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Default Allowance (%)</label>
                            <input type="number" step="0.01" name="payroll_allowance_percent" class="form-control" value="<?php echo e(get_setting('payroll_allowance_percent', '0')); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Default Deduction (%)</label>
                            <input type="number" step="0.01" name="payroll_deduction_percent" class="form-control" value="<?php echo e(get_setting('payroll_deduction_percent', '0')); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="padding:10px 34px;"><i class="fa fa-save"></i> Update</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('input', function(e){
    if (e.target.classList && e.target.classList.contains('ps-flag')) {
        var flagId = e.target.id.replace('flag-', '');
        var per = document.getElementById(flagId);
        if (per) {
            per.style.opacity = e.target.checked ? 1 : 0.4;
            per.querySelectorAll('select,input').forEach(function(inp){ inp.disabled = !e.target.checked; });
        }
    }
});
document.querySelectorAll('.ps-flag').forEach(function(cb){
    var per = document.getElementById(cb.id.replace('flag-', ''));
    if (per) {
        per.style.opacity = cb.checked ? 1 : 0.4;
        per.querySelectorAll('select,input').forEach(function(inp){ inp.disabled = !cb.checked; });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>