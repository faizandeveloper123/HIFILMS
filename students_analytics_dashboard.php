<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Student Analytics';

// Ensure lookup tables exist for queries below
try { db_query("CREATE TABLE IF NOT EXISTS class_heads (
    class_head_id INT AUTO_INCREMENT PRIMARY KEY,
    class_head_name VARCHAR(150) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE classes ADD COLUMN IF NOT EXISTS class_head_id INT DEFAULT NULL"); } catch (Throwable $ex) {}

$totalActive    = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=1")->fetch_assoc()['c'] ?? 0);
$totalAll       = (int) (db_query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'] ?? 0);
$boys           = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=1 AND gender='male'")->fetch_assoc()['c'] ?? 0);
$girls          = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=1 AND gender='female'")->fetch_assoc()['c'] ?? 0);
$struckOff      = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=0")->fetch_assoc()['c'] ?? 0);
$reEnrolled     = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=2")->fetch_assoc()['c'] ?? 0);
$totalClasses   = (int) (db_query("SELECT COUNT(*) c FROM classes WHERE status=1")->fetch_assoc()['c'] ?? 0);
$localities     = (int) (db_query("SELECT COUNT(*) c FROM localities WHERE status=1")->fetch_assoc()['c'] ?? 0);
$recentAdm      = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=1 AND admission_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['c'] ?? 0);

$classWise = [];
$res = db_query("SELECT c.class_name, COUNT(s.student_id) c FROM students s JOIN classes c ON s.class_id=c.class_id WHERE s.status=1 GROUP BY c.class_name ORDER BY c.class_name");
while ($row = $res->fetch_assoc()) { $classWise[] = $row; }
$classLabels = array_column($classWise, 'class_name');
$classCounts = array_map('intval', array_column($classWise, 'c'));
$maxClass = max(1, max($classCounts));

$admSources = [];
$res = db_query("SELECT COALESCE(NULLIF(TRIM(admission_source),''),'Unknown') src, COUNT(*) c FROM students WHERE status=1 GROUP BY src ORDER BY c DESC");
while ($row = $res->fetch_assoc()) { $admSources[] = $row; }
$admSrcLabels = array_column($admSources, 'src');
$admSrcCounts = array_map('intval', array_column($admSources, 'c'));
$admSrcColors = ['#f39c12','#3498db','#27ae60','#9b59b6','#e74c3c','#1abc9c','#e67e22','#34495e'];

$recentList = [];
$res = db_query("SELECT s.first_name, s.last_name, s.admission_date, s.gr_no, c.class_name FROM students s LEFT JOIN classes c ON s.class_id=c.class_id WHERE s.status=1 ORDER BY s.admission_date DESC LIMIT 10");
while ($row = $res->fetch_assoc()) { $recentList[] = $row; }

$attPresent = (int) (db_query("SELECT COUNT(*) c FROM attendance WHERE status='present' AND date=CURDATE()")->fetch_assoc()['c'] ?? 0);
$attAbsent = (int) (db_query("SELECT COUNT(*) c FROM attendance WHERE status='absent' AND date=CURDATE()")->fetch_assoc()['c'] ?? 0);
$attLate = (int) (db_query("SELECT COUNT(*) c FROM attendance WHERE status='late' AND date=CURDATE()")->fetch_assoc()['c'] ?? 0);
$attTotal = $attPresent + $attAbsent + $attLate;

// Monthly admission trends (this year)
$monthlyAdm = [];
for ($m = 1; $m <= 12; $m++) {
    $cnt = (int) (db_query("SELECT COUNT(*) c FROM students WHERE YEAR(admission_date)=YEAR(CURDATE()) AND MONTH(admission_date)=$m")->fetch_assoc()['c'] ?? 0);
    $monthlyAdm[] = ['month' => date('M', mktime(0,0,0,$m,1)), 'count' => $cnt];
}
$maxMonthly = max(1, max(array_column($monthlyAdm, 'count')));

// Class Head distribution (matches real site donut)
$headDist = [];
$res = db_query("SELECT COALESCE(h.class_head_name,'Unassigned') head, COUNT(s.student_id) c
                 FROM students s
                 LEFT JOIN classes cl ON s.class_id = cl.class_id
                 LEFT JOIN class_heads h ON cl.class_head_id = h.class_head_id
                 WHERE s.status=1
                 GROUP BY head ORDER BY c DESC");
while ($row = $res->fetch_assoc()) { $headDist[] = $row; }
$headDist = array_slice($headDist, 0, 7);
$headTotal = array_sum(array_column($headDist, 'c'));

// Age distribution (from dob)
$ageBrackets = ['0-5 yrs' => 0, '6-10 yrs' => 0, '11-15 yrs' => 0, '16-20 yrs' => 0, '20+ yrs' => 0];
$ag = db_query("SELECT dob FROM students WHERE status=1 AND dob IS NOT NULL AND dob <> ''");
while ($row = $ag->fetch_assoc()) {
    $age = (int) date_diff(date_create($row['dob']), date_create('today'))->y;
    if ($age <= 5)      $ageBrackets['0-5 yrs']++;
    elseif ($age <= 10) $ageBrackets['6-10 yrs']++;
    elseif ($age <= 15) $ageBrackets['11-15 yrs']++;
    elseif ($age <= 20) $ageBrackets['16-20 yrs']++;
    else                $ageBrackets['20+ yrs']++;
}
$maxAge = max(1, max($ageBrackets));

// Religion distribution
$relDist = [];
$rl = db_query("SELECT COALESCE(NULLIF(TRIM(religion),''),'Not Specified') religion, COUNT(*) c
                FROM students WHERE status=1 GROUP BY religion ORDER BY c DESC");
while ($row = $rl->fetch_assoc()) { $relDist[] = $row; }
$maxRel = max(1, max(array_column($relDist, 'c')));

// Withdrawal trends (status=0 students by month of this year)
$withTrend = [];
for ($m = 1; $m <= 12; $m++) {
    $cnt = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=0 AND YEAR(admission_date)=YEAR(CURDATE()) AND MONTH(admission_date)=$m")->fetch_assoc()['c'] ?? 0);
    $withTrend[] = $cnt;
}
$maxWith = max(1, max($withTrend));

// Gender % (of active)
$genderPct = function ($n) use ($totalActive) { return $totalActive > 0 ? round(($n / $totalActive) * 100, 1) : 0; };

// Data issues count (students missing critical fields) - functional version
$dataIssues = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=1 AND (TRIM(COALESCE(father_name,''))='' OR TRIM(COALESCE(phone,''))='' OR TRIM(COALESCE(address,''))='' OR dob IS NULL OR section_id IS NULL)")->fetch_assoc()['c'] ?? 0);

include __DIR__ . '/includes/header.php';
?>
<style>
/* ===== Analytics Dashboard (matches real site) ===== */
.analytics-topbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:14px 4px 6px; }
.analytics-topbar h2 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.analytics-breadcrumb { font-size:12px; color:#6B7280; margin-top:5px; }
.analytics-breadcrumb a { color:#6B7280; text-decoration:none; }
.analytics-breadcrumb a:hover { color:#FF7A1B; }

.analytics-layout { display:grid; grid-template-columns:minmax(0,1fr) 280px; gap:16px; }
.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
.stat-card2 { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; transition:all .5s ease; }
.stat-card-top { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
.stat-icon-badge { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; color:#fff; }
.stat-label { font-size:12.5px; font-weight:700; color:#6B7280; }
.stat-value { font-size:30px; font-weight:800; color:#111827; line-height:1.1; }
.stat-info { font-size:11.5px; color:#9CA3AF; margin-top:4px; }
.stat-dot { display:inline-block; width:7px; height:7px; border-radius:50%; background:#10B981; margin-right:5px; }
.theme-green .stat-icon-badge { background:linear-gradient(135deg,#10B981,#059669); } .theme-green i{color:#059669;}
.theme-blue .stat-icon-badge { background:linear-gradient(135deg,#377DFF,#2563EB); } .theme-blue .stat-icon-badge i{color:#fff;}
.theme-pink .stat-icon-badge { background:linear-gradient(135deg,#EC4899,#DB2777); }
.theme-amber .stat-icon-badge { background:linear-gradient(135deg,#F59E0B,#D97706); }
.theme-red .stat-icon-badge { background:linear-gradient(135deg,#EF4444,#DC2626); }
.theme-teal .stat-icon-badge { background:linear-gradient(135deg,#14B8A6,#0D9488); }
.theme-indigo .stat-icon-badge { background:linear-gradient(135deg,#6366F1,#4F46E5); }
.theme-purple .stat-icon-badge { background:linear-gradient(135deg,#8B5CF6,#7C3AED); }

.chart-container { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:16px; }
.chart-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; }
.chart-title { display:flex; align-items:center; justify-content:space-between; font-size:14px; font-weight:800; color:#111827; margin-bottom:14px; }
.chart-title .badge-soft { background:#ECFDF5; color:#059669; font-size:11px; font-weight:700; padding:3px 10px; border-radius:999px; }

.bar-chart { display:flex; flex-direction:column; gap:10px; }
.bar-item { display:flex; align-items:center; gap:10px; }
.bar-label { font-size:12px; font-weight:600; color:#5A6C7D; min-width:34px; }
.bar-track { flex:1; height:22px; background:#F3F4F6; border-radius:6px; overflow:hidden; position:relative; }
.bar-fill { height:100%; border-radius:6px; display:flex; align-items:center; justify-content:flex-end; padding-right:8px; font-size:11px; font-weight:700; color:#fff; }
.bar-fill.green { background:linear-gradient(90deg,#10B981,#34D399); }
.bar-fill.purple { background:linear-gradient(90deg,#8B5CF6,#A78BFA); }
.bar-fill.orange { background:linear-gradient(90deg,#F59E0B,#FBBF24); }

.donut-wrap { position:relative; width:150px; height:150px; margin:0 auto; }
.donut-center { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; }
.donut-center .num { font-size:22px; font-weight:800; color:#1F2937; }
.donut-center .lbl { font-size:10px; color:#6B7280; font-weight:600; }
.legend-list { margin-top:14px; display:flex; flex-direction:column; gap:8px; }
.legend-item { display:flex; align-items:center; gap:8px; font-size:12.5px; color:#374151; }
.legend-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.legend-name { flex:1; font-weight:600; }
.legend-count { font-weight:800; }
.legend-pct { color:#6B7280; width:44px; text-align:right; }

.section-container { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-top:16px; }
.section-title { display:flex; align-items:center; gap:8px; font-size:14px; font-weight:800; color:#111827; margin-bottom:14px; }
.section-title i { color:#FF7A1B; }

.gender-pie { display:flex; height:34px; border-radius:8px; overflow:hidden; }
.gender-pie .boys-segment { background:linear-gradient(90deg,#377DFF,#60A5FA); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; }
.gender-pie .girls-segment { background:linear-gradient(90deg,#EC4899,#F9A8D4); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; }
.gender-pie .unassigned-segment { background:#E5E7EB; color:#9CA3AF; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; }

.trend-grid { display:grid; grid-template-columns:repeat(12,1fr); gap:8px; }
.trend-bar { text-align:center; }
.trend-value { height:90px; display:flex; align-items:flex-end; justify-content:center; background:#F9FAFB; border-radius:6px; overflow:hidden; }
.trend-fill { width:100%; background:linear-gradient(180deg,#F87171,#DC2626); border-radius:6px 6px 0 0; }

.sidebar-panel .section-container { margin-top:0; }
.quick-links { display:flex; flex-direction:column; gap:8px; }
.quick-link2 { display:flex; align-items:center; gap:10px; text-decoration:none; color:#374151; background:#F9FAFB; border:1px solid #EEF1F4; border-radius:10px; padding:9px 11px; font-size:12.5px; font-weight:600; transition:all .2s; }
.quick-link2:hover { background:#FFF7ED; border-color:#FED7AA; color:#C2410C; }
.quick-link2 .ql-icon { width:28px; height:28px; border-radius:8px; background:#fff; display:flex; align-items:center; justify-content:center; color:#FF7A1B; font-size:13px; flex-shrink:0; }
.quick-link2.danger { border-color:#FECACA; color:#B91C1C; }
.quick-link2.danger .ql-icon { color:#DC2626; }
.quick-link2 .ql-chevron { margin-left:auto; font-size:11px; color:#9CA3AF; }

.topbar-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.btn-print { border:1px solid #E5E7EB; background:#fff; border-radius:9px; padding:8px 14px; font-size:13px; font-weight:600; color:#374151; cursor:pointer; }
.btn-print:hover { background:#FF7A1B; color:#fff; border-color:#FF7A1B; }

/* Data issues collapsed panel */
.data-issues-card { margin-top:16px; }
.data-issues-head { display:flex; flex-wrap:wrap; gap:12px; align-items:center; background:#FEF2F2; border:1px solid #FECACA; border-radius:14px 14px 0 0; padding:12px 16px; }
.data-issues-head .dih-icon { font-size:18px; color:#DC2626; }
.data-issues-head .dih-count { font-size:20px; font-weight:800; color:#B91C1C; }
.data-issues-inner { background:#fff; border:1px solid #FECACA; border-top:0; border-radius:0 0 14px 14px; padding:10px 16px; font-size:12.5px; color:#6B7280; }

@media (max-width:1100px){ .stats-grid{grid-template-columns:repeat(2,1fr);} }
@media (max-width:900px){ .analytics-layout{grid-template-columns:1fr;} .chart-container{grid-template-columns:1fr;} .trend-grid{grid-template-columns:repeat(6,1fr);} }
@media (max-width:520px){ .stats-grid{grid-template-columns:1fr;} }
@media print { .sidebar-panel,.analytics-breadcrumb,.topbar-actions{display:none;} .analytics-layout{grid-template-columns:1fr;} }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="analytics-topbar">
            <h2><i class="fa fa-chart-pie" style="color:#FF7A1B;"></i> Students Analytics Dashboard</h2>
            <div class="topbar-actions">
                <button class="btn-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
        </div>
        <div class="analytics-breadcrumb">
            <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; Student Analytics
        </div>

        <!-- Data Issues card -->
        <div class="data-issues-card" id="dataIssuesCard">
            <button style="width:100%; background:#FEF2F2; border:1px solid #FECACA; border-radius:14px; padding:12px 16px; display:flex; align-items:center; gap:12px; cursor:pointer;" onclick="toggleDataIssues()" id="dataIssuesToggleBtn">
                <span class="dih-icon"><i class="fa fa-exclamation-triangle"></i></span>
                <span style="font-weight:800; color:#B91C1C; font-size:14px;">Data Issues</span>
                <span style="background:#DC2626; color:#fff; border-radius:999px; padding:1px 10px; font-weight:800; font-size:12px;"><?php echo $dataIssues; ?></span>
                <span style="margin-left:auto; color:#B91C1C;"><i class="fa fa-chevron-down"></i> View Details</span>
            </button>
            <div id="dataIssuesContent" style="display:none;">
                <div class="data-issues-inner">
                    Students missing required fields (father name / contact / address / DOB / section): <strong><?php echo $dataIssues; ?></strong> record(s).
                    <a href="<?php echo BASE_URL; ?>manage_students.php" style="color:#C2410C; font-weight:700;">Review students &rarr;</a>
                </div>
            </div>
        </div>

        <div class="analytics-layout">
            <div style="min-width:0;">
                <!-- Main Statistics Cards -->
                <div class="stats-grid" style="margin-top:16px;">
                    <div class="stat-card2 theme-green">
                        <div class="stat-card-top"><div class="stat-icon-badge"><i class="fa fa-users"></i></div><div class="stat-label">Total Students</div></div>
                        <div class="stat-value"><?php echo $totalActive; ?></div>
                        <div class="stat-info"><span class="stat-dot"></span> Active enrollment</div>
                    </div>
                    <div class="stat-card2 theme-blue">
                        <div class="stat-card-top"><div class="stat-icon-badge"><i class="fa fa-male"></i></div><div class="stat-label">Boys</div></div>
                        <div class="stat-value"><?php echo $boys; ?></div>
                        <div class="stat-info"><i class="fa fa-arrow-up"></i> <?php echo $genderPct($boys); ?>% of total</div>
                    </div>
                    <div class="stat-card2 theme-pink">
                        <div class="stat-card-top"><div class="stat-icon-badge"><i class="fa fa-female"></i></div><div class="stat-label">Girls</div></div>
                        <div class="stat-value"><?php echo $girls; ?></div>
                        <div class="stat-info"><i class="fa fa-arrow-up"></i> <?php echo $genderPct($girls); ?>% of total</div>
                    </div>
                    <div class="stat-card2 theme-amber">
                        <div class="stat-card-top"><div class="stat-icon-badge"><i class="fa fa-book"></i></div><div class="stat-label">Total Courses/Classes</div></div>
                        <div class="stat-value"><?php echo $totalClasses; ?></div>
                        <div class="stat-info"><span class="stat-dot"></span> Active sections</div>
                    </div>
                    <div class="stat-card2 theme-red">
                        <div class="stat-card-top"><div class="stat-icon-badge"><i class="fa fa-user-times"></i></div><div class="stat-label">Struck-Off</div></div>
                        <div class="stat-value"><?php echo $struckOff; ?></div>
                        <div class="stat-info"><span class="stat-dot"></span> Inactive students</div>
                    </div>
                    <div class="stat-card2 theme-teal">
                        <div class="stat-card-top"><div class="stat-icon-badge"><i class="fa fa-user-plus"></i></div><div class="stat-label">Re-Enrolled</div></div>
                        <div class="stat-value"><?php echo $reEnrolled; ?></div>
                        <div class="stat-info"><span class="stat-dot"></span> Rejoined students</div>
                    </div>
                    <div class="stat-card2 theme-indigo">
                        <div class="stat-card-top"><div class="stat-icon-badge"><i class="fa fa-map-marker"></i></div><div class="stat-label">Localities</div></div>
                        <div class="stat-value"><?php echo $localities; ?></div>
                        <div class="stat-info"><span class="stat-dot"></span> Coverage areas</div>
                    </div>
                    <div class="stat-card2 theme-purple">
                        <div class="stat-card-top"><div class="stat-icon-badge"><i class="fa fa-building"></i></div><div class="stat-label">Recent Admissions</div></div>
                        <div class="stat-value"><?php echo $recentAdm; ?></div>
                        <div class="stat-info"><span class="stat-dot"></span> Last 7 days</div>
                    </div>
                </div>

                <?php
                $hdLabels = array_column($headDist, 'head');
                $hdCounts = array_map('intval', array_column($headDist, 'c'));
                $hdColors = ['#f39c12','#3498db','#27ae60','#9b59b6','#95a5a6','#e74c3c','#16a085'];
                $sharePct = $headTotal > 0 ? $headDist[0]['c'] / $headTotal * 100 : 0;
                $topHeadName = $headDist[0]['head'] ?? 'Unassigned';
                ?>
                <!-- Charts Section -->
                <div class="chart-container">
                    <div class="chart-card">
                        <div class="chart-title">
                            <span><i class="fa fa-chart-bar"></i> Monthly Admissions (<?php echo date('Y'); ?>)</span>
                            <span class="badge-soft">This Year</span>
                        </div>
                        <div class="bar-chart">
                            <?php foreach ($monthlyAdm as $ma): ?>
                                <div class="bar-item">
                                    <div class="bar-label"><?php echo $ma['month']; ?></div>
                                    <div class="bar-track">
                                        <div class="bar-fill green" style="width: <?php echo round(($ma['count'] / $maxMonthly) * 100, 4); ?>%;"><?php echo $ma['count']; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-title"><span><i class="fa fa-sitemap"></i> Class Head Distribution</span></div>
                        <div class="donut-wrap">
                            <canvas id="classHeadDonut" width="140" height="140" style="display:block; height:140px; width:140px;"></canvas>
                            <div class="donut-center">
                                <div class="num"><?php echo $headTotal; ?></div>
                                <div class="lbl">Total Class Heads</div>
                            </div>
                        </div>
                        <div class="legend-list">
                            <?php foreach ($headDist as $i => $hd): ?>
                                <div class="legend-item">
                                    <span class="legend-dot" style="background: <?php echo $hdColors[$i % count($hdColors)]; ?>;"></span>
                                    <span class="legend-name"><?php echo e($hd['head']); ?></span>
                                    <span class="legend-count"><?php echo $hd['c']; ?></span>
                                    <span class="legend-pct"><?php echo $headTotal > 0 ? round(($hd['c'] / $headTotal) * 100, 1) : 0; ?>%</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-title"><span><i class="fa fa-chart-pie"></i> Class Head Share</span></div>
                        <div class="donut-wrap">
                            <canvas id="classHeadShareDonut" width="140" height="140" style="display:block; height:140px; width:140px;"></canvas>
                            <div class="donut-center">
                                <div class="num"><?php echo round($sharePct, 1); ?>%</div>
                                <div class="lbl"><?php echo e($topHeadName); ?></div>
                            </div>
                        </div>
                        <div class="legend-list">
                            <?php foreach ($headDist as $i => $hd): ?>
                                <div class="legend-item">
                                    <span class="legend-dot" style="background: <?php echo $hdColors[$i % count($hdColors)]; ?>;"></span>
                                    <span class="legend-name"><?php echo e($hd['head']); ?></span>
                                    <span class="legend-pct" style="width:auto;"><?php echo $headTotal > 0 ? round(($hd['c'] / $headTotal) * 100, 1) : 0; ?>%</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Gender Distribution -->
                    <div class="section-container" style="display:flex; flex-direction:column; justify-content:center;">
                        <div class="section-title"><i class="fa fa-venus-mars"></i><span>Gender Distribution</span></div>
                        <div class="gender-pie">
                            <div class="boys-segment" style="width: <?php echo $genderPct($boys); ?>%;"><?php echo $genderPct($boys); ?>%</div>
                            <div class="girls-segment" style="width: <?php echo $genderPct($girls); ?>%;"><?php echo $genderPct($girls); ?>%</div>
                            <div class="unassigned-segment" style="width: <?php echo max(0, 100 - $genderPct($boys) - $genderPct($girls)); ?>%;"></div>
                        </div>
                        <div style="display:flex; justify-content:space-around; margin-top:10px; font-size:13px;">
                            <div><strong>Boys:</strong> <?php echo $boys; ?> (<?php echo $genderPct($boys); ?>%)</div>
                            <div><strong>Girls:</strong> <?php echo $girls; ?> (<?php echo $genderPct($girls); ?>%)</div>
                        </div>
                    </div>
                </div>

                <div class="chart-container" style="grid-template-columns: repeat(2,1fr);">
                    <!-- Age Distribution -->
                    <div class="chart-card">
                        <div class="chart-title"><span><i class="fa fa-birthday-cake"></i> Age Distribution</span></div>
                        <div class="bar-chart">
                            <?php foreach ($ageBrackets as $label => $val): ?>
                                <div class="bar-item">
                                    <div class="bar-label" style="min-width:60px;"><?php echo $label; ?></div>
                                    <div class="bar-track">
                                        <div class="bar-fill purple" style="width: <?php echo round(($val / $maxAge) * 100, 4); ?>%;"><?php echo $val; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Religion Distribution -->
                    <div class="chart-card">
                        <div class="chart-title"><span><i class="fa fa-users"></i> Religion Distribution</span></div>
                        <div class="bar-chart">
                            <?php foreach ($relDist as $rd): ?>
                                <div class="bar-item">
                                    <div class="bar-label" style="min-width:90px;"><?php echo e($rd['religion']); ?></div>
                                    <div class="bar-track">
                                        <div class="bar-fill orange" style="width: <?php echo round(($rd['c'] / $maxRel) * 100, 4); ?>%;"><?php echo $rd['c']; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Withdrawal Trends -->
                <div class="section-container">
                    <div class="section-title"><i class="fa fa-chart-line"></i><span>Withdrawal Trends (<?php echo date('Y'); ?>)</span></div>
                    <div class="trend-grid" style="display:none;" id="withGrid"></div>
                    <div id="withFallback" style="font-size:13px; color:#6B7280;">No withdrawal data yet.</div>
                </div>

                <!-- Students Per Class Bar Chart -->
                <div class="chart-card" style="margin-top:16px;">
                    <div class="chart-title">
                        <span><i class="fa fa-bar-chart"></i> Students Per Class</span>
                        <span class="badge-soft">Chart.js</span>
                    </div>
                    <canvas id="classBarChart" height="200"></canvas>
                </div>

                <!-- Admission Sources Pie Chart -->
                <div class="chart-card" style="margin-top:16px;">
                    <div class="chart-title">
                        <span><i class="fa fa-pie-chart"></i> Admission Sources</span>
                    </div>
                    <div style="max-width:400px; margin:0 auto;">
                        <canvas id="admSourcePie" height="250"></canvas>
                    </div>
                    <div class="legend-list" style="margin-top:14px;">
                        <?php foreach ($admSources as $i => $as): ?>
                            <div class="legend-item">
                                <span class="legend-dot" style="background:<?php echo $admSrcColors[$i % count($admSrcColors)]; ?>;"></span>
                                <span class="legend-name"><?php echo e($as['src']); ?></span>
                                <span class="legend-count"><?php echo $as['c']; ?></span>
                                <span class="legend-pct"><?php echo $totalActive > 0 ? round(($as['c'] / $totalActive) * 100, 1) : 0; ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Admissions List -->
                <div class="section-container" style="margin-top:16px;">
                    <div class="section-title"><i class="fa fa-user-plus"></i><span>Recent Admissions (Last 10)</span></div>
                    <?php if (count($recentList) === 0): ?>
                        <div style="text-align:center; color:#9CA3AF; padding:20px;">No recent admissions found.</div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="table table-striped table-bordered" style="width:100%; margin:0; font-size:12.5px;">
                                <thead><tr><th>#</th><th>Student Name</th><th>GR No</th><th>Class</th><th>Admission Date</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recentList as $i => $rl): ?>
                                        <tr>
                                            <td><?php echo $i + 1; ?></td>
                                            <td><strong><?php echo e(trim($rl['first_name'] . ' ' . $rl['last_name'])); ?></strong></td>
                                            <td><?php echo e($rl['gr_no'] ?? '-'); ?></td>
                                            <td><?php echo e($rl['class_name'] ?? '-'); ?></td>
                                            <td><?php echo $rl['admission_date'] ? date('d M Y', strtotime($rl['admission_date'])) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Attendance Summary -->
                <div class="section-container" style="margin-top:16px;">
                    <div class="section-title"><i class="fa fa-check-square-o"></i><span>Today's Attendance Summary</span></div>
                    <?php if ($attTotal > 0): ?>
                        <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px;">
                            <div style="text-align:center; background:#F0FFF4; border-radius:10px; padding:14px;">
                                <div style="font-size:24px; font-weight:800; color:#16A34A;"><?php echo $attPresent; ?></div>
                                <div style="font-size:12px; color:#16A34A; font-weight:600;">Present</div>
                                <div style="font-size:11px; color:#6B7280;"><?php echo $attTotal > 0 ? round(($attPresent / $attTotal) * 100, 1) : 0; ?>%</div>
                            </div>
                            <div style="text-align:center; background:#FEF2F2; border-radius:10px; padding:14px;">
                                <div style="font-size:24px; font-weight:800; color:#DC2626;"><?php echo $attAbsent; ?></div>
                                <div style="font-size:12px; color:#DC2626; font-weight:600;">Absent</div>
                                <div style="font-size:11px; color:#6B7280;"><?php echo $attTotal > 0 ? round(($attAbsent / $attTotal) * 100, 1) : 0; ?>%</div>
                            </div>
                            <div style="text-align:center; background:#FEF3C7; border-radius:10px; padding:14px;">
                                <div style="font-size:24px; font-weight:800; color:#D97706;"><?php echo $attLate; ?></div>
                                <div style="font-size:12px; color:#D97706; font-weight:600;">Late</div>
                                <div style="font-size:11px; color:#6B7280;"><?php echo $attTotal > 0 ? round(($attLate / $attTotal) * 100, 1) : 0; ?>%</div>
                            </div>
                            <div style="text-align:center; background:#EFF6FF; border-radius:10px; padding:14px;">
                                <div style="font-size:24px; font-weight:800; color:#2563EB;"><?php echo $attTotal; ?></div>
                                <div style="font-size:12px; color:#2563EB; font-weight:600;">Total Marked</div>
                                <div style="font-size:11px; color:#6B7280;">Today</div>
                            </div>
                        </div>
                        <div style="margin-top:12px; height:10px; background:#F3F4F6; border-radius:6px; overflow:hidden; display:flex;">
                            <?php if ($attTotal > 0): ?>
                                <div style="width:<?php echo round(($attPresent / $attTotal) * 100); ?>%; background:#16A34A;"></div>
                                <div style="width:<?php echo round(($attLate / $attTotal) * 100); ?>%; background:#D97706;"></div>
                                <div style="width:<?php echo round(($attAbsent / $attTotal) * 100); ?>%; background:#DC2626;"></div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; color:#9CA3AF; padding:16px;">No attendance recorded today.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Access Reports -->
            <div class="sidebar-panel">
                <div class="section-container">
                    <div class="section-title"><i class="fa fa-bolt"></i><span>Quick Access Reports</span></div>
                    <div class="quick-links sidebar">
                        <a href="#dataIssuesCard" class="quick-link2 danger" onclick="toggleDataIssues(); return false;">
                            <span class="ql-icon"><i class="fa fa-exclamation-triangle"></i></span>
                            <span>Data Issues (<?php echo $dataIssues; ?>)</span>
                            <i class="fa fa-chevron-right ql-chevron"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>manage_students.php" class="quick-link2">
                            <span class="ql-icon"><i class="fa fa-list"></i></span>
                            <span>Course/Class Wise Reports</span>
                            <i class="fa fa-chevron-right ql-chevron"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>manage_students.php?status=1" class="quick-link2">
                            <span class="ql-icon"><i class="fa fa-user-check"></i></span>
                            <span>Active Students</span>
                            <i class="fa fa-chevron-right ql-chevron"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>manage_students.php?status=0" class="quick-link2">
                            <span class="ql-icon"><i class="fa fa-user-times"></i></span>
                            <span>Struck-Off Students</span>
                            <i class="fa fa-chevron-right ql-chevron"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>manage_students.php?status=2" class="quick-link2">
                            <span class="ql-icon"><i class="fa fa-user-plus"></i></span>
                            <span>Re-Enrollment</span>
                            <i class="fa fa-chevron-right ql-chevron"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>adm_form.php" class="quick-link2">
                            <span class="ql-icon"><i class="fa fa-file"></i></span>
                            <span>Blank Admission Form</span>
                            <i class="fa fa-chevron-right ql-chevron"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>add_student.php" class="quick-link2">
                            <span class="ql-icon"><i class="fa fa-user-plus"></i></span>
                            <span>Add New Student</span>
                            <i class="fa fa-chevron-right ql-chevron"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>class_promotion.php" class="quick-link2">
                            <span class="ql-icon"><i class="fa fa-arrow-up"></i></span>
                            <span>Course/Class Promotion</span>
                            <i class="fa fa-chevron-right ql-chevron"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>student_inquiry.php" class="quick-link2">
                            <span class="ql-icon"><i class="fa fa-search"></i></span>
                            <span>Admission Inquiries</span>
                            <i class="fa fa-chevron-right ql-chevron"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
function toggleDataIssues() {
    var content = document.getElementById('dataIssuesContent');
    var btn = document.getElementById('dataIssuesToggleBtn');
    if (!content || !btn) return;
    var isHidden = content.style.display === 'none' || content.style.display === '';
    content.style.display = isHidden ? 'block' : 'none';
    btn.innerHTML = btn.innerHTML.replace(/<span[^>]*>View Details[\s\S]*/i, '');
    if (isHidden) { btn.insertAdjacentHTML('beforeend', '<span style="margin-left:auto; color:#B91C1C;"><i class="fa fa-chevron-up"></i> Hide Details</span>'); }
    else { btn.insertAdjacentHTML('beforeend', '<span style="margin-left:auto; color:#B91C1C;"><i class="fa fa-chevron-down"></i> View Details</span>'); }
}

(function () {
    var data = <?php echo json_encode($withTrend); ?>;
    var months = <?php echo json_encode(array_column($monthlyAdm, 'month')); ?>;
    var maxV = <?php echo $maxWith; ?>;
    var any = data.some(function (v) { return v > 0; });
    if (!any) return;
    var grid = document.getElementById('withGrid');
    var fb = document.getElementById('withFallback');
    if (grid && fb) {
        grid.style.display = 'grid';
        fb.style.display = 'none';
        grid.innerHTML = '';
        data.forEach(function (v, i) {
            var div = document.createElement('div');
            div.className = 'trend-bar';
            div.innerHTML =
                '<div class="trend-value"><div class="trend-fill" style="height:' + (v / maxV * 100) + '%;"></div></div>' +
                '<div style="font-weight:600; color:#5A6c7d;">' + months[i] + '</div>' +
                '<div style="color:#e74c3c; font-weight:700;">' + v + '</div>';
            grid.appendChild(div);
        });
    }
})();

document.addEventListener('DOMContentLoaded', function () {
    var cards = document.querySelectorAll('.stat-card2');
    cards.forEach(function (card, index) {
        setTimeout(function () {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 50);
    });

    var classLabels = <?php echo json_encode($classLabels); ?>;
    var classCounts = <?php echo json_encode($classCounts); ?>;
    var classBar = document.getElementById('classBarChart');
    if (classBar && classLabels.length > 0) {
        new Chart(classBar, {
            type: 'bar',
            data: {
                labels: classLabels,
                datasets: [{
                    label: 'Students',
                    data: classCounts,
                    backgroundColor: 'rgba(99, 102, 241, 0.7)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: { ticks: { maxRotation: 45, font: { size: 11 } } }
                }
            }
        });
    }

    var srcLabels = <?php echo json_encode($admSrcLabels); ?>;
    var srcCounts = <?php echo json_encode($admSrcCounts); ?>;
    var srcColors = <?php echo json_encode($admSrcColors); ?>;
    var srcPie = document.getElementById('admSourcePie');
    if (srcPie && srcLabels.length > 0) {
        new Chart(srcPie, {
            type: 'pie',
            data: {
                labels: srcLabels,
                datasets: [{
                    data: srcCounts,
                    backgroundColor: srcColors.slice(0, srcLabels.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 } } }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>