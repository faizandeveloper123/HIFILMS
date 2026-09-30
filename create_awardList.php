<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Award List';

db_query("CREATE TABLE IF NOT EXISTS award_lists (
  award_id INT(11) NOT NULL AUTO_INCREMENT,
  award_name VARCHAR(191) NOT NULL,
  class_id INT(11) NOT NULL DEFAULT 0,
  min_percentage DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  description VARCHAR(255) NOT NULL DEFAULT '',
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (award_id),
  KEY idx_award_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_award') {
        $name = trim($_POST['award_name'] ?? '');
        $class_id = (int) ($_POST['class_id'] ?? 0);
        $min_pct = (float) ($_POST['min_percentage'] ?? 0);
        $desc = trim($_POST['description'] ?? '');

        if ($name === '') {
            $msg = 'Award name is required.';
            $msg_type = 'danger';
        } else {
            $ins = db_prepare("INSERT INTO award_lists (award_name, class_id, min_percentage, description, status) VALUES (?, ?, ?, ?, 1)");
            $ins->bind_param('sids', $name, $class_id, $min_pct, $desc);
            $ins->execute();
            $msg = 'Award added successfully.';
        }
    }

    if ($action === 'edit_award') {
        $aid = (int) ($_POST['award_id'] ?? 0);
        $name = trim($_POST['award_name'] ?? '');
        $class_id = (int) ($_POST['class_id'] ?? 0);
        $min_pct = (float) ($_POST['min_percentage'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $status = (int) ($_POST['status'] ?? 1);

        if ($aid <= 0 || $name === '') {
            $msg = 'Invalid data.';
            $msg_type = 'danger';
        } else {
            $st = db_prepare("UPDATE award_lists SET award_name=?, class_id=?, min_percentage=?, description=?, status=? WHERE award_id=?");
            $st->bind_param('siddsi', $name, $class_id, $min_pct, $desc, $status, $aid);
            $st->execute();
            $msg = 'Award updated.';
        }
    }

    if ($action === 'delete_award') {
        $aid = (int) ($_POST['award_id'] ?? 0);
        if ($aid > 0) {
            $st = db_prepare("DELETE FROM award_lists WHERE award_id=?");
            $st->bind_param('i', $aid);
            $st->execute();
            $msg = 'Award deleted.';
        }
    }

    if ($action === 'toggle_status') {
        $aid = (int) ($_POST['award_id'] ?? 0);
        if ($aid > 0) {
            $st = db_prepare("UPDATE award_lists SET status = 1 - status WHERE award_id=?");
            $st->bind_param('i', $aid);
            $st->execute();
            $msg = 'Award status updated.';
        }
    }
}

$class_rows = [];
$res = db_query("SELECT class_id, class_name FROM classes ORDER BY class_id");
while ($r = $res->fetch_assoc()) { $class_rows[] = $r; }
$class_map = [];
foreach ($class_rows as $c) { $class_map[(int) $c['class_id']] = $c['class_name']; }

$rows = [];
$res = db_query("SELECT award_id, award_name, class_id, min_percentage, description, status FROM award_lists ORDER BY award_id DESC");
while ($r = $res->fetch_assoc()) { $rows[] = $r; }

include __DIR__ . '/includes/header.php';
?>
<style>
    .page-header { background: #2b2b36; color: white; padding: 20px 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .page-header h2 { margin: 0; font-size: 24px; font-weight: 600; color: white; }
    .breadcrumb { background: transparent; padding: 0; margin: 0 0 10px 0; color: rgba(255,255,255,0.9); }
    .breadcrumb a { color: rgba(255,255,255,0.9); text-decoration: none; }
    .breadcrumb a:hover { color: white; text-decoration: underline; }
    .info-banner { display: flex; align-items: center; gap: 15px; background: #fff7f0; border: 1px solid #ffd8b3; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; }
    .info-banner .info-icon { width: 42px; height: 42px; border-radius: 50%; background: #ff7800; color: white; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .info-banner strong { color: #e67e22; }
    .info-banner p { margin: 2px 0 0; color: #555; font-size: 13px; }
    .award-card { background: white; border-radius: 8px; padding: 22px 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
    .award-card-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; }
    .award-card-header h3 { margin: 0 0 4px; font-weight: 600; }
    .award-card-header p { margin: 0; color: #888; font-size: 13px; }
    table.award-table thead th { background: #2b2b36; color: white; text-align: center; padding: 12px; border: none; font-weight: 600; font-size: 13px; }
    table.award-table tbody td { padding: 10px; vertical-align: middle; text-align: center; }
    table.award-table tbody tr:hover { background-color: #f8f9fa; }
    .award-name-input { font-weight: 600; }
    .btn-add-award { background: #ff7800; border-color: #ff7800; color: white; font-weight: 600; padding: 9px 18px; border-radius: 6px; }
    .btn-add-award:hover { background: #e56d00; border-color: #e56d00; color: white; }
    .empty-state { text-align: center; color: #8a8a8a; padding: 35px 10px; }
</style>

<div class="page-header">
  <nav class="breadcrumb">
    <a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
    <span> <i class="fa fa-angle-double-right"></i> </span>
    <a href="<?php echo BASE_URL; ?>academic_setup.php">Academic Setup</a>
    <span> <i class="fa fa-angle-double-right"></i> </span>
    <span>Award List</span>
  </nav>
  <h2><i class="fa fa-list"></i> Award List</h2>
</div>

<?php include __DIR__ . '/includes/academic_tabs.php'; ?>
<br>

<?php if ($msg !== ''): ?>
<div class="alert alert-<?php echo $msg_type === 'danger' ? 'danger' : 'success'; ?> alert-dismissible fade in">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <?php echo e($msg); ?>
</div>
<?php endif; ?>

<div class="info-banner">
  <div class="info-icon"><i class="fa fa-info"></i></div>
  <div>
    <strong>How it works?</strong>
    <p>Define merit awards with a minimum percentage threshold and optionally restrict them to a specific course/class. Leave the class as "All Classes" to apply the award school-wide.</p>
  </div>
</div>

<div class="award-card">
  <div class="award-card-header">
    <div>
      <h3>Add New Award</h3>
      <p>Create an award rule that can be used on report cards.</p>
    </div>
  </div>
  <form method="post" action="<?php echo BASE_URL; ?>create_awardList.php">
    <input type="hidden" name="action" value="add_award">
    <div class="row">
      <div class="col-md-4 col-sm-6">
        <label>Award Name</label>
        <input type="text" class="form-control award-name-input" name="award_name" placeholder="e.g. Merit Scholarship" required>
      </div>
      <div class="col-md-3 col-sm-6">
        <label>Course/Class</label>
        <select class="form-control" name="class_id">
          <option value="0">All Classes</option>
          <?php foreach ($class_rows as $c): ?>
            <option value="<?php echo (int) $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 col-sm-4">
        <label>Min %</label>
        <input type="text" class="form-control" name="min_percentage" value="0">
      </div>
      <div class="col-md-2 col-sm-6">
        <label>Description</label>
        <input type="text" class="form-control" name="description" placeholder="Optional note">
      </div>
      <div class="col-md-1 col-sm-2">
        <label>&nbsp;</label><br>
        <button type="submit" class="btn btn-add-award"><i class="fa fa-plus"></i> Add</button>
      </div>
    </div>
  </form>
</div>

<div class="award-card">
  <div class="award-card-header">
    <div>
      <h3>Award Rules</h3>
      <p>Edit, toggle or delete the configured awards.</p>
    </div>
    <span class="badge" style="background:#2b2b36; font-size:13px; padding:9px 14px;"><?php echo (int) count($rows); ?> total</span>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered award-table">
      <thead>
        <tr>
          <th style="width:5%">#</th>
          <th style="width:24%">Award Name</th>
          <th style="width:18%">Course/Class</th>
          <th style="width:10%">Min %</th>
          <th style="width:23%">Description</th>
          <th style="width:10%">Status</th>
          <th style="width:10%">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows) === 0): ?>
          <tr><td colspan="7" class="empty-state">No awards configured yet. Add one using the form above.</td></tr>
        <?php else: ?>
          <?php $i = 1; foreach ($rows as $r): ?>
            <?php
              $clsId   = (int) $r['class_id'];
              $clsName = $clsId > 0 ? ($class_map[$clsId] ?? ('Class #' . $clsId)) : 'All Classes';
              $isOn    = (int) $r['status'] === 1;
            ?>
            <tr>
              <td class="row-index"><?php echo (int) $i; ?></td>
              <td style="text-align:left;"><strong><?php echo e($r['award_name']); ?></strong></td>
              <td><?php echo e($clsName); ?></td>
              <td><?php echo e($r['min_percentage']); ?>%</td>
              <td style="text-align:left;"><?php echo e($r['description']); ?></td>
              <td>
                <form method="post" action="<?php echo BASE_URL; ?>create_awardList.php" style="display:inline;">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="award_id" value="<?php echo (int) $r['award_id']; ?>">
                  <button type="submit" class="status-badge <?php echo $isOn ? 'status-present' : 'status-absent'; ?>" style="border:none; cursor:pointer;">
                    <?php echo $isOn ? 'Active' : 'Inactive'; ?>
                  </button>
                </form>
              </td>
              <td>
                <button type="button" class="btn btn-primary btn-xs" onclick='editAward(<?php echo (int) $r['award_id']; ?>, <?php echo $clsId; ?>, <?php echo e($r['min_percentage']); ?>, <?php echo $isOn ? 1 : 0; ?>, <?php echo json_encode($r['award_name']); ?>, <?php echo json_encode($r['description']); ?>)'><i class="fa fa-edit"></i></button>
                <form method="post" action="<?php echo BASE_URL; ?>create_awardList.php" style="display:inline;" onsubmit="return confirm('Delete this award?');">
                  <input type="hidden" name="action" value="delete_award">
                  <input type="hidden" name="award_id" value="<?php echo (int) $r['award_id']; ?>">
                  <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php $i++; endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="editAwardModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post" action="<?php echo BASE_URL; ?>create_awardList.php">
        <input type="hidden" name="action" value="edit_award">
        <input type="hidden" name="award_id" id="edit_award_id">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title"><i class="fa fa-edit"></i> Edit Award</h4>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Award Name</label>
            <input type="text" class="form-control" name="award_name" id="edit_award_name" required>
          </div>
          <div class="form-group">
            <label>Course/Class</label>
            <select class="form-control" name="class_id" id="edit_class_id">
              <option value="0">All Classes</option>
              <?php foreach ($class_rows as $c): ?>
                <option value="<?php echo (int) $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Min %</label>
            <input type="text" class="form-control" name="min_percentage" id="edit_min_percentage">
          </div>
          <div class="form-group">
            <label>Description</label>
            <input type="text" class="form-control" name="description" id="edit_description">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select class="form-control" name="status" id="edit_status">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
    function editAward(id, classId, minPct, status, name, desc) {
      document.getElementById('edit_award_id').value = id;
      document.getElementById('edit_award_name').value = name;
      document.getElementById('edit_class_id').value = classId;
      document.getElementById('edit_min_percentage').value = minPct;
      document.getElementById('edit_description').value = desc;
      document.getElementById('edit_status').value = status;
      $('#editAwardModal').modal('show');
    }

    setTimeout(function(){ $('.alert').fadeOut('slow'); }, 5000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
