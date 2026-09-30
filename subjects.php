<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Manage Subjects';

db_query("CREATE TABLE IF NOT EXISTS subjects (
  subject_id INT(11) NOT NULL AUTO_INCREMENT,
  subject_name VARCHAR(191) NOT NULL,
  subject_code VARCHAR(50) DEFAULT NULL,
  class_id INT(11) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = '';
$err = '';
$edit_id = 0;
$edit_sub = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $sub    = isset($_POST['sub']) ? trim($_POST['sub']) : '';
    $subId  = isset($_POST['subject']) ? (int) $_POST['subject'] : 0;

    if ($sub === '') {
        $err = 'Subject name is required.';
    } elseif ($action === 'AddSubject' && $subId > 0) {
        $stmt = db_prepare("UPDATE subjects SET subject_name = ? WHERE subject_id = ?");
        $stmt->bind_param('si', $sub, $subId);
        $stmt->execute();
        $msg = 'Subject updated successfully.';
    } elseif ($action === 'AddSubject') {
        $stmt = db_prepare("INSERT INTO subjects (subject_name) VALUES (?)");
        $stmt->bind_param('s', $sub);
        $stmt->execute();
        $msg = 'Subject added successfully.';
    } else {
        $err = 'Invalid action.';
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'DeleteSubject' && isset($_GET['subject'])) {
    $subId = (int) $_GET['subject'];
    $stmt = db_prepare("DELETE FROM subjects WHERE subject_id = ?");
    $stmt->bind_param('i', $subId);
    $stmt->execute();
    $stmt = db_prepare("DELETE FROM class_subjects WHERE subject_id = ?");
    $stmt->bind_param('i', $subId);
    $stmt->execute();
    $msg = 'Subject deleted successfully.';
}

if (isset($_GET['subject'])) {
    $edit_id = (int) $_GET['subject'];
    $stmt = db_prepare("SELECT subject_id, subject_name FROM subjects WHERE subject_id = ? LIMIT 1");
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $edit_id = (int) $row['subject_id'];
        $edit_sub = $row['subject_name'];
    }
}

$subjects = db_query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_id ASC");
$subject_count = $subjects->num_rows;

