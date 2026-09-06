<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Vehicles';

function veh_col_exists($c) {
    $r = db_query("SHOW COLUMNS FROM vehicles LIKE '" . str_replace("'", '', $c) . "'");
    return $r && $r->num_rows > 0;
}
try {
    foreach (['driver_license' => 'VARCHAR(191) NULL', 'driver_contact' => 'VARCHAR(50) NULL', 'vehicle_note' => 'TEXT NULL', 'year_made' => 'VARCHAR(10) NULL'] as $c => $d) {
        if (!veh_col_exists($c)) { db_query("ALTER TABLE vehicles ADD COLUMN `$c` $d"); }
    }
} catch (Throwable $ex) {}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddVehicle') {
        $no = trim($_POST['vehicle_no'] ?? '');
        $name = trim($_POST['vehicle_name'] ?? '');
        $capacity = (int) ($_POST['capacity'] ?? 0);
        $driver = trim($_POST['driver_name'] ?? '');
        $license = trim($_POST['driver_license'] ?? '');
        $contact = trim($_POST['driver_contact'] ?? '');
        $note = trim($_POST['vehicle_note'] ?? '');
        $year = trim($_POST['year_made'] ?? '');
        if ($no === '') {
            $error = 'Vehicle number is required.';
        } elseif ($driver === '') {
            $error = 'Driver name is required.';
        } elseif ($license === '') {
            $error = 'Driver license is required.';
        } elseif ($contact === '') {
            $error = 'Driver contact is required.';
        } else {
            $st2 = db_prepare("INSERT INTO vehicles (vehicle_no, vehicle_name, capacity, driver_name, driver_license, driver_contact, vehicle_note, year_made, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $st2->bind_param('ssisssss', $no, $name, $capacity, $driver, $license, $contact, $note, $year);
            $st2->execute();
            $message = 'Vehicle saved successfully!';
        }
    }

    if ($action === 'UpdateVehicle') {
        $vid = (int) ($_POST['vehicle_id'] ?? 0);
        $no = trim($_POST['vehicle_no'] ?? '');
        $name = trim($_POST['vehicle_name'] ?? '');
        $capacity = (int) ($_POST['capacity'] ?? 0);
        $driver = trim($_POST['driver_name'] ?? '');
        $license = trim($_POST['driver_license'] ?? '');
        $contact = trim($_POST['driver_contact'] ?? '');
        $note = trim($_POST['vehicle_note'] ?? '');
        $year = trim($_POST['year_made'] ?? '');
        $st2 = db_prepare("UPDATE vehicles SET vehicle_no=?, vehicle_name=?, capacity=?, driver_name=?, driver_license=?, driver_contact=?, vehicle_note=?, year_made=? WHERE vehicle_id=?");
        $st2->bind_param('ssisssssi', $no, $name, $capacity, $driver, $license, $contact, $note, $year, $vid);
        $st2->execute();
        $message = 'Vehicle updated successfully!';
    }

    if ($action === 'DeleteVehicle') {
        $vid = (int) ($_POST['vehicle_id'] ?? 0);
        $st2 = db_prepare("DELETE FROM vehicles WHERE vehicle_id=?");
        $st2->bind_param('i', $vid);
        $st2->execute();
        $message = 'Vehicle deleted successfully!';
    }

    if ($action === 'ToggleStatus') {
        $vid = (int) ($_POST['vehicle_id'] ?? 0);
        if ($vid > 0) {
            $st2 = db_prepare("UPDATE vehicles SET status = IF(status=1, 0, 1) WHERE vehicle_id=?");
            $st2->bind_param('i', $vid);
            $st2->execute();
            $message = 'Vehicle status toggled!';
        }
    }
}

