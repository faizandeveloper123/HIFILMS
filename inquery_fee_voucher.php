<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Inquiry Fee Voucher';

db_query("CREATE TABLE IF NOT EXISTS inquiry_fee_vouchers (
    voucher_id INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_id INT NOT NULL,
    student_name VARCHAR(191) DEFAULT '',
    father_name VARCHAR(191) DEFAULT '',
    class_id INT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$inquiries = [];
$res = db_query("SELECT inquiry_id, name, father_name, class_id FROM student_inquiries ORDER BY created_at DESC");
while ($row = $res->fetch_assoc()) { $inquiries[] = $row; }

$inquiry_id = (int) ($_GET['inquiry_id'] ?? 0);
$inquiry = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && trim($_POST['action'] ?? '') === 'save_voucher') {
    $vid = (int) ($_POST['inquiry_id'] ?? 0);
    $inquiry_id = $vid;
    if ($vid > 0) {
        $st = db_prepare("SELECT i.*, c.class_name FROM student_inquiries i LEFT JOIN classes c ON i.class_id=c.class_id WHERE i.inquiry_id=?");
        $st->bind_param('i', $vid);
        $st->execute();
        $inquiry = $st->get_result()->fetch_assoc();
        if ($inquiry) {
            $heads = [];
            $hs = db_prepare("SELECT head_name, amount FROM fee_heads WHERE status=1 AND (class_id=? OR class_id IS NULL) ORDER BY head_id");
            $hs->bind_param('i', $inquiry['class_id']);
            $hs->execute();
            $hr = $hs->get_result();
            while ($row = $hr->fetch_assoc()) { $heads[] = $row; }
            $amount = 0.0;
            foreach ($heads as $h) { $amount += (float) $h['amount']; }
            $uid = (int) ($_SESSION['user_id'] ?? 0);
            $cls = (int) $inquiry['class_id'];
            $ins = db_prepare("INSERT INTO inquiry_fee_vouchers (inquiry_id, student_name, father_name, class_id, amount, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->bind_param('issidi', $vid, $inquiry['name'], $inquiry['father_name'], $cls, $amount, $uid);
            $ins->execute();
            $message = 'Fee voucher saved! Voucher #' . (int) $ins->insert_id;
        }
    }
    if (empty($message)) { $error = 'Please select a valid inquiry.'; }
}

if ($inquiry_id > 0 && !$inquiry) {
    $st = db_prepare("SELECT i.*, c.class_name FROM student_inquiries i LEFT JOIN classes c ON i.class_id=c.class_id WHERE i.inquiry_id=?");
    $st->bind_param('i', $inquiry_id);
    $st->execute();
    $inquiry = $st->get_result()->fetch_assoc();
}

$heads = [];
$class_name = '';
if ($inquiry) {
    $class_name = $inquiry['class_name'] ?? '';
    $hs = db_prepare("SELECT head_name, amount FROM fee_heads WHERE status=1 AND (class_id=? OR class_id IS NULL) ORDER BY head_id");
    $hs->bind_param('i', $inquiry['class_id']);
    $hs->execute();
    $hr = $hs->get_result();
    while ($row = $hr->fetch_assoc()) { $heads[] = $row; }
}
$total = 0.0;
foreach ($heads as $h) { $total += (float) $h['amount']; }
$cs = get_setting('currency_symbol', 'Rs.');
$schoolName = get_setting('school_name', 'HIIFI LMS');

$message = $message ?? '';
$error = $error ?? '';

include __DIR__ . '/includes/header.php';
?>
<style>
@media print { #Header, #Footer { display: none !important; } .left_col, .top_nav { display: none !important; } body.sidebar-expanded .right_col { margin-left: 0 !important; width: 100% !important; } .btn-no-print { display: none !important; } }
@media print {
  a[href]:after { content: none !important; }
}
  body {
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
    background-color: #FAFAFA;
    font-weight:bold;
    font-size:15px;
}
* {
    box-sizing: border-box;
    -moz-box-sizing: border-box;
}
.page {
    width: 29.7cm;
    height: 21cm;
    padding-left:3mm;
    padding-top:3mm;
    padding-bottom:35mm;
    margin: 10mm auto;
    border: 1px #D3D3D3 solid;
    border-radius: 5px;
    background: white;
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
}
.subpage {
    width: 29.7cm;
    height: 20cm;
}
@media print {
    .page {
        margin: 0;
        border: initial;
        border-radius: initial;
        width: 100%;
        min-height: initial;
        box-shadow: initial;
        page-break-after: blue;
    }
}
ol li {
font-size: 11px;
margin-left: -32px;
}
        .container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            height: 100%;
        }
        .box {
            width:98%;
            padding: 0px;
            margin-top:1%;
            box-sizing: border-box;
            height: 100%;
        }
        .box .header {
            font-size: 16px;
            font-weight: bold;
            height:auto%;
            width: 99%;
            padding:1.5%;
            border:2px black solid;
            border-radius:10px;
        }
        .box .details {
            margin-top:1%;
            font-size: 14px;
            height:auto;
            width: 102%;
            background:;
            border-radius:10px;
        }
        .box .footer {
            font-size: 14px;
            float:left;
            margin-top:1%;
            height:auto%;
            width: 98%;
            padding:1.5%;
            background:;
            border:2px black solid;
            border-radius:6px;
        }
        .rowstyle {line-height: 15px;}
        .thstyle {padding:1.5%;font-size:11px;}
        tr th{padding:2%;font-size:14px;}
        tr td{font-size:14px;padding:2%;font-weight:bold;}
        .tdAlign{text-align:center;border:2px solid black;}
        .tdNames{border:2px solid black;font-size:14px;}
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-money"></i> Inquiry Fee Voucher</h3>
            <a href="<?php echo BASE_URL; ?>student_inquiry.php" class="btn btn-default btn-sm"><i class="fa fa-list"></i> Back to Inquiries</a>
        </div>

        <?php if ($message): ?><div class="alert alert-success" style="padding:8px 14px;font-size:12px;border-radius:8px;margin-bottom:10px;"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger" style="padding:8px 14px;font-size:12px;border-radius:8px;margin-bottom:10px;"><?php echo e($error); ?></div><?php endif; ?>

        <div class="panel panel-default" style="border-radius:12px;overflow:hidden;">
            <div class="panel-body">
                <form method="get" action="inquery_fee_voucher.php" class="form-inline">
                    <div class="form-group" style="min-width:320px;">
                        <label style="font-size:12px;font-weight:700;">Select Inquiry</label>
                        <select name="inquiry_id" class="form-control" onchange="this.form.submit()" style="height:36px;">
                            <option value="0">-- Choose Inquiry --</option>
                            <?php foreach ($inquiries as $iq): ?>
                                <option value="<?php echo (int) $iq['inquiry_id']; ?>" <?php echo $inquiry_id === (int) $iq['inquiry_id'] ? 'selected' : ''; ?>>
                                    #<?php echo $iq['inquiry_id']; ?> - <?php echo e($iq['name']); ?><?php echo $iq['father_name'] ? ' / ' . e($iq['father_name']) : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm btn-no-print" style="border-radius:8px;"><i class="fa fa-eye"></i> Load Voucher</button>
                </form>
            </div>
        </div>

        <?php if (!$inquiry): ?>
            <div class="panel panel-default" style="border-radius:12px;">
                <div class="panel-body" style="text-align:center;color:#6B7280;padding:40px;">Select an inquiry above to generate its fee voucher.</div>
            </div>
        <?php else: ?>
        <div class="btn-no-print" style="margin-bottom:12px;">
            <button type="button" onclick="window.print();" class="btn btn-warning" style="border-radius:8px;"><i class="fa fa-print"></i> Print Voucher</button>
            <form method="post" action="inquery_fee_voucher.php" style="display:inline;">
                <input type="hidden" name="action" value="save_voucher">
                <input type="hidden" name="inquiry_id" value="<?php echo (int) $inquiry['inquiry_id']; ?>">
                <button type="submit" class="btn btn-success" style="border-radius:8px;"><i class="fa fa-floppy-o"></i> Save Voucher</button>
            </form>
        </div>

        <div class="page">
            <div class="subpage">
                <div class="container">
                    <div class="box">
                        <div class="header">
                            <table width="100%" style="border-collapse:collapse;">
                                <tr>
                                    <td align="center" style="font-size:22px;font-weight:bold;"><?php echo e($schoolName); ?></td>
                                </tr>
                                <tr>
                                    <td align="center" style="font-size:13px;font-weight:bold;">Fee Voucher - Admission Inquiry Challan</td>
                                </tr>
                            </table>
                        </div>
                        <div class="details">
                            <table width="100%" style="border-collapse:collapse;" class="rowstyle">
                                <tr>
                                    <td width="50%" class="tdNames">Inquiry No: <?php echo (int) $inquiry['inquiry_id']; ?></td>
                                    <td width="50%" class="tdNames">Date: <?php echo date('d-M-Y', strtotime($inquiry['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <td width="50%" class="tdNames">Student Name: <?php echo e($inquiry['name']); ?></td>
                                    <td width="50%" class="tdNames">Father Name: <?php echo e($inquiry['father_name'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td width="50%" class="tdNames">Applying For Class: <?php echo e($class_name ?: '-'); ?></td>
                                    <td width="50%" class="tdNames">Session: <?php echo e($inquiry['session'] ?? '-'); ?></td>
                                </tr>
                            </table>
                            <br>
                            <table width="100%" style="border-collapse:collapse;">
                                <tr>
                                    <th class="thstyle tdAlign">Fee Head</th>
                                    <th class="thstyle tdAlign">Amount (<?php echo e($cs); ?>)</th>
                                </tr>
                                <?php if (count($heads) === 0): ?>
                                    <tr><td class="tdAlign" colspan="2">No fee heads configured for this class.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($heads as $h): ?>
                                        <tr>
                                            <td class="tdNames tdAlign"><?php echo e($h['head_name']); ?></td>
                                            <td class="tdNames tdAlign" style="text-align:right;"><?php echo number_format((float) $h['amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td class="tdNames tdAlign"><b>Total</b></td>
                                        <td class="tdNames tdAlign" style="text-align:right;"><b><?php echo $cs . ' ' . number_format($total, 2); ?></b></td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="footer">
                            <table width="100%" style="border-collapse:collapse;">
                                <tr>
                                    <td width="50%">Generated By: <?php echo e($_SESSION['user_name'] ?? 'Admin'); ?></td>
                                    <td width="50%">Authorized Signature: ______________________</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>