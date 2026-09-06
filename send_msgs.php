<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Instant Attendance SMS';

$message = '';
$error = '';

$status_map = ['A' => 'absent', 'L' => 'leave', 'LA' => 'late', 'SL' => 'short_leave'];
$status_label = ['A' => 'Absent', 'L' => 'Leave', 'LA' => 'Late', 'SL' => 'Short Leave'];
$status_badge = ['A' => '#DC2626', 'L' => '#2563EB', 'LA' => '#D97706', 'SL' => '#D97706'];

$heads = [];
$r = db_query("SELECT class_head_id, class_head_name FROM class_heads WHERE status = 1 ORDER BY class_head_name");
while ($row = $r->fetch_assoc()) { $heads[] = $row; }

$classes = [];
$r = db_query("SELECT class_id, class_name FROM classes WHERE status = 1 ORDER BY class_name");
while ($row = $r->fetch_assoc()) { $classes[] = $row; }

$sel_head = (int) ($_GET['class_head'] ?? 0);
$sel_class = (int) ($_GET['class_id'] ?? 0);
$sel_section = $_GET['section'] ?? 'All';
$sel_attendance = $_GET['attendance'] ?? 'A';
if (!isset($status_map[$sel_attendance])) { $sel_attendance = 'A'; }
$sel_date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sel_date)) { $sel_date = date('Y-m-d'); }

$sections = [];
if ($sel_class > 0) {
    $r = db_query("SELECT section_id, section_name FROM sections WHERE class_id = " . (int) $sel_class . " ORDER BY section_name");
    while ($row = $r->fetch_assoc()) { $sections[] = $row; }
}

