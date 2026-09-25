<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Employee Access';

function access_clean($v) {
    return preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string)$v)));
}

try {
    db_query("CREATE TABLE IF NOT EXISTS user_module_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        module VARCHAR(50) NOT NULL,
        page VARCHAR(50) NOT NULL,
        permission VARCHAR(20) DEFAULT NULL,
        allowed TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_access (user_id, module, page)
    )");
    $__col = db_query("SHOW COLUMNS FROM user_module_access LIKE 'permission'");
    if ($__col && $__col->num_rows === 0) {
        db_query("ALTER TABLE user_module_access ADD COLUMN permission VARCHAR(20) DEFAULT NULL");
    }
} catch (\Throwable $e) {}

$emp_id = (int) ($_GET['emp_id'] ?? 0);
$emp = null;
if ($emp_id > 0) {
    $emp = db_query("SELECT * FROM employees WHERE emp_id=$emp_id AND status IN (0,1)")->fetch_assoc();
}

$message = '';
$error = '';

if (isset($_GET['saved']) && $message === '') { $message = 'Access saved successfully.'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!$emp) {
        $error = 'Invalid employee.';
    } elseif ($action === 'GrantModuleAccess') {
        $module = access_clean($_POST['module'] ?? '');
        $page = access_clean($_POST['page'] ?? '');
        $fullName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
        $emaill = strtolower(trim($emp['email'] ?? ''));
        if ($module === '' || $page === '') {
            $error = 'Missing access details.';
        } elseif ($emaill === '') {
            $error = 'Please set an email for this employee first (Edit Employee) before granting module access.';
        } else {
            $existing = 0;
            $chk = db_prepare("SELECT user_id FROM users WHERE email=?");
            $chk->bind_param('s', $emaill);
            $chk->execute();
            if ($rr = $chk->get_result()->fetch_assoc()) { $existing = (int)$rr['user_id']; }
            if (!$existing) {
                $hash = hash('sha256', 'staff123');
                $ins = db_prepare("INSERT INTO users (email, password, full_name, role, status) VALUES (?, ?, ?, 'staff', 1)");
                $ins->bind_param('sss', $emaill, $hash, $fullName);
                $ins->execute();
                $existing = (int)$ins->insert_id;
            }
            $st2 = db_prepare("INSERT INTO user_module_access (user_id, module, page, allowed) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE allowed=1");
            $st2->bind_param('iss', $existing, $module, $page);
            $st2->execute();
            $message = 'Access granted to ' . $fullName . ' for ' . $page . '.';
        }
    } elseif ($action === 'RemoveModuleAccess') {
        $module = access_clean($_POST['module'] ?? '');
        $page = access_clean($_POST['page'] ?? '');
        $emaill = strtolower(trim($emp['email'] ?? ''));
        if ($module !== '' && $page !== '' && $emaill !== '') {
            $st2 = db_prepare("DELETE um FROM user_module_access um JOIN users u ON u.user_id=um.user_id WHERE u.email=? AND um.module=? AND um.page=?");
            $st2->bind_param('sss', $emaill, $module, $page);
            $st2->execute();
            $message = 'Access removed for ' . $page . '.';
        }
    } elseif ($action === 'TogglePermission') {
        $module = access_clean($_POST['module'] ?? '');
        $page = access_clean($_POST['page'] ?? '');
        $permission = access_clean($_POST['permission'] ?? '');
        $grant = !empty($_POST['grant']) ? 1 : 0;
        $emaill = strtolower(trim($emp['email'] ?? ''));
        if ($module !== '' && $page !== '' && in_array($permission, ['edit', 'delete'], true) && $emaill !== '') {
            $existing = 0;
            $chk = db_prepare("SELECT user_id FROM users WHERE email=?");
            $chk->bind_param('s', $emaill);
            $chk->execute();
            if ($rr = $chk->get_result()->fetch_assoc()) { $existing = (int)$rr['user_id']; }
            if ($existing > 0) {
                if ($grant) {
                    $st3 = db_prepare("INSERT INTO user_module_access (user_id, module, page, permission, allowed) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE allowed=1");
                    $st3->bind_param('isss', $existing, $module, $page, $permission);
                    $st3->execute();
                } else {
                    $st3 = db_prepare("DELETE FROM user_module_access WHERE user_id=? AND module=? AND page=? AND permission=?");
                    $st3->bind_param('isss', $existing, $module, $page, $permission);
                    $st3->execute();
                }
            }
        }
    } elseif ($action === 'SaveAccess') {
        $fullName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
        $emaill = strtolower(trim($emp['email'] ?? ''));
        if ($emaill === '') {
            $error = 'Please set an email for this employee first (Edit Employee) before granting module access.';
        } else {
            $existing = 0;
            $chk = db_prepare("SELECT user_id FROM users WHERE email=?");
            $chk->bind_param('s', $emaill);
            $chk->execute();
            if ($rr = $chk->get_result()->fetch_assoc()) { $existing = (int)$rr['user_id']; }
            if (!$existing) {
                $hash = hash('sha256', 'staff123');
                $ins = db_prepare("INSERT INTO users (email, password, full_name, role, status) VALUES (?, ?, ?, 'staff', 1)");
                $ins->bind_param('sss', $emaill, $hash, $fullName);
                $ins->execute();
                $existing = (int)$ins->insert_id;
            }
            $subPage   = $_POST['page'] ?? [];
            $subPerm   = $_POST['access'] ?? [];
            $subPage   = is_array($subPage) ? $subPage : [];
            $subPerm   = is_array($subPerm) ? $subPerm : [];
            $allPerms  = ['view', 'edit', 'delete'];

            $desired = [];
            foreach ($subPage as $key => $v) {
                $parts = explode('::', (string)$key, 2);
                if (count($parts) !== 2) { continue; }
                $desired[access_clean($parts[0])][access_clean($parts[1])]['view'] = 1;
            }
            foreach ($subPerm as $key => $vals) {
                $parts = explode('::', (string)$key, 2);
                if (count($parts) !== 2) { continue; }
                $mod = access_clean($parts[0]);
                $pg  = access_clean($parts[1]);
                $vals = is_array($vals) ? $vals : [$vals];
                foreach ($vals as $v) {
                    $v = access_clean($v);
                    if (in_array($v, ['edit', 'delete'], true)) { $desired[$mod][$pg][$v] = 1; }
                }
            }

            $ex = db_query("SELECT id, module, page, permission FROM user_module_access WHERE user_id=$existing");
            $toDelete = [];
            while ($row = $ex->fetch_assoc()) {
                if (!isset($desired[$row['module']][$row['page']][$row['permission']])) { $toDelete[] = (int)$row['id']; }
            }
            foreach ($toDelete as $rid) { db_query("DELETE FROM user_module_access WHERE id=$rid"); }

            $upsert = db_prepare("INSERT INTO user_module_access (user_id, module, page, permission, allowed) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE allowed=1");
            foreach ($desired as $mod => $pages) {
                foreach ($pages as $pg => $perms) {
                    foreach ($perms as $p => $x) {
                        $upsert->bind_param('isss', $existing, $mod, $pg, $p);
                        $upsert->execute();
                    }
                }
            }
            $message = 'Access saved for ' . $fullName . '.';
        }
    }
    $redir = BASE_URL . 'employee_access.php?emp_id=' . $emp_id;
    if ($action === 'SaveAccess' && $message !== '') { $redir .= '&saved=1'; }
    header('Location: ' . $redir);
    exit;
}

