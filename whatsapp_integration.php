<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ensure_schema.php';
require_login();

$page_title = 'WhatsApp Integration';

$message = '';
$error   = '';

// Ensure whatsapp_logs table exists
_hiifi_try_db("CREATE TABLE IF NOT EXISTS whatsapp_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(30) DEFAULT 'general',
    status VARCHAR(20) DEFAULT 'queued',
    student_id INT DEFAULT NULL,
    parent_id INT DEFAULT NULL,
    class_id INT DEFAULT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$classes = [];
$res = db_query("SELECT class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$templates = [];
$res = db_query("SELECT id, title, body FROM sms_templates WHERE status=1 ORDER BY title");
while ($row = $res->fetch_assoc()) { $templates[] = $row; }

$type_labels = [
    'attendance' => ['label' => 'Attendance', 'color' => '#2563EB', 'icon' => 'fa-check-circle'],
    'fee'        => ['label' => 'Fee',        'color' => '#D97706', 'icon' => 'fa-money'],
    'result'     => ['label' => 'Result',     'color' => '#059669', 'icon' => 'fa-graduation-cap'],
    'homework'   => ['label' => 'Homework',   'color' => '#7C3AED', 'icon' => 'fa-book'],
    'general'    => ['label' => 'General',    'color' => '#6B7280', 'icon' => 'fa-comment'],
];

$status_badge = [
    'queued'    => 'background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;',
    'sent'      => 'background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7;',
    'delivered' => 'background:#DBEAFE;color:#1E40AF;border:1px solid #93C5FD;',
    'failed'    => 'background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;',
];

// Date filter
$filter_from = $_GET['from'] ?? '';
$filter_to   = $_GET['to'] ?? '';
$filter_type = $_GET['type'] ?? '';
$search      = $_GET['search'] ?? '';

// Handle send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'SendWhatsApp') {
    $phone    = trim($_POST['phone'] ?? '');
    $msg      = trim($_POST['message'] ?? '');
    $type     = trim($_POST['msg_type'] ?? 'general');
    $cls_id   = (int) ($_POST['class_id'] ?? 0);
    $bulk     = (int) ($_POST['bulk_send'] ?? 0);

    if ($msg === '') {
        $error = 'Message is required.';
    } elseif ($bulk && $cls_id > 0) {
        // Bulk send to all parents in the class
        $class_name_res = db_prepare("SELECT class_name FROM classes WHERE class_id = ?");
        $class_name_res->bind_param('i', $cls_id);
        $class_name_res->execute();
        $class_name = $class_name_res->get_result()->fetch_assoc()['class_name'] ?? 'the class';

        $parents_sql = "SELECT DISTINCT s.father_cellno AS phone, s.student_id, s.father_name, s.first_name
                         FROM students s
                         WHERE s.class_id = ? AND s.status = 1 AND s.father_cellno IS NOT NULL AND s.father_cellno != ''";
        $st = db_prepare($parents_sql);
        $st->bind_param('i', $cls_id);
        $st->execute();
        $res = $st->get_result();
        $count = 0;
        while ($row = $res->fetch_assoc()) {
            $final_msg = str_replace(
                ['{student_name}', '{father_name}', '{class}'],
                [$row['first_name'], $row['father_name'] ?? '', $class_name],
                $msg
            );
            $ins = db_prepare("INSERT INTO whatsapp_logs (phone, message, type, status, student_id, class_id) VALUES (?, ?, ?, 'queued', ?, ?)");
            $ins->bind_param('sssii', $row['phone'], $final_msg, $type, $row['student_id'], $cls_id);
            $ins->execute();
            $count++;
        }
        $message = "Queued $count WhatsApp messages for " . e($class_name) . ". (Bulk)";
    } elseif ($phone !== '') {
        $ins = db_prepare("INSERT INTO whatsapp_logs (phone, message, type, status) VALUES (?, ?, ?, 'queued')");
        $ins->bind_param('sss', $phone, $msg, $type);
        $ins->execute();
        $message = "WhatsApp message queued to $phone.";
    } else {
        $error = 'Phone number is required for single send.';
    }
}

// Build log query
$log_where = "1=1";
$log_params = [];
$log_types = '';
if ($filter_from !== '') { $log_where .= " AND sent_at >= ?"; $log_params[] = $filter_from . ' 00:00:00'; $log_types .= 's'; }
if ($filter_to !== '')   { $log_where .= " AND sent_at <= ?"; $log_params[] = $filter_to . ' 23:59:59'; $log_types .= 's'; }
if ($filter_type !== '') { $log_where .= " AND w.type = ?"; $log_params[] = $filter_type; $log_types .= 's'; }
if ($search !== '')      { $log_where .= " AND (w.phone LIKE ? OR w.message LIKE ?)"; $log_params[] = "%$search%"; $log_params[] = "%$search%"; $log_types .= 'ss'; }

$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$count_sql = "SELECT COUNT(*) c FROM whatsapp_logs w WHERE $log_where";
$st = db_prepare($count_sql);
if ($log_params) { $st->bind_param($log_types, ...$log_params); }
$st->execute();
$total = (int) $st->get_result()->fetch_assoc()['c'];
$total_pages = max(1, ceil($total / $per_page));

$log_sql = "SELECT w.*, s.first_name, s.father_name, c.class_name
             FROM whatsapp_logs w
             LEFT JOIN students s ON s.student_id = w.student_id
             LEFT JOIN classes c ON c.class_id = w.class_id
             WHERE $log_where ORDER BY w.sent_at DESC LIMIT $per_page OFFSET $offset";
$st2 = db_prepare($log_sql);
if ($log_params) { $st2->bind_param($log_types, ...$log_params); }
$st2->execute();
$logs = $st2->get_result();

include __DIR__ . '/includes/header.php';
?>
<style>
.wa-panel{background:#fff;border:1px solid #E5E7EB;border-radius:16px;padding:22px;margin-bottom:16px;}
.wa-panel h4{margin:0 0 14px;font-weight:800;font-size:15px;color:#111827;}
.log-card{background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:14px;margin-bottom:8px;display:flex;gap:12px;align-items:flex-start;}
.log-card .log-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;flex-shrink:0;}
.log-card .log-body{flex:1;min-width:0;}
.log-card .log-phone{font-weight:700;color:#111827;font-size:13.5px;}
.log-card .log-msg{color:#4B5563;font-size:13px;margin-top:3px;word-break:break-word;}
.log-card .log-meta{color:#9CA3AF;font-size:11.5px;margin-top:5px;display:flex;gap:10px;flex-wrap:wrap;}
.preview-box{background:#DCF8C6;border-radius:12px;padding:14px 18px;max-width:380px;font-size:14px;color:#111827;line-height:1.5;white-space:pre-wrap;box-shadow:0 1px 2px rgba(0,0,0,0.1);position:relative;margin:12px auto;}
.preview-box::before{content:'';position:absolute;top:0;left:-6px;border:6px solid transparent;border-top-color:#DCF8C6;border-right-color:#DCF8C6;}
.chip-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:20px;border:1px solid #E5E7EB;background:#fff;color:#374151;font-size:12.5px;cursor:pointer;transition:all 0.2s;}
.chip-btn:hover,.chip-btn.active{background:#f97316;color:#fff;border-color:#f97316;}
</style>
<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 4px;">
            <h3 style="font-size:18px;font-weight:800;color:#111827;margin:0;"><i class="fab fa-whatsapp" style="color:#25D366;"></i> WhatsApp Integration</h3>
            <a href="<?php echo BASE_URL; ?>whatsapp_setting.php" class="btn btn-default" style="border-radius:10px;"><i class="fa fa-cog"></i> API Settings</a>
        </div>

        <div class="row">
            <!-- Send Form -->
            <div class="col-md-5">
                <div class="wa-panel">
                    <h4><i class="fa fa-paper-plane" style="color:#25D366;"></i> Send WhatsApp Message</h4>
                    <form method="post" action="whatsapp_integration.php" id="waForm">
                        <input type="hidden" name="action" value="SendWhatsApp">
                        <input type="hidden" name="bulk_send" id="bulk_send" value="0">

                        <div class="form-group">
                            <label style="font-weight:600;color:#374151;">Send Type</label>
                            <div style="display:flex;gap:8px;margin-top:4px;">
                                <label class="chip-btn active" id="singleBtn" onclick="setSendMode(0)"><i class="fa fa-user"></i> Single</label>
                                <label class="chip-btn" id="bulkBtn" onclick="setSendMode(1)"><i class="fa fa-users"></i> Bulk (Class)</label>
                            </div>
                        </div>

                        <div id="singleFields">
                            <div class="form-group">
                                <label style="font-weight:600;color:#374151;">Phone Number</label>
                                <input type="text" name="phone" id="waPhone" class="form-control" placeholder="923001234567">
                            </div>
                        </div>

                        <div id="bulkFields" style="display:none;">
                            <div class="form-group">
                                <label style="font-weight:600;color:#374151;">Select Class</label>
                                <select name="class_id" id="waClass" class="form-control">
                                    <option value="">Choose Class</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="font-weight:600;color:#374151;">Message Type</label>
                            <select name="msg_type" class="form-control">
                                <?php foreach ($type_labels as $k => $v): ?>
                                    <option value="<?php echo $k; ?>"><?php echo e($v['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="font-weight:600;color:#374151;">Template</label>
                            <select id="tplSelect" class="form-control" onchange="applyWATemplate(this.value)">
                                <option value="">Choose Template (optional)</option>
                                <?php foreach ($templates as $t): ?>
                                    <option value="<?php echo e($t['body']); ?>"><?php echo e($t['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="font-weight:600;color:#374151;">Message <span style="color:#9CA3AF;font-weight:400;font-size:12px;">Vars: {student_name} {father_name} {class} {amount} {due_date}</span></label>
                            <textarea name="message" id="waMessage" rows="5" class="form-control" placeholder="Type your WhatsApp message..." oninput="updatePreview()"></textarea>
                        </div>

                        <button type="submit" class="btn btn-success" style="width:100%;padding:11px;border-radius:12px;font-weight:700;">
                            <i class="fab fa-whatsapp"></i> Queue Message
                        </button>
                    </form>
                </div>

                <!-- Preview -->
                <div class="wa-panel" id="previewPanel" style="display:none;">
                    <h4><i class="fa fa-eye"></i> Message Preview</h4>
                    <div class="preview-box" id="previewBox">Your message will appear here...</div>
                    <p style="text-align:center;color:#9CA3AF;font-size:12px;margin-top:8px;">This is how the message will look on WhatsApp</p>
                </div>
            </div>

            <!-- Log Table -->
            <div class="col-md-7">
                <div class="wa-panel">
                    <h4><i class="fa fa-list"></i> Message Log <span style="font-weight:400;color:#9CA3AF;font-size:13px;">(<?php echo number_format($total); ?> messages)</span></h4>

                    <!-- Filters -->
                    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;align-items:flex-end;">
                        <div style="flex:1;min-width:120px;">
                            <label style="font-size:11px;font-weight:600;color:#6B7280;">FROM</label>
                            <input type="date" name="from" class="form-control" value="<?php echo e($filter_from); ?>" style="font-size:12.5px;height:34px;">
                        </div>
                        <div style="flex:1;min-width:120px;">
                            <label style="font-size:11px;font-weight:600;color:#6B7280;">TO</label>
                            <input type="date" name="to" class="form-control" value="<?php echo e($filter_to); ?>" style="font-size:12.5px;height:34px;">
                        </div>
                        <div style="flex:1;min-width:120px;">
                            <label style="font-size:11px;font-weight:600;color:#6B7280;">TYPE</label>
                            <select name="type" class="form-control" style="font-size:12.5px;height:34px;">
                                <option value="">All Types</option>
                                <?php foreach ($type_labels as $k => $v): ?>
                                    <option value="<?php echo $k; ?>" <?php echo $filter_type === $k ? 'selected' : ''; ?>><?php echo e($v['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex:1;min-width:120px;">
                            <label style="font-size:11px;font-weight:600;color:#6B7280;">SEARCH</label>
                            <input type="text" name="search" class="form-control" value="<?php echo e($search); ?>" placeholder="Phone or message..." style="font-size:12.5px;height:34px;">
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm" style="border-radius:8px;height:34px;"><i class="fa fa-filter"></i> Filter</button>
                        <a href="whatsapp_integration.php" class="btn btn-default btn-sm" style="border-radius:8px;height:34px;"><i class="fa fa-times"></i></a>
                    </form>

                    <?php if ($logs->num_rows === 0): ?>
                        <div style="text-align:center;padding:30px;color:#9CA3AF;">
                            <i class="fab fa-whatsapp" style="font-size:40px;color:#D1D5DB;"></i>
                            <p style="margin-top:10px;">No WhatsApp messages found.</p>
                        </div>
                    <?php endif; ?>

                    <?php while ($log = $logs->fetch_assoc()):
                        $tl = $type_labels[$log['type']] ?? $type_labels['general'];
                        $sb = $status_badge[$log['status']] ?? $status_badge['queued'];
                    ?>
                        <div class="log-card">
                            <div class="log-icon" style="background:<?php echo $tl['color']; ?>;">
                                <i class="fa <?php echo $tl['icon']; ?>"></i>
                            </div>
                            <div class="log-body">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <span class="log-phone"><?php echo e($log['phone']); ?></span>
                                    <span style="padding:2px 8px;border-radius:99px;font-size:10.5px;font-weight:700;<?php echo $sb; ?>"><?php echo strtoupper($log['status']); ?></span>
                                </div>
                                <div class="log-msg"><?php echo e(mb_strimwidth($log['message'], 0, 120, '...')); ?></div>
                                <div class="log-meta">
                                    <span><i class="fa <?php echo $tl['icon']; ?>"></i> <?php echo e($tl['label']); ?></span>
                                    <?php if ($log['first_name']): ?>
                                        <span><i class="fa fa-user"></i> <?php echo e($log['first_name']); ?> <?php echo e($log['father_name']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($log['class_name']): ?>
                                        <span><i class="fa fa-graduation-cap"></i> <?php echo e($log['class_name']); ?></span>
                                    <?php endif; ?>
                                    <span><i class="fa fa-clock-o"></i> <?php echo date('d M Y, h:i A', strtotime($log['sent_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>

                    <?php if ($total_pages > 1): ?>
                        <div style="display:flex;justify-content:center;gap:4px;margin-top:14px;">
                            <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++):
                                $q = $_GET; $q['page'] = $p;
                            ?>
                                <a href="?<?php echo http_build_query($q); ?>" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;<?php echo $p === $page ? 'background:#f97316;color:#fff;' : 'background:#F3F4F6;color:#374151;border:1px solid #E5E7EB;'; ?>"><?php echo $p; ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setSendMode(mode) {
    document.getElementById('bulk_send').value = mode;
    document.getElementById('singleFields').style.display = mode === 0 ? 'block' : 'none';
    document.getElementById('bulkFields').style.display = mode === 1 ? 'block' : 'none';
    document.getElementById('singleBtn').classList.toggle('active', mode === 0);
    document.getElementById('bulkBtn').classList.toggle('active', mode === 1);
}
function applyWATemplate(val) {
    if (val) {
        document.getElementById('waMessage').value = val;
        updatePreview();
    }
}
function updatePreview() {
    var msg = document.getElementById('waMessage').value;
    var panel = document.getElementById('previewPanel');
    var box = document.getElementById('previewBox');
    if (msg.trim()) {
        panel.style.display = 'block';
        var sample = msg.replace(/\{student_name\}/g, 'Ahmad Ali')
                        .replace(/\{father_name\}/g, 'Muhammad Ali')
                        .replace(/\{class\}/g, '5TH')
                        .replace(/\{amount\}/g, '15,000')
                        .replace(/\{due_date\}/g, '15 Sep 2026');
        box.textContent = sample;
    } else {
        panel.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
