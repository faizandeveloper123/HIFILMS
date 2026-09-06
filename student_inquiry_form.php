<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Add Student Inquiry';

$classes = [];
$res = db_query("SELECT class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sections = [];
$res = db_query("SELECT section_id, class_id, section_name FROM sections ORDER BY section_name");
while ($row = $res->fetch_assoc()) { $sections[] = $row; }

$localities = [];
$res = db_query("SELECT locality_id, locality_name FROM localities WHERE status=1 ORDER BY locality_name");
while ($row = $res->fetch_assoc()) { $localities[] = $row; }

$occupations = [];
$res = db_query("SELECT id, name FROM occupations ORDER BY name");
while ($row = $res->fetch_assoc()) { $occupations[] = $row; }

$references = [1 => 'Friends', 2 => 'Local Market', 3 => 'Social Media', 4 => 'Banner', 5 => 'Siblings', 6 => 'Students', 7 => 'Other'];

$nextInquiryNo = (int) (db_query("SELECT MAX(inquiry_id) m FROM student_inquiries")->fetch_assoc()['m'] ?? 0) + 1;

$feeHeads = [];
$res = db_query("SELECT head_id, head_name, amount, class_id, status FROM fee_heads WHERE status=1 ORDER BY head_id");
while ($row = $res->fetch_assoc()) { $feeHeads[] = $row; }
$feeMap = [];
foreach ($feeHeads as $fh) { $feeMap[(string)($fh['class_id'] === null ? 'g' : $fh['class_id'])][] = $fh; }
$feeByClass = [];
foreach ($classes as $c) {
    $cid = (string) $c['class_id'];
    $heads = $feeMap[$cid] ?? ($feeMap['g'] ?? []);
    $monthly = 0.0; $misc = 0.0; $first = true;
    foreach ($heads as $h) {
        if ($first) { $monthly += (float) $h['amount']; $first = false; }
        else { $misc += (float) $h['amount']; }
    }
    $feeByClass[$cid] = [round($monthly), round($misc)];
}

$message = '';
$error = '';
$lastInquiryId = 0;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && trim($_POST['action'] ?? '') === 'addNewInquiry') {
    $name = trim($_POST['student_name'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $phone = trim($_POST['std_cell'] ?? '');
    $father_cellno = trim($_POST['cell'] ?? '');
    $class_id = (int) ($_POST['class_id'] ?? 0) > 0 ? (int) $_POST['class_id'] : null;
    $section_val = trim($_POST['section'] ?? 'All');
    $section_id = is_numeric($section_val) && (int) $section_val > 0 ? (int) $section_val : null;
    $visit_date = trim($_POST['inquiry_date'] ?? '') !== '' ? trim($_POST['inquiry_date']) : null;
    $locality = trim($_POST['Locality'] ?? '');
    $admission_source = (int) ($_POST['refrence'] ?? 0) > 0 ? ($references[(int) $_POST['refrence']] ?? '') : '';
    $test_text = trim($_POST['test_date'] ?? '');
    $test_date = '';
    if ($test_text !== '') {
        $parsed = date('Y-m-d', strtotime($test_text));
        if ($parsed !== '' && $parsed !== '1970-01-01') { $test_date = $parsed; }
    }
    $address = trim($_POST['address'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    if ($name === '') { $error = 'Student Name is required.'; }
    elseif ($class_id === null) { $error = 'Please select a class.'; }
    else {
        $session = trim($_POST['current_session'] ?? '') !== '' ? trim($_POST['current_session']) : (string) get_setting('session_year', '2026-2027');
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        $st = db_prepare("INSERT INTO student_inquiries (name, father_name, phone, father_cellno, email, class_id, section_id, session, admission_source, locality, address, visit_date, test_date, remarks, status, created_by) VALUES (?, ?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?)");
        $st->bind_param('ssssiisssssssi', $name, $father_name, $phone, $father_cellno, $class_id, $section_id, $session, $admission_source, $locality, $address, $visit_date, $test_date, $remarks, $uid);
        $st->execute();
        $lastInquiryId = (int) $st->insert_id;
        $message = 'Inquiry added successfully! Inquiry #' . $lastInquiryId .
            '  <a href="' . BASE_URL . 'print_student_inquiry_form.php?inquiry_id=' . $lastInquiryId . '" class="btn btn-xs btn-info" target="_blank"><i class="fa fa-print"></i> Print Inquiry Form</a> ' .
            '<a href="' . BASE_URL . 'print_inquiry_rollslip.php?inquiry_id=' . $lastInquiryId . '" class="btn btn-xs btn-warning" target="_blank"><i class="fa fa-print"></i> Print Roll Slip</a> ' .
            '<a href="' . BASE_URL . 'student_inquiry.php" class="btn btn-xs btn-default"><i class="fa fa-list"></i> Back to Inquiries</a>';
    }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.tdStyle{border-bottom:1px solid lightgray;}
.thStyle{background-color:#00AFEF;color:white;}
:root{
  --pri:#0b47be;
  --pri-soft:#eef4ff;
  --border:#d9e2f1;
}
.inquiry-page-wrap{
  background:#fff;
  border:1px solid var(--border);
  border-radius:12px;
  box-shadow:0 2px 10px rgba(13, 48, 112, 0.08);
  padding:14px 12px 22px;
}
.inquiry-title{
  color:var(--pri);
  font-weight:700;
  margin-bottom:14px;
}
.form-group label{
  font-weight:600;
  color:#263a5a;
}
.form-control{
  border-radius:8px;
  border:1px solid #ced9ea;
  height:38px;
}
.form-control:focus{
  border-color:var(--pri);
  box-shadow:0 0 0 2px rgba(11,71,190,.12);
}
.section-toggle{
  font-size:18px;
  color:var(--pri);
  cursor:pointer;
  float:right;
  margin-right:26px;
}
.section-toggle b{
  font-weight:700;
}
.documents-box{
  margin-top:8px;
  background:var(--pri-soft);
  border:1px solid var(--border);
  border-radius:10px;
  padding:10px 12px;
}
.documents-heading{
  color:var(--pri);
  font-weight:700;
  margin-bottom:8px;
  font-size:14px;
}
.documents-inline{
  display:flex;
  flex-wrap:nowrap;
  align-items:center;
  gap:14px;
  overflow-x:auto;
  white-space:nowrap;
  padding-bottom:2px;
}
.doc-item{
  display:inline-flex;
  align-items:center;
  gap:6px;
  font-size:12px;
  font-weight:600;
  color:#25406f;
  margin:0;
}
.doc-item input[type="checkbox"]{
  width:14px;
  height:14px;
}
.field-help{
  margin-top:5px;
  font-size:11px;
  line-height:1.4;
  color:#5a6f93;
}
.field-help a{
  color:var(--pri);
  font-weight:700;
  text-decoration:none;
}
.field-help a:hover{
  text-decoration:underline;
}
.inline-link{
  margin-left:6px;
  font-size:11px;
  font-weight:700;
}
.inline-link a{
  color:var(--pri);
  text-decoration:none;
}
.inline-link a:hover{
  text-decoration:underline;
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <br>
        <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i>  &nbsp; Front Office &nbsp; <i style="" class="fa fa-angle-double-right"></i> &nbsp; Students Inquries  &nbsp; <i style="" class="fa fa-angle-double-right"></i> &nbsp; Add Inquries
        <br>
        <?php if ($message): ?><div class="alert alert-success" style="padding:8px 14px;font-size:12px;border-radius:8px;margin-bottom:10px;"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger" style="padding:8px 14px;font-size:12px;border-radius:8px;margin-bottom:10px;"><?php echo e($error); ?></div><?php endif; ?>

        <div class="row" style="background-color: white;">

            <section class="add_sub_agent" id="table_sub_agent">
                <div class="container">

                    <div class="" style="margin-top:10px;">
                        <div class="clearfix"></div>
                    </div>
                    <br>

                    <div class="inquiry-page-wrap">
                        <h3 class="inquiry-title">Student Inquiry Information</h3>

                        <form id="inquiryForm" action="" method="post" enctype="multipart/form-data" class="form-horizontal form-label-left">
                            <input type="hidden" name="action" value="addNewInquiry" />
                            <input type="hidden" name="current_session" value="<?php echo e(get_setting('session_year', '2026-2027')); ?>" />

                            <div class="col-md-12">

                                <div class="col-md-2 col-xs-12" style="padding: 8px;">
                                    <div class="form-group ">
                                        <label class="required"> Student Name</label>
                                        <input name="student_name" required value="" type="Text" class="form-control">
                                    </div>
                                </div>

                                <div class="col-md-2 col-xs-12" style="padding: 8px;">
                                    <div class="form-group ">
                                        <label class="required">Father Name</label>
                                        <input name="father_name" required value="" type="Text" class="form-control">
                                    </div>
                                </div>

                                <div class="col-md-2 col-xs-12" style="padding: 8px;">
                                    <div class="form-group ">
                                        <label class="required">Student Cell No</label>
                                        <input name="std_cell" value="" type="text" class="form-control" inputmode="numeric" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                                    </div>
                                </div>

                                <div class="col-md-2 col-xs-12" style="padding: 8px;">
                                    <div class="form-group ">
                                        <label class="required">Father Cell No</label>
                                        <input name="cell" value="" type="text" class="form-control" inputmode="numeric" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                                    </div>
                                </div>

                                <div class="col-md-2 col-xs-12" style="padding: 8px;">
                                    <div class="form-group ">
                                        <label class="required">Applying For Class</label>
                                        <select name="class_id" class="form-control" required onChange="getsec(this.value)">
                                            <option value="All">All</option>
                                            <?php foreach ($classes as $c): ?>
                                                <option value="<?php echo (int) $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-2 col-xs-12" style="padding: 8px;">
                                    <div class="form-group ">
                                        <label>Applying For Section</label>
                                        <select name="section" id="txt_section" class="form-control">
                                            <option value="All">All</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-2 col-xs-12" style="padding: 8px;">
                                    <div class="form-group ">
                                        <label class="required">Visiting Date</label>
                                        <input name="inquiry_date" value="<?php echo date('Y-m-d'); ?>" type="date" class="form-control">
                                    </div>
                                </div>

                                <div class="col-md-2 col-xs-12" style="padding: 8px;">
                                    <div class="form-group ">
                                        <label class="required">Admission Confirmation</label>
                                        <select name="student_confirmed" class="form-control">
                                            <option value="YES">YES</option>
                                            <option value="NO">NO</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-2 col-xs-12" style="padding: 8px;">
                                    <div class="form-group ">
                                        <label class="required">Welcome Message</label>
                                        <select name="send_msg" class="form-control">
                                            <option value="YES">YES</option>
                                            <option selected value="NO">NO</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-2 col-xs-12" style="padding: 8px;">
                                    <div class="form-group ">
                                        <label class="required">Add Locality</label>
                                        <select name="Locality" required class="form-control">
                                            <option value="">Choose Locality</option>
                                            <?php foreach ($localities as $l): ?>
                                                <option value="<?php echo e($l['locality_name']); ?>"><?php echo e($l['locality_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="field-help">If locality is missing, add it from <a href="<?php echo BASE_URL; ?>manage_localities.php" target="_blank">Manage Localities</a>.</div>
                                    </div>
                                </div>

                                <div class="form-group col-md-2" style="padding:8px;">
                                    <label class="required"> Inquiry Number</label>
                                    <input name="inquiry_number" readonly value="<?php echo (int) $nextInquiryNo; ?>" type="text" class="form-control">
                                </div>

                                <div class="col-md-12 pull-right">
                                    <span id="toggleAdvancedInfo" class="section-toggle"><b>+ Add Advance Info</b></span>
                                </div>

                                <div id="advancedInfo" style="display: none;">

                                    <div class="col-md-3 col-xs-12" style="padding: 8px;">
                                        <div class="form-group ">
                                            <label class="required"> Gender</label>
                                            <select name="gender" class="form-control">
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="shemale">Shemale</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3 col-xs-12" style="padding: 8px;">
                                        <div class="form-group ">
                                            <label class="required">Previous School Name</label>
                                            <select name="school_id" class="form-control">
                                                <option>Select</option>
                                            </select>
                                            <div class="field-help">No school in list? Create one from <a href="<?php echo BASE_URL; ?>dashboard.php" target="_blank">Add School</a>.</div>
                                        </div>
                                    </div>

                                    <div class="col-md-3 col-xs-12" style="padding: 8px;">
                                        <div class="form-group ">
                                            <label class="required">Prospectus</label>
                                            <select name="prospectus" class="form-control">
                                                <option value="YES">YES</option>
                                                <option value="NO">NO</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3 col-xs-12" style="padding: 8px;">
                                        <div class="form-group ">
                                            <label class="required"> Date Of Birth</label>
                                            <input name="date_of_birth" value="" type="date" class="form-control">
                                        </div>
                                    </div>

                                    <div class="col-md-3 col-xs-12" style="padding: 8px;">
                                        <div class="form-group ">
                                            <label class="required"> Refered By</label>
                                            <select name="refrence" class="form-control" onChange="HideDate(this.value);">
                                                <?php foreach ($references as $rv => $rl): ?>
                                                    <option value="<?php echo $rv; ?>"><?php echo e($rl); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3 col-xs-12" style="padding: 8px;">
                                        <div class="form-group ">
                                            <label class="required">Father Occupation</label>
                                            <select name="father_occupation" id="father_occupation" class="form-control" onchange="getFeeDetailsByClassAndSection()">
                                                <option value="">Choose Occupation</option>
                                                <?php foreach ($occupations as $o): ?>
                                                    <option value="<?php echo (int) $o['id']; ?>"><?php echo e($o['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="field-help">If occupation is not available, add it from <a href="<?php echo BASE_URL; ?>manage_occupations.php" target="_blank">Manage Occupations</a>.</div>
                                        </div>
                                    </div>

                                </div>

                                <div class="col-md-12 pull-right">
                                    <span id="toggleAcademicHistory" class="section-toggle"><b>+ Add Academic History</b></span>
                                </div>

                                <div id="academicHistory" style="display: none;">
                                    <h3 class="col-md-12">Academic Record / Address</h3>
                                    <div class="col-md-3 col-xs-12" style="padding: 8px;">
                                        <div class="form-group">
                                            <label class="required">Test Date / Time</label>
                                            <input name="test_date" value="" type="text" class="form-control" placeholder="14-Oct-2025 / 9:00 AM">
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-xs-12" style="padding: 8px;">
                                        <div class="form-group">
                                            <label class="required">Previous Class</label>
                                            <select name="previous_class" class="form-control">
                                                <option value="All">All</option>
                                                <?php foreach ($classes as $c): ?>
                                                    <option value="<?php echo (int) $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-xs-12" style="padding: 8px;">
                                        <div class="form-group">
                                            <label class="required">Total Marks (Previous Class)</label>
                                            <input name="previous_Tmarks" value="" type="number" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-xs-12" style="padding: 8px;">
                                        <div class="form-group">
                                            <label class="required">Total Obtain (Previous Class)</label>
                                            <input name="previous_Omarks" value="" type="number" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xs-12" style="padding: 8px;">
                                        <div class="form-group">
                                            <label class="required">Address</label>
                                            <input name="address" value="" type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xs-12" style="padding: 8px;">
                                        <div class="form-group">
                                            <label class="required">Remarks</label>
                                            <input name="remarks" value="" type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-12" style="padding: 8px;">
                                        <div class="documents-box">
                                            <div class="documents-heading"><i class="fa fa-paperclip"></i> Documents Attachment</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12 pull-right">
                                    <span id="toggleFeeRecords" class="section-toggle"><b>+ Add Fee Records</b></span>
                                </div>

                                <div id="FeeRecords" style="display: none;">
                                    <h3 class="col-md-12">Fee Record</h3>
                                    <div class="col-md-3 col-xs-12" style="padding: 8px;">
                                        <div class="form-group">
                                            <label class="required">Year / Month</label>
                                            <input type="month" id="bdaymonth" name="year_month" value="<?php echo date('Y-m'); ?>" class="form-control" />
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-xs-12" style="padding: 8px;">
                                        <div class="form-group">
                                            <label>Discount Package</label>
                                            <select name="discount_package" id="discount_package" class="form-control">
                                                <option value=""> Select Discount </option>
                                            </select>
                                            <div class="field-help">To add or update package/heads, open <a href="<?php echo BASE_URL; ?>update_fee_settings.php#feeHeads" target="_blank">Fee Settings</a>.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-xs-12" style="padding: 8px;">
                                        <div class="form-group">
                                            <label class="required">Monthly Fee</label>
                                            <input name="monthly_fee" id="monthly_fee" value="" type="number" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-xs-12" style="padding: 3px;">
                                        <div class="form-group">
                                            <label class="required"><label>  Misc Fee </label></label>
                                            <input name="misc_fee" id="misc_fee" value="" type="number" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" id="page_api_status" value="0"/>

                            <div>
                                <button id="send" type="submit" style="margin-top:30px;margin-left: 20px;" class="btn btn-primary pull-right">Save Inquiry</button>
                            </div>

                        </form>
                    </div>
                    <br><br>
                </div>
                <div class="clearfix"></div>
            </section>
        </div>
    </div>
</div>

<script>
var sectionsData = <?php echo json_encode($sections); ?>;
var feeByClass = <?php echo json_encode($feeByClass); ?>;
function HideDate(str){
    var el = document.getElementById('otherRemarks');
    if (!el) return;
    if (str == 7) { el.style.display = 'block'; }
    else { el.style.display = 'none'; }
}
function getsec(cid){
    var s = document.getElementById('txt_section');
    s.innerHTML = '<option value="All">All</option>';
    if (!cid || cid === 'All') return;
    sectionsData.forEach(function(sv){
        if (String(sv.class_id) === String(cid)) {
            var o = document.createElement('option');
            o.value = sv.section_id;
            o.textContent = sv.section_name;
            s.appendChild(o);
        }
    });
}
document.getElementById('toggleAdvancedInfo').addEventListener('click', function() {
    var advancedInfoDiv = document.getElementById('advancedInfo');
    if (advancedInfoDiv.style.display === 'none') {
        advancedInfoDiv.style.display = 'block';
        this.textContent = '- Hide Advance Info';
    } else {
        advancedInfoDiv.style.display = 'none';
        this.textContent = '+ Add Advance Info';
    }
});
document.getElementById('toggleAcademicHistory').addEventListener('click', function() {
    var academicHistoryDiv = document.getElementById('academicHistory');
    if (academicHistoryDiv.style.display === 'none') {
        academicHistoryDiv.style.display = 'block';
        this.textContent = '- Hide Academic History';
    } else {
        academicHistoryDiv.style.display = 'none';
        this.textContent = '+ Add Academic History';
    }
});
document.getElementById('toggleFeeRecords').addEventListener('click', function() {
    var feeRecordsDiv = document.getElementById('FeeRecords');
    if (feeRecordsDiv.style.display === 'none') {
        feeRecordsDiv.style.display = 'block';
        this.textContent = '- Hide Fee Records';
    } else {
        feeRecordsDiv.style.display = 'none';
        this.textContent = '+ Add Fee Records';
    }
});
function getFeeDetailsByClassAndSection() {
    var classId = document.querySelector('[name="class_id"]').value;
    var monthly = document.getElementById('monthly_fee');
    var misc = document.getElementById('misc_fee');
    var fd = feeByClass[String(classId)] || [0, 0];
    if (monthly) monthly.value = fd[0];
    if (misc) misc.value = fd[1];
}
document.querySelector('[name="class_id"]').addEventListener('change', function(){ getFeeDetailsByClassAndSection(); });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>