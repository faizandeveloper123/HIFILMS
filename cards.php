<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Staff ID Cards';

$selDesignation = trim((string)($_GET['designation'] ?? 'All'));
if ($selDesignation === '') { $selDesignation = 'All'; }
$selDepartment = trim((string)($_GET['department'] ?? 'All'));
if ($selDepartment === '') { $selDepartment = 'All'; }

$designations = [];
$res = db_query("SELECT DISTINCT designation FROM employees WHERE status=1 AND designation IS NOT NULL AND designation <> '' ORDER BY designation");
while ($row = $res->fetch_assoc()) { $designations[] = $row['designation']; }

$departments = [];
$res = db_query("SELECT DISTINCT department FROM employees WHERE status=1 AND department IS NOT NULL AND department <> '' ORDER BY department");
while ($row = $res->fetch_assoc()) { $departments[] = $row['department']; }

// EduPortal staff card option lists (exact same options as production)
$eduDesignations = [
    'Academia Manager', 'Academic head', 'Account Manager', 'Accountant', 'Accounts Manager', 'Admin', 'Admin Asst.', 'Admin Officer',
    'Admin/Purchas Officer', 'Administrator', 'Admission Officer', 'Admission Officer + Accounts', 'Assi Acc', 'Assi Mali', 'Assis C.O.E', 'Assistant',
    'Assistant Finance Officer', 'Assistant In-charge', 'Assistant Lecturer', 'Assistant Professor', 'Associate Professor', 'Ayah', 'Ayah (Junior)', 'Branch Admin',
    'C.O.E', 'Campus Manager', 'CEO', 'Chairman', 'Child-Nurse', 'Class Teacher', 'Clerk', 'Clinical Physiotherapist',
    'Comp Lab Assi', 'Computer Instructor', 'Computer Operator', 'Controller Exam', 'Cook', 'Coordinator', 'CT Teacher', 'Custodian',
    'Data Feeder', 'Demonstrator', 'Deputy director operations', 'Designation', 'Director', 'Driver', 'Electrician', 'EQ Leader',
    'Exam Controller', 'Examination Head', 'Fee Collector', 'Female Guard', 'Finance Manager', 'Front Desk Officer', 'Gardener', 'General',
    'Graghic Designing', 'Guard', 'Hafiza', 'Head Accountant', 'Head Cook', 'Head Librarian', 'Head Master SSB', 'Head Mistress',
    'Head of Department', 'Head Teacher', 'Headmistress MSG', 'Helper', 'HR Manager', 'HRM Admin', 'In Charge', 'Intern',
    'IQ Leader', 'IT Manager', 'IT+Activities Incharge', 'Lab Attendant', 'Lab Incharge', 'Lab Instructor', 'Lab Technician', 'Language Instructor',
    'Lecturer', 'lecturer & campus adminstrator', 'Lecturer+Administrater Incharge+Head of Marketing', 'Librarian', 'Maid', 'Mali', 'Marketing Officer', 'Masjid Imam',
    'Mentor', 'Montessori Directress', 'Nursery Manager', 'Office-Boy', 'P.T.I', 'Pantry Boy/Tea Boy', 'Patron-in-Chief', 'Peon',
    'Prep Teacher', 'President', 'Primary Teacher', 'Principal', 'principle', 'Professor', 'PST Teacher', 'PT Teacher',
    'QEC Head', 'Receptionist', 'S.G and Peon', 'Sanitary Worker', 'Sec Head', 'Secondary Teacher', 'Section Coordinator', 'Security Gaurd',
    'Security Guard', 'Security Officer', 'Senior Teacher', 'Servant', 'Sports Teacher', 'SS Teacher', 'SST Teacher', 'Staff room Attendant',
    'Store Incharge', 'Store Keeper', 'Subject Coordinator', 'Subject Teacher', 'Super Admin', 'Supervisor', 'Sweeper', 'Sweepress',
    'Teacher', 'Teacher Assistant', 'Therapist', 'TT Teacher', 'Vice Principal', 'Visiting lecturer', 'Warden Female', 'Warden Male',
    'Wasserman', 'Watch Man'
];
$eduDepartments = [
    'Academic Department', 'Accountant', 'Accounts', 'Admin Section', 'Administration', 'Administrator', 'Al Quran Department', 'Arts',
    'Biology Department', 'Boys Section', 'Camb Section', 'Chemistry Department', 'Child Development Centre CDC', 'Computer Science', 'COOK', 'Doctor of Physical Therapy',
    'DOP', 'English', 'English Department', 'Exam', 'Executive', 'Faculty', 'Four and Five Girls Block', 'Four, Five and Six Elementary Block',
    'Gardener', 'General', 'Graphic designer', 'Guard', 'Hifz Sections', 'High Section', 'House Keeping', 'Islamic Studies',
    'Islamiyat Department', 'IT&CS', 'Junior Section', 'Kids Section', 'LHV', 'Management', 'Marketing/Academics', 'Marketing/Management',
    'Math Department', 'Mathematics', 'Matriculation', 'Medical Lab Technology', 'Microbiology & Immunology', 'Middle School-Boys', 'Middle School-Girls', 'Middle Section',
    'Montessori', 'Montessori(Advance)', 'Montessori(Senior)', 'Montossori (Junior)', 'Nazra', 'Nine And Ten Boys Block', 'Nine and Ten Senior Block', 'Non-Teaching',
    'Nursery Block', 'Nursing', 'One Block', 'OT-Occupational Therapy', 'Pak Study', 'Pharmaceutical Chemistry', 'Pharmaceutics', 'Pharmacognosy',
    'Pharmacology', 'Pharmacy Practice', 'Physics Department', 'PlayGroup', 'Prep Block', 'Primary Section', 'Principal', 'PT',
    'Quran', 'Radiology', 'Science', 'Security', 'security & mangment', 'Senior School-Boys', 'Senior School-Girls', 'Senior Section',
    'Seven and Eight Elementary Blcok', 'Six, Seven, Eight Senior Block', 'Slow Learners SL', 'SLP-Speech Language Pathology', 'Social Science', 'Stock Department', 'Supporting Staff', 'Sweeper',
    'Teaching', 'Three Block', 'Two Block', 'Urdu Department', 'Vocational VC', 'Waqfiyat aama Department', 'Zoology'
];

