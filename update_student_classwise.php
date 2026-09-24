<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Update Student Info';

$class_id   = (int) ($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$section_id = (int) ($_GET['section'] ?? $_POST['section_id'] ?? 0);

$message = '';
$error   = '';

$classes = [];
$res = db_query("SELECT class_id, class_name FROM classes ORDER BY class_id");
if ($res) { while ($r = $res->fetch_assoc()) { $classes[] = $r; } }

$sections = [];
if ($class_id > 0) {
    $st = db_prepare("SELECT section_id, section_name FROM sections WHERE class_id = ? ORDER BY section_id");
    $st->bind_param('i', $class_id);
    $st->execute();
    $sr = $st->get_result();
    while ($r = $sr->fetch_assoc()) { $sections[] = $r; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'UpdateClasswise') {
    $rows = $_POST['students'] ?? [];
    if (is_array($rows) && count($rows) > 0) {
        $upd = db_prepare("UPDATE students SET first_name=?, last_name=?, father_name=?, phone=?, dob=?, address=?, roll_no=?, gender=?, status=? WHERE student_id=?");
        $count = 0;
        foreach ($rows as $sid => $f) {
            $sid = (int) $sid;
            if ($sid <= 0) { continue; }
            $fn = trim($f['first_name'] ?? '');
            if ($fn === '') { continue; }
            $ln     = trim($f['last_name'] ?? '');
            $father = trim($f['father_name'] ?? '');
            $phone  = trim($f['phone'] ?? '');
            $dob    = trim($f['dob'] ?? '');
            if ($dob === '' || $dob === '0000-00-00') { $dob = null; }
            $addr   = trim($f['address'] ?? '');
            $roll   = trim($f['roll_no'] ?? '');
            $gender = (($f['gender'] ?? 'male') === 'female') ? 'female' : 'male';
            $status = (int) ($f['status'] ?? 1);
            $upd->bind_param('ssssssssii', $fn, $ln, $father, $phone, $dob, $addr, $roll, $gender, $status, $sid);
            try { $upd->execute(); $count++; } catch (Exception $ex) { $error = 'Error: ' . $ex->getMessage(); }
        }
        if ($error === '') { $message = $count . ' student(s) updated successfully.'; }
    } else {
        $error = 'No student data submitted.';
    }
}

$students = [];
if ($class_id > 0) {
    $sql = "SELECT s.*, c.class_name, sec.section_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.class_id
            LEFT JOIN sections sec ON s.section_id = sec.section_id
            WHERE s.class_id = ?";
    if ($section_id > 0) { $sql .= " AND s.section_id = ?"; }
    $sql .= " ORDER BY s.gr_no ASC, s.first_name ASC";
    $stmt = db_prepare($sql);
    if ($section_id > 0) {
        $stmt->bind_param('ii', $class_id, $section_id);
    } else {
        $stmt->bind_param('i', $class_id);
    }
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
Update Student Info

<h3 style="margin-top:15px;">Update Student Info</h3>

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
    <button type="submit" class="btn btn-primary">Load Students</button>
</form>

<?php if ($class_id > 0 && count($students) > 0): ?>
<form method="post">
<input type="hidden" name="action" value="UpdateClasswise">
<input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
<input type="hidden" name="section_id" value="<?php echo $section_id; ?>">
<div class="table-responsive">
<table class="table table-bordered table-striped" style="background:#fff;">
    <thead>
        <tr>
            <th width="40">#</th>
            <th>GR No</th>
            <th>Student Name</th>
            <th>Father Name</th>
            <th>Phone</th>
            <th>DOB</th>
            <th>Roll No</th>
            <th>Gender</th>
            <th>Status</th>
            <th>Address</th>
        </tr>
    </thead>
    <tbody>
    <?php $sn = 0; foreach ($students as $s): $sn++; $sid = (int) $s['student_id']; ?>
        <tr>
            <td><?php echo $sn; ?></td>
            <td><?php echo e($s['gr_no']); ?></td>
            <td><input type="text" class="form-control input-sm" name="students[<?php echo $sid; ?>][first_name]" value="<?php echo e($s['first_name']); ?>" style="min-width:140px;"></td>
            <td><input type="text" class="form-control input-sm" name="students[<?php echo $sid; ?>][father_name]" value="<?php echo e($s['father_name']); ?>" style="min-width:140px;"></td>
            <td><input type="text" class="form-control input-sm" name="students[<?php echo $sid; ?>][phone]" value="<?php echo e($s['phone']); ?>" style="min-width:110px;"></td>
            <td><input type="date" class="form-control input-sm" name="students[<?php echo $sid; ?>][dob]" value="<?php echo ($s['dob'] && $s['dob'] !== '0000-00-00') ? e($s['dob']) : ''; ?>" style="min-width:130px;"></td>
            <td><input type="text" class="form-control input-sm" name="students[<?php echo $sid; ?>][roll_no]" value="<?php echo e($s['roll_no']); ?>" style="min-width:70px;"></td>
            <td>
                <select class="form-control input-sm" name="students[<?php echo $sid; ?>][gender]">
                    <option value="male" <?php echo ($s['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo ($s['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                </select>
            </td>
            <td>
                <select class="form-control input-sm" name="students[<?php echo $sid; ?>][status]">
                    <option value="1" <?php echo ((int) $s['status'] === 1) ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo ((int) $s['status'] === 0) ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </td>
            <td><input type="text" class="form-control input-sm" name="students[<?php echo $sid; ?>][address]" value="<?php echo e($s['address']); ?>" style="min-width:180px;"></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save Changes</button>
</form>
<?php elseif ($class_id > 0): ?>
<div class="alert alert-info">No students found for the selected class/section.</div>
<?php else: ?>
<div class="alert alert-info">Please select a class to update student information.</div>
<?php endif; ?>

</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
