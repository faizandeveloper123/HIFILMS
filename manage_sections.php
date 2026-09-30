<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin']);
$page_title = 'Manage Sections';

/* This page is linked straight from Manage Classes, so it cannot rely on any
   other page having created the ordering column first. */
try {
    $col = db_query("SHOW COLUMNS FROM sections LIKE 'sort_order'");
    if ($col && $col->num_rows === 0) {
        db_query("ALTER TABLE sections ADD COLUMN sort_order INT(11) NOT NULL DEFAULT 0 AFTER section_name");
        /* give the existing rows a stable order matching their id order */
        db_query("UPDATE sections SET sort_order = section_id WHERE sort_order = 0");
    }
    $idx = db_query("SHOW INDEX FROM sections WHERE Key_name = 'idx_sections_order'");
    if ($idx && $idx->num_rows === 0) {
        db_query("ALTER TABLE sections ADD INDEX idx_sections_order (class_id, sort_order)");
    }
} catch (\Throwable $e) {
    $migration_error = 'Section ordering is not set up correctly. Please contact your administrator.';
}

$msg = '';
$err = isset($migration_error) ? $migration_error : '';

/* Tables that hold a section_id, so a deleted section can be cleaned up
   without tripping a foreign key constraint. */
$sections_child_tables = array('class_periods', 'class_subjects', 'students', 'timetable');

$class_id = isset($_REQUEST['class_id']) ? (int) $_REQUEST['class_id'] : 0;

/* Sections outside the URL are never touched. */
function section_belongs_to_class($section_id, $class_id)
{
    $stmt = db_prepare("SELECT COUNT(*) c FROM sections WHERE section_id = ? AND class_id = ?");
    $stmt->bind_param('ii', $section_id, $class_id);
    $stmt->execute();
    return ((int) $stmt->get_result()->fetch_assoc()['c']) > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $post_class = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
    $sec_id     = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
    $sec_name   = isset($_POST['section_name']) ? trim((string) $_POST['section_name']) : '';

    if ($post_class > 0) {
        $class_id = $post_class;
    }

    try {
        if ($class_id <= 0) {
            $err = 'Please choose a class first.';
        } elseif ($action === 'AddSection') {
            if ($sec_name === '') {
                $err = 'Section name is required.';
            } else {
                $dup = db_prepare("SELECT COUNT(*) c FROM sections WHERE class_id = ? AND LOWER(section_name) = LOWER(?)");
                $dup->bind_param('is', $class_id, $sec_name);
                $dup->execute();
                if (((int) $dup->get_result()->fetch_assoc()['c']) > 0) {
                    $err = 'This class already has a section named "' . $sec_name . '".';
                } else {
                    /* new sections go to the end of their class's list */
                    $next = db_prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 n FROM sections WHERE class_id = ?");
                    $next->bind_param('i', $class_id);
                    $next->execute();
                    $pos = (int) $next->get_result()->fetch_assoc()['n'];

                    $ins = db_prepare("INSERT INTO sections (class_id, section_name, sort_order) VALUES (?, ?, ?)");
                    $ins->bind_param('isi', $class_id, $sec_name, $pos);
                    $ins->execute();
                    $msg = 'Section added successfully.';
                }
            }
        } elseif ($action === 'UpdateSection' && $sec_id > 0) {
            if ($sec_name === '') {
                $err = 'Section name is required.';
            } elseif (!section_belongs_to_class($sec_id, $class_id)) {
                $err = 'That section is not part of this class.';
            } else {
                $dup = db_prepare("SELECT COUNT(*) c FROM sections WHERE class_id = ? AND LOWER(section_name) = LOWER(?) AND section_id <> ?");
                $dup->bind_param('isi', $class_id, $sec_name, $sec_id);
                $dup->execute();
                if (((int) $dup->get_result()->fetch_assoc()['c']) > 0) {
                    $err = 'This class already has a section named "' . $sec_name . '".';
                } else {
                    $upd = db_prepare("UPDATE sections SET section_name = ? WHERE section_id = ? AND class_id = ?");
                    $upd->bind_param('sii', $sec_name, $sec_id, $class_id);
                    $upd->execute();
                    $msg = 'Section updated successfully.';
                }
            }
        } elseif ($action === 'DeleteSection' && $sec_id > 0) {
            if (!section_belongs_to_class($sec_id, $class_id)) {
                $err = 'That section is not part of this class.';
            } else {
                /* clear the dependent rows first, or the delete dies on a FK error */
                foreach ($sections_child_tables as $child) {
                    $del = db_prepare("DELETE FROM $child WHERE section_id = ?");
                    $del->bind_param('i', $sec_id);
                    $del->execute();
                }
                $del = db_prepare("DELETE FROM sections WHERE section_id = ? AND class_id = ?");
                $del->bind_param('ii', $sec_id, $class_id);
                $del->execute();
                $msg = 'Section deleted successfully.';
            }
        } elseif ($action === 'SaveSectionOrder') {
            $order = isset($_POST['section_order']) && is_array($_POST['section_order'])
                ? array_map('intval', $_POST['section_order'])
                : array();
            if (count($order) === 0) {
                $err = 'There are no sections to order for this class.';
            } else {
                /* only accept ids that really belong to this class, so a crafted
                   POST cannot reorder another class's sections */
                $allowed = array();
                $stmt = db_prepare("SELECT section_id FROM sections WHERE class_id = ?");
                $stmt->bind_param('i', $class_id);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $allowed[(int) $r['section_id']] = true;
                }

                $clean = array();
                foreach ($order as $sid) {
                    if (isset($allowed[$sid])) {
                        $clean[] = $sid;
                        unset($allowed[$sid]);
                    }
                }
                /* anything the form did not send keeps its place at the end */
                foreach (array_keys($allowed) as $sid) {
                    $clean[] = (int) $sid;
                }

                $upd = db_prepare("UPDATE sections SET sort_order = ? WHERE section_id = ? AND class_id = ?");
                $pos = 1;
                foreach ($clean as $sid) {
                    $upd->bind_param('iii', $pos, $sid, $class_id);
                    $upd->execute();
                    $pos++;
                }
                $msg = 'Section order saved.';
            }
        } else {
            $err = 'Invalid request.';
        }
    } catch (\Throwable $e) {
        $err = 'Could not save the section. Please check the values and try again.';
    }
}