$granted = [];
if ($emp) {
    $emaill = strtolower(trim($emp['email'] ?? ''));
    if ($emaill !== '') {
        $chk = db_prepare("SELECT user_id FROM users WHERE email=?");
        $chk->bind_param('s', $emaill);
        $chk->execute();
        if ($rr = $chk->get_result()->fetch_assoc()) {
            $uid = (int)$rr['user_id'];
            $res = db_query("SELECT module, page, permission FROM user_module_access WHERE user_id=$uid");
            while ($row = $res->fetch_assoc()) { $granted[$row['module']][$row['page']][$row['permission']] = 1; }
        }
    }
}

$modules = [
    ['no' => 1,  'module' => 'front_office',   'label' => 'Front Office', 'icon' => 'fa-phone', 'pages' => [
        ['page' => 'front_desk_analytics', 'label' => 'Front Desk Overview', 'acl' => 1],
        ['page' => 'student_inquiry', 'label' => 'Admission Inquiries', 'acl' => 1],
        ['page' => 'manage_complaint', 'label' => 'Complaint Hub', 'acl' => 0],
    ]],
    ['no' => 2,  'module' => 'dashboard',   'label' => 'Dashboard', 'icon' => 'fa-tachometer', 'pages' => [
        ['page' => 'dashboard', 'label' => 'Executive Dashboard', 'acl' => 0],
        ['page' => 'basic_dashboard', 'label' => 'Staff Dashboard', 'acl' => 0],
    ]],
    ['no' => 3,  'module' => 'students',   'label' => 'Students', 'icon' => 'fa-graduation-cap', 'pages' => [
        ['page' => 'add_student', 'label' => 'Add New Student', 'acl' => 0],
        ['page' => 'students_analytics_dashboard', 'label' => 'Student Analytics', 'acl' => 1],
        ['page' => 'class_promotion', 'label' => 'Class Promotion', 'acl' => 0],
    ]],
    ['no' => 4,  'module' => 'attendance',   'label' => 'Attendance', 'icon' => 'fa-calendar-check-o', 'pages' => [
        ['page' => 'mark_attend', 'label' => 'Mark Attendance', 'acl' => 1],
        ['page' => 'mark_attendanceReport_list', 'label' => 'Attendance Analytics', 'acl' => 1],
        ['page' => 'send_msgs', 'label' => 'Send SMS Report', 'acl' => 0],
    ]],
    ['no' => 5,  'module' => 'messages',   'label' => 'Messages', 'icon' => 'fa-envelope', 'pages' => [
        ['page' => 'new_message', 'label' => 'New Message', 'acl' => 0],
        ['page' => 'messages_history', 'label' => 'View Messages', 'acl' => 0],
        ['page' => 'view_templates', 'label' => 'View Templates', 'acl' => 1],
    ]],
    ['no' => 6,  'module' => 'fee_collection',   'label' => 'Fee Collection', 'icon' => 'fa-money', 'pages' => [
        ['page' => 'monthly_challan', 'label' => 'Create Challan', 'acl' => 1],
        ['page' => 'view_challan', 'label' => 'View Challan', 'acl' => 1],
        ['page' => 'multi_fee_reports', 'label' => 'Fee Reporting', 'acl' => 1],
        ['page' => 'update_fee_settings', 'label' => 'Fee Settings', 'acl' => 1],
    ]],
    ['no' => 7,  'module' => 'timetable',   'label' => 'Timetable', 'icon' => 'fa-clock-o', 'pages' => [
        ['page' => 'period_categories', 'label' => 'Periods Category', 'acl' => 1],
        ['page' => 'create_period_details', 'label' => 'Create/Manage Periods', 'acl' => 1],
        ['page' => 'class_period', 'label' => 'Assign Periods to Classes', 'acl' => 1],
        ['page' => 'class_period_selection', 'label' => 'Create Timetable', 'acl' => 0],
        ['page' => 'view_class_period_selection', 'label' => 'View Timetable', 'acl' => 1],
        ['page' => 'view_teachers_timetable', 'label' => 'Teachers Timetable', 'acl' => 0],
    ]],
    ['no' => 12, 'module' => 'library',   'label' => 'Library', 'icon' => 'fa-book', 'pages' => [
        ['page' => 'list_books', 'label' => 'Book List', 'acl' => 1],
        ['page' => 'issue_return', 'label' => 'Issue Return', 'acl' => 0],
        ['page' => 'issue_return_employee', 'label' => 'Employee Issue&Return', 'acl' => 0],
    ]],
    ['no' => 13, 'module' => 'payroll',   'label' => 'PayRoll', 'icon' => 'fa-money', 'pages' => [
        ['page' => 'creat_payroll', 'label' => 'Create PayRoll', 'acl' => 0],
        ['page' => 'view_payroll', 'label' => 'View PayRoll', 'acl' => 1],
        ['page' => 'staff_security', 'label' => 'Staff Security Fee', 'acl' => 0],
        ['page' => 'payroll_setting', 'label' => 'PayRoll Setting', 'acl' => 0],
    ]],
    ['no' => 14, 'module' => 'parents_portal',   'label' => 'Parents Portal', 'icon' => 'fa-home', 'pages' => [
        ['page' => 'parents_portal_dashboard', 'label' => 'Parents Overview', 'acl' => 1],
    ]],
    ['no' => 15, 'module' => 'cards_generator',   'label' => 'Cards Generator', 'icon' => 'fa-id-card-o', 'pages' => [
        ['page' => 'cards', 'label' => 'Staff Cards', 'acl' => 0],
        ['page' => 'students_card', 'label' => 'Students Cards', 'acl' => 0],
    ]],
    ['no' => 16, 'module' => 'expenses',   'label' => 'Expenses', 'icon' => 'fa-file', 'pages' => [
        ['page' => 'manage_expenses', 'label' => 'Add/View Expenses', 'acl' => 1],
        ['page' => 'monthly_expenses_report', 'label' => 'Expenses Report', 'acl' => 1],
    ]],
    ['no' => 17, 'module' => 'pos',   'label' => 'Point of Sale', 'icon' => 'fa-search', 'pages' => [
        ['page' => 'canteen_dashboard', 'label' => 'POS Dashboard', 'acl' => 0],
    ]],
    ['no' => 18, 'module' => 'academic_setup',   'label' => 'Academic Setup', 'icon' => 'fa-cog', 'pages' => [
        ['page' => 'academic_setup', 'label' => 'Manage Academics', 'acl' => 0],
    ]],
    ['no' => 19, 'module' => 'system_settings',   'label' => 'System Settings', 'icon' => 'fa-wrench', 'pages' => [
        ['page' => 'settings', 'label' => 'Update Settings', 'acl' => 0],
        ['page' => 'manage_localities', 'label' => 'Manage Localities', 'acl' => 1],
    ]],
    ['no' => 20, 'module' => 'accounts',   'label' => 'Accounts', 'icon' => 'fa-bar-chart', 'pages' => [
        ['page' => 'add_revenue', 'label' => 'Add Revenue', 'acl' => 0],
        ['page' => 'revenue_list', 'label' => 'List of Revenues', 'acl' => 0],
        ['page' => 'revenue_heads', 'label' => 'Revenue Heads', 'acl' => 0],
    ]],
];

