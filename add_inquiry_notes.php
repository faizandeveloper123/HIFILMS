<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Add Inquiry Notes';

$inquiries = [];
$res = db_query("SELECT inquiry_id, name, father_name, phone, class_id, status FROM student_inquiries ORDER BY created_at DESC");
while ($row = $res->fetch_assoc()) { $inquiries[] = $row; }

$inquiry_id = (int) ($_GET['inquiry_id'] ?? 0);

$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && trim($_POST['action'] ?? '') === 'AddNote') {
    $note = trim($_POST['note'] ?? '');
    $nid = (int) ($_POST['inquiry_id'] ?? 0);
    if ($nid > 0) {
        $inquiry_id = $nid;
    }
    if ($inquiry_id <= 0) { $error = 'Please select an inquiry.'; }
    elseif ($note === '') { $error = 'Note text is required.'; }
    else {
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        $st = db_prepare("INSERT INTO inquiry_notes (inquiry_id, note, created_by) VALUES (?, ?, ?)");
        $st->bind_param('isi', $inquiry_id, $note, $uid);
        $st->execute();
        $message = 'Note added successfully.';
    }
}

$inquiry = null;
if ($inquiry_id > 0) {
    $st = db_prepare("SELECT i.*, c.class_name, s.section_name, u.full_name added_by_name FROM student_inquiries i LEFT JOIN classes c ON i.class_id=c.class_id LEFT JOIN sections s ON i.section_id=s.section_id LEFT JOIN users u ON i.created_by=u.user_id WHERE i.inquiry_id=?");
    $st->bind_param('i', $inquiry_id);
    $st->execute();
    $res = $st->get_result();
    $inquiry = $res->fetch_assoc();
    if (!$inquiry) { $inquiry_id = 0; }
}

$notes = [];
if ($inquiry_id > 0) {
    $st = db_prepare("SELECT n.*, u.full_name added_by_name FROM inquiry_notes n LEFT JOIN users u ON n.created_by=u.user_id WHERE n.inquiry_id=? ORDER BY n.created_at DESC");
    $st->bind_param('i', $inquiry_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) { $notes[] = $row; }
}

