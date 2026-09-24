<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Fee Module Settings';

db_query("CREATE TABLE IF NOT EXISTS fee_discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    value DECIMAL(12,2) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

if (!function_exists('save_setting')) {
function save_setting($key, $value) {
    $st = db_prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $st->bind_param('ss', $key, $value);
    $st->execute();
}
}

function delete_setting($key) {
    $st = db_prepare("DELETE FROM settings WHERE setting_key = ?");
    $st->bind_param('s', $key);
    $st->execute();
}

function upload_image($field, $tag) {
    if (empty($_FILES[$field]['name'])) return false;
    $tmp = $_FILES[$field]['tmp_name'];
    if (!is_uploaded_file($tmp)) return false;
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return false;
    $dir = __DIR__ . '/uploads/bank_logo';
    if (!is_dir($dir)) { mkdir($dir, 0777, true); }
    $name = $tag . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . '/' . $name)) return false;
    return 'uploads/bank_logo/' . $name;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'Update_Fee_Details') {
        save_setting('fee_voucher_pay_date', trim($_POST['default_pay_date'] ?? ''));
        save_setting('fee_late_method', trim($_POST['fine_type'] ?? ''));
        save_setting('fee_late_amount', trim($_POST['late_fee_fine'] ?? '0'));
        save_setting('fee_default_method', trim($_POST['PiadBy'] ?? 'Cash'));
        save_setting('fee_allow_paydate_modify', trim($_POST['editable_paid_date'] ?? 'No'));
        save_setting('fee_show_history', trim($_POST['hide_fee_history'] ?? 'Show'));
        save_setting('fee_discount_reason', trim($_POST['display_discount_reason'] ?? 'Hide'));
        save_setting('fee_show_standard', trim($_POST['display_standard_fee'] ?? 'Show'));
        save_setting('fee_voucher_footer', trim($_POST['fee_vouc_note'] ?? ''));
        $message = 'Basic fee configuration saved successfully.';
    }

    if ($action === 'Update_Bank_Accounts') {
        $logo = upload_image('bank_logo', 'bank');
        if ($logo !== false) { save_setting('fee_bank_logo', $logo); }
        save_setting('fee_bank_account_title', trim($_POST['bank_accountitle'] ?? ''));
        save_setting('fee_bank_name', trim($_POST['bank_branch'] ?? ''));
        save_setting('fee_bank_account_no', trim($_POST['bank_accountno'] ?? ''));
        save_setting('fee_easypaisa_title', trim($_POST['easypaisa_account_title'] ?? ''));
        save_setting('fee_easypaisa_no', trim($_POST['easypaisa_account_no'] ?? ''));
        save_setting('fee_jazzcash_title', trim($_POST['jazzcash_account_title'] ?? ''));
        save_setting('fee_jazzcash_no', trim($_POST['jazzcash_account_no'] ?? ''));
        save_setting('fee_raast_id', trim($_POST['raast_id'] ?? ''));
        $message = 'Bank account details saved successfully.';
        if ($logo === false && !empty($_FILES['bank_logo']['name'])) {
            $error = 'Bank logo could not be uploaded. Please upload jpg/png image.';
        }
    }

    if ($action === 'Update_Fee_Heads') {
        $ids = $_POST['fee_head_Ids'] ?? [];
        $names = $_POST['fee_heads'] ?? [];
        $statuses = $_POST['heads_status'] ?? [];
        if (!is_array($ids)) $ids = [];
        if (!is_array($names)) $names = [];
        if (!is_array($statuses)) $statuses = [];
        $count = min(count($ids), count($names));
        for ($i = 0; $i < $count; $i++) {
            $hid = (int) $ids[$i];
            $name = trim($names[$i] ?? '');
            $status = ((int) ($statuses[$i] ?? 0)) === 1 ? 1 : 0;
            if ($hid <= 0) continue;
            $st = db_prepare("UPDATE fee_heads SET head_name = ?, status = ? WHERE head_id = ?");
            $st->bind_param('sii', $name, $status, $hid);
            $st->execute();
        }
        $message = 'Fee heads updated successfully.';
    }

    if ($action === 'discount_manager') {
        $name = trim($_POST['pkg_name'] ?? '');
        $type = ((int) ($_POST['discount_type'] ?? 0)) === 1 ? 'fixed' : 'percentage';
        $value = (float) ($_POST['discount_value'] ?? 0);
        $pkgup = (int) ($_POST['pkgup'] ?? 0);
        if ($name === '' || $value <= 0) {
            $error = 'Please provide a package name and a value greater than zero.';
        } elseif ($type === 'percentage' && $value > 100) {
            $error = 'Percentage value cannot exceed 100.';
        } elseif ($pkgup > 0) {
            $st = db_prepare("UPDATE fee_discounts SET name = ?, type = ?, value = ? WHERE id = ?");
            $st->bind_param('ssdi', $name, $type, $value, $pkgup);
            $st->execute();
            $message = 'Discount package updated successfully.';
        } else {
            $st = db_prepare("INSERT INTO fee_discounts (name, type, value, status) VALUES (?, ?, ?, 1)");
            $st->bind_param('ssd', $name, $type, $value);
            $st->execute();
            $message = 'Discount package created successfully.';
        }
    }

    if ($action === 'delete_discount') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = db_prepare("DELETE FROM fee_discounts WHERE id = ?");
            $st->bind_param('i', $id);
            $st->execute();
            $message = 'Discount package deleted successfully.';
        }
    }
}

