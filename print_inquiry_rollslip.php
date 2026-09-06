<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$inquiry_id = (int) ($_GET['inquiry_id'] ?? 0);

$inquiry = null;
if ($inquiry_id > 0) {
    $st = db_prepare("SELECT i.*, c.class_name FROM student_inquiries i LEFT JOIN classes c ON i.class_id=c.class_id WHERE i.inquiry_id=?");
    $st->bind_param('i', $inquiry_id);
    $st->execute();
    $inquiry = $st->get_result()->fetch_assoc();
    if (!$inquiry) { $inquiry_id = 0; }
}

$schoolName = get_setting('school_name', 'HIIFI LMS');
$schoolAddress = get_setting('school_address', '');
$sessionYear = (string) get_setting('session_year', '2026-2027');
$year = preg_match('/^(\d{4})/', $sessionYear, $ym) ? $ym[1] : date('Y');

$studentName = $inquiry ? inquiry_str($inquiry['name']) : '--';
$fatherName = $inquiry ? inquiry_str($inquiry['father_name']) : '--';
$classAdmission = $inquiry ? inquiry_str($inquiry['class_name']) : '--';
$contactNo = $inquiry ? (inquiry_str($inquiry['phone'] ?: $inquiry['father_cellno'])) : '--';
$previousSchool = $inquiry ? '' : '';
$dateOfTest = $inquiry && $inquiry['test_date'] ? date('d-M-Y', strtotime($inquiry['test_date'])) : '--';
$inquiryNo = $inquiry ? str_pad((string) $inquiry_id, 4, '0', STR_PAD_LEFT) : '--';
$serialNo = $inquiry ? 'RS-' . $inquiry_id . '-' . $year : '--';

function inquiry_str($val) {
    return $val !== null && $val !== '' ? $val : '--';
}
?>
 <!DOCTYPE HTML>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/img/favicon.png" sizes="32x32" />
<title> <?php echo e($schoolName); ?> | Roll No Slip</title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/font-awesome-5.min.css">
<script type="text/javascript">
   //window.print();
