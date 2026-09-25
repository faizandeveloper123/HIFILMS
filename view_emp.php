<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$__migrate = [
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS qualification VARCHAR(191) DEFAULT NULL",
    "CREATE TABLE IF NOT EXISTS user_module_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        module VARCHAR(50) NOT NULL,
        page VARCHAR(50) NOT NULL,
        permission VARCHAR(20) DEFAULT NULL,
        allowed TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_access (user_id, module, page)
    )",
];
foreach ($__migrate as $__sql) { try { db_query($__sql); } catch (\Throwable $e) {} }
try {
    $__col = db_query("SHOW COLUMNS FROM user_module_access LIKE 'permission'");
    if ($__col && $__col->num_rows === 0) {
        db_query("ALTER TABLE user_module_access ADD COLUMN permission VARCHAR(20) DEFAULT NULL");
    }
} catch (\Throwable $e) {}

$page_title = 'View Employees';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'RemoveEmployee') {
    $eid = (int) ($_POST['emp_id'] ?? 0);
    $reason = trim($_POST['removal_reason'] ?? '');
    $rdate = trim($_POST['removal_date'] ?? '');
    if ($eid > 0) {
        $row = db_query("SELECT first_name, last_name, phone, email FROM employees WHERE emp_id=$eid")->fetch_assoc();
        $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $phone = trim($row['phone'] ?? '');
        $uid = (int) ($_SESSION['user_id'] ?? 0);

        db_query("CREATE TABLE IF NOT EXISTS employee_removals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            emp_id INT NOT NULL,
            name VARCHAR(191) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            reason VARCHAR(500) DEFAULT NULL,
            removal_date DATE DEFAULT NULL,
            removed_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $rdb = ($rdate !== '' ? $rdate : null);
        $st2 = db_prepare("INSERT INTO employee_removals (emp_id, name, phone, reason, removal_date, removed_by) VALUES (?, ?, ?, ?, ?, ?)");
        $st2->bind_param('issssi', $eid, $fullName, $phone, $reason, $rdb, $uid);
        $st2->execute();

        if (isset($_POST['send_sms']) && $phone !== '') {
            $body = 'Dear ' . $fullName . ', your services with our school have been concluded as of ' . ($rdate !== '' ? $rdate : date('Y-m-d')) . '. Reason: ' . ($reason !== '' ? $reason : 'Not specified') . '. Thanks for your services.';
            $title3 = 'Employee Removal - ' . $fullName;
            $st3 = db_prepare("INSERT INTO messages (title, message, recipient_type, channel, recipient_list, status, message_type, template_title, created_by) VALUES (?, ?, 'employee', 'sms', ?, 'queued', 'employee_removal', 'Employee Removal SMS', ?)");
            $st3->bind_param('sssi', $title3, $body, $phone, $uid);
            $st3->execute();
        }

        $st4 = db_prepare("DELETE FROM employees WHERE emp_id=?");
        $st4->bind_param('i', $eid);
        $st4->execute();
        $message = 'Employee removed successfully!';
        if ($reason !== '') { $message .= ' Reason recorded.'; }
        if (isset($_POST['send_sms']) && $phone !== '') { $message .= ' Notification SMS queued.'; }
    }
}

function access_clean($v) {
    return preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string)$v)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'GrantAccess') {
    $eid = (int) ($_POST['emp_id'] ?? 0);
    $emaill = strtolower(trim($_POST['access_email'] ?? ''));
    $pw = trim($_POST['access_password'] ?? '');
    if ($eid <= 0) {
        $error = 'Invalid employee.';
    } elseif ($emaill === '') {
        $error = 'Email is required to grant access.';
    } else {
        $row = db_query("SELECT first_name, last_name, phone FROM employees WHERE emp_id=$eid")->fetch_assoc();
        $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $updEmp = db_prepare("UPDATE employees SET email=? WHERE emp_id=?");
        $updEmp->bind_param('si', $emaill, $eid);
        $updEmp->execute();
        $existing = 0;
        $chk = db_prepare("SELECT user_id FROM users WHERE email=?");
        $chk->bind_param('s', $emaill);
        $chk->execute();
        if ($rr = $chk->get_result()->fetch_assoc()) { $existing = (int)$rr['user_id']; }
        if ($existing) {
            if ($pw !== '') {
                $up = db_prepare("UPDATE users SET full_name=?, password=?, status=1 WHERE user_id=?");
                $hash = hash('sha256', $pw);
                $up->bind_param('ssi', $fullName, $hash, $existing);
                $up->execute();
            } else {
                $up = db_prepare("UPDATE users SET full_name=?, status=1 WHERE user_id=?");
                $up->bind_param('si', $fullName, $existing);
                $up->execute();
            }
        } else {
            $hash = hash('sha256', $pw !== '' ? $pw : 'staff123');
            $ins = db_prepare("INSERT INTO users (email, password, full_name, role, status) VALUES (?, ?, ?, 'staff', 1)");
            $ins->bind_param('sss', $emaill, $hash, $fullName);
            $ins->execute();
        }
        $message = 'Portal access granted to ' . $fullName . ' (' . $emaill . ').';
        if ($pw === '') { $message .= ' Default password: staff123'; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ImportStaffCSV') {
    if (!empty($_FILES['csv_file']['tmp_name'])) {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if ($handle) {
            $headers = fgetcsv($handle);
            $map = [];
            foreach ($headers as $i => $h) { $map[$i] = strtolower(str_replace([' ', '-'], '_', trim((string)$h))); }
            $colMap = [
                'first_name'=>'first_name', 'fname'=>'first_name', 'employee_name'=>'first_name', 'name'=>'first_name', 'employee' => 'first_name',
                'last_name'=>'last_name', 'father_name'=>'father_name', 'father'=>'father_name', 'father/husband_name'=>'father_name',
                'email'=>'email', 'phone'=>'phone', 'cell'=>'phone', 'cell_no'=>'phone', 'cell_number'=>'phone', 'contact'=>'phone',
                'designation'=>'designation', 'department'=>'department', 'dept'=>'department', 'qualification'=>'qualification',
                'salary'=>'salary', 'basic_salary'=>'salary', 'address'=>'address', 'cnic'=>'cnic', 'gender'=>'gender',
                'date_of_birth'=>'dob', 'dob'=>'dob', 'joining_date'=>'joining_date', 'date_of_joining'=>'joining_date',
            ];
            $cols = ['first_name','last_name','father_name','email','phone','designation','department','qualification','salary','address','cnic','gender','dob','joining_date'];
            $imported = 0; $skipped = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $data = [];
                foreach ($cols as $c) {
                    $data[$c] = '';
                    foreach ($map as $idx => $h) {
                        if ($h !== '' && ($colMap[$h] ?? '') === $c && isset($row[$idx])) { $data[$c] = trim((string)$row[$idx]); break; }
                    }
                }
                if ($data['first_name'] === '') { $skipped++; continue; }
                $fname = $data['first_name']; $lname = $data['last_name']; $father = $data['father_name'];
                $email = $data['email']; $phone = $data['phone']; $desig = $data['designation'];
                $dept = $data['department']; $qual = $data['qualification']; $sal = (float)$data['salary'];
                $addr = $data['address']; $cnic = $data['cnic']; $gen = $data['gender'];
                $dobd = $data['dob'] ?: null; $joind = $data['joining_date'] ?: null;
                $ins = db_prepare("INSERT INTO employees (first_name, last_name, father_name, email, phone, designation, department, qualification, salary, address, cnic, gender, dob, joining_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $ins->bind_param('sssssssssdssss', $fname, $lname, $father, $email, $phone, $desig, $dept, $qual, $sal, $addr, $cnic, $gen, $dobd, $joind);
                $ins->execute();
                $imported++;
            }
            fclose($handle);
            $message = "$imported staff imported, $skipped rows skipped (missing employee name).";
        } else {
            $error = 'Could not read the CSV file.';
        }
    } else {
        $error = 'Please choose a CSV file to import.';
    }
}

$q = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 20;

$where = "WHERE e.status IN (0,1)";
$params = [];
$types = '';
if ($q !== '') {
    $where .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.father_name LIKE ? OR e.designation LIKE ? OR e.department LIKE ? OR e.qualification LIKE ? OR e.email LIKE ? OR e.phone LIKE ?)";
    $like = "%$q%";
    $params = array_fill(0, 8, $like);
    $types = 'ssssssss';
}

$cntSql = "SELECT COUNT(*) c FROM employees e $where";
$total = 0;
if (count($params) > 0) { $st2 = db_prepare($cntSql); $st2->bind_param($types, ...$params); $st2->execute(); $total = (int)$st2->get_result()->fetch_assoc()['c']; }
else { $total = (int)db_query($cntSql)->fetch_assoc()['c']; }

$pages = max(1, (int)ceil($total / $per));
$offset = ($page - 1) * $per;

$sql = "SELECT e.*, u.user_id AS portal_user,
        (SELECT COUNT(*) FROM employee_attendance a WHERE a.emp_id=e.emp_id AND a.status='present') present_days
        FROM employees e LEFT JOIN users u ON u.email = e.email AND u.status=1
        $where ORDER BY e.first_name LIMIT ? OFFSET ?";
$params[] = $per;
$params[] = $offset;
$types .= 'ii';

$emps = [];
$st2 = db_prepare($sql);
$st2->bind_param($types, ...$params);
$st2->execute();
$res = $st2->get_result();
while ($row = $res->fetch_assoc()) { $emps[] = $row; }

function emp_class_name($emp, $type) {
    $raw = trim((string) ($emp[$type] ?? ''));
    if ($raw === '') { return ''; }
    if ($type === 'incharge_section' && !ctype_digit($raw)) { return $raw; }
    $id = (int) $raw;
    $table = $type === 'incharge_class' ? 'classes' : 'sections';
    $col = $type === 'incharge_class' ? 'class_name' : 'section_name';
    $r = db_query("SELECT $col AS n FROM $table WHERE " . ($type === 'incharge_class' ? 'class_id' : 'section_id') . "=$id")->fetch_assoc();
    return $r ? $r['n'] : '';
}

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.avatar { width:36px; height:36px; border-radius:999px; background:linear-gradient(135deg,#FF7A1B,#ffa35c); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-weight:800; }
.avatar-img { width:36px; height:36px; border-radius:999px; object-fit:cover; }
.status-badge { padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; display:inline-block; }
.status-paid,.status-present,.status-active { background:#DCFCE7; color:#16A34A; }
.status-pending,.status-unpaid { background:#FEF3C7; color:#D97706; }
.status-absent,.status-inactive { background:#FEE2E2; color:#DC2626; }
.toolbar-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 16px; border-radius:10px; font-size:13px; font-weight:600; text-decoration:none; border:1px solid transparent; }
.profile-photo { width:90px; height:90px; border-radius:999px; object-fit:cover; border:3px solid #FF7A1B; }
.profile-kv b { display:block; font-size:12px; color:#6B7280; font-weight:600; text-transform:uppercase; }
.profile-kv span { font-size:14px; color:#111827; font-weight:600; }
.ad-menu { display:none; position:static; z-index:2050; min-width:240px; max-width:300px; background:#fff; border:1px solid #E5E7EB; border-radius:10px; box-shadow:0 10px 30px rgba(15,23,42,.18); padding:6px; margin-top:6px; }
.ad-menu a { display:flex; align-items:center; gap:9px; padding:9px 12px; border-radius:7px; font-size:13px; font-weight:600; color:#111827; text-decoration:none; }
.ad-menu a i { width:16px; text-align:center; color:#FF7A1B; }
.ad-menu a:hover { background:#FFF7ED; color:#C2410C; }
.ad-menu a.danger { color:#DC2626; }
.ad-menu a.danger i { color:#DC2626; }
.ad-menu a.danger:hover { background:#FEF2F2; }
@media print { .no-print { display:none !important; } }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-users"></i> View Employees <span style="font-size:14px; color:#6B7280;">(<?php echo $total; ?> employees)</span></h3>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" class="toolbar-btn" style="background:#2563EB; color:#fff;" data-toggle="modal" data-target="#importCsvModal"><i class="fa fa-download"></i> Import Staff Data (CSV)</button>
                <a href="#" class="toolbar-btn" style="background:#E0E7FF; color:#4338CA;" onclick="alert('CV Bank - upload documents against employees (coming soon).'); return false;"><i class="fa fa-folder-open"></i> CV Bank</a>
                <a href="#" class="toolbar-btn" style="background:#16A34A; color:#fff;" onclick="window.print(); return false;"><i class="fa fa-print"></i> Print Employee List</a>
            </div>
        </div>

        <form method="get" action="view_emp.php" class="search-bar-student">
            <div class="form-group col-md-4" style="margin-bottom:0;">
                <input type="text" name="q" class="form-control" placeholder="Search by name, father, designation, department, qualification..." value="<?php echo e($q); ?>">
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa fa-search"></i> Search</button>
            </div>
        </form>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead>
                    <tr><th>S.No</th><th>Employee Name</th><th>Designation</th><th>Contact</th><th>Qualification</th><th>Access</th><th>Attendance</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (count($emps) === 0): ?>
                        <tr><td colspan="8" style="text-align:center; color:#6B7280; padding:30px;">No employees found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($emps as $i => $em):
                        $fullName = trim(($em['first_name'] ?? '') . ' ' . ($em['last_name'] ?? ''));
                        $photo = ($em['photo'] ?? '') !== '' ? BASE_URL . 'uploads/employees/' . rawurlencode($em['photo']) : '';
                        $profile = [
                            'name' => $fullName,
                            'father' => $em['father_name'] ?? '',
                            'cnic' => $em['cnic'] ?? '',
                            'gender' => $em['gender'] ?? '',
                            'religion' => $em['religion'] ?? '',
                            'blood' => $em['blood_group'] ?? '',
                            'marital' => $em['marital_status'] ?? '',
                            'qual' => $em['qualification'] ?? '',
                            'dob' => $em['dob'] ?? '',
                            'joining' => $em['joining_date'] ?? '',
                            'dept' => $em['department'] ?? '',
                            'desig' => $em['designation'] ?? '',
                            'jobtype' => $em['job_type'] ?? '',
                            'contract' => $em['contract_end'] ?? '',
                            'class_head' => $em['class_head'] ?? '',
                            'incharge_class' => emp_class_name($em, 'incharge_class'),
                            'incharge_section' => emp_class_name($em, 'incharge_section'),
                            'reg_no' => $em['reg_no'] ?? '',
                            'basic' => (string)($em['salary'] ?? 0),
                            'trav' => (string)($em['allowance_traveling'] ?? 0),
                            'reimb' => (string)($em['allowance_reimbursement'] ?? 0),
                            'others' => (string)($em['allowance_others'] ?? 0),
                            'email' => $em['email'] ?? '',
                            'phone' => $em['phone'] ?? '',
                            'home' => $em['home_phone'] ?? '',
                            'postal' => $em['postal_code'] ?? '',
                            'address' => $em['address'] ?? '',
                            'bank_title' => $em['bank_title'] ?? '',
                            'bank' => $em['bank_name'] ?? '',
                            'acct' => $em['bank_account'] ?? '',
                            'photo' => $photo,
                            'status' => $em['status'] ? 'Active' : 'Inactive',
                        ];
                    ?>
                        <tr id="emprow-<?php echo $em['emp_id']; ?>" data-profile="<?php echo e(json_encode($profile)); ?>">
                            <td><?php echo $offset + $i + 1; ?> <small style="color:#9CA3AF;">(#<?php echo $em['emp_id']; ?>)</small></td>
                            <td>
                                <?php if ($photo !== ''): ?>
                                    <img src="<?php echo e($photo); ?>" class="avatar-img" style="margin-right:8px; vertical-align:middle;" onerror="this.style.display='none';">
                                <?php else: ?>
                                    <span class="avatar" style="margin-right:8px;"><?php echo strtoupper(substr($em['first_name'], 0, 1)); ?></span>
                                <?php endif; ?>
                                <strong><?php echo e($fullName); ?></strong>
                            </td>
                            <td><?php echo e($em['designation'] ?? '-'); ?></td>
                            <td>
                                <?php echo e($em['phone'] ?? '-'); ?>
                                <?php if (!empty($em['email'])): ?><br><small style="color:#6B7280;"><?php echo e($em['email']); ?></small><?php endif; ?>
                            </td>
                            <td><?php echo e($em['qualification'] ?: '-'); ?></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>employee_access.php?emp_id=<?php echo $em['emp_id']; ?>" class="btn btn-success btn-sm"><i class="fa fa-key"></i> Access</a>
                            </td>
                            <td>
                                <span class="status-badge status-active"><?php echo (int)$em['present_days']; ?> days</span>
                                <a href="<?php echo BASE_URL; ?>view_emp_attendance.php" class="btn btn-default btn-xs" title="Mark Attendance"><i class="fa fa-calendar"></i></a>
                            </td>
                            <td nowrap style="position:relative;">
                                <button type="button" class="btn btn-primary btn-sm ad-toggle" id="adt_<?php echo $em['emp_id']; ?>" onclick="toggleActions(<?php echo $em['emp_id']; ?>)">
                                    <i class="fa fa-cog"></i> Actions <span class="caret"></span>
                                </button>
                                <div class="ad-menu" id="admenu_<?php echo $em['emp_id']; ?>">
                                    <a href="<?php echo BASE_URL; ?>emp_experience_certificate.php?emp_id=<?php echo $em['emp_id']; ?>" target="_blank"><i class="fa fa-certificate"></i> Generate Experience Certificate</a>
                                    <a href="<?php echo BASE_URL; ?>add_emp.php?emp_id=<?php echo $em['emp_id']; ?>"><i class="fa fa-pencil"></i> Edit Employee</a>
                                    <a href="javascript:void(0)" class="danger" data-id="<?php echo $em['emp_id']; ?>" data-name="<?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>" data-phone="<?php echo htmlspecialchars($em['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" onclick="openRemoveModal(this)"><i class="fa fa-trash"></i> Remove Employee</a>
                                    <a href="javascript:void(0)" onclick="printEmployee(<?php echo $em['emp_id']; ?>)"><i class="fa fa-print"></i> Print Employee Details</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
        <nav style="margin-top:14px; text-align:center;">
            <ul class="pagination" style="margin:0;">
                <li class="<?php echo $page <= 1 ? 'disabled' : ''; ?>"><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo;</a></li>
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <li class="<?php echo $p === $page ? 'active' : ''; ?>"><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"><?php echo $p; ?></a></li>
                <?php endfor; ?>
                <li class="<?php echo $page >= $pages ? 'disabled' : ''; ?>"><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">&raquo;</a></li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<div id="removeEmpModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:99990; padding:20px; overflow:auto;">
    <div style="max-width:480px; margin:8vh auto; background:#fff; border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,.35);">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #E5E7EB;">
            <h4 style="margin:0; font-size:16px; font-weight:800; color:#111827;"><i class="fa fa-trash" style="color:#DC2626;"></i> Remove Employee</h4>
            <button type="button" onclick="closeRemoveModal()" style="border:0; background:none; font-size:24px; line-height:1; cursor:pointer; color:#6B7280;">&times;</button>
        </div>
        <form method="post" action="view_emp.php" id="removeEmpForm" style="padding:20px;">
            <input type="hidden" name="action" value="RemoveEmployee">
            <input type="hidden" name="emp_id" id="remEmpId" value="">
            <p style="font-size:13px; color:#374151; margin:0 0 14px;">You are removing <strong id="remEmpName" style="color:#111827;"></strong>. This action is recorded in removal history.</p>
            <div class="form-group">
                <label style="font-weight:700; font-size:13px; color:#111827;">Reason for Removal</label>
                <select class="form-control" name="removal_reason" id="remReason" required onchange="toggleRemReason()">
                    <option value="" disabled selected>Select reason...</option>
                    <option value="Appointment in Government Sector">Appointment in Government Sector</option>
                    <option value="Migrate to other campus">Migrate to other campus</option>
                    <option value="Misbehaviour">Misbehaviour</option>
                    <option value="Went to Abroad">Went to Abroad</option>
                    <option value="Regular Absents">Regular Absents</option>
                    <option value="Due to marriage">Due to marriage</option>
                    <option value="Unsatisfaction">Unsatisfaction</option>
                    <option value="Wrong entry">Wrong entry</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group" id="remOtherRow" style="display:none;">
                <label style="font-weight:700; font-size:13px; color:#111827;">Describe Reason</label>
                <input type="text" class="form-control" name="removal_reason_other" id="remReasonOther" placeholder="Please specify the reason...">
            </div>
            <div class="form-group">
                <label style="font-weight:700; font-size:13px; color:#111827;">Removal Date</label>
                <input type="date" class="form-control" name="removal_date" id="remRemovalDate" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="checkbox" style="margin:6px 0 0;">
                <label style="font-size:13px; font-weight:600;">
                    <input type="checkbox" name="send_sms" id="remSendSms" value="1"> <i class="fa fa-comment" style="color:#16A34A;"></i> Send notification SMS to <strong id="remEmpPhone" style="color:#111827;"></strong>
                </label>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:22px;">
                <button type="button" class="btn btn-default" onclick="closeRemoveModal()">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Remove Employee</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="importCsvModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="post" action="view_emp.php" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-download"></i> Import Staff Data (CSV)</h4>
            </div>
            <div class="modal-body">
                <p>Upload a CSV file. The first row must be headers. Supported columns: <code>first_name</code>, <code>last_name</code> / <code>father_name</code>, <code>email</code>, <code>phone</code> / <code>cell</code>, <code>designation</code>, <code>department</code>, <code>qualification</code>, <code>salary</code>, <code>address</code>, <code>cnic</code>, <code>gender</code>, <code>dob</code>, <code>joining_date</code>.</p>
                <input type="file" name="csv_file" accept=".csv,text/csv" class="form-control" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success" name="action" value="ImportStaffCSV"><i class="fa fa-upload"></i> Import</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="empProfileModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-user"></i> <span id="profileTitle">Employee Profile</span></h4>
            </div>
            <div class="modal-body" id="profileBody" style="max-height:60vh; overflow:auto;"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" onclick="printProfile()"><i class="fa fa-print"></i> Print Details</button>
            </div>
        </div>
    </div>
</div>

<script>
var allProfiles = {};
document.querySelectorAll('tr[data-profile]').forEach(function(tr){
    allProfiles[tr.id.replace('emprow-', '')] = JSON.parse(tr.getAttribute('data-profile'));
});

function profileHtml(p) {
    var rows = [
        ['Father / Husband', p.father], ['CNIC', p.cnic], ['Gender', p.gender], ['Religion', p.religion],
        ['Blood Group', p.blood], ['Marital Status', p.marital], ['Qualification', p.qual],
        ['Date of Birth', p.dob], ['Date of Joining', p.joining], ['Department', p.dept], ['Designation', p.desig],
        ['Job Type', p.jobtype], ['Contract End', p.contract], ['Class Head', p.class_head],
        ['Incharge Course/Class', p.incharge_class], ['Incharge Section', p.incharge_section], ['Registration No', p.reg_no],
        ['Basic Salary', p.basic], ['Traveling Allow', p.trav], ['Reimbursement', p.reimb], ['Other Allow', p.others],
        ['Email', p.email], ['Cell No', p.phone], ['Home Contact', p.home], ['Postal Code', p.postal],
        ['Address', p.address], ['Account Title', p.bank_title], ['Bank Name', p.bank], ['Account No', p.acct]
    ];
    var kv = rows.map(function(r){
        return '<div class="col-md-4 profile-kv" style="margin-bottom:12px;"><b>' + r[0] + '</b><span>' + (r[1] || '-') + '</span></div>';
    }).join('');
    var photo = p.photo
        ? '<img src="' + p.photo + '" class="profile-photo" onerror="this.src=\'' + '<?php echo BASE_URL; ?>assets/img/logo.jpg' + '\';">'
        : '<div class="profile-photo" style="display:inline-flex; align-items:center; justify-content:center; background:#F3F4F6; color:#9CA3AF;">No<br>Photo</div>';
    return '<div style="text-align:center; margin-bottom:18px;">' + photo + '<h4 style="margin:10px 0 2px;">' + p.name + '</h4>' +
        '<span class="status-badge ' + (p.status === 'Active' ? 'status-active' : 'status-inactive') + '">' + p.status + '</span></div>' +
        '<div class="row">' + kv + '</div>';
}

window.showProfile = function(id) {
    var p = allProfiles[id];
    if (!p) return;
    document.getElementById('profileTitle').textContent = 'Employee Profile - ' + p.name;
    document.getElementById('profileBody').innerHTML = profileHtml(p);
    window.currentProfileId = id;
};

window.printEmployee = function(id) {
    var p = allProfiles[id];
    if (!p) return;
    var w = window.open('', '_blank');
    w.document.write('<html><head><title>Employee Details - ' + p.name.replace(/[<>&"]/g, '') + '</title>');
    w.document.write('<style>body{font-family:Segoe UI,Arial,sans-serif;padding:30px;} .head{text-align:center;margin-bottom:20px;} .head img{width:90px;height:90px;border-radius:999px;object-fit:cover;} h2{margin:6px 0 2px;} table{width:100%;border-collapse:collapse;} td{border:1px solid #ddd;padding:8px 10px;font-size:13px;} td.k{background:#f6f7f8;font-weight:700;width:32%;} .badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;}</style>');
    w.document.write('</head><body>');
    w.document.write('<div class="head">' + (p.photo ? '<img src="' + p.photo + '">' : '') + '<h2>' + p.name + '</h2><span>' + p.desig + ' | ' + p.dept + '</span></div>');
    var pairs = [
        ['Father / Husband', p.father], ['CNIC', p.cnic], ['Gender', p.gender], ['Religion', p.religion],
        ['Blood Group', p.blood], ['Marital Status', p.marital], ['Qualification', p.qual],
        ['Date of Birth', p.dob], ['Date of Joining', p.joining], ['Department', p.dept], ['Designation', p.desig],
        ['Job Type', p.jobtype], ['Contract End', p.contract], ['Class Head', p.class_head],
        ['Incharge Course/Class', p.incharge_class], ['Incharge Section', p.incharge_section], ['Registration No', p.reg_no],
        ['Basic Salary', p.basic], ['Traveling Allow', p.trav], ['Reimbursement', p.reimb], ['Other Allow', p.others],
        ['Email', p.email], ['Cell No', p.phone], ['Home Contact', p.home], ['Postal Code', p.postal],
        ['Address', p.address], ['Account Title', p.bank_title], ['Bank Name', p.bank], ['Account No', p.acct]
    ];
    var html = '<table>';
    pairs.forEach(function(r){
        html += '<tr><td class="k">' + (r[0].replace(/[<>&"]/g, '')) + '</td><td>' + (r[1] == null ? '' : r[1].replace(/[<>&"]/g, '')) + '</td></tr>';
    });
    html += '</table></body></html>';
    w.document.write(html);
    w.document.close();
    w.focus();
    setTimeout(function(){ w.print(); }, 300);
};

window.printProfile = function() {
    var id = window.currentProfileId;
    if (id) { printEmployee(id); }
};

window.openRemoveModal = function(el) {
    var id = el.getAttribute('data-id');
    var name = el.getAttribute('data-name');
    var phone = el.getAttribute('data-phone');
    document.getElementById('remEmpId').value = id;
    document.getElementById('remEmpName').textContent = name;
    document.getElementById('remEmpPhone').textContent = (phone && phone !== '') ? phone : '-';
    var cb = document.getElementById('remSendSms');
    if (phone && phone !== '') { cb.disabled = false; cb.checked = true; }
    else { cb.disabled = true; cb.checked = false; }
    document.getElementById('remReason').value = '';
    document.getElementById('remReasonOther').value = '';
    document.getElementById('remOtherRow').style.display = 'none';
    document.getElementById('remRemovalDate').value = '';
    document.getElementById('removeEmpModal').style.display = 'block';
};

window.toggleRemReason = function() {
    var v = document.getElementById('remReason').value;
    var otherRow = document.getElementById('remOtherRow');
    var other = document.getElementById('remReasonOther');
    if (v === 'Other') { otherRow.style.display = 'block'; other.required = true; }
    else { otherRow.style.display = 'none'; other.required = false; other.value = ''; }
};

document.getElementById('removeEmpForm').addEventListener('submit', function(e) {
    var sel = document.getElementById('remReason');
    if (sel.value === 'Other') {
        var other = document.getElementById('remReasonOther');
        if (other.value.trim() === '') { e.preventDefault(); other.focus(); alert('Please specify the reason.'); return; }
        sel.value = other.value.trim();
    }
});

window.closeRemoveModal = function() {
    document.getElementById('removeEmpModal').style.display = 'none';
};

window.onclick = function(e) {
    var m = document.getElementById('removeEmpModal');
    if (e.target === m) { m.style.display = 'none'; }
    var a = document.getElementById('accessModal');
    if (e.target === a) { a.style.display = 'none'; }
};

window.toggleActions = function(id) {
    var m = document.getElementById('admenu_' + id);
    if (!m) return;
    var willOpen = m.style.display !== 'block';
    var all = document.querySelectorAll('.ad-menu');
    for (var i = 0; i < all.length; i++) { all[i].style.display = 'none'; }
    if (willOpen) { m.style.display = 'block'; }
};

document.addEventListener('click', function(e) {
    if (!e.target.closest || !e.target.closest('.ad-toggle')) {
        var all = document.querySelectorAll('.ad-menu');
        for (var i = 0; i < all.length; i++) { all[i].style.display = 'none'; }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>