function opt($key, $def) { return get_setting($key, $def); }
function selv($key, $val, $def) { return (opt($key, $def) === $val) ? 'selected' : ''; }

$feeHeads = [];
$res = db_query("SELECT head_id, head_name, status FROM fee_heads ORDER BY head_id");
while ($row = $res->fetch_assoc()) { $feeHeads[] = $row; }

$discounts = [];
$res = db_query("SELECT * FROM fee_discounts WHERE status=1 ORDER BY id DESC");
while ($row = $res->fetch_assoc()) { $discounts[] = $row; }

$editPkg = null;
if (isset($_GET['edit_pkg'])) {
    $st = db_prepare("SELECT * FROM fee_discounts WHERE id = ?");
    $st->bind_param('i', (int) $_GET['edit_pkg']);
    $st->execute();
    $res = $st->get_result();
    if ($row = $res->fetch_assoc()) { $editPkg = $row; }
}

$payMethods = ['Cash', 'Bank Transfer', 'JazzCash', 'EasyPaisa', 'Cheque', 'UBL Omni'];

include __DIR__ . '/includes/header.php';
?>
<style>
.fee-settings-nav { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:10px 14px; margin-bottom:16px; display:flex; gap:8px; flex-wrap:wrap; }
.fee-settings-nav a { padding:8px 16px; border-radius:9px; font-size:13px; font-weight:700; color:#374151; text-decoration:none; }
.fee-settings-nav a.active, .fee-settings-nav a:hover { background:#F59E0B; color:#fff; }
.fee-settings-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; margin-bottom:16px; overflow:hidden; }
.fee-settings-card > h4 { margin:0; padding:14px 18px; border-bottom:1px solid #EEF0F3; font-size:15px; font-weight:800; color:#111827; }
.fee-settings-card > .card-body { padding:18px; }
.fee-settings-card label { font-size:12px; font-weight:700; color:#374151; }
.fh-switch { position:relative; display:inline-block; width:44px; height:24px; vertical-align:middle; }
.fh-switch input { opacity:0; width:0; height:0; }
.fh-slider { position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:#CBD5E1; border-radius:999px; transition:.2s; }
.fh-slider:before { position:absolute; content:""; height:18px; width:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
.fh-switch input:checked + .fh-slider { background:#10B981; }
.fh-switch input:checked + .fh-slider:before { transform:translateX(20px); }
.fh-status-label { margin-left:8px; font-size:12px; font-weight:700; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-sliders"></i> Fee Module Settings</h3>
            <a href="<?php echo BASE_URL; ?>multi_fee_reports.php" class="btn btn-info" style="color:#fff;"><i class="fa fa-chart-bar"></i> Fee Analytics</a>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="fee-settings-nav">
            <a href="#institute" class="active" data-tab="institute"><i class="fa fa-cog"></i> Basic Fee Configuration</a>
            <a href="#accounts" data-tab="accounts"><i class="fa fa-university"></i> Bank Accounts</a>
            <a href="#feeHeads" data-tab="feeHeads"><i class="fa fa-list"></i> Manage Fee Heads</a>
            <a href="#discountManager" data-tab="discountManager"><i class="fa fa-percent"></i> Discount Manager</a>
        </div>

        <div class="fee-settings-tab" id="tab-institute">
            <form method="post" action="update_fee_settings.php" class="fee-settings-card">
                <input type="hidden" name="action" value="Update_Fee_Details">
                <h4><i class="fa fa-cog" style="color:#F59E0B;"></i> Basic Fee Configuration</h4>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Default Voucher Payment Date</label>
                                <select name="default_pay_date" class="form-control">
                                    <?php for ($d = 1; $d <= 31; $d++): ?>
                                        <option value="<?php echo $d; ?>" <?php echo (int) opt('fee_voucher_pay_date', '10') === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <small style="color:#9CA3AF;">Day of month used on printed vouchers.</small>
                            </div>
                        </div>
                        <div class="col-md-4 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Late Fee Calculation Method</label>
                                <select name="fine_type" class="form-control">
                                    <option value="None" <?php echo selv('fee_late_method', 'None', 'Monthly'); ?>>None</option>
                                    <option value="Fixed" <?php echo selv('fee_late_method', 'Fixed', 'Monthly'); ?>>Fixed Amount</option>
                                    <option value="Percentage" <?php echo selv('fee_late_method', 'Percentage', 'Monthly'); ?>>Percentage of Fee</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Late Fee Amount (PKR)</label>
                                <input type="number" step="0.01" min="0" name="late_fee_fine" value="<?php echo e(get_setting('fee_late_amount', '100')); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Default Payment Method</label>
                                <select name="PiadBy" class="form-control">
                                    <?php foreach ($payMethods as $pm): ?>
                                        <option value="<?php echo e($pm); ?>" <?php echo selv('fee_default_method', $pm, 'Cash'); ?>><?php echo e($pm); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Allow Payment Date Modification</label>
                                <select name="editable_paid_date" class="form-control">
                                    <option value="Yes" <?php echo selv('fee_allow_paydate_modify', 'Yes', 'No'); ?>>Yes</option>
                                    <option value="No" <?php echo selv('fee_allow_paydate_modify', 'No', 'No'); ?>>No</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Show Payment History in Voucher</label>
                                <select name="hide_fee_history" class="form-control">
                                    <option value="Show" <?php echo selv('fee_show_history', 'Show', 'Show'); ?>>Show</option>
                                    <option value="Hide" <?php echo selv('fee_show_history', 'Hide', 'Show'); ?>>Hide</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Display Discount Reason in Voucher</label>
                                <select name="display_discount_reason" class="form-control">
                                    <option value="Show" <?php echo selv('fee_discount_reason', 'Show', 'Hide'); ?>>Show</option>
                                    <option value="Hide" <?php echo selv('fee_discount_reason', 'Hide', 'Hide'); ?>>Hide</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Show Standard Fee in Voucher</label>
                                <select name="display_standard_fee" class="form-control">
                                    <option value="Show" <?php echo selv('fee_show_standard', 'Show', 'Show'); ?>>Show</option>
                                    <option value="Hide" <?php echo selv('fee_show_standard', 'Hide', 'Show'); ?>>Hide</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Fee Voucher Footer Note</label>
                                <textarea name="fee_vouc_note" rows="3" class="form-control" placeholder="Text shown at the bottom of printed fee vouchers..."><?php echo e(get_setting('fee_voucher_footer', '')); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-12 col-xs-12" style="padding:8px;">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Configuration</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="fee-settings-tab" id="tab-accounts" style="display:none;">
            <form method="post" action="update_fee_settings.php" enctype="multipart/form-data" class="fee-settings-card">
                <input type="hidden" name="action" value="Update_Bank_Accounts">
                <h4><i class="fa fa-university" style="color:#F59E0B;"></i> Bank Accounts</h4>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Bank Logo</label>
                                <?php $bankLogo = get_setting('fee_bank_logo', ''); ?>
                                <?php if ($bankLogo !== ''): ?>
                                    <div style="margin-bottom:8px;"><img src="<?php echo BASE_URL . e($bankLogo); ?>" style="max-height:56px; border:1px solid #E5E7EB; border-radius:8px; padding:4px; background:#fff;"></div>
                                <?php endif; ?>
                                <input type="file" name="bank_logo" class="form-control" accept="image/*">
                                <input type="hidden" name="old_bank_logo" value="<?php echo e($bankLogo); ?>">
                            </div>
                        </div>
                        <div class="col-md-6 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Bank Account Title</label>
                                <input type="text" name="bank_accountitle" value="<?php echo e(get_setting('fee_bank_account_title', '')); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Bank Name</label>
                                <input type="text" name="bank_branch" value="<?php echo e(get_setting('fee_bank_name', '')); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Bank Account Number</label>
                                <input type="text" name="bank_accountno" value="<?php echo e(get_setting('fee_bank_account_no', '')); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Raast ID</label>
                                <input type="text" name="raast_id" value="<?php echo e(get_setting('fee_raast_id', '')); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Easypaisa Account Title</label>
                                <input type="text" name="easypaisa_account_title" value="<?php echo e(get_setting('fee_easypaisa_title', '')); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Easypaisa Account Number</label>
                                <input type="text" name="easypaisa_account_no" value="<?php echo e(get_setting('fee_easypaisa_no', '')); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>JazzCash Account Title</label>
                                <input type="text" name="jazzcash_account_title" value="<?php echo e(get_setting('fee_jazzcash_title', '')); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>JazzCash Account Number</label>
                                <input type="text" name="jazzcash_account_no" value="<?php echo e(get_setting('fee_jazzcash_no', '')); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-12 col-xs-12" style="padding:8px;">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Bank Accounts</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="fee-settings-tab" id="tab-feeHeads" style="display:none;">
            <form method="post" action="update_fee_settings.php" class="fee-settings-card">
                <input type="hidden" name="action" value="Update_Fee_Heads">
                <h4><i class="fa fa-list" style="color:#F59E0B;"></i> Manage Fee Heads</h4>
                <div class="card-body">
                    <table class="table table-striped table-bordered" style="width:100%; background:#fff;">
                        <thead>
                            <tr style="background:#F9FAFB;">
                                <th style="width:6%;">S.No</th>
                                <th style="width:22%;">Fee Head</th>
                                <th style="width:42%;">Customized Name</th>
                                <th style="width:30%;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($feeHeads as $fh): $enabled = (int) $fh['status'] === 1; ?>
                                <tr>
                                    <td class="fh-serial"><?php echo $i++; ?></td>
                                    <td class="fh-feehead-name"><strong><?php echo e($fh['head_name']); ?></strong></td>
                                    <td>
                                        <input type="hidden" name="fee_head_Ids[]" value="<?php echo $fh['head_id']; ?>">
                                        <input type="text" name="fee_heads[]" value="<?php echo e($fh['head_name']); ?>" class="form-control" maxlength="15">
                                    </td>
                                    <td>
                                        <div class="fh-toggle-wrap" style="display:flex; align-items:center;">
                                            <label class="fh-switch">
                                                <input type="checkbox" class="fh-toggle-input" <?php echo $enabled ? 'checked' : ''; ?>>
                                                <span class="fh-slider"></span>
                                            </label>
                                            <span class="fh-status-label <?php echo $enabled ? 'on' : ''; ?>" style="color:<?php echo $enabled ? '#16A34A' : '#EF4444'; ?>;"><?php echo $enabled ? 'Enabled' : 'Disabled'; ?></span>
                                            <input type="hidden" name="heads_status[]" class="fh-toggle-value" value="<?php echo $enabled ? '1' : '0'; ?>">
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Fee Heads</button>
                </div>
            </form>
        </div>

        <div class="fee-settings-tab" id="tab-discountManager" style="display:none;">
            <form method="post" action="update_fee_settings.php" class="fee-settings-card">
                <input type="hidden" name="action" value="discount_manager">
                <input type="hidden" name="pkgup" value="<?php echo $editPkg ? (int) $editPkg['id'] : ''; ?>">
                <h4><i class="fa fa-percent" style="color:#F59E0B;"></i> <?php echo $editPkg ? 'Edit Discount Package' : 'Create Discount Package'; ?></h4>
                <div class="card-body">
                    <div class="row" style="align-items:center;">
                        <div class="col-md-4 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Package Name</label>
                                <input type="text" name="pkg_name" value="<?php echo $editPkg ? e($editPkg['name']) : ''; ?>" class="form-control" placeholder="Enter package name">
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Type</label>
                                <select name="discount_type" class="form-control">
                                    <option value="">Choose...</option>
                                    <option value="0" <?php echo $editPkg && $editPkg['type'] === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                                    <option value="1" <?php echo $editPkg && $editPkg['type'] === 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Percentage/Amount</label>
                                <input type="number" step="0.01" min="0" name="discount_value" value="<?php echo $editPkg ? e($editPkg['value']) : ''; ?>" class="form-control" placeholder="Enter amount">
                            </div>
                        </div>
                        <div class="col-md-2 col-xs-12" style="padding:8px;">
                            <button type="submit" class="btn btn-primary" style="width:100%;">Save</button>
                        </div>
                        <?php if ($editPkg): ?>
                            <div class="col-md-12 col-xs-12" style="padding:8px;">
                                <a href="update_fee_settings.php" class="btn btn-default">Cancel Edit</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <div class="fee-settings-card">
                <h4><i class="fa fa-table" style="color:#F59E0B;"></i> Discount Package List</h4>
                <div class="card-body" style="padding:12px 18px 18px 18px;">
                    <table class="table table-striped table-bordered" style="width:100%; background:#fff;">
                        <thead>
                            <tr style="background:#F9FAFB;">
                                <th style="text-align:center; width:5%;">S.No</th>
                                <th style="width:40%;">Package Name</th>
                                <th style="text-align:center; width:20%;">Type</th>
                                <th style="text-align:center; width:20%;">Percentage/Amount</th>
                                <th style="text-align:center; width:15%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($discounts) === 0): ?>
                                <tr><td colspan="5" style="text-align:center; color:#6B7280; padding:24px;">No discount packages created yet.</td></tr>
                            <?php endif; ?>
                            <?php $i = 1; foreach ($discounts as $d): ?>
                                <tr>
                                    <td style="text-align:center;"><?php echo $i++; ?></td>
                                    <td><strong><?php echo e($d['name']); ?></strong></td>
                                    <td style="text-align:center;">
                                        <span class="status-badge" style="padding:3px 10px; border-radius:999px; font-size:12px; font-weight:700; background:<?php echo $d['type'] === 'percentage' ? '#E0E7FF' : '#FEF3C7'; ?>; color:<?php echo $d['type'] === 'percentage' ? '#4338CA' : '#B45309'; ?>;"><?php echo $d['type'] === 'percentage' ? 'Percentage' : 'Fixed'; ?></span>
                                    </td>
                                    <td style="text-align:center;"><?php echo $d['type'] === 'percentage' ? number_format($d['value'], 2) . ' %' : get_setting('currency_symbol', 'Rs.') . number_format($d['value'], 2); ?></td>
                                    <td style="text-align:center;">
                                        <a href="update_fee_settings.php?edit_pkg=<?php echo $d['id']; ?>" class="btn btn-xs btn-primary"><i class="fa fa-pencil"></i> Edit</a>
                                        <form method="post" action="update_fee_settings.php" style="display:inline;" onsubmit="return confirm('Delete this discount package?');">
                                            <input type="hidden" name="action" value="delete_discount">
                                            <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                            <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                                        </form>
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

<script>
document.querySelectorAll('.fh-toggle-input').forEach(function(inp){
    inp.addEventListener('change', function(){
        var wrap = this.closest('.fh-toggle-wrap');
        var label = wrap.querySelector('.fh-status-label');
        var hidden = wrap.querySelector('.fh-toggle-value');
        var on = this.checked;
        hidden.value = on ? '1' : '0';
        label.textContent = on ? 'Enabled' : 'Disabled';
        label.style.color = on ? '#16A34A' : '#EF4444';
    });
});
document.querySelectorAll('.fee-settings-nav a').forEach(function(link){
    link.addEventListener('click', function(e){
        e.preventDefault();
        document.querySelectorAll('.fee-settings-nav a').forEach(function(a){ a.classList.remove('active'); });
        this.classList.add('active');
        var tab = this.dataset.tab;
        document.querySelectorAll('.fee-settings-tab').forEach(function(t){ t.style.display = 'none'; });
        document.getElementById('tab-' + tab).style.display = '';
        if (history.replaceState) history.replaceState(null, '', '#' + tab);
    });
});
(function(){
    var hash = location.hash.replace('#', '');
    if (hash && document.getElementById('tab-' + hash)) {
        document.querySelectorAll('.fee-settings-nav a').forEach(function(a){ a.classList.toggle('active', a.dataset.tab === hash); });
        document.querySelectorAll('.fee-settings-tab').forEach(function(t){ t.style.display = 'none'; });
        document.getElementById('tab-' + hash).style.display = '';
    }
})();
</script>

<style>.status-badge{display:inline-block;}</style>

<?php include __DIR__ . '/includes/footer.php'; ?>