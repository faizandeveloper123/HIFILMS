<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
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

$ajaxAction = isset($_POST['action']) ? $_POST['action'] : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ajaxAction !== '' && $ajaxAction !== 'dump') {
    $teacher_id = isset($_POST['teacher_id']) ? (int) $_POST['teacher_id'] : 0;
    $class_id   = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
    $section_id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
    $subject_id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;

    if ($ajaxAction === 'GetTeacherAssignedSubjects') {
        $subjects = array();
        $stmt = db_prepare("SELECT s.subject_name FROM teacher_subjects ts JOIN subjects s ON s.subject_id = ts.subject_id WHERE ts.teacher_id = ? AND ts.class_id = ? AND ts.section_id = ? ORDER BY s.subject_name ASC");
        $stmt->bind_param('iii', $teacher_id, $class_id, $section_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $html = '';
        while ($row = $res->fetch_assoc()) {
            $html .= '<span class="badge badge-success">' . htmlspecialchars($row['subject_name'], ENT_QUOTES) . '</span>';
        }
        if ($html === '') {
            $html = '<span class="text-muted">No subjects assigned</span>';
        }
        echo $html;
        exit;
    }

    if ($subject_id > 0 && $teacher_id > 0 && $class_id > 0 && $section_id > 0) {
        if ($ajaxAction === 'TeacherSubjectAllocation') {
            $stmt = db_prepare("INSERT IGNORE INTO teacher_subjects (teacher_id, class_id, section_id, subject_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('iiii', $teacher_id, $class_id, $section_id, $subject_id);
            $stmt->execute();
            echo 'ok';
            exit;
        }
        if ($ajaxAction === 'DeleteTeacherSubjectAllocation') {
            $stmt = db_prepare("DELETE FROM teacher_subjects WHERE teacher_id = ? AND class_id = ? AND section_id = ? AND subject_id = ?");
            $stmt->bind_param('iiii', $teacher_id, $class_id, $section_id, $subject_id);
            $stmt->execute();
            echo 'ok';
            exit;
        }
    }
    echo 'error';
    exit;
}

$teachers = array();
$res = db_query("SELECT user_id, full_name, role FROM users WHERE status = 'active' ORDER BY full_name ASC");
while ($row = $res->fetch_assoc()) {
    $teachers[(int) $row['user_id']] = array('name' => $row['full_name'], 'role' => $row['role']);
}

$teacher_id = isset($_GET['teacher']) ? (int) $_GET['teacher'] : 0;
$teacher_name = '';
$teacher_role = '';
if ($teacher_id > 0 && isset($teachers[$teacher_id])) {
    $teacher_name = $teachers[$teacher_id]['name'];
    $teacher_role = $teachers[$teacher_id]['role'];
}

$subjects = array();
$res = db_query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC");
while ($row = $res->fetch_assoc()) {
    $subjects[(int) $row['subject_id']] = $row['subject_name'];
}

$sections = array();
if ($teacher_id > 0) {
    $res = db_query("SELECT c.class_id, c.class_name, s.section_id, s.section_name FROM sections s JOIN classes c ON c.class_id = s.class_id ORDER BY c.class_name ASC, s.section_name ASC");
    while ($row = $res->fetch_assoc()) {
        $sections[] = array('class_id' => (int) $row['class_id'], 'class_name' => $row['class_name'], 'section_id' => (int) $row['section_id'], 'section_name' => $row['section_name']);
    }
}

$assigned = array();
if ($teacher_id > 0) {
    $stmt = db_prepare("SELECT class_id, section_id, subject_id FROM teacher_subjects WHERE teacher_id = ?");
    $stmt->bind_param('i', $teacher_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $assigned[$row['class_id'] . '-' . $row['section_id']][] = (int) $row['subject_id'];
    }
}

$total_assigned = count($assigned);

include __DIR__ . '/includes/header.php';
?>
<style>
    .tsa-panel { background: #fff; padding: 16px 18px 20px; border-radius: 6px; }
    .tsa-toolbar { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 14px 16px; margin-bottom: 16px; }
    .tsa-toolbar label { font-weight: 600; color: #495057; font-size: 13px; }
    .tsa-teacher-meta { font-size: 13px; color: #6c757d; margin-top: 6px; }
    .tsa-teacher-meta strong { color: #2c3e50; }
    .tsa-table { table-layout: fixed; width: 100%; }
    .tsa-table th { background: #2c3e50; color: #fff; border-color: #2c3e50 !important; vertical-align: middle !important; }
    .tsa-table td { vertical-align: middle !important; }
    .tsa-class-cell { font-weight: 600; color: #2c3e50; }
    .tsa-class-cell small { display: block; font-weight: 400; color: #8a97a5; font-size: 11px; margin-top: 2px; }
    .tsa-assigned-cell .badge { font-size: 11px; padding: 4px 8px; border-radius: 20px; margin: 2px; display: inline-block; }
    .badge-success { background-color: #28a745 !important; color: #fff !important; }
    .tsa-filter-input { max-width: 320px; }
    .tsa-count-pill { display: inline-block; background: #e7f7fc; color: #0b7285; border-radius: 20px; padding: 2px 12px; font-size: 12px; font-weight: 600; margin-left: 8px; }
    .select2-container .select2-choices { min-height: 34px; }
</style>
<div class="row" style="background-color: white; margin-top:0;">
  <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
  <a href="<?php echo BASE_URL; ?>academic_settings.php">Academic Settings</a> &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
  Assign Subjects to Teachers

  <div class="nav-container">
    <div class="nav-bar">
      <a href="<?php echo BASE_URL; ?>manage_exams.php" class="nav-item ">
        <i class="fa fa fa-plus"></i>Manage Exams
      </a>
      <a href="<?php echo BASE_URL; ?>subjects.php" class="nav-item ">
        <i class="fa fa-book"></i>Manage Subjects
      </a>
      <a href="<?php echo BASE_URL; ?>class_subjects.php" class="nav-item ">
        <i class="fa fa-layer-group"></i> Class Subjects
      </a>
      <a href="<?php echo BASE_URL; ?>teacher_subjects_allocation.php" class="nav-item active">
        <i class="fa fa-chalkboard-teacher"></i> Teacher Subjects
      </a>
      <a href="<?php echo BASE_URL; ?>create_awardList.php" class="nav-item ">
        <i class="fa fa-list"></i> Award List
      </a>
      <a href="<?php echo BASE_URL; ?>grades_marks.php" class="nav-item ">
        <i class="fa fa fa-star"></i> Grade Settings
      </a>
      <a href="<?php echo BASE_URL; ?>upload_signature.php" class="nav-item ">
        <i class="fa fa-signature"></i> Academic Settings
      </a>
      <a href="<?php echo BASE_URL; ?>manage_classes.php" class="nav-item ">
        <i class="fa fa-users"></i> Class & Sections
      </a>
    </div>
  </div>
</div>

<section class="add_sub_agent" id="table_sub_agent">
  <div class="container">
    <div class="tsa-panel">
      <h3 style="margin-top:0;"><strong>Subjects Allocation To Teacher</strong></h3>

      <div class="tsa-toolbar">
        <form method="get" action="<?php echo BASE_URL; ?>teacher_subjects_allocation.php">
          <div class="row">
            <div class="col-md-4 col-sm-8">
              <label class="required">Teacher</label>
              <select name="teacher" class="form-control" title="Select Teacher">
                <option value="">Select Teacher</option>
                <?php foreach ($teachers as $uid => $t): ?>
                <option value="<?php echo (int) $uid; ?>" <?php echo $teacher_id === $uid ? 'selected' : ''; ?>>
                  <?php echo e($t['name'] . ' - ' . ($t['role'] !== '' ? $t['role'] : 'Teacher')); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2 col-sm-4">
              <label>&nbsp;</label><br>
              <button type="submit" class="btn btn-primary">
                <i class="fa fa-search"></i> Search
              </button>
            </div>
          </div>
        </form>
      </div>

      <?php if ($teacher_id > 0): ?>

      <div class="tsa-teacher-meta">
        Allocating subjects for <strong><?php echo e($teacher_name); ?></strong>
        <span class="tsa-count-pill"><?php echo (int) $total_assigned; ?> class-section(s) with subjects</span>
      </div>

      <div class="tsa-toolbar" style="margin-top:16px;">
        <input type="text" id="tsaClassFilter" class="form-control tsa-filter-input" placeholder="Filter by class or section...">
      </div>

      <div class="table-responsive" style="margin-top:10px;">
        <table class="table table-bordered tsa-table">
          <thead>
            <tr>
              <th width="22%">Class</th>
              <th width="28%">Assigned Subjects</th>
              <th width="50%">Change Allocation</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sections as $sec): ?>
            <?php
            $key = $sec['class_id'] . '-' . $sec['section_id'];
            $list = isset($assigned[$key]) ? $assigned[$key] : array();
            $label = $sec['class_name'] . ' - ' . $sec['section_name'];
            ?>
            <tr class="tsa-row" data-search="<?php echo e(strtolower($label)); ?>">
              <td class="tsa-class-cell">
                <?php echo e($label); ?>
                <small>Class ID: <?php echo (int) $sec['class_id']; ?> | Section ID: <?php echo (int) $sec['section_id']; ?></small>
              </td>
              <td class="tsa-assigned-cell">
                <span id="assigned-<?php echo (int) $sec['class_id']; ?>-<?php echo (int) $sec['section_id']; ?>">
                  <?php if (count($list) === 0): ?>
                  <span class="text-muted">No subjects assigned</span>
                  <?php else: ?>
                  <?php foreach ($list as $sid): ?>
                  <?php if (isset($subjects[$sid])): ?>
                  <span class="badge badge-success"><?php echo e($subjects[$sid]); ?></span>
                  <?php endif; ?>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </span>
              </td>
              <td>
                <select class="cs-subject-dropdown form-control" multiple style="width:100%;"
                        data-class-id="<?php echo (int) $sec['class_id']; ?>"
                        data-section-id="<?php echo (int) $sec['section_id']; ?>"
                        data-teacher-id="<?php echo (int) $teacher_id; ?>"
                        placeholder="Select subjects to assign">
                  <?php foreach ($subjects as $sid => $sname): ?>
                  <option value="<?php echo (int) $sid; ?>" <?php echo in_array($sid, $list) ? 'selected' : ''; ?>><?php echo e($sname); ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php else: ?>

      <div class="alert alert-info">
        <strong>Note:</strong> Select a teacher and click <strong>Search</strong> to allocate subjects
        class-section wise.
      </div>

      <?php endif; ?>
    </div>
  </div>
</section>

<script>
  $(function () {
    $('.cs-subject-dropdown').select2({
      width: '100%',
      placeholder: 'Select subjects to assign'
    });
  });

  function refreshAssignedSubjects(classId, sectionId, teacherId) {
    var cell = '#assigned-' + classId + '-' + sectionId;
    $.ajax({
      url: '<?php echo BASE_URL; ?>teacher_subjects_allocation.php',
      method: 'POST',
      data: {
        action: 'GetTeacherAssignedSubjects',
        class_id: classId,
        section_id: sectionId,
        teacher_id: teacherId
      },
      success: function (response) {
        $(cell).html(response);
      },
      error: function () {
        $(cell).html('<span class="text-danger">Error loading</span>');
      }
    });
  }

  $(document).ready(function () {
    $('.cs-subject-dropdown').each(function () {
      $(this).data('prev-val', $(this).val() || []);
    });

    $('.cs-subject-dropdown').on('change', function () {
      var $select = $(this);
      var classId = $select.data('class-id');
      var sectionId = $select.data('section-id');
      var teacherId = $select.data('teacher-id');
      var prevVal = $select.data('prev-val') || [];
      var currVal = $select.val() || [];

      var added = currVal.filter(function (v) { return prevVal.indexOf(v) === -1; });
      var removed = prevVal.filter(function (v) { return currVal.indexOf(v) === -1; });

      $select.data('prev-val', currVal.slice());

      function doAjax(action, subjectId) {
        $.ajax({
          url: '<?php echo BASE_URL; ?>teacher_subjects_allocation.php',
          method: 'POST',
          data: {
            action: action,
            subject_id: subjectId,
            class_id: classId,
            section_id: sectionId,
            teacher_id: teacherId
          },
          success: function () {
            refreshAssignedSubjects(classId, sectionId, teacherId);
          },
          error: function () {
            alert('Error updating subject allocation.');
          }
        });
      }

      $.each(added, function (i, v) { doAjax('TeacherSubjectAllocation', v); });
      $.each(removed, function (i, v) { doAjax('DeleteTeacherSubjectAllocation', v); });
    });

    $('#tsaClassFilter').on('keyup', function () {
      var q = $(this).val().toLowerCase().trim();
      $('.tsa-row').each(function () {
        var hay = $(this).data('search') || '';
        $(this).toggle(hay.indexOf(q) !== -1);
      });
    });
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>