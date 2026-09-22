<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Create Monthly Challans';

db_query("CREATE TABLE IF NOT EXISTS class_heads (class_head_id INT AUTO_INCREMENT PRIMARY KEY, class_head_name VARCHAR(150) NOT NULL, status TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
db_query("ALTER TABLE classes ADD COLUMN IF NOT EXISTS class_head_id INT DEFAULT NULL");
db_query("ALTER TABLE fee_challan_items ADD COLUMN IF NOT EXISTS discount DECIMAL(12,2) NOT NULL DEFAULT 0");

$seed = db_query("SELECT COUNT(*) c FROM class_heads")->fetch_assoc()['c'];
if ((int)$seed === 0) {
    foreach (['Hajvery Campus', 'Main Campus', 'Pharm-D', 'Modern Edu'] as $hn) {
        $st = db_prepare("INSERT IGNORE INTO class_heads (class_head_name, status) VALUES (?, 1)");
        $st->bind_param('s', $hn);
        $st->execute();
    }
}

$message = '';
$error = '';

$p = fn($k) => trim($_POST[$k] ?? '') !== '' ? trim($_POST[$k] ?? '') : trim($_GET[$k] ?? '');
function session_year_key($s) { return preg_match('/^(\d{4})/', (string) $s, $m) ? $m[1] : ''; }

$sel_session = $p('session');
if ($sel_session === '') { $sel_session = get_setting('session_year', '2026-2027'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'Create_Monthly_Challan_new') {
        $students = $_POST['std_ids'] ?? [];
        $monthYear = trim($_POST['monthYear'] ?? '');
        $feeHeadRows = $_POST['additional_fee'] ?? [];
        if (!is_array($students)) $students = [];
        if (!is_array($feeHeadRows)) $feeHeadRows = [];

        if (count($students) === 0) {
            $error = 'Please select at least one student to create a challan.';
        } elseif (!preg_match('#^(\d{1,2})/(\d{4})$#', $monthYear, $mm)) {
            $error = 'Please provide a valid challan month in MM/YYYY format.';
        } else {
            $m_num = (int) $mm[1];
            $m_year = (int) $mm[2];
            if ($m_num < 1 || $m_num > 12) {
                $error = 'Invalid month. Please provide month between 1 and 12.';
            } else {
                $addFees = [];
                foreach ($feeHeadRows as $row) {
                    if (!is_array($row)) continue;
                    $fees = $row['fees'] ?? [];
                    if (!is_array($fees)) $fees = [];
                    $collected = 0.0;
                    foreach ($fees as $f) { $collected += (float) trim($f ?? '0'); }
                    $month_str = trim($row['month'] ?? $monthYear);
                    if ($collected > 0 && $month_str !== '') {
                        $addFees[] = ['month' => $month_str, 'total' => $collected];
                    }
                }

                $uid = (int) ($_SESSION['user_id'] ?? 0);
                $created = 0;
                $skipped = 0;

                foreach ($students as $stdId) {
                    $stdId = (int) $stdId;
                    if ($stdId <= 0) continue;
                    $st = db_prepare("SELECT st.student_id, st.first_name, st.last_name, st.father_name, st.gr_no, st.class_id, st.section_id, st.session, st.monthly_fee
                                      FROM students st WHERE st.student_id=? AND st.status=1");
                    $st->bind_param('i', $stdId);
                    $st->execute();
                    $student = $st->get_result()->fetch_assoc();
                    if (!$student) continue;

                    if ($student['session'] && session_year_key($student['session']) !== session_year_key($sel_session)) {
                        continue;
                    }

                    $skip = false;
                    $st2 = db_prepare("SELECT challan_id FROM fee_challans WHERE student_id=? AND CAST(month AS UNSIGNED)=? AND year=? LIMIT 1");
                    $st2->bind_param('iii', $stdId, $m_num, $m_year);
                    $st2->execute();
                    if ($st2->get_result()->fetch_assoc()) { $skipped++; continue; }

                    $baseFee = 0.0;
                    $items = [];
                    $stdMonthlyFee = (float) ($student['monthly_fee'] ?? 0);
                    if ($stdMonthlyFee > 0) {
                        $baseFee = $stdMonthlyFee;
                        $items[] = ['head_id' => 0, 'head_name' => 'Monthly Fee', 'amount' => $stdMonthlyFee];
                    } else {
                        $clsRes = db_query("SELECT f.head_name, f.amount FROM fee_heads f WHERE f.status=1 ORDER BY f.head_id");
                        while ($fh = $clsRes->fetch_assoc()) {
                            $amt = (float) $fh['amount'];
                            if ($amt > 0) {
                                $baseFee += $amt;
                                $items[] = $fh;
                            }
                        }
                    }

                    $total = $baseFee;
                    foreach ($addFees as $af) {
                        $total += $af['total'];
                    }

                    $ticket = strtoupper(substr(md5(uniqid((string)$stdId . time(), true)), 0, 6));
                    $challan_no = 'CH-' . $m_year . '-' . $ticket;
                    $month_label = $m_num . '/' . $m_year;

                    $clsId = (int) $student['class_id'];
                    $st3 = db_prepare("INSERT INTO fee_challans (challan_no, student_id, class_id, month, year, total_amount, paid_amount, created_by, status, created_at)
                                       VALUES (?, ?, ?, ?, ?, ?, 0, ?, 'unpaid', NOW())");
                    $st3->bind_param('siisidi', $challan_no, $stdId, $clsId, $m_num, $m_year, $total, $uid);
                    $st3->execute();
                    $newCid = db_query("SELECT LAST_INSERT_ID() l")->fetch_assoc()['l'];

                    foreach ($items as $fh) {
                        $st4 = db_prepare("INSERT INTO fee_challan_items (challan_id, head_id, description, amount, discount) VALUES (?, ?, ?, ?, 0)");
                        $st4->bind_param('iisd', $newCid, (int)$fh['head_id'], $fh['head_name'], (float)$fh['amount']);
                        $st4->execute();
                    }

                    foreach ($addFees as $idx => $af) {
                        $st5 = db_prepare("INSERT INTO fee_challan_items (challan_id, head_id, description, amount, discount) VALUES (?, NULL, ?, ?, 0)");
                        $desc = 'Additional Fee ' . ($idx + 1) . ' (' . e($af['month']) . ')';
                        $st5->bind_param('isd', $newCid, $desc, $af['total']);
                        $st5->execute();
                    }

                    $created++;
                }

                $message = 'Challans created successfully for <strong>' . $created . '</strong> student(s)';
                if ($skipped > 0) { $message .= '. <strong>' . $skipped . '</strong> skipped (challan already exists for the month).'; }
            }
        }
    }
}

$sel_session = $p('session');
if ($sel_session === '') { $sel_session = get_setting('session_year', '2026-2027'); }
$sessions = [];
for ($y = 2018; $y <= 2030; $y++) { $sessions[] = $y . '-' . substr($y + 1, -2); }
if (!in_array($sel_session, $sessions)) { $sel_session = get_setting('session_year', '2026-2027'); }

$classHeads = [];
$res = db_query("Select class_head_id, class_head_name FROM class_heads WHERE status=1 ORDER BY class_head_name");
while ($row = $res->fetch_assoc()) { $classHeads[] = $row; }

$sel_head = (int) ($p('class_head') ?: 0);
$classes = [];
$res = db_query("SELECT c.class_id, c.class_name, c.class_head_id FROM classes c LEFT JOIN class_heads h ON c.class_head_id = h.class_head_id ORDER BY c.class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sel_class = (int) ($p('class_id') ?: 0);
$sel_section = (int) ($p('section') ?: 0);
$sel_month = $p('monthYear');
$sel_duedate = $p('duedate');
if ($sel_duedate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $sel_duedate)) { $sel_duedate = ''; }
$sel_fee_type = $p('fee_type');
if ($sel_fee_type === '') { $sel_fee_type = 'Monthly'; }
$sel_transport = $p('transport');
if ($sel_transport === '') { $sel_transport = 'Yes'; }

$sections = [];
if ($sel_class > 0) {
    $st = db_prepare("SELECT section_id, section_name FROM sections WHERE class_id=? ORDER BY section_name");
    $st->bind_param('i', $sel_class);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) { $sections[] = $row; }
}

$students = [];
$show_students = false;
$class_fee_total = 0.0;
$clsRes = db_query("SELECT amount FROM fee_heads f WHERE f.status=1 ORDER BY f.head_id");
while ($fh = $clsRes->fetch_assoc()) { $class_fee_total += (float) $fh['amount']; }
if ($sel_class > 0 && $sel_session !== '') {
    $where = ["st.class_id = ?", "st.status = 1"];
    $params = [$sel_class];
    $types = 'i';
    $session_key = session_year_key($sel_session);
    if ($session_key !== '') { $where[] = "LEFT(st.session, 4) = ?"; $params[] = $session_key; $types .= 's'; }
    if ($sel_section > 0) { $where[] = "st.section_id = ?"; $params[] = $sel_section; $types .= 'i'; }
    $where[] = "st.session = ?"; $params[] = $sel_session; $types .= 's';
    $sql = "SELECT st.student_id, st.first_name, st.last_name, st.father_name, st.gr_no, st.class_id, st.section_id, st.status,
            sec.section_name, cl.class_name
            FROM students st
            LEFT JOIN sections sec ON st.section_id = sec.section_id
            LEFT JOIN classes cl ON st.class_id = cl.class_id
            WHERE " . implode(' AND ', $where) . " ORDER BY st.gr_no, st.first_name";
    $st = db_prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) { $students[] = $row; }
    $show_students = count($students) > 0;
}

include __DIR__ . '/includes/header.php';
?>
<style>
.fee-filter-panel { background:#fff; border:1px solid #E5E7EB; border-radius:14px; margin-bottom:16px; overflow:hidden; }
.fee-filter-panel > .panel-heading { background:#fff; border-bottom:1px solid #EEF0F3; padding:14px 16px; }
.fee-filter-panel > .panel-heading h4 { margin:0; font-size:16px; font-weight:800; color:#111827; }
.fee-filter-panel > .panel-body { padding:14px 16px; }
.fee-filter-fields-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
.fee-filter-field { flex:1 1 150px; min-width:145px; }
.fee-filter-field label { font-size:12px; font-weight:700; color:#374151; margin-bottom:4px; display:block; }
.fee-filter-field .inputheight { height:40px; border-radius:9px; }
.fee-filter-btn-col { flex:0 0 auto; }
.fee-filter-btn-col button { height:40px; }
.additional-fees-block { display:none; background:#FFF7EC; border:1px dashed #F59E0B; border-radius:10px; padding:12px; margin-top:14px; }
.cfc-action-bar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; background:#fff; border-radius:12px; border:1px solid #E5E7EB; padding:14px 18px; margin-bottom:16px; }
.cfc-action-info { font-size:13px; font-weight:700; color:#111827; }
.cfc-action-info i { color:#F59E0B; margin-right:6px; }
.cfc-selected-count { font-size:12px; font-weight:600; color:#6B7280; }
.fee-empty-state { text-align:center; color:#6B7280; padding:50px 20px; background:#fff; border:1px solid #E5E7EB; border-radius:14px; }
.fee-empty-state i { font-size:42px; color:#D1D5DB; margin-bottom:12px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-plus-circle"></i> Create Monthly Challans</h3>
            <a href="<?php echo BASE_URL; ?>view_challan_details.php" class="btn btn-info" style="color:#fff;"><i class="fa fa-eye"></i> View Challans</a>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <form method="get" action="monthly_challan.php" class="fee-filter-panel">
            <div class="panel-heading"><h4><i class="fa fa-filter" style="color:#F59E0B;"></i> Advance Search</h4></div>
            <div class="panel-body">
                <div class="fee-filter-fields-row">
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:6px;">
                            <label>Session <span style="color:#DC2626;">*</span></label>
                            <select name="session" class="form-control inputheight">
                                <?php foreach ($sessions as $sv): ?>
                                    <option value="<?php echo e($sv); ?>" <?php echo $sel_session === $sv ? 'selected' : ''; ?>><?php echo e($sv); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:6px;">
                            <label>Course/Class Head</label>
                            <select name="class_head" class="form-control inputheight">
                                <option value="0">All</option>
                                <?php foreach ($classHeads as $ch): ?>
                                    <option value="<?php echo $ch['class_head_id']; ?>" <?php echo $sel_head === (int)$ch['class_head_id'] ? 'selected' : ''; ?>><?php echo e($ch['class_head_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:6px;">
                            <label for="class_id">Course/Class <span style="color:#DC2626;">*</span></label>
                            <select name="class_id" id="class_id" class="form-control inputheight">
                                <option value="0">Select Course/Class</option>
                                <?php foreach ($classes as $cl): ?>
                                    <option value="<?php echo $cl['class_id']; ?>" <?php echo $sel_class === (int)$cl['class_id'] ? 'selected' : ''; ?>><?php echo e($cl['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:6px;">
                            <label>Section</label>
                            <select name="section" id="txt_section" class="form-control inputheight">
                                <option value="0">All</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo $sec['section_id']; ?>" <?php echo $sel_section === (int)$sec['section_id'] ? 'selected' : ''; ?>><?php echo e($sec['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:6px;">
                            <label>Challan Month <span style="color:#DC2626;">*</span></label>
                            <input type="text" class="form-control inputheight" name="monthYear" placeholder="MM/YYYY" value="<?php echo e($sel_month); ?>" required>
                        </div>
                    </div>
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:6px;">
                            <label>Due Date <span style="color:#DC2626;">*</span></label>
                            <input type="date" class="form-control inputheight" name="duedate" id="duedate" value="<?php echo e($sel_duedate); ?>">
                        </div>
                    </div>
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:6px;">
                            <label>Fee Type</label>
                            <select name="fee_type" class="form-control inputheight">
                                <option value="Monthly" <?php echo $sel_fee_type === 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="Installment" <?php echo $sel_fee_type === 'Installment' ? 'selected' : ''; ?>>Installment</option>
                            </select>
                        </div>
                    </div>
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:6px;">
                            <label>Transport</label>
                            <select name="transport" class="form-control inputheight">
                                <option value="Yes" <?php echo $sel_transport === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                <option value="No" <?php echo $sel_transport === 'No' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                    </div>
                    <div class="fee-filter-field">
                        <div class="form-group" style="margin-bottom:6px;">
                            <label>&nbsp;</label>
                            <input type="button" value="+ Fee" class="btn btn-success inputheight" id="other_btn" onclick="ShowFeeParameters();">
                        </div>
                    </div>
                    <div class="fee-filter-btn-col">
                        <div class="form-group" style="margin-bottom:6px;">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary inputheight"><i class="fa fa-search"></i> Search</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <form id="createChallanForm" method="post" action="monthly_challan.php" onsubmit="return validateChallanSelection();">
            <input type="hidden" name="action" value="Create_Monthly_Challan_new">
            <input type="hidden" name="monthYear" value="<?php echo e($sel_month); ?>">
            <input type="hidden" name="duedate" id="duedate_hidden" value="<?php echo e($sel_duedate); ?>">

            <div id="additional_fees_container" class="additional-fees-block container-fluid" style="padding:14px 18px;">
                <div class="row additional-fee-row" data-month="<?php echo e($sel_month); ?>">
                    <div class="col-md-12" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:8px;">
                        <div style="flex:1 1 140px;">
                            <label style="font-size:12px;font-weight:700;">Fee Month (MM/YYYY)</label>
                            <input type="text" name="additional_fee[0][month]" value="<?php echo e($sel_month); ?>" class="form-control" placeholder="MM/YYYY">
                        </div>
                        <div style="flex:1 1 140px;">
                            <label style="font-size:12px;font-weight:700;">Admission Fee</label>
                            <input type="number" min="0" step="0.01" name="additional_fee[0][fees][number1]" value="" class="form-control afee-input" placeholder="Enter Fee">
                        </div>
                        <div style="flex:1 1 140px;">
                            <label style="font-size:12px;font-weight:700;">Other Charges</label>
                            <input type="number" min="0" step="0.01" name="additional_fee[0][fees][number2]" value="" class="form-control afee-input" placeholder="Enter Fee">
                        </div>
                        <div style="flex:1 1 140px;">
                            <label style="font-size:12px;font-weight:700;">Annual Fee</label>
                            <input type="number" min="0" step="0.01" name="additional_fee[0][fees][number3]" value="" class="form-control afee-input" placeholder="Enter Fee">
                        </div>
                        <div style="flex:1 1 140px;">
                            <label style="font-size:12px;font-weight:700;">Exam Fee</label>
                            <input type="number" min="0" step="0.01" name="additional_fee[0][fees][number5]" value="" class="form-control afee-input" placeholder="Enter Fee">
                        </div>
                        <div style="flex:1 1 140px;">
                            <label style="font-size:12px;font-weight:700;">Reg. Fee</label>
                            <input type="number" min="0" step="0.01" name="additional_fee[0][fees][number6]" value="" class="form-control afee-input" placeholder="Enter Fee">
                        </div>
                        <div style="flex:1 1 140px;">
                            <label style="font-size:12px;font-weight:700;">BookPack</label>
                            <input type="number" min="0" step="0.01" name="additional_fee[0][fees][number9]" value="" class="form-control afee-input" placeholder="Enter Fee">
                        </div>
                        <div>
                            <label>&nbsp;</label><br>
                            <button type="button" class="btn btn-default remove-fee-row" onclick="removeFeeRow(this)"><i class="fa fa-times"></i></button>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12" style="padding-top:6px;">
                        <button type="button" class="btn btn-sm btn-info" onclick="addFeeRow()"><i class="fa fa-plus"></i> Add More Fee Month</button>
                    </div>
                </div>
            </div>

            <div class="cfc-action-bar">
                <div class="cfc-action-info"><i class="fa fa-users"></i> Student Selection</div>
                <div class="cfc-action-right" style="display:flex; align-items:center; gap:12px;">
                    <span class="cfc-selected-count" id="selectedCount">0 of 0 selected</span>
                    <button type="submit" class="btn btn-primary" style="color:#fff;"><i class="fa fa-file-text-o"></i> Create Challans</button>
                </div>
            </div>

            <?php if (!$show_students): ?>
                <div class="fee-empty-state">
                    <i class="fa fa-users"></i>
                    <p style="margin:0 0 6px 0; font-size:15px; font-weight:700; color:#374151;">No students found.</p>
                    <span>Please select a class (and session) from the advance search above to load students.</span>
                </div>
            <?php else: ?>
            <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
                <table class="table table-hover table-bordered" id="listofstudents" style="width:100%; background:#fff; margin-bottom:0; font-size:13px;">
                    <thead>
                        <tr style="background:#F9FAFB;">
                            <th style="width:30px; text-align:center;"><input type="checkbox" id="selectAllStudents" checked onclick="toggleAllStudents(this)"></th>
                            <th>S.No</th>
                            <th>Student / Father Name</th>
                            <th>GR.No</th>
                            <th>Course/Class</th>
                            <th>Section</th>
                            <th>Monthly Fee</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($students as $s): ?>
                            <tr>
                                <td style="text-align:center;"><input type="checkbox" class="student-select" name="std_ids[]" value="<?php echo $s['student_id']; ?>" checked onclick="syncSelectAllState();"></td>
                                <td><?php echo $i++; ?></td>
                                <td>
                                    <strong><?php echo e($s['first_name'] . ' ' . $s['last_name']); ?></strong><br>
                                    <small style="color:#6B7280;"><?php echo e($s['father_name'] ?? ''); ?><?php echo $s['gr_no'] ? ' &mdash; GR# ' . e($s['gr_no']) : ''; ?></small>
                                </td>
                                <td><?php echo e($s['gr_no'] ?? '-'); ?></td>
                                <td><?php echo e($s['class_name'] ?? '-'); ?></td>
                                <td><?php echo e($s['section_name'] ?? '-'); ?></td>
                                <td style="font-weight:700; text-align:right;"><?php echo get_setting('currency_symbol', 'Rs.') . number_format($class_fee_total, 2); ?></td>
                                <td style="font-weight:800; text-align:right; color:#F59E0B;"><?php echo get_setting('currency_symbol', 'Rs.') . number_format($class_fee_total, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
function ShowFeeParameters(){
    var el = document.getElementById('additional_fees_container');
    var btn = document.getElementById('other_btn');
    if (el.style.display === 'block') {
        el.style.display = 'none';
        btn.value = '+ Fee';
        btn.classList.remove('btn-danger');
        btn.classList.add('btn-success');
    } else {
        el.style.display = 'block';
        btn.value = '- Fee';
        btn.classList.remove('btn-success');
        btn.classList.add('btn-danger');
    }
}
var feeRowIndex = 1;
function addFeeRow(){
    var container = document.getElementById('additional_fees_container');
    var first = container.querySelector('.additional-fee-row');
    var clone = first.cloneNode(true);
    var idx = feeRowIndex++;
    var monthVal = first.querySelector('input[name$="[month]"]').value;
    clone.dataset.month = monthVal;
    clone.querySelectorAll('input').forEach(function(input){
        var name = input.getAttribute('name');
        name = name.replace(/additional_fee\[\d+\]/, 'additional_fee[' + idx + ']');
        input.setAttribute('name', name);
        if (input.classList.contains('afee-input')) input.value = '';
    });
    var monthInput = clone.querySelector('input[name$="[month]"]');
    if (monthInput) monthInput.value = monthVal;
    container.insertBefore(clone, container.querySelector('.more-fee-row-holder') || container.lastElementChild);
    container.querySelector('.row').insertBefore(clone, document.querySelector('.more-fee-wrap') || null);
}
function removeFeeRow(btn){
    var rows = document.querySelectorAll('.additional-fee-row');
    if (rows.length <= 1) { alert('At least one fee row is required.'); return; }
    btn.closest('.additional-fee-row').remove();
}
function toggleAllStudents(source){
    document.querySelectorAll('.student-select').forEach(function(cb){ cb.checked = source.checked; });
    updateSelectedCount();
}
function syncSelectAllState(){
    var boxes = document.querySelectorAll('.student-select');
    var allChecked = boxes.length > 0 && Array.prototype.every.call(boxes, function(cb){ return cb.checked; });
    document.getElementById('selectAllStudents').checked = allChecked;
    updateSelectedCount();
}
function updateSelectedCount(){
    var el = document.getElementById('selectedCount');
    if (!el) return;
    var checkedCount = document.querySelectorAll('.student-select:checked').length;
    var total = document.querySelectorAll('.student-select').length;
    el.textContent = checkedCount + ' of ' + total + ' selected';
}
function validateChallanSelection(){
    var checkedCount = document.querySelectorAll('.student-select:checked').length;
    if (checkedCount === 0) { alert('Please select at least one student to create a challan.'); return false; }
    return true;
}

function parseMmyyyy(val){
    var m = /^(\d{1,2})\s*\/\s*(\d{4})$/.exec(val || '');
    return m ? { m: m[1], y: m[2] } : null;
}
function setDueDateFromMonth(){
    var mm = parseMmyyyy(document.getElementById('monthYear') ? document.getElementById('monthYear').value : '');
    var dd = document.getElementById('duedate');
    if (!dd) return;
    if (mm && mm.m >= 1 && mm.m <= 12) {
        var d = new Date(parseInt(mm.y, 10), parseInt(mm.m, 10) - 1, 10);
        var yyyy = d.getFullYear();
        var mo = String(d.getMonth() + 1).padStart(2, '0');
        var dy = String(d.getDate()).padStart(2, '0');
        dd.value = yyyy + '-' + mo + '-' + dy;
    } else {
        var today = new Date();
        dd.value = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-10';
    }
}
function syncDueDateHidden(){
    var dd = document.getElementById('duedate');
    var h = document.getElementById('duedate_hidden');
    if (dd && h) h.value = dd.value;
}
(function(){
    var monthInput = document.getElementById('monthYear');
    if (monthInput) {
        monthInput.addEventListener('change', function(){ setDueDateFromMonth(); syncDueDateHidden(); });
        monthInput.addEventListener('keyup', function(){ setDueDateFromMonth(); syncDueDateHidden(); });
    }
    if (!document.getElementById('duedate').value) {
        setDueDateFromMonth();
    }
    syncDueDateHidden();

    var dtEl = document.getElementById('listofstudents');
    if (dtEl && window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
        try {
            var dt = jQuery('#listofstudents').DataTable({
                pageLength: 100,
                order: [],
                columnDefs: [ { targets: 0, orderable: false, searchable: false } ],
                autoWidth: false
            });
            dt.on('draw search.dt', function(){ updateSelectedCount(); updateSelectAllOnDraw(); });
        } catch(e) {}
    }
})();
function updateSelectAllOnDraw(){
    var boxes = document.querySelectorAll('.student-select');
    if (boxes.length === 0) return;
    var allChecked = Array.prototype.every.call(boxes, function(cb){ return cb.checked; });
    var sa = document.getElementById('selectAllStudents');
    if (sa) sa.checked = allChecked;
}
document.addEventListener('DOMContentLoaded', updateSelectedCount);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>