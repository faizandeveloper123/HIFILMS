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
<div class="alert" style="margin:16px 24px;padding:12px 16px;border-radius:8px;background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;font-size:13px;">
    <i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                brand: '#f97316',
                'brand-dark': '#ea580c',
                sidebar: '#161922',
            },
            fontFamily: {
                inter: ['Inter', 'sans-serif'],
            }
        }
    }
}
</script>

<style>
body { font-family: 'Inter', sans-serif; }

/* ---- Fieldset-Box floating label ---- */
.fieldset-box {
    position: relative;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    padding: 1rem 0.75rem 0.5rem;
    background: #fff;
    transition: border-color 0.2s;
}
.fieldset-box:focus-within { border-color: #f97316; }
.fieldset-label {
    position: absolute;
    top: -0.6rem;
    left: 0.75rem;
    background: #fff;
    padding: 0 0.25rem;
    font-size: 0.7rem;
    color: #f97316;
    font-weight: 600;
    pointer-events: none;
}
.fieldset-input,
.fieldset-select {
    width: 100%;
    border: none;
    outline: none;
    font-size: 0.875rem;
    color: #1f2937;
    background: transparent;
    padding: 0;
    line-height: 1.5;
}
.fieldset-select { appearance: none; cursor: pointer; }
.fieldset-select option { color: #1f2937; }
.fieldset-input::placeholder { color: #9ca3af; }
.fieldset-input:read-only { background: #f9fafb; cursor: default; }

/* ---- Photo upload frame ---- */
#image-container {
    width: 100%;
    aspect-ratio: 11/12;
    border: 2px dashed #fdba74;
    border-radius: 0.5rem;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden;
    background: #fffbeb;
    position: relative;
}
#image { display:none; position:absolute; max-width:100%; max-height:100%; object-fit:contain; transition:transform .2s ease; cursor:move; }
#image, #sample-image { display:none; position:absolute; transition:transform .2s ease; }
#sample-image { pointer-events:none; }
.draggable { cursor:move; }

/* ---- Scrollbar ---- */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* ---- Range accent ---- */
input[type="range"] { accent-color: #f97316; }

/* ---- Doc cards ---- */
.doc-card:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

/* ---- Toast ---- */
#toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 9999; }
.toast-msg {
    background: #fff; border-left: 4px solid #f97316; border-radius: 8px;
    padding: 12px 16px; margin-top: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: flex; align-items: center; gap: 8px; font-size: 13px; color: #374151;
    animation: slideIn 0.3s ease;
}
@keyframes slideIn { from { transform: translateX(100%); opacity:0; } to { transform: translateX(0); opacity:1; } }

/* ---- Select2 overrides for fieldset ---- */
.fieldset-box .select2-container { width: 100% !important; }
.fieldset-box .select2-container .select2-choice {
    border: none !important; background: none !important; box-shadow: none !important;
    padding: 0 !important; height: auto !important; line-height: 1.5 !important;
}
.fieldset-box .select2-container .select2-choice .select2-chosen { line-height: 1.5; }
.fieldset-box .select2-container .select2-choice .select2-arrow { border: none !important; background: none !important; }
</style>

<div class="py-4 px-6 min-h-screen bg-gray-50 font-inter">

    <!-- Breadcrumb -->
    <div class="mb-4 text-sm text-gray-500 font-medium">
        <a href="dashboard.php" class="text-orange-600 hover:underline"><i class="fa fa-home"></i> Home</a>
        <span class="mx-2 text-gray-400">&raquo;</span>
        <a href="manage_students.php" class="text-orange-600 hover:underline">Student Management</a>
        <span class="mx-2 text-gray-400">&raquo;</span>
        <span class="text-gray-800 font-semibold">Add New Student</span>
    </div>

    <!-- Main Tabs -->
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <a href="#add-single" class="main-tab active inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-orange-500 text-white shadow transition" data-view="single">
            <i class="fa fa-user-plus"></i> Add New Student
        </a>
        <a href="bulk_stdns.php" class="main-tab inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 transition">
            <i class="fa fa-users"></i> Add Multi Students
        </a>
        <a href="import_data.php" class="main-tab inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 transition">
            <i class="fa fa-upload"></i> Import Students with CSV
        </a>
        <a href="adm_form.php" target="_blank" class="main-tab inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 transition">
            <i class="fa fa-file-lines"></i> Admission Form
        </a>
    </div>

    <!-- Step Wizard Banner -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
        <div class="flex items-center justify-center max-w-xl mx-auto gap-0">
            <div class="flex flex-col items-center z-10">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-orange-400 to-orange-600 text-white flex items-center justify-center font-bold text-sm shadow-md">1</div>
                <span class="mt-1.5 text-xs font-bold text-orange-600">Student Info</span>
            </div>
            <div class="flex-1 h-0.5 bg-gradient-to-r from-orange-400 to-gray-200 mx-3 mt-[-10px]"></div>
            <div class="flex flex-col items-center z-10">
                <div class="w-9 h-9 rounded-full border-2 border-gray-200 bg-white text-gray-400 flex items-center justify-center font-bold text-sm">2</div>
                <span class="mt-1.5 text-xs text-gray-400">Fee & Parent Info</span>
            </div>
        </div>
    </div>

    <!-- Card Container -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">

        <!-- Inner Tab Nav -->
        <div class="flex gap-1 p-1.5 bg-gray-100 rounded-xl mb-6 overflow-x-auto" id="studentWizardTabs">
            <button type="button" class="inner-tab active flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-medium whitespace-nowrap transition" data-tab="basic-info">
                <i class="fa fa-id-card"></i> Basic Information
            </button>
            <button type="button" class="inner-tab flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-medium whitespace-nowrap transition text-gray-500 hover:bg-white hover:text-gray-700" data-tab="parent-details">
                <i class="fa fa-users"></i> Parent Details
            </button>
            <button type="button" class="inner-tab flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-medium whitespace-nowrap transition text-gray-500 hover:bg-white hover:text-gray-700" data-tab="academic-info">
                <i class="fa fa-graduation-cap"></i> Academic Information
            </button>
            <button type="button" class="inner-tab flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-medium whitespace-nowrap transition text-gray-500 hover:bg-white hover:text-gray-700" data-tab="contact-info">
                <i class="fa fa-phone"></i> Contact Information
            </button>
            <button type="button" class="inner-tab flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-medium whitespace-nowrap transition text-gray-500 hover:bg-white hover:text-gray-700" data-tab="documents">
                <i class="fa fa-file-lines"></i> Documents
            </button>
        </div>

        <style>
        .inner-tab.active {
            background: #fff;
            color: #2563eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            font-weight: 600;
        }
        .inner-tab.active i { color: #2563eb; }
        .inner-tab-pane { display: none; }
        .inner-tab-pane.active { display: block; }
        </style>

        <form id="studentForm" action="<?php echo BASE_URL; ?>add_student.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="AddAdmission">
            <input type="hidden" name="captured_image" id="captured_image" value="">
            <input type="hidden" name="redirect_mode" id="redirect_mode" value="">
            <input type="hidden" name="family_code" id="family_code_value" value="">
            <input type="hidden" name="old_file" value="">

            <!-- ============ PANE 1: Basic Information ============ -->
            <div class="inner-tab-pane active" id="pane-basic-info">
                <div class="grid grid-cols-12 gap-5">
                    <!-- Left: Form Fields -->
                    <div class="col-span-12 lg:col-span-8">
                        <!-- Row 1 -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            <div class="fieldset-box">
                                <label class="fieldset-label">Student Name *</label>
                                <input type="text" class="fieldset-input" name="first_name" required id="fname" placeholder="Enter student name" maxlength="35">
                            </div>
                            <div class="fieldset-box">
                                <label class="fieldset-label">Father Name</label>
                                <input type="text" class="fieldset-input" name="lname" id="last_name" placeholder="Enter father name" maxlength="35">
                            </div>
                            <div class="fieldset-box">
                                <label class="fieldset-label">Select Family</label>
                                <select name="family_search" id="family_search" class="fieldset-select" onChange="getFamilyInfo(this.value);">
                                    <option value="" disabled selected>Select Family</option>
                                    <?php foreach ($families as $f): ?>
                                    <option value="<?php echo htmlspecialchars($f['family_code']); ?>"><?php echo htmlspecialchars(trim(($f['last_name'] ?? '') . ' - ' . $f['family_code'], ' -')); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fieldset-box">
                                <label class="fieldset-label">Cell Number *</label>
                                <input type="text" class="fieldset-input" name="cellno" required id="cell_no" placeholder="Enter cell number">
                            </div>
                        </div>
                        <!-- Row 2 -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            <div class="fieldset-box">
                                <label class="fieldset-label">Session *</label>
                                <select name="session" required id="session" class="fieldset-select">
                                    <option value="" disabled selected>Select Session</option>
                                    <?php foreach ($sessions as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $s === $cur_session ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fieldset-box">
                                <label class="fieldset-label">Select Class *</label>
                                <select name="class" required id="class" class="fieldset-select" onChange="getSection(this.value);">
                                    <option value="" disabled selected>Select Class</option>
                                    <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo (int) $c['class_id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fieldset-box" id="sec">
                                <label class="fieldset-label">Select Section *</label>
                                <select name="section" required id="txt_section" class="fieldset-select">
                                    <option value="" disabled selected>Select Section</option>
                                </select>
                            </div>
                            <div class="fieldset-box">
                                <label class="fieldset-label">GR-No</label>
                                <input type="text" class="fieldset-input" name="com_no" id="com_no" value="<?php echo htmlspecialchars($nextGr); ?>" readonly>
                            </div>
                        </div>
                        <!-- Row 3 -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            <div class="fieldset-box">
                                <label class="fieldset-label">Gender *</label>
                                <select id="gender" name="gender" class="fieldset-select" required>
                                    <option value="" disabled selected>Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="fieldset-box">
                                <label class="fieldset-label">Religion *</label>
                                <select id="religion" name="religion" class="fieldset-select" required>
                                    <option value="" disabled selected>Select Religion</option>
                                    <option value="Muslim" selected>Muslim</option>
                                    <option value="Hinduism">Hindu</option>
                                    <option value="Sikhism">Sikh</option>
                                    <option value="Christian">Christian</option>
                                </select>
                            </div>
                            <div class="fieldset-box">
                                <label class="fieldset-label">Date Of Birth</label>
                                <input type="text" class="fieldset-input" name="dob" id="dob" placeholder="dd/M/yyyy" value="<?php echo date('d/M/Y'); ?>">
                            </div>
                            <div class="fieldset-box">
                                <label class="fieldset-label">Date Of Admission</label>
                                <input type="text" class="fieldset-input" name="date_of_adms" id="date_of_adms" placeholder="dd/M/yyyy" value="<?php echo date('d/M/Y'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Right: Photo Upload Widget -->
                    <div class="col-span-12 lg:col-span-4">
                        <div class="bg-white border border-orange-200 rounded-xl p-4 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-bold text-gray-700">Student Photo</span>
                                <button type="button" id="remove-image-btn" class="text-xs text-red-500 hover:text-red-700 font-semibold hidden" onclick="removeStudentPhoto();">Delete Frame</button>
                            </div>
                            <div id="image-container" class="mx-auto" style="width:176px;height:192px;">
                                <img id="image" src="" alt="Uploaded Image" class="draggable" style="position:absolute;">
                                <img id="sample-image" src="" alt="Sample Image" style="display:none;">
                                <canvas id="imageCanvas" style="display:none;"></canvas>
                            </div>
                            <div class="mt-3 space-y-2">
                                <div class="flex items-center gap-2 text-xs text-gray-600">
                                    <i class="fa fa-search-plus text-orange-500"></i>
                                    <span class="w-10">Zoom</span>
                                    <input type="range" id="zoom-slider" min="0.5" max="2" step="0.05" value="1" class="flex-1 h-1.5">
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-600">
                                    <i class="fa fa-sync-alt text-orange-500"></i>
                                    <span class="w-10">Rotate</span>
                                    <input type="range" id="rotate-slider" min="-180" max="180" step="1" value="0" class="flex-1 h-1.5">
                                </div>
                            </div>
                            <label for="fileInput" class="mt-3 block text-center text-xs font-semibold text-orange-600 bg-orange-50 hover:bg-orange-100 rounded-lg py-2 cursor-pointer transition border border-orange-200">
                                <i class="fa fa-upload mr-1"></i> Upload Picture
                            </label>
                            <input type="file" class="hidden" name="img_file" id="fileInput" accept="image/*">
                        </div>
                    </div>
                </div>

                <!-- Row 4: Board, Group, Source, Locality with Add New -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5">
                    <div>
                        <div class="fieldset-box">
                            <label class="fieldset-label">Board/Council</label>
                            <select id="board_council" name="board_council" class="fieldset-select">
                                <option value="" disabled selected>Select Board/Council</option>
                                <?php foreach ($boards as $b): ?>
                                <option value="<?php echo (int) $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" onclick="openAddNewModal('board')" class="mt-1.5 text-xs font-semibold text-orange-600 hover:text-orange-800 flex items-center gap-1">
                            <i class="fa fa-plus-circle"></i> Add New
                        </button>
                    </div>
                    <div>
                        <div class="fieldset-box">
                            <label class="fieldset-label">Group/Shift</label>
                            <select id="group_shift" name="group_shift" class="fieldset-select">
                                <option value="" disabled selected>Select Group/Shift</option>
                                <?php foreach ($groups as $g): ?>
                                <option value="<?php echo (int) $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" onclick="openAddNewModal('group')" class="mt-1.5 text-xs font-semibold text-orange-600 hover:text-orange-800 flex items-center gap-1">
                            <i class="fa fa-plus-circle"></i> Add New
                        </button>
                    </div>
                    <div>
                        <div class="fieldset-box">
                            <label class="fieldset-label">Admission Source</label>
                            <select id="adm_source" name="adm_source" class="fieldset-select">
                                <option value="" disabled selected>Select Admission Source</option>
                                <?php foreach ($admSrcs as $a): ?>
                                <option value="<?php echo (int) $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" onclick="openAddNewModal('source')" class="mt-1.5 text-xs font-semibold text-orange-600 hover:text-orange-800 flex items-center gap-1">
                            <i class="fa fa-plus-circle"></i> Add New
                        </button>
                    </div>
                    <div>
                        <div class="fieldset-box">
                            <label class="fieldset-label">Choose Locality</label>
                            <select name="Locality" id="locality" class="fieldset-select">
                                <option value="" disabled selected>Choose Locality</option>
                                <?php foreach ($localities as $l): ?>
                                <option value="<?php echo (int) $l['locality_id']; ?>"><?php echo htmlspecialchars($l['locality_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" onclick="openAddNewModal('locality')" class="mt-1.5 text-xs font-semibold text-orange-600 hover:text-orange-800 flex items-center gap-1">
                            <i class="fa fa-plus-circle"></i> Add New
                        </button>
                    </div>
                </div>
            </div>
            <!-- ============ END PANE 1 ============ -->

            <!-- ============ PANE 2: Parent Details ============ -->
            <div class="inner-tab-pane" id="pane-parent-details">
                <!-- Family Information -->
                <h3 class="text-sm font-bold text-gray-700 mb-3 pl-3 border-l-4 border-purple-500">Family Information</h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                    <div class="fieldset-box">
                        <label class="fieldset-label">Father CNIC</label>
                        <input type="text" class="fieldset-input" name="cnic" id="cnic" placeholder="Father CNIC" maxlength="13" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,13); document.getElementById('fcnic-limit-msg').style.display=(this.value.length>0 && this.value.length<13)?'block':'none';">
                        <small id="fcnic-limit-msg" class="text-red-500 text-[10px] hidden">Must be exactly 13 digits.</small>
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Father Qualification</label>
                        <input type="text" class="fieldset-input" name="Fqualification" id="father_qualification" placeholder="Father Qualification">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Father Business Address</label>
                        <input type="text" class="fieldset-input" name="Fbusiness_address" id="Fbusiness_address" placeholder="Business Address">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Father Income</label>
                        <input type="text" class="fieldset-input" name="Fincome" id="father_income" placeholder="Father Income">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Mother Name</label>
                        <input type="text" class="fieldset-input" name="mother_name" id="mother_name" placeholder="Mother Name">
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                    <div class="fieldset-box">
                        <label class="fieldset-label">Mother CNIC</label>
                        <input type="text" class="fieldset-input" name="mother_cnic" id="mother_cnic" placeholder="Mother CNIC" maxlength="13" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,13); document.getElementById('mcnic-limit-msg').style.display=(this.value.length>0 && this.value.length<13)?'block':'none';">
                        <small id="mcnic-limit-msg" class="text-red-500 text-[10px] hidden">Must be exactly 13 digits.</small>
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Mother Qualification</label>
                        <input type="text" class="fieldset-input" name="mother_qualification" id="mother_qualification" placeholder="Mother Qualification">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Mother Activities</label>
                        <select id="mother_activity" name="mother_activity" class="fieldset-select">
                            <option value="" disabled selected>Mother Activities</option>
                            <option value="House Lady">House Lady</option>
                            <option value="Job Holder">Job Holder</option>
                        </select>
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Mother Designation</label>
                        <input type="text" class="fieldset-input" name="mother_designation" id="mother_designation" placeholder="Mother Designation">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Home Address</label>
                        <input type="text" class="fieldset-input" name="address" id="address" placeholder="Family Home Address">
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    <div>
                        <div class="fieldset-box">
                            <label class="fieldset-label">Father Occupation</label>
                            <select name="father_occupation" id="father_occupation" class="fieldset-select">
                                <option value="" disabled selected>Choose Occupation</option>
                                <?php foreach ($occupations as $o): ?>
                                <option value="<?php echo (int) $o['id']; ?>"><?php echo htmlspecialchars($o['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" onclick="window.open('manage_occupations.php','_blank')" class="mt-1 text-xs font-semibold text-orange-600 hover:text-orange-800 flex items-center gap-1">
                            <i class="fa fa-plus-circle"></i> Add New
                        </button>
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">B-Form No</label>
                        <input type="text" class="fieldset-input" name="formBNo" id="formBNo" placeholder="Form-B No">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Cast</label>
                        <input type="text" class="fieldset-input" name="cast" id="cast" placeholder="Cast">
                    </div>
                </div>

                <!-- Guardian Information -->
                <hr class="my-4 border-gray-200">
                <h3 class="text-sm font-bold text-gray-700 mb-3 pl-3 border-l-4 border-purple-500">
                    Guardian Information <span class="text-xs font-normal text-gray-400">(fill in case of father's death)</span>
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="fieldset-box">
                        <label class="fieldset-label">Guardian Name</label>
                        <input type="text" class="fieldset-input" name="gname" id="gardian_name" placeholder="Guardian Name">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Guardian CNIC</label>
                        <input type="text" class="fieldset-input" name="Gcnic" id="gardian_cnic" placeholder="Guardian CNIC">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Guardian Cell No</label>
                        <input type="text" class="fieldset-input" name="Gcellno" id="gardian_no" placeholder="Guardian Cell No">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Guardian Qualification</label>
                        <input type="text" class="fieldset-input" name="Gqualification" id="gardian_qualification" placeholder="Guardian Qualification">
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="fieldset-box">
                        <label class="fieldset-label">Guardian Occupation</label>
                        <input type="text" class="fieldset-input" name="Goccupation" id="gardian_occupation" placeholder="Guardian Occupation">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Guardian Income</label>
                        <input type="text" class="fieldset-input" name="Gincome" id="gardian_income" placeholder="Guardian Income">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Guardian Email</label>
                        <input type="text" class="fieldset-input" name="gardian_email" id="gardian_email" placeholder="Guardian Email">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Guardian Address</label>
                        <input type="text" class="fieldset-input" name="Gaddress" id="gardian_address" placeholder="Guardian Address">
                    </div>
                </div>
            </div>
            <!-- ============ END PANE 2 ============ -->

            <!-- ============ PANE 3: Academic Information ============ -->
            <div class="inner-tab-pane" id="pane-academic-info">
                <h3 class="text-sm font-bold text-gray-700 mb-3 pl-3 border-l-4 border-green-500">Admission &amp; Academic Information</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="fieldset-box">
                        <label class="fieldset-label">Previous Class</label>
                        <input type="text" class="fieldset-input" name="old_class" id="old_class" placeholder="Previous Class">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Previous Institute</label>
                        <input type="text" class="fieldset-input" name="old_school" id="old_school" placeholder="Previous Institute">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Total Marks</label>
                        <input type="text" class="fieldset-input" name="old_tmarks" id="old_tmarks" placeholder="Total Marks">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Obtained Marks</label>
                        <input type="text" class="fieldset-input" name="old_obtmarks" id="old_obtmarks" placeholder="Obtained Marks">
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="fieldset-box">
                        <label class="fieldset-label">Admission Form No</label>
                        <input type="text" class="fieldset-input" name="form_no" id="adm-no" placeholder="Admission Form No">
                    </div>
                    <div class="fieldset-box md:col-span-3">
                        <label class="fieldset-label">Reason for Previous School Leaving</label>
                        <input type="text" class="fieldset-input" name="school_leaving" id="school_leaving" placeholder="Reason of Previous School Leaving">
                    </div>
                </div>
            </div>
            <!-- ============ END PANE 3 ============ -->

            <!-- ============ PANE 4: Contact Information ============ -->
            <div class="inner-tab-pane" id="pane-contact-info">
                <h3 class="text-sm font-bold text-gray-700 mb-3 pl-3 border-l-4 border-blue-500">Contact &amp; Address Information</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="fieldset-box">
                        <label class="fieldset-label">Whatsapp No</label>
                        <input type="text" class="fieldset-input" name="whatsapp_number" id="whatsapp_number" placeholder="Whatsapp Number">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Father Cell No</label>
                        <input type="text" class="fieldset-input" name="father_cellno" id="father_cellno" placeholder="Father Cell No">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Mother Cell No</label>
                        <input type="text" class="fieldset-input" name="mother_cell" id="mother_cell" placeholder="Mother Cell Number">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Home Cell No</label>
                        <input type="text" class="fieldset-input" name="home_number" id="home_number" placeholder="Home PTCL Number">
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="fieldset-box">
                        <label class="fieldset-label">Place Of Birth</label>
                        <input type="text" class="fieldset-input" name="place_of_birth" id="place_of_birth" placeholder="Place Of Birth">
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Select State</label>
                        <select name="state" id="state" class="fieldset-select" onChange="getCity(this.value);">
                            <option value="" disabled selected>Select State</option>
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
                    <div class="fieldset-box">
                        <label class="fieldset-label">City</label>
                        <select name="city" id="city" class="fieldset-select">
                            <option value="" disabled selected>Select City</option>
                        </select>
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Email</label>
                        <input type="text" class="fieldset-input" name="email" id="email" placeholder="Email">
                    </div>
                </div>
            </div>
            <!-- ============ END PANE 4 ============ -->

            <!-- ============ PANE 5: Documents ============ -->
            <div class="inner-tab-pane" id="pane-documents">
                <h3 class="text-sm font-bold text-gray-700 mb-3 pl-3 border-l-4 border-gray-400">Student Documents</h3>
                <div class="flex items-center justify-between gap-3 mb-4 p-3 bg-orange-50 border border-orange-200 rounded-lg text-xs text-amber-700">
                    <div class="flex items-center gap-2">
                        <i class="fa fa-circle-info text-orange-500"></i>
                        <span>Upload the student's documents below. Accepted formats: JPG, JPEG, PNG, PDF.</span>
                    </div>
                    <a href="add_student_documents.php" target="_blank" class="inline-flex items-center gap-1 px-3 py-1.5 bg-orange-500 text-white text-xs font-bold rounded-md hover:bg-orange-600 transition whitespace-nowrap">
                        <i class="fa fa-plus-circle"></i> Manage Titles
                    </a>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <?php $di = 0; foreach ($docTitles as $dt): ?>
                    <div class="bg-white border border-gray-200 rounded-lg p-3 text-center transition hover:shadow-md hover:border-orange-300 doc-card" id="docCard_<?php echo $di; ?>">
                        <div class="w-full h-16 rounded-md bg-gray-50 flex items-center justify-center border border-dashed border-gray-300 mb-2 overflow-hidden" id="docThumb_<?php echo $di; ?>">
                            <i class="fa fa-file-lines text-2xl text-gray-300"></i>
                        </div>
                        <div class="text-xs font-semibold text-gray-700 truncate mb-1" title="<?php echo htmlspecialchars($dt['name']); ?>"><?php echo htmlspecialchars($dt['name']); ?></div>
                        <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 mb-2" id="docStatus_<?php echo $di; ?>">Not Uploaded</span>
                        <label class="block text-[11px] font-semibold text-orange-600 bg-orange-50 hover:bg-orange-100 rounded-md py-1.5 cursor-pointer transition border border-orange-200" for="docFile_<?php echo $di; ?>">
                            <i class="fa fa-upload mr-0.5"></i> Choose File
                        </label>
                        <input type="hidden" name="doc_types[]" value="<?php echo (int) $dt['id']; ?>">
                        <input type="file" id="docFile_<?php echo $di; ?>" name="doc_files[]" class="hidden" accept=".jpg,.jpeg,.png,.pdf" onchange="previewStudentDoc(this, <?php echo $di; ?>)">
                        <div class="text-[9px] text-gray-400 mt-1 truncate" id="docFileName_<?php echo $di; ?>"></div>
                    </div>
                    <?php $di++; endforeach; ?>
                </div>
            </div>
            <!-- ============ END PANE 5 ============ -->

            <!-- Footer Actions -->
            <div class="flex items-center justify-between mt-8 pt-5 border-t border-gray-200">
                <span class="text-xs text-gray-400">* Fields marked with an asterisk are required.</span>
                <div class="flex items-center gap-3">
                    <button type="button" class="px-5 py-2.5 rounded-lg text-sm font-semibold bg-white text-gray-600 border border-gray-300 hover:bg-gray-50 transition" id="btnCancel">Cancel</button>
                    <button type="button" class="px-6 py-2.5 rounded-lg text-sm font-semibold bg-gradient-to-r from-orange-400 to-orange-600 text-white shadow-md hover:shadow-lg transition" id="btnSaveStudent">
                        <i class="fa fa-save mr-1"></i> Save Student
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add New Modal -->
<div id="addNewModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-bold text-gray-800" id="addNewModalTitle">Add New</h3>
            <button type="button" onclick="closeAddNewModal()" class="text-gray-400 hover:text-gray-600 text-lg"><i class="fa fa-times"></i></button>
        </div>
        <div class="fieldset-box mb-4">
            <label class="fieldset-label" id="addNewModalLabel">Name</label>
            <input type="text" class="fieldset-input" id="addNewModalInput" placeholder="Enter name">
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" onclick="closeAddNewModal()" class="px-4 py-2 rounded-lg text-sm font-semibold bg-white text-gray-600 border border-gray-300 hover:bg-gray-50">Cancel</button>
            <button type="button" onclick="submitAddNew()" class="px-4 py-2 rounded-lg text-sm font-semibold bg-orange-500 text-white hover:bg-orange-600 transition">
                <i class="fa fa-plus mr-1"></i> Add
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<!-- Script: Searchable Select Family (select2) -->
<script>
$(function () {
    var el = document.getElementById('family_search');
    if (el && $.fn.select2) {
        $('#family_search').select2({ width: '100%', placeholder: 'Select Family', allowClear: true });
    }
});
</script>

<!-- Script: Inner tab navigation -->
<script>
(function () {
    var paneOrder = ['basic-info', 'parent-details', 'academic-info', 'contact-info', 'documents'];
    var tabItems  = document.querySelectorAll('.inner-tab');
    var panes     = document.querySelectorAll('.inner-tab-pane');
    var form      = document.getElementById('studentForm');
    var redirectModeInput = document.getElementById('redirect_mode');
    var studentWizardTabs = document.getElementById('studentWizardTabs');

    function activateTab(tabName) {
        var idx = paneOrder.indexOf(tabName);
        if (idx === -1) return;
        tabItems.forEach(function (t) {
            var isActive = t.dataset.tab === tabName;
            t.classList.toggle('active', isActive);
            if (!isActive) {
                t.classList.remove('text-gray-500');
            } else {
                t.classList.remove('text-gray-500');
            }
        });
        panes.forEach(function (p) { p.classList.toggle('active', p.id === 'pane-' + tabName); });
        if (studentWizardTabs) window.scrollTo({ top: studentWizardTabs.offsetTop - 90, behavior: 'smooth' });
    }

    tabItems.forEach(function (t) {
        t.addEventListener('click', function () { activateTab(t.dataset.tab); });
    });

    function blockOnFirstInvalid(container) {
        var invalid = container.querySelector(':invalid');
        if (invalid) {
            var pane = invalid.closest('.inner-tab-pane');
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
    status.className = 'inline-block text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 mb-2';
    card.classList.add('border-green-300');
    thumb.classList.add('border-solid', 'border-green-300');
    if (file.type === 'application/pdf') {
        thumb.innerHTML = '<i class="fa fa-file-pdf text-2xl text-red-400"></i>';
    } else {
        var reader = new FileReader();
        reader.onload = function (e) {
            thumb.innerHTML = '<img src="' + e.target.result + '" alt="' + file.name + '" class="w-full h-full object-cover">';
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
    var removeBtn = document.getElementById('remove-image-btn');
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
            if (removeBtn) removeBtn.classList.remove('hidden');
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

    window.removeStudentPhoto = function() {
        image.src = '';
        image.style.display = 'none';
        image.classList.remove('draggable');
        image.style.left = '0px';
        image.style.top = '0px';
        zoomSlider.value = 1;
        rotateSlider.value = 0;
        image.style.transform = '';
        fileInput.value = '';
        if (removeBtn) removeBtn.classList.add('hidden');
    };

    var isProcessing = false;
    document.getElementById('studentForm').addEventListener('submit', function (e) {
        var fcnicEl = document.getElementById('cnic');
        var mcnicEl = document.getElementById('mother_cnic');
        if (fcnicEl && fcnicEl.value.length > 0 && fcnicEl.value.length < 13) {
            e.preventDefault();
            document.getElementById('fcnic-limit-msg').classList.remove('hidden');
            fcnicEl.focus();
            return false;
        }
        if (mcnicEl && mcnicEl.value.length > 0 && mcnicEl.value.length < 13) {
            e.preventDefault();
            document.getElementById('mcnic-limit-msg').classList.remove('hidden');
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
        var containerWidth = 176;
        var containerHeight = 192;
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

<!-- Script: Sections, Family, State/City, flatpickr, Add New Modal -->
<script>
var HIIFI_BASE = '<?php echo BASE_URL; ?>';

function getSection(cid) {
    var sel = document.getElementById('txt_section');
    if (!cid) { sel.innerHTML = '<option value="" disabled selected>Select Section</option>'; return; }
    sel.innerHTML = '<option value="">Loading...</option>';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', HIIFI_BASE + 'ajax_get_sections.php?class_id=' + encodeURIComponent(cid));
    xhr.onload = function () {
        var data;
        try { data = JSON.parse(xhr.responseText || '[]'); } catch (e) { data = []; }
        sel.innerHTML = '<option value="" disabled selected>Select Section</option>';
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
    cityEl.innerHTML = '<option value="" disabled selected>Select City</option>';
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

/* ---- Add New Modal ---- */
var addNewType = '';
var addNewEndpoints = {
    board:   'manage_board.php',
    group:   'manage_group.php',
    source:  'manage_admission_sources.php',
    locality:'manage_localities.php'
};
var addNewLabels = {
    board: 'Board/Council',
    group: 'Group/Shift',
    source: 'Admission Source',
    locality: 'Locality'
};

function openAddNewModal(type) {
    addNewType = type;
    document.getElementById('addNewModalTitle').textContent = 'Add New ' + addNewLabels[type];
    document.getElementById('addNewModalLabel').textContent = addNewLabels[type] + ' Name';
    document.getElementById('addNewModalInput').value = '';
    var modal = document.getElementById('addNewModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('addNewModalInput').focus();
}

function closeAddNewModal() {
    var modal = document.getElementById('addNewModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function submitAddNew() {
    var val = document.getElementById('addNewModalInput').value.trim();
    if (!val) { showToast('Please enter a name.', 'error'); return; }
    window.open(addNewEndpoints[addNewType], '_blank');
    closeAddNewModal();
    showToast(addNewLabels[addNewType] + ' management page opened. Refresh after adding.', 'info');
}

function showToast(msg, type) {
    var container = document.getElementById('toast-container');
    var toast = document.createElement('div');
    toast.className = 'toast-msg';
    var icon = type === 'error' ? 'fa-exclamation-circle text-red-500' : 'fa-info-circle text-orange-500';
    toast.innerHTML = '<i class="fa ' + icon + '"></i> <span>' + msg + '</span>';
    container.appendChild(toast);
    setTimeout(function () { toast.remove(); }, 4000);
}
</script>