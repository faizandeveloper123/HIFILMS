<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

// Create necessary tables
try { db_query("CREATE TABLE IF NOT EXISTS student_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    doc_type VARCHAR(100),
    file_path VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

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
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS monthly_fee DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS old_balance DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS admission_no VARCHAR(50) DEFAULT NULL"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS sibling_code VARCHAR(50) DEFAULT NULL"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS course_package VARCHAR(191) DEFAULT NULL"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS discount_package_id INT DEFAULT NULL"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS course_package_id INT DEFAULT NULL"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS transport_fee DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS discount_reason VARCHAR(191) DEFAULT NULL"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS miscellaneous_fee DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_mode VARCHAR(30) DEFAULT 'monthly'"); } catch (Throwable $ex) {}

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

                // Redirect to fee plan
                header('Location: ' . BASE_URL . 'fee_plan.php?student_id=' . $studentId);
                exit;
            }
        } catch (Exception $ex) {
            $error = 'Error: ' . $ex->getMessage();
        }
    }
}

// AJAX handlers for multi-student and CSV import
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
            $fee    = (float)($r['fee'] ?? 0);
            $balance = (float)($r['balance'] ?? 0);
            $rc = $class_id > 0 ? $class_id : (int)($r['class_id'] ?? 0);
            $rs = $section_id > 0 ? $section_id : 0;
            if ($name === '' || $rc === 0) continue;
            $sid = 0;
            $stmt = db_prepare('INSERT INTO students (first_name, father_name, phone, class_id, section_id, session, monthly_fee, old_balance, admission_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
            $stmt->bind_param('sssiissds', $name, $father, $cell, $rc, $rs, $session, $fee, $balance, $adm);
            $stmt->execute();
            $sid = $stmt->insert_id;
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
    if ($session === '') { $session = get_setting('session_year', '2026-2027'); }
    $inserted = 0;
    $skipped  = 0;
    if (is_array($rows)) {
        $classCache = [];
        $sectionCache = [];
        foreach ($rows as $r) {
            $name    = trim($r['name'] ?? '');
            $father  = trim($r['father'] ?? '');
            $cell    = trim($r['cell'] ?? '');
            $cn      = trim($r['class'] ?? '');
            $sn      = trim($r['section'] ?? '');
            $gender  = strtolower(trim($r['gender'] ?? '')) === 'female' ? 'female' : 'male';
            $religion = trim($r['religion'] ?? '') !== '' ? $r['religion'] : 'Islam';
            $dob     = null;
            if (!empty($r['dob'])) {
                $ts = strtotime(str_replace('/', '-', $r['dob']));
                $dob = $ts ? date('Y-m-d', $ts) : null;
            }
            $admDate = null;
            if (!empty($r['admission_date'])) {
                $ats = strtotime(str_replace('/', '-', $r['admission_date']));
                $admDate = $ats ? date('Y-m-d', $ats) : null;
            }
            $admNo     = trim($r['admission_no'] ?? '') !== '' ? $r['admission_no'] : null;
            $sibCode   = trim($r['sibling_code'] ?? '') !== '' ? $r['sibling_code'] : null;
            $famCode   = trim($r['family_code'] ?? '') !== '' ? $r['family_code'] : null;
            $coursePkg = trim($r['course_package'] ?? '') !== '' ? $r['course_package'] : null;
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
            $newId = 0;
            $stmt = db_prepare('INSERT INTO students (first_name, father_name, phone, class_id, section_id, session, gender, religion, dob, admission_date, admission_no, sibling_code, family_code, course_package, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
            $stmt->bind_param('sssiisssssssss', $name, $father, $cell, $cid, $sid, $session, $gender, $religion, $dob, $admDate, $admNo, $sibCode, $famCode, $coursePkg);
            $stmt->execute();
            $newId = $stmt->insert_id;
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

<style>
    :root {
        --primary: #FF6B2C;
        --primary-light: #FFF3EC;
        --primary-hover: #E55A1E;
        --gray-50: #F8FAFC;
        --gray-100: #F1F5F9;
        --gray-200: #E2E8F0;
        --gray-300: #CBD5E1;
        --gray-400: #94A3B8;
        --gray-500: #64748B;
        --gray-600: #475569;
        --gray-700: #334155;
        --gray-800: #1E293B;
        --gray-900: #0F172A;
        --border-radius: 12px;
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: var(--gray-50); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }

    .page-wrapper { max-width: 1440px; margin: 0 auto; padding: 16px 20px 40px; width: 100%; }

    /* Top Bar */
    .top-bar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid var(--gray-200); }
    .top-bar-left { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
    .top-bar-left .brand { font-size: clamp(16px, 2.2vw, 22px); font-weight: 700; color: var(--gray-900); letter-spacing: -0.3px; }
    .top-bar-left .brand span { color: var(--primary); }
    .top-bar-left .session-badge { font-size: clamp(10px, 1.2vw, 13px); font-weight: 600; color: var(--gray-500); background: var(--gray-100); padding: 3px 12px; border-radius: 20px; border: 1px solid var(--gray-200); white-space: nowrap; }
    .top-bar-right { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
    .top-bar-right .search-box { display: flex; align-items: center; gap: 6px; background: white; border: 1px solid var(--gray-200); border-radius: 8px; padding: 5px 12px; font-size: clamp(11px, 1.1vw, 13px); color: var(--gray-500); min-width: 140px; flex: 1; max-width: 320px; }
    .top-bar-right .search-box i { color: var(--gray-400); font-size: clamp(12px, 1vw, 14px); }
    .top-bar-right .search-box input { border: none; outline: none; background: transparent; font-size: clamp(11px, 1.1vw, 13px); color: var(--gray-700); width: 100%; min-width: 80px; }
    .top-bar-right .search-box input::placeholder { color: var(--gray-400); font-size: clamp(10px, 1vw, 12px); }
    .top-bar-right .user-badge { display: flex; align-items: center; gap: 6px; font-size: clamp(11px, 1.1vw, 13px); font-weight: 500; color: var(--gray-700); white-space: nowrap; }
    .top-bar-right .user-badge .avatar { width: clamp(28px, 3vw, 36px); height: clamp(28px, 3vw, 36px); border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: clamp(12px, 1.2vw, 15px); font-weight: 600; flex-shrink: 0; }

    /* Quick Links */
    .quick-links { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-bottom: 14px; font-size: clamp(11px, 1.1vw, 13px); color: var(--gray-500); }
    .quick-links a { color: var(--gray-600); text-decoration: none; padding: 3px 8px; border-radius: 6px; transition: all 0.2s; font-size: clamp(11px, 1.1vw, 13px); white-space: nowrap; }
    .quick-links a:hover { background: var(--gray-100); color: var(--gray-800); }
    .quick-links .separator { color: var(--gray-300); font-size: clamp(9px, 0.8vw, 11px); }

    /* Main Tabs */
    .main-tabs { display: flex; align-items: center; gap: 4px; background: white; border: 1px solid var(--gray-200); border-radius: var(--border-radius); padding: 4px; margin-bottom: 20px; overflow-x: auto; flex-wrap: nowrap; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
    .main-tabs::-webkit-scrollbar { display: none; }
    .main-tabs .tab-btn { display: flex; align-items: center; gap: clamp(4px, 0.6vw, 8px); padding: clamp(6px, 0.8vw, 10px) clamp(12px, 1.5vw, 20px); border-radius: 8px; font-size: clamp(11px, 1.1vw, 14px); font-weight: 500; color: var(--gray-600); background: transparent; border: none; cursor: pointer; transition: all 0.2s; white-space: nowrap; text-decoration: none; flex-shrink: 0; }
    .main-tabs .tab-btn:hover { background: var(--gray-100); color: var(--gray-800); }
    .main-tabs .tab-btn.active { background: var(--primary-light); color: var(--primary); font-weight: 600; }
    .main-tabs .tab-btn i { font-size: clamp(13px, 1.2vw, 16px); }

    /* Section Card */
    .section-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--border-radius); overflow: hidden; margin-bottom: clamp(14px, 1.8vw, 24px); }
    .section-card .section-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: clamp(10px, 1.2vw, 16px) clamp(14px, 1.8vw, 24px); border-bottom: 1px solid var(--gray-100); background: var(--gray-50); }
    .section-card .section-header h3 { font-size: clamp(13px, 1.2vw, 16px); font-weight: 600; color: var(--gray-700); display: flex; align-items: center; gap: 8px; }
    .section-card .section-header h3 i { color: var(--primary); font-size: clamp(14px, 1.2vw, 17px); }
    .section-card .section-body { padding: clamp(14px, 1.8vw, 24px); }

    /* Form Grids */
    .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 220px), 1fr)); gap: clamp(12px, 1.5vw, 20px); }
    .form-grid-2 { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr)); gap: clamp(12px, 1.5vw, 20px); }

    /* Fieldset Box */
    .fieldset-box { position: relative; border: 1px solid var(--gray-200); border-radius: 8px; padding: clamp(12px, 1.2vw, 16px) clamp(10px, 1vw, 14px) clamp(4px, 0.5vw, 8px) clamp(10px, 1vw, 14px); background: white; transition: border-color 0.2s, box-shadow 0.2s; min-height: clamp(44px, 5vw, 56px); width: 100%; }
    .fieldset-box:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(255, 107, 44, 0.08); }
    .fieldset-box .fieldset-label { position: absolute; left: clamp(10px, 1vw, 14px); top: -8px; background: white; padding: 0 clamp(4px, 0.5vw, 8px); font-size: clamp(9px, 0.8vw, 11px); font-weight: 600; color: var(--gray-500); letter-spacing: 0.3px; pointer-events: none; text-transform: uppercase; white-space: nowrap; }
    .fieldset-box .fieldset-label.required::after { content: ' *'; color: #EF4444; }
    .fieldset-box .fieldset-input, .fieldset-box .fieldset-select, .fieldset-box .fieldset-textarea { width: 100%; border: none; outline: none; background: transparent; font-size: clamp(12px, 1.1vw, 14px); color: var(--gray-800); padding: 2px 0 4px; font-family: inherit; min-height: clamp(24px, 2.5vw, 32px); }
    .fieldset-box .fieldset-input::placeholder, .fieldset-box .fieldset-textarea::placeholder { color: var(--gray-400); font-weight: 400; font-size: clamp(11px, 1vw, 13px); }
    .fieldset-box .fieldset-textarea { resize: vertical; min-height: 28px; max-height: 80px; }
    .fieldset-box .fieldset-select { cursor: pointer; appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%2394a3b8'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 4px center; padding-right: 20px; }
    .fieldset-box .inline-actions { display: flex; align-items: center; gap: clamp(4px, 0.5vw, 8px); width: 100%; }
    .fieldset-box .inline-actions .fieldset-input, .fieldset-box .inline-actions .fieldset-select { flex: 1; min-width: 0; }
    .fieldset-box .btn-add-new { display: inline-flex; align-items: center; gap: 3px; font-size: clamp(10px, 0.9vw, 12px); font-weight: 600; color: var(--primary); padding: 2px clamp(6px, 0.6vw, 10px); border-radius: 4px; text-decoration: none; transition: background 0.2s; white-space: nowrap; flex-shrink: 0; background: transparent; border: none; cursor: pointer; }
    .fieldset-box .btn-add-new:hover { background: var(--primary-light); }
    .fieldset-box .btn-add-new i { font-size: clamp(9px, 0.8vw, 11px); }

    /* Main Layout */
    .main-layout { display: grid; grid-template-columns: 1fr minmax(200px, 280px); gap: clamp(16px, 2vw, 24px); }

    /* Photo Widget */
    .photo-widget { background: white; border: 1px solid var(--gray-200); border-radius: var(--border-radius); padding: clamp(14px, 1.8vw, 24px); text-align: center; height: fit-content; position: sticky; top: 20px; }
    .photo-widget .photo-title { font-size: clamp(12px, 1.2vw, 15px); font-weight: 600; color: var(--gray-700); margin-bottom: clamp(12px, 1.5vw, 18px); display: flex; align-items: center; justify-content: center; gap: 8px; }
    .photo-widget .photo-title i { color: var(--primary); font-size: clamp(14px, 1.2vw, 17px); }
    .photo-widget .photo-frame { width: 100%; max-width: 180px; aspect-ratio: 4/5; margin: 0 auto clamp(10px, 1.2vw, 16px); border-radius: 10px; border: 2px dashed var(--gray-300); background: var(--gray-50); display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: border-color 0.2s; overflow: hidden; position: relative; min-height: 140px; }
    .photo-widget .photo-frame:hover { border-color: var(--primary); }
    .photo-widget .photo-frame.has-image { border-style: solid; border-color: var(--gray-200); }
    .photo-widget .photo-frame img { width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; }
    .photo-widget .photo-frame .placeholder-icon { font-size: clamp(28px, 3vw, 40px); color: var(--gray-400); margin-bottom: 6px; }
    .photo-widget .photo-frame .placeholder-text { font-size: clamp(11px, 1vw, 14px); font-weight: 500; color: var(--gray-500); }
    .photo-widget .photo-frame .placeholder-sub { font-size: clamp(9px, 0.8vw, 11px); color: var(--gray-400); margin-top: 2px; }
    .photo-widget .photo-frame .delete-btn { position: absolute; top: 6px; right: 6px; width: clamp(22px, 2.2vw, 28px); height: clamp(22px, 2.2vw, 28px); border-radius: 50%; background: #EF4444; color: white; border: none; font-size: clamp(10px, 1vw, 13px); cursor: pointer; display: none; align-items: center; justify-content: center; transition: background 0.2s; z-index: 5; }
    .photo-widget .photo-frame .delete-btn:hover { background: #DC2626; }
    .photo-widget .photo-frame.has-image .delete-btn { display: flex; }
    .photo-widget .photo-controls { margin-top: clamp(10px, 1.2vw, 16px); display: none; }
    .photo-widget .photo-controls.active { display: block; }
    .photo-widget .photo-controls .control-row { display: flex; align-items: center; gap: clamp(6px, 0.8vw, 12px); margin-bottom: 6px; }
    .photo-widget .photo-controls .control-row i { color: var(--gray-400); font-size: clamp(11px, 1vw, 14px); width: clamp(14px, 1.2vw, 18px); flex-shrink: 0; }
    .photo-widget .photo-controls .control-row input[type="range"] { flex: 1; accent-color: var(--primary); height: 4px; cursor: pointer; min-width: 40px; }
    .photo-widget .photo-controls .control-row .value-label { font-size: clamp(10px, 0.9vw, 12px); color: var(--gray-400); width: clamp(32px, 3vw, 40px); text-align: right; flex-shrink: 0; }

    /* Buttons */
    .btn-primary { display: inline-flex; align-items: center; gap: clamp(6px, 0.6vw, 10px); padding: clamp(8px, 0.9vw, 12px) clamp(16px, 1.8vw, 28px); border-radius: 8px; background: var(--primary); color: white; font-size: clamp(12px, 1.1vw, 15px); font-weight: 600; border: none; cursor: pointer; transition: background 0.2s, transform 0.1s; white-space: nowrap; }
    .btn-primary:hover { background: var(--primary-hover); }
    .btn-primary:active { transform: scale(0.98); }
    .btn-secondary { display: inline-flex; align-items: center; gap: clamp(6px, 0.6vw, 10px); padding: clamp(8px, 0.9vw, 12px) clamp(14px, 1.5vw, 22px); border-radius: 8px; background: var(--gray-100); color: var(--gray-600); font-size: clamp(12px, 1.1vw, 15px); font-weight: 500; border: none; cursor: pointer; transition: background 0.2s; white-space: nowrap; }
    .btn-secondary:hover { background: var(--gray-200); }
    .btn-success { display: inline-flex; align-items: center; gap: clamp(6px, 0.6vw, 10px); padding: clamp(8px, 0.9vw, 12px) clamp(16px, 1.8vw, 28px); border-radius: 8px; background: #22C55E; color: white; font-size: clamp(12px, 1.1vw, 15px); font-weight: 600; border: none; cursor: pointer; transition: background 0.2s; white-space: nowrap; }
    .btn-success:hover { background: #16A34A; }

    /* Form Actions */
    .form-actions { display: flex; align-items: center; flex-wrap: wrap; gap: clamp(10px, 1.2vw, 16px); padding-top: clamp(16px, 2vw, 24px); border-top: 1px solid var(--gray-200); margin-top: 4px; }
    .form-actions .note { font-size: clamp(11px, 1vw, 13px); color: var(--gray-400); margin-left: auto; }

    /* Sub Tabs */
    .sub-tabs { display: flex; align-items: center; gap: 4px; margin-bottom: clamp(14px, 1.8vw, 20px); overflow-x: auto; flex-wrap: nowrap; -webkit-overflow-scrolling: touch; scrollbar-width: none; padding: 2px 0; }
    .sub-tabs::-webkit-scrollbar { display: none; }
    .sub-tabs .subtab-btn { display: inline-flex; align-items: center; gap: clamp(4px, 0.5vw, 8px); padding: clamp(5px, 0.6vw, 8px) clamp(10px, 1.2vw, 18px); border-radius: 8px; font-size: clamp(11px, 1vw, 13px); font-weight: 500; color: var(--gray-500); background: white; border: 1px solid var(--gray-200); cursor: pointer; transition: all 0.2s; white-space: nowrap; flex-shrink: 0; }
    .sub-tabs .subtab-btn:hover { background: var(--gray-50); }
    .sub-tabs .subtab-btn.active { background: var(--primary-light); color: var(--primary); border-color: var(--primary); font-weight: 600; }
    .sub-tabs .subtab-btn i { font-size: clamp(12px, 1vw, 14px); }

    /* Progress Steps */
    .progress-steps { display: flex; align-items: center; gap: clamp(10px, 1.5vw, 20px); background: white; border: 1px solid var(--gray-200); border-radius: var(--border-radius); padding: clamp(10px, 1.2vw, 16px) clamp(14px, 1.8vw, 24px); margin-bottom: clamp(16px, 2vw, 24px); flex-wrap: wrap; }
    .progress-steps .step { display: flex; align-items: center; gap: clamp(8px, 1vw, 14px); }
    .progress-steps .step .num { width: clamp(28px, 2.8vw, 36px); height: clamp(28px, 2.8vw, 36px); border-radius: 50%; background: var(--gray-200); color: var(--gray-500); display: flex; align-items: center; justify-content: center; font-size: clamp(12px, 1.2vw, 15px); font-weight: 700; flex-shrink: 0; }
    .progress-steps .step .num.active { background: var(--primary); color: white; }
    .progress-steps .step .info { flex: 1; min-width: 0; }
    .progress-steps .step .info .title { font-size: clamp(12px, 1.1vw, 14px); font-weight: 600; color: var(--gray-700); }
    .progress-steps .step .info .sub { font-size: clamp(10px, 0.9vw, 12px); color: var(--gray-400); }
    .progress-steps .divider { flex: 1; min-width: 20px; max-width: 100px; border-top: 2px dashed var(--gray-200); }

    /* Document Cards */
    .doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 150px), 1fr)); gap: clamp(12px, 1.5vw, 18px); }
    .doc-card { border: 1px dashed var(--gray-300); border-radius: 10px; padding: clamp(12px, 1.2vw, 18px) clamp(10px, 1vw, 14px); text-align: center; transition: border-color 0.2s, background 0.2s; cursor: pointer; }
    .doc-card:hover { border-color: var(--primary); background: var(--primary-light); }
    .doc-card .doc-icon { width: clamp(36px, 3.5vw, 48px); height: clamp(36px, 3.5vw, 48px); margin: 0 auto clamp(6px, 0.6vw, 10px); border-radius: 8px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: clamp(16px, 1.5vw, 22px); }
    .doc-card .doc-name { font-size: clamp(11px, 1vw, 13px); font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 4px; line-height: 1.3; }
    .doc-card .doc-status { display: inline-block; padding: 2px clamp(8px, 0.8vw, 12px); border-radius: 12px; font-size: clamp(9px, 0.8vw, 11px); font-weight: 600; background: var(--gray-100); color: var(--gray-400); margin-bottom: clamp(6px, 0.6vw, 10px); }
    .doc-card .doc-status.uploaded { background: #DCFCE7; color: #16A34A; }
    .doc-card .doc-upload-btn { display: inline-flex; align-items: center; gap: clamp(4px, 0.4vw, 8px); padding: clamp(4px, 0.4vw, 8px) clamp(10px, 1vw, 16px); border-radius: 6px; background: #2563EB; color: white; font-size: clamp(10px, 0.9vw, 12px); font-weight: 600; border: none; cursor: pointer; transition: background 0.2s; }
    .doc-card .doc-upload-btn:hover { background: #1D4ED8; }
    .doc-card .doc-filename { font-size: clamp(9px, 0.8vw, 11px); color: var(--gray-400); margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* Table Wrapper */
    .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 0; padding: 0; }
    .table-wrapper table { width: 100%; border-collapse: collapse; font-size: clamp(12px, 1.1vw, 14px); min-width: 600px; }
    .table-wrapper table th { padding: clamp(8px, 0.9vw, 12px) clamp(10px, 1vw, 16px); text-align: left; font-size: clamp(10px, 0.9vw, 12px); text-transform: uppercase; color: var(--gray-500); font-weight: 600; background: var(--gray-50); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
    .table-wrapper table td { padding: clamp(6px, 0.8vw, 10px) clamp(10px, 1vw, 16px); border-bottom: 1px solid var(--gray-100); }

    /* Toast */
    #toast-container { position: fixed; top: 20px; right: 20px; z-index: 1000; display: flex; flex-direction: column; gap: 8px; max-width: min(360px, 90vw); width: 100%; pointer-events: none; }
    .toast-item { padding: clamp(12px, 1.2vw, 16px) clamp(14px, 1.5vw, 20px); border-radius: 10px; color: white; font-size: clamp(12px, 1.1vw, 14px); font-weight: 500; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-lg); animation: slideIn 0.3s ease; pointer-events: auto; width: 100%; }
    .toast-item i { font-size: clamp(14px, 1.2vw, 18px); flex-shrink: 0; }
    .toast-item.success { background: #22C55E; }
    .toast-item.error { background: #EF4444; }
    .toast-item.warning { background: #F59E0B; }
    .toast-item.info { background: #3B82F6; }

    @keyframes slideIn { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: none; } }

    /* Error Message */
    .error-message { display: flex; align-items: center; gap: 10px; background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 8px; padding: clamp(10px, 1.2vw, 14px) clamp(14px, 1.5vw, 20px); margin-bottom: clamp(14px, 1.8vw, 20px); color: #DC2626; font-size: clamp(12px, 1.1vw, 14px); }

    /* ============ RESPONSIVE ============ */
    @media (max-width: 991px) { .main-layout { grid-template-columns: 1fr; gap: 16px; } .photo-widget { position: static; max-width: 320px; margin: 0 auto; } .form-grid { grid-template-columns: repeat(2, 1fr); } .form-grid-2 { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 767px) { .page-wrapper { padding: 12px 16px 28px; } .form-grid { grid-template-columns: 1fr; } .form-grid-2 { grid-template-columns: 1fr; } .main-layout { grid-template-columns: 1fr; } .top-bar { flex-direction: column; align-items: stretch; gap: 8px; } .top-bar-right { flex-wrap: wrap; } .top-bar-right .search-box { max-width: 100%; } .main-tabs .tab-btn span { display: none; } .main-tabs .tab-btn i { font-size: 16px; } .sub-tabs .subtab-btn span { display: none; } .sub-tabs .subtab-btn i { font-size: 14px; } .progress-steps .divider { display: none; } .progress-steps .step .info .sub { display: none; } .form-actions .note { margin-left: 0; width: 100%; } .form-actions { flex-wrap: wrap; } .photo-widget { position: static; } .table-wrapper table { min-width: 480px; } }
    @media (max-width: 479px) { .page-wrapper { padding: 10px 12px 24px; } .form-grid { grid-template-columns: 1fr; } .form-grid-2 { grid-template-columns: 1fr; } .main-layout { grid-template-columns: 1fr; } .doc-grid { grid-template-columns: 1fr 1fr; } .main-tabs .tab-btn { padding: 4px 10px; font-size: 10px; } .main-tabs .tab-btn span { display: none; } .main-tabs .tab-btn i { font-size: 14px; } .top-bar { flex-direction: column; align-items: stretch; gap: 6px; } .top-bar-right .search-box { max-width: 100%; } .sub-tabs .subtab-btn { padding: 4px 8px; font-size: 10px; } .sub-tabs .subtab-btn span { display: none; } .sub-tabs .subtab-btn i { font-size: 14px; } .progress-steps { flex-direction: column; align-items: flex-start; gap: 8px; } .progress-steps .divider { display: none; } .progress-steps .step .info .sub { display: none; } .form-actions { flex-direction: column; align-items: stretch; } .form-actions .note { margin-left: 0; } .btn-primary, .btn-secondary, .btn-success { justify-content: center; width: 100%; } .photo-widget { max-width: 280px; margin: 0 auto; } .table-wrapper table { min-width: 380px; } }

    /* Landscape */
    @media (max-height: 500px) and (orientation: landscape) {
        .page-wrapper { padding: 8px 16px 16px; }
        .top-bar { margin-bottom: 8px; padding-bottom: 8px; }
        .top-bar-left .brand { font-size: 16px; }
        .main-tabs .tab-btn { padding: 4px 10px; font-size: 10px; }
        .main-tabs .tab-btn span { display: none; }
        .main-tabs .tab-btn i { font-size: 12px; }
        .sub-tabs .subtab-btn { padding: 3px 8px; font-size: 9px; }
        .sub-tabs .subtab-btn span { display: none; }
        .sub-tabs .subtab-btn i { font-size: 12px; }
        .progress-steps { padding: 6px 12px; margin-bottom: 10px; gap: 6px; }
        .progress-steps .step .num { width: 22px; height: 22px; font-size: 10px; }
        .progress-steps .step .info .title { font-size: 10px; }
        .progress-steps .step .info .sub { display: none; }
        .progress-steps .divider { display: none; }
        .section-card .section-header { padding: 6px 12px; }
        .section-card .section-header h3 { font-size: 11px; }
        .section-card .section-body { padding: 8px 12px; }
        .form-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
        .form-grid-2 { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; }
        .fieldset-box { min-height: 34px; padding: 8px 6px 2px 6px; }
        .fieldset-box .fieldset-label { font-size: 7px; top: -5px; }
        .fieldset-box .fieldset-input, .fieldset-box .fieldset-select { font-size: 11px; min-height: 18px; }
        .main-layout { grid-template-columns: 1fr 160px; gap: 10px; }
        .photo-widget { padding: 10px; }
        .photo-widget .photo-frame { max-width: 100px; min-height: 80px; }
        .btn-primary, .btn-secondary, .btn-success { font-size: 10px; padding: 4px 12px; }
        .form-actions { padding-top: 10px; gap: 6px; }
        .table-wrapper table { min-width: 320px; font-size: 10px; }
    }
</style>

<div class="page-wrapper">

    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-bar-left">
            <div class="brand">Test <span>Portal</span></div>
            <div class="session-badge"><?php echo e($cur_session); ?></div>
        </div>
        <div class="top-bar-right">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search Student with | Name | GR No | Family Code">
            </div>
            <div class="user-badge">
                <span>Super Admin</span>
                <div class="avatar">SA</div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="quick-links">
        <a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <span class="separator">/</span>
        <a href="<?php echo BASE_URL; ?>manage_students.php">Students</a>
        <span class="separator">/</span>
        <span style="color: var(--gray-700); font-weight: 500;">Add New Student</span>
    </div>

    <?php if ($error !== ''): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Main Tabs -->
    <div class="main-tabs">
        <button id="tab-btn-single" onclick="switchMainView('single')" class="tab-btn active">
            <i class="fas fa-user-plus"></i>
            <span>Add New Student</span>
        </button>
        <button id="tab-btn-multi" onclick="switchMainView('multi')" class="tab-btn">
            <i class="fas fa-users"></i>
            <span>Add Multi Students</span>
        </button>
        <button id="tab-btn-import" onclick="switchMainView('import')" class="tab-btn">
            <i class="fas fa-upload"></i>
            <span>Import Students with CSV</span>
        </button>
        <a id="tab-btn-form" href="<?php echo BASE_URL; ?>adm_form.php" target="_blank" class="tab-btn">
            <i class="fas fa-file-alt"></i>
            <span>Admission Form</span>
        </a>
    </div>

    <!-- ===================== VIEW: SINGLE STUDENT ===================== -->
    <div id="view-single-student">

        <!-- Progress Steps -->
        <div class="progress-steps">
            <div class="step">
                <div class="num active">1</div>
                <div class="info">
                    <div class="title">Student Information</div>
                    <div class="sub">Name, class &amp; contact details</div>
                </div>
            </div>
            <div class="divider"></div>
            <div class="step">
                <div class="num">2</div>
                <div class="info">
                    <div class="title">Fee Plan</div>
                    <div class="sub">Guardians &amp; fee plan setup</div>
                </div>
            </div>
            <div class="divider"></div>
            <div class="step">
                <div class="num">3</div>
                <div class="info">
                    <div class="title">Finish</div>
                    <div class="sub">Complete admission process</div>
                </div>
            </div>
        </div>

        <!-- Sub Tabs -->
        <div class="sub-tabs">
            <button id="subtab-basic" onclick="switchFormTab('basic')" class="subtab-btn active">
                <i class="fas fa-id-card"></i>
                <span>Basic Information</span>
            </button>
            <button id="subtab-parent" onclick="switchFormTab('parent')" class="subtab-btn">
                <i class="fas fa-user-friends"></i>
                <span>Parent Details</span>
            </button>
            <button id="subtab-academic" onclick="switchFormTab('academic')" class="subtab-btn">
                <i class="fas fa-graduation-cap"></i>
                <span>Academic Information</span>
            </button>
            <button id="subtab-contact" onclick="switchFormTab('contact')" class="subtab-btn">
                <i class="fas fa-address-book"></i>
                <span>Contact Information</span>
            </button>
            <button id="subtab-documents" onclick="switchFormTab('documents')" class="subtab-btn">
                <i class="fas fa-paperclip"></i>
                <span>Documents</span>
            </button>
        </div>

        <!-- Form -->
        <form id="single-student-form" action="<?php echo BASE_URL; ?>add_student.php" method="post" enctype="multipart/form-data" onsubmit="handleSaveStudent(event)" autocomplete="off">
            <input type="hidden" name="action" value="AddAdmission">
            <input type="hidden" name="family_code" id="family_code_value" value="">
            <input type="hidden" name="captured_image" id="captured_image" value="">
            <input type="hidden" name="redirect_mode" id="redirect_mode" value="">
            <input type="hidden" name="old_file" id="old_file" value="">

            <div class="main-layout">

                <!-- Left Column -->
                <div>

                    <!-- BASIC TAB -->
                    <div id="form-basic">
                        <div class="section-card">
                            <div class="section-header">
                                <h3><i class="fas fa-user"></i> Basic Information</h3>
                            </div>
                            <div class="section-body">
                                <div class="form-grid">
                                    <div class="fieldset-box">
                                        <label class="fieldset-label required">Student Name</label>
                                        <input type="text" class="fieldset-input" name="first_name" id="first_name" placeholder="Enter Full Name" required>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label required">Father Name</label>
                                        <input type="text" class="fieldset-input" name="lname" id="last_name" placeholder="Enter Father Name" required>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Mother Name</label>
                                        <input type="text" class="fieldset-input" name="mother_name" id="mother_name" placeholder="Enter Mother Name">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label required">Cell / Mobile Number</label>
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
                                        <label class="fieldset-label">Board / Council</label>
                                        <div class="inline-actions">
                                            <select name="board_council" id="board_council" class="fieldset-select">
                                                <option value="">Select Board</option>
                                                <?php foreach ($boards as $b): ?>
                                                <option value="<?php echo $b['id']; ?>"><?php echo e($b['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <a href="<?php echo BASE_URL; ?>manage_board.php" target="_blank" class="btn-add-new"><i class="fas fa-plus"></i> Add New</a>
                                        </div>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Group / Shift</label>
                                        <div class="inline-actions">
                                            <select name="group_shift" id="group_shift" class="fieldset-select">
                                                <option value="">Select Group</option>
                                                <?php foreach ($groups as $g): ?>
                                                <option value="<?php echo $g['id']; ?>"><?php echo e($g['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <a href="<?php echo BASE_URL; ?>manage_group.php" target="_blank" class="btn-add-new"><i class="fas fa-plus"></i> Add New</a>
                                        </div>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Admission Source</label>
                                        <div class="inline-actions">
                                            <select name="adm_source" id="adm_source" class="fieldset-select">
                                                <option value="">Select Source</option>
                                                <?php foreach ($admSrcs as $a): ?>
                                                <option value="<?php echo $a['id']; ?>"><?php echo e($a['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <a href="<?php echo BASE_URL; ?>manage_admission_sources.php" target="_blank" class="btn-add-new"><i class="fas fa-plus"></i> Add New</a>
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
                                        <label class="fieldset-label">Locality</label>
                                        <div class="inline-actions">
                                            <select name="Locality" id="locality" class="fieldset-select">
                                                <option value="">Select Locality</option>
                                                <?php foreach ($localities as $loc): ?>
                                                <option value="<?php echo $loc['locality_id']; ?>"><?php echo e($loc['locality_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <a href="<?php echo BASE_URL; ?>manage_localities.php" target="_blank" class="btn-add-new"><i class="fas fa-plus"></i> Add New</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PARENT TAB -->
                    <div id="form-parent" class="hidden">
                        <div class="section-card">
                            <div class="section-header">
                                <h3><i class="fas fa-user-tie"></i> Father &amp; Mother Information</h3>
                            </div>
                            <div class="section-body">
                                <div class="form-grid-2">
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Father CNIC</label>
                                        <input type="text" class="fieldset-input" name="cnic" id="cnic" placeholder="00000-0000000-0" maxlength="13" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,13);">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Father Qualification</label>
                                        <input type="text" class="fieldset-input" name="Fqualification" id="father_qualification" placeholder="e.g. Master">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Father Occupation</label>
                                        <div class="inline-actions">
                                            <select name="father_occupation" id="father_occupation" class="fieldset-select">
                                                <option value="">Select Occupation</option>
                                                <?php foreach ($occupations as $o): ?>
                                                <option value="<?php echo $o['id']; ?>"><?php echo e($o['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <a href="<?php echo BASE_URL; ?>manage_occupations.php" target="_blank" class="btn-add-new"><i class="fas fa-plus"></i> Add New</a>
                                        </div>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Father Business Address</label>
                                        <textarea class="fieldset-input fieldset-textarea" name="Fbusiness_address" id="Fbusiness_address" placeholder="Business address"></textarea>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Father Income</label>
                                        <input type="text" class="fieldset-input" name="Fincome" id="father_income" placeholder="e.g. 60000" inputmode="numeric">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Father Cell No</label>
                                        <input type="text" class="fieldset-input" name="father_cellno" id="father_cellno" placeholder="03XX-XXXXXXX" inputmode="tel">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Mother CNIC</label>
                                        <input type="text" class="fieldset-input" name="mother_cnic" id="mother_cnic" placeholder="00000-0000000-0" maxlength="13" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,13);">
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
                                        <label class="fieldset-label">Mother Cell No</label>
                                        <input type="text" class="fieldset-input" name="mother_cell" id="mother_cell" placeholder="03XX-XXXXXXX" inputmode="tel">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">B-Form No</label>
                                        <input type="text" class="fieldset-input" name="formBNo" id="formBNo" placeholder="B-Form number">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Cast</label>
                                        <input type="text" class="fieldset-input" name="cast" id="cast" placeholder="Caste">
                                    </div>
                                    <div class="fieldset-box" style="grid-column: span 2;">
                                        <label class="fieldset-label">Home Address</label>
                                        <textarea class="fieldset-input fieldset-textarea" name="address" id="address" placeholder="Complete residential address"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="section-card">
                            <div class="section-header">
                                <h3><i class="fas fa-user-shield"></i> Guardian Information</h3>
                            </div>
                            <div class="section-body">
                                <div class="form-grid-2">
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
                                        <textarea class="fieldset-input fieldset-textarea" name="Gaddress" id="gardian_address" placeholder="Guardian address"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ACADEMIC TAB -->
                    <div id="form-academic" class="hidden">
                        <div class="section-card">
                            <div class="section-header">
                                <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                            </div>
                            <div class="section-body">
                                <div class="form-grid">
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
                    </div>

                    <!-- CONTACT TAB -->
                    <div id="form-contact" class="hidden">
                        <div class="section-card">
                            <div class="section-header">
                                <h3><i class="fas fa-map-marker-alt"></i> Contact Information</h3>
                            </div>
                            <div class="section-body">
                                <div class="form-grid-2">
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Whatsapp No</label>
                                        <input type="text" class="fieldset-input" name="whatsapp_number" id="whatsapp_number" placeholder="03XX-XXXXXXX" inputmode="tel">
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
                    </div>

                    <!-- DOCUMENTS TAB -->
                    <div id="form-documents" class="hidden">
                        <div class="section-card">
                            <div class="section-header">
                                <h3><i class="fas fa-paperclip"></i> Documents</h3>
                                <a href="<?php echo BASE_URL; ?>add_student_documents.php" target="_blank" style="font-size: clamp(11px, 1vw, 13px); font-weight: 600; color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;">
                                    <i class="fas fa-plus-circle"></i> Manage Document Titles
                                </a>
                            </div>
                            <div class="section-body">
                                <div class="doc-grid">
                                    <?php foreach ($docTitles as $di => $doc): ?>
                                    <div id="doc-card-<?php echo $doc['id']; ?>" class="doc-card">
                                        <div class="doc-icon">
                                            <i class="far fa-file-alt"></i>
                                        </div>
                                        <span class="doc-name"><?php echo e($doc['name']); ?></span>
                                        <span id="docStatus_<?php echo $di; ?>" class="doc-status">Not Uploaded</span>
                                        <div>
                                            <label for="docFile_<?php echo $di; ?>" class="doc-upload-btn">
                                                <i class="fas fa-upload"></i> Choose File
                                            </label>
                                        </div>
                                        <input type="hidden" name="doc_types[]" value="<?php echo e($doc['name']); ?>">
                                        <input type="file" id="docFile_<?php echo $di; ?>" name="doc_files[]" accept=".jpg,.jpeg,.png,.pdf" class="hidden" onchange="previewStudentDoc(this, <?php echo $di; ?>)">
                                        <div class="doc-filename" id="docFileName_<?php echo $di; ?>"></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Column - Photo Widget -->
                <div>
                    <div class="photo-widget">
                        <div class="photo-title">
                            <i class="fas fa-camera"></i> Student Photo
                        </div>

                        <div id="photo-placeholder" onclick="document.getElementById('photo-input').click()" class="photo-frame" style="cursor: pointer;">
                            <div class="placeholder-icon"><i class="fas fa-camera"></i></div>
                            <div class="placeholder-text">Add Photo</div>
                            <div class="placeholder-sub">JPG / PNG</div>
                        </div>

                        <div id="photo-frame" class="photo-frame has-image" style="display: none; cursor: grab;">
                            <img id="photo-preview" style="width: 100%; height: 100%; object-fit: cover; transform-origin: center;" onmousedown="posPhotoDrag(event)">
                            <button type="button" id="photo-delete-btn" onclick="deletePhotoFrame(event)" class="delete-btn" style="display: flex;"><i class="fas fa-times"></i></button>
                        </div>

                        <input type="file" id="photo-input" name="img_file" accept="image/*" class="hidden" onchange="handlePhotoUpload(event)">
                        <canvas id="photo-canvas" class="hidden"></canvas>

                        <div id="photo-controls" class="photo-controls active">
                            <div class="control-row">
                                <i class="fas fa-search-plus"></i>
                                <input type="range" id="zoom-slider" min="0.5" max="2.5" step="0.05" value="1" oninput="updatePhotoTransform()">
                                <span id="zoom-label" class="value-label">1.0x</span>
                            </div>
                            <div class="control-row">
                                <i class="fas fa-redo"></i>
                                <input type="range" id="rotate-slider" min="-180" max="180" step="5" value="0" oninput="updatePhotoTransform()">
                                <span id="rotate-label" class="value-label">0°</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Save Student
                </button>
                <button type="button" onclick="resetSingleForm()" class="btn-secondary">
                    <i class="fas fa-undo"></i> Cancel
                </button>
                <span class="note"><i class="fas fa-asterisk" style="color: #EF4444; font-size: 8px;"></i> Marked fields are mandatory</span>
            </div>

        </form>
    </div>

    <!-- ===================== VIEW: MULTI STUDENT ===================== -->
    <div id="view-multi-student" class="hidden">
        <div class="section-card">
            <div class="section-header">
                <h3><i class="fas fa-users"></i> Add Multi Student</h3>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <button onclick="addMultiRow()" class="btn-primary" style="padding: clamp(4px, 0.6vw, 8px) clamp(10px, 1.2vw, 18px); font-size: clamp(10px, 0.9vw, 13px);">
                        <i class="fas fa-plus"></i> Add Row
                    </button>
                    <button onclick="saveMultiStudents()" class="btn-success" style="padding: clamp(4px, 0.6vw, 8px) clamp(10px, 1.2vw, 18px); font-size: clamp(10px, 0.9vw, 13px);">
                        <i class="fas fa-database"></i> Submit
                    </button>
                    <button onclick="addLocality()" class="btn-secondary" style="padding: clamp(4px, 0.6vw, 8px) clamp(10px, 1.2vw, 18px); font-size: clamp(10px, 0.9vw, 13px);">
                        <i class="fas fa-map-marker-alt"></i> Add Locality
                    </button>
                    <a href="<?php echo BASE_URL; ?>manage_students.php" class="btn-secondary" style="padding: clamp(4px, 0.6vw, 8px) clamp(10px, 1.2vw, 18px); font-size: clamp(10px, 0.9vw, 13px); text-decoration: none;">
                        <i class="fas fa-eye"></i> View Student
                    </a>
                </div>
            </div>
            <div class="section-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="fieldset-box">
                        <label class="fieldset-label">Session</label>
                        <select id="multi-session" class="fieldset-select">
                            <?php foreach ($sessions as $s): ?>
                            <option value="<?php echo e($s); ?>" <?php echo ($s === $cur_session) ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fieldset-box">
                        <label class="fieldset-label">Class</label>
                        <select id="multi-class-select" class="fieldset-select">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $cl): ?>
                            <option value="<?php echo $cl['class_id']; ?>"><?php echo e($cl['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="min-width: 150px;">Student Name</th>
                                <th style="min-width: 150px;">Father Name</th>
                                <th style="min-width: 130px;">Cell</th>
                                <th style="min-width: 100px;">Fee</th>
                                <th style="min-width: 120px;">Initial Balance</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="multi-student-tbody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== VIEW: IMPORT CSV ===================== -->
    <div id="view-import-student" class="hidden">
        <div class="section-card">
            <div class="section-header">
                <h3><i class="fas fa-file-csv"></i> Import Student Data</h3>
            </div>
            <div class="section-body">
                <p style="font-size: 13px; color: var(--gray-500); margin-bottom: 16px;">
                    Upload the CSV template to stage student data, review it below, then save it into the software.
                </p>
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 8px;">
                    <button onclick="document.getElementById('import-csv-input').click()" class="btn-primary" style="padding: clamp(6px, 0.8vw, 10px) clamp(14px, 1.5vw, 22px); font-size: clamp(11px, 1vw, 14px);">
                        <i class="fas fa-upload"></i> Choose File
                    </button>
                    <input type="file" id="import-csv-input" accept=".csv" class="hidden" onchange="triggerCSVImport(this)">
                    <span id="csv-file-name" style="font-size: 13px; color: var(--gray-500);">No file chosen</span>
                    <button onclick="saveImportedData()" class="btn-success" style="padding: clamp(6px, 0.8vw, 10px) clamp(14px, 1.5vw, 22px); font-size: clamp(11px, 1vw, 14px);">
                        <i class="fas fa-database"></i> Import
                    </button>
                </div>
                <p style="font-size: 12px; color: var(--gray-400);">
                    <i class="fas fa-info-circle"></i> Only CSV files are accepted — match the class/section names from the downloaded template for best accuracy.
                </p>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header">
                <h3><i class="fas fa-list"></i> Import Data List</h3>
                <span id="csv-count" style="font-size: 12px; color: var(--gray-400);">0 records</span>
            </div>
            <div class="section-body" style="padding: 0;">
                <div style="display: flex; align-items: center; gap: 12px; padding: 10px 20px; border-bottom: 1px solid var(--gray-200); background: var(--gray-50);">
                    <input type="text" id="csv-search" placeholder="Search imported records..." oninput="filterCSVTable()" style="flex: 1; min-width: 120px; height: clamp(30px, 3vw, 38px); padding: 0 clamp(10px, 1vw, 14px); border-radius: 6px; border: 1px solid var(--gray-200); font-size: clamp(11px, 1vw, 13px); outline: none; transition: border-color 0.2s;">
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40px;"><i class="fas fa-check" style="color: var(--gray-300);"></i></th>
                                <th style="width: 50px;">S.No</th>
                                <th style="min-width: 140px;">Student Name</th>
                                <th style="min-width: 140px;">Father Name</th>
                                <th style="min-width: 120px;">Class Name</th>
                                <th style="min-width: 120px;">Section Name</th>
                                <th style="min-width: 110px;">Cell Number</th>
                                <th style="min-width: 90px;">Gender</th>
                                <th style="min-width: 90px;">Religion</th>
                                <th style="min-width: 100px;">Date of Birth</th>
                                <th style="min-width: 110px;">Admission Date</th>
                                <th style="min-width: 100px;">Admission No</th>
                                <th style="min-width: 90px;">Sibling Code</th>
                                <th style="min-width: 90px;">Family Code</th>
                                <th style="min-width: 120px;">Course Package</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="csv-table-body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<script>
var HIIFI_BASE = '<?php echo BASE_URL; ?>';

// ==================== SINGLE STUDENT ====================
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
    document.addEventListener('mousemove', onPosPhotoMove);
    document.addEventListener('mouseup', endPhotoDrag);
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

function endPhotoDrag() {
    _photoDrag = null;
    document.removeEventListener('mousemove', onPosPhotoMove);
    document.removeEventListener('mouseup', endPhotoDrag);
}

function switchMainView(view) {
    if (view === 'form') { window.location = HIIFI_BASE + 'adm_form.php'; return; }
    var views = { 'single': 'view-single-student', 'multi': 'view-multi-student', 'import': 'view-import-student' };
    Object.keys(views).forEach(function (k) {
        document.getElementById(views[k]).classList.toggle('hidden', k !== view);
    });
    document.querySelectorAll('.main-tabs .tab-btn').forEach(function (b) { b.classList.remove('active'); });
    var btn = document.getElementById('tab-btn-' + view);
    if (btn) btn.classList.add('active');
    if (view === 'multi' && multiIndex <= 1) addMultiRow();
}

function switchFormTab(tab) {
    var tabs = { 'basic': 'form-basic', 'parent': 'form-parent', 'academic': 'form-academic', 'contact': 'form-contact', 'documents': 'form-documents' };
    Object.keys(tabs).forEach(function (k) {
        document.getElementById(tabs[k]).classList.toggle('hidden', k !== tab);
    });
    document.querySelectorAll('.subtab-btn').forEach(function (b) { b.classList.remove('active'); });
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
    var types = { success: 'success', warning: 'warning', info: 'info', error: 'error' };
    var icons = { success: 'fa-check-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle', error: 'fa-exclamation-circle' };
    var el = document.createElement('div');
    el.className = 'toast-item ' + (types[type] || types.info);
    el.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '"></i><span>' + msg + '</span>';
    document.getElementById('toast-container').appendChild(el);
    setTimeout(function () { el.style.opacity = '0'; el.style.transform = 'translateX(30px)'; setTimeout(function () { el.remove(); }, 300); }, 3200);
}

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
        document.getElementById('photo-placeholder').style.display = 'none';
        document.getElementById('photo-frame').style.display = 'flex';
        document.getElementById('photo-controls').classList.add('active');
    };
    reader.readAsDataURL(file);
}

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
    document.getElementById('photo-placeholder').style.display = 'flex';
    document.getElementById('photo-frame').style.display = 'none';
    document.getElementById('photo-controls').classList.remove('active');
}

function handleSaveStudent(e) {
    e.preventDefault();
    var form = document.getElementById('single-student-form');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    form.submit();
}

function previewStudentDoc(input, idx) {
    var file = input.files && input.files[0];
    var status = document.getElementById('docStatus_' + idx);
    var nameEl = document.getElementById('docFileName_' + idx);
    if (!file) {
        if (status) { status.textContent = 'Not Uploaded'; status.className = 'doc-status'; }
        if (nameEl) nameEl.textContent = '';
        return;
    }
    if (status) {
        status.textContent = 'Uploaded';
        status.className = 'doc-status uploaded';
    }
    if (nameEl) { nameEl.textContent = file.name; }
}

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

var stateMap = <?php echo json_encode($stateMapDef); ?>;

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

// ==================== MULTI STUDENT ====================
var multiIndex = 0;
var multiClassOptions = '<?php foreach ($classes as $cl): ?><option value="<?php echo $cl['class_id']; ?>"><?php echo e($cl['class_name']); ?></option><?php endforeach; ?>';

function addMultiRow() {
    multiIndex++;
    var tr = document.createElement('tr');
    tr.id = 'multi-row-' + multiIndex;
    tr.innerHTML =
        '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-400); font-size: clamp(11px, 1vw, 13px); text-align:center;">' + multiIndex + '</td>' +
        '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px);"><div class="fieldset-box" style="min-height: clamp(32px, 3.5vw, 44px); padding: clamp(8px, 0.8vw, 12px) clamp(8px, 0.8vw, 12px) clamp(2px, 0.3vw, 6px) clamp(8px, 0.8vw, 12px);"><label class="fieldset-label" style="font-size: clamp(8px, 0.7vw, 10px); top: -6px;">Student Name</label><input id="multi-name-' + multiIndex + '" class="fieldset-input" placeholder="Student Name" style="font-size: clamp(11px, 1vw, 13px);"></div></td>' +
        '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px);"><div class="fieldset-box" style="min-height: clamp(32px, 3.5vw, 44px); padding: clamp(8px, 0.8vw, 12px) clamp(8px, 0.8vw, 12px) clamp(2px, 0.3vw, 6px) clamp(8px, 0.8vw, 12px);"><label class="fieldset-label" style="font-size: clamp(8px, 0.7vw, 10px); top: -6px;">Father Name</label><input id="multi-father-' + multiIndex + '" class="fieldset-input" placeholder="Father Name" style="font-size: clamp(11px, 1vw, 13px);"></div></td>' +
        '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px);"><div class="fieldset-box" style="min-height: clamp(32px, 3.5vw, 44px); padding: clamp(8px, 0.8vw, 12px) clamp(8px, 0.8vw, 12px) clamp(2px, 0.3vw, 6px) clamp(8px, 0.8vw, 12px);"><label class="fieldset-label" style="font-size: clamp(8px, 0.7vw, 10px); top: -6px;">Cell</label><input id="multi-cell-' + multiIndex + '" class="fieldset-input" placeholder="03XX-XXXXXXX" style="font-size: clamp(11px, 1vw, 13px);"></div></td>' +
        '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px);"><div class="fieldset-box" style="min-height: clamp(32px, 3.5vw, 44px); padding: clamp(8px, 0.8vw, 12px) clamp(8px, 0.8vw, 12px) clamp(2px, 0.3vw, 6px) clamp(8px, 0.8vw, 12px);"><label class="fieldset-label" style="font-size: clamp(8px, 0.7vw, 10px); top: -6px;">Fee</label><input id="multi-fee-' + multiIndex + '" class="fieldset-input" placeholder="0.00" style="font-size: clamp(11px, 1vw, 13px);"></div></td>' +
        '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px);"><div class="fieldset-box" style="min-height: clamp(32px, 3.5vw, 44px); padding: clamp(8px, 0.8vw, 12px) clamp(8px, 0.8vw, 12px) clamp(2px, 0.3vw, 6px) clamp(8px, 0.8vw, 12px);"><label class="fieldset-label" style="font-size: clamp(8px, 0.7vw, 10px); top: -6px;">Initial Balance</label><input id="multi-balance-' + multiIndex + '" class="fieldset-input" placeholder="0.00" style="font-size: clamp(11px, 1vw, 13px);"></div></td>' +
        '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); text-align: center;"><button onclick="removeMultiRow(' + multiIndex + ')" style="width: clamp(24px, 2.5vw, 32px); height: clamp(24px, 2.5vw, 32px); border-radius: 6px; border: none; background: transparent; color: #EF4444; cursor: pointer; font-size: clamp(11px, 1vw, 13px);"><i class="fas fa-trash-alt"></i></button></td>';
    document.getElementById('multi-student-tbody').appendChild(tr);
}

function removeMultiRow(rowId) {
    var el = document.getElementById('multi-row-' + rowId);
    if (el) { el.remove(); showToast('Row removed', 'info'); }
}

function addLocality() {
    window.location = HIIFI_BASE + 'manage_localities.php';
}

function saveMultiStudents() {
    var rows = [];
    var session = document.getElementById('multi-session').value;
    var classId = document.getElementById('multi-class-select').value;
    if (!classId) { showToast('Please select a class first', 'warning'); return; }
    document.querySelectorAll('#multi-student-tbody tr').forEach(function (tr) {
        var name = (tr.querySelector('input[id^="multi-name-"]') || {}).value || '';
        var father = (tr.querySelector('input[id^="multi-father-"]') || {}).value || '';
        var cell = (tr.querySelector('input[id^="multi-cell-"]') || {}).value || '';
        var fee = (tr.querySelector('input[id^="multi-fee-"]') || {}).value || '0';
        var balance = (tr.querySelector('input[id^="multi-balance-"]') || {}).value || '0';
        if (name) rows.push({ name: name, father: father, cell: cell, class_id: classId, fee: fee, balance: balance });
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
    xhr.send('action=SaveMultiStudents&rows=' + encodeURIComponent(JSON.stringify(rows)) + '&session=' + encodeURIComponent(session) + '&class_id=' + encodeURIComponent(classId));
}

// ==================== CSV IMPORT ====================
var csvRecords = [];

function renderCSVTable(data) {
    var tbody = document.getElementById('csv-table-body');
    tbody.innerHTML = '';
    var rows = data || csvRecords;
    rows.forEach(function (r) {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); text-align: center;"><input type="checkbox" checked style="width: clamp(12px, 1.2vw, 16px); height: clamp(12px, 1.2vw, 16px); accent-color: var(--primary);"></td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-400); font-size: clamp(11px, 1vw, 13px);">' + r.SNo + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); font-weight: 500; color: var(--gray-700);">' + (r.StudentName || '') + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-500);">' + (r.FatherName || '') + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-500);">' + (r.Class || '') + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-500);">' + (r.Section || '') + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-500);">' + (r.CellNo || '') + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-500);">' + (r.Gender || '') + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-500);">' + (r.Religion || '') + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-500);">' + (r.DOB || '') + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-500);">' + (r.AdmissionDate || '') + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-500);">' + (r.AdmissionNo || '') + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-500);">' + (r.SiblingCode || '') + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-500);">' + (r.FamilyCode || '') + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); color: var(--gray-500);">' + (r.CoursePackage || '') + '</td>' +
            '<td style="padding: clamp(4px, 0.6vw, 10px) clamp(8px, 0.8vw, 14px); text-align: center;"><button onclick="deleteCSVRecord(' + r.SNo + ')" style="width: clamp(24px, 2.5vw, 32px); height: clamp(24px, 2.5vw, 32px); border-radius: 6px; border: none; background: transparent; color: #EF4444; cursor: pointer; font-size: clamp(11px, 1vw, 13px);"><i class="fas fa-trash-alt"></i></button></td>';
        tbody.appendChild(tr);
    });
    document.getElementById('csv-count').textContent = rows.length + ' records';
}

function filterCSVTable() {
    var q = (document.getElementById('csv-search').value || '').toLowerCase();
    var filtered = csvRecords.filter(function (r) {
        return (r.StudentName || '').toLowerCase().indexOf(q) !== -1 ||
               (r.FatherName || '').toLowerCase().indexOf(q) !== -1 ||
               (r.Class || '').toLowerCase().indexOf(q) !== -1 ||
               (r.CellNo || '').toLowerCase().indexOf(q) !== -1;
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
    document.getElementById('csv-file-name').textContent = file.name;
    var reader = new FileReader();
    reader.onload = function (ev) {
        var text = ev.target.result;
        csvRecords = parseCSV(text);
        if (csvRecords.length === 0) {
            showToast('No valid rows found in CSV', 'warning');
            input.value = '';
            document.getElementById('csv-file-name').textContent = 'No file chosen';
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
    var classCol = findCol(hIdx, ['class', 'class name', 'cls', 'class name']);
    var sectionCol = findCol(hIdx, ['section', 'section name', 'sec']);
    var genderCol = findCol(hIdx, ['gender', 'sex']);
    var religionCol = findCol(hIdx, ['religion', 'faith']);
    var dobCol = findCol(hIdx, ['dob', 'date of birth', 'birthdate']);
    var admDateCol = findCol(hIdx, ['admission date', 'admission', 'adm date']);
    var admNoCol = findCol(hIdx, ['admission no', 'adm no', 'admission number']);
    var siblingCol = findCol(hIdx, ['sibling code', 'sibling']);
    var familyCol = findCol(hIdx, ['family code', 'family']);
    var courseCol = findCol(hIdx, ['course package', 'course', 'package']);
    
    if (nameCol === -1) return [];
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
            Class: classCol !== -1 ? (c[classCol] || '').trim() : '',
            Section: sectionCol !== -1 ? (c[sectionCol] || '').trim() : '',
            Gender: genderCol !== -1 ? (c[genderCol] || '').trim() : '',
            Religion: religionCol !== -1 ? (c[religionCol] || '').trim() : '',
            DOB: dobCol !== -1 ? (c[dobCol] || '').trim() : '',
            AdmissionDate: admDateCol !== -1 ? (c[admDateCol] || '').trim() : '',
            AdmissionNo: admNoCol !== -1 ? (c[admNoCol] || '').trim() : '',
            SiblingCode: siblingCol !== -1 ? (c[siblingCol] || '').trim() : '',
            FamilyCode: familyCol !== -1 ? (c[familyCol] || '').trim() : '',
            CoursePackage: courseCol !== -1 ? (c[courseCol] || '').trim() : ''
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

function saveImportedData() {
    if (csvRecords.length === 0) { showToast('No records to import', 'warning'); return; }
    var rows = csvRecords.map(function (r) {
        return { 
            name: r.StudentName, 
            father: r.FatherName, 
            cell: r.CellNo, 
            class: r.Class,
            section: r.Section,
            gender: r.Gender,
            religion: r.Religion,
            dob: r.DOB,
            admission_date: r.AdmissionDate,
            admission_no: r.AdmissionNo,
            sibling_code: r.SiblingCode,
            family_code: r.FamilyCode,
            course_package: r.CoursePackage
        };
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
            document.getElementById('csv-file-name').textContent = 'No file chosen';
        } else {
            showToast('Import failed', 'error');
        }
    };
    xhr.send('action=ImportCSV&rows=' + encodeURIComponent(JSON.stringify(rows)) + '&session=' + encodeURIComponent('<?php echo e($cur_session); ?>'));
}

// ==================== INITIALIZE ====================
window.onload = function () {
    multiIndex = 0;
    for (var i = 0; i < 3; i++) addMultiRow();
    csvRecords = [];
    renderCSVTable();

    if (window.flatpickr) {
        flatpickr('#dob', { dateFormat: 'd/m/Y' });
        flatpickr('#date_of_adms', { dateFormat: 'd/m/Y' });
    }
};
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>