$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'SendAttendanceSMS') {
    $ids = [];
    if (!empty($_POST['checkboxes']) && is_array($_POST['checkboxes'])) {
        foreach ($_POST['checkboxes'] as $id) {
            $id = (int) $id;
            if ($id > 0) { $ids[] = $id; }
        }
    }
    $ids = array_values(array_unique($ids));
    if (count($ids) === 0) {
        $error = 'Please select at least one student to send SMS';
    } else {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $sql = "SELECT s.student_id, s.first_name, s.last_name, cl.class_name, sec.section_name, a.date, a.status,
                       COALESCE(NULLIF(TRIM(s.father_cellno), ''), NULLIF(TRIM(s.phone), ''), '') AS cellno
                FROM students s
                LEFT JOIN classes cl ON s.class_id = cl.class_id
                LEFT JOIN sections sec ON s.section_id = sec.section_id
                LEFT JOIN attendance a ON a.student_id = s.student_id AND a.date = ?
                WHERE s.student_id IN ($in)";
        $st = db_prepare($sql);
        $st->bind_param('s' . $types, $sel_date, ...$ids);
        $st->execute();
        $res = $st->get_result();
        $students = [];
        while ($row = $res->fetch_assoc()) { $students[] = $row; }

        $inserted = 0;
        foreach ($students as $s) {
            $sname = trim($s['first_name'] . ' ' . ($s['last_name'] ?? ''));
            $cls = $s['class_name'] ? $s['class_name'] . ($s['section_name'] ? ' (' . $s['section_name'] . ')' : '') : '-';
            $sstatus = isset($status_label[$s['status']]) ? $status_label[$s['status']] : ucfirst($s['status'] ?? '');
            $sdate = $s['date'] ? $s['date'] : $sel_date;
            $body = "Dear Parent, this is to inform you about your child's attendance.\n" .
                    "Student: " . $sname . "\nClass: " . $cls . "\nDate: " . date('d-M-Y', strtotime($sdate)) .
                    "\nStatus: " . $sstatus . "\n\nRegards,\n" . get_setting('school_name', 'School') . " Administration";
            $title = 'Attendance SMS - ' . $sname;
            $st2 = db_prepare("INSERT INTO messages (title, message, recipient_type, created_by, created_at, channel, recipient_list, status, message_type, template_title)
                               VALUES (?, ?, 'student', ?, NOW(), 'sms', ?, 'queued', 'attendance', 'Attendance SMS')");
            $st2->bind_param('ssis', $title, $body, $_SESSION['user_id'], $s['student_id']);
            $st2->execute();
            $inserted++;
        }
        $message = $inserted . ' SMS message(s) recorded successfully for ' . date('d-M-Y', strtotime($sel_date)) . '.';
    }
}

$records = [];
$where = [];
$params = [];
$types = '';
if ($sel_class > 0) {
    $where[] = "s.class_id = ?";
    $params[] = $sel_class;
    $types .= 'i';
}
if ($sel_section !== '' && $sel_section !== 'All') {
    $where[] = "s.section_id = ?";
    $params[] = (int) $sel_section;
    $types .= 'i';
}
if ($sel_head > 0) {
    $where[] = "s.class_id IN (SELECT class_id FROM classes WHERE class_head_id = ?)";
    $params[] = $sel_head;
    $types .= 'i';
}
$where[] = "a.status = ?";
$params[] = $status_map[$sel_attendance];
$types .= 's';
$where[] = "a.date = ?";
$params[] = $sel_date;
$types .= 's';

$sql = "SELECT a.student_id, s.gr_no, s.first_name, s.last_name, s.father_cellno, s.phone, s.class_id, s.section_id,
               cl.class_name, sec.section_name, a.date, a.status
        FROM attendance a
        JOIN students s ON a.student_id = s.student_id
        LEFT JOIN classes cl ON s.class_id = cl.class_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.gr_no";
$st = db_prepare($sql);
$st->bind_param($types, ...$params);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) { $records[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<div class="right_col" role="main">
    <div class="" style="background-color: white;margin-top:0px;">

        <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i>  &nbsp;
        <a href="<?php echo BASE_URL; ?>mark_attendanceReport_list.php">Attendance Reports </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i>  &nbsp;
        Instant Attendance SMS (<?php echo count($records); ?> records)

        <br><br>

        <?php if ($message !== ''): ?>
            <div class="alert alert-success alert-dismissible"><a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a><i class="fa fa-check"></i> <?php echo e($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alert-dismissible"><a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a><i class="fa fa-exclamation-triangle"></i> <?php echo e($error); ?></div>
        <?php endif; ?>

        <section class="add_sub_agent" id="table_sub_agent">
            <div class="container">
                <div class="" style="margin-top:10px;">
                    <form class="" action="<?php echo BASE_URL; ?>send_msgs.php" enctype="multipart/form-data" method="get">
                        <div class="col-md-2 col-xs-6">
                            <div class="form-group">
                                <label class="required">Class Head</label>
                                <select name="class_head" id="class_head" class="form-control inputheight" onChange="getClassesByHead(this.value)">
                                    <option value="">All</option>
                                    <?php foreach ($heads as $h): ?>
                                        <option value="<?php echo $h['class_head_id']; ?>" <?php echo $sel_head === (int) $h['class_head_id'] ? 'selected' : ''; ?>><?php echo e($h['class_head_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-xs-6">
                            <div class="form-group ">
                                <label class="required">Class</label>
                                <select name="class_id" id="class_id" class="form-control inputheight" onChange="getsec(this.value)">
                                    <option value="">All</option>
                                    <?php foreach ($classes as $cl): ?>
                                        <option value="<?php echo $cl['class_id']; ?>" <?php echo $sel_class === (int) $cl['class_id'] ? 'selected' : ''; ?>><?php echo e($cl['class_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-xs-6">
                            <div class="form-group">
                                <label class="required">Section</label>
                                <select name="section" id="txt_section" class="form-control inputheight">
                                    <option value="All">All</option>
                                    <?php foreach ($sections as $sec): ?>
                                        <option value="<?php echo $sec['section_id']; ?>" <?php echo (int) $sel_section === (int) $sec['section_id'] ? 'selected' : ''; ?>><?php echo e($sec['section_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-xs-9">
                            <div class="form-group">
                                <label class="required">Attendance</label>
                                <select class="form-control" id="attendance" name="attendance">
                                    <option value="A" <?php echo $sel_attendance === 'A' ? 'selected' : ''; ?>>Absent</option>
                                    <option value="L" <?php echo $sel_attendance === 'L' ? 'selected' : ''; ?>>Leave</option>
                                    <option value="LA" <?php echo $sel_attendance === 'LA' ? 'selected' : ''; ?>>Late</option>
                                    <option value="SL" <?php echo $sel_attendance === 'SL' ? 'selected' : ''; ?>>Short Leave</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-1">
                            <div class="form-group ">
                                <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Search</button>
                            </div>
                        </div>
                    </form>

                    <br>

                    <div style="overflow-x:auto;">
                        <form class="form-style-7" id="attendanceSmsForm" action="<?php echo BASE_URL; ?>send_msgs.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="SendAttendanceSMS" />
                            <input type="hidden" name="class_id" value="<?php echo (int) $sel_class; ?>" />
                            <input type="hidden" name="section_id" value="<?php echo $sel_section !== 'All' ? (int) $sel_section : ''; ?>" />
                            <input type="hidden" name="date" value="<?php echo e($sel_date); ?>" />

                            <div class="col-md-12">
                                <input type="submit" name="save_attendance" id="btn" class="pull-right btn btn-primary" value="Send SMS to Parents" />
                            </div>

                            <table id="listofstudents" data-page-length='25' class="table table-striped table-bordered" style="width:100%">
                                <thead>
                                    <tr>
                                        <th width="2%">S.No</th>
                                        <th width="8%">GR. No</th>
                                        <th width="14%">Student Name</th>
                                        <th width="10%"> Cell No </th>
                                        <th width="15%" style="text-align:center;"> Class </th>
                                        <th width="10%" style="text-align:center;"> Date </th>
                                        <th width="10%">Attendance</th>
                                        <th width="5%">SMS</th>
                                        <th width="8%">
                                            <label class="container1">
                                                <input type="checkbox" id="checkAll" /><span style="margin-top: -13px;" class="checkmark"></span>
                                            </label>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($records) === 0): ?>
                                        <tr><td colspan="9" style="text-align:center;padding:30px;">No records found</td></tr>
                                    <?php endif; ?>
                                    <?php $i = 1; foreach ($records as $rec): $cellno = trim($rec['father_cellno'] ?: $rec['phone'] ?: ''); ?>
                                        <tr>
                                            <td><?php echo $i; ?></td>
                                            <td><?php echo e($rec['gr_no'] ?: '-'); ?></td>
                                            <td><?php echo e(trim($rec['first_name'] . ' ' . ($rec['last_name'] ?? ''))); ?></td>
                                            <td><?php echo e($cellno); ?></td>
                                            <td style="text-align:center;"><?php echo e($rec['class_name'] ?: '-'); ?> <?php echo $rec['section_name'] ? '<small>(' . e($rec['section_name']) . ')</small>' : ''; ?></td>
                                            <td style="text-align:center;"><?php echo date('d-M-Y', strtotime($rec['date'])); ?></td>
                                            <td><span style="padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;color:#fff;background:<?php echo $status_badge[$rec['status']] ?? '#6B7280'; ?>;"><?php echo $status_label[$rec['status']] ?? ucfirst($rec['status']); ?></span></td>
                                            <td style="text-align:center;"><i class="fa fa-whatsapp" style="color:#25D366;"></i></td>
                                            <td><input type="checkbox" name="checkboxes[]" value="<?php echo (int) $rec['student_id']; ?>" /></td>
                                        </tr>
                                    <?php $i++; endforeach; ?>
                                </tbody>
                            </table>
                        </form>
                    </div>

                    <br><br>
                </div>
                <div class="clearfix"></div>
            </div>
        </section>
    </div>
</div>
<script>
var BASEURL = '<?php echo BASE_URL; ?>';

function getClassesByHead(head) {
    var url = BASEURL + 'get_classes_by_head.php?class_head=' + encodeURIComponent(head);
    fetch(url)
        .then(function (res) { return res.json(); })
        .then(function (opts) {
            var sel = document.getElementById('class_id');
            sel.innerHTML = '';
            var all = document.createElement('option');
            all.value = '';
            all.textContent = 'All';
            sel.appendChild(all);
            (opts || []).forEach(function (o) {
                var opt = document.createElement('option');
                opt.value = o.id;
                opt.textContent = o.name;
                sel.appendChild(opt);
            });
            getsec(sel.value);
        })
        .catch(function () {});
}

function getsec(cid) {
    var url = BASEURL + 'ajax_get_sections.php?class_id=' + encodeURIComponent(cid);
    fetch(url)
        .then(function (res) { return res.json(); })
        .then(function (rows) {
            var sel = document.getElementById('txt_section');
            sel.innerHTML = '';
            var all = document.createElement('option');
            all.value = 'All';
            all.textContent = 'All';
            sel.appendChild(all);
            (rows || []).forEach(function (r) {
                var opt = document.createElement('option');
                opt.value = r.section_id;
                opt.textContent = r.section_name;
                sel.appendChild(opt);
            });
        })
        .catch(function () {});
}

document.getElementById('attendanceSmsForm').addEventListener('submit', function (e) {
    var checkboxes = document.querySelectorAll('input[name="checkboxes[]"]:checked');
    if (checkboxes.length === 0) {
        e.preventDefault();
        alert('Please select at least one student to send SMS');
        return;
    }
    if (!confirm('Are you sure you want to send SMS?')) {
        e.preventDefault();
        return;
    }
});

document.getElementById('checkAll').addEventListener('change', function () {
    var checked = this.checked;
    document.querySelectorAll('input[name="checkboxes[]"]').forEach(function (cb) {
        cb.checked = checked;
    });
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>