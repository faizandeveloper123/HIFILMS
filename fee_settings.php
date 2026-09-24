<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Update Monthly Fee';

$class_id   = (int) ($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$section_id = (int) ($_GET['section'] ?? $_POST['section_id'] ?? 0);
$session    = trim($_GET['session'] ?? $_POST['session'] ?? '');

$message = '';
$error   = '';

$classes = [];
$res = db_query("SELECT class_id, class_name, monthly_fee, misc_fee FROM classes ORDER BY class_id");
if ($res) { while ($r = $res->fetch_assoc()) { $classes[] = $r; } }

$sections = [];
if ($class_id > 0) {
    $st = db_prepare("SELECT section_id, section_name FROM sections WHERE class_id = ? ORDER BY section_id");
    $st->bind_param('i', $class_id);
    $st->execute();
    $sr = $st->get_result();
    while ($r = $sr->fetch_assoc()) { $sections[] = $r; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'UpdateFeeSettings') {
    $rows = $_POST['fees'] ?? [];
    if (is_array($rows) && count($rows) > 0) {
        $upd = db_prepare("UPDATE students SET monthly_fee=?, transport_fee=?, miscellaneous_fee=?, discount_reason=?, family_code=?, payment_mode=? WHERE student_id=?");
        $count = 0;
        foreach ($rows as $sid => $f) {
            $sid = (int) $sid;
            if ($sid <= 0) { continue; }
            $monthly = (float) ($f['monthly_fee'] ?? 0);
            $transport = (float) ($f['transport_fee'] ?? 0);
            $misc = (float) ($f['miscellaneous_fee'] ?? 0);
            $reason = trim($f['discount_reason'] ?? '');
            $family = trim($f['family_code'] ?? '');
            $mode = trim($f['payment_mode'] ?? '');
            $upd->bind_param('dddsssi', $monthly, $transport, $misc, $reason, $family, $mode, $sid);
            try { $upd->execute(); $count++; } catch (Exception $ex) { $error = 'Error: ' . $ex->getMessage(); }
        }
        if ($error === '') { $message = $count . ' student(s) fee updated successfully.'; }
    } else {
        $error = 'No fee data submitted.';
    }
}

$classDefault = null;
foreach ($classes as $c) { if ((int) $c['class_id'] === $class_id) { $classDefault = $c; break; } }

$students = [];
if ($class_id > 0) {
    $sql = "SELECT s.student_id, s.first_name, s.last_name, s.gr_no, s.monthly_fee, s.transport_fee, s.miscellaneous_fee, s.discount_reason, s.family_code, s.payment_mode, c.class_name, sec.section_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.class_id
            LEFT JOIN sections sec ON s.section_id = sec.section_id
            WHERE s.class_id = ?";
    if ($section_id > 0) { $sql .= " AND s.section_id = ?"; }
    $sql .= " ORDER BY s.gr_no ASC, s.first_name ASC";
    $stmt = db_prepare($sql);
    if ($section_id > 0) { $stmt->bind_param('ii', $class_id, $section_id); }
    else { $stmt->bind_param('i', $class_id); }
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) { $students[] = $row; }
}

include __DIR__ . '/includes/header.php';
?>
<div class="main-content">
<div class="container-fluid">

<a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard </a> &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
<a href="<?php echo BASE_URL; ?>students_reports.php">Classwise Students Reports </a> &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
Update Monthly Fee

<h3 style="margin-top:15px;">Update Monthly Fee</h3>

<?php if ($message !== ''): ?>
<div class="alert alert-success"><?php echo e($message); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
<div class="alert alert-danger"><?php echo e($error); ?></div>
<?php endif; ?>

<form method="get" class="form-inline" style="margin-bottom:15px;">
    <div class="form-group">
        <label>Class:</label>
        <select name="class_id" class="form-control" onchange="this.form.submit()">
            <option value="0">-- Select Class --</option>
            <?php foreach ($classes as $c): ?>
            <option value="<?php echo (int) $c['class_id']; ?>" <?php echo ($class_id === (int) $c['class_id']) ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Section:</label>
        <select name="section" class="form-control">
            <option value="0">All Sections</option>
            <?php foreach ($sections as $s): ?>
            <option value="<?php echo (int) $s['section_id']; ?>" <?php echo ($section_id === (int) $s['section_id']) ? 'selected' : ''; ?>><?php echo e($s['section_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <input type="hidden" name="session" value="<?php echo e($session); ?>">
    <button type="submit" class="btn btn-primary">Load Students</button>
</form>

<?php if ($classDefault !== null): ?>
<div class="alert alert-info">
    Class default fee &mdash; Monthly: <b><?php echo number_format((float) $classDefault['monthly_fee'], 2); ?></b>,
    Misc: <b><?php echo number_format((float) $classDefault['misc_fee'], 2); ?></b>
</div>
<?php endif; ?>

<?php if ($class_id > 0 && count($students) > 0): ?>
<form method="post">
<input type="hidden" name="action" value="UpdateFeeSettings">
<input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
<input type="hidden" name="section_id" value="<?php echo $section_id; ?>">
<input type="hidden" name="session" value="<?php echo e($session); ?>">
<div style="margin-bottom:10px;">
    <button type="button" class="btn btn-default btn-sm" onclick="applyFeeToAll()"><i class="fa fa-copy"></i> Apply first row's fee to all</button>
</div>
<div class="table-responsive">
<table class="table table-bordered table-striped" style="background:#fff;">
    <thead>
        <tr>
            <th width="40">#</th>
            <th>GR No</th>
            <th>Student Name</th>
            <th>Monthly Fee</th>
            <th>Transport Fee</th>
            <th>Misc. Fee</th>
            <th>Discount Reason</th>
            <th>Family Code</th>
            <th>Payment Mode</th>
        </tr>
    </thead>
    <tbody>
    <?php $sn = 0; foreach ($students as $s): $sn++; $sid = (int) $s['student_id']; ?>
        <tr>
            <td><?php echo $sn; ?></td>
            <td><?php echo e($s['gr_no']); ?></td>
            <td><?php echo e(trim($s['first_name'] . ' ' . $s['last_name'])); ?></td>
            <td><input type="number" step="0.01" min="0" class="form-control input-sm fee-monthly" name="fees[<?php echo $sid; ?>][monthly_fee]" value="<?php echo e($s['monthly_fee']); ?>" style="min-width:100px;"></td>
            <td><input type="number" step="0.01" min="0" class="form-control input-sm fee-transport" name="fees[<?php echo $sid; ?>][transport_fee]" value="<?php echo e($s['transport_fee']); ?>" style="min-width:100px;"></td>
            <td><input type="number" step="0.01" min="0" class="form-control input-sm fee-misc" name="fees[<?php echo $sid; ?>][miscellaneous_fee]" value="<?php echo e($s['miscellaneous_fee']); ?>" style="min-width:100px;"></td>
            <td><input type="text" class="form-control input-sm" name="fees[<?php echo $sid; ?>][discount_reason]" value="<?php echo e($s['discount_reason']); ?>" style="min-width:130px;"></td>
            <td><input type="text" class="form-control input-sm" name="fees[<?php echo $sid; ?>][family_code]" value="<?php echo e($s['family_code']); ?>" style="min-width:90px;"></td>
            <td>
                <select class="form-control input-sm" name="fees[<?php echo $sid; ?>][payment_mode]">
                    <option value="">--</option>
                    <?php foreach (['Cash', 'Bank', 'Online', 'Cheque'] as $pm): ?>
                    <option value="<?php echo $pm; ?>" <?php echo ($s['payment_mode'] === $pm) ? 'selected' : ''; ?>><?php echo $pm; ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save Fee Settings</button>
</form>
<script>
function applyFeeToAll() {
    var m = document.querySelector('.fee-monthly');
    var t = document.querySelector('.fee-transport');
    var x = document.querySelector('.fee-misc');
    if (!m) return;
    document.querySelectorAll('.fee-monthly').forEach(function (el) { el.value = m.value; });
    document.querySelectorAll('.fee-transport').forEach(function (el) { el.value = t.value; });
    document.querySelectorAll('.fee-misc').forEach(function (el) { el.value = x.value; });
}
</script>
<?php elseif ($class_id > 0): ?>
<div class="alert alert-info">No students found for the selected class/section.</div>
<?php else: ?>
<div class="alert alert-info">Please select a class to update monthly fee.</div>
<?php endif; ?>

</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
