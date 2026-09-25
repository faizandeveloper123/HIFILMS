<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/campus.php';
require_once __DIR__ . '/includes/ensure_schema.php';

// Public page - opens BEFORE login. When the app is switched OFF, this is the
// first screen: admin enters school/campus info which auto-applies everywhere,
// then clicks "Enter Website" to go to the login page.

$schoolMsg  = '';
$schoolErr  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'school_info_save') {
    $sName = trim((string)($_POST['si_name'] ?? ''));
    $sAddr = trim((string)($_POST['si_address'] ?? ''));
    $sPhone = trim((string)($_POST['si_phone'] ?? ''));

    if ($sName === '') {
        header('Location: school_setup.php?saved=0');
        exit;
    }

    $logo = '';
    if (!empty($_FILES['si_logo']['name']) && ($_FILES['si_logo']['tmp_name'] ?? '') !== '' && is_uploaded_file($_FILES['si_logo']['tmp_name'])) {
        $info = @getimagesize($_FILES['si_logo']['tmp_name']);
        if ($info !== false) {
            $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
            $ext = $map[$info[2]] ?? 'png';
            $dir = __DIR__ . '/uploads/campuses';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $fname = 'campus_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (@move_uploaded_file($_FILES['si_logo']['tmp_name'], $dir . '/' . $fname)) { $logo = 'uploads/campuses/' . $fname; }
        }
    }

    $campus = get_active_campus();
    if ($campus) {
        $keepLogo = ($logo !== '') ? $logo : (string)($campus['logo'] ?? '');
        $p = db_prepare("UPDATE campuses SET name=?, address=?, phone=?, logo=?, is_active=1 WHERE campus_id=?");
        $v1 = $sName; $v2 = $sAddr; $v3 = $sPhone; $v4 = $keepLogo; $v5 = (int)$campus['campus_id'];
        $p->bind_param('ssssi', $v1, $v2, $v3, $v4, $v5);
        $p->execute();
    } else {
        $p = db_prepare("INSERT INTO campuses (name, address, phone, logo, is_active) VALUES (?,?,?,?,1)");
        $v1 = $sName; $v2 = $sAddr; $v3 = $sPhone; $v4 = $logo;
        $p->bind_param('ssss', $v1, $v2, $v3, $v4);
        $p->execute();
    }
    $schoolSaved = get_active_campus();
    if ($schoolSaved) { apply_campus_settings($schoolSaved); }
    header('Location: school_setup.php?saved=1');
    exit;
}

// ===== Finalize pre-login: index.php ne valid login ko pending rakha tha. =====
// Ab user school info save/wapas kar ke "Enter Website" dabata hai → real
// session set + proper dashboard (role-wise).
if (($_GET['finalize'] ?? '') === '1' && !empty($_SESSION['pending_login'])) {
    $pl = $_SESSION['pending_login'];
    $e  = $pl['email'] ?? ''; $p  = $pl['password'] ?? '';
    $stmt = db_prepare("SELECT user_id, email, password, full_name, role, status FROM users WHERE email = ? AND status = 1");
    $stmt->bind_param('s', $e);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user && hash('sha256', $p) === $user['password']) {
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['user_id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name']  = $user['full_name'];
        $_SESSION['user_role']  = $user['role'];
        unset($_SESSION['pending_login']);
        $destMap = [
            'admin'    => 'dashboard.php',
            'staff'    => 'staff_dashboard.php',
            'teacher'  => 'teacher_dashboard.php',
            'accounts' => 'accounts_dashboard.php',
        ];
        $dest = $destMap[$user['role']] ?? 'dashboard.php';
        header('Location: ' . BASE_URL . $dest);
        exit;
    }
    unset($_SESSION['pending_login']);
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
// No pending login → public page. All buttons go to login (index.php).
$pendingLogin = !empty($_SESSION['pending_login']);
$enterHref = $pendingLogin ? 'school_setup.php?finalize=1' : 'index.php';
$skipHref  = $pendingLogin ? 'school_setup.php?finalize=1' : 'index.php';

