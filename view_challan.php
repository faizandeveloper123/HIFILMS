<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'View Challan';

$sessions = [];
for ($y = 2018; $y <= 2030; $y++) { $sessions[] = $y . '-' . substr($y + 1, -2); }

$months = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];

$sel_session  = $_GET['session'] ?? get_setting('session_year', '2026-2027');
if (!in_array($sel_session, $sessions)) { $sel_session = get_setting('session_year', '2026-2027'); }
$sel_month    = (int) ($_GET['month'] ?? 0);
$sel_fee      = $_GET['fee'] ?? 'All';
$sel_challan  = trim($_GET['challan_no'] ?? '');
$sel_student  = trim($_GET['student'] ?? '');
$sel_class    = (int) ($_GET['class_id'] ?? 0);

$classes = [];
$res = db_query("SELECT class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$where = [];
$params = [];
$types = '';

if (preg_match('/^(\d{4})-(\d{2})$/', $sel_session, $sm)) {
    $y1 = (int) $sm[1];
    $y2 = $y1 + 1;
    $where[] = "(c.year = ? OR c.year = ?)";
    $params[] = $y1;
    $params[] = $y2;
    $types .= 'ii';
}
if ($sel_month >= 1 && $sel_month <= 12) {
    $where[] = "CAST(c.month AS UNSIGNED) = ?";
    $params[] = $sel_month;
    $types .= 'i';
}
if ($sel_fee === 'PAID') {
    $where[] = "c.status = 'paid'";
} elseif ($sel_fee === 'UNPAID') {
    $where[] = "c.status IN ('unpaid','partial')";
}
if ($sel_challan !== '') {
    $where[] = "c.challan_no LIKE ?";
    $params[] = '%' . $sel_challan . '%';
    $types .= 's';
}
if ($sel_student !== '') {
    $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.gr_no LIKE ?)";
    $like = '%' . $sel_student . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}
if ($sel_class > 0) {
    $where[] = "c.class_id = ?";
    $params[] = $sel_class;
    $types .= 'i';
}

$search_performed = ($sel_challan !== '' || $sel_student !== '' || $sel_class > 0 || ($sel_month >= 1 && $sel_month <= 12));

$challans = [];
if ($search_performed && count($where) > 0) {
    $sql = "SELECT c.*, s.first_name, s.last_name, s.father_name, s.gr_no, cl.class_name, sec.section_name
            FROM fee_challans c
            LEFT JOIN students s ON c.student_id = s.student_id
            LEFT JOIN classes cl ON c.class_id = cl.class_id
            LEFT JOIN sections sec ON s.section_id = sec.section_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.year DESC, CAST(c.month AS UNSIGNED) DESC, c.challan_id DESC LIMIT 500";
    $st = db_prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) { $challans[] = $row; }
}

$items_by = [];
if (count($challans) > 0) {
    $ids = array_map('intval', array_column($challans, 'challan_id'));
    $r2 = db_query("SELECT * FROM fee_challan_items WHERE challan_id IN (" . implode(',', $ids) . ") ORDER BY challan_id, item_id");
    while ($row = $r2->fetch_assoc()) { $items_by[$row['challan_id']][] = $row; }
}

$stats = ['total' => 0, 'paid' => 0, 'partial' => 0, 'unpaid' => 0];
foreach ($challans as $c) {
    $stats['total']++;
    if ($c['status'] === 'paid') { $stats['paid']++; }
    elseif ($c['status'] === 'partial') { $stats['partial']++; }
    else { $stats['unpaid']++; }
}

