<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Manage Expenses';

// ------------------------------------------------------------
// Schema migration (idempotent)
// ------------------------------------------------------------
db_query("CREATE TABLE IF NOT EXISTS expense_categories (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191) NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB");

db_query("CREATE TABLE IF NOT EXISTS expense_subs (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `expense_id` INT NULL,
  `name` VARCHAR(191) NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB");

db_query("ALTER TABLE expenses ADD COLUMN IF NOT EXISTS category_id INT NULL AFTER category");
db_query("ALTER TABLE expenses ADD COLUMN IF NOT EXISTS sub_category_id INT NULL AFTER category_id");
db_query("ALTER TABLE expenses ADD COLUMN IF NOT EXISTS paid_by VARCHAR(50) DEFAULT 'Cash' AFTER expense_date");
db_query("ALTER TABLE expenses ADD COLUMN IF NOT EXISTS narration VARCHAR(255) DEFAULT NULL AFTER paid_by");

// Seed categories / sub categories once (if empty)
$seedCount = (int) (db_query("SELECT COUNT(*) c FROM expense_categories")->fetch_assoc()['c'] ?? 0);
if ($seedCount === 0) {
    $seedCats = [
        'Utilities' => ['Electricity', 'Gas', 'Water', 'Telephone', 'Internet'],
        'Salaries' => ['Current Salary', 'Salary Advance', 'Out Source Wages', 'Visiting Faculty Payments'],
        'Rent' => ['Building Rent', 'Property Tax'],
        'Transport' => ['Fuel Expense', 'Vehicle Repair & Maintenance', 'Travelling & Transportation'],
        'Maintenance & Repair' => ['Repair & Maintenance', 'Building Maintenance', 'Maint. of AC', 'Maint. of Computer', 'Maint. of Printer'],
        'Stationery' => ['Office Stationary', 'Paper', 'Print & Photocopy', 'Note Book'],
        'Advertisement / Marketing' => ['Advertising', 'Printing of Prospectus', 'Social Media Marketing', 'Promotional Events'],
        'Events' => ['Sports Gala', 'Fun Fair', 'Seerat Conference', 'Seminars & Workshops'],
        'Banking & Finance' => ['Bank Charges', 'Service Charges', 'Income Tax', 'Online Payment'],
        'Library' => ['Book Exp', 'Library Resources'],
        'Medical' => ['Medical', 'Medicine Exp'],
        'Security' => ['Security Services', 'Janitorial Staff'],
        'Software & IT' => ['Software Service Charges', 'Portal Monthly Charges', 'Server Hosting', 'Networking Accessories'],
        'Travel' => ['Travel Expense', 'Travelling / Bilty Charges'],
        'Other' => ["Other's", 'Misc. Expenses'],
    ];
    foreach ($seedCats as $cat => $subsList) {
        db_query("INSERT INTO expense_categories (name, status) VALUES ('" . addslashes($cat) . "', 1)");
        $catId = db_connect()->insert_id;
        foreach ($subsList as $s) {
            db_query("INSERT INTO expense_subs (expense_id, name, status) VALUES ($catId, '" . addslashes($s) . "', 1)");
        }
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddExpense') {
        $narration = trim($_POST['narration'] ?? '');
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $sub_category_id = (int) ($_POST['sub_category_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $expense_date = trim($_POST['expense_date'] ?? date('Y-m-d'));
        $paid_by = trim($_POST['paid_by'] ?? 'Cash');

        if ($category_id <= 0 && $narration === '') {
            $error = 'Category and narration / details are required.';
        } elseif ($amount <= 0) {
            $error = 'Valid expense amount is required.';
        } else {
            $uid = $_SESSION['user_id'];
            $scid = $sub_category_id > 0 ? $sub_category_id : null;
            $st2 = db_prepare("INSERT INTO expenses (title, category_id, sub_category_id, amount, expense_date, paid_by, narration, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $st2->bind_param('siidsssi', $narration, $category_id, $scid, $amount, $expense_date, $paid_by, $narration, $uid);
            $st2->execute();
            $message = 'Expense added successfully!';
        }
    }

    if ($action === 'UpdateExpense') {
        $eid = (int) ($_POST['expense_id'] ?? 0);
        $narration = trim($_POST['narration'] ?? '');
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $sub_category_id = (int) ($_POST['sub_category_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $expense_date = trim($_POST['expense_date'] ?? date('Y-m-d'));
        $paid_by = trim($_POST['paid_by'] ?? 'Cash');

        if ($eid <= 0 || $amount <= 0) {
            $error = 'Invalid expense data.';
        } else {
            $scid = $sub_category_id > 0 ? $sub_category_id : null;
            $st2 = db_prepare("UPDATE expenses SET title=?, category_id=?, sub_category_id=?, amount=?, expense_date=?, paid_by=?, narration=? WHERE expense_id=?");
            $st2->bind_param('siidsssi', $narration, $category_id, $scid, $amount, $expense_date, $paid_by, $narration, $eid);
            $st2->execute();
            $message = 'Expense updated successfully!';
        }
    }

    if ($action === 'DeleteExpense') {
        $eid = (int) ($_POST['expense_id'] ?? 0);
        if ($eid > 0) {
            $st2 = db_prepare("DELETE FROM expenses WHERE expense_id=?");
            $st2->bind_param('i', $eid);
            $st2->execute();
            $message = 'Expense deleted successfully!';
        }
    }
}

// ------------------------------------------------------------
// Filters (GET)
// ------------------------------------------------------------
$fCategory = $_GET['category'] ?? 'All';
$fSubCat = $_GET['subcatgory'] ?? 'All';
$fPaidBy = $_GET['PiadBy'] ?? 'All';
$fFrom = $_GET['from'] ?? '';
$fTo = $_GET['to'] ?? '';

$where = [];
$params = [];
$types = '';

if ($fCategory !== '' && $fCategory !== 'All') { $where[] = 'e.category_id = ?'; $params[] = (int)$fCategory; $types .= 'i'; }
if ($fSubCat !== '' && $fSubCat !== 'All') { $where[] = 'e.sub_category_id = ?'; $params[] = (int)$fSubCat; $types .= 'i'; }
if ($fPaidBy !== '' && $fPaidBy !== 'All') { $where[] = 'e.paid_by = ?'; $params[] = $fPaidBy; $types .= 's'; }
if ($fFrom !== '') { $where[] = 'e.expense_date >= ?'; $params[] = $fFrom; $types .= 's'; }
if ($fTo !== '') { $where[] = 'e.expense_date <= ?'; $params[] = $fTo; $types .= 's'; }

$sql = "SELECT e.*, c.name category_name, s.name sub_category_name
        FROM expenses e
        LEFT JOIN expense_categories c ON e.category_id = c.id
        LEFT JOIN expense_subs s ON e.sub_category_id = s.id";
if (count($where) > 0) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY e.expense_date DESC, e.expense_id DESC';

$expenses = [];
if (count($params) > 0) {
    $st2 = db_prepare($sql);
    $st2->bind_param($types, ...$params);
    $st2->execute();
    $res = $st2->get_result();
} else {
    $res = db_query($sql);
}
while ($row = $res->fetch_assoc()) { $expenses[] = $row; }

$totalExpense = 0.0;
foreach ($expenses as $ex) { $totalExpense += (float) $ex['amount']; }

// ------------------------------------------------------------
// Categories / Sub categories for dropdowns
// ------------------------------------------------------------
$cats = [];
$res = db_query("SELECT * FROM expense_categories WHERE status=1 ORDER BY name");
while ($row = $res->fetch_assoc()) { $cats[] = $row; }

$subs = [];
$res = db_query("SELECT * FROM expense_subs WHERE status=1 ORDER BY name");
while ($row = $res->fetch_assoc()) { $subs[] = $row; }

$subByCat = [];
foreach ($subs as $s) { $subByCat[$s['expense_id']][] = ['id' => (int)$s['id'], 'name' => $s['name']]; }

$paidByOptions = ['Cash', 'JazzCash', 'Easypaisa', 'BankAccount', 'UblOmni'];

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.13/css/jquery.dataTables.min.css">
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.filter-panel { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.filter-panel h4 { font-size:14px; font-weight:800; color:#111827; margin:0 0 6px; }
.page-head-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:14px 4px; }
.page-head-row h2 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.record-count-badge { display:inline-block; font-size:11px; font-weight:700; color:#377DFF; background:#E9F2FF; border-radius:999px; padding:4px 10px; margin-left:8px; vertical-align:middle; }
.breadcrumb-modern { display:flex; align-items:center; gap:8px; font-size:12.5px; color:#6B7280; margin:6px 0 0; padding:0; list-style:none; }
.breadcrumb-modern a { color:#377DFF; text-decoration:none; }
.breadcrumb-modern i { font-size:11px; color:#9CA3AF; }
.page-actions { margin-bottom:16px; display:flex; flex-wrap:wrap; gap:8px; }
.table-actions .btn { padding: 4px 9px; font-size: 12px; }
.total-amount-box { display:inline-flex; align-items:center; gap:8px; background:#FEF2F2; border:1px solid #FECACA; color:#DC2626; font-weight:800; font-size:14px; border-radius:12px; padding:10px 16px; margin-top:14px; }
.dataTables_wrapper { padding: 0 12px 12px; }
@media (max-width: 480px) {
  .page-actions .btn { flex:1 1 100%; white-space:normal; }
  #listofstudents { font-size: 12px !important; }
  #listofstudents th, #listofstudents td { padding: 6px 4px !important; }
  #listofstudents .btn { padding: 3px 6px; font-size: 11px; }
  .filter-panel { padding:12px; }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <!-- Page Header with Breadcrumb -->
        <div class="page-head-row">
            <div>
                <h2><i class="fa fa-money"></i> Manage Expenses <span class="record-count-badge"><?php echo count($expenses); ?> Records</span></h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb-modern">
                        <li><a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a></li>
                        <li><i class="fa fa-angle-right"></i></li>
                        <li><a href="#">Financial Management</a></li>
                        <li><i class="fa fa-angle-right"></i></li>
                        <li><span>Manage Expenses</span></li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Page Action Buttons -->
        <div class="page-actions">
            <button type="button" data-toggle="modal" data-target="#AddExpense" class="btn btn-success"><i class="fa fa-plus"></i> Add New Expense</button>
            <a href="<?php echo BASE_URL; ?>monthly_expenses_report.php" class="btn btn-info" style="text-decoration:none; color:#fff;"><i class="fa fa-chart-bar"></i> Expenses Summary</a>
            <button type="button" data-toggle="modal" data-target="#printExp" class="btn btn-info"><i class="fa fa-print"></i> Print Report</button>
        </div>

        <!-- Filter Panel -->
        <div class="filter-panel">
            <h4><i class="fa fa-filter"></i> Filter Expenses</h4>
            <form class="" action="manage_expenses.php" method="get">
                <div class="row">
                    <div class="col-md-2 col-sm-6" style="padding:8px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="required"><i class="fa fa-tags"></i> Category</label>
                            <select id="filter_category_select" name="category" class="form-control">
                                <option value="All">All</option>
                                <?php foreach ($cats as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $fCategory === (string)$c['id'] ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6" style="padding:8px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="required"><i class="fa fa-tag"></i> Sub Category</label>
                            <select name="subcatgory" id="viewlistsubcatgory" class="form-control">
                                <option value="All">All</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6" style="padding:8px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="required"><i class="fa fa-credit-card"></i> Paid By</label>
                            <select name="PiadBy" class="form-control">
                                <option value="All">All</option>
                                <?php foreach ($paidByOptions as $pb): ?>
                                    <option value="<?php echo $pb; ?>" <?php echo $fPaidBy === $pb ? 'selected' : ''; ?>><?php echo ($pb === 'BankAccount') ? 'Bank Account' : $pb; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6" style="padding:8px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="required"><i class="fa fa-calendar"></i> From Date</label>
                            <input name="from" value="<?php echo e($fFrom); ?>" type="date" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6" style="padding:8px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="required"><i class="fa fa-calendar"></i> To Date</label>
                            <input name="to" value="<?php echo e($fTo); ?>" type="date" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6" style="padding:8px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block" style="margin-top:0;"><i class="fa fa-search"></i> Apply Filters</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Expense Records -->
        <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <h4 style="margin:0; font-size:15px; font-weight:800; color:#111827; padding:14px 16px; border-bottom:2px solid #F3F4F6;">
                <i class="fa fa-list"></i> Expense Records
            </h4>
            <table id="listofstudents" class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead>
                    <tr>
                        <th width="5%">S.No</th>
                        <th width="20%">Expense Category</th>
                        <th width="30%">Expense Details</th>
                        <th width="12%">Date</th>
                        <th width="10%">Paid By</th>
                        <th width="10%" style="text-align:right;">Amount (Rs.)</th>
                        <th width="13%" style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expenses) === 0): ?>
                        <tr><td colspan="7" class="dataTables_empty">No data available in table</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; foreach ($expenses as $e): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td>
                                <strong><?php echo e($e['category_name'] ?: '-'); ?></strong>
                                <?php if (!empty($e['sub_category_name'])): ?><br><small style="color:#6B7280;"><?php echo e($e['sub_category_name']); ?></small><?php endif; ?>
                            </td>
                            <td><?php echo e($e['narration'] ?: $e['title']); ?></td>
                            <td><?php echo $e['expense_date'] ? date('d M Y', strtotime($e['expense_date'])) : '-'; ?></td>
                            <td><span class="status-badge" style="background:#E0E7FF; color:#4338CA;"><?php echo e($e['paid_by'] ?: '-'); ?></span></td>
                            <td style="text-align:right; color:#DC2626; font-weight:700;"><?php echo number_format($e['amount'], 2); ?></td>
                            <td class="table-actions" style="text-align:center; white-space:nowrap;">
                                <button type="button" class="btn btn-success btn-xs" onclick="openEdit(<?php echo $e['expense_id']; ?>)"><i class="fa fa-pencil"></i></button>
                                <form method="post" action="manage_expenses.php" style="display:inline;" onsubmit="return confirm('Delete this expense?');">
                                    <input type="hidden" name="action" value="DeleteExpense">
                                    <input type="hidden" name="expense_id" value="<?php echo $e['expense_id']; ?>">
                                    <button class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="padding: 4px 14px 14px;">
                <div class="total-amount-box">
                    <i class="fa fa-calculator"></i>
                    <span>Total Expenses: <?php echo e(get_setting('currency_symbol', 'Rs.')) . ' ' . number_format($totalExpense, 2); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Expense Report Modal -->
<div id="printExp" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">×</button>
                <h4 class="modal-title" style="text-align:center;"><i class="fa fa-print"></i> Print Expense Report</h4>
            </div>
            <div class="modal-body">
                <form action="print_expense_report.php" method="get">
                    <input type="hidden" name="from" value="<?php echo e($fFrom); ?>">
                    <input type="hidden" name="to" value="<?php echo e($fTo); ?>">
                    <input type="hidden" name="PiadBy" value="<?php echo e($fPaidBy); ?>">
                    <input type="hidden" name="category" value="<?php echo e($fCategory); ?>">
                    <input type="hidden" name="subcatgory" value="<?php echo e($fSubCat); ?>">
                    <div class="col-md-12" style="padding:8px;">
                        <div class="form-group">
                            <label>Show Column Sub Category</label>
                            <select name="subCatgry" class="form-control">
                                <option value="YES">YES</option>
                                <option value="NO">NO</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div id="AddExpense" class="modal fade" role="dialog">
    <div class="modal-dialog" style="width:850px; max-width:95%;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">×</button>
                <h3 class="modal-title" style="text-align:center;"><i class="fa fa-plus-circle"></i> Add New Expense</h3>
            </div>
            <div class="modal-body">
                <form method="post" action="manage_expenses.php" class="form-horizontal form-label-left">
                    <input type="hidden" name="action" value="AddExpense">
                    <div class="form-group">
                        <label class="control-label col-md-2 col-sm-2 col-xs-12">Category <span class="required">*</span></label>
                        <div class="col-md-4 col-sm-4 col-xs-12">
                            <select id="modal_category_select" name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($cats as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo e($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <label class="control-label col-md-2 col-sm-2 col-xs-12">Sub Category</label>
                        <div class="col-md-4 col-sm-4 col-xs-12">
                            <select name="sub_category_id" id="subcatgory" class="form-control">
                                <option value="">Select</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-md-2 col-sm-2 col-xs-12">Expense Date <span class="required">*</span></label>
                        <div class="col-md-4 col-sm-4 col-xs-12">
                            <input type="date" name="expense_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <label class="control-label col-md-2 col-sm-2 col-xs-12">Paid By <span class="required">*</span></label>
                        <div class="col-md-4 col-sm-4 col-xs-12">
                            <select name="paid_by" class="form-control" required>
                                <?php foreach ($paidByOptions as $pb): ?>
                                    <option value="<?php echo $pb; ?>"><?php echo ($pb === 'BankAccount') ? 'Bank Account' : $pb; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-md-2 col-sm-2 col-xs-12">Narration / Details</label>
                        <div class="col-md-4 col-sm-4 col-xs-12">
                            <input class="form-control" name="narration" placeholder="eg Repairing & Fixes">
                        </div>
                        <label class="control-label col-md-2 col-sm-2 col-xs-12">Expense Amount <span class="required">*</span></label>
                        <div class="col-md-4 col-sm-4 col-xs-12">
                            <input class="form-control" name="amount" required autocomplete="off" type="number" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-md-6 col-md-offset-5">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Expense Modal -->
<div id="EditExpense" class="modal fade" role="dialog">
    <div class="modal-dialog" style="width:850px; max-width:95%;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">×</button>
                <h3 class="modal-title" style="text-align:center;"><i class="fa fa-edit"></i> Edit Expense</h3>
            </div>
            <div class="modal-body">
                <form method="post" action="manage_expenses.php" class="form-horizontal form-label-left">
                    <input type="hidden" name="action" value="UpdateExpense">
                    <input type="hidden" name="expense_id" id="edit_expense_id" value="">
                    <div class="form-group">
                        <label class="control-label col-md-2 col-sm-2 col-xs-12">Category <span class="required">*</span></label>
                        <div class="col-md-4 col-sm-4 col-xs-12">
                            <select id="edit_category" name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($cats as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo e($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <label class="control-label col-md-2 col-sm-2 col-xs-12">Sub Category</label>
                        <div class="col-md-4 col-sm-4 col-xs-12">
                            <select name="sub_category_id" id="edit_subcategory" class="form-control">
                                <option value="">Select</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-md-2 col-sm-2 col-xs-12">Expense Date <span class="required">*</span></label>
                        <div class="col-md-4 col-sm-4 col-xs-12">
                            <input type="date" id="edit_edate" name="expense_date" class="form-control" required>
                        </div>
                        <label class="control-label col-md-2 col-sm-2 col-xs-12">Paid By <span class="required">*</span></label>
                        <div class="col-md-4 col-sm-4 col-xs-12">
                            <select name="paid_by" id="edit_PiadBy" class="form-control" required>
                                <?php foreach ($paidByOptions as $pb): ?>
                                    <option value="<?php echo $pb; ?>"><?php echo ($pb === 'BankAccount') ? 'Bank Account' : $pb; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-md-2 col-sm-2 col-xs-12">Narration / Details</label>
                        <div class="col-md-4 col-sm-4 col-xs-12">
                            <input class="form-control" id="edit_detail" name="narration" placeholder="eg Repairing & Fixes">
                        </div>
                        <label class="control-label col-md-2 col-sm-2 col-xs-12">Expense Amount <span class="required">*</span></label>
                        <div class="col-md-4 col-sm-4 col-xs-12">
                            <input class="form-control" id="edit_eamount" name="amount" required autocomplete="off" type="number" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-md-6 col-md-offset-5">
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js"></script>
<script>
var PAID_BY = <?php echo json_encode($paidByOptions); ?>;
var SUBS = <?php echo json_encode($subByCat); ?>;
var EXPENSES = <?php echo json_encode($expenses, JSON_UNESCAPED_UNICODE); ?>;

function fillSubCategory(select, catId, selectedVal) {
    select.innerHTML = '';
    var opt = document.createElement('option');
    opt.value = ''; opt.textContent = 'Select';
    select.appendChild(opt);
    var list = SUBS[catId] || [];
    list.forEach(function(s){
        var o = document.createElement('option');
        o.value = s.id; o.textContent = s.name;
        if (selectedVal !== undefined && String(s.id) === String(selectedVal)) o.selected = true;
        select.appendChild(o);
    });
}

$(document).ready(function() {
    $('#listofstudents').DataTable({
        order: [],
        pageLength: 10,
        scrollX: true,
        scrollCollapse: true
    });
});

// Filter panel category -> sub category
$('#filter_category_select').on('change', function(){
    var val = $(this).val();
    var sel = document.getElementById('viewlistsubcatgory');
    sel.innerHTML = '';
    var opt = document.createElement('option'); opt.value = 'All'; opt.textContent = 'All'; sel.appendChild(opt);
    if (val && val !== 'All') {
        (SUBS[val] || []).forEach(function(s){ var o = document.createElement('option'); o.value = s.id; o.textContent = s.name; sel.appendChild(o); });
    }
});
// Preselect filter sub category when page loads with query params
(function(){
    var fSub = <?php echo json_encode($fSubCat); ?>;
    var sel = document.getElementById('viewlistsubcatgory');
    if (fSub && fSub !== 'All') {
        var existing = Array.prototype.slice.call(sel.options).find(function(o){ return o.value === fSub; });
        if (!existing) {
            var fCat = <?php echo json_encode($fCategory); ?>;
            if (fCat && fCat !== 'All' && SUBS[fCat]) {
                SUBS[fCat].forEach(function(s){ var o = document.createElement('option'); o.value = s.id; o.textContent = s.name; sel.appendChild(o); });
            }
        }
    }
})();

// Add modal
$('#modal_category_select').on('change', function(){
    fillSubCategory(document.getElementById('subcatgory'), $(this).val());
});

// Edit modal open
function openEdit(id) {
    var x = EXPENSES.find(function(r){ return r.expense_id === id; });
    if (!x) return;
    document.getElementById('edit_expense_id').value = x.expense_id;
    document.getElementById('edit_category').value = x.category_id || '';
    fillSubCategory(document.getElementById('edit_subcategory'), x.category_id);
    document.getElementById('edit_subcategory').value = x.sub_category_id || '';
    document.getElementById('edit_edate').value = x.expense_date || '';
    document.getElementById('edit_PiadBy').value = x.paid_by || 'Cash';
    document.getElementById('edit_detail').value = x.narration || x.title || '';
    document.getElementById('edit_eamount').value = x.amount;
    $('#EditExpense').modal('show');
}
$('#edit_category').on('change', function(){
    var catId = $(this).val();
    var curr = document.getElementById('edit_subcategory').value;
    fillSubCategory(document.getElementById('edit_subcategory'), catId);
    if (curr) document.getElementById('edit_subcategory').value = curr;
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>