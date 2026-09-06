<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Create Classwise Parents IDs';

db_query("CREATE TABLE IF NOT EXISTS parent_access (
    access_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    username VARCHAR(191),
    password VARCHAR(255),
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$message = '';
$error = '';
$summary = [];

function hifi_password($name) {
    $prefix = strtoupper(substr(trim($name), 0, 3));
    if ($prefix === '') { $prefix = 'PAR'; }
    return $prefix . rand(100000, 999999);
}

$existing = [];
$res = db_query("SELECT student_id FROM parent_access");
while ($row = $res->fetch_assoc()) { $existing[(int) $row['student_id']] = 1; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'batch_generate') {
    $class_id = (int) ($_POST['class_id'] ?? 0);
    if ($class_id <= 0) {
        $error = 'Please select a class to generate parent login IDs.';
    } else {
        $cls = null;
        $st = db_prepare("SELECT class_id, class_name FROM classes WHERE class_id = ?");
        $st->bind_param('i', $class_id);
        $st->execute();
        $r = $st->get_result();
        if ($row = $r->fetch_assoc()) { $cls = $row; }

        if (!$cls) {
            $error = 'Selected class not found.';
        } else {
            $st = db_prepare("SELECT s.student_id, s.first_name, s.last_name, s.father_name, s.email, s.phone, s.father_cellno
                              FROM students s WHERE s.class_id = ? AND s.status = 1 ORDER BY s.first_name");
            $st->bind_param('i', $class_id);
            $st->execute();
            $r = $st->get_result();
            $rowList = [];
            while ($row = $r->fetch_assoc()) { $rowList[] = $row; }

            $total = count($rowList);
            $already = 0;
            $created = 0;
            $empty = 0;
            $used = [];
            foreach ($rowList as $stRow) {
                $sid = (int) $stRow['student_id'];
                if (isset($existing[$sid])) { $already++; continue; }
                $username = '';
                if (!empty($stRow['father_cellno'])) { $username = $stRow['father_cellno']; }
                elseif (!empty($stRow['phone'])) { $username = $stRow['phone']; }
                elseif (!empty($stRow['email'])) { $username = $stRow['email']; }
                else { $username = 'parent' . $sid; }
                if (in_array($username, $used, true)) { $username = $username . '-' . $sid; }
                $used[] = $username;
                if ($username === '' ) { $empty++; continue; }
                $password = hifi_password($stRow['father_name']);
                $ins = db_prepare("INSERT INTO parent_access (student_id, username, password, status) VALUES (?, ?, ?, 1)");
                $ins->bind_param('iss', $sid, $username, $password);
                $ins->execute();
                $created++;
                $existing[$sid] = 1;
            }
            $summary = [
                'class_id' => (int) $cls['class_id'],
                'class_name' => $cls['class_name'],
                'total' => $total,
                'already' => $already,
                'created' => $created,
                'empty' => $empty,
            ];
            $message = 'Login IDs generated for class <strong>' . e($cls['class_name']) . '</strong>: <strong>' . $created . '</strong> created, <strong>' . $already . '</strong> already had IDs.';
        }
    }
}

$classes = [];
$res = db_query("SELECT c.class_id, c.class_name,
                 (SELECT COUNT(*) FROM students s WHERE s.class_id = c.class_id AND s.status = 1) AS total_students,
                 (SELECT COUNT(*) FROM students s WHERE s.class_id = c.class_id AND s.status = 1 AND EXISTS (SELECT 1 FROM parent_access pa WHERE pa.student_id = s.student_id)) AS with_ids
                 FROM classes c WHERE c.status = 1 ORDER BY c.class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<div class="main-content">
    <div class="container-fluid">

        <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i>  &nbsp;
        Create Classwise Parent's ID's
        <br><br>

        <?php if ($message !== ''): ?>
            <div class="alert alert-success">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <h3 style="float: left;margin-left:8px;">Create Class Wise Parents Login IDs </h3>
        </div>

        <div class="row" style="clear:both;">
            <form method="post" action="<?php echo BASE_URL; ?>create_classwise_parents_ids.php" class="form-inline" style="background:#fff; border:1px solid #E5E7EB; border-radius:12px; padding:14px 16px; margin-bottom:14px;">
                <input type="hidden" name="action" value="batch_generate">
                <div class="form-group" style="margin-right:8px;">
                    <label>Batch Generate for Class</label>
                    <select name="class_id" class="form-control" style="min-width:220px;" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?> (<?php echo (int) $c['total_students']; ?> students)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-success" style="margin-top:22px;"><i class="fa fa-users"></i> Generate Login IDs for Selected Class</button>
            </form>
        </div>

        <div class="row">
            <table class="table table-striped table-bordered" style="width:100%;background-color:#FFFFFF;">
                <thead>
                    <tr>
                        <th width="5%" style="text-align:center;"> S.No </th>
                        <th width="50%"> Class </th>
                        <th width="35%" style="text-align:center;">Action </th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sn = 0; foreach ($classes as $c): $sn++; ?>
                        <tr>
                            <td style="text-align:center;"> <?php echo $sn; ?> </td>
                            <td> <?php echo e($c['class_name']); ?>
                                <span style="color:#8a94a6; font-size:12px;">(<?php echo (int) $c['total_students']; ?> students, <?php echo (int) $c['with_ids']; ?> have IDs)</span>
                            </td>
                            <td style="text-align:center;">
                                <a style="padding: 6px 8px; font-size:14px;" href="<?php echo BASE_URL; ?>parents_access.php?class_id=<?php echo $c['class_id']; ?>" style="cursor:pointer;" target="_blank" class="btn btn-success"> Create Login IDs </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($classes) === 0): ?>
                        <tr><td colspan="3" style="text-align:center; padding:30px; color:#6b7280;">No classes found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (count($summary) > 0): ?>
            <div class="row" style="margin-top:24px;">
                <h4 style="margin-left:8px;">Summary for <?php echo e($summary['class_name']); ?></h4>
                <table class="table table-striped table-bordered" style="width:100%;background-color:#FFFFFF;">
                    <thead>
                        <tr>
                            <th width="5%" style="text-align:center;"> S.No </th>
                            <th> Class </th>
                            <th style="text-align:center;"> Total Students </th>
                            <th style="text-align:center;"> Already Have IDs </th>
                            <th style="text-align:center;"> Newly Created </th>
                            <th style="text-align:center;"> Skipped (No Contact) </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align:center;">1</td>
                            <td><?php echo e($summary['class_name']); ?></td>
                            <td style="text-align:center;"><?php echo $summary['total']; ?></td>
                            <td style="text-align:center;"><?php echo $summary['already']; ?></td>
                            <td style="text-align:center;"><?php echo $summary['created']; ?></td>
                            <td style="text-align:center;"><?php echo $summary['empty']; ?></td>
                        </tr>
                    </tbody>
                </table>
                <div style="padding:8px 12px; font-size:12.5px; color:#6b7280;">ID format: HIFI-&lt;student_id&gt;. Username is the parent cell number / email and password is generated from father name + random digits.</div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>