/* Default to the first class so the page is never empty on arrival. */
$classes = array();
$cls_res = db_query("SELECT class_id, class_name FROM classes ORDER BY sort_order ASC, class_id ASC");
while ($r = $cls_res->fetch_assoc()) {
    $classes[] = array('id' => (int) $r['class_id'], 'name' => (string) $r['class_name']);
}
if ($class_id <= 0 && count($classes) > 0) {
    $class_id = $classes[0]['id'];
}
$valid_class_ids = array();
foreach ($classes as $c) {
    $valid_class_ids[$c['id']] = true;
}
if ($class_id > 0 && !isset($valid_class_ids[$class_id])) {
    $class_id = count($classes) > 0 ? $classes[0]['id'] : 0;
}

$class_name = '';
foreach ($classes as $c) {
    if ($c['id'] === $class_id) {
        $class_name = $c['name'];
        break;
    }
}

/* Sections of the selected class, with the counts that help avoid surprises. */
$sections = array();
if ($class_id > 0) {
    $stmt = db_prepare("SELECT s.section_id, s.section_name, s.sort_order,
                               (SELECT COUNT(*) FROM class_subjects cs WHERE cs.section_id = s.section_id) AS subj_count,
                               (SELECT COUNT(*) FROM students st WHERE st.section_id = s.section_id) AS student_count
                        FROM sections s
                        WHERE s.class_id = ?
                        ORDER BY s.sort_order ASC, s.section_name ASC, s.section_id ASC");
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $sections[] = array(
            'id'       => (int) $r['section_id'],
            'name'     => (string) $r['section_name'],
            'subjects' => (int) $r['subj_count'],
            'students' => (int) $r['student_count'],
        );
    }
}

$total_sections = count($sections);

