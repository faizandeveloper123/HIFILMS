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
    while ($row = $res->fetch_assoc()) { $students[] = $row; }

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
        background:var(--theme); color:#fff; padding:8px 8px 22px; position:relative; flex-shrink:0;
    }
    .top:after {
        content:""; position:absolute; left:-12%; right:-12%; bottom:0; height:0; background:#fff;
        border-top:4px solid var(--accent); border-radius:0 0 50% 50%;
    }
    .brand { display:flex; align-items:center; gap:6px; position:relative; z-index:2; }
    .brand img { width:32px; height:32px; object-fit:contain; background:#fff; border-radius:50%; padding:2px; }
    .school {
        font-size:var(--school-font); line-height:1.05; font-weight:800; letter-spacing:.2px;
        text-transform:uppercase; word-break:break-word;
    }
    .photo-wrap {
        margin:6px auto 4px; width:70px; height:70px; border-radius:50%; border:4px solid var(--accent);
        background:#f3f4f6; padding:2px; flex-shrink:0; overflow:hidden;
    }
    .photo-wrap img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
    .photo-placeholder {
        width:100%; height:100%; border-radius:50%; background:#e5e7eb; color:#6b7280;
        display:flex; align-items:center; justify-content:center; text-align:center;
        font-size:9px; font-weight:700; line-height:1.2; padding:4px;
    }
    .name {
        margin:2px 6px 2px; text-align:center; color:var(--ink); font-size:var(--st-name-font);
        font-weight:900; line-height:1.15; text-transform:uppercase; flex-shrink:0; word-break:break-word;
    }
    .role {
        width:78%; max-width:100%; margin:0 auto; border-radius:999px; background:var(--accent);
        color:#083a2b; text-align:center; font-size:8px; font-weight:800;
        letter-spacing:0.8px; padding:4px 6px; flex-shrink:0;
    }
    .details {
        margin:6px 4px 4px; padding:3px 8px 6px; flex-shrink:0; display:flex;
        flex-direction:column; justify-content:flex-start;
    }
    .line { display:flex; align-items:flex-start; gap:5px; font-size:8.5px; margin:3px 0; line-height:1.25; }
    .line i { width:11px; flex-shrink:0; font-size:10px; color:var(--theme); font-style:normal; }
    .line b { color:var(--ink); }
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
    .cc-panel { width:430px; max-width:94%; margin:70px auto; background:#fff; border-radius:10px; padding:14px; box-shadow:0 10px 28px rgba(0,0,0,.25); }
    .cc-panel h4 { margin:0 0 10px; font-size:16px; }
    .cc-row { margin-bottom:10px; }
    .cc-row label { display:block; font-size:12px; font-weight:700; margin-bottom:4px; }
    .cc-row input, .cc-row select { width:100%; height:36px; padding:6px; border:1px solid #ccc; border-radius:6px; }
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
        <div class="cards-head">
            <h3><i class="fa fa-id-card"></i> Students Cards</h3>
            <div class="cards-actions no-print">
                <button onclick="window.print()" class="btn btn-success" <?php echo $sel_class > 0 ? '' : 'disabled'; ?>><i class="fa fa-print"></i> Print Cards</button>
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
                <label>&nbsp;</label>
                <div><span style="font-size:13px; color:#6B7280;"><?php echo count($students); ?> students</span></div>
            </div>
        </form>

        <div class="sheet" id="cardSheet">
            <?php if ($sel_class > 0 && count($students) === 0): ?>
                <div class="no-record" style="grid-column:1/-1;">No students in this class.</div>
            <?php elseif ($sel_class === 0): ?>
                <div class="no-record" style="grid-column:1/-1;">Select a class to generate student cards.</div>
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
                                    <div><div class="school"><?php echo e($schoolName); ?></div></div>
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
                                <div class="line"><i class="fa fa-user"></i> <b>Father Name:</b>&nbsp;<span><?php echo e($st['father_name'] ?? '-'); ?></span></div>
                                <div class="line"><i class="fa fa-barcode"></i> <b>GR No:</b>&nbsp;<span><?php echo e($grNo); ?></span></div>
                                <div class="line"><i class="fa fa-graduation-cap"></i> <b>Class:</b>&nbsp;<span><?php echo e($st['class_name'] ?? '-'); ?><?php echo !empty($st['section_name']) ? ' - ' . e($st['section_name']) : ''; ?></span></div>
                                <?php if ($showDOB): ?>
                                    <div class="line"><i class="fa fa-calendar"></i> <b>DOB:</b>&nbsp;<span><?php echo $st['dob'] ? date('d-M-Y', strtotime($st['dob'])) : '-'; ?></span></div>
                                <?php endif; ?>
                                <?php if ($showCell): ?>
                                    <div class="line"><i class="fa fa-phone"></i> <b>Cell No:</b>&nbsp;<span><?php echo e($st['phone'] ?? '-'); ?></span></div>
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
            <div class="cc-row" style="display:flex; gap:10px;">
                <div style="flex:1;"><label>Theme Color</label>
                    <input type="color" name="color" value="<?php echo $color; ?>"></div>
                <div style="flex:1;"><label>Display DOB</label>
                    <select name="DOB"><option value="YES" <?php echo $showDOB ? 'selected' : ''; ?>>YES</option><option value="NO" <?php echo !$showDOB ? 'selected' : ''; ?>>NO</option></select></div>
            </div>
            <div class="cc-row" style="display:flex; gap:10px;">
                <div style="flex:1;"><label>Display Cell Number</label>
                    <select name="cell_number"><option value="YES" <?php echo $showCell ? 'selected' : ''; ?>>YES</option><option value="NO" <?php echo !$showCell ? 'selected' : ''; ?>>NO</option></select></div>
                <div style="flex:1;"><label>Validity Date</label>
                    <input type="date" name="valid" value="<?php echo $valid; ?>"></div>
            </div>
            <div class="cc-row" style="display:flex; gap:10px;">
                <div style="flex:1;"><label>School Name Font Size</label>
                    <select name="school_name_font_size">
                        <?php for ($f = 8; $f <= 24; $f++): ?>
                            <option value="<?php echo $f; ?>" <?php echo $f == $schoolFs ? 'selected' : ''; ?>><?php echo $f; ?>px</option>
                        <?php endfor; ?>
                    </select></div>
                <div style="flex:1;"><label>Student Name Font Size</label>
                    <select name="name_font_size">
                        <?php for ($f = 8; $f <= 24; $f++): ?>
                            <option value="<?php echo $f; ?>" <?php echo $f == $nameFs ? 'selected' : ''; ?>><?php echo $f; ?>px</option>
                        <?php endfor; ?>
                    </select></div>
            </div>
            <div class="cc-actions">
                <button type="button" class="btn btn-default" onclick="closeCC();">Cancel</button>
                <button type="submit" class="btn btn-primary" style="color:#fff;">Apply</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCC(){ document.getElementById('ccOverlay').style.display = 'block'; }
function closeCC(){ document.getElementById('ccOverlay').style.display = 'none'; }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>