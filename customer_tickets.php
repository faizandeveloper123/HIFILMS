<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ensure_schema.php';
require_login();

$page_title = 'Manage Ticket';

$message = '';
$error   = '';
$uid     = (int) ($_SESSION['user_id'] ?? 0);

$complaints = [
    'Create Challan', 'Challans Printing', 'Amount Reporting', 'Examination Module', 'Parents Portal',
    'Card Generator', 'DateSheet Module', 'Payroll Module', 'Student Diaries', 'Fee Reporting',
    'Students Reporting', 'WhatsApp Sms', 'Sim Sms', 'Generate Roll Number Slip', 'Class/Section',
    'LMS Module', 'Expense Module', 'Employee/HRM Module', 'Paying Student', 'Staff Access',
    'Accounts', 'Transport', 'Reports Module', 'Certificates', 'Data Effected : Classes Changed',
    'Data Effected : Challan Fee Changed', 'Data Effected : Course/Class Wise Fee Removed', 'Attendance Module', 'Dashboard',
];

$modules = [
    'Dashboard', 'Students', 'Attendance', 'Messages', 'Fee Collection', 'Examination',
    'Employees/HRM', 'PayRoll', 'Expenses', 'Front Office', 'System Settings', 'Academic Setup',
    'Parents Portal', 'Timetable', 'Cards Generator', 'Datesheet', 'Transport', 'Library',
    'Point of Sale', 'Accounts', 'Assets Management', 'Hostel Management',
];

function tkt_no(int $id): string {
    return 'TKT-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'addTicket' || $action === 'updateTicket') {
        $ticket_id  = (int) ($_POST['ticket_id'] ?? 0);
        $module     = trim($_POST['modules_id'] ?? '');
        $complain   = trim($_POST['complain_id'] ?? '');
        $priority   = trim($_POST['priority'] ?? '') ?: 'Medium';
        $ticketDate = trim($_POST['ticketDate'] ?? '');
        $subject    = trim($_POST['ticket_subject'] ?? '');
        $details    = trim($_POST['ticket_details'] ?? '');

        if ($subject === '' || $details === '') {
            $error = 'Ticket subject and description are required.';
        } else {
            $attachment = null;
            if (!empty($_FILES['img_file']['name']) && $_FILES['img_file']['error'] === UPLOAD_ERR_OK) {
                $dir = __DIR__ . '/uploads/tickets';
                if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                $ext = strtolower(pathinfo($_FILES['img_file']['name'], PATHINFO_EXTENSION));
                $attachment = 't_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                if (!move_uploaded_file($_FILES['img_file']['tmp_name'], $dir . '/' . $attachment)) { $attachment = null; }
            }

            if ($action === 'addTicket') {
                $st = db_prepare("INSERT INTO tickets (subject, module, priority, description, status, attachment, created_by)
                                  VALUES (?, ?, ?, ?, 'open', ?, ?)");
                $st->bind_param('sssssi', $subject, $complain, $priority, $details, $attachment, $uid);
                $st->execute();
                $newId = (int) $st->insert_id;
                $code  = tkt_no($newId);
                $up = db_prepare("UPDATE tickets SET ticket_no = ? WHERE id = ?");
                $up->bind_param('si', $code, $newId);
                $up->execute();
                $message = 'Ticket created (' . $code . ').';
            } else {
                $st = db_prepare("UPDATE tickets SET subject = ?, module = ?, priority = ?, description = ?, ticket_date = ? WHERE id = ?");
                $st->bind_param('ssssss', $subject, $complain, $priority, $details, $ticketDate, $ticket_id);
                $st->execute();
                if ($attachment) {
                    $st2 = db_prepare("UPDATE tickets SET attachment = ? WHERE id = ?");
                    $st2->bind_param('si', $attachment, $ticket_id);
                    $st2->execute();
                }
                $message = 'Ticket updated.';
            }
        }
    } elseif ($action === 'UpdateRating') {
        $tid    = (int) ($_POST['ticket_id'] ?? 0);
        $rating = (int) ($_POST['rating'] ?? 0);
        if ($tid > 0 && $rating >= 1 && $rating <= 3) {
            $st = db_prepare("UPDATE tickets SET rating = ? WHERE id = ?");
            $st->bind_param('ii', $rating, $tid);
            $st->execute();
        }
    } elseif ($action === 'deleteCustomerTicket') {
        $tid = (int) ($_POST['ticket_id'] ?? 0);
        if ($tid > 0) {
            $st = db_prepare("DELETE FROM tickets WHERE id = ?");
            $st->bind_param('i', $tid);
            $st->execute();
            $message = 'Ticket deleted.';
        }
    }
}

