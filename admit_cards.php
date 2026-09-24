<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/campus.php';
require_login();

$class_id   = (int) ($_GET['class_id'] ?? 0);
$section_id = (int) ($_GET['section'] ?? 0);
$session    = trim((string) ($_GET['session'] ?? ''));

$showGr    = isset($_GET['grno']);
$showExam  = isset($_GET['exam_title']);
$showCast  = isset($_GET['cast']);
$showSeat  = isset($_GET['seat_no']);
$examTitle = trim((string) ($_GET['exam_title_text'] ?? ''));
if ($examTitle === '') { $examTitle = 'Annual Examination'; }

$where = [];
$params = [];
$types = '';
if ($class_id > 0) { $where[] = 's.class_id = ?'; $params[] = $class_id; $types .= 'i'; }
if ($section_id > 0) { $where[] = 's.section_id = ?'; $params[] = $section_id; $types .= 'i'; }
if ($session !== '') { $where[] = 's.session = ?'; $params[] = $session; $types .= 's'; }

$sql = "SELECT s.*, c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.class_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id";
if (count($where) > 0) { $sql .= ' WHERE ' . implode(' AND ', $where); }
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
while ($res && $row = $res->fetch_assoc()) { $students[] = $row; }

if (count($students) === 0) { die('No students found for the selected class/section.'); }

$si = school_info();
$schoolName = $si['name'];
$schoolLogo = $si['logo'];
$logoSrc = $schoolLogo !== '' ? BASE_URL . $schoolLogo : BASE_URL . 'assets/img/logo.jpg';
$photoDir = BASE_URL . 'uploads/students/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admit Cards</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #000; margin: 8px; }
        .no-print { text-align: center; margin-bottom: 10px; }
        .no-print button { padding: 6px 16px; font-size: 13px; cursor: pointer; }
        .cards { display: flex; flex-wrap: wrap; }
        .card {
            width: 48%; margin: 1%; border: 2px solid #000; border-radius: 6px;
            padding: 8px 10px; position: relative;
        }
        .card-head { text-align: center; border-bottom: 1px solid #000; padding-bottom: 4px; margin-bottom: 6px; }
        .card-head img { height: 40px; }
        .card-head .school { font-size: 15px; font-weight: bold; }
        .card-head .exam { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .card-head .admit { font-size: 11px; letter-spacing: 1px; }
        .card-body { display: flex; gap: 8px; }
        .card-info { flex: 1; }
        .card-info .row { display: flex; border-bottom: 1px dotted #999; padding: 2px 0; }
        .card-info .lbl { width: 40%; font-weight: bold; }
        .card-info .val { width: 60%; }
        .card-photo { width: 70px; height: 85px; border: 1px solid #000; object-fit: cover; background: #f2f2f2; }
        .card-foot { margin-top: 8px; display: flex; justify-content: space-between; font-size: 10px; }
        .card-foot .sign { border-top: 1px solid #000; padding-top: 2px; width: 40%; text-align: center; }
        @media print {
            .no-print { display: none; }
            @page { size: A4 portrait; margin: 6mm; }
            body { margin: 0; }
            .card { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>

    <div class="cards">
    <?php foreach ($students as $s): ?>
        <div class="card">
            <div class="card-head">
                <?php if ($logoSrc !== ''): ?><img src="<?php echo e($logoSrc); ?>" alt=""><?php endif; ?>
                <div class="school"><?php echo e($schoolName); ?></div>
                <div class="admit">ADMIT CARD</div>
                <?php if ($showExam): ?><div class="exam"><?php echo e($examTitle); ?></div><?php endif; ?>
            </div>
            <div class="card-body">
                <div class="card-info">
                    <div class="row"><span class="lbl">Student Name</span><span class="val"><?php echo e(trim($s['first_name'] . ' ' . $s['last_name'])); ?></span></div>
                    <div class="row"><span class="lbl">Father Name</span><span class="val"><?php echo e($s['father_name']); ?></span></div>
                    <div class="row"><span class="lbl">Class</span><span class="val"><?php echo e($s['class_name']); ?><?php echo $s['section_name'] ? ' - ' . e($s['section_name']) : ''; ?></span></div>
                    <?php if ($showGr): ?><div class="row"><span class="lbl">GR No</span><span class="val"><?php echo e($s['gr_no']); ?></span></div><?php endif; ?>
                    <?php if ($showSeat): ?><div class="row"><span class="lbl">Seat No</span><span class="val"><?php echo e($s['roll_no']); ?></span></div><?php endif; ?>
                    <?php if ($showCast): ?><div class="row"><span class="lbl">Cast</span><span class="val"><?php echo e($s['caste']); ?></span></div><?php endif; ?>
                    <div class="row"><span class="lbl">Session</span><span class="val"><?php echo e($s['session']); ?></span></div>
                </div>
                <div>
                    <?php if (!empty($s['photo'])): ?>
                    <img class="card-photo" src="<?php echo e($photoDir . $s['photo']); ?>" alt="">
                    <?php else: ?>
                    <div class="card-photo"></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-foot">
                <div class="sign">Student Signature</div>
                <div class="sign">Principal</div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</body>
</html>