$vehicles = [];
$res = db_query("SELECT * FROM vehicles ORDER BY vehicle_id");
while ($row = $res->fetch_assoc()) { $vehicles[] = $row; }

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
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-bus"></i> Vehicles List <span style="font-size:14px; color:#6B7280;">(<?php echo count($vehicles); ?> records)</span></h3>
            <div style="display:flex; gap:8px;">
                <a href="<?php echo BASE_URL; ?>vehicle_route.php" class="btn btn-info" style="color:#fff;"><i class="fa fa-route"></i> Assign Vehicle Routes</a>
                <a href="<?php echo BASE_URL; ?>route.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-signal"></i> Routes</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:18px;">
                    <h4 style="font-size:15px; font-weight:800; margin:0 0 14px;"><i class="fa fa-plus" style="color:#48bb78;"></i> Add Vehicle</h4>
                    <form method="post" action="vehicles.php" id="addVehicleForm">
                        <input type="hidden" name="action" value="AddVehicle">
                        <div class="form-group">
                            <label class="form-lbl required">Vehicle Number</label>
                            <input type="text" name="vehicle_no" class="form-control" placeholder="eg VH5645" required>
                        </div>
                        <div class="form-group">
                            <label class="form-lbl required">Vehicle Model</label>
                            <input type="text" name="vehicle_name" class="form-control" placeholder="eg Volvo Bus" required>
                        </div>
                        <div class="form-group">
                            <label class="form-lbl">Year Made</label>
                            <input type="text" name="year_made" class="form-control" placeholder="eg 2021">
                        </div>
                        <div class="form-group">
                            <label class="form-lbl">Capacity</label>
                            <input type="number" name="capacity" class="form-control" value="50">
                        </div>
                        <div class="form-group">
                            <label class="form-lbl required">Driver Name</label>
                            <input type="text" name="driver_name" class="form-control" placeholder="e.g Michel" required>
                        </div>
                        <div class="form-group">
                            <label class="form-lbl required">Driver License</label>
                            <input type="text" name="driver_license" class="form-control" placeholder="eg R534534" required>
                        </div>
                        <div class="form-group">
                            <label class="form-lbl required">Driver Contact</label>
                            <input type="text" name="driver_contact" class="form-control" placeholder="eg 03159060190" autocomplete="off" required>
                        </div>
                        <div class="form-group">
                            <label class="form-lbl">Note</label>
                            <textarea name="vehicle_note" class="form-control" rows="2" placeholder="Write Here"></textarea>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button type="submit" class="btn btn-success" style="flex:1;"><i class="fa fa-check"></i> Save Record</button>
                            <button type="button" class="btn btn-default" onclick="document.getElementById('addVehicleForm').reset();">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-8">
                <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
                    <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                        <thead>
                            <tr><th>#</th><th>Vehicle Number</th><th>Model</th><th>Year</th><th>Capacity</th><th>Driver Name</th><th>License</th><th>Contact</th><th>Note</th><th>Status</th><th style="width:140px;">Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($vehicles) === 0): ?>
                                <tr><td colspan="11" style="text-align:center; color:#6B7280; padding:36px;"><i class="fa fa-bus" style="font-size:36px; display:block; margin-bottom:10px;"></i>No vehicles added yet. Use the form to save your first record.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($vehicles as $v): ?>
                                <tr>
                                    <td><?php echo $v['vehicle_id']; ?></td>
                                    <td><strong><?php echo e($v['vehicle_no']); ?></strong></td>
                                    <td><?php echo e($v['vehicle_name'] ?? '-'); ?></td>
                                    <td><?php echo e($v['year_made'] ?? '-'); ?></td>
                                    <td><?php echo $v['capacity']; ?></td>
                                    <td><?php echo e($v['driver_name'] ?? '-'); ?></td>
                                    <td><?php echo e($v['driver_license'] ?? '-'); ?></td>
                                    <td><?php echo e($v['driver_contact'] ?? '-'); ?></td>
                                    <td><?php echo e($v['vehicle_note'] ?? '-'); ?></td>
                                    <td>
                                        <form method="post" action="vehicles.php" style="display:inline;">
                                            <input type="hidden" name="action" value="ToggleStatus">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $v['vehicle_id']; ?>">
                                            <button type="submit" class="btn <?php echo $v['status'] ? 'btn-success' : 'btn-warning'; ?> btn-xs" style="font-size:11px;">
                                                <?php echo $v['status'] ? 'Active' : 'Inactive'; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-xs edit-v" data-id="<?php echo $v['vehicle_id']; ?>" data-no="<?php echo e($v['vehicle_no']); ?>" data-name="<?php echo e($v['vehicle_name']); ?>" data-year="<?php echo e($v['year_made']); ?>" data-cap="<?php echo $v['capacity']; ?>" data-driver="<?php echo e($v['driver_name']); ?>" data-license="<?php echo e($v['driver_license']); ?>" data-contact="<?php echo e($v['driver_contact']); ?>" data-note="<?php echo e($v['vehicle_note']); ?>"><i class="fa fa-pencil"></i></button>
                                        <form method="post" action="vehicles.php" style="display:inline;" onsubmit="return confirm('Delete this vehicle?');">
                                            <input type="hidden" name="action" value="DeleteVehicle">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $v['vehicle_id']; ?>">
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

<div class="modal fade" id="editVehicleModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB; padding:16px 20px;">
                <h4 class="modal-title" style="font-weight:800; font-size:16px;"><i class="fa fa-pencil" style="color:#ed8936;"></i> Edit Vehicle</h4>
            </div>
            <form method="post" action="vehicles.php">
                <div class="modal-body" style="padding:20px;">
                    <input type="hidden" name="action" value="UpdateVehicle">
                    <input type="hidden" name="vehicle_id" id="evId">
                    <div class="form-group">
                        <label class="form-lbl required">Vehicle Number</label>
                        <input type="text" name="vehicle_no" id="evNo" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-lbl required">Vehicle Model</label>
                                <input type="text" name="vehicle_name" id="evName" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-lbl">Year Made</label>
                                <input type="text" name="year_made" id="evYear" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-lbl">Capacity</label>
                                <input type="number" name="capacity" id="evCap" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-lbl required">Driver Name</label>
                                <input type="text" name="driver_name" id="evDriver" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-lbl required">Driver License</label>
                                <input type="text" name="driver_license" id="evLicense" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-lbl required">Driver Contact</label>
                                <input type="text" name="driver_contact" id="evContact" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-lbl">Note</label>
                        <textarea name="vehicle_note" id="evNote" class="form-control" rows="2" placeholder="Write Here"></textarea>
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
document.querySelectorAll('.edit-v').forEach(function(btn){
    btn.addEventListener('click', function(){
        document.getElementById('evId').value = this.dataset.id;
        document.getElementById('evNo').value = this.dataset.no;
        document.getElementById('evName').value = this.dataset.name;
        document.getElementById('evYear').value = this.dataset.year;
        document.getElementById('evCap').value = this.dataset.cap;
        document.getElementById('evDriver').value = this.dataset.driver;
        document.getElementById('evLicense').value = this.dataset.license;
        document.getElementById('evContact').value = this.dataset.contact;
        document.getElementById('evNote').value = this.dataset.note;
        jQuery('#editVehicleModal').modal('show');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>