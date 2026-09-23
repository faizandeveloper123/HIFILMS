<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Staff Cards';

// ---- Staff filters ----
$selDesignation = $_GET['designation'] ?? '';
$selDept        = $_GET['department'] ?? '';
if ($selDesignation === 'All') { $selDesignation = ''; }
if ($selDept === 'All') { $selDept = ''; }

// ---- Staff data ----
$allStaff = [];
$res = db_query("SELECT emp_id, first_name, last_name, designation, department, phone FROM employees WHERE status=1 ORDER BY emp_id");
while ($row = $res->fetch_assoc()) { $allStaff[] = $row; }

$staff = [];
foreach ($allStaff as $s) {
    if ($selDesignation !== '' && strcasecmp(trim((string)($s['designation'] ?? '')), $selDesignation) !== 0) { continue; }
    if ($selDept !== '' && strcasecmp(trim((string)($s['department'] ?? '')), $selDept) !== 0) { continue; }
    $staff[] = $s;
}

$designations = [];
$departments  = [];
foreach ($allStaff as $s) {
    $d = trim((string)($s['designation'] ?? ''));
    if ($d !== '') { $designations[$d] = true; }
    $dp = trim((string)($s['department'] ?? ''));
    if ($dp !== '') { $departments[$dp] = true; }
}
ksort($designations);
ksort($departments);

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
    .table { width:100%; border-collapse:collapse; background:#fff; }
    .table th { background:#000; color:#fff; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.3px; padding:9px 10px; border:1px solid #000; }
    .table td { border:1px solid #e5e7eb; padding:8px 10px; font-size:13px; color:#111827; }
    .table tbody tr { cursor:pointer; }
    .table tbody tr:hover td { background:#fff7ed; }
    .check-col { width:40px; text-align:center; }
    .check-col input { width:16px; height:16px; cursor:pointer; }
    .print-btn { margin-top:10px; }
    @media (max-width:600px){ .card-filters .form-group { min-width:100%; } }
</style>

<script>
function collectChecked(name) {
    var vals = [];
    document.querySelectorAll('input[name="' + name + '"]:checked').forEach(function (cb) { vals.push(cb.value); });
    return vals;
}
function printSelectedStaff() {
    var ids = collectChecked('staff_ids_checkbox[]');
    if (ids.length === 0) { alert('Please choose any staff from checkboxes...'); return false; }
    document.getElementById('selected_staff_ids').value = ids.join(',');
    document.getElementById('staffCardForm').submit();
}
function toggleAllStaff(chk) {
    document.querySelectorAll('input[name="staff_ids_checkbox[]"]').forEach(function (cb) { cb.checked = chk.checked; });
}
function applyStaffFilter() {
    var d = document.getElementById('designationFilter').value;
    var dp = document.getElementById('departmentFilter').value;
    window.location = '<?php echo BASE_URL; ?>cards.php?designation=' + encodeURIComponent(d) + '&department=' + encodeURIComponent(dp);
}
function openStaffCard(id) {
    var url = '<?php echo BASE_URL; ?>print_staff_cards.php?staff_ids=' + id;
    window.open(url, '_blank');
}
</script>

<div class="main-content">
    <div class="container-fluid">

        <div class="cards-head">
            <h3><i class="fa fa-id-card"></i> Staff Cards</h3>
            <div class="cards-actions">
                <a href="<?php echo BASE_URL; ?>students_card.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-graduation-cap"></i> Students Cards</a>
            </div>
        </div>

        <div class="card-panel">
            <p>Choose staff members to generate their ID cards. Click on a row to view that staff member's card.</p>

            <div class="card-filters">
                <div class="form-group">
                    <label>Designation</label>
                    <select id="designationFilter" class="form-control" onchange="applyStaffFilter()">
                        <option value="All">All</option>
                        <?php foreach (array_keys($designations) as $desg): ?>
                            <option value="<?php echo e($desg); ?>" <?php echo $selDesignation === $desg ? 'selected' : ''; ?>><?php echo e($desg); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select id="departmentFilter" class="form-control" onchange="applyStaffFilter()">
                        <option value="All">All</option>
                        <?php foreach (array_keys($departments) as $depv): ?>
                            <option value="<?php echo e($depv); ?>" <?php echo $selDept === $depv ? 'selected' : ''; ?>><?php echo e($depv); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <form id="staffCardForm" action="<?php echo BASE_URL; ?>print_staff_cards.php" method="post" target="_blank">
                <input type="hidden" name="staff_ids" id="selected_staff_ids" value="">
                <div style="overflow-x:auto;">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th class="check-col"><input type="checkbox" onclick="toggleAllStaff(this)"></th>
                                <th>Staff Name</th>
                                <th>Designation</th>
                                <th>Department</th>
                                <th>Contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($staff) === 0): ?>
                                <tr><td colspan="5" style="text-align:center; color:#6B7280;">No staff found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($staff as $s):
                                $sname = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
                            ?>
                                <tr onclick="openStaffCard(<?php echo (int)$s['emp_id']; ?>)">
                                    <td class="check-col" onclick="event.stopPropagation();"><input type="checkbox" name="staff_ids_checkbox[]" value="<?php echo (int)$s['emp_id']; ?>"></td>
                                    <td><?php echo e($sname); ?></td>
                                    <td><?php echo e($s['designation'] ?? ''); ?></td>
                                    <td><?php echo e($s['department'] ?? ''); ?></td>
                                    <td><?php echo e($s['phone'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="display:flex; justify-content:flex-end; margin-top:10px;">
                    <button type="button" class="btn btn-primary print-btn" style="color:#fff; margin-top:0;" onclick="printSelectedStaff()"><i class="fa fa-print"></i> Print Selected Staff Cards</button>
                </div>
            </form>
        </div>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>