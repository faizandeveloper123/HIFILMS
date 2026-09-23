<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Mark Attendance';

$message = '';
$error = '';

// QR / GR lookup endpoint (returns JSON)
if (isset($_GET['action']) && $_GET['action'] === 'findStudent') {
    header('Content-Type: application/json');
    $gr = trim($_GET['gr_no'] ?? '');
    if ($gr === '') { echo json_encode(['success' => false, 'message' => 'Please enter a GR No / Roll No.']); exit; }
    $stmt = db_prepare("SELECT s.student_id, s.first_name, s.last_name, s.father_name, s.gr_no, s.roll_no,
                              s.class_id, s.section_id, s.group_shift, c.class_name, sec.section_name
                        FROM students s
                        LEFT JOIN classes c ON s.class_id = c.class_id
                        LEFT JOIN sections sec ON s.section_id = sec.section_id
                        WHERE s.status = 1 AND (s.gr_no = ? OR s.roll_no = ? OR CAST(s.student_id AS CHAR) = ?)
                        LIMIT 1");
    $stmt->bind_param('sss', $gr, $gr, $gr);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) { echo json_encode(['success' => true, 'student' => $row]); }
    else { echo json_encode(['success' => false, 'message' => 'No student found for GR No: '.$gr]); }
    exit;
}

$classes = [];
$res = db_query("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

// Class heads (no dedicated table locally; keep the control for parity, All only)
$classHeads = [];

// Group / Shift options
$shifts = [];
$res = db_query("SELECT DISTINCT group_shift FROM students WHERE group_shift IS NOT NULL AND group_shift <> '' ORDER BY group_shift");
while ($row = $res->fetch_assoc()) { $shifts[] = $row['group_shift']; }

// Sessions (2018-2019 .. 2030-2031)
$sessionOptions = [];
$currentSession = get_setting('session_year', '2026-2027');
for ($s = 2018; $s <= 2030; $s++) {
    $label = $s . '-' . ($s + 1);
    $sessionOptions[$label] = $label;
}

$sel_session   = $_GET['session'] ?? $currentSession;
$sel_class_head= $_GET['class_head'] ?? '';
$sel_class     = (int) ($_GET['class_id'] ?? 0);
$sel_section   = (int) ($_GET['section'] ?? 0);
$sel_shift     = $_GET['group_shift'] ?? 'All';
$sel_date      = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sel_date)) { $sel_date = date('Y-m-d'); }

$students = [];
if ($sel_class > 0) {
    $sql = "SELECT s.*, sec.section_name FROM students s
            LEFT JOIN sections sec ON s.section_id = sec.section_id
            WHERE s.status=1 AND s.class_id=?";
    $params = [$sel_class]; $types = 'i';
    if ($sel_section > 0) {
        $sql .= " AND s.section_id=?";
        $params[] = $sel_section; $types .= 'i';
    }
    if ($sel_shift !== 'All' && $sel_shift !== '') {
        $sql .= " AND s.group_shift=?";
        $params[] = $sel_shift; $types .= 's';
    }
    $sql .= " ORDER BY s.roll_no, s.first_name";
    $stmt = db_prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $students[] = $row; }

    // Get existing attendance
    foreach ($students as &$st) {
        $st['att_status'] = '';
        $as = db_prepare("SELECT status FROM attendance WHERE student_id=? AND date=?");
        $as->bind_param('is', $st['student_id'], $sel_date);
        $as->execute();
        $ar = $as->get_result()->fetch_assoc();
        if ($ar) $st['att_status'] = $ar['status'];
    }
    unset($st);
}

// Save attendance (only writes rows whose value actually changed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'MarkAttendance') {
    $date  = $_POST['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = date('Y-m-d'); }
    $att   = $_POST['att'] ?? [];
    $count = 0;
    foreach ($att as $sid => $status) {
        $sid = (int) $sid;
        if ($sid <= 0 || !in_array($status, ['present','absent','late','leave'], true)) continue;
        $existing = db_prepare("SELECT status FROM attendance WHERE student_id=? AND date=?");
        $existing->bind_param('is', $sid, $date);
        $existing->execute();
        $cur = $existing->get_result()->fetch_assoc();
        if ($cur && $cur['status'] === $status) continue; // no change -> skip write
        if ($cur) {
            $up = db_prepare("UPDATE attendance SET status=?, marked_by=? WHERE student_id=? AND date=?");
            $uid = $_SESSION['user_id']; $up->bind_param('siis', $status, $uid, $sid, $date); $up->execute();
        } else {
            $ins = db_prepare("INSERT INTO attendance (student_id, date, status, marked_by) VALUES (?,?,?,?)");
            $uid = $_SESSION['user_id']; $ins->bind_param('issi', $sid, $date, $status, $uid); $ins->execute();
        }
        $count++;
    }
    $message = "$count attendance record(s) updated for $date!";
    header('Location: mark_attend.php?class_id=' . $sel_class . '&section=' . $sel_section . '&date=' . $date . '&saved=1');
    exit;
}

