<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Upload Signature';

if (!function_exists('hifi_save_setting')) {
    function hifi_save_setting($key, $value) {
        $st = db_prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $st->bind_param('ss', $key, $value);
        $st->execute();
    }
}

$message = '';
$error = '';

$dirUploads = __DIR__ . '/assets/uploads';
if (!is_dir($dirUploads)) { @mkdir($dirUploads, 0775, true); }

if (!function_exists('handle_signature_upload')) {
    function handle_signature_upload($fieldName, $settingKey) {
        global $dirUploads;
        $current = get_setting($settingKey, '');
        if (empty($_FILES[$fieldName]['name']) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $current;
        }
        $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
        $size = (int) $_FILES[$fieldName]['size'];
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true) || $size > (600 * 1024)) {
            throw new Exception('Only JPG/PNG files up to 600KB are allowed.');
        }
        $name = str_replace([' ', '.'], '', strtolower($settingKey)) . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        if (!@move_uploaded_file($_FILES[$fieldName]['tmp_name'], $dirUploads . '/' . $name)) {
            throw new Exception('File could not be uploaded.');
        }
        if ($current !== '' && is_file($dirUploads . '/' . basename($current))) {
            @unlink($dirUploads . '/' . basename($current));
        }
        return 'assets/uploads/' . $name;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'UploadSignature') {
        try {
            if (!empty($_POST['remove_signature'])) {
                $remove = $_POST['remove_signature'];
                $map = [
                    'principal' => 'signature_image',
                    'controller' => 'exam_controller_signature',
                    'header' => 'document_header_image',
                ];
                if (isset($map[$remove])) {
                    $old = get_setting($map[$remove], '');
                    if ($old !== '' && is_file($dirUploads . '/' . basename($old))) {
                        @unlink($dirUploads . '/' . basename($old));
                    }
                    hifi_save_setting($map[$remove], '');
                    $message = 'Signature removed successfully.';
                }
            } else {
                $sigPrincipal = handle_signature_upload('img_filePrinciplenew', 'signature_image');
                $sigController = handle_signature_upload('img_fileExamControlnew', 'exam_controller_signature');
                $sigHeader = handle_signature_upload('img_fileHeadernew', 'document_header_image');

                hifi_save_setting('signature_image', $sigPrincipal);
                hifi_save_setting('exam_controller_signature', $sigController);
                hifi_save_setting('document_header_image', $sigHeader);
                hifi_save_setting('teacher_exam_controller', trim($_POST['teacherExamController'] ?? 'Teacher'));
                hifi_save_setting('inquiry_instructions', trim($_POST['inquiry_instructions'] ?? ''));
                hifi_save_setting('result_card_instructions', trim($_POST['result_card_instructions'] ?? ''));
                $message = 'Settings saved successfully.';
            }
        } catch (Exception $ex) {
            $error = $ex->getMessage();
        }
    }
}

$sigPrincipal = get_setting('signature_image', '');
$sigController = get_setting('exam_controller_signature', '');
$sigHeader = get_setting('document_header_image', '');
$teacherExamController = get_setting('teacher_exam_controller', 'Teacher');
$inquiryInstructions = get_setting('inquiry_instructions', '');
$resultCardInstructions = get_setting('result_card_instructions', '');
$blankSig = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

