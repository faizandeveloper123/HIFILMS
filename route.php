<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Transport Routes';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddRoute') {
        $name = trim($_POST['route_name'] ?? '');
        $fare = (float) ($_POST['fare'] ?? 0);
        if ($name === '') {
            $error = 'Route title is required.';
        } else {
            $st2 = db_prepare("INSERT INTO routes (route_name, fare, status) VALUES (?, ?, 1)");
            $st2->bind_param('sd', $name, $fare);
            $st2->execute();
            $message = 'Route saved successfully!';
        }
    }

    if ($action === 'UpdateRoute') {
        $rid = (int) ($_POST['route_id'] ?? 0);
        $name = trim($_POST['route_name'] ?? '');
        $fare = (float) ($_POST['fare'] ?? 0);
        $st2 = db_prepare("UPDATE routes SET route_name=?, fare=? WHERE route_id=?");
        $st2->bind_param('sdi', $name, $fare, $rid);
        $st2->execute();
        $message = 'Route updated successfully!';
    }

    if ($action === 'DeleteRoute') {
        $rid = (int) ($_POST['route_id'] ?? 0);
        $st2 = db_prepare("DELETE FROM vehicle_route WHERE route_id=?");
        $st2->bind_param('i', $rid);
        $st2->execute();
        $st2 = db_prepare("DELETE FROM routes WHERE route_id=?");
        $st2->bind_param('i', $rid);
        $st2->execute();
        $st2 = db_prepare("UPDATE students SET route_id=NULL WHERE route_id=?");
        $st2->bind_param('i', $rid);
        $st2->execute();
        $message = 'Route deleted successfully!';
    }

    if ($action === 'ToggleStatus') {
        $rid = (int) ($_POST['route_id'] ?? 0);
        if ($rid > 0) {
            $st2 = db_prepare("UPDATE routes SET status = IF(status=1, 0, 1) WHERE route_id=?");
            $st2->bind_param('i', $rid);
            $st2->execute();
            $message = 'Route status toggled!';
        }
    }
}

$routes = [];
$res = db_query("SELECT * FROM routes ORDER BY route_id");
while ($row = $res->fetch_assoc()) { $routes[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.form-lbl { font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
.required::after { content: ' *'; color: #e53e3e; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px; flex-wrap:wrap; gap:10px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-signal"></i> Route List <span style="font-size:14px; color:#6B7280;">(<?php echo count($routes); ?> records)</span></h3>
            <div style="display:flex; gap:8px;">
                <a href="<?php echo BASE_URL; ?>vehicle_route.php" class="btn btn-info" style="color:#fff;"><i class="fa fa-road"></i> Vehicle Routes</a>
                <a href="<?php echo BASE_URL; ?>vehicles.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-bus"></i> Vehicles</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <form method="post" action="route.php" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
                    <h4 style="font-size:15px; font-weight:800; margin:0 0 14px;"><i class="fa fa-plus" style="color:#48bb78;"></i> Add New Route</h4>
                    <input type="hidden" name="action" value="AddRoute">
                    <div class="form-group">
                        <label class="form-lbl required">Route Title</label>
                        <input type="text" name="route_name" class="form-control" placeholder="eg Ikhlas Pur" required>
                    </div>
                    <div class="form-group">
                        <label class="form-lbl required">Fare</label>
                        <input type="number" step="0.01" name="fare" class="form-control" placeholder="eg 2000">
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%;"><i class="fa fa-check"></i> Save Record</button>
                </form>
            </div>

            <div class="col-md-8">
                <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
                    <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                        <thead>
                            <tr><th>#</th><th>Route Title</th><th>Fare</th><th>Status</th><th style="width:180px;">Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($routes) === 0): ?>
                                <tr><td colspan="5" style="text-align:center; color:#6B7280; padding:36px;"><i class="fa fa-signal" style="font-size:36px; display:block; margin-bottom:10px;"></i>No routes added yet. Use the form to save your first record.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($routes as $rt): ?>
                                <tr>
                                    <td><?php echo $rt['route_id']; ?></td>
                                    <td><strong><?php echo e($rt['route_name']); ?></strong></td>
                                    <td><?php echo number_format((float) $rt['fare'], 2); ?></td>
                                    <td>
                                        <form method="post" action="route.php" style="display:inline;">
                                            <input type="hidden" name="action" value="ToggleStatus">
                                            <input type="hidden" name="route_id" value="<?php echo $rt['route_id']; ?>">
                                            <button type="submit" class="btn <?php echo $rt['status'] ? 'btn-success' : 'btn-warning'; ?> btn-xs" style="font-size:11px;">
                                                <?php echo $rt['status'] ? 'Active' : 'Inactive'; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-xs edit-r" data-id="<?php echo $rt['route_id']; ?>" data-name="<?php echo e($rt['route_name']); ?>" data-fare="<?php echo e($rt['fare']); ?>"><i class="fa fa-pencil"></i></button>
                                        <form method="post" action="route.php" style="display:inline;" onsubmit="return confirm('Delete this route? Related student assignments will also be cleared.');">
                                            <input type="hidden" name="action" value="DeleteRoute">
                                            <input type="hidden" name="route_id" value="<?php echo $rt['route_id']; ?>">
                                            <button class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editRouteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB; padding:16px 20px;">
                <h4 class="modal-title" style="font-weight:800; font-size:16px;"><i class="fa fa-pencil" style="color:#ed8936;"></i> Edit Route</h4>
            </div>
            <form method="post" action="route.php">
                <div class="modal-body" style="padding:20px;">
                    <input type="hidden" name="action" value="UpdateRoute">
                    <input type="hidden" name="route_id" id="erId">
                    <div class="form-group">
                        <label class="form-lbl required">Route Title</label>
                        <input type="text" name="route_name" id="erName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-lbl required">Fare</label>
                        <input type="number" step="0.01" name="fare" id="erFare" class="form-control">
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.edit-r').forEach(function(btn){
    btn.addEventListener('click', function(){
        document.getElementById('erId').value = this.dataset.id;
        document.getElementById('erName').value = this.dataset.name;
        document.getElementById('erFare').value = this.dataset.fare;
        jQuery('#editRouteModal').modal('show');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>