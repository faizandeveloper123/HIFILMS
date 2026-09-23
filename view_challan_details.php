<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'View Challans';

db_query("CREATE TABLE IF NOT EXISTS class_heads (class_head_id INT AUTO_INCREMENT PRIMARY KEY, class_head_name VARCHAR(150) NOT NULL, status TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
db_query("ALTER TABLE classes ADD COLUMN IF NOT EXISTS class_head_id INT DEFAULT NULL");
db_query("ALTER TABLE fee_payments ADD COLUMN IF NOT EXISTS discount DECIMAL(12,2) NOT NULL DEFAULT 0");
db_query("ALTER TABLE fee_challan_items ADD COLUMN IF NOT EXISTS discount DECIMAL(12,2) NOT NULL DEFAULT 0");

$seed = db_query("SELECT COUNT(*) c FROM class_heads")->fetch_assoc()['c'];
if ((int)$seed === 0) {
    foreach (['Hajvery Campus', 'Main Campus', 'Pharm-D', 'Modern Edu'] as $hn) {
        $st = db_prepare("INSERT IGNORE INTO class_heads (class_head_name, status) VALUES (?, 1)");
        $st->bind_param('s', $hn);
        $st->execute();
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'SavePayment') {
        $cid = (int) ($_POST['challan_id'] ?? 0);
        $amount = (float) trim($_POST['amount'] ?? '0');
        $discount = (float) trim($_POST['discount'] ?? '0');
        $method = trim($_POST['payment_method'] ?? 'Cash');
        $paid_date = trim($_POST['paid_date'] ?? '');
        if ($cid <= 0 || $amount <= 0) {
            $error = 'Invalid payment request. Please enter a valid amount.';
        } else {
            $st = db_prepare("SELECT * FROM fee_challans WHERE challan_id=?");
            $st->bind_param('i', $cid);
            $st->execute();
            $ch = $st->get_result()->fetch_assoc();
            if (!$ch) {
                $error = 'Challan not found.';
            } else {
                $due = (float) $ch['total_amount'] - (float) $ch['paid_amount'];
                if ($amount > $due + 0.01) {
                    $error = 'Payment amount cannot exceed due amount (' . get_setting('currency_symbol', 'Rs.') . number_format($due, 2) . ').';
                } else {
                    $new_paid = (float) $ch['paid_amount'] + $amount;
                    $new_status = abs($new_paid - (float) $ch['total_amount']) < 0.01 ? 'paid' : 'partial';
                    $uid = (int) ($_SESSION['user_id'] ?? 0);
                    $pd = $paid_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $paid_date) ? $paid_date : date('Y-m-d');
                    $st2 = db_prepare("INSERT INTO fee_payments (challan_id, amount, discount, payment_method, received_by, created_at) VALUES (?, ?, ?, ?, ?, ?)");
                    $st2->bind_param('iddsis', $cid, $amount, $discount, $method, $uid, $pd . ' ' . date('H:i:s'));
                    $st2->execute();
                    $st3 = db_prepare("UPDATE fee_challans SET paid_amount=?, status=? WHERE challan_id=?");
                    $st3->bind_param('dsi', $new_paid, $new_status, $cid);
                    $st3->execute();
                    $message = 'Payment of ' . get_setting('currency_symbol', 'Rs.') . number_format($amount, 2) . ' recorded successfully!';
                }
            }
        }
    }

    if ($action === 'UpdateChallan') {
        $cid = (int) ($_POST['challan_id'] ?? 0);
        $new_total = 0.0;
        if ($cid > 0) {
            $item_ids = $_POST['item_ids'] ?? [];
            $amounts = $_POST['amounts'] ?? [];
            $discounts = $_POST['discounts'] ?? [];
            $new_items = $_POST['new_head_ids'] ?? [];
            $new_heads = $_POST['new_head_amounts'] ?? [];
            $new_head_discs = $_POST['new_head_discounts'] ?? [];
            if (!is_array($item_ids)) $item_ids = [];
            if (!is_array($amounts)) $amounts = [];
            if (!is_array($discounts)) $discounts = [];
            if (!is_array($new_items)) $new_items = [];

            $st = db_prepare("SELECT paid_amount FROM fee_challans WHERE challan_id=?");
            $st->bind_param('i', $cid);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            if (!$row) { $error = 'Challan not found.'; }
            else {
                $paid_current = (float) $row['paid_amount'];
                $count = min(count($item_ids), count($amounts), count($discounts));
                for ($i = 0; $i < $count; $i++) {
                    $iid = (int) $item_ids[$i];
                    $amt = (float) trim($amounts[$i] ?? '0');
                    $dsc = (float) trim($discounts[$i] ?? '0');
                    if ($dsc > $amt) $dsc = $amt;
                    $new_total += ($amt - $dsc);
                    $st2 = db_prepare("UPDATE fee_challan_items SET amount=?, discount=? WHERE item_id=? AND challan_id=?");
                    $st2->bind_param('ddii', $amt, $dsc, $iid, $cid);
                    $st2->execute();
                }
                foreach ($new_items as $hid) {
                    $hid = (int) $hid;
                    if ($hid <= 0) continue;
                    $key = array_search($hid, $new_items);
                    $amt = ($key !== false && isset($new_heads[$key])) ? (float) trim($new_heads[$key]) : 0;
                    $dsc = ($key !== false && isset($new_head_discs[$key])) ? (float) trim($new_head_discs[$key]) : 0;
                    if ($dsc > $amt) $dsc = $amt;
                    $net = $amt - $dsc;
                    if ($net > 0) {
                        $new_total += $net;
                        $hn = '';
                        $hr = db_query("SELECT head_name FROM fee_heads WHERE head_id=$hid");
                        if ($hr && $hrow = $hr->fetch_assoc()) { $hn = $hrow['head_name']; }
                        $st2 = db_prepare("INSERT INTO fee_challan_items (challan_id, head_id, description, amount, discount) VALUES (?, ?, ?, ?, ?)");
                        $st2->bind_param('iisdd', $cid, $hid, $hn, $amt, $dsc);
                        $st2->execute();
                    }
                }
                if ($new_total < $paid_current - 0.01) {
                    $error = 'New total cannot be less than already paid amount.';
                } else {
                    $st3 = db_prepare("UPDATE fee_challans SET total_amount=?, status=? WHERE challan_id=?");
                    $new_status = abs($new_total - $paid_current) < 0.01 && $paid_current > 0 ? 'paid' : ($paid_current > 0 ? 'partial' : 'unpaid');
                    $st3->bind_param('dsi', $new_total, $new_status, $cid);
                    $st3->execute();
                    $message = 'Fee challan updated successfully!';
                }
            }
        }
    }

    if ($action === 'DeleteChallan') {
        $cid = (int) ($_POST['challan_id'] ?? 0);
        if ($cid > 0) {
            $res = db_query("SELECT COUNT(*) c FROM fee_payments WHERE challan_id=$cid")->fetch_assoc();
            if ((int) $res['c'] > 0) {
                $error = 'Cannot delete a challan that has payments recorded against it.';
            } else {
                $st = db_prepare("DELETE FROM fee_challans WHERE challan_id=?");
                $st->bind_param('i', $cid);
                $st->execute();
                $message = 'Challan deleted successfully.';
            }
        }
    }
}

