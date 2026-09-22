<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Students Cards';

$schoolName  = get_setting('school_name', 'LAPS School & College');
$schoolAddr  = get_setting('school_address', '');
$schoolPhone = get_setting('school_phone', '');
$schoolLogo  = get_setting('school_logo', '');
$logoSrc     = $schoolLogo !== '' ? BASE_URL . $schoolLogo : BASE_URL . 'assets/img/logo.jpg';
$sigImg      = get_setting('signature_image', '');
$sigSrc      = $sigImg !== '' ? BASE_URL . 'assets/uploads/' . basename($sigImg) : '';

$sel_class = (int) ($_GET['class_id'] ?? 0);
$sel_section = (int) ($_GET['section_id'] ?? 0);
$sel_student = (int) ($_GET['student_id'] ?? 0);
$viewMode = isset($_GET['view']) && (int)($_GET['view'] ?? 0) === 1;

$valid   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['valid'] ?? '') ? $_GET['valid'] : date('Y-m-d', strtotime('+1 year'));
$color   = isset($_GET['color']) && preg_match('/^[0-9a-fA-F]{6}$/', $_GET['color']) ? '#' . $_GET['color'] : '#0067d7';
$schoolFs = (int)($_GET['school_name_font_size'] ?? 14);
if ($schoolFs < 8) $schoolFs = 8; if ($schoolFs > 26) $schoolFs = 26;
$nameFs  = (int)($_GET['name_font_size'] ?? 15);
if ($nameFs < 8) $nameFs = 8; if ($nameFs > 26) $nameFs = 26;
$showDOB  = ($_GET['DOB'] ?? 'YES') !== 'NO';
$showCell = ($_GET['cell_number'] ?? 'YES') !== 'NO';
$accent   = '#f2d500';

$classes = [];
$res = db_query("SELECT class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sections = [];
if ($sel_class > 0) {
    $res = db_query("SELECT section_id, section_name FROM sections WHERE class_id=$sel_class ORDER BY section_name");
    while ($row = $res->fetch_assoc()) { $sections[] = $row; }
}

$students = [];
$allClassStudents = [];
if ($sel_class > 0) {
    $sql = "SELECT s.*, c.class_name, sec.section_name, qt.token AS qr_token
            FROM students s
            LEFT JOIN classes c ON s.class_id=c.class_id
            LEFT JOIN sections sec ON s.section_id=sec.section_id
            LEFT JOIN qr_tokens qt ON s.student_id = qt.user_id AND qt.user_type='student' AND qt.is_active=1
            WHERE s.class_id=$sel_class AND s.status=1";
    if ($sel_section > 0) { $sql .= " AND s.section_id=$sel_section"; }
    $sql .= " ORDER BY s.first_name";
    $res = db_query($sql);
    while ($row = $res->fetch_assoc()) { $students[] = $row; $allClassStudents[] = $row; }

    // Ensure every student has a unique random QR token
    $insToken = db_prepare("INSERT INTO qr_tokens (user_id, user_type, token, created_at, expires_at, is_active) VALUES (?, 'student', ?, NOW(), DATE_ADD(NOW(), INTERVAL 2 YEAR), 1)");
    foreach ($students as $i => $st) {
        if (empty($st['qr_token'])) {
            $token = 'QR-' . strtoupper(bin2hex(random_bytes(8)));
            $sid = (int) $st['student_id'];
            $insToken->bind_param('is', $sid, $token);
            $insToken->execute();
            $students[$i]['qr_token'] = $token;
        }
    }

    if ($sel_student > 0) {
        $students = array_values(array_filter($students, function ($st) use ($sel_student) {
            return (int) $st['student_id'] === $sel_student;
        }));
    }
}

function card_photo_upload($file, $dir, $prefix) {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { return null; }
    if ((int)($file['size'] ?? 0) > 5242880) { return null; }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) { return null; }
    $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    $ext = $map[$info[2]] ?? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) { $ext = 'jpg'; }
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $name = $prefix . time() . '_' . rand(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) { return null; }
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_card_photo') {
    $sid = (int)($_POST['card_student_id'] ?? 0);
    if ($sid > 0) {
        $pn = card_photo_upload($_FILES['card_photo'] ?? null, __DIR__ . '/uploads/students', 's_');
        if ($pn !== null) {
            $up = db_prepare("UPDATE students SET photo=? WHERE student_id=?");
            $up->bind_param('si', $pn, $sid);
            $up->execute();
        }
    }
    $ccq = ['class_id', 'section_id', 'student_id', 'valid', 'color', 'school_name_font_size', 'name_font_size', 'DOB', 'cell_number'];
    $qs = [];
    foreach ($ccq as $k) { if (isset($_POST[$k]) && trim((string)$_POST[$k]) !== '') { $qs[$k] = (string)$_POST[$k]; } }
    $qs['class_id'] = (int)($_POST['class_id'] ?? 0);
    $qs['section_id'] = (int)($_POST['section_id'] ?? 0);
    header('Location: ' . BASE_URL . 'students_card.php?' . http_build_query($qs));
    exit;
}

