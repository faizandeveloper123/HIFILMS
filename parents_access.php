<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = "Create Parent's Login IDs";

db_query("CREATE TABLE IF NOT EXISTS parent_access (
    access_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    username VARCHAR(191),
    password VARCHAR(255),
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$message = '';
$error = '';

function hifi_password($name) {
    $prefix = strtoupper(substr(trim($name), 0, 3));
    if ($prefix === '') { $prefix = 'PAR'; }
    return $prefix . rand(100000, 999999);
}

$sel_class = (int) ($_GET['class_id'] ?? 0);

$existing = [];
$res = db_query("SELECT student_id, username, password, status FROM parent_access");
while ($row = $res->fetch_assoc()) { $existing[(int) $row['student_id']] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'generate') {
        $student_ids = $_POST['student_ids'] ?? [];
        if (!is_array($student_ids)) { $student_ids = []; }
        $ids = [];
        foreach ($student_ids as $sid) {
            $sid = (int) $sid;
            if ($sid > 0) { $ids[] = $sid; }
        }
        if (count($ids) === 0) {
            $error = 'Please select at least one parent to create login IDs.';
        } else {
            $place = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $st = db_prepare("SELECT s.student_id, s.first_name, s.last_name, s.father_name, s.email, s.phone, s.father_cellno
                              FROM students s WHERE s.student_id IN ($place)");
            $st->bind_param($types, ...$ids);
            $st->execute();
            $r = $st->get_result();
            $rowList = [];
            while ($row = $r->fetch_assoc()) { $rowList[$row['student_id']] = $row; }
            $created = 0;
            $skipped = 0;
            $used = [];
            foreach ($ids as $sid) {
                if (isset($existing[$sid])) { $skipped++; continue; }
                if (!isset($rowList[$sid])) { continue; }
                $st = $rowList[$sid];
                $username = '';
                if (!empty($st['father_cellno'])) { $username = $st['father_cellno']; }
                elseif (!empty($st['phone'])) { $username = $st['phone']; }
                elseif (!empty($st['email'])) { $username = $st['email']; }
                else { $username = 'parent' . $sid; }
                if (in_array($username, $used, true)) { $username = $username . '-' . $sid; }
                $used[] = $username;
                $password = hifi_password($st['father_name']);
                $ins = db_prepare("INSERT INTO parent_access (student_id, username, password, status) VALUES (?, ?, ?, 1)");
                $ins->bind_param('iss', $sid, $username, $password);
                $ins->execute();
                $created++;
                $existing[$sid] = ['student_id' => $sid, 'username' => $username, 'password' => $password, 'status' => 1];
            }
            $message = 'Login IDs generated for <strong>' . $created . '</strong> parent(s). Skipped <strong>' . $skipped . '</strong> already having credentials.';
        }
    }
}

$classes = [];
$res = db_query("SELECT class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$students = [];
if ($sel_class > 0) {
    $st = db_prepare("SELECT s.student_id, s.first_name, s.last_name, s.father_name, s.phone, s.father_cellno, s.whatsapp_number, s.email,
                      s.gr_no, s.class_id, s.section_id, c.class_name, sec.section_name
                      FROM students s
                      LEFT JOIN classes c ON s.class_id = c.class_id
                      LEFT JOIN sections sec ON s.section_id = sec.section_id
                      WHERE s.class_id = ? AND s.status = 1 ORDER BY s.first_name");
    $st->bind_param('i', $sel_class);
    $st->execute();
    $r = $st->get_result();
} else {
    $r = db_query("SELECT s.student_id, s.first_name, s.last_name, s.father_name, s.phone, s.father_cellno, s.whatsapp_number, s.email,
                   s.gr_no, s.class_id, s.section_id, c.class_name, sec.section_name
                   FROM students s
                   LEFT JOIN classes c ON s.class_id = c.class_id
                   LEFT JOIN sections sec ON s.section_id = sec.section_id
                   WHERE s.status = 1 ORDER BY s.first_name");
}
while ($row = $r->fetch_assoc()) { $students[] = $row; }

$childrenMap = [];
foreach ($students as $st) {
    $key = trim(($st['father_cellno'] ?: $st['phone'] ?: '') . '|' . $st['father_name']);
    if ($key === '|') { $key = 'single|' . $st['student_id']; }
    $childrenMap[$key][] = $st;
}
$childrenCount = [];
foreach ($students as $st) {
    $key = trim(($st['father_cellno'] ?: $st['phone'] ?: '') . '|' . $st['father_name']);
    if ($key === '|') { $key = 'single|' . $st['student_id']; }
    $childrenCount[$st['student_id']] = count($childrenMap[$key]);
}

include __DIR__ . '/includes/header.php';
?>
<style>
.container1 { display: block; position: relative; padding-left: 22px; margin-bottom: 0; cursor: pointer; }
.container1 input { position: absolute; opacity: 0; }
.container1 .checkmark { position: absolute; top: 2px; left: 0; height: 16px; width: 16px; border: 1px solid #d1d5db; background-color: #fff; border-radius: 4px; }
.container1 input:checked ~ .checkmark { background-color: #4f46e5; border-color: #4f46e5; }
.pid-pass-mask { font-family: monospace; letter-spacing: 1px; }
.pid-eye-toggle { cursor: pointer; color: #8a94a6; margin-left: 6px; }
.pid-eye-toggle:hover { color: #4f46e5; }
.cred-line { font-size: 11.5px; color: #6b7280; }
</style>

<div class="main-content">
    <div class="container-fluid">

        <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i>  &nbsp; Parents Portal &nbsp; <i style="" class="fa fa-angle-double-right"></i>
        &nbsp; Create Parent's Login IDs

        <a href="<?php echo BASE_URL; ?>parents_id.php" class="btn btn-round btn-info quiklink pull-right"> View Parents Login IDs </a>
        <a href="<?php echo BASE_URL; ?>parents_id.php?print=1" class="btn btn-round btn-info quiklink pull-right"> Print Parents Login IDs </a>

        <br>

        <br><br>

        <div class="row" style="background-color: white;">

            <?php if ($message !== ''): ?>
                <div class="alert alert-success">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fa fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fa fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <section class="add_sub_agent" id="table_sub_agent">
                <div class="container">

                    <div class="panel-body">
                        <div class="col-md-12">
                            <form class="form-inline" method="get" action="<?php echo BASE_URL; ?>parents_access.php">
                                <div class="form-group" style="margin-right:8px;">
                                    <label class="required">Class</label>
                                    <select name="class_id" class="form-control" style="min-width:220px;">
                                        <option value="">All Classes</option>
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?php echo $c['class_id']; ?>" <?php echo $sel_class == $c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="hidden" name="addaccountAdmin" value="1">
                                <button type="submit" class="btn btn-primary" style="margin-top:22px;">Search</button>
                            </form>
                        </div>
                        <div class="clearfix"></div>
                    </div>

                    <form class="form-style-7" action="<?php echo BASE_URL; ?>parents_access.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="generate">
                        <?php if ($sel_class > 0): ?>
                            <input type="hidden" name="class_id" value="<?php echo $sel_class; ?>">
                        <?php endif; ?>

                        <input type="submit" name="submit" style="" onClick="return confirmation()" class="pull-right btn btn-primary" value="Create Login IDs">

                        <table id="listofstudents" data-page-length='100' class="table table-striped table-bordered" style="width:100%">

                            <thead>
                                <tr>
                                    <th width="5%">S.No</th>
                                    <th width="15%">Parents Name</th>
                                    <th width="10%"> Cell No </th>
                                    <th width="10%">No Of Children</th>
                                    <th width="10%"> View Children </th>
                                    <th width="10%">Access</th>
                                    <th width="5%">
                                        <label class="container1"><input type="checkbox" id="checkAll"/><span class="checkmark" style="margin-top: -10px;"></span></label>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($students) === 0): ?>
                                    <tr><td colspan="7" style="text-align:center; padding:30px; color:#6b7280;">No students found.</td></tr>
                                <?php endif; ?>
                                <?php $sn = 0; foreach ($students as $st):
                                    $sn++;
                                    $cell = $st['father_cellno'] ?: $st['phone'];
                                    $ex = $existing[$st['student_id']] ?? null;
                                    $name = trim($st['father_name'] ?: '') !== '' ? $st['father_name'] : trim($st['first_name'] . ' ' . $st['last_name']);
                                ?>
                                <tr>
                                    <td style="text-align:center; padding: 2px;"><?php echo $sn; ?></td>
                                    <td style="padding: 2px;" >
                                        <?php echo e($name); ?>
                                        <div class="cred-line">
                                            <?php if ($ex): ?>
                                                Username: <strong><?php echo e($ex['username']); ?></strong><br>
                                                Password: <span class="pid-pass-mask" data-real="<?php echo e($ex['password']); ?>">••••••</span>
                                                <i class="fa fa-eye pid-eye-toggle" title="Show/Hide password"></i>
                                            <?php else: ?>
                                                Username: --<br>Password: --
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo e($cell ?: '--'); ?></td>
                                    <td style="padding: 2px; text-align: center;"> <?php echo $childrenCount[$st['student_id']]; ?> </td>
                                    <td style="text-align:center;">
                                        <a data-toggle="modal" data-target="#parentchild<?php echo $st['student_id']; ?>" style="padding: 0px 5px; font-size:14px; width: 80px;" class="btn btn-success">
                                            View
                                        </a>

                                        <div id="parentchild<?php echo $st['student_id']; ?>" class="modal fade" role="dialog">
                                            <div class="modal-dialog">
                                                <div class="modal-content" style="width: 900px;margin-left: -25%;">
                                                    <div class="modal-header">
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                        <h4 class="modal-title"> List Of Parents Child </h4>
                                                    </div>
                                                    <div class="modal-body"></div>
                                                    <br>
                                                    <div class="col-md-12">
                                                        <table border="1" width="100%" data-page-length='100'>
                                                            <tr>
                                                                <th class="thStyle" style="text-align: center;padding:1%;"> Student Name  </th>
                                                                <th class="thStyle" style="text-align: center;padding:1%;"> Father Name </th>
                                                                <th class="thStyle" style="text-align: center;padding:1%;"> Class/Sec </th>
                                                                <th class="thStyle" style="text-align: center;padding:1%;"> GR. No </th>
                                                                <th class="thStyle" style="text-align: center;padding:1%;"> Cell No </th>
                                                            </tr>
                                                            <?php $k = trim(($st['father_cellno'] ?: $st['phone'] ?: '') . '|' . $st['father_name']);
                                                                  if ($k === '|') { $k = 'single|' . $st['student_id']; }
                                                                  foreach ($childrenMap[$k] as $ch): ?>
                                                                <tr>
                                                                    <td><?php echo e(trim($ch['first_name'] . ' ' . $ch['last_name'])); ?></td>
                                                                    <td><?php echo e($ch['father_name'] ?? ''); ?></td>
                                                                    <td><?php echo e($ch['class_name'] ?? ''); ?><?php echo !empty($ch['section_name']) ? '-' . e($ch['section_name']) : ''; ?></td>
                                                                    <td><?php echo e($ch['gr_no'] ?? ''); ?></td>
                                                                    <td><?php echo e($ch['father_cellno'] ?: $ch['phone'] ?: ''); ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </table>
                                                    </div>
                                                    <br><br>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td >
                                        <?php if ($ex): ?>
                                            <a style="padding: 0px 5px; font-size:14px; width: 80px;" href="#" class="btn btn-success"> Yes </a>
                                        <?php else: ?>
                                            <a style="padding: 0px 5px; font-size:14px; width: 80px;" href="#" class="btn btn-danger"> No </a>
                                        <?php endif; ?>
                                    </td>

                                    <td style="padding: 2px;">
                                        <input type="hidden" name="last_name<?php echo $st['student_id']; ?>" value="<?php echo e($name); ?>" />
                                        <input type="hidden" name="cell_no<?php echo $st['student_id']; ?>" value="<?php echo e($cell ?: ''); ?>" />
                                        <input type="hidden" name="parent_password<?php echo $st['student_id']; ?>" value="<?php echo $ex ? e($ex['password']) : ''; ?>" />
                                        <label class="container1">
                                            <input type="checkbox" name="student_ids[]" value="<?php echo $st['student_id']; ?>" <?php echo $ex ? '' : 'checked'; ?>><span class="checkmark"></span>
                                        </label>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>

                        </table>

                    </form>

                    <br><br>

                </div>
                <div class="clearfix"></div>
            </section>

        </div>
    </div>
</div>

<script>
function confirmation() {
    var checked = document.querySelectorAll('input[name="student_ids[]"]:checked');
    if (checked.length === 0) {
        alert('Please select at least one parent to create login IDs.');
        return false;
    }
    return confirm('Create Login IDs for ' + checked.length + ' selected parent(s)?');
}
$("#checkAll").change(function () {
    $("input[name='student_ids[]']").prop('checked', $(this).prop("checked"));
});
document.querySelectorAll('.pid-eye-toggle').forEach(function(icon) {
    icon.addEventListener('click', function() {
        var span = this.previousElementSibling;
        if (span.textContent === '••••••') {
            span.textContent = span.getAttribute('data-real');
            this.classList.remove('fa-eye');
            this.classList.add('fa-eye-slash');
        } else {
            span.textContent = '••••••';
            this.classList.remove('fa-eye-slash');
            this.classList.add('fa-eye');
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>