include __DIR__ . '/includes/header.php';
?>
<div class="row" style="margin-top: 0px;">
    <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard </a> &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
    <a href="<?php echo BASE_URL; ?>view_challan.php"> Fee Collection </a> &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp; View Challan

    <div class="" style="margin-top:10px;">
        <form class="" action="<?php echo BASE_URL; ?>view_challan.php" enctype="multipart/form-data" method="get">
            <div class="panel panel-default">
                <div class="panel-heading " id="heading">
                    <span style="float:right;margin-top: -7px;">
                        <button type="submit" class="btn btn-primary" style="margin-top: 24px;float: right;"><i class="fa fa-search"></i> Search</button>
                    </span>
                    <div class="clearfix"></div>
                </div>
                <div class="panel-body">
                    <div class="col-md-12">
                        <div class="col-md-3 col-xs-12" style="padding: 8px;">
                            <div class="form-group ">
                                <label class="required">Challan No</label>
                                <input type="text" name="challan_no" class="form-control" value="<?php echo e($sel_challan); ?>" placeholder="Enter Challan No">
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding: 8px;">
                            <div class="form-group ">
                                <label>Student</label>
                                <input type="text" name="student" class="form-control" value="<?php echo e($sel_student); ?>" placeholder="Student Name / GR No">
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding: 8px;">
                            <div class="form-group ">
                                <label>Class</label>
                                <select name="class_id" class="form-control">
                                    <option value="0">All Classes</option>
                                    <?php foreach ($classes as $cl): ?>
                                        <option value="<?php echo $cl['class_id']; ?>" <?php echo $sel_class === (int) $cl['class_id'] ? 'selected' : ''; ?>><?php echo e($cl['class_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding: 8px;">
                            <div class="form-group ">
                                <label class="required">Session</label>
                                <select name="session" class="form-control">
                                    <?php foreach ($sessions as $sv): ?>
                                        <option <?php echo $sel_session === $sv ? 'selected' : ''; ?> value="<?php echo e($sv); ?>"><?php echo e($sv); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding: 8px;">
                            <div class="form-group ">
                                <label class="required">Challan Month</label>
                                <select name="month" id="month" class="form-control">
                                    <option value="">Select Month</option>
                                    <?php foreach ($months as $mn => $mname): ?>
                                        <option value="<?php echo $mn; ?>" <?php echo $sel_month === $mn ? 'selected' : ''; ?>><?php echo $mname; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding: 8px;">
                            <div class="form-group ">
                                <label class="required">Fee Status</label>
                                <select name="fee" class="form-control">
                                    <option value="All" <?php echo $sel_fee === 'All' ? 'selected' : ''; ?>>All</option>
                                    <option value="PAID" <?php echo $sel_fee === 'PAID' ? 'selected' : ''; ?>>PAID</option>
                                    <option value="UNPAID" <?php echo $sel_fee === 'UNPAID' ? 'selected' : ''; ?>>UNPAID</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>
        </form>
        <div class="clearfix"></div>
    </div>

    <?php if (!$search_performed): ?>
        <div style="display:block;font-size: 16px;color:white;" class="alert alert-danger alert-dismissible">
            <a href="#" class="close" data-dismiss="alert" aria-label="close">×</a>
            <strong>Warning !</strong> Please select Month and Search !!!
        </div>
    <?php else: ?>
        <div style="background:#fff; border:1px solid #E5E7EB; border-radius:12px; padding:16px; margin-top:10px; overflow-x:auto;">
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
                <h4 style="margin:0; font-weight:800; color:#111827; font-size:15px;"><i class="fa fa-file-text-o"></i> View Challan <span style="color:#6B7280; font-size:13px; font-weight:600;">(<?php echo count($challans); ?> records)</span></h4>
                <div>
                    <span class="label label-primary" style="font-size:12px;">Total: <?php echo $stats['total']; ?></span>
                    <span class="label label-success" style="font-size:12px;">Paid: <?php echo $stats['paid']; ?></span>
                    <span class="label label-warning" style="font-size:12px;">Partial: <?php echo $stats['partial']; ?></span>
                    <span class="label label-danger" style="font-size:12px;">Unpaid: <?php echo $stats['unpaid']; ?></span>
                </div>
            </div>
            <table class="table table-striped table-bordered" id="listofstudents" style="width:100%; background:#fff; font-size:13px; margin-bottom:0;">
                <thead>
                    <tr style="background:#F9FAFB;">
                        <th width="4%">S.No</th>
                        <th width="12%">Challan No</th>
                        <th width="18%">Student</th>
                        <th width="10%">Class</th>
                        <th width="6%">Month</th>
                        <th width="6%">Year</th>
                        <th width="10%">Total</th>
                        <th width="9%">Paid</th>
                        <th width="9%">Due</th>
                        <th width="8%">Status</th>
                        <th width="8%">Print</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($challans) === 0): ?>
                        <tr><td colspan="11" style="text-align:center; color:#6B7280; padding:40px;">No challans found for the selected filters.</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; foreach ($challans as $c):
                        $due = (float) $c['total_amount'] - (float) $c['paid_amount'];
                        $badge = 'background:#FEE2E2;color:#DC2626;';
                        if ($c['status'] === 'partial') { $badge = 'background:#FFF7E0;color:#F59E0B;'; }
                        if ($c['status'] === 'paid') { $badge = 'background:#DCFCE7;color:#16A34A;'; }
                        $ch_items = $items_by[$c['challan_id']] ?? [];
                    ?>
                        <tr>
                            <td><?php echo $i; ?></td>
                            <td><strong><?php echo e($c['challan_no']); ?></strong></td>
                            <td>
                                <?php echo e($c['first_name'] ? trim($c['first_name'] . ' ' . ($c['last_name'] ?? '')) : 'N/A'); ?><br>
                                <small style="color:#6B7280;">GR# <?php echo e($c['gr_no'] ?: '-'); ?></small>
                            </td>
                            <td><?php echo e($c['class_name'] ?? '-'); ?> <?php echo $c['section_name'] ? '<small>(' . e($c['section_name']) . ')</small>' : ''; ?></td>
                            <td><?php echo e($c['month']); ?></td>
                            <td><?php echo e($c['year']); ?></td>
                            <td style="font-weight:700;"><?php echo get_setting('currency_symbol', 'Rs.') . number_format($c['total_amount'], 2); ?></td>
                            <td style="color:#16A34A; font-weight:700;"><?php echo number_format($c['paid_amount'], 2); ?></td>
                            <td style="color:<?php echo $due > 0 ? '#DC2626' : '#16A34A'; ?>; font-weight:700;"><?php echo number_format($due, 2); ?></td>
                            <td><span style="padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; <?php echo $badge; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>payment_slip.php?challan_id=<?php echo (int) $c['challan_id']; ?>" target="_blank" class="btn btn-info btn-xs" title="Print Challan"><i class="fa fa-print"></i> Print</a>
                            </td>
                        </tr>
                        <tr style="background:#FBFCFE;">
                            <td colspan="11" style="padding:10px 14px;">
                                <strong style="font-size:12px; color:#374151;"><i class="fa fa-list"></i> Fee Heads:</strong>
                                <table class="table table-bordered" style="width:100%; background:#fff; font-size:12px; margin:6px 0 0 0;">
                                    <thead>
                                        <tr style="background:#F3F4F6;">
                                            <th width="5%">S.No</th>
                                            <th width="45%">Fee Head</th>
                                            <th width="15%">Amount</th>
                                            <th width="15%">Discount</th>
                                            <th width="20%">Net Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($ch_items) === 0): ?>
                                            <tr><td colspan="5" style="text-align:center; color:#9CA3AF; padding:10px;">No fee heads recorded on this challan.</td></tr>
                                        <?php endif; ?>
                                        <?php $k = 1; $net_total = 0.0; foreach ($ch_items as $it):
                                            $net = (float) $it['amount'] - (float) ($it['discount'] ?? 0);
                                            $net_total += $net;
                                        ?>
                                            <tr>
                                                <td><?php echo $k++; ?></td>
                                                <td><?php echo e($it['description'] ?: 'Fee'); ?></td>
                                                <td><?php echo number_format($it['amount'], 2); ?></td>
                                                <td><?php echo $it['discount'] ? number_format($it['discount'], 2) : '—'; ?></td>
                                                <td><?php echo number_format($net, 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr style="background:#F9FAFB; font-weight:700;">
                                            <td colspan="4" style="text-align:right;">Total Payable</td>
                                            <td><?php echo number_format($net_total > 0 ? $net_total : (float) $c['total_amount'], 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    <?php $i++; endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>