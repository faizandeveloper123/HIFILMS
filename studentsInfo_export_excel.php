<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$class_id   = (int) ($_GET['class_id'] ?? 0);
$section_id = (int) ($_GET['section'] ?? 0);

$where = [];
$params = [];
$types = '';
if ($class_id > 0) { $where[] = 's.class_id = ?'; $params[] = $class_id; $types .= 'i'; }
if ($section_id > 0) { $where[] = 's.section_id = ?'; $params[] = $section_id; $types .= 'i'; }

$sql = "SELECT s.gr_no, s.first_name, s.last_name, s.father_name, s.mother_name, s.gender, s.dob,
               s.religion, s.caste, s.phone, s.father_cellno, s.mother_cell, s.home_number,
               s.whatsapp_number, s.email, s.address, s.father_occupation, s.father_cnic,
               s.form_b_no, s.family_code, s.monthly_fee, s.transport_fee, s.miscellaneous_fee,
               s.admission_date, s.session, s.roll_no,
               c.class_name, sec.section_name, l.locality_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.class_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        LEFT JOIN localities l ON s.locality_id = l.locality_id";
if (count($where) > 0) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY s.gr_no ASC, s.first_name ASC';

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

$columns = [
    'GR No'          => 'gr_no',
    'Student Name'   => 'student_name',
    'Father Name'    => 'father_name',
    'Mother Name'    => 'mother_name',
    'Gender'         => 'gender',
    'DOB'            => 'dob',
    'Religion'       => 'religion',
    'Caste'          => 'caste',
    'Phone'          => 'phone',
    'Father Cell'    => 'father_cellno',
    'Mother Cell'    => 'mother_cell',
    'Home No'        => 'home_number',
    'WhatsApp'       => 'whatsapp_number',
    'Email'          => 'email',
    'Address'        => 'address',
    'Locality'       => 'locality_name',
    'Occupation'     => 'father_occupation',
    'CNIC'           => 'father_cnic',
    'B-Form'         => 'form_b_no',
    'Class'          => 'class_name',
    'Section'        => 'section_name',
    'Roll No'        => 'roll_no',
    'Session'        => 'session',
    'Admission Date' => 'admission_date',
    'Monthly Fee'    => 'monthly_fee',
    'Transport Fee'  => 'transport_fee',
    'Misc. Fee'      => 'miscellaneous_fee',
    'Family Code'    => 'family_code',
];

$val = function ($key, $row) {
    if ($key === 'student_name') { return trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); }
    if ($key === 'dob' || $key === 'admission_date') {
        $v = $row[$key] ?? '';
        return ($v && $v !== '0000-00-00') ? $v : '';
    }
    return (string) ($row[$key] ?? '');
};

$filename = 'Students_Info' . ($class_id > 0 ? '_Class' . $class_id : '') . '_' . date('Y-m-d') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF";
?>
<html>
<head>
<meta charset="utf-8">
<style>
    table { border-collapse: collapse; }
    th, td { border: 1px solid #999; padding: 4px 6px; font-family: Arial; font-size: 11pt; }
    th { background: #d9e1f2; font-weight: bold; }
</style>
</head>
<body>
<table>
    <thead>
        <tr>
            <?php foreach ($columns as $label => $key): ?>
            <th><?php echo e($label); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($students as $row): ?>
        <tr>
            <?php foreach ($columns as $label => $key): ?>
            <td><?php echo e($val($key, $row)); ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