$sessions = [];
for ($y = 2018; $y <= 2030; $y++) { $sessions[] = $y . '-' . substr($y + 1, -2); }
$sel_session = $_GET['session'] ?? get_setting('session_year', '2026-2027');
if (!in_array($sel_session, $sessions)) { $sel_session = get_setting('session_year', '2026-2027'); }

$classHeads = [];
$res = db_query("Select class_head_id, class_head_name FROM class_heads WHERE status=1 ORDER BY class_head_name");
while ($row = $res->fetch_assoc()) { $classHeads[] = $row; }

$sel_head = (int) ($_GET['class_head'] ?? 0);
$classes = [];
$res = db_query("SELECT c.class_id, c.class_name, c.class_head_id FROM classes c LEFT JOIN class_heads h ON c.class_head_id = h.class_head_id ORDER BY c.class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sel_class = (int) ($_GET['class_id'] ?? 0);
$sel_section = (int) ($_GET['section'] ?? 0);
$sel_month = trim($_GET['monthYear'] ?? '');
$sel_fee = $_GET['fee'] ?? 'All';
$focus_challan = (int) ($_GET['challan_id'] ?? 0);

$sections = [];
if ($sel_class > 0) {
    $st = db_prepare("SELECT section_id, section_name FROM sections WHERE class_id=? ORDER BY section_name");
    $st->bind_param('i', $sel_class);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) { $sections[] = $row; }
}

