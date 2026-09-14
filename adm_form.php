<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Admission Form';

$school_name = get_setting('school_name') ?: 'LAPS School & College';

$classes = [];
$res = db_query("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$student = null;
$sel_id = (int) ($_GET['student_id'] ?? 0);
if ($sel_id > 0) {
    $st = db_prepare("SELECT s.*, c.class_name, sec.section_name FROM students s
                      LEFT JOIN classes c ON s.class_id=c.class_id
                      LEFT JOIN sections sec ON s.section_id=sec.section_id
                      WHERE s.student_id=?");
    $st->bind_param('i', $sel_id);
    $st->execute();
    $student = $st->get_result()->fetch_assoc();
}

include __DIR__ . '/includes/header.php';
?>
<style>
.adm-sheet { background:#fff; max-width:880px; margin:0 auto; padding:34px 40px; border:1px solid #E5E7EB; border-radius:16px; }
.adm-sheet .hd { text-align:center; border-bottom:3px double #FF7A1B; padding-bottom:14px; margin-bottom:22px; }
.adm-sheet .hd h2 { margin:0; font-size:24px; font-weight:800; color:#111827; }
.adm-sheet .hd .sub { color:#6B7280; font-size:13px; margin-top:4px; }
.adm-sheet table.w { width:100%; border-collapse:collapse; }
.adm-sheet table.w td { border:1px solid #cfd8e3; padding:9px 12px; font-size:13px; vertical-align:top; }
.adm-sheet .lbl { background:#F4F6F9; font-weight:700; color:#2A3F54; width:180px; }
.adm-sheet .sec-head { background:#FF7A1B; color:#fff; font-weight:800; font-size:14px; padding:6px 12px; border-radius:4px; margin:22px 0 10px; }
.search-bar-af { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; max-width:880px; margin-left:auto; margin-right:auto; }
.adm-sheet .sign { margin-top:34px; display:flex; justify-content:space-between; gap:40px; }
.adm-sheet .sign div { text-align:center; }
.adm-sheet .sign .line { border-top:1px solid #333; padding-top:6px; font-size:12px; color:#6B7280; width:200px; }
.no-print { }
@media print { .no-print{display:none!important;} body{background:#fff;} .main-content{padding:0;} .adm-sheet{border:none;padding:0;max-width:100%;} }
@media (max-width:760px){ .adm-sheet .lbl{width:110px;} }
</style>
<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px; max-width:880px; margin:0 auto;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-file-text"></i> Admission Form</h3>
        </div>

        <form method="get" action="adm_form.php" class="search-bar-af no-print">
            <div class="form-group col-md-5" style="margin-bottom:0;">
                <label>Course/Class</label>
                <select name="term" class="form-control">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?><option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-5" style="margin-bottom:0;">
                <label>Student</label>
                <select name="student_id" class="form-control form-control-select2" required>
                    <option value="">Select Student</option>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="color:#fff;"><i class="fa fa-eye"></i> View</button>
            </div>
            <?php if ($student): ?>
            <div class="col-md-12" style="margin-top:10px; padding:0; display:flex; gap:10px;">
                <button type="button" onclick="window.print()" class="btn btn-success" style="color:#fff;"><i class="fa fa-print"></i> Print / Download PDF</button>
                <a href="add_student.php" class="btn btn-default">Back to Add Student</a>
            </div>
            <?php endif; ?>
        </form>

        <?php if (!$student): ?>
            <div class="adm-sheet" style="text-align:center; padding:60px 20px; color:#6B7280;">
                <i class="fa fa-file-text-o" style="font-size:48px; color:#FF7A1B;"></i>
                <h4 style="margin-top:14px; color:#111827;">Select a student to generate the Admission Form</h4>
                <p style="font-size:13px;">Choose a student from the dropdown above, then press View.</p>
            </div>
        <?php else: ?>
            <div class="adm-sheet" id="printArea">
                <div class="hd">
                    <h2><?php echo e($school_name); ?></h2>
                    <div class="sub">Admission / Enrollment Form</div>
                </div>

                <div class="sec-head"><i class="fa fa-user"></i> Student Information</div>
                <table class="w">
                    <tr><td class="lbl">Student Name</td><td><?php echo e(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))); ?></td>
                        <td class="lbl">GR No</td><td><?php echo e($student['gr_no'] ?? '-'); ?></td></tr>
                    <tr><td class="lbl">Date Of Birth</td><td><?php echo $student['dob'] ? date('d M Y', strtotime($student['dob'])) : ''; ?></td>
                        <td class="lbl">Gender</td><td><?php echo e(ucfirst($student['gender'] ?? '')); ?></td></tr>
                    <tr><td class="lbl">Religion</td><td><?php echo e($student['religion'] ?? ''); ?></td>
                        <td class="lbl">Course/Class</td><td><?php echo e($student['class_name'] ?? ''); ?></td></tr>
                    <tr><td class="lbl">Section</td><td><?php echo e($student['section_name'] ?? ''); ?></td>
                        <td class="lbl">Session</td><td><?php echo e($student['session'] ?? ''); ?></td></tr>
                    <tr><td class="lbl">Admission Date</td><td><?php echo $student['admission_date'] ? date('d M Y', strtotime($student['admission_date'])) : ''; ?></td>
                        <td class="lbl">Phone</td><td><?php echo e($student['phone'] ?? ''); ?></td></tr>
                    <tr><td class="lbl">Home Address</td><td colspan="3"><?php echo e($student['address'] ?? ''); ?></td></tr>
                </table>

                <div class="sec-head"><i class="fa fa-female"></i> Parents / Guardian Information</div>
                <table class="w">
                    <tr><td class="lbl">Father Name</td><td><?php echo e($student['father_name'] ?? ''); ?></td>
                        <td class="lbl">Father CNIC</td><td><?php echo e($student['father_cnic'] ?? ''); ?></td></tr>
                    <tr><td class="lbl">Father Occupation</td><td><?php echo e($student['father_occupation'] ?? ''); ?></td>
                        <td class="lbl">Father Cell No</td><td><?php echo e($student['father_cellno'] ?? ''); ?></td></tr>
                    <tr><td class="lbl">Mother Name</td><td><?php echo e($student['mother_name'] ?? ''); ?></td>
                        <td class="lbl">Mother CNIC</td><td><?php echo e($student['mother_cnic'] ?? ''); ?></td></tr>
                    <tr><td class="lbl">Guardian Name</td><td><?php echo e($student['guardian_name'] ?? ''); ?></td>
                        <td class="lbl">Guardian CNIC</td><td><?php echo e($student['guardian_cnic'] ?? ''); ?></td></tr>
                    <tr><td class="lbl">Guardian Cell No</td><td><?php echo e($student['guardian_cellno'] ?? ''); ?></td>
                        <td class="lbl">Guardian Occupation</td><td><?php echo e($student['guardian_occupation'] ?? ''); ?></td></tr>
                    <tr><td class="lbl">Family Code</td><td><?php echo e($student['family_code'] ?? ''); ?></td>
                        <td class="lbl">Phone</td><td><?php echo e($student['phone'] ?? ''); ?></td></tr>
                    <tr><td class="lbl" style="width:180px;">Home Address</td><td colspan="3"><?php echo e($student['address'] ?? ''); ?></td></tr>
                </table>

                <div class="sign no-print">
                    <div><div class="line">Guardian / Parent Signature</div></div>
                    <div><div class="line">Course/Class Teacher</div></div>
                    <div><div class="line">Principal / Authorised Signatory</div></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    var showSelect2 = typeof jQuery !== 'undefined' && jQuery.fn.select2;
    if (showSelect2) { jQuery('.form-control-select2').select2(); }

    function attach(e) {
        var sel = e.target;
        if (sel.tagName !== 'SELECT' || !sel.getAttribute('data-bound')) return;
    }
    var termSel = document.querySelector('[name="term"]');
    var stuSel = document.querySelector('[name="student_id"]');
    if (termSel && stuSel) {
        function loadStudents(cid) {
            var term = cid ? '&class_id=' + cid : '';
            eduGet(HIIFI_BASE + 'ajax_get_students.php' + term, function (data) {
                stuSel.innerHTML = '<option value="">Select Student</option>';
                (data || []).forEach(function (s) {
                    var o = document.createElement('option');
                    o.value = s.student_id;
                    o.textContent = s.first_name + ' (' + (s.gr_no || s.student_id) + ')';
                    stuSel.appendChild(o);
                });
                if (showSelect2) jQuery(stuSel).trigger('change');
            });
        }
        termSel.addEventListener('change', function () { loadStudents(this.value); });
        loadStudents(termSel.value);
    }
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
