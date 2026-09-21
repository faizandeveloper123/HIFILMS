<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'View Revenue List';

$message = '';
$error = '';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$head_id = (int) ($_GET['head_id'] ?? 0);
$month = (int) ($_GET['month'] ?? 0);

$heads = [];
$res = db_query("SELECT * FROM revenue_heads ORDER BY head_name");
while ($row = $res->fetch_assoc()) { $heads[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'DeleteRevenue') {
    $rid = (int) ($_POST['revenue_id'] ?? 0);
    if ($rid > 0) {
        $st2 = db_prepare("DELETE FROM revenues WHERE revenue_id=?");
        $st2->bind_param('i', $rid);
        $st2->execute();
        $message = 'Revenue deleted successfully!';
    }
}

$where = [];
$params = [];
$types = '';
if ($from !== '') { $where[] = "r.paid_date >= ?"; $params[] = $from; $types .= 's'; }
if ($to !== '') { $where[] = "r.paid_date <= ?"; $params[] = $to; $types .= 's'; }
if ($head_id > 0) { $where[] = "r.head_id = ?"; $params[] = $head_id; $types .= 'i'; }
if ($month > 0) { $where[] = "MONTH(r.paid_date) = ? AND YEAR(r.paid_date) = YEAR(CURDATE())"; $params[] = $month; $types .= 'i'; }

$sql = "SELECT r.*, h.head_name,
        CONCAT(s.first_name, ' ', COALESCE(s.father_name, '')) AS student_display, s.class_id, cl.class_name, sec.section_name
        FROM revenues r
        LEFT JOIN revenue_heads h ON r.head_id = h.head_id
        LEFT JOIN students s ON r.student_id = s.student_id
        LEFT JOIN classes cl ON s.class_id = cl.class_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id";
if (count($where) > 0) { $sql .= " WHERE " . implode(' AND ', $where); }
$sql .= " ORDER BY COALESCE(r.paid_date, r.revenue_date) DESC, r.revenue_id DESC";

$rows = [];
if (count($params) > 0) {
    $st2 = db_prepare($sql);
    $st2->bind_param($types, ...$params);
    $st2->execute();
    $res = $st2->get_result();
} else {
    $res = db_query($sql);
}
while ($row = $res->fetch_assoc()) { $rows[] = $row; }

$grand = 0.0;
foreach ($rows as $r) { $grand += (float) $r['amount']; }

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.13/css/jquery.dataTables.min.css">
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.page-head-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:14px 4px; }
.page-head-row h2 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.record-count-badge { display:inline-block; font-size:11px; font-weight:700; color:#377DFF; background:#E9F2FF; border-radius:999px; padding:4px 10px; margin-left:8px; vertical-align:middle; }
.breadcrumb-modern { display:flex; align-items:center; gap:8px; font-size:12.5px; color:#6B7280; margin:6px 0 0; padding:0; list-style:none; }
.breadcrumb-modern a { color:#377DFF; text-decoration:none; }
.breadcrumb-modern i { font-size:11px; color:#9CA3AF; }
.page-actions { margin-bottom:16px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="page-head-row">
            <div>
                <h2><i class="fa fa-list"></i> View Revenue List <span class="record-count-badge"><?php echo count($rows); ?> Records</span></h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb-modern">
                        <li><a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a></li>
                        <li><i class="fa fa-angle-right"></i></li>
                        <li><a href="#">Financial Management</a></li>
                        <li><i class="fa fa-angle-right"></i></li>
                        <li><span>View Revenue List</span></li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>add_revenue.php" class="btn btn-success" style="color:#fff;"><i class="fa fa-plus-circle"></i> Add Revenue</a>
            </div>
        </div>

        <form method="get" action="revenue_list.php" class="search-bar-student">
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label><i class="fa fa-calendar"></i> From</label>
                <input type="date" name="from" class="form-control" value="<?php echo e($from); ?>">
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label><i class="fa fa-calendar"></i> To</label>
                <input type="date" name="to" class="form-control" value="<?php echo e($to); ?>">
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <label><i class="fa fa-calendar-o"></i> Month</label>
                <select name="month" class="form-control">
                    <option value="">All Months</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $month === $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label><i class="fa fa-tags"></i> Head</label>
                <select name="head_id" class="form-control">
                    <option value="">All Heads</option>
                    <?php foreach ($heads as $h): ?>
                        <option value="<?php echo $h['head_id']; ?>" <?php echo $head_id == $h['head_id'] ? 'selected' : ''; ?>><?php echo e($h['head_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa fa-search"></i> Filter</button>
            </div>
        </form>

        <div class="page-actions">
            <button type="button" class="btn btn-info" style="color:#fff;" onclick="window.open('<?php echo BASE_URL; ?>print_revenue_voucher.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>','_blank')"><i class="fa fa-print"></i> Print Report</button>
            <button type="button" class="btn btn-default" onclick="location.href='revenue_list.php'"><i class="fa fa-refresh"></i> Refresh</button>
        </div>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <table id="revenuelist" class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead>
                    <tr>
                        <th width="5%">S.No</th>
                        <th width="20%">Student Name</th>
                        <th width="12%">Month</th>
                        <th width="12%">Date</th>
                        <th width="25%">Remarks</th>
                        <th width="12%">Total</th>
                        <th width="10%" style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rows) === 0): ?>
                        <tr><td colspan="7" class="dataTables_empty">No data available in table</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; foreach ($rows as $r): ?>
                        <?php $payDate = $r['paid_date'] ?: $r['revenue_date']; ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td>
                                <?php if (!empty($r['student_display']) && trim($r['student_display']) !== ''): ?>
                                    <strong><?php echo e($r['student_display']); ?></strong>
                                    <br><small style="color:#6B7280;"><?php echo e($r['class_name'] ?? ''); ?><?php echo !empty($r['section_name']) ? ' - ' . e($r['section_name']) : ''; ?></small>
                                <?php else: ?>
                                    <span class="status-badge" style="background:#F3F4F6; color:#6B7280;">Other's</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $payDate ? date('M Y', strtotime($payDate)) : '-'; ?></td>
                            <td><?php echo $payDate ? date('d M Y', strtotime($payDate)) : '-'; ?></td>
                            <td><?php echo e($r['remarks'] ?: $r['description'] ?: '-'); ?></td>
                            <td style="color:#16A34A; font-weight:700;"><?php echo number_format($r['amount'], 2); ?></td>
                            <td style="text-align:center;">
                                <a href="<?php echo BASE_URL; ?>add_revenue.php" class="btn btn-success btn-xs" title="Add New Revenue"><i class="fa fa-plus"></i></a>
                                <a href="<?php echo BASE_URL; ?>print_revenue_voucher.php?revenue_id=<?php echo $r['revenue_id']; ?>" target="_blank" class="btn btn-info btn-xs" title="Print Voucher"><i class="fa fa-print"></i></a>
                                <form method="post" action="revenue_list.php" style="display:inline;" onsubmit="return confirm('Delete this revenue?');">
                                    <input type="hidden" name="action" value="DeleteRevenue">
                                    <input type="hidden" name="revenue_id" value="<?php echo $r['revenue_id']; ?>">
                                    <button class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#F9FAFB;">
                        <th colspan="5" style="text-align:right;">Grand Total</th>
                        <th style="color:#16A34A;"><?php echo number_format($grand, 2); ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function(){
    $('#revenuelist').DataTable({ order: [], pageLength: 10 });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>