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
                     'class_id','section_id','admission_date','status'];
            $vals = [$name, $father_name, $father_name, $mother_name, $cell, $dob, $gender, $religion, $session !== '' ? $session : null,
                     $father_cnic !== '' ? $father_cnic : null, $father_cell !== '' ? $father_cell : null,
                     $guardian_name !== '' ? $guardian_name : null, $guardian_cnic !== '' ? $guardian_cnic : null,
                     $guardian_cell !== '' ? $guardian_cell : null, $address !== '' ? $address : null,
                     $class_id, $section_id, $adm_db, 1];

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
.step-wizard{background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.06);border:1px solid #f1f5f9;padding:14px 20px;margin-bottom:18px;}
.step-wizard-compact{display:flex;align-items:center;justify-content:center;gap:0;max-width:400px;margin:0 auto;}
.step-wizard-item{display:flex;flex-direction:column;align-items:center;cursor:pointer;position:relative;z-index:1;}
.step-wizard-item .circle{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;transition:all .2s;}
.step-wizard-item .circle.active{background:#f97316;color:#fff;box-shadow:0 2px 8px rgba(249,115,22,.35);}
.step-wizard-item .circle.done{background:#10b981;color:#fff;}
.step-wizard-item .circle.pending{background:#e5e7eb;color:#6b7280;border:2px solid #fff;}
.step-wizard-item .label{font-size:11px;font-weight:600;margin-top:4px;white-space:nowrap;}
.step-wizard-item .label.active{color:#ea580c;}
.step-wizard-item .label.pending{color:#9ca3af;}
.step-wizard-line{flex:1;height:2px;background:#e5e7eb;margin:0 -4px;position:relative;top:-10px;}
.step-wizard-line.active{background:#f97316;}

.nav-tab-top{padding:10px 18px;font-size:12.5px;font-weight:600;color:#4b5563;text-decoration:none;display:flex;align-items:center;gap:7px;border-bottom:2px solid transparent;transition:all .15s;background:#fff;}
.nav-tab-top:hover{color:#ea580c;background:#fff7ed;}
.nav-tab-top.active{color:#ea580c;border-bottom-color:#f97316;background:#fff;}
.nav-tab-top i{font-size:13px;}

.floating-label-group{position:relative;}
.floating-label-group label{position:absolute;top:-0.6rem;left:0.6rem;background-color:#fff;padding:0 .3rem;font-size:11px;font-weight:500;color:#64748b;border-radius:2px;z-index:10;}
.custom-input{width:100%;border:1px solid #cbd5e1;border-radius:.375rem;padding:.45rem .75rem;font-size:13px;transition:all .15s ease-in-out;background-color:#fff;}
.custom-input:focus{outline:none;border-color:#f97316;box-shadow:0 0 0 2px rgba(249,115,22,.2);}

.bulk-student-row{display:grid;grid-template-columns:2.5fr 1fr 1fr 1fr 1fr 1fr 40px;gap:10px;align-items:center;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:8px;background:#fff;transition:all .15s;}
.bulk-student-row:hover{border-color:#f97316;background:#fffaf5;}
.bulk-student-row .custom-input{padding:.35rem .6rem;font-size:12px;}

.bulk-header{display:grid;grid-template-columns:2.5fr 1fr 1fr 1fr 1fr 1fr 40px;gap:10px;padding:6px 10px;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px;}
</style>

<div style="background:#f1f5f9;min-height:100vh;padding:0 4px 24px;">

<!-- Breadcrumb -->
<div style="padding:10px 4px;font-size:12.5px;color:#64748b;font-weight:500;">
    <a href="dashboard.php" style="color:#377dff;text-decoration:none;">Dashboard</a>
    <span style="margin:0 8px;">&raquo;</span>
    <a href="manage_students.php" style="color:#377dff;text-decoration:none;">Students</a>
    <span style="margin:0 8px;">&raquo;</span>
    <span style="color:#1e293b;font-weight:600;">Add Multi Students</span>
</div>

<!-- Top Tabs -->
<div style="background:#fff;border-radius:10px 10px 0 0;border:1px solid #e5e7eb;border-bottom:none;padding:0 0 0;">
    <div style="display:flex;flex-wrap:wrap;gap:0;border-bottom:1px solid #e5e7eb;">
        <a href="add_student.php" class="nav-tab-top">
            <i class="fa fa-user-plus"></i> Add New Student
        </a>
        <a href="bulk_stdns.php" class="nav-tab-top active">
            <i class="fa fa-users"></i> Add Multi Students
        </a>
        <a href="import_data.php" class="nav-tab-top">
            <i class="fa fa-upload"></i> Import Students with CSV
        </a>
        <a href="adm_form.php" target="_blank" class="nav-tab-top">
            <i class="fa fa-file-text"></i> Admission Form
        </a>
    </div>
</div>

<!-- Step Wizard -->
<div class="step-wizard">
    <div class="step-wizard-compact">
        <div class="step-wizard-item">
            <div class="circle active">1</div>
            <span class="label active">Student Info</span>
        </div>
        <div class="step-wizard-line active"></div>
        <div class="step-wizard-item">
            <div class="circle done">2</div>
            <span class="label active">Fee Plan</span>
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

<!-- Form Container -->
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:0 0 10px 10px;overflow:hidden;padding:20px;">

    <form id="bulkForm" method="post" action="bulk_stdns.php">
        <input type="hidden" name="action" value="BulkAdd">

        <!-- Common Details -->
        <div style="border-left:4px solid #f97316;padding-left:12px;margin-bottom:18px;">
            <h4 style="font-size:15px;font-weight:700;color:#111827;margin:0;"><i class="fa fa-cog" style="color:#f97316;"></i> Common Details</h4>
        </div>

        <div class="form-row" style="margin-bottom:12px;">
            <div class="form-group col-md-3">
                <div class="floating-label-group">
                    <label>Session</label>
                    <select name="session" class="custom-input">
                        <?php foreach ($sessions as $s): ?>
                            <option value="<?php echo e($s); ?>" <?php echo $s === $cur_session ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group col-md-3">
                <div class="floating-label-group">
                    <label>Select Class *</label>
                    <select name="class" id="class_id" class="custom-input" required onchange="loadSections(this.value)">
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $c): ?><option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group col-md-3">
                <div class="floating-label-group">
                    <label>Select Section</label>
                    <select name="section" id="txt_section" class="custom-input"></select>
                </div>
            </div>
            <div class="form-group col-md-3">
                <div class="floating-label-group">
                    <label>Gender</label>
                    <select name="gender" class="custom-input">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="form-row" style="margin-bottom:12px;">
            <div class="form-group col-md-3">
                <div class="floating-label-group">
                    <label>Date Of Admission</label>
                    <input type="text" name="date_of_adms" class="custom-input" value="<?php echo date('d/m/Y'); ?>" placeholder="dd/mm/yyyy">
                </div>
            </div>
            <div class="form-group col-md-3">
                <div class="floating-label-group">
                    <label>Father Name</label>
                    <input type="text" name="lname" class="custom-input" placeholder="Father Name">
                </div>
            </div>
            <div class="form-group col-md-3">
                <div class="floating-label-group">
                    <label>Mother Name</label>
                    <input type="text" name="mother_name" class="custom-input" placeholder="Mother Name">
                </div>
            </div>
            <div class="form-group col-md-3">
                <div class="floating-label-group">
                    <label>Father Cell No</label>
                    <input type="text" name="father_cellno" class="custom-input" placeholder="Father Cell No">
                </div>
            </div>
        </div>
        <div class="form-row" style="margin-bottom:12px;">
            <div class="form-group col-md-3">
                <div class="floating-label-group">
                    <label>Guardian Name</label>
                    <input type="text" name="gname" class="custom-input" placeholder="Guardian Name">
                </div>
            </div>
            <div class="form-group col-md-3">
                <div class="floating-label-group">
                    <label>Guardian Cell No</label>
                    <input type="text" name="Gcellno" class="custom-input" placeholder="Guardian Cell No">
                </div>
            </div>
            <div class="form-group col-md-3">
                <div class="floating-label-group">
                    <label>Guardian CNIC</label>
                    <input type="text" name="Gcnic" class="custom-input" placeholder="Guardian CNIC">
                </div>
            </div>
            <div class="form-group col-md-3">
                <div class="floating-label-group">
                    <label>Father CNIC</label>
                    <input type="text" name="cnic" class="custom-input" placeholder="Father CNIC">
                </div>
            </div>
        </div>
        <div class="form-row" style="margin-bottom:20px;">
            <div class="form-group col-md-12">
                <div class="floating-label-group">
                    <label>Home Address</label>
                    <input type="text" name="address" class="custom-input" placeholder="Family Home Address">
                </div>
            </div>
        </div>

        <!-- Student Rows Section -->
        <div style="border-left:4px solid #8b5cf6;padding-left:12px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="font-size:15px;font-weight:700;color:#111827;margin:0;"><i class="fa fa-users" style="color:#8b5cf6;"></i> Student Names / Details</h4>
            <button type="button" id="addRowBtn" onclick="addRow()" style="padding:6px 14px;background:#10b981;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;">
                <i class="fa fa-plus"></i> Add Row
            </button>
        </div>

        <div style="font-size:11px;color:#9ca3af;margin-bottom:8px;">Maximum 20 students per batch. <span id="rowCount" style="font-weight:600;color:#f97316;">0</span>/20 students added.</div>

        <!-- Header Row -->
        <div class="bulk-header">
            <div>Student Name *</div>
            <div>Cell Number</div>
            <div>Father Name</div>
            <div>Gender</div>
            <div>Date of Birth</div>
            <div>Religion</div>
            <div></div>
        </div>

        <div id="rowsContainer"></div>

        <!-- Action Bar -->
        <div style="margin-top:24px;padding-top:16px;border-top:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:12px;color:#9ca3af;font-style:italic;">* Marked fields are mandatory</span>
            <div style="display:flex;gap:12px;">
                <a href="manage_students.php" style="padding:8px 24px;border:1px solid #d1d5db;color:#4b5563;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Cancel</a>
                <button type="button" onclick="submitBulk()" style="padding:8px 24px;background:#f97316;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;box-shadow:0 1px 3px rgba(249,115,22,.3);display:flex;align-items:center;gap:6px;">
                    <i class="fa fa-check"></i> Save All Students
                </button>
            </div>
        </div>
    </form>
</div>

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
    var container = document.getElementById('rowsContainer');
    var div = document.createElement('div');
    div.className = 'bulk-student-row';
    div.id = 'row_' + rowIndex;
    var idx = rowIndex;
    div.innerHTML =
        '<div><input type="text" name="names[]" class="custom-input" placeholder="Student Name" required></div>' +
        '<div><input type="text" name="cells[]" class="custom-input" placeholder="Cell Number"></div>' +
        '<div><input type="text" name="father_names[]" class="custom-input" placeholder="Father Name"></div>' +
        '<div><select name="genders[]" class="custom-input"><option value="male">Male</option><option value="female">Female</option></select></div>' +
        '<div><input type="text" name="dobs[]" class="custom-input" placeholder="dd/mm/yyyy"></div>' +
        '<div><select name="religions[]" class="custom-input"><option value="Islam">Muslim</option><option value="Hinduism">Hindu</option><option value="Sikhism">Sikh</option><option value="Christianity">Christian</option></select></div>' +
        '<div><button type="button" class="btn-remove-row" onclick="removeRow(' + idx + ')" style="width:28px;height:28px;border-radius:50%;border:1px solid #fca5a5;background:#fef2f2;color:#dc2626;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;" title="Remove"><i class="fa fa-trash"></i></button></div>';
    container.appendChild(div);
    rowIndex++;
    updateRowCount();
}

function removeRow(idx) {
    var el = document.getElementById('row_' + idx);
    if (el) {
        el.style.opacity = '0';
        el.style.transform = 'translateX(20px)';
        el.style.transition = 'all .2s';
        setTimeout(function() { el.remove(); updateRowCount(); }, 200);
    }
}

function updateRowCount() {
    var count = document.querySelectorAll('.bulk-student-row').length;
    var el = document.getElementById('rowCount');
    if (el) el.textContent = count;
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

function submitBulk() {
    var rows = document.querySelectorAll('.bulk-student-row');
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
