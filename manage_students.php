<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Manage Students';

db_query("CREATE TABLE IF NOT EXISTS class_heads (
  class_head_id INT AUTO_INCREMENT PRIMARY KEY,
  class_head_name VARCHAR(150) NOT NULL,
  status TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
try { db_query("ALTER TABLE classes ADD COLUMN IF NOT EXISTS class_head_id INT NULL"); } catch (Exception $e) {}

// Additional advanced filters (EduPortal parity)
$religion_f     = trim($_GET['religion'] ?? '');
$group_f        = trim($_GET['grp'] ?? '');
$board_f        = trim($_GET['board'] ?? '');
$occupation_f   = trim($_GET['occupation'] ?? '');
$contacts_f     = trim($_GET['contacts'] ?? '');
$address_f      = trim($_GET['address'] ?? '');
$age_filter     = trim($_GET['agefilter'] ?? '');
$age_val        = trim($_GET['age'] ?? '');
$adm_date_f     = trim($_GET['adm_date'] ?? '');
$from_date      = trim($_GET['from_date'] ?? '');
$to_date        = trim($_GET['to_date'] ?? '');

function hifi_calc_age($dob) {
    if (!$dob || $dob === '0000-00-00') return null;
    try { return floor((time() - strtotime($dob)) / 31536000); } catch (Exception $e) { return null; }
}
$allGroups = [];
$rg = db_query("SELECT DISTINCT group_shift FROM students WHERE group_shift IS NOT NULL AND group_shift <> '' ORDER BY group_shift");
if ($rg) { while ($row = $rg->fetch_assoc()) { $allGroups[] = $row['group_shift']; } }
$allBoards = [];
$rb = db_query("SELECT DISTINCT board_council FROM students WHERE board_council IS NOT NULL AND board_council <> '' ORDER BY board_council");
if ($rb) { while ($row = $rb->fetch_assoc()) { $allBoards[] = $row['board_council']; } }
$allOccupations = [];
$ro = db_query("SELECT DISTINCT father_occupation FROM students WHERE father_occupation IS NOT NULL AND father_occupation <> '' ORDER BY father_occupation");
if ($ro) { while ($row = $ro->fetch_assoc()) { $allOccupations[] = $row['father_occupation']; } }

$message = '';
$error = '';

// Edit / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'UpdateStudent') {
    $id = (int) ($_POST['student_id'] ?? 0);
    $first_name  = trim($_POST['first_name'] ?? '');
    $father_name = trim($_POST['lname'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? $father_name);
    $cellno      = trim($_POST['cellno'] ?? '');
    $class_id    = (int) ($_POST['class'] ?? 0);
    $gender      = $_POST['gender'] ?? 'male';
    $status      = (int) ($_POST['status'] ?? 1);
    $email       = trim($_POST['email'] ?? '');
    $address     = trim($_POST['address'] ?? '');

    if ($id > 0 && $first_name !== '') {
        $stmt = db_prepare("UPDATE students SET first_name=?, last_name=?, father_name=?, phone=?, email=?, gender=?, class_id=?, address=?, status=? WHERE student_id=?");
        $stmt->bind_param('sssssssisi', $first_name, $last_name, $father_name, $cellno, $email, $gender, $class_id, $address, $status, $id);
        try {
            $stmt->execute();
            $message = 'Student updated successfully!';
        } catch (Exception $ex) {
            $error = 'Error: ' . $ex->getMessage();
        }
    }
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'DeleteStudent') {
    $id = (int) ($_POST['student_id'] ?? 0);
    if ($id > 0) {
        $st = db_prepare("DELETE FROM students WHERE student_id=?");
        $st->bind_param('i', $id);
        $st->execute();
        $message = 'Student deleted successfully!';
    }
}

// Filters
$search      = trim($_GET['search'] ?? '');
$session_f   = trim($_GET['session'] ?? '');
$head_id     = (int) ($_GET['class_head'] ?? 0);
$class_id    = (int) ($_GET['class_id'] ?? 0);
$section_id  = (int) ($_GET['section'] ?? 0);
$gender_f    = trim($_GET['gender'] ?? '');
$status_f    = trim($_GET['status'] ?? '');

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.father_name LIKE ? OR s.phone LIKE ? OR s.gr_no LIKE ?)';
    $like = '%' . $search . '%';
    for ($i = 0; $i < 5; $i++) { $params[] = $like; $types .= 's'; }
}
if ($session_f !== '') {
    $where[] = 's.session LIKE ?';
    $params[] = '%' . $session_f . '%'; $types .= 's';
}
if ($head_id > 0) {
    $where[] = 'c.class_head_id = ?';
    $params[] = $head_id; $types .= 'i';
}
if ($class_id > 0) {
    $where[] = 's.class_id = ?';
    $params[] = $class_id; $types .= 'i';
}
if ($section_id > 0) {
    $where[] = 's.section_id = ?';
    $params[] = $section_id; $types .= 'i';
}
if ($gender_f !== '' && $gender_f !== 'All') {
    $where[] = 's.gender = ?';
    $params[] = $gender_f; $types .= 's';
}
if ($status_f !== '') {
    $where[] = 's.status = ?';
    $params[] = ($status_f === 'active') ? 1 : 0; $types .= 'i';
}
if ($religion_f !== '')     { $where[] = 's.religion = ?';        $params[] = $religion_f; $types .= 's'; }
if ($group_f !== '')        { $where[] = 's.group_shift = ?';     $params[] = $group_f; $types .= 's'; }
if ($board_f !== '')        { $where[] = 's.board_council = ?';   $params[] = $board_f; $types .= 's'; }
if ($occupation_f !== '')   { $where[] = 's.father_occupation = ?'; $params[] = $occupation_f; $types .= 's'; }
if ($contacts_f !== '')     { $where[] = '(s.phone LIKE ? OR s.father_cellno LIKE ? OR s.email LIKE ?)'; $like = '%' . $contacts_f . '%'; foreach (range(0,2) as $i) { $params[] = $like; $types .= 's'; } }
if ($address_f !== '')      { $where[] = 's.address LIKE ?'; $params[] = '%' . $address_f . '%'; $types .= 's'; }
if ($from_date !== '')      { $where[] = 's.admission_date >= ?'; $params[] = $from_date; $types .= 's'; }
if ($to_date !== '')        { $where[] = 's.admission_date <= ?'; $params[] = $to_date; $types .= 's'; }

