<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin']);
$page_title = 'Manage Classes';

db_query("ALTER TABLE classes ADD COLUMN IF NOT EXISTS monthly_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00");
db_query("ALTER TABLE classes ADD COLUMN IF NOT EXISTS misc_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00");
db_query("ALTER TABLE classes ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT NULL");
db_query("ALTER TABLE classes ADD COLUMN IF NOT EXISTS class_head_id INT(11) DEFAULT NULL");

db_query("CREATE TABLE IF NOT EXISTS class_heads (
  class_head_id INT(11) NOT NULL AUTO_INCREMENT,
  class_head_name VARCHAR(191) NOT NULL,
  status TINYINT(4) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (class_head_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$heads = db_query("SELECT COUNT(*) c FROM class_heads")->fetch_assoc()['c'];
if ((int) $heads === 0) {
    $seed = array('Hajvery Campus', 'Main Campus', 'Pharm-D', 'Pharm-D 2022-27', 'Pharm-D 2023-28', 'Pharm-D 2024-29', 'Modern Edu');
    $ins = db_prepare("INSERT INTO class_heads (class_head_name) VALUES (?)");
    foreach ($seed as $h) {
        $ins->bind_param('s', $h);
        $ins->execute();
    }
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $name   = isset($_POST['name']) ? trim($_POST['name']) : '';
    $head   = isset($_POST['class_head']) && $_POST['class_head'] !== '' ? (int) $_POST['class_head'] : 0;
    $fee    = isset($_POST['fee']) && is_numeric($_POST['fee']) ? (float) $_POST['fee'] : 0;
    $misc   = isset($_POST['misc']) && is_numeric($_POST['misc']) ? (float) $_POST['misc'] : 0;
    $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;

    if ($name === '') {
        $err = 'Class Name is required.';
    } elseif ($action === 'AddClass') {
        $stmt = db_prepare("INSERT INTO classes (class_name, class_head_id, monthly_fee, misc_fee, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
        $stmt->bind_param('sidd', $name, $head, $fee, $misc);
        $stmt->execute();
        $msg = 'Class added successfully.';
    } elseif ($action === 'UpdateClass' && $class_id > 0) {
        $stmt = db_prepare("UPDATE classes SET class_name = ?, class_head_id = ?, monthly_fee = ?, misc_fee = ? WHERE class_id = ?");
        $stmt->bind_param('siddi', $name, $head, $fee, $misc, $class_id);
        $stmt->execute();
        $msg = 'Class updated successfully.';
    } else {
        $err = 'Invalid action.';
    }
}

if (isset($_GET['cid']) && isset($_GET['delete_class']) && $_GET['delete_class'] == '1') {
    $class_id = (int) $_GET['cid'];
    $stmt = db_prepare("DELETE FROM class_subjects WHERE class_id = ?");
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $stmt = db_prepare("DELETE FROM sections WHERE class_id = ?");
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $stmt = db_prepare("DELETE FROM classes WHERE class_id = ?");
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $msg = 'Class deleted successfully.';
}

if (isset($_GET['toggle_status']) && isset($_GET['class_id'])) {
    $class_id = (int) $_GET['class_id'];
    $newStatus = (string) $_GET['toggle_status'];
    $valid = array('active', 'inactive');
    if (in_array($newStatus, $valid, true)) {
        $stmt = db_prepare("UPDATE classes SET status = ? WHERE class_id = ?");
        $stmt->bind_param('si', $newStatus, $class_id);
        $stmt->execute();
        $msg = 'Class status updated successfully.';
    }
}

$edit_id = 0;
$edit_name = '';
$edit_head = 0;
$edit_fee = 0;
$edit_misc = 0;
$is_edit = false;

if (isset($_GET['id'])) {
    $edit_id = (int) $_GET['id'];
    $stmt = db_prepare("SELECT class_id, class_name, class_head_id, monthly_fee, misc_fee FROM classes WHERE class_id = ? LIMIT 1");
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $is_edit = true;
        $edit_name = $row['class_name'];
        $edit_head = (int) $row['class_head_id'];
        $edit_fee = (float) $row['monthly_fee'];
        $edit_misc = (float) $row['misc_fee'];
    }
}

$classes = db_query("SELECT c.*, (SELECT COUNT(*) FROM sections WHERE sections.class_id = c.class_id) AS sec_count FROM classes c ORDER BY c.class_id ASC");
$total_classes = $classes->num_rows;

$heads_res = db_query("SELECT class_head_id, class_head_name FROM class_heads ORDER BY class_head_name ASC");
$head_options = array();
while ($row = $heads_res->fetch_assoc()) {
    $head_options[(int) $row['class_head_id']] = $row['class_head_name'];
}

$head_names = array();
foreach ($head_options as $hid => $hname) {
    $head_names[$hid] = $hname;
}

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
    .action-buttons { margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; }
    .action-buttons .btn-add-class { margin-left: auto; background: #ff9800; border-color: #ff9800; color: #111; }
    .action-buttons .btn-add-class:hover { background: #e68900; border-color: #e68900; }
    .action-buttons .btn { padding: 10px 20px; font-weight: 600; border-radius: 6px; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .action-buttons .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
    .action-buttons .btn i { margin-right: 8px; }
    .stats-card { background: white; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #ff9800; }
    .stats-card .badge { font-size: 18px; padding: 8px 15px; background: #ff9800; color: #111; border-radius: 20px; }
    .table-container { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
    .table thead th { background: #2b2b36; color: white; font-weight: 600; text-align: center; padding: 12px; border: none; }
    .table tbody td { padding: 12px; vertical-align: middle; text-align: center; }
    .table tbody tr { transition: all 0.2s ease; }
    .table tbody tr:hover { background-color: #f8f9fa; transform: scale(1.01); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .btn-action { padding: 6px 12px; margin: 0 3px; font-size: 13px; border-radius: 4px; transition: all 0.2s ease; }
    .btn-action:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    .empty-state { text-align: center; padding: 40px 20px; color: #6c757d; }
    .empty-state i { font-size: 64px; color: #dee2e6; margin-bottom: 15px; }
    .empty-state p { font-size: 16px; margin: 0; }
    .fee-amount { font-weight: 600; color: #28a745; }
    .section-link { color: #ff9800; text-decoration: none; font-weight: 500; transition: color 0.2s ease; }
    .section-link:hover { color: #e68900; text-decoration: underline; }
    .loading-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; }
    .loading-overlay.active { display: flex; }
    .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    @media (max-width: 768px) {
        .action-buttons { flex-direction: column; }
        .action-buttons .btn { width: 100%; }
    }
    .inputfield { width: 100%; height: 34px; }
    .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .status-active { background: #d4edda; color: #155724; }
    .status-inactive { background: #f8d7da; color: #721c24; }
</style>

<div class="page-header" style="margin-top: 0px;">
  <nav aria-label="breadcrumb" class="breadcrumb">
    <a href="<?php echo BASE_URL; ?>dashboard.php">
      <i class="fa fa-home"></i> Dashboard
    </a>
    <span> <i class="fa fa-angle-double-right"></i> </span>
    <a href="<?php echo BASE_URL; ?>academic_setup.php">
      <i class="fa fa-settings"></i> Academic Setup
    </a>
    <span> <i class="fa fa-angle-double-right"></i> </span>
    <span>Manage Classes</span>
  </nav>
  <h2><i class="fa fa-graduation-cap"></i> Class Management</h2>
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

<div class="stats-card">
  <strong><i class="fa fa-info-circle"></i> Total Classes:</strong>
  <span class="badge"><?php echo (int) $total_classes; ?></span>
</div>

<div class="action-buttons">
  <a href="<?php echo BASE_URL; ?>manage_sections.php?page=" class="btn btn-primary">
    <i class="fa fa-list"></i> Manage Sections
  </a>
  <a href="<?php echo BASE_URL; ?>class_drag.php" class="btn btn-info">
    <i class="fa fa-sort"></i> Class Order / Sorting
  </a>
  <a href="<?php echo BASE_URL; ?>manage_class_heads.php" class="btn btn-dark">
    <i class="fa fa-sitemap"></i> Class Heads
  </a>
  <button type="button" class="btn btn-primary btn-add-class" data-toggle="modal" data-target="#addClassModal">
    <i class="fa fa-plus"></i> Add New Class
  </button>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table table-bordered table-hover">
      <thead>
        <tr>
          <th><i class="fa fa-book"></i> Class Name</th>
          <th><i class="fa fa-sitemap"></i> Class Head</th>
          <th><i class="fa fa-list-alt"></i> Sections</th>
          <th><i class="fa fa-money-bill-wave"></i> Monthly Fee</th>
          <th><i class="fa fa-coins"></i> Misc Fee</th>
          <th><i class="fa fa-calendar"></i> Created Date</th>
          <th><i class="fa fa-cog"></i> Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($cls = $classes->fetch_assoc()): ?>
        <tr>
          <td style="font-weight: 600;"><?php echo e($cls['class_name']); ?></td>
          <td>
            <?php
            $headLabel = 'N/A';
            if (!empty($cls['class_head_id']) && isset($head_names[(int) $cls['class_head_id']])) {
                $headLabel = $head_names[(int) $cls['class_head_id']];
            }
            echo e($headLabel);
            ?>
            <?php if ($cls['status'] === 'active'): ?>
            <br><span class="status-badge status-active"><i class="fa fa-check-circle"></i> Active</span>
            <?php else: ?>
            <br><span class="status-badge status-inactive"><i class="fa fa-ban"></i> Inactive</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="<?php echo BASE_URL; ?>manage_sections.php?class_id=<?php echo (int) $cls['class_id']; ?>&class_head=<?php echo e($headLabel); ?>" class="section-link">
              <i class="fa fa-eye"></i> View Sections (<?php echo (int) $cls['sec_count']; ?>)
            </a>
          </td>
          <td><span class="fee-amount"><?php echo number_format((float) $cls['monthly_fee']); ?></span></td>
          <td><span class="fee-amount"><?php echo number_format((float) $cls['misc_fee']); ?></span></td>
          <td><?php echo $cls['created_at'] ? date('d-M-Y', strtotime($cls['created_at'])) : 'N/A'; ?></td>
          <td>
            <a href="<?php echo BASE_URL; ?>manage_classes.php?id=<?php echo (int) $cls['class_id']; ?>&fee=<?php echo (float) $cls['monthly_fee']; ?>" class="btn btn-success btn-action" title="Edit Class">
              <i class="fa fa-edit"></i> Edit
            </a>
            <?php $toggleTarget = $cls['status'] === 'active' ? 'inactive' : 'active'; ?>
            <a href="<?php echo BASE_URL; ?>manage_classes.php?class_id=<?php echo (int) $cls['class_id']; ?>&toggle_status=<?php echo $toggleTarget; ?>" class="btn btn-warning btn-action" title="Toggle Status">
              <i class="fa fa-toggle-on"></i> Status
            </a>
            <a onclick="return confirm('Are you sure you want to delete this class? This will also expel all students in this class!');" href="<?php echo BASE_URL; ?>manage_classes.php?cid=<?php echo (int) $cls['class_id']; ?>&delete_class=1" class="btn btn-danger btn-action" title="Delete Class">
              <i class="fa fa-trash"></i> Delete
            </a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="clearfix"></div>

<div id="addClassModal" class="modal fade" role="dialog" aria-labelledby="addClassModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background: #2b2b36; color: white;">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white; opacity: 1;">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title" id="addClassModalLabel">
          <?php echo $is_edit ? '<i class="fa fa-edit"></i> Update Class' : '<i class="fa fa-plus"></i> Add New Class'; ?>
        </h4>
      </div>
      <div class="modal-body">

        <form class="form-style-7" action="<?php echo BASE_URL; ?>manage_classes.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="<?php echo $is_edit ? 'UpdateClass' : 'AddClass'; ?>">
          <?php if ($is_edit): ?>
          <input type="hidden" name="class_id" value="<?php echo (int) $edit_id; ?>">
          <?php endif; ?>

          <ul style="list-style-type:none;">
            <div class="col-md-12"> <h3 class="heding_name"><?php echo $is_edit ? 'Update Class' : 'Add New Class'; ?></h3></div>
            <div class="clearfix"></div>
            <hr>

            <li>
              <label for=""> Class Name </label>
              <input type="text" name="name" class="inputfield" value="<?php echo e($edit_name); ?>" autocomplete="off" required maxlength="100">
            </li>

            <li>
              <label for=""> Class Head </label>
              <select name="class_head" class="inputfield">
                <option value="">Select Class Head</option>
                <?php foreach ($head_options as $hid => $hname): ?>
                <option value="<?php echo (int) $hid; ?>" <?php echo $edit_head === $hid ? 'selected' : ''; ?>>
                  <?php echo e($hname); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </li>

            <li>
              <label for=""> Standard Monthly Fee </label>
              <input type="text" name="fee" class="inputfield" value="<?php echo $edit_fee > 0 ? (float) $edit_fee : ''; ?>" required maxlength="100">
            </li>

            <li>
              <label for=""> Standard Misc Fee </label>
              <input type="text" name="misc" class="inputfield" value="<?php echo $edit_misc > 0 ? (float) $edit_misc : ''; ?>" maxlength="100">
            </li>

            <div class="clearfix"></div>
            <li class=" pull-left" style="margin-top:30px;">
              <input type="submit" value=" Submit " class="btn btn-primary">
            </li>
          </ul>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner"></div>
</div>

<script>
  $('a[href*="delete_class=1"]').on('click', function() {
    if (confirm('Are you sure you want to delete this class? This will also expel all students in this class!')) {
      $('#loadingOverlay').addClass('active');
    } else {
      return false;
    }
  });
  $('form').on('submit', function() {
    $('#loadingOverlay').addClass('active');
  });
  setTimeout(function() {
    $('.alert').fadeOut('slow');
  }, 5000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>