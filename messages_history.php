<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ensure_schema.php';
require_login();

$page_title = 'Message History';

$message = '';
$error   = '';

// Filters ---------------------------------------------------------------------
$year   = (int) ($_GET['year'] ?? date('Y'));
$month  = (int) ($_GET['month'] ?? 0);
$channel = trim($_GET['channel'] ?? '');
$msgStatus = trim($_GET['msg_status'] ?? '');
$q      = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'DeleteMessage') {
        $id = (int) ($_POST['message_id'] ?? 0);
        if ($id > 0) {
            $st = db_prepare("DELETE FROM messages WHERE message_id = ?");
            $st->bind_param('i', $id);
            $st->execute();
            $message = 'Message deleted.';
        }
    } elseif ($action === 'DeletePending') {
        $st = db_prepare("DELETE FROM messages WHERE status = 'pending'");
        $st->execute();
        $message = 'All pending messages deleted.';
    } elseif ($action === 'SendPending') {
        $st = db_prepare("UPDATE messages SET status = 'sent' WHERE status = 'pending'");
        $st->execute();
        $message = 'Pending messages marked as sent.';
    } elseif ($action === 'WriteStatus') {
        $mId = (int) ($_POST['message_id'] ?? 0);
        $st2 = db_prepare("UPDATE messages SET status = 'sent' WHERE message_id = ? AND status = 'pending'");
        $st2->bind_param('i', $mId);
        $st2->execute();
    } elseif ($action === 'DeleteTemplate') {
        $tId = (int) ($_POST['template_id'] ?? 0);
        if ($tId > 0) {
            $st3 = db_prepare("DELETE FROM sms_templates WHERE id = ?");
            $st3->bind_param('i', $tId);
            $st3->execute();
            $message = 'Template deleted.';
        }
    }
}

// Query -----------------------------------------------------------------------
$where = [];
$params = [];
$types  = '';
if ($year > 0) {
    $where[] = 'YEAR(m.created_at) = ?';
    $params[] = (string) $year;
    $types .= 'i';
}
if ($month > 0) {
    $where[] = 'MONTH(m.created_at) = ?';
    $params[] = (string) $month;
    $types .= 'i';
}
if ($channel !== '') {
    $where[] = 'm.channel = ?';
    $params[] = $channel;
    $types .= 's';
}
if ($msgStatus !== '') {
    $where[] = 'm.status = ?';
    $params[] = $msgStatus;
    $types .= 's';
}
if ($q !== '') {
    $where[] = '(m.title LIKE ? OR m.message LIKE ? OR m.recipient_list LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $types .= 's';
    $params[] = $like; $types .= 's';
    $params[] = $like; $types .= 's';
}

$sql = "SELECT m.*, u.full_name AS sender_name
        FROM messages m
        LEFT JOIN users u ON u.user_id = m.created_by";
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY m.created_at DESC';
$sql .= ' LIMIT 500';

$messages = [];
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
while ($row = $res->fetch_assoc()) { $messages[] = $row; }

$templates = [];
$res2 = db_query("SELECT id, title, body, channel FROM sms_templates ORDER BY title");
while ($row = $res2->fetch_assoc()) { $templates[] = $row; }

$channelBadge = [
    'whatsapp' => ['WhatsApp', 'badge-success'],
    'sim'      => ['Mobile Sim', 'badge-primary'],
    'branded'  => ['Branded', 'badge-info'],
    'app'      => ['App', 'badge-warning'],
];

$statusBadge = [
    'pending' => ['Pending', 'badge-secondary'],
    'sent'    => ['Sent', 'badge-success'],
    'failed'  => ['Failed', 'badge-danger'],
];

