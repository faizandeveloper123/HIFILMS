<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Generate QR Codes';

$message = '';
$error = '';

$classes = [];
$res = db_query("SELECT class_id, class_name FROM classes WHERE status = 1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sel_class   = (int)($_GET['class_id'] ?? 0);
$sel_section = (int)($_GET['section_id'] ?? 0);

$sections = [];
if ($sel_class > 0) {
    $res = db_query("SELECT section_id, section_name FROM sections WHERE class_id = $sel_class ORDER BY section_name");
    while ($row = $res->fetch_assoc()) { $sections[] = $row; }
}

// Generate tokens for a class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_tokens') {
    $gen_class   = (int)($_POST['class_id'] ?? 0);
    $gen_section = (int)($_POST['section_id'] ?? 0);

    if ($gen_class <= 0) {
        $error = 'Please select a class.';
    } else {
        $sql = "SELECT student_id FROM students WHERE status = 1 AND class_id = ?";
        $params = [$gen_class]; $types = 'i';
        if ($gen_section > 0) {
            $sql .= " AND section_id = ?";
            $params[] = $gen_section; $types .= 'i';
        }
        $stmt = db_prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $count = 0;
        $ins = db_prepare("INSERT INTO qr_tokens (user_id, user_type, token, created_at, expires_at, is_active) VALUES (?, 'student', ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 1)");

        while ($st = $res->fetch_assoc()) {
            $sid = (int)$st['student_id'];
            // Check if token already exists
            $chk = db_prepare("SELECT id FROM qr_tokens WHERE user_id = ? AND user_type = 'student' AND is_active = 1");
            $chk->bind_param('i', $sid);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) continue;

            $token = 'QR-' . strtoupper(bin2hex(random_bytes(8)));
            $ins->bind_param('is', $sid, $token);
            $ins->execute();
            $count++;
        }
        $message = "$count new QR token(s) generated successfully!";
        header('Location: qr_generate.php?class_id=' . $gen_class . '&section_id=' . $gen_section . '&msg=' . urlencode($message));
        exit;
    }
}

if (isset($_GET['msg'])) { $message = $_GET['msg']; }

// Fetch students with tokens
$students = [];
if ($sel_class > 0) {
    $sql = "SELECT s.student_id, s.first_name, s.last_name, s.gr_no, s.roll_no, s.photo,
                   c.class_name, sec.section_name,
                   qt.token, qt.created_at AS token_created, qt.is_active
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.class_id
            LEFT JOIN sections sec ON s.section_id = sec.section_id
            LEFT JOIN qr_tokens qt ON s.student_id = qt.user_id AND qt.user_type = 'student' AND qt.is_active = 1
            WHERE s.status = 1 AND s.class_id = ?";
    $params = [$sel_class]; $types = 'i';
    if ($sel_section > 0) {
        $sql .= " AND s.section_id = ?";
        $params[] = $sel_section; $types .= 'i';
    }
    $sql .= " ORDER BY s.roll_no, s.first_name";
    $stmt = db_prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $students[] = $row; }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.qr-gen-wrapper { max-width: 1200px; margin: 0 auto; }
