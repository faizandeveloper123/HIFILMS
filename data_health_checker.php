<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Data Health Checker';

$totalStudents = (int) (db_query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'] ?? 0);

$checks = [];

$res = db_query("SELECT s.student_id, s.gr_no, CONCAT(s.first_name, ' ', s.last_name) name, s.photo, s.class_id, COALESCE(c.class_name, '-') class_name FROM students s LEFT JOIN classes c ON c.class_id = s.class_id WHERE s.photo IS NULL OR s.photo = ''");
$rows = []; $n = 0;
if ($res) { while ($row = $res->fetch_assoc()) { $rows[] = $row; $n++; } }
$checks[] = [
    'key' => 'pictures',
    'title' => 'PROFILE PICTURES',
    'icon' => 'flaticon-user text-red',
    'icon_bg' => 'bg-light-red',
    'bar' => 'bg-red',
    'text_color' => 'text-red',
    'count' => $n,
    'desc' => 'pictures are missing. A complete profile enhances identification and security.',
    'link' => 'update_student_pictures.php?&class_id=All&section=All&emptyImage=empty',
    'columns' => ['S.No', 'GR No', 'Student Name', 'Class', 'Photo File'],
    'rows' => $rows,
    'fields' => ['gr_no', 'name', 'class_name', 'photo'],
];

$res = db_query("SELECT s.student_id, s.gr_no, CONCAT(s.first_name, ' ', s.last_name) name, s.phone, s.father_cellno, s.class_id, COALESCE(c.class_name, '-') class_name FROM students s LEFT JOIN classes c ON c.class_id = s.class_id WHERE s.phone IS NULL OR TRIM(s.phone) = '' OR CHAR_LENGTH(TRIM(s.phone)) < 10");
$rows = []; $n = 0;
if ($res) { while ($row = $res->fetch_assoc()) { $rows[] = $row; $n++; } }
$checks[] = [
    'key' => 'cell',
    'title' => 'CELL NUMBER MISSING/INVALID',
    'icon' => 'fa fa-phone text-green',
    'icon_bg' => 'bg-light-green',
    'bar' => 'bg-green',
    'text_color' => 'text-green',
    'count' => $n,
    'desc' => 'cell numbers are missing. Complete contact details are crucial for effective communication.',
    'link' => 'update_student_classwise.php?inValidNos=1&class_id=&section=All&orderBy=alphabetic&addaccountAdmin=1&stdName=stdName&fName=fName&cellno=cellno',
    'columns' => ['S.No', 'GR No', 'Student Name', 'Class', 'Phone', 'Father Cell'],
    'rows' => $rows,
    'fields' => ['gr_no', 'name', 'class_name', 'phone', 'father_cellno'],
];

$res = db_query("SELECT s.student_id, s.gr_no, CONCAT(s.first_name, ' ', s.last_name) name, s.dob, s.admission_date, s.class_id, COALESCE(c.class_name, '-') class_name FROM students s LEFT JOIN classes c ON c.class_id = s.class_id WHERE s.dob IS NULL OR s.dob = '0000-00-00'");
$rows = []; $n = 0;
if ($res) { while ($row = $res->fetch_assoc()) { $rows[] = $row; $n++; } }
$checks[] = [
    'key' => 'dob',
    'title' => 'Birthdays',
    'icon' => 'fa fa-birthday-cake text-blue',
    'icon_bg' => 'bg-light-blue',
    'bar' => 'bg-blue',
    'text_color' => 'text-blue',
    'count' => $n,
    'desc' => 'students have no date of birth on record. Birthdays help personalize communication and support.',
    'link' => 'update_student_classwise.php?missingDOB=1&class_id=&section=All&orderBy=alphabetic&addaccountAdmin=1&stdName=stdName&fName=fName&dob=dob',
    'columns' => ['S.No', 'GR No', 'Student Name', 'Class', 'DOB', 'Admission Date'],
    'rows' => $rows,
    'fields' => ['gr_no', 'name', 'class_name', 'dob', 'admission_date'],
];

$res = db_query("SELECT s.student_id, s.gr_no, CONCAT(s.first_name, ' ', s.last_name) name, s.phone, s.gender, s.class_id, COALESCE(c.class_name, '-') class_name FROM students s LEFT JOIN classes c ON c.class_id = s.class_id WHERE s.father_name IS NULL OR TRIM(s.father_name) = ''");
$rows = []; $n = 0;
if ($res) { while ($row = $res->fetch_assoc()) { $rows[] = $row; $n++; } }
$checks[] = [
    'key' => 'father',
    'title' => 'MISSING FATHER NAME',
    'icon' => 'fa fa-user text-purple',
    'icon_bg' => 'bg-light-purple',
    'bar' => 'bg-purple',
    'text_color' => 'text-purple',
    'count' => $n,
    'desc' => 'students are missing father name records. Parent details are required for guardianship and communication.',
    'link' => 'update_student_classwise.php?missingFather=1&class_id=&section=All&orderBy=alphabetic&addaccountAdmin=1&stdName=stdName&fName=fName',
    'columns' => ['S.No', 'GR No', 'Student Name', 'Class', 'Phone', 'Gender'],
    'rows' => $rows,
    'fields' => ['gr_no', 'name', 'class_name', 'phone', 'gender'],
];

$res = db_query("SELECT s.phone, s.student_id, s.gr_no, CONCAT(s.first_name, ' ', s.last_name) name, COALESCE(c.class_name, '-') class_name FROM students s LEFT JOIN classes c ON c.class_id = s.class_id WHERE s.phone IS NOT NULL AND TRIM(s.phone) <> '' GROUP BY s.phone HAVING COUNT(*) > 1 ORDER BY s.phone");
$rows = []; $n = 0;
$seen = [];
if ($res) { while ($row = $res->fetch_assoc()) {
    $dup = db_query("SELECT s.student_id, s.gr_no, CONCAT(s.first_name, ' ', s.last_name) name, COALESCE(c.class_name, '-') class_name FROM students s LEFT JOIN classes c ON c.class_id = s.class_id WHERE s.phone = '" . db_connect()->real_escape_string($row['phone']) . "'");
    $names = [];
    if ($dup) { while ($d = $dup->fetch_assoc()) { $names[] = trim($d['name'] . ' (' . $d['class_name'] . ')'); } }
    $rows[] = ['phone' => $row['phone'], 'names' => implode(', ', $names), 'count' => count($names)];
    $n++;
} }
$checks[] = [
    'key' => 'duplicate',
    'title' => 'DUPLICATE CELL NUMBERS',
    'icon' => 'fa fa-users text-orange',
    'icon_bg' => 'bg-light-orange',
    'bar' => 'bg-orange',
    'text_color' => 'text-orange',
    'count' => $n,
    'desc' => 'duplicate phone numbers found across students. Unique contact numbers prevent mixups in communication.',
    'link' => 'update_student_classwise.php?duplicateNos=1&class_id=&section=All&orderBy=alphabetic&addaccountAdmin=1',
    'columns' => ['S.No', 'Phone Number', 'Students', 'Count'],
    'rows' => $rows,
    'fields' => ['phone', 'names', 'count'],
];

$res = db_query("SELECT s.student_id, s.gr_no, CONCAT(s.first_name, ' ', s.last_name) name, s.phone, s.admission_date FROM students s WHERE s.class_id IS NULL OR s.class_id = 0");
$rows = []; $n = 0;
if ($res) { while ($row = $res->fetch_assoc()) { $rows[] = $row; $n++; } }
$checks[] = [
    'key' => 'noclass',
    'title' => 'STUDENTS WITHOUT CLASS',
    'icon' => 'fa fa-graduation-cap text-cyan',
    'icon_bg' => 'bg-light-cyan',
    'bar' => 'bg-cyan',
    'text_color' => 'text-cyan',
    'count' => $n,
    'desc' => 'students are not assigned to any class section. Assigning classes is required for attendance and fee management.',
    'link' => 'manage_students.php?noClass=1&addaccountAdmin=1',
    'columns' => ['S.No', 'GR No', 'Student Name', 'Phone', 'Admission Date'],
    'rows' => $rows,
    'fields' => ['gr_no', 'name', 'phone', 'admission_date'],
];

$res = db_query("SELECT fc.challan_id, fc.challan_no, fc.student_id, fc.month, fc.year, fc.total_amount, fc.status, fc.created_at FROM fee_challans fc LEFT JOIN students s ON s.student_id = fc.student_id WHERE s.student_id IS NULL");
$rows = []; $n = 0;
if ($res) { while ($row = $res->fetch_assoc()) { $rows[] = $row; $n++; } }
$checks[] = [
    'key' => 'orphan',
    'title' => 'ORPHAN FEE CHALLANS',
    'icon' => 'fa fa-money text-red',
    'icon_bg' => 'bg-light-red',
    'bar' => 'bg-red',
    'text_color' => 'text-red',
    'count' => $n,
    'desc' => 'fee challans reference students that no longer exist. Orphan challans must be cleaned up for accurate fee reports.',
    'link' => 'fee_challans.php?orphan=1&addaccountAdmin=1',
    'columns' => ['S.No', 'Challan No', 'Student ID', 'Month', 'Year', 'Total Amount', 'Status', 'Created At'],
    'rows' => $rows,
    'fields' => ['challan_no', 'student_id', 'month', 'year', 'total_amount', 'status', 'created_at'],
];

$issues = 0;
foreach ($checks as $c) { $issues += $c['count']; }
$completion = $totalStudents > 0 ? max(0, round((($totalStudents - min($issues, $totalStudents)) / $totalStudents) * 100)) : 100;

include __DIR__ . '/includes/header.php';
?>
<style type="text/css">
.dashboard-summery-one { background: #fff; border-radius: 8px; padding: 14px; margin-bottom: 18px; }
.dashboard-summery-one .item-icon { height: 90px; width: 90px; padding-top: 13%; border-radius: 18%; font-size: 36px; text-align: center; }
.dashboard-summery-one .row { margin-left: 0; margin-right: 0; }
.dashboard-summery-one .item-content { padding-left: 8px; }
.dashboard-summery-one .item-title { font-weight: 600; font-size: 13px; letter-spacing: 0.3px; }
.dashboard-summery-one .item-number .progress { height: 8px; margin-bottom: 6px; border-radius: 4px; }
.dashboard-summery-one .item-number .progress-bar { height: 100%; }
.flaticon-user:before { content: "\f007"; font-family: "Font Awesome 5 Free"; font-weight: 900; }
.bg-light-red { background: #fee2e2; } .text-red { color: #dc2626; } .bg-red { background: #dc2626; }
.bg-light-green { background: #dcfce7; } .text-green { color: #16a34a; } .bg-green { background: #16a34a; }
.bg-light-blue { background: #dbeafe; } .text-blue { color: #2563eb; } .bg-blue { background: #2563eb; }
.bg-light-orange { background: #ffedd5; } .text-orange { color: #ea580c; } .bg-orange { background: #ea580c; }
.bg-light-purple { background: #f3e8ff; } .text-purple { color: #9333ea; } .bg-purple { background: #9333ea; }
.bg-light-cyan { background: #cffafe; } .text-cyan { color: #0891b2; } .bg-cyan { background: #0891b2; }
.tile-stats .icon i { font-size: 35px; }
.vl { border-left: 1px solid lightgray; height: 40px; position: absolute; left: 50%; margin-left: -3px; margin-top: 24px; }
.blink { height: 100px; width: 150px; border-radius: 30px; font-size: 16px; font-weight: bold; color: white; animation: blinkButton 1s infinite; background-color: red; padding: 8px; }
@keyframes blinkButton { 0% { opacity: 0.1; } 50% { opacity: 1; box-shadow: 1px 0px 30px red; } 100% { opacity: 0.1; } }
.textblink { color: yellow; animation: blinkText 1s infinite; }
@keyframes blinkText { 0% { opacity: 0.1; } 50% { opacity: 1; text-shadow: 1px 0px 30px yellow; } 100% { opacity: 0.1; } }
</style>

<div class="main-content">
<div class="container-fluid">
<div style="margin-top:0px;">
<div class="row top_tiles">

<a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i> &nbsp;
<a href="<?php echo BASE_URL; ?>data_health_checker.php">Data Health Checker</a>
<br><br>

<h4 style="margin-left: 1%;">
    <strong>You're at <?php echo $completion; ?>% completion!</strong>
    Complete all the steps below to <strong>unlock new insights</strong> and <strong>enhance efficiency!</strong>
</h4>

<?php foreach ($checks as $i => $c): ?>
<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
    <div class="dashboard-summery-one" style="border: 1px solid lightgray;">
        <div class="row">
            <div class="col-md-2">
                <div class="item-icon <?php echo $c['icon_bg']; ?>">
                    <i class="<?php echo $c['icon']; ?>"></i>
                </div>
            </div>
            <div class="col-md-10">
                <div class="item-content" style="text-align: left;">
                    <div class="item-title pull-left">
                        <strong><?php echo $c['title']; ?></strong>
                    </div>
                    <div class="item-number">
                        <div class="progress" style="width: 30%; margin-left: 68%;">
                            <div class="progress-bar <?php echo $c['bar']; ?>" role="progressbar" aria-valuenow="<?php echo $c['count']; ?>"
                            aria-valuemin="0" aria-valuemax="100" style="width:<?php echo $totalStudents > 0 ? round($c['count'] / $totalStudents * 100) : 0; ?>%;">
                                <span class="sr-only"><?php echo $c['count']; ?></span>
                            </div>
                        </div>
                    </div>
                    <p>
                        <strong class="<?php echo $c['text_color']; ?>">(
                            <a target="_blank" href="<?php echo BASE_URL . $c['link']; ?>"><?php echo $c['count']; ?> Students </a> )</strong> <?php echo $c['desc']; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if ($i % 2 === 1): ?><div class="clearfix"></div><?php endif; ?>
<?php endforeach; ?>

</div>

<div class="row">
<?php foreach ($checks as $j => $c): ?>
    <div class="col-md-12" style="margin-top: 15px;">
        <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; margin-bottom: 12px;">
            <h4 style="margin: 0 0 10px 0; font-weight: 700; color: #111827;">
                <i class="fa fa-list"></i> <?php echo $c['title']; ?> - Affected Records
            </h4>
            <table class="table table-striped table-bordered" style="width:100%;background-color:#FFFFFF;">
                <thead>
                    <tr>
                        <?php foreach ($c['columns'] as $col): ?>
                            <th><?php echo $col; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($c['rows']) === 0): ?>
                    <tr><td colspan="<?php echo count($c['columns']); ?>" style="text-align:center; color:#6b7280;">No records found.</td></tr>
                <?php else: ?>
                    <?php $sn = 1; foreach ($c['rows'] as $r): ?>
                    <tr>
                        <td style="text-align:center;"><?php echo $sn++; ?></td>
                        <?php foreach ($c['fields'] as $f): ?>
                            <td><?php echo e($r[$f] ?? ''); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>
</div>

</div>
</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>