$schoolMsg  = (($_GET['saved'] ?? '') === '1');
$schoolErr  = (($_GET['saved'] ?? '') === '0');
$campusInfo = get_active_campus();
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Setup | LAPS School &amp; College</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --brand: #f97316;
            --brand-dark: #ea580c;
            --ink: #0f172a;
            --slate: #64748b;
            --line: #e2e8f0;
            --soft: #f8fafc;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', Arial, sans-serif;
            color: var(--ink);
            background: linear-gradient(160deg, #eef2ff 0%, #f8fafc 45%, #fff7ed 100%);
            position: relative;
            overflow-x: hidden;
        }
        .blob { position: fixed; border-radius: 50%; filter: blur(90px); opacity: .35; z-index: 0; pointer-events: none; }
        .blob.b1 { width: 420px; height: 420px; background: #fecaca; top: -120px; right: -80px; }
        .blob.b2 { width: 380px; height: 380px; background: #fed7aa; bottom: -140px; left: -100px; }
        .blob.b3 { width: 300px; height: 300px; background: #c7d2fe; top: 40%; left: 55%; }

        .page-wrap { position: relative; z-index: 1; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 26px 14px; }

        .setup-card {
            width: 100%;
            max-width: 860px;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(15, 23, 42, .14), 0 2px 8px rgba(15, 23, 42, .05);
            border: 1px solid var(--line);
            animation: rise .5s cubic-bezier(.16,1,.3,1);
        }
        @keyframes rise { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }

        /* ---- Top bar ---- */
        .topbar {
            display: flex; align-items: center; justify-content: space-between; gap: 14px;
            padding: 14px 30px;
            border-bottom: 1px solid var(--line);
            background: var(--soft);
        }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 15px; color: var(--ink); }
        .brand .logo-mark { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, var(--brand), #fbbf24); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; box-shadow: 0 6px 14px rgba(249,115,22,.35); }
        .status-pill {
            display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 600;
            color: #92400e; background: #fffbeb; border: 1px solid #fde68a; padding: 6px 12px; border-radius: 999px;
        }
        .status-pill .dot { width: 7px; height: 7px; border-radius: 50%; background: #f59e0b; animation: pulse 1.6s infinite; }
        @keyframes pulse { 0%,100% { box-shadow: 0 0 0 0 rgba(245,158,11,.5);} 50% { box-shadow: 0 0 0 6px rgba(245,158,11,0);} }

        /* ---- Stepper ---- */
        .stepper { display: flex; align-items: center; justify-content: center; gap: 0; padding: 20px 30px 0; }
        .step { display: flex; align-items: center; gap: 8px; }
        .step .num {
            width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; flex-shrink: 0;
        }
        .step.active .num { background: var(--brand); color: #fff; box-shadow: 0 6px 14px rgba(249,115,22,.35); }
        .step.done .num { background: #dcfce7; color: #16a34a; }
        .step.pending .num { background: #e2e8f0; color: #94a3b8; }
        .step .lbl { font-size: 13px; font-weight: 600; color: var(--slate); }
        .step.active .lbl { color: var(--ink); font-weight: 700; }
        .step.done .lbl { color: #16a34a; }
        .step-line { width: 46px; height: 2px; background: #e2e8f0; margin: 0 10px; border-radius: 2px; }
        .step-line.filled { background: linear-gradient(90deg, #f97316, #fbbf24); }

        /* ---- Header ---- */
        .setup-hd {
            padding: 8px 30px 4px;
            text-align: center;
        }
        .setup-hd h2 { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -.3px; }
        .setup-hd p { margin: 8px auto 0; max-width: 620px; font-size: 13.5px; color: var(--slate); line-height: 1.6; }
        .step-title { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 6px; }
        .step-title .badge { background: linear-gradient(135deg, var(--brand), #fbbf24); color: #fff; font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 999px; letter-spacing: .3px; }

        .setup-bd { padding: 22px 30px 26px; }

        /* ---- Alerts ---- */
        .alert-modern {
            display: flex; align-items: flex-start; gap: 10px; border-radius: 12px; padding: 12px 14px; margin-bottom: 18px;
            font-size: 13px; font-weight: 500;
        }
        .alert-modern i { margin-top: 2px; }
        .alert-ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .alert-err { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

        /* ---- Form ---- */
        .form-group { margin-bottom: 17px; }
        .form-group label { display: block; font-weight: 600; font-size: 12.5px; color: #334155; margin-bottom: 7px; letter-spacing: .1px; }
        .input-wrap { position: relative; }
        .input-wrap .ficon {
            position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
            font-size: 14px; color: #94a3b8; pointer-events: none; transition: color .2s;
        }
        .form-control {
            min-height: 46px; border-radius: 11px; border: 1px solid #cbd5e1; background: #fff;
            padding: 11px 14px 11px 40px; font-size: 14px; color: var(--ink);
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .form-control::placeholder { color: #b0bccb; }
        .form-control:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(249,115,22,.14);
            background: #fff;
        }
        .form-control:focus + .ficon, .input-wrap:focus-within .ficon { color: var(--brand); }
        .field-hint { font-size: 11.5px; color: #94a3b8; margin-top: 5px; }

        /* ---- Logo uploader ---- */
        .logo-label { display: flex; align-items: center; justify-content: space-between; margin-bottom: 7px; }
        .logo-label span { font-weight: 600; font-size: 12.5px; color: #334155; }
        .upload-zone {
            border: 2px dashed #cbd5e1; border-radius: 14px; background: linear-gradient(180deg, #f8fafc, #fff);
            padding: 16px; text-align: center; cursor: pointer; transition: all .25s ease; height: 172px;
            display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;
        }
        .upload-zone:hover, .upload-zone.drag { border-color: var(--brand); background: #fff7ed; }
        .upload-zone .dz-icon { font-size: 34px; color: #cbd5e1; margin-bottom: 8px; transition: all .25s; }
        .upload-zone:hover .dz-icon { color: var(--brand); transform: translateY(-3px); }
        .upload-zone .dz-txt { font-size: 12.5px; font-weight: 600; color: var(--slate); }
        .upload-zone .dz-sub { font-size: 11px; color: #94a3b8; margin-top: 3px; }
        .upload-zone img { max-height: 100%; max-width: 100%; object-fit: contain; }
        .upload-zone .remove-btn {
            position: absolute; top: 8px; right: 8px; width: 26px; height: 26px; border-radius: 50%;
            background: #fee2e2; color: #dc2626; border: none; font-size: 12px; cursor: pointer;
            display: none; align-items: center; justify-content: center; transition: all .2s;
        }
        .upload-zone .remove-btn:hover { background: #dc2626; color: #fff; }
        .upload-zone.has-img .remove-btn { display: flex; }
        .file-input { display: none; }

        /* ---- Applies-to chips ---- */
        .applies { margin-top: 20px; }
        .applies .cap { font-size: 11px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: #94a3b8; margin-bottom: 10px; }
        .chip-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .chip {
            display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 600; color: #475569;
            background: var(--soft); border: 1px solid var(--line); border-radius: 999px; padding: 6px 11px;
            transition: all .2s;
        }
        .chip i { color: var(--brand); font-size: 11px; }
        .chip:hover { border-color: #fdba74; color: var(--brand-dark); background: #fff7ed; }

        /* ---- Actions ---- */
        .actions { display: flex; flex-direction: column; align-items: center; gap: 6px; margin-top: 26px; padding-top: 20px; border-top: 1px solid var(--line); }
        .btn-enter {
            flex: 1; padding: 13px 20px; font-size: 15px; font-weight: 700; color: #fff; border: none; border-radius: 12px;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            box-shadow: 0 10px 24px rgba(249,115,22,.35);
            transition: all .25s ease; display: inline-flex; align-items: center; justify-content: center; gap: 9px;
        }
        .btn-enter:hover { transform: translateY(-2px); box-shadow: 0 16px 32px rgba(249,115,22,.42); color: #fff; }
        .btn-enter:active { transform: translateY(0); }
        .btn-save {
            padding: 13px 22px; font-size: 14px; font-weight: 700; color: #fff; border: none; border-radius: 12px;
            background: linear-gradient(135deg, #22c55e, #16a34a); transition: all .25s ease;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px; flex-shrink: 0;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(22,163,74,.3); color: #fff; }
        .skip-login-link {
            display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: var(--slate);
            text-decoration: none; flex-shrink: 0; padding: 10px 6px; border-radius: 10px; transition: all .2s;
        }
        .skip-login-link:hover { color: var(--brand); background: #fff7ed; text-decoration: none; }

        .setup-footer { text-align: center; padding: 0 30px 22px; font-size: 12px; color: #94a3b8; }

        /* ---- Responsive ---- */
        @media (max-width: 620px) {
            .setup-card { border-radius: 16px; }
            .topbar, .setup-hd { padding-left: 18px; padding-right: 18px; }
            .setup-bd { padding: 18px 18px 22px; }
            .setup-hd h2 { font-size: 20px; }
            .status-pill .stxt { display: none; }
            .actions { flex-wrap: wrap; }
            .btn-enter, .btn-save { width: 100%; }
            .skip-login-link { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="blob b1"></div>
    <div class="blob b2"></div>
    <div class="blob b3"></div>

    <div class="page-wrap">
        <div class="setup-card">
            <div class="topbar">
                <div class="brand">
                    <div class="logo-mark"><i class="fa fa-school"></i></div>
                    <span>School Setup Center</span>
                </div>
                <span class="status-pill"><span class="dot"></span><span class="stxt">Setup in progress</span></span>
            </div>

            <div class="stepper">
                <div class="step done">
                    <span class="num"><i class="fas fa-check"></i></span>
                    <span class="lbl">Account</span>
                </div>
                <div class="step-line filled"></div>
                <div class="step active">
                    <span class="num"><i class="fas fa-school"></i></span>
                    <span class="lbl">School Info</span>
                </div>
                <div class="step-line"></div>
                <div class="step pending">
                    <span class="num"><i class="fas fa-rocket"></i></span>
                    <span class="lbl">Dashboard</span>
                </div>
            </div>

            <div class="setup-hd">
                <div class="step-title">
                    <h2>Your School Information</h2>
                    <span class="badge">STEP 2 OF 3</span>
                </div>
                <p>Add your campus details once — this information is used automatically across ID cards, family cards, certificates, roll slips, pay slips and reports.</p>
            </div>

            <div class="setup-bd">
                <?php if ($schoolMsg): ?>
                    <div class="alert-modern alert-ok"><i class="fas fa-check-circle"></i> School info saved successfully. Now click <strong>"Login Website"</strong> to continue.</div>
                <?php elseif ($schoolErr): ?>
                    <div class="alert-modern alert-err"><i class="fas fa-exclamation-circle"></i> School / Campus name is required.</div>
                <?php endif; ?>

                <form action="school_setup.php" method="post" enctype="multipart/form-data" id="schoolForm">
                    <input type="hidden" name="action" value="school_info_save">
                    <div class="row g-4">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Campus / School Name *</label>
                                <div class="input-wrap">
                                    <input type="text" class="form-control" name="si_name" id="si_name" value="<?php echo e($campusInfo['name'] ?? ''); ?>" placeholder="e.g. LAPS DHA Campus" required="">
                                    <i class="fa fa-building ficon"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <div class="input-wrap">
                                    <input type="text" class="form-control" name="si_address" id="si_address" value="<?php echo e($campusInfo['address'] ?? ''); ?>" placeholder="School / campus address">
                                    <i class="fa fa-map-marker-alt ficon"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <div class="input-wrap">
                                    <input type="text" class="form-control" name="si_phone" id="si_phone" value="<?php echo e($campusInfo['phone'] ?? ''); ?>" placeholder="e.g. 0300-1234567">
                                    <i class="fa fa-phone-alt ficon"></i>
                                </div>
                                <div class="field-hint">Best to use the number printed on cards & receipts.</div>
                            </div>

                            <div class="applies">
                                <div class="cap">Auto-applies to</div>
                                <div class="chip-row">
                                    <span class="chip"><i class="fas fa-id-card"></i> ID Cards</span>
                                    <span class="chip"><i class="fas fa-users"></i> Family Cards</span>
                                    <span class="chip"><i class="fas fa-award"></i> Certificates</span>
                                    <span class="chip"><i class="fas fa-file-invoice-dollar"></i> Pay Slips</span>
                                    <span class="chip"><i class="fas fa-file-alt"></i> Reports</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="logo-label">
                                <span>School Logo</span>
                            </div>
                            <label class="upload-zone" id="uploadZone" for="schoolLogoInput">
                                <?php $lg = trim((string)($campusInfo['logo'] ?? '')); ?>
                                <?php if ($lg !== ''): ?>
                                    <img id="schoolLogoPreview" src="<?php echo BASE_URL . e($lg); ?>" alt="Logo" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';">
                                <?php else: ?>
                                    <span id="logoPlaceholder" class="text-center">
                                        <div class="dz-icon"><i class="fa fa-cloud-upload-alt"></i></div>
                                        <div class="dz-txt">Click to upload</div>
                                        <div class="dz-sub">PNG, JPG, WebP &bull; Max 2 MB</div>
                                    </span>
                                <?php endif; ?>
                                <button type="button" class="remove-btn" id="removeLogo" title="Remove logo"><i class="fa fa-times"></i></button>
                            </label>
                            <input type="file" id="schoolLogoInput" name="si_logo" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" class="file-input">
                            <div class="field-hint" style="text-align:center;">Transparent PNG looks best.</div>
                            <button type="submit" class="btn btn-save mt-2"><i class="fa fa-save"></i> Save School Info</button>
                        </div>
                    </div>
                </form>

                <div class="actions">
                    <a href="<?php echo BASE_URL . $enterHref; ?>" class="btn-enter"><i class="fa fa-rocket"></i> Login Website</a>
                    <a href="<?php echo BASE_URL . $skipHref; ?>" class="skip-login-link"><i class="fa fa-angle-double-right"></i> Skip - Direct Login</a>
                </div>
            </div>

            <div class="setup-footer">Powered by Parker Technologies LLC</div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const zone   = document.getElementById('uploadZone');
    const input  = document.getElementById('schoolLogoInput');
    const remove = document.getElementById('removeLogo');

    function previewFile(file) {
        if (!file || !file.type.match('image.*')) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            let img = zone.querySelector('img');
            let ph = document.getElementById('logoPlaceholder');
            if (!img) {
                img = document.createElement('img');
                img.id = 'schoolLogoPreview';
                const old = document.getElementById('schoolLogoPreview');
                if (old) old.remove();
                zone.insertBefore(img, zone.firstChild);
            }
            img.src = e.target.result;
            img.onerror = null;
            if (ph) ph.style.display = 'none';
            zone.classList.add('has-img');
        };
        reader.readAsDataURL(file);
    }

    input.addEventListener('change', function () { previewFile(this.files[0]); });

    if (remove) {
        remove.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            input.value = '';
            const img = document.getElementById('schoolLogoPreview');
            if (img) img.remove();
            const ph = document.getElementById('logoPlaceholder');
            if (ph) ph.style.display = '';
            zone.classList.remove('has-img');
        });
    }

    ['dragover','dragenter'].forEach(function (ev) {
        zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('drag'); });
    });
    ['dragleave','drop'].forEach(function (ev) {
        zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('drag'); });
    });
    zone.addEventListener('drop', function (e) {
        const f = e.dataTransfer && e.dataTransfer.files[0];
        if (f) { input.files = e.dataTransfer.files; previewFile(f); }
    });
})();
</script>
</body></html>