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
    <title>School Setup | LAPS School & College</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', Arial, sans-serif;
            background: radial-gradient(1200px 800px at 10% 10%, #eef2ff 0%, #f4f4f4 50%, #f8fafc 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .setup-box {
            width: 100%;
            max-width: 720px;
            margin: 22px;
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 25px 70px rgba(15, 23, 42, 0.12);
            border: 1px solid #e7ecf4;
            transition: transform .3s ease, box-shadow .3s ease;
        }
        .setup-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 32px 80px rgba(15, 23, 42, 0.16);
        }
        .setup-hd {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
            padding: 26px 30px 20px;
        }
        .setup-hd .school-icon { font-size: 30px; color: #ff7800; margin-right: 10px; }
        .setup-hd h2 { margin: 0; font-size: 22px; font-weight: 700; }
        .setup-hd p { margin: 6px 0 0; font-size: 13px; color: #cbd5e1; }
        .setup-bd { padding: 26px 30px 20px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { font-weight: 600; font-size: 13px; color: #334155; display: block; margin-bottom: 6px; }
        .form-control:focus { box-shadow: 0 0 0 0.25rem rgba(255, 122, 0, 0.15); border-color: #ff7800; }
        .logo-box {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f8fafc;
        }
        .logo-box img { max-height: 100%; max-width: 100%; }
        .logo-box i { font-size: 44px; color: #94a3b8; }
        .btn-enter {
            width: 100%;
            padding: 13px;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            background: #ff7800;
            border: none;
            border-radius: 10px;
            margin-top: 6px;
            cursor: pointer;
            transition: all .3s ease;
            box-shadow: 0 8px 18px rgba(255, 120, 0, 0.25);
        }
        .btn-enter:hover {
            background: #e56700;
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(255, 120, 0, 0.35);
        }
        .btn-enter:active { transform: translateY(0); box-shadow: 0 4px 10px rgba(255,120,0,.2); }
        .btn-save {
            background: #16a34a;
            color: #fff;
            font-weight: 600;
        }
        .btn-save:hover { background: #15803d; color: #fff; }
        .setup-help {
            margin-top: 18px;
            font-size: 12.5px;
            color: #64748b;
            background: #f1f5f9;
            border-left: 3px solid #ff7800;
            padding: 10px 12px;
            border-radius: 6px;
        }
        .alert-box { border-radius: 8px; font-size: 13px; }
        /* ===== Responsive & Mobile polish ===== */
        .form-control {
            min-height: 44px;
            border-radius: 9px;
            border: 1px solid #d5dee9;
            padding: 10px 13px;
        }
        .error-text { color: #dc2626; font-size: 12.5px; font-weight: 500; }
        @media (max-width: 620px) {
            .setup-box {
                margin: 12px;
                border-radius: 14px;
                max-width: 100%;
            }
            .setup-hd { padding: 20px 20px 16px; text-align: center; }
            .setup-hd .school-icon { display: block; margin: 0 auto 8px; text-align: center; }
            .setup-hd h2 { font-size: 19px; }
            .setup-bd { padding: 20px 18px 16px; }
            .form-group { margin-bottom: 14px; }
            .logo-box { height: 128px; }
            .btn-save, .btn-save + .form-group { margin-top: 8px; }
            .btn-skip, .skip-login-link { font-size: 13px; }
        }
        @media (max-width: 420px) {
            .setup-hd p { font-size: 12px; }
            .setup-help, .setup-hd .setup-help { width: 100%; }
            .btn-save, .btn-enter { font-size: 14px; padding: 12px 10px; }
        }
    </style>
</head>
<body>
<div class="setup-box">
    <div class="setup-hd">
        <h2><i class="fas fa-school school-icon"></i> School / Campus Setup</h2>
        <p>Website is currently OFF — set up your school info before login. This info auto-applies: ID cards, family cards, card back side, certificates, roll slips, pay slips, salary slips and reports.</p>
    </div>
    <div class="setup-bd">
        <?php if ($schoolMsg): ?>
            <div class="alert alert-success alert-box"><i class="fas fa-check-circle"></i> School info saved. Now click "Login Website" to proceed.</div>
        <?php elseif ($schoolErr): ?>
            <div class="alert alert-danger alert-box"><i class="fas fa-exclamation-circle"></i> School name required.</div>
        <?php endif; ?>

        <form action="school_setup.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="school_info_save">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label>Campus / School Name</label>
                        <input type="text" class="form-control" name="si_name" value="<?php echo e($campusInfo['name'] ?? ''); ?>" placeholder="e.g. LAPS DHA Campus" required="">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" class="form-control" name="si_address" value="<?php echo e($campusInfo['address'] ?? ''); ?>" placeholder="School / campus address">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" class="form-control" name="si_phone" value="<?php echo e($campusInfo['phone'] ?? ''); ?>" placeholder="e.g. 0300-1234567">
                    </div>
                </div>
                <div class="col-md-4">
                    <label>Logo</label>
                    <div class="logo-box">
                        <?php $lg = trim((string)($campusInfo['logo'] ?? '')); ?>
                        <?php if ($lg !== ''): ?>
                            <img id="schoolLogoPreview" src="<?php echo BASE_URL . e($lg); ?>" alt="Logo" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';">
                        <?php else: ?>
                            <span id="schoolLogoPreview"><i class="fa fa-image"></i></span>
                        <?php endif; ?>
                    </div>
                    <input type="file" id="schoolLogoInput" name="si_logo" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" class="form-control" style="margin-top:8px;">
                    <button type="submit" class="btn btn-save" style="width:100%; margin-top:10px;"><i class="fa fa-save"></i> Save School Info</button>
                </div>
            </div>
        </form>

        <a href="<?php echo BASE_URL . $enterHref; ?>" class="btn btn-enter"><i class="fa fa-rocket"></i> Login Website</a>
        <a href="<?php echo BASE_URL . $skipHref; ?>" class="skip-login-link"><i class="fa fa-angle-double-right"></i> Skip - Direct Login </a>

        <div class="setup-help"><i class="fas fa-info-circle"></i> Press "Login Website" and the login page opens. Later this info applies automatically on every card - student portrait/landscape, family cards, card back side, certificates, roll slips, pay slips, salary slips and reports.</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
