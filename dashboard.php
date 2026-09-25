<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/campus.php';
require_login();

$page_title = 'Executive Dashboard';

// ---- Statistics -------------------------------------------------
$today = date('Y-m-d');
$month = date('m');
$year = date('Y');

$totalStudents    = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=1")->fetch_assoc()['c'] ?? 0);
$totalEmployees   = (int) (db_query("SELECT COUNT(*) c FROM employees WHERE status=1")->fetch_assoc()['c'] ?? 0);
$totalComplaints  = (int) (db_query("SELECT COUNT(*) c FROM complaints WHERE MONTH(created_at)=$month AND YEAR(created_at)=$year")->fetch_assoc()['c'] ?? 0);
$totalInquiries   = (int) (db_query("SELECT COUNT(*) c FROM inquiries WHERE MONTH(created_at)=$month AND YEAR(created_at)=$year")->fetch_assoc()['c'] ?? 0);

$feeReceived      = (float) (db_query("SELECT COALESCE(SUM(f.amount),0) t FROM fee_payments f WHERE DATE(f.created_at)='$today'")->fetch_assoc()['t'] ?? 0);
$feeReceivable    = (float) (db_query("SELECT COALESCE(SUM(total_amount - paid_amount),0) t FROM fee_challans WHERE status != 'paid'")->fetch_assoc()['t'] ?? 0);
$monthlyExpenses  = (float) (db_query("SELECT COALESCE(SUM(amount),0) t FROM expenses WHERE MONTH(expense_date)=$month AND YEAR(expense_date)=$year")->fetch_assoc()['t'] ?? 0);

$boys  = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=1 AND gender='male'")->fetch_assoc()['c'] ?? 0);
$girls = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=1 AND gender='female'")->fetch_assoc()['c'] ?? 0);

// Attendance today
$attTotal = (int) (db_query("SELECT COUNT(*) c FROM attendance WHERE date='$today'")->fetch_assoc()['c'] ?? 0);
$attPresent = (int) (db_query("SELECT COUNT(*) c FROM attendance WHERE date='$today' AND status='present'")->fetch_assoc()['c'] ?? 0);
$attAbsent  = (int) (db_query("SELECT COUNT(*) c FROM attendance WHERE date='$today' AND status='absent'")->fetch_assoc()['c'] ?? 0);
$attLate    = (int) (db_query("SELECT COUNT(*) c FROM attendance WHERE date='$today' AND status='late'")->fetch_assoc()['c'] ?? 0);
$attLeave   = (int) (db_query("SELECT COUNT(*) c FROM attendance WHERE date='$today' AND status='leave'")->fetch_assoc()['c'] ?? 0);
$attPct = $attTotal > 0 ? round(($attPresent / $attTotal) * 100) : 0;

// Students overview percentages
$studentPctBoys  = $totalStudents > 0 ? round(($boys / $totalStudents) * 100) : 0;
$studentPctGirls = $totalStudents > 0 ? round(($girls / $totalStudents) * 100) : 0;

// Admissions this month
$admissionsMonth = (int) (db_query("SELECT COUNT(*) c FROM students WHERE MONTH(admission_date)=$month AND YEAR(admission_date)=$year")->fetch_assoc()['c'] ?? 0);
$admissionsYear  = (int) (db_query("SELECT COUNT(*) c FROM students WHERE YEAR(admission_date)=$year")->fetch_assoc()['c'] ?? 0);
$admissionsLastYear = (int) (db_query("SELECT COUNT(*) c FROM students WHERE YEAR(admission_date)=" . ($year - 1))->fetch_assoc()['c'] ?? 0);

// Withdrawals this month (status = 0 but created before)
$withdrawalsMonth = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=0 AND MONTH(admission_date)=$month AND YEAR(admission_date)=$year")->fetch_assoc()['c'] ?? 0);
$withdrawalsYear  = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=0 AND YEAR(admission_date)=$year")->fetch_assoc()['c'] ?? 0);
$withdrawalsLastYear = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=0 AND YEAR(admission_date)=" . ($year - 1))->fetch_assoc()['c'] ?? 0);

