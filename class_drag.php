<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin']);
$page_title = 'Class Order';

/* Reachable straight from the Class Order button, so it must create its own
   ordering column rather than trusting another page to have run first. */
try {
    $col = db_query("SHOW COLUMNS FROM classes LIKE 'sort_order'");
    if ($col && $col->num_rows === 0) {
        db_query("ALTER TABLE classes ADD COLUMN sort_order INT(11) NOT NULL DEFAULT 0 AFTER class_name");
        db_query("UPDATE classes SET sort_order = class_id WHERE sort_order = 0");
    }
    $idx = db_query("SHOW INDEX FROM classes WHERE Key_name = 'idx_classes_order'");
    if ($idx && $idx->num_rows === 0) {
        db_query("ALTER TABLE classes ADD INDEX idx_classes_order (sort_order)");
    }
} catch (\Throwable $e) {
    $migration_error = 'Class ordering is not set up correctly. Please contact your administrator.';
}

$msg = '';
$err = isset($migration_error) ? $migration_error : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $err === '') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $order  = isset($_POST['class_order']) && is_array($_POST['class_order'])
        ? array_map('intval', $_POST['class_order'])
        : array();

    if ($action !== 'SaveClassOrder') {
        $err = 'Invalid request.';
    } elseif (count($order) === 0) {
        $err = 'There are no classes to order.';
    } else {
        /* only accept ids that really exist, so a crafted POST cannot touch
           a class that is not in the list */
        $allowed = array();
        $res = db_query("SELECT class_id FROM classes");
        while ($r = $res->fetch_assoc()) {
            $allowed[(int) $r['class_id']] = true;
        }

        $clean = array();
        foreach ($order as $cid) {
            if (isset($allowed[$cid])) {
                $clean[] = $cid;
                unset($allowed[$cid]);
            }
        }
        /* anything the form did not send keeps its place at the end */
        foreach (array_keys($allowed) as $cid) {
            $clean[] = (int) $cid;
        }

        $upd = db_prepare("UPDATE classes SET sort_order = ? WHERE class_id = ?");
        $pos = 1;
        foreach ($clean as $cid) {
            $upd->bind_param('ii', $pos, $cid);
            $upd->execute();
            $pos++;
        }
        $msg = 'Class order saved.';
    }
}

