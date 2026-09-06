<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'User Audit Report';

db_query("CREATE TABLE IF NOT EXISTS user_activity_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    user_name VARCHAR(191) DEFAULT NULL,
    action VARCHAR(191) DEFAULT NULL,
    page VARCHAR(191) DEFAULT NULL,
    details TEXT,
    ip VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$count = (int) (db_query("SELECT COUNT(*) c FROM user_activity_log")->fetch_assoc()['c'] ?? 0);
if ($count === 0) {
    $stmt = db_prepare("INSERT INTO user_activity_log (user_id, user_name, action, page, details, ip) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        $uname = ($_SESSION['full_name'] ?? '') ?: 'System';
        $action = 'login';
        $page = 'user_audit_report.php';
        $details = 'Heartbeat seed record for audit log.';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt->bind_param('isssss', $uid, $uname, $action, $page, $details, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

$modules = [
    14 => 'Fee Collection',
    11 => 'Students',
    3 => 'Employee',
    2 => 'Attendance',
    6 => 'Result Cards',
    5 => 'Localities',
    4 => 'Transports',
    7 => 'Monthly Fees',
    1 => 'Admission Enquiry',
    10 => 'Vouchers',
    8 => 'Expenses',
    13 => 'Messages',
    12 => 'Parents/Students Accounts',
    0 => 'Settings',
];

$selectedModule = isset($_GET['module_id']) ? (int) $_GET['module_id'] : 0;
$selectedUser = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';
$filteredCount = (int) (db_query("SELECT COUNT(*) c FROM user_activity_log")->fetch_assoc()['c'] ?? 0);

$where = [];
$sql = "SELECT log_id, user_id, user_name, action, page, details, ip, created_at FROM user_activity_log WHERE 1=1";
if ($selectedUser > 0) {
    $where[] = "user_id = " . $selectedUser;
}
if ($fromDate !== '') {
    $where[] = "DATE(created_at) >= '" . db_connect()->real_escape_string($fromDate) . "'";
}
if ($toDate !== '') {
    $where[] = "DATE(created_at) <= '" . db_connect()->real_escape_string($toDate) . "'";
}
if ($where) {
    $sql .= ' AND ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC, log_id DESC LIMIT 500';

$logs = [];
$res = db_query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $logs[] = $row;
    }
}

include __DIR__ . '/includes/header.php';
?>
<style type="text/css">
.page-card {
    padding: 25px;
    background-color: #fff;
    border-radius: 15px;
    box-shadow: 0px 8px 25px rgba(0, 0, 0, 0.1);
    margin: 10px;
}
.page-card p {
    margin: 0px;
}
.page-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0px 10px 0px 10px;
    margin-bottom: 20px;
}
.page-meta h3 {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
}
.page-buttons {
    display: flex;
}
.btn-primary {
    background-color: #3498db;
    border: none;
}
.btn-primary:hover {
    background-color: #217dbb;
}
</style>

<div class="main-content">
<div class="container-fluid">
<div class="page-card">
    <div class="page-meta">
        <div style="display:flex; gap:12px; align-items:center;">
            <h4 style="margin:0;">
                <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a>
                <i class="fa fa-angle-double-right"></i>
                User Activity Logs
                <div style="font-size:16px; line-height:1.4;">(<b style="color:green;"><?php echo $filteredCount; ?></b> <b>records</b>)</div>
            </h4>
        </div>
    </div>
</div>

<form method="GET" action="<?php echo BASE_URL; ?>user_audit_report.php" class="page-card" style="margin-top: 0px;">
    <div class="row">
        <h4>User Activity Logs</h4>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                <label>Module</label>
                <select class="form-control select2" id="module_id" name="module_id">
                    <option value="">Select Module</option>
                    <?php foreach ($modules as $mid => $mname): ?>
                        <option value="<?php echo $mid; ?>" <?php echo $selectedModule === $mid ? 'selected' : ''; ?>><?php echo $mname; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>User</label>
                <select class="form-control select2" id="user_id" name="user_id">
                    <option value="">Select User</option>
                    <?php
                    $usr = db_query("SELECT user_id, full_name, role FROM users ORDER BY full_name");
                    if ($usr) { while ($u = $usr->fetch_assoc()) { ?>
                        <option value="<?php echo (int) $u['user_id']; ?>" <?php echo $selectedUser === (int) $u['user_id'] ? 'selected' : ''; ?>><?php echo e($u['full_name']); ?> (<?php echo e($u['role']); ?>)</option>
                    <?php } } ?>
                </select>
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label>From Date</label>
                <input type="date" class="form-control" name="from_date" value="<?php echo e($fromDate); ?>">
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label>To Date</label>
                <input type="date" class="form-control" name="to_date" value="<?php echo e($toDate); ?>">
            </div>
        </div>
        <div class="col-md-2" style="padding-top: 25px;">
            <button type="submit" class="btn btn-primary">Show</button>
        </div>
    </div>
</form>

<div class="page-card" style="margin-top: 0px;">
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                <input type="text" class="form-control" placeholder="Search..." id="auditSearch">
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table id="auditLogsTable" data-page-length="100" class="table table-bordered table-striped" style="width:100%">
            <thead>
            <tr>
                <th>S.No</th>
                <th>User</th>
                <th>Action</th>
                <th>Module / Page</th>
                <th>Details</th>
                <th>IP Address</th>
                <th>Date &amp; Time</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($logs) === 0): ?>
                <tr><td colspan="7" style="text-align:center;">No records found.</td></tr>
            <?php else: ?>
                <?php $sn = 1; foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo $sn++; ?></td>
                    <td><?php echo e($log['user_name']); ?></td>
                    <td><span class="badge badge-primary"><?php echo e($log['action']); ?></span></td>
                    <td><?php echo e($log['page']); ?></td>
                    <td><?php echo e($log['details']); ?></td>
                    <td><?php echo e($log['ip']); ?></td>
                    <td><?php echo e($log['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</div>

<script src="https://cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js"></script>
<script>
    var logtable = $('#auditLogsTable').DataTable({
        "order": [],
        "pageLength": 100,
        "lengthChange": false
    });
    $('#auditSearch').on('keyup', function () {
        logtable.search($(this).val()).draw();
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>