<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin']);

$page_title = 'System Settings & Configuration';

if (!function_exists('save_setting')) {
    function save_setting($key, $value) {
        $_SESSION['_settings_dirty'] = true;
        unset($GLOBALS['__settings_cache']);
        try {
            $st = db_prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $st->bind_param('ss', $key, $value);
            return $st->execute();
        } catch (Exception $ex) {
            // Fallback when the settings table lacks an upsert-safe key
            $st = db_prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
            $st->bind_param('ss', $value, $key);
            $st->execute();
            if ($st->affected_rows === 0) {
                $st2 = db_prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)');
                $st2->bind_param('ss', $key, $value);
                return $st2->execute();
            }
            return true;
        }
    }
}

$message = '';
$error   = '';

$alerts = [
    ['slug' => 'fee_collection_efficiency',    'title' => 'Fee Collection Efficiency'],
    ['slug' => 'student_attendance_retention', 'title' => 'Student Attendance & Retention Risk'],
    ['slug' => 'teacher_attendance_performance','title' => 'Teacher Attendance & Performance'],
    ['slug' => 'admissions_funnel_health',     'title' => 'Admissions Funnel Health'],
    ['slug' => 'communication_parents',        'title' => 'Communication with Parents'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'SaveSettings') {
    $saved = 0;

    // Institute information
    $inst = [
        'school_name'    => 'school_name',
        'school_tagline' => 'school_tagline',
        'school_address' => 'school_address',
        'school_phone'   => 'school_phone',
        'owner_name'     => 'owner_name',
        'owner_email'    => 'owner_email',
        'owner_phone'    => 'owner_phone',
        'session_year'   => 'session_year',
        'gr_no_format'   => 'gr_no_format',
        'family_search'  => 'family_search',
        'school_about'   => 'school_about',
        'currency_symbol'=> 'currency_symbol',
    ];
    foreach ($inst as $key => $postKey) {
        save_setting($key, trim($_POST[$postKey] ?? ''));
        $saved++;
    }

    // Logo upload -> uploads/settings/
    $logoPath = get_setting('school_logo', '');
    if (!empty($_FILES['logo_file']['name']) && ($_FILES['logo_file']['tmp_name'] ?? '') !== '' && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $dir = __DIR__ . '/uploads/settings';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $name = 'logo_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (@move_uploaded_file($_FILES['logo_file']['tmp_name'], $dir . '/' . $name)) {
                $logoPath = 'uploads/settings/' . $name;
            }
        }
    }
    save_setting('school_logo', $logoPath);
    $saved++;

    // SMS settings
    $sms = ['message_via', 'stds_welcome_msg', 'paid_salary_msg', 'login_alert_sms', 'fee_recv_sms', 'prefix_paid_remarks'];
    foreach ($sms as $key) {
        save_setting($key, trim($_POST[$key] ?? ''));
        $saved++;
    }
    save_setting('fee_paid_remarks', isset($_POST['fee_paid_remarks']) && $_POST['fee_paid_remarks'] === '1' ? '1' : '0');
    $saved++;

    // Social links & instructions
    $social = ['youtube_link', 'instagram_link', 'tiktok_link', 'facebook_link', 'app_link', 'rollNo_slip_note', 'datesheet_instructions', 'admission_instructions'];
    foreach ($social as $key) {
        save_setting($key, trim($_POST[$key] ?? ''));
        $saved++;
    }

    // AI alerts
    foreach ($alerts as $a) {
        save_setting('ai_alert_' . $a['slug'] . '_freq', trim($_POST['freq_' . $a['slug']] ?? 'fortnightly'));
        save_setting('ai_alert_' . $a['slug'] . '_active', isset($_POST['active_' . $a['slug']]) && $_POST['active_' . $a['slug']] === '1' ? '1' : '0');
        $saved += 2;
    }

    $message = 'All settings saved successfully! (' . $saved . ' keys)';
}