// Fee status this month
$challansTotal  = (int) (db_query("SELECT COUNT(*) c FROM fee_challans WHERE MONTH(created_at)=$month AND YEAR(created_at)=$year")->fetch_assoc()['c'] ?? 0);
$challansPaid   = (int) (db_query("SELECT COUNT(*) c FROM fee_challans WHERE status='paid' AND MONTH(created_at)=$month AND YEAR(created_at)=$year")->fetch_assoc()['c'] ?? 0);
$challansUnpaid = (int) (db_query("SELECT COUNT(*) c FROM fee_challans WHERE status='unpaid' AND MONTH(created_at)=$month AND YEAR(created_at)=$year")->fetch_assoc()['c'] ?? 0);
$challansPartial= (int) (db_query("SELECT COUNT(*) c FROM fee_challans WHERE status='partial' AND MONTH(created_at)=$month AND YEAR(created_at)=$year")->fetch_assoc()['c'] ?? 0);
$challanPct = $challansTotal > 0 ? round(($challansPaid / $challansTotal) * 100) : 0;

// Birthdays today
$birthdays = [];
$res = db_query("SELECT s.first_name, s.last_name, s.father_name, c.class_name FROM students s LEFT JOIN classes c ON s.class_id=c.class_id WHERE DATE_FORMAT(s.dob, '%m-%d') = DATE_FORMAT('$today', '%m-%d') AND s.status=1");
if ($res) { while ($r = $res->fetch_assoc()) { $birthdays[] = $r; } }

// Last 12 months income vs expenses for chart
$chartData = [];
$labels = [];
for ($i = 11; $i >= 0; $i--) {
    $d = new DateTime("first day of -$i months");
    $m = $d->format('m'); $y = $d->format('Y');
    $labels[] = $d->format('M');
    $income = (float) (db_query("SELECT COALESCE(SUM(amount),0) t FROM fee_payments WHERE MONTH(created_at)=$m AND YEAR(created_at)=$y")->fetch_assoc()['t'] ?? 0);
    $expense = (float) (db_query("SELECT COALESCE(SUM(amount),0) t FROM expenses WHERE MONTH(expense_date)=$m AND YEAR(expense_date)=$y")->fetch_assoc()['t'] ?? 0);
    $chartData['income'][] = $income;
    $chartData['expense'][] = $expense;
}

// Last 7 days fee collections (real sparkline data)
$fee7 = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $fee7[] = (float) (db_query("SELECT COALESCE(SUM(amount),0) t FROM fee_payments WHERE DATE(created_at)='$d'")->fetch_assoc()['t'] ?? 0);
}

function money($v) {
    return number_format($v);
}

$cur = e(get_setting('currency_symbol', 'Rs.'));
$curFull = get_setting('currency_symbol', 'Rs.');

include __DIR__ . '/includes/header.php';
?>
<style>
    .aqib-dash { padding-top: 6px; }
    .earnings-body canvas { max-height: 280px; }
    .kpi-spark { height: 28px; }
    @media (max-width: 640px) { .earnings-body { min-height: 200px; } }
</style>

