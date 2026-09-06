<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Attach Documents';

db_query("CREATE TABLE IF NOT EXISTS student_documents (
  doc_id INT(11) NOT NULL AUTO_INCREMENT,
  student_id INT(11) DEFAULT NULL,
  doc_type VARCHAR(150) DEFAULT NULL,
  doc_file VARCHAR(255) DEFAULT NULL,
  file_path VARCHAR(255) DEFAULT NULL,
  uploaded_by INT(11) DEFAULT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

db_query("CREATE TABLE IF NOT EXISTS document_titles (
  id INT(11) NOT NULL AUTO_INCREMENT,
  name VARCHAR(191) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$message = '';
$error = '';

$sel_student = (int) ($_GET['student_id'] ?? 0);
$edit_doc_id = (int) ($_GET['id'] ?? 0);
$edit_doc_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddDocumentType') {
        $name = trim($_POST['document_type'] ?? '');
        if ($name === '') {
            $error = 'Document type name is required.';
        } else {
            $st = db_prepare('INSERT INTO document_titles (name) VALUES (?)');
            $st->bind_param('s', $name);
            $st->execute();
            $message = 'Document type added successfully.';
        }
    }

    if ($action === 'EditDocumentType') {
        $id = (int) ($_POST['doc_type_id'] ?? 0);
        $name = trim($_POST['document_type'] ?? '');
        if ($id <= 0 || $name === '') {
            $error = 'Document type name is required.';
        } else {
            $st = db_prepare('UPDATE document_titles SET name = ? WHERE id = ?');
            $st->bind_param('si', $name, $id);
            $st->execute();
            $message = 'Document type updated successfully.';
        }
    }

    if ($action === 'DeleteDocumentType') {
        $id = (int) ($_POST['doc_type_id'] ?? 0);
        if ($id > 0) {
            $st = db_prepare('DELETE FROM document_titles WHERE id = ?');
            $st->bind_param('i', $id);
            $st->execute();
            $message = 'Document type deleted successfully.';
        }
    }

    if ($action === 'UploadDocument') {
        $sid = (int) ($_POST['student_id'] ?? 0);
        $dtype = trim($_POST['doc_type'] ?? '');
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        if ($sid <= 0 || $dtype === '') {
            $error = 'Please select a student and a document type.';
        } elseif (empty($_FILES['attach_document']['name']) || ($_FILES['attach_document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Please choose a file to upload.';
        } else {
            $ext = strtolower(pathinfo($_FILES['attach_document']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                $error = 'Only PDF, JPG, JPEG and PNG files are allowed.';
            } else {
                $dir = __DIR__ . '/assets/uploads/documents';
                if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                $fname = 'doc_' . $sid . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                if (!@move_uploaded_file($_FILES['attach_document']['tmp_name'], $dir . '/' . $fname)) {
                    $error = 'File could not be uploaded.';
                } else {
                    $orig = basename($_FILES['attach_document']['name']);
                    $rel = 'assets/uploads/documents/' . $fname;
                    $st = db_prepare('INSERT INTO student_documents (student_id, doc_type, doc_file, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)');
                    $st->bind_param('isssi', $sid, $dtype, $orig, $rel, $uid);
                    $st->execute();
                    $sel_student = $sid;
                    $message = 'Document uploaded successfully.';
                }
            }
        }
    }

    if ($action === 'DeleteDocument') {
        $did = (int) ($_POST['doc_id'] ?? 0);
        if ($did > 0) {
            $st = db_prepare('SELECT file_path FROM student_documents WHERE doc_id = ?');
            $st->bind_param('i', $did);
            $st->execute();
            $r = $st->get_result();
            if ($row = $r->fetch_assoc()) {
                if (!empty($row['file_path'])) {
                    $full = __DIR__ . '/' . $row['file_path'];
                    if (is_file($full)) { @unlink($full); }
                }
            }
            $st = db_prepare('DELETE FROM student_documents WHERE doc_id = ?');
            $st->bind_param('i', $did);
            $st->execute();
            $message = 'Document deleted successfully.';
        }
    }
}

if ($edit_doc_id > 0) {
    $st = db_prepare('SELECT id, name FROM document_titles WHERE id = ?');
    $st->bind_param('i', $edit_doc_id);
    $st->execute();
    $r = $st->get_result();
    if ($row = $r->fetch_assoc()) {
        $edit_doc_id = (int) $row['id'];
        $edit_doc_name = $row['name'];
    }
}

$docTitles = [];
$res = db_query('SELECT id, name FROM document_titles ORDER BY name');
while ($row = $res->fetch_assoc()) { $docTitles[] = $row; }

$students = [];
$res = db_query('SELECT s.student_id, s.first_name, s.last_name, s.gr_no, s.class_id, s.roll_no, c.class_name FROM students s LEFT JOIN classes c ON s.class_id = c.class_id WHERE s.status = 1 ORDER BY c.class_name, s.first_name');
while ($row = $res->fetch_assoc()) { $students[] = $row; }

$docs = [];
if ($sel_student > 0) {
    $st = db_prepare('SELECT d.*, u.full_name FROM student_documents d LEFT JOIN users u ON d.uploaded_by = u.user_id WHERE d.student_id = ? ORDER BY d.doc_id DESC');
    $st->bind_param('i', $sel_student);
    $st->execute();
    $r = $st->get_result();
    while ($row = $r->fetch_assoc()) { $docs[] = $row; }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.attach-box { padding:3%; }
.attach-table td, .attach-table th { font-size:12px; }
.heding_name { margin-top:0; }
</style>

<div class="row" style="background-color:white;">
  <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i>  &nbsp;
    <a href="<?php echo BASE_URL; ?>student_inquiry.php">Student Inquiry</a>
    &nbsp; <i style="" class="fa fa-angle-double-right"></i>  &nbsp;
    Attach Documents
    <br><br>

  <?php if ($message !== ''): ?>
    <div class="alert alert-success alert-dismissible fade in">
        <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
        <?php echo e($message); ?>
    </div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade in">
        <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
        <?php echo e($error); ?>
    </div>
  <?php endif; ?>

<section class="add_sub_agent" id="table_sub_agent">
  <div class="container">
   <div class="box_visa_form">
    <div class="attach-box">

<div class="col-md-12 ">
  <h3>Attach Documents To Student</h3>
  <form class="form-inline" method="get" action="<?php echo BASE_URL; ?>attach_documents.php">
    <div class="form-group" style="margin-right:8px;">
      <label>Student</label>
      <select name="student_id" class="form-control" style="min-width:280px;">
        <option value="0">Select Student</option>
        <?php foreach ($students as $stu): ?>
          <option value="<?php echo (int) $stu['student_id']; ?>" <?php echo $sel_student === (int) $stu['student_id'] ? 'selected' : ''; ?>><?php echo e(trim($stu['first_name'] . ' ' . $stu['last_name'])); ?> (<?php echo e($stu['class_name'] ?? '-'); ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">View Documents</button>
  </form>
  <div class="clearfix"></div>
</div>

<br>

<?php if ($sel_student > 0): ?>
<div class="col-md-12" style="padding:0;">
  <table class="table table-bordered table-hover text-center attach-table" style="width:100%;">
    <thead>
      <tr class="" style="color:#e2333b;">
        <td> S.No </td>
        <td> Document Name </td>
        <td> File </td>
        <td> Uploaded At </td>
        <td> Action </td>
      </tr>
    </thead>
    <tbody>
      <?php if (count($docs) === 0): ?>
        <tr><td colspan="5" style="padding:20px;font-size:12px;color:#6b7280;">No documents uploaded for this student yet.</td></tr>
      <?php endif; ?>
      <?php $i = 1; foreach ($docs as $d): ?>
      <tr>
        <td style="padding:1px;font-size:12px;"><?php echo $i; ?></td>
        <td style="padding:1px;font-size:12px;"><?php echo e($d['doc_type']); ?></td>
        <td style="padding:1px;font-size:12px;"><?php echo e($d['doc_file'] ?: basename($d['file_path'])); ?></td>
        <td style="padding:1px;font-size:12px;"><?php echo date('d-M-Y h:i A', strtotime($d['uploaded_at'])); ?></td>
        <td style="padding:1px;font-size:12px;">
          <?php if (!empty($d['file_path'])): ?>
            <a href="<?php echo BASE_URL . e($d['file_path']); ?>" target="_blank" style="padding:0px 5px;" class="btn btn-info"><i class="fa fa-external-link"></i></a>
          <?php endif; ?>
          <form method="post" action="<?php echo BASE_URL; ?>attach_documents.php?student_id=<?php echo $sel_student; ?>" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this record');">
            <input type="hidden" name="action" value="DeleteDocument">
            <input type="hidden" name="doc_id" value="<?php echo (int) $d['doc_id']; ?>">
            <button type="submit" style="padding:0px 5px; font-size:14px;" class="btn btn-danger"><i class="fa fa-remove"></i></button>
          </form>
        </td>
      </tr>
      <?php $i++; endforeach; ?>
    </tbody>
  </table>

  <div class="addclasswidth">
    <form class="form-style-7" action="<?php echo BASE_URL; ?>attach_documents.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="UploadDocument">
      <input type="hidden" name="student_id" value="<?php echo (int) $sel_student; ?>">
      <ul style="list-style-type:none;">
        <div class="col-md-12" style=""> <h3 class="heding_name">Upload Document </h3></div>
        <div class="clearfix"></div>
        <hr>
        <li>
          <label for=""> Document Type </label>
          <select name="doc_type" class="inputfield" required>
            <option value="">Select Document Type</option>
            <?php foreach ($docTitles as $dt): ?>
              <option value="<?php echo e($dt['name']); ?>"><?php echo e($dt['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </li>
        <li>
          <label for=""> File (PDF, JPG, JPEG, PNG)</label>
          <input type="file" name="attach_document" class="inputfield" accept=".pdf,.jpg,.jpeg,.png" required>
        </li>
        <div class="clearfix"></div>
        <li class="pull-left" style="margin-top:30px;">
          <input type="submit" value=" Upload " class="btn btn-primary">
        </li>
      </ul>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="clearfix"></div>

<div class="col-md-12 ">
  <div class="clearfix"></div>
</div>
<div class="clearfix"></div>
<hr>

<div class="col-md-12 ">
  <div class="col-md-6 ">
    <h3>Manage Document Type</h3>
  </div>
</div>

<table class="table table-bordered table-hover text-center" style="width:60%;float: left;">
<thead>
<tr class="" style="color:#e2333b;">
<td> Document Name</td>
<td> Action</td>
</tr>
</thead>
<tbody>
  <?php if (count($docTitles) === 0): ?>
    <tr><td colspan="2" style="padding:20px;font-size:12px;color:#6b7280;">No document types added yet.</td></tr>
  <?php endif; ?>
  <?php foreach ($docTitles as $dt): ?>
<tr>
<td style="padding:1px;font-size:12px;"><?php echo e($dt['name']); ?></td>
<td style="padding:1px;font-size:12px;">
  <a href="<?php echo BASE_URL; ?>attach_documents.php?id=<?php echo (int) $dt['id']; ?>" style="padding:0px 5px;" class="btn btn-success"><i class="fa fa-pencil"></i></a>
  <form method="post" action="<?php echo BASE_URL; ?>attach_documents.php" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this record');">
    <input type="hidden" name="action" value="DeleteDocumentType">
    <input type="hidden" name="doc_type_id" value="<?php echo (int) $dt['id']; ?>">
    <button type="submit" style="padding: 0px 5px; font-size:14px;" class="btn btn-danger"><i class="fa fa-remove"></i></button>
  </form>
</td>
</tr>
  <?php endforeach; ?>
</tbody>
</table>

<div class="addclasswidth">
  <form class="form-style-7" action="<?php echo BASE_URL; ?>attach_documents.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="<?php echo $edit_doc_id > 0 ? 'EditDocumentType' : 'AddDocumentType'; ?>">
    <?php if ($edit_doc_id > 0): ?>
      <input type="hidden" name="doc_type_id" value="<?php echo (int) $edit_doc_id; ?>">
    <?php endif; ?>
    <ul style="list-style-type:none;">
      <div class="col-md-12" style=""> <h3 class="heding_name"><?php echo $edit_doc_id > 0 ? 'Edit Document Type' : 'Add Document Type'; ?></h3></div>
      <div class="clearfix"></div>
      <hr>
      <li>
        <label for=""> Document Type Name </label>
        <input type="text" name="document_type" class="inputfield" value="<?php echo e($edit_doc_name); ?>" autocomplete="off" required>
      </li>
      <div class="clearfix"></div>
      <li class="pull-left" style="margin-top:30px;">
        <input type="submit" value=" Submit " class="btn btn-primary">
      </li>
    </ul>
  </form>
</div>

<div class="clearfix"></div>

    </div>
    <div class="clearfix"></div>
   </div>
  </div>
  <div class="clearfix"></div>
</section>

</div>
<?php include __DIR__ . '/includes/footer.php'; ?>