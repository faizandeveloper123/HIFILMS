<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

try { db_query("CREATE TABLE IF NOT EXISTS student_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    doc_type VARCHAR(100),
    file_path VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE student_documents ADD COLUMN IF NOT EXISTS file_path VARCHAR(255) DEFAULT NULL"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE student_documents ADD COLUMN IF NOT EXISTS doc_type VARCHAR(100) DEFAULT NULL"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE student_documents ADD COLUMN IF NOT EXISTS student_id INT DEFAULT NULL"); } catch (Throwable $ex) {}

try { db_query("CREATE TABLE IF NOT EXISTS boards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

try { db_query("CREATE TABLE IF NOT EXISTS `groups` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

try { db_query("CREATE TABLE IF NOT EXISTS admission_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

try { db_query("CREATE TABLE IF NOT EXISTS document_titles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS family_code VARCHAR(50) DEFAULT NULL"); } catch (Throwable $ex) {}

try { db_query("CREATE TABLE IF NOT EXISTS student_fee_plan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    head_id INT DEFAULT NULL,
    head_name VARCHAR(191) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (student_id)
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

function seed_if_empty($table, $nameCol, $values) {
    $c = db_query("SELECT COUNT(*) c FROM `$table`")->fetch_assoc()['c'];
    if ((int)$c === 0) {
        foreach ($values as $v) {
            $st = db_prepare("INSERT INTO `$table` (`$nameCol`) VALUES (?)");
            $st->bind_param('s', $v);
            $st->execute();
        }
    }
}
seed_if_empty('boards', 'name', ['BISE GRW', 'BISE LHR', 'BISE QTA']);
seed_if_empty('groups', 'name', ['Morning Shift', 'Evening Shift', 'FSC', 'ICS', 'Pre Engineering']);
seed_if_empty('admission_sources', 'name', ['Walk-in', 'Referral', 'Online Ads']);
seed_if_empty('document_titles', 'name', ['Beform', 'Matric Result Card', 'Inter Result Card', 'Father CNIC', 'B-Form / CNIC / Photo', 'Previous School Certificate', 'Fee Challan / DMC']);

$page_title = 'Add New Student';
$message = '';
$error = '';

function lookup_rows($sql) {
    $out = [];
    $r = db_query($sql);
    if ($r) { while ($row = $r->fetch_assoc()) { $out[] = $row; } }
    return $out;
}
$classes    = lookup_rows("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
$localities = lookup_rows("SELECT locality_id, locality_name FROM localities WHERE status=1 ORDER BY locality_name");
$boards     = lookup_rows("SELECT id, name FROM boards ORDER BY name");
$groups     = lookup_rows("SELECT id, name FROM `groups` ORDER BY name");
$admSrcs    = lookup_rows("SELECT id, name FROM admission_sources ORDER BY name");
$occupations= lookup_rows("SELECT id, name FROM occupations ORDER BY name");
$docTitles  = lookup_rows("SELECT id, name FROM document_titles ORDER BY name");
$families   = lookup_rows("SELECT DISTINCT f.family_code, f.last_name FROM students f WHERE f.family_code IS NOT NULL AND f.family_code <> '' ORDER BY f.family_code");

$sessions = [];
for ($y = 2018; $y <= 2030; $y++) { $sessions[] = $y . '-' . substr($y + 1, -2); }
$cur_session = get_setting('session_year', '2026-2027');
if (!in_array($cur_session, $sessions, true)) { array_unshift($sessions, $cur_session); }

$nextGr = '';
$cnt = db_query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'] ?? 0;
$nextGr = substr(date('Y'), 2) . '-' . str_pad($cnt + 1, 3, '0', STR_PAD_LEFT);

// -------- POST: Save Student --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'AddAdmission') {
    function parse_date($d) {
        $d = trim($d);
        if ($d === '') return null;
        $ts = strtotime(str_replace('/', '-', $d));
        return $ts ? date('Y-m-d', $ts) : null;
    }
    function val($key) { return isset($_POST[$key]) ? trim($_POST[$key]) : ''; }
    function valNull($key) { $v = val($key); return $v === '' ? null : $v; }

    $first_name     = val('first_name');
    $father_name    = val('lname');
    $mother_name    = val('mother_name');
    $email          = valNull('email');
    $cellno         = val('cellno');
    $class_id       = (int) val('class');
    $section_id     = (int) val('section') ?: null;
    $dob            = val('dob');
    $date_of_adms   = val('date_of_adms');
    $gender         = strtolower(val('gender')) ?: 'male';
    $religion       = val('religion') ?: 'Islam';
    $session        = valNull('session');
    $board_council  = valNull('board_council');
    $group_shift    = valNull('group_shift');
    $adm_source     = valNull('adm_source');
    $locality_id    = val('Locality') !== '' ? (int) val('Locality') : null;
    $father_cnic    = valNull('cnic');
    $father_qual    = valNull('Fqualification');
    $father_bus     = valNull('Fbusiness_address');
    $father_income  = valNull('Fincome');
    $father_occ     = valNull('father_occupation');
    $father_cell    = valNull('father_cellno');
    $mother_cnic    = valNull('mother_cnic');
    $mother_qual    = valNull('mother_qualification');
    $mother_act     = valNull('mother_activity');
    $mother_desig   = valNull('mother_designation');
    $mother_cell    = valNull('mother_cell');
    $formBNo        = valNull('formBNo');
    $caste          = valNull('cast');
    $gname          = valNull('gname');
    $Gcnic          = valNull('Gcnic');
    $Gcellno        = valNull('Gcellno');
    $Gqual          = valNull('Gqualification');
    $Gocc           = valNull('Goccupation');
    $Gincome        = valNull('Gincome');
    $gemail         = valNull('gardian_email');
    $Gaddress       = valNull('Gaddress');
    $old_class      = valNull('old_class');
    $old_school     = valNull('old_school');
    $old_tmarks     = valNull('old_tmarks');
    $old_obtmarks   = valNull('old_obtmarks');
    $form_no        = valNull('form_no');
    $school_leaving = valNull('school_leaving');
    $whatsapp       = valNull('whatsapp_number');
    $home_number    = valNull('home_number');
    $place_of_birth = valNull('place_of_birth');
    $state          = valNull('state');
    $city           = valNull('city');
    $address        = valNull('address');
    $dob_db         = parse_date($dob);
    $adm_db         = parse_date($date_of_adms) ?? date('Y-m-d');

    $photo = null;
    if (!empty($_FILES['img_file']['name']) && $_FILES['img_file']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/uploads/students';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $ext = strtolower(pathinfo($_FILES['img_file']['name'], PATHINFO_EXTENSION));
        $photo = 's_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        if (!move_uploaded_file($_FILES['img_file']['tmp_name'], $dir . '/' . $photo)) { $photo = null; }
    } elseif (!empty($_POST['captured_image'])) {
        $dir = __DIR__ . '/uploads/students';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $data = $_POST['captured_image'];
        $prefix = 'data:image/jpeg;base64,';
        if (stripos($data, $prefix) === 0) {
            $base64 = substr($data, strlen($prefix));
            $bin = base64_decode($base64, true);
            if ($bin !== false) {
                $photo = 's_' . time() . '_' . rand(1000, 9999) . '.jpg';
                if (file_put_contents($dir . '/' . $photo, $bin) === false) { $photo = null; }
            }
        }
    }

    if ($first_name === '' || $class_id === 0) {
        $error = 'Student Name and Class are required.';
    } else {
        $cols = ['first_name','last_name','father_name','mother_name','email','phone','dob','gender',
                 'religion','session','board_council','group_shift','admission_source','locality_id',
                 'father_cnic','father_qualification','father_business_address','father_income',
                 'father_occupation','father_cellno','mother_cnic','mother_qualification','mother_activity',
                 'mother_designation','mother_cell','form_b_no','caste','guardian_name','guardian_cnic',
                 'guardian_cellno','guardian_qualification','guardian_occupation','guardian_income',
                 'guardian_email','guardian_address','old_class','old_school','old_tmarks','old_obtmarks',
                 'admission_form_no','school_leaving_reason','whatsapp_number','home_number','place_of_birth',
                 'state','city','address','class_id','section_id','admission_date','status','photo'];

        $lname = $father_name;
        $vals  = [$first_name,$lname,$father_name,$mother_name,$email,$cellno,$dob_db,$gender,
                 $religion,$session,$board_council,$group_shift,$adm_source,$locality_id,
                 $father_cnic,$father_qual,$father_bus,$father_income,
                 $father_occ,$father_cell,$mother_cnic,$mother_qual,$mother_act,
                 $mother_desig,$mother_cell,$formBNo,$caste,$gname,$Gcnic,
                 $Gcellno,$Gqual,$Gocc,$Gincome,
                 $gemail,$Gaddress,$old_class,$old_school,$old_tmarks,$old_obtmarks,
                 $form_no,$school_leaving,$whatsapp,$home_number,$place_of_birth,
                 $state,$city,$address,$class_id,$section_id,$adm_db,1,$photo];

        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO students (`' . implode('`,`', $cols) . '`) VALUES (' . $placeholders . ')';
        try {
            $stmt = db_prepare($sql);
            $types = str_repeat('s', count($vals));
            $bindVals = [$types];
            foreach ($vals as $k => $v) { $bindVals[] = &$vals[$k]; }
            call_user_func_array([$stmt, 'bind_param'], $bindVals);
            $stmt->execute();
            $studentId = $stmt->insert_id;

            if ($studentId > 0) {
                $gr = substr(date('Y'), 2) . '-' . str_pad($studentId, 3, '0', STR_PAD_LEFT);
                $u = db_prepare('UPDATE students SET gr_no = ? WHERE student_id = ?');
                $u->bind_param('si', $gr, $studentId);
                $u->execute();

                $family_code = valNull('family_code');
                if (!$family_code) {
                    $key = $Gcellno !== null ? $Gcellno : ($father_cell !== null ? $father_cell : null);
                    if ($key) {
                        $ex = db_prepare("SELECT family_code FROM students WHERE family_code IS NOT NULL AND family_code <> '' AND (guardian_cellno = ? OR father_cellno = ?) LIMIT 1");
                        $ex->bind_param('ss', $key, $key);
                        $ex->execute();
                        $fr = $ex->get_result()->fetch_assoc();
                        $family_code = $fr ? $fr['family_code'] : ('F-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT));
                    }
                }
                if ($family_code) {
                    $u2 = db_prepare('UPDATE students SET family_code = ? WHERE student_id = ?');
                    $u2->bind_param('si', $family_code, $studentId);
                    $u2->execute();
                }

                $docTypes = isset($_POST['doc_types']) ? (array) $_POST['doc_types'] : [];
                if (!empty($_FILES['doc_files'])) {
                    $docFiles = $_FILES['doc_files'];
                    $docDir = __DIR__ . '/uploads/students/documents';
                    if (!is_dir($docDir)) { @mkdir($docDir, 0775, true); }
                    $docInsert = db_prepare('INSERT INTO student_documents (student_id, doc_type, file_path) VALUES (?, ?, ?)');
                    foreach ($docFiles['name'] as $i => $name) {
                        if (empty($name)) continue;
                        if ($docFiles['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        $fname = 'doc_' . $studentId . '_' . time() . '_' . $i . '_' . rand(1000, 9999) . '.' . $ext;
                        if (!move_uploaded_file($docFiles['tmp_name'][$i], $docDir . '/' . $fname)) continue;
                        $dtype = isset($docTypes[$i]) ? trim($docTypes[$i]) : '';
                        $docInsert->bind_param('iss', $studentId, $dtype, $fname);
                        $docInsert->execute();
                    }
                    $docInsert->close();
                }

                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => true, 'student_id' => $studentId, 'gr_no' => $gr]);
                    exit;
                }
                header('Location: ' . BASE_URL . 'fee_plan.php?student_id=' . $studentId);
                exit;
            }
        } catch (Exception $ex) {
            $error = 'Error: ' . $ex->getMessage();
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($error !== ''): ?>
<div class="container mt-4" style="padding-left:0;padding-right:0;">
    <div class="alert alert-danger" style="border-radius:10px;font-size:13px;"><?php echo htmlspecialchars($error); ?></div>
</div>
<?php endif; ?>

<style>
/* ============ Add Student — EduPortal style ============ */
.page-card{background:#fff;border-radius:14px;box-shadow:0 6px 24px rgba(20,30,60,0.08);padding:20px 24px 24px 24px;position:relative;overflow:hidden;}
.page-card::before{content:"";position:absolute;top:0;left:0;right:0;height:5px;background:linear-gradient(90deg,#FF7C1B,#FFB25E,#4F86C6,#2FAE6B,#9B59D0);}
.top-tabs-row{display:flex;align-items:center;justify-content:flex-start;flex-wrap:nowrap;row-gap:10px;}
.top-tabs-row .nav-tabs{margin-bottom:0;flex:0 0 auto;}

.page-card .nav-tabs > li.active > a,.page-card .nav-tabs > li.active > a:hover,.page-card .nav-tabs > li.active > a:focus{background-color:#FF7C1B !important;color:#fff !important;border:1px solid #FF7C1B;}
.page-card .nav-tabs > li > a{color:gray;border:1px solid #ddd;padding:10px 15px;border-radius:0;}
.page-card .nav-tabs > li > a:hover{background-color:#f1f1f1;}
.page-card .nav-tabs li:first-child > a{border-top-left-radius:4px;border-bottom-left-radius:4px;}
.page-card .nav-tabs li:last-child > a{border-top-right-radius:4px;border-bottom-right-radius:4px;}
.page-card .tab-content{margin-top:20px;}

/* Step Wizard */
.step-wizard{display:flex;align-items:flex-start;justify-content:space-between;width:90%;margin:20px auto 30px auto;}
.step-wizard-compact{width:40%;margin:0 0 0 14px;justify-content:space-between;flex:0 0 auto;min-width:160px;}
.step-wizard-compact .step-wizard-item{flex:none;}
.step-wizard-compact .step-circle{width:20px;height:20px;font-size:10px;}
.step-wizard-compact .step-label{font-size:8px;width:auto;white-space:nowrap;margin-top:3px;}
.step-wizard-compact .step-wizard-line{flex:1 1 auto;width:auto;min-width:10px;margin-top:10px;}
.step-wizard-item{display:flex;flex-direction:column;align-items:center;position:relative;}
.step-circle{width:32px;height:32px;border-radius:50%;border:2px solid #e2e6ea;background:#fff;color:#999;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:13px;z-index:1;transition:all .25s ease;}
.step-wizard-item.active .step-circle,.step-wizard-item.completed .step-circle{background:linear-gradient(135deg,#FF9142,#FF7C1B);border-color:#FF7C1B;color:#fff;box-shadow:0 4px 10px rgba(255,124,27,.4);transform:scale(1.08);}
.step-label{margin-top:6px;font-size:11px;color:#777;text-align:center;width:110px;}
.step-wizard-item.active .step-label{color:#FF7C1B;font-weight:700;}
.step-wizard-line{flex:1;height:3px;border-radius:2px;background:#e2e6ea;margin-top:16px;}
.step-wizard-line.completed{background:linear-gradient(90deg,#FF7C1B,#FFB25E);}

/* Icon Tabs */
.icon-tabs{display:flex;list-style:none;padding:6px;margin:0 0 20px 0;background:#f4f6f9;border-radius:10px;flex-wrap:wrap;gap:4px;}
.icon-tab-item{padding:9px 16px;cursor:pointer;color:#666;font-size:13px;border-radius:8px;white-space:nowrap;transition:all .2s ease;}
.icon-tab-item:hover{background:#fff;color:#444;box-shadow:0 1px 4px rgba(0,0,0,.08);}
.icon-tab-item i{margin-right:6px;}
.icon-tab-item:nth-child(1) i{color:#4F86C6;}
.icon-tab-item:nth-child(2) i{color:#9B59D0;}
.icon-tab-item:nth-child(3) i{color:#2FAE6B;}
.icon-tab-item:nth-child(4) i{color:#17A2B8;}
.icon-tab-item:nth-child(5) i{color:#8a8f98;}
.icon-tab-item.active{color:#FF7C1B;background:#fff;box-shadow:0 2px 8px rgba(255,124,27,.25);font-weight:600;}
.icon-tab-item.active i{color:#FF7C1B;}
.wizard-pane{display:none;}
.wizard-pane.active{display:block;}
.wizard-section-title{margin:10px 0 15px 0;padding-left:12px;border-left:4px solid #FF7C1B;}
.wizard-section-title span{font-weight:bold;font-size:19px;}

/* form rows as flex */
.form-row{display:flex;flex-wrap:wrap;}
.col-fifth{flex:0 0 20%;max-width:20%;padding-left:15px;padding-right:15px;position:relative;}

.page-card .form-control{border:2px solid #ccc;border-radius:4px;padding:19px 15px;width:100%;font-size:16px;background-color:#fff;transition:border-color .3s ease;box-shadow:none;height:auto;}
.page-card select.form-control{line-height:normal;height:auto;padding:7px 12px;vertical-align:middle;}
.page-card input[type="file"].form-control{line-height:normal;height:auto;padding:8px 12px;vertical-align:middle;}
.page-card .form-control:focus{border-color:#ff8c00;box-shadow:0 0 5px rgba(255,140,0,.5);}
.page-card .form-group{position:relative;margin-bottom:15px;}
.page-card .form-group label{position:absolute;left:15px;top:-10px;font-size:11px;color:#666;background-color:#fff;padding:0 5px;pointer-events:none;transition:all .3s ease;font-weight:normal;margin:0;line-height:normal;}
.page-card .form-control:focus + label{color:#ff8c00;}
.page-card .form-control::placeholder{color:transparent;}
.page-card input[type="file"].form-control{padding:8px 12px;}

#image-container{width:125px;height:140px;margin:12px 0 6px 0;border:2px dashed #FFC68A;border-radius:6px;display:flex;justify-content:center;align-items:center;overflow:hidden;background-color:#fff;position:relative;}
#image{display:none;position:absolute;max-width:100%;max-height:100%;object-fit:contain;transition:transform .2s ease;}
#image,#sample-image{display:none;position:absolute;transition:transform .2s ease;}
#sample-image{pointer-events:none;}
.draggable{cursor:move;}
.slider-container{display:flex;align-items:center;}
.slider-container label{margin-right:10px;}
#zoom-slider,#rotate-slider{width:100px;}
#show-image-btn,#remove-image-btn{margin-top:0;padding:5px 6px;font-size:10px;cursor:pointer;}

/* Dropdown + "Add New" shortcut */
.field-with-add{display:flex;align-items:center;gap:8px;}
.field-with-add select{flex:1;min-width:0;}
.btn-add-new{display:inline-flex;align-items:center;gap:4px;height:30px;padding:0 10px;white-space:nowrap;border:1px solid #FF7C1B;color:#FF7C1B;background:#fff;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none;margin-top:0;}
.btn-add-new:hover,.btn-add-new:focus{background:#FF7C1B;color:#fff;text-decoration:none;}

/* Parent Details compact */
#pane-parent-details .wizard-section-title{margin:4px 0 10px 0;}
#pane-parent-details .form-row{margin-bottom:2px;}
#pane-parent-details .form-group{margin-bottom:10px;}
#pane-parent-details .col-fifth{padding-left:8px;padding-right:8px;}
#pane-parent-details .form-group.col-md-3{padding-left:8px;padding-right:8px;}
#pane-parent-details .page-card .form-control{padding:9px 12px;font-size:13px;}
#pane-parent-details select.form-control{padding:6px 10px;}
#pane-parent-details .form-group label{font-size:10px;top:-8px;}
#pane-parent-details .field-with-add{gap:6px;}
#pane-parent-details .btn-add-new{height:26px;font-size:11px;}
#pane-parent-details hr{margin:10px 0;}

/* Documents pane */
.doc-pane-intro{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;padding:8px 12px;background:#FFF4EB;border:1px solid #FFDCB8;border-radius:8px;color:#8a4b12;font-size:12px;flex-wrap:wrap;}
.doc-pane-intro-text{display:flex;align-items:center;gap:8px;}
.doc-pane-intro i{color:#FF7C1B;font-size:14px;}
.doc-pane-manage-btn{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;font-size:11px;font-weight:700;color:#fff;background:#FF7C1B;border-radius:6px;text-decoration:none;white-space:nowrap;}
.doc-pane-manage-btn:hover{background:#e56f14;color:#fff;text-decoration:none;}
.doc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;}
.doc-card{background:#fff;border:1px solid #e7e9ee;border-radius:8px;padding:8px;text-align:center;transition:all .2s ease;position:relative;}
.doc-card:hover{box-shadow:0 4px 10px rgba(20,30,60,.08);transform:translateY(-1px);border-color:#FFDCB8;}
.doc-card-thumb{width:100%;height:62px;border-radius:6px;background:#f4f6f9;display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:6px;border:1px dashed #d9dee5;}
.doc-card-thumb img{width:100%;height:100%;object-fit:cover;}
.doc-card-thumb i{font-size:22px;color:#b9c0cb;}
.doc-card.has-file .doc-card-thumb{border-style:solid;border-color:#cdeedd;}
.doc-card-title{font-size:11px;font-weight:600;color:#333;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.doc-card-status{display:inline-block;font-size:9px;font-weight:700;padding:1px 7px;border-radius:20px;margin-bottom:6px;background:#eee;color:#888;}
.doc-card.has-file .doc-card-status{background:#E4F8ED;color:#1E9A57;}
.doc-card-upload{display:inline-flex;align-items:center;justify-content:center;gap:4px;width:100%;padding:4px 0;font-size:10px;font-weight:600;color:#FF7C1B;background:#fff;border:1px solid #FF7C1B;border-radius:5px;cursor:pointer;transition:all .2s ease;}
.doc-card-upload:hover{background:#FF7C1B;color:#fff;}
.doc-card-filename{margin-top:4px;font-size:9px;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.doc-card input[type="file"]{display:none;}

/* Bottom bar */
.wizard-actions-bar{display:flex;justify-content:space-between;align-items:center;border-top:1px solid #e2e2e2;margin-top:25px;padding-top:15px;flex-wrap:wrap;gap:10px;}
.mandatory-note{color:#999;font-size:12px;}
.wizard-actions-buttons{display:flex;gap:10px;flex-wrap:wrap;}
.wizard-btn{padding:9px 22px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid #d5d9de;background:#fff;color:#555;transition:all .2s ease;}
.wizard-btn:hover{transform:translateY(-1px);box-shadow:0 3px 8px rgba(0,0,0,.1);}
.wizard-btn-outline{border-color:#FF7C1B;color:#FF7C1B;background:#fff;}
.wizard-btn-outline:hover{background:#FFF4EB;}
.wizard-btn-primary{background:linear-gradient(135deg,#FF9142,#FF7C1B);border-color:#FF7C1B;color:#fff;box-shadow:0 4px 10px rgba(255,124,27,.35);}
.wizard-btn-primary:hover{box-shadow:0 6px 14px rgba(255,124,27,.45);}

#family_search + .select2-container{width:100% !important;}
#family_search + .select2-container .select2-choice{height:auto;padding:7px 12px;line-height:normal;border:2px solid #ccc;border-radius:4px;font-size:16px;color:#555;}
#family_search + .select2-container .select2-choice .select2-chosen{line-height:normal;}
#family_search + .select2-container .select2-choice .select2-arrow{border-radius:0 4px 4px 0;background:none;border-left:none;}
#family_search + .select2-container.select2-dropdown-open .select2-choice,
#family_search + .select2-container-active .select2-choice{border-color:#ff8c00;box-shadow:0 0 5px rgba(255,140,0,.5);}

.bcrumb{font-size:13px;color:#64748b;font-weight:500;padding:8px 0 14px;}
.bcrumb a{color:#c05621;text-decoration:none;}
</style>

<div class="container mt-4" style="padding-left:0;padding-right:0;">
    <div class="bcrumb">
        <a href="dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
        <span style="margin:0 8px;color:#cbd5e1;">&raquo;</span>
        <a href="manage_students.php">Students</a>
        <span style="margin:0 8px;color:#cbd5e1;">&raquo;</span>
        <span style="color:#1e293b;font-weight:600;">Add New Student</span>
    </div>

    <div class="page-card">
        <div class="top-tabs-row">
            <div class="nav-tabs-wrap">
                <ul class="nav nav-tabs" id="studentTabs" role="tablist">
                    <li class="active"><a href="#add-single" data-toggle="tab"><i class="fa fa-user-plus"></i> Add New Student</a></li>
                    <li><a href="bulk_stdns.php"><i class="fa fa-users"></i>&nbsp; Add Multi Students</a></li>
                    <li><a href="import_data.php"><i class="fa fa-upload"></i>&nbsp; Import Students with CSV</a></li>
                    <li><a href="adm_form.php" target="_blank"><i class="fa fa-file-alt"></i>&nbsp; Admission Form</a></li>
                </ul>
            </div>
            <div class="step-wizard step-wizard-compact">
                <div class="step-wizard-item active"><div class="step-circle">1</div><div class="step-label">Student Info</div></div>
                <div class="step-wizard-line"></div>
                <div class="step-wizard-item"><div class="step-circle">2</div><div class="step-label">Fee Plan</div></div>
            </div>
        </div>

        <div class="tab-content" id="studentTabsContent">
            <div class="tab-pane active" id="add-single">
                <ul class="icon-tabs" id="studentWizardTabs">
                    <li class="icon-tab-item active" data-tab="basic-info"><i class="fa fa-id-card"></i> Basic Information</li>
                    <li class="icon-tab-item" data-tab="parent-details"><i class="fa fa-user-friends"></i> Parent Details</li>
                    <li class="icon-tab-item" data-tab="academic-info"><i class="fa fa-graduation-cap"></i> Academic Information</li>
                    <li class="icon-tab-item" data-tab="contact-info"><i class="fa fa-phone-alt"></i> Contact Information</li>
                    <li class="icon-tab-item" data-tab="documents"><i class="fa fa-file-alt"></i> Documents</li>
                </ul>

                <form id="studentForm" action="<?php echo BASE_URL; ?>add_student.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="AddAdmission">
                    <input type="hidden" name="captured_image" id="captured_image" value="">
                    <input type="hidden" name="redirect_mode" id="redirect_mode" value="">
                    <input type="hidden" name="family_code" id="family_code_value" value="">

                    <!-- ============ PANE 1: Basic Information ============ -->
                    <div class="wizard-pane active" id="pane-basic-info">
                        <div class="form-row">
                            <div class="col-md-8" style="padding-left:0;">
                                <div class="form-row" style="margin-bottom:14px;">
                                    <div class="form-group col-md-3">
                                        <label for="fname">Student Name</label>
                                        <input type="text" value="" class="form-control" name="first_name" required id="fname" placeholder="Student Name" maxlength="35" oninput="if(this.value.length>=35){document.getElementById('fname-limit-msg').style.display='block';}else{document.getElementById('fname-limit-msg').style.display='none';}">
                                        <small id="fname-limit-msg" style="display:none;color:red;">Student Name cannot be more than 35 characters.</small>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="last_name">Father Name</label>
                                        <input type="text" value="" class="form-control" name="lname" id="last_name" placeholder="Father Name" maxlength="35" oninput="if(this.value.length>=35){document.getElementById('lname-limit-msg').style.display='block';}else{document.getElementById('lname-limit-msg').style.display='none';}">
                                        <small id="lname-limit-msg" style="display:none;color:red;">Father Name cannot be more than 35 characters.</small>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="family_search">Select Family</label>
                                        <select name="family_search" id="family_search" class="form-control chosen-select" onChange="getFamilyInfo(this.value);">
                                            <option value="">Select Family</option>
                                            <?php foreach ($families as $f): ?>
                                            <option value="<?php echo htmlspecialchars($f['family_code']); ?>"><?php echo htmlspecialchars(trim(($f['last_name'] ?? '') . ' - ' . $f['family_code'], ' -')); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="cell_no">Cell Number</label>
                                        <input type="text" value="" class="form-control" name="cellno" required id="cell_no" placeholder="Number/ Reporting SMS">
                                    </div>
                                </div>
                                <div class="form-row" style="margin-bottom:14px;">
                                    <div class="form-group col-md-3">
                                        <label for="session">Session</label>
                                        <select name="session" required id="session" class="form-control">
                                            <option value="">Select Session</option>
                                            <?php foreach ($sessions as $s): ?>
                                            <option value="<?php echo $s; ?>" <?php echo $s === $cur_session ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="class">Select Class</label>
                                        <select name="class" required id="class" class="form-control" onChange="getSection(this.value);">
                                            <option value="">Select Class</option>
                                            <?php foreach ($classes as $c): ?>
                                            <option value="<?php echo (int) $c['class_id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3" id="sec">
                                        <label for="txt_section">Select Section</label>
                                        <select name="section" required id="txt_section" class="form-control">
                                            <option value="">Select Section</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="com_no">GR-No</label>
                                        <input type="text" value="<?php echo htmlspecialchars($nextGr); ?>" class="form-control" name="com_no" id="com_no" readonly>
                                    </div>
                                </div>
                                <div class="form-row" style="margin-bottom:14px;">
                                    <div class="form-group col-md-3">
                                        <label for="gender">Gender <span style="color:red;">*</span></label>
                                        <select id="gender" name="gender" class="form-control" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="religion">Select Religion <span style="color:red;">*</span></label>
                                        <select id="religion" name="religion" class="form-control" required>
                                            <option value="">Select Religion</option>
                                            <option value="Muslim" selected>Muslim</option>
                                            <option value="Hinduism">Hindu</option>
                                            <option value="Sikhism">Sikh</option>
                                            <option value="Christian">Christian</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="dob">Date Of Birth</label>
                                        <input type="text" class="form-control" name="dob" id="dob" placeholder="dd/mm/yyyy" value="<?php echo date('d/M/Y'); ?>">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="date_of_adms">Date Of Admission</label>
                                        <input type="text" class="form-control" name="date_of_adms" id="date_of_adms" placeholder="dd/mm/yyyy" value="<?php echo date('d/M/Y'); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Image Upload Box -->
                            <div class="col-md-4" style="border:1px solid #FFD9B3;border-radius:8px;height:auto;background:linear-gradient(180deg,#FFF9F4,#ffffff);box-shadow:0 2px 8px rgba(255,124,27,.08);">
                                <div class="col-md-6">
                                    <div id="image-container">
                                        <div style="cursor:pointer;display:flex;align-items:center;justify-content:center;width:100%;height:100%;">
                                            <img id="image" src="" alt="Uploaded Image" class="draggable" style="position:absolute;">
                                            <img id="sample-image" src="" alt="Sample Image" style="display:none;">
                                        </div>
                                        <canvas id="imageCanvas" style="display:none;"></canvas>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <br>
                                    <a id="remove-image-btn" class="btn btn-danger btn-sm" style="display:none;font-size:10px;">Delete Frame</a>
                                    <br><br>
                                    <div class="slider-container">
                                        <label for="zoom-slider">Zoom:</label>
                                        <input type="range" id="zoom-slider" min="0.5" max="2" step="0.05" value="1">
                                    </div>
                                    <div class="slider-container">
                                        <label for="rotate-slider">Rotate:</label>
                                        <input type="range" id="rotate-slider" min="-180" max="180" step="1" value="0">
                                    </div>
                                    <label for="fileInput" style="font-size:11px;color:#666;">Upload Picture</label>
                                    <input type="file" class="form-control" name="img_file" id="fileInput" accept="image/*">
                                    <input type="hidden" name="old_file" value="">
                                </div>
                            </div>
                        </div>

                        <div class="row" style="margin-top:10px;">
                            <div class="form-group col-md-3">
                                <label for="board_council">Board/Council</label>
                                <div class="field-with-add">
                                    <select id="board_council" name="board_council" class="form-control">
                                        <option value="">Select Board/Council</option>
                                        <?php foreach ($boards as $b): ?>
                                        <option value="<?php echo (int) $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a href="manage_board.php" target="_blank" class="btn-add-new"><i class="fa fa-plus"></i> Add New</a>
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="group_shift">Group/Shift</label>
                                <div class="field-with-add">
                                    <select id="group_shift" name="group_shift" class="form-control">
                                        <option value="">Select Group/Shift</option>
                                        <?php foreach ($groups as $g): ?>
                                        <option value="<?php echo (int) $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a href="manage_group.php" target="_blank" class="btn-add-new"><i class="fa fa-plus"></i> Add New</a>
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="adm_source">Admission Source</label>
                                <div class="field-with-add">
                                    <select id="adm_source" name="adm_source" class="form-control">
                                        <option value="">Select Admission Source</option>
                                        <?php foreach ($admSrcs as $a): ?>
                                        <option value="<?php echo (int) $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a href="manage_admission_sources.php" target="_blank" class="btn-add-new"><i class="fa fa-plus"></i> Add New</a>
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="locality">Choose Locality</label>
                                <div class="field-with-add">
                                    <select name="Locality" id="locality" class="form-control">
                                        <option value="">Choose Locality</option>
                                        <?php foreach ($localities as $l): ?>
                                        <option value="<?php echo (int) $l['locality_id']; ?>"><?php echo htmlspecialchars($l['locality_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a href="manage_localities.php" target="_blank" class="btn-add-new"><i class="fa fa-plus"></i> Add New</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ============ END PANE 1 ============ -->

                    <!-- ============ PANE 2: Parent Details ============ -->
                    <div class="wizard-pane" id="pane-parent-details">
                        <div id="familyInformationSection">
                            <div class="wizard-section-title" style="border-left-color:#9B59D0;"><span>Family Information</span></div>
                            <div class="row" style="margin-top:10px;">
                                <div class="col-md-12">
                                    <div class="form-row">
                                        <div class="form-group col-fifth">
                                            <label for="cnic">Father CNIC</label>
                                            <input type="text" value="" class="form-control" name="cnic" id="cnic" placeholder="Father CNIC" maxlength="13" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,13); document.getElementById('fcnic-limit-msg').style.display=(this.value.length>0 && this.value.length<13)?'block':'none';">
                                            <small id="fcnic-limit-msg" style="display:none;color:red;">Father CNIC must be exactly 13 digits.</small>
                                        </div>
                                        <div class="form-group col-fifth">
                                            <label for="father_qualification">Father Qualification</label>
                                            <input type="text" value="" class="form-control" name="Fqualification" id="father_qualification" placeholder="Father Qualification">
                                        </div>
                                        <div class="form-group col-fifth">
                                            <label for="Fbusiness_address">Father Business Address</label>
                                            <input type="text" value="" class="form-control" name="Fbusiness_address" id="Fbusiness_address" placeholder="Father Business Address">
                                        </div>
                                        <div class="form-group col-fifth">
                                            <label for="father_income">Father Income</label>
                                            <input type="text" value="" class="form-control" name="Fincome" id="father_income" placeholder="Father Income">
                                        </div>
                                        <div class="form-group col-fifth">
                                            <label for="mother_name">Mother Name</label>
                                            <input type="text" value="" class="form-control" name="mother_name" id="mother_name" placeholder="Mother Name">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-fifth">
                                            <label for="mother_cnic">Mother CNIC</label>
                                            <input type="text" value="" class="form-control" name="mother_cnic" id="mother_cnic" placeholder="Mother CNIC" maxlength="13" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,13); document.getElementById('mcnic-limit-msg').style.display=(this.value.length>0 && this.value.length<13)?'block':'none';">
                                            <small id="mcnic-limit-msg" style="display:none;color:red;">Mother CNIC must be exactly 13 digits.</small>
                                        </div>
                                        <div class="form-group col-fifth">
                                            <label for="mother_qualification">Mother Qualification</label>
                                            <input type="text" value="" class="form-control" name="mother_qualification" id="mother_qualification" placeholder="Mother Qualification">
                                        </div>
                                        <div class="form-group col-fifth">
                                            <label for="mother_activity">Mother Activities</label>
                                            <select id="mother_activity" name="mother_activity" class="form-control">
                                                <option value="">Mother Activities</option>
                                                <option value="House Lady">House Lady</option>
                                                <option value="Job Holder">Job Holder</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-fifth">
                                            <label for="mother_designation">Mother Designation</label>
                                            <input type="text" value="" class="form-control" name="mother_designation" id="mother_designation" placeholder="Mother Designation">
                                        </div>
                                        <div class="form-group col-fifth">
                                            <label for="address">Home Address</label>
                                            <input type="text" value="" class="form-control" name="address" id="address" placeholder="Family Home Address">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-fifth">
                                            <label for="father_occupation">Father Occupation</label>
                                            <div class="field-with-add">
                                                <select name="father_occupation" id="father_occupation" class="form-control">
                                                    <option value="">Choose Occupation</option>
                                                    <?php foreach ($occupations as $o): ?>
                                                    <option value="<?php echo (int) $o['id']; ?>"><?php echo htmlspecialchars($o['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <a href="manage_occupations.php" target="_blank" class="btn-add-new"><i class="fa fa-plus"></i> Add New</a>
                                            </div>
                                        </div>
                                        <div class="form-group col-fifth">
                                            <label for="formBNo">B-Form-No</label>
                                            <input type="text" value="" class="form-control" name="formBNo" id="formBNo" placeholder="Form-B No">
                                        </div>
                                        <div class="form-group col-fifth">
                                            <label for="cast">Cast</label>
                                            <input type="text" value="" class="form-control" name="cast" id="cast" placeholder="Cast">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="guardianInformationSection">
                            <hr>
                            <div class="wizard-section-title" style="border-left-color:#9B59D0;"><span>Guardian Information <small style="font-size:12px;color:gray;">(this part will be filled in case of death of father)</small></span></div>
                            <div class="row" style="margin-top:10px;">
                                <div class="col-md-12">
                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label for="gardian_name">Guardian Name</label>
                                            <input type="text" value="" class="form-control" name="gname" id="gardian_name" placeholder="Guardian Name">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="gardian_cnic">Guardian CNIC</label>
                                            <input type="text" value="" class="form-control" name="Gcnic" id="gardian_cnic" placeholder="Guardian CNIC">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="gardian_no">Guardian Cell No</label>
                                            <input type="text" value="" class="form-control" name="Gcellno" id="gardian_no" placeholder="Guardian Cell No">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="gardian_qualification">Guardian Qualification</label>
                                            <input type="text" value="" class="form-control" name="Gqualification" id="gardian_qualification" placeholder="Guardian Qualification">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label for="gardian_occupation">Guardian Occupation</label>
                                            <input type="text" value="" class="form-control" name="Goccupation" id="gardian_occupation" placeholder="Guardian Occupation">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="gardian_income">Guardian Income</label>
                                            <input type="text" value="" class="form-control" name="Gincome" id="gardian_income" placeholder="Guardian Income">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="gardian_email">Guardian Email</label>
                                            <input type="text" value="" class="form-control" name="gardian_email" id="gardian_email" placeholder="Guardian Email">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="gardian_address">Guardian Address</label>
                                            <input type="text" value="" class="form-control" name="Gaddress" id="gardian_address" placeholder="Guardian Address">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ============ END PANE 2 ============ -->

                    <!-- ============ PANE 3: Academic Information ============ -->
                    <div class="wizard-pane" id="pane-academic-info">
                        <div id="admissionInformationSection">
                            <div class="wizard-section-title" style="border-left-color:#2FAE6B;"><span>Admission &amp; Academic Information</span></div>
                            <div class="row" style="margin-top:10px;">
                                <div class="col-md-12">
                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label for="old_class">Previous Class</label>
                                            <input type="text" value="" class="form-control" name="old_class" id="old_class" placeholder="Previous Class">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="old_school">Previous Institute</label>
                                            <input type="text" value="" class="form-control" name="old_school" id="old_school" placeholder="Previous Institute">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="old_tmarks">Total Marks</label>
                                            <input type="text" value="" class="form-control" name="old_tmarks" id="old_tmarks" placeholder="Total Marks">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="old_obtmarks">Obtaining Marks</label>
                                            <input type="text" value="" class="form-control" name="old_obtmarks" id="old_obtmarks" placeholder="Obtained Marks">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label for="adm-no">Admission Form No</label>
                                            <input type="text" class="form-control" name="form_no" id="adm-no" placeholder="Admission Form No">
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="school_leaving">Reason for Previous School Leaving</label>
                                            <input type="text" value="" class="form-control" name="school_leaving" id="school_leaving" placeholder="Reason of Previous School Leaving">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ============ END PANE 3 ============ -->

                    <!-- ============ PANE 4: Contact Information ============ -->
                    <div class="wizard-pane" id="pane-contact-info">
                        <div id="contactInformationSection">
                            <div class="wizard-section-title" style="border-left-color:#17A2B8;"><span>Contact &amp; Address Information</span></div>
                            <div class="row" style="margin-top:10px;">
                                <div class="col-md-12">
                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label for="whatsapp_number">Whatsapp No</label>
                                            <input type="text" value="" class="form-control" name="whatsapp_number" id="whatsapp_number" placeholder="Whatsapp Number">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="father_cellno">Father Cell No</label>
                                            <input type="text" value="" class="form-control" name="father_cellno" id="father_cellno" placeholder="Father Cell No">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="mother_cell">Mother Cell No</label>
                                            <input type="text" value="" class="form-control" name="mother_cell" id="mother_cell" placeholder="Mother Cell Number">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="home_number">Home Cell No</label>
                                            <input type="text" value="" class="form-control" name="home_number" id="home_number" placeholder="Home PTCL Number">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="place_of_birth">Place Of Birth</label>
                                            <input type="text" value="" class="form-control" name="place_of_birth" id="place_of_birth" placeholder="Place Of Birth">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="state">Select State</label>
                                            <select name="state" id="state" class="form-control" onChange="getCity(this.value);">
                                                <option value="">Select State</option>
                                                <option value="Punjab">Punjab</option>
                                                <option value="Sindh">Sindh</option>
                                                <option value="Balochistan">Balochistan</option>
                                                <option value="KPK">KPK</option>
                                                <option value="Gilgit-Baltistan">Gilgit-Baltistan</option>
                                                <option value="Kashmir (territory)">Kashmir (territory)</option>
                                                <option value="FATA (territory)">FATA (territory)</option>
                                                <option value="Federal">Federal</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="city">City</label>
                                            <select name="city" id="city" class="form-control">
                                                <option value="">Select City</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="email">Email</label>
                                            <input type="text" value="" class="form-control" name="email" id="email" placeholder="Email">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ============ END PANE 4 ============ -->

                    <!-- ============ PANE 5: Documents ============ -->
                    <div class="wizard-pane" id="pane-documents">
                        <div class="wizard-section-title"><span>Student Documents</span></div>
                        <div class="doc-pane-intro">
                            <div class="doc-pane-intro-text"><i class="fa fa-info-circle"></i><span>Upload the student's documents below. Accepted formats: JPG, JPEG, PNG, PDF.</span></div>
                            <a href="add_student_documents.php" target="_blank" class="doc-pane-manage-btn"><i class="fa fa-plus-circle"></i> Manage Document Titles</a>
                        </div>
                        <div class="doc-grid">
                            <?php $di = 0; foreach ($docTitles as $dt): ?>
                            <div class="doc-card" id="docCard_<?php echo $di; ?>">
                                <div class="doc-card-thumb" id="docThumb_<?php echo $di; ?>"><i class="fa fa-file-alt"></i></div>
                                <div class="doc-card-title" title="<?php echo htmlspecialchars($dt['name']); ?>"><?php echo htmlspecialchars($dt['name']); ?></div>
                                <span class="doc-card-status" id="docStatus_<?php echo $di; ?>">Not Uploaded</span>
                                <br>
                                <label class="doc-card-upload" for="docFile_<?php echo $di; ?>"><i class="fa fa-upload"></i> Choose File</label>
                                <input type="hidden" name="doc_types[]" value="<?php echo (int) $dt['id']; ?>">
                                <input type="file" id="docFile_<?php echo $di; ?>" name="doc_files[]" accept=".jpg,.jpeg,.png,.pdf" onchange="previewStudentDoc(this, <?php echo $di; ?>)">
                                <div class="doc-card-filename" id="docFileName_<?php echo $di; ?>"></div>
                            </div>
                            <?php $di++; endforeach; ?>
                        </div>
                    </div>
                    <!-- ============ END PANE 5 ============ -->

                    <!-- Bottom Action Bar -->
                    <div class="wizard-actions-bar">
                        <div class="mandatory-note">* Marked fields are mandatory</div>
                        <div class="wizard-actions-buttons">
                            <button type="button" class="wizard-btn" id="btnCancel">Cancel</button>
                            <button type="button" class="wizard-btn wizard-btn-primary" id="btnSaveStudent">Save Student</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Script: Searchable Select Family (select2) -->
<script>
$(function () {
    var el = document.getElementById('family_search');
    if (el && $.fn.select2) {
        $('#family_search').select2({ width: '100%', placeholder: 'Select Family', allowClear: true });
    }
});
</script>

<!-- Script: Wizard tab navigation + save modes -->
<script>
(function () {
    var paneOrder = ['basic-info', 'parent-details', 'academic-info', 'contact-info', 'documents'];
    var tabItems  = document.querySelectorAll('.icon-tab-item');
    var panes     = document.querySelectorAll('.wizard-pane');
    var form      = document.getElementById('studentForm');
    var redirectModeInput = document.getElementById('redirect_mode');
    var studentWizardTabs = document.getElementById('studentWizardTabs');
    var navTabs = document.getElementById('studentTabs');

    function activateTab(tabName) {
        var idx = paneOrder.indexOf(tabName);
        if (idx === -1) return;
        tabItems.forEach(function (t) { t.classList.toggle('active', t.dataset.tab === tabName); });
        panes.forEach(function (p) { p.classList.toggle('active', p.id === 'pane-' + tabName); });
        if (navTabs) {
            tabItems.forEach(function (t) { t.classList.remove('active'); });
            tabItems.forEach(function (t) { if (t.dataset.tab === tabName) t.classList.add('active'); });
        }
        if (studentWizardTabs) window.scrollTo({ top: studentWizardTabs.offsetTop - 90, behavior: 'smooth' });
    }

    tabItems.forEach(function (t) {
        t.addEventListener('click', function () { activateTab(t.dataset.tab); });
    });

    function blockOnFirstInvalid(container) {
        var invalid = container.querySelector(':invalid');
        if (invalid) {
            var pane = invalid.closest('.wizard-pane');
            if (pane) activateTab(pane.id.replace('pane-', ''));
            invalid.reportValidity();
            return true;
        }
        return false;
    }

    document.getElementById('btnCancel').addEventListener('click', function () {
        window.location.href = 'manage_students.php';
    });

    function submitWithMode(mode) {
        if (blockOnFirstInvalid(form)) return;
        redirectModeInput.value = mode;
        if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
    }

    document.getElementById('btnSaveStudent').addEventListener('click', function () { submitWithMode('profile'); });
})();
</script>

<!-- Script: Documents pane live preview -->
<script>
function previewStudentDoc(input, index) {
    var card = document.getElementById('docCard_' + index);
    var thumb = document.getElementById('docThumb_' + index);
    var status = document.getElementById('docStatus_' + index);
    var fileName = document.getElementById('docFileName_' + index);
    var file = input.files && input.files[0];
    if (!file) return;
    fileName.textContent = file.name;
    status.textContent = 'Uploaded';
    card.classList.add('has-file');
    if (file.type === 'application/pdf') {
        thumb.innerHTML = '<i class="fa fa-file-pdf-o"></i>';
    } else {
        var reader = new FileReader();
        reader.onload = function (e) {
            thumb.innerHTML = '<img src="' + e.target.result + '" alt="' + file.name + '">';
        };
        reader.readAsDataURL(file);
    }
}
</script>

<!-- Script: Photo upload, drag, zoom, rotate -->
<script>
(function () {
    var fileInput = document.getElementById('fileInput');
    var image = document.getElementById('image');
    var zoomSlider = document.getElementById('zoom-slider');
    var rotateSlider = document.getElementById('rotate-slider');
    var isDragging = false;
    var startX, startY;
    if (!fileInput || !image) return;

    fileInput.addEventListener('change', function (event) {
        var file = event.target.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            image.src = e.target.result;
            image.style.display = 'block';
            image.classList.add('draggable');
            image.style.left = '0px';
            image.style.top = '0px';
            zoomSlider.value = 1;
            rotateSlider.value = 0;
            image.style.transform = '';
        };
        reader.onerror = function () { alert('Failed to read file! Please try again.'); };
        reader.readAsDataURL(file);
    });

    image.addEventListener('mousedown', function (e) {
        isDragging = true;
        startX = e.clientX - (parseFloat(image.style.left) || 0);
        startY = e.clientY - (parseFloat(image.style.top) || 0);
        image.style.cursor = 'grabbing';
    });
    document.addEventListener('mousemove', function (e) {
        if (isDragging) {
            image.style.left = (e.clientX - startX) + 'px';
            image.style.top = (e.clientY - startY) + 'px';
        }
    });
    document.addEventListener('mouseup', function () {
        isDragging = false;
        image.style.cursor = 'grab';
    });

    function applyTransform() {
        image.style.transform = 'scale(' + zoomSlider.value + ') rotate(' + rotateSlider.value + 'deg)';
    }
    zoomSlider.addEventListener('input', applyTransform);
    rotateSlider.addEventListener('input', applyTransform);

    var isProcessing = false;
    document.getElementById('studentForm').addEventListener('submit', function (e) {
        var fcnicEl = document.getElementById('cnic');
        var mcnicEl = document.getElementById('mother_cnic');
        if (fcnicEl && fcnicEl.value.length > 0 && fcnicEl.value.length < 13) {
            e.preventDefault();
            document.getElementById('fcnic-limit-msg').style.display = 'block';
            fcnicEl.focus();
            return false;
        }
        if (mcnicEl && mcnicEl.value.length > 0 && mcnicEl.value.length < 13) {
            e.preventDefault();
            document.getElementById('mcnic-limit-msg').style.display = 'block';
            mcnicEl.focus();
            return false;
        }
        if (isProcessing) return true;
        if (image.style.display !== 'block') return true;

        var hasNewFile = fileInput.files && fileInput.files[0];
        var hasTransformation = parseFloat(zoomSlider.value) !== 1 ||
                                parseInt(rotateSlider.value) !== 0 ||
                                parseFloat(image.style.left) !== 0 ||
                                parseFloat(image.style.top) !== 0;
        if (!hasNewFile && !hasTransformation) return true;

        e.preventDefault();
        isProcessing = true;
        processImageTransformation();
    });

    function processImageTransformation() {
        var canvas = document.getElementById('imageCanvas');
        var form = document.getElementById('studentForm');
        if (!image.complete || !image.naturalWidth) {
            alert('Image not loaded properly. Please try again.');
            isProcessing = false;
            return;
        }
        var zoom = parseFloat(zoomSlider.value) || 1;
        var rotation = parseInt(rotateSlider.value) || 0;
        var imgLeft = parseFloat(image.style.left) || 0;
        var imgTop = parseFloat(image.style.top) || 0;
        var containerWidth = 125;
        var containerHeight = 140;
        var outputScale = 2;
        canvas.width = containerWidth * outputScale;
        canvas.height = containerHeight * outputScale;
        var ctx = canvas.getContext('2d');
        ctx.scale(outputScale, outputScale);
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, containerWidth, containerHeight);
        ctx.save();
        ctx.translate(containerWidth / 2, containerHeight / 2);
        if (rotation !== 0) ctx.rotate((rotation * Math.PI) / 180);
        var imageAspectRatio = image.naturalWidth / image.naturalHeight;
        var containerAspectRatio = containerWidth / containerHeight;
        var baseWidth, baseHeight;
        if (imageAspectRatio > containerAspectRatio) {
            baseWidth = containerWidth;
            baseHeight = containerWidth / imageAspectRatio;
        } else {
            baseHeight = containerHeight;
            baseWidth = containerHeight * imageAspectRatio;
        }
        var drawWidth = baseWidth * zoom;
        var drawHeight = baseHeight * zoom;
        ctx.drawImage(image, -drawWidth / 2 + imgLeft, -drawHeight / 2 + imgTop, drawWidth, drawHeight);
        ctx.restore();
        canvas.toBlob(function (blob) {
            if (!blob) { alert('Failed to process image.'); isProcessing = false; return; }
            var transformedFile = new File([blob], 'transformed_student_image.jpg', { type: 'image/jpeg', lastModified: Date.now() });
            try {
                var dataTransfer = new DataTransfer();
                dataTransfer.items.add(transformedFile);
                fileInput.files = dataTransfer.files;
                form.submit();
            } catch (error) {
                console.error(error);
                alert('Failed to process image. Please try again.');
                isProcessing = false;
            }
        }, 'image/jpeg', 0.98);
    }
})();
</script>

<!-- Script: Sections, Family, State/City, flatpickr -->
<script>
var HIIFI_BASE = '<?php echo BASE_URL; ?>';

function getSection(cid) {
    var sel = document.getElementById('txt_section');
    if (!cid) { sel.innerHTML = '<option value="">Select Section</option>'; return; }
    sel.innerHTML = '<option value="">Loading...</option>';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', HIIFI_BASE + 'ajax_get_sections.php?class_id=' + encodeURIComponent(cid));
    xhr.onload = function () {
        var data;
        try { data = JSON.parse(xhr.responseText || '[]'); } catch (e) { data = []; }
        sel.innerHTML = '<option value="">Select Section</option>';
        data.forEach(function (s) {
            var o = document.createElement('option');
            o.value = s.section_id;
            o.textContent = s.section_name;
            sel.appendChild(o);
        });
    };
    xhr.send();
}

function getFamilyInfo(code) {
    document.getElementById('family_code_value').value = code || '';
    if (!code) return;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', HIIFI_BASE + 'ajax_get_family_by_code.php?code=' + encodeURIComponent(code));
    xhr.onload = function () {
        var data;
        try { data = JSON.parse(xhr.responseText); } catch (e) { return; }
        if (!data) return;
        var map = {
            'cell_no': data.phone,
            'last_name': data.father_name,
            'cnic': data.father_cnic,
            'father_qualification': data.father_qualification,
            'father_occupation': data.father_occupation,
            'Fbusiness_address': data.father_business_address,
            'father_income': data.father_income,
            'mother_name': data.mother_name,
            'mother_cnic': data.mother_cnic,
            'mother_qualification': data.mother_qualification,
            'mother_activity': data.mother_activity,
            'mother_designation': data.mother_designation,
            'address': data.address,
            'gardian_name': data.guardian_name,
            'gardian_cnic': data.guardian_cnic,
            'gardian_no': data.guardian_cellno,
            'gardian_qualification': data.guardian_qualification,
            'gardian_occupation': data.guardian_occupation,
            'gardian_income': data.guardian_income,
            'gardian_email': data.guardian_email,
            'gardian_address': data.guardian_address,
            'father_cellno': data.father_cellno
        };
        Object.keys(map).forEach(function (id) {
            var el = document.getElementById(id);
            if (el && map[id] !== null && map[id] !== undefined && String(map[id]) !== '') {
                if (el.tagName === 'SELECT') {
                    if (el.querySelector('option[value="' + map[id] + '"]')) el.value = map[id];
                } else {
                    el.value = map[id];
                }
            }
        });
        if (data.locality_id) {
            var loc = document.getElementById('locality');
            if (loc) loc.value = data.locality_id;
        }
    };
    xhr.send();
}

var stateMap = {
    'Punjab': ['Lahore','Rawalpindi','Faisalabad','Multan','Gujranwala','Sialkot','Bahawalpur','Sargodha','Sheikhupura','Rahim Yar Khan','Jhang','Kasur','Gujrat','Okara','Sahiwal','Mianwali','Dera Ghazi Khan','Attock','Chakwal','Mandi Bahauddin','Vehari','Muzaffargarh','Khanewal','Wazirabad','Hafizabad','Narowal','Burewala','Toba Tek Singh'],
    'Sindh': ['Karachi','Hyderabad','Sukkur','Larkana','Nawabshah','Mirpur Khas','Badin','Shikarpur','Dadu','Thatta','Jacobabad','Ghorki'],
    'Balochistan': ['Quetta','Khuzdar','Turbat','Gwadar','Chaman','Sibi','Zhob','Noshki'],
    'KPK': ['Peshawar','Mardan','Swat','Abbottabad','Kohat','Bannu','Charsadda','Dera Ismail Khan','Nowshera','Mansehra','Haripur','Swabi'],
    'Gilgit-Baltistan': ['Gilgit','Skardu','Hunza','Nagar','Ghizer','Astore'],
    'Kashmir (territory)': ['Muzaffarabad','Mirpur','Rawalakot','Kotli','Bhimber'],
    'FATA (territory)': ['Parachinar','Miranshah','Wana','Kurram'],
    'Federal': ['Islamabad']
};
function getCity(stateVal) {
    var cityEl = document.getElementById('city');
    cityEl.innerHTML = '<option value="">Select City</option>';
    var list = stateMap[stateVal];
    if (!list) return;
    list.forEach(function (c) {
        var o = document.createElement('option');
        o.value = c;
        o.textContent = c;
        cityEl.appendChild(o);
    });
}

(function () {
    function loadFP(cb) {
        if (window.flatpickr) { cb(); return; }
        if (document.getElementById('fp-css')) { waitFP(cb); return; }
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js';
        s.onload = function () {
            var l = document.createElement('link');
            l.id = 'fp-css'; l.rel = 'stylesheet';
            l.href = 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css';
            document.head.appendChild(l);
            cb();
        };
        document.head.appendChild(s);
    }
    function waitFP(cb) {
        if (window.flatpickr) { cb(); return; }
        setTimeout(function () { waitFP(cb); }, 60);
    }
    loadFP(function () {
        var today = '<?php echo date('d/M/Y'); ?>';
        flatpickr('#dob', { dateFormat: 'd/M/Y', defaultDate: today });
        flatpickr('#date_of_adms', { dateFormat: 'd/M/Y', defaultDate: today });
    });
})();
</script>