include __DIR__ . '/includes/header.php';
?>
<style>
    :root {
        --theme: <?php echo $color; ?>;
        --accent: <?php echo $accent; ?>;
        --ink: <?php echo $color; ?>;
        --card-w: 2.2in;
        --card-h: 3.6in;
        --safe-inset: 0.125in;
        --school-font: <?php echo $schoolFs; ?>px;
        --st-name-font: <?php echo $nameFs; ?>px;
    }
    .cards-head { display:flex; align-items:center; justify-content:space-between; padding:14px 4px; flex-wrap:wrap; gap:10px; }
    .cards-head h3 { font-size:18px; font-weight:800; color:#111827; margin:0; }
    .cards-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
    .sheet {
        background:#e8e8e8; padding:8mm; border-radius:12px;
        display:grid; grid-template-columns:repeat(3, var(--card-w)); gap:4mm 4mm; justify-content:center;
        max-width:1100px; margin:0 auto;
    }
    .id-card {
        position:relative; width:var(--card-w); min-height:var(--card-h);
        padding:var(--safe-inset); border-radius:18px; overflow:hidden;
        border:1px dashed #999; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.12);
        break-inside:avoid;
    }
    .id-card::before {
        content:""; position:absolute; inset:var(--safe-inset);
        border:1px dotted rgba(0,0,0,0.12); border-radius:8px; pointer-events:none; z-index:6;
    }
    .id-card-inner { position:relative; z-index:1; width:100%; min-height:100%; display:flex; flex-direction:column; }
    .top {
        background:linear-gradient(135deg, var(--theme) 0%, var(--ink) 100%); color:#fff;
        padding:9px 10px 16px; position:relative; flex-shrink:0;
        border-bottom:6px solid var(--accent);
    }
    .brand { display:flex; align-items:center; gap:8px; position:relative; z-index:2; }
    .brand img {
        width:38px; height:38px; object-fit:contain; background:#fff;
        border-radius:50%; padding:3px; box-shadow:0 2px 5px rgba(0,0,0,.25);
    }
    .school {
        font-size:var(--school-font); line-height:1.05; font-weight:800; letter-spacing:.2px;
        text-transform:uppercase; word-break:break-word;
    }
    .school-sub {
        font-size:7px; font-weight:600; color:rgba(255,255,255,.9);
        line-height:1.25; margin-top:2px; word-break:break-word;
    }
    .photo-wrap {
        margin:8px auto 5px; width:80px; height:98px; border-radius:10px;
        border:3px solid var(--theme); background:#fff; padding:3px; flex-shrink:0;
        overflow:hidden; box-shadow:0 3px 8px rgba(0,0,0,.14);
    }
    .photo-wrap img { width:100%; height:100%; border-radius:7px; object-fit:cover; display:block; }
    .photo-placeholder {
        width:100%; height:100%; border-radius:7px; background:#eef0f3; color:var(--theme);
        display:flex; align-items:center; justify-content:center;
        font-size:34px; font-weight:900;
    }
    .name {
        margin:2px 8px 2px; text-align:center; color:var(--ink); font-size:var(--st-name-font);
        font-weight:900; line-height:1.15; text-transform:uppercase; flex-shrink:0; word-break:break-word;
    }
    .role {
        width:72%; max-width:100%; margin:0 auto; border-radius:999px; background:var(--accent);
        color:#083a2b; text-align:center; font-size:8px; font-weight:800;
        letter-spacing:0.8px; padding:4px 6px; flex-shrink:0;
    }
    .details {
        margin:5px 8px 4px; padding:0; flex-shrink:0;
        display:grid; grid-template-columns:1fr 1fr; gap:5px 8px;
    }
    .detail {
        background:#f2f4f7; border:1px solid #eceff3; border-radius:7px;
        padding:3px 8px; min-width:0; line-height:1.35;
    }
    .detail.wide { grid-column:1/-1; }
    .detail .lbl { display:block; font-size:6.5px; font-weight:800; color:#9aa3af; text-transform:uppercase; letter-spacing:.5px; }
    .detail .val { display:block; font-size:9.5px; font-weight:700; color:#111827; word-break:break-word; }
    .foot {
        margin:0 6px 6px; display:grid; grid-template-columns:1fr auto 1fr; align-items:end;
        gap:4px 6px; font-size:8px; flex-shrink:0;
    }
    .foot-left { justify-self:start; text-align:left; min-width:0; margin-bottom:2px; }
    .foot-date-line { font-size:7px; line-height:1.4; white-space:nowrap; }
    .foot-lbl { font-weight:700; color:var(--ink); }
    .foot-qr { justify-self:center; align-self:end; }
    .foot-right { justify-self:end; text-align:center; min-width:0; }
    .foot-principal-label { font-weight:700; color:var(--ink); line-height:1.2; padding-bottom:2px; }
    .sign-below { text-align:center; margin:2px 0 4px; flex-shrink:0; }
    .sign-below img { height:20px; max-width:64px; object-fit:contain; display:block; margin:0 auto 1px; }
    .sign-below .sig-cap { font-size:8px; color:#333; line-height:1; }
    .bar {
        height:6px; margin-top:auto; flex-shrink:0; border-radius:0 0 14px 14px;
        background:linear-gradient(to right,var(--accent) 0%, var(--theme) 25%, var(--theme) 100%);
    }
    .qr-wrap { margin:0; width:70px; height:70px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .qr-wrap img { width:64px !important; height:64px !important; }
    .no-record { text-align:center; padding:40px; color:#6B7280; background:#fff; border-radius:12px; }

    /* customization UI */
    .cc-ui { position:fixed; top:12px; right:12px; z-index:9999; }
    .cc-btn { background:<?php echo $color; ?>; color:#fff; border:none; border-radius:6px; padding:8px 12px; font-size:13px; cursor:pointer; margin-left:6px; }
    .cc-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; z-index:10000; }
    .cc-panel { width:430px; max-width:94%; max-height:calc(100vh - 32px); overflow-y:auto; margin:16px auto; background:#fff; border-radius:10px; padding:16px; box-shadow:0 10px 28px rgba(0,0,0,.25); scrollbar-width:thin; }
    .cc-panel::-webkit-scrollbar { width:6px; }
    .cc-panel::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:3px; }
    .cc-panel h4 { margin:0 0 10px; font-size:16px; }
    .cc-row { margin-bottom:10px; }
    .cc-row label { display:block; font-size:12px; font-weight:700; margin-bottom:4px; }
    .cc-row input, .cc-row select { width:100%; height:36px; padding:6px; border:1px solid #ccc; border-radius:6px; }
    @media (max-width:480px){
        .cc-panel { width:100%; max-width:100%; margin:8px auto; padding:12px; }
        .cc-row[style] { flex-direction:column !important; }
    }
    .cc-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:10px; }

    @media (max-width:900px){ .sheet{ grid-template-columns:repeat(2, var(--card-w)); justify-content:center; } }
    @media (max-width:520px){ .sheet{ grid-template-columns:1fr; justify-items:center; } }
    @media print {
        @page { size:A4 portrait; margin:5mm; }
        body { background:#fff; }
        body * { visibility:hidden; }
        #cardSheet, #cardSheet * { visibility:visible; }
        #cardSheet {
            position:absolute; left:0; top:0; width:100%; margin:0; padding:2mm;
            background:#fff; grid-template-columns:repeat(3, var(--card-w));
        }
        .no-print { display:none !important; }
        .id-card { box-shadow:none; border:1px solid #bbb; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .id-card::before { display:none; }
    }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if (!$viewMode): ?>
        <div class="cards-head">
            <h3><i class="fa fa-id-card"></i> Students Cards</h3>
            <div class="cards-actions no-print">
                <button onclick="window.print()" class="btn btn-success" <?php echo $sel_student > 0 ? '' : 'disabled'; ?>><i class="fa fa-print"></i> Print Cards</button>
                <button type="button" class="btn btn-primary" style="color:#fff;" onclick="openCC()"><i class="fa fa-paint-brush"></i> Apply Customization</button>
                <a href="<?php echo BASE_URL; ?>cards.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-id-card"></i> Staff Cards</a>
            </div>
        </div>

        <form method="get" action="students_card.php" class="search-bar-student no-print">
            <div class="form-group col-md-4" style="margin-bottom:0;">
                <label>Course/Class</label>
                <select name="class_id" class="form-control" required onchange="this.form.submit()">
                    <option value="">Select Course/Class</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>" <?php echo $sel_class == $c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>Section</label>
                <select name="section_id" class="form-control" onchange="this.form.submit()">
                    <option value="0">All Sections</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo $s['section_id']; ?>" <?php echo $sel_section == $s['section_id'] ? 'selected' : ''; ?>><?php echo e($s['section_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>Student</label>
                <select name="student_id" class="form-control" onchange="if(this.value){location.href='<?php echo BASE_URL; ?>students_card.php?class_id=<?php echo $sel_class; ?>&section_id=<?php echo $sel_section; ?>&student_id='+encodeURIComponent(this.value)+'&view=1';}">
                    <option value="0">Select Student</option>
                    <?php foreach ($allClassStudents as $ss): $ssn = trim(($ss['first_name'] ?? '') . ' ' . ($ss['last_name'] ?? '')); ?>
                        <option value="<?php echo (int)$ss['student_id']; ?>" <?php echo $sel_student === (int)$ss['student_id'] ? 'selected' : ''; ?>>
                            <?php echo (int)$ss['student_id']; ?>. <?php echo e($ssn); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>&nbsp;</label>
                <div><span style="font-size:13px; color:#6B7280;"><?php echo count($students); ?> students</span></div>
            </div>
        </form>

        <?php else: ?>
        <div class="cards-head no-print">
            <h3><i class="fa fa-id-card"></i> Student Card</h3>
            <div class="cards-actions no-print">
                <a href="<?php echo BASE_URL; ?>students_card.php?class_id=<?php echo $sel_class; ?>&section_id=<?php echo $sel_section; ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                <button onclick="window.print()" class="btn btn-success"><i class="fa fa-print"></i> Print Card</button>
            </div>
        </div>
        <?php endif; ?>

        <div class="sheet" id="cardSheet">
            <?php if ($sel_class === 0): ?>
                <div class="no-record" style="grid-column:1/-1;">Select a class to generate student cards.</div>
            <?php elseif ($sel_student === 0): ?>
                <div class="no-record" style="grid-column:1/-1;">Pehle student ka naam select karein, phir uska card khulega.</div>
            <?php elseif (count($students) === 0): ?>
                <div class="no-record" style="grid-column:1/-1;">No students in this class.</div>
            <?php else: ?>
                <?php foreach ($students as $st):
                    $fullName = trim(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? ''));
                    $initial = strtoupper(substr($fullName !== '' ? $fullName : 'S', 0, 1));
                    $grNo = trim($st['gr_no'] ?? '') !== '' ? $st['gr_no'] : ('STD-' . $st['student_id']);
                    $photo = '';
                    if (!empty($st['photo']) && is_file(__DIR__ . '/uploads/students/' . $st['photo'])) {
                        $photo = BASE_URL . 'uploads/students/' . e($st['photo']);
                    }
                    $qrPayload = $st['qr_token'];
                    $qrSrc = BASE_URL . 'qr_image.php?d=' . urlencode($qrPayload);
                ?>
                    <div class="id-card">
                        <div class="id-card-inner">
                            <div class="top">
                                <div class="brand">
                                    <img src="<?php echo $logoSrc; ?>" alt="Logo" onerror="this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';">
                                    <div>
                                        <div class="school"><?php echo e($schoolName); ?></div>
                                        <?php if (trim($schoolAddr) !== ''): ?>
                                            <div class="school-sub"><?php echo e((strlen($schoolAddr) > 44 ? substr($schoolAddr, 0, 44) . '…' : $schoolAddr)); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="photo-wrap">
                                <?php if ($photo !== ''): ?>
                                    <img src="<?php echo $photo; ?>" alt="">
                                <?php else: ?>
                                    <div class="photo-placeholder"><?php echo $initial; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="name"><?php echo e($fullName); ?></div>
                            <div class="role">STUDENT</div>

                            <div class="details">
                                <div class="detail wide"><span class="lbl">Father Name</span><span class="val"><?php echo e($st['father_name'] ?? '-'); ?></span></div>
                                <div class="detail"><span class="lbl">Class</span><span class="val"><?php echo e($st['class_name'] ?? '-'); ?><?php echo !empty($st['section_name']) ? ' - ' . e($st['section_name']) : ''; ?></span></div>
                                <div class="detail"><span class="lbl">GR No</span><span class="val"><?php echo e($grNo); ?></span></div>
                                <?php if ($showDOB): ?>
                                    <div class="detail"><span class="lbl">DOB</span><span class="val"><?php echo $st['dob'] ? date('d-M-Y', strtotime($st['dob'])) : '-'; ?></span></div>
                                <?php endif; ?>
                                <?php if ($showCell): ?>
                                    <div class="detail"><span class="lbl">Cell No</span><span class="val"><?php echo e($st['phone'] ?? '-'); ?></span></div>
                                <?php endif; ?>
                            </div>

                            <div class="foot">
                                <div class="foot-left">
                                    <div class="foot-date-line"><?php echo date('d-M-Y', strtotime($valid)); ?></div>
                                    <div class="foot-date-line"><span class="foot-lbl">Valid Till:</span></div>
                                </div>
                                <div class="foot-qr">
                                    <div class="qr-wrap">
                                        <img src="<?php echo $qrSrc; ?>" alt="QR Code" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/img/qr-default.png';">
                                    </div>
                                </div>
                                <div class="foot-right">
                                    <div class="foot-principal-label">
                                        <?php if ($sigSrc !== ''): ?>
                                            <div class="sign-below">
                                                <img src="<?php echo $sigSrc; ?>" onerror="this.style.display='none';">
                                                <div class="sig-cap">Principal</div>
                                            </div>
                                        <?php else: ?>
                                            <div class="sign-below">
                                                <div style="border-top:1px solid #333; width:56px; margin:0 auto 1px;"></div>
                                                <div class="sig-cap">Principal</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="bar"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Customization modal -->
<div id="ccOverlay" class="cc-overlay no-print" onclick="if(event.target===this) closeCC();">
    <div class="cc-panel">
        <h4>Card Settings</h4>
        <form method="get" action="<?php echo BASE_URL; ?>students_card.php">
            <input type="hidden" name="class_id" value="<?php echo $sel_class; ?>">
            <input type="hidden" name="section_id" value="<?php echo $sel_section; ?>">
            <input type="hidden" name="student_id" value="<?php echo $sel_student; ?>">
            <div class="cc-row"><label>Theme Color</label>
                <input type="color" name="color" value="<?php echo $color; ?>"></div>
            <div class="cc-row" style="display:flex; gap:10px;">
                <div style="flex:1;"><label>Display DOB</label>
                    <select name="DOB"><option value="YES" <?php echo $showDOB ? 'selected' : ''; ?>>YES</option><option value="NO" <?php echo !$showDOB ? 'selected' : ''; ?>>NO</option></select></div>
                <div style="flex:1;"><label>Display Cell Number</label>
                    <select name="cell_number"><option value="YES" <?php echo $showCell ? 'selected' : ''; ?>>YES</option><option value="NO" <?php echo !$showCell ? 'selected' : ''; ?>>NO</option></select></div>
            </div>
            <div class="cc-row"><label>School Name Font Size</label>
                <select name="school_name_font_size">
                    <?php for ($f = 8; $f <= 24; $f++): ?>
                        <option value="<?php echo $f; ?>" <?php echo $f == $schoolFs ? 'selected' : ''; ?>><?php echo $f; ?>px</option>
                    <?php endfor; ?>
                </select></div>
            <div class="cc-row"><label>Student Name Font Size</label>
                <select name="name_font_size">
                    <?php for ($f = 8; $f <= 24; $f++): ?>
                        <option value="<?php echo $f; ?>" <?php echo $f == $nameFs ? 'selected' : ''; ?>><?php echo $f; ?>px</option>
                    <?php endfor; ?>
                </select></div>
            <div class="cc-row"><label>Validity Date</label>
                <input type="date" name="valid" value="<?php echo $valid; ?>"></div>
            <div class="cc-actions">
                <button type="button" class="btn btn-default" onclick="closeCC();">Cancel</button>
                <button type="submit" class="btn btn-primary" style="color:#fff;">Apply</button>
            </div>
        </form>

        <hr style="border:none; border-top:1px solid #eee; margin:16px 0;">
        <h4 style="font-size:14px; margin:0 0 10px;"><i class="fa fa-camera" style="color:#FF7A1B;"></i> Change Student Photo</h4>
        <?php if (count($allClassStudents) > 0): ?>
            <?php
                $stuPhotoMap = [];
                foreach ($allClassStudents as $st) {
                    $pf = '';
                    if (!empty($st['photo']) && is_file(__DIR__ . '/uploads/students/' . $st['photo'])) {
                        $pf = BASE_URL . 'uploads/students/' . e($st['photo']);
                    }
                    $initial = strtoupper(substr(trim(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? '')), 0, 1)) ?: 'S';
                    $stuPhotoMap[(int)$st['student_id']] = ['url' => $pf, 'initial' => $initial];
                }
            ?>
            <form method="post" enctype="multipart/form-data" action="<?php echo BASE_URL; ?>students_card.php">
                <input type="hidden" name="action" value="update_card_photo">
                <input type="hidden" name="class_id" value="<?php echo $sel_class; ?>">
                <input type="hidden" name="section_id" value="<?php echo $sel_section; ?>">
                <input type="hidden" name="valid" value="<?php echo $valid; ?>">
                <input type="hidden" name="color" value="<?php echo ltrim($color, '#'); ?>">
                <input type="hidden" name="school_name_font_size" value="<?php echo $schoolFs; ?>">
                <input type="hidden" name="name_font_size" value="<?php echo $nameFs; ?>">
                <input type="hidden" name="DOB" value="<?php echo $showDOB ? 'YES' : 'NO'; ?>">
                <input type="hidden" name="cell_number" value="<?php echo $showCell ? 'YES' : 'NO'; ?>">
                <div class="cc-row"><label>Student</label>
                    <select name="card_student_id" id="ccStuSel" onchange="updStudentPick(this)">
                        <?php foreach ($allClassStudents as $st): ?>
                            <option value="<?php echo (int)$st['student_id']; ?>"><?php echo (int)$st['student_id']; ?>. <?php echo e(trim($st['first_name'] . ' ' . ($st['last_name'] ?? ''))); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="cc-row" style="justify-content:center;">
                    <label class="pick-box" for="card_photo_input">
                        <img id="studentPickPrev" alt="">
                        <span class="pick-empty" id="studentPickEmpty"><i class="fa fa-graduation-cap"></i><br><small>Photo Pick Karne ke liye Click Karein</small></span>
                        <span class="pick-cam"><i class="fa fa-camera"></i> Change Photo</span>
                    </label>
                    <input type="file" id="card_photo_input" name="card_photo" accept="image/*" style="display:none;" required onchange="previewStudentPhoto(this)">
                </div>
                <div class="cc-actions">
                    <button type="submit" class="btn btn-success" style="color:#fff;"><i class="fa fa-upload"></i> Update Photo</button>
                </div>
            </form>
        <?php else: ?>
            <p style="color:#9CA3AF; font-size:12px; margin:0;">Pehle koi class select karein (upar search bar se).</p>
        <?php endif; ?>
    </div>
</div>

<style>
    .pick-box{ position:relative; display:flex; align-items:center; justify-content:center; width:110px; height:110px; border-radius:14px; border:2px dashed #d1d5db; background:#f9fafb; cursor:pointer; overflow:hidden; margin:0 auto; }
    .pick-box img{ width:100%; height:100%; object-fit:cover; display:none; border-radius:12px; }
    .pick-empty{ display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; color:#9CA3AF; font-size:26px; gap:4px; }
    .pick-empty small{ font-size:10px; line-height:1.3; font-weight:600; text-transform:uppercase; letter-spacing:.3px; padding:0 8px; }
    .pick-cam{ position:absolute; left:0; right:0; bottom:0; background:rgba(0,0,0,.55); color:#fff; font-size:10.5px; font-weight:700; text-align:center; padding:3px 0; text-transform:uppercase; letter-spacing:.3px; }
</style>

<script>
var STUDENT_PHOTOS = {
    <?php foreach ($stuPhotoMap as $sid => $info): ?>
        "<?php echo $sid; ?>": { url: "<?php echo $info['url']; ?>", initial: "<?php echo $info['initial']; ?>" },
    <?php endforeach; ?>
};
function updStudentPick(sel) {
    var info = STUDENT_PHOTOS[sel.value] || { url: '', initial: 'S' };
    var img = document.getElementById('studentPickPrev');
    var empty = document.getElementById('studentPickEmpty');
    if (info.url) { img.src = info.url; img.style.display = 'block'; empty.style.display = 'none'; }
    else { img.style.display = 'none'; empty.innerHTML = '<i class="fa fa-graduation-cap"></i><br><small>' + info.initial + ' | No Photo</small>'; empty.style.display = 'flex'; }
}
function previewStudentPhoto(input) {
    if (input.files && input.files[0]) {
        var rd = new FileReader();
        rd.onload = function(e) {
            var img = document.getElementById('studentPickPrev');
            img.src = e.target.result; img.style.display = 'block';
            document.getElementById('studentPickEmpty').style.display = 'none';
        };
        rd.readAsDataURL(input.files[0]);
    }
}
document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('ccStuSel');
    if (sel) updStudentPick(sel);
});
</script>

<script>
function openCC(){ var ov = document.getElementById('ccOverlay'); ov.style.display = 'block'; var p = ov.querySelector('.cc-panel'); if (p) p.scrollTop = 0; }
function closeCC(){ document.getElementById('ccOverlay').style.display = 'none'; }
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>