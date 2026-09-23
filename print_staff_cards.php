<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$ids = trim((string)($_POST['staff_ids'] ?? ($_GET['staff_ids'] ?? '')));
$idList = [];
if ($ids !== '') {
    foreach (explode(',', $ids) as $i) {
        $i = (int)trim($i);
        if ($i > 0) { $idList[$i] = true; }
    }
}

$valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['valid'] ?? '') ? $_GET['valid'] : date('Y-m-d', strtotime('+1 year'));

require_once __DIR__ . '/includes/card_design.php';
$design = card_design('staff');
$theme = $design['theme']; $accent = $design['accent']; $ink = $design['name_color'];
$topText = $design['top_text']; $roleColor = $design['role_color'];
$schoolFs = (int)$design['school_font']; $empFs = (int)$design['name_font'];
$schoolName = $design['school_name'] !== '' ? $design['school_name'] : get_setting('school_name', 'LAPS School & College');
$schoolAddr = $design['school_addr'];
$schoolLogo = $design['logo'] !== '' ? $design['logo'] : get_setting('school_logo', '');
$logoSrc = $schoolLogo !== '' ? BASE_URL . $schoolLogo : BASE_URL . 'assets/img/logo.jpg';
$sigImg = get_setting('signature_image', '');
$sigSrc = $sigImg !== '' ? BASE_URL . 'assets/uploads/' . basename($sigImg) : '';