$sql = "SELECT s.*, c.class_name, c.class_head_id, ch.class_head_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.class_id
        LEFT JOIN class_heads ch ON ch.class_head_id = c.class_head_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id";

if (count($where) > 0) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY s.gr_no ASC, s.first_name ASC';

$students = [];
if (count($params) > 0) {
    $stmt = db_prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = db_query($sql);
}
while ($row = $res->fetch_assoc()) {
    if ($age_filter !== '' && $age_val !== '') {
        $a = hifi_calc_age($row['dob'] ?? '');
        if ($a === null) continue;
        $av = (int) $age_val;
        if ($age_filter === 'lt' && !($a < $av)) continue;
        if ($age_filter === 'gt' && !($a > $av)) continue;
        if ($age_filter === 'eq' && $a !== $av) continue;
    }
    $students[] = $row;
}

$classes = [];
$res2 = db_query("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res2->fetch_assoc()) { $classes[] = $row; }

$class_heads = [];
$rh = db_query("Select class_head_id, class_head_name FROM class_heads WHERE status=1 ORDER BY class_head_name");
while ($row = $rh->fetch_assoc()) { $class_heads[] = $row; }

$sessions = [];
$rs = db_query("SELECT DISTINCT session FROM students WHERE session IS NOT NULL AND session <> '' ORDER BY session DESC");
while ($row = $rs->fetch_assoc()) { $sessions[] = $row['session']; }

$counts = db_query("SELECT SUM(status=1) AS active_s, SUM(status=0) AS inactive_s, COUNT(*) AS total_s FROM students")->fetch_assoc();

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.student-head { display:flex; align-items:center; justify-content:space-between; padding:14px 4px; flex-wrap:wrap; gap:10px; }
.student-head h3 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.summary-strip { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.summary-chip { display:flex; align-items:center; gap:10px; background:#fff; border:1px solid #E5E7EB; border-radius:12px; padding:10px 16px; min-width:150px; }
.summary-chip .num { font-size:20px; font-weight:800; color:#111827; }
.summary-chip .lbl { font-size:12px; color:#6B7280; }
.summary-chip .ico { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; }
.adv-filter { background:#f7f8fa; border:1px solid #dde3ec; border-radius:8px; padding:14px 16px 6px; margin-bottom:10px; }
.adv-filter .af-sec-label { font-size:10px; font-weight:700; color:#aaa; text-transform:uppercase; letter-spacing:1.2px; margin-bottom:8px; }
.frame-row { display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:12px 16px; margin-bottom:12px; }
.frame-row .toolbar { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.photo-cell img { width: 38px; height:38px; border-radius:50%; object-fit:cover; }
.dt-controls { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:12px 16px; }
.dt-controls input, .dt-controls select { border:1px solid #E5E7EB; border-radius:8px; padding:6px 10px; font-size:13px; }
.pager { display:flex; gap:6px; align-items:center; padding:10px 16px; }
.pager button { border:1px solid #E5E7EB; background:#fff; border-radius:6px; padding:5px 11px; font-size:13px; }
.pager button.active { background:#FF7A1B; color:#fff; border-color:#FF7A1B; }
.pager button:disabled { opacity:.45; }
.sp-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
.sp-tabs .tab-pill { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:20px; background:#fff; border:1px solid #E5E7EB; color:#374151; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; transition:all .15s; }
.sp-tabs .tab-pill:hover { border-color:#FF7A1B; color:#FF7A1B; }
.sp-tabs .tab-pill.active { background:#FF7A1B; border-color:#FF7A1B; color:#fff; }
.bcrumb { font-size:13px; color:#6B7280; margin-bottom:14px; }
.bcrumb a { color:#FF7A1B; text-decoration:none; }
.bcrumb i { margin:0 6px; font-size:11px; color:#C0C7D0; }
@media print {
  .no-print, .frame-row, .dt-controls, .pager, .search-bar-student, .summary-strip { display:none !important; }
}
</style>

<div class="main-content">
    <div class="container-fluid">

        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="bcrumb">
            <a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
            <i class="fa fa-angle-right"></i>
            <span>Students</span>
            <i class="fa fa-angle-right"></i>
            <span>View Students</span>
        </div>

        <div class="sp-tabs">
            <a class="tab-pill" href="students_analytics_dashboard.php"><i class="fa fa-bar-chart"></i> Classwise Reports</a>
            <a class="tab-pill active" href="manage_students.php"><i class="fa fa-users"></i> View Students</a>
            <span class="tab-pill" style="cursor:default;"><i class="fa fa-exchange"></i> Admissions &amp; Withdrawal Report</span>
            <span class="tab-pill" style="cursor:default;"><i class="fa fa-id-card"></i> Student Slips</span>
            <span class="tab-pill" style="cursor:default;"><i class="fa fa-map-marker"></i> Locality Reports</span>
            <a class="tab-pill" href="add_student.php"><i class="fa fa-plus"></i> Admission Form</a>
        </div>

        <div class="student-head">
            <h3><i class="fa fa-users"></i> Manage Students <span style="font-size:14px; color:#6B7280;">(<?php echo count($students); ?> records)</span></h3>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" onclick="window.print()" class="btn btn-success" style="padding:7px 14px;"><i class="fa fa-print"></i> Print List</button>
                <button type="button" onclick="exportStudentList()" class="btn btn-info" style="padding:7px 14px;"><i class="fa fa-file-excel-o"></i> Export to Excel</button>
                <a href="<?php echo BASE_URL; ?>add_student.php" class="btn" style="background:#FF7A1B; color:#fff; padding:7px 14px;"><i class="fa fa-plus"></i> Add New Student</a>
            </div>
        </div>

        <div class="summary-strip">
            <div class="summary-chip">
                <div class="ico" style="background:#2a78d622; color:#2a78d6;"><i class="fa fa-users"></i></div>
                <div><div class="num"><?php echo (int) ($counts['total_s'] ?? 0); ?></div><div class="lbl">Total Students</div></div>
            </div>
            <div class="summary-chip">
                <div class="ico" style="background:#16a34a22; color:#16A34A;"><i class="fa fa-check-circle"></i></div>
                <div><div class="num"><?php echo (int) ($counts['active_s'] ?? 0); ?></div><div class="lbl">Active</div></div>
            </div>
            <div class="summary-chip">
                <div class="ico" style="background:#dc262622; color:#DC2626;"><i class="fa fa-user-times"></i></div>
                <div><div class="num"><?php echo (int) ($counts['inactive_s'] ?? 0); ?></div><div class="lbl">Inactive / Struck-Off</div></div>
            </div>
        </div>

        <form method="get" action="manage_students.php" class="search-bar-student">
            <div class="form-group col-md-4" style="margin-bottom:0;">
                <label>Search</label>
                <input type="text" name="search" class="form-control" value="<?php echo e($search); ?>" placeholder="Name / GR No / Cell No">
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>Session</label>
                <select name="session" class="form-control">
                    <option value="">All Sessions</option>
                    <?php foreach ($sessions as $ss): ?>
                        <option value="<?php echo e($ss); ?>" <?php echo $session_f === $ss ? 'selected' : ''; ?>><?php echo e($ss); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>Course/Class Head</label>
                <select name="class_head" class="form-control">
                    <option value="0">All Heads</option>
                    <?php foreach ($class_heads as $h): ?>
                        <option value="<?php echo $h['class_head_id']; ?>" <?php echo $head_id == $h['class_head_id'] ? 'selected' : ''; ?>><?php echo e($h['class_head_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>Course/Class</label>
                <select name="class_id" id="ms_class" class="form-control" onchange="loadFilterSections();">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>" <?php echo $class_id == $c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>Section</label>
                <select name="section" id="ms_section" class="form-control">
                    <option value="0">All Sections</option>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <label>Gender</label>
                <select name="gender" class="form-control">
                    <option value="All">All</option>
                    <option value="male" <?php echo $gender_f === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo $gender_f === 'female' ? 'selected' : ''; ?>>Female</option>
                    <option value="other" <?php echo $gender_f === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All</option>
                    <option value="active" <?php echo $status_f === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_f === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="form-group col-md-1" style="margin-bottom:0;">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa fa-search"></i></button>
            </div>
            <div class="form-group col-md-1" style="margin-bottom:0;">
                <label>&nbsp;</label>
                <a href="manage_students.php" class="btn btn-default" style="width:100%;"><i class="fa fa-refresh"></i></a>
            </div>
        </form>

        <div style="text-align:right; margin-bottom:10px;">
            <button type="button" class="btn btn-default btn-sm" id="advToggle"><i class="fa fa-search-plus"></i> Advance Search</button>
        </div>

        <form method="get" action="manage_students.php" class="adv-filter" id="advPanel" style="display:none;">
            <button type="button" class="close" style="float:right; margin:0;" onclick="document.getElementById('advPanel').style.display='none'; document.getElementById('advToggle').style.display='';"><span>&times;</span></button>
            <div class="af-sec-label">General &amp; Admission</div>
            <div class="row" style="margin-bottom:8px;">
                <div class="form-group col-md-3">
                    <label>Religion</label>
                    <input type="text" name="religion" class="form-control" value="<?php echo e($religion_f); ?>" placeholder="e.g. Islam">
                </div>
                <div class="form-group col-md-3">
                    <label>Group / Shift</label>
                    <select name="grp" class="form-control">
                        <option value="">All</option>
                        <?php foreach ($allGroups as $g): ?>
                            <option value="<?php echo e($g); ?>" <?php echo $group_f === $g ? 'selected' : ''; ?>><?php echo e($g); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Board / Council</label>
                    <select name="board" class="form-control">
                        <option value="">All</option>
                        <?php foreach ($allBoards as $b): ?>
                            <option value="<?php echo e($b); ?>" <?php echo $board_f === $b ? 'selected' : ''; ?>><?php echo e($b); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Father Occupation</label>
                    <select name="occupation" class="form-control">
                        <option value="">All</option>
                        <?php foreach ($allOccupations as $o): ?>
                            <option value="<?php echo e($o); ?>" <?php echo $occupation_f === $o ? 'selected' : ''; ?>><?php echo e($o); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="af-sec-label">Contact &amp; Address</div>
            <div class="row" style="margin-bottom:8px;">
                <div class="form-group col-md-4">
                    <label>Contacts (Cell / Phone / Email)</label>
                    <input type="text" name="contacts" class="form-control" value="<?php echo e($contacts_f); ?>" placeholder="Search any contact field">
                </div>
                <div class="form-group col-md-4">
                    <label>Address</label>
                    <input type="text" name="address" class="form-control" value="<?php echo e($address_f); ?>" placeholder="Address contains">
                </div>
                <div class="form-group col-md-2">
                    <label>Age</label>
                    <select name="agefilter" class="form-control">
                        <option value="">--</option>
                        <option value="lt" <?php echo $age_filter === 'lt' ? 'selected' : ''; ?>&lt;</option>
                        <option value="gt" <?php echo $age_filter === 'gt' ? 'selected' : ''; ?>&gt;</option>
                        <option value="eq" <?php echo $age_filter === 'eq' ? 'selected' : ''; ?>=</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>Age (Years)</label>
                    <input type="number" name="age" class="form-control" value="<?php echo e($age_val); ?>" placeholder="e.g. 5">
                </div>
            </div>
            <div class="af-sec-label">Admission Date Range</div>
            <div class="row" style="margin-bottom:12px;">
                <div class="form-group col-md-3">
                    <label>From</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo e($from_date); ?>">
                </div>
                <div class="form-group col-md-3">
                    <label>To</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo e($to_date); ?>">
                </div>
                <div class="form-group col-md-6" style="display:flex; align-items:flex-end; padding-bottom:6px;">
                    <button type="submit" class="btn btn-primary" style="margin-right:6px;"><i class="fa fa-search"></i> Apply</button>
                    <a href="manage_students.php" class="btn btn-default"><i class="fa fa-refresh"></i> Clear</a>
                </div>
            </div>
        </form>
        <script>
        (function(){
            var panel = document.getElementById('advPanel');
            var toggle = document.getElementById('advToggle');
            <?php if ($religion_f !== '' || $group_f !== '' || $board_f !== '' || $occupation_f !== '' || $contacts_f !== '' || $address_f !== '' || $age_filter !== '' || $from_date !== '' || $to_date !== ''): ?>
            panel.style.display = '';
            toggle.style.display = 'none';
            <?php endif; ?>
            toggle.addEventListener('click', function(){
                panel.style.display = '';
                toggle.style.display = 'none';
            });
        })();
        </script>

        <div class="frame-row no-print">
            <div class="toolbar">
                <label style="font-size:12px; color:#6B7280; margin-right:4px;">Show</label>
                <select id="dtPageSize">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="All">All</option>
                </select>
                <span style="font-size:12px; color:#6B7280;">entries</span>
            </div>
            <div style="color:#6B7280; font-size:13px;" id="dtInfo">Showing 0 entries</div>
        </div>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <table class="table table-striped table-bordered" id="studentsTable" style="width:100%; background:#fff; margin-bottom:0;">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>GR. No</th>
                        <th>Student Name</th>
                        <th>Father Name</th>
                        <th>Course/Class / Section</th>
                        <th>Cell No</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($students) === 0): ?>
                        <tr><td colspan="8" style="text-align:center; color:#6B7280; padding:30px;">No students found.</td></tr>
                    <?php endif; ?>
                    <?php $si = 1; foreach ($students as $s): ?>
                        <tr>
                            <td style="text-align:center;"><?php echo $si++; ?></td>
                            <td style="text-align:center;"><?php echo e($s['gr_no']); ?></td>
                            <td>
                                <a href="student.php?student=<?php echo (int) $s['student_id']; ?>" style="color:#111827;">
                                    <?php if (!empty($s['photo'])): ?>
                                        <img class="photo-cell" src="<?php echo BASE_URL; ?>uploads/students/<?php echo e($s['photo']); ?>" alt="" style="width:38px;height:38px;border-radius:50%;object-fit:cover;">
                                    <?php else: ?>
                                        <span style="display:inline-flex;width:38px;height:38px;border-radius:50%;background:#FFF3E9;color:#FF7A1B;align-items:center;justify-content:center;"><i class="fa fa-user"></i></span>
                                    <?php endif; ?>
                                    &nbsp;<strong><?php echo e($s['first_name']); ?></strong>
                                </a>
                            </td>
                            <td><?php echo e($s['father_name'] ?? $s['last_name']); ?></td>
                            <td><?php echo e($s['class_name'] ?? '-'); ?> / <?php echo e($s['section_name'] ?? '-'); ?></td>
                            <td><?php echo e($s['phone']); ?></td>
                            <td>
                                <?php if ($s['status'] == 1): ?>
                                    <span class="label label-success">Active</span>
                                <?php else: ?>
                                    <span class="label label-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-primary btn-xs" onclick="openEdit(<?php echo $s['student_id']; ?>)"><i class="fa fa-pencil"></i></button>
                                <form method="post" action="manage_students.php" style="display:inline;" onsubmit="return confirm('Delete this student and all data?');">
                                    <input type="hidden" name="action" value="DeleteStudent">
                                    <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pager no-print" id="dtPager"></div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="manage_students.php">
                <input type="hidden" name="action" value="UpdateStudent">
                <input type="hidden" name="student_id" id="m_id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Edit Student</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Student Name</label>
                            <input type="text" class="form-control" name="first_name" id="m_fname" required="">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Father Name</label>
                            <input type="text" class="form-control" name="lname" id="m_father">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Phone</label>
                            <input type="text" class="form-control" name="cellno" id="m_phone">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Email</label>
                            <input type="text" class="form-control" name="email" id="m_email">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>Course/Class</label>
                            <select name="class_id" id="m_class" class="form-control">
                                <option value="">Select Course/Class</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Gender</label>
                            <select name="gender" id="m_gender" class="form-control">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Status</label>
                            <select name="status" id="m_status" class="form-control">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" id="m_address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var studentData = <?php echo json_encode($students, JSON_UNESCAPED_UNICODE); ?>;
function openEdit(id) {
    var s = studentData.find(function(x){ return x.student_id == id; });
    if (!s) return;
    document.getElementById('m_id').value = s.student_id;
    document.getElementById('m_fname').value = s.first_name || '';
    document.getElementById('m_father').value = s.father_name || '';
    document.getElementById('m_phone').value = s.phone || '';
    document.getElementById('m_email').value = s.email || '';
    document.getElementById('m_class').value = s.class_id || '';
    document.getElementById('m_gender').value = s.gender || 'male';
    document.getElementById('m_status').value = s.status || 1;
    document.getElementById('m_address').value = s.address || '';
    $('#editModal').modal('show');
}

function loadFilterSections() {
    var cid = document.getElementById('ms_class').value;
    var sel = document.getElementById('ms_section');
    sel.innerHTML = '<option value="0">All Sections</option>';
    if (!cid) return;
    fetch('ajax_get_sections.php?class_id=' + cid)
        .then(function(r){ return r.json(); })
        .then(function(data){
            data.forEach(function(s){
                var o = document.createElement('option');
                o.value = s.section_id; o.textContent = s.section_name;
                if (s.section_id == <?php echo $section_id; ?>) o.selected = true;
                sel.appendChild(o);
            });
        });
}
loadFilterSections();

(function(){
    var pageSize = parseInt(document.getElementById('dtPageSize').value, 10) || 25;
    var currentPage = 1;
    var searchTerm = '';
    var infoEl = document.getElementById('dtInfo');
    var pagerEl = document.getElementById('dtPager');

    function rows() { return Array.prototype.slice.call(document.querySelectorAll('#studentsTable tbody tr')); }

    function visibleRows() {
        return rows().filter(function(r){
            if (!searchTerm) return true;
            return r.textContent.toLowerCase().indexOf(searchTerm) !== -1;
        });
    }

    function renderPager(total, perPage) {
        var tPages = Math.max(1, Math.ceil(total / perPage));
        if (currentPage > tPages) currentPage = tPages;
        pagerEl.innerHTML = '';
        ['«','‹','1','2','3','4','5','›','»'].forEach(function(lbl){
            if (['«','‹'].indexOf(lbl) !== -1) {
                var b0 = document.createElement('button');
                b0.textContent = lbl;
                b0.disabled = lbl === '«' ? currentPage === 1 : currentPage === 1;
                b0.onclick = function(){ currentPage = lbl === '«' ? 1 : Math.max(1, currentPage - 1); render(); };
                pagerEl.appendChild(b0);
            } else if (['›','»'].indexOf(lbl) !== -1) {
                var b1 = document.createElement('button');
                b1.textContent = lbl;
                b1.disabled = lbl === '»' ? currentPage === tPages : currentPage === tPages;
                b1.onclick = function(){ currentPage = lbl === '»' ? tPages : Math.min(tPages, currentPage + 1); render(); };
                pagerEl.appendChild(b1);
            } else {
                var i = parseInt(lbl, 10);
                var startPage = Math.max(1, currentPage - 2);
                var endPage = Math.min(tPages, startPage + 4);
                if (i >= startPage && i <= endPage) {
                    var b2 = document.createElement('button');
                    b2.textContent = i;
                    if (i === currentPage) b2.className = 'active';
                    b2.onclick = function(){ currentPage = i; render(); };
                    pagerEl.appendChild(b2);
                }
            }
        });
    }

    function render() {
        var v = visibleRows();
        var total = v.length;
        var perPage = pageSize;
        if (perPage === 0 || perPage > total) perPage = total || 1;
        var start = (currentPage - 1) * perPage;
        var end = Math.min(start + perPage, total);
        rows().forEach(function(r){ r.style.display = 'none'; });
        for (var i = start; i < end; i++) { if (v[i]) v[i].style.display = ''; }
        infoEl.textContent = total === 0 ? 'Showing 0 entries' : 'Showing ' + (start + 1) + ' to ' + end + ' of ' + total + ' entries';
        renderPager(total, perPage);
    }

    document.getElementById('dtPageSize').addEventListener('change', function(){
        pageSize = this.value === 'All' ? 999999 : parseInt(this.value, 10);
        currentPage = 1;
        render();
    });

    var searchBox = document.createElement('input');
    searchBox.type = 'text';
    searchBox.placeholder = 'Search...';
    searchBox.style.cssText = 'border:1px solid #E5E7EB;border-radius:8px;padding:6px 10px;font-size:13px;min-width:220px;';
    document.querySelector('.frame-row .toolbar').appendChild(searchBox);
    searchBox.addEventListener('input', function(){
        searchTerm = this.value.toLowerCase();
        currentPage = 1;
        render();
    });
    render();
})();

function exportStudentList() {
    var rows = document.querySelectorAll('#studentsTable tbody tr');
    if (rows.length === 0) { alert('No data available to export.'); return; }
    var header = ['Sr.No','Gr.No','Student Name','Father Name','Class','Section','Cell No','Status'];
    var csv = header.join(',') + '\n';
    Array.prototype.forEach.call(rows, function(r){
        var cells = r.querySelectorAll('td');
        if (cells.length < 6) return;
        function txt(el){ return el ? el.textContent.trim().replace(/\s+/g,' ') : ''; }
        var clssec = txt(cells[4]).split('/');
        var row = [txt(cells[0]), txt(cells[1]), txt(cells[2]), txt(cells[3]),
                   clssec[0]||'', clssec[1]||'', txt(cells[5]), txt(cells[6])];
        csv += row.map(function(x){ return '"' + x.replace(/"/g,'""') + '"'; }).join(',') + '\n';
    });
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'Students_List.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>