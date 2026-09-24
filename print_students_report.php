<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$classParam = trim((string) ($_GET['class_id'] ?? ''));
$section_id = (int) ($_GET['section'] ?? 0);
$session    = trim((string) ($_GET['session'] ?? ''));
$orderBy    = trim((string) ($_GET['orderBy'] ?? 'default'));
$heading    = trim((string) ($_GET['studnetlist'] ?? 'Students Class Wise List'));
if ($heading === '') { $heading = 'Students Class Wise List'; }

$fieldMap = [
    'grNo'             => ['label' => 'GR. No',                    'key' => 'gr_no'],
    'student_name'     => ['label' => 'Student Name',              'key' => 'student_name'],
    'father_name'      => ['label' => 'Father Name',               'key' => 'father_name'],
    'tution_fee'       => ['label' => 'Monthly Fee',               'key' => 'monthly_fee'],
    'std_transport_fee'=> ['label' => 'Transport Fee',             'key' => 'transport_fee'],
    'miscellaneous'    => ['label' => 'Misc.',                     'key' => 'miscellaneous_fee'],
    'loginid_paswrd'   => ['label' => 'Student Login Id & Password','key' => 'loginid_paswrd'],
    'cell_no'          => ['label' => 'SMS Reporting No',          'key' => 'phone'],
    'father_cellno'    => ['label' => 'Father No',                 'key' => 'father_cellno'],
    'mother_cell'      => ['label' => 'Mother No',                 'key' => 'mother_cell'],
    'home_number'      => ['label' => 'Home No',                   'key' => 'home_number'],
    'father_Bnumber'   => ['label' => 'Father Business No',        'key' => 'father_business_address'],
    'whatsapp_number'  => ['label' => 'WhatsApp No',               'key' => 'whatsapp_number'],
    'address'          => ['label' => 'Address',                   'key' => 'address'],
    'localities'       => ['label' => 'Localities',                'key' => 'locality_name'],
    'dob'              => ['label' => 'DOB',                       'key' => 'dob'],
    'date_admission'   => ['label' => 'Date of Admission',         'key' => 'admission_date'],
    'class_admited'    => ['label' => 'Admitted Class',            'key' => 'class_name'],
    'family_code'      => ['label' => 'Family Code',               'key' => 'family_code'],
    'cnic'             => ['label' => 'CNIC',                      'key' => 'father_cnic'],
    'B-Form'           => ['label' => 'B-Form',                    'key' => 'form_b_no'],
    'remarks_column'   => ['label' => 'Remarks',                   'key' => 'school_leaving_reason'],
    'occupation'       => ['label' => 'Occupation',                'key' => 'father_occupation'],
];

$selected = [];
foreach ($fieldMap as $name => $meta) {
    if (isset($_GET[$name])) { $selected[$name] = $meta; }
}
if (count($selected) === 0) { $selected = $fieldMap; }

$where = [];
$params = [];
$types = '';
if ($classParam !== '' && strcasecmp($classParam, 'All') !== 0) {
    $where[] = 's.class_id = ?';
    $params[] = (int) $classParam; $types .= 'i';
}
if ($section_id > 0) {
    $where[] = 's.section_id = ?';
    $params[] = $section_id; $types .= 'i';
}
if ($session !== '') {
    $where[] = 's.session = ?';
    $params[] = $session; $types .= 's';
}

$orderSql = 's.gr_no ASC, s.first_name ASC';
if ($orderBy === 'GRnoWise') { $orderSql = 's.gr_no ASC, s.first_name ASC'; }
elseif ($orderBy === 'asc') { $orderSql = 's.first_name ASC, s.last_name ASC'; }
elseif ($orderBy === 'Classwise') { $orderSql = 's.class_id ASC, s.section_id ASC, s.gr_no ASC'; }
elseif ($orderBy === 'default') { $orderSql = 's.student_id ASC'; }

$sql = "SELECT s.*, c.class_name, sec.section_name, l.locality_name,
               pa.username AS login_username, pa.password AS login_password
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.class_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        LEFT JOIN localities l ON s.locality_id = l.locality_id
        LEFT JOIN parent_access pa ON pa.student_id = s.student_id";
if (count($where) > 0) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY ' . $orderSql;

$students = [];
if (count($params) > 0) {
    $stmt = db_prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = db_query($sql);
}
while ($res && $row = $res->fetch_assoc()) { $students[] = $row; }

$si = school_info();
$schoolName = $si['name'];
$schoolLogo = $si['logo'];
$logoSrc = $schoolLogo !== '' ? BASE_URL . $schoolLogo : BASE_URL . 'assets/img/logo.jpg';

$fmt = function ($key, $row) {
    switch ($key) {
        case 'student_name':
            return trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        case 'loginid_paswrd':
            $u = $row['login_username'] ?? '';
            $p = $row['login_password'] ?? '';
            return ($u !== '' || $p !== '') ? ($u . ' / ' . $p) : '';
        case 'dob':
        case 'admission_date':
            $v = $row[$key] ?? '';
            return ($v && $v !== '0000-00-00') ? $v : '';
        case 'monthly_fee':
        case 'transport_fee':
        case 'miscellaneous_fee':
            return number_format((float) ($row[$key] ?? 0), 0);
        default:
            return (string) ($row[$key] ?? '');
    }
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo e($heading); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #000; margin: 10px; }
        .rpt-header { text-align: center; margin-bottom: 8px; }
        .rpt-header img { height: 55px; }
        .rpt-header .school { font-size: 20px; font-weight: bold; }
        .rpt-header .title { font-size: 15px; font-weight: bold; margin-top: 4px; }
        .rpt-meta { text-align: center; font-size: 11px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 3px 4px; }
        th { background: #e9e9e9; text-align: center; font-size: 10px; }
        td.center { text-align: center; }
        .no-print { text-align: center; margin-bottom: 10px; }
        .no-print button { padding: 6px 16px; font-size: 13px; cursor: pointer; }
        @media print {
            .no-print { display: none; }
            @page { size: A4 landscape; margin: 8mm; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>

    <div class="rpt-header">
        <?php if ($logoSrc !== ''): ?><img src="<?php echo e($logoSrc); ?>" alt=""><?php endif; ?>
        <div class="school"><?php echo e($schoolName); ?></div>
        <?php if (!empty($si['addr'])): ?><div><?php echo e($si['addr']); ?></div><?php endif; ?>
        <div class="title"><?php echo e($heading); ?></div>
    </div>
    <div class="rpt-meta">
        <?php
        $meta = [];
        if ($classParam !== '' && strcasecmp($classParam, 'All') !== 0) {
            foreach ($students as $s) { $meta[] = 'Class: ' . $s['class_name']; break; }
        } elseif (strcasecmp($classParam, 'All') === 0) {
            $meta[] = 'Class: All';
        }
        if ($session !== '') { $meta[] = 'Session: ' . $session; }
        $meta[] = 'Total Students: ' . count($students);
        echo e(implode('   |   ', $meta));
        ?>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:35px;">S#</th>
                <?php foreach ($selected as $name => $meta2): ?>
                <th><?php echo e($meta2['label']); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php $sn = 0; foreach ($students as $row): $sn++; ?>
            <tr>
                <td class="center"><?php echo $sn; ?></td>
                <?php foreach ($selected as $name => $meta2): ?>
                <td><?php echo e($fmt($meta2['key'], $row)); ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            <?php if (count($students) === 0): ?>
            <tr><td colspan="<?php echo count($selected) + 1; ?>" class="center">No students found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