include __DIR__ . '/includes/header.php';
?>
<style>
.panel-white { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:18px; }
.crumb { font-size:13px; color:#6B7280; margin:6px 4px 14px; }
.crumb a { color:#e67e22; text-decoration:none; }
.crumb a:hover { text-decoration:underline; }
.sub-list-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:14px; }
.sub-list-head h3 { font-size:17px; font-weight:800; color:#111827; margin:0; }
.sub-list-head h3 small { font-weight:500; color:#6B7280; font-size:13px; }
.sub-count { background:#ff9800; color:#111; font-weight:700; border-radius:20px; padding:5px 14px; font-size:13px; }
.sub-table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
table.sub-table { width:100%; min-width:420px; margin:0; }
table.sub-table th { background:#2b2b36; color:#fff; text-align:center; padding:11px; border:none; font-weight:600; font-size:13px; white-space:nowrap; }
table.sub-table td { padding:10px; vertical-align:middle; }
table.sub-table td.sub-name { font-weight:600; color:#111827; }
table.sub-table tbody tr:hover { background-color:#f8f9fa; }
.sub-actions { white-space:nowrap; text-align:center; }
.sub-form-card h4 { font-size:15px; font-weight:800; color:#111827; margin:0 0 4px; }
.sub-form-card p { font-size:12.5px; color:#6B7280; margin:0 0 16px; }
.sub-form-card label { display:block; font-weight:600; font-size:13px; margin:0 0 6px; }
.sub-save { width:100%; font-weight:600; }
.sub-editing-note { display:none; background:#fff7f0; border:1px solid #ffd8b3; color:#e67e22; border-radius:8px; padding:9px 12px; font-size:12.5px; margin-bottom:14px; }
.sub-editing-note.show { display:block; }
@media (max-width: 767px) {
  .panel-white { padding:14px; }
  .sub-list-head h3 { font-size:15px; }
}
</style>

<div class="main-content">
  <div class="container-fluid">
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

    <div class="crumb"><a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; <a href="<?php echo BASE_URL; ?>academic_setup.php">Academic Setup</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; Manage Subjects</div>

    <?php $academic_tabs_container_style = 'margin-top:0;'; include __DIR__ . '/includes/academic_tabs.php'; ?>

    <div class="row" style="margin-top:16px;">
      <div class="col-md-7 col-sm-12">
        <div class="panel-white">
          <div class="sub-list-head">
            <h3><i class="fa fa-book"></i> List View Subjects <small>(<?php echo (int) $subject_count; ?> Record Founds)</small></h3>
            <span class="sub-count"><?php echo (int) $subject_count; ?> total</span>
          </div>
          <div class="sub-table-wrap">
            <table class="table table-striped table-bordered sub-table">
              <thead>
                <tr>
                  <th style="width:70px; text-align:center;">S.No</th>
                  <th>Subject</th>
                  <th style="width:110px; text-align:center;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ((int) $subject_count === 0): ?>
                <tr><td colspan="3" style="text-align:center; color:#6B7280; padding:30px;">No subjects yet. Add one using the form on the right.</td></tr>
                <?php endif; ?>
                <?php $i = 1; while ($row = $subjects->fetch_assoc()): ?>
                <tr>
                  <td style="text-align:center;"><?php echo (int) $i; ?></td>
                  <td class="sub-name"><?php echo e($row['subject_name']); ?></td>
                  <td class="sub-actions">
                    <a href="<?php echo BASE_URL; ?>subjects.php?subject=<?php echo (int) $row['subject_id']; ?>" style="padding:0 5px;" class="btn btn-success" title="Edit Subject">
                      <i class="fa fa-pencil" aria-hidden="true"></i>
                    </a>
                    <a href="<?php echo BASE_URL; ?>subjects.php?subject=<?php echo (int) $row['subject_id']; ?>&action=DeleteSubject" style="padding:0 5px;" onClick="return confirm('Are you sure you want to delete this record');" class="btn btn-danger" title="Delete Subject"><i class="fa fa-remove"></i></a>
                  </td>
                </tr>
                <?php $i++; endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-md-5 col-sm-12">
        <div class="panel-white sub-form-card">
          <div class="sub-editing-note<?php echo $edit_id > 0 ? ' show' : ''; ?>">
            <i class="fa fa-pencil"></i> Editing subject &mdash; save to apply, or
            <a href="<?php echo BASE_URL; ?>subjects.php">cancel</a>.
          </div>
          <h4><?php echo $edit_id > 0 ? 'Update Subject' : 'Add New Subject'; ?></h4>
          <p><?php echo $edit_id > 0 ? 'Change the name below and save the record.' : 'Create a new subject offered by the school.'; ?></p>

          <form action="<?php echo BASE_URL; ?>subjects.php" method="post" autocomplete="off">
            <input type="hidden" name="action" value="AddSubject">
            <?php if ($edit_id > 0): ?>
            <input type="hidden" name="subject" value="<?php echo (int) $edit_id; ?>">
            <?php endif; ?>
            <div class="form-group" style="margin-bottom:12px;">
              <label for="sub_name">Subject Name</label>
              <input type="text" id="sub_name" name="sub" class="form-control" placeholder="Enter Subject Name..." maxlength="100" required value="<?php echo e($edit_sub); ?>">
            </div>
            <button type="submit" class="btn btn-primary sub-save"><i class="fa fa-save"></i> <?php echo $edit_id > 0 ? 'Update Subject' : 'Save Subject'; ?></button>
            <?php if ($edit_id > 0): ?>
            <a href="<?php echo BASE_URL; ?>subjects.php" class="btn btn-default sub-save" style="margin-top:10px;">Cancel</a>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  setTimeout(function() { $('.alert').fadeOut('slow'); }, 5000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>