include __DIR__ . '/includes/header.php';
?>
<style>
    .breadcrumb-bar {
      background: transparent;
      padding: 0;
      margin: 0 0 14px;
      font-size: 13px;
      color: #999;
    }
    .breadcrumb-bar a { color: #999; text-decoration: none; }
    .breadcrumb-bar a:hover { color: #ff7800; }
    .breadcrumb-bar span.sep { margin: 0 6px; color: #ccc; }
    .breadcrumb-bar span.current { color: #333; font-weight: 600; }

    .page-head-card {
      background: #fff;
      border-radius: 10px;
      padding: 20px 24px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.06);
      margin-bottom: 18px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      flex-wrap: wrap;
      gap: 16px;
    }
    .page-head-card .head-left { display: flex; gap: 14px; align-items: center; }
    .page-head-card .head-icon {
      width: 46px; height: 46px; border-radius: 12px;
      background: #fff1e6; color: #ff7800;
      display: flex; align-items: center; justify-content: center;
      font-size: 20px; flex-shrink: 0;
    }
    .page-head-card h1 { margin: 0; font-size: 20px; font-weight: 700; color: #222; }
    .page-head-card p { margin: 3px 0 0; font-size: 13px; color: #888; }

    .quick-links { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .quick-links a {
      font-size: 12.5px; font-weight: 500; padding: 7px 12px; border-radius: 6px;
      border: 1px solid #eee; color: #555; text-decoration: none; background: #fafafa;
      white-space: nowrap;
    }
    .quick-links a:hover { border-color: #ff7800; color: #ff7800; background: #fff7f0; }

    .settings-card {
      background: #fff;
      border-radius: 10px;
      padding: 24px 26px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.06);
      margin-bottom: 20px;
    }
    .settings-card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
    .settings-card-header .icon-box {
      width: 38px; height: 38px; border-radius: 9px;
      display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;
    }
    .settings-card-header.sig .icon-box { background: #e8f0fe; color: #2563eb; }
    .settings-card-header.instr .icon-box { background: #f3e8fd; color: #7c3aed; }
    .settings-card-header h3 { margin: 0; font-size: 16.5px; font-weight: 600; color: #222; }
    .settings-card-header p { margin: 2px 0 0; font-size: 12.5px; color: #888; }

    .sig-row {
      display: flex; align-items: center; gap: 18px; padding: 18px 0;
      border-bottom: 1px solid #f0f0f0; flex-wrap: wrap;
    }
    .sig-row:last-child { border-bottom: none; }

    .sig-icon {
      width: 42px; height: 42px; border-radius: 10px; background: #fff1e6; color: #ff7800;
      display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;
    }

    .sig-info { flex: 0 0 250px; }
    .sig-info strong { display: block; font-size: 14px; color: #222; }
    .sig-info span { display: block; font-size: 12px; color: #929292; margin-top: 2px; line-height: 1.4; }

    .sig-controls { flex: 1; display: flex; gap: 14px; align-items: center; flex-wrap: wrap; min-width: 260px; }

    .upload-box {
      border: 1.5px dashed #dcdcdc; border-radius: 8px; padding: 12px 18px; text-align: center;
      cursor: pointer; flex: 1; min-width: 190px; background: #fafafa; transition: all .15s ease;
    }
    .upload-box:hover { border-color: #ff7800; background: #fff7f0; }
    .upload-box i { font-size: 17px; color: #999; display: block; margin-bottom: 4px; }
    .upload-box:hover i { color: #ff7800; }
    .upload-box .up-title { font-size: 12.5px; color: #555; font-weight: 500; }
    .upload-box .up-sub { font-size: 11px; color: #aaa; }
    .upload-box input[type=file] { display: none; }

    .preview-box {
      width: 92px; height: 68px; border: 1px solid #eee; border-radius: 8px;
      display: flex; align-items: center; justify-content: center; overflow: hidden;
      background: #fff; flex-shrink: 0;
    }
    .preview-box img { max-width: 100%; max-height: 100%; object-fit: contain; }

    .remove-form { display:inline-block; }
    .remove-link {
      color: #e74c3c; font-size: 12.5px; font-weight: 600; text-decoration: none; white-space: nowrap;
      background: none; border: none; padding: 0; cursor: pointer;
    }
    .remove-link:hover { text-decoration: underline; }
    .remove-link i { margin-right: 3px; }

    .field-error { color: #e74c3c; display: none; font-size: 11.5px; flex-basis: 100%; margin-top: -6px; }

    .sig-controls select.form-control { max-width: 460px; }

    .char-count { text-align: right; font-size: 11.5px; color: #999; margin-top: 6px; }

    .form-actions-bar {
      display: flex; align-items: center; gap: 14px; padding: 4px 2px 20px;
    }
    .btn-save-changes {
      background: #ff7800; border-color: #ff7800; color: #fff; font-weight: 600;
      padding: 10px 24px; border-radius: 6px;
    }
    .btn-save-changes:hover { background: #e56d00; border-color: #e56d00; color: #fff; }
    .btn-reset-default {
      background: #fff; border: 1px solid #ddd; color: #555; font-weight: 500;
      padding: 9px 20px; border-radius: 6px;
    }
    .btn-reset-default:hover { border-color: #ff7800; color: #ff7800; }
    .instr-textarea { width:100%; min-height:150px; border:1px solid #e9ecef; border-radius:8px; padding:12px; font-size:14px; }
</style>

<nav class="breadcrumb-bar">
  <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a>
  <span class="sep"><i class="fa fa-angle-right"></i></span>
  <a href="<?php echo BASE_URL; ?>academic_setup.php">Academic Settings</a>
  <span class="sep"><i class="fa fa-angle-right"></i></span>
  <span class="current">Upload Signature</span>
</nav>

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

<div class="page-head-card">
  <div class="head-left">
    <div class="head-icon"><i class="fa fa-cog"></i></div>
    <div>
      <h1>Academic Settings</h1>
      <p>Manage academic documents, signatures and instructions that will be used across the system.</p>
    </div>
  </div>
  <div class="quick-links">
    <a href="<?php echo BASE_URL; ?>performance_settings.php"><i class="fa fa-sliders"></i> Academic Performance Criteria</a>
    <a href="<?php echo BASE_URL; ?>add_acdamic_heads.php"><i class="fa fa-sitemap"></i> Academic Performance Heads</a>
    <a href="<?php echo BASE_URL; ?>add_student_performance_marks.php"><i class="fa fa-plus"></i> Add Academic Performance</a>
  </div>
</div>

<div class="nav-container">
  <div class="nav-bar">

    <a href="<?php echo BASE_URL; ?>manage_exams.php" class="nav-item ">
        <i class="fa fa fa-plus"></i>Manage Exams
    </a>

    <a href="<?php echo BASE_URL; ?>subjects.php" class="nav-item ">
        <i class="fa fa-book"></i>Manage Subjects
    </a>

    <a href="<?php echo BASE_URL; ?>class_subjects.php" class="nav-item ">
        <i class="fa fa-layer-group"></i> Class Subjects
    </a>

    <a href="<?php echo BASE_URL; ?>teacher_subjects_allocation.php" class="nav-item ">
        <i class="fa fa-chalkboard-teacher"></i> Teacher Subjects
    </a>

    <a href="<?php echo BASE_URL; ?>create_awardList.php" class="nav-item ">
        <i class="fa fa-list"></i> Award List
    </a>

    <a href="<?php echo BASE_URL; ?>grades_marks.php" class="nav-item ">
        <i class="fa fa fa-star"></i> Grade Settings
    </a>

    <a href="<?php echo BASE_URL; ?>upload_signature.php" class="nav-item active">
        <i class="fa fa-signature"></i> Academic Settings
    </a>

    <a href="<?php echo BASE_URL; ?>manage_classes.php" class="nav-item ">
        <i class="fa fa-users"></i> Class &amp; Sections
    </a>

  </div>
</div>
<br>

<form id="add_emp" action="<?php echo BASE_URL; ?>upload_signature.php" method="post" enctype="multipart/form-data">
  <input type="hidden" name="action" value="UploadSignature">

  <div class="settings-card">
    <div class="settings-card-header sig">
      <div class="icon-box"><i class="fa fa-pencil"></i></div>
      <div>
        <h3>Signatures &amp; Documents</h3>
        <p>Upload and manage signatures and document header.</p>
      </div>
    </div>

    <div class="sig-row">
      <div class="sig-icon"><i class="fa fa-pencil"></i></div>
      <div class="sig-info">
        <strong>Principal Signature</strong>
        <span>Upload your signature which will appear on reports, result cards and other documents.</span>
      </div>
      <div class="sig-controls">
        <label class="upload-box" for="img_filePrinciple">
          <i class="fa fa-cloud-upload"></i>
          <span class="up-title">Click to upload</span>
          <span class="up-sub">PNG, JPG or JPEG (Max 600KB)</span>
          <input type="file" name="img_filePrinciplenew" id="img_filePrinciple" accept=".png,.jpg,.jpeg" />
        </label>
        <input type="hidden" name="old_filePrinciple" id="old_filePrinciple" value="<?php echo e($sigPrincipal); ?>" />

        <div class="preview-box">
          <img src="<?php echo $sigPrincipal !== '' ? BASE_URL . e($sigPrincipal) : $blankSig; ?>" id="previewPrincipal" />
        </div>

        <form method="post" action="<?php echo BASE_URL; ?>upload_signature.php" class="remove-form" onsubmit="return confirm('Are you sure you want to remove the Principal Signature?');">
          <input type="hidden" name="action" value="UploadSignature">
          <input type="hidden" name="remove_signature" value="principal">
          <button type="submit" class="remove-link"><i class="fa fa-times"></i> Remove</button>
        </form>

        <span class="field-error" id="errPrincipal">Only JPG/PNG files up to 600KB are allowed.</span>
      </div>
    </div>

    <div class="sig-row">
      <div class="sig-icon"><i class="fa fa-user"></i></div>
      <div class="sig-info">
        <strong>Exam Controller / Teacher Signature</strong>
        <span>Upload Exam Controller or Teacher signature for result cards and reports.</span>
      </div>
      <div class="sig-controls">
        <label class="upload-box" for="img_fileExamControl">
          <i class="fa fa-cloud-upload"></i>
          <span class="up-title">Click to upload</span>
          <span class="up-sub">PNG, JPG or JPEG (Max 600KB)</span>
          <input type="file" name="img_fileExamControlnew" id="img_fileExamControl" accept=".png,.jpg,.jpeg" />
        </label>
        <input type="hidden" name="old_fileExamControl" id="old_fileExamControl" value="<?php echo e($sigController); ?>" />

        <div class="preview-box">
          <img src="<?php echo $sigController !== '' ? BASE_URL . e($sigController) : $blankSig; ?>" id="previewExamControl" />
        </div>

        <form method="post" action="<?php echo BASE_URL; ?>upload_signature.php" class="remove-form" onsubmit="return confirm('Are you sure you want to remove the Exam Controller/Teacher Signature?');">
          <input type="hidden" name="action" value="UploadSignature">
          <input type="hidden" name="remove_signature" value="controller">
          <button type="submit" class="remove-link"><i class="fa fa-times"></i> Remove</button>
        </form>

        <span class="field-error" id="errExamControl">Only JPG/PNG files up to 600KB are allowed.</span>
      </div>
    </div>

    <div class="sig-row">
      <div class="sig-icon"><i class="fa fa-file-text"></i></div>
      <div class="sig-info">
        <strong>Upload Header for Documents</strong>
        <span>Upload header image which will be used in printed documents.</span>
      </div>
      <div class="sig-controls">
        <label class="upload-box" for="header">
          <i class="fa fa-cloud-upload"></i>
          <span class="up-title">Click to upload</span>
          <span class="up-sub">PNG, JPG or JPEG (Max 600KB)</span>
          <input type="file" name="img_fileHeadernew" id="header" accept=".png,.jpg,.jpeg" />
        </label>
        <input type="hidden" name="old_fileHeader" id="old_fileHeader" value="<?php echo e($sigHeader); ?>" />

        <div class="preview-box">
          <img src="<?php echo $sigHeader !== '' ? BASE_URL . e($sigHeader) : $blankSig; ?>" id="previewHeader" />
        </div>

        <form method="post" action="<?php echo BASE_URL; ?>upload_signature.php" class="remove-form" onsubmit="return confirm('Are you sure you want to remove the Document Header?');">
          <input type="hidden" name="action" value="UploadSignature">
          <input type="hidden" name="remove_signature" value="header">
          <button type="submit" class="remove-link"><i class="fa fa-times"></i> Remove</button>
        </form>

        <span class="field-error" id="errHeader">Only JPG/PNG files up to 600KB are allowed.</span>
      </div>
    </div>

    <div class="sig-row">
      <div class="sig-icon"><i class="fa fa-check-square"></i></div>
      <div class="sig-info">
        <strong>Select Signature for Exam Controller/Teacher</strong>
        <span>Choose which signature should be used in the result card.</span>
      </div>
      <div class="sig-controls">
        <select name="teacherExamController" id="teacherExamController" class="form-control">
          <option value="Teacher" <?php echo $teacherExamController === 'Teacher' ? 'selected' : ''; ?>>Exam Controller Signature (Teacher)</option>
          <option value="ExamController" <?php echo $teacherExamController === 'ExamController' ? 'selected' : ''; ?>>Exam Controller Signature</option>
        </select>
      </div>
    </div>

  </div>

  <div class="settings-card">
    <div class="settings-card-header instr">
      <div class="icon-box"><i class="fa fa-file-text-o"></i></div>
      <div>
        <h3>Inquiry Form Instructions</h3>
        <p>Add instructions that will be displayed on inquiry forms.</p>
      </div>
    </div>

    <textarea class="instr-textarea" id="inquiry_instructions" name="inquiry_instructions" onkeyup="document.getElementById('inqCharCount').textContent=this.value.trim().length"><?php echo e($inquiryInstructions); ?></textarea>
    <div class="char-count">Characters : <span id="inqCharCount"><?php echo mb_strlen(trim($inquiryInstructions)); ?></span></div>
  </div>

  <div class="settings-card">
    <div class="settings-card-header instr">
      <div class="icon-box"><i class="fa fa-file-text-o"></i></div>
      <div>
        <h3>Result Card Instructions</h3>
        <p>Add instructions that will be displayed on result cards.</p>
      </div>
    </div>

    <textarea class="instr-textarea" id="result_card_instructions" name="result_card_instructions" onkeyup="document.getElementById('resultCardCharCount').textContent=this.value.trim().length"><?php echo e($resultCardInstructions); ?></textarea>
    <div class="char-count">Characters : <span id="resultCardCharCount"><?php echo mb_strlen(trim($resultCardInstructions)); ?></span></div>
  </div>

  <div class="form-actions-bar">
    <button type="submit" class="btn btn-save-changes"><i class="fa fa-save"></i> Save Changes</button>
    <button type="button" class="btn btn-reset-default" onclick="resetToDefault()"><i class="fa fa-undo"></i> Reset to Default</button>
  </div>

</form>

<script type="text/javascript">
  var blankSig = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
  function validateAndPreview(inputId, previewId, errId) {
    var input = document.getElementById(inputId);
    input.addEventListener('change', function(event) {
      var errEl = document.getElementById(errId);
      errEl.style.display = 'none';
      var file = event.target.files[0];
      if (!file) return;
      var maxSize = 600 * 1024;
      var fileTypes = ['jpg', 'jpeg', 'png'];
      var fileExtension = file.name.split('.').pop().toLowerCase();
      if (file.size > maxSize || fileTypes.indexOf(fileExtension) === -1) {
        errEl.style.display = 'block';
        input.value = '';
        return;
      }
      var reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById(previewId).src = e.target.result;
      };
      reader.readAsDataURL(file);
    });
  }

  validateAndPreview('img_filePrinciple', 'previewPrincipal', 'errPrincipal');
  validateAndPreview('img_fileExamControl', 'previewExamControl', 'errExamControl');
  validateAndPreview('header', 'previewHeader', 'errHeader');

  function resetToDefault() {
    if (!confirm('This will clear the signatures, header and instructions below. You still need to click "Save Changes" to apply it. Continue?')) {
      return;
    }
    ['img_filePrinciple', 'img_fileExamControl', 'header'].forEach(function(id) {
      document.getElementById(id).value = '';
    });
    ['old_filePrinciple', 'old_fileExamControl', 'old_fileHeader'].forEach(function(id) {
      document.getElementById(id).value = '';
    });
    ['previewPrincipal', 'previewExamControl', 'previewHeader'].forEach(function(id) {
      document.getElementById(id).src = blankSig;
    });
    document.getElementById('teacherExamController').value = 'Teacher';
    document.getElementById('inquiry_instructions').value = '';
    document.getElementById('result_card_instructions').value = '';
    document.getElementById('inqCharCount').textContent = '0';
    document.getElementById('resultCardCharCount').textContent = '0';
  }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>