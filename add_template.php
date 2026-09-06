<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Create Message Template';

db_query("CREATE TABLE IF NOT EXISTS report_templates (
  template_id INT(11) NOT NULL AUTO_INCREMENT,
  name VARCHAR(191) NOT NULL,
  header_text TEXT,
  footer_text TEXT,
  options TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $title  = isset($_POST['title']) ? trim($_POST['title']) : '';
    $body   = isset($_POST['text_fld']) ? trim($_POST['text_fld']) : '';
    $footer = isset($_POST['footer_fld']) ? trim($_POST['footer_fld']) : '';
    $opts   = isset($_POST['options']) && is_array($_POST['options']) ? $_POST['options'] : array();
    $options_saved = implode(',', array_map('trim', $opts));
    $tid    = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;

    if ($title === '' || $body === '') {
        $err = 'Title and Template are required.';
    } elseif ($action === 'AddTemplate') {
        $stmt = db_prepare("INSERT INTO report_templates (name, header_text, footer_text, options) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $title, $body, $footer, $options_saved);
        $stmt->execute();
        $msg = 'Template saved successfully.';
    } elseif ($action === 'UpdateTemplate' && $tid > 0) {
        $stmt = db_prepare("UPDATE report_templates SET name = ?, header_text = ?, footer_text = ?, options = ? WHERE template_id = ?");
        $stmt->bind_param('ssssi', $title, $body, $footer, $options_saved, $tid);
        $stmt->execute();
        $msg = 'Template updated successfully.';
    } else {
        $err = 'Invalid action.';
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'DeleteTemplate' && isset($_GET['tid'])) {
    $tid = (int) $_GET['tid'];
    $stmt = db_prepare("DELETE FROM report_templates WHERE template_id = ?");
    $stmt->bind_param('i', $tid);
    $stmt->execute();
    $msg = 'Template deleted successfully.';
}

$edit_id = 0;
$edit_title = '';
$edit_body = '';
$edit_footer = '';
$edit_options = array();

if (isset($_GET['tid'])) {
    $edit_id = (int) $_GET['tid'];
    $stmt = db_prepare("SELECT template_id, name, header_text, footer_text, options FROM report_templates WHERE template_id = ? LIMIT 1");
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $edit_id = (int) $row['template_id'];
        $edit_title = $row['name'];
        $edit_body = $row['header_text'];
        $edit_footer = $row['footer_text'];
        $edit_options = array_filter(array_map('trim', explode(',', (string) $row['options'])));
    }
}

$templates = db_query("SELECT template_id, name, header_text, created_at FROM report_templates ORDER BY template_id DESC");

$field_options = array(
    'marks' => 'Marks',
    'grade' => 'Grade',
    'remarks' => 'Remarks',
    'teacher_remarks' => "Teacher's Remarks",
    'attendance' => 'Attendance',
);

include __DIR__ . '/includes/header.php';
?>
<h3>Create Message Template</h3>

<div class="clearfix"></div>

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

<div class="row">
  <div class="col-md-12 col-sm-12 col-xs-12">
    <div class="x_panel">

      <div class="x_content">

        <form class="form-style-7" id="add_student" action="<?php echo BASE_URL; ?>add_template.php" method="post" onSubmit="return student_check();" enctype="multipart/form-data">
          <input type="hidden" name="action" value="<?php echo $edit_id > 0 ? 'UpdateTemplate' : 'AddTemplate'; ?>">
          <?php if ($edit_id > 0): ?>
          <input type="hidden" name="template_id" value="<?php echo (int) $edit_id; ?>">
          <?php endif; ?>

          <div class="col-md-2"></div>

          <div class="col-md-8">

            <div class="row">

              <div class="col-md-12">
                <div class="form-group">
                  <label> Title : * </label>
                  <input type="text" name="title" class="form-control" required value="<?php echo e($edit_title); ?>" />
                </div>
              </div>

              <div class="col-md-12">
                <div class="form-group">
                  <label> Template : * </label>
                  <textarea maxlength="500" class="form-control message1 form-textarea" required="" rows="7" id="text_fld" name="text_fld" onkeyup="update_counter('text_counter', 'text_fld', 160)" style="line-height: 1.5em; font-family: Arial, Helvetica, sans-serif; font-size: 14px;"><?php echo e($edit_body); ?></textarea>
                </div>
              </div>

              <div class="col-md-12">
                <div class="form-group">
                  <label> Footer Text </label>
                  <textarea maxlength="500" class="form-control" rows="3" name="footer_fld" style="line-height: 1.5em; font-family: Arial, Helvetica, sans-serif; font-size: 14px;"><?php echo e($edit_footer); ?></textarea>
                </div>
              </div>

              <div class="col-md-12">
                <div class="form-group">
                  <label> Show on Report Card </label>
                  <br>
                  <?php foreach ($field_options as $fkey => $flabel): ?>
                  <label class="checkbox-inline">
                    <input type="checkbox" name="options[]" value="<?php echo e($fkey); ?>" <?php echo in_array($fkey, $edit_options, true) ? 'checked' : ''; ?>> <?php echo e($flabel); ?>
                  </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="col-md-12">
                <div class="msg-count">
                  <div class="col-md-6 col-sm-6">
                    <strong>Message Count :<span id="char">1</span></strong>
                  </div>
                  <div class="col-md-6 col-sm-6" style="text-align:right;">
                    <strong class="text-right" > <span id="text_counter">160</span> Characters Left <br /></strong>
                  </div>
                </div>
              </div>

              <div class="col-md-12">
                <br> <br>
                <input type="submit" value=" Save Record " class="pull-right btn btn-primary">
              </div>

            </div>

          </div>

          <div class="col-md-2"></div>
        </form>

        <div class="clearfix"></div>

      </div>

    </div>
  </div>
</div>

<?php if ($templates->num_rows > 0): ?>
<div class="row" style="margin-top:20px;">
  <div class="col-md-12 col-sm-12 col-xs-12">
    <div class="x_panel">
      <div class="x_title">
        <h2>Saved Templates <small>(<?php echo (int) $templates->num_rows; ?> records)</small></h2>
        <div class="clearfix"></div>
      </div>
      <div class="x_content">
        <table class="table table-striped table-bordered">
          <thead>
            <tr>
              <th width="5%">S.No</th>
              <th width="30%">Title</th>
              <th width="45%">Template</th>
              <th width="20%">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1; $templates->data_seek(0); while ($row = $templates->fetch_assoc()): ?>
            <tr>
              <td style="text-align:center;"><?php echo (int) $i; ?></td>
              <td><?php echo e($row['name']); ?></td>
              <td><?php echo e(mb_substr($row['header_text'], 0, 60)); ?><?php echo mb_strlen($row['header_text']) > 60 ? '...' : ''; ?></td>
              <td>
                <a style="padding: 0px 5px; font-size:14px;" href="<?php echo BASE_URL; ?>add_template.php?tid=<?php echo (int) $row['template_id']; ?>" class="btn btn-success">
                  <i class="fa fa-pencil" aria-hidden="true"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>add_template.php?tid=<?php echo (int) $row['template_id']; ?>&action=DeleteTemplate" style="padding: 0px 5px; font-size:14px;" onClick="return confirm('Are you sure you want to delete this record');" class="btn btn-danger">
                  <i class="fa fa-remove"></i>
                </a>
              </td>
            </tr>
            <?php $i++; endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
  function student_check() {
    var body = document.getElementById('text_fld');
    if (body && body.value.trim() === '') {
      alert('Template cannot be empty.');
      return false;
    }
    return true;
  }

  function update_counter(counter_id, field_id, max) {
    var el = document.getElementById(field_id);
    var c = document.getElementById(counter_id);
    var remaining = max - el.value.length;
    if (remaining < 0) {
      remaining = 0;
      el.value = el.value.substring(0, max);
    }
    c.innerHTML = remaining;
    var cl = document.getElementById('char');
    if (cl) {
      cl.innerHTML = Math.max(1, Math.ceil((el.value.length || 1) / 160));
    }
  }
</script>