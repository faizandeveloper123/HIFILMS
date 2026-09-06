<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Teacher Dashboard';

$today = date('Y-m-d');
$userName = e($_SESSION['user_name'] ?? 'Teacher');
$userId = (int) $_SESSION['user_id'];
$dayName = date('l');

$todayClasses = [];
if (@db_query("SHOW TABLES LIKE 'class_period_selection'")->num_rows > 0) {
    $res = @db_query("SELECT t.class_name, s.section_name, sub.subject_name, cp.start_time, cp.end_time FROM class_period_selection cps JOIN classes t ON cps.class_id = t.class_id LEFT JOIN sections s ON cps.section_id = s.section_id LEFT JOIN subjects sub ON cps.subject_id = sub.subject_id LEFT JOIN class_periods cp ON cps.period_id = cp.period_id WHERE cps.teacher_id = $userId AND cps.day = '" . $dayName . "' ORDER BY cp.start_time ASC");
    if ($res) { while ($row = $res->fetch_assoc()) { $todayClasses[] = $row; } }
}

$attTotal = (int) (@db_query("SELECT COUNT(*) c FROM attendance WHERE date='$today'")->fetch_assoc()['c'] ?? 0);
$attPresent = (int) (@db_query("SELECT COUNT(*) c FROM attendance WHERE date='$today' AND status='present'")->fetch_assoc()['c'] ?? 0);
$attPct = $attTotal > 0 ? round(($attPresent / $attTotal) * 100) : 0;

$recentLectures = [];
if (@db_query("SHOW TABLES LIKE 'student_lectures'")->num_rows > 0) {
    $res = @db_query("SELECT c.class_name, vl.subject, vl.content as topic, vl.created_at FROM student_lectures vl LEFT JOIN classes c ON vl.class_id = c.class_id WHERE vl.created_by = $userId ORDER BY vl.created_at DESC LIMIT 5");
    if ($res) { while ($row = $res->fetch_assoc()) { $recentLectures[] = $row; } }
}

$totalClasses = count($todayClasses);

