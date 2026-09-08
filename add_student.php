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

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($page_title . ' | ' . get_setting('school_name', 'HIFILMS')); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                brand: {
                    orange: '#FF6B2C',
                    orangeHover: '#EA5A1F',
                    sidebar: '#161922',
                    sidebarActive: '#232735',
                    bg: '#f5f6f8',
                    border: '#e7e7e7',
                    textDark: '#1f2430',
                    textMuted: '#6b7280',
                    blueBtn: '#3B82F6',
                    greenBtn: '#22C55E',
                    redBtn: '#EF4444'
                }
            },
            fontFamily: {
                sans: ['Inter', 'sans-serif']
            }
        }
    }
}
</script>
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
</head>
<body class="flex h-screen overflow-hidden bg-slate-100 antialiased">

<?php
$navItems = [
    ['title' => 'Front Office',       'icon' => 'fa-solid fa-building-columns',          'href' => 'student_inquiry.php'],
    ['title' => 'Dashboard',          'icon' => 'fa-solid fa-gauge-high',                'href' => 'dashboard.php'],
    ['title' => 'Students',           'icon' => 'fa-solid fa-user-graduate',             'href' => 'manage_students.php'],
    ['title' => 'Attendance',         'icon' => 'fa-solid fa-clipboard-user',            'href' => 'mark_attend.php'],
    ['title' => 'Messages',           'icon' => 'fa-solid fa-comments',                  'href' => 'messages_history.php'],
    ['title' => 'Fee Collection',     'icon' => 'fa-solid fa-hand-holding-dollar',       'href' => 'fee_challans.php'],
    ['title' => 'Examination',        'icon' => 'fa-solid fa-file-pen',                  'href' => 'manage_exams.php'],
    ['title' => 'Timetable',          'icon' => 'fa-solid fa-calendar-days',             'href' => 'class_period.php'],
    ['title' => 'Employees / HR',     'icon' => 'fa-solid fa-users',                     'href' => 'view_emp.php'],
    ['title' => 'Datesheet',          'icon' => 'fa-solid fa-table-list',                'href' => 'view_datesheet.php'],
    ['title' => 'Transport',          'icon' => 'fa-solid fa-bus-simple',                'href' => 'vehicles.php'],
];
$topUserName = e($_SESSION['user_name'] ?? 'Admin');
$topUserRole = e($_SESSION['user_role'] ?? 'admin');
$initials = strtoupper(substr(trim(preg_replace('/[^A-Za-z]/', '', $topUserName)), 0, 1) ?: 'A');
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

<!-- ===================== LEFT SIDEBAR ===================== -->
<aside id="sidebar" class="fixed z-40 inset-y-0 left-0 w-[230px] flex flex-col bg-brand-sidebar text-slate-300 transition-transform duration-300 -translate-x-full lg:translate-x-0">
    <div class="h-[56px] shrink-0 flex items-center gap-2.5 px-4 border-b border-white/[0.08]">
        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-brand-orange to-amber-500 flex items-center justify-center text-white font-bold text-sm">H</div>
        <div class="min-w-0">
            <p class="text-white font-semibold text-sm leading-tight truncate"><?php echo e(get_setting('school_name', 'HIFILMS')); ?></p>
            <p class="text-[10px] text-slate-400 leading-tight">Session <?php echo e(get_setting('session_year', '2026-2027')); ?></p>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <p class="px-2 text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2">Menu</p>
        <?php foreach ($navItems as $ni): ?>
        <a href="<?php echo BASE_URL . $ni['href']; ?>" onclick="closeSidebar()" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium text-slate-300 hover:bg-white/[0.06] hover:text-white transition">
            <i class="<?php echo $ni['icon']; ?> w-4 text-center text-slate-400"></i>
            <span><?php echo $ni['title']; ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="px-3 py-3 border-t border-white/[0.08]">
        <a href="<?php echo BASE_URL; ?>logout.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium text-red-400 hover:bg-red-500/10 hover:text-red-300 transition">
            <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Mobile backdrop -->
