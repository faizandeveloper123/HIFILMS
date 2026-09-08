<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Student Portal';
$sp_page = 'dashboard';
$sp_sid = (int) ($_GET['student_id'] ?? 0);
$sp_student = null;

if ($sp_sid > 0) {
    $res = db_prepare("SELECT s.*, c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id=c.class_id
        LEFT JOIN sections sec ON s.section_id=sec.section_id
        WHERE s.student_id=?");
    $res->bind_param('i', $sp_sid);
    $res->execute();
    $sp_student = $res->get_result()->fetch_assoc();
}

if (!$sp_student) {
    // Show student selector
    $students = [];
    $res = db_query("SELECT s.student_id, s.first_name, s.last_name, s.father_name, c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id=c.class_id
        LEFT JOIN sections sec ON s.section_id=sec.section_id
        WHERE s.status=1 ORDER BY c.class_name, s.first_name");
    while ($row = $res->fetch_assoc()) { $students[] = $row; }

    $sp_student = [];
    $sp_sid = 0;
    include __DIR__ . '/includes/header.php';
    ?>
    <style>
    .sp-select-wrap { max-width: 700px; margin: 0 auto; padding: 20px 0; }
    .sp-select-title { text-align: center; padding: 20px 0 10px; }
    .sp-select-title i { font-size: 64px; color: #f97316; }
    .sp-select-title h3 { color: #111827; margin: 10px 0 4px; }
    .sp-select-title p { color: #6B7280; font-size: 14px; }
    .sp-student-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; padding: 14px 16px; margin-bottom: 10px; display: flex; align-items: center; gap: 14px; text-decoration: none; color: inherit; transition: all .2s; }
    .sp-student-card:hover { border-color: #f97316; box-shadow: 0 4px 14px rgba(249,115,22,0.12); transform: translateY(-1px); }
    .sp-student-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #f97316, #fb923c); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px; flex-shrink: 0; }
    .sp-student-info h4 { margin: 0; font-size: 14px; font-weight: 700; color: #111827; }
    .sp-student-info p { margin: 2px 0 0; font-size: 12px; color: #6B7280; }
    </style>
    <div style="padding: 20px 24px;">
        <div class="sp-select-wrap">
            <div class="sp-select-title">
                <i class="fa fa-graduation-cap"></i>
                <h3>Student Portal</h3>
                <p>Select a student to continue</p>
            </div>
            <?php if (empty($students)): ?>
                <div style="text-align:center; padding:40px; color:#9CA3AF;">
                    <i class="fa fa-users" style="font-size:48px; display:block; margin-bottom:10px;"></i>
                    <p>No students found in the system.</p>
                </div>
            <?php else: ?>
                <?php foreach ($students as $st): ?>
                    <a href="<?php echo BASE_URL; ?>student_portal.php?student_id=<?php echo $st['student_id']; ?>" class="sp-student-card">
                        <div class="sp-student-avatar"><?php echo strtoupper(substr($st['first_name'], 0, 1)); ?></div>
                        <div class="sp-student-info" style="flex:1;">
                            <h4><?php echo e($st['first_name'] . ' ' . ($st['last_name'] ?? '')); ?></h4>
                            <p><?php echo e($st['class_name'] ?? ''); ?> <?php echo !empty($st['section_name']) ? '- ' . e($st['section_name']) : ''; ?> | Father: <?php echo e($st['father_name'] ?? '-'); ?></p>
                        </div>
                        <i class="fa fa-chevron-right" style="color:#9CA3AF;"></i>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Student found - build dashboard
$full_name = trim($sp_student['first_name'] . ' ' . ($sp_student['last_name'] ?? ''));
$class_label = e($sp_student['class_name'] ?? '-') . ($sp_student['section_name'] ? ' - ' . e($sp_student['section_name']) : '');

// Attendance this month
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$att = db_prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE student_id=? AND date BETWEEN ? AND ? GROUP BY status");
$att->bind_param('iss', $sp_sid, $month_start, $month_end);
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

// Fee
$fee_total = (float) ($sp_student['total_fee'] ?? 0);
$paid_res = db_prepare("SELECT COALESCE(SUM(fp.amount),0) as paid FROM fee_challans fc LEFT JOIN fee_payments fp ON fc.challan_id=fp.challan_id WHERE fc.student_id=?");
$paid_res->bind_param('i', $sp_sid);
$paid_res->execute();
$fee_paid = (float) $paid_res->get_result()->fetch_assoc()['paid'];
$fee_pending = $fee_total - $fee_paid;

// Recent results
$results = [];
$res_marks = db_prepare("SELECT e.exam_name, su.subject_name, m.obtained_marks, m.total_marks,
    ROUND((m.obtained_marks / GREATEST(m.total_marks,1)) * 100, 1) as pct
    FROM marks m JOIN exams e ON m.exam_id=e.exam_id LEFT JOIN subjects su ON m.subject_id=su.subject_id
    WHERE m.student_id=? ORDER BY e.exam_id DESC, su.subject_name LIMIT 5");
$res_marks->bind_param('i', $sp_sid);
$res_marks->execute();
$r_rows = $res_marks->get_result();
while ($r = $r_rows->fetch_assoc()) { $results[] = $r; }

// Homework
$homework = [];
$hw = db_prepare("SELECT * FROM student_diaries WHERE student_id=? AND diary_date >= ? ORDER BY diary_date DESC LIMIT 5");
$hw->bind_param('is', $sp_sid, date('Y-m-d', strtotime('-7 days')));
$hw->execute();
$hw_rows = $hw->get_result();
while ($r = $hw_rows->fetch_assoc()) { $homework[] = $r; }

// Recent messages
$announcements = [];
$ann = db_query("SELECT * FROM messages ORDER BY created_at DESC LIMIT 5");
while ($r = $ann->fetch_assoc()) { $announcements[] = $r; }

include __DIR__ . '/includes/header.php';
?>

<style>
.sp-wrap { max-width: 900px; margin: 0 auto; padding: 20px 24px 40px; }
.sp-welcome { background: linear-gradient(135deg, #1a2332, #2A3F54); color: #fff; padding: 24px; border-radius: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 16px; }
.sp-welcome-avatar { width: 60px; height: 60px; border-radius: 50%; background: rgba(249,115,22,0.2); border: 2px solid #f97316; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 800; flex-shrink: 0; }
.sp-welcome h2 { margin: 0; font-size: 20px; font-weight: 700; }
.sp-welcome p { margin: 4px 0 0; font-size: 13px; opacity: 0.8; }

.sp-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px; }
.sp-kpi { background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; padding: 18px 14px; text-align: center; transition: all .2s; }
.sp-kpi:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.06); }
.sp-kpi i { font-size: 24px; margin-bottom: 8px; }
.sp-kpi .sp-kpi-val { font-size: 28px; font-weight: 800; line-height: 1; }
.sp-kpi .sp-kpi-label { font-size: 11px; color: #6B7280; margin-top: 4px; font-weight: 500; }
.sp-kpi.green i { color: #16A34A; }
.sp-kpi.green .sp-kpi-val { color: #16A34A; }
.sp-kpi.red i { color: #DC2626; }
.sp-kpi.red .sp-kpi-val { color: #DC2626; }
.sp-kpi.orange i { color: #f97316; }
.sp-kpi.orange .sp-kpi-val { color: #f97316; }
.sp-kpi.blue i { color: #2563EB; }
.sp-kpi.blue .sp-kpi-val { color: #2563EB; }

.sp-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; padding: 18px; margin-bottom: 16px; }
.sp-card-title { font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.sp-card-title i { color: #f97316; font-size: 16px; }

.sp-result-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.sp-result-table th { background: #FFF7ED; color: #f97316; padding: 8px 10px; text-align: left; font-weight: 600; border-bottom: 2px solid #fed7aa; }
.sp-result-table td { padding: 8px 10px; border-bottom: 1px solid #F3F4F6; color: #374151; }

.sp-hw-item { padding: 10px 0; border-bottom: 1px solid #F3F4F6; }
.sp-hw-item:last-child { border-bottom: none; }
.sp-hw-date { font-size: 11px; color: #f97316; font-weight: 600; }
.sp-hw-content { font-size: 13px; color: #374151; margin-top: 3px; }
.sp-hw-subject { font-size: 12px; color: #6B7280; }

.sp-empty { text-align: center; color: #9CA3AF; padding: 20px; font-size: 13px; }
.sp-empty i { font-size: 28px; display: block; margin-bottom: 6px; }

.sp-grade-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }

.sp-fee-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #F3F4F6; font-size: 14px; }
.sp-fee-row:last-child { border-bottom: none; }

.sp-quick-links { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
.sp-ql { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 14px 8px; border-radius: 14px; background: #F9FAFB; text-decoration: none; color: #374151; font-size: 12px; font-weight: 600; border: 1px solid #E5E7EB; transition: all .2s; }
.sp-ql:hover { background: #fff7ed; border-color: #f97316; color: #f97316; }
.sp-ql i { font-size: 20px; color: #f97316; }

@media (max-width: 768px) {
  .sp-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .sp-quick-links { grid-template-columns: repeat(2, 1fr); }
  .sp-welcome { flex-direction: column; text-align: center; }
}
@media (max-width: 480px) {
  .sp-wrap { padding: 14px 12px 30px; }
  .sp-kpi .sp-kpi-val { font-size: 22px; }
}
</style>

<div class="sp-wrap">
    <div class="sp-welcome">
        <div class="sp-welcome-avatar"><?php echo strtoupper(substr($sp_student['first_name'], 0, 1)); ?></div>
        <div>
            <h2>Welcome, <?php echo e($full_name); ?>!</h2>
            <p><?php echo $class_label; ?> &bull; GR# <?php echo e($sp_student['gr_no'] ?? '-'); ?></p>
        </div>
    </div>

    <div class="sp-grid">
        <div class="sp-kpi green">
            <i class="fa fa-calendar-check-o"></i>
            <div class="sp-kpi-val"><?php echo $att_pct; ?>%</div>
            <div class="sp-kpi-label">Attendance</div>
        </div>
        <div class="sp-kpi orange">
            <i class="fa fa-money"></i>
            <div class="sp-kpi-val"><?php echo $fee_pending > 0 ? number_format($fee_pending, 0) : '0'; ?></div>
            <div class="sp-kpi-label">Pending Fee</div>
        </div>
        <div class="sp-kpi blue">
            <i class="fa fa-trophy"></i>
            <div class="sp-kpi-val"><?php echo count($results); ?></div>
            <div class="sp-kpi-label">Results</div>
        </div>
        <div class="sp-kpi red">
            <i class="fa fa-book"></i>
            <div class="sp-kpi-val"><?php echo count($homework); ?></div>
            <div class="sp-kpi-label">Homework</div>
        </div>
    </div>

    <div class="sp-card">
        <div class="sp-card-title"><i class="fa fa-calendar-check-o"></i> Attendance Summary</div>
        <?php if ($att_data['total'] > 0): ?>
        <div class="sp-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom:0;">
            <div class="sp-kpi green" style="border:none; padding:8px;"><div class="sp-kpi-val" style="font-size:20px;"><?php echo $att_data['present']; ?></div><div class="sp-kpi-label">Present</div></div>
            <div class="sp-kpi red" style="border:none; padding:8px;"><div class="sp-kpi-val" style="font-size:20px;"><?php echo $att_data['absent']; ?></div><div class="sp-kpi-label">Absent</div></div>
            <div class="sp-kpi orange" style="border:none; padding:8px;"><div class="sp-kpi-val" style="font-size:20px;"><?php echo $att_data['late']; ?></div><div class="sp-kpi-label">Late</div></div>
            <div class="sp-kpi blue" style="border:none; padding:8px;"><div class="sp-kpi-val" style="font-size:20px;"><?php echo $att_data['total']; ?></div><div class="sp-kpi-label">Total Days</div></div>
        </div>
        <?php else: ?>
        <div class="sp-empty"><i class="fa fa-calendar-o"></i>No attendance records this month.</div>
        <?php endif; ?>
    </div>

    <div class="sp-card">
        <div class="sp-card-title"><i class="fa fa-money"></i> Fee Status</div>
        <?php if ($fee_total > 0): ?>
            <div class="sp-fee-row"><span>Total Fee</span><strong><?php echo number_format($fee_total, 0); ?></strong></div>
            <div class="sp-fee-row"><span>Paid</span><strong style="color:#16A34A;"><?php echo number_format($fee_paid, 0); ?></strong></div>
            <div class="sp-fee-row"><span>Pending</span><strong style="color:<?php echo $fee_pending > 0 ? '#DC2626' : '#16A34A'; ?>;"><?php echo number_format($fee_pending, 0); ?></strong></div>
        <?php else: ?>
            <div class="sp-empty"><i class="fa fa-check-circle" style="color:#16A34A;"></i>No fee records found.</div>
        <?php endif; ?>
    </div>

    <?php if (count($results) > 0): ?>
    <div class="sp-card">
        <div class="sp-card-title"><i class="fa fa-trophy"></i> Recent Results</div>
        <div style="overflow-x:auto;">
            <table class="sp-result-table">
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
                    <td><span class="sp-grade-badge" style="background:<?php echo $bg; ?>; color:<?php echo $gc; ?>;"><?php echo $gl; ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($homework) > 0): ?>
    <div class="sp-card">
        <div class="sp-card-title"><i class="fa fa-book"></i> Recent Homework</div>
        <?php foreach ($homework as $hw): ?>
        <div class="sp-hw-item">
            <div class="sp-hw-date"><i class="fa fa-calendar"></i> <?php echo e(date('d M Y', strtotime($hw['diary_date'] ?? $hw['created_at']))); ?></div>
            <?php if (!empty($hw['subject'])): ?>
                <div class="sp-hw-subject">Subject: <?php echo e($hw['subject']); ?></div>
            <?php endif; ?>
            <div class="sp-hw-content"><?php echo nl2br(e($hw['content'])); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="sp-card">
        <div class="sp-card-title"><i class="fa fa-link"></i> Quick Links</div>
        <div class="sp-quick-links">
            <a href="<?php echo BASE_URL; ?>student_portal_profile.php?student_id=<?php echo $sp_sid; ?>" class="sp-ql">
                <i class="fa fa-user"></i>My Profile
            </a>
            <a href="<?php echo BASE_URL; ?>student_portal_attendance.php?student_id=<?php echo $sp_sid; ?>" class="sp-ql">
                <i class="fa fa-calendar-check-o"></i>Attendance
            </a>
            <a href="<?php echo BASE_URL; ?>student_portal_results.php?student_id=<?php echo $sp_sid; ?>" class="sp-ql">
                <i class="fa fa-trophy"></i>Results
            </a>
            <a href="<?php echo BASE_URL; ?>student_fee_payments_view.php?student_id=<?php echo $sp_sid; ?>" class="sp-ql">
                <i class="fa fa-money"></i>Fee Voucher
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
