<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Staff Cards';

require_once __DIR__ . '/includes/card_design.php';

$design   = card_design('staff');
$theme    = $design['theme'];
$accent   = $design['accent'];
$ink      = $design['name_color'];
$topText  = $design['top_text'];
$roleColor = $design['role_color'];
$schoolFs = (int) $design['school_font'];
$empFs    = (int) $design['name_font'];
$schoolName = $design['school_name'] !== '' ? $design['school_name'] : get_setting('school_name', 'LAPS School & College');
$schoolAddr = $design['school_addr'];
$schoolLogo = $design['logo'] !== '' ? $design['logo'] : get_setting('school_logo', '');
$logoSrc  = $schoolLogo !== '' ? BASE_URL . $schoolLogo : BASE_URL . 'assets/img/logo.jpg';
$sigImg   = get_setting('signature_image', '');
$sigSrc   = $sigImg !== '' ? BASE_URL . 'assets/uploads/' . basename($sigImg) : '';

$valid    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['valid'] ?? '') ? $_GET['valid'] : date('Y-m-d', strtotime('+1 year'));

$selEmp  = (int) ($_GET['emp_id'] ?? 0);
$selDept = isset($_GET['department']) && trim($_GET['department']) !== '' ? trim($_GET['department']) : '';
$viewMode = isset($_GET['view']) && (int)($_GET['view'] ?? 0) === 1;

