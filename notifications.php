<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ensure_schema.php';
require_login();

$page_title = 'Notifications';

$user_id = $_SESSION['user_id'];

// Ensure notifications table exists
_hiifi_try_db("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type VARCHAR(30) DEFAULT 'general',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Auto-delete notifications older than 90 days
_hiifi_try_db("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");

$message = '';
$error   = '';

// Handle create notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'CreateNotification') {
    $target_user = trim($_POST['target_user'] ?? '');
    $title       = trim($_POST['notif_title'] ?? '');
    $msg_body    = trim($_POST['notif_message'] ?? '');
    $type        = trim($_POST['notif_type'] ?? 'general');
    $link        = trim($_POST['notif_link'] ?? '');

    if ($title === '' || $msg_body === '') {
        $error = 'Title and Message are required.';
    } else {
        if ($target_user === '' || $target_user === 'all') {
            $ins = db_prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (NULL, ?, ?, ?, ?)");
            $ins->bind_param('ssss', $title, $msg_body, $type, $link);
            $ins->execute();
            $message = 'Notification sent to all users.';
        } else {
            $target_user = (int) $target_user;
            $ins = db_prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param('issss', $target_user, $title, $msg_body, $type, $link);
            $ins->execute();
            $message = 'Notification sent to user #' . $target_user . '.';
        }
    }
}

