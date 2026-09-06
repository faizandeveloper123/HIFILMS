<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Grade Settings';

db_query("CREATE TABLE IF NOT EXISTS grade_scales (
  grade_id INT(11) NOT NULL AUTO_INCREMENT,
  min_marks DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  max_marks DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  grade VARCHAR(10) NOT NULL,
  remarks VARCHAR(191) NOT NULL,
  teacher_remarks VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (grade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$defaults = array(
    array('start' => '80', 'end' => '100', 'grade' => 'A+', 'remarks' => 'Exceptional', 'teacher_remarks' => 'Exceptional achievement! Continue to excel and inspire!'),
    array('start' => '70', 'end' => '79.99', 'grade' => 'A', 'remarks' => 'Excellent', 'teacher_remarks' => 'Excellent performance! Keep up the outstanding work!'),
    array('start' => '60', 'end' => '69.99', 'grade' => 'B', 'remarks' => 'Good', 'teacher_remarks' => 'Good work! Keep aiming higher to reach your full potential.'),
    array('start' => '50', 'end' => '59.99', 'grade' => 'C', 'remarks' => 'Fair', 'teacher_remarks' => "Satisfactory work, but there's room for improvement. Keep going!"),
    array('start' => '33', 'end' => '49.99', 'grade' => 'D', 'remarks' => 'Pass', 'teacher_remarks' => ' Shows potential but needs more consistent effort. Keep striving!'),
    array('start' => '0', 'end' => '32.99', 'grade' => 'F', 'remarks' => 'Failed', 'teacher_remarks' => "Needs improvement. Let's work together to achieve success next term."),
);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'UpdateGrading') {
        $starts = isset($_POST['start']) ? $_POST['start'] : array();
        $ends   = isset($_POST['end']) ? $_POST['end'] : array();
        $grades = isset($_POST['grade']) ? $_POST['grade'] : array();
        $rem    = isset($_POST['remarks']) ? $_POST['remarks'] : array();
        $trem   = isset($_POST['teacher_remarks']) ? $_POST['teacher_remarks'] : array();
        $count = count($starts);

        if ($count > 0) {
            db_query("DELETE FROM grade_scales");
            $ins = db_prepare("INSERT INTO grade_scales (min_marks, max_marks, grade, remarks, teacher_remarks) VALUES (?, ?, ?, ?, ?)");
            for ($i = 0; $i < $count; $i++) {
                $min = is_numeric($starts[$i]) ? (float) $starts[$i] : 0;
                $max = is_numeric($ends[$i]) ? (float) $ends[$i] : 0;
                $g   = isset($grades[$i]) ? trim($grades[$i]) : '';
                $r   = isset($rem[$i]) ? trim($rem[$i]) : '';
                $tr  = isset($trem[$i]) ? trim($trem[$i]) : '';
                if ($g === '') {
                    continue;
                }
                $ins->bind_param('ddsss', $min, $max, $g, $r, $tr);
                $ins->execute();
            }
            $msg = 'Grading saved successfully.';
        } else {
            $msg = 'At least one grade row is required.';
        }
    }
}

if (isset($_GET['default']) && $_GET['default'] == '1') {
    db_query("DELETE FROM grade_scales");
    $ins = db_prepare("INSERT INTO grade_scales (min_marks, max_marks, grade, remarks, teacher_remarks) VALUES (?, ?, ?, ?, ?)");
    foreach ($defaults as $d) {
        $ins->bind_param('ddsss', $d['start'], $d['end'], $d['grade'], $d['remarks'], $d['teacher_remarks']);
        $ins->execute();
    }
    $msg = 'Default grading loaded. Click "Save Grading" to apply.';
}

$rows = array();
$res = db_query("SELECT min_marks, max_marks, grade, remarks, teacher_remarks FROM grade_scales ORDER BY grade_id ASC");
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
if (count($rows) === 0) {
    $rows = $defaults;
}

$row_count = count($rows);