include __DIR__ . '/includes/header.php';
?>
<style>
.aqib-dash{padding-top:10px;padding-bottom:30px}.aqib-dash*{box-sizing:border-box}.kpi-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:16px}.kpi-card{background:#fff;border:1px solid #E5E7EB;border-radius:16px;padding:18px;box-shadow:0 1px 3px rgba(16,24,40,.06)}.kpi-card:hover{box-shadow:0 8px 24px rgba(15,23,42,.08)}.kpi-top{display:flex;align-items:center;gap:11px}.kpi-icon{width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}.kpi-label{font-size:12.5px;color:#6B7280;font-weight:600}.kpi-value{font-size:23px;font-weight:800;color:#111827;margin-top:14px;line-height:1.2}.row2{display:grid;grid-template-columns:1.6fr 1fr;gap:14px}.card-head{display:flex;align-items:center;justify-content:space-between;padding:16px 18px 0 18px}.card-title{font-size:15px;font-weight:700;color:#111827}.quick-links{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;padding:16px 18px}.quick-link{display:flex;align-items:center;gap:10px;padding:14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;text-decoration:none;color:#111827;transition:all .2s ease}.quick-link:hover{background:#fff7ed;border-color:#ffd8b3;color:#ff7c00;transform:translateY(-1px);box-shadow:0 4px 12px rgba(255,124,0,.1)}.quick-link i{font-size:18px;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.quick-link .ql-text{font-size:13px;font-weight:600}.quick-link .ql-sub{font-size:11px;color:#6b7280}.tt-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f3f4f6}.tt-item:last-child{border-bottom:none}.tt-time{font-size:12px;font-weight:700;color:#377DFF;background:#E9F2FF;padding:4px 10px;border-radius:8px;white-space:nowrap}.tt-info{flex:1}.tt-class{font-size:13px;font-weight:700;color:#111827}.tt-sub{font-size:12px;color:#6B7280}
@media(max-width:1400px){.kpi-row{grid-template-columns:repeat(2,1fr)}.row2{grid-template-columns:1fr}.quick-links{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.kpi-row{grid-template-columns:1fr}.quick-links{grid-template-columns:1fr}}
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="aqib-dash">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding:4px 4px 14px">
                <h3 style="font-size:18px;font-weight:800;color:#111827;margin:0"><i class="fa fa-tachometer"></i> Welcome, <?php echo $userName; ?></h3>
                <span style="font-size:12.5px;color:#6B7280;background:#F3F4F6;border:1px solid #E5E7EB;padding:5px 12px;border-radius:999px"><?php echo date('d M, Y') . ' | ' . e($dayName); ?></span>
            </div>

            <div class="kpi-row">
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#E9F2FF;color:#377DFF"><i class="fa fa-calendar-check-o"></i></div><div class="kpi-label">Today Classes</div></div>
                    <div class="kpi-value"><?php echo $totalClasses; ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#D3F3E4;color:#22C55E"><i class="fa fa-user-check"></i></div><div class="kpi-label">Attendance Today</div></div>
                    <div class="kpi-value"><?php echo $attPct; ?>%</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#EADAFF;color:#9747FF"><i class="fa fa-book"></i></div><div class="kpi-label">Recent Lectures</div></div>
                    <div class="kpi-value"><?php echo count($recentLectures); ?></div>
                </div>
            </div>

            <div style="margin-bottom:16px">
                <div class="kpi-card">
                    <div class="card-head" style="padding:0 0 12px"><div class="card-title"><i class="fa fa-bolt" style="color:#FF7C1B;margin-right:8px"></i> Quick Links</div></div>
                    <div class="quick-links">
                        <a href="<?php echo BASE_URL; ?>mark_attend.php" class="quick-link"><i style="background:#E9F2FF;color:#377DFF"><i class="fa fa-clipboard-check"></i></i><div><div class="ql-text">Mark Attendance</div><div class="ql-sub">Mark class attendance</div></div></a>
                        <a href="<?php echo BASE_URL; ?>view_student_lecture.php" class="quick-link"><i style="background:#D3F3E4;color:#22C55E"><i class="fa fa-book-open"></i></i><div><div class="ql-text">View Lectures</div><div class="ql-sub">Your lecture entries</div></div></a>
                        <a href="<?php echo BASE_URL; ?>new_message.php" class="quick-link"><i style="background:#EADAFF;color:#9747FF"><i class="fa fa-envelope"></i></i><div><div class="ql-text">Messages</div><div class="ql-sub">Send messages</div></div></a>
                    </div>
                </div>
            </div>

            <div class="row2">
                <div class="kpi-card">
                    <div class="card-head" style="padding:0 0 12px"><div class="card-title"><i class="fa fa-calendar" style="color:#377DFF;margin-right:8px"></i> Today Timetable</div></div>
                    <?php if (count($todayClasses) === 0): ?>
                        <div style="text-align:center;padding:30px;color:#9CA3AF"><i class="fa fa-calendar-times-o" style="font-size:32px;margin-bottom:8px;display:block"></i>No classes scheduled for today</div>
                    <?php else: ?>
                        <div style="padding:4px 18px 18px">
                            <?php foreach ($todayClasses as $tc): ?>
                                <div class="tt-item">
                                    <div class="tt-time"><?php echo e($tc['start_time'] ?? ''); ?> - <?php echo e($tc['end_time'] ?? ''); ?></div>
                                    <div class="tt-info">
                                        <div class="tt-class"><?php echo e($tc['class_name'] ?? ''); ?> - <?php echo e($tc['section_name'] ?? ''); ?></div>
                                        <div class="tt-sub"><?php echo e($tc['subject_name'] ?? ''); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="kpi-card">
                    <div class="card-head" style="padding:0 0 12px"><div class="card-title"><i class="fa fa-book" style="color:#9747FF;margin-right:8px"></i> Recent Lectures</div></div>
                    <div style="overflow-x:auto">
                        <table class="table table-striped table-bordered" style="width:100%;background:#fff;margin-bottom:0;font-size:13px">
                            <thead><tr><th>Class</th><th>Topic</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php if (count($recentLectures) === 0): ?><tr><td colspan="3" style="text-align:center;color:#6B7280;padding:20px">No lectures yet.</td></tr><?php endif; ?>
                                <?php foreach ($recentLectures as $rl): ?>
                                    <tr>
                                        <td><strong><?php echo e($rl['class_name'] ?? ''); ?></strong></td>
                                        <td><?php echo e(mb_strimwidth($rl['topic'] ?? '', 0, 40, '...')); ?></td>
                                        <td><?php echo date('d M', strtotime($rl['created_at'] ?? 'now')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
