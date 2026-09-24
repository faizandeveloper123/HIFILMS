<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Update Student Pictures';

$class_id   = (int) ($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$section_id = (int) ($_GET['section'] ?? $_POST['section_id'] ?? 0);
$session    = trim($_GET['session'] ?? $_POST['session'] ?? '');

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'UpdatePictures') {
    $dir = __DIR__ . '/uploads/students';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $upd = db_prepare("UPDATE students SET photo = ? WHERE student_id = ?");
    $count = 0;
    $files = $_FILES['photos'] ?? [];
    if (!empty($files['name']) && is_array($files['name'])) {
        foreach ($files['name'] as $sid => $name) {
            $sid = (int) $sid;
            if ($sid <= 0 || $name === '' || ($files['error'][$sid] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { continue; }
            $info = @getimagesize($files['tmp_name'][$sid]);
            if ($info === false) { continue; }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) { $ext = 'jpg'; }
            $photo = 's_' . time() . '_' . $sid . '_' . rand(1000, 9999) . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$sid], $dir . '/' . $photo)) {
                $upd->bind_param('si', $photo, $sid);
                try { $upd->execute(); $count++; } catch (Exception $ex) { $error = 'Error: ' . $ex->getMessage(); }
            }
        }
    }
    if ($error === '') { $message = $count . ' picture(s) updated successfully.'; }
}

$students = [];
if ($class_id > 0) {
    $sql = "SELECT s.student_id, s.first_name, s.last_name, s.father_name, s.gr_no, s.photo, c.class_name, sec.section_name
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
Update Pictures

<h3 style="margin-top:15px;">Update Student Pictures</h3>

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

<?php if ($class_id > 0 && count($students) > 0): ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="UpdatePictures">
<input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
<input type="hidden" name="section_id" value="<?php echo $section_id; ?>">
<input type="hidden" name="session" value="<?php echo e($session); ?>">
<div class="row">
<?php foreach ($students as $s): $sid = (int) $s['student_id']; ?>
    <div class="col-md-3 col-sm-4 col-xs-6" style="margin-bottom:15px;">
        <div style="border:1px solid #ddd; border-radius:6px; padding:10px; text-align:center; background:#fff;">
            <div style="height:150px; display:flex; align-items:center; justify-content:center; background:#f7f7f7; margin-bottom:8px; overflow:hidden;">
                <?php if (!empty($s['photo'])): ?>
                <img src="<?php echo BASE_URL; ?>uploads/students/<?php echo e($s['photo']); ?>" alt="" style="max-width:100%; max-height:150px; object-fit:cover;">
                <?php else: ?>
                <span style="color:#aaa;"><i class="fa fa-user" style="font-size:48px;"></i></span>
                <?php endif; ?>
            </div>
            <div style="font-weight:600; font-size:13px;"><?php echo e(trim($s['first_name'] . ' ' . $s['last_name'])); ?></div>
            <div style="font-size:12px; color:#777;">GR: <?php echo e($s['gr_no']); ?></div>
            <div style="font-size:12px; color:#777; margin-bottom:6px;"><?php echo e($s['class_name']); ?><?php echo $s['section_name'] ? ' - ' . e($s['section_name']) : ''; ?></div>
            <input type="file" name="photos[<?php echo $sid; ?>]" accept="image/*" class="form-control input-sm">
        </div>
    </div>
<?php endforeach; ?>
</div>
<button type="submit" class="btn btn-success"><i class="fa fa-upload"></i> Upload Pictures</button>
</form>
<?php elseif ($class_id > 0): ?>
<div class="alert alert-info">No students found for the selected class/section.</div>
<?php else: ?>
<div class="alert alert-info">Please select a class to update student pictures.</div>
<?php endif; ?>

</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
