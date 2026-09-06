<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$schoolName = get_setting('school_name', 'HIIFI LMS');
?>
<!DOCTYPE HTML>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Print blank inquiry sheet Receipt</title>
<link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/img/favicon.png" sizes="32x32" />
<script type="text/javascript">
// window.print();
</script>
<style type="text/css">

tr th {border-left:1px solid black;border-top:1px solid black;font-size:14px;padding: 18px;}
tr td {border-bottom:1px solid black;border-left:1px solid black;font-size:16px;padding: 18px;}

.border{border:1px solid black;}

.alignment{padding-left:1%;text-align:left;}

.borderbottom{
  border-bottom:1px solid black;
}



@media print {
    #Header, #Footer { display: none !important; }
}
@media screen
   {
      p.bodyText {font-family:verdana, arial, sans-serif;}
   }

   @media print
   {
      p.bodyText {font-family:georgia, times, serif;}
   }
   @media screen, print
   {
      p.bodyText {font-size:10pt}
   }
   @media print {
  a[href]:after {
    content: none !important;
  }
}
.center{text-align:center;}
</style>
</head>

<body style="font-size:12px;padding-right:1%;padding-left:1%;padding-top:3%;padding-bottom:1%;">
<div style="text-align:center;font-size:24px;font-weight:bold;margin:0 auto;"><?php echo e($schoolName); ?>
</div>

<br>

<div style="text-align:center;font-size:18px;font-weight:bold;"> STUDENT INQUIRY SHEET

 </div>

<hr style="width:100%;">



<div style="width:33%; float:left;font-size:18px; margin-left:10px; "> <b> Date:</b> <?php echo date('d-M-Y'); ?> </div>


<br>


    <table id="" data-page-length='100' class="table table-striped table-bordered" style="width:100%;font-size:11px;">

          <thead>


            <tr>
                <th width="5%" class="borderbottom">SR</th>
                <th width="15%" class="borderbottom">Student Name</th>
                <th width="15%" class="borderbottom">Father Name</th>
                <th width="15%" class="borderbottom">Contact No</th>
                <th width="15%" class="borderbottom">Apply Class</th>
                <th width="35%" class="borderbottom" style="border-right:1px solid black;">Address</th>

            </tr>
        </thead>
        <tbody>

<?php for ($i = 1; $i <= 13; $i++): ?>
<tr>
      <td style="text-align:center; padding: 2px;"><?php echo $i; ?></td>
      <td style="padding-left:1px;" ></td>
      <td style="padding-left:1px;">  </td>
      <td> </td>
      <td> </td>
      <td style="border-right:1px solid black;"> </td>
</tr>
<?php endfor; ?>

        </tbody>

    </table>



</body>
</html>