<div id="sidebar-backdrop" class="fixed inset-0 z-30 bg-black/50 hidden lg:hidden" onclick="closeSidebar()"></div>

<!-- ===================== MAIN AREA ===================== -->
<div class="flex-1 flex flex-col min-w-0 w-full lg:pl-[230px]">

    <!-- Top Header -->
    <header class="h-[56px] shrink-0 bg-white border-b border-brand-border flex items-center px-4 gap-3">
        <button onclick="toggleSidebar()" class="w-9 h-9 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-500 transition lg:hidden">
            <i class="fa-solid fa-bars-staggered text-[15px]"></i>
        </button>
        <div class="relative w-full max-w-[420px] hidden md:block">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" placeholder="Search students, staff, reports..." class="w-full h-9 pl-9 pr-3 rounded-lg bg-slate-100 text-[13px] text-slate-700 outline-none focus:ring-2 focus:ring-brand-orange/50 placeholder:text-slate-400">
        </div>
        <select class="hidden lg:block h-9 px-3 rounded-lg bg-slate-100 text-[12px] text-slate-600 outline-none focus:ring-2 focus:ring-brand-orange/50 border-none">
            <option>Quick Links</option>
            <option>Admission Form</option>
            <option>Fee Collection</option>
            <option>Attendance</option>
            <option>Result Cards</option>
        </select>
        <select class="hidden sm:block h-9 px-3 rounded-lg bg-slate-100 text-[12px] text-slate-600 outline-none focus:ring-2 focus:ring-brand-orange/50 border-none">
            <option>2026-2027</option>
            <option>2025-2026</option>
            <option>2024-2025</option>
        </select>

        <div class="ml-auto flex items-center gap-1.5">
            <button class="w-9 h-9 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-500 transition"><i class="fa-brands fa-whatsapp text-[17px] text-emerald-500"></i></button>
            <button class="w-9 h-9 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-500 transition relative"><i class="fa-solid fa-envelope text-[15px]"></i><span class="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-brand-orange"></span></button>
            <button class="w-9 h-9 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-500 transition relative"><i class="fa-solid fa-bell text-[15px]"></i><span class="absolute top-1.5 right-1.5 w-4 h-4 rounded-full bg-red-500 text-white text-[9px] flex items-center justify-center font-bold">3</span></button>
            <div class="flex items-center gap-2 pl-2 ml-1 border-l border-brand-border">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-brand-orange to-amber-500 text-white text-[12px] font-bold flex items-center justify-center"><?php echo $initials; ?></div>
                <div class="hidden sm:block leading-tight">
                    <p class="text-[12px] font-semibold text-slate-700"><?php echo $topUserName; ?></p>
                    <p class="text-[10px] text-slate-400"><?php echo ucfirst($topUserRole); ?></p>
                </div>
            </div>
        </div>
    </header>

    <!-- Content -->
    <main id="main-content" class="flex-1 overflow-y-auto p-4 sm:p-5">

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
                <i class="fa-solid fa-user-plus"></i> Add New Student
            </button>
            <button id="tab-btn-multi" onclick="switchMainView('multi')" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-transparent whitespace-nowrap shrink-0">
                <i class="fa-solid fa-users"></i> Add Multi Students
            </button>
            <button id="tab-btn-import" onclick="switchMainView('import')" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-transparent whitespace-nowrap shrink-0">
                <i class="fa-solid fa-file-csv"></i> Import CSV
            </button>
            <button id="tab-btn-form" onclick="switchMainView('form')" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-transparent whitespace-nowrap shrink-0">
                <i class="fa-solid fa-file-lines"></i> Admission Form
            </button>
        </div>

        <!-- ===================== VIEW: SINGLE STUDENT ===================== -->
        <div id="view-single-student">

            <!-- Step Tracker -->
            <div class="bg-white border border-brand-border rounded-xl p-4 mb-5">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-brand-orange text-white text-[13px] font-bold flex items-center justify-center shadow">1</div>
                        <div>
                            <p class="text-[13px] font-semibold text-slate-700">Student Info</p>
                            <p class="text-[11px] text-slate-400">Name, class, contact details</p>
                        </div>
                    </div>
                    <div class="hidden md:block w-16 border-t-2 border-dashed border-slate-200"></div>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-slate-200 text-slate-500 text-[13px] font-bold flex items-center justify-center">2</div>
                        <div>
                            <p class="text-[13px] font-semibold text-slate-700">Fee &amp; Parent Info</p>
                            <p class="text-[11px] text-slate-400">Guardians &amp; fee plan setup</p>
                        </div>
                    </div>
                    <div class="hidden md:block w-16 border-t-2 border-dashed border-slate-200"></div>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-slate-200 text-slate-500 text-[13px] font-bold flex items-center justify-center">3</div>
                        <div>
                            <p class="text-[13px] font-semibold text-slate-700">Submission</p>
                            <p class="text-[11px] text-slate-400">Documents &amp; final review</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inner Tabs -->
            <div class="flex items-center gap-1 mb-4 overflow-x-auto">
                <button id="subtab-basic" onclick="switchFormTab('basic')" class="subtab-btn active flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-brand-border bg-white whitespace-nowrap shrink-0">
                    <i class="fa-solid fa-id-card"></i> Basic Info
                </button>
                <button id="subtab-parent" onclick="switchFormTab('parent')" class="subtab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-brand-border bg-white whitespace-nowrap shrink-0">
                    <i class="fa-solid fa-people-roof"></i> Parent &amp; Guardian Info
                </button>
                <button id="subtab-academic" onclick="switchFormTab('academic')" class="subtab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-brand-border bg-white whitespace-nowrap shrink-0">
                    <i class="fa-solid fa-graduation-cap"></i> Academic Info
                </button>
                <button id="subtab-contact" onclick="switchFormTab('contact')" class="subtab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] text-slate-500 border border-brand-border bg-white whitespace-nowrap shrink-0">
                    <i class="fa-solid fa-address-book"></i> Contact Info
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
                                        <select name="board_council" id="board_council" class="fieldset-select">
                                            <option value="">Select Board</option>
                                            <?php foreach ($boards as $b): ?>
                                            <option value="<?php echo $b['id']; ?>"><?php echo e($b['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Group / Shift</label>
                                        <select name="group_shift" id="group_shift" class="fieldset-select">
                                            <option value="">Select Group</option>
                                            <?php foreach ($groups as $g): ?>
                                            <option value="<?php echo $g['id']; ?>"><?php echo e($g['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Admission Source</label>
                                        <select name="adm_source" id="adm_source" class="fieldset-select">
                                            <option value="">Select Source</option>
                                            <?php foreach ($admSrcs as $a): ?>
                                            <option value="<?php echo $a['id']; ?>"><?php echo e($a['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Date of Birth</label>
                                        <input type="date" class="fieldset-input" name="dob" id="dob" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Date of Admission</label>
                                        <input type="date" class="fieldset-input" name="date_of_adms" id="date_of_adms" value="<?php echo date('Y-m-d'); ?>">
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
                                        <select name="Locality" id="locality" class="fieldset-select">
                                            <option value="">Select Locality</option>
                                            <?php foreach ($localities as $loc): ?>
                                            <option value="<?php echo $loc['locality_id']; ?>"><?php echo e($loc['locality_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
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
                                        <select name="father_occupation" id="father_occupation" class="fieldset-select">
                                            <option value="">Select Occupation</option>
                                            <?php foreach ($occupations as $o): ?>
                                            <option value="<?php echo $o['id']; ?>"><?php echo e($o['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Father Cell No</label>
                                        <input type="text" class="fieldset-input" name="father_cellno" id="father_cellno" placeholder="03XX-XXXXXXX" inputmode="tel">
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
                                        <label class="fieldset-label">Whatsapp Number</label>
                                        <input type="text" class="fieldset-input" name="whatsapp_number" id="whatsapp_number" placeholder="03XX-XXXXXXX" inputmode="tel">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Home Number</label>
                                        <input type="text" class="fieldset-input" name="home_number" id="home_number" placeholder="Landline (optional)">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Place of Birth</label>
                                        <input type="text" class="fieldset-input" name="place_of_birth" id="place_of_birth" placeholder="City of birth">
                                    </div>
                                    <div class="fieldset-box">
                                        <label class="fieldset-label">Email</label>
                                        <input type="email" class="fieldset-input" name="email" id="email" placeholder="student@email.com">
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
                                    <div class="fieldset-box col-span-2">
                                        <label class="fieldset-label">Home Address</label>
                                        <textarea class="fieldset-input fieldset-area" name="address" id="address" placeholder="Complete residential address"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- DOCUMENTS TAB -->
                        <div id="form-documents" class="hidden">
                            <div class="bg-white border border-brand-border rounded-xl p-5">
                                <h3 class="text-[13px] font-bold text-slate-700 mb-1 flex items-center gap-2">
                                    <i class="fa-solid fa-paperclip text-brand-orange"></i> Attached Documents
                                </h3>
                                <p class="text-[11px] text-slate-400 mb-4">Upload PDF, JPG or PNG files. Multiple documents supported.</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <?php foreach ($docTitles as $di => $doc): ?>
                                    <div id="doc-card-<?php echo $doc['id']; ?>" class="border border-dashed border-brand-border rounded-xl p-3">
                                        <input type="hidden" name="doc_types[]" value="<?php echo e($doc['name']); ?>">
                                        <label class="text-[12px] font-semibold text-slate-600 block mb-2 leading-tight"><?php echo e($doc['name']); ?></label>
                                        <div class="flex items-center justify-between gap-2">
                                            <input type="file" name="doc_files[]" class="text-[10px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[11px] file:font-medium file:bg-orange-50 file:text-brand-orange hover:file:bg-orange-100 cursor-pointer file:cursor-pointer" accept=".pdf,.jpg,.jpeg,.png" onchange="previewStudentDoc(this)">
                                            <button type="button" onclick="openModal('Document: <?php echo e($doc['name']); ?>')" class="shrink-0 w-6 h-6 rounded-md text-brand-orange hover:bg-orange-50 flex items-center justify-center" title="More options"><i class="fa-solid fa-ellipsis-vertical text-[11px]"></i></button>
                                        </div>
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
                                <img id="photo-preview" class="w-full h-full object-cover absolute inset-0" style="transform-origin:center;">
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
</div>

<!-- ===================== MODAL ===================== -->
<div id="add-modal" class="hidden flex items-center justify-center fixed inset-0 z-50 bg-black/50">
    <div class="bg-white rounded-xl w-[420px] max-w-[95%] shadow-xl">
        <div class="flex items-center justify-between px-4 py-3 border-b border-brand-border">
            <h3 id="modal-title" class="text-[14px] font-bold text-slate-700">Add New</h3>
            <button onclick="closeModal()" class="w-7 h-7 rounded-lg hover:bg-slate-100 text-slate-400 flex items-center justify-center transition"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="p-4 space-y-3">
            <div class="fieldset-box">
                <label id="modal-field-label" class="fieldset-label">Title</label>
                <input type="text" id="modal-input" class="fieldset-input" placeholder="Enter value">
            </div>
            <div class="flex items-center justify-end gap-2 pt-1">
                <button onclick="closeModal()" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-[12px] font-medium transition">Cancel</button>
                <button onclick="submitModalItem()" class="px-5 py-2 rounded-lg bg-brand-orange hover:bg-brand-orangeHover text-white text-[12px] font-semibold transition"><i class="fa-solid fa-check"></i> Save</button>
            </div>
        </div>
    </div>
</div>

<!-- ===================== TOAST ===================== -->
<div id="toast-container" class="fixed top-4 right-4 z-[100] space-y-2 w-[320px] max-w-[90vw]"></div>

<script>
var HIIFI_BASE = '<?php echo BASE_URL; ?>';
var modalSaveCallback = null;

function toggleSidebar() {
    var sb = document.getElementById('sidebar');
    var bk = document.getElementById('sidebar-backdrop');
    sb.classList.toggle('-translate-x-full');
    if (bk) bk.classList.toggle('hidden');
}

function closeSidebar() {
    var sb = document.getElementById('sidebar');
    var bk = document.getElementById('sidebar-backdrop');
    if (sb) sb.classList.add('-translate-x-full');
    if (bk) bk.classList.add('hidden');
}

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

function openModal(title, label) {
    document.getElementById('modal-title').textContent = title || 'Add New';
    document.getElementById('modal-field-label').textContent = label || 'Title';
    document.getElementById('modal-input').value = '';
    document.getElementById('modal-input').focus();
    document.getElementById('add-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('add-modal').classList.add('hidden');
    modalSaveCallback = null;
}

function submitModalItem() {
    var val = document.getElementById('modal-input').value.trim();
    if (!val) { showToast('Please enter a value first', 'warning'); return; }
    if (modalSaveCallback) modalSaveCallback(val);
    closeModal();
    showToast('Saved successfully', 'success');
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
    var hasTransformation = zoomSlider && rotateSlider &&
        (parseFloat(zoomSlider.value) !== 1 || parseInt(rotateSlider.value) !== 0);
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
    ctx.drawImage(image, -drawWidth / 2, -drawHeight / 2, drawWidth, drawHeight);
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

function previewStudentDoc(input) {
    var file = input.files && input.files[0];
    if (!file) return;
    var card = input.closest('.border-dashed');
    var label = card.querySelector('.fieldset-label') || card.querySelector('label');
    if (label) showToast('File selected: ' + file.name, 'info');
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
    var rows = document.querySelectorAll('#multi-student-tbody tr');
    var count = 0;
    rows.forEach(function (tr) {
        var name = (tr.querySelector('input[id^="multi-name-"]') || {}).value || '';
        if (name) count++;
    });
    if (count === 0) { showToast('No students added yet', 'warning'); return; }
    showToast(count + ' students ready to save. Use Add Multi Students for bulk save.', 'success');
}

/* ---------------- CSV Import ---------------- */
var initialCSVData = [
    { SNo: 1, StudentName: 'Ahmed Raza', FatherName: 'Muhammad Raza', CellNo: '0300-1234567', Class: '10th' },
    { SNo: 2, StudentName: 'Fatima Bibi', FatherName: 'Abdul Ghafoor', CellNo: '0345-9876543', Class: '8th' },
    { SNo: 3, StudentName: 'Hassan Ali', FatherName: 'Ali Akbar', CellNo: '0333-1122334', Class: '9th' },
    { SNo: 4, StudentName: 'Ayesha Khan', FatherName: 'Naveed Khan', CellNo: '0312-4455667', Class: '7th' },
    { SNo: 5, StudentName: 'Bilal Ahmed', FatherName: 'Tariq Ahmed', CellNo: '0301-9988776', Class: '6th' },
    { SNo: 6, StudentName: 'Zainab Noor', FatherName: 'Imran Noor', CellNo: '0322-5566778', Class: '5th' },
    { SNo: 7, StudentName: 'Usman Malik', FatherName: 'Shahid Malik', CellNo: '0307-3344556', Class: '10th' }
];
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
    showToast('CSV imported: ' + file.name, 'success');
    csvRecords = initialCSVData.map(function (r) { return Object.assign({}, r); });
    renderCSVTable();
    switchMainView('import');
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
    var rows = document.querySelectorAll('#csv-table-body tr');
    if (rows.length === 0) { showToast('No records to import', 'warning'); return; }
    var count = rows.length;
    showToast(count + ' student(s) imported successfully', 'success');
    csvRecords = [];
    renderCSVTable();
}
function clearImportedData() {
    csvRecords = [];
    renderCSVTable();
    showToast('All records cleared', 'info');
}

window.onload = function () {
    multiIndex = 0;
    for (var i = 0; i < 5; i++) addMultiRow();
    csvRecords = initialCSVData.map(function (r) { return Object.assign({}, r); });
    renderCSVTable();
};
</script>
</body>
</html>