$allDesignations = array_values(array_unique(array_merge($eduDesignations, $designations)));
$allDepartments  = array_values(array_unique(array_merge($eduDepartments, $departments)));

$staff = [];
$sql = "SELECT emp_id, first_name, last_name, designation, department, phone
        FROM employees WHERE status=1";
if ($selDesignation !== 'All') {
    $sql .= " AND designation='" . db_connect()->real_escape_string($selDesignation) . "'";
}
if ($selDepartment !== 'All') {
    $sql .= " AND department='" . db_connect()->real_escape_string($selDepartment) . "'";
}
$sql .= " ORDER BY first_name";
$res = db_query($sql);
while ($row = $res->fetch_assoc()) { $staff[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
      .scg-wrap { padding: 0 4px 40px; font-family: 'Inter', system-ui, sans-serif; color: #212529; }
      .scg-wrap * { box-sizing: border-box; }
      .scg-crumb { font-size: 12.5px; color: #8a99a8; padding: 2px 4px 8px; }
      .scg-crumb a { color: #3e7cb1; text-decoration: none; }
      .scg-crumb a:hover { text-decoration: underline; }

      .scg-title-row { display: flex; align-items: center; gap: 8px; padding: 0 4px 8px; font-size: 17px; font-weight: 800; color: #2a3f54; }
      .scg-title-row i { color: #3e7cb1; font-size: 16px; }

      .scg-panel { background: #fff; border: 1px solid #e6e9ed; border-radius: 10px; margin-bottom: 12px; padding: 14px 18px; box-shadow: 0 1px 3px rgba(42,63,84,.06); }

      .scg-filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
      .scg-filter-col { flex: 1 1 200px; min-width: 170px; }
      .scg-filter-col label { display: block; font-size: 11.5px; font-weight: 700; color: #495057; margin-bottom: 5px; }
      .scg-filter-col select, .scg-filter-col input { width: 100%; height: 38px; border: 1px solid #d6dee5; border-radius: 8px; padding: 0 12px; font-size: 13.5px; background: #fff; color: #2a3f54; }
      .scg-filter-col select:focus, .scg-filter-col input:focus { outline: none; border-color: #3e7cb1; box-shadow: 0 0 0 3px rgba(62,124,177,.14); }

      .scg-pick-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 12px; }
      .scg-pick-count { font-size: 13px; color: #495057; }
      .scg-pick-count b { color: #2a3f54; font-size: 14px; }
      .scg-pick-count .scg-selected-n { color: #27ae60; font-weight: 800; }

      table.scg-tbl thead th { background: #eef2f6; color: #33475b; font-size: 12px; text-transform: uppercase; letter-spacing: .3px; padding: 11px 10px; border-bottom: 2px solid #dde3e9; }
      table.scg-tbl tbody td { padding: 9px 10px; font-size: 13px; vertical-align: middle; }
      table.scg-tbl tbody tr:hover { background: #f5f9fc; }
      .scg-chk { width: 18px; height: 18px; cursor: pointer; }
      .scg-empty { text-align: center; padding: 40px 20px; color: #95a5a6; }
      .scg-empty i { font-size: 36px; color: #d5dbdb; display: block; margin-bottom: 10px; }

      .scg-print-btn { height: 40px; padding: 0 24px; border: none; border-radius: 8px; background: #3e7cb1; color: #fff; font-weight: 700; font-size: 13.5px; cursor: pointer; }
      .scg-print-btn:hover { background: #336a99; }
      .scg-print-bar { display: flex; justify-content: flex-end; margin-top: 10px; }
    </style>

<div class="main-content">
    <div class="container-fluid">
        <div class="scg-wrap">

            <div class="scg-crumb">
                <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp;
                Staff Cards
            </div>

            <div class="scg-title-row"><i class="fa fa-id-card"></i> Generate Staff ID Cards</div>

            <form id="staffCardForm" action="print_staff_cards.php" method="post" target="_blank">
                <input type="hidden" name="staff_ids" id="selected_staff_ids" value="">
                <input type="hidden" name="designation" id="selected_designation" value="All">
                <input type="hidden" name="department" id="selected_department" value="All">

                <!-- ===== Filters ===== -->
                <div class="scg-panel">
                    <div class="scg-filter-row">
                        <div class="scg-filter-col">
                            <label>Designation</label>
                            <select name="designation" id="designationFilter" class="form-control" onchange="window.location='cards.php?designation='+encodeURIComponent(this.value)+'&department='+encodeURIComponent(document.getElementById('departmentFilter').value);">
                                <option value="All">All</option>
                                <?php foreach ($allDesignations as $d): ?>
                                    <option value="<?php echo e($d); ?>" <?php echo $selDesignation === $d ? 'selected' : ''; ?>><?php echo e($d); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="scg-filter-col">
                            <label>Department</label>
                            <select name="department" id="departmentFilter" class="form-control" onchange="window.location='cards.php?designation='+encodeURIComponent(document.querySelector('#designationFilter').value)+'&department='+encodeURIComponent(this.value);">
                                <option value="All">All</option>
                                <?php foreach ($allDepartments as $dept): ?>
                                    <option value="<?php echo e($dept); ?>" <?php echo $selDepartment === $dept ? 'selected' : ''; ?>><?php echo e($dept); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ===== Staff list ===== -->
                <div class="scg-panel">
                    <div class="scg-pick-bar">
                        <div class="scg-pick-count"><b><?php echo count($staff); ?></b> staff found &middot; <span class="scg-selected-n" id="scgSelectedCount">0</span> selected</div>
                    </div>

                    <div style="overflow-x:auto;">
                    <table id="listofstaff" class="table table-striped table-bordered scg-tbl" style="width:100%;background-color:#FFFFFF;">
                        <thead>
                            <tr>
                                <th width="4%" style="text-align:center;"><input type="checkbox" id="checkAllStaff" class="scg-chk" onclick="toggleAllStaff(this)"></th>
                                <th width="6%" style="text-align:center;"> S.No </th>
                                <th width="30%"> Staff Name </th>
                                <th width="25%"> Designation </th>
                                <th width="20%"> Department </th>
                                <th width="15%"> Contact </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($staff) === 0): ?>
                                <tr>
                                    <td colspan="6" class="scg-empty"><i class="fa fa-user-circle-o"></i>No staff found. Please change the filters and try again.</td>
                                </tr>
                            <?php endif; ?>
                            <?php $si = 0; foreach ($staff as $emp): $si++;
                                $empName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
                            ?>
                                <tr>
                                    <td style="text-align:center;">
                                        <input type="checkbox" class="scg-chk staff-checkbox" name="emp_ids[]" value="<?php echo (int)$emp['emp_id']; ?>" onchange="updateSelectedCount()">
                                    </td>
                                    <td style="text-align:center;"> <?php echo $si; ?> </td>
                                    <td><?php echo e($empName); ?></td>
                                    <td><?php echo e($emp['designation'] ?? ''); ?></td>
                                    <td><?php echo e($emp['department'] ?? ''); ?></td>
                                    <td><?php echo e($emp['phone'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <div class="scg-print-bar">
                        <button type="button" class="scg-print-btn" onclick="printSelectedStaffCards()"><i class="fa fa-print"></i> Print Selected Staff Cards</button>
                    </div>
                </div>

            </form>

        </div>
    </div>
</div>

<script>
    function updateSelectedCount() {
        var n = document.querySelectorAll('input[name="emp_ids[]"]:checked').length;
        document.getElementById('scgSelectedCount').textContent = n;
    }
    function toggleAllStaff(source) {
        document.querySelectorAll('input[name="emp_ids[]"]').forEach(function (cb) { cb.checked = source.checked; });
        updateSelectedCount();
    }
    function printSelectedStaffCards() {
        var selected = [];
        document.querySelectorAll('input[name="emp_ids[]"]:checked').forEach(function (cb) { selected.push(cb.value); });
        if (selected.length === 0) {
            alert('Please choose any staff from checkboxes...');
            return false;
        }
        document.getElementById('selected_staff_ids').value = selected.join(',');
        document.getElementById('selected_designation').value = document.getElementById('designationFilter').value || 'All';
        document.getElementById('selected_department').value = document.getElementById('departmentFilter').value || 'All';
        document.getElementById('staffCardForm').submit();
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>