$edit_id   = isset($_GET['edit_section']) ? (int) $_GET['edit_section'] : 0;
$edit_name = '';
if ($edit_id > 0) {
    foreach ($sections as $s) {
        if ($s['id'] === $edit_id) {
            $edit_name = $s['name'];
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
    <span>Manage Sections</span>
  </nav>
  <h2><i class="fa fa-list-alt"></i> Manage Sections</h2>
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
  .ms-stats-card { background:#fff; border-radius:8px; padding:15px 20px; margin-bottom:20px;
    box-shadow:0 2px 4px rgba(0,0,0,0.1); border-left:4px solid #ff9800;
    display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
  .ms-stats-card .badge { font-size:18px; padding:8px 15px; background:#ff9800; color:#111; border-radius:20px; }

  .ms-picker { background:#fff; border-radius:8px; padding:18px 20px; margin-bottom:20px;
    box-shadow:0 2px 4px rgba(0,0,0,0.1); }
  .ms-picker label { font-weight:600; margin-bottom:6px; display:block; font-size:14px; }

  .ms-card { background:#fff; border-radius:8px; padding:20px; box-shadow:0 2px 4px rgba(0,0,0,0.1); }
  .ms-card h3 { margin:0 0 14px; font-size:18px; }

  .ms-list { list-style:none; margin:0; padding:0; }
  .ms-item { display:flex; align-items:center; gap:10px; background:#fcfcfb;
    border:1px solid #e1e0d9; border-radius:8px; padding:10px 12px; margin-bottom:8px; }
  .ms-item:last-child { margin-bottom:0; }
  .ms-num { flex:0 0 auto; width:28px; height:28px; border-radius:50%; background:#f0efeb;
    color:#52514e; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
  .ms-name { flex:1 1 auto; min-width:0; font-size:15px; font-weight:600; overflow-wrap:break-word; word-wrap:break-word; }
  .ms-meta { flex:0 0 auto; font-size:12px; color:#898781; text-align:right; }
  .ms-meta span { display:block; white-space:nowrap; }
  .ms-move { flex:0 0 auto; display:flex; gap:4px; }
  .ms-btn { width:34px; height:34px; padding:0; line-height:1; display:flex; align-items:center; justify-content:center; }
  .ms-btn[disabled] { opacity:.4; cursor:not-allowed; }
  .ms-actions { flex:0 0 auto; display:flex; gap:4px; }
  .ms-empty { color:#898781; font-style:italic; padding:10px 2px; }

  .ms-footer { margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; }
  .ms-add { display:flex; gap:8px; flex-wrap:wrap; margin-top:18px;
    border-top:1px solid #e1e0d9; padding-top:18px; }
  .ms-add .inputfield { flex:1 1 220px; margin:0; }

  @media (max-width: 767px) {
    .ms-item { flex-wrap:wrap; }
    .ms-name { flex:1 1 100%; order:1; }
    .ms-num { order:0; }
    .ms-meta { flex:1 1 auto; text-align:left; order:2; }
    .ms-meta span { display:inline; margin-right:10px; }
    .ms-move { order:3; }
    .ms-actions { order:4; flex:1 1 100%; }
    .ms-actions .btn { flex:1 1 auto; }
    .ms-btn { width:38px; height:38px; }
    .ms-footer .btn { flex:1 1 auto; }
  }
</style>

<?php if (count($classes) === 0): ?>
  <div class="ms-card">
    <div class="ms-empty">There are no classes yet. Add a class first, then come back to manage its sections.</div>
    <div class="ms-footer">
      <a href="<?php echo BASE_URL; ?>manage_classes.php" class="btn btn-primary">Go to Class Management</a>
    </div>
  </div>
<?php else: ?>

  <div class="ms-stats-card">
    <strong><i class="fa fa-info-circle"></i> Sections in this class:</strong>
    <span class="badge"><?php echo (int) $total_sections; ?></span>
  </div>

  <div class="ms-picker">
    <label for="ms_class_picker">Choose a class</label>
    <form method="get" action="">
      <select name="class_id" id="ms_class_picker" class="inputfield" onchange="this.form.submit();">
        <?php foreach ($classes as $c): ?>
          <option value="<?php echo (int) $c['id']; ?>" <?php echo $c['id'] === $class_id ? 'selected' : ''; ?>>
            <?php echo e($c['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="ms-card">
    <h3><i class="fa fa-list-alt"></i> <?php echo e($class_name); ?></h3>

    <?php if ($total_sections === 0): ?>
      <div class="ms-empty">This class has no sections yet. Add the first one below.</div>
    <?php else: ?>
      <form method="post" action="">
        <input type="hidden" name="action" value="SaveSectionOrder">
        <input type="hidden" name="class_id" value="<?php echo (int) $class_id; ?>">
        <ol class="ms-list">
          <?php foreach ($sections as $i => $s): ?>
          <li class="ms-item">
            <span class="ms-num"><?php echo $i + 1; ?></span>
            <span class="ms-name"><?php echo e($s['name']); ?></span>
            <span class="ms-meta">
              <span><i class="fa fa-book"></i> <?php echo (int) $s['subjects']; ?> subject(s)</span>
              <span><i class="fa fa-users"></i> <?php echo (int) $s['students']; ?> student(s)</span>
            </span>
            <input type="hidden" name="section_order[]" value="<?php echo (int) $s['id']; ?>">
            <span class="ms-move">
              <button type="button" class="btn btn-default btn-sm ms-btn js-move" data-dir="-1" <?php echo $i === 0 ? 'disabled' : ''; ?>
                      aria-label="Move <?php echo e($s['name']); ?> up" title="Move up">
                <i class="fa fa-arrow-up" aria-hidden="true"></i>
              </button>
              <button type="button" class="btn btn-default btn-sm ms-btn js-move" data-dir="1" <?php echo $i === $total_sections - 1 ? 'disabled' : ''; ?>
                      aria-label="Move <?php echo e($s['name']); ?> down" title="Move down">
                <i class="fa fa-arrow-down" aria-hidden="true"></i>
              </button>
            </span>
            <span class="ms-actions">
              <a class="btn btn-info btn-sm" href="<?php echo BASE_URL; ?>subject_drag.php?class_id=<?php echo (int) $class_id; ?>&section_id=<?php echo (int) $s['id']; ?>" title="Order subjects in this section">
                <i class="fa fa-sort" aria-hidden="true"></i>
              </a>
              <a class="btn btn-success btn-sm" href="<?php echo BASE_URL; ?>manage_sections.php?class_id=<?php echo (int) $class_id; ?>&edit_section=<?php echo (int) $s['id']; ?>#ms_edit" title="Edit section">
                <i class="fa fa-edit" aria-hidden="true"></i>
              </a>
              <a class="btn btn-danger btn-sm" title="Delete section"
                 onclick="return confirm('Delete section \'<?php echo e(addslashes($s['name'])); ?>\'? Its subjects, students and timetable entries will also be removed.');"
                 href="<?php echo BASE_URL; ?>manage_sections.php?class_id=<?php echo (int) $class_id; ?>&delete_section=<?php echo (int) $s['id']; ?>&confirm_delete=1">
                <i class="fa fa-trash" aria-hidden="true"></i>
              </a>
            </span>
          </li>
          <?php endforeach; ?>
        </ol>
        <div class="ms-footer">
          <button type="submit" class="btn btn-success"><i class="fa fa-save" aria-hidden="true"></i> Save Order</button>
          <a href="<?php echo BASE_URL; ?>manage_classes.php" class="btn btn-default">Back to Class Management</a>
        </div>
      </form>
    <?php endif; ?>

    <div class="ms-add" id="ms_edit">
      <form method="post" action="" style="display:contents;">
        <input type="hidden" name="class_id" value="<?php echo (int) $class_id; ?>">
        <?php if ($edit_id > 0): ?>
          <input type="hidden" name="action" value="UpdateSection">
          <input type="hidden" name="section_id" value="<?php echo (int) $edit_id; ?>">
          <input type="text" name="section_name" class="inputfield" required maxlength="50"
                 placeholder="Section name" value="<?php echo e($edit_name); ?>" autofocus>
          <button type="submit" class="btn btn-warning"><i class="fa fa-check" aria-hidden="true"></i> Update Section</button>
          <a href="<?php echo BASE_URL; ?>manage_sections.php?class_id=<?php echo (int) $class_id; ?>" class="btn btn-default">Cancel</a>
        <?php else: ?>
          <input type="hidden" name="action" value="AddSection">
          <input type="text" name="section_name" class="inputfield" required maxlength="50" placeholder="New section name, e.g. A">
          <button type="submit" class="btn btn-primary"><i class="fa fa-plus" aria-hidden="true"></i> Add Section</button>
        <?php endif; ?>
      </form>
    </div>
  </div>
<?php endif; ?>

<script>
(function () {
    /* delete is a GET link, so honour it only when it was explicitly confirmed */
    (function () {
        var u = new URL(window.location.href);
        if (!u.searchParams.get('confirm_delete')) return;
        if (u.searchParams.get('delete_section')) {
            var f = document.createElement('form');
            f.method = 'post';
            f.innerHTML = '<input type="hidden" name="action" value="DeleteSection">' +
                          '<input type="hidden" name="class_id" value="' + (u.searchParams.get('class_id') || '0') + '">' +
                          '<input type="hidden" name="section_id" value="' + (u.searchParams.get('delete_section') || '0') + '">';
            document.body.appendChild(f);
            f.submit();
        }
    })();

    var list = document.querySelector('.ms-list');
    if (!list) return;

    function renumber() {
        var items = list.querySelectorAll('.ms-item');
        items.forEach(function (li, idx) {
            li.querySelector('.ms-num').textContent = idx + 1;
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
        var li = btn.closest('.ms-item');
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
