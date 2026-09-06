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
<div class="row" style="background-color: white;">
  <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i> &nbsp;
  <a href="<?php echo BASE_URL; ?>academic_settings.php">Academic Settings </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i> &nbsp;
  Manage Subjects

  <div class="nav-container">
    <div class="nav-bar">
      <a href="<?php echo BASE_URL; ?>manage_exams.php" class="nav-item ">
        <i class="fa fa fa-plus"></i>Manage Exams
      </a>
      <a href="<?php echo BASE_URL; ?>subjects.php" class="nav-item active">
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
      <div class="" style="margin-top:10px;">
        <div class="">
          <h3 style="float: left;">List View Subjects <small>(<?php echo $subject_count; ?> Record Founds)</small> </h3>

          <div class="clearfix"></div>
        </div>
        <div class="panel-body">
          <div class="clearfix"></div>
        </div>
        <div class="clearfix"></div>
      </div>
      <br>
    </div>

    <div style="float:left;" class="col-md-6">
      <table class="table table-striped table-bordered" style="width:100%">
        <thead>
          <tr>
            <th width="15%" style="text-align:center;">S.No</th>
            <th width="60%">Subject</th>
            <th width="25%"> Action </th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; while ($row = $subjects->fetch_assoc()): ?>
          <tr>
            <td style="text-align:center;">
              <?php echo (int) $i; ?>
            </td>
            <td> <?php echo e($row['subject_name']); ?> </td>
            <td>
              <a style="padding: 0px 5px; font-size:14px;" href="<?php echo BASE_URL; ?>subjects.php?subject=<?php echo (int) $row['subject_id']; ?>" style="cursor:pointer;" class="btn btn-success">
                <i class="fa fa-pencil" aria-hidden="true"></i>
              </a>
              <a href="<?php echo BASE_URL; ?>subjects.php?subject=<?php echo (int) $row['subject_id']; ?>&action=DeleteSubject" style="padding: 0px 5px; font-size:14px;" onClick="return confirm('Are you sure you want to delete this record');" class="btn btn-danger"><i class="fa fa-remove"></i> </a>
            </td>
          </tr>
          <?php $i++; endwhile; ?>
        </tbody>
      </table>
    </div>

    <div style="float:left;" class="col-md-6">
      <table style="width:100%">
        <tr>
          <td width="100%" style="padding-left:2%; padding-right:2%;">

            <form class="form-style-7" action="<?php echo BASE_URL; ?>subjects.php" method="post" enctype="multipart/form-data">
              <input type="hidden" name="action" value="AddSubject">
              <?php if ($edit_id > 0): ?>
              <input type="hidden" name="subject" value="<?php echo (int) $edit_id; ?>">
              <?php endif; ?>
              <div class="form-group col-xs-12">
                <label for=""> Add New Subject </label>
                <input type="text" name="sub" placeholder="Enter Subject Name..." autocomplete="off" required maxlength="100" style="width: 100%;height: 34px;" autofocus="autofocus" required value="<?php echo e($edit_sub); ?>">
                <br><br>
                <div class="clearfix"></div>
                <input type="submit" class="btn btn-primary" value="Save Subject">
              </div>
            </form>

          </td>
        </tr>
      </table>
    </div>

    <br><br>
  </section>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>