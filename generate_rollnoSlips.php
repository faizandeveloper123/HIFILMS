<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Generate Roll No Slips';

// Sessions 2018-2019 .. 2030-2031
$currentSession = get_setting('session_year', '2026-2027');
$sessionOptions = [];
for ($s = 2018; $s <= 2030; $s++) { $sessionOptions[$s . '-' . ($s + 1)] = $s . '-' . ($s + 1); }

// Terms: free list + any named exams for more flexibility
$freeTerms = ['FIRST TERM', 'SECOND TERM', 'THIRD TERM', 'ANNUAL EXAMINATION', 'MID TERM', 'PRE-BOARD'];
$examTerms = [];
$res = db_query("SELECT DISTINCT exam_name FROM exams WHERE exam_name IS NOT NULL AND exam_name <> '' ORDER BY exam_name");
while ($row = $res->fetch_assoc()) { $examTerms[] = $row['exam_name']; }
$allTerms = $freeTerms;
foreach ($examTerms as $t) { if (!in_array($t, $allTerms, true)) $allTerms[] = $t; }

$classes = [];
$res = db_query("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sel_session = $_GET['session'] ?? $currentSession;
if (!isset($sessionOptions[$sel_session])) { $sel_session = $currentSession; }
$sel_term  = $_GET['term_id'] ?? '';
$sel_class = (int) ($_GET['class_id'] ?? 0);
$sel_section = $_GET['section'] ?? 'All';
$sel_syllabus = $_GET['syllabus'] ?? 'Yes';
$sel_order = $_GET['orderBy'] ?? 'default';

$students = [];
$slip_no = 1;
if ($sel_class > 0) {
    $sql = "SELECT s.*, sec.section_name FROM students s
            LEFT JOIN sections sec ON s.section_id = sec.section_id
            WHERE s.status=1 AND s.class_id=" . (int) $sel_class;
    if ($sel_section !== 'All' && (int) $sel_section > 0) {
        $sql .= " AND s.section_id=" . (int) $sel_section;
    }
    switch ($sel_order) {
        case 'GRnoWise': $sql .= " ORDER BY s.gr_no, s.roll_no"; break;
        case 'asc':      $sql .= " ORDER BY s.first_name, s.last_name"; break;
        default:         $sql .= " ORDER BY s.roll_no, s.first_name"; break;
    }
    $res = db_query($sql);
    while ($row = $res->fetch_assoc()) {
        $row['_slip_no'] = $slip_no++;
        $students[] = $row;
    }
}

$sections = [];
if ($sel_class > 0) {
    $res = db_query("SELECT section_id, section_name FROM sections WHERE class_id=" . (int) $sel_class);
    while ($row = $res->fetch_assoc()) { $sections[] = $row; }
}

// Selected subset for printing
$printIds = [];
if (isset($_GET['printSlip']) && $_GET['printSlip'] === '1' && isset($_GET['slip_ids']) && is_array($_GET['slip_ids'])) {
    foreach ($_GET['slip_ids'] as $id) { $printIds[] = (int) $id; }
}
$printStudents = [];
foreach ($students as $st) {
    if ($printIds && !in_array((int) $st['student_id'], $printIds, true)) continue;
    $printStudents[] = $st;
}

// Syllabus subjects for the class
$subjects = [];
if ($sel_class > 0 && $sel_syllabus === 'Yes') {
    $res = db_query("SELECT subject_name FROM subjects WHERE class_id=" . (int) $sel_class . " ORDER BY subject_name");
    while ($row = $res->fetch_assoc()) { $subjects[] = $row['subject_name']; }
}

$examDate = '';
if ($sel_term !== '') {
    $r = db_query("SELECT exam_date FROM exams WHERE exam_name = '" . db_connect()->real_escape_string($sel_term) . "' AND (class_id = " . (int) $sel_class . " OR class_id IS NULL) ORDER BY exam_id DESC LIMIT 1");
    if ($r) { $row = $r->fetch_assoc(); $examDate = $row['exam_date'] ?? ''; }
}
if (!$examDate) { $examDate = date('Y-m-d'); }

include __DIR__ . '/includes/header.php';
?>
<style>
.search-box { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px; margin-bottom:14px; }
.slip-sheet { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
.slip-card { background:#fff; border:1px solid #E5E7EB; border-radius:12px; padding:14px; break-inside:avoid; page-break-inside:avoid; margin-bottom:14px; position:relative; }
.slip-card .top { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #FF7A1B; padding-bottom:8px; margin-bottom:8px; }
.slip-card .stripe { position:absolute; right:0; top:0; bottom:0; width:10px; background:linear-gradient(180deg,#FF7A1B,#ffa35c); border-radius:12px 0 0 12px; }
@media print {
    .no-print { display:none!important; }
    body { background:#fff; }
}
<?php if (isset($_GET['landscape'])): ?>
@page { size: A4 landscape; margin: 10mm; }
<?php endif; ?>
</style>

<div class="main-content">
    <div class="container-fluid">

        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding:6px 0 10px 0;"> 
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;">Generate Roll No Slips</h3>
        </div>

        <form class="search-box no-print" action="<?php echo BASE_URL; ?>generate_rollnoSlips.php" method="get">
            <div class="col-md-1 col-xs-12" style="padding:8px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="required">Session</label>
                    <select name="session" id="session" class="form-control" onchange="getterm(this.value)">
                        <option value="">Select Session</option>
                        <?php foreach ($sessionOptions as $val => $label): ?>
                            <option value="<?php echo e($val); ?>" <?php echo $sel_session === $val ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2 col-xs-12" style="padding:8px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="required">Term</label>
                    <select name="term_id" id="term_id" class="form-control" required="">
                        <option value="">Select Term</option>
                        <?php foreach ($allTerms as $t): ?>
                            <option value="<?php echo e($t); ?>" <?php echo $sel_term === $t ? 'selected' : ''; ?>><?php echo e($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2 col-xs-12" style="padding:8px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="required">Course/Class</label>
                    <select name="class_id" id="class_id" class="form-control" onchange="getsec(this.value)" required="">
                        <option value="">Select Course/Class</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php echo $sel_class == $c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-1 col-xs-12" style="padding:8px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="required">Section</label>
                    <select name="section" id="txt_section" class="form-control">
                        <option value="All">All</option>
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?php echo $sec['section_id']; ?>" <?php echo $sel_section == $sec['section_id'] ? 'selected' : ''; ?>><?php echo e($sec['section_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2 col-xs-12" style="padding:8px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="required">Display Syllabus</label>
                    <select name="syllabus" id="syllabus" class="form-control">
                        <option value="Yes" <?php echo $sel_syllabus === 'Yes' ? 'selected' : ''; ?>>Display</option>
                        <option value="No"  <?php echo $sel_syllabus === 'No' ? 'selected' : ''; ?>>Hide</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2 col-xs-12" style="padding:8px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="required">List Order</label>
                    <select name="orderBy" id="orderBy" class="form-control">
                        <option value="GRnoWise" <?php echo $sel_order === 'GRnoWise' ? 'selected' : ''; ?>>By GR Number (Ascending)</option>
                        <option value="asc" <?php echo $sel_order === 'asc' ? 'selected' : ''; ?>>By Student Name (A-Z)</option>
                        <option value="default" <?php echo $sel_order === 'default' ? 'selected' : ''; ?>>System Default Order</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2 col-xs-12" style="padding:2px;">
                <div class="form-group" style="margin-bottom:0;">
                    <button type="submit" class="btn btn-primary" style="margin-top:24px;"><i class="fa fa-search"></i> Search</button>
                </div>
            </div>
        </form>

        <?php if ($sel_class > 0): ?>
        <div class="no-print" style="display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
            <form id="slipPrintForm" method="get" action="<?php echo BASE_URL; ?>generate_rollnoSlips.php" class="no-print" onsubmit="return buildSlipPrint(this);" style="display:flex; gap:8px; margin:0;">
                <input type="hidden" name="session" value="<?php echo e($sel_session); ?>">
                <input type="hidden" name="term_id" value="<?php echo e($sel_term); ?>">
                <input type="hidden" name="class_id" value="<?php echo $sel_class; ?>">
                <input type="hidden" name="section" value="<?php echo e($sel_section); ?>">
                <input type="hidden" name="syllabus" value="<?php echo e($sel_syllabus); ?>">
                <input type="hidden" name="orderBy" value="<?php echo e($sel_order); ?>">
                <input type="hidden" name="printSlip" value="1">
                <div id="slipIdsHidden"></div>
                <button onclick="return selectAllThenPrint(event);" class="btn btn-warning" style="float:right;"><i class="fa fa-print"></i> Generate Roll No Slips</button>
                <button onclick="return buildSlipPrintLandscape(event);" class="btn btn-info" style="float:right;"><i class="fa fa-file"></i> Print RollNo Slips Landscape</button>
            </form>
        </div>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:12px;" class="no-print">
            <table id="listofstudents" class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:8px;">
                <thead>
                    <tr>
                        <th width="3%">S.No</th>
                        <th width="5%">GR. No</th>
                        <th width="17%">Student</th>
                        <th width="20%">Course/Class</th>
                        <th width="3%"><label><input type="checkbox" id="checkAll"></label></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($students) === 0): ?>
                        <tr><td colspan="5" style="text-align:center; color:#6B7280; padding:30px;">No data available in table.</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; foreach ($students as $st): ?>
                        <tr data-sid="<?php echo $st['student_id']; ?>">
                            <td><?php echo $i++; ?></td>
                            <td><?php echo e($st['gr_no'] ?? ($st['roll_no'] ?? $st['student_id'])); ?></td>
                            <td><strong><?php echo e($st['first_name']); ?> <?php echo e($st['last_name']); ?></strong><div style="font-size:11px; color:#6B7280;"><?php echo e($st['father_name'] ?? ''); ?></div></td>
                            <td><?php foreach ($classes as $c) { if ($c['class_id'] == $st['class_id']) echo e($c['class_name']); } ?><?php echo $st['section_name'] ? ' / ' . e($st['section_name']) : ''; ?></td>
                            <td style="text-align:center;"><input type="checkbox" class="slip-check" value="<?php echo $st['student_id']; ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="font-size:12px; color:#6B7280;">Showing <?php echo count($students); ?> students. Select rows then click <strong>Generate Roll No Slips</strong>, or check <strong>All</strong> to print every slip.</div>

            <!-- Live slip preview -->
            <div style="margin-top:14px; border-top:1px solid #E5E7EB; padding-top:12px;">
                <h4 style="font-size:14px; font-weight:700; color:#334155; margin:0 0 10px 0;"><i class="fa fa-eye"></i> Slip Preview</h4>
                <div id="slipPreview" style="color:#6B7280; font-size:13px;">Select a student row to preview its roll no slip.</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['printSlip']) && $_GET['printSlip'] === '1'): ?>
        <div class="slip-sheet" id="printArea" style="display:grid; grid-template-columns:repeat(2,1fr); gap:10px;">
            <?php if (count($printStudents) === 0): ?>
                <div style="grid-column:1/-1; text-align:center; color:#6B7280; padding:40px;">No students selected for printing.</div>
            <?php endif; ?>
            <?php foreach ($printStudents as $st): ?>
                <div class="slip-card">
                    <div class="stripe"></div>
                    <div class="top">
                        <div>
                            <div style="font-weight:800; color:#111827;"><?php echo e($schoolName = get_setting('school_name', 'LAPS School & College')); ?></div>
                            <div style="font-size:12px; color:#6B7280;"><?php echo e($sel_term !== '' ? $sel_term : 'Roll No Slip'); ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:800; color:#FF7A1B; font-size:20px;"><?php echo $st['_slip_no']; ?></div>
                            <div style="font-size:11px; color:#6B7280;">Roll #</div>
                        </div>
                    </div>
                    <table style="width:100%; font-size:12.5px;">
                        <tr><td style="color:#6B7280; width:90px;">Session</td><td><strong><?php echo e($sel_session); ?></strong></td></tr>
                        <tr><td style="color:#6B7280;">Student</td><td><strong><?php echo e($st['first_name']); ?> <?php echo e($st['father_name'] ?? ''); ?></strong></td></tr>
                        <tr><td style="color:#6B7280;">Course/Class</td><td><?php foreach ($classes as $c) { if ($c['class_id'] == $st['class_id']) echo e($c['class_name']); } ?><?php echo $st['section_name'] ? ' / ' . e($st['section_name']) : ''; ?></td></tr>
                        <tr><td style="color:#6B7280;">GR No</td><td><?php echo e($st['gr_no'] ?? ($st['roll_no'] ?? $st['student_id'])); ?></td></tr>
                        <tr><td style="color:#6B7280;">Date</td><td><?php echo $examDate ? date('d M Y', strtotime($examDate)) : date('d M Y'); ?></td></tr>
                        <?php if ($sel_syllabus === 'Yes' && count($subjects)): ?>
                            <tr><td style="color:#6B7280;">Syllabus</td><td><?php echo e(implode(', ', $subjects)); ?></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
        <script>window.addEventListener('load', function(){ window.print(); });</script>
        <?php endif; ?>

    </div>
</div>

<script>
document.getElementById('checkAll').addEventListener('change', function(){
    document.querySelectorAll('.slip-check').forEach(function(cb){ cb.checked = this.checked; }.bind(this));
});

document.querySelectorAll('#listofstudents tbody tr[data-sid]').forEach(function(tr){
    tr.addEventListener('click', function(){
        var sid = this.getAttribute('data-sid');
        var cells = this.querySelectorAll('td');
        var gr   = cells[1].textContent.trim();
        var name = cells[2].querySelector('strong').textContent.trim();
        var cls  = cells[3].textContent.trim();
        var out  = document.getElementById('slipPreview');
        out.innerHTML = '<div class="slip-card">' +
            '<div class="stripe"></div>' +
            '<div class="top"><div><div style="font-weight:800;color:#111827;"><?php echo e(get_setting('school_name', 'LAPS School & College')); ?></div>' +
            '<div style="font-size:12px;color:#6B7280;"><?php echo e($sel_term !== '' ? $sel_term : 'Roll No Slip'); ?></div></div>' +
            '<div style="text-align:right;"><div style="font-weight:800;color:#FF7A1B;font-size:20px;">' + this.querySelectorAll('td')[0].textContent + '</div>' +
            '<div style="font-size:11px;color:#6B7280;">Roll #</div></div></div>' +
            '<table style="width:100%;font-size:12.5px;">' +
            '<tr><td style="color:#6B7280;width:90px;">Session</td><td><strong><?php echo e($sel_session); ?></strong></td></tr>' +
            '<tr><td style="color:#6B7280;">Student</td><td><strong>' + name + '</strong></td></tr>' +
            '<tr><td style="color:#6B7280;">Course/Class</td><td>' + cls + '</td></tr>' +
            '<tr><td style="color:#6B7280;">GR No</td><td>' + gr + '</td></tr>' +
            '<tr><td style="color:#6B7280;">Date</td><td><?php echo date('d M Y', strtotime($examDate)); ?></td></tr>' +
            '</table></div>';
    });
});

function selectedIds(){
    var ids = [];
    document.querySelectorAll('.slip-check:checked').forEach(function(cb){ ids.push(cb.value); });
    return ids;
}
function buildSlipPrint(form){
    var ids = selectedIds();
    var holder = document.getElementById('slipIdsHidden');
    holder.innerHTML = '';
    ids.forEach(function(id){
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'slip_ids[]'; inp.value = id;
        holder.appendChild(inp);
    });
    if (ids.length === 0) { alert('Please select at least one student to generate roll no slips.'); return false; }
    return true;
}
function selectAllThenPrint(event){
    document.querySelectorAll('.slip-check').forEach(function(cb){ cb.checked = true; });
    var form = document.getElementById('slipPrintForm');
    if (!buildSlipPrint(form)) return false;
    return true;
}
function buildSlipPrintLandscape(event){
    var form = document.getElementById('slipPrintForm');
    if (!buildSlipPrint(form)) { event.preventDefault(); return; }
    var link = document.createElement('a');
    link.href = '<?php echo BASE_URL; ?>generate_rollnoSlips.php?' + $(form).serialize() + '&landscape=1';
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    link.remove();
    event.preventDefault();
    return false;
}

function getsec(val){
    var sel = document.getElementById('txt_section');
    sel.innerHTML = '<option value="All">All</option>';
    if (!val) return;
    fetch('<?php echo BASE_URL; ?>ajax_get_sections.php?class_id=' + val)
        .then(function(r){ return r.json(); })
        .then(function(data){
            data.forEach(function(s){
                var o = document.createElement('option');
                o.value = s.section_id; o.textContent = s.section_name;
                sel.appendChild(o);
            });
        })
        .catch(function(){ /* fallback: keep All */ });
}
function getterm(val){
    if (!val) return;
    fetch('<?php echo BASE_URL; ?>ajax_get_terms.php?session=' + encodeURIComponent(val))
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data && data.terms && data.terms.length) {
                var sel = document.getElementById('term_id');
                sel.innerHTML = '<option value="">Select Term</option>';
                data.terms.forEach(function(t){
                    var o = document.createElement('option');
                    o.value = t; o.textContent = t;
                    sel.appendChild(o);
                });
            }
        })
        .catch(function(){ /* capability fallback: keep current term list */ });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>