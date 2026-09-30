<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Manage Exams';

if (!function_exists('hiifi_ensure_exam_schema')) {
    function hiifi_ensure_exam_schema() {
        $queries = [
            "ALTER TABLE exams ADD COLUMN IF NOT EXISTS session VARCHAR(50) DEFAULT NULL AFTER exam_name",
            "ALTER TABLE exams ADD COLUMN IF NOT EXISTS exam_type VARCHAR(20) DEFAULT NULL AFTER session",
            "ALTER TABLE exams ADD COLUMN IF NOT EXISTS display_mobile VARCHAR(3) DEFAULT 'YES' AFTER exam_type",
            "CREATE TABLE IF NOT EXISTS datesheet (
                id INT AUTO_INCREMENT PRIMARY KEY,
                exam_id INT NOT NULL,
                class_id INT NOT NULL,
                section_id INT DEFAULT NULL,
                subject_id INT NOT NULL,
                exam_date DATE DEFAULT NULL,
                exam_time VARCHAR(80) DEFAULT NULL,
                UNIQUE KEY uq_ds (exam_id, class_id, section_id, subject_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS syllabus_entry (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_id INT NOT NULL,
                section_id INT NOT NULL DEFAULT 0,
                subject_id INT NOT NULL,
                term_id INT NOT NULL,
                syllabus TEXT,
                UNIQUE KEY uq_syl (class_id, section_id, subject_id, term_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($queries as $sql) {
            try { db_query($sql); } catch (Throwable $e) {}
        }
        try {
            $res = db_query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'datesheet\\_%'");
            if ($res) {
                $ins = db_prepare("INSERT INTO datesheet (exam_id, class_id, section_id, subject_id, exam_date, exam_time) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE exam_date=VALUES(exam_date), exam_time=VALUES(exam_time)");
                while ($row = $res->fetch_assoc()) {
                    $parts = explode('_', $row['setting_key']);
                    if (count($parts) < 4) continue;
                    $exam = (int)$parts[1];
                    $class = (int)$parts[2];
                    $sub = (int)$parts[3];
                    if ($exam <= 0 || $class <= 0 || $sub <= 0) continue;
                    $v = explode('|', $row['setting_value']);
                    $date = $v[0] ?? null;
                    $time = $v[1] ?? null;
                    $sec = null;
                    $ins->bind_param('iiisss', $exam, $class, $sec, $sub, $date, $time);
                    try { $ins->execute(); } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {}
    }
}
hiifi_ensure_exam_schema();

$message = '';
$error = '';
$flash = $_SESSION['hiifi_flash'] ?? null;
unset($_SESSION['hiifi_flash']);
if ($flash) {
    if ($flash['type'] === 'error') { $error = $flash['msg']; } else { $message = $flash['msg']; }
}

$sessions = [];
for ($y = 2018; $y <= 2030; $y++) { $sessions[] = $y . '-' . ($y + 1); }
$cur_session = get_setting('session_year', '2026-2027');
if (!in_array($cur_session, $sessions, true)) { array_unshift($sessions, $cur_session); }

$exam_types = ['Class Test', 'Term Exam', 'Assesments'];

function hiifi_exam_type_label($t) {
    if ($t === '') return '-';
    $map = ['Class Test' => 'Class Test (CT)', 'Term Exam' => 'Term Exam (TE)', 'Assesments' => 'Assesments (AS)'];
    return $map[$t] ?? $t;
}

$f_session = trim($_REQUEST['session'] ?? '');
$f_type = trim($_REQUEST['type_id'] ?? '');
$f_type = ($f_type === 'All') ? '' : $f_type;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddExam' || $action === 'UpdateExam') {
        $exam_id = (int)($_POST['exam_id'] ?? 0);
        $name = trim($_POST['exam_name'] ?? '');
        $session = trim($_POST['session'] ?? '');
        $exam_type = trim($_POST['exam_type'] ?? '');
        $exam_date = trim($_POST['exam_date'] ?? '');
        $display_mobile = strtoupper(trim($_POST['display_mobile'] ?? 'YES'));
        $display_mobile = ($display_mobile === 'NO') ? 'NO' : 'YES';
        if ($name === '' || $session === '' || $exam_type === '') {
            $error = 'Exam title, session and exam type are required.';
        } else {
            try {
                if ($action === 'UpdateExam' && $exam_id > 0) {
                    $stmt = db_prepare("UPDATE exams SET exam_name=?, session=?, exam_type=?, display_mobile=?, exam_date=? WHERE exam_id=?");
                    $ed = $exam_date !== '' ? $exam_date : null;
                    $stmt->bind_param('sssssi', $name, $session, $exam_type, $display_mobile, $ed, $exam_id);
                    $stmt->execute();
                    $message = 'Exam updated successfully!';
                } else {
                    $stmt = db_prepare("INSERT INTO exams (exam_name, session, exam_type, display_mobile, exam_date, status) VALUES (?, ?, ?, ?, ?, 1)");
                    $ed = $exam_date !== '' ? $exam_date : null;
                    $stmt->bind_param('sssss', $name, $session, $exam_type, $display_mobile, $ed);
                    $stmt->execute();
                    $message = 'Exam added successfully!';
                }
            } catch (Throwable $e) {
                $error = 'Save failed: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'DeleteExam') {
        $exam_id = (int)($_POST['exam_id'] ?? 0);
        if ($exam_id > 0) {
            try {
                $st = db_prepare("DELETE FROM marks WHERE exam_id=?");
                $st->bind_param('i', $exam_id);
                $st->execute();
                $st = db_prepare("DELETE FROM datesheet WHERE exam_id=?");
                $st->bind_param('i', $exam_id);
                $st->execute();
                $st = db_prepare("DELETE FROM exams WHERE exam_id=?");
                $st->bind_param('i', $exam_id);
                $st->execute();
                $message = 'Exam deleted. Associated marksheets were removed.';
            } catch (Throwable $e) {
                $error = 'Delete failed: ' . $e->getMessage();
            }
        }
    }
}

$exams = [];
$f_session = trim($_REQUEST['session'] ?? '');
$f_type_raw = trim($_REQUEST['type_id'] ?? '');
$f_type = ($f_type_raw === 'All') ? '' : $f_type_raw;

$sql = "SELECT e.* FROM exams e";
$where = [];
$params = [];
if ($f_session !== '') { $where[] = "e.session = ?"; $params[] = $f_session; }
if ($f_type !== '') { $where[] = "e.exam_type = ?"; $params[] = $f_type; }
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= " ORDER BY e.exam_date DESC, e.exam_id DESC";
try {
    if ($params) {
        $stmt = db_prepare($sql);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = db_query($sql);
    }
    while ($row = $res->fetch_assoc()) { $exams[] = $row; }
} catch (Throwable $e) {
    $exams = [];
}

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
/* The form is a flex row, so the children are sized with flex, not bootstrap grid
   columns. col-md-1 (8.33%) squeezed the Filter button until its label was cut off. */
.sbs-field { flex:1 1 190px; min-width:0; margin:0; }
.sbs-field > label { display:block; margin:0 0 6px; }
.sbs-submit { flex:0 0 auto; margin:0; }
.sbs-submit .btn { min-width:112px; white-space:nowrap; }
@media (max-width:575px) {
  .sbs-field, .sbs-submit { flex:1 1 100%; }
  .sbs-submit .btn { width:100%; }
}
.crumb { font-size:13px; color:#6B7280; margin:6px 4px 14px; }
.crumb a { color:#e67e22; text-decoration:none; }
.crumb a:hover { text-decoration:underline; }
.panel-white { background:#fff; border:1px solid #E5E7EB; border-radius:14px; }
.btn-soft { background:#fff; border:1px solid #E5E7EB; color:#374151; }
.btn-soft:hover { background:#fff4e6; border-color:#ffd8b3; color:#e67e22; }
.modal-header .close { font-size:26px; opacity:.5; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px 6px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-pencil-square"></i> Manage Exams</h3>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#AddExams" style="color:#fff;"><i class="fa fa-plus"></i> Add New Exam</button>
        </div>
        <div class="crumb"><a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; <a href="<?php echo BASE_URL; ?>manage_exams.php">Examination</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; Manage Exams (<?php echo count($exams); ?> records)</div>

        <?php include __DIR__ . '/includes/academic_tabs.php'; ?>

        <form method="get" action="manage_exams.php" class="search-bar-student">
            <div class="sbs-field">
                <label for="flt_session">Session</label>
                <select name="session" id="flt_session" class="form-control form-select2">
                    <option value="">Select Session</option>
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?php echo e($s); ?>" <?php echo $f_session === $s ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sbs-field">
                <label for="flt_type">Exam Type</label>
                <select name="type_id" id="flt_type" class="form-control">
                    <option value="All">All</option>
                    <?php foreach ($exam_types as $t): ?>
                        <option value="<?php echo e($t); ?>" <?php echo $f_type === $t ? 'selected' : ''; ?>><?php echo e($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sbs-submit">
                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
            </div>
        </form>

        <div class="panel-white" style="overflow-x:auto;">
            <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead>
                    <tr>
                        <th style="text-align:center; width:50px;">S.No</th>
                        <th>Exam Title</th>
                        <th style="text-align:center;">Display on Mobile App</th>
                        <th style="text-align:center;">Exam Type</th>
                        <th style="text-align:center;">Exam Date</th>
                        <th style="text-align:center;">Session</th>
                        <th style="text-align:center; width:120px;">Enter Marks</th>
                        <th style="text-align:center; width:90px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($exams) === 0): ?>
                        <tr><td colspan="8" style="text-align:center; color:#6B7280; padding:35px;">
                            <i class="fa fa-inbox" style="font-size:28px; display:block; margin-bottom:8px; color:#D1D5DB;"></i>
                            No exams found<?php echo ($f_session !== '' || $f_type !== '') ? ' for the selected filters' : ' yet'; ?>. Click "Add New Exam" to create one.
                        </td></tr>
                    <?php endif; ?>
                    <?php $i = 1; ?>
                    <?php foreach ($exams as $x): ?>
                        <tr>
                            <td style="text-align:center;"><?php echo $i++; ?></td>
                            <td><strong><?php echo e($x['exam_name']); ?></strong></td>
                            <td style="text-align:center;">
                                <?php $dm = strtoupper($x['display_mobile'] ?? 'YES'); ?>
                                <span class="status-badge <?php echo $dm === 'YES' ? 'status-present' : 'status-absent'; ?>" style="padding:4px 10px;"><?php echo $dm === 'YES' ? 'Yes' : 'No'; ?></span>
                            </td>
                            <td style="text-align:center;"><?php echo e(hiifi_exam_type_label($x['exam_type'] ?? '')); ?></td>
                            <td style="text-align:center;"><?php echo $x['exam_date'] ? date('d M Y', strtotime($x['exam_date'])) : '-'; ?></td>
                            <td style="text-align:center;"><?php echo e($x['session'] ?? '-'); ?></td>
                            <td style="text-align:center;">
                                <a href="<?php echo BASE_URL; ?>add_marks.php?exam_id=<?php echo (int)$x['exam_id']; ?>" class="btn btn-primary btn-xs" style="color:#fff;"><i class="fa fa-edit"></i> Enter Marks</a>
                            </td>
                            <td style="text-align:center; white-space:nowrap;">
                                <a data-toggle="modal" data-target="#EditExam<?php echo (int)$x['exam_id']; ?>" class="btn btn-success btn-xs" title="Edit" style="color:#fff;"><i class="fa fa-pencil"></i></a>
                                <form method="post" action="manage_exams.php" style="display:inline;" onsubmit="return confirm('Deleting this record will permanently remove all associated marksheets for this exam. Are you sure you want to continue?');">
                                    <input type="hidden" name="action" value="DeleteExam">
                                    <input type="hidden" name="exam_id" value="<?php echo (int)$x['exam_id']; ?>">
                                    <?php if ($f_session !== ''): ?><input type="hidden" name="session" value="<?php echo e($f_session); ?>"><?php endif; ?>
                                    <?php if ($f_type !== ''): ?><input type="hidden" name="type_id" value="<?php echo e($f_type); ?>"><?php endif; ?>
                                    <button class="btn btn-danger btn-xs" title="Delete"><i class="fa fa-trash-o"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($exams as $x): ?>
    <div id="EditExam<?php echo (int)$x['exam_id']; ?>" class="modal fade" role="dialog">
        <div class="modal-dialog" style="width:600px; max-width:95%;">
            <div class="modal-content">
                <div class="modal-header" style="border-bottom:1px solid #E5E7EB;">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" style="text-align:center; font-weight:700;">Edit Exam</h4>
                </div>
                <div class="modal-body">
                    <form method="post" action="manage_exams.php">
                        <input type="hidden" name="action" value="UpdateExam">
                        <input type="hidden" name="exam_id" value="<?php echo (int)$x['exam_id']; ?>">
                        <?php if ($f_session !== ''): ?><input type="hidden" name="session" value="<?php echo e($f_session); ?>"><?php endif; ?>
                        <?php if ($f_type !== ''): ?><input type="hidden" name="type_id" value="<?php echo e($f_type); ?>"><?php endif; ?>
                        <div class="form-group">
                            <label class="required">Session</label>
                            <select name="session" class="form-control" required>
                                <option value="">Select Session</option>
                                <?php foreach ($sessions as $s): ?>
                                    <option value="<?php echo e($s); ?>" <?php echo $x['session'] === $s ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="required">Exam Type</label>
                            <select name="exam_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <?php foreach ($exam_types as $t): ?>
                                    <option value="<?php echo e($t); ?>" <?php echo $x['exam_type'] === $t ? 'selected' : ''; ?>><?php echo e($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="required">Display on Mobile App</label>
                            <select name="display_mobile" class="form-control">
                                <option value="YES" <?php echo strtoupper($x['display_mobile'] ?? 'YES') === 'YES' ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo strtoupper($x['display_mobile'] ?? '') === 'NO' ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="">Exam Date</label>
                            <input type="date" class="form-control" name="exam_date" value="<?php echo e($x['exam_date'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="required" for="">Paper/Exam Title</label>
                            <input type="text" class="form-control" name="exam_name" value="<?php echo e($x['exam_name']); ?>" placeholder="Weekly Test etc..." maxlength="100" required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%; color:#fff;"><i class="fa fa-save"></i> Save Record</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div id="AddExams" class="modal fade" role="dialog">
    <div class="modal-dialog" style="width:600px; max-width:95%;">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" style="text-align:center; font-weight:700;">Add New Exam</h4>
            </div>
            <div class="modal-body">
                <form method="post" action="manage_exams.php">
                    <input type="hidden" name="action" value="AddExam">
                    <?php if ($f_session !== ''): ?><input type="hidden" name="session" value="<?php echo e($f_session); ?>"><?php endif; ?>
                    <?php if ($f_type !== ''): ?><input type="hidden" name="type_id" value="<?php echo e($f_type); ?>"><?php endif; ?>
                    <div class="form-group">
                        <label class="required">Session</label>
                        <select name="session" class="form-control" required>
                            <option value="">Select Session</option>
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?php echo e($s); ?>" <?php echo ($f_session ?: $cur_session) === $s ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">Exam Type</label>
                        <select name="exam_type" id="type_id" class="form-control" required>
                            <option value="">Select Type</option>
                            <?php foreach ($exam_types as $t): ?>
                                <option value="<?php echo e($t); ?>"><?php echo e($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">Display on Mobile App</label>
                        <select name="display_mobile" class="form-control">
                            <option value="YES" selected>YES</option>
                            <option value="NO">NO</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="">Exam Date</label>
                        <input type="date" class="form-control" name="exam_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="required" for="">Paper/Exam Title</label>
                        <input type="text" class="form-control" name="exam_name" placeholder="Weekly Test etc..." maxlength="100" autofocus required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; color:#fff;"><i class="fa fa-save"></i> Save Record</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    function initFormSelect2(){
        var S2 = (typeof window.Select2 === 'function') ? window.Select2 : ((window.jQuery && jQuery.fn && jQuery.fn.select2) ? jQuery.fn.select2 : null);
        if (!S2 || !window.jQuery) return;
        jQuery('.form-select2').each(function(){ try { S2.call(jQuery(this), { width: '100%' }); } catch (e) {} });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initFormSelect2); } else { initFormSelect2(); }
    window.addEventListener('load', initFormSelect2);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>