// Mark single as read/unread
if (isset($_GET['toggle_read'])) {
    $nid = (int) $_GET['toggle_read'];
    $st = db_prepare("UPDATE notifications SET is_read = 1 - is_read WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
    $st->bind_param('ii', $nid, $user_id);
    $st->execute();
    $qparams = array_filter($_GET, fn($k) => $k !== 'toggle_read', ARRAY_FILTER_USE_KEY);
    header('Location: notifications.php' . ($qparams ? '?' . http_build_query($qparams) : ''));
    exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $st = db_prepare("UPDATE notifications SET is_read = 1 WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
    $st->bind_param('i', $user_id);
    $st->execute();
    $message = 'All notifications marked as read.';
}

// Filters
$filter_type   = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search        = $_GET['search'] ?? '';

$notif_where = "(n.user_id = ? OR n.user_id IS NULL)";
$notif_params = [$user_id];
$notif_types  = 'i';

if ($filter_type !== '')   { $notif_where .= " AND n.type = ?"; $notif_params[] = $filter_type; $notif_types .= 's'; }
if ($filter_status === 'read')     { $notif_where .= " AND n.is_read = 1"; }
if ($filter_status === 'unread')   { $notif_where .= " AND n.is_read = 0"; }
if ($search !== '')        { $notif_where .= " AND (n.title LIKE ? OR n.message LIKE ?)"; $notif_params[] = "%$search%"; $notif_params[] = "%$search%"; $notif_types .= 'ss'; }

$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$count_sql = "SELECT COUNT(*) c FROM notifications n WHERE $notif_where";
$st = db_prepare($count_sql);
$st->bind_param($notif_types, ...$notif_params);
$st->execute();
$total = (int) $st->get_result()->fetch_assoc()['c'];
$total_pages = max(1, ceil($total / $per_page));

$notifs = [];
$st2 = db_prepare("SELECT n.* FROM notifications n WHERE $notif_where ORDER BY n.is_read ASC, n.created_at DESC LIMIT $per_page OFFSET $offset");
$st2->bind_param($notif_types, ...$notif_params);
$st2->execute();
$res = $st2->get_result();
while ($row = $res->fetch_assoc()) { $notifs[] = $row; }

$unread_count = 0;
$uc_res = db_prepare("SELECT COUNT(*) c FROM notifications n WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
$uc_res->bind_param('i', $user_id);
$uc_res->execute();
$unread_count = (int) $uc_res->get_result()->fetch_assoc()['c'];

$type_colors = [
    'fee'        => ['color' => '#D97706', 'bg' => '#FEF3C7', 'icon' => 'fa-money'],
    'attendance' => ['color' => '#2563EB', 'bg' => '#DBEAFE', 'icon' => 'fa-check-circle'],
    'exam'       => ['color' => '#7C3AED', 'bg' => '#EDE9FE', 'icon' => 'fa-graduation-cap'],
    'homework'   => ['color' => '#059669', 'bg' => '#D1FAE5', 'icon' => 'fa-book'],
    'general'    => ['color' => '#6B7280', 'bg' => '#F3F4F6', 'icon' => 'fa-bell'],
    'system'     => ['color' => '#DC2626', 'bg' => '#FEE2E2', 'icon' => 'fa-cog'],
];

include __DIR__ . '/includes/header.php';
?>
<style>
.notif-card{background:#fff;border:1px solid #E5E7EB;border-radius:14px;padding:16px;margin-bottom:10px;display:flex;gap:14px;align-items:flex-start;transition:all 0.2s;}
.notif-card:hover{box-shadow:0 4px 12px rgba(0,0,0,0.06);}
.notif-card.unread{border-left:4px solid #f97316;background:#FFFBF5;}
.notif-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.notif-body{flex:1;min-width:0;}
.notif-title{font-weight:700;color:#111827;font-size:14px;margin-bottom:3px;}
.notif-msg{color:#4B5563;font-size:13px;line-height:1.5;}
.notif-meta{color:#9CA3AF;font-size:11.5px;margin-top:6px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
.empty-state{text-align:center;padding:50px 20px;color:#9CA3AF;}
.empty-state i{font-size:50px;color:#D1D5DB;}
.stats-row{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
.stat-chip{background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:10px 16px;display:flex;align-items:center;gap:8px;font-size:13px;}
.stat-chip .num{font-weight:800;font-size:18px;}
</style>
<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 4px;flex-wrap:wrap;gap:10px;">
            <h3 style="font-size:18px;font-weight:800;color:#111827;margin:0;"><i class="fa fa-bell"></i> Notifications <span style="font-weight:400;color:#9CA3AF;font-size:14px;">(<?php echo number_format($total); ?>)</span></h3>
            <div style="display:flex;gap:8px;">
                <?php if ($unread_count > 0): ?>
                    <a href="?mark_all_read=1<?php echo $filter_type ? '&type=' . urlencode($filter_type) : ''; ?>" class="btn btn-warning btn-sm" style="border-radius:10px;"><i class="fa fa-check-double"></i> Mark All Read</a>
                <?php endif; ?>
                <button class="btn btn-primary btn-sm" style="border-radius:10px;" onclick="document.getElementById('createNotifModal').style.display='flex'"><i class="fa fa-plus"></i> New Notification</button>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-chip"><span class="num" style="color:#111827;"><?php echo number_format($total); ?></span> Total</div>
            <div class="stat-chip" style="border-color:#F97316;"><span class="num" style="color:#F97316;"><?php echo number_format($unread_count); ?></span> Unread</div>
            <div class="stat-chip" style="border-color:#059669;"><span class="num" style="color:#059669;"><?php echo number_format($total - $unread_count); ?></span> Read</div>
        </div>

        <!-- Filters -->
        <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:flex-end;">
            <div style="flex:1;min-width:130px;">
                <label style="font-size:11px;font-weight:600;color:#6B7280;">TYPE</label>
                <select name="type" class="form-control" style="font-size:12.5px;height:34px;">
                    <option value="">All Types</option>
                    <?php foreach (array_keys($type_colors) as $t): ?>
                        <option value="<?php echo $t; ?>" <?php echo $filter_type === $t ? 'selected' : ''; ?>><?php echo ucfirst($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1;min-width:120px;">
                <label style="font-size:11px;font-weight:600;color:#6B7280;">STATUS</label>
                <select name="status" class="form-control" style="font-size:12.5px;height:34px;">
                    <option value="">All</option>
                    <option value="unread" <?php echo $filter_status === 'unread' ? 'selected' : ''; ?>>Unread</option>
                    <option value="read" <?php echo $filter_status === 'read' ? 'selected' : ''; ?>>Read</option>
                </select>
            </div>
            <div style="flex:1;min-width:140px;">
                <label style="font-size:11px;font-weight:600;color:#6B7280;">SEARCH</label>
                <input type="text" name="search" class="form-control" value="<?php echo e($search); ?>" placeholder="Search notifications..." style="font-size:12.5px;height:34px;">
            </div>
            <button type="submit" class="btn btn-warning btn-sm" style="border-radius:8px;height:34px;"><i class="fa fa-filter"></i> Filter</button>
            <a href="notifications.php" class="btn btn-default btn-sm" style="border-radius:8px;height:34px;"><i class="fa fa-times"></i></a>
        </form>

        <!-- Notification Feed -->
        <?php if (empty($notifs)): ?>
            <div class="empty-state">
                <i class="fa fa-bell-slash"></i>
                <p style="margin-top:12px;font-size:14px;">No notifications found.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($notifs as $n):
            $tc = $type_colors[$n['type']] ?? $type_colors['general'];
            $is_unread = !$n['is_read'];
        ?>
            <div class="notif-card <?php echo $is_unread ? 'unread' : ''; ?>">
                <div class="notif-icon" style="background:<?php echo $tc['bg']; ?>;color:<?php echo $tc['color']; ?>;">
                    <i class="fa <?php echo $tc['icon']; ?>"></i>
                </div>
                <div class="notif-body">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div class="notif-title"><?php echo e($n['title']); ?></div>
                        <div style="display:flex;gap:4px;align-items:center;">
                            <?php if ($n['link']): ?>
                                <a href="<?php echo e($n['link']); ?>" style="font-size:11px;color:#2563EB;text-decoration:none;font-weight:600;"><i class="fa fa-external-link"></i> Open</a>
                            <?php endif; ?>
                            <a href="?toggle_read=<?php echo $n['id']; ?>&<?php echo http_build_query(array_filter($_GET, fn($k) => !in_array($k, ['toggle_read']), ARRAY_FILTER_USE_KEY)); ?>"
                               style="font-size:11px;color:<?php echo $is_unread ? '#F97316' : '#9CA3AF'; ?>;text-decoration:none;font-weight:600;margin-left:6px;" title="Toggle read">
                                <i class="fa <?php echo $is_unread ? 'fa-envelope' : 'fa-envelope-open'; ?>"></i>
                            </a>
                        </div>
                    </div>
                    <div class="notif-msg"><?php echo e($n['message']); ?></div>
                    <div class="notif-meta">
                        <span style="padding:2px 8px;border-radius:99px;font-size:10.5px;font-weight:700;background:<?php echo $tc['bg']; ?>;color:<?php echo $tc['color']; ?>;"><?php echo ucfirst($n['type']); ?></span>
                        <span><i class="fa fa-clock-o"></i> <?php echo date('d M Y, h:i A', strtotime($n['created_at'])); ?></span>
                        <?php if ($n['user_id']): ?>
                            <span><i class="fa fa-user"></i> User #<?php echo $n['user_id']; ?></span>
                        <?php else: ?>
                            <span><i class="fa fa-globe"></i> All Users</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($total_pages > 1): ?>
            <div style="display:flex;justify-content:center;gap:4px;margin-top:16px;">
                <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++):
                    $q = array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY);
                    $q['page'] = $p;
                ?>
                    <a href="?<?php echo http_build_query($q); ?>" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;<?php echo $p === $page ? 'background:#f97316;color:#fff;' : 'background:#F3F4F6;color:#374151;border:1px solid #E5E7EB;'; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Notification Modal -->
<div id="createNotifModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:28px;width:90%;max-width:520px;max-height:90vh;overflow-y:auto;position:relative;">
        <button onclick="document.getElementById('createNotifModal').style.display='none'" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;color:#9CA3AF;cursor:pointer;"><i class="fa fa-times"></i></button>
        <h4 style="font-weight:800;color:#111827;margin:0 0 16px;"><i class="fa fa-plus-circle" style="color:#f97316;"></i> Create Notification</h4>
        <form method="post" action="notifications.php">
            <input type="hidden" name="action" value="CreateNotification">
            <div class="form-group">
                <label style="font-weight:600;color:#374151;">Send To</label>
                <select name="target_user" class="form-control">
                    <option value="all">All Users</option>
                    <?php
                    $urs = db_query("SELECT user_id, full_name, role FROM users WHERE status=1 ORDER BY full_name");
                    while ($ur = $urs->fetch_assoc()):
                    ?>
                        <option value="<?php echo $ur['user_id']; ?>"><?php echo e($ur['full_name']); ?> (<?php echo e($ur['role']); ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label style="font-weight:600;color:#374151;">Type</label>
                <select name="notif_type" class="form-control">
                    <?php foreach (array_keys($type_colors) as $t): ?>
                        <option value="<?php echo $t; ?>"><?php echo ucfirst($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label style="font-weight:600;color:#374151;">Title</label>
                <input type="text" name="notif_title" class="form-control" placeholder="Notification title..." required>
            </div>
            <div class="form-group">
                <label style="font-weight:600;color:#374151;">Message</label>
                <textarea name="notif_message" class="form-control" rows="4" placeholder="Notification message..." required></textarea>
            </div>
            <div class="form-group">
                <label style="font-weight:600;color:#374151;">Link (optional)</label>
                <input type="text" name="notif_link" class="form-control" placeholder="dashboard.php">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:11px;border-radius:12px;font-weight:700;"><i class="fa fa-paper-plane"></i> Send Notification</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
