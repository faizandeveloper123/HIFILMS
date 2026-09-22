<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Staff Cards';

$schoolName  = get_setting('school_name', 'LAPS School & College');
$schoolPhone = get_setting('school_phone', '');
$schoolLogo  = get_setting('school_logo', '');
$logoSrc     = $schoolLogo !== '' ? BASE_URL . $schoolLogo : BASE_URL . 'assets/img/logo.jpg';
$sigImg      = get_setting('signature_image', '');
$sigSrc      = $sigImg !== '' ? BASE_URL . 'assets/uploads/' . basename($sigImg) : '';

$theme   = isset($_GET['color']) && preg_match('/^[0-9a-fA-F]{6}$/', $_GET['color']) ? '#' . $_GET['color'] : '#0b5a43';
$schoolFs = (int)($_GET['school_name_font_size'] ?? 16);
if ($schoolFs < 8) $schoolFs = 8; if ($schoolFs > 30) $schoolFs = 30;
$empFs    = (int)($_GET['emp_name_font_size'] ?? 15);
if ($empFs < 8) $empFs = 8; if ($empFs > 26) $empFs = 26;
$valid    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['valid'] ?? '') ? $_GET['valid'] : date('Y-m-d', strtotime('+1 year'));
$accent   = '#f2d500';