<div class="main-content">
    <div class="aqib-dash">

        <div class="dash-head">
            <div>
                <div class="dash-eyebrow">Executive Dashboard</div>
                <h1 class="dash-title">Campus at a glance</h1>
                <div class="dash-sub">Live overview of <?php echo e(get_setting('school_name', 'LAPS School & College')); ?> &middot; <?php echo date('l, d M Y'); ?></div>
            </div>
            <div class="dash-actions">
                <button type="button" id="moneyToggle" class="btn" onclick="toggleMoneyVisibility()">
                    <i class="fa fa-eye" id="moneyToggleIcon"></i> <span id="moneyToggleLbl">Hide Values</span>
                </button>
            </div>
        </div>

        <div class="kpi-row">
            <a class="kpi-card" href="<?php echo BASE_URL; ?>datewise_fee_collection_report_new.php" target="_blank">
                <span class="kpi-accent" style="background:linear-gradient(90deg,#2563EB,#60A5FA);"></span>
                <div class="kpi-top">
                    <div class="kpi-icon" style="background:#DBEAFE;color:#2563EB;"><i class="fa fa-wallet"></i></div>
                    <div class="kpi-label">Fee Received</div>
                </div>
                <div class="kpi-value"><span class="money-value" data-full="<?php echo e($curFull . number_format($feeReceived)); ?>" data-hidden="0"><?php echo $cur . number_format($feeReceived); ?></span><i class="fa fa-eye toggle-money-eye" title="Show/Hide Amount"></i></div>
                <div class="kpi-change flat">Today</div>
                <span class="kpi-spark" data-spark="<?php echo e(json_encode($chartData['income'])); ?>" data-type="line" data-color="#2563EB" title="Fee collections &middot; last 12 months"></span>
            </a>

            <a class="kpi-card" href="<?php echo BASE_URL; ?>print_unpaid_fee_new.php" target="_blank">
                <span class="kpi-accent" style="background:linear-gradient(90deg,#16A34A,#4ADE80);"></span>
                <div class="kpi-top">
                    <div class="kpi-icon" style="background:#DCFCE7;color:#16A34A;"><i class="fa fa-file-invoice-dollar"></i></div>
                    <div class="kpi-label">Fee Receivable</div>
                </div>
                <div class="kpi-value"><span class="money-value" data-full="<?php echo e($curFull . number_format($feeReceivable)); ?>" data-hidden="0"><?php echo $cur . number_format($feeReceivable); ?></span><i class="fa fa-eye toggle-money-eye" title="Show/Hide Amount"></i></div>
                <div class="kpi-change flat">Outstanding balance</div>
                <div class="kpi-spark ds-mini-stat"><?php echo $challansUnpaid > 0 ? '<span class="ds-pill" style="color:#D97706;background:#FEF3C7;">' . $challansUnpaid . ' unpaid challans</span>' : '<span class="ds-pill">No unpaid challans this month</span>'; ?></div>
            </a>

            <a class="kpi-card" href="<?php echo BASE_URL; ?>datewise_fee_collection_report_new.php" target="_blank">
                <span class="kpi-accent" style="background:linear-gradient(90deg,#7C3AED,#A78BFA);"></span>
                <div class="kpi-top">
                    <div class="kpi-icon" style="background:#EDE9FE;color:#7C3AED;"><i class="fa fa-coins"></i></div>
                    <div class="kpi-label">Today's Collection</div>
                </div>
                <div class="kpi-value"><span class="money-value" data-full="<?php echo e($curFull . number_format($feeReceived)); ?>" data-hidden="0"><?php echo $cur . number_format($feeReceived); ?></span><i class="fa fa-eye toggle-money-eye" title="Show/Hide Amount"></i></div>
                <div class="kpi-change flat">Today</div>
                <span class="kpi-spark" data-spark="<?php echo e(json_encode($fee7)); ?>" data-type="bar" data-color="#7C3AED" title="Fee collections &middot; last 7 days"></span>
            </a>

            <a class="kpi-card" href="<?php echo BASE_URL; ?>manage_expenses.php" target="_blank">
                <span class="kpi-accent" style="background:linear-gradient(90deg,#F97316,#FBBF24);"></span>
                <div class="kpi-top">
                    <div class="kpi-icon" style="background:#FFF3E6;color:#F97316;"><i class="fa fa-receipt"></i></div>
                    <div class="kpi-label">Monthly Expenses</div>
                </div>
                <div class="kpi-value"><span class="money-value" data-full="<?php echo e($curFull . number_format($monthlyExpenses)); ?>" data-hidden="0"><?php echo $cur . number_format($monthlyExpenses); ?></span><i class="fa fa-eye toggle-money-eye" title="Show/Hide Amount"></i></div>
                <div class="kpi-change flat">This month</div>
                <span class="kpi-spark" data-spark="<?php echo e(json_encode($chartData['expense'])); ?>" data-type="line" data-color="#F97316" title="Expenses &middot; last 12 months"></span>
            </a>

            <a class="kpi-card" href="<?php echo BASE_URL; ?>student_inquiry.php" target="_blank">
                <span class="kpi-accent" style="background:linear-gradient(90deg,#0891B2,#22D3EE);"></span>
                <div class="kpi-top">
                    <div class="kpi-icon" style="background:#CFFAFE;color:#0891B2;"><i class="fa fa-user-plus"></i></div>
                    <div class="kpi-label">Admission Inquiry</div>
                </div>
                <div class="kpi-value"><?php echo $totalInquiries; ?></div>
                <div class="kpi-change flat">This month</div>
                <div class="kpi-spark ds-mini-stat"><?php echo $totalInquiries > 0 ? '<span class="ds-pill" style="color:#0891B2;background:#CFFAFE;">Open to new admissions</span>' : '<span class="ds-pill">No new inquiries</span>'; ?></div>
            </a>

            <div class="kpi-flip-container" id="complaintFlipCard">
                <div class="kpi-flipper">
                    <a class="kpi-card kpi-flip-face front" href="<?php echo BASE_URL; ?>manage_complaint.php" target="_blank">
                        <span class="kpi-accent" style="background:linear-gradient(90deg,#EF4444,#F87171);"></span>
                        <div class="kpi-top">
                            <div class="kpi-icon" style="background:#FEE2E2;color:#EF4444;"><i class="fa fa-comment-dots"></i></div>
                            <div class="kpi-label">Complaints</div>
                        </div>
                        <div class="kpi-value"><?php echo $totalComplaints; ?></div>
                        <div class="kpi-change flat">This month</div>
                        <div class="kpi-spark ds-mini-stat"><?php echo $totalComplaints > 0 ? '<span class="ds-pill" style="color:#EF4444;background:#FEE2E2;">' . $totalComplaints . ' reported</span>' : '<span class="ds-pill">No complaints</span>'; ?></div>
                    </a>
                    <a class="kpi-card kpi-flip-face back" href="<?php echo BASE_URL; ?>print_unpaid_fee_new.php" target="_blank">
                        <span class="kpi-accent" style="background:linear-gradient(90deg,#D97706,#FBBF24);"></span>
                        <div class="kpi-top">
                            <div class="kpi-icon" style="background:#FEF3C7;color:#D97706;"><i class="fa fa-calendar-check"></i></div>
                            <div class="kpi-label">Unpaid Challans</div>
                        </div>
                        <div class="kpi-value"><?php echo $challansUnpaid; ?></div>
                        <div class="kpi-change flat">This month</div>
                        <div class="kpi-spark ds-mini-stat"><span class="ds-pill" style="color:#D97706;background:#FEF3C7;">Collection rate <?php echo $challanPct; ?>%</span></div>
                    </a>
                </div>
            </div>
        </div>

        <div class="row2">
            <div class="aqib-card earnings-col">
                <div class="card-head">
                    <div class="card-title">
                        <span class="ico" style="background:#E8F4FF; color:#2563EB;"><i class="fa fa-chart-bar"></i></span>
                        Earnings Overview
                    </div>
                    <span class="pill-tabs">
                        <span class="pill-tab active" style="cursor:default;">Last 12 months</span>
                    </span>
                </div>
                <div class="legend-row">
                    <span><span class="legend-dot" style="background:#22C55E;"></span>Income</span>
                    <span><span class="legend-dot" style="background:#FB923C;"></span>Expenses</span>
                </div>
                <div class="earnings-body">
                    <canvas id="aqibEarningsChart"></canvas>
                </div>
            </div>

            <div class="aqib-card">
                <div class="card-head">
                    <div class="card-title">
                        <span class="ico" style="background:#DBEAFE; color:#2563EB;"><i class="fa fa-user-check"></i></span>
                        Attendance
                    </div>
                    <span class="date-chip"><?php echo date('d M, Y'); ?></span>
                </div>
                <div class="att-body">
                    <div class="att-donut-wrap">
                        <div class="att-donut" style="background: conic-gradient(#22C55E <?php echo $attPct; ?>%, #E5EAF0 0);">
                            <div class="att-donut-hole">
                                <div class="pct"><?php echo $attPct; ?>%</div>
                                <div class="lbl">Overall<br>Attendance</div>
                            </div>
                        </div>
                    </div>
                    <div class="att-stats">
                        <a href="<?php echo BASE_URL; ?>day_attendance_summary.php" class="att-stat" style="display:block; text-decoration:none; color:inherit;"><span class="p" style="color:#16A34A;"><?php echo $attTotal > 0 ? round(($attPresent / $attTotal) * 100) : 0; ?>%</span><div class="n" style="color:#16A34A;"><?php echo $attPresent; ?></div><div class="l">Present</div></a>
                        <a href="<?php echo BASE_URL; ?>day_attendance_summary.php" class="att-stat" style="display:block; text-decoration:none; color:inherit;"><span class="p" style="color:#DC2626;"><?php echo $attTotal > 0 ? round(($attAbsent / $attTotal) * 100) : 0; ?>%</span><div class="n" style="color:#DC2626;"><?php echo $attAbsent; ?></div><div class="l">Absent</div></a>
                        <a href="<?php echo BASE_URL; ?>day_attendance_summary.php" class="att-stat" style="display:block; text-decoration:none; color:inherit;"><span class="p" style="color:#D97706;"><?php echo $attTotal > 0 ? round(($attLeave / $attTotal) * 100) : 0; ?>%</span><div class="n" style="color:#D97706;"><?php echo $attLeave; ?></div><div class="l">Leave</div></a>
                        <a href="<?php echo BASE_URL; ?>day_attendance_summary.php" class="att-stat" style="display:block; text-decoration:none; color:inherit;"><span class="p" style="color:#2563EB;"><?php echo $attTotal > 0 ? round(($attLate / $attTotal) * 100) : 0; ?>%</span><div class="n" style="color:#2563EB;"><?php echo $attLate; ?></div><div class="l">Late</div></a>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>day_attendance_summary.php" class="view-details">View Details &rarr;</a>
            </div>
        </div>

        <div class="row3">
            <div class="aqib-card">
                <div class="card-head" style="padding-bottom:0;">
                    <div>
                        <div class="stu-total-lbl">Total Students</div>
                        <div class="stu-total-val"><?php echo $totalStudents; ?></div>
                    </div>
                    <span class="stu-badge">Active Students</span>
                </div>
                <div class="r3-body" style="padding-top:0;">
                    <div class="stu-donut-wrap">
                        <div class="stu-donut" style="background: conic-gradient(#22C55E 0 <?php echo $studentPctBoys; ?>%, #FB923C <?php echo $studentPctBoys; ?>% 100%);">
                            <div class="stu-donut-hole"><i class="fa fa-user-graduate"></i></div>
                        </div>
                    </div>
                    <div class="stu-legend">
                        <span><span class="legend-dot" style="background:#22C55E;"></span>Boys <span class="n"><?php echo $boys; ?></span></span>
                        <span><span class="legend-dot" style="background:#FB923C;"></span>Girls <span class="n"><?php echo $girls; ?></span></span>
                    </div>
                    <div style="text-align:center; margin-top:8px;"><span class="ds-pill"><?php echo $studentPctBoys; ?>% boys &middot; <?php echo $studentPctGirls; ?>% girls</span></div>
                </div>
                <a href="<?php echo BASE_URL; ?>manage_students.php" target="_blank" class="view-details">View Details &rarr;</a>
            </div>

            <div class="aqib-card">
                <div class="card-head" style="padding-bottom:0;">
                    <div class="card-title" style="font-size:14px;">
                        <span class="ico" style="background:#FCE7F3; color:#EC4899;"><i class="fa fa-birthday-cake"></i></span>
                        Birthdays Today
                    </div>
                    <span class="date-chip"><?php echo date('d M'); ?></span>
                </div>
                <div class="bday-body">
                    <?php if (count($birthdays) > 0): ?>
                        <div class="bday-list">
                            <?php foreach ($birthdays as $b): ?>
                                <div class="bday-row">
                                    <div class="bday-avatar"><i class="fa fa-user"></i></div>
                                    <div>
                                        <div class="bday-name"><?php echo e($b['first_name'] . ' ' . ($b['father_name'] ? ' Mr. ' . $b['father_name'] : '')); ?></div>
                                        <div class="bday-class"><?php echo e($b['class_name'] ?? ''); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="bday-sub">No birthdays today.<br>Nothing to celebrate &mdash; yet.</div>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>student_birthday.php" class="bday-btn">View All &rarr;</a>
                </div>
            </div>

            <div class="aqib-card">
                <div class="card-head">
                    <div class="card-title" style="font-size:14px;">
                        <span class="ico" style="background:#DCFCE7; color:#16A34A;"><i class="fa fa-file-invoice"></i></span>
                        Challan / Fee Status
                    </div>
                    <span class="date-chip"><?php echo date('M Y'); ?></span>
                </div>
                <div class="r3-body">
                    <div class="chl-donut-wrap">
                        <div class="chl-donut" style="background: conic-gradient(#16A34A 0 <?php echo $challanPct; ?>%, #F59E0B <?php echo $challanPct; ?>% <?php echo $challanPct + $challansUnpaid > 0 ? min(100, $challanPct + ($challansUnpaid / max(1,$challansTotal)) * 100) : $challanPct; ?>%, #EF4444 <?php echo $challanPct; ?>% 100%);">
                            <div class="chl-donut-hole"><div class="pct"><?php echo $challanPct; ?>%</div><div class="lbl">Paid</div></div>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>fee_challans.php" target="_blank" class="chl-legend-item" style="text-decoration:none;"><span><span class="legend-dot" style="background:#16A34A;"></span>Paid</span><span class="n"><?php echo $challansPaid; ?></span></a>
                    <a href="<?php echo BASE_URL; ?>fee_challans.php" target="_blank" class="chl-legend-item" style="text-decoration:none;"><span><span class="legend-dot" style="background:#F59E0B;"></span>Un-Paid</span><span class="n"><?php echo $challansUnpaid; ?></span></a>
                    <div class="chl-legend-item"><span><span class="legend-dot" style="background:#EF4444;"></span>Partial</span><span class="n"><?php echo $challansPartial; ?></span></div>
                    <div class="chl-rate-lbl">Collection Rate</div>
                    <div class="chl-rate-bar"><div class="chl-rate-fill" style="width: <?php echo $challanPct; ?>%;"></div></div>
                    <div class="chl-rate-foot">
                        <span style="font-weight:800; color:#111827;"><?php echo $challanPct; ?>%</span>
                        <span style="color:#6B7280; font-weight:600;"><?php echo $challansTotal; ?> challans this month</span>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>fee_challans.php" target="_blank" class="view-details">View Details &rarr;</a>
            </div>

            <div class="aqib-card">
                <div class="card-head" style="padding-bottom:0;">
                    <div class="card-title" style="font-size:14px;">
                        <span class="ico" style="background:#EDE9FE; color:#7C3AED;"><i class="fa fa-exchange-alt"></i></span>
                        Admissions &amp; Withdrawals
                    </div>
                </div>
                <div class="pill-tabs aw-pill-tabs">
                    <button type="button" class="pill-tab aw-pill-tab active" onclick="aqibSwitchAWTab(this, 'month')">This Month</button>
                    <button type="button" class="pill-tab aw-pill-tab" onclick="aqibSwitchAWTab(this, 'year')">This Year</button>
                    <button type="button" class="pill-tab aw-pill-tab" onclick="aqibSwitchAWTab(this, 'lastyear')">Last Year</button>
                </div>
                <div class="aw-body">
                    <div class="aw-box">
                        <div class="left">
                            <div class="aw-ico" style="background:#FFF3E6; color:#F97316;"><i class="fa fa-user-plus"></i></div>
                            <div>
                                <div class="name">Admissions</div>
                                <span class="ds-pill" id="awAdmissionsSub" style="margin-top:3px;">This month</span>
                            </div>
                        </div>
                        <div class="val" id="awAdmissionsVal" style="color:#F97316;"><?php echo $admissionsMonth; ?></div>
                    </div>
                    <div class="aw-box" style="margin-bottom:0;">
                        <div class="left">
                            <div class="aw-ico" style="background:#E7F7EF; color:#16A34A;"><i class="fa fa-user-minus"></i></div>
                            <div>
                                <div class="name">Withdrawals</div>
                                <span class="ds-pill" id="awWithdrawalsSub" style="margin-top:3px;">This month</span>
                            </div>
                        </div>
                        <div class="val" id="awWithdrawalsVal" style="color:#16A34A;"><?php echo $withdrawalsMonth; ?></div>
                    </div>
                    <div class="aw-net">
                        <div>
                            <div class="n-lbl">Net Growth</div>
                            <div class="n-val" id="awNetVal" style="color:#4ADE80;">+<?php echo max(0, $admissionsMonth - $withdrawalsMonth); ?> Students</div>
                        </div>
                        <i class="fa fa-chart-line" id="awNetIcon" style="color:#4ADE80; font-size:20px;"></i>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>manage_students.php" target="_blank" id="awViewDetails" class="view-details">View Details &rarr;</a>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var awData = {
    month: { adm: <?php echo $admissionsMonth; ?>, wd: <?php echo $withdrawalsMonth; ?>, lbl: 'This month' },
    year: { adm: <?php echo $admissionsYear; ?>, wd: <?php echo $withdrawalsYear; ?>, lbl: 'This year' },
    lastyear: { adm: <?php echo $admissionsLastYear; ?>, wd: <?php echo $withdrawalsLastYear; ?>, lbl: 'Last year' }
};
function aqibSwitchAWTab(btn, key) {
    document.querySelectorAll('.aw-pill-tab').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    var d = awData[key];
    document.getElementById('awAdmissionsVal').textContent = d.adm;
    document.getElementById('awWithdrawalsVal').textContent = d.wd;
    document.getElementById('awAdmissionsSub').textContent = d.lbl;
    document.getElementById('awWithdrawalsSub').textContent = d.lbl;
    var net = d.adm - d.wd;
    var el = document.getElementById('awNetVal');
    el.textContent = (net >= 0 ? '+' : '') + net + ' Students';
    el.style.color = net >= 0 ? '#4ADE80' : '#F87171';
    document.getElementById('awNetIcon').style.color = net >= 0 ? '#4ADE80' : '#F87171';
}

