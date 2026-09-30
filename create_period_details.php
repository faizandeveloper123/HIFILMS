<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

require_once __DIR__ . '/includes/period_schema.php';

$page_title = 'Manage Period';

$message = '';
$error = '';

$categories = [];
$res = db_query("SELECT * FROM period_categories ORDER BY name");
while ($row = $res->fetch_assoc()) { $categories[] = $row; }

$periods = [];
$res = db_query("SELECT p.*, pc.name AS cat_name FROM periods p
                 LEFT JOIN period_categories pc ON pc.id=p.category_id
                 ORDER BY p.start_time, p.period_id");
while ($row = $res->fetch_assoc()) { $periods[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'CreatePeriodDetails') {
        $period_cat = (int) ($_POST['Periodcat'] ?? 0);
        $titles = $_POST['title'] ?? [];
        $starts = $_POST['start'] ?? [];
        $ends   = $_POST['end'] ?? [];
        if ($period_cat <= 0) {
            $error = 'Please select a period category.';
        } else {
            $count = 0;
            $st2 = db_prepare("INSERT INTO periods (period_name, start_time, end_time, category_id) VALUES (?, ?, ?, ?)");
            foreach ($titles as $i => $title) {
                $title = trim($title);
                if ($title === '') continue;
                $start = trim($starts[$i] ?? '');
                $end   = trim($ends[$i] ?? '');
                $start_sql = $start !== '' ? date('H:i', strtotime($start)) : null;
                $end_sql   = $end !== '' ? date('H:i', strtotime($end)) : null;
                $st2->bind_param('sssi', $title, $start_sql, $end_sql, $period_cat);
                $st2->execute();
                $count++;
            }
            $message = "$count period(s) created successfully!";
        }
    }

    if ($action === 'EditCreatePeriodDetails') {
        $pid = (int) ($_POST['period_details_id'] ?? 0);
        $title = trim($_POST['period_details_title'] ?? '');
        $start = trim($_POST['period_start'] ?? '');
        $end   = trim($_POST['period_end'] ?? '');
        if ($pid <= 0 || $title === '') {
            $error = 'Period title is required.';
        } else {
            $start_sql = $start !== '' ? date('H:i', strtotime($start)) : null;
            $end_sql   = $end !== '' ? date('H:i', strtotime($end)) : null;
            $st2 = db_prepare("UPDATE periods SET period_name=?, start_time=?, end_time=? WHERE period_id=?");
            $st2->bind_param('sssi', $title, $start_sql, $end_sql, $pid);
            $st2->execute();
            $message = 'Period updated successfully!';
        }
    }

    if ($action === 'DeletePeriodDetails') {
        $pid = (int) ($_POST['period_id'] ?? 0);
        if ($pid > 0) {
            $d = db_prepare("DELETE FROM timetable WHERE period_id=?");
            $d->bind_param('i', $pid);
            $d->execute();
            $st2 = db_prepare("DELETE FROM periods WHERE period_id=?");
            $st2->bind_param('i', $pid);
            $st2->execute();
            $message = 'Period deleted.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.a2-breadcrumb a { color:#6366F1; text-decoration:none; cursor:pointer; }
.a2-breadcrumb i { color:#9CA3AF; margin:0 6px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="a2-breadcrumb" style="padding:8px 4px; font-size:13px; color:#111827;">
            <a href="<?php echo BASE_URL; ?>index.php"><i class="fa fa-home"></i> Dashboard</a>
            <i class="fa fa-angle-double-right"></i> Manage Period
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="row" style="margin-top:6px;">
            <div class="col-md-6">
                <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:4px;">
                    <div style="padding:12px 14px; font-weight:800; color:#111827; font-size:15px; border-bottom:1px solid #E5E7EB;"><i class="fa fa-list"></i> Period Definitions</div>
                    <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0; font-size:12.5px;">
                        <thead>
                            <tr>
                                <th style="width:9%; text-align:center;">S.No</th>
                                <th>Period Name</th>
                                <th>Category</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th style="width:14%; text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($periods) === 0): ?>
                                <tr><td colspan="6" style="text-align:center; color:#6B7280; padding:30px;">No periods configured yet. Use the form on the right to create them.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($periods as $i => $p): ?>
                                <tr>
                                    <td style="text-align:center;"><?php echo $i + 1; ?></td>
                                    <td><?php echo e($p['period_name']); ?></td>
                                    <td><?php echo e($p['cat_name'] ?? '-'); ?></td>
                                    <td><?php echo $p['start_time'] ? date('h:i A', strtotime($p['start_time'])) : '-'; ?></td>
                                    <td><?php echo $p['end_time'] ? date('h:i A', strtotime($p['end_time'])) : '-'; ?></td>
                                    <td style="text-align:center; white-space:nowrap;">
                                        <a href="#" data-toggle="modal" data-target="#EditPeriodDetail<?php echo $p['period_id']; ?>" class="btn btn-success" style="padding:2px 8px; font-size:13px;"><i class="fa fa-pencil" aria-hidden="true"></i></a>
                                        <form method="post" action="create_period_details.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this record');">
                                            <input type="hidden" name="action" value="DeletePeriodDetails">
                                            <input type="hidden" name="period_id" value="<?php echo $p['period_id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="padding:2px 8px; font-size:13px;"><i class="fa fa-remove" aria-hidden="true"></i></button>
                                        </form>
                                    </td>
                                </tr>

                                <div id="EditPeriodDetail<?php echo $p['period_id']; ?>" class="modal fade" role="dialog">
                                    <div class="modal-dialog">
                                        <div class="modal-content" style="border-radius:14px;">
                                            <div class="modal-header">
                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                <h4 class="modal-title" style="font-weight:800; text-align:center;">Edit (<?php echo e(($p['cat_name'] ?? $p['period_name']) . ' - ' . $p['period_name']); ?>)</h4>
                                            </div>
                                            <div class="modal-body">
                                                <form method="post" action="create_period_details.php">
                                                    <input type="hidden" name="action" value="EditCreatePeriodDetails">
                                                    <input type="hidden" name="period_details_id" value="<?php echo $p['period_id']; ?>">
                                                    <div class="form-group">
                                                        <label>Period Name</label>
                                                        <input type="text" name="period_details_title" class="form-control" value="<?php echo e($p['period_name']); ?>" placeholder="e.g. Period 1" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Start Time</label>
                                                        <input type="time" name="period_start" class="form-control" value="<?php echo $p['start_time'] ? date('H:i', strtotime($p['start_time'])) : ''; ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>End Time</label>
                                                        <input type="time" name="period_end" class="form-control" value="<?php echo $p['end_time'] ? date('H:i', strtotime($p['end_time'])) : ''; ?>">
                                                    </div>
                                                    <div class="modal-footer" style="border-top:none; text-align:center;">
                                                        <button type="submit" name="submit" class="btn btn-primary">Submit</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-md-6">
                <form method="post" action="create_period_details.php" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
                    <input type="hidden" name="action" value="CreatePeriodDetails">
                    <div class="form-group">
                        <label class="required">Period Categories</label>
                        <select name="Periodcat" class="form-control" required>
                            <option value="">Select</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php for ($r = 0; $r < 6; $r++): ?>
                        <div class="row" style="padding:0.5%; margin:0.5%;">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <input type="text" class="form-control" name="title[]" placeholder="e.g. Period 1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <input type="time" class="form-control" name="start[]" placeholder="Period Start">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <input type="time" class="form-control" name="end[]" placeholder="Period End">
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                    <div class="clearfix"></div>
                    <button type="submit" name="submit" class="btn btn-primary" style="padding:10px 30px;"><i class="fa fa-save"></i> Save Period</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>