/* Classes in their saved order, with the section count for context. */
$classes = array();
$res = db_query("SELECT c.class_id, c.class_name, c.sort_order, c.status,
                        (SELECT COUNT(*) FROM sections s WHERE s.class_id = c.class_id) AS sec_count
                 FROM classes c
                 ORDER BY c.sort_order ASC, c.class_id ASC");
while ($r = $res->fetch_assoc()) {
    $classes[] = array(
        'id'       => (int) $r['class_id'],
        'name'     => (string) $r['class_name'],
        'status'   => (int) $r['status'],
        'sections' => (int) $r['sec_count'],
    );
}
$total_classes = count($classes);

$academic_active = 'manage_classes.php';
include __DIR__ . '/includes/header.php';
?>
<div class="page-header" style="margin-top: 0px;">
  <nav aria-label="breadcrumb" class="breadcrumb">
    <a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
    <span> <i class="fa fa-angle-double-right"></i> </span>
    <a href="<?php echo BASE_URL; ?>manage_classes.php"><i class="fa fa-users"></i> Class &amp; Sections</a>
    <span> <i class="fa fa-angle-double-right"></i> </span>
    <span>Class Order</span>
  </nav>
  <h2><i class="fa fa-sort"></i> Class Order / Sorting</h2>
</div>

<?php $academic_tabs_container_style = 'margin:0 0 18px;'; include __DIR__ . '/includes/academic_tabs.php'; ?>

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

<style>
  .co-stats-card { background:#fff; border-radius:8px; padding:15px 20px; margin-bottom:20px;
    box-shadow:0 2px 4px rgba(0,0,0,0.1); border-left:4px solid #ff9800;
    display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
  .co-stats-card .badge { font-size:18px; padding:8px 15px; background:#ff9800; color:#111; border-radius:20px; }

  .co-card { background:#fff; border-radius:8px; padding:20px; box-shadow:0 2px 4px rgba(0,0,0,0.1); }
  .co-card h3 { margin:0 0 4px; font-size:18px; }
  .co-hint { font-size:12px; color:#898781; margin:0 0 16px; }

  .co-list { list-style:none; margin:0; padding:0; }
  .co-item { display:flex; align-items:center; gap:10px; background:#fcfcfb;
    border:1px solid #e1e0d9; border-radius:8px; padding:10px 12px; margin-bottom:8px; }
  .co-item:last-child { margin-bottom:0; }
  .co-num { flex:0 0 auto; width:28px; height:28px; border-radius:50%; background:#f0efeb;
    color:#52514e; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
  .co-name { flex:1 1 auto; min-width:0; font-size:15px; font-weight:600; overflow-wrap:break-word; word-wrap:break-word; }
  .co-meta { flex:0 0 auto; font-size:12px; color:#898781; }
  .co-move { flex:0 0 auto; display:flex; gap:4px; }
  .co-btn { width:34px; height:34px; padding:0; line-height:1; display:flex; align-items:center; justify-content:center; }
  .co-btn[disabled] { opacity:.4; cursor:not-allowed; }
  .co-actions { margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; }
  .co-empty { color:#898781; font-style:italic; padding:10px 2px; }
  .co-inactive { font-size:11px; font-weight:600; padding:2px 8px; border-radius:12px;
    background:#f8d7da; color:#721c24; }

  @media (max-width: 767px) {
    .co-item { flex-wrap:wrap; }
    .co-name { flex:1 1 auto; }
    .co-meta { order:3; flex:1 1 100%; }
    .co-move { order:2; }
    .co-btn { width:38px; height:38px; }
    .co-actions .btn { flex:1 1 auto; }
  }
</style>

<div class="co-stats-card">
  <strong><i class="fa fa-info-circle"></i> Total Classes:</strong>
  <span class="badge"><?php echo (int) $total_classes; ?></span>
</div>

<div class="co-card">
  <h3>Class Order</h3>
  <p class="co-hint">Use the arrows to set the order in which classes appear throughout the system.</p>

  <?php if ($total_classes === 0): ?>
    <div class="co-empty">There are no classes yet.</div>
    <div class="co-actions">
      <a href="<?php echo BASE_URL; ?>manage_classes.php" class="btn btn-primary">Go to Class Management</a>
    </div>
  <?php else: ?>
  <form method="post" action="">
    <input type="hidden" name="action" value="SaveClassOrder">
    <ol class="co-list">
      <?php foreach ($classes as $i => $c): ?>
      <li class="co-item">
        <span class="co-num"><?php echo $i + 1; ?></span>
        <span class="co-name">
          <?php echo e($c['name']); ?>
          <?php if ($c['status'] !== 1): ?><span class="co-inactive">Inactive</span><?php endif; ?>
        </span>
        <span class="co-meta"><?php echo (int) $c['sections']; ?> section(s)</span>
        <input type="hidden" name="class_order[]" value="<?php echo (int) $c['id']; ?>">
        <span class="co-move">
          <button type="button" class="btn btn-default btn-sm co-btn js-move" data-dir="-1" <?php echo $i === 0 ? 'disabled' : ''; ?>
                  aria-label="Move <?php echo e($c['name']); ?> up" title="Move up">
            <i class="fa fa-arrow-up" aria-hidden="true"></i>
          </button>
          <button type="button" class="btn btn-default btn-sm co-btn js-move" data-dir="1" <?php echo $i === $total_classes - 1 ? 'disabled' : ''; ?>
                  aria-label="Move <?php echo e($c['name']); ?> down" title="Move down">
            <i class="fa fa-arrow-down" aria-hidden="true"></i>
          </button>
        </span>
      </li>
      <?php endforeach; ?>
    </ol>
    <div class="co-actions">
      <button type="submit" class="btn btn-success"><i class="fa fa-save" aria-hidden="true"></i> Save Order</button>
      <a href="<?php echo BASE_URL; ?>manage_classes.php" class="btn btn-default">Back to Class Management</a>
    </div>
  </form>
  <?php endif; ?>
</div>

<script>
(function () {
    var list = document.querySelector('.co-list');
    if (!list) return;

    function renumber() {
        var items = list.querySelectorAll('.co-item');
        items.forEach(function (li, idx) {
            li.querySelector('.co-num').textContent = idx + 1;
            var up = li.querySelector('.js-move[data-dir="-1"]');
            var down = li.querySelector('.js-move[data-dir="1"]');
            if (up) up.disabled = (idx === 0);
            if (down) down.disabled = (idx === items.length - 1);
        });
    }

    list.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-move');
        if (!btn) return;
        ev.preventDefault();
        var li = btn.closest('.co-item');
        if (!li) return;
        if (btn.getAttribute('data-dir') === '-1') {
            var prev = li.previousElementSibling;
            if (prev) list.insertBefore(li, prev);
        } else {
            var next = li.nextElementSibling;
            if (next) list.insertBefore(next, li);
        }
        renumber();
        btn.focus();
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
