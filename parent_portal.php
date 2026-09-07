<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'ParentsConnect';

$student_id = (int) ($_GET['student_id'] ?? 0);

if ($student_id <= 0) {
    $students = [];
    $res = db_query("SELECT s.student_id, s.first_name, s.last_name, s.father_name, c.class_name
        FROM students s LEFT JOIN classes c ON s.class_id=c.class_id WHERE s.status=1 ORDER BY s.first_name");
    while ($row = $res->fetch_assoc()) { $students[] = $row; }

    include __DIR__ . '/includes/header.php';
    ?>
    <div class="main-content">
        <div class="container-fluid">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
                <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-home"></i> ParentsConnect</h3>
            </div>
            <div style="text-align:center; padding:20px 0 10px;">
                <i class="fa fa-user-circle" style="font-size:64px; color:#f97316;"></i>
                <h3 style="color:#111827; margin:10px 0 4px;">Select Your Child</h3>
                <p style="color:#6B7280; font-size:14px;">Choose a student to view their dashboard</p>
            </div>
            <?php foreach ($students as $st): ?>
                <a href="<?php echo BASE_URL; ?>parent_portal.php?student_id=<?php echo $st['student_id']; ?>" style="text-decoration:none;">
                    <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:12px; display:flex; align-items:center; gap:14px; transition:box-shadow .2s;">
                        <div style="width:48px; height:48px; border-radius:50%; background:linear-gradient(135deg,#f97316,#fb923c); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:18px; flex-shrink:0;">
                            <?php echo strtoupper(substr($st['first_name'], 0, 1)); ?>
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:700; color:#111827; font-size:15px;"><?php echo e($st['first_name']); ?> <?php echo e($st['last_name'] ?? ''); ?></div>
                            <div style="font-size:13px; color:#6B7280;">Father: <?php echo e($st['father_name'] ?? '-'); ?> | <?php echo e($st['class_name'] ?? '-'); ?></div>
                        </div>
                        <i class="fa fa-chevron-right" style="color:#9CA3AF;"></i>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$student = null;
$res = db_prepare("SELECT s.*, c.class_name, sec.section_name
    FROM students s
    LEFT JOIN classes c ON s.class_id=c.class_id
    LEFT JOIN sections sec ON s.section_id=sec.section_id
    WHERE s.student_id=?");
$res->bind_param('i', $student_id);
$res->execute();
$student = $res->get_result()->fetch_assoc();

if (!$student) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="main-content"><div class="container-fluid"><div style="text-align:center; padding:60px;"><i class="fa fa-exclamation-triangle" style="font-size:48px; color:#DC2626;"></i><h3 style="color:#DC2626; margin-top:10px;">Student Not Found</h3><p style="color:#6B7280;">The student ID is invalid.</p><a href="' . BASE_URL . 'parent_portal.php" class="btn btn-warning" style="color:#fff;"><i class="fa fa-arrow-left"></i> Back</a></div></div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$full_name = trim($student['first_name'] . ' ' . ($student['last_name'] ?? ''));
$class_label = e($student['class_name'] ?? '-') . ($student['section_name'] ? ' - ' . e($student['section_name']) : '');

$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$att = db_prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE student_id=? AND date BETWEEN ? AND ? GROUP BY status");
$att->bind_param('iss', $student_id, $month_start, $month_end);
$att->execute();
$att_rows = $att->get_result();
$att_data = ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0];
while ($r = $att_rows->fetch_assoc()) {
    $s = strtolower(trim($r['status']));
    if ($s === 'present' || $s === 'p') { $att_data['present'] = (int)$r['cnt']; }
    elseif ($s === 'absent' || $s === 'a') { $att_data['absent'] = (int)$r['cnt']; }
    elseif ($s === 'late' || $s === 'l') { $att_data['late'] = (int)$r['cnt']; }
    $att_data['total'] += (int)$r['cnt'];
}
$att_pct = $att_data['total'] > 0 ? round(($att_data['present'] / $att_data['total']) * 100, 1) : 0;

$fee_total = (float) ($student['total_fee'] ?? 0);
$paid_res = db_prepare("SELECT COALESCE(SUM(fp.amount),0) as paid
    FROM fee_challans fc LEFT JOIN fee_payments fp ON fc.challan_id=fp.challan_id
    WHERE fc.student_id=?");
$paid_res->bind_param('i', $student_id);
$paid_res->execute();
$fee_paid = (float) $paid_res->get_result()->fetch_assoc()['paid'];
$fee_pending = $fee_total - $fee_paid;
$last_pay_res = db_prepare("SELECT MAX(fp.payment_date) as last_date
    FROM fee_challans fc LEFT JOIN fee_payments fp ON fc.challan_id=fp.challan_id
    WHERE fc.student_id=?");
$last_pay_res->bind_param('i', $student_id);
$last_pay_res->execute();
$last_pay_date = $last_pay_res->get_result()->fetch_assoc()['last_date'];

$results = [];
$res_marks = db_prepare("SELECT e.exam_name, su.subject_name, m.obtained_marks, m.total_marks,
    ROUND((m.obtained_marks / GREATEST(m.total_marks,1)) * 100, 1) as pct
    FROM marks m
    JOIN exams e ON m.exam_id=e.exam_id
    LEFT JOIN subjects su ON m.subject_id=su.subject_id
    WHERE m.student_id=?
    ORDER BY e.exam_id DESC, su.subject_name
    LIMIT 10");
$res_marks->bind_param('i', $student_id);
$res_marks->execute();
$r_rows = $res_marks->get_result();
while ($r = $r_rows->fetch_assoc()) { $results[] = $r; }

$homework = [];
$hw = db_prepare("SELECT * FROM student_diaries WHERE student_id=? AND diary_date >= ? ORDER BY diary_date DESC LIMIT 5");
$hw->bind_param('is', $student_id, date('Y-m-d', strtotime('-7 days')));
$hw->execute();
$hw_rows = $hw->get_result();
while ($r = $hw_rows->fetch_assoc()) { $homework[] = $r; }

$announcements = [];
$ann = db_query("SELECT * FROM messages ORDER BY created_at DESC LIMIT 5");
while ($r = $ann->fetch_assoc()) { $announcements[] = $r; }

$transport = null;
$tr = db_prepare("SELECT v.vehicle_no, v.driver_name, r.route_name, r.starting_point, r.ending_point
    FROM students s
    LEFT JOIN vehicle_route vr ON s.route_id=vr.route_id
    LEFT JOIN vehicles v ON vr.vehicle_id=v.vehicle_id
    LEFT JOIN routes r ON vr.route_id=r.route_id
    WHERE s.student_id=?");
$tr->bind_param('i', $student_id);
$tr->execute();
$transport = $tr->get_result()->fetch_assoc();
if ($transport && empty($transport['vehicle_no'])) { $transport = null; }

include __DIR__ . '/includes/header.php';
?>
<style>
.pp-wrap { max-width: 800px; margin: 0 auto; padding: 0 0 80px; }
.pp-top-bar { background: linear-gradient(135deg, #f97316, #fb923c); color: #fff; padding: 18px 16px; border-radius: 0 0 24px 24px; margin: -10px -10px 16px; display: flex; align-items: center; gap: 14px; }
.pp-top-bar .pp-avatar { width: 52px; height: 52px; border-radius: 50%; background: rgba(255,255,255,0.25); display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; flex-shrink: 0; }
.pp-top-bar .pp-info h2 { margin: 0; font-size: 17px; font-weight: 700; }
.pp-top-bar .pp-info p { margin: 2px 0 0; font-size: 13px; opacity: 0.85; }
.pp-top-bar .pp-back { color: #fff; font-size: 16px; text-decoration: none; margin-right: 4px; }
.pp-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 16px; padding: 16px; margin-bottom: 14px; }
.pp-card-title { font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.pp-card-title i { color: #f97316; font-size: 16px; }
.pp-stat-row { display: flex; gap: 10px; flex-wrap: wrap; }
.pp-stat { flex: 1; min-width: 70px; text-align: center; padding: 10px 4px; border-radius: 12px; background: #F9FAFB; }
.pp-stat .pp-num { font-size: 22px; font-weight: 800; }
.pp-stat .pp-label { font-size: 11px; color: #6B7280; margin-top: 2px; }
.pp-stat.green .pp-num { color: #16A34A; }
.pp-stat.red .pp-num { color: #DC2626; }
.pp-stat.yellow .pp-num { color: #D97706; }
.pp-stat.blue .pp-num { color: #2563EB; }
.pp-fee-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #F3F4F6; font-size: 14px; }
.pp-fee-row:last-child { border-bottom: none; }
.pp-fee-row strong { color: #111827; }
.pp-hw-item { padding: 10px 0; border-bottom: 1px solid #F3F4F6; }
.pp-hw-item:last-child { border-bottom: none; }
.pp-hw-date { font-size: 11px; color: #f97316; font-weight: 600; }
.pp-hw-content { font-size: 13px; color: #374151; margin-top: 3px; }
.pp-hw-subject { font-size: 12px; color: #6B7280; }
.pp-ann-item { padding: 10px 0; border-bottom: 1px solid #F3F4F6; }
.pp-ann-item:last-child { border-bottom: none; }
.pp-ann-title { font-size: 13px; font-weight: 600; color: #111827; }
.pp-ann-msg { font-size: 12px; color: #6B7280; margin-top: 2px; }
.pp-ann-date { font-size: 11px; color: #9CA3AF; }
.pp-quick-links { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.pp-ql { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 14px 8px; border-radius: 14px; background: #F9FAFB; text-decoration: none; color: #374151; font-size: 12px; font-weight: 600; border: 1px solid #E5E7EB; transition: all .2s; }
.pp-ql:hover { background: #fff7ed; border-color: #f97316; color: #f97316; }
.pp-ql i { font-size: 20px; color: #f97316; }
.pp-chart-wrap { position: relative; width: 160px; height: 160px; margin: 0 auto; }
.pp-empty { text-align: center; color: #9CA3AF; padding: 20px; font-size: 13px; }
.pp-empty i { font-size: 28px; display: block; margin-bottom: 6px; }
.pp-bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; border-top: 1px solid #E5E7EB; display: flex; z-index: 100; box-shadow: 0 -2px 10px rgba(0,0,0,0.06); }
.pp-bottom-nav a { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px; padding: 10px 4px; text-decoration: none; color: #6B7280; font-size: 11px; font-weight: 500; transition: color .2s; }
.pp-bottom-nav a.active, .pp-bottom-nav a:hover { color: #f97316; }
.pp-bottom-nav a i { font-size: 18px; }
.pp-result-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.pp-result-table th { background: #FFF7ED; color: #f97316; padding: 8px 10px; text-align: left; font-weight: 600; border-bottom: 2px solid #fed7aa; }
.pp-result-table td { padding: 8px 10px; border-bottom: 1px solid #F3F4F6; color: #374151; }
.pp-grade-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
@media (max-width: 480px) {
    .pp-wrap { padding: 0 0 70px; }
    .pp-stat-row { gap: 6px; }
    .pp-stat { min-width: 60px; padding: 8px 2px; }
    .pp-stat .pp-num { font-size: 18px; }
    .pp-quick-links { grid-template-columns: repeat(3, 1fr); gap: 6px; }
    .pp-ql { padding: 10px 4px; font-size: 11px; }
    .pp-ql i { font-size: 18px; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="main-content">
<div class="container-fluid">
<div class="pp-wrap">

    <div class="pp-top-bar">
        <a href="<?php echo BASE_URL; ?>parent_portal.php" class="pp-back"><i class="fa fa-arrow-left"></i></a>
        <div class="pp-avatar"><?php echo strtoupper(substr($student['first_name'], 0, 1)); ?></div>
        <div class="pp-info">
            <h2><?php echo e($full_name); ?></h2>
            <p><?php echo $class_label; ?> &bull; GR# <?php echo e($student['gr_no'] ?? '-'); ?></p>
        </div>
    </div>

    <div class="pp-card">
        <div class="pp-card-title"><i class="fa fa-user"></i> Student Profile</div>
        <div style="display:flex; gap:14px; align-items:center;">
            <div style="width:64px; height:64px; border-radius:14px; overflow:hidden; background:#FFF7ED; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <?php if (!empty($student['photo']) && file_exists(__DIR__ . '/uploads/students/' . $student['photo'])): ?>
                    <img src="<?php echo BASE_URL; ?>uploads/students/<?php echo e($student['photo']); ?>" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <i class="fa fa-user" style="font-size:28px; color:#f97316;"></i>
                <?php endif; ?>
            </div>
            <div style="flex:1; font-size:13px; color:#374151;">
                <div style="margin-bottom:4px;"><strong>Father:</strong> <?php echo e($student['father_name'] ?? '-'); ?></div>
                <div style="margin-bottom:4px;"><strong>Phone:</strong> <?php echo e($student['phone'] ?? '-'); ?></div>
                <div><strong>GR No:</strong> <?php echo e($student['gr_no'] ?? '-'); ?></div>
            </div>
        </div>
    </div>

    <div class="pp-card">
        <div class="pp-card-title"><i class="fa fa-calendar-check-o"></i> Attendance This Month</div>
        <div class="pp-stat-row">
            <div class="pp-stat green"><div class="pp-num"><?php echo $att_data['present']; ?></div><div class="pp-label">Present</div></div>
            <div class="pp-stat red"><div class="pp-num"><?php echo $att_data['absent']; ?></div><div class="pp-label">Absent</div></div>
            <div class="pp-stat yellow"><div class="pp-num"><?php echo $att_data['late']; ?></div><div class="pp-label">Late</div></div>
            <div class="pp-stat blue"><div class="pp-num"><?php echo $att_pct; ?>%</div><div class="pp-label">Rate</div></div>
        </div>
        <?php if ($att_data['total'] > 0): ?>
            <div class="pp-chart-wrap" style="margin-top:14px;">
                <canvas id="attChart"></canvas>
            </div>
        <?php else: ?>
            <div class="pp-empty"><i class="fa fa-calendar-o"></i>No attendance data this month.</div>
        <?php endif; ?>
    </div>

    <div class="pp-card">
        <div class="pp-card-title"><i class="fa fa-money"></i> Fee Status</div>
        <?php if ($fee_total > 0): ?>
            <div class="pp-fee-row"><span>Total Fee</span><strong><?php echo number_format($fee_total, 0); ?></strong></div>
            <div class="pp-fee-row"><span>Paid</span><strong style="color:#16A34A;"><?php echo number_format($fee_paid, 0); ?></strong></div>
            <div class="pp-fee-row"><span>Pending</span><strong style="color:<?php echo $fee_pending > 0 ? '#DC2626' : '#16A34A'; ?>;"><?php echo number_format($fee_pending, 0); ?></strong></div>
            <?php if ($last_pay_date): ?>
                <div class="pp-fee-row"><span>Last Payment</span><strong><?php echo e(date('d M Y', strtotime($last_pay_date))); ?></strong></div>
            <?php endif; ?>
            <div style="margin-top:10px; display:flex; gap:8px;">
                <button onclick="printFeeVoucher()" style="flex:1; padding:10px; border-radius:10px; border:1px solid #f97316; background:#fff7ed; color:#f97316; font-weight:600; cursor:pointer; font-size:13px;"><i class="fa fa-print"></i> Print Voucher</button>
            </div>
        <?php else: ?>
            <div class="pp-empty"><i class="fa fa-check-circle" style="color:#16A34A;"></i>No fee records found.</div>
        <?php endif; ?>
    </div>

    <div class="pp-card">
        <div class="pp-card-title"><i class="fa fa-trophy"></i> Recent Results</div>
        <?php if (count($results) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="pp-result-table">
                    <thead><tr><th>Exam</th><th>Subject</th><th>Marks</th><th>%</th><th>Grade</th></tr></thead>
                    <tbody>
                        <?php foreach ($results as $r):
                            $pct = $r['pct'];
                            if ($pct >= 80) { $gc = '#16A34A'; $bg = '#DCFCE7'; $gl = 'A'; }
                            elseif ($pct >= 60) { $gc = '#2563EB'; $bg = '#DBEAFE'; $gl = 'B'; }
                            elseif ($pct >= 40) { $gc = '#D97706'; $bg = '#FEF3C7'; $gl = 'C'; }
                            else { $gc = '#DC2626'; $bg = '#FEE2E2'; $gl = 'F'; }
                        ?>
                        <tr>
                            <td><?php echo e($r['exam_name']); ?></td>
                            <td><?php echo e($r['subject_name'] ?? '-'); ?></td>
                            <td><?php echo e($r['obtained_marks']); ?>/<?php echo e($r['total_marks']); ?></td>
                            <td><?php echo $pct; ?>%</td>
                            <td><span class="pp-grade-badge" style="background:<?php echo $bg; ?>; color:<?php echo $gc; ?>;"><?php echo $gl; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:10px;">
                <button onclick="shareResult()" style="width:100%; padding:10px; border-radius:10px; border:1px solid #f97316; background:#fff7ed; color:#f97316; font-weight:600; cursor:pointer; font-size:13px;"><i class="fa fa-share-alt"></i> Share Result Card</button>
            </div>
        <?php else: ?>
            <div class="pp-empty"><i class="fa fa-file-text-o"></i>No results published yet.</div>
        <?php endif; ?>
    </div>

    <div class="pp-card">
        <div class="pp-card-title"><i class="fa fa-book"></i> Today's Homework</div>
        <?php if (count($homework) > 0): ?>
            <?php foreach ($homework as $hw): ?>
                <div class="pp-hw-item">
                    <div class="pp-hw-date"><i class="fa fa-calendar"></i> <?php echo e(date('d M Y', strtotime($hw['diary_date'] ?? $hw['created_at']))); ?></div>
                    <?php if (!empty($hw['subject'])): ?>
                        <div class="pp-hw-subject">Subject: <?php echo e($hw['subject']); ?></div>
                    <?php endif; ?>
                    <div class="pp-hw-content"><?php echo nl2br(e($hw['content'])); ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="pp-empty"><i class="fa fa-check"></i>No homework assigned.</div>
        <?php endif; ?>
    </div>

    <div class="pp-card">
        <div class="pp-card-title"><i class="fa fa-bullhorn"></i> Recent Announcements</div>
        <?php if (count($announcements) > 0): ?>
            <?php foreach ($announcements as $an): ?>
                <div class="pp-ann-item">
                    <div class="pp-ann-title"><?php echo e($an['title'] ?? 'Announcement'); ?></div>
                    <div class="pp-ann-msg"><?php echo e(mb_strimwidth($an['message'] ?? '', 0, 120, '...')); ?></div>
                    <div class="pp-ann-date"><?php echo e(date('d M Y', strtotime($an['created_at']))); ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="pp-empty"><i class="fa fa-bell-slash"></i>No announcements.</div>
        <?php endif; ?>
    </div>

    <?php if ($transport): ?>
    <div class="pp-card">
        <div class="pp-card-title"><i class="fa fa-bus"></i> Transport Info</div>
        <div class="pp-fee-row"><span>Route</span><strong><?php echo e($transport['route_name'] ?? '-'); ?></strong></div>
        <div class="pp-fee-row"><span>Vehicle</span><strong><?php echo e($transport['vehicle_no'] ?? '-'); ?></strong></div>
        <div class="pp-fee-row"><span>Driver</span><strong><?php echo e($transport['driver_name'] ?? '-'); ?></strong></div>
        <?php if (!empty($transport['starting_point'])): ?>
            <div class="pp-fee-row"><span>Pickup</span><strong><?php echo e($transport['starting_point']); ?></strong></div>
        <?php endif; ?>
        <?php if (!empty($transport['ending_point'])): ?>
            <div class="pp-fee-row"><span>Drop-off</span><strong><?php echo e($transport['ending_point']); ?></strong></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="pp-card">
        <div class="pp-card-title"><i class="fa fa-link"></i> Quick Links</div>
        <div class="pp-quick-links">
            <a href="<?php echo BASE_URL; ?>student_fee_payments_view.php?student_id=<?php echo $student_id; ?>" class="pp-ql">
                <i class="fa fa-money"></i>Fee Voucher
            </a>
            <a href="<?php echo BASE_URL; ?>reportcards.php?class_id=<?php echo $student['class_id']; ?>" class="pp-ql">
                <i class="fa fa-file-text-o"></i>Report Card
            </a>
            <a href="<?php echo BASE_URL; ?>parent_portal.php?student_id=<?php echo $student_id; ?>" class="pp-ql">
                <i class="fa fa-calendar-check-o"></i>Attendance
            </a>
        </div>
    </div>

</div>
</div>
</div>

<div class="pp-bottom-nav">
    <a href="<?php echo BASE_URL; ?>parent_portal.php?student_id=<?php echo $student_id; ?>" class="active">
        <i class="fa fa-home"></i>Home
    </a>
    <a href="#att-section" onclick="scrollToCard('att-card'); return false;">
        <i class="fa fa-calendar-check-o"></i>Attendance
    </a>
    <a href="#fee-section" onclick="scrollToCard('fee-card'); return false;">
        <i class="fa fa-money"></i>Fees
    </a>
    <a href="#result-section" onclick="scrollToCard('result-card'); return false;">
        <i class="fa fa-trophy"></i>Results
    </a>
    <a href="#more-section" onclick="scrollToCard('quick-card'); return false;">
        <i class="fa fa-ellipsis-h"></i>More
    </a>
</div>

<script>
function scrollToCard(id) {
    var cards = document.querySelectorAll('.pp-card');
    var idx = 0;
    if (id === 'att-card') idx = 1;
    else if (id === 'fee-card') idx = 2;
    else if (id === 'result-card') idx = 3;
    else if (id === 'quick-card') idx = cards.length - 1;
    if (cards[idx]) cards[idx].scrollIntoView({ behavior: 'smooth', block: 'start' });
}

<?php if ($att_data['total'] > 0): ?>
var attCtx = document.getElementById('attChart');
if (attCtx) {
    new Chart(attCtx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent', 'Late'],
            datasets: [{
                data: [<?php echo $att_data['present']; ?>, <?php echo $att_data['absent']; ?>, <?php echo $att_data['late']; ?>],
                backgroundColor: ['#16A34A', '#DC2626', '#D97706'],
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, pointStyleWidth: 8, font: { size: 12 } } }
            }
        }
    });
}
<?php endif; ?>

function printFeeVoucher() {
    var w = window.open('', '_blank', 'width=700,height=600');
    w.document.write('<html><head><title>Fee Voucher - <?php echo e($full_name); ?></title>');
    w.document.write('<style>body{font-family:Arial,sans-serif;padding:20px;color:#333;} h2{color:#f97316;border-bottom:2px solid #f97316;padding-bottom:8px;} table{width:100%;border-collapse:collapse;margin:10px 0;} td,th{padding:8px 10px;border:1px solid #ddd;text-align:left;font-size:13px;} th{background:#FFF7ED;color:#f97316;} .footer{margin-top:20px;font-size:11px;color:#999;text-align:center;}</style>');
    w.document.write('</head><body>');
    w.document.write('<h2>Fee Voucher</h2>');
    w.document.write('<p><strong>Student:</strong> <?php echo e($full_name); ?></p>');
    w.document.write('<p><strong>Class:</strong> <?php echo $class_label; ?> | <strong>GR No:</strong> <?php echo e($student["gr_no"] ?? "-"); ?></p>');
    w.document.write('<table><tr><th>Item</th><th>Amount</th></tr>');
    w.document.write('<tr><td>Total Fee</td><td><?php echo number_format($fee_total, 0); ?></td></tr>');
    w.document.write('<tr><td>Paid</td><td><?php echo number_format($fee_paid, 0); ?></td></tr>');
    w.document.write('<tr><td><strong>Pending</strong></td><td><strong><?php echo number_format($fee_pending, 0); ?></strong></td></tr>');
    w.document.write('</table>');
    w.document.write('<p>Due Date: Please check with accounts office.</p>');
    w.document.write('<div class="footer">HIIFI LMS &bull; <?php echo e(get_setting("school_name", "HIIFI LMS")); ?></div>');
    w.document.write('</body></html>');
    w.document.close();
    w.print();
}

function shareResult() {
    var text = 'Result Card - <?php echo e($full_name); ?>\nClass: <?php echo $class_label; ?>\n\n';
    <?php foreach ($results as $r): ?>
    text += '<?php echo e($r["exam_name"]); ?> | <?php echo e($r["subject_name"] ?? "-"); ?> | <?php echo e($r["obtained_marks"]); ?>/<?php echo e($r["total_marks"]); ?> (<?php echo $r["pct"]; ?>%)\n';
    <?php endforeach; ?>
    text += '\nPowered by <?php echo e(get_setting("school_name", "HIIFI LMS")); ?>';
    if (navigator.share) {
        navigator.share({ title: 'Result Card - <?php echo e($full_name); ?>', text: text });
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        alert('Result copied to clipboard!');
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