$where = [];
$params = [];
$types = '';

if ($sel_session !== '' && preg_match('/^(\d{4})-(\d{2})$/', $sel_session, $sm)) {
    $y1 = (int) $sm[1];
    $where[] = "(c.year = ? OR c.year = ?)";
    $params[] = $y1; $params[] = $y1 + 1;
    $types .= 'ii';
}
if ($sel_head > 0) {
    $where[] = "cl.class_head_id = ?";
    $params[] = $sel_head;
    $types .= 'i';
}
if ($sel_class > 0) {
    $where[] = "c.class_id = ?";
    $params[] = $sel_class;
    $types .= 'i';
}
if ($sel_section > 0) {
    $where[] = "s.section_id = ?";
    $params[] = $sel_section;
    $types .= 'i';
}
if ($sel_month !== '' && preg_match('#^(\d{1,2})/(\d{4})$#', $sel_month, $mm)) {
    $month_num = (int) $mm[1];
    $month_year = (int) $mm[2];
    if ($month_num >= 1 && $month_num <= 12) {
        $where[] = "CAST(c.month AS UNSIGNED) = ? AND c.year = ?";
        $params[] = $month_num;
        $params[] = $month_year;
        $types .= 'ii';
    }
}
if ($sel_fee === 'PAID') {
    $where[] = "c.status = 'paid'";
} elseif ($sel_fee === 'UNPAID') {
    $where[] = "c.status IN ('unpaid','partial')";
}
if ($focus_challan > 0) {
    $where[] = "c.challan_id = ?";
    $params[] = $focus_challan;
    $types .= 'i';
}

$sql = "SELECT c.*, s.first_name, s.father_name, s.gr_no, s.phone, s.status student_status,
        cl.class_name, sec.section_name
        FROM fee_challans c
        LEFT JOIN students s ON c.student_id = s.student_id
        LEFT JOIN classes cl ON c.class_id = cl.class_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id";
if (count($where) > 0) { $sql .= " WHERE " . implode(' AND ', $where); }
$sql .= " ORDER BY c.year DESC, c.created_at DESC, c.challan_id DESC LIMIT 600";

$challans = [];
if (count($params) > 0) {
    $st = db_prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
} else {
    $res = db_query($sql);
}
while ($row = $res->fetch_assoc()) { $challans[] = $row; }

$stats = ['total' => 0, 'paid' => 0, 'partial' => 0, 'unpaid' => 0];
foreach ($challans as $c) {
    $stats['total']++;
    if ($c['status'] === 'paid') $stats['paid']++;
    elseif ($c['status'] === 'partial') $stats['partial']++;
    else $stats['unpaid']++;
}

$feeHeads = [];
$res = db_query("SELECT head_id, head_name, amount FROM fee_heads WHERE status=1 ORDER BY head_id");
while ($row = $res->fetch_assoc()) { $feeHeads[] = $row; }

$items_by_challan = [];
$res = db_query("SELECT * FROM fee_challan_items ORDER BY challan_id, item_id");
while ($row = $res->fetch_assoc()) {
    $items_by_challan[$row['challan_id']][] = $row;
}

