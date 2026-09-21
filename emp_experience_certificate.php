<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$emp_id = (int) ($_GET['emp_id'] ?? 0);
$emp = null;
if ($emp_id > 0) {
    $emp = db_query("SELECT * FROM employees WHERE emp_id=$emp_id AND status IN (0,1)")->fetch_assoc();
}

$schoolName = get_setting('school_name', 'School Name');
$schoolAddr = get_setting('school_address', '');
$schoolPhone = get_setting('school_phone', '');

function ord_date($d) {
    $ts = strtotime($d);
    $day = (int) date('j', $ts);
    $suffix = 'th';
    if ($day % 10 == 1 && $day != 11) { $suffix = 'st'; }
    elseif ($day % 10 == 2 && $day != 12) { $suffix = 'nd'; }
    elseif ($day % 10 == 3 && $day != 13) { $suffix = 'rd'; }
    return $day . $suffix . date(' F Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Experience Certificate | <?php echo e($schoolName); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f5; font-family: 'Segoe UI', 'Times New Roman', Arial, sans-serif; color: #111827; }
        .toolbar { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 5; }
        .toolbar h3 { margin: 0; font-size: 16px; font-weight: 800; }
        .toolbar .btn { border: 0; border-radius: 8px; padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer; color: #fff; text-decoration: none; display: inline-block; }
        .btn-print { background: #16A34A; margin-right: 8px; }
        .btn-back { background: #377DFF; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
        }
        .cert { max-width: 720px; margin: 18px auto; background: #fff; border: 3px double #111827; padding: 30px 40px; }
        .chead { text-align: center; }
        .chead .school { font-size: 24px; font-weight: 900; letter-spacing: 0.5px; }
        .chead .sub { font-size: 13px; color: #4B5563; margin-top: 2px; }
        .heading { text-align: center; font-size: 17px; font-weight: 800; text-decoration: underline; margin: 22px 0; letter-spacing: 1px; }
        .meta { font-size: 12.5px; margin-top: 4px; display: flex; justify-content: space-between; }
        .btext { font-size: 14.5px; line-height: 1.9; text-align: justify; margin-top: 14px; }
        .duties { margin: 10px 0 0 0; padding-left: 22px; font-size: 14px; line-height: 1.8; text-align: justify; }
        .duties li { margin-bottom: 2px; }
        .close { font-size: 14.5px; margin-top: 12px; text-align: justify; }
        .sig { text-align: right; margin-top: 48px; font-size: 13.5px; }
        .sig .line { border-bottom: 1px solid #374151; display: inline-block; width: 150px; margin-bottom: 6px; }
        .no-records { background:#fff; border:1px solid #E5E7EB; border-radius:12px; padding:40px; text-align:center; color:#6B7280; max-width:720px; margin:18px auto; }
        .vpower { margin-top: 44px; text-align: center; font-size: 11px; color: #9CA3AF; letter-spacing: 0.3px; }
    </style>
</head>
<body>

<div class="toolbar">
    <h3><i class="fa fa-certificate"></i> Experience Certificate</h3>
    <div>
        <button class="btn btn-print" onclick="window.print()">Print Certificate</button>
        <a class="btn btn-back" href="<?php echo BASE_URL; ?>view_emp.php">Back to Employees</a>
    </div>
</div>

<?php if (!$emp): ?>
    <div class="no-records">Employee not found. <a href="<?php echo BASE_URL; ?>view_emp.php">Back to list</a></div>
<?php else: ?>
    <?php
    $fullName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
    $father = trim($emp['father_name'] ?? '');
    $gender = strtolower(trim($emp['gender'] ?? ''));
    $isFemale = ($gender === 'female');
    $mrMiss = $isFemale ? 'Miss.' : 'Mr.';
    $sonOf = $isFemale ? 'D/O' : 'S/O';
    $his = $isFemale ? 'her' : 'his';
    $Him = $isFemale ? 'She' : 'He';
    $jDate = $emp['joining_date'] ? ord_date($emp['joining_date']) : '-';
    $toLabel = 'to date';
    if (!empty($emp['contract_end'])) {
        $toLabel = 'to ' . ord_date($emp['contract_end']);
    } elseif ((int) $emp['status'] === 0) {
        $toLabel = 'to ' . date('jS F Y');
    }
    $designation = trim($emp['designation'] ?? '');
    if ($designation === '') { $designation = '-'; }
    $regNo = trim($emp['reg_no'] ?? '');
    $refNo = $regNo !== '' ? $regNo : str_pad((string) $emp['emp_id'], 4, '0', STR_PAD_LEFT);
    $duties = array(
        'Teach one or more subjects to students in public or private secondary schools.',
        'Administer tests to evaluate pupil progress, records results, and issues reports to inform parents of progress.',
        'Keep attendance records',
        'Maintain discipline in classroom.',
        'Meet with parents to discuss student progress problems.',
        'Participate in faculty professional meetings, educational conferences, and teacher training workshops.',
        'Prepare course materials like syllabi, homework assignments, and handouts',
        'Plan, evaluate, and revise curricula, course content, and course materials methods of instruction'
    );
    ?>
    <div class="cert">
        <div class="chead">
            <div class="school"><?php echo e($schoolName); ?></div>
            <?php if ($schoolAddr !== ''): ?><div class="sub"><?php echo e($schoolAddr); ?></div><?php endif; ?>
            <?php if ($schoolPhone !== ''): ?><div class="sub">Contact: <?php echo e($schoolPhone); ?></div><?php endif; ?>
        </div>

        <div class="heading">TO WHOM IT MAY CONCERN</div>

        <div class="meta">
            <span><strong>Ref No:</strong> <?php echo e($refNo); ?></span>
            <span><strong>Date:</strong> <?php echo date('d M Y'); ?></span>
        </div>

        <div class="btext">
            This is to certify that <?php echo $mrMiss . ' ' . e($fullName); ?> <?php echo $sonOf; ?> <?php echo e($father); ?>, has been
            associated with our <strong><?php echo e($schoolName); ?></strong> as a <strong><?php echo e($designation); ?></strong>
            since <strong><?php echo e($jDate); ?></strong> <?php echo e($toLabel); ?>. During <?php echo $his; ?> term of employment,
            <?php echo $Him; ?> was found satisfactory while rendering duties such as:
        </div>

        <ul class="duties">
            <?php foreach ($duties as $d): ?>
                <li><?php echo e($d); ?></li>
            <?php endforeach; ?>
        </ul>

        <div class="close">
            We wish the best for <?php echo $his; ?> future endeavors.
        </div>

        <div class="sig">
            <div class="line"></div>
            <div><strong>PRINCIPAL SIGNATURE</strong></div>
            <div style="color:#6B7280; font-size:12px;"><?php echo e($schoolName); ?></div>
        </div>

        <div class="vpower">Powered by: <?php echo e(get_setting('software_name', 'HIFI')); ?></div>
    </div>
<?php endif; ?>

</body>
</html>