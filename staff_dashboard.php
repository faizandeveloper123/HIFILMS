<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Staff Dashboard';

$today = date('Y-m-d');
$userName = e($_SESSION['user_name'] ?? 'Staff');

$totalStudents   = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=1")->fetch_assoc()['c'] ?? 0);
$attTotal = (int) (db_query("SELECT COUNT(*) c FROM attendance WHERE date='$today'")->fetch_assoc()['c'] ?? 0);
$attPresent = (int) (db_query("SELECT COUNT(*) c FROM attendance WHERE date='$today' AND status='present'")->fetch_assoc()['c'] ?? 0);
$attPct = $attTotal > 0 ? round(($attPresent / $attTotal) * 100) : 0;

$pendingFee = (float) (db_query("SELECT COALESCE(SUM(total_amount - paid_amount),0) t FROM fee_challans WHERE status != 'paid'")->fetch_assoc()['t'] ?? 0);
$feeReceivedToday = (float) (db_query("SELECT COALESCE(SUM(amount),0) t FROM fee_payments WHERE DATE(created_at)='$today'")->fetch_assoc()['t'] ?? 0);

$recentStudents = [];
$res = db_query("SELECT first_name, last_name, father_name, class_id FROM students WHERE status=1 ORDER BY student_id DESC LIMIT 5");
while ($row = $res->fetch_assoc()) { $recentStudents[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.aqib-dash { padding-top: 10px; padding-bottom: 30px; }
.aqib-dash * { box-sizing: border-box; }
.kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 16px; }
.kpi-card { background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:18px; box-shadow:0 1px 3px rgba(16,24,40,.06); }
.kpi-card:hover { box-shadow:0 8px 24px rgba(15,23,42,.08); }
.kpi-top { display:flex; align-items:center; gap:11px; }
.kpi-icon { width:42px; height:42px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; }
.kpi-label { font-size:12.5px; color:#6B7280; font-weight:600; }
.kpi-value { font-size:23px; font-weight:800; color:#111827; margin-top:14px; line-height:1.2; }
.row2 { display:grid; grid-template-columns:1.6fr 1fr; gap:14px; }
.card-head { display:flex; align-items:center; justify-content:space-between; padding:16px 18px 0 18px; }
.card-title { font-size:15px; font-weight:700; color:#111827; }
.quick-links { display:grid; grid-template-columns:repeat(2, 1fr); gap:12px; padding:16px 18px; }
.quick-link { display:flex; align-items:center; gap:10px; padding:14px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; text-decoration:none; color:#111827; transition:all .2s ease; }
.quick-link:hover { background:#fff7ed; border-color:#ffd8b3; color:#ff7c00; transform:translateY(-1px); box-shadow:0 4px 12px rgba(255,124,0,.1); }
.quick-link i { font-size:18px; width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.quick-link .ql-text { font-size:13px; font-weight:600; }
.quick-link .ql-sub { font-size:11px; color:#6b7280; }
@media (max-width:1400px){ .kpi-row{grid-template-columns:repeat(2,1fr);} .row2{grid-template-columns:1fr;} }
@media (max-width:600px){ .kpi-row{grid-template-columns:1fr;} .quick-links{grid-template-columns:1fr;} }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="aqib-dash">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding:4px 4px 14px;">
                <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-tachometer"></i> Welcome, <?php echo $userName; ?></h3>
                <span style="font-size:12.5px; color:#6B7280; background:#F3F4F6; border:1px solid #E5E7EB; padding:5px 12px; border-radius:999px;"><?php echo date('d M, Y'); ?></span>
            </div>

            <div class="kpi-row">
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#E9F2FF; color:#377DFF;"><i class="fa fa-users"></i></div><div class="kpi-label">Total Students</div></div>
                    <div class="kpi-value"><?php echo number_format($totalStudents); ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#D3F3E4; color:#22C55E;"><i class="fa fa-user-check"></i></div><div class="kpi-label">Today Present</div></div>
                    <div class="kpi-value"><?php echo $attPresent; ?> <span style="font-size:14px; color:#22C55E; font-weight:600;">(<?php echo $attPct; ?>%)</span></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#FFE5D1; color:#FF7C1B;"><i class="fa fa-money"></i></div><div class="kpi-label">Fee Received Today</div></div>
                    <div class="kpi-value"><?php echo e(get_setting('currency_symbol', 'Rs.') . number_format($feeReceivedToday)); ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#FFD4D1; color:#DC2626;"><i class="fa fa-file-invoice-dollar"></i></div><div class="kpi-label">Pending Fee</div></div>
                    <div class="kpi-value"><?php echo e(get_setting('currency_symbol', 'Rs.') . number_format($pendingFee)); ?></div>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <div class="kpi-card">
                    <div class="card-head" style="padding:0 0 12px;">
                        <div class="card-title"><i class="fa fa-bolt" style="color:#FF7C1B; margin-right:8px;"></i> Quick Links</div>
                    </div>
                    <div class="quick-links">
                        <a href="<?php echo BASE_URL; ?>mark_attend.php" class="quick-link">
                            <i style="background:#E9F2FF; color:#377DFF;"><i class="fa fa-clipboard-check"></i></i>
                            <div><div class="ql-text">Mark Attendance</div><div class="ql-sub">Mark student attendance</div></div>
                        </a>
                        <a href="<?php echo BASE_URL; ?>manage_students.php" class="quick-link">
                            <i style="background:#D3F3E4; color:#22C55E;"><i class="fa fa-user-graduate"></i></i>
                            <div><div class="ql-text">View Students</div><div class="ql-sub">Browse student records</div></div>
                        </a>
                        <a href="<?php echo BASE_URL; ?>view_challan_details.php" class="quick-link">
                            <i style="background:#FFE5D1; color:#FF7C1B;"><i class="fa fa-money"></i></i>
                            <div><div class="ql-text">Fee Collection</div><div class="ql-sub">View & collect fees</div></div>
                        </a>
                        <a href="<?php echo BASE_URL; ?>new_message.php" class="quick-link">
                            <i style="background:#EADAFF; color:#9747FF;"><i class="fa fa-envelope"></i></i>
                            <div><div class="ql-text">Messages</div><div class="ql-sub">Send messages to parents</div></div>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row2">
                <div class="kpi-card">
                    <div class="card-head" style="padding:0 0 12px;">
                        <div class="card-title"><i class="fa fa-clock-o" style="color:#377DFF; margin-right:8px;"></i> Today Attendance</div>
                    </div>
                    <div style="text-align:center; padding:10px 0;">
                        <div style="width:120px; height:120px; border-radius:50%; margin:0 auto; background:conic-gradient(#22C55E <?php echo $attPct; ?>%, #E5EAF0 0); display:flex; align-items:center; justify-content:center;">
                            <div style="width:88px; height:88px; border-radius:50%; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                <div style="font-size:20px; font-weight:800; color:#16A34A;"><?php echo $attPct; ?>%</div>
                                <div style="font-size:9.5px; color:#9CA3AF;">Present</div>
                            </div>
                        </div>
                        <div style="margin-top:12px; color:#6B7280; font-size:13px;"><?php echo $attPresent; ?> of <?php echo $attTotal; ?> present today</div>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="card-head" style="padding:0 0 12px;">
                        <div class="card-title"><i class="fa fa-user-plus" style="color:#22C55E; margin-right:8px;"></i> Recent Students</div>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0; font-size:13px;">
                            <thead><tr><th>Name</th><th>Father</th></tr></thead>
                            <tbody>
                                <?php if (count($recentStudents) === 0): ?><tr><td colspan="2" style="text-align:center; color:#6B7280; padding:20px;">No students found.</td></tr><?php endif; ?>
                                <?php foreach ($recentStudents as $s): ?>
                                    <tr>
                                        <td><strong><?php echo e($s['first_name'] . ' ' . $s['last_name']); ?></strong></td>
                                        <td><?php echo e($s['father_name'] ?? ''); ?></td>
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
