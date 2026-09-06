<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Students Reports';

$session = '2026';
$res = db_query("SELECT DISTINCT session FROM students WHERE session IS NOT NULL AND session <> '' ORDER BY session DESC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) { $session = $row['session']; }

$printFields = [
    'grNo' => 'GR. No',
    'student_name' => 'Student Name',
    'father_name' => 'Father Name',
    'tution_fee' => 'Monthly Fee',
    'std_transport_fee' => 'Transport Fee',
    'miscellaneous' => 'Misc.',
    'loginid_paswrd' => 'Student Login Id & Password',
    'cell_no' => 'SMS Reporting No',
    'father_cellno' => 'Father No',
    'mother_cell' => 'Mother No',
    'home_number' => 'Home No',
    'father_Bnumber' => 'Father Business No',
    'whatsapp_number' => 'WhatsApp No',
    'address' => 'Address',
    'localities' => 'Localities',
    'dob' => 'DOB',
    'date_admission' => 'Date of Admission',
    'class_admited' => 'Admitted Class',
    'family_code' => 'Family Code',
    'cnic' => 'CNIC',
    'B-Form' => 'B-Form',
    'remarks_column' => 'Remarks',
    'occupation' => 'Occupation',
];

$classesList = [];
$res = db_query("SELECT c.class_id, c.class_name FROM classes c WHERE c.status = 1 OR EXISTS (SELECT 1 FROM students s WHERE s.class_id = c.class_id) ORDER BY c.class_id");
if ($res) { while ($row = $res->fetch_assoc()) { $classesList[] = $row; } }

$classesData = [];
$grandTotal = 0; $grandBoys = 0; $grandGirls = 0;
foreach ($classesList as $cls) {
    $cid = (int) $cls['class_id'];
    $res = db_query("SELECT COUNT(*) t, SUM(gender = 'male') b, SUM(gender = 'female') g FROM students WHERE class_id = $cid");
    $row = $res ? $res->fetch_assoc() : null;
    $total = (int) ($row['t'] ?? 0);
    $boys = (int) ($row['b'] ?? 0);
    $girls = (int) ($row['g'] ?? 0);
    $grandTotal += $total; $grandBoys += $boys; $grandGirls += $girls;

    $sections = [];
    $res = db_query("SELECT sec.section_id, sec.section_name, COUNT(st.student_id) t, SUM(st.gender = 'male') b, SUM(st.gender = 'female') g FROM sections sec LEFT JOIN students st ON st.section_id = sec.section_id AND st.class_id = sec.class_id WHERE sec.class_id = $cid GROUP BY sec.section_id, sec.section_name ORDER BY sec.section_id");
    if ($res) { while ($srow = $res->fetch_assoc()) {
        $sections[] = [
            'id' => (int) $srow['section_id'],
            'name' => $srow['section_name'],
            'total' => (int) ($srow['t'] ?? 0),
            'boys' => (int) ($srow['b'] ?? 0),
            'girls' => (int) ($srow['g'] ?? 0),
        ];
    } }

    $covered = 0;
    foreach ($sections as $s) { $covered += $s['total']; }
    if ($total - $covered > 0) {
        $sections[] = [
            'id' => 0,
            'name' => '-',
            'total' => $total - $covered,
            'boys' => ($boys - $covered === 0) ? 0 : max(0, $boys - $covered),
            'girls' => $total - $covered - max(0, $boys - $covered),
        ];
    }

    $classesData[] = [
        'id' => $cid,
        'name' => $cls['class_name'],
        'total' => $total,
        'boys' => $boys,
        'girls' => $girls,
        'sections' => $sections,
    ];
}

include __DIR__ . '/includes/header.php';
?>
<div class="main-content">
<div class="container-fluid">

<a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i>  &nbsp;
<a href="<?php echo BASE_URL; ?>students_reports_link.php">Students Reports </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i>  &nbsp;
Classwise Students Reports

<div class="nav-container">
  <div class="nav-bar">
    <a href="<?php echo BASE_URL; ?>students_reports.php" class="nav-item active">
      <i class="fa fa-th-list"></i> Classwise Reports
    </a>
    <a href="<?php echo BASE_URL; ?>manage_students.php" class="nav-item ">
      <i class="fa fa-users"></i> View Students
    </a>
    <a href="<?php echo BASE_URL; ?>annual_adm_withdrawReport.php" class="nav-item ">
      <i class="fa fa-chart-bar"></i> Admissions &amp; Withdrawl Report
    </a>
    <a href="<?php echo BASE_URL; ?>classwise_information.php" class="nav-item ">
      <i class="fa fa-file"></i> Student Slips
    </a>
    <a href="<?php echo BASE_URL; ?>locality_reports.php" class="nav-item ">
      <i class="fa fa-map"></i> Locality Reports
    </a>
    <a target="_blank" href="<?php echo BASE_URL; ?>adm_form.php" class="nav-item ">
      <i class="fa fa-file-alt"></i> Admission Form
    </a>
  </div>
</div>
<br><br>

<h3 style="float: left;">Class &amp; Section Wise Report </h3>

<a target="_blank" class="btn btn-success pull-right" title="Print Students List" href="<?php echo BASE_URL; ?>print_students_report.php">
<i class="fa fa-print m-right-xs"></i> &nbsp; Print Report
</a>

<a style="margin-right: 8px;" class="btn btn-primary pull-right" data-toggle="modal" data-target="#ModalAllClasses" href="#" title="Print List All Classes">
<i class="fa fa-print m-right-xs"></i> &nbsp; Print List All Classes
</a>

<a href="<?php echo BASE_URL; ?>watch_video.php?videourl=6q_-ZLWVtaI" target="_blank" class="btn btn-success" style="padding: 5px 5px; font-size:14px;color:white;float: right;"><i class="fa fa-play"></i>&nbsp;&nbsp;Watch Video</a>

<a href="<?php echo BASE_URL; ?>class_drag.php" target="_blank" class="btn btn-success" style="padding: 5px 5px; font-size:14px;color:white;float: right;"><i class="fa fa-sort"></i>&nbsp;&nbsp;Class Order</a>

<style>
#ModalAllClasses .modal-dialog { width: 90%; max-width: 720px; }
#ModalAllClasses .checkbox-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px 16px; margin-top: 8px; }
#ModalAllClasses .checkbox-grid .container1 {padding-bottom:3%; margin-bottom: 0; white-space: nowrap; font-size:12px; }
@media (max-width: 600px) { #ModalAllClasses .checkbox-grid { grid-template-columns: repeat(2, 1fr); } }
.class-parent-row td { background-color:#e3edf9 !important; border-top:2px solid #c9d9ec !important; }
.class-parent-row:hover td { background-color:#d6e5f6 !important; }
.class-child-row-even td { background-color:#ffffff !important; }
.class-child-row-even:hover td { background-color:#f1f1f1 !important; }
.class-child-row-odd td { background-color:#f2f6fb !important; }
.class-child-row-odd:hover td { background-color:#e6edf5 !important; }
</style>

<div id="ModalAllClasses" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?php echo BASE_URL; ?>print_students_report.php" target="_blank" method="get">
        <input type="hidden" name="action" value="ClassPrintParameter">
        <input type="hidden" name="class_id" value="All" />
        <input type="hidden" name="section" value="" />
        <input type="hidden" name="session" value="<?php echo e($session); ?>" />
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title"> Choose The Parameters To Display in Print Report (All Classes) </h4>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label><b>Select Listing Order:</b></label>
            <select name="orderBy" class="form-control" style="max-width: 100%;">
              <option value="GRnoWise">GR. No Wise</option>
              <option value="asc">Alphabetic</option>
              <option value="Classwise">Classwise</option>
              <option value="default">Default</option>
            </select>
          </div>
          <div class="form-group">
            <label><b>Page Heading:</b></label>
            <input type="text" class="form-control" name="studnetlist" value="Students All Classes List"/>
          </div>
          <div class="form-group">
            <label class="container1" style="display: block; margin-bottom: 10px;">
              <input type="checkbox" id="checkAllAllClasses" checked onclick="toggleCheckboxesAllClasses(this)"/>
              <b>Check All</b>
              <span class="checkmark"></span>
            </label>
            <div id="allClassesCheckboxGrid" class="checkbox-grid">
            <?php foreach ($printFields as $name => $label): ?>
              <label class="container1"><input type="checkbox" name="<?php echo $name; ?>" checked value="1"/> <?php echo $label; ?> <span class="checkmark"></span></label>
            <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="modal-footer" style="text-align: right;">
          <input type="submit" class="btn btn-primary" value="Print" />
        </div>
      </form>
    </div>
  </div>
</div>

<input type="hidden" id="sessionValue" value="<?php echo e($session); ?>" />

<table class="table table-striped table-bordered" style="width:100%;background-color:#FFFFFF;">
    <thead>
        <tr>
            <th width="5%" style="text-align:center;"> S.No </th>
            <th width="25%"> Class / Section </th>
            <th width="10%" style="text-align:center;"> Students </th>
            <th width="10%" style="text-align:center;"> Boys </th>
            <th width="10%" style="text-align:center;"> Girls </th>
            <th width="40%" style="text-align:center;">Action</th>
        </tr>
    </thead>
    <tbody>

<?php $sn = 0; foreach ($classesData as $cls): $sn++; $cid = $cls['id']; ?>
<tr class="class-parent-row">
    <td style="text-align:center;"> <?php echo $sn; ?> </td>
    <td>
        <button type="button" class="btn btn-xs btn-default class-toggle-btn" onclick="toggleClassSections('<?php echo $cid; ?>', this)">
            <i class="fa fa-plus"></i>
        </button>
        &nbsp;<b><?php echo e($cls['name']); ?></b>
    </td>
    <td style="text-align:center;"> <?php echo $cls['total']; ?> </td>
    <td style="text-align:center;"><a target="_blank" style="font-size:14px;" href="<?php echo BASE_URL; ?>manage_students.php?class_id=<?php echo $cid; ?>&gender=Male" class="btn btn-success"> <?php echo $cls['boys']; ?> </a></td>
    <td style="text-align:center;"><a target="_blank" style="font-size:14px;" href="<?php echo BASE_URL; ?>manage_students.php?class_id=<?php echo $cid; ?>&gender=Female" class="btn btn-success"> <?php echo $cls['girls']; ?> </a></td>
    <td style="text-align:center;">
        <a target="_blank" style="font-size:14px;" href="<?php echo BASE_URL; ?>manage_students.php?class_id=<?php echo $cid; ?>&session=<?php echo e($session); ?>" class="btn btn-success">View Class</a>
        <div class="btn-group">
            <button data-toggle="dropdown" class="btn btn-warning dropdown-toggle btn-sm" type="button" aria-expanded="true">Action <span class="caret"></span></button>
            <ul role="menu" class="dropdown-menu">
                <li><a style="padding: 6px 8px; font-size:14px;" href="<?php echo BASE_URL; ?>update_student_classwise.php?class_id=<?php echo $cid; ?>" target="_blank">Update Student Info </a></li>
                <li><a target="_blank" style="padding: 6px 8px; font-size:14px;" href="<?php echo BASE_URL; ?>update_student_pictures.php?class_id=<?php echo $cid; ?>&session=<?php echo e($session); ?>">Update Pictures</a></li>
                <li><a target="_blank" style="padding: 6px 8px; font-size:14px;" href="<?php echo BASE_URL; ?>fee_settings.php?class_id=<?php echo $cid; ?>&session=<?php echo e($session); ?>">Update Monthly Fee</a></li>
                <li><a style="padding: 6px 8px; font-size:14px;" data-toggle="modal" data-target="#AdmitCardModalClass<?php echo $cid; ?>" href="#">Admit Cards</a></li>
                <li><a style="padding: 6px 8px; font-size:14px;" data-toggle="modal" data-target="#ModalClass<?php echo $cid; ?>" href="#">Print List</a></li>
                <li><a style="padding: 6px 8px; font-size:14px;" href="<?php echo BASE_URL; ?>studentsInfo_export_excel.php?class_id=<?php echo $cid; ?>">Export</a></li>
            </ul>

            <div id="ModalClass<?php echo $cid; ?>" class="modal fade" role="dialog">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form action="<?php echo BASE_URL; ?>print_students_report.php" target="_blank" method="get">
                    <input type="hidden" name="action" value="ClassPrintParameter">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal">&times;</button>
                      <h4 class="modal-title"> Choose The Parametters To Display in Print Report </h4>
                    </div>
                    <div class="modal-body">
                      <input type="hidden" name="class_id" value="<?php echo $cid; ?>" />
                      <input type="hidden" name="section" value="" />
                      <input type="hidden" name="session" value="<?php echo e($session); ?>" />
                      <label style="text-align: left; float: left;"> <b>Select Listing Order:</b> </label>
                      <select name="orderBy" style="width: 562px;" id="orderBy" class="form-control">
                        <option value="GRnoWise">GR. No Wise</option>
                        <option value="asc">Alphabetic</option>
                        <option value="default">Default</option>
                      </select>
                      <br>
                      <label style="text-align: left;"> <b>Page Heading:</b>
                        <input type="text" style="width: 562px; height:40px;" name="studnetlist" value="Students Class Wise List"/>
                      </label>
                      <br><br>
                      <label class="container1" style="text-align: left;">
                        <input type="checkbox" checked onclick="toggleCheckboxes(this)"/>
                        Check All
                        <span class="checkmark"></span>
                      </label>
                      <?php foreach ($printFields as $name => $fieldLabel): ?>
                      <label class="container1" style="text-align: left;">
                        <input type="checkbox" name="<?php echo $name; ?>" checked value="1"/>
                        <?php echo $fieldLabel; ?>
                        <span class="checkmark"></span>
                      </label>
                      <?php endforeach; ?>
                    </div>
                    <div class="modal-footer" style="text-align: right;">
                      <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                      <input type="submit" class="btn btn-primary" value="Print" />
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <div id="AdmitCardModalClass<?php echo $cid; ?>" class="modal fade" role="dialog">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form action="<?php echo BASE_URL; ?>admit_cards.php" target="_blank" method="get">
                    <input type="hidden" name="class_id" value="<?php echo $cid; ?>" />
                    <input type="hidden" name="section" value="" />
                    <input type="hidden" name="session" value="<?php echo e($session); ?>" />
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal">&times;</button>
                      <h4 class="modal-title">Admit Card - Select Fields to Display</h4>
                    </div>
                    <div class="modal-body">
                      <label class="container1" style="text-align: left;">
                        <input type="checkbox" name="grno" checked value="1"/>
                        Gr.No
                        <span class="checkmark"></span>
                      </label>
                      <div style="display:flex; align-items:center; gap:10px;">
                        <label class="container1" style="text-align: left; margin-bottom:0; white-space:nowrap;">
                          <input type="checkbox" name="exam_title" checked value="1"/>
                          Exam Title
                          <span class="checkmark"></span>
                        </label>
                        <input type="text" name="exam_title_text" class="form-control" placeholder="Enter Exam Title" style="width:100%;" />
                      </div>
                      <label class="container1" style="text-align: left;">
                        <input type="checkbox" name="cast" checked value="1"/>
                        Cast
                        <span class="checkmark"></span>
                      </label>
                      <label class="container1" style="text-align: left;">
                        <input type="checkbox" name="seat_no" checked value="1"/>
                        Seat No
                        <span class="checkmark"></span>
                      </label>
                    </div>
                    <div class="modal-footer" style="text-align: right;">
                      <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                      <input type="submit" class="btn btn-primary" value="Submit" />
                    </div>
                  </form>
                </div>
              </div>
            </div>

        </div>
    </td>
</tr>

<?php $rIdx = 0; foreach ($cls['sections'] as $sec): $rIdx++; $parity = $rIdx % 2 === 1 ? 'even' : 'odd'; $sid = $sec['id']; ?>
<tr class="class-child-row class-child-row-<?php echo $parity; ?> class-child-<?php echo $cid; ?>" style="display:none;">
    <td style="text-align:center;"></td>
    <td style="padding-left:30px;">
        &#8627; <?php echo e($sec['name']); ?>
    </td>
    <td style="text-align:center;"> <?php echo $sec['total']; ?> </td>
    <td style="text-align:center;"><a target="_blank" style="font-size:14px;" href="<?php echo BASE_URL; ?>manage_students.php?class_id=<?php echo $cid; ?>&section=<?php echo $sid; ?>&gender=Male" class="btn btn-success"><?php echo $sec['boys']; ?></a></td>
    <td style="text-align:center;"><a target="_blank" style="font-size:14px;" href="<?php echo BASE_URL; ?>manage_students.php?class_id=<?php echo $cid; ?>&section=<?php echo $sid; ?>&gender=Female" class="btn btn-success"><?php echo $sec['girls']; ?></a></td>
    <td style="text-align:center;">
        <a target="_blank" style="font-size:14px;" href="<?php echo BASE_URL; ?>manage_students.php?class_id=<?php echo $cid; ?>&section=<?php echo $sid; ?>&session=<?php echo e($session); ?>" class="btn btn-success">View Class</a>
        <div class="btn-group">
            <button data-toggle="dropdown" class="btn btn-warning dropdown-toggle btn-sm" type="button" aria-expanded="true">Action <span class="caret"></span></button>
            <ul role="menu" class="dropdown-menu">
                <li><a style="padding: 6px 8px; font-size:14px;" href="<?php echo BASE_URL; ?>update_student_classwise.php?class_id=<?php echo $cid; ?>&section=<?php echo $sid; ?>" target="_blank">Update Student Info</a></li>
                <li><a target="_blank" style="padding: 6px 8px; font-size:14px;" href="<?php echo BASE_URL; ?>update_student_pictures.php?class_id=<?php echo $cid; ?>&session=<?php echo e($session); ?>&section=<?php echo $sid; ?>">Update Pictures</a></li>
                <li><a target="_blank" style="padding: 6px 8px; font-size:14px;" href="<?php echo BASE_URL; ?>fee_settings.php?class_id=<?php echo $cid; ?>&session=<?php echo e($session); ?>&section=<?php echo $sid; ?>">Update Monthly Fee</a></li>
                <li><a style="padding: 6px 8px; font-size:14px;" data-toggle="modal" data-target="#AdmitCardModal<?php echo $cid; ?>-<?php echo $sid; ?>" href="#">Admit Cards</a></li>
                <li><a style="padding: 6px 8px; font-size:14px;" data-toggle="modal" data-target="#Modal<?php echo $cid; ?>-<?php echo $sid; ?>" href="#">Print List</a></li>
                <li><a style="padding: 6px 8px; font-size:14px;" href="<?php echo BASE_URL; ?>studentsInfo_export_excel.php?class_id=<?php echo $cid; ?>&section=<?php echo $sid; ?>">Export</a></li>
            </ul>

            <div id="Modal<?php echo $cid; ?>-<?php echo $sid; ?>" class="modal fade" role="dialog">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form action="<?php echo BASE_URL; ?>print_students_report.php" target="_blank" method="get">
                    <input type="hidden" name="action" value="ClassPrintParameter">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal">&times;</button>
                      <h4 class="modal-title"> Choose The Parametters To Display in Print Report </h4>
                    </div>
                    <div class="modal-body">
                      <input type="hidden" name="class_id" value="<?php echo $cid; ?>" />
                      <input type="hidden" name="section" value="<?php echo $sid; ?>" />
                      <input type="hidden" name="session" value="<?php echo e($session); ?>" />
                      <label style="text-align: left; float: left;"> <b>Select Listing Order:</b> </label>
                      <select name="orderBy" style="width: 562px;" id="orderBy" class="form-control">
                        <option value="GRnoWise">GR. No Wise</option>
                        <option value="asc">Alphabetic</option>
                        <option value="default">Default</option>
                      </select>
                      <br>
                      <label style="text-align: left;"> <b>Page Heading:</b>
                        <input type="text" style="width: 562px; height:40px;" name="studnetlist" value="Students Class Wise List"/>
                      </label>
                      <br><br>
                      <label class="container1" style="text-align: left;">
                        <input type="checkbox" checked onclick="toggleCheckboxes(this)"/>
                        Check All
                        <span class="checkmark"></span>
                      </label>
                      <?php foreach ($printFields as $name => $fieldLabel): ?>
                      <label class="container1" style="text-align: left;">
                        <input type="checkbox" name="<?php echo $name; ?>" checked value="1"/>
                        <?php echo $fieldLabel; ?>
                        <span class="checkmark"></span>
                      </label>
                      <?php endforeach; ?>
                    </div>
                    <div class="modal-footer" style="text-align: right;">
                      <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                      <input type="submit" class="btn btn-primary" value="Print" />
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <div id="AdmitCardModal<?php echo $cid; ?>-<?php echo $sid; ?>" class="modal fade" role="dialog">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form action="<?php echo BASE_URL; ?>admit_cards.php" target="_blank" method="get">
                    <input type="hidden" name="class_id" value="<?php echo $cid; ?>" />
                    <input type="hidden" name="section" value="<?php echo $sid; ?>" />
                    <input type="hidden" name="session" value="<?php echo e($session); ?>" />
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal">&times;</button>
                      <h4 class="modal-title">Admit Card - Select Fields to Display</h4>
                    </div>
                    <div class="modal-body">
                      <label class="container1" style="text-align: left;">
                        <input type="checkbox" name="grno" checked value="1"/>
                        Gr.No
                        <span class="checkmark"></span>
                      </label>
                      <div style="display:flex; align-items:center; gap:10px;">
                        <label class="container1" style="text-align: left; margin-bottom:0; white-space:nowrap;">
                          <input type="checkbox" name="exam_title" checked value="1"/>
                          Exam Title
                          <span class="checkmark"></span>
                        </label>
                        <input type="text" name="exam_title_text" class="form-control" placeholder="Enter Exam Title" style="width:100%;" />
                      </div>
                      <label class="container1" style="text-align: left;">
                        <input type="checkbox" name="cast" checked value="1"/>
                        Cast
                        <span class="checkmark"></span>
                      </label>
                      <label class="container1" style="text-align: left;">
                        <input type="checkbox" name="seat_no" checked value="1"/>
                        Seat No
                        <span class="checkmark"></span>
                      </label>
                    </div>
                    <div class="modal-footer" style="text-align: right;">
                      <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                      <input type="submit" class="btn btn-primary" value="Submit" />
                    </div>
                  </form>
                </div>
              </div>
            </div>

        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php endforeach; ?>

    </tbody>
    <tfoot>
        <tr style="background:#f6f9fc; font-weight:700;">
            <td colspan="2" style="text-align:right;">Overall Strength (All Classes):</td>
            <td style="text-align:center;"><?php echo $grandTotal; ?></td>
            <td style="text-align:center;"><?php echo $grandBoys; ?></td>
            <td style="text-align:center;"><?php echo $grandGirls; ?></td>
            <td style="text-align:center;">All Classes: <?php echo count($classesList); ?></td>
        </tr>
    </tfoot>
</table>

</div>
</div>

<script>
function toggleCheckboxes(source) {
  let checkboxes = document.querySelectorAll('.container1 input[type="checkbox"]');
  checkboxes.forEach(checkbox => {
    checkbox.checked = source.checked;
  });
}
function toggleCheckboxesAllClasses(source) {
  let modal = document.getElementById('ModalAllClasses');
  if (!modal) return;
  let checkboxes = modal.querySelectorAll('.container1 input[type="checkbox"]:not(#checkAllAllClasses)');
  checkboxes.forEach(checkbox => {
    checkbox.checked = source.checked;
  });
}
function toggleClassSections(classId, btn) {
  let rows = document.querySelectorAll('.class-child-' + classId);
  if (!rows.length) return;
  let show = rows[0].style.display === 'none';
  rows.forEach(function (row) {
    row.style.display = show ? '' : 'none';
  });
  let icon = btn.querySelector('i');
  if (icon) {
    icon.classList.toggle('fa-plus', !show);
    icon.classList.toggle('fa-minus', show);
  }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>