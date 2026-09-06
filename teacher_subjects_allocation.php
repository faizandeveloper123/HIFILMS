<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin']);
$page_title = 'Teacher Subjects Allocation';

db_query("CREATE TABLE IF NOT EXISTS teacher_subjects (
  id INT(11) NOT NULL AUTO_INCREMENT,
  teacher_id INT(11) NOT NULL,
  class_id INT(11) NOT NULL,
  section_id INT(11) NOT NULL,
  subject_id INT(11) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ts (teacher_id, class_id, section_id, subject_id),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_allocation') {
        $teacher_id = (int) ($_POST['teacher_id'] ?? 0);
        $class_id = (int) ($_POST['class_id'] ?? 0);
        $section_id = (int) ($_POST['section_id'] ?? 0);
        $subject_id = (int) ($_POST['subject_id'] ?? 0);

        if ($teacher_id <= 0 || $class_id <= 0 || $section_id <= 0 || $subject_id <= 0) {
            $error = 'All fields are required.';
        } else {
            $check = db_prepare("SELECT id FROM teacher_subjects WHERE teacher_id=? AND class_id=? AND section_id=? AND subject_id=?");
            $check->bind_param('iiii', $teacher_id, $class_id, $section_id, $subject_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'This allocation already exists.';
            } else {
                $ins = db_prepare("INSERT INTO teacher_subjects (teacher_id, class_id, section_id, subject_id) VALUES (?, ?, ?, ?)");
                $ins->bind_param('iiii', $teacher_id, $class_id, $section_id, $subject_id);
                $ins->execute();
                $message = 'Subject allocated to teacher successfully.';
            }
        }
    }

    if ($action === 'delete_allocation') {
        $id = (int) ($_POST['allocation_id'] ?? 0);
        if ($id > 0) {
            $st = db_prepare("DELETE FROM teacher_subjects WHERE id=?");
            $st->bind_param('i', $id);
            $st->execute();
            $message = 'Allocation deleted.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($_POST['action'] ?? '', ['add_allocation', 'delete_allocation'])) {
    $ajaxAction = $_POST['action'] ?? '';
    $teacher_id = (int) ($_POST['teacher_id'] ?? 0);
    $class_id = (int) ($_POST['class_id'] ?? 0);
    $section_id = (int) ($_POST['section_id'] ?? 0);
    $subject_id = (int) ($_POST['subject_id'] ?? 0);

    if ($ajaxAction === 'GetSubjectsByClass') {
        $subjects = [];
        $stmt = db_prepare("SELECT s.subject_id, s.subject_name FROM subjects s
                           JOIN class_subjects cs ON cs.subject_id = s.subject_id
                           WHERE cs.class_id = ? ORDER BY s.subject_name ASC");
        $stmt->bind_param('i', $class_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $subjects[] = $row; }
        header('Content-Type: application/json');
        echo json_encode($subjects);
        exit;
    }

    if ($ajaxAction === 'GetSectionsByClass') {
        $sections = [];
        $stmt = db_prepare("SELECT section_id, section_name FROM sections WHERE class_id = ? ORDER BY section_name ASC");
        $stmt->bind_param('i', $class_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $sections[] = $row; }
        header('Content-Type: application/json');
        echo json_encode($sections);
        exit;
    }

    if ($ajaxAction === 'GetTeacherAssignedSubjects') {
        $stmt = db_prepare("SELECT s.subject_name FROM teacher_subjects ts JOIN subjects s ON s.subject_id = ts.subject_id WHERE ts.teacher_id = ? AND ts.class_id = ? AND ts.section_id = ? ORDER BY s.subject_name ASC");
        $stmt->bind_param('iii', $teacher_id, $class_id, $section_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $html = '';
        while ($row = $res->fetch_assoc()) {
            $html .= '<span class="label label-success" style="margin:2px;display:inline-block;">' . e($row['subject_name']) . '</span>';
        }
        if ($html === '') { $html = '<span class="text-muted">No subjects assigned</span>'; }
        echo $html;
        exit;
    }

    if ($ajaxAction === 'TeacherSubjectAllocation' && $subject_id > 0 && $teacher_id > 0 && $class_id > 0 && $section_id > 0) {
        $stmt = db_prepare("INSERT IGNORE INTO teacher_subjects (teacher_id, class_id, section_id, subject_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiii', $teacher_id, $class_id, $section_id, $subject_id);
        $stmt->execute();
        echo 'ok';
        exit;
    }

    if ($ajaxAction === 'DeleteTeacherSubjectAllocation' && $subject_id > 0 && $teacher_id > 0 && $class_id > 0 && $section_id > 0) {
        $stmt = db_prepare("DELETE FROM teacher_subjects WHERE teacher_id = ? AND class_id = ? AND section_id = ? AND subject_id = ?");
        $stmt->bind_param('iiii', $teacher_id, $class_id, $section_id, $subject_id);
        $stmt->execute();
        echo 'ok';
        exit;
    }

    echo 'error';
    exit;
}

$teachers = [];
$res = db_query("SELECT user_id, full_name, role FROM users WHERE status = 'active' AND (role = 'teacher' OR role = 'staff') ORDER BY full_name ASC");
while ($row = $res->fetch_assoc()) {
    $teachers[(int) $row['user_id']] = ['name' => $row['full_name'], 'role' => $row['role']];
}

$classes = [];
$res = db_query("SELECT class_id, class_name FROM classes WHERE status = 1 ORDER BY class_name ASC");
while ($row = $res->fetch_assoc()) { $classes[(int) $row['class_id']] = $row['class_name']; }

$all_subjects = [];
$res = db_query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC");
while ($row = $res->fetch_assoc()) { $all_subjects[(int) $row['subject_id']] = $row['subject_name']; }

$allocations = [];
$res = db_query("SELECT ts.id, ts.teacher_id, u.full_name AS teacher_name, ts.class_id, c.class_name, ts.section_id, s.section_name, ts.subject_id, sub.subject_name
                 FROM teacher_subjects ts
                 JOIN users u ON u.user_id = ts.teacher_id
                 JOIN classes c ON c.class_id = ts.class_id
                 JOIN sections s ON s.section_id = ts.section_id
                 JOIN subjects sub ON sub.subject_id = ts.subject_id
                 ORDER BY u.full_name ASC, c.class_name ASC, s.section_name ASC");
while ($row = $res->fetch_assoc()) { $allocations[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.alloc-table th { background: #2c3e50; color: #fff; }
.alloc-table td { vertical-align: middle !important; }
.alloc-toolbar { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
.alloc-toolbar label { font-weight: 600; color: #495057; font-size: 13px; }
</style>
<div class="row" style="background-color: white; margin-top:0;">
  <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
  <a href="<?php echo BASE_URL; ?>academic_settings.php">Academic Settings</a> &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
  Assign Subjects to Teachers
  <div class="nav-container">
    <div class="nav-bar">
      <a href="<?php echo BASE_URL; ?>manage_exams.php" class="nav-item"><i class="fa fa fa-plus"></i>Manage Exams</a>
      <a href="<?php echo BASE_URL; ?>subjects.php" class="nav-item"><i class="fa fa-book"></i>Manage Subjects</a>
      <a href="<?php echo BASE_URL; ?>class_subjects.php" class="nav-item"><i class="fa fa-layer-group"></i> Class Subjects</a>
      <a href="<?php echo BASE_URL; ?>teacher_subjects_allocation.php" class="nav-item active"><i class="fa fa-chalkboard-teacher"></i> Teacher Subjects</a>
      <a href="<?php echo BASE_URL; ?>create_awardList.php" class="nav-item"><i class="fa fa-list"></i> Award List</a>
      <a href="<?php echo BASE_URL; ?>grades_marks.php" class="nav-item"><i class="fa fa fa-star"></i> Grade Settings</a>
      <a href="<?php echo BASE_URL; ?>upload_signature.php" class="nav-item"><i class="fa fa-signature"></i> Academic Settings</a>
      <a href="<?php echo BASE_URL; ?>manage_classes.php" class="nav-item"><i class="fa fa-users"></i> Class & Sections</a>
    </div>
  </div>
</div>

<section class="add_sub_agent" id="table_sub_agent">
  <div class="container">
    <?php if ($message): ?><div class="alert alert-success" style="margin-top:10px;"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-check-circle"></i> <?php echo e($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger" style="margin-top:10px;"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-exclamation-triangle"></i> <?php echo e($error); ?></div><?php endif; ?>

    <div class="panel panel-default" style="border-radius:8px;overflow:hidden;">
      <div class="panel-body">
        <h3 style="margin-top:0;"><strong>Add New Allocation</strong></h3>
        <form method="post" action="<?php echo BASE_URL; ?>teacher_subjects_allocation.php" class="alloc-toolbar">
          <input type="hidden" name="action" value="add_allocation">
          <div class="row">
            <div class="col-md-3 col-sm-6">
              <label>Teacher *</label>
              <select name="teacher_id" class="form-control" required>
                <option value="">Select Teacher</option>
                <?php foreach ($teachers as $uid => $t): ?>
                  <option value="<?php echo (int) $uid; ?>"><?php echo e($t['name'] . ' (' . $t['role'] . ')'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2 col-sm-6">
              <label>Class *</label>
              <select name="class_id" id="allocClassSelect" class="form-control" required>
                <option value="">Select Class</option>
                <?php foreach ($classes as $cid => $cname): ?>
                  <option value="<?php echo (int) $cid; ?>"><?php echo e($cname); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2 col-sm-6">
              <label>Section *</label>
              <select name="section_id" id="allocSectionSelect" class="form-control" required>
                <option value="">Select Class First</option>
              </select>
            </div>
            <div class="col-md-3 col-sm-6">
              <label>Subject *</label>
              <select name="subject_id" id="allocSubjectSelect" class="form-control" required>
                <option value="">Select Class First</option>
              </select>
            </div>
            <div class="col-md-2 col-sm-6">
              <label>&nbsp;</label><br>
              <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Add</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div class="panel panel-default" style="border-radius:8px;overflow:hidden;">
      <div class="panel-body">
        <h3 style="margin-top:0;"><strong>Current Allocations</strong> <span class="badge" style="background:#0b7285;color:#fff;border-radius:20px;padding:2px 12px;font-size:12px;margin-left:6px;"><?php echo count($allocations); ?></span></h3>
        <div class="table-responsive">
          <table class="table table-bordered table-striped alloc-table">
            <thead>
              <tr>
                <th width="5%" style="text-align:center;">#</th>
                <th width="22%">Teacher</th>
                <th width="15%">Class</th>
                <th width="15%">Section</th>
                <th width="20%">Subject</th>
                <th width="13%">Date</th>
                <th width="10%" style="text-align:center;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($allocations) === 0): ?>
                <tr><td colspan="7" style="text-align:center;padding:30px;color:#6b7280;">No allocations found.</td></tr>
              <?php else: ?>
                <?php $sn = 0; foreach ($allocations as $a): $sn++; ?>
                <tr>
                  <td style="text-align:center;"><?php echo $sn; ?></td>
                  <td><?php echo e($a['teacher_name']); ?></td>
                  <td><?php echo e($a['class_name']); ?></td>
                  <td><?php echo e($a['section_name']); ?></td>
                  <td><?php echo e($a['subject_name']); ?></td>
                  <td><?php echo date('d-M-Y', strtotime($a['created_at'])); ?></td>
                  <td style="text-align:center;">
                    <form method="post" action="<?php echo BASE_URL; ?>teacher_subjects_allocation.php" style="display:inline;" onsubmit="return confirm('Delete this allocation?');">
                      <input type="hidden" name="action" value="delete_allocation">
                      <input type="hidden" name="allocation_id" value="<?php echo (int) $a['id']; ?>">
                      <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i> Delete</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
$(document).ready(function() {
    $('#allocClassSelect').on('change', function() {
        var classId = $(this).val();
        var sectionSel = $('#allocSectionSelect');
        var subjectSel = $('#allocSubjectSelect');
        sectionSel.html('<option value="">Loading...</option>');
        subjectSel.html('<option value="">Select Class First</option>');
        if (!classId) { sectionSel.html('<option value="">Select Class First</option>'); return; }
        $.getJSON('<?php echo BASE_URL; ?>teacher_subjects_allocation.php', { action: 'GetSectionsByClass', class_id: classId }, function(data) {
            var html = '<option value="">Select Section</option>';
            $.each(data, function(i, s) { html += '<option value="' + s.section_id + '">' + s.section_name + '</option>'; });
            sectionSel.html(html);
        });
        $.getJSON('<?php echo BASE_URL; ?>teacher_subjects_allocation.php', { action: 'GetSubjectsByClass', class_id: classId }, function(data) {
            var html = '<option value="">Select Subject</option>';
            $.each(data, function(i, s) { html += '<option value="' + s.subject_id + '">' + s.subject_name + '</option>'; });
            subjectSel.html(html);
        });
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