include __DIR__ . '/includes/header.php';
?>
<style>
:root { --in-brand: #00AFEF; --in-border: rgba(11,11,11,0.08); --in-muted: #898781; --in-ink: #52514e; }
.in-wrap { padding: 14px 18px; }
.in-card { background:#fff; border:1px solid var(--in-border); border-radius:12px; padding:14px 18px; margin-bottom:14px; }
.in-topbar { background:#fff; border:1px solid var(--in-border); border-radius:12px; padding:14px 18px; margin-bottom:12px; }
.in-crumb { font-size:12px; color:var(--in-muted); margin:0 0 4px; }
.in-crumb a { color:var(--in-ink); text-decoration:none; }
.in-title-row { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
.in-title-row h2 { margin:0; font-size:19px; font-weight:700; color:#0b0b0b; }
.in-badge { font-size:11px; font-weight:700; color:var(--in-brand); background:rgba(0,175,239,0.12); border-radius:999px; padding:3px 10px; }
.in-subtitle { margin:3px 0 0; font-size:12px; color:var(--in-ink); }
.in-select-row { display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap; }
.in-select-row .form-control { border-radius:8px; border:1px solid #ced9ea; font-size:13px; }
.in-detail-table { width:100%; border-collapse:collapse; font-size:12.5px; }
.in-detail-table td { padding:6px 8px; border-bottom:1px solid #f1f3f9; vertical-align:top; }
.in-detail-table td.k { width:33%; font-weight:700; color:var(--in-ink); background:#f8fbff; }
.in-status-pill { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; color:#fff; }
.in-note-item { position:relative; background:#fff; border:1px solid var(--in-border); border-radius:10px; padding:12px 14px; margin-bottom:10px; }
.in-note-avatar { width:34px; height:34px; border-radius:999px; background:linear-gradient(135deg,#00AFEF,#3ec6f7); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; flex-shrink:0; }
.in-note-meta { font-size:11px; color:var(--in-muted); }
.in-note-text { font-size:13px; color:#0b0b0b; margin-top:4px; white-space:pre-wrap; }
.in-empty { text-align:center; color:var(--in-muted); padding:30px; }
.in-actions a { margin-right:6px; }
</style>

<div class="in-wrap">
    <?php if ($message): ?><div class="alert alert-success" style="padding:8px 14px;font-size:12px;border-radius:8px;margin-bottom:10px;"><?php echo e($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger" style="padding:8px 14px;font-size:12px;border-radius:8px;margin-bottom:10px;"><?php echo e($error); ?></div><?php endif; ?>

    <div class="in-topbar">
        <p class="in-crumb"><a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> <i class="fas fa-angle-double-right"></i> Front Office <i class="fas fa-angle-double-right"></i> <a href="<?php echo BASE_URL; ?>student_inquiry.php">Students Inquiries</a> <i class="fas fa-angle-double-right"></i> Add Inquiry Notes</p>
        <div class="in-title-row">
            <h2>Add Inquiry Notes</h2>
            <span class="in-badge"><?php echo count($inquiries); ?> Inquiries</span>
        </div>
        <p class="in-subtitle">Select an inquiry to view its detail, print its documents and add follow-up notes.</p>
    </div>

    <div class="in-card">
        <form method="get" action="add_inquiry_notes.php" class="in-select-row">
            <div class="form-group" style="flex:1;min-width:280px;margin-bottom:0;">
                <label style="font-size:11px;font-weight:700;color:var(--in-ink);text-transform:uppercase;">Select Inquiry</label>
                <select name="inquiry_id" class="form-control" style="height:38px;">
                    <option value="0">-- Choose Inquiry --</option>
                    <?php foreach ($inquiries as $iq): ?>
                        <option value="<?php echo (int) $iq['inquiry_id']; ?>" <?php echo $inquiry_id === (int) $iq['inquiry_id'] ? 'selected' : ''; ?>>
                            #<?php echo $iq['inquiry_id']; ?> - <?php echo e($iq['name']); ?><?php echo $iq['father_name'] ? ' / ' . e($iq['father_name']) : ''; ?> (<?php echo e($iq['phone'] ?? '-'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="border-radius:8px;background:var(--in-brand);border-color:var(--in-brand);"><i class="fa fa-eye"></i> Load Inquiry</button>
        </form>
    </div>

    <?php if (!$inquiry && $inquiry_id <= 0): ?>
        <div class="in-card in-empty">
            <i class="fa fa-user-plus" style="font-size:34px;color:#D1D5DB;"></i>
            <p style="margin:10px 0 0;">No inquiry selected. Choose an inquiry from the list above.</p>
        </div>
    <?php elseif (!$inquiry): ?>
        <div class="in-card in-empty"><p>Inquiry not found.</p></div>
    <?php else: ?>
        <div class="in-card">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                <div class="in-title-row">
                    <h2 style="font-size:16px;">Inquiry #<?php echo (int) $inquiry['inquiry_id']; ?> - <?php echo e($inquiry['name']); ?></h2>
                    <span class="in-status-pill" style="background:#337ab7;"><?php echo e($inquiry['status'] ?? 'New'); ?></span>
                </div>
                <div class="in-actions">
                    <a href="<?php echo BASE_URL; ?>print_student_inquiry_form.php?inquiry_id=<?php echo (int) $inquiry['inquiry_id']; ?>" target="_blank" class="btn btn-info btn-xs"><i class="fa fa-print"></i> Print Form</a>
                    <a href="<?php echo BASE_URL; ?>print_inquiry_rollslip.php?inquiry_id=<?php echo (int) $inquiry['inquiry_id']; ?>" target="_blank" class="btn btn-warning btn-xs"><i class="fa fa-print"></i> Roll Slip</a>
                    <a href="<?php echo BASE_URL; ?>inquery_fee_voucher.php?inquiry_id=<?php echo (int) $inquiry['inquiry_id']; ?>" class="btn btn-success btn-xs"><i class="fa fa-money"></i> Fee Voucher</a>
                </div>
            </div>
            <table class="in-detail-table">
                <tr><td class="k">Father Name</td><td><?php echo e($inquiry['father_name'] ?? '-'); ?></td><td class="k">Student Cell</td><td><?php echo e($inquiry['phone'] ?? '-'); ?></td></tr>
                <tr><td class="k">Father Cell</td><td><?php echo e($inquiry['father_cellno'] ?? '-'); ?></td><td class="k">Email</td><td><?php echo e($inquiry['email'] ?? '-'); ?></td></tr>
                <tr><td class="k">Class / Section</td><td><?php echo e($inquiry['class_name'] ?? '-'); ?> / <?php echo e($inquiry['section_name'] ?? '-'); ?></td><td class="k">Session</td><td><?php echo e($inquiry['session'] ?? '-'); ?></td></tr>
                <tr><td class="k">Admission Source</td><td><?php echo e($inquiry['admission_source'] ?? '-'); ?></td><td class="k">Locality</td><td><?php echo e($inquiry['locality'] ?? '-'); ?></td></tr>
                <tr><td class="k">Visiting Date</td><td><?php echo $inquiry['visit_date'] ? date('d-M-Y', strtotime($inquiry['visit_date'])) : '-'; ?></td><td class="k">Test Date</td><td><?php echo $inquiry['test_date'] ? date('d-M-Y', strtotime($inquiry['test_date'])) : '-'; ?></td></tr>
                <tr><td class="k">Address</td><td colspan="3"><?php echo e($inquiry['address'] ?? '-'); ?></td></tr>
                <tr><td class="k">Remarks</td><td colspan="3"><?php echo e($inquiry['remarks'] ?? '-'); ?></td></tr>
                <tr><td class="k">Added By</td><td><?php echo e($inquiry['added_by_name'] ?? 'Admin'); ?></td><td class="k">Inquiry Date</td><td><?php echo date('d-M-Y', strtotime($inquiry['created_at'])); ?></td></tr>
            </table>
        </div>

        <div class="in-card">
            <h3 style="font-size:14px;font-weight:800;color:#0b0b0b;margin:0 0 10px;"><i class="fa fa-sticky-note-o" style="color:var(--in-brand);"></i> Follow-up Notes (<?php echo count($notes); ?>)</h3>
            <?php if (count($notes) === 0): ?>
                <div class="in-empty" style="padding:20px;">No notes added yet for this inquiry.</div>
            <?php else: ?>
                <?php foreach ($notes as $n): ?>
                    <div class="in-note-item">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="in-note-avatar"><?php echo strtoupper(substr($n['added_by_name'] ?? 'A', 0, 1)); ?></div>
                            <div>
                                <div style="font-size:12.5px;font-weight:700;color:#0b0b0b;"><?php echo e($n['added_by_name'] ?? 'Admin'); ?></div>
                                <div class="in-note-meta"><i class="fa fa-clock-o"></i> <?php echo date('d-M-Y h:i A', strtotime($n['created_at'])); ?> &nbsp; (<?php echo e($n['created_by'] ? 'User #' . $n['created_by'] : 'System'); ?>)</div>
                            </div>
                        </div>
                        <div class="in-note-text"><?php echo e($n['note']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="post" action="add_inquiry_notes.php" style="margin-top:14px;border-top:1px dashed #dbe3f1;padding-top:14px;">
                <input type="hidden" name="action" value="AddNote">
                <input type="hidden" name="inquiry_id" value="<?php echo (int) $inquiry['inquiry_id']; ?>">
                <div class="form-group" style="margin-bottom:8px;">
                    <label style="font-size:11px;font-weight:700;color:var(--in-ink);text-transform:uppercase;">Add New Note</label>
                    <textarea name="note" rows="3" class="form-control" placeholder="Write a follow-up note for this inquiry..." required style="border-radius:8px;border:1px solid #ced9ea;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="border-radius:8px;background:var(--in-brand);border-color:var(--in-brand);"><i class="fa fa-plus"></i> Add Note</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>