<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Subject Order';

db_query("CREATE TABLE IF NOT EXISTS class_subjects (
  id INT(11) NOT NULL AUTO_INCREMENT,
  class_id INT(11) NOT NULL,
  section_id INT(11) NOT NULL,
  subject_id INT(11) NOT NULL,
  sort_order INT(11) NOT NULL DEFAULT 0,
  session VARCHAR(50) NOT NULL DEFAULT '2026-2027',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cs (class_id, section_id, subject_id),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* This page is reachable straight from the Subject Order button, so it cannot
   rely on class_subjects.php having run its migration first. */
try {
    $csCol = db_query("SHOW COLUMNS FROM class_subjects LIKE 'sort_order'");
    if ($csCol && $csCol->num_rows === 0) {
        db_query("ALTER TABLE class_subjects ADD COLUMN sort_order INT(11) NOT NULL DEFAULT 0 AFTER subject_id");
    }
    $csIdx = db_query("SHOW INDEX FROM class_subjects WHERE Key_name = 'idx_cs_order'");
    if ($csIdx && $csIdx->num_rows === 0) {
        db_query("ALTER TABLE class_subjects ADD INDEX idx_cs_order (class_id, section_id, sort_order)");
    }
} catch (\Throwable $e) {}

$msg = '';
$err = '';

$class_id   = isset($_REQUEST['class_id']) ? (int) $_REQUEST['class_id'] : 0;
$section_id = isset($_REQUEST['section_id']) ? (int) $_REQUEST['section_id'] : 0;

/* Resolve the section and its class name (sections carries class_id). */
$class_name   = '';
$section_name = '';
if ($class_id > 0 && $section_id > 0) {
    $stmt = db_prepare("SELECT c.class_name, s.section_name
                        FROM sections s
                        JOIN classes c ON c.class_id = s.class_id
                        WHERE s.section_id = ? AND s.class_id = ?");
    $stmt->bind_param('ii', $section_id, $class_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $class_name   = (string) $row['class_name'];
        $section_name = (string) $row['section_name'];
    } else {
        $err = 'The selected section was not found.';
        $class_id = 0;
        $section_id = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $err === '') {
    $action   = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $order    = isset($_POST['subject_order']) && is_array($_POST['subject_order'])
                  ? array_map('intval', $_POST['subject_order']) : array();
    $order    = array_values(array_filter($order, function ($v) { return $v > 0; }));

    if ($class_id <= 0 || $section_id <= 0) {
        $err = 'The selected section was not found.';
    } elseif ($action !== 'SaveSubjectOrder') {
        $err = 'Invalid request.';
    } elseif (count($order) === 0) {
        $err = 'There are no subjects to order for this section.';
    } else {
        /* Only accept subject ids that really belong to this section, so a
           crafted POST cannot reorder another section's subjects. */
        $allowed = array();
        $stmt = db_prepare("SELECT subject_id FROM class_subjects WHERE class_id = ? AND section_id = ?");
        $stmt->bind_param('ii', $class_id, $section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($r = $result->fetch_assoc()) {
            $allowed[(int) $r['subject_id']] = true;
        }

        $clean = array();
        foreach ($order as $sid) {
            if (isset($allowed[$sid])) {
                $clean[] = $sid;
                unset($allowed[$sid]);
            }
        }
        /* Anything the form did not send keeps its relative order at the end. */
        foreach (array_keys($allowed) as $sid) {
            $clean[] = (int) $sid;
        }

        if (count($clean) === 0) {
            $err = 'Those subjects are not assigned to this section.';
        } else {
            $upd = db_prepare("UPDATE class_subjects SET sort_order = ? WHERE class_id = ? AND section_id = ? AND subject_id = ?");
            $pos = 0;
            foreach ($clean as $sid) {
                $upd->bind_param('iiii', $pos, $class_id, $section_id, $sid);
                $upd->execute();
                $pos++;
            }
            $msg = 'Subject order saved.';
        }
    }
}

/* Current subjects for this section, in their saved order. */
$subjects = array();
if ($class_id > 0 && $section_id > 0) {
    $stmt = db_prepare("SELECT cs.subject_id, s.subject_name
                        FROM class_subjects cs
                        JOIN subjects s ON s.subject_id = cs.subject_id
                        WHERE cs.class_id = ? AND cs.section_id = ?
                        ORDER BY cs.sort_order ASC, s.subject_name ASC, cs.subject_id ASC");
    $stmt->bind_param('ii', $class_id, $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) {
        $subjects[] = array('id' => (int) $r['subject_id'], 'name' => (string) $r['subject_name']);
    }
}

include __DIR__ . '/includes/header.php';
?>
<style>
    .so-page { background:#fff; padding-top:14px; }
    .so-breadcrumb { font-size:13px; margin:0 0 14px; overflow-wrap:break-word; word-wrap:break-word; }
    .so-breadcrumb i { color:#c9c8c3; }

    .so-card {
        background:#fcfcfb; border:1px solid #e1e0d9; border-radius:10px;
        padding:18px; box-shadow:0 1px 3px rgba(11,11,11,0.07); margin-bottom:16px;
    }
    .so-card h3 { margin:0 0 4px; font-size:17px; }
    .so-hint { font-size:12px; color:#898781; margin:0 0 16px; }

    .so-list { list-style:none; margin:0; padding:0; }
    .so-item {
        display:flex; align-items:center; gap:10px;
        background:#fff; border:1px solid #e1e0d9; border-radius:8px;
        padding:8px 10px; margin-bottom:8px;
    }
    .so-item:last-child { margin-bottom:0; }
    .so-num {
        flex:0 0 auto; width:26px; height:26px; border-radius:50%;
        background:#f0efeb; color:#52514e; font-size:12px; font-weight:700;
        display:flex; align-items:center; justify-content:center;
    }
    .so-name { flex:1 1 auto; min-width:0; font-size:14px; font-weight:500; overflow-wrap:break-word; word-wrap:break-word; }
    .so-move { flex:0 0 auto; display:flex; gap:4px; }
    .so-btn {
        width:34px; height:34px; padding:0; line-height:1;
        display:flex; align-items:center; justify-content:center;
    }
    .so-btn[disabled] { opacity:.4; cursor:not-allowed; }

    .so-actions { margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; }
    .so-empty { color:#898781; font-size:13px; font-style:italic; padding:6px 2px; }

    @media (max-width: 767px) {
        .so-breadcrumb { font-size:12px; margin-bottom:12px; }
        .so-card { padding:14px; }
        .so-card h3 { font-size:15px; }
        .so-item { padding:8px; }
        .so-name { font-size:13px; }
        .so-btn { width:38px; height:38px; }
        .so-actions .btn { flex:1 1 auto; }
    }
</style>

<div class="so-page">
  <div class="container">
    <div class="so-breadcrumb">
      <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a>
      &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
      <a href="<?php echo BASE_URL; ?>class_subjects.php">Class Subjects</a>
      &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
      Subject Order
    </div>
  </div>

  <div class="container">
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

    <div class="so-card">
      <h3><strong><?php echo e($class_name . ' - ' . $section_name); ?></strong></h3>
      <p class="so-hint">Use the arrows to set the order in which these subjects appear for this section.</p>

      <?php if (count($subjects) === 0): ?>
        <div class="so-empty">No subjects are assigned to this section yet.</div>
        <div class="so-actions">
          <a href="<?php echo BASE_URL; ?>class_subjects.php" class="btn btn-primary">Assign Subjects</a>
        </div>
      <?php else: ?>
      <form method="post" action="">
        <input type="hidden" name="action" value="SaveSubjectOrder">
        <input type="hidden" name="class_id" value="<?php echo (int) $class_id; ?>">
        <input type="hidden" name="section_id" value="<?php echo (int) $section_id; ?>">
        <ol class="so-list">
          <?php foreach ($subjects as $i => $sub): ?>
          <li class="so-item">
            <span class="so-num"><?php echo $i + 1; ?></span>
            <span class="so-name"><?php echo e($sub['name']); ?></span>
            <input type="hidden" name="subject_order[]" value="<?php echo (int) $sub['id']; ?>">
            <span class="so-move">
              <button type="button" class="btn btn-default btn-sm so-btn js-move" data-dir="-1" <?php echo $i === 0 ? 'disabled' : ''; ?>
                      aria-label="Move <?php echo e($sub['name']); ?> up" title="Move up">
                <i class="fa fa-arrow-up" aria-hidden="true"></i>
              </button>
              <button type="button" class="btn btn-default btn-sm so-btn js-move" data-dir="1" <?php echo $i === count($subjects) - 1 ? 'disabled' : ''; ?>
                      aria-label="Move <?php echo e($sub['name']); ?> down" title="Move down">
                <i class="fa fa-arrow-down" aria-hidden="true"></i>
              </button>
            </span>
          </li>
          <?php endforeach; ?>
        </ol>
        <div class="so-actions">
          <button type="submit" class="btn btn-success">
            <i class="fa fa-save" aria-hidden="true"></i> Save Order
          </button>
          <a href="<?php echo BASE_URL; ?>class_subjects.php" class="btn btn-default">Back to Class Subjects</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function () {
    var list = document.querySelector('.so-list');
    if (!list) return;

    function renumber() {
        var items = list.querySelectorAll('.so-item');
        items.forEach(function (li, idx) {
            li.querySelector('.so-num').textContent = idx + 1;
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
        var li = btn.closest('.so-item');
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
