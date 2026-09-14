<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Staff Cards';

$employees = [];
$res = db_query("SELECT * FROM employees WHERE status=1 ORDER BY emp_id");
while ($row = $res->fetch_assoc()) { $employees[] = $row; }

$school_name = get_setting('school_name') ?: 'LAPS School & College';

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.card-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:14px; }
.id-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.04); }
.id-card .band { background:linear-gradient(90deg,#8B5CF6,#a78bfa); color:#fff; padding:10px 14px; font-weight:800; display:flex; justify-content:space-between; align-items:center; }
.id-card .body { padding:14px; }
.id-card .avatar-big { width:64px; height:64px; border-radius:999px; background:#F5F3FF; border:2px solid #8B5CF6; color:#8B5CF6; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:800; }
.id-card table { width:100%; font-size:12.5px; }
.id-card td { padding:3px 0; }
.id-card .lbl { color:#6B7280; width:92px; }
@media print { .no-print{display:none!important;} .card-grid{grid-template-columns:repeat(2,1fr);} }
@media (max-width:900px){ .card-grid{grid-template-columns:1fr;} }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-id-card"></i> Staff Cards</h3>
            <a href="<?php echo BASE_URL; ?>students_card.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-graduation-cap"></i> Students Cards</a>
        </div>

        <div class="no-print" style="margin-bottom:14px;">
            <button onclick="window.print()" class="btn btn-success"><i class="fa fa-print"></i> Print All Cards</button>
            <span style="color:#6B7280; margin-left:10px; font-size:13px;"><?php echo count($employees); ?> staff</span>
        </div>

        <div class="card-grid">
            <?php if (count($employees) === 0): ?>
                <div style="text-align:center; color:#6B7280; padding:40px;">No staff added yet. HRM module se employees add karein.</div>
            <?php endif; ?>
            <?php foreach ($employees as $emp): ?>
                <div class="id-card">
                    <div class="band">
                        <span><?php echo e($school_name); ?></span>
                        <span style="font-size:11px; font-weight:600;">STAFF ID</span>
                    </div>
                    <div class="body">
                        <div style="display:flex; gap:12px; align-items:center; margin-bottom:10px;">
                            <div class="avatar-big"><?php echo strtoupper(substr($emp['first_name'], 0, 1)); ?></div>
                            <div>
                                <div style="font-weight:800; font-size:14px; color:#111827;"><?php echo e($emp['first_name']); ?></div>
                                <div style="font-size:11.5px; color:#6B7280;"><?php echo e($emp['designation'] ?? 'Staff'); ?></div>
                            </div>
                        </div>
                        <table>
                            <tr><td class="lbl">Staff ID</td><td><strong><?php echo $emp['emp_id']; ?></strong></td></tr>
                            <tr><td class="lbl">Designation</td><td><?php echo e($emp['designation'] ?? '-'); ?></td></tr>
                            <tr><td class="lbl">Department</td><td><?php echo e($emp['department'] ?? '-'); ?></td></tr>
                            <tr><td class="lbl">Phone</td><td><?php echo e($emp['phone'] ?? '-'); ?></td></tr>
                            <tr><td class="lbl">Date of Joining</td><td><?php echo $emp['joining_date'] ? date('d M Y', strtotime($emp['joining_date'])) : '-'; ?></td></tr>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>