include __DIR__ . '/includes/header.php';
?>
<style>
    .page-header {
        background: #2b2b36;
        color: white;
        padding: 20px 25px;
        border-radius: 8px;
        margin-bottom: 25px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .page-header h2 { margin: 0; font-size: 24px; font-weight: 600; color: white; }
    .breadcrumb { background: transparent; padding: 0; margin: 0 0 10px 0; color: rgba(255,255,255,0.9); }
    .breadcrumb a { color: rgba(255,255,255,0.9); text-decoration: none; }
    .breadcrumb a:hover { color: white; text-decoration: underline; }
    .info-banner { display: flex; align-items: center; gap: 15px; background: #fff7f0; border: 1px solid #ffd8b3; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; }
    .info-banner .info-icon { width: 42px; height: 42px; border-radius: 50%; background: #ff7800; color: white; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .info-banner strong { color: #e67e22; }
    .info-banner p { margin: 2px 0 0; color: #555; font-size: 13px; }
    .grade-card { background: white; border-radius: 8px; padding: 22px 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
    .grade-card-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; }
    .grade-card-header h3 { margin: 0 0 4px; font-weight: 600; }
    .grade-card-header p { margin: 0; color: #888; font-size: 13px; }
    .btn-add-grade { background: #ff7800; border-color: #ff7800; color: white; font-weight: 600; padding: 9px 18px; border-radius: 6px; }
    .btn-add-grade:hover { background: #e56d00; border-color: #e56d00; color: white; }
    table.grade-table thead th { background: #2b2b36; color: white; text-align: center; padding: 12px; border: none; font-weight: 600; font-size: 13px; }
    table.grade-table tbody td { padding: 10px; vertical-align: middle; text-align: center; }
    table.grade-table tbody tr:hover { background-color: #f8f9fa; }
    table.grade-table input.form-control { text-align: center; }
    table.grade-table td:nth-child(5) input, table.grade-table td:nth-child(6) input { text-align: left; }
    .grade-badge-input { font-weight: 700; text-align: center; border-radius: 20px !important; border: 2px solid #ddd !important; transition: all 0.15s ease; }
    .row-index { font-weight: 600; color: #999; }
    .btn-remove-row { background: #fdecea; color: #e74c3c; border: none; border-radius: 5px; padding: 6px 10px; }
    .btn-remove-row:hover { background: #e74c3c; color: white; }
    .warning-note { display: flex; gap: 10px; align-items: flex-start; background: #fff9e6; border: 1px solid #ffe58f; border-radius: 6px; padding: 12px 16px; margin: 18px 0; font-size: 13px; color: #8a6d3b; }
    .warning-note i { color: #e6a700; margin-top: 2px; }
    .form-actions { display: flex; gap: 18px; align-items: center; }
    .btn-save-grading { background: #ff7800; border-color: #ff7800; color: white; font-weight: 600; padding: 10px 26px; border-radius: 6px; }
    .btn-save-grading:hover { background: #e56d00; border-color: #e56d00; color: white; }
    .btn-reset-default { background: transparent; border: none; color: #666; font-weight: 500; text-decoration: underline; cursor: pointer; padding: 0; }
    .btn-reset-default:hover { color: #ff7800; }
</style>

<div class="page-header">
  <nav class="breadcrumb">
    <a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
    <span> <i class="fa fa-angle-double-right"></i> </span>
    <a href="<?php echo BASE_URL; ?>academic_settings.php">Academic Settings</a>
    <span> <i class="fa fa-angle-double-right"></i> </span>
    <span>Grade Settings</span>
  </nav>
  <h2><i class="fa fa-star"></i> Grade Settings</h2>
</div>

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
    <a href="<?php echo BASE_URL; ?>teacher_subjects_allocation.php" class="nav-item ">
      <i class="fa fa-chalkboard-teacher"></i> Teacher Subjects
    </a>
    <a href="<?php echo BASE_URL; ?>create_awardList.php" class="nav-item ">
      <i class="fa fa-list"></i> Award List
    </a>
    <a href="<?php echo BASE_URL; ?>grades_marks.php" class="nav-item active">
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
<br>

<?php if ($msg !== ''): ?>
<div class="alert alert-success alert-dismissible fade in">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <?php echo e($msg); ?>
</div>
<?php endif; ?>

<div class="info-banner">
  <div class="info-icon"><i class="fa fa-info"></i></div>
  <div>
    <strong>How it works?</strong>
    <p>Define a percentage range for each grade along with remarks that will appear on students' report cards and mark sheets.</p>
  </div>
</div>

<div class="grade-card">
  <div class="grade-card-header">
    <div>
      <h3>Grading Criteria</h3>
      <p>Set the percentage range for each grade with remarks and teacher's remarks.</p>
    </div>
    <button type="button" class="btn btn-add-grade" id="addGradeRow"><i class="fa fa-plus"></i> Add New Grade</button>
  </div>

  <form id="gradingForm" action="<?php echo BASE_URL; ?>grades_marks.php" method="post">
    <input type="hidden" name="action" value="UpdateGrading" />

    <div class="table-responsive">
      <table class="table table-bordered grade-table" id="gradeTable">
        <thead>
          <tr>
            <th style="width:4%">#</th>
            <th style="width:13%">Start (%)</th>
            <th style="width:13%">End (%)</th>
            <th style="width:10%">Grade</th>
            <th style="width:27%">Remarks (Report Card)</th>
            <th style="width:27%">Teacher's Remarks (Mark Sheet)</th>
            <th style="width:6%">Action</th>
          </tr>
        </thead>
        <tbody id="gradeTableBody">
          <?php $i = 1; foreach ($rows as $r): ?>
          <tr>
            <td class="row-index"><?php echo (int) $i; ?></td>
            <td><input type="text" class="form-control" name="start[]" value="<?php echo e($r['start'] ?? $r['min_marks']); ?>"></td>
            <td><input type="text" class="form-control" name="end[]" value="<?php echo e($r['end'] ?? $r['max_marks']); ?>"></td>
            <td><input type="text" class="form-control grade-badge-input" name="grade[]" value="<?php echo e($r['grade']); ?>"></td>
            <td><input type="text" class="form-control" name="remarks[]" value="<?php echo e($r['remarks']); ?>"></td>
            <td><input type="text" class="form-control" name="teacher_remarks[]" value="<?php echo e($r['teacher_remarks']); ?>"></td>
            <td><button type="button" class="btn-remove-row" onclick="removeGradeRow(this)" title="Remove row"><i class="fa fa-trash"></i></button></td>
          </tr>
          <?php $i++; endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="warning-note">
      <i class="fa fa-exclamation-triangle"></i>
      <span>To ensure accurate grading, percentage ranges should not overlap and should cover 0% to 100%.</span>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-save-grading"><i class="fa fa-save"></i> Save Grading</button>
      <button type="button" class="btn-reset-default" onclick="resetToDefault()"><i class="fa fa-undo"></i> Reset to Default</button>
    </div>
  </form>
</div>

<script>
    let rowCount = <?php echo (int) $row_count; ?>;

    function colorizeGradeInput(input) {
      const map = {
        'A+': {bg:'#e6f7f5', color:'#0d9488', border:'#0d9488'},
        'A':  {bg:'#e8f0fe', color:'#2563eb', border:'#2563eb'},
        'B':  {bg:'#f3e8fd', color:'#7c3aed', border:'#7c3aed'},
        'C':  {bg:'#fef9e7', color:'#ca8a04', border:'#ca8a04'},
        'D':  {bg:'#fff1e6', color:'#ea580c', border:'#ea580c'},
        'F':  {bg:'#fdecea', color:'#dc2626', border:'#dc2626'}
      };
      const val = input.value.trim().toUpperCase();
      const style = map[val] || {bg:'#fff', color:'#333', border:'#ddd'};
      input.style.background = style.bg;
      input.style.color = style.color;
      input.style.borderColor = style.border;
    }

    document.querySelectorAll('.grade-badge-input').forEach(colorizeGradeInput);

    document.getElementById('gradeTableBody').addEventListener('input', function(e) {
      if (e.target.classList.contains('grade-badge-input')) {
        colorizeGradeInput(e.target);
      }
    });

    function renumberRows() {
      document.querySelectorAll('#gradeTableBody tr').forEach(function(tr, idx) {
        tr.querySelector('.row-index').textContent = idx + 1;
      });
    }

    function removeGradeRow(btn) {
      if (document.querySelectorAll('#gradeTableBody tr').length <= 1) {
        alert('At least one grade row is required.');
        return;
      }
      if (confirm('Remove this grade row?')) {
        btn.closest('tr').remove();
        renumberRows();
      }
    }

    document.getElementById('addGradeRow').addEventListener('click', function() {
      rowCount++;
      const tbody = document.getElementById('gradeTableBody');
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td class="row-index">' + rowCount + '</td>' +
        '<td><input type="text" class="form-control" name="start[]"></td>' +
        '<td><input type="text" class="form-control" name="end[]"></td>' +
        '<td><input type="text" class="form-control grade-badge-input" name="grade[]"></td>' +
        '<td><input type="text" class="form-control" name="remarks[]"></td>' +
        '<td><input type="text" class="form-control" name="teacher_remarks[]"></td>' +
        '<td><button type="button" class="btn-remove-row" onclick="removeGradeRow(this)" title="Remove row"><i class="fa fa-trash"></i></button></td>';
      tbody.appendChild(tr);
      tr.querySelector('input').focus();
    });

    function resetToDefault() {
      if (confirm('This will load the default grading values below. You still need to click "Save Grading" to apply it. Continue?')) {
        window.location.href = '<?php echo BASE_URL; ?>grades_marks.php?default=1';
      }
    }

    setTimeout(function(){ $('.alert').fadeOut('slow'); }, 5000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>