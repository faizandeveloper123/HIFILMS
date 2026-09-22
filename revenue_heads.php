<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Revenue Heads';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddHead') {
        $name = trim($_POST['revenue_head'] ?? '');
        if ($name === '') {
            $error = 'Revenue Head is required.';
        } else {
            $st2 = db_prepare("INSERT INTO revenue_heads (head_name, status) VALUES (?, 1)");
            $st2->bind_param('s', $name);
            $st2->execute();
            $message = 'Revenue head added successfully!';
        }
    }
}

$heads = [];
$res = db_query("SELECT * FROM revenue_heads ORDER BY head_id");
while ($row = $res->fetch_assoc()) { $heads[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.13/css/jquery.dataTables.min.css">
<style>
.page-head-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:14px 4px; }
.page-head-row h2 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.breadcrumb-modern { display:flex; align-items:center; gap:8px; font-size:12.5px; color:#6B7280; margin:6px 0 0; padding:0; list-style:none; }
.breadcrumb-modern a { color:#377DFF; text-decoration:none; }
.breadcrumb-modern i { font-size:11px; color:#9CA3AF; }
.page-actions { margin-bottom:16px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="page-head-row">
            <div>
                <h2><i class="fa fa-tags"></i> Revenue Head</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb-modern">
                        <li><a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a></li>
                        <li><i class="fa fa-angle-right"></i></li>
                        <li><span>Revenue Head</span></li>
                    </ol>
                </nav>
            </div>
        </div>

        <h3 style="font-size:16px; font-weight:800; color:#111827; margin:0 0 6px;">Add Revenue Head</h3>
        <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:20px;">
            <form method="post" action="revenue_heads.php" class="form-horizontal form-label-left" style="margin-bottom:0;">
                <input type="hidden" name="action" value="AddHead">
                <div class="form-group">
                    <label class="control-label col-md-3 col-sm-3 col-xs-12">Revenue Head</label>
                    <div class="col-md-6 col-sm-6 col-xs-12">
                        <input class="form-control" name="revenue_head" placeholder="eg Uniform" value="" required>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-md-6 col-md-offset-3">
                        <button id="send" type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="page-actions">
            <a href="<?php echo BASE_URL; ?>add_revenue.php" class="btn btn-success" style="color:#fff;"><i class="fa fa-plus-circle"></i> Add Revenue</a>
            <a href="<?php echo BASE_URL; ?>revenue_list.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-list"></i> List Revenues</a>
        </div>

        <h3 style="font-size:16px; font-weight:800; color:#111827; margin:0 0 10px;">Revenue Head List <small style="font-size:12px; color:#6B7280;">(<?php echo count($heads); ?> records)</small></h3>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <table id="datatable" class="table table-striped table-bordered" style="width:100%; background-color:#fff; margin-bottom:0;">
                <thead>
                    <tr>
                        <th width="5%" style="text-align:center;">S.No</th>
                        <th width="95%">Revenue Head Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($heads) === 0): ?>
                        <tr><td colspan="2" class="dataTables_empty">No data available in table</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; foreach ($heads as $h): ?>
                        <tr>
                            <td style="text-align:center;"><?php echo $i++; ?></td>
                            <td><strong><?php echo e($h['head_name']); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function(){
    $('#datatable').DataTable({ order:[], pageLength: 10 });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>