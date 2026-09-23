<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Students Cards';

// ---- Student filters ----
$selSession = trim((string)($_GET['session'] ?? ''));
$selClass   = (int) ($_GET['class_id'] ?? 0);
$selSection = (int) ($_GET['section_id'] ?? 0);

// ---- Data ----
$sessions = [];
$res = db_query("SELECT DISTINCT session FROM students WHERE session IS NOT NULL AND session <> '' ORDER BY session");
while ($row = $res->fetch_assoc()) { $sessions[] = $row['session']; }

$classes = [];
$res = db_query("SELECT class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sections = [];
if ($selClass > 0) {
    $res = db_query("SELECT section_id, section_name FROM sections WHERE class_id=$selClass ORDER BY section_name");
    while ($row = $res->fetch_assoc()) { $sections[] = $row; }
}

$students = [];
$sql = "SELECT s.student_id, s.first_name, s.last_name, s.father_name, s.gr_no,
               c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.class_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        WHERE s.status=1";
if ($selClass > 0) { $sql .= " AND s.class_id=$selClass"; }
if ($selSection > 0) { $sql .= " AND s.section_id=$selSection"; }
if ($selSession !== '') { $sql .= " AND s.session='" . db_connect()->real_escape_string($selSession) . "'"; }
$sql .= " ORDER BY s.first_name";
$res = db_query($sql);
while ($row = $res->fetch_assoc()) { $students[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
    .cards-head { display:flex; align-items:center; justify-content:space-between; padding:12px 4px; flex-wrap:wrap; gap:10px; }
    .cards-head h3 { font-size:18px; font-weight:800; color:#111827; margin:0; }
    .cards-head .cards-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .card-panel { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:24px; }
    .card-panel > h4 { font-size:15px; font-weight:800; color:#111827; margin:0 0 4px; }
    .card-panel > p { color:#6B7280; font-size:12.5px; margin:0 0 14px; }
    .card-filters { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:14px; }
    .card-filters .form-group { margin-bottom:0; min-width:180px; }
    .card-filters label { font-size:12px; font-weight:700; color:#374151; margin-bottom:4px; display:block; }
    .scg-pick-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
    .scg-pick-count { font-size:13px; color:#374151; }
    .scg-pick-count b { color:#111827; }
    .scg-select-all-btn { border:1px solid #d1d5db; background:#fff; border-radius:6px; padding:5px 12px; font-size:12.5px; cursor:pointer; }
    .scg-select-all-btn:hover { background:#f3f4f6; }
    .scg-search-box { position:relative; margin-left:auto; }
    .scg-search-box i { position:absolute; left:9px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:12px; }
    .scg-search-box input { border:1px solid #d1d5db; border-radius:6px; padding:6px 10px 6px 28px; font-size:12.5px; width:220px; }
    .table { width:100%; border-collapse:collapse; background:#fff; }
    .table th { background:#000; color:#fff; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.3px; padding:9px 10px; border:1px solid #000; }
    .table td { border:1px solid #e5e7eb; padding:8px 10px; font-size:13px; color:#111827; }
    .table tbody tr { cursor:pointer; }
    .table tbody tr:hover td { background:#eff6ff; }
    .check-col { width:40px; text-align:center; }
    .check-col input { width:16px; height:16px; cursor:pointer; }
    .print-btn { margin-top:10px; }
    @media (max-width:600px){ .cards-head .cards-actions { width:100%; } .card-filters .form-group { min-width:100%; } .scg-search-box { margin-left:0; width:100%; } .scg-search-box input { width:100%; } }
</style>

<script>
function collectChecked(name) {
    var vals = [];
    document.querySelectorAll('input[name="' + name + '"]:checked').forEach(function (cb) { vals.push(cb.value); });
    return vals;
}
function printSelectedStudents() {
    var ids = collectChecked('student_ids_checkbox[]');
    if (ids.length === 0) { alert('Please choose any student from checkboxes...'); return false; }
    document.getElementById('selected_student_ids').value = ids.join(',');
    document.getElementById('studentCardForm').submit();
}
function toggleAllStudents(chk) {
    document.querySelectorAll('input[name="student_ids_checkbox[]"]').forEach(function (cb) { cb.checked = chk.checked; });
}
function applyStudentFilter() {
    var s = document.getElementById('sessionFilter').value;
    var c = document.getElementById('classFilter').value;
    var sec = document.getElementById('sectionFilter').value;
    window.location = '<?php echo BASE_URL; ?>students_card.php?session=' + encodeURIComponent(s) + '&class_id=' + c + '&section_id=' + sec;
}
function searchStudents() {
    var q = (document.getElementById('studentTableSearch').value || '').toLowerCase();
    var rows = document.querySelectorAll('#studentTable tbody tr');
    rows.forEach(function (row) {
        var txt = (row.textContent || '').toLowerCase();
        row.style.display = txt.indexOf(q) > -1 ? '' : 'none';
    });
    var shown = 0;
    rows.forEach(function (row) {
        var txt = (row.textContent || '').toLowerCase();
        if (txt.indexOf(q) > -1) { shown++; }
    });
    var el = document.getElementById('studentFoundCount');
    if (el) { el.textContent = shown; }
}
function openStudentCard(id) {
    var url = '<?php echo BASE_URL; ?>print_students_cards.php?student_ids=' + id;
    window.open(url, '_blank');
}
</script>

<div class="main-content">
    <div class="container-fluid">

        <div class="cards-head">
            <h3><i class="fa fa-graduation-cap"></i> Students Cards</h3>
            <div class="cards-actions">
                <a href="<?php echo BASE_URL; ?>cards.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-id-card"></i> Staff Cards</a>
            </div>
        </div>

        <div class="card-panel">
            <p>Choose students to generate their ID cards. Click on a row to view that student's card.</p>

            <div class="card-filters">
                <div class="form-group">
                    <label>Session</label>
                    <select id="sessionFilter" class="form-control" onchange="applyStudentFilter()">
                        <option value="">All</option>
                        <?php foreach ($sessions as $ss): ?>
                            <option value="<?php echo e($ss); ?>" <?php echo $selSession === $ss ? 'selected' : ''; ?>><?php echo e($ss); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Class</label>
                    <select id="classFilter" class="form-control" onchange="applyStudentFilter()">
                        <option value="0">All</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo (int)$c['class_id']; ?>" <?php echo $selClass === (int)$c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Section</label>
                    <select id="sectionFilter" class="form-control" onchange="applyStudentFilter()">
                        <option value="0">All</option>
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?php echo (int)$sec['section_id']; ?>" <?php echo $selSection === (int)$sec['section_id'] ? 'selected' : ''; ?>><?php echo e($sec['section_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <form id="studentCardForm" action="<?php echo BASE_URL; ?>print_students_cards.php" method="post" target="_blank">
                <input type="hidden" name="student_ids" id="selected_student_ids" value="">

                <div class="scg-pick-bar">
                    <div class="scg-pick-count"><b id="studentFoundCount"><?php echo count($students); ?></b> students found &middot; <b>0</b> selected</div>
                    <button type="button" class="scg-select-all-btn" onclick="document.querySelectorAll('#studentTable tbody input[name=\'student_ids_checkbox[]\']').forEach(function(cb){cb.checked=true;});"><i class="fa fa-check-square-o"></i> Select All</button>
                    <div class="scg-search-box"><i class="fa fa-search"></i><input type="text" id="studentTableSearch" placeholder="Search in this list&hellip;" oninput="searchStudents();"></div>
                </div>

                <div style="overflow-x:auto;">
                    <table id="studentTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th class="check-col"><input type="checkbox" onclick="toggleAllStudents(this)"></th>
                                <th style="width:6%;">S.No</th>
                                <th>GR.NO</th>
                                <th>Student Name</th>
                                <th>Father Name</th>
                                <th>Class/Section</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($students) === 0): ?>
                                <tr><td colspan="6" style="text-align:center; color:#6B7280;">No students found.</td></tr>
                            <?php endif; ?>
                            <?php $si = 0; foreach ($students as $st): $si++;
                                $stName  = trim(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? ''));
                                $grNow   = trim($st['gr_no'] ?? '') !== '' ? $st['gr_no'] : '';
                                $clsSec  = trim((string)($st['class_name'] ?? '')) . (trim((string)($st['section_name'] ?? '')) !== '' ? ' - ' . e($st['section_name']) : '');
                            ?>
                                <tr onclick="openStudentCard(<?php echo (int)$st['student_id']; ?>)">
                                    <td class="check-col" onclick="event.stopPropagation();"><input type="checkbox" name="student_ids_checkbox[]" value="<?php echo (int)$st['student_id']; ?>"></td>
                                    <td style="text-align:center;"><?php echo $si; ?></td>
                                    <td><?php echo e($grNow); ?></td>
                                    <td><?php echo e($stName); ?></td>
                                    <td><?php echo e($st['father_name'] ?? ''); ?></td>
                                    <td><?php echo $clsSec; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="display:flex; justify-content:flex-end; margin-top:10px;">
                    <button type="button" class="btn btn-primary print-btn" style="color:#fff; margin-top:0;" onclick="printSelectedStudents()"><i class="fa fa-print"></i> Print Selected Students Cards</button>
                </div>
            </form>
        </div>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>