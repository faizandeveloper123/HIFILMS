<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ensure_schema.php';
require_login();

$page_title = 'Complaint Hub';

$message = '';
$error   = '';

$typeOptions = ['Student', 'Parent', 'Teacher', 'Administration'];

// GET numeric status -> new string statuses
$statusMap = [
    'new'        => ['New',        '#3b82f6'],
    'pending'    => ['Pending',    '#f59e0b'],
    'in-process' => ['In-Process', '#8b5cf6'],
    'resolved'   => ['Resolved',   '#10b981'],
    'closed'     => ['Closed',     '#6b7280'],
];
$statusIdToKey = ['1' => 'new', '2' => 'pending', '4' => 'in-process', '3' => 'resolved'];
$statusKeyToId = array_flip($statusIdToKey);

function cmpHue(string $s): string {
    $h = crc32(trim($s));
    $h = $h === 0 ? 1 : $h;
    $me = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4'];
    return $me[abs($h) % count($me)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = (int) $_SESSION['user_id'];

    if ($action === 'AddComplaint') {
        $type   = trim($_POST['complainantType'] ?? '') ?: 'general';
        $name   = trim($_POST['complainantName'] ?? '');
        $mobile = trim($_POST['complainantMobile'] ?? '');
        $subject = trim($_POST['complaintSubject'] ?? '');
        $desc    = trim($_POST['complaintDescription'] ?? '');
        $sendSms = ((string) ($_POST['sendSMS'] ?? 'No')) === 'Yes';

        if ($subject === '') {
            $error = 'Complaint subject is required.';
        } else {
            $st = db_prepare("INSERT INTO complaints (complaint_type, complainant_type, complainant_name, complainant_mobile, subject, description, status, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, 'new', ?)");
            $st->bind_param('ssssssi', $type, $type, $name, $mobile, $subject, $desc, $uid);
            $st->execute();
            $newId = (int) $st->insert_id;
            $code = 'CMP-' . date('Y') . '-' . str_pad((string) $newId, 4, '0', STR_PAD_LEFT);
            $up = db_prepare("UPDATE complaints SET complaint_code = ? WHERE complaint_id = ?");
            $up->bind_param('si', $code, $newId);
            $up->execute();

            $message = 'Complaint added (' . $code . ').';
            if ($sendSms && $mobile !== '') {
                $smsTitle = 'Complaint SMS - ' . $code;
                $smsBody  = 'Your complaint "' . $subject . '" has been registered (' . $code . '). Our team will follow up shortly.';
                $st2 = db_prepare("INSERT INTO messages (title, message, channel, recipient_type, recipient_list, status, message_type, created_by)
                                   VALUES (?, ?, 'whatsapp', 'complaint', ?, 'pending', 'english', ?)");
                $st2->bind_param('sssi', $smsTitle, $smsBody, $mobile, $uid);
                $st2->execute();
                $message = 'Complaint added (' . $code . ') and SMS queued.';
            }
        }
    } elseif ($action === 'UpdateStatus') {
        $id       = (int) ($_POST['complaint_id'] ?? 0);
        $statusId = (string) ($_POST['status'] ?? '1');
        $newStatus = $statusIdToKey[$statusId] ?? 'new';
        if ($id > 0) {
            $st = db_prepare("UPDATE complaints SET status = ? WHERE complaint_id = ?");
            $st->bind_param('si', $newStatus, $id);
            $st->execute();
            $message = 'Complaint status updated to ' . $newStatus . '.';
        }
    } elseif ($action === 'EditComplaint') {
        $id     = (int) ($_POST['complaint_id'] ?? 0);
        $type   = trim($_POST['complainantType'] ?? '') ?: 'general';
        $name   = trim($_POST['complainantName'] ?? '');
        $mobile = trim($_POST['complainantMobile'] ?? '');
        $subject = trim($_POST['complaintSubject'] ?? '');
        $desc    = trim($_POST['complaintDescription'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        if ($id > 0) {
            $st = db_prepare("UPDATE complaints SET complainant_type = ?, complainant_name = ?, complainant_mobile = ?, subject = ?, description = ?, remarks = ? WHERE complaint_id = ?");
            $st->bind_param('ssssssi', $type, $name, $mobile, $subject, $desc, $remarks, $id);
            $st->execute();
            $message = 'Complaint updated.';
        }
    } elseif ($action === 'DeleteComplaint') {
        $id = (int) ($_POST['complaint_id'] ?? 0);
        if ($id > 0) {
            $st = db_prepare("DELETE FROM complaints WHERE complaint_id = ?");
            $st->bind_param('i', $id);
            $st->execute();
            $message = 'Complaint deleted.';
        }
    } elseif ($action === 'SendComplaintSms') {
        $id      = (int) ($_POST['complaint_id'] ?? 0);
        $mobile  = trim($_POST['sms_mobile'] ?? '');
        $smsMsg  = trim($_POST['sms_message'] ?? '');
        if ($id > 0 && $mobile !== '' && $smsMsg !== '') {
            $title = 'Complaint SMS';
            $st = db_prepare("INSERT INTO messages (title, message, channel, recipient_type, recipient_list, status, message_type, created_by)
                              VALUES (?, ?, 'whatsapp', 'complaint', ?, 'pending', 'english', ?)");
            $st->bind_param('sssi', $title, $smsMsg, $mobile, $uid);
            $st->execute();
            $message = 'SMS queued for ' . $mobile . '.';
        }
    }
}

// Filters ---------------------------------------------------------------------
$fStatus  = (string) ($_GET['fstatus'] ?? 'All');
$fType    = (string) ($_GET['ftype'] ?? 'All');
$search   = trim($_GET['search'] ?? '');
$year     = (int) ($_GET['year'] ?? date('Y'));
$month    = (int) ($_GET['month'] ?? 0);

$where = [];
$params = [];
$types  = '';
if ($fStatus !== 'All' && isset($statusIdToKey[$fStatus])) {
    $where[] = 'c.status = ?';
    $params[] = $statusIdToKey[$fStatus];
    $types .= 's';
}
if ($fType !== 'All') {
    $where[] = 'c.complainant_type = ?';
    $params[] = $fType;
    $types .= 's';
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(c.complainant_name LIKE ? OR c.subject LIKE ? OR c.description LIKE ? OR c.complaint_code LIKE ?)';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
if ($month > 0) {
    $where[] = 'MONTH(c.created_at) = ?';
    $params[] = (string) $month;
    $types .= 'i';
}
if ($year > 0) {
    $where[] = 'YEAR(c.created_at) = ?';
    $params[] = (string) $year;
    $types .= 'i';
}

$sql = "SELECT c.*, u.full_name AS created_name
        FROM complaints c
        LEFT JOIN users u ON u.user_id = c.created_by";
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY c.complaint_id DESC LIMIT 800';

if ($params) {
    $stmt = db_prepare($sql);
    $bindVals = [$types];
    foreach ($params as $k => $v) { $bindVals[] = &$params[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $bindVals);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $sth = db_prepare($sql);
    $sth->execute();
    $res = $sth->get_result();
}
$complaints = [];
while ($row = $res->fetch_assoc()) { $complaints[] = $row; }

// Stat cards
$totals = ['total' => 0, 'new' => 0, 'pending' => 0, 'in-process' => 0, 'resolved' => 0];
$cntSql = "SELECT status, COUNT(*) AS c FROM complaints c";
if ($where) { $cntSql .= ' WHERE ' . implode(' AND ', $where); }
$cntSql .= ' GROUP BY status';
if ($params) {
    $cstmt = db_prepare($cntSql);
    $cVals = [$types];
    foreach ($params as $k => $v) { $cVals[] = &$params[$k]; }
    call_user_func_array([$cstmt, 'bind_param'], $cVals);
    $cstmt->execute();
    $cres = $cstmt->get_result();
} else {
    $csth = db_prepare($cntSql);
    $csth->execute();
    $cres = $csth->get_result();
}
while ($crow = $cres->fetch_assoc()) {
    $key = $crow['status'];
    $totals['total'] += (int) $crow['c'];
    if (isset($totals[$key])) { $totals[$key] += (int) $crow['c']; }
}

include __DIR__ . '/includes/header.php';
?>
<style>
:root { --cp-brand: #17202a; }
.cp-topbar { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:14px 4px 10px; flex-wrap:wrap; }
.cp-crumb { margin:0 0 6px; font-size:12.5px; color:#6b7280; }
.cp-crumb a { color:#6b7280; }
.cp-title-row h2 { font-size:22px; font-weight:800; color:#111827; margin:0; }
.cp-subtitle { margin:4px 0 0; color:#6b7280; font-size:13.5px; }
.cp-btn-primary { background:var(--cp-brand); border:1px solid var(--cp-brand); color:#fff !important; }
.cp-btn-primary:hover { background:#1f2937; color:#fff; }
.cp-stat-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px,1fr)); gap:12px; margin:14px 0; }
.cp-stat-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px; display:flex; gap:12px; align-items:center; color:#111827; }
.cp-stat-card:hover { box-shadow:0 4px 14px rgba(0,0,0,.06); text-decoration:none; color:#111827; }
.cp-stat-card.active { border-color:var(--cp-brand); box-shadow:0 0 0 1px var(--cp-brand); }
.cp-stat-icon { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:17px; flex:0 0 42px; }
.cp-stat-body { min-width:0; }
.cp-stat-label { font-size:12px; color:#6b7280; }
.cp-stat-value-row { display:flex; align-items:center; gap:8px; margin-top:2px; }
.cp-stat-value { font-weight:800; font-size:19px; }
.cp-stat-pct { font-size:11px; font-weight:700; padding:2px 7px; border-radius:99px; }
.cp-filter-panel { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px; margin:0 0 14px; }
.cp-filter-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:12px; }
.cp-filter-actions { display:flex; align-items:flex-end; gap:8px; }
.cp-search-box { position:relative; }
.cp-search-box i { position:absolute; left:10px; top:11px; color:#9ca3af; }
.cp-search-box input { padding-left:32px; }
.cp-table-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px; overflow-x:auto; }
.cp-table-wrap { overflow-x:auto; }
.cp-id-code { font-family:monospace; font-weight:700; color:var(--cp-brand); background:#f1f5f9; padding:3px 8px; border-radius:6px; font-size:12.5px; }
.cp-complainant { display:flex; gap:10px; align-items:center; }
.cp-avatar { width:34px; height:34px; border-radius:50%; color:#fff; font-weight:800; display:flex; align-items:center; justify-content:center; flex:0 0 34px; font-size:13px; text-transform:uppercase; }
.cp-complainant-name { font-weight:700; font-size:13.5px; color:#111827; line-height:1.2; }
.cp-complainant-sub { font-size:12px; color:#6b7280; }
.cp-type-pill, .cp-status-pill { font-size:11.5px; font-weight:700; padding:4px 10px; border-radius:99px; display:inline-block; }
.cp-action-icons { display:flex; gap:6px; flex-wrap:wrap; }
.cp-icon-btn { width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:13px; }
.cp-icon-view  { background:#3b82f622; color:#3b82f6; }
.cp-icon-sms   { background:#25d36622; color:#1da851; }
.cp-icon-edit  { background:#f59e0b22; color:#d97706; }
.cp-icon-delete{ background:#ef444422; color:#dc2626; }
@media (max-width: 767px) {
    .cp-table-card { margin-left:-4px; margin-right:-4px; }
}
@media (max-width: 480px) {
    .cp-topbar { padding: 10px 4px 8px; }
    .cp-title-row h2 { font-size: 18px; }
    .cp-stat-grid { grid-template-columns: 1fr 1fr; }
    .cp-filter-actions { align-items: stretch; }
    .cp-filter-actions .btn { flex: 1 1 100%; white-space: normal; }
    .cp-table-card { padding: 10px; font-size: 12px; }
    .cp-table-card th, .cp-table-card td { padding: 7px 5px !important; }
    .cp-icon-btn { width: 26px; height: 26px; font-size: 12px; }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="cp-topbar">
            <div>
                <p class="cp-crumb"><a href="<?php echo BASE_URL; ?>index.php">Dashboard</a> <i class="fa fa-angle-double-right"></i> Front Desk <i class="fa fa-angle-double-right"></i> Complaint Hub</p>
                <div class="cp-title-row"><h2>Complaint Hub</h2></div>
                <p class="cp-subtitle">Manage, track and resolve all complaints efficiently</p>
            </div>
            <button type="button" class="btn cp-btn-primary" data-toggle="modal" data-target="#complaintModal">
                <i class="fa fa-plus"></i> Add New Complaint
            </button>
        </div>

        <!-- Add Complaint Modal -->
        <div id="complaintModal" class="modal fade" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header" style="background:var(--cp-brand); color:#fff;">
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Add New Complaint</h4>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="manage_complaint.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="AddComplaint">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="complainantType">Complainant Type:</label>
                                        <select class="form-control" id="complainantType" name="complainantType">
                                            <?php foreach ($typeOptions as $t): ?>
                                                <option value="<?php echo $t; ?>"><?php echo $t; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="complainantName">Complainant Name:</label>
                                        <input type="text" class="form-control" id="complainantName" name="complainantName" placeholder="Enter name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="complainantMobile">Complainant Mobile:</label>
                                        <input type="text" class="form-control" id="complainantMobile" name="complainantMobile" placeholder="Enter mobile number">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="complaintSubject">Complaint Subject:</label>
                                        <input type="text" class="form-control" id="complaintSubject" name="complaintSubject" placeholder="Enter subject">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="complaintDescription">Complaint Description:</label>
                                        <textarea class="form-control" id="complaintDescription" name="complaintDescription" rows="4" placeholder="Enter complaint details"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sendSMS">Send SMS:</label>
                                        <select class="form-control" id="sendSMS" name="sendSMS">
                                            <option value="No">No</option>
                                            <option value="Yes">Yes</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" class="btn cp-btn-primary">Submit Complaint</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="cp-stat-grid">
            <a class="cp-stat-card active" href="manage_complaint.php" title="Show all complaints">
                <div class="cp-stat-icon" style="background:#6366f122; color:#6366f1;"><i class="fa fa-comments"></i></div>
                <div class="cp-stat-body">
                    <div class="cp-stat-label">Total Complaints</div>
                    <div class="cp-stat-value-row">
                        <span class="cp-stat-value"><?php echo $totals['total']; ?></span>
                        <span class="cp-stat-pct" style="background:#6366f122; color:#6366f1;">100%</span>
                    </div>
                </div>
            </a>
            <?php
            $cardMeta = [
                '1' => ['New', 'fa-inbox', '#3b82f6', 'new'],
                '2' => ['Pending', 'fa-hourglass-half', '#f59e0b', 'pending'],
                '4' => ['In-Process', 'fa-spinner', '#8b5cf6', 'in-process'],
                '3' => ['Resolved', 'fa-check-circle', '#10b981', 'resolved'],
            ];
            foreach ($cardMeta as $fid => $meta):
            ?>
            <a class="cp-stat-card" href="manage_complaint.php?fstatus=<?php echo $fid; ?>" title="Show only <?php echo $meta[0]; ?> complaints">
                <div class="cp-stat-icon" style="background:<?php echo $meta[2]; ?>22; color:<?php echo $meta[2]; ?>;"><i class="fa <?php echo $meta[1]; ?>"></i></div>
                <div class="cp-stat-body">
                    <div class="cp-stat-label"><?php echo $meta[0]; ?></div>
                    <div class="cp-stat-value-row">
                        <span class="cp-stat-value"><?php echo $totals[$meta[3]]; ?></span>
                        <span class="cp-stat-pct" style="background:<?php echo $meta[2]; ?>22; color:<?php echo $meta[2]; ?>;"><?php echo $totals['total'] > 0 ? round($totals[$meta[3]] / $totals['total'] * 100) . '%' : '0%'; ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <form action="manage_complaint.php" method="get">
            <div class="cp-filter-panel">
                <div class="cp-filter-grid">
                    <div class="form-group">
                        <label>Year</label>
                        <select name="year" class="form-control">
                            <?php for ($y = date('Y') - 3; $y <= date('Y'); $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Month</label>
                        <select name="month" class="form-control">
                            <option value="0">All Months</option>
                            <?php for ($mo = 1; $mo <= 12; $mo++): ?>
                                <option value="<?php echo $mo; ?>" <?php echo $month === $mo ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $mo, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="fstatus" class="form-control">
                            <option value="All" <?php echo $fStatus === 'All' ? 'selected' : ''; ?>>All Status</option>
                            <?php foreach ($cardMeta as $fid => $meta): ?>
                                <option value="<?php echo $fid; ?>" <?php echo $fStatus === $fid ? 'selected' : ''; ?>><?php echo $meta[0]; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="ftype" class="form-control">
                            <option value="All" <?php echo $fType === 'All' ? 'selected' : ''; ?>>All Types</option>
                            <?php foreach ($typeOptions as $t): ?>
                                <option value="<?php echo $t; ?>" <?php echo $fType === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Search</label>
                        <div class="cp-search-box">
                            <i class="fa fa-search"></i>
                            <input type="text" name="search" class="form-control" value="<?php echo e($search); ?>" placeholder="Name, Subject, or Complaint ID...">
                        </div>
                    </div>
                    <div class="form-group cp-filter-actions">
                        <button type="submit" class="btn cp-btn-primary"><i class="fa fa-filter"></i> Filters</button>
                        <a href="manage_complaint.php" class="btn btn-default"><i class="fa fa-undo"></i> Reset</a>
                    </div>
                </div>
            </div>
        </form>

        <!-- Table -->
        <div class="cp-table-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <strong style="color:#111827;">Complaint List (<?php echo count($complaints); ?>)</strong>
                <select id="cpPageSize" class="form-control" style="width:auto; display:inline-block;">
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </select>
            </div>
            <div class="cp-table-wrap">
                <table id="cpComplaintTable" class="table table-striped" style="min-width:900px; margin:0;">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th>Complaint ID</th>
                            <th>Complainant</th>
                            <th class="text-center">Type</th>
                            <th>Subject</th>
                            <th class="text-center">Date</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="cpTbody">
                        <?php if (!$complaints): ?>
                            <tr><td colspan="8" class="text-center text-muted">No complaints found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($complaints as $i => $c):
                            $key = $c['status'] ?? 'new';
                            $meta = $statusMap[$key] ?? ['Unknown', '#6b7280'];
                            $type = $c['complainant_type'] ?: ($c['complaint_type'] ?: 'general');
                            $name = $c['complainant_name'] ?: 'Unnamed';
                            $date = date('d-M-Y', strtotime($c['created_at']));
                        ?>
                        <tr class="cp-row" data-search="<?php echo e(strtolower($c['complaint_code'] . ' ' . $name . ' ' . $type . ' ' . $c['subject'] . ' ' . $c['description'])); ?>">
                            <td class="text-center"><?php echo $i + 1; ?></td>
                            <td><span class="cp-id-code"><?php echo e($c['complaint_code'] ?: 'CMP-' . date('Y') . '-' . str_pad((string) $c['complaint_id'], 4, '0', STR_PAD_LEFT)); ?></span></td>
                            <td>
                                <div class="cp-complainant">
                                    <div class="cp-avatar" style="background:<?php echo cmpHue($name); ?>;"><?php echo e(strtoupper(substr($name, 0, 1))); ?></div>
                                    <div>
                                        <div class="cp-complainant-name"><?php echo e($name); ?></div>
                                        <div class="cp-complainant-sub">(<?php echo e($type); ?>)</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center"><span class="cp-type-pill" style="background:<?php echo cmpHue($type); ?>22; color:<?php echo cmpHue($type); ?>;"><?php echo e($type); ?></span></td>
                            <td>
                                <?php echo e($c['subject']); ?>
                                <?php if ($c['remarks']): ?><div style="font-size:11.5px; color:#6b7280; margin-top:2px;"><i class="fa fa-sticky-note-o"></i> Remarks: <?php echo e(substr($c['remarks'], 0, 40)); ?><?php echo strlen($c['remarks']) > 40 ? '&hellip;' : ''; ?></div><?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo $date; ?></td>
                            <td class="text-center"><span class="cp-status-pill" style="background:<?php echo $meta[1]; ?>22; color:<?php echo $meta[1]; ?>;"><?php echo $meta[0]; ?></span></td>
                            <td class="text-center">
                                <div class="cp-action-icons" style="justify-content:center;">
                                    <a href="#" class="cp-icon-btn cp-icon-view" data-toggle="modal" data-target="#historyModal<?php echo $c['complaint_id']; ?>" title="View"><i class="fa fa-eye"></i></a>
                                    <a href="#" class="cp-icon-btn cp-icon-sms" data-toggle="modal" data-target="#smsModal<?php echo $c['complaint_id']; ?>" title="Send SMS"><i class="fa fa-envelope"></i></a>
                                    <a href="#" class="cp-icon-btn cp-icon-edit" data-toggle="modal" data-target="#editModal<?php echo $c['complaint_id']; ?>" title="Edit"><i class="fa fa-edit"></i></a>
                                    <form method="post" action="manage_complaint.php" onsubmit="return confirm('Are you sure you want to delete this record');">
                                        <input type="hidden" name="action" value="DeleteComplaint">
                                        <input type="hidden" name="complaint_id" value="<?php echo (int) $c['complaint_id']; ?>">
                                        <button type="submit" class="btn btn-link btn-sm cp-icon-btn cp-icon-delete p-0" title="Delete"><i class="fa fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
                <div style="font-size:13px; color:#6b7280;">Page <span id="cpPgInfo">1</span></div>
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cpPgPrev">&laquo; Prev</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cpPgNext">Next &raquo;</button>
                </div>
            </div>
        </div>

        <?php foreach ($complaints as $c):
            $meta = $statusMap[$c['status'] ?? 'new'] ?? ['Unknown', '#6b7280'];
            $name = $c['complainant_name'] ?: 'Unnamed';
            $type = $c['complainant_type'] ?: ($c['complaint_type'] ?: 'general');
            $cid  = (int) $c['complaint_id'];
            $code = $c['complaint_code'] ?: 'CMP-' . date('Y') . '-' . str_pad((string) $cid, 4, '0', STR_PAD_LEFT);
        ?>
        <!-- View Modal -->
        <div id="historyModal<?php echo $cid; ?>" class="modal fade" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header" style="background:var(--cp-brand); color:#fff;">
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Complaint Detail</h4>
                    </div>
                    <div class="modal-body">
                        <div style="display:flex; flex-wrap:wrap; gap:24px; margin-bottom:14px;">
                            <div><span style="color:#6b7280; font-size:12px;">Complaint ID</span><div style="font-weight:700;"><span class="cp-id-code"><?php echo e($code); ?></span></div></div>
                            <div><span style="color:#6b7280; font-size:12px;">Complainant</span><div style="font-weight:700;"><?php echo e($name); ?></div></div>
                            <div><span style="color:#6b7280; font-size:12px;">Type</span><div style="font-weight:700;"><?php echo e($type); ?></div></div>
                            <div><span style="color:#6b7280; font-size:12px;">Mobile</span><div style="font-weight:700;"><?php echo e($c['complainant_mobile'] ?: '-'); ?></div></div>
                            <div><span style="color:#6b7280; font-size:12px;">Date</span><div style="font-weight:700;"><?php echo date('j M Y', strtotime($c['created_at'])); ?></div></div>
                            <div><span style="color:#6b7280; font-size:12px;">Status</span><div><span class="cp-status-pill" style="background:<?php echo $meta[1]; ?>22; color:<?php echo $meta[1]; ?>;"><?php echo $meta[0]; ?></span></div></div>
                        </div>
                        <h5 style="font-size:14px; font-weight:800; margin:0 0 4px;"><?php echo e($c['subject']); ?></h5>
                        <p style="color:#374151;"><?php echo nl2br(e($c['description'])); ?></p>
                        <h6 style="font-weight:800;">Remarks</h6>
                        <?php if ($c['remarks']): ?>
                            <p style="color:#374151;"><?php echo nl2br(e($c['remarks'])); ?></p>
                        <?php else: ?>
                            <p style="color:#9ca3af;">No remarks yet.</p>
                        <?php endif; ?>
                        <form method="post" action="manage_complaint.php" style="display:flex; gap:8px; align-items:center; margin-top:8px;">
                            <input type="hidden" name="action" value="UpdateStatus">
                            <input type="hidden" name="complaint_id" value="<?php echo $cid; ?>">
                            <select name="status" class="form-control" style="width:auto;">
                                <?php foreach ($statusKeyToId as $k2 => $idv): ?>
                                    <option value="<?php echo $idv; ?>" <?php echo $c['status'] === $k2 ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('-', ' ', $k2)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn cp-btn-primary btn-sm">Update Status</button>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS Modal -->
        <div id="smsModal<?php echo $cid; ?>" class="modal fade" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header" style="background:var(--cp-brand); color:#fff;">
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Send SMS</h4>
                    </div>
                    <form method="post" action="manage_complaint.php">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="SendComplaintSms">
                            <input type="hidden" name="complaint_id" value="<?php echo $cid; ?>">
                            <div class="form-group">
                                <label>Mobile Number</label>
                                <input type="text" name="sms_mobile" class="form-control" value="<?php echo e($c['complainant_mobile']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Message</label>
                                <textarea name="sms_message" class="form-control" rows="4">Your complaint <?php echo $code; ?> is being processed. Thanks, School Administration.</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn cp-btn-primary"><i class="fa fa-send"></i> Send SMS</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="editModal<?php echo $cid; ?>" class="modal fade" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header" style="background:var(--cp-brand); color:#fff;">
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Edit Complaint (<?php echo e($code); ?>)</h4>
                    </div>
                    <form method="post" action="manage_complaint.php">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="EditComplaint">
                            <input type="hidden" name="complaint_id" value="<?php echo $cid; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Complainant Type</label>
                                        <select name="complainantType" class="form-control">
                                            <?php foreach ($typeOptions as $t): ?>
                                                <option value="<?php echo $t; ?>" <?php echo $type === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Complainant Name</label>
                                        <input type="text" name="complainantName" class="form-control" value="<?php echo e($name); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Complainant Mobile</label>
                                        <input type="text" name="complainantMobile" class="form-control" value="<?php echo e($c['complainant_mobile']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Subject</label>
                                        <input type="text" name="complaintSubject" class="form-control" value="<?php echo e($c['subject']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea name="complaintDescription" class="form-control" rows="4"><?php echo e($c['description']); ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Remarks</label>
                                        <textarea name="remarks" class="form-control" rows="2"><?php echo e($c['remarks']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn cp-btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function cpGetVisibleRows() {
    return Array.prototype.slice.call(document.querySelectorAll('#cpTbody .cp-row'));
}
function cpApplyPagination() {
    var rows = cpGetVisibleRows();
    var size = parseInt(document.getElementById('cpPageSize').value, 10);
    var pages = Math.max(1, Math.ceil(rows.length / size));
    var cur = 1;
    rows.forEach(function (tr, idx) {
        var pageNo = Math.floor(idx / size) + 1;
        tr.style.display = (pageNo === cur) ? '' : 'none';
    });
    document.getElementById('cpPgInfo').innerText = cur + ' / ' + pages;
}
document.getElementById('cpPageSize').addEventListener('change', function () { cpApplyPagination(); });
document.addEventListener('DOMContentLoaded', function () { cpApplyPagination(); });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>