var chart = document.getElementById('aqibEarningsChart');
if (chart) {
    new Chart(chart, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [
                {
                    label: 'Income',
                    data: <?php echo json_encode($chartData['income']); ?>,
                    borderColor: '#22C55E',
                    backgroundColor: 'rgba(34,197,94,0.08)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3
                },
                {
                    label: 'Expenses',
                    data: <?php echo json_encode($chartData['expense']); ?>,
                    borderColor: '#FB923C',
                    backgroundColor: 'rgba(251,146,60,0.08)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#F3F4F6' } },
                x: { grid: { display: false } }
            }
        }
    });
}

var flip = document.getElementById('complaintFlipCard');
if (flip) {
    setInterval(function(){
        flip.classList.toggle('flipped');
    }, 3000);
}

/* Real-data sparklines (jQuery Sparkline loaded in footer) */
window.addEventListener('load', function(){
    if (!window.jQuery || !jQuery.fn.sparkline) return;
    jQuery('.kpi-spark[data-spark]').each(function(){
        var $el = jQuery(this);
        var raw = $el.attr('data-spark');
        if (!raw) return;
        try { var data = JSON.parse(raw); } catch(e) { return; }
        var type = $el.attr('data-type') === 'bar' ? 'bar' : 'line';
        var color = $el.attr('data-color') || '#2563EB';
        $el.sparkline(data, {
            type: type,
            height: '28px',
            width: '100%',
            lineColor: color,
            fillColor: type === 'line' ? 'rgba(37,99,235,0.10)' : false,
            fillOpacity: 0.12,
            spotColor: false,
            minSpotColor: false,
            maxSpotColor: false,
            highlightSpotColor: color,
            barColor: color,
            barWidth: 6,
            barSpacing: 3,
            chartRangeMin: 0,
            tooltipFormat: '{{offset|prefix}} {{value}}',
            disableInteraction: true
        });
    });
});