</script>
<style>
.fieldStyle{border-bottom: 1px solid black;text-align:left;border-color: #0067DE}
.tdStyle{border-bottom:1px solid lightgray;}
.thStyle{background-color:gray;color:white;}
.section{width:100%;float:left;margin:0 auto;padding:2%;margin-left:1%;}

 tr td {

    padding:1%;
    text-align:left;
    color: #0067DE;
 }
  tr th {

    padding:2%;
  }
.tdalign{text-align:center;border:1px solid black;}

</style>
  <style>
      body {
        width: 100%;
        height: auto;
        margin: 0;
        padding: 0;
        background-color: white;
        font: 12pt "Tahoma";
        color: #042954;
    }
    * {
        box-sizing: border-box;
        -moz-box-sizing: border-box;
    }
    .page {
        width: 297mm;
        height:auto;
        padding: 1mm;
        margin: 30mm auto 10mm auto;
        /*border: 1px #D3D3D3 solid;*/
        border-radius: 5px;
        background: white;
        /*box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);*/
    }
    .subpage {
        width: 285mm;
        height:auto;

    }

    @page {
        size: A4 portrait;
        margin: 8mm 0 0 0;
    }
    @media print {
        html, body {
            width:297mm;
            height:auto;
        }
        .page {
            margin: 8mm auto 0 auto;
            border: initial;
            border-radius: initial;
            width: initial;
            min-height: initial;
            box-shadow: initial;
            background: initial;
            page-break-after: blue;
            padding-top: 5mm;
        }
    }
  </style>
</head>
<body>
  <div class="book">
    <div class="page">
        <div class="subpage">
 <!-- Vouchere Fist -->
<div class="section">

   <div style="width:100%; float:left; margin-bottom:20px;">

<!-- Header Row with Logo and Branch Info -->
<div style="display:flex; align-items:flex-start; margin-bottom:15px;">

    <!-- Left: Logo -->
    <div style="width:15%; text-align:left; padding-right:20px;">
                    <img src="<?php echo BASE_URL; ?>assets/img/logo.jpg"
                 style="height:120px; width:auto;" />
            </div>

    <!-- Right: Branch Name, Location & Title -->
    <div style="width:85%; text-align:center;">
        <!-- Branch Name -->
        <div style="font-size:40px; font-family:'Times New Roman', serif; font-weight:bold; color:#000; font-style:italic;">
            <?php echo e($schoolName); ?>        </div>

        <!-- Branch Location -->
        <div style="font-size:14px; color:#000; margin-top:2px; font-family:Arial, sans-serif;">
            <?php echo e($schoolAddress); ?>        </div>

        <!-- Roll Number Slip Title -->
        <div style="margin-top:8px;">
            <strong style="color:#fff; background-color:#000; padding:6px 80px; font-size:18px; font-family:Arial, sans-serif; letter-spacing:1px;">
                Roll Number Slip
            </strong>
        </div>
    </div>
</div>



<!-- Student Information Header -->
<div style="width:100%; background-color:#000; text-align:center; margin-top:10px; padding:10px 0;">
    <strong style="color:#fff; font-size:18px; font-family:Arial, sans-serif; letter-spacing:1px; font-style:italic;">
        Student Information
    </strong>
</div>

<!-- Student Information Table -->
<table width="100%" style="font-size:18px; margin-top:15px; border-collapse:collapse; font-family:'Times New Roman', serif;">
    <tbody>
        <tr>
            <td width="20%" style="padding:15px 10px; font-weight:bold; font-size:20px; color:#000;">Student Name:</td>
            <td width="30%" style="padding:15px 10px; border-bottom:2px solid #000; font-size:19px; color:#000;">
                <?php echo e($studentName); ?>            </td>
            <td width="20%" style="padding:15px 10px; font-weight:bold; font-size:20px; color:#000;">Inquiry No:</td>
            <td width="30%" style="padding:15px 10px; border-bottom:2px solid #000; font-size:19px; color:#000;">
                <?php echo e($inquiryNo); ?>            </td>
        </tr>

        <tr>
            <td width="20%" style="padding:15px 10px; font-weight:bold; font-size:20px; color:#000;">Father Name:</td>
            <td width="30%" style="padding:15px 10px; border-bottom:2px solid #000; font-size:19px; color:#000;">
                <?php echo e($fatherName); ?>            </td>
            <td width="20%" style="padding:15px 10px; font-weight:bold; font-size:20px; color:#000;">Serial No:</td>
            <td width="30%" style="padding:15px 10px; border-bottom:2px solid #000; font-size:19px; color:#000;">
                <?php echo e($serialNo); ?>            </td>
        </tr>

        <tr>
            <td width="20%" style="padding:15px 10px; font-weight:bold; font-size:20px; color:#000;">Class Admission:</td>
            <td width="30%" style="padding:15px 10px; border-bottom:2px solid #000; font-size:19px; color:#000;">
                <?php echo e($classAdmission); ?>            </td>
            <td width="20%" style="padding:15px 10px; font-weight:bold; font-size:20px; color:#000;">Contact No:</td>
            <td width="30%" style="padding:15px 10px; border-bottom:2px solid #000; font-size:19px; color:#000;">
                <?php echo e($contactNo); ?>            </td>
        </tr>

        <tr>
            <td width="20%" style="padding:15px 10px; font-weight:bold; font-size:20px; color:#000;">Previous School:</td>
            <td width="30%" style="padding:15px 10px; border-bottom:2px solid #000; font-size:19px; color:#000;">
                <?php echo e($previousSchool); ?>            </td>
            <td width="20%" style="padding:15px 10px; font-weight:bold; font-size:20px; color:#000;">Date of Test:</td>
            <td width="30%" style="padding:15px 10px; border-bottom:2px solid #000; font-size:19px; color:#000;">
                <?php echo e($dateOfTest); ?>            </td>
        </tr>
    </tbody>
</table>

<!-- Bottom Black Line with Instructions -->
<div style="width:100%; border-bottom:6px solid #000; margin-top:30px;"></div>
<div style="width:100%; text-align:center; margin-top:15px;">
    <strong style="font-size:20px; font-family:Arial, sans-serif; letter-spacing:3px; color:#000;">
        INSTRUCTIONS
    </strong>
</div>

    <div style="margin-top:15px; padding:0 10px; font-size:13px; line-height:1.8; color:#000;">
        <h3><strong>Entry Test Instructions</strong></h3>

<p><strong>1.</strong> Test will be conducted only on the above mentioned date.<br />
<strong>2.</strong> Students are bound to bring their Roll No Slip, Clipboard and Stationery with them throughout the entry test.<br />
<strong>3.</strong> A fine of Rs.100/- will be charged for duplicate Roll No Slip.<br />
<strong>4.</strong> There will be no chance of Re-Test.<br />
<strong>5.</strong> Don’t give irrelevant answers in the answer book and don’t misbehave with the supervisory staff.<br />
<strong>6.</strong> 60% marks in each subject of the written paper are required to qualify.<br />
<strong>7.</strong> Parents are not allowed to take the marked test with them.<br />
<strong>8.</strong> Parents can see the checked test/result of their children within three days. After that, no arguments will be acceptable.<br />
<strong>9.</strong> Candidates are directed to report 30 minutes before test time in the office.<br />
<strong>10.</strong> In case of selection, please collect the admission form from the office within two days; otherwise, the seat will be transferred to the next waiting list.<br />
<strong>11.</strong> In case of admission in a new class, the following documents are required:<br />
&emsp;&emsp;• Birth Certificate or B-Form<br />
&emsp;&emsp;• School Leaving Certificate<br />
&emsp;&emsp;• Father’s CNIC<br />
<strong>12.</strong> Candidates must avoid disturbing the peace in and around the examination hall.<br />
<strong>13.</strong> I have solemnly read the rules and regulations mentioned beside the school gate.</p>
    </div>



  </div>
    </div>
        </div>
    </div>
  </div>
</body>
</html>