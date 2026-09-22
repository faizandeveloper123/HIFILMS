<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Add Revenue';

// Revenues schema migration (idempotent)
db_query("ALTER TABLE revenues ADD COLUMN IF NOT EXISTS student_id INT NULL AFTER revenue_id");
db_query("ALTER TABLE revenues ADD COLUMN IF NOT EXISTS paid_by VARCHAR(50) DEFAULT 'Cash' AFTER amount");
db_query("ALTER TABLE revenues ADD COLUMN IF NOT EXISTS remarks VARCHAR(255) DEFAULT NULL AFTER description");
db_query("ALTER TABLE revenues ADD COLUMN IF NOT EXISTS paid_date DATE DEFAULT NULL AFTER remarks");

$message = '';
$error = '';

$heads = [];
$res = db_query("SELECT * FROM revenue_heads ORDER BY head_name");
while ($row = $res->fetch_assoc()) { $heads[] = $row; }

$students = [];
$res = db_query("SELECT s.student_id, s.first_name, s.father_name, s.phone, s.gr_no, c.class_name, st.section_name
                 FROM students s
                 LEFT JOIN classes c ON s.class_id = c.class_id
                 LEFT JOIN sections st ON s.section_id = st.section_id
                 ORDER BY s.first_name");
while ($row = $res->fetch_assoc()) { $students[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'AddRevenue') {
    $studentRaw = trim($_POST['student'] ?? '');
    $studentId = $studentRaw !== '' ? (int)$studentRaw : null;
    $paidBy = trim($_POST['paidBy'] ?? 'Cash');
    $paidDate = trim($_POST['paidDate'] ?? date('Y-m-d'));

    $headIds = $_POST['revenue_head'] ?? [];
    $amounts = $_POST['amount'] ?? [];
    $remarks = $_POST['remarks'] ?? [];

    $inserted = 0;
    foreach ($headIds as $idx => $headId) {
        $hid = (int)$headId;
        $amt = isset($amounts[$idx]) ? (float)$amounts[$idx] : 0;
        if ($hid <= 0 || $amt <= 0) { continue; }
        $rem = isset($remarks[$idx]) ? trim($remarks[$idx]) : '';
        $st2 = db_prepare("INSERT INTO revenues (student_id, head_id, description, amount, paid_by, remarks, paid_date, revenue_date, created_by)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $st2->bind_param('iisdssssi', $studentId, $hid, $rem, $amt, $paidBy, $rem, $paidDate, $paidDate, $_SESSION['user_id']);
        $st2->execute();
        $inserted++;
    }

    if ($inserted > 0) {
        $message = $inserted . ' revenue record(s) added successfully!';
    } else {
        $error = 'No valid revenue rows to save. Please select a Revenue Head and enter amount.';
    }
}

include __DIR__ . '/includes/header.php';
?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.page-head-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:14px 4px; }
.page-head-row h2 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.breadcrumb-modern { display:flex; align-items:center; gap:8px; font-size:12.5px; color:#6B7280; margin:6px 0 0; padding:0; list-style:none; }
.breadcrumb-modern a { color:#377DFF; text-decoration:none; }
.breadcrumb-modern i { font-size:11px; color:#9CA3AF; }
.page-actions { margin-bottom:16px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="page-head-row">
            <div>
                <h2><i class="fa fa-plus-circle"></i> Add Revenue</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb-modern">
                        <li><a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a></li>
                        <li><i class="fa fa-angle-right"></i></li>
                        <li><span>Add Revenue Parameters</span></li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="page-actions">
            <a href="<?php echo BASE_URL; ?>revenue_heads.php" class="btn btn-warning" style="color:#fff;"><i class="fa fa-tags"></i> Revenue Heads</a>
            <a href="<?php echo BASE_URL; ?>revenue_list.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-list"></i> List Revenues</a>
        </div>

        <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
            <form id="revenueForm" class="" method="post" action="add_revenue.php">
                <input type="hidden" name="action" value="AddRevenue">
                <input type="hidden" name="student" value="" id="hiddenStudent">
                <input type="hidden" name="class_id" value="" id="hiddenClass">

                <h3 style="font-size:16px; font-weight:800; color:#111827; margin:0 0 6px;">Add Income / Revenue</h3>

                <div class="row">
                    <div class="col-md-2 col-xs-6" style="padding:8px;">
                        <div class="form-group">
                            <label class="required">Choose option</label>
                            <select name="stdOther" id="stdOther" class="form-control" required>
                                <option value="">Select</option>
                                <option value="1">Student</option>
                                <option value="0">Other's</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-xs-6" id="studentName" style="padding:8px; margin-top:24px; display:none;">
                        <select name="student_search" id="student" class="form-control" data-placeholder="Select Student">
                            <option value="">Select Student</option>
                            <?php foreach ($students as $s): ?>
                                <option value="<?php echo $s['student_id']; ?>" data-class="<?php echo $s['class_id'] ?? ''; ?>">
                                    <?php echo e(trim(($s['first_name'] ?? '') . ' ' . ($s['father_name'] ?? ''))); ?> | <?php echo e($s['gr_no'] ?? ''); ?> | <?php echo e($s['class_name'] ?? ''); ?><?php echo $s['section_name'] ? ' - ' . e($s['section_name']) : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-xs-6" style="padding:8px;">
                        <div class="form-group">
                            <label class="required">Paid By</label>
                            <select name="paidBy" id="paidBy" class="form-control" required>
                                <option value="">Select</option>
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-xs-6" style="padding:8px;">
                        <div class="form-group">
                            <label class="required">Paid Date</label>
                            <input name="paidDate" id="paidDate" value="<?php echo date('Y-m-d'); ?>" type="date" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-2 col-xs-6" style="padding:8px; margin-top:24px;">
                        <input class="add-row btn btn-success" name="pronam" type="button" value="Add Row" style="width:100%; color:#fff;">
                    </div>
                </div>

                <table id="mytable" class="table table-striped table-bordered" style="width:100%; background-color:#fff;">
                    <thead>
                        <tr>
                            <th width="33%"><i class="fa fa-tasks"></i> Revenue Head</th>
                            <th width="22%"><i class="fa fa-dollar"></i> Amount</th>
                            <th width="45%"><i class="fa fa-comment"></i> Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <select name="revenue_head[]" class="form-control" required>
                                    <option value="">Select</option>
                                    <?php foreach ($heads as $h): ?>
                                        <option value="<?php echo $h['head_id']; ?>"><?php echo e($h['head_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="form-control" name="amount[]" type="number" min="0" step="0.01" required></td>
                            <td><input class="form-control" name="remarks[]" type="text"></td>
                        </tr>
                    </tbody>
                </table>

                <input type="submit" style="font-size:16px; float:right;" value="Submit" class="btn btn-success">
                <div class="clearfix"></div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
function HideDate(val) {
    var div = document.getElementById('studentName');
    if (parseInt(val) === 1) { div.style.display = 'block'; } else { div.style.display = 'none'; document.getElementById('hiddenStudent').value = ''; document.getElementById('hiddenClass').value = ''; }
}

$(document).ready(function(){
    $('#stdOther').on('change', function(){ HideDate(this.value); });

    $('#student').select2({ width:'100%', placeholder:'Select Student', allowClear:true });

    $('#student').on('select2:select change', function(){
        var opt = $(this).find('option:selected');
        $('#hiddenStudent').val($(this).val());
        $('#hiddenClass').val(opt.data('class') || '');
    });
    $('#student').on('select2:unselect', function(){
        $('#hiddenStudent').val('');
        $('#hiddenClass').val('');
    });

    $('.add-row').click(function(){
        var tpl = $('#mytable tbody tr:first').clone(false);
        tpl.find('select').val('');
        tpl.find('input').val('');
        $('#mytable tbody').append(tpl);
    });

    $('#revenueForm').on('submit', function(){
        var stdOther = $('#stdOther').val();
        if (stdOther === '') { alert('Please choose Student or Other is.'); return false; }
        if (stdOther === '1' && $('#hiddenStudent').val() === '') { alert('Please select a student.'); return false; }
        if ($('#paidBy').val() === '') { alert('Please select Paid By.'); return false; }
        var validRow = false;
        $('#mytable tbody tr').each(function(){
            if ($(this).find('select').val() !== '' && parseFloat($(this).find('input[name="amount[]"]').val() || 0) > 0) validRow = true;
        });
        if (!validRow) { alert('Please fill at least one Revenue Head row with amount.'); return false; }
        return true;
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>