<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Fee Payment Slip';

$challan_id = (int) ($_GET['challan_id'] ?? 0);
$challan = null;
if ($challan_id > 0) {
    $st = db_prepare("SELECT c.*, s.first_name, s.last_name, s.father_name, s.gr_no, s.session,
                      s.phone, cl.class_name, sec.section_name
                      FROM fee_challans c
                      LEFT JOIN students s ON c.student_id = s.student_id
                      LEFT JOIN classes cl ON c.class_id = cl.class_id
                      LEFT JOIN sections sec ON s.section_id = sec.section_id
                      WHERE c.challan_id = ?");
    $st->bind_param('i', $challan_id);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $challan = $row; }
}

$items = [];
$payments = [];
if ($challan) {
    $st = db_prepare("SELECT * FROM fee_challan_items WHERE challan_id = ? ORDER BY item_id");
    $st->bind_param('i', $challan_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) { $items[] = $row; }

    $st = db_prepare("SELECT p.*, u.full_name FROM fee_payments p
                      LEFT JOIN users u ON p.received_by = u.user_id
                      WHERE p.challan_id = ? ORDER BY p.created_at");
    $st->bind_param('i', $challan_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) { $payments[] = $row; }
}

function amount_to_words($num) {
    $num = round((float) $num);
    $words = ['Zero','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen','Twenty'];
    $tens = [0,0,'Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    if ($num < 20) return $words[$num];
    if ($num < 100) return $tens[intdiv($num, 10)] . ($num % 10 ? ' ' . $words[$num % 10] : '');
    if ($num < 1000) return $words[intdiv($num, 100)] . ' Hundred' . ($num % 100 ? ' and ' . amount_to_words($num % 100) : '');
    if ($num < 1000000) return amount_to_words(intdiv($num, 1000)) . ' Thousand' . ($num % 1000 ? ' ' . amount_to_words($num % 1000) : '');
    return amount_to_words(intdiv($num, 1000000)) . ' Million' . ($num % 1000000 ? ' ' . amount_to_words($num % 1000000) : '');
}

function number_in_words($amount) {
    $amount = (float) $amount;
    $whole = floor($amount);
    $paisa = round(($amount - $whole) * 100);
    $txt = amount_to_words($whole);
    if ($paisa > 0) { $txt .= ' And ' . amount_to_words($paisa) . ' Paisa'; }
    return $txt . ' Only';
}

include __DIR__ . '/includes/header.php';
?>
<style>
.slip-toolbar { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding:10px 0 16px 0; }
.fee-slip { max-width:820px; margin:0 auto; background:#fff; border:1px solid #E5E7EB; border-radius:12px; padding:28px 32px; font-family:'Segoe UI', Arial, sans-serif; }
.fee-slip .slip-head { display:flex; justify-content:space-between; align-items:center; border-bottom:3px double #111827; padding-bottom:14px; margin-bottom:16px; }
.fee-slip .school-name { font-size:24px; font-weight:900; color:#F59E0B; letter-spacing:.5px; }
.fee-slip .school-tag { font-size:12px; color:#6B7280; }
.fee-slip .voucher-title { text-align:center; font-size:17px; font-weight:800; letter-spacing:2px; text-transform:uppercase; margin:2px 0 14px 0; color:#111827; }
.fee-slip table.bordered { width:100%; border-collapse:collapse; font-size:13px; }
.fee-slip table.bordered th, .fee-slip table.bordered td { border:1px solid #D1D5DB; padding:7px 10px; }
.fee-slip table.bordered thead th { background:#F3F4F6; font-size:12px; text-transform:uppercase; letter-spacing:.3px; color:#374151; }
.fee-slip .info-box { border:1px solid #E5E7EB; border-radius:8px; padding:10px 12px; margin-bottom:14px; font-size:12px; }
.slip-footer { margin-top:22px; border-top:1px solid #E5E7EB; padding-top:14px; font-size:12px; color:#374151; display:flex; justify-content:space-between; gap:20px; flex-wrap:wrap; }
@media print {
    .slideout, #slideout { display:none !important; }
    .left_col, .ds-branch, .slip-toolbar { display:none !important; }
    .right_col { margin:0 !important; padding:0 !important; }
    body { background:#fff !important; }
    .fee-slip { border:none; padding:10px 4px; }
    .noprint { display:none !important; }
}
</style>

<div class="main-content">
    <div class="container-fluid">

        <?php if (!$challan): ?>
            <div class="alert alert-danger" style="margin-top:20px;">
                <strong>Invalid request.</strong> No challan found with the provided identifier.
                <a href="<?php echo BASE_URL; ?>monthly_invoices.php" class="btn btn-default btn-sm" style="margin-left:10px;">Back to Invoices</a>
            </div>
        <?php else: ?>

        <div class="slip-toolbar noprint">
            <a href="javascript:history.back()" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
            <button class="btn btn-primary" onclick="window.print();"><i class="fa fa-print"></i> Print Voucher</button>
        </div>

        <div class="fee-slip">
            <div class="slip-head">
                <div>
                    <div class="school-name"><?php echo e(get_setting('school_name', 'LAPS School & College')); ?></div>
                    <div class="school-tag"><?php echo e(get_setting('school_tagline', '')); ?></div>
                    <div class="school-tag"><?php echo e(get_setting('school_address', '')); ?> &nbsp;|&nbsp; <?php echo e(get_setting('school_phone', '')); ?></div>
                </div>
                <div style="text-align:right;">
                    <div class="voucher-title" style="margin:0;">Fee Payment Voucher</div>
                    <div style="font-size:12px; color:#6B7280;"><?php echo e($challan['challan_no']); ?></div>
                </div>
            </div>

            <div class="info-box">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <tr>
                        <td style="padding:2px 0;"><strong>Student:</strong> <?php echo e($challan['first_name'] . ' ' . $challan['last_name']); ?></td>
                        <td style="padding:2px 0;"><strong>GR No:</strong> <?php echo e($challan['gr_no'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <td style="padding:2px 0;"><strong>Father:</strong> <?php echo e($challan['father_name'] ?? '-'); ?></td>
                        <td style="padding:2px 0;"><strong>Course/Class / Section:</strong> <?php echo e($challan['class_name'] ?? '-') . ' / ' . e($challan['section_name'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <td style="padding:2px 0;"><strong>Challan Month:</strong> <?php echo e($challan['month']) . ' / ' . e($challan['year']); ?></td>
                        <td style="padding:2px 0;"><strong>Session:</strong> <?php echo e($challan['session'] ?? '-'); ?></td>
                    </tr>
                </table>
            </div>

            <table class="bordered" style="margin-bottom:16px;">
                <thead>
                    <tr>
                        <th style="width:6%; text-align:center;">S.No</th>
                        <th style="width:44%;">Fee Head</th>
                        <th style="width:16%; text-align:right;">Amount</th>
                        <th style="width:14%; text-align:right;">Discount</th>
                        <th style="width:20%; text-align:right;">Net Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; $net_total = 0.0; foreach ($items as $it):
                        $net = (float) $it['amount'] - (float) ($it['discount'] ?? 0);
                        $net_total += $net; ?>
                        <tr>
                            <td style="text-align:center;"><?php echo $i++; ?></td>
                            <td><?php echo e($it['description'] ?: 'Fee'); ?></td>
                            <td style="text-align:right;"><?php echo number_format($it['amount'], 2); ?></td>
                            <td style="text-align:right;"><?php echo $it['discount'] ? number_format($it['discount'], 2) : '—'; ?></td>
                            <td style="text-align:right;"><?php echo number_format($net, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($items) === 0): ?>
                        <tr><td colspan="5" style="text-align:center; color:#9CA3AF; padding:16px;">No fee heads recorded on this challan.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#F9FAFB;">
                        <td colspan="3" style="text-align:right;"><strong>Total Amount</strong></td>
                        <td></td>
                        <td style="text-align:right;"><strong><?php echo get_setting('currency_symbol', 'Rs.') . number_format($challan['total_amount'], 2); ?></strong></td>
                    </tr>
                    <tr style="background:#EEF2FF;">
                        <td colspan="3" style="text-align:right;"><strong>Amount Paid</strong></td>
                        <td></td>
                        <td style="text-align:right; color:#16A34A;"><strong><?php echo number_format($challan['paid_amount'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align:right;"><strong>Balance Due</strong></td>
                        <td></td>
                        <td style="text-align:right; color:#DC2626;"><strong><?php echo number_format((float)$challan['total_amount'] - (float)$challan['paid_amount'], 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>

            <div class="info-box">
                <div style="font-size:13px;"><strong>Amount in words:</strong> <?php echo number_in_words((float) $challan['total_amount']); ?> <?php echo e(get_setting('currency_symbol', 'Rs.')); ?></div>
            </div>

            <?php if (count($payments) > 0): ?>
                <table class="bordered" style="margin-bottom:16px;">
                    <thead>
                        <tr>
                            <th style="width:10%; text-align:center;">S.No</th>
                            <th style="width:30%;">Payment Method</th>
                            <th style="width:20%; text-align:right;">Amount</th>
                            <th style="width:20%; text-align:right;">Discount</th>
                            <th style="width:20%;">Received On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($payments as $p): ?>
                            <tr>
                                <td style="text-align:center;"><?php echo $i++; ?></td>
                                <td><?php echo e($p['payment_method'] ?: 'Cash'); ?></td>
                                <td style="text-align:right;"><?php echo number_format($p['amount'], 2); ?></td>
                                <td style="text-align:right;"><?php echo $p['discount'] ? number_format($p['discount'], 2) : '—'; ?></td>
                                <td><?php echo date('d-M-Y h:i A', strtotime($p['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="slip-footer">
                <div style="max-width:420px;">
                    <div style="font-weight:800; margin-bottom:4px;">Payment Accounts</div>
                    <?php if (get_setting('fee_bank_account_title', '') || get_setting('fee_bank_account_no', '')): ?>
                        <div><strong><?php echo e(get_setting('fee_bank_account_title', 'Bank Transfer')); ?></strong></div>
                        <div><?php echo e(get_setting('fee_bank_name', '')); ?> &mdash; <?php echo e(get_setting('fee_bank_account_no', '')); ?></div>
                    <?php endif; ?>
                    <?php if (get_setting('fee_jazzcash_account_no', '')): ?>
                        <div><strong>JazzCash:</strong> <?php echo e(get_setting('fee_jazzcash_account_title', '')); ?> — <?php echo e(get_setting('fee_jazzcash_account_no', '')); ?></div>
                    <?php endif; ?>
                    <?php if (get_setting('fee_easypaisa_account_no', '')): ?>
                        <div><strong>EasyPaisa:</strong> <?php echo e(get_setting('fee_easypaisa_account_title', '')); ?> — <?php echo e(get_setting('fee_easypaisa_account_no', '')); ?></div>
                    <?php endif; ?>
                    <?php if (get_setting('fee_raast_id', '')): ?>
                        <div><strong>Raast ID:</strong> <?php echo e(get_setting('fee_raast_id', '')); ?></div>
                    <?php endif; ?>
                </div>
                <div style="text-align:right; align-self:flex-end;">
                    <div style="border-top:1px solid #9CA3AF; padding-top:6px; min-width:200px;">Authorized Signatory</div>
                </div>
            </div>

            <?php $footerNote = get_setting('fee_voucher_footer', ''); if ($footerNote !== ''): ?>
                <div style="margin-top:16px; font-size:11px; color:#6B7280; text-align:center; border-top:1px dashed #D1D5DB; padding-top:10px;"><?php echo nl2br(e($footerNote)); ?></div>
            <?php endif; ?>

            <div style="margin-top:10px; font-size:11px; color:#9CA3AF; text-align:center;">
                Printed on <?php echo date('d-M-Y h:i A'); ?> &middot; Generated by LAPS School &amp; College
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>