// Stats
$totalCount = 0; $openCount = 0; $highCount = 0; $weekCount = 0;
$statsRes = db_query("SELECT
    COUNT(*) AS total,
    SUM(status = 'open') AS open_,
    SUM(status = 'open' AND priority = 'High') AS high_,
    SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS week_
    FROM tickets");
if ($statsRes) { $srow = $statsRes->fetch_assoc();
    $totalCount = (int) $srow['total'];
    $openCount  = (int) $srow['open_'];
    $highCount  = (int) $srow['high_'];
    $weekCount  = (int) $srow['week_'];
}

// Tickets query
$res = db_query("SELECT t.*, u.full_name AS added_by FROM tickets t
                 LEFT JOIN users u ON u.user_id = t.created_by
                 ORDER BY t.id DESC LIMIT 500");
$tickets = [];
while ($row = $res->fetch_assoc()) { $tickets[] = $row; }

// Edit prefill
$editing = null;
$tikup = (int) ($_GET['tikup'] ?? 0);
if ($tikup > 0) {
    foreach ($tickets as $t) { if ((int) $t['id'] === $tikup) { $editing = $t; break; } }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.stats-container { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:14px; }
.stat-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; }
.stat-header { display:flex; align-items:center; justify-content:space-between; }
.stat-label { font-size:11.5px; font-weight:800; letter-spacing:.6px; color:#6b7280; }
.stat-value { font-size:26px; font-weight:800; color:#111827; line-height:1.1; margin:4px 0; }
.stat-info { font-size:12px; color:#9ca3af; }
.stat-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:19px; }
.ticket-actions { display:flex; gap:6px; justify-content:center; }
.ticket-action-btn { width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:13px; }
.ticket-action-view   { background:#3b82f622; color:#3b82f6; }
.ticket-action-edit   { background:#f59e0b22; color:#d97706; }
.ticket-action-delete { background:#ef444422; color:#dc2626; }
.rating-icon { font-size:26px; color:#d1d5db; cursor:pointer; }
.rating-icon.active { color:#169F85 !important; }
.btn-closed { background:#169F85; border-color:#169F85; color:#fff; }
.btn-open   { background:#f97316; border-color:#f97316; color:#fff; }
@media (max-width: 767px) { .rating-icon { font-size:20px; } .ticket-actions { flex-wrap:wrap; } }
@media (max-width: 480px) { .main-content { padding:10px !important; } .stat-value { font-size:22px; } }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="padding:12px 4px 6px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <div>
                <a href="<?php echo BASE_URL; ?>index.php">Dashboard</a>
                &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp; Manage Ticket
            </div>
            <button type="button" data-toggle="modal" data-target="#create_ticket" class="btn btn-round btn-info" style="color:#fff;">
                <i class="fa fa-plus"></i> Create New Ticket
            </button>
        </div>
        <br>

        <!-- Summary Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <div class="stat-label">TOTAL TICKETS</div>
                        <div class="stat-value"><?php echo $totalCount; ?></div>
                        <div class="stat-info">All time tickets</div>
                    </div>
                    <div class="stat-icon" style="background:#6366f122; color:#6366f1;"><i class="fa fa-ticket"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <div class="stat-label">OPEN TICKETS</div>
                        <div class="stat-value"><?php echo $openCount; ?></div>
                        <div class="stat-info">Pending resolution</div>
                    </div>
                    <div class="stat-icon" style="background:#f59e0b22; color:#f59e0b;"><i class="fa fa-folder-open"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <div class="stat-label">HIGH PRIORITY</div>
                        <div class="stat-value"><?php echo $highCount; ?></div>
                        <div class="stat-info">Requires attention</div>
                    </div>
                    <div class="stat-icon" style="background:#ef444422; color:#ef4444;"><i class="fa fa-exclamation-triangle"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <div class="stat-label">THIS WEEK</div>
                        <div class="stat-value"><?php echo $weekCount; ?></div>
                        <div class="stat-info">Last 7 days</div>
                    </div>
                    <div class="stat-icon" style="background:#10b98122; color:#10b981;"><i class="fa fa-calendar"></i></div>
                </div>
            </div>
        </div>

        <br>

        <!-- Create/Edit Ticket Modal -->
        <div id="create_ticket" class="modal fade" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title" id="ticketModalTitle">Create New Ticket</h4>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="customer_tickets.php" enctype="multipart/form-data" class="form-horizontal form-label-left">
                            <input type="hidden" name="action" id="ticketAction" value="addTicket">
                            <input type="hidden" name="ticket_id" id="ticketId" value="0">

                            <div class="form-group">
                                <label class="required">Module</label>
                                <select name="modules_id" id="ticketModule" class="form-control">
                                    <option value="">Select Module</option>
                                    <?php foreach ($modules as $m): ?>
                                        <option value="<?php echo e($m); ?>"><?php echo e($m); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Complain</label>
                                <select name="complain_id" id="ticketComplain" class="form-control">
                                    <option value="">Search Complain...</option>
                                    <?php foreach ($complaints as $i => $c): ?>
                                        <option value="<?php echo $i + 1; ?>"><?php echo e($c); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Priority</label>
                                <select name="priority" id="ticketPriority" class="form-control">
                                    <option value="">Select</option>
                                    <option value="High">High</option>
                                    <option value="Medium">Medium</option>
                                    <option value="Low">Low</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Date</label>
                                <input type="date" name="ticketDate" id="ticketDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label class="required">Ticket Subject</label>
                                <input class="form-control" name="ticket_subject" id="ticketSubject" maxlength="255" placeholder="Challan Printing Error...">
                            </div>
                            <div class="form-group">
                                <label class="required">Description</label>
                                <textarea id="ticketDetails" name="ticket_details" class="form-control" required placeholder="Describe the issue..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Upload Image</label>
                                <input type="file" class="form-control" name="img_file">
                            </div>
                            <button id="send" type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Ticket</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="main-content" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
                <h3 style="margin:0; font-size:16px; font-weight:800; color:#111827;">Tickets List <small>(<?php echo count($tickets); ?> records)</small></h3>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <input type="text" id="tktSearch" class="form-control" placeholder="Search tickets..." style="width:220px; max-width:100%;">
                    <select id="tktPageSize" class="form-control" style="width:auto;">
                        <option value="10">10 / page</option>
                        <option value="25">25 / page</option>
                        <option value="50">50 / page</option>
                        <option value="100">100 / page</option>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
            <table class="table table-striped table-bordered" style="background:#fff; margin:0;">
                <thead>
                    <tr>
                        <th width="7%">Ticket ID</th>
                        <th width="23%">Title</th>
                        <th width="11%">Rating</th>
                        <th width="12%">Posted</th>
                        <th width="7%">Priority</th>
                        <th width="9%">Added</th>
                        <th width="9%">Status</th>
                        <th width="9%">Action</th>
                    </tr>
                </thead>
                <tbody id="tktTbody">
                    <?php if (!$tickets): ?>
                        <tr><td colspan="8" class="text-center text-muted">No tickets found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($tickets as $t):
                        $tid = (int) $t['id'];
                        $no  = $t['ticket_no'] ?: tkt_no($tid);
                        $closed = $t['status'] === 'closed';
                    ?>
                    <tr class="tkt-row" data-search="<?php echo e(strtolower($no . ' ' . $t['subject'] . ' ' . $t['module'] . ' ' . $t['priority'] . ' ' . ($t['added_by'] ?? ''))); ?>">
                        <td style="text-align:center; font-family:monospace; font-weight:700;"><?php echo e($no); ?></td>
                        <td>
                            <?php echo e($t['subject']); ?>
                            <?php if ($t['module']): ?><div style="font-size:11.5px; color:#6b7280;"><?php echo e($t['module']); ?></div><?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php $r = (int) $t['rating']; ?>
                            <span class="rating-icon <?php echo $r === 1 ? 'active' : ''; ?>" title="Not Satisfied"><i class="fa fa-frown-o"></i></span>
                            <span class="rating-icon <?php echo $r === 2 ? 'active' : ''; ?>" title="Neutral"><i class="fa fa-meh-o"></i></span>
                            <span class="rating-icon <?php echo $r === 3 ? 'active' : ''; ?>" title="Satisfied"><i class="fa fa-smile-o"></i></span>
                            <form method="post" action="customer_tickets.php" id="ratingForm<?php echo $tid; ?>">
                                <input type="hidden" name="action" value="UpdateRating">
                                <input type="hidden" name="ticket_id" value="<?php echo $tid; ?>">
                            </form>
                        </td>
                        <td><?php echo date('d-M-Y', strtotime($t['created_at'])); ?><br><i><?php echo date('g:i A', strtotime($t['created_at'])); ?></i></td>
                        <td>
                            <?php
                                $pc = $t['priority'] === 'High' ? '#ef4444' : ($t['priority'] === 'Low' ? '#6b7280' : '#f59e0b');
                            ?>
                            <span style="color:<?php echo $pc; ?>; font-weight:700;"><?php echo e($t['priority']); ?></span>
                        </td>
                        <td><?php echo e($t['added_by'] ?? '-'); ?></td>
                        <td>
                            <span class="btn btn-sm <?php echo $closed ? 'btn-closed' : 'btn-open'; ?>" style="cursor:default; padding:2px 10px;"><?php echo $closed ? 'Closed' : 'Open'; ?></span>
                            <?php if ($closed): ?><br><i style="font-size:11px;"><?php echo date('d-M', strtotime($t['created_at'])); ?> <?php echo date('g:i A', strtotime($t['created_at'])); ?></i><?php endif; ?>
                        </td>
                        <td>
                            <div class="ticket-actions">
                                <a class="ticket-action-btn ticket-action-view" title="More Details" data-toggle="modal" data-target="#myModal<?php echo $tid; ?>"><i class="fa fa-eye"></i></a>
                                <a class="ticket-action-btn ticket-action-edit" title="Edit" href="customer_tickets.php?tikup=<?php echo $tid; ?>"><i class="fa fa-edit"></i></a>
                                <form method="post" action="customer_tickets.php" onsubmit="return confirm('Are you sure you want to delete this ticket');">
                                    <input type="hidden" name="action" value="deleteCustomerTicket">
                                    <input type="hidden" name="ticket_id" value="<?php echo $tid; ?>">
                                    <button type="submit" class="btn btn-link btn-sm ticket-action-btn ticket-action-delete p-0" title="Delete"><i class="fa fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; flex-wrap:wrap; gap:8px;">
                <div style="font-size:13px; color:#6b7280;">Page <span id="tktPgInfo">1</span></div>
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="tktPgPrev">&laquo; Prev</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="tktPgNext">Next &raquo;</button>
                </div>
            </div>
        </div>

        <?php foreach ($tickets as $t):
            $tid = (int) $t['id'];
            $no  = $t['ticket_no'] ?: tkt_no($tid);
        ?>
        <!-- View Modal -->
        <div id="myModal<?php echo $tid; ?>" class="modal fade" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Ticket ID: <?php echo e($no); ?> / <?php echo e($t['subject']); ?></h4>
                    </div>
                    <div class="modal-body">
                        <div class="row"><div class="item form-group col-md-12">
                            <label class="control-label" style="color:#6b7280;">Posted:</label>
                            <?php echo date('d-M-Y', strtotime($t['created_at'])); ?> <i><?php echo date('g:i A', strtotime($t['created_at'])); ?></i>
                        </div></div>
                        <div class="row"><div class="item form-group col-md-12">
                            <label class="control-label" style="color:#6b7280;">Module:</label>
                            <?php echo e($t['module'] ?: '-'); ?>
                        </div></div>
                        <div class="row"><div class="item form-group col-md-12">
                            <label class="control-label" style="color:#6b7280;">Priority:</label>
                            <?php echo e($t['priority']); ?>
                        </div></div>
                        <div class="row"><div class="item form-group col-md-12">
                            <label class="control-label" style="color:#6b7280;">Details:</label>
                            <?php echo nl2br(e($t['description'])); ?>
                        </div></div>
                        <div class="row"><div class="item form-group col-md-12">
                            <label class="control-label" style="color:#6b7280;">Attachment:</label>
                            <?php if ($t['attachment']): ?>
                                <a href="<?php echo BASE_URL; ?>uploads/tickets/<?php echo e($t['attachment']); ?>" target="_blank"><i class="fa fa-paperclip"></i> <?php echo e($t['attachment']); ?></a>
                            <?php else: ?>
                                No Attachment...
                            <?php endif; ?>
                        </div></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.querySelectorAll('.rating-icon').forEach(function (el) {
    el.addEventListener('click', function () {
        var td = el.parentElement;
        var icons = td.querySelectorAll('.rating-icon');
        var form = td.querySelector('form');
        var rating = 0;
        icons.forEach(function (ic, idx) { if (ic === el) rating = idx + 1; });
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'rating'; inp.value = rating;
        form.appendChild(inp);
        form.submit();
    });
});

function tktGetRows() {
    var term = (document.getElementById('tktSearch').value || '').trim().toLowerCase();
    return Array.prototype.filter.call(document.querySelectorAll('#tktTbody .tkt-row'), function (tr) {
        if (!term) return true;
        return (tr.getAttribute('data-search') || '').indexOf(term) !== -1;
    });
}
var tktCurPage = 1;
function tktApplyPagination() {
    var rows = tktGetRows();
    var size = parseInt(document.getElementById('tktPageSize').value, 10);
    var pages = Math.max(1, Math.ceil(rows.length / size));
    if (tktCurPage > pages) tktCurPage = pages;
    if (tktCurPage < 1) tktCurPage = 1;
    rows.forEach(function (tr, idx) {
        var pageNo = Math.floor(idx / size) + 1;
        tr.style.display = (pageNo === tktCurPage) ? '' : 'none';
    });
    document.getElementById('tktPgInfo').innerText = tktCurPage + ' / ' + pages;
    document.getElementById('tktPgPrev').disabled = tktCurPage <= 1;
    document.getElementById('tktPgNext').disabled = tktCurPage >= pages;
}
document.getElementById('tktSearch').addEventListener('input', function () { tktCurPage = 1; tktApplyPagination(); });
document.getElementById('tktPgPrev').addEventListener('click', function () { if (tktCurPage > 1) { tktCurPage--; tktApplyPagination(); } });
document.getElementById('tktPgNext').addEventListener('click', function () { tktCurPage++; tktApplyPagination(); });
document.getElementById('tktPageSize').addEventListener('change', function () { tktCurPage = 1; tktApplyPagination(); });
document.addEventListener('DOMContentLoaded', function () { tktApplyPagination(); });
</script>
<?php if ($editing): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('ticketModalTitle').innerText = 'Edit Ticket (<?php echo e($editing['ticket_no'] ?: tkt_no((int) $editing['id'])); ?>)';
    document.getElementById('ticketAction').value = 'updateTicket';
    document.getElementById('ticketId').value = <?php echo (int) $editing['id']; ?>;
    document.getElementById('ticketModule').value = '<?php echo e($editing['module']); ?>';
    document.getElementById('ticketComplain').value = '<?php echo e($editing['module']); ?>';
    document.getElementById('ticketPriority').value = '<?php echo e($editing['priority']); ?>';
    document.getElementById('ticketDate').value = '<?php echo date('Y-m-d', strtotime($editing['created_at'])); ?>';
    document.getElementById('ticketSubject').value = '<?php echo e($editing['subject']); ?>';
    document.getElementById('ticketDetails').value = '<?php echo e($editing['description']); ?>';
    jQuery('#create_ticket').modal('show');
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>