$employees = [];
if (count($idList) > 0) {
    $ph = implode(',', array_fill(0, count($idList), '?'));
    $st2 = db_prepare("SELECT e.*, qt.token AS qr_token
                       FROM employees e
                       LEFT JOIN qr_tokens qt ON qt.user_id = e.emp_id AND qt.user_type IN ('staff','employee') AND qt.is_active = 1
                       WHERE e.status=1 AND e.emp_id IN ($ph) ORDER BY e.emp_id");
    $st2->bind_param(str_repeat('i', count($idList)), ...array_keys($idList));
    $st2->execute();
    $res = $st2->get_result();
    while ($row = $res->fetch_assoc()) { $employees[] = $row; }
}

if (count($employees) === 0) { die('No staff selected.'); }

$insToken = db_prepare("INSERT INTO qr_tokens (user_id, user_type, token, created_at, expires_at, is_active) VALUES (?, 'staff', ?, NOW(), DATE_ADD(NOW(), INTERVAL 2 YEAR), 1)");
foreach ($employees as $i => $emp) {
    if (empty($emp['qr_token'])) {
        $token = 'QR-' . strtoupper(bin2hex(random_bytes(8)));
        $eid = (int)$emp['emp_id'];
        $insToken->bind_param('is', $eid, $token);
        $insToken->execute();
        $employees[$i]['qr_token'] = $token;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Staff ID Cards</title>
    <style>
        :root {
            --theme: <?php echo $theme; ?>; --accent: <?php echo $accent; ?>; --ink: <?php echo $ink; ?>;
            --top-text: <?php echo $topText; ?>; --role-color: <?php echo $roleColor; ?>;
            --card-w: 2.2in; --card-h: 3.6in; --safe-inset: 0.125in;
            --school-font: <?php echo $schoolFs; ?>px; --emp-name-font: <?php echo $empFs; ?>px;
        }
        body { margin:0; background:#e8e8e8; padding:20px; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .sheet { max-width:1100px; margin:0 auto; display:grid; grid-template-columns:repeat(3, var(--card-w)); gap:4mm 4mm; justify-content:center; }
        .id-card {
            position:relative; width:var(--card-w); min-height:var(--card-h); padding:var(--safe-inset);
            border-radius:18px; overflow:hidden; border:1px dashed #999; background:#fff;
            box-shadow:0 2px 8px rgba(0,0,0,.12); break-inside:avoid;
        }
        .id-card::before { content:""; position:absolute; inset:var(--safe-inset); border:1px dotted rgba(0,0,0,0.12); border-radius:8px; pointer-events:none; z-index:6; }
        .id-card-inner { position:relative; z-index:1; width:100%; min-height:100%; display:flex; flex-direction:column; }
        .top { background:var(--theme); color:var(--top-text); padding:8px 8px 22px; position:relative; flex-shrink:0; }
        .top:after { content:""; position:absolute; left:-12%; right:-12%; bottom:0; height:0; background:#fff; border-top:4px solid var(--accent); border-radius:0 0 50% 50%; }
        .brand { display:flex; align-items:center; gap:6px; position:relative; z-index:2; }
        .brand img { width:32px; height:32px; object-fit:contain; background:#fff; border-radius:50%; padding:2px; }
        .school { font-size:var(--school-font); line-height:1.05; font-weight:800; letter-spacing:.2px; text-transform:uppercase; word-break:break-word; }
        .school-sub { font-size:6.5px; font-weight:600; color:var(--top-text); opacity:.85; line-height:1.25; margin-top:2px; word-break:break-word; }
        .photo-wrap { margin:16px auto 6px; width:70px; height:70px; border-radius:50%; border:4px solid var(--accent); background:#f3f4f6; padding:2px; flex-shrink:0; overflow:hidden; }
        .photo-wrap img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
        .photo-placeholder { width:100%; height:100%; border-radius:50%; background:#e5e7eb; color:#6b7280; display:flex; align-items:center; justify-content:center; text-align:center; font-size:9px; font-weight:700; line-height:1.2; padding:4px; }
        .name { margin:2px 6px 2px; text-align:center; color:var(--ink); font-size:var(--emp-name-font); font-weight:900; line-height:1.15; text-transform:uppercase; flex-shrink:0; word-break:break-word; }
        .role { width:78%; max-width:100%; margin:0 auto; border-radius:999px; background:var(--accent); color:var(--role-color); text-align:center; font-size:8px; font-weight:800; letter-spacing:0.8px; padding:4px 6px; flex-shrink:0; }
        .details { margin:6px 4px 4px; padding:3px 8px 6px; flex-shrink:0; display:flex; flex-direction:column; justify-content:flex-start; }
        .line { display:flex; align-items:flex-start; gap:5px; font-size:8.5px; margin:3px 0; line-height:1.25; }
        .line i { width:11px; flex-shrink:0; font-size:10px; color:var(--theme); font-style:normal; }
        .line b { color:var(--ink); }
        .foot { margin:0 6px 6px; display:grid; grid-template-columns:1fr auto 1fr; align-items:end; gap:4px 6px; font-size:8px; flex-shrink:0; }
        .foot-left { justify-self:start; text-align:left; min-width:0; margin-bottom:2px; }
        .foot-date-line { font-size:7px; line-height:1.4; white-space:nowrap; }
        .foot-lbl { font-weight:700; color:var(--ink); }
        .foot-qr { justify-self:center; align-self:end; }
        .foot-right { justify-self:end; text-align:center; min-width:0; }
        .foot-principal-label { font-weight:700; color:var(--ink); line-height:1.2; padding-bottom:2px; }
        .sign-below { text-align:center; margin:2px 0 4px; flex-shrink:0; }
        .sign-below img { height:20px; max-width:64px; object-fit:contain; display:block; margin:0 auto 1px; }
        .sign-below .sig-cap { font-size:8px; color:#333; line-height:1; }
        .bar { height:6px; margin-top:auto; flex-shrink:0; border-radius:0 0 14px 14px; background:linear-gradient(to right,var(--accent) 0%, var(--theme) 25%, var(--theme) 100%); }
        .qr-wrap { margin:0; width:70px; height:70px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .qr-wrap img { width:64px !important; height:64px !important; }
        .no-record { text-align:center; padding:40px; color:#6B7280; }
        @media (max-width:900px){ .sheet { grid-template-columns:repeat(2, var(--card-w)); } }
        @media (max-width:520px){ .sheet { grid-template-columns:1fr; justify-items:center; } }
        @media print {
            @page { size:A4 portrait; margin:5mm; }
            body { background:#fff; padding:0; }
            .sheet { padding:0; }
            .id-card { box-shadow:none; border:1px solid #bbb; }
            .id-card::before { display:none; }
            #printToolbar { display:none !important; }
        }
        #printToolbar { position:fixed; top:12px; right:12px; z-index:99; }
        #printToolbar button { background:#ff7800; color:#fff; border:none; border-radius:6px; padding:10px 18px; font-size:14px; font-weight:700; cursor:pointer; }
    </style>
</head>
<body>
    <div id="printToolbar">
        <button onclick="window.print()"><i class="fa fa-print"></i> Print Cards</button>
    </div>
    <div class="sheet" id="cardSheet">
        <?php foreach ($employees as $emp):
            $fullName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
            $initial = strtoupper(substr($fullName !== '' ? $fullName : 'E', 0, 1));
            $photo = '';
            if (!empty($emp['photo']) && is_file(__DIR__ . '/uploads/employees/' . $emp['photo'])) {
                $photo = BASE_URL . 'uploads/employees/' . e($emp['photo']);
            }
            $qrSrc = BASE_URL . 'qr_image.php?d=' . urlencode($emp['qr_token']);
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
                                        <div class="school-sub"><?php echo e(strlen($schoolAddr) > 34 ? substr($schoolAddr, 0, 34) . '…' : $schoolAddr); ?></div>
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
                                <img src="<?php echo $qrSrc; ?>" alt="QR" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/img/qr-default.png';">
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="foot-right">
                            <div class="foot-principal-label">
                                <?php if ($design['show_sign'] === 'YES'): ?>
                                    <?php if ($sigSrc !== ''): ?>
                                        <div class="sign-below"><img src="<?php echo $sigSrc; ?>" onerror="this.style.display='none';"><div class="sig-cap"><?php echo e($design['principal_text']); ?></div></div>
                                    <?php else: ?>
                                        <div class="sign-below"><div style="border-top:1px solid #333; width:56px; margin:0 auto 1px;"></div><div class="sig-cap"><?php echo e($design['principal_text']); ?></div></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="bar"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>