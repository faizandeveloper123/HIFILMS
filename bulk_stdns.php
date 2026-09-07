<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Add Multi Students';
$message = '';
$error = '';

$classes = [];
$res = db_query("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sessions = [];
for ($y = 2018; $y <= 2030; $y++) { $sessions[] = $y . '-' . substr($y + 1, -2); }
$cur_session = get_setting('session_year', '2026-2027');
if (!in_array($cur_session, $sessions, true)) { array_unshift($sessions, $cur_session); }

$boards = [];
$r = db_query("SELECT id, name FROM boards ORDER BY name");
while ($row = $r->fetch_assoc()) { $boards[] = $row; }

$groups = [];
$r = db_query("SELECT id, name FROM `groups` ORDER BY name");
while ($row = $r->fetch_assoc()) { $groups[] = $row; }

$admSrcs = [];
$r = db_query("SELECT id, name FROM admission_sources ORDER BY name");
while ($row = $r->fetch_assoc()) { $admSrcs[] = $row; }

$localities = [];
$r = db_query("SELECT locality_id, locality_name FROM localities WHERE status=1 ORDER BY locality_name");
while ($row = $r->fetch_assoc()) { $localities[] = $row; }

function bulk_parse_date($d) {
    $d = trim($d);
    if ($d === '') return null;
    $ts = strtotime(str_replace('/', '-', $d));
    return $ts ? date('Y-m-d', $ts) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'BulkAdd') {
    $class_id   = (int) ($_POST['class'] ?? 0);
    $section_id = (int) ($_POST['section'] ?? 0) ?: null;
    $session    = trim($_POST['session'] ?? '');
    $gender     = strtolower($_POST['gender'] ?? '') ?: 'male';
    $religion   = $_POST['religion'] ?? 'Islam';
    $father_name = trim($_POST['lname'] ?? '');
    $mother_name = trim($_POST['mother_name'] ?? '');
    $father_cell = trim($_POST['father_cellno'] ?? '');
    $guardian_cell = trim($_POST['Gcellno'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $guardian_name = trim($_POST['gname'] ?? '');
    $guardian_cnic = trim($_POST['Gcnic'] ?? '');
    $father_cnic = trim($_POST['cnic'] ?? '');
    $adm_db     = bulk_parse_date($_POST['date_of_adms'] ?? '') ?? date('Y-m-d');
    $board_council = trim($_POST['board_council'] ?? '') ?: null;
    $group_shift   = trim($_POST['group_shift'] ?? '') ?: null;
    $adm_source    = trim($_POST['adm_source'] ?? '') ?: null;
    $locality_id   = ($_POST['Locality'] ?? '') !== '' ? (int) $_POST['Locality'] : null;

    $names = isset($_POST['names']) ? array_map('trim', (array)$_POST['names']) : [];
    $cells = isset($_POST['cells']) ? array_map('trim', (array)$_POST['cells']) : [];
    $dobs  = isset($_POST['dobs']) ? (array)$_POST['dobs'] : [];

    if ($class_id === 0) {
        $error = 'Please select a class.';
    } else {
        $added = 0;
        $family_code = null;
        $key = $guardian_cell !== '' ? $guardian_cell : ($father_cell !== '' ? $father_cell : null);
        if ($key) {
            $ex = db_prepare("SELECT family_code FROM students WHERE family_code IS NOT NULL AND family_code <> '' AND (guardian_cellno = ? OR father_cellno = ?) LIMIT 1");
            $ex->bind_param('ss', $key, $key);
            $ex->execute();
            $fr = $ex->get_result()->fetch_assoc();
            $family_code = $fr ? $fr['family_code'] : ('F-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT));
        }

        foreach ($names as $i => $name) {
            if ($name === '') continue;
            $cell = $cells[$i] ?? '';
            $dob  = bulk_parse_date($dobs[$i] ?? '');

            $cols = ['first_name','last_name','father_name','mother_name','phone','dob','gender','religion','session',
                     'father_cnic','father_cellno','guardian_name','guardian_cnic','guardian_cellno','address',
                     'class_id','section_id','admission_date','status','board_council','group_shift','admission_source','locality_id'];
            $vals = [$name, $father_name, $father_name, $mother_name, $cell, $dob, $gender, $religion, $session !== '' ? $session : null,
                     $father_cnic !== '' ? $father_cnic : null, $father_cell !== '' ? $father_cell : null,
                     $guardian_name !== '' ? $guardian_name : null, $guardian_cnic !== '' ? $guardian_cnic : null,
                     $guardian_cell !== '' ? $guardian_cell : null, $address !== '' ? $address : null,
                     $class_id, $section_id, $adm_db, 1, $board_council, $group_shift, $adm_source, $locality_id];

            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $sql = 'INSERT INTO students (`' . implode('`,`', $cols) . '`) VALUES (' . $placeholders . ')';
            try {
                $stmt = db_prepare($sql);
                $types = str_repeat('s', count($vals));
                $bindVals = [$types];
                foreach ($vals as $k => $v) { $bindVals[] = &$vals[$k]; }
                call_user_func_array([$stmt, 'bind_param'], $bindVals);
                $stmt->execute();
                $sid = $stmt->insert_id;
                if ($sid > 0) {
                    $gr = substr(date('Y'), 2) . '-' . str_pad($sid, 3, '0', STR_PAD_LEFT);
                    $u = db_prepare('UPDATE students SET gr_no = ? WHERE student_id = ?');
                    $u->bind_param('si', $gr, $sid);
                    $u->execute();
                    if ($family_code) {
                        $u2 = db_prepare('UPDATE students SET family_code = ? WHERE student_id = ?');
                        $u2->bind_param('si', $family_code, $sid);
                        $u2->execute();
                    }
                    $added++;
                }
            } catch (Exception $ex2) {
                $error = 'Error while adding: ' . $ex2->getMessage();
            }
        }
        if ($added > 0) { $message = $added . ' student(s) added successfully!'; }
        elseif ($error === '') { $error = 'No valid student names were provided.'; }
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
.page-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;}
.top-tabs-row{margin-bottom:0;}
.step-wizard{background:#f9fafb;border-radius:10px;padding:14px 20px;margin-bottom:18px;border:1px solid #f1f5f9;}
.step-wizard-compact{display:flex;align-items:center;justify-content:center;gap:0;max-width:400px;margin:0 auto;}
.step-wizard-item{display:flex;flex-direction:column;align-items:center;cursor:pointer;position:relative;z-index:1;}
.step-circle{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;transition:all .2s;}
.step-wizard-item.active .step-circle{background:#f97316;color:#fff;box-shadow:0 2px 8px rgba(249,115,22,.35);}
.step-wizard-item:not(.active) .step-circle{background:#e5e7eb;color:#6b7280;}
.step-label{font-size:11px;font-weight:600;margin-top:4px;white-space:nowrap;}
.step-wizard-item.active .step-label{color:#ea580c;}
.step-wizard-item:not(.active) .step-label{color:#9ca3af;}
.step-wizard-line{flex:1;height:2px;background:#e5e7eb;margin:0 -4px;position:relative;top:-10px;}
.step-wizard-line.active{background:#f97316;}

.icon-tabs{display:flex;flex-wrap:wrap;align-items:center;gap:5px;background:#f9fafb;padding:6px;border-radius:8px;margin-bottom:20px;border:1px solid #f3f4f6;list-style:none;}
.icon-tab-item{padding:7px 13px;border-radius:6px;font-size:12px;font-weight:600;color:#4b5563;cursor:pointer;border:none;background:transparent;display:flex;align-items:center;gap:6px;transition:all .15s;white-space:nowrap;}
.icon-tab-item:hover{background:#f3f4f6;}
.icon-tab-item.active{background:#fff;color:#ea580c;border:1px solid #fed7aa;box-shadow:0 1px 3px rgba(0,0,0,.05);}
.icon-tab-item i{font-size:13px;}

.wizard-section-title{font-size:14px;font-weight:700;color:#111827;margin:14px 0 12px;border-left:4px solid #8b5cf6;padding-left:12px;}
.wizard-section-title span{display:inline;}

.field-with-add{display:flex;gap:4px;align-items:flex-start;}
.field-with-add select,.field-with-add input{flex:1;}
.btn-add-new{margin-top:18px;padding:4px 8px;border:1px solid #fdba74;color:#ea580c;border-radius:4px;font-size:11px;text-decoration:none;font-weight:600;white-space:nowrap;display:inline-block;}
.btn-add-new:hover{background:#fff7ed;}

.col-fifth{width:20%;float:left;padding:0 10px;position:relative;min-height:1px;}
@media(max-width:991px){.col-fifth{width:33.33%;}}
@media(max-width:767px){.col-fifth{width:50%;}}

.mandatory-note{font-size:12px;color:#9ca3af;font-style:italic;}
.wizard-actions-buttons{display:flex;gap:12px;align-items:center;}
.wizard-actions-bar{display:flex;justify-content:space-between;align-items:center;padding:16px 0;border-top:1px solid #f3f4f6;margin-top:20px;}
.wizard-btn{padding:8px 24px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid #d1d5db;background:#fff;color:#4b5563;}
.wizard-btn-primary{background:#f97316;color:#fff;border:none;box-shadow:0 1px 3px rgba(249,115,22,.3);}
.wizard-btn-primary:hover{background:#ea580c;}

.bulk-student-table{width:100%;border-collapse:collapse;}
.bulk-student-table th{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.3px;padding:8px 6px;border-bottom:2px solid #e5e7eb;}
.bulk-student-table td{padding:5px 4px;vertical-align:middle;}
.bulk-student-table tbody tr{transition:all .15s;}
.bulk-student-table tbody tr:hover{background:#fffaf5;}
.bulk-student-table .form-control{padding:.35rem .5rem;font-size:12px;}
</style>

<div class="container mt-4" style="padding-left:0;padding-right:0;">
<div style="padding:10px 0;font-size:12.5px;color:#64748b;font-weight:500;">
    <a href="dashboard.php" style="color:#377dff;text-decoration:none;">Dashboard</a>
    <span style="margin:0 8px;">&raquo;</span>
    <a href="manage_students.php" style="color:#377dff;text-decoration:none;">Students</a>
    <span style="margin:0 8px;">&raquo;</span>
    <span style="color:#1e293b;font-weight:600;">Add Multi Students</span>
</div>
</div>

<div class="container mt-4 page-card">

    <div class="top-tabs-row">
        <ul class="nav nav-tabs" role="tablist">
            <li><a href="add_student.php"><i class="fa fa-user-plus"></i> Add New Student</a></li>
            <li class="active"><a href="bulk_stdns.php"><i class="fa fa-users"></i> Add Multi Students</a></li>
            <li><a href="import_data.php"><i class="fa fa-upload"></i> Import Students with CSV</a></li>
            <li><a href="adm_form.php" target="_blank"><i class="fa fa-file-alt"></i> Admission Form</a></li>
        </ul>

        <div class="step-wizard step-wizard-compact">
            <div class="step-wizard-item active">
                <div class="step-circle">1</div>
                <div class="step-label">Student Info</div>
            </div>
            <div class="step-wizard-line active"></div>
            <div class="step-wizard-item">
                <div class="step-circle">2</div>
                <div class="step-label">Fee Plan</div>
            </div>
        </div>
    </div>

<?php if ($message): ?>
    <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:500;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
        <i class="fa fa-check-circle"></i> <?php echo e($message); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:500;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
        <i class="fa fa-exclamation-circle"></i> <?php echo e($error); ?>
    </div>
<?php endif; ?>

    <form id="bulkForm" method="post" action="bulk_stdns.php">
        <input type="hidden" name="action" value="BulkAdd">

        <!-- ===== Common Details ===== -->
        <div class="wizard-section-title"><span><i class="fa fa-cog" style="color:#f97316;"></i> Common Details</span></div>

        <div class="form-row" style="margin-bottom:12px;">
            <div class="form-group col-md-3">
                <label>Session</label>
                <select name="session" class="form-control">
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?php echo e($s); ?>" <?php echo $s === $cur_session ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label>Select Class *</label>
                <select name="class" id="class_id" class="form-control" required onchange="loadSections(this.value)">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label>Select Section</label>
                <select name="section" id="txt_section" class="form-control"></select>
            </div>
            <div class="form-group col-md-3">
                <label>Gender</label>
                <select name="gender" class="form-control">
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>
        </div>

        <div class="form-row" style="margin-bottom:12px;">
            <div class="form-group col-md-3">
                <label>Date Of Admission</label>
                <input type="text" name="date_of_adms" class="form-control" value="<?php echo date('d/m/Y'); ?>" placeholder="dd/mm/yyyy">
            </div>
            <div class="form-group col-md-3">
                <label>Father Name</label>
                <input type="text" name="lname" class="form-control" placeholder="Father Name">
            </div>
            <div class="form-group col-md-3">
                <label>Mother Name</label>
                <input type="text" name="mother_name" class="form-control" placeholder="Mother Name">
            </div>
            <div class="form-group col-md-3">
                <label>Father Cell No</label>
                <input type="text" name="father_cellno" class="form-control" placeholder="Father Cell No">
            </div>
        </div>

        <div class="form-row" style="margin-bottom:12px;">
            <div class="form-group col-md-3">
                <label>Guardian Name</label>
                <input type="text" name="gname" class="form-control" placeholder="Guardian Name">
            </div>
            <div class="form-group col-md-3">
                <label>Guardian Cell No</label>
                <input type="text" name="Gcellno" class="form-control" placeholder="Guardian Cell No">
            </div>
            <div class="form-group col-md-3">
                <label>Guardian CNIC</label>
                <input type="text" name="Gcnic" class="form-control" placeholder="Guardian CNIC">
            </div>
            <div class="form-group col-md-3">
                <label>Father CNIC</label>
                <input type="text" name="cnic" class="form-control" placeholder="Father CNIC">
            </div>
        </div>

        <div class="form-row" style="margin-bottom:12px;">
            <div class="form-group col-md-3">
                <label>Board / Council</label>
                <div class="field-with-add">
                    <select name="board_council" class="form-control">
                        <option value="">Select Board</option>
                        <?php foreach ($boards as $b): ?>
                            <option value="<?php echo e($b['name']); ?>"><?php echo e($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn-add-new" onclick="addNewRecord('boards','board_council')">+ Add New</button>
                </div>
            </div>
            <div class="form-group col-md-3">
                <label>Group / Shift</label>
                <div class="field-with-add">
                    <select name="group_shift" class="form-control">
                        <option value="">Select Group</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?php echo e($g['name']); ?>"><?php echo e($g['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn-add-new" onclick="addNewRecord('groups','group_shift')">+ Add New</button>
                </div>
            </div>
            <div class="form-group col-md-3">
                <label>Admission Source</label>
                <div class="field-with-add">
                    <select name="adm_source" class="form-control">
                        <option value="">Select Source</option>
                        <?php foreach ($admSrcs as $s): ?>
                            <option value="<?php echo e($s['name']); ?>"><?php echo e($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn-add-new" onclick="addNewRecord('admission_sources','adm_source')">+ Add New</button>
                </div>
            </div>
            <div class="form-group col-md-3">
                <label>Locality</label>
                <div class="field-with-add">
                    <select name="Locality" class="form-control">
                        <option value="">Select Locality</option>
                        <?php foreach ($localities as $l): ?>
                            <option value="<?php echo $l['locality_id']; ?>"><?php echo e($l['locality_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn-add-new" onclick="addNewLocality()">+ Add New</button>
                </div>
            </div>
        </div>

        <div class="form-row" style="margin-bottom:20px;">
            <div class="form-group col-md-12">
                <label>Home Address</label>
                <input type="text" name="address" class="form-control" placeholder="Family Home Address">
            </div>
        </div>

        <!-- ===== Student Details ===== -->
        <div class="wizard-section-title" style="display:flex;align-items:center;justify-content:space-between;">
            <span><i class="fa fa-users" style="color:#8b5cf6;"></i> Student Details</span>
            <button type="button" id="addRowBtn" onclick="addRow()" style="padding:6px 14px;background:#10b981;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;">
                <i class="fa fa-plus"></i> Add Row
            </button>
        </div>

        <div style="font-size:11px;color:#9ca3af;margin-bottom:8px;">Maximum 20 students per batch. <span id="rowCount" style="font-weight:600;color:#f97316;">0</span>/20 students added.</div>

        <div class="table-responsive">
            <table class="bulk-student-table table" id="studentTable">
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th>Student Name *</th>
                        <th>Cell Number</th>
                        <th>Father Name</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Religion</th>
                        <th style="width:45px;">Action</th>
                    </tr>
                </thead>
                <tbody id="rowsContainer">
                </tbody>
            </table>
        </div>

        <!-- Bottom Actions -->
        <div class="wizard-actions-bar">
            <div class="mandatory-note">* Marked fields are mandatory</div>
            <div class="wizard-actions-buttons">
                <a href="add_student.php" class="wizard-btn">Cancel</a>
                <button type="button" onclick="submitBulk()" class="wizard-btn wizard-btn-primary">
                    <i class="fa fa-check"></i> Save All Students
                </button>
            </div>
        </div>
    </form>

</div>

<script>
var HIIFI_BASE = '<?php echo BASE_URL; ?>';
var rowIndex = 0;
var maxRows = 20;

function addRow() {
    if (rowIndex >= maxRows) {
        alert('Maximum 20 students per batch allowed.');
        return;
    }
    var tbody = document.getElementById('rowsContainer');
    var tr = document.createElement('tr');
    tr.id = 'row_' + rowIndex;
    var idx = rowIndex;
    var rowNumber = tbody.querySelectorAll('tr').length + 1;
    tr.innerHTML =
        '<td style="text-align:center;font-size:12px;font-weight:600;color:#6b7280;">' + rowNumber + '</td>' +
        '<td><input type="text" name="names[]" class="form-control" placeholder="Student Name" required></td>' +
        '<td><input type="text" name="cells[]" class="form-control" placeholder="Cell Number"></td>' +
        '<td><input type="text" name="father_names[]" class="form-control" placeholder="Father Name"></td>' +
        '<td><select name="genders[]" class="form-control"><option value="male">Male</option><option value="female">Female</option></select></td>' +
        '<td><input type="text" name="dobs[]" class="form-control" placeholder="dd/mm/yyyy"></td>' +
        '<td><select name="religions[]" class="form-control"><option value="Islam">Muslim</option><option value="Hinduism">Hindu</option><option value="Sikhism">Sikh</option><option value="Christianity">Christian</option></select></td>' +
        '<td><button type="button" class="btn-remove-row" onclick="removeRow(' + idx + ')" style="width:28px;height:28px;border-radius:50%;border:1px solid #fca5a5;background:#fef2f2;color:#dc2626;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;" title="Remove"><i class="fa fa-trash"></i></button></td>';
    tbody.appendChild(tr);
    rowIndex++;
    updateRowCount();
    updateRowNumbers();
}

function removeRow(idx) {
    var el = document.getElementById('row_' + idx);
    if (el) {
        el.style.opacity = '0';
        el.style.transform = 'translateX(20px)';
        el.style.transition = 'all .2s';
        setTimeout(function() { el.remove(); updateRowCount(); updateRowNumbers(); }, 200);
    }
}

function updateRowCount() {
    var count = document.querySelectorAll('#rowsContainer tr').length;
    var el = document.getElementById('rowCount');
    if (el) el.textContent = count;
}

function updateRowNumbers() {
    var rows = document.querySelectorAll('#rowsContainer tr');
    rows.forEach(function(row, i) {
        var numCell = row.querySelector('td:first-child');
        if (numCell) numCell.textContent = i + 1;
    });
}

function loadSections(cid) {
    var sel = document.getElementById('txt_section');
    sel.innerHTML = '<option value="">Loading...</option>';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', HIIFI_BASE + 'ajax_get_sections.php?class_id=' + encodeURIComponent(cid));
    xhr.onload = function() {
        var data = JSON.parse(xhr.responseText || '[]');
        sel.innerHTML = '<option value="">Select Section</option>';
        data.forEach(function(s) {
            var o = document.createElement('option');
            o.value = s.section_id;
            o.textContent = s.section_name;
            sel.appendChild(o);
        });
    };
    xhr.send();
}

function addNewRecord(table, selectName) {
    var name = prompt('Enter new record name:');
    if (name && name.trim() !== '') {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', HIIFI_BASE + 'ajax_add_record.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var resp = JSON.parse(xhr.responseText || '{}');
            if (resp.ok && resp.name) {
                var sel = document.querySelector('[name="' + selectName + '"]');
                var opt = document.createElement('option');
                opt.value = resp.name;
                opt.textContent = resp.name;
                opt.selected = true;
                sel.appendChild(opt);
            } else {
                alert(resp.error || 'Failed to add record.');
            }
        };
        xhr.send('table=' + encodeURIComponent(table) + '&name=' + encodeURIComponent(name.trim()));
    }
}

function addNewLocality() {
    var name = prompt('Enter new locality name:');
    if (name && name.trim() !== '') {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', HIIFI_BASE + 'ajax_add_locality.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var resp = JSON.parse(xhr.responseText || '{}');
            if (resp.ok && resp.id) {
                var sel = document.querySelector('[name="Locality"]');
                var opt = document.createElement('option');
                opt.value = resp.id;
                opt.textContent = resp.name || name.trim();
                opt.selected = true;
                sel.appendChild(opt);
            } else {
                alert(resp.error || 'Failed to add locality.');
            }
        };
        xhr.send('name=' + encodeURIComponent(name.trim()));
    }
}

function submitBulk() {
    var rows = document.querySelectorAll('#rowsContainer tr');
    var hasName = false;
    rows.forEach(function(row) {
        var nameInput = row.querySelector('input[name="names[]"]');
        if (nameInput && nameInput.value.trim() !== '') hasName = true;
    });
    if (!hasName) {
        alert('Please add at least one student name.');
        return;
    }
    var classEl = document.getElementById('class_id');
    if (!classEl.value) {
        alert('Please select a class.');
        classEl.focus();
        return;
    }
    document.getElementById('bulkForm').submit();
}

// Preload 2 rows
addRow();
addRow();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
