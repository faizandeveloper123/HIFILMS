<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Class Subjects';

db_query("CREATE TABLE IF NOT EXISTS class_subjects (
  id INT(11) NOT NULL AUTO_INCREMENT,
  class_id INT(11) NOT NULL,
  section_id INT(11) NOT NULL,
  subject_id INT(11) NOT NULL,
  session VARCHAR(50) NOT NULL DEFAULT '2026-2027',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cs (class_id, section_id, subject_id),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'UpdateClassSubjects') {
        $session    = isset($_POST['session']) ? trim($_POST['session']) : '2026-2027';
        $class_id   = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        $section_id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        $subjects   = isset($_POST['subjects']) && is_array($_POST['subjects']) ? array_map('intval', $_POST['subjects']) : array();

        if ($class_id > 0 && $section_id > 0) {
            $stmt = db_prepare("DELETE FROM class_subjects WHERE class_id = ? AND section_id = ?");
            $stmt->bind_param('ii', $class_id, $section_id);
            $stmt->execute();

            $ins = db_prepare("INSERT INTO class_subjects (class_id, section_id, subject_id, session) VALUES (?, ?, ?, ?)");
            foreach ($subjects as $subject_id) {
                $ins->bind_param('iiis', $class_id, $section_id, $subject_id, $session);
                $ins->execute();
            }
            $msg = 'Subjects updated for the selected section.';
        } else {
            $err = 'Invalid section selected.';
        }
    } elseif ($action === 'BulkUpdateClassSubjects') {
        $session  = isset($_POST['session']) ? trim($_POST['session']) : '2026-2027';
        $sections = isset($_POST['sections']) && is_array($_POST['sections']) ? $_POST['sections'] : array();
        $subjects = isset($_POST['subjects']) && is_array($_POST['subjects']) ? array_map('intval', $_POST['subjects']) : array();

        $pairs = array();
        foreach ($sections as $sec) {
            $parts = explode('-', $sec, 2);
            if (count($parts) === 2 && ctype_digit($parts[0]) && ctype_digit($parts[1])) {
                $pairs[] = array((int) $parts[0], (int) $parts[1]);
            }
        }

        if (count($pairs) === 0) {
            $err = 'Please select at least one section.';
        } elseif (count($subjects) === 0) {
            $err = 'Please select at least one subject.';
        } else {
            $del = db_prepare("DELETE FROM class_subjects WHERE class_id = ? AND section_id = ?");
            $ins = db_prepare("INSERT INTO class_subjects (class_id, section_id, subject_id, session) VALUES (?, ?, ?, ?)");
            foreach ($pairs as $pair) {
                $del->bind_param('ii', $pair[0], $pair[1]);
                $del->execute();
                foreach ($subjects as $subject_id) {
                    $ins->bind_param('iiis', $pair[0], $pair[1], $subject_id, $session);
                    $ins->execute();
                }
            }
            $msg = 'Subjects assigned to ' . count($pairs) . ' selected section(s).';
        }
    }
}

$sessions = array('3' => '2018-2019', '4' => '2019-2020', '5' => '2020-2021', '6' => '2021-2022', '7' => '2022-2023', '8' => '2023-2024', '9' => '2024-2025', '10' => '2025-2026', '11' => '2026-2027', '12' => '2027-2028', '13' => '2028-2029', '14' => '2029-2030', '15' => '2030-2031');
$cur_session = get_setting('session_year', '2026-2027');
$cur_key = array_search($cur_session, $sessions, true);
if ($cur_key === false) {
    $cur_key = '11';
}

$subjects = array();
$res = db_query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_id ASC");
while ($row = $res->fetch_assoc()) {
    $subjects[(int) $row['subject_id']] = $row['subject_name'];
}

$classes = array();
$res = db_query("SELECT c.class_id, c.class_name, s.section_id, s.section_name FROM sections s JOIN classes c ON c.class_id = s.class_id ORDER BY c.class_id ASC, s.section_name ASC");
while ($row = $res->fetch_assoc()) {
    $classes[] = array('class_id' => (int) $row['class_id'], 'class_name' => $row['class_name'], 'section_id' => (int) $row['section_id'], 'section_name' => $row['section_name']);
}

$assigned = array();
$res = db_query("SELECT class_id, section_id, subject_id FROM class_subjects");
while ($row = $res->fetch_assoc()) {
    $assigned[$row['class_id'] . '-' . $row['section_id']][] = (int) $row['subject_id'];
}

$total_classes = count($classes);
$with_classes = count($assigned);
$without_classes = $total_classes - $with_classes;

$palette = array('subj-c0', 'subj-c1', 'subj-c2', 'subj-c3', 'subj-c4', 'subj-c5', 'subj-c6', 'subj-c7');

