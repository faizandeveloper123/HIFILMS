<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
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

function money($v) {
    return number_format($v);
}

include __DIR__ . '/includes/header.php';
?>
<style>
    .aqib-dash { padding-top: 10px; padding-bottom: 30px; }
    .aqib-dash * { box-sizing: border-box; }
    .aqib-card {
        background: #fff;
        border: 1px solid #E5E7EB;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(16,24,40,0.06);
    }
    .aqib-card:hover { box-shadow: 0 8px 24px rgba(15,23,42,0.08); }
    .kpi-row {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 14px;
        margin-bottom: 16px;
    }
    .kpi-card { padding: 18px; position: relative; overflow: hidden; }
    .kpi-top { display: flex; align-items: center; gap: 11px; min-width: 0; }
    .kpi-icon {
        width: 42px; height: 42px; border-radius: 13px;
        display: flex; align-items: center; justify-content: center;
        font-size: 17px; flex-shrink: 0;
    }
    .kpi-label { font-size: 12.5px; color: #6B7280; font-weight: 600; min-width: 0; overflow-wrap: break-word; }
    .kpi-badge {
        font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px;
        display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;
    }
    .kpi-badge.up { color: #16A34A; background: #E7F8EE; }
    .kpi-badge.down { color: #DC2626; background: #FDECEC; }
    .kpi-badge.flat { color: #6B7280; background: #F3F4F6; }
    .kpi-value { font-size: 23px; font-weight: 800; color: #111827; margin-top: 14px; line-height: 1.2; overflow-wrap: break-word; word-break: break-word; }
    .kpi-change { font-size: 12px; font-weight: 700; margin-top: 5px; display: flex; flex-wrap: wrap; align-items: center; gap: 4px; }
    .kpi-change.up { color: #16A34A; }
    .kpi-change.down { color: #DC2626; }
    .kpi-change.flat { color: #9CA3AF; font-weight: 500; }
    .kpi-change .sub { color: #9CA3AF; font-weight: 500; margin-left: 1px; }
    .kpi-spark { margin-top: 12px; height: 30px; line-height: 0; margin-left: -18px; margin-right: -18px; margin-bottom: -18px; }
    a.kpi-card { color: inherit; text-decoration: none; }
    .kpi-flip-container { position: relative; perspective: 1000px; height: 100%; }
    .kpi-flipper {
        display: grid;
        grid-template-areas: "stack";
        width: 100%; height: 100%;
        transition: transform 0.6s;
        transform-style: preserve-3d;
    }
    .kpi-flip-container.flipped .kpi-flipper { transform: rotateY(180deg); }
    .kpi-flip-face {
        grid-area: stack;
        width: 100%; height: 100%;
        backface-visibility: hidden; -webkit-backface-visibility: hidden;
    }
    .kpi-flip-face.front { z-index: 2; }
    .kpi-flip-face.back { transform: rotateY(180deg); }
    .row2 {
        display: grid;
        grid-template-columns: 2.6fr 1fr;
        gap: 14px;
        margin-bottom: 14px;
        align-items: stretch;
    }
    .card-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 16px 18px 0 18px;
    }
    .card-title { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 700; color: #111827; }
    .card-title .ico { width: 30px; height: 30px; border-radius: 9px; display:flex; align-items:center; justify-content:center; font-size: 13px; }
    .pill-tabs { display: flex; gap: 4px; background: #F3F4F6; padding: 3px; border-radius: 999px; }
    .pill-tab {
        border: none; background: transparent; font-size: 11.5px; font-weight: 600; color: #6B7280;
        padding: 5px 12px; border-radius: 999px; cursor: pointer; transition: all .15s ease;
    }
    .pill-tab.active { background: #FF7A1B; color: #fff; box-shadow: 0 2px 6px rgba(255,122,27,.35); }
    .legend-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; margin-right: 6px; }
    .legend-row { display: flex; align-items: center; gap: 16px; font-size: 12px; color: #6B7280; padding: 8px 18px 0 18px; }
    .earnings-col { display: flex; flex-direction: column; }
    .earnings-body { padding: 6px 14px 18px 14px; flex: 1 1 auto; min-height: 220px; }
    .att-body { padding: 14px 18px 18px 18px; }
    .att-donut-wrap { display: flex; align-items: center; justify-content: center; margin: 6px 0 14px 0; }
    .att-donut {
        width: 132px; height: 132px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        position: relative;
        box-shadow: inset 0 0 0 1px #E5E7EB;
    }
    .att-donut::before {
        content: ""; position: absolute; width: 10px; height: 10px; background: #fff;
        border-radius: 50%; top: 6px; left: 50%; transform: translateX(-50%);
        box-shadow: 0 0 0 3px #fff;
    }
    .att-donut-hole {
        width: 98px; height: 98px; border-radius: 50%; background: #fff;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        box-shadow: inset 0 0 0 1px #F0F2F5;
    }
    .att-donut-hole .pct { font-size: 20px; font-weight: 800; color: #16A34A; line-height: 1.1; }
    .att-donut-hole .lbl { font-size: 9.5px; color: #9CA3AF; text-align: center; line-height: 1.2; margin-top: 2px; }
    .att-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .att-stat { border: 1px solid #E5E7EB; border-radius: 10px; padding: 8px 10px; }
    .att-stat .n { font-size: 15px; font-weight: 800; }
    .att-stat .l { font-size: 10.5px; color: #6B7280; }
    .att-stat .p { font-size: 10px; font-weight: 700; float: right; }
    .aw-body { padding: 4px 18px 18px 18px; }
    .aw-pill-tabs { padding: 10px 18px 0 18px; width: 100%; }
    .aw-pill-tabs .pill-tab { flex: 1; text-align: center; padding: 6px 4px; white-space: nowrap; }
    .aw-box {
        display: flex; align-items: center; justify-content: space-between;
        border: 1px solid #E5E7EB; border-radius: 12px; padding: 10px 12px; margin-bottom: 10px;
    }
    .aw-box .left { display: flex; align-items: center; gap: 10px; }
    .aw-ico { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
    .aw-box .name { font-size: 12.5px; font-weight: 700; color: #111827; }
    .aw-box .val { font-size: 17px; font-weight: 800; }
    .aw-net {
        background: linear-gradient(135deg,#111827,#1F2937);
        border-radius: 12px; padding: 12px 14px; color: #fff;
        display: flex; align-items: center; justify-content: space-between; margin-top: 12px;
    }
    .aw-net .n-lbl { font-size: 11px; color: #9CA3AF; }
    .aw-net .n-val { font-size: 17px; font-weight: 800; color: #4ADE80; }
    .view-details { display: block; text-align: right; padding: 6px 18px 16px 18px; font-size: 12px; color: #6B7280; text-decoration: none; font-weight: 600; }
    .view-details:hover { color: #FF7A1B; text-decoration: none; }
    .row3 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
    .r3-body { padding: 16px 18px; }
    .stu-total-lbl { font-size: 12.5px; color: #6B7280; }
    .stu-total-val { font-size: 24px; font-weight: 800; color: #111827; }
    .stu-badge { font-size: 10.5px; font-weight: 700; color: #16A34A; background: #E7F8EE; padding: 4px 9px; border-radius: 999px; }
    .stu-donut-wrap { display: flex; justify-content: center; margin: 14px 0; }
    .stu-donut {
        width: 118px; height: 118px; border-radius: 50%;
        background: conic-gradient(#22C55E 0 51%, #FB923C 51% 100%);
        display: flex; align-items: center; justify-content: center;
    }
    .stu-donut-hole { width: 84px; height: 84px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #9CA3AF; }
    .stu-legend { display: flex; justify-content: space-between; font-size: 12px; color: #374151; margin-top: 4px; }
    .stu-legend .n { font-weight: 800; color: #111827; }
    .bday-body { text-align: center; padding: 18px; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; }
    .bday-title { font-size: 14.5px; font-weight: 800; color: #111827; }
    .bday-sub { font-size: 12px; color: #6B7280; margin-top: 4px; }
    .bday-btn {
        margin-top: 14px; font-size: 12px; font-weight: 700; color: #FF7A1B;
        border: 1px solid #FFD9B3; background: #FFF7ED; padding: 7px 16px; border-radius: 999px; text-decoration: none; display: inline-block;
    }
    .bday-btn:hover { background: #FF7A1B; color: #fff; text-decoration: none; }
    .bday-list { width: 100%; padding: 4px 4px 0 4px; }
    .bday-row { display: flex; align-items: center; gap: 10px; padding: 8px 4px; border-bottom: 1px solid #F3F4F6; text-align: left; }
    .bday-row:last-child { border-bottom: none; }
    .bday-avatar { width: 32px; height: 32px; border-radius: 50%; background: #FFE0EC; color: #EC4899; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; overflow: hidden; }
    .bday-avatar img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
    .bday-name { font-size: 12.5px; font-weight: 700; color: #111827; }
    .bday-class { font-size: 11px; color: #6B7280; }
    .chl-donut-wrap { display: flex; justify-content: center; margin: 6px 0 12px 0; }
    .chl-donut {
        width: 108px; height: 108px; border-radius: 50%;
        background: conic-gradient(#16A34A 0 78%, #F59E0B 78% 95%, #EF4444 95% 100%);
        display: flex; align-items: center; justify-content: center;
    }
    .chl-donut-hole { width: 76px; height: 76px; border-radius: 50%; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .chl-donut-hole .pct { font-size: 17px; font-weight: 800; color: #16A34A; }
    .chl-donut-hole .lbl { font-size: 9.5px; color: #9CA3AF; }
    .chl-legend-item { display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: #374151; padding: 4px 0; }
    .chl-legend-item .n { font-weight: 800; color: #111827; }
    .chl-rate-lbl { font-size: 11.5px; color: #6B7280; margin-top: 10px; }
    .chl-rate-bar { height: 7px; border-radius: 999px; background: #F3F4F6; margin-top: 6px; overflow: hidden; }
    .chl-rate-fill { height: 100%; width: 78%; background: linear-gradient(90deg,#22C55E,#16A34A); border-radius: 999px; }
    .chl-rate-foot { display: flex; justify-content: space-between; font-size: 11px; margin-top: 6px; }
    @media (max-width: 1400px) {
        .kpi-row { grid-template-columns: repeat(3, 1fr); }
        .row2 { grid-template-columns: 1fr; }
        .row3 { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 768px) {
        .kpi-row { grid-template-columns: repeat(2, 1fr); }
        .row3 { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
        .kpi-row { grid-template-columns: 1fr; gap: 10px; }
        .kpi-card { padding: 14px; }
        .kpi-value { font-size: 20px; }
        .att-stats { grid-template-columns: 1fr 1fr; gap: 6px; }
    }
.kpi-value .toggle-money-eye { font-size: 13px; color: #9CA3AF; cursor: pointer; margin-left: 8px; vertical-align: middle; }
        .kpi-value .toggle-money-eye:hover { color: #377DFF; }
    </style>

<div class="main-content">
    <div class="container-fluid">
        <div class="aqib-dash">

            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:4px 4px 14px;">
                <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-tachometer"></i> Executive Dashboard</h3>
                <button type="button" id="moneyToggle" class="btn btn-default" style="background:#fff; border:1px solid #E5E7EB; border-radius:999px; padding:7px 16px; font-weight:700; color:#374151;" onclick="toggleMoneyVisibility()">
                    <i class="fa fa-eye" id="moneyToggleIcon"></i> <span id="moneyToggleLbl">Hide Values</span>
                </button>
            </div>

            <?php
            $campuses = get_campuses();
            $actCampus = get_active_campus();
            if (count($campuses) === 0 || !$actCampus):
            ?>
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; background:linear-gradient(120deg,#FFF3E0,#FFE8CC); border:1px solid #FFD9A0; border-radius:14px; padding:14px 18px; margin:0 0 14px;">
                <div>
                    <strong style="font-size:14px; color:#7A4E12;"><i class="fa fa-exclamation-triangle"></i> School Info not set yet!</strong>
                    <div style="font-size:12.5px; color:#9A6B24; margin-top:3px;">Add your school name, logo, address & phone — it will appear automatically on all student cards, family cards, card back sides, certificates and reports.</div>
                </div>
                <a href="<?php echo BASE_URL; ?>manage_schools.php" class="btn" style="background:#FF7800; color:#fff; font-weight:700; border-radius:999px; padding:9px 20px; text-decoration:none;">Set Up School Info →</a>
            </div>
            <?php else: ?>
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:12px 16px; margin:0 0 14px; box-shadow:0 1px 3px rgba(0,0,0,.04);">
                <div style="display:flex; align-items:center; gap:12px;">
                    <?php if (!empty($actCampus['logo'])): ?>
                        <img src="<?php echo BASE_URL . e($actCampus['logo']); ?>" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid #F3F4F6;" onerror="this.style.display='none';">
                    <?php else: ?>
                        <div style="width:44px;height:44px;border-radius:50%;background:#FFE9D6;color:#FF7800;display:flex;align-items:center;justify-content:center;font-size:17px;"><i class="fa fa-university"></i></div>
                    <?php endif; ?>
                    <div>
                        <div style="font-size:14px; font-weight:800; color:#111827;"><?php echo e($actCampus['name']); ?></div>
                        <div style="font-size:12px; color:#6B7280;"><?php echo e($actCampus['address']); ?><?php echo trim((string)$actCampus['phone']) !== '' ? ' &nbsp;|&nbsp; ' . e($actCampus['phone']) : ''; ?></div>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>manage_schools.php" class="btn" style="background:#FF7800; color:#fff; font-weight:700; border-radius:999px; padding:8px 18px; text-decoration:none; font-size:13px;"><i class="fa fa-pencil"></i> Edit School Info</a>
            </div>
            <?php endif; ?>

            <div class="kpi-row">
                <a class="aqib-card kpi-card" href="<?php echo BASE_URL; ?>datewise_fee_collection_report_new.php" target="_blank">
                    <div class="kpi-top">
                        <div class="kpi-icon" style="background:#E9F2FF; color:#377DFF;"><i class="fa fa-wallet"></i></div>
                        <div class="kpi-label">Fee Received</div>
                    </div>
                    <div class="kpi-value"><span class="money-value" data-full="<?php echo e(number_format($feeReceived)); ?>" data-hidden="0"><?php echo e(get_setting('currency_symbol', 'Rs.') . number_format($feeReceived)); ?></span><i class="fa fa-eye toggle-money-eye" title="Show/Hide Amount"></i></div>
                    <div class="kpi-change flat">Today</div>
                    <div class="kpi-spark">
                        <svg width="100%" height="30" viewBox="0 0 120 30" preserveAspectRatio="none">
                            <defs><linearGradient id="sg1" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#377DFF" stop-opacity="0.28"></stop><stop offset="100%" stop-color="#377DFF" stop-opacity="0"></stop></linearGradient></defs>
                            <path d="M0,22 20,18 40,21 60,11 80,15 100,7 120,10 V30 H0 Z" fill="url(#sg1)"></path>
                            <polyline points="0,22 20,18 40,21 60,11 80,15 100,7 120,10" fill="none" stroke="#377DFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>
                        </svg>
                    </div>
                </a>

                <a class="aqib-card kpi-card" href="<?php echo BASE_URL; ?>print_unpaid_fee_new.php" target="_blank">
                    <div class="kpi-top">
                        <div class="kpi-icon" style="background:#D7F5E7; color:#16A34A;"><i class="fa fa-file-invoice-dollar"></i></div>
                        <div class="kpi-label">Fee Receivable</div>
                    </div>
                    <div class="kpi-value"><span class="money-value" data-full="<?php echo e(number_format($feeReceivable)); ?>" data-hidden="0"><?php echo e(get_setting('currency_symbol', 'Rs.') . number_format($feeReceivable)); ?></span><i class="fa fa-eye toggle-money-eye" title="Show/Hide Amount"></i></div>
                    <div class="kpi-change flat">Outstanding balance</div>
                    <div class="kpi-spark">
                        <svg width="100%" height="30" viewBox="0 0 120 30" preserveAspectRatio="none">
                            <defs><linearGradient id="sg2" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#16A34A" stop-opacity="0.28"></stop><stop offset="100%" stop-color="#16A34A" stop-opacity="0"></stop></linearGradient></defs>
                            <path d="M0,9 20,13 40,11 60,18 80,16 100,21 120,17 V30 H0 Z" fill="url(#sg2)"></path>
                            <polyline points="0,9 20,13 40,11 60,18 80,16 100,21 120,17" fill="none" stroke="#16A34A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>
                        </svg>
                    </div>
                </a>

                <a class="aqib-card kpi-card" href="<?php echo BASE_URL; ?>datewise_fee_collection_report_new.php" target="_blank">
                    <div class="kpi-top">
                        <div class="kpi-icon" style="background:#EADAFF; color:#9747FF;"><i class="fa fa-coins"></i></div>
                        <div class="kpi-label">Today's Collection</div>
                    </div>
                    <div class="kpi-value"><span class="money-value" data-full="<?php echo e(number_format($feeReceived)); ?>" data-hidden="0"><?php echo e(get_setting('currency_symbol', 'Rs.') . number_format($feeReceived)); ?></span><i class="fa fa-eye toggle-money-eye" title="Show/Hide Amount"></i></div>
                    <div class="kpi-change flat">Today</div>
                    <div class="kpi-spark">
                        <svg width="100%" height="30" viewBox="0 0 120 30" preserveAspectRatio="none">
                            <defs><linearGradient id="sg3" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#9747FF" stop-opacity="0.28"></stop><stop offset="100%" stop-color="#9747FF" stop-opacity="0"></stop></linearGradient></defs>
                            <path d="M0,20 20,21 40,16 60,17 80,10 100,12 120,4 V30 H0 Z" fill="url(#sg3)"></path>
                            <polyline points="0,20 20,21 40,16 60,17 80,10 100,12 120,4" fill="none" stroke="#9747FF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>
                        </svg>
                    </div>
                </a>

                <a class="aqib-card kpi-card" href="<?php echo BASE_URL; ?>manage_expenses.php" target="_blank">
                    <div class="kpi-top">
                        <div class="kpi-icon" style="background:#FFE5D1; color:#FF7C1B;"><i class="fa fa-receipt"></i></div>
                        <div class="kpi-label">Monthly Expenses</div>
                    </div>
                    <div class="kpi-value"><span class="money-value" data-full="<?php echo e(number_format($monthlyExpenses)); ?>" data-hidden="0"><?php echo e(get_setting('currency_symbol', 'Rs.') . number_format($monthlyExpenses)); ?></span><i class="fa fa-eye toggle-money-eye" title="Show/Hide Amount"></i></div>
                    <div class="kpi-change flat">This month</div>
                    <div class="kpi-spark">
                        <svg width="100%" height="30" viewBox="0 0 120 30" preserveAspectRatio="none">
                            <defs><linearGradient id="sg4" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FF7C1B" stop-opacity="0.28"></stop><stop offset="100%" stop-color="#FF7C1B" stop-opacity="0"></stop></linearGradient></defs>
                            <path d="M0,7 20,11 40,10 60,17 80,15 100,23 120,19 V30 H0 Z" fill="url(#sg4)"></path>
                            <polyline points="0,7 20,11 40,10 60,17 80,15 100,23 120,19" fill="none" stroke="#FF7C1B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>
                        </svg>
                    </div>
                </a>

                <a class="aqib-card kpi-card" href="<?php echo BASE_URL; ?>student_inquiry.php" target="_blank">
                    <div class="kpi-top">
                        <div class="kpi-icon" style="background:#D3F3E4; color:#22C55E;"><i class="fa fa-user-plus"></i></div>
                        <div class="kpi-label">Admission Inquiry</div>
                    </div>
                    <div class="kpi-value"><?php echo $totalInquiries; ?></div>
                    <div class="kpi-change flat">This month</div>
                    <div class="kpi-spark">
                        <svg width="100%" height="30" viewBox="0 0 120 30" preserveAspectRatio="none">
                            <defs><linearGradient id="sg5" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#22C55E" stop-opacity="0.28"></stop><stop offset="100%" stop-color="#22C55E" stop-opacity="0"></stop></linearGradient></defs>
                            <path d="M0,23 20,20 40,22 60,15 80,17 100,9 120,12 V30 H0 Z" fill="url(#sg5)"></path>
                            <polyline points="0,23 20,20 40,22 60,15 80,17 100,9 120,12" fill="none" stroke="#22C55E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>
                        </svg>
                    </div>
                </a>

                <div class="kpi-flip-container" id="complaintFlipCard">
                    <div class="kpi-flipper">
                        <a class="aqib-card kpi-card kpi-flip-face front" href="<?php echo BASE_URL; ?>manage_complaint.php" target="_blank">
                            <div class="kpi-top">
                                <div class="kpi-icon" style="background:#FFD4D1; color:#FF261B;"><i class="fa fa-comment-dots"></i></div>
                                <div class="kpi-label">Complaints</div>
                            </div>
                            <div class="kpi-value"><?php echo $totalComplaints; ?></div>
                            <div class="kpi-change flat">This month</div>
                            <div class="kpi-spark">
                                <svg width="100%" height="30" viewBox="0 0 120 30" preserveAspectRatio="none">
                                    <defs><linearGradient id="sg6" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FF261B" stop-opacity="0.22"></stop><stop offset="100%" stop-color="#FF261B" stop-opacity="0"></stop></linearGradient></defs>
                                    <path d="M0,18 20,17 40,19 60,16 80,18 100,15 120,17 V30 H0 Z" fill="url(#sg6)"></path>
                                    <polyline points="0,18 20,17 40,19 60,16 80,18 100,15 120,17" fill="none" stroke="#FF261B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>
                                </svg>
                            </div>
                        </a>
                        <a class="aqib-card kpi-card kpi-flip-face back" href="<?php echo BASE_URL; ?>print_unpaid_fee_new.php" target="_blank">
                            <div class="kpi-top">
                                <div class="kpi-icon" style="background:#FFF4EC; color:#FF7C1B;"><i class="fa fa-calendar-check"></i></div>
                                <div class="kpi-label">Today's Commitments</div>
                            </div>
                            <div class="kpi-value"><?php echo $challansUnpaid; ?></div>
                            <div class="kpi-change flat">Unpaid challans today</div>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row2">
                <div class="aqib-card earnings-col">
                    <div class="card-head">
                        <div class="card-title">
                            <span class="ico" style="background:#FFE5D1; color:#FF7C1B;"><i class="fa fa-chart-bar"></i></span>
                            Earnings Overview
                        </div>
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
                            <span class="ico" style="background:#D7DEF3; color:#377DFF;"><i class="fa fa-user-check"></i></span>
                            Attendance
                        </div>
                        <span style="font-size:11px; color:#6B7280; background:#F3F4F6; border:1px solid #E5E7EB; padding:4px 9px; border-radius:999px; white-space:nowrap;"><?php echo date('d M, Y'); ?></span>
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
                            <a href="<?php echo BASE_URL; ?>day_attendance_summary.php" class="att-stat" style="display:block; text-decoration:none; color:inherit;"><span class="p" style="color:#F59E0B;"><?php echo $attTotal > 0 ? round(($attLeave / $attTotal) * 100) : 0; ?>%</span><div class="n" style="color:#F59E0B;"><?php echo $attLeave; ?></div><div class="l">Leave</div></a>
                            <a href="<?php echo BASE_URL; ?>day_attendance_summary.php" class="att-stat" style="display:block; text-decoration:none; color:inherit;"><span class="p" style="color:#377DFF;"><?php echo $attTotal > 0 ? round(($attLate / $attTotal) * 100) : 0; ?>%</span><div class="n" style="color:#377DFF;"><?php echo $attLate; ?></div><div class="l">Late</div></a>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>day_attendance_summary.php" class="view-details">View Details →</a>
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
                            <span><span class="legend-dot" style="background:#22C55E;"></span><span class="n"><?php echo $boys; ?></span> Boys <?php echo $studentPctBoys; ?>%</span>
                            <span><span class="legend-dot" style="background:#FB923C;"></span><span class="n"><?php echo $girls; ?></span> Girls <?php echo $studentPctGirls; ?>%</span>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>manage_students.php" target="_blank" class="view-details">View Details →</a>
                </div>

                <div class="aqib-card" style="display:flex; flex-direction:column;">
                    <div class="card-head" style="padding-bottom:0;">
                        <div class="card-title" style="font-size:14px;">
                            <span class="ico" style="background:#FFE0EC; color:#EC4899;"><i class="fa fa-birthday-cake"></i></span>
                            Birthday's Today
                        </div>
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
                            <div class="bday-sub">No birthdays today</div>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>student_birthday.php" class="bday-btn">View All →</a>
                    </div>
                </div>

                <div class="aqib-card">
                    <div class="card-head">
                        <div class="card-title" style="font-size:14px;">
                            <span class="ico" style="background:#D7F5E7; color:#16A34A;"><i class="fa fa-file-invoice"></i></span>
                            Challan / Fee Status
                        </div>
                        <span style="font-size:11px; color:#6B7280; background:#F3F4F6; border:1px solid #E5E7EB; padding:4px 9px; border-radius:999px; white-space:nowrap;"><?php echo date('M Y'); ?></span>
                    </div>
                    <div class="r3-body">
                        <div class="chl-donut-wrap">
                            <div class="chl-donut" style="background: conic-gradient(#16A34A 0 <?php echo $challanPct; ?>%, #F59E0B <?php echo $challanPct; ?>% <?php echo $challanPct + $challansUnpaid > 0 ? min(100, $challanPct + ($challansUnpaid / max(1,$challansTotal)) * 100) : $challanPct; ?>%, #EF4444 <?php echo $challanPct; ?>% 100%);">
                                <div class="chl-donut-hole"><div class="pct"><?php echo $challanPct; ?>%</div><div class="lbl">Paid</div></div>
                            </div>
                        </div>
                        <a href="<?php echo BASE_URL; ?>fee_challans.php" target="_blank" class="chl-legend-item" style="text-decoration:none;"><span><span class="legend-dot" style="background:#16A34A;"></span>Paid <?php echo $challanPct; ?>%</span><span class="n"><?php echo $challansPaid; ?></span></a>
                        <a href="<?php echo BASE_URL; ?>fee_challans.php" target="_blank" class="chl-legend-item" style="text-decoration:none;"><span><span class="legend-dot" style="background:#F59E0B;"></span>Un-Paid <?php echo $challansTotal > 0 ? round(($challansUnpaid / $challansTotal) * 100) : 0; ?>%</span><span class="n"><?php echo $challansUnpaid; ?></span></a>
                        <div class="chl-legend-item"><span><span class="legend-dot" style="background:#EF4444;"></span>Partially <?php echo $challansTotal > 0 ? round(($challansPartial / $challansTotal) * 100) : 0; ?>%</span><span class="n"><?php echo $challansPartial; ?></span></div>
                        <div class="chl-rate-lbl">Collection Rate</div>
                        <div class="chl-rate-bar"><div class="chl-rate-fill" style="width: <?php echo $challanPct; ?>%;"></div></div>
                        <div class="chl-rate-foot">
                            <span style="font-weight:800; color:#111827;"><?php echo $challanPct; ?>%</span>
                            <span style="color:#6B7280; font-weight:600;"><?php echo $challansTotal; ?> challans this month</span>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>fee_challans.php" target="_blank" class="view-details">View Details →</a>
                </div>

                <div class="aqib-card">
                    <div class="card-head" style="padding-bottom:0;">
                        <div class="card-title" style="font-size:14px;">
                            <span class="ico" style="background:#EADAFF; color:#9747FF;"><i class="fa fa-exchange-alt"></i></span>
                            Admissions &amp; Withdrawals
                        </div>
                    </div>
                    <div class="pill-tabs aw-pill-tabs" style="display:flex;">
                        <button type="button" class="pill-tab active" onclick="aqibSwitchAWTab(this, 'month')">This Month</button>
                        <button type="button" class="pill-tab" onclick="aqibSwitchAWTab(this, 'year')">This Year</button>
                        <button type="button" class="pill-tab" onclick="aqibSwitchAWTab(this, 'lastyear')">Last Year</button>
                    </div>
                    <div class="aw-body">
                        <div class="aw-box">
                            <div class="left">
                                <div class="aw-ico" style="background:#FFE6D6; color:#FF7C1B;"><i class="fa fa-chart-bar"></i></div>
                                <div>
                                    <div class="name">Admissions</div>
                                    <span class="kpi-badge flat" id="awAdmissionsSub" style="margin-top:2px;">This month</span>
                                </div>
                            </div>
                            <div class="val" id="awAdmissionsVal" style="color:#FF7C1B;"><?php echo $admissionsMonth; ?></div>
                        </div>
                        <div class="aw-box" style="margin-bottom:0;">
                            <div class="left">
                                <div class="aw-ico" style="background:#E7F7EF; color:#16A34A;"><i class="fa fa-chart-bar"></i></div>
                                <div>
                                    <div class="name">Withdrawals</div>
                                    <span class="kpi-badge flat" id="awWithdrawalsSub" style="margin-top:2px;">This month</span>
                                </div>
                            </div>
                            <div class="val" id="awWithdrawalsVal" style="color:#16A34A;"><?php echo $withdrawalsMonth; ?></div>
                        </div>
                        <div class="aw-net">
                            <div>
                                <div class="n-lbl">Net Growth</div>
                                <div class="n-val" id="awNetVal" style="color: #4ADE80;">+<?php echo max(0, $admissionsMonth - $withdrawalsMonth); ?> Students</div>
                            </div>
                            <i class="fa fa-chart-line" id="awNetIcon" style="color:#4ADE80; font-size:20px;"></i>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>manage_students.php" target="_blank" id="awViewDetails" class="view-details">View Details →</a>
                </div>
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