// Section options for selected class
$sections = [];
if ($sel_class > 0) {
    $res = db_query("SELECT section_id, section_name FROM sections WHERE class_id=$sel_class");
    while ($row = $res->fetch_assoc()) { $sections[] = $row; }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.qr-scan-btn { background:#22C55E; border:none; color:#fff; }
.qr-scan-btn:hover { background:#16A34A; color:#fff; }
.attendance-pills { display:flex; gap:6px; flex-wrap:wrap; }
.att-option { display:inline-flex; align-items:center; justify-content:center; min-width:36px; height:30px; padding:0 9px; border-radius:8px; border:1px solid #E5E7EB; cursor:pointer; font-size:12px; font-weight:700; color:#6B7280; background:#fff; transition:all .15s; }
.att-option input { display:none; }
.att-option span { display:inline-flex; align-items:center; justify-content:center; width:100%; height:100%; border-radius:6px; }
.att-option.present input:checked ~ span { background:#22C55E; color:#fff; }
.att-option.absent  input:checked ~ span { background:#DC2626; color:#fff; }
.att-option.late    input:checked ~ span { background:#2563EB; color:#fff; }
.att-option.leave   input:checked ~ span { background:#D97706; color:#fff; }
.att-option .att-label { color:inherit; }
.att-option.present { color:#16A34A; } .att-option.absent { color:#DC2626; } .att-option.late { color:#2563EB; } .att-option.leave { color:#D97706; }
@media (max-width: 767px) {
  .search-bar-student .form-group { padding:4px !important; }
  .search-bar-student .form-group input, .search-bar-student .form-group select { width:100% !important; }
}
@media (max-width: 767px) {
  #listofstudents { font-size:12px; }
  #listofstudents th, #listofstudents td { padding: 5px 4px !important; white-space: nowrap; }
  #listofstudents th:nth-child(3), #listofstudents td:nth-child(3) { max-width: 120px; overflow: hidden; text-overflow: ellipsis; }
  #listofstudents .att-option { min-width:30px; height:26px; font-size:11px; }
  #listofstudents .att-option span { padding:0 6px; }
  #listofstudents .attendance-pills { gap:3px; }
  .filter-row .col-md-5 .form-group { margin-bottom:0 !important; }
}
@media (max-width: 480px) {
  .qr-scan-btn { width:100%; white-space:normal; }
  #saveAttendanceBtn { width:100%; }
  .search-bar-student { padding:12px; }
}
@keyframes fadeInDown { from { opacity:0; transform: translateY(-14px); } to { opacity:1; transform: translateY(0); } }
@keyframes fadeInUp   { from { opacity:0; transform: translateY(10px); } to { opacity:1; transform: translateY(0); } }
</style>

<div class="main-content">
    <div class="container-fluid">

        <?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Attendance saved successfully!</div><?php endif; ?>
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding-bottom:10px; border-bottom:1px solid #e2e8f0;">
            <div style="font-size:13px;">
                <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
                <a href="#">Attendance</a> &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
                <strong>Mark Attendance</strong>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" class="btn qr-scan-btn" style="margin:0;" data-toggle="modal" data-target="#qrScanModal">
                    <i class="fa fa-qrcode"></i> Open QR Attendance Scanner
                </button>
            </div>
        </div>

        <form class="" action="mark_attend.php" method="get">
            <div class="panel panel-default">
                <div class="panel-heading"> Students Records <div class="clearfix"></div>
                <div class="panel-body">
                    <div class="col-md-12 filter-row" id="advanceSearch" style="">
                        <div class="col-md-3 col-xs-12" style="padding: 8px;">
                            <div class="form-group">
                                <label class="required">Session</label>
                                <select name="session" class="form-control inputheight" style="font-size:12px; padding-left:4px;">
                                    <?php foreach ($sessionOptions as $val => $label): ?>
                                        <option value="<?php echo e($val); ?>" <?php echo $sel_session == $val ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2" style="padding: 8px;">
                            <div class="form-group">
                                <label class="required">Course/Class Head</label>
                                <select name="class_head" id="class_head" class="form-control" onchange="getClassesByHead(this.value)">
                                    <option value="">All</option>
                                    <?php foreach ($classHeads as $ch): ?>
                                        <option value="<?php echo e($ch['id']); ?>" <?php echo $sel_class_head == $ch['id'] ? 'selected' : ''; ?>><?php echo e($ch['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2" style="padding: 8px;">
                            <div class="form-group">
                                <label class="required">Course/Class</label>
                                <select name="class_id" id="class_id" class="form-control">
                                    <option value="">Select Course/Class</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?php echo $c['class_id']; ?>" <?php echo $sel_class == $c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2" style="padding: 8px;">
                            <div class="form-group">
                                <label class="required">Section</label>
                                <select name="section" id="txt_section" class="form-control inputheight">
                                    <option value="">All</option>
                                    <?php foreach ($sections as $sec): ?>
                                        <option value="<?php echo $sec['section_id']; ?>" <?php echo $sel_section == $sec['section_id'] ? 'selected' : ''; ?>><?php echo e($sec['section_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding: 8px;">
                            <div style="display:flex; flex-wrap:nowrap; justify-content:flex-end; align-items:flex-end; gap:6px;">
                                <div class="form-group" style="margin:0; flex:0 0 auto;">
                                    <label>Group / Shift</label>
                                    <select name="group_shift" class="form-control" style="min-width:110px; font-size:13px;">
                                        <option value="All">All</option>
                                        <?php foreach ($shifts as $sh): ?>
                                            <option value="<?php echo e($sh); ?>" <?php echo $sel_shift == $sh ? 'selected' : ''; ?>><?php echo e($sh); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin:0; flex:0 0 auto;">
                                    <label class="required">Attendance Date</label>
                                    <input class="form-control" type="date" name="date" id="date" value="<?php echo e($sel_date); ?>" style="width:150px; font-size:13px;">
                                </div>
                                <div class="form-group" style="margin:0; flex:0 0 auto;">
                                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:0;">Search</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>
        </form>

        <?php if ($sel_class > 0): ?>
        <form method="post" action="mark_attend.php" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
            <input type="hidden" name="action" value="MarkAttendance">
            <input type="hidden" name="date" value="<?php echo e($sel_date); ?>">
            <div style="overflow-x:auto;">
                <table id="listofstudents" class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:10px;">
                    <thead>
                        <tr>
                            <th width="5%">S.No</th>
                            <th width="7%">GR. No</th>
                            <th width="20%">Student / Father Name</th>
                            <th width="10%">Section</th>
                            <th width="8%">Shift</th>
                            <th width="50%">Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($students) === 0): ?>
                            <tr><td colspan="6" style="text-align:center; color:#6B7280; padding:30px;">No students found in selected class/section.</td></tr>
                        <?php endif; ?>
                        <?php $i = 1; foreach ($students as $st): $cur = $st['att_status']; ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo e($st['gr_no'] ?? ($st['roll_no'] ?? $st['student_id'])); ?></td>
                                <td>
                                    <strong><?php echo e($st['first_name']); ?></strong>
                                    <div style="font-size:11px; color:#6B7280;"><?php echo e($st['father_name'] ?? $st['last_name']); ?></div>
                                </td>
                                <td><?php echo e($st['section_name'] ?? '-'); ?></td>
                                <td><?php echo e($st['group_shift'] ?? '-'); ?></td>
                                <td>
                                    <div class="attendance-pills">
                                        <label class="att-option present">
                                            <input type="radio" name="att[<?php echo $st['student_id']; ?>]" value="present" <?php echo $cur === 'present' ? 'checked' : ''; ?>>
                                            <span>P</span>
                                        </label>
                                        <label class="att-option absent">
                                            <input type="radio" name="att[<?php echo $st['student_id']; ?>]" value="absent" <?php echo $cur === 'absent' ? 'checked' : ''; ?>>
                                            <span>A</span>
                                        </label>
                                        <label class="att-option late">
                                            <input type="radio" name="att[<?php echo $st['student_id']; ?>]" value="late" <?php echo $cur === 'late' ? 'checked' : ''; ?>>
                                            <span>L</span>
                                        </label>
                                        <label class="att-option leave">
                                            <input type="radio" name="att[<?php echo $st['student_id']; ?>]" value="leave" <?php echo $cur === 'leave' ? 'checked' : ''; ?>>
                                            <span>Lv</span>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" name="submit" class="btn btn-success" style="padding:10px 30px;"><i class="fa fa-check"></i> Save Attendance</button>
        </form>
        <?php else: ?>
            <div style="text-align:center; color:#6B7280; padding:40px; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
                Please select a Class and click Search to load students.
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- QR Scanner Modal -->
<div class="modal fade" id="qrScanModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-qrcode"></i> QR Attendance Scanner</h4>
            </div>
            <div class="modal-body">
                <p style="color:#6B7280;">Scan a student QR code or enter the GR No / Roll No manually below.</p>
                <div class="input-group">
                    <input type="text" id="qrGrNo" class="form-control" placeholder="Enter GR No / Roll No">
                    <span class="input-group-btn">
                        <button class="btn btn-primary" type="button" onclick="qrLookup()"><i class="fa fa-search"></i> Lookup</button>
                    </span>
                </div>
                <div id="qrResult" style="margin-top:12px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('class_id').addEventListener('change', function(){
    var cid = this.value;
    var sel = document.getElementById('txt_section');
    sel.innerHTML = '<option value="">All</option>';
    if (!cid) return;
    fetch('<?php echo BASE_URL; ?>ajax_get_sections.php?class_id=' + cid)
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data && data.length) {
                data.forEach(function(s){
                    var o = document.createElement('option');
                    o.value = s.section_id; o.textContent = s.section_name;
                    sel.appendChild(o);
                });
            }
        })
        .catch(function(){ /* fallback: keep All option only */ });
});

function getSectionAll(val){ document.getElementById('class_id').value = val; document.getElementById('class_id').dispatchEvent(new Event('change')); }
function getClassesByHead(val){
    if (!val) { resetClassDropdown(); return; }
    fetch('<?php echo BASE_URL; ?>ajax_get_classes_by_head.php?head=' + encodeURIComponent(val))
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data && data.length) {
                var sel = document.getElementById('class_id');
                sel.innerHTML = '<option value="">Select Course/Class</option>';
                data.forEach(function(c){
                    var o = document.createElement('option');
                    o.value = c.class_id; o.textContent = c.class_name;
                    sel.appendChild(o);
                });
            }
        })
        .catch(function(){ /* capability fallback: keep all classes */ });
}
function resetClassDropdown(){
    var sel = document.getElementById('class_id');
    if (sel.querySelector('option[value=""]')) { /* keep as-is */ }
}

function qrLookup(){
    var gr = document.getElementById('qrGrNo').value.trim();
    var out = document.getElementById('qrResult');
    if (!gr) { out.innerHTML = '<div class="alert alert-warning">Please enter a GR No / Roll No.</div>'; return; }
    out.innerHTML = '<div class="text-muted"><i class="fa fa-spinner fa-spin"></i> Searching...</div>';
    fetch('<?php echo BASE_URL; ?>mark_attend.php?action=findStudent&gr_no=' + encodeURIComponent(gr))
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                var s = d.student;
                var today = new Date().toISOString().slice(0,10);
                var link = '<?php echo BASE_URL; ?>mark_attend.php?class_id=' + s.class_id + '&date=' + today;
                out.innerHTML = '<div class="alert alert-success">' +
                    '<strong>' + escapeHtml(s.first_name) + ' ' + escapeHtml(s.last_name || '') + '</strong><br>' +
                    'GR No: ' + escapeHtml(s.gr_no || s.roll_no || s.student_id) + '<br>' +
                    'Class: ' + escapeHtml(s.class_name || '-') + ' / ' + escapeHtml(s.section_name || '-') +
                    '<br><a href="' + link + '" class="btn btn-success btn-sm" style="margin-top:8px;"><i class="fa fa-edit"></i> Mark Attendance</a></div>';
            } else {
                out.innerHTML = '<div class="alert alert-danger">' + escapeHtml(d.message) + '</div>';
            }
        })
        .catch(function(){ out.innerHTML = '<div class="alert alert-danger">Search service unavailable. Please try the manual list.</div>'; });
}
function escapeHtml(t){ var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }
function toggleAdvanceSearch(){
    var box = document.getElementById('advanceSearch');
    var icon = document.getElementById('advIcon');
    if (!box) return;
    var hide = box.style.display === 'none';
    if (hide) {
        box.style.display = '';
        box.style.animation = '';
        box.style.animation = 'fadeInDown .2s ease';
        if (icon) icon.className = 'fa fa-chevron-up';
    } else {
        box.style.display = 'none';
        if (icon) icon.className = 'fa fa-chevron-down';
    }
}
    var scr = document.getElementById('advanceSearch');
    if (scr) setTimeout(function(){ scr.scrollIntoView({behavior:'smooth', block:'start'}); }, 80);
}
    if (icon) icon.className = 'fa fa-chevron-up';
    var scr = document.getElementById('advanceSearch');
    if (scr) setTimeout(function(){ scr.scrollIntoView({behavior:'smooth', block:'start'}); }, 60);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
