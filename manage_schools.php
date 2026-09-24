<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin']);

$page_title = 'Manage School (Campuses)';

db_query("CREATE TABLE IF NOT EXISTS campuses (
  campus_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL DEFAULT '',
  tagline VARCHAR(191) NOT NULL DEFAULT '',
  logo VARCHAR(255) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  phone VARCHAR(64) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function campus_promote($id) {
    db_query("UPDATE campuses SET is_active = 0");
    $p = db_prepare("UPDATE campuses SET is_active = 1 WHERE campus_id = ?");
    $v = (int)$id;
    $p->bind_param('i', $v);
    $p->execute();
}

function campus_upload_logo($file) {
    if (empty($file['name']) || ($file['tmp_name'] ?? '') === '' || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) { return false; }
    $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    $ext = $map[$info[2]] ?? 'png';
    $dir = __DIR__ . '/uploads/campuses';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $name = 'campus_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    if (@move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return 'uploads/campuses/' . $name;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $cid = (int)($_POST['campus_id'] ?? 0);

    if ($action === 'save') {
        $name = trim((string)($_POST['name'] ?? ''));
        $tagline = trim((string)($_POST['tagline'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $makeActive = (($_POST['make_active'] ?? '0') === '1');

        if ($name === '') {
            header('Location: manage_schools.php?err=' . rawurlencode('Please enter the school / campus name.'));
            exit;
        }

        $logo = campus_upload_logo($_FILES['logo_file'] ?? []);
        if ($logo === false) {
            header('Location: manage_schools.php?err=' . rawurlencode('Invalid logo file. Only JPG, PNG, GIF or WEBP images are allowed.'));
            exit;
        }

        if ($cid > 0) {
            if ($logo === null) {
                $old = get_campus($cid);
                $logo = ($old && !empty($old['logo'])) ? $old['logo'] : '';
            }
            $p = db_prepare("UPDATE campuses SET name=?, tagline=?, logo=?, address=?, phone=? WHERE campus_id=?");
            $p->bind_param('sssssi', $name, $tagline, $logo, $address, $phone, $cid);
            $p->execute();
        } else {
            if ($logo === null) { $logo = ''; }
            $p = db_prepare("INSERT INTO campuses (name, tagline, logo, address, phone, is_active) VALUES (?, ?, ?, ?, ?, 0)");
            $p->bind_param('sssss', $name, $tagline, $logo, $address, $phone);
            $p->execute();
            $cid = (int)$p->insert_id;
        }

        $cnts = get_campuses();
        $first = (count($cnts) === 1);
        if ($first) { $makeActive = true; }
        if ($makeActive) { campus_promote($cid); }

        $active = get_active_campus();
        if ($active) { apply_campus_settings($active); }

        header('Location: manage_schools.php?msg=' . rawurlencode('School info saved successfully. The active campus is now used everywhere (cards, back sides, reports).'));
        exit;
    }

    if ($action === 'set_active') {
        $target = get_campus($cid);
        if ($target) {
            campus_promote($cid);
            apply_campus_settings($target);
            header('Location: manage_schools.php?msg=' . rawurlencode($target['name'] . ' is now the active campus. All cards & prints will use its name, logo, address and phone.'));
            exit;
        }
    }

    if ($action === 'delete') {
        $target = get_campus($cid);
        if ($target) {
            if (!empty($target['logo']) && strpos($target['logo'], 'uploads/campuses/') === 0) {
                $f = __DIR__ . '/' . $target['logo'];
                if (is_file($f)) { @unlink($f); }
            }
            $p = db_prepare("DELETE FROM campuses WHERE campus_id = ?");
            $p->bind_param('i', $cid);
            $p->execute();
            $active = get_active_campus();
            if ($active) { apply_campus_settings($active); }
        }
        header('Location: manage_schools.php?msg=' . rawurlencode('School record deleted.'));
        exit;
    }

    header('Location: manage_schools.php');
    exit;
}

$msg = trim((string)($_GET['msg'] ?? ''));
$err = trim((string)($_GET['err'] ?? ''));
$campuses = get_campuses();
$activeCampus = get_active_campus();

$editing = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) { $editing = get_campus($editId); }

include __DIR__ . '/includes/header.php';
?>
<style>
.mng-wrap { padding: 0 4px 40px; font-family: 'Inter', system-ui, sans-serif; color: #212529; }
.mng-crumb { font-size: 12.5px; color: #8a99a8; padding: 2px 4px 8px; }
.mng-crumb a { color: #3e7cb1; text-decoration: none; }
.mng-title-row { display: flex; align-items: center; gap: 8px; padding: 0 4px 10px; font-size: 17px; font-weight: 800; color: #2a3f54; }
.mng-title-row i { color: #3e7cb1; }
.mng-panel { background: #fff; border: 1px solid #e6e9ed; border-radius: 12px; margin-bottom: 16px; padding: 18px; box-shadow: 0 1px 3px rgba(42,63,84,.06); }
.mng-panel h4 { font-size: 14.5px; font-weight: 800; color: #1F2937; margin: 0 0 14px; padding-bottom: 10px; border-bottom: 2px solid #FF7800; }
.mng-panel h4 i { color: #FF7800; margin-right: 8px; }
.mng-legend { display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 600; color: #6c757d; background:#f5f8fb; border:1px dashed #c7d3e0; border-radius:8px; padding:8px 12px; margin-bottom:14px; }
.mng-legend b { color: #27ae60; }
.mng-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
.campus-card { border: 1.5px solid #e6e9ed; border-radius: 12px; overflow: hidden; background: #fff; transition: border-color .15s, box-shadow .15s; }
.campus-card:hover { box-shadow: 0 6px 18px rgba(62,124,177,.12); }
.campus-card.active { border-color: #27ae60; box-shadow: 0 0 0 1px #27ae60; }
.cc-top { display: flex; align-items: center; gap: 12px; padding: 14px 14px 0; }
.cc-logo { width: 58px; height: 58px; border-radius: 50%; overflow: hidden; border: 2px solid #eef1f4; background: #f7f9fb; flex-shrink: 0; display:flex; align-items:center; justify-content:center; }
.cc-logo img, .cc-logo span { width: 100%; height: 100%; object-fit: cover; }
.cc-logo span { display:flex; align-items:center; justify-content:center; background: linear-gradient(135deg,#3e7cb1,#275e90); color:#fff; font-size:22px; font-weight:800; }
.cc-name { font-size: 14.5px; font-weight: 800; color: #2a3f54; line-height: 1.25; }
.cc-badge { display: inline-block; margin-top: 5px; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 999px; }
.cc-badge.on { background: #e2f5e8; color: #1b8a3c; }
.cc-badge.off { background: #eef1f4; color: #8492a2; }
.cc-meta { padding: 10px 14px 12px; font-size: 12px; color: #6c757d; line-height: 1.6; border-top: 1px solid #f1f3f5; margin-top: 12px; }
.cc-meta div i { width: 16px; color: #3e7cb1; }
.cc-actions { display: flex; gap: 8px; padding: 0 14px 14px; }
.cc-actions .btn { flex: 1; border-radius: 8px; font-size: 12.5px; font-weight: 700; }
.mng-empty { text-align: center; padding: 30px 16px; color: #95a5a6; }
.mng-empty i { font-size: 34px; color: #d5dbdb; display: block; margin-bottom: 10px; }
.mng-form label { font-weight: 600; font-size: 12.5px; color: #374151; }
.mng-form .form-control { border-radius: 8px; }
.mng-logo-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.mng-logo-prev { width: 84px; height: 84px; border-radius: 50%; overflow: hidden; border: 2px dashed #d1d5db; background: #f3f4f6; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.mng-logo-prev img, .mng-logo-prev span { width: 100%; height: 100%; object-fit: cover; }
.mng-logo-prev span { display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:24px; }
.switch-check { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 13px; color: #374151; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="mng-wrap">

            <div class="mng-crumb">
                <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp;
                <a href="<?php echo BASE_URL; ?>settings.php">System Settings</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp;
                Manage Schools (Campuses)
            </div>

            <div class="mng-title-row"><i class="fa fa-building"></i> Manage Schools (Campuses)</div>

            <?php if ($msg !== ''): ?>
            <div class="alert alert-success alert-dismissible fade in">
                <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                <?php echo e($msg); ?>
            </div>
            <?php endif; ?>

            <?php if ($err !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade in">
                <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                <?php echo e($err); ?>
            </div>
            <?php endif; ?>

            <div class="mng-legend">
                <i class="fa fa-link" style="color:#3e7cb1;"></i>
                The <b>active</b> campus is applied everywhere automatically: student portrait / landscape cards,
                family cards, card back sides, certificates, pay slips, reports and the site header.
            </div>

            <div class="mng-panel">
                <h4><i class="fa fa-list"></i> School / Campus List</h4>
                <?php if (count($campuses) === 0): ?>
                <div class="mng-empty">
                    <i class="fa fa-building-o"></i>
                    No campus added yet. Use the form below to add your first school / campus.
                </div>
                <?php else: ?>
                <div class="mng-grid">
                    <?php foreach ($campuses as $c): $isAct = (int)($c['is_active'] ?? 0) === 1; ?>
                    <div class="campus-card<?php echo $isAct ? ' active' : ''; ?>">
                        <div class="cc-top">
                            <div class="cc-logo">
                                <?php if (!empty($c['logo'])): ?>
                                    <img src="<?php echo BASE_URL . e($c['logo']); ?>" alt="Logo" onerror="this.onerror=null;this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';">
                                <?php else: ?>
                                    <span><i class="fa fa-university"></i></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="cc-name"><?php echo e($c['name']); ?></div>
                                <span class="cc-badge <?php echo $isAct ? 'on' : 'off'; ?>"><?php echo $isAct ? 'ACTIVE' : 'INACTIVE'; ?></span>
                            </div>
                        </div>
                        <div class="cc-meta">
                            <?php if (trim((string)($c['tagline'] ?? '')) !== ''): ?>
                            <div><i class="fa fa-quote-left"></i> <?php echo e($c['tagline']); ?></div>
                            <?php endif; ?>
                            <?php if (trim((string)($c['address'] ?? '')) !== ''): ?>
                            <div><i class="fa fa-map-marker"></i> <?php echo e($c['address']); ?></div>
                            <?php endif; ?>
                            <?php if (trim((string)($c['phone'] ?? '')) !== ''): ?>
                            <div><i class="fa fa-phone"></i> <?php echo e($c['phone']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="cc-actions">
                            <a class="btn btn-default" href="<?php echo BASE_URL; ?>manage_schools.php?edit=<?php echo (int)$c['campus_id']; ?>#addForm"><i class="fa fa-pencil"></i> Edit</a>
                            <?php if (!$isAct): ?>
                            <form method="post" action="manage_schools.php" style="display:inline;">
                                <input type="hidden" name="action" value="set_active">
                                <input type="hidden" name="campus_id" value="<?php echo (int)$c['campus_id']; ?>">
                                <button type="submit" class="btn btn-success" title="Make this the active campus used everywhere"><i class="fa fa-check-circle"></i> Set Active</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" action="manage_schools.php" style="display:inline;" onsubmit="return confirm('Delete this campus? Its cards everywhere will switch to the active campus.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="campus_id" value="<?php echo (int)$c['campus_id']; ?>">
                                <button type="submit" class="btn btn-danger"><i class="fa fa-remove"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="mng-panel" id="addForm">
                <h4><i class="fa fa-plus-circle"></i> <?php echo $editing ? 'Edit Campus' : 'Add New Campus'; ?></h4>
                <form method="post" action="manage_schools.php" enctype="multipart/form-data" class="mng-form form-horizontal form-label-left">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="campus_id" value="<?php echo $editing ? (int)$editing['campus_id'] : 0; ?>">

                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>School / Campus Name <span style="color:red;">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="191"
                                   value="<?php echo $editing ? e($editing['name']) : e($activeCampus['name'] ?? ''); ?>"
                                   placeholder="e.g., LAPS Model Town Campus">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Tagline</label>
                            <input type="text" name="tagline" class="form-control" maxlength="191"
                                   value="<?php echo $editing ? e($editing['tagline']) : e($activeCampus['tagline'] ?? ''); ?>"
                                   placeholder="e.g., A Path to Excellence">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Address</label>
                            <input type="text" name="address" class="form-control" maxlength="255"
                                   value="<?php echo $editing ? e($editing['address']) : e($activeCampus['address'] ?? ''); ?>"
                                   placeholder="e.g., Main Boulevard, Model Town, Lahore">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Phone Number</label>
                            <input type="text" name="phone" class="form-control" maxlength="64"
                                   value="<?php echo $editing ? e($editing['phone']) : e($activeCampus['phone'] ?? ''); ?>"
                                   placeholder="e.g., 0300-1234567">
                        </div>
                        <div class="form-group col-md-12">
                            <label>School Logo</label>
                            <div class="mng-logo-row">
                                <div class="mng-logo-prev">
                                    <?php $editLogo = $editing ? $editing['logo'] : ($activeCampus['logo'] ?? ''); ?>
                                    <?php if (!empty($editLogo)): ?>
                                        <img id="campusLogoPreview" src="<?php echo BASE_URL . e($editLogo); ?>" alt="Logo" onerror="this.onerror=null;this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';">
                                    <?php else: ?>
                                        <span id="campusLogoPreview"><i class="fa fa-image"></i></span>
                                    <?php endif; ?>
                                </div>
                                <div style="flex:1; min-width:200px;">
                                    <input type="file" id="campusLogoInput" name="logo_file" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png" class="form-control">
                                    <small class="help-text" style="color:#9CA3AF; display:block; margin-top:4px;">Upload a new logo to replace it. Leave empty to keep the current one. JPG / PNG / GIF / WEBP only.</small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-md-12">
                            <label class="switch-check">
                                <input type="checkbox" name="make_active" value="1" <?php echo (!$editing && count($campuses) === 0) ? 'checked' : ''; ?>>
                                Set as the ACTIVE campus (used everywhere on cards &amp; prints)
                            </label>
                        </div>
                    </div>

                    <div class="col-md-6 col-md-offset-3" style="text-align:center; margin-top:6px;">
                        <button type="submit" class="btn btn-primary" style="min-width:180px;"><i class="fa fa-save"></i> Save Campus</button>
                        <?php if ($editing): ?>
                        <a href="<?php echo BASE_URL; ?>manage_schools.php" class="btn btn-default" style="margin-left:8px;"><i class="fa fa-ban"></i> Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('campusLogoInput');
    var preview = document.getElementById('campusLogoPreview');
    var makeActive = document.querySelector('input[name="make_active"]');
    if (input && preview) {
        input.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (!file) { return; }
            var reader = new FileReader();
            reader.onload = function (e) {
                preview.outerHTML = '<img id="campusLogoPreview" src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
                preview = document.getElementById('campusLogoPreview');
                if (makeActive) { makeActive.checked = true; }
            };
            reader.readAsDataURL(file);
        });
    }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>