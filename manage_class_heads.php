<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin']);
$page_title = 'Class Heads';

/* manage_classes.php creates this table, but this page is linked directly from
   it and can also be bookmarked, so make sure the table exists here too. */
try {
    db_query("CREATE TABLE IF NOT EXISTS class_heads (
      class_head_id INT(11) NOT NULL AUTO_INCREMENT,
      class_head_name VARCHAR(191) NOT NULL,
      status TINYINT(4) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (class_head_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (\Throwable $e) {
    $err = 'Class heads are not set up correctly. Please contact your administrator.';
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($err)) {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $head_id = isset($_POST['head_id']) ? (int) $_POST['head_id'] : 0;
    $name    = isset($_POST['head_name']) ? trim((string) $_POST['head_name']) : '';

    try {
        if ($action === 'AddHead') {
            if ($name === '') {
                $err = 'Class head name is required.';
            } else {
                $dup = db_prepare("SELECT COUNT(*) c FROM class_heads WHERE LOWER(class_head_name) = LOWER(?)");
                $dup->bind_param('s', $name);
                $dup->execute();
                if (((int) $dup->get_result()->fetch_assoc()['c']) > 0) {
                    $err = 'A class head named "' . $name . '" already exists.';
                } else {
                    $ins = db_prepare("INSERT INTO class_heads (class_head_name, status) VALUES (?, 1)");
                    $ins->bind_param('s', $name);
                    $ins->execute();
                    $msg = 'Class head added successfully.';
                }
            }
        } elseif ($action === 'UpdateHead' && $head_id > 0) {
            if ($name === '') {
                $err = 'Class head name is required.';
            } else {
                $dup = db_prepare("SELECT COUNT(*) c FROM class_heads WHERE LOWER(class_head_name) = LOWER(?) AND class_head_id <> ?");
                $dup->bind_param('si', $name, $head_id);
                $dup->execute();
                if (((int) $dup->get_result()->fetch_assoc()['c']) > 0) {
                    $err = 'A class head named "' . $name . '" already exists.';
                } else {
                    $upd = db_prepare("UPDATE class_heads SET class_head_name = ? WHERE class_head_id = ?");
                    $upd->bind_param('si', $name, $head_id);
                    $upd->execute();
                    $msg = 'Class head updated successfully.';
                }
            }
        } elseif ($action === 'ToggleStatus' && $head_id > 0) {
            /* class_heads.status is a TINYINT: 1 = active, 0 = inactive. */
            $stmt = db_prepare("SELECT status FROM class_heads WHERE class_head_id = ?");
            $stmt->bind_param('i', $head_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $new = ((int) $row['status'] === 1) ? 0 : 1;
                $upd = db_prepare("UPDATE class_heads SET status = ? WHERE class_head_id = ?");
                $upd->bind_param('ii', $new, $head_id);
                $upd->execute();
                $msg = $new === 1 ? 'Class head marked as active.' : 'Class head marked as inactive.';
            } else {
                $err = 'That class head was not found.';
            }
        } elseif ($action === 'DeleteHead' && $head_id > 0) {
            /* classes.class_head_id is a FK to class_heads, so the classes have
               to be unlinked first - deleting the head outright would fail. */
            $chk = db_prepare("SELECT COUNT(*) c FROM classes WHERE class_head_id = ?");
            $chk->bind_param('i', $head_id);
            $chk->execute();
            $used = (int) $chk->get_result()->fetch_assoc()['c'];

            if ($used > 0) {
                /* keep the delete safe: detach instead of destroying the link */
                $upd = db_prepare("UPDATE classes SET class_head_id = NULL WHERE class_head_id = ?");
                $upd->bind_param('i', $head_id);
                $upd->execute();
            }
            $del = db_prepare("DELETE FROM class_heads WHERE class_head_id = ?");
            $del->bind_param('i', $head_id);
            $del->execute();
            $msg = $used > 0
                ? 'Class head deleted. ' . $used . ' class(es) now have no class head assigned.'
                : 'Class head deleted successfully.';
        } else {
            $err = 'Invalid request.';
        }
    } catch (\Throwable $e) {
        $err = 'Could not save the class head. Please check the values and try again.';
    }
}

$heads = array();
$res = db_query("SELECT h.class_head_id, h.class_head_name, h.status, h.created_at,
                        (SELECT COUNT(*) FROM classes c WHERE c.class_head_id = h.class_head_id) AS class_count
                 FROM class_heads h
                 ORDER BY h.class_head_name ASC");
while ($r = $res->fetch_assoc()) {
    $heads[] = array(
        'id'        => (int) $r['class_head_id'],
        'name'      => (string) $r['class_head_name'],
        'status'    => (int) $r['status'],
        'classes'   => (int) $r['class_count'],
        'created'   => $r['created_at'],
    );
}
$total_heads = count($heads);
$total_active = 0;
foreach ($heads as $h) {
    if ($h['status'] === 1) {
        $total_active++;
    }
}

$edit_id   = isset($_GET['edit_head']) ? (int) $_GET['edit_head'] : 0;
$edit_name = '';
if ($edit_id > 0) {
    foreach ($heads as $h) {
        if ($h['id'] === $edit_id) {
            $edit_name = $h['name'];
            break;
        }
    }
    if ($edit_name === '') {
        $edit_id = 0;
    }
}

$academic_active = 'manage_classes.php';
include __DIR__ . '/includes/header.php';
?>
<div class="page-header" style="margin-top: 0px;">
  <nav aria-label="breadcrumb" class="breadcrumb">
    <a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
    <span> <i class="fa fa-angle-double-right"></i> </span>
    <a href="<?php echo BASE_URL; ?>manage_classes.php"><i class="fa fa-users"></i> Class &amp; Sections</a>
    <span> <i class="fa fa-angle-double-right"></i> </span>
    <span>Class Heads</span>
  </nav>
  <h2><i class="fa fa-sitemap"></i> Class Heads</h2>
</div>

<?php $academic_tabs_container_style = 'margin:0 0 18px;'; include __DIR__ . '/includes/academic_tabs.php'; ?>

<?php if ($msg !== ''): ?>
<div class="alert alert-success alert-dismissible fade in">
  <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
  <?php echo e($msg); ?>
</div>
<?php endif; ?>

<?php if (isset($err) && $err !== ''): ?>
<div class="alert alert-danger alert-dismissible fade in">
  <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
  <?php echo e($err); ?>
</div>
<?php endif; ?>

<style>
  .ch-stats-card { background:#fff; border-radius:8px; padding:15px 20px; margin-bottom:20px;
    box-shadow:0 2px 4px rgba(0,0,0,0.1); border-left:4px solid #ff9800;
    display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
  .ch-stats-card .badge { font-size:18px; padding:8px 15px; background:#ff9800; color:#111; border-radius:20px; }

  .ch-card { background:#fff; border-radius:8px; padding:20px; box-shadow:0 2px 4px rgba(0,0,0,0.1); }
  .ch-card h3 { margin:0 0 14px; font-size:18px; }

  .ch-list { list-style:none; margin:0; padding:0; }
  .ch-item { display:flex; align-items:center; gap:10px; background:#fcfcfb;
    border:1px solid #e1e0d9; border-radius:8px; padding:10px 12px; margin-bottom:8px; }
  .ch-item:last-child { margin-bottom:0; }
  .ch-name { flex:1 1 auto; min-width:0; font-size:15px; font-weight:600; overflow-wrap:break-word; word-wrap:break-word; }
  .ch-meta { flex:0 0 auto; font-size:12px; color:#898781; white-space:nowrap; }
  .ch-actions { flex:0 0 auto; display:flex; gap:4px; }
  .ch-empty { color:#898781; font-style:italic; padding:10px 2px; }

  .ch-badge { font-size:11px; font-weight:600; padding:2px 10px; border-radius:12px; }
  .ch-active { background:#d4edda; color:#155724; }
  .ch-inactive { background:#f8d7da; color:#721c24; }

  .ch-add { display:flex; gap:8px; flex-wrap:wrap; margin-top:18px; border-top:1px solid #e1e0d9; padding-top:18px; }
  .ch-add .inputfield { flex:1 1 220px; margin:0; }
  .ch-footer { margin-top:16px; }

  @media (max-width: 767px) {
    .ch-item { flex-wrap:wrap; }
    .ch-name { flex:1 1 100%; }
    .ch-meta { flex:1 1 auto; }
    .ch-actions { flex:1 1 100%; }
    .ch-actions .btn { flex:1 1 auto; }
  }
</style>

<div class="ch-stats-card">
  <strong><i class="fa fa-info-circle"></i> Total Class Heads:</strong>
  <span class="badge"><?php echo (int) $total_heads; ?></span>
  <strong><i class="fa fa-check-circle"></i> Active:</strong>
  <span class="badge"><?php echo (int) $total_active; ?></span>
</div>

<div class="ch-card">
  <h3>Class Heads</h3>

  <?php if ($total_heads === 0): ?>
    <div class="ch-empty">No class heads yet. Add the first one below.</div>
  <?php else: ?>
    <ul class="ch-list">
      <?php foreach ($heads as $h): ?>
      <li class="ch-item">
        <span class="ch-name">
          <?php echo e($h['name']); ?>
          <?php if ($h['status'] === 1): ?>
            <span class="ch-badge ch-active">Active</span>
          <?php else: ?>
            <span class="ch-badge ch-inactive">Inactive</span>
          <?php endif; ?>
        </span>
        <span class="ch-meta"><?php echo (int) $h['classes']; ?> class(es)</span>
        <span class="ch-actions">
          <form method="post" action="" style="display:contents;">
            <input type="hidden" name="action" value="ToggleStatus">
            <input type="hidden" name="head_id" value="<?php echo (int) $h['id']; ?>">
            <button type="submit" class="btn btn-warning btn-sm" title="<?php echo $h['status'] === 1 ? 'Mark as inactive' : 'Mark as active'; ?>">
              <i class="fa fa-toggle-on" aria-hidden="true"></i>
            </button>
          </form>
          <a class="btn btn-success btn-sm" title="Edit class head"
             href="<?php echo BASE_URL; ?>manage_class_heads.php?edit_head=<?php echo (int) $h['id']; ?>#ch_edit">
            <i class="fa fa-edit" aria-hidden="true"></i>
          </a>
          <form method="post" action="" style="display:contents;">
            <input type="hidden" name="action" value="DeleteHead">
            <input type="hidden" name="head_id" value="<?php echo (int) $h['id']; ?>">
            <button type="submit" class="btn btn-danger btn-sm" title="Delete class head"
                    onclick="return confirm('Delete class head \'<?php echo e(addslashes($h['name'])); ?>\'?<?php echo $h['classes'] > 0 ? ' ' . (int) $h['classes'] . ' class(es) will have no class head assigned.' : ''; ?>');">
              <i class="fa fa-trash" aria-hidden="true"></i>
            </button>
          </form>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div class="ch-add" id="ch_edit">
    <form method="post" action="" style="display:contents;">
      <?php if ($edit_id > 0): ?>
        <input type="hidden" name="action" value="UpdateHead">
        <input type="hidden" name="head_id" value="<?php echo (int) $edit_id; ?>">
        <input type="text" name="head_name" class="inputfield" required maxlength="191"
               placeholder="Class head name" value="<?php echo e($edit_name); ?>" autofocus>
        <button type="submit" class="btn btn-warning"><i class="fa fa-check" aria-hidden="true"></i> Update</button>
        <a href="<?php echo BASE_URL; ?>manage_class_heads.php" class="btn btn-default">Cancel</a>
      <?php else: ?>
        <input type="hidden" name="action" value="AddHead">
        <input type="text" name="head_name" class="inputfield" required maxlength="191" placeholder="New class head name">
        <button type="submit" class="btn btn-primary"><i class="fa fa-plus" aria-hidden="true"></i> Add Class Head</button>
      <?php endif; ?>
    </form>
  </div>

  <div class="ch-footer">
    <a href="<?php echo BASE_URL; ?>manage_classes.php" class="btn btn-default">Back to Class Management</a>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
