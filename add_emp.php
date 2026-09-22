<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$__migrate = [
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS father_name VARCHAR(191) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS religion VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS gender VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS blood_group VARCHAR(10) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS cnic VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS marital_status VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS qualification VARCHAR(191) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS job_type VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS contract_end DATE DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS class_head VARCHAR(191) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS incharge_class INT DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS incharge_section VARCHAR(10) DEFAULT NULL",
    "ALTER TABLE employees MODIFY COLUMN incharge_section VARCHAR(10) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS reg_no VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS bank_title VARCHAR(191) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS bank_name VARCHAR(191) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS bank_account VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS home_phone VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS postal_code VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS allowance_traveling DECIMAL(12,2) DEFAULT 0",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS allowance_reimbursement DECIMAL(12,2) DEFAULT 0",
    "ALTER TABLE employees ADD COLUMN IF NOT EXISTS allowance_others DECIMAL(12,2) DEFAULT 0",
    "CREATE TABLE IF NOT EXISTS employee_experience (id INT AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, designation VARCHAR(191) DEFAULT NULL, from_date DATE DEFAULT NULL, to_date DATE DEFAULT NULL, company VARCHAR(191) DEFAULT NULL)",
];
foreach ($__migrate as $__sql) { try { db_query($__sql); } catch (\Throwable $e) {} }

$page_title = 'Add Employee';

$message = '';
$error = '';

$edit_id = (int) ($_GET['emp_id'] ?? 0);