$history_content = [];
foreach ($challans as $c) {
    $sid = (int) $c['student_id'];
    if (isset($history_content[$sid])) continue;
    $html = '<div style="padding:4px 2px;font-size:13px;">';
    $html .= '<div class="fee-student-info-bar" style="display:inline-flex;gap:12px;margin-bottom:10px;flex-wrap:wrap;">';
    $html .= '<span class="fee-info-chip"><i class="fa fa-id-badge"></i> GR# ' . e($c['gr_no'] ?? '-') . '</span>';
    $html .= '<span class="fee-info-chip"><i class="fa fa-graduation-cap"></i> ' . e($c['class_name'] ?? '-') . '</span>';
    $html .= '<span class="fee-info-chip"><i class="fa fa-phone"></i> ' . e($c['phone'] ?? '-') . '</span>';
    $html .= '</div>';
    $html .= '<table class="table table-striped table-bordered" style="background:#fff;font-size:13px;">';
    $html .= '<thead><tr><th>Challan No</th><th>Month</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th></tr></thead><tbody>';
    $st2 = db_prepare("SELECT * FROM fee_challans WHERE student_id=? ORDER BY year, challan_id");
    $st2->bind_param('i', $sid);
    $st2->execute();
    $res2 = $st2->get_result();
    while ($rc = $res2->fetch_assoc()) {
        $due = (float) $rc['total_amount'] - (float) $rc['paid_amount'];
        $stt = $rc['status'];
        $badge = 'background:#FEE2E2;color:#DC2626;';
        if ($stt === 'partial') $badge = 'background:#FFF7E0;color:#F59E0B;';
        if ($stt === 'paid') $badge = 'background:#DCFCE7;color:#16A34A;';
        $html .= '<tr>';
        $html .= '<td><strong>' . e($rc['challan_no']) . '</strong></td>';
        $html .= '<td>' . e($rc['month']) . ' / ' . e($rc['year']) . '</td>';
        $html .= '<td>' . number_format($rc['total_amount'], 2) . '</td>';
        $html .= '<td style="color:#16A34A;">' . number_format($rc['paid_amount'], 2) . '</td>';
        $html .= '<td style="color:#DC2626;">' . number_format($due, 2) . '</td>';
        $html .= '<td><span class="status-badge" style="' . $badge . '">' . ucfirst($stt) . '</span></td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $html .= '</div>';
    $history_content[$sid] = $html;
}

$update_content = [];
foreach ($challans as $c) {
    $cid = (int) $c['challan_id'];
    if (isset($update_content[$cid])) continue;
    $ch_items = $items_by_challan[$cid] ?? [];
    $html = '<div style="padding:2px;"><table class="table table-bordered" style="background:#fff;font-size:13px;margin-bottom:8px;">';
    $html .= '<thead><tr><th style="width:30%;">Fee Head</th><th style="width:22%;">Amount</th><th style="width:22%;">Discount</th><th>Net Amount</th></tr></thead><tbody>';
    foreach ($ch_items as $it) {
        $html .= '<tr class="edit-fee-row">';
        $html .= '<input type="hidden" name="item_ids[]" value="' . (int) $it['item_id'] . '">';
        $html .= '<td>' . e($it['description'] ?: 'Item') . '</td>';
        $html .= '<td><input type="number" min="0" step="0.01" class="form-control edit_fee_head_amount" name="amounts[]" value="' . e($it['amount']) . '"></td>';
        $html .= '<td><input type="number" min="0" step="0.01" class="form-control edit_discount" name="discounts[]" value="' . e($it['discount'] ?? 0) . '"></td>';
        $html .= '<td><input type="number" readonly tabindex="-1" class="form-control edit_net_amount" value="' . ((float)$it['amount'] - (float)($it['discount'] ?? 0)) . '"></td>';
        $html .= '</tr>';
    }
    if (count($ch_items) === 0) {
        $html .= '<tr><td colspan="4" style="text-align:center;color:#6B7280;padding:14px;">No fee heads on this challan.</td></tr>';
    }
    $html .= '<tr style="background:#F9FAFB;font-weight:700;"><td colspan="2" style="text-align:right;">Total Payable</td><td></td><td><input type="number" readonly tabindex="-1" class="form-control" id="edit_total_payable" value="' . e($c['total_amount']) . '" style="font-weight:700;"></td></tr>';
    $html .= '</tbody></table>';
    $html .= '<div class="form-group"><label style="font-size:12px;">Add Fee Head (optional)</label>';
    $html .= '<select class="form-control" name="new_head_ids[]" style="margin-bottom:6px;"><option value="">Select fee head to add</option>';
    foreach ($feeHeads as $fh) {
        $html .= '<option value="' . $fh['head_id'] . '">' . e($fh['head_name']) . '</option>';
    }
    $html .= '</select></div></div>';
    $update_content[$cid] = $html;
}

include __DIR__ . '/includes/header.php';
?>
<style>
.fee-filter-panel { background:#fff; border:1px solid #E5E7EB; border-radius:14px; margin-bottom:16px; overflow:hidden; }
.fee-filter-panel > .panel-heading { background:#fff; border-bottom:1px solid #EEF0F3; padding:14px 16px; }
.fee-filter-panel > .panel-heading h4 { margin:0; font-size:16px; font-weight:800; color:#111827; }
.fee-filter-panel > .panel-body { padding:14px 16px; }
.fee-filter-fields-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
.fee-filter-field { flex:1 1 180px; min-width:170px; }
.fee-filter-field label { font-size:12px; font-weight:700; color:#374151; margin-bottom:4px; display:block; }
.fee-filter-field .inputheight, .fee-filter-btn-col .inputheight { height:40px; border-radius:9px; }
.fee-filter-btn-col { flex:0 0 auto; }
.fee-filter-btn-col button { height:40px; }
.fee-stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:16px; }
.stat-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; display:flex; align-items:center; gap:14px; border-left:5px solid #6366F1; }
.stat-card .stat-icon { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; }
.stat-card .stat-label { font-size:12px; color:#6B7280; text-transform:uppercase; letter-spacing:.3px; }
.stat-card .stat-value { font-size:24px; font-weight:800; color:#111827; line-height:1.1; }
.stat-total { border-left-color:#6366F1; } .stat-total .stat-icon { background:#E0E7FF; color:#4338CA; }
.stat-paid { border-left-color:#10B981; } .stat-paid .stat-icon { background:#D1FAE5; color:#059669; }
.stat-partial { border-left-color:#F59E0B; } .stat-partial .stat-icon { background:#FEF3C7; color:#D97706; }
.stat-unpaid { border-left-color:#EF4444; } .stat-unpaid .stat-icon { background:#FEE2E2; color:#DC2626; }
.status-badge { padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; }
.fee-empty-state { text-align:center; color:#6B7280; padding:50px 20px; background:#fff; border:1px solid #E5E7EB; border-radius:14px; }
.fee-empty-state i { font-size:42px; color:#D1D5DB; margin-bottom:12px; }
.fee-info-chip { background:#F3F4F6; color:#374151; border-radius:999px; padding:4px 12px; font-size:12px; font-weight:600; }
.fee-student-info-bar { display:flex; gap:8px; flex-wrap:wrap; }
@media (max-width:900px){ .fee-stats-row { grid-template-columns:repeat(2,1fr); } }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-eye"></i> View Challans <span style="font-size:14px; color:#6B7280;">(<?php echo count($challans); ?> records)</span></h3>
            <a href="<?php echo BASE_URL; ?>monthly_challan.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-plus"></i> Create Challan</a>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <form method="get" action="view_challan_details.php" class="fee-filter-panel">
            <div class="panel-heading"><h4><i class="fa fa-filter" style="color:#F59E0B;"></i> Challan Filters</h4></div>
            <div class="panel-body">
                <div class="fee-filter-fields-row">
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Session</label>
                            <select name="session" class="form-control inputheight">
                                <?php foreach ($sessions as $sv): ?>
                                    <option value="<?php echo e($sv); ?>" <?php echo $sel_session === $sv ? 'selected' : ''; ?>><?php echo e($sv); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Course/Class Head</label>
                            <select name="class_head" class="form-control inputheight">
                                <option value="0">All</option>
                                <?php foreach ($classHeads as $ch): ?>
                                    <option value="<?php echo $ch['class_head_id']; ?>" <?php echo $sel_head === (int)$ch['class_head_id'] ? 'selected' : ''; ?>><?php echo e($ch['class_head_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Course/Class</label>
                            <select name="class_id" class="form-control inputheight" onchange="this.form.submit()">
                                <option value="0">All</option>
                                <?php foreach ($classes as $cl): ?>
                                    <option value="<?php echo $cl['class_id']; ?>" <?php echo $sel_class === (int)$cl['class_id'] ? 'selected' : ''; ?>><?php echo e($cl['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Section</label>
                            <select name="section" class="form-control inputheight">
                                <option value="0">All</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo $sec['section_id']; ?>" <?php echo $sel_section === (int)$sec['section_id'] ? 'selected' : ''; ?>><?php echo e($sec['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Challan Month (MM/YYYY)</label>
                            <input type="text" class="form-control inputheight" name="monthYear" placeholder="MM/YYYY" value="<?php echo e($sel_month); ?>">
                        </div>
                    </div>
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Fee Status</label>
                            <select name="fee" class="form-control inputheight">
                                <option value="All" <?php echo $sel_fee === 'All' ? 'selected' : ''; ?>>All</option>
                                <option value="PAID" <?php echo $sel_fee === 'PAID' ? 'selected' : ''; ?>>PAID</option>
                                <option value="UNPAID" <?php echo $sel_fee === 'UNPAID' ? 'selected' : ''; ?>>UNPAID</option>
                            </select>
                        </div>
                    </div>
                    <div class="fee-filter-btn-col">
                        <div class="form-group" style="margin-bottom:0;">
                            <button type="submit" class="btn btn-primary inputheight"><i class="fa fa-search"></i> Filter</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="fee-stats-row">
            <div class="stat-card stat-total"><div class="stat-icon"><i class="fa fa-file-text-o"></i></div><div><div class="stat-label">Total Challans</div><div class="stat-value"><?php echo $stats['total']; ?></div></div></div>
            <div class="stat-card stat-paid"><div class="stat-icon"><i class="fa fa-check-circle"></i></div><div><div class="stat-label">Paid Challans</div><div class="stat-value"><?php echo $stats['paid']; ?></div></div></div>
            <div class="stat-card stat-partial"><div class="stat-icon"><i class="fa fa-clock-o"></i></div><div><div class="stat-label">Partial Challans</div><div class="stat-value"><?php echo $stats['partial']; ?></div></div></div>
            <div class="stat-card stat-unpaid"><div class="stat-icon"><i class="fa fa-times-circle"></i></div><div><div class="stat-label">Unpaid Challans</div><div class="stat-value"><?php echo $stats['unpaid']; ?></div></div></div>
        </div>

        <?php if (count($challans) === 0): ?>
            <div class="fee-empty-state">
                <i class="fa fa-filter"></i>
                <p style="margin:0;font-size:15px;font-weight:600;color:#374151;">No challans found.</p>
                <span>Adjust the filters above and click <strong>Filter</strong> to view fee challans.</span>
            </div>
        <?php else: ?>
        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <table class="table table-hover table-bordered" id="listofstudents" style="width:100%; background:#fff; margin-bottom:0; font-size:13px;">
                <thead>
                    <tr style="background:#F9FAFB;">
                        <th>S.No</th>
                        <th>Section</th>
                        <th>Month</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th style="width:200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($challans as $c): $due = (float)$c['total_amount'] - (float)$c['paid_amount']; $badge = 'background:#FEE2E2;color:#DC2626;'; if ($c['status'] === 'partial') $badge = 'background:#FFF7E0;color:#F59E0B;'; if ($c['status'] === 'paid') $badge = 'background:#DCFCE7;color:#16A34A;'; ?>
                        <tr id="row-<?php echo $c['challan_id']; ?>">
                            <td><?php echo $i++; ?></td>
                            <td><?php echo e($c['section_name'] ?? '-'); ?></td>
                            <td><?php echo e($c['month']) . ' / ' . e($c['year']); ?></td>
                            <td style="font-weight:700;"><?php echo get_setting('currency_symbol', 'Rs.') . number_format($c['total_amount'], 2); ?></td>
                            <td style="color:#16A34A; font-weight:700;"><?php echo number_format($c['paid_amount'], 2); ?></td>
                            <td style="color:<?php echo $due > 0 ? '#DC2626' : '#16A34A'; ?>; font-weight:700;"><?php echo number_format($due, 2); ?></td>
                            <td><span class="status-badge" style="<?php echo $badge; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <?php if ($due > 0): ?>
                                        <button class="btn btn-success btn-xs pay-btn" title="Receive Payment" data-id="<?php echo $c['challan_id']; ?>" data-no="<?php echo e($c['challan_no']); ?>" data-due="<?php echo $due; ?>"><i class="fa fa-money"></i> Pay</button>
                                    <?php endif; ?>
                                    <button class="btn btn-primary btn-xs upd-btn" title="Update Fee Challan" data-id="<?php echo $c['challan_id']; ?>" data-no="<?php echo e($c['challan_no']); ?>"><i class="fa fa-pencil"></i> Update</button>
                                    <button class="btn btn-info btn-xs hist-btn" title="Student Fee History" data-student="<?php echo (int)$c['student_id']; ?>" data-name="<?php echo e($c['first_name']); ?>" data-gr="<?php echo e($c['gr_no']); ?>" data-class="<?php echo e($c['class_name']); ?>"><i class="fa fa-history"></i> History</button>
                                    <form method="post" action="view_challan_details.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this challan?');">
                                        <input type="hidden" name="action" value="DeleteChallan">
                                        <input type="hidden" name="challan_id" value="<?php echo $c['challan_id']; ?>">
                                        <button class="btn btn-danger btn-xs" title="Delete"><i class="fa fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php $hist_json = json_encode($history_content); $upd_json = json_encode($update_content); ?>

<!-- Receive Payment Modal -->
<div class="modal fade" id="payfee" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB; padding:16px 20px;">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" style="margin:0; font-weight:800; font-size:16px;"><i class="fa fa-money"></i> Receive Payment — <span id="payChallanNo"></span></h4>
            </div>
            <form method="post" action="view_challan_details.php">
                <div class="modal-body" style="padding:20px;">
                    <input type="hidden" name="action" value="SavePayment">
                    <input type="hidden" name="challan_id" id="payChallanId">
                    <div class="form-group">
                        <label style="font-size:12px;font-weight:700;">Due Amount</label>
                        <input type="text" class="form-control" id="payDue" disabled>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-size:12px;font-weight:700;">Payment Amount</label>
                                <input type="number" step="0.01" class="form-control" name="amount" id="payAmount" min="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-size:12px;font-weight:700;">Discount (optional)</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="discount" id="payDiscount" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px;font-weight:700;">Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="JazzCash">JazzCash</option>
                            <option value="EasyPaisa">EasyPaisa</option>
                            <option value="Cheque">Cheque</option>
                            <option value="UBL Omni">UBL Omni</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px;font-weight:700;">Payment Date</label>
                        <input type="date" name="paid_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Save Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Fee Challan Modal -->
<div class="modal fade" id="update_fee_challan" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" style="width:60%;">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB; padding:16px 20px;">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" style="margin:0; font-weight:800; font-size:16px;"><i class="fa fa-pencil"></i> Update Fee Challan — <span id="updChallanNo"></span></h4>
            </div>
            <form method="post" action="view_challan_details.php">
                <div class="modal-body" id="update_fee_content" style="overflow-x:auto; padding:20px;"></div>
                <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Update Fee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Student Fee History Modal -->
<div class="modal fade" id="fee_payments" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" style="width:95%; max-width:1200px;">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB; padding:16px 20px;">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
                <div>
                    <h4 class="modal-title fee-payments-heading" style="margin:0 0 8px 0; font-weight:800; font-size:16px;">Student Fee History</h4>
                    <div id="fee_payments_student_info" class="fee-student-info-bar"></div>
                </div>
            </div>
            <div class="modal-body" id="fee_payments_content" style="overflow-x:auto; padding:20px;"></div>
            <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                <div style="float:left;">
                    <a href="#" id="feeHistoryProfileLink" target="_blank" class="btn btn-info" style="color:#fff;"><i class="fa fa-user"></i> View Full Profile</a>
                </div>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
var feeHistoryMap = <?php echo $hist_json; ?>;
var feeUpdateMap = <?php echo $upd_json; ?>;
document.querySelectorAll('.pay-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        document.getElementById('payChallanId').value = this.dataset.id;
        document.getElementById('payChallanNo').textContent = this.dataset.no;
        document.getElementById('payDue').value = '<?php echo get_setting('currency_symbol', 'Rs.'); ?> ' + parseFloat(this.dataset.due).toFixed(2);
        document.getElementById('payAmount').value = this.dataset.due;
        jQuery('#payfee').modal('show');
    });
});
document.querySelectorAll('.upd-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        var cid = this.dataset.id;
        document.getElementById('updChallanNo').textContent = this.dataset.no;
        var container = document.getElementById('update_fee_content');
        container.innerHTML = feeUpdateMap[cid] || '<div class="alert alert-warning" style="margin:10px;">No update data found.</div>';
        var form = container.closest('form');
        if (form) {
            var hid = document.createElement('input');
            hid.type = 'hidden'; hid.name = 'action'; hid.value = 'UpdateChallan';
            form.appendChild(hid);
            var hid2 = document.createElement('input');
            hid2.type = 'hidden'; hid2.name = 'challan_id'; hid2.value = cid;
            form.appendChild(hid2);
        }
        initEditCalc(container);
        jQuery('#update_fee_challan').modal('show');
    });
});
function initEditCalc(container){
    function updateRowNet(row){
        var amt = parseFloat(row.querySelector('.edit_fee_head_amount').value) || 0;
        var disc = parseFloat(row.querySelector('.edit_discount').value) || 0;
        if (disc < 0) disc = 0;
        if (disc > amt) disc = amt;
        row.querySelector('.edit_net_amount').value = Math.round((amt - disc) * 100) / 100;
    }
    container.querySelectorAll('tr.edit-fee-row').forEach(function(row){
        row.querySelectorAll('input').forEach(function(inp){
            ['input','change','keyup'].forEach(function(ev){
                inp.addEventListener(ev, function(){ updateRowNet(row); calcTotal(); });
            });
        });
    });
    function calcTotal(){
        var t = 0;
        container.querySelectorAll('.edit_net_amount').forEach(function(n){ t += parseFloat(n.value) || 0; });
        var el = container.querySelector('#edit_total_payable');
        if (el) el.value = Math.round(t * 100) / 100;
    }
    container.querySelectorAll('tr.edit-fee-row').forEach(updateRowNet);
    calcTotal();
}
document.querySelectorAll('.hist-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        var sid = this.dataset.student;
        var chips = '';
        if (this.dataset.gr) chips += '<span class="fee-info-chip"><i class="fa fa-id-badge"></i> GR# ' + this.dataset.gr + '</span>';
        if (this.dataset.class) chips += '<span class="fee-info-chip"><i class="fa fa-graduation-cap"></i> ' + this.dataset.class + '</span>';
        document.getElementById('fee_payments_student_info').innerHTML = chips;
        document.querySelector('#fee_payments .fee-payments-heading').innerHTML = 'Student Fee History &nbsp;<span style="color:#F59E0B;font-size:15px;">' + this.dataset.name + '</span>';
        document.getElementById('fee_payments_content').innerHTML = feeHistoryMap[sid] || '<div class="alert alert-warning" style="margin:10px;">No fee history found for this student.</div>';
        var plink = document.getElementById('feeHistoryProfileLink');
        if (plink) { plink.href = '<?php echo BASE_URL; ?>student.php?student=' + sid; }
        jQuery('#fee_payments').modal('show');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>