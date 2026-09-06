<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Parents Login IDs';

db_query("CREATE TABLE IF NOT EXISTS parent_access (
    access_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    username VARCHAR(191),
    password VARCHAR(255),
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$classes = [];
$res = db_query("SELECT class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sel_class = (int) ($_GET['class_id'] ?? 0);
$sel_section = (int) ($_GET['section'] ?? 0);
$sel_status = trim($_GET['status_filter'] ?? '');
$sel_search = trim($_GET['search_term'] ?? '');

$sections = [];
if ($sel_class > 0) {
    $st = db_prepare("SELECT section_id, section_name FROM sections WHERE class_id=? ORDER BY section_name");
    $st->bind_param('i', $sel_class);
    $st->execute();
    $r = $st->get_result();
    while ($row = $r->fetch_assoc()) { $sections[] = $row; }
}

$where = ["s.status = 1"];
$params = [];
$types = '';
if ($sel_class > 0) { $where[] = "s.class_id = ?"; $params[] = $sel_class; $types .= 'i'; }
if ($sel_section > 0) { $where[] = "s.section_id = ?"; $params[] = $sel_section; $types .= 'i'; }
if ($sel_status === 'active') {
    $where[] = "EXISTS (SELECT 1 FROM parent_access pa WHERE pa.student_id = s.student_id AND pa.status = 1)";
} elseif ($sel_status === 'inactive') {
    $where[] = "NOT EXISTS (SELECT 1 FROM parent_access pa WHERE pa.student_id = s.student_id AND pa.status = 1)";
}
if ($sel_search !== '') {
    $where[] = "(s.first_name LIKE ? OR s.father_name LIKE ? OR s.gr_no LIKE ? OR s.father_cellno LIKE ? OR s.phone LIKE ?)";
    $like = '%' . $sel_search . '%';
    for ($i = 0; $i < 5; $i++) { $params[] = $like; $types .= 's'; }
}

$sql = "SELECT s.student_id, s.first_name, s.last_name, s.father_name, s.phone, s.father_cellno, s.whatsapp_number, s.email,
        s.gr_no, s.address, s.class_id, s.section_id, s.photo,
        c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.class_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        WHERE " . implode(' AND ', $where) . " ORDER BY s.first_name, s.last_name";

$students = [];
if (count($params) > 0) {
    $st = db_prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $r = $st->get_result();
} else {
    $r = db_query($sql);
}
while ($row = $r->fetch_assoc()) { $students[] = $row; }

$totalStudents = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status = 1")->fetch_assoc()['c'] ?? 0);
$activeLogins = (int) (db_query("SELECT COUNT(*) c FROM parent_access WHERE status = 1")->fetch_assoc()['c'] ?? 0);
$inactiveLogins = (int) (db_query("SELECT COUNT(*) c FROM parent_access WHERE status = 0")->fetch_assoc()['c'] ?? 0);
$monthLogins = (int) (db_query("SELECT COUNT(*) c FROM parent_access WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetch_assoc()['c'] ?? 0);

$schoolName = get_setting('school_name', 'HIIFI LMS');

function hifi_barcode($text) {
    $map = [
        '0' => '101001101101', '1' => '110100101011', '2' => '101100101011', '3' => '110110010101',
        '4' => '101001101011', '5' => '110100110101', '6' => '101100110101', '7' => '101001011011',
        '8' => '110100101101', '9' => '101100101101', 'A' => '110101001011', 'B' => '101101001011',
        'C' => '110110100101', 'D' => '101011001011', 'E' => '110101100101', 'F' => '101101100101',
        'G' => '101010011011', 'H' => '110101001101', 'I' => '101101001101', 'J' => '101011001101',
        'K' => '110101010011', 'L' => '101101010011', 'M' => '110110101001', 'N' => '101011010011',
        'O' => '110101101001', 'P' => '101101101001', 'Q' => '101010110011', 'R' => '110101011001',
        'S' => '101101011001', 'T' => '101011011001', 'U' => '110010101011', 'V' => '100110101011',
        'W' => '110011010101', 'X' => '100101101011', 'Y' => '110010110101', 'Z' => '100110110101',
        '-' => '100101011011', '.' => '110010101101', ' ' => '100110101101', '*' => '100101101101',
    ];
    $code = '*';
    $upper = strtoupper($text);
    for ($i = 0; $i < strlen($upper); $i++) {
        if (isset($map[$upper[$i]])) { $code .= $upper[$i]; }
    }
    $code .= '*';
    $html = '<div class="pid-barcode">';
    for ($i = 1; $i <= strlen($code); $i++) {
        $pat = $map[$code[$i - 1]];
        $j = 0;
        while ($j < 12) {
            $bit = $pat[$j];
            $k = $j;
            while ($k < 12 && $pat[$k] === $bit) { $k++; }
            $w = ($k - $j) * 2;
            if ($bit === '1') {
                $html .= '<span class="bc-bar" style="width:' . $w . 'px;"></span>';
            } else {
                $html .= '<span class="bc-space" style="width:' . $w . 'px;"></span>';
            }
            $j = $k;
        }
        if ($i < strlen($code)) { $html .= '<span class="bc-space" style="width:2px;"></span>'; }
    }
    $html .= '</div>';
    return $html;
}

include __DIR__ . '/includes/header.php';
?>
<style>
:root { --pid-card: #ffffff; --pid-border: #edf0f5; --pid-dark: #1f2937; --pid-muted: #8a94a6; }
.pid-shadow { background: var(--pid-card); border: 1px solid var(--pid-border); border-radius: 12px; box-shadow: 0 1px 2px rgba(16,24,40,0.04); }
.pid-topbar { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; padding: 16px 18px; margin: 12px 0; }
.pid-topbar h2 { font-size: 19px; font-weight: 700; color: var(--pid-dark); margin: 0 0 3px 0; }
.pid-topbar p { font-size: 12px; color: var(--pid-muted); margin: 0; }
.pid-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.pid-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; border-radius: 9px; font-size: 12px; font-weight: 600; text-decoration: none; white-space: nowrap; border: 1px solid transparent; }
.pid-btn:hover { text-decoration: none; opacity: 0.92; }
.pid-btn-outline { background: #fff; border-color: #c7d2fe; color: #4f46e5; }
.pid-btn-blue { background: #3b82f6; color: #fff !important; }
.pid-btn-green { background: #10b981; color: #fff !important; }
.pid-btn-teal { background: #06b6d4; color: #fff !important; }
.pid-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 12px; }
.pid-stat { padding: 14px 16px; display: flex; align-items: center; gap: 12px; }
.pid-stat-icon { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
.pid-stat.total .pid-stat-icon { background: #eef2ff; color: #6366f1; }
.pid-stat.active .pid-stat-icon { background: #ecfdf5; color: #10b981; }
.pid-stat.inactive .pid-stat-icon { background: #fff1f2; color: #f43f5e; }
.pid-stat.month .pid-stat-icon { background: #fdf2f8; color: #ec4899; }
.pid-stat-value { font-size: 20px; font-weight: 800; color: var(--pid-dark); line-height: 1.2; }
.pid-stat-label { font-size: 11px; color: var(--pid-muted); font-weight: 600; }
.pid-filterbar { padding: 12px 16px; margin-bottom: 12px; display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
.pid-filterbar .form-group { margin-bottom: 0; }
.pid-filterbar label { font-size: 11px; font-weight: 700; color: var(--pid-muted); text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 4px; display: block; }
.pid-filterbar select, .pid-filterbar input[type="text"] { border-radius: 8px; border: 1px solid var(--pid-border); font-size: 12.5px; height: 36px; padding: 6px 10px; }
.pid-filterbar .search-wrap { position: relative; flex: 1 1 220px; }
.pid-filterbar .search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--pid-muted); font-size: 12px; }
.pid-filterbar .search-wrap input { width: 100%; padding-left: 28px; }
.pid-card { background: #fff; border: 1px solid var(--pid-border); border-radius: 12px; overflow: hidden; break-inside: avoid; box-shadow: 0 2px 8px rgba(16,24,40,0.06); }
.pid-card .pc-band { background: linear-gradient(90deg, #4f46e5, #6366f1); color: #fff; padding: 10px 14px; font-weight: 800; display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
.pid-card .pc-body { padding: 14px; }
.pc-avatar { width: 54px; height: 54px; border-radius: 999px; background: #eef2ff; border: 2px solid #6366f1; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800; flex-shrink: 0; }
.pc-avatar-img { width: 54px; height: 54px; border-radius: 999px; object-fit: cover; border: 2px solid #6366f1; flex-shrink: 0; }
.pid-card table { width: 100%; font-size: 12px; }
.pid-card td { padding: 3px 0; }
.pid-card .lbl { color: var(--pid-muted); width: 76px; }
.pid-card .pc-id { color: #4f46e5; font-weight: 800; letter-spacing: 0.5px; }
.pid-barcode { display: flex; align-items: flex-end; margin: 8px 0 2px; }
.pid-barcode .bc-bar, .pid-barcode .bc-space { height: 30px; display: inline-block; }
.card-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.barcode-id { font-weight: 700; font-size: 13px; color: #1f2937; letter-spacing: 2px; text-align: center; }
.no-print { }
.pid-print-head, .pid-print-foot { display: none; }
@media (max-width: 1100px) { .pid-stats { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 900px) { .card-grid { grid-template-columns: 1fr; } }
@media (max-width: 576px) { .pid-stats { grid-template-columns: 1fr; } }
@media print {
    .left_col, .top_nav, .pid-topbar, .pid-stats, .pid-filterbar { display: none !important; }
    .right_col { margin-left: 0 !important; width: 100% !important; }
    .card-grid { grid-template-columns: repeat(3, 1fr); }
    .pid-print-head, .pid-print-foot { display: block !important; }
}
</style>

<div class="main-content pid-indent">
    <div class="container-fluid">

        <div style="font-size:12px;color:#8a94a6;margin-top:10px;">
            <a href="<?php echo BASE_URL; ?>dashboard.php" style="color:#8a94a6;">Dashboard</a> &nbsp;<i class="fa fa-angle-right"></i>&nbsp;
            <a href="<?php echo BASE_URL; ?>parents_portal_dashboard.php" style="color:#8a94a6;">Parents Portal</a> &nbsp;<i class="fa fa-angle-right"></i>&nbsp;
            <strong style="color:#1f2937;">Parents Login IDs</strong>
        </div>

        <div class="pid-topbar pid-shadow no-print">
            <div>
                <h2><i class="fa fa-users" style="color:#6366f1;"></i> Parents Login IDs</h2>
                <p>Manage all parent login accounts, reset passwords and send login credentials.</p>
            </div>
            <div class="pid-actions">
                <a href="<?php echo BASE_URL; ?>create_classwise_parents_ids.php" target="_blank" class="pid-btn pid-btn-outline"><i class="fa fa-graduation-cap"></i> Create Classwise Login IDs</a>
                <a href="<?php echo BASE_URL; ?>parents_access.php" class="pid-btn pid-btn-blue"><i class="fa fa-user-plus"></i> Create Login IDs</a>
                <a href="<?php echo BASE_URL; ?>parents_access.php" class="pid-btn pid-btn-green"><i class="fa fa-print"></i> Print Parent Credentials</a>
                <a href="<?php echo BASE_URL; ?>parents_id.php" class="pid-btn pid-btn-teal"><i class="fa fa-id-card"></i> Print Login IDs</a>
            </div>
        </div>

        <div class="pid-stats no-print">
            <div class="pid-stat total pid-shadow">
                <div class="pid-stat-icon"><i class="fa fa-users"></i></div>
                <div>
                    <div class="pid-stat-value"><?php echo $totalStudents; ?></div>
                    <div class="pid-stat-label">Total Parents</div>
                </div>
            </div>
            <div class="pid-stat active pid-shadow">
                <div class="pid-stat-icon"><i class="fa fa-check-circle"></i></div>
                <div>
                    <div class="pid-stat-value"><?php echo $activeLogins; ?></div>
                    <div class="pid-stat-label">Active Logins</div>
                </div>
            </div>
            <div class="pid-stat inactive pid-shadow">
                <div class="pid-stat-icon"><i class="fa fa-lock"></i></div>
                <div>
                    <div class="pid-stat-value"><?php echo $inactiveLogins; ?></div>
                    <div class="pid-stat-label">Inactive Logins</div>
                </div>
            </div>
            <div class="pid-stat month pid-shadow">
                <div class="pid-stat-icon"><i class="fa fa-key"></i></div>
                <div>
                    <div class="pid-stat-value"><?php echo $monthLogins; ?></div>
                    <div class="pid-stat-label">Logins Created (This Month)</div>
                </div>
            </div>
        </div>

        <form method="GET" action="<?php echo BASE_URL; ?>parents_id.php" class="pid-filterbar pid-shadow no-print">
            <div class="form-group">
                <label for="class_id">Filter by Class</label>
                <select name="class_id" id="class_id" class="form-control">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>" <?php echo $sel_class == $c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="txt_section">Filter by Section</label>
                <select name="section" id="txt_section" class="form-control">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo $s['section_id']; ?>" <?php echo $sel_section == $s['section_id'] ? 'selected' : ''; ?>><?php echo e($s['section_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="status_filter">Status</label>
                <select name="status_filter" id="status_filter" class="form-control">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $sel_status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $sel_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="form-group search-wrap">
                <label for="search_term">Search</label>
                <i class="fa fa-search"></i>
                <input type="text" name="search_term" id="search_term" class="form-control" placeholder="Search by Parent Name, Cell No, ID..." value="<?php echo e($sel_search); ?>">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" class="pid-btn pid-btn-blue" style="height:36px;"><i class="fa fa-search"></i> Search</button>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <a href="<?php echo BASE_URL; ?>parents_id.php" class="pid-btn pid-btn-outline" style="height:36px;"><i class="fa fa-undo"></i> Reset</a>
            </div>
        </form>

        <div class="pid-print-head">
            <table width="100%" style="font-size:12px; color:#1f2937;"><tr>
                <td><strong><?php echo e($schoolName); ?></strong><br><?php echo e(get_setting('school_address', '')); ?></td>
                <td align="right"><strong>Parents Login IDs</strong></td>
            </tr></table>
        </div>

        <div class="no-print" style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
            <button onclick="window.print()" class="btn btn-success btn-xs" style="border-radius:9px;"><i class="fa fa-print"></i> Print ID Cards</button>
            <span style="color:#8a94a6; font-size:12.5px;"><?php echo count($students); ?> record(s) shown</span>
        </div>

        <div class="card-grid">
            <?php if (count($students) === 0): ?>
                <div style="text-align:center; color:#6B7280; padding:50px; grid-column:1/-1;">No parent records found.</div>
            <?php endif; ?>
            <?php foreach ($students as $st): $pc = $st['father_cellno'] ?: $st['phone']; ?>
                <div class="pid-card">
                    <div class="pc-band">
                        <span><?php echo e($schoolName); ?></span>
                        <span style="font-size:10px; font-weight:600;">PARENT ID</span>
                    </div>
                    <div class="pc-body">
                        <div style="display:flex; gap:12px; align-items:center; margin-bottom:10px;">
                            <?php if (!empty($st['photo'])): ?>
                                <img class="pc-avatar-img" src="<?php echo BASE_URL; ?>uploads/students/<?php echo e($st['photo']); ?>" alt="">
                            <?php else: ?>
                                <div class="pc-avatar"><?php echo strtoupper(substr($st['first_name'], 0, 1)); ?></div>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:800; font-size:14px; color:#1f2937;"><?php echo e(trim($st['first_name'] . ' ' . $st['last_name'])); ?></div>
                                <div style="font-size:11.5px; color:#8a94a6;"><?php echo e($st['class_name'] ?? '-'); ?><?php echo !empty($st['section_name']) ? ' / ' . e($st['section_name']) : ''; ?></div>
                            </div>
                        </div>
                        <div class="pc-id" style="font-size:15px; text-align:center; padding:5px 0; background:#eef2ff; border-radius:8px; margin-bottom:8px;">
                            HIFI-<?php echo $st['student_id']; ?>
                        </div>
                        <table>
                            <tr><td class="lbl">GR No</td><td><strong><?php echo e($st['gr_no'] ?? $st['student_id']); ?></strong></td></tr>
                            <tr><td class="lbl">Father Name</td><td><?php echo e($st['father_name'] ?? '-'); ?></td></tr>
                            <tr><td class="lbl">Phone</td><td><?php echo e($pc ?: '-'); ?></td></tr>
                            <tr><td class="lbl">Address</td><td><?php echo e($st['address'] ?? '-'); ?></td></tr>
                        </table>
                        <?php echo hifi_barcode('HIFI-' . $st['student_id']); ?>
                        <div class="barcode-id">HIFI-<?php echo $st['student_id']; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pid-print-foot" style="margin-top:18px; padding-top:8px; border-top:1px solid #edf0f5; font-size:12px; color:#8a94a6;">
            Printed By: <?php echo e($_SESSION['user_name'] ?? 'Admin'); ?> &nbsp;|&nbsp; Date: <?php echo date('d-M-Y h:i A'); ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>