<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Add School';

db_query("CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(191) NOT NULL,
  setting_value LONGTEXT,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'add_school') {
        $school_name    = isset($_POST['school_name']) ? trim($_POST['school_name']) : '';
        $school_contact = isset($_POST['school_contact']) ? trim($_POST['school_contact']) : '';
        $school_address = isset($_POST['school_address']) ? trim($_POST['school_address']) : '';

        if ($school_name === '' || $school_contact === '') {
            $err = 'Please fill in School Name and School Contact.';
        } else {
            $stmt = db_prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
            $stmt->bind_param('ss', $k, $v);
            $k = 'school_name';    $v = $school_name;    $stmt->execute();
            $k = 'school_contact'; $v = $school_contact; $stmt->execute();
            $k = 'school_address'; $v = $school_address; $stmt->execute();

            $msg = 'School info saved successfully.';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'DeleteSchool') {
    $stmt = db_prepare("DELETE FROM settings WHERE setting_key IN ('school_name', 'school_contact', 'school_address')");
    $stmt->execute();
    $msg = 'School record deleted successfully.';
}

$schlup      = isset($_GET['schlup']) ? (int) $_GET['schlup'] : 0;
$school_name    = get_setting('school_name', '');
$school_contact = get_setting('school_contact', '');
$school_address = get_setting('school_address', '');
$has_school = ($school_name !== '');

include __DIR__ . '/includes/header.php';
?>

<div class="page-title" style="margin-top:15px;">
    <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i> &nbsp;
    <a href="<?php echo BASE_URL; ?>student_inquiry.php">View Student Inquiry List </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i> &nbsp;
    Add School
    <br><br>
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

<div class="row" style="background-color: white;">
  <section class="add_sub_agent" id="table_sub_agent">
    <div class="container">

      <div class="" style="margin-top:10px;">

        <div class="">
          <div class="page-title">
            <div class="title_left"></div>
          </div>
          <div class="clearfix"></div>
          <h3 style="margin-left: 15px;">Add New School</h3>
          <div class="row">

            <div class="col-md-12 col-sm-12 col-xs-12">
              <div class="x_panel">
                <div class="x_content">

                  <form action="<?php echo BASE_URL; ?>add_school.php" method="post" enctype="multipart/form-data" class="form-horizontal form-label-left">
                    <input type="hidden" name="action" value="add_school" />

                    <div class="item form-group">
                      <label class="control-label col-md-3 col-sm-3 col-xs-12" for="school_name"> School Name <span class="required">*</span>
                      </label>
                      <div class="col-md-6 col-sm-6 col-xs-12">
                        <input class="form-control col-md-7 col-xs-12" name="school_name" placeholder="eg Shaheen Public School " value="<?php echo e($school_name); ?>">
                      </div>
                    </div>

                    <div class="item form-group">
                      <label class="control-label col-md-3 col-sm-3 col-xs-12" for="school_contact"> School Conctact <span class="required">*</span>
                      </label>
                      <div class="col-md-6 col-sm-6 col-xs-12">
                        <input class="form-control col-md-7 col-xs-12" name="school_contact" placeholder="eg 03111111111 " value="<?php echo e($school_contact); ?>">
                      </div>
                    </div>

                    <div class="item form-group">
                      <label class="control-label col-md-3 col-sm-3 col-xs-12" for="school_address">School Addres <span class="required"></span>
                      </label>
                      <div class="col-md-6 col-sm-6 col-xs-12">
                        <input class="form-control col-md-7 col-xs-12" name="school_address" placeholder="eg Railway Road Shakargarh " value="<?php echo e($school_address); ?>">
                      </div>
                    </div>

                    <div class="ln_solid" style="border-top:none;"></div>
                    <div class="form-group">
                      <div class="col-md-6 col-md-offset-3">
                        <button id="send" type="submit" class="btn btn-primary" >Submit</button>
                        <a href="<?php echo BASE_URL; ?>add_school.php" class="btn btn-primary">Cancel</a>
                      </div>
                    </div>

                  </form>

                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
      <div class="clearfix"></div>
    </section>

    <div class="t col-md-12 col-sm-12 col-xs-12">
      <h3>School List<small> (<?php echo $has_school ? 1 : 0; ?> records)</small></h3>
    </div>

    <table id="datatable" class="table table-striped table-bordered" style="background-color: white;">
      <thead>
        <tr>
          <th width="1%">S.No</th>
          <th width="10%">School Name</th>
          <th width="10%">School Contact</th>
          <th width="20%">School Address</th>
          <th width="8%">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($has_school): ?>
        <tr>
          <td style="text-align:center;">1</td>
          <td><?php echo e($school_name); ?></td>
          <td><?php echo e($school_contact); ?></td>
          <td><?php echo e($school_address); ?></td>
          <td>
            <a style="padding: 0px 5px; font-size:14px;" href="<?php echo BASE_URL; ?>add_school.php?schlup=1" style="cursor:pointer;" class="btn btn-success"> <i class="fa fa-pencil" aria-hidden="true"></i></a>
            <a href="<?php echo BASE_URL; ?>add_school.php?schlup=1&action=DeleteSchool" style="padding: 0px 5px; font-size:14px;" onClick="return confirm('Are you sure you want to delete this record');" class="btn btn-danger"><i class="fa fa-remove"></i> </a>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>