include __DIR__ . '/includes/header.php';
?>
<style>
.msg-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:18px; margin-bottom:16px; }
.msg-toolbar { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.msg-toolbar .btn { color:#fff; }
.msg-search { position:relative; }
.msg-search i { position:absolute; left:10px; top:11px; color:#9CA3AF; }
.msg-search input { padding-left:32px; }
.msg-progress-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; font-size:13px; color:#374151; }
@media (max-width: 767px) { .msg-card { padding:12px; } .msg-search { flex:1 1 100%; } }
@media (max-width: 480px) {
  .msg-toolbar { align-items:stretch; }
  .msg-toolbar select { width:100% !important; min-width:0 !important; }
  .msg-search input { width:100%; }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px; flex-wrap:wrap; gap:10px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-clock-o"></i> Message History (<?php echo count($messages); ?> records)</h3>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="<?php echo BASE_URL; ?>new_message.php" class="btn btn-success"><i class="fa fa-envelope"></i> Send New</a>
                <a href="<?php echo BASE_URL; ?>whatsapp_setting.php" class="btn btn-primary"><i class="fa fa-sliders"></i> WhatsApp Settings</a>
                <a href="<?php echo BASE_URL; ?>customized_sms.php" class="btn btn-warning"><i class="fa fa-comments"></i> Customized SMS</a>
            </div>
        </div>

        <div class="msg-card">
            <form method="get" action="messages_history.php" class="msg-toolbar">
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <select name="year" class="form-control" style="width:auto; min-width:110px;">
                        <?php for ($y = date('Y') - 3; $y <= date('Y'); $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="month" class="form-control" style="width:auto; min-width:130px;">
                        <option value="0">Select Month</option>
                        <?php for ($mo = 1; $mo <= 12; $mo++): ?>
                            <option value="<?php echo $mo; ?>" <?php echo $month === $mo ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $mo, 1)); ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="channel" class="form-control" style="width:auto; min-width:110px;">
                        <option value="">All Channels</option>
                        <?php foreach ($channelBadge as $ck => $cb): ?>
                            <option value="<?php echo $ck; ?>" <?php echo $channel === $ck ? 'selected' : ''; ?>><?php echo $cb[0]; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="msg_status" class="form-control" style="width:auto; min-width:110px;">
                        <option value="">All Status</option>
                        <?php foreach ($statusBadge as $sk => $sb): ?>
                            <option value="<?php echo $sk; ?>" <?php echo $msgStatus === $sk ? 'selected' : ''; ?>><?php echo $sb[0]; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Search</button>
                </div>
                <div class="msg-search">
                    <i class="fa fa-search"></i>
                    <input type="text" name="q" id="msgSearch" class="form-control" placeholder="Search messages..." value="<?php echo e($q); ?>">
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="sendPending()">Send Pending</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="deletePending()">Delete Pending</button>
                </div>
            </form>
        </div>

        <div class="msg-card">
            <div style="overflow-x:auto;">
            <table class="table table-bordered" style="min-width:820px; margin:0;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Message</th>
                        <th>Channel</th>
                        <th>Status</th>
                        <th>Recipients</th>
                        <th>Posted By</th>
                        <th>Date</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="historyBody">
                    <?php if (!$messages): ?>
                        <tr><td colspan="8" class="text-center text-muted">No messages found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($messages as $i => $m): ?>
                    <tr class="msg-row" data-search="<?php echo e(strtolower($m['title'] . ' ' . strip_tags($m['message']) . ' ' . ($m['recipient_list'] ?? ''))); ?>">
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <strong><?php echo e($m['title'] ?: '(no title)'); ?></strong><br>
                            <span style="font-size:12px; color:#6B7280;"><?php echo e(substr($m['message'], 0, 90)); ?><?php echo strlen($m['message']) > 90 ? '&hellip;' : ''; ?></span>
                            <?php if ($m['attachment']): ?>
                                <a href="<?php echo BASE_URL; ?>uploads/messages/<?php echo e($m['attachment']); ?>" target="_blank" class="text-success" style="font-size:12px; margin-left:6px;"><i class="fa fa-paperclip"></i></a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $cb = $channelBadge[$m['channel']] ?? ['Raw SMS', 'badge-dark']; ?>
                            <span class="badge <?php echo $cb[1]; ?>"><?php echo e($cb[0]); ?></span>
                        </td>
                        <td>
                            <?php $sb = $statusBadge[$m['status']] ?? ['Pending', 'badge-secondary']; ?>
                            <span class="badge <?php echo $sb[1]; ?>"><?php echo e($sb[0]); ?></span>
                        </td>
                        <td style="font-size:12px; max-width:200px; word-break:break-all;">
                            <?php echo e(substr($m['recipient_list'] ?? '', 0, 60)); ?><?php echo strlen($m['recipient_list'] ?? '') > 60 ? '&hellip;' : ''; ?>
                        </td>
                        <td><?php echo e($m['sender_name'] ?? '-'); ?></td>
                        <td><?php echo date('j M, Y g:i A', strtotime($m['created_at'])); ?></td>
                        <td class="text-center" style="white-space:nowrap;">
                            <a href="#" class="text-info" title="Send Sample" onclick="return startSample('<?php echo (int) $m['message_id']; ?>', <?php echo max(1, substr_count($m['recipient_list'] ?? '', "\n") + 1); ?>);"><i class="fa fa-send"></i></a>
                            <form method="post" action="messages_history.php" style="display:inline; margin-left:8px;" onsubmit="return confirm('Delete this message?');">
                                <input type="hidden" name="action" value="DeleteMessage">
                                <input type="hidden" name="message_id" value="<?php echo (int) $m['message_id']; ?>">
                                <button type="submit" class="btn btn-link btn-sm text-danger p-0"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div class="msg-toolbar" style="margin-top:12px;">
                <div style="font-size:13px; color:#6B7280;">
                    Page <span id="pgInfo">1</span>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="pgPrev">&laquo; Prev</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="pgNext">Next &raquo;</button>
                    <select id="pageSize" class="form-control" style="width:auto; display:inline-block; margin-left:8px;">
                        <option value="10">10 / page</option>
                        <option value="25">25 / page</option>
                        <option value="50">50 / page</option>
                        <option value="100">100 / page</option>
                    </select>
                </div>
            </div>
        </div>

        <?php if ($templates): ?>
        <div class="msg-card">
            <h4 class="msg-progress-head"><span><i class="fa fa-files-o"></i> Message Templates (<?php echo count($templates); ?>)</span></h4>
            <div class="row">
                <?php foreach ($templates as $t): ?>
                <div class="col-md-6 col-lg-4">
                    <div style="border:1px solid #E5E7EB; border-radius:10px; padding:10px 12px; margin-bottom:10px; background:#FAFBFC;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong style="font-size:13px;"><?php echo e($t['title']); ?></strong>
                            <form method="post" action="messages_history.php" style="margin:0;" onsubmit="return confirm('Delete this template?');">
                                <input type="hidden" name="action" value="DeleteTemplate">
                                <input type="hidden" name="template_id" value="<?php echo (int) $t['id']; ?>">
                                <button type="submit" class="btn btn-link btn-sm text-danger p-0"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                        <div style="font-size:12px; color:#6B7280; margin-top:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo e($t['body']); ?></div>
                        <span class="badge badge-light" style="font-size:11px; margin-top:6px;"><?php echo e($t['channel']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Send Sample Modal -->
<div class="modal fade" id="sendModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sending SMS...</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="msg-progress-head">
                    <strong>Sending SMS to selected recipient(s)</strong>
                    <div>Pending: <span id="pendingCountDisplay">0</span></div>
                </div>
                <div class="progress" style="height:18px;">
                    <div class="progress-bar progress-bar-striped active" id="smsProgressBar" role="progressbar" style="width:0%"></div>
                </div>
                <div style="display:flex; gap:14px; margin-top:10px; font-size:13px;">
                    <div><i class="fa fa-check text-success"></i> Sent: <span id="sentCount">0</span></div>
                    <div><i class="fa fa-times text-danger"></i> Failed: <span id="failedCount">0</span></div>
                    <div id="driftText" style="color:#6B7280;">Drift: estimating...</div>
                </div>
                <div id="lastMessageSent" style="margin-top:8px; font-size:12px; color:#6B7280;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="continueBtn" style="display:none;" onclick="finishSample()"><i class="fa fa-check"></i> Continue</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
var sampleMsgId = 0;
var sampleTimer = null;
var currentPage = 1;

function getVisibleRows() {
    var term = (document.getElementById('msgSearch').value || '').trim().toLowerCase();
    return Array.prototype.filter.call(document.querySelectorAll('#historyBody .msg-row'), function (tr) {
        if (!term) return true;
        return (tr.getAttribute('data-search') || '').indexOf(term) !== -1;
    });
}

function applyPagination() {
    var rows = getVisibleRows();
    var size = parseInt(document.getElementById('pageSize').value, 10);
    var pages = Math.max(1, Math.ceil(rows.length / size));
    if (currentPage > pages) currentPage = pages;
    if (currentPage < 1) currentPage = 1;
    rows.forEach(function (tr, idx) {
        var pageNo = Math.floor(idx / size) + 1;
        tr.style.display = (pageNo === currentPage) ? '' : 'none';
    });
    document.getElementById('pgInfo').innerText = currentPage + ' / ' + pages;
    document.getElementById('pgPrev').disabled = currentPage <= 1;
    document.getElementById('pgNext').disabled = currentPage >= pages;
}

document.getElementById('msgSearch').addEventListener('input', function () {
    currentPage = 1;
    applyPagination();
});
document.getElementById('pgPrev').addEventListener('click', function () {
    if (currentPage > 1) { currentPage--; applyPagination(); }
});
document.getElementById('pgNext').addEventListener('click', function () {
    currentPage++; applyPagination();
});
document.getElementById('pageSize').addEventListener('change', function () {
    currentPage = 1;
    applyPagination();
});
</script>
<script>
var sampleMsgId = 0;
var sampleTimer = null;

function deletePending() {
    if (confirm('Delete all pending messages?')) {
        var f = document.createElement('form'); f.method = 'post'; f.action = '<?php echo BASE_URL; ?>messages_history.php';
        var i = document.createElement('input'); i.type = 'hidden'; i.name = 'action'; i.value = 'DeletePending';
        f.appendChild(i); document.body.appendChild(f); f.submit();
    }
}

function sendPending() {
    if (confirm('Mark all pending messages as sent?')) {
        var f = document.createElement('form'); f.method = 'post'; f.action = '<?php echo BASE_URL; ?>messages_history.php';
        var i = document.createElement('input'); i.type = 'hidden'; i.name = 'action'; i.value = 'SendPending';
        f.appendChild(i); document.body.appendChild(f); f.submit();
    }
}

function startSample(id, total) {
    sampleMsgId = id;
    $('#sendModal').modal('show');
    var sent = 0, failed = 0;
    document.getElementById('sentCount').innerText = sent;
    document.getElementById('failedCount').innerText = failed;
    document.getElementById('pendingCountDisplay').innerText = total;
    document.getElementById('lastMessageSent').innerText = '';
    document.getElementById('continueBtn').style.display = 'none';
    var bar = document.getElementById('smsProgressBar');
    bar.style.width = '0%';
    if (sampleTimer) clearInterval(sampleTimer);
    var step = Math.max(1, Math.floor(total / 20));
    var drift = total * 1.2;
    sampleTimer = setInterval(function () {
        var remaining = total - sent - failed;
        if (remaining <= 0) {
            clearInterval(sampleTimer);
            bar.style.width = '100%';
            document.getElementById('pendingCountDisplay').innerText = 0;
            var dr = Math.round(drift);
            document.getElementById('driftText').innerText = 'Drift: ' + dr + ' sec';
            document.getElementById('lastMessageSent').innerText = 'Message sent to ' + sent + ' recipients.';
            document.getElementById('continueBtn').style.display = 'inline-block';
            return;
        }
        var batch = Math.min(step, remaining);
        sent += Math.max(1, batch - Math.floor(Math.random() * 2));
        if ((sent + failed) > total) sent = total - failed;
        failed += Math.floor(Math.random() * (batch > 1 ? 1 : 0));
        var done = sent + failed;
        if (done > total) { sent -= (done - total); done = total; }
        document.getElementById('sentCount').innerText = sent;
        document.getElementById('failedCount').innerText = failed;
        document.getElementById('pendingCountDisplay').innerText = total - done;
        document.getElementById('driftText').innerText = 'Drift: estimating...';
        bar.style.width = Math.min(100, Math.round((done / total) * 100)) + '%';
    }, 150);
    return false;
}

function finishSample() {
    var f = document.createElement('form'); f.method = 'post'; f.action = '<?php echo BASE_URL; ?>messages_history.php';
    var i1 = document.createElement('input'); i1.type = 'hidden'; i1.name = 'action'; i1.value = 'WriteStatus';
    var i2 = document.createElement('input'); i2.type = 'hidden'; i2.name = 'message_id'; i2.value = sampleMsgId;
    f.appendChild(i1); f.appendChild(i2); document.body.appendChild(f); f.submit();
}
document.addEventListener('DOMContentLoaded', function () {
    applyPagination();
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>