include __DIR__ . '/includes/header.php';
?>
<style>
.settings-page .page-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; padding: 14px 4px; }
.settings-page .page-head h3 { font-size: 18px; font-weight: 800; color: #111827; margin: 0; }
.settings-page .quick-links { display: flex; gap: 8px; flex-wrap: wrap; }
.settings-page .quick-links .btn { border-radius: 999px; padding: 8px 16px; font-size: 13px; font-weight: 600; }
.tab-wrap { background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
.tab-wrap .nav-tabs { border-bottom: 2px solid #F3F4F6; padding: 10px 14px 0; background: #FAFAFB; }
.tab-wrap .nav-tabs > li > a { border: none; border-radius: 10px 10px 0 0; font-weight: 600; color: #6B7280; padding: 11px 16px; font-size: 13.5px; }
.tab-wrap .nav-tabs > li.active > a, .tab-wrap .nav-tabs > li.active > a:hover, .tab-wrap .nav-tabs > li.active > a:focus {
    background: #fff; color: #FF7800; border: none; box-shadow: 0 -1px 3px rgba(0,0,0,.04); border-bottom: 2px solid #FF7800;
}
.tab-wrap .nav-tabs > li > a i { margin-right: 6px; }
.tab-wrap .tab-content { padding: 20px; }
.tab-wrap .tab-content > .tab-pane { display: none; }
.tab-wrap .tab-content > .tab-pane.active { display: block; }
.form-section { background: #FAFAFB; border: 1px solid #EDEFF2; border-radius: 10px; padding: 18px; margin-bottom: 18px; }
.form-section h4 { font-size: 14.5px; font-weight: 800; color: #1F2937; margin: 0 0 14px; padding-bottom: 10px; border-bottom: 2px solid #FF7800; }
.form-section h4 i { color: #FF7800; margin-right: 8px; }
.form-group label { font-weight: 600; font-size: 12.5px; color: #374151; }
.form-group .help-text { font-size: 11.5px; color: #9CA3AF; display: block; margin-top: 4px; }
.logo-row { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
.logo-preview-circle { width: 130px; height: 130px; border-radius: 50%; overflow: hidden; border: 3px dashed #D1D5DB; background: #F3F4F6; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.logo-preview-circle img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.submit-bar { background: linear-gradient(to right, #F8FAFC, #fff); border: 1px solid #E5E7EB; border-radius: 12px; padding: 20px; text-align: center; margin-top: 6px; }
.alerts-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
.switch-box { display: flex; align-items: center; gap: 10px; }
.switch { position: relative; width: 46px; height: 24px; background: #D1D5DB; border-radius: 999px; cursor: pointer; transition: background .2s; display: inline-block; flex-shrink: 0; }
.switch input { display: none; }
.switch .slider { position: absolute; top: 0; left: 0; right: 0; bottom: 0; border-radius: 999px; }
.switch .slider::after { content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; background: #fff; border-radius: 50%; transition: transform .2s; box-shadow: 0 1px 2px rgba(0,0,0,.2); }
.switch input:checked ~ .slider { background: #27AE60; }
.switch input:checked ~ .slider::after { transform: translateX(22px); }
.sw-state { font-weight: 600; font-size: 12.5px; }
.form-section textarea.form-control { min-height: 120px; }
</style>

<div class="main-content settings-page">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="page-head">
            <h3><i class="fa fa-cog"></i> System Settings &amp; Configuration</h3>
            <div class="quick-links">
                <a href="<?php echo BASE_URL; ?>add_student_documents.php" class="btn btn-default"><i class="fa fa-file-text"></i> Student Documents</a>
                <a href="<?php echo BASE_URL; ?>manage_localities.php" class="btn btn-default"><i class="fa fa-map-marker"></i> Localities</a>
                <a href="<?php echo BASE_URL; ?>manage_occupations.php" class="btn btn-default"><i class="fa fa-briefcase"></i> Father Occupations</a>
                <a href="<?php echo BASE_URL; ?>update_profile.php" class="btn btn-info" style="color:#fff;"><i class="fa fa-user"></i> Update Profile</a>
                <a href="<?php echo BASE_URL; ?>update_pswd.php" class="btn btn-warning" style="color:#fff;"><i class="fa fa-key"></i> Change Password</a>
            </div>
        </div>

        <form method="post" action="settings.php" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="action" value="SaveSettings">

            <div class="tab-wrap">
                <ul class="nav nav-tabs">
                    <li class="active"><a href="#institute" data-toggle="tab"><i class="fa fa-building"></i> Institute Information</a></li>
                    <li><a href="#sms" data-toggle="tab"><i class="fa fa-comment"></i> SMS Settings</a></li>
                    <li><a href="#social" data-toggle="tab"><i class="fa fa-share-alt"></i> Social Links &amp; Instructions</a></li>
                    <li><a href="#alerts" data-toggle="tab"><i class="fa fa-bell"></i> AI Alerts</a></li>
                </ul>

                <div class="tab-content">
                    <!-- ================= INSTITUTE ================= -->
                    <div class="tab-pane active" id="institute">
                        <div class="form-section">
                            <h4><i class="fa fa-info-circle"></i> Basic Institute Information</h4>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label>Institute Name <span style="color:red;">*</span></label>
                                    <input type="text" name="Branch_Name" class="form-control" value="<?php echo e(get_setting('school_name', 'HIIFI LMS')); ?>" placeholder="Enter institute name">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Institute Address <span style="color:red;">*</span></label>
                                    <input type="text" name="Branch_Location" class="form-control" value="<?php echo e(get_setting('school_address')); ?>" placeholder="Enter complete address">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Institute Contact <span style="color:red;">*</span></label>
                                    <input type="text" name="branch_contact" class="form-control" value="<?php echo e(get_setting('school_phone')); ?>" placeholder="e.g., 030034567">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Owner Name <span style="color:red;">*</span></label>
                                    <input type="text" name="Owner_Name" class="form-control" value="<?php echo e(get_setting('owner_name')); ?>" placeholder="Enter owner name">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Owner Email <span style="color:red;">*</span></label>
                                    <input type="email" name="Branch_Owner_Email" class="form-control" value="<?php echo e(get_setting('owner_email')); ?>" placeholder="owner@example.com">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Owner Cell Number <span style="color:red;">*</span></label>
                                    <input type="text" name="Owner_no" class="form-control" value="<?php echo e(get_setting('owner_phone')); ?>" placeholder="e.g., 03001234567">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Tagline</label>
                                    <input type="text" name="school_tagline" class="form-control" value="<?php echo e(get_setting('school_tagline', 'Test Portal')); ?>">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Currency Symbol</label>
                                    <input type="text" name="currency_symbol" class="form-control" value="<?php echo e(get_setting('currency_symbol', 'Rs.')); ?>">
                                </div>
                            </div>

                            <div class="logo-row">
                                <div class="logo-preview-circle">
                                    <?php $logo = get_setting('school_logo', ''); ?>
                                    <img id="logoPreview" src="<?php echo $logo ? BASE_URL . e($logo) : BASE_URL . 'assets/img/logo.jpg'; ?>" alt="Logo" onerror="this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';">
                                </div>
                                <div style="flex:1; min-width:220px;">
                                    <label>Institute Logo</label>
                                    <input type="file" id="logoFileInput" name="logo_file" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                    <small class="help-text">Supported: JPG/PNG. Uploaded files are stored under uploads/settings/ and the path is saved.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4><i class="fa fa-calendar"></i> Session &amp; Settings</h4>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label>Current Academic Session</label>
                                    <select name="current_session" class="form-control">
                                        <?php $curSession = get_setting('session_year', '2026-2027'); ?>
                                        <?php for ($y = 2018; $y <= 2030; $y++): ?>
                                            <?php $val = $y . '-' . ($y + 1); ?>
                                            <option value="<?php echo e($val); ?>" <?php echo $curSession === $val ? 'selected' : ''; ?>><?php echo e($val); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>GR. No Format</label>
                                    <select name="no_type" class="form-control">
                                        <?php $gr = get_setting('gr_no_format', '1'); ?>
                                        <option value="1" <?php echo ($gr ?? '1') === '1' ? 'selected' : ''; ?>>Auto (20-001) Overall</option>
                                        <option value="2" <?php echo $gr === '2' ? 'selected' : ''; ?>>Manual Adm No (1002) Overall</option>
                                    </select>
                                    <small class="help-text">Select how student registration numbers are generated</small>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Family Search Method</label>
                                    <select name="family_search" class="form-control">
                                        <?php $fs = get_setting('family_search', 'CN'); ?>
                                        <option value="CN" <?php echo ($fs === '' || $fs === 'CN') ? 'selected' : ''; ?>>Cell Number</option>
                                        <option value="FC" <?php echo $fs === 'FC' ? 'selected' : ''; ?>>Family Code</option>
                                    </select>
                                    <small class="help-text">Choose how to identify family groups</small>
                                </div>
                                <div class="form-group col-md-12">
                                    <label>School Description</label>
                                    <textarea class="form-control" rows="3" name="school_about" placeholder="Brief description about your school..."><?php echo e(get_setting('school_about')); ?></textarea>
                                    <small class="help-text">A brief description that will be displayed in various places</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ================= SMS ================= -->
                    <div class="tab-pane" id="sms">
                        <div class="form-section">
                            <h4><i class="fa fa-comment"></i> SMS &amp; Notification Preferences</h4>
                            <p class="help-text">Configure when and how SMS notifications are sent to students, parents, and staff.</p>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label>Welcome Message to Students</label>
                                    <select name="stds_welcome_msg" class="form-control">
                                        <?php $sw = get_setting('stds_welcome_msg', '1'); ?>
                                        <option value="1" <?php echo ($sw === '' || $sw === '1') ? 'selected' : ''; ?>>YES</option>
                                        <option value="0" <?php echo $sw === '0' ? 'selected' : ''; ?>>NO</option>
                                    </select>
                                    <small class="help-text">Send welcome SMS when new students are enrolled</small>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>SMS on Paid Salary</label>
                                    <select name="paid_salary_msg" class="form-control">
                                        <?php $ps = get_setting('paid_salary_msg', '1'); ?>
                                        <option value="1" <?php echo ($ps === '' || $ps === '1') ? 'selected' : ''; ?>>YES</option>
                                        <option value="0" <?php echo $ps === '0' ? 'selected' : ''; ?>>NO</option>
                                    </select>
                                    <small class="help-text">Send notification when employee salary is paid</small>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Login Alert SMS</label>
                                    <select name="login_alert_sms" class="form-control">
                                        <?php $la = get_setting('login_alert_sms', '1'); ?>
                                        <option value="1" <?php echo ($la === '' || $la === '1') ? 'selected' : ''; ?>>YES</option>
                                        <option value="0" <?php echo $la === '0' ? 'selected' : ''; ?>>NO</option>
                                    </select>
                                    <small class="help-text">Send SMS when user logs into the system</small>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>SMS on Fee Receiving</label>
                                    <select name="fee_recv_sms" class="form-control">
                                        <?php $fr = get_setting('fee_recv_sms', '1'); ?>
                                        <option value="1" <?php echo ($fr === '' || $fr === '1') ? 'selected' : ''; ?>>YES</option>
                                        <option value="0" <?php echo $fr === '0' ? 'selected' : ''; ?>>NO</option>
                                    </select>
                                    <small class="help-text">Send SMS notification when fee is received</small>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>SMS Channel</label>
                                    <select name="message_via" class="form-control">
                                        <?php $mv = get_setting('message_via', '0'); ?>
                                        <option value="0" <?php echo ($mv === '' || $mv === '0') ? 'selected' : ''; ?>>WhatsApp</option>
                                        <option value="1" <?php echo $mv === '1' ? 'selected' : ''; ?>>Mobile Sim</option>
                                        <option value="3" <?php echo $mv === '3' ? 'selected' : ''; ?>>App</option>
                                    </select>
                                    <small class="help-text">Choose the primary channel for sending messages</small>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>&nbsp;</label>
                                    <div style="padding-top:6px;">
                                        <label class="switch-box" style="font-weight:600;">
                                            <span class="switch"><input type="checkbox" name="fee_paid_remarks" value="1" <?php echo get_setting('fee_paid_remarks', '1') === '1' ? 'checked' : ''; ?>><span class="slider"></span></span>
                                            Fee Paid Remarks (Compulsory)
                                        </label>
                                    </div>
                                    <small class="help-text">Require remarks when marking fee as paid</small>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Prefix (Paid Fee Remarks)</label>
                                    <input type="text" name="prefix_paid_remarks" class="form-control" value="<?php echo e(get_setting('prefix_paid_remarks')); ?>" placeholder="e.g., RECEIPT-">
                                    <small class="help-text">Prefix to be added before fee payment remarks</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ================= SOCIAL ================= -->
                    <div class="tab-pane" id="social">
                        <div class="form-section">
                            <h4><i class="fa fa-share-alt"></i> Social Media Links &amp; Instructions</h4>
                            <p class="help-text">Configure social media links and customize instruction texts for various documents.</p>
                            <div class="row">
                                <div class="form-group col-md-3">
                                    <label><i class="fa fa-youtube"></i> YouTube Channel Link</label>
                                    <input type="url" name="youtube_link" class="form-control" value="<?php echo e(get_setting('youtube_link')); ?>" placeholder="https://www.youtube.com/channel/...">
                                </div>
                                <div class="form-group col-md-3">
                                    <label><i class="fa fa-instagram"></i> Instagram Profile Link</label>
                                    <input type="url" name="instagram_link" class="form-control" value="<?php echo e(get_setting('instagram_link')); ?>" placeholder="https://www.instagram.com/...">
                                </div>
                                <div class="form-group col-md-3">
                                    <label><i class="fa fa-facebook"></i> Facebook Page Link</label>
                                    <input type="url" name="facebook_link" class="form-control" value="<?php echo e(get_setting('facebook_link')); ?>" placeholder="https://www.facebook.com/...">
                                </div>
                                <div class="form-group col-md-3">
                                    <label><i class="fa fa-music"></i> TikTok Profile Link</label>
                                    <input type="url" name="tiktok_link" class="form-control" value="<?php echo e(get_setting('tiktok_link')); ?>" placeholder="https://www.tiktok.com/@...">
                                </div>
                                <div class="form-group col-md-4">
                                    <label><i class="fa fa-mobile"></i> Mobile App Download Link</label>
                                    <input type="url" name="app_link" class="form-control" value="<?php echo e(get_setting('app_link', 'https://play.google.com/store/apps/details?id=com.app.UmarFarooq')); ?>" placeholder="https://play.google.com/store/apps/...">
                                </div>
                            </div>

                            <div class="row" style="margin-top:10px;">
                                <div class="form-group col-md-6">
                                    <label><i class="fa fa-file-text"></i> Roll No Slip Instructions</label>
                                    <textarea name="rollNo_slip_note" class="form-control" rows="5" placeholder="Instructions that will appear on roll number slips..."><?php echo e(get_setting('rollNo_slip_note')); ?></textarea>
                                </div>
                                <div class="form-group col-md-6">
                                    <label><i class="fa fa-calendar"></i> Datesheet Instructions</label>
                                    <textarea name="datesheet_instructions" class="form-control" rows="5" placeholder="Instructions that will appear on exam datesheets..."><?php echo e(get_setting('datesheet_instructions')); ?></textarea>
                                </div>
                                <div class="form-group col-md-12">
                                    <label><i class="fa fa-file-text-o"></i> Admission Form Instructions</label>
                                    <textarea name="admission_instructions" class="form-control" rows="6" placeholder="Instructions that will appear on admission forms..."><?php echo e(get_setting('admission_instructions')); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ================= AI ALERTS ================= -->
                    <div class="tab-pane" id="alerts">
                        <div class="form-section">
                            <h4><i class="fa fa-bell"></i> Automated System Alerts Configuration</h4>
                            <p class="help-text">Configure automated alerts that will be sent to parents and staff based on selected frequency.</p>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered alerts-table">
                                    <thead>
                                        <tr>
                                            <th style="width:50px; text-align:center;"><i class="fa fa-hashtag"></i> #</th>
                                            <th><i class="fa fa-bell"></i> Alert Title</th>
                                            <th style="width:200px;"><i class="fa fa-clock-o"></i> Frequency</th>
                                            <th style="width:140px;"><i class="fa fa-toggle-on"></i> Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alerts as $i => $a): ?>
                                        <?php
                                            $freq   = get_setting('ai_alert_' . $a['slug'] . '_freq', 'fortnightly');
                                            $active = get_setting('ai_alert_' . $a['slug'] . '_active', '1');
                                        ?>
                                        <tr>
                                            <td style="text-align:center;"><?php echo $i + 1; ?></td>
                                            <td style="font-weight:600;"><?php echo e($a['title']); ?></td>
                                            <td>
                                                <select name="freq_<?php echo e($a['slug']); ?>" class="form-control">
                                                    <option value="fortnightly" <?php echo $freq === 'fortnightly' ? 'selected' : ''; ?>>Fortnightly</option>
                                                    <option value="monthly" <?php echo $freq === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                                </select>
                                            </td>
                                            <td>
                                                <label class="switch-box">
                                                    <span class="switch"><input type="checkbox" name="active_<?php echo e($a['slug']); ?>" value="1" <?php echo $active === '1' ? 'checked' : ''; ?>><span class="slider"></span></span>
                                                    <span class="sw-state" style="color:<?php echo $active === '1' ? '#27AE60' : '#9CA3AF'; ?>;"><?php echo $active === '1' ? 'Active' : 'Inactive'; ?></span>
                                                </label>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="submit-bar">
                <button type="submit" class="btn btn-primary btn-lg" style="min-width:220px;"><i class="fa fa-save"></i> Save All Settings</button>
                <button type="reset" class="btn btn-default btn-lg" style="margin-left:12px;"><i class="fa fa-refresh"></i> Reset Form</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var logoInput = document.getElementById('logoFileInput');
    var preview = document.getElementById('logoPreview');
    if (logoInput && preview) {
        logoInput.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (!file) { return; }
            var reader = new FileReader();
            reader.onload = function (e) { preview.src = e.target.result; };
            reader.readAsDataURL(file);
        });
    }

    document.querySelectorAll('.switch').forEach(function (sw) {
        var input = sw.querySelector('input');
        if (!input) { return; }
        input.addEventListener('change', function () {
            var textEl = sw.parentElement.querySelector('.sw-state');
            if (textEl) {
                textEl.textContent = input.checked ? 'Active' : 'Inactive';
                textEl.style.color = input.checked ? '#27AE60' : '#9CA3AF';
            }
        });
    });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>