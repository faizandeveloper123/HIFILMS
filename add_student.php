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

/* ---------------- AJAX / JSON handlers (lookup, multi-save, CSV import) ---------------- */
if (($_POST['action'] ?? '') === 'AddLookup') {
    header('Content-Type: application/json');
    $tableMap = ['boards', 'groups', 'admission_sources', 'document_titles', 'localities'];
    $table = $_POST['table'] ?? '';
    $name  = trim($_POST['name'] ?? '');
    $id    = 0;
    if (!in_array($table, $tableMap, true) || $name === '') {
        echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
        exit;
    }
    try {
        if ($table === 'localities') {
            $st = db_prepare('INSERT INTO localities (locality_name, status) VALUES (?, 1)');
            $st->bind_param('s', $name);
            $st->execute();
            $id = $st->insert_id;
        } else {
            $st = db_prepare("INSERT INTO `$table` (name) VALUES (?)");
            $st->bind_param('s', $name);
            $st->execute();
            $id = $st->insert_id;
        }
        echo json_encode(['ok' => true, 'id' => $id, 'name' => $name]);
    } catch (Exception $ex) {
        echo json_encode(['ok' => false, 'msg' => $ex->getMessage()]);
    }
    exit;
}

if (($_POST['action'] ?? '') === 'SaveMultiStudents') {
    header('Content-Type: application/json');
    $rows      = json_decode($_POST['rows'] ?? '[]', true);
    $session   = trim($_POST['session'] ?? '');
    $class_id  = (int)($_POST['class_id'] ?? 0);
    $section_id = (int)($_POST['section_id'] ?? 0);
    $inserted  = 0;
    if (is_array($rows)) {
        $adm = date('Y-m-d');
        foreach ($rows as $r) {
            $name   = trim($r['name'] ?? '');
            $father = trim($r['father'] ?? '');
            $cell   = trim($r['cell'] ?? '');
            $rc = $class_id > 0 ? $class_id : (int)($r['class_id'] ?? 0);
            $rs = $section_id > 0 ? $section_id : 0;
            if ($name === '' || $rc === 0) continue;
            $gender = ($r['gender'] ?? '') === 'Female' ? 'female' : 'male';
            $sid = 0;
            if ($rs > 0) {
                $stmt = db_prepare('INSERT INTO students (first_name, father_name, phone, class_id, section_id, session, gender, admission_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
                $stmt->bind_param('sssiissd', $name, $father, $cell, $rc, $rs, $session, $gender, $adm);
                $stmt->execute();
                $sid = $stmt->insert_id;
            } else {
                $stmt = db_prepare('INSERT INTO students (first_name, father_name, phone, class_id, session, gender, admission_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
                $stmt->bind_param('sssissd', $name, $father, $cell, $rc, $session, $gender, $adm);
                $stmt->execute();
                $sid = $stmt->insert_id;
            }
            if ($sid > 0) {
                $gr = substr(date('Y'), 2) . '-' . str_pad($sid, 4, '0', STR_PAD_LEFT);
                $u = db_prepare('UPDATE students SET gr_no = ? WHERE student_id = ?');
                $u->bind_param('si', $gr, $sid);
                $u->execute();
                $inserted++;
            }
        }
    }
    echo json_encode(['ok' => true, 'inserted' => $inserted]);
    exit;
}

if (($_POST['action'] ?? '') === 'ImportCSV') {
    header('Content-Type: application/json');
    $rows     = json_decode($_POST['rows'] ?? '[]', true);
    $session  = trim($_POST['session'] ?? '');
    $inserted = 0;
    $skipped  = 0;
    if (is_array($rows)) {
        $classCache = [];
        $sectionCache = [];
        $adm = date('Y-m-d');
        foreach ($rows as $r) {
            $name    = trim($r['name'] ?? '');
            $father  = trim($r['father'] ?? '');
            $cell    = trim($r['cell'] ?? '');
            $cn      = trim($r['class'] ?? '');
            $sn      = trim($r['section'] ?? '');
            if ($name === '' || $cn === '') { $skipped++; continue; }
            if (!isset($classCache[$cn])) {
                $st = db_prepare('SELECT class_id FROM classes WHERE class_name = ?');
                $st->bind_param('s', $cn);
                $st->execute();
                $res = $st->get_result()->fetch_assoc();
                $classCache[$cn] = $res ? (int)$res['class_id'] : 0;
            }
            $cid = $classCache[$cn];
            if ($cid === 0) { $skipped++; continue; }
            $sid = 0;
            if ($sn !== '') {
                $key = $cid . '|' . $sn;
                if (!isset($sectionCache[$key])) {
                    $st = db_prepare('SELECT section_id FROM sections WHERE class_id = ? AND section_name = ?');
                    $st->bind_param('is', $cid, $sn);
                    $st->execute();
                    $res = $st->get_result()->fetch_assoc();
                    $sectionCache[$key] = $res ? (int)$res['section_id'] : 0;
                }
                $sid = $sectionCache[$key];
            }
            $gender  = strtolower(trim($r['gender'] ?? '')) === 'female' ? 'female' : 'male';
            $religion = trim($r['religion'] ?? '') !== '' ? $r['religion'] : 'Islam';
            $dob = null;
            if (!empty($r['dob'])) {
                $dt = DateTime::createFromFormat('Y-m-d', trim($r['dob']));
                $dob = $dt ? $dt->format('Y-m-d') : null;
            }
            $newId = 0;
            if ($sid > 0) {
                $stmt = db_prepare('INSERT INTO students (first_name, father_name, phone, class_id, section_id, session, gender, religion, dob, admission_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
                $stmt->bind_param('sssiisssss', $name, $father, $cell, $cid, $sid, $session, $gender, $religion, $dob, $adm);
                $stmt->execute();
                $newId = $stmt->insert_id;
            } else {
                $stmt = db_prepare('INSERT INTO students (first_name, father_name, phone, class_id, session, gender, religion, dob, admission_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
                $stmt->bind_param('sssissdss', $name, $father, $cell, $cid, $session, $gender, $religion, $dob, $adm);
                $stmt->execute();
                $newId = $stmt->insert_id;
            }
            if ($newId > 0) {
                $gr = substr(date('Y'), 2) . '-' . str_pad($newId, 4, '0', STR_PAD_LEFT);
                $u = db_prepare('UPDATE students SET gr_no = ? WHERE student_id = ?');
                $u->bind_param('si', $gr, $newId);
                $u->execute();
                $inserted++;
            }
        }
    }
    echo json_encode(['ok' => true, 'inserted' => $inserted, 'skipped' => $skipped]);
    exit;
}

?>

<?php
$stateMapDef = [
    'Punjab' => ['Lahore','Rawalpindi','Faisalabad','Multan','Gujranwala','Sialkot','Bahawalpur','Sargodha','Sheikhupura','Rahim Yar Khan','Jhang','Kasur','Gujrat','Okara','Sahiwal','Mianwali','Dera Ghazi Khan','Attock','Chakwal','Mandi Bahauddin','Vehari','Muzaffargarh','Khanewal','Wazirabad','Hafizabad','Narowal','Burewala','Toba Tek Singh'],
    'Sindh' => ['Karachi','Hyderabad','Sukkur','Larkana','Nawabshah','Mirpur Khas','Badin','Shikarpur','Dadu','Thatta','Jacobabad','Ghorki'],
    'Balochistan' => ['Quetta','Khuzdar','Turbat','Gwadar','Chaman','Sibi','Zhob','Noshki'],
    'KPK' => ['Peshawar','Mardan','Swat','Abbottabad','Kohat','Bannu','Charsadda','Dera Ismail Khan','Nowshera','Mansehra','Haripur','Swabi'],
    'Gilgit-Baltistan' => ['Gilgit','Skardu','Hunza','Nagar','Ghizer','Astore'],
    'Kashmir (territory)' => ['Muzaffarabad','Mirpur','Rawalakot','Kotli','Bhimber'],
    'FATA (territory)' => ['Parachinar','Miranshah','Wana','Kurram'],
    'Federal' => ['Islamabad']
];
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>
.right_col { padding: 0; }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.fieldset-box {
    position: relative;
    border: 1px solid #e7e7e7;
    border-radius: 10px;
    padding: 14px 12px 8px 12px;
    background: #fefefe;
    height: 100%;
    transition: border-color .15s ease, box-shadow .15s ease;
    min-height: 46px;
}
.fieldset-box:focus-within {
    border-color: #FF6B2C;
    box-shadow: 0 0 0 3px rgba(255,107,44,.12);
}
.fieldset-label {
    position: absolute;
    left: 14px;
    top: -9px;
    background: #fff;
    padding: 0 6px;
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    letter-spacing: .02em;
    pointer-events: none;
}
.fieldset-label.required::after { content: ' *'; color: #ef4444; }
.fieldset-input,
.fieldset-select {
    width: 100%;
    border: none;
    outline: none;
    background: transparent;
    font-size: 13px;
    color: #1f2430;
    padding: 0 0 1px;
}
.fieldset-input::placeholder { color: #9ca3af; font-weight: 400; }
.fieldset-area {
    resize: vertical;
    min-height: 32px;
}

*::-webkit-scrollbar { width: 7px; height: 7px; }
*::-webkit-scrollbar-track { background: transparent; }
*::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 20px; }
*::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

.toast-item { animation: slideIn .25s ease; box-shadow: 0 8px 24px rgba(0,0,0,.18); }
@keyframes slideIn { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: none; } }
.toast-item.remove { animation: slideOut .25s ease forwards; }
@keyframes slideOut { from { opacity: 1; transform: none; } to { opacity: 0; transform: translateX(30px); } }

input[type=range] { accent-color: #FF6B2C; }

.tab-btn { transition: all .15s ease; }
.tab-btn.active {
    background: #fff7ed;
    color: #FF6B2C;
    border-color: #FF6B2C;
    font-weight: 600;
}
.subtab-btn { transition: all .15s ease; }
.subtab-btn.active {
    background: #fff7ed;
    color: #FF6B2C;
    border-color: #FF6B2C;
    font-weight: 600;
}
.photo-controls:disabled { opacity: .4; pointer-events: none; }
</style>
<main id="main-content" class="p-4 sm:p-5">

        <?php if ($error !== ''): ?>
        <div class="mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-[13px]">
            <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <!-- Breadcrumb -->
        <div class="flex items-center gap-1.5 text-[12px] text-slate-500 mb-4">
            <a href="<?php echo BASE_URL; ?>dashboard.php" class="hover:text-brand-orange transition">Dashboard</a>
            <i class="fa-solid fa-chevron-right text-[9px] text-slate-300"></i>
            <a href="<?php echo BASE_URL; ?>manage_students.php" class="hover:text-brand-orange transition">Students</a>
            <i class="fa-solid fa-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-700 font-medium">Add New Student</span>
        </div>

        <!-- Top Sub Tabs -->
        <div class="flex items-center gap-1 bg-white border border-brand-border rounded-xl p-1.5 mb-5 overflow-x-auto">
            <button id="tab-btn-single" onclick="switchMainView('single')" class="tab-btn active flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-transparent whitespace-nowrap shrink-0">
                <i class="fa fa-user-plus"></i> Add New Student
            </button>
            <a id="tab-btn-multi" href="<?php echo BASE_URL; ?>bulk_stdns.php" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-transparent whitespace-nowrap shrink-0 no-underline">
                <i class="fa fa-users"></i> Add Multi Students
            </a>
            <a id="tab-btn-import" href="<?php echo BASE_URL; ?>import_data.php" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-transparent whitespace-nowrap shrink-0 no-underline">
                <i class="fa fa-upload"></i> Import Students with CSV
            </a>
            <a id="tab-btn-form" href="<?php echo BASE_URL; ?>adm_form.php" target="_blank" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-transparent whitespace-nowrap shrink-0 no-underline">
                <i class="fa fa-file-alt"></i> Admission Form
            </a>
        </div>

        <!-- ===================== VIEW: SINGLE STUDENT ===================== -->
        <div id="view-single-student">

            <!-- Step Tracker -->
            <div class="bg-white border border-brand-border rounded-xl p-4 mb-5">
                <div class="flex items-center gap-3 sm:gap-4 flex-wrap">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-brand-orange text-white text-[13px] font-bold flex items-center justify-center shadow">1</div>
                        <div>
                            <p class="text-[13px] font-semibold text-slate-700">Student Info</p>
                            <p class="text-[11px] text-slate-400">Name, class &amp; contact details</p>
                        </div>
                    </div>
                    <div class="hidden md:block flex-1 max-w-[120px] border-t-2 border-dashed border-slate-200"></div>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-slate-200 text-slate-500 text-[13px] font-bold flex items-center justify-center">2</div>
                        <div>
                            <p class="text-[13px] font-semibold text-slate-700">Fee &amp; Parent Info</p>
                            <p class="text-[11px] text-slate-400">Guardians &amp; fee plan setup</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inner Tabs -->
            <div class="flex items-center gap-1 mb-4 overflow-x-auto">
                <button id="subtab-basic" onclick="switchFormTab('basic')" class="subtab-btn active flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-brand-border bg-white whitespace-nowrap shrink-0">
                    <i class="fa-solid fa-id-card"></i> Basic Information
                </button>
                <button id="subtab-parent" onclick="switchFormTab('parent')" class="subtab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-brand-border bg-white whitespace-nowrap shrink-0">
                    <i class="fa-solid fa-people-roof"></i> Parent Details
                </button>
                <button id="subtab-academic" onclick="switchFormTab('academic')" class="subtab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-brand-border bg-white whitespace-nowrap shrink-0">
                    <i class="fa-solid fa-graduation-cap"></i> Academic Information
                </button>
                <button id="subtab-contact" onclick="switchFormTab('contact')" class="subtab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-brand-border bg-white whitespace-nowrap shrink-0">
                    <i class="fa-solid fa-address-book"></i> Contact Information
                </button>
                <button id="subtab-documents" onclick="switchFormTab('documents')" class="subtab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-brand-border bg-white whitespace-nowrap shrink-0">
                    <i class="fa-solid fa-paperclip"></i> Documents
                </button>
            </div>

            <!-- Form -->
            <form id="single-student-form" action="<?php echo BASE_URL; ?>add_student.php" method="post" enctype="multipart/form-data" onsubmit="handleSaveStudent(event)" autocomplete="off">
                <input type="hidden" name="action" value="AddAdmission">
                <input type="hidden" name="family_code" id="family_code_value" value="">
                <input type="hidden" name="captured_image" id="captured_image" value="">
                <input type="hidden" name="redirect_mode" id="redirect_mode" value="">
                <input type="hidden" name="old_file" id="old_file" value="">

                <div class="grid grid-cols-12 gap-5">

                    <div class="col-span-12 lg:col-span-9 space-y-6">

                        <!-- BASIC TAB -->
                        <div id="form-basic">
                            <div class="bg-white border border-brand-border rounded-xl p-5 space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                                    <div class="fieldset-box">
                                        <label class="fieldset-label required">Student Name</label>
                                        <input type="text" class="fieldset-input" name="first_name" id="first_name" placeholder="Enter Full Name" required>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label required">Father Name</label>
                                        <input type="text" class="fieldset-input" name="lname" id="last_name" placeholder="Enter Father Name" required>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Cell / Mobile Number</label>
                                        <input type="text" class="fieldset-input" name="cellno" id="cell_no" placeholder="03XX-XXXXXXX" inputmode="tel" required>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Guardian / Family</label>
                                        <select name="family_search" id="family_search" class="fieldset-select" onChange="getFamilyInfo(this.value);">
                                            <option value="">Select Family</option>
                                            <?php foreach ($families as $fam): ?>
                                            <option value="<?php echo e($fam['family_code']); ?>"><?php echo e($fam['family_code'] . ' - ' . $fam['last_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label required">Session</label>
                                        <select name="session" id="session" class="fieldset-select" required>
                                            <option value="">Select Session</option>
                                            <?php foreach ($sessions as $s): ?>
                                            <option value="<?php echo e($s); ?>" <?php echo ($s === $cur_session) ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label required">GR-No</label>
                                        <input type="text" class="fieldset-input" name="com_no" id="com_no" value="<?php echo e($nextGr); ?>" readonly>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label required">Class</label>
                                        <select name="class" required id="class" class="fieldset-select" onChange="getSection(this.value);">
                                            <option value="" disabled selected>Select Class</option>
                                            <?php foreach ($classes as $cl): ?>
                                            <option value="<?php echo $cl['class_id']; ?>"><?php echo e($cl['class_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label required">Section</label>
                                        <select name="section" required id="txt_section" class="fieldset-select">
                                            <option value="" disabled selected>Select Section</option>
                                        </select>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Board / Council</label>
                                        <div class="flex items-center gap-1.5">
                                            <select name="board_council" id="board_council" class="fieldset-select flex-1">
                                                <option value="">Select Board</option>
                                                <?php foreach ($boards as $b): ?>
                                                <option value="<?php echo $b['id']; ?>"><?php echo e($b['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <a href="<?php echo BASE_URL; ?>manage_board.php" target="_blank" class="btn-add-new shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold text-brand-orange hover:bg-orange-50 no-underline" title="Add New Board"><i class="fa fa-plus text-[10px]"></i> Add New</a>
                                        </div>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Group / Shift</label>
                                        <div class="flex items-center gap-1.5">
                                            <select name="group_shift" id="group_shift" class="fieldset-select flex-1">
                                                <option value="">Select Group</option>
                                                <?php foreach ($groups as $g): ?>
                                                <option value="<?php echo $g['id']; ?>"><?php echo e($g['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <a href="<?php echo BASE_URL; ?>manage_group.php" target="_blank" class="btn-add-new shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold text-brand-orange hover:bg-orange-50 no-underline" title="Add New Group"><i class="fa fa-plus text-[10px]"></i> Add New</a>
                                        </div>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Admission Source</label>
                                        <div class="flex items-center gap-1.5">
                                            <select name="adm_source" id="adm_source" class="fieldset-select flex-1">
                                                <option value="">Select Source</option>
                                                <?php foreach ($admSrcs as $a): ?>
                                                <option value="<?php echo $a['id']; ?>"><?php echo e($a['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <a href="<?php echo BASE_URL; ?>manage_admission_sources.php" target="_blank" class="btn-add-new shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold text-brand-orange hover:bg-orange-50 no-underline" title="Add New Source"><i class="fa fa-plus text-[10px]"></i> Add New</a>
                                        </div>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Date of Birth</label>
                                        <input type="text" class="fieldset-input" name="dob" id="dob" placeholder="dd/mm/yyyy" value="<?php echo date('d/m/Y'); ?>" autocomplete="off">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Date of Admission</label>
                                        <input type="text" class="fieldset-input" name="date_of_adms" id="date_of_adms" placeholder="dd/mm/yyyy" value="<?php echo date('d/m/Y'); ?>" autocomplete="off">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label required">Gender</label>
                                        <select name="gender" id="gender" class="fieldset-select" required>
                                            <option value="male" selected>Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label required">Religion</label>
                                        <select name="religion" id="religion" class="fieldset-select" required>
                                            <option value="Muslim" selected>Muslim</option>
                                            <option value="Non-Muslim">Non-Muslim</option>
                                        </select>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Locality</label>
                                        <div class="flex items-center gap-1.5">
                                            <select name="Locality" id="locality" class="fieldset-select flex-1">
                                                <option value="">Select Locality</option>
                                                <?php foreach ($localities as $loc): ?>
                                                <option value="<?php echo $loc['locality_id']; ?>"><?php echo e($loc['locality_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <a href="<?php echo BASE_URL; ?>manage_localities.php" target="_blank" class="btn-add-new shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold text-brand-orange hover:bg-orange-50 no-underline" title="Add New Locality"><i class="fa fa-plus text-[10px]"></i> Add New</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PARENT TAB -->
                        <div id="form-parent" class="hidden space-y-5">
                            <div class="bg-white border border-brand-border rounded-xl p-5">
                                <h3 class="text-[13px] font-bold text-slate-700 mb-4 flex items-center gap-2">
                                    <i class="fa-solid fa-user-tie text-brand-orange"></i> Father &amp; Mother Information
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Father CNIC</label>
                                        <input type="text" class="fieldset-input" name="cnic" id="cnic" placeholder="00000-0000000-0" maxlength="13" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,13); document.getElementById('fcnic-limit-msg').style.display=(this.value.length>0 && this.value.length<13)?'block':'none';">
                                        <small id="fcnic-limit-msg" class="text-red-500 text-[10px] hidden">Must be exactly 13 digits.</small>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Father Qualification</label>
                                        <input type="text" class="fieldset-input" name="Fqualification" id="father_qualification" placeholder="e.g. Master">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Father Occupation</label>
                                        <div class="flex items-center gap-1.5">
                                            <select name="father_occupation" id="father_occupation" class="fieldset-select flex-1">
                                                <option value="">Select Occupation</option>
                                                <?php foreach ($occupations as $o): ?>
                                                <option value="<?php echo $o['id']; ?>"><?php echo e($o['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <a href="<?php echo BASE_URL; ?>manage_occupations.php" target="_blank" class="btn-add-new shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold text-brand-orange hover:bg-orange-50 no-underline" title="Add New Occupation"><i class="fa fa-plus text-[10px]"></i> Add New</a>
                                        </div>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Father Business Address</label>
                                        <textarea class="fieldset-input fieldset-area" name="Fbusiness_address" id="Fbusiness_address" placeholder="Business address"></textarea>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Father Income</label>
                                        <input type="text" class="fieldset-input" name="Fincome" id="father_income" placeholder="e.g. 60000" inputmode="numeric">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Mother Name</label>
                                        <input type="text" class="fieldset-input" name="mother_name" id="mother_name" placeholder="Enter Mother Name">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Mother CNIC</label>
                                        <input type="text" class="fieldset-input" name="mother_cnic" id="mother_cnic" placeholder="00000-0000000-0" maxlength="13" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,13); document.getElementById('mcnic-limit-msg').style.display=(this.value.length>0 && this.value.length<13)?'block':'none';">
                                        <small id="mcnic-limit-msg" class="text-red-500 text-[10px] hidden">Must be exactly 13 digits.</small>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Mother Qualification</label>
                                        <input type="text" class="fieldset-input" name="mother_qualification" id="mother_qualification" placeholder="e.g. Intermediate">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Mother Activity</label>
                                        <select name="mother_activity" id="mother_activity" class="fieldset-select">
                                            <option value="">Select Activity</option>
                                            <option value="House Lady">House Lady</option>
                                            <option value="Job Holder">Job Holder</option>
                                        </select>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Mother Designation</label>
                                        <input type="text" class="fieldset-input" name="mother_designation" id="mother_designation" placeholder="Designation">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">B-Form No</label>
                                        <input type="text" class="fieldset-input" name="formBNo" id="formBNo" placeholder="B-Form number">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Cast</label>
                                        <input type="text" class="fieldset-input" name="cast" id="cast" placeholder="Caste">
                                    </div>
                                    <div class="fieldset-box col-span-2">
                                        <label class="fieldset-label">Home Address</label>
                                        <textarea class="fieldset-input fieldset-area" name="address" id="address" placeholder="Complete residential address"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white border border-brand-border rounded-xl p-5">
                                <h3 class="text-[13px] font-bold text-slate-700 mb-4 flex items-center gap-2">
                                    <i class="fa-solid fa-user-shield text-brand-orange"></i> Guardian Information
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Guardian Name</label>
                                        <input type="text" class="fieldset-input" name="gname" id="gardian_name" placeholder="Full name">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Guardian CNIC</label>
                                        <input type="text" class="fieldset-input" name="Gcnic" id="gardian_cnic" placeholder="00000-0000000-0" maxlength="13" inputmode="numeric">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Guardian Cell No</label>
                                        <input type="text" class="fieldset-input" name="Gcellno" id="gardian_no" placeholder="03XX-XXXXXXX" inputmode="tel">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Guardian Qualification</label>
                                        <input type="text" class="fieldset-input" name="Gqualification" id="gardian_qualification" placeholder="Qualification">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Guardian Occupation</label>
                                        <input type="text" class="fieldset-input" name="Goccupation" id="gardian_occupation" placeholder="Occupation">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Guardian Income</label>
                                        <input type="text" class="fieldset-input" name="Gincome" id="gardian_income" placeholder="e.g. 40000" inputmode="numeric">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Guardian Email</label>
                                        <input type="email" class="fieldset-input" name="gardian_email" id="gardian_email" placeholder="guardian@email.com">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Guardian Address</label>
                                        <textarea class="fieldset-input fieldset-area" name="Gaddress" id="gardian_address" placeholder="Guardian address"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ACADEMIC TAB -->
                        <div id="form-academic" class="hidden">
                            <div class="bg-white border border-brand-border rounded-xl p-5">
                                <h3 class="text-[13px] font-bold text-slate-700 mb-4 flex items-center gap-2">
                                    <i class="fa-solid fa-school-circle-check text-brand-orange"></i> Previous Education
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Previous Class</label>
                                        <input type="text" class="fieldset-input" name="old_class" id="old_class" placeholder="Last attended class">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Previous Institute</label>
                                        <input type="text" class="fieldset-input" name="old_school" id="old_school" placeholder="School / College name">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Total Marks</label>
                                        <input type="text" class="fieldset-input" name="old_tmarks" id="old_tmarks" placeholder="e.g. 1100" inputmode="numeric">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Obtained Marks</label>
                                        <input type="text" class="fieldset-input" name="old_obtmarks" id="old_obtmarks" placeholder="e.g. 900" inputmode="numeric">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Admission Form No</label>
                                        <input type="text" class="fieldset-input" name="form_no" id="adm-no" placeholder="Admission form number">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Reason of Leaving</label>
                                        <input type="text" class="fieldset-input" name="school_leaving" id="school_leaving" placeholder="Optional">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CONTACT TAB -->
                        <div id="form-contact" class="hidden">
                            <div class="bg-white border border-brand-border rounded-xl p-5">
                                <h3 class="text-[13px] font-bold text-slate-700 mb-4 flex items-center gap-2">
                                    <i class="fa-solid fa-location-dot text-brand-orange"></i> Address &amp; Contact Information
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Whatsapp No</label>
                                        <input type="text" class="fieldset-input" name="whatsapp_number" id="whatsapp_number" placeholder="03XX-XXXXXXX" inputmode="tel">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Father Cell No</label>
                                        <input type="text" class="fieldset-input" name="father_cellno" id="father_cellno" placeholder="03XX-XXXXXXX" inputmode="tel">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Mother Cell No</label>
                                        <input type="text" class="fieldset-input" name="mother_cell" id="mother_cell" placeholder="03XX-XXXXXXX" inputmode="tel">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Home Cell No</label>
                                        <input type="text" class="fieldset-input" name="home_number" id="home_number" placeholder="Landline (optional)">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Place of Birth</label>
                                        <input type="text" class="fieldset-input" name="place_of_birth" id="place_of_birth" placeholder="City of birth">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">State / Province</label>
                                        <select name="state" id="state" class="fieldset-select" onChange="getCity(this.value);">
                                            <option value="" disabled selected>Select State</option>
                                            <?php foreach (array_keys($stateMapDef) as $st): ?>
                                            <option value="<?php echo e($st); ?>"><?php echo e($st); ?></option>
                                            <?php endforeach; ?>
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
                                        <input type="email" class="fieldset-input" name="email" id="email" placeholder="student@email.com">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- DOCUMENTS TAB -->
                        <div id="form-documents" class="hidden">
                            <div class="bg-white border border-brand-border rounded-xl p-5">
                                <div class="flex items-start justify-between flex-wrap gap-3 mb-4">
                                    <div>
                                        <h3 class="text-[13px] font-bold text-slate-700 mb-1 flex items-center gap-2">
                                            <i class="fa-solid fa-paperclip text-brand-orange"></i> Student Documents
                                        </h3>
                                        <p class="text-[11px] text-slate-400"><i class="fa fa-info-circle mr-1"></i>Upload the student's documents below. Accepted formats: JPG, JPEG, PNG, PDF.</p>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>add_student_documents.php" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-brand-border bg-orange-50 hover:bg-orange-100 text-brand-orange text-[11px] font-semibold transition no-underline">
                                        <i class="fa fa-plus-circle"></i> Manage Document Titles
                                    </a>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <?php foreach ($docTitles as $di => $doc): ?>
                                    <div id="doc-card-<?php echo $doc['id']; ?>" class="border border-dashed border-brand-border rounded-xl p-3 text-center">
                                        <div class="w-10 h-10 mx-auto rounded-lg bg-orange-50 text-brand-orange flex items-center justify-center mb-2">
                                            <i class="fa-regular fa-file-lines text-[16px]"></i>
                                        </div>
                                        <label class="text-[12px] font-semibold text-slate-600 block mb-1.5 leading-tight"><?php echo e($doc['name']); ?></label>
                                        <span id="docStatus_<?php echo $di; ?>" class="doc-card-status inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-400 mb-2">Not Uploaded</span>
                                        <div class="mb-2">
                                            <label for="docFile_<?php echo $di; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-blueBtn hover:bg-blue-600 text-white text-[11px] font-semibold transition cursor-pointer">
                                                <i class="fa fa-upload"></i> Choose File
                                            </label>
                                        </div>
                                        <input type="hidden" name="doc_types[]" value="<?php echo e($doc['name']); ?>">
                                        <input type="file" id="docFile_<?php echo $di; ?>" name="doc_files[]" accept=".jpg,.jpeg,.png,.pdf" class="hidden" onchange="previewStudentDoc(this, <?php echo $di; ?>)">
                                        <div class="doc-card-filename" id="docFileName_<?php echo $di; ?>"></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center gap-3">
                            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-brand-orange hover:bg-brand-orangeHover text-white text-[13px] font-semibold shadow-sm transition">
                                <i class="fa-solid fa-floppy-disk"></i> Save Student
                            </button>
                            <button type="button" onclick="resetSingleForm()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-[13px] font-medium transition">
                                <i class="fa-solid fa-rotate-left"></i> Cancel
                            </button>
                        </div>

                    </div>

                    <!-- Photo Widget -->
                    <div class="col-span-12 lg:col-span-3">
                        <div class="bg-white border border-brand-border rounded-xl p-5">
                            <h3 class="text-[13px] font-bold text-slate-700 mb-4 flex items-center gap-2">
                                <i class="fa-solid fa-camera text-brand-orange"></i> Student Photo
                            </h3>

                            <div id="photo-placeholder" onclick="document.getElementById('photo-input').click()" class="w-44 h-48 mx-auto rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 flex flex-col items-center justify-center text-center cursor-pointer hover:border-brand-orange transition">
                                <i class="fa-solid fa-camera text-3xl text-slate-400 mb-2"></i>
                                <p class="text-xs text-slate-500 font-medium">Add Photo</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">JPG / PNG</p>
                            </div>

                            <div id="photo-frame" class="hidden relative w-44 h-48 mx-auto rounded-xl overflow-hidden border border-slate-200 bg-white">
                                <img id="photo-preview" class="w-full h-full object-cover absolute inset-0 select-none" style="transform-origin:center; cursor:grab;" onmousedown="posPhotoDrag(event)">
                                <button type="button" id="photo-delete-btn" onclick="deletePhotoFrame(event)" class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-red-500 text-white text-[11px] flex items-center justify-center shadow hover:bg-red-600 transition z-10"><i class="fa-solid fa-xmark"></i></button>
                            </div>

                            <input type="file" id="photo-input" name="img_file" accept="image/*" class="hidden" onchange="handlePhotoUpload(event)">
                            <canvas id="photo-canvas" class="hidden"></canvas>

                            <div id="photo-controls" class="mt-4 space-y-4 photo-controls" disabled>
                                <div class="flex items-center gap-3">
                                    <i class="fa-solid fa-magnifying-glass-plus text-slate-400 text-[13px] w-4"></i>
                                    <input type="range" id="zoom-slider" min="0.5" max="2.5" step="0.05" value="1" oninput="updatePhotoTransform()" class="flex-1">
                                    <span id="zoom-label" class="text-[10px] text-slate-400 w-9 text-right">1.0x</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <i class="fa-solid fa-rotate-right text-slate-400 text-[13px] w-4"></i>
                                    <input type="range" id="rotate-slider" min="-180" max="180" step="5" value="0" oninput="updatePhotoTransform()" class="flex-1">
                                    <span id="rotate-label" class="text-[10px] text-slate-400 w-9 text-right">0&deg;</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>

        <!-- ===================== VIEW: MULTI STUDENT ===================== -->
        <div id="view-multi-student" class="hidden">
            <div class="bg-white border border-brand-border rounded-xl overflow-hidden">
                <div class="flex items-center justify-between flex-wrap gap-3 px-4 py-3 border-b border-brand-border">
                    <div>
                        <h3 class="text-[14px] font-bold text-slate-700"><i class="fa-solid fa-users text-brand-orange mr-2"></i>Add Multi Students</h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Quickly add several students in one go</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="addMultiRow()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand-blueBtn hover:bg-blue-600 text-white text-[12px] font-semibold transition"><i class="fa-solid fa-plus"></i> Add Row</button>
                        <button onclick="saveMultiStudents()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand-greenBtn hover:bg-green-600 text-white text-[12px] font-semibold transition"><i class="fa-solid fa-database"></i> Save All</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[13px]">
                        <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold w-14">#</th>
                                <th class="px-4 py-2.5 font-semibold min-w-[180px]">Student Name</th>
                                <th class="px-4 py-2.5 font-semibold min-w-[180px]">Father Name</th>
                                <th class="px-4 py-2.5 font-semibold min-w-[150px]">Cell / Mobile</th>
                                <th class="px-4 py-2.5 font-semibold min-w-[140px]">Class</th>
                                <th class="px-4 py-2.5 font-semibold w-16"></th>
                            </tr>
                        </thead>
                        <tbody id="multi-student-tbody" class="divide-y divide-brand-border">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===================== VIEW: IMPORT CSV ===================== -->
        <div id="view-import-student" class="hidden">
            <div class="bg-white border border-brand-border rounded-xl overflow-hidden">
                <div class="flex items-center justify-between flex-wrap gap-3 px-4 py-3 border-b border-brand-border">
                    <div>
                        <h3 class="text-[14px] font-bold text-slate-700"><i class="fa-solid fa-file-csv text-brand-orange mr-2"></i>Import Students / CSV</h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Upload a CSV file to bulk import students</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="downloadSampleCSV()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-[12px] font-semibold transition"><i class="fa-solid fa-download"></i> Sample CSV File</button>
                        <button onclick="document.getElementById('import-csv-input').click()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand-blueBtn hover:bg-blue-600 text-white text-[12px] font-semibold transition"><i class="fa-solid fa-upload"></i> Choose File</button>
                        <input type="file" id="import-csv-input" accept=".csv" class="hidden" onchange="triggerCSVImport(this)">
                    </div>
                </div>

                <div class="flex items-center gap-3 px-4 py-2.5 border-b border-brand-border bg-slate-50/50">
                    <input type="text" id="csv-search" placeholder="Search imported records..." oninput="filterCSVTable()" class="flex-1 h-9 px-3 rounded-lg bg-white border border-brand-border text-[12px] text-slate-600 outline-none focus:ring-2 focus:ring-brand-orange/40 placeholder:text-slate-400">
                    <span id="csv-count" class="text-[11px] text-slate-400 whitespace-nowrap">0 records</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[13px]">
                        <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold w-10"><i class="fa-solid fa-check text-slate-300"></i></th>
                                <th class="px-4 py-2.5 font-semibold w-14">S.No</th>
                                <th class="px-4 py-2.5 font-semibold min-w-[170px]">Student Name</th>
                                <th class="px-4 py-2.5 font-semibold min-w-[170px]">Father Name</th>
                                <th class="px-4 py-2.5 font-semibold min-w-[130px]">Cell / Mobile</th>
                                <th class="px-4 py-2.5 font-semibold min-w-[110px]">Class</th>
                                <th class="px-4 py-2.5 font-semibold w-16"></th>
                            </tr>
                        </thead>
                        <tbody id="csv-table-body" class="divide-y divide-brand-border">
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between flex-wrap gap-3 px-4 py-3 border-t border-brand-border bg-slate-50/50">
                    <p class="text-[11px] text-slate-400"><i class="fa-solid fa-circle-info text-brand-blueBtn mr-1"></i> Columns: Student Name, Father Name, Cell / Mobile, Class</p>
                    <div class="flex items-center gap-2">
                        <button onclick="clearImportedData()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 text-[12px] font-semibold transition"><i class="fa-solid fa-trash-can"></i> Clear All</button>
                        <button onclick="saveImportedData()" class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-brand-greenBtn hover:bg-green-600 text-white text-[12px] font-semibold transition"><i class="fa-solid fa-database"></i> Import Selected</button>
                    </div>
                </div>
            </div>
        </div>

    </main>

<!-- ===================== TOAST ===================== -->
<div id="toast-container" class="fixed top-4 right-4 z-[100] space-y-2 w-[320px] max-w-[90vw]"></div>

<script>
var HIIFI_BASE = '<?php echo BASE_URL; ?>';


function switchMainView(view) {
    if (view === 'form') { window.location = HIIFI_BASE + 'adm_form.php'; return; }
    var views = { 'single': 'view-single-student', 'multi': 'view-multi-student', 'import': 'view-import-student' };
    Object.keys(views).forEach(function (k) {
        document.getElementById(views[k]).classList.toggle('hidden', k !== view);
    });
    document.querySelectorAll('#tab-btn-single, #tab-btn-multi, #tab-btn-import').forEach(function (b) { b.classList.remove('active'); });
    var btn = document.getElementById('tab-btn-' + view);
    if (btn) btn.classList.add('active');
    if (view === 'multi' && multiIndex <= 1) addMultiRow();
}

function switchFormTab(tab) {
    var tabs = { 'basic': 'form-basic', 'parent': 'form-parent', 'academic': 'form-academic', 'contact': 'form-contact', 'documents': 'form-documents' };
    Object.keys(tabs).forEach(function (k) {
        document.getElementById(tabs[k]).classList.toggle('hidden', k !== tab);
    });
    document.querySelectorAll('#subtab-basic, #subtab-parent, #subtab-academic, #subtab-contact, #subtab-documents').forEach(function (b) { b.classList.remove('active'); });
    var btn = document.getElementById('subtab-' + tab);
    if (btn) btn.classList.add('active');
}

function resetSingleForm() {
    document.getElementById('single-student-form').reset();
    document.getElementById('family_code_value').value = '';
    document.getElementById('com_no').value = '<?php echo e($nextGr); ?>';
    document.getElementById('txt_section').innerHTML = '<option value="" disabled selected>Select Section</option>';
    resetPhotoFrame();
    switchFormTab('basic');
    showToast('Form cleared', 'info');
}

function showToast(msg, type) {
    var types = { success: 'bg-emerald-600', warning: 'bg-amber-500', info: 'bg-sky-600', error: 'bg-red-600' };
    var icons = { success: 'fa-circle-check', warning: 'fa-triangle-exclamation', info: 'fa-circle-info', error: 'fa-circle-exclamation' };
    var el = document.createElement('div');
    el.className = 'toast-item ' + (types[type] || types.info) + ' text-white text-sm px-4 py-3 rounded-xl flex items-start gap-2';
    el.innerHTML = '<i class="fa-solid ' + (icons[type] || icons.info) + ' mt-0.5"></i><span class="flex-1"></span>';
    el.querySelector('span').textContent = msg;
    document.getElementById('toast-container').appendChild(el);
    setTimeout(function () { el.classList.add('remove'); setTimeout(function () { el.remove(); }, 260); }, 3200);
}

/* ---------------- Photo ---------------- */
function handlePhotoUpload(e) {
    var input = e.target;
    var file = input.files && input.files[0];
    if (!file) return;
    if (!/^image\//.test(file.type)) { showToast('Please select an image file', 'warning'); input.value = ''; return; }
    var reader = new FileReader();
    reader.onload = function (ev) {
        var img = document.getElementById('photo-preview');
        img.src = ev.target.result;
        img.style.transform = 'scale(1) rotate(0deg)';
        img.style.left = '0px';
        img.style.top = '0px';
        document.getElementById('zoom-slider').value = 1;
        document.getElementById('rotate-slider').value = 0;
        document.getElementById('zoom-label').textContent = '1.0x';
        document.getElementById('rotate-label').textContent = '0\u00B0';
        document.getElementById('photo-placeholder').classList.add('hidden');
        document.getElementById('photo-frame').classList.remove('hidden');
        document.getElementById('photo-controls').removeAttribute('disabled');
    };
    reader.readAsDataURL(file);
}

/* drag-to-position the photo inside the frame */
var _photoDrag = null;
function posPhotoDrag(e) {
    e.preventDefault();
    var img = document.getElementById('photo-preview');
    _photoDrag = {
        startX: e.clientX,
        startY: e.clientY,
        orgLeft: parseInt(img.style.left) || 0,
        orgTop: parseInt(img.style.top) || 0,
        img: img
    };
    onPosPhotoMove(e);
}
function onPosPhotoMove(e) {
    if (!_photoDrag) return;
    var dx = e.clientX - _photoDrag.startX;
    var dy = e.clientY - _photoDrag.startY;
    var img = _photoDrag.img;
    var frame = img.parentElement;
    var fw = frame.offsetWidth;
    var fh = frame.offsetHeight;
    var sw = (img.naturalWidth || fw) / fw;
    var sh = (img.naturalHeight || fh) / fh;
    var maxX = Math.round(fw * (sw - 1) / 2);
    var maxY = Math.round(fh * (sh - 1) / 2);
    var nx = _photoDrag.orgLeft + dx;
    var ny = _photoDrag.orgTop + dy;
    if (nx > maxX) nx = maxX;
    if (nx < -maxX) nx = -maxX;
    if (ny > maxY) ny = maxY;
    if (ny < -maxY) ny = -maxY;
    img.style.left = nx + 'px';
    img.style.top = ny + 'px';
}
function endPhotoDrag() { _photoDrag = null; }

function updatePhotoTransform() {
    var img = document.getElementById('photo-preview');
    var zoom = parseFloat(document.getElementById('zoom-slider').value);
    var rot = parseInt(document.getElementById('rotate-slider').value);
    img.style.transform = 'scale(' + zoom + ') rotate(' + rot + 'deg)';
    document.getElementById('zoom-label').textContent = zoom.toFixed(2) + 'x';
    document.getElementById('rotate-label').textContent = rot + '\u00B0';
}

function deletePhotoFrame(e) {
    if (e) e.stopPropagation();
    resetPhotoFrame();
}

function resetPhotoFrame() {
    var input = document.getElementById('photo-input');
    input.value = '';
    document.getElementById('captured_image').value = '';
    document.getElementById('photo-placeholder').classList.remove('hidden');
    document.getElementById('photo-frame').classList.add('hidden');
    document.getElementById('photo-controls').setAttribute('disabled', 'disabled');
}

function handleSaveStudent(e) {
    e.preventDefault();
    var form = document.getElementById('single-student-form');
    var fcnic = document.getElementById('cnic');
    var mcnic = document.getElementById('mother_cnic');
    if (fcnic && fcnic.value && fcnic.value.length < 13) {
        switchFormTab('parent');
        document.getElementById('fcnic-limit-msg').classList.remove('hidden');
        fcnic.focus();
        showToast('Father CNIC must be exactly 13 digits', 'warning');
        return;
    }
    if (mcnic && mcnic.value && mcnic.value.length < 13) {
        switchFormTab('parent');
        document.getElementById('mcnic-limit-msg').classList.remove('hidden');
        mcnic.focus();
        showToast('Mother CNIC must be exactly 13 digits', 'warning');
        return;
    }
    if (!form.checkValidity()) { form.reportValidity(); return; }
    if (window._studentSaveProcessing) return;

    var frame = document.getElementById('photo-frame');
    var fileInput = document.getElementById('photo-input');
    var zoomSlider = document.getElementById('zoom-slider');
    var rotateSlider = document.getElementById('rotate-slider');
    var frameVisible = frame && !frame.classList.contains('hidden');
    var hasNewFile = fileInput.files && fileInput.files[0];
    var photoPreview = document.getElementById('photo-preview');
    var imgLeft = photoPreview ? (parseFloat(photoPreview.style.left) || 0) : 0;
    var imgTop = photoPreview ? (parseFloat(photoPreview.style.top) || 0) : 0;
    var hasTransformation = zoomSlider && rotateSlider &&
        (parseFloat(zoomSlider.value) !== 1 || parseInt(rotateSlider.value) !== 0 || imgLeft !== 0 || imgTop !== 0);
    if (!frameVisible || (!hasNewFile && !hasTransformation)) { form.submit(); return; }

    window._studentSaveProcessing = true;
    processImageTransformation();
}

function processImageTransformation() {
    var canvas = document.getElementById('photo-canvas');
    var form = document.getElementById('single-student-form');
    var image = document.getElementById('photo-preview');
    var fileInput = document.getElementById('photo-input');
    var zoomSlider = document.getElementById('zoom-slider');
    var rotateSlider = document.getElementById('rotate-slider');
    if (!image.complete || !image.naturalWidth) {
        showToast('Image not loaded properly. Please try again.', 'warning');
        window._studentSaveProcessing = false;
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
        if (!blob) { showToast('Failed to process image', 'error'); window._studentSaveProcessing = false; return; }
        var transformedFile = new File([blob], 'transformed_student_image.jpg', { type: 'image/jpeg', lastModified: Date.now() });
        try {
            var dataTransfer = new DataTransfer();
            dataTransfer.items.add(transformedFile);
            fileInput.files = dataTransfer.files;
            form.submit();
        } catch (err) {
            console.error(err);
            showToast('Failed to process image. Please try again.', 'error');
            window._studentSaveProcessing = false;
        }
    }, 'image/jpeg', 0.98);
}

function previewStudentDoc(input, idx) {
    var file = input.files && input.files[0];
    var status = document.getElementById('docStatus_' + idx);
    var nameEl = document.getElementById('docFileName_' + idx);
    if (!file) {
        if (status) { status.textContent = 'Not Uploaded'; status.className = 'doc-card-status inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-400 mb-2'; }
        if (nameEl) nameEl.textContent = '';
        return;
    }
    if (status) {
        status.textContent = 'Uploaded';
        status.className = 'doc-card-status inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-600 mb-2';
    }
    if (nameEl) { nameEl.textContent = file.name; nameEl.className = 'doc-card-filename text-[10px] text-slate-400 truncate mt-1'; }
}

/* ---------------- Sections / Family / City ---------------- */
function getSection(cid) {
    var sel = document.getElementById('txt_section');
    if (!cid) { sel.innerHTML = '<option value="" disabled selected>Select Section</option>'; return; }
    sel.innerHTML = '<option value="">Loading...</option>';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', HIIFI_BASE + 'ajax_get_sections.php?class_id=' + encodeURIComponent(cid));
    xhr.onload = function () {
        var data;
        try { data = JSON.parse(xhr.responseText || '[]'); } catch (err) { data = []; }
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
        try { data = JSON.parse(xhr.responseText); } catch (err) { return; }
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
        showToast('Family details loaded', 'success');
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

/* ---------------- Multi Students ---------------- */
var multiIndex = 0;
function addMultiRow() {
    multiIndex++;
    var tr = document.createElement('tr');
    tr.id = 'multi-row-' + multiIndex;
    tr.innerHTML =
        '<td class="px-4 py-2 text-slate-400 text-[12px]">' + multiIndex + '</td>' +
        '<td class="px-4 py-2"><div class="fieldset-box"><label class="fieldset-label">Student Name</label><input id="multi-name-' + multiIndex + '" class="fieldset-input" placeholder="Full name"></div></td>' +
        '<td class="px-4 py-2"><div class="fieldset-box"><label class="fieldset-label">Father Name</label><input id="multi-father-' + multiIndex + '" class="fieldset-input" placeholder="Father name"></div></td>' +
        '<td class="px-4 py-2"><div class="fieldset-box"><label class="fieldset-label">Cell / Mobile</label><input id="multi-cell-' + multiIndex + '" class="fieldset-input" placeholder="03XX-XXXXXXX"></div></td>' +
        '<td class="px-4 py-2"><div class="fieldset-box"><label class="fieldset-label">Class</label><select id="multi-class-' + multiIndex + '" class="fieldset-select"><option value="">Select Class</option>' + multiClassOptions + '</select></div></td>' +
        '<td class="px-4 py-2 text-center"><button onclick="removeMultiRow(' + multiIndex + ')" class="w-7 h-7 rounded-md text-red-500 hover:bg-red-50 flex items-center justify-center"><i class="fa-solid fa-trash-can text-[12px]"></i></button></td>';
    document.getElementById('multi-student-tbody').appendChild(tr);
}
function removeMultiRow(rowId) {
    var el = document.getElementById('multi-row-' + rowId);
    if (el) { el.remove(); showToast('Row removed', 'info'); }
}
function saveMultiStudents() {
    var rows = [];
    document.querySelectorAll('#multi-student-tbody tr').forEach(function (tr) {
        var name = (tr.querySelector('input[id^="multi-name-"]') || {}).value || '';
        var father = (tr.querySelector('input[id^="multi-father-"]') || {}).value || '';
        var cell = (tr.querySelector('input[id^="multi-cell-"]') || {}).value || '';
        var cls = (tr.querySelector('select[id^="multi-class-"]') || { value: '' }).value || '';
        if (name) rows.push({ name: name, father: father, cell: cell, class_id: cls });
    });
    if (rows.length === 0) { showToast('No students added yet', 'warning'); return; }
    var xhr = new XMLHttpRequest();
    xhr.open('POST', HIIFI_BASE + 'add_student.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function () {
        var resp;
        try { resp = JSON.parse(xhr.responseText); } catch (err) { resp = null; }
        if (resp && resp.ok) {
            showToast(resp.inserted + ' student(s) saved successfully', 'success');
            document.querySelectorAll('#multi-student-tbody tr').forEach(function (tr) { tr.remove(); });
            multiIndex = 0;
            for (var i = 0; i < 3; i++) addMultiRow();
        } else {
            showToast('Failed to save students', 'error');
        }
    };
    xhr.send('action=SaveMultiStudents&rows=' + encodeURIComponent(JSON.stringify(rows)));
}

/* ---------------- CSV Import ---------------- */
var csvRecords = [];
var multiClassOptions = '<?php foreach ($classes as $cl): ?><option value="<?php echo $cl['class_id']; ?>"><?php echo e($cl['class_name']); ?></option><?php endforeach; ?>';

function renderCSVTable(data) {
    var tbody = document.getElementById('csv-table-body');
    tbody.innerHTML = '';
    var rows = data || csvRecords;
    rows.forEach(function (r) {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="px-4 py-2 text-center"><input type="checkbox" checked class="w-3.5 h-3.5 accent-brand-orange"></td>' +
            '<td class="px-4 py-2 text-slate-400 text-[12px]">' + r.SNo + '</td>' +
            '<td class="px-4 py-2 font-medium text-slate-700">' + r.StudentName + '</td>' +
            '<td class="px-4 py-2 text-slate-500">' + r.FatherName + '</td>' +
            '<td class="px-4 py-2 text-slate-500">' + r.CellNo + '</td>' +
            '<td class="px-4 py-2 text-slate-500">' + r.Class + '</td>' +
            '<td class="px-4 py-2 text-center"><button onclick="deleteCSVRecord(' + r.SNo + ')" class="w-7 h-7 rounded-md text-red-500 hover:bg-red-50 flex items-center justify-center"><i class="fa-solid fa-trash-can text-[12px]"></i></button></td>';
        tbody.appendChild(tr);
    });
    document.getElementById('csv-count').textContent = rows.length + ' records';
}
function filterCSVTable() {
    var q = (document.getElementById('csv-search').value || '').toLowerCase();
    var filtered = csvRecords.filter(function (r) {
        return r.StudentName.toLowerCase().indexOf(q) !== -1 ||
               r.FatherName.toLowerCase().indexOf(q) !== -1 ||
               r.CellNo.toLowerCase().indexOf(q) !== -1 ||
               r.Class.toLowerCase().indexOf(q) !== -1;
    });
    renderCSVTable(filtered);
}
function deleteCSVRecord(sno) {
    csvRecords = csvRecords.filter(function (r) { return r.SNo !== sno; });
    renderCSVTable();
    showToast('Record deleted', 'info');
}
function triggerCSVImport(input) {
    var file = input.files && input.files[0];
    if (!file) return;
    var ext = file.name.split('.').pop().toLowerCase();
    if (ext !== 'csv') { showToast('Please select a CSV file', 'warning'); input.value = ''; return; }
    var reader = new FileReader();
    reader.onload = function (ev) {
        var text = ev.target.result;
        csvRecords = parseCSV(text);
        if (csvRecords.length === 0) {
            showToast('No valid rows found in CSV', 'warning');
            input.value = '';
            return;
        }
        renderCSVTable();
        showToast(csvRecords.length + ' records loaded from ' + file.name, 'success');
    };
    reader.onerror = function () { showToast('Failed to read file', 'error'); };
    reader.readAsText(file);
}

function parseCSV(text) {
    var lines = (text || '').split(/\r?\n/).map(function (l) { return l.trim(); }).filter(Boolean);
    if (lines.length === 0) return [];
    var header = splitCSVLine(lines[0]);
    var hIdx = {};
    header.forEach(function (h, i) { hIdx[h.trim().toLowerCase()] = i; });
    var nameCol = findCol(hIdx, ['student name', 'name', 'student']);
    var fatherCol = findCol(hIdx, ['father name', 'father', 'fathers name']);
    var cellCol = findCol(hIdx, ['cell', 'mobile', 'cell no', 'cellno', 'phone', 'contact']);
    var classCol = findCol(hIdx, ['class', 'class name', 'cls']);
    if (nameCol === -1 || classCol === -1) return [];
    var rows = [];
    var sno = 1;
    for (var i = 1; i < lines.length; i++) {
        var c = splitCSVLine(lines[i]);
        var name = (c[nameCol] || '').trim();
        if (!name) continue;
        rows.push({
            SNo: sno++,
            StudentName: name,
            FatherName: fatherCol !== -1 ? (c[fatherCol] || '').trim() : '',
            CellNo: cellCol !== -1 ? (c[cellCol] || '').trim() : '',
            Class: (c[classCol] || '').trim(),
            Section: '',
            Gender: '',
            Religion: '',
            DOB: ''
        });
    }
    return rows;
}

function findCol(hIdx, names) {
    for (var i = 0; i < names.length; i++) {
        if (hIdx[names[i]] !== undefined) return hIdx[names[i]];
    }
    return -1;
}

function splitCSVLine(line) {
    var out = [];
    var cur = '';
    var inQ = false;
    for (var i = 0; i < line.length; i++) {
        var ch = line[i];
        if (inQ) {
            if (ch === '"') {
                if (line[i + 1] === '"') { cur += '"'; i++; }
                else inQ = false;
            } else cur += ch;
        } else {
            if (ch === '"') inQ = true;
            else if (ch === ',') { out.push(cur); cur = ''; }
            else cur += ch;
        }
    }
    out.push(cur);
    return out;
}
function downloadSampleCSV() {
    var csv = 'S.No,Student Name,Father Name,Cell / Mobile,Class\n1,Ahmed Raza,Muhammad Raza,0300-1234567,10th\n2,Fatima Bibi,Abdul Ghafoor,0345-9876543,8th\n';
    var blob = new Blob([csv], { type: 'text/csv' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'sample_students.csv';
    a.click();
    URL.revokeObjectURL(a.href);
    showToast('Sample CSV downloaded', 'success');
}
function saveImportedData() {
    if (csvRecords.length === 0) { showToast('No records to import', 'warning'); return; }
    var rows = csvRecords.map(function (r) {
        return { name: r.StudentName, father: r.FatherName, cell: r.CellNo, class: r.Class };
    });
    var xhr = new XMLHttpRequest();
    xhr.open('POST', HIIFI_BASE + 'add_student.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function () {
        var resp;
        try { resp = JSON.parse(xhr.responseText); } catch (err) { resp = null; }
        if (resp && resp.ok) {
            showToast(resp.inserted + ' student(s) imported, ' + resp.skipped + ' skipped', resp.skipped > 0 ? 'warning' : 'success');
            csvRecords = [];
            renderCSVTable();
            document.getElementById('import-csv-input').value = '';
        } else {
            showToast('Import failed', 'error');
        }
    };
    xhr.send('action=ImportCSV&rows=' + encodeURIComponent(JSON.stringify(rows)));
}
function clearImportedData() {
    csvRecords = [];
    renderCSVTable();
    showToast('All records cleared', 'info');
}

window.onload = function () {
    multiIndex = 0;
    for (var i = 0; i < 3; i++) addMultiRow();
    csvRecords = [];
    renderCSVTable();

    document.addEventListener('mousemove', onPosPhotoMove);
    document.addEventListener('mouseup', endPhotoDrag);

    if (window.flatpickr) {
        flatpickr('#dob', { dateFormat: 'd/m/Y' });
        flatpickr('#date_of_adms', { dateFormat: 'd/m/Y' });
    }
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('#family_search').select2({ width: '100%', placeholder: 'Select Family', allowClear: true });
    }
};
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