include __DIR__ . '/includes/header.php';
?>
<style>
.emp-card{ background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:24px; }
.emp-head{ display:flex; align-items:center; gap:14px; padding-bottom:20px; border-bottom:1px solid #F3F4F6; flex-wrap:wrap; }
.emp-head .avatar-big{ width:58px; height:58px; border-radius:999px; background:linear-gradient(135deg,#FF7A1B,#ffa35c); color:#fff; display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:800; }
.acl-table{ width:100%; border-collapse:collapse; margin-top:6px; }
.acl-table th{ background:#FFF7ED; color:#9A3412; font-size:13px; font-weight:800; text-align:left; padding:10px 14px; border:1px solid #FFE9D6; }
.acl-table td{ border:1px solid #E5E7EB; padding:12px 14px; vertical-align:top; font-size:14px; }
.prow{ display:flex; align-items:center; gap:8px; padding:6px 8px; border-radius:8px; }
.prow:hover{ background:#FFF7ED; }
.prow .plink{ flex:1; font-weight:600; font-size:13.5px; color:#111827; text-decoration:none; }
.prow .plink:hover{ color:#C2410C; }
.acl-cb{ width:17px; height:17px; cursor:pointer; accent-color:#FF7A1B; flex-shrink:0; }
.granted-chip{ display:inline-block; font-size:10.5px; font-weight:800; color:#16A34A; background:#DCFCE7; padding:2px 9px; border-radius:999px; }
.pperm{ font-size:10px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:.3px; }
.save-bar{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:16px; flex-wrap:wrap; }
.save-bar .hint{ font-size:12px; color:#6B7280; }
.mod-name{ display:flex; align-items:center; gap:8px; font-weight:800; font-size:13.5px; color:#111827; white-space:nowrap; }
.mod-name i{ color:#FF7A1B; }
@media print { .no-print { display:none !important; } }
</style>
<div class="main-content">
    <div class="container-fluid">

        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-key" style="color:#16A34A;"></i> Employee Access / Portal</h3>
            <a href="<?php echo BASE_URL; ?>view_emp.php" class="toolbar-btn" style="background:#377DFF; color:#fff; text-decoration:none;"><i class="fa fa-arrow-left"></i> Back to Employees</a>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <?php if (!$emp): ?>
            <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:40px; text-align:center; color:#6B7280;">Employee not found.</div>
        <?php else: ?>
            <?php
            $fullName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
            $initial = strtoupper(substr($fullName, 0, 1));
            $initial = $initial !== '' ? $initial : 'E';
            ?>
            <div class="emp-card">
                <div class="emp-head">
                    <div class="avatar-big"><?php echo $initial; ?></div>
                    <div style="flex:1;">
                        <div style="font-size:18px; font-weight:800; color:#111827;"><?php echo e($fullName); ?></div>
                        <div style="font-size:13px; color:#6B7280; margin-top:2px;">
                            <?php echo e($emp['designation'] ?? '-'); ?><?php echo !empty($emp['department']) ? ' &middot; ' . e($emp['department']) : ''; ?>
                        </div>
                        <div style="font-size:12.5px; color:#9CA3AF; margin-top:2px;">
                            <?php if (!empty($emp['email'])): ?><i class="fa fa-envelope"></i> <?php echo e($emp['email']); ?><?php endif; ?>
                            <?php if (!empty($emp['phone'])): ?> &nbsp; <i class="fa fa-phone"></i> <?php echo e($emp['phone']); ?><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="display:flex; align-items:center; justify-content:space-between; margin:20px 0 10px;">
                    <span style="font-weight:800; font-size:15px; color:#111827;"><i class="fa fa-list-alt" style="color:#FF7A1B;"></i> Module &amp; Pages Access</span>
                    <span style="font-size:11.5px; color:#9CA3AF;">Boxes check karein phir <strong>Save Access</strong> click karein</span>
                </div>

                <form method="post" action="<?php echo BASE_URL; ?>employee_access.php?emp_id=<?php echo $emp_id; ?>">
                    <input type="hidden" name="action" value="SaveAccess">
                    <table class="acl-table">
                    <thead>
                        <tr><th style="width:70px;">S.No</th><th style="width:190px;">Module</th><th>Pages Access</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modules as $mod): ?>
                            <tr>
                                <td><?php echo $mod['no']; ?></td>
                                <td><span class="mod-name"><i class="fa <?php echo $mod['icon']; ?>"></i> <?php echo $mod['label']; ?></span></td>
                                <td>
                                    <?php foreach ($mod['pages'] as $pg): ?>
                                        <?php $isGranted = isset($granted[$mod['module']][$pg['page']]['view']); ?>
                                        <div class="prow">
                                            <input type="checkbox" class="acl-cb" name="page[<?php echo $mod['module'] . '::' . $pg['page']; ?>]" value="1" <?php echo $isGranted ? 'checked' : ''; ?> title="Grant / Remove access">
                                            <a class="plink" href="<?php echo BASE_URL . $pg['page'] . '.php'; ?>" target="_blank"><?php echo $pg['label']; ?></a>
                                            <?php if ($pg['acl']): ?>
                                                <span class="pperm">Edit</span>
                                                <input type="checkbox" class="acl-cb" name="access[<?php echo $mod['module'] . '::' . $pg['page']; ?>][]" value="edit" <?php echo isset($granted[$mod['module']][$pg['page']]['edit']) ? 'checked' : ''; ?> title="Edit access">
                                                <span class="pperm">Delete</span>
                                                <input type="checkbox" class="acl-cb" name="access[<?php echo $mod['module'] . '::' . $pg['page']; ?>][]" value="delete" <?php echo isset($granted[$mod['module']][$pg['page']]['delete']) ? 'checked' : ''; ?> title="Delete access">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="save-bar">
                    <span class="hint"><i class="fa fa-info-circle"></i> Changes apply only after clicking <strong>Save Access</strong>.</span>
                    <button type="submit" class="btn btn-success" style="font-weight:700;"><i class="fa fa-save"></i> Save Access</button>
                </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
// Access is saved via the "Save Access" button.
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>