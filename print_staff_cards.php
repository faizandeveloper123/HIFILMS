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

$validity = trim((string)($_POST['validity_date'] ?? ($_GET['validity_date'] ?? ($_GET['valid'] ?? ''))));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $validity)) {
    $validity = date('Y-m-d', strtotime('+1 year'));
}

require_once __DIR__ . '/includes/card_design.php';
$design = card_design('staff');

$color = trim((string)($_POST['color'] ?? ($_GET['color'] ?? '')));
if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $color)) { $color = $design['theme']; }
$color = '#' . strtolower(ltrim($color, '#'));

$schoolFs = (int)($_POST['school_name_font_size'] ?? ($_GET['school_name_font_size'] ?? $design['school_font']));
if ($schoolFs < 8) { $schoolFs = 8; }
if ($schoolFs > 28) { $schoolFs = 28; }

$empFs = (int)($_POST['emp_name_font_size'] ?? ($_GET['emp_name_font_size'] ?? $design['name_font']));
if ($empFs < 8) { $empFs = 8; }
if ($empFs > 24) { $empFs = 24; }

$accent = '#f2d500';
$ink = '#083a2b';
$si = school_info((int)($_POST['campus_id'] ?? ($_GET['campus_id'] ?? 0)));
$schoolName = $si['name'] !== '' ? $si['name'] : $design['school_name'];
$schoolLogo = $si['logo'] !== '' ? $si['logo'] : $design['logo'];
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
$idsJoined = implode(',', array_map('intval', array_column($employees, 'emp_id')));
$postedDesignation = trim((string)($_POST['designation'] ?? ($_GET['designation'] ?? 'All')));
$postedDepartment = trim((string)($_POST['department'] ?? ($_GET['department'] ?? 'All')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/img/favicon.png" sizes="32x32">
    <title>Print Staff Cards</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
/*
  Portrait card (tall) — paper print + laminate + manual cut:
  Trim (cutting): 2.2" wide × 3.6" tall  (matches student portrait card; extra size vs
  the 2.125"×3.375" ISO/CR80 plastic-card standard gives bleed/margin for manual cutting)
  Safe zone: 1.95" × 3.35" → 0.125" inset on each side
*/
:root{
  --theme: <?php echo $color; ?>;
  --accent:#f2d500;
  --ink:#083a2b;
  --card-w: 2.2in;
  --card-h: 3.6in;
  --safe-inset: 0.125in;
  --school-font: <?php echo $schoolFs; ?>px;
  --emp-name-font: <?php echo $empFs; ?>px;
}
*{box-sizing:border-box}
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#e8e8e8;color:#111}
.sheet{
  width:max-content;
  max-width:100%;
  margin:0 auto;
  padding:8mm;
  display:grid;
  grid-template-columns:repeat(3, var(--card-w));
  gap:4mm 4mm;
  justify-content:center;
}
.id-card{
  position:relative;
  width:var(--card-w);
  min-height:var(--card-h);
  padding:var(--safe-inset);
  border-radius:18px;
  overflow:hidden;
  border:1px dashed #999;
  background:#fff;
  box-shadow:0 2px 8px rgba(0,0,0,.12);
}
/* Dotted guide = full bleed / trim; inner content = safe zone */
.id-card::before{
  content:"";
  position:absolute;
  inset:var(--safe-inset);
  border:1px dotted rgba(0,0,0,0.12);
  border-radius:8px;
  pointer-events:none;
  z-index:6;
}
.id-card-inner{
  position:relative;
  z-index:1;
  width:100%;
  min-height:100%;
  display:flex;
  flex-direction:column;
}
.top{
  background:var(--theme);
  color:#fff;
  padding:8px 8px 22px;
  position:relative;
  flex-shrink:0;
}
.top:after{
  content:"";
  position:absolute;
  left:-12%;
  right:-12%;
  bottom:0px;
  height:0px;
  background:#fff;
  border-top:4px solid var(--accent);
  border-radius:0 0 50% 50%;
}
.brand{
  display:flex;
  align-items:center;
  gap:6px;
  position:relative;
  z-index:2;
}
.brand img{width:32px;height:32px;object-fit:contain;background:#fff;border-radius:50%;padding:2px}
.school{
  font-size:var(--school-font);
  line-height:1.05;
  font-weight:800;
  letter-spacing:.2px;
  text-transform:uppercase;
  word-break:break-word;
}
.sub{
  margin:6px auto 0;
  font-size:7px;
  letter-spacing:2px;
  text-transform:uppercase;
  color:var(--theme);
  font-weight:800;
  text-align:center;
  flex-shrink:0;
}
.action-btns{
  position:fixed;
  top:10px;
  right:10px;
  z-index:9999;
  display:flex;
  gap:8px;
}
.customize-btn{
  background:#0b47be;
  color:#fff;
  border:none;
  border-radius:6px;
  padding:8px 12px;
  font-size:13px;
  cursor:pointer;
}
.customize-modal{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.45);
  display:none;
  z-index:10000;
}
.customize-panel{
  width:440px;
  max-width:94%;
  margin:70px auto;
  background:#fff;
  border-radius:10px;
  padding:14px;
  box-shadow:0 10px 28px rgba(0,0,0,.25);
}
.customize-panel h4{margin:0 0 10px;font-size:16px}
.customize-row{margin-bottom:10px}
.customize-row label{display:block;font-size:12px;font-weight:700;margin-bottom:4px}
.customize-row input,.customize-row select{width:100%;height:36px;padding:6px;border:1px solid #ccc;border-radius:6px}
.customize-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:10px}
.btn-light{background:#f5f5f5;border:1px solid #ddd;padding:7px 12px;border-radius:6px;cursor:pointer}
.btn-primary{background:#0b47be;color:#fff;border:1px solid #093a98;padding:7px 12px;border-radius:6px;cursor:pointer}
.photo-wrap{
  margin:6px auto 4px;
  width:70px;height:70px;
  border-radius:50%;
  border:4px solid var(--accent);
  background:#f3f4f6;
  padding:2px;
  flex-shrink:0;
  overflow:hidden;
}
.photo-wrap img{width:100%;height:100%;border-radius:50%;object-fit:cover}
.photo-placeholder{
  width:100%;
  height:100%;
  border-radius:50%;
  background:#e5e7eb;
  color:#6b7280;
  display:flex;
  align-items:center;
  justify-content:center;
  text-align:center;
  font-size:9px;
  font-weight:700;
  line-height:1.2;
  padding:4px;
}
.name{
  margin:2px 6px 2px;
  text-align:center;
  color:var(--ink);
  font-size:var(--emp-name-font);
  font-weight:900;
  line-height:1.15;
  text-transform:uppercase;
  min-height:0;
  flex-shrink:0;
}
.role{
  width:78%;
  max-width:100%;
  margin:0 auto;
  border-radius:999px;
  background:var(--accent);
  color:var(--ink);
  text-align:center;
  font-size:8px;
  font-weight:800;
  letter-spacing:0.8px;
  padding:4px 6px;
  flex-shrink:0;
}
.details{
  margin:6px 4px 4px;
  padding:3px 8px 6px;
  flex-shrink:0;
  display:flex;
  flex-direction:column;
  justify-content:flex-start;
}
.line{display:flex;align-items:flex-start;gap:5px;font-size:8.5px;margin:3px 0;line-height:1.25}
.line i{width:11px;flex-shrink:0;font-size:10px;color:var(--theme)}
.line b{color:var(--ink)}
.foot{
  margin:0px 6px 6px;
  display:grid;
  grid-template-columns:1fr auto 1fr;
  align-items:end;
  gap:4px 6px;
  font-size:8px;
  flex-shrink:0;
}
.foot-left{justify-self:start;text-align:left;min-width:0; margin-bottom: 2px;}
.foot-date-line{font-size:7px;line-height:1.4;white-space:nowrap}
.foot-lbl{font-weight:700;color:var(--ink)}
.foot-qr{justify-self:center;align-self:end}
.foot-right{justify-self:end;text-align:center;min-width:0}
.foot-principal-label{font-weight:700;color:var(--ink);line-height:1.2;padding-bottom:2px}
.sign-below{
  text-align:center;
  margin:2px 0 4px;
  flex-shrink:0;
}
.sign-below img{
  height:20px;
  max-width:64px;
  object-fit:contain;
  display:block;
  margin:0 auto 1px;
}
.sign-below .sig-cap{font-size:8px;color:#333;line-height:1}
.bar{
  height:6px;
  margin-top:auto;
  flex-shrink:0;
  border-radius:0 0 12px 12px;
  background:linear-gradient(to right,var(--accent) 0%, var(--theme) 25%, var(--theme) 100%);
}
.qr-wrap{
  margin:0;
  width:44px;height:70px;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.qr-wrap img,.qr-wrap canvas{
  width:80px !important;
  height:80px !important;
}
.no-record{
  width:210mm;
  margin:12mm auto;
  text-align:center;
  color:#444;
  font-size:16px;
}
@media (max-width:900px){
  .sheet{grid-template-columns:repeat(2, var(--card-w));}
}
@media (max-width:520px){
  .sheet{
    grid-template-columns:1fr;
    justify-items:center;
  }
}
@media print{
  @page{size:A4 portrait;margin:5mm}
  body{background:#fff}
  .sheet{width:100%;padding:2mm;margin:0;gap:3mm 3mm;grid-template-columns:repeat(3, var(--card-w));justify-content:center}
  .id-card{
    break-inside:avoid;
    box-shadow:none;
    border:1px solid #bbb;
    -webkit-print-color-adjust:exact;
    print-color-adjust:exact;
  }
  .id-card::before{display:none}
  .action-btns,.customize-modal{display:none !important}
}
    </style>
</head>
<body>
<div class="action-btns">
  <button type="button" class="customize-btn" onclick="openCustomizeModal()">Apply Customization</button>
  <button type="button" class="customize-btn" style="background:#374151;" onclick="openCardDimensions()">Card Dimensions</button>
</div>
<div id="customizeModal" class="customize-modal" onclick="if(event.target===this) closeCustomizeModal();">
  <div class="customize-panel">
    <h4>Card Settings</h4>
    <form method="post" action="print_staff_cards.php">
      <div class="customize-row">
        <label>Theme Color</label>
        <input type="color" name="color" value="<?php echo $color; ?>">
      </div>
      <div class="customize-row">
        <label>School Name Font Size</label>
        <select name="school_name_font_size">
            <?php for ($f = 8; $f <= 28; $f++): ?>
                <option value="<?php echo $f; ?>" <?php echo $f === $schoolFs ? 'selected' : ''; ?>><?php echo $f; ?>px</option>
            <?php endfor; ?>
        </select>
      </div>
      <div class="customize-row">
        <label>Employee Name Font Size</label>
        <select name="emp_name_font_size">
            <?php for ($f = 8; $f <= 24; $f++): ?>
                <option value="<?php echo $f; ?>" <?php echo $f === $empFs ? 'selected' : ''; ?>><?php echo $f; ?>px</option>
            <?php endfor; ?>
        </select>
      </div>
      <div class="customize-row">
        <label>Validity Date</label>
        <input type="date" name="validity_date" value="<?php echo $validity; ?>">
      </div>
      <input type="hidden" name="staff_ids" value="<?php echo $idsJoined; ?>">
      <input type="hidden" name="designation" value="<?php echo e($postedDesignation); ?>">
      <input type="hidden" name="department" value="<?php echo e($postedDepartment); ?>">
      <div class="customize-actions">
        <button type="button" class="btn-light" onclick="closeCustomizeModal()">Cancel</button>
        <button type="submit" class="btn-primary">Apply</button>
      </div>
    </form>
  </div>
</div>
<div id="dimModal" class="customize-modal" onclick="if(event.target===this) closeCardDimensions();">
  <div class="customize-panel">
    <h4>Card Dimensions Guide</h4>
    <div style="font-size:13px;line-height:1.6;">
      <p style="margin:0 0 10px;">These are the print-safe dimensions for the staff portrait ID card. Cards are printed on paper and manually cut/laminated, not machine-cut plastic — so the trim size is slightly larger than the standard ISO/CR80 card for bleed margin.</p>
      <ul style="padding-left:18px;margin:0 0 12px;">
        <li><strong>Full size (cutting size):</strong> 2.2" × 3.6"</li>
        <li><strong>Safe Zone (content area):</strong> 1.95" × 3.35" (0.125" inset on all sides)</li>
        <li><strong>Cards per A4 sheet:</strong> 3 per row (auto-wraps by page width)</li>
        <li><strong>Reference standard:</strong> ISO/IEC 7810 ID-1 (CR80) portrait = 2.125" × 3.375"</li>
      </ul>
      <div style="background:#f3f4f6;border:1px solid #d1d5db;border-radius:6px;padding:10px;color:#111827;">
        <strong>Printing Tip:</strong> Keep photo, name, and QR code inside the safe zone to avoid cutting at edges.
      </div>
    </div>
    <div class="customize-actions">
      <button type="button" class="btn-light" onclick="closeCardDimensions()">Close</button>
    </div>
  </div>
</div>
  <div class="sheet">
    <?php foreach ($employees as $emp):
        $fullName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
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
                        <img src="<?php echo $logoSrc; ?>" alt="Logo" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';">
                        <div>
                            <div class="school"><?php echo e($schoolName); ?></div>
                        </div>
                    </div>
                </div>

                <div class="photo-wrap">
                    <?php if ($photo !== ''): ?>
                        <img src="<?php echo $photo; ?>" data-fallback="<?php echo BASE_URL; ?>assets/img/no-photo.png" onerror="handleStaffPhotoError(this);" alt="Staff Photo">
                    <?php else: ?>
                        <div class="photo-placeholder">Employee<br>Photo</div>
                    <?php endif; ?>
                </div>

                <div class="name"><?php echo e($fullName); ?></div>
                <div class="role"><?php echo e($design['role_text']); ?></div>

                <div class="details">
                    <div class="line"><i class="fa fa-id-badge"></i> <b><?php echo e($design['id_label']); ?>:</b>&nbsp;<span><?php echo $emp['emp_id']; ?></span></div>
                    <div class="line"><i class="fa fa-briefcase"></i> <b><?php echo e($design['designation_label']); ?>:</b>&nbsp;<span><?php echo e($emp['designation'] ?? '-'); ?></span></div>
                    <div class="line"><i class="fa fa-building"></i> <b><?php echo e($design['department_label']); ?>:</b>&nbsp;<span><?php echo e($emp['department'] ?? '-'); ?></span></div>
                </div>

                <div class="foot">
                    <div class="foot-left">
                        <div class="foot-date-line"><?php echo date('d-M-Y', strtotime($validity)); ?></div>
                        <div class="foot-date-line"><span class="foot-lbl"><?php echo e($design['valid_label']); ?></span></div>
                    </div>
                    <div class="foot-qr">
                        <div class="qr-wrap">
                            <img src="<?php echo $qrSrc; ?>" alt="QR Code" onerror="this.style.display='none';">
                        </div>
                    </div>
                    <div class="foot-right">
                        <div class="foot-principal-label">
                            <div class="sign-below">
                                <?php if ($sigSrc !== ''): ?>
                                    <img src="<?php echo $sigSrc; ?>" alt="Principal Signature">
                                <?php else: ?>
                                    <div style="border-top:1px solid #333; width:56px; margin:0 auto 1px;"></div>
                                <?php endif; ?>
                                <div class="sig-cap"><?php echo e($design['principal_text']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bar"></div>
            </div>
        </div>
    <?php endforeach; ?>
  </div>
<script>
function openCustomizeModal(){ document.getElementById('customizeModal').style.display='block'; }
function closeCustomizeModal(){ document.getElementById('customizeModal').style.display='none'; }
function openCardDimensions(){ document.getElementById('dimModal').style.display='block'; }
function closeCardDimensions(){ document.getElementById('dimModal').style.display='none'; }
function handleStaffPhotoError(imgEl){
  var fallbackSrc = imgEl.getAttribute('data-fallback');
  if (fallbackSrc && imgEl.src !== fallbackSrc) {
    imgEl.src = fallbackSrc;
    imgEl.removeAttribute('data-fallback');
    return;
  }
  if (imgEl.parentNode) {
    imgEl.parentNode.innerHTML = '<div class="photo-placeholder">Employee<br>Photo</div>';
  }
}
</script>
</body>
</html>