$classes = [];
$res = db_query("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$designations = [];
$res = db_query("SELECT DISTINCT designation FROM employees WHERE designation IS NOT NULL AND designation <> '' ORDER BY designation");
while ($row = $res->fetch_assoc()) { $designations[] = $row['designation']; }

$departments = [];
$res = db_query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department <> '' ORDER BY department");
while ($row = $res->fetch_assoc()) { $departments[] = $row['department']; }

$emp = null;
$exp_rows = [];
if ($edit_id > 0) {
    $st2 = db_prepare("SELECT * FROM employees WHERE emp_id=?");
    $st2->bind_param('i', $edit_id);
    $st2->execute();
    $emp = $st2->get_result()->fetch_assoc();
    if ($emp) {
        $res = db_query("SELECT * FROM employee_experience WHERE employee_id=$edit_id ORDER BY id");
        while ($row = $res->fetch_assoc()) { $exp_rows[] = $row; }
    }
}

function save_photo($file) {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { return null; }
    $data = @file_get_contents($file['tmp_name']);
    if ($data === false || strlen($data) === 0) { return null; }
    $img = @imagecreatefromstring($data);
    if (!$img) { return null; }
    $dir = __DIR__ . '/uploads/employees';
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    $name = 'emp_' . time() . '_' . rand(1000, 9999) . '.jpg';
    $w = imagesx($img); $h = imagesy($img);
    $maxw = 400;
    if ($w > $maxw) {
        $nh = (int) round($h * ($maxw / $w));
        $thumb = imagecreatetruecolor($maxw, $nh);
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $maxw, $nh, $w, $h);
        imagedestroy($img);
        $img = $thumb;
    }
    imagejpeg($img, $dir . '/' . $name, 80);
    imagedestroy($img);
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'AddEmployee') {
    $first = trim($_POST['fname'] ?? $_POST['first_name'] ?? '');
    $father = trim($_POST['lname'] ?? trim($_POST['father_name'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['cell_no'] ?? trim($_POST['phone'] ?? ''));
    $designation = trim($_POST['designation'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $joining = trim($_POST['joining_date'] ?? $_POST['joining'] ?? '');
    $salary = (float) ($_POST['salary'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $religion = trim($_POST['religion'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $blood = trim($_POST['blood'] ?? '');
    $cnic = trim($_POST['cnic'] ?? '');
    $marital = trim($_POST['marital_status'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $jobtype = trim($_POST['jobtype'] ?? '');
    $contract_end = trim($_POST['contract_ending_date'] ?? '');
    $class_head = trim($_POST['class_head'] ?? '');
    $incharge_class = (int) ($_POST['class_id'] ?? 0);
    $incharge_section = trim((string) ($_POST['section'] ?? ''));
    $reg_no = trim($_POST['registratin_number'] ?? '');
    $bank_title = trim($_POST['account_holder'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_account = trim($_POST['account_no'] ?? '');
    $home_phone = trim($_POST['home_no'] ?? '');
    $postal = trim($_POST['postal'] ?? '');
    $trav = (float) ($_POST['emp_trav'] ?? 0);
    $reimb = (float) ($_POST['emp_reimbersement'] ?? 0);
    $others = (float) ($_POST['emp_other'] ?? 0);

    if ($first === '') {
        $error = 'Employee name is required.';
    } else {
        $photo = save_photo($_FILES['img_file'] ?? null);
        $dob_db = $dob ?: null;
        $join_db = $joining ?: null;
        $contract_db = $contract_end ?: null;
        $edit_id = (int) ($_POST['emp_id'] ?? $edit_id);

        if ($edit_id > 0) {
            if ($photo === null && !empty($emp['photo'])) { $photo = $emp['photo']; }
            $st2 = db_prepare("UPDATE employees SET first_name=?, last_name=?, father_name=?, email=?, phone=?, designation=?, department=?, dob=?, joining_date=?, salary=?, address=?, religion=?, gender=?, blood_group=?, cnic=?, marital_status=?, qualification=?, job_type=?, contract_end=?, class_head=?, incharge_class=?, incharge_section=?, reg_no=?, bank_title=?, bank_name=?, bank_account=?, home_phone=?, postal_code=?, allowance_traveling=?, allowance_reimbursement=?, allowance_others=?, photo=IFNULL(?, photo), status=1 WHERE emp_id=?");
            $st2->bind_param('sssssssssdsssssssssssissssssdddsi',
                $first, $father, $father, $email, $phone, $designation, $department,
                $dob_db, $join_db, $salary, $address, $religion, $gender, $blood,
                $cnic, $marital, $qualification, $jobtype, $contract_db, $class_head,
                $incharge_class, $incharge_section, $reg_no, $bank_title, $bank_name,
                $bank_account, $home_phone, $postal, $trav, $reimb, $others, $photo, $edit_id);
            $st2->execute();
            $message = 'Employee updated successfully!';
        } else {
            $st2 = db_prepare("INSERT INTO employees (first_name, last_name, father_name, email, phone, designation, department, dob, joining_date, salary, address, religion, gender, blood_group, cnic, marital_status, qualification, job_type, contract_end, class_head, incharge_class, incharge_section, reg_no, bank_title, bank_name, bank_account, home_phone, postal_code, allowance_traveling, allowance_reimbursement, allowance_others, photo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $st2->bind_param('sssssssssdsssssssssssissssssddds',
                $first, $father, $father, $email, $phone, $designation, $department,
                $dob_db, $join_db, $salary, $address, $religion, $gender, $blood,
                $cnic, $marital, $qualification, $jobtype, $contract_db, $class_head,
                $incharge_class, $incharge_section, $reg_no, $bank_title, $bank_name,
                $bank_account, $home_phone, $postal, $trav, $reimb, $others, $photo);
            $st2->execute();
            $edit_id = $st2->insert_id;
            $message = 'Employee added successfully!';
        }

        if ($edit_id > 0) {
            $stDel = db_query("DELETE FROM employee_experience WHERE employee_id=$edit_id");
            $stIns = db_prepare("INSERT INTO employee_experience (employee_id, designation, from_date, to_date, company) VALUES (?, ?, ?, ?, ?)");
            $desigs = $_POST['prev_desig'] ?? [];
            foreach ($desigs as $i => $d) {
                $d = trim((string)$d);
                $from = trim((string)($_POST['prev_exp_from'][$i] ?? ''));
                $to = trim((string)($_POST['prev_exp_to'][$i] ?? ''));
                $comp = trim((string)($_POST['prev_comp'][$i] ?? ''));
                if ($d === '' && $comp === '' && $from === '' && $to === '') { continue; }
                $fromVal = ($from !== '') ? $from : null;
                $toVal = ($to !== '') ? $to : null;
                $stIns->bind_param('issss', $edit_id, $d, $fromVal, $toVal, $comp);
                $stIns->execute();
            }
        }

        if (!empty($_POST['userAccess']) && $email !== '') {
            $pw = trim($_POST['password'] ?? '');
            $full = $first . ' ' . $father;
            $existing = null;
            $chk = db_prepare("SELECT user_id FROM users WHERE email=?");
            $chk->bind_param('s', $email);
            $chk->execute();
            $chkRes = $chk->get_result();
            if ($row = $chkRes->fetch_assoc()) { $existing = (int)$row['user_id']; }
            if ($existing) {
                if ($pw !== '') {
                    $up = db_prepare("UPDATE users SET full_name=?, password=?, status=1 WHERE user_id=?");
                    $hash = hash('sha256', $pw);
                    $up->bind_param('ssi', $full, $hash, $existing);
                    $up->execute();
                } else {
                    $up = db_prepare("UPDATE users SET full_name=?, status=1 WHERE user_id=?");
                    $up->bind_param('si', $full, $existing);
                    $up->execute();
                }
            } else {
                $hash = hash('sha256', $pw !== '' ? $pw : 'staff123');
                $ins = db_prepare("INSERT INTO users (email, password, full_name, role, status) VALUES (?, ?, ?, 'staff', 1)");
                $ins->bind_param('sss', $email, $hash, $full);
                $ins->execute();
            }
        }

        if ($edit_id > 0) {
            $st2 = db_prepare("SELECT * FROM employees WHERE emp_id=?");
            $st2->bind_param('i', $edit_id);
            $st2->execute();
            $emp = $st2->get_result()->fetch_assoc();
            $res = db_query("SELECT * FROM employee_experience WHERE employee_id=$edit_id ORDER BY id");
            $exp_rows = [];
            while ($row = $res->fetch_assoc()) { $exp_rows[] = $row; }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.status-badge { padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; display:inline-block; }
.form-section { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:20px; margin-bottom:18px; }
.form-section h4 { font-size:15px; font-weight:800; color:#111827; margin:0 0 16px; padding-bottom:12px; border-bottom:1px solid #F3F4F6; }
.form-section h4 i { color:#FF7A1B; margin-right:8px; }
.exp-row { background:#F9FAFB; border:1px solid #EEF0F2; border-radius:10px; padding:12px; margin-bottom:10px; }
.exp-row .exp-title { font-size:12px; font-weight:700; color:#6B7280; margin-bottom:8px; text-transform:uppercase; }
.emp-photo-preview { display:block; margin:0 auto; width:100%; max-width:160px; height:150px; object-fit:cover; border-radius:10px; border:1px solid #E5E7EB; }
label { font-weight:600; font-size:13px; color:#374151; }
.required:after { content:" *"; color:#DC2626; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-user-plus"></i> <?php echo $edit_id > 0 && $emp ? 'Edit Employee' : 'Add Employee'; ?></h3>
            <a href="<?php echo BASE_URL; ?>view_emp.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-users"></i> View Employees</a>
        </div>

        <form method="post" action="add_emp.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="AddEmployee">
            <input type="hidden" name="emp_id" value="<?php echo $edit_id > 0 ? $edit_id : ''; ?>">

            <div class="row">
                <div class="col-md-10">
                    <div class="form-section">
                        <h4><i class="fa fa-id-card"></i> Personal Information</h4>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="required">Employee Name</label>
                                    <input type="text" name="fname" class="form-control" required value="<?php echo e($emp['first_name'] ?? ''); ?>" placeholder="Employee Name">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Father / Husband Name</label>
                                    <input type="text" name="lname" class="form-control" value="<?php echo e($emp['last_name'] ?? ''); ?>" placeholder="Father / Husband Name">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Gender</label>
                                    <div>
                                        <label style="font-weight:400; margin-right:12px;"><input type="radio" name="gender" value="Male" <?php echo ($emp['gender'] ?? '') === 'Male' ? 'checked' : ''; ?>> Male</label>
                                        <label style="font-weight:400;"><input type="radio" name="gender" value="Female" <?php echo ($emp['gender'] ?? '') === 'Female' ? 'checked' : ''; ?>> Female</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Religion</label>
                                    <select name="religion" class="form-control">
                                        <option value="">Select Religion</option>
                                        <?php foreach (['Muslim','Hindu','Sikh','Christian','Ahmadi'] as $r): ?>
                                            <option value="<?php echo $r; ?>" <?php echo ($emp['religion'] ?? '') === $r ? 'selected' : ''; ?>><?php echo $r; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Blood Group</label>
                                    <select name="blood" class="form-control">
                                        <option value="">Select</option>
                                        <?php foreach (['A-','A+','B-','B+','O-','O+','AB-','AB+'] as $b): ?>
                                            <option value="<?php echo $b; ?>" <?php echo ($emp['blood_group'] ?? '') === $b ? 'selected' : ''; ?>><?php echo $b; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>CNIC</label>
                                    <input type="text" name="cnic" class="form-control" value="<?php echo e($emp['cnic'] ?? ''); ?>" placeholder="XXXXX-XXXXXXX-X">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Marital Status</label>
                                    <select name="marital_status" class="form-control">
                                        <option value="">Select</option>
                                        <?php foreach (['Married','Widowed','Separated','Divorced','Single'] as $m): ?>
                                            <option value="<?php echo $m; ?>" <?php echo ($emp['marital_status'] ?? '') === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="required">Qualification</label>
                                    <input type="text" name="qualification" class="form-control" required value="<?php echo e($emp['qualification'] ?? ''); ?>" placeholder="e.g. M.Sc, B.Ed">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Date of Birth</label>
                                    <input type="date" name="dob" class="form-control" value="<?php echo e($emp['dob'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Date of Joining</label>
                                    <input type="date" name="joining_date" class="form-control" value="<?php echo e($emp['joining_date'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-section text-center">
                        <h4><i class="fa fa-camera"></i> Photo</h4>
                        <img src="<?php
                            $cur = $emp['photo'] ?? '';
                            echo $cur !== '' ? BASE_URL . 'uploads/employees/' . e($cur) : (BASE_URL . 'assets/img/logo.jpg');
                        ?>" id="preview" class="emp-photo-preview" onerror="this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';">
                        <input type="file" name="img_file" id="img_file_input" class="form-control" accept="image/jpeg,image/png,.jpg,.jpeg,.png" style="margin-top:10px; padding:4px;">
                        <small class="text-muted">Max 200KB - JPG / PNG</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4><i class="fa fa-briefcase"></i> Employment Details</h4>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Job Type</label>
                            <select name="jobtype" id="job_type" class="form-control" onchange="toggleContract(this.value)">
                                <option value="">Select</option>
                                <option value="Permanent" <?php echo ($emp['job_type'] ?? '') === 'Permanent' ? 'selected' : ''; ?>>Permanent</option>
                                <option value="Contract" <?php echo ($emp['job_type'] ?? '') === 'Contract' ? 'selected' : ''; ?>>Visiting</option>
                                <option value="Internship" <?php echo ($emp['job_type'] ?? '') === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3" id="contract_row" style="display:none;">
                        <div class="form-group">
                            <label>Contract Ending Date</label>
                            <input type="date" name="contract_ending_date" class="form-control" value="<?php echo e($emp['contract_end'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Designation</label>
                            <input type="text" name="designation" list="desig_list" class="form-control" value="<?php echo e($emp['designation'] ?? ''); ?>" placeholder="e.g. Teacher">
                            <datalist id="desig_list">
                                <?php foreach ($designations as $d): ?><option value="<?php echo e($d); ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" name="department" list="dept_list" class="form-control" value="<?php echo e($emp['department'] ?? ''); ?>" placeholder="e.g. Academics">
                            <datalist id="dept_list">
                                <?php foreach ($departments as $d): ?><option value="<?php echo e($d); ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Basic Salary</label>
                            <input type="number" step="0.01" name="salary" class="form-control" value="<?php echo e($emp['salary'] ?? '0'); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Course/Class Head</label>
                            <select name="class_head" class="form-control">
                                <option value="">All Heads</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo e($c['class_name']); ?>" <?php echo ($emp['class_head'] ?? '') === $c['class_name'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Incharge Course/Class</label>
                            <select name="class_id" id="class_dropdown" class="form-control" onchange="getInchargeSections(this.value)">
                                <option value="">Select Course/Class</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['class_id']; ?>" <?php echo (int)($emp['incharge_class'] ?? 0) === (int)$c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Incharge Section</label>
                            <select name="section" id="txt_section" class="form-control">
                                <option value="">No Section</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Registration Number</label>
                            <input type="text" name="registratin_number" class="form-control" value="<?php echo e($emp['reg_no'] ?? ''); ?>" placeholder="0123">
                        </div>
                    </div>
                    <div class="col-md-10">
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo e($emp['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4><i class="fa fa-university"></i> Bank &amp; Contact</h4>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Account Title</label>
                            <input type="text" name="account_holder" class="form-control" value="<?php echo e($emp['bank_title'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" value="<?php echo e($emp['bank_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Account No</label>
                            <input type="text" name="account_no" class="form-control" value="<?php echo e($emp['bank_account'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="required">Cell No</label>
                            <input type="text" name="cell_no" class="form-control" required value="<?php echo e($emp['phone'] ?? ''); ?>" placeholder="03XX-XXXXXXX">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Home Contact No</label>
                            <input type="text" name="home_no" class="form-control" value="<?php echo e($emp['home_phone'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Postal Code</label>
                            <input type="text" name="postal" class="form-control" value="<?php echo e($emp['postal_code'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4><i class="fa fa-money"></i> Salary &amp; Allowances</h4>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Traveling Allowance</label>
                            <input type="number" step="0.01" name="emp_trav" class="form-control" value="<?php echo e($emp['allowance_traveling'] ?? '0'); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Reimbursement</label>
                            <input type="number" step="0.01" name="emp_reimbersement" class="form-control" value="<?php echo e($emp['allowance_reimbursement'] ?? '0'); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Others</label>
                            <input type="number" step="0.01" name="emp_other" class="form-control" value="<?php echo e($emp['allowance_others'] ?? '0'); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4><i class="fa fa-history"></i> Previous Experience <button type="button" class="btn btn-success btn-xs" style="float:right;" onclick="toggleExperience(this)"><i class="fa fa-plus"></i> Add Previous Experience</button></h4>
                <div id="experience_area" style="display:none;">
                    <?php for ($i = 0; $i < 5; $i++): $ex = $exp_rows[$i] ?? null; ?>
                    <div class="exp-row">
                        <div class="exp-title">Experience <?php echo $i + 1; ?></div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Designation</label>
                                    <input type="text" name="prev_desig[]" class="form-control" value="<?php echo e($ex['designation'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Experience From</label>
                                    <input type="date" name="prev_exp_from[]" class="form-control" value="<?php echo e($ex['from_date'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Experience To</label>
                                    <input type="date" name="prev_exp_to[]" class="form-control" value="<?php echo e($ex['to_date'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Company/School/College</label>
                                    <input type="text" name="prev_comp[]" class="form-control" value="<?php echo e($ex['company'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="form-section">
                <h4><i class="fa fa-key"></i> Software Access</h4>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo e($emp['email'] ?? ''); ?>" placeholder="employee@school.com">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" class="form-control" placeholder="<?php echo $edit_id > 0 ? 'Leave blank to keep current' : 'Enter secure password'; ?>">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" onclick="togglePassword()"><i class="fa fa-eye" id="togglePasswordIcon"></i></button>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group" style="margin-top:26px;">
                            <label><input type="checkbox" name="userAccess" value="1" style="margin-right:6px;"> Portal Access (allow sign in)</label>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-bottom:24px;">
                <button type="submit" class="btn btn-success" style="padding:10px 34px;"><i class="fa fa-save"></i> Save Employee</button>
                <a href="<?php echo BASE_URL; ?>view_emp.php" class="btn btn-default" style="padding:10px 24px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleContract(v) {
    document.getElementById('contract_row').style.display = (v === 'Contract') ? 'block' : 'none';
}
<?php if (($emp['job_type'] ?? '') === 'Contract'): ?>document.getElementById('contract_row').style.display = 'block';<?php endif; ?>
function toggleExperience(btn) {
    var area = document.getElementById('experience_area');
    var hidden = area.style.display === 'none';
    area.style.display = hidden ? 'block' : 'none';
}
function togglePassword() {
    var p = document.getElementById('password');
    p.type = p.type === 'password' ? 'text' : 'password';
}
function getInchargeSections(classId) {
    var sel = document.getElementById('txt_section');
    var abc = '<option value="">No Section</option><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>';
    if (!classId) { sel.innerHTML = abc; return; }
    fetch('<?php echo BASE_URL; ?>ajax_get_sections.php?class_id=' + encodeURIComponent(classId))
        .then(function(r){ return r.json(); })
        .then(function(data){
            var html = abc;
            data.forEach(function(s){ html += '<option value="' + s.section_id + '">' + s.section_name + '</option>'; });
            sel.innerHTML = html;
            <?php if ($emp !== null && trim((string)($emp['incharge_section'] ?? '')) !== ''): ?>
            sel.value = '<?php echo e($emp['incharge_section']); ?>';
            <?php endif; ?>
        });
}
<?php if ($edit_id > 0 && $emp !== null && (int)($emp['incharge_class'] ?? 0) > 0): ?>
getInchargeSections(<?php echo (int)$emp['incharge_class']; ?>);
<?php endif; ?>
document.getElementById('img_file_input').addEventListener('change', function(e){
    var file = e.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(ev){ document.getElementById('preview').src = ev.target.result; };
    reader.readAsDataURL(file);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>