var moneyEls = document.querySelectorAll('.money-value');
var MASK = '••••••';

function maskMoney(el, hidden) {
    if (hidden) { el.textContent = MASK; el.setAttribute('data-hidden', '1'); }
    else { el.textContent = el.getAttribute('data-full'); el.setAttribute('data-hidden', '0'); }
    var icon = el.nextElementSibling;
    if (icon && icon.classList.contains('toggle-money-eye')) {
        icon.className = 'fa fa-' + (hidden ? 'eye' : 'eye-slash') + ' toggle-money-eye';
    }
}
function allMoneyHidden() {
    var all = true;
    moneyEls.forEach(function(el){ if (el.getAttribute('data-hidden') === '0') all = false; });
    return all;
}
function syncMoneyToggleBtn() {
    var allHidden = allMoneyHidden();
    var icon = document.getElementById('moneyToggleIcon');
    var lbl  = document.getElementById('moneyToggleLbl');
    if (icon) { icon.className = allHidden ? 'fa fa-eye' : 'fa fa-eye-slash'; }
    if (lbl)  { lbl.textContent = allHidden ? 'Show Values' : 'Hide Values'; }
}
function applyMoneyMask(hidden) {
    moneyEls.forEach(function(el){ maskMoney(el, hidden); });
    syncMoneyToggleBtn();
}
function toggleMoneyVisibility() {
    var allHidden = allMoneyHidden();
    applyMoneyMask(!allHidden);
    localStorage.setItem('hid_money', allMoneyHidden() ? '1' : '0');
}

var moneyHidden = localStorage.getItem('hid_money') === '1';
if (moneyHidden) applyMoneyMask(true);
syncMoneyToggleBtn();

document.addEventListener('click', function(e) {
    var icon = e.target.closest ? e.target.closest('.toggle-money-eye') : null;
    if (!icon) return;
    e.preventDefault();
    e.stopPropagation();
    var el = icon.previousElementSibling;
    if (!el || !el.classList.contains('money-value')) return;
    var hidden = el.getAttribute('data-hidden') !== '0';
    maskMoney(el, !hidden);
    syncMoneyToggleBtn();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>