.filter-bar {
    display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;
    background: #fff; border-radius: 14px; padding: 16px 18px;
    box-shadow: 0 2px 12px rgba(0,0,0,.04); margin-bottom: 20px;
}
.filter-bar select, .filter-bar input {
    height: 40px; border-radius: 10px; border: 1px solid #e5e7eb;
    padding: 0 12px; font-size: 13px;
}
.filter-bar label { font-size: 12px; font-weight: 600; color: #6b7280; display: block; margin-bottom: 4px; }
.btn-generate {
    padding: 10px 22px; background: linear-gradient(135deg, #f97316, #fb923c);
    color: #fff; border: none; border-radius: 10px; font-weight: 700;
    font-size: 13px; cursor: pointer; box-shadow: 0 4px 12px rgba(249,115,22,.25);
    transition: all .2s;
}
.btn-generate:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(249,115,22,.35); }
.btn-print {
    padding: 10px 22px; background: #065f46; color: #fff; border: none;
    border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer;
}
.qr-student-card {
    background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.05);
    overflow: hidden; border: 1px solid #f3f4f6;
}
.qr-card-header {
    background: linear-gradient(135deg, #f97316, #fb923c);
    color: #fff; padding: 10px 14px; font-weight: 700; font-size: 12px;
    display: flex; justify-content: space-between; align-items: center;
}
.qr-card-body { padding: 14px; text-align: center; }
.qr-card-body img { border-radius: 8px; }
.qr-card-name { font-weight: 700; font-size: 14px; color: #111827; margin: 8px 0 2px; }
.qr-card-detail { font-size: 11.5px; color: #6b7280; }
.qr-card-token { font-size: 10px; color: #9ca3af; margin-top: 6px; word-break: break-all; font-family: monospace; }
.qr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
.stats-bar {
    display: flex; gap: 20px; margin-bottom: 16px; flex-wrap: wrap;
}
.stat-chip {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 600;
}
.stat-total { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.stat-token { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
.stat-missing { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.no-students { text-align: center; padding: 60px 20px; color: #9ca3af; }
.no-students i { font-size: 48px; margin-bottom: 12px; display: block; }

/* Print Styles */
@media print {
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea { position: absolute; left: 0; top: 0; width: 100%; }
    .no-print { display: none !important; }
    .print-grid {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;
        padding: 10mm;
    }
    .print-card {
        border: 1px solid #ccc; border-radius: 10px; overflow: hidden;
        page-break-inside: avoid; text-align: center; padding: 10px;
    }
    .print-card img { width: 120px; height: 120px; border-radius: 8px; border: 2px solid #f97316; }
    .print-card .name { font-weight: 700; font-size: 13px; margin: 6px 0 2px; }
    .print-card .detail { font-size: 11px; color: #555; }
    .print-card .qr-img { margin: 6px 0; }
    .print-card .token { font-size: 9px; color: #999; font-family: monospace; word-break: break-all; }
}
</style>

<div class="main-content">
<div class="container-fluid">
<div class="qr-gen-wrapper">

    <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px; flex-wrap:wrap; gap:10px;">
        <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;">
            <i class="fa fa-qrcode" style="color:#f97316;"></i> Generate QR Codes for Students
        </h3>
        <?php if ($sel_class > 0): ?>
            <button class="btn-print no-print" onclick="window.print()">
                <i class="fa fa-print"></i> Print QR Cards
            </button>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div style="background:#d1fae5; color:#065f46; padding:12px 18px; border-radius:10px; margin-bottom:16px; font-size:13px; font-weight:600;">
            <i class="fa fa-check-circle"></i> <?php echo e($message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="background:#fef2f2; color:#991b1b; padding:12px 18px; border-radius:10px; margin-bottom:16px; font-size:13px; font-weight:600;">
            <i class="fa fa-exclamation-circle"></i> <?php echo e($error); ?>
        </div>
    <?php endif; ?>

    <form method="GET" class="filter-bar no-print">
        <div>
            <label>Class</label>
            <select name="class_id" id="classSelect" onchange="loadSections()" required>
                <option value="0">-- Select Class --</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo (int)$c['class_id']; ?>" <?php echo $sel_class == $c['class_id'] ? 'selected' : ''; ?>>
                        <?php echo e($c['class_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Section (Optional)</label>
            <select name="section_id" id="sectionSelect">
                <option value="0">All Sections</option>
                <?php foreach ($sections as $s): ?>
                    <option value="<?php echo (int)$s['section_id']; ?>" <?php echo $sel_section == $s['section_id'] ? 'selected' : ''; ?>>
                        <?php echo e($s['section_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn-generate" style="height:40px;">
                <i class="fa fa-search"></i> Load Students
            </button>
        </div>
    </form>

    <?php if ($sel_class > 0 && count($students) > 0): ?>
        <?php
        $total = count($students);
        $withToken = 0;
        foreach ($students as $st) { if (!empty($st['token'])) $withToken++; }
        $missing = $total - $withToken;
        ?>
        <div class="stats-bar no-print">
            <div class="stat-chip stat-total"><i class="fa fa-users"></i> Total Students: <?php echo $total; ?></div>
            <div class="stat-chip stat-token"><i class="fa fa-qrcode"></i> With QR Token: <?php echo $withToken; ?></div>
            <?php if ($missing > 0): ?>
                <div class="stat-chip stat-missing"><i class="fa fa-exclamation-triangle"></i> Missing Token: <?php echo $missing; ?></div>
            <?php endif; ?>
        </div>

        <?php if ($missing > 0): ?>
            <form method="POST" class="no-print" style="margin-bottom:16px;">
                <input type="hidden" name="action" value="generate_tokens">
                <input type="hidden" name="class_id" value="<?php echo $sel_class; ?>">
                <input type="hidden" name="section_id" value="<?php echo $sel_section; ?>">
                <button type="submit" class="btn-generate">
                    <i class="fa fa-magic"></i> Generate <?php echo $missing; ?> Missing QR Token(s)
                </button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Screen Grid -->
    <?php if ($sel_class > 0 && count($students) > 0): ?>
        <div class="qr-grid no-print">
            <?php foreach ($students as $st): ?>
                <div class="qr-student-card">
                    <div class="qr-card-header">
                        <span><?php echo e($st['class_name'] ?? ''); ?> <?php echo e($st['section_name'] ?? ''); ?></span>
                        <span>GR: <?php echo e($st['gr_no'] ?? '-'); ?></span>
                    </div>
                    <div class="qr-card-body">
                        <?php if (!empty($st['photo']) && file_exists(__DIR__ . '/uploads/students/' . $st['photo'])): ?>
                            <img src="<?php echo BASE_URL . 'uploads/students/' . e($st['photo']); ?>"
                                 alt="" style="width:64px; height:64px; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <div style="width:64px; height:64px; border-radius:50%; background:#fff7ed; display:inline-flex; align-items:center; justify-content:center; font-size:24px; color:#f97316; font-weight:700; border:2px solid #fed7aa;">
                                <?php echo strtoupper(substr($st['first_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="qr-card-name"><?php echo e($st['first_name'] . ' ' . $st['last_name']); ?></div>
                        <div class="qr-card-detail">Roll #<?php echo e($st['roll_no'] ?? '-'); ?></div>
                        <?php if (!empty($st['token'])): ?>
                            <div class="qr-card-token">
                                <img src="https://chart.googleapis.com/chart?cht=qr&chs=150x150&chl=<?php echo urlencode($st['token']); ?>"
                                     alt="QR" style="width:120px; height:120px; margin:6px 0; border-radius:6px;">
                                <div><?php echo e($st['token']); ?></div>
                            </div>
                        <?php else: ?>
                            <div style="padding:20px; color:#dc2626; font-size:12px; font-weight:600;">
                                <i class="fa fa-exclamation-triangle"></i> No QR token generated
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif ($sel_class > 0): ?>
        <div class="no-students">
            <i class="fa fa-user-slash"></i>
            <p>No students found in this class.</p>
        </div>
    <?php else: ?>
        <div class="no-students">
            <i class="fa fa-qrcode"></i>
            <p>Select a class to generate and view QR codes for students.</p>
        </div>
    <?php endif; ?>

    <!-- Print Area (hidden on screen) -->
    <div id="printArea">
        <?php if ($sel_class > 0 && count($students) > 0): ?>
            <div class="print-grid">
                <?php foreach ($students as $st): ?>
                    <div class="print-card">
                        <?php if (!empty($st['photo']) && file_exists(__DIR__ . '/uploads/students/' . $st['photo'])): ?>
                            <img src="<?php echo BASE_URL . 'uploads/students/' . e($st['photo']); ?>" alt="">
                        <?php else: ?>
                            <div style="width:120px; height:120px; border-radius:50%; background:#f0f0f0; display:inline-flex; align-items:center; justify-content:center; font-size:40px; color:#999; border:2px solid #ddd;">
                                <?php echo strtoupper(substr($st['first_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="name"><?php echo e($st['first_name'] . ' ' . $st['last_name']); ?></div>
                        <div class="detail"><?php echo e($st['class_name'] ?? ''); ?> | GR: <?php echo e($st['gr_no'] ?? '-'); ?> | Roll: <?php echo e($st['roll_no'] ?? '-'); ?></div>
                        <?php if (!empty($st['token'])): ?>
                            <div class="qr-img">
                                <img src="https://chart.googleapis.com/chart?cht=qr&chs=150x150&chl=<?php echo urlencode($st['token']); ?>" alt="QR">
                            </div>
                            <div class="token"><?php echo e($st['token']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>
</div>
</div>

<script>
function loadSections() {
    var classId = document.getElementById('classSelect').value;
    var sectionSel = document.getElementById('sectionSelect');
    sectionSel.innerHTML = '<option value="0">All Sections</option>';
    if (classId <= 0) return;

    fetch('<?php echo BASE_URL; ?>ajax_get_sections.php?class_id=' + classId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
        data.forEach(function(s) {
            var opt = document.createElement('option');
            opt.value = s.section_id;
            opt.textContent = s.section_name;
            sectionSel.appendChild(opt);
        });
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