$employees = [];
$res = db_query("SELECT e.*, qt.token AS qr_token
                 FROM employees e
                 LEFT JOIN qr_tokens qt ON qt.user_id = e.emp_id AND qt.user_type IN ('staff','employee') AND qt.is_active = 1
                 WHERE e.status = 1 ORDER BY e.emp_id");
while ($row = $res->fetch_assoc()) { $employees[] = $row; }

// Ensure every staff member has a unique random QR token
$insToken = db_prepare("INSERT INTO qr_tokens (user_id, user_type, token, created_at, expires_at, is_active) VALUES (?, 'staff', ?, NOW(), DATE_ADD(NOW(), INTERVAL 2 YEAR), 1)");
foreach ($employees as $i => $emp) {
    if (empty($emp['qr_token'])) {
        $token = 'QR-' . strtoupper(bin2hex(random_bytes(8)));
        $eid = (int) $emp['emp_id'];
        $insToken->bind_param('is', $eid, $token);
        $insToken->execute();
        $employees[$i]['qr_token'] = $token;
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
    $eid = (int)($_POST['card_emp_id'] ?? 0);
    if ($eid > 0) {
        $pn = card_photo_upload($_FILES['card_photo'] ?? null, __DIR__ . '/uploads/employees', 'emp_');
        if ($pn !== null) {
            $up = db_prepare("UPDATE employees SET photo=? WHERE emp_id=?");
            $up->bind_param('si', $pn, $eid);
            $up->execute();
        }
    }
    $ccq = ['valid', 'color', 'school_name_font_size', 'emp_name_font_size'];
    $qs = [];
    foreach ($ccq as $k) { if (isset($_POST[$k]) && trim((string)$_POST[$k]) !== '') { $qs[$k] = (string)$_POST[$k]; } }
    header('Location: ' . BASE_URL . 'cards.php?' . http_build_query($qs));
    exit;
}

include __DIR__ . '/includes/header.php';
?>
<style>
    :root {
        --theme: <?php echo $theme; ?>;
        --accent: <?php echo $accent; ?>;
        --ink: <?php echo $theme; ?>;
        --card-w: 2.2in;
        --card-h: 3.6in;
        --safe-inset: 0.125in;
        --school-font: <?php echo $schoolFs; ?>px;
        --emp-name-font: <?php echo $empFs; ?>px;
    }
    .cards-head { display:flex; align-items:center; justify-content:space-between; padding:14px 4px; flex-wrap:wrap; gap:10px; }
    .cards-head h3 { font-size:18px; font-weight:800; color:#111827; margin:0; }
    .cards-actions { display:flex; gap:8px; flex-wrap:wrap; }
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
        margin:2px 6px 2px; text-align:center; color:var(--ink); font-size:var(--emp-name-font);
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
    .cc-btn { background:<?php echo $theme; ?>; color:#fff; border:none; border-radius:6px; padding:8px 12px; font-size:13px; cursor:pointer; margin-left:6px; }
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
        <div class="cards-head">
            <h3><i class="fa fa-id-card"></i> Staff Cards</h3>
            <div class="cards-actions no-print">
                <button onclick="window.print()" class="btn btn-success"><i class="fa fa-print"></i> Print All Cards</button>
                <button type="button" class="btn btn-primary" style="color:#fff;" onclick="openCC()"><i class="fa fa-paint-brush"></i> Apply Customization</button>
                <a href="<?php echo BASE_URL; ?>students_card.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-graduation-cap"></i> Students Cards</a>
            </div>
        </div>

        <div class="sheet" id="cardSheet">
            <?php if (count($employees) === 0): ?>
                <div class="no-record" style="grid-column:1/-1;">No staff added yet. HRM module se employees add karein.</div>
            <?php endif; ?>
            <?php foreach ($employees as $emp):
                $fullName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
                $initial = strtoupper(substr($fullName !== '' ? $fullName : 'E', 0, 1));
                $photo = '';
                if (!empty($emp['photo']) && is_file(__DIR__ . '/uploads/employees/' . $emp['photo'])) {
                    $photo = BASE_URL . 'uploads/employees/' . e($emp['photo']);
                }
                $qrPayload = $emp['qr_token'];
                $qrSrc = BASE_URL . 'qr_image.php?d=' . urlencode($qrPayload);
            ?>
                <div class="id-card">
                    <div class="id-card-inner">
                        <div class="top">
                            <div class="brand">
                                <img src="<?php echo $logoSrc; ?>" alt="Logo" onerror="this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';">
                                <div><div class="school">Test Portal</div></div>
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
                        <div class="role">STAFF MEMBER</div>

                        <div class="details">
                            <div class="line"><i class="fa fa-id-badge"></i> <b>Employee ID:</b>&nbsp;<span><?php echo $emp['emp_id']; ?></span></div>
                            <div class="line"><i class="fa fa-briefcase"></i> <b>Designation:</b>&nbsp;<span><?php echo e($emp['designation'] ?? '-'); ?></span></div>
                            <div class="line"><i class="fa fa-building"></i> <b>Department:</b>&nbsp;<span><?php echo e($emp['department'] ?? '-'); ?></span></div>
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
        </div>
    </div>
</div>

<!-- Customization modal -->
<div id="ccOverlay" class="cc-overlay no-print" onclick="if(event.target===this) closeCC();">
    <div class="cc-panel">
        <h4>Card Settings</h4>
        <form method="get" action="<?php echo BASE_URL; ?>cards.php">
            <div class="cc-row"><label>Theme Color</label>
                <input type="color" name="color" value="<?php echo $theme; ?>"></div>
            <div class="cc-row"><label>School Name Font Size</label>
                <select name="school_name_font_size">
                    <?php for ($f = 8; $f <= 28; $f++): ?>
                        <option value="<?php echo $f; ?>" <?php echo $f == $schoolFs ? 'selected' : ''; ?>><?php echo $f; ?>px</option>
                    <?php endfor; ?>
                </select></div>
            <div class="cc-row"><label>Employee Name Font Size</label>
                <select name="emp_name_font_size">
                    <?php for ($f = 8; $f <= 24; $f++): ?>
                        <option value="<?php echo $f; ?>" <?php echo $f == $empFs ? 'selected' : ''; ?>><?php echo $f; ?>px</option>
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
        <h4 style="font-size:14px; margin:0 0 10px;"><i class="fa fa-camera" style="color:#FF7A1B;"></i> Change Staff Photo</h4>
        <?php if (count($employees) > 0): ?>
            <?php
                $empPhotoMap = [];
                foreach ($employees as $emp) {
                    $pf = '';
                    if (!empty($emp['photo']) && is_file(__DIR__ . '/uploads/employees/' . $emp['photo'])) {
                        $pf = BASE_URL . 'uploads/employees/' . e($emp['photo']);
                    }
                    $initial = strtoupper(substr(trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')), 0, 1)) ?: 'E';
                    $empPhotoMap[(int)$emp['emp_id']] = ['url' => $pf, 'initial' => $initial];
                }
            ?>
            <form method="post" enctype="multipart/form-data" action="<?php echo BASE_URL; ?>cards.php">
                <input type="hidden" name="action" value="update_card_photo">
                <input type="hidden" name="valid" value="<?php echo $valid; ?>">
                <input type="hidden" name="color" value="<?php echo ltrim($theme, '#'); ?>">
                <input type="hidden" name="school_name_font_size" value="<?php echo $schoolFs; ?>">
                <input type="hidden" name="emp_name_font_size" value="<?php echo $empFs; ?>">
                <div class="cc-row"><label>Employee</label>
                    <select name="card_emp_id" id="ccEmpSel" onchange="updStaffPick(this)">
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo (int)$emp['emp_id']; ?>">#<?php echo (int)$emp['emp_id']; ?> <?php echo e(trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="cc-row" style="justify-content:center;">
                    <label class="pick-box" for="card_photo_input">
                        <img id="staffPickPrev" alt="">
                        <span class="pick-empty" id="staffPickEmpty"><i class="fa fa-user"></i><br><small>Photo Pick Karne ke liye Click Karein</small></span>
                        <span class="pick-cam"><i class="fa fa-camera"></i> Change Photo</span>
                    </label>
                    <input type="file" id="card_photo_input" name="card_photo" accept="image/*" style="display:none;" required onchange="previewStaffPhoto(this)">
                </div>
                <div class="cc-actions">
                    <button type="submit" class="btn btn-success" style="color:#fff;"><i class="fa fa-upload"></i> Update Photo</button>
                </div>
            </form>
        <?php else: ?>
            <p style="color:#9CA3AF; font-size:12px; margin:0;">Koi staff member available nahi hai.</p>
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
var STAFF_PHOTOS = {
    <?php foreach ($empPhotoMap as $eid => $info): ?>
        "<?php echo $eid; ?>": { url: "<?php echo $info['url']; ?>", initial: "<?php echo $info['initial']; ?>" },
    <?php endforeach; ?>
};
function updStaffPick(sel) {
    var info = STAFF_PHOTOS[sel.value] || { url: '', initial: 'E' };
    var img = document.getElementById('staffPickPrev');
    var empty = document.getElementById('staffPickEmpty');
    if (info.url) { img.src = info.url; img.style.display = 'block'; empty.style.display = 'none'; }
    else { img.style.display = 'none'; empty.innerHTML = '<i class="fa fa-user"></i><br><small>' + info.initial + ' | No Photo</small>'; empty.style.display = 'flex'; }
}
function previewStaffPhoto(input) {
    if (input.files && input.files[0]) {
        var rd = new FileReader();
        rd.onload = function(e) {
            var img = document.getElementById('staffPickPrev');
            img.src = e.target.result; img.style.display = 'block';
            document.getElementById('staffPickEmpty').style.display = 'none';
        };
        rd.readAsDataURL(input.files[0]);
    }
}
document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('ccEmpSel');
    if (sel) updStaffPick(sel);
});
</script>

<script>
function openCC(){ var ov = document.getElementById('ccOverlay'); ov.style.display = 'block'; var p = ov.querySelector('.cc-panel'); if (p) p.scrollTop = 0; }
function closeCC(){ document.getElementById('ccOverlay').style.display = 'none'; }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>