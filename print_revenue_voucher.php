<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$fFrom = $_GET['from'] ?? '';
$fTo = $_GET['to'] ?? '';
$fRid = (int) ($_GET['revenue_id'] ?? 0);

$where = [];
$params = [];
$types = '';
if ($fRid > 0) { $where[] = "r.revenue_id = ?"; $params[] = $fRid; $types .= 'i'; }
if ($fFrom !== '') { $where[] = "r.paid_date >= ?"; $params[] = $fFrom; $types .= 's'; }
if ($fTo !== '') { $where[] = "r.paid_date <= ?"; $params[] = $fTo; $types .= 's'; }

$sql = "SELECT r.*, h.head_name,
        s.first_name, s.last_name, s.father_name, s.gr_no, s.session,
        cl.class_name, sec.section_name
        FROM revenues r
        LEFT JOIN revenue_heads h ON r.head_id = h.head_id
        LEFT JOIN students s ON r.student_id = s.student_id
        LEFT JOIN classes cl ON s.class_id = cl.class_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id";
if (count($where) > 0) { $sql .= " WHERE " . implode(' AND ', $where); }
$sql .= " ORDER BY COALESCE(r.paid_date, r.revenue_date), r.head_id, r.revenue_id";

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

$currency = get_setting('currency_symbol', 'Rs.');
$schoolName = get_setting('school_name', 'Test Portal');
$schoolAddr = get_setting('school_address', '');
$schoolPhone = get_setting('school_phone', '');
$softwareName = get_setting('software_name', 'HIFI');
$schoolLogo = get_setting('school_logo', '');
$logoSrc = $schoolLogo !== '' ? BASE_URL . $schoolLogo : BASE_URL . 'assets/img/logo.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Revenue Voucher | <?php echo e($schoolName); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f5; font-family: 'Segoe UI', Arial, sans-serif; color: #111827; }
        .toolbar { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 5; }
        .toolbar h3 { margin: 0; font-size: 16px; font-weight: 800; }
        .toolbar .btn { border: 0; border-radius: 8px; padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer; color: #fff; }
        .btn-print { background: #16A34A; margin-right: 8px; }
        .btn-back { background: #377DFF; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
        }
        .sheet { max-width: 760px; margin: 18px auto; }
        .voucher-copy { background: #fff; border: 1.5px solid #111827; padding: 22px 26px; margin-bottom: 22px; page-break-inside: avoid; }
        .copy-tag { text-align: right; font-size: 12px; font-weight: 800; letter-spacing: 0.5px; color: #374151; text-transform: uppercase; }
        .vhead { text-align: center; }
        .vhead .vlogo { display: flex; justify-content: center; margin-bottom: 6px; }
        .vhead .vlogo img { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 2px solid #111827; background: #fff; }
        .vhead .school { font-size: 26px; font-weight: 900; letter-spacing: 0.5px; }
        .vhead .addr { font-size: 13px; color: #4B5563; margin-top: 2px; }
        .vhead .contact { font-size: 12.5px; color: #4B5563; }
        .vhead .vtitle { display: inline-block; margin-top: 10px; padding: 2px 26px; border: 2px solid #111827; font-size: 16px; font-weight: 800; letter-spacing: 3px; text-transform: uppercase; }
        .vmeta { width: 100%; font-size: 12.5px; margin-top: 12px; }
        .vmeta td { padding: 3px 0; }
        .vmeta b { font-weight: 800; }
        .vinfo { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 12.5px; }
        .vinfo td { border: 1px solid #9CA3AF; padding: 6px 8px; }
        .vinfo b { font-weight: 800; }
        .vitems { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12.5px; }
        .vitems th { border: 1px solid #6B7280; background: #F3F4F6; padding: 7px 8px; text-align: left; font-weight: 800; }
        .vitems td { border: 1px solid #9CA3AF; padding: 7px 8px; }
        .vitems .amt { text-align: right; font-weight: 700; white-space: nowrap; }
        .vitems .total-row td { font-weight: 900; background: #F9FAFB; }
        .vsig { margin-top: 26px; text-align: center; font-size: 12.5px; color: #4B5563; }
        .vsig .line { display: inline-block; margin-top: 26px; border-top: 1px solid #6B7280; padding: 4px 40px 0; }
        .vpower { margin-top: 14px; text-align: center; font-size: 11px; color: #9CA3AF; letter-spacing: 0.3px; }
        .no-records { background:#fff; border:1px solid #E5E7EB; border-radius:12px; padding:40px; text-align:center; color:#6B7280; max-width:760px; margin:18px auto; }
    </style>
</head>
<body>

<div class="toolbar">
    <h3><i class="fa fa-print"></i> Revenue Voucher</h3>
    <div>
        <button class="btn btn-print" onclick="window.print()">Print Voucher</button>
        <a class="btn btn-back" href="<?php echo BASE_URL; ?>revenue_list.php">Back to List</a>
    </div>
</div>

<?php if (count($rows) === 0): ?>
    <div class="no-records">No voucher record found.</div>
<?php elseif ($fRid > 0 && count($rows) === 1): ?>

    <?php
    $r = $rows[0];
    $payDate = $r['paid_date'] ?: $r['revenue_date'];
    $studentName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
    if ($studentName === '') { $studentName = trim(($r['first_name'] ?? '') . ' ' . ($r['father_name'] ?? '')); }
    if ($studentName === '') { $studentName = "Other's"; }
    $monthSession = $payDate ? date('F-y', strtotime($payDate)) : '-';
    $issued = $payDate ? date('d-M-Y', strtotime($payDate)) : '-';
    $amount = number_format((float) $r['amount'], 0, '.', '');
    $headName = $r['head_name'] ?: 'Fee';
    $remarks = trim($r['remarks'] ?: $r['description'] ?: '');
    ?>

    <div class="sheet">
        <?php foreach (['Institute Copy', 'Student Copy'] as $copyLabel): ?>
        <div class="voucher-copy">
            <div class="copy-tag"><?php echo $copyLabel; ?></div>
            <div class="vhead">
                <div class="vlogo"><img src="<?php echo $logoSrc; ?>" alt="Logo"></div>
                <div class="school"><?php echo e($schoolName); ?></div>
                <?php if ($schoolAddr !== ''): ?><div class="addr"><?php echo e($schoolAddr); ?></div><?php endif; ?>
                <?php if ($schoolPhone !== ''): ?><div class="contact">Contact: <?php echo e($schoolPhone); ?></div><?php endif; ?>
                <div class="vtitle">Voucher</div>
            </div>
            <table class="vmeta">
                <tr>
                    <td>Voucher No: <b><?php echo (int) $r['revenue_id']; ?></b></td>
                    <td style="text-align:right;">Issued: <b><?php echo e($issued); ?></b></td>
                </tr>
            </table>
            <table class="vinfo">
                <tr>
                    <td style="width:50%;">Student Name: <b><?php echo e($studentName); ?></b></td>
                    <td>Father Name: <b><?php echo e(trim($r['father_name'] ?? '')) ?: '-'; ?></b></td>
                </tr>
                <tr>
                    <td>Class: <b><?php echo e($r['class_name'] ?? '-'); ?></b></td>
                    <td>Section: <b><?php echo e($r['section_name'] ?? '-'); ?></b></td>
                </tr>
                <tr>
                    <td>Roll No: <b><?php echo e($r['gr_no'] ?? '-'); ?></b></td>
                    <td>Month/Session: <b><?php echo e($monthSession); ?></b></td>
                </tr>
            </table>
            <table class="vitems">
                <thead>
                    <tr><th style="width:55%;">Name</th><th style="width:17%;">Total</th><th style="width:28%;">Remarks</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo e($headName); ?></td>
                        <td class="amt"><?php echo $amount; ?></td>
                        <td><?php echo e($remarks ?: '-'); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td style="text-align:right; font-weight:900;">Total</td>
                        <td class="amt"><?php echo $amount; ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <div class="vsig">
                <span class="line">(Signature &amp; Stamp)</span>
            </div>
            <div class="vpower">Powered by: <?php echo e($softwareName); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>

    <?php
    $grand = 0.0;
    $byHead = [];
    foreach ($rows as $r) {
        $grand += (float) $r['amount'];
        $key = $r['head_name'] ?: 'Miscellaneous';
        if (!isset($byHead[$key])) { $byHead[$key] = ['name' => $key, 'total' => 0.0, 'rows' => []]; }
        $byHead[$key]['total'] += (float) $r['amount'];
        $byHead[$key]['rows'][] = $r;
    }
    ?>
    <div class="sheet">
        <div style="background:#fff; border:1.5px solid #111827; padding:22px 26px;">
            <div class="vhead">
                <div class="vlogo"><img src="<?php echo $logoSrc; ?>" alt="Logo"></div>
                <div class="school"><?php echo e($schoolName); ?></div>
                <?php if ($schoolAddr !== ''): ?><div class="addr"><?php echo e($schoolAddr); ?></div><?php endif; ?>
                <?php if ($schoolPhone !== ''): ?><div class="contact">Contact: <?php echo e($schoolPhone); ?></div><?php endif; ?>
                <div class="vtitle">Revenue Voucher List</div>
            </div>
            <p style="text-align:center; font-size:13px; margin:10px 0;">
                Period: <?php echo $fFrom !== '' ? e($fFrom) : 'All'; ?> To: <?php echo $fTo !== '' ? e($fTo) : 'Today'; ?>
            </p>
            <?php $ci = 0; ?>
            <?php foreach ($byHead as $group): $ci++; ?>
                <h4 style="font-weight:800; border-bottom:2px solid #111827; padding-bottom:6px; margin-top:20px;"><?php echo e($group['name']); ?></h4>
                <table class="vitems">
                    <thead>
                        <tr><th style="width:5%;">S.No</th><th style="width:30%;">Student Name</th><th style="width:12%;">Date</th><th style="width:24%;">Remarks</th><th style="width:16%;" align="right">Amount</th></tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($group['rows'] as $r): ?>
                            <?php $pd = $r['paid_date'] ?: $r['revenue_date']; ?>
                            <?php $nm = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); if ($nm === '') { $nm = "Other's"; } ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo e($nm); ?></td>
                                <td><?php echo $pd ? date('d M Y', strtotime($pd)) : '-'; ?></td>
                                <td><?php echo e(trim($r['remarks'] ?: $r['description'] ?: '')) ?: '-'; ?></td>
                                <td class="amt"><?php echo number_format((float) $r['amount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row"><td colspan="4" style="text-align:right;">Voucher Total</td><td class="amt"><?php echo number_format($group['total']); ?></td></tr>
                    </tfoot>
                </table>
            <?php endforeach; ?>
            <div style="margin-top:12px; text-align:right; font-size:15px; font-weight:900;">
                Grand Total: <?php echo $currency . ' ' . number_format($grand); ?>
            </div>
        </div>
    </div>
<?php endif; ?>

</body>
</html>