include __DIR__ . '/includes/header.php';
?>
<style>
    #table_sub_agent { padding-top:22px; }
    #table_sub_agent > .container { padding-bottom:16px; }

    .cs-section-label {
        font-size:11px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase;
        color:#898781; margin:0 0 10px 2px;
    }

    .cs-stats-row { display:flex; gap:16px; flex-wrap:wrap; margin:0 0 28px 0; }
    .cs-stat-tile {
        flex:1 1 220px; display:flex; align-items:center; gap:14px;
        background:#fcfcfb; border:1px solid #e1e0d9; border-radius:10px;
        padding:16px 18px; box-shadow:0 1px 3px rgba(11,11,11,0.07);
    }
    .cs-stat-icon {
        width:44px; height:44px; min-width:44px; border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size:18px;
    }
    .cs-stat-tile.cs-total .cs-stat-icon { background:#2a78d6; }
    .cs-stat-tile.cs-with .cs-stat-icon { background:#0ca30c; }
    .cs-stat-tile.cs-without .cs-stat-icon { background:#d03b3b; }
    .cs-stat-value { font-size:26px; font-weight:700; color:#0b0b0b; line-height:1.1; }
    .cs-stat-label { font-size:12px; color:#52514e; font-weight:600; margin-top:2px; }

    .cs-divider { border:none; border-top:1px solid #e1e0d9; margin:0 0 22px 0; }

    #table_sub_agent h3 { margin-bottom:16px; }

    #table_sub_agent .table > tbody > tr > td,
    #table_sub_agent .table > thead > tr > th { padding:9px 10px; vertical-align:middle; font-size:13px; }
    #table_sub_agent .table { margin-top:4px; }

    .subj-badge {
        display:inline-block; padding:1px 6px; margin:1px 2px 1px 0;
        border-radius:6px; font-size:10px; font-weight:600; color:#333;
        line-height:1.6; border-left:2px solid #ccc; white-space:nowrap;
    }
    .subj-c0 { background:rgba(42,120,214,0.13); border-left-color:#2a78d6; }
    .subj-c1 { background:rgba(235,104,52,0.14); border-left-color:#eb6834; }
    .subj-c2 { background:rgba(27,175,122,0.14); border-left-color:#1baf7a; }
    .subj-c3 { background:rgba(237,161,0,0.16); border-left-color:#eda100; }
    .subj-c4 { background:rgba(232,123,164,0.16); border-left-color:#e87ba4; }
    .subj-c5 { background:rgba(0,131,0,0.13); border-left-color:#008300; }
    .subj-c6 { background:rgba(74,58,167,0.13); border-left-color:#4a3aa7; }
    .subj-c7 { background:rgba(227,73,72,0.14); border-left-color:#e34948; }
    .subj-empty { color:#898781; font-size:12px; font-style:italic; }

    .container1 { display:block; position:relative; padding-left:28px; margin-bottom:8px; cursor:pointer; font-size:13px; -webkit-user-select:none; -moz-user-select:none; -ms-user-select:none; user-select:none; }
    .container1 input { position:absolute; opacity:0; cursor:pointer; }
    .checkmark { position:absolute; top:0; left:0; height:18px; width:18px; background-color:#eee; border:1px solid #ccc; border-radius:4px; }
    .container1:hover input ~ .checkmark { background-color:#ccc; }
    .container1 input:checked ~ .checkmark { background-color:#ff7800; border-color:#ff7800; }
    .checkmark:after { content:""; position:absolute; display:none; }
    .container1 input:checked ~ .checkmark:after { display:block; }
    .container1 .checkmark:after { left:6px; top:2px; width:5px; height:10px; border:solid white; border-width:0 2px 2px 0; -webkit-transform:rotate(45deg); -ms-transform:rotate(45deg); transform:rotate(45deg); }
</style>
<div class="row" style="background-color: white;">
  <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a>
  &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
  <a href="<?php echo BASE_URL; ?>academic_settings.php">Academic Settings</a>
  &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
  Assign Subjects to Classes

  <div class="nav-container">
    <div class="nav-bar">
      <a href="<?php echo BASE_URL; ?>manage_exams.php" class="nav-item ">
        <i class="fa fa fa-plus"></i>Manage Exams
      </a>
      <a href="<?php echo BASE_URL; ?>subjects.php" class="nav-item ">
        <i class="fa fa-book"></i>Manage Subjects
      </a>
      <a href="<?php echo BASE_URL; ?>class_subjects.php" class="nav-item active">
        <i class="fa fa-layer-group"></i> Class Subjects
      </a>
      <a href="<?php echo BASE_URL; ?>teacher_subjects_allocation.php" class="nav-item ">
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

<?php if ($msg !== ''): ?>
<div class="alert alert-success alert-dismissible fade in">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <?php echo e($msg); ?>
</div>
<?php endif; ?>

<?php if ($err !== ''): ?>
<div class="alert alert-danger alert-dismissible fade in">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <?php echo e($err); ?>
</div>
<?php endif; ?>

  <section class="add_sub_agent" id="table_sub_agent">
    <div class="container">
      <div style="float:left;" class="col-md-12">
        <div class="cs-section-label">Overview</div>
        <div class="cs-stats-row">
          <div class="cs-stat-tile cs-total">
            <div class="cs-stat-icon"><i class="fa fa-graduation-cap" aria-hidden="true"></i></div>
            <div>
              <div class="cs-stat-value"><?php echo (int) $total_classes; ?></div>
              <div class="cs-stat-label">Total Classes</div>
            </div>
          </div>
          <div class="cs-stat-tile cs-with">
            <div class="cs-stat-icon"><i class="fa fa-check-circle" aria-hidden="true"></i></div>
            <div>
              <div class="cs-stat-value"><?php echo (int) $with_classes; ?></div>
              <div class="cs-stat-label">Classes with Subjects Assigned</div>
            </div>
          </div>
          <div class="cs-stat-tile cs-without">
            <div class="cs-stat-icon"><i class="fa fa-exclamation-circle" aria-hidden="true"></i></div>
            <div>
              <div class="cs-stat-value"><?php echo (int) $without_classes; ?></div>
              <div class="cs-stat-label">Classes without Subjects Assigned</div>
            </div>
          </div>
        </div>
        <hr class="cs-divider">
        <h3><strong>Subjects Allocation To Each Class:</strong>
          <small>(<?php echo (int) $total_classes; ?> records found)</small>
          <a href="#" data-toggle="modal" data-target="#BulkAssignModal" class="btn btn-primary pull-right" style="margin-top:-8px;">
            <i class="fa fa-clone" aria-hidden="true"></i> Bulk Assign Subjects
          </a>
        </h3>
        <table class="table table-striped table-bordered" style="width:100%">
          <thead>
            <tr>
              <th width="5%" style="text-align:center;">S.No</th>
              <th width="15%">Class</th>
              <th width="38%" style="text-align:center;">Subjects</th>
              <th width="12%" style="text-align:center;">No. of Subjects</th>
              <th width="18%" style="text-align:center;">Manage Subjects</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1; foreach ($classes as $cls): ?>
            <?php $key = $cls['class_id'] . '-' . $cls['section_id']; ?>
            <?php $list = isset($assigned[$key]) ? $assigned[$key] : array(); ?>
            <tr>
              <td style="text-align:center;"><?php echo (int) $i; ?></td>
              <td>
                <?php echo e($cls['class_name'] . ' - ' . $cls['section_name']); ?>
              </td>
              <td style="text-align:center;">
                <?php if (count($list) === 0): ?>
                <span class="subj-empty">No subjects assigned</span>
                <?php else: ?>
                <?php $ci = 0; foreach ($list as $sid): ?>
                <?php if (isset($subjects[$sid])): ?>
                <span class="subj-badge <?php echo $palette[$ci % 8]; ?>"><?php echo e($subjects[$sid]); ?></span>
                <?php endif; ?>
                <?php $ci++; endforeach; ?>
                <?php endif; ?>
              </td>
              <td style="text-align:center;"><?php echo count($list); ?></td>
              <td>
                <a style="padding: 3px 5px; font-size:14px;" data-toggle="modal" data-target="#Modal<?php echo (int) $cls['section_id']; ?>" href="#" style="cursor:pointer;" class="btn btn-success">
                  Manage <i class="fa fa-pencil" aria-hidden="true"></i>
                </a>
                <a style="padding: 3px 5px; font-size:14px;" href="<?php echo BASE_URL; ?>subject_drag.php?section_id=<?php echo (int) $cls['section_id']; ?>&class_id=<?php echo (int) $cls['class_id']; ?>" target="_blank" class="btn btn-success">
                  Subject Order <i class="fa fa-sort" aria-hidden="true"></i>
                </a>
              </td>
            </tr>
            <?php $i++; endforeach; ?>
          </tbody>
        </table>

        <?php foreach ($classes as $cls): ?>
        <?php $key = $cls['class_id'] . '-' . $cls['section_id']; ?>
        <?php $list = isset($assigned[$key]) ? $assigned[$key] : array(); ?>
        <?php $modalName = 'Modal' . $cls['section_id']; ?>
        <div id="<?php echo $modalName; ?>" class="modal fade" role="dialog">
          <div class="modal-dialog">
            <div class="modal-content">
              <form action="<?php echo BASE_URL; ?>class_subjects.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="UpdateClassSubjects">
                <input type="hidden" name="from_timetable" value="0">

                <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                  <h4 class="modal-title">
                    <?php echo e($cls['class_name'] . ' - ' . $cls['section_name']); ?>
                  </h4>
                </div>

                <div class="modal-body">
                  <label for="session">Session</label>
                  <select name="session" class="form-control">
                    <?php foreach ($sessions as $sk => $sv): ?>
                    <option value='<?php echo (int) $sk; ?>' <?php echo $sk == $cur_key ? 'selected' : ''; ?>><?php echo e($sv); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <br><br>

                  <input type="hidden" name="class_id" value="<?php echo (int) $cls['class_id']; ?>" />
                  <input type="hidden" name="section_id" value="<?php echo (int) $cls['section_id']; ?>" />

                  <?php foreach ($subjects as $sid => $sname): ?>
                  <label class='container1'>
                    <input type='checkbox' name='subjects[]' value='<?php echo (int) $sid; ?>' <?php echo in_array($sid, $list) ? 'checked' : ''; ?> />
                    <?php echo e($sname); ?>
                    <span class='checkmark'></span>
                  </label>
                  <?php endforeach; ?>
                </div>

                <div class="modal-footer">
                  <input type="submit" class="btn btn-primary" value="Save Subjects">
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div id="BulkAssignModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form action="<?php echo BASE_URL; ?>class_subjects.php" method="post" enctype="multipart/form-data" id="bulkAssignForm">
          <input type="hidden" name="action" value="BulkUpdateClassSubjects">

          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Bulk Assign Subjects</h4>
            <small style="color:#777;">Select one or more sections and the subjects to assign to all of them at once. This will overwrite the currently assigned subjects for the selected sections.</small>
          </div>

          <div class="modal-body">
            <label for="bulk_session">Session</label>
            <select name="session" id="bulk_session" class="form-control">
              <?php foreach ($sessions as $sk => $sv): ?>
              <option value="<?php echo (int) $sk; ?>" <?php echo $sk == $cur_key ? 'selected' : ''; ?>>
                <?php echo e($sv); ?>
              </option>
              <?php endforeach; ?>
            </select>
            <br>

            <div class="row">
              <div class="col-md-6">
                <label>Sections</label>
                <div style="max-height:300px; overflow-y:auto; border:1px solid #e5e5e5; padding:10px;">
                  <?php
                  $by_class = array();
                  foreach ($classes as $cls) {
                      $by_class[$cls['class_id']]['name'] = $cls['class_name'];
                      $by_class[$cls['class_id']]['sections'][] = $cls;
                  }
                  ?>
                  <?php foreach ($by_class as $class_id => $grp): ?>
                  <div style="margin-bottom:10px;">
                    <label style="font-weight:bold;">
                      <input type="checkbox" class="selectAllClass" data-class="<?php echo (int) $class_id; ?>" onclick="toggleClassSections(this)">
                      <?php echo e($grp['name']); ?>
                    </label>
                    <div style="padding-left:20px;">
                      <?php foreach ($grp['sections'] as $sec): ?>
                      <label style="display:block; font-weight:normal;">
                        <input type="checkbox" name="sections[]" class="sectionChk class_<?php echo (int) $class_id; ?>" value="<?php echo $sec['class_id'] . '-' . $sec['section_id']; ?>">
                        <?php echo e($sec['class_name'] . ' - ' . $sec['section_name']); ?>
                      </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="col-md-6">
                <label>Subjects</label>
                <div style="max-height:300px; overflow-y:auto; border:1px solid #e5e5e5; padding:10px;">
                  <?php foreach ($subjects as $sid => $sname): ?>
                  <label class='container1'>
                    <input type='checkbox' name='subjects[]' value="<?php echo (int) $sid; ?>" />
                    <?php echo e($sname); ?>
                    <span class='checkmark'></span>
                  </label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <input type="submit" class="btn btn-primary" value="Apply To Selected Sections" onclick="return confirmBulkAssign();">
          </div>
        </form>
      </div>
    </div>
  </div>

</div>

<script>
  function toggleClassSections(el) {
    var cls = el.getAttribute('data-class');
    var checkboxes = document.querySelectorAll('.class_' + cls);
    checkboxes.forEach(function (chk) {
      chk.checked = el.checked;
    });
  }

  function confirmBulkAssign() {
    var sectionsChecked = document.querySelectorAll('#bulkAssignForm input[name="sections[]"]:checked').length;
    var subjectsChecked = document.querySelectorAll('#bulkAssignForm input[name="subjects[]"]:checked').length;
    if (sectionsChecked === 0) {
      alert('Please select at least one section.');
      return false;
    }
    if (subjectsChecked === 0) {
      alert('Please select at least one subject.');
      return false;
    }
    return confirm('This will overwrite the currently assigned subjects for ' + sectionsChecked + ' selected section(s). Continue?');
  }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>