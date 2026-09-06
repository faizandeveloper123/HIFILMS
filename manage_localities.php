<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin']);

$page_title = 'Manage Localities';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddLocality') {
        $name = trim($_POST['locality_name'] ?? '');
        if ($name === '') {
            $error = 'Locality name is required.';
        } else {
            $st2 = db_prepare("INSERT INTO localities (locality_name, status) VALUES (?, 1)");
            $st2->bind_param('s', $name);
            $st2->execute();
            $message = 'Locality saved successfully!';
        }
    }

    if ($action === 'UpdateLocality') {
        $lid = (int) ($_POST['locality_id'] ?? 0);
        $name = trim($_POST['locality_name'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        $st2 = db_prepare("UPDATE localities SET locality_name=?, status=? WHERE locality_id=?");
        $st2->bind_param('sii', $name, $status, $lid);
        $st2->execute();
        $message = 'Locality updated successfully!';
    }

    if ($action === 'DeleteLocality') {
        $lid = (int) ($_POST['locality_id'] ?? 0);
        $st2 = db_prepare("UPDATE students SET locality_id=NULL WHERE locality_id=?");
        $st2->bind_param('i', $lid);
        $st2->execute();
        $st2 = db_prepare("DELETE FROM localities WHERE locality_id=?");
        $st2->bind_param('i', $lid);
        $st2->execute();
        $message = 'Locality deleted successfully!';
    }
}

$localities = [];
$res = db_query("SELECT * FROM localities ORDER BY locality_name");
while ($row = $res->fetch_assoc()) { $localities[] = $row; }

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
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-map-marker"></i> Manage Localities <span style="font-size:14px; color:#6B7280;">(<?php echo count($localities); ?> records)</span></h3>
            <div style="display:flex; gap:8px;">
                <a href="<?php echo BASE_URL; ?>manage_occupations.php" class="btn btn-success" style="color:#fff;"><i class="fa fa-briefcase"></i> Manage Occupation</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <form method="post" action="manage_localities.php" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
                    <h4 style="font-size:15px; font-weight:800; margin:0 0 14px;"><i class="fa fa-plus" style="color:#48bb78;"></i> Add Locality</h4>
                    <input type="hidden" name="action" value="AddLocality">
                    <div class="form-group">
                        <label class="form-lbl required">Add New Locality</label>
                        <input type="text" name="locality_name" class="form-control" placeholder="Enter Locality Name..." maxlength="100" autofocus required>
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%;"><i class="fa fa-check"></i> Save Locality</button>
                </form>
            </div>

            <div class="col-md-8">
                <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
                    <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                        <thead>
                            <tr><th style="width:1%; text-align:center;">S.No</th><th>Locality Name</th><th style="width:140px;">Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($localities) === 0): ?>
                                <tr><td colspan="3" style="text-align:center; color:#6B7280; padding:36px;"><i class="fa fa-map-marker" style="font-size:36px; display:block; margin-bottom:10px;"></i>No localities added yet. Use the form to save your first record.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($localities as $i => $l): ?>
                                <tr>
                                    <td style="text-align:center;"><?php echo $i + 1; ?></td>
                                    <td><strong><?php echo e($l['locality_name']); ?></strong></td>
                                    <td>
                                        <button class="btn btn-success btn-xs edit-l" data-id="<?php echo $l['locality_id']; ?>" data-name="<?php echo e($l['locality_name']); ?>" data-status="<?php echo $l['status']; ?>"><i class="fa fa-pencil"></i></button>
                                        <form method="post" action="manage_localities.php" style="display:inline;" onsubmit="return confirm('Delete this locality? Attached students will have their locality cleared.');">
                                            <input type="hidden" name="action" value="DeleteLocality">
                                            <input type="hidden" name="locality_id" value="<?php echo $l['locality_id']; ?>">
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

<div class="modal fade" id="editLocalityModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB; padding:16px 20px;">
                <h4 class="modal-title" style="font-weight:800; font-size:16px;"><i class="fa fa-pencil" style="color:#ed8936;"></i> Edit Locality</h4>
            </div>
            <form method="post" action="manage_localities.php">
                <div class="modal-body" style="padding:20px;">
                    <input type="hidden" name="action" value="UpdateLocality">
                    <input type="hidden" name="locality_id" id="elId">
                    <div class="form-group">
                        <label class="form-lbl required">Locality Name</label>
                        <input type="text" name="locality_name" id="elName" class="form-control" maxlength="100" required>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="status" id="elStatus" checked> Active</label>
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
document.querySelectorAll('.edit-l').forEach(function(btn){
    btn.addEventListener('click', function(){
        document.getElementById('elId').value = this.dataset.id;
        document.getElementById('elName').value = this.dataset.name;
        document.getElementById('elStatus').checked = this.dataset.status == '1';
        jQuery('#editLocalityModal').modal('show');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>