$allEmployees = [];
$res = db_query("SELECT e.*, qt.token AS qr_token
                 FROM employees e
                 LEFT JOIN qr_tokens qt ON qt.user_id = e.emp_id AND qt.user_type IN ('staff','employee') AND qt.is_active = 1
                 WHERE e.status = 1 ORDER BY e.emp_id");
while ($row = $res->fetch_assoc()) { $allEmployees[] = $row; }

// Ensure every staff member has a unique random QR token
$insToken = db_prepare("INSERT INTO qr_tokens (user_id, user_type, token, created_at, expires_at, is_active) VALUES (?, 'staff', ?, NOW(), DATE_ADD(NOW(), INTERVAL 2 YEAR), 1)");
foreach ($allEmployees as $i => $emp) {
    if (empty($emp['qr_token'])) {
        $token = 'QR-' . strtoupper(bin2hex(random_bytes(8)));
        $eid = (int) $emp['emp_id'];
        $insToken->bind_param('is', $eid, $token);
        $insToken->execute();
        $allEmployees[$i]['qr_token'] = $token;
    }
}

$employees = $allEmployees;
if ($selEmp > 0) {
    $employees = array_values(array_filter($allEmployees, function ($e) use ($selEmp) {
        return (int) $e['emp_id'] === $selEmp;
    }));
} elseif ($selDept !== '') {
    $employees = array_values(array_filter($allEmployees, function ($e) use ($selDept) {
        return strcasecmp(trim((string)($e['department'] ?? '')), $selDept) === 0;
    }));
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
    $qs = ['emp_id' => (int)($_POST['emp_id'] ?? 0), 'view' => (int)($_POST['view'] ?? 0)];
    if (isset($_POST['valid']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_POST['valid'])) { $qs['valid'] = (string)$_POST['valid']; }
    header('Location: ' . BASE_URL . 'cards.php?' . http_build_query($qs));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_card_design') {
    card_design_save('staff', $_POST, $_FILES['logo_file'] ?? null);
    $qs = ['emp_id' => (int)($_POST['emp_id'] ?? 0), 'view' => (int)($_POST['view'] ?? 0)];
    if (isset($_POST['valid']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_POST['valid'])) { $qs['valid'] = (string)$_POST['valid']; }
    header('Location: ' . BASE_URL . 'cards.php?' . http_build_query($qs));
    exit;
}

include __DIR__ . '/includes/header.php';
?>
<style>
    :root {
        --theme: <?php echo $theme; ?>;
        --accent: <?php echo $accent; ?>;
        --ink: <?php echo $ink; ?>;
        --top-text: <?php echo $topText; ?>;
        --role-color: <?php echo $roleColor; ?>;
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
        background:var(--theme); color:var(--top-text); padding:8px 8px 22px; position:relative; flex-shrink:0;
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
    .school-sub {
        font-size:6.5px; font-weight:600; color:var(--top-text); opacity:.85;
        line-height:1.25; margin-top:2px; word-break:break-word;
    }
    .photo-wrap {
        margin:16px auto 6px; width:70px; height:70px; border-radius:50%; border:4px solid var(--accent);
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
        color:var(--role-color); text-align:center; font-size:8px; font-weight:800;
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
    .cc-panel details { margin-bottom:8px; }
    .cc-panel summary { cursor:pointer; font-weight:700; font-size:13px; padding:6px 10px; background:#f3f4f6; border-radius:6px; margin-bottom:6px; }
    .cc-grid2 { display:flex; gap:10px; }
    .cc-grid2 > div { flex:1; min-width:0; }
    .cc-inline { display:flex; gap:10px; align-items:center; }
    .cc-inline > div { flex:1; min-width:0; }

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
        #cardSheet, #cardSheet * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
        .no-print { display:none !important; }
        .id-card { box-shadow:none; border:1px solid #bbb; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .id-card::before { display:none; }
    }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if (!$viewMode): ?>
        <div class="cards-head">
            <h3><i class="fa fa-id-card"></i> Staff Cards</h3>
            <div class="cards-actions no-print">
                <button onclick="window.print()" class="btn btn-success"><i class="fa fa-print"></i> Print All Cards</button>
                <button type="button" class="btn btn-primary" style="color:#fff;" onclick="openCC()"><i class="fa fa-paint-brush"></i> Apply Customization</button>
                <a href="<?php echo BASE_URL; ?>students_card.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-graduation-cap"></i> Students Cards</a>
            </div>
        </div>

<?php
            $depts = [];
            foreach ($allEmployees as $demp) {
                $dd = trim((string)($demp['department'] ?? ''));
                if ($dd !== '') {
                    $depts[$dd] = true;
                }
            }
            ksort($depts);
        ?>
        <form method="get" action="cards.php" class="search-bar-student no-print">
            <div class="form-group col-md-4" style="margin-bottom:0;">
                <label>Select Staff</label>
                <select name="emp_id" class="form-control" onchange="if(this.value){location.href='<?php echo BASE_URL; ?>cards.php?emp_id='+encodeURIComponent(this.value)+'&view=1';}">
                    <option value="0">Select Staff Name</option>
                    <?php foreach ($allEmployees as $se): ?>
                        <option value="<?php echo (int)$se['emp_id']; ?>" <?php echo $selEmp === (int)$se['emp_id'] ? 'selected' : ''; ?>>
                            <?php echo (int)$se['emp_id']; ?>. <?php echo e(trim(($se['first_name'] ?? '') . ' ' . ($se['last_name'] ?? ''))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (count($depts) > 0): ?>
                <div class="form-group col-md-3" style="margin-bottom:0;">
                    <label>Department</label>
                    <select name="department" class="form-control" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php foreach (array_keys($depts) as $dep): ?>
                            <option value="<?php echo e($dep); ?>" <?php echo $selDept === $dep ? 'selected' : ''; ?>><?php echo e($dep); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </form>
        <?php else: ?>
        <div class="cards-head no-print">
            <h3><i class="fa fa-id-card"></i> Staff Card</h3>
            <div class="cards-actions no-print">
                <a href="<?php echo BASE_URL; ?>cards.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                <button type="button" class="btn btn-primary" style="color:#fff;" onclick="openCC()"><i class="fa fa-paint-brush"></i> Customize Card</button>
                <button onclick="window.print()" class="btn btn-success"><i class="fa fa-print"></i> Print Card</button>
            </div>
        </div>
        <?php endif; ?>

        <div id="cardSheet">
            <?php if ($selEmp <= 0): ?>
            <?php elseif (count($employees) === 0): ?>
            <?php else: ?>
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
                                <?php if ($design['show_school'] === 'YES' || $design['show_addr'] === 'YES'): ?>
                                    <div>
                                        <?php if ($design['show_school'] === 'YES'): ?>
                                            <div class="school"><?php echo e($schoolName); ?></div>
                                        <?php endif; ?>
                                        <?php if ($design['show_addr'] === 'YES' && trim($schoolAddr) !== ''): ?>
                                            <div class="school-sub"><?php echo e((strlen($schoolAddr) > 34 ? substr($schoolAddr, 0, 34) . '…' : $schoolAddr)); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
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
                        <div class="role"><?php echo e($design['role_text']); ?></div>

                        <div class="details">
                            <?php if ($design['show_emp'] === 'YES'): ?>
                                <div class="line"><i class="fa fa-id-badge"></i> <b><?php echo e($design['id_label']); ?>:</b>&nbsp;<span><?php echo $emp['emp_id']; ?></span></div>
                            <?php endif; ?>
                            <?php if ($design['show_desig'] === 'YES'): ?>
                                <div class="line"><i class="fa fa-briefcase"></i> <b><?php echo e($design['designation_label']); ?>:</b>&nbsp;<span><?php echo e($emp['designation'] ?? '-'); ?></span></div>
                            <?php endif; ?>
                            <?php if ($design['show_dept'] === 'YES'): ?>
                                <div class="line"><i class="fa fa-building"></i> <b><?php echo e($design['department_label']); ?>:</b>&nbsp;<span><?php echo e($emp['department'] ?? '-'); ?></span></div>
                            <?php endif; ?>
                        </div>

                        <div class="foot">
                            <div class="foot-left">
                                <div class="foot-date-line"><?php echo date('d-M-Y', strtotime($valid)); ?></div>
                                <div class="foot-date-line"><span class="foot-lbl"><?php echo e($design['valid_label']); ?></span></div>
                            </div>
                            <?php if ($design['show_qr'] === 'YES'): ?>
                            <div class="foot-qr">
                                <div class="qr-wrap">
                                    <img src="<?php echo $qrSrc; ?>" alt="QR Code" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/img/qr-default.png';">
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="foot-right">
                                <div class="foot-principal-label">
                                    <?php if ($design['show_sign'] === 'YES'): ?>
                                        <?php if ($sigSrc !== ''): ?>
                                            <div class="sign-below">
                                                <img src="<?php echo $sigSrc; ?>" onerror="this.style.display='none';">
                                                <div class="sig-cap"><?php echo e($design['principal_text']); ?></div>
                                            </div>
                                        <?php else: ?>
                                            <div class="sign-below">
                                                <div style="border-top:1px solid #333; width:56px; margin:0 auto 1px;"></div>
                                                <div class="sig-cap"><?php echo e($design['principal_text']); ?></div>
                                            </div>
                                        <?php endif; ?>
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
        <h4><i class="fa fa-paint-brush"></i> Card Designer <small style="color:#9CA3AF;">Staff Card</small></h4>
        <p style="color:#6B7280; font-size:12px; margin:0 0 12px; line-height:1.4;">Yahan design save karo — har tab (logos, colors, texts, QR) ka hissa alag section me hai. Save hone par aapki tarah card har baar usi design par banega.</p>

        <form method="post" enctype="multipart/form-data" action="<?php echo BASE_URL; ?>cards.php">
            <input type="hidden" name="action" value="save_card_design">
            <input type="hidden" name="emp_id" value="<?php echo $selEmp; ?>">
            <input type="hidden" name="view" value="<?php echo $viewMode ? 1 : 0; ?>">
            <input type="hidden" name="valid" value="<?php echo $valid; ?>">

            <details open>
                <summary>Brand: Logo, School Name & Address</summary>
                <div class="cc-row"><label>Card Logo (change karein)</label>
                    <div class="cc-inline">
                        <img src="<?php echo $logoSrc; ?>" alt="" onerror="this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';" style="width:44px;height:44px;object-fit:contain;border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:2px;">
                        <input type="file" name="logo_file" accept="image/*" style="height:auto;padding:4px;">
                    </div></div>
                <div class="cc-row"><label>School Name</label>
                    <input type="text" name="school_name" value="<?php echo e($schoolName); ?>"></div>
                <div class="cc-row"><label>Address Line</label>
                    <input type="text" name="school_addr" value="<?php echo e($schoolAddr); ?>"></div>
                <div class="cc-grid2">
                    <div class="cc-row"><label>Show School Name</label>
                        <select name="show_school"><option value="YES" <?php echo $design['show_school'] === 'YES' ? 'selected' : ''; ?>>YES</option><option value="NO" <?php echo $design['show_school'] === 'NO' ? 'selected' : ''; ?>>NO</option></select></div>
                    <div class="cc-row"><label>Show Address</label>
                        <select name="show_addr"><option value="YES" <?php echo $design['show_addr'] === 'YES' ? 'selected' : ''; ?>>YES</option><option value="NO" <?php echo $design['show_addr'] === 'NO' ? 'selected' : ''; ?>>NO</option></select></div>
                </div>
            </details>

            <details>
                <summary>Colors</summary>
                <div class="cc-grid2">
                    <div class="cc-row"><label>Header Color</label>
                        <input type="color" name="theme" value="<?php echo $design['theme']; ?>"></div>
                    <div class="cc-row"><label>Accent Color</label>
                        <input type="color" name="accent" value="<?php echo $design['accent']; ?>"></div>
                </div>
                <div class="cc-grid2">
                    <div class="cc-row"><label>Header Text Color</label>
                        <input type="color" name="top_text" value="<?php echo $design['top_text']; ?>"></div>
                    <div class="cc-row"><label>Name & Details Color</label>
                        <input type="color" name="name_color" value="<?php echo $design['name_color']; ?>"></div>
                </div>
                <div class="cc-row"><label>Role Badge Text Color</label>
                    <input type="color" name="role_color" value="<?php echo $design['role_color']; ?>"></div>
            </details>

            <details>
                <summary>Font Sizes</summary>
                <div class="cc-grid2">
                    <div class="cc-row"><label>School Name Font Size</label>
                        <select name="school_font">
                            <?php for ($f = 8; $f <= 28; $f++): ?>
                                <option value="<?php echo $f; ?>" <?php echo $f == $schoolFs ? 'selected' : ''; ?>><?php echo $f; ?>px</option>
                            <?php endfor; ?>
                        </select></div>
                    <div class="cc-row"><label>Employee Name Font Size</label>
                        <select name="name_font">
                            <?php for ($f = 8; $f <= 24; $f++): ?>
                                <option value="<?php echo $f; ?>" <?php echo $f == $empFs ? 'selected' : ''; ?>><?php echo $f; ?>px</option>
                            <?php endfor; ?>
                        </select></div>
                </div>
            </details>

            <details>
                <summary>Labels & Text</summary>
                <div class="cc-row"><label>Role Badge Text</label>
                    <input type="text" name="role_text" value="<?php echo e($design['role_text']); ?>"></div>
                <div class="cc-grid2">
                    <div class="cc-row"><label>Employee ID Label</label>
                        <input type="text" name="id_label" value="<?php echo e($design['id_label']); ?>"></div>
                    <div class="cc-row"><label>Show Employee ID</label>
                        <select name="show_emp"><option value="YES" <?php echo $design['show_emp'] === 'YES' ? 'selected' : ''; ?>>YES</option><option value="NO" <?php echo $design['show_emp'] === 'NO' ? 'selected' : ''; ?>>NO</option></select></div>
                </div>
                <div class="cc-grid2">
                    <div class="cc-row"><label>Designation Label</label>
                        <input type="text" name="designation_label" value="<?php echo e($design['designation_label']); ?>"></div>
                    <div class="cc-row"><label>Show Designation</label>
                        <select name="show_desig"><option value="YES" <?php echo $design['show_desig'] === 'YES' ? 'selected' : ''; ?>>YES</option><option value="NO" <?php echo $design['show_desig'] === 'NO' ? 'selected' : ''; ?>>NO</option></select></div>
                </div>
                <div class="cc-grid2">
                    <div class="cc-row"><label>Department Label</label>
                        <input type="text" name="department_label" value="<?php echo e($design['department_label']); ?>"></div>
                    <div class="cc-row"><label>Show Department</label>
                        <select name="show_dept"><option value="YES" <?php echo $design['show_dept'] === 'YES' ? 'selected' : ''; ?>>YES</option><option value="NO" <?php echo $design['show_dept'] === 'NO' ? 'selected' : ''; ?>>NO</option></select></div>
                </div>
            </details>

            <details>
                <summary>Footer, QR & Signature</summary>
                <div class="cc-grid2">
                    <div class="cc-row"><label>Valid Till Label</label>
                        <input type="text" name="valid_label" value="<?php echo e($design['valid_label']); ?>"></div>
                    <div class="cc-row"><label>Show QR Code</label>
                        <select name="show_qr"><option value="YES" <?php echo $design['show_qr'] === 'YES' ? 'selected' : ''; ?>>YES</option><option value="NO" <?php echo $design['show_qr'] === 'NO' ? 'selected' : ''; ?>>NO</option></select></div>
                </div>
                <div class="cc-grid2">
                    <div class="cc-row"><label>Principal Text</label>
                        <input type="text" name="principal_text" value="<?php echo e($design['principal_text']); ?>"></div>
                    <div class="cc-row"><label>Show Signature</label>
                        <select name="show_sign"><option value="YES" <?php echo $design['show_sign'] === 'YES' ? 'selected' : ''; ?>>YES</option><option value="NO" <?php echo $design['show_sign'] === 'NO' ? 'selected' : ''; ?>>NO</option></select></div>
                </div>
                <div class="cc-row"><label>Validity Date</label>
                    <input type="date" name="valid" value="<?php echo $valid; ?>"></div>
            </details>

            <div class="cc-actions">
                <button type="button" class="btn btn-default" onclick="closeCC();">Cancel</button>
                <button type="submit" class="btn btn-primary" style="color:#fff;"><i class="fa fa-check"></i> Apply Design</button>
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
                <input type="hidden" name="emp_id" value="<?php echo $selEmp; ?>">
                <input type="hidden" name="view" value="<?php echo $viewMode ? 1 : 0; ?>">
                <input type="hidden" name="valid" value="<?php echo $valid; ?>">
                <div class="cc-row"><label>Employee</label>
                    <select name="card_emp_id" id="ccEmpSel" onchange="updStaffPick(this)">
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo (int)$emp['emp_id']; ?>">#<?php echo (int)$emp['emp_id']; ?> <?php echo e(trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="cc-row" style="justify-content:center;">
                    <label class="pick-box" for="card_photo_input">
                        <img id="staffPickPrev" alt="">
                        <span class="pick-empty" id="staffPickEmpty"><i class="fa fa-user"></i><br><small>Click to Pick a Photo</small></span>
                        <span class="pick-cam"><i class="fa fa-camera"></i> Change Photo</span>
                    </label>
                    <input type="file" id="card_photo_input" name="card_photo" accept="image/*" style="display:none;" required onchange="previewStaffPhoto(this)">
                </div>
                <div class="cc-actions">
                    <button type="submit" class="btn btn-success" style="color:#fff;"><i class="fa fa-upload"></i> Update Photo</button>
                </div>
            </form>
        <?php else: ?>
            <p style="color:#9CA3AF; font-size:12px; margin:0;">No staff member available.</p>
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

<script>document.body.classList.add('sidebar-hidden');</script>

<?php include __DIR__ . '/includes/footer.php'; ?>